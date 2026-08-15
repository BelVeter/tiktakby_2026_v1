<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Журнал изменений тарифов аренды.
 *
 * До сих пор история существовала только для удалений (`rent_tarif_prev`,
 * заполнялась одной веткой bb/rent_tarifs.php), а при UPDATE старое значение
 * затиралось без следа. Одно событие здесь = полный снапшот строки тарифа до
 * и после, поэтому прайс на произвольную дату восстанавливается запросом
 * «последнее событие с changed_at <= D» без проигрывания всей ленты.
 *
 * Миграция также заполняет стартовую точку:
 *   - baseline: каждая живая строка rent_tarif_act, датированная её change_date;
 *   - legacy_import: каждая строка rent_tarif_prev как событие delete.
 */
class CreateRentTarifHistoryTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rent_tarif_history')) {
            Schema::create('rent_tarif_history', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('tarif_id');
                $table->integer('model_id');
                $table->enum('change_type', ['baseline', 'create', 'update', 'delete']);
                $table->integer('changed_at');           // unix ts, как везде в легаси
                $table->integer('actor_user_id')->nullable();
                $table->string('actor_name', 128)->nullable();
                $table->string('source', 32);            // bb_admin | model_archive | migration | legacy_import | baseline
                $table->string('ip', 45)->nullable();

                $table->string('old_step', 16)->nullable();
                $table->integer('old_kol_vo')->nullable();
                $table->integer('old_kol_vo_min')->nullable();
                $table->decimal('old_rent_amount', 11, 2)->nullable();
                $table->decimal('old_rent_per_step', 11, 2)->nullable();
                $table->integer('old_start_date')->nullable();
                $table->integer('old_sort_num')->nullable();

                $table->string('new_step', 16)->nullable();
                $table->integer('new_kol_vo')->nullable();
                $table->integer('new_kol_vo_min')->nullable();
                $table->decimal('new_rent_amount', 11, 2)->nullable();
                $table->decimal('new_rent_per_step', 11, 2)->nullable();
                $table->integer('new_start_date')->nullable();
                $table->integer('new_sort_num')->nullable();

                $table->string('note', 255)->nullable();

                $table->index(['model_id', 'changed_at'], 'idx_th_model_time');
                $table->index(['tarif_id', 'changed_at'], 'idx_th_tarif_time');
                $table->index('changed_at', 'idx_th_time');
            });
        }

        // Заливка идемпотентна, но гарды на два INSERT НАМЕРЕННО раздельные
        // и проверяются по `source`, а не общим "count() > 0" перед обоими.
        // На проде `migrate` запускается HTTP-запросом через Deploy.php —
        // таймаут реален. Если процесс оборвётся между первым и вторым
        // INSERT, Laravel не отметит миграцию выполненной; при повторном
        // запуске общий ранний return по "count() > 0" (после уже
        // вставленного baseline) молча пропустил бы импорт rent_tarif_prev
        // навсегда. Раздельные гарды позволяют докатить именно
        // недостающую часть.

        // Baseline. change_who хранит вперемешку id ('777') и имена ('Кристина'),
        // поэтому разбираем: строка целиком из цифр → actor_user_id, иначе → actor_name.
        if (DB::table('rent_tarif_history')->where('source', 'baseline')->count() === 0) {
            DB::statement("
                INSERT INTO rent_tarif_history (
                    tarif_id, model_id, change_type, changed_at,
                    actor_user_id, actor_name, source,
                    new_step, new_kol_vo, new_kol_vo_min,
                    new_rent_amount, new_rent_per_step, new_start_date, new_sort_num,
                    note
                )
                SELECT
                    a.tarif_id, a.model_id, 'baseline', a.change_date,
                    CASE WHEN a.change_who REGEXP '^[0-9]+$' THEN CAST(a.change_who AS SIGNED) END,
                    CASE WHEN a.change_who REGEXP '^[0-9]+$' THEN NULL ELSE NULLIF(a.change_who, '') END,
                    'baseline',
                    a.step, a.kol_vo, a.kol_vo_min,
                    a.rent_amount, a.rent_per_step, a.start_date, a.sort_num,
                    'Снимок состояния на момент внедрения журнала; датирован последней правкой строки'
                FROM rent_tarif_act a
            ");
        }

        // Импорт старого архива удалений. rent_tarif_prev.rent_amount объявлен
        // DECIMAL(11,1) — копейки там уже округлены, восстановить их нельзя.
        if (DB::table('rent_tarif_history')->where('source', 'legacy_import')->count() === 0) {
            DB::statement("
                INSERT INTO rent_tarif_history (
                    tarif_id, model_id, change_type, changed_at,
                    actor_user_id, actor_name, source,
                    old_step, old_kol_vo, old_kol_vo_min,
                    old_rent_amount, old_rent_per_step, old_start_date, old_sort_num,
                    note
                )
                SELECT
                    p.tarif_act_id, p.model_id, 'delete', p.change_date,
                    CASE WHEN p.change_who REGEXP '^[0-9]+$' THEN CAST(p.change_who AS SIGNED) END,
                    CASE WHEN p.change_who REGEXP '^[0-9]+$' THEN NULL ELSE NULLIF(p.change_who, '') END,
                    'legacy_import',
                    p.step, p.kol_vo, p.kol_vo_min,
                    p.rent_amount, p.rent_per_step, p.start_date, p.sort_num,
                    'Импорт rent_tarif_prev: суммы хранились с точностью до десятых, копейки утрачены'
                FROM rent_tarif_prev p
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_tarif_history');
    }
}
