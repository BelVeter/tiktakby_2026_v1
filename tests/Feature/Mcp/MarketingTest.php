<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tests for GET /api/mcp/v1/marketing/conversions
 *
 * Uses a future period (2037) so synthetic rows don't overlap real data.
 * DatabaseTransactions rolls back all inserts after each test.
 */
class MarketingTest extends McpTestCase
{
    use DatabaseTransactions;

    private const FROM = '2037-01-01';
    private const TO   = '2037-01-31';

    /** Unix timestamps for the future test window */
    private const TS_IN  = 2114380800; // 2037-01-01 00:00:00 UTC
    private const TS_OUT = 2111702400; // 2036-12-01 00:00:00 UTC (outside window)

    private static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ─── Envelope & Auth ─────────────────────────────────────────────────────

    public function test_envelope_shape_and_token(): void
    {
        $r = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO]);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['query', 'data', 'meta']);
    }

    public function test_requires_bearer_token(): void
    {
        $this->getJson('/api/mcp/v1/marketing/conversions')->assertStatus(401);
    }

    public function test_empty_period_returns_empty_data(): void
    {
        $r = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO]);
        $r->assertStatus(200);
        $this->assertSame([], $r->json('data'));
    }

    // ─── Period filter ────────────────────────────────────────────────────────

    public function test_period_filter_excludes_rows_outside_window(): void
    {
        // One row inside the window, one outside.
        $this->insertUtm('phone_click', 0, self::TS_IN,  'google', 'cpc');
        $this->insertUtm('phone_click', 0, self::TS_OUT, 'yandex', 'cpc');

        $rows = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO])->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('google', $rows[0]['utm_source']);
    }

    public function test_sorted_desc_by_date(): void
    {
        $this->insertUtm('phone_click', 0, self::TS_IN,      'first',  'cpc');
        $this->insertUtm('phone_click', 0, self::TS_IN + 60, 'second', 'cpc');

        $rows = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO])->json('data');
        $this->assertCount(2, $rows);
        // DESC order → 'second' first
        $this->assertSame('second', $rows[0]['utm_source']);
        $this->assertSame('first',  $rows[1]['utm_source']);
    }

    // ─── UTM filters ──────────────────────────────────────────────────────────

    public function test_utm_source_filter(): void
    {
        $this->insertUtm('phone_click', 0, self::TS_IN, 'google',  'cpc');
        $this->insertUtm('phone_click', 0, self::TS_IN, 'yandex',  'cpc');

        $rows = $this->mcp('marketing/conversions', [
            'from'       => self::FROM,
            'to'         => self::TO,
            'utm_source' => 'google',
        ])->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('google', $rows[0]['utm_source']);
    }

    public function test_utm_campaign_filter(): void
    {
        $this->insertUtm('phone_click', 0, self::TS_IN, 'google', 'cpc', 'brand');
        $this->insertUtm('phone_click', 0, self::TS_IN, 'google', 'cpc', 'promo');

        $rows = $this->mcp('marketing/conversions', [
            'from'         => self::FROM,
            'to'           => self::TO,
            'utm_campaign' => 'brand',
        ])->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('brand', $rows[0]['utm_campaign']);
    }

    // ─── phone_click entity type ──────────────────────────────────────────────

    public function test_phone_click_has_null_entity_fields(): void
    {
        $this->insertUtm('phone_click', 0, self::TS_IN, 'google', 'cpc');

        $row = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO])->json('data.0');

        $this->assertSame('phone_click', $row['entity_type']);
        $this->assertNull($row['fio']);
        $this->assertNull($row['phone']);
        $this->assertNull($row['info']);
        $this->assertNull($row['status']);
        $this->assertNull($row['model_id']);
        $this->assertNull($row['model_name']);
        $this->assertNull($row['cat_id']);
        $this->assertNull($row['cat_name']);
    }

    // ─── rent_orders entity type ──────────────────────────────────────────────

    public function test_rent_orders_returns_model_and_category(): void
    {
        // Use real model and category IDs that exist in the DB.
        $modelId = DB::table('rent_model_web')->where('lang', 'ru')->value('model_id');
        $catId   = DB::table('rent_orders')->where('model_id', $modelId)->value('cat_id');

        if (!$modelId || !$catId) {
            $this->markTestSkipped('No rent_orders+rent_model_web data available in test DB');
        }

        $orderId = $this->insertOrder($modelId, $catId);
        $this->insertUtm('rent_orders', $orderId, self::TS_IN, 'google', 'cpc');

        $row = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO])->json('data.0');

        $this->assertSame('rent_orders', $row['entity_type']);
        $this->assertSame($modelId, $row['model_id']);
        $this->assertNotNull($row['model_name'], 'model_name must be populated for rent_orders');
        $this->assertSame($catId, $row['cat_id']);
        $this->assertNotNull($row['cat_name'], 'cat_name must be populated for rent_orders');
    }

    public function test_rent_orders_with_zero_model_id_has_null_model_fields(): void
    {
        // An order submitted without a model (e.g. created by manager).
        $orderId = $this->insertOrder(0, 0);
        $this->insertUtm('rent_orders', $orderId, self::TS_IN, 'google', 'cpc');

        $row = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO])->json('data.0');

        $this->assertSame('rent_orders', $row['entity_type']);
        $this->assertSame(0, $row['model_id']);
        $this->assertNull($row['model_name']);
        $this->assertSame(0, $row['cat_id']);
        $this->assertNull($row['cat_name']);
    }

    // ─── kb_zayavki entity type ───────────────────────────────────────────────

    public function test_kb_zayavki_returns_model_without_cat(): void
    {
        // Use real model_id that exists in kb_zayavki.
        $zayavka = DB::table('kb_zayavki')->where('model_id', '>', 0)->first();
        if (!$zayavka) {
            $this->markTestSkipped('No kb_zayavki data available in test DB');
        }

        $this->insertUtm('kb_zayavki', $zayavka->id, self::TS_IN, 'google', 'cpc');

        $row = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO])->json('data.0');

        $this->assertSame('kb_zayavki', $row['entity_type']);
        $this->assertSame((int) $zayavka->model_id, $row['model_id']);
        $this->assertNotNull($row['model_name'], 'model_name must be populated for kb_zayavki with valid model_id');
        // cat_id is NOT stored in kb_zayavki — must always be null
        $this->assertNull($row['cat_id'],   'cat_id must be null for kb_zayavki');
        $this->assertNull($row['cat_name'], 'cat_name must be null for kb_zayavki');
    }

    public function test_kb_zayavki_phone_is_populated(): void
    {
        $zayavka = DB::table('kb_zayavki')->where('model_id', '>', 0)->whereNotNull('phone')->first();
        if (!$zayavka) {
            $this->markTestSkipped('No kb_zayavki with phone in test DB');
        }

        $this->insertUtm('kb_zayavki', $zayavka->id, self::TS_IN, 'google', 'cpc');

        $row = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO])->json('data.0');

        $this->assertSame($zayavka->phone, $row['phone']);
        $this->assertNull($row['fio'],    'fio must be null for kb_zayavki');
        $this->assertNull($row['status'], 'status must be null for kb_zayavki');
    }

    // ─── karn_brons entity type ───────────────────────────────────────────────

    public function test_karn_brons_has_null_model_fields(): void
    {
        $kb = DB::table('karn_brons')->first();
        if (!$kb) {
            $this->markTestSkipped('No karn_brons data in test DB');
        }

        $this->insertUtm('karn_brons', $kb->kb_id, self::TS_IN, 'vk', 'social');

        $row = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO])->json('data.0');

        $this->assertSame('karn_brons', $row['entity_type']);
        // karn_brons has no model/cat — must be null
        $this->assertNull($row['model_id'],   'model_id must be null for karn_brons');
        $this->assertNull($row['model_name'], 'model_name must be null for karn_brons');
        $this->assertNull($row['cat_id'],     'cat_id must be null for karn_brons');
        $this->assertNull($row['cat_name'],   'cat_name must be null for karn_brons');
    }

    // ─── zvonki entity type ───────────────────────────────────────────────────

    public function test_zvonki_has_null_model_fields(): void
    {
        $z = DB::table('zvonki')->first();
        if (!$z) {
            $this->markTestSkipped('No zvonki data in test DB');
        }

        $this->insertUtm('zvonki', $z->zv_id, self::TS_IN, 'google', 'cpc');

        $row = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO])->json('data.0');

        $this->assertSame('zvonki', $row['entity_type']);
        $this->assertNull($row['model_id'],   'model_id must be null for zvonki');
        $this->assertNull($row['model_name'], 'model_name must be null for zvonki');
        $this->assertNull($row['cat_id'],     'cat_id must be null for zvonki');
        $this->assertNull($row['cat_name'],   'cat_name must be null for zvonki');
    }

    // ─── Response shape ───────────────────────────────────────────────────────

    public function test_row_contains_all_expected_keys(): void
    {
        $this->insertUtm('phone_click', 0, self::TS_IN, 'google', 'cpc');

        $row = $this->mcp('marketing/conversions', ['from' => self::FROM, 'to' => self::TO])->json('data.0');

        foreach (['date', 'entity_type', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term',
                  'fio', 'phone', 'info', 'status', 'model_id', 'model_name', 'cat_id', 'cat_name'] as $key) {
            $this->assertArrayHasKey($key, $row, "Response row must contain key '{$key}'");
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function insertUtm(
        string $entityType,
        int    $entityId,
        int    $ts,
        string $utmSource,
        string $utmMedium,
        string $utmCampaign = 'test_campaign',
        string $utmTerm     = ''
    ): void {
        $dt = date('Y-m-d H:i:s', $ts);
        DB::table('tiktak_utms')->insert([
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'utm_source'   => $utmSource,
            'utm_medium'   => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_term'     => $utmTerm,
            'utm_content'  => '',
            'created_at'   => $dt,
            'updated_at'   => $dt,
        ]);
    }

    private function insertOrder(int $modelId, int $catId): int
    {
        $id = 9_800_000 + (++self::$seq);
        DB::table('rent_orders')->insert([
            'order_id'     => $id,
            'type'         => 'zayavka',
            'order_date'   => self::TS_IN,
            'phone'        => '375291234567',
            'phone_yn'     => 0,
            'family'       => 'TestUser',
            'name'         => '',
            'otch'         => '',
            'fio_yn'       => 0,
            'address'      => '',
            'validity'     => self::TS_IN + 86400,
            'inv_n'        => 0,
            'model_id'     => $modelId,
            'cat_id'       => $catId,
            'type2'        => 'zayavka',
            'client_id'    => 0,
            'info'         => '',
            'info2'        => '',
            'web'          => 1,
            'cr_time'      => self::TS_IN,
            'cr_who_id'    => 0,
            'ch_time'      => 0,
            'ch_who_id'    => 0,
            'status'       => '',
            'appr_id'      => 0,
            'appr_time'    => 0,
            'cr_ip'        => '',
            'place_status' => '',
            'rem_type'     => '',
        ]);
        return $id;
    }
}
