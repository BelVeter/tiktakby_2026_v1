<?php

namespace Tests\Feature\Mcp;

use Illuminate\Support\Facades\DB;

class CallsTest extends McpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('a1_cdr')->truncate();
        DB::table('a1_call_recordings')->truncate();
        DB::table('a1_call_analysis')->truncate();
        DB::table('a1_daily_summaries')->truncate();
    }

    // ── GET /calls/cdr ────────────────────────────────────────────

    public function test_calls_cdr_requires_token(): void
    {
        $this->assertRequiresToken('calls/cdr');
    }

    public function test_calls_cdr_returns_envelope(): void
    {
        DB::table('a1_cdr')->insert([
            'uuid'          => 'test-cdr-1',
            'call_date'     => '2026-05-21 10:00:00',
            'call_type'     => 'incoming',
            'caller_number' => '+375291111111',
            'callee_number' => '+375296303532',
            'call_duration' => 120,
            'recording_uuid' => null,
            'created_at'    => now(),
        ]);

        $response = $this->mcp('calls/cdr', ['from' => '2026-05-21', 'to' => '2026-05-21']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['query', 'data', 'meta']);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('incoming', $response->json('data.0.call_type'));
    }

    public function test_calls_cdr_filters_by_type(): void
    {
        DB::table('a1_cdr')->insert([
            ['uuid' => 'cdr-in',  'call_date' => '2026-05-21 09:00:00', 'call_type' => 'incoming',
             'caller_number' => '+375291111111', 'callee_number' => '+375296303532', 'call_duration' => 60, 'created_at' => now()],
            ['uuid' => 'cdr-out', 'call_date' => '2026-05-21 09:30:00', 'call_type' => 'outgoing',
             'caller_number' => '+375296303532', 'callee_number' => '+375291111111', 'call_duration' => 90, 'created_at' => now()],
            ['uuid' => 'cdr-mis', 'call_date' => '2026-05-21 10:00:00', 'call_type' => 'missed',
             'caller_number' => '+375441111111', 'callee_number' => '+375296303532', 'call_duration' => 0,  'created_at' => now()],
        ]);

        $r = $this->mcp('calls/cdr', ['from' => '2026-05-21', 'to' => '2026-05-21', 'call_type' => 'missed']);
        $r->assertStatus(200);
        $this->assertCount(1, $r->json('data'));
        $this->assertEquals('missed', $r->json('data.0.call_type'));
    }

    // ── GET /calls/pending-analysis ───────────────────────────────

    public function test_pending_analysis_requires_token(): void
    {
        $this->assertRequiresToken('calls/pending-analysis');
    }

    public function test_pending_analysis_returns_pending_recordings(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid'          => 'rec-pending',
            'record_name'   => 'test/record',
            'call_date'     => '2026-05-21 10:00:00',
            'caller_part'   => '+375291111111',
            'callee_part'   => '+375296303532',
            'call_duration' => 120,
            'file_path'     => 'a1_recordings/2026-05/test.mp3',
            'file_size'     => 1024,
            'created_at'    => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-pending',
            'ai_status'      => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $response = $this->mcp('calls/pending-analysis', ['from' => '2026-05-21', 'to' => '2026-05-21']);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertArrayHasKey('file_url', $response->json('data.0'));
    }

    public function test_pending_analysis_sets_status_to_processing(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-set-processing', 'record_name' => 'test/record2',
            'call_date' => '2026-05-21 11:00:00', 'caller_part' => '+375291111111',
            'callee_part' => '+375296303532', 'call_duration' => 60,
            'file_path' => 'a1_recordings/2026-05/test2.mp3', 'file_size' => 512, 'created_at' => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-set-processing',
            'ai_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->mcp('calls/pending-analysis', ['from' => '2026-05-21', 'to' => '2026-05-21']);

        $status = DB::table('a1_call_analysis')
            ->where('recording_uuid', 'rec-set-processing')
            ->value('ai_status');
        $this->assertEquals('processing', $status);
    }

    public function test_pending_analysis_resets_stale_processing(): void
    {
        DB::table('a1_call_recordings')->insert([
            ['uuid' => 'rec-stale1', 'record_name' => 'test/1', 'call_date' => '2026-05-21 11:00:00', 'file_size' => 1, 'file_path' => 't', 'created_at' => now()],
            ['uuid' => 'rec-stale2', 'record_name' => 'test/2', 'call_date' => '2026-05-21 11:10:00', 'file_size' => 1, 'file_path' => 't', 'created_at' => now()],
        ]);
        // Stale processing record (Phase 1, no transcript)
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-stale1',
            'ai_status'  => 'processing',
            'transcript' => null,
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);
        // Stale processing record (Phase 2, has transcript)
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-stale2',
            'ai_status'  => 'processing',
            'transcript' => 'Some text',
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        $response = $this->mcp('calls/pending-analysis', ['from' => '2026-05-21', 'to' => '2026-05-21']);
        $response->assertStatus(200);

        // rec-stale1 should be reset to pending and immediately picked up, so it becomes processing
        $s1 = DB::table('a1_call_analysis')->where('recording_uuid', 'rec-stale1')->value('ai_status');
        $this->assertEquals('processing', $s1);

        // rec-stale2 should be reset to transcribed, so it won't be picked up by pending request
        $s2 = DB::table('a1_call_analysis')->where('recording_uuid', 'rec-stale2')->value('ai_status');
        $this->assertEquals('transcribed', $s2);
    }

    public function test_pending_analysis_fetches_transcribed_records(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-transcribed', 'record_name' => 'test/trans', 'call_date' => '2026-05-21 12:00:00',
            'file_path' => 'a1_recordings/test.mp3', 'file_size' => 10, 'created_at' => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-transcribed',
            'ai_status'  => 'transcribed',
            'transcript' => 'Phase 1 done',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->mcp('calls/pending-analysis', ['from' => '2026-05-21', 'to' => '2026-05-21', 'status' => 'transcribed']);
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Phase 1 done', $data[0]['transcript']);

        // Assert it was locked
        $status = DB::table('a1_call_analysis')->where('recording_uuid', 'rec-transcribed')->value('ai_status');
        $this->assertEquals('processing', $status);
    }

    // ── POST /calls/recordings/{uuid}/analysis ────────────────────

    public function test_submit_analysis_creates_record(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-submit', 'record_name' => 'test/submit',
            'call_date' => '2026-05-21 12:00:00', 'caller_part' => '+375291111111',
            'callee_part' => '+375296303532', 'call_duration' => 200,
            'file_path' => 'a1_recordings/2026-05/submit.mp3', 'file_size' => 2048, 'created_at' => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-submit', 'ai_status' => 'processing',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->postMcp('calls/recordings/rec-submit/analysis', [
            'transcript'      => 'Клиент: Есть ли коляска? Менеджер: Да, есть.',
            'ai_summary'      => 'Клиент интересовался наличием коляски Chicco',
            'ai_result'       => 'info',
            'ai_result_detail'=> 'Уточнил наличие, не забронировал',
        ]);

        $response->assertStatus(200);

        $analysis = DB::table('a1_call_analysis')->where('recording_uuid', 'rec-submit')->first();
        $this->assertEquals('done', $analysis->ai_status);
        $this->assertEquals('info', $analysis->ai_result);
        $this->assertNotNull($analysis->ai_processed_at);
    }

    public function test_submit_analysis_two_phase(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-twophase', 'record_name' => 'test/twophase',
            'call_date' => '2026-05-21 12:00:00', 'file_path' => 't.mp3', 'file_size' => 10, 'created_at' => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-twophase', 'ai_status' => 'processing',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Phase 1: Only transcript
        $res1 = $this->postMcp('calls/recordings/rec-twophase/analysis', [
            'transcript' => 'Only transcript here.',
        ]);
        $res1->assertStatus(200);
        $analysis1 = DB::table('a1_call_analysis')->where('recording_uuid', 'rec-twophase')->first();
        $this->assertEquals('transcribed', $analysis1->ai_status);
        $this->assertEquals('Only transcript here.', $analysis1->transcript);
        $this->assertNull($analysis1->ai_summary);

        // Agent fetches transcribed (simulating second phase start)
        DB::table('a1_call_analysis')->where('recording_uuid', 'rec-twophase')->update(['ai_status' => 'processing']);

        // Phase 2: Summary provided
        $res2 = $this->postMcp('calls/recordings/rec-twophase/analysis', [
            'ai_summary' => 'Now we have summary.',
            'ai_result'  => 'info',
        ]);
        $res2->assertStatus(200);
        $analysis2 = DB::table('a1_call_analysis')->where('recording_uuid', 'rec-twophase')->first();
        $this->assertEquals('done', $analysis2->ai_status);
        $this->assertEquals('Now we have summary.', $analysis2->ai_summary);
    }

    public function test_submit_analysis_error_status(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-error', 'record_name' => 'test/error',
            'call_date' => '2026-05-21 13:00:00', 'caller_part' => '+375291111111',
            'callee_part' => '+375296303532', 'call_duration' => 300,
            'file_path' => 'a1_recordings/2026-05/error.mp3', 'file_size' => 3072, 'created_at' => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-error', 'ai_status' => 'processing',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->postMcp('calls/recordings/rec-error/analysis', [
            'error' => 'Audio file corrupt',
        ]);

        $response->assertStatus(200);
        $status = DB::table('a1_call_analysis')->where('recording_uuid', 'rec-error')->value('ai_status');
        $this->assertEquals('error', $status);
    }

    // ── GET /calls/recordings/{uuid}/analysis ─────────────────────

    public function test_get_analysis_returns_done_record(): void
    {
        DB::table('a1_call_recordings')->insert([
            'uuid' => 'rec-done', 'record_name' => 'test/done',
            'call_date' => '2026-05-21 14:00:00', 'caller_part' => '+375291111111',
            'callee_part' => '+375296303532', 'call_duration' => 150,
            'file_path' => 'a1_recordings/2026-05/done.mp3', 'file_size' => 1500, 'created_at' => now(),
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-done',
            'transcript'     => 'Полный текст разговора',
            'ai_summary'     => 'Клиент бронировал велосипед',
            'ai_result'      => 'booking',
            'ai_status'      => 'done',
            'ai_processed_at'=> now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $response = $this->mcp('calls/recordings/rec-done/analysis');
        $response->assertStatus(200);
        $this->assertEquals('booking', $response->json('data.ai_result'));
        $this->assertEquals('done', $response->json('data.ai_status'));
    }

    public function test_get_analysis_returns_404_for_unknown(): void
    {
        $response = $this->mcp('calls/recordings/nonexistent/analysis');
        $response->assertStatus(404);
    }

    // ── POST /calls/recordings/{uuid}/reset-analysis ─────────────

    public function test_submit_analysis_saves_new_fields(): void
    {
        // Insert a recording and pending analysis
        DB::table('a1_call_recordings')->insert([
            'record_name' => 'test/rec2.mp3', 'uuid' => 'uuid-new-fields',
            'call_date' => '2026-05-22 10:00:00', 'caller_part' => '+375291234567',
            'callee_part' => '+375172345678', 'call_duration' => 180,
            'file_path' => 'a1_recordings/2026-05/rec2.mp3', 'file_size' => 1024,
            'downloaded_at' => '2026-05-22 10:01:00',
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'uuid-new-fields', 'ai_status' => 'processing',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->postMcp('calls/recordings/uuid-new-fields/analysis', [
            'transcript'           => 'Test transcript',
            'ai_summary'           => 'Test summary',
            'ai_result'            => 'booking',
            'ai_result_detail'     => 'Booked stroller',
            'discussed_items'      => ['Chicco коляска', 'Joie самокат'],
            'missed_item'          => 'Inglesina коляска',
            'client_sentiment'     => 'positive',
            'consultant_sentiment' => 'neutral',
        ]);

        $response->assertStatus(200);

        $row = DB::table('a1_call_analysis')->where('recording_uuid', 'uuid-new-fields')->first();
        $this->assertEquals('done', $row->ai_status);
        $this->assertEquals('Inglesina коляска', $row->missed_item);
        $this->assertEquals('positive', $row->client_sentiment);
        $this->assertEquals('neutral', $row->consultant_sentiment);
        $items = json_decode($row->discussed_items, true);
        $this->assertContains('Chicco коляска', $items);
    }

    public function test_reset_analysis_sets_status_to_pending(): void
    {
        DB::table('a1_call_recordings')->insert([
            'record_name' => 'test/rec3.mp3', 'uuid' => 'uuid-reset',
            'call_date' => '2026-05-22 10:00:00', 'caller_part' => '+375291234567',
            'callee_part' => '+375172345678', 'call_duration' => 120,
            'file_path' => 'a1_recordings/2026-05/rec3.mp3', 'file_size' => 512,
            'downloaded_at' => '2026-05-22 10:01:00',
        ]);
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'uuid-reset', 'ai_status' => 'done',
            'ai_summary' => 'Old summary', 'transcript' => 'Old transcript',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->postMcp('calls/recordings/uuid-reset/reset-analysis');

        $response->assertStatus(200);
        $row = DB::table('a1_call_analysis')->where('recording_uuid', 'uuid-reset')->first();
        $this->assertEquals('pending', $row->ai_status);
    }

    public function test_reset_analysis_returns_404_if_not_found(): void
    {
        $response = $this->postMcp('calls/recordings/nonexistent-uuid/reset-analysis');
        $response->assertStatus(404);
    }

    // ── GET/POST /calls/daily-summary/{date} ──────────────────────

    public function test_daily_summary_get_returns_404_when_missing(): void
    {
        $response = $this->mcp('calls/daily-summary/2026-05-21');
        $response->assertStatus(404);
    }

    public function test_daily_summary_post_creates_summary(): void
    {
        DB::table('a1_cdr')->insert([
            ['uuid' => 's1', 'call_date' => '2026-05-21 09:00:00', 'call_type' => 'incoming',
             'caller_number' => '+375291111111', 'callee_number' => '+375296303532', 'call_duration' => 60, 'created_at' => now()],
            ['uuid' => 's2', 'call_date' => '2026-05-21 10:00:00', 'call_type' => 'missed',
             'caller_number' => '+375441111111', 'callee_number' => '+375296303532', 'call_duration' => 0,  'created_at' => now()],
        ]);

        $response = $this->postMcp('calls/daily-summary/2026-05-21', [
            'summary_text' => 'День прошёл спокойно. Основные запросы — велосипеды.',
            'key_themes'   => ['велосипеды', 'наличие'],
        ]);

        $response->assertStatus(200);
        $row = DB::table('a1_daily_summaries')->where('summary_date', '2026-05-21')->first();
        $this->assertNotNull($row);
        $this->assertEquals(2, $row->total_calls);
        $this->assertEquals(1, $row->incoming_calls);
        $this->assertEquals(1, $row->missed_calls);
    }

    public function test_daily_summary_get_returns_existing(): void
    {
        DB::table('a1_daily_summaries')->insert([
            'summary_date'   => '2026-05-20',
            'summary_text'   => 'Тихий день',
            'total_calls'    => 10,
            'incoming_calls' => 7,
            'outgoing_calls' => 2,
            'missed_calls'   => 1,
            'calls_analyzed' => 5,
            'key_themes'     => json_encode(['прокат', 'возврат']),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $response = $this->mcp('calls/daily-summary/2026-05-20');
        if ($response->status() !== 200) {
            $response->dd();
        }
        $response->assertStatus(200);
        $this->assertEquals('Тихий день', $response->json('data.summary_text'));
    }
}
