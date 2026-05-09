<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;

/**
 * GET /api/mcp/v1/health
 *
 * Lightweight liveness probe — does not touch the database so it can be polled
 * frequently by the MCP server / monitoring without impact.
 */
class HealthController extends BaseController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'query' => [],
            'data'  => [
                'status'    => 'ok',
                'version'   => config('mcp.version', '1.0.0'),
                'timestamp' => now()->toIso8601String(),
            ],
            'meta'  => [
                'currency' => 'BYN',
                'warnings' => [],
            ],
        ]);
    }
}
