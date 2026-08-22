# CLAUDE.md

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
3. **MCP Analytics API** (`routes/api.php`, `app/Http/Controllers/Mcp/*`) - 63 endpoints (analytics, AI-agent calls, SEO content management, SMS, redirects CRUD) under `/api/mcp/v1/`. Token + geo auth, `{query, data, meta}` envelope, OpenAPI spec at `/api/mcp/v1/openapi.json`.

**MCP API methodology (locked 2026-05-14)** — revenue/office/carnival query contracts and legacy-parity rules live in [app/Http/Controllers/Mcp/CLAUDE.md](app/Http/Controllers/Mcp/CLAUDE.md) (loads automatically when working in that directory).
⚠️ DO NOT sum `/finance/revenue` + `/carnival/revenue` — `/finance/revenue` already includes carnival items as rentals. Double-count risk.

## Essential Commands

### Local Development

> **Note**: use `docker compose` (без дефиса, новый синтаксис). `docker-compose` недоступен на этой машине.
> Laragon (Windows) requires Apache serving the project root as document root — `artisan serve` won't work (see Critical Rule 2).

### Git & Deployment

```bash
# BEFORE handing over a PR link — verify it merges cleanly:
git fetch origin && git merge-tree --write-tree --messages HEAD origin/main
# exit code 0 = no conflicts; non-zero = fix before asking for review

# Production deployment (automated)
# Visit: https://tiktak.by/Deploy.php?key=SECRET_KEY
# Triggers: git reset --hard, composer install, migrate, cache rebuild
```

**⚠️ PRs are SQUASH-merged — never reuse a merged branch.** A squash merge puts a *new* commit on `main`; the branch's own commits never become ancestors of `main`. If you keep committing to that same branch and open a second PR, git re-proposes the already-merged commits and **every file touched by both PRs conflicts**. After a merge, always start the next change from a fresh base:
```bash
git fetch origin && git checkout -b fix/next-thing origin/main
```
To move work already committed to a merged branch: `git cherry-pick <new-commits>` onto the fresh branch.

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

### 7. Тарифы правятся только через `bb\classes\Tariff`

История изменений тарифов захватывается кодом, а не триггерами БД. Сырой
`INSERT`/`UPDATE`/`DELETE` по `rent_tarif_act` мимо класса `Tariff` не попадёт в
`rent_tarif_history` и молча сломает `/pricing/snapshot`. Исключение — разовые миграции
каталога в `database/migrations/`. Проверяется `tests/Feature/TariffWriteGuardTest.php`.

⚠️ `bb/` не использует composer autoload — каждый файл сам объявляет свои зависимости через
`require_once`. `Tariff.php` делает `require_once __DIR__ . '/TariffHistory.php'`, а
`ModelArchive.php` — `require_once __DIR__ . '/Tariff.php'`. Без этой цепочки легаси-страницы,
подключающие `Tariff.php` напрямую, падали с `Fatal error: Class 'bb\classes\TariffHistory'
not found`.

## Project Structure

Full controller/middleware/table inventory lives in `AGENTS.md` — this section covers only what isn't derivable by reading the code.

- ⚠️ **L3 resolves the model by `rent_model_web.page_addr` + `lang` ONLY** (`L3Controller` → `L3Page::getPageByUrlName()` → `ModelWeb::getByUrlNameLangSafe()`). The `{razdel}/{subrazdel}/{category}` segments feed only the breadcrumbs and the recommendations-slider cache key, so **any** prefix returns 200 (`/ru/chush/chush2/chush3/<real-slug>` → 200; 404 only when the model slug itself is unknown). Consequence: moving a model between categories never 404s the old URL — only `<link rel="canonical">` changes, and it is always built from the model's real category.
- **MCP Analytics API** (`GET /api/mcp/v1/*`) — full endpoint catalog in [docs/mcp_server.md](docs/mcp_server.md) and `resources/openapi/mcp-v1.json`. `/finance/pnl` injects a `meta.warnings` entry referring to `D-OPEN-FY2025` whenever the requested period overlaps 2025-01 or later — DO NOT remove this without coordinating with the analytics workspace at `/home/dmitry/Documents/прокат/`

## Deployment Process

`Deploy.php` handles production deployment (triggered via URL with secret key; steps: fetch+reset, composer install, migrate, cache rebuild — see `Deploy.php` for the exact sequence).

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
Real values (DB credentials, MCP token) live in `.env` (gitignored, not committed) — see `.env.example` for the full variable list and defaults. Never hardcode credentials in this file.

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

**Project Notes & Backlog**:
- [docs/prod_pending.md](docs/prod_pending.md) — **что сделать на проде до заливки.** Работа идёт локально, прод не трогаем; сюда складываются прод-действия (порядок влития веток, бэкапы, сверка данных, проверки после деплоя). Читать и выдавать владельцу, когда он просит залить ветку и дать ссылку на PR.
- [docs/db_notes.md](docs/db_notes.md) — DB gotchas + архитектура заявок/звонков. **Читать перед правками `rent_orders`/`rent_orders_arch`/`zvonki`/заявок.** Главная ловушка: позиционные `INSERT ... VALUES` ломаются при добавлении колонок — всегда проверять перед `ALTER TABLE ADD COLUMN`. Там же (п.7) — известный баг: `php artisan migrate` сломан на проде для любой новой миграции (ionCube Loader), обходной путь через прямой SQL.
- [docs/backlog.md](docs/backlog.md) — техдолг и отложенные задачи (вкл. чистку найденного легаси).
- [docs/geo_address_fix.md](docs/geo_address_fix.md) — методика разбора нераспознанных адресов клиентов (`clients_geo.geo_status=2`) для тепловой карты `bb/geo_heatmap.php`: словарь минских сокращений улиц, AI-нормализация + проверка через Google Geocoding API, когда включать Яндекс-фоллбек, когда эскалировать на человека.

**Working preference (owner):** владелец писал базу сам ~10 лет (самоучка), есть легаси. При каждом удобном случае предлагать **мелкие безопасные (low-risk) правки** кода, который и так трогаем; массовый рефакторинг легаси — только по явному запросу.
