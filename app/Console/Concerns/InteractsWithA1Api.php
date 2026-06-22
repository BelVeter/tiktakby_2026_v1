<?php

namespace App\Console\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Единая точка координации доступа к A1 ВАТС Open API для всех a1:* команд.
 *
 * Зачем трейт, а не три копии логики в каждой команде:
 *  - A1 держит ОДИН активный access-токен на компанию (JWT: company_id+sub+exp,
 *    без nonce). Любой повторный /auth/tokens ротирует токен и инвалидирует
 *    предыдущий. Поэтому все команды обязаны ходить в API под ОДНИМ общим
 *    локом (a1_api_mutex) — иначе re-auth в одной команде гасит свежий токен
 *    другой («токен отклонён даже после re-auth»).
 *  - A1 ограничивает ~1 запрос/сек на компанию (429 «Retry after: N ms»).
 *    Троттлинг должен быть ГЛОБАЛЬНЫМ (через кэш), а не локальным внутри одной
 *    команды, иначе соседние команды/вызовы превышают лимит.
 */
trait InteractsWithA1Api
{
    // Примечание: константы в трейтах доступны только с PHP 8.2, а прод/контейнер
    // работают на PHP 7.4 — поэтому эти значения отдаём методами, а не const.

    /** Ключ кэша с меткой времени последнего запроса (глобальный троттлинг). */
    private function a1ThrottleKey(): string
    {
        return 'a1:last_request_at';
    }

    /** Сколько раз повторять запрос при 429, прежде чем вернуть последний ответ. */
    private function a1MaxAttempts(): int
    {
        return 4;
    }

    protected function a1BaseUrl(): string
    {
        return 'https://vats.a1.by/crm-api/open-api/v1';
    }

    protected function a1CompanyId(): ?string
    {
        return config('services.a1.company_id');
    }

    protected function a1ApiKey(): ?string
    {
        return config('services.a1.api_key');
    }

    /**
     * Общий лок на ВЕСЬ доступ к A1 API. Все a1:* команды используют один ключ,
     * чтобы исключить конкурентную ротацию токена и наложение запросов.
     * TTL 600s покрывает длинные «догоняющие» прогоны со скачиванием записей.
     */
    protected function a1Lock()
    {
        return Cache::lock('a1_api_mutex', 600);
    }

    // ─────────────────────────────────────────────────────────────
    // Глобальный троттлинг (≥ min-interval между ЛЮБЫМИ запросами к A1)
    // ─────────────────────────────────────────────────────────────

    /** Минимальный интервал между запросами в микросекундах (по умолч. 1.2s). */
    protected function a1MinIntervalMicros(): int
    {
        return (int) (config('services.a1.throttle_ms', 1200) * 1000);
    }

    private function a1Throttle(): void
    {
        $min = $this->a1MinIntervalMicros();
        if ($min <= 0) {
            return;
        }

        $key  = $this->a1ThrottleKey();
        $last = (float) Cache::get($key, 0);
        if ($last > 0) {
            $elapsed = (microtime(true) - $last) * 1_000_000; // в микросекундах
            if ($elapsed < $min) {
                usleep((int) ($min - $elapsed));
            }
        }

        Cache::put($key, microtime(true), now()->addMinutes(5));
    }

    // ─────────────────────────────────────────────────────────────
    // Низкоуровневый запрос: троттлинг + ретрай на 429 с учётом Retry-After
    // ─────────────────────────────────────────────────────────────

    /**
     * @param  array  $options  ['headers'=>[], 'query'=>[], 'timeout'=>int]
     * @return \Illuminate\Http\Client\Response
     */
    protected function a1Request(string $method, string $url, array $options = [])
    {
        $verb        = strtolower($method) === 'put' ? 'put' : 'get';
        $maxAttempts = $this->a1MaxAttempts();
        $response    = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->a1Throttle();

            $request = Http::withHeaders($options['headers'] ?? []);
            if (!empty($options['timeout'])) {
                $request = $request->timeout($options['timeout']);
            }

            $response = $request->{$verb}($url, $options['query'] ?? []);

            if ($response->status() !== 429) {
                return $response;
            }

            $waitMicros = $this->a1RetryAfterMicros($response);
            Log::warning(sprintf(
                'A1 API 429 (попытка %d/%d), ждём %dms перед повтором: %s',
                $attempt,
                $maxAttempts,
                (int) round($waitMicros / 1000),
                $url
            ));

            if ($attempt < $maxAttempts) {
                usleep($waitMicros);
            }
        }

