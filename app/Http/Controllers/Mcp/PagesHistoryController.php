<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/mcp/v1/pages/history
 *
 * Field-level change log of SEO content written through the MCP API
 * (`mcp_content_versions`). Covers both L2 listing pages and L3 product pages;
 * edits made in the legacy admin panel are not recorded there.
 */
class PagesHistoryController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_type' => 'nullable|in:listing,product',
            'slug'      => 'nullable|string|max:191',
            'field'     => 'nullable|string|max:64',
            'from'      => 'nullable|date',
            'to'        => 'nullable|date',
            'page'      => 'nullable|integer|min:1',
            'per_page'  => 'nullable|integer|min:1|max:500',
        ]);

        $query = DB::table('mcp_content_versions');

        if (!empty($validated['page_type'])) {
            $query->where('page_type', $validated['page_type']);
        }
        if (!empty($validated['slug'])) {
            $query->where('page_slug', $validated['slug']);
        }
        if (!empty($validated['field'])) {
            $query->where('field', $validated['field']);
        }
        if (!empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from'] . ' 00:00:00');
        }
        if (!empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'] . ' 23:59:59');
        }

        $total   = (clone $query)->count();
        $perPage = (int) ($validated['per_page'] ?? 100);
        $page    = (int) ($validated['page'] ?? 1);

        $rows = $query->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        $data = $rows->map(fn ($r) => [
            'id'         => (int) $r->id,
            'page_type'  => $r->page_type,
            'slug'       => $r->page_slug,
            'field'      => $r->field,
            'old_value'  => $r->old_value,
            'new_value'  => $r->new_value,
            'source'     => $r->source,
            'created_at' => $r->created_at,
        ])->all();

        return $this->envelope($request->query(), $data, [
            'total_rows'    => $total,
            'returned_rows' => count($data),
            'page'          => $page,
            'per_page'      => $perPage,
        ]);
    }
}
