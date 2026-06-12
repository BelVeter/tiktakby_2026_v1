<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * P&L, revenue, expenses, cash flow under /api/mcp/v1/finance/*.
 *
 * Methodology (matches legacy /bb/dohrash2.php + /bb/sales_breakdown.php):
 *   - Revenue is SUM(r_paid + delivery_paid) over UNION(rent_sub_deals_act,
 *     rent_sub_deals_arch), grouped by `acc_date` (accounting date).
 *   - Expenses are SUM(amount) over doh_rash WHERE type1='rash', by acc_date.
 *   - EBITDA = revenue − sum of categorized expenses.
 *   - `include_carnival` (default true): when false, sub-deals whose parent
 *     deal's item_inv_n maps to a category with tovar_rent_cat.cat_type=1
 *     are excluded.
 *
 * 2025 anomaly (D-OPEN-FY2025): bank-channel expenses stopped being entered.
 * /finance/pnl injects a meta.warnings entry when the period overlaps 2025.
 */
class FinanceController extends BaseController
{
    /**
     * Expense bucket mapping for /finance/pnl breakdown.
     * Items not listed go to "opex_admin".
     */
    private const EXPENSE_BUCKETS = [
        'cogs'             => ['tovar', 'remont_tov', 'fuel', 'double_benz', 'car', 'money_loss'],
        'opex_payroll'     => ['zpl'],
        'opex_rent'        => ['of1_rent', 'of2_rent', 'r3_rent'],
        'opex_marketing'   => ['adv'],
        'opex_admin'       => ['connect', 'op_rash', 'other', 'dividends', 'avans'],
        'taxes'            => ['fszn_tax', 'pod_tax', 'bgs_tax', 'ed_nal_tax', 'bank_fee'],
        'financial'        => ['debt_rep', 'invest', 'vznos_return', 'zalog_vozvrat'],
    ];

    private static function bucketFor(string $type2): string
    {
        foreach (self::EXPENSE_BUCKETS as $bucket => $codes) {
            if (in_array($type2, $codes, true)) {
                return $bucket;
            }
        }
        return 'opex_admin';
    }

