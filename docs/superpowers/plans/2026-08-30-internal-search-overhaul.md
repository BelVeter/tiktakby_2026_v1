# Внутренний поиск tiktak.by — план доработки

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Довести внутренний поиск с 20% пустых выдач и OR-семантикой до состояния, когда человек находит товар, который у нас реально есть, — включая случаи, когда он называет его словом из названия категории, а не из названия модели.

**Architecture:** Поиск переезжает из `bb\classes\ModelWeb::getModelIdsFullTextSearch()` (mysqli, один natural-language `MATCH`) в новый Laravel-класс `App\MyClasses\Search\ProductSearch` (Query Builder). Поиск становится трёхуровневым: (1) FULLTEXT BOOLEAN со стеммингом и `+` на каждом слове, (2) тот же FULLTEXT в режиме OR как фолбэк, (3) совпадение по названию категории `tovar_rent_cat.rent_cat_name`. Результаты склеиваются с сохранением порядка тиров. Запрос предварительно расширяется словарём синонимов `search_synonyms`, редактируемым владельцем.

**Tech Stack:** Laravel 8.75, PHP 7.4, MySQL 5.7.44 (прод, MyISAM для `rent_model_web`) / MariaDB 10.6 (локально, тот же движок таблицы), PHPUnit 9, Blade, Laravel Mix.

## Global Constraints

- **PHP 7.4** — никаких `match`, именованных аргументов, конструктор-промоушна, `?->`. `str_contains`/`str_starts_with` допустимы (полифилл Symfony уже в зависимостях).
- **MySQL 5.7.44 на проде, MariaDB 10.6 локально** — разные движки. Любую SQL-функцию проверять по документации MySQL 5.7, а не по факту «локально работает». `REGEXP_REPLACE` запрещён (MySQL 8.0+). См. `CLAUDE.md`, раздел Common Pitfalls.
- **`ft_min_word_len = 4`** и на проде, и локально (проверено). Токены короче 4 символов FULLTEXT не индексирует **даже с `*`** — обрабатывать отдельной веткой, не надеяться на wildcard.
- **`rent_model_web` — MyISAM, `utf8_general_ci`**; `search_log` — InnoDB, `utf8mb4_unicode_ci`. При сравнении строк из разных таблиц обязателен `CONVERT(... USING utf8) COLLATE utf8_general_ci`, иначе `Illegal mix of collations`.
- **Правило CLAUDE.md #4** — не смешивать Eloquent/Query Builder и mysqli в одном файле. Новый код только `Illuminate\Support\Facades\DB`; `bb/classes/ModelWeb.php` не трогаем, кроме пометки `@deprecated`.
- **Правило CLAUDE.md #1** — никаких замыканий в `routes/web.php`.
- **Прод не трогаем.** Работа локально, на фича-ветке от свежего `origin/main`, деплой — отдельный шаг владельца после ручной проверки.
- **Ветку не переиспользовать после merge** — PR сквошатся (`CLAUDE.md`, Git & Deployment).
- Тесты гоняются в контейнере: `docker compose exec -T app php artisan test --filter=<Имя>`.

## Исходные данные (замерено на проде 2026-08-30)

За 18 дней, без 121 строки скриптового прогона с IP `139.28.41.138`:

| Метрика | Значение |
|---|---|
| Запросов | 403 (~22/день) |
| С мобильных | **79,2 %** |
| Ноль результатов | **20,3 %** |
| Многословных | 27 % |
| Выдача > 24 товаров (стр. 2+) | 31 % успешных |

Контрольные замеры «до» (прод, `MATCH ... AGAINST('...')` без BOOLEAN):

| Запрос | Сейчас | Должно стать | Чем чинится |
|---|---|---|---|
| `коляска` | 38 | 41 | Task 3 |
| `коляски` | **0** | 41 | Task 3 (стемминг) |
| `кроватки` | **0** | 10 | Task 3 |
| `цыганский костюм` | 306 (мусор) | 0 + честное сообщение | Task 3 (AND) + Task 8 |
| `эргорюкзак` | 1 | **19** | Task 5 (категории) |
| `слинг` | 1 | **19** | Task 5 |
| `видеоняня` | **0** | **4** | Task 5 |
| `шезлонг` | 5 | 8 | Task 5 |
| `кокон` | 2 | 10 | Task 5 |
| `толокар` | **0** | 3 | Task 6 (синонимы) |
| `кенгуру` | 1 | 19 | Task 6 |
| `мяч` | **0** | 6 | Task 7 (короткие слова) |
| `весы` | 10 | 10 (не сломать!) | Task 3 |
| `стульчик` | 9 | ≥9 (не сломать!) | Task 3 |

## Корневые причины (подтверждены на проде)

1. **Natural language mode = OR.** `цыганский костюм` → 306 моделей, при этом `цыганский` отдельно → 0. Все 306 пришли от слова «костюм».
2. **Нет морфологии.** `коляска` → 38, `коляски` → 0.
3. **Индексируются 3 поля из 5** — `keywords` (заполнено у 797/848) и `main_descr` (815/848) не участвуют.
4. **Название категории не участвует вообще.** Владелец руками ведёт таксономию «Эргорюкзаки, слинги, туристические рюкзаки» (16 моделей), а поиск её не видит: модели названы «Эргономичный рюкзак ...», «Нагрудная сумка ...», «Хипсит ...». Это же ломает «видеоняню»: категория «Радио- видеоняни» существует, 4 модели, но все названы «Радионяня ...».
5. **Составные слова.** `люлька` → 0, потому что в базе «авто**люлька**» — один токен.
6. **`ft_min_word_len = 4`** — `мяч`, `лук`, `ева` выбрасываются молча.
7. **Правило 50 %** natural-language режима — слово из более чем половины моделей игнорируется. В BOOLEAN MODE правила нет.

## File Structure

**Создаём:**
- `app/MyClasses/Search/QueryNormalizer.php` — чистая логика без БД: разбор строки запроса на токены, стемминг, классификация коротких токенов. Единственное место, где живут правила русских окончаний.
- `app/MyClasses/Search/SynonymDictionary.php` — чтение и применение `search_synonyms`, кэш на 1 час.
- `app/MyClasses/Search/ProductSearch.php` — оркестратор трёх проходов, склейка и ранжирование. Единственное место, которое знает про тиры.
- `app/MyClasses/Search/SearchResult.php` — value object: `modelIds`, `tier`, `total`.
- `database/migrations/2026_08_31_100000_create_search_synonyms_table.php`
- `database/migrations/2026_08_31_100100_add_keywords_to_model_web_fulltext.php`
- `database/seeders/SearchSynonymsSeeder.php` — стартовый словарь из лога.
- `tests/Unit/Search/QueryNormalizerTest.php`
- `tests/Feature/Search/ProductSearchTest.php`
- `tests/Feature/Search/SearchPageTest.php`

**Меняем:**
- `app/Http/Controllers/SearchController.php` — переключить на `ProductSearch`, прокинуть `tier` и `total` во вью.
- `resources/views/search.blade.php` — счётчик, форма поиска, экран пустой выдачи.
- `resources/views/includes/header.blade.php:267,349-352` — мобильные фиксы.
- `bb/classes/ModelWeb.php:1240` — пометить `@deprecated`.

**Почему так:** `QueryNormalizer` не ходит в БД, поэтому тестируется юнит-тестами мгновенно и покрывает самую хитрую часть (окончания). `ProductSearch` ходит в БД и тестируется на реальных 844 локальных моделях — это единственный способ поймать регресс вида «починили коляски, сломали весы».

---

### Task 1: Ветка и базовый замер поведения «до»

**Files:**
- Create: `tests/Feature/Search/ProductSearchTest.php`

**Interfaces:**
- Consumes: ничего.
- Produces: `Tests\Feature\Search\ProductSearchTest` — база для всех дальнейших тестов поиска. Использует `Illuminate\Foundation\Testing\DatabaseTransactions`.

- [ ] **Step 1: Свежая ветка от origin/main**

```bash
cd /home/dmitry/sites/tiktakby
git fetch origin && git checkout -b feature/search-overhaul origin/main
```

- [ ] **Step 2: Убедиться, что локальная БД поднята и содержит каталог**

```bash
docker compose up -d
docker compose exec -T db mysql -u tiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT COUNT(*) FROM rent_model_web; SELECT COUNT(*) FROM tovar_rent_cat;"
```
Ожидается: ~844 модели, ~120 категорий. Если 0 — импортировать дамп, иначе все дальнейшие тесты бессмысленны.

- [ ] **Step 3: Написать характеризующий тест текущего поведения**

Создать `tests/Feature/Search/ProductSearchTest.php`:

```php
<?php

namespace Tests\Feature\Search;

use bb\classes\ModelWeb;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Замер поведения "до" переделки поиска. Числа взяты с локального каталога
 * (844 модели) и совпадают с продом с точностью до 3-4 моделей.
 *
 * Эти тесты фиксируют СЛОМАННОЕ поведение намеренно — они будут удалены
 * в Task 3, когда появится ProductSearch. Смысл: доказать, что мы правда
 * воспроизводим баг, прежде чем его чинить.
 */
class ProductSearchTest extends TestCase
{
    use DatabaseTransactions;

    /** @dataProvider legacyBrokenQueries */
    public function test_legacy_search_is_broken_for_plural_forms(string $query, int $expected): void
    {
        $ids = ModelWeb::getModelIdsFullTextSearch($query);

        $this->assertCount(
            $expected,
            $ids,
            "запрос «{$query}» вернул " . count($ids) . " моделей вместо {$expected}"
        );
    }

    public function legacyBrokenQueries(): array
    {
        return [
            'единственное число работает' => ['коляска', 38],
            'множественное число — ноль'  => ['коляски', 0],
            'кроватки — ноль'             => ['кроватки', 0],
            'эргорюкзак — почти ноль'     => ['эргорюкзак', 1],
            'видеоняня — ноль'            => ['видеоняня', 0],
        ];
    }
}
```

