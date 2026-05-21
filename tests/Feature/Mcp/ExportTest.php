<?php

namespace Tests\Feature\Mcp;

class ExportTest extends McpTestCase
{
    private function fetchCsv(string $topic, array $query = []): string
    {
        $url = '/api/mcp/v1/export/monthly/' . $topic;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        $r = $this->get($url, ['Authorization' => 'Bearer ' . config('mcp.api_token')]);
        $r->assertStatus(200);
        return $r->streamedContent();
    }

    public function test_pnl_csv_header_matches_schema(): void
    {
        $csv = $this->fetchCsv('pnl', ['from' => '2024-01-01', 'to' => '2024-01-31']);
        $header = strtok($csv, "\n");
        // Must match column layout in 04_analytics/data/monthly/_schema.md / pnl.csv.
        $expected = 'period,revenue_total,cogs_park_amortization,cogs_cleaning,cogs_repairs,cogs_courier_fuel,cogs_courier_salary,direct_costs,gross_margin,opex_rent,opex_payroll,opex_marketing,opex_telephony,opex_admin,ebitda,capex_new_park,net_cash_flow';
        $this->assertSame($expected, trim($header));
    }

    public function test_pnl_csv_includes_period_rows(): void
    {
        $csv = $this->fetchCsv('pnl', ['from' => '2019-01-01', 'to' => '2019-12-31']);
        // Rough sanity: at least 12 month rows for full year + header.
        $lines = array_filter(explode("\n", $csv), 'strlen');
        $this->assertGreaterThanOrEqual(13, count($lines));
        // Spot-check a 2019 month is in the output.
        $this->assertStringContainsString('2019-01,', $csv);
    }

    public function test_revenue_csv_header_matches_schema(): void
    {
        $csv = $this->fetchCsv('revenue', ['from' => '2024-01-01', 'to' => '2024-01-31']);
        $header = strtok($csv, "\n");
        $expected = 'period,category,segment,revenue_gross,revenue_discounts,revenue_net,refunds,penalties,revenue_final,issuances_count,avg_check';
        $this->assertSame($expected, trim($header));
    }

    public function test_revenue_csv_revenue_final_matches_pnl_revenue(): void
    {
        $csv = $this->fetchCsv('revenue', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $lines = array_filter(explode("\n", $csv), 'strlen');
        array_shift($lines);
        $sum = 0;
        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            $sum += (float) $cols[8]; // revenue_final
        }
        $pnlRevenue = $this->mcp('finance/pnl', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'year'])->json('data.0.revenue_byn');
        $this->assertEqualsWithDelta($pnlRevenue, $sum, 0.10);
    }

    public function test_operations_csv_header_matches_schema(): void
    {
        $csv = $this->fetchCsv('operations', ['from' => '2024-01-01', 'to' => '2024-01-31']);
        $header = strtok($csv, "\n");
        $expected = 'period,category,applications,applications_online,applications_phone,applications_messenger,bookings,issuances,prolongations,returns,cancelled,no_show,damages,losses,cr_application_to_booking,cr_booking_to_issuance,avg_rental_days';
        $this->assertSame($expected, trim($header));
    }

    public function test_traffic_csv_is_header_only_stub(): void
    {
        $csv = $this->fetchCsv('traffic');
        $lines = array_filter(explode("\n", $csv), 'strlen');
        $this->assertCount(1, $lines, 'traffic stub returns header only');
        $this->assertStringStartsWith('period,segment,region,category,source,visits', $csv);
    }

    public function test_unknown_topic_returns_404(): void
    {
        $r = $this->get('/api/mcp/v1/export/monthly/foo', [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
        $r->assertStatus(404);
    }

    public function test_route_constraint_rejects_uppercase_topic(): void
    {
        // route is constrained to [a-z_-]+ → uppercase fails route match (404).
        $r = $this->get('/api/mcp/v1/export/monthly/PNL', [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
        $r->assertStatus(404);
    }

    public function test_export_validates_invalid_dates(): void
    {
        $r = $this->get('/api/mcp/v1/export/monthly/pnl?from=not-a-date', [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
        $r->assertStatus(422);
    }

    public function test_export_returns_csv_content_type(): void
    {
        $r = $this->get('/api/mcp/v1/export/monthly/pnl?from=2024-01-01&to=2024-01-31', [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
        $r->assertStatus(200);
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
    }

    public function test_export_period_filter_excludes_outside_data(): void
    {
        // Distant future window — synthetic empty period beyond real data.
        $csv = $this->fetchCsv('revenue', ['from' => '2037-01-01', 'to' => '2037-01-31']);
        $lines = array_filter(explode("\n", $csv), 'strlen');
        // Header only — no data rows in this period.
        $this->assertCount(1, $lines);
    }

    public function test_export_endpoints_require_token(): void
    {
        $this->get('/api/mcp/v1/export/monthly/pnl')->assertStatus(401);
        $this->get('/api/mcp/v1/export/monthly/revenue')->assertStatus(401);
    }
}
