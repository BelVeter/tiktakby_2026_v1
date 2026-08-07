# Tariff History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Сохранять каждое создание, изменение и удаление тарифа в журнал `rent_tarif_history` и отдавать через MCP API данные, достаточные для анализа влияния ценовых решений на поток новых сделок.

**Architecture:** Захват изменений — из PHP-кода: класс `bb\classes\Tariff` становится единственной точкой записи в `rent_tarif_act`, каждый его метод пишет событие через `bb\classes\TariffHistory`. Событие хранит полный снапшот строки до и после, поэтому прайс на произвольную дату восстанавливается одним запросом «последнее событие с `changed_at ≤ D`». Читающая сторона — новый `App\Http\Controllers\Mcp\PricingController` и один метод в `OperationsController`.

**Tech Stack:** PHP 7.4, Laravel 8.75, MariaDB 10.6, mysqli (легаси-слой `bb/`), PHPUnit 9.

## Global Constraints

- Спека: [docs/superpowers/specs/2026-07-31-tariff-history-design.md](../specs/2026-07-31-tariff-history-design.md).
- Все команды выполняются **внутри контейнера**: `docker compose exec -T app <cmd>`. `bb\Db` жёстко подключается к хосту `db`, снаружи контейнера тесты легаси-классов не пройдут.
- Ветка `feature/tariff-history` уже создана от `origin/main`, спека в ней закоммичена. Не создавать новых веток.
- Денежные колонки журнала — `DECIMAL(11,2)`, как в `rent_tarif_act`. Не повторять `DECIMAL(11,1)` из `rent_tarif_prev`.
- Легаси-код в `bb/` использует mysqli через `\bb\Db::getInstance()->getConnection()` и `die()` при сбое запроса. Laravel-код использует `DB::` фасад. **Никогда не смешивать эти подходы в одном файле** (CLAUDE.md).
- В `routes/web.php` и `routes/api.php` запрещены замыкания — только `[Controller::class, 'method']`.
- Методика MCP API зафиксирована 2026-05-14: сделки читаются из `UNION(rent_deals_act, rent_deals_arch)`, период — по `da.cr_time`. Фильтр по разделу — только через `BaseController::itemsInRazdelSubquery()`; прямой join `subrazdel_category × razdel_subrazdel` в запросе с агрегатом даёт M×N раздутие.
- `tests/Feature/Mcp/LegacyParityTest.php` должен продолжать проходить — его ассерты не трогать.
- Тесты идут против живой dev-базы в докере (не `RefreshDatabase`). Тесты, которые пишут в `bb/`-таблицы, обязаны открывать транзакцию в `setUp()` и откатывать её в `tearDown()`.

## File Structure

**Создаются:**

| Файл | Ответственность |
|------|-----------------|
| `database/migrations/2026_07_31_000001_create_rent_tarif_history_table.php` | Схема журнала + baseline текущих тарифов + импорт `rent_tarif_prev` |
| `bb/classes/TariffHistory.php` | Единственный писатель журнала; чтение для админки; расчёт `price_per_day` |
| `app/Http/Controllers/Mcp/PricingController.php` | `/pricing/history`, `/pricing/snapshot` |
| `tests/Unit/TariffHistoryTest.php` | Поведение писателя и `Tariff` |
| `tests/Feature/Mcp/PricingTest.php` | Три эндпоинта |
| `tests/Feature/TariffHistoryMigrationTest.php` | Полнота baseline и импорта |
| `tests/Feature/TariffWriteGuardTest.php` | Нет сырых DML по `rent_tarif_act` вне `Tariff.php` |

**Модифицируются:**

| Файл | Что меняется |
|------|--------------|
| `bb/classes/Tariff.php` | `saveNew()` / `update()` пишут события; новый `delete()`; `differsFrom()`; свойство `$historySource` |
| `bb/rent_tarifs.php` | 4 ветки `switch` переходят на класс `Tariff`; снимается `$$key = get_post($key)`; добавляется блок истории |
| `bb/classes/ModelArchive.php` | Логирование `delete` при архивации модели |
| `app/Http/Controllers/Mcp/BaseController.php` | `modelInventoryAtDate()` переезжает сюда как `protected` |
| `app/Http/Controllers/Mcp/InventoryController.php` | Удаляется приватная копия `modelInventoryAtDate()` |
| `app/Http/Controllers/Mcp/OperationsController.php` | Новый метод `dealsByModel()` |
| `routes/api.php` | Три маршрута |
| `resources/openapi/mcp-v1.json` | Схемы трёх эндпоинтов |
| `docs/tariffs.md`, `docs/db_notes.md`, `CLAUDE.md`, `AGENTS.md` | Документация |

---

### Task 1: Миграция журнала, baseline и импорт legacy

**Files:**
- Create: `database/migrations/2026_07_31_000001_create_rent_tarif_history_table.php`
- Test: `tests/Feature/TariffHistoryMigrationTest.php`

**Interfaces:**
- Consumes: ничего.
- Produces: таблица `rent_tarif_history` со столбцами `id, tarif_id, model_id, change_type, changed_at, actor_user_id, actor_name, source, ip, old_step, old_kol_vo, old_kol_vo_min, old_rent_amount, old_rent_per_step, old_start_date, old_sort_num, new_step, new_kol_vo, new_kol_vo_min, new_rent_amount, new_rent_per_step, new_start_date, new_sort_num, note`. Значения `change_type`: `baseline|create|update|delete`.

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Feature/TariffHistoryMigrationTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Проверяет, что миграция журнала тарифов покрыла baseline'ом каждый живой
 * тариф и импортировала весь rent_tarif_prev.
 *
 * Числа не зашиты: на проде и в локальном дампе они разные, поэтому
 * сверяемся с фактическим содержимым базы.
 */
