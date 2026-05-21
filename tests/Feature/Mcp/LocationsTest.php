<?php

namespace Tests\Feature\Mcp;

class LocationsTest extends McpTestCase
{
    public function test_performance_envelope_and_columns(): void
    {
        $r = $this->mcp('locations/performance', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [[
            'period', 'office_id', 'office_name', 'office_type',
            'deals', 'issuance_events', 'unique_clients_proxy',
            'revenue_byn', 'avg_payment_byn',
        ]]]);
    }

    public function test_performance_2024_q4_lozhinskaya_lower_than_q3(): void
    {
        // D-OPEN-LOCATIONS: Lozhinskaya trail-off through 2025–2026; Q4 2024 < Q3 2024.
        $rows = $this->mcp('locations/performance', ['from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'quarter'])->json('data');
        $byPeriod = collect($rows)->groupBy('period');
        $q3 = collect($byPeriod['2024-Q3'])->firstWhere('office_id', 2);
        $q4 = collect($byPeriod['2024-Q4'])->firstWhere('office_id', 2);
        $this->assertNotNull($q3); $this->assertNotNull($q4);
        $this->assertLessThan($q3['deals'], $q4['deals'], 'Lozhinskaya Q4 2024 should be lower than Q3 2024');
    }

    public function test_performance_period_with_no_deals_is_empty(): void
    {
        $rows = $this->mcp('locations/performance', ['from' => '2037-01-01', 'to' => '2037-01-31'])->json('data');
        $this->assertSame([], $rows);
    }

    public function test_lifecycle_includes_pobediteley_closure(): void
    {
        $r = $this->mcp('locations/lifecycle');
        $this->assertEnvelope($r);
        $rows = $r->json('data');
        $byId = collect($rows)->keyBy('office_id');

        $this->assertSame(0, (int) $byId[3]['currently_active']);
        // last_activity_date is from sub_deals (cl_payment settlements on old deals)
        // and may extend years past the 2022-07 physical office closure.
        $this->assertNotNull($byId[3]['last_activity_date']);
        $this->assertGreaterThan(20000, (int) $byId[3]['total_deals']);
        $this->assertGreaterThan(600000, (float) $byId[3]['total_revenue_byn']);
    }

    public function test_lifecycle_lozhinskaya_last_activity_2026(): void
    {
        $rows = $this->mcp('locations/lifecycle')->json('data');
        $byId = collect($rows)->keyBy('office_id');
        // Lozhinskaya (id=2) trail-off through 2026.
        $this->assertStringStartsWith('2026-', $byId[2]['last_activity_date']);
    }

    public function test_lifecycle_includes_courier_rows(): void
    {
        $rows = $this->mcp('locations/lifecycle')->json('data');
        $types = array_unique(array_column($rows, 'office_type'));
        $this->assertContains('courier', $types);
    }

    public function test_lifecycle_does_not_require_period(): void
    {
        // Lifecycle is full history — should respond without from/to.
        $this->mcp('locations/lifecycle')->assertStatus(200);
    }

    public function test_locations_endpoints_require_token(): void
    {
        $this->assertRequiresToken('locations/performance');
        $this->assertRequiresToken('locations/lifecycle');
    }
}
