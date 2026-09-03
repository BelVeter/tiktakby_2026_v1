# Трекинг использования CRM (bb/) — план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Логировать заходы сотрудников на страницы легаси-админки `bb/`, дать владельцу отчёт «кто/что/как часто» + «что не используют», и открыть те же данные ИИ-агенту через MCP API.

**Architecture:** Один трекер (`bb/classes/PageVisitTracker.php`) подключается PHP-движком перед КАЖДЫМ `.php`-файлом в `bb/` через `auto_prepend_file` в `bb/.htaccess` — без правки существующих ~176 файлов страниц. Пишет в новую таблицу `bb_page_visits` через `mysqli` (легаси-паттерн `bb/`). Общий класс `bb/classes/PageVisitCatalog.php` (composer-autoloadable, `bb\classes\` psr-4) отвечает на вопрос «это реальная страница или техническая/библиотечная» — его переиспользуют трекер, страница отчёта `bb/page_track.php` и Laravel-контроллер `StaffController`.

**Tech Stack:** Laravel 8.75 / PHP 7.4, MariaDB 10.6, raw `mysqli` в `bb/`, Laravel `DB`-фасад в MCP API, PHPUnit 9.

## Global Constraints

- Спека: `docs/superpowers/specs/2026-09-03-crm-usage-tracking-design.md` — источник истины по требованиям.
- PHP 7.4 на проде (`alt-php74`) — не использовать `str_starts_with`/`str_ends_with`/`??=` с null-safe операторами и другие PHP8-only конструкции нигде в `bb/`-коде.
- `bb/` не использует composer-автозагрузку — каждый `bb/`-скрипт объявляет свои зависимости через `require_once` (см. `CLAUDE.md`).
- Раздельный доступ к БД: `bb/`-код — только `mysqli` (`\bb\Db::getInstance()->getConnection()`); Laravel-код (`app/`) — только `DB`-фасад/Eloquent. Никогда не смешивать в одном файле.
- Тесты запускаются против реальной (не sqlite) dev-копии БД без автоматических транзакций/refresh (`tests/TestCase.php` не подключает `RefreshDatabase`) — любые тестовые фикстуры обязаны сами убирать за собой (`setUp`/`tearDown`), используя заведомо несуществующий `user_id`, чтобы не смешиваться с реальными данными.
- Ветка: `feature/crm-usage-tracking`, создана свежей от `origin/main` (проект — squash-merge, см. `CLAUDE.md`).
- Прод не трогаем — вся работа только локально/в Docker.

## Отклонение от спеки (зафиксировано здесь, спека будет обновлена по факту)

Спека предполагала, что классы/модели физически не лежат в `bb/*.php` верхнего уровня и потому не попадут в скан `PageVisitCatalog::listTrackablePages()`. Это неверно: `bb/tovar.php`, `bb/Db.php`, `bb/client.php` и ещё 14 файлов — чистые классы (`namespace bb; class X {...}`), лежащие прямо в `bb/*.php`, никогда не открываются по URL. Обнаружено эмпирически 03.09.2026 (см. Task 2) — признак «объявляет `class` И нигде не вызывает `session_start()`» верен для всех настоящих файлов-библиотек и ложен для всех настоящих страниц в этой кодовой базе. `PageVisitCatalog` получает точный список этих 17 файлов константой.

---

### Task 1: Миграция `bb_page_visits`

**Files:**
- Create: `database/migrations/2026_09_03_120000_create_bb_page_visits_table.php`

**Interfaces:**
- Produces: таблица `bb_page_visits(id, user_id, page, visited_at)` с индексом `idx_bpv_user_page_time` на `(user_id, page, visited_at)`. Используется всеми последующими тасками.

- [ ] **Step 1: Написать миграцию**

```php
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
```

- [ ] **Step 2: Применить в Docker**

Run: `docker compose exec -T app php artisan migrate`
Expected: `Migrating: 2026_09_03_120000_create_bb_page_visits_table` → `Migrated:  ...` (batch N).

- [ ] **Step 3: Проверить структуру**

Run: `docker compose exec -T app php artisan tinker --execute="print_r(Schema::getColumnListing('bb_page_visits'));"`
Expected: `['id', 'user_id', 'page', 'visited_at']`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_09_03_120000_create_bb_page_visits_table.php
git commit -m "feat(crm-tracking): миграция bb_page_visits"
```

---

### Task 2: `bb/classes/PageVisitCatalog.php` (TDD)

**Files:**
- Create: `bb/classes/PageVisitCatalog.php`
- Test: `tests/Unit/PageVisitCatalogTest.php`

**Interfaces:**
- Produces: `bb\classes\PageVisitCatalog::isTechnical(string $filename): bool`, `bb\classes\PageVisitCatalog::listTrackablePages(): array` (отсортированный список basename-ов). Потребляется Task 3 (`PageVisitTracker.php`), Task 5 (`page_track.php`), Task 6 (`StaffController.php`).

- [ ] **Step 1: Написать падающий тест**

`tests/Unit/PageVisitCatalogTest.php`:

```php
<?php

namespace Tests\Unit;

use bb\classes\PageVisitCatalog;
use PHPUnit\Framework\TestCase;

class PageVisitCatalogTest extends TestCase
{
    public function test_ajax_prefix_is_technical(): void
    {
        $this->assertTrue(PageVisitCatalog::isTechnical('ajax_client_check.php'));
        $this->assertTrue(PageVisitCatalog::isTechnical('ajax_model_suggest.php'));
    }

    public function test_api_suffix_is_technical(): void
    {
        $this->assertTrue(PageVisitCatalog::isTechnical('redirects_api.php'));
        $this->assertTrue(PageVisitCatalog::isTechnical('a1_calls_api.php'));
    }

    public function test_badge_suffix_is_technical(): void
    {
        $this->assertTrue(PageVisitCatalog::isTechnical('bb_nav_badge.php'));
    }

    public function test_known_library_files_are_technical(): void
    {
        $this->assertTrue(PageVisitCatalog::isTechnical('Db.php'));
        $this->assertTrue(PageVisitCatalog::isTechnical('tovar.php'));
        $this->assertTrue(PageVisitCatalog::isTechnical('client.php'));
    }

    public function test_real_page_is_not_technical(): void
    {
        $this->assertFalse(PageVisitCatalog::isTechnical('deals.php'));
        $this->assertFalse(PageVisitCatalog::isTechnical('tovar_new.php'));
        $this->assertFalse(PageVisitCatalog::isTechnical('kr_baza_new.php'));
    }

    public function test_list_trackable_pages_excludes_technical_and_library_files(): void
    {
        $pages = PageVisitCatalog::listTrackablePages();
        $this->assertContains('deals.php', $pages);
        $this->assertNotContains('ajax_client_check.php', $pages);
        $this->assertNotContains('bb_nav_badge.php', $pages);
        $this->assertNotContains('redirects_api.php', $pages);
        $this->assertNotContains('Db.php', $pages);
        $this->assertNotContains('tovar.php', $pages);
    }

    public function test_list_trackable_pages_is_sorted_and_unique(): void
    {
        $pages = PageVisitCatalog::listTrackablePages();
        $sorted = $pages;
        sort($sorted);
        $this->assertSame($sorted, $pages);
        $this->assertSame(count($pages), count(array_unique($pages)));
    }
}
```

- [ ] **Step 2: Запустить и убедиться, что падает**

Run: `docker compose exec -T app vendor/bin/phpunit tests/Unit/PageVisitCatalogTest.php`
Expected: FAIL — `Class "bb\classes\PageVisitCatalog" not found`.

- [ ] **Step 3: Реализовать класс**

`bb/classes/PageVisitCatalog.php`:

```php
<?php

namespace bb\classes;

/**
 * Какие файлы bb/*.php — реальные страницы, а какие технические (ajax/api/badge
 * запросы) или чистые классы-библиотеки (namespace bb; class X — подключаются
 * через require_once другими файлами, никогда не открываются напрямую по URL).
 *
 * Используется в трёх местах: PageVisitTracker.php (что не логировать),
 * bb/page_track.php (отчёт по страницам) и Mcp/StaffController.php (тот же
 * список для ИИ-агента). bb\classes\ объявлен в composer.json psr-4, поэтому
 * из Laravel класс доступен через обычный `use`; из голых bb/-скриптов —
 * через require_once (в bb/ автозагрузка composer не используется).
 */
class PageVisitCatalog
{
    /**
     * Топ-уровневые bb/*.php файлы, которые физически являются классами
     * (namespace bb; class X {...}), а не страницами. Выверено вручную
     * 03.09.2026 по признаку «объявляет class И нигде не вызывает
     * session_start()» — верно для всех настоящих страниц bb/. Новый файл
     * такого рода придётся добавить сюда руками.
     */
    private const LIBRARY_FILES = [
        'base_lowercase.php',
        'client.php',
        'Db.php',
        'DealRow.php',
        'Delivery.php',
        'DeliveryPage.php',
        'DohRash.php',
        'KarnavalBron.php',
        'Kassa.php',
        'KBron.php',
        'KBronForm.php',
        'Office.php',
        'Payment.php',
        'Schedule.php',
        'Signature.php',
        'tovar.php',
        'User.php',
    ];

    /**
     * true — технический/служебный файл: не считается «страницей» ни для
     * логирования, ни для отчёта/API.
     */
    public static function isTechnical(string $filename): bool
    {
        if (strpos($filename, 'ajax_') === 0) {
            return true;
        }
        foreach (['_api.php', '_badge.php'] as $suffix) {
            if (substr($filename, -strlen($suffix)) === $suffix) {
                return true;
            }
        }
        return in_array($filename, self::LIBRARY_FILES, true);
    }

    /**
     * Отсортированный список реальных страниц bb/*.php (верхний уровень, без
     * подкаталогов) минус технические/библиотечные файлы.
     *
     * @return string[]
     */
    public static function listTrackablePages(): array
    {
        $files = glob(__DIR__ . '/../*.php') ?: [];
        $pages = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!self::isTechnical($name)) {
                $pages[] = $name;
            }
        }
        sort($pages);
        return $pages;
    }
}
```

- [ ] **Step 4: Запустить и убедиться, что проходит**

Run: `docker compose exec -T app vendor/bin/phpunit tests/Unit/PageVisitCatalogTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add bb/classes/PageVisitCatalog.php tests/Unit/PageVisitCatalogTest.php
git commit -m "feat(crm-tracking): PageVisitCatalog — каталог реальных страниц bb/"
```

---

### Task 3: `bb/classes/PageVisitTracker.php`

**Files:**
- Create: `bb/classes/PageVisitTracker.php`

**Interfaces:**
- Consumes: `bb\classes\PageVisitCatalog::isTechnical()` (Task 2), `\bb\Db::getInstance()->getConnection()` (существующий `bb/Db.php`), таблица `bb_page_visits` (Task 1).
- Produces: скрипт, безопасный для подключения как `auto_prepend_file` (Task 4) — не выводит ничего, не бросает наружу исключений.

- [ ] **Step 1: Написать трекер**

```php
<?php
// auto_prepend_file — подключается PHP-движком перед ЛЮБЫМ .php в bb/
// (см. bb/.htaccess). Логирует заход залогиненного сотрудника на реальную
// (не техническую) страницу в bb_page_visits. Никогда не должен ронять
// страницу, которую открывает сотрудник — ошибки логирования проглатываются.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    return;
}

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
if ($scriptName === '') {
    return;
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    return;
}

