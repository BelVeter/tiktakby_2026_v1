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
}
