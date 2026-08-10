<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * RED tests for the write half of the finance ledger API (`doh_rash`):
 *
 *   POST   /finance/entries          (batch create)
 *   PATCH  /finance/entries/{id}     (partial update)
 *   DELETE /finance/entries/{id}
 *
 * None of these routes exist yet (Task 5 implements them) — every test here
 * is expected to fail. An unregistered POST/PATCH/DELETE path falls through
 * to Laravel's own "URI matched, method didn't" handling and returns 405
 * (pre-existing, app-wide, not a defect, since GET routes already exist at
 * these same paths) rather than a PHP error; that's fine. What must NOT
 * happen is a fatal/syntax error inside this file itself.
 *
 * ── MyISAM caveat ──────────────────────────────────────────────────────────
 * `doh_rash` is MyISAM and does not honour transactions, so DatabaseTransactions
 * will NOT roll back rows inserted into it — either fixture rows inserted
 * directly for PATCH/DELETE setup, or rows this suite creates indirectly by
 * POSTing. Every such row is tagged with an `info` value starting with the
 * literal prefix `TESTENTRY-`, purged in both setUp() and tearDown().
 *
 * `rash_items`/`doh_items` (the type2 dictionaries) are *also* MyISAM. Item 10
 * of the brief requires an `is_active=0` dictionary code, and this dev DB has
 * no naturally inactive row in either table — so this suite temporarily seeds
 * one throwaway inactive code per dictionary (`ri_code`/`rd_code` prefixed
 * `TESTENTRY_INACTIVE_`) and purges them the same way as doh_rash fixtures.
 *
 * `doh_rash_history` (the journal table added in Task 1) is InnoDB, so
 * fixture rows written there are covered by DatabaseTransactions and need no
 * manual cleanup.
 */
class FinanceEntriesWriteTest extends McpTestCase
{
    use DatabaseTransactions;

    private const MARKER = 'TESTENTRY-';
    private const INACTIVE_RI_CODE = 'TESTENTRY_INACTIVE_RI';
    private const INACTIVE_RD_CODE = 'TESTENTRY_INACTIVE_RD';

    private int $apiSystemId;

    /** logpass_id=1, pre-existing seed row, stable across environments (same convention as FinanceEntriesReadTest). */
    private int $employeeId = 1;
    private string $employeeName;

    private string $liveOffice;        // an active office number, e.g. '1'
    private ?string $inactiveOffice;   // an existing-but-administratively-closed office number, e.g. '3' (nullable: environment-dependent)
    private string $nonexistentOffice; // an office number verified to have no row in `offices`

    protected function setUp(): void
    {
        parent::setUp();

        $this->purgeDohRash();
        $this->purgeInactiveDictionaryCodes();

        $this->apiSystemId = (int) DB::table('logpass')->where('log', 'api_system')->value('logpass_id');
        $this->assertGreaterThan(0, $this->apiSystemId, 'fixture depends on the api_system logpass row seeded in Task 1');

        $this->employeeName = (string) DB::table('logpass')->where('logpass_id', $this->employeeId)->value('lp_fio');
        $this->assertNotSame('', $this->employeeName, 'fixture depends on logpass_id=1 existing');

        $this->resolveOfficeNumbers();
        $this->seedInactiveDictionaryCodes();
    }

    protected function tearDown(): void
    {
        $this->purgeDohRash();
        $this->purgeInactiveDictionaryCodes();
        parent::tearDown();
    }

    private function purgeDohRash(): void
    {
        DB::table('doh_rash')->where('info', 'LIKE', self::MARKER . '%')->delete();
    }

    private function purgeInactiveDictionaryCodes(): void
    {
        DB::table('rash_items')->where('ri_code', self::INACTIVE_RI_CODE)->delete();
        DB::table('doh_items')->where('rd_code', self::INACTIVE_RD_CODE)->delete();
    }

    private function seedInactiveDictionaryCodes(): void
    {
        DB::table('rash_items')->insert([
            'ri_order'    => 999999,
            'ri_text'     => 'TESTENTRY temp inactive expense code',
            'ri_code'     => self::INACTIVE_RI_CODE,
            'bank_yn'     => 0,
            'is_active'   => 0,
            'resertve_1'  => 0,
            'resertve_2'  => 0,
            'resertve_3'  => 0,
        ]);
        DB::table('doh_items')->insert([
            'rd_order'    => 999999,
            'rd_text'     => 'TESTENTRY temp inactive income code',
            'rd_code'     => self::INACTIVE_RD_CODE,
            'bank_yn'     => 0,
            'is_active'   => 0,
            'resertve_1'  => 0,
            'resertve_2'  => 0,
            'resertve_3'  => 0,
        ]);
    }

