<?php

namespace Tests\Feature;

use bb\classes\Category;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Категория с алиасом (Category::URL_ALIASES, сейчас Биоптрон): «настоящий» адрес отвечает 301 на алиас,
 * поэтому внутренние ссылки должны вести сразу на алиас, а не через лишний редирект.
 *
 * Тесты берут реальные данные dev-БД и пропускаются, если подходящих строк нет.
 */
class CategoryUrlAliasTest extends TestCase
{
    private const REAL = '/ru/medical-prokat/bioptron-prokat-minsk/prokat-bioptron-minsk';
    private const ALIAS = '/ru/medical-prokat/bioptron';

    public function test_alias_map_matches_the_route_redirect(): void
    {
        foreach (Category::URL_ALIASES as $real => $alias) {
            $this->get($real)->assertStatus(301)->assertRedirect($alias);
        }
    }

    public function test_category_url_for_page_returns_the_alias(): void
    {
        $category = Category::getByUrlName('prokat-bioptron-minsk', 'ru');
        if (!$category) {
            $this->markTestSkipped('В dev-БД нет категории prokat-bioptron-minsk.');
        }

        $this->assertSame(self::ALIAS, $category->getUrlForPage('ru'));
    }

    public function test_menus_and_lists_link_straight_to_the_alias(): void
    {
        foreach (['/ru/medical-prokat', '/ru/medical-prokat/bioptron-prokat-minsk'] as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            $this->assertStringNotContainsString('href="' . self::REAL . '"', $html, "$page ссылается на редиректящий адрес категории");
        }

        $html = $this->get('/ru/medical-prokat')->getContent();
        $this->assertStringContainsString('href="' . self::ALIAS . '"', $html, 'В списке категорий нет ссылки на алиас');
    }

    public function test_product_breadcrumbs_link_to_the_alias(): void
    {
        $slug = DB::table('rent_model_web as w')
            ->join('tovar_rent as tr', 'tr.tovar_rent_id', '=', 'w.model_id')
            ->join('tovar_rent_cat as c', 'c.tovar_rent_cat_id', '=', 'tr.tovar_rent_cat_id')
            ->where('c.cat_url_key', 'prokat-bioptron-minsk')
            ->where('w.lang', 'ru')->where('w.status', 'show')
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('tovar_rent_items as ti')->whereColumn('ti.model_id', 'w.model_id');
            })
            ->value('w.page_addr');
        if (!$slug) {
            $this->markTestSkipped('В dev-БД нет живой модели категории Биоптрона.');
        }

        $html = $this->get(self::REAL . '/' . $slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('href="' . self::REAL . '"', $html);
        $this->assertStringContainsString('href="' . self::ALIAS . '"', $html, 'Хлебные крошки не ведут на алиас');
    }

    public function test_legacy_category_route_redirects_straight_to_the_alias(): void
    {
        if (!Category::getByUrlName('prokat-bioptron-minsk', 'ru')) {
            $this->markTestSkipped('В dev-БД нет категории prokat-bioptron-minsk.');
        }

        $this->get('/ru/prokat/prokat-bioptron-minsk')->assertStatus(301)->assertRedirect(self::ALIAS);
    }
}
