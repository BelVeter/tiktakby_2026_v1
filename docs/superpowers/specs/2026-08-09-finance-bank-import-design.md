# Design: POST /api/mcp/v1/finance/bank-import

**Date:** 2026-08-09
**Status:** Approved
**Context:** Monthly bank-statement reconciliation (analytics workspace at `/home/dmitry/Documents/прокат/`) currently requires a human to hand-run SQL against production to close the `fy2025_bank_channel_gap` (`D-OPEN-FY2025`, documented in `docs/mcp_server.md`) — bank-sourced taxes/rent/fees have been missing from `doh_rash` since 2025-01. This endpoint gives the analytics workspace a programmatic, idempotent write path through the existing MCP API instead. Going forward this is expected to become the **primary** way bank income/expense lines enter `doh_rash`, not an occasional backfill tool.

---

## Problem

`doh_rash` (the kassa/finance ledger) has no write path in the MCP API — only reads (`/finance/pnl`, `/finance/expenses`, etc.). Reconciling a bank statement against it today means asking the site's AI agent to hand-run SQL on production each month. This endpoint closes that gap for bank-channel transactions specifically (`kassa='bank'`), both expenses and income.

---

## Endpoint

```
POST /api/mcp/v1/finance/bank-import
```

Auth: same shared `mcp.token` Bearer middleware as the rest of the MCP API (`MCP_API_TOKEN`) — no new credential. This was a deliberate simplicity-over-isolation tradeoff: a leaked token already grants redirect/SEO/SMS write access today, and splitting out a finance-only token was judged not worth the extra operational surface for now.

Response: standard `{data, meta}` envelope with per-item status, same family as `PagesProductController::bulkUpdate`.

### Request

```json
{
  "dry_run": false,
  "expenses": [
    {
      "type1": "rash",
      "doc_n": "97",
      "date": "2026-07-06",
      "amount": 351.90,
      "type2": "of1_rent",
      "beneficiary": "ТС ЖИЛОГО ДОМА 22 ПО УЛИЦЕ ЛИТЕРАТУРНАЯ",
      "ground": "ОПЛАТА АРЕНДЫ ЗА ИЮЛЬ 2026 ГОДА...",
      "note": null
    },
    {
      "type1": "doh",
      "doc_n": "55",
      "date": "2026-07-10",
      "amount": 1200.00,
      "type2": "bank_interest",
      "beneficiary": "ОАО \"СБЕР БАНК\"",
      "ground": "...",
      "note": null
    }
  ]
}
```

**Field validation (per item):**

| Field | Rule |
|---|---|
| `type1` | required, **whitelist `['rash','doh']` only** — no `shift_plus`/`shift_minus`/other internal-transfer types (see "Why type1 is whitelisted" below) |
| `doc_n` | required, string, max 64 |
| `date` | required, `YYYY-MM-DD`, must be a real calendar date |
| `amount` | required, numeric, **strictly positive** (magnitude only — see sign convention below), max 2 decimal places, must fit `decimal(11,2)` |
| `type2` | required, string; must exist and be active in the dictionary matching `type1` (see below) |
| `beneficiary` | required, string, max 500 |
| `ground` | required, string (bank payment purpose text — `doh_rash.info` is `TEXT`, no length limit in practice) |
| `note` | optional, nullable, string |

Batch cap: **200 items**, matching the `MAX_BULK_ITEMS` convention used by `RedirectsController::bulk` / `PagesProductController::bulkUpdate`. Empty `expenses` array → 422.

**Validation failure levels — two tiers, not one:**
- **Request-level (422, whole batch rejected):** `expenses` missing/not an array/empty (`min:1`), or over 200 items (`max:200`). Nothing is processed.
- **Per-item (200 response, `status: "invalid"` for that item only):** every field rule in the table below (`type1`, `doc_n`, `date`, `amount`, `type2`, `beneficiary`, `ground` — including a non-numeric or negative `amount`, an unknown `type2`, etc.). One bad item never blocks the rest of the batch — matches `PagesProductController::bulkUpdate`, not `RedirectsController::bulk`'s all-or-nothing per-batch validation.

**Why `type1` is whitelisted, not left open:** `doh_rash` also uses `type1 IN ('shift_plus','shift_minus')` for internal till↔bank transfers — confirmed in production data (3 `shift_minus`/`type2='bank'` rows exist at `kassa='bank'`). This endpoint must never be able to create those; it only ever represents real external bank-statement lines.

**`type2` dictionary selection:**
- `type1='rash'` → validate against `rash_items.ri_code WHERE is_active=1`
- `type1='doh'` → validate against `doh_items.rd_code WHERE is_active=1`

Note: `bank_yn` on both tables was considered as an extra filter and rejected — it does not reliably mark "usable for bank channel." Production data shows plenty of `bank_yn=0` codes routinely used at `kassa='bank'` (`zpl` 1012 rows, `connect` 425, `op_rash` 309, `tovar` 305, `adv` 96 historically), and `doh_items.bank_yn` is `0` on every single row including `bank_interest`. Using `bank_yn` as a gate would have incorrectly rejected real, common categories.

