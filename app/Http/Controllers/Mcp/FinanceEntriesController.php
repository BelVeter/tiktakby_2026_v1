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
     * The only type1 values this API may create, edit or delete.
     *
     * shift_plus/shift_minus (paired till-transfer rows, linked to each other
     * through `link_to`) are deliberately excluded: a single-row API write
     * would corrupt the till balance the pair exists to keep in sync. This
     * gates both the INCOMING value (validateItem(), on create and on the
     * merged row of a patch) and the EXISTING row (update()/destroy(), so a
     * shift row can never be retyped into a rash/doh row or half-deleted).
     */
    private const EDITABLE_TYPE1 = ['rash', 'doh'];

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

        $type2Map     = $this->type2Dictionary();
        $createdByMap = $this->createdByDictionary();

        $data = $rows->map(fn ($row) => $this->formatRow($row, $type2Map, $createdByMap))->values()->all();

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

        $type2Map     = $this->type2Dictionary();
        $createdByMap = $this->createdByDictionary();

        return $this->envelope($request->query(), $this->formatRow($row, $type2Map, $createdByMap), ['total_rows' => 1]);
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
    // WRITE (Task 5): POST /finance/entries, PATCH/DELETE /finance/entries/{id}
    // ──────────────────────────────────────────────────────────────────────

    /**
     * POST /finance/entries
     *
     * Batch create (1-200 items per request; empty or >200 is a whole-request
     * 422). Each item is independently validated and written — one invalid
     * item never blocks the others, so the batch response itself is always
     * HTTP 200; per-item outcome is carried in data[].status.
     *
     * Inserts are NOT journalled (see journal() docblock for why).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'entries' => 'required|array|min:1|max:200',
        ]);

        $entries = $request->input('entries');
        $results = [];
        $created = 0;
        $invalid = 0;

        foreach (array_values($entries) as $index => $entry) {
            $entry = is_array($entry) ? $entry : [];
            $errors = $this->validateItem($entry);

            if (!empty($errors)) {
                $invalid++;
                $results[] = [
                    'index'  => $index,
                    'status' => 'invalid',
                    'dr_id'  => null,
                    'errors' => $errors,
                ];
                continue;
            }

            $storageRow = $this->toStorageRow($entry);
            // Server-set, never client-controlled — a client-supplied
            // cr_who_id/cr_time in $entry is simply never read here.
            $storageRow['cr_time']   = time();
            $storageRow['cr_who_id'] = $this->apiAuthorId();

            $drId = DB::table('doh_rash')->insertGetId($storageRow);

            $created++;
            $results[] = [
                'index'  => $index,
                'status' => 'created',
                'dr_id'  => $drId,
                'errors' => null,
            ];
        }

        return $this->envelope($request->query(), $results, [
            'total_rows' => count($entries),
            'summary'    => [
                'created' => $created,
                'invalid' => $invalid,
            ],
        ]);
    }

    /**
     * PATCH /finance/entries/{id}
     *
     * Partial update. Validation runs against the MERGED row (existing
     * columns with the patch fields applied on top) — a field not present in
     * this request's body can still fail validation if it no longer pairs
     * with a field that WAS patched (e.g. patching only kassa on a row whose
     * existing channel doesn't pair with the new kassa).
     *
     * The EXISTING row's type1 is gated too (see self::EDITABLE_TYPE1) —
     * validating only the merged row would let a shift_plus/shift_minus row be
     * retyped as rash/doh (sign-flipping its amount) while its `link_to`
     * partner is left untouched, silently desynchronising the till balance.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $existing = DB::table('doh_rash')->where('dr_id', $id)->first();
        if (!$existing) {
            return response()->json(['error' => 'Finance entry not found.'], 404);
        }

        if (!in_array($existing->type1, self::EDITABLE_TYPE1, true)) {
            return response()->json(['error' => 'This entry is not editable through this API (internal transfer type).'], 422);
        }

        $body = $request->all();
        if (empty($body)) {
            return response()->json(['error' => 'At least one field must be provided for update.'], 422);
        }

        // Only the mutable API fields may be patched; cr_who_id/cr_time (and
        // anything else) are silently ignored, same as on create.
        $allowedFields = ['type1', 'type2', 'date', 'amount', 'kassa', 'channel', 'info', 'dr_name_id', 'link_to'];
        $patch = array_intersect_key($body, array_flip($allowedFields));

        $merged = array_merge($this->rowToApiShape($existing), $patch);

        $errors = $this->validateItem($merged);
        if (!empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        $storageRow = $this->toStorageRow($merged);
        DB::table('doh_rash')->where('dr_id', $id)->update($storageRow);

        $updated = DB::table('doh_rash')->where('dr_id', $id)->first();

        $this->journal('update', $existing, $updated);

        $type2Map     = $this->type2Dictionary();
        $createdByMap = $this->createdByDictionary();

        return response()->json([
            'data' => $this->formatRow($updated, $type2Map, $createdByMap),
            'meta' => ['affected_rows' => 1],
        ]);
    }

    /**
     * DELETE /finance/entries/{id}
     *
     * Refuses rows whose type1 is outside self::EDITABLE_TYPE1 — deleting one
     * half of a shift_plus/shift_minus pair would leave the other half dangling
     * and the till balance wrong.
     *
     * Everything the journal needs is resolved BEFORE the row is destroyed: for
     * a delete the journal snapshot is the only remaining copy of the row, so a
     * failure to build it must abort the delete rather than proceed into an
     * unrecoverable one.
     */
    public function destroy(int $id): JsonResponse
    {
        $existing = DB::table('doh_rash')->where('dr_id', $id)->first();
        if (!$existing) {
            return response()->json(['error' => 'Finance entry not found.'], 404);
        }

        if (!in_array($existing->type1, self::EDITABLE_TYPE1, true)) {
            return response()->json(['error' => 'This entry is not editable through this API (internal transfer type).'], 422);
        }

        // apiAuthorId() throws when the api_system seed row is missing. Resolved
        // here — not lazily inside journal(), which runs after the delete — so
        // that failure surfaces as an error response with the row still intact,
        // instead of a successful delete whose contents were never journalled.
        try {
            $actorId = $this->apiAuthorId();
        } catch (\Throwable $e) {
            \Log::error('doh_rash delete aborted, journal actor unresolvable', [
                'dr_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Delete aborted: this deletion could not be journalled, and an unjournalled delete is unrecoverable.',
            ], 500);
        }

        DB::table('doh_rash')->where('dr_id', $id)->delete();

        $this->journal('delete', $existing, null, $actorId);

        return response()->json([
            'data' => ['deleted_id' => $id],
            'meta' => ['affected_rows' => 1],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Write-side validation and storage-row construction
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Validates one entry (API shape: type1, type2, date, amount, kassa,
     * channel, info, dr_name_id?, link_to?) and returns a field => message
     * map. Empty array means valid. Never throws for bad input — that's the
     * point, since POST must report per-item errors instead of failing the
     * whole batch.
     *
     * @param  array<string,mixed> $data
     * @return array<string,string>
     */
    private function validateItem(array $data): array
    {
        $errors = [];

        // ── type1 ────────────────────────────────────────────────────────
        $type1 = array_key_exists('type1', $data) ? $data['type1'] : null;
        if ($type1 === null || $type1 === '') {
            $errors['type1'] = 'type1 is required.';
        } elseif (!in_array($type1, self::EDITABLE_TYPE1, true)) {
            // shift_plus/shift_minus (paired till-transfer types) are
            // deliberately excluded — a single-row API write would corrupt
            // the till balance they're meant to keep in sync.
            $errors['type1'] = 'type1 must be one of: rash, doh.';
        }

        // ── type2 (dictionary depends on type1) ─────────────────────────
        // The is_scalar() guard (same shape as kassa/channel below) must come
        // BEFORE any (string) cast: a JSON array/object here would otherwise
        // hit "Array to string conversion", which this app's error_reporting
        // turns into a thrown ErrorException — fatal mid-batch, after earlier
        // items of the same POST have already been inserted into MyISAM
        // doh_rash with no transaction to roll them back.
        $type2Raw = array_key_exists('type2', $data) ? $data['type2'] : null;
        $type2    = is_scalar($type2Raw) ? (string) $type2Raw : null;
        if ($type2Raw !== null && $type2 === null) {
            $errors['type2'] = 'type2 must be a string.';
        } elseif ($type2 === null || $type2 === '') {
            $errors['type2'] = 'type2 is required.';
        } elseif (!isset($errors['type1'])) {
            if (!$this->type2ExistsActive($type1, $type2)) {
                $errors['type2'] = 'type2 must be an active code in the dictionary matching type1.';
            }
        }

        // ── date ─────────────────────────────────────────────────────────
        $date = array_key_exists('date', $data) ? $data['date'] : null;
        if ($date === null || $date === '') {
            $errors['date'] = 'date is required.';
        } elseif (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['date'] = 'date must be in YYYY-MM-DD format.';
        } else {
            [$y, $m, $d] = array_map('intval', explode('-', $date));
            if (!checkdate($m, $d, $y)) {
                $errors['date'] = 'date must be a real calendar date.';
            }
        }

        // ── amount (positive magnitude; decimal(11,2) column) ───────────
        $amount = array_key_exists('amount', $data) ? $data['amount'] : null;
        if ($amount === null || $amount === '') {
            $errors['amount'] = 'amount is required.';
        } elseif (!is_int($amount) && !is_float($amount) && !(is_string($amount) && is_numeric($amount))) {
            $errors['amount'] = 'amount must be numeric.';
        } else {
            $amountStr = is_string($amount) ? $amount : (string) $amount;
            if (stripos($amountStr, 'e') !== false) {
                $errors['amount'] = 'amount must be a plain decimal number.';
            } else {
                $numeric = (float) $amountStr;
                if ($numeric <= 0) {
                    $errors['amount'] = 'amount must be a positive number.';
                } else {
                    $parts   = explode('.', $amountStr);
                    $intPart = ltrim($parts[0], '-+');
                    $decPart = $parts[1] ?? '';
                    if (strlen($decPart) > 2) {
                        $errors['amount'] = 'amount must have at most 2 decimal places.';
                    } elseif (strlen($intPart) > 9) {
                        // decimal(11,2): 9 integer digits + 2 decimal digits = 11.
                        $errors['amount'] = 'amount exceeds the maximum magnitude allowed by the column (decimal(11,2)).';
                    }
                }
            }
        }

        // ── kassa (fixed whitelist) ──────────────────────────────────────
        $kassa = array_key_exists('kassa', $data) && is_scalar($data['kassa']) ? (string) $data['kassa'] : null;
        if (!array_key_exists('kassa', $data) || $data['kassa'] === null || $data['kassa'] === '') {
            $errors['kassa'] = 'kassa is required.';
        } elseif (!in_array($kassa, ['k1', 'k2', 'bank', 'card'], true)) {
            $errors['kassa'] = 'kassa must be one of: k1, k2, bank, card.';
        }

        // ── channel (office number, resolved live, OR 'cur' OR 'bank') ──
        $channel = array_key_exists('channel', $data) && is_scalar($data['channel']) ? (string) $data['channel'] : null;
        if (!array_key_exists('channel', $data) || $data['channel'] === null || $data['channel'] === '') {
            $errors['channel'] = 'channel is required.';
        } elseif (!in_array($channel, ['bank', 'cur'], true) && !$this->officeExists($channel)) {
            $errors['channel'] = 'channel must be an existing office number, "cur", or "bank".';
        }

        // ── channel × kassa pairing (only meaningful once both are individually valid) ──
        if (!isset($errors['kassa']) && !isset($errors['channel'])) {
            if (!$this->channelKassaPairValid($channel, $kassa)) {
                $errors['channel'] = 'channel and kassa are not a valid pair.';
            }
        }

        // ── info (required, non-empty after trim, max 2000 chars) ───────
        // Same is_scalar() guard, same reason as type2 above — trim((string) $info)
        // on an array/object would throw instead of reporting a per-item error.
        $infoRaw = array_key_exists('info', $data) ? $data['info'] : null;
        $info    = is_scalar($infoRaw) ? (string) $infoRaw : null;
        if ($infoRaw !== null && $info === null) {
            $errors['info'] = 'info must be a string.';
        } elseif ($info === null || trim($info) === '') {
            $errors['info'] = 'info is required.';
        } elseif (strlen($info) > 2000) {
            $errors['info'] = 'info must not exceed 2000 characters.';
        }

        // ── dr_name_id (required + must resolve for zpl/avans; optional otherwise) ──
        $drNameIdRaw = array_key_exists('dr_name_id', $data) ? $data['dr_name_id'] : 0;
        if ($drNameIdRaw === null || $drNameIdRaw === '') {
            $drNameIdRaw = 0;
        }
        if (!is_numeric($drNameIdRaw)) {
            $errors['dr_name_id'] = 'dr_name_id must be an integer.';
        } else {
            $drNameIdInt   = (int) $drNameIdRaw;
            $isSalaryType2 = in_array($type2, ['zpl', 'avans'], true);

            if ($isSalaryType2) {
                if ($drNameIdInt <= 0 || !DB::table('logpass')->where('logpass_id', $drNameIdInt)->exists()) {
                    $errors['dr_name_id'] = 'dr_name_id is required and must reference an existing employee when type2 is zpl or avans.';
                }
            } elseif ($drNameIdInt !== 0 && !DB::table('logpass')->where('logpass_id', $drNameIdInt)->exists()) {
                $errors['dr_name_id'] = 'dr_name_id must reference an existing employee.';
            }
        }

        return $errors;
    }

    /**
     * Converts a validated (no errors from validateItem()) API-shape item
     * into the doh_rash storage-column array. This is the ONLY place that
     * negates amount for 'rash' — callers (store()/update()) never do it
     * themselves. Does not include cr_who_id/cr_time/dr_id: those are
     * create-only (store()) or immutable (update()).
     *
     * @param  array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function toStorageRow(array $item): array
    {
        $type1     = $item['type1'];
        $magnitude = round((float) $item['amount'], 2);
        $stored    = $type1 === 'rash' ? -$magnitude : $magnitude;

        $accDate = strtotime($item['date'] . ' 00:00:00');

        $drNameIdRaw = $item['dr_name_id'] ?? 0;
        $drNameId    = ($drNameIdRaw === null || $drNameIdRaw === '') ? 0 : (int) $drNameIdRaw;

        $linkToRaw = $item['link_to'] ?? 0;
        $linkTo    = ($linkToRaw === null || $linkToRaw === '') ? 0 : (int) $linkToRaw;

        return [
            'acc_date'   => $accDate,
            'amount'     => $stored,
            'type1'      => $type1,
            'type2'      => (string) $item['type2'],
            'channel'    => (string) $item['channel'],
            'kassa'      => (string) $item['kassa'],
            'link_to'    => $linkTo,
            'info'       => (string) $item['info'],
            'dr_name_id' => $drNameId,
        ];
    }

    /**
     * Converts a doh_rash DB row into the same API shape validateItem()/
     * toStorageRow() expect, with `amount` re-expressed as the positive
     * magnitude (mirrors formatRow(), but keyed for round-tripping through
     * validation rather than for display). Used by update() to build the
     * "merged row" patch fields get applied on top of.
     */
    private function rowToApiShape(object $row): array
    {
        return [
            'type1'      => $row->type1,
            'type2'      => $row->type2,
            'date'       => date('Y-m-d', (int) $row->acc_date),
            'amount'     => round(abs((float) $row->amount), 2),
            'kassa'      => $row->kassa,
            'channel'    => $row->channel,
            'info'       => $row->info,
            'dr_name_id' => (int) $row->dr_name_id,
            'link_to'    => (int) $row->link_to,
        ];
    }

    /** type2 exists AND is_active=1 in the dictionary matching type1. Stricter than type2Dictionary() (display), which is deliberately unfiltered by is_active. */
    private function type2ExistsActive(string $type1, string $type2): bool
    {
        if ($type1 === 'rash') {
            return DB::table('rash_items')->where('ri_code', $type2)->where('is_active', 1)->exists();
        }
        if ($type1 === 'doh') {
            return DB::table('doh_items')->where('rd_code', $type2)->where('is_active', 1)->exists();
        }
        return false;
    }

    /**
     * Live existence check against offices WHERE type='office' — never hardcode
     * office numbers. Existence gates this, not `active` (a closed office is
     * still a valid channel).
     *
     * `offices.number` is int(11), so MySQL coerces the compared string to an
     * int: '2abc', ' 2 ' and '01' would all match office 2 and pass validation,
     * yet toStorageRow() stores the ORIGINAL string verbatim into
     * `channel varchar(16)` — a row that then matches no office in any
     * downstream report. Require a canonical integer string up front so the
     * value that validates is exactly the value that gets stored.
     */
    private function officeExists(string $number): bool
    {
        if (!ctype_digit($number) || $number !== (string) (int) $number) {
            return false;
        }

        return DB::table('offices')->where('type', 'office')->where('number', $number)->exists();
    }

    /**
     * channel/kassa are a pair, not independently valid values:
     *   - kassa='bank' <=> channel='bank' (only valid together)
     *   - kassa in {k1,k2,card} requires channel to be an office number or 'cur'
     */
    private function channelKassaPairValid(?string $channel, ?string $kassa): bool
    {
        if ($kassa === 'bank') {
            return $channel === 'bank';
        }
        if ($channel === 'bank') {
            return false;
        }
        return $channel === 'cur' || $this->officeExists((string) $channel);
    }

    /**
     * The `api_system` logpass row's id — always the actor for MCP-API
     * writes to doh_rash (cr_who_id on create, actor_user_id in the
     * journal). Cached under TTL_META; throws if the Task 1 seed row is
     * somehow missing rather than silently guessing an id.
     */
    private function apiAuthorId(): int
    {
        return (int) $this->cacheRemember('mcp.finance_entries.api_system_id', self::TTL_META, function () {
            $id = DB::table('logpass')->where('log', 'api_system')->value('logpass_id');
            if (!$id) {
                throw new \RuntimeException('logpass row with log=api_system not found — has the Task 1 seed migration run?');
            }
            return (int) $id;
        });
    }

    /**
     * Appends one doh_rash_history row. Only 'update' and 'delete' are ever
     * journalled — an insert is already attributable from the doh_rash row
     * itself (cr_who_id + cr_time), so journalling it would just duplicate
     * data that already exists (see the migration docblock).
     *
     * Never throws in a way that fails an already-successful write — mirrors
     * BaseController::recordContentVersion()'s try/catch + Log::error()
     * pattern: a broken journal table must not roll back or fail a write
     * that already landed in doh_rash. Because of that, the catch block logs
     * the FULL payload (not just the exception message): for a delete the
     * journal row is the only copy of the vanished row, so if the insert fails
     * the application log has to be able to stand in as the recovery trace.
     *
     * $actorId lets the caller resolve the actor BEFORE the write it is
     * journalling (destroy() must — see its docblock); omitted, it is resolved
     * lazily here.
     */
    private function journal(string $action, object $before, ?object $after, ?int $actorId = null): void
    {
        $beforeJson = json_encode($before, JSON_UNESCAPED_UNICODE);
        $afterJson  = $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null;

        try {
            DB::table('doh_rash_history')->insert([
                'dr_id'         => (int) $before->dr_id,
                'action'        => $action,
                'before_json'   => $beforeJson,
                'after_json'    => $afterJson,
                'actor_user_id' => $actorId ?? $this->apiAuthorId(),
                'source'        => 'mcp_api',
                'ip'            => request()->ip(),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('doh_rash_history insert failed: ' . $e->getMessage(), [
                'dr_id'       => (int) $before->dr_id,
                'action'      => $action,
                'before_json' => $beforeJson,
                'after_json'  => $afterJson,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Row formatting
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Maps a doh_rash DB row to the response shape (exact key set — see
     * class docblock for the storage quirks this accounts for).
     */
    protected function formatRow(object $row, array $type2Map, array $createdByMap): array
    {
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
