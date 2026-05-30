# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**TikTak.by** - Children's goods rental service (Minsk, Belarus)
- **Framework**: Laravel 8.75+, PHP 7.4+ with hybrid legacy PHP architecture
- **Database**: MariaDB 10.6 (73+ tables)
- **Frontend**: Laravel Mix (Sass + JS), Bootstrap
- **Hosting**: cPanel (Hoster.by), path: `~/public_html/`
- **Git**: GitHub repo `BelVeter/tiktakby_2026_v1`, branch protection on `main`

**CRITICAL**: Dual architecture:
1. **Laravel app** (`app/`, `routes/`, `resources/`) - Public website
2. **Legacy admin panel** (`bb/`) - Standalone PHP admin interface
3. **MCP Analytics API** (`routes/api.php`, `app/Http/Controllers/Mcp/*`) - 31 analytics + 9 AI-agent call endpoints under `/api/mcp/v1/`. Token + geo auth, `{query, data, meta}` envelope, OpenAPI spec at `/api/mcp/v1/openapi.json`.

**MCP API methodology (locked 2026-05-14)** — reproduces legacy admin reports `/bb/reports.php`, `/bb/sales_breakdown.php`, `/bb/dohrash2.php`, `/bb/cat_analysis.php`:
- Revenue = `SUM(r_paid + delivery_paid)` over `UNION(rent_sub_deals_act, rent_sub_deals_arch)` filtered by `acc_date` (accounting date — when payment landed). NOT by deal `cr_time`.
- Deal/return counts read `UNION(rent_deals_act, rent_deals_arch)`. `_act` holds ~430 currently-open deals; querying only `_arch` misses them.
- Office attribution uses `sub_deal.place + delivery_yn` (per-payment), NOT `deal.first_rent_place` (per-deal). office_id=0 = synthetic "Курьер" pseudo-office.
- Carnival items: detected via `tovar_rent_cat.cat_type=1`. Endpoints accept `include_carnival` (default true). `/finance/pnl` splits into `revenue_carnival_byn` + `revenue_non_carnival_byn`.
- Inventory at date X: `tovar_rent_items` (buy_date ≤ X) + `tovar_rent_items_arch` (buy_date ≤ X AND arch_time ≥ X). Used by `/inventory/utilization` as historical denominator.
- ⚠️ DO NOT sum `/finance/revenue` + `/carnival/revenue` — `/carnival/*` reads `karn_brons` (pre-booking system), `/finance/revenue` already includes carnival items as rentals. Double-count risk.
- Many-to-many trap: never join `subrazdel_category × razdel_subrazdel` directly in a query that also `SUM`s payments — that chain inflates sums M×N. Use `BaseController::itemsInRazdelSubquery()` instead.
- Legacy parity is enforced by `tests/Feature/Mcp/LegacyParityTest.php` — DO NOT remove or weaken those assertions without coordinating with the analytics workspace.

## Essential Commands

### Local Development (Two Options)

**Option 1: Docker (Recommended)**
```bash
docker-compose up -d
# App: http://localhost
# PhpMyAdmin: http://localhost:8088
# Database: localhost:33060

# Run commands inside container
docker-compose exec app php artisan migrate
docker-compose exec app npm run dev
docker-compose exec app php artisan test
```

**Option 2: Laragon (Windows)**
```bash
# ⚠️ CRITICAL: Must use Apache, NOT artisan serve
# Project root must serve as document root (not /public)
# Access: http://localhost (Apache required)
```

### Frontend Assets
```bash
npm run dev          # Development build
npm run watch        # Watch mode (rebuilds on file changes)
npm run prod         # Production build (COMMIT OUTPUT before deploying!)
```

### Database & Cache
```bash
php artisan migrate           # Run pending migrations
php artisan db:seed          # Seed database
php artisan optimize:clear   # Clear all caches (required after config/route changes)
php artisan config:cache     # Cache config
php artisan route:cache      # Cache routes (fails if route closures exist!)
php artisan view:cache       # Cache views
```

