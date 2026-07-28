<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Удаляет модели каталога (tovar_rent), на которые не ссылается ни одна из
 * таблиц с model_id: ни товаров, ни архива, ни веб-страницы, ни заявок,
 * ни звонков, ни фото, ни избранного. Сделок у таких моделей быть не может —
 * сделки привязаны к item_inv_n, а юнитов нет.
 *
 * Список ID сознательно НЕ хардкодится: условие пере-проверяется в момент
 * запуска. На 28.07.2026 на проде под него попадали 38 моделей, в локальном
 * снапшоте — 39 (модель 1852 успела обрасти данными на проде). Пересчёт
 * гарантирует, что удалится только реально несвязанное на момент деплоя.
 *
 * Восстановление — из дампа, снятого владельцем перед выкладкой.
 */
class CleanupUnreferencedModels extends Migration
{
    /** Таблицы, ссылающиеся на tovar_rent.tovar_rent_id через model_id. */
    private const REFERENCING_TABLES = [
        'tovar_rent_items',
        'tovar_rent_items_arch',
        'rent_model_web',
        'rent_orders',
        'rent_orders_arch',
        'zvonki',
        'dop_photos',
        'multi_web',
        'favorite_tovars',
        'kb_zayavki',
        'karnaval_zakaz',
    ];

    public function up(): void
    {
        $notExists = implode(' AND ', array_map(
            fn ($table) => "NOT EXISTS (SELECT 1 FROM {$table} x WHERE x.model_id = tr.tovar_rent_id)",
            self::REFERENCING_TABLES
        ));

        $rows = DB::select("SELECT tr.tovar_rent_id AS id FROM tovar_rent tr WHERE {$notExists}");
        $ids  = array_map(fn ($row) => (int) $row->id, $rows);

        if (empty($ids)) {
            logger()->info('CleanupUnreferencedModels: нечего удалять');
            return;
        }

        DB::transaction(function () use ($ids) {
            $tarifAct  = DB::table('rent_tarif_act')->whereIn('model_id', $ids)->delete();
            $tarifPrev = DB::table('rent_tarif_prev')->whereIn('model_id', $ids)->delete();
            $models    = DB::table('tovar_rent')->whereIn('tovar_rent_id', $ids)->delete();

            logger()->info('CleanupUnreferencedModels: удалено', [
                'models'          => $models,
                'rent_tarif_act'  => $tarifAct,
                'rent_tarif_prev' => $tarifPrev,
                'ids'             => $ids,
            ]);
        });
    }

    public function down(): void
    {
        // Необратимо: удалённые строки восстанавливаются только из дампа.
    }
}
