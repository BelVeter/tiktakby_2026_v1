# MCP API methodology migration — 2026-05-14

## TL;DR

The MCP Analytics API now reproduces the calculations from the legacy admin
reports `/bb/reports.php`, `/bb/sales_breakdown.php`, `/bb/dohrash2.php`,
`/bb/cat_analysis.php`. Numbers will differ from the pre-migration API for
the same time range — this is the correction, not a regression. The
`docs/mcp_smoke_test.sh` acceptance values have been updated to the new
baselines.

This is a **breaking change** for every consumer of the API. There is no
opt-in flag for the old behavior — the previous methodology was
demonstrably wrong (see "What was broken" below).

## What was broken

| Bug | Impact |
|---|---|
| API read only `rent_deals_arch`; legacy reports read `UNION(_act, _arch)` | ~430 open deals invisible (mostly affects last 30-60 days) |
| API filtered by deal `cr_time`; legacy filtered by sub-deal `acc_date` | Month/quarter totals drift; deals spanning periods accounted incorrectly |
| Office attribution used `rent_deals_arch.first_rent_place` (per-deal); legacy uses `rent_sub_deals.place` (per-payment) | Office-3 (Pobediteley) understated, Office-1 overstated by ~23% in 2024 |
| `/finance/revenue` always included carnival items; legacy `/bb/dohrash2.php` has separate "карнавал / не карнавал" rows | Carnival revenue couldn't be isolated; double-count risk with `/carnival/revenue` |
| `/inventory/utilization` used current unit_count as denominator; legacy uses historical | Retrospective utilization wrong when inventory grew |
| Razdel filter joined `subrazdel_category × razdel_subrazdel` directly | M:N inflation of SUM aggregates (e.g. children-category revenue exceeded total) |
| `/orders/stats` and `/deals/list` were thin legacy passthroughs to `rent_deals_act` only | Same `_act`-only blindness; also out of scope of the new methodology |

## What changed in the API

### Methodology (every numeric endpoint)

| Concern | New behavior |
|---|---|
| Revenue source | `SUM(r_paid + delivery_paid)` over `UNION(rent_sub_deals_act, rent_sub_deals_arch)` |
| Period filter for revenue | `sub_deal.acc_date BETWEEN from AND to` |
| Deal/return source | `UNION(rent_deals_act, rent_deals_arch)` |
| Period filter for deal counts | `da.cr_time BETWEEN from AND to` (issuance event) or `da.return_date BETWEEN from AND to` (return event) |
| Office filter | `sub_deal.place` + `sub_deal.delivery_yn` (per-payment); office_id=0 = synthetic "Курьер" |
| Carnival detection | `tovar_rent_cat.cat_type=1` |
| Razdel filter | `BaseController::itemsInRazdelSubquery()` returns DISTINCT item_inv_n — used in JOIN instead of the M:N chain |
| Inventory at date X | `tovar_rent_items (buy_date≤X)` + `tovar_rent_items_arch (buy_date≤X AND arch_time≥X)` |
| Utilization denominator | `(units_at_from + units_at_to) / 2 × period_seconds` |

### New query parameter

`include_carnival` (boolean, default `true`) on every numeric endpoint.
Truthy: `true`, `1`, `yes` (default). Falsy: `false`, `0`, `no`, `off`.

When `false`, sub-deals whose parent deal's `item_inv_n` maps to a
`tovar_rent_cat.cat_type=1` category are excluded.

### New `location` value

`location=courier` — selects `sub_deal.delivery_yn='1'`. Office numerics
(`location=1|2|3`) now imply `delivery_yn != '1'`.

### Response shape changes

| Endpoint | Field changes |
|---|---|
| `/finance/pnl` row | **+** `revenue_rent_byn`, `revenue_delivery_byn`, `revenue_non_carnival_byn`, `revenue_carnival_byn`. Carnival/non-carnival are always present even when `include_carnival=true`. |
| `/finance/revenue` row | **–** `unique_clients`; **+** `issuance_events` (sub-deal count with type IN `first_rent`/`takeaway_plan`) |
| `/operations/funnel` data | **+** `issuance_events` |
| `/operations/by-location` row | **–** `returns_in_period`, `first_deal_in_period`, `last_deal_in_period`; **+** `issuance_events`. office_id=0 = Курьер. |
| `/locations/performance` row | **–** `avg_ticket_byn`; **+** `office_type`, `issuance_events`, `unique_clients_proxy`, `avg_payment_byn`. office_id=0 = Курьер. |
| `/locations/lifecycle` row | renamed `first_deal_date` → `first_activity_date`, `last_deal_date` → `last_activity_date` (now derived from sub_deal.acc_date — captures office-3 lifetime properly) |
| `/inventory/utilization` row | **–** `units`; **+** `units_at_from`, `units_at_to`, `avg_units` (historical) |
| `meta` | **+** `methodology` string on `/finance/revenue` and `/locations/performance` (descriptive note) |
| `query` | **+** `include_carnival` echoed back |

