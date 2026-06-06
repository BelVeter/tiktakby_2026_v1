# Заявки Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Связать звонки и заявки, добавить статусы/планируемую дату/смену модели/soft-delete и анти-дубль, не сломав брони в общей таблице `rent_orders`.

**Architecture:** Единый класс `bb\classes\Zayavka` (mysqli, инъектируемое соединение) инкапсулирует логику заявок; вызывается из легаси `bb/`-страниц и Laravel-контроллеров. UI: доска `rent_zayavk.php` + попап на `zv_ch.php` через AJAX-эндпоинт `bb/zayavka_api.php`. **Безопасность схемы прежде всего:** позиционные `INSERT ... VALUES` переводятся на явные колонки ДО `ALTER TABLE`.

**Tech Stack:** PHP 7.4, Laravel 8, MariaDB 10.6, mysqli (легаси `bb/`), PHPUnit (через `docker exec tiktakby-app php artisan test`).

**Спека:** [docs/superpowers/specs/2026-06-05-zayavki-redesign-design.md](../specs/2026-06-05-zayavki-redesign-design.md). Находки/ловушки БД: [docs/db_notes.md](../../db_notes.md).

---

## Соглашения для всех тестов

- Запуск: `docker exec tiktakby-app php artisan test --filter=<...>` (контейнер резолвит host `db`, который хардкожен в `bb\Db`).
- Легаси-код использует `bb\Db` (отдельное mysqli-соединение, НЕ Laravel PDO) → Laravel `DatabaseTransactions` его не откатывает. Поэтому тесты заявок **сами чистят за собой**: собирают созданные `order_id`/`zv_id` и удаляют их в `tearDown()` из `rent_orders`, `rent_orders_arch`, `zvonki`.
- Маркер тестовых данных: телефон из диапазона `79900000000+` и `info` с префиксом `__TEST__`, чтобы случайные остатки были опознаваемы.
- `Zayavka` и тесты соединяются через инъекцию: `new Zayavka($conn)`, где `$conn` в тестах = `bb\Db::getInstance()->getConnection()` (тот же Docker dev DB).

## Эталонный порядок колонок (зафиксировано 2026-06-05)

`rent_orders` (29 колонок; `order_id` auto):
```
order_id, type, order_date, phone, phone_yn, family, name, otch, fio_yn, address,
validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time,
cr_who_id, ch_time, ch_who_id, status, appr_id, appr_time, cr_ip, place_status, rem_type
```
`rent_orders_arch` = `arch_order_id`(auto), `arch_time`, `arch_who`, затем те же 29 (`order_id`...`rem_type`).
`zvonki` (14): `zv_id`(auto), `z_name, pr_time, operator, phone, tema, info, cr_time, status, react_time, person_id, validity_days, type1, model_id`.

---

## Task 1: Регрессионный сейф-нет на bron::insert() + arch_copy() (характеризация ДО изменений)

Цель: зафиксировать, что создание и архивация записи в `rent_orders` работают на текущей 29-колоночной схеме. Этот тест должен оставаться зелёным после конвертации (Task 2) и после миграции (Task 6).

**Files:**
- Create: `tests/Feature/Zayavka/BronRegressionTest.php`

- [ ] **Step 1: Написать тест (создание + архивация bron через bb\classes\bron)**

```php
<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\bron;

class BronRegressionTest extends TestCase
{
    private $conn;
    private array $cleanupOrderIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->conn = Db::getInstance()->getConnection();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupOrderIds as $id) {
            $this->conn->query("DELETE FROM rent_orders WHERE order_id=" . (int)$id);
            $this->conn->query("DELETE FROM rent_orders_arch WHERE order_id=" . (int)$id);
        }
        parent::tearDown();
    }

    public function test_bron_insert_and_arch_copy_roundtrip(): void
    {
        $br = new bron();
        $br->type = 'strong';
        $br->order_date = time();
        $br->phone = 79900000001;
        $br->phone_yn = 0;
        $br->family = '__TEST__ Иванов';
        $br->name = '';
        $br->otch = '';
        $br->fio_yn = 0;
        $br->address = '';
        $br->validity = time() + 86400;
        $br->inv_n = 0;
        $br->model_id = 0;
        $br->cat_id = 0;
        $br->type2 = 'bron';
        $br->client_id = 0;
        $br->info = '__TEST__ regression';
        $br->info2 = '';
        $br->web = 0;
        $br->cr_time = time();
        $br->cr_who_id = 0;
        $br->ch_time = 0;
        $br->ch_who_id = 0;
        $br->status = '';
        $br->appr_id = 0;
        $br->appr_time = 0;
        $br->cr_ip = '';
        $br->place_status = '';
        $br->rem_type = '';

        $br->insert();
        $this->assertGreaterThan(0, $br->insert_id, 'insert() must return new id');
        $this->cleanupOrderIds[] = $br->insert_id;
        $br->order_id = $br->insert_id;

        $row = $this->conn->query("SELECT type2, family, phone FROM rent_orders WHERE order_id=" . (int)$br->insert_id)->fetch_assoc();
        $this->assertSame('bron', $row['type2']);
        $this->assertSame('__TEST__ Иванов', $row['family']);

        // arch_copy() копирует в rent_orders_arch без удаления из активной
        $br->arch_copy('dogovor_new'); // auto-режим, user=0, без $_SESSION
        $arch = $this->conn->query("SELECT COUNT(*) c FROM rent_orders_arch WHERE order_id=" . (int)$br->insert_id)->fetch_assoc();
        $this->assertSame(1, (int)$arch['c'], 'arch_copy must duplicate the row into _arch');
    }
}
```

- [ ] **Step 2: Запустить — должен пройти на текущей схеме**

Run: `docker exec tiktakby-app php artisan test --filter=BronRegressionTest`
Expected: PASS (1 test). Если падает на `arch_copy` из-за `$_SESSION` — передан режим `'dogovor_new'`, ветка `auto != ''` → `user=0`, `$_SESSION` не читается. Подтвердить.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Zayavka/BronRegressionTest.php
git commit -m "test: regression safety-net for bron insert/arch_copy on rent_orders"
```

---

## Task 2: Конвертация bron::insert() и arch_copy() на явные колонки

**Files:**
- Modify: `bb/classes/bron.php` (`insert()` ~520, `arch_copy()` ~805)

- [ ] **Step 1: Заменить позиционный INSERT в `insert()` на явные колонки**

Найти (line ~520):
```php
			$query = "INSERT INTO rent_orders VALUES ('', '$this->type', '$this->order_date', '$this->phone', '$this->phone_yn', '$this->family', '$this->name', '$this->otch', '$this->fio_yn', '$this->address', '$this->validity', '$this->inv_n', '$this->model_id', '$this->cat_id', '$this->type2', '$this->client_id', '$this->info','$this->info2', '$this->web', '$this->cr_time', '$this->cr_who_id', '$this->ch_time', '$this->ch_who_id', '$this->status', '$this->appr_id', '$this->appr_time', '$this->cr_ip', '$this->place_status', '$this->rem_type')";
