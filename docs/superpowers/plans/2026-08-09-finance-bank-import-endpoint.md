# Finance Bank Import Endpoint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `POST /api/mcp/v1/finance/bank-import` — an idempotent, batch write endpoint that inserts bank-statement income/expense lines into `doh_rash` (`kassa='bank'`), closing the `fy2025_bank_channel_gap` (D-OPEN-FY2025) reconciliation gap that currently requires hand-run SQL against production.

**Architecture:** New standalone controller `FinanceBankImportController` (write concerns kept separate from the existing read-only `FinanceController`). A migration seeds a dedicated "API" `logpass` row used as the fixed `cr_who_id` author for every row this endpoint writes. Per-item validation + idempotency check + insert, matching the existing `PagesProductController::bulkUpdate` per-item-status response pattern.

**Tech Stack:** Laravel 8.75, PHP 7.4, MariaDB 10.6 (local: Docker `db` service), PHPUnit (feature tests hit the real dev DB inside a transaction via `DatabaseTransactions`, not `RefreshDatabase`).

**Spec:** `docs/superpowers/specs/2026-08-09-finance-bank-import-design.md` — read this first for the full rationale; this plan implements it as-is with one refinement noted below.

## Global Constraints

- No route closures — `routes/api.php` must use `[Controller::class, 'method']`, never a closure (breaks `route:cache` on production).
- `bb/` legacy code is untouched by this work — this endpoint only writes through Laravel's `DB::table()`, matching the existing MCP write-endpoint convention (`RedirectsController`, `PagesProductController`), never `bb\classes\*`.
- Laravel 8.75 does **not** have the `decimal:min,max` validation rule (added in Laravel 9.16) — amount decimal-place validation uses a `regex` rule instead (see Task 4).
- `doh_rash` is `MyISAM` — no foreign keys, no transactions across the table. Verified against production (see spec) — its only columns are `dr_id, acc_date, amount, type1, type2, channel, kassa, link_to, info, cr_time, cr_who_id, dr_name_id`. `bb/models/KassaOperation.php` references `le_id`/`channel_num`/`zpl_period` columns that **do not exist** on the real table (pre-existing legacy inconsistency, confirmed via production `SHOW CREATE TABLE`, unrelated to this endpoint — do not copy that column list).
- **Refinement vs. the written spec:** the spec describes resolving `cr_who_id` via `config('mcp.php')`. This plan instead resolves it with a small cached DB lookup inside the controller (`bankImportAuthorId()`), keyed on `logpass.log='api_system'` rather than a hardcoded id. Rationale: `config/mcp.php` is a static file with no natural place to run a DB query, and hardcoding a numeric id risks drifting from whatever id the seeding migration actually assigns in a given environment. The architectural intent (resolved once, cached, never guessed) is unchanged.

---

## File Map

| Action | File | Responsibility |
|--------|------|-----------------|
| Create | `database/migrations/2026_08_09_120000_seed_api_system_logpass_user.php` | Seeds the dedicated "API" `logpass` row |
| Create | `tests/Feature/Mcp/FinanceBankImportTest.php` | Feature tests — full matrix from the spec |
| Create | `app/Http/Controllers/Mcp/FinanceBankImportController.php` | `store()` — validation, idempotency, insert |
| Modify | `routes/api.php` | Register `POST finance/bank-import` |
| Modify | `docs/mcp_server.md` | Document the new endpoint |
| Modify | `resources/openapi/mcp-v1.json` | Add path + schemas, bump `info.version` |
| Modify | `config/mcp.php` | Bump `version` to match the OpenAPI spec (fixes a pre-existing drift — spec says 2.3.0, config says 2.2.0 — while touching version anyway) |
| Modify | `tests/Feature/Mcp/SpecRuntimeParityTest.php` | Update hardcoded version assertion |
| Modify | `docs/db_notes.md` | Note the permanent `dr_name_id=0` limitation for API-imported `zpl` rows |

---

### Task 1: Migration — seed the "API" system logpass user

**Files:**
- Create: `database/migrations/2026_08_09_120000_seed_api_system_logpass_user.php`

**Interfaces:**
- Produces: a `logpass` row with `log='api_system'`, `active=0`, `lp_fio='API'` — later tasks look it up via `DB::table('logpass')->where('log', 'api_system')->value('logpass_id')`.

- [ ] **Step 1.1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a dedicated "API" logpass row used as cr_who_id for every doh_rash
 * row written by POST /finance/bank-import — so bank-imported rows are
 * attributed to a real, inert account instead of a guessed employee id.
 * active=0 means it can never log in (bb/models/User's login query requires
 * active>0), so the random `pass` value has no credential to protect.
 */
class SeedApiSystemLogpassUser extends Migration
{
    public function up(): void
    {
        DB::table('logpass')->updateOrInsert(
            ['log' => 'api_system'],
            [
                'pass'      => bin2hex(random_bytes(16)),
                'lp_fio'    => 'API',
                'level'     => -1,
                'delivery'  => 0,
                'time_yn'   => 0,
                'time_from' => 0,
                'time_to'   => 0,
                'ip_yn'     => 0,
                'ip_addr'   => '',
                'ip_addr_2' => '',
                'ip_addr_3' => '',
                'zp_yn'     => 0,
                'oklad'     => 0,
                'active'    => 0,
                'main_role' => 'consultant',
                'color'     => '',
            ]
        );
    }

