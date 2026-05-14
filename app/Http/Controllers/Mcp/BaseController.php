<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Shared functionality for MCP Analytics API controllers.
 *
 * Methodology (locked 2026-05-14 to match legacy admin reports /bb/reports.php,
 * /bb/sales_breakdown.php, /bb/dohrash2.php, /bb/cat_analysis.php):
 *
 *   - Deal-level aggregation reads UNION(rent_deals_act, rent_deals_arch).
 *     `rent_deals_arch` alone misses ~430 currently-open deals; the partition
 *     is strict (zero overlap on deal_id).
 *   - Revenue is summed at the sub-deal level: SUM(r_paid + delivery_paid)
 *     over UNION(rent_sub_deals_act, rent_sub_deals_arch), filtered by
 *     `acc_date` (accounting date — when the payment landed) rather than by
 *     the parent deal's `cr_time`. acc_date can be months after cr_time for
 *     deals that extend across periods.
 *   - Office attribution uses sub_deal.place + delivery_yn (per-payment),
 *     not deal.first_rent_place (per-deal). A deal opened at office 1 but
 *     extended at office 2 contributes to both.
 *   - Carnival items are detected via tovar_rent_cat.cat_type=1.  /finance/*
 *     accepts include_carnival=true|false (default true).
 *   - Historical inventory at date X = COUNT(tovar_rent_items WHERE
 *     buy_date <= X) + COUNT(tovar_rent_items_arch WHERE buy_date <= X AND
 *     arch_time >= X) — matches legacy tovar::getTovNumberForCatsForDate.
 *
 * Helpers below return SQL fragments (subquery strings) and caller-supplied
 * parameter arrays so individual controllers can compose final queries
 * without re-implementing the UNIONs.
 */
abstract class BaseController extends Controller
{
    /**
     * @param array<string,mixed> $query  Echoed input parameters
     * @param mixed               $data   Result rows (array, collection, scalar)
     * @param array<string,mixed> $meta   Extra metadata fields merged on top of defaults
     */
    protected function envelope(array $query, $data, array $meta = []): JsonResponse
    {
        $totalRows = is_array($data) || $data instanceof \Countable ? count($data) : null;

        $defaultMeta = [
            'total_rows'     => $totalRows,
            'currency'       => 'BYN',
            'data_freshness' => $this->dataFreshness(),
            'warnings'       => [],
        ];

        return response()->json([
            'query' => $query,
            'data'  => $data,
            'meta'  => array_merge($defaultMeta, $meta),
        ]);
    }

