# TikTak.by — Architecture & Context

## Overview

- **Website**: https://tiktak.by — children's goods rental in Minsk, Belarus
- **Framework**: Laravel 8.83.29 (PHP)
- **Database**: MySQL, database `tiktakby_2026_v1` (~73 tables)
- **Hosting**: cPanel (Hoster.by), path: `~/public_html/`
- **Local environment**: Laragon on Windows, project at `d:\sites\tiktakby_2026_v1`
- **Git**: GitHub, repo `BelVeter/tiktakby_2026_v1`
- **Branching**: `main` = production, feature branches (e.g. `dima2`). Branch protection on `main` — merges only via PRs
- **Deploy**: `Deploy.php` (triggered via URL with secret key). Does `git reset --hard origin/main`, `composer install`, `migrate`, config/route/view caching

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

### Middleware (`app/Http/Middleware/`)

- `CheckRedirects` — global middleware (in `$middleware` in `Kernel.php`). Intercepts requests and checks the `redirects` table for 301/302 redirects

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
- `bb/redirects.php` — redirect management
- `bb/redirects_api.php` — API for cascading URL selection (by site structure)
- Order, client, product, and rental management

### Templates (`resources/views/`)

Blade templates. Main layout: `layouts/app.blade.php` (contains version number for vendor CSS/JS cache-busting).

### Routes (`routes/web.php`)

- All routes use controllers (no closures!) — required for `route:cache`
- Language redirects `/en/*`, `/lt/*` → `/ru/*`
- Catalog: `/{lang}/{razdel}/{subrazdel}/{category}/{model}`
- Fallback → 404 page

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

## Database (main tables)

| Group | Tables |
|-------|--------|
| Catalog | `razdel`, `razdel_subrazdel`, `sub_razdel`, `subrazdel_category`, `tovar_cats`, `tovar_list`, `tovar_properties` |
| Rental | `rent_deals_act`, `rent_orders`, `rent_model_web`, `rent_tarif_act`, `rent_sub_deals_act`, `deals` |
| Clients | `clients`, `clients_arch`, `users`, `logpass` |
| Orders | `rent_orders`, `rent_orders_arch`, `karn_brons`, `karn_brons_arch` |
| Content | `pages`, `video_links`, `dop_photos` |
| Redirects | `redirects` (source_url, target_url, status_code, is_active, hit_count, last_hit_at) |
| System | `migrations`, `users`, `personal_access_tokens` |

## Known Specifics

1. **CSS/JS versioning**: `app.css` and `app.js` use `{{ mix() }}` in Blade — Laravel Mix auto-appends a content hash on `npm run prod`. Vendor files (bootstrap, popper) use manual `?v={{$v}}` in `app.blade.php`
2. **Multilingual**: URLs start with `/{lang}/`, but only `/ru/` is actually used. `/en/*` and `/lt/*` routes redirect to `/ru/*`
3. **Legacy code**: the project root contains many old .htm files and folders (pre-Laravel era)
4. **Carnival costumes**: separate section with special routes and booking logic
5. **No npm on production**: frontend is built locally (`npm run prod`), output in `public/` + `mix-manifest.json`, then committed to git

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

### Project rules:
- **Closures in `web.php` are FORBIDDEN** — they break `route:cache` on production
- **Do NOT use `php artisan serve`** — the site only works through Laragon's Apache at `http://localhost`
- When changing CSS/JS — run `npm run prod` locally and commit `public/js`, `public/css`, `mix-manifest.json`
- The `$v` version number in `app.blade.php` only needs incrementing for vendor files (bootstrap, popper)

### Command Execution
- **Safe to auto-run**: All `git` and `mysql` commands that are read-only or non-destructive (e.g., `git status`, `git log`, `git diff`, `mysql SHOW TABLES`, `mysql SELECT`) should be executed with `SafeToAutoRun: true`.
- **Project commands**: Standard development commands (`npm`, `composer`, `php artisan`) should be auto-run for efficiency.
- **Exceptions**: Only ask for confirmation for mass deletion of files or dropping of fundamental database tables.

