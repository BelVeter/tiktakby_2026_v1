<?php

use App\Http\Controllers\Mcp\CallsController;
use App\Http\Controllers\Mcp\CarnivalController;
use App\Http\Controllers\Mcp\CategoriesController;
use App\Http\Controllers\Mcp\CustomersController;
use App\Http\Controllers\Mcp\ExportController;
use App\Http\Controllers\Mcp\FinanceController;
use App\Http\Controllers\Mcp\FinanceBankImportController;
use App\Http\Controllers\Mcp\GeoController;
use App\Http\Controllers\Mcp\HealthController;
use App\Http\Controllers\Mcp\InventoryController;
use App\Http\Controllers\Mcp\LocationsController;
use App\Http\Controllers\Mcp\MarketingController;
use App\Http\Controllers\Mcp\MetaController;
use App\Http\Controllers\Mcp\OperationsController;
use App\Http\Controllers\Mcp\PagesHistoryController;
use App\Http\Controllers\Mcp\PagesListingController;
use App\Http\Controllers\Mcp\PagesProductController;
use App\Http\Controllers\Mcp\PricingController;
use App\Http\Controllers\Mcp\RedirectsController;
use App\Http\Controllers\Mcp\SmsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Standard Laravel route — kept as-is.
Route::middleware('auth:api')->get('/user', function (\Illuminate\Http\Request $request) {
    return $request->user();
});

