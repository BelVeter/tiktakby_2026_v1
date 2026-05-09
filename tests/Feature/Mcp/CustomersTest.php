<?php

namespace Tests\Feature\Mcp;

class CustomersTest extends McpTestCase
{
    public function test_timeline(): void
    {
        $r = $this->mcp('customers/timeline', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['period', 'new_clients', 'active_clients', 'returning_clients', 'new_active_clients']]]);
    }

    public function test_cohorts(): void
    {
        $r = $this->mcp('customers/cohorts', ['from' => '2024-01-01', 'to' => '2024-06-30']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['cohort', 'size', 'retention']]]);
    }

    public function test_repeat_intervals(): void
    {
        $r = $this->mcp('customers/repeat-intervals', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => ['count', 'mean_days', 'median_days', 'p25_days', 'p75_days', 'histogram']]);
    }

    public function test_legacy_clients_ltv(): void
    {
        $r = $this->mcp('clients/ltv', ['limit' => 5]);
        $this->assertEnvelope($r);
    }
}
