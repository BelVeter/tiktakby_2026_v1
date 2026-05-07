<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use GeoIp2\Database\Reader;

/**
 * MCP API: ограничение доступа по стране через GeoLite2.
 * Допустимые страны задаются в .env: MCP_GEO_ALLOWED_COUNTRIES=BY,RU
 * Если переменная пуста — пропускаем всех (для разработки).
 */
class McpGeoCountryMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $allowed = config('mcp.geo_allowed_countries'); // массив кодов стран, e.g. ['BY']

        // Если список пуст — режим разработки, пропускаем всех
        if (empty($allowed)) {
            return $next($request);
        }

        $ip = $request->ip();

        // Локальные адреса всегда пропускаем
        if ($this->isLocalIp($ip)) {
            return $next($request);
        }

        $dbPath = storage_path('app/geoip/GeoLite2-Country.mmdb');

        if (!file_exists($dbPath)) {
            // Если база не найдена — логируем и пропускаем (fail open для dev)
            \Log::warning('MCP GeoIP: database not found at ' . $dbPath);
            return $next($request);
        }

        try {
            $reader  = new Reader($dbPath);
            $record  = $reader->country($ip);
            $country = $record->country->isoCode; // e.g. 'BY'
        } catch (\Exception $e) {
            // IP не определён (частные диапазоны, VPN и т.п.) — пропускаем
            \Log::warning('MCP GeoIP: lookup failed for ' . $ip . ': ' . $e->getMessage());
            return $next($request);
        }

        if (!in_array($country, $allowed, true)) {
            return response()->json([
                'data'   => null,
                'meta'   => [],
                'errors' => [['code' => 'FORBIDDEN', 'message' => 'Access denied: country not allowed']],
            ], 403);
        }

        return $next($request);
    }

    private function isLocalIp(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1'], true)
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.')
            || str_starts_with($ip, '172.');
    }
}
