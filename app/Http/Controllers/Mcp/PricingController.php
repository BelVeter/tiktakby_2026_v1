<?php

namespace App\Http\Controllers\Mcp;

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
     * `null` означает «фильтра нет»; иначе — список слагов категорий.
     *
     * @return string[]|null
     */
    protected function parseCategories(?string $category): ?array
    {
        if ($category === null || $category === '' || $category === 'all') {
            return null;
        }
        return array_map('trim', explode(',', $category));
    }

    /**
     * Подзапрос «модели указанных разделов». DISTINCT включен как оптимизация:
     * цепочка subrazdel_category × razdel_subrazdel создает many-to-many junction,
     * что может привести к дублям model_id в результате. Поскольку подзапрос
     * используется в условии `h.model_id IN (подзапрос)`, дубли в списке
     * семантически неправильны (x IN (1,1,2) = x IN (1,2)), поэтому DISTINCT
     * предотвращает материализацию раздутого списка. Однако на КОРРЕКТНОСТЬ
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
            'start_date'    => $h->$startDateKey ? gmdate('Y-m-d', (int) $h->$startDateKey) : null,
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