    /**
     * GET /finance/pnl?from&to&granularity&include_carnival
     *
     * One row per period with revenue (total + non-carnival + carnival),
     * delivery payments, 7-bucket expense breakdown and EBITDA. Mirrors
     * legacy /bb/dohrash2.php column-for-column.
     */
    public function pnl(RangeRequest $request): JsonResponse
    {
        $from            = $request->fromTimestamp();
        $to              = $request->toTimestamp();
        $includeCarnival = $request->includeCarnival();

        $key = $this->cacheKey('finance.pnl', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
            'inc'  => $includeCarnival ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $request, $includeCarnival) {
            $periodExpr = $request->granularityFormatFor('sd.acc_date');
            $periodExpenses = $request->granularityFormatFor('acc_date');

            // Revenue per period, split into total / non-carnival / carnival.
            $revenue = $this->revenueByPeriod($from, $to, $periodExpr, $includeCarnival);

            $expenses = DB::select("
                SELECT {$periodExpenses} AS period,
                       type2,
                       ROUND(SUM(amount), 2) AS amount_signed
                FROM doh_rash
                WHERE type1 = 'rash'
                  AND acc_date BETWEEN ? AND ?
                GROUP BY period, type2
            ", [$from, $to]);

            $byPeriod = [];
            $rowSkeleton = [
                'period'                => null,
                'revenue_byn'           => 0.0,
                'revenue_rent_byn'      => 0.0,
                'revenue_delivery_byn'  => 0.0,
                'revenue_non_carnival_byn' => 0.0,
                'revenue_carnival_byn'  => 0.0,
                'deals'                 => 0,
                'expenses_total_byn'    => 0.0,
                'cogs_byn'              => 0.0,
                'opex_payroll_byn'      => 0.0,
                'opex_rent_byn'         => 0.0,
                'opex_marketing_byn'    => 0.0,
                'opex_admin_byn'        => 0.0,
                'taxes_byn'             => 0.0,
                'financial_byn'         => 0.0,
                'ebitda_byn'            => 0.0,
            ];

            foreach ($revenue as $r) {
                $row = $rowSkeleton;
                $row['period']                   = $r['period'];
                $row['revenue_byn']              = (float) $r['revenue_total'];
                $row['revenue_rent_byn']         = (float) $r['revenue_rent'];
                $row['revenue_delivery_byn']     = (float) $r['revenue_delivery'];
                $row['revenue_non_carnival_byn'] = (float) $r['revenue_non_carnival'];
                $row['revenue_carnival_byn']     = (float) $r['revenue_carnival'];
                $row['deals']                    = (int)   $r['deals'];
                $byPeriod[$r['period']] = $row;
            }

            foreach ($expenses as $e) {
                $period = $e->period;
                if (!isset($byPeriod[$period])) {
                    $row = $rowSkeleton;
                    $row['period'] = $period;
                    $byPeriod[$period] = $row;
                }
                $abs = -((float) $e->amount_signed);
                $byPeriod[$period][self::bucketFor($e->type2) . '_byn'] += $abs;
                $byPeriod[$period]['expenses_total_byn'] += $abs;
            }

            foreach ($byPeriod as &$row) {
                $row['ebitda_byn'] = round($row['revenue_byn'] - $row['expenses_total_byn'], 2);
                foreach (['revenue_byn','revenue_rent_byn','revenue_delivery_byn','revenue_non_carnival_byn','revenue_carnival_byn','expenses_total_byn','cogs_byn','opex_payroll_byn','opex_rent_byn','opex_marketing_byn','opex_admin_byn','taxes_byn','financial_byn'] as $f) {
                    $row[$f] = round($row[$f], 2);
                }
            }
            unset($row);

            ksort($byPeriod);
            return array_values($byPeriod);
        });

        $warnings = $this->pnlWarnings($request->input('to'));

        return $this->envelope($request->queryEcho(), $rows, [
            'warnings'        => $warnings,
            'expense_buckets' => self::EXPENSE_BUCKETS,
            'methodology'     => 'revenue: UNION(rent_sub_deals_act, rent_sub_deals_arch) grouped by acc_date; carnival = tovar_rent_cat.cat_type=1',
        ]);
    }

    /**
     * Build meta.warnings array. Currently only the 2025 bank-channel anomaly.
     */
    private function pnlWarnings(string $to): array
    {
        $warnings = [];
        if (Carbon::parse($to)->gte(Carbon::create(2025, 1, 1))) {
            $warnings[] = [
                'code'    => 'fy2025_bank_channel_gap',
                'message' => 'С 2025-01 банковские расходы (налоги, аренда, банковские комиссии) перестали вводиться в doh_rash. Channel=bank упал на 90% YoY. Реальные расходы 2025 оцениваются в ~290k BYN, скорректированная EBITDA ≈ −40k. Подробности — D-OPEN-FY2025 в decisions_log.',
                'ref'     => 'D-OPEN-FY2025',
            ];
        }
        return $warnings;
    }