### Removed endpoints

- `GET /orders/stats` — superseded by `/operations/funnel` + `/operations/timeline`.
- `GET /deals/list` — no functional replacement; if needed, build a new endpoint with the correct UNION methodology.

### Numbers that changed (reference)

| Reference query | Old API | New API | Source of truth |
|---|---:|---:|---|
| /finance/pnl 2019 full year, revenue_byn | 433 656 | **424 232** | /bb/dohrash2.php |
| /finance/pnl 2019 full year, ebitda_byn | +34 909 | **+25 485** | (was wrong: 2019 expenses underreported) |
| /finance/pnl 2024 full year, revenue_byn | 291 069 | **293 189** | sub_deal.acc_date sums |
| /finance/pnl 2024 full year, ebitda_byn | −15 071 | **−12 950** | revenue − expenses_total |
| /finance/revenue location=1, 2024 | 180 497 | **148 413** | sub_deal.place per-payment |
| /finance/revenue location=3, 2019 | 0 (invisible) | **186 392** | sub_deal.place=3 |

## For API consumers

The full consumer-side migration prompt is at the end of this document
(see "Consumer-side migration prompt").

Quick checklist:
1. Stop summing `/finance/revenue` with `/carnival/revenue` — that's a double-count.
2. Use `/finance/pnl.revenue_carnival_byn` for realized carnival revenue.
3. Pass `location=courier` to filter delivery, not `location=0` or `delivery_yn=1`.
4. Pass `include_carnival=false` when you specifically want non-carnival only.
5. If you displayed `unique_clients`, `returns_in_period`, `avg_ticket_byn`, `units`, `first_deal_date`, `last_deal_date` — rename / re-derive.
6. If you called `/orders/stats` or `/deals/list` — switch to `/operations/funnel`, `/operations/timeline`, or build a custom query.

## Testing

```bash
# Full test suite, including legacy parity:
docker exec tiktakby-app ./vendor/bin/phpunit tests/Feature/Mcp/

# Production smoke test (after deploy):
MCP_API_BASE=https://tiktak.by/api/mcp/v1 \
MCP_API_TOKEN=$TIKTAK_MCP_TOKEN \
./docs/mcp_smoke_test.sh
```

The legacy-parity test (`tests/Feature/Mcp/LegacyParityTest.php`) compares
API responses to direct SQL queries against the legacy schema for several
canonical metrics. Do not weaken these assertions.

---

## Consumer-side migration prompt

Copy the section below verbatim into your consumer-side agent's prompt.

