<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Shared functionality for MCP Analytics API controllers.
 *
 * - envelope(): wraps responses in the {query, data, meta} format consumed by
 *   the local MCP server (see /home/dmitry/.mcp-servers/connectors/tiktak-mcp/).
 * - dataFreshness(): timestamp of the most recent business write across the
 *   hot tables, cached per-process.
 * - cacheRemember(): thin wrapper over Cache::remember that turns null TTLs
 *   into "no cache" so individual endpoints can opt out.
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
                    COALESCE((SELECT MAX(cr_time) FROM rent_deals_arch),  0),
                    COALESCE((SELECT MAX(acc_date) FROM doh_rash),        0),
                    COALESCE((SELECT MAX(cr_time) FROM karn_brons),       0),
                    COALESCE((SELECT MAX(cr_time) FROM clients),          0),
                    COALESCE((SELECT MAX(cr_time) FROM rent_orders_arch), 0)
                ) AS ts
            ");
            $ts = (int) ($row->ts ?? 0);
            return $ts > 0 ? gmdate('Y-m-d\TH:i:s\Z', $ts) : null;
        });
    }

    /**
     * Cache::remember when ttl > 0; pass-through when ttl is null/0.
     *
     * @param  string $key
     * @param  int|null $ttl seconds; 0 or null disables caching
     * @param  \Closure $callback
     * @return mixed
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
     *
     * @param array<string,mixed> $params
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
     * Default cache TTL hierarchy (see api_stage1_implementation.md A.2).
     * Subclasses pick the bucket that matches the endpoint cost.
     */
    protected const TTL_META    = 86400; // 24h - metadata, rarely changes
    protected const TTL_HEAVY   = 3600;  // 1h  - large aggregations
    protected const TTL_DEFAULT = 300;   // 5m  - normal endpoints
    protected const TTL_NONE    = 0;     // disabled
}