- [ ] **Step 4: Прогнать и убедиться, что баг воспроизводится**

```bash
docker compose exec -T app php artisan test --filter=ProductSearchTest
```
Ожидается: PASS. Если какой-то кейс упал — значит локальный каталог отличается от прода; **зафиксировать фактические числа в тесте** и продолжить (важна дельта до/после, а не абсолют).

- [ ] **Step 5: Коммит**

```bash
git add tests/Feature/Search/ProductSearchTest.php
git commit -m "test(search): зафиксировать сломанное поведение поиска до переделки"
```

---

### Task 2: QueryNormalizer — разбор запроса и стемминг

**Files:**
- Create: `app/MyClasses/Search/QueryNormalizer.php`
- Test: `tests/Unit/Search/QueryNormalizerTest.php`

**Interfaces:**
- Consumes: ничего.
- Produces:
  - `QueryNormalizer::tokenize(string $raw): array` — вернёт `['stems' => string[], 'short' => string[], 'raw' => string[]]`. `stems` — основы длиной ≥4 для FULLTEXT, `short` — токены короче 4 символов (для LIKE-ветки), `raw` — исходные токены в нижнем регистре.
  - `QueryNormalizer::stem(string $word): string` — основа слова без `*`.

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Unit/Search/QueryNormalizerTest.php`:

```php
<?php

namespace Tests\Unit\Search;

use App\MyClasses\Search\QueryNormalizer;
use PHPUnit\Framework\TestCase;

class QueryNormalizerTest extends TestCase
{
    /** @dataProvider stems */
    public function test_stem_strips_russian_endings(string $word, string $expected): void
    {
        $this->assertSame($expected, QueryNormalizer::stem($word));
    }

    public function stems(): array
    {
        return [
            'мн.число'                 => ['коляски', 'коляск'],
            'тв.падеж мн.'             => ['колясками', 'коляск'],
            'кроватки'                 => ['кроватки', 'кроватк'],
            'эргорюкзаки'              => ['эргорюкзаки', 'эргорюкзак'],
            'прилагательное ж.р.'      => ['прогулочная', 'прогулочн'],
            'прилагательное м.р.'      => ['цыганский', 'цыганск'],
            'видеоняня'                => ['видеоняня', 'видеонян'],
            'короткая основа не режется' => ['весы', 'весы'],
            'нет окончания — как есть' => ['матрас', 'матрас'],
            'шезлонг'                  => ['шезлонг', 'шезлонг'],
            'латиница не режется'      => ['doona', 'doona'],
            'латиница на -a'           => ['anexa', 'anexa'],
        ];
    }

    public function test_tokenize_splits_short_and_long_tokens(): void
    {
        $result = QueryNormalizer::tokenize('Коляска  для МЯЧ');

        $this->assertSame(['коляск', 'для'], $result['stems']);
        $this->assertSame(['мяч'], $result['short']);
    }

    public function test_tokenize_drops_boolean_operators(): void
    {
        $result = QueryNormalizer::tokenize('+коляска* -автокресло ~(тест)');

        $this->assertSame(['коляск', 'автокресл', 'тест'], $result['stems']);
    }

    public function test_tokenize_caps_token_count(): void
    {
        $result = QueryNormalizer::tokenize(str_repeat('коляска ', 40));

        $this->assertLessThanOrEqual(10, count($result['stems']));
    }

    public function test_tokenize_of_empty_string_is_empty(): void
    {
        $result = QueryNormalizer::tokenize('   ');

        $this->assertSame([], $result['stems']);
        $this->assertSame([], $result['short']);
    }
}
```

- [ ] **Step 2: Прогнать — должно упасть**

```bash
docker compose exec -T app php artisan test --filter=QueryNormalizerTest
```
Ожидается: FAIL, `Class "App\MyClasses\Search\QueryNormalizer" not found`.

- [ ] **Step 3: Реализовать QueryNormalizer**

Создать `app/MyClasses/Search/QueryNormalizer.php`:

```php
<?php

namespace App\MyClasses\Search;

/**
 * Разбор пользовательского запроса в токены, пригодные для MySQL FULLTEXT.
 *
 * Полноценной морфологии (Porter/mystem) здесь нет намеренно: каталог — 848
 * моделей бытовых названий, и отсечения окончания с wildcard-суффиксом хватает,
 * чтобы «коляски» находили «коляска». Держим правила в одном месте, чтобы их
 * можно было расширять по логу search_log.
 */
class QueryNormalizer
{
    /**
     * ft_min_word_len на проде и локально = 4. Токены короче FULLTEXT не
     * индексирует ДАЖЕ с '*', поэтому они уходят в отдельную LIKE-ветку.
     */
    public const MIN_FT_LEN = 4;

    /** Защита от запроса-простыни: больше 10 слов в поиске по прокату не бывает. */
    private const MAX_TOKENS = 10;

    /**
     * Русские окончания, от длинных к коротким. Только кириллица — латинские
     * бренды (doona, anexa) резать нельзя, там 'a' это часть имени.
     */
    private const ENDINGS = [
        'иями', 'ами', 'ями', 'ого', 'его', 'ому', 'ему', 'ыми', 'ими',
        'ая', 'яя', 'ое', 'ее', 'ые', 'ие', 'ой', 'ей', 'ый', 'ий',
        'ом', 'ем', 'ах', 'ях', 'ам', 'ям', 'ов', 'ев', 'ью', 'ия', 'ья',
        'а', 'я', 'ы', 'и', 'у', 'ю', 'е', 'о', 'ь',
    ];

    /**
     * @return array{stems: string[], short: string[], raw: string[]}
     */
    public static function tokenize(string $raw): array
    {
        $raw = mb_strtolower(trim($raw), 'UTF-8');

        // Всё, что не буква/цифра — разделитель. Заодно снимает булевы
        // операторы FULLTEXT (+ - > < ( ) ~ * " @), которые иначе ломают синтаксис.
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return ['stems' => [], 'short' => [], 'raw' => []];
        }

        $parts = array_slice(array_values(array_unique($parts)), 0, self::MAX_TOKENS);

        $stems = [];
        $short = [];
        foreach ($parts as $token) {
            if (mb_strlen($token, 'UTF-8') < self::MIN_FT_LEN) {
                $short[] = $token;
                continue;
            }
            $stems[] = self::stem($token);
        }

        return ['stems' => $stems, 'short' => $short, 'raw' => $parts];
    }

    /**
     * Отсекает самое длинное известное окончание при условии, что основа
     * остаётся не короче MIN_FT_LEN — иначе FULLTEXT её не увидит
     * («весы» → «вес» было бы поиском в пустоту).
     */
    public static function stem(string $word): string
    {
        $len = mb_strlen($word, 'UTF-8');
        if ($len <= self::MIN_FT_LEN) {
            return $word;
        }

        foreach (self::ENDINGS as $ending) {
            $endLen = mb_strlen($ending, 'UTF-8');
            if ($len - $endLen < self::MIN_FT_LEN) {
                continue;
            }
            if (mb_substr($word, -$endLen, null, 'UTF-8') === $ending) {
                return mb_substr($word, 0, $len - $endLen, 'UTF-8');
            }
        }

        return $word;
    }
}
```

- [ ] **Step 4: Прогнать — должно пройти**

```bash
docker compose exec -T app php artisan test --filter=QueryNormalizerTest
```
Ожидается: OK (17 tests).

- [ ] **Step 5: Коммит**

```bash
git add app/MyClasses/Search/QueryNormalizer.php tests/Unit/Search/QueryNormalizerTest.php
git commit -m "feat(search): нормализация запроса со стеммингом русских окончаний"
```

---

### Task 3: ProductSearch — BOOLEAN MODE с AND и фолбэком на OR

**Files:**
- Create: `app/MyClasses/Search/SearchResult.php`, `app/MyClasses/Search/ProductSearch.php`
- Modify: `tests/Feature/Search/ProductSearchTest.php` (заменить характеризующие тесты на целевые)
- Modify: `app/Http/Controllers/SearchController.php:52`
- Modify: `bb/classes/ModelWeb.php:1240` (пометка `@deprecated`)

**Interfaces:**
- Consumes: `QueryNormalizer::tokenize()`.
- Produces:
  - `SearchResult` — публичные readonly-подобные свойства через геттеры: `getModelIds(): int[]`, `getTier(): string` (`'exact'|'partial'|'category'|'none'`), `getTotal(): int`.
  - `ProductSearch::find(string $query): SearchResult`.

- [ ] **Step 1: Переписать тест под целевое поведение**

Полностью заменить содержимое `tests/Feature/Search/ProductSearchTest.php`:

```php
<?php

namespace Tests\Feature\Search;

