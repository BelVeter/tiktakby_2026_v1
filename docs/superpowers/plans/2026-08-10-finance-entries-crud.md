# Finance Entries CRUD Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a general read+write CRUD surface over the `doh_rash` income/expense ledger (`/api/mcp/v1/finance/entries`), so an AI agent can list, create, correct and delete ledger rows — with update/delete journalled for recovery.

**Architecture:** New `FinanceEntriesController` holding all six endpoints (kept out of the read-only aggregate-reporting `FinanceController`, which is already ~590 lines and has a different concern: period aggregation with legacy-parity guarantees). A new `doh_rash_history` table records before/after snapshots for updates and deletes. No existing table is altered.

**Tech Stack:** Laravel 8.75, PHP 7.4, MariaDB 10.6 (local: Docker Compose services `app`/`db`), PHPUnit feature tests against the real dev DB.

**Spec:** `docs/superpowers/specs/2026-08-09-finance-entries-crud-design.md` — read it first; it carries the rationale (sign convention, why `type1` is whitelisted, why delete is physical, why only update/delete are journalled).

## Global Constraints

- **No `ALTER TABLE` on `doh_rash`, `rash_items`, `doh_items`.** Positional `INSERT ... VALUES` across the legacy admin breaks when columns are added to these tables (`docs/db_notes.md`). Only the new `doh_rash_history` table may be created.
- **No route closures** — `routes/api.php` uses `[Controller::class, 'method']` only; closures break `route:cache` on production.
- **`bb/` legacy code is not modified by this work.** All DB access goes through Laravel's `DB::table()`, never `bb\classes\*`.
- **Sign convention:** requests carry a positive magnitude; storage is `-abs()` for `type1='rash'`, `+abs()` for `type1='doh'`. Reads return positive magnitude + `type1`. Implemented in exactly one place.
- **`type1` whitelist is `rash`|`doh` only** — never `shift_plus`/`shift_minus` (paired till transfers, would corrupt balances as single rows).
- **`kassa` whitelist:** `k1`, `k2`, `bank`, `card` (fixed list). **`channel`:** an office number resolved live from `offices WHERE type='office'`, or `cur`, or `bank` — not a hardcoded number list. (The 4 live rows with `'HZ'` in both columns are junk and deliberately not valid input.)
- **`channel` × `kassa` must be a valid pair:** `kassa='bank'` ⟺ `channel='bank'`; `kassa` ∈ {`k1`,`k2`,`card`} requires `channel` to be an office number or `cur`. Enforced on create and on the merged post-update row.
- **All business fields are required on create** — `type1`, `type2`, `date`, `amount`, `kassa`, `channel`, `info` (non-empty, max 2000 chars), plus `dr_name_id` when `type2` ∈ {`zpl`,`avans`}. Stricter than the legacy admin, on purpose (see the spec's rationale).
- **`info` must have an explicit max length (2000).** `doh_rash.info` is `TEXT` and production `sql_mode` is empty, so an over-long value is silently truncated instead of rejected — a write that appears to succeed while losing data. Same guard, same reason, as `PagesProductController::MAX_TEXT_BYTES`.
- **`cr_who_id` / `cr_time` are server-set**, never client-supplied. `cr_who_id` = the `api_system` `logpass` row, resolved by login name and cached — never a hardcoded id.
- **Route order:** `/finance/entries/history` must be registered **before** `/finance/entries/{id}`.
- **Testing:** use `DatabaseTransactions` (project convention for MCP feature tests). `doh_rash` is MyISAM so rollback is a no-op for it — every test that writes must clean up its own rows explicitly in `setUp()`/`tearDown()`.
- **Test gating:** run ONE test class per `php artisan test` invocation. Running the whole `tests/Feature/Mcp/` directory in one process exhausts PHP's 128M memory limit partway through — a known environment limitation, not a code defect. PHPUnit's CLI also silently runs only the first file if given several.

---

## File Map

| Action | File | Responsibility |
|--------|------|-----------------|
| Create | `database/migrations/2026_08_10_120000_create_doh_rash_history_table.php` | The change journal table |
| Create | `app/Http/Controllers/Mcp/FinanceEntriesController.php` | All six endpoints + shared validation/journalling helpers |
| Create | `tests/Feature/Mcp/FinanceEntriesReadTest.php` | GET list / GET one / GET history |
| Create | `tests/Feature/Mcp/FinanceEntriesWriteTest.php` | POST / PATCH / DELETE + journalling |
| Modify | `routes/api.php` | Register the six routes |
| Modify | `docs/mcp_server.md` | Document the endpoints |
| Modify | `resources/openapi/mcp-v1.json` | Add paths + schemas, bump `info.version` |
| Modify | `config/mcp.php` | Bump `version` (currently `2.2.0`, drifted from the spec's `2.3.0`) |
| Modify | `tests/Feature/Mcp/SpecRuntimeParityTest.php` | Update the hardcoded version assertion |
| Already done | `database/migrations/2026_08_09_120000_seed_api_system_logpass_user.php` | `api_system` author row (commit `c8d29a7`, kept from the superseded plan) |

---

### Task 1: Migration — `doh_rash_history` table

**Files:**
- Create: `database/migrations/2026_08_10_120000_create_doh_rash_history_table.php`

**Interfaces:**
- Produces: table `doh_rash_history` with columns `id, dr_id, action, before_json, after_json, actor_user_id, source, ip, created_at`. Task 5 writes to it; Task 3 reads it.

- [ ] **Step 1.1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change journal for doh_rash rows written through the MCP API.
 *
 * Only `update` and `delete` are recorded: an insert is already attributable
 * from the doh_rash row itself (cr_who_id = the api_system user, plus cr_time),
 * so journalling it would duplicate data that already exists.
 *
 * Snapshots are whole-row rather than per-field so a delete can be replayed
 * from a single journal row. mcp_api_log (the mcp.audit middleware) cannot
 * serve this purpose — it stores query parameters only, never request bodies.
 *
 * Scope is API-originated changes only; the legacy admin keeps its own
 * file-based deletion log at bb/logs/YYYY-MM-DD_dohrash.
 */
class CreateDohRashHistoryTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doh_rash_history')) {
            return;
        }

        Schema::create('doh_rash_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('dr_id');
            $table->enum('action', ['update', 'delete']);
            $table->text('before_json');
            $table->text('after_json')->nullable();
            $table->integer('actor_user_id')->nullable();
            $table->string('source', 32)->default('mcp_api');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('dr_id', 'idx_drh_dr_id');
            $table->index('created_at', 'idx_drh_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doh_rash_history');
    }
}
```

- [ ] **Step 1.2: Run it**

```bash
cd /home/dmitry/sites/tiktakby
docker compose exec app php artisan migrate --path=database/migrations/2026_08_10_120000_create_doh_rash_history_table.php
```

Expected: `Migrating:` then `Migrated:` with no errors.

- [ ] **Step 1.3: Verify the schema**

```bash
docker compose exec -T db mysql -utiktakby_tiktak -pVai7evahch tiktakby_tiktak -e "SHOW CREATE TABLE doh_rash_history\G"
```

Expected: all nine columns present, `action` is `enum('update','delete')`, both indexes exist.

- [ ] **Step 1.4: Confirm no existing table was touched**

```bash
docker compose exec -T db mysql -utiktakby_tiktak -pVai7evahch tiktakby_tiktak -e "SHOW COLUMNS FROM doh_rash;"
```

Expected: exactly the 12 original columns — `dr_id, acc_date, amount, type1, type2, channel, kassa, link_to, info, cr_time, cr_who_id, dr_name_id`. Nothing added.

- [ ] **Step 1.5: Commit**

```bash
git add database/migrations/2026_08_10_120000_create_doh_rash_history_table.php
git commit -m "Add doh_rash_history table for API change journal"
```

---

### Task 2: Read-endpoint tests (RED)

**Files:**
- Create: `tests/Feature/Mcp/FinanceEntriesReadTest.php`

**Interfaces:**
- Consumes: `McpTestCase::mcp(string $path, array $query = [])` (GET with Bearer token), `McpTestCase::assertRequiresToken()`, `McpTestCase::assertEnvelope()`.
- Produces: the failing spec for `GET /finance/entries`, `GET /finance/entries/{id}`, `GET /finance/entries/history`.

Write tests covering, each as its own method:

1. `GET /finance/entries` returns the standard envelope and a `data` array
2. Row shape contains exactly: `dr_id, date, amount, type1, type2, type2_name, kassa, channel, info, dr_name_id, link_to, created_at, created_by_id, created_by`
3. `amount` is always returned **positive** even for `rash` rows (which are stored negative) — insert a known `rash` fixture row with `amount = -123.45` and assert the API returns `123.45` with `type1='rash'`
4. `date` is rendered `YYYY-MM-DD` from the `acc_date` unix timestamp, interpreted in `Europe/Minsk`
5. `from`/`to` filter by `acc_date` — fixture rows inside and outside the window, only the inside ones returned
6. `type1` filter returns only matching rows
7. `type2` filter returns only matching rows
8. `kassa` filter returns only matching rows
9. `channel` filter returns only matching rows
10. `search` filter does a LIKE match on `info`
11. Two filters combine as AND (e.g. `type1=rash` + `kassa=bank`)
12. `per_page` caps the row count; `page=2` returns a different, non-overlapping set
13. `per_page` above 500 is rejected `422`
14. `meta.total_rows` reflects the filtered total, not the page size
15. Ordering is `acc_date DESC` (assert descending across the returned page)
16. `GET /finance/entries/{id}` returns the single matching row with the same shape
17. `GET /finance/entries/{id}` returns `404` for an unknown id
18. `GET /finance/entries/history` returns the envelope and an array (may be empty at this point)
19. `GET /finance/entries/history?dr_id=` filters by row id
20. All three endpoints require the Bearer token (`401` without)

Fixture rows must be created in `setUp()` with a recognizable `info` marker (e.g. prefix `TESTENTRY-`) and deleted in both `setUp()` and `tearDown()` — `doh_rash` is MyISAM, so `DatabaseTransactions` will not roll them back.

- [ ] **Step 2.1: Write the test file**
- [ ] **Step 2.2: Run it and confirm RED**

```bash
docker compose exec app php artisan test tests/Feature/Mcp/FinanceEntriesReadTest.php
```

Expected: all tests fail. Note that in this app an unregistered **GET** path returns `404` via the global fallback route, while an unregistered **POST** returns `405` — both are pre-existing app-wide behavior, not a defect.

- [ ] **Step 2.3: Commit**

```bash
git add tests/Feature/Mcp/FinanceEntriesReadTest.php
git commit -m "Add failing tests for finance entries read endpoints"
```

---

### Task 3: Implement the read endpoints (GREEN)

**Files:**
- Create: `app/Http/Controllers/Mcp/FinanceEntriesController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `BaseController::envelope()`, `BaseController::cacheRemember()`, `BaseController::TTL_META`.
- Produces: `index()`, `show(int $id)`, `history()` on `FinanceEntriesController`; the row-formatting helper `formatRow()` and the `type2` dictionary lookup that Task 5 reuses.