    public function down(): void
    {
        DB::table('logpass')->where('log', 'api_system')->delete();
    }
}
```

- [ ] **Step 1.2: Run the migration against the local dev DB**

```bash
cd /home/dmitry/sites/tiktakby
docker compose exec app php artisan migrate --path=database/migrations/2026_08_09_120000_seed_api_system_logpass_user.php
```

Expected: `Migrating: 2026_08_09_120000_seed_api_system_logpass_user` then `Migrated:` with no errors.

- [ ] **Step 1.3: Verify the row was seeded correctly**

```bash
docker compose exec -T db mysql -utiktakby_tiktak -pVai7evahch tiktakby_tiktak -e \
  "SELECT logpass_id, log, lp_fio, active, level FROM logpass WHERE log='api_system';"
```

Expected: exactly one row, `active=0`, `lp_fio='API'`, `level=-1`.

- [ ] **Step 1.4: Commit**

```bash
git add database/migrations/2026_08_09_120000_seed_api_system_logpass_user.php
git commit -m "Seed dedicated API system user for finance bank-import author attribution"
```

---

### Task 2: Write the failing test suite

**Files:**
- Create: `tests/Feature/Mcp/FinanceBankImportTest.php`

**Interfaces:**
- Consumes: `McpTestCase::postMcp(string $path, array $body): TestResponse` (already exists — sends Bearer token automatically), `McpTestCase::assertRequiresToken(string $path)`.
- Consumes (from migration in Task 1): `logpass` row with `log='api_system'`.

- [ ] **Step 2.1: Create the full test file**

```php
<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Tests for POST /api/mcp/v1/finance/bank-import.
 * Case numbers in comments match the matrix in
 * docs/superpowers/specs/2026-08-09-finance-bank-import-design.md.
 */
class FinanceBankImportTest extends McpTestCase
{
    use DatabaseTransactions;

