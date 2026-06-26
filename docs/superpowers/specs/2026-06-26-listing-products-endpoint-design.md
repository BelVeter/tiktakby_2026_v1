# Design: GET /api/mcp/v1/pages/listing/{slug}/products

**Date:** 2026-06-26  
**Status:** Approved  
**Context:** Content generation pipeline for tiktak.by — replaces fragile HTML scraping with authoritative DB source.

---

## Problem

The external content pipeline (`build_context.py`) scrapes HTML to get product lists per listing page. Due to JS/pagination it loses products (e.g., dropped from 79 to 24 items). This endpoint provides a reliable, DB-backed source.

---

## Endpoint

```
GET /api/mcp/v1/pages/listing/{slug}/products
```

Auth: Bearer token (same `mcp.token` middleware).  
Response: standard `{query, data, meta}` envelope.

### Slug resolution

Reuses existing `resolveListingSlug()` in `PagesListingController`. Supports all three catalog levels:

| Level | Slug example | Filter |
|-------|-------------|--------|
| `category` | `buster`, `autokresla-lulki` | `tovar_rent_cat.cat_url_key = slug` |
| `subrazdel` | `autokresla`, `kolyaski` | `sub_razdel.url_sub_razdel_name = slug` |
| `razdel` | `prokat-detskih-tovarov` | `razdel.url_razdel_name = slug` |

Slug not found → 404 in standard MCP error format.

### Response fields per model

```json
{
  "model_id": 42,
  "model_name": "Автокресло Cybex Sirona T i-Size",
  "model_url": "/ru/autokresla/cybex-sirona-t-isize",
  "brand": "Cybex",
  "total_units": 5,
  "free_units": 2,
  "is_available": true,
  "price_per_week_byn": 45.00,
  "price_per_day_byn": 8.00
}
```

**Field semantics:**
- `brand` — `tovar_rent.producer` (LEFT JOIN; null if empty/not set; NEVER guessed)
- `total_units` — all units in `tovar_rent_items` for this model (total inventory)
- `free_units` — units with `active_deal_id = 0` AND `status NOT IN ('в аренде','бронь','ремонт','списан')` — same logic as `/inventory/free-tree`
- `is_available` — `free_units > 0`
- Prices — week/day tariff (`rent_tarif_act`, `kol_vo = 1`)

### Sorting

`free_units DESC, price_per_week_byn DESC NULLS LAST` — free items first, within free: premium first (for content pipeline's "principle D": premium → budget).

### Caching

`TTL_DEFAULT` (5 min), cache key includes slug.

---

## Implementation approach

All aggregates (counts, prices) computed in pre-aggregated subqueries **before** the main catalog JOIN. This prevents M:N row inflation when a model appears in multiple categories within a subrazdel/razdel.

```sql
-- Canonical catalog join (same as freeTree):
JOIN tovar_list tl ON tl.tovar_id = rmw.model_id AND tl.tovar_cat = <cat_id>

-- Free units subquery (same logic as freeTree):
SELECT model_id, COUNT(*) AS free_units
FROM tovar_rent_items
WHERE active_deal_id = 0
  AND (status IS NULL OR status NOT IN ('в аренде','бронь','ремонт','списан'))
GROUP BY model_id

-- Total units subquery:
SELECT model_id, COUNT(*) AS total_units
FROM tovar_rent_items
GROUP BY model_id

-- Prices subquery:
SELECT model_id,
  MIN(CASE WHEN step='week' AND kol_vo=1 THEN rent_amount END) AS price_per_week_byn,
  MIN(CASE WHEN step='day'  AND kol_vo=1 THEN rent_amount END) AS price_per_day_byn
FROM rent_tarif_act
GROUP BY model_id
```

For subrazdel/razdel levels: the catalog filter is applied via JOINs; outer `GROUP BY rmw.model_id` collapses duplicates from multi-category models.

---

## Files to change

1. `app/Http/Controllers/Mcp/PagesListingController.php` — add `products()` method
2. `routes/api.php` — add route (controller string, no closure)
3. `docs/mcp_server.md` — document new endpoint
4. `resources/openapi/mcp-v1.json` — add OpenAPI path entry

---

## Site-parity rules (verified against `Model::getModelIdsArrayBy*`)

The endpoint must return EXACTLY the models a visitor sees on the listing page. Two
deliberate rules, verified against the legacy rendering path (`bb/classes/Model.php`):

1. **Only models with ≥1 physical item** — the site filters `tovar_rent_items.item_id > 0`
   in every level query. Replicated via `INNER JOIN` on the inventory subquery (which
   emits a row only for models that have items). A `status='show'` model with zero stock
   is a phantom and must NOT appear. Verified: 0 mismatches across 40 categories.

2. **`multi_web` cross-listings intentionally EXCLUDED** — the site's subrazdel/category
   queries UNION in models from `multi_web` (a model borrowed into additional categories).
   Per owner decision (2026-06-26), this endpoint returns only models whose PRIMARY
   `tovar_rent.tovar_rent_cat_id` matches the listing — "только реально дочерние". This is
   a documented divergence: the endpoint may return slightly fewer models than the rendered
   page for subrazdels that use multi_web.

## Out of scope

- Write/mutation operations
- Filtering by availability (client-side concern)
- Pagination (content pipeline needs full list)
- Carnival items distinction (handled by content pipeline separately)
- `multi_web` cross-category listings (see Site-parity rules above)
