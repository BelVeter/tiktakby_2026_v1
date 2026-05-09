<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Inventory state and profitability endpoints under /api/mcp/v1/inventory/*.
 *
 * Existing endpoints (migrated from McpAnalyticsController):
 *   - GET /inventory/free-tree
 *   - GET /inventory/profitability
 * Stage 1 additions (A.6):
 *   - GET /inventory/utilization
 *   - GET /inventory/turnover
 *   - GET /inventory/idle
 */
class InventoryController extends BaseController
{
    /**
     * GET /inventory/free-tree?as_of=YYYY-MM-DD
     *
     * Tree razdel → sub_razdel → category → models with current free-unit
     * counts. "Free" = tovar_rent_items.active_deal_id = 0 AND status not in
     * ('в аренде','бронь','ремонт','списан').
     */
    public function freeTree(Request $request): JsonResponse
    {
        $asOf = $request->date('as_of') ?? now();
        $key  = $this->cacheKey('inventory.free_tree', ['as_of' => $asOf->toDateString()]);

        $data = $this->cacheRemember($key, self::TTL_DEFAULT, function () {
            $freeItems = DB::select("
                SELECT tri.model_id, COUNT(*) AS free_count
                FROM tovar_rent_items tri
                WHERE tri.active_deal_id = 0
                  AND (tri.status IS NULL OR tri.status NOT IN ('в аренде','бронь','ремонт','списан'))
                GROUP BY tri.model_id
            ");
            $freeMap = collect($freeItems)->keyBy('model_id');

            $rows = DB::select("
                SELECT
                    r.id_razdel              AS section_id,
                    r.name_razdel_text       AS section_name,
                    r.url_razdel_name        AS section_url,
                    sr.id_sub_razdel         AS subsection_id,
                    sr.name_sub_razdel_text  AS subsection_name,
                    sr.url_sub_razdel_name   AS subsection_url,
                    tc.cat_id,
                    tc.cat_name,
                    tc.cat_url,
                    rmw.model_id,
                    rmw.l2_name              AS model_name,
                    rmw.page_addr            AS model_url,
                    rmw.status               AS model_status
                FROM razdel r
                JOIN razdel_subrazdel rs   ON rs.id_razdel = r.id_razdel
                JOIN sub_razdel sr         ON sr.id_sub_razdel = rs.id_sub_razdel
                JOIN subrazdel_category sc ON sc.id_sub_razdel = sr.id_sub_razdel
                JOIN tovar_cats tc         ON tc.cat_id = sc.tovar_rent_cat_id
                JOIN rent_model_web rmw    ON rmw.lang = 'ru'
                JOIN tovar_list tl         ON tl.tovar_id = rmw.model_id AND tl.tovar_cat = tc.cat_id
                WHERE rmw.status = 'show'
                ORDER BY r.razdel_order_num, sr.order_num_sub_razd, tc.cat_order, rmw.sort_n
            ");

            $tree = [];
            foreach ($rows as $row) {
                $sid = $row->section_id;
                $ssid = $row->subsection_id;
                $cid = $row->cat_id;
                $mid = $row->model_id;

                if (!isset($tree[$sid])) {
                    $tree[$sid] = [
                        'section_id'   => $sid,
                        'section_name' => $row->section_name,
                        'section_url'  => $row->section_url,
                        'subsections'  => [],
                    ];
                }
                if (!isset($tree[$sid]['subsections'][$ssid])) {
                    $tree[$sid]['subsections'][$ssid] = [
                        'subsection_id'   => $ssid,
                        'subsection_name' => $row->subsection_name,
                        'subsection_url'  => $row->subsection_url,
                        'categories'      => [],
                    ];
                }
                if (!isset($tree[$sid]['subsections'][$ssid]['categories'][$cid])) {
                    $tree[$sid]['subsections'][$ssid]['categories'][$cid] = [
                        'cat_id'   => $cid,
                        'cat_name' => $row->cat_name,
                        'cat_url'  => $row->cat_url,
                        'models'   => [],
                    ];
                }

                $freeCount = $freeMap->get($mid)->free_count ?? 0;
                $tree[$sid]['subsections'][$ssid]['categories'][$cid]['models'][] = [
                    'model_id'   => $mid,
                    'model_name' => $row->model_name,
                    'model_url'  => $row->model_url,
                    'free_units' => (int) $freeCount,
                ];
            }

            return array_values(array_map(function ($section) {
                $section['subsections'] = array_values(array_map(function ($ss) {
                    $ss['categories'] = array_values(array_map(function ($cat) {
                        $cat['total_units'] = array_sum(array_column($cat['models'], 'free_units'));
                        return $cat;
                    }, $ss['categories']));
                    return $ss;
                }, $section['subsections']));
                return $section;
            }, $tree));
        });

        return $this->envelope(
            ['as_of' => $asOf->toDateString()],
            $data
        );
    }

    /**
     * GET /inventory/profitability?cat_id&model_id&min_deals
     *
     * Per-physical-item profitability: total revenue from rent_deals_act minus
     * the BYN purchase cost. cat_id / model_id narrow the result; min_deals
     * filters out items that have never been rented out enough times to matter.
     */
    public function profitability(Request $request): JsonResponse
    {
        $request->validate([
            'cat_id'    => 'integer|min:1',
            'model_id'  => 'integer|min:1',
            'min_deals' => 'integer|min:0',
        ]);

        $catFilter   = $request->filled('cat_id')   ? 'AND tri.cat_id = ?'   : '';
        $modelFilter = $request->filled('model_id') ? 'AND tri.model_id = ?' : '';
        $minDeals    = (int) $request->get('min_deals', 0);

        $params = [];
        if ($request->filled('cat_id'))   { $params[] = $request->integer('cat_id'); }
        if ($request->filled('model_id')) { $params[] = $request->integer('model_id'); }
        $params[] = $minDeals;

        $rows = DB::select("
            SELECT
                tri.item_id,
                tri.item_inv_n,
                tri.model_id,
                tri.cat_id,
                rmw.l2_name AS model_name,
                tc.cat_name,
                tri.buy_date,
                ROUND(tri.buy_price * COALESCE(tri.exch_to_byr, 1), 2) AS buy_price_byn,
                FROM_UNIXTIME(tri.buy_date, '%Y-%m-%d') AS buy_date_fmt,
                COUNT(rda.deal_id) AS total_deals,
                COALESCE(SUM(rda.r_to_pay + rda.delivery_to_pay), 0) AS total_revenue_byn,
                ROUND(
                    COALESCE(SUM(rda.r_to_pay + rda.delivery_to_pay), 0)
                    - ROUND(tri.buy_price * COALESCE(tri.exch_to_byr, 1), 2),
                2) AS profit_byn,
                tri.status AS current_status
            FROM tovar_rent_items tri
            LEFT JOIN rent_deals_act rda ON rda.item_inv_n = tri.item_inv_n
            LEFT JOIN rent_model_web rmw ON rmw.model_id = tri.model_id AND rmw.lang = 'ru'
            LEFT JOIN tovar_cats tc      ON tc.cat_id = tri.cat_id
            WHERE 1=1
              {$catFilter}
              {$modelFilter}
            GROUP BY tri.item_id, tri.item_inv_n, tri.model_id, tri.cat_id, rmw.l2_name, tc.cat_name, tri.buy_date, tri.buy_price, tri.exch_to_byr, tri.status
            HAVING COUNT(rda.deal_id) >= ?
            ORDER BY profit_byn DESC
        ", $params);

        return $this->envelope([
            'cat_id'    => $request->get('cat_id'),
            'model_id'  => $request->get('model_id'),
            'min_deals' => $minDeals,
        ], $rows);
    }

    public function utilization(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    public function turnover(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    public function idle(Request $request): JsonResponse
    {
        return $this->envelope([], []);
    }
}