use App\MyClasses\Search\ProductSearch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function count(string $query): int
    {
        return (new ProductSearch())->find($query)->getTotal();
    }

    /**
     * Главный регресс-щит: множественное число обязано находить то же,
     * что и единственное.
     */
    public function test_plural_finds_the_same_as_singular(): void
    {
        $this->assertSame($this->count('коляска'), $this->count('коляски'));
        $this->assertSame($this->count('кроватка'), $this->count('кроватки'));
    }

    public function test_singular_still_finds_a_lot(): void
    {
        $this->assertGreaterThanOrEqual(38, $this->count('коляска'));
    }

    /**
     * Не сломать то, что уже работало: до переделки «весы» давали 10,
     * «стульчик» — 9. Стемминг не должен их обрезать в пустоту.
     */
    public function test_previously_working_queries_do_not_regress(): void
    {
        $this->assertGreaterThanOrEqual(10, $this->count('весы'));
        $this->assertGreaterThanOrEqual(9, $this->count('стульчик'));
    }

    /**
     * Ключевая смена семантики: natural language mode отдавал 306 моделей
     * на «цыганский костюм», потому что OR-ил слова и всё пришло от «костюм».
     * Теперь оба слова обязательны — точных совпадений нет.
     */
    public function test_multiword_query_requires_all_words(): void
    {
        $result = (new ProductSearch())->find('цыганский костюм');

        $this->assertNotSame('exact', $result->getTier());
        $this->assertLessThan(306, $result->getTotal());
    }

    public function test_multiword_query_narrows_the_result(): void
    {
        $broad = $this->count('коляска');
        $narrow = (new ProductSearch())->find('коляска babyzen');

        $this->assertSame('exact', $narrow->getTier());
        $this->assertLessThan($broad, $narrow->getTotal());
    }

    /**
     * Если по всем словам ничего — не отдаём пустоту, а честно показываем
     * выдачу по части запроса и помечаем её тиром 'partial'.
     */
    public function test_falls_back_to_partial_match(): void
    {
        $result = (new ProductSearch())->find('цыганский костюм');

        $this->assertSame('partial', $result->getTier());
        $this->assertGreaterThan(0, $result->getTotal());
    }

    public function test_empty_query_returns_nothing_without_crashing(): void
    {
        $result = (new ProductSearch())->find('   ');

        $this->assertSame('none', $result->getTier());
        $this->assertSame(0, $result->getTotal());
    }

    public function test_sql_injection_attempt_does_not_crash(): void
    {
        $result = (new ProductSearch())->find("коляска' OR '1'='1");

        $this->assertGreaterThan(0, $result->getTotal());
    }

    public function test_boolean_operators_in_input_do_not_crash(): void
    {
        $result = (new ProductSearch())->find('+коляска* -автокресло ~(((');

        $this->assertGreaterThan(0, $result->getTotal());
    }

    public function test_only_models_with_physical_items_are_returned(): void
    {
        $ids = (new ProductSearch())->find('коляска')->getModelIds();

        $withoutItems = \Illuminate\Support\Facades\DB::table('rent_model_web as w')
            ->leftJoin('tovar_rent_items as t', 't.model_id', '=', 'w.model_id')
            ->whereIn('w.model_id', $ids)
            ->whereNull('t.item_id')
            ->count();

        $this->assertSame(0, $withoutItems);
    }
}
```

- [ ] **Step 2: Прогнать — должно упасть**

```bash
docker compose exec -T app php artisan test --filter=ProductSearchTest
```
Ожидается: FAIL, `Class "App\MyClasses\Search\ProductSearch" not found`.

- [ ] **Step 3: Создать SearchResult**

Создать `app/MyClasses/Search/SearchResult.php`:

```php
<?php

namespace App\MyClasses\Search;

/**
 * Результат поиска вместе с тем, КАК он был получен. Тир нужен вью, чтобы
 * честно сказать «точных совпадений нет, показываем похожее» вместо того,
 * чтобы молча выдавать что попало.
 */
class SearchResult
{
    public const TIER_EXACT    = 'exact';    // все слова запроса найдены в товаре
    public const TIER_PARTIAL  = 'partial';  // найдена часть слов
    public const TIER_CATEGORY = 'category'; // совпало название категории
    public const TIER_NONE     = 'none';     // ничего

    /** @var int[] */
    private $modelIds;

    /** @var string */
    private $tier;

    /** @param int[] $modelIds */
    public function __construct(array $modelIds, string $tier)
    {
        $this->modelIds = array_values($modelIds);
        $this->tier = $modelIds === [] ? self::TIER_NONE : $tier;
    }

    public static function empty(): self
    {
        return new self([], self::TIER_NONE);
    }

    /** @return int[] */
    public function getModelIds(): array
    {
        return $this->modelIds;
    }

    public function getTier(): string
    {
        return $this->tier;
    }

    public function getTotal(): int
    {
        return count($this->modelIds);
    }
}
```

- [ ] **Step 4: Создать ProductSearch (пока два прохода: AND, затем OR)**

Создать `app/MyClasses/Search/ProductSearch.php`:

```php
<?php

namespace App\MyClasses\Search;

use Illuminate\Support\Facades\DB;

/**
 * Поиск по каталогу. Заменяет bb\classes\ModelWeb::getModelIdsFullTextSearch().
 *
 * Почему BOOLEAN MODE, а не natural language:
 *  - natural language OR-ит слова, поэтому «цыганский костюм» отдавал 306
 *    моделей — все костюмы каталога, притом что «цыганский» не нашёлся вовсе;
 *  - в natural language работает правило 50%: слово, встречающееся более чем
 *    в половине строк, молча выбрасывается. В BOOLEAN MODE такого правила нет.
 *
 * Ограничение, которое НЕ обходится wildcard-ом: ft_min_word_len = 4.
 * Токены короче четырёх символов FULLTEXT не индексирует даже как «мяч*» —
 * для них отдельная ветка (Task 7).
 */
class ProductSearch
{
    /** Поля, по которым построен FULLTEXT-индекс rent_model_web. */
    private const MATCH_FIELDS = 'w.title, w.l2_name, w.item_name_main';

    public function find(string $query): SearchResult
    {
        $tokens = QueryNormalizer::tokenize($query);
        if ($tokens['stems'] === []) {
            return SearchResult::empty();
        }

        $exact = $this->fulltext($tokens['stems'], true);
        if ($exact !== []) {
            return new SearchResult($exact, SearchResult::TIER_EXACT);
        }

        // Одно слово уже искалось как обязательное — OR-проход дал бы то же самое.
        if (count($tokens['stems']) > 1) {
            $partial = $this->fulltext($tokens['stems'], false);
            if ($partial !== []) {
                return new SearchResult($partial, SearchResult::TIER_PARTIAL);
            }
        }

        return SearchResult::empty();
    }

    /**
     * @param string[] $stems
     * @param bool $requireAll true → «+слово*» на каждом токене (AND), false → OR
     * @return int[]
     */
    private function fulltext(array $stems, bool $requireAll): array
    {
        $prefix = $requireAll ? '+' : '';
        $expression = implode(' ', array_map(static function ($stem) use ($prefix) {
            return $prefix . $stem . '*';
        }, $stems));

        $rows = DB::select(
            'SELECT w.model_id,
                    MATCH(' . self::MATCH_FIELDS . ') AGAINST(? IN BOOLEAN MODE) AS relevance
             FROM rent_model_web w
             INNER JOIN tovar_rent_items t ON t.model_id = w.model_id
             WHERE MATCH(' . self::MATCH_FIELDS . ') AGAINST(? IN BOOLEAN MODE)
               AND w.status = ?
             GROUP BY w.model_id
             ORDER BY relevance DESC, w.model_id DESC',
            [$expression, $expression, 'show']
        );

        return array_map(static function ($row) {
            return (int) $row->model_id;
        }, $rows);
    }
}
```

- [ ] **Step 5: Прогнать тесты**

```bash
docker compose exec -T app php artisan test --filter=ProductSearchTest
```
Ожидается: PASS по всем, кроме `test_multiword_query_narrows_the_result`, если в каталоге нет коляски Babyzen — тогда заменить `'коляска babyzen'` на пару слов, которые точно есть вместе (проверить: `SELECT l2_name FROM rent_model_web WHERE l2_name LIKE '%коляск%' LIMIT 20`).

- [ ] **Step 6: Переключить контроллер на ProductSearch**

В `app/Http/Controllers/SearchController.php` заменить строку 52:

```php
        $modelIdArray = ModelWeb::getModelIdsFullTextSearch($text);
```

на:

```php
        $searchResult = (new \App\MyClasses\Search\ProductSearch())->find($text);
        $modelIdArray = $searchResult->getModelIds();
```

и добавить `'searchTier' => $searchResult->getTier(),` и `'totalFound' => $searchResult->getTotal(),` в массив, передаваемый в `view('search', [...])` (строка 57).

- [ ] **Step 7: Пометить легаси-метод как устаревший**

В `bb/classes/ModelWeb.php` перед строкой 1240 добавить:

```php
  /**
   * @deprecated Заменён на App\MyClasses\Search\ProductSearch (BOOLEAN MODE,
   *   стемминг, поиск по названию категории). Natural language mode OR-ил слова
   *   и подчинялся правилу 50%. Метод оставлен, пока не проверено, что на него
   *   не ссылается ничего в bb/. Удалить после одного цикла на проде.
   */
```

- [ ] **Step 8: Прогнать весь поисковый набор, включая старые тесты**

```bash
docker compose exec -T app php artisan test --filter=Search
```
Ожидается: PASS, в том числе `SearchLogTest` и `SearchSqlInjectionTest` (они дёргают HTTP-роут и не должны сломаться).

- [ ] **Step 9: Коммит**

```bash
git add app/MyClasses/Search/ app/Http/Controllers/SearchController.php \
        bb/classes/ModelWeb.php tests/Feature/Search/ProductSearchTest.php
git commit -m "feat(search): BOOLEAN MODE со стеммингом вместо natural language OR"
```

---

### Task 4: Добавить keywords в FULLTEXT-индекс

**Files:**
- Create: `database/migrations/2026_08_31_100100_add_keywords_to_model_web_fulltext.php`
- Modify: `app/MyClasses/Search/ProductSearch.php:20` (константа `MATCH_FIELDS`)
- Modify: `tests/Feature/Search/ProductSearchTest.php`

**Interfaces:**
- Consumes: `ProductSearch::MATCH_FIELDS`.
- Produces: FULLTEXT-индекс `ft_search (title, l2_name, item_name_main, keywords)` на `rent_model_web`.

**Зачем:** `keywords` заполнен у 797 из 848 моделей и в поиске не участвует. Заодно это поле становится «ручкой» владельца: вписал в keywords «толокар» — товар начал находиться. `main_descr` в индекс НЕ добавляем: описания длинные, они размоют релевантность и потянут в выдачу случайные совпадения.

- [ ] **Step 1: Написать падающий тест**

Добавить в `tests/Feature/Search/ProductSearchTest.php`:

```php
    /**
     * keywords — единственное поле, которым владелец может вручную «дотянуть»
     * товар до запроса, не переименовывая его. Оно обязано искаться.
     */
    public function test_keywords_field_is_searchable(): void
    {
        $modelId = \Illuminate\Support\Facades\DB::table('rent_model_web as w')
            ->join('tovar_rent_items as t', 't.model_id', '=', 'w.model_id')
            ->where('w.status', 'show')
            ->value('w.model_id');

        \Illuminate\Support\Facades\DB::table('rent_model_web')
            ->where('model_id', $modelId)
            ->update(['keywords' => 'зюзюблик']);

        $this->assertContains(
            (int) $modelId,
            (new ProductSearch())->find('зюзюблик')->getModelIds()
        );
    }
