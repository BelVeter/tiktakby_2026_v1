# Справочник производителей — план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Дать бренду одно место в базе (таблица `producers`), закрыть приток опечаток-дублей живым поиском вместо свободного текста, и хранить логотип бренда одной строкой вместо копий на каждой странице модели.

**Architecture:** Новая таблица `producers` (справочник) рядом с существующей строкой `tovar_rent.producer` (52 читающих места не переписываются). Детектор дублей — расширение уже написанного `bb\classes\Similarity` вторым сигналом (Левенштейн) поверх существующего триграммного Jaccard. Два независимых виджета живого поиска на `LivePicker` (уже есть в ветке `feature/tovar-new-mod-category-picker`): один с созданием/редактированием на `tovar_new_mod.php`, второй — только сужающий каскад на `tovar_new.php`. Логотип переносится из `rent_model_web.logo` (рассылка копий) в `producers.logo` (одна строка), с фолбэком на старую колонку.

**Tech Stack:** Laravel 8 / PHP 7.4, MariaDB 10.6, легаси `bb/` без composer autoload (каждый класс сам подключает свои зависимости через `require_once __DIR__ . '/...'`), vanilla JS без библиотек.

## Global Constraints

- Работа только **локальная** (Docker: `docker compose exec -T app ...`, `docker compose exec -T db mysql ...`). Прод не трогаем — чек-лист прод-действий уже заведён в `docs/prod_pending.md`, обновлять его не входит в эту задачу.
- `bb/` не использует composer autoload на проде: каждый новый класс, который зависит от другого класса `bb/`, обязан сам подключать его через `require_once __DIR__ . '/Имя.php'` (см. `bb/classes/Tariff.php` → `TariffHistory.php`, закреплено тестом `tests/Feature/TariffWriteGuardTest.php::test_tariff_class_requires_tariff_history_itself`). Файлы под `app/` (Laravel) автозагружаются нормально — там `require_once` не нужен, достаточно `use`.
- `tovar_rent.producer` остаётся свободной строкой. Никакого `producer_id`/внешнего ключа — это осознанный компромисс из спеки.
- SQL-экранирование строковых значений — через `addslashes()`, как в `bb/classes/Category.php` и `bb/ajax_category_create.php` (это прямой прецедент, который расширяет эта работа).
- Права: создание бренда — уровни `[0, 5, 7]` (как создание категории); переименование/скрытие — уровни `[5, 7]` (как архивация категории, т.к. трогает три таблицы).
- Каждая задача заканчивается своим коммитом. PHP-файлы проверяются `docker compose exec -T app php -l <файл>` перед коммитом, JS — `node --check <файл>`.
- Тесты гоняются через `docker compose exec -T app php artisan test --filter=<Name>` (либо `./vendor/bin/phpunit --filter=<Name>`, работает так же).

---

### Task 1: `Similarity` — расстояние Левенштейна как второй сигнал дублей

Триграммный Jaccard (уже в `bb/classes/Similarity.php`) ловит опечатки в длинных названиях, но разваливается на коротких: у `аксессуар`/`акссесуар` он даёт 0.40 (порог 0.55 — не поймано), у `Батик`/`Ботик` — 0.20. Это ровно тот тип опечатки, ради которого делается справочник производителей.

Решение — **новый, отдельный** набор методов (`editSimilarity`, `combinedScore`, `findSimilarByEdit`), не трогающий существующие `score()`/`findSimilar()`/`findExact()`. Эти три метода уже используются категориями (`bb/ajax_category_suggest.php`, `bb/ajax_category_create.php`) с порогом 0.55, подобранным и провалидированным на реальных данных категорий — менять их поведение ради производителей нельзя, это чужой, уже отревьюженный функционал.

**Files:**
- Modify: `bb/classes/Similarity.php`
- Test: `tests/Unit/SimilarityLevenshteinTest.php` (создать)

**Interfaces:**
- Produces: `Similarity::editSimilarity($a, $b): float` (0.0–1.0), `Similarity::combinedScore($a, $b): float` (`max(score(), editSimilarity())`), `Similarity::findSimilarByEdit($needle, array $haystack, $min = self::DEFAULT_THRESHOLD, $limit = 5): array` (та же форма возврата, что и `findSimilar()`: `[['key'=>, 'label'=>, 'score'=>], ...]`).

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Unit/SimilarityLevenshteinTest.php`:

```php
<?php

namespace Tests\Unit;

use bb\classes\Similarity;
use PHPUnit\Framework\TestCase;

class SimilarityLevenshteinTest extends TestCase
{
    /**
     * Пары, которые триграммный Jaccard пропускает (см. докблок Similarity):
     * аксессуар/акссесуар — 0.40, Chicco/Chico — 0.40, Батик/Ботик — 0.20.
     * Все три — одиночная опечатка в коротком слове, editSimilarity обязана
     * их ловить через расстояние редактирования, а не через триграммы.
     */
    public function test_edit_similarity_catches_typos_jaccard_misses(): void
    {
        $this->assertGreaterThanOrEqual(0.55, Similarity::editSimilarity('аксессуар', 'акссесуар'));
        $this->assertGreaterThanOrEqual(0.55, Similarity::editSimilarity('Chicco', 'Chico'));
        $this->assertGreaterThanOrEqual(0.55, Similarity::editSimilarity('Батик', 'Ботик'));
    }

    /**
     * Medel/Medela — РАЗНЫЕ реальные бренды (см. спеку 2026-08-14). По
     * расстоянию Левенштейна они дают 0.83 — это ПРЕДУПРЕЖДЕНИЕ (< 1.0),
     * не запрет. Запрет — только точное совпадение нормализованной строки.
     */
    public function test_medel_medela_is_warning_not_exact_duplicate(): void
    {
        $score = Similarity::combinedScore('Medel', 'Medela');

        $this->assertGreaterThanOrEqual(0.55, $score);
        $this->assertLessThan(1.0, $score);
        $this->assertFalse(Similarity::normalize('Medel') === Similarity::normalize('Medela'));
    }

    public function test_combined_score_is_max_of_both_metrics(): void
    {
        $a = 'Maxi Cosi';
        $b = 'подгузники'; // заведомо непохоже ни по триграммам, ни по Левенштейну

        $this->assertSame(
            max(Similarity::score($a, $b), Similarity::editSimilarity($a, $b)),
            Similarity::combinedScore($a, $b)
        );
    }

    public function test_edit_similarity_is_utf8_safe(): void
    {
        // Кириллица — 2 байта/символ в UTF-8. Штатный levenshtein() читает
        // байты, поэтому на разнице в одну БУКВУ дал бы дистанцию 2 (два
        // байта), а не 1. editSimilarity должна считать посимвольно.
        $this->assertEquals(1 - 1 / 5, Similarity::editSimilarity('Батик', 'Ботик'), '', 0.001);
    }

    public function test_find_similar_by_edit_excludes_exact_matches(): void
    {
        $haystack = ['a' => 'Chicco', 'b' => 'Chico', 'c' => 'CHICCO'];

        $found = Similarity::findSimilarByEdit('Chicco', $haystack);

        $keys = array_column($found, 'key');
        $this->assertContains('b', $keys, 'Chico похож на Chicco — должен найтись');
        $this->assertNotContains('c', $keys, 'CHICCO — точный дубль после normalize(), не "похожий"');
    }

    public function test_find_similar_by_edit_respects_threshold_and_limit(): void
    {
        $haystack = ['a' => 'Chico', 'b' => 'Совершенно другое слово'];

        $found = Similarity::findSimilarByEdit('Chicco', $haystack, 0.55, 1);

        $this->assertCount(1, $found);
        $this->assertSame('a', $found[0]['key']);
    }
}
```

- [ ] **Step 2: Прогнать тест, убедиться что падает**

```bash
docker compose exec -T app php artisan test --filter=SimilarityLevenshteinTest
```
Ожидается: FAIL — `Call to undefined method bb\classes\Similarity::editSimilarity()`.

- [ ] **Step 3: Реализовать методы**

В `bb/classes/Similarity.php` добавить после метода `trigrams()` (перед `score()`) приватный расчёт расстояния и три публичных метода в конец класса (перед закрывающей `}`):

```php
    /**
     * Расстояние Левенштейна, посимвольно (не побайтово — см. докблок класса).
     *
     * @param  string[]  $a  символы первой строки (уже разбита preg_split('//u'))
     * @param  string[]  $b  символы второй строки
     * @return int
     */
    private static function levenshteinChars(array $a, array $b)
    {
        $la = count($a);
        $lb = count($b);

        $prev = range(0, $lb);
        for ($i = 1; $i <= $la; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $cost);
            }
            $prev = $cur;
        }

        return $prev[$lb];
    }
```

И три публичных метода в самом конце класса, перед `}`:

```php
    /**
     * Похожесть через расстояние Левенштейна на нормализованных строках.
     *
     * Ловит одиночные опечатки в КОРОТКИХ словах, которые триграммный
     * Jaccard пропускает: у пятибуквенного слова всего ~5 триграмм, и одна
     * изменённая буква убивает три из них разом. Используется отдельно от
     * score() — не подменяет его, а дополняет через combinedScore().
     *
     * @return float 0.0..1.0
     */
    public static function editSimilarity($a, $b)
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        if ($na === '' || $nb === '') {
            return 0.0;
        }
        if ($na === $nb) {
            return 1.0;
        }

        $ca = preg_split('//u', $na, -1, PREG_SPLIT_NO_EMPTY);
        $cb = preg_split('//u', $nb, -1, PREG_SPLIT_NO_EMPTY);
        $maxLen = max(count($ca), count($cb));

        return 1 - self::levenshteinChars($ca, $cb) / $maxLen;
    }

    /**
     * Максимум из триграммного Jaccard и расстояния Левенштейна. Каждая
     * метрика ловит свой тип опечатки — сильные стороны одной перекрывают
     * слабости другой (см. докблок editSimilarity()).
     *
     * @return float 0.0..1.0
     */
    public static function combinedScore($a, $b)
    {
        return max(self::score($a, $b), self::editSimilarity($a, $b));
    }

    /**
     * Как findSimilar(), но по combinedScore() вместо чистого Jaccard.
     * Отдельный метод (а не флаг в findSimilar()), чтобы не менять поведение
     * уже отревьюженного и используемого категориями findSimilar().
     *
     * @param  string                      $needle
     * @param  array<int|string, string>   $haystack
     * @param  float                       $min
     * @param  int                         $limit
     * @return array<int, array{key: int|string, label: string, score: float}>
     */
    public static function findSimilarByEdit($needle, array $haystack, $min = self::DEFAULT_THRESHOLD, $limit = 5)
    {
        $out = [];

        foreach ($haystack as $key => $label) {
            $s = self::combinedScore($needle, (string) $label);

            if ($s >= $min && $s < 1.0) {
                $out[] = ['key' => $key, 'label' => (string) $label, 'score' => round($s, 3)];
            }
        }

        usort($out, function ($x, $y) {
            return $y['score'] < $x['score'] ? -1 : ($y['score'] > $x['score'] ? 1 : 0);
        });

        return array_slice($out, 0, $limit);
    }
