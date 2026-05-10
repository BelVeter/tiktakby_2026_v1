<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Category-level analytics under /api/mcp/v1/categories/*.
 *
 * Existing endpoint (migrated from McpAnalyticsController):
 *   - GET /categories/performance
 * Stage 1 addition (A.10):
 *   - GET /categories/seasonality
 */
class CategoriesController extends BaseController
{
    /**
     * GET /categories/performance?date_from=&date_to=
     */
    public function performance(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->date('date_from')->startOfDay()->timestamp;
        $dateTo   = $request->date('date_to')->endOfDay()->timestamp;

        $rows = DB::select("
            SELECT
                tc.cat_id,
                tc.cat_name,
                tc.cat_url,
                sr.name_sub_razdel_text AS subsection_name,
                r.name_razdel_text      AS section_name,
                COUNT(rda.deal_id)                                 AS total_deals,
                COUNT(DISTINCT rda.client_id)                      AS unique_clients,
                ROUND(SUM(rda.r_to_pay + rda.delivery_to_pay), 2)  AS total_revenue_byn,
                ROUND(AVG(rda.r_to_pay), 2)                        AS avg_deal_byn,
                COUNT(DISTINCT tri.model_id)                       AS active_models,
                COUNT(DISTINCT tri.item_inv_n)                     AS total_units
            FROM rent_deals_act rda
            JOIN tovar_rent_items tri      ON tri.item_inv_n = rda.item_inv_n
            JOIN tovar_cats tc             ON tc.cat_id = tri.cat_id
            LEFT JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.cat_id
            LEFT JOIN sub_razdel sr         ON sr.id_sub_razdel = sc.id_sub_razdel
            LEFT JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sr.id_sub_razdel
            LEFT JOIN razdel r              ON r.id_razdel = rs.id_razdel
            WHERE rda.cr_time BETWEEN ? AND ?
            GROUP BY tc.cat_id, tc.cat_name, tc.cat_url, sr.name_sub_razdel_text, r.name_razdel_text
            ORDER BY total_revenue_byn DESC
        ", [$dateFrom, $dateTo]);

        return $this->envelope([
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
        ], $rows);
    }

    /**
     * GET /categories/seasonality?category=&years=5
     *
     * Monthly seasonality profile for a business category aggregated over
     * the last `years` years. Each row is a month-of-year (1-12) with
     * average deals + revenue, plus a seasonality_index where 1.0 means
     * "average month". Driver question: when is each category busiest?
     * (Costumes peak in December — this should show index ≈ 3-4 for Dec.)
     */
    public function seasonality(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'nullable|string|in:' . implode(',', \App\Http\Requests\Mcp\RangeRequest::CATEGORIES),
            'years'    => 'integer|min:1|max:11',
        ]);

        $category = $request->get('category', 'all');
        $years    = (int) $request->get('years', 5);
        $cutoff   = strtotime("-{$years} years");

        $key = $this->cacheKey('categories.seasonality', [
            'cat' => $category, 'years' => $years,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($category, $years, $cutoff) {
            $razdel = $category !== 'all' ? $this->categoryToRazdelId($category) : null;
            if ($category !== 'all' && $razdel === null) {
                return [];
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

            $monthly = DB::select("
                SELECT MONTH(FROM_UNIXTIME(da.cr_time))                AS month_num,
                       COUNT(*)                                         AS deals,
                       ROUND(SUM(da.r_paid + da.delivery_paid), 2)      AS revenue_byn,
                       COUNT(DISTINCT YEAR(FROM_UNIXTIME(da.cr_time))) AS years_covered
                FROM rent_deals_arch da
                {$catJoin}
                WHERE da.cr_time >= ?
                  {$catWhere}
                GROUP BY month_num
                ORDER BY month_num
            ", array_merge([$cutoff], $catParams));

            if (empty($monthly)) {
                return [];
            }

            $totalDeals = array_sum(array_map(fn($r) => (int) $r->deals, $monthly));
            $avgPerSlot = $totalDeals / max(1, count($monthly));

            $monthNames = [
                1 => 'January',  2 => 'February',  3 => 'March',
                4 => 'April',    5 => 'May',       6 => 'June',
                7 => 'July',     8 => 'August',    9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December',
            ];

            $out = [];
            foreach ($monthly as $r) {
                $deals  = (int) $r->deals;
                $years  = max(1, (int) $r->years_covered);
                $out[]  = [
                    'month_num'         => (int) $r->month_num,
                    'month_name'        => $monthNames[(int) $r->month_num] ?? null,
                    'deals'             => $deals,
                    'avg_deals_per_year' => round($deals / $years, 2),
                    'revenue_byn'       => (float) $r->revenue_byn,
                    'years_covered'     => $years,
                    'seasonality_index' => round($deals / $avgPerSlot, 3),
                ];
            }
            return $out;
        });

        return $this->envelope([
            'category' => $category,
            'years'    => $years,
        ], $rows);
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
