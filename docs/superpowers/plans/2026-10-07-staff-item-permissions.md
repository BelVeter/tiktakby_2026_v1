# Права на тарифы, выбытие и каталог товаров Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Дать Кате (logpass 22) всё, что есть у Юли (26) для работы с товарами, заменив зашитый `getId()==26` на три права из `permissions`/`user_permissions`.

**Architecture:** Три новых права (7 тарифы, 8 выбытие, 9 каталог). Миграция заводит их и выдаёт 22 и 26. В `bb/` условия `level>=5 || id==26` заменяются на `User::currentHasPermission(Permission::X)`; страницы, открытые любому `level 0`, получают серверную проверку `User::requireCurrentPermission()`.

**Tech Stack:** PHP 7.4, легаси `bb/` без composer-автозагрузки (зависимости через `require_once`), Laravel 8 миграции, PHPUnit в docker (`docker compose exec -T app ...`).

## Global Constraints

- Спека: `docs/superpowers/specs/2026-10-07-staff-item-permissions-design.md` (коды 7, 8, 9; код 6 зарезервирован под «Банк и Сейф»).
- PHP 7.4 на проде: никаких `match`, `?->`, typed properties, `str_contains`.
- `bb/` не использует composer autoload: каждый файл сам `require_once`'ит зависимости (CLAUDE.md, п. 7).
- В `bb/` не смешивать Laravel DB и `\bb\Db` в одном файле.
- `can_destroy()` в `bb/tovar_del.php` (жёсткое удаление, перенос модели в архив — только `level` 5/7) не менять.
- Работа только локально на ветке `feature/staff-item-permissions`; прод не трогать, не деплоить.
- Коммиты оканчиваются строкой `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- `fail-closed`: нет сессии, пользователя или ошибка чтения прав → доступа нет.

---

## File Structure

| Файл | Ответственность |
|---|---|
| `bb/classes/Permission.php` | + константы кодов `TARIFFS=7`, `DISPOSAL=8`, `CATALOG=9` |
| `bb/models/User.php` | + `require_once` Permission.php; + `currentHasPermission()`, `requireCurrentPermission()` |
| `database/migrations/2026_10_07_120000_seed_staff_item_permissions.php` | создаёт права 7–9 и выдаёт 22 и 26 |
| `bb/kr_baza_new.php` | меню товара, `toggle_fake` — по трём правам |
| `bb/rent_tarifs.php` | вход на страницу тарифов — по праву `TARIFFS` |
| `bb/index.php` | плитки «Внести товар / Тарифы / QR-коды» — по правам |
| `bb/tovar_new.php`, `bb/tovar_new_mod.php`, `bb/tovar_del.php` | серверная проверка права на странице |
| `tests/Feature/Bb/StaffItemPermissionsTest.php` | механика прав, идемпотентность миграции, гарды на исходники |
| `docs/backlog.md`, `docs/prod_pending.md`, `AGENTS.md` | нумерация прав, прод-проверка, таблица прав |

---

### Task 1: Права, хелперы `User`, миграция

**Files:**
- Modify: `bb/classes/Permission.php` (после `private $int_code;`)
- Modify: `bb/models/User.php` (блок `use`, метод после `hasPermission`)
- Create: `database/migrations/2026_10_07_120000_seed_staff_item_permissions.php`
- Create: `tests/Feature/Bb/StaffItemPermissionsTest.php`

**Interfaces:**
- Produces: `bb\classes\Permission::TARIFFS|DISPOSAL|CATALOG` (int 7|8|9);
  `bb\models\User::currentHasPermission($code): bool`;
  `bb\models\User::requireCurrentPermission($code, $what): void` (die с коротким отказом).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Bb/StaffItemPermissionsTest.php`:

