<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer cohorts, retention, repeat intervals, LTV.
 *
 * Methodology: all deal-based aggregates use UNION(rent_deals_act, rent_deals_arch);
 * carnival can be excluded via include_carnival=false.
 * No PII (full name, phone, passport) is ever returned — only client_id.
 */
class CustomersController extends BaseController
{
    /**
     * GET /clients/ltv?min_deals&min_ltv&limit&offset&include_carnival
     */
    public function ltv(Request $request): JsonResponse
    {
        $request->validate([
            'min_deals'        => 'integer|min:1',
            'min_ltv'          => 'numeric|min:0',
            'limit'            => 'integer|min:1|max:1000',
            'offset'           => 'integer|min:0',
            'include_carnival' => 'nullable',
        ]);

        $minDeals = (int)   $request->get('min_deals', 1);
        $minLtv   = (float) $request->get('min_ltv', 0);
        $limit    = (int)   $request->get('limit', 200);
        $offset   = (int)   $request->get('offset', 0);
        $incRaw   = $request->input('include_carnival');
        $incCarn  = $incRaw === null
            ? true
            : !in_array(strtolower(trim((string) $incRaw)), ['0','false','no','off','n',''], true);

        $daSub   = $this->unifiedDealsSubquery();
        $itSub   = $this->unifiedItemsSubquery();
        $carnIds = $this->carnivalCatIds();
        $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

        $where  = ["da.deal_status NOT IN ('отменена','тест')"];
        $params = [];
        $itJoin = '';
        if (!$incCarn && $carnPh) {
            $itJoin   = " LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n ";
            $where[]  = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
            $params   = array_merge($params, $carnIds);
        }
        $whereSql = implode(' AND ', $where);

        $params[] = $minDeals;
        $params[] = $minLtv;
        $params[] = $limit;
        $params[] = $offset;

        $rows = DB::select("
            SELECT
                da.client_id,
                c.city                                            AS client_city,
                c.status                                          AS client_status,
                FROM_UNIXTIME(c.cr_time, '%Y-%m-%d')              AS client_since,
                COUNT(da.deal_id)                                 AS total_deals,
                ROUND(SUM(da.r_to_pay + da.delivery_to_pay), 2)   AS ltv_byn,
                ROUND(AVG(da.r_to_pay), 2)                        AS avg_deal_byn,
                MIN(FROM_UNIXTIME(da.cr_time, '%Y-%m-%d'))        AS first_deal_date,
                MAX(FROM_UNIXTIME(da.cr_time, '%Y-%m-%d'))        AS last_deal_date,
                DATEDIFF(
                    FROM_UNIXTIME(MAX(da.cr_time)),
                    FROM_UNIXTIME(MIN(da.cr_time))
                ) AS active_days
            FROM {$daSub} da
            LEFT JOIN clients c ON c.client_id = da.client_id
            {$itJoin}
            WHERE {$whereSql}
            GROUP BY da.client_id, c.city, c.status, c.cr_time
            HAVING total_deals >= ? AND ltv_byn >= ?
            ORDER BY ltv_byn DESC
            LIMIT ? OFFSET ?
        ", $params);

        return $this->envelope([
            'min_deals'        => $minDeals,
            'min_ltv'          => $minLtv,
            'limit'            => $limit,
            'offset'           => $offset,
            'include_carnival' => $incCarn,
        ], $rows);
    }

    /**
     * GET /customers/timeline?from&to&granularity&include_carnival
     */
    public function timeline(RangeRequest $request): JsonResponse
    {
        $from    = $request->fromTimestamp();
        $to      = $request->toTimestamp();
        $incCarn = $request->includeCarnival();

        $key = $this->cacheKey('customers.timeline', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
            'inc'  => $incCarn ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $incCarn, $request) {
            $daSub   = $this->unifiedDealsSubquery();
            $itSub   = $this->unifiedItemsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

            $newPeriod         = $request->granularityFormatFor('cr_time');
            $activePeriod      = $request->granularityFormatFor('da.cr_time');
            $activePeriodStart = $request->granularityPeriodStartFor('da.cr_time');

            $newRows = DB::select("
                SELECT {$newPeriod} AS period, COUNT(*) AS cnt
                FROM clients
                WHERE cr_time BETWEEN ? AND ?
                GROUP BY period
            ", [$from, $to]);

            $activeJoins = '';
            $activeWhere = ['da.cr_time BETWEEN ? AND ?'];
            $activeParams = [];
            if (!$incCarn && $carnPh) {
                $activeJoins  = " LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n ";
                $activeWhere[] = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $activeParams  = array_merge($activeParams, $carnIds);
            }
            $activeWhereSql = implode(' AND ', $activeWhere);

            // returning/new_active compare c.cr_time against the START OF EACH BUCKET
            // ({$activePeriodStart}, derived from da.cr_time per row), not the
            // request's global $from — otherwise a client who registered before the
            // bucket but after $from gets misclassified as "new" in that bucket.
            $sql = "
                SELECT {$activePeriod} AS period,
                       COUNT(DISTINCT da.client_id) AS active_cnt,
                       COUNT(DISTINCT CASE WHEN c.cr_time < {$activePeriodStart}  THEN da.client_id END) AS returning_cnt,
                       COUNT(DISTINCT CASE WHEN c.cr_time >= {$activePeriodStart} THEN da.client_id END) AS new_active_cnt
                FROM {$daSub} da
                LEFT JOIN clients c ON c.client_id = da.client_id
                {$activeJoins}
                WHERE {$activeWhereSql}
                GROUP BY period
            ";
            $activeRows = DB::select(
                $sql,
                array_merge([$from, $to], $activeParams)
            );

            $byPeriod = [];
            foreach ($newRows as $r) {
                $byPeriod[$r->period] = [
                    'period'             => $r->period,
                    'new_clients'        => (int) $r->cnt,
                    'active_clients'     => 0,
                    'returning_clients'  => 0,
                    'new_active_clients' => 0,
                ];
            }
            foreach ($activeRows as $r) {
                if (!isset($byPeriod[$r->period])) {
                    $byPeriod[$r->period] = [
                        'period'             => $r->period,
                        'new_clients'        => 0,
                        'active_clients'     => 0,
                        'returning_clients'  => 0,
                        'new_active_clients' => 0,
                    ];
                }
                $byPeriod[$r->period]['active_clients']     = (int) $r->active_cnt;
                $byPeriod[$r->period]['returning_clients']  = (int) $r->returning_cnt;
                $byPeriod[$r->period]['new_active_clients'] = (int) $r->new_active_cnt;
            }

            ksort($byPeriod);
            return array_values($byPeriod);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /customers/cohorts?from&to&include_carnival
     */
    public function cohorts(RangeRequest $request): JsonResponse
    {
        $from    = $request->fromTimestamp();
        $to      = $request->toTimestamp();
        $incCarn = $request->includeCarnival();

        $key = $this->cacheKey('customers.cohorts', [
            'from' => $from, 'to' => $to, 'inc' => $incCarn ? 1 : 0,
        ]);

        $payload = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $incCarn) {
            $daSub   = $this->unifiedDealsSubquery();
            $itSub   = $this->unifiedItemsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

            $sizes = DB::select("
                SELECT DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m') AS cohort,
                       COUNT(*) AS size
                FROM clients
                WHERE cr_time BETWEEN ? AND ?
                GROUP BY cohort
                ORDER BY cohort
            ", [$from, $to]);

            $itJoin = '';
            $where  = ['c.cr_time BETWEEN ? AND ?', 'da.cr_time >= c.cr_time'];
            $params = [$from, $to];
            if (!$incCarn && $carnPh) {
                $itJoin  = " LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n ";
                $where[] = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $params  = array_merge($params, $carnIds);
            }
            $whereSql = implode(' AND ', $where);

            $cells = DB::select("
                SELECT DATE_FORMAT(FROM_UNIXTIME(c.cr_time), '%Y-%m')  AS cohort,
                       DATE_FORMAT(FROM_UNIXTIME(da.cr_time), '%Y-%m') AS observed,
                       COUNT(DISTINCT c.client_id) AS active
                FROM clients c
                JOIN {$daSub} da ON da.client_id = c.client_id
                {$itJoin}
                WHERE {$whereSql}
                GROUP BY cohort, observed
            ", $params);

            $byCohort = [];
            foreach ($sizes as $s) {
                $byCohort[$s->cohort] = [
                    'cohort'    => $s->cohort,
                    'size'      => (int) $s->size,
                    'retention' => [],
                ];
            }
            foreach ($cells as $c) {
                if (!isset($byCohort[$c->cohort])) continue;
                $size = $byCohort[$c->cohort]['size'];
                $byCohort[$c->cohort]['retention'][] = [
                    'period' => $c->observed,
                    'active' => (int) $c->active,
                    'rate'   => $size > 0 ? round($c->active / $size, 4) : null,
                ];
            }
            foreach ($byCohort as &$entry) {
                usort($entry['retention'], fn($a, $b) => strcmp($a['period'], $b['period']));
            }
            unset($entry);

            return array_values($byCohort);
        });

        return $this->envelope($request->queryEcho(), $payload);
    }

    /**
     * GET /customers/repeat-intervals?from&to&category&include_carnival
     */
    public function repeatIntervals(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $categories = $request->categories();
        $catStr     = implode(',', $categories);
        $incCarn  = $request->includeCarnival();

        $key = $this->cacheKey('customers.repeat_intervals', [
            'from' => $from, 'to' => $to, 'cat' => $catStr, 'inc' => $incCarn ? 1 : 0,
        ]);

        $payload = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $categories, $incCarn) {
            $razdelIds = $this->categoryToRazdelIds($categories);
            if (!in_array('all', $categories, true) && empty($razdelIds)) {
                return null;
            }

            $daSub   = $this->unifiedDealsSubquery();
            $itSub   = $this->unifiedItemsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

            // razdel → JOIN via itemsInRazdelSubquery (M:N would inflate deal
            // rows and pollute the median/p25/p75 interval distribution).
            $joinParams  = [];
            $whereParams = [$from, $to];
            $joins  = '';
            $where  = ['da.cr_time BETWEEN ? AND ?'];

            if (!empty($razdelIds) || !$incCarn) {
                $joins = " LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n ";
                if (!empty($razdelIds)) {
                    $razdelSub    = $this->itemsInRazdelSubquery($razdelIds);
                    $joins       .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                    $joinParams   = array_merge($joinParams, $razdelIds);
                }
                if (!$incCarn && $carnPh) {
                    $where[]     = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                    $whereParams = array_merge($whereParams, $carnIds);
                }
            }
            $whereSql = implode(' AND ', $where);

            $rows = DB::select(
                "SELECT da.client_id, da.cr_time
                 FROM {$daSub} da
                 {$joins}
                 WHERE {$whereSql}
                 ORDER BY da.client_id, da.cr_time",
                array_merge($joinParams, $whereParams)
            );

            $values     = [];
            $lastClient = null;
            $lastTime   = null;
            foreach ($rows as $r) {
                $client = (string) $r->client_id;
                $time   = (int) $r->cr_time;
                if ($client === $lastClient && $lastTime !== null) {
                    $values[] = ($time - $lastTime) / 86400.0;
                }
                $lastClient = $client;
                $lastTime   = $time;
            }
            sort($values);
            $count = count($values);

            if ($count === 0) {
                return [
                    'count' => 0,
                    'mean_days' => null, 'min_days' => null, 'max_days' => null,
                    'p25_days'  => null, 'median_days' => null, 'p75_days' => null,
                    'histogram' => [],
                ];
            }

            $sum = array_sum($values);
            $p   = function (float $q) use ($values, $count) {
                $idx = (int) max(0, min($count - 1, floor($q * ($count - 1))));
                return round($values[$idx], 2);
            };

            $buckets = [
                ['label' => '0-7d',     'lo' => 0,   'hi' => 7,    'count' => 0],
                ['label' => '7-30d',    'lo' => 7,   'hi' => 30,   'count' => 0],
                ['label' => '30-90d',   'lo' => 30,  'hi' => 90,   'count' => 0],
                ['label' => '90-180d',  'lo' => 90,  'hi' => 180,  'count' => 0],
                ['label' => '180-365d', 'lo' => 180, 'hi' => 365,  'count' => 0],
                ['label' => '365+d',    'lo' => 365, 'hi' => null, 'count' => 0],
            ];
            foreach ($values as $d) {
                foreach ($buckets as &$b) {
                    if ($d >= $b['lo'] && ($b['hi'] === null || $d < $b['hi'])) {
                        $b['count']++;
                        break;
                    }
                }
                unset($b);
            }

            return [
                'count'       => $count,
                'mean_days'   => round($sum / $count, 2),
                'min_days'    => round(min($values), 2),
                'max_days'    => round(max($values), 2),
                'p25_days'    => $p(0.25),
                'median_days' => $p(0.50),
                'p75_days'    => $p(0.75),
                'histogram'   => $buckets,
            ];
        });

        return $this->envelope($request->queryEcho(), $payload ?? []);
    }
}
