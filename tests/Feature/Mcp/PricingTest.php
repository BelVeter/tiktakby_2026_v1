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
}
