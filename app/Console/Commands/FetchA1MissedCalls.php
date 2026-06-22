<?php

namespace App\Console\Commands;

use App\Console\Concerns\InteractsWithA1Api;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FetchA1MissedCalls extends Command
{
    use InteractsWithA1Api;

    protected $signature   = 'a1:fetch-missed-calls {--period=15 : Период выборки в минутах (с перекрытием)}';
    protected $description = 'Получает пропущенные звонки из A1 ВАТС API и сохраняет в MySQL';

    private const MISSED_STATUSES = [
        'NOT_ANSWERED_COMMON',
        'CANCELLED_BY_CALLER',
        'DENIED_DUE_TO_NOT_WORK_TIME',
        'DENIED_DUE_TO_MAX_SESSION',
        'DENIED_DUE_TO_MAX_CHANNEL_LIMIT',
    ];

    private const ANSWERED_STATUSES = [
        'ANSWERED_COMMON',
        'ANSWERED_BY_ORIGINAL_CLIENT',
    ];

    public function handle(): int
    {
        $lock = $this->a1Lock();
        if (!$lock->get()) {
            Log::warning('A1 MissedCalls: не удалось захватить a1_api_mutex, пропускаем запуск');
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

        $periodMinutes = (int) $this->option('period');
        $end   = time();
        $start = $end - ($periodMinutes * 60);

        // Авторизация + CDR через общий трейт (единый лок/токен/троттлинг/re-auth)
        try {
            $response = $this->a1AuthGet('/cdr', [
                'company_id' => $companyId,
                'start'      => $start,
                'end'        => $end,
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('CDR запрос провалился: HTTP ' . $response->status() . ' — ' . $response->body());
            }

            $records = $this->a1UnwrapList($response->json(), ['data', 'items', 'records', 'calls']);
        } catch (\Exception $e) {
            Log::error('A1 MissedCalls: ошибка CDR — ' . $e->getMessage());
            $this->logFetch('error', 0, 0, $e->getMessage(), $start, $end);
            $this->error('A1: ошибка CDR: ' . $e->getMessage());
            return 1;
        }

        // 3. Отфильтровать пропущенные (только входящие — исходящие без ответа не считаются пропущенными)
        $missed = array_filter($records, function ($r) {
            $type = strtolower($r['callType'] ?? '');
            if (str_contains($type, 'outgoing') || str_contains($type, 'out')) {
                return false;
            }
            return in_array($r['callStatus'] ?? '', self::MISSED_STATUSES);
        });

        $this->line('A1: получено звонков: ' . count($records) . ', пропущенных: ' . count($missed));

        // 4. Сохранить новые в MySQL
        $added = 0;
        foreach ($missed as $call) {
            $uuid = $call['uuid'] ?? null;
            if (!$uuid) {
                continue;
            }

            $exists = DB::table('a1_missed_calls')->where('uuid', $uuid)->exists();
            if ($exists) {
                continue;
            }

            $enriched = $this->enrichWithCrm($call);
            $this->insertCall($uuid, $enriched);
            $added++;
        }

        // 5. Автоопределение: клиент сам перезвонил
        $this->detectCallbacks($records);

        $this->logFetch('success', count($records), $added, null, $start, $end);
        $this->info("A1: добавлено новых пропущенных: {$added}.");
        return 0;
    }

    // ─────────────────────────────────────────────────────────────
    // MySQL
    // ─────────────────────────────────────────────────────────────

    private function insertCall(string $uuid, array $call): void
    {
        DB::table('a1_missed_calls')->insert([
            'uuid'             => $uuid,
            'call_timestamp'   => $call['callTimestamp']   ?? 0,
            'caller_number'    => $call['callerNumber']    ?? '',
            'callee_number'    => $call['calleeNumber']    ?? '',
            'call_status'      => $call['callStatus']      ?? '',
            'call_duration'    => $call['callDuration']    ?? 0,
            'crm_client_id'    => $call['crm_client']['id']   ?? null,
            'crm_client_fio'   => $call['crm_client']['fio']  ?? null,
            'crm_active_deals' => json_encode($call['crm_active_deals'] ?? [], JSON_UNESCAPED_UNICODE),
            'crm_last_return'  => $call['crm_last_return'] ?? null,
            'crm_total_deals'  => $call['crm_total_deals'] ?? 0,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    private function logFetch(string $status, int $total, int $added, ?string $error, int $pStart, int $pEnd): void
    {
        DB::table('a1_api_fetch_log')->insert([
            'fetched_at'    => date('Y-m-d H:i:s'),
            'status'        => $status,
            'total_records' => $total,
            'new_records'   => $added,
            'error_message' => $error,
            'period_start'  => $pStart,
            'period_end'    => $pEnd,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Автоопределение обратных звонков
    // ─────────────────────────────────────────────────────────────

    private function detectCallbacks(array $allRecords): void
    {
        // Build map: normalized_phone → sorted timestamps of answered calls in this CDR batch
        $answeredByPhone = [];
        foreach ($allRecords as $r) {
            if (!in_array($r['callStatus'] ?? '', self::ANSWERED_STATUSES, true)) {
                continue;
            }
            $phone = $this->normalizeLast9($r['callerNumber'] ?? '');
            if ($phone) {
                $answeredByPhone[$phone][] = (int)($r['callTimestamp'] ?? 0);
            }
        }

        if (empty($answeredByPhone)) {
            return;
        }

        foreach ($answeredByPhone as &$ts) {
            sort($ts);
        }
        unset($ts);

        // Check all unresolved missed calls from last 30 days
        $unresolved = DB::table('a1_missed_calls')
            ->where('action_type', 'none')
            ->where('call_timestamp', '>=', time() - 30 * 86400)
            ->get(['id', 'call_timestamp', 'caller_number']);

        foreach ($unresolved as $missed) {
            $phone = $this->normalizeLast9($missed->caller_number);
            if (!$phone || !isset($answeredByPhone[$phone])) {
                continue;
            }

            // Find the earliest answered call that came AFTER this missed call
            foreach ($answeredByPhone[$phone] as $answeredTs) {
                if ($answeredTs > $missed->call_timestamp) {
                    $minutes = (int)(($answeredTs - $missed->call_timestamp) / 60);
                    DB::table('a1_missed_calls')
                        ->where('id', $missed->id)
                        ->where('action_type', 'none')
                        ->update([
                            'action_type'      => 'client_called_back',
                            'callback_minutes' => $minutes,
                        ]);
                    break;
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CRM обогащение
    // ─────────────────────────────────────────────────────────────

    private function enrichWithCrm(array $call): array
    {
        $callerNumber = $call['callerNumber'] ?? '';
        $last9 = $this->normalizeLast9($callerNumber);

        $call['crm_client']       = null;
        $call['crm_active_deals'] = [];
        $call['crm_last_return']  = null;
        $call['crm_total_deals']  = 0;

        if (!$last9) {
            return $call;
        }

        $pattern = '%' . implode('%', str_split($last9)) . '%';
        $clients = DB::select("
            SELECT client_id, family, name, otch, phone_1, phone_2 
            FROM clients 
            WHERE phone_1 LIKE ? OR phone_2 LIKE ?
            ORDER BY client_id DESC
            LIMIT 50
        ", [$pattern, $pattern]);

        $foundClient = null;
        foreach ($clients as $client) {
            if ($this->normalizeLast9($client->phone_1) === $last9
                || $this->normalizeLast9($client->phone_2) === $last9) {
                $foundClient = $client;
                break;
            }
        }

        if (!$foundClient) {
            return $call;
        }

        $clientId = $foundClient->client_id;
        $call['crm_client'] = [
            'id'   => $clientId,
            'fio'  => trim($foundClient->family . ' ' . $foundClient->name . ' ' . $foundClient->otch),
            'phone'=> $callerNumber,
        ];

        $activeDeals = DB::select("
            SELECT
                tr.model                  AS goods_model,
                trc.rent_cat_name         AS category,
                FROM_UNIXTIME(rda.start_date, '%d.%m.%Y')           AS rented_from,
                FROM_UNIXTIME(rda.planned_return_date, '%d.%m.%Y')  AS return_due
            FROM rent_deals_act rda
            JOIN tovar_rent_items tri ON tri.item_inv_n = rda.item_inv_n
            JOIN tovar_rent tr  ON tr.tovar_rent_id  = tri.model_id
            JOIN tovar_rent_cat trc ON trc.tovar_rent_cat_id = tr.tovar_rent_cat_id
            WHERE rda.client_id = ?
        ", [$clientId]);

        $call['crm_active_deals'] = array_map(function ($d) {
            return [
                'model'      => $d->goods_model,
                'category'   => $d->category,
                'rented_from'=> $d->rented_from,
                'return_due' => $d->return_due,
            ];
        }, $activeDeals);

        $totalAct  = DB::selectOne("SELECT COUNT(*) as n FROM rent_deals_act  WHERE client_id = ?", [$clientId]);
        $totalArch = DB::selectOne("SELECT COUNT(*) as n FROM rent_deals_arch WHERE client_id = ?", [$clientId]);
        $call['crm_total_deals'] = ($totalAct->n ?? 0) + ($totalArch->n ?? 0);

        if (empty($activeDeals)) {
            $lastReturn = DB::selectOne("
                SELECT FROM_UNIXTIME(MAX(arch_time), '%d.%m.%Y') AS last_return
                FROM rent_deals_arch
                WHERE client_id = ?
            ", [$clientId]);
            $call['crm_last_return'] = $lastReturn->last_return ?? null;
        }

        return $call;
    }

    private function normalizeLast9(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        return strlen($digits) >= 7 ? substr($digits, -7) : '';
    }
}
