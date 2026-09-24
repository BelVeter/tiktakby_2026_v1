<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateSitemap;
use bb\classes\Category;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Страницы скрытой служебной категории («К УДАЛЕНИЮ», Category::HIDDEN_CATEGORY_REDIRECTS)
 * не открываются (301) и не попадают в sitemap.
 *
 * Карточка модели открывается по слагу при ЛЮБОМ префиксе адреса, поэтому редирект
 * проверяем и на каноническом пути, и на чужом.
 *
 * Тесты берут реальные данные dev-БД и пропускаются, если подходящих строк нет.
 */
class HiddenCategoryPagesTest extends TestCase
{
    private const CANONICAL_CHAIN = "
        JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = tr.tovar_rent_cat_id
        JOIN sub_razdel sr ON sr.id_sub_razdel = c.main_sub_razdel_id
        JOIN razdel r ON r.id_razdel = sr.main_razdel_id";

    private function hiddenTarget(): string
    {
        $target = Category::hiddenCatRedirectTarget(Category::hiddenCatIds()[0]);
        $this->assertNotNull($target);

        return $target;
    }

    private function liveModel(string $catCondition): ?object
    {
        return DB::selectOne(
            "SELECT w.page_addr AS slug,
                    CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name, '/', c.cat_url_key, '/', w.page_addr) AS url
             FROM rent_model_web w
             JOIN tovar_rent tr ON tr.tovar_rent_id = w.model_id
             " . self::CANONICAL_CHAIN . "
             WHERE w.lang = 'ru' AND w.status = 'show' AND w.page_addr <> ''
               AND c.tovar_rent_cat_id $catCondition
               AND r.url_razdel_name <> '' AND sr.url_sub_razdel_name <> '' AND c.cat_url_key <> ''
               AND EXISTS (SELECT 1 FROM tovar_rent_items t WHERE t.model_id = w.model_id)
               AND (SELECT COUNT(*) FROM rent_model_web x WHERE x.page_addr = w.page_addr) = 1
             ORDER BY w.model_id
             LIMIT 1"
        );
    }

    public function test_model_page_of_hidden_category_redirects_on_canonical_and_foreign_prefix(): void
    {
        $model = $this->liveModel('IN (' . Category::hiddenCatIdsSql() . ')');
        if (!$model) {
            $this->markTestSkipped('В dev-БД нет опубликованной модели скрытой категории с единицами.');
        }

        $this->get($model->url)->assertStatus(301)->assertRedirect($this->hiddenTarget());

        $this->get('/ru/prokat-detskih-tovarov/no-such-sub/no-such-cat/' . $model->slug)
            ->assertStatus(301)
            ->assertRedirect($this->hiddenTarget());
    }

    public function test_hidden_category_page_redirects(): void
    {
        $cat = DB::selectOne(
            "SELECT CONCAT('/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name, '/', c.cat_url_key) AS url
             FROM tovar_rent_cat c
             JOIN sub_razdel sr ON sr.id_sub_razdel = c.main_sub_razdel_id
             JOIN razdel r ON r.id_razdel = sr.main_razdel_id
             WHERE c.tovar_rent_cat_id IN (" . Category::hiddenCatIdsSql() . ")
               AND r.url_razdel_name <> '' AND sr.url_sub_razdel_name <> '' AND c.cat_url_key <> ''
             LIMIT 1"
        );
        if (!$cat) {
            $this->markTestSkipped('В dev-БД скрытая категория не привязана к подразделу/разделу.');
        }

        $this->get($cat->url)->assertStatus(301)->assertRedirect($this->hiddenTarget());
    }

    public function test_regular_model_page_is_not_redirected(): void
    {
        $model = $this->liveModel('NOT IN (' . Category::hiddenCatIdsSql() . ')');
        if (!$model) {
            $this->markTestSkipped('В dev-БД нет опубликованной обычной модели с единицами.');
        }

        $this->get($model->url)->assertStatus(200);
    }

    public function test_sitemap_generator_excludes_hidden_category(): void
    {
        $prefix = DB::selectOne(
            "SELECT CONCAT('https://tiktak.by/ru/', r.url_razdel_name, '/', sr.url_sub_razdel_name, '/', c.cat_url_key) AS p
             FROM tovar_rent_cat c
             JOIN sub_razdel sr ON sr.id_sub_razdel = c.main_sub_razdel_id
             JOIN razdel r ON r.id_razdel = sr.main_razdel_id
             WHERE c.tovar_rent_cat_id IN (" . Category::hiddenCatIdsSql() . ")
               AND r.url_razdel_name <> '' AND sr.url_sub_razdel_name <> '' AND c.cat_url_key <> ''
             LIMIT 1"
        );
        if (!$prefix) {
            $this->markTestSkipped('В dev-БД скрытая категория не привязана к подразделу/разделу.');
        }

        $urls = app(GenerateSitemap::class)->collectUrls();
        $this->assertNotEmpty($urls);

        foreach ($urls as $u) {
            $this->assertStringStartsNotWith($prefix->p . '/', $u['loc'] . '/', 'В sitemap попал адрес скрытой категории: ' . $u['loc']);
        }
    }
}