require_once __DIR__ . '/PageVisitCatalog.php';

if (\bb\classes\PageVisitCatalog::isTechnical($scriptName)) {
    return;
}

require_once __DIR__ . '/../Db.php';

try {
    $mysqli = \bb\Db::getInstance()->getConnection();
    $userId = (int) $_SESSION['user_id'];
    $page   = $mysqli->real_escape_string($scriptName);
    $mysqli->query("INSERT INTO bb_page_visits (user_id, page, visited_at) VALUES ({$userId}, '{$page}', NOW())");
} catch (\Throwable $e) {
    error_log('PageVisitTracker: ' . $e->getMessage());
}
```

- [ ] **Step 2: Проверить логику напрямую в Docker (без Apache/сессии браузера)**

Run:
```bash
docker compose exec -T app php -r '
$_SESSION = ["user_id" => 999001];
$_SERVER["SCRIPT_NAME"] = "/bb/deals.php";
$_SERVER["DOCUMENT_ROOT"] = "/var/www/html";
require "/var/www/html/bb/classes/PageVisitTracker.php";
'
docker compose exec -T app php artisan tinker --execute="echo DB::table(\"bb_page_visits\")->where(\"user_id\", 999001)->count();"
```
Expected: второй вызов печатает `1`. Затем удалить тестовую строку: `docker compose exec -T app php artisan tinker --execute="DB::table('bb_page_visits')->where('user_id', 999001)->delete();"`

- [ ] **Step 3: Проверить, что технический файл не логируется**

Run:
```bash
docker compose exec -T app php -r '
$_SESSION = ["user_id" => 999001];
$_SERVER["SCRIPT_NAME"] = "/bb/ajax_client_check.php";
require "/var/www/html/bb/classes/PageVisitTracker.php";
'
docker compose exec -T app php artisan tinker --execute="echo DB::table(\"bb_page_visits\")->where(\"user_id\", 999001)->count();"
```
Expected: `0`.

- [ ] **Step 4: Commit**

```bash
git add bb/classes/PageVisitTracker.php
git commit -m "feat(crm-tracking): PageVisitTracker — auto_prepend логгер заходов"
```

---

### Task 4: `bb/.htaccess` — включить `auto_prepend_file`

**Files:**
- Modify: `bb/.htaccess`

**Interfaces:**
- Consumes: `bb/classes/PageVisitTracker.php` (Task 3).

- [ ] **Step 1: Временно проверить, что Apache вообще подхватывает директиву**

Добавить в начало `PageVisitTracker.php` временную строку `echo 'PREPEND-OK';` (после `<?php`), прописать в `bb/.htaccess`:
```
php_flag display_errors Off
php_value auto_prepend_file /var/www/html/bb/classes/PageVisitTracker.php
```
Run: `curl -s http://localhost/bb/deals.php | head -c 20`
Expected: вывод начинается с `PREPEND-OK` (доказывает, что `.htaccess`-директива реально подключает файл на реальный HTTP-запрос, не только в моих ручных PHP-вызовах из Task 3). Убрать временный `echo` из `PageVisitTracker.php` сразу после проверки.

