<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCallAnalysisFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('a1_call_analysis', function (Blueprint $table) {
            $table->boolean('is_internal')->default(0)->after('ai_processed_at');
            $table->string('missed_reason', 20)->nullable()->after('is_internal');
            $table->string('missed_outcome', 20)->nullable()->after('missed_reason');
        });

        Schema::table('a1_daily_summaries', function (Blueprint $table) {
            $table->integer('internal_calls')->default(0)->after('calls_analyzed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('a1_call_analysis', function (Blueprint $table) {
            $table->dropColumn(['is_internal', 'missed_reason', 'missed_outcome']);
        });

        Schema::table('a1_daily_summaries', function (Blueprint $table) {
            $table->dropColumn(['internal_calls']);
        });
    }
}
