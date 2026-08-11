# MCP Analytics & AI Calls API

HTTP API (analytics + AI agent call processing) powering the local MCP server (`tiktak-mcp`).
Lives on `https://tiktak.by/api/mcp/v1/*` behind a token + GeoIP gate, returns
a uniform `{query, data, meta}` envelope, and exposes its own OpenAPI 3.0
description for client tooling.

## Methodology (locked 2026-05-14)

The API reproduces the calculations used by legacy admin reports
`/bb/reports.php`, `/bb/sales_breakdown.php`, `/bb/dohrash2.php`,
`/bb/cat_analysis.php`:

| Concern | Convention |
|---|---|
| **Revenue source** | `SUM(r_paid + delivery_paid)` over `UNION(rent_sub_deals_act, rent_sub_deals_arch)` |
| **Period filter** | `acc_date` (accounting date — when the payment landed). Not deal `cr_time`. |
| **Deal counts** | `COUNT(DISTINCT deal_id)` over `UNION(rent_deals_act, rent_deals_arch)` |
| **Issuance events** | `COUNT(*)` of sub-deals with `type IN ('first_rent','takeaway_plan')` — matches `/bb/reports.php` |
| **Renewal events** | `COUNT(*)` of sub-deals with `type = 'extention'` |
| **Office attribution** | `sub_deal.place` + `sub_deal.delivery_yn` (per-payment), NOT `deal.first_rent_place` (per-deal). Office 0 in responses = synthetic "Курьер" pseudo-office for `delivery_yn='1'` sub-deals. |
| **Carnival detection** | `tovar_rent_cat.cat_type=1`. All endpoints accept `include_carnival` (default true). `/finance/pnl` returns both `revenue_carnival_byn` and `revenue_non_carnival_byn`. |
| **Historical inventory** | At date X = `COUNT(tovar_rent_items WHERE buy_date<=X)` + `COUNT(tovar_rent_items_arch WHERE buy_date<=X AND arch_time>=X)`. Used by `/inventory/utilization`. |
| **Razdel filter dedup** | Joins through `subrazdel_category × razdel_subrazdel` are many-to-many and would inflate `SUM` aggregates. Use `BaseController::itemsInRazdelSubquery()` which returns DISTINCT `item_inv_n` for a razdel. |

### Common pitfalls for API consumers

- **Don't sum `/finance/revenue` with `/carnival/revenue`.** The carnival
  endpoint reads `karn_brons` (pre-booking system) — these bookings,
  once issued, also live in `rent_sub_deals_*`. Summing both is a
  double-count. To compare, use `/finance/pnl.revenue_carnival_byn` for
  realized carnival revenue and `/carnival/revenue` for pre-booking
  pipeline.
- **`include_carnival=false`** zeroes carnival contribution in every
  numeric field including `/finance/pnl.revenue_carnival_byn` (which is
  always zero in that mode by construction).
- **`location=courier`** is the new way to filter delivery_yn='1'
  sub-deals. Numeric `location=1|2|3` filters `place=N AND delivery_yn!='1'`.
- **`/inventory/utilization`** denominator is historical: `(units_at_from + units_at_to)/2`,
  not the current catalog count. For very old periods the inventory may
  be larger than today (items archived since).

## Architecture

```
┌──────────────────────────┐  HTTP  ┌──────────────────────────┐  MCP  ┌─────────────┐
│  Production tiktak.by    │◀──────│  Local tiktak-mcp server  │──────▶│ Claude Code │
│  Laravel + MariaDB       │        │  (Node.js, Phase B)       │        │             │
│  /api/mcp/v1/*           │        │                           │        │             │
└──────────────────────────┘        └──────────────────────────┘        └─────────────┘
```

The HTTP layer (this document) is Phase A. Phase B is the local MCP-protocol
server that wraps Phase A; it auto-generates its tool definitions from
`/api/mcp/v1/openapi.json`.

## Layout

