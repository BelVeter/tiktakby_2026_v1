<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;

/**
 * Geographic breakdown under /api/mcp/v1/geo/*.
 * Implemented in step A.8.
 *
 * Stage 1 ships only city-level grouping. Minsk-district resolution requires
 * a free-text address parser and is deferred to Stage 2 (api_stage2_geo.md).
 */
class GeoController extends BaseController
{
    public function clientsByCity(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }
}
