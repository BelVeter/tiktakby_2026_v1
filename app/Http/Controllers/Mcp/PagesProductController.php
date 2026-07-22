<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * SEO fields of L3 product pages (`rent_model_web`).
 *
 * Canonical URL resolution (important): the page URL is built through the
 * single-parent chain the site itself uses in ModelWeb::getUrlPageAddress —
 *
 *     rent_model_web.model_id
 *       -> tovar_rent.tovar_rent_cat_id
 *       -> tovar_rent_cat.main_sub_razdel_id
 *       -> sub_razdel.main_razdel_id
 *       -> razdel
 *
 * NOT through the many-to-many `subrazdel_category` × `razdel_subrazdel` chain.
 * A model whose category is linked to several subrazdels renders under several
 * URLs, but only this one carries <link rel="canonical"> and only this one is
 * in sitemap.xml. Resolving through the M:N chain produced a non-canonical URL
 * for ~14% of models and NULL for ~130 that do have a canonical URL.
 */
class PagesProductController extends BaseController
{
    /** API field => rent_model_web column. Order defines seo_score/summary order. */
    private const FIELD_MAP = [
        'meta_title'       => 'title',
        'meta_description' => 'meta_description',
        'h1'               => 'item_name_main',
        'l2_name'          => 'l2_name',
        'main_pic_alt'     => 'm_pic_alt',
        'main_pic_title'   => 'm_a_title',
        'l2_pic_alt'       => 'l2_alt',
        'description'      => 'main_descr',
        'breadcrumb_name'  => 'breadcrumbs_name',
        'faq'              => 'faq',
    ];

    /**
     * Fields rendered inside an HTML attribute or <title>; markup in them
     * breaks the tag. bb/classes/ModelWeb::setMetaDescription strips quotes on
     * the admin side, the API used to write raw.
     */
    private const PLAIN_TEXT_FIELDS = [
        'meta_title', 'meta_description', 'main_pic_alt',
        'main_pic_title', 'l2_pic_alt', 'breadcrumb_name',
    ];

    private const WRITE_RULES = [
        'meta_title'       => 'nullable|string|max:70',
        'meta_description' => 'nullable|string|max:160',
        'h1'               => 'nullable|string|max:255',
        'l2_name'          => 'nullable|string|max:255',
        'main_pic_alt'     => 'nullable|string|max:125',
        'main_pic_title'   => 'nullable|string|max:125',
        'l2_pic_alt'       => 'nullable|string|max:125',
        'description'      => 'nullable|string',
        'breadcrumb_name'  => 'nullable|string|max:100',
        'faq'              => 'nullable|array',
        'faq.*.question'   => 'required_with:faq|string|max:500',
        'faq.*.answer'     => 'required_with:faq|string|max:5000',
    ];

    private const MAX_BULK_ITEMS = 100;

