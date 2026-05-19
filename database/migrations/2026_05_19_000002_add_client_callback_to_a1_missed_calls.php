<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddClientCallbackToA1MissedCalls extends Migration
{
    public function up()
    {
        // Expand enum to add 'client_called_back'
        DB::statement("ALTER TABLE a1_missed_calls
            MODIFY COLUMN action_type
            ENUM('none','called_back','false_call','client_called_back')
            NOT NULL DEFAULT 'none'");

        Schema::table('a1_missed_calls', function ($table) {
            $table->unsignedSmallInteger('callback_minutes')->nullable()->after('action_type');
        });
    }

    public function down()
    {
        Schema::table('a1_missed_calls', function ($table) {
            $table->dropColumn('callback_minutes');
        });

        DB::statement("ALTER TABLE a1_missed_calls
            MODIFY COLUMN action_type
            ENUM('none','called_back','false_call')
            NOT NULL DEFAULT 'none'");
    }
}
