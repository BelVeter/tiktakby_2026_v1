<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Operational funnel and per-dimension breakdowns under /api/mcp/v1/operations/*.
 *
 * Funnel stages and source tables:
 *   Stage 1 — leads:     rent_orders_arch.cr_time + zvonki.cr_time
 *   Stage 2 — deals:     rent_deals_arch.cr_time           (issuance)
 *   Stage 3 — sub_deals: rent_sub_deals_arch.cr_time       (extensions)
 *   Stage 4 — returns:   rent_deals_arch.return_date > 0   (returned items)
 *
 * Category filter (RangeRequest::CATEGORIES) is applied via the
 * cat_id → tovar_cats → subrazdel_category → razdel chain. zvonki has no
 * cat_id so phone leads are not filtered when category is set — surfaced
 * via meta.warnings.
 *
 * Location filter narrows on rent_deals_arch.first_rent_place; leads have no
 * geo binding so they are not filtered.
 *
 * Existing endpoints (migrated from McpAnalyticsController):
 *   - GET /orders/stats
 *   - GET /deals/list
 */
class OperationsController extends BaseController
{
    /**
     * GET /operations/funnel?from&to&category&location
     */
    public function funnel(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $category = $request->input('category', 'all');
        $location = $request->input('location', 'all');

        $key = $this->cacheKey('operations.funnel', [
            'from' => $from, 'to' => $to, 'cat' => $category, 'loc' => $location,
        ]);

        $payload = $this->cacheRemember($key, self::TTL_DEFAULT, function () use ($from, $to, $category, $location) {
            $razdel = $category !== 'all' ? $this->categoryToRazdelId($category) : null;
            if ($category !== 'all' && $razdel === null) {
                return null; // unknown / unavailable category — caller will see empty data
            }

            $orders   = $this->countOrders($from, $to, $razdel);
            $calls    = $this->countCalls($from, $to);
            $deals    = $this->countDeals($from, $to, $razdel, $location);
            $subDeals = $this->countSubDeals($from, $to, $razdel);
            $returns  = $this->countReturns($from, $to, $razdel, $location);

            $leadsTotal = $orders + $calls;
            return [
                'leads' => [
                    'online_orders' => $orders,
                    'phone_calls'   => $calls,
                    'total'         => $leadsTotal,
                ],
                'deals'      => $deals,
                'sub_deals'  => $subDeals,
                'returns'    => $returns,
                'conversion_rates' => [
                    'lead_to_deal'    => $leadsTotal > 0 ? round($deals / $leadsTotal, 4) : null,
                    'deal_to_sub_avg' => $deals > 0 ? round($subDeals / $deals, 4) : null,
                    'deal_to_return'  => $deals > 0 ? round($returns / $deals, 4) : null,
                ],
            ];
        });

        $meta = [];
        if ($category !== 'all' && $payload !== null) {
            $meta['warnings'] = [[
                'code'    => 'phone_calls_not_filtered',
                'message' => 'zvonki has no category column; phone_calls counts are NOT filtered by category',
            ]];
        }
        return $this->envelope($request->queryEcho(), $payload ?? [], $meta);
    }

