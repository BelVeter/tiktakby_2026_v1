<?php

namespace Tests\Unit;

use bb\classes\AdminSecurity;
use PHPUnit\Framework\TestCase;

/**
 * Заголовки безопасности страниц bb/ (подключаются из auto_prepend_file PageVisitTracker.php).
 */
class AdminSecurityTest extends TestCase
{
    public function test_enforced_policy_only_has_directives_that_cannot_break_pages(): void
    {
        $h = AdminSecurity::headers();
        $this->assertSame("frame-ancestors 'self'; object-src 'none'; base-uri 'self'", $h['Content-Security-Policy']);
        $this->assertSame('same-origin', $h['Referrer-Policy']);
        $this->assertSame('SAMEORIGIN', $h['X-Frame-Options']);
    }

    public function test_report_only_policy_keeps_images_and_requests_on_our_site(): void
    {
        $p = AdminSecurity::reportOnlyPolicy();
        $directives = [];
        foreach (explode('; ', $p) as $d) {
            $parts = explode(' ', $d);
            $directives[array_shift($parts)] = $parts;
        }
        $this->assertSame(["'self'"], $directives['connect-src']);
        $this->assertContains("'self'", $directives['img-src']);
        // ни «любой https», ни «*» — иначе спам-картинки снова грузились бы
        $this->assertNotContains('https:', $directives['img-src']);
        $this->assertNotContains('*', $directives['img-src']);
        $this->assertSame(['/csp-report'], $directives['report-uri']);
    }

    /** @dataProvider httpsCases */
    public function test_is_https(array $server, bool $expected): void
    {
        $this->assertSame($expected, AdminSecurity::isHttps($server));
    }

    public function httpsCases(): array
    {
        return [
            'direct https' => [['HTTPS' => 'on'], true],
            'behind nginx' => [['HTTP_X_FORWARDED_PROTO' => 'https', 'SERVER_PORT' => '80'], true],
            'port 443' => [['SERVER_PORT' => '443'], true],
            'iis off' => [['HTTPS' => 'off', 'SERVER_PORT' => '80'], false],
            'local http' => [['SERVER_PORT' => '80'], false],
        ];
    }
}
