<?php

namespace Tests\Feature\Mcp;

class InventoryTest extends McpTestCase
{
    // ─── /inventory/free-tree ─────────────────────────────────────────────

    public function test_free_tree_envelope(): void
    {
        $r = $this->mcp('inventory/free-tree');
        $this->assertEnvelope($r);
    }

    public function test_free_tree_supports_as_of_param(): void
    {
        $r = $this->mcp('inventory/free-tree', ['as_of' => '2026-01-01']);
        $r->assertStatus(200);
        $this->assertSame('2026-01-01', $r->json('query.as_of'));
    }

    // ─── /inventory/profitability ─────────────────────────────────────────

    public function test_profitability_min_deals_filter(): void
    {
        // Use min_deals=1 so the dev DB always returns rows; stronger filter is exercised below.
        $rows = $this->mcp('inventory/profitability', ['min_deals' => 1])->json('data');
        $this->assertNotEmpty($rows, 'profitability must return rows for min_deals=1');
        foreach ($rows as $row) {
            $this->assertGreaterThanOrEqual(1, $row['total_deals']);
        }
    }

    public function test_profitability_sorted_desc_by_profit(): void
    {
        $rows = $this->mcp('inventory/profitability', ['min_deals' => 1])->json('data');
        $this->assertGreaterThan(1, count($rows));
        $profits = array_map(static fn ($r) => (float) $r['profit_byn'], $rows);
        $this->assertSortedDesc($profits, 'profit_byn must be sorted DESC');
    }

    public function test_profitability_validates_negative_min_deals(): void
    {
        $this->mcp('inventory/profitability', ['min_deals' => -1])->assertStatus(422);
    }

    public function test_profitability_validates_non_integer_cat_id(): void
    {
        $this->mcp('inventory/profitability', ['cat_id' => 'abc'])->assertStatus(422);
    }

    // ─── /inventory/utilization ───────────────────────────────────────────

    public function test_utilization_envelope_and_columns(): void
    {
        $r = $this->mcp('inventory/utilization', ['from' => '2024-01-01', 'to' => '2024-12-31', 'category' => 'children']);
        $this->assertEnvelope($r);
        if (!empty($r->json('data'))) {
            $r->assertJsonStructure(['data' => [['model_id', 'model_name', 'units', 'deals_in_period', 'rented_days', 'utilization']]]);
        }
    }

    public function test_utilization_is_a_fraction_between_0_and_1(): void
    {
        $rows = $this->mcp('inventory/utilization', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        foreach ($rows as $row) {
            $this->assertGreaterThanOrEqual(0, $row['utilization']);
            $this->assertLessThanOrEqual(1, $row['utilization']);
        }
    }

    public function test_utilization_unknown_category_returns_empty(): void
    {
        $this->assertSame([], $this->mcp('inventory/utilization', ['from' => '2024-01-01', 'to' => '2024-12-31', 'category' => 'tools'])->json('data'));
    }

    // ─── /inventory/turnover ──────────────────────────────────────────────

    public function test_turnover_envelope_and_columns(): void
    {
        $r = $this->mcp('inventory/turnover', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $rows = $r->json('data');
        $this->assertNotEmpty($rows);
        $r->assertJsonStructure(['data' => [['model_id', 'units', 'deals', 'turnover']]]);
    }

    public function test_turnover_sorted_desc(): void
    {
        $rows = $this->mcp('inventory/turnover', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        // Some rows may have null turnover (units = 0); skip them in the sort check.
        $values = array_filter(array_map(static fn ($r) => $r['turnover'] === null ? null : (float) $r['turnover'], $rows), static fn ($v) => $v !== null);
        $this->assertSortedDesc(array_values($values));
    }

    public function test_turnover_with_category_filter(): void
    {
        $r = $this->mcp('inventory/turnover', ['from' => '2024-01-01', 'to' => '2024-12-31', 'category' => 'children']);
        $this->assertEnvelope($r);
    }

    // ─── /inventory/idle ──────────────────────────────────────────────────

    public function test_idle_default_90_days(): void
    {
        $r = $this->mcp('inventory/idle');
        $this->assertEnvelope($r);
        $this->assertSame(90, $r->json('query.days'));
    }

    public function test_idle_validates_days(): void
    {
        $this->mcp('inventory/idle', ['days' => 'abc'])->assertStatus(422);
        $this->mcp('inventory/idle', ['days' => 0])->assertStatus(422);
        $this->mcp('inventory/idle', ['days' => 999999])->assertStatus(422);
    }

    public function test_idle_validates_category(): void
    {
        $this->mcp('inventory/idle', ['category' => 'invalid'])->assertStatus(422);
    }

    public function test_idle_includes_never_rented_models(): void
    {
        // 'days_idle' is null for models that have never been rented; the
        // endpoint must include them (they're the most idle items of all).
        $rows = $this->mcp('inventory/idle', ['days' => 365])->json('data');
        $never = collect($rows)->whereNull('last_deal_ts');
        $this->assertGreaterThan(0, $never->count(), 'expect models that have never been rented');
    }

    public function test_idle_orders_never_rented_first(): void
    {
        $rows = $this->mcp('inventory/idle', ['days' => 365])->json('data');
        // Never-rented (last_deal_ts = null) come first; then by oldest last_deal_ts ASC.
        $sawDated = false;
        foreach ($rows as $row) {
            if ($row['last_deal_ts'] === null) {
                $this->assertFalse($sawDated, 'never-rented entries must precede dated ones');
            } else {
                $sawDated = true;
            }
        }
    }

    public function test_idle_query_includes_cutoff_iso(): void
    {
        $q = $this->mcp('inventory/idle', ['days' => 30])->json('query');
        $this->assertArrayHasKey('cutoff_iso', $q);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $q['cutoff_iso']);
    }

    // ─── auth ─────────────────────────────────────────────────────────────

    public function test_inventory_endpoints_require_token(): void
    {
        foreach (['inventory/free-tree', 'inventory/profitability', 'inventory/utilization', 'inventory/turnover', 'inventory/idle'] as $p) {
            $this->assertRequiresToken($p);
        }
    }
}
