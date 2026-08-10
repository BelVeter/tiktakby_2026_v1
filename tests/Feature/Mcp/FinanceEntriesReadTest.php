<?php

namespace Tests\Feature\Mcp;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * RED tests for the read half of the finance ledger API (`doh_rash`):
 *
 *   GET /finance/entries
 *   GET /finance/entries/{id}
 *   GET /finance/entries/history
 *
 * None of these routes exist yet (Task 3 implements them) — every test here
 * is expected to fail. An unregistered GET path falls through to the app's
 * global fallback route and returns 404 (pre-existing behaviour, not a
 * defect) rather than a PHP error; that's fine. What must NOT happen is a
 * fatal/syntax error inside this file itself.
 *
 * ── MyISAM caveat ──────────────────────────────────────────────────────────
 * `doh_rash` is MyISAM and does not honour transactions, so DatabaseTransactions
 * will NOT roll back rows inserted into it. Every fixture row this test creates
 * is tagged with an `info` value starting with the literal prefix `TESTENTRY-`,
 * and that prefix is deleted in BOTH setUp() and tearDown() (setUp() guards
 * against leftovers from a previously-crashed run; tearDown() cleans up after
 * a normal run). `doh_rash_history` (the journal table added in Task 1) is
 * InnoDB, so fixture rows written there are covered by DatabaseTransactions
 * and need no manual cleanup.
 */
class FinanceEntriesReadTest extends McpTestCase
{
    use DatabaseTransactions;

    private const MARKER = 'TESTENTRY-';

    /** @var array<string,int> scenario name => doh_rash.dr_id */
    private array $ids = [];

    /** Text tiktak.by currently has on file for rash_items.ri_code='tovar' (used by the canonical fixture row). */
    private string $tovarRashText;

    /** lp_fio of the logpass row used as cr_who_id in fixtures. */
    private string $fixtureUserName;
    private int $fixtureUserId = 1; // logpass_id=1, pre-existing seed row, stable across environments

    protected function setUp(): void
    {
        parent::setUp();

        $this->purgeDohRash();

        $this->tovarRashText    = (string) DB::table('rash_items')->where('ri_code', 'tovar')->value('ri_text');
        $this->fixtureUserName  = (string) DB::table('logpass')->where('logpass_id', $this->fixtureUserId)->value('lp_fio');

        $this->assertNotSame('', $this->tovarRashText, 'fixture depends on rash_items.ri_code=tovar existing');
        $this->assertNotSame('', $this->fixtureUserName, 'fixture depends on logpass_id=1 existing');

        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        $this->purgeDohRash();
        parent::tearDown();
    }

    private function purgeDohRash(): void
    {
        DB::table('doh_rash')->where('info', 'LIKE', self::MARKER . '%')->delete();
    }

