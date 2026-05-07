<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MCP API: аудит-лог каждого запроса в таблицу mcp_api_log.
 * Не сохраняет персональные данные в query_params.
 */
class McpAuditLogMiddleware
{
    // Параметры, которые могут содержать ПД — зачищаем перед логированием
    private const PD_PARAMS = ['name', 'fio', 'phone', 'email', 'address', 'passport'];

    public function handle(Request $request, Closure $next)
    {
        $startMs = (int) round(microtime(true) * 1000);

        $response = $next($request);

        $endMs      = (int) round(microtime(true) * 1000);
        $responseMs = $endMs - $startMs;

        // Очищаем query params от возможных ПД
        $params = $request->query();
        foreach (self::PD_PARAMS as $pd) {
            if (isset($params[$pd])) {
                $params[$pd] = '[REDACTED]';
            }
        }

        try {
            DB::table('mcp_api_log')->insert([
                'created_at'   => now(),
                'ip'           => $request->ip(),
                'method'       => $request->method(),
                'endpoint'     => '/' . ltrim($request->path(), '/'),
                'query_params' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'status_code'  => $response->getStatusCode(),
                'response_ms'  => $responseMs,
                'user_agent'   => substr($request->userAgent() ?? '', 0, 255),
            ]);
        } catch (\Exception $e) {
            // Не ломаем ответ если лог не пишется
            \Log::error('McpAuditLog failed: ' . $e->getMessage());
        }

        return $response;
    }
}