        return $response; // исчерпали попытки — отдаём последний 429
    }

    private function a1RetryAfterMicros($response): int
    {
        $header = $response->header('Retry-After');
        if ($header !== null && $header !== '' && is_numeric($header)) {
            return max(1, (int) $header) * 1_000_000; // заголовок в секундах
        }

        // Тело A1: {"details":["Retry after: 1000 ms"], "message":"..."}
        if (preg_match('/Retry after:\s*(\d+)\s*ms/i', $response->body(), $m)) {
            return max(1, (int) $m[1]) * 1000;
        }

        return 1_200_000; // дефолт 1.2s
    }

    // ─────────────────────────────────────────────────────────────
    // Аутентифицированный GET с однократным re-auth на 401/403
    // ─────────────────────────────────────────────────────────────

    /**
     * Выполняет GET с access-токеном. При 401/403 один раз сбрасывает токен,
     * переавторизуется и повторяет. Безопасно, т.к. вся команда держит общий
     * лок — параллельной ротации токена быть не может.
     *
     * @return \Illuminate\Http\Client\Response
     */
    protected function a1AuthGet(string $path, array $query = [], int $timeout = 0)
    {
        $opts = [
            'headers' => ['Authentication' => $this->a1AccessToken()],
            'query'   => $query,
        ];
        if ($timeout > 0) {
            $opts['timeout'] = $timeout;
        }

        $response = $this->a1Request('GET', $this->a1BaseUrl() . $path, $opts);

        if (in_array($response->status(), [401, 403], true)) {
            Log::warning("A1: {$path} вернул {$response->status()}, форсируем re-auth");
            $this->a1ForgetTokens();
            $opts['headers']['Authentication'] = $this->a1AccessToken();
            $response = $this->a1Request('GET', $this->a1BaseUrl() . $path, $opts);
        }

        return $response;
    }

    // ─────────────────────────────────────────────────────────────
    // Токены / авторизация
    // ─────────────────────────────────────────────────────────────

    protected function a1AccessToken(): string
    {
        $tokens = $this->a1LoadTokens();

        if (!empty($tokens['access_token']) && !empty($tokens['access_expires_at'])
            && $tokens['access_expires_at'] > time() + 300) {
            return $tokens['access_token'];
        }

        if (!empty($tokens['refresh_token']) && !empty($tokens['refresh_expires_at'])
            && $tokens['refresh_expires_at'] > time() + 60) {
            try {
                return $this->a1RefreshToken($tokens['refresh_token']);
            } catch (\Exception $e) {
                Log::warning('A1: refresh_token не сработал, повторная авторизация: ' . $e->getMessage());
            }
        }

        return $this->a1Authorize();
    }

    protected function a1Authorize(): string
    {
        $credential = base64_encode($this->a1CompanyId() . ':' . $this->a1ApiKey());

        $response = $this->a1Request('GET', $this->a1BaseUrl() . '/auth/tokens', [
            'headers' => ['Authorization' => $credential],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Авторизация A1 провалилась: HTTP ' . $response->status() . ' — ' . $response->body());
        }

        $data = $response->json();
        if (empty($data['access_token'])) {
            throw new \RuntimeException('Авторизация A1: пустой access_token в ответе');
        }

        $this->a1SaveTokens($data);
        return $data['access_token'];
    }

    protected function a1RefreshToken(string $refreshToken): string
    {
        $response = $this->a1Request('PUT', $this->a1BaseUrl() . '/auth/tokens', [
            'headers' => ['Authorization' => $refreshToken],
        ]);

        if (in_array($response->status(), [401, 403], true)) {
            throw new \RuntimeException('refresh_token отклонён (HTTP ' . $response->status() . ')');
        }
        if (!$response->successful()) {
            throw new \RuntimeException('Refresh failed: HTTP ' . $response->status());
        }

        $data = $response->json();
        if (empty($data['access_token'])) {
            throw new \RuntimeException('Refresh A1: пустой access_token в ответе');
        }

        $this->a1SaveTokens($data);
        return $data['access_token'];
    }

    // ─────────────────────────────────────────────────────────────
    // Файловое хранилище токенов (общий файл для всех a1:* команд)
    // ─────────────────────────────────────────────────────────────

    protected function a1TokensPath(): string
    {
        return storage_path('app/a1_tokens.json');
    }

    protected function a1LoadTokens(): array
    {
        $path = $this->a1TokensPath();
        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    protected function a1ForgetTokens(): void
    {
        @unlink($this->a1TokensPath());
    }

    protected function a1SaveTokens(array $data): void
    {
        $now     = time();
        $access  = $data['access_token']  ?? '';
        $refresh = $data['refresh_token'] ?? '';

        // Реальный срок жизни берём из JWT exp (надёжнее догадки time()+TTL).
        $tokens = [
            'access_token'       => $access,
            'access_expires_at'  => $this->a1JwtExp($access)  ?? ($now + 86400),
            'refresh_token'      => $refresh,
            'refresh_expires_at' => $this->a1JwtExp($refresh) ?? ($now + 604800),
        ];

        file_put_contents(
            $this->a1TokensPath(),
            json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /** Достаёт claim `exp` из JWT, не проверяя подпись (нам нужен только срок). */
    private function a1JwtExp(?string $jwt): ?int
    {
        if (!$jwt) {
            return null;
        }
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        return isset($payload['exp']) ? (int) $payload['exp'] : null;
    }

    // ─────────────────────────────────────────────────────────────
    // Разбор списочных ответов A1 (cdr / record/list)
    // ─────────────────────────────────────────────────────────────

    protected function a1UnwrapList($data, array $keys = ['data', 'items', 'records']): array
    {
        if (is_array($data) && (isset($data[0]) || $data === [])) {
            return $data;
        }
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        return is_array($data) ? $data : [];
    }
}