| Concern | Path |
|---|---|
| Domain controllers | `app/Http/Controllers/Mcp/*Controller.php` (12 files) |
| Shared helpers | `app/Http/Controllers/Mcp/BaseController.php` (envelope, cache, data-freshness, TTL constants) |
| Form Request | `app/Http/Requests/Mcp/RangeRequest.php` (defaults, validation, `granularityFormatFor()`) |
| Middleware | `app/Http/Middleware/Mcp{ForceJson,Token,GeoCountry,AuditLog}Middleware.php` |
| Routes | `routes/api.php` under `Route::prefix('mcp/v1')` |
| OpenAPI spec | `resources/openapi/mcp-v1.json` (served via `HealthController::openapi()`) |
| DB performance indexes | migration `2026_05_09_000001_add_mcp_analytics_indexes` |
| Audit log table | `mcp_api_log` (created by `2026_05_06_000001_create_mcp_api_log_table`) |
| Feature tests | `tests/Feature/Mcp/*Test.php` (215 tests, analytics + calls) |
| Smoke test (prod) | `docs/mcp_smoke_test.sh` |
| Frontend tracking | `app/Http/Controllers/TrackingController.php` + `POST /track-event` (web route, CSRF-protected, public) |

## Response envelope

Every endpoint except `/openapi.json` and `/export/monthly/*` returns:

```json
{
  "query":  { /* echoed parameters with defaults filled in */ },
  "data":   /* endpoint-specific payload */,
  "meta": {
    "total_rows":     123,
    "currency":       "BYN",
    "data_freshness": "2026-04-23T20:49:28Z",
    "warnings":       [ /* see below */ ]
  }
}
```

`/finance/pnl` injects a warning when the requested period overlaps 2025+:

```json
{
  "code":    "fy2025_bank_channel_gap",
  "message": "С 2025-01 банковские расходы (налоги, аренда, банковские комиссии) перестали вводиться в doh_rash...",
  "ref":     "D-OPEN-FY2025"
}
```

This is mandated by the analytics workspace decisions log (D-OPEN-FY2025) —
do not remove it without coordinating with `/home/dmitry/Documents/прокат/`.

## Endpoint catalog

All endpoints accept `?from=&to=` (default: last 12 months) plus
`?granularity=day|week|month|quarter|year` (default: month) where applicable.
Categories enum: `all|children|costumes|medical|cleaning|sports|tools` —
`tools` has no current razdel and resolves to an empty result. Locations are
`offices.id` integers or `all`.