```

- [ ] **Step 2: Прогнать — должно упасть**

```bash
docker compose exec -T app php artisan test --filter=test_keywords_field_is_searchable
```
Ожидается: FAIL — «Failed asserting that an array contains ...».

**Важно:** `rent_model_web` — MyISAM, а MyISAM **не поддерживает транзакции**. `DatabaseTransactions` этот `update` не откатит. Поэтому тест обязан вернуть значение обратно — см. Step 3.

- [ ] **Step 3: Сделать тест самоочищающимся**

Заменить тело теста на версию с восстановлением:

```php
    public function test_keywords_field_is_searchable(): void
    {
        $row = \Illuminate\Support\Facades\DB::table('rent_model_web as w')
            ->join('tovar_rent_items as t', 't.model_id', '=', 'w.model_id')
            ->where('w.status', 'show')
            ->select('w.web_id', 'w.model_id', 'w.keywords')
            ->first();

        // rent_model_web — MyISAM, транзакций нет: DatabaseTransactions это не откатит.
        try {
            \Illuminate\Support\Facades\DB::table('rent_model_web')
                ->where('web_id', $row->web_id)
                ->update(['keywords' => 'зюзюблик']);

            $this->assertContains(
                (int) $row->model_id,
                (new ProductSearch())->find('зюзюблик')->getModelIds()
            );
        } finally {
            \Illuminate\Support\Facades\DB::table('rent_model_web')
                ->where('web_id', $row->web_id)
                ->update(['keywords' => $row->keywords]);
        }
    }
```

- [ ] **Step 4: Написать миграцию**

Создать `database/migrations/2026_08_31_100100_add_keywords_to_model_web_fulltext.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет keywords в поисковый FULLTEXT-индекс rent_model_web и убирает
 * дублирующий индекс `title` (title, item_name_main), который не использовал
 * ни один запрос — MATCH шёл по `title_2`.
 *
 * Schema::table()->fullText() в Laravel 8 недоступен (появился в 9.x),
 * поэтому сырой SQL. Синтаксис одинаков для MySQL 5.7 и MariaDB 10.6.
 */
class AddKeywordsToModelWebFulltext extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('ft_search')) {
            DB::statement(
                'ALTER TABLE rent_model_web
                 ADD FULLTEXT ft_search (title, l2_name, item_name_main, keywords)'
            );
        }

        foreach (['title', 'title_2'] as $legacyIndex) {
            if ($this->indexExists($legacyIndex)) {
                DB::statement('ALTER TABLE rent_model_web DROP INDEX `' . $legacyIndex . '`');
            }
        }
    }

    public function down(): void
    {
        if ($this->indexExists('ft_search')) {
            DB::statement('ALTER TABLE rent_model_web DROP INDEX ft_search');
        }
        if (!$this->indexExists('title_2')) {
            DB::statement(
                'ALTER TABLE rent_model_web
                 ADD FULLTEXT title_2 (title, l2_name, item_name_main)'
            );
        }
    }

    private function indexExists(string $name): bool
    {
        $rows = DB::select(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'rent_model_web'
               AND INDEX_NAME = ?
             LIMIT 1",
            [$name]
        );

        return $rows !== [];
    }
}
```

- [ ] **Step 5: Обновить MATCH_FIELDS**

В `app/MyClasses/Search/ProductSearch.php` заменить:

```php
    private const MATCH_FIELDS = 'w.title, w.l2_name, w.item_name_main';
```

на:

```php
    /**
     * Должно ПОБУКВЕННО совпадать со списком колонок индекса ft_search,
     * иначе MySQL не выберет индекс и MATCH упадёт с
     * "Can't find FULLTEXT index matching the column list".
     * См. миграцию 2026_08_31_100100_add_keywords_to_model_web_fulltext.
     */
    private const MATCH_FIELDS = 'w.title, w.l2_name, w.item_name_main, w.keywords';
```

- [ ] **Step 6: Прогнать миграцию и тесты**

```bash
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan test --filter=ProductSearchTest
```
Ожидается: миграция прошла, все тесты PASS.

- [ ] **Step 7: Проверить, что перестроение индекса не сломало старые запросы**

```bash
docker compose exec -T db mysql -u tiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  --default-character-set=utf8mb4 -e "
SELECT COUNT(DISTINCT w.model_id) FROM rent_model_web w
  JOIN tovar_rent_items t ON t.model_id=w.model_id
  WHERE MATCH(w.title,w.l2_name,w.item_name_main,w.keywords) AGAINST('+вес*' IN BOOLEAN MODE);"
```
Ожидается: ≥10.

- [ ] **Step 8: Коммит**

```bash
git add database/migrations/2026_08_31_100100_add_keywords_to_model_web_fulltext.php \
        app/MyClasses/Search/ProductSearch.php tests/Feature/Search/ProductSearchTest.php
git commit -m "feat(search): keywords в FULLTEXT-индексе, снят дублирующий индекс title"
```

---

### Task 5: Поиск по названию категории — третий тир

**Files:**
- Modify: `app/MyClasses/Search/ProductSearch.php`
- Modify: `tests/Feature/Search/ProductSearchTest.php`

**Interfaces:**
- Consumes: `QueryNormalizer::tokenize()`, `SearchResult::TIER_CATEGORY`.
- Produces: `ProductSearch::find()` начинает возвращать тир `'category'`.

**Зачем:** это самый крупный выигрыш плана. Владелец ведёт таксономию («Эргорюкзаки, слинги, туристические рюкзаки»), а поиск её не видит, потому что модели внутри названы «Эргономичный рюкзак», «Нагрудная сумка», «Хипсит». Категорий всего 120 — полный скан по `rent_cat_name` стоит копейки.

- [ ] **Step 1: Написать падающий тест**

Добавить в `tests/Feature/Search/ProductSearchTest.php`:

```php
    /**
     * Категория «Эргорюкзаки, слинги, туристические рюкзаки» — 16 моделей,
     * ни одна из которых не названа «эргорюкзак» или «слинг». До этой задачи
     * оба запроса давали ровно 1 результат.
     *
     * @dataProvider categoryOnlyQueries
     */
    public function test_query_matching_category_name_finds_its_models(string $query, int $atLeast): void
    {
        $result = (new ProductSearch())->find($query);

        $this->assertGreaterThanOrEqual(
            $atLeast,
            $result->getTotal(),
            "запрос «{$query}» вернул {$result->getTotal()} моделей"
        );
    }

    public function categoryOnlyQueries(): array
    {
        return [
            'эргорюкзак → вся категория' => ['эргорюкзак', 15],
            'слинг → вся категория'      => ['слинг', 15],
            'видеоняня → Радио- видеоняни' => ['видеоняня', 4],
            'шезлонг → вся категория'    => ['шезлонг', 8],
            'кокон → Кроватки, колыбели, коконы' => ['кокон', 9],
        ];
    }

    /**
     * Совпадение по товару всегда важнее совпадения по категории:
     * тот, кто ввёл «слинг», сначала должен увидеть «Слинг-рюкзак Babybjorn».
     */
    public function test_exact_model_matches_rank_above_category_matches(): void
    {
        $ids = (new ProductSearch())->find('слинг')->getModelIds();

        $directIds = \Illuminate\Support\Facades\DB::table('rent_model_web')
            ->where('status', 'show')
            ->where('l2_name', 'like', '%слинг%')
            ->pluck('model_id')
            ->map(function ($id) { return (int) $id; })
            ->all();

        $this->assertNotEmpty($directIds);
        $this->assertContains($ids[0], $directIds, 'первым идёт не прямое совпадение');
    }

    public function test_category_tier_is_reported(): void
    {
        $this->assertSame('category', (new ProductSearch())->find('видеоняня')->getTier());
    }