```
The TikTak.by MCP Analytics API at https://tiktak.by/api/mcp/v1 was migrated
on 2026-05-14 to a new methodology that reproduces the legacy admin
reports (/bb/reports.php, /bb/sales_breakdown.php, /bb/dohrash2.php,
/bb/cat_analysis.php). The OpenAPI spec is current at
https://tiktak.by/api/mcp/v1/openapi.json — re-fetch it.

Your task: audit every place where this codebase calls the API and update
it to the new contract. Do NOT keep backward-compatibility shims for the
old behavior — the new methodology is correct and the old API was wrong.

## What changed at the API level

1. Two query parameters added (both accept truthy/falsy strings):
   - `include_carnival` (default true) on every numeric endpoint. Set
     false to exclude items in carnival categories
     (`tovar_rent_cat.cat_type=1`).
   - `location=courier` is the new way to filter delivery_yn='1'
     sub-deals. `location=1|2|3` filters non-courier office sub-deals.

2. Endpoints REMOVED (return 404):
   - GET /orders/stats — use /operations/funnel + /operations/timeline.
   - GET /deals/list — no functional replacement; if you depended on
     per-deal rows with addresses, request a new endpoint or build one.

3. Response shape changes (full list in docs/mcp_api_migration_2026-05-14.md
   in the server repo):
   - /finance/pnl row gained: revenue_rent_byn, revenue_delivery_byn,
     revenue_non_carnival_byn, revenue_carnival_byn. The carnival/non-
     carnival split is ALWAYS present, even when include_carnival=true.
   - /finance/revenue row gained: issuance_events (count of sub-deals
     with type IN ('first_rent','takeaway_plan')). LOST: unique_clients.
   - /operations/by-location row uses office_id=0 for the Курьер pseudo-
     office (delivery_yn='1' sub-deals). Removed: returns_in_period,
     first_deal_in_period, last_deal_in_period. Added: issuance_events.
   - /locations/performance row: removed avg_ticket_byn, added
     office_type, issuance_events, unique_clients_proxy, avg_payment_byn.
   - /locations/lifecycle row: first_deal_date → first_activity_date,
     last_deal_date → last_activity_date.
   - /inventory/utilization row: removed `units`, added units_at_from,
     units_at_to, avg_units. Use avg_units when displaying a single
     "inventory size" number.

4. Numbers will differ for the same query because the methodology is
   different. Examples:
   - /finance/pnl 2019 full year: revenue was 433 656; now is ~424 232.
     ebitda was +34 909; now is +25 485.
   - /finance/pnl 2024 full year: ebitda was −15 071; now is −12 950.
   - /finance/revenue location=3 in 2019 returned empty; now ~186k BYN.
   - /finance/revenue location=1 in 2024 returned 180k; now ~148k.

## What to update on your side

a) Refetch /api/mcp/v1/openapi.json and regenerate any client stubs /
   tool registrations.

b) Find every UI / report / dashboard / saved query that:
   - Displays /finance/revenue.unique_clients — remove that column
     or replace with /customers/timeline.active_clients for the same period.
   - Displays /operations/by-location.returns_in_period — query
     /operations/timeline.returns instead (it has per-period return counts).
   - Displays /locations/performance.avg_ticket_byn — rename to
     .avg_payment_byn (semantics now: average sub-deal payment, not
     average per deal).
   - Displays /inventory/utilization.units — use avg_units.
   - Uses /locations/lifecycle.first_deal_date or .last_deal_date —
     rename to first_activity_date / last_activity_date.
   - Calls /orders/stats or /deals/list — remove or rewrite (the
     functionality is in /operations/funnel + /operations/timeline).

c) Find every place where you SUM /finance/revenue and /carnival/revenue
   together — that is a double-count. /finance/revenue already includes
   carnival rentals. /carnival/* is a separate pre-booking funnel from
   karn_brons; use its bookings/issued/returned counts and its
   payment-channel split, but DO NOT add its revenue to /finance/revenue.
   For realized carnival revenue, use /finance/pnl.revenue_carnival_byn.

d) For any UI that filters by office and uses delivery_yn=1 or office_id=0
   directly in the URL, switch to location=courier as the parameter.

e) For any UI that compares periods (YoY etc.), be aware that totals
   will SHIFT VS THE OLD API. Don't show old historical snapshots and
   new API responses side-by-side without re-pulling the historical
   data through the new API.

f) For any place that asserted "office 3 has 0 deals after 2022" — this
   is no longer strictly true. Pobediteley closed mid-2022 but residual
   cl_payment sub-deals continue to land on place=3 in 2023+ as old
   deals are paid off. Use "< 10000 BYN annual" or similar threshold
   rather than "== 0".

g) For any place that asserts "inventory size X for model Y in 2019" —
   the API now returns historical inventory. The number will be larger
   than today's catalog for many models because items have since been
   sold/lost.

h) Cached responses keyed on (endpoint, params) hash: BUST EVERYTHING for
   any analytics endpoint. The new params (include_carnival) plus the
   shape changes mean any prior cache entry is stale and likely wrong.

## Verification

After your changes:
- Pull /finance/pnl for 2019 full year. Expect revenue_byn ≈ 424232.
- Pull /operations/by-location for 2019-01-01..2019-12-31. Expect
  office_id=3 (Pobediteley) to appear with revenue ≈ 186k BYN.
- Pull /inventory/utilization for any past full year. Expect
  units_at_from, units_at_to, avg_units to be present, NOT `units`.

If any of these checks fail, the migration on your side is incomplete.

## Where to find more detail

Server repo: /home/dmitry/sites/tiktakby
- docs/mcp_api_migration_2026-05-14.md (this document on the server side)
- docs/mcp_server.md
- resources/openapi/mcp-v1.json
- tests/Feature/Mcp/LegacyParityTest.php — direct comparisons to legacy SQL
```
