# MCP Analytics API — query methodology

Locked 2026-05-14 — reproduces legacy admin reports `/bb/reports.php`, `/bb/sales_breakdown.php`, `/bb/dohrash2.php`, `/bb/cat_analysis.php`:

- Revenue = `SUM(r_paid + delivery_paid)` over `UNION(rent_sub_deals_act, rent_sub_deals_arch)` filtered by `acc_date` (accounting date — when payment landed). NOT by deal `cr_time`.
- Deal/return counts read `UNION(rent_deals_act, rent_deals_arch)`. `_act` holds ~430 currently-open deals; querying only `_arch` misses them.
- Office attribution uses `sub_deal.place + delivery_yn` (per-payment), NOT `deal.first_rent_place` (per-deal). office_id=0 = synthetic "Курьер" pseudo-office.
- Carnival items: detected via `tovar_rent_cat.cat_type=1`. Endpoints accept `include_carnival` (default true). `/finance/pnl` splits into `revenue_carnival_byn` + `revenue_non_carnival_byn`.
- Inventory at date X: `tovar_rent_items` (buy_date ≤ X) + `tovar_rent_items_arch` (buy_date ≤ X AND arch_time ≥ X). Used by `/inventory/utilization` as historical denominator.
- ⚠️ DO NOT sum `/finance/revenue` + `/carnival/revenue` — `/carnival/*` reads `karn_brons` (pre-booking system), `/finance/revenue` already includes carnival items as rentals. Double-count risk.
- Many-to-many trap: never join `subrazdel_category × razdel_subrazdel` directly in a query that also `SUM`s payments — that chain inflates sums M×N. Use `BaseController::itemsInRazdelSubquery()` instead.
- Legacy parity is enforced by `tests/Feature/Mcp/LegacyParityTest.php` — DO NOT remove or weaken those assertions without coordinating with the analytics workspace.