    /**
     * One row per test scenario. Every `info` value starts with self::MARKER
     * so purgeDohRash() always finds it, and most scenarios additionally share
     * a scenario-specific substring so tests can scope a query to exactly
     * their own rows via `search`, independent of whatever real data already
     * lives in the shared dev `doh_rash` table.
     */
    private function seedFixtures(): void
    {
        // ── Canonical row: row shape, envelope, amount sign, date rendering, get-by-id ──
        $canonicalAcc = Carbon::parse('2026-05-05 12:00:00', 'Europe/Minsk');
        $canonicalCr  = Carbon::parse('2026-05-05 12:05:00', 'Europe/Minsk');
        $this->ids['canonical'] = $this->insertEntry([
            'acc_date'   => $canonicalAcc->timestamp,
            'cr_time'    => $canonicalCr->timestamp,
            'amount'     => -123.45,
            'type1'      => 'rash',
            'type2'      => 'tovar',
            'channel'    => '1',
            'kassa'      => 'k2',
            'link_to'    => 555,
            'dr_name_id' => 777,
            'cr_who_id'  => $this->fixtureUserId,
            'info'       => self::MARKER . 'CANONICAL',
        ]);

        // ── from/to window ──
        $this->ids['windowIn1'] = $this->insertEntry([
            'acc_date' => Carbon::parse('2026-03-11 10:00:00', 'Europe/Minsk')->timestamp,
            'info'     => self::MARKER . 'WINDOW-IN-1',
        ]);
        $this->ids['windowIn2'] = $this->insertEntry([
            'acc_date' => Carbon::parse('2026-03-14 15:00:00', 'Europe/Minsk')->timestamp,
            'info'     => self::MARKER . 'WINDOW-IN-2',
        ]);
        $this->ids['windowOutBefore'] = $this->insertEntry([
            'acc_date' => Carbon::parse('2026-03-01 10:00:00', 'Europe/Minsk')->timestamp,
            'info'     => self::MARKER . 'WINDOW-OUT-BEFORE',
        ]);
        $this->ids['windowOutAfter'] = $this->insertEntry([
            'acc_date' => Carbon::parse('2026-03-20 10:00:00', 'Europe/Minsk')->timestamp,
            'info'     => self::MARKER . 'WINDOW-OUT-AFTER',
        ]);

        // ── type1 filter ──
        $this->ids['type1Rash'] = $this->insertEntry([
            'type1' => 'rash', 'amount' => -15,
            'info'  => self::MARKER . 'TYPE1-RASH',
        ]);
        $this->ids['type1Doh'] = $this->insertEntry([
            'type1' => 'doh', 'type2' => 'other', 'amount' => 15,
            'info'  => self::MARKER . 'TYPE1-DOH',
        ]);

        // ── type2 filter ──
        $this->ids['type2Tovar'] = $this->insertEntry([
            'type2' => 'tovar', 'amount' => -20,
            'info'  => self::MARKER . 'TYPE2-TOVAR',
        ]);
        $this->ids['type2Other'] = $this->insertEntry([
            'type1' => 'rash', 'type2' => 'other', 'amount' => -25,
            'info'  => self::MARKER . 'TYPE2-OTHER',
        ]);

        // ── kassa filter ──
        $this->ids['kassaK1'] = $this->insertEntry([
            'kassa' => 'k1', 'amount' => -5,
            'info'  => self::MARKER . 'KASSA-K1',
        ]);
        $this->ids['kassaBank'] = $this->insertEntry([
            'kassa' => 'bank', 'amount' => -5,
            'info'  => self::MARKER . 'KASSA-BANK',
        ]);

        // ── channel filter ──
        $this->ids['channel1'] = $this->insertEntry([
            'channel' => '1', 'amount' => -5,
            'info'    => self::MARKER . 'CHANNEL-1',
        ]);
        $this->ids['channelCur'] = $this->insertEntry([
            'channel' => 'cur', 'amount' => -5,
            'info'    => self::MARKER . 'CHANNEL-CUR',
        ]);

        // ── search filter (LIKE on info) ──
        $this->ids['searchApple'] = $this->insertEntry([
            'info' => self::MARKER . 'SEARCH-APPLE-XYZ',
        ]);
        $this->ids['searchBanana'] = $this->insertEntry([
            'info' => self::MARKER . 'SEARCH-BANANA-XYZ',
        ]);

        // ── two filters combined as AND ──
        $this->ids['andMatch'] = $this->insertEntry([
            'type1' => 'rash', 'kassa' => 'bank', 'amount' => -30,
            'info'  => self::MARKER . 'AND-MATCH',
        ]);
        $this->ids['andWrongKassa'] = $this->insertEntry([
            'type1' => 'rash', 'kassa' => 'k1', 'amount' => -30,
            'info'  => self::MARKER . 'AND-WRONG-KASSA',
        ]);
        $this->ids['andWrongType1'] = $this->insertEntry([
            'type1' => 'doh', 'type2' => 'other', 'kassa' => 'bank', 'amount' => 30,
            'info'  => self::MARKER . 'AND-WRONG-TYPE1',
        ]);

        // ── pagination / total_rows / ordering family (5 rows, distinct calendar days) ──
        foreach ([1, 2, 3, 4, 5] as $i) {
            $day = 2 * $i - 1; // 1, 3, 5, 7, 9
            $this->ids['page' . $i] = $this->insertEntry([
                'acc_date' => Carbon::parse(sprintf('2026-04-%02d 10:00:00', $day), 'Europe/Minsk')->timestamp,
                'amount'   => -1 * $i,
                'info'     => self::MARKER . 'PAGE-' . $i,
            ]);
        }
    }

