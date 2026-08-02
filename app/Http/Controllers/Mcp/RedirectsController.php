<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MCP Redirects CRUD API
 *
 * Manages the `redirects` table that drives CheckRedirects middleware.
 * After every write operation, clears the two cache keys used by
 * CheckRedirects so changes take effect immediately.
 *
 * Cache keys cleared on write:
 *   - redirects_exact_map
 *   - redirects_regex_list
 */
class RedirectsController extends BaseController
{
    // ──────────────────────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────────────────────

    /**
     * GET /api/mcp/v1/redirects
     *
     * List redirects with optional filters.
     *
     * Query params:
     *   is_active  0|1  (optional)
     *   is_regex   0|1  (optional)
     *   search     string — LIKE match on source_url or target_url (optional)
     *   per_page   int, default 100, max 500
     *   page       int, default 1
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'is_active' => 'sometimes|in:0,1',
            'is_regex'  => 'sometimes|in:0,1',
            'search'    => 'sometimes|string|max:500',
            'per_page'  => 'sometimes|integer|min:1|max:500',
            'page'      => 'sometimes|integer|min:1',
        ]);

        $perPage = (int) $request->input('per_page', 100);
        $page    = (int) $request->input('page', 1);
        $offset  = ($page - 1) * $perPage;

        $query = DB::table('redirects');

        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->input('is_active'));
        }
        if ($request->filled('is_regex')) {
            $query->where('is_regex', (int) $request->input('is_regex'));
        }
        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('source_url', 'LIKE', $term)
                  ->orWhere('target_url', 'LIKE', $term);
            });
        }

        $total = $query->count();
        $rows  = $query->orderBy('id')
                       ->offset($offset)
                       ->limit($perPage)
                       ->get();

        $data = $rows->map(fn($row) => $this->formatRow($row))->values()->all();

        $queryParams = array_filter([
            'is_active' => $request->input('is_active'),
            'is_regex'  => $request->input('is_regex'),
            'search'    => $request->input('search'),
            'per_page'  => $perPage,
            'page'      => $page,
        ], fn($v) => $v !== null);

        return response()->json([
            'query' => $queryParams,
            'data'  => $data,
            'meta'  => [
                'total_rows' => $total,
                'page'       => $page,
                'per_page'   => $perPage,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // CREATE
    // ──────────────────────────────────────────────────────────────────────

    /**
     * POST /api/mcp/v1/redirects
     *
     * Create a single redirect.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_url'  => 'required|string|max:2000',
            'target_url'  => 'required|string|max:2000',
            'status_code' => 'sometimes|integer|in:301,302',
            'is_active'   => 'sometimes|boolean',
            'is_regex'    => 'sometimes|boolean',
            'comment'     => 'sometimes|nullable|string|max:255',
        ]);

        $sourceUrl  = $validated['source_url'];
        $isRegex    = isset($validated['is_regex']) ? (bool) $validated['is_regex'] : false;

        // Normalise: non-regex URLs must start with /
        if (!$isRegex && !str_starts_with($sourceUrl, '/')) {
            $sourceUrl = '/' . $sourceUrl;
        }

        // Check for duplicate source_url
        $exists = DB::table('redirects')->where('source_url', $sourceUrl)->exists();
        if ($exists) {
            return response()->json([
                'error'      => 'source_url already exists',
                'source_url' => $sourceUrl,
            ], 422);
        }

        $id = DB::table('redirects')->insertGetId([
            'source_url'  => $sourceUrl,
            'target_url'  => $validated['target_url'],
            'status_code' => $validated['status_code'] ?? 301,
            'is_active'   => isset($validated['is_active']) ? (int) $validated['is_active'] : 1,
            'is_regex'    => $isRegex ? 1 : 0,
            'comment'     => $validated['comment'] ?? null,
            'hit_count'   => 0,
            'last_hit_at' => null,
        ]);

        $this->clearCache();

        $row = DB::table('redirects')->where('id', $id)->first();

        return response()->json([
            'data' => $this->formatRow($row),
            'meta' => ['affected_rows' => 1],
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────────────
    // UPDATE
    // ──────────────────────────────────────────────────────────────────────

    /**
     * PATCH /api/mcp/v1/redirects/{id}
     *
     * Partial update — only provided fields are modified.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'source_url'  => 'sometimes|string|max:2000',
            'target_url'  => 'sometimes|string|max:2000',
            'status_code' => 'sometimes|integer|in:301,302',
            'is_active'   => 'sometimes|boolean',
            'is_regex'    => 'sometimes|boolean',
            'comment'     => 'sometimes|nullable|string|max:255',
        ]);

        if (empty($validated)) {
            return response()->json([
                'error' => 'At least one field must be provided for update.',
            ], 422);
        }

        $row = DB::table('redirects')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'Redirect not found.'], 404);
        }

        $payload = [];

        if (isset($validated['source_url'])) {
            $sourceUrl = $validated['source_url'];
            $isRegex   = isset($validated['is_regex'])
                ? (bool) $validated['is_regex']
                : (bool) $row->is_regex;

            if (!$isRegex && !str_starts_with($sourceUrl, '/')) {
                $sourceUrl = '/' . $sourceUrl;
            }

            // Check for duplicate source_url
            $exists = DB::table('redirects')
                ->where('source_url', $sourceUrl)
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'error'      => 'source_url already exists',
                    'source_url' => $sourceUrl,
                ], 422);
            }

            $payload['source_url'] = $sourceUrl;
        }

        if (isset($validated['target_url'])) {
            $payload['target_url'] = $validated['target_url'];
        }
        if (isset($validated['status_code'])) {
            $payload['status_code'] = $validated['status_code'];
        }
        if (isset($validated['is_active'])) {
            $payload['is_active'] = (int) $validated['is_active'];
        }
        if (isset($validated['is_regex'])) {
            $payload['is_regex'] = (int) $validated['is_regex'];
        }
        if (array_key_exists('comment', $validated)) {
            $payload['comment'] = $validated['comment'];
        }

        DB::table('redirects')->where('id', $id)->update($payload);

        $this->clearCache();

        $updated = DB::table('redirects')->where('id', $id)->first();

        return response()->json([
            'data' => $this->formatRow($updated),
            'meta' => ['affected_rows' => 1],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // DELETE
    // ──────────────────────────────────────────────────────────────────────

    /**
     * DELETE /api/mcp/v1/redirects/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $row = DB::table('redirects')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'Redirect not found.'], 404);
        }

        DB::table('redirects')->where('id', $id)->delete();

        $this->clearCache();

        return response()->json([
            'data' => ['deleted_id' => $id],
            'meta' => ['affected_rows' => 1],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // BULK UPSERT
    // ──────────────────────────────────────────────────────────────────────

    /**
     * POST /api/mcp/v1/redirects/bulk
     *
     * Bulk upsert up to 200 redirects.
     * Body: { "redirects": [ {source_url, target_url, ...}, ... ] }
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE on the unique source_url column.
     */
    public function bulk(Request $request): JsonResponse
    {
        $request->validate([
            'redirects'   => 'required|array|min:1|max:200',
        ]);

        $items = $request->input('redirects');

        // Validate each item
        $errors = [];
        foreach ($items as $i => $item) {
            $itemValidator = \Illuminate\Support\Facades\Validator::make($item, [
                'source_url'  => 'required|string|max:2000',
                'target_url'  => 'required|string|max:2000',
                'status_code' => 'sometimes|integer|in:301,302',
                'is_active'   => 'sometimes|boolean',
                'is_regex'    => 'sometimes|boolean',
                'comment'     => 'sometimes|nullable|string|max:255',
            ]);
            if ($itemValidator->fails()) {
                $errors[$i] = $itemValidator->errors()->toArray();
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'error'  => 'Validation failed for one or more items.',
                'errors' => $errors,
            ], 422);
        }

