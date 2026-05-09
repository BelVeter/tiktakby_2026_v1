<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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

    /**
     * GET /api/mcp/v1/openapi.json
     *
     * Serves the static OpenAPI 3.0 spec from resources/openapi/mcp-v1.json
     * so MCP-server tooling (openapi-mcp-generator) can auto-generate tool
     * definitions for Phase B.
     */
    public function openapi(): Response
    {
        $path = resource_path('openapi/mcp-v1.json');
        if (!is_file($path)) {
            abort(500, 'OpenAPI spec is missing.');
        }
        return response((string) file_get_contents($path), 200, [
            'Content-Type'  => 'application/json',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
