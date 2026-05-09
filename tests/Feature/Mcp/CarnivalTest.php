<?php

namespace Tests\Feature\Mcp;

class CarnivalTest extends McpTestCase
{
    public function test_funnel(): void
    {
        $r = $this->mcp('carnival/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => ['bookings', 'approved', 'issued', 'returned', 'conversion_rates']]);
    }

    public function test_seasonality_december_peak(): void
    {
        $r = $this->mcp('carnival/seasonality', ['years' => 5]);
        $this->assertEnvelope($r);
        $rows = $r->json('data');
        $peak = collect($rows)->sortByDesc('seasonality_index')->first();
        $this->assertSame(12, $peak['month_num']);
    }

    public function test_revenue(): void
    {
        $r = $this->mcp('carnival/revenue', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['period', 'bookings', 'revenue_total_byn']]]);
    }
}
