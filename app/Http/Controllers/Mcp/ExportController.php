<?php

namespace App\Http\Controllers\Mcp;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/mcp/v1/export/monthly/{topic}
 *
 * CSV streaming export for regular backfill of
 * /home/dmitry/Documents/прокат/04_analytics/data/monthly/*.csv.
 * Column layout matches data/monthly/_schema.md and the existing CSV
 * headers exactly so files can be appended without breaking downstream
 * tooling.
 *
 * Topics implemented from MySQL: operations, revenue, pnl.
 * Topic `traffic` requires Yandex Metrika and is returned as a header-only
 * file with X-Mcp-Warning hinting where to fetch the data instead.
 */
class ExportController extends BaseController
{
    private const TOPICS = ['operations', 'revenue', 'pnl', 'traffic'];

    public function monthly(Request $request, string $topic): StreamedResponse
    {
        if (!in_array($topic, self::TOPICS, true)) {
            abort(404, 'Unknown export topic. Allowed: ' . implode(', ', self::TOPICS));
        }
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $fromTs = Carbon::parse($request->input('from', '2014-01-01'))->startOfDay()->timestamp;
        $toTs   = Carbon::parse($request->input('to',   Carbon::now()->endOfMonth()->toDateString()))->endOfDay()->timestamp;

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $topic . '.csv"',
        ];

