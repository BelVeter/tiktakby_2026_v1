<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Шрифты сайта: свои шрифты — сначала woff2 (в 2–3 раза легче .ttf/.otf) и font-display: swap;
 * Google Fonts — одним запросом. См. docs/home_speed_plan_2026-11.md, этап 2.
 *
 * Ловушка (ноябрь 2026): LetsBeGlyphs грузился как .ttf на 111 КБ ради одного заголовка на главной,
 * Roboto подключался дважды, у Nunito и Roboto были заказаны все 28 начертаний.
 */
class FontLoadingTest extends TestCase
{
    public function test_local_font_faces_use_woff2_first_and_swap(): void
    {
        $root = dirname(__DIR__, 2);
        $scss = file_get_contents($root . '/resources/sass/app.scss');

        preg_match_all('/@font-face\s*\{(.*?)\}/s', $scss, $faces);
        $this->assertNotEmpty($faces[1], 'В app.scss не найдено @font-face.');

        foreach ($faces[1] as $face) {
            $this->assertMatchesRegularExpression('/src:\s*url\("([^"]+\.woff2)"\)\s*format\("woff2"\)/', $face, "@font-face без woff2 первым источником:\n$face");
            $this->assertStringContainsString('font-display: swap', $face, "@font-face без font-display: swap:\n$face");

            preg_match('/url\("([^"]+\.woff2)"\)/', $face, $m);
            $this->assertFileExists($root . $m[1], "Файл шрифта {$m[1]} не существует.");
        }
    }

    public function test_google_fonts_loaded_with_single_request(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 2) . '/resources/views/layouts/app.blade.php');
        $blade = preg_replace('/<noscript>.*?<\/noscript>/s', '', $blade);
        $blade = preg_replace('/\{\{--.*?--\}\}/s', '', $blade);

        $this->assertSame(1, substr_count($blade, 'fonts.googleapis.com/css2'), 'Google Fonts должны подключаться одним запросом (раньше было три, Roboto — дважды).');
    }
}
