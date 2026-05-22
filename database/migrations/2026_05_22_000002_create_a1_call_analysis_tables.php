<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateA1CallAnalysisTables extends Migration
{
    public function up()
    {
        Schema::create('a1_call_analysis', function (Blueprint $table) {
            $table->id();
            $table->string('recording_uuid', 100)->unique();
            $table->longText('transcript')->nullable();
            $table->text('ai_summary')->nullable();
            $table->string('ai_result', 100)->nullable();
            $table->text('ai_result_detail')->nullable();
            $table->enum('ai_status', ['pending', 'processing', 'done', 'error'])
                  ->default('pending')->index();
            $table->text('ai_error')->nullable();
            $table->dateTime('ai_processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('a1_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date')->unique();
            $table->text('summary_text')->nullable();
            $table->unsignedSmallInteger('total_calls')->default(0);
            $table->unsignedSmallInteger('incoming_calls')->default(0);
            $table->unsignedSmallInteger('outgoing_calls')->default(0);
            $table->unsignedSmallInteger('missed_calls')->default(0);
            $table->unsignedSmallInteger('calls_analyzed')->default(0);
            $table->json('key_themes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down()
    {
        Schema::dropIfExists('a1_call_analysis');
        Schema::dropIfExists('a1_daily_summaries');
    }
}
