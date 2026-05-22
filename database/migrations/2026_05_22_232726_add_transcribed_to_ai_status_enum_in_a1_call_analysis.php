<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddTranscribedToAiStatusEnumInA1CallAnalysis extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Idempotent safety-net:
        // We use a raw SQL ALTER TABLE here because modifying ENUMs using Laravel's
        // Schema builder often requires doctrine/dbal, and we want to ensure
        // 'transcribed' is added safely even if another developer/AI previously
        // modified the original 000002 migration locally.
        DB::statement("ALTER TABLE a1_call_analysis MODIFY COLUMN ai_status ENUM('pending', 'processing', 'transcribed', 'done', 'error') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverting this might cause data loss if there are 'transcribed' records.
        // We'll leave it as is or revert to the previous ENUM without 'transcribed'.
        // DB::statement("ALTER TABLE a1_call_analysis MODIFY COLUMN ai_status ENUM('pending', 'processing', 'done', 'error') NOT NULL DEFAULT 'pending'");
    }
}
