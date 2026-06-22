<?php

namespace App\Console\Commands;

use App\Console\Concerns\InteractsWithA1Api;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchA1Cdr extends Command
{
    use InteractsWithA1Api;

    protected $signature   = 'a1:fetch-cdr {--period=90 : Период выборки в минутах}';
    protected $description = 'Скачивает CDR (историю звонков) с A1 ВАТС';

    private const MISSED_STATUSES = [
        'NOT_ANSWERED_COMMON',
        'CANCELLED_BY_CALLER',
        'DENIED_DUE_TO_NOT_WORK_TIME',
        'DENIED_DUE_TO_MAX_SESSION',
    ];

    public function handle(): int
    {
        $lock = $this->a1Lock();
        if (!$lock->get()) {
            Log::warning('A1 CDR: не удалось захватить a1_api_mutex, пропускаем');
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
            $response = $this->a1AuthGet('/cdr', [
                'company_id' => $companyId,
                'start'      => $start,
                'end'        => $end,
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('cdr HTTP ' . $response->status() . ': ' . $response->body());
            }

            $records = $this->a1UnwrapList($response->json(), ['data', 'items', 'records', 'cdr']);
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
        $type = strtolower($rec['callType'] ?? '');
        if (str_contains($type, 'outgoing') || str_contains($type, 'out')) {
            return 'outgoing';
        }
        $status = $rec['callStatus'] ?? '';
        if (in_array($status, self::MISSED_STATUSES, true)) {
            return 'missed';
        }
        return 'incoming';
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
}
