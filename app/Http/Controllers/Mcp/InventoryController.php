<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Inventory state and profitability endpoints under /api/mcp/v1/inventory/*.
 *
 * Methodology (matches legacy /bb/cat_analysis.php + tovar::*):
 *   - Inventory at date X counts BOTH `tovar_rent_items` (currently active)
 *     AND `tovar_rent_items_arch` items that were alive at X
 *     (buy_date <= X AND arch_time >= X).
 *   - Rental windows use `start_date`..`return_date` (not cr_time).
 *     Open deals (return_date = 0) are clipped at NOW().
 *   - Utilization denominator uses the average of units at the period start
 *     and units at the period end — same as cat_analysis.php.
 */
class InventoryController extends BaseController
{
    /**
     * GET /inventory/free-tree?as_of=YYYY-MM-DD
     *
     * Current catalog tree with free-unit counts. "Free" = active_deal_id=0
     * AND status NOT IN ('в аренде','бронь','ремонт','списан').
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
                $sid = $row->section_id; $ssid = $row->subsection_id; $cid = $row->cat_id; $mid = $row->model_id;
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

        return $this->envelope(['as_of' => $asOf->toDateString()], $data);
    }

    /**
     * GET /inventory/profitability?cat_id&model_id&min_deals
     *
     * Per-physical-item profitability across UNION(rent_deals_act, rent_deals_arch).
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

        $daSub = $this->unifiedDealsSubquery();

        $rows = DB::select("
            SELECT
                tri.item_id,
                tri.item_inv_n,
                tri.model_id,
                tri.cat_id,
                rmw.l2_name AS model_name,
                tc.rent_cat_name AS cat_name,
                tri.buy_date,
                ROUND(tri.buy_price * COALESCE(tri.exch_to_byr, 1), 2) AS buy_price_byn,
                FROM_UNIXTIME(tri.buy_date, '%Y-%m-%d') AS buy_date_fmt,
                COUNT(da.deal_id) AS total_deals,
                COALESCE(SUM(da.r_to_pay + da.delivery_to_pay), 0) AS total_revenue_byn,
                ROUND(
                    COALESCE(SUM(da.r_to_pay + da.delivery_to_pay), 0)
                    - ROUND(tri.buy_price * COALESCE(tri.exch_to_byr, 1), 2),
                2) AS profit_byn,
                tri.status AS current_status
            FROM tovar_rent_items tri
            LEFT JOIN {$daSub} da ON da.item_inv_n = tri.item_inv_n
            LEFT JOIN rent_model_web rmw ON rmw.model_id = tri.model_id AND rmw.lang = 'ru'
            LEFT JOIN tovar_rent_cat tc  ON tc.tovar_rent_cat_id = tri.cat_id
            WHERE 1=1
              {$catFilter}
              {$modelFilter}
            GROUP BY tri.item_id, tri.item_inv_n, tri.model_id, tri.cat_id, rmw.l2_name, tc.rent_cat_name, tri.buy_date, tri.buy_price, tri.exch_to_byr, tri.status
            HAVING COUNT(da.deal_id) >= ?
            ORDER BY profit_byn DESC
        ", $params);

        return $this->envelope([
            'cat_id'    => $request->get('cat_id'),
            'model_id'  => $request->get('model_id'),
            'min_deals' => $minDeals,
        ], $rows);
    }

    /**
     * GET /inventory/utilization?from&to&category&include_carnival
     *
     * Per-model utilization = rented_days / (avg_units × period_days).
     *
     *   - rented_days uses [start_date..return_date] clipped to [from..to].
     *     Open deals (return_date=0) are clipped at NOW().
     *   - units = (units_at_from + units_at_to) / 2 — matches cat_analysis.php.
     *     Units are counted across BOTH tovar_rent_items (current) and
     *     tovar_rent_items_arch (sold/lost) — an item that was sold in
     *     2023 still belongs to 2022's denominator.
     */
    public function utilization(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $categories = $request->categories();
        $catStr     = implode(',', $categories);
        $incCarn  = $request->includeCarnival();

        $key = $this->cacheKey('inventory.utilization', [
            'from' => $from, 'to' => $to, 'cat' => $catStr, 'inc' => $incCarn ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $categories, $incCarn) {
            $razdelIds = $this->categoryToRazdelIds($categories);
            if (!in_array('all', $categories, true) && empty($razdelIds)) {
                return [];
            }

            $periodSeconds = max(1, $to - $from);
            $now           = time();

            $daSub   = $this->unifiedDealsSubquery();
            $itSub   = $this->unifiedItemsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

            // Per-model historical inventory at $from and $to.
            $unitsAtFrom = $this->modelInventoryAtDate($from, $razdelIds, $incCarn);
            $unitsAtTo   = $this->modelInventoryAtDate($to,   $razdelIds, $incCarn);

            $allModelIds = array_unique(array_merge(array_keys($unitsAtFrom), array_keys($unitsAtTo)));

            // Rented seconds per model. razdel filter goes via itemsInRazdelSubquery()
            // join (avoids M:N inflation of SUM(rented_seconds)). Actual bind order
            // (see array_merge below): SELECT params, then JOIN params (razdel), then
            // WHERE params.
            $joinParams  = [];
            $selectParams = [$now, $to, $from];                       // IF + LEAST + GREATEST
            // Must have exactly as many values as `?` in $where below (start_date < ?,
            // return_date > ?, start_date < ?) — under emulated prepares PDO silently
            // drops trailing extra bindings instead of erroring, which previously left
            // an errant leading $now here shifting every WHERE placeholder by one and
            // made /inventory/utilization undercount deals_in_period/rented_days by
            // ~8x (only long-open deals with return_date=0 happened to still match).
            $whereParams  = [$to, $from, $to];                        // start_date < ?, return_date > ?, start_date < ?
            $joins  = "
                JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
                LEFT JOIN rent_model_web rmw ON rmw.model_id = ti.model_id AND rmw.lang = 'ru'
            ";
            $where  = ['ti.model_id IS NOT NULL',
                       'da.start_date < ?',
                       '(da.return_date > ? OR (da.return_date = 0 AND da.start_date < ?))'];

            if (!empty($razdelIds)) {
                $razdelSub    = $this->itemsInRazdelSubquery($razdelIds);
                $joins       .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                $joinParams = array_merge($joinParams, $razdelIds);
            }
            if (!$incCarn && $carnPh) {
                $where[]     = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $whereParams = array_merge($whereParams, $carnIds);
            }
            $whereSql = implode(' AND ', $where);

            // Params order matches `?` order: SELECT (`IF`, `LEAST`, `GREATEST`),
            // then JOIN (razdelSub), then WHERE.
            $sql = "
                SELECT ti.model_id,
                       rmw.l2_name AS model_name,
                       SUM(
                           GREATEST(0,
                               LEAST(IF(da.return_date > 0, da.return_date, ?), ?)
                               - GREATEST(da.start_date, ?)
                           )
                       ) AS rented_seconds,
                       COUNT(DISTINCT da.deal_id) AS deals
                FROM {$daSub} da
                {$joins}
                WHERE {$whereSql}
                GROUP BY ti.model_id, rmw.l2_name
            ";
            $rentedRows = DB::select($sql, array_merge($selectParams, $joinParams, $whereParams));

            $rentMap = [];
            foreach ($rentedRows as $r) {
                $rentMap[$r->model_id] = [
                    'model_name'     => $r->model_name,
                    'rented_seconds' => (float) ($r->rented_seconds ?? 0),
                    'deals'          => (int) $r->deals,
                ];
            }

            $sdSub = $this->unifiedSubDealsSubquery();
            $frSql = "
                SELECT ti.model_id,
                       ROUND(AVG(GREATEST(0, CAST(sd.`to` AS SIGNED) - CAST(sd.`from` AS SIGNED))) / 86400, 1) AS avg_first_rent_days
                FROM {$daSub} da
                JOIN {$sdSub} sd ON sd.deal_id = da.deal_id
                {$joins}
                WHERE {$whereSql}
                  AND sd.`type` IN ('first_rent', 'takeaway_plan')
                GROUP BY ti.model_id
            ";
            $frRows = DB::select($frSql, array_merge($joinParams, $whereParams));
            
            $firstRentMap = [];
            foreach ($frRows as $fr) {
                $firstRentMap[$fr->model_id] = (float) $fr->avg_first_rent_days;
            }

            $out = [];
            foreach ($allModelIds as $mid) {
                $uFrom = $unitsAtFrom[$mid] ?? 0;
                $uTo   = $unitsAtTo[$mid]   ?? 0;
                $avgU  = ($uFrom + $uTo) / 2;
                if ($avgU <= 0) continue;

                $entry = $rentMap[$mid] ?? ['model_name' => null, 'rented_seconds' => 0.0, 'deals' => 0];
                $rentDays = round($entry['rented_seconds'] / 86400, 2);
                $util     = round($entry['rented_seconds'] / ($avgU * $periodSeconds), 4);
                $avgRentalDaysPerDeal = $entry['deals'] > 0 ? round($rentDays / $entry['deals'], 1) : 0;
                $avgFirstRentDays     = isset($firstRentMap[$mid]) ? $firstRentMap[$mid] : null;

                $out[] = [
                    'model_id'        => (int) $mid,
                    'model_name'      => $entry['model_name'],
                    'units_at_from'   => $uFrom,
                    'units_at_to'     => $uTo,
                    'avg_units'       => $avgU,
                    'deals_in_period' => $entry['deals'],
                    'rented_days'     => $rentDays,
                    'avg_rental_days_per_deal' => $avgRentalDaysPerDeal,
                    'avg_first_rent_days' => $avgFirstRentDays,
                    'utilization'     => min(1.0, $util),
                ];
            }
            usort($out, fn($a, $b) => $b['utilization'] <=> $a['utilization']);
            return $out;
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /inventory/turnover?from&to&category&include_carnival
     */
    public function turnover(RangeRequest $request): JsonResponse
    {
        $from     = $request->fromTimestamp();
        $to       = $request->toTimestamp();
        $categories = $request->categories();
        $catStr     = implode(',', $categories);
        $incCarn  = $request->includeCarnival();

        $key = $this->cacheKey('inventory.turnover', [
            'from' => $from, 'to' => $to, 'cat' => $catStr, 'inc' => $incCarn ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($from, $to, $categories, $incCarn) {
            $razdelIds = $this->categoryToRazdelIds($categories);
            if (!in_array('all', $categories, true) && empty($razdelIds)) {
                return [];
            }

            $daSub   = $this->unifiedDealsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

            $catJoin   = '';
            $catWhere  = '';
            $catParams = [];
            if (!empty($razdelIds)) {
                $catJoin = '
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ';
                $catWhere    = 'AND rs.id_razdel = ?';
                $catParams = array_merge($catParams, $razdelIds);
            }
            $carnWhere = '';
            if (!$incCarn && $carnPh) {
                $carnWhere = "AND (tri.cat_id IS NULL OR tri.cat_id NOT IN ({$carnPh}))";
                $catParams  = array_merge($catParams, $carnIds);
            }

            return DB::select("
                SELECT tri.model_id,
                       rmw.l2_name AS model_name,
                       tri.cat_id,
                       tc.rent_cat_name AS cat_name,
                       COUNT(DISTINCT tri.item_id)  AS units,
                       COUNT(DISTINCT da.deal_id)   AS deals,
                       ROUND(SUM(da.r_paid + da.delivery_paid), 2) AS revenue_byn,
                       ROUND(COUNT(DISTINCT da.deal_id) / NULLIF(COUNT(DISTINCT tri.item_id), 0), 2) AS turnover
                FROM tovar_rent_items tri
                LEFT JOIN {$daSub} da ON da.item_inv_n = tri.item_inv_n
                                         AND da.cr_time BETWEEN ? AND ?
                LEFT JOIN rent_model_web rmw ON rmw.model_id          = tri.model_id AND rmw.lang = 'ru'
                LEFT JOIN tovar_rent_cat tc  ON tc.tovar_rent_cat_id  = tri.cat_id
                {$catJoin}
                WHERE 1=1
                  {$catWhere}
                  {$carnWhere}
                GROUP BY tri.model_id, rmw.l2_name, tri.cat_id, tc.rent_cat_name
                ORDER BY turnover DESC
            ", array_merge([$from, $to], $catParams));
        });

        return $this->envelope($request->queryEcho(), $rows);
    }

    /**
     * GET /inventory/idle?days=90&category&include_carnival
     */
    public function idle(Request $request): JsonResponse
    {
        $catInput = $request->input('category');
        if ($catInput === null || $catInput === '') {
            $categories = ['all'];
        } elseif (is_string($catInput)) {
            $categories = array_map('trim', explode(',', $catInput));
        } else {
            $categories = (array) $catInput;
        }

        $request->merge(['category' => $categories]);

        $request->validate([
            'days'             => 'integer|min:1|max:3650',
            'category'         => 'nullable|array',
            'category.*'       => 'string|in:' . implode(',', RangeRequest::CATEGORIES),
            'include_carnival' => 'nullable',
        ]);

        $categories = empty($categories) || in_array('all', $categories, true) ? ['all'] : array_values(array_unique($categories));
        $catStr = implode(',', $categories);

        $days     = (int) $request->get('days', 90);
        $cutoff   = time() - $days * 86400;
        $incRaw   = $request->input('include_carnival');
        $incCarn  = $incRaw === null
            ? true
            : !in_array(strtolower(trim((string) $incRaw)), ['0','false','no','off','n',''], true);

        $key = $this->cacheKey('inventory.idle', ['days' => $days, 'cat' => $catStr, 'inc' => $incCarn ? 1 : 0]);

        $rows = $this->cacheRemember($key, self::TTL_DEFAULT, function () use ($cutoff, $categories, $incCarn) {
            $razdelIds = $this->categoryToRazdelIds($categories);
            if (!in_array('all', $categories, true) && empty($razdelIds)) {
                return [];
            }

            $daSub   = $this->unifiedDealsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

            $catJoin   = '';
            $catWhere  = '';
            $catParams = [];
            if (!empty($razdelIds)) {
                $catJoin = '
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tri.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sc.id_sub_razdel
                ';
                $catWhere    = 'AND rs.id_razdel = ?';
                $catParams = array_merge($catParams, $razdelIds);
            }
            $carnWhere = '';
            if (!$incCarn && $carnPh) {
                $carnWhere = "AND (tri.cat_id IS NULL OR tri.cat_id NOT IN ({$carnPh}))";
                $catParams  = array_merge($catParams, $carnIds);
            }

            return DB::select("
                SELECT tri.model_id,
                       rmw.l2_name AS model_name,
                       tri.cat_id,
                       tc.rent_cat_name AS cat_name,
                       COUNT(DISTINCT tri.item_id) AS units,
                       MAX(da.cr_time)             AS last_deal_ts,
                       FROM_UNIXTIME(MAX(da.cr_time), '%Y-%m-%d') AS last_deal_date,
                       FLOOR((? - MAX(da.cr_time)) / 86400) AS days_idle
                FROM tovar_rent_items tri
                LEFT JOIN {$daSub} da ON da.item_inv_n = tri.item_inv_n
                LEFT JOIN rent_model_web rmw ON rmw.model_id         = tri.model_id AND rmw.lang = 'ru'
                LEFT JOIN tovar_rent_cat tc  ON tc.tovar_rent_cat_id = tri.cat_id
                {$catJoin}
                WHERE 1=1
                  {$catWhere}
                  {$carnWhere}
                GROUP BY tri.model_id, rmw.l2_name, tri.cat_id, tc.rent_cat_name
                HAVING (MAX(da.cr_time) IS NULL OR MAX(da.cr_time) < ?)
                ORDER BY (MAX(da.cr_time) IS NULL) DESC, MAX(da.cr_time) ASC
            ", array_merge([time()], $catParams, [$cutoff]));
        });

        return $this->envelope([
            'days'             => $days,
            'category'         => $catStr,
            'include_carnival' => $incCarn,
            'cutoff_iso'       => gmdate('Y-m-d\TH:i:s\Z', $cutoff),
        ], $rows);
    }
    /**
     * GET /inventory/pricing?category
     *
     * Returns rental prices by model with category mapping.
     * Designed for advertising bid calculation — includes section/subsection
     * URLs for ad landing pages and a calculated daily rate.
     */
    public function pricing(Request $request): JsonResponse
    {
        $key = $this->cacheKey('inventory.pricing', []);

        $rows = $this->cacheRemember($key, self::TTL_DEFAULT, function () {
            $data = DB::select("
                SELECT
                    rmw.model_id,
                    rmw.l2_name AS model_name,
                    rmw.page_addr AS model_url,
                    tc.tovar_rent_cat_id AS category_id,
                    tc.rent_cat_name AS category_name,
                    MIN(CASE WHEN ta.step = 'day' AND ta.kol_vo = 1 THEN ta.rent_amount END) AS price_day_1,
                    MIN(CASE WHEN ta.step = 'week' AND ta.kol_vo = 1 THEN ta.rent_amount END) AS price_week_1,
                    MIN(CASE WHEN ta.step = 'month' AND ta.kol_vo = 1 THEN ta.rent_amount END) AS price_month_1,
                    MIN(CASE
                        WHEN ta.step = 'day'   AND ta.kol_vo > 0 THEN ta.kol_vo
                        WHEN ta.step = 'week'  AND ta.kol_vo > 0 THEN ta.kol_vo * 7
                        WHEN ta.step = 'month' AND ta.kol_vo > 0 THEN ta.kol_vo * 30
                    END) AS min_rental_days,
                    COUNT(DISTINCT tri.item_id) AS active_units
                FROM rent_model_web rmw
                JOIN rent_tarif_act ta ON ta.model_id = rmw.model_id
                LEFT JOIN tovar_rent_items tri ON tri.model_id = rmw.model_id
                LEFT JOIN tovar_rent_cat tc ON tc.tovar_rent_cat_id = tri.cat_id
                WHERE rmw.lang = 'ru' AND rmw.status = 'show'
                GROUP BY rmw.model_id, rmw.l2_name, rmw.page_addr,
                         tc.tovar_rent_cat_id, tc.rent_cat_name
            ");

            // Category URL lookup (cached separately at META TTL)
            $catUrls = \Illuminate\Support\Facades\Cache::remember('mcp.cat_url_map', 86400, function () {
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

            return array_map(function ($r) use ($catUrls) {
                $pDay   = $r->price_day_1 !== null ? (float) $r->price_day_1 : null;
                $pWeek  = $r->price_week_1 !== null ? (float) $r->price_week_1 : null;
                $pMonth = $r->price_month_1 !== null ? (float) $r->price_month_1 : null;

                // Computed daily rate: actual day price, or week/7, or month/30
                $dailyRate = $pDay;
                if ($dailyRate === null && $pWeek !== null) {
                    $dailyRate = round($pWeek / 7, 2);
                }
                if ($dailyRate === null && $pMonth !== null) {
                    $dailyRate = round($pMonth / 30, 2);
                }

                $catId = $r->category_id !== null ? (int) $r->category_id : null;
                $urls  = $catId !== null ? ($catUrls[$catId] ?? null) : null;

                return [
                    'model_id'              => (int) $r->model_id,
                    'model_name'            => $r->model_name,
                    'model_url'             => $r->model_url,
                    'category_id'           => $catId,
                    'category_name'         => $r->category_name,
                    'section'               => $urls ? $urls['section'] : null,
                    'subsection'            => $urls ? $urls['subsection'] : null,
                    'price_per_day_byn'     => $pDay,
                    'price_per_week_byn'    => $pWeek,
                    'price_per_month_byn'   => $pMonth,
                    'price_per_day_calc_byn' => $dailyRate,
                    'min_rental_days'       => $r->min_rental_days !== null ? (int) $r->min_rental_days : null,
                    'active_units'          => (int) $r->active_units,
                ];
            }, $data);
        });

        return $this->envelope([], $rows);
    }
}
