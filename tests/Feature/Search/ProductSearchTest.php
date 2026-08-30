<?php

namespace Tests\Feature\Search;

use bb\classes\ModelWeb;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Замер поведения "до" переделки поиска.
 *
 * Эти тесты фиксируют СЛОМАННОЕ поведение намеренно: смысл — доказать, что мы
 * воспроизводим баг локально, прежде чем его чинить. В Task 3 файл будет
 * переписан под целевое поведение ProductSearch.
 *
 * Числа сняты с локального каталога (844 модели) 2026-08-30 и сходятся с
 * продом с точностью до 1-3 моделей: коляска 39/38, весы 11/10, стульчик 12/9,
 * остальные совпадают точно.
 *
 * @see docs/superpowers/plans/2026-08-30-internal-search-overhaul.md
 */
class ProductSearchTest extends TestCase
{
    use DatabaseTransactions;

    /** @dataProvider legacyBaseline */
    public function test_legacy_search_baseline(string $query, int $expected): void
    {
        $ids = ModelWeb::getModelIdsFullTextSearch($query);

        $this->assertCount(
            $expected,
            $ids,
            "запрос «{$query}» вернул " . count($ids) . " моделей вместо {$expected}"
        );
    }

    public function legacyBaseline(): array
    {
        return [
            // Морфологии нет: единственное число ищет, множественное — ноль.
            'коляска работает'   => ['коляска', 39],
            'коляски — ноль'     => ['коляски', 0],
            'кроватка работает'  => ['кроватка', 6],
            'кроватки — ноль'    => ['кроватки', 0],

            // Название категории в поиске не участвует: «Эргорюкзаки, слинги,
            // туристические рюкзаки» — 16 моделей, а находится одна.
            'эргорюкзак'         => ['эргорюкзак', 1],
            'слинг'              => ['слинг', 1],
            'кенгуру'            => ['кенгуру', 1],
            'видеоняня — ноль'   => ['видеоняня', 0],
            'шезлонг 5 из 8'     => ['шезлонг', 5],
            'кокон 2 из 10'      => ['кокон', 2],

            // keywords и main_descr не индексируются: «толокар» есть в описании
            // трёх машин-каталок Chi Lok Bo, но не находится.
            'толокар — ноль'     => ['толокар', 0],

            // ft_min_word_len = 4 — слово короче четырёх букв не индексируется.
            'мяч — ноль'         => ['мяч', 0],

            // Что уже работает и что нельзя сломать переделкой.
            'весы'               => ['весы', 11],
            'стульчик'           => ['стульчик', 12],
        ];
    }

    /**
     * Natural language mode OR-ит слова: все 306 моделей пришли от слова
     * «костюм», притом что «цыганский» сам по себе не находит ничего.
     */
    public function test_multiword_query_is_an_or_not_an_and(): void
    {
        $both = ModelWeb::getModelIdsFullTextSearch('цыганский костюм');
        $rare = ModelWeb::getModelIdsFullTextSearch('цыганский');
        $common = ModelWeb::getModelIdsFullTextSearch('костюм');

        $this->assertCount(0, $rare, 'слово «цыганский» неожиданно что-то нашло');
        $this->assertCount(306, $both);
        $this->assertSame(count($common), count($both), 'выдача по двум словам равна выдаче по одному');
    }
}
