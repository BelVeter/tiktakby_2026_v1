# Search Query Logging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist user-entered search queries (`/{lang}/search`) to a new `search_log` table — time, IP, query text, result count — so they can be analyzed later, including repeat queries from the same visitor.

**Architecture:** One new InnoDB table (`search_log`) plus an inline logging call at the end of `SearchController::search()`, after the result count is already known. No middleware, no new classes — mirrors the existing `mcp_api_log` / `McpAuditLogMiddleware` pattern (schema shape, try/catch-and-log-on-failure) but without the middleware layer, since this feature has exactly one call site.

**Tech Stack:** Laravel 8.75, PHP 7.4 (with `symfony/polyfill-php80`, so `str_contains()` is available — already used elsewhere, e.g. `app/Console/Commands/FetchA1Cdr.php:129`), MariaDB 10.6 (local: Docker Compose services `app`/`db`), PHPUnit feature tests against the real dev DB.

**Spec:** `docs/superpowers/specs/2026-08-13-search-query-logging-design.md` — read it first; it carries the rationale (why inline over middleware, why full IP, why no retention job, why `results_count` was added).

## Global Constraints

- **Only `search_log` is created. No existing table is altered** — `rent_model_web`, `mcp_api_log`, etc. stay untouched.
- **No route closures** — not applicable here (no route changes), but keep in mind for any future edit to `routes/web.php`.
- **Logging must never break the search response.** The `insert()` call is wrapped in `try/catch`; a failure goes to `\Log::error()` and the page still renders — same pattern as `app/Http/Middleware/McpAuditLogMiddleware.php:35-49`.
- **Only non-empty, trimmed queries are logged.** `$text === ''` (from `?search=` or `?search=` with only whitespace, since the controller already does `trim($req->input('search'))`) is never written.
- **Known crawlers are filtered by User-Agent substring**, case-insensitive: `googlebot`, `bingbot`, `yandex`, `ahrefsbot`, `semrushbot`, `mj12bot`, `dotbot`, `petalbot`, `bytespider`, `mail.ru_bot`. This is noise reduction, not a security control.
- **`query` is truncated to 255 chars** (`substr($text, 0, 255)`) before insert — matches the column width, prevents insert failure on pathological input.
- **IP is stored in full** (`$request->ip()`), same as `mcp_api_log.ip` — no masking/hashing.
- **No retention/cleanup job** — table grows unbounded by design; the owner will prune manually if needed. Do not add a scheduled command.
- **`filter.producer` / `filter.age` routes are out of scope** — both share `SearchController` but take structured params (`producer`, `age_from`/`age_to`), not free text, and must NOT write to `search_log`.
- **Testing convention:** `search_log` is InnoDB (`config/database.php:60` has `'engine' => null`, MariaDB's InnoDB default), so `Illuminate\Foundation\Testing\DatabaseTransactions` rolls back every row a test inserts — no manual cleanup needed (same reasoning as `doh_rash_history` in `docs/superpowers/plans/2026-08-10-finance-entries-crud.md`, contrasted there with MyISAM tables that need manual purging).
- **Tests run against the real local dev DB** (not `RefreshDatabase`/sqlite — `phpunit.xml` has the sqlite lines commented out). Run via `docker compose exec app php artisan test <path>`, one file per invocation.
- Branch `feature/search-query-logging` already exists, created from fresh `origin/main`, with the spec committed (`913605c`). Do not create a new branch.

## File Structure

| Action | File | Responsibility |
|--------|------|-----------------|
| Create | `database/migrations/2026_08_13_120000_create_search_log_table.php` | `search_log` table schema |
| Create | `tests/Feature/SearchLogTest.php` | Schema assertions (Task 1) + logging behavior (Task 2) |
| Modify | `app/Http/Controllers/SearchController.php` | Add `DB` import, bot-UA constant, `logSearchQuery()`/`isBotUserAgent()` helpers, call site in `search()` |

---

### Task 1: Migration — `search_log` table

**Files:**
- Create: `database/migrations/2026_08_13_120000_create_search_log_table.php`
- Test: `tests/Feature/SearchLogTest.php` (new)

**Interfaces:**
- Consumes: nothing.
- Produces: table `search_log` with columns `id, created_at, ip, query, results_count, user_agent`; indexes on `created_at` alone and on `(ip, created_at)`. Task 2 writes rows into it via `DB::table('search_log')->insert()`.

- [ ] **Step 1.1: Write the failing schema test**

Create `tests/Feature/SearchLogTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SearchLogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('search_log'));

        foreach (['id', 'created_at', 'ip', 'query', 'results_count', 'user_agent'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('search_log', $column),
                "колонка {$column} отсутствует"
            );
        }
    }

    public function test_ip_and_created_at_composite_index_exists(): void
    {
        $row = DB::selectOne("
            SELECT COUNT(*) AS n
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'search_log'
              AND INDEX_NAME = 'search_log_ip_created_at_index'
        ");
        $this->assertGreaterThan(0, (int) $row->n);
    }
}
```

- [ ] **Step 1.2: Run it and confirm it fails**

```bash
cd /home/dmitry/sites/tiktakby
docker compose exec app php artisan test tests/Feature/SearchLogTest.php
```

Expected: FAIL — `Base table or view not found: ... search_log doesn't exist` (or similar) for both tests.

- [ ] **Step 1.3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSearchLogTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_log')) {
            return;
        }

        Schema::create('search_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('created_at')->index();
            $table->string('ip', 45);
            $table->string('query', 255);
            $table->smallInteger('results_count')->unsigned()->nullable();
            $table->string('user_agent', 255)->nullable();
        });

        Schema::table('search_log', function (Blueprint $table) {
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_log');
    }
}
```

Save as `database/migrations/2026_08_13_120000_create_search_log_table.php`.

- [ ] **Step 1.4: Run the migration**

```bash
docker compose exec app php artisan migrate --path=database/migrations/2026_08_13_120000_create_search_log_table.php
```

Expected: `Migrating: 2026_08_13_120000_create_search_log_table` then `Migrated:  2026_08_13_120000_create_search_log_table` with no errors.

- [ ] **Step 1.5: Run the test again and confirm it passes**

```bash
docker compose exec app php artisan test tests/Feature/SearchLogTest.php
```

Expected: `Tests: 2 passed`.

- [ ] **Step 1.6: Commit**

```bash
git add database/migrations/2026_08_13_120000_create_search_log_table.php tests/Feature/SearchLogTest.php
git commit -m "Add search_log table for search query logging"
```

---

### Task 2: Log searches in `SearchController::search()`

**Files:**
- Modify: `app/Http/Controllers/SearchController.php`
- Modify: `tests/Feature/SearchLogTest.php`

**Interfaces:**
- Consumes: table `search_log` from Task 1 (columns `id, created_at, ip, query, results_count, user_agent`).
- Produces: nothing consumed elsewhere — this is the feature's leaf.

- [ ] **Step 2.1: Write the failing behavior tests**

Append to `tests/Feature/SearchLogTest.php` (inside the existing `SearchLogTest` class, after the two schema tests):

```php
    public function test_nonempty_search_is_logged(): void
    {
        $query = 'коляска-' . uniqid();

        $this->get('/ru/search?search=' . urlencode($query));

        $this->assertDatabaseHas('search_log', ['query' => $query]);
    }

    public function test_empty_search_is_not_logged(): void
    {
        $before = DB::table('search_log')->count();

        $this->get('/ru/search?search=');

        $this->assertSame($before, DB::table('search_log')->count());
    }

    public function test_bot_user_agent_is_not_logged(): void
    {
        $query = 'самокат-' . uniqid();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
        ])->get('/ru/search?search=' . urlencode($query));

        $this->assertDatabaseMissing('search_log', ['query' => $query]);
    }

    public function test_results_count_is_recorded(): void
    {
        $query = 'zzzzzzzzzz-no-such-product-' . uniqid();

        $this->get('/ru/search?search=' . urlencode($query));

        $this->assertDatabaseHas('search_log', [
            'query' => $query,
            'results_count' => 0,
        ]);
    }

    public function test_long_query_is_truncated_and_does_not_fail(): void
    {
        $query = str_repeat('a', 400);

        $response = $this->get('/ru/search?search=' . urlencode($query));

        $response->assertStatus(200);
        $this->assertDatabaseHas('search_log', ['query' => substr($query, 0, 255)]);
    }

    public function test_producer_filter_does_not_write_to_search_log(): void
    {
        $before = DB::table('search_log')->count();

        $this->get('/ru/producer?producer=' . urlencode('TestBrand-' . uniqid()));

        $this->assertSame($before, DB::table('search_log')->count());
    }
