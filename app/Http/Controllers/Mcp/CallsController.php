<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $perPage = min(200, max(1, (int) $request->get('per_page', 50)));

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';

        $query = DB::table('a1_call_recordings')
            ->whereBetween('call_date', [$fromDt, $toDt]);

        if ($caller) {
            $query->where('caller_part', 'like', '%' . $caller . '%');
        }
        if ($callee) {
            $query->where('callee_part', 'like', '%' . $callee . '%');
        }

        $total = $query->count();

        $rows = (clone $query)
            ->orderBy('call_date', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get(['uuid', 'record_name', 'call_date', 'caller_part', 'callee_part', 'call_duration', 'file_size', 'downloaded_at']);

        $totalSizeBytes = (int) Cache::remember('a1.recordings.total_size', 300, function () {
            return DB::table('a1_call_recordings')->sum('file_size');
        });

        $lastFetch = DB::table('a1_recordings_fetch_log')
            ->where('status', 'success')
            ->orderBy('fetched_at', 'desc')
            ->value('fetched_at');

        $data = $rows->map(function ($row) {
            return [
                'uuid'          => $row->uuid,
                'record_name'   => $row->record_name,
                'call_date'     => $row->call_date,
                'caller_part'   => $row->caller_part,
                'callee_part'   => $row->callee_part,
                'call_duration' => (int) $row->call_duration,
                'file_size'     => (int) $row->file_size,
                'downloaded_at' => $row->downloaded_at,
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

        return response()->download($diskPath, $filename, ['Content-Type' => 'audio/mpeg']);
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
            ->whereBetween('call_date', [$from . ' 00:00:00', $to . ' 23:59:59']);

        if ($callType !== 'all' && in_array($callType, ['incoming', 'outgoing', 'missed'], true)) {
            $query->where('call_type', $callType);
        }

        $total = $query->count();
        $rows  = (clone $query)
            ->orderBy('call_date', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get(['uuid', 'call_date', 'call_type', 'caller_number', 'callee_number', 'call_duration', 'recording_uuid']);

        return $this->envelope(
            ['from' => $from, 'to' => $to, 'call_type' => $callType],
            $rows->values()->all(),
            ['total_rows' => $total, 'page' => $page, 'per_page' => $perPage]
        );
    }

    /**
     * GET /api/mcp/v1/calls/pending-analysis
     * Resets stale 'processing' records (>2h) back to 'pending'.
     * Returns pending recordings and sets them to 'processing'.
     */
    public function pendingAnalysis(Request $request): JsonResponse
    {
        $from  = $request->get('from', date('Y-m-d', strtotime('-1 day')));
        $to    = $request->get('to',   date('Y-m-d'));
        $limit = min(50, max(1, (int) $request->get('limit', 20)));

        // Reset stale processing records (stuck > 2 hours)
        DB::table('a1_call_analysis')
            ->where('ai_status', 'processing')
            ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-2 hours')))
            ->update(['ai_status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')]);

        // Find pending recordings in date range
        $rows = DB::table('a1_call_analysis as ca')
            ->join('a1_call_recordings as r', 'r.uuid', '=', 'ca.recording_uuid')
            ->where('ca.ai_status', 'pending')
            ->whereBetween('r.call_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('r.call_date', 'asc')
            ->limit($limit)
            ->get(['r.uuid', 'r.call_date', 'r.caller_part', 'r.callee_part', 'r.call_duration', 'r.file_size']);

        if ($rows->isEmpty()) {
            return $this->envelope(['from' => $from, 'to' => $to], [], ['total_rows' => 0]);
        }

        $uuids = $rows->pluck('uuid')->all();

        // Mark as processing
        DB::table('a1_call_analysis')
            ->whereIn('recording_uuid', $uuids)
            ->update(['ai_status' => 'processing', 'updated_at' => date('Y-m-d H:i:s')]);

        $baseUrl = config('app.url') . '/api/mcp/v1/calls/recordings/';
        $data = $rows->map(function ($r) use ($baseUrl) {
            return array_merge((array) $r, [
                'file_url' => $baseUrl . $r->uuid . '/file',
            ]);
        })->values()->all();

        return $this->envelope(
            ['from' => $from, 'to' => $to],
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
        } else {
            DB::table('a1_call_analysis')->where('recording_uuid', $uuid)->update([
                'transcript'           => $request->input('transcript'),
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
