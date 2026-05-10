<?php

namespace Tests\Feature\Mcp;

class CarnivalTest extends McpTestCase
{
    public function test_funnel_envelope_and_stages(): void
    {
        $r = $this->mcp('carnival/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure([
            'data' => ['bookings', 'approved', 'issued', 'returned', 'unanswered', 'conversion_rates'],
        ]);
    }

    public function test_funnel_stage_counts_are_monotonic(): void
    {
        $d = $this->mcp('carnival/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        // bookings ≥ approved ≥ issued ≥ returned (some bookings get cancelled/no-show)
        $this->assertGreaterThanOrEqual($d['approved'], $d['bookings']);
        $this->assertGreaterThanOrEqual($d['issued'],   $d['approved']);
        $this->assertGreaterThanOrEqual($d['returned'], $d['issued']);
    }

    public function test_funnel_conversion_rates_are_fractions(): void
    {
        $cr = $this->mcp('carnival/funnel', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data.conversion_rates');
        foreach (['booking_to_approved', 'approved_to_issued', 'booking_to_issued'] as $k) {
            $this->assertNotNull($cr[$k]);
            $this->assertGreaterThanOrEqual(0, $cr[$k]);
            $this->assertLessThanOrEqual(1, $cr[$k], "$k must be a fraction");
        }
    }

    public function test_funnel_empty_period_returns_zero_stages(): void
    {
        $d = $this->mcp('carnival/funnel', ['from' => '2037-01-01', 'to' => '2037-01-31'])->json('data');
        $this->assertSame(0, $d['bookings']);
        $this->assertSame(0, $d['approved']);
        $this->assertNull($d['conversion_rates']['booking_to_approved']);
    }

    public function test_seasonality_december_is_absolute_peak(): void
    {
        $rows = $this->mcp('carnival/seasonality', ['years' => 5])->json('data');
        $this->assertCount(12, $rows);
        $peak = collect($rows)->sortByDesc('seasonality_index')->first();
        $this->assertSame(12, $peak['month_num']);
        $this->assertGreaterThan(3.0, $peak['seasonality_index'], 'carnival December peak > 3x average');
    }

    public function test_seasonality_returns_all_12_months_with_year_counts(): void
    {
        $rows = $this->mcp('carnival/seasonality', ['years' => 5])->json('data');
        $this->assertSame(range(1, 12), array_column($rows, 'month_num'));
        foreach ($rows as $row) {
            $this->assertGreaterThan(0, $row['years_covered']);
            $this->assertLessThanOrEqual(5, $row['years_covered']);
        }
    }

    public function test_seasonality_validates_years(): void
    {
        $this->mcp('carnival/seasonality', ['years' => 0])->assertStatus(422);
        $this->mcp('carnival/seasonality', ['years' => 99])->assertStatus(422);
    }

    public function test_revenue_envelope_and_columns(): void
    {
        $r = $this->mcp('carnival/revenue', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['period', 'bookings', 'revenue_k1_byn', 'revenue_k2_byn', 'revenue_terminal_byn', 'revenue_bank_byn', 'revenue_total_byn']]]);
    }

    public function test_revenue_quarterly_has_4_rows_for_full_year(): void
    {
        $rows = $this->mcp('carnival/revenue', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter'])->json('data');
        $periods = array_column($rows, 'period');
        $this->assertContains('2024-Q1', $periods);
        $this->assertContains('2024-Q4', $periods);
    }

    public function test_carnival_endpoints_require_token(): void
    {
        $this->assertRequiresToken('carnival/funnel');
        $this->assertRequiresToken('carnival/seasonality');
        $this->assertRequiresToken('carnival/revenue');
    }
}