    private int $apiUserId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('doh_rash')->where('info', 'LIKE', '[AI] BANK#%')->delete();
        $this->apiUserId = (int) DB::table('logpass')->where('log', 'api_system')->value('logpass_id');
        $this->assertGreaterThan(0, $this->apiUserId, "run the Task 1 migration before these tests");
    }

    private function validRash(array $overrides = []): array
    {
        return array_merge([
            'type1'       => 'rash',
            'doc_n'       => '97',
            'date'        => '2026-07-06',
            'amount'      => 351.90,
            'type2'       => 'of1_rent',
            'beneficiary' => 'ТС ЖИЛОГО ДОМА 22',
            'ground'      => 'ОПЛАТА АРЕНДЫ ЗА ИЮЛЬ 2026 ГОДА',
            'note'        => null,
        ], $overrides);
    }

    private function validDoh(array $overrides = []): array
    {
        return array_merge([
            'type1'       => 'doh',
            'doc_n'       => '55',
            'date'        => '2026-07-10',
            'amount'      => 12.40,
            'type2'       => 'bank_interest',
            'beneficiary' => 'ОАО "СБЕР БАНК"',
            'ground'      => 'Проценты на остаток',
            'note'        => null,
        ], $overrides);
    }

    private function insertDohRash(array $overrides = []): int
    {
        return DB::table('doh_rash')->insertGetId(array_merge([
            'acc_date'   => Carbon::parse('2026-07-06', 'Europe/Minsk')->startOfDay()->timestamp,
            'amount'     => -100.00,
            'type1'      => 'rash',
            'type2'      => 'op_rash',
            'channel'    => 'bank',
            'kassa'      => 'bank',
            'link_to'    => 0,
            'info'       => '[AI] BANK#PRESET existing entry',
            'cr_time'    => now()->timestamp,
            'cr_who_id'  => $this->apiUserId,
            'dr_name_id' => 0,
        ], $overrides), 'dr_id');
    }

    // ─── Cases 1-3: valid inserts, sign convention ─────────────────────────────

    public function test_case1_valid_rash_tax_inserts_with_negative_amount(): void
    {
        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash(['type2' => 'pod_tax', 'amount' => 101.07])],
        ]);

        $r->assertStatus(200);
        $this->assertSame('inserted', $r->json('data.0.status'));
        $drId = $r->json('data.0.dr_id');
        $this->assertIsInt($drId);

        $row = DB::table('doh_rash')->where('dr_id', $drId)->first();
        $this->assertEquals(-101.07, (float) $row->amount);
        $this->assertSame('rash', $row->type1);
        $this->assertSame('pod_tax', $row->type2);
    }

    public function test_case2_valid_rash_non_bank_yn_code_still_inserts(): void
    {
        // zpl has rash_items.bank_yn=0 but is a real, commonly-used bank-channel code.
        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash(['type2' => 'zpl', 'doc_n' => '22'])],
        ]);

        $r->assertStatus(200);
        $this->assertSame('inserted', $r->json('data.0.status'));
    }

    public function test_case3_valid_doh_inserts_with_positive_amount(): void
    {
        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validDoh()],
        ]);

        $r->assertStatus(200);
        $this->assertSame('inserted', $r->json('data.0.status'));
        $drId = $r->json('data.0.dr_id');

        $row = DB::table('doh_rash')->where('dr_id', $drId)->first();
        $this->assertEquals(12.40, (float) $row->amount);
        $this->assertSame('doh', $row->type1);
    }

    // ─── Cases 4-7: type1/type2 validation ─────────────────────────────────────

    public function test_case4_type2_wrong_dictionary_is_invalid(): void
    {
        // bank_interest is a doh-only code; sending it under type1=rash must fail.
        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash(['type2' => 'bank_interest'])],
        ]);

        $r->assertStatus(200);
        $this->assertSame('invalid', $r->json('data.0.status'));
        $this->assertArrayHasKey('type2', $r->json('data.0.errors'));
    }

    public function test_case5_type2_inactive_is_invalid(): void
    {
        DB::table('rash_items')->insert([
            'ri_order' => 9999, 'ri_text' => 'test inactive', 'ri_code' => 'test_inactive_code',
            'bank_yn' => 0, 'is_active' => 0, 'resertve_1' => 0, 'resertve_2' => 0, 'resertve_3' => 0,
        ]);

        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash(['type2' => 'test_inactive_code'])],
        ]);

        $this->assertSame('invalid', $r->json('data.0.status'));

        DB::table('rash_items')->where('ri_code', 'test_inactive_code')->delete();
    }

    public function test_case6_type1_internal_transfer_is_invalid(): void
    {
        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash(['type1' => 'shift_plus'])],
        ]);

        $this->assertSame('invalid', $r->json('data.0.status'));
        $this->assertArrayHasKey('type1', $r->json('data.0.errors'));
    }

    public function test_case7_type1_missing_or_garbage_is_invalid(): void
    {
        foreach (['', 'expense', null] as $badType1) {
            $item = $this->validRash();
            if ($badType1 === null) {
                unset($item['type1']);
            } else {
                $item['type1'] = $badType1;
            }
            $r = $this->postMcp('finance/bank-import', ['expenses' => [$item]]);
            $this->assertSame('invalid', $r->json('data.0.status'), 'type1=' . var_export($badType1, true));
        }
    }

    // ─── Cases 8-11: amount validation ──────────────────────────────────────────

    public function test_case8_negative_amount_is_invalid_not_flipped(): void
    {
        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash(['amount' => -351.90])],
        ]);
        $this->assertSame('invalid', $r->json('data.0.status'));
        $this->assertArrayHasKey('amount', $r->json('data.0.errors'));

        // Confirm nothing was written under any sign.
        $this->assertSame(0, DB::table('doh_rash')->where('info', 'LIKE', '%BANK#97 %')->count());
    }

    public function test_case9_zero_amount_is_invalid(): void
    {
        $r = $this->postMcp('finance/bank-import', ['expenses' => [$this->validRash(['amount' => 0])]]);
        $this->assertSame('invalid', $r->json('data.0.status'));
    }

    public function test_case10_non_numeric_amount_is_invalid(): void
    {
        $r = $this->postMcp('finance/bank-import', ['expenses' => [$this->validRash(['amount' => 'abc'])]]);
        $this->assertSame('invalid', $r->json('data.0.status'));
    }

    public function test_case11_amount_precision_and_overflow_invalid(): void
    {
        $r1 = $this->postMcp('finance/bank-import', ['expenses' => [$this->validRash(['amount' => 123.456])]]);
        $this->assertSame('invalid', $r1->json('data.0.status'), 'three decimal places must be rejected');

        $r2 = $this->postMcp('finance/bank-import', ['expenses' => [$this->validRash(['amount' => 9999999999.99])]]);
        $this->assertSame('invalid', $r2->json('data.0.status'), 'exceeds decimal(11,2) capacity');
    }

    // ─── Case 12: missing required fields ───────────────────────────────────────

    public function test_case12_missing_required_fields_invalid(): void
    {
        foreach (['doc_n', 'date', 'beneficiary', 'ground'] as $field) {
            $item = $this->validRash();
            unset($item[$field]);
            $r = $this->postMcp('finance/bank-import', ['expenses' => [$item]]);
            $this->assertSame('invalid', $r->json('data.0.status'), "missing {$field}");
            $this->assertArrayHasKey($field, $r->json('data.0.errors'), "missing {$field}");
        }
    }

    // ─── Case 13: malformed dates ────────────────────────────────────────────────

    public function test_case13_malformed_dates_invalid(): void
    {
        foreach (['2026-13-01', '2026-02-30', '07/06/2026'] as $badDate) {
            $r = $this->postMcp('finance/bank-import', [
                'expenses' => [$this->validRash(['date' => $badDate])],
            ]);
            $this->assertSame('invalid', $r->json('data.0.status'), "date={$badDate}");
        }
    }

    // ─── Case 14: doc_n length ───────────────────────────────────────────────────

    public function test_case14_doc_n_too_long_invalid(): void
    {
        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash(['doc_n' => str_repeat('9', 65)])],
        ]);
        $this->assertSame('invalid', $r->json('data.0.status'));
    }

    // ─── Cases 15-18: idempotency ────────────────────────────────────────────────

    public function test_case15_duplicate_within_same_batch(): void
    {
        $item = $this->validRash(['doc_n' => '200', 'amount' => 75.00]);
        $r = $this->postMcp('finance/bank-import', ['expenses' => [$item, $item]]);

        $this->assertSame('inserted', $r->json('data.0.status'));
        $this->assertSame('duplicate', $r->json('data.1.status'));
        $this->assertSame($r->json('data.0.dr_id'), $r->json('data.1.dr_id'));
    }

    public function test_case16_idempotent_resubmit_across_requests(): void
    {
        $item = $this->validRash(['doc_n' => '201', 'amount' => 88.50]);

        $first = $this->postMcp('finance/bank-import', ['expenses' => [$item]]);
        $this->assertSame('inserted', $first->json('data.0.status'));
        $countAfterFirst = DB::table('doh_rash')->where('info', 'LIKE', '%BANK#201 %')->count();

        $second = $this->postMcp('finance/bank-import', ['expenses' => [$item]]);
        $this->assertSame('duplicate', $second->json('data.0.status'));
        $countAfterSecond = DB::table('doh_rash')->where('info', 'LIKE', '%BANK#201 %')->count();

        $this->assertSame(1, $countAfterFirst);
        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_case17_dedup_window_boundary_inside_two_days(): void
    {
        $this->insertDohRash([
            'acc_date' => Carbon::parse('2026-07-06', 'Europe/Minsk')->startOfDay()->timestamp,
            'amount'   => -60.00,
            'type1'    => 'rash',
            'info'     => '[AI] BANK#300 test',
        ]);

        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash(['doc_n' => '300', 'amount' => 60.00, 'date' => '2026-07-08'])],
        ]);
        $this->assertSame('duplicate', $r->json('data.0.status'));
    }

    public function test_case18_dedup_window_boundary_outside_three_days(): void
    {
        $this->insertDohRash([
            'acc_date' => Carbon::parse('2026-07-06', 'Europe/Minsk')->startOfDay()->timestamp,
            'amount'   => -60.00,
            'type1'    => 'rash',
            'info'     => '[AI] BANK#301 test',
        ]);

        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash(['doc_n' => '301', 'amount' => 60.00, 'date' => '2026-07-09'])],
        ]);
        $this->assertSame('inserted', $r->json('data.0.status'), '3 days outside the ±2-day window must be a new row');
    }

    // ─── Cases 19-20: dry_run ────────────────────────────────────────────────────

    public function test_case19_dry_run_writes_nothing(): void
    {
        $before = DB::table('doh_rash')->count();

        $r = $this->postMcp('finance/bank-import', [
            'dry_run'  => true,
            'expenses' => [$this->validRash(['doc_n' => '400']), $this->validDoh(['doc_n' => '401'])],
        ]);

        $r->assertStatus(200);
        $this->assertSame('would_insert', $r->json('data.0.status'));
        $this->assertSame('would_insert', $r->json('data.1.status'));
        $this->assertTrue($r->json('meta.dry_run'));
        $this->assertSame($before, DB::table('doh_rash')->count());
    }

    public function test_case20_dry_run_then_real_submit_matches_prediction(): void
    {
        $item = $this->validRash(['doc_n' => '402', 'amount' => 33.33]);

        $dry = $this->postMcp('finance/bank-import', ['dry_run' => true, 'expenses' => [$item]]);
        $this->assertSame('would_insert', $dry->json('data.0.status'));

        $real = $this->postMcp('finance/bank-import', ['dry_run' => false, 'expenses' => [$item]]);
        $this->assertSame('inserted', $real->json('data.0.status'));
    }

    // ─── Case 21: batch size boundary ───────────────────────────────────────────

    public function test_case21_batch_size_boundary(): void
    {
        $items200 = [];
        for ($i = 0; $i < 200; $i++) {
            $items200[] = $this->validRash(['doc_n' => 'batch-' . $i, 'amount' => 1.00 + $i]);
        }
        $r200 = $this->postMcp('finance/bank-import', ['expenses' => $items200]);
        $r200->assertStatus(200);
        $this->assertCount(200, $r200->json('data'));

        $items201 = $items200;
        $items201[] = $this->validRash(['doc_n' => 'batch-200', 'amount' => 999]);
        $r201 = $this->postMcp('finance/bank-import', ['expenses' => $items201]);
        $r201->assertStatus(422);
    }

    // ─── Case 22: mixed batch, partial success ──────────────────────────────────

    public function test_case22_mixed_batch_partial_success(): void
    {
        $r = $this->postMcp('finance/bank-import', [
            'expenses' => [
                $this->validRash(['doc_n' => '500']),
                $this->validDoh(['doc_n' => '501']),
                $this->validRash(['doc_n' => '502', 'amount' => -5]),   // invalid
                $this->validRash(['doc_n' => '500']),                  // duplicate of item 0
            ],
        ]);

        $r->assertStatus(200);
        $this->assertSame('inserted',  $r->json('data.0.status'));
        $this->assertSame('inserted',  $r->json('data.1.status'));
        $this->assertSame('invalid',   $r->json('data.2.status'));
        $this->assertSame('duplicate', $r->json('data.3.status'));
        $this->assertSame(2, $r->json('meta.summary.inserted'));
        $this->assertSame(1, $r->json('meta.summary.invalid'));
        $this->assertSame(1, $r->json('meta.summary.duplicate'));
    }

    // ─── Case 23: client cannot override fixed fields ───────────────────────────

    public function test_case23_client_cannot_override_fixed_fields(): void
    {
        $item = $this->validRash(['doc_n' => '600']);
        $item['channel']   = 'cash';
        $item['kassa']     = 'k1';
        $item['cr_who_id'] = 3;

        $r = $this->postMcp('finance/bank-import', ['expenses' => [$item]]);
        $drId = $r->json('data.0.dr_id');

        $row = DB::table('doh_rash')->where('dr_id', $drId)->first();
        $this->assertSame('bank', $row->channel);
        $this->assertSame('bank', $row->kassa);
        $this->assertSame($this->apiUserId, (int) $row->cr_who_id);
    }

    // ─── Case 24: info format ────────────────────────────────────────────────────

    public function test_case24_info_format_with_and_without_note(): void
    {
        $r1 = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash(['doc_n' => '700', 'beneficiary' => 'Beneficiary A', 'ground' => 'Ground A'])],
        ]);
        $row1 = DB::table('doh_rash')->where('dr_id', $r1->json('data.0.dr_id'))->first();
        $this->assertSame('[AI] BANK#700 Beneficiary A: Ground A', $row1->info);

        $r2 = $this->postMcp('finance/bank-import', [
            'expenses' => [$this->validRash([
                'doc_n' => '701', 'beneficiary' => 'Beneficiary B', 'ground' => 'Ground B', 'note' => 'extra note',
            ])],
        ]);
        $row2 = DB::table('doh_rash')->where('dr_id', $r2->json('data.0.dr_id'))->first();
        $this->assertSame('[AI] BANK#701 Beneficiary B: Ground B extra note', $row2->info);
    }

    // ─── Case 25: auth ───────────────────────────────────────────────────────────

    public function test_case25_requires_bearer_token(): void
    {
        $this->postJson('/api/mcp/v1/finance/bank-import', ['expenses' => [$this->validRash()]])
            ->assertStatus(401);
    }

    // ─── Case 26: empty batch ────────────────────────────────────────────────────

    public function test_case26_empty_batch_rejected(): void
    {
        $this->postMcp('finance/bank-import', ['expenses' => []])->assertStatus(422);
    }
}
```

- [ ] **Step 2.2: Run the tests to confirm they fail**

```bash
cd /home/dmitry/sites/tiktakby
docker compose exec app php artisan test --filter=FinanceBankImportTest
```

Expected: every test fails — the route doesn't exist yet, so `postMcp()` returns 404 and every `assertStatus(200)`/`assertSame('inserted', ...)` assertion fails.

---

### Task 3: Register the route

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 3.1: Add the `use` import**

Find (near the top, alphabetical with the other `use App\Http\Controllers\Mcp\*` lines):
```php
use App\Http\Controllers\Mcp\FinanceController;
```
Add immediately after it:
```php
use App\Http\Controllers\Mcp\FinanceBankImportController;
```

- [ ] **Step 3.2: Register the route**

Find in `routes/api.php`:
```php
        // Finance (A.4)
        Route::get('finance/pnl',                 [FinanceController::class, 'pnl'])->name('finance.pnl');
        Route::get('finance/revenue',             [FinanceController::class, 'revenue'])->name('finance.revenue');
        Route::get('finance/revenue-by-category', [FinanceController::class, 'revenueByCategory'])->name('finance.revenue-by-category');
        Route::get('finance/expenses',            [FinanceController::class, 'expenses'])->name('finance.expenses');
        Route::get('finance/cash-flow', [FinanceController::class, 'cashFlow'])->name('finance.cash-flow');
