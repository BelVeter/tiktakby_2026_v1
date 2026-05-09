<?php

namespace Tests\Feature\Mcp;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Common harness for MCP API feature tests.
 *
 * - Forwards the configured Bearer token so requests pass mcp.token middleware.
 * - Force Accept: application/json so validation failures yield 422 JSON.
 * - Bypasses geo/audit middleware that depend on external services.
 */
abstract class McpTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('mcp.api_token'))) {
            // Tests need a token even when .env hasn't set one.
            config(['mcp.api_token' => 'test-token-for-phpunit']);
        }
        // Disable country restriction & audit logging for tests.
        config(['mcp.geo_allowed_countries' => []]);

        $this->withoutMiddleware([
            \App\Http\Middleware\McpAuditLogMiddleware::class,
        ]);
    }

    protected function mcp(string $path, array $query = []): TestResponse
    {
        $url = '/api/mcp/v1/' . ltrim($path, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        return $this->getJson($url, [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
    }

    /**
     * Assert standard envelope shape { query, data, meta:{currency,warnings} }.
     */
    protected function assertEnvelope(TestResponse $response): void
    {
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'query',
            'data',
            'meta' => ['currency', 'warnings'],
        ]);
        $response->assertJsonPath('meta.currency', 'BYN');
    }
}