class TariffHistoryMigrationTest extends TestCase
{
    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('rent_tarif_history'));

        foreach ([
            'id', 'tarif_id', 'model_id', 'change_type', 'changed_at',
            'actor_user_id', 'actor_name', 'source', 'ip',
            'old_step', 'old_kol_vo', 'old_kol_vo_min', 'old_rent_amount',
            'old_rent_per_step', 'old_start_date', 'old_sort_num',
            'new_step', 'new_kol_vo', 'new_kol_vo_min', 'new_rent_amount',
            'new_rent_per_step', 'new_start_date', 'new_sort_num', 'note',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('rent_tarif_history', $column),
                "колонка {$column} отсутствует"
            );
        }
    }

    public function test_money_columns_keep_two_decimals(): void
    {
        $row = DB::selectOne("
            SELECT COLUMN_TYPE AS t
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'rent_tarif_history'
              AND COLUMN_NAME = 'new_rent_amount'
        ");
        $this->assertSame('decimal(11,2)', strtolower($row->t));
    }

    public function test_every_live_tariff_has_a_history_event(): void
    {
        $orphans = DB::selectOne("
            SELECT COUNT(*) AS n
            FROM rent_tarif_act a
            WHERE NOT EXISTS (
                SELECT 1 FROM rent_tarif_history h WHERE h.tarif_id = a.tarif_id
            )
        ");
        $this->assertSame(0, (int) $orphans->n, 'у каждого живого тарифа должно быть событие');
    }

    public function test_every_archived_tariff_was_imported(): void
    {
        $missing = DB::selectOne("
            SELECT COUNT(*) AS n
            FROM rent_tarif_prev p
            WHERE NOT EXISTS (
                SELECT 1 FROM rent_tarif_history h
                WHERE h.tarif_id = p.tarif_act_id
                  AND h.source = 'legacy_import'
            )
        ");
        $this->assertSame(0, (int) $missing->n, 'все строки rent_tarif_prev должны быть импортированы');
    }

    public function test_baseline_events_carry_no_old_values(): void
    {
        $bad = DB::selectOne("
            SELECT COUNT(*) AS n FROM rent_tarif_history
            WHERE change_type = 'baseline' AND old_rent_amount IS NOT NULL
        ");
        $this->assertSame(0, (int) $bad->n);
    }

    public function test_legacy_delete_events_carry_no_new_values(): void
    {
        $bad = DB::selectOne("
            SELECT COUNT(*) AS n FROM rent_tarif_history
            WHERE change_type = 'delete' AND new_rent_amount IS NOT NULL
        ");
        $this->assertSame(0, (int) $bad->n);
    }

    public function test_numeric_change_who_is_parsed_into_actor_user_id(): void
    {
        // change_who в rent_tarif_act хранит вперемешку id ('777', '26') и имена ('Кристина').
        $hasNumericActors = DB::selectOne("
            SELECT COUNT(*) AS n FROM rent_tarif_act WHERE change_who REGEXP '^[0-9]+$'
        ");
        if ((int) $hasNumericActors->n === 0) {
            $this->markTestSkipped('в этой базе нет тарифов с числовым change_who');
        }

        $mismatch = DB::selectOne("
            SELECT COUNT(*) AS n
            FROM rent_tarif_act a
            JOIN rent_tarif_history h
              ON h.tarif_id = a.tarif_id AND h.change_type = 'baseline'
            WHERE a.change_who REGEXP '^[0-9]+$'
              AND (h.actor_user_id IS NULL OR h.actor_user_id <> CAST(a.change_who AS SIGNED))
        ");
        $this->assertSame(0, (int) $mismatch->n);
    }
}
```

- [ ] **Step 2: Запустить тест и убедиться, что он падает**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Feature/TariffHistoryMigrationTest.php`
Expected: FAIL — `Failed asserting that false is true` (таблицы `rent_tarif_history` ещё нет).

- [ ] **Step 3: Написать миграцию**

Создать `database/migrations/2026_07_31_000001_create_rent_tarif_history_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Журнал изменений тарифов аренды.
 *
 * До сих пор история существовала только для удалений (`rent_tarif_prev`,
 * заполнялась одной веткой bb/rent_tarifs.php), а при UPDATE старое значение
 * затиралось без следа. Одно событие здесь = полный снапшот строки тарифа до
 * и после, поэтому прайс на произвольную дату восстанавливается запросом
 * «последнее событие с changed_at <= D» без проигрывания всей ленты.
 *
 * Миграция также заполняет стартовую точку:
 *   - baseline: каждая живая строка rent_tarif_act, датированная её change_date;
 *   - legacy_import: каждая строка rent_tarif_prev как событие delete.
 */
class CreateRentTarifHistoryTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rent_tarif_history')) {
            Schema::create('rent_tarif_history', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('tarif_id');
                $table->integer('model_id');
                $table->enum('change_type', ['baseline', 'create', 'update', 'delete']);
                $table->integer('changed_at');           // unix ts, как везде в легаси
                $table->integer('actor_user_id')->nullable();
                $table->string('actor_name', 128)->nullable();
                $table->string('source', 32);            // bb_admin | model_archive | migration | legacy_import | baseline
                $table->string('ip', 45)->nullable();

                $table->string('old_step', 16)->nullable();
                $table->integer('old_kol_vo')->nullable();
                $table->integer('old_kol_vo_min')->nullable();
                $table->decimal('old_rent_amount', 11, 2)->nullable();
                $table->decimal('old_rent_per_step', 11, 2)->nullable();
                $table->integer('old_start_date')->nullable();
                $table->integer('old_sort_num')->nullable();

                $table->string('new_step', 16)->nullable();
                $table->integer('new_kol_vo')->nullable();
                $table->integer('new_kol_vo_min')->nullable();
                $table->decimal('new_rent_amount', 11, 2)->nullable();
                $table->decimal('new_rent_per_step', 11, 2)->nullable();
                $table->integer('new_start_date')->nullable();
                $table->integer('new_sort_num')->nullable();

                $table->string('note', 255)->nullable();

                $table->index(['model_id', 'changed_at'], 'idx_th_model_time');
                $table->index(['tarif_id', 'changed_at'], 'idx_th_tarif_time');
                $table->index('changed_at', 'idx_th_time');
            });
        }

        // Заливка идемпотентна: пустая таблица — единственное условие.
        if (DB::table('rent_tarif_history')->count() > 0) {
            return;
        }

        // Baseline. change_who хранит вперемешку id ('777') и имена ('Кристина'),
        // поэтому разбираем: строка целиком из цифр → actor_user_id, иначе → actor_name.
        DB::statement("
            INSERT INTO rent_tarif_history (
                tarif_id, model_id, change_type, changed_at,
                actor_user_id, actor_name, source,
                new_step, new_kol_vo, new_kol_vo_min,
                new_rent_amount, new_rent_per_step, new_start_date, new_sort_num,
                note
            )
            SELECT
                a.tarif_id, a.model_id, 'baseline', a.change_date,
                CASE WHEN a.change_who REGEXP '^[0-9]+$' THEN CAST(a.change_who AS SIGNED) END,
                CASE WHEN a.change_who REGEXP '^[0-9]+$' THEN NULL ELSE NULLIF(a.change_who, '') END,
                'baseline',
                a.step, a.kol_vo, a.kol_vo_min,
                a.rent_amount, a.rent_per_step, a.start_date, a.sort_num,
                'Снимок состояния на момент внедрения журнала; датирован последней правкой строки'
            FROM rent_tarif_act a
        ");

        // Импорт старого архива удалений. rent_tarif_prev.rent_amount объявлен
        // DECIMAL(11,1) — копейки там уже округлены, восстановить их нельзя.
        DB::statement("
            INSERT INTO rent_tarif_history (
                tarif_id, model_id, change_type, changed_at,
                actor_user_id, actor_name, source,
                old_step, old_kol_vo, old_kol_vo_min,
                old_rent_amount, old_rent_per_step, old_start_date, old_sort_num,
                note
            )
            SELECT
                p.tarif_act_id, p.model_id, 'delete', p.change_date,
                CASE WHEN p.change_who REGEXP '^[0-9]+$' THEN CAST(p.change_who AS SIGNED) END,
                CASE WHEN p.change_who REGEXP '^[0-9]+$' THEN NULL ELSE NULLIF(p.change_who, '') END,
                'legacy_import',
                p.step, p.kol_vo, p.kol_vo_min,
                p.rent_amount, p.rent_per_step, p.start_date, p.sort_num,
                'Импорт rent_tarif_prev: суммы хранились с точностью до десятых, копейки утрачены'
            FROM rent_tarif_prev p
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_tarif_history');
    }
}
```

- [ ] **Step 4: Прогнать миграцию и тест**

Run:
```bash
docker compose exec -T app php artisan migrate
docker compose exec -T app ./vendor/bin/phpunit tests/Feature/TariffHistoryMigrationTest.php
```
Expected: миграция выводит `Migrated: 2026_07_31_000001_create_rent_tarif_history_table`; тест — `OK (7 tests)`.

- [ ] **Step 5: Проверить идемпотентность повторного запуска**

Run:
```bash
docker compose exec -T app php artisan migrate:rollback --step=1
docker compose exec -T app php artisan migrate
docker compose exec -T app ./vendor/bin/phpunit tests/Feature/TariffHistoryMigrationTest.php
```
Expected: тест снова `OK (7 tests)` — заливка повторилась ровно один раз, дублей нет.

- [ ] **Step 6: Коммит**

```bash
git add database/migrations/2026_07_31_000001_create_rent_tarif_history_table.php tests/Feature/TariffHistoryMigrationTest.php
git commit -m "feat(db): журнал изменений тарифов + baseline и импорт rent_tarif_prev"
```

---

### Task 2: Класс-писатель `TariffHistory`

**Files:**
- Create: `bb/classes/TariffHistory.php`
- Test: `tests/Unit/TariffHistoryTest.php`

**Interfaces:**
- Consumes: таблица `rent_tarif_history` (Task 1); существующий `bb\classes\Tariff` с публичными полями `tarif_id, model_id, start_date (\DateTime), step, kol_vo, kol_vo_min, rent_amount, rent_per_step, sort_num`.
- Produces:
  - `TariffHistory::stepDays(string $step): int` — `day=1, week=7, month=30, year=365`, иначе `0`
  - `TariffHistory::pricePerDay($rentAmount, string $step, $kolVo): ?float` — `null`, если период нулевой
  - `TariffHistory::log(string $changeType, ?Tariff $before, ?Tariff $after, string $source = self::SOURCE_BB_ADMIN, ?string $note = null): void`
  - `TariffHistory::forModel(int $modelId, int $limit = 50): array` — строки, новые сверху
  - Константы `TYPE_BASELINE|TYPE_CREATE|TYPE_UPDATE|TYPE_DELETE`, `SOURCE_BB_ADMIN|SOURCE_MODEL_ARCHIVE|SOURCE_MIGRATION`

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Unit/TariffHistoryTest.php`:

```php
<?php

namespace Tests\Unit;

use bb\classes\Tariff;
use bb\classes\TariffHistory;
use bb\Db;
use Tests\TestCase;

/**
 * Тесты идут против живой dev-базы (проект не использует RefreshDatabase),
 * поэтому каждый тест выполняется внутри транзакции и откатывается.
 */
class TariffHistoryTest extends TestCase
{
    /** Модель-песочница: id, которого заведомо нет в каталоге. */
    private const SANDBOX_MODEL_ID = 999999;

    protected function setUp(): void
    {
        parent::setUp();
        Db::getInstance()->getConnection()->query('START TRANSACTION');

        // Tariff::saveNew()/update() читают User::getCurrentUser()->id_user.
        // Без сессии getCurrentUser() возвращает false и change_who уедет пустым,
        // поэтому подставляем реального пользователя (id 26 — «КристинаН»).
        $_SESSION['user_id']  = 26;
        $_SESSION['user_fio'] = 'PHPUnit';
    }

    protected function tearDown(): void
    {
        Db::getInstance()->getConnection()->query('ROLLBACK');
        unset($_SESSION['user_id'], $_SESSION['user_fio']);
        parent::tearDown();
    }

    private function makeTariff(float $amount = 100.00, int $kolVo = 2): Tariff
    {
        $t = new Tariff();
        $t->tarif_id      = 0;
        $t->model_id      = self::SANDBOX_MODEL_ID;
        $t->start_date    = new \DateTime('2026-01-01');
        $t->step          = 'week';
        $t->kol_vo        = $kolVo;
        $t->kol_vo_min    = $kolVo;
        $t->rent_amount   = $amount;
        $t->rent_per_step = round($amount / $kolVo, 2);
        $t->sort_num      = 7;
        return $t;
    }

    private function events(): array
    {
        return TariffHistory::forModel(self::SANDBOX_MODEL_ID, 100);
    }

    public function test_step_days_covers_all_units(): void
    {
        $this->assertSame(1,   TariffHistory::stepDays('day'));
        $this->assertSame(7,   TariffHistory::stepDays('week'));
        $this->assertSame(30,  TariffHistory::stepDays('month'));
        $this->assertSame(365, TariffHistory::stepDays('year'));
        $this->assertSame(0,   TariffHistory::stepDays('fortnight'));
    }

    public function test_price_per_day_divides_by_full_period(): void
    {
        // 2 недели за 85.00 → 85 / 14 = 6.07
        $this->assertSame(6.07, TariffHistory::pricePerDay(85.00, 'week', 2));
        $this->assertSame(50.0, TariffHistory::pricePerDay(50.00, 'day', 1));
    }

    public function test_price_per_day_is_null_for_zero_period(): void
    {
        $this->assertNull(TariffHistory::pricePerDay(50.00, 'week', 0));
        $this->assertNull(TariffHistory::pricePerDay(50.00, 'fortnight', 2));
    }

    public function test_log_create_writes_only_new_values(): void
    {
        $t = $this->makeTariff(120.00);
        $t->tarif_id = 12345;

        TariffHistory::log(TariffHistory::TYPE_CREATE, null, $t, TariffHistory::SOURCE_BB_ADMIN);

        $events = $this->events();
        $this->assertCount(1, $events);
        $this->assertSame('create', $events[0]['change_type']);
        $this->assertSame('bb_admin', $events[0]['source']);
        $this->assertNull($events[0]['old_rent_amount']);
        $this->assertSame('120.00', $events[0]['new_rent_amount']);
        $this->assertSame(12345, (int) $events[0]['tarif_id']);
    }

    public function test_log_update_writes_both_sides(): void
    {
        $before = $this->makeTariff(85.00);
        $before->tarif_id = 12345;
        $after = $this->makeTariff(95.00);
        $after->tarif_id = 12345;

        TariffHistory::log(TariffHistory::TYPE_UPDATE, $before, $after, TariffHistory::SOURCE_BB_ADMIN);

        $events = $this->events();
        $this->assertSame('85.00', $events[0]['old_rent_amount']);
        $this->assertSame('95.00', $events[0]['new_rent_amount']);
    }

    public function test_log_delete_writes_only_old_values(): void
    {
        $before = $this->makeTariff(85.00);
        $before->tarif_id = 12345;

        TariffHistory::log(TariffHistory::TYPE_DELETE, $before, null, TariffHistory::SOURCE_MODEL_ARCHIVE);

        $events = $this->events();
        $this->assertSame('delete', $events[0]['change_type']);
        $this->assertSame('model_archive', $events[0]['source']);
        $this->assertSame('85.00', $events[0]['old_rent_amount']);
        $this->assertNull($events[0]['new_rent_amount']);
    }

    public function test_for_model_returns_newest_first(): void
    {
        $t = $this->makeTariff(100.00);
        $t->tarif_id = 12345;
        TariffHistory::log(TariffHistory::TYPE_CREATE, null, $t, TariffHistory::SOURCE_BB_ADMIN);
        TariffHistory::log(TariffHistory::TYPE_UPDATE, $t, $t, TariffHistory::SOURCE_BB_ADMIN);

        $events = $this->events();
        $this->assertCount(2, $events);
        $this->assertSame('update', $events[0]['change_type'], 'новое событие должно быть первым');
    }

    public function test_actor_is_taken_from_session(): void
    {
        $t = $this->makeTariff();
        $t->tarif_id = 12345;
        TariffHistory::log(TariffHistory::TYPE_CREATE, null, $t, TariffHistory::SOURCE_BB_ADMIN);

        $events = $this->events();
        $this->assertSame(26, (int) $events[0]['actor_user_id']);
        $this->assertSame('PHPUnit', $events[0]['actor_name']);
    }

    public function test_actor_is_null_without_session(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_fio']);

        $t = $this->makeTariff();
        $t->tarif_id = 12345;
        TariffHistory::log(TariffHistory::TYPE_CREATE, null, $t, TariffHistory::SOURCE_MIGRATION);

        $events = $this->events();
        $this->assertNull($events[0]['actor_user_id']);
        $this->assertNull($events[0]['actor_name']);
    }
}
```

- [ ] **Step 2: Запустить тест и убедиться, что он падает**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Unit/TariffHistoryTest.php`
Expected: FAIL — `Class "bb\classes\TariffHistory" not found`.

- [ ] **Step 3: Написать класс**

Создать `bb/classes/TariffHistory.php`:

```php
<?php

namespace bb\classes;

use bb\Db;

/**
 * Журнал изменений тарифов — единственный писатель `rent_tarif_history`.
 *
 * Одно событие хранит полный снимок строки тарифа до и после изменения, а не
 * набор изменённых полей. Так восстановление прайса на дату сводится к
 * «последнее событие с changed_at <= D», без проигрывания ленты с начала.
 *
 * Вызывается из `Tariff` (создание/правка/удаление) и `ModelArchive`
 * (снятие тарифов при архивации модели). Транзакциями управляет вызывающий
 * код: и `bb/rent_tarifs.php`, и `ModelArchive::archive()` уже оборачивают
 * свои операции, а вложенный START TRANSACTION в mysqli неявно коммитит
 * внешнюю — поэтому здесь их нет.
 */
class TariffHistory
{
    const TYPE_BASELINE = 'baseline';
    const TYPE_CREATE   = 'create';
    const TYPE_UPDATE   = 'update';
    const TYPE_DELETE   = 'delete';

    const SOURCE_BB_ADMIN      = 'bb_admin';
    const SOURCE_MODEL_ARCHIVE = 'model_archive';
    const SOURCE_MIGRATION     = 'migration';

    /** Дней в одном шаге тарифа. Конвертация фиксированная — см. docs/tariffs.md. */
    public static function stepDays($step)
    {
        switch ($step) {
            case 'day':   return 1;
            case 'week':  return 7;
            case 'month': return 30;
            case 'year':  return 365;
            default:      return 0;
        }
    }

    /**
     * Цена за день — нормализованная метрика для сравнения тарифов с разным шагом.
     *
     * @return float|null null, если период нулевой
     */
    public static function pricePerDay($rentAmount, $step, $kolVo)
    {
        $days = self::stepDays($step) * (int) $kolVo;
        if ($days <= 0) {
            return null;
        }
        return round((float) $rentAmount / $days, 2);
    }

    /**
     * @param string      $changeType одна из TYPE_* констант
     * @param Tariff|null $before     состояние до (null для create/baseline)
     * @param Tariff|null $after      состояние после (null для delete)
     * @param string      $source     одна из SOURCE_* констант
     * @param string|null $note
     */
    public static function log($changeType, $before, $after, $source = self::SOURCE_BB_ADMIN, $note = null)
    {
        $subject = $after ?: $before;
        if (!$subject) {
            return;
        }

        $mysqli = Db::getInstance()->getConnection();

        $columns = [
            'tarif_id'      => (int) $subject->tarif_id,
            'model_id'      => (int) $subject->model_id,
            'change_type'   => self::quote($mysqli, $changeType),
            'changed_at'    => time(),
            'actor_user_id' => self::actorUserId(),
            'actor_name'    => self::quote($mysqli, self::actorName()),
            'source'        => self::quote($mysqli, $source),
            'ip'            => self::quote($mysqli, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null),
            'note'          => self::quote($mysqli, $note),
        ];

        $columns = array_merge($columns, self::snapshotColumns($mysqli, 'old', $before));
        $columns = array_merge($columns, self::snapshotColumns($mysqli, 'new', $after));

        $query = 'INSERT INTO rent_tarif_history (' . implode(', ', array_keys($columns)) . ') '
               . 'VALUES (' . implode(', ', array_values($columns)) . ')';

        if (!$mysqli->query($query)) {
            die('Сбой при записи истории тарифов: ' . $query . ' (' . $mysqli->errno . ') ' . $mysqli->error);
        }
    }

    /**
     * Последние события по модели, новые сверху.
     *
     * @return array[] ассоциативные строки таблицы
     */
    public static function forModel($modelId, $limit = 50)
    {
        $modelId = (int) $modelId;
        $limit   = max(1, (int) $limit);

        $mysqli = Db::getInstance()->getConnection();
        $query  = "SELECT * FROM rent_tarif_history
                   WHERE model_id = {$modelId}
                   ORDER BY changed_at DESC, id DESC
                   LIMIT {$limit}";

        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->errno . ') ' . $mysqli->error);
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Колонки снимка одной стороны события.
     *
     * @param string      $prefix 'old' | 'new'
     * @param Tariff|null $t
     * @return array<string,string|int>
     */
    private static function snapshotColumns($mysqli, $prefix, $t)
    {
        if (!$t) {
            return [
                $prefix . '_step'          => 'NULL',
                $prefix . '_kol_vo'        => 'NULL',
                $prefix . '_kol_vo_min'    => 'NULL',
                $prefix . '_rent_amount'   => 'NULL',
                $prefix . '_rent_per_step' => 'NULL',
                $prefix . '_start_date'    => 'NULL',
                $prefix . '_sort_num'      => 'NULL',
            ];
        }

        return [
            $prefix . '_step'          => self::quote($mysqli, $t->step),
            $prefix . '_kol_vo'        => (int) $t->kol_vo,
            $prefix . '_kol_vo_min'    => (int) $t->kol_vo_min,
            $prefix . '_rent_amount'   => "'" . number_format((float) $t->rent_amount, 2, '.', '') . "'",
            $prefix . '_rent_per_step' => "'" . number_format((float) $t->rent_per_step, 2, '.', '') . "'",
            $prefix . '_start_date'    => $t->start_date instanceof \DateTime ? $t->start_date->getTimestamp() : 0,
            $prefix . '_sort_num'      => (int) $t->sort_num,
        ];
    }

    /** @return string SQL-литерал: экранированная строка либо NULL */
    private static function quote($mysqli, $value)
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }
        return "'" . $mysqli->real_escape_string($value) . "'";
    }

    /** @return int|string id пользователя из сессии либо SQL NULL */
    private static function actorUserId()
    {
        return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0
            ? (int) $_SESSION['user_id']
            : 'NULL';
    }

    /** @return string|null ФИО из сессии */
    private static function actorName()
    {
        return isset($_SESSION['user_fio']) && $_SESSION['user_fio'] !== ''
            ? $_SESSION['user_fio']
            : null;
    }
}
```

- [ ] **Step 4: Запустить тест и убедиться, что он проходит**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Unit/TariffHistoryTest.php`
Expected: `OK (9 tests)`.

- [ ] **Step 5: Коммит**

```bash
git add bb/classes/TariffHistory.php tests/Unit/TariffHistoryTest.php
git commit -m "feat(bb): класс TariffHistory — единственный писатель журнала тарифов"
```

---

### Task 3: `Tariff` пишет события при create / update / delete

**Files:**
- Modify: `bb/classes/Tariff.php:49-75`
- Test: `tests/Unit/TariffHistoryTest.php` (дописать)

**Interfaces:**
- Consumes: `TariffHistory::log()`, константы `TYPE_*` / `SOURCE_*` из Task 2.
- Produces:
  - `Tariff->historySource` — публичное свойство, по умолчанию `TariffHistory::SOURCE_BB_ADMIN`
  - `Tariff::delete(): bool` — удаляет строку и пишет событие `delete`
  - `Tariff->differsFrom(Tariff $other): bool` — сравнение значимых полей

- [ ] **Step 1: Дописать падающие тесты**

Добавить в `tests/Unit/TariffHistoryTest.php` (внутрь класса):

```php
    public function test_save_new_writes_create_event(): void
    {
        $t = $this->makeTariff(140.00);
        $t->save();

        $this->assertGreaterThan(0, $t->tarif_id, 'save() должен проставить tarif_id');

        $events = $this->events();
        $this->assertCount(1, $events);
        $this->assertSame('create', $events[0]['change_type']);
        $this->assertSame('140.00', $events[0]['new_rent_amount']);
        $this->assertSame($t->tarif_id, (int) $events[0]['tarif_id']);
    }

    public function test_update_writes_before_and_after(): void
    {
        $t = $this->makeTariff(140.00);
        $t->save();

        $t->rent_amount   = 160.00;
        $t->rent_per_step = 80.00;
        $t->save();

        $events = $this->events();
        $this->assertCount(2, $events);
        $this->assertSame('update', $events[0]['change_type']);
        $this->assertSame('140.00', $events[0]['old_rent_amount']);
        $this->assertSame('160.00', $events[0]['new_rent_amount']);
    }

    public function test_update_without_real_change_writes_nothing(): void
    {
        $t = $this->makeTariff(140.00);
        $t->save();
        $t->save();   // повторное сохранение без изменений

        $this->assertCount(1, $this->events(), 'пустой UPDATE не должен порождать событие');
    }

    public function test_delete_writes_delete_event_and_removes_row(): void
    {
        $t = $this->makeTariff(140.00);
        $t->save();
        $tarifId = $t->tarif_id;

        $t->delete();

        $this->assertFalse(Tariff::getById($tarifId), 'строка должна быть удалена');

        $events = $this->events();
        $this->assertSame('delete', $events[0]['change_type']);
        $this->assertSame('140.00', $events[0]['old_rent_amount']);
        $this->assertNull($events[0]['new_rent_amount']);
    }

    public function test_history_source_is_carried_into_event(): void
    {
        $t = $this->makeTariff(140.00);
        $t->historySource = TariffHistory::SOURCE_MODEL_ARCHIVE;
        $t->save();

        $this->assertSame('model_archive', $this->events()[0]['source']);
    }
```

- [ ] **Step 2: Запустить тесты и убедиться, что они падают**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Unit/TariffHistoryTest.php`
Expected: FAIL — `Call to undefined method bb\classes\Tariff::delete()` и отсутствие событий у `save()`.

- [ ] **Step 3: Изменить `bb/classes/Tariff.php`**

Добавить публичное свойство рядом с остальными (после `public $change_who;`):

```php
    /**
     * Откуда пришло изменение — попадает в журнал `rent_tarif_history`.
     * @var string одна из TariffHistory::SOURCE_* констант
     */
    public $historySource = TariffHistory::SOURCE_BB_ADMIN;
```

Заменить `saveNew()` и `update()` и добавить два новых метода:

```php
    private function saveNew() {
        $mysqli = Db::getInstance()->getConnection();
        $query_new_tarif = "INSERT INTO rent_tarif_act
            SET model_id='$this->model_id', start_date='".$this->start_date->getTimestamp()."', step='$this->step', kol_vo='$this->kol_vo', kol_vo_min='$this->kol_vo_min', rent_amount='$this->rent_amount', rent_per_step='$this->rent_per_step', sort_num='$this->sort_num', change_date='".time()."', change_who='".User::getCurrentUser()->id_user."'";
        if (!$mysqli->query($query_new_tarif)) {die('Сбой при доступе к базе данных: '.$query_new_tarif.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $this->tarif_id=$mysqli->insert_id;

        TariffHistory::log(TariffHistory::TYPE_CREATE, null, $this, $this->historySource);

        return true;
    }

    private function update(){
        $before = self::getById($this->tarif_id);

        // Пустой UPDATE не должен засорять журнал: форма сохраняется и тогда,
        // когда оператор ничего не поменял.
        if ($before && !$this->differsFrom($before)) {
            return true;
        }

        $mysqli = Db::getInstance()->getConnection();
        $query_new_tarif = "UPDATE rent_tarif_act
            SET model_id='$this->model_id', start_date='".$this->start_date->getTimestamp()."', step='$this->step', kol_vo='$this->kol_vo', kol_vo_min='$this->kol_vo_min', rent_amount='$this->rent_amount', rent_per_step='$this->rent_per_step', sort_num='$this->sort_num', change_date='".time()."', change_who='".User::getCurrentUser()->id_user."'
            WHERE tarif_id='$this->tarif_id'";
        if (!$mysqli->query($query_new_tarif)) {die('Сбой при доступе к базе данных: '.$query_new_tarif.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        TariffHistory::log(TariffHistory::TYPE_UPDATE, $before, $this, $this->historySource);

        return true;
    }

    /**
     * Удаляет тариф и пишет событие в журнал.
     *
     * Раньше удаление жило прямо в bb/rent_tarifs.php и складывало строку в
     * `rent_tarif_prev`. Теперь архив — это журнал; писать в старую таблицу
     * перестали, но её данные импортированы миграцией.
     *
     * @return bool
     */
    public function delete(){
        if ($this->tarif_id < 1) {
            return false;
        }

        $before = self::getById($this->tarif_id);
        if (!$before) {
            return false;
        }

        $mysqli = Db::getInstance()->getConnection();
        $query_del = "DELETE FROM rent_tarif_act WHERE tarif_id='".(int) $this->tarif_id."'";
        if (!$mysqli->query($query_del)) {die('Сбой при доступе к базе данных: '.$query_del.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        TariffHistory::log(TariffHistory::TYPE_DELETE, $before, null, $this->historySource);

        return true;
    }

    /**
     * Отличается ли этот тариф от другого по значимым полям.
     * `change_date` и `change_who` не сравниваются — они меняются всегда.
     *
     * @param Tariff $other
     * @return bool
     */
    public function differsFrom($other){
        if (!$other) {
            return true;
        }

        $thisStart  = $this->start_date instanceof \DateTime ? $this->start_date->getTimestamp() : 0;
        $otherStart = $other->start_date instanceof \DateTime ? $other->start_date->getTimestamp() : 0;

        return (string) $this->step !== (string) $other->step
            || (int) $this->kol_vo !== (int) $other->kol_vo
            || (int) $this->kol_vo_min !== (int) $other->kol_vo_min
            || (int) $this->sort_num !== (int) $other->sort_num
            || $thisStart !== $otherStart
            || number_format((float) $this->rent_amount, 2, '.', '') !== number_format((float) $other->rent_amount, 2, '.', '')
            || number_format((float) $this->rent_per_step, 2, '.', '') !== number_format((float) $other->rent_per_step, 2, '.', '');
    }
```

- [ ] **Step 4: Запустить тесты и убедиться, что они проходят**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Unit/TariffHistoryTest.php`
Expected: `OK (14 tests)`.

- [ ] **Step 5: Проверить синтаксис изменённого файла**

Run: `docker compose exec -T app php -l bb/classes/Tariff.php`
Expected: `No syntax errors detected in bb/classes/Tariff.php`

- [ ] **Step 6: Коммит**

```bash
git add bb/classes/Tariff.php tests/Unit/TariffHistoryTest.php
git commit -m "feat(bb): Tariff пишет create/update/delete в журнал изменений"
```

---

### Task 4: `bb/rent_tarifs.php` — запись только через класс

**Files:**
- Modify: `bb/rent_tarifs.php:336-440`
- Test: `tests/Feature/TariffWriteGuardTest.php` (создать)

**Interfaces:**
- Consumes: `Tariff::save()`, `Tariff::delete()`, `Tariff::getById()`, `$historySource` (Task 3).
- Produces: ничего для последующих задач; файл перестаёт содержать сырые DML по `rent_tarif_act`.

- [ ] **Step 1: Написать падающий guard-тест**

Создать `tests/Feature/TariffWriteGuardTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Захват истории тарифов сделан на уровне кода, а не триггеров БД, поэтому
 * любой сырой INSERT/UPDATE/DELETE по rent_tarif_act мимо bb/classes/Tariff.php
 * пройдёт мимо журнала. Этот тест ловит такие места.
 *
 * Исключения:
 *   - bb/classes/Tariff.php — единственная легальная точка записи;
 *   - database/migrations/  — разовые миграции каталога правят таблицу
 *     напрямую по своей природе (слияние и чистка моделей).
 */
class TariffWriteGuardTest extends TestCase
{
    public function test_no_raw_tariff_writes_outside_tariff_class(): void
    {
        $root = dirname(__DIR__, 2);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $offenders = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace($root . '/', '', $file->getPathname());

            if (strpos($path, 'vendor/') === 0
                || strpos($path, 'node_modules/') === 0
                || strpos($path, 'storage/') === 0
                || strpos($path, 'tests/') === 0
                || strpos($path, 'database/migrations/') === 0
                || $path === 'bb/classes/Tariff.php') {
                continue;
            }

            $code = file_get_contents($file->getPathname());
            if (preg_match('/(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?rent_tarif_act`?/i', $code)) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders,
            'сырые DML по rent_tarif_act должны идти только через bb/classes/Tariff.php');
    }
}
```

- [ ] **Step 2: Запустить тест и убедиться, что он падает**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Feature/TariffWriteGuardTest.php`
Expected: FAIL — в списке нарушителей `bb/rent_tarifs.php`.

- [ ] **Step 3: Переписать блок обработки POST**

В `bb/rent_tarifs.php` заменить строки 336-440 (от `<?php` после закрывающего `</script>` до конца `}//end of if`) на:

```php
<?php

$tarif_id=''; //для подсветки откорректированного тарифа
$cat_def['tovar_rent_cat_id']='';//нулевое значение

// Раньше здесь стоял `$$key = get_post($key)` — весь $_POST разворачивался в
// переменные скоупа без белого списка. Теперь читаем только известные поля.
$action      = isset($_POST['action'])      ? $_POST['action']              : null;
$model_id    = isset($_POST['model_id'])    ? (int) $_POST['model_id']      : null;
$tarif_id_in = isset($_POST['tarif_id'])    ? (int) $_POST['tarif_id']      : 0;
$item_id     = isset($_POST['item_id'])     ? (int) $_POST['item_id']       : null;
$start_date  = isset($_POST['start_date'])  ? (string) $_POST['start_date'] : '';
$step        = isset($_POST['step'])        ? (string) $_POST['step']       : 'week';
$kol_vo      = isset($_POST['kol_vo'])      ? (int) $_POST['kol_vo']        : 0;
$kol_vo_min  = isset($_POST['kol_vo_min'])  ? (int) $_POST['kol_vo_min']    : 0;
$rent_amount   = isset($_POST['rent_amount'])   ? (float) str_replace(',', '.', $_POST['rent_amount'])   : 0;
$rent_per_step = isset($_POST['rent_per_step']) ? (float) str_replace(',', '.', $_POST['rent_per_step']) : 0;

if (!in_array($step, ['day', 'week', 'month', 'year'], true)) {
    $step = 'week';
}

if (isset($_POST['action'])) {

switch ($action) {

	case 'внести новый тариф':
        \bb\Db::startTransaction();
            $t = new \bb\classes\Tariff();
            $t->tarif_id      = 0;
            $t->model_id      = $model_id;
            $t->start_date    = (new \DateTime())->setTimestamp(strtotime($start_date));
            $t->step          = $step;
            $t->kol_vo        = $kol_vo;
            $t->kol_vo_min    = $kol_vo_min;
            $t->rent_amount   = $rent_amount;
            $t->rent_per_step = $rent_per_step;
            $t->sort_num      = sort_num($step);
            $t->save();
        \bb\Db::commitTransaction();

        $tarif_id = $t->tarif_id;

	break;


	case 'сохранить тариф':
        $t = \bb\classes\Tariff::getById($tarif_id_in);
        if ($t) {
            \bb\Db::startTransaction();
                $t->start_date    = (new \DateTime())->setTimestamp(strtotime($start_date));
                $t->step          = $step;
                $t->kol_vo        = $kol_vo;
                $t->kol_vo_min    = $kol_vo_min;
                $t->rent_amount   = $rent_amount;
                $t->rent_per_step = $rent_per_step;
                $t->sort_num      = sort_num($step);
                $t->save();
            \bb\Db::commitTransaction();

            $tarif_id = $t->tarif_id;
        }

	break;

    case 'авто расчет':
        $t_base = \bb\classes\Tariff::getById($tarif_id_in);
        $t3 = clone $t_base;
        $t2 = clone $t_base;
        $t1 = clone $t_base;

        $t3->tarif_id=null;
        $t3->kol_vo=3;
        $t3->rent_amount = $t_base->rent_amount * 0.9;

        $t2->tarif_id=null;
        $t2->kol_vo=2;
        $t2->rent_amount = $t3->rent_amount * 0.85;

        $t1->tarif_id=null;
        $t1->kol_vo=1;
        $t1->rent_amount = $t2->rent_amount * 0.7;

        $t1->t4AutoCalcAndFill();
        $t2->t4AutoCalcAndFill();
        $t3->t4AutoCalcAndFill();
        $t_base->t4AutoCalcAndFill();

        \bb\Db::startTransaction();
            $t1->hardSave();
            $t2->hardSave();
            $t3->hardSave();
        \bb\Db::commitTransaction();

    break;


	case 'удалить':
        $t = \bb\classes\Tariff::getById($tarif_id_in);
        if ($t) {
            \bb\Db::startTransaction();
                $t->delete();
            \bb\Db::commitTransaction();
        }

	break;


}//end of switch
}//end of if
```

Ниже по файлу, в блоке выборки модели, заменить чтение `$model_id` — оно уже приходит из нового парсинга, но при GET-переходе его нет. Заменить строку 444:

```php
if (isset($model_id) && $model_id!=0) {
```

на:

```php
if (!empty($model_id)) {
```

и строку 502:

```php
if (!isset($model_id) || $model_id=='') {
```

на:

```php
if (empty($model_id)) {
```

и строку 579:

```php
if (isset($model_id) && $model_id>0) {
```

на:

```php
if (!empty($model_id) && $model_id > 0) {
```

Также заменить строку 490 (ссылка «Выбрать другую модель»), где было `isset($model_id)`:

```php
	<a class="div_item" id="new_mod_ch" href="/bb/rent_tarifs.php" '.(!empty($model_id) ? '' : 'style="display:none"').'>Выбрать другую модель</a>
```

и строку 709 (`if (isset($item_id))`) на:

```php
if (!empty($item_id)) {
```

Удалить ставшую ненужной функцию `get_post()` в конце файла (строки 737-742).

- [ ] **Step 4: Проверить синтаксис и прогнать guard-тест**

Run:
```bash
docker compose exec -T app php -l bb/rent_tarifs.php
docker compose exec -T app ./vendor/bin/phpunit tests/Feature/TariffWriteGuardTest.php
```
Expected: `No syntax errors detected`; тест — `OK (1 test)`.

- [ ] **Step 5: Ручная проверка админки**

Открыть `http://localhost/bb/rent_tarifs.php`, выбрать любую модель с тарифами. Проверить четыре действия: «внести новый тариф», «корректировать» → «сохранить тариф», «авто расчет» (доступен для week×4), «удалить». После каждого выполнить:

```bash
docker compose exec -T db mysql -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT id, tarif_id, change_type, source, old_rent_amount, new_rent_amount, actor_name FROM rent_tarif_history ORDER BY id DESC LIMIT 5;"
```
Expected: по одному событию нужного типа на каждое действие; `source='bb_admin'`; `actor_name` — ФИО залогиненного пользователя.

- [ ] **Step 6: Коммит**

```bash
git add bb/rent_tarifs.php tests/Feature/TariffWriteGuardTest.php
git commit -m "refactor(bb): rent_tarifs.php пишет тарифы только через класс Tariff"
```

---

### Task 5: Логирование при архивации модели

**Files:**
- Modify: `bb/classes/ModelArchive.php`
- Test: `tests/Unit/TariffHistoryTest.php` (дописать)

**Interfaces:**
- Consumes: `Tariff::getById()`, `Tariff->delete()`, `TariffHistory::SOURCE_MODEL_ARCHIVE`.
- Produces: ничего для последующих задач.

- [ ] **Step 1: Прочитать текущий код удаления спутников**

Run: `docker compose exec -T app grep -n "SATELLITE_TABLES\|DELETE FROM\|collectSatellites" bb/classes/ModelArchive.php`
Expected: видно, где спутники удаляются циклом по `SATELLITE_TABLES`.

- [ ] **Step 2: Написать падающий тест**

Добавить в `tests/Unit/TariffHistoryTest.php`:

```php
    public function test_model_archive_logs_tariff_deletion(): void
    {
        // Готовим модель-песочницу с одним тарифом.
        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query("INSERT INTO tovar_rent (tovar_rent_id, tovar_rent_cat_id, producer, model, color)
                        VALUES (" . self::SANDBOX_MODEL_ID . ", 0, 'TestProducer', 'TestModel', 'TestColor')");

        $t = $this->makeTariff(200.00);
        $t->save();

        $result = \bb\classes\ModelArchive::archive(self::SANDBOX_MODEL_ID, 26);
        $this->assertTrue($result === true, is_string($result) ? $result : 'архивация должна пройти');

        $deletes = array_values(array_filter($this->events(), static function ($e) {
            return $e['change_type'] === 'delete';
        }));

        $this->assertCount(1, $deletes, 'архивация модели должна залогировать удаление тарифа');
        $this->assertSame('model_archive', $deletes[0]['source']);
        $this->assertSame('200.00', $deletes[0]['old_rent_amount']);
    }
```

- [ ] **Step 3: Запустить тест и убедиться, что он падает**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Unit/TariffHistoryTest.php --filter test_model_archive_logs_tariff_deletion`
Expected: FAIL — `архивация модели должна залогировать удаление тарифа`, найдено 0 событий.

- [ ] **Step 4: Добавить логирование в `ModelArchive::archive()`**

В `bb/classes/ModelArchive.php`, внутри `archive()`, сразу после `Db::startTransaction();` и до удаления спутников, вставить:

```php
        // Тарифы уезжают в arch_snapshot вместе с остальными спутниками, но
        // журнал изменений должен видеть их исчезновение как обычное удаление —
        // иначе прайс на дату после архивации не восстановится.
        foreach (self::tariffIdsForModel($mysqli, $modelId) as $tarifId) {
            $tariff = Tariff::getById($tarifId);
            if ($tariff) {
                TariffHistory::log(
                    TariffHistory::TYPE_DELETE,
                    $tariff,
                    null,
                    TariffHistory::SOURCE_MODEL_ARCHIVE,
                    'Модель перенесена в архив'
                );
            }
        }
```

Добавить приватный метод в конец класса:

```php
    /**
     * @return int[] id тарифов модели
     */
    private static function tariffIdsForModel($mysqli, $modelId)
    {
        $modelId = (int) $modelId;
        $res = $mysqli->query("SELECT tarif_id FROM rent_tarif_act WHERE model_id='{$modelId}'");
        if (!$res) {
            return [];
        }

        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['tarif_id'];
        }
        return $ids;
    }
```

- [ ] **Step 5: Запустить тест и убедиться, что он проходит**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Unit/TariffHistoryTest.php`
Expected: `OK (15 tests)`.

- [ ] **Step 6: Коммит**

```bash
git add bb/classes/ModelArchive.php tests/Unit/TariffHistoryTest.php
git commit -m "feat(bb): архивация модели логирует удаление её тарифов"
```

---

### Task 6: Блок истории на странице тарифов

**Files:**
- Modify: `bb/rent_tarifs.php` (вывод после таблицы тарифов)

**Interfaces:**
- Consumes: `TariffHistory::forModel($modelId, 50)`, `TariffHistory::pricePerDay()`.
- Produces: ничего для последующих задач.

- [ ] **Step 1: Добавить рендер истории**

В `bb/rent_tarifs.php`, сразу после блока `<div id="tarif_new_form">...</div>` (перед `if (!empty($item_id))`), вставить:

```php
$history = \bb\classes\TariffHistory::forModel($model_id, 50);

echo '<br /><input type="button" value="история изменений (' . count($history) . ')"
        onclick="var d=document.getElementById(\'tarif_history\'); d.style.display = (d.style.display==\'none\' ? \'\' : \'none\');" />';

echo '<div id="tarif_history" style="display:none">';

if (count($history) === 0) {
    echo '<br /><strong>По этой модели изменений пока не зафиксировано.</strong><br />';
} else {
    echo '<table border="1" cellspacing="0">
    <tr>
        <td>когда</td>
        <td>кто</td>
        <td>что</td>
        <td>id тарифа</td>
        <td>было</td>
        <td>стало</td>
        <td>изменение</td>
    </tr>';

    foreach ($history as $h) {
        $oldText = $h['old_rent_amount'] === null
            ? '—'
            : $h['old_kol_vo'] . ' ' . r_step($h['old_step']) . ' = ' . $h['old_rent_amount'];
        $newText = $h['new_rent_amount'] === null
            ? '—'
            : $h['new_kol_vo'] . ' ' . r_step($h['new_step']) . ' = ' . $h['new_rent_amount'];

        $deltaText = '—';
        if ($h['old_rent_amount'] !== null && $h['new_rent_amount'] !== null) {
            $oldPpd = \bb\classes\TariffHistory::pricePerDay($h['old_rent_amount'], $h['old_step'], $h['old_kol_vo']);
            $newPpd = \bb\classes\TariffHistory::pricePerDay($h['new_rent_amount'], $h['new_step'], $h['new_kol_vo']);
            if ($oldPpd > 0 && $newPpd !== null) {
                $pct = round((($newPpd - $oldPpd) / $oldPpd) * 100, 1);
                $deltaText = ($pct > 0 ? '+' : '') . $pct . '% за день';
            }
        }

        $actor = $h['actor_name'];
        if ($actor === null && $h['actor_user_id'] !== null) {
            $actor = 'id ' . $h['actor_user_id'];
        }

        echo '<tr>
            <td>' . date('d.m.Y H:i', $h['changed_at']) . '</td>
            <td>' . htmlspecialchars((string) $actor) . '</td>
            <td>' . htmlspecialchars($h['change_type']) . '</td>
            <td>' . (int) $h['tarif_id'] . '</td>
            <td>' . htmlspecialchars($oldText) . '</td>
            <td>' . htmlspecialchars($newText) . '</td>
            <td>' . htmlspecialchars($deltaText) . '</td>
        </tr>';
    }

    echo '</table>';
}

echo '</div>';
```

- [ ] **Step 2: Проверить синтаксис**

Run: `docker compose exec -T app php -l bb/rent_tarifs.php`
Expected: `No syntax errors detected in bb/rent_tarifs.php`

- [ ] **Step 3: Ручная проверка**

Открыть `http://localhost/bb/rent_tarifs.php`, выбрать модель, которую правили в Task 4. Нажать «история изменений».
Expected: таблица с событиями, столбец «изменение» показывает процент по цене за день; событие `baseline` выводится с прочерком в «было».

- [ ] **Step 4: Коммит**

```bash
git add bb/rent_tarifs.php
git commit -m "feat(bb): блок истории изменений на странице тарифов модели"
```

---

### Task 7: `modelInventoryAtDate()` переезжает в `BaseController`

**Files:**
- Modify: `app/Http/Controllers/Mcp/BaseController.php`
- Modify: `app/Http/Controllers/Mcp/InventoryController.php:337-416`

**Interfaces:**
- Consumes: `cacheKey()`, `cacheRemember()`, `carnivalCatIds()`, константу `TTL_HEAVY` — всё уже в `BaseController`.
- Produces: `BaseController::modelInventoryAtDate(int $ts, array $razdelIds, bool $incCarn): array` — `protected`, возвращает `model_id => unit_count`.

- [ ] **Step 1: Зафиксировать текущее поведение эталонным тестом**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/InventoryTest.php`
Expected: зелёный прогон — это база для сравнения после переноса.

- [ ] **Step 2: Перенести метод**

Вырезать метод `modelInventoryAtDate()` целиком из `app/Http/Controllers/Mcp/InventoryController.php` (строки 331-416, вместе с докблоком) и вставить в `app/Http/Controllers/Mcp/BaseController.php` перед закрывающей скобкой класса, изменив только видимость и добавив пояснение:

```php
    /**
     * Per-model inventory at a given timestamp. Sums tovar_rent_items
     * (buy_date <= ts) + tovar_rent_items_arch (buy_date <= ts AND arch_time >= ts).
     *
     * Lives here rather than in InventoryController because /operations/deals-by-model
     * needs the same denominator: a raw deal count conflates a price effect with
     * the effect of simply buying more units.
     *
     * @return array<int,int>  model_id => unit_count
     */
    protected function modelInventoryAtDate(int $ts, array $razdelIds, bool $incCarn): array
    {
        // ... тело метода переносится без изменений ...
    }
```

Тело метода не менять ни на строку — это чистый перенос.

- [ ] **Step 3: Убедиться, что поведение не изменилось**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/InventoryTest.php`
Expected: тот же зелёный результат, что в шаге 1.

- [ ] **Step 4: Коммит**

```bash
git add app/Http/Controllers/Mcp/BaseController.php app/Http/Controllers/Mcp/InventoryController.php
git commit -m "refactor(mcp): modelInventoryAtDate переезжает в BaseController"
```

---

### Task 8: `GET /pricing/history`

**Files:**
- Create: `app/Http/Controllers/Mcp/PricingController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Mcp/PricingTest.php`

**Interfaces:**
- Consumes: `BaseController::envelope()`, `cacheKey()`, `cacheRemember()`, `categoryToRazdelIds()`, `TTL_DEFAULT`.
- Produces: `PricingController::history(Request $request): JsonResponse`; маршрут `pricing.history`.

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Feature/Mcp/PricingTest.php`:

```php
<?php

namespace Tests\Feature\Mcp;

class PricingTest extends McpTestCase
{
    // ─── /pricing/history ─────────────────────────────────────────────────

    public function test_history_requires_token(): void
    {
        $this->assertRequiresToken('pricing/history');
    }

    public function test_history_envelope_and_columns(): void
    {
        $r = $this->mcp('pricing/history', ['limit' => 5]);
        $this->assertEnvelope($r);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows, 'после baseline-миграции журнал не может быть пустым');
        $r->assertJsonStructure(['data' => [[
            'event_id', 'changed_at', 'change_type', 'source',
            'model_id', 'model_name', 'tarif_id',
            'actor' => ['user_id', 'name'],
            'before', 'after', 'delta_amount_byn', 'delta_pct',
        ]]]);
    }

    public function test_history_respects_limit(): void
    {
        $rows = $this->mcp('pricing/history', ['limit' => 3])->json('data');
        $this->assertLessThanOrEqual(3, count($rows));
    }

    public function test_history_rejects_oversized_limit(): void
    {
        $this->mcp('pricing/history', ['limit' => 501])->assertStatus(422);
    }

    public function test_history_rejects_unknown_change_type(): void
    {
        $this->mcp('pricing/history', ['change_type' => 'renamed'])->assertStatus(422);
    }

    public function test_history_filters_by_change_type(): void
    {
        $rows = $this->mcp('pricing/history', ['change_type' => 'baseline', 'limit' => 20])->json('data');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('baseline', $row['change_type']);
        }
    }

    public function test_history_filters_by_model_id(): void
    {
        $anyModelId = $this->mcp('pricing/history', ['limit' => 1])->json('data.0.model_id');
        $rows = $this->mcp('pricing/history', ['model_id' => $anyModelId, 'limit' => 50])->json('data');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame($anyModelId, $row['model_id']);
        }
    }

    public function test_history_is_sorted_newest_first(): void
    {
        $rows = $this->mcp('pricing/history', ['limit' => 30])->json('data');
        $timestamps = array_map(static fn ($r) => strtotime($r['changed_at']), $rows);
        $this->assertSortedDesc($timestamps, 'changed_at must be sorted DESC');
    }

    public function test_baseline_rows_have_no_before_side(): void
    {
        $rows = $this->mcp('pricing/history', ['change_type' => 'baseline', 'limit' => 5])->json('data');
        foreach ($rows as $row) {
            $this->assertNull($row['before'], 'у baseline не может быть состояния "до"');
            $this->assertNotNull($row['after']);
        }
    }

    public function test_price_per_day_is_amount_divided_by_period(): void
    {
        $rows = $this->mcp('pricing/history', ['change_type' => 'baseline', 'limit' => 50])->json('data');

        $checked = 0;
        foreach ($rows as $row) {
            $after = $row['after'];
            $stepDays = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365][$after['step']] ?? 0;
            $days = $stepDays * $after['kol_vo'];
            if ($days <= 0 || $after['price_per_day'] === null) {
                continue;
            }
            $this->assertEqualsWithDelta(
                round((float) $after['rent_amount'] / $days, 2),
                (float) $after['price_per_day'],
                0.011
            );
            $checked++;
        }
        $this->assertGreaterThan(0, $checked, 'нужна хотя бы одна строка с ненулевым периодом');
    }
}
```

- [ ] **Step 2: Запустить тест и убедиться, что он падает**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/PricingTest.php`
Expected: FAIL — 404, маршрута `pricing/history` не существует.