```php
<?php

namespace Tests\Feature\Bb;

use bb\classes\Permission;
use bb\Db;
use bb\models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Права сотрудника на товары: тарифы (7), выбытие (8), каталог (9).
 * Раньше это держалось на зашитом `getId()==26` (Юля).
 *
 * Механика прав проверяется на временном пользователе внутри транзакции
 * `\bb\Db` (откатывается). Миграция проверяется через Laravel DB в своей
 * транзакции — она идемпотентна, поэтому повторный up() ничего не меняет.
 *
 * Требует применённой миграции 2026_10_07_120000_seed_staff_item_permissions
 * (для тестов миграции; механика прав от неё не зависит — берёт право 1).
 */
class StaffItemPermissionsTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_07_120000_seed_staff_item_permissions.php';

    protected function setUp(): void
    {
        parent::setUp();
        User::$_users = [];
        unset($_SESSION['user_id']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        User::$_users = [];
        parent::tearDown();
    }

    private function conn(): \mysqli
    {
        return Db::getInstance()->getConnection();
    }

    /** Временный сотрудник level 0 без прав; возвращает его logpass_id. */
    private function createTempStaff(): int
    {
        $login = 'tmp_perm_' . bin2hex(random_bytes(4));
        $ok = $this->conn()->query(
            "INSERT INTO logpass SET log='$login', pass='x', lp_fio='tmp', `level`=0, delivery=0, time_yn=0, "
            . "time_from=0, time_to=0, ip_yn=0, ip_addr='', ip_addr_2='', ip_addr_3='', zp_yn=0, oklad=0, "
            . "`active`=0, main_role='consultant', color=''"
        );
        $this->assertTrue((bool) $ok, 'не удалось создать временного сотрудника: ' . $this->conn()->error);

        return (int) $this->conn()->insert_id;
    }

    // ─── коды ─────────────────────────────────────────────────────────────────

    public function test_permission_codes_match_the_spec(): void
    {
        $this->assertSame(7, Permission::TARIFFS);
        $this->assertSame(8, Permission::DISPOSAL);
        $this->assertSame(9, Permission::CATALOG);
    }

    // ─── механика ─────────────────────────────────────────────────────────────

    public function test_denied_without_session(): void
    {
        $this->assertFalse(User::currentHasPermission(Permission::TARIFFS));
    }

    public function test_staff_passes_only_with_the_granted_permission(): void
    {
        Db::startTransaction();
        try {
            $id = $this->createTempStaff();
            $_SESSION['user_id'] = $id;

            $this->assertFalse(User::currentHasPermission(1), 'без строки в user_permissions права нет');

            $this->conn()->query("INSERT INTO user_permissions SET user_id=$id, permission_id=1");
            User::$_users = []; // права кэшируются в объекте пользователя

            $this->assertTrue(User::currentHasPermission(1), 'выданное право действует');
            $this->assertFalse(User::currentHasPermission(2), 'чужое право не действует');
        } finally {
            Db::rollBackTransaction();
        }
    }

    public function test_owner_passes_without_rows(): void
    {
        $exists = $this->conn()->query("SELECT 1 FROM logpass WHERE logpass_id=3")->num_rows > 0;
        if (!$exists) {
            $this->markTestSkipped('в локальной БД нет владельца id 3');
        }
        $_SESSION['user_id'] = 3;

        $this->assertTrue(User::currentHasPermission(Permission::TARIFFS));
        $this->assertTrue(User::currentHasPermission(Permission::DISPOSAL));
        $this->assertTrue(User::currentHasPermission(Permission::CATALOG));
    }

    // ─── миграция ─────────────────────────────────────────────────────────────

    public function test_migration_grants_all_three_to_katya_and_yulia_and_is_idempotent(): void
    {
        require_once base_path(self::MIGRATION);

        DB::beginTransaction();
        try {
            (new \SeedStaffItemPermissions())->up();
            (new \SeedStaffItemPermissions())->up(); // повтор не должен ничего задвоить

            foreach ([7, 8, 9] as $code) {
                $this->assertSame(
                    1,
                    DB::table('permissions')->where('int_code', $code)->count(),
                    "право $code должно быть заведено ровно один раз"
                );
            }

            foreach ([22, 26] as $userId) {
                if (!DB::table('logpass')->where('logpass_id', $userId)->exists()) {
                    continue; // чистая БД без этого сотрудника
                }
                foreach ([7, 8, 9] as $code) {
                    $this->assertSame(
                        1,
                        DB::table('user_permissions')
                            ->where(['user_id' => $userId, 'permission_id' => $code])->count(),
                        "у сотрудника $userId право $code должно быть выдано ровно один раз"
                    );
                }
            }
        } finally {
            DB::rollBack();
        }
    }

    // ─── гарды на исходники ───────────────────────────────────────────────────

    public function test_no_hardcoded_staff_id_26_left_in_live_bb_pages(): void
    {
        $root = dirname(__DIR__, 3);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/bb', \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $offenders = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace($root . '/', '', $file->getPathname());
            // phpqrcode — сторонняя библиотека; kr_baza_new3.php — мёртвая старая копия списка товаров.
            if (strpos($path, 'bb/phpqrcode/') === 0 || $path === 'bb/kr_baza_new3.php') {
                continue;
            }
            if (preg_match('/(getId\(\)|id_user)\s*==\s*26\b/', file_get_contents($file->getPathname()))) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, 'id сотрудника 26 нельзя зашивать в код — используйте права');
    }

    public function test_pages_are_gated_by_the_permission_constants(): void
    {
        $root = dirname(__DIR__, 3);
        $expect = [
            'bb/rent_tarifs.php'   => ['Permission::TARIFFS'],
            'bb/tovar_new.php'     => ['requireCurrentPermission(\bb\classes\Permission::CATALOG'],
            'bb/tovar_new_mod.php' => ['requireCurrentPermission(\bb\classes\Permission::CATALOG'],
            'bb/tovar_del.php'     => ['requireCurrentPermission(\bb\classes\Permission::DISPOSAL'],
            'bb/kr_baza_new.php'   => ['Permission::TARIFFS', 'Permission::DISPOSAL', 'Permission::CATALOG'],
            'bb/index.php'         => ['Permission::TARIFFS', 'Permission::CATALOG'],
        ];

        foreach ($expect as $path => $needles) {
            $code = file_get_contents($root . '/' . $path);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $code, "$path должен проверять право: $needle");
            }
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/dmitry/sites/tiktakby && docker compose exec -T app vendor/bin/phpunit tests/Feature/Bb/StaffItemPermissionsTest.php 2>&1 | tail -30`
Expected: FAIL/ERROR — `Undefined class constant 'bb\classes\Permission::TARIFFS'` (и/или `Call to undefined method ...currentHasPermission`).

