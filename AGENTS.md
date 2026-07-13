# TikTak.by — Architecture & Context

## Overview

- **Website**: https://tiktak.by — children's goods rental in Minsk, Belarus
- **Framework**: Laravel 8.83.29 (PHP)
- **Database**: MySQL, database `tiktakby_2026_v1` (~73 tables)
- **Hosting**: cPanel (Hoster.by), path: `~/public_html/`
- **Local environment**: Laragon on Windows, project at `d:\sites\tiktakby_2026_v1`
- **Git**: GitHub, repo `BelVeter/tiktakby_2026_v1`
- **Branching**: `main` = production, feature branches (e.g. `dima2`). Branch protection on `main` — merges only via PRs
- **Deploy**: `Deploy.php` (triggered via URL with secret key). Does `git reset --hard origin/main`, `composer install`, `migrate`, config/route/view caching. **NOTE**: `git clean` is DISABLED to protect user-uploaded images in `/bb/`.

## Local Development

> **WARNING: Do NOT use `php artisan serve`.** This site does NOT work with Laravel's built-in server. The admin panel (`bb/`) and other legacy parts require the document root to be the project root (not `/public`). Use **Laragon's Apache** server instead — the site is available at `http://localhost`, not `localhost:8000`.

## Project Structure

### Controllers (`app/Http/Controllers/`)

| Controller | Purpose |
|-----------|---------|
| `MainController` | Home page |
| `CatController` | Catalog: sections, subsections, categories |
| `L3Controller` | Individual product (model) page + order form |
| `AboutController` | Static pages (about, conditions, delivery, contacts, policy) |
| `SearchController` | Search, filtering by manufacturer and age |
| `ZvonokController` | Callback requests, bookings, subscriptions |
| `RedirectController` | All redirect routes (created for `route:cache` compatibility) |
| `FavoritesController` | Favorites functionality (added by Kristina) |
| `CartController` | Shopping cart: display cart page, tariff retrieval, availability check, checkout with booking creation |
| `Feed2GisController` | Generation of 2GIS XML feed (`/api/feed/2gis`) |
| `Mcp/HealthController` | `/health` + `/openapi.json` |
| `Mcp/MetaController` | `/meta/*` — categories, locations, expense-items, income-items, data-freshness |
| `Mcp/FinanceController` | `/finance/{pnl,revenue,revenue-by-category,expenses,cash-flow}` — P&L injects 2025 bank-channel warning |
| `Mcp/OperationsController` | `/operations/*` (funnel/timeline/by-category/by-location) + legacy `/orders/stats`, `/deals/list` |
| `Mcp/InventoryController` | `/inventory/{free-tree,pricing,profitability,utilization,turnover,idle}` |
| `Mcp/CustomersController` | `/customers/{timeline,cohorts,repeat-intervals}` + legacy `/clients/ltv` |
| `Mcp/GeoController` | `/geo/clients-by-city` (Minsk-district resolution deferred to Stage 2) |
| `Mcp/LocationsController` | `/locations/{performance,lifecycle}` |
| `Mcp/CategoriesController` | `/categories/{seasonality,performance}` |
| `Mcp/CarnivalController` | `/carnival/{funnel,seasonality,revenue}` (UNION of `karn_brons` + `karn_brons_arch`) |
| `Mcp/ExportController` | `/export/monthly/{topic}` — CSV streams matching `data/monthly/_schema.md` |
| `Mcp/CallsController` | `/calls/*` — A1 Call recordings, CDR, and AI call analysis |
| `Mcp/SmsController` | `/sms/send` — sending SMS messages via RocketSMS API |
| `Mcp/BaseController` | abstract — `envelope()`, `cacheRemember()`, `dataFreshness()`, TTL constants |
| `Mcp/MarketingController` | `/marketing/conversions` — UTM-attributed conversion events for all conversion types |
| `Mcp/RedirectsController` | `/redirects/*` — redirects CRUD API and bulk upsert |
| `BbAudioController` | `/bb-internal/audio/{uuid}` — streams audio files to bb/ interface with cookie auth |

See `docs/mcp_server.md` and `resources/openapi/mcp-v1.json` for the full endpoint catalog.

> **Marketing tracking**: Frontend click events (`phone_click`, `add_to_cart_click`) are written to `tiktak_utms` via `POST /track-event` (web route, handled by `TrackingController`). This is separate from the MCP API and requires a CSRF token from the frontend.

