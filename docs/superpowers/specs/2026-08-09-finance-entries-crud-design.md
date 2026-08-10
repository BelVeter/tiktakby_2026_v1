# Design: `/finance/entries` — CRUD for the income/expense ledger

**Date:** 2026-08-09 (rewritten 2026-08-10)
**Status:** Approved
**Supersedes:** the original bank-specific `POST /finance/expenses/bank-import` design — see "Why this was rewritten" below.

---

## Problem

`doh_rash` (the kassa/finance ledger — income and expenses) has **no write path** in the MCP API, and no row-level read path either. `GET /finance/expenses` and `GET /finance/cash-flow` return only aggregates.

That means an AI agent working with the business's finances cannot enter a payment, correct a mistyped one, or check what is already recorded — every such change requires a human to hand-run SQL against production. This spec closes that gap with a small, general CRUD surface.

The immediate driver is the monthly bank-statement reconciliation (`fy2025_bank_channel_gap` / `D-OPEN-FY2025`, documented in `docs/mcp_server.md` — bank-sourced taxes/rent/fees have been missing from `doh_rash` since 2025-01), but the endpoints are deliberately **not** bank-specific: statement parsing and matching happen locally in the calling agent, and the API just stores ledger rows.

## Why this was rewritten

The first version of this design was a single bank-shaped endpoint (`POST /finance/expenses/bank-import`) with `kassa`/`channel` hardcoded to `'bank'`, required `doc_n`/`beneficiary`/`ground` fields, and idempotency keyed on a `BANK#<doc_n>` marker parsed out of `info`.

That framing was inherited from an external draft request and never questioned. It was wrong: bank is just one value of the `kassa` column, expenses are also cash (`k1`, `k2`, `card`), and the bank-statement concepts (document number, beneficiary, payment ground) belong to the caller's parsing step, not to a ledger API. The rewrite drops all of it in favour of the ledger's own fields.

---

## Endpoints