### Response

```json
{
  "data": [
    {"index": 0, "doc_n": "97", "status": "inserted", "dr_id": 33910},
    {"index": 1, "doc_n": "55", "status": "inserted", "dr_id": 33911},
    {"index": 2, "doc_n": "94", "status": "duplicate", "dr_id": 33847},
    {"index": 3, "doc_n": "", "status": "invalid", "errors": {"type2": ["..."]}}
  ],
  "meta": {
    "total_rows": 4,
    "dry_run": false,
    "summary": {"inserted": 2, "duplicate": 1, "invalid": 1}
  }
}
```

`status` values:
- `inserted` — new row written (`would_insert` instead, when `dry_run=true` — nothing written)
- `duplicate` — idempotency key already exists; `dr_id` references the existing row, no write
- `invalid` — validation failed; `errors` holds per-field messages (Laravel validator shape)

A batch with a mix of valid/invalid/duplicate items partially succeeds — one bad item does not block the others, matching `PagesProductController::bulkUpdate`'s behavior.

---

## Write logic

**Sign convention** — request `amount` is always a positive magnitude (natural external contract: "this cost/paid 351.90 BYN"). The controller converts based on `type1`, in exactly one place:
- `type1='rash'` → store `amount = -abs(input)`
- `type1='doh'` → store `amount = +abs(input)`

This matches live data conventions for both types and removes the ambiguity a sign-inferred design would have introduced.

**Fixed fields, not client-controlled** — every row this endpoint writes gets:
```
channel='bank', kassa='bank', link_to=0, dr_name_id=0,
cr_time=now(), cr_who_id=<API system user, see below>
```
If a request body includes `channel`, `kassa`, or `cr_who_id`, they are ignored — the validator doesn't read them, so there's no path for a caller to override them.

`info` is built as:
```
[AI] BANK#<doc_n> <beneficiary>: <ground>[ <note>]
```
— matching the convention already proven in the May 2026 reconciliation cycle (`04_analytics/data/bank/README.md`), so existing/future reconciliation tooling that scans for the `BANK#` prefix keeps working unchanged.

**`dr_name_id` stays `0` always.** This is a deliberate, permanent limitation, not a TODO: `dr_name_id` is a real, actively-used FK (`bb/doh-rash.php`, `bb/rash_analysis.php` group/display salary by it), but bank salary payments arrive as one lump Sber transfer per pay run with no reliable per-employee split available from the bank statement. The existing per-employee salary report will show these `zpl` rows unattributed — same as today's equivalent hand-entered case. No optional `employee_id` field is added; scope stays to what the bank data can actually support.

**Idempotency check**, one query per item before insert:
```sql
SELECT dr_id FROM doh_rash
WHERE kassa='bank' AND type1 = ? AND ABS(amount) = ?
  AND acc_date BETWEEN ? AND ?          -- ±2 days around `date`, unix timestamps
  AND info LIKE '%BANK#<doc_n> %'
```
Adapted from the May-cycle logic (`insert_doh_rash_2025_2026.sql`), with two changes: `type1` added to remove any theoretical doc_n collision between a debit and credit line, and the `LIKE` pattern gets a **leading `%`**. The May script's rows store `info` as plain `BANK#<doc_n> ...` (no prefix existed yet), so its unprefixed `LIKE 'BANK#<doc_n> %'` was correct for what it was checking against. This design adds the `[AI] ` prefix (below) — an unprefixed, start-anchored `LIKE` would then never match a row this endpoint itself just inserted, silently defeating the entire dedup mechanism on every resubmit. Confirmed this exact bug is already present in the draft `PROPOSED_insert_2026-07.sql` (checks `info LIKE 'BANK#90504 %'` while inserting `'[AI] BANK#90504 ...'` — would duplicate on rerun). The leading-`%` version matches both the old unprefixed rows and the new `[AI]`-prefixed ones, so dedup works against the full history, not just future API-authored rows.

Items are processed sequentially, each insert committed immediately (no batch-wide transaction) — so if two items in the same request share a dedup key, the first's insert is already visible when the second's `SELECT` runs, and the second correctly comes back `duplicate` against the first's new `dr_id`. This also means a batch can partially succeed if the request is interrupted mid-way — acceptable given every write is independently idempotent and safe to retry.

**`date` → `acc_date` conversion:** interpreted as Minsk-local (`Europe/Minsk`) midnight, matching the existing convention used throughout `bb/` and the May-cycle import SQL — not UTC midnight, which would shift the unix timestamp by 2-3 hours and could push a transaction just outside the ±2-day dedup window at month boundaries.

**`dry_run`** (optional, default `false`) — runs the full validation + idempotency-check path and reports what *would* happen (`would_insert` instead of `inserted`), with zero writes. Cheap to support (same code path, skip the `INSERT`) and gives a future unattended caller (analytics workspace running this on a schedule) a self-check step before committing, without requiring any workflow change today.

---

