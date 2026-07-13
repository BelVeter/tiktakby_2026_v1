<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /api/mcp/v1/calls/recordings         — list recordings
 * GET /api/mcp/v1/calls/recordings/{uuid}/file — download binary
 */
class CallsController extends BaseController
{
    /**
     * GET /api/mcp/v1/calls/recordings
     *
     * Query params:
     *   from     YYYY-MM-DD  (default: 30 days ago)
     *   to       YYYY-MM-DD  (default: today)
     *   caller   string      (LIKE %value%)
     *   callee   string      (LIKE %value%)
     *   page     int         (default: 1)
     *   per_page int         (default: 50, max: 200)
     */
    public function index(Request $request): JsonResponse
    {
        $from    = $request->get('from', date('Y-m-d', strtotime('-30 days')));
        $to      = $request->get('to',   date('Y-m-d'));
        $caller  = $request->get('caller');
        $callee  = $request->get('callee');
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = min(500, max(1, (int) $request->get('per_page', 100)));

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';

        $query = DB::table('a1_call_recordings')
            ->leftJoin('a1_call_analysis', 'a1_call_recordings.uuid', '=', 'a1_call_analysis.recording_uuid')
            ->whereBetween('a1_call_recordings.call_date', [$fromDt, $toDt]);

        if ($caller) {
            $query->where('a1_call_recordings.caller_part', 'like', '%' . $caller . '%');
        }
        if ($callee) {
            $query->where('a1_call_recordings.callee_part', 'like', '%' . $callee . '%');
        }

        $total = $query->count();

        $rows = (clone $query)
            ->orderBy('a1_call_recordings.call_date', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'a1_call_recordings.uuid',
                'a1_call_recordings.record_name',
                'a1_call_recordings.call_date',
                'a1_call_recordings.caller_part',
                'a1_call_recordings.callee_part',
                'a1_call_recordings.call_duration',
                'a1_call_recordings.file_size',
                'a1_call_recordings.has_audio',
                'a1_call_recordings.downloaded_at',
                'a1_call_analysis.ai_status',
                'a1_call_analysis.ai_business_note',
                'a1_call_analysis.ai_summary',
                'a1_call_analysis.client_sentiment',
                'a1_call_analysis.consultant_sentiment',
                'a1_call_analysis.ai_result',
                'a1_call_analysis.ai_result_detail'
            ]);

        $totalSizeBytes = (int) Cache::remember('a1.recordings.total_size', 300, function () {
            return DB::table('a1_call_recordings')->sum('file_size');
        });

        $lastFetch = DB::table('a1_recordings_fetch_log')
            ->where('status', 'success')
            ->orderBy('fetched_at', 'desc')
            ->value('fetched_at');

        $baseUrl = config('app.url') . '/api/mcp/v1/calls/recordings/';

        $data = $rows->map(function ($row) use ($baseUrl) {
            return [
                'uuid'             => $row->uuid,
                'record_name'      => $row->record_name,
                'call_date'        => $row->call_date,
                'caller_number'    => $row->caller_part,
                'caller_part'      => $row->caller_part,
                'callee_part'      => $row->callee_part,
                'call_duration'    => (int) $row->call_duration,
                'file_size'        => (int) $row->file_size,
                // null when audio has been deleted or record is a historical import
                'file_url'         => $row->has_audio ? $baseUrl . $row->uuid . '/file' : null,
                'has_audio'        => (bool) $row->has_audio,
                'downloaded_at'    => $row->downloaded_at,
                'ai_status'        => $row->ai_status ?? 'pending',
                'ai_business_note' => $row->ai_business_note,
                'ai_summary'           => $row->ai_summary,
                'client_sentiment'     => $row->client_sentiment,
                'consultant_sentiment' => $row->consultant_sentiment,
                'ai_result'            => $row->ai_result,
                'ai_result_detail'     => $row->ai_result_detail,
            ];
        })->values()->all();