All under `/api/mcp/v1/`, all behind the existing shared `mcp.token` Bearer middleware (no new credential — same decision as the rest of the MCP API).

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/finance/entries` | List rows with filters + pagination |
| `GET` | `/finance/entries/{id}` | One row by `dr_id` |
| `POST` | `/finance/entries` | Create one or many rows |
| `PATCH` | `/finance/entries/{id}` | Update one row |
| `DELETE` | `/finance/entries/{id}` | Delete one row (physical) |
| `GET` | `/finance/entries/history` | Read the change journal |

Route order matters: `/finance/entries/history` must be registered **before** `/finance/entries/{id}`, or `history` is swallowed as an id.

### `GET /finance/entries`

Filters (all optional, AND-combined): `from`, `to` (dates, on `acc_date`), `type1`, `type2`, `kassa`, `channel`, `dr_name_id`, `search` (LIKE on `info`).
Pagination: `per_page` (default 100, max 500), `page` (default 1).
Order: `acc_date DESC, dr_id DESC`.

Row shape — the ledger's own columns, plus resolved labels:
```json
{
  "dr_id": 33910,
  "date": "2026-07-06",
  "amount": 351.90,
  "type1": "rash",
  "type2": "of1_rent",
  "type2_name": "Аренда Машерова",
  "kassa": "bank",
  "channel": "bank",
  "info": "Аренда за июль",
  "dr_name_id": 0,
  "link_to": 0,
  "created_at": "2026-07-06T09:15:22Z",
  "created_by_id": 51,
  "created_by": "API"
}
```

`amount` is returned as a **positive magnitude** with the direction carried by `type1` — the same contract as writes (see Sign convention). `type2_name` resolves through `rash_items`/`doh_items`; `created_by` through `logpass.lp_fio`.

### `POST /finance/entries`

Body: `{"entries": [ {...}, ... ]}` — 1 to 200 items. Per-item result, following `PagesProductController::bulkUpdate`'s established pattern:

```json
{
  "data": [
    {"index": 0, "status": "created", "dr_id": 33910},
    {"index": 1, "status": "invalid", "dr_id": null, "errors": {"type2": ["..."]}}
  ],
  "meta": {"total_rows": 2, "summary": {"created": 1, "invalid": 1}}
}
```

One invalid item never blocks the rest of the batch. Batch-shape problems (`entries` missing, empty, or over 200) are request-level `422` and process nothing.

### `PATCH /finance/entries/{id}`

Partial update — only supplied fields change. At least one field required. `404` if the row does not exist. Writes an `update` journal entry with full before/after snapshots.

Changing `type1` re-normalizes the stored sign (see below).

### `DELETE /finance/entries/{id}`

**Physical** delete, matching what the legacy admin already does — reports and balances keep behaving exactly as before, with no deleted-row filtering to retrofit across legacy queries. A full JSON snapshot goes to the journal first, so the row is recoverable. `404` if not found.

A soft-delete flag was rejected: it would require a new column on `doh_rash`, which is precisely the kind of schema change this design avoids (see Schema impact).

### `GET /finance/entries/history`

Filters: `dr_id`, `action`, `from`, `to`; same pagination as the list endpoint. This is the recovery path — it holds enough to reconstruct a deleted or mis-edited row.

---

## Field semantics — what the ledger columns actually mean

The column names do not explain themselves, and getting them wrong produces rows that look valid but land in the wrong report. This model is reconstructed from the legacy author's own annotations in `bb/models/KassaOperation.php` and confirmed against live data; it must be reproduced in `docs/mcp_server.md` and in the OpenAPI field descriptions so a calling agent has it without reading this spec.

**Every entry answers four questions:**

| Question | Column | Values |
|---|---|---|
| Income or expense? | `type1` | `doh` = income, `rash` = expense |
| What kind? | `type2` | An article code from the dictionary matching `type1` |
| Where did it happen? | `channel` | Office number, `cur` (courier), or `bank` |
| Where did the money sit? | `kassa` | `k1`/`k2` (cash tills), `card` (acquiring), `bank` (bank account) |

- **`channel` — the point of origin.** Office numbers come from `offices.number WHERE type='office'` (today: `1` Литературная 22, `2` Ложинская, `3` Победителей 125 (closed), `4` склад). `cur` means a courier operation; `bank` means it went through the bank account.
- **`kassa` — the till or account.** `k1`/`k2` are the two physical cash registers, `card` is card acquiring, `bank` is the settlement account.
- **`type2` — the article.** Dictionary is chosen by `type1`: `rash_items.ri_code` for expenses (25 active codes: `zpl`, `of1_rent`, `pod_tax`, …), `doh_items.rd_code` for income (7 active codes: `bank_interest`, `prod_tovar`, …). A code from the wrong dictionary is invalid, not merely unusual.
- **`dr_name_id` — which employee** the operation concerns (`logpass_id`). Meaningful for salary and advances; the legacy per-person salary report groups by it.
- **`link_to`** — for till transfers, the paired operation's id; for advances, the linked advance operation. `0` when not applicable.
- **`date` → `acc_date` is the accounting date** — when the money actually moved — not when the record was created. Creation time is the separate `cr_time`. All period reports slice on `acc_date`.

**`channel` and `kassa` are not independent.** Live data shows a strict pairing, and the API enforces it:

| `kassa` | allowed `channel` |
|---|---|
| `bank` | `bank` only |
| `k1`, `k2`, `card` | an office number, or `cur` |

`channel='bank'` with a cash till (or an office channel with `kassa='bank'`) is a contradiction — money cannot be simultaneously in the bank account and in a drawer at Ложинская. Verified across all 19,606 `doh`/`rash` rows: `bank` pairs only with `bank` (4,847 expense + 171 income rows), and no cash till ever carries a `bank` channel.

## Write field contract

Validation is deliberately **stricter than the legacy admin**. The admin panel is used by people who know the business and can eyeball a mistake; an API is used by an agent that cannot. Rejecting an incomplete row costs one error response, while accepting one silently corrupts a financial report that nobody re-checks.

| Field | Rule |
|---|---|
| `type1` | **required**; `rash` \| `doh` only |
| `type2` | **required**; must exist with `is_active=1` in `rash_items.ri_code` (`type1=rash`) or `doh_items.rd_code` (`type1=doh`) |
| `date` | **required**; `YYYY-MM-DD`, real calendar date |
| `amount` | **required**; numeric, strictly positive, ≤ 2 decimals, fits `decimal(11,2)` |
| `kassa` | **required**; `k1` \| `k2` \| `bank` \| `card` |
| `channel` | **required**; an office number from `offices WHERE type='office'`, or `cur`, or `bank` — resolved live, not hardcoded, so a newly opened office works without a code change |
| `channel` × `kassa` | **required to be a valid pair** per the table above |
| `info` | **required, non-empty** (see below) |
| `dr_name_id` | **required when `type2` ∈ {`zpl`, `avans`}**; otherwise optional, default `0`. When supplied it must reference an existing `logpass` row |
| `link_to` | optional, integer, default `0` |

**Why `info` is required even though legacy allows it empty.** 20% of existing expense rows (3,508) and 13% of income rows have an empty `info`. Those are human-entered rows whose context lived in someone's head. A row entered by an agent with no description is unauditable after the fact — the owner opens the kassa journal, sees "−351.90, of1_rent, API" and has no way to tell what it was. The laxity is legacy debt and is not inherited here.

**Why `dr_name_id` is conditionally required.** For `zpl` it is filled on 5,876 of 5,912 rows (99.4%) and for `avans` on 553 of 568 (97.4%) — it is de facto mandatory in practice, and the legacy per-employee salary report (`bb/rash_analysis.php`, `bb/doh-rash.php`) silently produces an unattributed row without it. Requiring it turns a silent reporting hole into an upfront error.

**On `PATCH`** every field is optional, but each supplied field obeys the same rule, and the resulting row must still satisfy the cross-field constraints (`channel`×`kassa` pairing, and the `dr_name_id` requirement if the update changes `type2` to `zpl`/`avans`) — validation runs against the merged post-update row, not against the patch body alone.

**Sign convention.** Requests always carry a positive magnitude; the server stores `-abs()` for `type1='rash'` and `+abs()` for `type1='doh'`, matching the live data. This lives in exactly one place in the code and is covered by tests. A signed-input contract was rejected: a wrong sign would silently corrupt `SUM(amount)` in `/finance/pnl`, `/finance/expenses` and the legacy `bb/reports.php` with no error anywhere.

**`type1` is whitelisted, not open.** `doh_rash` also stores `shift_plus`/`shift_minus` — transfers between tills. Those are created as **linked pairs** with mutual `link_to` references, so a single-row API insert would produce a half-transfer and corrupt till balances. Out of scope by construction; a separate paired-transfer endpoint can be added later if needed.

**`cr_who_id` is server-set**, always the dedicated `api_system` user (see below). `cr_time` is server-set. Clients cannot supply either — the validator does not read them, so there is no override path.

**`kassa` whitelist** is a fixed list (`k1`, `k2`, `bank`, `card`) — these are physical tills and accounts, not a growing catalog. **`channel`** resolves office numbers live from the `offices` table so a newly opened office needs no code change. `doh_rash` also contains 4 rows with the junk value `'HZ'` in both columns; those are excluded deliberately rather than blessed as valid input.

---

## Author attribution: the `api_system` user

Every row written through this API is authored by a dedicated `logpass` row (`log='api_system'`, `lp_fio='API'`, `active=0`, `level=-1`), seeded by migration, resolved once by login name and cached — never a hardcoded or guessed numeric id. The legacy admin (`bb/doh-rash.php`, `bb/rash_analysis.php`) displays the author name, so API-entered rows visibly read as "API" instead of being attributed to a real employee.

**It cannot be logged into.** Verified against the live login path (`bb/models/User.php:146`), whose query requires `active > 0`. The only other `logpass` login query in the codebase, `bb/one_login.php:60`, omits that check — but that file is dead code: nothing includes it (0 references), and a direct HTTP POST to it dies immediately on an undefined `$mysqli` without setting a session or the `tt_is_logged_in` cookie (verified live). Its `pass` is additionally a random 32-hex value that is never displayed.

---

## Change journal

New table `doh_rash_history`:

| Column | Purpose |
|---|---|
| `id` | PK |
| `dr_id` | the affected `doh_rash` row |
| `action` | `update` \| `delete` |
| `before_json` | full row snapshot before the change |
| `after_json` | full row snapshot after (null for `delete`) |
| `actor_user_id` | `logpass_id` of the author (the `api_system` user) |
| `source` | `mcp_api` |
| `ip` | request IP |
| `created_at` | timestamp |

**Only `update` and `delete` are journalled.** Inserts are already attributable: the row itself exists and carries `cr_who_id` = the API user and `cr_time`. Journalling an insert would duplicate data that is already in `doh_rash`.

Full-snapshot (rather than per-field) rows keep restore simple: one journal row contains everything needed to rebuild a deleted entry or revert an edit.

**Scope: API-originated changes only.** The legacy admin keeps its own separate file-based deletion journal (`bb/logs/YYYY-MM-DD_dohrash`, JSON lines with `del_time`/`del_who_id`); `bb/` is not modified by this work. The two journals coexist — a change made by a human in the admin panel will not appear in `doh_rash_history`, and that is the accepted boundary.

`mcp_api_log` (from the `mcp.audit` middleware) does **not** cover this need: it records only query parameters, never request bodies, so a `PATCH` or `DELETE` leaves no recoverable trace there. This is the same reason `mcp_content_versions` exists for SEO content — `doh_rash_history` follows that established precedent.

---

## Schema impact

**No `ALTER TABLE` on any existing table.** `doh_rash`, `rash_items` and `doh_items` are untouched — all twelve `doh_rash` columns already cover the full CRUD surface. Only one new table (`doh_rash_history`) is added.

This is a hard constraint, not a preference: `docs/db_notes.md` records that positional `INSERT ... VALUES` statements throughout the legacy admin break when columns are added to these tables. Verified against production schema via read-only SSH — local dev and production `doh_rash` are identical.

---

## Out of scope

- Bank-statement parsing, matching and deduplication — done locally by the calling agent
- `shift_plus`/`shift_minus` till transfers (paired rows, see above)
- Soft delete (would require a new `doh_rash` column)
- Journalling changes made through the legacy admin panel
- Any modification to `bb/` legacy code
