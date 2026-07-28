<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Чинит ссылки на модели, удалённые из `tovar_rent` в обход архива.
 *
 * ПРОИСХОЖДЕНИЕ. Это не «удалили товар», а недоделанные слияния дублей:
 * сотрудник переносил юниты на правильную модель, удалял модель-дубль через
 * `bb/tovar_del.php` — а тот чистит только `tovar_rent` + `tovar_rent_items`
 * и оставляет заявки, тарифы, фото и архивные юниты висеть в пустоту.
 * Удаление произошло после 06.07.2022 (в этот день все затронутые модели ещё
 * участвовали в массовом обновлении тарифов); журнала действий в БД нет.
 *
 * МЕТОД. У заявки хранится инвентарный номер, а по нему однозначно известно,
 * какой модели юнит принадлежит сегодня, — этого достаточно, чтобы вернуть
 * настоящую связь, а не выдумывать модель-заглушку. Карта строится на лету;
 * жёстко заданы только два случая, где сам юнит остался без модели.
 *
 * ЧТО ВОССТАНАВЛИВАЕТСЯ (прод, 28.07.2026):
 *   362  Fisher-price «Сад бабочек»  -> 107   (юнит-близнец 71133 уже на 107,
 *        тот же комплект, категория 9; в 2016-2018 модель жила в категории 11
 *        «Качели напольные», в марте 2018 переехала в 9 «Колыбель-качели»)
 *   1456 Lorelli манеж-кровать       -> 1565 «Torino 1» (7 юнитов закупки)
 *                                    -> 1458 «Moonlight» (2 юнита)
 *
 * ЧТО НЕ ВОССТАНАВЛИВАЕТСЯ: 55 веб-заявок моделей 257/479/482 — у них
 * `inv_n=0` и `cat_id=0`, товар по данным не определяется. 482 по снимкам =
 * «Развивающий мяч Tiny love», живого аналога в каталоге нет; 257 и 479 не
 * опознаются вообще. Заглушки для них не создаём: это была бы выдуманная
 * запись, которая попадёт в справочники и отчёты как реальная модель.
 *
 * ФОТО. 15 строк `dop_photos` удаляются, но ни один файл не пропадает: те же
 * снимки прописаны у живых моделей — `play_station_b_*` у 282 Lorelli
 * «Play Station», `fisher-price_butterfly_garden_papasan_b_*` у 107, 636 и
 * 1087. Исключение — `tiny_love_activity_ball_b_*` (модель 482): живого
 * владельца нет, но сами файлы остаются на диске в /public/rent/images/.
 *
 * ТАРИФЫ. 172 + 6 строк — цены моделей, которых не существует. Показать их
 * невозможно (нет ни страницы, ни юнитов), в расчёте аренды не участвуют:
 * применённый тариф хранится в самой сделке (docs/db_notes.md, п.8).
 */
class RepointOrphanedModelRefs extends Migration
{
    /**
     * Архивные юниты, чья модель удалена, -> модель-владелец.
     * 71127: близнец 71133 (тот же комплект «качели, балдахин, столик,
     *        3 подвесные игрушки, блок питания») уже принадлежит 107.
     * 705195: из закупки 17.06.2021 семь юнитов ушли на 1565, два на 1458;
     *        по цвету не различить (серые есть в обеих) — берём большинство.
     */
    private const UNIT_REPOINT = [
        71127  => 107,
        705195 => 1565,
    ];

    /**
     * Удалённая модель -> модель, определённая по смыслу. Добирает то, что не
     * ловится по инвентарному номеру: заявки, оформленные до выбора экземпляра
     * (`inv_n=0`), и юнит 362 без инвентарного номера (0 сделок, куплен
     * 31.12.2011, списан 02.02.2015 — номер ему так и не присвоили).
     */
    private const MODEL_FALLBACK = [
        362 => 107,
    ];

    private const ORDER_TABLES = ['rent_orders', 'rent_orders_arch'];

    private const UNIT_TABLES = ['tovar_rent_items', 'tovar_rent_items_arch'];

    public function up(): void
    {
        DB::transaction(function () {
            $stats = [
                'units_repointed'  => $this->repointOrphanedUnits(),
                'orders_by_inv'    => $this->repointOrdersByInventoryNumber(),
                'model_fallback'   => $this->repointRemainderByModel(),
                'tarifs_deleted'   => $this->deleteDanglingRows('rent_tarif_act')
                                    + $this->deleteDanglingRows('rent_tarif_prev'),
                'photos_deleted'   => $this->deleteDanglingRows('dop_photos'),
            ];

            logger()->info('RepointOrphanedModelRefs: выполнено', $stats + [
                'refs_left' => $this->countDanglingRefs(),
            ]);
        });
    }