    /**
     * GET /api/mcp/v1/pages/product
     *
     * Filters, pagination and `fields=full` exist so a content pass over ~1000
     * pages does not need one request per page: the endpoint is behind
     * throttle:60,1, and reading every product body used to cost 982 requests.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'razdel'     => 'nullable|string|max:100',
            'subrazdel'  => 'nullable|string|max:100',
            'category'   => 'nullable|string|max:100',
            'search'     => 'nullable|string|max:100',
            'missing'    => 'nullable|string|max:255',
            'status'     => 'nullable|in:show,not_show,all',
            'has_stock'  => 'nullable|boolean',
            'indexable'  => 'nullable|boolean',
            'fields'     => 'nullable|in:summary,full',
            'summary'    => 'nullable|boolean',
            'page'       => 'nullable|integer|min:1',
            'per_page'   => 'nullable|integer|min:1|max:500',
        ]);

        $missing = $this->parseMissing($validated['missing'] ?? null);
        if ($missing === false) {
            return response()->json([
                'error'   => 'bad_request',
                'message' => 'Unknown field in `missing`. Allowed: ' . implode(', ', array_keys(self::FIELD_MAP)),
            ], 422);
        }

        $rows = $this->fetchRows([
            'razdel'    => $validated['razdel']    ?? null,
            'subrazdel' => $validated['subrazdel'] ?? null,
            'category'  => $validated['category']  ?? null,
            'search'    => $validated['search']    ?? null,
            'status'    => $validated['status'] ?? 'show',
            // boolean() normalises "0"/"false"/"off"; validated() would hand
            // back the raw string, and the string "false" is truthy in PHP.
            'has_stock' => $request->has('has_stock') ? $request->boolean('has_stock') : null,
            'indexable' => $request->has('indexable') ? $request->boolean('indexable') : null,
            'missing'   => $missing,
        ]);

        $full     = ($validated['fields'] ?? 'summary') === 'full';
        $perPage  = (int) ($validated['per_page'] ?? 100);
        $page     = (int) ($validated['page'] ?? 1);
        $total    = count($rows);
        $pageRows = array_slice($rows, ($page - 1) * $perPage, $perPage);

        $data = array_map(fn ($r) => $this->presentRow($r, $full), $pageRows);

        $meta = [
            'total_rows'    => $total,
            'returned_rows' => count($data),
            'page'          => $page,
            'per_page'      => $perPage,
            'summary'       => $this->buildSummary($rows, $request->boolean('summary')),
        ];

        return $this->envelope($request->query(), $data, $meta);
    }

    /**
     * GET /api/mcp/v1/pages/product/{slug}
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $rows = $this->fetchRows(['slug' => $slug, 'status' => 'all']);

        if (empty($rows)) {
            return $this->notFound($slug);
        }
        if (count($rows) > 1) {
            return $this->slugConflict($slug, $rows);
        }

        $row      = $rows[0];
        $current  = $this->currentValues($row);
        $warnings = [];

        // <title>@yield('page-title')</title> has no fallback on L3 (unlike L2,
        // where PagesListingController advertises a generated default), so an
        // empty `title` column ships an empty <title> tag.
        if ($current['meta_title'] === null) {
            $warnings[] = [
                'code'    => 'empty_meta_title',
                'message' => 'meta_title is empty — the L3 template has no fallback, the page renders <title></title>.',
            ];
        }
        if ($row->full_url === null) {
            $warnings[] = [
                'code'    => 'no_canonical_url',
                'message' => 'The model has no canonical URL (category is not wired to a main subrazdel/razdel), so the page is unreachable and absent from sitemap.xml.',
            ];
        }

        return $this->envelope($request->query(), $this->presentRow($row, true), ['warnings' => $warnings]);
    }

    /**
     * PATCH /api/mcp/v1/pages/product/{slug}
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $rows = $this->fetchRows(['slug' => $slug, 'status' => 'all']);

        if (empty($rows)) {
            return $this->notFound($slug);
        }
        if (count($rows) > 1) {
            return $this->slugConflict($slug, $rows);
        }

        $validated = $request->validate(self::WRITE_RULES);
        $outcome   = $this->applyToRow($rows[0], $validated);

        if ($outcome['status'] === 'no_fields') {
            return response()->json([
                'error'   => 'bad_request',
                'message' => 'No valid fields provided for update.',
            ], 400);
        }

        return $this->show($request, $slug);
    }

    /**
     * PATCH /api/mcp/v1/pages/product/bulk
     *
     * Partial success by design: each item reports its own status so one bad
     * slug in a batch of 100 does not discard 99 good writes.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'pages'        => 'required|array|min:1|max:' . self::MAX_BULK_ITEMS,
            'pages.*.slug' => 'required|string|max:255',
        ]);

        $results = [];
        foreach ($request->input('pages') as $i => $item) {
            $slug = (string) ($item['slug'] ?? '');

            $fields    = array_diff_key($item, ['slug' => null]);
            $validator = Validator::make($fields, self::WRITE_RULES);
            if ($validator->fails()) {
                $results[] = $this->bulkResult($i, $slug, 'invalid', [], $validator->errors()->toArray());
                continue;
            }

            $rows = $this->fetchRows(['slug' => $slug, 'status' => 'all']);
            if (empty($rows)) {
                $results[] = $this->bulkResult($i, $slug, 'not_found');
                continue;
            }
            if (count($rows) > 1) {
                $results[] = $this->bulkResult($i, $slug, 'conflict');
                continue;
            }

            $outcome = $this->applyToRow($rows[0], $validator->validated());
            if ($outcome['status'] === 'no_fields') {
                $results[] = $this->bulkResult($i, $slug, 'invalid', [], ['fields' => ['No writable fields provided.']]);
                continue;
            }

            $results[] = $this->bulkResult($i, $slug, $outcome['status'], $outcome['changed']);
        }

        $counts = array_count_values(array_column($results, 'status'));

        return $this->envelope($request->query(), $results, [
            'total_rows' => count($results),
            'summary'    => [
                'updated'   => $counts['updated']   ?? 0,
                'unchanged' => $counts['unchanged'] ?? 0,
                'not_found' => $counts['not_found'] ?? 0,
                'conflict'  => $counts['conflict']  ?? 0,
                'invalid'   => $counts['invalid']   ?? 0,
            ],
        ]);
    }

    // ─── writing ──────────────────────────────────────────────────────────────

    /**
     * Validate-and-write one row, addressed by primary key.
     *
     * The old implementation updated `WHERE page_addr = ?`, which silently
     * wrote to every row sharing a slug (six such groups exist, one of them
     * two genuinely different models).
     *
     * @return array{status:string, changed:string[]}
     */
    private function applyToRow(object $row, array $validated): array
    {
        $before     = $this->currentValues($row);
        $updateData = [];
        $after      = [];

        foreach (self::FIELD_MAP as $field => $column) {
            if (!array_key_exists($field, $validated)) {
                continue;
            }
            $value = $validated[$field];

            if ($field === 'faq') {
                $after[$field]       = ($value && count($value) > 0) ? $value : null;
                $updateData[$column] = $after[$field] ? json_encode($value, JSON_UNESCAPED_UNICODE) : null;
                continue;
            }

            if (in_array($field, self::PLAIN_TEXT_FIELDS, true)) {
                $value = $this->sanitizePlainText($value);
            }

            $after[$field]       = ($value === null || $value === '') ? null : $value;
            $updateData[$column] = $value ?? '';
        }

        if (empty($updateData)) {
            return ['status' => 'no_fields', 'changed' => []];
        }

        // change_time is TIMESTAMP ... ON UPDATE CURRENT_TIMESTAMP — the column
        // maintains itself, no need to set it here.
        DB::table('rent_model_web')->where('web_id', $row->web_id)->update($updateData);

        $changed = $this->recordContentVersion('product', $row->slug, $before, $after);

        return ['status' => $changed ? 'updated' : 'unchanged', 'changed' => $changed];
    }

