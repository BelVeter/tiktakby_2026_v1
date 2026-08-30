<?php

namespace Tests\Feature\Search;

use App\MyClasses\Search\ProductSearch;
use App\MyClasses\Search\SearchResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Целевое поведение поиска. Числа сняты с локального каталога (844 модели)
 * и сходятся с продом с точностью до 1-3 моделей.
 *
 * @see docs/superpowers/plans/2026-08-30-internal-search-overhaul.md
 */
class ProductSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function search(string $query): SearchResult
    {
        return (new ProductSearch())->find($query);
    }

    private function total(string $query): int
    {
        return $this->search($query)->getTotal();
    }

    /**
     * Главный регресс-щит. До переделки «коляски» и «кроватки» давали ноль,
     * потому что natural language mode ищет слово буквально, без морфологии.
     */
    public function test_plural_finds_the_same_as_singular(): void
    {
        $this->assertSame($this->total('коляска'), $this->total('коляски'));
        $this->assertSame($this->total('кроватка'), $this->total('кроватки'));
    }

    public function test_stemming_widens_the_result(): void
    {
        // Было 39 и 6 соответственно — wildcard по основе добирает словоформы.
        $this->assertGreaterThanOrEqual(41, $this->total('коляска'));
        $this->assertGreaterThanOrEqual(10, $this->total('кроватка'));
    }

    /**
     * Не сломать то, что уже работало: «весы» — 11, «стульчик» — 12.
     * Стемминг не должен обрезать их в основу короче ft_min_word_len.
     */
    public function test_previously_working_queries_do_not_regress(): void
    {
        $this->assertGreaterThanOrEqual(11, $this->total('весы'));
        $this->assertGreaterThanOrEqual(12, $this->total('стульчик'));
    }

    /**
     * Смена семантики с OR на AND. Раньше «цыганский костюм» отдавал 306 моделей
     * — все костюмы каталога, притом что «цыганский» не находил ничего. Теперь
     * оба слова обязательны, точных совпадений нет, и мы это честно помечаем.
     *
     * Количество при этом не падает: OR-фолбэк отдаёт 330 (wildcard «костюм*»
     * ловит ещё и «костюмы»). Ценность не в цифре, а в том, что выдача больше
     * не выдаётся за точный ответ — см. текст на странице в Task 8.
     */
    public function test_multiword_query_without_exact_match_is_labeled_partial(): void
    {
        $result = $this->search('цыганский костюм');

        $this->assertSame(SearchResult::TIER_PARTIAL, $result->getTier());
        $this->assertGreaterThan(0, $result->getTotal());
    }

    public function test_multiword_query_narrows_when_both_words_exist(): void
    {
        $broad = $this->total('коляска');
        $narrow = $this->search('коляска babyzen');

        $this->assertSame(SearchResult::TIER_EXACT, $narrow->getTier());
        $this->assertSame(3, $narrow->getTotal());
        $this->assertLessThan($broad, $narrow->getTotal());
    }

    /**
     * Категория «Эргорюкзаки, слинги, туристические рюкзаки» — 16 моделей,
     * и ни одна не названа «эргорюкзак» или «слинг»: внутри «Эргономичный
     * рюкзак» ×7, «Нагрудная сумка» ×4, «Хипсит» ×2. До этой задачи оба
     * запроса давали ровно 1 результат.
     *
     * Такая же история с «Радио- видеоняни» (4 модели, все названы
     * «Радионяня»), «Шезлонги детские» и «Кроватки, колыбели, коконы».
     *
     * @dataProvider categoryOnlyQueries
     */
    public function test_query_matching_category_name_finds_its_models(string $query, int $atLeast): void
    {
        $result = $this->search($query);

        $this->assertGreaterThanOrEqual(
            $atLeast,
            $result->getTotal(),
            "запрос «{$query}» вернул {$result->getTotal()} моделей"
        );
    }

    public function categoryOnlyQueries(): array
    {
        return [
            'эргорюкзак → вся категория'         => ['эргорюкзак', 15],
            'слинг → вся категория'              => ['слинг', 15],
            'видеоняня → Радио- видеоняни'       => ['видеоняня', 4],
            'шезлонг → вся категория'            => ['шезлонг', 8],
            'кокон → Кроватки, колыбели, коконы' => ['кокон', 9],
        ];
    }

    /**
     * Совпадение по товару всегда важнее совпадения по категории: тот, кто
     * ввёл «слинг», первым должен увидеть «Слинг-рюкзак Babybjorn», а не
     * произвольную модель из той же категории.
     */
    public function test_exact_model_matches_rank_above_category_matches(): void
    {
        $ids = $this->search('слинг')->getModelIds();

        $direct = DB::table('rent_model_web')
            ->where('status', 'show')
            ->where('l2_name', 'like', '%слинг%')
            ->pluck('model_id')
            ->map(function ($id) { return (int) $id; })
            ->all();

        $this->assertNotEmpty($direct);
        $this->assertContains($ids[0], $direct, 'первым идёт не прямое совпадение по товару');
    }

    /**
     * Проверяем сам механизм, а не ярлык тира: «Нагрудная сумка Babybjorn»
     * лежит в категории «Эргорюкзаки, слинги, туристические рюкзаки», но слова
     * «эргорюкзак» в её собственных полях нет — попасть в выдачу она может
     * только через название категории.
     *
     * Тир при этом будет 'exact', а не 'category': в категории есть и прямые
     * совпадения, они идут первыми. Отдельный тир 'category' — страховка для
     * случая, когда по товарам не нашлось вообще ничего.
     */
    public function test_category_pass_pulls_in_models_that_do_not_match_by_name(): void
    {
        $ids = $this->search('эргорюкзак')->getModelIds();

        $chestCarrier = DB::table('rent_model_web')
            ->where('status', 'show')
            ->where('l2_name', 'like', 'Нагрудная сумка%')
            ->value('model_id');

        $this->assertNotNull($chestCarrier, 'в каталоге нет «Нагрудной сумки» — обновить фикстуру');
        $this->assertContains((int) $chestCarrier, $ids);
        $this->assertStringNotContainsStringIgnoringCase(
            'эргорюкзак',
            (string) DB::table('rent_model_web')->where('model_id', $chestCarrier)->value('l2_name'),
            'модель стала находиться по названию — тест больше не проверяет категорийный проход'
        );
    }

    /**
     * Категорийный проход не должен ломать сужение: «коляска babyzen» обязана
     * остаться тремя моделями, а не подтянуть всю категорию колясок. Поэтому
     * от названия категории требуются ВСЕ основы запроса, как и от товара.
     */
    public function test_category_pass_does_not_break_narrowing(): void
    {
        $this->assertSame(3, $this->search('коляска babyzen')->getTotal());
    }

    public function test_empty_query_returns_nothing_without_crashing(): void
    {
        $result = $this->search('   ');

        $this->assertSame(SearchResult::TIER_NONE, $result->getTier());
        $this->assertSame(0, $result->getTotal());
    }

    public function test_sql_injection_attempt_does_not_crash(): void
    {
        $this->assertGreaterThan(0, $this->total("коляска' OR '1'='1"));
    }

    public function test_boolean_operators_in_input_do_not_crash(): void
    {
        $this->assertGreaterThan(0, $this->total('+коляска* -автокресло ~((('));
    }

    public function test_only_models_with_physical_items_are_returned(): void
    {
        $ids = $this->search('коляска')->getModelIds();

        $this->assertNotEmpty($ids);
        $withoutItems = DB::table('rent_model_web as w')
            ->leftJoin('tovar_rent_items as t', 't.model_id', '=', 'w.model_id')
            ->whereIn('w.model_id', $ids)
            ->whereNull('t.item_id')
            ->count();

        $this->assertSame(0, $withoutItems);
    }

    /**
     * keywords — единственное поле, которым владелец может вручную «дотянуть»
     * товар до запроса, не переименовывая сам товар. Заполнено у 797 моделей
     * из 848 и до этой задачи в поиске не участвовало.
     */
    public function test_keywords_field_is_searchable(): void
    {
        $row = DB::table('rent_model_web as w')
            ->where('w.status', 'show')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('tovar_rent_items as t')
                  ->whereColumn('t.model_id', 'w.model_id');
            })
            ->select('w.web_id', 'w.model_id', 'w.keywords')
            ->first();

        $this->assertNotNull($row);

        // rent_model_web — MyISAM, транзакций нет: DatabaseTransactions этот
        // update не откатит, поэтому возвращаем значение руками.
        try {
            DB::table('rent_model_web')->where('web_id', $row->web_id)
                ->update(['keywords' => 'зюзюблик']);

            $this->assertContains((int) $row->model_id, $this->search('зюзюблик')->getModelIds());
        } finally {
            DB::table('rent_model_web')->where('web_id', $row->web_id)
                ->update(['keywords' => $row->keywords]);
        }
    }

    /**
     * Отдельный тест на существование индекса: в BOOLEAN MODE MySQL умеет
     * искать и БЕЗ FULLTEXT-индекса, полным сканом. Без этой проверки
     * пропавший индекс не уронил бы ни один функциональный тест — поиск
     * продолжил бы работать, просто медленно и молча.
     */
    public function test_fulltext_index_covers_all_searched_columns(): void
    {
        $columns = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', 'rent_model_web')
            ->where('INDEX_NAME', 'ft_search')
            ->orderBy('SEQ_IN_INDEX')
            ->pluck('COLUMN_NAME')
            ->all();

        $this->assertSame(['title', 'l2_name', 'item_name_main', 'keywords'], $columns);
    }

    public function test_result_ids_are_unique(): void
    {
        $ids = $this->search('коляска')->getModelIds();

        $this->assertSame(array_values(array_unique($ids)), $ids);
    }
}
