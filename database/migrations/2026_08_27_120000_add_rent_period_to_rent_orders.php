<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Срок аренды, который клиент указал при бронировании.
 *
 * До этого дни существовали только внутри свободного текста rent_orders.info
 * (App\Http\Controllers\L3Controller — «В брони клиент указал: с ... по ... на N дня.»,
 * App\Http\Controllers\CartController — «<span class="bk-days">N дн.</span>»),
 * то есть при оформлении договора оператор перебивал их руками.
 *
 * Позиционные `INSERT INTO rent_orders VALUES` (ловушка №1 в docs/db_notes.md) проверены
 * 27.08.2026: все 6 штук живут в неподключаемых файлах (bb/l_3_br.php, bb/bron/rent_orders.php,
 * bb/classes/old_bron.php) и уже несовместимы со схемой — 20 и 23 значения против 29 колонок.
 * Все 7 живых писателей (bb/classes/bron.php, bb/classes/Zayavka.php, includes/l_3_br.php)
 * используют явные списки колонок, поэтому добавление колонок их не задевает.
 */
class AddRentPeriodToRentOrders extends Migration
{
    /** Колонки добавляются в обе таблицы пары act/arch — иначе архивация потеряет срок. */
    private const TABLES = ['rent_orders', 'rent_orders_arch'];

    public function up(): void
    {
        foreach (self::TABLES as $t) {
            Schema::table($t, function ($table) use ($t) {
                // Guard: на проде `php artisan migrate` сломан ionCube-лоадером (docs/db_notes.md, п.7),
                // поэтому DDL там накатывается руками, а строка в `migrations` дописывается отдельно.
                // Без проверки повторный прогон миграции упал бы на «Duplicate column name».
                if (!Schema::hasColumn($t, 'rent_days')) {
                    $table->unsignedSmallInteger('rent_days')->nullable()->after('validity');
                }
                if (!Schema::hasColumn($t, 'date_from')) {
                    // Unix timestamp, а не date — сквозная конвенция времени в этой базе (docs/db_notes.md, п.3)
                    $table->integer('date_from')->nullable()->after('rent_days');
                }
                if (!Schema::hasColumn($t, 'date_to')) {
                    $table->integer('date_to')->nullable()->after('date_from');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            Schema::table($t, function ($table) use ($t) {
                $cols = array_values(array_filter(
                    ['rent_days', 'date_from', 'date_to'],
                    function ($c) use ($t) { return Schema::hasColumn($t, $c); }
                ));
                if ($cols) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
}