```

- [ ] **Step 2.2: Run the tests and confirm the new ones fail**

```bash
docker compose exec app php artisan test tests/Feature/SearchLogTest.php
```

Expected: the 2 schema tests still PASS; `test_nonempty_search_is_logged`, `test_results_count_is_recorded`, and `test_long_query_is_truncated_and_does_not_fail` FAIL (no row written yet). `test_empty_search_is_not_logged`, `test_bot_user_agent_is_not_logged`, and `test_producer_filter_does_not_write_to_search_log` PASS vacuously (nothing is written yet, by anyone) — that's expected and fine; they'll stay meaningful once Task 2's implementation lands.

- [ ] **Step 2.3: Implement the logging in `SearchController`**

In `app/Http/Controllers/SearchController.php`, add the `DB` facade import — insert after the existing `use Illuminate\Http\Request;` line:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
```

Add the bot-marker constant right after the existing `LISTING_LIMIT` constant:

```php
    /** Кол-во карточек на страницу листинга (как в каталоге, MainPage). */
    private const LISTING_LIMIT = 24;

    /** Известные краулеры не считаем "реальными" запросами — снижает шум в аналитике search_log. */
    private const BOT_USER_AGENT_MARKERS = [
        'googlebot', 'bingbot', 'yandex', 'ahrefsbot', 'semrushbot',
        'mj12bot', 'dotbot', 'petalbot', 'bytespider', 'mail.ru_bot',
    ];
```

