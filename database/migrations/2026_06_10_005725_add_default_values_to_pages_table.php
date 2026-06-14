<?php

use Illuminate\Database\Migrations\Migration;

class AddDefaultValuesToPagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // MariaDB 10.6 запрещает литеральный DEFAULT на TEXT-колонках, поэтому
        // h1_long_text и code_block_2 здесь НАМЕРЕННО пропущены — иначе ALTER
        // падает с "BLOB, TEXT ... can't have a default value" и миграция
        // зацикливает деплой. Вставку этих TEXT-колонок и так гарантирует код
        // (PagesListingController при создании страницы + MainPage::create),
        // DB-дефолты тут — лишь подстраховка для NOT NULL VARCHAR-колонок.
        DB::statement("ALTER TABLE pages
            ALTER title SET DEFAULT '',
            ALTER h1 SET DEFAULT '',
            ALTER h1_pic_url SET DEFAULT '',
            ALTER block_2_title SET DEFAULT ''
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
            ALTER block_2_title DROP DEFAULT
        ");
    }
}