```

- [ ] **Step 2: Прогнать — должно упасть**

```bash
docker compose exec -T app php artisan test --filter=ProductSearchTest
```
Ожидается: FAIL на всех пяти кейсах `categoryOnlyQueries`.

- [ ] **Step 3: Реализовать проход по категориям**

В `app/MyClasses/Search/ProductSearch.php` заменить метод `find()` целиком:

```php
    public function find(string $query): SearchResult
    {
        $tokens = QueryNormalizer::tokenize($query);
        if ($tokens['stems'] === []) {
            return SearchResult::empty();
        }

        $exact = $this->fulltext($tokens['stems'], true);
        $categoryIds = $this->byCategoryName($tokens['stems']);

        // Прямые совпадения по товару всегда выше совпадений по категории:
        // на «слинг» сначала «Слинг-рюкзак Babybjorn», потом остальные переноски.
        if ($exact !== []) {
            $merged = $this->mergePreservingOrder($exact, $categoryIds);

            return new SearchResult($merged, SearchResult::TIER_EXACT);
        }

        if ($categoryIds !== []) {
            return new SearchResult($categoryIds, SearchResult::TIER_CATEGORY);
        }

        if (count($tokens['stems']) > 1) {
            $partial = $this->fulltext($tokens['stems'], false);
            if ($partial !== []) {
                return new SearchResult($partial, SearchResult::TIER_PARTIAL);
            }
        }

        return SearchResult::empty();
    }

    /**
     * Модели категорий, чьё название содержит хотя бы одну основу запроса.
     * Категорий всего ~120, поэтому LIKE по неиндексируемому полю здесь дешевле
     * и надёжнее, чем ещё один FULLTEXT (в названиях категорий много слов
     * короче ft_min_word_len: «мойка», «дуги»).
     *
     * Категории ранжируются по числу совпавших основ: «видео няня» даёт две
     * основы, «Радио- видеоняни» матчится одной, но всё равно попадает в выдачу.
     *
     * @param string[] $stems
     * @return int[]
     */
    private function byCategoryName(array $stems): array
    {
        $conditions = [];
        $bindings = [];
        $scoreParts = [];
        foreach ($stems as $stem) {
            $conditions[] = 'c.rent_cat_name LIKE ?';
            $bindings[] = '%' . $this->escapeLike($stem) . '%';
            $scoreParts[] = '(c.rent_cat_name LIKE ?)';
        }

        $sql = 'SELECT w.model_id, (' . implode(' + ', $scoreParts) . ') AS score
                FROM tovar_rent_cat c
                INNER JOIN tovar_rent tr ON tr.tovar_rent_cat_id = c.tovar_rent_cat_id
                INNER JOIN rent_model_web w ON w.model_id = tr.tovar_rent_id
                INNER JOIN tovar_rent_items t ON t.model_id = w.model_id
                WHERE (' . implode(' OR ', $conditions) . ')
                  AND w.status = ?
                GROUP BY w.model_id
                ORDER BY score DESC, w.model_id DESC';

        // Плейсхолдеры идут в порядке появления в SQL: сначала SELECT-score, потом WHERE.
        $rows = DB::select($sql, array_merge($bindings, $bindings, ['show']));

        return array_map(static function ($row) {
            return (int) $row->model_id;
        }, $rows);
    }

    /**
     * Склейка с сохранением порядка первого списка и без дублей.
     *
     * @param int[] $primary
     * @param int[] $secondary
     * @return int[]
     */
    private function mergePreservingOrder(array $primary, array $secondary): array
    {
        $seen = array_flip($primary);
        $merged = $primary;
        foreach ($secondary as $id) {
            if (!isset($seen[$id])) {
                $merged[] = $id;
                $seen[$id] = true;
            }
        }

        return $merged;
    }

    /** Экранирует спецсимволы LIKE, чтобы «100%» в запросе не стал wildcard-ом. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
```

- [ ] **Step 4: Прогнать тесты**

```bash
docker compose exec -T app php artisan test --filter=ProductSearchTest
```
Ожидается: PASS. Если `test_previously_working_queries_do_not_regress` упал — значит категорийный проход притянул мусор; сузить, потребовав совпадение основы длиной ≥5.

- [ ] **Step 5: Ручная проверка контрольной таблицы**

```bash
docker compose exec -T app php artisan tinker --execute="
\$s = new App\MyClasses\Search\ProductSearch();
foreach (['коляска','коляски','эргорюкзак','слинг','видеоняня','шезлонг','кокон','весы','стульчик','цыганский костюм'] as \$q) {
    \$r = \$s->find(\$q);
    echo str_pad(\$q, 20) . ' → ' . str_pad((string) \$r->getTotal(), 5) . \$r->getTier() . PHP_EOL;
}"
```
Ожидается примерно: коляска 41 exact / коляски 41 exact / эргорюкзак 19 category / слинг 19 exact / видеоняня 4 category / шезлонг 8 category / кокон 10 category / весы 10 exact / стульчик 9 exact / цыганский костюм — partial.

- [ ] **Step 6: Коммит**

```bash
git add app/MyClasses/Search/ProductSearch.php tests/Feature/Search/ProductSearchTest.php
git commit -m "feat(search): поиск по названию категории — чинит эргорюкзаки, слинги, видеоняни"
```

---

### Task 6: Словарь синонимов

**Files:**
- Create: `database/migrations/2026_08_31_100000_create_search_synonyms_table.php`
- Create: `database/seeders/SearchSynonymsSeeder.php`
- Create: `app/MyClasses/Search/SynonymDictionary.php`
- Modify: `app/MyClasses/Search/ProductSearch.php`
- Modify: `tests/Feature/Search/ProductSearchTest.php`

**Interfaces:**
- Consumes: `QueryNormalizer::stem()`.
- Produces: `SynonymDictionary::expand(array $stems): array` — возвращает расширенный список основ.

**Зачем:** закрывает то, что ни стемминг, ни категории не берут: «толокар» → «каталка» (слово живёт только в `main_descr`), «кенгуру»/«переноска» → «эргорюкзак», «люлька» → «автолюлька» (составное слово), «Дона» → «Doona» (кириллица вместо латиницы), опечатки брендов.

- [ ] **Step 1: Написать падающий тест**

Добавить в `tests/Feature/Search/ProductSearchTest.php`:

```php
    /** @dataProvider synonymQueries */
    public function test_synonyms_expand_the_query(string $query, int $atLeast): void
    {
        $result = (new ProductSearch())->find($query);

        $this->assertGreaterThanOrEqual(
            $atLeast,
            $result->getTotal(),
            "запрос «{$query}» вернул {$result->getTotal()} моделей"
        );
    }

    public function synonymQueries(): array
    {
        return [
            'толокар → машина-каталка' => ['толокар', 3],
            'кенгуру → эргорюкзаки'    => ['кенгуру', 15],
            'переноска → эргорюкзаки'  => ['переноска', 15],
            'дона → Doona'             => ['дона', 5],
            'сайбекс → Cybex'          => ['сайбекс', 20],
        ];
    }
```

- [ ] **Step 2: Прогнать — должно упасть**

```bash
docker compose exec -T app php artisan test --filter=test_synonyms_expand_the_query
```
Ожидается: FAIL на всех пяти.

- [ ] **Step 3: Миграция таблицы синонимов**

Создать `database/migrations/2026_08_31_100000_create_search_synonyms_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Словарь синонимов внутреннего поиска. Наполняется по нулевым запросам
 * из search_log; редактируется владельцем (страница в bb/ — Task 12).
 *
 * term    — ОСНОВА запроса после QueryNormalizer::stem(), в нижнем регистре
 * expands — что подставить вместо/вдобавок, через запятую, тоже основами
 */
class CreateSearchSynonymsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_synonyms')) {
            return;
        }

        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('term', 64)->unique();
            $table->string('expands', 255);
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
    }
}
```

- [ ] **Step 4: Сидер со стартовым словарём**

Создать `database/seeders/SearchSynonymsSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Стартовый словарь собран из нулевых запросов search_log за 13–30.08.2026.
 * Ключи — уже прогнанные через QueryNormalizer::stem() основы.
 */
class SearchSynonymsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // синонимы категорий
            ['кенгуру',   'эргорюкзак,рюкзак,переноск', 'категория 12'],
            ['переноск',  'эргорюкзак,рюкзак,слинг',    'категория 12'],
            ['хипсит',    'эргорюкзак,хисит',           'в каталоге опечатка «Хисит»'],
            ['толокар',   'каталк,машин',               '3 модели Chi Lok Bo'],
            ['люльк',     'автолюльк,люльк',            'составное слово'],
            ['видеонян',  'радионян,видеонян',          'категория 31'],
            ['башн',      'помощник,ступеньк',          'башня Монтессори'],
            ['тоннел',    'туннел,палатк',              ''],
            ['туннел',    'тоннел,палатк',              ''],
            // кириллица → латиница
            ['дона',      'doona',                      ''],
            ['сайбекс',   'cybex',                      ''],
            ['чикко',     'chicco',                     ''],
            ['бебибьорн', 'babybjorn',                  ''],
            ['йойо',      'yoyo',                       ''],
            ['пег',       'peg,perego',                 ''],
            // частые опечатки брендов из лога
            ['evenfli',   'evenflo',                    ''],
            ['babubjorn', 'babybjorn',                  ''],
            ['cyber',     'cybex',                      ''],
            ['donna',     'doona',                      ''],
            ['carella',   'carrello',                   ''],
        ];

        foreach ($rows as $row) {
            DB::table('search_synonyms')->updateOrInsert(
                ['term' => $row[0]],
                ['expands' => $row[1], 'note' => $row[2]]
            );
        }
    }
}
```

- [ ] **Step 5: Реализовать SynonymDictionary**

Создать `app/MyClasses/Search/SynonymDictionary.php`:

```php
<?php

namespace App\MyClasses\Search;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Словарь синонимов. Таблица маленькая (десятки строк) и меняется редко,
 * поэтому грузится целиком и кэшируется на час.
 */
class SynonymDictionary
{
    private const CACHE_KEY = 'search.synonyms';
    private const CACHE_TTL = 3600;

    /** Потолок расширения — иначе цепочка синонимов раздует BOOLEAN-выражение. */
    private const MAX_STEMS = 12;

    /**
     * @param string[] $stems
     * @return string[]
     */
    public function expand(array $stems): array
    {
        $map = $this->load();
        if ($map === []) {
            return $stems;
        }

        $expanded = $stems;
        foreach ($stems as $stem) {
            if (!isset($map[$stem])) {
                continue;
            }
            foreach (explode(',', $map[$stem]) as $synonym) {
                $synonym = trim($synonym);
                if ($synonym !== '') {
                    $expanded[] = $synonym;
                }
            }
        }

        return array_slice(array_values(array_unique($expanded)), 0, self::MAX_STEMS);
    }

    /** @return array<string, string> */
    private function load(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, static function () {
            try {
                return DB::table('search_synonyms')->pluck('expands', 'term')->all();
            } catch (\Throwable $e) {
                // Таблицы ещё нет (миграция не прогнана) — поиск обязан работать без неё.
                \Log::warning('search_synonyms unavailable: ' . $e->getMessage());

                return [];
            }
        });
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
```

- [ ] **Step 6: Подключить словарь в ProductSearch**

В `app/MyClasses/Search/ProductSearch.php` в начале `find()` после проверки на пустоту добавить расширение. Заменить:

```php
        $tokens = QueryNormalizer::tokenize($query);
        if ($tokens['stems'] === []) {
            return SearchResult::empty();
        }