    /**
     * Most recent UNIX timestamp across the hot business tables, formatted as ISO8601.
     * Cached for 5 minutes to keep this cheap on every response.
     */
    protected function dataFreshness(): ?string
    {
        return Cache::remember('mcp.data_freshness', 300, function () {
            $row = DB::selectOne("
                SELECT GREATEST(
                    COALESCE((SELECT MAX(cr_time)  FROM rent_deals_act),  0),
                    COALESCE((SELECT MAX(cr_time)  FROM rent_deals_arch), 0),
                    COALESCE((SELECT MAX(acc_date) FROM rent_sub_deals_act),  0),
                    COALESCE((SELECT MAX(acc_date) FROM rent_sub_deals_arch), 0),
                    COALESCE((SELECT MAX(acc_date) FROM doh_rash),        0),
                    COALESCE((SELECT MAX(cr_time)  FROM karn_brons),      0),
                    COALESCE((SELECT MAX(cr_time)  FROM clients),         0),
                    COALESCE((SELECT MAX(cr_time)  FROM rent_orders_arch), 0)
                ) AS ts
            ");
            $ts = (int) ($row->ts ?? 0);
            return $ts > 0 ? gmdate('Y-m-d\TH:i:s\Z', $ts) : null;
        });
    }

    /**
     * Cache::remember when ttl > 0; pass-through when ttl is null/0.
     */
    protected function cacheRemember(string $key, ?int $ttl, \Closure $callback)
    {
        if (!$ttl) {
            return $callback();
        }
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Build a deterministic cache key from a prefix + scalar params.
     */
    protected function cacheKey(string $prefix, array $params): string
    {
        ksort($params);
        $flat = [];
        foreach ($params as $k => $v) {
            $flat[] = $k . '=' . (is_scalar($v) ? (string) $v : md5(serialize($v)));
        }
        return 'mcp.' . $prefix . '.' . implode('.', $flat);
    }

    /**
     * Default cache TTL hierarchy.
     */
    protected const TTL_META    = 86400; // 24h - metadata, rarely changes
    protected const TTL_HEAVY   = 3600;  // 1h  - large aggregations
    protected const TTL_DEFAULT = 300;   // 5m  - normal endpoints
    protected const TTL_NONE    = 0;     // disabled

    // ───────────────────────────────────────────────────────────────────
    // Unified subqueries — every business query goes through these.
    // ───────────────────────────────────────────────────────────────────

    /**
     * Subquery returning every deal row (one per deal) across BOTH
     * rent_deals_act (open deals) and rent_deals_arch (closed/archived deals).
     * Columns: deal_id, client_id, item_inv_n, start_date, return_date,
     * delivery_yn, delivery_to_pay, delivery_paid, r_to_pay, r_paid,
     * cr_time, first_rent_place, deal_status, planned_return_date,
     * last_sub_deal_ch_time, source ('act' | 'arch').
     *
     * Usage:
     *   $sub = $this->unifiedDealsSubquery();
     *   DB::select("SELECT COUNT(*) FROM {$sub} da WHERE da.cr_time BETWEEN ? AND ?", [...]);
     */
    protected function unifiedDealsSubquery(): string
    {
        return "(
            SELECT deal_id, client_id, item_inv_n, start_date, return_date,
                   delivery_yn, delivery_to_pay, delivery_paid, r_to_pay, r_paid,
                   cr_time, first_rent_place, deal_status, planned_return_date,
                   last_sub_deal_ch_time, 'act' AS source
            FROM rent_deals_act
            UNION ALL
            SELECT deal_id, client_id, item_inv_n, start_date, return_date,
                   delivery_yn, delivery_to_pay, delivery_paid, r_to_pay, r_paid,
                   cr_time, first_rent_place, deal_status, planned_return_date,
                   last_sub_deal_ch_time, 'arch' AS source
            FROM rent_deals_arch
        )";
    }

    /**
     * Subquery returning every sub-deal row (payment event) across BOTH
     * rent_sub_deals_act and rent_sub_deals_arch.
     * Columns include: deal_id, sub_deal_id, type, r_paid, delivery_paid,
     * r_to_pay, place, delivery_yn, r_payment_type, acc_date, cr_time,
     * `from`, `to`, source.
     *
     * Note: `delivery_yn` is typed varchar(16) here, so compare with the
     * legacy convention as a string ('1' vs '0' / empty).
     */
    protected function unifiedSubDealsSubquery(): string
    {
        return "(
            SELECT sub_deal_id, deal_id, `type`, r_paid, delivery_paid, r_to_pay,
                   place, delivery_yn, r_payment_type, acc_date, cr_time,
                   `from`, `to`, 'act' AS source
            FROM rent_sub_deals_act
            UNION ALL
            SELECT sub_deal_id, deal_id, `type`, r_paid, delivery_paid, r_to_pay,
                   place, delivery_yn, r_payment_type, acc_date, cr_time,
                   `from`, `to`, 'arch' AS source
            FROM rent_sub_deals_arch
        )";
    }

