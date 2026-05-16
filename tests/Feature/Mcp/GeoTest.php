<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GeoTest extends McpTestCase
{
    /**
     * Inserts roll back at the end of each test thanks to DatabaseTransactions
     * (clients/rent_deals_arch are InnoDB).
     */
    use DatabaseTransactions;

    /**
     * A future window so synthetic test data doesn't intersect real records.
     * 2037 stays within int(11) UNIX timestamp range (2038-01-19 cap).
     */
    private const FROM = '2037-01-01';
    private const TO   = '2037-01-31';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_clients_by_city_envelope_and_columns(): void
    {
        $r = $this->mcp('geo/clients-by-city', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [['city_norm', 'unique_clients', 'deals', 'revenue_byn']]]);
    }

    public function test_minsk_is_top_row_for_2024(): void
    {
        $r = $this->mcp('geo/clients-by-city', ['from' => '2024-01-01', 'to' => '2024-12-31']);
        $r->assertStatus(200);
        $rows = $r->json('data');
        $this->assertNotEmpty($rows);
        $this->assertSame('минск', $rows[0]['city_norm']);
    }

    public function test_results_are_sorted_desc_by_unique_clients(): void
    {
        $rows = $this->mcp('geo/clients-by-city', ['from' => '2024-01-01', 'to' => '2024-12-31'])->json('data');
        $counts = array_map(static fn ($r) => $r['unique_clients'], $rows);
        $sorted = $counts;
        rsort($sorted);
        $this->assertSame($sorted, $counts);
    }

    public function test_period_with_no_deals_returns_empty_data(): void
    {
        $r = $this->mcp('geo/clients-by-city', ['from' => self::FROM, 'to' => self::TO]);
        $r->assertStatus(200);
        $this->assertSame([], $r->json('data'));
    }

    public function test_normalizes_city_case_and_whitespace(): void
    {
        // Three synthetic clients with different spellings of the same city in
        // a future period — endpoint must aggregate them into one bucket.
        $this->insertClientWithDeal(990001, '  Минск ', self::FROM);
        $this->insertClientWithDeal(990002, 'минск',   self::FROM);
        $this->insertClientWithDeal(990003, 'МИНСК',   self::FROM);

        $rows = $this->mcp('geo/clients-by-city', ['from' => self::FROM, 'to' => self::TO])->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('минск', $rows[0]['city_norm']);
        $this->assertSame(3, $rows[0]['unique_clients']);
        $this->assertSame(3, $rows[0]['deals']);
    }

    public function test_groups_empty_or_whitespace_city_into_unknown_bucket(): void
    {
        $this->insertClientWithDeal(990010, '',     self::FROM);
        $this->insertClientWithDeal(990011, '   ',  self::FROM);

        $rows = $this->mcp('geo/clients-by-city', ['from' => self::FROM, 'to' => self::TO])->json('data');
        $byCity = collect($rows)->keyBy('city_norm');
        $this->assertArrayHasKey('(unknown)', $byCity->toArray());
        $this->assertSame(2, $byCity['(unknown)']['unique_clients']);
    }

    public function test_period_filter_excludes_clients_with_deals_outside_window(): void
    {
        // Synthetic Vitebsk client whose deal falls one day BEFORE the window.
        $oneDayBefore = strtotime(self::FROM) - 86400;
        $this->insertClientWithDeal(990020, 'Витебск', '@' . $oneDayBefore);

        $rows = $this->mcp('geo/clients-by-city', ['from' => self::FROM, 'to' => self::TO])->json('data');
        $this->assertSame([], $rows, 'no in-window deals → endpoint must return empty data');
        $this->assertNotContains('витебск', array_column($rows, 'city_norm'));
    }

    public function test_aggregates_revenue_and_deal_count_correctly(): void
    {
        // One client, two deals → unique_clients=1, deals=2, revenue=sum.
        $this->insertClientWithDeal(990030, 'Брест', self::FROM, 100.00, 10.00);
        $this->insertDealForClient(990030, self::FROM, 50.00, 5.00);

        $rows = $this->mcp('geo/clients-by-city', ['from' => self::FROM, 'to' => self::TO])->json('data');
        $byCity = collect($rows)->keyBy('city_norm');
        $this->assertArrayHasKey('брест', $byCity->toArray());
        $this->assertSame(1, $byCity['брест']['unique_clients']);
        $this->assertSame(2, $byCity['брест']['deals']);
        $this->assertEqualsWithDelta(165.00, (float) $byCity['брест']['revenue_byn'], 0.01);
    }

    public function test_clients_without_deals_are_not_listed(): void
    {
        $this->insertClient(990040, 'Гомель', self::FROM);  // no deals

        $rows = $this->mcp('geo/clients-by-city', ['from' => self::FROM, 'to' => self::TO])->json('data');
        $cities = array_column($rows, 'city_norm');
        $this->assertNotContains('гомель', $cities);
    }

    public function test_validation_rejects_invalid_date(): void
    {
        $this->mcp('geo/clients-by-city', ['from' => 'not-a-date'])->assertStatus(422);
    }

    public function test_validation_rejects_inverted_range(): void
    {
        $this->mcp('geo/clients-by-city', ['from' => '2024-12-31', 'to' => '2024-01-01'])->assertStatus(422);
    }

    public function test_requires_bearer_token(): void
    {
        $this->getJson('/api/mcp/v1/geo/clients-by-city')->assertStatus(401);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * @param string $when ISO date `YYYY-MM-DD` or `@<unix-timestamp>` for raw seconds.
     */
    private function insertClientWithDeal(int $clientId, string $city, string $when, float $rPaid = 100.00, float $deliveryPaid = 0.00): void
    {
        $this->insertClient($clientId, $city, $when);
        $this->insertDealForClient($clientId, $when, $rPaid, $deliveryPaid);
    }

    private function insertClient(int $clientId, string $city, string $when): void
    {
        DB::table('clients')->insert([
            'client_id'    => $clientId,
            'family'       => 'TestFamily',
            'name'         => 'TestName',
            'otch'         => '',
            'city'         => $city,
            'str'          => '', 'dom' => '', 'kv' => '',
            'pas_n'        => '', 'pas_ln' => '', 'pas_date' => 0, 'pas_who' => '',
            'reg_city'     => '', 'reg_str' => '', 'reg_dom' => '', 'reg_kv' => '',
            'phone_1'      => '', 'phone_2' => '',
            'info'         => '',
            'status'       => '',
            'cr_time'      => $this->ts($when),
            'arch_n'       => 0,
            'arch_amount'  => 0,
            'arch_l_date'  => 0,
            'cr_who'       => 0,
            'source'       => 'test',
        ]);
    }

    private static int $dealSeq = 0;

    private function insertDealForClient(int $clientId, string $when, float $rPaid, float $deliveryPaid): void
    {
        $ts     = $this->ts($when);
        $dealId = 9_000_000 + (++self::$dealSeq); // unique per insert; UNIQUE constraint on deal_id
        DB::table('rent_deals_arch')->insert([
            'deal_id'             => $dealId,
            'client_id'           => $clientId,
            'item_inv_n'          => 0,
            'start_date'          => $ts,
            'return_date'         => 0,
            'delivery_yn'         => 0,
            'delivery_to_pay'     => $deliveryPaid,
            'delivery_paid'       => $deliveryPaid,
            'r_to_pay'            => $rPaid,
            'r_paid'              => $rPaid,
            'collateral_amount'   => 0,
            'collateral_cur'      => '',
            'deal_status'         => 'test',
            'deal_info'           => '',
            'acc_person_id'       => 0,
            'cr_who_id'           => '',
            'cr_time'             => $ts,
            'last_sub_deal_ch_time' => 0,
            'planned_return_date' => 0,
            'deal_set'            => '',
            'first_rent_place'    => 0,
            'arch_time'           => 0,
        ]);

        // Revenue is now sourced from UNION(rent_sub_deals_act, rent_sub_deals_arch)
        // by acc_date — we must record at least one sub-deal payment so the deal
        // appears in /geo/clients-by-city aggregations.
        DB::table('rent_sub_deals_arch')->insert([
            'arch_time'        => 0,
            'sub_deal_id'      => $dealId,           // unique enough for fixtures
            'deal_id'          => $dealId,
            'type'             => 'first_rent',
            'type_sort_n'      => 0,
            'from'             => $ts,
            'to'               => $ts,
            'tarif_id'         => 0,
            'tarif_step'       => '',
            'tarif_value'      => 0,
            'rent_tenor'       => 0,
            'r_to_pay'         => $rPaid,
            'delivery_yn'      => '0',
            'delivery_to_pay'  => $deliveryPaid,
            'courier_id'       => 0,
            'r_paid'           => $rPaid,
            'delivery_paid'    => $deliveryPaid,
            'r_payment_type'   => '',
            'del_payment_type' => '',
            'status'           => '',
            'info'             => '',
            'cr_time'          => $ts,
            'cr_who_id'        => 0,
            'ch_time'          => 0,
            'ch_who_id'        => 0,
            'link'             => 0,
            'acc_date'         => $ts,
            'place'            => 0,
            'ch_num'           => '',
            'sd_cat_id'        => 0,
            'sd_model_id'      => 0,
            'sd_inv_n'         => 0,
        ]);
    }

    private function ts(string $when): int
    {
        return $when[0] === '@' ? (int) substr($when, 1) : strtotime($when);
    }
}