| Group | Endpoint | Purpose |
|---|---|---|
| Health | `GET /health` | Liveness, no DB |
|        | `GET /openapi.json` | Full OpenAPI 3.0 spec |
| Meta   | `GET /meta/categories` | Business categories + detailed `tovar_cats` |
|        | `GET /meta/locations` | Offices/couriers + computed first/last deal + revenue |
|        | `GET /meta/expense-items` | Active `rash_items` |
|        | `GET /meta/income-items` | Active `doh_items` |
|        | `GET /meta/data-freshness` | Per-table max(cr_time/acc_date) ISO UTC |
| Finance| `GET /finance/pnl` | Revenue + 7-bucket expenses + EBITDA, with 2025 warning |
|        | `GET /finance/revenue` | Period × category × location revenue |
|        | `GET /finance/revenue-by-category` | Period × category revenue with `avg_deal_byn`, `avg_rental_days` (full deal duration), `avg_first_rent_days` (first-issuance sub-deal duration) |
|        | `GET /finance/expenses` | doh_rash by `type2` × channel (cash/bank/etc.) |
|        | `GET /finance/cash-flow` | Inflow/outflow/net per till (`kassa`) |
|        | `GET /finance/entries` | List `doh_rash` ledger rows, filtered (`from`/`to`/`type1`/`type2`/`kassa`/`channel`/`dr_name_id`/`search`) and paginated. `amount` is always a positive magnitude; direction is `type1`. Only `rash`/`doh` rows are ever returned — `shift_plus`/`shift_minus` till transfers (~39% of the table) are filtered out server-side and cannot be opted back in. A reversed range (`to` < `from`) is a 422, never an empty page. See "Ledger entry model" below. |
|        | `GET /finance/entries/{id}` | Read a single ledger entry by `dr_id`. A `shift_plus`/`shift_minus` id 404s exactly like an unknown one — from this API there is no such entry. |
|        | `POST /finance/entries` | Batch-create 1-200 ledger entries. One invalid item never blocks the others — HTTP 200 with per-item `status: created\|invalid`; only whole-request shape problems (empty array, >200 items) are HTTP 422. `type1` restricted to `rash`/`doh`; `link_to` must be `0`. |
|        | `PATCH /finance/entries/{id}` | Partial update, validated against the merged (existing + patch) row — so it can fail on a field you never sent (see "Ledger entry model"). Body is read from the JSON body only; query-string parameters are ignored, and a body with no patchable field is a 422, not a no-op. Refuses rows whose existing `type1` is outside `rash`/`doh`. Journalled to `doh_rash_history` (best-effort — see below). |
|        | `DELETE /finance/entries/{id}` | **Physical delete** — the row is actually removed from `doh_rash`, no soft-delete flag. Refuses `type1` outside `rash`/`doh`. Recovery is via `GET /finance/entries/history`, journalled before the row is destroyed. |
|        | `GET /finance/entries/history` | Change journal (`doh_rash_history`) reader — `update`/`delete` only, full before/after row snapshots. `create` is never journalled (a new row is already self-describing via its own `created_by`/`created_at`). Filters: `dr_id`, `action`, `from`, `to` (reversed range → 422). |
| Operations | `GET /operations/funnel` | leads → deals → sub-deals → returns + CR |
|        | `GET /operations/timeline` | Period-bucketed funnel |
|        | `GET /operations/by-category` | Per-razdel orders + deals + revenue |
|        | `GET /operations/by-location` | Per-office deals + clients + revenue + returns |
|        | `GET /operations/deals-by-model` | Deals *started* per model × period (`cr_time`, not the period-intersecting count `/inventory/utilization` uses), with `units_at_period_end`/`deals_per_unit` denominator (skipped above 60 periods in range — see `inventory_denominator_skipped` warning) |
| Inventory | `GET /inventory/free-tree` | Catalog tree with free-unit counts |
|        | `GET /inventory/pricing` | Active catalog model pricing with calculated daily rate |
|        | `GET /inventory/profitability` | Per-physical-item profitability |
|        | `GET /inventory/utilization` | Per-model utilization, `avg_rental_days_per_deal`, and `avg_first_rent_days` (initial issuance duration) |
|        | `GET /inventory/turnover` | deals / units per model |
|        | `GET /inventory/idle?days=` | Models without rentals for ≥ N days |
| Customers | `GET /customers/timeline` | new / active / returning / new_active per period |
|        | `GET /customers/cohorts` | Monthly cohort × observed retention matrix |
|        | `GET /customers/repeat-intervals` | mean/p25/median/p75 + 6-bucket histogram |
|        | `GET /clients/ltv` | Top-N clients by LTV (no PII) |
| Geo    | `GET /geo/clients-by-city` | Trimmed/lower-cased city grouping |
| Locations | `GET /locations/performance` | Per-period × per-office revenue + tickets |
|        | `GET /locations/lifecycle` | Full office history (open/close/total) |
| Categories | `GET /categories/seasonality` | Month-of-year × seasonality_index |
|        | `GET /categories/performance` | Per-category deals + revenue (legacy) |
| Pricing | `GET /pricing/history` | Tariff change log from `rent_tarif_history` (one event = full before/after snapshot of a `rent_tarif_act` row). Filters: `model_id`, `category`, `from`, `to`, `change_type` (`baseline\|create\|update\|delete`), `actor_user_id`, `limit`/`offset`. Each row includes `delta_amount_byn`/`delta_pct`. |
|        | `GET /pricing/snapshot?as_of=` | Reconstructed price list as of an arbitrary date: latest `rent_tarif_history` event with `changed_at <= as_of` per `tarif_id`. Rows with no event before `as_of` but that were already active (`new_start_date <= as_of`) are returned with `extrapolated: true`, plus a `tariff_rows_extrapolated` warning giving the share. Cached for `TTL_HEAVY` (1 h) — an admin price edit can take up to an hour to show up here. |
| Carnival | `GET /carnival/funnel` | bookings → approved → issued → returned |
|        | `GET /carnival/seasonality` | December peak verification (idx ≈ 6.7+) |
|        | `GET /carnival/revenue` | k1/k2/terminal/bank revenue split |
| Legacy | `GET /orders/stats` | Original combined orders+brons+deals stats |
|        | `GET /deals/list` | Paginated recent deals with addresses (no PII) |
| Export | `GET /export/monthly/{topic}` | CSV stream for `operations`, `revenue`, `pnl`; `traffic` is a header-only stub (Y.Metrika lives elsewhere) |
| Calls  | `GET /calls/recordings` | List A1 VATS recordings. Response includes **new fields**: `is_internal` (bool), `missed_reason` (stock/assortment/price/null), `missed_outcome` (hard/soft/null). `transcript` excluded — fetch via detail endpoint. |
|        | `GET /calls/recordings/{uuid}/file` | Stream MP3 binary (Range-aware) |
|        | `GET /calls/cdr` | CDR list — all calls (incoming/outgoing/missed) from A1 VATS |
|        | `GET /calls/pending-analysis` | Pending AI analysis queue; auto-marks returned records as processing |
|        | `GET /calls/recordings/{uuid}/analysis` | Get analysis result for a recording |
|        | `POST /calls/recordings/{uuid}/analysis` | Submit analysis. Body: `{transcript, ai_summary, ai_result, ai_result_detail, discussed_items[], missed_item, client_sentiment, consultant_sentiment, ai_business_note, **is_internal** (bool), **missed_reason** (stock/assortment/price), **missed_outcome** (hard/soft)}`. Sets ai_status=done or error. |
|        | `GET /calls/recordings/{uuid}/items` | Get demand items for a recording (from `call_demand_items` table) |
|        | `POST /calls/recordings/{uuid}/items` | Submit demand items. Array of `{phrase, kind(demand/missed), cat_id, cat_name, match_source, missed_reason, missed_outcome}`. Replaces all non-manual items; preserves rows with `match_source='manual'`. |
|        | `GET /calls/demand` | Aggregate demand by category. Params: `from`, `to`, `kind`, `missed_reason`, `missed_outcome`, `razdel_id`. Response: `{cat_id, cat_name, mentions, missed_hard, missed_soft, top_phrases[]}`. Excludes internal calls. |
|        | `POST /calls/recordings/import-completed` | Bulk import historical records with fully completed analysis (skips pending queue) |
|        | `GET /calls/daily-summary/{date}` | Get AI daily summary for YYYY-MM-DD |
|        | `POST /calls/daily-summary/{date}` | Upsert daily summary; counts auto-filled from a1_cdr |
|        | `POST /calls/recordings/{uuid}/reset-analysis` | Reset ai_status → pending for re-processing |
| Marketing | `GET /marketing/conversions` | All conversion events with full UTM attribution. Joins entity details from `zvonki`, `rent_orders`, `karn_brons`, `kb_zayavki`. Accepts `?utm_source=` and `?utm_campaign=` filters. |
| Pages (SEO) | `GET /pages/listing` | List all L2 categories, subrazdels, razdels with their SEO completion status |
|        | `GET /pages/listing/{slug}` | Read L2 category SEO fields (from `pages` table) with site defaults |
|        | `PATCH /pages/listing/{slug}` | Upsert L2 category SEO fields (`meta_title`, `meta_description`, `h1`, `intro_text`, `seo_text`, `h1_pic_url`, `faq`) |
|        | `POST /pages/listing/{slug}/image` | Upload and resize hero-image for L2 category (saves as JPG, updates h1_pic_url) |
|        | `GET /pages/listing/{slug}/products` | List all rental models shown on a listing page (razdel/subrazdel/category). Returns `model_id`, `model_name`, `brand`, `active_units`, `free_units`, `is_available`, prices. Sorted `free_units DESC`. |
|        | `GET /pages/product` | List L3 product pages with SEO completion status. Filters: `razdel`, `subrazdel`, `category`, `search`, `missing=` (CSV of field names, "missing at least one"), `status=show\|not_show\|all`, `has_stock`, `indexable`; `fields=full` adds the editable values, `summary=1` adds a per-category breakdown; paginated (`per_page` max 500, default 100) |
|        | `GET /pages/product/{slug}` | Read L3 model SEO fields (from `rent_model_web`). 409 when the slug maps to several rows |
|        | `PATCH /pages/product/{slug}` | Update L3 model SEO fields (`meta_title`, `meta_description`, `h1`, `l2_name`, `main_pic_alt`, `main_pic_title`, `l2_pic_alt`, `description`, `breadcrumb_name`, `faq`) |
|        | `PATCH /pages/product/bulk` | Update up to 100 pages in one request. Body `{"pages":[{"slug": …, …fields}]}`; each item reports `updated\|unchanged\|not_found\|conflict\|invalid` |
|        | `GET /pages/history` | Field-level change log of SEO content written through this API (`mcp_content_versions`). Filters: `page_type`, `slug`, `field`, `from`, `to` |
| SMS    | `POST /sms/send` | Send an SMS message using RocketSMS (`phone`, `text`, optional `sender`) |
| Redirects | `GET /redirects` | List redirects with optional filters: `is_active`, `is_regex`, `search` (LIKE on source/target); paginated (`per_page` max 500, default 100) |
|        | `POST /redirects` | Create a single redirect (`source_url`, `target_url`, required; `status_code` 301/302, `is_active`, `is_regex`, `comment` optional, **max 255 chars**). Non-regex URLs auto-prefixed with `/`. Returns 422 on duplicate `source_url`. |
|        | `PATCH /redirects/{id}` | Partial update — only provided fields are modified. At least one field required. `comment` max 255 chars. |
|        | `DELETE /redirects/{id}` | Delete redirect by id. |
|        | `POST /redirects/bulk` | Bulk upsert up to 200 redirects. Body: `{"redirects": [...]}`. `comment` max 255 chars. Uses `INSERT … ON DUPLICATE KEY UPDATE` on `source_url`. All writes immediately clear `redirects_exact_map` + `redirects_regex_list` cache keys used by `CheckRedirects` middleware. |