- [ ] **Step 3: Создать контроллер**

Создать `app/Http/Controllers/Mcp/PricingController.php`:

```php
<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * История изменений тарифов и восстановление прайса на произвольную дату.
 *
 * Источник — `rent_tarif_history`: одно событие хранит полный снимок строки
 * тарифа до и после, поэтому состояние на дату D восстанавливается выбором
 * последнего события с `changed_at <= D` (см. docs/superpowers/specs/
 * 2026-07-31-tariff-history-design.md).
 */
class PricingController extends BaseController
{
    /** Дней в одном шаге тарифа — фиксированная конвертация, см. docs/tariffs.md. */
    private const STEP_DAYS = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365];

    /**
     * GET /pricing/history?model_id&category&from&to&change_type&actor_user_id&limit&offset
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_id'      => 'nullable|integer|min:1',
            'category'      => 'nullable|string',
            'from'          => 'nullable|date',
            'to'            => 'nullable|date|after_or_equal:from',
            'change_type'   => 'nullable|string|in:baseline,create,update,delete',
            'actor_user_id' => 'nullable|integer|min:1',
            'limit'         => 'nullable|integer|min:1|max:500',
            'offset'        => 'nullable|integer|min:0',
        ]);

        $limit  = (int) ($validated['limit'] ?? 100);
        $offset = (int) ($validated['offset'] ?? 0);

        $key = $this->cacheKey('pricing.history', [
            'model'  => $validated['model_id'] ?? 'all',
            'cat'    => $validated['category'] ?? 'all',
            'from'   => $validated['from'] ?? '',
            'to'     => $validated['to'] ?? '',
            'type'   => $validated['change_type'] ?? 'all',
            'actor'  => $validated['actor_user_id'] ?? 'all',
            'limit'  => $limit,
            'offset' => $offset,
        ]);

        $rows = $this->cacheRemember($key, self::TTL_DEFAULT, function () use ($validated, $limit, $offset) {
            $where  = ['1 = 1'];
            $params = [];

            if (!empty($validated['model_id'])) {
                $where[]  = 'h.model_id = ?';
                $params[] = (int) $validated['model_id'];
            }
            if (!empty($validated['from'])) {
                $where[]  = 'h.changed_at >= ?';
                $params[] = strtotime($validated['from'] . ' 00:00:00');
            }
            if (!empty($validated['to'])) {
                $where[]  = 'h.changed_at <= ?';
                $params[] = strtotime($validated['to'] . ' 23:59:59');
            }
            if (!empty($validated['change_type'])) {
                $where[]  = 'h.change_type = ?';
                $params[] = $validated['change_type'];
            }
            if (!empty($validated['actor_user_id'])) {
                $where[]  = 'h.actor_user_id = ?';
                $params[] = (int) $validated['actor_user_id'];
            }

            $categories = $this->parseCategories($validated['category'] ?? null);
            if ($categories !== null) {
                $razdelIds = $this->categoryToRazdelIds($categories);
                if (empty($razdelIds)) {
                    return [];
                }
                $where[]  = 'h.model_id IN (' . $this->modelsInRazdelSubquery(count($razdelIds)) . ')';
                $params   = array_merge($params, $razdelIds);
            }

            $whereSql = implode(' AND ', $where);

            $sql = "
                SELECT h.*, rmw.l2_name AS model_name
                FROM rent_tarif_history h
                LEFT JOIN rent_model_web rmw ON rmw.model_id = h.model_id AND rmw.lang = 'ru'
                WHERE {$whereSql}
                ORDER BY h.changed_at DESC, h.id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";

            return array_map([$this, 'formatEvent'], DB::select($sql, $params));
        });

        return $this->envelope([
            'model_id'      => $validated['model_id'] ?? null,
            'category'      => $validated['category'] ?? 'all',
            'from'          => $validated['from'] ?? null,
            'to'            => $validated['to'] ?? null,
            'change_type'   => $validated['change_type'] ?? 'all',
            'actor_user_id' => $validated['actor_user_id'] ?? null,
            'limit'         => $limit,
            'offset'        => $offset,
        ], $rows);
    }

    /**
     * `null` означает «фильтра нет»; иначе — список слагов категорий.
     *
     * @return string[]|null
     */
    protected function parseCategories(?string $category): ?array
    {
        if ($category === null || $category === '' || $category === 'all') {
            return null;
        }
        return array_map('trim', explode(',', $category));
    }

    /**
     * Подзапрос «модели указанных разделов». DISTINCT обязателен: цепочка
     * subrazdel_category × razdel_subrazdel — many-to-many, без него модель
     * вернётся столько раз, сколько у неё пар подраздел×раздел.
     */
    protected function modelsInRazdelSubquery(int $razdelCount): string
    {
        $placeholders = implode(',', array_fill(0, $razdelCount, '?'));
        return "
            SELECT DISTINCT tr.tovar_rent_id
            FROM tovar_rent tr
            JOIN subrazdel_category sc ON sc.tovar_rent_cat_id = tr.tovar_rent_cat_id
            JOIN razdel_subrazdel rs   ON rs.id_sub_razdel     = sc.id_sub_razdel
            WHERE rs.id_razdel IN ({$placeholders})
        ";
    }

    /**
     * Строка журнала → строка ответа API.
     *
     * @param object $h
     * @return array<string,mixed>
     */
    protected function formatEvent($h): array
    {
        $before = $this->formatSide($h, 'old');
        $after  = $this->formatSide($h, 'new');

        $deltaAmount = null;
        $deltaPct    = null;
        if ($before !== null && $after !== null) {
            $deltaAmount = number_format((float) $after['rent_amount'] - (float) $before['rent_amount'], 2, '.', '');
            if ($before['price_per_day'] !== null && $after['price_per_day'] !== null && (float) $before['price_per_day'] > 0) {
                $deltaPct = round(((float) $after['price_per_day'] - (float) $before['price_per_day'])
                    / (float) $before['price_per_day'] * 100, 1);
            }
        }

        return [
            'event_id'         => (int) $h->id,
            'changed_at'       => gmdate('Y-m-d\TH:i:s\Z', (int) $h->changed_at),
            'change_type'      => $h->change_type,
            'source'           => $h->source,
            'model_id'         => (int) $h->model_id,
            'model_name'       => $h->model_name,
            'tarif_id'         => (int) $h->tarif_id,
            'actor'            => [
                'user_id' => $h->actor_user_id !== null ? (int) $h->actor_user_id : null,
                'name'    => $h->actor_name,
            ],
            'before'           => $before,
            'after'            => $after,
            'delta_amount_byn' => $deltaAmount,
            'delta_pct'        => $deltaPct,
            'note'             => $h->note,
        ];
    }

    /**
     * Одна сторона события. `null`, если снимка нет (create/baseline не имеют
     * «до», delete не имеет «после»).
     *
     * @param object $h
     * @param string $prefix 'old' | 'new'
     * @return array<string,mixed>|null
     */
    protected function formatSide($h, string $prefix): ?array
    {
        $amountKey = $prefix . '_rent_amount';
        if ($h->$amountKey === null) {
            return null;
        }

        $stepKey      = $prefix . '_step';
        $kolVoKey     = $prefix . '_kol_vo';
        $kolVoMinKey  = $prefix . '_kol_vo_min';
        $perStepKey   = $prefix . '_rent_per_step';
        $startDateKey = $prefix . '_start_date';

        return [
            'step'          => $h->$stepKey,
            'kol_vo'        => (int) $h->$kolVoKey,
            'kol_vo_min'    => (int) $h->$kolVoMinKey,
            'rent_amount'   => number_format((float) $h->$amountKey, 2, '.', ''),
            'rent_per_step' => number_format((float) $h->$perStepKey, 2, '.', ''),
            'price_per_day' => $this->pricePerDay($h->$amountKey, $h->$stepKey, (int) $h->$kolVoKey),
            'start_date'    => $h->$startDateKey ? gmdate('Y-m-d', (int) $h->$startDateKey) : null,
        ];
    }

    /**
     * Цена за день — единственная метрика, позволяющая сравнить тарифы
     * с разным шагом между собой.
     *
     * @return string|null
     */
    protected function pricePerDay($rentAmount, ?string $step, int $kolVo): ?string
    {
        $days = (self::STEP_DAYS[$step] ?? 0) * $kolVo;
        if ($days <= 0) {
            return null;
        }
        return number_format((float) $rentAmount / $days, 2, '.', '');
    }
}
```

