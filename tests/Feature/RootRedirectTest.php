<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Корень сайта `/` → `/ru` (301). Редирект обязан сохранять query string: реклама и рассылки
 * ведут на https://tiktak.by/?gclid=…&utm_*=…, а до 22.11.2026 PHP-редирект отбрасывал параметры,
 * и GA4 / Google Ads / Метрика теряли источник визита (docs/home_speed_plan_2026-11.md, п. 3.1).
 *
 * На проде редирект делает корневой .htaccess (без PHP); Laravel-маршрут остаётся запасным.
 */
class RootRedirectTest extends TestCase
{
    public function test_laravel_fallback_redirect_keeps_query_string(): void
    {
        $response = $this->get('/?utm_source=google&utm_medium=cpc&gclid=abc123');

        $response->assertStatus(301);
        $location = $response->headers->get('Location');
        $this->assertSame('/ru', parse_url($location, PHP_URL_PATH));
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(['utm_source' => 'google', 'utm_medium' => 'cpc', 'gclid' => 'abc123'], $query);
    }

    public function test_laravel_fallback_redirect_without_query_has_no_question_mark(): void
    {
        $response = $this->get('/');

        $response->assertStatus(301);
        $this->assertStringEndsWith('/ru', $response->headers->get('Location'));
    }

    public function test_root_htaccess_redirects_root_before_front_controller(): void
    {
        $h = file_get_contents(dirname(__DIR__, 2) . '/.htaccess');

        $rule = 'RewriteRule ^$ https://%{HTTP:Host}/ru [R=301,L]';
        $root = strpos($h, $rule);
        $front = strpos($h, 'RewriteRule ^ index.php [L]');

        $this->assertNotFalse($root, 'Нет правила `/` → `/ru` в корневом .htaccess (редирект уходит в PHP: +0.2 с TTFB и лишние Set-Cookie).');
        $this->assertNotFalse($front, 'Не найден front controller (RewriteRule ^ index.php [L]).');
        $this->assertLessThan($front, $root, 'Редирект корня должен стоять до front controller.');
        // Подстановка без «?» и без флага QSD — mod_rewrite сам допишет исходный query string.
        $this->assertStringNotContainsString('/ru? ', $h);
        $this->assertDoesNotMatchRegularExpression('/RewriteRule \^\$ .*QSD/', $h);
    }
}