    /**
     * Strip markup and attribute-breaking characters from a field that ends up
     * inside <title> or an HTML attribute (meta description, alt, title=).
     */
    private function sanitizePlainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = strip_tags($value);
        $value = str_replace(['"', '<', '>'], '', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    // ─── reading ──────────────────────────────────────────────────────────────

    /**
     * One query serving index/show/update. Every join is single-parent, so no
     * row multiplication and no need for DISTINCT.
     *
     * @param  array<string,mixed> $filters
     * @return object[]
     */
    private function fetchRows(array $filters): array
    {
        $where  = ["rmw.lang = 'ru'"];
        $params = [];

        $status = $filters['status'] ?? 'show';
        if ($status !== 'all') {
            $where[]  = 'rmw.status = ?';
            $params[] = $status;
        }

        if (!empty($filters['slug'])) {
            $where[]  = 'rmw.page_addr = ?';
            $params[] = $filters['slug'];
        }
        if (!empty($filters['razdel'])) {
            $where[]  = 'r.url_razdel_name = ?';
            $params[] = $filters['razdel'];
        }
        if (!empty($filters['subrazdel'])) {
            $where[]  = 'sr.url_sub_razdel_name = ?';
            $params[] = $filters['subrazdel'];
        }
        if (!empty($filters['category'])) {
            $where[]  = 'c.cat_url_key = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $like     = '%' . $filters['search'] . '%';
            $where[]  = '(rmw.item_name_main LIKE ? OR rmw.l2_name LIKE ? OR rmw.page_addr LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (isset($filters['has_stock']) && $filters['has_stock'] !== null) {
            $where[] = $filters['has_stock']
                ? 'COALESCE(inv.units, 0) > 0'
                : 'COALESCE(inv.units, 0) = 0';
        }
        if (isset($filters['indexable']) && $filters['indexable'] !== null) {
            $indexable = "(rmw.status = 'show' AND r.url_razdel_name IS NOT NULL AND COALESCE(inv.units, 0) > 0)";
            $where[]   = $filters['indexable'] ? $indexable : "NOT {$indexable}";
        }
        if (!empty($filters['missing'])) {
            // OR: "pages missing at least one of these fields" — the shape a
            // content work queue needs.
            $parts = [];
            foreach ($filters['missing'] as $column) {
                $parts[] = "(rmw.`{$column}` IS NULL OR rmw.`{$column}` = '')";
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        $whereSql = implode("\n              AND ", $where);

        return DB::select("
            SELECT
                rmw.web_id,
                rmw.model_id,
                rmw.page_addr              AS slug,
                rmw.item_name_main         AS name,
                rmw.l2_name,
                rmw.title,
                rmw.meta_description,
                rmw.main_descr,
                rmw.m_pic_alt,
                rmw.m_a_title,
                rmw.l2_alt,
                rmw.breadcrumbs_name,
                rmw.faq,
                rmw.status,
                rmw.change_time,
                r.url_razdel_name          AS razdel_slug,
                sr.url_sub_razdel_name     AS subrazdel_slug,
                c.cat_url_key              AS category_slug,
                COALESCE(inv.units, 0)     AS active_units,
                CASE WHEN r.url_razdel_name IS NOT NULL
                     THEN CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name, '/', c.cat_url_key, '/', rmw.page_addr)
                END                        AS full_url
            FROM rent_model_web rmw
            LEFT JOIN tovar_rent tr      ON tr.tovar_rent_id     = rmw.model_id
            LEFT JOIN tovar_rent_cat c   ON c.tovar_rent_cat_id  = tr.tovar_rent_cat_id AND c.cat_url_key <> ''
            LEFT JOIN sub_razdel sr      ON sr.id_sub_razdel     = c.main_sub_razdel_id AND sr.url_sub_razdel_name <> ''
            LEFT JOIN razdel r           ON r.id_razdel          = sr.main_razdel_id    AND r.url_razdel_name <> ''
            LEFT JOIN (
                SELECT model_id, COUNT(*) AS units
                FROM tovar_rent_items
                GROUP BY model_id
            ) inv ON inv.model_id = rmw.model_id
            WHERE {$whereSql}
            ORDER BY rmw.sort_n, rmw.item_name_main
        ", $params);
    }

    /** API-shaped values of a row; null for "not filled in". */
    private function currentValues(object $row): array
    {
        return [
            'meta_title'       => $row->title            ?: null,
            'meta_description' => $row->meta_description ?: null,
            'h1'               => $row->name             ?: null,
            'l2_name'          => $row->l2_name          ?: null,
            'main_pic_alt'     => $row->m_pic_alt        ?: null,
            'main_pic_title'   => $row->m_a_title        ?: null,
            'l2_pic_alt'       => $row->l2_alt           ?: null,
            'description'      => $row->main_descr       ?: null,
            'breadcrumb_name'  => $row->breadcrumbs_name ?: null,
            'faq'              => $row->faq ? json_decode($row->faq, true) : null,
        ];
    }

    private function presentRow(object $row, bool $withValues): array
    {
        $current = $this->currentValues($row);

        $out = [
            'level'          => 'product',
            'slug'           => $row->slug,
            'model_id'       => (int) $row->model_id,
            'name'           => $row->name,
            'full_url'       => $row->full_url,
            'razdel_slug'    => $row->razdel_slug,
            'subrazdel_slug' => $row->subrazdel_slug,
            'category_slug'  => $row->category_slug,
            'status'         => $row->status,
            'active_units'   => (int) $row->active_units,
            'is_indexable'   => $row->status === 'show' && $row->full_url !== null && (int) $row->active_units > 0,
            'seo_score'      => $this->seoScore($current),
            'updated_at'     => $row->change_time,
        ];

        if ($withValues) {
            $out['current_values'] = $current;
            $out['default_values'] = [
                // The L3 template has no fallbacks: whatever is empty here ships empty.
                'meta_title'       => null,
                'meta_description' => null,
                'h1'               => $row->name,
                'l2_name'          => $row->name,
                'main_pic_alt'     => null,
                'main_pic_title'   => null,
                'l2_pic_alt'       => null,
                'description'      => null,
                'breadcrumb_name'  => $row->name,
                'faq'              => null,
            ];
        }

        return $out;
    }

    /** @param array<string,mixed> $current */
    private function seoScore(array $current): array
    {
        $score = [];
        foreach (array_keys(self::FIELD_MAP) as $field) {
            $score['has_' . $field] = !empty($current[$field]);
        }

        return $score;
    }

    /**
     * Missing-field counters over the whole filtered set (not just the page),
     * plus an optional per-category breakdown — the inventory a content pass
     * needs in order to plan work without pulling every row.
     *
     * @param object[] $rows
     */
    private function buildSummary(array $rows, bool $withBreakdown): array
    {
        $missing = array_fill_keys(array_keys(self::FIELD_MAP), 0);
        $buckets = [];

        foreach ($rows as $row) {
            $current = $this->currentValues($row);
            $key     = ($row->razdel_slug ?? '-') . '|' . ($row->subrazdel_slug ?? '-') . '|' . ($row->category_slug ?? '-');

            if ($withBreakdown && !isset($buckets[$key])) {
                $buckets[$key] = [
                    'razdel_slug'    => $row->razdel_slug,
                    'subrazdel_slug' => $row->subrazdel_slug,
                    'category_slug'  => $row->category_slug,
                    'total'          => 0,
                    'missing'        => array_fill_keys(array_keys(self::FIELD_MAP), 0),
                ];
            }
            if ($withBreakdown) {
                $buckets[$key]['total']++;
            }

            foreach (array_keys(self::FIELD_MAP) as $field) {
                if (empty($current[$field])) {
                    $missing[$field]++;
                    if ($withBreakdown) {
                        $buckets[$key]['missing'][$field]++;
                    }
                }
            }
        }

        $summary = [
            'total_matching' => count($rows),
            'missing'        => $missing,
        ];

        if ($withBreakdown) {
            usort($buckets, fn ($a, $b) => $b['total'] <=> $a['total']);
            $summary['by_category'] = array_values($buckets);
        }

        return $summary;
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    /**
     * @return string[]|false  DB columns to test for emptiness, or false when
     *                         an unknown field name was requested.
     */
    private function parseMissing(?string $raw)
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $columns = [];
        foreach (array_filter(array_map('trim', explode(',', $raw))) as $field) {
            if (!isset(self::FIELD_MAP[$field])) {
                return false;
            }
            $columns[] = self::FIELD_MAP[$field];
        }

        return $columns;
    }

    private function bulkResult(int $index, string $slug, string $status, array $changed = [], array $errors = []): array
    {
        return [
            'index'          => $index,
            'slug'           => $slug,
            'status'         => $status,
            'changed_fields' => $changed,
            'errors'         => $errors ?: null,
        ];
    }

    private function notFound(string $slug): JsonResponse
    {
        return response()->json([
            'error'   => 'not_found',
            'message' => "Product page with slug '{$slug}' not found.",
        ], 404);
    }

    /** @param object[] $rows */
    private function slugConflict(string $slug, array $rows): JsonResponse
    {
        return response()->json([
            'error'   => 'slug_conflict',
            'message' => "Slug '{$slug}' maps to " . count($rows) . " rows in rent_model_web. "
                       . 'Refusing to read or write an ambiguous page — deduplicate page_addr in the admin panel first.',
            'models'  => array_map(fn ($r) => [
                'web_id'   => (int) $r->web_id,
                'model_id' => (int) $r->model_id,
                'name'     => $r->name,
                'full_url' => $r->full_url,
            ], $rows),
        ], 409);
    }
}