- [ ] **Step 2: Финальный `.htaccess`**

```
php_flag display_errors Off
php_value auto_prepend_file /var/www/html/bb/classes/PageVisitTracker.php
# Прод (cPanel/hoster.by): раскомментировать строку с реальным абсолютным
# путём (узнать через `pwd` в SSH-сессии на проде — обычно
# /home/<cpanel-логин>/public_html/bb/classes/PageVisitTracker.php) и
# закомментировать строку выше с Docker-путём. См. docs/prod_pending.md.
#php_value auto_prepend_file /home/CPANEL_USER/public_html/bb/classes/PageVisitTracker.php
```

- [ ] **Step 3: Commit**

```bash
git add bb/.htaccess
git commit -m "feat(crm-tracking): включить auto_prepend_file трекера в bb/.htaccess"
```

---

### Task 5: `bb/page_track.php` — страница отчёта

**Files:**
- Create: `bb/page_track.php`

**Interfaces:**
- Consumes: `bb/auth_guard.php`, `bb\models\User::getCurrentUser()->isDima()`, `bb\models\User::getUserById()`/`getShortName()`, `bb\classes\PageVisitCatalog::listTrackablePages()` (Task 2), `bb_page_visits` (Task 1).

- [ ] **Step 1: Написать страницу**

