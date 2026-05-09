<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;

/**
 * Carnival-costume booking funnel and revenue under /api/mcp/v1/carnival/*.
 * Implemented in step A.11.
 *
 * Source tables: karn_brons (active) + karn_brons_arch — separate funnel
 * from regular rentals. Strong December seasonality.
 */
class CarnivalController extends BaseController
{
    public function funnel(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    public function seasonality(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }

    public function revenue(RangeRequest $request): JsonResponse
    {
        return $this->envelope($request->queryEcho(), []);
    }
}
