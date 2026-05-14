<?php

namespace Tests\Feature\Mcp;

use Illuminate\Support\Facades\DB;

/**
 * Legacy parity: every revenue/deal aggregate served by the MCP API must
 * match the same calculation done with direct SQL against the legacy schema
 * the way /bb/reports.php, /bb/sales_breakdown.php, /bb/dohrash2.php and
 * /bb/cat_analysis.php compute it.
 *
 * These tests are designed to catch any future regression that re-introduces
 * the "only rent_deals_arch" / "cr_time instead of acc_date" / M:N inflation
 * bugs documented in the 2026-05-14 audit.
 */
class LegacyParityTest extends McpTestCase
{
    /**
     * Reference: /bb/sales_breakdown.php → Deal::getSalesRentDeliv()
     * Total revenue = SUM(r_paid + delivery_paid) over UNION(sub_act, sub_arch)
     * by acc_date.
     */
    public function test_finance_revenue_matches_legacy_total(): void
    {
        $from = strtotime('2019-01-01 00:00:00');
        $to   = strtotime('2019-12-31 23:59:59');

        $legacy = DB::selectOne("
            SELECT ROUND(SUM(r_paid + delivery_paid), 2) AS total
            FROM (
                SELECT r_paid, delivery_paid, acc_date FROM rent_sub_deals_act
                UNION ALL
                SELECT r_paid, delivery_paid, acc_date FROM rent_sub_deals_arch
            ) sd
            WHERE sd.acc_date BETWEEN ? AND ?
        ", [$from, $to])->total;

        $apiTotal = collect($this->mcp('finance/revenue', [
            'from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'year',
        ])->json('data'))->sum('total_byn');

        $this->assertEqualsWithDelta($legacy, $apiTotal, 0.05);
    }

    /**
     * Reference: /bb/sales_breakdown.php → Deal::getSalesRentDeliv($place=1, $delivYN=0)
     * Per-office revenue keys on sub_deal.place + delivery_yn != '1'.
     */
    public function test_finance_revenue_office_3_matches_legacy(): void
    {
        $from = strtotime('2019-01-01 00:00:00');
        $to   = strtotime('2019-12-31 23:59:59');

        $legacy = (float) DB::selectOne("
            SELECT ROUND(SUM(r_paid + delivery_paid), 2) AS total
            FROM (
                SELECT r_paid, delivery_paid, acc_date, place, delivery_yn FROM rent_sub_deals_act
                UNION ALL
                SELECT r_paid, delivery_paid, acc_date, place, delivery_yn FROM rent_sub_deals_arch
            ) sd
            WHERE sd.acc_date BETWEEN ? AND ?
              AND sd.place = 3 AND sd.delivery_yn != '1'
        ", [$from, $to])->total;

        $apiTotal = collect($this->mcp('finance/revenue', [
            'from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'year',
            'location' => '3',
        ])->json('data'))->sum('total_byn');

        $this->assertEqualsWithDelta($legacy, $apiTotal, 0.05);
    }

    /**
     * Reference: /bb/sales_breakdown.php → courier line uses delivery_yn = '1'.
     */
    public function test_finance_revenue_courier_matches_legacy(): void
    {
        $from = strtotime('2019-01-01 00:00:00');
        $to   = strtotime('2019-12-31 23:59:59');

        $legacy = (float) DB::selectOne("
            SELECT ROUND(SUM(r_paid + delivery_paid), 2) AS total
            FROM (
                SELECT r_paid, delivery_paid, acc_date, delivery_yn FROM rent_sub_deals_act
                UNION ALL
                SELECT r_paid, delivery_paid, acc_date, delivery_yn FROM rent_sub_deals_arch
            ) sd
            WHERE sd.acc_date BETWEEN ? AND ?
              AND sd.delivery_yn = '1'
        ", [$from, $to])->total;

        $apiTotal = collect($this->mcp('finance/revenue', [
            'from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'year',
            'location' => 'courier',
        ])->json('data'))->sum('total_byn');

        $this->assertEqualsWithDelta($legacy, $apiTotal, 0.05);
    }

    /**
     * Reference: /bb/dohrash2.php → DohRashesAnalisys carnival/non-carnival split.
     * Non-carnival revenue = revenue from sub_deals whose parent deal's item
     * does NOT belong to a tovar_rent_cat with cat_type=1.
     */
    public function test_pnl_carnival_split_matches_legacy(): void
    {
        $from = strtotime('2019-01-01 00:00:00');
        $to   = strtotime('2019-12-31 23:59:59');

        $legacyTotal = (float) DB::selectOne("
            SELECT ROUND(SUM(r_paid + delivery_paid), 2) AS t
            FROM (
                SELECT r_paid, delivery_paid, acc_date FROM rent_sub_deals_act
                UNION ALL
                SELECT r_paid, delivery_paid, acc_date FROM rent_sub_deals_arch
            ) sd
            WHERE sd.acc_date BETWEEN ? AND ?
        ", [$from, $to])->t;

        $legacyCarnival = (float) DB::selectOne("
            SELECT ROUND(SUM(sd.r_paid + sd.delivery_paid), 2) AS t
            FROM (
                SELECT deal_id, r_paid, delivery_paid, acc_date FROM rent_sub_deals_act
                UNION ALL
                SELECT deal_id, r_paid, delivery_paid, acc_date FROM rent_sub_deals_arch
            ) sd
            JOIN (
                SELECT deal_id, item_inv_n FROM rent_deals_act
                UNION ALL
                SELECT deal_id, item_inv_n FROM rent_deals_arch
            ) da ON da.deal_id = sd.deal_id
            JOIN (
                SELECT item_inv_n, cat_id FROM tovar_rent_items
                UNION ALL
                SELECT item_inv_n, cat_id FROM tovar_rent_items_arch
            ) ti ON ti.item_inv_n = da.item_inv_n
            JOIN tovar_rent_cat trc ON trc.tovar_rent_cat_id = ti.cat_id
            WHERE sd.acc_date BETWEEN ? AND ? AND trc.cat_type = 1
        ", [$from, $to])->t;

        $pnl = $this->mcp('finance/pnl', [
            'from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'year',
        ])->json('data');

        $this->assertEqualsWithDelta($legacyTotal,    $pnl[0]['revenue_byn'],           1.0);
        $this->assertEqualsWithDelta($legacyCarnival, $pnl[0]['revenue_carnival_byn'],  1.0);
        $this->assertEqualsWithDelta($legacyTotal - $legacyCarnival, $pnl[0]['revenue_non_carnival_byn'], 1.0);
    }

    /**
     * Reference: /bb/reports.php → deal counts use type IN ('first_rent','takeaway_plan').
     */
    public function test_operations_issuance_events_match_legacy(): void
    {
        $from = strtotime('2019-01-01 00:00:00');
        $to   = strtotime('2019-12-31 23:59:59');

        $legacy = (int) DB::selectOne("
            SELECT COUNT(*) AS c
            FROM (
                SELECT `type`, acc_date FROM rent_sub_deals_act
                UNION ALL
                SELECT `type`, acc_date FROM rent_sub_deals_arch
            ) sd
            WHERE sd.acc_date BETWEEN ? AND ?
              AND sd.`type` IN ('first_rent','takeaway_plan')
        ", [$from, $to])->c;

        $api = $this->mcp('operations/funnel', [
            'from' => '2019-01-01', 'to' => '2019-12-31',
        ])->json('data.issuance_events');

        $this->assertSame($legacy, $api);
    }

    /**
     * Reference: act + arch deal counts (UNION) by cr_time.
     */
    public function test_operations_deals_count_matches_union_legacy(): void
    {
        $from = strtotime('2024-01-01 00:00:00');
        $to   = strtotime('2024-12-31 23:59:59');

        $legacy = (int) DB::selectOne("
            SELECT COUNT(DISTINCT deal_id) AS c
            FROM (
                SELECT deal_id, cr_time FROM rent_deals_act
                UNION ALL
                SELECT deal_id, cr_time FROM rent_deals_arch
            ) da
            WHERE da.cr_time BETWEEN ? AND ?
        ", [$from, $to])->c;

        $api = $this->mcp('operations/funnel', [
            'from' => '2024-01-01', 'to' => '2024-12-31',
        ])->json('data.deals');

        $this->assertSame($legacy, $api);
    }

    /**
     * Reference: /bb/cat_analysis.php → tovar::getTovNumberForCatsForDate
     * Inventory at date = active items (buy_date<=ts)
     *                   + archived items (buy_date<=ts AND arch_time>=ts).
     *
     * We verify the API returns NON-zero historical units and that the
     * value matches a direct legacy-style SQL count.
     */
    public function test_inventory_utilization_uses_historical_units(): void
    {
        $ts = strtotime('2019-06-01 00:00:00');
        $rows = $this->mcp('inventory/utilization', [
            'from' => '2019-01-01', 'to' => '2019-12-31',
        ])->json('data');

        $this->assertNotEmpty($rows);
        foreach ($rows as $r) {
            $this->assertArrayHasKey('units_at_from', $r);
            $this->assertArrayHasKey('units_at_to',   $r);
            $this->assertArrayHasKey('avg_units',     $r);
            $this->assertGreaterThan(0, $r['avg_units']);
        }

        // Match legacy formula against direct SQL.
        $legacyHistorical = (int) DB::selectOne("
            SELECT (
                (SELECT COUNT(*) FROM tovar_rent_items WHERE buy_date <= ?)
              + (SELECT COUNT(*) FROM tovar_rent_items_arch WHERE buy_date <= ? AND arch_time >= ?)
            ) AS c
        ", [$ts, $ts, $ts])->c;

        // API doesn't return per-period inventory directly, but units_at_from
        // (computed inside utilization) should sum to roughly the legacy count
        // — within ±15% because the API filters out NULL model_id rows.
        $apiSumFrom = (int) round(collect($rows)->sum('units_at_from'));
        $this->assertEqualsWithDelta($legacyHistorical, $apiSumFrom, $legacyHistorical * 0.15);
    }

    /**
     * Spot check: bands of expenses tie out to direct doh_rash sums.
     */
    public function test_expenses_total_matches_direct_doh_rash(): void
    {
        $from = strtotime('2024-01-01 00:00:00');
        $to   = strtotime('2024-12-31 23:59:59');

        $legacy = (float) DB::selectOne("
            SELECT ROUND(SUM(-amount), 2) AS t
            FROM doh_rash
            WHERE type1 = 'rash' AND acc_date BETWEEN ? AND ?
        ", [$from, $to])->t;

        $api = (float) $this->mcp('finance/pnl', [
            'from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'year',
        ])->json('data.0.expenses_total_byn');

        $this->assertEqualsWithDelta($legacy, $api, 0.5);
    }
}