## `cr_who_id` — new "API" system user

No existing `logpass` row represents "the API" — reusing a real employee's id risks misattribution (the external draft that prompted this design had guessed `id=9`, which is Света/Sveta, an **inactive** account — confirmed wrong via direct query).

One-time migration seeds:
```
logpass: log='api_system', lp_fio='API', active=0 (cannot log in), level=-1
```
Its `logpass_id` is read into `config('mcp.php')` as `finance_bank_import_author_id`, resolved once, not guessed per request. Every row this endpoint writes carries that id, so `bb/doh-rash.php` shows "API" as the author instead of attributing to a real person.

---

## Testing — exhaustive matrix

| # | Case | Input | Expected |
|---|---|---|---|
| 1 | Valid rash, tax category | `type1=rash, type2=pod_tax, amount=101.07` | `inserted`, DB `amount=-101.07` |
| 2 | Valid rash, non-bank_yn code | `type1=rash, type2=zpl` | `inserted` (bank_yn=0 does not block it) |
| 3 | Valid doh | `type1=doh, type2=bank_interest, amount=12.40` | `inserted`, DB `amount=+12.40` |
| 4 | type2 wrong dictionary | `type1=rash, type2=bank_interest` (doh-only code) | `invalid` |
| 5 | type2 inactive | `type2` exists but `is_active=0` | `invalid` |
| 6 | type1 = internal transfer | `type1=shift_plus` | `invalid` — outside whitelist |
| 7 | type1 missing/garbage | `type1=""`, omitted, `type1="expense"` | `invalid` |
| 8 | Negative amount | `amount=-351.90` | `invalid` — not silently flipped |
| 9 | Zero amount | `amount=0` | `invalid` |
| 10 | Non-numeric amount | `amount="abc"` | `invalid` |
| 11 | Amount precision/overflow | `amount=123.456`; `amount=999999999.99` | `invalid` |
| 12 | Missing required field | omit each of `doc_n`/`date`/`beneficiary`/`ground` in turn | `invalid`, field named in `errors` |
| 13 | Malformed date | `2026-13-01`; `2026-02-30`; `07/06/2026` | `invalid` |
| 14 | `doc_n` too long | 65-char string | `invalid` |
| 15 | Duplicate within same batch | two items, same `doc_n`+`amount`+`date` | 1st `inserted`, 2nd `duplicate` referencing 1st's `dr_id` |
| 16 | Idempotent resubmit, separate call | same item POSTed twice, two requests | 2nd call: `duplicate`, no new row |
| 17 | Dedup window boundary — inside | resubmit with `date` shifted 2 days, same `doc_n`+`amount` | `duplicate` |
| 18 | Dedup window boundary — outside | resubmit with `date` shifted 3 days | new distinct row, not duplicate |
| 19 | `dry_run=true`, valid batch | any valid batch | `would_insert` per item, zero DB writes |
| 20 | `dry_run=true` then real submit | dry-run, then same batch with `dry_run=false` | 2nd call inserts identically to prediction |
| 21 | Batch size boundary | 200 items / 201 items | 200 accepted; 201 → 422 |
| 22 | Mixed batch | valid rash + valid doh + invalid + duplicate together | valid ones insert; others fail independently |
| 23 | Client tries to override fixed fields | body includes `channel`, `kassa`, `cr_who_id` | ignored; row still gets `channel='bank'`, `kassa='bank'`, `cr_who_id=<API user>` |
| 24 | `info` format | with and without `note` | `[AI] BANK#<doc_n> <beneficiary>: <ground>[ <note>]` |
| 25 | Auth | missing/invalid Bearer token | 401, no DB access |
| 26 | Empty batch | `"expenses": []` | 422 |

---

## Files to change

1. `app/Http/Controllers/Mcp/FinanceController.php` (or a new `FinanceBankImportController` if it grows large) — `bankImport()` method
2. `routes/api.php` — `Route::post('finance/bank-import', ...)`
3. `database/migrations/` — seed the `api_system` `logpass` row
4. `config/mcp.php` — `finance_bank_import_author_id`
5. `docs/mcp_server.md` — document the new endpoint
6. `resources/openapi/mcp-v1.json` — add OpenAPI path entry
7. `docs/db_notes.md` — one-line note on the permanent `dr_name_id=0` limitation for API-imported `zpl` rows
8. `tests/Feature/Mcp/FinanceBankImportTest.php` — covers the full matrix above

---

## Out of scope

- Actually running the July 2026 import — a separate AI-agent session handles that data-entry pass; this design covers the endpoint only
- Non-bank channels (`kassa='k1'/'k2'`, cash entries) — this endpoint is bank-statement-specific by construction
- Per-employee salary attribution (`dr_name_id`) for API-imported `zpl` rows — permanent limitation, not deferred work
- A dedicated finance-write auth scope/token — reuses the existing shared `MCP_API_TOKEN` (see Auth section)
- UI/dashboard for reviewing imported rows — `bb/doh-rash.php` already shows them (with `[AI]`-prefixed `info` and "API" as author)