        // Build upsert rows
        $rows = [];
        foreach ($items as $item) {
            $isRegex   = isset($item['is_regex']) ? (bool) $item['is_regex'] : false;
            $sourceUrl = $item['source_url'];

            if (!$isRegex && !str_starts_with($sourceUrl, '/')) {
                $sourceUrl = '/' . $sourceUrl;
            }

            $rows[] = [
                'source_url'  => $sourceUrl,
                'target_url'  => $item['target_url'],
                'status_code' => $item['status_code'] ?? 301,
                'is_active'   => isset($item['is_active']) ? (int) $item['is_active'] : 1,
                'is_regex'    => $isRegex ? 1 : 0,
                'comment'     => $item['comment'] ?? null,
                'hit_count'   => 0,
                'last_hit_at' => null,
            ];
        }

        // INSERT ... ON DUPLICATE KEY UPDATE via raw PDO to get rowCount per statement
        $pdo   = DB::getPdo();
        $sql   = "INSERT INTO `redirects`
                    (`source_url`, `target_url`, `status_code`, `is_active`, `is_regex`, `comment`, `hit_count`, `last_hit_at`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    `target_url`  = VALUES(`target_url`),
                    `status_code` = VALUES(`status_code`),
                    `is_active`   = VALUES(`is_active`),
                    `is_regex`    = VALUES(`is_regex`),
                    `comment`     = VALUES(`comment`)";
        $stmt = $pdo->prepare($sql);
        foreach ($rows as $r) {
            $stmt->execute([
                $r['source_url'], $r['target_url'], $r['status_code'],
                $r['is_active'],  $r['is_regex'],   $r['comment'],
                $r['hit_count'],  $r['last_hit_at'],
            ]);
        }

        // Every item was either inserted or upserted — all rows were touched.
        $upserted = count($rows);

        $this->clearCache();

        return response()->json([
            'data' => ['upserted' => $upserted],
            'meta' => ['affected_rows' => $upserted],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Clear redirect-related cache keys used by CheckRedirects middleware.
     */
    private function clearCache(): void
    {
        Cache::forget('redirects_exact_map');
        Cache::forget('redirects_regex_list');
    }

    /**
     * Cast DB row fields to correct PHP types for JSON output.
     */
    private function formatRow(object $row): array
    {
        return [
            'id'          => (int) $row->id,
            'source_url'  => $row->source_url,
            'target_url'  => $row->target_url,
            'status_code' => (int) $row->status_code,
            'is_active'   => (bool) $row->is_active,
            'is_regex'    => (bool) $row->is_regex,
            'comment'     => $row->comment,
            'hit_count'   => (int) $row->hit_count,
            'last_hit_at' => $row->last_hit_at,
        ];
    }
}
