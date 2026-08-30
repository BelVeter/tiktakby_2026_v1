<?php

namespace App\MyClasses\Search;

use Illuminate\Support\Facades\DB;

/**
 * Поиск по каталогу. Заменяет bb\classes\ModelWeb::getModelIdsFullTextSearch().
 *
 * Почему BOOLEAN MODE, а не natural language:
 *  - natural language OR-ит слова, поэтому «цыганский костюм» отдавал 306 моделей
 *    — все костюмы каталога, притом что «цыганский» отдельно не находил ничего;
 *  - в natural language действует правило 50%: слово, встречающееся более чем в
 *    половине строк, молча выбрасывается. В BOOLEAN MODE такого правила нет.
 *
 * Ограничение, которое НЕ обходится wildcard-ом: ft_min_word_len = 4. Токены
 * короче четырёх символов FULLTEXT не индексирует даже как «мяч*» (проверено:
 * даёт 0). Для них отдельная ветка — Task 7 плана.
 */
class ProductSearch
{
    /**
     * Должно совпадать со списком колонок FULLTEXT-индекса, иначе MySQL ответит
     * "Can't find FULLTEXT index matching the column list".
     */
    private const MATCH_FIELDS = 'w.title, w.l2_name, w.item_name_main';

    public function find(string $query): SearchResult
    {
        $tokens = QueryNormalizer::tokenize($query);
        if ($tokens['stems'] === []) {
            return SearchResult::empty();
        }

        $exact = $this->fulltext($tokens['stems'], true);
        if ($exact !== []) {
            return new SearchResult($exact, SearchResult::TIER_EXACT);
        }

        // Одно слово уже искалось как обязательное — OR-проход дал бы то же самое.
        if (count($tokens['stems']) > 1) {
            $partial = $this->fulltext($tokens['stems'], false);
            if ($partial !== []) {
                return new SearchResult($partial, SearchResult::TIER_PARTIAL);
            }
        }

        return SearchResult::empty();
    }

    /**
     * @param string[] $stems
     * @param bool $requireAll true → «+основа*» на каждом слове (AND), false → OR
     * @return int[]
     */
    private function fulltext(array $stems, bool $requireAll): array
    {
        $prefix = $requireAll ? '+' : '';
        $expression = implode(' ', array_map(static function ($stem) use ($prefix) {
            return $prefix . $stem . '*';
        }, $stems));

        // EXISTS, а не INNER JOIN: джойн с tovar_rent_items размножал строки и
        // требовал GROUP BY, а Laravel-соединение работает в strict mode
        // (ONLY_FULL_GROUP_BY), где MATCH() в SELECT ломает такой запрос.
        // Легаси-код на mysqli strict mode не включал, поэтому там GROUP BY жил.
        $rows = DB::select(
            'SELECT w.model_id,
                    MATCH(' . self::MATCH_FIELDS . ') AGAINST(? IN BOOLEAN MODE) AS relevance
             FROM rent_model_web w
             WHERE MATCH(' . self::MATCH_FIELDS . ') AGAINST(? IN BOOLEAN MODE)
               AND w.status = ?
               AND EXISTS (SELECT 1 FROM tovar_rent_items t WHERE t.model_id = w.model_id)
             ORDER BY relevance DESC, w.model_id DESC',
            [$expression, $expression, 'show']
        );

        // Сейчас в rent_model_web одна строка на модель (844/844), но колонка
        // lang в схеме есть — если появится вторая языковая версия, дедуп спасёт.
        return array_values(array_unique(array_map(static function ($row) {
            return (int) $row->model_id;
        }, $rows)));
    }
}
