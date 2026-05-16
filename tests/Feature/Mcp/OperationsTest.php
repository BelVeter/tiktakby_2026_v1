<?php

namespace Tests\Feature\Mcp;

class OperationsTest extends McpTestCase
{
    // ─── /operations/funnel ───────────────────────────────────────────────

    public function test_funnel_envelope_and_stages(): void
    {
        $r = $this->mcp('operations/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure([
            'data' => ['leads' => ['online_orders', 'phone_calls', 'total'], 'deals', 'issuance_events', 'renewal_events', 'sub_deals', 'returns', 'conversion_rates'],
        ]);
    }

    public function test_funnel_leads_total_equals_orders_plus_calls(): void
    {
        $d = $this->mcp('operations/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        $this->assertSame($d['leads']['online_orders'] + $d['leads']['phone_calls'], $d['leads']['total']);
    }

    public function test_funnel_conversion_rates_are_computed(): void
    {
        $d = $this->mcp('operations/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        $expected = $d['leads']['total'] > 0 ? round($d['deals'] / $d['leads']['total'], 4) : null;
        $this->assertSame($expected, $d['conversion_rates']['lead_to_deal']);
    }

    public function test_funnel_with_category_filter_includes_warning(): void
    {
        $r = $this->mcp('operations/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31', 'category' => 'children']);
        $r->assertStatus(200);
        $warnings = $r->json('meta.warnings');
        $this->assertNotEmpty($warnings);
        $this->assertSame('phone_calls_not_filtered', $warnings[0]['code']);
    }

    public function test_funnel_no_warning_when_category_all(): void
    {
        $w = $this->mcp('operations/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('meta.warnings');
        $this->assertEmpty($w);
    }

    public function test_funnel_unknown_category_tools_empty(): void
    {
        $d = $this->mcp('operations/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31', 'category' => 'tools'])->json('data');
        $this->assertSame([], $d);
    }

    public function test_funnel_validates_invalid_category(): void
    {
        $this->mcp('operations/funnel', ['category' => 'invalid'])->assertStatus(422);
    }

    // ─── /operations/timeline ─────────────────────────────────────────────

    public function test_timeline_quarterly_yields_4_rows_for_full_year(): void
    {
        $rows = $this->mcp('operations/timeline', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter'])->json('data');
        $this->assertCount(4, $rows);
    }

    public function test_timeline_monthly_yields_12_rows(): void
    {
        $rows = $this->mcp('operations/timeline', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'month'])->json('data');
        $this->assertCount(12, $rows);
    }

    public function test_timeline_row_columns(): void
    {
        $rows = $this->mcp('operations/timeline', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter'])->json('data');
        foreach (['period', 'online_orders', 'phone_calls', 'leads_total', 'deals', 'sub_deals', 'returns'] as $f) {
            $this->assertArrayHasKey($f, $rows[0]);
        }
    }

    // ─── /operations/by-category ──────────────────────────────────────────

    public function test_by_category_2024_includes_known_razdels(): void
    {
        $rows = $this->mcp('operations/by-category', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        $names = array_column($rows, 'name');
        $this->assertContains('Детские товары', $names);
        $this->assertContains('Карнавальные костюмы', $names);
    }

    public function test_by_category_sorted_desc_by_deals(): void
    {
        $rows = $this->mcp('operations/by-category', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        $this->assertSortedDesc(array_map(static fn ($r) => $r['deals'], $rows));
    }

    public function test_by_category_children_dominates_2024(): void
    {
        $rows = $this->mcp('operations/by-category', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        $top  = $rows[0];
        $this->assertSame('Детские товары', $top['name']);
        $this->assertGreaterThan(3000, $top['deals'], 'Children > 3000 deals in 2024');
    }

    // ─── /operations/by-location ──────────────────────────────────────────

    public function test_by_location_2019_pobediteley_top_acceptance(): void
    {
        // Acceptance: in 2019 office id=3 (Pobediteley) is the most active office.
        $rows = $this->mcp('operations/by-location', ['from' => '2019-01-01', 'to' => '2019-12-31'])->json('data');
        $this->assertSame(3, $rows[0]['office_id']);
        $this->assertGreaterThan(8000, $rows[0]['deals'], '2019 Pobediteley ≈ 8 496 deals');
    }

    public function test_by_location_pobediteley_zero_after_closure(): void
    {
        // Acceptance: office id=3 closed 2022-07; after closure only residual
        // cl_payment sub-deals (settlements on old deals) may keep the row alive
        // with zero issuance_events and tiny revenue (< 10 000 BYN).
        $rows = $this->mcp('operations/by-location', ['from' => '2022-08-01', 'to' => '2026-04-30'])->json('data');
        $byId = collect($rows)->keyBy('office_id');
        if (isset($byId[3])) {
            $this->assertSame(0, (int) $byId[3]['issuance_events'], 'Pobediteley must have 0 issuances after closure');
            $this->assertLessThan(10000.0, (float) $byId[3]['revenue_byn'], 'Pobediteley residual revenue must be < 10 000 BYN');
        }
    }

    public function test_by_location_issuance_events_column_present(): void
    {
        $rows = $this->mcp('operations/by-location', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        foreach ($rows as $row) {
            $this->assertArrayHasKey('issuance_events', $row);
            $this->assertGreaterThanOrEqual(0, (int) $row['issuance_events']);
            $this->assertArrayHasKey('renewal_events', $row);
            $this->assertGreaterThanOrEqual(0, (int) $row['renewal_events']);
        }
    }

    public function test_by_location_sorted_desc_by_deals(): void
    {
        $rows = $this->mcp('operations/by-location', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        $this->assertSortedDesc(array_map(static fn ($r) => $r['deals'], $rows));
    }

    // ─── auth ─────────────────────────────────────────────────────────────

    public function test_operations_endpoints_require_token(): void
    {
        $this->assertRequiresToken('operations/funnel');
        $this->assertRequiresToken('operations/timeline');
        $this->assertRequiresToken('operations/by-category');
        $this->assertRequiresToken('operations/by-location');
    }
}