```php
<?php

use bb\classes\PageVisitCatalog;
use bb\models\User;

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/classes/PageVisitCatalog.php';

if (!User::getCurrentUser()->isDima()) {
    die('
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title>Нет доступа</title></head>
    <body>Эта страница доступна только владельцу.</body></html>');
}

$mysqli = \bb\Db::getInstance()->getConnection();

$i_from_date = (isset($_GET['i_from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['i_from_date']))
    ? $_GET['i_from_date']
    : date('Y-m-d', strtotime('-30 days'));
$i_to_date = (isset($_GET['i_to_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['i_to_date']))
    ? $_GET['i_to_date']
    : date('Y-m-d');

$from_dt = $i_from_date . ' 00:00:00';
$to_dt   = $i_to_date . ' 23:59:59';

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>BB: Использование CRM</title>
<link href="stile.css" rel="stylesheet" type="text/css" />
</head>
<body>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>

<form name="srch_form" method="get" id="srch_form" action="page_track.php">
	За период:
		c <input type="date" name="i_from_date" value="' . htmlspecialchars($i_from_date) . '" />
		по <input type="date" name="i_to_date" value="' . htmlspecialchars($i_to_date) . '" />
		<input type="submit" value="показать" /><br />
</form>

<h3>По сотрудникам</h3>
<table border="1" cellspacing="0">
<tr><th>сотрудник</th><th>заходов</th><th>уникальных страниц</th><th>последний визит</th></tr>
';

$q_users = "
    SELECT bpv.user_id, COUNT(*) AS visits, COUNT(DISTINCT bpv.page) AS distinct_pages, MAX(bpv.visited_at) AS last_visit
    FROM bb_page_visits bpv
    WHERE bpv.visited_at BETWEEN '{$from_dt}' AND '{$to_dt}'
    GROUP BY bpv.user_id
    ORDER BY visits DESC
";
$result_users = $mysqli->query($q_users);
if (!$result_users) {
    die('Сбой при доступе к базе данных: ' . $q_users . ' (' . $mysqli->error . ')');
}
while ($row = $result_users->fetch_assoc()) {
    $u = User::getUserById($row['user_id']);
    echo '
    <tr>
        <td>' . htmlspecialchars($u ? $u->getShortName() : ('#' . $row['user_id'])) . '</td>
        <td>' . (int) $row['visits'] . '</td>
        <td>' . (int) $row['distinct_pages'] . '</td>
        <td>' . htmlspecialchars($row['last_visit']) . '</td>
    </tr>';
}

echo '
</table>

<h3>По страницам (сначала неиспользуемые)</h3>
<table border="1" cellspacing="0">
<tr><th>страница</th><th>заходов</th><th>уникальных сотрудников</th><th>последний визит</th></tr>
';

$q_pages = "
    SELECT bpv.page, COUNT(*) AS visits, COUNT(DISTINCT bpv.user_id) AS distinct_users, MAX(bpv.visited_at) AS last_visit
    FROM bb_page_visits bpv
    WHERE bpv.visited_at BETWEEN '{$from_dt}' AND '{$to_dt}'
    GROUP BY bpv.page
";
$result_pages = $mysqli->query($q_pages);
if (!$result_pages) {
    die('Сбой при доступе к базе данных: ' . $q_pages . ' (' . $mysqli->error . ')');
}
$stats_by_page = [];
while ($row = $result_pages->fetch_assoc()) {
    $stats_by_page[$row['page']] = $row;
}

$rows = [];
foreach (PageVisitCatalog::listTrackablePages() as $page) {
    $stat = $stats_by_page[$page] ?? null;
    $rows[] = [
        'page'           => $page,
        'visits'         => $stat ? (int) $stat['visits'] : 0,
        'distinct_users' => $stat ? (int) $stat['distinct_users'] : 0,
        'last_visit'     => $stat ? $stat['last_visit'] : null,
    ];
}
usort($rows, function ($a, $b) { return $a['visits'] <=> $b['visits']; });

foreach ($rows as $row) {
    echo '
    <tr>
        <td>' . htmlspecialchars($row['page']) . '</td>
        <td>' . $row['visits'] . '</td>
        <td>' . $row['distinct_users'] . '</td>
        <td>' . htmlspecialchars($row['last_visit'] ?? '—') . '</td>
    </tr>';
}

echo '
</table>
</body></html>
';
```

- [ ] **Step 2: Проверить в Docker вручную**

Открыть `http://localhost/bb/page_track.php` в браузере под пользователем `isDima()` (id 3) — обе таблицы рендерятся, фильтр по датам работает при повторной отправке формы. Проверить с другим пользователем — видно «Эта страница доступна только владельцу.».

