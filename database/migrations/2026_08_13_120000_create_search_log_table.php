<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSearchLogTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_log')) {
            return;
        }

        Schema::create('search_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('created_at')->index();
            $table->string('ip', 45);
            $table->string('query', 255);
            $table->smallInteger('results_count')->unsigned()->nullable();
            $table->string('user_agent', 255)->nullable();
        });

        Schema::table('search_log', function (Blueprint $table) {
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_log');
    }
}
