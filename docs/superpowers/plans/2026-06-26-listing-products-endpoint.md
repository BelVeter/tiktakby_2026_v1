# Listing Products Endpoint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `GET /api/mcp/v1/pages/listing/{slug}/products` — a read-only endpoint returning all rental models for a given listing page slug (category/subrazdel/razdel level), for use by the content generation pipeline.

**Architecture:** New method `products()` + private helper `buildCatalogJoin()` added to the existing `PagesListingController`. Reuses `resolveListingSlug()` already in that controller. Pre-aggregated subqueries for inventory counts and prices prevent M:N inflation from multi-category models.

**Tech Stack:** Laravel 8, PHP 7.4, MariaDB 10.6, PHPUnit (feature tests hit real DB).

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `tests/Feature/Mcp/PagesListingProductsTest.php` | Feature tests for the new endpoint |
| Modify | `app/Http/Controllers/Mcp/PagesListingController.php` | Add `products()` + `buildCatalogJoin()` |
| Modify | `routes/api.php` | Register route (no closures — required for route:cache) |
| Modify | `docs/mcp_server.md` | Document new endpoint in the table |
| Modify | `resources/openapi/mcp-v1.json` | Add OpenAPI path entry |

---

### Task 1: Write the failing test

**Files:**
- Create: `tests/Feature/Mcp/PagesListingProductsTest.php`

- [ ] **Step 1.1: Create the test file**

```php
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
                  'total_units', 'free_units', 'is_available',
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
            $this->assertIsInt($row['total_units']);
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

    public function test_free_units_never_exceed_total_units(): void
    {
        $rows = $this->mcp('pages/listing/buster/products')->json('data');
        foreach ($rows as $row) {
            $this->assertLessThanOrEqual(
                $row['total_units'],
                $row['free_units'],
                "free_units must not exceed total_units for model {$row['model_id']}"
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
}
```

- [ ] **Step 1.2: Run the test to confirm it fails (method not found)**

```bash
cd /home/dmitry/sites/tiktakby
docker-compose exec app php artisan test --filter=PagesListingProductsTest
```

Expected: multiple errors like `Call to undefined method` or 404 responses (route not registered yet).

---

### Task 2: Implement the controller method

**Files:**
- Modify: `app/Http/Controllers/Mcp/PagesListingController.php`

Add two methods at the end of the class, **before** the closing `}`.

- [ ] **Step 2.1: Add `products()` and `buildCatalogJoin()` to PagesListingController**

Insert this block immediately before the final `}` of the class (after `resolveListingSlug()`):