Implement so Task 2's tests pass:

- `index(Request $request)` — validated filters, pagination, `acc_date DESC, dr_id DESC` ordering, `meta.total_rows` = filtered count.
- `show(Request $request, int $id)` — single row or `404` in the standard MCP error shape.
- `history(Request $request)` — journal rows with `dr_id`/`action`/`from`/`to` filters and the same pagination.
- `formatRow()` — maps a DB row to the response shape, converting `acc_date` → `date` (Minsk), `amount` → positive magnitude, and resolving `type2_name` / `created_by`.

Route registration (note the ordering constraint):
```php
Route::get('finance/entries',           [FinanceEntriesController::class, 'index'])->name('finance.entries.index');
Route::get('finance/entries/history',   [FinanceEntriesController::class, 'history'])->name('finance.entries.history');
Route::get('finance/entries/{id}',      [FinanceEntriesController::class, 'show'])->name('finance.entries.show')->where('id', '[0-9]+');
```

Resolve `type2_name` and `created_by` with small cached lookup maps (`TTL_META`) rather than a per-row join.

- [ ] **Step 3.1: Write the controller's read half + register the three GET routes**
- [ ] **Step 3.2: Run the read tests until green**

```bash
docker compose exec app php artisan test tests/Feature/Mcp/FinanceEntriesReadTest.php
```

