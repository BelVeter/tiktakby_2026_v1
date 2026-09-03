<?php

namespace Tests\Feature\Mcp;

use Illuminate\Support\Facades\DB;

class StaffTest extends McpTestCase
{
    private const TEST_USER_ID = 999001;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
        parent::tearDown();
    }

    private function cleanupFixtures(): void
    {
        DB::table('bb_page_visits')->where('user_id', self::TEST_USER_ID)->delete();
    }

    private function seedVisit(string $page, string $visitedAt): void
    {
        DB::table('bb_page_visits')->insert([
            'user_id'    => self::TEST_USER_ID,
            'page'       => $page,
            'visited_at' => $visitedAt,
        ]);
    }

    public function test_by_user_envelope_and_aggregation(): void
    {
        $this->seedVisit('deals.php', '2026-08-01 10:00:00');
        $this->seedVisit('deals.php', '2026-08-02 11:00:00');
        $this->seedVisit('tovar_new.php', '2026-08-03 12:00:00');

        $r = $this->mcp('staff/page-visits/by-user', ['from' => '2026-08-01', 'to' => '2026-08-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [[
            'user_id', 'user_name', 'visits', 'distinct_pages', 'last_visit_at',
        ]]]);

        $row = collect($r->json('data'))->firstWhere('user_id', self::TEST_USER_ID);
        $this->assertNotNull($row, 'seeded test user must appear in by-user response');
        $this->assertSame(3, $row['visits']);
        $this->assertSame(2, $row['distinct_pages']);
        $this->assertSame('2026-08-03 12:00:00', $row['last_visit_at']);
    }

    public function test_by_user_page_filter_narrows_results(): void
    {
        $this->seedVisit('deals.php', '2026-08-01 10:00:00');
        $this->seedVisit('tovar_new.php', '2026-08-01 11:00:00');

        $r = $this->mcp('staff/page-visits/by-user', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'page' => 'deals.php',
        ]);
        $row = collect($r->json('data'))->firstWhere('user_id', self::TEST_USER_ID);
        $this->assertSame(1, $row['visits']);
    }

    public function test_by_page_includes_zero_visit_pages(): void
    {
        $r = $this->mcp('staff/page-visits/by-page', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'user_id' => self::TEST_USER_ID,
        ]);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [[
            'page', 'visits', 'distinct_users', 'last_visit_at',
        ]]]);

        $data = $r->json('data');
        $this->assertGreaterThan(50, count($data), 'catalog should list most real bb/ pages');

        $dealsRow = collect($data)->firstWhere('page', 'deals.php');
        $this->assertNotNull($dealsRow);
        $this->assertSame(0, $dealsRow['visits'], 'no visits from this test user in this period');
    }

    public function test_by_page_excludes_technical_files(): void
    {
        $r = $this->mcp('staff/page-visits/by-page', ['from' => '2026-08-01', 'to' => '2026-08-31']);
        $pages = array_column($r->json('data'), 'page');
        $this->assertNotContains('ajax_client_check.php', $pages);
        $this->assertNotContains('bb_nav_badge.php', $pages);
        $this->assertNotContains('Db.php', $pages);
        $this->assertNotContains('Base.php', $pages);
        $this->assertContains('deals.php', $pages);
    }

    public function test_by_page_reflects_seeded_visits_and_sorts_ascending(): void
    {
        $this->seedVisit('deals.php', '2026-08-05 09:00:00');

        $r = $this->mcp('staff/page-visits/by-page', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'user_id' => self::TEST_USER_ID,
        ]);
        $data = $r->json('data');

        $visits = array_column($data, 'visits');
        $sorted = $visits;
        sort($sorted);
        $this->assertSame($sorted, $visits, 'by-page must sort ascending by visits');

        $dealsRow = collect($data)->firstWhere('page', 'deals.php');
        $this->assertSame(1, $dealsRow['visits']);
        $this->assertSame(1, $dealsRow['distinct_users']);
    }

    public function test_staff_endpoints_require_token(): void
    {
        $this->assertRequiresToken('staff/page-visits/by-user');
        $this->assertRequiresToken('staff/page-visits/by-page');
    }
}
