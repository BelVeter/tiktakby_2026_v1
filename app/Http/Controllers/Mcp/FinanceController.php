<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * P&L, revenue, expenses, cash flow under /api/mcp/v1/finance/*.
 *
 * Numbers tie out to /home/dmitry/Documents/прокат/04_analytics/data/
 * annual_pnl_summary.csv:
 *   - revenue = SUM(rent_deals_arch.r_paid + delivery_paid) by cr_time
 *   - expenses = SUM(doh_rash.amount) WHERE type1='rash' by acc_date
 *   - EBITDA = revenue + sum(rash.amount)   ; amount is negative for outflows
 *
 * Mapping doh_rash.type2 → expense bucket lives in EXPENSE_BUCKETS below.
 *
 * 2025 anomaly (D-OPEN-FY2025): bank-channel expenses stopped being entered.
 * /finance/pnl injects a meta.warnings entry when the period overlaps 2025.
 */
class FinanceController extends BaseController
{
    /**
     * Expense bucket mapping for /finance/pnl breakdown.
     * Items not listed go to "other".
     */
    private const EXPENSE_BUCKETS = [
        // Cost of goods sold — repairs, fuel, transport, write-offs.
        'cogs' => ['tovar', 'remont_tov', 'fuel', 'double_benz', 'car', 'money_loss'],
        // Salaries & contractor pay.
        'opex_payroll' => ['zpl'],
        // Office rent (Mashera/Lozhinskaya/Pobediteley historical codes).
        'opex_rent' => ['of1_rent', 'of2_rent', 'r3_rent'],
        // Advertising & promotion.
        'opex_marketing' => ['adv'],
        // Communications, misc operational, depreciation booked under "dividends".
        'opex_admin' => ['connect', 'op_rash', 'other', 'dividends', 'avans'],
        // Taxes & bank fees.
        'taxes' => ['fszn_tax', 'pod_tax', 'bgs_tax', 'ed_nal_tax', 'bank_fee'],
        // Capital movements — debt repayment, founder withdrawals, deposit returns.
        'financial' => ['debt_rep', 'invest', 'vznos_return', 'zalog_vozvrat'],
    ];

