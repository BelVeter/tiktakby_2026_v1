<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFaqToPagesAndModels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->text('faq')->nullable()->after('code_block_2');
        });
        Schema::table('rent_model_web', function (Blueprint $table) {
            $table->text('faq')->nullable()->after('main_descr');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('faq');
        });
        Schema::table('rent_model_web', function (Blueprint $table) {
            $table->dropColumn('faq');
        });
    }
}