    private function resolveOfficeNumbers(): void
    {
        $offices = DB::table('offices')->where('type', 'office')->get(['number', 'active']);
        $this->assertNotEmpty($offices, 'fixture depends on offices table having type=office rows');

        $active = $offices->firstWhere('active', 1);
        $this->assertNotNull($active, 'fixture depends on at least one active office');
        $this->liveOffice = (string) $active->number;

        $inactive = $offices->firstWhere('active', 0);
        $this->inactiveOffice = $inactive !== null ? (string) $inactive->number : null;

        $maxNumber = (int) $offices->max('number');
        $candidate = $maxNumber + 1000;
        $this->assertFalse(
            DB::table('offices')->where('number', $candidate)->where('type', 'office')->exists(),
            'candidate nonexistent office number must really not exist'
        );
        $this->nonexistentOffice = (string) $candidate;
    }

    // ── payload builders ────────────────────────────────────────────────

    private function rashPayload(array $overrides = []): array
    {
        return array_merge([
            'type1'   => 'rash',
            'type2'   => 'other',
            'date'    => '2026-05-01',
            'amount'  => 100.00,
            'kassa'   => 'k1',
            'channel' => $this->liveOffice,
            'info'    => self::MARKER . 'RASH-DEFAULT',
        ], $overrides);
    }

    private function dohPayload(array $overrides = []): array
    {
        return array_merge([
            'type1'   => 'doh',
            'type2'   => 'other',
            'date'    => '2026-05-01',
            'amount'  => 50.00,
            'kassa'   => 'k1',
            'channel' => $this->liveOffice,
            'info'    => self::MARKER . 'DOH-DEFAULT',
        ], $overrides);
    }

    private function postEntries(array $entries): TestResponse
    {
        return $this->postMcp('finance/entries', ['entries' => $entries]);
    }

