<?php

namespace Tests\Feature\Mcp;

class OperationsTest extends McpTestCase
{
    public function test_funnel_returns_stages(): void
    {
        $r = $this->mcp('operations/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure([
            'data' => ['leads' => ['online_orders', 'phone_calls', 'total'], 'deals', 'sub_deals', 'returns', 'conversion_rates'],
        ]);
    }

    public function test_timeline_quarterly(): void
    {
        $r = $this->mcp('operations/timeline', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter']);
        $this->assertEnvelope($r);
        $rows = $r->json('data');
        $this->assertCount(4, $rows);
    }

    public function test_by_category_2024(): void
    {
        $r = $this->mcp('operations/by-category', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['razdel_id', 'name', 'url_slug', 'deals', 'revenue_byn']]]);
    }

    public function test_by_location_2019_pobediteley_top(): void
    {
        $r = $this->mcp('operations/by-location', ['from' => '2019-01-01', 'to' => '2019-12-31']);
        $this->assertEnvelope($r);
        $rows = $r->json('data');
        $this->assertSame(3, $rows[0]['office_id']);
    }

    public function test_by_location_post_pobediteley_closure(): void
    {
        $r = $this->mcp('operations/by-location', ['from' => '2022-08-01', 'to' => '2026-04-30']);
        $this->assertEnvelope($r);
        foreach ($r->json('data') as $row) {
            $this->assertNotSame(3, $row['office_id'], 'Pobediteley (id=3) closed 2022-07; must be absent');
        }
    }

    public function test_legacy_orders_stats(): void
    {
        $r = $this->mcp('orders/stats', ['date_from' => '2024-01-01', 'date_to' => '2024-01-31', 'group_by' => 'day']);
        $this->assertEnvelope($r);
    }

    public function test_legacy_deals_list(): void
    {
        $r = $this->mcp('deals/list', ['date_from' => '2024-01-01', 'date_to' => '2024-01-31', 'limit' => 5]);
        $this->assertEnvelope($r);
    }

    public function test_orders_stats_validation(): void
    {
        $this->mcp('orders/stats', ['date_from' => '2024-01-01'])->assertStatus(422);
    }
}
