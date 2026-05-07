<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * MCP Analytics API Controller
 *
 * Предоставляет данные для Claude Desktop через MCP-обёртку.
 * Все endpoints — только GET, без изменения данных.
 *
 * Маршруты: /api/mcp/v1/*
 * Middleware: mcp.token, mcp.geo, mcp.audit
 */
class McpAnalyticsController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────
    // GET /api/mcp/v1/health
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Проверка работоспособности API.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'version'   => config('mcp.version', '1.0.0'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/mcp/v1/inventory/free-tree
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Дерево свободного инвентаря: раздел → подраздел → категория → модели.
     *
     * Параметры: ?as_of=YYYY-MM-DD (по умолчанию — сегодня)
     *
     * «Свободен» = в tovar_rent_items нет записи с active_deal_id != 0
     *              ИЛИ status не в ('в аренде', 'бронь').
     */
    public function getFreeTree(Request $request): JsonResponse
    {
        $asOf = $request->date('as_of') ?? now();
        $ts   = $asOf->timestamp;

        $cacheKey = 'mcp.free_tree.' . $asOf->toDateString();
        $ttl      = config('mcp.cache_ttl', 300);

        $data = Cache::remember($cacheKey, $ttl, function () use ($ts) {
            // Свободные физические экземпляры, сгруппированные по модели
            $freeItems = DB::select("
                SELECT
                    tri.model_id,
                    COUNT(*) AS free_count
                FROM tovar_rent_items tri
                WHERE tri.active_deal_id = 0
                  AND (tri.status IS NULL OR tri.status NOT IN ('в аренде','бронь','ремонт','списан'))
                GROUP BY tri.model_id
            ");
            $freeMap = collect($freeItems)->keyBy('model_id');

            // Полное дерево: раздел → подраздел → категория → модели
            $rows = DB::select("
                SELECT
                    r.id_razdel          AS section_id,
                    r.name_razdel_text   AS section_name,
                    r.url_razdel_name    AS section_url,
                    sr.id_sub_razdel     AS subsection_id,
                    sr.name_sub_razdel_text AS subsection_name,
                    sr.url_sub_razdel_name  AS subsection_url,
                    tc.cat_id,
                    tc.cat_name,
                    tc.cat_url,
                    rmw.model_id,
                    rmw.l2_name          AS model_name,
                    rmw.page_addr        AS model_url,
                    rmw.status           AS model_status
                FROM razdel r
                JOIN razdel_subrazdel rs  ON rs.id_razdel    = r.id_razdel
                JOIN sub_razdel sr        ON sr.id_sub_razdel = rs.id_sub_razdel
                JOIN subrazdel_category sc ON sc.id_sub_razdel = sr.id_sub_razdel
                JOIN tovar_cats tc        ON tc.cat_id = sc.tovar_rent_cat_id
                JOIN rent_model_web rmw   ON rmw.lang = 'ru'
                JOIN tovar_list tl        ON tl.tovar_id = rmw.model_id
                    AND tl.tovar_cat = tc.cat_id
                WHERE rmw.status = 'show'
                ORDER BY r.razdel_order_num, sr.order_num_sub_razd, tc.cat_order, rmw.sort_n
            ");

            // Сборка дерева
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

            // Пересчитать агрегаты и переиндексировать массивы
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

        return response()->json([
            'as_of' => $asOf->toDateString(),
            'data'  => $data,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/mcp/v1/orders/stats
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Статистика заявок и броней за период.
     *
     * Параметры: ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD&group_by=day|week|month
     */
    public function getOrdersStats(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'group_by'  => 'in:day,week,month',
        ]);

        $dateFrom = $request->date('date_from')->startOfDay()->timestamp;
        $dateTo   = $request->date('date_to')->endOfDay()->timestamp;
        $groupBy  = $request->get('group_by', 'day');

        switch ($groupBy) {
            case 'week':
                $groupExpr = "DATE_FORMAT(FROM_UNIXTIME(cr_time), '%x-W%v')";
                break;
            case 'month':
                $groupExpr = "DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m')";
                break;
            default:
                $groupExpr = "DATE_FORMAT(FROM_UNIXTIME(cr_time), '%Y-%m-%d')";
        }

        // Заявки (rent_orders)
        $orders = DB::select("
            SELECT
                {$groupExpr} AS period,
                COUNT(*) AS total,
                SUM(CASE WHEN web = 1 THEN 1 ELSE 0 END) AS from_web,
                SUM(CASE WHEN status = 'одобрена' OR status = 'выдана' THEN 1 ELSE 0 END) AS approved
            FROM rent_orders
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
            ORDER BY period
        ", [$dateFrom, $dateTo]);

        // Брони карнавальных костюмов (karn_brons)
        $brons = DB::select("
            SELECT
                {$groupExpr} AS period,
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('выдана','одобрена') THEN 1 ELSE 0 END) AS approved
            FROM karn_brons
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
            ORDER BY period
        ", [$dateFrom, $dateTo]);

        // Новые сделки (rent_deals_act) — фактические аренды
        $deals = DB::select("
            SELECT
                {$groupExpr} AS period,
                COUNT(*) AS total,
                COUNT(DISTINCT client_id) AS unique_clients
            FROM rent_deals_act
            WHERE cr_time BETWEEN ? AND ?
            GROUP BY period
            ORDER BY period
        ", [$dateFrom, $dateTo]);

        return response()->json([
            'date_from'  => $request->get('date_from'),
            'date_to'    => $request->get('date_to'),
            'group_by'   => $groupBy,
            'orders'     => $orders,
            'carnival_bookings' => $brons,
            'deals'      => $deals,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/mcp/v1/deals/list
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Список новых сделок за период с ID клиента и адресом.
     *
     * Параметры: ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD&limit=100&offset=0
     *
     * Адрес клиента — для анализа географии (районы города).
     * Персональные данные (ФИО, телефон) не передаются.
     */
    public function getDealsList(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'limit'     => 'integer|min:1|max:500',
            'offset'    => 'integer|min:0',
        ]);

        $dateFrom = $request->date('date_from')->startOfDay()->timestamp;
        $dateTo   = $request->date('date_to')->endOfDay()->timestamp;
        $limit    = (int) $request->get('limit', 100);
        $offset   = (int) $request->get('offset', 0);

        $deals = DB::select("
            SELECT
                rda.deal_id,
                rda.client_id,
                rda.item_inv_n,
                rda.deal_status,
                FROM_UNIXTIME(rda.cr_time, '%Y-%m-%d') AS deal_date,
                rda.r_to_pay                            AS rental_amount_byn,
                rda.delivery_yn,
                rda.delivery_to_pay                     AS delivery_amount_byn,
                -- Адрес клиента (улица + дом, без ФИО)
                CONCAT_WS(', ', NULLIF(c.str,''), NULLIF(c.dom,''), NULLIF(c.kv,'')) AS client_address,
                c.city                                  AS client_city,
                -- Модель и категория товара
                tri.model_id,
                tri.cat_id,
                rmw.l2_name                             AS model_name
            FROM rent_deals_act rda
            LEFT JOIN clients c     ON c.client_id = rda.client_id
            LEFT JOIN tovar_rent_items tri ON tri.item_inv_n = rda.item_inv_n
            LEFT JOIN rent_model_web rmw   ON rmw.model_id = tri.model_id AND rmw.lang = 'ru'
            WHERE rda.cr_time BETWEEN ? AND ?
            ORDER BY rda.cr_time DESC
            LIMIT ? OFFSET ?
        ", [$dateFrom, $dateTo, $limit, $offset]);

        $total = DB::selectOne("
            SELECT COUNT(*) AS cnt FROM rent_deals_act
            WHERE cr_time BETWEEN ? AND ?
        ", [$dateFrom, $dateTo])->cnt;

        return response()->json([
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
            'total'     => (int) $total,
            'limit'     => $limit,
            'offset'    => $offset,
            'data'      => $deals,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/mcp/v1/inventory/profitability
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Рентабельность физических экземпляров товаров.
     *
     * Считает: выручка за все сделки - стоимость покупки = profit.
     * Периоды аренды — из rent_deals_act.
     *
     * Параметры: ?cat_id=INT&model_id=INT&min_deals=1
     */
    public function getItemProfitability(Request $request): JsonResponse
    {
        $request->validate([
            'cat_id'    => 'integer|min:1',
            'model_id'  => 'integer|min:1',
            'min_deals' => 'integer|min:0',
        ]);

        $catFilter   = $request->filled('cat_id')   ? 'AND tri.cat_id = ?' : '';
        $modelFilter = $request->filled('model_id') ? 'AND tri.model_id = ?' : '';
        $minDeals    = (int) $request->get('min_deals', 0);

        $params = [];
        if ($request->filled('cat_id'))   $params[] = $request->integer('cat_id');
        if ($request->filled('model_id')) $params[] = $request->integer('model_id');
        $params[] = $minDeals;

        $rows = DB::select("
            SELECT
                tri.item_id,
                tri.item_inv_n,
                tri.model_id,
                tri.cat_id,
                rmw.l2_name          AS model_name,
                tc.cat_name,
                tri.buy_date,
                -- Цена покупки в BYN (с учётом курса)
                ROUND(tri.buy_price * COALESCE(tri.exch_to_byr, 1), 2) AS buy_price_byn,
                FROM_UNIXTIME(tri.buy_date, '%Y-%m-%d')                 AS buy_date_fmt,
                -- Статистика аренды
                COUNT(rda.deal_id)                                       AS total_deals,
                COALESCE(SUM(rda.r_to_pay + rda.delivery_to_pay), 0)   AS total_revenue_byn,
                ROUND(
                    COALESCE(SUM(rda.r_to_pay + rda.delivery_to_pay), 0)
                    - ROUND(tri.buy_price * COALESCE(tri.exch_to_byr, 1), 2),
                2)                                                       AS profit_byn,
                tri.status           AS current_status
            FROM tovar_rent_items tri
            LEFT JOIN rent_deals_act rda  ON rda.item_inv_n = tri.item_inv_n
            LEFT JOIN rent_model_web rmw  ON rmw.model_id = tri.model_id AND rmw.lang = 'ru'
            LEFT JOIN tovar_cats tc        ON tc.cat_id = tri.cat_id
            WHERE 1=1
              {$catFilter}
              {$modelFilter}
            GROUP BY tri.item_id, tri.item_inv_n, tri.model_id, tri.cat_id, rmw.l2_name, tc.cat_name, tri.buy_date, tri.buy_price, tri.exch_to_byr, tri.status
            HAVING COUNT(rda.deal_id) >= ?
            ORDER BY profit_byn DESC
        ", $params);

        return response()->json([
            'filters' => [
                'cat_id'    => $request->get('cat_id'),
                'model_id'  => $request->get('model_id'),
                'min_deals' => $minDeals,
            ],
            'count' => count($rows),
            'data'  => $rows,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/mcp/v1/categories/performance
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Эффективность категорий: количество сделок, выручка, средний чек.
     *
     * Параметры: ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
     */
    public function getCategoriesPerformance(Request $request): JsonResponse
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
                COUNT(rda.deal_id)                               AS total_deals,
                COUNT(DISTINCT rda.client_id)                    AS unique_clients,
                ROUND(SUM(rda.r_to_pay + rda.delivery_to_pay), 2) AS total_revenue_byn,
                ROUND(AVG(rda.r_to_pay), 2)                      AS avg_deal_byn,
                COUNT(DISTINCT tri.model_id)                     AS active_models,
                COUNT(DISTINCT tri.item_inv_n)                   AS total_units
            FROM rent_deals_act rda
            JOIN tovar_rent_items tri ON tri.item_inv_n = rda.item_inv_n
            JOIN tovar_cats tc         ON tc.cat_id = tri.cat_id
            LEFT JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.cat_id
            LEFT JOIN sub_razdel sr         ON sr.id_sub_razdel = sc.id_sub_razdel
            LEFT JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sr.id_sub_razdel
            LEFT JOIN razdel r              ON r.id_razdel = rs.id_razdel
            WHERE rda.cr_time BETWEEN ? AND ?
            GROUP BY tc.cat_id, tc.cat_name, tc.cat_url, sr.name_sub_razdel_text, r.name_razdel_text
            ORDER BY total_revenue_byn DESC
        ", [$dateFrom, $dateTo]);

        return response()->json([
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
            'count'     => count($rows),
            'data'      => $rows,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/mcp/v1/clients/ltv
    // ──────────────────────────────────────────────────────────────────────

    /**
     * LTV клиентов: суммарная выручка, количество сделок, дата первой/последней.
     *
     * Параметры: ?min_deals=2&min_ltv=0&limit=200
     *
     * Персональные данные (ФИО, телефон, паспорт) не возвращаются.
     */
    public function getClientsLtv(Request $request): JsonResponse
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
                c.city                                          AS client_city,
                c.status                                        AS client_status,
                FROM_UNIXTIME(c.cr_time, '%Y-%m-%d')           AS client_since,
                COUNT(rda.deal_id)                              AS total_deals,
                ROUND(SUM(rda.r_to_pay + rda.delivery_to_pay), 2) AS ltv_byn,
                ROUND(AVG(rda.r_to_pay), 2)                    AS avg_deal_byn,
                MIN(FROM_UNIXTIME(rda.cr_time, '%Y-%m-%d'))    AS first_deal_date,
                MAX(FROM_UNIXTIME(rda.cr_time, '%Y-%m-%d'))    AS last_deal_date,
                -- Дни между первой и последней сделкой
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

        return response()->json([
            'filters' => [
                'min_deals' => $minDeals,
                'min_ltv'   => $minLtv,
            ],
            'count'  => count($rows),
            'limit'  => $limit,
            'offset' => $offset,
            'data'   => $rows,
        ]);
    }
}
