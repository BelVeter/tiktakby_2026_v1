<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSiteSettingsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_settings')) {
            return;
        }

        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('setting_key', 64)->primary();
            $table->string('setting_value', 255)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('updated_by', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
}