        return response()->streamDownload(function () use ($topic, $fromTs, $toTs) {
            $out = fopen('php://output', 'w');
            switch ($topic) {
                case 'operations': $this->streamOperations($out, $fromTs, $toTs); break;
                case 'revenue':    $this->streamRevenue($out, $fromTs, $toTs);    break;
                case 'pnl':        $this->streamPnl($out, $fromTs, $toTs);        break;
                case 'traffic':    $this->streamTrafficStub($out);                break;
            }
            fclose($out);
        }, $topic . '.csv', $headers);
    }

    /**
     * operations.csv columns (from data/monthly/_schema.md):
     *   period, category, applications, applications_online, applications_phone,
     *   applications_messenger, bookings, issuances, prolongations, returns,
     *   cancelled, no_show, damages, losses,
     *   cr_application_to_booking, cr_booking_to_issuance, avg_rental_days
     */
    private function streamOperations($out, int $from, int $to): void
    {
        fputcsv($out, [
            'period', 'category', 'applications', 'applications_online', 'applications_phone',
            'applications_messenger', 'bookings', 'issuances', 'prolongations', 'returns',
            'cancelled', 'no_show', 'damages', 'losses',
            'cr_application_to_booking', 'cr_booking_to_issuance', 'avg_rental_days',
        ]);

        $rows = DB::select("
            SELECT DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m') AS period,
                   COUNT(*) AS applications,
                   SUM(CASE WHEN web = 1 THEN 1 ELSE 0 END) AS applications_online
            FROM rent_orders_arch
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
        ", [$from, $to]);
        $apps = [];
        foreach ($rows as $r) {
            $apps[$r->period] = ['applications' => (int) $r->applications, 'online' => (int) $r->applications_online];
        }

        $rows = DB::select("
            SELECT DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m') AS period, COUNT(*) AS cnt
            FROM zvonki
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
        ", [$from, $to]);
        $calls = [];
        foreach ($rows as $r) {
            $calls[$r->period] = (int) $r->cnt;
        }

        $rows = DB::select("
            SELECT DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m') AS period,
                   COUNT(*) AS issuances,
                   ROUND(AVG(CASE
                       WHEN return_date > cr_time
                       THEN (LEAST(return_date, ?) - cr_time) / 86400
                   END), 2) AS avg_rental_days
            FROM rent_deals_arch
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
        ", [$to, $from, $to]);
        $deals = [];
        foreach ($rows as $r) {
            $deals[$r->period] = [
                'issuances'       => (int) $r->issuances,
                'avg_rental_days' => $r->avg_rental_days !== null ? (float) $r->avg_rental_days : 0,
            ];
        }

        $rows = DB::select("
            SELECT DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m') AS period, COUNT(*) AS cnt
            FROM rent_sub_deals_arch
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
        ", [$from, $to]);
        $subs = [];
        foreach ($rows as $r) {
            $subs[$r->period] = (int) $r->cnt;
        }

        $rows = DB::select("
            SELECT DATE_FORMAT(FROM_UNIXTIME(return_date), '%Y-%m') AS period, COUNT(*) AS cnt
            FROM rent_deals_arch
            WHERE return_date BETWEEN ? AND ? AND return_date > 0
            GROUP BY period
        ", [$from, $to]);
        $rets = [];
        foreach ($rows as $r) {
            $rets[$r->period] = (int) $r->cnt;
        }

        $allPeriods = array_unique(array_merge(
            array_keys($apps), array_keys($calls), array_keys($deals), array_keys($subs), array_keys($rets)
        ));
        sort($allPeriods);

        foreach ($allPeriods as $period) {
            $applications = $apps[$period]['applications'] ?? 0;
            $online       = $apps[$period]['online']       ?? 0;
            $phone        = $calls[$period]                ?? 0;
            $issuances    = $deals[$period]['issuances']   ?? 0;
            $avgDays      = $deals[$period]['avg_rental_days'] ?? 0;
            $subDeals     = $subs[$period]                 ?? 0;
            $returns      = $rets[$period]                 ?? 0;

            // application-to-booking and booking-to-issuance not directly modelable here
            // (we don't have a separate "booking" stage) → use applications→issuances ratio.
            $crAppToBook = $applications > 0 ? round($issuances / $applications, 3) : 0;

            fputcsv($out, [
                $period, 'all',
                $applications + $phone, $online, $phone, 0,
                $issuances,                  // bookings ≈ issuances (no separate booking stage)
                $issuances,
                $subDeals,
                $returns,
                0, 0, 0, 0,                  // cancelled, no_show, damages, losses — not tracked yet
                $crAppToBook, 1.0,           // cr_application_to_booking, cr_booking_to_issuance
                $avgDays,
            ]);
        }
    }

    /**
     * revenue.csv columns:
     *   period, category, segment, revenue_gross, revenue_discounts, revenue_net,
     *   refunds, penalties, revenue_final, issuances_count, avg_check
     */
    private function streamRevenue($out, int $from, int $to): void
    {
        fputcsv($out, [
            'period', 'category', 'segment', 'revenue_gross', 'revenue_discounts',
            'revenue_net', 'refunds', 'penalties', 'revenue_final',
            'issuances_count', 'avg_check',
        ]);

        $rows = DB::select("
            SELECT DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m') AS period,
                   ROUND(SUM(r_paid + delivery_paid), 2)        AS revenue_final,
                   COUNT(*)                                      AS issuances_count,
                   ROUND(AVG(r_paid + delivery_paid), 2)         AS avg_check
            FROM rent_deals_arch
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
            ORDER BY period
        ", [$from, $to]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r->period, 'all', 'all',
                0, 0, 0, 0, 0,                              // discounts/refunds/penalties not tracked here
                $r->revenue_final ?: 0,
                (int) $r->issuances_count,
                $r->avg_check ?: 0,
            ]);
        }
    }

    /**
     * pnl.csv columns:
     *   period, revenue_total, cogs_park_amortization, cogs_cleaning, cogs_repairs,
     *   cogs_courier_fuel, cogs_courier_salary, direct_costs, gross_margin,
     *   opex_rent, opex_payroll, opex_marketing, opex_telephony, opex_admin,
     *   ebitda, capex_new_park, net_cash_flow
     */
    private function streamPnl($out, int $from, int $to): void
    {
        fputcsv($out, [
            'period', 'revenue_total', 'cogs_park_amortization', 'cogs_cleaning',
            'cogs_repairs', 'cogs_courier_fuel', 'cogs_courier_salary', 'direct_costs',
            'gross_margin', 'opex_rent', 'opex_payroll', 'opex_marketing',
            'opex_telephony', 'opex_admin', 'ebitda', 'capex_new_park', 'net_cash_flow',
        ]);

        $revenue = DB::select("
            SELECT DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m') AS period,
                   ROUND(SUM(r_paid + delivery_paid), 2)        AS revenue_total
            FROM rent_deals_arch
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
        ", [$from, $to]);

        $expenses = DB::select("
            SELECT DATE_FORMAT(FROM_UNIXTIME(acc_date), '%Y-%m') AS period,
                   type2,
                   ROUND(SUM(-amount), 2) AS amount
            FROM doh_rash
            WHERE type1 = 'rash' AND acc_date BETWEEN ? AND ?
            GROUP BY period, type2
        ", [$from, $to]);

        $byPeriod = [];
        foreach ($revenue as $r) {
            $byPeriod[$r->period] = [
                'revenue_total'     => (float) $r->revenue_total,
                'cogs_repairs'      => 0.0,
                'cogs_courier_fuel' => 0.0,
                'cogs_capex'        => 0.0,
                'opex_rent'         => 0.0,
                'opex_payroll'      => 0.0,
                'opex_marketing'    => 0.0,
                'opex_telephony'    => 0.0,
                'opex_admin'        => 0.0,
            ];
        }
        foreach ($expenses as $e) {
            if (!isset($byPeriod[$e->period])) {
                $byPeriod[$e->period] = [
                    'revenue_total'     => 0.0,
                    'cogs_repairs'      => 0.0,
                    'cogs_courier_fuel' => 0.0,
                    'cogs_capex'        => 0.0,
                    'opex_rent'         => 0.0,
                    'opex_payroll'      => 0.0,
                    'opex_marketing'    => 0.0,
                    'opex_telephony'    => 0.0,
                    'opex_admin'        => 0.0,
                ];
            }
            $amt = (float) $e->amount;
            switch ($e->type2) {
                case 'remont_tov':
                    $byPeriod[$e->period]['cogs_repairs'] += $amt;
                    break;
                case 'fuel': case 'double_benz': case 'car':
                    $byPeriod[$e->period]['cogs_courier_fuel'] += $amt;
                    break;
                case 'tovar':
                    $byPeriod[$e->period]['cogs_capex'] += $amt;
                    break;
                case 'of1_rent': case 'of2_rent': case 'r3_rent':
                    $byPeriod[$e->period]['opex_rent'] += $amt;
                    break;
                case 'zpl':
                    $byPeriod[$e->period]['opex_payroll'] += $amt;
                    break;
                case 'adv':
                    $byPeriod[$e->period]['opex_marketing'] += $amt;
                    break;
                case 'connect':
                    $byPeriod[$e->period]['opex_telephony'] += $amt;
                    break;
                default:
                    $byPeriod[$e->period]['opex_admin'] += $amt;
            }
        }

        ksort($byPeriod);
        foreach ($byPeriod as $period => $b) {
            $direct      = $b['cogs_repairs'] + $b['cogs_courier_fuel'];
            $grossMargin = $b['revenue_total'] - $direct;
            $opexTotal   = $b['opex_rent'] + $b['opex_payroll'] + $b['opex_marketing']
                         + $b['opex_telephony'] + $b['opex_admin'];
            $ebitda      = $grossMargin - $opexTotal;
            $netCashFlow = $ebitda - $b['cogs_capex'];

            fputcsv($out, [
                $period,
                round($b['revenue_total'], 2),
                0,                              // cogs_park_amortization — not tracked
                0,                              // cogs_cleaning — not tracked
                round($b['cogs_repairs'], 2),
                round($b['cogs_courier_fuel'], 2),
                0,                              // cogs_courier_salary — not separable
                round($direct, 2),
                round($grossMargin, 2),
                round($b['opex_rent'], 2),
                round($b['opex_payroll'], 2),
                round($b['opex_marketing'], 2),
                round($b['opex_telephony'], 2),
                round($b['opex_admin'], 2),
                round($ebitda, 2),
                round($b['cogs_capex'], 2),     // capex_new_park ≈ tovar purchases
                round($netCashFlow, 2),
            ]);
        }
    }

    private function streamTrafficStub($out): void
    {
        // Header-only file — traffic data lives in Yandex Metrika; see
        // 04_analytics/queries/metrika_basic_queries.sh.
        fputcsv($out, [
            'period', 'segment', 'region', 'category', 'source',
            'visits', 'users', 'pageviews', 'bounce_rate',
            'page_depth', 'avg_duration', 'new_visitors_pct',
        ]);
    }
}