- [ ] **Step 4: Зарегистрировать маршрут**

В `routes/api.php` добавить импорт рядом с остальными:

```php
use App\Http\Controllers\Mcp\PricingController;
```

и блок маршрутов сразу после блока `// Inventory (A.6 + existing)`:

```php
        // Pricing history (2026-07-31)
        Route::get('pricing/history',  [PricingController::class, 'history'])->name('pricing.history');
```

- [ ] **Step 5: Сбросить кеш маршрутов и прогнать тест**

Run:
```bash
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/PricingTest.php
```
Expected: `OK (10 tests)`.

- [ ] **Step 6: Коммит**

```bash
git add app/Http/Controllers/Mcp/PricingController.php routes/api.php tests/Feature/Mcp/PricingTest.php
git commit -m "feat(mcp): GET /pricing/history — лента изменений тарифов"
```

---

### Task 9: `GET /pricing/snapshot`

**Files:**
- Modify: `app/Http/Controllers/Mcp/PricingController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Mcp/PricingTest.php` (дописать)

**Interfaces:**
- Consumes: `formatSide()`, `pricePerDay()`, `parseCategories()`, `modelsInRazdelSubquery()` из Task 8.
- Produces: `PricingController::snapshot(Request $request): JsonResponse`; маршрут `pricing.snapshot`.