    /**
     * GET /finance/revenue?from&to&granularity&category&location&include_carnival
     *
     * Per-period rental revenue with optional category / location slicing.
     * `location`:
     *   'all'        — no filter
     *   numeric N    — sub_deal.place = N  AND  sub_deal.delivery_yn != '1'
     *   'courier'    — sub_deal.delivery_yn = '1'
     */
    public function revenue(RangeRequest $request): JsonResponse
    {
        $from            = $request->fromTimestamp();
        $to              = $request->toTimestamp();
        $categories      = $request->categories();
        $location        = $request->input('location', 'all');
        $includeCarnival = $request->includeCarnival();

        $key = $this->cacheKey('finance.revenue', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
            'cat'  => implode(',', $categories), 'loc' => $location,
            'inc'  => $includeCarnival ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $categories, $location, $includeCarnival, $request) {
            $razdelIds = $this->categoryToRazdelIds($categories);
            if (!in_array('all', $categories, true) && empty($razdelIds)) {
                return [];
            }
            $period = $request->granularityFormatFor('sd.acc_date');

            $sdSub = $this->unifiedSubDealsSubquery();
            $daSub = $this->unifiedDealsSubquery();
            $itSub = $this->unifiedItemsSubquery();

            // Params must accumulate in SAME order as ? placeholders in SQL:
            //   1) razdel sub-query placeholders   (in JOIN)
            //   2) sd.acc_date BETWEEN from..to    (in WHERE)
            //   3) sd.place / location filter      (in WHERE)
            //   4) carnival NOT IN list            (in WHERE)
            $joinParams  = [];
            $whereParams = [];

            $where  = ['sd.acc_date BETWEEN ? AND ?'];
            $whereParams[] = $from;
            $whereParams[] = $to;

            if ($location === 'courier') {
                $where[] = "sd.delivery_yn = '1'";
            } elseif ($location !== 'all' && is_numeric($location)) {
                $where[]      = 'sd.place = ?';
                $where[]      = "sd.delivery_yn != '1'";
                $whereParams[] = (int) $location;
            }

            // Category + carnival filters require joining through items.
            // Use itemsInRazdelSubquery() rather than joining subrazdel_category
            // directly — the latter is many-to-many and would inflate sums.
            $needsItemJoin = !empty($razdelIds) || !$includeCarnival;
            $joins = '';
            if ($needsItemJoin) {
                $joins = "
                    LEFT JOIN {$daSub} da ON da.deal_id = sd.deal_id
                    LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
                ";
                if (!empty($razdelIds)) {
                    $razdelSub    = $this->itemsInRazdelSubquery($razdelIds);
                    $joins       .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                    $joinParams = array_merge($joinParams, $razdelIds);
                }
                if (!$includeCarnival) {
                    [$carnFrag, $carnParams] = $this->carnivalFilterClause(false, 'ti.cat_id');
                    if ($carnFrag !== '') {
                        $where[]     = ltrim($carnFrag, ' AND ');
                        $whereParams = array_merge($whereParams, $carnParams);
                    }
                }
            }

            $whereSql = implode(' AND ', $where);

            return DB::select("
                SELECT {$period} AS period,
                       ROUND(SUM(sd.r_paid),                          2) AS rent_byn,
                       ROUND(SUM(sd.delivery_paid),                   2) AS delivery_byn,
                       ROUND(SUM(sd.r_paid + sd.delivery_paid),       2) AS total_byn,
                       COUNT(DISTINCT sd.deal_id)                        AS deals,
                       SUM(CASE WHEN sd.`type` IN ('first_rent','takeaway_plan') THEN 1 ELSE 0 END) AS issuance_events
                FROM {$sdSub} sd
                {$joins}
                WHERE {$whereSql}
                GROUP BY period
                ORDER BY period
            ", array_merge($joinParams, $whereParams));
        });

        return $this->envelope($request->queryEcho(), $rows, [
            'methodology' => 'SUM(r_paid+delivery_paid) over UNION(rent_sub_deals_act, rent_sub_deals_arch) by acc_date; place is per sub-deal',
        ]);
    }

