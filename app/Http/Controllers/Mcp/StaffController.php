<?php

namespace App\Http\Controllers\Mcp;

use bb\classes\PageVisitCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/mcp/v1/staff/page-visits/by-user
 * GET /api/mcp/v1/staff/page-visits/by-page
 *
 * Reads bb_page_visits, populated by bb/classes/PageVisitTracker.php
 * (auto_prepend_file on every bb/ admin request). Mirrors the two tables of
 * the owner-only bb/page_track.php report so an AI agent can read the same
 * data. See docs/superpowers/specs/2026-09-03-crm-usage-tracking-design.md.
 */
class StaffController extends BaseController
{
    public function byUser(Request $request): JsonResponse
    {
        $from = $request->get('from', date('Y-m-d', strtotime('-30 days')));
        $to   = $request->get('to', date('Y-m-d'));
        $page = $request->get('page');

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to . ' 23:59:59';

        $query = DB::table('bb_page_visits')
            ->leftJoin('logpass', 'logpass.logpass_id', '=', 'bb_page_visits.user_id')
            ->whereBetween('bb_page_visits.visited_at', [$fromDt, $toDt]);

        if ($page) {
            $query->where('bb_page_visits.page', $page);
        }

        $rows = $query
            ->select(
                'bb_page_visits.user_id',
                DB::raw("COALESCE(logpass.lp_fio, CONCAT('#', bb_page_visits.user_id)) as user_name"),
                DB::raw('COUNT(*) as visits'),
                DB::raw('COUNT(DISTINCT bb_page_visits.page) as distinct_pages'),
                DB::raw('MAX(bb_page_visits.visited_at) as last_visit_at')
            )
            ->groupBy('bb_page_visits.user_id', 'logpass.lp_fio')
            ->orderByDesc('visits')
            ->get();

        $data = $rows->map(fn ($r) => [
            'user_id'        => (int) $r->user_id,
            'user_name'      => $r->user_name,
            'visits'         => (int) $r->visits,
            'distinct_pages' => (int) $r->distinct_pages,
            'last_visit_at'  => $r->last_visit_at,
        ])->values()->all();

        return $this->envelope(['from' => $from, 'to' => $to, 'page' => $page], $data);
    }

    public function byPage(Request $request): JsonResponse
    {
        $from   = $request->get('from', date('Y-m-d', strtotime('-30 days')));
        $to     = $request->get('to', date('Y-m-d'));
        $userId = $request->get('user_id');

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to . ' 23:59:59';

        $visitsQuery = DB::table('bb_page_visits')->whereBetween('visited_at', [$fromDt, $toDt]);
        if ($userId) {
            $visitsQuery->where('user_id', (int) $userId);
        }

        $visits = $visitsQuery
            ->select(
                'page',
                DB::raw('COUNT(*) as visits'),
                DB::raw('COUNT(DISTINCT user_id) as distinct_users'),
                DB::raw('MAX(visited_at) as last_visit_at')
            )
            ->groupBy('page')
            ->get()
            ->keyBy('page');

        $data = collect(PageVisitCatalog::listTrackablePages())
            ->map(function (string $page) use ($visits) {
                $row = $visits->get($page);
                return [
                    'page'           => $page,
                    'visits'         => $row ? (int) $row->visits : 0,
                    'distinct_users' => $row ? (int) $row->distinct_users : 0,
                    'last_visit_at'  => $row->last_visit_at ?? null,
                ];
            })
            ->sortBy('visits')
            ->values()
            ->all();

        return $this->envelope(['from' => $from, 'to' => $to, 'user_id' => $userId], $data);
    }
}
