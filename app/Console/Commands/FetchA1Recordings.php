<?php

namespace App\Console\Commands;

use App\Console\Concerns\InteractsWithA1Api;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FetchA1Recordings extends Command
{
    use InteractsWithA1Api;

    protected $signature   = 'a1:fetch-recordings {--period=90 : Период выборки в минутах}';
    protected $description = 'Скачивает записи звонков с A1 ВАТС и сохраняет в storage/app/a1_recordings/';

    private const QUOTA_BYTES  = 1_073_741_824; // 1 GB
    private const BUFFER_BYTES = 5_242_880;     // 5 MB buffer before download

    public function handle(): int
    {
        $lock = $this->a1Lock();
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

        // Получить список записей (авторизация/токен/re-auth — внутри трейта).
        // Не получить даже список — фатально для всего прогона.
        try {
            $response = $this->a1AuthGet('/record/list', [
                'company_id' => $companyId,
                'start'      => $start,
                'end'        => $end,
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('record/list HTTP ' . $response->status() . ': ' . $response->body());
            }

            $records = $this->a1UnwrapList($response->json());
        } catch (\Exception $e) {
            Log::error('A1 Recordings: ошибка получения списка — ' . $e->getMessage());
            $this->logFetch('error', $start, $end, 0, 0, 0, 0, 0, 0, $e->getMessage());
            return 1;
        }

        $recordsFound = count($records);
        $this->line("A1 Recordings: найдено записей: {$recordsFound}");

        $recordsNew      = 0;
        $filesDownloaded = 0;
        $filesFailed     = 0;
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

            // Скачивание одной записи. Авторизация/токен/re-auth/троттлинг — в трейте.
            // ВАЖНО: сбой одной записи НЕ должен прерывать весь батч.
            $resp = $this->a1AuthGet('/record', [
                'company_id' => $companyId,
                'filename'   => $recordName,
            ], 60);

            if ($resp->status() === 404) {
                $this->line("  skip (404): {$recordName}");
                continue;
            }
            if (!$resp->successful()) {
                Log::warning('A1 Recordings: ошибка скачивания ' . $recordName . ': HTTP ' . $resp->status());
                $filesFailed++;
                continue;
            }

            $bytes = $resp->body();

            // Build file path
            $parts    = explode('/', $recordName);
            $filename = end($parts);
            $callDate = $rec['callDate'] ?? date('Y-m-d H:i:s');
            $folder   = 'a1_recordings/' . date('Y-m', strtotime($callDate));
            Storage::disk('local')->makeDirectory($folder);
            $filePath = $folder . '/' . $filename . '.mp3';
            $written = Storage::disk('local')->put($filePath, $bytes);
            if ($written === false) {
                Log::warning('A1 Recordings: не удалось записать файл: ' . $filePath);
                $filesFailed++;
                continue;
            }
            $fileSize = strlen($bytes);

            // Insert DB record
            // callerPart/calleePart are objects from A1 API — extract fullNumber
            DB::table('a1_call_recordings')->insert([
                'record_name'   => $recordName,
                'uuid'          => $uuid,
                'call_date'     => date('Y-m-d H:i:s', strtotime($callDate)),
                'caller_part'   => $this->extractNumber($rec['callerPart'] ?? ''),
                'callee_part'   => $this->extractNumber($rec['calleePart'] ?? ''),
                'call_duration' => (int) ($rec['callDuration'] ?? 0),
                'file_path'     => $filePath,
                'file_size'     => $fileSize,
                'has_audio'     => 1,
                'downloaded_at' => date('Y-m-d H:i:s'),
            ]);

            // Create pending analysis record for AI agent
            DB::table('a1_call_analysis')->insertOrIgnore([
                'recording_uuid' => $uuid,
                'ai_status'      => 'pending',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

            // Link recording to CDR if already fetched
            DB::table('a1_cdr')
                ->where('uuid', $uuid)
                ->whereNull('recording_uuid')
                ->update(['recording_uuid' => $uuid]);

            $filesDownloaded++;
            $bytesDownloaded += $fileSize;
        }

        $this->logFetch('success', $start, $end, $recordsFound, $recordsNew, $filesDownloaded, $filesDeleted, $bytesDownloaded, $bytesFreed, null);
        $this->info("A1 Recordings: скачано {$filesDownloaded} файлов, ошибок {$filesFailed}, удалено {$filesDeleted} старых.");
        if ($filesFailed > 0) {
            Log::warning("A1 Recordings: не скачано {$filesFailed} записей (см. предупреждения выше)");
        }
        return 0;
    }

    // ─────────────────────────────────────────────────────────────
    // Quota management
    // ─────────────────────────────────────────────────────────────

    private function enforceQuota(int $deleted, int $freed): array
    {
        // Sum only records that still have audio on disk
        $used = (int) DB::table('a1_call_recordings')
            ->where('has_audio', 1)
            ->sum('file_size');

        while (($used + self::BUFFER_BYTES) > self::QUOTA_BYTES) {
            // Pick the oldest recording that still has audio on disk
            $oldest = DB::table('a1_call_recordings')
                ->where('has_audio', 1)
                ->orderBy('call_date', 'asc')
                ->first();

            if (!$oldest) {
                break;
            }

            $diskPath = storage_path('app/' . $oldest->file_path);
            if (file_exists($diskPath)) {
                @unlink($diskPath);
            }

            // Mark as audio-deleted — keep the row so transcripts/analysis survive.
            // Do NOT delete the DB record.
            DB::table('a1_call_recordings')
                ->where('id', $oldest->id)
                ->update(['has_audio' => 0]);

            $used  -= $oldest->file_size;
            $freed += $oldest->file_size;
            $deleted++;
        }

        return [$deleted, $freed];
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

    // A1 API returns callerPart/calleePart as objects — extract the phone number
    private function extractNumber($part): string
    {
        if (is_array($part)) {
            return (string) ($part['fullNumber'] ?? '');
        }
        return (string) $part;
    }
}
