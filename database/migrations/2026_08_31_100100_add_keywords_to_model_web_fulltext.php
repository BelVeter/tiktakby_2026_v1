<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет keywords в поисковый FULLTEXT-индекс rent_model_web и убирает два
 * старых индекса, которые он заменяет.
 *
 * Зачем: keywords заполнен у 797 моделей из 848 и в поиске не участвовал —
 * владелец годами вписывал туда слова, которые поиск не видел. Это же поле
 * становится штатной «ручкой»: вписал «толокар» — товар начал находиться.
 *
 * main_descr в индекс сознательно НЕ берём: описания длинные, они размоют
 * релевантность и потянут в выдачу случайные совпадения.
 *
 * Индекс `title` (title, item_name_main) не использовал ни один запрос —
 * MATCH шёл по `title_2`. Оба заменяются одним ft_search.
 *
 * ⚠️ Перед созданием индекса колонку приходится перевести в utf8mb4: FULLTEXT
 * требует одной кодировки у всех своих колонок, а в таблице их две —
 * title/l2_name/item_name_main лежат в utf8mb4, а keywords и main_descr в
 * utf8mb3. Без конвертации ALTER падает с «1283 Column 'keywords' cannot be
 * part of FULLTEXT index». Расхождение одинаковое и на проде, и локально.
 *
 * Расширение utf8mb3 → utf8mb4 данные не теряет. На MyISAM это перестроение
 * таблицы с блокировкой, но строк меньше тысячи — доли секунды.
 *
 * Schema::table()->fullText() появился только в Laravel 9, здесь 8.75 —
 * поэтому сырой SQL. Синтаксис одинаков для MySQL 5.7 и MariaDB 10.6
 * (на 5.7 та же кодировка называется `utf8`).
 */
class AddKeywordsToModelWebFulltext extends Migration
{
    public function up(): void
    {
        if ($this->keywordsCharset() !== 'utf8mb4') {
            DB::statement(
                'ALTER TABLE rent_model_web
                 MODIFY keywords VARCHAR(256)
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL'
            );
        }

        if (!$this->indexExists('ft_search')) {
            DB::statement(
                'ALTER TABLE rent_model_web
                 ADD FULLTEXT ft_search (title, l2_name, item_name_main, keywords)'
            );
        }

        foreach (['title', 'title_2'] as $legacyIndex) {
            if ($this->indexExists($legacyIndex)) {
                DB::statement('ALTER TABLE rent_model_web DROP INDEX `' . $legacyIndex . '`');
            }
        }
    }

    /**
     * Кодировку колонки обратно НЕ сужаем: если за время жизни индекса в
     * keywords попали четырёхбайтовые символы, откат в utf8mb3 их обрежет.
     * Восстанавливаем только индексы.
     */
    public function down(): void
    {
        if (!$this->indexExists('title_2')) {
            DB::statement(
                'ALTER TABLE rent_model_web
                 ADD FULLTEXT title_2 (title, l2_name, item_name_main)'
            );
        }
        if (!$this->indexExists('title')) {
            DB::statement(
                'ALTER TABLE rent_model_web ADD FULLTEXT `title` (title, item_name_main)'
            );
        }
        if ($this->indexExists('ft_search')) {
            DB::statement('ALTER TABLE rent_model_web DROP INDEX ft_search');
        }
    }

    private function keywordsCharset(): ?string
    {
        $row = DB::selectOne(
            "SELECT CHARACTER_SET_NAME AS cs FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'rent_model_web'
               AND COLUMN_NAME = 'keywords'"
        );

        return $row ? $row->cs : null;
    }

    private function indexExists(string $name): bool
    {
        $rows = DB::select(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'rent_model_web'
               AND INDEX_NAME = ?
             LIMIT 1",
            [$name]
        );

        return $rows !== [];
    }
}
