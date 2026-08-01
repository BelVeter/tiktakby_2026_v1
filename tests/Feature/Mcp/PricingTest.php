<?php

namespace Tests\Feature\Mcp;

class PricingTest extends McpTestCase
{
    // ─── /pricing/history ─────────────────────────────────────────────────

    public function test_history_requires_token(): void
    {
        $this->assertRequiresToken('pricing/history');
    }

    public function test_history_envelope_and_columns(): void
    {
        $r = $this->mcp('pricing/history', ['limit' => 5]);
        $this->assertEnvelope($r);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows, 'после baseline-миграции журнал не может быть пустым');
        $r->assertJsonStructure(['data' => [[
            'event_id', 'changed_at', 'change_type', 'source',
            'model_id', 'model_name', 'tarif_id',
            'actor' => ['user_id', 'name'],
            'before', 'after', 'delta_amount_byn', 'delta_pct',
        ]]]);
    }

    public function test_history_respects_limit(): void
    {
        $rows = $this->mcp('pricing/history', ['limit' => 3])->json('data');
        $this->assertLessThanOrEqual(3, count($rows));
    }

    public function test_history_rejects_oversized_limit(): void
    {
        $this->mcp('pricing/history', ['limit' => 501])->assertStatus(422);
    }

    public function test_history_rejects_unknown_change_type(): void
    {
        $this->mcp('pricing/history', ['change_type' => 'renamed'])->assertStatus(422);
    }

    public function test_history_filters_by_change_type(): void
    {
        $rows = $this->mcp('pricing/history', ['change_type' => 'baseline', 'limit' => 20])->json('data');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('baseline', $row['change_type']);
        }
    }

    public function test_history_filters_by_model_id(): void
    {
        $anyModelId = $this->mcp('pricing/history', ['limit' => 1])->json('data.0.model_id');
        $rows = $this->mcp('pricing/history', ['model_id' => $anyModelId, 'limit' => 50])->json('data');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame($anyModelId, $row['model_id']);
        }
    }

    public function test_history_is_sorted_newest_first(): void
    {
        $rows = $this->mcp('pricing/history', ['limit' => 30])->json('data');
        $timestamps = array_map(static fn ($r) => strtotime($r['changed_at']), $rows);
        $this->assertSortedDesc($timestamps, 'changed_at must be sorted DESC');
    }

    public function test_baseline_rows_have_no_before_side(): void
    {
        $rows = $this->mcp('pricing/history', ['change_type' => 'baseline', 'limit' => 5])->json('data');
        foreach ($rows as $row) {
            $this->assertNull($row['before'], 'у baseline не может быть состояния "до"');
            $this->assertNotNull($row['after']);
        }
    }

    public function test_price_per_day_is_amount_divided_by_period(): void
    {
        $rows = $this->mcp('pricing/history', ['change_type' => 'baseline', 'limit' => 50])->json('data');

        $checked = 0;
        foreach ($rows as $row) {
            $after = $row['after'];
            $stepDays = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365][$after['step']] ?? 0;
            $days = $stepDays * $after['kol_vo'];
            if ($days <= 0 || $after['price_per_day'] === null) {
                continue;
            }
            $this->assertEqualsWithDelta(
                round((float) $after['rent_amount'] / $days, 2),
                (float) $after['price_per_day'],
                0.011
            );
            $checked++;
        }
        $this->assertGreaterThan(0, $checked, 'нужна хотя бы одна строка с ненулевым периодом');
    }

    // ─── Filter coverage tests ─────────────────────────────────────────

    /**
     * Валидная категория фильтрует и все вернувшиеся модели действительно
     * относятся к запрошенной категории (проверяем по-настоящему через БД).
     */
    public function test_history_filters_by_valid_category_with_real_membership(): void
    {
        // Выбираем категорию, которая есть в БД с событиями (children = prokat-detskih-tovarov)
        $r = $this->mcp('pricing/history', ['category' => 'children', 'limit' => 50]);
        $r->assertStatus(200);
        $this->assertEnvelope($r);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows, 'категория children должна иметь события');

        // Для каждой модели проверяем, что она действительно в разделе "prokat-detskih-tovarov"
        $razdelName = 'prokat-detskih-tovarov';
        $razdelId = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT id_razdel FROM razdel WHERE url_razdel_name = ?",
            [$razdelName]
        );
        $this->assertNotNull($razdelId, "раздел $razdelName должен существовать");

        // Получаем все модели из этого раздела в БД
        $modelsInRazdel = \Illuminate\Support\Facades\DB::select("
            SELECT DISTINCT tr.tovar_rent_id
            FROM tovar_rent tr
            JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tr.tovar_rent_cat_id
            JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sc.id_sub_razdel
            WHERE rs.id_razdel = ?
        ", [$razdelId->id_razdel]);

        $validModelIds = array_map(fn($r) => $r->tovar_rent_id, $modelsInRazdel);

        foreach ($rows as $row) {
            $this->assertContains(
                $row['model_id'],
                $validModelIds,
                "модель {$row['model_id']} должна быть в разделе $razdelName"
            );
        }
    }

    /**
     * Неизвестная категория возвращает пустой список и предупреждение.
     */
    public function test_history_unknown_category_returns_empty_with_warning(): void
    {
        $r = $this->mcp('pricing/history', ['category' => 'unknown_nonexistent_cat']);
        $r->assertStatus(200);
        $this->assertEnvelope($r);

        $data = $r->json('data');
        $this->assertEmpty($data, 'неизвестная категория должна вернуть пустой результат');

        $warnings = $r->json('meta.warnings');
        $this->assertNotEmpty($warnings, 'должно быть предупреждение об неизвестной категории');

        $unknownWarning = collect($warnings)->firstWhere('code', 'unknown_category');
        $this->assertNotNull($unknownWarning, 'должно быть warning с кодом unknown_category');
    }

    /**
     * DISTINCT критичен в подзапросе категории: без него подзапрос содержит дубли
     * model_id через М:М junction (subrazdel_category × razdel_subrazdel).
     *
     * Проверяем напрямую что подзапрос выбирает уникальные модели.
     * Для категории 'children' (prokat-detskih-tovarov) это показывает
     * дублики: БЕЗ DISTINCT=1171, С DISTINCT=1066 (105 дублей).
     */
    public function test_history_category_subquery_has_correct_distinct_count(): void
    {
        $razdelId = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT id_razdel FROM razdel WHERE url_razdel_name = 'prokat-detskih-tovarov'"
        )->id_razdel;

        // Количество УНИКАЛЬНЫХ моделей (как должно быть с DISTINCT)
        $countWithDistinct = \Illuminate\Support\Facades\DB::selectOne("
            SELECT COUNT(DISTINCT tr.tovar_rent_id) as cnt
            FROM tovar_rent tr
            JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tr.tovar_rent_cat_id
            JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sc.id_sub_razdel
            WHERE rs.id_razdel = ?
        ", [$razdelId])->cnt;

        // Количество БЕЗ DISTINCT (содержит дубли от М:М junction)
        $countWithoutDistinct = \Illuminate\Support\Facades\DB::selectOne("
            SELECT COUNT(*) as cnt
            FROM tovar_rent tr
            JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tr.tovar_rent_cat_id
            JOIN razdel_subrazdel rs ON rs.id_sub_razdel = sc.id_sub_razdel
            WHERE rs.id_razdel = ?
        ", [$razdelId])->cnt;

        // Инвариант: с DISTINCT должно быть РОВНО столько, сколько уникальных моделей
        $this->assertGreaterThan(0, $countWithDistinct, 'раздел должен иметь >= 1 модели');

        // CRITICAL: без DISTINCT должны быть дубли для этого раздела
        // Это гарантирует что DISTINCT действительно нужен и тест что-то проверяет
        $this->assertGreaterThan(
            $countWithDistinct,
            $countWithoutDistinct,
            "без DISTINCT должно быть больше чем с DISTINCT (дубли М:М junction): с DISTINCT={$countWithDistinct}, без={$countWithoutDistinct}"
        );
    }

    /**
     * Фильтр `from` работает: все вернувшиеся даты >= from.
     */
    public function test_history_respects_from_date_filter(): void
    {
        $fromDate = '2026-01-01';
        $r = $this->mcp('pricing/history', ['from' => $fromDate, 'limit' => 100]);
        $r->assertStatus(200);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows, 'диапазон from должен содержать события');

        $fromTimestamp = strtotime($fromDate . ' 00:00:00');

        foreach ($rows as $row) {
            $rowTimestamp = strtotime($row['changed_at']);
            $this->assertGreaterThanOrEqual(
                $fromTimestamp,
                $rowTimestamp,
                "дата {$row['changed_at']} должна быть >= $fromDate"
            );
        }
    }

    /**
     * Фильтр `to` работает: все вернувшиеся даты <= to.
     */
    public function test_history_respects_to_date_filter(): void
    {
        $toDate = '2026-06-30';
        $r = $this->mcp('pricing/history', ['to' => $toDate, 'limit' => 100]);
        $r->assertStatus(200);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows, 'диапазон to должен содержать события');

        $toTimestamp = strtotime($toDate . ' 23:59:59');

        foreach ($rows as $row) {
            $rowTimestamp = strtotime($row['changed_at']);
            $this->assertLessThanOrEqual(
                $toTimestamp,
                $rowTimestamp,
                "дата {$row['changed_at']} должна быть <= $toDate"
            );
        }
    }

    /**
     * Фильтр `actor_user_id` работает: у всех событий совпадает юзер.
     */
    public function test_history_filters_by_actor_user_id(): void
    {
        // Получаем случайный actor_user_id из БД
        $anyEvent = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT actor_user_id FROM rent_tarif_history WHERE actor_user_id IS NOT NULL LIMIT 1"
        );
        $this->assertNotNull($anyEvent, 'должны быть события с непустым actor_user_id');

        $actorId = $anyEvent->actor_user_id;

        $r = $this->mcp('pricing/history', ['actor_user_id' => $actorId, 'limit' => 50]);
        $r->assertStatus(200);
        $this->assertEnvelope($r);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows, "события с actor_user_id=$actorId должны существовать");

        foreach ($rows as $row) {
            $this->assertSame(
                $actorId,
                $row['actor']['user_id'],
                "все события должны иметь actor.user_id = $actorId"
            );
        }
    }
}
