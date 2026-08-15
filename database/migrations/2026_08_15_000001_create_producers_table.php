<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Справочник производителей: одно каноничное написание бренда, один
 * логотип на бренд. `tovar_rent.producer` (varchar(365)) остаётся строкой —
 * её читают 52 файла, переход на producer_id с внешним ключом несоразмерен
 * риску (docs/superpowers/specs/2026-08-14-producers-directory-design.md).
 *
 * `name` — 365 символов вслед за длиной исходной колонки, чтобы ни одно
 * существующее значение не обрезалось при засеве. Уникальный индекс на
 * utf8mb4(365) укладывается в лимит префикса InnoDB только при
 * innodb_large_prefix=ON (MariaDB 10.6 — по умолчанию включено, ROW_FORMAT
 * DYNAMIC у новых таблиц тоже по умолчанию).
 */
class CreateProducersTable extends Migration
{
    public function up()
    {
        Schema::create('producers', function (Blueprint $table) {
            $table->increments('producer_id');
            $table->string('name', 365);
            $table->unique('name');
            $table->string('name_norm', 365);
            $table->index('name_norm');
            $table->string('logo')->default('');
            $table->string('comment', 255)->default('');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('cr_time')->nullable();
            $table->unsignedInteger('cr_user_id')->nullable();
            $table->unsignedInteger('ch_time')->nullable();
            $table->unsignedInteger('ch_user_id')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('producers');
    }
}
