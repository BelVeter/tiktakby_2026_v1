<?php

namespace Tests\Feature\Mcp;

class CustomersTest extends McpTestCase
{
    // ─── /customers/timeline ──────────────────────────────────────────────

    public function test_timeline_envelope_and_columns(): void
    {
        $r = $this->mcp('customers/timeline', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['period', 'new_clients', 'active_clients', 'returning_clients', 'new_active_clients']]]);
    }

    public function test_timeline_active_at_least_returning_plus_new_active(): void
    {
        // Some rent_deals_arch.client_id rows reference clients that aren't in
        // the `clients` table (LEFT JOIN gives NULL c.cr_time → falls in
        // neither returning nor new_active), so active can exceed the sum by
        // a small NULL-bucket. We assert ≥ within a small tolerance.
        $rows = $this->mcp('customers/timeline', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter'])->json('data');
        foreach ($rows as $row) {
            $sum = $row['returning_clients'] + $row['new_active_clients'];
            $this->assertGreaterThanOrEqual($sum, $row['active_clients'], "period {$row['period']}: active ≥ returning + new_active");
            $this->assertLessThanOrEqual($sum + 5, $row['active_clients'], "period {$row['period']}: NULL-client bucket should be small");
        }
    }

    public function test_timeline_quarterly_full_year(): void
    {
        $rows = $this->mcp('customers/timeline', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter'])->json('data');
        $this->assertCount(4, $rows);
    }

    public function test_timeline_returning_can_be_lower_than_new_in_2024(): void
    {
        // From real data: returning ~30-40% of active; new_active dominates.
        $q1 = $this->mcp('customers/timeline', ['from' => '2024-01-01', 'to' => '2024-03-31', 'granularity' => 'quarter'])->json('data.0');
        $this->assertGreaterThan(0, $q1['returning_clients']);
        $this->assertGreaterThan(0, $q1['new_active_clients']);
    }

    // ─── /customers/cohorts ───────────────────────────────────────────────

    public function test_cohorts_envelope_and_structure(): void
    {
        $r = $this->mcp('customers/cohorts', ['from' => '2024-01-01', 'to' => '2024-06-30']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['cohort', 'size', 'retention']]]);
    }

    public function test_cohorts_h1_2024_returns_six_cohorts(): void
    {
        $rows = $this->mcp('customers/cohorts', ['from' => '2024-01-01', 'to' => '2024-06-30'])->json('data');
        $this->assertCount(6, $rows);
        $cohorts = array_column($rows, 'cohort');
        $this->assertSame(['2024-01', '2024-02', '2024-03', '2024-04', '2024-05', '2024-06'], $cohorts);
    }

    public function test_cohorts_first_observed_period_high_retention(): void
    {
        // M0 retention = % of cohort that had a deal in the cohort month.
        // For paying customers this should be near 100%.
        $rows = $this->mcp('customers/cohorts', ['from' => '2024-01-01', 'to' => '2024-06-30'])->json('data');
        foreach ($rows as $cohort) {
            if (!empty($cohort['retention'])) {
                $first = $cohort['retention'][0];
                $this->assertSame($cohort['cohort'], $first['period']);
                $this->assertGreaterThan(0.85, $first['rate'], "M0 retention > 85% for {$cohort['cohort']}");
            }
        }
    }

    public function test_cohorts_retention_sorted_chronologically(): void
    {
        $rows = $this->mcp('customers/cohorts', ['from' => '2024-01-01', 'to' => '2024-06-30'])->json('data');
        foreach ($rows as $cohort) {
            $periods = array_column($cohort['retention'], 'period');
            $sorted  = $periods;
            sort($sorted);
            $this->assertSame($sorted, $periods, "retention rows must be sorted by period");
        }
    }

    // ─── /customers/repeat-intervals ──────────────────────────────────────

    public function test_repeat_intervals_returns_summary_stats(): void
    {
        $r = $this->mcp('customers/repeat-intervals', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => ['count', 'mean_days', 'min_days', 'max_days', 'p25_days', 'median_days', 'p75_days', 'histogram']]);
    }

    public function test_repeat_intervals_quartiles_are_ordered(): void
    {
        $d = $this->mcp('customers/repeat-intervals', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        $this->assertLessThanOrEqual($d['median_days'], $d['p25_days'], 'p25 ≤ median');
        $this->assertLessThanOrEqual($d['p75_days'],    $d['median_days'], 'median ≤ p75');
        $this->assertLessThanOrEqual($d['max_days'],    $d['p75_days'], 'p75 ≤ max');
    }

    public function test_repeat_intervals_histogram_buckets_sum_to_count(): void
    {
        $d = $this->mcp('customers/repeat-intervals', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        $sum = array_sum(array_column($d['histogram'], 'count'));
        $this->assertSame($d['count'], $sum);
    }

    public function test_repeat_intervals_histogram_has_six_buckets(): void
    {
        $d = $this->mcp('customers/repeat-intervals', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        $this->assertCount(6, $d['histogram']);
        $labels = array_column($d['histogram'], 'label');
        $this->assertSame(['0-7d', '7-30d', '30-90d', '90-180d', '180-365d', '365+d'], $labels);
    }

    public function test_repeat_intervals_unknown_category_empty(): void
    {
        $d = $this->mcp('customers/repeat-intervals', ['from' => '2024-01-01', 'to' => '2024-12-31', 'category' => 'tools'])->json('data');
        $this->assertSame([], $d);
    }

    // ─── legacy /clients/ltv ──────────────────────────────────────────────

    public function test_clients_ltv_returns_clients(): void
    {
        $r = $this->mcp('clients/ltv', ['limit' => 5]);
        $this->assertEnvelope($r);
        $rows = $r->json('data');
        $this->assertLessThanOrEqual(5, count($rows));
    }

    public function test_clients_ltv_no_pii(): void
    {
        $rows = $this->mcp('clients/ltv', ['limit' => 5])->json('data');
        if (!empty($rows)) {
            $row = $rows[0];
            foreach (['family', 'name', 'otch', 'phone_1', 'phone_2', 'pas_n', 'pas_ln'] as $field) {
                $this->assertArrayNotHasKey($field, $row);
            }
        }
    }

    public function test_clients_ltv_sorted_desc_by_ltv(): void
    {
        $rows = $this->mcp('clients/ltv', ['limit' => 20])->json('data');
        $ltvs = array_map(static fn ($r) => (float) $r['ltv_byn'], $rows);
        $this->assertSortedDesc($ltvs);
    }

    public function test_clients_ltv_validates_limit(): void
    {
        $this->mcp('clients/ltv', ['limit' => 99999])->assertStatus(422);
        $this->mcp('clients/ltv', ['limit' => 0])->assertStatus(422);
    }

    public function test_clients_ltv_min_filters(): void
    {
        $rows = $this->mcp('clients/ltv', ['min_deals' => 5, 'min_ltv' => 100, 'limit' => 10])->json('data');
        foreach ($rows as $row) {
            $this->assertGreaterThanOrEqual(5,   $row['total_deals']);
            $this->assertGreaterThanOrEqual(100, (float) $row['ltv_byn']);
        }
    }

    // ─── auth ─────────────────────────────────────────────────────────────

    public function test_customers_endpoints_require_token(): void
    {
        foreach (['customers/timeline', 'customers/cohorts', 'customers/repeat-intervals', 'clients/ltv'] as $p) {
            $this->assertRequiresToken($p);
        }
    }
}