    private function patchMcp(int $id, array $body): TestResponse
    {
        return $this->patchJson('/api/mcp/v1/finance/entries/' . $id, $body, [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
    }

    private function deleteMcp(int $id): TestResponse
    {
        return $this->deleteJson('/api/mcp/v1/finance/entries/' . $id, [], [
            'Authorization' => 'Bearer ' . config('mcp.api_token'),
        ]);
    }

    /** Direct-DB fixture row for PATCH/DELETE setup (mirrors FinanceEntriesReadTest::insertEntry). */
    private function insertRow(array $overrides = []): int
    {
        $defaults = [
            'acc_date'   => strtotime('2026-05-01 10:00:00'),
            'amount'     => -40.00,
            'type1'      => 'rash',
            'type2'      => 'other',
            'channel'    => $this->liveOffice,
            'kassa'      => 'k1',
            'link_to'    => 0,
            'info'       => self::MARKER . 'ROW-DEFAULT',
            'cr_time'    => strtotime('2026-05-01 10:00:00'),
            'cr_who_id'  => $this->apiSystemId,
            'dr_name_id' => 0,
        ];

        return DB::table('doh_rash')->insertGetId(array_merge($defaults, $overrides));
    }

    // ── 1. POST valid rash row → created; amount negative; type1='rash' ────

    public function test_01_post_valid_rash_row_is_created_with_negative_amount(): void
    {
        $r = $this->postEntries([$this->rashPayload(['info' => self::MARKER . 'CREATE-RASH-HAPPY'])]);
        $r->assertStatus(200);

        $item = $r->json('data.0');
        $this->assertSame('created', $item['status']);
        $this->assertNotNull($item['dr_id']);

        $row = DB::table('doh_rash')->where('dr_id', $item['dr_id'])->first();
        $this->assertNotNull($row, 'row must actually exist in doh_rash');
        $this->assertSame('rash', $row->type1);
        $this->assertEqualsWithDelta(-100.00, (float) $row->amount, 0.001);
    }

    // ── 2. POST valid doh row → created; amount positive ───────────────────

    public function test_02_post_valid_doh_row_is_created_with_positive_amount(): void
    {
        $r = $this->postEntries([$this->dohPayload(['info' => self::MARKER . 'CREATE-DOH-HAPPY'])]);
        $r->assertStatus(200);

        $item = $r->json('data.0');
        $this->assertSame('created', $item['status']);

        $row = DB::table('doh_rash')->where('dr_id', $item['dr_id'])->first();
        $this->assertSame('doh', $row->type1);
        $this->assertEqualsWithDelta(50.00, (float) $row->amount, 0.001);
    }

    // ── 3. batch of 3 valid → three created, three distinct dr_ids ─────────

    public function test_03_post_batch_of_three_valid_rows_creates_three_distinct_rows(): void
    {
        $entries = [
            $this->rashPayload(['info' => self::MARKER . 'BATCH3-A']),
            $this->dohPayload(['info' => self::MARKER . 'BATCH3-B']),
            $this->rashPayload(['info' => self::MARKER . 'BATCH3-C', 'type2' => 'op_rash']),
        ];

        $r = $this->postEntries($entries);
        $r->assertStatus(200);

        $data = $r->json('data');
        $this->assertCount(3, $data);
        foreach ($data as $item) {
            $this->assertSame('created', $item['status']);
        }

        $ids = array_column($data, 'dr_id');
        $this->assertCount(3, array_unique($ids), 'each created row must get its own dr_id');
    }

    // ── 4. server-set fields: cr_who_id = api_system id, cr_time non-zero ──

    public function test_04_server_sets_cr_who_id_and_cr_time(): void
    {
        $r = $this->postEntries([$this->rashPayload(['info' => self::MARKER . 'SERVER-SET'])]);
        $id = $r->json('data.0.dr_id');

        $row = DB::table('doh_rash')->where('dr_id', $id)->first();
        $this->assertSame($this->apiSystemId, (int) $row->cr_who_id);
        $this->assertGreaterThan(0, (int) $row->cr_time);
    }

    // ── 5. client cannot override server-set fields ─────────────────────────

    public function test_05_client_supplied_cr_who_id_and_cr_time_are_ignored(): void
    {
        $before = time();

        $r = $this->postEntries([$this->rashPayload([
            'info'      => self::MARKER . 'CLIENT-OVERRIDE',
            'cr_who_id' => 999999,
            'cr_time'   => 123,
        ])]);
        $id = $r->json('data.0.dr_id');

        $row = DB::table('doh_rash')->where('dr_id', $id)->first();
        $this->assertSame($this->apiSystemId, (int) $row->cr_who_id, 'client-supplied cr_who_id must be ignored');
        $this->assertNotSame(123, (int) $row->cr_time, 'client-supplied cr_time must be ignored');
        $this->assertGreaterThanOrEqual($before, (int) $row->cr_time, 'cr_time must be server-generated "now"');
    }

    // ── 6. dr_name_id / link_to default to 0, round-trip when supplied ─────

    public function test_06_dr_name_id_and_link_to_default_and_roundtrip(): void
    {
        $rOmitted = $this->postEntries([$this->rashPayload(['info' => self::MARKER . 'DEFAULTS-OMITTED'])]);
        $rowOmitted = DB::table('doh_rash')->where('dr_id', $rOmitted->json('data.0.dr_id'))->first();
        $this->assertSame(0, (int) $rowOmitted->dr_name_id);
        $this->assertSame(0, (int) $rowOmitted->link_to);

        $rSupplied = $this->postEntries([$this->rashPayload([
            'info'       => self::MARKER . 'DEFAULTS-SUPPLIED',
            'dr_name_id' => $this->employeeId,
            'link_to'    => 4242,
        ])]);
        $rowSupplied = DB::table('doh_rash')->where('dr_id', $rSupplied->json('data.0.dr_id'))->first();
        $this->assertSame($this->employeeId, (int) $rowSupplied->dr_name_id);
        $this->assertSame(4242, (int) $rowSupplied->link_to);
    }

    // ── 7. type1 outside whitelist → invalid ────────────────────────────────

    public function test_07_type1_outside_whitelist_is_invalid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'type1' => 'shift_plus',
            'info'  => self::MARKER . 'INVALID-TYPE1-WHITELIST',
        ])]);
        $r->assertStatus(200);

        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertArrayHasKey('type1', $item['errors']);
    }

    // ── 8. type1 missing / empty / garbage → invalid ────────────────────────

    public function test_08_type1_missing_empty_or_garbage_is_invalid(): void
    {
        $missing = $this->rashPayload(['info' => self::MARKER . 'INVALID-TYPE1-MISSING']);
        unset($missing['type1']);

        $cases = [
            'missing' => $missing,
            'empty'   => $this->rashPayload(['type1' => '', 'info' => self::MARKER . 'INVALID-TYPE1-EMPTY']),
            'garbage' => $this->rashPayload(['type1' => 'zzz', 'info' => self::MARKER . 'INVALID-TYPE1-GARBAGE']),
        ];

        foreach ($cases as $label => $payload) {
            $r = $this->postEntries([$payload]);
            $item = $r->json('data.0');
            $this->assertSame('invalid', $item['status'], "case: $label");
            $this->assertArrayHasKey('type1', $item['errors'], "case: $label");
        }
    }

    // ── 9. type2 from wrong dictionary → invalid ────────────────────────────

    public function test_09_type2_from_wrong_dictionary_is_invalid(): void
    {
        // 'bank_interest' is a doh_items code; sending it with type1=rash must fail.
        $r = $this->postEntries([$this->rashPayload([
            'type2' => 'bank_interest',
            'info'  => self::MARKER . 'INVALID-TYPE2-DICT',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertArrayHasKey('type2', $item['errors']);
    }

    // ── 10. type2 is_active=0 → invalid ─────────────────────────────────────

    public function test_10_type2_inactive_code_is_invalid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'type2' => self::INACTIVE_RI_CODE,
            'info'  => self::MARKER . 'INVALID-TYPE2-INACTIVE',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertArrayHasKey('type2', $item['errors']);
    }

    // ── 11. amount negative → invalid (not silently flipped) ───────────────

    public function test_11_negative_amount_is_invalid_not_silently_flipped(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'amount' => -50,
            'info'   => self::MARKER . 'INVALID-AMOUNT-NEGATIVE',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertArrayHasKey('amount', $item['errors']);
    }

    // ── 12. amount zero → invalid ────────────────────────────────────────

    public function test_12_zero_amount_is_invalid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'amount' => 0,
            'info'   => self::MARKER . 'INVALID-AMOUNT-ZERO',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertArrayHasKey('amount', $item['errors']);
    }

    // ── 13. amount non-numeric → invalid ────────────────────────────────────

    public function test_13_non_numeric_amount_is_invalid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'amount' => 'abc',
            'info'   => self::MARKER . 'INVALID-AMOUNT-NONNUMERIC',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertArrayHasKey('amount', $item['errors']);
    }

    // ── 14. amount decimal precision: 3dp rejected, overflow rejected ──────

    public function test_14_amount_decimal_precision_is_enforced(): void
    {
        $rThreeDecimals = $this->postEntries([$this->rashPayload([
            'amount' => 10.123,
            'info'   => self::MARKER . 'INVALID-AMOUNT-3DP',
        ])]);
        $itemA = $rThreeDecimals->json('data.0');
        $this->assertSame('invalid', $itemA['status'], '3 decimal places must be rejected');
        $this->assertArrayHasKey('amount', $itemA['errors']);

        // decimal(11,2) allows at most 9 integer digits; this has 10.
        $rOverflow = $this->postEntries([$this->rashPayload([
            'amount' => 1234567890.12,
            'info'   => self::MARKER . 'INVALID-AMOUNT-OVERFLOW',
        ])]);
        $itemB = $rOverflow->json('data.0');
        $this->assertSame('invalid', $itemB['status'], 'amount exceeding decimal(11,2) must be rejected');
        $this->assertArrayHasKey('amount', $itemB['errors']);
    }

    // ── 15. kassa outside whitelist → invalid ───────────────────────────────

    public function test_15_kassa_outside_whitelist_is_invalid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'kassa' => 'HZ',
            'info'  => self::MARKER . 'INVALID-KASSA',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertArrayHasKey('kassa', $item['errors']);
    }

    // ── 16. channel outside allowed set → invalid ───────────────────────────

    public function test_16_channel_outside_allowed_set_is_invalid(): void
    {
        $cases = [
            'HZ'                 => $this->rashPayload(['channel' => 'HZ', 'info' => self::MARKER . 'INVALID-CHANNEL-HZ']),
            'nonexistent-office' => $this->rashPayload(['channel' => $this->nonexistentOffice, 'info' => self::MARKER . 'INVALID-CHANNEL-99']),
        ];

        foreach ($cases as $label => $payload) {
            $r = $this->postEntries([$payload]);
            $item = $r->json('data.0');
            $this->assertSame('invalid', $item['status'], "case: $label");
            $this->assertArrayHasKey('channel', $item['errors'], "case: $label");
        }
    }

    // ── 17. malformed date → invalid ────────────────────────────────────────

    public function test_17_malformed_date_is_invalid(): void
    {
        foreach (['2026-13-01', '2026-02-30', '07/06/2026'] as $badDate) {
            $r = $this->postEntries([$this->rashPayload([
                'date' => $badDate,
                'info' => self::MARKER . 'INVALID-DATE',
            ])]);
            $item = $r->json('data.0');
            $this->assertSame('invalid', $item['status'], "date: $badDate");
            $this->assertArrayHasKey('date', $item['errors'], "date: $badDate");
        }
    }

    // ── 18. missing required field, each in turn → invalid, field named ────

    public function test_18_missing_required_field_is_invalid_and_named_in_errors(): void
    {
        $requiredFields = ['type1', 'type2', 'date', 'amount', 'kassa', 'channel', 'info'];

        foreach ($requiredFields as $field) {
            $payload = $this->rashPayload(['info' => self::MARKER . 'MISSING-' . strtoupper($field)]);
            unset($payload[$field]);

            $r = $this->postEntries([$payload]);
            $item = $r->json('data.0');
            $this->assertSame('invalid', $item['status'], "missing field: $field");
            $this->assertArrayHasKey($field, $item['errors'], "missing field: $field");
        }
    }

    // ── 19. mixed batch: valid created, invalid reported, still HTTP 200 ───

    public function test_19_mixed_batch_reports_each_item_and_returns_200(): void
    {
        $entries = [
            $this->rashPayload(['info' => self::MARKER . 'MIXED-VALID-1']),
            $this->rashPayload(['type1' => 'bogus', 'info' => self::MARKER . 'MIXED-INVALID']),
            $this->dohPayload(['info' => self::MARKER . 'MIXED-VALID-2']),
        ];

        $r = $this->postEntries($entries);
        $r->assertStatus(200);

        $data = $r->json('data');
        $this->assertCount(3, $data);
        $this->assertSame(0, $data[0]['index']);
        $this->assertSame('created', $data[0]['status']);
        $this->assertSame(1, $data[1]['index']);
        $this->assertSame('invalid', $data[1]['status']);
        $this->assertSame(2, $data[2]['index']);
        $this->assertSame('created', $data[2]['status']);

        $this->assertSame(3, $r->json('meta.total_rows'));
        $this->assertSame(2, $r->json('meta.summary.created'));
        $this->assertSame(1, $r->json('meta.summary.invalid'));
    }

    // ── 19a. info missing / empty / whitespace-only → invalid ──────────────

    public function test_19a_info_missing_empty_or_whitespace_is_invalid(): void
    {
        $missing = $this->rashPayload();
        unset($missing['info']);

        // NOTE on the cleanup marker (see class docblock / self::MARKER): the
        // 'empty' and 'whitespace' cases below deliberately do NOT carry the
        // TESTENTRY- prefix. Prefixing would make the string non-empty /
        // non-whitespace-only, defeating the very condition under test. Both
        // cases assert status === 'invalid' immediately below, i.e. by this
        // suite's own contract no row is ever written to doh_rash for them,
        // so purgeDohRash() has nothing to clean up. If a future validation
        // bug ever let one through anyway, that bug would already surface as
        // a failing assertSame('invalid', ...) here — it would not be masked,
        // it would just (in that specific broken-implementation scenario)
        // additionally leave an unmarked residual row, an accepted trade-off
        // since these two values cannot carry a marker without invalidating
        // the test itself.
        $cases = [
            'missing'    => $missing,
            'empty'      => $this->rashPayload(['info' => '']),
            'whitespace' => $this->rashPayload(['info' => '   ']),
        ];

        foreach ($cases as $label => $payload) {
            $r = $this->postEntries([$payload]);
            $item = $r->json('data.0');
            $this->assertSame('invalid', $item['status'], "case: $label");
            $this->assertArrayHasKey('info', $item['errors'], "case: $label");
        }
    }

    // ── 19a2. info length boundary: 2001 invalid, 2000 created + untruncated ──

    public function test_19a2_info_length_boundary_2000_ok_2001_invalid(): void
    {
        // Unlike the empty/whitespace cases in 19a, this fixture's content
        // doesn't need to be pure — only its total length matters for the
        // boundary being tested — so it carries the TESTENTRY- marker,
        // letting purgeDohRash() find and remove it if a Task 5 validation
        // bug ever lets a 2001-char row land in doh_rash despite being
        // expected 'invalid'.
        $tooLong = self::MARKER . str_repeat('x', 2001 - strlen(self::MARKER));
        $this->assertSame(2001, strlen($tooLong), 'sanity check on the fixture string itself');
        $rInvalid = $this->postEntries([$this->rashPayload(['info' => $tooLong])]);
        $itemInvalid = $rInvalid->json('data.0');
        $this->assertSame('invalid', $itemInvalid['status']);
        $this->assertArrayHasKey('info', $itemInvalid['errors']);

        $prefix = self::MARKER . 'INFO2000-';
        $exactly2000 = $prefix . str_repeat('y', 2000 - strlen($prefix));
        $this->assertSame(2000, strlen($exactly2000), 'sanity check on the fixture string itself');

        $rValid = $this->postEntries([$this->rashPayload(['info' => $exactly2000])]);
        $itemValid = $rValid->json('data.0');
        $this->assertSame('created', $itemValid['status']);

        $row = DB::table('doh_rash')->where('dr_id', $itemValid['dr_id'])->first();
        $this->assertSame(2000, strlen($row->info), 'stored info must round-trip at exactly 2000 chars, untruncated');
        $this->assertSame($exactly2000, $row->info);
    }

    // ── 19b. channel='bank' + kassa='k2' → invalid (contradictory pair) ────

    public function test_19b_channel_bank_with_kassa_k2_is_invalid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'channel' => 'bank',
            'kassa'   => 'k2',
            'info'    => self::MARKER . 'PAIR-BANK-K2',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        // The brief doesn't mandate which of the two fields carries the pairing
        // error, only that the pair is rejected — accept either, but require one.
        $this->assertTrue(
            array_key_exists('channel', $item['errors']) || array_key_exists('kassa', $item['errors']),
            'expected the channel/kassa pairing violation to be reported against one of the two fields'
        );
    }

    // ── 19c. channel=office + kassa='bank' → invalid (contradictory pair) ──

    public function test_19c_channel_office_with_kassa_bank_is_invalid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'channel' => $this->liveOffice,
            'kassa'   => 'bank',
            'info'    => self::MARKER . 'PAIR-OFFICE-BANK',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertTrue(
            array_key_exists('channel', $item['errors']) || array_key_exists('kassa', $item['errors']),
            'expected the channel/kassa pairing violation to be reported against one of the two fields'
        );
    }

    // ── 19d. channel='bank' + kassa='bank' → created (the valid bank pair) ─

    public function test_19d_channel_bank_kassa_bank_is_valid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'channel' => 'bank',
            'kassa'   => 'bank',
            'info'    => self::MARKER . 'PAIR-BANK-BANK',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('created', $item['status']);

        $row = DB::table('doh_rash')->where('dr_id', $item['dr_id'])->first();
        $this->assertSame('bank', $row->channel);
        $this->assertSame('bank', $row->kassa);
    }

    // ── 19e. channel='cur' + kassa='k2' → created (valid courier-to-till) ──

    public function test_19e_channel_cur_kassa_k2_is_valid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'channel' => 'cur',
            'kassa'   => 'k2',
            'info'    => self::MARKER . 'PAIR-CUR-K2',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('created', $item['status']);

        $row = DB::table('doh_rash')->where('dr_id', $item['dr_id'])->first();
        $this->assertSame('cur', $row->channel);
        $this->assertSame('k2', $row->kassa);
    }

    // ── 19f. type2='zpl' without dr_name_id → invalid, names dr_name_id ────

    public function test_19f_zpl_without_dr_name_id_is_invalid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'type2' => 'zpl',
            'info'  => self::MARKER . 'ZPL-NO-EMPLOYEE',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertArrayHasKey('dr_name_id', $item['errors']);
    }

    // ── 19g. type2='avans' without dr_name_id → invalid ─────────────────────

    public function test_19g_avans_without_dr_name_id_is_invalid(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'type2' => 'avans',
            'info'  => self::MARKER . 'AVANS-NO-EMPLOYEE',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertArrayHasKey('dr_name_id', $item['errors']);
    }

    // ── 19h. type2='zpl' with valid dr_name_id → created, round-trips ──────

    public function test_19h_zpl_with_valid_dr_name_id_is_created(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'type2'      => 'zpl',
            'dr_name_id' => $this->employeeId,
            'info'       => self::MARKER . 'ZPL-WITH-EMPLOYEE',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('created', $item['status']);

        $row = DB::table('doh_rash')->where('dr_id', $item['dr_id'])->first();
        $this->assertSame($this->employeeId, (int) $row->dr_name_id);
    }

    // ── 19i. dr_name_id referencing nonexistent logpass_id → invalid ───────

    public function test_19i_dr_name_id_referencing_nonexistent_logpass_is_invalid(): void
    {
        $bogusId = 999999999;
        $this->assertFalse(DB::table('logpass')->where('logpass_id', $bogusId)->exists(), 'sanity check on the fixture itself');

        $r = $this->postEntries([$this->rashPayload([
            'type2'      => 'zpl',
            'dr_name_id' => $bogusId,
            'info'       => self::MARKER . 'DR-NAME-ID-BOGUS',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('invalid', $item['status']);
        $this->assertArrayHasKey('dr_name_id', $item['errors']);
    }

    // ── 19j. non-salary type2 without dr_name_id → created, stored as 0 ────

    public function test_19j_non_salary_type2_without_dr_name_id_defaults_to_zero(): void
    {
        $r = $this->postEntries([$this->rashPayload([
            'type2' => 'other',
            'info'  => self::MARKER . 'NON-SALARY-NO-EMPLOYEE',
        ])]);
        $item = $r->json('data.0');
        $this->assertSame('created', $item['status']);

        $row = DB::table('doh_rash')->where('dr_id', $item['dr_id'])->first();
        $this->assertSame(0, (int) $row->dr_name_id);
    }

    // ── 20. entries empty array → 422 ───────────────────────────────────────

    public function test_20_entries_empty_array_is_422(): void
    {
        $this->postMcp('finance/entries', ['entries' => []])->assertStatus(422);
    }

    // ── 21. entries 201 items → 422; 200 items → 200 ────────────────────────

    public function test_21_entries_batch_size_limit_is_enforced(): void
    {
        $over = [];
        for ($i = 0; $i < 201; $i++) {
            $over[] = $this->rashPayload(['info' => self::MARKER . 'BATCH201-' . $i]);
        }
        $this->postMcp('finance/entries', ['entries' => $over])->assertStatus(422);

        $atLimit = [];
        for ($i = 0; $i < 200; $i++) {
            $atLimit[] = $this->rashPayload(['info' => self::MARKER . 'BATCH200-' . $i]);
        }
        $rAtLimit = $this->postMcp('finance/entries', ['entries' => $atLimit]);
        $rAtLimit->assertStatus(200);
        $this->assertSame(200, $rAtLimit->json('meta.total_rows'));
    }

    // ── 22. PATCH changes only supplied fields ──────────────────────────────

    public function test_22_patch_updates_only_supplied_fields(): void
    {
        $id = $this->insertRow([
            'type1'   => 'rash',
            'type2'   => 'other',
            'amount'  => -60.00,
            'kassa'   => 'k1',
            'channel' => $this->liveOffice,
            'info'    => self::MARKER . 'PATCH-PARTIAL',
        ]);

        $r = $this->patchMcp($id, ['kassa' => 'k2']);
        $r->assertStatus(200);

        $row = DB::table('doh_rash')->where('dr_id', $id)->first();
        $this->assertSame('k2', $row->kassa, 'the one supplied field must change');
        $this->assertSame('rash', $row->type1);
        $this->assertSame('other', $row->type2);
        $this->assertEqualsWithDelta(-60.00, (float) $row->amount, 0.001);
        $this->assertSame($this->liveOffice, (string) $row->channel);
        $this->assertSame(self::MARKER . 'PATCH-PARTIAL', $row->info);
    }

    // ── 23. PATCH re-normalizes sign when type1 flips rash→doh ─────────────

    public function test_23_patch_renormalizes_sign_when_type1_flips(): void
    {
        $id = $this->insertRow([
            'type1'  => 'rash',
            'type2'  => 'other',
            'amount' => -40.00,
            'info'   => self::MARKER . 'PATCH-FLIP-SIGN',
        ]);

        // amount is deliberately omitted: the magnitude must carry over from the
        // existing row while the sign is recomputed from the new type1.
        $r = $this->patchMcp($id, ['type1' => 'doh', 'type2' => 'bank_interest']);
        $r->assertStatus(200);

        $row = DB::table('doh_rash')->where('dr_id', $id)->first();
        $this->assertSame('doh', $row->type1);
        $this->assertEqualsWithDelta(40.00, (float) $row->amount, 0.001);
    }

    // ── 24. PATCH with empty body → 422 ─────────────────────────────────────

    public function test_24_patch_empty_body_is_422(): void
    {
        $id = $this->insertRow(['info' => self::MARKER . 'PATCH-EMPTY-BODY']);

        $r = $this->patchMcp($id, []);
        $r->assertStatus(422);

        $this->assertSame(0, DB::table('doh_rash_history')->where('dr_id', $id)->count(), 'a rejected patch must not journal anything');
    }

    // ── 25. PATCH on unknown id → 404 ───────────────────────────────────────

    public function test_25_patch_unknown_id_is_404(): void
    {
        $this->patchMcp(999999999, ['kassa' => 'k1'])->assertStatus(404);
    }

    // ── 26. PATCH validates the same field rules (bad kassa → 422) ─────────

    public function test_26_patch_validates_field_rules(): void
    {
        $id = $this->insertRow(['kassa' => 'k1', 'info' => self::MARKER . 'PATCH-BAD-KASSA']);

        $r = $this->patchMcp($id, ['kassa' => 'HZ']);
        $r->assertStatus(422);

        $row = DB::table('doh_rash')->where('dr_id', $id)->first();
        $this->assertSame('k1', $row->kassa, 'rejected patch must not have been applied');
    }

    // ── 26a. PATCH enforces pairing against the MERGED row, not the patch body ──

    public function test_26a_patch_pairing_validated_against_merged_row(): void
    {
        $id = $this->insertRow([
            'channel' => $this->liveOffice,
            'kassa'   => 'k1',
            'info'    => self::MARKER . 'PATCH-PAIR-MERGED',
        ]);

        // The patch body alone ({"kassa":"bank"}) looks fine in isolation; it's
        // only invalid once merged with the row's existing channel=<office>.
        $r = $this->patchMcp($id, ['kassa' => 'bank']);
        $r->assertStatus(422);

        $row = DB::table('doh_rash')->where('dr_id', $id)->first();
        $this->assertSame('k1', $row->kassa, 'rejected patch must not have been applied');
    }

    // ── 26b. PATCH conditional dr_name_id requirement applies to the merged row ──

    public function test_26b_patch_type2_zpl_on_row_with_dr_name_id_zero_is_422(): void
    {
        $id = $this->insertRow([
            'type1'      => 'rash',
            'type2'      => 'other',
            'dr_name_id' => 0,
            'info'       => self::MARKER . 'PATCH-ZPL-MERGED',
        ]);

        $r = $this->patchMcp($id, ['type2' => 'zpl']);
        $r->assertStatus(422);

        $row = DB::table('doh_rash')->where('dr_id', $id)->first();
        $this->assertSame('other', $row->type2, 'rejected patch must not have been applied');
    }

    // ── 27. PATCH writes exactly one update history row w/ before/after ────

    public function test_27_patch_writes_exactly_one_update_history_row_with_before_after(): void
    {
        $id = $this->insertRow([
            'type1'   => 'rash',
            'type2'   => 'other',
            'amount'  => -80.00,
            'kassa'   => 'k1',
            'channel' => $this->liveOffice,
            'info'    => self::MARKER . 'PATCH-JOURNAL',
        ]);

        $r = $this->patchMcp($id, ['kassa' => 'k2']);
        $r->assertStatus(200);

        $history = DB::table('doh_rash_history')->where('dr_id', $id)->get();
        $this->assertCount(1, $history);

        $entry = $history->first();
        $this->assertSame('update', $entry->action);

        $before = json_decode($entry->before_json, true);
        $after  = json_decode($entry->after_json, true);

        $this->assertSame('k1', $before['kassa']);
        $this->assertSame('k2', $after['kassa']);
        $this->assertEqualsWithDelta(-80.00, (float) $before['amount'], 0.001);
        $this->assertEqualsWithDelta(-80.00, (float) $after['amount'], 0.001);
    }

    // ── 28. DELETE physically removes the row ───────────────────────────────

    public function test_28_delete_physically_removes_row(): void
    {
        $id = $this->insertRow(['info' => self::MARKER . 'DELETE-BASIC']);

        $r = $this->deleteMcp($id);
        $r->assertStatus(200);

        $this->assertDatabaseMissing('doh_rash', ['dr_id' => $id]);
    }

    // ── 29. DELETE on unknown id → 404 ──────────────────────────────────────

    public function test_29_delete_unknown_id_is_404(): void
    {
        $this->deleteMcp(999999999)->assertStatus(404);
    }

    // ── 30. DELETE writes exactly one delete history row ────────────────────

    public function test_30_delete_writes_exactly_one_delete_history_row(): void
    {
        $id = $this->insertRow([
            'amount' => -33.00,
            'info'   => self::MARKER . 'DELETE-JOURNAL',
        ]);

        $r = $this->deleteMcp($id);
        $r->assertStatus(200);

        $history = DB::table('doh_rash_history')->where('dr_id', $id)->get();
        $this->assertCount(1, $history);

        $entry = $history->first();
        $this->assertSame('delete', $entry->action);
        $this->assertNull($entry->after_json);

        $before = json_decode($entry->before_json, true);
        $this->assertEqualsWithDelta(-33.00, (float) $before['amount'], 0.001);
    }

    // ── 31. deleted row is fully reconstructable from before_json ──────────

    public function test_31_deleted_row_is_fully_reconstructable_from_before_json(): void
    {
        $id = $this->insertRow([
            'acc_date'   => strtotime('2026-05-02 09:00:00'),
            'amount'     => -77.50,
            'type1'      => 'rash',
            'type2'      => 'op_rash',
            'channel'    => $this->liveOffice,
            'kassa'      => 'k1',
            'link_to'    => 321,
            'dr_name_id' => $this->employeeId,
            'info'       => self::MARKER . 'DELETE-RECONSTRUCT',
            'cr_time'    => strtotime('2026-05-02 09:05:00'),
            'cr_who_id'  => $this->apiSystemId,
        ]);

        $originalRow = (array) DB::table('doh_rash')->where('dr_id', $id)->first();

        $r = $this->deleteMcp($id);
        $r->assertStatus(200);

        $entry = DB::table('doh_rash_history')
            ->where('dr_id', $id)
            ->where('action', 'delete')
            ->first();
        $this->assertNotNull($entry);

        $snapshot = json_decode($entry->before_json, true);

        foreach ($originalRow as $column => $value) {
            $this->assertArrayHasKey($column, $snapshot, "before_json missing column: $column");
            $this->assertEquals($value, $snapshot[$column], "before_json value mismatch for column: $column");
        }
    }

    // ── 32. POST writes NO history row (inserts are not journalled) ────────

    public function test_32_post_writes_no_history_row(): void
    {
        $before = DB::table('doh_rash_history')->count();

        $r = $this->postEntries([$this->rashPayload(['info' => self::MARKER . 'NO-JOURNAL-ON-CREATE'])]);
        $r->assertStatus(200);
        $item = $r->json('data.0');
        $this->assertSame('created', $item['status']);
        $id = $item['dr_id'];

        $this->assertSame($before, DB::table('doh_rash_history')->count(), 'POST must not write any journal row at all');
        $this->assertSame(0, DB::table('doh_rash_history')->where('dr_id', $id)->count());
    }

    // ── 33. journal rows carry actor_user_id=api_system id, source=mcp_api ──

    public function test_33_journal_rows_carry_actor_and_source(): void
    {
        $id = $this->insertRow(['info' => self::MARKER . 'JOURNAL-ACTOR']);

        $r = $this->patchMcp($id, ['kassa' => 'card']);
        $r->assertStatus(200);

        $entry = DB::table('doh_rash_history')->where('dr_id', $id)->first();
        $this->assertSame($this->apiSystemId, (int) $entry->actor_user_id);
        $this->assertSame('mcp_api', $entry->source);
    }

    // ── 34. POST/PATCH/DELETE each require the Bearer token ────────────────

    public function test_34_write_endpoints_require_token(): void
    {
        $id = $this->insertRow(['info' => self::MARKER . 'AUTH-CHECK']);

        $this->postJson('/api/mcp/v1/finance/entries', ['entries' => [$this->rashPayload()]])
            ->assertStatus(401);
        $this->patchJson('/api/mcp/v1/finance/entries/' . $id, ['kassa' => 'k2'])
            ->assertStatus(401);
        $this->deleteJson('/api/mcp/v1/finance/entries/' . $id)
            ->assertStatus(401);
    }

    // ── supplemental: ambiguity call on `channel` = existing-but-closed office ──

    /**
     * Not one of the 34 numbered brief behaviors. Codifies the permissive
     * reading chosen for item 16's "channel outside the allowed set": office 3
     * exists as a real row in `offices` but is administratively closed
     * (active=0). `channel` is treated as a location identifier (existence
     * gate), not an operating-status gate — see task report for the explicit
     * ambiguity call.
     */
    public function test_channel_accepts_existing_but_administratively_closed_office(): void
    {
        if ($this->inactiveOffice === null) {
            $this->markTestSkipped('no inactive office row present in this environment to exercise this case');
        }

        $r = $this->postEntries([$this->rashPayload([
            'channel' => $this->inactiveOffice,
            'info'    => self::MARKER . 'CHANNEL-INACTIVE-OFFICE-OK',
        ])]);

        $item = $r->json('data.0');
        $this->assertSame('created', $item['status']);
    }
}
