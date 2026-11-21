<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * public/.htaccess — отдельный набор правил: у папки public/ свой RewriteEngine, правила корневого
 * .htaccess сюда не наследуются, а $1 считается от public/. Обе ловушки стоили трафика (ноябрь 2026),
 * см. docs/home_speed_plan_2026-11.md, п. 1.4 и 1.6.
 */
class PublicHtaccessRulesTest extends TestCase
{
    private function htaccess(): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/public/.htaccess');
    }

    public function test_missing_static_files_never_reach_laravel(): void
    {
        $h = $this->htaccess();
        $static = strpos($h, 'RewriteRule \.(avif|webp|jpg|jpeg|png|gif|svg|ico|css|js|woff|woff2|ttf|eot)$ - [L]');
        $front = strpos($h, 'RewriteRule ^ index.php [L]');

        $this->assertNotFalse($static, 'Нет правила «статика — мимо Laravel»: битая картинка отдаёт HTML-страницу 404 на ~212 КБ через PHP.');
        $this->assertNotFalse($front, 'Не найден front controller (RewriteRule ^ index.php [L]).');
        $this->assertLessThan($front, $static, 'Правило для статики должно стоять до front controller.');
    }

    public function test_www_redirect_keeps_public_prefix(): void
    {
        $h = $this->htaccess();

        $this->assertStringNotContainsString('https://%1/$1', $h, 'www-редирект через $1 теряет /public: www.tiktak.by/public/x.jpg → tiktak.by/x.jpg (404).');
        $this->assertStringContainsString('https://%1%{REQUEST_URI}', $h, 'www-редирект должен брать полный путь из %{REQUEST_URI}.');
    }
}
