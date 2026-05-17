<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FetchA1MissedCalls extends Command
{
    protected $signature   = 'a1:fetch-missed-calls {--period=15 : Период выборки в минутах (с перекрытием)}';
    protected $description = 'Получает пропущенные звонки из A1 ВАТС API и сохраняет в JSON';

    // Файлы хранилища
    private const CALLS_FILE  = 'a1_missed_calls.json';
    private const TOKENS_FILE = 'a1_tokens.json';

    // Максимум хранимых записей (старые вытесняются)
    private const MAX_CALLS = 500;

    // Статусы, считающиеся пропущенными
    private const MISSED_STATUSES = [
        'NOT_ANSWERED_COMMON',
        'CANCELLED_BY_CALLER',
        'DENIED_DUE_TO_NOT_WORK_TIME',
        'DENIED_DUE_TO_MAX_SESSION',
        'DENIED_DUE_TO_MAX_CHANNEL_LIMIT',
    ];

    private const BASE_URL   = 'https://vats.a1.by/crm-api/open-api/v1';

    public function handle(): int
    {
        $companyId = config('services.a1.company_id');
        $apiKey    = config('services.a1.api_key');

        if (!$companyId || !$apiKey) {
            $this->error('A1: не задан A1_COMPANY_ID или A1_API_KEY в .env');
            return 1;
        }

        // 1. Получить/обновить access token
        try {
            $accessToken = $this->getAccessToken($companyId, $apiKey);
        } catch (\Exception $e) {
            Log::error('A1 MissedCalls: ошибка авторизации — ' . $e->getMessage());
            $this->error('A1: ошибка авторизации: ' . $e->getMessage());
            return 1;
        }

        // 2. Диапазон запроса: последние N минут + перекрытие на случай задержки
        $periodMinutes = (int) $this->option('period');
        $end   = time();
        $start = $end - ($periodMinutes * 60);

        // 3. Получить CDR. При 401/403 (токен инвалидирован — например, параллельным логином)
        // сбрасываем сохранённые токены, форсируем полную re-auth и пробуем ещё раз.
        try {
            $records = $this->fetchCdr($accessToken, $companyId, $start, $end);

            if ($records === null) {
                Log::warning('A1 MissedCalls: токен отклонён (401/403), форсируем re-auth');
                $this->forgetTokens();
                $accessToken = $this->getAccessToken($companyId, $apiKey);
                $records = $this->fetchCdr($accessToken, $companyId, $start, $end);
            }

            if ($records === null) {
                throw new \RuntimeException('CDR: токен отклонён даже после re-auth (401/403)');
            }
        } catch (\Exception $e) {
            Log::error('A1 MissedCalls: ошибка CDR — ' . $e->getMessage());
            $this->error('A1: ошибка CDR: ' . $e->getMessage());
            return 1;
        }

        // 4. Отфильтровать пропущенные
        $missed = array_filter($records, function ($r) {
            return in_array($r['callStatus'] ?? '', self::MISSED_STATUSES);
        });

        $this->line('A1: получено звонков: ' . count($records) . ', пропущенных: ' . count($missed));

        // 5. Загрузить существующие данные
        $existing = $this->loadCalls();

        // 6. Добавить новые (дедупликация по uuid)
        $added = 0;
        foreach ($missed as $call) {
            $uuid = $call['uuid'] ?? null;
            if (!$uuid || isset($existing[$uuid])) {
                continue;
            }

            // Обогащение CRM-данными
            $enriched = $this->enrichWithCrm($call);
            $existing[$uuid] = $enriched;
            $added++;
        }

        // 7. Обрезать до MAX_CALLS, сортируя по времени (новые последними в массиве)
        if (count($existing) > self::MAX_CALLS) {
            uasort($existing, function ($a, $b) {
                return ($b['callTimestamp'] ?? 0) - ($a['callTimestamp'] ?? 0);
            });
            $existing = array_slice($existing, 0, self::MAX_CALLS, true);
        }

        // 8. Сохранить
        $this->saveCalls($existing);

        $this->info("A1: добавлено новых пропущенных: {$added}. Всего в хранилище: " . count($existing));
        return 0;
    }

    // ─────────────────────────────────────────────────────────────
    // Авторизация / токены
    // ─────────────────────────────────────────────────────────────

    private function getAccessToken(string $companyId, string $apiKey): string
    {
        $tokens = $this->loadTokens();

        // Если access_token ещё живой (запас 5 мин) — использовать
        if (!empty($tokens['access_token']) && !empty($tokens['access_expires_at'])) {
            if ($tokens['access_expires_at'] > time() + 300) {
                return $tokens['access_token'];
            }
        }

        // Попробовать обновить через refresh_token
        if (!empty($tokens['refresh_token']) && !empty($tokens['refresh_expires_at'])) {
            if ($tokens['refresh_expires_at'] > time() + 60) {
                try {
                    return $this->refreshToken($tokens['refresh_token']);
                } catch (\Exception $e) {
                    // refresh не сработал — идём на полную авторизацию
                    Log::warning('A1: refresh_token не сработал, повторная авторизация: ' . $e->getMessage());
                }
            }
        }

        // Полная авторизация
        return $this->authorize($companyId, $apiKey);
    }

    private function authorize(string $companyId, string $apiKey): string
    {
        // Authorization: <base64(company_id:api_key)> — БЕЗ "Basic"
        $credential = base64_encode($companyId . ':' . $apiKey);

        $response = Http::withHeaders([
            'Authorization' => $credential,
        ])->get(self::BASE_URL . '/auth/tokens');

        if (!$response->successful()) {
            throw new \RuntimeException('Авторизация A1 провалилась: HTTP ' . $response->status() . ' — ' . $response->body());
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

    // ─────────────────────────────────────────────────────────────
    // CDR
    // ─────────────────────────────────────────────────────────────

    private function fetchCdr(string $accessToken, string $companyId, int $start, int $end): ?array
    {
        // ⚠️ Заголовок называется Authentication, а НЕ Authorization. Без Bearer.
        $response = Http::withHeaders([
            'Authentication' => $accessToken,
        ])->get(self::BASE_URL . '/cdr', [
            'company_id' => $companyId,
            'start'      => $start,
            'end'        => $end,
        ]);

        // Токен инвалидирован (например, параллельным логином в другом приложении).
        // Возвращаем null — вызывающий код сделает re-auth и повторит.
        if (in_array($response->status(), [401, 403], true)) {
            return null;
        }

        if ($response->status() === 429) {
            throw new \RuntimeException('Rate limit A1 API (429)');
        }

        if (!$response->successful()) {
            throw new \RuntimeException('CDR запрос провалился: HTTP ' . $response->status() . ' — ' . $response->body());
        }

        $data = $response->json();

        // API может вернуть как массив напрямую, так и обёртку
        if (isset($data[0]) || $data === []) {
            return $data;
        }

        // Попробовать достать из поля data/items/records
        foreach (['data', 'items', 'records', 'calls'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────
    // Обогащение CRM-данными
    // ─────────────────────────────────────────────────────────────

    private function enrichWithCrm(array $call): array
    {
        $callerNumber = $call['callerNumber'] ?? '';
        $last9 = $this->normalizeLast9($callerNumber);

        $call['crm_client']      = null;
        $call['crm_active_deals'] = [];
        $call['crm_last_return']  = null;
        $call['crm_total_deals']  = 0;

        if (!$last9) {
            return $call;
        }

        // Поиск клиента по phone_1 и phone_2 (нормализуем в PHP, т.к. MySQL 5.7 без REGEXP_REPLACE)
        $clients = DB::select("SELECT client_id, family, name, otch, phone_1, phone_2 FROM clients LIMIT 5000");

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

        // Активные аренды
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

        // Счётчик всех сделок (акт + арх)
        $totalAct  = DB::selectOne("SELECT COUNT(*) as n FROM rent_deals_act  WHERE client_id = ?", [$clientId]);
        $totalArch = DB::selectOne("SELECT COUNT(*) as n FROM rent_deals_arch WHERE client_id = ?", [$clientId]);
        $call['crm_total_deals'] = ($totalAct->n ?? 0) + ($totalArch->n ?? 0);

        // Если нет активных — дата последнего возврата
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
        // Используем последние 7 цифр для надёжного совпадения:
        // 375295646699 → 5646699
        // 0295646699   → 5646699
        // 295646699    → 5646699
        // 5646699      → 5646699
        return strlen($digits) >= 7 ? substr($digits, -7) : '';
    }

    // ─────────────────────────────────────────────────────────────
    // Файловое хранилище
    // ─────────────────────────────────────────────────────────────

    private function callsPath(): string
    {
        return storage_path('app/' . self::CALLS_FILE);
    }

    private function tokensPath(): string
    {
        return storage_path('app/' . self::TOKENS_FILE);
    }

    private function loadCalls(): array
    {
        $path = $this->callsPath();
        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function saveCalls(array $calls): void
    {
        file_put_contents($this->callsPath(), json_encode($calls, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        @chmod($this->callsPath(), 0666);
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
        $now = time();
        $tokens = [
            'access_token'      => $data['access_token']  ?? '',
            'access_expires_at' => $now + 86400,   // 1 день
            'refresh_token'     => $data['refresh_token'] ?? '',
            'refresh_expires_at'=> $now + 604800,  // 7 дней
        ];
        file_put_contents($this->tokensPath(), json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
