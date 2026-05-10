<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reference / metadata endpoints under /api/mcp/v1/meta/*.
 *
 * All Meta endpoints are cacheable for 24h — the data behind them
 * (catalog sections, expense item dictionary, office list) is rarely edited.
 */
class MetaController extends BaseController
{
    /**
     * GET /meta/categories
     *
     * Two-level reference list:
     * - business_categories: the seven enum keys consumed by `?category=`
     *   on other endpoints (children, costumes, medical, cleaning, sports,
     *   tools, all), each mapped to its razdel row when one exists.
     * - detailed_categories: rows from tovar_cats for fine-grained slicing.
     */
    public function categories(Request $request): JsonResponse
    {
        $data = $this->cacheRemember('mcp.meta.categories', self::TTL_META, function () {
            $razdels = DB::select("
                SELECT id_razdel, name_razdel_text, url_razdel_name, razdel_order_num
                FROM razdel
                ORDER BY razdel_order_num
            ");
            $byUrl = [];
            foreach ($razdels as $r) {
                $byUrl[$r->url_razdel_name] = $r;
            }

            // Mapping of MCP business-category enum → razdel.url_razdel_name.
            // Stays in sync with RangeRequest::CATEGORIES.
            $mapping = [
                'children' => ['url' => 'prokat-detskih-tovarov', 'name' => 'Детские товары'],
                'costumes' => ['url' => 'karnavalnye-kostyumy',   'name' => 'Карнавальные костюмы'],
                'medical'  => ['url' => 'medical-prokat',         'name' => 'Красота и здоровье'],
                'cleaning' => ['url' => 'prokat-uborka',          'name' => 'Оборудование для уборки'],
                'sports'   => ['url' => 'prokat-sports',          'name' => 'Спорт и отдых'],
                // No razdel row in current catalog — see decisions_log.md D-OPEN-TOOLS.
                'tools'    => ['url' => null,                     'name' => 'Стройинструмент'],
            ];

            $business = [];
            foreach ($mapping as $key => $info) {
                $row = $info['url'] !== null ? ($byUrl[$info['url']] ?? null) : null;
                $business[] = [
                    'key'       => $key,
                    'name'      => $row->name_razdel_text ?? $info['name'],
                    'url_slug'  => $info['url'],
                    'razdel_id' => $row->id_razdel ?? null,
                    'available' => $row !== null,
                ];
            }

            $detailed = DB::select("
                SELECT cat_id, cat_name, cat_folder, cat_url, cat_order, razdel
                FROM tovar_cats
                ORDER BY cat_order, cat_id
            ");

            return [
                'business_categories' => $business,
                'detailed_categories' => $detailed,
            ];
        });

        return $this->envelope([], $data, [
            'total_rows' => count($data['business_categories']) + count($data['detailed_categories']),
        ]);
    }

    /**
     * GET /meta/locations
     *
     * offices (active and historical) augmented with computed first_deal_date,
     * last_deal_date, total_deals, total_revenue_byn aggregated from
     * rent_deals_arch grouped by first_rent_place.
     */
    public function locations(Request $request): JsonResponse
    {
        $rows = $this->cacheRemember('mcp.meta.locations', self::TTL_META, function () {
            return DB::select("
                SELECT
                    o.id,
                    o.type,
                    o.number,
                    o.name,
                    o.short_address,
                    o.active,
                    FROM_UNIXTIME(MIN(NULLIF(da.cr_time, 0)), '%Y-%m-%d') AS first_deal_date,
                    FROM_UNIXTIME(MAX(NULLIF(da.cr_time, 0)), '%Y-%m-%d') AS last_deal_date,
                    COUNT(da.arch_deal_id)                                AS total_deals,
                    ROUND(COALESCE(SUM(da.r_paid + da.delivery_paid), 0), 2) AS total_revenue_byn
                FROM offices o
                LEFT JOIN rent_deals_arch da ON da.first_rent_place = o.id
                GROUP BY o.id, o.type, o.number, o.name, o.short_address, o.active
                ORDER BY o.id
            ");
        });

        return $this->envelope([], $rows);
    }

    /**
     * GET /meta/expense-items
     *
     * Dictionary of expense item codes used in doh_rash.type2 (when type1='rash').
     * Only is_active=1 are returned so the agent does not waste time on
     * deprecated codes.
     */
    public function expenseItems(Request $request): JsonResponse
    {
        $rows = $this->cacheRemember('mcp.meta.expense_items', self::TTL_META, function () {
            return DB::select("
                SELECT ri_id AS id, ri_code AS code, ri_text AS name, ri_order AS sort_order, bank_yn
                FROM rash_items
                WHERE is_active = 1
                ORDER BY ri_order
            ");
        });

        return $this->envelope([], $rows);
    }

    /**
     * GET /meta/income-items
     *
     * Dictionary of income item codes used in doh_rash.type2 (when type1='doh').
     */
    public function incomeItems(Request $request): JsonResponse
    {
        $rows = $this->cacheRemember('mcp.meta.income_items', self::TTL_META, function () {
            return DB::select("
                SELECT rd_id AS id, rd_code AS code, rd_text AS name, rd_order AS sort_order, bank_yn
                FROM doh_items
                WHERE is_active = 1
                ORDER BY rd_order
            ");
        });

        return $this->envelope([], $rows);
    }

    /**
     * GET /meta/data-freshness
     *
     * Latest modification timestamp per hot table, plus the global maximum.
     * Useful for the agent to decide whether cached results are still fresh
     * before issuing heavy aggregations.
     */
    public function dataFreshnessEndpoint(Request $request): JsonResponse
    {
        $row = $this->cacheRemember('mcp.meta.data_freshness_detail', self::TTL_DEFAULT, function () {
            return DB::selectOne("
                SELECT
                    (SELECT MAX(cr_time)  FROM rent_deals_arch)  AS rent_deals_arch_ts,
                    (SELECT MAX(acc_date) FROM doh_rash)         AS doh_rash_ts,
                    (SELECT MAX(cr_time)  FROM karn_brons)       AS karn_brons_ts,
                    (SELECT MAX(cr_time)  FROM clients)          AS clients_ts,
                    (SELECT MAX(cr_time)  FROM rent_orders_arch) AS rent_orders_arch_ts
            ");
        });

        $tables = [
            'rent_deals_arch'  => $this->iso((int) ($row->rent_deals_arch_ts  ?? 0)),
            'doh_rash'         => $this->iso((int) ($row->doh_rash_ts         ?? 0)),
            'karn_brons'       => $this->iso((int) ($row->karn_brons_ts       ?? 0)),
            'clients'          => $this->iso((int) ($row->clients_ts          ?? 0)),
            'rent_orders_arch' => $this->iso((int) ($row->rent_orders_arch_ts ?? 0)),
        ];

        $max = 0;
        foreach ((array) $row as $ts) {
            $max = max($max, (int) $ts);
        }

        return $this->envelope([], [
            'tables' => $tables,
            'max'    => $this->iso($max),
        ], ['total_rows' => count($tables)]);
    }

    private function iso(int $ts): ?string
    {
        return $ts > 0 ? gmdate('Y-m-d\TH:i:s\Z', $ts) : null;
    }
}
