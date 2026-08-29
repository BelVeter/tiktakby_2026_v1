<?php

namespace Tests\Feature\Mcp;

use Tests\TestCase;

/**
 * Production runs MySQL 5.7.44 (verified via SELECT VERSION() 2026-08-29),
 * not MariaDB 10.6 as CLAUDE.md states — REGEXP_REPLACE only exists on
 * MySQL 8+/MariaDB 10.0.5+, so any query using it throws "FUNCTION ...
 * REGEXP_REPLACE does not exist" on prod while working fine against the
 * local Docker DB (MariaDB 10.6), which is exactly how the bug went
 * unnoticed for 3 months (54 failures in laravel.log since 2026-05-24,
 * countDealsBySource() in OperationsController.php). Use
 * BaseController::phoneDigitsSql() instead — same effect via REPLACE(),
 * compatible with both.
 */
class RegexpReplaceCompatTest extends TestCase
{
    public function test_no_regexp_replace_in_app_sql(): void
    {
        $root = dirname(__DIR__, 3) . '/app';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $offenders = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = file_get_contents($file->getPathname());
            if (stripos($code, 'REGEXP_REPLACE(') !== false) {
                $offenders[] = str_replace(dirname(__DIR__, 3) . '/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders,
            'REGEXP_REPLACE() does not exist on production (MySQL 5.7) — use BaseController::phoneDigitsSql() or another MySQL 5.7-compatible approach.');
    }
}
