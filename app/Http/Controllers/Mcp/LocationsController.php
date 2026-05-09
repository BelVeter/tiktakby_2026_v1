<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;

/**
 * Per-location performance under /api/mcp/v1/locations/*.
 * Implemented in step A.9.
 *
 * Aggregations are keyed on rent_deals_arch.first_rent_place which references
 * offices.id. Pobediteley (id=3) closed 2022-07; Lozhinskaya (id=2) trailed
 * off through 2025-2026 — see decisions_log.md D-OPEN-LOCATIONS.
 */
class LocationsController extends BaseController
{
    public function performance(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    public function lifecycle(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }
}
