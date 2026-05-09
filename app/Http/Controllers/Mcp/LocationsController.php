<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Per-location performance under /api/mcp/v1/locations/*.
 * Implemented in step A.9.
 *
 * Aggregations are keyed on rent_deals_arch.first_rent_place which references
 * offices.id. Pobediteley (id=3) closed 2022-07; Lozhinskaya (id=2) trailed
 * off through 2025-2026 — see decisions_log.md D-OPEN-LOCATIONS.
 */
class LocationsController extends BaseController
{
    /**
     * GET /locations/performance?from&to&granularity
     *
     * Per-period × per-office revenue, deals, and average ticket size.
     * Useful for spotting downturns at specific offices (e.g. Lozhinskaya
     * trail-off through 2025–2026).
     */
    public function performance(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();

        $key = $this->cacheKey('locations.performance', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $request) {
            $period = $request->granularityFormatFor('da.cr_time');
            return DB::select("
                SELECT {$period}                                AS period,
                       da.first_rent_place                      AS office_id,
                       o.name                                   AS office_name,
                       o.type                                   AS office_type,
                       COUNT(DISTINCT da.deal_id)               AS deals,
                       COUNT(DISTINCT da.client_id)             AS unique_clients,
                       ROUND(SUM(da.r_paid + da.delivery_paid), 2) AS revenue_byn,
                       ROUND(AVG(da.r_paid + da.delivery_paid), 2) AS avg_ticket_byn
                FROM rent_deals_arch da
                LEFT JOIN offices o ON o.id = da.first_rent_place
                WHERE da.cr_time BETWEEN ? AND ?
                GROUP BY period, da.first_rent_place, o.name, o.type
                ORDER BY period, deals DESC
            ", [$from, $to]);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /locations/lifecycle
     *
     * Office lifecycle: open/close timestamps inferred from rent_deals_arch
     * (first/last deal at that location) plus current `active` flag and
     * total cumulative revenue. Period filters do NOT apply — this is the
     * full history view.
     */
    public function lifecycle(RangeRequest $request): JsonResponse
    {
        $rows = $this->cacheRemember('mcp.locations.lifecycle', self::TTL_META, function () {
            return DB::select("
                SELECT
                    o.id                                                        AS office_id,
                    o.type                                                      AS office_type,
                    o.name                                                      AS office_name,
                    o.short_address                                             AS office_address,
                    o.active                                                    AS currently_active,
                    FROM_UNIXTIME(MIN(NULLIF(da.cr_time, 0)), '%Y-%m-%d')        AS first_deal_date,
                    FROM_UNIXTIME(MAX(NULLIF(da.cr_time, 0)), '%Y-%m-%d')        AS last_deal_date,
                    COUNT(da.arch_deal_id)                                      AS total_deals,
                    ROUND(COALESCE(SUM(da.r_paid + da.delivery_paid), 0), 2)    AS total_revenue_byn,
                    DATEDIFF(
                        FROM_UNIXTIME(NULLIF(MAX(da.cr_time), 0)),
                        FROM_UNIXTIME(NULLIF(MIN(da.cr_time), 0))
                    ) AS active_days
                FROM offices o
                LEFT JOIN rent_deals_arch da ON da.first_rent_place = o.id
                GROUP BY o.id, o.type, o.name, o.short_address, o.active
                ORDER BY o.id
            ");
        });

        return $this->envelope([], $rows);
    }
}
