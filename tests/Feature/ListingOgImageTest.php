<?php

namespace Tests\Feature;

use App\MyClasses\L2ModelWeb;
use App\MyClasses\MainPage;
use Tests\TestCase;

/**
 * Превью ссылки на листинг (og:image) — картинка этой категории, а не общая картинка сайта.
 *
 * Ловушка (ноябрь 2026): листинги не задавали og:image и брали картинку по умолчанию из layouts/app
 * (коляска Cybex) — ссылка на «Костюм пирата» в Viber/Instagram показывала коляску.
 * Порядок: шапка категории → фото первого товара → null (картинка по умолчанию). Только существующий файл.
 */
class ListingOgImageTest extends TestCase
{
    private const EXISTING = '/public/img/home/insta-doona.webp';      // отслеживается git
    private const EXISTING_2 = '/public/img/home/insta-hobot-388.webp';

    private function page(?string $h1Pic, array $modelPics): MainPage
    {
        $page = (new \ReflectionClass(MainPage::class))->newInstanceWithoutConstructor();
        $page->setH1PicUrl($h1Pic);

        $models = [];
        foreach ($modelPics as $pic) {
            $m = $this->createMock(L2ModelWeb::class);
            $m->method('getPicUrl')->willReturn($pic);
            $models[] = $m;
        }
        $prop = new \ReflectionProperty(MainPage::class, 'l2ModelsWeb');
        $prop->setAccessible(true);
        $prop->setValue($page, $models);

        return $page;
    }

    public function test_category_header_picture_wins(): void
    {
        $this->assertSame('https://tiktak.by' . self::EXISTING, $this->page(self::EXISTING, [self::EXISTING_2])->getOgImageUrl());
    }

    public function test_falls_back_to_first_product_picture(): void
    {
        $this->assertSame('https://tiktak.by' . self::EXISTING_2, $this->page(null, [self::EXISTING_2, self::EXISTING])->getOgImageUrl());
        $this->assertSame('https://tiktak.by' . self::EXISTING_2, $this->page('', [self::EXISTING_2])->getOgImageUrl());
    }

    public function test_missing_file_is_skipped(): void
    {
        $this->assertSame('https://tiktak.by' . self::EXISTING_2, $this->page('/public/img/nope/missing.jpg', [self::EXISTING_2])->getOgImageUrl());
        $this->assertNull($this->page('/public/img/nope/missing.jpg', ['/public/rent/images/nope/missing.webp'])->getOgImageUrl());
    }

    public function test_no_pictures_keeps_site_default(): void
    {
        $this->assertNull($this->page(null, [])->getOgImageUrl());
    }

    public function test_cyrillic_and_spaces_are_url_encoded(): void
    {
        $dir = base_path('public/img/home');
        $name = 'Тест пробел.webp';
        copy($dir . '/insta-doona.webp', $dir . '/' . $name);
        try {
            $expected = 'https://tiktak.by/public/img/home/' . rawurlencode($name);
            $this->assertSame($expected, $this->page('/public/img/home/' . $name, [])->getOgImageUrl(), 'сырое имя');
            $this->assertSame($expected, $this->page('/public/img/home/' . rawurlencode($name), [])->getOgImageUrl(), 'уже закодированное имя не кодируется второй раз');
        } finally {
            @unlink($dir . '/' . $name);
        }
    }

    public function test_catpage_uses_listing_og_image(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 2) . '/resources/views/catpage.blade.php');
        $this->assertStringContainsString("@section('og-image', \$p->getOgImageUrl())", $blade);
    }
}
