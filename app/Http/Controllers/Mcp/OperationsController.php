<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Operational funnel + per-dimension breakdowns under /api/mcp/v1/operations/*.
 *
 * Existing endpoints (migrated from McpAnalyticsController):
 *   - GET /orders/stats   (kept under its original path for B/W compat)
 *   - GET /deals/list
 * Stage 1 additions (A.5):
 *   - GET /operations/funnel
 *   - GET /operations/timeline
 *   - GET /operations/by-category
 *   - GET /operations/by-location
 */
class OperationsController extends BaseController
{
    public function funnel(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    public function timeline(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    public function byCategory(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    public function byLocation(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    /**
     * GET /orders/stats?date_from=&date_to=&group_by=day|week|month
     * Legacy endpoint preserved for clients already integrated.
     */
    public function ordersStats(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'group_by'  => 'in:day,week,month',
        ]);

        $dateFrom = $request->date('date_from')->startOfDay()->timestamp;
        $dateTo   = $request->date('date_to')->endOfDay()->timestamp;
        $groupBy  = $request->get('group_by', 'day');

        switch ($groupBy) {
            case 'week':
                $groupExpr = "DATE_FORMAT(FROM_UNIXTIME(cr_time), '%x-W%v')";
                break;
            case 'month':
                $groupExpr = "DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m')";
                break;
            default:
                $groupExpr = "DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m-%d')";
        }

        $orders = DB::select("
            SELECT
                {$groupExpr} AS period,
                COUNT(*) AS total,
                SUM(CASE WHEN web = 1 THEN 1 ELSE 0 END) AS from_web,
                SUM(CASE WHEN status = 'одобрена' OR status = 'выдана' THEN 1 ELSE 0 END) AS approved
            FROM rent_orders
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
            ORDER BY period
        ", [$dateFrom, $dateTo]);

        $brons = DB::select("
            SELECT
                {$groupExpr} AS period,
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('выдана','одобрена') THEN 1 ELSE 0 END) AS approved
            FROM karn_brons
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
            ORDER BY period
        ", [$dateFrom, $dateTo]);

        $deals = DB::select("
            SELECT
                {$groupExpr} AS period,
                COUNT(*) AS total,
                COUNT(DISTINCT client_id) AS unique_clients
            FROM rent_deals_act
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
            ORDER BY period
        ", [$dateFrom, $dateTo]);

        return $this->envelope([
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
            'group_by'  => $groupBy,
        ], [
            'orders'            => $orders,
            'carnival_bookings' => $brons,
            'deals'             => $deals,
        ]);
    }

    /**
     * GET /deals/list?date_from=&date_to=&limit=&offset=
     * Legacy endpoint preserved for clients already integrated.
     */
    public function dealsList(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'limit'     => 'integer|min:1|max:500',
            'offset'    => 'integer|min:0',
        ]);

        $dateFrom = $request->date('date_from')->startOfDay()->timestamp;
        $dateTo   = $request->date('date_to')->endOfDay()->timestamp;
        $limit    = (int) $request->get('limit', 100);
        $offset   = (int) $request->get('offset', 0);

        $deals = DB::select("
            SELECT
                rda.deal_id,
                rda.client_id,
                rda.item_inv_n,
                rda.deal_status,
                FROM_UNIXTIME(rda.cr_time, '%Y-%m-%d') AS deal_date,
                rda.r_to_pay              AS rental_amount_byn,
                rda.delivery_yn,
                rda.delivery_to_pay       AS delivery_amount_byn,
                CONCAT_WS(', ', NULLIF(c.str,''), NULLIF(c.dom,''), NULLIF(c.kv,'')) AS client_address,
                c.city                    AS client_city,
                tri.model_id,
                tri.cat_id,
                rmw.l2_name               AS model_name
            FROM rent_deals_act rda
            LEFT JOIN clients c            ON c.client_id = rda.client_id
            LEFT JOIN tovar_rent_items tri ON tri.item_inv_n = rda.item_inv_n
            LEFT JOIN rent_model_web rmw   ON rmw.model_id = tri.model_id AND rmw.lang = 'ru'
            WHERE rda.cr_time BETWEEN ? AND ?
            ORDER BY rda.cr_time DESC
            LIMIT ? OFFSET ?
        ", [$dateFrom, $dateTo, $limit, $offset]);

        $total = DB::selectOne("
            SELECT COUNT(*) AS cnt FROM rent_deals_act
            WHERE cr_time BETWEEN ? AND ?
        ", [$dateFrom, $dateTo])->cnt;

        return $this->envelope([
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
            'limit'     => $limit,
            'offset'    => $offset,
        ], $deals, [
            'total_rows' => (int) $total,
            'limit'      => $limit,
            'offset'     => $offset,
        ]);
    }
}