## Ledger entry model (`/finance/entries*`)

`doh_rash` is the income/expense ledger. Its column names don't explain
themselves, and neither an admin seeing them for the first time nor a
calling agent can infer them from the name alone — this section is the
reference, not a footnote.

**Every entry answers four questions:**

| Question | Field | Values |
|---|---|---|
| Direction | `type1` | `doh` (income) \| `rash` (expense) |
| Article | `type2` | code from `doh_items.rd_code` (income) or `rash_items.ri_code` (expense) |
| Where it happened | `channel` | office number (string, e.g. `"2"`), `cur` (courier), or `bank` |
| Where the money sits | `kassa` | `k1` \| `k2` (physical cash tills), `card`, `bank` |

**`type1` — the hard editable boundary.** `doh_rash` also holds `shift_plus`/
`shift_minus` rows: paired till-transfer records (linked to each other via
`link_to`) that keep the physical till balance in sync. This API can only
create, update or delete rows with an existing `type1` of `rash` or `doh` —
`shift_plus`/`shift_minus` rows are excluded on **both** sides (the existing
DB row and the incoming/target value), because a single-row write would
desynchronize the till balance the pair exists to keep in sync.

**The same boundary applies to reads.** `GET /finance/entries` never lists a
`shift_plus`/`shift_minus` row (they are ~39% of `doh_rash`), and
`GET /finance/entries/{id}` 404s for one. There is no parameter to include
them, and none is wanted: rendered through this API's shape a transfer row
loses its sign (`amount` is always a positive magnitude) and resolves no
`type2_name`, so summing `amount` over a page that contained one would count
a till transfer as income.

