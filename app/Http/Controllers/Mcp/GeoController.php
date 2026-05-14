<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Geographic breakdown under /api/mcp/v1/geo/*.
 *
 * Ships only city-level grouping for Stage 1. Minsk-district resolution
 * requires a free-text address parser (deferred to Stage 2).
 */
class GeoController extends BaseController
{
    /**
     * GET /geo/clients-by-city?from&to&include_carnival
     *
     * Distinct clients grouped by trimmed/lower-cased clients.city, with
     * counts of unique clients, deals (UNION act+arch), and SUM(r_paid +
     * delivery_paid) from sub-deals over the requested window.
     */
    public function clientsByCity(RangeRequest $request): JsonResponse
    {
        $from    = $request->fromTimestamp();
        $to      = $request->toTimestamp();
        $incCarn = $request->includeCarnival();

        $key = $this->cacheKey('geo.clients_by_city', [
            'from' => $from, 'to' => $to, 'inc' => $incCarn ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $incCarn) {
            $sdSub   = $this->unifiedSubDealsSubquery();
            $daSub   = $this->unifiedDealsSubquery();
            $itSub   = $this->unifiedItemsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

            $where  = ['sd.acc_date BETWEEN ? AND ?'];
            $params = [$from, $to];
            $itJoin = '';
            if (!$incCarn && $carnPh) {
                $itJoin   = " LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n ";
                $where[]  = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $params   = array_merge($params, $carnIds);
            }
            $whereSql = implode(' AND ', $where);

            return DB::select("
                SELECT
                    COALESCE(NULLIF(TRIM(LOWER(c.city)), ''), '(unknown)') AS city_norm,
                    COUNT(DISTINCT c.client_id)                            AS unique_clients,
                    COUNT(DISTINCT sd.deal_id)                             AS deals,
                    ROUND(SUM(sd.r_paid + sd.delivery_paid), 2)            AS revenue_byn
                FROM {$sdSub} sd
                JOIN {$daSub} da ON da.deal_id = sd.deal_id
                LEFT JOIN clients c ON c.client_id = da.client_id
                {$itJoin}
                WHERE {$whereSql}
                GROUP BY city_norm
                ORDER BY unique_clients DESC
            ", $params);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }
}