### Testing
```bash
php artisan test                    # Run all tests
./vendor/bin/phpunit               # Run PHPUnit directly
./vendor/bin/phpunit tests/Unit    # Unit tests only
./vendor/bin/phpunit tests/Feature # Feature tests only
./vendor/bin/phpunit --filter=TestName  # Run specific test
```

### Git & Deployment
```bash
git checkout -b feature/my-feature  # Create feature branch
git push -u origin feature/my-feature  # Push and create PR

# Production deployment (automated)
# Visit: https://tiktak.by/Deploy.php?key=SECRET_KEY
# Triggers: git reset --hard, composer install, migrate, cache rebuild
```

## Critical Architecture Rules

### 1. NO Route Closures (Production Breaking)
**FORBIDDEN**: Closures in `routes/web.php` break `route:cache` on production.
```php
// ❌ WRONG — Route::get('/path', function() { ... });
// ✅ CORRECT
Route::get('/path', 'App\Http\Controllers\MyController@method');
```
All routes must use full controller paths. This enables the `route:cache` optimization needed for production deployment.

### 2. Document Root Must Be Project Root
The project serves the root directory as document root, NOT `/public`:
- **Correct**: `http://localhost` (Apache serving `/` → project root)
- **Wrong**: `http://localhost:8000` (artisan serve) or `/public` as docroot
- **Setup**: Use Docker Compose or Laragon with project root as docroot

### 3. Frontend Builds Must Be Committed
Production server has no npm. Build locally and commit compiled assets:
```bash
npm run prod
git add public/css/ public/js/ public/mix-manifest.json
git commit -m "Build frontend assets"
```

### 4. Dual Database Access Patterns

**Laravel code** (`app/`, `routes/`, `resources/`):
```php
use Illuminate\Support\Facades\DB;
use App\Models\User;

$user = User::find(1);  // Eloquent ORM
$results = DB::table('clients')->where('id', $id)->first();  // Query Builder
```

**Legacy admin code** (`bb/`):
```php
$mysqli = \bb\Db::getInstance()->getConnection();
$result = $mysqli->query("SELECT * FROM clients WHERE id = {$id}");
```
**NEVER mix these approaches in the same file.**

### 5. Middleware Execution Order
Global middleware in `app/Http/Kernel.php`:
1. `CheckRedirects` — Intercepts all requests, checks `redirects` table for 301/302 redirects
2. Standard Laravel middleware (CORS, proxies, maintenance, etc.)

Route-specific middleware:
- `mcp.token` — Validates Bearer token for MCP API
- `mcp.geo` — Restricts MCP API to BY/RU countries
- `mcp.audit` — Logs all MCP API requests to `mcp_api_log` table

### 6. Authentication: Two Separate Systems

**Laravel sessions** — Standard Laravel auth system
**Legacy admin** (`bb/`) — Separate PHP session system via `$_COOKIE['tt_is_logged_in']`

Check admin status in Laravel views:
```php
// ✅ CORRECT (works in Laravel templates)
@if(isset($_COOKIE['tt_is_logged_in']))
    {{-- Admin-only content --}}
@endif

// ❌ WRONG (only works in bb/)
@if(\bb\models\User::isLoggedIn())  {{-- Won't work in Laravel context --}}
@endif
```

## Project Structure

### Controllers (`app/Http/Controllers/`)

| Controller | Purpose |
|-----------|---------|
| `MainController` | Homepage rendering |
| `CatController` | Catalog pages (sections → subsections → categories) |
| `L3Controller` | Individual product (model) pages with order form |
| `CartController` | Shopping cart display, tariff retrieval, availability check, checkout |
| `AboutController` | Static pages (about, conditions, delivery, contacts, policy, premium-start) |
| `SearchController` | Search, filtering by manufacturer and age |
| `FavoritesController` | Favorites functionality |
| `ZvonokController` | Callback requests, bookings (KB), subscriptions |
| `RedirectController` | URL redirects (created for `route:cache` compatibility) |

### MCP Analytics API Controllers (`app/Http/Controllers/Mcp/`)

All MCP controllers extend `BaseController` (envelope/cache/data-freshness helpers, TTL constants). Common date-range parameters validated by `App\Http\Requests\Mcp\RangeRequest` with defaults (last 12 months, `granularity=month`, all dimensional filters = `all`).

