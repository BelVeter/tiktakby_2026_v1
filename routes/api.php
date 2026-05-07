<?php

use App\Http\Controllers\McpAnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Стандартный маршрут Laravel (оставляем)
Route::middleware('auth:api')->get('/user', function (\Illuminate\Http\Request $request) {
    return $request->user();
});

// ──────────────────────────────────────────────────────────────────────────
// MCP Analytics API v1
// Middleware: mcp.token (Bearer auth) → mcp.geo (страна BY) → mcp.audit (лог)
// Throttle: 60 запросов в минуту
// ──────────────────────────────────────────────────────────────────────────
Route::prefix('mcp/v1')
    ->middleware(['mcp.token', 'mcp.geo', 'mcp.audit', 'throttle:60,1'])
    ->name('mcp.v1.')
    ->group(function () {

        // Статус API
        Route::get('health', [McpAnalyticsController::class, 'health'])
            ->name('health');

        // Инвентарь
        Route::get('inventory/free-tree', [McpAnalyticsController::class, 'getFreeTree'])
            ->name('inventory.free-tree');

        Route::get('inventory/profitability', [McpAnalyticsController::class, 'getItemProfitability'])
            ->name('inventory.profitability');

        // Заявки и сделки
        Route::get('orders/stats', [McpAnalyticsController::class, 'getOrdersStats'])
            ->name('orders.stats');

        Route::get('deals/list', [McpAnalyticsController::class, 'getDealsList'])
            ->name('deals.list');

        // Категории
        Route::get('categories/performance', [McpAnalyticsController::class, 'getCategoriesPerformance'])
            ->name('categories.performance');

        // Клиенты
        Route::get('clients/ltv', [McpAnalyticsController::class, 'getClientsLtv'])
            ->name('clients.ltv');
    });

