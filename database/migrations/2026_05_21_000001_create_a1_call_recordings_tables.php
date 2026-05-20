<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateA1CallRecordingsTables extends Migration
{
    public function up()
    {
        Schema::create('a1_call_recordings', function (Blueprint $table) {
            $table->id();
            $table->string('record_name', 255)->unique();
            $table->string('uuid', 100)->unique();
            $table->dateTime('call_date')->index();
            $table->string('caller_part', 30)->default('');
            $table->string('callee_part', 30)->default('');
            $table->unsignedSmallInteger('call_duration')->default(0);
            $table->string('file_path', 500);
            $table->unsignedInteger('file_size')->default(0);
            $table->dateTime('downloaded_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('a1_recordings_fetch_log', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fetched_at')->index();
            $table->enum('status', ['success', 'error']);
            $table->unsignedInteger('period_start')->default(0);
            $table->unsignedInteger('period_end')->default(0);
            $table->unsignedSmallInteger('records_found')->default(0);
            $table->unsignedSmallInteger('records_new')->default(0);
            $table->unsignedSmallInteger('files_downloaded')->default(0);
            $table->unsignedSmallInteger('files_deleted')->default(0);
            $table->unsignedInteger('bytes_downloaded')->default(0);
            $table->unsignedInteger('bytes_freed')->default(0);
            $table->text('error_message')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('a1_call_recordings');
        Schema::dropIfExists('a1_recordings_fetch_log');
    }
}
