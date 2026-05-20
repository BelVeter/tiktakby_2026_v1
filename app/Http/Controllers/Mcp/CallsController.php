<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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

        $totalSizeBytes = (int) DB::table('a1_call_recordings')->sum('file_size');

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
                'from'     => $from,
                'to'       => $to,
                'caller'   => $caller,
                'callee'   => $callee,
                'page'     => $page,
                'per_page' => $perPage,
            ],
            $data,
            [
                'total_rows'        => $total,
                'page'              => $page,
                'per_page'          => $perPage,
                'total_size_bytes'  => $totalSizeBytes,
                'quota_bytes'       => 1_073_741_824,
                'data_freshness'    => $lastFetch,
            ]
        );
    }

    /**
     * GET /api/mcp/v1/calls/recordings/{uuid}/file
     *
     * Streams the mp3 binary. Does NOT return the standard JSON envelope.
     * Returns 404 if the DB record is missing or the file is gone from disk.
     */
    public function streamFile(string $uuid): Response
    {
        $recording = DB::table('a1_call_recordings')
            ->where('uuid', $uuid)
            ->first();

        if (!$recording) {
            abort(404, 'Recording not found');
        }

        $diskPath = storage_path('app/' . $recording->file_path);

        if (!file_exists($diskPath)) {
            abort(404, 'Recording file not found on disk');
        }

        $parts    = explode('/', $recording->file_path);
        $filename = end($parts);

        return response(file_get_contents($diskPath), 200, [
            'Content-Type'        => 'audio/mpeg',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => filesize($diskPath),
        ]);
    }
}