- [ ] **Step 3: Commit**

```bash
git add bb/page_track.php
git commit -m "feat(crm-tracking): page_track.php — отчёт по использованию CRM"
```

---

### Task 6: `app/Http/Controllers/Mcp/StaffController.php` + маршруты (TDD)

**Files:**
- Create: `app/Http/Controllers/Mcp/StaffController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Mcp/StaffTest.php`

**Interfaces:**
- Consumes: `bb\classes\PageVisitCatalog::listTrackablePages()` (Task 2), таблица `bb_page_visits` (Task 1), `BaseController::envelope()`.
- Produces: `GET /api/mcp/v1/staff/page-visits/by-user`, `GET /api/mcp/v1/staff/page-visits/by-page`.

- [ ] **Step 1: Написать падающий тест**

`tests/Feature/Mcp/StaffTest.php`:

```php
<?php

namespace Tests\Feature\Mcp;

use Illuminate\Support\Facades\DB;

class StaffTest extends McpTestCase
{
    private const TEST_USER_ID = 999001;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
        parent::tearDown();
    }

    private function cleanupFixtures(): void
    {
        DB::table('bb_page_visits')->where('user_id', self::TEST_USER_ID)->delete();
    }

    private function seed(string $page, string $visitedAt): void
    {
        DB::table('bb_page_visits')->insert([
            'user_id'    => self::TEST_USER_ID,
            'page'       => $page,
            'visited_at' => $visitedAt,
        ]);
    }

    public function test_by_user_envelope_and_aggregation(): void
    {
        $this->seed('deals.php', '2026-08-01 10:00:00');
        $this->seed('deals.php', '2026-08-02 11:00:00');
        $this->seed('tovar_new.php', '2026-08-03 12:00:00');

        $r = $this->mcp('staff/page-visits/by-user', ['from' => '2026-08-01', 'to' => '2026-08-31']);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [[
            'user_id', 'user_name', 'visits', 'distinct_pages', 'last_visit_at',
        ]]]);

        $row = collect($r->json('data'))->firstWhere('user_id', self::TEST_USER_ID);
        $this->assertNotNull($row, 'seeded test user must appear in by-user response');
        $this->assertSame(3, $row['visits']);
        $this->assertSame(2, $row['distinct_pages']);
        $this->assertSame('2026-08-03 12:00:00', $row['last_visit_at']);
    }

    public function test_by_user_page_filter_narrows_results(): void
    {
        $this->seed('deals.php', '2026-08-01 10:00:00');
        $this->seed('tovar_new.php', '2026-08-01 11:00:00');

        $r = $this->mcp('staff/page-visits/by-user', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'page' => 'deals.php',
        ]);
        $row = collect($r->json('data'))->firstWhere('user_id', self::TEST_USER_ID);
        $this->assertSame(1, $row['visits']);
    }

    public function test_by_page_includes_zero_visit_pages(): void
    {
        $r = $this->mcp('staff/page-visits/by-page', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'user_id' => self::TEST_USER_ID,
        ]);
        $this->assertEnvelope($r);
        $r->assertJsonStructure(['data' => [[
            'page', 'visits', 'distinct_users', 'last_visit_at',
        ]]]);

        $data = $r->json('data');
        $this->assertGreaterThan(50, count($data), 'catalog should list most real bb/ pages');

        $dealsRow = collect($data)->firstWhere('page', 'deals.php');
        $this->assertNotNull($dealsRow);
        $this->assertSame(0, $dealsRow['visits'], 'no visits from this test user in this period');
    }

    public function test_by_page_excludes_technical_files(): void
    {
        $r = $this->mcp('staff/page-visits/by-page', ['from' => '2026-08-01', 'to' => '2026-08-31']);
        $pages = array_column($r->json('data'), 'page');
        $this->assertNotContains('ajax_client_check.php', $pages);
        $this->assertNotContains('bb_nav_badge.php', $pages);
        $this->assertNotContains('Db.php', $pages);
        $this->assertContains('deals.php', $pages);
    }

    public function test_by_page_reflects_seeded_visits_and_sorts_ascending(): void
    {
        $this->seed('deals.php', '2026-08-05 09:00:00');

        $r = $this->mcp('staff/page-visits/by-page', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'user_id' => self::TEST_USER_ID,
        ]);
        $data = $r->json('data');

        $visits = array_column($data, 'visits');
        $sorted = $visits;
        sort($sorted);
        $this->assertSame($sorted, $visits, 'by-page must sort ascending by visits');

        $dealsRow = collect($data)->firstWhere('page', 'deals.php');
        $this->assertSame(1, $dealsRow['visits']);
        $this->assertSame(1, $dealsRow['distinct_users']);
    }

    public function test_staff_endpoints_require_token(): void
    {
        $this->assertRequiresToken('staff/page-visits/by-user');
        $this->assertRequiresToken('staff/page-visits/by-page');
    }
}
```

- [ ] **Step 2: Запустить и убедиться, что падает**

Run: `docker compose exec -T app vendor/bin/phpunit tests/Feature/Mcp/StaffTest.php`
Expected: FAIL — 404 (маршрут не существует).

