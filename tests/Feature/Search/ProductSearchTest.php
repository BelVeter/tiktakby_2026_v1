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

    public function test_result_ids_are_unique(): void
    {
        $ids = $this->search('коляска')->getModelIds();

        $this->assertSame(array_values(array_unique($ids)), $ids);
    }
}