Expected: all pass, output pristine.

- [ ] **Step 3.3: Commit**

```bash
git add app/Http/Controllers/Mcp/FinanceEntriesController.php routes/api.php
git commit -m "Implement finance entries read endpoints"
```

---

### Task 4: Write-endpoint tests (RED)

**Files:**
- Create: `tests/Feature/Mcp/FinanceEntriesWriteTest.php`

**Interfaces:**
- Consumes: `McpTestCase::postMcp()`, plus `patchJson`/`deleteJson` with an explicit `Authorization` header (see `RedirectsTest` for the established pattern).

Cover, each as its own method:

**Create — happy paths**
1. `POST` a valid `rash` row → `created`; DB `amount` is **negative**, `type1='rash'`
2. `POST` a valid `doh` row → `created`; DB `amount` is **positive**
3. `POST` a batch of 3 valid rows → three `created`, three distinct `dr_id`s
4. Server-set fields: `cr_who_id` equals the `api_system` `logpass_id`, `cr_time` is non-zero
5. Client cannot override server-set fields — send `cr_who_id`, `cr_time` in the body and assert the stored row ignores them
6. `dr_name_id` defaults to `0` when omitted and round-trips when supplied; `link_to` defaults to `0` when omitted, and an explicit `link_to: 0` is likewise accepted — but any *non-zero* `link_to` is rejected (added after the final whole-branch review found it arms a cascade-delete in the legacy admin's `bb/doh-rash.php`; see the design spec's `link_to` section)

