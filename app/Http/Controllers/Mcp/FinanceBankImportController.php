<?php

namespace App\Http\Controllers\Mcp;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * POST /api/mcp/v1/finance/bank-import
 *
 * Idempotent batch write path for bank-statement lines (income + expense)
 * into doh_rash, kassa='bank' channel only. Closes the fy2025_bank_channel_gap
 * (D-OPEN-FY2025) reconciliation gap that previously required hand-run SQL
 * against production.
 *
 * See docs/superpowers/specs/2026-08-09-finance-bank-import-design.md for the
 * full rationale (sign convention, why type1 is whitelisted, why dr_name_id
 * stays 0, the idempotency key).
 */
class FinanceBankImportController extends BaseController
{
    private const MAX_BATCH_ITEMS = 200;

    /** type1 => [dictionary table, code column] */
    private const TYPE1_DICTIONARIES = [
        'rash' => ['rash_items', 'ri_code'],
        'doh'  => ['doh_items',  'rd_code'],
    ];

    private const ITEM_RULES = [
        'type1'       => 'required|in:rash,doh',
        'doc_n'       => 'required|string|max:64',
        'date'        => 'required|date_format:Y-m-d',
        // decimal:min,max isn't available before Laravel 9.16 — regex enforces
        // "at most 2 decimal places"; max: caps at what decimal(11,2) can hold.
        'amount'      => 'required|numeric|gt:0|max:999999999.99|regex:/^\d{1,9}(\.\d{1,2})?$/',
        'type2'       => 'required|string|max:64',
        'beneficiary' => 'required|string|max:500',
        'ground'      => 'required|string',
        'note'        => 'sometimes|nullable|string',
    ];

    /**
     * Body: {"dry_run": bool, "expenses": [{type1, doc_n, date, amount, type2, beneficiary, ground, note}]}
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'dry_run'  => 'sometimes|boolean',
            'expenses' => 'required|array|min:1|max:' . self::MAX_BATCH_ITEMS,
        ]);

        $dryRun = $request->boolean('dry_run');
        $items  = $request->input('expenses');

        $results = [];
        foreach ($items as $i => $item) {
            $results[] = $this->processItem((int) $i, is_array($item) ? $item : [], $dryRun);
        }

        $counts = array_count_values(array_column($results, 'status'));

        return $this->envelope([], $results, [
            'dry_run' => $dryRun,
            'summary' => [
                'inserted'     => $counts['inserted']     ?? 0,
                'would_insert' => $counts['would_insert'] ?? 0,
                'duplicate'    => $counts['duplicate']    ?? 0,
                'invalid'      => $counts['invalid']      ?? 0,
            ],
        ]);
    }

    /** @return array{index:int, doc_n:string, status:string, dr_id:?int, errors:?array} */
    private function processItem(int $index, array $item, bool $dryRun): array
    {
        $docN = is_string($item['doc_n'] ?? null) ? $item['doc_n'] : '';

        $validator = Validator::make($item, self::ITEM_RULES);
        if ($validator->fails()) {
            return $this->result($index, $docN, 'invalid', null, $validator->errors()->toArray());
        }
        $v = $validator->validated();

        [$dictTable, $dictCol] = self::TYPE1_DICTIONARIES[$v['type1']];
        $type2Active = DB::table($dictTable)->where($dictCol, $v['type2'])->where('is_active', 1)->exists();
        if (!$type2Active) {
            return $this->result($index, $docN, 'invalid', null, [
                'type2' => ["Unknown or inactive {$v['type1']} category '{$v['type2']}'."],
            ]);
        }

        $day       = Carbon::createFromFormat('Y-m-d', $v['date'], 'Europe/Minsk')->startOfDay();
        $accDate   = $day->timestamp;
        $windowLo  = $day->copy()->subDays(2)->timestamp;
        $windowHi  = $day->copy()->addDays(2)->timestamp;
        $amountAbs = round((float) $v['amount'], 2);

        $existingId = DB::table('doh_rash')
            ->where('kassa', 'bank')
            ->where('type1', $v['type1'])
            ->whereRaw('ABS(amount) = ?', [$amountAbs])
            ->whereBetween('acc_date', [$windowLo, $windowHi])
            // Leading % is required: stored info is prefixed "[AI] BANK#...", and
            // older rows from the May-2026 reconciliation are unprefixed "BANK#...".
            // A start-anchored pattern would never match either — see the design
            // spec's Idempotency section for the bug this avoids (present in the
            // draft PROPOSED_insert_2026-07.sql, which reuses the old unprefixed
            // pattern while also adding the new [AI] prefix).
            ->where('info', 'LIKE', '%BANK#' . $docN . ' %')
            ->value('dr_id');

        if ($existingId) {
            return $this->result($index, $docN, 'duplicate', (int) $existingId);
        }

        if ($dryRun) {
            return $this->result($index, $docN, 'would_insert');
        }

        $info = '[AI] BANK#' . $docN . ' ' . $v['beneficiary'] . ': ' . $v['ground'];
        if (!empty($v['note'] ?? null)) {
            $info .= ' ' . $v['note'];
        }

        $storedAmount = $v['type1'] === 'rash' ? -$amountAbs : $amountAbs;

        $drId = DB::table('doh_rash')->insertGetId([
            'acc_date'   => $accDate,
            'amount'     => $storedAmount,
            'type1'      => $v['type1'],
            'type2'      => $v['type2'],
            'channel'    => 'bank',
            'kassa'      => 'bank',
            'link_to'    => 0,
            'info'       => $info,
            'cr_time'    => now()->timestamp,
            'cr_who_id'  => $this->bankImportAuthorId(),
            'dr_name_id' => 0,
        ], 'dr_id');

        return $this->result($index, $docN, 'inserted', $drId);
    }

    private function result(int $index, string $docN, string $status, ?int $drId = null, ?array $errors = null): array
    {
        return [
            'index'  => $index,
            'doc_n'  => $docN,
            'status' => $status,
            'dr_id'  => $drId,
            'errors' => $errors,
        ];
    }

    /**
     * logpass_id of the dedicated 'API' system user, seeded by migration
     * 2026_08_09_120000_seed_api_system_logpass_user.php. Cached, never guessed.
     */
    private function bankImportAuthorId(): int
    {
        return (int) $this->cacheRemember('mcp.finance_bank_import_author_id', self::TTL_META, function () {
            $id = DB::table('logpass')->where('log', 'api_system')->value('logpass_id');
            if (!$id) {
                throw new \RuntimeException("logpass 'api_system' row is missing — run migrations.");
            }
            return $id;
        });
    }
}
