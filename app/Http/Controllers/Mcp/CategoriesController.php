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

    public function seasonality(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }
}
