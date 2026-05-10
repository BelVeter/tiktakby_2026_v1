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
    }

    public function test_categories_business_enum_size_and_keys(): void
    {
        $r = $this->mcp('meta/categories');
        $rows = $r->json('data.business_categories');
        $this->assertCount(6, $rows, 'six business category keys');

        $keys = array_column($rows, 'key');
        sort($keys);
        $this->assertSame(['children', 'cleaning', 'costumes', 'medical', 'sports', 'tools'], $keys);

        // Every entry has all five fields.
        foreach ($rows as $row) {
            foreach (['key', 'name', 'url_slug', 'razdel_id', 'available'] as $field) {
                $this->assertArrayHasKey($field, $row);
            }
        }
    }

    public function test_categories_tools_marked_unavailable(): void
    {
        $rows = $this->mcp('meta/categories')->json('data.business_categories');
        $tools = collect($rows)->firstWhere('key', 'tools');
        $this->assertNotNull($tools);
        $this->assertFalse($tools['available']);
        $this->assertNull($tools['razdel_id']);
    }

    public function test_categories_children_resolves_to_razdel(): void
    {
        $rows = $this->mcp('meta/categories')->json('data.business_categories');
        $children = collect($rows)->firstWhere('key', 'children');
        $this->assertTrue($children['available']);
        $this->assertSame('prokat-detskih-tovarov', $children['url_slug']);
        $this->assertIsInt($children['razdel_id']);
    }

    public function test_locations_includes_offices_and_couriers(): void
    {
        $r = $this->mcp('meta/locations');
        $this->assertEnvelope($r);
        $rows = $r->json('data');
        $this->assertGreaterThanOrEqual(4, count($rows));

        $types = array_unique(array_column($rows, 'type'));
        $this->assertContains('office',  $types);
        $this->assertContains('courier', $types);
    }

    public function test_locations_pobediteley_closure_history(): void
    {
        $rows = $this->mcp('meta/locations')->json('data');
        $byId = collect($rows)->keyBy('id');
        $this->assertSame('2022-07-06', $byId[3]['last_deal_date'], 'Pobediteley closed on 2022-07-06');
        $this->assertSame(0, (int) $byId[3]['active']);
        $this->assertGreaterThan(20000, $byId[3]['total_deals']);
    }

    public function test_expense_items_returns_active_only(): void
    {
        $r = $this->mcp('meta/expense-items');
        $this->assertEnvelope($r);
        $rows = $r->json('data');
        $this->assertNotEmpty($rows);
        $r->assertJsonStructure(['data' => [['id', 'code', 'name', 'sort_order', 'bank_yn']]]);

        $codes = array_column($rows, 'code');
        // Known codes from rash_items dictionary
        $this->assertContains('zpl', $codes);
        $this->assertContains('adv', $codes);
        $this->assertContains('fszn_tax', $codes);
    }

    public function test_expense_items_sorted_by_sort_order(): void
    {
        $rows = $this->mcp('meta/expense-items')->json('data');
        $orders = array_map(static fn ($r) => $r['sort_order'], $rows);
        $sorted = $orders;
        sort($sorted);
        $this->assertSame($sorted, $orders);
    }

    public function test_income_items_returns_active_only(): void
    {
        $rows = $this->mcp('meta/income-items')->json('data');
        $this->assertNotEmpty($rows);
        $codes = array_column($rows, 'code');
        $this->assertContains('prod_tovar', $codes);
        $this->assertContains('av_return', $codes);
    }

    public function test_data_freshness_returns_per_table_and_max(): void
    {
        $r = $this->mcp('meta/data-freshness');
        $this->assertEnvelope($r);
        $r->assertJsonStructure([
            'data' => [
                'tables' => ['rent_deals_arch', 'doh_rash', 'karn_brons', 'clients', 'rent_orders_arch'],
                'max',
            ],
        ]);

        $tables = $r->json('data.tables');
        $max    = $r->json('data.max');
        // Every per-table value must be ≤ global max.
        foreach ($tables as $name => $iso) {
            if ($iso !== null) {
                $this->assertLessThanOrEqual($max, $iso, "$name newer than global max");
            }
        }
    }

    public function test_meta_endpoints_require_token(): void
    {
        foreach (['meta/categories', 'meta/locations', 'meta/expense-items', 'meta/income-items', 'meta/data-freshness'] as $p) {
            $this->assertRequiresToken($p);
        }
    }
}
