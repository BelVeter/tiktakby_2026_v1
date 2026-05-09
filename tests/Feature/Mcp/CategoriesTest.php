<?php

namespace Tests\Feature\Mcp;

class CategoriesTest extends McpTestCase
{
    public function test_legacy_performance(): void
    {
        $r = $this->mcp('categories/performance', ['date_from' => '2024-01-01', 'date_to' => '2024-12-31']);
        $this->assertEnvelope($r);
    }

    public function test_seasonality_costumes_december_peak(): void
    {
        $r = $this->mcp('categories/seasonality', ['category' => 'costumes', 'years' => 5]);
        $this->assertEnvelope($r);
        $rows = $r->json('data');
        $this->assertCount(12, $rows);
        $peak = collect($rows)->sortByDesc('seasonality_index')->first();
        $this->assertSame(12, $peak['month_num']);
    }

    public function test_seasonality_unknown_category_422(): void
    {
        $this->mcp('categories/seasonality', ['category' => 'invalid'])->assertStatus(422);
    }
}
