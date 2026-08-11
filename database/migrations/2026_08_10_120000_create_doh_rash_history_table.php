<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change journal for doh_rash rows written through the MCP API.
 *
 * Only `update` and `delete` are recorded: an insert is already attributable
 * from the doh_rash row itself (cr_who_id = the api_system user, plus cr_time),
 * so journalling it would duplicate data that already exists.
 *
 * Snapshots are whole-row rather than per-field so a delete can be replayed
 * from a single journal row. mcp_api_log (the mcp.audit middleware) cannot
 * serve this purpose — it stores query parameters only, never request bodies.
 *
 * Scope is API-originated changes only; the legacy admin keeps its own
 * file-based deletion log at bb/logs/YYYY-MM-DD_dohrash.
 */
class CreateDohRashHistoryTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doh_rash_history')) {
            return;
        }

        Schema::create('doh_rash_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('dr_id');
            $table->enum('action', ['update', 'delete']);
            $table->text('before_json');
            $table->text('after_json')->nullable();
            $table->integer('actor_user_id')->nullable();
            $table->string('source', 32)->default('mcp_api');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('dr_id', 'idx_drh_dr_id');
            $table->index('created_at', 'idx_drh_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doh_rash_history');
    }
}
