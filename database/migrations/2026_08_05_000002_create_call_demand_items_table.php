<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCallDemandItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('call_demand_items', function (Blueprint $table) {
            $table->increments('id');
            // Must be varchar(100) to match a1_call_recordings.uuid
            $table->string('recording_uuid', 100);
            $table->date('call_date');
            $table->string('phrase', 255);
            $table->integer('cat_id')->nullable();
            $table->string('cat_name', 255)->nullable();
            $table->string('kind', 20); // demand, missed, mention
            $table->string('missed_reason', 20)->nullable(); // stock, assortment
            $table->string('missed_outcome', 20)->nullable(); // hard, soft
            $table->string('match_source', 20); // alias, fuzzy, model, manual, unmatched
            $table->timestamps();

            $table->index('recording_uuid', 'idx_cdi_uuid');
            $table->index('call_date', 'idx_cdi_date');
            $table->index('cat_id', 'idx_cdi_cat');

            $table->foreign('recording_uuid', 'fk_cdi_recording')
                  ->references('uuid')->on('a1_call_recordings')
                  ->onDelete('cascade');
            
            $table->foreign('cat_id', 'fk_cdi_category')
                  ->references('tovar_rent_cat_id')->on('tovar_rent_cat')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('call_demand_items');
    }
}
