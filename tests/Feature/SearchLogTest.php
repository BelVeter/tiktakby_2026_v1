<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SearchLogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('search_log'));

        foreach (['id', 'created_at', 'ip', 'query', 'results_count', 'user_agent'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('search_log', $column),
                "колонка {$column} отсутствует"
            );
        }
    }

    public function test_ip_and_created_at_composite_index_exists(): void
    {
        $row = DB::selectOne("
            SELECT COUNT(*) AS n
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'search_log'
              AND INDEX_NAME = 'search_log_ip_created_at_index'
        ");
        $this->assertGreaterThan(0, (int) $row->n);
    }

    public function test_nonempty_search_is_logged(): void
    {
        $query = 'коляска-' . uniqid();

        $this->get('/ru/search?search=' . urlencode($query));

        $this->assertDatabaseHas('search_log', ['query' => $query]);
    }

    public function test_empty_search_is_not_logged(): void
    {
        $before = DB::table('search_log')->count();

        $this->get('/ru/search?search=');

        $this->assertSame($before, DB::table('search_log')->count());
    }

    public function test_bot_user_agent_is_not_logged(): void
    {
        $query = 'самокат-' . uniqid();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
        ])->get('/ru/search?search=' . urlencode($query));

        $this->assertDatabaseMissing('search_log', ['query' => $query]);
    }

    public function test_results_count_is_recorded(): void
    {
        $query = 'zzzzzzzzzz-no-such-product-' . uniqid();

        $this->get('/ru/search?search=' . urlencode($query));

        $this->assertDatabaseHas('search_log', [
            'query' => $query,
            'results_count' => 0,
        ]);
    }

    public function test_long_query_is_truncated_and_does_not_fail(): void
    {
        $query = str_repeat('a', 400);

        $response = $this->get('/ru/search?search=' . urlencode($query));

        $response->assertStatus(200);
        $this->assertDatabaseHas('search_log', ['query' => substr($query, 0, 255)]);
    }

    public function test_producer_filter_does_not_write_to_search_log(): void
    {
        $before = DB::table('search_log')->count();

        $this->get('/ru/producer?producer=' . urlencode('TestBrand-' . uniqid()));

        $this->assertSame($before, DB::table('search_log')->count());
    }
}