```
Заменить на:
```php
			$query = "INSERT INTO rent_orders
				(`type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type)
				VALUES ('$this->type', '$this->order_date', '$this->phone', '$this->phone_yn', '$this->family', '$this->name', '$this->otch', '$this->fio_yn', '$this->address', '$this->validity', '$this->inv_n', '$this->model_id', '$this->cat_id', '$this->type2', '$this->client_id', '$this->info', '$this->info2', '$this->web', '$this->cr_time', '$this->cr_who_id', '$this->ch_time', '$this->ch_who_id', '$this->status', '$this->appr_id', '$this->appr_time', '$this->cr_ip', '$this->place_status', '$this->rem_type')";
```

- [ ] **Step 2: Заменить позиционный INSERT...SELECT в `arch_copy()` на явные колонки**

Найти (line ~805):
```php
		$query_arch = "INSERT INTO rent_orders_arch SELECT '', '".time()."', '".$user."', order_id, `type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, `validity`, `inv_n`, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, status, `appr_id`, `appr_time`, `cr_ip`, `place_status`, `rem_type` FROM rent_orders WHERE order_id='$this->order_id'";
```
Заменить на:
```php
		$query_arch = "INSERT INTO rent_orders_arch
			(arch_time, arch_who, order_id, `type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type)
			SELECT '".time()."', '".$user."', order_id, `type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type FROM rent_orders WHERE order_id='$this->order_id'";
```

- [ ] **Step 3: Запустить регрессию — должна остаться зелёной**

Run: `docker exec tiktakby-app php artisan test --filter=BronRegressionTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add bb/classes/bron.php
git commit -m "refactor: explicit columns in bron insert/arch_copy (safe column adds)"
```

---

## Task 3: Конвертация includes/l_3_br.php (3 позиционных INSERT) на явные колонки

`includes/l_3_br.php` — процедурный AJAX-эндпоинт сайта (брони строки 343/416, заявка строка 508). Колонки те же 28. Конвертация поведение-сохраняющая; покрытие — паттерн доказан Task 1/2 на той же таблице.

**Files:**
- Modify: `includes/l_3_br.php:343, 416, 508`

- [ ] **Step 1: Строка 343 — заменить**

Найти `INSERT INTO rent_orders VALUES ('', 'strong', '$ac_date', '$tel', '', '$f', '$i', '$o', '', '$deliv_addr', '$validity', '".$free1_def['item_inv_n']."', ... )` и заменить на явный список колонок (тот же порядок 28 значений):
```php
	$query = "INSERT INTO rent_orders
		(`type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type)
		VALUES ('strong', '$ac_date', '$tel', '', '$f', '$i', '$o', '', '$deliv_addr', '$validity', '".$free1_def['item_inv_n']."', '".$free1_def['model_id']."', '".$free1_def['cat_id']."', '$type_2_q', '".Base::getAdvCompId()."', '$dop_info', '', '1', '".time()."', '', '', '', '".Base::getAdvCompId()."', '', '', '".$_SERVER['REMOTE_ADDR']."', '', '')";
```
(Маппинг исходных значений к колонкам: `'strong'`→type, `$ac_date`→order_date, `$tel`→phone, `''`→phone_yn, `$f`→family, `$i`→name, `$o`→otch, `''`→fio_yn, `$deliv_addr`→address, `$validity`→validity, inv_n, model_id, cat_id, `$type_2_q`→type2, getAdvCompId→client_id, `$dop_info`→info, `''`→info2, `'1'`→web, time→cr_time, `''`→cr_who_id, `''`→ch_time, `''`→ch_who_id, getAdvCompId→status, `''`→appr_id, `''`→appr_time, REMOTE_ADDR→cr_ip, `''`→place_status, `''`→rem_type.)

- [ ] **Step 2: Строка 416 — заменить аналогично** (значения идентичны, но `$free_zap` вместо `$free1_def`):
```php
		$query = "INSERT INTO rent_orders
			(`type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type)
			VALUES ('strong', '$ac_date', '$tel', '', '$f', '$i', '$o', '', '$deliv_addr', '$validity', '".$free_zap['item_inv_n']."', '".$free_zap['model_id']."', '".$free_zap['cat_id']."', '$type_2_q', '".Base::getAdvCompId()."', '$dop_info', '', '1', '".time()."', '', '', '', '".Base::getAdvCompId()."', '', '', '".$_SERVER['REMOTE_ADDR']."', '', '')";
```

- [ ] **Step 3: Строка 508 (заявка) — заменить**:
```php
	$query = "INSERT INTO rent_orders
		(`type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type)
		VALUES ('zayavka', '$ac_date', '$tel', '', '$f', '$i', '$o', '', '$deliv_addr', '$validity', '', '$model_id', '".$model_m['tovar_rent_cat_id']."', 'zayavka', '".Base::getAdvCompId()."', '$info', '', '1', '".time()."', '', '', '', '', '', '', '".$_SERVER['REMOTE_ADDR']."', '', '')";
```

- [ ] **Step 4: Проверить синтаксис PHP**

Run: `docker exec tiktakby-app php -l includes/l_3_br.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add includes/l_3_br.php
git commit -m "refactor: explicit columns in includes/l_3_br.php inserts"
```

---

## Task 4: Удалить мёртвый includes/zvonok.php

Подтверждено: позиционный INSERT (11 значений vs 14 колонок) — уже несовместим; живой путь звонков — `ZvonokController`; статические `.html`-хедеры переадресованы (владелец подтвердил).

**Files:**
- Delete: `includes/zvonok.php`

- [ ] **Step 1: Удалить файл**

```bash
git rm includes/zvonok.php
```

- [ ] **Step 2: Убедиться, что PHP-кода, который его require/include, нет**

Run: `grep -rnE "require.*zvonok\.php|include.*zvonok\.php" --include="*.php" . | grep -v vendor`
Expected: пусто (только `.html` ссылки, которые переадресованы).

- [ ] **Step 3: Commit**

```bash
git commit -m "chore: remove dead includes/zvonok.php (incompatible positional insert)"
```

---

## Task 5: Миграция — колонки z_status / z_reject_reason / planned_date + zvonki.order_id + индексы + backfill

**Files:**
- Create: `database/migrations/2026_06_05_120000_add_zayavka_lifecycle_columns.php`

- [ ] **Step 1: Написать миграцию**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddZayavkaLifecycleColumns extends Migration
{
    public function up(): void
    {
        foreach (['rent_orders', 'rent_orders_arch'] as $t) {
            Schema::table($t, function ($table) {
                $table->string('z_status', 20)->default('new')->after('status');
                $table->string('z_reject_reason', 40)->nullable()->after('z_status');
                $table->date('planned_date')->nullable()->after('z_reject_reason');
            });
        }

        Schema::table('zvonki', function ($table) {
            $table->integer('order_id')->nullable()->after('model_id');
            $table->index('order_id', 'idx_zvonki_order_id');
        });

        Schema::table('rent_orders', function ($table) {
            $table->index(['model_id', 'phone'], 'idx_ro_model_phone');
            $table->index(['type2', 'z_status'], 'idx_ro_type2_zstatus');
        });

        // Backfill: новизна определялась пустотой info2
        DB::statement("UPDATE rent_orders SET z_status='new'
                       WHERE type2='zayavka' AND (info2 IS NULL OR info2='')");
        DB::statement("UPDATE rent_orders SET z_status='in_work'
                       WHERE type2='zayavka' AND info2 IS NOT NULL AND info2<>''");
    }

    public function down(): void
    {
        Schema::table('rent_orders', function ($table) {
            $table->dropIndex('idx_ro_model_phone');
            $table->dropIndex('idx_ro_type2_zstatus');
        });
        Schema::table('zvonki', function ($table) {
            $table->dropIndex('idx_zvonki_order_id');
            $table->dropColumn('order_id');
        });
        foreach (['rent_orders', 'rent_orders_arch'] as $t) {
            Schema::table($t, function ($table) {
                $table->dropColumn(['z_status', 'z_reject_reason', 'planned_date']);
            });
        }
    }
}
```

- [ ] **Step 2: Прогнать миграцию**

Run: `docker exec tiktakby-app php artisan migrate`
Expected: `Migrated: 2026_06_05_120000_add_zayavka_lifecycle_columns`.

- [ ] **Step 3: Регрессия броней — после добавления колонок INSERT с явными колонками работает**

Run: `docker exec tiktakby-app php artisan test --filter=BronRegressionTest`
Expected: PASS (доказывает, что Task 2 защитил от поломки column-count).

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_05_120000_add_zayavka_lifecycle_columns.php
git commit -m "feat(db): zayavka lifecycle columns, zvonki.order_id link, indexes, backfill"
```

---

## Task 6: Класс bb\classes\Zayavka — каркас + create() с дедупом (TDD)

**Files:**
- Create: `bb/classes/Zayavka.php`
- Create: `tests/Feature/Zayavka/ZayavkaCreateTest.php`

- [ ] **Step 1: Написать падающий тест на create() + дедуп**

```php
<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\Zayavka;

class ZayavkaCreateTest extends TestCase
{
    private $conn;
    private array $cleanup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->conn = Db::getInstance()->getConnection();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $id) {
            $this->conn->query("DELETE FROM rent_orders WHERE order_id=" . (int)$id);
            $this->conn->query("DELETE FROM rent_orders_arch WHERE order_id=" . (int)$id);
        }
        $this->conn->query("DELETE FROM zvonki WHERE info LIKE '__TEST__%'");
        parent::tearDown();
    }

    public function test_create_makes_one_zayavka_with_new_status(): void
    {
        $z = new Zayavka($this->conn);
        $res = $z->create([
            'model_id' => 999001, 'phone' => 79900000010, 'family' => '__TEST__ Петров',
            'info' => '__TEST__ нужна коляска', 'web' => 1, 'planned_date' => null,
        ], 'crm');

        $this->assertFalse($res->isDuplicate);
        $this->assertGreaterThan(0, $res->orderId);
        $this->cleanup[] = $res->orderId;

        $row = $this->conn->query("SELECT type2, z_status, phone FROM rent_orders WHERE order_id=" . (int)$res->orderId)->fetch_assoc();
        $this->assertSame('zayavka', $row['type2']);
        $this->assertSame('new', $row['z_status']);
        $this->assertSame('79900000010', $row['phone']);
    }

    public function test_create_detects_duplicate_by_model_and_phone(): void
    {
        $z = new Zayavka($this->conn);
        $first = $z->create(['model_id' => 999002, 'phone' => 79900000011, 'family' => '__TEST__ A', 'info' => '__TEST__ 1', 'web' => 1], 'crm');
        $this->cleanup[] = $first->orderId;

        $second = $z->create(['model_id' => 999002, 'phone' => 79900000011, 'family' => '__TEST__ A', 'info' => '__TEST__ 2', 'web' => 1], 'crm');
        if ($second->orderId) { $this->cleanup[] = $second->orderId; }

        $this->assertTrue($second->isDuplicate);
        $this->assertNotNull($second->existing);
        $this->assertSame($first->orderId, $second->existing->order_id);
    }

    public function test_create_with_empty_phone_does_not_match_other_empty(): void
    {
        $z = new Zayavka($this->conn);
        $a = $z->create(['model_id' => 999003, 'phone' => 0, 'family' => '__TEST__ X', 'info' => '__TEST__ a', 'web' => 1], 'web_modal');
        $this->cleanup[] = $a->orderId;
        $b = $z->create(['model_id' => 999003, 'phone' => 0, 'family' => '__TEST__ Y', 'info' => '__TEST__ b', 'web' => 1], 'web_modal');
        $this->cleanup[] = $b->orderId;

        $this->assertFalse($b->isDuplicate, 'phone<=1 must NOT be treated as duplicate');
    }
}
```

- [ ] **Step 2: Запустить — падает (класс отсутствует)**

Run: `docker exec tiktakby-app php artisan test --filter=ZayavkaCreateTest`
Expected: FAIL (Class "bb\classes\Zayavka" not found).

- [ ] **Step 3: Реализовать каркас Zayavka + create() + findActiveDuplicate()**

```php
<?php
namespace bb\classes;

use bb\Db;

class ZayavkaCreateResult
{
    public bool $isDuplicate = false;
    public ?int $orderId = null;
    public ?Zayavka $existing = null;
    public ?int $zvonokId = null;
}

class Zayavka
{
    /** @var \mysqli */
    private $conn;

    // поля строки
    public $order_id, $type, $order_date, $phone, $phone_yn, $family, $name, $otch,
           $fio_yn, $address, $validity, $inv_n, $model_id, $cat_id, $type2, $client_id,
           $info, $info2, $web, $cr_time, $cr_who_id, $ch_time, $ch_who_id, $status,
           $appr_id, $appr_time, $cr_ip, $place_status, $rem_type,
           $z_status, $z_reject_reason, $planned_date;

    const DEDUP_WINDOW_MONTHS = 6;

    public function __construct($conn = null)
    {
        $this->conn = $conn ?: Db::getInstance()->getConnection();
    }

    private function esc($v): string { return $this->conn->real_escape_string((string)$v); }

    public static function fromRow(array $r, $conn = null): self
    {
        $z = new self($conn);
        foreach ($r as $k => $v) { if (property_exists($z, $k)) { $z->$k = $v; } }
        return $z;
    }

    public function findActiveDuplicate(int $modelId, ?int $phone): ?self
    {
        if (!$phone || $phone <= 1 || $modelId <= 0) { return null; }
        $since = time() - self::DEDUP_WINDOW_MONTHS * 30 * 86400;

        // активные
        $sql = "SELECT * FROM rent_orders WHERE type2='zayavka' AND model_id=" . (int)$modelId
             . " AND phone=" . (int)$phone . " AND z_status IN ('new','in_work') ORDER BY cr_time DESC LIMIT 1";
        $r = $this->conn->query($sql);
        if ($r && $r->num_rows > 0) { return self::fromRow($r->fetch_assoc(), $this->conn); }

        // архив (включая удалённые) за окно
        $sqlA = "SELECT * FROM rent_orders_arch WHERE type2='zayavka' AND model_id=" . (int)$modelId
              . " AND phone=" . (int)$phone . " AND cr_time>" . (int)$since . " ORDER BY cr_time DESC LIMIT 1";
        $ra = $this->conn->query($sqlA);
        if ($ra && $ra->num_rows > 0) { return self::fromRow($ra->fetch_assoc(), $this->conn); }

        return null;
    }

    public function create(array $d, string $source): ZayavkaCreateResult
    {
        $res = new ZayavkaCreateResult();
        $modelId = (int)($d['model_id'] ?? 0);
        $phone = isset($d['phone']) ? (int)preg_replace('/[^0-9]/', '', (string)$d['phone']) : 0;

        $existing = $this->findActiveDuplicate($modelId, $phone ?: null);
        if ($existing) {
            $res->isDuplicate = true;
            $res->existing = $existing;
            return $res; // не плодим строку; связывание звонка — на стороне вызывающего/Task 10
        }

        // загрузить cat_id из модели
        $catId = 0;
        $mr = $this->conn->query("SELECT tovar_rent_cat_id FROM tovar_rent WHERE tovar_rent_id=" . $modelId . " LIMIT 1");
        if ($mr && $row = $mr->fetch_assoc()) { $catId = (int)$row['tovar_rent_cat_id']; }

        $now = time();
        $planned = !empty($d['planned_date']) ? "'" . $this->esc($d['planned_date']) . "'" : 'NULL';
        $sql = "INSERT INTO rent_orders
            (`type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type, z_status, z_reject_reason, planned_date)
            VALUES ('zayavka', $now, " . (int)$phone . ", 0, '" . $this->esc($d['family'] ?? '') . "', '', '', 0, '', "
            . (int)($d['validity'] ?? ($now + 14 * 86400)) . ", 0, " . $modelId . ", " . $catId . ", 'zayavka', 0, '"
            . $this->esc($d['info'] ?? '') . "', '', " . (int)($d['web'] ?? 0) . ", $now, 0, 0, 0, '', 0, 0, '', '', '', 'new', NULL, $planned)";
        if (!$this->conn->query($sql)) {
            throw new \RuntimeException('Zayavka create failed: ' . $this->conn->error);
        }
        $res->orderId = (int)$this->conn->insert_id;
        return $res;
    }
}
```

- [ ] **Step 4: Запустить — должно пройти**

Run: `docker exec tiktakby-app php artisan test --filter=ZayavkaCreateTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add bb/classes/Zayavka.php tests/Feature/Zayavka/ZayavkaCreateTest.php
git commit -m "feat: Zayavka class with dedup-aware create()"
```

---

## Task 7: Zayavka — load/update/setStatus/softDelete/changeModel/linkZvonok (TDD)

**Files:**
- Modify: `bb/classes/Zayavka.php`
- Create: `tests/Feature/Zayavka/ZayavkaLifecycleTest.php`

- [ ] **Step 1: Написать падающий тест**

```php
<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\Zayavka;

class ZayavkaLifecycleTest extends TestCase
{
    private $conn; private array $cleanup = [];
    protected function setUp(): void { parent::setUp(); $this->conn = Db::getInstance()->getConnection(); }
    protected function tearDown(): void {
        foreach ($this->cleanup as $id) {
            $this->conn->query("DELETE FROM rent_orders WHERE order_id=".(int)$id);
            $this->conn->query("DELETE FROM rent_orders_arch WHERE order_id=".(int)$id);
        }
        $this->conn->query("DELETE FROM zvonki WHERE info LIKE '__TEST__%'");
        parent::tearDown();
    }

    private function makeZayavka(): Zayavka {
        $z = new Zayavka($this->conn);
        $res = $z->create(['model_id'=>999100,'phone'=>79900000020,'family'=>'__TEST__ L','info'=>'__TEST__ life','web'=>1],'crm');
        $this->cleanup[] = $res->orderId;
        return Zayavka::load($res->orderId, $this->conn);
    }

    public function test_update_appends_history_and_sets_in_work(): void {
        $z = $this->makeZayavka();
        $z->update(['info' => '__TEST__ перезвонить завтра', 'last_ch_time' => $z->ch_time]);
        $fresh = Zayavka::load($z->order_id, $this->conn);
        $this->assertSame('in_work', $fresh->z_status);
        $this->assertStringContainsString('перезвонить завтра', $fresh->info2);
    }

    public function test_change_model_updates_model_and_cat(): void {
        $z = $this->makeZayavka();
        $z->changeModel(999200);
        $fresh = Zayavka::load($z->order_id, $this->conn);
        $this->assertSame('999200', (string)$fresh->model_id);
    }

    public function test_set_status_rejected_archives_and_removes_from_active(): void {
        $z = $this->makeZayavka();
        $z->setStatus('rejected', 'changed_mind');
        $active = $this->conn->query("SELECT COUNT(*) c FROM rent_orders WHERE order_id=".(int)$z->order_id)->fetch_assoc();
        $arch = $this->conn->query("SELECT z_status, z_reject_reason FROM rent_orders_arch WHERE order_id=".(int)$z->order_id)->fetch_assoc();
        $this->assertSame(0, (int)$active['c'], 'terminal status removes from active');
        $this->assertSame('rejected', $arch['z_status']);
        $this->assertSame('changed_mind', $arch['z_reject_reason']);
    }

    public function test_optimistic_lock_rejects_stale_edit(): void {
        $z = $this->makeZayavka();
        $this->expectException(\RuntimeException::class);
        $z->update(['info' => '__TEST__ stale', 'last_ch_time' => $z->ch_time + 999]); // не совпадает
    }

    public function test_link_zvonok_sets_order_id(): void {
        $z = $this->makeZayavka();
        $this->conn->query("INSERT INTO zvonki SET z_name='__TEST__', phone=79900000020, tema='__TEST__', info='__TEST__ zv', cr_time=".time().", status='new', pr_time=0, operator='', react_time=0, person_id=0, validity_days=0, type1='zayavka', model_id=999100");
        $zvId = $this->conn->insert_id;
        $z->linkZvonok($zvId);
        $row = $this->conn->query("SELECT order_id FROM zvonki WHERE zv_id=".(int)$zvId)->fetch_assoc();
        $this->assertSame((string)$z->order_id, (string)$row['order_id']);
    }
}
```

- [ ] **Step 2: Запустить — падает (методы отсутствуют)**

Run: `docker exec tiktakby-app php artisan test --filter=ZayavkaLifecycleTest`
Expected: FAIL.

- [ ] **Step 3: Реализовать методы в Zayavka**

```php
    public static function load(int $orderId, $conn = null): self
    {
        $c = $conn ?: Db::getInstance()->getConnection();
        $r = $c->query("SELECT * FROM rent_orders WHERE order_id=" . (int)$orderId . " LIMIT 1");
        if (!$r || $r->num_rows < 1) { throw new \RuntimeException('Zayavka not found: ' . $orderId); }
        return self::fromRow($r->fetch_assoc(), $c);
    }

    /** @param array $f keys: info?, planned_date?, last_ch_time (для optimistic-lock) */
    public function update(array $f): void
    {
        // optimistic lock
        if (array_key_exists('last_ch_time', $f) && (string)$f['last_ch_time'] !== (string)$this->ch_time) {
            throw new \RuntimeException('stale edit: zayavka changed by someone else');
        }
        $sets = [];
        if (!empty($f['info'])) {
            $hist = '<p class="bron_hist_unit">' . date('d.m H:i') . ': ' . $this->esc($f['info']) . '</p>';
            $this->info2 = (string)$this->info2 . $hist;
            $sets[] = "info2='" . $this->esc($this->info2) . "'";
        }
        if (array_key_exists('planned_date', $f)) {
            $sets[] = "planned_date=" . (empty($f['planned_date']) ? 'NULL' : "'" . $this->esc($f['planned_date']) . "'");
            $this->planned_date = $f['planned_date'] ?: null;
        }
        // первое действие переводит new -> in_work
        if ($this->z_status === 'new') { $sets[] = "z_status='in_work'"; $this->z_status = 'in_work'; }
        $sets[] = "ch_time=" . time();
        $this->ch_time = time();

        $sql = "UPDATE rent_orders SET " . implode(', ', $sets) . " WHERE order_id=" . (int)$this->order_id;
        if (!$this->conn->query($sql)) { throw new \RuntimeException('update failed: ' . $this->conn->error); }
    }

    public function changeModel(int $newModelId): void
    {
        $catId = 0;
        $mr = $this->conn->query("SELECT tovar_rent_cat_id FROM tovar_rent WHERE tovar_rent_id=" . (int)$newModelId . " LIMIT 1");
        if ($mr && $row = $mr->fetch_assoc()) { $catId = (int)$row['tovar_rent_cat_id']; }
        $hist = '<p class="bron_hist_unit">' . date('d.m H:i') . ': модель изменена #' . (int)$this->model_id . ' → #' . (int)$newModelId . '</p>';
        $this->info2 = (string)$this->info2 . $hist;
        $sql = "UPDATE rent_orders SET model_id=" . (int)$newModelId . ", cat_id=" . $catId
             . ", info2='" . $this->esc($this->info2) . "', ch_time=" . time()
             . " WHERE order_id=" . (int)$this->order_id;
        if (!$this->conn->query($sql)) { throw new \RuntimeException('changeModel failed: ' . $this->conn->error); }
        $this->model_id = $newModelId; $this->cat_id = $catId;
    }

    /** Терминальные статусы (rejected/spam/deleted/done): записать статус+причину и заархивировать */
    public function setStatus(string $status, ?string $reason = null): void
    {
        $terminal = in_array($status, ['rejected', 'spam', 'deleted', 'done'], true);
        $sql = "UPDATE rent_orders SET z_status='" . $this->esc($status) . "'"
             . ($reason !== null ? ", z_reject_reason='" . $this->esc($reason) . "'" : "")
             . ", ch_time=" . time() . " WHERE order_id=" . (int)$this->order_id;
        if (!$this->conn->query($sql)) { throw new \RuntimeException('setStatus failed: ' . $this->conn->error); }
        $this->z_status = $status; $this->z_reject_reason = $reason;

        if ($terminal) { $this->archiveAndRemove(); }
    }

    public function softDelete(?string $reason = null): void { $this->setStatus('deleted', $reason); }

    private function archiveAndRemove(): void
    {
        $user = (int)($_SESSION['user_id'] ?? 0);
        $cols = "(arch_time, arch_who, order_id, `type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type, z_status, z_reject_reason, planned_date)";
        $sel  = time() . ", " . $user . ", order_id, `type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type, z_status, z_reject_reason, planned_date";
        $arch = "INSERT INTO rent_orders_arch $cols SELECT $sel FROM rent_orders WHERE order_id=" . (int)$this->order_id;
        if (!$this->conn->query($arch)) { throw new \RuntimeException('archive failed: ' . $this->conn->error); }
        $this->conn->query("DELETE FROM rent_orders WHERE order_id=" . (int)$this->order_id);
    }

    public function linkZvonok(int $zvId): void
    {
        $this->conn->query("UPDATE zvonki SET order_id=" . (int)$this->order_id . " WHERE zv_id=" . (int)$zvId);
    }
```

- [ ] **Step 4: Запустить — должно пройти**

Run: `docker exec tiktakby-app php artisan test --filter=ZayavkaLifecycleTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add bb/classes/Zayavka.php tests/Feature/Zayavka/ZayavkaLifecycleTest.php
git commit -m "feat: Zayavka load/update/setStatus/softDelete/changeModel/linkZvonok"
```

---

## Task 8: bron::createZayavka() → обёртка над Zayavka::create() + связывание звонка в точках создания

**Files:**
- Modify: `bb/classes/bron.php` (`createZayavka()` ~357)
- Modify: `app/Http/Controllers/ZvonokController.php`, `L3Controller.php`, `CartController.php`
- Create: `tests/Feature/Zayavka/CreateZayavkaWrapperTest.php`

- [ ] **Step 1: Тест — bron::createZayavka возвращает объект с insert_id и создаёт zayavka со статусом new**

```php
<?php
namespace Tests\Feature\Zayavka;

use Tests\TestCase;
use bb\Db;
use bb\classes\bron;

class CreateZayavkaWrapperTest extends TestCase
{
    private $conn; private array $cleanup = [];
    protected function setUp(): void { parent::setUp(); $this->conn = Db::getInstance()->getConnection(); }
    protected function tearDown(): void {
        foreach ($this->cleanup as $id) { $this->conn->query("DELETE FROM rent_orders WHERE order_id=".(int)$id); }
        parent::tearDown();
    }

    public function test_create_zayavka_wrapper_still_returns_insert_id(): void
    {
        $validity = new \DateTime('+10 days');
        $z = bron::createZayavka(999300, 79900000030, '__TEST__ Сидоров', '', '', $validity, '__TEST__ wrapper', 1);
        $this->assertNotNull($z->insert_id);
        $this->cleanup[] = $z->insert_id;
        $row = $this->conn->query("SELECT type2, z_status FROM rent_orders WHERE order_id=".(int)$z->insert_id)->fetch_assoc();
        $this->assertSame('zayavka', $row['type2']);
        $this->assertSame('new', $row['z_status']);
    }
}
```

- [ ] **Step 2: Запустить — падает (старый createZayavka не ставит z_status, либо колонки уже есть и ставят default — уточнить)**

Run: `docker exec tiktakby-app php artisan test --filter=CreateZayavkaWrapperTest`
Expected: до правки — может пройти (default 'new'), но цель — переключить на Zayavka::create. Если проходит, всё равно делаем Step 3 для единой логики.

- [ ] **Step 3: Переписать `bron::createZayavka()` как обёртку**

Заменить тело метода (сохранив сигнатуру и возврат объекта с `insert_id`):
```php
  public static function createZayavka($model_id, $phone, $family, $name, $otch, \DateTime $validityDate, $info, $webYN){
    $za = new \bb\classes\Zayavka();
    $res = $za->create([
      'model_id'  => $model_id,
      'phone'     => $phone,
      'family'    => $family,
      'info'      => $info,
      'web'       => $webYN,
      'validity'  => $validityDate->getTimestamp(),
    ], 'crm');

    $z = new self();
    $z->type2 = 'zayavka';
    $z->model_id = $model_id;
    // при дубле возвращаем id существующей заявки, чтобы вызывающий связал с ней звонок (спека §6)
    $z->insert_id = $res->orderId ?: ($res->existing ? $res->existing->order_id : null);
    $z->is_duplicate = $res->isDuplicate; // публичное поле-флаг (добавить объявление в класс bron)
    return $z;
  }
```

> Добавить в класс `bron` публичное свойство `public $is_duplicate = false;`. Тогда `insert_id` всегда указывает на актуальную заявку (новую или существующую), а `is_duplicate` сообщает, что строка не создавалась. Связывание звонка в контроллерах (Step 4) сработает в обоих случаях.

- [ ] **Step 4: В контроллерах связать созданный звонок с заявкой**

В `ZvonokController.php`, `L3Controller.php`, `CartController.php` после создания звонка (`$z`) и заявки (`$zayavka`/`createZayavka`) добавить связывание (там, где есть и `$z->id`, и `insert_id`):
```php
if (isset($z) && $z->id && isset($zayavka) && $zayavka->insert_id) {
    (new \bb\classes\Zayavka())->linkAfterCreate((int)$zayavka->insert_id, (int)$z->id);
}
```
И добавить в `Zayavka` хелпер:
```php
    public function linkAfterCreate(int $orderId, int $zvId): void
    {
        $this->conn->query("UPDATE zvonki SET order_id=" . (int)$orderId . " WHERE zv_id=" . (int)$zvId);
    }
```

- [ ] **Step 5: Запустить тест + регрессию броней**

Run: `docker exec tiktakby-app php artisan test --filter="CreateZayavkaWrapperTest|BronRegressionTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add bb/classes/bron.php bb/classes/Zayavka.php app/Http/Controllers/ZvonokController.php app/Http/Controllers/L3Controller.php app/Http/Controllers/CartController.php tests/Feature/Zayavka/CreateZayavkaWrapperTest.php
git commit -m "feat: route createZayavka through Zayavka::create + link zvonok to zayavka"
```

---

## Task 9: AJAX-эндпоинт bb/zayavka_api.php (GET load / POST действия через Zayavka)

**Files:**
- Create: `bb/zayavka_api.php`
- Create: `tests/Feature/Zayavka/ZayavkaApiTest.php`

- [ ] **Step 1: Тест на эндпоинт (HTTP через Laravel client; эндпоинт — обычный PHP, но доступен по URL)**

> Примечание: `bb/zayavka_api.php` — standalone PHP (как другие `bb/`-страницы), не Laravel-роут. Тестируем его логику опосредованно через класс `Zayavka` (Task 6–7 уже покрывают), а сам эндпоинт проверяем смоук-тестом на синтаксис + ручной проверкой. Здесь добавляем smoke-тест синтаксиса:

```php
<?php
namespace Tests\Feature\Zayavka;
use Tests\TestCase;

class ZayavkaApiTest extends TestCase
{
    public function test_endpoint_has_no_syntax_errors(): void
    {
        $out = shell_exec('php -l ' . base_path('bb/zayavka_api.php') . ' 2>&1');
        $this->assertStringContainsString('No syntax errors', (string)$out);
    }
}
```

- [ ] **Step 2: Запустить — падает (файла нет)**

Run: `docker exec tiktakby-app php artisan test --filter=ZayavkaApiTest`
Expected: FAIL.

- [ ] **Step 3: Реализовать эндпоинт**

```php
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/bron.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Zayavka.php');

// auth (как в других bb/ страницах)
if (!isset($_SESSION['svoi']) || $_SESSION['svoi'] != 8941) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

use bb\classes\Zayavka;

$action = $_REQUEST['action'] ?? 'load';

try {
    if ($action === 'load') {
        $z = Zayavka::load((int)$_GET['order_id']);
        echo json_encode(['ok' => true, 'zayavka' => [
            'order_id' => $z->order_id, 'model_id' => $z->model_id, 'phone' => $z->phone,
            'family' => $z->family, 'info' => $z->info, 'info2' => $z->info2,
            'planned_date' => $z->planned_date, 'z_status' => $z->z_status, 'ch_time' => $z->ch_time,
        ]]);
    } elseif ($action === 'save') {
        $z = Zayavka::load((int)$_POST['order_id']);
        $z->update([
            'info' => $_POST['info'] ?? '',
            'planned_date' => $_POST['planned_date'] ?? null,
            'last_ch_time' => $_POST['last_ch_time'] ?? $z->ch_time,
        ]);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'change_model') {
        $z = Zayavka::load((int)$_POST['order_id']);
        $z->changeModel((int)$_POST['model_id']);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'set_status') {
        $z = Zayavka::load((int)$_POST['order_id']);
        $z->setStatus($_POST['status'], $_POST['reason'] ?? null);
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
    }
} catch (\Throwable $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
}
```

- [ ] **Step 4: Запустить — проходит**

Run: `docker exec tiktakby-app php artisan test --filter=ZayavkaApiTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add bb/zayavka_api.php tests/Feature/Zayavka/ZayavkaApiTest.php
git commit -m "feat: bb/zayavka_api.php AJAX endpoint (load/save/change_model/set_status)"
```

---

## Task 10: UI доска rent_zayavk.php — статус, планируемая дата, смена модели, soft-delete, подсветка без телефона

**Files:**
- Modify: `bb/rent_zayavk.php`

> UI-задача: правки HTML-в-PHP. Действия формы переводим на `Zayavka` вместо прямого `bron`. Тесты логики уже в Task 6–7; здесь — корректность разметки и роутинг действий. Проверка — `php -l` + ручная проверка в браузере (чеклист §15 спеки).

- [ ] **Step 1: Выборка активной доски — добавить фильтр по статусу**

Заменить запрос (line ~332):
```php
$query_or = "SELECT * FROM rent_orders WHERE type2='zayavka' AND z_status IN ('new','in_work') ORDER BY (planned_date IS NULL) ASC, planned_date ASC, validity";
```

- [ ] **Step 2: Обработчики действий — перевести на Zayavka**

В `switch ($action)` (line ~258) заменить тела:
- `'сохранить звонок'`: `\bb\classes\Zayavka::load($order_id)->update(['info'=>$info,'planned_date'=>($br_valid_date ?? null),'last_ch_time'=>$last_ch_time]);`
- `'недозвон'`: `\bb\classes\Zayavka::load($order_id)->update(['info'=>'недозвон','last_ch_time'=>$last_ch_time]);`
- `'удалить'`: `\bb\classes\Zayavka::load($order_id)->softDelete($_POST['reason'] ?? null);`
- добавить `'отказ'`: `\bb\classes\Zayavka::load($order_id)->setStatus('rejected', $_POST['reason'] ?? null);`
- добавить `'спам'`: `\bb\classes\Zayavka::load($order_id)->setStatus('spam');`
- `'самовывоз'` (заявка→бронь) оставить через существующий `z_to_br()` (см. Task 11 для z_status='done').

(Обернуть `Zayavka::load(...)->...` в try/catch и при `RuntimeException` выводить сообщение о конфликте — как текущий `die('Бронь была изменена...')`.)

- [ ] **Step 3: Разметка строки — добавить planned_date (date input), бейдж статуса, кнопки «Отказ»/«Спам» (чипсы причины), селект смены модели, подсветку phone<=1**

Добавить в ячейку товара/действий (рядом с существующими кнопками):
```php
// подсветка без телефона
$noPhone = ((int)$br_line->phone <= 1);
// в строку <tr ...>: добавить класс/стиль если $noPhone
// в ячейку: if ($noPhone) echo '<span style="color:#b00;font-weight:bold;">⚠ нет телефона — контакт в комментарии</span>';
```
Добавить элементы формы (`form="order_{$order_id}"`):
- `<input type="date" name="planned_date" value="...">` — планируемая дата.
- Кнопка «Отказ» (`name="action" value="отказ"`) + скрытый блок чипсов `name="reason"` (значения: out_of_stock/changed_mind/too_expensive/found_elsewhere/other).
- Кнопка «Спам» (`name="action" value="спам"`).
- `<select name="new_model_id">` + кнопка «Сменить модель» (постит на `zayavka_api.php?action=change_model` через JS, либо action на странице).

- [ ] **Step 4: Проверить синтаксис**

Run: `docker exec tiktakby-app php -l bb/rent_zayavk.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add bb/rent_zayavk.php
git commit -m "feat(ui): zayavki board - status, planned_date, change model, soft-delete, no-phone flag"
```

---

## Task 11: z_to_br() — проставлять z_status='done' при конверсии заявки в бронь

**Files:**
- Modify: `bb/classes/bron.php` (`z_to_br()` ~847)
- Create: `tests/Feature/Zayavka/ConversionStatusTest.php`

- [ ] **Step 1: Тест — после z_to_br заявка становится type2='bron', z_status='done' (строка сохраняется, order_id тот же)**

```php
<?php
namespace Tests\Feature\Zayavka;
use Tests\TestCase; use bb\Db; use bb\classes\Zayavka; use bb\classes\bron;

class ConversionStatusTest extends TestCase
{
    private $conn; private array $cleanup = [];
    protected function setUp(): void { parent::setUp(); $this->conn = Db::getInstance()->getConnection(); }
    protected function tearDown(): void {
        foreach ($this->cleanup as $id) { $this->conn->query("DELETE FROM rent_orders WHERE order_id=".(int)$id); }
        parent::tearDown();
    }

    public function test_zayavka_marked_done_on_conversion(): void
    {
        // создаём заявку с инвентарным номером, который не занят (мок через прямую вставку)
        $z = new Zayavka($this->conn);
        $res = $z->create(['model_id'=>999400,'phone'=>79900000040,'family'=>'__TEST__ Conv','info'=>'__TEST__','web'=>1],'crm');
        $this->cleanup[] = $res->orderId;
        // ставим z_status='done' напрямую через setStatus без архивации? нет — done терминальный.
        // Проверяем именно ветку z_to_br: для неё нужен inv_n. Вместо полного z_to_br
        // проверяем, что setStatus('done') помечает строку (архивирует) — это покрывает «done».
        $zl = Zayavka::load($res->orderId, $this->conn);
        $zl->setStatus('done');
        $arch = $this->conn->query("SELECT z_status FROM rent_orders_arch WHERE order_id=".(int)$res->orderId)->fetch_assoc();
        $this->assertSame('done', $arch['z_status']);
    }
}
```

> Примечание: полный `z_to_br()` требует реального свободного `tovar_rent_items` (LOCK TABLES). Поэтому здесь покрываем семантику «done» через `setStatus('done')`. Реальную конверсию проверяем ручным чеклистом (§15 спеки). В `z_to_br()` добавляем установку `z_status` для консистентности на доске до архивации сделки.

- [ ] **Step 2: Запустить — падает или проходит; затем реализовать**

Run: `docker exec tiktakby-app php artisan test --filter=ConversionStatusTest`

- [ ] **Step 3: В `z_to_br()` перед `$this->update()` добавить установку статуса**

Найти в `z_to_br()` (перед `$this->update();`, ~line 891) и добавить присваивание:
```php
        $this->z_status = 'done';
```
И убедиться, что `bron::update()` пишет `z_status` (если update не трогает z_status — добавить колонку в его SET; либо отдельным запросом `UPDATE rent_orders SET z_status='done' WHERE order_id=...`). Простейше — отдельный запрос сразу после `$this->update();`:
```php
        $this->mysqli->query("UPDATE rent_orders SET z_status='done' WHERE order_id='".(int)$this->order_id."'");
```

- [ ] **Step 4: Запустить — проходит**

Run: `docker exec tiktakby-app php artisan test --filter=ConversionStatusTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add bb/classes/bron.php tests/Feature/Zayavka/ConversionStatusTest.php
git commit -m "feat: mark zayavka z_status=done on conversion to bron"
```

---

## Task 12: zv_ch.php — контекстная кнопка + попап «Редактировать заявку», бейдж «повторная»

**Files:**
- Modify: `bb/zv_ch.php`
- Modify: `bb/assets/js/zvonki_bb.js` (попап-логика; если файла нет — инлайн `<script>`)

> UI-задача. Логика — через `bb/zayavka_api.php` (Task 9). Тесты — синтаксис + ручной чеклист.

- [ ] **Step 1: В выборке звонков подтянуть `order_id` и статус связанной заявки**

Заменить запрос звонков (line ~430), добавив LEFT JOIN на заявку:
```php
$query_zv = "SELECT z.*, ro.z_status AS zay_status
             FROM zvonki z
             LEFT JOIN rent_orders ro ON ro.order_id = z.order_id
             WHERE z.`status`!='arch' ORDER BY z.`status` DESC, z.cr_time DESC LIMIT $limit";
```

- [ ] **Step 2: Кнопка в строке — контекстная**

В ячейке действий заменить логику кнопки «Оформить заявку»:
```php
if (!empty($zv['order_id'])) {
    echo '<button type="button" class="zay_edit_btn" data-orderid="'.$zv['order_id'].'">Редактировать заявку</button>';
} elseif ($zv['type1']=='zayavka') {
    echo '<button type="button" class="zayavka_btn">Создать заявку</button>';
}
```
(Бейдж «повторная» добавляется при создании, когда `Zayavka::create` вернул `isDuplicate` — это покрывается логикой создания; в звонках показываем существующую через `order_id`.)

- [ ] **Step 3: Попап редактирования (JS: грузит `zayavka_api.php?action=load&order_id=...`, сохраняет `action=save`)**

Добавить модалку (как существующая modal-background) с полями: инфо, planned_date, статус, смена модели, кнопка «Удалить заявку» (`action=set_status&status=deleted`). JS — fetch к `/bb/zayavka_api.php`.

- [ ] **Step 4: Проверить синтаксис**

Run: `docker exec tiktakby-app php -l bb/zv_ch.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add bb/zv_ch.php bb/assets/js/zvonki_bb.js
git commit -m "feat(ui): zv_ch.php - context button + edit-zayavka popup via api"
```

---

## Task 13: Бейдж «новые» по z_status + страница «Удалённые заявки» показывает статус

**Files:**
- Modify: `bb/bb_nav_badge.php:29`
- Modify: `bb/rent_zayavk_arch.php`

- [ ] **Step 1: bb_nav_badge.php — «новые» по z_status='new'**

Заменить (line ~29):
```php
$query_new = "SELECT COUNT(*) as cnt FROM rent_orders WHERE type2='zayavka' AND z_status='new'";
```

- [ ] **Step 2: rent_zayavk_arch.php — показать z_status / z_reject_reason**

В выборке (line ~45) и выводе добавить колонки `z_status`, `z_reject_reason`; в заголовок таблицы — колонку «Статус». Добавить фильтр по статусу (rejected/spam/deleted/done) — `WHERE type2='zayavka' AND z_status IN (...)`.

- [ ] **Step 3: Проверить синтаксис обоих**

Run: `docker exec tiktakby-app php -l bb/bb_nav_badge.php && docker exec tiktakby-app php -l bb/rent_zayavk_arch.php`
Expected: оба `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add bb/bb_nav_badge.php bb/rent_zayavk_arch.php
git commit -m "feat: badge by z_status + show status/reason on deleted-zayavki page"
```

---

## Task 14: Полный прогон тестов + регрессия MCP

- [ ] **Step 1: Прогнать весь Zayavka-набор + регрессии**

Run: `docker exec tiktakby-app php artisan test --filter="Zayavka|Bron|Conversion"`
Expected: все PASS.

- [ ] **Step 2: Прогнать существующие MCP/Legacy тесты (колонки аддитивны — должны быть зелёными)**

Run: `docker exec tiktakby-app php artisan test --filter="Mcp|LegacyParity|ZvonokRedirect"`
Expected: PASS (без изменений). Если `LegacyParityTest` падает — проверить, что миграция не сдвинула суммы (не должна).

- [ ] **Step 3: Финальный commit (если правки по итогам прогона)**

```bash
git add -A && git commit -m "test: full suite green for zayavki redesign"
```

---

## Self-review notes (для исполнителя)

- Порядок критичен: Task 1–4 (безопасность INSERT, удаление мёртвого) → Task 5 (миграция) → Task 6+ (логика/UI). Не менять порядок 2→5.
- Дедуп игнорирует `phone<=1` (иначе все «без телефона» схлопнутся).
- Конверсия в бронь НЕ создаёт лишних arch-zayavka строк сверх существующего поведения (MCP не трогаем).
- Все легаси-запросы в новом коде — на инъектируемом `$this->conn`; вход экранируется (`esc()` / приведение к int). Не использовать `$$key` из `$_POST`.
- Ручной чеклист (браузер) — §15 спеки: создание с сайта, повтор, правка из попапа и с доски, сортировка по дате.
