<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Индексы на karn_brons для проверки «свободен ли юнит в окне дат».
 *
 * До этого у таблицы были только PRIMARY, br_num и idx_cl_id, и каждый запрос вида
 * `WHERE inv_n = ? AND (условия по t_from/t_to)` читал её целиком (~11,8 тыс. строк). Такие запросы
 * идут циклом по юнитам: форма брони на сайте (KBron::getBrons), карнавальная таблица персонала
 * (bb/kb_lines.php), bb/karn_free.php, bb/karn_srch.php, bb/zakaz2.php.
 *
 * - idx_kb_inv_n_t_to — поиск броней конкретного юнита;
 * - idx_kb_t_to       — все брони, которые заканчиваются не раньше начала окна (фильтр листинга
 *                       карнавала по дате праздника: одним запросом на все юниты).
 *
 * На проде (MySQL 5.7, MyISAM) индексы добавлены вручную 22.11.2026 по согласованию с владельцем,
 * поэтому миграция идемпотентна: создаёт только недостающие, одним ALTER (MyISAM перестраивает
 * таблицу один раз).
 */
class AddKarnBronsAvailabilityIndexes extends Migration
{
    private const TABLE = 'karn_brons';

    /** @var array<string, string> имя => колонки */
    private const INDEXES = [
        'idx_kb_inv_n_t_to' => '(`inv_n`, `t_to`)',
        'idx_kb_t_to' => '(`t_to`)',
    ];

    public function up(): void
    {
        $add = [];
        foreach (self::INDEXES as $name => $columns) {
            if (!$this->indexExists($name)) {
                $add[] = "ADD INDEX `$name` $columns";
            }
        }
        if ($add) {
            DB::statement('ALTER TABLE `' . self::TABLE . '` ' . implode(', ', $add));
        }
    }

    public function down(): void
    {
        $drop = [];
        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->indexExists($name)) {
                $drop[] = "DROP INDEX `$name`";
            }
        }
        if ($drop) {
            DB::statement('ALTER TABLE `' . self::TABLE . '` ' . implode(', ', $drop));
        }
    }

    private function indexExists(string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [self::TABLE, $index]
        );
        return !empty($rows);
    }
}
