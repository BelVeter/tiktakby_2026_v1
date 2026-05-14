<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Per-location performance under /api/mcp/v1/locations/*.
 *
 * Methodology (matches legacy /bb/sales_breakdown.php "Офис 1/2/3 / Курьер"):
 *   - Office is taken from sub_deal.place (per-payment), not from
 *     deal.first_rent_place (per-deal). A deal opened at office 1 but
 *     extended at office 2 contributes to both.
 *   - delivery_yn='1' rows are folded into a "Курьер" pseudo-office (office_id=0).
 *   - Pobediteley (id=3) closed 2022-07; Lozhinskaya (id=2) trailed off through
 *     2025-2026 — see decisions_log.md D-OPEN-LOCATIONS.
 */
class LocationsController extends BaseController
{
    public function performance(RangeRequest $request): JsonResponse
    {
        $from    = $request->fromTimestamp();
        $to      = $request->toTimestamp();
        $incCarn = $request->includeCarnival();

        $key = $this->cacheKey('locations.performance', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
            'inc'  => $incCarn ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $incCarn, $request) {
            $sdSub   = $this->unifiedSubDealsSubquery();
            $daSub   = $this->unifiedDealsSubquery();
            $itSub   = $this->unifiedItemsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;
            $period  = $request->granularityFormatFor('sd.acc_date');

            $where  = ['sd.acc_date BETWEEN ? AND ?'];
            $params = [$from, $to];
            if (!$incCarn && $carnPh) {
                $where[] = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $params  = array_merge($params, $carnIds);
            }
            $whereSql = implode(' AND ', $where);

            $itJoin = "
                LEFT JOIN {$daSub} da ON da.deal_id = sd.deal_id
                LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
            ";

            return DB::select("
                SELECT {$period} AS period,
                       CASE WHEN sd.delivery_yn = '1' THEN 0 ELSE sd.place END AS office_id,
                       CASE WHEN sd.delivery_yn = '1' THEN 'Курьер'
                            ELSE COALESCE(o.name, CONCAT('Place ', sd.place)) END AS office_name,
                       CASE WHEN sd.delivery_yn = '1' THEN 'courier'
                            ELSE COALESCE(o.type, 'office') END AS office_type,
                       COUNT(DISTINCT sd.deal_id) AS deals,
                       SUM(CASE WHEN sd.`type` IN ('first_rent','takeaway_plan') THEN 1 ELSE 0 END) AS issuance_events,
                       COUNT(DISTINCT CASE WHEN sd.`type` IN ('first_rent','takeaway_plan')
                                           THEN sd.deal_id END) AS unique_clients_proxy,
                       ROUND(SUM(sd.r_paid + sd.delivery_paid), 2) AS revenue_byn,
                       ROUND(AVG(sd.r_paid + sd.delivery_paid), 2) AS avg_payment_byn
                FROM {$sdSub} sd
                {$itJoin}
                LEFT JOIN offices o ON o.id = sd.place AND sd.delivery_yn != '1'
                WHERE {$whereSql}
                GROUP BY period, office_id, office_name, office_type
                ORDER BY period, revenue_byn DESC
            ", $params);
        });

        return $this->envelope($request->queryEcho(), $rows, [
            'methodology' => 'office = sub_deal.place (per-payment); delivery_yn=1 → pseudo-office 0 (Курьер)',
        ]);
    }

    /**
     * GET /locations/lifecycle
     *
     * Full history view — open/close dates, total deals + revenue per office.
     * Uses sub_deal.place (per-payment) to detect when each office was actually
     * active; first_rent_place would understate office-3 because it's NULL/0
     * for older deals.
     */
    public function lifecycle(): JsonResponse
    {
        $rows = $this->cacheRemember('mcp.locations.lifecycle', self::TTL_META, function () {
            $sdSub = $this->unifiedSubDealsSubquery();

            return DB::select("
                SELECT
                    o.id                                                    AS office_id,
                    o.type                                                  AS office_type,
                    o.name                                                  AS office_name,
                    o.short_address                                         AS office_address,
                    o.active                                                AS currently_active,
                    FROM_UNIXTIME(MIN(NULLIF(sd.acc_date, 0)), '%Y-%m-%d')    AS first_activity_date,
                    FROM_UNIXTIME(MAX(NULLIF(sd.acc_date, 0)), '%Y-%m-%d')    AS last_activity_date,
                    COUNT(DISTINCT sd.deal_id)                              AS total_deals,
                    ROUND(COALESCE(SUM(sd.r_paid + sd.delivery_paid), 0), 2) AS total_revenue_byn,
                    DATEDIFF(
                        FROM_UNIXTIME(NULLIF(MAX(sd.acc_date), 0)),
                        FROM_UNIXTIME(NULLIF(MIN(sd.acc_date), 0))
                    ) AS active_days
                FROM offices o
                LEFT JOIN {$sdSub} sd ON sd.place = o.id AND sd.delivery_yn != '1'
                GROUP BY o.id, o.type, o.name, o.short_address, o.active
                ORDER BY o.id
            ");
        });

        return $this->envelope([], $rows);
    }
}