```

- [ ] **Step 4: Прогнать тест снова**

```bash
docker compose exec -T app php artisan test --filter=SimilarityLevenshteinTest
```
Ожидается: PASS, 6 тестов.

- [ ] **Step 5: Проверить существующие тесты категорий не сломались**

```bash
docker compose exec -T app php -l bb/classes/Similarity.php
docker compose exec -T app php artisan test --filter=Similarity
```
(если найдутся другие тесты, использующие `Similarity` — они не должны были измениться, `score()`/`findSimilar()`/`findExact()` не тронуты).

- [ ] **Step 6: Commit**

```bash
git add bb/classes/Similarity.php tests/Unit/SimilarityLevenshteinTest.php
git commit -m "feat(bb): Similarity — расстояние Левенштейна вторым сигналом дублей"
```

---

### Task 2: Миграция — таблица `producers`

**Files:**
- Create: `database/migrations/2026_08_15_000001_create_producers_table.php`

**Interfaces:**
- Produces: таблица `producers` с колонками `producer_id, name, name_norm, logo, comment, is_active, cr_time, cr_user_id, ch_time, ch_user_id`.

- [ ] **Step 1: Написать миграцию**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Справочник производителей: одно каноничное написание бренда, один
 * логотип на бренд. `tovar_rent.producer` (varchar(365)) остаётся строкой —
 * её читают 52 файла, переход на producer_id с внешним ключом несоразмерен
 * риску (docs/superpowers/specs/2026-08-14-producers-directory-design.md).
 *
 * `name` — 365 символов вслед за длиной исходной колонки, чтобы ни одно
 * существующее значение не обрезалось при засеве. Уникальный индекс на
 * utf8mb4(365) укладывается в лимит префикса InnoDB только при
 * innodb_large_prefix=ON (MariaDB 10.6 — по умолчанию включено, ROW_FORMAT
 * DYNAMIC у новых таблиц тоже по умолчанию).
 */
class CreateProducersTable extends Migration
{
    public function up()
    {
        Schema::create('producers', function (Blueprint $table) {
            $table->increments('producer_id');
            $table->string('name', 365);
            $table->unique('name');
            $table->string('name_norm', 365);
            $table->index('name_norm');
            $table->string('logo')->default('');
            $table->string('comment', 255)->default('');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('cr_time')->nullable();
            $table->unsignedInteger('cr_user_id')->nullable();
            $table->unsignedInteger('ch_time')->nullable();
            $table->unsignedInteger('ch_user_id')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('producers');
    }
}
```

- [ ] **Step 2: Прогнать миграцию**

```bash
docker compose exec -T app php artisan migrate --path=database/migrations/2026_08_15_000001_create_producers_table.php
```
Ожидается: `Migrating: ..._create_producers_table` → `Migrated:`.

- [ ] **Step 3: Проверить схему**

```bash
docker compose exec -T db mysql -utiktakby_tiktak -p"$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)" tiktakby_tiktak -e "SHOW CREATE TABLE producers\G"
```
Ожидается: колонки как в миграции, `UNIQUE KEY` на `name`, `KEY` на `name_norm`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_15_000001_create_producers_table.php
git commit -m "feat(bb): миграция — таблица producers"
```

---

### Task 3: Миграция — засев `producers` из существующих данных

Источник — `DISTINCT producer` из трёх таблиц (`tovar_rent`, `tovar_rent_items`, `tovar_rent_items_arch`), не только `tovar_rent`: архивные строки не всегда 1-в-1 совпадают с живыми моделями, а терять значение при засеве нельзя (см. спеку, раздел «Логотип»). Логотип — `MAX(logo)` по моделям бренда в `tovar_rent`, как сейчас делает `Producer::getAllProducersTovExists()`.

Список гашения (`is_active=0`) — **ровно** те значения, что уже названы не-брендами в одобренной спеке (`РБ`, `РФ`, `Польша`, `вечернее`, `-`). `аксессуар`/`акссесуар` туда **не входит** — это пара на слияние (Task, который делается отдельной миграцией на прод-данных после ревью владельцем, см. `docs/prod_pending.md`), а не на гашение; здесь она сеется как два обычных активных значения.

**Files:**
- Create: `database/migrations/2026_08_15_000002_seed_producers_table.php`
- Test: `tests/Feature/ProducersSeedTest.php` (создать)

**Interfaces:**
- Consumes: `bb\classes\Similarity::normalize()` (Task 1, уже существует).
- Produces: заполненная таблица `producers`.

- [ ] **Step 1: Написать падающий тест**

```php
<?php

namespace Tests\Feature;

use bb\classes\Similarity;
use bb\Db;
use Tests\TestCase;

