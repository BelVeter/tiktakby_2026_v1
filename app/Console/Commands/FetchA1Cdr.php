<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchA1Cdr extends Command
{
    protected $signature   = 'a1:fetch-cdr {--period=90 : Период выборки в минутах}';
    protected $description = 'Скачивает CDR (историю звонков) с A1 ВАТС';

    private const TOKENS_FILE = 'a1_tokens.json';
    private const BASE_URL    = 'https://vats.a1.by/crm-api/open-api/v1';

    private const MISSED_STATUSES = [
        'NOT_ANSWERED_COMMON',
        'CANCELLED_BY_CALLER',
        'DENIED_DUE_TO_NOT_WORK_TIME',
        'DENIED_DUE_TO_MAX_SESSION',
    ];

    public function handle(): int
    {
        $lock = Cache::lock('a1_cdr_mutex', 120);
        if (!$lock->get()) {
            Log::warning('A1 CDR: не удалось захватить a1_cdr_mutex, пропускаем');
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
            $this->error('A1 CDR: не задан A1_COMPANY_ID или A1_API_KEY');
            return 1;
        }

        $end     = time();
        $isEmpty = !DB::table('a1_cdr')->exists();
        if ($isEmpty) {
            $start = $end - 30 * 86400;
        } else {
            $periodMinutes = (int) $this->option('period');
            $start = $end - ($periodMinutes * 60);
        }

        try {
            $token = $this->getAccessToken($companyId, $apiKey);
        } catch (\Exception $e) {
            Log::error('A1 CDR: ошибка авторизации — ' . $e->getMessage());
            $this->logFetch('error', $start, $end, 0, 0, $e->getMessage());
            return 1;
        }

        try {
            $records = $this->fetchCdr($token, $companyId, $start, $end);

            if ($records === null) {
                $this->forgetTokens();
                $token   = $this->getAccessToken($companyId, $apiKey);
                $records = $this->fetchCdr($token, $companyId, $start, $end);
            }

            if ($records === null) {
                throw new \RuntimeException('CDR: токен отклонён даже после re-auth');
            }
        } catch (\Exception $e) {
            Log::error('A1 CDR: ошибка получения — ' . $e->getMessage());
            $this->logFetch('error', $start, $end, 0, 0, $e->getMessage());
            return 1;
        }

        $recordsFound = count($records);
        $recordsNew   = 0;

        foreach ($records as $rec) {
            $uuid      = $rec['uuid'] ?? null;
            $timestamp = $rec['callTimestamp'] ?? null;

            if (!$uuid || !$timestamp) {
                continue;
            }

            if (DB::table('a1_cdr')->where('uuid', $uuid)->exists()) {
                continue;
            }

            $callType = $this->resolveCallType($rec);
            $duration = (int) ($rec['callDuration'] ?? 0);

            $recordingExists = DB::table('a1_call_recordings')
                ->where('uuid', $uuid)
                ->exists();
            $recordingUuid = $recordingExists ? $uuid : null;

            DB::table('a1_cdr')->insert([
                'uuid'           => $uuid,
                'call_date'      => date('Y-m-d H:i:s', (int) $timestamp),
                'call_type'      => $callType,
                'caller_number'  => (string) ($rec['callerNumber'] ?? ''),
                'callee_number'  => (string) ($rec['calleeNumber'] ?? ''),
                'call_duration'  => $duration,
                'recording_uuid' => $recordingUuid,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            if ($recordingUuid) {
                DB::table('a1_call_analysis')->insertOrIgnore([
                    'recording_uuid' => $recordingUuid,
                    'ai_status'      => 'pending',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);
            }

            $recordsNew++;
        }

        $this->logFetch('success', $start, $end, $recordsFound, $recordsNew, null);
        $this->info("A1 CDR: найдено {$recordsFound}, новых {$recordsNew}.");
        return 0;
    }

    private function resolveCallType(array $rec): string
    {
        $status = $rec['callStatus'] ?? '';
        if (in_array($status, self::MISSED_STATUSES, true)) {
            return 'missed';
        }
        $type = strtolower($rec['callType'] ?? '');
        if (str_contains($type, 'outgoing') || str_contains($type, 'out')) {
            return 'outgoing';
        }
        return 'incoming';
    }

    private function fetchCdr(string $token, string $companyId, int $start, int $end): ?array
    {
        usleep(1_100_000);
        $response = Http::withHeaders([
            'Authentication' => $token,
        ])->get(self::BASE_URL . '/cdr', [
            'company_id' => $companyId,
            'start'      => $start,
            'end'        => $end,
        ]);

        if (in_array($response->status(), [401, 403], true)) {
            return null;
        }
        if (!$response->successful()) {
            throw new \RuntimeException('cdr HTTP ' . $response->status() . ': ' . $response->body());
        }

        $data = $response->json();
        if (is_array($data) && (isset($data[0]) || $data === [])) {
            return $data;
        }
        foreach (['data', 'items', 'records', 'cdr'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        return is_array($data) ? $data : [];
    }

    private function logFetch(string $status, int $start, int $end, int $found, int $new, ?string $error): void
    {
        DB::table('a1_cdr_fetch_log')->insert([
            'fetched_at'    => date('Y-m-d H:i:s'),
            'status'        => $status,
            'period_start'  => $start,
            'period_end'    => $end,
            'records_found' => $found,
            'records_new'   => $new,
            'error_message' => $error,
        ]);
    }

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
                    Log::warning('A1 CDR: refresh не сработал: ' . $e->getMessage());
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
        file_put_contents(
            $this->tokensPath(),
            json_encode([
                'access_token'       => $data['access_token']  ?? '',
                'access_expires_at'  => time() + 86400,
                'refresh_token'      => $data['refresh_token'] ?? '',
                'refresh_expires_at' => time() + 604800,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}
