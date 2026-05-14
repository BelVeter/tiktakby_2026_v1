<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Operational funnel and per-dimension breakdowns under /api/mcp/v1/operations/*.
 *
 * Methodology (matches legacy /bb/reports.php):
 *   Stage 1 — leads:     rent_orders_arch.cr_time + zvonki.cr_time
 *   Stage 2 — deals:     UNION(deals_act, deals_arch).cr_time
 *                        — counted as the number of issuance events
 *                        (sub-deal type IN ('first_rent','takeaway_plan'))
 *                        when accuracy matters; otherwise distinct deal_id.
 *   Stage 3 — sub_deals: UNION(sub_deals_act, sub_deals_arch).cr_time
 *   Stage 4 — returns:   UNION(deals_act, deals_arch).return_date > 0
 *
 * Office attribution uses sub_deal.place + delivery_yn (per-payment).
 */
class OperationsController extends BaseController
{
    public function funnel(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $category = $request->input('category', 'all');
        $location = $request->input('location', 'all');
        $incCarn  = $request->includeCarnival();

        $key = $this->cacheKey('operations.funnel', [
            'from' => $from, 'to' => $to, 'cat' => $category, 'loc' => $location,
            'inc'  => $incCarn ? 1 : 0,
        ]);

        $payload = $this->cacheRemember($key, self::TTL_DEFAULT, function () use ($from, $to, $category, $location, $incCarn) {
            $razdel = $category !== 'all' ? $this->categoryToRazdelId($category) : null;
            if ($category !== 'all' && $razdel === null) {
                return null;
            }

            $orders     = $this->countOrders($from, $to, $razdel);
            $calls      = $this->countCalls($from, $to);
            $deals      = $this->countDeals($from, $to, $razdel, $location, $incCarn);
            $issuances  = $this->countIssuanceEvents($from, $to, $razdel, $location, $incCarn);
            $subDeals   = $this->countSubDeals($from, $to, $razdel, $incCarn);
            $returns    = $this->countReturns($from, $to, $razdel, $location, $incCarn);

            $leadsTotal = $orders + $calls;
            return [
                'leads' => [
                    'online_orders' => $orders,
                    'phone_calls'   => $calls,
                    'total'         => $leadsTotal,
                ],
                'deals'            => $deals,
                'issuance_events'  => $issuances,
                'sub_deals'        => $subDeals,
                'returns'          => $returns,
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

    public function timeline(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $category = $request->input('category', 'all');
        $location = $request->input('location', 'all');
        $incCarn  = $request->includeCarnival();

        $key = $this->cacheKey('operations.timeline', [
            'from' => $from, 'to' => $to, 'cat' => $category, 'loc' => $location,
            'g'    => $request->input('granularity'),
            'inc'  => $incCarn ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $category, $location, $incCarn, $request) {
            $razdel = $category !== 'all' ? $this->categoryToRazdelId($category) : null;
            if ($category !== 'all' && $razdel === null) {
                return [];
            }

            $periods = [];
            $daSub   = $this->unifiedDealsSubquery();
            $sdSub   = $this->unifiedSubDealsSubquery();
            $itSub   = $this->unifiedItemsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

            // Online orders (rent_orders_arch.cr_time)
            $orderPeriod = $request->granularityFormatFor('o.cr_time');
            $orderJoins  = '';
            $orderWhere  = ['o.cr_time BETWEEN ? AND ?'];
            $orderParams = [$from, $to];
            if ($razdel !== null) {
                $orderJoins = '
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = o.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ';
                $orderWhere[]  = 'rs.id_razdel = ?';
                $orderParams[] = $razdel;
            }
            $orderWhereSql = implode(' AND ', $orderWhere);
            foreach (DB::select("SELECT {$orderPeriod} AS period, COUNT(*) AS cnt FROM rent_orders_arch o {$orderJoins} WHERE {$orderWhereSql} GROUP BY period", $orderParams) as $r) {
                $periods[$r->period]['period']        = $r->period;
                $periods[$r->period]['online_orders'] = (int) $r->cnt;
            }

            // Phone calls
            $callsPeriod = $request->granularityFormatFor('cr_time');
            foreach (DB::select("SELECT {$callsPeriod} AS period, COUNT(*) AS cnt FROM zvonki WHERE cr_time BETWEEN ? AND ? GROUP BY period", [$from, $to]) as $r) {
                $periods[$r->period]['period']      = $r->period;
                $periods[$r->period]['phone_calls'] = (int) $r->cnt;
            }

            // Deals (UNION act + arch) by cr_time. razdel param → JOIN, split params.
            $dealPeriod      = $request->granularityFormatFor('da.cr_time');
            $dealJoins       = '';
            $dealWhere       = ['da.cr_time BETWEEN ? AND ?'];
            $dealJoinParams  = [];
            $dealWhereParams = [$from, $to];
            if ($razdel !== null || !$incCarn) {
                $dealJoins = " LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n ";
                if ($razdel !== null) {
                    $razdelSub        = $this->itemsInRazdelSubquery();
                    $dealJoins       .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                    $dealJoinParams[] = $razdel;
                }
                if (!$incCarn && $carnPh) {
                    $dealWhere[]     = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                    $dealWhereParams = array_merge($dealWhereParams, $carnIds);
                }
            }
            if ($location !== 'all' && is_numeric($location)) {
                $dealWhere[]       = 'da.first_rent_place = ?';
                $dealWhereParams[] = (int) $location;
            }
            $dealWhereSql = implode(' AND ', $dealWhere);
            foreach (DB::select("SELECT {$dealPeriod} AS period, COUNT(DISTINCT da.deal_id) AS cnt FROM {$daSub} da {$dealJoins} WHERE {$dealWhereSql} GROUP BY period", array_merge($dealJoinParams, $dealWhereParams)) as $r) {
                $periods[$r->period]['period'] = $r->period;
                $periods[$r->period]['deals'] = (int) $r->cnt;
            }

            // Sub-deals (UNION act + arch) by cr_time. razdel → JOIN, split params.
            $subPeriod      = $request->granularityFormatFor('sd.cr_time');
            $subJoins       = '';
            $subWhere       = ['sd.cr_time BETWEEN ? AND ?'];
            $subJoinParams  = [];
            $subWhereParams = [$from, $to];
            if ($razdel !== null || !$incCarn) {
                $subJoins = "
                    LEFT JOIN {$daSub} da ON da.deal_id = sd.deal_id
                    LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
                ";
                if ($razdel !== null) {
                    $razdelSub        = $this->itemsInRazdelSubquery();
                    $subJoins        .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                    $subJoinParams[]  = $razdel;
                }
                if (!$incCarn && $carnPh) {
                    $subWhere[]     = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                    $subWhereParams = array_merge($subWhereParams, $carnIds);
                }
            }
            $subWhereSql = implode(' AND ', $subWhere);
            foreach (DB::select("SELECT {$subPeriod} AS period, COUNT(*) AS cnt FROM {$sdSub} sd {$subJoins} WHERE {$subWhereSql} GROUP BY period", array_merge($subJoinParams, $subWhereParams)) as $r) {
                $periods[$r->period]['period']    = $r->period;
                $periods[$r->period]['sub_deals'] = (int) $r->cnt;
            }

            // Returns (UNION act + arch) by return_date. razdel → JOIN, split params.
            $retPeriod      = $request->granularityFormatFor('da.return_date');
            $retJoins       = '';
            $retWhere       = ['da.return_date BETWEEN ? AND ?', 'da.return_date > 0'];
            $retJoinParams  = [];
            $retWhereParams = [$from, $to];
            if ($razdel !== null || !$incCarn) {
                $retJoins = " LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n ";
                if ($razdel !== null) {
                    $razdelSub        = $this->itemsInRazdelSubquery();
                    $retJoins        .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                    $retJoinParams[]  = $razdel;
                }
                if (!$incCarn && $carnPh) {
                    $retWhere[]     = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                    $retWhereParams = array_merge($retWhereParams, $carnIds);
                }
            }
            if ($location !== 'all' && is_numeric($location)) {
                $retWhere[]       = 'da.first_rent_place = ?';
                $retWhereParams[] = (int) $location;
            }
            $retWhereSql = implode(' AND ', $retWhere);
            foreach (DB::select("SELECT {$retPeriod} AS period, COUNT(DISTINCT da.deal_id) AS cnt FROM {$daSub} da {$retJoins} WHERE {$retWhereSql} GROUP BY period", array_merge($retJoinParams, $retWhereParams)) as $r) {
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
     * GET /operations/by-category
     */
    public function byCategory(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();
        $incCarn = $request->includeCarnival();

        $key = $this->cacheKey('operations.by_category', [
            'from' => $from, 'to' => $to, 'inc' => $incCarn ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $incCarn) {
            $daSub   = $this->unifiedDealsSubquery();
            $sdSub   = $this->unifiedSubDealsSubquery();
            $itSub   = $this->unifiedItemsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

            $orders = DB::select("
                SELECT r.id_razdel, r.name_razdel_text, r.url_razdel_name, COUNT(*) AS cnt
                FROM rent_orders_arch o
                JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = o.cat_id
                JOIN razdel_subrazdel rs   ON rs.id_sub_razdel    = sc.id_sub_razdel
                JOIN razdel r              ON r.id_razdel         = rs.id_razdel
                WHERE o.cr_time BETWEEN ? AND ?
                GROUP BY r.id_razdel, r.name_razdel_text, r.url_razdel_name
            ", [$from, $to]);

            // Revenue per razdel: pre-aggregate per item_inv_n in a derived
            // table, then join to a DISTINCT item→razdel mapping. Avoids
            // M:N inflation through subrazdel_category × razdel_subrazdel.
            $perItemWhere  = ['sd.acc_date BETWEEN ? AND ?'];
            $perItemParams = [$from, $to];
            if (!$incCarn && $carnPh) {
                $perItemWhere[]  = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $perItemParams   = array_merge($perItemParams, $carnIds);
            }
            $perItemWhereSql = implode(' AND ', $perItemWhere);

            $deals = DB::select("
                SELECT r.id_razdel,
                       r.name_razdel_text,
                       r.url_razdel_name,
                       SUM(per_item.deals) AS cnt,
                       ROUND(SUM(per_item.rev), 2) AS revenue_byn
                FROM (
                    SELECT da.item_inv_n,
                           SUM(sd.r_paid + sd.delivery_paid) AS rev,
                           COUNT(DISTINCT sd.deal_id) AS deals
                    FROM {$sdSub} sd
                    JOIN {$daSub} da ON da.deal_id = sd.deal_id
                    JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
                    WHERE {$perItemWhereSql}
                    GROUP BY da.item_inv_n
                ) per_item
                JOIN (
                    SELECT DISTINCT ti2.item_inv_n, rs.id_razdel
                    FROM {$itSub} ti2
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = ti2.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel    = sc.id_sub_razdel
                ) irz ON irz.item_inv_n = per_item.item_inv_n
                JOIN razdel r ON r.id_razdel = irz.id_razdel
                GROUP BY r.id_razdel, r.name_razdel_text, r.url_razdel_name
            ", $perItemParams);

            $byRazdel = [];
            foreach ($orders as $o) {
                $byRazdel[$o->id_razdel] = [
                    'razdel_id'     => (int) $o->id_razdel,
                    'name'          => $o->name_razdel_text,
                    'url_slug'      => $o->url_razdel_name,
                    'online_orders' => (int) $o->cnt,
                    'deals'         => 0,
                    'revenue_byn'   => 0.0,
                ];
            }
            foreach ($deals as $d) {
                $existing = $byRazdel[$d->id_razdel] ?? [
                    'razdel_id'     => (int) $d->id_razdel,
                    'name'          => $d->name_razdel_text,
                    'url_slug'      => $d->url_razdel_name,
                    'online_orders' => 0,
                ];
                $existing['deals']       = (int)   $d->cnt;
                $existing['revenue_byn'] = (float) $d->revenue_byn;
                $byRazdel[$d->id_razdel] = $existing;
            }

            usort($byRazdel, fn($a, $b) => $b['deals'] <=> $a['deals']);
            return array_values($byRazdel);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /operations/by-location
     *
     * Per-office revenue is computed from sub-deal.place (legacy parity).
     * Counts are still keyed on first_rent_place because that's the office
     * of first issuance — but additional `revenue_per_office_subdeal_byn`
     * reflects the place where each payment was actually collected.
     */
    public function byLocation(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();
        $incCarn = $request->includeCarnival();

        $key = $this->cacheKey('operations.by_location', [
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
            if (!$incCarn && $carnPh) {
                $where[] = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $params  = array_merge($params, $carnIds);
            }
            $whereSql = implode(' AND ', $where);

            $itJoin = (!$incCarn || $carnPh) ? "
                LEFT JOIN {$daSub} da ON da.deal_id = sd.deal_id
                LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
            " : '';

            // Group by (place, delivery_yn): non-courier office row vs courier row.
            return DB::select("
                SELECT
                    CASE WHEN sd.delivery_yn = '1' THEN 0 ELSE sd.place END AS office_id,
                    CASE WHEN sd.delivery_yn = '1' THEN 'Курьер' ELSE COALESCE(o.name, CONCAT('Place ', sd.place)) END AS office_name,
                    CASE WHEN sd.delivery_yn = '1' THEN 'courier' ELSE COALESCE(o.type, 'office') END AS office_type,
                    o.short_address AS office_address,
                    COUNT(DISTINCT sd.deal_id) AS deals,
                    SUM(CASE WHEN sd.`type` IN ('first_rent','takeaway_plan') THEN 1 ELSE 0 END) AS issuance_events,
                    ROUND(SUM(sd.r_paid + sd.delivery_paid), 2) AS revenue_byn
                FROM {$sdSub} sd
                {$itJoin}
                LEFT JOIN offices o ON o.id = sd.place AND sd.delivery_yn != '1'
                WHERE {$whereSql}
                GROUP BY office_id, office_name, office_type, office_address
                ORDER BY revenue_byn DESC
            ", $params);
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

    private function countDeals(int $from, int $to, ?int $razdel, $location, bool $incCarn): int
    {
        $daSub  = $this->unifiedDealsSubquery();
        $itSub  = $this->unifiedItemsSubquery();
        $carnIds = $this->carnivalCatIds();
        $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

        // razdel param goes into JOIN (itemsInRazdelSubquery), so split params.
        $joinParams  = [];
        $whereParams = [$from, $to];
        $where  = ['da.cr_time BETWEEN ? AND ?'];
        $joins  = '';

        if ($razdel !== null || !$incCarn) {
            $joins = " LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n ";
            if ($razdel !== null) {
                $razdelSub    = $this->itemsInRazdelSubquery();
                $joins       .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                $joinParams[] = $razdel;
            }
            if (!$incCarn && $carnPh) {
                $where[]      = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $whereParams  = array_merge($whereParams, $carnIds);
            }
        }

        if ($location !== 'all' && is_numeric($location)) {
            $where[]       = 'da.first_rent_place = ?';
            $whereParams[] = (int) $location;
        }
        $whereSql = implode(' AND ', $where);
        $row = DB::selectOne(
            "SELECT COUNT(DISTINCT da.deal_id) AS c FROM {$daSub} da {$joins} WHERE {$whereSql}",
            array_merge($joinParams, $whereParams)
        );
        return (int) ($row->c ?? 0);
    }

    /**
     * Issuance events = COUNT(sub_deal) WHERE type IN ('first_rent','takeaway_plan').
     * This is what /bb/reports.php uses for "выдачи" counts. May be slightly
     * higher than countDeals() if a deal has multiple takeaway_plan events
     * (e.g. set rentals where parts are picked up separately).
     */
    private function countIssuanceEvents(int $from, int $to, ?int $razdel, $location, bool $incCarn): int
    {
        $sdSub  = $this->unifiedSubDealsSubquery();
        $daSub  = $this->unifiedDealsSubquery();
        $itSub  = $this->unifiedItemsSubquery();
        $carnIds = $this->carnivalCatIds();
        $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

        // razdel param goes into JOIN (itemsInRazdelSubquery), so split params.
        $joinParams  = [];
        $whereParams = [$from, $to];
        $where  = ['sd.acc_date BETWEEN ? AND ?', "sd.`type` IN ('first_rent','takeaway_plan')"];
        $joins  = '';

        if ($location === 'courier') {
            $where[] = "sd.delivery_yn = '1'";
        } elseif ($location !== 'all' && is_numeric($location)) {
            $where[]       = 'sd.place = ?';
            $where[]       = "sd.delivery_yn != '1'";
            $whereParams[] = (int) $location;
        }

        if ($razdel !== null || !$incCarn) {
            $joins = "
                LEFT JOIN {$daSub} da ON da.deal_id = sd.deal_id
                LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
            ";
            if ($razdel !== null) {
                $razdelSub    = $this->itemsInRazdelSubquery();
                $joins       .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                $joinParams[] = $razdel;
            }
            if (!$incCarn && $carnPh) {
                $where[]     = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $whereParams = array_merge($whereParams, $carnIds);
            }
        }
        $whereSql = implode(' AND ', $where);
        $row = DB::selectOne(
            "SELECT COUNT(*) AS c FROM {$sdSub} sd {$joins} WHERE {$whereSql}",
            array_merge($joinParams, $whereParams)
        );
        return (int) ($row->c ?? 0);
    }

    private function countSubDeals(int $from, int $to, ?int $razdel, bool $incCarn): int
    {
        $sdSub = $this->unifiedSubDealsSubquery();
        $daSub = $this->unifiedDealsSubquery();
        $itSub = $this->unifiedItemsSubquery();
        $carnIds = $this->carnivalCatIds();
        $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

        // razdel param goes into JOIN (itemsInRazdelSubquery), so split params.
        $joinParams  = [];
        $whereParams = [$from, $to];
        $where  = ['sd.cr_time BETWEEN ? AND ?'];
        $joins  = '';
        if ($razdel !== null || !$incCarn) {
            $joins = "
                LEFT JOIN {$daSub} da ON da.deal_id = sd.deal_id
                LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
            ";
            if ($razdel !== null) {
                $razdelSub    = $this->itemsInRazdelSubquery();
                $joins       .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                $joinParams[] = $razdel;
            }
            if (!$incCarn && $carnPh) {
                $where[]     = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $whereParams = array_merge($whereParams, $carnIds);
            }
        }
        $whereSql = implode(' AND ', $where);
        $row = DB::selectOne(
            "SELECT COUNT(*) AS c FROM {$sdSub} sd {$joins} WHERE {$whereSql}",
            array_merge($joinParams, $whereParams)
        );
        return (int) ($row->c ?? 0);
    }

    private function countReturns(int $from, int $to, ?int $razdel, $location, bool $incCarn): int
    {
        $daSub = $this->unifiedDealsSubquery();
        $itSub = $this->unifiedItemsSubquery();
        $carnIds = $this->carnivalCatIds();
        $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

        // razdel param goes into JOIN (itemsInRazdelSubquery), so split params.
        $joinParams  = [];
        $whereParams = [$from, $to];
        $where  = ['da.return_date BETWEEN ? AND ?', 'da.return_date > 0'];
        $joins  = '';

        if ($razdel !== null || !$incCarn) {
            $joins = " LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n ";
            if ($razdel !== null) {
                $razdelSub    = $this->itemsInRazdelSubquery();
                $joins       .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                $joinParams[] = $razdel;
            }
            if (!$incCarn && $carnPh) {
                $where[]     = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $whereParams = array_merge($whereParams, $carnIds);
            }
        }

        if ($location !== 'all' && is_numeric($location)) {
            $where[]       = 'da.first_rent_place = ?';
            $whereParams[] = (int) $location;
        }
        $whereSql = implode(' AND ', $where);
        $row = DB::selectOne(
            "SELECT COUNT(DISTINCT da.deal_id) AS c FROM {$daSub} da {$joins} WHERE {$whereSql}",
            array_merge($joinParams, $whereParams)
        );
        return (int) ($row->c ?? 0);
    }

}
