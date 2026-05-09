<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/mcp/v1/export/monthly/{topic}
 *
 * CSV export for regular backfill of /home/dmitry/Documents/прокат/04_analytics/
 * data/monthly/*.csv. Format must match data/monthly/_schema.md exactly.
 *
 * Implemented in step A.12.
 */
class ExportController extends BaseController
{
    public function monthly(Request $request, string $topic): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['period']);
            fclose($out);
        }, $topic . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
