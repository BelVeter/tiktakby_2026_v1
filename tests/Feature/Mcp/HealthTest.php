<?php

namespace Tests\Feature\Mcp;

class HealthTest extends McpTestCase
{
    public function test_health_returns_envelope_and_status_ok(): void
    {
        $r = $this->mcp('health');
        $this->assertEnvelope($r);
        $r->assertJsonPath('data.status', 'ok');
        $r->assertJsonStructure(['data' => ['status', 'version', 'timestamp']]);
    }

    public function test_health_requires_token(): void
    {
        $this->assertRequiresToken('health');
    }

    public function test_health_rejects_wrong_token(): void
    {
        $this->getJson('/api/mcp/v1/health', ['Authorization' => 'Bearer wrong-token-here'])
            ->assertStatus(401);
    }

    public function test_openapi_spec_is_served(): void
    {
        $r = $this->mcp('openapi.json');
        $r->assertStatus(200);
        $r->assertHeader('Content-Type', 'application/json');
        $spec = $r->json();
        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertGreaterThanOrEqual(31, count($spec['paths']));
    }

    public function test_openapi_describes_all_implemented_endpoints(): void
    {
        $spec = $this->mcp('openapi.json')->json();
        $expected = [
            '/health', '/openapi.json',
            '/meta/categories', '/meta/locations', '/meta/expense-items', '/meta/income-items', '/meta/data-freshness',
            '/finance/pnl', '/finance/revenue', '/finance/expenses', '/finance/cash-flow',
            '/operations/funnel', '/operations/timeline', '/operations/by-category', '/operations/by-location',
            '/inventory/free-tree', '/inventory/profitability', '/inventory/utilization', '/inventory/turnover', '/inventory/idle',
            '/customers/timeline', '/customers/cohorts', '/customers/repeat-intervals', '/clients/ltv',
            '/geo/clients-by-city',
            '/locations/performance', '/locations/lifecycle',
            '/categories/seasonality', '/categories/performance',
            '/carnival/funnel', '/carnival/seasonality', '/carnival/revenue',
            '/export/monthly/{topic}',
        ];
        foreach ($expected as $path) {
            $this->assertArrayHasKey($path, $spec['paths'], "spec must document $path");
        }
    }
}
