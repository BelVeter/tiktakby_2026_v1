<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Geographic breakdown under /api/mcp/v1/geo/*.
 * Implemented in step A.8.
 *
 * Stage 1 ships only city-level grouping. Minsk-district resolution requires
 * a free-text address parser and is deferred to Stage 2 (api_stage2_geo.md).
 */
class GeoController extends BaseController
{
    /**
     * GET /geo/clients-by-city?from&to
     *
     * Distinct clients grouped by trimmed/lower-cased clients.city, with
     * counts of unique clients, deals, and total revenue. Period filters by
     * deals — clients with no deal in the window are dropped.
     *
     * Empty/null cities are aggregated into one bucket labelled "(unknown)".
     */
    public function clientsByCity(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();

        $key = $this->cacheKey('geo.clients_by_city', ['from' => $from, 'to' => $to]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to) {
            return DB::select("
                SELECT
                    COALESCE(NULLIF(TRIM(LOWER(c.city)), ''), '(unknown)') AS city_norm,
                    COUNT(DISTINCT c.client_id)                          AS unique_clients,
                    COUNT(DISTINCT da.deal_id)                           AS deals,
                    ROUND(SUM(da.r_paid + da.delivery_paid), 2)          AS revenue_byn
                FROM clients c
                JOIN rent_deals_arch da ON da.client_id = c.client_id
                WHERE da.cr_time BETWEEN ? AND ?
                GROUP BY city_norm
                ORDER BY unique_clients DESC
            ", [$from, $to]);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }
}