### Middleware (`app/Http/Middleware/`)

- `CheckRedirects` — global middleware (in `$middleware` in `Kernel.php`). Intercepts requests and checks the `redirects` table for 301/302 redirects
- `McpForceJsonMiddleware` — route middleware (`mcp.json`). Sets `Accept: application/json` so validation failures return 422 JSON instead of 302 HTML redirects
- `McpTokenMiddleware` — route middleware (`mcp.token`). Validates Bearer token from `MCP_API_TOKEN` env var for MCP API
- `McpGeoCountryMiddleware` — route middleware (`mcp.geo`). Restricts access by country (BY+RU) using GeoLite2 database at `storage/app/geoip/GeoLite2-Country.mmdb`
- `McpAuditLogMiddleware` — route middleware (`mcp.audit`). Writes each MCP API request to the `mcp_api_log` table
- `TrackUtmMiddleware` — global middleware. Parses and stores `utm_*` tracking parameters (source, medium, campaign) into HTTP-only cookie and sets DB context tracking for API connections

### Form Requests (`app/Http/Requests/Mcp/`)

- `RangeRequest` — common parameters for range-based MCP endpoints. Default range is the last 12 months; `granularity=month`; dimensional filters `category` (razdel alias) and `detailed_category` (tovar_rent_cat alias) default to `all` and support **multiple comma-separated values**; `include_carnival` defaults to `true`. Provides `categories()` to retrieve normalized array of category slugs, `granularityFormatFor($column)` for MySQL `DATE_FORMAT()`, and `includeCarnival()` flag.

### MyClasses (`app/MyClasses/`)

Business logic classes:
- `MainPage` — home page generation
- `CatMainPage` — catalog pages
- `L3Page` — product page
- `L2ModelWeb` — product models for web display
- `CatMenuItem` — catalog menu items
- `Header` — site header
- `KBForm`, `KBronLine` — booking forms

### Admin Panel (`bb/`)

Separate PHP admin panel (not Laravel-based), accessible at `/bb/`. Key files:
- `bb/index.php` — dashboard with links to all sections
- `bb/bb_nav.php` — shared navigation component (admin header)
- `bb/redirects.php` — redirect management
- `bb/redirects_api.php` — API for cascading URL selection (by site structure)
- `bb/items_manage.php` — Management of expense and income categories (added by Antigravity)
- `bb/rash_analysis.php` — Interactive expense analysis with breakdown charts (added by Antigravity)
- `bb/webp_converter.php` — batch image conversion tool (GD library, WebP)
- `bb/a1_missed_calls.php` — A1 VATS missed calls viewer (reads from `storage/app/a1_missed_calls.json`)
- Order, client, product, and rental management

### Templates (`resources/views/`)

Blade templates. Main layout: `layouts/app.blade.php` (contains version number for vendor CSS/JS cache-busting).

### Routes (`routes/web.php` and `routes/api.php`)

- All routes use controllers (no closures!) — required for `route:cache`
- Language redirects `/en/*`, `/lt/*` → `/ru/*`
- Catalog: `/{lang}/{razdel}/{subrazdel}/{category}/{model}`
- Fallback → 404 page
- **MCP API** (`routes/api.php`): `GET /api/mcp/v1/*` — 31 analytics endpoints + `/health` + `/openapi.json` + `/redirects/*`, middleware chain `mcp.json → mcp.token → mcp.audit → throttle:60,1`. All responses follow the `{query, data, meta}` envelope with `meta.currency=BYN`. `/finance/pnl` injects a `D-OPEN-FY2025` warning whenever the period overlaps 2025+.

  **Methodology (locked 2026-05-14, reproduces legacy admin reports — see `docs/mcp_server.md`):**
  - Revenue = `SUM(r_paid + delivery_paid)` over `UNION(rent_sub_deals_act, rent_sub_deals_arch)` by `acc_date` (not deal `cr_time`).
  - Deal/return counts read `UNION(rent_deals_act, rent_deals_arch)`. `_act` holds ~430 open deals.
  - Issuance and **renewal** counts use `UNION(rent_sub_deals_act, rent_sub_deals_arch)` filtered by sub-deal type (`first_rent`/`takeaway_plan` for issuance, `extention` for renewal).
  - Office attribution uses `sub_deal.place + delivery_yn` per-payment, not `deal.first_rent_place` per-deal. office_id=0 = Курьер pseudo-office.
  - Carnival = `tovar_rent_cat.cat_type=1`. `include_carnival` toggle (default true). `/finance/pnl` always splits into carnival/non-carnival columns.
  - Inventory at date X = `tovar_rent_items (buy_date≤X)` + `tovar_rent_items_arch (buy_date≤X AND arch_time≥X)`. Used by `/inventory/utilization`.
  - `subrazdel_category × razdel_subrazdel` joins inflate SUM aggregates M×N. Use `BaseController::itemsInRazdelSubquery()` instead.
  - `tests/Feature/Mcp/LegacyParityTest.php` enforces parity with direct legacy-style SQL.