class ProducersSeedTest extends TestCase
{
    public function test_seed_covers_every_distinct_producer_value(): void
    {
        $mysqli = Db::getInstance()->getConnection();

        $expected = $mysqli->query("
            SELECT DISTINCT producer FROM (
                SELECT producer FROM tovar_rent WHERE producer <> ''
                UNION SELECT producer FROM tovar_rent_items WHERE producer <> ''
                UNION SELECT producer FROM tovar_rent_items_arch WHERE producer <> ''
            ) u
        ")->fetch_all();
        $expectedNames = array_column($expected, 0);

        $seeded = $mysqli->query('SELECT name FROM producers')->fetch_all();
        $seededNames = array_column($seeded, 0);

        sort($expectedNames);
        sort($seededNames);
        $this->assertSame($expectedNames, $seededNames);
    }

    public function test_seed_is_idempotent(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $before = $mysqli->query('SELECT COUNT(*) n FROM producers')->fetch_assoc()['n'];

        (new \SeedProducersTable())->up();

        $after = $mysqli->query('SELECT COUNT(*) n FROM producers')->fetch_assoc()['n'];
        $this->assertSame($before, $after);
    }

    public function test_seed_gates_known_non_brands(): void
    {
        $mysqli = Db::getInstance()->getConnection();

        foreach (['РБ', 'РФ', 'Польша', 'вечернее', '-'] as $value) {
            $row = $mysqli->query("SELECT is_active FROM producers WHERE name='" . addslashes($value) . "'")->fetch_assoc();
            if ($row === null) {
                continue; // значения могло не быть в текущем снимке данных
            }
            $this->assertSame(0, (int) $row['is_active'], "«$value» должен быть скрыт (is_active=0)");
        }
    }

    public function test_seed_carries_logo_from_max_over_producer_models(): void
    {
        $mysqli = Db::getInstance()->getConnection();

        $row = $mysqli->query("
            SELECT tr.producer, MAX(NULLIF(w.logo, '')) AS logo
            FROM tovar_rent tr
            JOIN rent_model_web w ON w.model_id = tr.tovar_rent_id
            WHERE w.logo <> ''
            GROUP BY tr.producer
            LIMIT 1
        ")->fetch_assoc();

        if ($row === null) {
            $this->markTestSkipped('нет ни одной модели с логотипом в текущем снимке данных');
        }

        $seededLogo = $mysqli->query("SELECT logo FROM producers WHERE name='" . addslashes($row['producer']) . "'")
            ->fetch_assoc()['logo'];

        $this->assertSame($row['logo'], $seededLogo);
    }

    public function test_name_norm_matches_similarity_normalize(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $row = $mysqli->query('SELECT name, name_norm FROM producers LIMIT 1')->fetch_assoc();

        if ($row === null) {
            $this->markTestSkipped('справочник пуст');
        }

        $this->assertSame(Similarity::normalize($row['name']), $row['name_norm']);
    }
}
```

- [ ] **Step 2: Прогнать тест, убедиться что падает**

```bash
docker compose exec -T app php artisan test --filter=ProducersSeedTest
```
Ожидается: FAIL — `producers` пуста (миграция засева ещё не написана).

- [ ] **Step 3: Реализовать миграцию**

```php
<?php

use bb\classes\Similarity;
use bb\Db;
use Illuminate\Database\Migrations\Migration;

/**
 * Засевает producers из ВСЕХ существующих значений producer — без
 * автослияния (это отдельный шаг, детектор дублей только предупреждает,
 * решает человек — см. спеку). Источник — объединение трёх таблиц, а не
 * только tovar_rent: архивные строки не всегда совпадают с живыми моделями
 * 1-в-1, а терять значение при засеве нельзя (иначе заливка логотипа для
 * такого бренда молча уйдёт только в старую rent_model_web.logo и никогда
 * не попадёт в справочник).
 *
 * Список гашения — ТОЛЬКО значения, уже названные не-брендами в одобренной
 * спеке (2026-08-14-producers-directory-design.md, раздел «Поле используется
 * не только под бренд»). Список для ПРОДА владелец подтверждает отдельно
 * перед деплоем (docs/prod_pending.md) — он может отличаться от локального.
 */
class SeedProducersTable extends Migration
{
    private const GATED_NON_BRANDS = ['РБ', 'РФ', 'Польша', 'вечернее', '-'];

    public function up()
    {
        $mysqli = Db::getInstance()->getConnection();

        $names = $mysqli->query("
            SELECT DISTINCT producer FROM (
                SELECT producer FROM tovar_rent WHERE producer <> ''
                UNION SELECT producer FROM tovar_rent_items WHERE producer <> ''
                UNION SELECT producer FROM tovar_rent_items_arch WHERE producer <> ''
            ) u
        ")->fetch_all();

        $now = time();

        foreach ($names as $row) {
            $name = $row[0];
            $escaped = addslashes($name);

            $exists = $mysqli->query("SELECT producer_id FROM producers WHERE name='$escaped'")->fetch_assoc();
            if ($exists) {
                continue; // идемпотентность: повторный прогон ничего не дублирует
            }

            $logoRow = $mysqli->query("
                SELECT MAX(NULLIF(w.logo, '')) AS logo
                FROM tovar_rent tr
                JOIN rent_model_web w ON w.model_id = tr.tovar_rent_id
                WHERE tr.producer = '$escaped'
            ")->fetch_assoc();
            $logo = $logoRow && $logoRow['logo'] !== null ? $logoRow['logo'] : '';

            $isActive = in_array($name, self::GATED_NON_BRANDS, true) ? 0 : 1;
            $nameNorm = addslashes(Similarity::normalize($name));
            $logoEscaped = addslashes($logo);

            $mysqli->query("
                INSERT INTO producers SET
                    name='$escaped', name_norm='$nameNorm', logo='$logoEscaped',
                    is_active=$isActive, cr_time=$now
            ");
        }
    }

    public function down()
    {
        Db::getInstance()->getConnection()->query('TRUNCATE TABLE producers');
    }
}
```

`bb\classes\Similarity` подключается через composer autoload (миграция лежит под `database/migrations/`, которая грузится Laravel-бутстрапом — `use bb\classes\Similarity;` работает без `require_once`, в отличие от файлов `bb/`).

- [ ] **Step 4: Прогнать миграцию**

```bash
docker compose exec -T app php artisan migrate --path=database/migrations/2026_08_15_000002_seed_producers_table.php
```

- [ ] **Step 5: Прогнать тест снова**

```bash
docker compose exec -T app php artisan test --filter=ProducersSeedTest
```
Ожидается: PASS, 5 тестов (возможны SKIP на `test_seed_carries_logo_...`/`test_name_norm_...`, если в тестовой БД нет данных — это нормально).

- [ ] **Step 6: Сверить количество вручную**

```bash
docker compose exec -T db mysql -utiktakby_tiktak -p"$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)" tiktakby_tiktak -e "
SELECT COUNT(*) total, SUM(is_active=0) gated, SUM(logo<>'') with_logo FROM producers;"
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_15_000002_seed_producers_table.php tests/Feature/ProducersSeedTest.php
git commit -m "feat(bb): миграция — засев producers из существующих данных"
```

---

### Task 4: `bb/classes/Producer.php` — ядро (поиск, создание)

Переписывает существующий класс `Producer` (сейчас — тонкий DTO над `Producer::getAllProducersTovExists()`) в полноценную DB-backed модель над таблицей `producers`. Публичный метод `getAllProducersTovExists()` и его возвращаемый тип (`getName()`, `getUrl()`, `getNameUrlEncoded()`) сохраняются без изменений — `MainPage.php`/`home.blade.php` не трогаются вообще (см. Task 11).

**Files:**
- Modify: `bb/classes/Producer.php` (полная замена содержимого)
- Test: `tests/Unit/ProducerTest.php` (создать)

**Interfaces:**
- Consumes: `bb\classes\Similarity::normalize()`, `::findExact()`, `::findSimilarByEdit()` (Task 1); `bb\Db::getInstance()->getConnection()`.
- Produces: `Producer::getById($id): Producer|false`, `Producer::getByName($name): Producer|false` (точное совпадение сырой строки — по этому методу его ищет `ModelWeb`, см. Task 11), `Producer::getAllActive(): Producer[]`, `Producer::getAll(): Producer[]` (включая скрытые), `Producer::findDuplicates($name): array{exact: Producer|null, similar: array}`, `->getId()`, `->getName()`, `->setName()`, `->getLogo()`, `->setLogo()`, `->getComment()`, `->setComment()`, `->isActive()`, `->setActive(bool)`, `->save(): bool`. Метод `getAllProducersTovExists()` сохраняется (см. Task 11 — реализация меняется там).

- [ ] **Step 1: Написать падающий тест**

```php
<?php

namespace Tests\Unit;

use bb\classes\Producer;
use bb\Db;
use Tests\TestCase;

class ProducerTest extends TestCase
{
    private const SANDBOX_NAME = 'Тестовый Производитель ZZZ';

    protected function setUp(): void
    {
        parent::setUp();
        $this->purgeSandbox();
    }

    protected function tearDown(): void
    {
        $this->purgeSandbox();
        parent::tearDown();
    }

    private function purgeSandbox(): void
    {
        Db::getInstance()->getConnection()->query(
            "DELETE FROM producers WHERE name LIKE 'Тестовый Производитель%'"
        );
    }

    public function test_save_inserts_then_update_reuses_id(): void
    {
        $p = new Producer();
        $p->setName(self::SANDBOX_NAME);
        $p->setLogo('/img/test.webp');
        $this->assertTrue($p->save());
        $id = $p->getId();
        $this->assertGreaterThan(0, $id);

        $p->setComment('заметка');
        $this->assertTrue($p->save());
        $this->assertSame($id, $p->getId());

        $reloaded = Producer::getById($id);
        $this->assertSame('заметка', $reloaded->getComment());
    }

    public function test_get_by_name_is_exact_match(): void
    {
        $p = new Producer();
        $p->setName(self::SANDBOX_NAME);
        $p->save();

        $this->assertNotFalse(Producer::getByName(self::SANDBOX_NAME));
        $this->assertFalse(Producer::getByName(self::SANDBOX_NAME . ' другое'));
    }

    public function test_get_all_active_excludes_hidden(): void
    {
        $p = new Producer();
        $p->setName(self::SANDBOX_NAME);
        $p->setActive(false);
        $p->save();

        $activeNames = array_map(function (Producer $x) { return $x->getName(); }, Producer::getAllActive());
        $this->assertNotContains(self::SANDBOX_NAME, $activeNames);

        $allNames = array_map(function (Producer $x) { return $x->getName(); }, Producer::getAll());
        $this->assertContains(self::SANDBOX_NAME, $allNames);
    }

    public function test_find_duplicates_reports_exact(): void
    {
        $p = new Producer();
        $p->setName(self::SANDBOX_NAME);
        $p->save();

        $result = Producer::findDuplicates(self::SANDBOX_NAME);
        $this->assertNotNull($result['exact']);
        $this->assertSame(self::SANDBOX_NAME, $result['exact']->getName());
    }

    public function test_find_duplicates_reports_similar_typo(): void
    {
        $p = new Producer();
        $p->setName('Тестовый Производитель ZZZ Chicco');
        $p->save();

        $result = Producer::findDuplicates('Тестовый Производитель ZZZ Chico');
        $this->assertNull($result['exact']);
        $this->assertNotEmpty($result['similar']);
    }

    public function test_find_duplicates_sees_hidden_producers(): void
    {
        $p = new Producer();
        $p->setName(self::SANDBOX_NAME);
        $p->setActive(false);
        $p->save();

        $result = Producer::findDuplicates(self::SANDBOX_NAME);
        $this->assertNotNull($result['exact'], 'скрытый бренд обязан находиться по точному имени (см. спеку)');
    }
}
```

- [ ] **Step 2: Прогнать тест, убедиться что падает**

```bash
docker compose exec -T app php artisan test --filter=ProducerTest
```
Ожидается: FAIL — старый `Producer` не имеет `setName()`/`save()`/`getById()` и т.д.

- [ ] **Step 3: Переписать `bb/classes/Producer.php`**

Полностью заменить содержимое файла:

```php
<?php

namespace bb\classes;

use bb\Db;

require_once __DIR__ . '/Similarity.php';

/**
 * Справочник производителей (`producers`). Один бренд — одна запись, один
 * логотип. `tovar_rent.producer` остаётся свободной строкой — справочник
 * источник для ЗАПИСИ и для витрин, 52 читающих места не переписываются
 * (docs/superpowers/specs/2026-08-14-producers-directory-design.md).
 *
 * Скрытые (is_active=0) не показываются в подсказках (getAllActive()), но
 * находятся при вводе точного названия через findDuplicates() — иначе
 * скрытый бренд стало бы невозможно найти и включить обратно, отдельной
 * страницы управления в проекте нет.
 */
class Producer
{
    private $producer_id;
    private $name = '';
    private $name_norm = '';
    private $logo = '';
    private $comment = '';
    private $is_active = true;
    private $cr_time;
    private $cr_user_id;
    private $ch_time;
    private $ch_user_id;

    public static function getMysqlTableName()
    {
        return 'producers';
    }

    public function getId()
    {
        return $this->producer_id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Тот же контракт, что был у старого DTO: адрес логотипа без
     * абсолютного префикса домена. Сохранён ради home.blade.php — см. Task 11.
     */
    public function getUrl()
    {
        return str_replace('http://www.tiktak.by', '', $this->logo);
    }

    public function getLogo()
    {
        return $this->logo;
    }

    public function setLogo($logo)
    {
        $this->logo = $logo;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    public function isActive()
    {
        return (bool) $this->is_active;
    }

    public function setActive($active)
    {
        $this->is_active = (bool) $active;
    }

    public function getNameUrlEncoded()
    {
        return urlencode($this->getName());
    }

    private static function createFromDbArray($row)
    {
        $p = new self();
        $p->producer_id = (int) $row['producer_id'];
        $p->name        = $row['name'];
        $p->name_norm   = $row['name_norm'];
        $p->logo        = $row['logo'];
        $p->comment     = $row['comment'];
        $p->is_active   = (bool) $row['is_active'];
        $p->cr_time     = $row['cr_time'];
        $p->cr_user_id  = $row['cr_user_id'];
        $p->ch_time     = $row['ch_time'];
        $p->ch_user_id  = $row['ch_user_id'];

        return $p;
    }

    /**
     * @return Producer|false
     */
    public static function getById($id)
    {
        $id = (int) $id;
        if ($id < 1) {
            return false;
        }

        $mysqli = Db::getInstance()->getConnection();
        $result = $mysqli->query("SELECT * FROM producers WHERE producer_id=$id");
        if (!$result || $result->num_rows < 1) {
            return false;
        }

        return self::createFromDbArray($result->fetch_assoc());
    }

    /**
     * Точное совпадение СЫРОЙ строки (не нормализованной) — этим методом
     * ModelWeb ищет бренд модели по значению tovar_rent.producer, которое
     * должно 1-в-1 совпадать со значением, записанным через справочник.
     *
     * @return Producer|false
     */
    public static function getByName($name)
    {
        $name = (string) $name;
        if ($name === '') {
            return false;
        }

        $mysqli = Db::getInstance()->getConnection();
        $escaped = addslashes($name);
        $result = $mysqli->query("SELECT * FROM producers WHERE name='$escaped'");
        if (!$result || $result->num_rows < 1) {
            return false;
        }

        return self::createFromDbArray($result->fetch_assoc());
    }

    /**
     * Для подсказок живого поиска — только активные.
     *
     * @return Producer[]
     */
    public static function getAllActive()
    {
        return self::queryAll('WHERE is_active=1');
    }

    /**
     * Включая скрытые — для проверки на дубли (findDuplicates()) и для
     * административных списков.
     *
     * @return Producer[]
     */
    public static function getAll()
    {
        return self::queryAll('');
    }

    private static function queryAll($where)
    {
        $mysqli = Db::getInstance()->getConnection();
        $result = $mysqli->query("SELECT * FROM producers $where ORDER BY name");
        if (!$result) {
            return [];
        }

        $out = [];
        while ($row = $result->fetch_assoc()) {
            $out[] = self::createFromDbArray($row);
        }

        return $out;
    }

    /**
     * Точный дубль (после нормализации, включая скрытые) + похожие названия
     * (комбинированный сигнал Jaccard+Левенштейн, только среди активных —
     * предлагать слияние со скрытым брендом сотруднику смысла нет).
     *
     * @return array{exact: Producer|null, similar: array}
     */
    public static function findDuplicates($name)
    {
        $all = self::getAll();
        $labels = [];
        foreach ($all as $i => $p) {
            $labels[$i] = $p->getName();
        }

        $exactKey = Similarity::findExact($name, $labels);
        $exact = $exactKey === false ? null : $all[$exactKey];

        $activeLabels = [];
        foreach ($all as $i => $p) {
            if ($p->isActive()) {
                $activeLabels[$i] = $p->getName();
            }
        }

        $similar = [];
        foreach (Similarity::findSimilarByEdit($name, $activeLabels) as $match) {
            $similar[] = ['producer' => $all[$match['key']], 'score' => $match['score']];
        }

        return ['exact' => $exact, 'similar' => $similar];
    }

    /**
     * @return bool
     */
    public function save()
    {
        $mysqli = Db::getInstance()->getConnection();

        $name = addslashes($this->name);
        $nameNorm = addslashes(Similarity::normalize($this->name));
        $logo = addslashes($this->logo);
        $comment = addslashes($this->comment);
        $isActive = $this->is_active ? 1 : 0;
        $now = time();

        if ($this->producer_id > 0) {
            $query = "UPDATE producers SET
                name='$name', name_norm='$nameNorm', logo='$logo', comment='$comment',
                is_active=$isActive, ch_time=$now
                WHERE producer_id={$this->producer_id}";
        } else {
            $query = "INSERT INTO producers SET
                name='$name', name_norm='$nameNorm', logo='$logo', comment='$comment',
                is_active=$isActive, cr_time=$now";
        }

        $result = $mysqli->query($query);
        if (!$result) {
            return false;
        }

        if ($this->producer_id === null) {
            $this->producer_id = $mysqli->insert_id;
        }

        \Illuminate\Support\Facades\Cache::forget('all_producers_tov_exists');

        return true;
    }
}
```

- [ ] **Step 4: Прогнать тест снова**

```bash
docker compose exec -T app php artisan test --filter=ProducerTest
```
Ожидается: PASS, 6 тестов.

- [ ] **Step 5: Проверить синтаксис**

```bash
docker compose exec -T app php -l bb/classes/Producer.php
```

- [ ] **Step 6: Commit**

```bash
git add bb/classes/Producer.php tests/Unit/ProducerTest.php
git commit -m "feat(bb): Producer — DB-backed справочник вместо DTO поверх GROUP BY"
```

---

### Task 5: `Producer` — переименование с предпросмотром и транзакцией

Три защиты из спеки: предпросмотр масштаба, одна транзакция на четыре таблицы (`producers` + `tovar_rent` + `tovar_rent_items` + `tovar_rent_items_arch`), запрет переименования в уже существующее имя (эта проверка — на уровне AJAX-эндпоинта, Task 8, а не здесь: `Producer` остаётся «глупым» персистером, как `Category::save()` не проверяет уникальность `cat_url_key` сама — это делает `ajax_category_create.php`).

**Files:**
- Modify: `bb/classes/Producer.php`
- Test: `tests/Feature/ProducerRenameTest.php` (создать)

**Interfaces:**
- Consumes: `bb\Db::startTransaction()`, `::commitTransaction()`, `::rollBackTransaction()` (уже существуют, `bb/Db.php`).
- Produces: `->impactOfRename(): array{models: int, items: int, items_arch: int}`, `->rename($newName, $userId): bool`.

- [ ] **Step 1: Написать падающий тест**

```php
<?php

namespace Tests\Feature;

use bb\classes\Producer;
use bb\Db;
use Tests\TestCase;

/**
 * rename() открывает СВОЮ транзакцию (Db::startTransaction()). В mysqli
 * вложенный START TRANSACTION неявно коммитит внешнюю — тот же готча, что
 * уже задокументирован в tests/Unit/TariffHistoryTest.php для
 * ModelArchive::archive(). Поэтому здесь НЕ оборачиваем тест в свою
 * транзакцию с расчётом на ROLLBACK в tearDown() — чистим явными DELETE
 * до и после каждого теста.
 */
class ProducerRenameTest extends TestCase
{
    private const OLD_NAME = 'Тестовый Производитель ZZZ Старый';
    private const NEW_NAME = 'Тестовый Производитель ZZZ Новый';
    private const SANDBOX_MODEL_ID = 999998;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purge();

        $_SESSION['user_id'] = 26;

        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query("
            INSERT INTO tovar_rent SET tovar_rent_id=" . self::SANDBOX_MODEL_ID . ",
            tovar_rent_cat_id=1, producer='" . self::OLD_NAME . "', model='sandbox', cr_time=" . time()
        );
        $mysqli->query("
            INSERT INTO tovar_rent_items SET item_id=" . self::SANDBOX_MODEL_ID . ",
            cat_id=1, producer='" . self::OLD_NAME . "', model_id=" . self::SANDBOX_MODEL_ID
        );
    }

    protected function tearDown(): void
    {
        $this->purge();
        unset($_SESSION['user_id']);
        parent::tearDown();
    }

    private function purge(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query('DELETE FROM tovar_rent WHERE tovar_rent_id=' . self::SANDBOX_MODEL_ID);
        $mysqli->query('DELETE FROM tovar_rent_items WHERE item_id=' . self::SANDBOX_MODEL_ID);
        $mysqli->query("DELETE FROM producers WHERE name LIKE 'Тестовый Производитель ZZZ%'");
    }

    public function test_impact_counts_affected_rows(): void
    {
        $p = new Producer();
        $p->setName(self::OLD_NAME);
        $p->save();

        $impact = $p->impactOfRename();

        $this->assertSame(1, $impact['models']);
        $this->assertSame(1, $impact['items']);
        $this->assertSame(0, $impact['items_arch']);
    }

    public function test_rename_propagates_to_all_three_tables(): void
    {
        $p = new Producer();
        $p->setName(self::OLD_NAME);
        $p->save();

        $ok = $p->rename(self::NEW_NAME, 26);
        $this->assertTrue($ok);
        $this->assertSame(self::NEW_NAME, $p->getName());

        $mysqli = Db::getInstance()->getConnection();
        $model = $mysqli->query('SELECT producer FROM tovar_rent WHERE tovar_rent_id=' . self::SANDBOX_MODEL_ID)->fetch_assoc();
        $item = $mysqli->query('SELECT producer FROM tovar_rent_items WHERE item_id=' . self::SANDBOX_MODEL_ID)->fetch_assoc();

        $this->assertSame(self::NEW_NAME, $model['producer']);
        $this->assertSame(self::NEW_NAME, $item['producer']);

        $reloaded = Producer::getByName(self::NEW_NAME);
        $this->assertNotFalse($reloaded);
        $this->assertFalse(Producer::getByName(self::OLD_NAME));
    }
}
```

- [ ] **Step 2: Прогнать тест, убедиться что падает**

```bash
docker compose exec -T app php artisan test --filter=ProducerRenameTest
```
Ожидается: FAIL — `impactOfRename()`/`rename()` не существуют.

- [ ] **Step 3: Добавить методы в `bb/classes/Producer.php`**

Добавить перед закрывающей `}` класса:

```php
    /**
     * Масштаб переименования — для предпросмотра перед подтверждением
     * (спека: «затронет 11 моделей, 15 единиц, 4 архивных записи»).
     *
     * @return array{models: int, items: int, items_arch: int}
     */
    public function impactOfRename()
    {
        $mysqli = Db::getInstance()->getConnection();
        $name = addslashes($this->name);

        $count = function ($table, $column = 'producer') use ($mysqli, $name) {
            $result = $mysqli->query("SELECT COUNT(*) n FROM $table WHERE $column='$name'");
            return (int) $result->fetch_assoc()['n'];
        };

        return [
            'models'      => $count('tovar_rent'),
            'items'       => $count('tovar_rent_items'),
            'items_arch'  => $count('tovar_rent_items_arch'),
        ];
    }

    /**
     * Переименовывает бренд везде: справочник + три таблицы каталога —
     * одной транзакцией, либо всё, либо ничего. Не проверяет, что $newName
     * уже не занят другим брендом — это ответственность вызывающего кода
     * (ajax_producer_update.php), как Category::save() не проверяет
     * уникальность cat_url_key сама.
     *
     * @return bool
     */
    public function rename($newName, $userId)
    {
        $mysqli = Db::getInstance()->getConnection();
        $oldName = addslashes($this->name);
        $newNameEsc = addslashes($newName);

        Db::startTransaction();

        $ok = $mysqli->query("UPDATE tovar_rent SET producer='$newNameEsc' WHERE producer='$oldName'")
            && $mysqli->query("UPDATE tovar_rent_items SET producer='$newNameEsc' WHERE producer='$oldName'")
            && $mysqli->query("UPDATE tovar_rent_items_arch SET producer='$newNameEsc' WHERE producer='$oldName'");

        if (!$ok) {
            Db::rollBackTransaction();
            return false;
        }

        $this->name = $newName;
        $this->ch_user_id = $userId;

        if (!$this->save()) {
            Db::rollBackTransaction();
            return false;
        }

        Db::commitTransaction();

        return true;
    }
```

- [ ] **Step 4: Прогнать тест снова**

```bash
docker compose exec -T app php artisan test --filter=ProducerRenameTest
```
Ожидается: PASS, 2 теста.

- [ ] **Step 5: Прогнать Task 4 тесты — убедиться, что ничего не сломалось**

```bash
docker compose exec -T app php artisan test --filter=ProducerTest
```

- [ ] **Step 6: Commit**

```bash
git add bb/classes/Producer.php tests/Feature/ProducerRenameTest.php
git commit -m "feat(bb): Producer::rename() — предпросмотр масштаба + транзакция на 4 таблицы"
```

---

### Task 6: `bb/ajax_producer_suggest.php` — живой поиск + проверка на дубль

Прямая калька `bb/ajax_category_suggest.php` (Task 1 предыдущей ветки) на `Producer`. Два режима: `q=<строка>` — подсказки для виджета на `tovar_new_mod.php` (активные + скрытый, если введено точное имя); `q=<строка>&check=1` — плюс проверка вводимого НОВОГО названия для модалки создания.

Опциональный `cat_id` — сортирует бренды, уже встречающиеся в выбранной категории, первыми (спека: «сначала бренды, уже встречающиеся в выбранной категории»).

**Files:**
- Create: `bb/ajax_producer_suggest.php`

**Interfaces:**
- Consumes: `Producer::getAllActive()`, `Producer::findDuplicates()` (Task 4/5).

- [ ] **Step 1: Написать эндпоинт**

```php
<?php
/**
 * Живой поиск производителей для bb/tovar_new_mod.php + проверка вводимого
 * названия на дубль/опечатку. Калька bb/ajax_category_suggest.php.
 *
 * Режимы:
 *   q=<строка>                — подсказки (активные; скрытый бренд — только
 *                                при точном совпадении, помечен hidden:true);
 *   q=<строка>&check=1        — плюс exact/similar для модалки создания;
 *   &cat_id=<id>               — бренды, уже встречавшиеся в этой категории,
 *                                идут первыми.
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Producer.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Similarity.php');

header('Content-Type: application/json; charset=utf-8');

$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    echo json_encode(['items' => [], 'error' => 'Нет доступа']);
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();

$query = trim($_REQUEST['q'] ?? '');
$check = !empty($_REQUEST['check']);
$catId = (int) ($_REQUEST['cat_id'] ?? 0);

$usedInCat = [];
if ($catId > 0) {
    $result = $mysqli->query("SELECT DISTINCT producer FROM tovar_rent WHERE tovar_rent_cat_id=$catId");
    while ($row = $result->fetch_assoc()) {
        $usedInCat[$row['producer']] = true;
    }
}

$active = \bb\classes\Producer::getAllActive();

$response = ['items' => []];

if ($query !== '') {
    $needle = \bb\classes\Similarity::normalize($query);

    $items = [];
    foreach ($active as $p) {
        if (mb_strpos(\bb\classes\Similarity::normalize($p->getName()), $needle) !== false) {
            $items[] = $p;
        }
    }

    // Точное совпадение среди СКРЫТЫХ — находится, даже если is_active=0
    // (спека: скрытый бренд нельзя было бы включить обратно иначе).
    $exactAny = \bb\classes\Producer::getByName($query);
    $alreadyThere = false;
    foreach ($items as $p) {
        if ($p->getName() === $query) { $alreadyThere = true; break; }
    }
    if ($exactAny && !$exactAny->isActive() && !$alreadyThere) {
        $items[] = $exactAny;
    }

    if ($catId > 0) {
        usort($items, function ($a, $b) use ($usedInCat) {
            $au = isset($usedInCat[$a->getName()]) ? 0 : 1;
            $bu = isset($usedInCat[$b->getName()]) ? 0 : 1;
            return $au <=> $bu ?: strcmp($a->getName(), $b->getName());
        });
    }

    $response['items'] = array_slice(array_map(function ($p) {
        return [
            'id'     => $p->getName(),
            'name'   => $p->getName(),
            'hidden' => !$p->isActive(),
        ];
    }, $items), 0, 15);
}

if ($check && $query !== '') {
    $dup = \bb\classes\Producer::findDuplicates($query);

    $response['exact'] = $dup['exact'] ? ['id' => $dup['exact']->getName(), 'name' => $dup['exact']->getName()] : null;
    $response['similar'] = array_map(function ($m) {
        return ['id' => $m['producer']->getName(), 'name' => $m['producer']->getName(), 'score' => $m['score']];
    }, $dup['similar']);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
```

Значение `id` — само имя (`valueKey: 'id'` для `LivePicker` кладёт его в hidden-поле, а форма пишет производителя строкой, не числом — в отличие от категории, у которой `id` числовой).

- [ ] **Step 2: Проверить синтаксис**

```bash
docker compose exec -T app php -l bb/ajax_producer_suggest.php
```

- [ ] **Step 3: Смоук-тест руками**

```bash
docker compose exec -T app php -r '
session_id("test"); session_start();
$_SESSION["svoi"] = 8941; $_SESSION["level"] = 5;
$_GET["q"] = "Chic";
chdir("/var/www/html");
include "bb/ajax_producer_suggest.php";
'
```
Ожидается: JSON с `items`, содержащий активные производители, у названия которых есть подстрока «Chic» после нормализации.

- [ ] **Step 4: Commit**

```bash
git add bb/ajax_producer_suggest.php
git commit -m "feat(bb): ajax_producer_suggest — живой поиск производителя + проверка дубля"
```

---

### Task 7: `bb/ajax_producer_create.php` — создание бренда из модалки

Калька `bb/ajax_category_create.php`. Поля: только название и комментарий (логотип не спрашиваем — заливается на странице веб-модели, спека). Двухуровневая защита от дублей: точный дубль — всегда отказ; похожие — предупреждение один раз, повторная отправка с `confirm=1` создаёт.

**Files:**
- Create: `bb/ajax_producer_create.php`

**Interfaces:**
- Consumes: `Producer::findDuplicates()`, `->save()` (Task 4).

- [ ] **Step 1: Написать эндпоинт**

```php
<?php
/**
 * Создание производителя из модалки на bb/tovar_new_mod.php.
 * Калька bb/ajax_category_create.php.
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Producer.php');

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload)
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    respond(['error' => 'Нет доступа']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Только POST']);
}

$name    = trim($_POST['name'] ?? '');
$comment = trim($_POST['comment'] ?? '');
$confirm = !empty($_POST['confirm']);

if (mb_strlen($name) < 2) {
    respond(['error' => 'Укажите название производителя.']);
}

$dup = \bb\classes\Producer::findDuplicates($name);

if ($dup['exact']) {
    respond([
        'error'    => 'Такой производитель уже есть: «' . $dup['exact']->getName() . '». Выберите его в списке.',
        'existing' => ['id' => $dup['exact']->getName(), 'name' => $dup['exact']->getName()],
    ]);
}

if (!$confirm && !empty($dup['similar'])) {
    respond([
        'needs_confirm' => true,
        'similar'       => array_map(function ($m) {
            return ['id' => $m['producer']->getName(), 'name' => $m['producer']->getName(), 'score' => $m['score']];
        }, $dup['similar']),
    ]);
}

$producer = new \bb\classes\Producer();
$producer->setName($name);
$producer->setComment($comment);

if (!$producer->save()) {
    respond(['error' => 'Сбой при сохранении в базу данных.']);
}

respond([
    'ok'       => true,
    'producer' => ['id' => $producer->getName(), 'name' => $producer->getName()],
]);
```

- [ ] **Step 2: Проверить синтаксис**

```bash
docker compose exec -T app php -l bb/ajax_producer_create.php
```

- [ ] **Step 3: Commit**

```bash
git add bb/ajax_producer_create.php
git commit -m "feat(bb): ajax_producer_create — создание бренда с проверкой на дубль"
```

---

### Task 8: `bb/ajax_producer_update.php` — предпросмотр и переименование

Уровни `[5, 7]` (не `[0, 5, 7]` — переименование трогает три таблицы каталога, как архивация категории). Режим `preview=1` возвращает масштаб (`impactOfRename()`) без изменений; без него — выполняет `rename()`. Переименование в уже существующее имя запрещено здесь (в `Producer::rename()` этой проверки нет — см. Task 5).

**Files:**
- Create: `bb/ajax_producer_update.php`

**Interfaces:**
- Consumes: `Producer::getById()`, `->impactOfRename()`, `->rename()`, `Producer::getByName()` (Task 4/5).

- [ ] **Step 1: Написать эндпоинт**

```php
<?php
/**
 * Переименование производителя (название, комментарий, is_active) из
 * попапа редактирования на bb/tovar_new_mod.php.
 *
 * preview=1 — только масштаб изменений, без записи (для подтверждения
 * сотрудником перед кнопкой «сохранить»).
 * Без preview — переименовывает. Запрет слияния (переименование в уже
 * существующее имя) — здесь, а не в Producer::rename(): класс остаётся
 * «глупым» персистером, как Category::save() не проверяет cat_url_key сам.
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Producer.php');

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload)
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$in_level = array(5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    respond(['error' => 'Нет доступа. Переименование доступно только уровням 5 и 7.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Только POST']);
}

$id       = (int) ($_POST['id'] ?? 0);
$newName  = trim($_POST['name'] ?? '');
$comment  = trim($_POST['comment'] ?? '');
$isActive = !empty($_POST['is_active']);
$preview  = !empty($_POST['preview']);

$producer = \bb\classes\Producer::getById($id);
if (!$producer) {
    respond(['error' => 'Производитель не найден.']);
}

if (mb_strlen($newName) < 2) {
    respond(['error' => 'Укажите название производителя.']);
}

if ($preview) {
    respond(['ok' => true, 'impact' => $producer->impactOfRename()]);
}

if ($newName !== $producer->getName()) {
    $existing = \bb\classes\Producer::getByName($newName);
    if ($existing && $existing->getId() !== $producer->getId()) {
        respond([
            'error' => 'Производитель «' . $newName . '» уже существует. '
                . 'Это слияние, а не переименование — оно делается миграцией через PR, не отсюда.',
        ]);
    }
}

$producer->setComment($comment);
$producer->setActive($isActive);

if ($newName !== $producer->getName()) {
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    if (!$producer->rename($newName, $userId)) {
        respond(['error' => 'Сбой при переименовании в базе данных.']);
    }
} elseif (!$producer->save()) {
    respond(['error' => 'Сбой при сохранении в базу данных.']);
}

respond(['ok' => true, 'producer' => ['id' => $producer->getName(), 'name' => $producer->getName()]]);
```

- [ ] **Step 2: Проверить синтаксис**

```bash
docker compose exec -T app php -l bb/ajax_producer_update.php
```

- [ ] **Step 3: Commit**

```bash
git add bb/ajax_producer_update.php
git commit -m "feat(bb): ajax_producer_update — предпросмотр и переименование, уровни 5/7"
```

---

### Task 9: `bb/assets/js/producer_picker.js` + стили

Обёртка над `LivePicker` (Task 8 предыдущей ветки, уже в `bb/assets/js/live_picker.js`) — по образцу `category_picker.js`. Живой поиск + модалка создания + модалка редактирования (название/комментарий/активность), с предпросмотром масштаба перед переименованием.

**Files:**
- Create: `bb/assets/js/producer_picker.js`
- Modify: `bb/assets/styles/category_picker.css` (добавить только чекбокс-строку — остальные классы `.catp*` уже общие и переиспользуются как есть)

**Interfaces:**
- Consumes: `window.LivePicker` (уже существует).
- Produces: `window.ProducerPicker` — не требуется, скрипт самоинициализируется по `DOMContentLoaded`, как `category_picker.js`.

- [ ] **Step 1: Добавить стиль для строки-чекбокса**

В конец `bb/assets/styles/category_picker.css` дописать:

```css

/* Строка с чекбоксом в попапе редактирования производителя. */
.catp-modal__row--checkbox {
    display: flex;
    align-items: center;
    gap: 6px;
}

.catp-modal__row--checkbox label {
    margin-bottom: 0;
}

.catp__preview {
    font-size: 12px;
    color: #555;
    margin: 4px 0 8px;
}
```

- [ ] **Step 2: Написать `producer_picker.js`**

```js
/**
 * Выбор производителя на bb/tovar_new_mod.php + создание и редактирование
 * в модалках. Построен на общем ядре live_picker.js, по образцу
 * category_picker.js.
 */
(function () {
	'use strict';

	var CHECK_DELAY = 400;

	var picker = null;
	var els = {};
	var confirmPending = false;
	var editingId = null;

	function $(id) {
		return document.getElementById(id);
	}

	function init() {
		els.search  = $('prod_search');
		els.hidden  = $('producer_select_new');
		els.chosen  = $('prod_chosen');
		els.createBtn = $('prod_create_open');
		els.editBtn   = $('prod_edit_open');
		els.createModal = $('prod_create_modal');
		els.editModal    = $('prod_edit_modal');

		if (!els.search || !els.hidden || !window.LivePicker) {
			return;
		}

		picker = new window.LivePicker({
			inputId:   'prod_search',
			hiddenId:  'producer_select_new',
			resultsId: 'prod_results',
			chosenId:  'prod_chosen',
			url:       '/bb/ajax_producer_suggest.php',
			valueKey:  'name',
			extraParams: function () {
				var catField = $('cat_select_new');
				return catField && catField.value ? { cat_id: catField.value } : {};
			},
			renderMeta: function (item) {
				return item.hidden ? 'скрыт' : '';
			},
			onChoose: function () {
				toggleEditButton();
			}
		});

		if (els.createBtn) {
			els.createBtn.addEventListener('click', openCreateModal);
		}
		if (els.editBtn) {
			els.editBtn.addEventListener('click', openEditModal);
		}

		toggleEditButton();
		initCreateModal();
		initEditModal();
	}

	function toggleEditButton() {
		if (els.editBtn) {
			els.editBtn.style.display = els.hidden.value ? 'inline-block' : 'none';
		}
	}

	// ----------------------------------------------------------- создание

	function initCreateModal() {
		if (!els.createModal) {
			return;
		}

		els.cName  = $('newprod_name');
		els.cComment = $('newprod_comment');
		els.cWarn  = $('newprod_warning');
		els.cSave  = $('newprod_save');
		els.cCancel = $('newprod_cancel');

		els.cCancel.addEventListener('click', function () { els.createModal.style.display = 'none'; });
		els.createModal.addEventListener('click', function (e) {
			if (e.target === els.createModal) { els.createModal.style.display = 'none'; }
		});

		var checkTimer = null;
		els.cName.addEventListener('input', function () {
			confirmPending = false;
			els.cSave.value = 'создать производителя';
			clearTimeout(checkTimer);
			checkTimer = setTimeout(checkCreateName, CHECK_DELAY);
		});

		els.cSave.addEventListener('click', submitCreate);
	}

	function openCreateModal() {
		els.createModal.style.display = 'flex';
		els.cName.value = els.search.value.trim();
		els.cComment.value = '';
		warn(els.cWarn, '');
		confirmPending = false;
		els.cSave.value = 'создать производителя';
		els.cName.focus();
		if (els.cName.value) {
			checkCreateName();
		}
	}

	function checkCreateName() {
		var name = els.cName.value.trim();
		if (name.length < 2) {
			warn(els.cWarn, '');
			return;
		}

		window.LivePicker.request(
			'/bb/ajax_producer_suggest.php?check=1&q=' + encodeURIComponent(name),
			null,
			function (data) {
				if (data.exact) {
					warn(els.cWarn, 'Такой производитель уже есть: «' + data.exact.name + '». Закройте окно и выберите его в списке.', 'error');
					return;
				}
				if (data.similar && data.similar.length) {
					warn(els.cWarn, 'Похожие уже есть: ' + names(data.similar) + '. Проверьте, не дубль ли это.', 'hint');
					return;
				}
				warn(els.cWarn, '');
			}
		);
	}

	function submitCreate() {
		var payload = {
			name:    els.cName.value.trim(),
			comment: els.cComment.value.trim()
		};
		if (confirmPending) {
			payload.confirm = '1';
		}

		els.cSave.disabled = true;

		window.LivePicker.request('/bb/ajax_producer_create.php', payload, function (data) {
			els.cSave.disabled = false;

			if (data.error) {
				warn(els.cWarn, data.error, 'error');
				return;
			}
			if (data.needs_confirm) {
				confirmPending = true;
				els.cSave.value = 'всё равно создать';
				warn(els.cWarn, 'Похожие: ' + names(data.similar) + '. Если это точно другой производитель — нажмите ещё раз.', 'hint');
				return;
			}
			if (data.ok) {
				picker.choose(data.producer);
				els.createModal.style.display = 'none';
			}
		}, function () {
			els.cSave.disabled = false;
			warn(els.cWarn, 'Не удалось связаться с сервером. Попробуйте ещё раз.', 'error');
		});
	}

	// -------------------------------------------------------- редактирование

	function initEditModal() {
		if (!els.editModal) {
			return;
		}

		els.eName    = $('editprod_name');
		els.eComment = $('editprod_comment');
		els.eActive  = $('editprod_active');
		els.ePreview = $('editprod_preview');
		els.eWarn    = $('editprod_warning');
		els.eSave    = $('editprod_save');
		els.eCancel  = $('editprod_cancel');

		els.eCancel.addEventListener('click', function () { els.editModal.style.display = 'none'; });
		els.editModal.addEventListener('click', function (e) {
			if (e.target === els.editModal) { els.editModal.style.display = 'none'; }
		});

		els.eSave.addEventListener('click', submitEdit);
	}

	function openEditModal() {
		var name = els.hidden.value;
		if (!name) {
			return;
		}

		window.LivePicker.request(
			'/bb/ajax_producer_suggest.php?check=1&q=' + encodeURIComponent(name),
			null,
			function (data) {
				var current = data.exact;
				if (!current) {
					return;
				}

				editingId = current.id;
				els.eName.value = current.name;
				els.eComment.value = '';
				els.eActive.checked = true;
				warn(els.eWarn, '');
				els.ePreview.textContent = '';
				els.editModal.style.display = 'flex';
				els.eName.focus();

				loadPreview();
			}
		);
	}

	function loadPreview() {
		window.LivePicker.request('/bb/ajax_producer_update.php', {
			id: editingId, name: els.eName.value.trim(), preview: '1'
		}, function (data) {
			if (data.ok) {
				els.ePreview.textContent = 'Затронет: моделей — ' + data.impact.models
					+ ', единиц товара — ' + data.impact.items
					+ ', архивных записей — ' + data.impact.items_arch + '.';
			}
		});
	}

	function submitEdit() {
		var payload = {
			id:        editingId,
			name:      els.eName.value.trim(),
			comment:   els.eComment.value.trim(),
			is_active: els.eActive.checked ? '1' : ''
		};

		els.eSave.disabled = true;

		window.LivePicker.request('/bb/ajax_producer_update.php', payload, function (data) {
			els.eSave.disabled = false;

			if (data.error) {
				warn(els.eWarn, data.error, 'error');
				return;
			}
			if (data.ok) {
				picker.choose(data.producer);
				els.editModal.style.display = 'none';
			}
		}, function () {
			els.eSave.disabled = false;
			warn(els.eWarn, 'Не удалось связаться с сервером. Попробуйте ещё раз.', 'error');
		});
	}

	// ------------------------------------------------------------- утилиты

	function names(list) {
		return list.map(function (item) { return '«' + item.name + '»'; }).join(', ');
	}

	function warn(el, text, kind) {
		el.textContent = text || '';
		el.className = 'catp__warn' + (text ? ' catp__warn--' + (kind || 'hint') : '');
		el.style.display = text ? 'block' : 'none';
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
```

- [ ] **Step 3: Проверить синтаксис**

```bash
node --check bb/assets/js/producer_picker.js
```

- [ ] **Step 4: Commit**

```bash
git add bb/assets/js/producer_picker.js bb/assets/styles/category_picker.css
git commit -m "feat(bb): producer_picker.js — живой поиск + попапы создания/редактирования"
```

---

### Task 10: Подключить виджет на `bb/tovar_new_mod.php`

Заменяет `<select name="producer_select_new">` + `<input name="producer_input_new">` (строки «Фирма:», см. текущую разметку) на виджет живого поиска. PHP-обработка `сохранить`/`обновить` читает готовое имя из одного поля вместо `if ($producer_select_new != '0') {...} else {...}`.

**Files:**
- Modify: `bb/tovar_new_mod.php`

**Interfaces:**
- Consumes: `bb/ajax_producer_suggest.php`, `bb/ajax_producer_create.php`, `bb/ajax_producer_update.php` (Task 6–8), `bb/assets/js/producer_picker.js` (Task 9).

- [ ] **Step 1: Подключить CSS/JS в `<head>`/перед `</body>`**

В `bb/tovar_new_mod.php` найти строку (уже подключённую в предыдущей ветке):
```php
<script src="/bb/assets/js/category_picker.js?v=2"></script>
```
и сразу после неё добавить:
```php
<script src="/bb/assets/js/producer_picker.js?v=1"></script>
```

- [ ] **Step 2: Заменить разметку поля «Фирма:»**

Найти блок (текущая разметка, строки ~487–498):
```php
	<tr>
		<td>Фирма:</td>
		<td>
			<select name="producer_select_new" id="producer_select_new" onchange="select_ch2(\'producer_select_new\', \'producer_input_new\');" style="width:220px;" >
			    	<option value="0">ввести нового производителя</option>';
while ($prod_names = $result_prod->fetch_assoc()) {
	echo '
					<option value="' . good_print($prod_names['producer']) . '" ' . sel_d($model_def['producer'], $prod_names['producer']) . '>' . good_print($prod_names['producer']) . '</option>
					';
}
echo '</select>
			<input type="text" name="producer_input_new" size="30" id="producer_input_new" ' . ($action == 'редактировать' ? 'disabled="disabled"' : '') . ' />
		</td>
	</tr>
```

Заменить на:
```php
	<tr>
		<td>Фирма:</td>
		<td>
			<div class="catp">
				<input type="text" id="prod_search" class="catp__input" autocomplete="off"
					placeholder="начните вводить название производителя"
					value="' . good_print($model_def['producer']) . '" />
				<div id="prod_results" class="catp__results"></div>
				<div id="prod_chosen" class="catp__chosen"' . ($model_def['producer'] !== '' ? ' style="display:block;"' : '') . '>'
					. ($model_def['producer'] !== '' ? 'Выбрано: ' . good_print($model_def['producer']) : '') . '</div>
			</div>
			<input type="hidden" name="producer_select_new" id="producer_select_new" value="' . good_print($model_def['producer']) . '" />
			<input type="button" id="prod_create_open" value="+ создать производителя" />
			<input type="button" id="prod_edit_open" value="редактировать" style="display:none;" />
		</td>
	</tr>
```

Удалить теперь неиспользуемый PHP-запрос чуть выше (`$query_prod`/`$result_prod` — `SELECT DISTINCT producer FROM tovar_rent ORDER BY producer`), так как разметка больше не перебирает `$result_prod->fetch_assoc()`:
```php
//chose tovar producers
$query_prod = "SELECT DISTINCT producer FROM tovar_rent ORDER BY producer";
$result_prod = $mysqli->query($query_prod);
if (!$result_prod) {
	die('Сбой при доступе к базе данных: ' . $query_prod . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
```
— эти четыре строки убрать целиком.

- [ ] **Step 3: Добавить обе модалки перед `</body>`**

Рядом с уже существующей модалкой категории (`<div id="cat_modal" class="catp-modal">...`) добавить две новые:

```php
<div id="prod_create_modal" class="catp-modal">
	<div class="catp-modal__box">
		<h3>Новый производитель</h3>
		<div id="newprod_warning" class="catp__warn"></div>
		<div class="catp-modal__row">
			<label>Название</label>
			<input type="text" id="newprod_name" />
		</div>
		<div class="catp-modal__row">
			<label>Комментарий (необязательно)</label>
			<input type="text" id="newprod_comment" />
		</div>
		<div class="catp-modal__actions">
			<input type="button" id="newprod_cancel" value="отмена" />
			<input type="button" id="newprod_save" value="создать производителя" />
		</div>
	</div>
</div>

<div id="prod_edit_modal" class="catp-modal">
	<div class="catp-modal__box">
		<h3>Редактировать производителя</h3>
		<div id="editprod_warning" class="catp__warn"></div>
		<div class="catp-modal__row">
			<label>Название</label>
			<input type="text" id="editprod_name" />
		</div>
		<div class="catp-modal__row">
			<label>Комментарий</label>
			<input type="text" id="editprod_comment" />
		</div>
		<div class="catp-modal__row catp-modal__row--checkbox">
			<input type="checkbox" id="editprod_active" />
			<label for="editprod_active">Показывать в подсказках</label>
		</div>
		<div id="editprod_preview" class="catp__preview"></div>
		<div class="catp-modal__actions">
			<input type="button" id="editprod_cancel" value="отмена" />
			<input type="button" id="editprod_save" value="сохранить" />
		</div>
	</div>
</div>
```

- [ ] **Step 4: Обновить PHP-обработку в веткам «сохранить» и «обновить»**

В ветке `case 'сохранить':` заменить:
```php
			//определяем наименование производителя
			if ($producer_select_new != '0') {
				$producer_name = $producer_select_new;
			} else {
				$producer_name = $producer_input_new;
			}
```
на:
```php
			// Производитель выбирается живым поиском по справочнику; новый
			// заводится в модалке через bb/ajax_producer_create.php — сюда
			// приходит уже готовое имя (не '0'/пусто — форма это не пускает
			// дальше на клиенте, но подстрахуемся и на сервере).
			$producer_name = trim($producer_select_new);
			if ($producer_name === '') {
				die('Производитель не выбран. Найдите его в поле «Фирма» '
					. 'или создайте кнопкой «+ создать производителя».');
			}
```

В ветке `case 'обновить':` заменить:
```php
			//далее, апдейтим модель
			$producer_select_new == '0' ? $producer_name = $producer_input_new : $producer_name = $producer_select_new;
```
на:
```php
			//далее, апдейтим модель
			$producer_name = trim($producer_select_new);
			if ($producer_name === '') {
				die('Производитель не выбран. Найдите его в поле «Фирма» '
					. 'или создайте кнопкой «+ создать производителя».');
			}
```

- [ ] **Step 5: Убрать мёртвый `select_ch2()` для производителя**

Функция `select_ch2()` (строки ~306–316) больше не вызывается для `producer_select_new` (только что убрали её единственный вызов из разметки). Проверить, не осталось ли других вызовов:
```bash
grep -n "select_ch2" bb/tovar_new_mod.php
```
Если вызовов не осталось — удалить саму функцию `select_ch2()`. Если остались (например, для модели) — оставить как есть, не трогать.

- [ ] **Step 6: Проверить синтаксис и вручную открыть страницу**

```bash
docker compose exec -T app php -l bb/tovar_new_mod.php
```

Открыть `http://localhost/bb/tovar_new_mod.php` в браузере (залогинившись под тестовым пользователем уровня 5/7), проверить:
- поле «Фирма» показывает список при фокусе (пустой запрос через `q=`, если `minQuery` по умолчанию 1 — ввести хотя бы 1 символ);
- клик «+ создать производителя» открывает модалку, ввод похожего на существующее имени показывает предупреждение;
- после выбора производителя появляется кнопка «редактировать», открывающая вторую модалку с предпросмотром масштаба;
- сохранение новой модели проставляет `tovar_rent.producer` = выбранному имени (проверить в БД).

- [ ] **Step 7: Commit**

```bash
git add bb/tovar_new_mod.php
git commit -m "feat(bb): tovar_new_mod.php — живой поиск производителя вместо select+input"
```

---

### Task 11: Логотип — запись и чтение через справочник

Переносит хранение логотипа с «копия на каждой странице модели» на «одна строка в `producers.logo`», с фолбэком на старую `rent_model_web.logo` везде, где бренд ещё не нашёлся в справочнике (не должно случаться после Task 3, но проверка бесплатная и по спеке обязательна).

**Files:**
- Modify: `bb/classes/ModelWeb.php`
- Modify: `app/MyClasses/L3Page.php`
- Test: `tests/Unit/ProducerLogoFallbackTest.php` (создать)

**Interfaces:**
- Consumes: `Producer::getByName()`, `->getLogo()`, `->setLogo()`, `->save()` (Task 4).
- Produces: изменённое поведение `ModelWeb::loadLastProducerLogo()`, `ModelWeb::updateLogoUrlForAll()`, `L3Page::getProducerLogoUrl()`; изменённая реализация `Producer::getAllProducersTovExists()` (публичный контракт — без изменений, см. Task 4).

- [ ] **Step 1: Написать падающий тест на фолбэк L3**

```php
<?php

namespace Tests\Unit;

use App\MyClasses\L3Page;
use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\classes\Producer;
use bb\Db;
use Tests\TestCase;

class ProducerLogoFallbackTest extends TestCase
{
    private const SANDBOX_MODEL_ID = 999997;
    private const PRODUCER_NAME = 'Тестовый Производитель ZZZ Лого';

    protected function tearDown(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query('DELETE FROM tovar_rent WHERE tovar_rent_id=' . self::SANDBOX_MODEL_ID);
        $mysqli->query('DELETE FROM rent_model_web WHERE model_id=' . self::SANDBOX_MODEL_ID);
        $mysqli->query("DELETE FROM producers WHERE name LIKE 'Тестовый Производитель ZZZ%'");
        parent::tearDown();
    }

    public function test_l3_falls_back_to_own_logo_when_producer_has_none(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query("
            INSERT INTO tovar_rent SET tovar_rent_id=" . self::SANDBOX_MODEL_ID . ",
            tovar_rent_cat_id=1, producer='" . self::PRODUCER_NAME . "', model='sandbox', cr_time=" . time()
        );

        $producer = new Producer();
        $producer->setName(self::PRODUCER_NAME);
        $producer->setLogo(''); // директория есть, но логотипа в ней нет
        $producer->save();

        $mw = new ModelWeb(self::SANDBOX_MODEL_ID, 'ru');
        $mw->setLogoUrlAddress('/img/own-fallback.webp');

        $p = new L3Page('ru');
        $p->model = Model::getById(self::SANDBOX_MODEL_ID);
        $p->modelWeb = $mw;

        $this->assertSame('/img/own-fallback.webp', $p->getProducerLogoUrl());
    }

    public function test_l3_prefers_directory_logo_over_own(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $mysqli->query("
            INSERT INTO tovar_rent SET tovar_rent_id=" . self::SANDBOX_MODEL_ID . ",
            tovar_rent_cat_id=1, producer='" . self::PRODUCER_NAME . "', model='sandbox', cr_time=" . time()
        );

        $producer = new Producer();
        $producer->setName(self::PRODUCER_NAME);
        $producer->setLogo('/img/from-directory.webp');
        $producer->save();

        $mw = new ModelWeb(self::SANDBOX_MODEL_ID, 'ru');
        $mw->setLogoUrlAddress('/img/own-fallback.webp');

        $p = new L3Page('ru');
        $p->model = Model::getById(self::SANDBOX_MODEL_ID);
        $p->modelWeb = $mw;

        $this->assertSame('/img/from-directory.webp', $p->getProducerLogoUrl());
    }
}
```

- [ ] **Step 2: Прогнать тест, убедиться что падает**

```bash
docker compose exec -T app php artisan test --filter=ProducerLogoFallbackTest
```
Ожидается: FAIL на втором тесте (сейчас `getProducerLogoUrl()` всегда возвращает `modelWeb`-логотип, директорию не смотрит).

- [ ] **Step 3: Обновить `bb/classes/ModelWeb.php`**

Добавить после блока `use bb\Base; use bb\Db;` (перед `class ModelWeb`):
```php
require_once __DIR__ . '/Producer.php';
```

Заменить существующий метод `loadLastProducerLogo()`:
```php
  public function loadLastProducerLogo()
  {
    $mysqli = Db::getInstance()->getConnection();
    $model = Model::getById($this->getModelId());
    if ($model) {
      $model_ids = Model::getModelIdsArrayByProducer($model->getProducer());

      if ($model_ids && count($model_ids) > 0) {
        $query = "SELECT logo FROM rent_model_web WHERE model_id IN (" . implode(',', $model_ids) . ") ORDER BY model_id DESC";
        $result = $mysqli->query($query);
        if (!$result) {
          die('Сбой при доступе к БД MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        while ($row = $result->fetch_assoc()) {
          if (substr($row['logo'], 0, 1) == '/') {
            $this->setLogoUrlAddress($row['logo']);
            return true;
          }
        }
      }
    }

    return false;

  }
```
на:
```php
  /**
   * Предзаполняет поле логотипа при заведении НОВОЙ веб-страницы модели —
   * логотипом бренда из справочника, если он там есть.
   *
   * @return bool
   */
  public function loadLastProducerLogo()
  {
    $model = Model::getById($this->getModelId());
    if (!$model) {
      return false;
    }

    $producer = Producer::getByName($model->getProducer());
    if ($producer && $producer->getLogo() !== '') {
      $this->setLogoUrlAddress($producer->getLogo());
      return true;
    }

    return false;
  }
```

Заменить существующий метод `updateLogoUrlForAll()`:
```php
  public function updateLogoUrlForAll()
  {
    $mysqli = Db::getInstance()->getConnection();
    $model = Model::getById($this->getModelId());
    $modelIds = Model::getModelIdsArrayByProducer($model->getProducer(), 0);
    $query = "UPDATE rent_model_web SET logo='$this->logo' WHERE model_id IN (" . implode(',', $modelIds) . ")";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обновлении url лого в MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return true;
  }
```
на:
```php
  /**
   * Заливка нового логотипа расходится на весь бренд ОДНОЙ строкой в
   * справочнике, а не рассылкой копий по rent_model_web всех моделей бренда
   * (так было раньше — отсюда расхождение копий у трёх брендов, найденное
   * при аудите 2026-08-14). Собственный логотип ЭТОЙ модели (rent_model_web
   * для текущего model_id) сохраняется отдельно, через save() — эта функция
   * трогает только справочник.
   *
   * @return bool
   */
  public function updateLogoUrlForAll()
  {
    $model = Model::getById($this->getModelId());
    if (!$model) {
      return false;
    }

    $producer = Producer::getByName($model->getProducer());
    if (!$producer) {
      // Строка в обход справочника не бывает при обычной работе (он
      // засеян из всех значений producer), но если такое всё же
      // случилось — молча не рассылаем: сама модель уже сохранила свой
      // логотип через save(), теряем только рассылку остальным.
      return false;
    }

    $producer->setLogo($this->logo);

    return $producer->save();
  }
```

- [ ] **Step 4: Обновить `app/MyClasses/L3Page.php`**

Добавить в блок `use` (после `use bb\classes\ModelWeb;`):
```php
use bb\classes\Producer;
```

Заменить:
```php
  public function getProducerLogoUrl()
  {
    return $this->modelWeb->getLogoUrlAddress();
  }
```
на:
```php
  public function getProducerLogoUrl()
  {
    if ($this->model) {
      $producer = Producer::getByName($this->model->getProducer());
      if ($producer && $producer->getLogo() !== '') {
        return $producer->getLogo();
      }
    }

    return $this->modelWeb->getLogoUrlAddress();
  }
```

- [ ] **Step 5: Обновить `Producer::getAllProducersTovExists()` — читать из справочника**

В `bb/classes/Producer.php` добавить перед `private static function queryAll(...)`:
```php
    /**
     * Для главной страницы: бренды с живыми товарами И логотипом — то же
     * условие, что было в старом GROUP BY MAX(logo), теперь через
     * справочник. Брендов без логотипа сознательно НЕ добавляем — это
     * видимое изменение главной, не входящее в объём этой задачи.
     *
     * @return Producer[]|false
     */
    public static function getAllProducersTovExists()
    {
        return \Illuminate\Support\Facades\Cache::remember('all_producers_tov_exists', 1440, function () {
            $mysqli = Db::getInstance()->getConnection();

            $query = "SELECT DISTINCT p.producer_id, p.name, p.logo
                        FROM producers p
                        JOIN tovar_rent tr ON tr.producer = p.name
                        JOIN tovar_rent_items i ON i.model_id = tr.tovar_rent_id
                       WHERE p.logo <> '' AND i.item_id > 0";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }

            if ($result->num_rows < 1) {
                return false;
            }

            $rez = [];
            while ($row = $result->fetch_assoc()) {
                $p = new self();
                $p->name = $row['name'];
                $p->logo = $row['logo'];
                $rez[] = $p;
            }

            return $rez;
        });
    }
```

Публичный интерфейс (`getName()`, `getUrl()`, `getNameUrlEncoded()`) не меняется — `app/MyClasses/MainPage.php:getProducers()` и `resources/views/home.blade.php` остаются нетронутыми.

- [ ] **Step 6: Прогнать тесты**

```bash
docker compose exec -T app php artisan test --filter=ProducerLogoFallbackTest
docker compose exec -T app php artisan test --filter=ProducerTest
docker compose exec -T app php -l bb/classes/ModelWeb.php
docker compose exec -T app php -l app/MyClasses/L3Page.php
docker compose exec -T app php -l bb/classes/Producer.php
```
Ожидается: все PASS.

- [ ] **Step 7: Ручная проверка главной страницы и L3**

```bash
docker compose exec -T app php artisan cache:clear
```
Открыть `http://localhost/ru` — карточки брендов должны выглядеть как раньше (тот же состав, так как условие «живые товары + логотип» сохранено). Открыть карточку товара бренда `Baby Mamy` (модель 1053, «Хипсит») — логотип должен быть логотипом Baby Mamy, а не чужим `logo_pognae.webp` (это ожидаемая, документированная в спеке починка).

- [ ] **Step 8: Commit**

```bash
git add bb/classes/ModelWeb.php app/MyClasses/L3Page.php bb/classes/Producer.php tests/Unit/ProducerLogoFallbackTest.php
git commit -m "feat(bb): логотип бренда — из справочника вместо рассылки копий по моделям"
```

---

### Task 12: Живой поиск производителя на `bb/tovar_new.php` (каскад сохраняется)

Здесь производитель **сужает каскад** до существующей модели, а не назначается из справочника — источник остаётся `SELECT DISTINCT producer FROM tovar_rent WHERE tovar_rent_cat_id=...` (как сейчас в `bb/cat_ch_new.php`, `case 'cat_producer'`), не справочник. Ни создания, ни редактирования на этой странице нет.

Ключевая деталь дизайна: `producer_select_old` меняется из `<select>` в `<input type="hidden">`. Это не косметика — `LivePicker.choose()` делает `this.hidden.value = item.name`, а у `<select>` присвоение `.value` строке, для которой нет `<option>`, молча ничего не делает (в отличие от `<input>`, где `.value` — обычное свойство). Раз опций больше нет (виджет их не строит), `<select>` для хранения значения не годится.

Существующие `cat_ch()`/`prod_ch()`/`model_ch()` (см. `bb/tovar_new.php`) **не изменяются ни строкой** — только читаются по-новому и дополняются ДОБАВОЧНЫМ обработчиком, который не заменяет их, а довешивается рядом.

**Files:**
- Create: `bb/ajax_producer_by_category.php`
- Modify: `bb/tovar_new.php`

**Interfaces:**
- Consumes: `window.LivePicker` (уже существует).

- [ ] **Step 1: Написать `bb/ajax_producer_by_category.php`**

```php
<?php
/**
 * Производители, встречающиеся в конкретной категории — для живого поиска
 * на bb/tovar_new.php. НЕ справочник: производитель здесь сужает каскад до
 * существующей модели, а не назначается — если источником сделать
 * справочник, можно выбрать бренд без единой модели в этой категории и
 * упереться в пустой список моделей. Источник и запрос — как в
 * bb/cat_ch_new.php (case 'cat_producer'), только JSON вместо eval'имой JS.
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');

header('Content-Type: application/json; charset=utf-8');

$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    echo json_encode(['items' => [], 'error' => 'Нет доступа']);
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();

$catId = (int) ($_REQUEST['cat_id'] ?? 0);
$query = trim($_REQUEST['q'] ?? '');

if ($catId < 1) {
    echo json_encode(['items' => []]);
    exit;
}

$result = $mysqli->query("SELECT DISTINCT producer FROM tovar_rent WHERE tovar_rent_cat_id=$catId ORDER BY producer");
if (!$result) {
    echo json_encode(['items' => [], 'error' => 'Сбой при доступе к базе данных']);
    exit;
}

$needle = $query !== '' ? mb_strtolower($query, 'UTF-8') : null;

$items = [];
while ($row = $result->fetch_assoc()) {
    if ($needle !== null && mb_strpos(mb_strtolower($row['producer'], 'UTF-8'), $needle) === false) {
        continue;
    }
    $items[] = ['id' => $row['producer'], 'name' => $row['producer']];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
```

- [ ] **Step 2: Проверить синтаксис**

```bash
docker compose exec -T app php -l bb/ajax_producer_by_category.php
```

- [ ] **Step 3: Подключить JS в `bb/tovar_new.php`**

В `<head>` (или рядом с существующими стилями/скриптами страницы) добавить:
```php
<link href="/bb/assets/styles/category_picker.css?v=2" rel="stylesheet" type="text/css" />
```
(если уже подключён — не дублировать) и перед `</body>`:
```php
<script src="/bb/assets/js/live_picker.js?v=2"></script>
```
(если ещё не подключён на этой странице).

- [ ] **Step 4: Заменить разметку поля «Фирма:»**

Найти (строки ~745–752):
```php
	<tr>
		<td>Фирма:</td>
		<td>
			<select name="producer_select_old" id="producer_select_old" onchange="prod_ch();">
    			<option value="0">----------</option>
				' . $producers_list . '
    		</select>

	  		<textarea id="produceer_sel_temp" readonly="readonly" style="display:none"></textarea> <!--- это чтобы кавычки двойные правильно сравнивались -->
		</td>
	</tr>
```

Заменить на:
```php
	<tr>
		<td>Фирма:</td>
		<td>
			<div class="catp">
				<input type="text" id="prod_old_search" class="catp__input" autocomplete="off"
					placeholder="сначала выберите категорию"
					value="' . good_print($model_def['producer']) . '" />
				<div id="prod_old_results" class="catp__results"></div>
			</div>
			<input type="hidden" name="producer_select_old" id="producer_select_old" value="' . good_print($model_def['producer']) . '" />

	  		<textarea id="produceer_sel_temp" readonly="readonly" style="display:none"></textarea> <!--- это чтобы кавычки двойные правильно сравнивались -->
		</td>
	</tr>
```

(`$model_def['producer']` уже заполняется в ветке `редактировать товар` — см. `bb/tovar_new.php` вокруг строки 264 `sel_d($model_def['producer'], ...)`; при создании нового товара `$model_def['producer']` не установлен — добавить его в начальную инициализацию `$model_def` рядом с остальными `$model_def['...'] = '';`, если такой строки там ещё нет: `$model_def['producer'] = '';`.)

- [ ] **Step 5: Инициализировать `LivePicker` и добавочный сброс каскада**

Перед закрывающим `</script>` в конце файла (там же, где сейчас определены `cat_ch()`/`prod_ch()`/`model_ch()`) добавить:
```js
(function () {
	if (!window.LivePicker) {
		return;
	}

	var producerPicker = new window.LivePicker({
		inputId:   'prod_old_search',
		hiddenId:  'producer_select_old',
		resultsId: 'prod_old_results',
		url:       '/bb/ajax_producer_by_category.php',
		valueKey:  'name',
		minQuery:  0,
		extraParams: function () {
			var cat = document.getElementById('cat_select_old');
			return { cat_id: cat ? cat.value : 0 };
		},
		onChoose: function () {
			// Сохраняет каскад: prod_ch() читает producer_select_old.value —
			// LivePicker.choose() уже проставил его ДО этого колбэка.
			prod_ch();
		}
	});

	// cat_ch() (см. выше) сам НЕ трогается — этот обработчик довешивается
	// рядом и сбрасывает бренд/модель при смене категории, как раньше делал
	// select-каскад (cat_ch() пытается очистить producer_select_old через
	// innerHTML, но у input это не имеет эффекта — сброс значения нужен
	// явно здесь).
	var catSelect = document.getElementById('cat_select_old');
	if (catSelect) {
		catSelect.addEventListener('change', function () {
			producerPicker.reset();
			document.getElementById('model_select_old').innerHTML = '<option value="0">----------</option>';
		});
	}
})();
```

- [ ] **Step 6: Проверить синтаксис и вручную открыть страницу**

```bash
docker compose exec -T app php -l bb/tovar_new.php
```

Открыть `http://localhost/bb/tovar_new.php`:
- выбрать категорию → поле «Фирма» на фокусе показывает производителей именно этой категории (не весь список);
- сменить категорию → поле «Фирма» и список моделей сбрасываются;
- выбрать производителя → список моделей заполняется, как раньше (проверить, что `prod_ch()` реально сработал — Network-таб покажет запрос к `cat_ch_new.php` с `par2=producer`);
- открыть существующий товар на редактирование → поле «Фирма» предзаполнено текущим производителем.

- [ ] **Step 7: Commit**

```bash
git add bb/ajax_producer_by_category.php bb/tovar_new.php
git commit -m "feat(bb): tovar_new.php — живой поиск производителя, сужающий каскад по категории"
```

---

### Task 13: Полная проверка и обновление плана хардненинга

**Files:**
- Modify: `docs/superpowers/plans/2026-07-27-tovar-new-mod-hardening.md` (отметить Фазу 7 выполненной)

- [ ] **Step 1: Прогнать весь набор новых и не только тестов**

```bash
docker compose exec -T app php artisan test --filter=Similarity
docker compose exec -T app php artisan test --filter=Producer
docker compose exec -T app php artisan test
```
Ожидается: весь набор PASS (в т.ч. `tests/Feature/TariffWriteGuardTest.php` — не задет этой работой, но должен остаться зелёным).

- [ ] **Step 2: `php -l` по всем новым/изменённым файлам разом**

```bash
docker compose exec -T app bash -c '
for f in bb/classes/Similarity.php bb/classes/Producer.php bb/classes/ModelWeb.php \
         app/MyClasses/L3Page.php bb/tovar_new_mod.php bb/tovar_new.php \
         bb/ajax_producer_suggest.php bb/ajax_producer_create.php \
         bb/ajax_producer_update.php bb/ajax_producer_by_category.php; do
  php -l "$f" || exit 1
done
'
node --check bb/assets/js/producer_picker.js
```

- [ ] **Step 3: Проверить чистоту слияния с `origin/main`**

```bash
git fetch origin
git merge-tree --write-tree --messages HEAD origin/main
echo "exit code: $?"
```
Ожидается: код 0 (нет конфликтов).

- [ ] **Step 4: Отметить план хардненинга**

В `docs/superpowers/plans/2026-07-27-tovar-new-mod-hardening.md` найти Фазу 7 (производитель/логотип) и отметить выполненной, аналогично отметкам предыдущих фаз (Task 10/Task 14 в этом же файле — см. существующий стиль пометок «ВЫПОЛНЕНО» со ссылкой на ветку).

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/plans/2026-07-27-tovar-new-mod-hardening.md
git commit -m "docs: план хардненинга — Фаза 7 (справочник производителей) выполнена"
```

---

## Self-Review

**Покрытие спеки:** таблица `producers` (Task 2) · детектор дублей, две метрики (Task 1) · засев без автослияния + гашение (Task 3) · создание в попапе, уровни `[0,5,7]` (Task 7, 10) · редактирование с предпросмотром/транзакцией/запретом слияния, уровни `[5,7]` (Task 5, 8, 10) · живой поиск на `tovar_new_mod.php`, приоритет брендов категории (Task 6, 10) · живой поиск на `tovar_new.php`, источник — существующие значения, не справочник, каскад сохранён (Task 12) · логотип: ввод там же, хранение в справочнике, фолбэк на `rent_model_web.logo`, чтение на L3 и главной (Task 11) · скрытые находятся по точному имени (Task 4, 6) · компромиссы спеки (без `producer_id`, без таблицы псевдонимов, без страницы управления, без `url_key`) — ни один файл плана их не нарушает. Не в объёме по спеке (SEO-лендинг, разбор карнавальной свалки по существу, `model_clean.php`, замена `<select>` моделей) — в плане не затронуты, как и требуется.

**Заглушки:** проверено — во всех шагах либо полный код, либо точная команда с ожидаемым результатом; нигде нет «добавить обработку ошибок» без кода.

**Типы/сигнатуры:** `valueKey: 'name'` у producer-пикеров (не `'id'`, как у категории) — согласовано между `producer_picker.js` (Task 9), разметкой (Task 10) и PHP-чтением `$producer_select_new` как строки (Task 10, Step 4). `Producer::getByName()` используется одинаково в `ModelWeb` (Task 11) и в AJAX-эндпоинтах (Task 6, 8) — точное совпадение сырой строки, без нормализации. `Similarity::findSimilarByEdit()`/`combinedScore()`/`editSimilarity()` (Task 1) используются только в `Producer::findDuplicates()` (Task 4) — существующие `findSimilar()`/`score()` категорий нигде не тронуты.