This is enforced and tested (`FinanceEntriesController::EDITABLE_TYPE1`), not
an implementation detail to work around.

**`type2` — write vs. read.** On write, `type2` must be an `is_active=1` code
in the dictionary matching `type1`. On read, `type2_name` resolves the label
even for inactive/retired codes, so a historical row referencing a since-
deactivated article code still displays a readable name.

**`channel` × `kassa` is a validated pair, not two independent fields** —
money can't be simultaneously in the bank account and a physical till:

| `kassa` | valid `channel` |
|---|---|
| `bank` | `bank` only |
| `k1`, `k2`, `card` | a live office number (existence-gated, not active-status-gated — a closed-but-existing office is still valid), or `cur` |

A mismatched pair (e.g. `channel=bank` + `kassa=k2`) is rejected with a 422.

**`dr_name_id`** — which employee (`logpass_id`) this entry is attributed to.
Required (and must reference an existing `logpass` row) when `type2` is
`zpl` or `avans` (salary/advance) — omitting it there silently breaks the
legacy per-employee salary report. Optional otherwise, defaulting to `0`
(not attributed to an employee).

**`date` is the accounting date, not the creation date.** It round-trips as
`acc_date` — when the money moved, which is what every financial report
(`/finance/pnl`, `/finance/revenue`, etc.) slices on — and is distinct from
`created_at` (`cr_time`, when the record was typed into the system).