| Controller | Path prefix | Endpoints (count) |
|-----------|-------------|-------------------|
| `HealthController` | `/health`, `/openapi.json` | 2 — liveness probe + OpenAPI 3.0 spec |
| `MetaController` | `/meta/*` | 5 — categories, locations, expense-items, income-items, data-freshness |
| `FinanceController` | `/finance/*` | 4 — pnl (with 2025 warning), revenue, expenses, cash-flow |
| `OperationsController` | `/operations/*`, `/orders/stats`, `/deals/list` | 6 — funnel, timeline, by-category, by-location + 2 legacy |
| `InventoryController` | `/inventory/*` | 5 — free-tree, profitability, utilization, turnover, idle |
| `CustomersController` | `/customers/*`, `/clients/ltv` | 4 — timeline, cohorts, repeat-intervals + legacy LTV |
| `GeoController` | `/geo/clients-by-city` | 1 — city-level grouping (Minsk-district resolution deferred to Stage 2) |
| `LocationsController` | `/locations/*` | 2 — performance (per period × office), lifecycle (full history) |
| `CategoriesController` | `/categories/*` | 2 — seasonality, performance (legacy) |
| `CarnivalController` | `/carnival/*` | 3 — funnel, seasonality, revenue (UNION of `karn_brons` + `karn_brons_arch`) |
| `ExportController` | `/export/monthly/{topic}` | 1 — streaming CSV for `operations`, `revenue`, `pnl`, `traffic` |
| `CallsController` | `/calls/*` | 9 — recordings list, file stream, CDR, pending-analysis queue, get/submit/reset analysis, get/submit daily summary |

### Middleware (`app/Http/Middleware/`)

| Middleware | Type | Purpose |
|-----------|------|---------|
| `CheckRedirects` | Global | Intercepts all requests, checks `redirects` table for 301/302 redirects |
| `McpForceJsonMiddleware` | Route (mcp.json) | Sets `Accept: application/json` so validation failures return 422 JSON instead of 302 HTML |
| `McpTokenMiddleware` | Route (mcp.token) | Validates Bearer token from `MCP_API_TOKEN` env |
| `McpGeoCountryMiddleware` | Route (mcp.geo) | Restricts access by country (BY+RU using GeoLite2) |
| `McpAuditLogMiddleware` | Route (mcp.audit) | Logs each MCP API request to `mcp_api_log` table |

### Business Logic (`app/MyClasses/`)
- `MainPage` — Homepage generation
- `CatMainPage` — Catalog page logic
- `L3Page` — Product page display
- `L2ModelWeb` — Product model web representation
- `CatMenuItem`, `Header` — Navigation components
- `KBForm`, `KBronLine` — Booking form logic
- `Pic` — Image handling

### Admin Panel (`bb/`)
Standalone PHP application (not Laravel):
- `bb/index.php` — Admin dashboard with links
- `bb/Base.php` — Core functionality
- `bb/Db.php` — MySQLi singleton wrapper
- `bb/redirects.php`, `bb/redirects_api.php` — Redirect management
- `bb/items_manage.php` — Expense/income category management
- `bb/webp_converter.php` — Batch WebP conversion (GD library)
- `bb/classes/` — Business logic (Deal, Client, Category, Order, etc.)
- `bb/models/` — Data models (User, Office, Kassa, etc.)

### Routes (`routes/web.php`, `routes/api.php`)

**Web routes**:
- Language redirects: `/en/*`, `/lt/*` → `/ru/*` (only `/ru/` active)
- Catalog structure: `/{lang}/{razdel}/{subrazdel}/{category}/{model}`
- All routes use controllers (no closures!) — required for `route:cache`

**API routes** (`routes/api.php`):
- **MCP Analytics API** (`GET /api/mcp/v1/*`)
  - Middleware chain: `mcp.json` → `mcp.token` → `mcp.geo` → `mcp.audit` → `throttle:60,1`
  - 42 endpoints + `/health` + `/openapi.json` — see [docs/mcp_server.md](docs/mcp_server.md) and `resources/openapi/mcp-v1.json` for the full catalog
  - Response envelope: `{query, data, meta:{total_rows, currency:"BYN", data_freshness, warnings}}`
  - `/finance/pnl` injects a `meta.warnings` entry referring to `D-OPEN-FY2025` whenever the requested period overlaps 2025-01 or later — DO NOT remove this without coordinating with the analytics workspace at `/home/dmitry/Documents/прокат/`

