<?php

namespace Tests\Feature\Mcp;

class CategoriesTest extends McpTestCase
{
    public function test_legacy_performance_envelope_and_columns(): void
    {
        $r = $this->mcp('categories/performance', ['date_from' => '2024-01-01', 'date_to' => '2024-12-31']);
        $this->assertEnvelope($r);
    }

    public function test_legacy_performance_validates_required_dates(): void
    {
        $this->mcp('categories/performance')->assertStatus(422);
    }

    public function test_legacy_performance_validates_date_order(): void
    {
        $this->mcp('categories/performance', ['date_from' => '2024-12-31', 'date_to' => '2024-01-01'])->assertStatus(422);
    }

    public function test_seasonality_costumes_december_peak(): void
    {
        $rows = $this->mcp('categories/seasonality', ['category' => 'costumes', 'years' => 5])->json('data');
        $this->assertCount(12, $rows);
        $peak = collect($rows)->sortByDesc('seasonality_index')->first();
        $this->assertSame(12, $peak['month_num']);
        $this->assertSame('December', $peak['month_name']);
        $this->assertGreaterThan(3.0, $peak['seasonality_index'], 'December costumes idx > 3 (very strong NYE peak)');
    }

    public function test_seasonality_children_summer_peak(): void
    {
        $rows = $this->mcp('categories/seasonality', ['category' => 'children', 'years' => 5])->json('data');
        $peak = collect($rows)->sortByDesc('seasonality_index')->first();
        // Children peak is in summer (June or July) — outdoor toys, dachas.
        $this->assertContains($peak['month_num'], [6, 7], 'children peak in June or July');
    }

    public function test_seasonality_index_average_is_one(): void
    {
        $rows = $this->mcp('categories/seasonality', ['category' => 'children', 'years' => 5])->json('data');
        $avg = array_sum(array_column($rows, 'seasonality_index')) / count($rows);
        $this->assertEqualsWithDelta(1.0, $avg, 0.01, 'seasonality_index avg should be 1.0 by definition');
    }

    public function test_seasonality_returns_all_12_months(): void
    {
        $rows = $this->mcp('categories/seasonality', ['category' => 'children'])->json('data');
        $months = array_column($rows, 'month_num');
        $this->assertSame(range(1, 12), $months);
    }

    public function test_seasonality_unknown_category_returns_422(): void
    {
        $this->mcp('categories/seasonality', ['category' => 'invalid'])->assertStatus(422);
    }

    public function test_seasonality_tools_returns_empty_data(): void
    {
        // tools has no razdel — empty result, but still 200.
        $r = $this->mcp('categories/seasonality', ['category' => 'tools']);
        $r->assertStatus(200);
        $this->assertSame([], $r->json('data'));
    }

    public function test_seasonality_validates_years_range(): void
    {
        $this->mcp('categories/seasonality', ['years' => 99])->assertStatus(422);
        $this->mcp('categories/seasonality', ['years' => 0])->assertStatus(422);
    }

    public function test_categories_endpoints_require_token(): void
    {
        $this->assertRequiresToken('categories/performance?date_from=2024-01-01&date_to=2024-01-31');
        $this->assertRequiresToken('categories/seasonality');
    }
}
