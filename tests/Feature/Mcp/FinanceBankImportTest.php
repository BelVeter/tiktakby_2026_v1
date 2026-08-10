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
