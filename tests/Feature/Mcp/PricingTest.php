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

    // ─── /pricing/snapshot ────────────────────────────────────────────────

    public function test_snapshot_requires_as_of(): void
    {
        $this->mcp('pricing/snapshot')->assertStatus(422);
    }

    public function test_snapshot_rejects_malformed_as_of(): void
    {
        $this->mcp('pricing/snapshot', ['as_of' => 'вчера'])->assertStatus(422);
    }

    public function test_snapshot_envelope_and_columns(): void
    {
        $r = $this->mcp('pricing/snapshot', ['as_of' => date('Y-m-d')]);
        $this->assertEnvelope($r);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows);
        $r->assertJsonStructure(['data' => [[
            'model_id', 'model_name', 'min_price_per_day', 'extrapolated',
            'tariffs' => [['tarif_id', 'step', 'kol_vo', 'rent_amount', 'price_per_day']],
        ]]]);
    }

    public function test_snapshot_today_matches_live_tariff_table(): void
    {
        $rows = $this->mcp('pricing/snapshot', ['as_of' => date('Y-m-d')])->json('data');

        $snapshotTariffs = 0;
        foreach ($rows as $row) {
            $snapshotTariffs += count($row['tariffs']);
        }

        $live = (int) \Illuminate\Support\Facades\DB::selectOne(
            'SELECT COUNT(*) AS n FROM rent_tarif_act'
        )->n;

        $this->assertSame($live, $snapshotTariffs,
            'снимок на сегодня должен совпадать с живой таблицей тарифов');
    }

    public function test_snapshot_far_past_is_empty_or_extrapolated(): void
    {
        // 2010 год раньше самых старых данных в rent_tarif_history: ни у одной
        // строки нет changed_at или new_start_date <= 2010 (самые ранние —
        // 2013-11-13), поэтому единственный корректный ответ — пустой снимок.
        // Утверждаем это явно (а не полагаемся на пустой foreach, который
        // раньше не делал ни одного assert и PHPUnit помечал тест как risky).
        $rows = $this->mcp('pricing/snapshot', ['as_of' => '2010-01-01'])->json('data');

        if (empty($rows)) {
            $this->assertSame([], $rows, 'до начала данных (2013-11-13) снимок обязан быть пустым');
            return;
        }

        // Если данные когда-нибудь появятся раньше 2010 года — единственный
        // допустимый способ попасть в снимок на эту дату — экстраполяция.
        foreach ($rows as $row) {
            $this->assertTrue($row['extrapolated'], 'до начала данных строки могут быть только экстраполированными');
        }
    }

    public function test_snapshot_warns_about_extrapolated_rows(): void
    {
        $r = $this->mcp('pricing/snapshot', ['as_of' => '2015-01-01']);
        $warnings = $r->json('meta.warnings');
        $extrapolated = array_filter($r->json('data'), static fn ($row) => $row['extrapolated']);

        if (empty($extrapolated)) {
            $this->assertTrue(true, 'на эту дату экстраполированных строк нет — предупреждению взяться неоткуда');
            return;
        }

        $this->assertNotEmpty($warnings, 'наличие экстраполяции обязано попасть в meta.warnings');

        // Находка 2: предупреждение обязано нести саму ДОЛЮ, а не только
        // абсолютное число — meta.total_rows считает модели, а не строки
        // тарифов, так что готового знаменателя нигде больше в ответе нет.
        // Проверяем, что "N of M tariff rows ... (P%)" присутствует и что
        // P арифметически совпадает с N/M.
        $warningText = implode(' ', $warnings);
        $matched = preg_match('/(\d+) of (\d+) tariff rows.*?\(([\d.]+)%\)/', $warningText, $m);
        $this->assertSame(1, $matched, "предупреждение должно содержать 'N of M tariff rows ... (P%)': {$warningText}");

        [, $extrapolatedCount, $totalCount, $pct] = $m;
        $this->assertGreaterThan(0, (int) $totalCount, 'знаменатель доли должен быть положительным');
        $this->assertEqualsWithDelta(
            round(((int) $extrapolatedCount / (int) $totalCount) * 100, 1),
            (float) $pct,
            0.05,
            'процент в предупреждении обязан соответствовать N/M'
        );
    }

    public function test_snapshot_filters_by_model_id(): void
    {
        $anyModelId = $this->mcp('pricing/snapshot', ['as_of' => date('Y-m-d')])->json('data.0.model_id');
        $rows = $this->mcp('pricing/snapshot', ['as_of' => date('Y-m-d'), 'model_id' => $anyModelId])->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($anyModelId, $rows[0]['model_id']);
    }

    /**
     * Находка 4: `delete`-события — смысловое ядро эндпоинта (удалённый тариф
     * обязан исчезнуть из снимка после удаления, но оставаться в снимках "до"),
     * а на живых данных это не воспроизвести: ни у одного tarif_id нет и
     * delete-события, и ещё какого-либо другого (миграция 2026_07_31_000001
     * заливала delete из rent_tarif_prev отдельным потоком от baseline).
     * Заводим синтетическую пару под заведомо несуществующий tarif_id/model_id
     * и убираем её за собой в finally — тест обязан быть идемпотентным при
     * повторных прогонах.
     */
    public function test_snapshot_excludes_tariff_after_delete_but_includes_it_before(): void
    {
        $tarifId = 999998;
        $modelId = 999997;

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('rent_tarif_history')
            ->where('tarif_id', $tarifId)->count(), 'tarif_id 999998 не должен существовать заранее');

        $t1 = strtotime('2020-06-01 12:00:00'); // create
        $t2 = strtotime('2020-06-15 12:00:00'); // delete, t2 > t1

        // Два отдельных insert() вместо одного батча: Laravel строит список колонок
        // батч-инсёрта по ключам ПЕРВОЙ строки, а у create/delete набор колонок разный.
        \Illuminate\Support\Facades\DB::table('rent_tarif_history')->insert([
            'tarif_id'          => $tarifId,
            'model_id'          => $modelId,
            'change_type'       => 'create',
            'changed_at'        => $t1,
            'source'            => 'bb_admin',
            'new_step'          => 'week',
            'new_kol_vo'        => 1,
            'new_kol_vo_min'    => 1,
            'new_rent_amount'   => 10.00,
            'new_rent_per_step' => 10.00,
            'new_start_date'    => $t1,
            'new_sort_num'      => 1,
        ]);
        // new_* НЕ заполняем: настоящий писатель (bb/classes/Tariff.php:125,
        // TariffHistory::log(TYPE_DELETE, $before, null, ...) →
        // TariffHistory::snapshotColumns($mysqli, 'new', null)) всегда пишет
        // на delete-событии new_* = NULL. Событие delete с непустыми new_*
        // в этой системе возникнуть не может — тест проверяет контракт на
        // данных той же формы, что пишет продакшн-код, а не на синтетическом
        // входе, который в проде не встречается.
        \Illuminate\Support\Facades\DB::table('rent_tarif_history')->insert([
            'tarif_id'          => $tarifId,
            'model_id'          => $modelId,
            'change_type'       => 'delete',
            'changed_at'        => $t2,
            'source'            => 'bb_admin',
            'old_step'          => 'week',
            'old_kol_vo'        => 1,
            'old_kol_vo_min'    => 1,
            'old_rent_amount'   => 10.00,
            'old_rent_per_step' => 10.00,
            'old_start_date'    => $t1,
            'old_sort_num'      => 1,
        ]);

        try {
            // Между T1 и T2 — тариф уже создан, ещё не удалён: обязан быть в снимке.
            $before = $this->mcp('pricing/snapshot', [
                'as_of'    => '2020-06-10',
                'model_id' => $modelId,
            ])->json('data');
            $this->assertCount(1, $before, 'до удаления модель с этим тарифом обязана быть в снимке');
            $this->assertSame(
                $tarifId,
                $before[0]['tariffs'][0]['tarif_id'] ?? null,
                'в снимке "до" ожидается именно наш синтетический tarif_id'
            );

            // После T2 — тариф удалён: обязан исчезнуть из снимка.
            $after = $this->mcp('pricing/snapshot', [
                'as_of'    => '2020-06-20',
                'model_id' => $modelId,
            ])->json('data');
            $this->assertEmpty($after, 'после удаления модель не должна попадать в снимок');
        } finally {
            \Illuminate\Support\Facades\DB::table('rent_tarif_history')->where('tarif_id', $tarifId)->delete();
        }
    }

    // ─── /operations/deals-by-model ───────────────────────────────────────

    public function test_deals_by_model_envelope_and_columns(): void
    {
        $r = $this->mcp('operations/deals-by-model', [
            'from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'month',
        ]);
        $this->assertEnvelope($r);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows);
        $r->assertJsonStructure(['data' => [[
            'model_id', 'model_name', 'period',
            'deals_started', 'units_at_period_end', 'deals_per_unit',
        ]]]);
    }

    public function test_deals_by_model_periods_are_inside_requested_range(): void
    {
        $rows = $this->mcp('operations/deals-by-model', [
            'from' => '2024-03-01', 'to' => '2024-05-31', 'granularity' => 'month',
        ])->json('data');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertContains($row['period'], ['2024-03', '2024-04', '2024-05']);
        }
    }

    public function test_deals_by_model_counts_deals_created_in_period(): void
    {
        $from = strtotime('2024-06-01 00:00:00');
        $to   = strtotime('2024-06-30 23:59:59');

        $rows = $this->mcp('operations/deals-by-model', [
            'from' => '2024-06-01', 'to' => '2024-06-30', 'granularity' => 'month',
        ])->json('data');

        $apiTotal = array_sum(array_map(static fn ($r) => $r['deals_started'], $rows));

        // Эталон: сделки, ЗАВЕДЁННЫЕ в периоде (cr_time), у которых юнит
        // разрешается в модель. Это отличается от /inventory/utilization,
        // который считает сделки, ПЕРЕСЕКАЮЩИЕ период.
        $expected = (int) \Illuminate\Support\Facades\DB::selectOne("
            SELECT COUNT(DISTINCT d.deal_id) AS n FROM (
                SELECT deal_id, item_inv_n, cr_time FROM rent_deals_act
                UNION ALL
                SELECT deal_id, item_inv_n, cr_time FROM rent_deals_arch
            ) d
            JOIN (
                SELECT item_inv_n, model_id FROM tovar_rent_items
                UNION ALL
                SELECT item_inv_n, model_id FROM tovar_rent_items_arch
            ) i ON i.item_inv_n = d.item_inv_n
            WHERE d.cr_time BETWEEN ? AND ? AND i.model_id IS NOT NULL
        ", [$from, $to])->n;

        $this->assertSame($expected, $apiTotal);
    }

    public function test_deals_by_model_filters_by_model_id(): void
    {
        $anyModelId = $this->mcp('operations/deals-by-model', [
            'from' => '2024-01-01', 'to' => '2024-12-31',
        ])->json('data.0.model_id');

        $rows = $this->mcp('operations/deals-by-model', [
            'from' => '2024-01-01', 'to' => '2024-12-31', 'model_id' => $anyModelId,
        ])->json('data');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame($anyModelId, $row['model_id']);
        }
    }

    public function test_deals_by_model_skips_inventory_for_too_many_periods(): void
    {
        // day-гранулярность за год — 366 периодов, знаменатель не считается.
        $r = $this->mcp('operations/deals-by-model', [
            'from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'day',
        ]);
        $r->assertStatus(200);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows);
        $this->assertNull($rows[0]['units_at_period_end']);
        $this->assertNull($rows[0]['deals_per_unit']);
        $this->assertNotEmpty($r->json('meta.warnings'));
    }

    // ─── Пробел 1: фильтр по категории (many-to-many ловушка) ─────────────

    /**
     * Ни один из старых тестов не передавал `category`, поэтому JOIN через
     * itemsInRazdelSubquery() (BaseController) вообще не исполнялся. Это
     * COUNT-агрегат — неверный join (например, наивный
     * subrazdel_category × razdel_subrazdel без DISTINCT) раздувает счётчик
     * в M×N раз, а не просто теряет строки, поэтому сверяем именно СУММУ
     * deals_started с независимым запросом, а не "что-то вернулось".
     *
     * Категория подбирается динамически (перебором слагов RangeRequest::CATEGORIES
     * с реальным razdel) на диапазоне 2024-01-01..2024-03-31: если на дампе
     * не найдётся ни одной категории с данными, тест явно падает через
     * markTestSkipped с указанием причины — не проходит молча на пустом ответе.
     */
    public function test_deals_by_model_category_filter_matches_independent_razdel_count(): void
    {
        $from   = '2024-01-01';
        $to     = '2024-03-31';
        $fromTs = strtotime($from . ' 00:00:00');
        $toTs   = strtotime($to . ' 23:59:59');

        // Соответствие API-слага (RangeRequest::CATEGORIES) и url_razdel_name —
        // повторяет BaseController::categoryToRazdelId(), но независимо (не
        // вызывает сам метод), чтобы бага в маппинге не остался незамеченным.
        $slugToUrlName = [
            'children' => 'prokat-detskih-tovarov',
            'sports'   => 'prokat-sports',
            'medical'  => 'medical-prokat',
            'costumes' => 'karnavalnye-kostyumy',
            'cleaning' => 'prokat-uborka',
        ];

        // Независимый подсчёт "сделок, заведённых в периоде, у моделей раздела X",
        // переписанный заново (не переиспользует itemsInRazdelSubquery()/
        // unifiedItemsSubquery()/unifiedDealsSubquery() из BaseController) —
        // иначе баг в общем helper'е был бы невидим для теста.
        $countForRazdel = static function (int $razdelId) use ($fromTs, $toTs): int {
            return (int) \Illuminate\Support\Facades\DB::selectOne("
                SELECT COUNT(DISTINCT d.deal_id) AS n
                FROM (
                    SELECT deal_id, item_inv_n, cr_time FROM rent_deals_act
                    UNION ALL
                    SELECT deal_id, item_inv_n, cr_time FROM rent_deals_arch
                ) d
                JOIN (
                    SELECT item_inv_n, cat_id, model_id FROM tovar_rent_items
                    UNION ALL
                    SELECT item_inv_n, cat_id, model_id FROM tovar_rent_items_arch
                ) i ON i.item_inv_n = d.item_inv_n
                JOIN (
                    SELECT DISTINCT i2.item_inv_n
                    FROM (
                        SELECT item_inv_n, cat_id FROM tovar_rent_items
                        UNION ALL
                        SELECT item_inv_n, cat_id FROM tovar_rent_items_arch
                    ) i2
                    JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = i2.cat_id
                    JOIN razdel_subrazdel rs   ON rs.id_sub_razdel    = sc.id_sub_razdel
                    WHERE rs.id_razdel = ?
                ) irz ON irz.item_inv_n = d.item_inv_n
                WHERE d.cr_time BETWEEN ? AND ? AND i.model_id IS NOT NULL
            ", [$razdelId, $fromTs, $toTs])->n;
        };

        $chosenSlug = null;
        $expected   = 0;
        foreach ($slugToUrlName as $apiSlug => $urlName) {
            $razdel = \Illuminate\Support\Facades\DB::selectOne(
                "SELECT id_razdel FROM razdel WHERE url_razdel_name = ?",
                [$urlName]
            );
            if (!$razdel) {
                continue;
            }
            $n = $countForRazdel((int) $razdel->id_razdel);
            if ($n > 0) {
                $chosenSlug = $apiSlug;
                $expected   = $n;
                break;
            }
        }

        if ($chosenSlug === null) {
            $this->markTestSkipped(
                "ни одна категория из RangeRequest::CATEGORIES не имеет сделок в {$from}..{$to} на этом дампе — " .
                'подберите другой диапазон дат вручную'
            );
        }

        $rows = $this->mcp('operations/deals-by-model', [
            'from' => $from, 'to' => $to, 'granularity' => 'month', 'category' => $chosenSlug,
        ])->json('data');

        $apiTotal = array_sum(array_map(static fn ($r) => $r['deals_started'], $rows));

        $this->assertSame(
            $expected,
            $apiTotal,
            "category={$chosenSlug}: сумма deals_started по всем строкам ответа должна совпадать " .
            'с независимым COUNT(DISTINCT deal_id) по тому же разделу — расхождение указывает на ' .
            'many-to-many раздувание через subrazdel_category × razdel_subrazdel'
        );
    }

    // ─── Пробел 2: include_carnival=false ──────────────────────────────────

    /**
     * Ветка исключения карнавальных товаров (`ti.cat_id NOT IN (...)`) не
     * исполнялась ни одним старым тестом. Проверяем не просто "меньше или
     * равно", а точное равенство разницы числу карнавальных сделок за тот же
     * период, посчитанному независимым запросом (JOIN на tovar_rent_cat.cat_type=1,
     * без переиспользования BaseController::carnivalCatIds()).
     */
    public function test_deals_by_model_include_carnival_false_excludes_exactly_carnival_deals(): void
    {
        $from = '2024-12-01';
        $to   = '2024-12-31';

        $withCarnival = $this->mcp('operations/deals-by-model', [
            'from' => $from, 'to' => $to, 'granularity' => 'month', 'include_carnival' => 1,
        ])->json('data');
        $withoutCarnival = $this->mcp('operations/deals-by-model', [
            'from' => $from, 'to' => $to, 'granularity' => 'month', 'include_carnival' => 0,
        ])->json('data');

        $totalWith    = array_sum(array_map(static fn ($r) => $r['deals_started'], $withCarnival));
        $totalWithout = array_sum(array_map(static fn ($r) => $r['deals_started'], $withoutCarnival));

        $this->assertLessThanOrEqual(
            $totalWith,
            $totalWithout,
            'include_carnival=0 не может УВЕЛИЧИТЬ суммарное число сделок относительно include_carnival=1'
        );

        $fromTs = strtotime($from . ' 00:00:00');
        $toTs   = strtotime($to . ' 23:59:59');

        // Независимый подсчёт сделок ИМЕННО по карнавальным категориям
        // (tovar_rent_cat.cat_type = 1), переписанный заново.
        $carnivalDeals = (int) \Illuminate\Support\Facades\DB::selectOne("
            SELECT COUNT(DISTINCT d.deal_id) AS n
            FROM (
                SELECT deal_id, item_inv_n, cr_time FROM rent_deals_act
                UNION ALL
                SELECT deal_id, item_inv_n, cr_time FROM rent_deals_arch
            ) d
            JOIN (
                SELECT item_inv_n, cat_id, model_id FROM tovar_rent_items
                UNION ALL
                SELECT item_inv_n, cat_id, model_id FROM tovar_rent_items_arch
            ) i ON i.item_inv_n = d.item_inv_n
            JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = i.cat_id
            WHERE d.cr_time BETWEEN ? AND ? AND i.model_id IS NOT NULL AND c.cat_type = 1
        ", [$fromTs, $toTs])->n;

        $this->assertGreaterThan(
            0,
            $carnivalDeals,
            "в {$from}..{$to} должны быть карнавальные сделки на этом дампе — иначе тест ничего не проверяет"
        );
        $this->assertSame(
            $carnivalDeals,
            $totalWith - $totalWithout,
            'разница deals_started между include_carnival=1 и include_carnival=0 должна равняться ' .
            'числу сделок именно по карнавальным категориям'
        );
    }

    // ─── Пробел 3: units_at_period_end / deals_per_unit ────────────────────

    /**
     * До этого теста units_at_period_end и deals_per_unit проверялись только
     * на присутствие ключей и на null в режиме превышения порога периодов —
     * сами числа не сверялись ни с чем, хотя ради этого знаменателя эндпоинт
     * и делался.
     *
     * Диапазон 2021-10 выбран НЕ произвольно: на этом дампе в нём одновременно
     * есть модели с units_at_period_end > 0 и модели с units_at_period_end = 0
     * (товар выбыл из инвентаря до конца периода), так что обе ветки формулы
     * deals_per_unit проверяются на реальных данных, а не только на бумаге.
     */
    public function test_deals_by_model_deals_per_unit_matches_formula_and_units_match_independent_inventory_count(): void
    {
        $from = '2021-10-01';
        $to   = '2021-10-31';

        $rows = $this->mcp('operations/deals-by-model', [
            'from' => $from, 'to' => $to, 'granularity' => 'month',
        ])->json('data');
        $this->assertNotEmpty($rows);

        $checkedPositive = 0;
        $checkedZeroOrNull = 0;
        foreach ($rows as $row) {
            $units = $row['units_at_period_end'];
            $deals = $row['deals_started'];

            if ($units !== null && $units > 0) {
                $this->assertEqualsWithDelta(
                    round($deals / $units, 2),
                    $row['deals_per_unit'],
                    0.001,
                    "model_id={$row['model_id']}: deals_per_unit должен быть round(deals_started/units_at_period_end, 2)"
                );
                $checkedPositive++;
            } else {
                $this->assertNull(
                    $row['deals_per_unit'],
                    "model_id={$row['model_id']}: при units_at_period_end=" . var_export($units, true) .
                    ' deals_per_unit обязан быть null'
                );
                $checkedZeroOrNull++;
            }
        }

        $this->assertGreaterThan(0, $checkedPositive, 'должна быть хотя бы одна строка с units_at_period_end > 0');
        $this->assertGreaterThan(0, $checkedZeroOrNull, 'на диапазоне 2021-10 должна быть хотя бы одна строка с units_at_period_end = 0 (иначе ветка null не проверена)');

        // Независимая проверка самого знаменателя (не только формулы деления)
        // для 1-2 моделей — не всего списка, чтобы тест не превратился в
        // копию продакшн-запроса. Формула из CLAUDE.md / BaseController::modelInventoryAtDate():
        // tovar_rent_items(buy_date<=X) + tovar_rent_items_arch(buy_date<=X AND arch_time>=X).
        //
        // Специально берём ПО ОДНОЙ модели из каждой корзины (units>0 и units=0),
        // а не просто первые две строки: у моделей с units_at_period_end=0 остаток
        // чаще всего менялся В ТЕЧЕНИЕ периода (товар выбыл из инвентаря до конца
        // месяца) — только на них ловится подмена "конец периода" на "начало
        // периода" в periodEndTimestamp(). Две "стабильные" модели без движения
        // остатка внутри периода дали бы одинаковый результат в обеих точках
        // отсчёта и не заметили бы такую мутацию.
        $toTs = strtotime($to . ' 23:59:59');
        $positiveSample = null;
        $zeroSample     = null;
        foreach ($rows as $r) {
            if ($r['units_at_period_end'] === null) {
                continue;
            }
            if ($r['units_at_period_end'] > 0 && $positiveSample === null) {
                $positiveSample = $r;
            } elseif ($r['units_at_period_end'] === 0 && $zeroSample === null) {
                $zeroSample = $r;
            }
            if ($positiveSample !== null && $zeroSample !== null) {
                break;
            }
        }
        $sample = array_values(array_filter([$positiveSample, $zeroSample]));
        $this->assertNotEmpty($sample, 'нужна хотя бы одна строка с посчитанным знаменателем для независимой сверки');

        foreach ($sample as $row) {
            $modelId = $row['model_id'];

            $active = (int) \Illuminate\Support\Facades\DB::selectOne(
                "SELECT COUNT(*) AS n FROM tovar_rent_items WHERE model_id = ? AND buy_date <= ?",
                [$modelId, $toTs]
            )->n;
            $archived = (int) \Illuminate\Support\Facades\DB::selectOne(
                "SELECT COUNT(*) AS n FROM tovar_rent_items_arch WHERE model_id = ? AND buy_date <= ? AND arch_time >= ?",
                [$modelId, $toTs, $toTs]
            )->n;

            $this->assertSame(
                $active + $archived,
                $row['units_at_period_end'],
                "model_id={$modelId}: units_at_period_end должен совпадать с независимым подсчётом остатков " .
                'на КОНЕЦ периода (tovar_rent_items + tovar_rent_items_arch по методике CLAUDE.md)'
            );
        }
    }
}
