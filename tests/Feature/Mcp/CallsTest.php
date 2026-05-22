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
            'uuid' => 'rec-stale', 'record_name' => 'test/record3',
            'call_date' => '2026-05-21 11:00:00', 'caller_part' => '+375291111111',
            'callee_part' => '+375296303532', 'call_duration' => 60,
            'file_path' => 'a1_recordings/2026-05/test3.mp3', 'file_size' => 512, 'created_at' => now(),
        ]);
        // Stale processing record (updated 3 hours ago)
        DB::table('a1_call_analysis')->insert([
            'recording_uuid' => 'rec-stale',
            'ai_status'  => 'processing',
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        $response = $this->mcp('calls/pending-analysis', ['from' => '2026-05-21', 'to' => '2026-05-21']);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
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
        $response->assertStatus(200);
        $this->assertEquals('Тихий день', $response->json('data.summary_text'));
    }
}
