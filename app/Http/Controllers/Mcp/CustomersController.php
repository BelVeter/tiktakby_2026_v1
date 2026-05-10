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

    /**
     * GET /customers/timeline?from&to&granularity
     *
     * Per-period customer counts:
     *   - new_clients:        clients.cr_time within period
     *   - active_clients:     distinct rent_deals_arch.client_id with deal in period
     *   - returning_clients:  active_clients whose first deal predates the period
     *   - new_active_clients: active_clients whose first deal is in the period
     */
    public function timeline(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();

        $key = $this->cacheKey('customers.timeline', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $request) {
            $newPeriod    = $request->granularityFormatFor('cr_time');
            $activePeriod = $request->granularityFormatFor('da.cr_time');

            $newRows = DB::select("
                SELECT {$newPeriod} AS period, COUNT(*) AS cnt
                FROM clients
                WHERE cr_time BETWEEN ? AND ?
                GROUP BY period
            ", [$from, $to]);

            $activeRows = DB::select("
                SELECT {$activePeriod} AS period,
                       COUNT(DISTINCT da.client_id) AS active_cnt,
                       SUM(CASE WHEN c.cr_time < ? THEN 0 ELSE 0 END) AS dummy,
                       COUNT(DISTINCT CASE WHEN c.cr_time < ? THEN da.client_id END)  AS returning_cnt,
                       COUNT(DISTINCT CASE WHEN c.cr_time >= ? THEN da.client_id END) AS new_active_cnt
                FROM rent_deals_arch da
                LEFT JOIN clients c ON c.client_id = da.client_id
                WHERE da.cr_time BETWEEN ? AND ?
                GROUP BY period
            ", [$from, $from, $from, $from, $to]);

            $byPeriod = [];
            foreach ($newRows as $r) {
                $byPeriod[$r->period] = [
                    'period'              => $r->period,
                    'new_clients'         => (int) $r->cnt,
                    'active_clients'      => 0,
                    'returning_clients'   => 0,
                    'new_active_clients'  => 0,
                ];
            }
            foreach ($activeRows as $r) {
                if (!isset($byPeriod[$r->period])) {
                    $byPeriod[$r->period] = [
                        'period'              => $r->period,
                        'new_clients'         => 0,
                        'active_clients'      => 0,
                        'returning_clients'   => 0,
                        'new_active_clients'  => 0,
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
     * GET /customers/cohorts?from&to
     *
     * Monthly cohort × observed retention matrix. Cohort key = month of
     * clients.cr_time. For each (cohort, observed) cell we report the count
     * of clients from that cohort who had at least one deal in observed
     * month, plus the % relative to cohort size.
     *
     * The endpoint always uses month granularity regardless of the request's
     * `granularity` field — cohort matrices below month grain are noisy.
     */
    public function cohorts(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();

        $key = $this->cacheKey('customers.cohorts', ['from' => $from, 'to' => $to]);

        $payload = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to) {
            $sizes = DB::select("
                SELECT DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m') AS cohort,
                       COUNT(*) AS size
                FROM clients
                WHERE cr_time BETWEEN ? AND ?
                GROUP BY cohort
                ORDER BY cohort
            ", [$from, $to]);

            $cells = DB::select("
                SELECT DATE_FORMAT(FROM_UNIXTIME(c.cr_time), '%Y-%m')  AS cohort,
                       DATE_FORMAT(FROM_UNIXTIME(da.cr_time), '%Y-%m') AS observed,
                       COUNT(DISTINCT c.client_id) AS active
                FROM clients c
                JOIN rent_deals_arch da ON da.client_id = c.client_id
                WHERE c.cr_time BETWEEN ? AND ?
                  AND da.cr_time >= c.cr_time
                GROUP BY cohort, observed
            ", [$from, $to]);

            $byCohort = [];
            foreach ($sizes as $s) {
                $byCohort[$s->cohort] = [
                    'cohort'    => $s->cohort,
                    'size'      => (int) $s->size,
                    'retention' => [],
                ];
            }
            foreach ($cells as $c) {
                if (!isset($byCohort[$c->cohort])) {
                    continue;
                }
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
     * GET /customers/repeat-intervals?from&to&category
     *
     * Distribution of intervals (in days) between consecutive deals of the
     * same client. We surface:
     *   - count of intervals
     *   - mean / min / max
     *   - p25 / p50 (median) / p75 via offset-based selection
     *   - histogram buckets: 0–7 / 7–30 / 30–90 / 90–180 / 180–365 / 365+ days
     *
     * Useful for sizing reminder windows and detecting churn.
     *
     * Intervals are computed in PHP rather than via SQL window functions —
     * production MariaDB rejected the `WITH ordered AS (... LAG(...) OVER ...)`
     * form with a 500, so we keep the SQL portable (plain ORDER BY) and do a
     * single linear scan in PHP.
     */
    public function repeatIntervals(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $category = $request->input('category', 'all');

        $key = $this->cacheKey('customers.repeat_intervals', [
            'from' => $from, 'to' => $to, 'cat' => $category,
        ]);

        $payload = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $category) {
            $razdel = $category !== 'all' ? $this->categoryToRazdelId($category) : null;
            if ($category !== 'all' && $razdel === null) {
                return null;
            }

            $catJoin   = '';
            $catWhere  = '';
            $catParams = [];
            if ($razdel !== null) {
                $catJoin = "
                    JOIN tovar_rent_items tri  ON tri.item_inv_n = da.item_inv_n
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ";
                $catWhere    = 'AND rs.id_razdel = ?';
                $catParams[] = $razdel;
            }

            $rows = DB::select(
                "SELECT da.client_id, da.cr_time
                 FROM rent_deals_arch da
                 {$catJoin}
                 WHERE da.cr_time BETWEEN ? AND ?
                   {$catWhere}
                 ORDER BY da.client_id, da.cr_time",
                array_merge([$from, $to], $catParams)
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
}
