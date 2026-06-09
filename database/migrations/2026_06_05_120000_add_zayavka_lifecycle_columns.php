<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddZayavkaLifecycleColumns extends Migration
{
    public function up(): void
    {
        foreach (['rent_orders', 'rent_orders_arch'] as $t) {
            Schema::table($t, function ($table) {
                $table->string('z_status', 20)->default('new')->after('status');
                $table->string('z_reject_reason', 40)->nullable()->after('z_status');
                $table->date('planned_date')->nullable()->after('z_reject_reason');
            });
        }

        Schema::table('zvonki', function ($table) {
            $table->integer('order_id')->nullable()->after('model_id');
            $table->index('order_id', 'idx_zvonki_order_id');
        });

        Schema::table('rent_orders', function ($table) {
            $table->index(['model_id', 'phone'], 'idx_ro_model_phone');
            $table->index(['type2', 'z_status'], 'idx_ro_type2_zstatus');
        });

        // Backfill: новизна определялась пустотой info2
        DB::statement("UPDATE rent_orders SET z_status='new'
                       WHERE type2='zayavka' AND (info2 IS NULL OR info2='')");
        DB::statement("UPDATE rent_orders SET z_status='in_work'
                       WHERE type2='zayavka' AND info2 IS NOT NULL AND info2<>''");
    }

    public function down(): void
    {
        Schema::table('rent_orders', function ($table) {
            $table->dropIndex('idx_ro_model_phone');
            $table->dropIndex('idx_ro_type2_zstatus');
        });
        Schema::table('zvonki', function ($table) {
            $table->dropIndex('idx_zvonki_order_id');
            $table->dropColumn('order_id');
        });
        foreach (['rent_orders', 'rent_orders_arch'] as $t) {
            Schema::table($t, function ($table) {
                $table->dropColumn(['z_status', 'z_reject_reason', 'planned_date']);
            });
        }
    }
}