```

Replace with (adds one line at the end):
```php
        // Finance (A.4)
        Route::get('finance/pnl',                 [FinanceController::class, 'pnl'])->name('finance.pnl');
        Route::get('finance/revenue',             [FinanceController::class, 'revenue'])->name('finance.revenue');
        Route::get('finance/revenue-by-category', [FinanceController::class, 'revenueByCategory'])->name('finance.revenue-by-category');
        Route::get('finance/expenses',            [FinanceController::class, 'expenses'])->name('finance.expenses');
        Route::get('finance/cash-flow', [FinanceController::class, 'cashFlow'])->name('finance.cash-flow');
        Route::post('finance/bank-import', [FinanceBankImportController::class, 'store'])->name('finance.bank-import');
```

- [ ] **Step 3.3: Run the tests again — confirm a different failure mode**

```bash
docker compose exec app php artisan test --filter=FinanceBankImportTest
```

Expected: fatal error / 500 responses now (`Target class [FinanceBankImportController] does not exist`) instead of 404 — confirms routing works, controller is the missing piece.

- [ ] **Step 3.4: Commit**

```bash
git add routes/api.php tests/Feature/Mcp/FinanceBankImportTest.php
git commit -m "Add failing tests and route for POST /finance/bank-import"
```

---

### Task 4: Implement `FinanceBankImportController`

**Files:**
- Create: `app/Http/Controllers/Mcp/FinanceBankImportController.php`

**Interfaces:**
- Consumes: `BaseController::envelope(array $query, $data, array $meta = []): JsonResponse`, `BaseController::cacheRemember(string $key, ?int $ttl, \Closure $callback)`, `BaseController::TTL_META`.
- Produces: `FinanceBankImportController::store(Request $request): JsonResponse` — wired to the route in Task 3.

- [ ] **Step 4.1: Create the controller**

```php
<?php