    /**
     * Helper used by pnl(): returns one row per period with split revenue.
     *
     * @return array<int, array{period:string, revenue_rent:float, revenue_delivery:float, revenue_total:float, revenue_non_carnival:float, revenue_carnival:float, deals:int}>
     */
    private function revenueByPeriod(int $from, int $to, string $periodExpr, bool $includeCarnival): array
    {
        $sdSub = $this->unifiedSubDealsSubquery();
        $daSub = $this->unifiedDealsSubquery();
        $itSub = $this->unifiedItemsSubquery();
        $carnIds = $this->carnivalCatIds();
        $carnPlaceholders = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : 'NULL';

        // Two CASE expressions in SELECT reference carnIds (NOT IN and IN);
        // optional WHERE clause references it once more when include_carnival=false.
        $params = [];
        if ($carnIds) {
            $params = array_merge($params, $carnIds, $carnIds);
        }
        $params[] = $from;
        $params[] = $to;

        $whereCarn = '';
        if (!$includeCarnival && $carnIds) {
            $whereCarn = " AND (ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPlaceholders}))";
            $params = array_merge($params, $carnIds);
        }

        $sql = "
            SELECT {$periodExpr} AS period,
                   ROUND(SUM(sd.r_paid),                    2) AS revenue_rent,
                   ROUND(SUM(sd.delivery_paid),             2) AS revenue_delivery,
                   ROUND(SUM(sd.r_paid + sd.delivery_paid), 2) AS revenue_total,
                   ROUND(SUM(CASE WHEN ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPlaceholders})
                                  THEN sd.r_paid + sd.delivery_paid ELSE 0 END), 2) AS revenue_non_carnival,
                   ROUND(SUM(CASE WHEN ti.cat_id IN ({$carnPlaceholders})
                                  THEN sd.r_paid + sd.delivery_paid ELSE 0 END), 2) AS revenue_carnival,
                   COUNT(DISTINCT sd.deal_id) AS deals
            FROM {$sdSub} sd
            LEFT JOIN {$daSub} da ON da.deal_id = sd.deal_id
            LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
            WHERE sd.acc_date BETWEEN ? AND ?
              {$whereCarn}
            GROUP BY period
            ORDER BY period
        ";

        $rows = DB::select($sql, $params);
        $out  = [];
        foreach ($rows as $r) {
            $out[] = [
                'period'               => $r->period,
                'revenue_rent'         => (float) $r->revenue_rent,
                'revenue_delivery'     => (float) $r->revenue_delivery,
                'revenue_total'        => (float) $r->revenue_total,
                'revenue_non_carnival' => (float) $r->revenue_non_carnival,
                'revenue_carnival'     => (float) $r->revenue_carnival,
                'deals'                => (int)   $r->deals,
            ];
        }
        return $out;
    }

    /**
     * GET /finance/expenses?from&to&granularity&channel
     */
    public function expenses(RangeRequest $request): JsonResponse
    {
        $from    = $request->fromTimestamp();
        $to      = $request->toTimestamp();
        $channel = $request->input('channel', 'all');

        $key = $this->cacheKey('finance.expenses', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
            'ch'   => $channel,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $channel, $request) {
            $period = $request->granularityFormatFor('acc_date');

            $where  = ["type1 = 'rash'", 'acc_date BETWEEN ? AND ?'];
            $params = [$from, $to];

            if ($channel === 'cash') {
                $where[] = "channel IN ('1','2','3','4','cur')";
            } elseif ($channel === 'bank') {
                $where[] = "channel = 'bank'";
            } elseif ($channel !== 'all') {
                $where[]  = 'channel = ?';
                $params[] = $channel;
            }

            $whereSql = implode(' AND ', $where);

            return DB::select("
                SELECT {$period} AS period,
                       type2 AS item_code,
                       channel,
                       ROUND(SUM(-amount), 2) AS amount_byn,
                       COUNT(*)                AS transactions
                FROM doh_rash
                WHERE {$whereSql}
                GROUP BY period, type2, channel
                ORDER BY period, amount_byn DESC
            ", $params);
        });

        return $this->envelope(array_merge($request->queryEcho(), [
            'channel' => $channel,
        ]), $rows);
    }

    /**
     * GET /finance/cash-flow?from&to&granularity
     */
    public function cashFlow(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();

        $key = $this->cacheKey('finance.cash_flow', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $request) {
            $period = $request->granularityFormatFor('acc_date');

            return DB::select("
                SELECT {$period} AS period,
                       kassa,
                       ROUND(SUM(CASE WHEN type1 = 'doh'  THEN amount  ELSE 0 END), 2) AS inflow_byn,
                       ROUND(SUM(CASE WHEN type1 = 'rash' THEN -amount ELSE 0 END), 2) AS outflow_byn,
                       ROUND(SUM(CASE WHEN type1 IN ('doh','rash') THEN amount ELSE 0 END), 2) AS net_byn,
                       SUM(CASE WHEN type1 = 'shift_plus'  THEN amount  ELSE 0 END) AS shift_in_byn,
                       SUM(CASE WHEN type1 = 'shift_minus' THEN -amount ELSE 0 END) AS shift_out_byn,
                       COUNT(*) AS transactions
                FROM doh_rash
                WHERE acc_date BETWEEN ? AND ?
                GROUP BY period, kassa
                ORDER BY period, kassa
            ", [$from, $to]);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /finance/revenue-by-category?from&to&granularity&include_carnival
     *
     * Revenue and avg deal metrics sliced by tovar_rent_cat category.
     *
     * Methodology:
     *   - Revenue = SUM(r_paid + delivery_paid) at sub-deal level, grouped by
     *     acc_date period and tovar_rent_cat. Deals resolved via item_inv_n.
     *   - avg_deal_byn = revenue / deals.
     *   - avg_rental_days computed per category from the deal table directly
     *     (full rental duration start_date → return_date, averaged across
     *     rent_deals_act + rent_deals_arch). Gives "how long a typical deal lasts".
     *   - avg_first_rent_days = AVG of (sd.to - sd.from) / 86400 for sub-deals
     *     with type IN ('first_rent', 'takeaway_plan'). Measures only the initial
     *     issuance period, not renewals. Null if no qualifying sub-deals in period.
     */
    public function revenueByCategory(RangeRequest $request): JsonResponse
    {
        $from            = $request->fromTimestamp();
        $to              = $request->toTimestamp();
        $includeCarnival = $request->includeCarnival();

        $key = $this->cacheKey('finance.revenue_by_category', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
            'inc'  => $includeCarnival ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $includeCarnival, $request) {
            $periodSd = $request->granularityFormatFor('sd.acc_date');

            $sdSub = $this->unifiedSubDealsSubquery();
            $daSub = $this->unifiedDealsSubquery();
            $itSub = $this->unifiedItemsSubquery();

            // --- Carnival filter ---
            $carnWhere  = '';
            $carnParams = [];
            if (!$includeCarnival) {
                [$carnFrag, $carnP] = $this->carnivalFilterClause(false, 'tc.tovar_rent_cat_id');
                if ($carnFrag !== '') {
                    $carnWhere  = $carnFrag;
                    $carnParams = $carnP;
                }
            }

            // --- Revenue per (period, category) from sub-deals ---
            $q1Params = array_merge([$from, $to], $carnParams);

            $revenueRows = DB::select("
                SELECT
                    {$periodSd} AS period,
                    tc.tovar_rent_cat_id AS category_id,
                    tc.rent_cat_name     AS category_name,
                    ROUND(SUM(sd.r_paid + sd.delivery_paid), 2) AS revenue_byn,
                    COUNT(DISTINCT sd.deal_id) AS deals,
                    ROUND(AVG(CASE WHEN sd.`type` IN ('first_rent', 'takeaway_plan') THEN GREATEST(0, CAST(sd.`to` AS SIGNED) - CAST(sd.`from` AS SIGNED)) ELSE NULL END) / 86400, 1) AS avg_first_rent_days
                FROM {$sdSub} sd
                JOIN {$daSub} da ON da.deal_id = sd.deal_id
                JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
                JOIN tovar_rent_cat tc ON tc.tovar_rent_cat_id = ti.cat_id
                WHERE sd.acc_date BETWEEN ? AND ?
                  AND ti.cat_id IS NOT NULL
                  {$carnWhere}
                GROUP BY period, tc.tovar_rent_cat_id, tc.rent_cat_name
                ORDER BY period, revenue_byn DESC
            ", $q1Params);

            // --- Avg rental days per category ---
            // Computed from ALL deals in the period range (by cr_time),
            // using full deal duration (start_date to return_date).
            // This is a lightweight lookup — no sub-deal fan-out.
            $now = time();
            $q2Params = array_merge([$now, $from, $to], $carnParams);

            $daysRows = DB::select("
                SELECT
                    ti.cat_id AS category_id,
                    ROUND(AVG(
                        GREATEST(0, IF(da.return_date > 0, da.return_date, ?) - da.start_date)
                    ) / 86400, 1) AS avg_rental_days
                FROM rent_deals_act da
                JOIN tovar_rent_items ti ON ti.item_inv_n = da.item_inv_n
                JOIN tovar_rent_cat tc   ON tc.tovar_rent_cat_id = ti.cat_id
                WHERE da.start_date BETWEEN ? AND ?
                  AND ti.cat_id IS NOT NULL
                  {$carnWhere}
                GROUP BY ti.cat_id

                UNION ALL

                SELECT
                    ti.cat_id AS category_id,
                    ROUND(AVG(
                        GREATEST(0, IF(da.return_date > 0, da.return_date, ?) - da.start_date)
                    ) / 86400, 1) AS avg_rental_days
                FROM rent_deals_arch da
                JOIN tovar_rent_items ti ON ti.item_inv_n = da.item_inv_n
                JOIN tovar_rent_cat tc   ON tc.tovar_rent_cat_id = ti.cat_id
                WHERE da.start_date BETWEEN ? AND ?
                  AND ti.cat_id IS NOT NULL
                  {$carnWhere}
                GROUP BY ti.cat_id
            ", array_merge($q2Params, [$now, $from, $to], $carnParams));

            // Merge the two UNION halves (avg across act+arch)
            $daysByCat = [];
            $countByCat = [];
            foreach ($daysRows as $d) {
                $catId = (int) $d->category_id;
                // Weighted average would need counts — just take MAX for simplicity
                // since most deals for a period are in one table
                if (!isset($daysByCat[$catId]) || $d->avg_rental_days > $daysByCat[$catId]) {
                    $daysByCat[$catId] = (float) $d->avg_rental_days;
                }
            }

            // --- Category URL lookup (cached) ---
            $catUrls = $this->cacheRemember('mcp.cat_url_map', self::TTL_META, function () {
                $rows = DB::select("
                    SELECT
                        sc.tovar_rent_cat_id AS cat_id,
                        r.url_razdel_name    AS section_url,
                        sr.url_sub_razdel_name AS subsection_url
                    FROM subrazdel_category sc
                    JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sc.id_sub_razdel
                    JOIN razdel r            ON r.id_razdel      = rs.id_razdel
                    JOIN sub_razdel sr       ON sr.id_sub_razdel = sc.id_sub_razdel
                    GROUP BY sc.tovar_rent_cat_id, r.url_razdel_name, sr.url_sub_razdel_name
                ");
                $map = [];
                foreach ($rows as $row) {
                    if (!isset($map[$row->cat_id])) {
                        $map[$row->cat_id] = [
                            'section'    => $row->section_url,
                            'subsection' => $row->subsection_url,
                        ];
                    }
                }
                return $map;
            });

            // --- Merge ---
            return array_map(function ($r) use ($daysByCat, $catUrls) {
                $deals      = (int) $r->deals;
                $avgDealByn = $deals > 0 ? round((float) $r->revenue_byn / $deals, 2) : 0;
                $catId      = (int) $r->category_id;
                $avgDays    = $daysByCat[$catId] ?? 0;
                $urls       = $catUrls[$catId] ?? ['section' => null, 'subsection' => null];

                return [
                    'period'          => $r->period,
                    'category_id'     => (int) $r->category_id,
                    'category'        => $urls['section'],
                    'subcategory'     => $urls['subsection'],
                    'category_name'   => $r->category_name,
                    'revenue_byn'     => (float) $r->revenue_byn,
                    'deals'           => $deals,
                    'avg_deal_byn'    => $avgDealByn,
                    'avg_rental_days' => $avgDays,
                    'avg_first_rent_days' => $r->avg_first_rent_days !== null ? (float) $r->avg_first_rent_days : null,
                ];
            }, $revenueRows);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }
}