```php
    /**
     * GET /api/mcp/v1/pages/listing/{slug}/products
     *
     * Returns all rental models shown on the listing page with this slug.
     * Supports all catalog levels (razdel, subrazdel, category).
     * Inventory counts use the same status logic as /inventory/free-tree.
     * Pre-aggregated subqueries prevent M:N inflation for multi-category models.
     */
    public function products(Request $request, string $slug): JsonResponse
    {
        $resolved = $this->resolveListingSlug($slug);

        if (!$resolved) {
            return response()->json([
                'error'   => 'not_found',
                'message' => "Listing page with slug '{$slug}' not found in catalog.",
            ], 404);
        }

        $levelCode = $resolved['level_code'];
        $key = $this->cacheKey('pages.listing.products', ['slug' => $slug]);

        $data = $this->cacheRemember($key, self::TTL_DEFAULT, function () use ($slug, $levelCode) {
            [$join, $params] = $this->buildCatalogJoin($levelCode, $slug);

            $rows = DB::select("
                SELECT DISTINCT
                    rmw.model_id,
                    rmw.l2_name                              AS model_name,
                    rmw.page_addr                            AS model_url,
                    NULLIF(tr.producer, '')                  AS brand,
                    COALESCE(total_sub.total_units, 0)       AS total_units,
                    COALESCE(free_sub.free_units, 0)         AS free_units,
                    prices.price_per_week_byn,
                    prices.price_per_day_byn
                FROM rent_model_web rmw
                {$join}
                LEFT JOIN tovar_rent tr ON tr.tovar_rent_id = rmw.model_id
                LEFT JOIN (
                    SELECT model_id, COUNT(*) AS total_units
                    FROM tovar_rent_items
                    GROUP BY model_id
                ) total_sub ON total_sub.model_id = rmw.model_id
                LEFT JOIN (
                    SELECT model_id, COUNT(*) AS free_units
                    FROM tovar_rent_items
                    WHERE active_deal_id = 0
                      AND (status IS NULL OR status NOT IN ('в аренде','бронь','ремонт','списан'))
                    GROUP BY model_id
                ) free_sub ON free_sub.model_id = rmw.model_id
                LEFT JOIN (
                    SELECT model_id,
                        MIN(CASE WHEN step = 'week' AND kol_vo = 1 THEN rent_amount END) AS price_per_week_byn,
                        MIN(CASE WHEN step = 'day'  AND kol_vo = 1 THEN rent_amount END) AS price_per_day_byn
                    FROM rent_tarif_act
                    GROUP BY model_id
                ) prices ON prices.model_id = rmw.model_id
                WHERE rmw.lang = 'ru' AND rmw.status = 'show'
                ORDER BY free_units DESC, price_per_week_byn DESC
            ", $params);

            return array_map(fn ($r) => [
                'model_id'           => (int) $r->model_id,
                'model_name'         => $r->model_name,
                'model_url'          => $r->model_url,
                'brand'              => $r->brand,
                'total_units'        => (int) $r->total_units,
                'free_units'         => (int) $r->free_units,
                'is_available'       => (int) $r->free_units > 0,
                'price_per_week_byn' => $r->price_per_week_byn !== null ? (float) $r->price_per_week_byn : null,
                'price_per_day_byn'  => $r->price_per_day_byn !== null ? (float) $r->price_per_day_byn : null,
            ], $rows);
        });

        return $this->envelope(
            array_merge($request->query(), ['slug' => $slug, 'level' => $levelCode]),
            $data
        );
    }

    /**
     * Builds the catalog JOIN fragment and its bound parameters for the given
     * catalog level and slug. Used by products() to filter models to a specific
     * listing page without duplicating the join logic three times.
     *
     * @return array{0: string, 1: array}  [sql_fragment, bound_params]
     */
    private function buildCatalogJoin(string $levelCode, string $slug): array
    {
        if ($levelCode === 'category') {
            return [
                'JOIN tovar_list tl        ON tl.tovar_id = rmw.model_id
                 JOIN tovar_rent_cat tc    ON tc.tovar_rent_cat_id = tl.tovar_cat
                                         AND tc.cat_url_key = ?',
                [$slug],
            ];
        }

        if ($levelCode === 'subrazdel') {
            return [
                'JOIN tovar_list tl        ON tl.tovar_id = rmw.model_id
                 JOIN tovar_rent_cat tc    ON tc.tovar_rent_cat_id = tl.tovar_cat
                 JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.tovar_rent_cat_id
                 JOIN sub_razdel sr        ON sr.id_sub_razdel = sc.id_sub_razdel
                                         AND sr.url_sub_razdel_name = ?',
                [$slug],
            ];
        }

        // razdel
        return [
            'JOIN tovar_list tl        ON tl.tovar_id = rmw.model_id
             JOIN tovar_rent_cat tc    ON tc.tovar_rent_cat_id = tl.tovar_cat
             JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.tovar_rent_cat_id
             JOIN sub_razdel sr        ON sr.id_sub_razdel = sc.id_sub_razdel
             JOIN razdel_subrazdel rs  ON rs.id_sub_razdel = sr.id_sub_razdel
             JOIN razdel r             ON r.id_razdel = rs.id_razdel
                                     AND r.url_razdel_name = ?',
            [$slug],
        ];
    }
```

---

### Task 3: Register the route

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 3.1: Add the route after the existing `/pages/listing/{slug}/image` route**