- [ ] **Step 3: Add the permission constants**

In `bb/classes/Permission.php` replace

```php
class Permission
{
  private $id;
  private $int_code;
```

with

```php
class Permission
{
  /** Тарифы товаров: bb/rent_tarifs.php, пункт меню «Тарифы», плитка на главной. */
  const TARIFFS = 7;
  /** Выбытие товара: форма bb/tovar_del.php, пункт меню «Удаление». */
  const DISPOSAL = 8;
  /** Каталог: новый товар/модель, правка, «В популярные», ФЕЙК, QR-коды. */
  const CATALOG = 9;
  // Код 6 зарезервирован под «Банк и Сейф в расходах» (docs/backlog.md).

  private $id;
  private $int_code;
```

- [ ] **Step 4: Add the `User` helpers**

In `bb/models/User.php`, right after the line `use phpDocumentor\Reflection\Types\True_;` add (a blank line before `class User`):

```php
// bb/ без composer autoload: User обращается к Permission, поэтому тянет его сам.
require_once __DIR__ . '/../classes/Permission.php';
```

Then, right after the `hasPermission` method (ends with `return false;\n    }`), add:

```php
    /**
     * Есть ли у ТЕКУЩЕГО (из сессии) сотрудника право. Fail-closed: нет сессии,
     * пользователя или ошибка чтения прав — отказ. Владельцы (2, 3, 5) проходят
     * через hasPermission().
     *
     * @param int $code Permission::TARIFFS и т.п.
     * @return bool
     */
    public static function currentHasPermission($code){
      if (empty($_SESSION['user_id'])) return false;
      try {
        $user = self::getCurrentUser();
        return $user instanceof self && $user->hasPermission($code);
      }
      catch (\Throwable $e){
        return false;
      }
    }

    /**
     * Закрывает страницу правом: без него — короткий отказ вместо страницы.
     *
     * @param int $code Permission::CATALOG и т.п.
     * @param string $what что именно закрыто (для текста отказа)
     */
    public static function requireCurrentPermission($code, $what){
      if (self::currentHasPermission($code)) return;

      die('<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'
        . '<title>Нет доступа</title></head><body>Недостаточно прав: ' . $what . '.<br /><br />'
        . '<a href="/bb/index.php">На главную</a></body></html>');
    }
```

