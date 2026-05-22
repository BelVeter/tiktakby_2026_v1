<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateA1CdrTables extends Migration
{
    public function up()
    {
        Schema::create('a1_cdr', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 100)->unique();
            $table->dateTime('call_date')->index();
            $table->enum('call_type', ['incoming', 'outgoing', 'missed'])->index();
            $table->string('caller_number', 30)->default('');
            $table->string('callee_number', 30)->default('');
            $table->unsignedSmallInteger('call_duration')->default(0);
            $table->string('recording_uuid', 100)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('a1_cdr_fetch_log', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fetched_at')->index();
            $table->enum('status', ['success', 'error']);
            $table->unsignedInteger('period_start')->default(0);
            $table->unsignedInteger('period_end')->default(0);
            $table->unsignedSmallInteger('records_found')->default(0);
            $table->unsignedSmallInteger('records_new')->default(0);
            $table->text('error_message')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('a1_cdr');
        Schema::dropIfExists('a1_cdr_fetch_log');
    }
}
