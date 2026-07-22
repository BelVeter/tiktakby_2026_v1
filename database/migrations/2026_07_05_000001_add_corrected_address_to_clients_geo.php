<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCorrectedAddressToClientsGeo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients_geo', function (Blueprint $table) {
            $table->string('corrected_address', 255)->nullable()->after('geo_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clients_geo', function (Blueprint $table) {
            $table->dropColumn('corrected_address');
        });
    }
}
