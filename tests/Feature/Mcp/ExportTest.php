<?php

namespace Tests\Feature\Mcp;

class ExportTest extends McpTestCase
{
    public function test_pnl_csv_header(): void
    {
        $r = $this->get('/api/mcp/v1/export/monthly/pnl?from=2024-01-01&to=2024-01-31', [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
            'Accept'        => 'text/csv',
        ]);
        $r->assertStatus(200);
        $body = $r->streamedContent();
        $firstLine = strtok($body, "\n");
        $this->assertStringContainsString('period,revenue_total', $firstLine);
        $this->assertStringContainsString('ebitda', $firstLine);
    }

    public function test_revenue_csv(): void
    {
        $r = $this->get('/api/mcp/v1/export/monthly/revenue?from=2024-01-01&to=2024-01-31', [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
        $r->assertStatus(200);
    }

    public function test_operations_csv(): void
    {
        $r = $this->get('/api/mcp/v1/export/monthly/operations?from=2024-01-01&to=2024-01-31', [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
        $r->assertStatus(200);
    }

    public function test_traffic_stub(): void
    {
        $r = $this->get('/api/mcp/v1/export/monthly/traffic', [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
        $r->assertStatus(200);
    }

    public function test_unknown_topic_404(): void
    {
        $r = $this->get('/api/mcp/v1/export/monthly/foo', [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
        $r->assertStatus(404);
    }
}
