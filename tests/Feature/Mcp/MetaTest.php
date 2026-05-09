<?php

namespace Tests\Feature\Mcp;

class MetaTest extends McpTestCase
{
    public function test_categories_returns_business_and_detailed(): void
    {
        $r = $this->mcp('meta/categories');
        $this->assertEnvelope($r);
        $r->assertJsonStructure([
            'data' => ['business_categories', 'detailed_categories'],
        ]);
        $r->assertJsonPath('data.business_categories.0.key', 'children');
    }

    public function test_locations_includes_offices(): void
    {
        $r = $this->mcp('meta/locations');
        $this->assertEnvelope($r);
        $r->assertJsonStructure([
            'data' => [['id', 'type', 'name', 'first_deal_date', 'last_deal_date', 'total_deals', 'total_revenue_byn']],
        ]);
    }

    public function test_expense_items_active_only(): void
    {
        $r = $this->mcp('meta/expense-items');
        $this->assertEnvelope($r);
        $r->assertJsonStructure([
            'data' => [['id', 'code', 'name', 'sort_order', 'bank_yn']],
        ]);
    }

    public function test_income_items(): void
    {
        $r = $this->mcp('meta/income-items');
        $this->assertEnvelope($r);
    }

    public function test_data_freshness_returns_per_table(): void
    {
        $r = $this->mcp('meta/data-freshness');
        $this->assertEnvelope($r);
        $r->assertJsonStructure([
            'data' => ['tables' => ['rent_deals_arch', 'doh_rash', 'karn_brons', 'clients', 'rent_orders_arch'], 'max'],
        ]);
    }
}