**`amount` is always a positive magnitude**, in both requests and responses.
Direction is carried entirely by `type1`. Internally `doh_rash.amount` is
stored negative for `rash` and positive for `doh`; the API hides that
storage detail on both write (server negates for `rash`) and read (server
returns `abs()`).

**`info`** is required and must be non-empty after trimming whitespace (max
2000 chars) — this guards against silent `TEXT`-column truncation under this
app's empty `sql_mode`.

**`link_to` must be `0`. Any other value is rejected (422 / per-item
`invalid`), on create and on the merged PATCH row alike** — and this is a
data-integrity guard, not a stylistic restriction, so do not loosen it:

> The legacy admin (`bb/doh-rash.php`) renders **every** row's delete form
> with a hidden `dr_id_link` set to that row's `link_to`, and its delete
> handler runs `DELETE FROM doh_rash WHERE dr_id IN ('$dr_id','$dr_id_link')`
> whenever that value is `> 0` — with no `type1` check, behind a confirm
> dialog that says «эту операцию», singular. A `rash`/`doh` row carrying a
> non-zero `link_to` therefore silently destroys a *second, unrelated* row the
> next time a human deletes it in the admin — possibly one half of a
> `shift_plus`/`shift_minus` pair, corrupting a till balance through a
> different door than the `type1` boundary above closes.

Rejecting only links that *point at* a shift row would not be enough: the
legacy cascade deletes whatever `dr_id` it is handed. Linked/paired operations
are out of scope for this API; `0` is the only accepted value.

Because the rule is enforced against the **merged** PATCH row, a legacy row
that already carries a non-zero `link_to` cannot be patched until the caller
clears it — send `link_to: 0` alongside the patch and the error message says
so. (When this rule landed, 0 of 19,606 real `rash`/`doh` rows were in that
state; this API was the only thing that could ever have introduced one.)

**Server-set fields, never client-controlled:** `created_by`/`cr_who_id`
(always the dedicated `api_system` logpass user — a client-supplied value is
silently ignored, not rejected) and `created_at`/`cr_time`.

**`DELETE` is physical, not soft.** The row is actually removed from
`doh_rash`. There is no status flag to flip back — recovery is via
`GET /finance/entries/history`, which is journalled from a snapshot taken
**before** the row is destroyed.

**The change journal (`doh_rash_history`)** records `update` and `delete`
only, each as a full before/after row snapshot — never `create` (a newly
created row is already self-describing via its own `created_by`/
`created_at`). This is the only recovery path for a mistaken edit or delete
made through this API.

Journalling is **best-effort**: a journal-write failure is logged (with the
full before/after payload, so the application log can stand in as the recovery
trace) but never rolls back the `doh_rash` write that already succeeded. So a
successful `PATCH`/`DELETE` response means the ledger row changed — it is not,
by itself, a guarantee that a history row exists for it. The one exception is
`DELETE`, which resolves its journal actor **before** touching the row and
aborts with a 500 (row intact) if that fails, because an unjournalled delete
would be unrecoverable.

**Validation of a `PATCH` runs against the merged row, so it can fail on a
field you never sent.** That is deliberate — the row as it would be *after*
the patch must be valid — but it surprises callers when the offending value
was already in the database. The two live cases:

