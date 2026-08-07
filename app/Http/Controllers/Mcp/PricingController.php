<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\RangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * История изменений тарифов и восстановление прайса на произвольную дату.
 *
 * Источник — `rent_tarif_history`: одно событие хранит полный снимок строки
 * тарифа до и после, поэтому состояние на дату D восстанавливается выбором
 * последнего события с `changed_at <= D` (см. docs/superpowers/specs/
 * 2026-07-31-tariff-history-design.md).
 */
class PricingController extends BaseController
{
    /** Дней в одном шаге тарифа — фиксированная конвертация, см. docs/tariffs.md. */
    private const STEP_DAYS = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365];

    /**
     * GET /pricing/history?model_id&category&from&to&change_type&actor_user_id&limit&offset
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_id'      => 'nullable|integer|min:1',
            'category'      => 'nullable|string',
            'from'          => 'nullable|date',
            'to'            => 'nullable|date|after_or_equal:from',
            'change_type'   => 'nullable|string|in:baseline,create,update,delete',
            'actor_user_id' => 'nullable|integer|min:1',
            'limit'         => 'nullable|integer|min:1|max:500',
            'offset'        => 'nullable|integer|min:0',
        ]);

        $limit  = (int) ($validated['limit'] ?? 100);
        $offset = (int) ($validated['offset'] ?? 0);

        $key = $this->cacheKey('pricing.history', [
            'model'  => $validated['model_id'] ?? 'all',
            'cat'    => $validated['category'] ?? 'all',
            'from'   => $validated['from'] ?? '',
            'to'     => $validated['to'] ?? '',
            'type'   => $validated['change_type'] ?? 'all',
            'actor'  => $validated['actor_user_id'] ?? 'all',
            'limit'  => $limit,
            'offset' => $offset,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_DEFAULT, function () use ($validated, $limit, $offset) {
            $where  = ['1 = 1'];
            $params = [];

            if (!empty($validated['model_id'])) {
                $where[]  = 'h.model_id = ?';
                $params[] = (int) $validated['model_id'];
            }
            if (!empty($validated['from'])) {
                $where[]  = 'h.changed_at >= ?';
                $params[] = strtotime($validated['from'] . ' 00:00:00');
            }
            if (!empty($validated['to'])) {
                $where[]  = 'h.changed_at <= ?';
                $params[] = strtotime($validated['to'] . ' 23:59:59');
            }
            if (!empty($validated['change_type'])) {
                $where[]  = 'h.change_type = ?';
                $params[] = $validated['change_type'];
            }
            if (!empty($validated['actor_user_id'])) {
                $where[]  = 'h.actor_user_id = ?';
                $params[] = (int) $validated['actor_user_id'];
            }

            $categories = $this->parseCategories($validated['category'] ?? null);
            if ($categories !== null) {
                $razdelIds = $this->categoryToRazdelIds($categories);
                if (empty($razdelIds)) {
                    return [];
                }
                $where[]  = 'h.model_id IN (' . $this->modelsInRazdelSubquery(count($razdelIds)) . ')';
                $params   = array_merge($params, $razdelIds);
            }

            $whereSql = implode(' AND ', $where);

            $sql = "
                SELECT h.*, rmw.l2_name AS model_name
                FROM rent_tarif_history h
                LEFT JOIN rent_model_web rmw ON rmw.model_id = h.model_id AND rmw.lang = 'ru'
                WHERE {$whereSql}
                ORDER BY h.changed_at DESC, h.id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";

            return array_map([$this, 'formatEvent'], DB::select($sql, $params));
        });

        return $this->envelope([
            'model_id'      => $validated['model_id'] ?? null,
            'category'      => $validated['category'] ?? 'all',
            'from'          => $validated['from'] ?? null,
            'to'            => $validated['to'] ?? null,
            'change_type'   => $validated['change_type'] ?? 'all',
            'actor_user_id' => $validated['actor_user_id'] ?? null,
            'limit'         => $limit,
            'offset'        => $offset,
        ], $rows);
    }

    /**
     * GET /pricing/snapshot?as_of=YYYY-MM-DD&model_id&category
     *
     * Прайс-лист на произвольную дату. Для каждой строки тарифа берётся
     * последнее событие с `changed_at <= as_of`.
     *
     * Строки, у которых событий до этой даты нет, но которые к ней уже
     * действовали (`new_start_date <= as_of`), попадают в ответ с
     * `extrapolated: true`. Причина — по-тарифная, не общая: для ЭТОЙ
     * конкретной строки тарифа в журнале нет ни одного записанного события
     * раньше `as_of`, поэтому отдаётся её baseline-запись — снимок на момент
     * последней известной правки именно этой строки (у каждой свой момент:
     * миграция 2026_07_31_000001 датировала baseline `change_date` из
     * `rent_tarif_act`, а не датой самой миграции; в базе baseline-события
     * разбросаны с 2013 по 2026 год). Что было до этой правки — неизвестно.
     * Доля таких строк тает по мере накопления реальных событий.
     *
     * Кеш: ответ хранится `TTL_HEAVY` (1 час) — правка тарифа в bb/rent_tarifs.php
     * кеш не инвалидирует. Для аналитики (агрегаты, тренды) это нормально, но
     * при РУЧНОЙ проверке «я только что поменял цену, а снимок на сегодня
     * показывает старую» это не баг: снимок может отставать от админки до часа.
     */
    public function snapshot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of'    => 'required|date',
            'model_id' => 'nullable|integer|min:1',
            'category' => 'nullable|string',
        ]);

        $asOf = strtotime($validated['as_of'] . ' 23:59:59');

        $key = $this->cacheKey('pricing.snapshot', [
            'as_of' => $asOf,
            'model' => $validated['model_id'] ?? 'all',
            'cat'   => $validated['category'] ?? 'all',
        ]);

        $payload = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($validated, $asOf) {
            $where  = [];
            $params = [];

            if (!empty($validated['model_id'])) {
                $where[]  = 'h.model_id = ?';
                $params[] = (int) $validated['model_id'];
            }

            $categories = $this->parseCategories($validated['category'] ?? null);
            if ($categories !== null) {
                $razdelIds = $this->categoryToRazdelIds($categories);
                if (empty($razdelIds)) {
                    return ['rows' => [], 'extrapolated' => 0, 'total' => 0];
                }
                $where[]  = 'h.model_id IN (' . $this->modelsInRazdelSubquery(count($razdelIds)) . ')';
                $params   = array_merge($params, $razdelIds);
            }

            $extraWhere = $where ? ' AND ' . implode(' AND ', $where) : '';

            // Ветки 1 и 2 ниже проверяют членство в снимке ПО-РАЗНОМУ, и это
            // осознанно, а не недосмотр: не «выравнивайте» их без пересмотра
            // этого рассуждения.
            //   - Ветка 1 (известные) вообще не смотрит на new_start_date:
            //     у неё есть ЗАПИСАННОЕ СОБЫТИЕ с changed_at <= as_of — это
            //     само по себе доказательство, что строка в таком виде
            //     существовала на дату as_of, независимо от того, что там
            //     написано в new_start_date (который отражает design-намерение
            //     «действует с X», а не факт наблюдения).
            //   - Ветка 2 (экстраполированные) обязана проверять
            //     new_start_date <= as_of, потому что для этих строк ТАКОГО
            //     доказательства нет: ни одного события до as_of не
            //     записано, единственный доступный признак «эта строка уже
            //     существовала на дату» — её собственный start_date.
            // Ветка 1 — тарифы, о которых на дату уже есть событие.
            // Порядок (changed_at, id): один только MAX(id) сломался бы там, где
            // импортированное legacy-удаление получило id выше baseline-события.
            //
            // Удалённый тариф держат вне снимка ДВЕ независимые защиты, и каждой
            // достаточно поодиночке: это `AND h.change_type <> 'delete'` ниже и
            // проверка на null в formatSide() — на реальных данных у delete-события
            // new_* всегда пустые (TariffHistory::log(TYPE_DELETE, $before, null, ...)
            // из bb/classes/Tariff.php).
            //
            // Проверено мутационно на
            // test_snapshot_excludes_tariff_after_delete_but_includes_it_before:
            // убрать только это условие — тест зелёный (ловит formatSide);
            // сломать только formatSide — тест зелёный (ловит это условие);
            // сломать обе — тест краснеет. То есть ни одна из них не «главная»,
            // и убирать любую из них поодиночке не стоит: тест не предупредит.
            $knownSql = "
                SELECT h.*, rmw.l2_name AS model_name, 0 AS extrapolated
                FROM rent_tarif_history h
                LEFT JOIN rent_model_web rmw ON rmw.model_id = h.model_id AND rmw.lang = 'ru'
                WHERE h.id = (
                    SELECT h2.id FROM rent_tarif_history h2
                    WHERE h2.tarif_id = h.tarif_id AND h2.changed_at <= ?
                    ORDER BY h2.changed_at DESC, h2.id DESC
                    LIMIT 1
                )
                AND h.change_type <> 'delete'
                {$extraWhere}
            ";
            $known = DB::select($knownSql, array_merge([$asOf], $params));

            // Ветка 2 — тариф действовал на дату, но событий до неё нет.
            $extrapolatedSql = "
                SELECT h.*, rmw.l2_name AS model_name, 1 AS extrapolated
                FROM rent_tarif_history h
                LEFT JOIN rent_model_web rmw ON rmw.model_id = h.model_id AND rmw.lang = 'ru'
                WHERE h.change_type = 'baseline'
                  AND h.changed_at > ?
                  AND h.new_start_date <= ?
                  AND NOT EXISTS (
                      SELECT 1 FROM rent_tarif_history h3
                      WHERE h3.tarif_id = h.tarif_id AND h3.changed_at <= ?
                  )
                {$extraWhere}
            ";
            $extrapolated = DB::select($extrapolatedSql, array_merge([$asOf, $asOf, $asOf], $params));

            $byModel          = [];
            $extrapolatedRows = 0;
            $totalRows        = 0;

            foreach (array_merge($known, $extrapolated) as $h) {
                $side = $this->formatSide($h, 'new');
                if ($side === null) {
                    continue;
                }

                $totalRows++;

                $modelId = (int) $h->model_id;
                if (!isset($byModel[$modelId])) {
                    $byModel[$modelId] = [
                        'model_id'          => $modelId,
                        'model_name'        => $h->model_name,
                        'min_price_per_day' => null,
                        'extrapolated'      => false,
                        'tariffs'           => [],
                    ];
                }

                $byModel[$modelId]['tariffs'][] = array_merge(
                    ['tarif_id' => (int) $h->tarif_id],
                    $side
                );

                if ((int) $h->extrapolated === 1) {
                    $byModel[$modelId]['extrapolated'] = true;
                    $extrapolatedRows++;
                }

                if ($side['price_per_day'] !== null) {
                    $current = $byModel[$modelId]['min_price_per_day'];
                    if ($current === null || (float) $side['price_per_day'] < (float) $current) {
                        $byModel[$modelId]['min_price_per_day'] = $side['price_per_day'];
                    }
                }
            }

            return ['rows' => array_values($byModel), 'extrapolated' => $extrapolatedRows, 'total' => $totalRows];
        });

        $meta = [];
        if ($payload['extrapolated'] > 0) {
            $pct = $payload['total'] > 0 ? round($payload['extrapolated'] / $payload['total'] * 100, 1) : 0.0;
            $meta['warnings'] = [
                [
                    'code'    => 'tariff_rows_extrapolated',
                    'message' => $payload['extrapolated'] . ' of ' . $payload['total'] . ' tariff rows in this snapshot ('
                        . $pct . '%) are extrapolated: each of these tarif_id rows has no recorded change-log '
                        . 'event before ' . $validated['as_of'] . ', so its baseline record (the state as of its '
                        . 'own last known edit) is returned instead of an observed value at the requested date.',
                ],
            ];
        }

        return $this->envelope([
            'as_of'    => $validated['as_of'],
            'model_id' => $validated['model_id'] ?? null,
            'category' => $validated['category'] ?? 'all',
        ], $payload['rows'], $meta);
    }

    /**
     * `null` означает «фильтра нет»; иначе — список слагов категорий из
     * белого списка `RangeRequest::CATEGORIES`.
     *
     * Приведено к общему контракту API (см. `RangeRequest::categories()`):
     *  - если в списке встречается `all` — фильтра нет целиком (return null),
     *    а не «список из ['all', 'children']», который ниже по стеку
     *    (`categoryToRazdelIds()`) сам схлопывается в [] по своему собственному
     *    short-circuit на 'all' и читается вызывающим кодом как «нет
     *    совпадений» → пустой ответ. Раньше это был именно этот баг:
     *    `category=all,children` тихо возвращал 0 строк без предупреждения;
     *  - значения вне белого списка отбрасываются (array_intersect), но,
     *    в отличие от `RangeRequest::categories()`, при пустом остатке метод
     *    возвращает [] (а не null/['all']) — здесь это осознанное отличие:
     *    у /pricing/history и /pricing/snapshot нет строгой enum-валидации
     *    параметра `category` (это просто 'nullable|string'), поэтому опечатка
     *    не должна молча превращаться в «фильтра нет» и отдавать весь массив
     *    данных — она обязана вернуть пустой результат (плюс warning
     *    unknown_category, который считает envelope() независимо по сырому
     *    query-параметру).
     *
     * @return string[]|null
     */
    protected function parseCategories(?string $category): ?array
    {
        if ($category === null || $category === '') {
            return null;
        }

        $cats = array_map('trim', explode(',', $category));
        if (in_array('all', $cats, true)) {
            return null;
        }

        return array_values(array_intersect($cats, RangeRequest::CATEGORIES));
    }

    /**
     * Подзапрос «модели указанных разделов». DISTINCT включен как оптимизация:
     * цепочка subrazdel_category × razdel_subrazdel создает many-to-many junction,
     * что может привести к дублям model_id в результате. Поскольку подзапрос
     * используется в условии `h.model_id IN (подзапрос)`, дубли в списке
     * семантически безразличны (x IN (1,1,2) = x IN (1,2)), и DISTINCT лишь
     * предотвращает материализацию раздутого списка. На КОРРЕКТНОСТЬ
     * результата это не влияет — в отличие от запросов, которые ДЖОЙНЯТ эту же
     * цепочку И одновременно агрегируют (SUM), где дубли реально раздувают суммы.
     */
    protected function modelsInRazdelSubquery(int $razdelCount): string
    {
        $placeholders = implode(',', array_fill(0, $razdelCount, '?'));
        return "
            SELECT DISTINCT tr.tovar_rent_id
            FROM tovar_rent tr
            JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tr.tovar_rent_cat_id
            JOIN razdel_subrazdel rs   ON rs.id_sub_razdel     = sc.id_sub_razdel
            WHERE rs.id_razdel IN ({$placeholders})
        ";
    }

    /**
     * Строка журнала → строка ответа API.
     *
     * @param object $h
     * @return array<string,mixed>
     */
    protected function formatEvent($h): array
    {
        $before = $this->formatSide($h, 'old');
        $after  = $this->formatSide($h, 'new');

        $deltaAmount = null;
        $deltaPct    = null;
        if ($before !== null && $after !== null) {
            $deltaAmount = number_format((float) $after['rent_amount'] - (float) $before['rent_amount'], 2, '.', '');
            if ($before['price_per_day'] !== null && $after['price_per_day'] !== null && (float) $before['price_per_day'] > 0) {
                $deltaPct = round(((float) $after['price_per_day'] - (float) $before['price_per_day'])
                    / (float) $before['price_per_day'] * 100, 1);
            }
        }

        return [
            'event_id'         => (int) $h->id,
            'changed_at'       => gmdate('Y-m-d\TH:i:s\Z', (int) $h->changed_at),
            'change_type'      => $h->change_type,
            'source'           => $h->source,
            'model_id'         => (int) $h->model_id,
            'model_name'       => $h->model_name,
            'tarif_id'         => (int) $h->tarif_id,
            'actor'            => [
                'user_id' => $h->actor_user_id !== null ? (int) $h->actor_user_id : null,
                'name'    => $h->actor_name,
            ],
            'before'           => $before,
            'after'            => $after,
            'delta_amount_byn' => $deltaAmount,
            'delta_pct'        => $deltaPct,
            'note'             => $h->note,
        ];
    }

    /**
     * Одна сторона события. `null`, если снимка нет (create/baseline не имеют
     * «до», delete не имеет «после»).
     *
     * @param object $h
     * @param string $prefix 'old' | 'new'
     * @return array<string,mixed>|null
     */
    protected function formatSide($h, string $prefix): ?array
    {
        $amountKey = $prefix . '_rent_amount';
        if ($h->$amountKey === null) {
            return null;
        }

        $stepKey      = $prefix . '_step';
        $kolVoKey     = $prefix . '_kol_vo';
        $kolVoMinKey  = $prefix . '_kol_vo_min';
        $perStepKey   = $prefix . '_rent_per_step';
        $startDateKey = $prefix . '_start_date';

        return [
            'step'          => $h->$stepKey,
            'kol_vo'        => (int) $h->$kolVoKey,
            'kol_vo_min'    => (int) $h->$kolVoMinKey,
            'rent_amount'   => number_format((float) $h->$amountKey, 2, '.', ''),
            'rent_per_step' => number_format((float) $h->$perStepKey, 2, '.', ''),
            'price_per_day' => $this->pricePerDay($h->$amountKey, $h->$stepKey, (int) $h->$kolVoKey),
            // date(), не gmdate(): start_date записан легаси-кодом как
            // strtotime('YYYY-MM-DD') в таймзоне приложения (config/app.php
            // → Europe/Minsk), т.е. это полночь по Минску. gmdate() рендерит
            // тот же момент в UTC (на 2-3 часа раньше) и переползает на
            // предыдущие сутки. changed_at в formatEvent() ниже — другой
            // случай: это полная ISO-8601 метка МОМЕНТА (не календарная
            // дата), для неё gmdate()+'Z' корректны.
            'start_date'    => $h->$startDateKey ? date('Y-m-d', (int) $h->$startDateKey) : null,
        ];
    }

    /**
     * Цена за день — единственная метрика, позволяющая сравнить тарифы
     * с разным шагом между собой.
     *
     * @return string|null
     */
    protected function pricePerDay($rentAmount, ?string $step, int $kolVo): ?string
    {
        $days = (self::STEP_DAYS[$step] ?? 0) * $kolVo;
        if ($days <= 0) {
            return null;
        }
        return number_format((float) $rentAmount / $days, 2, '.', '');
    }
}