- [ ] **Step 3: Реализовать контроллер**

`app/Http/Controllers/Mcp/StaffController.php`:

```php
<?php

namespace App\Http\Controllers\Mcp;

use bb\classes\PageVisitCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/mcp/v1/staff/page-visits/by-user
 * GET /api/mcp/v1/staff/page-visits/by-page
 *
 * Reads bb_page_visits, populated by bb/classes/PageVisitTracker.php
 * (auto_prepend_file on every bb/ admin request). Mirrors the two tables of
 * the owner-only bb/page_track.php report so an AI agent can read the same
 * data. See docs/superpowers/specs/2026-09-03-crm-usage-tracking-design.md.
 */
class StaffController extends BaseController
{
    public function byUser(Request $request): JsonResponse
    {
        $from = $request->get('from', date('Y-m-d', strtotime('-30 days')));
        $to   = $request->get('to', date('Y-m-d'));
        $page = $request->get('page');

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to . ' 23:59:59';

        $query = DB::table('bb_page_visits')
            ->leftJoin('logpass', 'logpass.logpass_id', '=', 'bb_page_visits.user_id')
            ->whereBetween('bb_page_visits.visited_at', [$fromDt, $toDt]);

        if ($page) {
            $query->where('bb_page_visits.page', $page);
        }

        $rows = $query
            ->select(
                'bb_page_visits.user_id',
                DB::raw("COALESCE(logpass.lp_fio, CONCAT('#', bb_page_visits.user_id)) as user_name"),
                DB::raw('COUNT(*) as visits'),
                DB::raw('COUNT(DISTINCT bb_page_visits.page) as distinct_pages'),
                DB::raw('MAX(bb_page_visits.visited_at) as last_visit_at')
            )
            ->groupBy('bb_page_visits.user_id', 'logpass.lp_fio')
            ->orderByDesc('visits')
            ->get();

        $data = $rows->map(fn ($r) => [
            'user_id'        => (int) $r->user_id,
            'user_name'      => $r->user_name,
            'visits'         => (int) $r->visits,
            'distinct_pages' => (int) $r->distinct_pages,
            'last_visit_at'  => $r->last_visit_at,
        ])->values()->all();

        return $this->envelope(['from' => $from, 'to' => $to, 'page' => $page], $data);
    }

    public function byPage(Request $request): JsonResponse
    {
        $from   = $request->get('from', date('Y-m-d', strtotime('-30 days')));
        $to     = $request->get('to', date('Y-m-d'));
        $userId = $request->get('user_id');

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to . ' 23:59:59';

        $visitsQuery = DB::table('bb_page_visits')->whereBetween('visited_at', [$fromDt, $toDt]);
        if ($userId) {
            $visitsQuery->where('user_id', (int) $userId);
        }

        $visits = $visitsQuery
            ->select(
                'page',
                DB::raw('COUNT(*) as visits'),
                DB::raw('COUNT(DISTINCT user_id) as distinct_users'),
                DB::raw('MAX(visited_at) as last_visit_at')
            )
            ->groupBy('page')
            ->get()
            ->keyBy('page');

        $data = collect(PageVisitCatalog::listTrackablePages())
            ->map(function (string $page) use ($visits) {
                $row = $visits->get($page);
                return [
                    'page'           => $page,
                    'visits'         => $row ? (int) $row->visits : 0,
                    'distinct_users' => $row ? (int) $row->distinct_users : 0,
                    'last_visit_at'  => $row->last_visit_at ?? null,
                ];
            })
            ->sortBy('visits')
            ->values()
            ->all();

        return $this->envelope(['from' => $from, 'to' => $to, 'user_id' => $userId], $data);
    }
}
```

`routes/api.php` — добавить `use App\Http\Controllers\Mcp\StaffController;` к списку use-выражений и, перед закрывающим `});` группы `mcp/v1`, зарегистрировать:

```php
        // Staff CRM usage tracking (2026-09-03)
        Route::get('staff/page-visits/by-user', [StaffController::class, 'byUser'])->name('staff.page-visits.by-user');
        Route::get('staff/page-visits/by-page', [StaffController::class, 'byPage'])->name('staff.page-visits.by-page');
```

- [ ] **Step 4: Запустить и убедиться, что проходит**

Run: `docker compose exec -T app vendor/bin/phpunit tests/Feature/Mcp/StaffTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Mcp/StaffController.php routes/api.php tests/Feature/Mcp/StaffTest.php
git commit -m "feat(crm-tracking): MCP API staff/page-visits/{by-user,by-page}"
```

---

### Task 7: Документация — `docs/mcp_server.md` + OpenAPI spec + parity-тест

**Files:**
- Modify: `docs/mcp_server.md`
- Modify: `resources/openapi/mcp-v1.json`
- Modify: `tests/Feature/Mcp/SpecRuntimeParityTest.php`

**Interfaces:**
- Consumes: маршруты и поля ответа из Task 6.

- [ ] **Step 1: `docs/mcp_server.md`** — добавить новую группу `Staff` в конец таблицы «Endpoint catalog» (после группы `Redirects`, перед `## Ledger entry model`):