- [ ] **Step 1: Дописать падающие тесты**

Добавить в `tests/Feature/Mcp/PricingTest.php`:

```php
    // ─── /pricing/snapshot ────────────────────────────────────────────────

    public function test_snapshot_requires_as_of(): void
    {
        $this->mcp('pricing/snapshot')->assertStatus(422);
    }

    public function test_snapshot_rejects_malformed_as_of(): void
    {
        $this->mcp('pricing/snapshot', ['as_of' => 'вчера'])->assertStatus(422);
    }

    public function test_snapshot_envelope_and_columns(): void
    {
        $r = $this->mcp('pricing/snapshot', ['as_of' => date('Y-m-d')]);
        $this->assertEnvelope($r);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows);
        $r->assertJsonStructure(['data' => [[
            'model_id', 'model_name', 'min_price_per_day', 'extrapolated',
            'tariffs' => [['tarif_id', 'step', 'kol_vo', 'rent_amount', 'price_per_day']],
        ]]]);
    }

    public function test_snapshot_today_matches_live_tariff_table(): void
    {
        $rows = $this->mcp('pricing/snapshot', ['as_of' => date('Y-m-d')])->json('data');

        $snapshotTariffs = 0;
        foreach ($rows as $row) {
            $snapshotTariffs += count($row['tariffs']);
        }

        $live = (int) \Illuminate\Support\Facades\DB::selectOne(
            'SELECT COUNT(*) AS n FROM rent_tarif_act'
        )->n;

        $this->assertSame($live, $snapshotTariffs,
            'снимок на сегодня должен совпадать с живой таблицей тарифов');
    }

    public function test_snapshot_far_past_is_empty_or_extrapolated(): void
    {
        // 2010 год — раньше первой записи в rent_tarif_act (2013).
        $rows = $this->mcp('pricing/snapshot', ['as_of' => '2010-01-01'])->json('data');
        foreach ($rows as $row) {
            $this->assertTrue($row['extrapolated'], 'до начала данных строки могут быть только экстраполированными');
        }
    }

    public function test_snapshot_warns_about_extrapolated_rows(): void
    {
        $r = $this->mcp('pricing/snapshot', ['as_of' => '2015-01-01']);
        $warnings = $r->json('meta.warnings');
        $extrapolated = array_filter($r->json('data'), static fn ($row) => $row['extrapolated']);

        if (!empty($extrapolated)) {
            $this->assertNotEmpty($warnings, 'наличие экстраполяции обязано попасть в meta.warnings');
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_snapshot_filters_by_model_id(): void
    {
        $anyModelId = $this->mcp('pricing/snapshot', ['as_of' => date('Y-m-d')])->json('data.0.model_id');
        $rows = $this->mcp('pricing/snapshot', ['as_of' => date('Y-m-d'), 'model_id' => $anyModelId])->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($anyModelId, $rows[0]['model_id']);
    }
```

