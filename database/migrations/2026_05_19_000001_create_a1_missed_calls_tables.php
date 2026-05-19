<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateA1MissedCallsTables extends Migration
{
    public function up()
    {
        Schema::create('a1_missed_calls', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 100)->unique();
            $table->unsignedInteger('call_timestamp')->index();
            $table->string('caller_number', 30)->default('');
            $table->string('callee_number', 30)->default('');
            $table->string('call_status', 60)->default('');
            $table->unsignedSmallInteger('call_duration')->default(0);

            // CRM enrichment
            $table->unsignedInteger('crm_client_id')->nullable();
            $table->string('crm_client_fio', 255)->nullable();
            $table->text('crm_active_deals')->nullable(); // JSON
            $table->string('crm_last_return', 20)->nullable();
            $table->unsignedSmallInteger('crm_total_deals')->default(0);

            // Processing
            $table->enum('action_type', ['none', 'called_back', 'false_call'])->default('none')->index();
            $table->unsignedSmallInteger('action_user_id')->nullable();
            $table->string('action_user_name', 100)->nullable();
            $table->dateTime('action_at')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('a1_api_fetch_log', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fetched_at')->index();
            $table->enum('status', ['success', 'error']);
            $table->unsignedSmallInteger('total_records')->default(0);
            $table->unsignedSmallInteger('new_records')->default(0);
            $table->text('error_message')->nullable();
            $table->unsignedInteger('period_start')->default(0);
            $table->unsignedInteger('period_end')->default(0);
        });
    }

    public function down()
    {
        Schema::dropIfExists('a1_missed_calls');
        Schema::dropIfExists('a1_api_fetch_log');
    }
}
