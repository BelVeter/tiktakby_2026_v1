<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddClientIdIndexesForDeduplication extends Migration
{
    public function up()
    {
        $indexes = [
            ['rent_deals_act',  'idx_client_id', 'client_id'],
            ['rent_deals_arch', 'idx_client_id', 'client_id'],
            ['deals',           'idx_client_id', 'client_id'],
            ['rent_orders',     'idx_client_id', 'client_id'],
            ['rent_orders_arch','idx_client_id', 'client_id'],
            ['karn_brons',      'idx_cl_id',     'cl_id'],
            ['karn_brons_arch', 'idx_cl_id',     'cl_id'],
        ];

        foreach ($indexes as [$table, $index, $column]) {
            $exists = DB::select("SELECT 1 FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                AND table_name = ? AND index_name = ? LIMIT 1", [$table, $index]);
            if (empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$column}`)");
            }
        }
    }

    public function down()
    {
        $indexes = [
            ['rent_deals_act',  'idx_client_id'],
            ['rent_deals_arch', 'idx_client_id'],
            ['deals',           'idx_client_id'],
            ['rent_orders',     'idx_client_id'],
            ['rent_orders_arch','idx_client_id'],
            ['karn_brons',      'idx_cl_id'],
            ['karn_brons_arch', 'idx_cl_id'],
        ];

        foreach ($indexes as [$table, $index]) {
            $exists = DB::select("SELECT 1 FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                AND table_name = ? AND index_name = ? LIMIT 1", [$table, $index]);
            if (!empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
            }
        }
    }
}