- [ ] **Step 2: Запустить тесты и убедиться, что они падают**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/PricingTest.php`
Expected: FAIL — 404 на `pricing/snapshot`.

- [ ] **Step 3: Добавить метод `snapshot()` в `PricingController`**

```php
    /**
     * GET /pricing/snapshot?as_of=YYYY-MM-DD&model_id&category
     *
     * Прайс-лист на произвольную дату. Для каждой строки тарифа берётся
     * последнее событие с `changed_at <= as_of`.
     *
     * Строки, у которых событий до этой даты нет, но которые к ней уже
     * действовали (`new_start_date <= as_of`), попадают в ответ с
     * `extrapolated: true`: baseline фиксирует состояние на момент внедрения
     * журнала, а что было до последней правки — неизвестно. Доля таких строк
     * тает по мере накопления реальных событий.
     */
    public function snapshot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of'    => 'required|date',
            'model_id' => 'nullable|integer|min:1',
            'category' => 'nullable|string',
        ]);

        $asOf = strtotime($validated['as_of'] . ' 23:59:59');

        $key = $this->cacheKey('pricing.snapshot', [
            'as_of' => $asOf,
            'model' => $validated['model_id'] ?? 'all',
            'cat'   => $validated['category'] ?? 'all',
        ]);

        $payload = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($validated, $asOf) {
            $where  = [];
            $params = [];

            if (!empty($validated['model_id'])) {
                $where[]  = 'h.model_id = ?';
                $params[] = (int) $validated['model_id'];
            }

            $categories = $this->parseCategories($validated['category'] ?? null);
            if ($categories !== null) {
                $razdelIds = $this->categoryToRazdelIds($categories);
                if (empty($razdelIds)) {
                    return ['rows' => [], 'extrapolated' => 0];
                }
                $where[]  = 'h.model_id IN (' . $this->modelsInRazdelSubquery(count($razdelIds)) . ')';
                $params   = array_merge($params, $razdelIds);
            }

            $extraWhere = $where ? ' AND ' . implode(' AND ', $where) : '';

            // Ветка 1 — тарифы, о которых на дату уже есть событие.
            // Порядок (changed_at, id): один только MAX(id) сломался бы там, где
            // импортированное legacy-удаление получило id выше baseline-события.
            $knownSql = "
                SELECT h.*, rmw.l2_name AS model_name, 0 AS extrapolated
                FROM rent_tarif_history h
                LEFT JOIN rent_model_web rmw ON rmw.model_id = h.model_id AND rmw.lang = 'ru'
                WHERE h.id = (
                    SELECT h2.id FROM rent_tarif_history h2
                    WHERE h2.tarif_id = h.tarif_id AND h2.changed_at <= ?
                    ORDER BY h2.changed_at DESC, h2.id DESC
                    LIMIT 1
                )
                AND h.change_type <> 'delete'
                {$extraWhere}
            ";
            $known = DB::select($knownSql, array_merge([$asOf], $params));

            // Ветка 2 — тариф действовал на дату, но событий до неё нет.
            $extrapolatedSql = "
                SELECT h.*, rmw.l2_name AS model_name, 1 AS extrapolated
                FROM rent_tarif_history h
                LEFT JOIN rent_model_web rmw ON rmw.model_id = h.model_id AND rmw.lang = 'ru'
                WHERE h.change_type = 'baseline'
                  AND h.changed_at > ?
                  AND h.new_start_date <= ?
                  AND NOT EXISTS (
                      SELECT 1 FROM rent_tarif_history h3
                      WHERE h3.tarif_id = h.tarif_id AND h3.changed_at <= ?
                  )
                {$extraWhere}
            ";
            $extrapolated = DB::select($extrapolatedSql, array_merge([$asOf, $asOf, $asOf], $params));

            $byModel          = [];
            $extrapolatedRows = 0;

            foreach (array_merge($known, $extrapolated) as $h) {
                $side = $this->formatSide($h, 'new');
                if ($side === null) {
                    continue;
                }

                $modelId = (int) $h->model_id;
                if (!isset($byModel[$modelId])) {
                    $byModel[$modelId] = [
                        'model_id'          => $modelId,
                        'model_name'        => $h->model_name,
                        'min_price_per_day' => null,
                        'extrapolated'      => false,
                        'tariffs'           => [],
                    ];
                }

                $byModel[$modelId]['tariffs'][] = array_merge(
                    ['tarif_id' => (int) $h->tarif_id],
                    $side
                );

                if ((int) $h->extrapolated === 1) {
                    $byModel[$modelId]['extrapolated'] = true;
                    $extrapolatedRows++;
                }

                if ($side['price_per_day'] !== null) {
                    $current = $byModel[$modelId]['min_price_per_day'];
                    if ($current === null || (float) $side['price_per_day'] < (float) $current) {
                        $byModel[$modelId]['min_price_per_day'] = $side['price_per_day'];
                    }
                }
            }

            return ['rows' => array_values($byModel), 'extrapolated' => $extrapolatedRows];
        });

        $meta = [];
        if ($payload['extrapolated'] > 0) {
            $meta['warnings'] = [
                $payload['extrapolated'] . ' tariff rows are extrapolated: the change log starts at the '
                . 'baseline migration (2026-07-31), so values before a row\'s last recorded change are '
                . 'the baseline snapshot, not observed history.',
            ];
        }

        return $this->envelope([
            'as_of'    => $validated['as_of'],
            'model_id' => $validated['model_id'] ?? null,
            'category' => $validated['category'] ?? 'all',
        ], $payload['rows'], $meta);
    }
