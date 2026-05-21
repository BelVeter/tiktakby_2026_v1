<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * MCP API: forces JSON responses even for clients that omit Accept headers.
 *
 * Without this Laravel's FormRequest validators issue a 302 redirect on
 * validation failure (HTML default), which is useless for the MCP server.
 * Setting Accept: application/json early in the pipeline makes the framework
 * return 422 JSON instead.
 */
class McpForceJsonMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}
