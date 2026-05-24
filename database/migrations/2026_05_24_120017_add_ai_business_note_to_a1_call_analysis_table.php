<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiBusinessNoteToA1CallAnalysisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('a1_call_analysis', function (Blueprint $table) {
            $table->text('ai_business_note')->nullable()->default(null)->after('consultant_sentiment');
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
            $table->dropColumn('ai_business_note');
        });
    }
}
