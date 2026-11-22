<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Листинги и страница товара: картинки ниже первого экрана — loading="lazy", первое фото товара — в приоритете.
 *
 * Ловушка (ноябрь 2026): листинг сразу качал картинки всех ~24 карточек, страница товара — все фото галереи
 * и рекомендаций. На слабом 4G они отнимали канал у CSS: первый экран листинга 2.4 с вместо 1.5 с,
 * «Карнавальные костюмы» грузились 14 с. См. docs/home_speed_plan_2026-11.md, этап 4.
 */
class CatalogLazyLoadingTest extends TestCase
{
    private function bladeSource(string $name): string
    {
        $s = file_get_contents(dirname(__DIR__, 2) . '/resources/views/' . $name);

        return preg_replace('/\{\{--.*?--\}\}/s', '', $s);
    }

    public function test_listing_passes_lazy_flag_after_first_cards(): void
    {
        foreach (['catpage.blade.php', 'search.blade.php'] as $view) {
            $s = $this->bladeSource($view);
            preg_match_all("/@include\('includes\.l2(karn)?_model_block'[^)]*\)/", $s, $m);
            $this->assertNotEmpty($m[0], "$view: не найдено подключение карточки листинга.");
            foreach ($m[0] as $include) {
                $this->assertStringContainsString("'lazy' => \$loop->index >= 4", $include, "$view: карточкам после первых 4 нужен 'lazy' => \$loop->index >= 4.");
            }
        }
    }

    public function test_listing_card_images_honour_lazy_flag(): void
    {
        foreach (['includes/l2_model_block.blade.php' => 'l2-card_img', 'includes/l2karn_model_block.blade.php' => 'l2karn-card_img'] as $view => $class) {
            $this->assertMatchesRegularExpression(
                // [^\n]* а не [^>]*: в Blade-выражениях есть «->» ($l2->getPicUrl())
                '/<img[^\n]*class="' . $class . '"[^\n]*@if\(!empty\(\$lazy\)\) loading="lazy" decoding="async" @endif>/',
                $this->bladeSource($view),
                "$view: картинка карточки должна получать loading=\"lazy\", когда передан \$lazy."
            );
        }
    }

    public function test_product_gallery_first_photo_priority_rest_lazy(): void
    {
        foreach (['l3.blade.php', 'l3_not_found.blade.php'] as $view) {
            $s = $this->bladeSource($view);
            $this->assertMatchesRegularExpression(
                '/class="l3__slider__big_pic .{0,300}?@if\(\$index == 0\) fetchpriority="high" @else loading="lazy" decoding="async" @endif>/s',
                $s,
                "$view: первое фото галереи — fetchpriority=\"high\", остальные — loading=\"lazy\"."
            );
            $this->assertMatchesRegularExpression(
                '/<img class="main-img"[^\n]*loading="lazy"/',
                $s,
                "$view: картинки блока «Вам может понравится» — loading=\"lazy\"."
            );
        }
    }
}
