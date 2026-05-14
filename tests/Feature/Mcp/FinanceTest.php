<?php

namespace Tests\Feature\Mcp;

class FinanceTest extends McpTestCase
{
    // ─── /finance/pnl ─────────────────────────────────────────────────────

    public function test_pnl_2019_baseline_acceptance(): void
    {
        // Methodology lock 2026-05-14: revenue is SUM(sub_deals.r_paid+delivery_paid)
        // by acc_date over UNION(act, arch). 2019 reproduces /bb/dohrash2.php exactly.
        $rows = $this->mcp('finance/pnl', ['from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'year'])->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('2019', $rows[0]['period']);
        $this->assertEqualsWithDelta(424231.72, $rows[0]['revenue_byn'], 1.0);
        $this->assertEqualsWithDelta(25484.82,  $rows[0]['ebitda_byn'],  1.0);
        $this->assertEqualsWithDelta(400951.62, $rows[0]['revenue_non_carnival_byn'], 1.0);
        $this->assertEqualsWithDelta(23280.10,  $rows[0]['revenue_carnival_byn'],     1.0);
    }

    public function test_pnl_2024_baseline_acceptance(): void
    {
        $rows = $this->mcp('finance/pnl', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'year'])->json('data');
        $this->assertEqualsWithDelta(293189.25, $rows[0]['revenue_byn'], 1.0);
        $this->assertEqualsWithDelta(-12950.45, $rows[0]['ebitda_byn'], 1.0);
    }

    public function test_pnl_carnival_split_columns_sum_to_total(): void
    {
        $rows = $this->mcp('finance/pnl', ['from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'year'])->json('data');
        $r = $rows[0];
        $this->assertEqualsWithDelta(
            $r['revenue_byn'],
            $r['revenue_non_carnival_byn'] + $r['revenue_carnival_byn'],
            0.05
        );
    }

    public function test_pnl_include_carnival_false_zeroes_carnival_revenue(): void
    {
        $rows = $this->mcp('finance/pnl', ['from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'year', 'include_carnival' => 'false'])->json('data');
        $r = $rows[0];
        $this->assertSame(0.0, (float) $r['revenue_carnival_byn']);
        $this->assertEqualsWithDelta($r['revenue_byn'], $r['revenue_non_carnival_byn'], 0.05);
    }

    public function test_pnl_2025_warning_present(): void
    {
        $r = $this->mcp('finance/pnl', ['from' => '2025-01-01', 'to' => '2025-12-31', 'granularity' => 'year']);
        $this->assertEnvelope($r);
        $w = $r->json('meta.warnings');
        $this->assertCount(1, $w);
        $this->assertSame('fy2025_bank_channel_gap', $w[0]['code']);
        $this->assertSame('D-OPEN-FY2025',           $w[0]['ref']);
        $this->assertStringContainsString('банковские расходы', $w[0]['message']);
    }

    public function test_pnl_2024_no_warning(): void
    {
        $w = $this->mcp('finance/pnl', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('meta.warnings');
        $this->assertEmpty($w);
    }

    public function test_pnl_period_overlapping_2025_triggers_warning(): void
    {
        // 2024-Q4 to 2025-Q1 — only the last day overlaps 2025 → still warns.
        $w = $this->mcp('finance/pnl', ['from' => '2024-10-01', 'to' => '2025-01-15'])->json('meta.warnings');
        $this->assertCount(1, $w);
        $this->assertSame('fy2025_bank_channel_gap', $w[0]['code']);
    }

    public function test_pnl_expense_buckets_sum_to_total(): void
    {
        $rows = $this->mcp('finance/pnl', ['from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'year'])->json('data');
        $r = $rows[0];
        $sum = $r['cogs_byn'] + $r['opex_payroll_byn'] + $r['opex_rent_byn']
             + $r['opex_marketing_byn'] + $r['opex_admin_byn'] + $r['taxes_byn'] + $r['financial_byn'];
        $this->assertEqualsWithDelta($r['expenses_total_byn'], $sum, 0.05);
    }

    public function test_pnl_ebitda_equals_revenue_minus_expenses(): void
    {
        $rows = $this->mcp('finance/pnl', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'year'])->json('data');
        $r = $rows[0];
        $this->assertEqualsWithDelta($r['revenue_byn'] - $r['expenses_total_byn'], $r['ebitda_byn'], 0.01);
    }

    public function test_pnl_monthly_returns_12_rows_for_full_year(): void
    {
        $rows = $this->mcp('finance/pnl', ['from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'month'])->json('data');
        $this->assertCount(12, $rows);
        $this->assertSame('2019-01', $rows[0]['period']);
        $this->assertSame('2019-12', $rows[11]['period']);
    }

    public function test_pnl_quarter_granularity(): void
    {
        $rows = $this->mcp('finance/pnl', ['from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'quarter'])->json('data');
        $this->assertCount(4, $rows);
        $this->assertSame('2019-Q1', $rows[0]['period']);
    }

    public function test_pnl_invalid_date_returns_422(): void
    {
        $this->mcp('finance/pnl', ['from' => 'not-a-date'])->assertStatus(422);
    }

    public function test_pnl_invalid_granularity_returns_422(): void
    {
        $this->mcp('finance/pnl', ['granularity' => 'fortnight'])->assertStatus(422);
    }

    public function test_pnl_meta_includes_expense_buckets_dictionary(): void
    {
        $buckets = $this->mcp('finance/pnl', ['from' => '2024-01-01', 'to' => '2024-01-31'])->json('meta.expense_buckets');
        $this->assertIsArray($buckets);
        $this->assertArrayHasKey('cogs', $buckets);
        $this->assertContains('zpl', $buckets['opex_payroll']);
        $this->assertContains('adv', $buckets['opex_marketing']);
        $this->assertContains('fszn_tax', $buckets['taxes']);
    }

    // ─── /finance/revenue ─────────────────────────────────────────────────

    public function test_revenue_envelope_and_columns(): void
    {
        $r = $this->mcp('finance/revenue', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['period', 'rent_byn', 'delivery_byn', 'total_byn', 'deals', 'issuance_events']]]);
    }

    public function test_revenue_2024_total_matches_pnl_revenue(): void
    {
        $totalFromRevenue = collect($this->mcp('finance/revenue', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data'))
            ->sum('total_byn');
        $pnlRevenue = $this->mcp('finance/pnl', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'year'])->json('data.0.revenue_byn');
        $this->assertEqualsWithDelta($pnlRevenue, $totalFromRevenue, 0.05);
    }

    public function test_revenue_category_filter_smaller_than_total(): void
    {
        $totalAll      = collect($this->mcp('finance/revenue', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data'))->sum('total_byn');
        $totalChildren = collect($this->mcp('finance/revenue', ['from' => '2024-01-01', 'to' => '2024-12-31', 'category' => 'children'])->json('data'))->sum('total_byn');
        $this->assertGreaterThan(0, $totalChildren);
        $this->assertLessThan($totalAll, $totalChildren);
    }

    public function test_revenue_unknown_category_tools_returns_empty(): void
    {
        $this->assertSame([], $this->mcp('finance/revenue', ['from' => '2024-01-01', 'to' => '2024-12-31', 'category' => 'tools'])->json('data'));
    }

    public function test_revenue_location_filter_pobediteley_2019(): void
    {
        // Pobediteley (id=3) was the busiest office in 2019 (D-OPEN-LOCATIONS).
        // New methodology uses sub_deal.place — visible now (was 0 with first_rent_place).
        $rows = $this->mcp('finance/revenue', ['from' => '2019-01-01', 'to' => '2019-12-31', 'location' => '3'])->json('data');
        $this->assertNotEmpty($rows);
        $sum = collect($rows)->sum('total_byn');
        $this->assertGreaterThan(150000, $sum);  // 2019 Pobediteley revenue ≈ 186k
    }

    public function test_revenue_location_filter_pobediteley_post_closure_negligible(): void
    {
        // Pobediteley closed mid-2022. Some lingering sub-deal payments still
        // land on place=3 in 2023 (likely cl_payment for old deals) — small
        // residual is fine, but it should be a small fraction of the active years.
        $rows = $this->mcp('finance/revenue', ['from' => '2023-01-01', 'to' => '2023-12-31', 'location' => '3'])->json('data');
        $sum = collect($rows)->sum('total_byn');
        $this->assertLessThan(5000, $sum);
    }

    public function test_revenue_courier_pseudo_office(): void
    {
        $rows = $this->mcp('finance/revenue', ['from' => '2019-01-01', 'to' => '2019-12-31', 'location' => 'courier'])->json('data');
        $this->assertNotEmpty($rows);
        $sum = collect($rows)->sum('total_byn');
        $this->assertGreaterThan(80000, $sum);
    }

    // ─── /finance/expenses ────────────────────────────────────────────────

    public function test_expenses_bank_channel_2024(): void
    {
        // D-OPEN-FY2025: bank-channel 2024 ≈ 174 762 BYN (493 transactions).
        $rows = $this->mcp('finance/expenses', ['from' => '2024-01-01', 'to' => '2024-12-31', 'channel' => 'bank', 'granularity' => 'year'])->json('data');
        $total = array_sum(array_column($rows, 'amount_byn'));
        $this->assertEqualsWithDelta(174762, $total, 5.0);
    }

    public function test_expenses_bank_channel_2025_collapsed(): void
    {
        // D-OPEN-FY2025 confirmation: 2025 bank ≈ 17 639 (90% drop).
        $total = array_sum(array_column($this->mcp('finance/expenses', ['from' => '2025-01-01', 'to' => '2025-12-31', 'channel' => 'bank', 'granularity' => 'year'])->json('data'), 'amount_byn'));
        $this->assertEqualsWithDelta(17639, $total, 5.0);
    }

    public function test_expenses_cash_channel_includes_office_tills(): void
    {
        $rows = $this->mcp('finance/expenses', ['from' => '2024-01-01', 'to' => '2024-12-31', 'channel' => 'cash', 'granularity' => 'year'])->json('data');
        $channels = array_unique(array_column($rows, 'channel'));
        foreach ($channels as $ch) {
            $this->assertContains($ch, ['1', '2', '3', '4', 'cur'], "cash channel must be one of office tills");
        }
    }

    public function test_expenses_query_echoes_channel(): void
    {
        $r = $this->mcp('finance/expenses', ['from' => '2024-01-01', 'to' => '2024-12-31', 'channel' => 'bank']);
        $this->assertSame('bank', $r->json('query.channel'));
    }

    // ─── /finance/cash-flow ───────────────────────────────────────────────

    public function test_cash_flow_returns_kassa_breakdown(): void
    {
        $r = $this->mcp('finance/cash-flow', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['period', 'kassa', 'inflow_byn', 'outflow_byn', 'net_byn', 'shift_in_byn', 'shift_out_byn', 'transactions']]]);
    }

    public function test_cash_flow_kassa_values_are_known_set(): void
    {
        $rows = $this->mcp('finance/cash-flow', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'year'])->json('data');
        $kassas = array_unique(array_column($rows, 'kassa'));
        foreach ($kassas as $k) {
            $this->assertContains($k, ['k1', 'k2', 'bank', 'card', 'cur', 'HZ', '']);
        }
    }

    // ─── auth ─────────────────────────────────────────────────────────────

    public function test_finance_endpoints_require_token(): void
    {
        $this->assertRequiresToken('finance/pnl');
        $this->assertRequiresToken('finance/revenue');
        $this->assertRequiresToken('finance/expenses');
        $this->assertRequiresToken('finance/cash-flow');
    }
}
