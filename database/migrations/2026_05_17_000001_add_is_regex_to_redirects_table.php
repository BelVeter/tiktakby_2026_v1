<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsRegexToRedirectsTable extends Migration
{
    public function up()
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->boolean('is_regex')->default(false)->after('status_code');
        });
    }

    public function down()
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropColumn('is_regex');
        });
    }
}
