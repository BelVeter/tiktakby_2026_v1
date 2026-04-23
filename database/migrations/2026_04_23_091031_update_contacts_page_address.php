<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateContactsPageAddress extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('pages')
            ->where('url_key', 'contacts')
            ->where('level_code', 'main')
            ->update([
                'code_block_1' => DB::raw("REPLACE(code_block_1, 'ул. Ложинская 5, оф. 194', 'ул. Литературная 22 оф 149')")
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('pages')
            ->where('url_key', 'contacts')
            ->where('level_code', 'main')
            ->update([
                'code_block_1' => DB::raw("REPLACE(code_block_1, 'ул. Литературная 22 оф 149', 'ул. Ложинская 5, оф. 194')")
            ]);
    }
}
