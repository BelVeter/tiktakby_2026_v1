<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotFoundLogTable extends Migration
{
    public function up()
    {
        Schema::create('not_found_log', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->string('referrer', 2048)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('not_found_log');
    }
}
