<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Статьи расходов по офисам: остался один офис, статьи закрытых точек больше
 * не нужны в форме внесения.
 *
 * Строки не удаляются, а гасятся флагом is_active — на эти коды ссылаются сотни
 * операций в doh_rash, и без строки справочника они потеряли бы название
 * в истории и в отчётах. Ровно для этого is_active и заводился: форма внесения
 * берёт только активные, фильтр и расшифровка — все.
 */
class UpdateRashItemsOfficeArticles extends Migration
{
    /** порядок «ком.платежей» — сразу за арендой офиса */
    const UTIL_ORDER_STEP = 50;

    public function up()
    {
        // закрытые точки и неиспользуемая статья
        DB::table('rash_items')
            ->whereIn('ri_code', ['of1_rent', 'of2_rent', 'debt_rep'])
            ->update(['is_active' => 0]);

        // единственный оставшийся офис называется просто «аренда офиса»
        DB::table('rash_items')
            ->where('ri_code', 'r3_rent')
            ->update(['ri_text' => 'аренда офиса']);

        if (DB::table('rash_items')->where('ri_code', 'office_util')->exists()) {
            return;
        }

        $rent = DB::table('rash_items')->where('ri_code', 'r3_rent')->first();

        DB::table('rash_items')->insert([
            'ri_order'   => ($rent ? (int) $rent->ri_order : 2200) + self::UTIL_ORDER_STEP,
            'ri_text'    => 'ком.платежи офис',
            'ri_code'    => 'office_util',
            'bank_yn'    => $rent ? (int) $rent->bank_yn : 1,  // платятся оттуда же, откуда аренда
            'is_active'  => 1,
            'resertve_1' => 0,   // NOT NULL без значения по умолчанию
            'resertve_2' => 0,
            'resertve_3' => 0,
        ]);
    }

    public function down()
    {
        DB::table('rash_items')->where('ri_code', 'office_util')->delete();

        DB::table('rash_items')
            ->where('ri_code', 'r3_rent')
            ->update(['ri_text' => 'Аренда Литературная']);

        DB::table('rash_items')
            ->whereIn('ri_code', ['of1_rent', 'of2_rent', 'debt_rep'])
            ->update(['is_active' => 1]);
    }
}