- [ ] **Step 5: Create the migration**

Create `database/migrations/2026_10_07_120000_seed_staff_item_permissions.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Права сотрудника на товары (docs/superpowers/specs/2026-10-07-staff-item-permissions-design.md):
 * 7 — тарифы, 8 — выбытие, 9 — каталог. Раньше всё это держалось на зашитом
 * `getId()==26` (Юля); выдаём права Кате (22) и Юле (26), чтобы доступ Юли
 * не изменился до её ухода.
 *
 * Идемпотентна: permissions.int_code уникален, user_permissions проверяется
 * перед вставкой. Сотрудника, которого нет в logpass (чистая БД), пропускаем —
 * иначе упадёт внешний ключ user_list_constrain.
 */
class SeedStaffItemPermissions extends Migration
{
    private const PERMISSIONS = [
        7 => 'Тарифы товаров',
        8 => 'Выбытие товара',
        9 => 'Каталог: добавление и правка товаров и моделей',
    ];

    private const GRANTED_TO = [22, 26];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $code => $description) {
            if (!DB::table('permissions')->where('int_code', $code)->exists()) {
                DB::table('permissions')->insert(['int_code' => $code, 'description' => $description]);
            }
        }

        foreach (self::GRANTED_TO as $userId) {
            if (!DB::table('logpass')->where('logpass_id', $userId)->exists()) {
                continue;
            }
            foreach (array_keys(self::PERMISSIONS) as $code) {
                $granted = DB::table('user_permissions')
                    ->where(['user_id' => $userId, 'permission_id' => $code])
                    ->exists();
                if (!$granted) {
                    DB::table('user_permissions')->insert(['user_id' => $userId, 'permission_id' => $code]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('user_permissions')->whereIn('permission_id', array_keys(self::PERMISSIONS))->delete();
        DB::table('permissions')->whereIn('int_code', array_keys(self::PERMISSIONS))->delete();
    }
}
```

- [ ] **Step 6: Apply the migration locally and run the tests**

Run:
```bash
cd /home/dmitry/sites/tiktakby && docker compose exec -T app php artisan migrate 2>&1 | tail -5
docker compose exec -T app vendor/bin/phpunit tests/Feature/Bb/StaffItemPermissionsTest.php 2>&1 | tail -30
```
Expected: миграция `Migrated: 2026_10_07_120000_seed_staff_item_permissions`; в тесте PASS: `test_permission_codes_match_the_spec`, `test_denied_without_session`, `test_staff_passes_only_with_the_granted_permission`, `test_owner_passes_without_rows`, `test_migration_grants_...`. **Два теста-гарда на исходники ещё FAIL** (`test_no_hardcoded_staff_id_26...`, `test_pages_are_gated...`) — их закрывают задачи 2 и 3.

Проверка данных: `docker compose exec -T db mysql -utiktakby_tiktak -p"$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)" tiktakby_tiktak -e "SELECT * FROM permissions; SELECT user_id,permission_id FROM user_permissions WHERE permission_id>=7 ORDER BY 1,2;"` — права 7–9 и по три строки для 22 и 26.

- [ ] **Step 7: Commit**

