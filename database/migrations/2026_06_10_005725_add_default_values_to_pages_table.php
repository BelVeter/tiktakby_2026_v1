<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultValuesToPagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE pages 
            ALTER title SET DEFAULT '',
            ALTER h1 SET DEFAULT '',
            ALTER h1_pic_url SET DEFAULT '',
            ALTER block_2_title SET DEFAULT '',
            ALTER h1_long_text SET DEFAULT '',
            ALTER code_block_2 SET DEFAULT ''
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE pages 
            ALTER title DROP DEFAULT,
            ALTER h1 DROP DEFAULT,
            ALTER h1_pic_url DROP DEFAULT,
            ALTER block_2_title DROP DEFAULT,
            ALTER h1_long_text DROP DEFAULT,
            ALTER code_block_2 DROP DEFAULT
        ");
    }
}
