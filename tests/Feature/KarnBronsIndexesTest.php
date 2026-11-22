<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Индексы karn_brons для проверки занятости юнитов (миграция 2026_11_22_000001).
 *
 * Сторожит расхождение схемы: индексы idx_mcp_* из миграции 2026_05_09 на проде отмечены как
 * созданные, но в базе их нет (потерялись при переезде хостинга). Без idx_kb_* форма брони и
 * карнавальная таблица персонала снова начнут читать karn_brons целиком на каждый юнит.
 */
class KarnBronsIndexesTest extends TestCase
{
    /** @return string[] колонки индекса по порядку */
    private function indexColumns(string $index): array
    {
        $rows = DB::select(
            'SELECT column_name AS col FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? ORDER BY seq_in_index',
            ['karn_brons', $index]
        );

        return array_map(static function ($r) {
            return $r->col;
        }, $rows);
    }

    public function test_unit_lookup_index_exists(): void
    {
        $this->assertSame(['inv_n', 't_to'], $this->indexColumns('idx_kb_inv_n_t_to'));
    }

    public function test_date_window_index_exists(): void
    {
        $this->assertSame(['t_to'], $this->indexColumns('idx_kb_t_to'));
    }
}
