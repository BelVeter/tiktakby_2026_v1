<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FetchA1Recordings extends Command
{
    protected $signature   = 'a1:fetch-recordings {--period=90 : Период выборки в минутах}';
    protected $description = 'Скачивает записи звонков с A1 ВАТС и сохраняет в storage/app/a1_recordings/';

    private const TOKENS_FILE  = 'a1_tokens.json';
    private const BASE_URL     = 'https://vats.a1.by/crm-api/open-api/v1';
    private const QUOTA_BYTES  = 1_073_741_824; // 1 GB
    private const BUFFER_BYTES = 5_242_880;     // 5 MB buffer before download

    public function handle(): int
    {
        $lock = Cache::lock('a1_api_mutex', 120);
        if (!$lock->get()) {
            Log::warning('A1 Recordings: не удалось захватить a1_api_mutex, пропускаем запуск');
            return 0;
        }
        try {
            return $this->doHandle();
        } finally {
            $lock->release();
        }
    }

    private function doHandle(): int
    {
        $companyId = config('services.a1.company_id');
        $apiKey    = config('services.a1.api_key');

        if (!$companyId || !$apiKey) {
            $this->error('A1: не задан A1_COMPANY_ID или A1_API_KEY в .env');
            return 1;
        }

        // Determine period
        $end   = time();
        $isEmpty = !DB::table('a1_call_recordings')->exists();
        if ($isEmpty) {
            $start = $end - 30 * 86400; // first run: last 30 days
        } else {
            $periodMinutes = (int) $this->option('period');
            $start = $end - ($periodMinutes * 60);
        }

        // Auth
        try {
            $token = $this->getAccessToken($companyId, $apiKey);
        } catch (\Exception $e) {
            Log::error('A1 Recordings: ошибка авторизации — ' . $e->getMessage());
            $this->logFetch('error', $start, $end, 0, 0, 0, 0, 0, 0, $e->getMessage());
            return 1;
        }

        // Fetch list
        try {
            $records = $this->fetchRecordingsList($token, $companyId, $start, $end);

            if ($records === null) {
                Log::warning('A1 Recordings: токен отклонён (401/403), форсируем re-auth');
                $this->forgetTokens();
                $token   = $this->getAccessToken($companyId, $apiKey);
                $records = $this->fetchRecordingsList($token, $companyId, $start, $end);
            }

            if ($records === null) {
                throw new \RuntimeException('record/list: токен отклонён даже после re-auth');
            }
        } catch (\Exception $e) {
            Log::error('A1 Recordings: ошибка получения списка — ' . $e->getMessage());
            $this->logFetch('error', $start, $end, 0, 0, 0, 0, 0, 0, $e->getMessage());
            return 1;
        }

        $recordsFound = count($records);
        $this->line("A1 Recordings: найдено записей: {$recordsFound}");

        $recordsNew      = 0;
        $filesDownloaded = 0;
        $filesDeleted    = 0;
        $bytesDownloaded = 0;
        $bytesFreed      = 0;

        foreach ($records as $rec) {
            $recordName = $rec['recordName'] ?? null;
            $uuid       = $rec['uuid']       ?? null;

            if (!$recordName || !$uuid) {
                continue;
            }

            // Deduplication
            if (DB::table('a1_call_recordings')->where('record_name', $recordName)->exists()) {
                continue;
            }

            $recordsNew++;

            // Quota enforcement before download
            [$filesDeleted, $bytesFreed] = $this->enforceQuota($filesDeleted, $bytesFreed);

            // Download
            try {
                $bytes = $this->downloadRecording($token, $companyId, $recordName);
            } catch (\RuntimeException $e) {
                if (strpos($e->getMessage(), '404') !== false) {
                    $this->line("  skip (404): {$recordName}");
                    continue;
                }
                // Re-auth on 401/403
                if (strpos($e->getMessage(), '401') !== false || strpos($e->getMessage(), '403') !== false) {
                    $this->forgetTokens();
                    try {
                        $token = $this->getAccessToken($companyId, $apiKey);
                        $bytes = $this->downloadRecording($token, $companyId, $recordName);
                    } catch (\Exception $e2) {
                        Log::error('A1 Recordings: скачивание не удалось после re-auth: ' . $e2->getMessage());
                        $this->logFetch('error', $start, $end, $recordsFound, $recordsNew, $filesDownloaded, $filesDeleted, $bytesDownloaded, $bytesFreed, $e2->getMessage());
                        return 1;
                    }
                } else {
                    Log::error('A1 Recordings: ошибка скачивания ' . $recordName . ': ' . $e->getMessage());
                    $this->logFetch('error', $start, $end, $recordsFound, $recordsNew, $filesDownloaded, $filesDeleted, $bytesDownloaded, $bytesFreed, $e->getMessage());
                    return 1;
                }
            }

            // Build file path
            $parts    = explode('/', $recordName);
            $filename = end($parts);
            $callDate = $rec['callDate'] ?? date('Y-m-d H:i:s');
            $folder   = 'a1_recordings/' . date('Y-m', strtotime($callDate));
            Storage::disk('local')->makeDirectory($folder);
            $filePath = $folder . '/' . $filename . '.mp3';
            $written = Storage::disk('local')->put($filePath, $bytes);
            if ($written === false) {
                Log::error('A1 Recordings: не удалось записать файл: ' . $filePath);
                $this->logFetch('error', $start, $end, $recordsFound, $recordsNew, $filesDownloaded, $filesDeleted, $bytesDownloaded, $bytesFreed, 'Storage::put failed: ' . $filePath);
                return 1;
            }
            $fileSize = strlen($bytes);

            // Insert DB record
            DB::table('a1_call_recordings')->insert([
                'record_name'   => $recordName,
                'uuid'          => $uuid,
                'call_date'     => date('Y-m-d H:i:s', strtotime($callDate)),
                'caller_part'   => $rec['callerPart'] ?? '',
                'callee_part'   => $rec['calleePart'] ?? '',
                'call_duration' => (int) ($rec['callDuration'] ?? 0),
                'file_path'     => $filePath,
                'file_size'     => $fileSize,
                'downloaded_at' => date('Y-m-d H:i:s'),
            ]);

            $filesDownloaded++;
            $bytesDownloaded += $fileSize;
        }

        $this->logFetch('success', $start, $end, $recordsFound, $recordsNew, $filesDownloaded, $filesDeleted, $bytesDownloaded, $bytesFreed, null);
        $this->info("A1 Recordings: скачано {$filesDownloaded} файлов, удалено {$filesDeleted} старых.");
        return 0;
    }

    // ─────────────────────────────────────────────────────────────
    // Quota management
    // ─────────────────────────────────────────────────────────────

    private function enforceQuota(int $deleted, int $freed): array
    {
        $used = (int) DB::table('a1_call_recordings')->sum('file_size');

        while (($used + self::BUFFER_BYTES) > self::QUOTA_BYTES) {
            $oldest = DB::table('a1_call_recordings')
                ->orderBy('call_date', 'asc')
                ->first();

            if (!$oldest) {
                break;
            }

            $diskPath = storage_path('app/' . $oldest->file_path);
            if (file_exists($diskPath)) {
                @unlink($diskPath);
            }

            DB::table('a1_call_recordings')->where('id', $oldest->id)->delete();
            $used    -= $oldest->file_size;
            $freed   += $oldest->file_size;
            $deleted++;
        }

        return [$deleted, $freed];
    }

    // ─────────────────────────────────────────────────────────────
    // A1 API calls
    // ─────────────────────────────────────────────────────────────

    private function fetchRecordingsList(string $token, string $companyId, int $start, int $end): ?array
    {
        usleep(1_100_000); // rate limit: 1 req/sec (1.1s matches Python client)
        $response = Http::withHeaders([
            'Authentication' => $token,
        ])->get(self::BASE_URL . '/record/list', [
            'company_id' => $companyId,
            'start'      => $start,
            'end'        => $end,
        ]);

        if (in_array($response->status(), [401, 403], true)) {
            return null;
        }

        if ($response->status() === 429) {
            throw new \RuntimeException('Rate limit A1 API (429)');
        }

        if (!$response->successful()) {
            throw new \RuntimeException('record/list HTTP ' . $response->status() . ': ' . $response->body());
        }

        $data = $response->json();
        if (is_array($data) && (isset($data[0]) || $data === [])) {
            return $data;
        }
        foreach (['data', 'items', 'records'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        return is_array($data) ? $data : [];
    }

    private function downloadRecording(string $token, string $companyId, string $recordName): string
    {
        usleep(1_100_000); // rate limit: 1 req/sec (1.1s matches Python client)
        $response = Http::withHeaders([
            'Authentication' => $token,
        ])->timeout(60)->get(self::BASE_URL . '/record', [
            'company_id' => $companyId,
            'filename'   => $recordName,
        ]);

        if ($response->status() === 404) {
            throw new \RuntimeException('404: запись не найдена ' . $recordName);
        }
        if (in_array($response->status(), [401, 403], true)) {
            throw new \RuntimeException($response->status() . ': токен отклонён при скачивании');
        }
        if (!$response->successful()) {
            throw new \RuntimeException('download HTTP ' . $response->status() . ': ' . $response->body());
        }

        return $response->body();
    }

    // ─────────────────────────────────────────────────────────────
    // Fetch log
    // ─────────────────────────────────────────────────────────────

    private function logFetch(
        string $status,
        int $periodStart,
        int $periodEnd,
        int $recordsFound,
        int $recordsNew,
        int $filesDownloaded,
        int $filesDeleted,
        int $bytesDownloaded,
        int $bytesFreed,
        ?string $errorMessage
    ): void {
        DB::table('a1_recordings_fetch_log')->insert([
            'fetched_at'       => date('Y-m-d H:i:s'),
            'status'           => $status,
            'period_start'     => $periodStart,
            'period_end'       => $periodEnd,
            'records_found'    => $recordsFound,
            'records_new'      => $recordsNew,
            'files_downloaded' => $filesDownloaded,
            'files_deleted'    => $filesDeleted,
            'bytes_downloaded' => $bytesDownloaded,
            'bytes_freed'      => $bytesFreed,
            'error_message'    => $errorMessage,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Auth (identical pattern to FetchA1MissedCalls)
    // ─────────────────────────────────────────────────────────────

    private function getAccessToken(string $companyId, string $apiKey): string
    {
        $tokens = $this->loadTokens();

        if (!empty($tokens['access_token']) && !empty($tokens['access_expires_at'])) {
            if ($tokens['access_expires_at'] > time() + 300) {
                return $tokens['access_token'];
            }
        }

        if (!empty($tokens['refresh_token']) && !empty($tokens['refresh_expires_at'])) {
            if ($tokens['refresh_expires_at'] > time() + 60) {
                try {
                    return $this->refreshToken($tokens['refresh_token']);
                } catch (\Exception $e) {
                    Log::warning('A1 Recordings: refresh_token не сработал: ' . $e->getMessage());
                }
            }
        }

        return $this->authorize($companyId, $apiKey);
    }

    private function authorize(string $companyId, string $apiKey): string
    {
        $credential = base64_encode($companyId . ':' . $apiKey);
        $response = Http::withHeaders([
            'Authorization' => $credential,
        ])->get(self::BASE_URL . '/auth/tokens');

        if (!$response->successful()) {
            throw new \RuntimeException('Авторизация A1 провалилась: HTTP ' . $response->status());
        }

        $data = $response->json();
        $this->saveTokens($data);
        return $data['access_token'];
    }

    private function refreshToken(string $refreshToken): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $refreshToken,
        ])->put(self::BASE_URL . '/auth/tokens');

        if (in_array($response->status(), [401, 403], true)) {
            throw new \RuntimeException('refresh_token отклонён (HTTP ' . $response->status() . ')');
        }
        if (!$response->successful()) {
            throw new \RuntimeException('Refresh failed: HTTP ' . $response->status());
        }

        $data = $response->json();
        $this->saveTokens($data);
        return $data['access_token'];
    }

    private function tokensPath(): string
    {
        return storage_path('app/' . self::TOKENS_FILE);
    }

    private function loadTokens(): array
    {
        $path = $this->tokensPath();
        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function forgetTokens(): void
    {
        @unlink($this->tokensPath());
    }

    private function saveTokens(array $data): void
    {
        $tokens = [
            'access_token'       => $data['access_token']  ?? '',
            'access_expires_at'  => time() + 86400,
            'refresh_token'      => $data['refresh_token'] ?? '',
            'refresh_expires_at' => time() + 604800,
        ];
        file_put_contents(
            $this->tokensPath(),
            json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}
