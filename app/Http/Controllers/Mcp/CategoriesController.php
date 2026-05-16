<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Category-level analytics under /api/mcp/v1/categories/*.
 *
 * Methodology (matches legacy /bb/sales_breakdown.php category split):
 *   - Revenue = SUM(r_paid + delivery_paid) over UNION(sub_deals_act, sub_deals_arch)
 *     by acc_date, joined to deals → items → categories.
 *   - `include_carnival` (default true) toggles carnival categories
 *     (tovar_rent_cat.cat_type=1).
 */
class CategoriesController extends BaseController
{
    /**
     * GET /categories/performance?date_from=&date_to=&include_carnival
     */
    public function performance(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'        => 'required|date',
            'date_to'          => 'required|date|after_or_equal:date_from',
            'include_carnival' => 'nullable',
        ]);

        $dateFrom = $request->date('date_from')->startOfDay()->timestamp;
        $dateTo   = $request->date('date_to')->endOfDay()->timestamp;
        $incRaw   = $request->input('include_carnival');
        $incCarn  = $incRaw === null
            ? true
            : !in_array(strtolower(trim((string) $incRaw)), ['0','false','no','off','n',''], true);

        $sdSub   = $this->unifiedSubDealsSubquery();
        $daSub   = $this->unifiedDealsSubquery();
        $itSub   = $this->unifiedItemsSubquery();
        $carnIds = $this->carnivalCatIds();
        $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

        // Pre-aggregate SUM-prone metrics (revenue, issuance_events) at cat_id
        // level via a derived per-item table so the M:N expansion of
        // subrazdel_category × razdel_subrazdel cannot inflate them.
        // COUNT(DISTINCT *) and AVG() are unaffected by the expansion (DISTINCT
        // cancels duplication; AVG of uniformly duplicated rows is unchanged).
        $perItemWhere  = ['sd.acc_date BETWEEN ? AND ?'];
        $perItemParams = [$dateFrom, $dateTo];
        if (!$incCarn && $carnPh) {
            $perItemWhere[]  = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
            $perItemParams   = array_merge($perItemParams, $carnIds);
        }
        $perItemWhereSql = implode(' AND ', $perItemWhere);

        // SUM-prone metrics grouped by cat_id (no M:N inflation).
        $sumsByCat = DB::select("
            SELECT per_item.cat_id,
                   ROUND(SUM(per_item.rev), 2)        AS total_revenue_byn,
                   SUM(per_item.issuance_events)      AS issuance_events
            FROM (
                SELECT ti.cat_id,
                       SUM(sd.r_paid + sd.delivery_paid) AS rev,
                       SUM(CASE WHEN sd.`type` IN ('first_rent','takeaway_plan') THEN 1 ELSE 0 END) AS issuance_events
                FROM {$sdSub} sd
                JOIN {$daSub} da ON da.deal_id = sd.deal_id
                JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
                WHERE {$perItemWhereSql}
                GROUP BY da.item_inv_n, ti.cat_id
            ) per_item
            GROUP BY per_item.cat_id
        ", $perItemParams);
        $sumsMap = [];
        foreach ($sumsByCat as $s) {
            $sumsMap[(int) $s->cat_id] = [
                'total_revenue_byn' => (float) $s->total_revenue_byn,
                'issuance_events'   => (int)   $s->issuance_events,
            ];
        }

        // COUNT(DISTINCT *) / AVG() metrics — safe to query with the legacy
        // M:N join chain because the inflation factor cancels.
        $where  = ['sd.acc_date BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];
        if (!$incCarn && $carnPh) {
            $where[] = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
            $params  = array_merge($params, $carnIds);
        }
        $whereSql = implode(' AND ', $where);

        $rows = DB::select("
            SELECT
                tc.cat_id,
                tc.cat_name,
                tc.cat_url,
                sr.name_sub_razdel_text AS subsection_name,
                r.name_razdel_text      AS section_name,
                COUNT(DISTINCT sd.deal_id)                              AS total_deals,
                COUNT(DISTINCT da.client_id)                            AS unique_clients,
                ROUND(AVG(sd.r_paid),                       2)          AS avg_payment_byn,
                COUNT(DISTINCT ti.model_id)                             AS active_models,
                COUNT(DISTINCT ti.item_inv_n)                           AS units_rented
            FROM {$sdSub} sd
            JOIN {$daSub} da ON da.deal_id = sd.deal_id
            JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
            JOIN tovar_cats tc              ON tc.cat_id = ti.cat_id
            LEFT JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tc.cat_id
            LEFT JOIN sub_razdel sr         ON sr.id_sub_razdel = sc.id_sub_razdel
            LEFT JOIN razdel_subrazdel rs   ON rs.id_sub_razdel = sr.id_sub_razdel
            LEFT JOIN razdel r              ON r.id_razdel = rs.id_razdel
            WHERE {$whereSql}
            GROUP BY tc.cat_id, tc.cat_name, tc.cat_url, sr.name_sub_razdel_text, r.name_razdel_text
        ", $params);

        // Merge SUM metrics in PHP; one (cat × subsection × section) row gets
        // the cat-level totals (intentional — same item appears in multiple
        // (subsection, section) cells but its revenue is logically the same).
        $rows = array_map(function ($r) use ($sumsMap) {
            $cid = (int) $r->cat_id;
            $r->total_revenue_byn = $sumsMap[$cid]['total_revenue_byn'] ?? 0.0;
            $r->issuance_events   = $sumsMap[$cid]['issuance_events']   ?? 0;
            return $r;
        }, $rows);
        usort($rows, fn($a, $b) => $b->total_revenue_byn <=> $a->total_revenue_byn);

        return $this->envelope([
            'date_from'        => $request->get('date_from'),
            'date_to'          => $request->get('date_to'),
            'include_carnival' => $incCarn,
        ], $rows);
    }

    /**
     * GET /categories/seasonality?category=&years=5&include_carnival
     *
     * Monthly seasonality profile aggregated over the last `years` years.
     */
    public function seasonality(Request $request): JsonResponse
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
            'category'         => 'nullable|array',
            'category.*'       => 'string|in:' . implode(',', RangeRequest::CATEGORIES),
            'years'            => 'integer|min:1|max:11',
            'include_carnival' => 'nullable',
        ]);

        $categories = empty($categories) || in_array('all', $categories, true) ? ['all'] : array_values(array_unique($categories));
        $catStr = implode(',', $categories);

        $years    = (int) $request->get('years', 5);
        $cutoff   = strtotime("-{$years} years");
        $incRaw   = $request->input('include_carnival');
        $incCarn  = $incRaw === null
            ? true
            : !in_array(strtolower(trim((string) $incRaw)), ['0','false','no','off','n',''], true);

        $key = $this->cacheKey('categories.seasonality', [
            'cat'   => $catStr,
            'years' => $years,
            'inc'   => $incCarn ? 1 : 0,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($categories, $cutoff, $incCarn) {
            $razdelIds = $this->categoryToRazdelIds($categories);
            if (!in_array('all', $categories, true) && empty($razdelIds)) {
                return [];
            }

            $sdSub   = $this->unifiedSubDealsSubquery();
            $daSub   = $this->unifiedDealsSubquery();
            $itSub   = $this->unifiedItemsSubquery();
            $carnIds = $this->carnivalCatIds();
            $carnPh  = $carnIds ? implode(',', array_fill(0, count($carnIds), '?')) : null;

            // razdel param → JOIN via itemsInRazdelSubquery (avoids M:N inflation
            // of SUM(r_paid+delivery_paid)). Split params: join then where.
            $joinParams  = [];
            $whereParams = [$cutoff];
            $joins = "
                LEFT JOIN {$daSub} da ON da.deal_id = sd.deal_id
                LEFT JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
            ";
            $where  = ['sd.acc_date >= ?'];
            if (!empty($razdelIds)) {
                $razdelSub    = $this->itemsInRazdelSubquery($razdelIds);
                $joins       .= " JOIN {$razdelSub} irz ON irz.item_inv_n = da.item_inv_n ";
                $joinParams   = array_merge($joinParams, $razdelIds);
            }
            if (!$incCarn && $carnPh) {
                $where[]     = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                $whereParams = array_merge($whereParams, $carnIds);
            }
            $whereSql = implode(' AND ', $where);

            $monthly = DB::select("
                SELECT MONTH(FROM_UNIXTIME(sd.acc_date))                AS month_num,
                       COUNT(DISTINCT sd.deal_id)                       AS deals,
                       ROUND(SUM(sd.r_paid + sd.delivery_paid), 2)      AS revenue_byn,
                       COUNT(DISTINCT YEAR(FROM_UNIXTIME(sd.acc_date))) AS years_covered
                FROM {$sdSub} sd
                {$joins}
                WHERE {$whereSql}
                GROUP BY month_num
                ORDER BY month_num
            ", array_merge($joinParams, $whereParams));

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
                $deals = (int) $r->deals;
                $yrs   = max(1, (int) $r->years_covered);
                $out[] = [
                    'month_num'         => (int) $r->month_num,
                    'month_name'        => $monthNames[(int) $r->month_num] ?? null,
                    'deals'             => $deals,
                    'avg_deals_per_year' => round($deals / $yrs, 2),
                    'revenue_byn'       => (float) $r->revenue_byn,
                    'years_covered'     => $yrs,
                    'seasonality_index' => round($deals / $avgPerSlot, 3),
                ];
            }
            return $out;
        });

        return $this->envelope([
            'category'         => $catStr,
            'years'            => $years,
            'include_carnival' => $incCarn,
        ], $rows);
    }
}