namespace App\Http\Controllers\Mcp;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * POST /api/mcp/v1/finance/bank-import
 *
 * Idempotent batch write path for bank-statement lines (income + expense)
 * into doh_rash, kassa='bank' channel only. Closes the fy2025_bank_channel_gap
 * (D-OPEN-FY2025) reconciliation gap that previously required hand-run SQL
 * against production.
 *
 * See docs/superpowers/specs/2026-08-09-finance-bank-import-design.md for the
 * full rationale (sign convention, why type1 is whitelisted, why dr_name_id
 * stays 0, the idempotency key).
 */
class FinanceBankImportController extends BaseController
{
    private const MAX_BATCH_ITEMS = 200;

    /** type1 => [dictionary table, code column] */
    private const TYPE1_DICTIONARIES = [
        'rash' => ['rash_items', 'ri_code'],
        'doh'  => ['doh_items',  'rd_code'],
    ];

    private const ITEM_RULES = [
        'type1'       => 'required|in:rash,doh',
        'doc_n'       => 'required|string|max:64',
        'date'        => 'required|date_format:Y-m-d',
        // decimal:min,max isn't available before Laravel 9.16 — regex enforces
        // "at most 2 decimal places"; max: caps at what decimal(11,2) can hold.
        'amount'      => 'required|numeric|gt:0|max:999999999.99|regex:/^\d{1,9}(\.\d{1,2})?$/',
        'type2'       => 'required|string|max:64',
        'beneficiary' => 'required|string|max:500',
        'ground'      => 'required|string',
        'note'        => 'sometimes|nullable|string',
    ];

