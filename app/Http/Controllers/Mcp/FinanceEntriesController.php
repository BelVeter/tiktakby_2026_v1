<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * General CRUD API over `doh_rash`, the income/expense ledger.
 *
 * Domain model — every row answers four questions:
 *   - type1   direction: 'doh' (income) | 'rash' (expense)
 *   - type2   article code; dictionary depends on type1: rash_items.ri_code
 *             (+ri_text) for expenses, doh_items.rd_code (+rd_text) for income
 *   - channel WHERE it happened: office number ('1'-'4'), 'cur' (courier), 'bank'
 *   - kassa   WHERE the money sits: 'k1'/'k2' (cash tills), 'card', 'bank'
 *
 * Storage quirks handled here:
 *   - `amount` is stored NEGATIVE for rash, POSITIVE for doh. The API always
 *     returns a POSITIVE magnitude; direction is carried by `type1`.
 *   - `acc_date` (accounting date — when the money moved) is a unix
 *     timestamp and is rendered as `date` (Y-m-d). PHP's default timezone is
 *     set to Europe/Minsk from config('app.timezone') at bootstrap
 *     (Illuminate\Foundation\Bootstrap\LoadConfiguration), so plain date()/
 *     strtotime() below already operate in Minsk local time — do not swap
 *     these for gmdate()/UTC-based helpers.
 *   - `cr_time` (record creation time) is rendered separately as
 *     `created_at`; it must never be used for the `date` field.
 *   - `cr_who_id` is resolved to `created_by` via logpass.lp_fio; the raw id
 *     is also returned as `created_by_id`.
 *
 * This is a separate concern from FinanceController (period-aggregation
 * reporting with legacy-parity guarantees over doh_rash) — that class is not
 * touched here.
 *
 * type2_name and created_by are resolved via small dictionaries cached under
 * BaseController::TTL_META rather than a per-row join.
 */
