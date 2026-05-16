<?php

namespace App\Http\Requests\Mcp;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Common Form Request for MCP analytics endpoints accepting a date range
 * plus optional dimensional filters (category, location, region, segment).
 *
 * Defaults (see api_stage1_implementation.md A.2):
 *   - from / to:    last 12 full months ending today
 *   - granularity:  month
 *   - category:     all
 *   - region:       all
 *   - location:     all
 *   - segment:      all
 *
 * Endpoint-specific subclasses may override rules() to constrain enums or
 * mark `from`/`to` as required.
 */
class RangeRequest extends FormRequest
{
    public const GRANULARITIES = ['day', 'week', 'month', 'quarter', 'year'];

    public const CATEGORIES = ['all', 'children', 'costumes', 'medical', 'cleaning', 'sports', 'tools'];

    public const SEGMENTS = ['all', 'b2c', 'b2b'];

    public const REGIONS = ['all', 'belarus', 'minsk', 'vitebsk', 'grodno', 'mogilev', 'gomel', 'brest'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'             => ['nullable', 'date'],
            'to'               => ['nullable', 'date', 'after_or_equal:from'],
            'granularity'      => ['nullable', 'string', 'in:' . implode(',', self::GRANULARITIES)],
            'category'         => ['nullable', 'array'],
            'category.*'       => ['string', 'in:' . implode(',', self::CATEGORIES)],
            'segment'          => ['nullable', 'string', 'in:' . implode(',', self::SEGMENTS)],
            'region'           => ['nullable', 'string', 'in:' . implode(',', self::REGIONS)],
            'location'         => ['nullable'], // string 'all' or numeric office id - validated below
            'include_carnival' => ['nullable'], // truthy/falsy — coerced in includeCarnival()
        ];
    }

    /**
     * Apply defaults before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $merged = [];

        if (!$this->filled('from') && !$this->filled('to')) {
            $merged['from'] = Carbon::now()->subMonthsNoOverflow(12)->startOfMonth()->toDateString();
            $merged['to']   = Carbon::now()->endOfDay()->toDateString();
        }

        $cat = $this->input('category');
        if ($cat === null || $cat === '') {
            $merged['category'] = ['all'];
        } elseif (is_string($cat)) {
            $merged['category'] = array_map('trim', explode(',', $cat));
        }

        foreach (['granularity' => 'month', 'segment' => 'all', 'region' => 'all', 'location' => 'all', 'include_carnival' => 'true'] as $k => $default) {
            if (!$this->filled($k) && $this->input($k) === null) {
                $merged[$k] = $default;
            }
        }

        if ($merged) {
            $this->merge($merged);
        }
    }

    /**
     * Get validated categories as an array of valid slugs.
     * Fallback to ['all'] if empty or invalid.
     */
    public function categories(): array
    {
        $cats = $this->input('category');
        if (!is_array($cats)) {
            return ['all'];
        }
        
        $valid = array_intersect($cats, self::CATEGORIES);
        if (empty($valid) || in_array('all', $valid, true)) {
            return ['all'];
        }
        
        return array_values(array_unique($valid));
    }

    /**
     * Parse include_carnival as a strict boolean.
     * Treats '0', 'false', 'no', 'off', '' as false; everything else as true.
     * Default is true (carnival items contribute to revenue/deal aggregates).
     */
    public function includeCarnival(): bool
    {
        $v = $this->input('include_carnival');
        if ($v === null) return true;
        if (is_bool($v)) return $v;
        $s = strtolower(trim((string) $v));
        return !in_array($s, ['0', 'false', 'no', 'off', '', 'n'], true);
    }

    public function fromTimestamp(): int
    {
        return Carbon::parse($this->input('from'))->startOfDay()->timestamp;
    }

    public function toTimestamp(): int
    {
        return Carbon::parse($this->input('to'))->endOfDay()->timestamp;
    }

    /**
     * Echo of normalized request parameters for inclusion in `query` envelope field.
     *
     * @return array<string,mixed>
     */
    public function queryEcho(): array
    {
        return [
            'from'             => $this->input('from'),
            'to'               => $this->input('to'),
            'granularity'      => $this->input('granularity'),
            'category'         => implode(',', $this->categories()),
            'segment'          => $this->input('segment'),
            'region'           => $this->input('region'),
            'location'         => $this->input('location'),
            'include_carnival' => $this->includeCarnival(),
        ];
    }

    /**
     * MySQL DATE_FORMAT expression matching the requested granularity.
     * Falls back to month for unknown values (already filtered by validator).
     */
    public function granularityFormat(): string
    {
        switch ($this->input('granularity')) {
            case 'day':     return "DATE_FORMAT(FROM_UNIXTIME({col}), '%Y-%m-%d')";
            case 'week':    return "DATE_FORMAT(FROM_UNIXTIME({col}), '%x-W%v')";
            case 'quarter': return "CONCAT(YEAR(FROM_UNIXTIME({col})), '-Q', QUARTER(FROM_UNIXTIME({col})))";
            case 'year':    return "DATE_FORMAT(FROM_UNIXTIME({col}), '%Y')";
            case 'month':
            default:        return "DATE_FORMAT(FROM_UNIXTIME({col}), '%Y-%m')";
        }
    }

    /**
     * Convenience: returns granularityFormat() with {col} replaced by a column name.
     */
    public function granularityFormatFor(string $column): string
    {
        return str_replace('{col}', $column, $this->granularityFormat());
    }
}
