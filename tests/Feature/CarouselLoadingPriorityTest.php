<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Слайдер главной (горизонтальная лента, все слайды в HTML сразу): высокий приоритет загрузки —
 * только у первого видимого слайда, остальные — loading="lazy".
 *
 * Ловушка (ноябрь 2026): fetchpriority="high" стоял на 5-м и 6-м слайдах, а у первого его не было.
 * Браузер качал невидимые слайды раньше первого баннера; на слабом 4G первый баннер появлялся
 * на 13-й секунде. Слайды правят вручную (включают/выключают сезонные), поэтому правило легко
 * потерять. См. docs/home_speed_plan_2026-11.md, п. 1.2.
 */
class CarouselLoadingPriorityTest extends TestCase
{
    public function test_only_first_slide_is_high_priority_and_rest_are_lazy(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 2) . '/resources/views/includes/carousel.blade.php');
        $blade = preg_replace('/\{\{--.*?--\}\}/s', '', $blade);

        preg_match_all('/<a class="slide".*?<\/a>/s', $blade, $slides);
        $slides = $slides[0];
        $this->assertNotEmpty($slides, 'В слайдере не найдено ни одного слайда.');

        foreach ($slides as $i => $slide) {
            $this->assertSame(1, preg_match('/<img\b[^>]*>/i', $slide, $img), "Слайд №" . ($i + 1) . " без <img>.");
            $img = $img[0];
            $n = $i + 1;

            if ($i === 0) {
                $this->assertStringContainsString('fetchpriority="high"', $img, "Первый слайд должен иметь fetchpriority=\"high\".");
                $this->assertStringNotContainsString('loading="lazy"', $img, 'Первый слайд не должен быть lazy — это главный баннер.');
            } else {
                $this->assertStringNotContainsString('fetchpriority="high"', $img, "Слайд №$n не первый — fetchpriority=\"high\" только у первого.");
                $this->assertStringContainsString('loading="lazy"', $img, "Слайд №$n не первый — нужен loading=\"lazy\".");
            }
        }
    }
}