        return $this->envelope(
            [
                'from'   => $from,
                'to'     => $to,
                'caller' => $caller,
                'callee' => $callee,
            ],
            $data,
            [
                'total_rows'       => $total,
                'page'             => $page,
                'per_page'         => $perPage,
                'total_size_bytes' => $totalSizeBytes,
                'quota_bytes'      => 1_073_741_824,
                'data_freshness'   => $lastFetch,
            ]
        );
    }

    /**
     * POST /api/mcp/v1/calls/recordings/import-completed
     *
     * Imports historical call records with their completed AI analysis in one go.
     * Bypasses the pending-analysis queue — records are immediately written as ai_status='done'
     * and has_audio=0 (no audio file stored on server).
     * Skipped records are those already existing (matched by record_name) or malformed.
     *
     * Accepts:
     *   - a JSON array directly: [{...}, {...}]        (up to 1000 items per request)
     *   - an object with a 'records' key: {"records": [{...}, {...}]}
     *   - a single object: {...}
     *
     * Required fields per item: record_name, call_date (YYYY-MM-DD HH:MM:SS).
     * Optional analysis fields: transcript, ai_summary, ai_result, ai_result_detail,
     *   discussed_items (array), missed_item, client_sentiment, consultant_sentiment,
     *   ai_business_note, caller_part (or caller_number), callee_part,
     *   call_duration (int, seconds), file_size (int, bytes).
     */
    public function importCompleted(Request $request): JsonResponse
    {
        $body = $request->all();

        // Normalise to a sequential array of items
        if (isset($body['records']) && is_array($body['records'])) {
            // Wrapped format: {"records": [...]}
            $payload = array_values($body['records']);
        } elseif (isset($body[0]) || empty($body)) {
            // Direct array format: [{...}, {...}] — Laravel gives numeric keys
            $payload = array_values($body);
        } else {
            // Single object format: {...}
            $payload = [$body];
        }

        if (empty($payload)) {
            return $this->envelope([], ['imported' => 0, 'skipped' => 0], []);
        }

        // Guard against oversized batches
        if (count($payload) > 1000) {
            return response()->json(['error' => 'Payload too large (max 1000 records per request)'], 422);
        }

        // Collect all valid record_names for bulk existence check
        $recordNames = [];
        foreach ($payload as $item) {
            if (!empty($item['record_name'])) {
                $recordNames[] = $item['record_name'];
            }
        }

        if (empty($recordNames)) {
            return response()->json(['error' => 'No valid record_name fields found in payload'], 422);
        }

        $existing = DB::table('a1_call_recordings')
            ->whereIn('record_name', array_unique($recordNames))
            ->pluck('record_name')
            ->toArray();

        $existingMap = array_flip($existing);

        $now = date('Y-m-d H:i:s');
        $recordingsToInsert = [];
        $analysisToInsert = [];
        $imported = 0;
        $skipped = 0;

        foreach ($payload as $item) {
            // Skip malformed items — count as skipped for transparency
            if (empty($item['record_name']) || empty($item['call_date'])) {
                $skipped++;
                continue;
            }

            // Validate call_date format: YYYY-MM-DD HH:MM:SS
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $item['call_date'])) {
                $skipped++;
                continue;
            }

            // Skip already existing records
            if (isset($existingMap[$item['record_name']])) {
                $skipped++;
                continue;
            }

            // Sanitize record_name for file_path: remove ".." segments, keep slashes
            $safePath = preg_replace('/\.\./u', '', $item['record_name']);
            $safePath = trim($safePath, '/');

            $uuid = (string) Str::uuid();
            $recordingsToInsert[] = [
                'record_name'   => $item['record_name'],
                'uuid'          => $uuid,
                'call_date'     => $item['call_date'],
                'caller_part'   => $item['caller_part'] ?? $item['caller_number'] ?? '',
                'callee_part'   => $item['callee_part'] ?? '',
                'call_duration' => (int) ($item['call_duration'] ?? 0),
                'file_path'     => 'historical_import/' . $safePath . '.mp3',
                'file_size'     => (int) ($item['file_size'] ?? 0),
                'has_audio'     => 0, // no audio file on server; analysis-only record
                'downloaded_at' => null,
                'created_at'    => $now,
            ];

            $analysisToInsert[] = [
                'recording_uuid'       => $uuid,
                'ai_status'            => 'done',
                'transcript'           => $item['transcript'] ?? null,
                'ai_summary'           => $item['ai_summary'] ?? null,
                'ai_result'            => $item['ai_result'] ?? null,
                'ai_result_detail'     => $item['ai_result_detail'] ?? null,
                'discussed_items'      => isset($item['discussed_items']) ? json_encode($item['discussed_items']) : '[]',
                'missed_item'          => $item['missed_item'] ?? null,
                'client_sentiment'     => $item['client_sentiment'] ?? null,
                'consultant_sentiment' => $item['consultant_sentiment'] ?? null,
                'ai_business_note'     => $item['ai_business_note'] ?? null,
                'ai_processed_at'      => $now,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];

            // Prevent duplicates within the same payload batch
            $existingMap[$item['record_name']] = true;
            $imported++;
        }

        if (!empty($recordingsToInsert)) {
            DB::transaction(function () use ($recordingsToInsert, $analysisToInsert) {
                foreach (array_chunk($recordingsToInsert, 200) as $chunk) {
                    DB::table('a1_call_recordings')->insert($chunk);
                }
                foreach (array_chunk($analysisToInsert, 200) as $chunk) {
                    DB::table('a1_call_analysis')->insert($chunk);
                }
            });
        }

        return $this->envelope(
            [],
            ['imported' => $imported, 'skipped' => $skipped],
            []
        );
    }

    /**
     * GET /api/mcp/v1/calls/recordings/{uuid}/file
     *
     * Streams the mp3 binary via BinaryFileResponse (chunked, Range-aware).
     * Returns 404 if the DB record is missing or the file is gone from disk.
     */
    public function streamFile(string $uuid): BinaryFileResponse
    {
        $recording = DB::table('a1_call_recordings')
            ->where('uuid', $uuid)
            ->first();

        if (!$recording) {
            abort(404, 'Recording not found');
        }

        $diskPath = storage_path('app/' . $recording->file_path);

        // Path traversal guard: resolved path must stay inside a1_recordings/
        $allowed  = storage_path('app/a1_recordings') . DIRECTORY_SEPARATOR;
        $resolved = realpath($diskPath);
        if ($resolved === false || strpos($resolved, $allowed) !== 0) {
            abort(404, 'Recording not found');
        }

        if (!file_exists($diskPath)) {
            abort(404, 'Recording file not found on disk');
        }

        $parts    = explode('/', $recording->file_path);
        $filename = end($parts);

        return response()->file($diskPath, ['Content-Type' => 'audio/mpeg']);
    }

    /**
     * GET /api/mcp/v1/calls/cdr
     */
    public function cdr(Request $request): JsonResponse
    {
        $from     = $request->get('from', date('Y-m-d', strtotime('-30 days')));
        $to       = $request->get('to',   date('Y-m-d'));
        $callType = $request->get('call_type', 'all');
        $page     = max(1, (int) $request->get('page', 1));
        $perPage  = min(200, max(1, (int) $request->get('per_page', 100)));

        $query = DB::table('a1_cdr')
            ->leftJoin('a1_call_analysis', 'a1_cdr.recording_uuid', '=', 'a1_call_analysis.recording_uuid')
            ->whereBetween('a1_cdr.call_date', [$from . ' 00:00:00', $to . ' 23:59:59']);

        if ($callType !== 'all' && in_array($callType, ['incoming', 'outgoing', 'missed'], true)) {
            $query->where('a1_cdr.call_type', $callType);
        }

        $total = $query->count();
        $rows  = (clone $query)
            ->orderBy('a1_cdr.call_date', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'a1_cdr.uuid', 
                'a1_cdr.call_date', 
                'a1_cdr.call_type', 
                'a1_cdr.caller_number', 
                'a1_cdr.callee_number', 
                'a1_cdr.call_duration', 
                'a1_cdr.recording_uuid',
                'a1_call_analysis.ai_business_note'
            ]);

        return $this->envelope(
            ['from' => $from, 'to' => $to, 'call_type' => $callType],
            $rows->values()->all(),
            ['total_rows' => $total, 'page' => $page, 'per_page' => $perPage]
        );
    }

    /**
     * GET /api/mcp/v1/calls/pending-analysis
     *
     * Query params:
     *   status  pending|transcribed  (default: pending)
     *   from    YYYY-MM-DD           (default: yesterday)
     *   to      YYYY-MM-DD           (default: today)
     *   limit   int                  (default: 20, max: 50)
     *
     * Resets stale 'processing' records (>2h) contextually (using transcript field).
     * Returns matching recordings and sets them to 'processing' to prevent race conditions.
     */
    public function pendingAnalysis(Request $request): JsonResponse
    {
        $from   = $request->get('from', date('Y-m-d', strtotime('-1 day')));
        $to     = $request->get('to',   date('Y-m-d'));
        $limit  = min(50, max(1, (int) $request->get('limit', 20)));
        $status = in_array($request->get('status'), ['pending', 'transcribed'], true)
                  ? $request->get('status')
                  : 'pending';

        // Context-aware timeout resets (>2h)
        // 1. If transcript IS NULL, they timed out in Phase 1 -> reset to pending
        DB::table('a1_call_analysis')
            ->where('ai_status', 'processing')
            ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-2 hours')))
            ->whereNull('transcript')
            ->update(['ai_status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')]);

        // 2. If transcript IS NOT NULL, they timed out in Phase 2 -> reset to transcribed
        DB::table('a1_call_analysis')
            ->where('ai_status', 'processing')
            ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-2 hours')))
            ->whereNotNull('transcript')
            ->update(['ai_status' => 'transcribed', 'updated_at' => date('Y-m-d H:i:s')]);

        $selectFields = ['r.uuid', 'r.call_date', 'r.caller_part', 'r.callee_part', 'r.call_duration', 'r.file_size', 'r.has_audio'];
        if ($status === 'transcribed') {
            $selectFields[] = 'ca.transcript';
        }

        // Find recordings in requested status — only those that still have audio on disk
        $rows = DB::table('a1_call_analysis as ca')
            ->join('a1_call_recordings as r', 'r.uuid', '=', 'ca.recording_uuid')
            ->where('ca.ai_status', $status)
            ->where('r.has_audio', 1)
            ->whereBetween('r.call_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('r.call_date', 'asc')
            ->limit($limit)
            ->get($selectFields);

        if ($rows->isEmpty()) {
            return $this->envelope(['from' => $from, 'to' => $to, 'status' => $status], [], ['total_rows' => 0]);
        }

        $uuids = $rows->pluck('uuid')->all();

        // Lock them by setting to 'processing'
        DB::table('a1_call_analysis')
            ->whereIn('recording_uuid', $uuids)
            ->update(['ai_status' => 'processing', 'updated_at' => date('Y-m-d H:i:s')]);

        $baseUrl = config('app.url') . '/api/mcp/v1/calls/recordings/';
        $data = $rows->map(function ($r) use ($baseUrl) {
            $arr = (array) $r;
            // has_audio=1 guaranteed by the WHERE clause above, but be explicit
            $arr['file_url'] = $baseUrl . $r->uuid . '/file';
            unset($arr['has_audio']); // internal field, not useful to the agent here
            return $arr;
        })->values()->all();

        return $this->envelope(
            ['from' => $from, 'to' => $to, 'status' => $status],
            $data,
            ['total_rows' => count($data)]
        );
    }

    /**
     * POST /api/mcp/v1/calls/recordings/{uuid}/analysis
     * Body: {transcript, ai_summary, ai_result, ai_result_detail} | {error}
     */
    public function submitAnalysis(Request $request, string $uuid): JsonResponse
    {
        $analysis = DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->first();

        if (!$analysis) {
            $recording = DB::table('a1_call_recordings')->where('uuid', $uuid)->first();
            if (!$recording) {
                return response()->json(['error' => 'Recording not found'], 404);
            }
            DB::table('a1_call_analysis')->insert([
                'recording_uuid' => $uuid,
                'ai_status'      => 'processing',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
        }

        if ($request->has('error')) {
            DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->update([
                'ai_status'  => 'error',
                'ai_error'   => $request->input('error'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } elseif ($request->has('ai_summary')) {
            // Phase 2: full analysis → done
            $updateData = [
                'transcript'           => $request->has('transcript') ? $request->input('transcript') : ($analysis->transcript ?? ''),
                'ai_summary'           => $request->input('ai_summary'),
                'ai_result'            => $request->input('ai_result'),
                'ai_result_detail'     => $request->input('ai_result_detail'),
                'discussed_items'      => json_encode($request->input('discussed_items', [])),
                'missed_item'          => $request->input('missed_item'),
                'client_sentiment'     => $request->input('client_sentiment'),
                'consultant_sentiment' => $request->input('consultant_sentiment'),
                'ai_status'            => 'done',
                'ai_processed_at'      => date('Y-m-d H:i:s'),
                'updated_at'           => date('Y-m-d H:i:s'),
            ];

            if ($request->has('ai_business_note')) {
                $updateData['ai_business_note'] = $request->input('ai_business_note');
            }

            DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->update($updateData);
        } else {
            // Phase 1: transcript only → transcribed (ready for Phase 2)
            DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->update([
                'transcript' => $request->input('transcript'),
                'ai_status'  => 'transcribed',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $updated = DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->first();
        return $this->envelope(['uuid' => $uuid], (array) $updated, []);
    }

    /**
     * POST /api/mcp/v1/calls/recordings/{uuid}/reset-analysis
     * Resets ai_status back to 'pending' so the AI agent re-processes the recording.
     */
    public function resetAnalysis(string $uuid): JsonResponse
    {
        $analysis = DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->first();

        if (!$analysis) {
            return response()->json(['error' => 'Analysis not found'], 404);
        }

        DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->update([
            'ai_status'  => 'pending',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $updated = DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->first();
        return $this->envelope(['uuid' => $uuid], (array) $updated, []);
    }

    /**
     * GET /api/mcp/v1/calls/recordings/{uuid}/analysis
     */
    public function getAnalysis(string $uuid): JsonResponse
    {
        $analysis = DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->first();

        if (!$analysis) {
            return response()->json(['error' => 'Analysis not found'], 404);
        }

        return $this->envelope(['uuid' => $uuid], (array) $analysis, []);
    }

    /**
     * GET /api/mcp/v1/calls/daily-summary/{date}
     */
    public function getDailySummary(string $date): JsonResponse
    {
        $row = DB::table('a1_daily_summaries')->where('summary_date', $date)->first();

        if (!$row) {
            return response()->json(['error' => 'Summary not found'], 404);
        }

        $data = (array) $row;
        $data['key_themes'] = json_decode($row->key_themes ?? '[]', true);

        return $this->envelope(['date' => $date], $data, []);
    }

    /**
     * POST /api/mcp/v1/calls/daily-summary/{date}
     * Body: {summary_text, key_themes[]}
     * Counts filled from a1_cdr automatically.
     */
    public function submitDailySummary(Request $request, string $date): JsonResponse
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['error' => 'Invalid date format, expected YYYY-MM-DD'], 422);
        }

        $fromDt = $date . ' 00:00:00';
        $toDt   = $date . ' 23:59:59';

        $counts = DB::table('a1_cdr')
            ->whereBetween('call_date', [$fromDt, $toDt])
            ->selectRaw("
                COUNT(*) as total,
                SUM(call_type = 'incoming') as incoming,
                SUM(call_type = 'outgoing') as outgoing,
                SUM(call_type = 'missed') as missed
            ")
            ->first();

        $analyzed = DB::table('a1_call_analysis as ca')
            ->join('a1_call_recordings as r', 'r.uuid', '=', 'ca.recording_uuid')
            ->whereBetween('r.call_date', [$fromDt, $toDt])
            ->where('ca.ai_status', 'done')
            ->count();

        $payload = [
            'summary_date'   => $date,
            'summary_text'   => $request->input('summary_text', ''),
            'total_calls'    => (int) ($counts->total    ?? 0),
            'incoming_calls' => (int) ($counts->incoming ?? 0),
            'outgoing_calls' => (int) ($counts->outgoing ?? 0),
            'missed_calls'   => (int) ($counts->missed   ?? 0),
            'calls_analyzed' => $analyzed,
            'key_themes'     => json_encode($request->input('key_themes', [])),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $existing = DB::table('a1_daily_summaries')->where('summary_date', $date)->exists();
        if ($existing) {
            DB::table('a1_daily_summaries')->where('summary_date', $date)->update($payload);
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            DB::table('a1_daily_summaries')->insert($payload);
        }

        $row = DB::table('a1_daily_summaries')->where('summary_date', $date)->first();
        $data = (array) $row;
        $data['key_themes'] = json_decode($row->key_themes ?? '[]', true);

        return $this->envelope(['date' => $date], $data, []);
    }
}
