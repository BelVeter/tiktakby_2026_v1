<?php

namespace Tests\Feature\Mcp;

use Illuminate\Support\Facades\DB;

/**
 * Tests for call demand analytics endpoints:
 *   POST /calls/recordings/{uuid}/items
 *   GET  /calls/recordings/{uuid}/items
 *   GET  /calls/demand
 *
 * Also covers the new fields on POST /calls/recordings/{uuid}/analysis:
 *   is_internal, missed_reason, missed_outcome
 */
class CallsDemandTest extends McpTestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────

    private function insertRecording(string $uuid, string $date = '2026-08-01'): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid'          => $uuid,
            'record_name'   => "test/{$uuid}",
            'call_date'     => "{$date} 10:00:00",
            'caller_part'   => '+375291111111',
            'callee_part'   => '+375296303532',
            'call_duration' => 120,
            'file_path'     => "a1_recordings/2026-08/{$uuid}.mp3",
            'file_size'     => 1024,
            'has_audio'     => 1,
            'created_at'    => now(),
        ]);
    }

    private function insertAnalysis(string $uuid, array $overrides = []): void
    {
        DB::table('a1_call_analysis')->insert(array_merge([
            'recording_uuid' => $uuid,
            'ai_status'      => 'done',
            'ai_result'      => 'info',
            'ai_summary'     => 'Test summary',
            'is_internal'    => 0,
            'missed_reason'  => null,
            'missed_outcome' => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ], $overrides));
    }

    // ── setUp ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('call_demand_items')->truncate();
        DB::table('a1_call_analysis')->truncate();
        DB::table('a1_call_recordings')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    // ── POST /calls/recordings/{uuid}/analysis (new fields) ───────────────

    public function test_submit_analysis_accepts_is_internal(): void
    {
        $uuid = 'demand-internal-01';
        $this->insertRecording($uuid);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => $uuid,
            'ai_status'      => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $r = $this->postMcp("calls/recordings/{$uuid}/analysis", [
            'ai_summary'       => 'Internal staff call',
            'ai_result'        => 'other',
            'ai_result_detail' => '',
            'is_internal'      => true,
        ]);

        $r->assertStatus(200);
        $this->assertEquals(1, DB::table('a1_call_analysis')
            ->where('recording_uuid', $uuid)->value('is_internal'));
    }

    public function test_submit_analysis_accepts_missed_reason_and_outcome(): void
    {
        $uuid = 'demand-missed-01';
        $this->insertRecording($uuid);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => $uuid,
            'ai_status'      => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $r = $this->postMcp("calls/recordings/{$uuid}/analysis", [
            'ai_summary'     => 'Client wanted a stroller, out of stock',
            'ai_result'      => 'missed',
            'missed_item'    => 'коляска Cybex',
            'missed_reason'  => 'stock',
            'missed_outcome' => 'soft',
        ]);

        $r->assertStatus(200);
        $row = DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->first();
        $this->assertEquals('stock', $row->missed_reason);
        $this->assertEquals('soft', $row->missed_outcome);
        $this->assertEquals('missed', $row->ai_result);
    }

    public function test_submit_analysis_rejects_invalid_missed_reason(): void
    {
        $uuid = 'demand-invalid-reason-01';
        $this->insertRecording($uuid);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => $uuid,
            'ai_status'      => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->postMcp("calls/recordings/{$uuid}/analysis", [
            'ai_summary'    => 'Test',
            'ai_result'     => 'missed',
            'missed_reason' => 'bogus_value',
        ])->assertStatus(200);

        // Invalid enum value should be stored as NULL
        $this->assertNull(DB::table('a1_call_analysis')
            ->where('recording_uuid', $uuid)->value('missed_reason'));
    }

    // ── POST /calls/recordings/{uuid}/items ───────────────────────────────

    public function test_submit_items_requires_token(): void
    {
        $this->postJson('/api/mcp/v1/calls/recordings/test-uuid/items', [])
            ->assertStatus(401);
    }

    public function test_submit_items_inserts_rows(): void
    {
        $uuid = 'demand-items-01';
        $this->insertRecording($uuid);
        $this->insertAnalysis($uuid);

        $items = [
            ['phrase' => 'автокресло на 9 кг', 'kind' => 'demand', 'cat_id' => null, 'cat_name' => 'Автокресла', 'match_source' => 'fuzzy',  'missed_reason' => null,    'missed_outcome' => null],
            ['phrase' => 'коляска Cybex',       'kind' => 'missed', 'cat_id' => null, 'cat_name' => 'Коляски',   'match_source' => 'alias',  'missed_reason' => 'stock', 'missed_outcome' => 'soft'],
        ];

        $r = $this->postMcp("calls/recordings/{$uuid}/items", $items);
        $r->assertStatus(200);
        $r->assertJsonPath('status', 'ok');
        $this->assertEquals(2, DB::table('call_demand_items')->where('recording_uuid', $uuid)->count());
    }

    public function test_submit_items_replaces_existing_non_manual(): void
    {
        $uuid = 'demand-items-replace-01';
        $this->insertRecording($uuid);
        $this->insertAnalysis($uuid);

        $this->postMcp("calls/recordings/{$uuid}/items", [
            ['phrase' => 'старая фраза', 'kind' => 'demand', 'cat_id' => null, 'cat_name' => null, 'match_source' => 'fuzzy', 'missed_reason' => null, 'missed_outcome' => null],
        ]);
        $this->assertEquals(1, DB::table('call_demand_items')->where('recording_uuid', $uuid)->count());

        $this->postMcp("calls/recordings/{$uuid}/items", [
            ['phrase' => 'новая фраза 1', 'kind' => 'demand', 'cat_id' => null, 'cat_name' => null, 'match_source' => 'alias',  'missed_reason' => null,         'missed_outcome' => null],
            ['phrase' => 'новая фраза 2', 'kind' => 'missed', 'cat_id' => null, 'cat_name' => null, 'match_source' => 'fuzzy',  'missed_reason' => 'assortment', 'missed_outcome' => 'hard'],
        ]);

        $this->assertEquals(2, DB::table('call_demand_items')->where('recording_uuid', $uuid)->count());
        $this->assertNull(DB::table('call_demand_items')
            ->where('recording_uuid', $uuid)->where('phrase', 'старая фраза')->first());
    }

    public function test_submit_items_preserves_manual_overrides(): void
    {
        $uuid = 'demand-items-manual-01';
        $this->insertRecording($uuid);
        $this->insertAnalysis($uuid);

        DB::table('call_demand_items')->insert([
            'recording_uuid' => $uuid,
            'call_date'      => '2026-08-01',
            'phrase'         => 'автокресло',
            'kind'           => 'demand',
            'cat_id'         => null,
            'cat_name'       => 'Автокресла',
            'match_source'   => 'manual',
            'missed_reason'  => null,
            'missed_outcome' => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Re-submit the same phrase as unmatched — manual override must survive
        $this->postMcp("calls/recordings/{$uuid}/items", [
            ['phrase' => 'автокресло', 'kind' => 'demand', 'cat_id' => null, 'cat_name' => null, 'match_source' => 'unmatched', 'missed_reason' => null, 'missed_outcome' => null],
        ]);

        $row = DB::table('call_demand_items')
            ->where('recording_uuid', $uuid)->where('phrase', 'автокресло')->first();
        $this->assertEquals('manual', $row->match_source);
        $this->assertEquals('Автокресла', $row->cat_name);
    }

    // ── GET /calls/recordings/{uuid}/items ────────────────────────────────

    public function test_get_items_requires_token(): void
    {
        $this->getJson('/api/mcp/v1/calls/recordings/test-uuid/items')
            ->assertStatus(401);
    }

    public function test_get_items_returns_items_for_recording(): void
    {
        $uuid = 'demand-get-items-01';
        $this->insertRecording($uuid);
        $this->insertAnalysis($uuid);

        DB::table('call_demand_items')->insert([
            'recording_uuid' => $uuid,
            'call_date'      => '2026-08-01',
            'phrase'         => 'велокресло',
            'kind'           => 'demand',
            'cat_id'         => null,
            'cat_name'       => 'Велокресла',
            'match_source'   => 'alias',
            'missed_reason'  => null,
            'missed_outcome' => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $r = $this->mcp("calls/recordings/{$uuid}/items");
        $r->assertStatus(200);
        $r->assertJsonStructure(['query', 'data', 'meta']);
        $this->assertCount(1, $r->json('data'));
        $this->assertEquals('велокресло', $r->json('data.0.phrase'));
    }

    // ── GET /calls/demand ─────────────────────────────────────────────────

    public function test_demand_aggregate_requires_token(): void
    {
        $this->assertRequiresToken('calls/demand');
    }

    public function test_demand_aggregate_returns_envelope(): void
    {
        $r = $this->mcp('calls/demand', ['from' => '2026-08-01', 'to' => '2026-08-31']);
        $r->assertStatus(200);
        $r->assertJsonStructure(['query', 'data', 'meta' => ['total_categories']]);
    }

    public function test_demand_aggregate_counts_mentions(): void
    {
        $uuid1 = 'demand-agg-01';
        $uuid2 = 'demand-agg-02';
        $this->insertRecording($uuid1, '2026-08-01');
        $this->insertRecording($uuid2, '2026-08-02');
        $this->insertAnalysis($uuid1);
        $this->insertAnalysis($uuid2);

        DB::table('call_demand_items')->insert([
            ['recording_uuid' => $uuid1, 'call_date' => '2026-08-01', 'phrase' => 'автокресло A', 'kind' => 'missed', 'cat_id' => null, 'cat_name' => 'Автокресла', 'match_source' => 'alias', 'missed_reason' => 'stock', 'missed_outcome' => 'hard', 'created_at' => now(), 'updated_at' => now()],
            ['recording_uuid' => $uuid2, 'call_date' => '2026-08-02', 'phrase' => 'автокресло B', 'kind' => 'missed', 'cat_id' => null, 'cat_name' => 'Автокресла', 'match_source' => 'fuzzy', 'missed_reason' => 'stock', 'missed_outcome' => 'soft', 'created_at' => now(), 'updated_at' => now()],
            ['recording_uuid' => $uuid1, 'call_date' => '2026-08-01', 'phrase' => 'коляска',      'kind' => 'demand', 'cat_id' => null, 'cat_name' => 'Коляски',    'match_source' => 'alias', 'missed_reason' => null,    'missed_outcome' => null,   'created_at' => now(), 'updated_at' => now()],
        ]);

        $r = $this->mcp('calls/demand', ['from' => '2026-08-01', 'to' => '2026-08-31']);
        $r->assertStatus(200);

        $data = $r->json('data');
        $this->assertEquals('Автокресла', $data[0]['cat_name']);
        $this->assertEquals(2, $data[0]['mentions']);
        $this->assertEquals(1, $data[0]['missed_hard']);
        $this->assertEquals(1, $data[0]['missed_soft']);
        $this->assertEquals(2, $r->json('meta.total_categories'));
    }

    public function test_demand_aggregate_excludes_internal_calls(): void
    {
        $uuid = 'demand-agg-internal-01';
        $this->insertRecording($uuid);
        $this->insertAnalysis($uuid, ['is_internal' => 1]);

        DB::table('call_demand_items')->insert([
            'recording_uuid' => $uuid,
            'call_date'      => '2026-08-01',
            'phrase'         => 'внутренний товар',
            'kind'           => 'demand',
            'cat_id'         => null,
            'cat_name'       => 'Тест',
            'match_source'   => 'alias',
            'missed_reason'  => null,
            'missed_outcome' => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $r = $this->mcp('calls/demand', ['from' => '2026-08-01', 'to' => '2026-08-31']);
        $r->assertStatus(200);
        $this->assertCount(0, $r->json('data'));
    }

    public function test_demand_aggregate_filters_by_kind(): void
    {
        $uuid = 'demand-agg-kind-01';
        $this->insertRecording($uuid);
        $this->insertAnalysis($uuid);

        DB::table('call_demand_items')->insert([
            ['recording_uuid' => $uuid, 'call_date' => '2026-08-01', 'phrase' => 'фраза missed', 'kind' => 'missed', 'cat_id' => null, 'cat_name' => 'Кат А', 'match_source' => 'alias', 'missed_reason' => 'stock', 'missed_outcome' => 'hard', 'created_at' => now(), 'updated_at' => now()],
            ['recording_uuid' => $uuid, 'call_date' => '2026-08-01', 'phrase' => 'фраза demand', 'kind' => 'demand', 'cat_id' => null, 'cat_name' => 'Кат Б', 'match_source' => 'alias', 'missed_reason' => null,    'missed_outcome' => null,   'created_at' => now(), 'updated_at' => now()],
        ]);

        $r = $this->mcp('calls/demand', ['from' => '2026-08-01', 'to' => '2026-08-31', 'kind' => 'missed']);
        $r->assertStatus(200);
        $this->assertCount(1, $r->json('data'));
        $this->assertEquals('Кат А', $r->json('data.0.cat_name'));
    }

    public function test_demand_aggregate_top_phrases_capped_at_5(): void
    {
        $uuid = 'demand-agg-phrases-01';
        $this->insertRecording($uuid);
        $this->insertAnalysis($uuid);

        $rows = [];
        foreach (['фраза 1', 'фраза 2', 'фраза 3', 'фраза 4', 'фраза 5', 'фраза 6'] as $p) {
            $rows[] = ['recording_uuid' => $uuid, 'call_date' => '2026-08-01', 'phrase' => $p, 'kind' => 'demand', 'cat_id' => null, 'cat_name' => 'Одна категория', 'match_source' => 'alias', 'missed_reason' => null, 'missed_outcome' => null, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('call_demand_items')->insert($rows);

        $r = $this->mcp('calls/demand', ['from' => '2026-08-01', 'to' => '2026-08-31']);
        $r->assertStatus(200);
        $this->assertCount(5, $r->json('data.0.top_phrases'));
    }

    public function test_recordings_list_includes_new_fields(): void
    {
        $uuid = 'demand-list-fields-01';
        $this->insertRecording($uuid);
        $this->insertAnalysis($uuid, [
            'is_internal'    => 1,
            'missed_reason'  => 'stock',
            'missed_outcome' => 'hard',
        ]);

        $r = $this->mcp('calls/recordings', ['from' => '2026-08-01', 'to' => '2026-08-31']);
        $r->assertStatus(200);
        $item = $r->json('data.0');
        $this->assertTrue($item['is_internal']);
        $this->assertEquals('stock', $item['missed_reason']);
        $this->assertEquals('hard', $item['missed_outcome']);
    }
}