```

- [ ] **Step 4: Зарегистрировать маршрут**

В `routes/api.php`, рядом с `pricing/history`:

```php
        Route::get('pricing/snapshot', [PricingController::class, 'snapshot'])->name('pricing.snapshot');
```

- [ ] **Step 5: Прогнать тесты**

Run:
```bash
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/PricingTest.php
```
Expected: `OK (17 tests)`.

- [ ] **Step 6: Коммит**

```bash
git add app/Http/Controllers/Mcp/PricingController.php routes/api.php tests/Feature/Mcp/PricingTest.php
git commit -m "feat(mcp): GET /pricing/snapshot — прайс-лист на произвольную дату"
```

---

### Task 10: `GET /operations/deals-by-model`

**Files:**
- Modify: `app/Http/Controllers/Mcp/OperationsController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Mcp/PricingTest.php` (дописать)

**Interfaces:**
- Consumes: `RangeRequest` (`fromTimestamp()`, `toTimestamp()`, `categories()`, `includeCarnival()`, `granularityFormatFor()`, `queryEcho()`), `BaseController::unifiedDealsSubquery()`, `unifiedItemsSubquery()`, `itemsInRazdelSubquery()`, `carnivalCatIds()`, `categoryToRazdelIds()`, `modelInventoryAtDate()` (Task 7).
- Produces: `OperationsController::dealsByModel(RangeRequest $request): JsonResponse`; маршрут `operations.deals-by-model`. Строка: `{model_id, model_name, period, deals_started, units_at_period_end, deals_per_unit}`.

- [ ] **Step 1: Дописать падающие тесты**

Добавить в `tests/Feature/Mcp/PricingTest.php`:

```php
    // ─── /operations/deals-by-model ───────────────────────────────────────

    public function test_deals_by_model_envelope_and_columns(): void
    {
        $r = $this->mcp('operations/deals-by-model', [
            'from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'month',
        ]);
        $this->assertEnvelope($r);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows);
        $r->assertJsonStructure(['data' => [[
            'model_id', 'model_name', 'period',
            'deals_started', 'units_at_period_end', 'deals_per_unit',
        ]]]);
    }

    public function test_deals_by_model_periods_are_inside_requested_range(): void
    {
        $rows = $this->mcp('operations/deals-by-model', [
            'from' => '2024-03-01', 'to' => '2024-05-31', 'granularity' => 'month',
        ])->json('data');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertContains($row['period'], ['2024-03', '2024-04', '2024-05']);
        }
    }

    public function test_deals_by_model_counts_deals_created_in_period(): void
    {
        $from = strtotime('2024-06-01 00:00:00');
        $to   = strtotime('2024-06-30 23:59:59');

        $rows = $this->mcp('operations/deals-by-model', [
            'from' => '2024-06-01', 'to' => '2024-06-30', 'granularity' => 'month',
        ])->json('data');

        $apiTotal = array_sum(array_map(static fn ($r) => $r['deals_started'], $rows));

        // Эталон: сделки, ЗАВЕДЁННЫЕ в периоде (cr_time), у которых юнит
        // разрешается в модель. Это отличается от /inventory/utilization,
        // который считает сделки, ПЕРЕСЕКАЮЩИЕ период.
        $expected = (int) \Illuminate\Support\Facades\DB::selectOne("
            SELECT COUNT(DISTINCT d.deal_id) AS n FROM (
                SELECT deal_id, item_inv_n, cr_time FROM rent_deals_act
                UNION ALL
                SELECT deal_id, item_inv_n, cr_time FROM rent_deals_arch
            ) d
            JOIN (
                SELECT item_inv_n, model_id FROM tovar_rent_items
                UNION ALL
                SELECT item_inv_n, model_id FROM tovar_rent_items_arch
            ) i ON i.item_inv_n = d.item_inv_n
            WHERE d.cr_time BETWEEN ? AND ? AND i.model_id IS NOT NULL
        ", [$from, $to])->n;

        $this->assertSame($expected, $apiTotal);
    }

    public function test_deals_by_model_filters_by_model_id(): void
    {
        $anyModelId = $this->mcp('operations/deals-by-model', [
            'from' => '2024-01-01', 'to' => '2024-12-31',
        ])->json('data.0.model_id');

        $rows = $this->mcp('operations/deals-by-model', [
            'from' => '2024-01-01', 'to' => '2024-12-31', 'model_id' => $anyModelId,
        ])->json('data');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame($anyModelId, $row['model_id']);
        }
    }

    public function test_deals_by_model_skips_inventory_for_too_many_periods(): void
    {
        // day-гранулярность за год — 366 периодов, знаменатель не считается.
        $r = $this->mcp('operations/deals-by-model', [
            'from' => '2024-01-01', 'to' => '2024-12-31', 'granularity' => 'day',
        ]);
        $r->assertStatus(200);

        $rows = $r->json('data');
        $this->assertNotEmpty($rows);
        $this->assertNull($rows[0]['units_at_period_end']);
        $this->assertNull($rows[0]['deals_per_unit']);
        $this->assertNotEmpty($r->json('meta.warnings'));
    }
```

- [ ] **Step 2: Запустить тесты и убедиться, что они падают**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/PricingTest.php`
Expected: FAIL — 404 на `operations/deals-by-model`.

- [ ] **Step 3: Добавить метод в `OperationsController`**

Вставить в `app/Http/Controllers/Mcp/OperationsController.php` перед закрывающей скобкой класса:

```php
    /**
     * Порог, после которого знаменатель (складские остатки) не считается:
     * каждый период требует отдельного запроса к историческим остаткам.
     */
    private const MAX_PERIODS_WITH_INVENTORY = 60;

    /**
     * GET /operations/deals-by-model?from&to&granularity&model_id&category&include_carnival
     *
     * Новые сделки по модели и периоду. В отличие от /inventory/utilization,
     * который считает сделки, ПЕРЕСЕКАЮЩИЕ период, здесь считаются сделки,
     * ЗАВЕДЁННЫЕ в нём (`cr_time`) — то есть моменты решения клиента. Именно
     * этот ряд сопоставляется с /pricing/history при анализе смены цены.
     *
     * `units_at_period_end` — исторические остатки модели на конец периода.
     * Без этого знаменателя рост числа сделок от закупки новых юнитов
     * неотличим от эффекта цены.
     */
    public function dealsByModel(RangeRequest $request): JsonResponse
    {
        $request->validate(['model_id' => 'nullable|integer|min:1']);

        $from       = $request->fromTimestamp();
        $to         = $request->toTimestamp();
        $categories = $request->categories();
        $incCarn    = $request->includeCarnival();
        $modelId    = $request->filled('model_id') ? $request->integer('model_id') : null;

        $key = $this->cacheKey('operations.deals_by_model', [
            'from'  => $from,
            'to'    => $to,
            'gran'  => $request->input('granularity'),
            'cat'   => implode(',', $categories),
            'model' => $modelId ?? 'all',
            'inc'   => $incCarn ? 1 : 0,
        ]);

        $payload = $this->cacheRemember($key, self::TTL_HEAVY, function () use ($request, $from, $to, $categories, $incCarn, $modelId) {
            $razdelIds = $this->categoryToRazdelIds($categories);
            if (!in_array('all', $categories, true) && empty($razdelIds)) {
                return ['rows' => [], 'inventory_skipped' => false];
            }

            $daSub = $this->unifiedDealsSubquery();
            $itSub = $this->unifiedItemsSubquery();

            $periodExpr = $request->granularityFormatFor('da.cr_time');

            $joins = "
                JOIN {$itSub} ti ON ti.item_inv_n = da.item_inv_n
                LEFT JOIN rent_model_web rmw ON rmw.model_id = ti.model_id AND rmw.lang = 'ru'
            ";
            $joinParams  = [];
            $where       = ['da.cr_time BETWEEN ? AND ?', 'ti.model_id IS NOT NULL'];
            $whereParams = [$from, $to];

            if (!empty($razdelIds)) {
                $joins      .= ' JOIN ' . $this->itemsInRazdelSubquery($razdelIds) . ' irz ON irz.item_inv_n = da.item_inv_n ';
                $joinParams  = array_merge($joinParams, $razdelIds);
            }
            if ($modelId !== null) {
                $where[]       = 'ti.model_id = ?';
                $whereParams[] = $modelId;
            }
            if (!$incCarn) {
                $carnIds = $this->carnivalCatIds();
                if ($carnIds) {
                    $carnPh        = implode(',', array_fill(0, count($carnIds), '?'));
                    $where[]       = "(ti.cat_id IS NULL OR ti.cat_id NOT IN ({$carnPh}))";
                    $whereParams   = array_merge($whereParams, $carnIds);
                }
            }

            $whereSql = implode(' AND ', $where);

            $sql = "
                SELECT ti.model_id,
                       rmw.l2_name AS model_name,
                       {$periodExpr} AS period,
                       COUNT(DISTINCT da.deal_id) AS deals_started
                FROM {$daSub} da
                {$joins}
                WHERE {$whereSql}
                GROUP BY ti.model_id, rmw.l2_name, period
                ORDER BY period ASC, deals_started DESC
            ";

            $rows = DB::select($sql, array_merge($joinParams, $whereParams));

            $periods = array_values(array_unique(array_map(static fn ($r) => $r->period, $rows)));
            $withInventory = count($periods) <= self::MAX_PERIODS_WITH_INVENTORY;

            $inventoryByPeriod = [];
            if ($withInventory) {
                foreach ($periods as $period) {
                    $inventoryByPeriod[$period] = $this->modelInventoryAtDate(
                        $this->periodEndTimestamp($period, $to),
                        $razdelIds,
                        $incCarn
                    );
                }
            }

            $out = [];
            foreach ($rows as $r) {
                $mid   = (int) $r->model_id;
                $deals = (int) $r->deals_started;
                $units = $withInventory ? ($inventoryByPeriod[$r->period][$mid] ?? 0) : null;

                $out[] = [
                    'model_id'            => $mid,
                    'model_name'          => $r->model_name,
                    'period'              => $r->period,
                    'deals_started'       => $deals,
                    'units_at_period_end' => $units,
                    'deals_per_unit'      => ($units !== null && $units > 0) ? round($deals / $units, 2) : null,
                ];
            }

            return ['rows' => $out, 'inventory_skipped' => !$withInventory];
        });

        $meta = [];
        if ($payload['inventory_skipped']) {
            $meta['warnings'] = [
                'units_at_period_end and deals_per_unit were skipped: more than '
                . self::MAX_PERIODS_WITH_INVENTORY . ' periods in range. Use a coarser granularity '
                . 'or a shorter range to get the inventory denominator.',
            ];
        }

        return $this->envelope($request->queryEcho() + ['model_id' => $modelId], $payload['rows'], $meta);
    }

    /**
     * Последняя секунда периода, выданного granularityFormatFor().
     * Формат зависит от гранулярности: '2024-06', '2024-06-15', '2024-W24',
     * '2024-Q2', '2024'. Результат ограничивается концом запрошенного диапазона.
     */
    private function periodEndTimestamp(string $period, int $rangeEnd): int
    {
        if (preg_match('/^(\d{4})-W(\d{2})$/', $period, $m)) {
            $ts = strtotime(sprintf('%sW%s', $m[1], $m[2]) . ' +6 days 23:59:59');
        } elseif (preg_match('/^(\d{4})-Q(\d)$/', $period, $m)) {
            $endMonth = (int) $m[2] * 3;
            $ts = strtotime(sprintf('%s-%02d-01', $m[1], $endMonth) . ' last day of this month 23:59:59');
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            $ts = strtotime($period . ' 23:59:59');
        } elseif (preg_match('/^\d{4}-\d{2}$/', $period)) {
            $ts = strtotime($period . '-01 last day of this month 23:59:59');
        } else {
            $ts = strtotime($period . '-12-31 23:59:59');
        }

        return min((int) $ts, $rangeEnd);
    }
```

