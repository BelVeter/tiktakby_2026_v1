<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Полностью удаляет из каталога направление «строительный инструмент и садовая
 * техника». Это была коллаборация с другим прокатом, она прекращена — товары
 * никогда не были нашими (решение владельца 28.07.2026).
 *
 * Почему это надо удалить, а не спрятать:
 *  - 129 чужих юнитов числились со статусом to_rent, то есть попадали в остатки,
 *    в знаменатель utilization и в исторические срезы инвентаря с июля 2022;
 *  - 129 страниц отдавали HTTP 200 с canonical на главную (полный путь не
 *    строился: их категории ссылаются на несуществующие подразделы 17, 20,
 *    28-33, 36-41, а раздела «инструмент» в `razdel` нет вообще) — то есть мы
 *    сами сообщали поисковику, что это дубликаты главной;
 *  - страницы продолжали собирать брони на товар, которого нет: последняя —
 *    23.07.2026, всего 33 брони за 2022-2026.
 *
 * `rent_model_web.status='not_show'` здесь не помогает: `ModelWeb::getByUrlName()`
 * статус не фильтрует, страница продолжила бы отдавать 200. Удаляем строку —
 * тогда срабатывает штатный фолбэк `L3Controller::showCategoryWithNotice()`
 * и адрес отдаёт честный 404.
 *
 * История брони/заявок выгружена перед удалением в
 * docs/archive/2026-07-28-instrument-broni-i-zayavki.csv.
 *
 * ФИНАНСЫ: удаляются 2 сделки августа 2022 (кусторез Makita DUH523, инв. 1023)
 * и 5 их sub-deals, из которых один платёж на 70.00 руб. Выручка за август 2022
 * уменьшится на эту сумму — владелец подтвердил, что это тест.
 */
class PurgeConstructionToolsCatalog extends Migration
{
    /** Категории направления. Список зафиксирован явно — по нему шла сверка. */
    private const CATEGORY_IDS = [
        100, 101, 102, 103, 104, 105, 106, 107, 110, 111, 112, 113, 114, 116,
        117, 118, 119, 120, 121, 122, 123, 124, 126, 127, 128, 129, 130, 131,
        132, 134, 135, 136, 138, 139, 140, 141, 142,
    ];

    public function up(): void
    {
        $modelIds = DB::table('tovar_rent')
            ->whereIn('tovar_rent_cat_id', self::CATEGORY_IDS)
            ->pluck('tovar_rent_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($modelIds)) {
            logger()->info('PurgeConstructionToolsCatalog: нечего удалять');
            return;
        }

        // Инвентарные номера нужны до удаления юнитов — по ним ищутся сделки.
        $invNumbers = DB::table('tovar_rent_items')->whereIn('model_id', $modelIds)->pluck('item_inv_n')
            ->merge(DB::table('tovar_rent_items_arch')->whereIn('model_id', $modelIds)->pluck('item_inv_n'))
            ->unique()->values()->all();

        DB::transaction(function () use ($modelIds, $invNumbers) {
            $stats = [];

            // 1. Сделки и их платежи (sub-deals).
            if (!empty($invNumbers)) {
                $dealIds = DB::table('rent_deals_arch')->whereIn('item_inv_n', $invNumbers)->pluck('deal_id')
                    ->merge(DB::table('rent_deals_act')->whereIn('item_inv_n', $invNumbers)->pluck('deal_id'))
                    ->unique()->values()->all();

                if (!empty($dealIds)) {
                    $stats['rent_sub_deals_arch'] = DB::table('rent_sub_deals_arch')->whereIn('deal_id', $dealIds)->delete();
                    $stats['rent_sub_deals_act']  = DB::table('rent_sub_deals_act')->whereIn('deal_id', $dealIds)->delete();
                    $stats['rent_deals_arch']     = DB::table('rent_deals_arch')->whereIn('deal_id', $dealIds)->delete();
                    $stats['rent_deals_act']      = DB::table('rent_deals_act')->whereIn('deal_id', $dealIds)->delete();
                }
            }

            // 2. Брони, заявки, звонки, избранное.
            $stats['rent_orders']      = DB::table('rent_orders')->whereIn('model_id', $modelIds)->delete();
            $stats['rent_orders_arch'] = DB::table('rent_orders_arch')->whereIn('model_id', $modelIds)->delete();
            $stats['zvonki']           = DB::table('zvonki')->whereIn('model_id', $modelIds)->delete();
            $stats['kb_zayavki']       = DB::table('kb_zayavki')->whereIn('model_id', $modelIds)->delete();
            $stats['karnaval_zakaz']   = DB::table('karnaval_zakaz')->whereIn('model_id', $modelIds)->delete();
            $stats['favorite_tovars']  = DB::table('favorite_tovars')->whereIn('model_id', $modelIds)->delete();

            // 3. Контент и цены.
            $stats['rent_model_web']  = DB::table('rent_model_web')->whereIn('model_id', $modelIds)->delete();
            $stats['dop_photos']      = DB::table('dop_photos')->whereIn('model_id', $modelIds)->delete();
            $stats['multi_web']       = DB::table('multi_web')->whereIn('model_id', $modelIds)->delete();
            $stats['rent_tarif_act']  = DB::table('rent_tarif_act')->whereIn('model_id', $modelIds)->delete();
            $stats['rent_tarif_prev'] = DB::table('rent_tarif_prev')->whereIn('model_id', $modelIds)->delete();

            // 4. Юниты, модели, категории.
            $stats['tovar_rent_items']      = DB::table('tovar_rent_items')->whereIn('model_id', $modelIds)->delete();
            $stats['tovar_rent_items_arch'] = DB::table('tovar_rent_items_arch')->whereIn('model_id', $modelIds)->delete();
            $stats['tovar_rent']            = DB::table('tovar_rent')->whereIn('tovar_rent_id', $modelIds)->delete();
            $stats['subrazdel_category']    = DB::table('subrazdel_category')->whereIn('tovar_rent_cat_id', self::CATEGORY_IDS)->delete();
            $stats['tovar_rent_cat']        = DB::table('tovar_rent_cat')->whereIn('tovar_rent_cat_id', self::CATEGORY_IDS)->delete();

            logger()->info('PurgeConstructionToolsCatalog: удалено', [
                'models' => count($modelIds),
                'stats'  => $stats,
            ]);
        });

        // Записи в redirects не трогаем (на инструмент их нет), но кэш
        // CheckRedirects всё равно сбрасываем — правило из docs/db_notes.md.
        Cache::forget('redirects_exact_map');
        Cache::forget('redirects_regex_list');
    }

    public function down(): void
    {
        // Необратимо. Восстановление — из дампа, снятого перед выкладкой;
        // история броней/заявок продублирована в docs/archive/.
    }
}