**Create — validation (each → per-item `invalid`, HTTP 200)**
7. `type1` outside the whitelist (`shift_plus`) → invalid
8. `type1` missing / empty / garbage → invalid
9. `type2` from the wrong dictionary (a `doh_items` code sent with `type1=rash`) → invalid
10. `type2` that is `is_active=0` → invalid
11. `amount` negative → invalid (not silently flipped)
12. `amount` zero → invalid
13. `amount` non-numeric → invalid
14. `amount` with 3 decimals, and one exceeding `decimal(11,2)` → invalid
15. `kassa` outside the whitelist (incl. `'HZ'`) → invalid
16. `channel` outside the allowed set (incl. `'HZ'`, and a nonexistent office number like `99`) → invalid
17. `date` malformed (`2026-13-01`, `2026-02-30`, `07/06/2026`) → invalid
18. Missing required field (`type1`/`type2`/`date`/`amount`/`kassa`/`channel`/`info`, each in turn) → invalid, field named in `errors`
19. Mixed batch (valid + invalid) → valid ones created, invalid ones reported, batch still HTTP 200

**Create — completeness and cross-field rules**
19a. `info` missing, empty string, or whitespace-only → invalid
19a2. `info` of 2001 characters → invalid (guards against silent TEXT truncation); 2000 characters → created, and the stored value round-trips **untruncated** (assert the stored length is exactly 2000)
19b. `channel='bank'` with `kassa='k2'` → invalid (contradictory pair)
19c. `channel='1'` with `kassa='bank'` → invalid (contradictory pair)
19d. `channel='bank'` + `kassa='bank'` → created (the valid bank pair)
19e. `channel='cur'` + `kassa='k2'` → created (valid courier-to-till pair)
19f. `type2='zpl'` without `dr_name_id` → invalid; the error names `dr_name_id`
19g. `type2='avans'` without `dr_name_id` → invalid
19h. `type2='zpl'` **with** a valid `dr_name_id` → created, and the value round-trips
19i. `dr_name_id` referencing a nonexistent `logpass_id` → invalid
19j. A non-salary `type2` (e.g. `other`) without `dr_name_id` → created, stored as `0`

**Create — request-level (HTTP 422, nothing processed)**
20. `entries` empty array → 422
21. `entries` with 201 items → 422; 200 items → 200

