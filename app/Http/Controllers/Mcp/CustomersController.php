<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer cohorts, retention, repeat intervals, LTV.
 *
 * Existing endpoint (migrated from McpAnalyticsController):
 *   - GET /clients/ltv
 * Stage 1 additions (A.7):
 *   - GET /customers/timeline
 *   - GET /customers/cohorts
 *   - GET /customers/repeat-intervals
 *
 * No PII (full name, phone, passport) is ever returned — only client_id.
 */
class CustomersController extends BaseController
{
    /**
     * GET /clients/ltv?min_deals&min_ltv&limit&offset
     */
    public function ltv(Request $request): JsonResponse
    {
        $request->validate([
            'min_deals' => 'integer|min:1',
            'min_ltv'   => 'numeric|min:0',
            'limit'     => 'integer|min:1|max:1000',
            'offset'    => 'integer|min:0',
        ]);

        $minDeals = (int) $request->get('min_deals', 1);
        $minLtv   = (float) $request->get('min_ltv', 0);
        $limit    = (int) $request->get('limit', 200);
        $offset   = (int) $request->get('offset', 0);

        $rows = DB::select("
            SELECT
                rda.client_id,
                c.city                                            AS client_city,
                c.status                                          AS client_status,
                FROM_UNIXTIME(c.cr_time, '%Y-%m-%d')              AS client_since,
                COUNT(rda.deal_id)                                AS total_deals,
                ROUND(SUM(rda.r_to_pay + rda.delivery_to_pay), 2) AS ltv_byn,
                ROUND(AVG(rda.r_to_pay), 2)                       AS avg_deal_byn,
                MIN(FROM_UNIXTIME(rda.cr_time, '%Y-%m-%d'))       AS first_deal_date,
                MAX(FROM_UNIXTIME(rda.cr_time, '%Y-%m-%d'))       AS last_deal_date,
                DATEDIFF(
                    FROM_UNIXTIME(MAX(rda.cr_time)),
                    FROM_UNIXTIME(MIN(rda.cr_time))
                ) AS active_days
            FROM rent_deals_act rda
            LEFT JOIN clients c ON c.client_id = rda.client_id
            WHERE rda.deal_status NOT IN ('отменена','тест')
            GROUP BY rda.client_id, c.city, c.status, c.cr_time
            HAVING total_deals >= ? AND ltv_byn >= ?
            ORDER BY ltv_byn DESC
            LIMIT ? OFFSET ?
        ", [$minDeals, $minLtv, $limit, $offset]);

        return $this->envelope([
            'min_deals' => $minDeals,
            'min_ltv'   => $minLtv,
            'limit'     => $limit,
            'offset'    => $offset,
        ], $rows);
    }

    public function timeline(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    public function cohorts(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    public function repeatIntervals(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }
}
