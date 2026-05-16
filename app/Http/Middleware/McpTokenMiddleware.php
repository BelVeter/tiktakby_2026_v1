<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * MCP API: аутентификация через Bearer-токен из .env MCP_API_TOKEN
 */
class McpTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = config('mcp.api_token');

        if (empty($token)) {
            return $this->error(401, 'UNAUTHORIZED', 'MCP API token is not configured on server');
        }

        $authHeader = $request->header('Authorization', '');
        $provided = '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $provided = substr($authHeader, 7);
        }

        if (empty($provided) || !hash_equals($token, $provided)) {
            return $this->error(401, 'UNAUTHORIZED', 'Invalid or missing Bearer token');
        }

        return $next($request);
    }

    private function error(int $status, string $code, string $message)
    {
        return response()->json([
            'data'   => null,
            'meta'   => [],
            'errors' => [['code' => $code, 'message' => $message]],
        ], $status);
    }
}