// ──────────────────────────────────────────────────────────────────────────
// MCP Analytics API v1
// Middleware: mcp.token (Bearer auth) → mcp.audit (log) → throttle
// ──────────────────────────────────────────────────────────────────────────
Route::prefix('mcp/v1')
    ->middleware(['mcp.json', 'mcp.token', 'mcp.audit', 'throttle:60,1'])
    ->name('mcp.v1.')
    ->group(function () {

        // Health check
        Route::get('health', [HealthController::class, 'index'])->name('health');

        // OpenAPI 3.0 spec for MCP-server tool auto-generation (Phase B)
        Route::get('openapi.json', [HealthController::class, 'openapi'])->name('openapi');

        // Meta / reference (A.3)
        Route::get('meta/categories',      [MetaController::class, 'categories'])->name('meta.categories');
        Route::get('meta/locations',       [MetaController::class, 'locations'])->name('meta.locations');
        Route::get('meta/expense-items',   [MetaController::class, 'expenseItems'])->name('meta.expense-items');
        Route::get('meta/income-items',    [MetaController::class, 'incomeItems'])->name('meta.income-items');
        Route::get('meta/data-freshness',  [MetaController::class, 'dataFreshnessEndpoint'])->name('meta.data-freshness');

        // Finance (A.4)
        Route::get('finance/pnl',                 [FinanceController::class, 'pnl'])->name('finance.pnl');
        Route::get('finance/revenue',             [FinanceController::class, 'revenue'])->name('finance.revenue');
        Route::get('finance/revenue-by-category', [FinanceController::class, 'revenueByCategory'])->name('finance.revenue-by-category');
        Route::get('finance/expenses',            [FinanceController::class, 'expenses'])->name('finance.expenses');
        Route::get('finance/cash-flow', [FinanceController::class, 'cashFlow'])->name('finance.cash-flow');
        Route::post('finance/bank-import', [FinanceBankImportController::class, 'store'])->name('finance.bank-import');

        // Operations (A.5)
        Route::get('operations/funnel',      [OperationsController::class, 'funnel'])->name('operations.funnel');
        Route::get('operations/timeline',    [OperationsController::class, 'timeline'])->name('operations.timeline');
        Route::get('operations/by-category', [OperationsController::class, 'byCategory'])->name('operations.by-category');
        Route::get('operations/by-location', [OperationsController::class, 'byLocation'])->name('operations.by-location');
        Route::get('operations/deals-by-model', [OperationsController::class, 'dealsByModel'])->name('operations.deals-by-model');

        // Inventory (A.6 + existing)
        Route::get('inventory/free-tree',      [InventoryController::class, 'freeTree'])->name('inventory.free-tree');
        Route::get('inventory/pricing',        [InventoryController::class, 'pricing'])->name('inventory.pricing');
        Route::get('inventory/profitability',  [InventoryController::class, 'profitability'])->name('inventory.profitability');
        Route::get('inventory/utilization',    [InventoryController::class, 'utilization'])->name('inventory.utilization');
        Route::get('inventory/turnover',       [InventoryController::class, 'turnover'])->name('inventory.turnover');
        Route::get('inventory/idle',           [InventoryController::class, 'idle'])->name('inventory.idle');

        // Pricing history (2026-07-31)
        Route::get('pricing/history',  [PricingController::class, 'history'])->name('pricing.history');
        Route::get('pricing/snapshot', [PricingController::class, 'snapshot'])->name('pricing.snapshot');

        // Customers (A.7 + existing /clients/ltv)
        Route::get('customers/timeline',          [CustomersController::class, 'timeline'])->name('customers.timeline');
        Route::get('customers/cohorts',           [CustomersController::class, 'cohorts'])->name('customers.cohorts');
        Route::get('customers/repeat-intervals',  [CustomersController::class, 'repeatIntervals'])->name('customers.repeat-intervals');
        Route::get('clients/ltv',                 [CustomersController::class, 'ltv'])->name('clients.ltv');

        // Geo (A.8)
        Route::get('geo/clients-by-city', [GeoController::class, 'clientsByCity'])->name('geo.clients-by-city');

        // Locations (A.9)
        Route::get('locations/performance', [LocationsController::class, 'performance'])->name('locations.performance');
        Route::get('locations/lifecycle',   [LocationsController::class, 'lifecycle'])->name('locations.lifecycle');

        // Categories (A.10 + existing /performance)
        Route::get('categories/seasonality', [CategoriesController::class, 'seasonality'])->name('categories.seasonality');
        Route::get('categories/performance', [CategoriesController::class, 'performance'])->name('categories.performance');

        // Carnival (A.11)
        Route::get('carnival/funnel',       [CarnivalController::class, 'funnel'])->name('carnival.funnel');
        Route::get('carnival/seasonality',  [CarnivalController::class, 'seasonality'])->name('carnival.seasonality');
        Route::get('carnival/revenue',      [CarnivalController::class, 'revenue'])->name('carnival.revenue');

        // Export (A.12)
        Route::get('export/monthly/{topic}', [ExportController::class, 'monthly'])
            ->where('topic', '[a-z_-]+')
            ->name('export.monthly');

        // A1 Calls: demand reporting (new)
        Route::get('calls/demand', [CallsController::class, 'getDemandAggregate'])->name('calls.demand');
        Route::get('calls/recordings/{uuid}/items', [CallsController::class, 'getDemandItems'])->name('calls.recordings.items.get');
        Route::post('calls/recordings/{uuid}/items', [CallsController::class, 'submitDemandItems'])->name('calls.recordings.items.post');

        // A1 Calls: recordings (existing)
        Route::get('calls/recordings', [CallsController::class, 'index'])->name('calls.recordings');
        Route::post('calls/recordings/import-completed', [CallsController::class, 'importCompleted'])->name('calls.recordings.import-completed');
        Route::get('calls/recordings/{uuid}/file', [CallsController::class, 'streamFile'])->name('calls.recordings.file');

        // A1 Calls: CDR and AI analysis (new)
        Route::get('calls/cdr', [CallsController::class, 'cdr'])->name('calls.cdr');
        Route::get('calls/pending-analysis', [CallsController::class, 'pendingAnalysis'])->name('calls.pending-analysis');
        Route::get('calls/recordings/{uuid}/analysis', [CallsController::class, 'getAnalysis'])->name('calls.recordings.analysis.get');
        Route::post('calls/recordings/{uuid}/analysis', [CallsController::class, 'submitAnalysis'])->name('calls.recordings.analysis.post');
        Route::post('calls/recordings/{uuid}/reset-analysis', [CallsController::class, 'resetAnalysis'])->name('calls.recordings.analysis.reset');
        Route::get('calls/daily-summary/{date}', [CallsController::class, 'getDailySummary'])->name('calls.daily-summary.get');
        Route::post('calls/daily-summary/{date}', [CallsController::class, 'submitDailySummary'])->name('calls.daily-summary.post');

        // Marketing Conversions
        Route::get('marketing/conversions', [MarketingController::class, 'conversions'])->name('marketing.conversions');

        // SMS / Notifications
        Route::post('sms/send', [SmsController::class, 'send'])->name('sms.send');

        // Pages (SEO)
        Route::get('pages/listing',          [PagesListingController::class, 'index'])->name('pages.listing.index');
        Route::get('pages/listing/{slug}',   [PagesListingController::class, 'show'])->name('pages.listing.show');
        Route::post('pages/listing/{slug}/image', [PagesListingController::class, 'uploadImage'])->name('pages.listing.uploadImage');
        Route::get('pages/listing/{slug}/products', [PagesListingController::class, 'products'])->name('pages.listing.products');
        Route::patch('pages/listing/{slug}', [PagesListingController::class, 'update'])->name('pages.listing.update');

        Route::get('pages/product',          [PagesProductController::class, 'index'])->name('pages.product.index');
        // `bulk` must precede `{slug}` — otherwise it is swallowed as a slug.
        Route::patch('pages/product/bulk',   [PagesProductController::class, 'bulkUpdate'])->name('pages.product.bulk');
        Route::get('pages/product/{slug}',   [PagesProductController::class, 'show'])->name('pages.product.show');
        Route::patch('pages/product/{slug}', [PagesProductController::class, 'update'])->name('pages.product.update');

        // Change log of SEO content written through this API (both levels)
        Route::get('pages/history',          [PagesHistoryController::class, 'index'])->name('pages.history');


        // Redirects management (CRUD + bulk)
        Route::get('redirects',           [RedirectsController::class, 'index'])->name('redirects.index');
        Route::post('redirects/bulk',     [RedirectsController::class, 'bulk'])->name('redirects.bulk');
        Route::post('redirects',          [RedirectsController::class, 'store'])->name('redirects.store');
        Route::patch('redirects/{id}',    [RedirectsController::class, 'update'])->name('redirects.update')->where('id', '[0-9]+');
        Route::delete('redirects/{id}',   [RedirectsController::class, 'destroy'])->name('redirects.destroy')->where('id', '[0-9]+');
    });
