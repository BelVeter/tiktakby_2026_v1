<?php

namespace Tests\Feature\Mcp;

class LocationsTest extends McpTestCase
{
    public function test_performance(): void
    {
        $r = $this->mcp('locations/performance', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['period', 'office_id', 'deals', 'revenue_byn', 'avg_ticket_byn']]]);
    }

    public function test_lifecycle_includes_pobediteley_closure(): void
    {
        $r = $this->mcp('locations/lifecycle');
        $this->assertEnvelope($r);
        $rows = $r->json('data');
        $byId = collect($rows)->keyBy('office_id');
        $this->assertSame(0, (int) $byId[3]['currently_active']);
        $this->assertSame('2022-07-06', $byId[3]['last_deal_date']);
    }
}