**Update**
22. `PATCH` changes only supplied fields; untouched columns keep their values
23. `PATCH` re-normalizes the sign when `type1` flips `rash`→`doh`
24. `PATCH` with an empty body → 422
25. `PATCH` on an unknown id → 404
26. `PATCH` validates the same field rules (e.g. bad `kassa` → 422)
26a. `PATCH` enforces the pairing against the **merged** row, not the patch body: patching only `kassa='bank'` on a row whose `channel='1'` → 422
26b. `PATCH` changing `type2` to `zpl` on a row with `dr_name_id=0` → 422 (the conditional requirement applies to the post-update row)
27. `PATCH` writes exactly one `doh_rash_history` row with `action='update'`, and its `before_json`/`after_json` reflect the actual old and new values

**Delete**
28. `DELETE` physically removes the row (`assertDatabaseMissing`)
29. `DELETE` on an unknown id → 404
30. `DELETE` writes exactly one `doh_rash_history` row with `action='delete'`, `before_json` holding the full pre-delete row, `after_json` null
31. The deleted row is fully reconstructable from `before_json` (assert every original column value is present in the snapshot)

**Journal scope**
32. `POST` writes **no** `doh_rash_history` row (inserts are not journalled)
33. Journal rows carry `actor_user_id` = the `api_system` id and `source='mcp_api'`

**Auth**
34. `POST`, `PATCH`, `DELETE` each return 401 without a Bearer token

- [ ] **Step 4.1: Write the test file**
- [ ] **Step 4.2: Run and confirm RED**
- [ ] **Step 4.3: Commit**

---

### Task 5: Implement the write endpoints (GREEN)