Find this block in `routes/api.php` (around line 125):
```php
        Route::get('pages/listing',          [PagesListingController::class, 'index'])->name('pages.listing.index');
        Route::get('pages/listing/{slug}',   [PagesListingController::class, 'show'])->name('pages.listing.show');
        Route::post('pages/listing/{slug}/image', [PagesListingController::class, 'uploadImage'])->name('pages.listing.uploadImage');
        Route::patch('pages/listing/{slug}', [PagesListingController::class, 'update'])->name('pages.listing.update');
```

Add one line after the `uploadImage` route:
```php
        Route::get('pages/listing/{slug}/products', [PagesListingController::class, 'products'])->name('pages.listing.products');
```

Result after edit:
```php
        Route::get('pages/listing',          [PagesListingController::class, 'index'])->name('pages.listing.index');
        Route::get('pages/listing/{slug}',   [PagesListingController::class, 'show'])->name('pages.listing.show');
        Route::post('pages/listing/{slug}/image', [PagesListingController::class, 'uploadImage'])->name('pages.listing.uploadImage');
        Route::get('pages/listing/{slug}/products', [PagesListingController::class, 'products'])->name('pages.listing.products');
        Route::patch('pages/listing/{slug}', [PagesListingController::class, 'update'])->name('pages.listing.update');
```

**Important:** The `GET .../products` route MUST be registered before `PATCH .../listing/{slug}` to avoid routing conflicts. The current position (between image and patch) is correct.

---

### Task 4: Run the tests — verify they pass

- [ ] **Step 4.1: Run the test suite**

```bash
cd /home/dmitry/sites/tiktakby
docker-compose exec app php artisan test --filter=PagesListingProductsTest
```

Expected output: All tests PASS. If any fail, check:
- SQL syntax errors → review the query in `products()` (check `tovar_list` join: `tl.tovar_id = rmw.model_id` and `tl.tovar_cat = tc.tovar_rent_cat_id`)
- `test_subrazdel_returns_more_items_than_single_category` fails → verify 'autokresla' contains 'buster' as a sub-category (run `SELECT * FROM sub_razdel WHERE url_sub_razdel_name='autokresla'` then `SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id IN (SELECT tovar_rent_cat_id FROM subrazdel_category WHERE id_sub_razdel=<id>)`)

- [ ] **Step 4.2: Run the full MCP test suite to catch regressions**

```bash
docker-compose exec app php artisan test tests/Feature/Mcp/
```

Expected: All existing tests still pass.

- [ ] **Step 4.3: Commit**

```bash
git add tests/Feature/Mcp/PagesListingProductsTest.php \
        app/Http/Controllers/Mcp/PagesListingController.php \
        routes/api.php
git commit -m "feat(mcp): add GET /pages/listing/{slug}/products endpoint

Returns all rental models for a listing page (category/subrazdel/razdel).
Uses pre-aggregated subqueries to avoid M:N inflation. Brand from
tovar_rent.producer. Free units use same status logic as /inventory/free-tree.
Sorted by free_units DESC, price DESC."
```

---

### Task 5: Update docs/mcp_server.md

**Files:**
- Modify: `docs/mcp_server.md`

- [ ] **Step 5.1: Add the new endpoint row to the Pages table**

Find this block (around line 163–164):
```markdown
|        | `POST /pages/listing/{slug}/image` | Upload and resize hero-image for L2 category (saves as JPG, updates h1_pic_url) |
|        | `GET /pages/product` | List all L3 product models with their SEO completion status |
```

Add one row between those two lines:
```markdown
|        | `GET /pages/listing/{slug}/products` | List all rental models shown on a listing page (any level: razdel/subrazdel/category). Returns model_id, model_name, model_url, brand, total_units, free_units, is_available, prices. Sorted free_units DESC. |
```

Result after edit:
```markdown
|        | `POST /pages/listing/{slug}/image` | Upload and resize hero-image for L2 category (saves as JPG, updates h1_pic_url) |
|        | `GET /pages/listing/{slug}/products` | List all rental models shown on a listing page (any level: razdel/subrazdel/category). Returns model_id, model_name, model_url, brand, total_units, free_units, is_available, prices. Sorted free_units DESC. |
|        | `GET /pages/product` | List all L3 product models with their SEO completion status |
```