Add the call site in `search()`, right after `$total` is computed and before the `return view(...)`:

```php
    public function search($lang, Request $req) {
        $text = trim($req->input('search'));
        $p = new CatMainPage();

        $p->setPageTitle('Результаты поиска детских товаров напрокат в Минске:');
        $p->setH1Title('Результаты поиска по запросу: "'.$text.'"');
        $p->addBreadCrumbItem('поиск', '');

        $modelIdArray = ModelWeb::getModelIdsFullTextSearch($text);
        $page = max(1, (int) $req->input('page', 1));
        $total = $this->buildPageModels($p, $modelIdArray, $page);

        $this->logSearchQuery($req, $text, $total);

        // Результаты внутреннего поиска не индексируем (служебная страница),
        // но ссылочный вес пропускаем на карточки товаров.
        return view('search', [
            'p' => $p,
            'robots' => 'noindex,follow',
            'currentPage' => $page,
            'totalPages' => (int) ceil($total / self::LISTING_LIMIT),
            'paginationBase' => '/ru/search?search=' . urlencode($text),
        ]);
    }
```

Add the two private helper methods — place them right after `search()`, before `ageFilter()`:

```php
    private function logSearchQuery(Request $req, string $text, int $resultsCount): void
    {
        if ($text === '' || self::isBotUserAgent($req->userAgent())) {
            return;
        }

        try {
            DB::table('search_log')->insert([
                'created_at'    => now(),
                'ip'            => $req->ip(),
                'query'         => substr($text, 0, 255),
                'results_count' => $resultsCount,
                'user_agent'    => substr($req->userAgent() ?? '', 0, 255),
            ]);
        } catch (\Exception $e) {
            \Log::error('SearchLog failed: ' . $e->getMessage());
        }
    }

    private static function isBotUserAgent(?string $userAgent): bool
    {
        if (!$userAgent) {
            return false;
        }

        $ua = strtolower($userAgent);
        foreach (self::BOT_USER_AGENT_MARKERS as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }
```

- [ ] **Step 2.4: Run the tests and confirm everything passes**

```bash
docker compose exec app php artisan test tests/Feature/SearchLogTest.php
```

Expected: `Tests: 8 passed` (2 from Task 1 + 6 from this task).

- [ ] **Step 2.5: Lint check**

```bash
docker compose exec app php -l app/Http/Controllers/SearchController.php
docker compose exec app php -l tests/Feature/SearchLogTest.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 2.6: Re-run the pre-existing SQL-injection/special-character suite as a regression check**

```bash
docker compose exec app php artisan test tests/Feature/SearchSqlInjectionTest.php
```

Expected: `Tests: 4 passed` — the new logging call must not change how malformed input is handled (it runs after the search itself, and is wrapped in `try/catch`).

- [ ] **Step 2.7: Commit**

```bash
git add app/Http/Controllers/SearchController.php tests/Feature/SearchLogTest.php
git commit -m "Log non-empty, non-bot search queries to search_log"
```

---

## Self-Review Notes

- **Spec coverage:** table shape (Task 1) ✓, inline call site (Task 2) ✓, bot filtering (Task 2) ✓, empty-query skip (Task 2) ✓, truncation to 255 chars (Task 2) ✓, full IP (Task 2, `$req->ip()`) ✓, try/catch-and-log (Task 2) ✓, `results_count` (Task 1 column + Task 2 write) ✓, no retention job (nothing built, matches spec) ✓, `filter.producer`/`filter.age` excluded (Task 2 regression test) ✓.
- **Type/name consistency:** `logSearchQuery(Request $req, string $text, int $resultsCount)` and `isBotUserAgent(?string $userAgent)` are defined once in Task 2 Step 2.3 and used only there — no drift across tasks.
- **No placeholders:** every step has complete, runnable code; no "add validation"-style steps.