```bash
git add bb/classes/Permission.php bb/models/User.php database/migrations/2026_10_07_120000_seed_staff_item_permissions.php tests/Feature/Bb/StaffItemPermissionsTest.php
git commit -m "$(cat <<'EOF'
feat(bb): права TARIFFS/DISPOSAL/CATALOG, User::currentHasPermission и миграция выдачи Кате и Юле

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Заменить зашитый id 26 на права в `kr_baza_new.php`, `rent_tarifs.php`, `index.php`

**Files:**
- Modify: `bb/kr_baza_new.php` (около строк 58–60, 160, 424, 440)
- Modify: `bb/rent_tarifs.php:27`
- Modify: `bb/index.php:352-370`

**Interfaces:**
- Consumes: `\bb\classes\Permission::TARIFFS|DISPOSAL|CATALOG`, `\bb\models\User::currentHasPermission($code): bool` (Task 1).
- Produces: в `kr_baza_new.php` PHP-переменные `$can_tariffs`, `$can_disposal`, `$can_catalog` (bool), доступные ниже по файлу.

- [ ] **Step 1: Run the guard test to see it fail**

Run: `docker compose exec -T app vendor/bin/phpunit --filter test_no_hardcoded_staff_id_26 tests/Feature/Bb/StaffItemPermissionsTest.php 2>&1 | tail -15`
Expected: FAIL, offenders: `bb/index.php`, `bb/kr_baza_new.php`, `bb/rent_tarifs.php`.

- [ ] **Step 2: `kr_baza_new.php` — вычислить три права один раз**

Найти закрывающую метку проверки паролей (`grep -n "proverka paroley" bb/kr_baza_new.php` — вторая, с 11 дефисами, около строки 58) и сразу после неё вставить:

```php
// Права на работу с товарами (раньше — `level>=5 || id==26`). Владельцы проходят автоматически.
$can_tariffs  = \bb\models\User::currentHasPermission(\bb\classes\Permission::TARIFFS);
$can_disposal = \bb\models\User::currentHasPermission(\bb\classes\Permission::DISPOSAL);
$can_catalog  = \bb\models\User::currentHasPermission(\bb\classes\Permission::CATALOG);
```

Это должно стоять ДО блока `if (isset($_POST['action']))` и AJAX-обработчика (там используется `$can_catalog`).

- [ ] **Step 3: `kr_baza_new.php` — `toggle_fake` на сервере**

Заменить

```php
            if (!($_SESSION['level']>=5 || \bb\models\User::getCurrentUser()->getId()==26)) {
                echo json_encode(['status' => 'error', 'message' => 'Недостаточно прав.']);
```

на

```php
            if (!$can_catalog) {
                echo json_encode(['status' => 'error', 'message' => 'Недостаточно прав.']);
```

- [ ] **Step 4: `kr_baza_new.php` — флаг ФЕЙК в JS**

Заменить

```php
	var isPrivileged = <?php echo ($_SESSION['level']>=5 || \bb\models\User::getCurrentUser()->getId()==26) ? 'true' : 'false'; ?>;
```

на

```php
	var isPrivileged = <?php echo $can_catalog ? 'true' : 'false'; ?>;
```

(Эта переменная управляет только кнопкой «ФЕЙК»; название оставляем, чтобы не трогать JS ниже.)

- [ ] **Step 5: `kr_baza_new.php` — меню товара по трём правам**

В строке `document.getElementById('hist_'+item_id).innerHTML='<ul class="i_menu"> ...` (около строки 440) единый тернарник `echo ($_SESSION['level']>=5 || ...==26) ? '...' : '';` разрезается на три. Делать **тремя точечными правками**, остальной текст строки не менять:

1. `echo ($_SESSION['level']>=5 || \bb\models\User::getCurrentUser()->getId()==26) ? ' <li><a href="#" onclick="document.getElementById(\\\'tovar_tarif_` → `echo $can_tariffs ? ' <li><a href="#" onclick="document.getElementById(\\\'tovar_tarif_`
2. `Тарифы</a></li>   <li><a href="#" onclick="document.getElementById(\\\'tovar_edit_` → `Тарифы</a></li>' : ''; echo $can_catalog ? '   <li><a href="#" onclick="document.getElementById(\\\'tovar_edit_`
3. `В популярные товары</a></li>    <li><a href="#" onclick="document.getElementById(\\\'tovar_del_` → `В популярные товары</a></li>' : ''; echo $can_disposal ? '    <li><a href="#" onclick="document.getElementById(\\\'tovar_del_`

Итог: «Тарифы» — по `TARIFFS`; «Редактировать товар», «Редактировать модель», «В популярные товары» — по `CATALOG`; «Удаление» — по `DISPOSAL`.

Проверка синтаксиса: `docker compose exec -T app php -l bb/kr_baza_new.php` → `No syntax errors detected`.

- [ ] **Step 6: `rent_tarifs.php` — вход по праву**

Заменить

```php
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level) || \bb\models\User::getCurrentUser()->getId()==26)) {
```

на

```php
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level) || \bb\models\User::currentHasPermission(\bb\classes\Permission::TARIFFS))) {
```

- [ ] **Step 7: `index.php` — плитки по правам**

Первый блок плиток владельца (`if (($_SESSION['level'] > 4 || $_SESSION['level'] == 3) && !$role->isCurier()) {`) оставить без изменений, но условие вынести в переменную, чтобы владельцам не показывалась вторая копия плиток (`hasPermission` пускает владельцев автоматически).

Заменить строку

```php
if (($_SESSION['level'] > 4 || $_SESSION['level'] == 3) && !$role->isCurier()) {
```

на

```php
$owner_tiles = ($_SESSION['level'] > 4 || $_SESSION['level'] == 3) && !$role->isCurier();

if ($owner_tiles) {
```

А блок

```php
if (User::getCurrentUser()->getId() == 26) {

    echo '
 <div class="container-menu">
        <a class="menu-link" href="/bb/tovar_new.php">
            <img src="/bb/assets/images/png/menu-newtovar.png">
            <span>Внести товар</span>
        </a>
        <a class="menu-link" href="/bb/rent_tarifs.php">
            <img src="/bb/assets/images/png/menu-tarifs.png">
            <span>Тарифы</span>
        </a>
        <a class="menu-link" href="/bb/qrs.php">
            <img src="/bb/assets/images/png/menu-qr.png">
            <span>QR-коды</span>
        </a>
    </div>
';
}
```

заменить на

```php
if (!$owner_tiles) {
    // Сотрудник с правами на товары (раньше — только id 26): плитки по отдельным правам.
    $staff_tiles = '';
    $tile_new_item = User::currentHasPermission(\bb\classes\Permission::CATALOG);
    $tile_tariffs  = User::currentHasPermission(\bb\classes\Permission::TARIFFS);

    if ($tile_new_item) {
        $staff_tiles .= '
        <a class="menu-link" href="/bb/tovar_new.php">
            <img src="/bb/assets/images/png/menu-newtovar.png">
            <span>Внести товар</span>
        </a>';
    }
    if ($tile_tariffs) {
        $staff_tiles .= '
        <a class="menu-link" href="/bb/rent_tarifs.php">
            <img src="/bb/assets/images/png/menu-tarifs.png">
            <span>Тарифы</span>
        </a>';
    }
    if ($tile_new_item) {
        $staff_tiles .= '
        <a class="menu-link" href="/bb/qrs.php">
            <img src="/bb/assets/images/png/menu-qr.png">
            <span>QR-коды</span>
        </a>';
    }

    if ($staff_tiles !== '') {
        echo '
 <div class="container-menu">' . $staff_tiles . '
    </div>
';
    }
}
```

Проверка синтаксиса: `docker compose exec -T app php -l bb/index.php && docker compose exec -T app php -l bb/rent_tarifs.php`.

- [ ] **Step 8: Run the guard test**

Run: `docker compose exec -T app vendor/bin/phpunit --filter test_no_hardcoded_staff_id_26 tests/Feature/Bb/StaffItemPermissionsTest.php 2>&1 | tail -10`
Expected: PASS (offenders пуст).

- [ ] **Step 9: Commit**

```bash
git add bb/kr_baza_new.php bb/rent_tarifs.php bb/index.php
git commit -m "$(cat <<'EOF'
refactor(bb): меню товара, тарифы и плитки главной — по правам вместо id 26

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Серверные проверки на страницах `tovar_new.php`, `tovar_new_mod.php`, `tovar_del.php`

**Files:**
- Modify: `bb/tovar_new.php` (после закрывающей метки проверки паролей)
- Modify: `bb/tovar_new_mod.php` (после неё же; + `require_once` User.php)
- Modify: `bb/tovar_del.php` (после `\bb\Base::loginCheck();`; + `require_once`, комментарий)

**Interfaces:**
- Consumes: `\bb\models\User::requireCurrentPermission($code, $what)` и `Permission::CATALOG|DISPOSAL` (Task 1).

- [ ] **Step 1: Run the guard test to see it fail**

Run: `docker compose exec -T app vendor/bin/phpunit --filter test_pages_are_gated tests/Feature/Bb/StaffItemPermissionsTest.php 2>&1 | tail -12`
Expected: FAIL (`bb/tovar_new.php должен проверять право: requireCurrentPermission(...CATALOG`).

- [ ] **Step 2: `tovar_new.php`**

`grep -n "proverka paroley" bb/tovar_new.php` — закрывающая метка `//-----------proverka paroley` после блока `die(...)`. Сразу после неё вставить (User.php в файле уже подключён):

```php

// Страница закрыта правом «Каталог»: раньше её не открывали лишь потому, что ссылку не показывали.
\bb\models\User::requireCurrentPermission(\bb\classes\Permission::CATALOG, 'добавление и правка товаров и моделей');
```

- [ ] **Step 3: `tovar_new_mod.php`**

Эта страница `User.php` не подключает. Рядом с существующими `require_once` (после `require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');`) добавить:

```php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php');
```

И после закрывающей метки `//-----------proverka paroley` вставить:

```php

// Страница закрыта правом «Каталог»: раньше её не открывали лишь потому, что ссылку не показывали.
\bb\models\User::requireCurrentPermission(\bb\classes\Permission::CATALOG, 'добавление и правка товаров и моделей');
```

- [ ] **Step 4: `tovar_del.php`**

Заменить блок комментария и вызов

```php
// Страница нужна всем сотрудникам: через неё оформляется выбытие товара.
// Разрушающие действия отдельно ограничены can_destroy() ниже.
```

на

```php
// Выбытие товара доступно сотрудникам с правом «Выбытие товара» (Permission::DISPOSAL).
// Разрушающие действия отдельно ограничены can_destroy() ниже.
```

(остальной комментарий про `level>4` оставить). После строки `\bb\Base::loginCheck();` вставить:

```php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php');
\bb\models\User::requireCurrentPermission(\bb\classes\Permission::DISPOSAL, 'выбытие товара');
```

- [ ] **Step 5: Syntax check and guard test**

Run:
```bash
for f in tovar_new tovar_new_mod tovar_del; do docker compose exec -T app php -l bb/$f.php; done
docker compose exec -T app vendor/bin/phpunit tests/Feature/Bb/StaffItemPermissionsTest.php 2>&1 | tail -12
```
Expected: `No syntax errors detected` ×3; все тесты `StaffItemPermissionsTest` PASS.

- [ ] **Step 6: Commit**

```bash
git add bb/tovar_new.php bb/tovar_new_mod.php bb/tovar_del.php
git commit -m "$(cat <<'EOF'
fix(bb): страницы нового товара, модели и выбытия закрыты правами на сервере

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Проверка в браузере под разными ролями, документация, PR

**Files:**
- Modify: `docs/backlog.md` (раздел про «Банк и Сейф»)
- Modify: `docs/prod_pending.md`
- Modify: `AGENTS.md` (таблица/раздел прав, если есть; иначе короткий раздел)

- [ ] **Step 1: Ручная проверка локально под четырьмя ролями**

Через временных сотрудников в локальной БД (создать, проверить, удалить; реальные пароли не использовать):

```bash
PW=$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)
SQL() { docker compose exec -T db mysql -utiktakby_tiktak -p"$PW" tiktakby_tiktak -e "$1" 2>&1 | grep -v -i version; }
SQL "INSERT INTO logpass SET log='tmp_full', pass='tmp1', lp_fio='tmp full', level=0, delivery=0, time_yn=0, time_from=0, time_to=0, ip_yn=0, ip_addr='', ip_addr_2='', ip_addr_3='', zp_yn=0, oklad=0, active=1, main_role='consultant', color='';
     INSERT INTO logpass SET log='tmp_none', pass='tmp2', lp_fio='tmp none', level=0, delivery=0, time_yn=0, time_from=0, time_to=0, ip_yn=0, ip_addr='', ip_addr_2='', ip_addr_3='', zp_yn=0, oklad=0, active=1, main_role='consultant', color='';
     INSERT INTO user_permissions (user_id, permission_id) SELECT logpass_id, p FROM logpass JOIN (SELECT 7 p UNION SELECT 8 UNION SELECT 9) x WHERE log='tmp_full';"
```

Затем под `tmp_full` (должен видеть всё), `tmp_none` (ничего), владельцем и Юлей (26) залогиниться на `http://localhost/bb/index.php` (curl с cookie-jar: найти форму входа в `bb/index2.php`/`User::Register`, выбрать офис) и для каждого проверить:

| Проверка | `tmp_full` / Катя / Юля | `tmp_none` |
|---|---|---|
| `GET /bb/index.php` — плитки «Внести товар», «Тарифы», «QR-коды» | есть | нет |
| `POST /bb/kr_baza_new.php` (`cat_id`) — в меню товара «Тарифы», «Редактировать товар», «Редактировать модель», «В популярные», «Удаление» | есть | нет |
| `GET /bb/rent_tarifs.php` | страница тарифов | форма логина |
| `GET /bb/tovar_new.php`, `/bb/tovar_new_mod.php` | страница | «Недостаточно прав» |
| `POST /bb/tovar_del.php` (`model_id`, `item_id`) | страница выбытия | «Недостаточно прав» |
| Владелец (id 3) на `/bb/index.php` | плитки владельца **один раз** (не дублируются) | — |

Если автоматизировать вход curl'ом не получается за разумное время — проверить руками в браузере (`http://localhost/bb/`) и записать результат в отчёт; не заявлять «проверено», если не проверено.

Очистка: `SQL "DELETE FROM user_permissions WHERE user_id IN (SELECT logpass_id FROM logpass WHERE log LIKE 'tmp\_%'); DELETE FROM logpass WHERE log LIKE 'tmp\_%';"`

- [ ] **Step 2: Документация**

1. `docs/backlog.md`, раздел «Доступ к «Банку» и «Сейфу»… — перевести со списка id на права»: в пункте плана 1 оговорить, что коды 7–9 заняты (права на товары, 2026-10-07), «Банк и Сейф» остаётся кодом 6.
2. `docs/prod_pending.md`: в формате файла добавить пункт «Права на товары (ветка `feature/staff-item-permissions`)»: до заливки — ничего; после деплоя **явно** проверить `SELECT * FROM permissions WHERE int_code IN (7,8,9)` и `SELECT user_id, permission_id FROM user_permissions WHERE permission_id IN (7,8,9)` (должны быть 22 и 26 по трое; вывод `Deploy.php` «Nothing to migrate» ненадёжен — db_notes п. 7); запасной вариант — `INSERT` вручную; проверить под Катей: пункты меню товара, «Тарифы», «Новый товар», выбытие; Юля сохраняет все права до ухода, потом `active=0`.
3. `AGENTS.md` (на английском): в разделе про авторизацию/права добавить таблицу прав 1–5 + 7–9 (6 зарезервирован) и правило «в `bb/` не зашивать id сотрудников; использовать `User::currentHasPermission(Permission::X)`».

- [ ] **Step 3: Полный прогон тестов**

Run: `docker compose exec -T app vendor/bin/phpunit 2>&1 | tail -15`
Expected: без новых падений. Если падают тесты, не связанные с этой веткой, — сравнить с `git stash`/`origin/main` и сообщить отдельно.

- [ ] **Step 4: Commit docs**

```bash
git add docs/backlog.md docs/prod_pending.md AGENTS.md
git commit -m "$(cat <<'EOF'
docs: права на товары — нумерация, проверка на проде, правило для bb/

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 5: Проверка слияния и PR**

```bash
git fetch origin && git merge-tree --write-tree --messages HEAD origin/main; echo "exit=$?"
```
Expected: `exit=0`. Затем — push ветки и PR по принятому рабочему процессу владельца (локально → PR; прод не трогать). PR-описание на русском: что и зачем, таблица прав, шаги прод-проверки из `docs/prod_pending.md`, что `tovar_new*`/`tovar_del` теперь закрыты правами (другие сотрудники `level 0` теряют доступ по прямому URL — у них его и не было в меню), оканчивается строкой `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.
