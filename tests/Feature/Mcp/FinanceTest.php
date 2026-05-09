<?php

namespace Tests\Feature\Mcp;

class FinanceTest extends McpTestCase
{
    public function test_pnl_2019_baseline(): void
    {
        $r = $this->mcp('finance/pnl', ['from' => '2019-01-01', 'to' => '2019-12-31', 'granularity' => 'year']);
        $this->assertEnvelope($r);

        $rows = $r->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('2019', $rows[0]['period']);
        // annual_pnl_summary.csv: revenue 433,656 ; ebitda 34,909
        $this->assertEqualsWithDelta(433656, $rows[0]['revenue_byn'], 1.0);
        $this->assertEqualsWithDelta(34909,  $rows[0]['ebitda_byn'],  1.0);
    }

    public function test_pnl_2025_warning_present(): void
    {
        $r = $this->mcp('finance/pnl', ['from' => '2025-01-01', 'to' => '2025-12-31', 'granularity' => 'year']);
        $this->assertEnvelope($r);
        $warnings = $r->json('meta.warnings');
        $this->assertNotEmpty($warnings);
        $this->assertSame('fy2025_bank_channel_gap', $warnings[0]['code']);
        $this->assertSame('D-OPEN-FY2025', $warnings[0]['ref']);
    }

    public function test_pnl_2024_no_warning(): void
    {
        $r = $this->mcp('finance/pnl', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'year']);
        $this->assertEnvelope($r);
        $this->assertEmpty($r->json('meta.warnings'));
    }

    public function test_pnl_invalid_date_returns_422(): void
    {
        $r = $this->mcp('finance/pnl', ['from' => 'not-a-date']);
        $r->assertStatus(422);
    }

    public function test_revenue_with_category_filter(): void
    {
        $r = $this->mcp('finance/revenue', ['from' => '2024-01-01', 'to' => '2024-12-31', 'category' => 'children']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['period', 'rent_byn', 'delivery_byn', 'total_byn', 'deals', 'unique_clients']]]);
    }

    public function test_expenses_bank_channel(): void
    {
        $r = $this->mcp('finance/expenses', ['from' => '2024-01-01', 'to' => '2024-12-31', 'channel' => 'bank']);
        $this->assertEnvelope($r);
    }

    public function test_cash_flow_returns_kassa_breakdown(): void
    {
        $r = $this->mcp('finance/cash-flow', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['period', 'kassa', 'inflow_byn', 'outflow_byn', 'net_byn']]]);
    }
}