**Files:**
- Modify: `app/Http/Controllers/Mcp/FinanceEntriesController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `formatRow()` and the dictionary lookup from Task 3; the `api_system` row from the seed migration.
- Produces: `store()`, `update(int $id)`, `destroy(int $id)`, plus private `validateItem()`, `toStorageRow()`, `journal(string $action, object $before, ?object $after)`, `apiAuthorId()`.

Key points:
- `apiAuthorId()` — `DB::table('logpass')->where('log','api_system')->value('logpass_id')`, cached at `TTL_META`; throws a clear `RuntimeException` if the seed migration has not run.
- `journal()` never throws in a way that fails an already-successful write — wrap in try/catch and `Log::error()` on failure, mirroring `BaseController::recordContentVersion()`.
- Sign normalization lives in `toStorageRow()` only.
- `store()` returns per-item statuses; `update()`/`destroy()` return single-row responses.

Routes:
```php
Route::post('finance/entries',          [FinanceEntriesController::class, 'store'])->name('finance.entries.store');
Route::patch('finance/entries/{id}',    [FinanceEntriesController::class, 'update'])->name('finance.entries.update')->where('id', '[0-9]+');
Route::delete('finance/entries/{id}',   [FinanceEntriesController::class, 'destroy'])->name('finance.entries.destroy')->where('id', '[0-9]+');
```

- [ ] **Step 5.1: Implement the write half + register the three routes**
- [ ] **Step 5.2: Run the write tests until green**
- [ ] **Step 5.3: Re-run the read tests — confirm no regression**
- [ ] **Step 5.4: Commit**

---

### Task 6: Documentation + OpenAPI

**Files:**
- Modify: `docs/mcp_server.md`, `resources/openapi/mcp-v1.json`, `config/mcp.php`, `tests/Feature/Mcp/SpecRuntimeParityTest.php`

- [ ] **Step 6.1: Add the six endpoints to the `docs/mcp_server.md` catalog table** (Finance section), noting: positive-magnitude amount contract, `type1` whitelist, `kassa`/`channel` rules, physical delete with journal recovery, and that only update/delete are journalled.

- [ ] **Step 6.1a: Add a "Ledger entry model" section to `docs/mcp_server.md`** — this is a first-class deliverable, not a footnote: the column names do not explain themselves and a calling agent cannot infer them. Reproduce the spec's *Field semantics* section: the four questions every entry answers (`type1` direction / `type2` article / `channel` where it happened / `kassa` where the money sits), what each value means, the `channel`×`kassa` pairing table, that `acc_date` is the accounting date and not the creation time, and which fields are required (including the conditional `dr_name_id` rule for `zpl`/`avans`). Place it near the existing "L3 product pages — URL resolution and gotchas" section, which is the established precedent for this kind of domain explainer in that file.

- [ ] **Step 6.2: Add OpenAPI paths** for all six, plus `FinanceEntry`, `FinanceEntryInput` and `FinanceEntryHistoryRow` schemas. Every field in `FinanceEntryInput` carries a `description` explaining its meaning (not just its type) — an agent generating tool definitions from this spec sees only these strings. Include the pairing rule and the conditional `dr_name_id` requirement in the endpoint-level `description`, and use `enum` for `type1`/`kassa` so invalid values are caught client-side too.
- [ ] **Step 6.3: Bump `info.version` in the OpenAPI spec to `2.4.0`, and `config/mcp.php`'s `version` to `2.4.0`** — this also closes the pre-existing drift where `config/mcp.php` said `2.2.0` while the spec said `2.3.0`.
- [ ] **Step 6.4: Update the hardcoded assertion** in `SpecRuntimeParityTest::test_spec_version_matches` from `2.3.0` to `2.4.0`.
- [ ] **Step 6.5: Validate the JSON**

```bash
python3 -c "import json; json.load(open('resources/openapi/mcp-v1.json')); print('valid JSON')"
```

- [ ] **Step 6.6: Run `SpecRuntimeParityTest` and `HealthTest`**
- [ ] **Step 6.7: Commit**

---

### Task 7: Final verification

- [ ] **Step 7.1: Run every `tests/Feature/Mcp/` class individually**

```bash
cd /home/dmitry/sites/tiktakby
for f in tests/Feature/Mcp/*.php; do
  echo "=== $f ===";
  docker compose exec -T app php artisan test "$f" || echo "FAILED: $f";
done
```

Expected: every file `OK`, no `FAILED:` lines. `LegacyParityTest` is the key regression guard here — it enforces legacy-report parity over the same `doh_rash` numbers.

- [ ] **Step 7.2: Lint the new PHP files**

```bash
docker compose exec app php -l app/Http/Controllers/Mcp/FinanceEntriesController.php
docker compose exec app php -l database/migrations/2026_08_10_120000_create_doh_rash_history_table.php
docker compose exec app php -l tests/Feature/Mcp/FinanceEntriesReadTest.php
docker compose exec app php -l tests/Feature/Mcp/FinanceEntriesWriteTest.php
```

- [ ] **Step 7.3: Confirm no existing table was altered**

```bash
docker compose exec -T db mysql -utiktakby_tiktak -pVai7evahch tiktakby_tiktak -e "SHOW COLUMNS FROM doh_rash; SHOW COLUMNS FROM rash_items; SHOW COLUMNS FROM doh_items;"
```

Expected: `doh_rash` still has exactly its 12 original columns; `rash_items`/`doh_items` unchanged.

- [ ] **Step 7.4: Live smoke test — full round trip**

```bash
TOKEN=$(docker compose exec -T app php artisan tinker --execute="echo config('mcp.api_token');" 2>/dev/null | tail -1)
BASE=http://localhost/api/mcp/v1/finance/entries
# create
curl -s -X POST $BASE -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"entries":[{"type1":"rash","type2":"other","date":"2026-08-01","amount":12.34,"kassa":"bank","channel":"bank","info":"SMOKETEST round trip"}]}' | python3 -m json.tool
# read back, update, delete, then confirm the journal has both events
```

Expected: create returns `created` + a `dr_id`; the row reads back with `amount: 12.34`; PATCH and DELETE succeed; `GET /finance/entries/history?dr_id=<id>` shows one `update` and one `delete`. **Clean up the smoke-test row afterwards.**

- [ ] **Step 7.5: Verify the branch merges cleanly**

```bash
git fetch origin && git merge-tree --write-tree --messages HEAD origin/main
```

Expected: exit code 0.

- [ ] **Step 7.6: Review the diff, then report to the user** — this project's workflow is local dev + PR for the owner to review; do not deploy.