    /**
     * Body: {"dry_run": bool, "expenses": [{type1, doc_n, date, amount, type2, beneficiary, ground, note}]}
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'dry_run'  => 'sometimes|boolean',
            'expenses' => 'required|array|min:1|max:' . self::MAX_BATCH_ITEMS,
        ]);

        $dryRun = $request->boolean('dry_run');
        $items  = $request->input('expenses');

        $results = [];
        foreach ($items as $i => $item) {
            $results[] = $this->processItem((int) $i, is_array($item) ? $item : [], $dryRun);
        }

        $counts = array_count_values(array_column($results, 'status'));

        return $this->envelope([], $results, [
            'dry_run' => $dryRun,
            'summary' => [
                'inserted'     => $counts['inserted']     ?? 0,
                'would_insert' => $counts['would_insert'] ?? 0,
                'duplicate'    => $counts['duplicate']    ?? 0,
                'invalid'      => $counts['invalid']      ?? 0,
            ],
        ]);
    }

    /** @return array{index:int, doc_n:string, status:string, dr_id:?int, errors:?array} */
    private function processItem(int $index, array $item, bool $dryRun): array
    {
        $docN = is_string($item['doc_n'] ?? null) ? $item['doc_n'] : '';

        $validator = Validator::make($item, self::ITEM_RULES);
        if ($validator->fails()) {
            return $this->result($index, $docN, 'invalid', null, $validator->errors()->toArray());
        }
        $v = $validator->validated();

        [$dictTable, $dictCol] = self::TYPE1_DICTIONARIES[$v['type1']];
        $type2Active = DB::table($dictTable)->where($dictCol, $v['type2'])->where('is_active', 1)->exists();
        if (!$type2Active) {
            return $this->result($index, $docN, 'invalid', null, [
                'type2' => ["Unknown or inactive {$v['type1']} category '{$v['type2']}'."],
            ]);
        }

        $day       = Carbon::createFromFormat('Y-m-d', $v['date'], 'Europe/Minsk')->startOfDay();
        $accDate   = $day->timestamp;
        $windowLo  = $day->copy()->subDays(2)->timestamp;
        $windowHi  = $day->copy()->addDays(2)->timestamp;
        $amountAbs = round((float) $v['amount'], 2);

        $existingId = DB::table('doh_rash')
            ->where('kassa', 'bank')
            ->where('type1', $v['type1'])
            ->whereRaw('ABS(amount) = ?', [$amountAbs])
            ->whereBetween('acc_date', [$windowLo, $windowHi])
            // Leading % is required: stored info is prefixed "[AI] BANK#...", and
            // older rows from the May-2026 reconciliation are unprefixed "BANK#...".
            // A start-anchored pattern would never match either — see the design
            // spec's Idempotency section for the bug this avoids (present in the
            // draft PROPOSED_insert_2026-07.sql, which reuses the old unprefixed
            // pattern while also adding the new [AI] prefix).
            ->where('info', 'LIKE', '%BANK#' . $docN . ' %')
            ->value('dr_id');

        if ($existingId) {
            return $this->result($index, $docN, 'duplicate', (int) $existingId);
        }

        if ($dryRun) {
            return $this->result($index, $docN, 'would_insert');
        }

        $info = '[AI] BANK#' . $docN . ' ' . $v['beneficiary'] . ': ' . $v['ground'];
        if (!empty($v['note'] ?? null)) {
            $info .= ' ' . $v['note'];
        }

        $storedAmount = $v['type1'] === 'rash' ? -$amountAbs : $amountAbs;

        $drId = DB::table('doh_rash')->insertGetId([
            'acc_date'   => $accDate,
            'amount'     => $storedAmount,
            'type1'      => $v['type1'],
            'type2'      => $v['type2'],
            'channel'    => 'bank',
            'kassa'      => 'bank',
            'link_to'    => 0,
            'info'       => $info,
            'cr_time'    => now()->timestamp,
            'cr_who_id'  => $this->bankImportAuthorId(),
            'dr_name_id' => 0,
        ], 'dr_id');

        return $this->result($index, $docN, 'inserted', $drId);
    }

    private function result(int $index, string $docN, string $status, ?int $drId = null, ?array $errors = null): array
    {
        return [
            'index'  => $index,
            'doc_n'  => $docN,
            'status' => $status,
            'dr_id'  => $drId,
            'errors' => $errors,
        ];
    }

