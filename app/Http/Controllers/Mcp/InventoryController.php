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

    /**
     * GET /inventory/utilization?from&to&category
     *
     * Per-model utilization = rented_days / (unit_count * period_days).
     * rented_days is computed by clipping each deal's [cr_time .. return_date]
     * window to the requested period and summing across all units of the model.
     * Deals that haven't returned yet (return_date = 0) are clipped at NOW().
     */
    public function utilization(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $category = $request->input('category', 'all');

        $key = $this->cacheKey('inventory.utilization', [
            'from' => $from, 'to' => $to, 'cat' => $category,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $category) {
            $razdel = $category !== 'all' ? $this->categoryToRazdelId($category) : null;
            if ($category !== 'all' && $razdel === null) {
                return [];
            }

            $periodSeconds = max(1, $to - $from);
            $now           = time();

            $catJoin   = '';
            $catWhere  = '';
            $catParams = [];
            if ($razdel !== null) {
                $catJoin = '
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ';
                $catWhere   = 'AND rs.id_razdel = ?';
                $catParams[] = $razdel;
            }

            // Number of physical units per model (independent of the period).
            $unitsByModel = [];
            $unitRows = DB::select("
                SELECT tri.model_id, COUNT(*) AS unit_count
                FROM tovar_rent_items tri
                {$catJoin}
                WHERE tri.model_id IS NOT NULL
                  {$catWhere}
                GROUP BY tri.model_id
            ", $catParams);
            foreach ($unitRows as $u) {
                $unitsByModel[$u->model_id] = (int) $u->unit_count;
            }

            // Rented seconds per model = sum of clipped [cr_time .. return_date or now] intervals.
            $rentedSql = "
                SELECT tri.model_id,
                       rmw.l2_name AS model_name,
                       SUM(
                           GREATEST(0,
                               LEAST(IF(da.return_date > 0, da.return_date, ?), ?)
                               - GREATEST(da.cr_time, ?)
                           )
                       ) AS rented_seconds,
                       COUNT(DISTINCT da.deal_id) AS deals
                FROM rent_deals_arch da
                JOIN tovar_rent_items tri ON tri.item_inv_n = da.item_inv_n
                LEFT JOIN rent_model_web rmw ON rmw.model_id = tri.model_id AND rmw.lang = 'ru'
                {$catJoin}
                WHERE da.cr_time < ?
                  AND (da.return_date = 0 OR da.return_date > ?)
                  {$catWhere}
                GROUP BY tri.model_id, rmw.l2_name
                ORDER BY rented_seconds DESC
            ";
            $rentedRows = DB::select($rentedSql, array_merge([$now, $to, $from, $to, $from], $catParams));

            $out = [];
            foreach ($rentedRows as $r) {
                $units    = $unitsByModel[$r->model_id] ?? 0;
                $rentSecs = (float) ($r->rented_seconds ?? 0);
                if ($units <= 0) {
                    continue;
                }
                $rentedDays = round($rentSecs / 86400, 2);
                $util       = round($rentSecs / ($units * $periodSeconds), 4);
                $out[] = [
                    'model_id'        => (int) $r->model_id,
                    'model_name'      => $r->model_name,
                    'units'           => $units,
                    'deals_in_period' => (int) $r->deals,
                    'rented_days'     => $rentedDays,
                    'utilization'     => min(1.0, $util),
                ];
            }
            return $out;
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /inventory/turnover?from&to&category
     *
     * Per-model turnover = deals_in_period / unit_count. A turnover of 4 means
     * each unit was rented out four times on average during the period.
     */
    public function turnover(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $category = $request->input('category', 'all');

        $key = $this->cacheKey('inventory.turnover', [
            'from' => $from, 'to' => $to, 'cat' => $category,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $category) {
            $razdel = $category !== 'all' ? $this->categoryToRazdelId($category) : null;
            if ($category !== 'all' && $razdel === null) {
                return [];
            }

            $catJoin   = '';
            $catWhere  = '';
            $catParams = [];
            if ($razdel !== null) {
                $catJoin = '
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ';
                $catWhere   = 'AND rs.id_razdel = ?';
                $catParams[] = $razdel;
            }

            return DB::select("
                SELECT tri.model_id,
                       rmw.l2_name AS model_name,
                       tc.cat_id,
                       tc.cat_name,
                       COUNT(DISTINCT tri.item_id)  AS units,
                       COUNT(DISTINCT da.deal_id)   AS deals,
                       ROUND(SUM(da.r_paid + da.delivery_paid), 2) AS revenue_byn,
                       ROUND(COUNT(DISTINCT da.deal_id) / NULLIF(COUNT(DISTINCT tri.item_id), 0), 2) AS turnover
                FROM tovar_rent_items tri
                LEFT JOIN rent_deals_arch da ON da.item_inv_n = tri.item_inv_n
                                               AND da.cr_time BETWEEN ? AND ?
                LEFT JOIN rent_model_web rmw ON rmw.model_id = tri.model_id AND rmw.lang = 'ru'
                LEFT JOIN tovar_cats tc      ON tc.cat_id    = tri.cat_id
                {$catJoin}
                WHERE 1=1
                  {$catWhere}
                GROUP BY tri.model_id, rmw.l2_name, tc.cat_id, tc.cat_name
                ORDER BY turnover DESC
            ", array_merge([$from, $to], $catParams));
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /inventory/idle?days=90&category
     *
     * Models whose last rental cr_time is older than `days` days ago (or that
     * have never been rented at all). Useful for ассортимент cleanup.
     */
    public function idle(Request $request): JsonResponse
    {
        $request->validate([
            'days'     => 'integer|min:1|max:3650',
            'category' => 'nullable|string|in:' . implode(',', \App\Http\Requests\Mcp\RangeRequest::CATEGORIES),
        ]);

        $days     = (int) $request->get('days', 90);
        $category = $request->get('category', 'all');
        $cutoff   = time() - $days * 86400;

        $key = $this->cacheKey('inventory.idle', ['days' => $days, 'cat' => $category]);

        $rows = $this->cacheRemember($key, self::TTL_DEFAULT, function () use ($cutoff, $days, $category) {
            $razdel = $category !== 'all' ? $this->categoryToRazdelId($category) : null;
            if ($category !== 'all' && $razdel === null) {
                return [];
            }

            $catJoin   = '';
            $catWhere  = '';
            $catParams = [];
            if ($razdel !== null) {
                $catJoin = '
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ';
                $catWhere   = 'AND rs.id_razdel = ?';
                $catParams[] = $razdel;
            }

            return DB::select("
                SELECT tri.model_id,
                       rmw.l2_name AS model_name,
                       tc.cat_id,
                       tc.cat_name,
                       COUNT(DISTINCT tri.item_id) AS units,
                       MAX(da.cr_time)             AS last_deal_ts,
                       FROM_UNIXTIME(MAX(da.cr_time), '%Y-%m-%d') AS last_deal_date,
                       FLOOR((? - MAX(da.cr_time)) / 86400) AS days_idle
                FROM tovar_rent_items tri
                LEFT JOIN rent_deals_arch da ON da.item_inv_n = tri.item_inv_n
                LEFT JOIN rent_model_web rmw ON rmw.model_id = tri.model_id AND rmw.lang = 'ru'
                LEFT JOIN tovar_cats tc      ON tc.cat_id    = tri.cat_id
                {$catJoin}
                WHERE 1=1
                  {$catWhere}
                GROUP BY tri.model_id, rmw.l2_name, tc.cat_id, tc.cat_name
                HAVING (MAX(da.cr_time) IS NULL OR MAX(da.cr_time) < ?)
                ORDER BY (MAX(da.cr_time) IS NULL) DESC, MAX(da.cr_time) ASC
            ", array_merge([time()], $catParams, [$cutoff]));
        });

        return $this->envelope([
            'days'     => $days,
            'category' => $category,
            'cutoff_iso' => gmdate('Y-m-d\TH:i:s\Z', $cutoff),
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