- [ ] **Step 4: Зарегистрировать маршрут**

В `routes/api.php`, в блоке `// Operations (A.5)`:

```php
        Route::get('operations/deals-by-model', [OperationsController::class, 'dealsByModel'])->name('operations.deals-by-model');
```

- [ ] **Step 5: Прогнать тесты**

Run:
```bash
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/PricingTest.php
```
Expected: `OK (22 tests)`.

- [ ] **Step 6: Убедиться, что смежные эндпоинты не сломались**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/OperationsTest.php tests/Feature/Mcp/InventoryTest.php tests/Feature/Mcp/LegacyParityTest.php`
Expected: зелёный прогон.

- [ ] **Step 7: Коммит**

```bash
git add app/Http/Controllers/Mcp/OperationsController.php routes/api.php tests/Feature/Mcp/PricingTest.php
git commit -m "feat(mcp): GET /operations/deals-by-model — новые сделки по модели и периоду"
```

---

### Task 11: OpenAPI-спецификация трёх эндпоинтов

**Files:**
- Modify: `resources/openapi/mcp-v1.json`
- Modify: `tests/Feature/Mcp/SpecRuntimeParityTest.php`

**Interfaces:**
- Consumes: фактические ключи ответов из Task 8-10.
- Produces: записи `paths` для `/pricing/history`, `/pricing/snapshot`, `/operations/deals-by-model`.

- [ ] **Step 1: Добавить три эндпоинта в матрицу parity-теста**

В `tests/Feature/Mcp/SpecRuntimeParityTest.php`, в метод `endpointMatrix()`, добавить в возвращаемый массив:

```php
            // Pricing history (2026-07-31)
            'pricing/history'              => ['pricing/history',              ['limit' => 5],                                                                'array_row', null],
            'pricing/snapshot'             => ['pricing/snapshot',             ['as_of' => '2026-01-01'],                                                     'array_row', null],
            'operations/deals-by-model'    => ['operations/deals-by-model',    $range + ['granularity' => 'month'],                                           'array_row', null],
```

- [ ] **Step 2: Запустить parity-тест и убедиться, что он падает**

Run: `docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/SpecRuntimeParityTest.php`
Expected: FAIL — рантайм отдаёт ключи, которых нет в спеке.

- [ ] **Step 3: Изучить формат существующей записи в спеке**

Run: `docker compose exec -T app php -r "\$s=json_decode(file_get_contents('resources/openapi/mcp-v1.json'),true); echo json_encode(\$s['paths']['/inventory/utilization'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);"`
Expected: печатается структура `get.parameters` / `get.responses.200.content.application/json.schema` — новые записи оформляются по этому же образцу.

- [ ] **Step 4: Дописать три записи в `paths`**

Добавить в `resources/openapi/mcp-v1.json` в объект `paths`, повторяя стиль соседних записей. Обязательные поля строк:

- `/pricing/history` → `event_id` (integer), `changed_at` (string, date-time), `change_type` (string, enum `baseline|create|update|delete`), `source` (string), `model_id` (integer), `model_name` (string, nullable), `tarif_id` (integer), `actor` (object: `user_id` integer nullable, `name` string nullable), `before` (object nullable: `step`, `kol_vo`, `kol_vo_min`, `rent_amount`, `rent_per_step`, `price_per_day`, `start_date`), `after` (тот же объект, nullable), `delta_amount_byn` (string nullable), `delta_pct` (number nullable), `note` (string nullable).
  Параметры: `model_id`, `category`, `from`, `to`, `change_type`, `actor_user_id`, `limit`, `offset`.
- `/pricing/snapshot` → `model_id` (integer), `model_name` (string nullable), `min_price_per_day` (string nullable), `extrapolated` (boolean), `tariffs` (array объектов: `tarif_id`, `step`, `kol_vo`, `kol_vo_min`, `rent_amount`, `rent_per_step`, `price_per_day`, `start_date`).
  Параметры: `as_of` (required), `model_id`, `category`.
- `/operations/deals-by-model` → `model_id` (integer), `model_name` (string nullable), `period` (string), `deals_started` (integer), `units_at_period_end` (integer nullable), `deals_per_unit` (number nullable).
  Параметры: `from`, `to`, `granularity`, `model_id`, `category`, `include_carnival`.

- [ ] **Step 5: Проверить валидность JSON и прогнать parity-тест**

Run:
```bash
docker compose exec -T app php -r "json_decode(file_get_contents('resources/openapi/mcp-v1.json'), true, 512, JSON_THROW_ON_ERROR); echo \"spec is valid JSON\n\";"
docker compose exec -T app ./vendor/bin/phpunit tests/Feature/Mcp/SpecRuntimeParityTest.php
```
Expected: `spec is valid JSON`; parity-тест зелёный.

- [ ] **Step 6: Коммит**

```bash
git add resources/openapi/mcp-v1.json tests/Feature/Mcp/SpecRuntimeParityTest.php
git commit -m "docs(mcp): OpenAPI-спека для /pricing/* и /operations/deals-by-model"
```

---

### Task 12: Документация и финальная проверка

**Files:**
- Modify: `docs/tariffs.md`
- Modify: `docs/db_notes.md`
- Modify: `CLAUDE.md`
- Modify: `AGENTS.md`
- Modify: `docs/superpowers/specs/2026-07-31-tariff-history-design.md`

**Interfaces:**
- Consumes: всё построенное в Task 1-11.
- Produces: ничего.

- [ ] **Step 1: Обновить `docs/tariffs.md`**

Заменить строку 18:

```markdown
Активные тарифы хранятся в таблице `rent_tarif_act`, при удалении/изменении переносятся в архив `rent_tarif_prev`.
```

на:

```markdown
Активные тарифы хранятся в таблице `rent_tarif_act`. Каждое создание, изменение и удаление
пишется в журнал `rent_tarif_history` — одно событие хранит полный снимок строки до и после,
поэтому прайс на произвольную дату восстанавливается запросом «последнее событие
с `changed_at ≤ D`».

⚠️ **Правки `rent_tarif_act` идут только через `bb\classes\Tariff`.** Захват истории сделан на
уровне кода, а не триггеров БД: сырой `INSERT`/`UPDATE`/`DELETE` мимо класса пройдёт мимо
журнала. Это проверяет `tests/Feature/TariffWriteGuardTest.php`.

Старая таблица `rent_tarif_prev` заполнялась только при удалении и содержит суммы с точностью
до десятых (`DECIMAL(11,1)` — копейки утрачены). Её данные импортированы в журнал как события
`delete` с `source='legacy_import'`; новые записи туда не пишутся.
```

Дописать в таблицу «Ссылки на код»:

```markdown
| [bb/classes/TariffHistory.php](../bb/classes/TariffHistory.php) | Журнал изменений: запись событий, чтение для админки, `pricePerDay()` |
| [app/Http/Controllers/Mcp/PricingController.php](../app/Http/Controllers/Mcp/PricingController.php) | `/pricing/history`, `/pricing/snapshot` |
```

- [ ] **Step 2: Обновить `docs/db_notes.md`**

В пункте 9 заменить `**`model_id` живёт в 13 таблицах` на `**`model_id` живёт в 14 таблицах` и добавить `rent_tarif_history` в перечисление таблиц после `rent_tarif_prev`.

Дописать в конец пункта 9:

```markdown
    `rent_tarif_history` при слиянии моделей переносить, а не удалять: это единственный
    источник исторических цен, и удаление ломает `/pricing/snapshot` для дат до слияния.
```

- [ ] **Step 3: Обновить `CLAUDE.md`**

В таблицу «MCP Analytics API Controllers» добавить строку:

```markdown
| `PricingController` | `/pricing/*` | 2 — history (лента изменений тарифов из `rent_tarif_history`), snapshot (прайс-лист на произвольную дату; строки без событий до этой даты помечаются `extrapolated`) |
```

В строке `OperationsController` заменить `4 — funnel, timeline, by-category, by-location` на `5 — funnel, timeline, by-category, by-location, deals-by-model`.

В группу таблиц «Rental» добавить `rent_tarif_history`, а в группу «MCP API» дописать:

```markdown
`rent_tarif_history` (журнал изменений тарифов: полный снимок строки до и после, `source` различает `bb_admin`/`model_archive`/`legacy_import`/`baseline`)
```

Дописать в раздел «Critical Architecture Rules» новый пункт:

```markdown
### 7. Тарифы правятся только через `bb\classes\Tariff`

История изменений тарифов захватывается кодом, а не триггерами БД. Сырой
`INSERT`/`UPDATE`/`DELETE` по `rent_tarif_act` мимо класса `Tariff` не попадёт в
`rent_tarif_history` и молча сломает `/pricing/snapshot`. Исключение — разовые миграции
каталога в `database/migrations/`. Проверяется `tests/Feature/TariffWriteGuardTest.php`.
```

- [ ] **Step 4: Обновить `AGENTS.md`**

Найти раздел с перечнем эндпоинтов MCP API и добавить туда `/pricing/history`, `/pricing/snapshot`, `/operations/deals-by-model` в том же формате, что и соседние записи. Обновить итоговое число эндпоинтов (было 58 + health + openapi.json → стало 61 + health + openapi.json).

Run для поиска мест: `docker compose exec -T app grep -n "58 endpoints\|60 endpoints\|inventory/turnover" AGENTS.md CLAUDE.md`

- [ ] **Step 5: Привести спеку в соответствие с реализацией**

В `docs/superpowers/specs/2026-07-31-tariff-history-design.md`, в секции 4, заменить пример ответа `/operations/deals-by-model`:

```json
{ "model_id": 1069, "model_name": "Коляска X", "period": "2026-07",
  "deals_started": 14, "avg_units": 9.0, "deals_per_unit": 1.56 }
```

на:

```json
{ "model_id": 1069, "model_name": "Коляска X", "period": "2026-07",
  "deals_started": 14, "units_at_period_end": 9, "deals_per_unit": 1.56 }
```

и заменить абзац про `deals_per_unit` на:

```markdown
`deals_per_unit` включён намеренно: голое число сделок смешивает эффект цены с эффектом
закупки новых юнитов. Знаменатель — остатки модели на конец периода, считает существующий
`modelInventoryAtDate()` (переезжает из `InventoryController` в `BaseController` как
`protected`). Каждый период требует отдельного запроса к историческим остаткам, поэтому при
более чем 60 периодах в диапазоне знаменатель не считается: `units_at_period_end` и
`deals_per_unit` приходят как `null`, а причина уходит в `meta.warnings`.
```

- [ ] **Step 6: Полный прогон тестов**

Run: `docker compose exec -T app php artisan test`
Expected: зелёный прогон целиком. Если падают тесты, не относящиеся к этой работе, — зафиксировать их имена и вывод в отчёте, не «чинить» вслепую.

- [ ] **Step 7: Проверить, что ветка мержится без конфликтов**

Run:
```bash
git fetch origin && git merge-tree --write-tree --messages HEAD origin/main
echo "exit=$?"
```
Expected: `exit=0`.

- [ ] **Step 8: Коммит**

```bash
git add docs/tariffs.md docs/db_notes.md CLAUDE.md AGENTS.md docs/superpowers/specs/2026-07-31-tariff-history-design.md
git commit -m "docs: журнал изменений тарифов в документации проекта"
```

---

## Проверка плана против спеки

| Требование спеки | Задача |
|------------------|--------|
| Таблица `rent_tarif_history`, `DECIMAL(11,2)` | Task 1 |
| Baseline + импорт `rent_tarif_prev`, идемпотентность | Task 1 |
| Разбор `change_who` на `actor_user_id` / `actor_name` | Task 1 (импорт), Task 2 (новые события) |
| Класс `TariffHistory` — единственный писатель | Task 2 |
| `Tariff` пишет create/update/delete; пустой UPDATE не логируется | Task 3 |
| `rent_tarifs.php` через класс; снятие `$$key = get_post($key)` | Task 4 |
| Прекращение записи в `rent_tarif_prev` | Task 3 (новый `delete()` не пишет туда), Task 4 (старая ветка удалена) |
| Логирование при архивации модели | Task 5 |
| Блок истории в админке | Task 6 |
| `modelInventoryAtDate()` → `BaseController` как `protected` | Task 7 |
| `GET /pricing/history` + `price_per_day` + `delta_pct` | Task 8 |
| `GET /pricing/snapshot` + правило `extrapolated` + warning | Task 9 |
| `GET /operations/deals-by-model` по `cr_time` | Task 10 |
| OpenAPI-спека | Task 11 |
| Guard-тест на сырые DML | Task 4 |
| Документация (`tariffs.md`, `db_notes.md`, `CLAUDE.md`, `AGENTS.md`) | Task 12 |
| `LegacyParityTest` продолжает проходить | Task 10 шаг 6, Task 12 шаг 6 |
