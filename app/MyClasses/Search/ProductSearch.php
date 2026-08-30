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
     * Должно ПОБУКВЕННО совпадать со списком колонок индекса ft_search, иначе
     * MySQL ответит "Can't find FULLTEXT index matching the column list".
     *
     * @see database/migrations/2026_08_31_100100_add_keywords_to_model_web_fulltext.php
     */
    private const MATCH_FIELDS = 'w.title, w.l2_name, w.item_name_main, w.keywords';

    public function find(string $query): SearchResult
    {
        $tokens = QueryNormalizer::tokenize($query);
        if ($tokens['stems'] === []) {
            return SearchResult::empty();
        }

        $exact = $this->fulltext($tokens['stems'], true);
        $byCategory = $this->byCategoryName($tokens['stems']);

        // Прямое совпадение по товару всегда выше совпадения по категории:
        // на «слинг» сначала «Слинг-рюкзак Babybjorn», потом остальные переноски.
        if ($exact !== []) {
            return new SearchResult(
                $this->mergePreservingOrder($exact, $byCategory),
                SearchResult::TIER_EXACT
            );
        }

        if ($byCategory !== []) {
            return new SearchResult($byCategory, SearchResult::TIER_CATEGORY);
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
     * Модели категорий, чьё название содержит ВСЕ основы запроса.
     *
     * Зачем: владелец ведёт таксономию («Эргорюкзаки, слинги, туристические
     * рюкзаки» — 16 моделей), а поиск её не видел, потому что MATCH идёт только
     * по колонкам самой модели. Внутри этой категории нет ни одного товара со
     * словом «эргорюкзак» в названии — там «Эргономичный рюкзак» и «Нагрудная
     * сумка». Отсюда 1 результат вместо 19.
     *
     * Почему LIKE, а не ещё один FULLTEXT: категорий всего ~120, полный скан
     * стоит копейки, а в их названиях полно слов короче ft_min_word_len
     * («дуги», «мойка»), которые FULLTEXT просто не увидел бы.
     *
     * Почему ВСЕ основы, а не любая: иначе «коляска babyzen» перестала бы
     * сужаться — одна основа «коляск» подтянула бы всю категорию колясок.
     * Требование всех слов держит ту же семантику, что и основной проход.
     *
     * @param string[] $stems
     * @return int[]
     */
    private function byCategoryName(array $stems): array
    {
        $conditions = [];
        $bindings = [];
        foreach ($stems as $stem) {
            $conditions[] = 'c.rent_cat_name LIKE ?';
            $bindings[] = '%' . $this->escapeLike($stem) . '%';
        }

        $rows = DB::select(
            'SELECT DISTINCT w.model_id
             FROM tovar_rent_cat c
             INNER JOIN tovar_rent tr ON tr.tovar_rent_cat_id = c.tovar_rent_cat_id
             INNER JOIN rent_model_web w ON w.model_id = tr.tovar_rent_id
             WHERE (' . implode(' AND ', $conditions) . ')
               AND w.status = ?
               AND EXISTS (SELECT 1 FROM tovar_rent_items t WHERE t.model_id = w.model_id)
             ORDER BY w.model_id DESC',
            array_merge($bindings, ['show'])
        );

        return array_map(static function ($row) {
            return (int) $row->model_id;
        }, $rows);
    }

    /**
     * Склейка с сохранением порядка первого списка и без дублей.
     *
     * @param int[] $primary
     * @param int[] $secondary
     * @return int[]
     */
    private function mergePreservingOrder(array $primary, array $secondary): array
    {
        $seen = array_flip($primary);
        $merged = $primary;
        foreach ($secondary as $id) {
            if (!isset($seen[$id])) {
                $merged[] = $id;
                $seen[$id] = true;
            }
        }

        return $merged;
    }

    /** Экранирует спецсимволы LIKE, чтобы «100%» в запросе не стал wildcard-ом. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
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
