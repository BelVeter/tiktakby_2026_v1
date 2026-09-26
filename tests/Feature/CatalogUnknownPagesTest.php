<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateSitemap;
use bb\classes\Category;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Выдуманные адреса каталога не отдают 200 «Раздел не найден.» (soft-404).
 *
 * Страница подраздела/категории настоящая, только если по имени нашлись все сущности цепочки
 * (MainPage::isRealPage). Если цепочка не сошлась, отвечает тот же фолбэк, что и для прочих
 * неизвестных адресов (RedirectController::notFound): 301 на канонический адрес по последнему
 * сегменту, если он настоящий, иначе 404.
 *
 * Тесты берут реальные данные dev-БД и пропускаются, если подходящих строк нет.
 */
class CatalogUnknownPagesTest extends TestCase
{
    private function chain(): object
    {
        $hidden = Category::hiddenCatIdsSql();
        $row = DB::selectOne("
            SELECT r.url_razdel_name AS razdel, sr.url_sub_razdel_name AS sub, c.cat_url_key AS cat
            FROM tovar_rent_cat c
            JOIN sub_razdel sr ON sr.id_sub_razdel = c.main_sub_razdel_id
            JOIN razdel r ON r.id_razdel = sr.main_razdel_id
            WHERE c.cat_url_key != '' AND sr.url_sub_razdel_name != '' AND r.url_razdel_name != ''
              AND c.tovar_rent_cat_id NOT IN ($hidden)
              AND c.cat_url_key <> 'prokat-bioptron-minsk'
              AND (SELECT COUNT(*) FROM tovar_rent_cat c2 WHERE c2.cat_url_key = c.cat_url_key) = 1
              AND (SELECT COUNT(*) FROM sub_razdel s2 WHERE s2.url_sub_razdel_name = sr.url_sub_razdel_name) = 1
              AND EXISTS (SELECT 1 FROM tovar_rent tr
                          JOIN tovar_rent_items ti ON ti.model_id = tr.tovar_rent_id
                          WHERE tr.tovar_rent_cat_id = c.tovar_rent_cat_id)
            ORDER BY c.tovar_rent_cat_id LIMIT 1");
        if (!$row) {
            $this->markTestSkipped('В dev-БД нет подходящей цепочки раздел/подраздел/категория.');
        }

        return $row;
    }

    public function test_real_pages_at_every_level_still_answer_200(): void
    {
        $c = $this->chain();

        $this->get("/ru/{$c->razdel}")->assertOk();
        $this->get("/ru/{$c->razdel}/{$c->sub}")->assertOk();
        $this->get("/ru/{$c->razdel}/{$c->sub}/{$c->cat}")->assertOk();
    }

    public function test_every_listing_page_from_the_sitemap_answers_200(): void
    {
        $base = 'https://tiktak.by';
        $checked = 0;

        foreach (app(GenerateSitemap::class)->collectUrls() as $u) {
            $path = substr($u['loc'], strlen($base));
            $depth = count(array_filter(explode('/', $path)));
            if ($depth < 2 || $depth > 4) {
                continue; // главную (глубина 1) и карточки моделей (5) не проверяем: здесь разделы, подразделы, категории и статические страницы
            }

            $this->get($path)->assertOk("Страница каталога из sitemap не отвечает 200: $path");
            $checked++;
        }

        $this->assertGreaterThan(50, $checked, 'Не проверено ни одной страницы каталога.');
    }

    public function test_unknown_category_under_real_subrazdel_is_404(): void
    {
        $c = $this->chain();

        $this->get("/ru/{$c->razdel}/{$c->sub}/zzzz-nonexistent-category")->assertNotFound();
    }

    public function test_unknown_subrazdel_under_real_razdel_is_404(): void
    {
        $c = $this->chain();

        $this->get("/ru/{$c->razdel}/zzzz-nonexistent-sub")->assertNotFound();
    }

    public function test_real_category_under_garbage_ancestors_redirects_to_canonical_url(): void
    {
        $c = $this->chain();
        $canonical = "/ru/{$c->razdel}/{$c->sub}/{$c->cat}";

        $this->get("/ru/zzzz-garbage/{$c->sub}/{$c->cat}")->assertStatus(301)->assertRedirect($canonical);
        $this->get("/ru/{$c->razdel}/zzzz-garbage/{$c->cat}")->assertStatus(301)->assertRedirect($canonical);
    }

    public function test_real_subrazdel_under_garbage_razdel_redirects_to_canonical_url(): void
    {
        $c = $this->chain();
        $links = DB::selectOne(
            'SELECT COUNT(*) AS n FROM razdel_subrazdel rs JOIN sub_razdel sr ON sr.id_sub_razdel = rs.id_sub_razdel WHERE sr.url_sub_razdel_name = ?',
            [$c->sub]
        );
        if ((int) $links->n !== 1) {
            $this->markTestSkipped('Подраздел привязан к нескольким разделам: канонический адрес фолбэка неоднозначен.');
        }

        $this->get("/ru/zzzz-garbage/{$c->sub}")->assertStatus(301)->assertRedirect("/ru/{$c->razdel}/{$c->sub}");
    }

    public function test_product_url_with_unknown_category_shows_subrazdel_not_the_empty_stub(): void
    {
        $c = $this->chain();

        $this->get("/ru/{$c->razdel}/{$c->sub}/zzzz-nonexistent-category/zzzz_no_such_model")
            ->assertNotFound()
            ->assertDontSee('Раздел не найден.');
    }

    public function test_bioptron_category_slug_under_garbage_prefix_goes_straight_to_the_alias(): void
    {
        if (!Category::getByUrlName('prokat-bioptron-minsk', 'ru')) {
            $this->markTestSkipped('В dev-БД нет категории prokat-bioptron-minsk.');
        }

        $this->get('/ru/zzzz-garbage/medical-prokat/prokat-bioptron-minsk')
            ->assertStatus(301)
            ->assertRedirect('/ru/medical-prokat/bioptron');
    }
}
