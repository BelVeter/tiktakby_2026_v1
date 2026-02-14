<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DeleteOldPage1Redirect extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Delete the redirect for /old_page1 if it exists
        DB::table('redirects')->where('source_url', '/old_page1')->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Optionally restore it, though we might not know the target/comments easily without hardcoding
        // For now, we leave it empty as this is a cleanup operation.
    }
}