- [ ] **Step 5.2: Commit the docs update**

```bash
git add docs/mcp_server.md
git commit -m "docs(mcp): document GET /pages/listing/{slug}/products"
```

---

### Task 6: Update OpenAPI spec

**Files:**
- Modify: `resources/openapi/mcp-v1.json`

- [ ] **Step 6.1: Add the new path entry to the OpenAPI spec**

Find the very end of the `paths` object. The last path entry is `/pages/listing/{slug}/image`. The structure ends as:
```json
            }
        }
    },
    "tags": [
```

Change this (the `}` that closes `/pages/listing/{slug}/image`, then `},` that closes `paths`):
```json
            }
        }
    },
    "tags": [
```

To (add a comma after the image entry, then append the new path before `},`):
```json
            }
        },
        "/pages/listing/{slug}/products": {
            "get": {
                "summary": "List products for a listing page",
                "description": "Returns all rental models shown on the listing page with this slug. Supports all catalog levels: razdel, subrazdel (L2), and category (L3). Used by content pipelines as authoritative product source instead of HTML scraping.",
                "operationId": "getListingProducts",
                "tags": [
                    "Pages"
                ],
                "parameters": [
                    {
                        "name": "slug",
                        "in": "path",
                        "required": true,
                        "description": "Catalog slug at any level: razdel (e.g. 'prokat-detskih-tovarov'), subrazdel (e.g. 'autokresla'), or category (e.g. 'buster')",
                        "schema": {
                            "type": "string"
                        }
                    }
                ],
                "responses": {
                    "200": {
                        "description": "Success — list of models sorted by free_units DESC, price DESC",
                        "content": {
                            "application/json": {
                                "schema": {
                                    "type": "object",
                                    "properties": {
                                        "query": {
                                            "type": "object",
                                            "properties": {
                                                "slug":  { "type": "string" },
                                                "level": { "type": "string", "enum": ["razdel", "subrazdel", "category"] }
                                            }
                                        },
                                        "data": {
                                            "type": "array",
                                            "items": {
                                                "type": "object",
                                                "properties": {
                                                    "model_id":           { "type": "integer" },
                                                    "model_name":         { "type": "string" },
                                                    "model_url":          { "type": "string" },
                                                    "brand":              { "type": "string", "nullable": true },
                                                    "total_units":        { "type": "integer" },
                                                    "free_units":         { "type": "integer" },
                                                    "is_available":       { "type": "boolean" },
                                                    "price_per_week_byn": { "type": "number", "nullable": true },
                                                    "price_per_day_byn":  { "type": "number", "nullable": true }
                                                }
                                            }
                                        },
                                        "meta": {
                                            "type": "object",
                                            "properties": {
                                                "currency": { "type": "string", "example": "BYN" },
                                                "warnings": { "type": "array" }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    },
                    "404": {
                        "description": "Slug not found in catalog",
                        "content": {
                            "application/json": {
                                "schema": {
                                    "type": "object",
                                    "properties": {
                                        "error":   { "type": "string", "example": "not_found" },
                                        "message": { "type": "string" }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    },
    "tags": [
```

- [ ] **Step 6.2: Validate the JSON is still valid**

```bash
cd /home/dmitry/sites/tiktakby
python3 -c "import json; json.load(open('resources/openapi/mcp-v1.json')); print('JSON valid')"
```

Expected: `JSON valid`

- [ ] **Step 6.3: Commit**

```bash
git add resources/openapi/mcp-v1.json
git commit -m "docs(openapi): add /pages/listing/{slug}/products path"
```

---

### Task 7: Final verification

- [ ] **Step 7.1: Run full test suite one last time**

```bash
docker-compose exec app php artisan test tests/Feature/Mcp/
```

Expected: All tests pass, no regressions.

- [ ] **Step 7.2: Verify route cache still works (no closures)**

```bash
docker-compose exec app php artisan route:cache && echo "route:cache OK"
docker-compose exec app php artisan optimize:clear
```

Expected: `route:cache OK` — confirms no closure was accidentally added to routes.
