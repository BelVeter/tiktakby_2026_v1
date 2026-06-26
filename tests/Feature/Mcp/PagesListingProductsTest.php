<?php

namespace Tests\Feature\Mcp;

class PagesListingProductsTest extends McpTestCase
{
    // ─── /pages/listing/{slug}/products ──────────────────────────────────────

    // Slug 'buster' is a known category-level slug (L3).
    // Slug 'autokresla' is a known subrazdel-level slug (L2).
    // Both exist in the production catalog.

    public function test_envelope_for_category_slug(): void
    {
        $this->assertEnvelope($this->mcp('pages/listing/buster/products'));
    }

    public function test_returns_required_fields(): void
    {
        $rows = $this->mcp('pages/listing/buster/products')->json('data');
        $this->assertNotEmpty($rows, 'buster category must contain products');

        $first = $rows[0];
        foreach (['model_id', 'model_name', 'model_url', 'brand',
                  'active_units', 'free_units', 'is_available',
                  'price_per_week_byn', 'price_per_day_byn'] as $field) {
            $this->assertArrayHasKey($field, $first, "field '{$field}' must be present");
        }
    }

    public function test_field_types(): void
    {
        $rows = $this->mcp('pages/listing/buster/products')->json('data');
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertIsInt($row['model_id']);
            $this->assertIsString($row['model_name']);
            $this->assertIsString($row['model_url']);
            $this->assertIsInt($row['active_units']);
            $this->assertIsInt($row['free_units']);
            $this->assertIsBool($row['is_available']);
            $this->assertTrue(
                $row['brand'] === null || is_string($row['brand']),
                'brand must be string or null'
            );
            $this->assertTrue(
                $row['price_per_week_byn'] === null || is_float($row['price_per_week_byn']),
                'price_per_week_byn must be float or null'
            );
            $this->assertTrue(
                $row['price_per_day_byn'] === null || is_float($row['price_per_day_byn']),
                'price_per_day_byn must be float or null'
            );
        }
    }

    public function test_is_available_matches_free_units(): void
    {
        $rows = $this->mcp('pages/listing/buster/products')->json('data');
        foreach ($rows as $row) {
            $this->assertSame(
                $row['free_units'] > 0,
                $row['is_available'],
                "is_available must equal free_units > 0 for model {$row['model_id']}"
            );
        }
    }

    public function test_free_units_never_exceed_active_units(): void
    {
        $rows = $this->mcp('pages/listing/buster/products')->json('data');
        foreach ($rows as $row) {
            $this->assertLessThanOrEqual(
                $row['active_units'],
                $row['free_units'],
                "free_units must not exceed active_units for model {$row['model_id']}"
            );
        }
    }

    public function test_sorted_by_free_units_desc(): void
    {
        $rows = $this->mcp('pages/listing/buster/products')->json('data');
        $this->assertNotEmpty($rows);
        $this->assertSortedDesc(array_column($rows, 'free_units'),
            'results must be sorted by free_units DESC');
    }

    public function test_query_echo_contains_slug_and_level(): void
    {
        $r = $this->mcp('pages/listing/buster/products');
        $r->assertJsonPath('query.slug', 'buster');
        $r->assertJsonPath('query.level', 'category');
    }

    public function test_subrazdel_slug_returns_envelope(): void
    {
        $r = $this->mcp('pages/listing/autokresla/products');
        $this->assertEnvelope($r);
        $this->assertNotEmpty($r->json('data'));
    }

    public function test_subrazdel_query_level_is_subrazdel(): void
    {
        $r = $this->mcp('pages/listing/autokresla/products');
        $r->assertJsonPath('query.level', 'subrazdel');
    }

    public function test_subrazdel_returns_more_items_than_single_category(): void
    {
        $subrazdelCount = count($this->mcp('pages/listing/autokresla/products')->json('data'));
        $categoryCount  = count($this->mcp('pages/listing/buster/products')->json('data'));
        $this->assertGreaterThan($categoryCount, $subrazdelCount,
            'subrazdel must contain more models than a single category within it');
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->mcp('pages/listing/nonexistent-slug-xyz-abc/products')
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    public function test_requires_bearer_token(): void
    {
        $this->assertRequiresToken('pages/listing/buster/products');
    }

    public function test_razdel_slug_returns_envelope_with_correct_level(): void
    {
        // razdel-level slug: covers the longest JOIN chain in buildCatalogJoin()
        $r = $this->mcp('pages/listing/prokat-detskih-tovarov/products');
        $this->assertEnvelope($r);
        $this->assertNotEmpty($r->json('data'), 'razdel must contain products');
        $r->assertJsonPath('query.level', 'razdel');
    }
}