    /**
     * GET /operations/timeline?from&to&granularity&category&location
     * Period-bucketed funnel (one row per period).
     */
    public function timeline(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $category = $request->input('category', 'all');
        $location = $request->input('location', 'all');

        $key = $this->cacheKey('operations.timeline', [
            'from' => $from, 'to' => $to, 'cat' => $category, 'loc' => $location,
            'g'    => $request->input('granularity'),
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $category, $location, $request) {
            $razdel = $category !== 'all' ? $this->categoryToRazdelId($category) : null;
            if ($category !== 'all' && $razdel === null) {
                return [];
            }

            $periods = [];

            $orderPeriod = $request->granularityFormatFor('o.cr_time');
            $orderJoins  = '';
            $orderWhere  = ['o.cr_time BETWEEN ? AND ?'];
            $orderParams = [$from, $to];
            if ($razdel !== null) {
                $orderJoins  = '
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = o.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ';
                $orderWhere[]  = 'rs.id_razdel = ?';
                $orderParams[] = $razdel;
            }
            $orderWhereSql = implode(' AND ', $orderWhere);
            foreach (DB::select("
                SELECT {$orderPeriod} AS period, COUNT(*) AS cnt
                FROM rent_orders_arch o
                {$orderJoins}
                WHERE {$orderWhereSql}
                GROUP BY period
            ", $orderParams) as $r) {
                $periods[$r->period]['period']        = $r->period;
                $periods[$r->period]['online_orders'] = (int) $r->cnt;
            }

            $callsPeriod = $request->granularityFormatFor('cr_time');
            foreach (DB::select("
                SELECT {$callsPeriod} AS period, COUNT(*) AS cnt
                FROM zvonki
                WHERE cr_time BETWEEN ? AND ?
                GROUP BY period
            ", [$from, $to]) as $r) {
                $periods[$r->period]['period']      = $r->period;
                $periods[$r->period]['phone_calls'] = (int) $r->cnt;
            }

            $dealPeriod = $request->granularityFormatFor('da.cr_time');
            $dealJoins  = '';
            $dealWhere  = ['da.cr_time BETWEEN ? AND ?'];
            $dealParams = [$from, $to];
            if ($razdel !== null) {
                $dealJoins  = '
                    JOIN tovar_rent_items tri  ON tri.item_inv_n = da.item_inv_n
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ';
                $dealWhere[]  = 'rs.id_razdel = ?';
                $dealParams[] = $razdel;
            }
            if ($location !== 'all' && is_numeric($location)) {
                $dealWhere[]  = 'da.first_rent_place = ?';
                $dealParams[] = (int) $location;
            }
            $dealWhereSql = implode(' AND ', $dealWhere);
            foreach (DB::select("
                SELECT {$dealPeriod} AS period, COUNT(DISTINCT da.deal_id) AS cnt
                FROM rent_deals_arch da
                {$dealJoins}
                WHERE {$dealWhereSql}
                GROUP BY period
            ", $dealParams) as $r) {
                $periods[$r->period]['period'] = $r->period;
                $periods[$r->period]['deals']  = (int) $r->cnt;
            }

            // Sub-deals — narrow by deal_id linkage when category filter applies, otherwise direct date filter.
            $subPeriod = $request->granularityFormatFor('sd.cr_time');
            if ($razdel !== null) {
                $subJoins = '
                    JOIN rent_deals_arch da    ON da.deal_id = sd.deal_id
                    JOIN tovar_rent_items tri  ON tri.item_inv_n = da.item_inv_n
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ';
                $subWhere  = ['sd.cr_time BETWEEN ? AND ?', 'rs.id_razdel = ?'];
                $subParams = [$from, $to, $razdel];
            } else {
                $subJoins  = '';
                $subWhere  = ['sd.cr_time BETWEEN ? AND ?'];
                $subParams = [$from, $to];
            }
            $subWhereSql = implode(' AND ', $subWhere);
            foreach (DB::select("
                SELECT {$subPeriod} AS period, COUNT(*) AS cnt
                FROM rent_sub_deals_arch sd
                {$subJoins}
                WHERE {$subWhereSql}
                GROUP BY period
            ", $subParams) as $r) {
                $periods[$r->period]['period']    = $r->period;
                $periods[$r->period]['sub_deals'] = (int) $r->cnt;
            }

            $retPeriod = $request->granularityFormatFor('da.return_date');
            $retJoins  = '';
            $retWhere  = ['da.return_date BETWEEN ? AND ?', 'da.return_date > 0'];
            $retParams = [$from, $to];
            if ($razdel !== null) {
                $retJoins  = '
                    JOIN tovar_rent_items tri  ON tri.item_inv_n = da.item_inv_n
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ';
                $retWhere[]  = 'rs.id_razdel = ?';
                $retParams[] = $razdel;
            }
            if ($location !== 'all' && is_numeric($location)) {
                $retWhere[]  = 'da.first_rent_place = ?';
                $retParams[] = (int) $location;
            }
            $retWhereSql = implode(' AND ', $retWhere);
            foreach (DB::select("
                SELECT {$retPeriod} AS period, COUNT(DISTINCT da.deal_id) AS cnt
                FROM rent_deals_arch da
                {$retJoins}
                WHERE {$retWhereSql}
                GROUP BY period
            ", $retParams) as $r) {
                $periods[$r->period]['period']  = $r->period;
                $periods[$r->period]['returns'] = (int) $r->cnt;
            }

            ksort($periods);
            $out = [];
            foreach ($periods as $p) {
                $online  = $p['online_orders'] ?? 0;
                $calls   = $p['phone_calls']   ?? 0;
                $deals   = $p['deals']         ?? 0;
                $subs    = $p['sub_deals']     ?? 0;
                $returns = $p['returns']       ?? 0;
                $leads   = $online + $calls;
                $out[] = [
                    'period'        => $p['period'],
                    'online_orders' => $online,
                    'phone_calls'   => $calls,
                    'leads_total'   => $leads,
                    'deals'         => $deals,
                    'sub_deals'     => $subs,
                    'returns'       => $returns,
                    'cr_lead_to_deal'   => $leads > 0 ? round($deals / $leads, 4) : null,
                    'cr_deal_to_sub'    => $deals > 0 ? round($subs / $deals, 4)  : null,
                    'cr_deal_to_return' => $deals > 0 ? round($returns / $deals, 4) : null,
                ];
            }
            return $out;
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /operations/by-category?from&to
     * Per-business-category counts of deals + sub_deals + returns. Leads from
     * rent_orders_arch are reported when the order's cat_id maps to a known
     * razdel.
     */
    public function byCategory(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();

        $key = $this->cacheKey('operations.by_category', ['from' => $from, 'to' => $to]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to) {
            $orders = DB::select("
                SELECT r.id_razdel,
                       r.name_razdel_text,
                       r.url_razdel_name,
                       COUNT(*) AS cnt
                FROM rent_orders_arch o
                JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = o.cat_id
                JOIN razdel_subrazdel rs   ON rs.id_sub_razdel    = sc.id_sub_razdel
                JOIN razdel r              ON r.id_razdel         = rs.id_razdel
                WHERE o.cr_time BETWEEN ? AND ?
                GROUP BY r.id_razdel, r.name_razdel_text, r.url_razdel_name
            ", [$from, $to]);

            $deals = DB::select("
                SELECT r.id_razdel,
                       r.name_razdel_text,
                       r.url_razdel_name,
                       COUNT(DISTINCT da.deal_id) AS cnt,
                       ROUND(SUM(da.r_paid + da.delivery_paid), 2) AS revenue_byn
                FROM rent_deals_arch da
                JOIN tovar_rent_items tri  ON tri.item_inv_n = da.item_inv_n
                JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                JOIN razdel_subrazdel rs   ON rs.id_sub_razdel    = sc.id_sub_razdel
                JOIN razdel r              ON r.id_razdel         = rs.id_razdel
                WHERE da.cr_time BETWEEN ? AND ?
                GROUP BY r.id_razdel, r.name_razdel_text, r.url_razdel_name
            ", [$from, $to]);

            $byRazdel = [];
            foreach ($orders as $o) {
                $byRazdel[$o->id_razdel] = [
                    'razdel_id'        => (int) $o->id_razdel,
                    'name'             => $o->name_razdel_text,
                    'url_slug'         => $o->url_razdel_name,
                    'online_orders'    => (int) $o->cnt,
                    'deals'            => 0,
                    'revenue_byn'      => 0.0,
                ];
            }
            foreach ($deals as $d) {
                $existing = $byRazdel[$d->id_razdel] ?? null;
                if (!$existing) {
                    $existing = [
                        'razdel_id'     => (int) $d->id_razdel,
                        'name'          => $d->name_razdel_text,
                        'url_slug'      => $d->url_razdel_name,
                        'online_orders' => 0,
                    ];
                }
                $existing['deals']       = (int) $d->cnt;
                $existing['revenue_byn'] = (float) $d->revenue_byn;
                $byRazdel[$d->id_razdel] = $existing;
            }

            usort($byRazdel, fn($a, $b) => $b['deals'] <=> $a['deals']);
            return array_values($byRazdel);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /operations/by-location?from&to
     * Per-office counts of deals + sub_deals + returns + revenue.
     * NULL/0 first_rent_place is reported as a separate "unknown" row.
     */
    public function byLocation(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();

        $key = $this->cacheKey('operations.by_location', ['from' => $from, 'to' => $to]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to) {
            return DB::select("
                SELECT
                    da.first_rent_place AS office_id,
                    o.name              AS office_name,
                    o.type              AS office_type,
                    o.short_address     AS office_address,
                    COUNT(DISTINCT da.deal_id)                          AS deals,
                    COUNT(DISTINCT da.client_id)                        AS unique_clients,
                    ROUND(SUM(da.r_paid + da.delivery_paid), 2)         AS revenue_byn,
                    SUM(CASE WHEN da.return_date BETWEEN ? AND ? AND da.return_date > 0 THEN 1 ELSE 0 END) AS returns_in_period,
                    FROM_UNIXTIME(MIN(da.cr_time), '%Y-%m-%d')          AS first_deal_in_period,
                    FROM_UNIXTIME(MAX(da.cr_time), '%Y-%m-%d')          AS last_deal_in_period
                FROM rent_deals_arch da
                LEFT JOIN offices o ON o.id = da.first_rent_place
                WHERE da.cr_time BETWEEN ? AND ?
                GROUP BY da.first_rent_place, o.name, o.type, o.short_address
                ORDER BY deals DESC
            ", [$from, $to, $from, $to]);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    // ─── Funnel helpers ───────────────────────────────────────────────────

    private function countOrders(int $from, int $to, ?int $razdel): int
    {
        if ($razdel === null) {
            $row = DB::selectOne("SELECT COUNT(*) AS c FROM rent_orders_arch WHERE cr_time BETWEEN ? AND ?", [$from, $to]);
        } else {
            $row = DB::selectOne("
                SELECT COUNT(*) AS c
                FROM rent_orders_arch o
                JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = o.cat_id
                JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                WHERE o.cr_time BETWEEN ? AND ? AND rs.id_razdel = ?
            ", [$from, $to, $razdel]);
        }
        return (int) ($row->c ?? 0);
    }

    private function countCalls(int $from, int $to): int
    {
        $row = DB::selectOne("SELECT COUNT(*) AS c FROM zvonki WHERE cr_time BETWEEN ? AND ?", [$from, $to]);
        return (int) ($row->c ?? 0);
    }

    private function countDeals(int $from, int $to, ?int $razdel, $location): int
    {
        $where  = ['da.cr_time BETWEEN ? AND ?'];
        $joins  = '';
        $params = [$from, $to];
        if ($razdel !== null) {
            $joins   = '
                JOIN tovar_rent_items tri  ON tri.item_inv_n = da.item_inv_n
                JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
            ';
            $where[]  = 'rs.id_razdel = ?';
            $params[] = $razdel;
        }
        if ($location !== 'all' && is_numeric($location)) {
            $where[]  = 'da.first_rent_place = ?';
            $params[] = (int) $location;
        }
        $whereSql = implode(' AND ', $where);
        $row = DB::selectOne("SELECT COUNT(DISTINCT da.deal_id) AS c FROM rent_deals_arch da {$joins} WHERE {$whereSql}", $params);
        return (int) ($row->c ?? 0);
    }

    private function countSubDeals(int $from, int $to, ?int $razdel): int
    {
        if ($razdel === null) {
            $row = DB::selectOne("SELECT COUNT(*) AS c FROM rent_sub_deals_arch WHERE cr_time BETWEEN ? AND ?", [$from, $to]);
        } else {
            $row = DB::selectOne("
                SELECT COUNT(*) AS c
                FROM rent_sub_deals_arch sd
                JOIN rent_deals_arch da   ON da.deal_id = sd.deal_id
                JOIN tovar_rent_items tri ON tri.item_inv_n = da.item_inv_n
                JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                WHERE sd.cr_time BETWEEN ? AND ? AND rs.id_razdel = ?
            ", [$from, $to, $razdel]);
        }
        return (int) ($row->c ?? 0);
    }

    private function countReturns(int $from, int $to, ?int $razdel, $location): int
    {
        $where  = ['da.return_date BETWEEN ? AND ?', 'da.return_date > 0'];
        $joins  = '';
        $params = [$from, $to];
        if ($razdel !== null) {
            $joins   = '
                JOIN tovar_rent_items tri  ON tri.item_inv_n = da.item_inv_n
                JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
            ';
            $where[]  = 'rs.id_razdel = ?';
            $params[] = $razdel;
        }
        if ($location !== 'all' && is_numeric($location)) {
            $where[]  = 'da.first_rent_place = ?';
            $params[] = (int) $location;
        }
        $whereSql = implode(' AND ', $where);
        $row = DB::selectOne("SELECT COUNT(DISTINCT da.deal_id) AS c FROM rent_deals_arch da {$joins} WHERE {$whereSql}", $params);
        return (int) ($row->c ?? 0);
    }

    private function categoryToRazdelId(string $category): ?int
    {
        $map = $this->cacheRemember('mcp.category_razdel_map', self::TTL_META, function () {
            $rows = DB::select("SELECT id_razdel, url_razdel_name FROM razdel");
            $byUrl = [];
            foreach ($rows as $r) {
                $byUrl[$r->url_razdel_name] = (int) $r->id_razdel;
            }
            return [
                'children' => $byUrl['prokat-detskih-tovarov'] ?? null,
                'costumes' => $byUrl['karnavalnye-kostyumy']   ?? null,
                'medical'  => $byUrl['medical-prokat']         ?? null,
                'cleaning' => $byUrl['prokat-uborka']          ?? null,
                'sports'   => $byUrl['prokat-sports']          ?? null,
                'tools'    => null,
            ];
        });
        return $map[$category] ?? null;
    }

    // ─── Legacy endpoints ─────────────────────────────────────────────────

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
