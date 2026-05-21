<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds indexes to legacy bb/-managed tables to speed up MCP Analytics API
 * aggregations (date filters, GROUP BY, JOINs).
 *
 * Idempotent: each index is created only if missing — safe to re-run on prod
 * where some indexes may have been added manually.
 */
class AddMcpAnalyticsIndexes extends Migration
{
    /**
     * @var array<int, array{table: string, name: string, columns: string}>
     */
    private array $indexes = [
        // Hot date / FK columns on rent_deals_arch (≈120k rows).
        ['table' => 'rent_deals_arch',     'name' => 'idx_mcp_cr_time',          'columns' => '(cr_time)'],
        ['table' => 'rent_deals_arch',     'name' => 'idx_mcp_client_id',        'columns' => '(client_id)'],
        ['table' => 'rent_deals_arch',     'name' => 'idx_mcp_first_rent_place', 'columns' => '(first_rent_place)'],
        ['table' => 'rent_deals_arch',     'name' => 'idx_mcp_item_inv_n',       'columns' => '(item_inv_n)'],

        // rent_sub_deals_arch (≈500k rows). deal_id is already indexed.
        ['table' => 'rent_sub_deals_arch', 'name' => 'idx_mcp_cr_time',  'columns' => '(cr_time)'],
        ['table' => 'rent_sub_deals_arch', 'name' => 'idx_mcp_acc_date', 'columns' => '(acc_date)'],

        // doh_rash (≈32k rows) — only PRIMARY exists today.
        ['table' => 'doh_rash', 'name' => 'idx_mcp_acc_date',   'columns' => '(acc_date)'],
        ['table' => 'doh_rash', 'name' => 'idx_mcp_type1_type2', 'columns' => '(type1, type2)'],
        ['table' => 'doh_rash', 'name' => 'idx_mcp_channel',    'columns' => '(channel)'],

        // clients (≈51k rows).
        ['table' => 'clients', 'name' => 'idx_mcp_cr_time', 'columns' => '(cr_time)'],

        // karn_brons (active) and karn_brons_arch.
        ['table' => 'karn_brons',      'name' => 'idx_mcp_cr_time',  'columns' => '(cr_time)'],
        ['table' => 'karn_brons',      'name' => 'idx_mcp_cl_id',    'columns' => '(cl_id)'],
        ['table' => 'karn_brons',      'name' => 'idx_mcp_vidacha',  'columns' => '(vidacha)'],
        ['table' => 'karn_brons',      'name' => 'idx_mcp_vozvrat',  'columns' => '(vozvrat)'],
        ['table' => 'karn_brons_arch', 'name' => 'idx_mcp_cr_time',  'columns' => '(cr_time)'],
        ['table' => 'karn_brons_arch', 'name' => 'idx_mcp_cl_id',    'columns' => '(cl_id)'],

        // rent_orders_arch / rent_orders (≈190k arch). composite type+order_date already exists.
        ['table' => 'rent_orders_arch', 'name' => 'idx_mcp_cr_time',   'columns' => '(cr_time)'],
        ['table' => 'rent_orders_arch', 'name' => 'idx_mcp_client_id', 'columns' => '(client_id)'],
        ['table' => 'rent_orders',      'name' => 'idx_mcp_cr_time',   'columns' => '(cr_time)'],
        ['table' => 'rent_orders',      'name' => 'idx_mcp_client_id', 'columns' => '(client_id)'],

        // rent_deals_act (small but used in current controller).
        ['table' => 'rent_deals_act', 'name' => 'idx_mcp_cr_time',    'columns' => '(cr_time)'],
        ['table' => 'rent_deals_act', 'name' => 'idx_mcp_client_id',  'columns' => '(client_id)'],
        ['table' => 'rent_deals_act', 'name' => 'idx_mcp_item_inv_n', 'columns' => '(item_inv_n)'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $idx) {
            if (!$this->tableExists($idx['table'])) {
                continue;
            }
            if ($this->indexExists($idx['table'], $idx['name'])) {
                continue;
            }
            DB::statement("CREATE INDEX `{$idx['name']}` ON `{$idx['table']}` {$idx['columns']}");
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes) as $idx) {
            if (!$this->tableExists($idx['table'])) {
                continue;
            }
            if (!$this->indexExists($idx['table'], $idx['name'])) {
                continue;
            }
            DB::statement("DROP INDEX `{$idx['name']}` ON `{$idx['table']}`");
        }
    }

    private function tableExists(string $table): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
            [$table]
        );
        return !empty($rows);
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );
        return !empty($rows);
    }
}
