<?php

namespace Tests\Feature\Mcp;

class InventoryTest extends McpTestCase
{
    public function test_free_tree(): void
    {
        $r = $this->mcp('inventory/free-tree');
        $this->assertEnvelope($r);
    }

    public function test_profitability(): void
    {
        $r = $this->mcp('inventory/profitability', ['min_deals' => 5]);
        $this->assertEnvelope($r);
    }

    public function test_utilization_with_category(): void
    {
        $r = $this->mcp('inventory/utilization', ['from' => '2024-01-01', 'to' => '2024-12-31', 'category' => 'children']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['model_id', 'units', 'rented_days', 'utilization']]]);
    }

    public function test_turnover(): void
    {
        $r = $this->mcp('inventory/turnover', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['model_id', 'units', 'deals', 'turnover']]]);
    }

    public function test_idle_default_90d(): void
    {
        $r = $this->mcp('inventory/idle');
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['model_id', 'units', 'days_idle']]]);
    }

    public function test_idle_validates_days(): void
    {
        $this->mcp('inventory/idle', ['days' => 'abc'])->assertStatus(422);
    }
}