### Database Tables (Key Groups)

| Group | Tables |
|-------|--------|
| **Catalog** | `razdel`, `razdel_subrazdel`, `sub_razdel`, `subrazdel_category`, `tovar_cats`, `tovar_list`, `tovar_properties` |
| **Rental** | `rent_deals_act`, `rent_orders`, `rent_model_web`, `rent_tarif_act`, `rent_sub_deals_act`, `deals` |
| **Orders** | `rent_orders`, `rent_orders_arch`, `karn_brons`, `karn_brons_arch` |
| **Clients** | `clients`, `clients_arch`, `users`, `logpass` |
| **Handbooks** | `rash_items`, `doh_items` (contain `is_active` for form filtering) |
| **Content** | `pages`, `video_links`, `dop_photos` |
| **Redirects** | `redirects` (source_url, target_url, status_code, is_active, hit_count, last_hit_at) |
| **MCP API** | `mcp_api_log` (audit logs); `idx_mcp_*` performance indexes added in migration `2026_05_09_000001_add_mcp_analytics_indexes` |
| **System** | `migrations`, `personal_access_tokens` |

### Frontend (`resources/views/`)
Blade templates. Main layout: `layouts/app.blade.php` (contains version number for vendor CSS/JS cache-busting via filemtime).

## Deployment Process

`Deploy.php` handles production deployment (triggered via URL with secret key):

1. `git fetch origin` + `git reset --hard origin/main`
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force`
4. `php artisan optimize:clear`
5. `php artisan config:cache`
6. `php artisan route:cache` ← Requires no route closures
7. `php artisan view:cache`

**IMPORTANT**: 
- `git clean` is intentionally DISABLED to preserve unversioned user-uploaded images in `/bb/`
- Never use force push to `main` — branch protection prevents accidental overwrites
- All assets must be built locally (`npm run prod`) and committed before deployment

## Common Pitfalls

| Issue | Solution |
|-------|----------|
| **Route caching fails** | Check for closures in `routes/web.php` — must use controller methods |
| **Session data lost in `bb/`** | Don't use Laravel session APIs in legacy code; use `$_COOKIE`, `\bb\Db`, raw PHP |
| **Frontend looks outdated** | Run `npm run prod` and commit `public/` assets before deploying |
| **404 errors on dynamic routes** | Check `CheckRedirects` middleware — verify route exists AND isn't in `redirects` table |
| **Database migrations fail locally** | Ensure DB credentials in `.env` match Docker/Laragon setup; run `php artisan migrate:fresh --seed` |
| **MCP API returns 403** | Check: Bearer token in `Authorization` header, client IP in BY/RU, GeoLite2 database at `storage/app/geoip/GeoLite2-Country.mmdb` |
| **Can't connect to Docker database** | Use `db` as host inside container, `localhost:33060` from your machine; phpmyadmin at `http://localhost:8088` |

## Configuration

### Environment Variables (`.env`)
```
APP_KEY=          # Laravel encryption key (generated automatically)
DB_CONNECTION=mysql
DB_HOST=db        # Docker: 'db'; Laragon: localhost
DB_PORT=3306
DB_DATABASE=tiktakby_tiktak
DB_USERNAME=tiktakby_tiktak
DB_PASSWORD=Vai7evahch

MCP_API_TOKEN=    # Bearer token for MCP Analytics API
MCP_GEO_ALLOWED_COUNTRIES=BY,RU  # Restrict API access by country
MCP_CACHE_TTL=300  # Cache TTL in seconds
```

## Additional Documentation

For deeper details, see `AGENTS.md`:
- Complete database schema with all table descriptions
- Detailed MCP API endpoint specifications
- Admin panel structure and file organization
- Git workflow and branching strategy
- Security, authentication, and session management
- Project history and stakeholder context

**External APIs & Integrations**:
- [RocketSMS API Integration](docs/rocketsms_api.md)