```markdown
| Staff  | `GET /staff/page-visits/by-user` | Per-employee bb/ admin panel usage: visit count, distinct pages, last visit. Filters: `from`, `to`, `page`. Backed by `bb_page_visits`, written by `bb/classes/PageVisitTracker.php` on every non-technical bb/ page request. |
|        | `GET /staff/page-visits/by-page` | Per bb/ page usage, sorted ascending by visits — pages nobody opens surface first (`visits: 0`). Filters: `from`, `to`, `user_id`. |
```

- [ ] **Step 2: `resources/openapi/mcp-v1.json`**

Bump `"version": "2.5.0"` → `"2.6.0"` (line 5, `info.version`).

Добавить два path-а в `"paths"` (рядом с любым существующим, порядок не важен):

```json
"/staff/page-visits/by-user": {
    "get": {
        "operationId": "tiktak_staff_page_visits_by_user",
        "summary": "Per-employee bb/ admin panel usage: visit count, distinct pages, last visit. Backed by bb_page_visits, written by bb/classes/PageVisitTracker.php on every non-technical bb/ page request.",
        "parameters": [
            { "$ref": "#/components/parameters/from" },
            { "$ref": "#/components/parameters/to" },
            {
                "in": "query",
                "name": "page",
                "schema": { "type": "string" },
                "description": "Exact bb/ page filename (e.g. 'deals.php') — narrow to who visited that one page."
            }
        ],
        "responses": {
            "200": { "$ref": "#/components/responses/StaffPageVisitsByUserEnvelope" }
        }
    }
},
"/staff/page-visits/by-page": {
    "get": {
        "operationId": "tiktak_staff_page_visits_by_page",
        "summary": "Per bb/ admin page usage: visit count, distinct employees, last visit. Includes every real page from the catalog with visits=0 when nobody opened it in the period — surfaces unused/dead admin pages. Sorted ascending by visits.",
        "parameters": [
            { "$ref": "#/components/parameters/from" },
            { "$ref": "#/components/parameters/to" },
            {
                "in": "query",
                "name": "user_id",
                "schema": { "type": "integer" },
                "description": "Restrict to one employee's visits (logpass.logpass_id)."
            }
        ],
        "responses": {
            "200": { "$ref": "#/components/responses/StaffPageVisitsByPageEnvelope" }
        }
    }
}
```

Добавить два row-schema в `"components"."schemas"`:

```json
"StaffPageVisitsByUserRow": {
    "type": "object",
    "properties": {
        "user_id": { "type": "integer" },
        "user_name": { "type": "string" },
        "visits": { "type": "integer" },
        "distinct_pages": { "type": "integer" },
        "last_visit_at": { "type": ["string", "null"], "format": "date-time" }
    }
},
"StaffPageVisitsByPageRow": {
    "type": "object",
    "properties": {
        "page": { "type": "string" },
        "visits": { "type": "integer" },
        "distinct_users": { "type": "integer" },
        "last_visit_at": { "type": ["string", "null"], "format": "date-time" }
    }
}
```

Добавить два envelope-компонента в `"components"."responses"` (по образцу `LocationsPerformanceEnvelope`):

```json
"StaffPageVisitsByUserEnvelope": {
    "description": "Staff page-visits by-user envelope.",
    "content": {
        "application/json": {
            "schema": {
                "allOf": [
                    { "$ref": "#/components/schemas/Envelope" },
                    { "type": "object", "properties": { "data": {
                        "type": "array", "items": { "$ref": "#/components/schemas/StaffPageVisitsByUserRow" }
                    } } }
                ]
            }
        }
    }
},
"StaffPageVisitsByPageEnvelope": {
    "description": "Staff page-visits by-page envelope.",
    "content": {
        "application/json": {
            "schema": {
                "allOf": [
                    { "$ref": "#/components/schemas/Envelope" },
                    { "type": "object", "properties": { "data": {
                        "type": "array", "items": { "$ref": "#/components/schemas/StaffPageVisitsByPageRow" }
                    } } }
                ]
            }
        }
    }
}
```

Validate JSON syntax: `docker compose exec -T app php -r "json_decode(file_get_contents('resources/openapi/mcp-v1.json'), true) !== null ? print('OK') : print('INVALID');"`
Expected: `OK`.

- [ ] **Step 3: `tests/Feature/Mcp/SpecRuntimeParityTest.php`**

Обновить `test_spec_version_matches()`: `'2.5.0'` → `'2.6.0'`.

Добавить в `endpointMatrix()` две строки:

```php
            // Staff (CRM usage tracking, 2026-09-03)
            'staff/page-visits/by-user'    => ['staff/page-visits/by-user',    [],                                                                            'array_row', null],
            'staff/page-visits/by-page'    => ['staff/page-visits/by-page',    [],                                                                            'array_row', null],
```

- [ ] **Step 4: Запустить весь Mcp-набор тестов**

