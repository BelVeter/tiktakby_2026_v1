<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsActiveToItemsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('rash_items', 'is_active')) {
            Schema::table('rash_items', function (Blueprint $table) {
                $table->tinyInteger('is_active')->default(1)->after('bank_yn');
            });
        }

        if (!Schema::hasColumn('doh_items', 'is_active')) {
            Schema::table('doh_items', function (Blueprint $table) {
                $table->tinyInteger('is_active')->default(1)->after('bank_yn');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('rash_items', 'is_active')) {
            Schema::table('rash_items', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }

        if (Schema::hasColumn('doh_items', 'is_active')) {
            Schema::table('doh_items', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
}