        $exact = $this->fulltext($tokens['stems'], true);
        $categoryIds = $this->byCategoryName($tokens['stems']);
```

на:

```php
        $tokens = QueryNormalizer::tokenize($query);
        if ($tokens['stems'] === []) {
            return SearchResult::empty();
        }

        // Синонимы расширяют ТОЛЬКО OR-проходы и категории. В AND-проход они
        // не идут: «+кенгуру* +эргорюкзак*» потребовало бы оба слова сразу
        // и не нашло бы ничего.
        $expanded = (new SynonymDictionary())->expand($tokens['stems']);

        $exact = $this->fulltext($tokens['stems'], true);
        $categoryIds = $this->byCategoryName($expanded);
```

и в ветке фолбэка заменить `$this->fulltext($tokens['stems'], false)` на `$this->fulltext($expanded, false)`, а условие `count($tokens['stems']) > 1` — на `count($expanded) > 1`.

- [ ] **Step 7: Прогнать миграцию, сидер и тесты**

```bash
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan db:seed --class=SearchSynonymsSeeder
docker compose exec -T app php artisan test --filter=ProductSearchTest
```
Ожидается: PASS. Если «толокар» не находится — слово живёт в `main_descr`, а он не в индексе: вписать «толокар» в `keywords` трёх моделей Chi Lok Bo (это как раз штатный сценарий работы владельца) либо расширить синоним до `каталк,машин,mercedes`.

- [ ] **Step 8: Коммит**

```bash
git add database/migrations/2026_08_31_100000_create_search_synonyms_table.php \
        database/seeders/SearchSynonymsSeeder.php \
        app/MyClasses/Search/SynonymDictionary.php \
        app/MyClasses/Search/ProductSearch.php \
        tests/Feature/Search/ProductSearchTest.php
git commit -m "feat(search): словарь синонимов — толокар, кенгуру, кириллические бренды"
```

---

### Task 7: Короткие слова (< 4 символов)

**Files:**
- Modify: `app/MyClasses/Search/ProductSearch.php`
- Modify: `tests/Feature/Search/ProductSearchTest.php`

**Interfaces:**
- Consumes: `QueryNormalizer::tokenize()['short']`.
- Produces: ничего нового наружу — меняется только поведение `find()`.

**Зачем:** `ft_min_word_len = 4` на проде и локально. «мяч», «лук», «ева» FULLTEXT не видит **даже как `мяч*`** (проверено локально: `+мяч*` → 0, при том что «мяч» есть у 6 моделей). Менять `ft_min_word_len` нельзя — это правка `my.cnf` на хостинге плюс `REPAIR TABLE`.

- [ ] **Step 1: Написать падающий тест**

Добавить в `tests/Feature/Search/ProductSearchTest.php`:

```php
    /**
     * ft_min_word_len = 4: «мяч» не индексируется даже как «мяч*».
     * Проверено локально — «+мяч*» в BOOLEAN MODE даёт 0.
     */
    public function test_short_words_are_found_via_like_fallback(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->count('мяч'));
    }

    public function test_short_word_mixed_with_long_word_does_not_break(): void
    {
        $this->assertGreaterThan(0, $this->count('мяч для фитнеса'));
    }
```

- [ ] **Step 2: Прогнать — должно упасть**

```bash
docker compose exec -T app php artisan test --filter=test_short_word
```
Ожидается: FAIL, 0 результатов.

- [ ] **Step 3: Реализовать LIKE-ветку**

В `app/MyClasses/Search/ProductSearch.php` добавить метод:

```php
    /**
     * Поиск по токенам короче ft_min_word_len. FULLTEXT их не индексирует
     * ни в каком виде, поэтому единственный путь — LIKE по тем же полям.
     * Требуем границу слова с обеих сторон, иначе «мяч» вытащит «мячик»
     * вместе со всем, где эти три буквы встретились внутри слова.
     *
     * REGEXP вместо REGEXP_REPLACE: последнего нет в MySQL 5.7 (см. CLAUDE.md).
     *
     * @param string[] $shortTokens
     * @return int[]
     */
    private function byShortTokens(array $shortTokens): array
    {
        if ($shortTokens === []) {
            return [];
        }

        $conditions = [];
        $bindings = [];
        foreach ($shortTokens as $token) {
            $conditions[] = "CONCAT(w.title,' ',w.l2_name,' ',w.item_name_main,' ',w.keywords) REGEXP ?";
            $bindings[] = '[[:<:]]' . preg_quote($token, '/') . '[[:>:]]';
        }

        $rows = DB::select(
            'SELECT w.model_id
             FROM rent_model_web w
             INNER JOIN tovar_rent_items t ON t.model_id = w.model_id
             WHERE (' . implode(' OR ', $conditions) . ')
               AND w.status = ?
             GROUP BY w.model_id
             ORDER BY w.model_id DESC',
            array_merge($bindings, ['show'])
        );

        return array_map(static function ($row) {
            return (int) $row->model_id;
        }, $rows);
    }
```

**Проверить синтаксис границы слова:** MySQL 5.7 и MariaDB 10.6 используют `[[:<:]]`/`[[:>:]]`; в MySQL 8.0 это `\\b`. Прод — 5.7, локально MariaDB 10.6 — обе поддерживают `[[:<:]]`. Если тест упадёт с `Got error 'repetition-operator operand invalid'`, заменить на простой `LIKE '% мяч %'` с обрамлением пробелами через `CONCAT(' ', поля, ' ')`.

- [ ] **Step 4: Подключить ветку в find()**

В `find()` после строки `$categoryIds = $this->byCategoryName($expanded);` добавить:

```php
        $shortIds = $this->byShortTokens($tokens['short']);
```

и в обеих ветках, где формируется результат, склеивать `$shortIds` последним:

```php
        if ($exact !== [] || $shortIds !== []) {
            $merged = $this->mergePreservingOrder(
                $exact !== [] ? $exact : $shortIds,
                $this->mergePreservingOrder($shortIds, $categoryIds)
            );

            return new SearchResult($merged, SearchResult::TIER_EXACT);
        }
```

- [ ] **Step 5: Прогнать тесты**

```bash
docker compose exec -T app php artisan test --filter=ProductSearchTest
```
Ожидается: PASS по всем.

- [ ] **Step 6: Коммит**

```bash
git add app/MyClasses/Search/ProductSearch.php tests/Feature/Search/ProductSearchTest.php
git commit -m "feat(search): находить слова короче ft_min_word_len через REGEXP-ветку"
```

---

### Task 8: Страница результатов — счётчик, форма, честная пустая выдача

**Files:**
- Modify: `resources/views/search.blade.php:36-46`
- Modify: `app/Http/Controllers/SearchController.php`
- Create: `resources/views/includes/search_empty.blade.php`
- Create: `tests/Feature/Search/SearchPageTest.php`

**Interfaces:**
- Consumes: `searchTier`, `totalFound` из Task 3 Step 6.
- Produces: ничего для кода — только разметка.

**Зачем:** 20 % поисков заканчиваются экраном «Товары в данной **категории** не найдены» — текст не про поиск, без формы, без подсказок, без выхода. При 79 % мобильного трафика это гарантированный выход с сайта.

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Feature/Search/SearchPageTest.php`:

```php
<?php

namespace Tests\Feature\Search;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_results_page_shows_the_number_found(): void
    {
        $response = $this->get('/ru/search?search=' . urlencode('коляска'));

        $response->assertStatus(200);
        $response->assertSee('Найдено', false);
    }

    public function test_results_page_has_a_search_form_to_refine(): void
    {
        $response = $this->get('/ru/search?search=' . urlencode('коляска'));

        $response->assertSee('name="search"', false);
        $response->assertSee('value="коляска"', false);
    }

    public function test_empty_result_does_not_say_category(): void
    {
        $response = $this->get('/ru/search?search=' . urlencode('зюзюбликтест'));

        $response->assertStatus(200);
        $response->assertDontSee('в данной категории', false);
        $response->assertSee('Ничего не нашлось', false);
    }

    public function test_empty_result_offers_popular_categories(): void
    {
        $response = $this->get('/ru/search?search=' . urlencode('зюзюбликтест'));

        $response->assertSee('Коляски', false);
    }

    public function test_partial_match_is_labeled_honestly(): void
    {
        $response = $this->get('/ru/search?search=' . urlencode('цыганский костюм'));

        $response->assertSee('Точных совпадений не нашлось', false);
    }

    public function test_search_results_stay_noindex(): void
    {
        $response = $this->get('/ru/search?search=' . urlencode('коляска'));

        $response->assertSee('noindex', false);
    }
}
```

- [ ] **Step 2: Прогнать — должно упасть**

```bash
docker compose exec -T app php artisan test --filter=SearchPageTest
```
Ожидается: FAIL на всех, кроме `test_search_results_stay_noindex`.

- [ ] **Step 3: Создать блок пустой выдачи**

Создать `resources/views/includes/search_empty.blade.php`:

```blade
{{-- Экран «ничего не нашлось». 20% поисков приходят сюда — он обязан
     давать выход, а не тупик. Категории захардкожены осознанно: это
     пять самых частых запросов из search_log, а не автогенерация. --}}
<div class="col-12">
    <div class="search-empty" style="text-align:center; padding:30px 15px;">
        <h2 style="font-size:20px; margin-bottom:10px;">Ничего не нашлось</h2>
        <p style="color:#666; margin-bottom:20px;">
            Проверьте написание или попробуйте более короткое слово —
            например, «коляска» вместо «прогулочная коляска трость».
        </p>

        <form method="get" action="/{{ request()->lang ?: 'ru' }}/search"
              style="max-width:420px; margin:0 auto 25px; position:relative;">
            <input type="search" name="search" value="{{ $query ?? '' }}"
                   enterkeyhint="search" aria-label="Поиск по каталогу"
                   placeholder="Я ищу..."
                   style="width:100%; height:44px; font-size:16px; padding:0 15px;
                          border:1px solid #ddd; border-radius:8px;">
            <button type="submit"
                    style="position:absolute; right:6px; top:50%; transform:translateY(-50%);
                           background:none; border:none; padding:8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3180D1" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
        </form>

        <p style="color:#666; margin-bottom:12px;">Или популярные разделы:</p>
        <div style="display:flex; flex-wrap:wrap; gap:10px; justify-content:center;">
            <a href="/ru/search?search=%D0%BA%D0%BE%D0%BB%D1%8F%D1%81%D0%BA%D0%B0" class="btn btn-outline-primary">Коляски</a>
            <a href="/ru/search?search=%D0%B0%D0%B2%D1%82%D0%BE%D0%BA%D1%80%D0%B5%D1%81%D0%BB%D0%BE" class="btn btn-outline-primary">Автокресла</a>
            <a href="/ru/search?search=%D0%BC%D0%B0%D0%BD%D0%B5%D0%B6" class="btn btn-outline-primary">Манежи</a>
            <a href="/ru/search?search=%D0%B2%D0%B5%D0%BB%D0%BE%D1%81%D0%B8%D0%BF%D0%B5%D0%B4" class="btn btn-outline-primary">Велосипеды</a>
            <a href="/ru/search?search=%D1%8D%D1%80%D0%B3%D0%BE%D1%80%D1%8E%D0%BA%D0%B7%D0%B0%D0%BA" class="btn btn-outline-primary">Эргорюкзаки</a>
        </div>
    </div>
</div>
```

**Внимание:** в строке `<p style="color:#666; margin-bottom:12px;">Или популярные разделы:</p>` первый символ — иероглиф вместо «Или». Исправить на `Или популярные разделы:` при наборе.

- [ ] **Step 4: Подключить блок и счётчик в search.blade.php**

В `resources/views/search.blade.php` заменить блок с 36 по 46 строку:

```blade
            <div class="row l2-cards-container">
                @if($p->getModelsNum()>0)
                    @foreach($p->getModels() as $m)
                        @include('includes.l2_model_block', ['l2' => $m])
                    @endforeach

                @else
                    <div class="col">
                        <div class="alert-warning"><h2>Товары в данной категории не найдены.</h2></div>

                    </div>
                @endif
            </div> <!-- end of row -->
```

на:

```blade
            @isset($totalFound)
                @if($totalFound > 0)
                    <div class="col-12" style="margin-bottom:12px; color:#666;">
                        @if(($searchTier ?? '') === 'partial')
                            Точных совпадений не нашлось — показываем товары по части запроса.
                            Найдено {{ $totalFound }}
                        @elseif(($searchTier ?? '') === 'category')
                            Найдено {{ $totalFound }} в подходящих разделах каталога
                        @else
                            Найдено {{ $totalFound }}
                        @endif
                    </div>
                @endif
            @endisset

            <div class="row l2-cards-container">
                @if($p->getModelsNum()>0)
                    @foreach($p->getModels() as $m)
                        @include('includes.l2_model_block', ['l2' => $m])
                    @endforeach
                @else
                    @include('includes.search_empty', ['query' => $searchQuery ?? ''])
                @endif
            </div> <!-- end of row -->
```

- [ ] **Step 5: Прокинуть searchQuery из контроллера**

В `app/Http/Controllers/SearchController.php` в массив `view('search', [...])` метода `search()` добавить:

```php
            'searchQuery' => $text,
```

- [ ] **Step 6: Прогнать тесты**

```bash
docker compose exec -T app php artisan test --filter=SearchPageTest
```
Ожидается: PASS. `test_results_page_has_a_search_form_to_refine` упадёт, если форма рендерится только при пустой выдаче — тогда вынести форму из `search_empty.blade.php` наверх страницы, чтобы она была всегда.

- [ ] **Step 7: Коммит**

```bash
git add resources/views/search.blade.php resources/views/includes/search_empty.blade.php \
        app/Http/Controllers/SearchController.php tests/Feature/Search/SearchPageTest.php
git commit -m "feat(search): счётчик найденного, форма уточнения и живой экран пустой выдачи"
```

---

### Task 9: Мобильные фиксы шапки

**Files:**
- Modify: `resources/views/includes/header.blade.php:267`, `:349-352`, `:116-119`
- Modify: `resources/sass/sections/header.scss:822`
- Test: ручная проверка + сборка ассетов

**Interfaces:**
- Consumes: ничего.
- Produces: ничего.

**Зачем:** 79 % трафика — мобильный. Три конкретных дефекта:
1. `.mobile-header-new` в `app.css` объявлена `position: sticky; top: 0; z-index: 1000`, но инлайн-стиль в шаблоне перебивает её на `position: relative` — шапка с поиском уезжает при скролле.
2. У мобильного инпута **нет `font-size` вообще**: правило `.mobile-header-new .mob-action-bar ... input {font-size:14px}` относится к старой мёртвой разметке, поэтому применяется браузерный дефолт ~13,3 px. Всё, что меньше 16 px, заставляет iOS Safari зумить страницу при фокусе.
3. `type="text"` вместо `type="search"` — нет крестика очистки, на клавиатуре «Ввод» вместо «Найти».

- [ ] **Step 1: Снять инлайновый position, ломающий sticky**

В `resources/views/includes/header.blade.php` строка 267–268 заменить:

```blade
            <div class="mobile-header-new d-md-none"
                style="background: #fff; border-bottom: 1px solid #eee; position: relative;">
```

на:

```blade
            {{-- position задаётся в app.css (.mobile-header-new: sticky/top:0/z-index:1000).
                 Инлайновый position: relative его перебивал, и шапка с поиском
                 уезжала при скролле — при 79% мобильного трафика это главный
                 способ потерять человека после неудачного поиска. --}}
            <div class="mobile-header-new d-md-none"
                style="background: #fff; border-bottom: 1px solid #eee;">
```

- [ ] **Step 2: Починить мобильный инпут**

В том же файле строки 350–352 заменить:

```blade
                        <input type="text" name="search" placeholder="Я ищу..."
                            style="width: 100%; height: 40px; padding-left: 15px; border: 1px solid #ddd; border-radius: 8px; background: #fff;">
```

на:

```blade
                        {{-- font-size: 16px обязателен: при меньшем значении iOS Safari
                             зумит страницу на фокусе, и человек попадает в поиск
                             уже с уехавшей вёрсткой. --}}
                        <input type="search" name="search" placeholder="Я ищу..."
                            value="{{ request()->input('search') }}"
                            enterkeyhint="search" autocomplete="off"
                            aria-label="Поиск по каталогу"
                            style="width: 100%; height: 44px; font-size: 16px; padding-left: 15px; border: 1px solid #ddd; border-radius: 8px; background: #fff;">
```

- [ ] **Step 3: Починить десктопный инпут (та же болячка на планшетах)**

Строки 118–119 заменить:

```blade
                <input name="search" type="text" placeholder="Я ищу... (например, автокресло)">
```

на:

```blade
                <input name="search" type="search" placeholder="Я ищу... (например, автокресло)"
                       value="{{ request()->input('search') }}"
                       enterkeyhint="search" aria-label="Поиск по каталогу">
```

- [ ] **Step 4: Поднять font-size в scss**

В `resources/sass/sections/header.scss` в блоке `.header-search-small input` (около строки 830) заменить `font-size: 14px;` на:

```scss
    // 16px — порог, ниже которого iOS Safari зумит страницу при фокусе.
    font-size: 16px;
```

- [ ] **Step 5: Собрать ассеты**

```bash
npm run prod
```
Ожидается: сборка без ошибок, изменились `public/css/app.css` и `public/mix-manifest.json`.

- [ ] **Step 6: Проверить в браузере**

```bash
docker compose exec -T app php artisan view:clear
```
Открыть `http://localhost` в мобильной эмуляции (DevTools → iPhone SE), проверить:
- шапка с поиском остаётся сверху при скролле;
- тап по полю поиска не зумит страницу;
- в поле появляется крестик очистки после ввода;
- на странице результатов в поле уже стоит введённый запрос.

- [ ] **Step 7: Коммит**

```bash
git add resources/views/includes/header.blade.php resources/sass/sections/header.scss \
        public/css/ public/js/ public/mix-manifest.json
git commit -m "fix(mobile): липкая шапка поиска, 16px против автозума iOS, type=search"
```

---

### Task 10: Логировать тир и устройство, отсечь скриптовые прогоны

**Files:**
- Create: `database/migrations/2026_08_31_100200_add_tier_to_search_log.php`
- Modify: `app/Http/Controllers/SearchController.php:65-95`
- Modify: `tests/Feature/SearchLogTest.php`

**Interfaces:**
- Consumes: `SearchResult::getTier()`.
- Produces: колонки `search_log.match_tier`, `search_log.is_mobile`.

**Зачем:** сейчас лог отвечает «что искали», но не «нашли ли». Плюс 23 % строк лога оказались одним скриптовым прогоном, который бот-фильтр по User-Agent не поймал.

- [ ] **Step 1: Написать падающий тест**

Добавить в `tests/Feature/SearchLogTest.php`:

```php
    public function test_log_records_match_tier(): void
    {
        $this->get('/ru/search?search=' . urlencode('коляска'));

        $this->assertDatabaseHas('search_log', [
            'query'      => 'коляска',
            'match_tier' => 'exact',
        ]);
    }

    public function test_log_records_mobile_flag(): void
    {
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15',
        ])->get('/ru/search?search=' . urlencode('манеж'));

        $this->assertDatabaseHas('search_log', [
            'query'     => 'манеж',
            'is_mobile' => 1,
        ]);
    }

    /**
     * 30.08.2026 один IP отправил 120 запросов за 2 минуты с обычным
     * Chrome-User-Agent — фильтр по маркерам его не увидел, и 23% лога
     * стали мусором. Частота решает там, где User-Agent врёт.
     */
    public function test_burst_from_one_ip_is_not_logged(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->get('/ru/search?search=' . urlencode('коляска' . $i));
        }

        $logged = DB::table('search_log')
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        $this->assertLessThan(25, $logged);
    }
```

