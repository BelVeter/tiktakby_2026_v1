<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Проверяет, что миграция журнала тарифов покрыла baseline'ом каждый живой
 * тариф и импортировала весь rent_tarif_prev.
 *
 * Числа не зашиты: на проде и в локальном дампе они разные, поэтому
 * сверяемся с фактическим содержимым базы.
 */
class TariffHistoryMigrationTest extends TestCase
{
    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('rent_tarif_history'));

        foreach ([
            'id', 'tarif_id', 'model_id', 'change_type', 'changed_at',
            'actor_user_id', 'actor_name', 'source', 'ip',
            'old_step', 'old_kol_vo', 'old_kol_vo_min', 'old_rent_amount',
            'old_rent_per_step', 'old_start_date', 'old_sort_num',
            'new_step', 'new_kol_vo', 'new_kol_vo_min', 'new_rent_amount',
            'new_rent_per_step', 'new_start_date', 'new_sort_num', 'note',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('rent_tarif_history', $column),
                "колонка {$column} отсутствует"
            );
        }
    }

    public function test_money_columns_keep_two_decimals(): void
    {
        $row = DB::selectOne("
            SELECT COLUMN_TYPE AS t
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'rent_tarif_history'
              AND COLUMN_NAME = 'new_rent_amount'
        ");
        $this->assertSame('decimal(11,2)', strtolower($row->t));
    }

    public function test_every_live_tariff_has_a_history_event(): void
    {
        $orphans = DB::selectOne("
            SELECT COUNT(*) AS n
            FROM rent_tarif_act a
            WHERE NOT EXISTS (
                SELECT 1 FROM rent_tarif_history h WHERE h.tarif_id = a.tarif_id
            )
        ");
        $this->assertSame(0, (int) $orphans->n, 'у каждого живого тарифа должно быть событие');
    }

    public function test_every_archived_tariff_was_imported(): void
    {
        $missing = DB::selectOne("
            SELECT COUNT(*) AS n
            FROM rent_tarif_prev p
            WHERE NOT EXISTS (
                SELECT 1 FROM rent_tarif_history h
                WHERE h.tarif_id = p.tarif_act_id
                  AND h.source = 'legacy_import'
            )
        ");
        $this->assertSame(0, (int) $missing->n, 'все строки rent_tarif_prev должны быть импортированы');
    }

    public function test_baseline_events_carry_no_old_values(): void
    {
        $bad = DB::selectOne("
            SELECT COUNT(*) AS n FROM rent_tarif_history
            WHERE change_type = 'baseline' AND old_rent_amount IS NOT NULL
        ");
        $this->assertSame(0, (int) $bad->n);
    }

    public function test_legacy_delete_events_carry_no_new_values(): void
    {
        $bad = DB::selectOne("
            SELECT COUNT(*) AS n FROM rent_tarif_history
            WHERE change_type = 'delete' AND new_rent_amount IS NOT NULL
        ");
        $this->assertSame(0, (int) $bad->n);
    }

    public function test_numeric_change_who_is_parsed_into_actor_user_id(): void
    {
        // change_who в rent_tarif_act хранит вперемешку id ('777', '26') и имена ('Кристина').
        $hasNumericActors = DB::selectOne("
            SELECT COUNT(*) AS n FROM rent_tarif_act WHERE change_who REGEXP '^[0-9]+$'
        ");
        if ((int) $hasNumericActors->n === 0) {
            $this->markTestSkipped('в этой базе нет тарифов с числовым change_who');
        }

        $mismatch = DB::selectOne("
            SELECT COUNT(*) AS n
            FROM rent_tarif_act a
            JOIN rent_tarif_history h
              ON h.tarif_id = a.tarif_id AND h.change_type = 'baseline'
            WHERE a.change_who REGEXP '^[0-9]+$'
              AND (h.actor_user_id IS NULL OR h.actor_user_id <> CAST(a.change_who AS SIGNED))
        ");
        $this->assertSame(0, (int) $mismatch->n);
    }
}