    private function insertEntry(array $overrides = []): int
    {
        $defaults = [
            'acc_date'   => Carbon::parse('2026-01-01 10:00:00', 'Europe/Minsk')->timestamp,
            'amount'     => -10.00,
            'type1'      => 'rash',
            'type2'      => 'tovar',
            'channel'    => '1',
            'kassa'      => 'k2',
            'link_to'    => 0,
            'info'       => self::MARKER . 'DEFAULT',
            'cr_time'    => Carbon::parse('2026-01-01 10:00:00', 'Europe/Minsk')->timestamp,
            'cr_who_id'  => $this->fixtureUserId,
            'dr_name_id' => 0,
        ];

        return DB::table('doh_rash')->insertGetId(array_merge($defaults, $overrides));
    }

    // ── 1. envelope + data array ────────────────────────────────────────────

    public function test_list_returns_envelope_and_data_array(): void
    {
        $r = $this->mcp('finance/entries', ['search' => self::MARKER . 'CANONICAL']);
        $this->assertEnvelope($r);
        $this->assertIsArray($r->json('data'));
        $this->assertNotEmpty($r->json('data'));
    }

    // ── 2. row shape is exactly the documented set of keys ─────────────────

    public function test_list_row_shape_has_exactly_documented_keys(): void
    {
        $r = $this->mcp('finance/entries', ['search' => self::MARKER . 'CANONICAL']);
        $row = $r->json('data.0');

        $expectedKeys = [
            'dr_id', 'date', 'amount', 'type1', 'type2', 'type2_name',
            'kassa', 'channel', 'info', 'dr_name_id', 'link_to',
            'created_at', 'created_by_id', 'created_by',
        ];

        $this->assertIsArray($row, 'expected exactly one matching row');
        $this->assertEqualsCanonicalizing($expectedKeys, array_keys($row));
    }

    // ── 3. amount always positive, direction carried by type1 ──────────────

    public function test_amount_is_returned_positive_for_rash_rows_stored_negative(): void
    {
        $r = $this->mcp('finance/entries', ['search' => self::MARKER . 'CANONICAL']);
        $row = $r->json('data.0');

        $this->assertSame('rash', $row['type1']);
        $this->assertEqualsWithDelta(123.45, (float) $row['amount'], 0.001);
    }

    // ── 4. date rendered Y-m-d from acc_date, Europe/Minsk ──────────────────

    public function test_date_is_rendered_from_acc_date_in_europe_minsk(): void
    {
        $r = $this->mcp('finance/entries', ['search' => self::MARKER . 'CANONICAL']);
        $row = $r->json('data.0');

        $expected = Carbon::createFromTimestamp(
            DB::table('doh_rash')->where('dr_id', $this->ids['canonical'])->value('acc_date'),
            'Europe/Minsk'
        )->toDateString();

        $this->assertSame('2026-05-05', $expected, 'sanity check on the fixture itself');
        $this->assertSame($expected, $row['date']);
    }

    // ── 5. from/to filter by acc_date ───────────────────────────────────────

    public function test_from_to_filters_by_acc_date_window(): void
    {
        $r = $this->mcp('finance/entries', [
            'from'   => '2026-03-10',
            'to'     => '2026-03-15',
            'search' => self::MARKER . 'WINDOW-',
        ]);
        $r->assertStatus(200);

        $infos = array_column($r->json('data'), 'info');

        $this->assertContains(self::MARKER . 'WINDOW-IN-1', $infos);
        $this->assertContains(self::MARKER . 'WINDOW-IN-2', $infos);
        $this->assertNotContains(self::MARKER . 'WINDOW-OUT-BEFORE', $infos);
        $this->assertNotContains(self::MARKER . 'WINDOW-OUT-AFTER', $infos);
    }

    // ── 6. type1 filter ──────────────────────────────────────────────────

    public function test_type1_filter_returns_only_matching_rows(): void
    {
        $r = $this->mcp('finance/entries', [
            'type1'  => 'rash',
            'search' => self::MARKER . 'TYPE1-',
        ]);
        $r->assertStatus(200);

        $infos = array_column($r->json('data'), 'info');

        $this->assertContains(self::MARKER . 'TYPE1-RASH', $infos);
        $this->assertNotContains(self::MARKER . 'TYPE1-DOH', $infos);
    }

    // ── 7. type2 filter ──────────────────────────────────────────────────

