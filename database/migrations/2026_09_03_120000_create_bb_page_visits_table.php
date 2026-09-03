<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Логирует каждый заход сотрудника на страницу bb/ — пишется
 * bb/classes/PageVisitTracker.php (auto_prepend_file), читается
 * bb/page_track.php (отчёт) и app/Http/Controllers/Mcp/StaffController.php
 * (staff/page-visits/* для ИИ-агента).
 *
 * Без IP и query-строки — не требуется для «кто/что/как часто», хранить
 * лишнее (потенциально с ПДн клиентов в URL) незачем.
 */
class CreateBbPageVisitsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bb_page_visits')) {
            return;
        }

        Schema::create('bb_page_visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('page', 100);
            $table->timestamp('visited_at')->useCurrent();

            $table->index(['user_id', 'page', 'visited_at'], 'idx_bpv_user_page_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_page_visits');
    }
}