    /**
     * Inverse lookup: type2 → bucket key.
     */
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
     * GET /finance/pnl?from&to&granularity
     *
     * One row per period with revenue, total expenses, EBITDA, and an expense
     * breakdown across COGS / OPEX (payroll, rent, marketing, admin) / taxes
     * / financial. EBITDA = revenue − (cogs + opex_* + taxes + financial)
     * which matches annual_pnl_summary.csv.
     */
    public function pnl(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();
        $key  = $this->cacheKey('finance.pnl', [
            'from' => $from, 'to' => $to, 'granularity' => $request->input('granularity'),
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $request) {
            $periodRevenue  = $request->granularityFormatFor('cr_time');
            $periodExpenses = $request->granularityFormatFor('acc_date');

            // Revenue per period
            $revenue = DB::select("
                SELECT {$periodRevenue} AS period,
                       ROUND(SUM(r_paid + delivery_paid), 2) AS revenue_byn,
                       COUNT(*) AS deals
                FROM rent_deals_arch
                WHERE cr_time BETWEEN ? AND ?
                GROUP BY period
            ", [$from, $to]);

            // Expenses per period+type2; we'll roll up in PHP into buckets.
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
            foreach ($revenue as $r) {
                $byPeriod[$r->period] = [
                    'period'             => $r->period,
                    'revenue_byn'        => (float) $r->revenue_byn,
                    'deals'              => (int) $r->deals,
                    'expenses_total_byn' => 0.0,
                    'cogs_byn'           => 0.0,
                    'opex_payroll_byn'   => 0.0,
                    'opex_rent_byn'      => 0.0,
                    'opex_marketing_byn' => 0.0,
                    'opex_admin_byn'     => 0.0,
                    'taxes_byn'          => 0.0,
                    'financial_byn'      => 0.0,
                    'ebitda_byn'         => 0.0,
                ];
            }

            foreach ($expenses as $e) {
                $period = $e->period;
                if (!isset($byPeriod[$period])) {
                    $byPeriod[$period] = [
                        'period'             => $period,
                        'revenue_byn'        => 0.0,
                        'deals'              => 0,
                        'expenses_total_byn' => 0.0,
                        'cogs_byn'           => 0.0,
                        'opex_payroll_byn'   => 0.0,
                        'opex_rent_byn'      => 0.0,
                        'opex_marketing_byn' => 0.0,
                        'opex_admin_byn'     => 0.0,
                        'taxes_byn'          => 0.0,
                        'financial_byn'      => 0.0,
                        'ebitda_byn'         => 0.0,
                    ];
                }
                // amount_signed is negative for expenses; flip sign for human-friendly figures.
                $abs = -((float) $e->amount_signed);
                $byPeriod[$period][self::bucketFor($e->type2) . '_byn'] += $abs;
                $byPeriod[$period]['expenses_total_byn'] += $abs;
            }

            foreach ($byPeriod as &$row) {
                $row['ebitda_byn'] = round($row['revenue_byn'] - $row['expenses_total_byn'], 2);
                foreach (['revenue_byn','expenses_total_byn','cogs_byn','opex_payroll_byn','opex_rent_byn','opex_marketing_byn','opex_admin_byn','taxes_byn','financial_byn'] as $f) {
                    $row[$f] = round($row[$f], 2);
                }
            }
            unset($row);

            ksort($byPeriod);
            return array_values($byPeriod);
        });

        $warnings = $this->pnlWarnings($request->input('to'));

        return $this->envelope($request->queryEcho(), $rows, [
            'warnings' => $warnings,
            'expense_buckets' => self::EXPENSE_BUCKETS,
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
     * GET /finance/revenue?from&to&granularity&category&location
     *
     * Per-period rental revenue with optional category / location slicing.
     * `category` matches RangeRequest::CATEGORIES — translated to razdel via
     * /meta/categories. `location` is an offices.id integer or 'all'.
     */
    public function revenue(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $category = $request->input('category', 'all');
        $location = $request->input('location', 'all');

        $key = $this->cacheKey('finance.revenue', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
            'cat'  => $category, 'loc' => $location,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $category, $location, $request) {
            $period  = $request->granularityFormatFor('da.cr_time');
            $joins   = '';
            $where   = ['da.cr_time BETWEEN ? AND ?'];
            $params  = [$from, $to];

            if ($category !== 'all') {
                $razdel = $this->categoryToRazdelId($category);
                if ($razdel !== null) {
                    $joins .= "
                        JOIN tovar_rent_items tri      ON tri.item_inv_n = da.item_inv_n
                        JOIN subrazdel_category sc     ON sc.tovar_rent_cat_id = tri.cat_id
                        JOIN razdel_subrazdel rs       ON rs.id_sub_razdel = sc.id_sub_razdel
                    ";
                    $where[]  = 'rs.id_razdel = ?';
                    $params[] = $razdel;
                } else {
                    // Unknown category → empty result instead of incorrect totals.
                    return [];
                }
            }

            if ($location !== 'all' && is_numeric($location)) {
                $where[]  = 'da.first_rent_place = ?';
                $params[] = (int) $location;
            }

            $whereSql = implode(' AND ', $where);

            return DB::select("
                SELECT {$period} AS period,
                       ROUND(SUM(da.r_paid),         2) AS rent_byn,
                       ROUND(SUM(da.delivery_paid),  2) AS delivery_byn,
                       ROUND(SUM(da.r_paid + da.delivery_paid), 2) AS total_byn,
                       COUNT(DISTINCT da.deal_id)            AS deals,
                       COUNT(DISTINCT da.client_id)          AS unique_clients
                FROM rent_deals_arch da
                {$joins}
                WHERE {$whereSql}
                GROUP BY period
                ORDER BY period
            ", $params);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /finance/expenses?from&to&granularity&channel
     *
     * Per-period+article expense breakdown. `channel`:
     *   'all'  — no filter
     *   'cash' — channel IN (1,2,3,4,cur)  (office tills + courier till)
     *   'bank' — channel = 'bank'
     *   '<value>' — exact match
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
     *
     * Per-period inflow/outflow grouped by `kassa` field (k1, k2, bank, card, cur).
     * Useful for reconciling till balances and spotting channel-routing changes.
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
     * RangeRequest::CATEGORIES → razdel.id_razdel.
     * Returns null when the category has no current razdel (e.g. 'tools').
     */
    private function categoryToRazdelId(string $category): ?int
    {
        // Hard-coded URL slugs ↔ razdel rows; same mapping lives in MetaController.
        // We resolve once and cache 24h since razdel is effectively static.
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
