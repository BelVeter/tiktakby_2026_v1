<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Справочный расчёт массового продления по клиенту (bb/dogovor_new.php).
 *
 * Хранит последний расчёт: одна строка на позицию. При новом расчёте строки
 * клиента заменяются целиком, поэтому истории здесь нет — только «что мы
 * посчитали клиенту в прошлый раз», чтобы это видел любой сотрудник.
 * Суммы справочные: реальные деньги пишутся в rent_sub_deals_act при оформлении.
 */
class CreateRentExtCalcTable extends Migration
{
    public function up()
    {
        Schema::create('rent_ext_calc', function (Blueprint $table) {
            $table->increments('calc_id');
            $table->integer('client_id');
            $table->integer('deal_id');
            $table->integer('item_inv_n');
            $table->integer('ext_from');  // unix: конец оплаченного периода (rent_deals_act.return_date)
            $table->integer('ext_to');    // unix: дата, по которую продлеваем
            $table->integer('ext_days');
            $table->decimal('amount', 11, 2);
            $table->integer('calc_time'); // unix
            $table->integer('user_id');

            $table->index('client_id', 'idx_rec_client');
            $table->index('deal_id', 'idx_rec_deal');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rent_ext_calc');
    }
}