class FinanceEntriesController extends BaseController
{
    /**
     * GET /finance/entries
     *
     * Filters: from, to (on acc_date), type1, type2, kassa, channel,
     * dr_name_id, search (LIKE on info). Pagination: per_page (default 100,
     * max 500), page. Ordered acc_date DESC, dr_id DESC.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from'       => 'nullable|date',
            'to'         => 'nullable|date',
            'type1'      => 'nullable|in:doh,rash',
            'type2'      => 'nullable|string|max:64',
            'kassa'      => 'nullable|string|max:16',
            'channel'    => 'nullable|string|max:16',
            'dr_name_id' => 'nullable|integer',
            'search'     => 'nullable|string|max:500',
            'page'       => 'nullable|integer|min:1',
            'per_page'   => 'nullable|integer|min:1|max:500',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 100);
        $page    = (int) ($validated['page'] ?? 1);

        $query = DB::table('doh_rash');

        if (!empty($validated['from'])) {
            $query->where('acc_date', '>=', strtotime($validated['from'] . ' 00:00:00'));
        }
        if (!empty($validated['to'])) {
            $query->where('acc_date', '<=', strtotime($validated['to'] . ' 23:59:59'));
        }
        if (!empty($validated['type1'])) {
            $query->where('type1', $validated['type1']);
        }
        if (!empty($validated['type2'])) {
            $query->where('type2', $validated['type2']);
        }
        if (!empty($validated['kassa'])) {
            $query->where('kassa', $validated['kassa']);
        }
        if (!empty($validated['channel'])) {
            $query->where('channel', $validated['channel']);
        }
        if (isset($validated['dr_name_id'])) {
            $query->where('dr_name_id', (int) $validated['dr_name_id']);
        }
        if (!empty($validated['search'])) {
            $query->where('info', 'LIKE', '%' . $validated['search'] . '%');
        }

        $total = (clone $query)->count();

        $rows = $query->orderByDesc('acc_date')
            ->orderByDesc('dr_id')
            ->forPage($page, $perPage)
            ->get();

        $data = $rows->map(fn ($row) => $this->formatRow($row))->values()->all();

        return $this->envelope($request->query(), $data, [
            'total_rows' => $total,
            'page'       => $page,
            'per_page'   => $perPage,
        ]);
    }

    /**
     * GET /finance/entries/{id}
     *
     * Single row, same shape as index(). 404 with a JSON {"error": ...} body
     * when the id is unknown (this app's global fallback 404s any
     * unregistered path as HTML, so the JSON error key is what proves this
     * route actually exists and ran).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('doh_rash')->where('dr_id', $id)->first();

        if (!$row) {
            return response()->json(['error' => 'Finance entry not found.'], 404);
        }

        return response()->json([
            'data' => $this->formatRow($row),
            'meta' => ['total_rows' => 1],
        ]);
    }

    /**
     * GET /finance/entries/history
     *
     * Reads doh_rash_history (created in Task 1). Filters: dr_id, action,
     * from, to (on created_at). Same pagination as index().
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dr_id'    => 'nullable|integer',
            'action'   => 'nullable|in:update,delete',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:500',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 100);
        $page    = (int) ($validated['page'] ?? 1);

        $query = DB::table('doh_rash_history');

        if (isset($validated['dr_id'])) {
            $query->where('dr_id', (int) $validated['dr_id']);
        }
        if (!empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }
        if (!empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from'] . ' 00:00:00');
        }
        if (!empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'] . ' 23:59:59');
        }

        $total = (clone $query)->count();

        $rows = $query->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        $data = $rows->map(fn ($row) => $this->formatHistoryRow($row))->values()->all();

        return $this->envelope($request->query(), $data, [
            'total_rows' => $total,
            'page'       => $page,
            'per_page'   => $perPage,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Row formatting
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Maps a doh_rash DB row to the response shape (exact key set — see
     * class docblock for the storage quirks this accounts for).
     */
    protected function formatRow(object $row): array
    {
        $type2Map     = $this->type2Dictionary();
        $createdByMap = $this->createdByDictionary();

        return [
            'dr_id'         => (int) $row->dr_id,
            'date'          => date('Y-m-d', (int) $row->acc_date),
            'amount'        => round(abs((float) $row->amount), 2),
            'type1'         => $row->type1,
            'type2'         => $row->type2,
            'type2_name'    => $type2Map[$row->type1][$row->type2] ?? null,
            'kassa'         => $row->kassa,
            'channel'       => $row->channel,
            'info'          => $row->info,
            'dr_name_id'    => (int) $row->dr_name_id,
            'link_to'       => (int) $row->link_to,
            'created_at'    => date('Y-m-d H:i:s', (int) $row->cr_time),
            'created_by_id' => (int) $row->cr_who_id,
            'created_by'    => $createdByMap[(int) $row->cr_who_id] ?? null,
        ];
    }

    private function formatHistoryRow(object $row): array
    {
        return [
            'id'            => (int) $row->id,
            'dr_id'         => (int) $row->dr_id,
            'action'        => $row->action,
            'before'        => $row->before_json !== null ? json_decode($row->before_json, true) : null,
            'after'         => $row->after_json !== null ? json_decode($row->after_json, true) : null,
            'actor_user_id' => $row->actor_user_id !== null ? (int) $row->actor_user_id : null,
            'source'        => $row->source,
            'ip'            => $row->ip,
            'created_at'    => (string) $row->created_at,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Dictionaries (Task 5 reuses these two)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * type1 => type2 code => label. Unfiltered by is_active so historical
     * rows referencing a since-deactivated article code still resolve.
     *
     * @return array{doh: array<string,string>, rash: array<string,string>}
     */
    protected function type2Dictionary(): array
    {
        return $this->cacheRemember('mcp.finance_entries.type2_dictionary', self::TTL_META, function () {
            $doh = [];
            foreach (DB::table('doh_items')->select('rd_code', 'rd_text')->get() as $r) {
                $doh[$r->rd_code] = $r->rd_text;
            }
            $rash = [];
            foreach (DB::table('rash_items')->select('ri_code', 'ri_text')->get() as $r) {
                $rash[$r->ri_code] = $r->ri_text;
            }
            return ['doh' => $doh, 'rash' => $rash];
        });
    }

    /** logpass_id => lp_fio. */
    protected function createdByDictionary(): array
    {
        return $this->cacheRemember('mcp.finance_entries.created_by_dictionary', self::TTL_META, function () {
            $map = [];
            foreach (DB::table('logpass')->select('logpass_id', 'lp_fio')->get() as $r) {
                $map[(int) $r->logpass_id] = $r->lp_fio;
            }
            return $map;
        });
    }
}
