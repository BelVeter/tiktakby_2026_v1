<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds has_audio flag to a1_call_recordings.
 *
 * has_audio = 1  — audio file is present on disk (default for live recordings).
 * has_audio = 0  — no audio: either a historical import (analysis only) or
 *                  a live recording whose file has been deleted to free disk space.
 *
 * This allows GET /calls/recordings to return file_url=null and
 * GET /calls/pending-analysis to skip audio-less records safely.
 */
class AddHasAudioToA1CallRecordings extends Migration
{
    public function up()
    {
        Schema::table('a1_call_recordings', function (Blueprint $table) {
            // After file_size; default 1 keeps existing live recordings correct.
            $table->tinyInteger('has_audio')->default(1)->after('file_size');
        });
    }

    public function down()
    {
        Schema::table('a1_call_recordings', function (Blueprint $table) {
            $table->dropColumn('has_audio');
        });
    }
}
