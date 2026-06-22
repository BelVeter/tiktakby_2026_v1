<?php

namespace Tests\Unit\Console;

use App\Console\Concerns\InteractsWithA1Api;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * DB-free unit tests for the A1 ВАТС API coordination trait.
 *
 * These tests exercise the trait via a fixture class that overrides
 * a1TokensPath() so the real storage/app/a1_tokens.json is never touched,
 * and uses Http::fake() so no real network calls are made. No DB at all.
 */
class InteractsWithA1ApiTest extends TestCase
{
    /** @var string */
    private $tokenPath;

    /** @var A1ApiTestFixture */
    private $sut;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.a1.company_id'  => '1080',
            'services.a1.api_key'     => 'testkey',
            'services.a1.throttle_ms' => 0, // disable throttling unless a test overrides it
        ]);

        // A temp token file that lives nowhere near the real one.
        $this->tokenPath = storage_path('framework/testing/a1_tokens_test_' . uniqid() . '.json');
        @unlink($this->tokenPath);

        $this->sut = new A1ApiTestFixture();
        $this->sut->setTokensPath($this->tokenPath);

        // Make sure the throttle cache key is clean between tests.
        Cache::forget('a1:last_request_at');
    }

    protected function tearDown(): void
    {
        if ($this->tokenPath && file_exists($this->tokenPath)) {
            @unlink($this->tokenPath);
        }
        parent::tearDown();
    }

    /**
     * Build a valid access-token JWT whose `exp` is in the future.
     */
    private function makeJwt(int $expOffset = 3600): string
    {
        $b64url = function ($json) {
            return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        };

        $header  = $b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $b64url(json_encode([
            'company_id' => 1080,
            'sub'        => 'ACCESS_OPENAPI_TOKEN',
            'exp'        => time() + $expOffset,
        ]));

        return $header . '.' . $payload . '.' . 'sig';
    }

    private function tokenResponse(): array
    {
        $jwt = $this->makeJwt();

        return ['access_token' => $jwt, 'refresh_token' => $jwt];
    }

    /** Test 1: 429 retried honoring `Retry after: N ms` body pattern. */
    public function testRequestRetriesOn429WithBodyRetryAfter(): void
    {
        $url = 'https://vats.a1.by/crm-api/open-api/v1/cdr';

        Http::fake([
            '*/cdr*' => Http::sequence()
                ->push(json_encode(['details' => ['Retry after: 30 ms']]), 429)
                ->push(json_encode(['ok' => true]), 200),
        ]);

        $response = $this->sut->reqPublic('GET', $url);

        $this->assertSame(200, $response->status());
        Http::assertSentCount(2);
    }

    /** Test 2: 429 exhausted returns last response after max attempts (4). */
    public function testRequestReturnsLastResponseAfter429Exhausted(): void
    {
        $url = 'https://vats.a1.by/crm-api/open-api/v1/cdr';

        Http::fake([
            '*/cdr*' => Http::response(
                json_encode(['details' => ['Retry after: 5 ms']]),
                429
            ),
        ]);

        $response = $this->sut->reqPublic('GET', $url);

        $this->assertSame(429, $response->status());
        Http::assertSentCount(4); // a1MaxAttempts()
    }

    /** Test 3: a1AuthGet re-auths exactly once on 401, then retries. */
    public function testAuthGetReauthsOnceOn401(): void
    {
        Http::fake([
            '*/auth/tokens*' => Http::response($this->tokenResponse(), 200),
            '*/cdr*'         => Http::sequence()
                ->push('', 401)
                ->push(json_encode(['ok' => true]), 200),
        ]);

        $response = $this->sut->authGetPublic('/cdr', ['from' => '2026-06-01']);

        $this->assertSame(200, $response->status());

        // /cdr hit twice: first 401, then the retry after re-auth.
        $cdrCalls = 0;
        $authCalls = 0;
        Http::assertSent(function ($request) use (&$cdrCalls, &$authCalls) {
            if (strpos($request->url(), '/cdr') !== false) {
                $cdrCalls++;
            }
            if (strpos($request->url(), '/auth/tokens') !== false) {
                $authCalls++;
            }
            return true;
        });

        $this->assertSame(2, $cdrCalls, '/cdr should be hit twice (initial + retry)');
        $this->assertSame(2, $authCalls, '/auth/tokens should be hit twice (initial auth + one re-auth)');

        // The temp token file should exist after the re-auth flow saved fresh tokens.
        $this->assertFileExists($this->tokenPath);
    }

    /** Test 4: access token reused while JWT exp is valid (no redundant re-auth). */
    public function testAccessTokenReusedWhileJwtExpValid(): void
    {
        Http::fake([
            '*/auth/tokens*' => Http::response($this->tokenResponse(), 200),
        ]);

        $first  = $this->sut->accessTokenPublic();
        $second = $this->sut->accessTokenPublic();

        $this->assertNotEmpty($first);
        $this->assertSame($first, $second, 'Second call must reuse the cached token');

        // Only ONE auth call — second call reused the token from the temp file.
        $authCalls = 0;
        Http::assertSent(function ($request) use (&$authCalls) {
            if (strpos($request->url(), '/auth/tokens') !== false) {
                $authCalls++;
            }
            return true;
        });
        $this->assertSame(1, $authCalls, '/auth/tokens should be sent exactly once');
    }

    /** Test 5: throttle paces requests when enabled, fast when disabled. */
    public function testThrottlePacesRequestsWhenEnabled(): void
    {
        $url = 'https://vats.a1.by/crm-api/open-api/v1/cdr';

        // --- With throttling enabled (200ms min interval) ---
        config(['services.a1.throttle_ms' => 200]);
        Cache::forget('a1:last_request_at');

        Http::fake(['*/cdr*' => Http::response(json_encode(['ok' => true]), 200)]);

        $start = microtime(true);
        $this->sut->reqPublic('GET', $url);
        $this->sut->reqPublic('GET', $url);
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThanOrEqual(
            0.18,
            $elapsed,
            'Two throttled requests should take at least ~200ms apart'
        );
        // Cache key must be set after a throttled request.
        $this->assertGreaterThan(0, (float) Cache::get('a1:last_request_at'));

        // --- With throttling disabled (0ms) ---
        config(['services.a1.throttle_ms' => 0]);
        Cache::forget('a1:last_request_at');

        Http::fake(['*/cdr*' => Http::response(json_encode(['ok' => true]), 200)]);

        $start = microtime(true);
        $this->sut->reqPublic('GET', $url);
        $this->sut->reqPublic('GET', $url);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(
            0.1,
            $elapsed,
            'Two un-throttled requests should complete quickly'
        );
        // Throttle disabled => no timestamp written.
        $this->assertSame(0, (int) Cache::get('a1:last_request_at', 0));
    }

    /** Test 6: Retry-After header (seconds) is parsed and honored. */
    public function testRequestRetriesOnHeaderRetryAfterSeconds(): void
    {
        $url = 'https://vats.a1.by/crm-api/open-api/v1/cdr';

        Http::fake([
            '*/cdr*' => Http::sequence()
                ->push('', 429, ['Retry-After' => '1'])
                ->push(json_encode(['ok' => true]), 200),
        ]);

        $start = microtime(true);
        $response = $this->sut->reqPublic('GET', $url);
        $elapsed = microtime(true) - $start;

        $this->assertSame(200, $response->status());
        Http::assertSentCount(2);
        // Header says 1 second — the retry must wait roughly that long.
        $this->assertGreaterThanOrEqual(0.9, $elapsed, 'Retry-After: 1 should pause ~1s');
    }
}

/**
 * Test fixture: uses the trait, redirects token storage to a temp path,
 * and exposes the protected methods under test as public wrappers.
 */
class A1ApiTestFixture
{
    use InteractsWithA1Api;

    /** @var string */
    private $tokensPathOverride = '';

    public function setTokensPath(string $path): void
    {
        $this->tokensPathOverride = $path;
    }

    protected function a1TokensPath(): string
    {
        return $this->tokensPathOverride;
    }

    public function reqPublic(string $method, string $url, array $options = [])
    {
        return $this->a1Request($method, $url, $options);
    }

    public function authGetPublic(string $path, array $query = [], int $timeout = 0)
    {
        return $this->a1AuthGet($path, $query, $timeout);
    }

    public function accessTokenPublic(): string
    {
        return $this->a1AccessToken();
    }
}
