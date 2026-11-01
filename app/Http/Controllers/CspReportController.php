<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Принимает отчёты браузера о нарушениях Content-Security-Policy-Report-Only со страниц bb/
 * (см. bb\classes\AdminSecurity) и пишет их в storage/logs/csp-*.log. По этим отчётам видно,
 * какие внешние ресурсы реально нужны админке, прежде чем включать политику обязательно.
 */
class CspReportController extends Controller
{
    public function store(Request $req)
    {
        $data = json_decode(substr((string)$req->getContent(), 0, 8192), true);
        $r = is_array($data) ? ($data['csp-report'] ?? null) : null;

        if (is_array($r)) {
            Log::channel('csp')->warning('csp', [
                'page' => self::cut($r['document-uri'] ?? ''),
                'directive' => self::cut($r['effective-directive'] ?? ($r['violated-directive'] ?? '')),
                'blocked' => self::cut($r['blocked-uri'] ?? ''),
                'source' => self::cut($r['source-file'] ?? ''),
                'line' => (int)($r['line-number'] ?? 0),
            ]);
        }

        return response()->noContent();
    }

    private static function cut($v): string
    {
        return mb_substr(is_scalar($v) ? (string)$v : '', 0, 300);
    }
}
