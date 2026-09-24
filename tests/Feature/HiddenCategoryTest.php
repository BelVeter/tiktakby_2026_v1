<?php

namespace Tests\Feature;

use App\MyClasses\Search\ProductSearch;
use bb\classes\Category;
use bb\classes\Model;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Служебная категория «К УДАЛЕНИЮ» (Category::HIDDEN_CAT_IDS) не должна давать выхода
 * на свои товары через рекомендации, внутренний поиск и фильтры сайта.
 *
 * Тесты берут реальные данные dev-БД и пропускаются, если подходящих строк нет.
 */
class HiddenCategoryTest extends TestCase
{
    /** @return int[] id всех моделей скрытых категорий */
    private function hiddenModelIds(): array
    {
        $rows = DB::select('SELECT tovar_rent_id FROM tovar_rent WHERE tovar_rent_cat_id IN (' . Category::hiddenCatIdsSql() . ')');

        return array_map(static function ($r) {
            return (int) $r->tovar_rent_id;
        }, $rows);
    }

    /** Опубликованная модель скрытой категории, у которой есть единицы. */
    private function liveHiddenModel(string $extraWhere = ''): ?object
    {
        return DB::selectOne(
            "SELECT w.model_id, w.l2_name, w.item_name_main, tr.producer, tr.age_from, tr.age_to
             FROM rent_model_web w
             JOIN tovar_rent tr ON tr.tovar_rent_id = w.model_id
             WHERE tr.tovar_rent_cat_id IN (" . Category::hiddenCatIdsSql() . ")
               AND w.status = 'show'
               AND EXISTS (SELECT 1 FROM tovar_rent_items t WHERE t.model_id = w.model_id)
               $extraWhere
             ORDER BY w.model_id
             LIMIT 1"
        );
    }

    public function test_internal_search_does_not_return_hidden_category_models(): void
    {
        $hidden = $this->liveHiddenModel("AND w.l2_name <> ''");
        if (!$hidden) {
            $this->markTestSkipped('В dev-БД нет опубликованной модели скрытой категории с единицами.');
        }

        $found = (new ProductSearch())->find($hidden->l2_name)->getModelIds();

        $this->assertSame([], array_values(array_intersect($found, $this->hiddenModelIds())));
    }

    public function test_recommendations_do_not_include_hidden_category_models(): void
    {
        $hiddenIds = $this->hiddenModelIds();
        if (!$this->liveHiddenModel()) {
            $this->markTestSkipped('В dev-БД нет опубликованной модели скрытой категории с единицами.');
        }

        // Запрос выбирает модели того же раздела в случайном порядке — прогоняем несколько раз.
        // Подходит любая модель раздела «medical-prokat» из нескрытой категории.
        $base = DB::selectOne(
            "SELECT tr.tovar_rent_id AS id
             FROM tovar_rent tr
             JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = tr.tovar_rent_cat_id
             JOIN sub_razdel sr ON sr.id_sub_razdel = c.main_sub_razdel_id
             JOIN razdel r ON r.id_razdel = sr.main_razdel_id
             WHERE r.url_razdel_name = 'medical-prokat'
               AND tr.tovar_rent_cat_id NOT IN (" . Category::hiddenCatIdsSql() . ")
               AND EXISTS (SELECT 1 FROM tovar_rent_items t WHERE t.model_id = tr.tovar_rent_id)
             ORDER BY tr.tovar_rent_id
             LIMIT 1"
        );
        if (!$base) {
            $this->markTestSkipped('В dev-БД нет модели раздела medical-prokat вне скрытых категорий.');
        }

        $model = Model::getById($base->id);
        for ($i = 0; $i < 15; $i++) {
            $recs = Model::getModelIdsArrayForFavoriteTovSlider($model, 'medical-prokat', 'electroterapiya-prokat', 0);
            $this->assertSame([], array_values(array_intersect(array_map('intval', $recs), $hiddenIds)));
        }
    }

    public function test_producer_filter_does_not_return_hidden_category_models(): void
    {
        $hidden = $this->liveHiddenModel("AND tr.producer <> ''");
        if (!$hidden) {
            $this->markTestSkipped('В dev-БД нет опубликованной модели скрытой категории с производителем.');
        }

        $found = Model::getModelIdsArrayByProducer($hidden->producer);

        $this->assertSame([], array_values(array_intersect(array_map('intval', $found ?: []), $this->hiddenModelIds())));
    }

    public function test_age_filter_does_not_return_hidden_category_models(): void
    {
        $hidden = $this->liveHiddenModel('AND tr.age_to > tr.age_from');
        if (!$hidden) {
            $this->markTestSkipped('В dev-БД нет опубликованной модели скрытой категории с возрастным диапазоном.');
        }

        $found = Model::getModelIdsArrayByAge((int) $hidden->age_from, (int) $hidden->age_from);

        $this->assertSame([], array_values(array_intersect(array_map('intval', $found), $this->hiddenModelIds())));
    }
}