    public function test_type2_filter_returns_only_matching_rows(): void
    {
        $r = $this->mcp('finance/entries', [
            'type2'  => 'tovar',
            'search' => self::MARKER . 'TYPE2-',
        ]);
        $r->assertStatus(200);

        $infos = array_column($r->json('data'), 'info');

        $this->assertContains(self::MARKER . 'TYPE2-TOVAR', $infos);
        $this->assertNotContains(self::MARKER . 'TYPE2-OTHER', $infos);
    }

    // ── 8. kassa filter ──────────────────────────────────────────────────

    public function test_kassa_filter_returns_only_matching_rows(): void
    {
        $r = $this->mcp('finance/entries', [
            'kassa'  => 'bank',
            'search' => self::MARKER . 'KASSA-',
        ]);
        $r->assertStatus(200);

        $infos = array_column($r->json('data'), 'info');

        $this->assertContains(self::MARKER . 'KASSA-BANK', $infos);
        $this->assertNotContains(self::MARKER . 'KASSA-K1', $infos);
    }

    // ── 9. channel filter ────────────────────────────────────────────────

    public function test_channel_filter_returns_only_matching_rows(): void
    {
        $r = $this->mcp('finance/entries', [
            'channel' => 'cur',
            'search'  => self::MARKER . 'CHANNEL-',
        ]);
        $r->assertStatus(200);

        $infos = array_column($r->json('data'), 'info');

        $this->assertContains(self::MARKER . 'CHANNEL-CUR', $infos);
        $this->assertNotContains(self::MARKER . 'CHANNEL-1', $infos);
    }

    // ── 10. search filter: LIKE match on info ───────────────────────────

    public function test_search_filter_does_like_match_on_info(): void
    {
        $r = $this->mcp('finance/entries', ['search' => 'SEARCH-APPLE-XYZ']);
        $r->assertStatus(200);

        $infos = array_column($r->json('data'), 'info');

        $this->assertContains(self::MARKER . 'SEARCH-APPLE-XYZ', $infos);
        $this->assertNotContains(self::MARKER . 'SEARCH-BANANA-XYZ', $infos);
    }

    // ── 11. two filters combine as AND ──────────────────────────────────

    public function test_two_filters_combine_as_and(): void
    {
        $r = $this->mcp('finance/entries', [
            'type1'  => 'rash',
            'kassa'  => 'bank',
            'search' => self::MARKER . 'AND-',
        ]);
        $r->assertStatus(200);

        $infos = array_column($r->json('data'), 'info');

        $this->assertContains(self::MARKER . 'AND-MATCH', $infos);
        $this->assertNotContains(self::MARKER . 'AND-WRONG-KASSA', $infos);
        $this->assertNotContains(self::MARKER . 'AND-WRONG-TYPE1', $infos);
    }

    // ── 12. per_page caps row count; page=2 is a different, non-overlapping set ──

    public function test_per_page_caps_rows_and_page_2_is_non_overlapping(): void
    {
        $page1 = $this->mcp('finance/entries', [
            'search' => self::MARKER . 'PAGE-', 'per_page' => 2, 'page' => 1,
        ]);
        $page2 = $this->mcp('finance/entries', [
            'search' => self::MARKER . 'PAGE-', 'per_page' => 2, 'page' => 2,
        ]);

        $page1->assertStatus(200);
        $page2->assertStatus(200);

        $data1 = $page1->json('data');
        $data2 = $page2->json('data');

        $this->assertCount(2, $data1);
        $this->assertCount(2, $data2);

        $ids1 = array_column($data1, 'dr_id');
        $ids2 = array_column($data2, 'dr_id');

        $this->assertEmpty(array_intersect($ids1, $ids2), 'page 1 and page 2 must not overlap');
    }

    // ── 13. per_page above 500 rejected 422 ─────────────────────────────

    public function test_per_page_above_500_is_rejected(): void
    {
        $this->mcp('finance/entries', ['per_page' => 501])->assertStatus(422);
    }

    // ── 14. meta.total_rows reflects filtered total, not page size ─────

    public function test_meta_total_rows_reflects_filtered_total_not_page_size(): void
    {
        $r = $this->mcp('finance/entries', [
            'search' => self::MARKER . 'PAGE-', 'per_page' => 2, 'page' => 1,
        ]);
        $r->assertStatus(200);

        $this->assertCount(2, $r->json('data'));
        $this->assertSame(5, $r->json('meta.total_rows'));
    }

    // ── 15. ordering is acc_date DESC ───────────────────────────────────

