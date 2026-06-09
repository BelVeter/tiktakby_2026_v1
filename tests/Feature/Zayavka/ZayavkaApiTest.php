<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;

class ZayavkaApiTest extends TestCase
{
    public function test_endpoint_has_no_syntax_errors(): void
    {
        $out = shell_exec('php -l ' . escapeshellarg(base_path('bb/zayavka_api.php')) . ' 2>&1');
        $this->assertStringContainsString('No syntax errors', (string) $out);
    }
}