Run: `docker compose exec -T app php artisan test --filter=Mcp`
Expected: те же 441 passed + 6 новых из `StaffTest` + `test_runtime_response_keys_are_documented_in_spec` проходит для двух новых записей `endpointMatrix` (для `by-page` — реально, для `by-user` — либо реально, либо `markTestSkipped` при пустой `bb_page_visits`, оба варианта — не failure). Тот же 1 pre-existing failure (`CategoriesTest` seasonality) — не мой, не трогаю.

- [ ] **Step 5: Commit**

```bash
git add docs/mcp_server.md resources/openapi/mcp-v1.json tests/Feature/Mcp/SpecRuntimeParityTest.php
git commit -m "docs(crm-tracking): описать staff/page-visits/* в mcp_server.md и OpenAPI-спеке"
```

---

### Task 8: `docs/prod_pending.md` — прод-чеклист

**Files:**
- Modify: `docs/prod_pending.md`

- [ ] **Step 1: Добавить ветку в «Очередь веток»**

Новая строка в таблице (независима от веток 1-3):
```markdown
| 4 | `feature/crm-usage-tracking` | Трекинг использования bb/: кто на какие страницы заходит и как часто + чего не открывает никто. `auto_prepend_file`-трекер, таблица `bb_page_visits`, отчёт `page_track.php` (только владелец), MCP API `staff/page-visits/{by-user,by-page}` для ИИ-агента | — |
```

- [ ] **Step 2: Добавить пункт в «Перед заливкой»**

```markdown
**Заменить путь в `bb/.htaccess` (auto_prepend_file) на реальный прод-путь.**
Закоммиченное значение рассчитано на локальный Docker
(`/var/www/html/bb/classes/PageVisitTracker.php`) — на cPanel он другой.
Узнать через `pwd` в SSH-сессии на проде (обычно
`/home/<cpanel-логин>/public_html/bb/classes/PageVisitTracker.php`),
раскомментировать закомментированную строку с этим путём, закомментировать
Docker-путь.
```

- [ ] **Step 3: Добавить пункт в «После заливки»**

```markdown
**Проверить, что `auto_prepend_file` реально сработал.** Зайти на любую
страницу `bb/` под своей учёткой и проверить, что в `bb_page_visits`
появилась строка (`SELECT * FROM bb_page_visits ORDER BY id DESC LIMIT 5`).
Если PHP на проде работает через FPM и `.htaccess`-директива не применяется —
запасной план без правки кода: `bb/.user.ini` с той же директивой (см.
`docs/superpowers/specs/2026-09-03-crm-usage-tracking-design.md`, раздел
«Точка перехвата»).
```

- [ ] **Step 4: Commit**

```bash
git add docs/prod_pending.md
git commit -m "docs(crm-tracking): чеклист заливки для трекинга использования CRM"
```

---

### Task 9: Финальная проверка и обновление спеки

**Files:**
- Modify: `docs/superpowers/specs/2026-09-03-crm-usage-tracking-design.md`

- [ ] **Step 1: Исправить в спеке описание исключения библиотечных файлов**

В разделе «Общий каталог страниц» заменить фразу про то, что классы физически не лежат в `bb/*.php`, на точное описание: явный список 17 файлов (`LIBRARY_FILES`), выверенный по признаку «объявляет `class` и не вызывает `session_start()`».

- [ ] **Step 2: Прогнать весь тестовый набор**

Run: `docker compose exec -T app php artisan test`
Expected: столько же passed, сколько до начала работы (441) + новые тесты (7 `PageVisitCatalogTest` + 6 `StaffTest` + 2 новых кейса `SpecRuntimeParityTest`), тот же 1 pre-existing failure (`CategoriesTest`), без новых failures.

- [ ] **Step 3: Ручная сквозная проверка в браузере (Docker)**

Залогиниться в `http://localhost/bb/`, открыть 2-3 реальные страницы (`deals.php`, `tovar_new.php`), открыть `bb_nav_badge.php` косвенно (просто подождать автообновление на странице с nav) — затем `http://localhost/bb/page_track.php` под `isDima()`-пользователем: обе таблицы отражают реальные заходы, badge/ajax не попали в лог.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/specs/2026-09-03-crm-usage-tracking-design.md
git commit -m "docs(crm-tracking): исправить описание фильтра библиотечных файлов в спеке"
```

---

## Self-Review

**Spec coverage:** auto_prepend-трекер (Task 3-4) ✓, таблица (Task 1) ✓, отчёт `page_track.php` + `isDima()`-гейт (Task 5) ✓, `PageVisitCatalog` как общий каталог (Task 2) ✓, MCP API + документация + openapi + тест (Task 6-7) ✓, `docs/prod_pending.md` (Task 8) ✓. Все разделы спеки покрыты.

**Placeholder scan:** нет TBD/TODO, весь код полный и рабочий, ни одного «similar to Task N» — каждый шаг несёт полный код.

**Type consistency:** `PageVisitCatalog::isTechnical(string): bool` / `listTrackablePages(): array` — одинаковая сигнатура используется в Task 3, 5, 6. Поля ответа `by-user` (`user_id`, `user_name`, `visits`, `distinct_pages`, `last_visit_at`) и `by-page` (`page`, `visits`, `distinct_users`, `last_visit_at`) одинаковы в контроллере (Task 6), тесте (Task 6) и openapi-схеме (Task 7).
