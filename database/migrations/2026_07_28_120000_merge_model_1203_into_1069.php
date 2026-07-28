<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Сливает дубль Nania 1203 в 1069. Обе записи — одно и то же кресло
 * Nania Cosmo SP Animals Elefant: подтверждено сотрудником 28.07.2026
 * («получается одинаковые»), правильное название — Cosmo SP, правильный
 * юнит — 719219 (тот, что дороже).
 *
 * Страницу 1203 по ошибке заполнили названием и описанием модели Driver,
 * поэтому её слаг, title, l2_name, alt'ы, keywords и описание говорят
 * «Driver» — доверять этому контенту нельзя, он скопирован из чужой карточки.
 *
 * ТАРИФЫ: у 1069 они выше (день 10/12/15, неделя 18/27/30/35) против
 * 1203 (9.10/10.40/11.70 и 13/17/20/25). Сотрудник назвал правильным
 * «который дороже», поэтому тарифы 1069 остаются, тарифы 1203 удаляются.
 *
 * Юнит 719242 на момент написания в активной сделке 137506. Это безопасно:
 * уже применённый тариф хранится в rent_sub_deals_act.tarif_value и при
 * возврате/просрочке берётся оттуда (bb/dogovor_new2.php:2807), а не из
 * rent_tarif_act. При НОВОМ продлении форма покажет актуальные тарифы 1069
 * и рядом кнопку «Последний использованный тариф» с прежней ценой —
 * выбор за оператором, автоматического подорожания нет.
 */
class MergeModel1203Into1069 extends Migration
{
    private const DUP  = 1203;
    private const KEEP = 1069;

    private const OLD_URL = '/ru/prokat-detskih-tovarov/autokresla/avtokreselo-naprokat/avtokreslo_nania_driver_animals_elefant';
    private const NEW_URL = '/ru/prokat-detskih-tovarov/autokresla/avtokreselo-naprokat/avtokreslo_nania_cosmo_sp_animals_elefant';

    public function up(): void
    {
        // Редиректы правим ВСЕГДА и до guard'а, чтобы шаг был идемпотентным:
        // при повторном запуске (слияние уже выполнено) цепочка всё равно
        // окажется выпрямленной.
        DB::table('redirects')->updateOrInsert(
            ['source_url' => self::OLD_URL],
            ['target_url' => self::NEW_URL, 'status_code' => 301, 'is_active' => 1]
        );

        // Уже существующие редиректы, ведущие на страницу Driver, перенаправляем
        // сразу на Cosmo SP — иначе получается цепочка 301 -> 301 (middleware
        // CheckRedirects делает один переход и по цепочке не идёт).
        $flattened = DB::table('redirects')
            ->where('target_url', self::OLD_URL)
            ->where('source_url', '<>', self::OLD_URL)
            ->update(['target_url' => self::NEW_URL]);

        logger()->info('MergeModel1203Into1069: редиректы', ['chains_flattened' => $flattened]);

        $dupExists  = DB::table('tovar_rent')->where('tovar_rent_id', self::DUP)->exists();
        $keepExists = DB::table('tovar_rent')->where('tovar_rent_id', self::KEEP)->exists();

        if (!$dupExists || !$keepExists) {
            logger()->info('MergeModel1203Into1069: слияние пропущено', [
                'dup_exists' => $dupExists, 'keep_exists' => $keepExists,
            ]);
            return;
        }

        DB::transaction(function () {
            $moved = [
                'tovar_rent_items'      => DB::table('tovar_rent_items')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
                'tovar_rent_items_arch' => DB::table('tovar_rent_items_arch')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
                'rent_orders'           => DB::table('rent_orders')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
                'rent_orders_arch'      => DB::table('rent_orders_arch')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
                'zvonki'                => DB::table('zvonki')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
            ];

            $deleted = [
                'rent_tarif_act'  => DB::table('rent_tarif_act')->where('model_id', self::DUP)->delete(),
                'rent_tarif_prev' => DB::table('rent_tarif_prev')->where('model_id', self::DUP)->delete(),
                'rent_model_web'  => DB::table('rent_model_web')->where('model_id', self::DUP)->delete(),
                'dop_photos'      => DB::table('dop_photos')->where('model_id', self::DUP)->delete(),
                'multi_web'       => DB::table('multi_web')->where('model_id', self::DUP)->delete(),
                'favorite_tovars' => DB::table('favorite_tovars')->where('model_id', self::DUP)->delete(),
                'kb_zayavki'      => DB::table('kb_zayavki')->where('model_id', self::DUP)->delete(),
                'tovar_rent'      => DB::table('tovar_rent')->where('tovar_rent_id', self::DUP)->delete(),
            ];

            logger()->info('MergeModel1203Into1069: слито', ['moved' => $moved, 'deleted' => $deleted]);
        });
    }

    public function down(): void
    {
        // Необратимо: восстановление — из дампа, снятого перед выкладкой.
    }
}