    /**
     * Возвращает юниты на существующие модели. Делается ПЕРВЫМ шагом:
     * после него заявки по этим инвентарным номерам разрешаются автоматически.
     */
    private function repointOrphanedUnits(): int
    {
        $done = 0;

        foreach (self::UNIT_REPOINT as $invNumber => $targetModelId) {
            if (!$this->modelExists($targetModelId)) {
                continue;
            }

            foreach (self::UNIT_TABLES as $table) {
                $done += DB::table($table)
                    ->where('item_inv_n', $invNumber)
                    ->whereNotIn('model_id', [$targetModelId])
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('tovar_rent')
                        ->whereColumn('tovar_rent.tovar_rent_id', "{$table}.model_id"))
                    ->update(['model_id' => $targetModelId]);
            }
        }

        return $done;
    }

    /**
     * Заявка помнит инвентарный номер — по нему находим сегодняшнего владельца.
     * Берём только те номера, у которых владелец ровно один и он существует:
     * неоднозначные случаи чинить вслепую нельзя.
     */
    private function repointOrdersByInventoryNumber(): array
    {
        $result = [];

        foreach (self::ORDER_TABLES as $table) {
            $orphanInvNumbers = DB::table($table)
                ->where('model_id', '>', 0)
                ->where('inv_n', '>', 0)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('tovar_rent')
                    ->whereColumn('tovar_rent.tovar_rent_id', "{$table}.model_id"))
                ->distinct()
                ->pluck('inv_n')
                ->all();

            foreach ($this->resolveOwners($orphanInvNumbers) as $invNumber => $modelId) {
                $moved = DB::table($table)
                    ->where('inv_n', $invNumber)
                    ->where('model_id', '<>', $modelId)
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('tovar_rent')
                        ->whereColumn('tovar_rent.tovar_rent_id', "{$table}.model_id"))
                    ->update(['model_id' => $modelId]);

                if ($moved > 0) {
                    $result["{$table}:{$invNumber}->{$modelId}"] = $moved;
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<int>  $invNumbers
     * @return array<int, int>  инвентарный номер => id живой модели
     */
    private function resolveOwners(array $invNumbers): array
    {
        if (empty($invNumbers)) {
            return [];
        }

        $owners = [];

        foreach (self::UNIT_TABLES as $table) {
            $rows = DB::table($table)
                ->whereIn('item_inv_n', $invNumbers)
                ->select('item_inv_n', 'model_id')
                ->get();

            foreach ($rows as $row) {
                $owners[(int) $row->item_inv_n][(int) $row->model_id] = true;
            }
        }

        $resolved = [];

        foreach ($owners as $invNumber => $modelIds) {
            $modelIds = array_keys($modelIds);

            // Один и тот же юнит числится за двумя моделями — это отдельная
            // проблема, вслепую её не решаем.
            if (count($modelIds) !== 1) {
                logger()->warning('RepointOrphanedModelRefs: неоднозначный владелец', [
                    'inv_n' => $invNumber, 'models' => $modelIds,
                ]);
                continue;
            }

            if ($this->modelExists($modelIds[0])) {
                $resolved[$invNumber] = $modelIds[0];
            }
        }

        return $resolved;
    }

    /** Остаток, который не ловится по инвентарному номеру. */
    private function repointRemainderByModel(): array
    {
        $result = [];

        foreach (self::MODEL_FALLBACK as $oldModelId => $newModelId) {
            if ($this->modelExists($oldModelId) || !$this->modelExists($newModelId)) {
                continue;
            }

            foreach (array_merge(self::ORDER_TABLES, self::UNIT_TABLES) as $table) {
                $moved = DB::table($table)
                    ->where('model_id', $oldModelId)
                    ->update(['model_id' => $newModelId]);

                if ($moved > 0) {
                    $result["{$table}:{$oldModelId}->{$newModelId}"] = $moved;
                }
            }
        }

        return $result;
    }

    /** Строки, чья модель не существует: показать их невозможно. */
    private function deleteDanglingRows(string $table): int
    {
        return DB::table($table)
            ->where('model_id', '>', 0)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('tovar_rent')
                ->whereColumn('tovar_rent.tovar_rent_id', "{$table}.model_id"))
            ->delete();
    }

    private function countDanglingRefs(): array
    {
        $left = [];

        foreach (array_merge(self::ORDER_TABLES, self::UNIT_TABLES) as $table) {
            $left[$table] = DB::table($table)
                ->where('model_id', '>', 0)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('tovar_rent')
                    ->whereColumn('tovar_rent.tovar_rent_id', "{$table}.model_id"))
                ->count();
        }

        return $left;
    }

    private function modelExists(int $modelId): bool
    {
        return DB::table('tovar_rent')->where('tovar_rent_id', $modelId)->exists();
    }

    public function down(): void
    {
        // Необратимо: восстановление — из дампа, снятого перед выкладкой.
    }
}
