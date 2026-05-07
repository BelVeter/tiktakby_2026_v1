<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMcpApiLogTable extends Migration
{
    public function up()
    {
        Schema::create('mcp_api_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('created_at')->index();
            $table->string('ip', 45);
            $table->string('method', 10);
            $table->string('endpoint', 255);
            $table->json('query_params')->nullable();
            $table->smallInteger('status_code')->unsigned()->nullable();
            $table->integer('response_ms')->unsigned()->nullable();
            $table->string('user_agent', 255)->nullable();
        });

        // Индекс для автоматической очистки старых записей (>90 дней)
        Schema::table('mcp_api_log', function (Blueprint $table) {
            $table->index(['created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('mcp_api_log');
    }
}