## Deploy (`Deploy.php`)

Sequence:
1. `git fetch origin`
2. `git reset --hard origin/main`
3. `composer install --no-dev`
4. `php artisan migrate --force`
5. `php artisan optimize:clear`
6. `php artisan config:cache`
7. `php artisan route:cache` ← **works because closures were replaced with controllers**
8. `php artisan view:cache`
9. **NOTE**: `git clean` is intentionally disabled to prevent deletion of unversioned images in /bb/.

## Database (main tables)

| Group | Tables |
|-------|--------|
| Catalog | `razdel`, `razdel_subrazdel`, `sub_razdel`, `subrazdel_category`, `tovar_rent_cat`, `tovar_list`, `tovar_properties` |
| Rental | `rent_deals_act`, `rent_orders`, `rent_model_web` (includes `faq` TEXT/JSON column), `rent_tarif_act`, `rent_sub_deals_act`, `deals` |
| Clients | `clients`, `clients_arch`, `clients_geo`, `users`, `logpass` |
| Handbooks | `rash_items`, `doh_items` (contain `is_active` column for entry form filtering) |
| Orders | `rent_orders`, `rent_orders_arch`, `karn_brons`, `karn_brons_arch` |
| Content | `pages` (includes `faq` TEXT/JSON column), `video_links`, `dop_photos` |
| Redirects | `redirects` (source_url, target_url, status_code, is_active, is_regex, hit_count, last_hit_at) |
| System | `migrations`, `users`, `personal_access_tokens` |
| A1 API | `a1_call_recordings` (has `has_audio tinyint DEFAULT 1` — set to 0 when file deleted from disk to preserve transcripts; never delete rows), `a1_recordings_fetch_log`, `a1_cdr`, `a1_cdr_fetch_log`, `a1_call_analysis`, `a1_daily_summaries` |
| MCP | `mcp_api_log` (ip, method, endpoint, query_params, status_code, response_ms, user_agent); plus `idx_mcp_*` performance indexes on `rent_deals_arch`, `rent_sub_deals_arch`, `doh_rash`, `clients`, `karn_brons`, `karn_brons_arch`, `rent_orders`, `rent_orders_arch`, `rent_deals_act` (migration `2026_05_09_000001_add_mcp_analytics_indexes`) |
| Marketing | `tiktak_utms` (entity_type, entity_id, utm_source, utm_medium, utm_campaign, utm_term, utm_content, created_at). Written by `UtmTracker::track()`. entity_types: `zvonki`, `rent_orders`, `karn_brons`, `kb_zayavki`, `phone_click`, `add_to_cart_click` |

## Data Access Strategy

The project uses two distinct methods for database interaction due to its hybrid nature:

1.  **Laravel (`app/`)**:
    - Uses standard **Eloquent ORM** and **Query Builder**.
    - Configuration in `.env` and `config/database.php`.

2.  **Legacy/Admin (`bb/`)**:
    - Uses `bb/Db.php` — a custom Singleton wrapper for `mysqli`.
    - **Usage**: `$mysqli = \bb\Db::getInstance()->getConnection();`
    - **Context**: When modifying files in `bb/`, use this existing `Db` class. Do not attempt to use Laravel's Eloquent in `bb/` files unless you are sure Laravel is bootstrapped (which is not guaranteed in all `bb/` scripts).

## Known Specifics