    public function test_ordering_is_acc_date_desc(): void
    {
        $r = $this->mcp('finance/entries', [
            'search' => self::MARKER . 'PAGE-', 'per_page' => 10,
        ]);
        $r->assertStatus(200);

        $data = $r->json('data');
        $this->assertCount(5, $data);

        $timestamps = array_map(fn ($row) => strtotime($row['date']), $data);
        $this->assertSortedDesc($timestamps, 'finance/entries must be ordered by acc_date DESC');
    }

    // ── 16. GET /finance/entries/{id} returns the single matching row ──

    public function test_show_returns_single_row_with_same_shape(): void
    {
        $id = $this->ids['canonical'];
        $r = $this->mcp('finance/entries/' . $id);
        $r->assertStatus(200);

        $row = $r->json('data');

        $expectedKeys = [
            'dr_id', 'date', 'amount', 'type1', 'type2', 'type2_name',
            'kassa', 'channel', 'info', 'dr_name_id', 'link_to',
            'created_at', 'created_by_id', 'created_by',
        ];
        $this->assertEqualsCanonicalizing($expectedKeys, array_keys($row));

        $this->assertSame($id, $row['dr_id']);
        $this->assertEqualsWithDelta(123.45, (float) $row['amount'], 0.001);
        $this->assertSame('rash', $row['type1']);
        $this->assertSame('tovar', $row['type2']);
        $this->assertSame($this->tovarRashText, $row['type2_name']);
        $this->assertSame('k2', $row['kassa']);
        $this->assertSame('1', (string) $row['channel']);
        $this->assertSame(self::MARKER . 'CANONICAL', $row['info']);
        $this->assertSame(777, $row['dr_name_id']);
        $this->assertSame(555, $row['link_to']);
        $this->assertSame($this->fixtureUserId, $row['created_by_id']);
        $this->assertSame($this->fixtureUserName, $row['created_by']);
    }

    // ── 17. GET /finance/entries/{id} 404 for unknown id ────────────────

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->mcp('finance/entries/999999999')->assertStatus(404);
    }

    // ── 18. GET /finance/entries/history returns envelope + array ──────

    public function test_history_returns_envelope_and_array(): void
    {
        $r = $this->mcp('finance/entries/history');
        $this->assertEnvelope($r);
        $this->assertIsArray($r->json('data'));
    }

    // ── 19. GET /finance/entries/history?dr_id= filters by row id ──────

    public function test_history_filters_by_dr_id(): void
    {
        $targetDrId = $this->ids['canonical'];
        $otherDrId  = $this->ids['type1Rash'];

        $historyIdA = DB::table('doh_rash_history')->insertGetId([
            'dr_id'         => $targetDrId,
            'action'        => 'update',
            'before_json'   => json_encode(['amount' => -100]),
            'after_json'    => json_encode(['amount' => -123.45]),
            'actor_user_id' => $this->fixtureUserId,
            'source'        => 'mcp_api',
            'ip'            => '127.0.0.1',
            'created_at'    => now(),
        ]);
        $historyIdB = DB::table('doh_rash_history')->insertGetId([
            'dr_id'         => $otherDrId,
            'action'        => 'update',
            'before_json'   => json_encode(['amount' => -15]),
            'after_json'    => json_encode(['amount' => -16]),
            'actor_user_id' => $this->fixtureUserId,
            'source'        => 'mcp_api',
            'ip'            => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $r = $this->mcp('finance/entries/history', ['dr_id' => $targetDrId]);
        $this->assertEnvelope($r);

        $data = $r->json('data');
        $this->assertNotEmpty($data, 'expected at least the history row just inserted for this dr_id');

        $drIds = array_column($data, 'dr_id');
        $this->assertContains($targetDrId, $drIds);
        $this->assertNotContains($otherDrId, $drIds);

        // sanity: both rows exist (guards against a query so broad it'd pass by accident)
        $this->assertNotNull($historyIdA);
        $this->assertNotNull($historyIdB);
    }

    // ── 20. all three endpoints require the Bearer token ────────────────

    public function test_all_endpoints_require_token(): void
    {
        $this->assertRequiresToken('finance/entries');
        $this->assertRequiresToken('finance/entries/' . $this->ids['canonical']);
        $this->assertRequiresToken('finance/entries/history');
    }
}