    /**
     * logpass_id of the dedicated 'API' system user, seeded by migration
     * 2026_08_09_120000_seed_api_system_logpass_user.php. Cached, never guessed.
     */
    private function bankImportAuthorId(): int
    {
        return (int) $this->cacheRemember('mcp.finance_bank_import_author_id', self::TTL_META, function () {
            $id = DB::table('logpass')->where('log', 'api_system')->value('logpass_id');
            if (!$id) {
                throw new \RuntimeException("logpass 'api_system' row is missing — run migrations.");
            }
            return $id;
        });
    }
}
```

- [ ] **Step 4.2: Commit**

```bash
git add app/Http/Controllers/Mcp/FinanceBankImportController.php
git commit -m "Implement FinanceBankImportController"
```

---

### Task 5: Run the full test file — verify everything passes

- [ ] **Step 5.1: Run the tests**

```bash
cd /home/dmitry/sites/tiktakby
docker compose exec app php artisan test --filter=FinanceBankImportTest
```

Expected: all 26 test methods pass (`OK (26 tests, ...)`). If `test_case17`/`test_case18` (dedup window) fail, check the `Europe/Minsk` timezone conversion in `processItem()` against the fixture's `Carbon::parse(..., 'Europe/Minsk')` — both must agree on the same day boundary. If `test_case11` fails on the overflow value, double check the regex has no typo (`\d{1,9}` not `\d{1,10}`).

- [ ] **Step 5.2: Run nearby MCP test classes individually to check for regressions**

Known project quirk (from a prior implementation session, recorded so it isn't
re-discovered the hard way): running many `tests/Feature/Mcp/*` classes together
in one process trips cross-test isolation issues unrelated to any real defect —
the same classes pass clean when run one at a time. Gate per-class, not via a
broad `--filter=Mcp` that pulls in the whole directory:

```bash
docker compose exec app php artisan test tests/Feature/Mcp/FinanceTest.php
docker compose exec app php artisan test tests/Feature/Mcp/RedirectsTest.php
docker compose exec app php artisan test tests/Feature/Mcp/PagesProductTest.php
docker compose exec app php artisan test tests/Feature/Mcp/MetaTest.php
docker compose exec app php artisan test tests/Feature/Mcp/HealthTest.php
```

Expected: each command reports its own `OK` — no failures in any of the five.

- [ ] **Step 5.3: Commit any fixes made while chasing failures**

```bash
git add -A
git commit -m "Fix FinanceBankImportController issues found by test run"
```
(Skip this step if Step 5.1 passed clean on the first run.)

---

### Task 6: Document the endpoint in `docs/mcp_server.md`

**Files:**
- Modify: `docs/mcp_server.md`

- [ ] **Step 6.1: Add a table row in the endpoint catalog**

Find this line (in the `## Endpoint catalog` table):
```
|        | `GET /finance/cash-flow` | Inflow/outflow/net per till (`kassa`) |
```

Replace with:
```
|        | `GET /finance/cash-flow` | Inflow/outflow/net per till (`kassa`) |
|        | `POST /finance/bank-import` | Idempotent batch write of bank-statement lines into `doh_rash` (`kassa='bank'` only). Body `{"dry_run": bool, "expenses": [{type1: rash\|doh, doc_n, date, amount (positive magnitude), type2, beneficiary, ground, note}]}`, up to 200 items. Each item reports `inserted\|would_insert\|duplicate\|invalid`. `type2` validated against `rash_items`/`doh_items` per `type1`. Author (`cr_who_id`) is a dedicated "API" system user, never a guessed employee id. `dr_name_id` is always 0 (no per-employee salary attribution from bank data — permanent limitation, see `docs/db_notes.md`). |
```

- [ ] **Step 6.2: Commit**

```bash
git add docs/mcp_server.md
git commit -m "Document POST /finance/bank-import in mcp_server.md"
```

---

### Task 7: Update the OpenAPI spec

**Files:**
- Modify: `resources/openapi/mcp-v1.json`

- [ ] **Step 7.1: Add the path entry**

The file is one JSON object; add this key inside `paths`, alongside the other `/finance/*` entries (find `"/finance/cash-flow"` and add a new `"/finance/bank-import"` key as a sibling after it, before the closing `,` of that block moves to this new key):

```json
"/finance/bank-import": {
  "post": {
    "operationId": "tiktak_finance_bank_import",
    "summary": "Idempotent batch import of bank-statement lines into doh_rash.",
    "description": "Writes income (type1=doh) and expense (type1=rash) rows sourced from a bank statement, kassa='bank' channel only. Amount is always a positive magnitude in the request; the server negates it for rash rows. Re-submitting the same doc_n/amount/date is a no-op (status=duplicate). Set dry_run=true to validate and check for duplicates without writing.",
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "required": ["expenses"],
            "properties": {
              "dry_run": {
                "type": "boolean",
                "default": false
              },
              "expenses": {
                "type": "array",
                "minItems": 1,
                "maxItems": 200,
                "items": {
                  "$ref": "#/components/schemas/BankImportItem"
                }
              }
            }
          }
        }
      }
    },
    "responses": {
      "200": {
        "$ref": "#/components/responses/BankImportResponse"
      },
      "401": {
        "$ref": "#/components/responses/Unauthorized"
      },
      "422": {
        "$ref": "#/components/responses/ValidationError"
      }
    }
  }
}
```

- [ ] **Step 7.2: Add the request item schema**

Inside `components/schemas`, add a new key alongside `RedirectInput`:

```json
"BankImportItem": {
  "type": "object",
  "required": ["type1", "doc_n", "date", "amount", "type2", "beneficiary", "ground"],
  "properties": {
    "type1": {
      "type": "string",
      "enum": ["rash", "doh"]
    },
    "doc_n": {
      "type": "string",
      "maxLength": 64,
      "example": "97"
    },
    "date": {
      "type": "string",
      "format": "date",
      "example": "2026-07-06"
    },
    "amount": {
      "type": "number",
      "description": "Positive magnitude — server negates internally for type1=rash.",
      "example": 351.90
    },
    "type2": {
      "type": "string",
      "description": "Must exist and be active in rash_items (type1=rash) or doh_items (type1=doh).",
      "example": "of1_rent"
    },
    "beneficiary": {
      "type": "string",
      "maxLength": 500
    },
    "ground": {
      "type": "string"
    },
    "note": {
      "type": "string",
      "nullable": true
    }
  }
}
```

- [ ] **Step 7.3: Add the response schema**

Inside `components/responses`, add a new key alongside `RedirectBulkResponse`:

```json
"BankImportResponse": {
  "description": "Per-item import result.",
  "content": {
    "application/json": {
      "schema": {
        "type": "object",
        "properties": {
          "data": {
            "type": "array",
            "items": {
              "type": "object",
              "properties": {
                "index": { "type": "integer" },
                "doc_n": { "type": "string" },
                "status": {
                  "type": "string",
                  "enum": ["inserted", "would_insert", "duplicate", "invalid"]
                },
                "dr_id": { "type": "integer", "nullable": true },
                "errors": { "type": "object", "nullable": true }
              }
            }
          },
          "meta": {
            "type": "object",
            "properties": {
              "dry_run": { "type": "boolean" },
              "summary": {
                "type": "object",
                "properties": {
                  "inserted": { "type": "integer" },
                  "would_insert": { "type": "integer" },
                  "duplicate": { "type": "integer" },
                  "invalid": { "type": "integer" }
                }
              }
            }
          }
        }
      }
    }
  }
}
```

- [ ] **Step 7.4: Bump the spec version**

Find:
```json
"info": {
  "version": "2.3.0",
```
Replace with:
```json
"info": {
  "version": "2.4.0",
```
(Exact surrounding keys depend on the file — only change the `"version"` value inside `"info"`, do not restructure the rest of that object.)

- [ ] **Step 7.5: Validate the JSON is well-formed**

```bash
python3 -c "import json; json.load(open('resources/openapi/mcp-v1.json')); print('valid JSON')"
```

Expected: `valid JSON`. If it errors, find and fix the syntax mistake (trailing comma is the usual culprit) before continuing.

- [ ] **Step 7.6: Commit**

```bash
git add resources/openapi/mcp-v1.json
git commit -m "Add OpenAPI spec entry for POST /finance/bank-import, bump to 2.4.0"
```

---

### Task 8: Fix the version drift + document the `dr_name_id` limitation

**Files:**
- Modify: `config/mcp.php`
- Modify: `tests/Feature/Mcp/SpecRuntimeParityTest.php`
- Modify: `docs/db_notes.md`

`config('mcp.version')` (served by `GET /health`) currently says `2.2.0` while `resources/openapi/mcp-v1.json`'s `info.version` already said `2.3.0` before this change — a pre-existing drift, visible to any consumer comparing the two endpoints. Since Task 7 bumps the spec to `2.4.0` anyway, fix both together rather than widen the gap.

- [ ] **Step 8.1: Bump `config/mcp.php`**

Find:
```php
    'version' => '2.2.0',
```
Replace with:
```php
    'version' => '2.4.0',
```

- [ ] **Step 8.2: Update the hardcoded version assertion**

In `tests/Feature/Mcp/SpecRuntimeParityTest.php`, find:
```php
    public function test_spec_version_matches(): void
    {
        $this->assertSame(
            '2.3.0',
            $this->spec['info']['version'],
            'bumping spec without updating this assertion is a footgun — update both together'
        );
    }
```
Replace `'2.3.0'` with `'2.4.0'`:
```php
    public function test_spec_version_matches(): void
    {
        $this->assertSame(
            '2.4.0',
            $this->spec['info']['version'],
            'bumping spec without updating this assertion is a footgun — update both together'
        );
    }
```

- [ ] **Step 8.3: Add the `dr_name_id` limitation note to `docs/db_notes.md`**

Read the file first to find a natural insertion point (a "gotchas" or numbered list near the top, per the file's existing structure), then add one entry such as:

```
- **`doh_rash.dr_name_id` is always 0 for rows written by `POST /finance/bank-import`.** Bank
  salary payments arrive as one lump Sber transfer per pay run — there is no per-employee split
  in the bank statement to attach. This is permanent, not a TODO: the per-employee salary view
  (`bb/rash_analysis.php`, `bb/doh-rash.php`) will show these `zpl` rows unattributed. See
  `docs/superpowers/specs/2026-08-09-finance-bank-import-design.md`.
```

- [ ] **Step 8.4: Run the full MCP test suite once more**

```bash
cd /home/dmitry/sites/tiktakby
docker compose exec app php artisan test --testsuite=Feature --filter=Mcp
```

Expected: all green, including `SpecRuntimeParityTest::test_spec_version_matches`.

- [ ] **Step 8.5: Commit**

```bash
git add config/mcp.php tests/Feature/Mcp/SpecRuntimeParityTest.php docs/db_notes.md
git commit -m "Bump mcp.version to 2.4.0 (fixes drift vs OpenAPI spec), document dr_name_id limitation"
```

---

### Task 9: Final verification

- [ ] **Step 9.1: Run the full `Mcp` test directory per-class**

Do **not** run `php artisan test` (whole suite) or `--filter=Mcp` (whole directory
in one process) — both are known to fail on cross-test isolation issues in this
project unrelated to any real defect (documented from a prior implementation
session). Gate per-class instead:

```bash
cd /home/dmitry/sites/tiktakby
for f in tests/Feature/Mcp/*.php; do
  echo "=== $f ===";
  docker compose exec -T app php artisan test "$f" || echo "FAILED: $f";
done
```

Expected: every file reports `OK`, no `FAILED:` lines. This directory includes
`LegacyParityTest.php`, which is the relevant regression guard for this change
(it enforces legacy-report parity for the same `doh_rash`-adjacent numbers).

- [ ] **Step 9.2: PHP syntax-lint the new/changed PHP files**

```bash
docker compose exec app php -l app/Http/Controllers/Mcp/FinanceBankImportController.php
docker compose exec app php -l database/migrations/2026_08_09_120000_seed_api_system_logpass_user.php
docker compose exec app php -l tests/Feature/Mcp/FinanceBankImportTest.php
```

Expected: `No syntax errors detected` for all three.

- [ ] **Step 9.3: Manual smoke test against the running dev container**

```bash
TOKEN=$(docker compose exec -T app php artisan tinker --execute="echo config('mcp.api_token');" 2>/dev/null | tail -1)
curl -s -X POST http://localhost/api/mcp/v1/finance/bank-import \
  -H "Authorization: Bearer ${TOKEN}" -H "Content-Type: application/json" \
  -d '{"dry_run": true, "expenses": [{"type1":"rash","doc_n":"smoke-1","date":"2026-08-01","amount":10.50,"type2":"other","beneficiary":"Test","ground":"Smoke test"}]}' \
  | python3 -m json.tool
```

Expected: `data.0.status` is `"would_insert"`, `meta.dry_run` is `true`, HTTP 200.

- [ ] **Step 9.4: Review the full diff before handing off**

```bash
git status
git diff --stat main...HEAD
```

Confirm only the files in the File Map (plus the two spec/plan docs) changed.

- [ ] **Step 9.5: Report status to the user**

Summarize: endpoint implemented and tested on branch `feature/finance-bank-import-endpoint`, all tests passing, ready for the user to review before opening a PR (per this project's workflow — local dev + PR review, no direct deploy).
