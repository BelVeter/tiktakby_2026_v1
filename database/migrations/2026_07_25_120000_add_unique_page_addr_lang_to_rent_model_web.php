<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One page per URL, per language.
 *
 * `page_addr` carried no uniqueness constraint, so nothing stopped two rows
 * from answering to the same URL — six such collisions had accumulated by
 * 2026-07. Which of the colliding rows the site rendered was left to MySQL,
 * and the webp conversion only ever updated one of them, leaving the other
 * pointing at image files it had already deleted.
 *
 * The key is (page_addr, lang), not page_addr alone: one product keeps the
 * same URL tail across ru/en/lt, so translations must stay free to share a
 * slug while a second row in the *same* language is rejected.
 *
 * A 191-character prefix because page_addr is TEXT and rent_model_web is
 * MyISAM, whose index key is capped at 1000 bytes; the longest slug in the
 * catalog is 61 characters.
 *
 * Applied to production by hand on 2026-07-25 once the existing duplicates
 * were merged away, hence the existence check — this migration has to be a
 * no-op there and still build the index on every other copy of the database.
 */
class AddUniquePageAddrLangToRentModelWeb extends Migration
{
    private const INDEX = 'uniq_page_addr_lang';

    public function up(): void
    {
        if ($this->indexExists()) {
            return;
        }

        $duplicates = DB::select('
            SELECT page_addr, lang, COUNT(*) AS rows_count
            FROM rent_model_web
            GROUP BY page_addr, lang
            HAVING rows_count > 1
        ');

        if (!empty($duplicates)) {
            // Failing here beats failing inside ALTER TABLE: the message names
            // the rows that have to be merged before the index can exist.
            $listed = array_map(
                fn ($row) => "{$row->page_addr} ({$row->lang}) ×{$row->rows_count}",
                array_slice($duplicates, 0, 10)
            );

            throw new RuntimeException(
                'rent_model_web still holds duplicate (page_addr, lang) pairs; merge them first: '
                . implode(', ', $listed)
                . (count($duplicates) > 10 ? ' …' : '')
            );
        }

        DB::statement(
            'ALTER TABLE rent_model_web ADD UNIQUE INDEX ' . self::INDEX . ' (page_addr(191), lang)'
        );
    }

    public function down(): void
    {
        if ($this->indexExists()) {
            DB::statement('ALTER TABLE rent_model_web DROP INDEX ' . self::INDEX);
        }
    }

    private function indexExists(): bool
    {
        return !empty(DB::select(
            "SHOW INDEX FROM rent_model_web WHERE Key_name = ?",
            [self::INDEX]
        ));
    }
}