- **`info`** — ~20% of existing `rash`/`doh` rows have an empty `info`
  (legacy data predating this API's stricter write rules). Patching an amount
  on one of them fails on `info`; the error explicitly says the row has no
  description on file and that you should supply `info` with your patch.
- **`link_to`** — see the `link_to` rule above; supply `link_to: 0`.

## L3 product pages — URL resolution and gotchas

**`full_url` is the canonical URL, resolved through the single-parent chain the site itself uses**
(`ModelWeb::getUrlPageAddress`):

```
rent_model_web.model_id → tovar_rent.tovar_rent_cat_id → tovar_rent_cat.main_sub_razdel_id
                        → sub_razdel.main_razdel_id → razdel
```

Do **not** resolve it through `subrazdel_category` × `razdel_subrazdel`. A category linked to several
subrazdels renders the same product under several working URLs, but only the chain above carries
`<link rel="canonical">` and only it appears in `sitemap.xml`. The M:N chain used before 2026-07 returned
a non-canonical URL for ~14% of models and `NULL` for ~130 models that do have one.
`tests/Feature/Mcp/PagesProductTest::test_full_url_matches_the_canonical_chain` guards this.

- `full_url: null` + `no_canonical_url` warning = the category is not wired into the catalog tree, so the
  page has no URL at all and is absent from the sitemap. Such pages are still readable and writable
  (they used to 404 while a PATCH silently succeeded).
- `is_indexable` = `status='show'` AND canonical URL exists AND `active_units > 0` — the same rule
  `GenerateSitemap` applies. Use `?indexable=1` to get the pages worth optimising.
- `page_addr` is not unique: several rows can share a slug. Both read and write answer **409
  `slug_conflict`** listing the colliding `web_id`/`model_id` instead of guessing (the previous
  implementation updated every matching row).
- `meta_title`, `meta_description`, `*_pic_alt`, `main_pic_title` and `breadcrumb_name` are stripped of
  tags and `"`/`<`/`>` before storage — they end up inside `<title>` or an HTML attribute rendered by an
  unescaped `@yield`. `description` and `h1` keep their markup. Tag removal is a regex over well-formed
  tags, **not** `strip_tags()`: the latter treats a lone `<` as an opening tag and would store
  «Коляска весом <5 кг для прогулок» as «Коляска весом».
- **`sql_mode` is empty on production** — MySQL truncates oversized values instead of raising an error.
  `description` and the encoded `faq` are therefore rejected with 422 above 60 000 bytes (TEXT holds
  65 535). Any new write path into these tables needs the same guard.
- `h1` (`item_name_main`) and `l2_name` may be edited but not blanked (`filled` rule) — the same value is
  the product name in the admin panel and in `bb/` printed forms. It is never copied into deal or order
  tables, so editing it is display-only and safe.
- **The L3 template has no meta fallbacks.** An empty `title` column ships a literally empty `<title>` tag
  (unlike L2, where `PagesListingController` advertises generated defaults); `show` returns an
  `empty_meta_title` warning for those pages.
- Every changed field is written to `mcp_content_versions` and readable via `GET /pages/history`.
  Edits made in the legacy admin (`bb/model_web.php`) are **not** recorded there.
- After a content change, purge the Cloudflare cache — the HTML is cached at the edge.

## Marketing conversions — data model

All conversions are stored in `tiktak_utms` and populated via `App\Helpers\UtmTracker::track($entityType, $entityId)`, called after each qualifying user action:

| `entity_type` | Source action | Joined table | PK |
|---|---|---|---|
| `zvonki` | Callback request / call lead | `zvonki` | `zv_id` |
| `rent_orders` | Online order (standard item booking) | `rent_orders` | `order_id` |
| `karn_brons` | Carnival costume booking | `karn_brons` | `kb_id` |
| `kb_zayavki` | Waitlist request (item out of stock) | `kb_zayavki` | `id` |
| `phone_click` | Click on phone number (JS event) | — | `entity_id = 0` |
| `add_to_cart_click` | Add-to-cart button click (JS event) | — | `entity_id = 0` |

### Response row shape (`GET /marketing/conversions`)

```json
{
  "date":         "2026-05-24 17:32:10",
  "entity_type":  "rent_orders",
  "utm_source":   "google",
  "utm_medium":   "cpc",
  "utm_campaign": "brand_may2026",
  "utm_term":     "прокат коляски минск",
  "fio":          "Иванова",
  "phone":        "375291234567",
  "info":         null,
  "status":       ""
}
```

For `phone_click` / `add_to_cart_click` the entity fields (`fio`, `phone`, `info`, `status`) are always `null`. UTM data is taken from the `tiktak_utms.utm_*` columns.

### Front-end JS tracking snippet

To record a frontend event (phone click, add-to-cart), call from page JS:

```javascript
fetch('/track-event', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
  },
  body: JSON.stringify({ entity_type: 'phone_click' }) // or 'add_to_cart_click'
});
```

The endpoint reads UTM cookies set by `TrackUtmMiddleware` and writes a row to `tiktak_utms`. UTMs with no active cookie are silently skipped (returns `{success: false}` — not an error).

## Caching

`Cache::remember`-based, file driver in production. TTL constants on `BaseController`:

| Class of endpoint | TTL | Constant |
|---|---|---|
| Meta / static reference | 24 h | `TTL_META` |
| Heavy aggregations (P&L, cohorts, seasonality, exports) | 1 h | `TTL_HEAVY` |
| Default | 5 min | `TTL_DEFAULT` |
| Disabled | — | `TTL_NONE` |

`BaseController::dataFreshness()` is itself cached for 5 minutes so the
envelope timestamp is cheap on every response.

## Authentication

Bearer token via the `Authorization` header, validated against
`config('mcp.api_token')` (`MCP_API_TOKEN` env var). Generate a fresh value
with `openssl rand -hex 32`.

GeoIP allow-list is `MCP_GEO_ALLOWED_COUNTRIES=BY,RU` (private/loopback IPs
are exempt). The GeoLite2 DB lives at `storage/app/geoip/GeoLite2-Country.mmdb`
and the middleware fails open with a logged warning if it's missing.

## Tests

```bash
docker exec tiktakby-app ./vendor/bin/phpunit tests/Feature/Mcp/
```

`McpTestCase` injects a token, disables geo + audit middleware, and exposes
`mcp(path, query)` + `assertEnvelope($response)`. 215 tests verify happy
paths, validation, the 2025 warning, the location-3 acceptance criteria,
December peaks for costumes/carnival, CSV header layout, and all calls
endpoints (recordings, CDR, pending-analysis queue, analysis submit/get/reset,
daily summaries).

## Smoke test (prod)

After deploy, run from any machine that can reach prod:

```bash
MCP_API_BASE=https://tiktak.by/api/mcp/v1 \
MCP_API_TOKEN=<token> \
./docs/mcp_smoke_test.sh
```

The script hits every endpoint and asserts:

- `{query, data, meta}` envelope shape
- `meta.currency == "BYN"`
- 2019 P&L: `revenue ≈ 433 656`, `EBITDA ≈ +34 909`
- 2024 P&L: `EBITDA ≈ −15 071`
- 2025 P&L: contains the `fy2025_bank_channel_gap` warning
- 2019 ops `by-location`: office id 3 (Pobediteley) is top
- 2022-08 → 2026 ops `by-location`: office id 3 absent
- December has the highest `seasonality_index` for both `categories=costumes`
  and `carnival/seasonality`

Exit code = number of failed endpoints. Requires `python3` (no `jq` needed).

## Deployment

Production deploy is via `https://tiktak.by/Deploy.php?key=...` which already
runs `migrate --force`, `route:cache`, `view:cache`. After deploy:

1. Run `docs/mcp_smoke_test.sh` against the prod base URL — should report
   `passed: 50, failed: 0`.
2. Verify `GET /api/mcp/v1/openapi.json` returns the spec; this is the
   contract Phase B's MCP server reads.
3. Tail `mcp_api_log` (table) for the first hour to spot anomalies.

## Phase B (out of scope here)

The local MCP server lives at `/home/dmitry/.mcp-servers/connectors/tiktak-mcp/`
and consumes this API. It registers ~42 tools 1:1 with the endpoints,
auto-generated from `/openapi.json`. See
`/home/dmitry/Documents/прокат/99_meta/api_stage1_implementation.md` for the
B-side plan.