- [ ] **Step 2: Прогнать — должно упасть**

```bash
docker compose exec -T app php artisan test --filter=SearchLogTest
```
Ожидается: FAIL, `Unknown column 'match_tier'`.

- [ ] **Step 3: Миграция**

Создать `database/migrations/2026_08_31_100200_add_tier_to_search_log.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTierToSearchLog extends Migration
{
    public function up(): void
    {
        Schema::table('search_log', function (Blueprint $table) {
            if (!Schema::hasColumn('search_log', 'match_tier')) {
                $table->string('match_tier', 16)->nullable()->after('results_count');
            }
            if (!Schema::hasColumn('search_log', 'is_mobile')) {
                $table->boolean('is_mobile')->nullable()->after('match_tier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('search_log', function (Blueprint $table) {
            $table->dropColumn(['match_tier', 'is_mobile']);
        });
    }
}
```

- [ ] **Step 4: Дописать логирование**

В `app/Http/Controllers/SearchController.php` заменить сигнатуру и тело `logSearchQuery`:

```php
    private function logSearchQuery(Request $req, string $text, int $resultsCount, string $tier): void
    {
        if ($text === '' || self::isBotUserAgent($req->userAgent()) || $this->isBurst($req)) {
            return;
        }

        try {
            DB::table('search_log')->insert([
                'created_at'    => now(),
                'ip'            => $req->ip(),
                'query'         => mb_substr($text, 0, 255),
                'results_count' => min($resultsCount, 65535),
                'match_tier'    => $tier,
                'is_mobile'     => self::isMobileUserAgent($req->userAgent()) ? 1 : 0,
                'user_agent'    => mb_substr($req->userAgent() ?? '', 0, 255),
            ]);
        } catch (\Exception $e) {
            \Log::error('SearchLog failed: ' . $e->getMessage());
        }
    }

    /**
     * Больше 15 запросов с одного IP за 5 минут — это скрипт, а не человек.
     * User-Agent тут не помогает: 30.08.2026 такой прогон пришёл с обычным
     * Chrome-UA и занял 23% лога.
     */
    private function isBurst(Request $req): bool
    {
        try {
            return DB::table('search_log')
                ->where('ip', $req->ip())
                ->where('created_at', '>=', now()->subMinutes(5))
                ->count() >= 15;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function isMobileUserAgent(?string $userAgent): bool
    {
        if (!$userAgent) {
            return false;
        }

        return (bool) preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent);
    }
```

и в `search()` заменить вызов на:

```php
        $this->logSearchQuery($req, $text, $total, $searchResult->getTier());
```

- [ ] **Step 5: Прогнать миграцию и тесты**

```bash
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan test --filter=SearchLogTest
```
Ожидается: PASS.

- [ ] **Step 6: Коммит**

```bash
git add database/migrations/2026_08_31_100200_add_tier_to_search_log.php \
        app/Http/Controllers/SearchController.php tests/Feature/SearchLogTest.php
git commit -m "feat(search): логировать тир совпадения, мобильность и глушить скриптовые прогоны"
```

---

### Task 11: Проверка на конфликты и PR

**Files:** нет

- [ ] **Step 1: Прогнать весь набор тестов**

```bash
docker compose exec -T app php artisan test
```
Ожидается: PASS. Любое падение — чинить до PR, а не «потом».

- [ ] **Step 2: Проверить, что ветка мержится чисто**

```bash
git fetch origin && git merge-tree --write-tree --messages HEAD origin/main
echo "exit=$?"
```
Ожидается: `exit=0`.

- [ ] **Step 3: Проверить, что route:cache не сломан**

```bash
docker compose exec -T app php artisan route:cache && docker compose exec -T app php artisan route:clear
```
Ожидается: `Routes cached successfully.`

- [ ] **Step 4: Прогнать контрольную таблицу «после» и вписать её в описание PR**

```bash
docker compose exec -T app php artisan tinker --execute="
\$s = new App\MyClasses\Search\ProductSearch();
foreach (['коляска','коляски','кроватки','эргорюкзак','слинг','кенгуру','переноска','видеоняня','шезлонг','кокон','толокар','мяч','весы','стульчик','цыганский костюм'] as \$q) {
    \$r = \$s->find(\$q);
    echo str_pad(\$q, 20) . ' → ' . str_pad((string) \$r->getTotal(), 5) . \$r->getTier() . PHP_EOL;
}"
```

- [ ] **Step 5: Открыть PR**

```bash
git push -u origin feature/search-overhaul
gh pr create --title "feat(search): переделка внутреннего поиска" --body "$(cat <<'BODY'
## Что было
20,3% поисков заканчивались нулём; 79% трафика — мобильный.

- natural language mode OR-ил слова: «цыганский костюм» → 306 моделей, при этом «цыганский» отдельно → 0
- нет морфологии: «коляска» → 38, «коляски» → 0
- название категории не участвовало в поиске: «эргорюкзак» → 1 при 16 моделях в категории «Эргорюкзаки, слинги, туристические рюкзаки»
- keywords (заполнен у 797/848) не индексировался
- ft_min_word_len = 4 глушил «мяч», «лук»
- пустая выдача — текст «Товары в данной категории не найдены» без формы и подсказок
- мобильная шапка не липкая (инлайновый position перебивал sticky), инпут < 16px → автозум iOS

## Что стало
Поиск переехал в `App\MyClasses\Search\ProductSearch`: BOOLEAN MODE со стеммингом, три тира (товар → категория → частичное совпадение), словарь синонимов.

<таблица «до/после» из Step 4>

## Проверка
- `php artisan test` — зелёный
- контрольные запросы прогнаны на локальном каталоге (844 модели)

## Прод
Три миграции; `rent_model_web` — MyISAM, `ALTER TABLE ... ADD FULLTEXT` перестраивает индекс и **блокирует таблицу** на время перестроения (848 строк — секунды, но лучше в непиковое время).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
BODY
)"
```

---

### Task 12: Список для владельца — качество каталога (без кода)

Найдено при разборе и **не чинится поиском**, требует решения владельца:

- [ ] **«Хисит» вместо «Хипсит»** — модель `1053` («Хисит Babymamy от 6 мес до 3-х лет»), при этом модель `1777` названа правильно («Хипсит Babymamy Хипсит со спинкой», с дублем слова).
- [ ] **Аксессуары внутри товарной категории.** В «Шезлонги детские» (8 моделей) лежат 4 дуги с игрушками — из-за этого «шезлонг» находил 5 из 8. Вопрос: выносить дуги в отдельную категорию аксессуаров?
- [ ] **«Радио- видеоняни» — 4 модели, все Philips Avent SCD502/505/711, то есть аудио-радионяни.** Видеонянь как класса, похоже, нет, а спрос есть (6 запросов за 18 дней). После Task 5 человек хотя бы попадёт в эту категорию — но решение о закупке за вами.
- [ ] **Спрос без товара** (нулевые запросы, которых не чинит ни один пункт плана): фитбол, электромобиль, тоннель/туннель, спорткомплекс, термопот, термос, чайник, наушники, горшок, электрокачели, батут-трасса.
- [ ] **Бренды, которых нет:** Joolz, Carrello, Leonella, Diono Radian 3R/3QX, Filo, Cubed, timikbaby.
- [ ] **«Нагрудная сумка» ×4** в категории эргорюкзаков — не находится ни по одному синониму, который вводит человек. Стоит переименовать в «Рюкзак-переноска (нагрудная сумка) ...» или вписать синонимы в `keywords`.

---

## Self-Review

**Покрытие исходных проблем:**

| Проблема | Задача |
|---|---|
| OR-семантика многословных | Task 3 |
| Нет морфологии | Task 2 + 3 |
| keywords не в индексе | Task 4 |
| Название категории не ищется | Task 5 |
| Составные слова, транслит, опечатки | Task 6 |
| ft_min_word_len = 4 | Task 7 |
| Правило 50 % | Task 3 (BOOLEAN MODE) |
| Пустая выдача — тупик | Task 8 |
| Мобильная шапка не липкая | Task 9 |
| Автозум iOS | Task 9 |
| type=search, enterkeyhint, aria-label | Task 9 |
| Нет счётчика найденного | Task 8 |
| Нет формы на странице результатов | Task 8 |
| Лог не знает, нашли ли | Task 10 |
| Бот-фильтр пропускает скрипты | Task 10 |
| Дублирующий FULLTEXT-индекс `title` | Task 4 |
| Качество каталога | Task 12 |

**Сознательно не вошло** (обсудить отдельно, до этого не доводить самовольно):
- **Саджест/автодополнение** — самый крупный мобильный выигрыш, но это отдельный AJAX-эндпоинт, JS-компонент и свой набор тестов. Отдельный план.
- **Пагинация «Показать ещё»** вместо ссылки «Вперёд »» — 31 % успешных запросов дают больше страницы.
- **Логирование кликов по результату** (CTR + позиция) — без этого не измерить, стало ли лучше на самом деле.
- **Отчёт по поиску в `bb/`** — страница с нулевыми запросами и редактором `search_synonyms`.
- **Перевод `rent_model_web` в InnoDB** — снял бы правило 50 % и опустил порог слова с 4 до 3 символов (`innodb_ft_min_token_size = 3` на проде). Но локально MariaDB, а на проде MySQL 5.7 — по правилу из `CLAUDE.md` такое проверяется только на проде, локальный тест ничего не докажет.
- **Rate limit на `/search`** — сейчас его нет вовсе.
