<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Достраивает `tovar_rent_arch` до рабочей архивной таблицы моделей.
 *
 * Таблица существует давно и уже участвует в фолбэке отчётов —
 * `UNION(tovar_rent, tovar_rent_arch)` при резолве модели в категорию
 * (`bb/classes/Deal.php:1003,1027,1061,1085`, `bb/classes/tovar.php:1546,1562`).
 * То есть отчёты готовы к тому, что модель уехала в архив. Но писать туда
 * было нечем: схема отстала от живой таблицы и не было полей «кто и когда».
 *
 * Добавляем:
 *   price_new     — колонка появилась в `tovar_rent` позже, в архиве её нет;
 *   arch_time     — как в `tovar_rent_cat_arch`;
 *   arch_who_id   — как в `tovar_rent_cat_arch`;
 *   arch_snapshot — JSON со спутниками модели (тарифы, доп. фото, веб-страница,
 *                   мультистраницы, избранное). Эти строки при архивации
 *                   удаляются из живых таблиц: тарифы и фото несуществующей
 *                   модели показать нельзя, а `rent_model_web` обязана исчезнуть,
 *                   иначе L3-страница продолжит отдавать 200 (`ModelWeb::getByUrlName()`
 *                   не фильтрует `status`). Снимок нужен, чтобы SEO-тексты и
 *                   прайс не пропадали безвозвратно.
 *
 * Позиционных `INSERT ... VALUES` в `tovar_rent_arch` нет (проверено grep'ом
 * по bb/ и app/), поэтому добавление колонок безопасно — см. docs/db_notes.md, п.1.
 */
class ExtendTovarRentArchForArchiving extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tovar_rent_arch', 'price_new')) {
            DB::statement("ALTER TABLE `tovar_rent_arch` ADD COLUMN `price_new` INT(11) NOT NULL DEFAULT 0");
        }

        if (!Schema::hasColumn('tovar_rent_arch', 'arch_time')) {
            DB::statement("ALTER TABLE `tovar_rent_arch` ADD COLUMN `arch_time` INT(11) NOT NULL DEFAULT 0");
            DB::statement("ALTER TABLE `tovar_rent_arch` ADD INDEX `arch_time` (`arch_time`)");
        }

        if (!Schema::hasColumn('tovar_rent_arch', 'arch_who_id')) {
            DB::statement("ALTER TABLE `tovar_rent_arch` ADD COLUMN `arch_who_id` INT(11) NOT NULL DEFAULT 0");
        }

        if (!Schema::hasColumn('tovar_rent_arch', 'arch_snapshot')) {
            DB::statement("ALTER TABLE `tovar_rent_arch` ADD COLUMN `arch_snapshot` LONGTEXT NULL");
        }
    }

    public function down(): void
    {
        foreach (['arch_snapshot', 'arch_who_id', 'arch_time', 'price_new'] as $column) {
            if (Schema::hasColumn('tovar_rent_arch', $column)) {
                DB::statement("ALTER TABLE `tovar_rent_arch` DROP COLUMN `{$column}`");
            }
        }
    }
}
