<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Отметка «расчёт разнесён»: после автоматической разноски оплаты строки расчёта
 * остаются на экране справочно, но повторно разнести их уже нельзя.
 */
class AddAppliedToRentExtCalc extends Migration
{
    public function up()
    {
        Schema::table('rent_ext_calc', function (Blueprint $table) {
            $table->integer('applied_time')->default(0);     // unix, 0 = не разнесён
            $table->integer('applied_user_id')->default(0);
        });
    }

    public function down()
    {
        Schema::table('rent_ext_calc', function (Blueprint $table) {
            $table->dropColumn(['applied_time', 'applied_user_id']);
        });
    }
}
