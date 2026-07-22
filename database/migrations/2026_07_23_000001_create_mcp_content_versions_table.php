<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change history for SEO content written through the MCP API.
 *
 * McpAuditLogMiddleware only records query parameters, so PATCH bodies (the
 * actual content) were nowhere on the server. One row per changed field gives
 * both a diff trail and a rollback source.
 */
class CreateMcpContentVersionsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_content_versions')) {
            return;
        }

        Schema::create('mcp_content_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('page_type', 16);          // listing | product
            $table->string('page_slug', 191);
            $table->string('field', 64);              // API field name, not DB column
            $table->mediumText('old_value')->nullable();
            $table->mediumText('new_value')->nullable();
            $table->string('source', 32)->default('mcp_api');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['page_type', 'page_slug', 'created_at'], 'idx_cv_page');
            $table->index('created_at', 'idx_cv_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_content_versions');
    }
}
