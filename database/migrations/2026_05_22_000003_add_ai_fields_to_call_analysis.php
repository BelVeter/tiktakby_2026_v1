<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiFieldsToCallAnalysis extends Migration
{
    public function up()
    {
        Schema::table('a1_call_analysis', function (Blueprint $table) {
            $table->json('discussed_items')->nullable()->after('ai_result_detail');
            $table->string('missed_item', 255)->nullable()->after('discussed_items');
            $table->enum('client_sentiment', ['positive', 'neutral', 'negative'])->nullable()->after('missed_item');
            $table->enum('consultant_sentiment', ['positive', 'neutral', 'negative'])->nullable()->after('client_sentiment');
        });
    }

    public function down()
    {
        Schema::table('a1_call_analysis', function (Blueprint $table) {
            $table->dropColumn(['discussed_items', 'missed_item', 'client_sentiment', 'consultant_sentiment']);
        });
    }
}
