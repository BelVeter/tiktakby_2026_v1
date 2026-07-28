<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Сливает модель-надгробие 1063 в живую 1062 (BAMBOLA «Bambino Одуванчик
 * (т.синий/бирюза)», категория 89). Обе полностью совпадают по четвёрке
 * категория + производитель + название + цвет.
 *
 * У 1063 нет ни активных, ни архивных юнитов и нет веб-страницы, но за ней
 * висят 69 архивных заявок и 1 звонок. Эти заявки относятся к юниту 719213,
 * который сейчас числится за 1062 — то есть товар когда-то переехал, а ссылки
 * остались на старой модели. Поэтому переносим ссылки, а не удаляем данные.
 *
 * ТАРИФЫ: у моделей они РАЗНЫЕ — у 1063 цены ниже на 30-40% и на год старше
 * (день 9/10/12 и неделя 15/18/21/24 против 10/12/15 и 18/27/30/35 у 1062).
 * Оставляем тарифы 1062: это модель, которая реально отрисовывается, её цены
 * видит и платит клиент. Тарифы 1063 не показывались никогда — без страницы и
 * без юнита их нечему рендерить. Слияние не должно менять цены на сайте.
 * История цен не теряется: в rent_tarif_prev у 1063 ноль строк.
 */
class MergeModel1063Into1062 extends Migration
{
    private const DUP  = 1063;
    private const KEEP = 1062;

    public function up(): void
    {
        $dupExists  = DB::table('tovar_rent')->where('tovar_rent_id', self::DUP)->exists();
        $keepExists = DB::table('tovar_rent')->where('tovar_rent_id', self::KEEP)->exists();

        if (!$dupExists || !$keepExists) {
            logger()->info('MergeModel1063Into1062: пропущено', [
                'dup_exists' => $dupExists, 'keep_exists' => $keepExists,
            ]);
            return;
        }

        // Защита: если у дубля появились юниты, значит ситуация изменилась
        // и автоматическое слияние небезопасно — нужен ручной разбор.
        $dupItems = DB::table('tovar_rent_items')->where('model_id', self::DUP)->count()
                  + DB::table('tovar_rent_items_arch')->where('model_id', self::DUP)->count();

        if ($dupItems > 0) {
            throw new RuntimeException(
                'У модели ' . self::DUP . ' появились юниты (' . $dupItems . ') — слияние отменено, нужен ручной разбор.'
            );
        }

        DB::transaction(function () {
            $moved = [
                'rent_orders'      => DB::table('rent_orders')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
                'rent_orders_arch' => DB::table('rent_orders_arch')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
                'zvonki'           => DB::table('zvonki')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
            ];

            $deleted = [
                'rent_tarif_act'  => DB::table('rent_tarif_act')->where('model_id', self::DUP)->delete(),
                'rent_tarif_prev' => DB::table('rent_tarif_prev')->where('model_id', self::DUP)->delete(),
                'dop_photos'      => DB::table('dop_photos')->where('model_id', self::DUP)->delete(),
                'multi_web'       => DB::table('multi_web')->where('model_id', self::DUP)->delete(),
                'favorite_tovars' => DB::table('favorite_tovars')->where('model_id', self::DUP)->delete(),
                'tovar_rent'      => DB::table('tovar_rent')->where('tovar_rent_id', self::DUP)->delete(),
            ];

            logger()->info('MergeModel1063Into1062: слито', ['moved' => $moved, 'deleted' => $deleted]);
        });
    }

    public function down(): void
    {
        // Необратимо: какие именно строки были у 1063, после переноса не отличить.
        // Восстановление — из дампа, снятого перед выкладкой.
    }
}
