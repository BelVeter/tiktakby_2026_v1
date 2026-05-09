<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Carnival-costume booking funnel and revenue under /api/mcp/v1/carnival/*.
 * Implemented in step A.11.
 *
 * Source tables: karn_brons (active) + karn_brons_arch — separate funnel
 * from regular rentals. Strong December seasonality.
 */
class CarnivalController extends BaseController
{
    /**
     * SQL fragment that unions karn_brons + karn_brons_arch under alias `k`,
     * giving us the full booking history for carnival costumes.
     */
    private const ALL_BRONS = "
        (
            SELECT kb_id, cl_id, status, payment_k1, payment_k2, payment_term, payment_bank,
                   cr_time, appr_time, vidacha, vozvrat, nedozvon
            FROM karn_brons
            UNION ALL
            SELECT kb_id, cl_id, status, payment_k1, payment_k2, payment_term, payment_bank,
                   cr_time, appr_time, vidacha, vozvrat, nedozvon
            FROM karn_brons_arch
        ) k
    ";

    /**
     * GET /carnival/funnel?from&to
     *
     * Funnel built from karn_brons + karn_brons_arch:
     *   Stage 1 — bookings (cr_time in period)
     *   Stage 2 — approved (appr_time > 0)
     *   Stage 3 — issued   (vidacha   > 0)
     *   Stage 4 — returned (vozvrat   > 0)
     * Plus side-counters: unanswered (nedozvon > 0).
     */
    public function funnel(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();

        $key = $this->cacheKey('carnival.funnel', ['from' => $from, 'to' => $to]);

        $payload = $this->cacheRemember($key, self::TTL_DEFAULT, function () use ($from, $to) {
            $row = DB::selectOne("
                SELECT
                    COUNT(*)                                    AS bookings,
                    SUM(CASE WHEN appr_time > 0 THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN vidacha   > 0 THEN 1 ELSE 0 END) AS issued,
                    SUM(CASE WHEN vozvrat   > 0 THEN 1 ELSE 0 END) AS returned,
                    SUM(CASE WHEN nedozvon  > 0 THEN 1 ELSE 0 END) AS unanswered
                FROM " . self::ALL_BRONS . "
                WHERE cr_time BETWEEN ? AND ?
            ", [$from, $to]);

            $bookings  = (int) $row->bookings;
            $approved  = (int) $row->approved;
            $issued    = (int) $row->issued;
            $returned  = (int) $row->returned;

            return [
                'bookings'    => $bookings,
                'approved'    => $approved,
                'issued'      => $issued,
                'returned'    => $returned,
                'unanswered'  => (int) $row->unanswered,
                'conversion_rates' => [
                    'booking_to_approved' => $bookings > 0 ? round($approved / $bookings, 4) : null,
                    'approved_to_issued'  => $approved > 0 ? round($issued / $approved, 4)   : null,
                    'issued_to_returned'  => $issued   > 0 ? round($returned / $issued, 4)   : null,
                    'booking_to_issued'   => $bookings > 0 ? round($issued / $bookings, 4)   : null,
                ],
            ];
        });

        return $this->envelope($request->queryEcho(), $payload);
    }

    /**
     * GET /carnival/seasonality?years=5
     *
     * Month-of-year profile (deals + revenue) over the last `years` years.
     * December must be the absolute peak (NYE costumes + matinees).
     */
    public function seasonality(Request $request): JsonResponse
    {
        $request->validate([
            'years' => 'integer|min:1|max:11',
        ]);
        $years  = (int) $request->get('years', 5);
        $cutoff = strtotime("-{$years} years");

        $key = $this->cacheKey('carnival.seasonality', ['years' => $years]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($cutoff) {
            $monthly = DB::select("
                SELECT MONTH(FROM_UNIXTIME(cr_time))                                AS month_num,
                       COUNT(*)                                                      AS bookings,
                       SUM(CASE WHEN vidacha > 0 THEN 1 ELSE 0 END)                  AS issued,
                       ROUND(SUM(payment_k1 + payment_k2 + payment_term + payment_bank), 2) AS revenue_byn,
                       COUNT(DISTINCT YEAR(FROM_UNIXTIME(cr_time)))                  AS years_covered
                FROM " . self::ALL_BRONS . "
                WHERE cr_time >= ?
                GROUP BY month_num
                ORDER BY month_num
            ", [$cutoff]);

            if (empty($monthly)) {
                return [];
            }

            $totalBookings = array_sum(array_map(fn($r) => (int) $r->bookings, $monthly));
            $avgPerSlot    = $totalBookings / max(1, count($monthly));

            $monthNames = [
                1 => 'January',  2 => 'February',  3 => 'March',
                4 => 'April',    5 => 'May',       6 => 'June',
                7 => 'July',     8 => 'August',    9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December',
            ];

            $out = [];
            foreach ($monthly as $r) {
                $bookings = (int) $r->bookings;
                $years    = max(1, (int) $r->years_covered);
                $out[] = [
                    'month_num'             => (int) $r->month_num,
                    'month_name'            => $monthNames[(int) $r->month_num] ?? null,
                    'bookings'              => $bookings,
                    'issued'                => (int) $r->issued,
                    'revenue_byn'           => (float) $r->revenue_byn,
                    'avg_bookings_per_year' => round($bookings / $years, 2),
                    'years_covered'         => $years,
                    'seasonality_index'     => round($bookings / $avgPerSlot, 3),
                ];
            }
            return $out;
        });

        return $this->envelope(['years' => $years], $rows);
    }

    /**
     * GET /carnival/revenue?from&to&granularity
     *
     * Per-period revenue by payment channel (k1, k2, terminal, bank).
     */
    public function revenue(RangeRequest $request): JsonResponse
    {
        $from = $request->fromTimestamp();
        $to   = $request->toTimestamp();

        $key = $this->cacheKey('carnival.revenue', [
            'from' => $from, 'to' => $to,
            'g'    => $request->input('granularity'),
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $request) {
            $period = $request->granularityFormatFor('cr_time');

            return DB::select("
                SELECT {$period}                              AS period,
                       COUNT(*)                                AS bookings,
                       ROUND(SUM(payment_k1),   2)             AS revenue_k1_byn,
                       ROUND(SUM(payment_k2),   2)             AS revenue_k2_byn,
                       ROUND(SUM(payment_term), 2)             AS revenue_terminal_byn,
                       ROUND(SUM(payment_bank), 2)             AS revenue_bank_byn,
                       ROUND(SUM(payment_k1 + payment_k2 + payment_term + payment_bank), 2) AS revenue_total_byn
                FROM " . self::ALL_BRONS . "
                WHERE cr_time BETWEEN ? AND ?
                GROUP BY period
                ORDER BY period
            ", [$from, $to]);
        });

        return $this->envelope($request->queryEcho(), $rows);
    }
}