1. **CSS/JS versioning**: `app.css` and `app.js` use `{{ mix() }}` in Blade — Laravel Mix auto-appends a content hash on `npm run prod`. Vendor files (bootstrap, popper) use manual `?v={{$v}}` in `app.blade.php`
2. **Multilingual**: URLs start with `/{lang}/`, but only `/ru/` is actually used. `/en/*` and `/lt/*` routes redirect to `/ru/*`
3. **Legacy code**: the project root contains many old .htm files and folders (pre-Laravel era)
4. **Carnival costumes**: separate section with special routes and booking logic
5. **No npm on production**: frontend is built locally (`npm run prod`), output in `public/` + `mix-manifest.json`, then committed to git
6. **PHP version**: Container runs **PHP 7.4** — avoid PHP 8.0+ syntax (`match`, named arguments, nullsafe operator `?->`, etc.) in Laravel code
7. **MCP Analytics API**: A GET-only analytics API lives at `/api/mcp/v1/` and is consumed by a Node.js MCP wrapper at `/home/dmitry/sites/mcp-tiktak/`. The wrapper uses `@modelcontextprotocol/sdk` and Node.js 20 (installed via fnm at `/home/dmitry/.fnm`). See `mcp-tiktak/README.md` for Claude Desktop config. The Bearer token is in `.env` as `MCP_API_TOKEN`. GeoLite2 DB is at `storage/app/geoip/GeoLite2-Country.mmdb` (not in git — downloaded at setup time).
8. **Local dev environment**: Docker-based on Linux (not Laragon/Windows). Containers: `tiktakby-app` (Apache+PHP 7.4, port 80), `db` (MySQL). Run commands via `docker exec tiktakby-app php artisan ...`
9. **Sitemap Generation**: A cron job runs `php artisan sitemap:generate` daily to update `sitemap.xml` (root) and `public/sitemap.xml`. The command iterates through all active catalog categories and products.
10. **A1 VATS Integration**: Missed calls are fetched via `php artisan a1:fetch-missed-calls` (scheduled every 10 min during 9:00–19:00, hourly otherwise). Call recordings are fetched via `php artisan a1:fetch-recordings`. Credentials in `.env`: `A1_COMPANY_ID`, `A1_API_KEY`. Tokens stored in `storage/app/a1_tokens.json` (access: 1 day, refresh: 7 days). Calls stored in `storage/app/a1_missed_calls.json` (UUID-keyed, max 500 records). Enriched with CRM data: client lookup by phone (last-7-digits normalization), active rentals from `rent_deals_act`, last return date from `rent_deals_arch`. Viewed at `/bb/a1_missed_calls.php`. **Audio file lifecycle**: files stored at `storage/app/a1_recordings/` with a 1 GB quota. When quota is exceeded, `FetchA1Recordings::enforceQuota()` deletes the oldest files from disk and sets `has_audio=0` on the DB row — the row itself is **never deleted**, preserving `a1_call_analysis` data (transcript, AI summary) forever. `GET /calls/recordings` returns `file_url=null` and `has_audio=false` for audio-less records. `GET /calls/pending-analysis` excludes them.
11. **RocketSMS Integration**: A legacy PHP class `\bb\classes\RocketSMS` handles sending SMS messages, checking balance, and checking status. The credentials are read manually from `ROCKETSMS_USERNAME` and `ROCKETSMS_PASSWORD` in `.env`. The test interface is at `/bb/rocketsms_test.php` (accessible only to the administrator). Documentation is at `docs/rocketsms_api.md`.
12. **2GIS Feed Generation**: A cron job runs `php artisan feed:2gis` to generate the `feed_2gis_xml` cache used by `Feed2GisController` for providing a YML catalog at `/api/feed/2gis`.
13. **`php artisan migrate` is broken on production for ANY new migration** (found 2026-07-05). Host is CloudLinux; ionCube Loader is active on every PHP version, including `/opt/php74/bin/php` (7.4.33, the version this project actually uses). Any brand-new migration file — even a trivial one with a never-before-used class name on a throwaway table — fails with `PHP Fatal error: Cannot declare class X, because the name is already in use`. Reproduced repeatedly; not fixed by `opcache_reset()`, not caused by a real class/name collision (checked composer classmap, `loadMigrationsFrom`, duplicate files — all clean). The project has no ionCube-encoded files in `vendor/`, so the loader isn't actually needed — a support ticket was sent to `support@hoster.by` 2026-07-05 asking to disable it for account `h149208`. **Workaround until fixed**: apply schema changes via direct `mysql -e "ALTER TABLE ..."` over SSH, then manually `INSERT INTO migrations (migration, batch) VALUES (...)` so Laravel doesn't try to re-run it later. Full details in `docs/db_notes.md` (gotcha #7).
14. **Client geocoding + heatmap**: `app/Console/Commands/GeocodeClients.php` geocodes `clients.city/str/dom` via Google Maps (Yandex Geocoder as fallback when configured) into `clients_geo` (`geo_status`: 1=resolved, 2=unresolved). `bb/geo_heatmap.php` renders results via Leaflet+OpenStreetMap (not Yandex's own map widget — matters for Yandex Geocoder license compliance, see below) and shows a total-vs-unrecognized client count. Many `geo_status=2` failures are real Minsk streets written as informal local abbreviations (e.g. "Беды" → "улица Леонида Беды") that trip up strict geocoders, not bad data — see `docs/geo_address_fix.md` for the known-abbreviation dictionary and the fix workflow (AI-normalize → re-test via Google → write to `clients_geo.corrected_address`, never overwrite the client's own `city/str/dom`). Yandex Geocoder's free tier requires public-facing use and forbids storing results — this project's use (private admin tool + persisted coordinates) needs the paid commercial tier.

## Rules for AI Agents

> **MANDATORY**: This file MUST always be written in **English**. Do NOT rewrite it in Russian or any other language, even if the user communicates in Russian.

> **MANDATORY**: When modifying the project architecture — adding/removing controllers, middleware, DB tables, changing the deploy process, routes, or other significant structural changes — **update this `AGENTS.md` file** to keep it current.

### When to update this file:
- Controller added/removed/renamed → update the Controllers table
- Middleware added/removed → update the Middleware section
- New DB table created → update the Database section
- `Deploy.php` changed → update the Deploy section
- Routes changed (`web.php`) → update the Routes section
- New important specifics discovered → update Known Specifics
- **MCP API endpoint changed** (new fields, new params, new endpoint) → update **both**:
  1. `resources/openapi/mcp-v1.json` — the `responses` schema for that endpoint (not just `summary`!)
  2. `docs/mcp_server.md` — the endpoint table description

### Project rules:
- **Closures in `web.php` are FORBIDDEN** — they break `route:cache` on production
- **Do NOT use `php artisan serve`** — the site only works through Laragon's Apache at `http://localhost`
- When changing CSS/JS — run `npm run prod` locally and commit `public/js`, `public/css`, `mix-manifest.json`
- The `$v` version number in `app.blade.php` only needs incrementing for vendor files (bootstrap, popper)

### Command Execution
- **Safe to auto-run**: All `git` and `mysql` commands that are read-only or non-destructive (e.g., `git status`, `git log`, `git diff`, `mysql SHOW TABLES`, `mysql SELECT`) should be executed with `SafeToAutoRun: true`.
- **Project commands**: Standard development commands (`npm`, `composer`, `php artisan`) should be auto-run for efficiency.
- **Exceptions**: Only ask for confirmation for mass deletion of files or dropping of fundamental database tables.


## Authentication & Security

The project runs two parallel systems (Laravel and Legacy PHP), each with its own session management.

1.  **Legacy Admin (`/bb/`)**:
    -   Uses native PHP sessions (`session_start()`).
    -   User login sets the `tt_is_logged_in` cookie (valid for 30 days).
    -   Verification: `\bb\models\User::isLoggedIn()` (works ONLY within legacy scripts).

2.  **Laravel App (`/`)**:
    -   Uses Laravel's session driver (file/cookie).
    -   **Does NOT share sessions** with the legacy admin panel.
    -   `\bb\models\User::isLoggedIn()` returns `false` in Laravel controllers/views because it cannot access the legacy PHP session.

### How to Check Admin Status in Laravel

To determine if a user is an administrator (logged into `/bb/`) from within a Laravel Blade template or Controller, **DO NOT use `User::isLoggedIn()`**. Instead, check for the authentication cookie:

```php
// In Blade Templates
@if(isset($_COOKIE['tt_is_logged_in']))
    {{-- Admin-only content --}}
@endif

// In PHP/Controllers
if (isset($_COOKIE['tt_is_logged_in'])) {
    // Admin logic
}
```

This cookie serves as the bridge between the two systems for "is logged in" checks on the frontend. for deep security, backend scripts in `/bb/` must still use `\bb\Base::loginCheck()`.