    /**
     * Subquery returning catalog item rows across BOTH tovar_rent_items
     * (currently in catalog) and tovar_rent_items_arch (sold off / lost).
     * Used to resolve item_inv_n → cat_id / model_id when deals reference
     * items that have since been removed from the active catalog.
     */
    protected function unifiedItemsSubquery(): string
    {
        return "(
            SELECT item_inv_n, cat_id, model_id, buy_date, NULL AS arch_time
            FROM tovar_rent_items
            UNION ALL
            SELECT item_inv_n, cat_id, model_id, buy_date, arch_time
            FROM tovar_rent_items_arch
        )";
    }

    /**
     * IDs of carnival categories (tovar_rent_cat.cat_type = 1). Cached 24h.
     * Returns a sorted array of integers, possibly empty.
     */
    protected function carnivalCatIds(): array
    {
        return $this->cacheRemember('mcp.carnival_cat_ids', self::TTL_META, function () {
            $rows = DB::select("SELECT tovar_rent_cat_id FROM tovar_rent_cat WHERE cat_type = 1");
            $ids = array_map(fn($r) => (int) $r->tovar_rent_cat_id, $rows);
            sort($ids);
            return $ids;
        });
    }

    /**
     * SQL fragment to be inserted in a WHERE clause that constrains a
     * tovar_rent_items.cat_id (or compatible) column to carnival or non-
     * carnival categories. Returns empty string + empty params when no
     * carnival filter is needed.
     *
     * @param  bool       $includeCarnival
     * @param  string     $catIdColumn  e.g. "tri.cat_id"
     * @return array{0:string,1:array<int>}  [sqlFragment, params]
     */
    protected function carnivalFilterClause(bool $includeCarnival, string $catIdColumn): array
    {
        if ($includeCarnival) {
            return ['', []];
        }
        $ids = $this->carnivalCatIds();
        if (empty($ids)) {
            return ['', []];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return [" AND ({$catIdColumn} IS NULL OR {$catIdColumn} NOT IN ({$placeholders})) ", $ids];
    }

    /**
     * Inventory count at a given UNIX timestamp, optionally narrowed by
     * razdel and by carnival/non-carnival filter.
     *
     * Formula matches legacy tovar::getTovNumberForCatsForDate:
     *   active items: buy_date <= ts
     *   archived items: buy_date <= ts AND arch_time >= ts
     *
     * @param  int       $ts
     * @param  ?int      $razdel           id_razdel filter, null = all
     * @param  bool      $includeCarnival
     * @return int
     */
    protected function inventoryCountAtDate(int $ts, ?int $razdel = null, bool $includeCarnival = true): int
    {
        $key = $this->cacheKey('inventory.count_at_date', [
            'ts' => $ts, 'razdel' => $razdel ?? 'all', 'carn' => $includeCarnival ? 1 : 0,
        ]);
        return (int) $this->cacheRemember($key, self::TTL_HEAVY, function () use ($ts, $razdel, $includeCarnival) {
            [$razdelJoin, $razdelWhere, $razdelParams] = $this->razdelFilterFragment($razdel, 'cat_id');
            [$carnWhere, $carnParams] = $this->carnivalFilterClause($includeCarnival, 'cat_id');

            $activeSql = "
                SELECT COUNT(*) AS num
                FROM tovar_rent_items tri
                {$razdelJoin}
                WHERE tri.buy_date <= ?
                  {$razdelWhere}
                  {$carnWhere}
            ";
            $archSql = "
                SELECT COUNT(*) AS num
                FROM tovar_rent_items_arch tria
                LEFT JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tria.cat_id
                LEFT JOIN razdel_subrazdel rs   ON rs.id_sub_razdel    = sc.id_sub_razdel
                WHERE tria.buy_date <= ?
                  AND tria.arch_time >= ?
                  " . ($razdel !== null ? "AND rs.id_razdel = ? " : "") . "
                  " . ($carnWhere ? "AND (tria.cat_id IS NULL OR tria.cat_id NOT IN ("
                        . implode(',', array_fill(0, count($carnParams), '?')) . "))" : '') . "
            ";

            $activeParams = array_merge([$ts], $razdelParams, $carnParams);
            $archParams   = array_merge([$ts, $ts], $razdel !== null ? [$razdel] : [], $carnParams);

            $a = DB::selectOne($activeSql, $activeParams);
            $b = DB::selectOne($archSql,   $archParams);
            return (int) ($a->num ?? 0) + (int) ($b->num ?? 0);
        });
    }

    /**
     * Razdel filter fragment used to constrain a tri.cat_id-based query
     * to one razdel (business category). Returns [join, whereClause,
     * paramsForWhere].
     *
     * @return array{0:string,1:string,2:array<int>}
     */
    protected function razdelFilterFragment(?int $razdel, string $catIdColumn): array
    {
        if ($razdel === null) {
            return ['', '', []];
        }
        $join  = "
            LEFT JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = {$catIdColumn}
            LEFT JOIN razdel_subrazdel rs   ON rs.id_sub_razdel    = sc.id_sub_razdel
        ";
        $where = ' AND rs.id_razdel = ? ';
        return [$join, $where, [$razdel]];
    }

    /**
     * Subquery returning DISTINCT item_inv_n values that belong to the given
     * razdel (business category). Use this instead of joining
     * subrazdel_category → razdel_subrazdel directly when the outer query
     * also aggregates sums — those two tables are many-to-many through
     * tovar_rent_cat, so a naïve join double-counts revenue when a cat
     * appears in multiple sub-razdels.
     *
     * Caller still supplies the razdel id as the only bound parameter.
     */
    protected function itemsInRazdelSubquery(): string
    {
        $itSub = $this->unifiedItemsSubquery();
        return "(
            SELECT DISTINCT ti.item_inv_n
            FROM {$itSub} ti
            JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = ti.cat_id
            JOIN razdel_subrazdel rs   ON rs.id_sub_razdel    = sc.id_sub_razdel
            WHERE rs.id_razdel = ?
        )";
    }

    /**
     * Map RangeRequest CATEGORIES enum → razdel.id_razdel.
     * Returns null when the category has no current razdel (e.g. 'tools')
     * or the slug doesn't match.
     */
    protected function categoryToRazdelId(string $category): ?int
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
