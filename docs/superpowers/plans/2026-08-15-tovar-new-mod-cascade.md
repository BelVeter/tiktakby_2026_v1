# Каскад категория/фирма/модель + вкладки на tovar_new_mod.php — план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** На `bb/tovar_new_mod.php` заменить поле «Модель» (нативный `<select>` со всеми
~1800 именами) живым поиском, который сужается по выбранным категории/фирме, при выборе
существующей записи жёстко проставляет категорию+фирму, и развести два режима работы —
«новая модель» и «редактировать действующую» — по вкладкам, формализуя уже существующий, но
недоступный из UI `action=обновить`.

**Architecture:** Новый JSON-эндпоинт `bb/ajax_model_suggest.php` отдаёт строки `tovar_rent`
(категория+фирма+модель+цвет), отфильтрованные по тому, что уже выбрано. Новый виджет
`bb/assets/js/model_picker.js` строится на существующем `LivePicker` (`live_picker.js`),
которому добавляется общий хук `onItems` (трансформация результатов перед отрисовкой) и
исправление рассинхрона текста/значения на потере фокуса — эти два изменения в общем
ядре используются всеми четырьмя виджетами страницы (категория, фирма, обе вкладки модели).

**Tech Stack:** PHP 7.4 / mysqli (без ORM, `bb/` без composer autoload — каждый файл сам
объявляет зависимости через `require_once`), ванильный JS без библиотек, MariaDB 10.6.

## Global Constraints

- `bb/` не использует composer autoload — каждый новый/изменённый файл сам подключает свои
  зависимости через `require_once`.
- Права на все новые/изменённые AJAX-эндпоинты: уровни `[0, 5, 7]` (как у существующих
  `ajax_category_suggest.php`, `ajax_producer_suggest.php`) — копировать блок проверки
  `$_SESSION['svoi']`/`$_SESSION['level']` один в один.
- Все SQL-параметры из `$_REQUEST` — через `$mysqli->real_escape_string()`; числовые — через
  `(int)`. Родственный `bb/cat_ch_new.php` этого не делает (легаси), в новом коде — делаем.
- JSON-ответы — `JSON_UNESCAPED_UNICODE`, заголовок `Content-Type: application/json;
  charset=utf-8`.
- Работа идёт локально на ветке `feature/producers-directory` (уже текущая ветка), прод не
  трогаем. Docker: `docker compose exec -T app <cmd>` для PHP, `docker compose exec -T db
  mysql -utiktakby_tiktak -p"$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)" tiktakby_tiktak -e
  "<sql>"` для прямых запросов.
- В этом репозитории нет JS-тест-раннера (ни jest, ни mocha) — для чисто-логических функций
  (не трогающих DOM) пишем автономный `node`-скрипт на встроенном `assert`, без новых
  npm-пакетов. Это первый такой файл в репозитории; см. Задача 1.
- Каждый шаг с кодом — готовый код, без плейсхолдеров. Каждая задача заканчивается коммитом.

---

### Task 1: `live_picker.js` — рассинхрон на потере фокуса + хук `onItems`

**Files:**
- Modify: `bb/assets/js/live_picker.js`
- Create: `bb/assets/js/live_picker.test.js`

**Interfaces:**
- Produces: `LivePicker.resolveBlurAction(currentText, lastValue)` → `'keep' | 'clear' |
  'revert'` (чистая функция, без DOM). `LivePicker.prototype.syncOnBlur()` — метод экземпляра,
  использует `this.input`, `this.lastValue`, вызывает `this.reset()` либо откатывает
  `this.input.value`. Новое поле экземпляра `this.lastValue`. Новая опция конфига
  `config.onItems(items, query) → items` — вызывается после получения результатов (и в
  удалённом, и в локальном режиме), до `render()`; если не задана — результаты не меняются.
  Используется в Task 5 виджетом модели.

- [ ] **Step 1: Написать автономный тест на `resolveBlurAction`**

Создать `bb/assets/js/live_picker.test.js`:

```js
// Автономный тест без фреймворка: в репозитории нет jest/mocha, а эта функция
// не трогает DOM, поэтому достаточно встроенного assert. Запуск: node bb/assets/js/live_picker.test.js
'use strict';

var assert = require('assert');

global.window = global.window || {};
require('./live_picker.js');
var LivePicker = global.window.LivePicker;

assert.strictEqual(typeof LivePicker, 'function', 'live_picker.js должен выставить window.LivePicker');
assert.strictEqual(typeof LivePicker.resolveBlurAction, 'function', 'resolveBlurAction ещё не добавлена');

// Текст совпадает с последним выбором — ничего не делаем.
assert.strictEqual(LivePicker.resolveBlurAction('Bugaboo', 'Bugaboo'), 'keep');
assert.strictEqual(LivePicker.resolveBlurAction('  Bugaboo  ', 'Bugaboo'), 'keep', 'пробелы по краям не считаются изменением');

// Поле стёрли полностью — это явный сброс.
assert.strictEqual(LivePicker.resolveBlurAction('', 'Bugaboo'), 'clear');
assert.strictEqual(LivePicker.resolveBlurAction('   ', 'Bugaboo'), 'clear');

// Напечатали что-то другое и не выбрали вариант — откат к последнему выбору.
assert.strictEqual(LivePicker.resolveBlurAction('Buga', 'Bugaboo'), 'revert');

// Ничего раньше не было выбрано, поле так и осталось пустым.
assert.strictEqual(LivePicker.resolveBlurAction('', ''), 'keep');

console.log('live_picker.test.js: OK (6 assertions)');
```

- [ ] **Step 2: Запустить тест и убедиться, что падает**

Run: `node bb/assets/js/live_picker.test.js`
Expected: `AssertionError` / `TypeError: LivePicker.resolveBlurAction is not a function` —
функции ещё нет.

- [ ] **Step 3: Добавить `resolveBlurAction`, `syncOnBlur`, поле `lastValue` и вызов на blur**

В `bb/assets/js/live_picker.js` изменить константы (после `var SEARCH_DELAY = 200;`):

```js
	var SEARCH_DELAY = 200;
	var BLUR_DELAY = 150;
```

В конструкторе `LivePicker` — после строки `this.minQuery = config.minQuery === undefined ? 1 : config.minQuery;` добавить:

```js
		this.lastValue = this.input.value || '';
```

Там же, после существующих `addEventListener` вызовов (после `keydown`), добавить:

```js
		this.input.addEventListener('blur', function () {
			setTimeout(function () { self.syncOnBlur(); }, BLUR_DELAY);
		});
```

Клик по варианту сначала уводит фокус с поля (`blur`), и только потом срабатывает `click` на
строке результата — задержка нужна, чтобы `choose()` успел отработать раньше отката.

Добавить после `LivePicker.normalize`:

```js
	/**
	 * Что делать с текстом поля на потере фокуса, если он не был подтверждён
	 * выбором варианта. Чистая функция — без DOM, чтобы её можно было
	 * протестировать без браузера (см. live_picker.test.js).
	 */
	LivePicker.resolveBlurAction = function (currentText, lastValue) {
		var trimmed = String(currentText).trim();

		if (trimmed === lastValue) {
			return 'keep';
		}
		if (trimmed === '') {
			return 'clear';
		}
		return 'revert';
	};
```

Добавить новый метод прототипа (после `LivePicker.prototype.reset`):

```js
	/**
	 * Синхронизирует текст поля со скрытым значением, если пользователь начал
	 * печатать поверх уже выбранного варианта и передумал, не выбрав новый.
	 * Без этого скрытое поле держит старое значение, а видимый текст — то,
	 * что не удалось допечатать до конца.
	 */
	LivePicker.prototype.syncOnBlur = function () {
		var action = LivePicker.resolveBlurAction(this.input.value, this.lastValue);

		if (action === 'keep') {
			return;
		}
		if (action === 'clear') {
			this.reset();
			return;
		}
		this.input.value = this.lastValue;
		this.render([]);
	};
```

В `LivePicker.prototype.choose`, после `this.input.value = item.name;` добавить:

```js
		this.lastValue = item.name;
```

В `LivePicker.prototype.reset`, после `this.input.value = '';` добавить:

```js
		this.lastValue = '';
```

- [ ] **Step 4: Запустить тест снова, убедиться, что проходит**

Run: `node bb/assets/js/live_picker.test.js`
Expected: `live_picker.test.js: OK (6 assertions)`, код возврата 0.

- [ ] **Step 5: Добавить хук `onItems` в `search()`**

В `LivePicker.prototype.search`, локальная ветка (`if (this.config.items) { ... }`) — заменить:

```js
			if (this.config.items) {
				var needle = LivePicker.normalize(query);
				this.items = needle === ''
					? this.config.items.slice(0)
					: this.config.items.filter(function (item) {
						return LivePicker.normalize(item.name).indexOf(needle) !== -1;
					});
				this.active = -1;
				this.render(this.items);
				return;
			}
```

на:

```js
			if (this.config.items) {
				var needle = LivePicker.normalize(query);
				var localItems = needle === ''
					? this.config.items.slice(0)
					: this.config.items.filter(function (item) {
						return LivePicker.normalize(item.name).indexOf(needle) !== -1;
					});
				if (self.config.onItems) {
					localItems = self.config.onItems(localItems, query) || localItems;
				}
				this.items = localItems;
				this.active = -1;
				this.render(this.items);
				return;
			}
```

Удалённая ветка — заменить:

```js
				LivePicker.request(url, null, function (data) {
					self.items = data.items || [];
					self.active = -1;
					self.render(self.items);
				});
```

на:

```js
				LivePicker.request(url, null, function (data) {
					var items = data.items || [];
					if (self.config.onItems) {
						items = self.config.onItems(items, query) || items;
					}
					self.items = items;
					self.active = -1;
					self.render(self.items);
				});
```

Также обновить doc-comment виджета в шапке файла — в списке опций конфига после строки
`*   minQuery   — с какой длины запроса искать (по умолчанию 1)` добавить:

```
 *   onItems    — функция(items, query) -> items, вызывается после получения
 *                результатов до отрисовки; можно перегруппировать или отфильтровать
```

- [ ] **Step 6: Синтаксическая проверка**

Run: `node --check bb/assets/js/live_picker.js`
Expected: без вывода, код возврата 0.

- [ ] **Step 7: Финальный прогон теста и коммит**

Run: `node bb/assets/js/live_picker.test.js`
Expected: `live_picker.test.js: OK (6 assertions)`

```bash
git add bb/assets/js/live_picker.js bb/assets/js/live_picker.test.js
git commit -m "$(cat <<'EOF'
fix(bb): live_picker.js — синхронизация поля на blur + хук onItems

Без синхронизации скрытое значение виджета держит старый выбор, если
пользователь начал печатать поверх и передумал, не кликнув по варианту —
раньше это маскировалось тем, что смена категории сбрасывала производителя
целиком; после отвязки полей друг от друга риск рос. onItems даёт
виджету модели (следующие задачи) перегруппировать результаты по имени
на вкладке создания без дублирования рендер-логики.
EOF
)"
```

---

### Task 2: `ajax_category_suggest.php` — полный список по пустому запросу

**Files:**
- Modify: `bb/ajax_category_suggest.php`
- Modify: `bb/assets/js/category_picker.js`

**Interfaces:**
- Consumes: ничего нового.
- Produces: при `q=''` эндпоинт отдаёт `items` — все категории (тот же формат, что и при
  непустом `q`), без обрезки до 15.

- [ ] **Step 1: Смоук-тест — зафиксировать текущее (ошибочное) поведение**

Временный скрипт (не коммитится), скопировать в контейнер и запустить:

```bash
cat > /tmp/smoke_cat_empty.php <<'PHP'
<?php
session_start();
$_SESSION['svoi'] = 8941;
$_SESSION['level'] = 7;
$_REQUEST['q'] = '';
$_GET['q'] = '';

ob_start();
include '/var/www/html/bb/ajax_category_suggest.php';
$out = ob_get_clean();
$data = json_decode($out, true);

echo 'items count: ' . count($data['items']) . "\n";
if (count($data['items']) === 0) {
    echo "FAIL: пустой список при q=''\n";
} else {
    echo "OK: список непустой\n";
}
PHP
docker compose cp /tmp/smoke_cat_empty.php app:/tmp/smoke_cat_empty.php
docker compose exec -T app php /tmp/smoke_cat_empty.php
```

Expected (до правки): `items count: 0`, `FAIL: пустой список при q=''`.

- [ ] **Step 2: Отдавать полный список при пустом `q`**

В `bb/ajax_category_suggest.php` заменить:

```php
$response = ['items' => []];

if ($query !== '') {
    $needle = \bb\classes\Similarity::normalize($query);
```

на:

```php
$response = ['items' => []];

if ($query === '') {
    $response['items'] = $all;
} elseif ($query !== '') {
    $needle = \bb\classes\Similarity::normalize($query);
```

(Условие `elseif ($query !== '')` эквивалентно прежнему `if`, оставлено для минимального
диффа — весь остальной блок substring+fuzzy+`array_slice(..., 0, 15)` не меняется.)

- [ ] **Step 3: Повторить смоук-тест**

Run: `docker compose cp /tmp/smoke_cat_empty.php app:/tmp/smoke_cat_empty.php && docker compose exec -T app php /tmp/smoke_cat_empty.php`
Expected: `items count: 115` (или текущее число категорий в БД), `OK: список непустой`.

Удалить временный файл: `rm /tmp/smoke_cat_empty.php`

- [ ] **Step 4: Включить полный список по фокусу в виджете**

В `bb/assets/js/category_picker.js`, в `init()`, в конфиге основного `picker` (не
`subRazdelPicker` — у того `minQuery: 0` уже есть) — после `url: '/bb/ajax_category_suggest.php',` добавить:

```js
			minQuery:  0,
```

- [ ] **Step 5: Синтаксическая проверка и коммит**

Run: `php -l bb/ajax_category_suggest.php && node --check bb/assets/js/category_picker.js`
Expected: `No syntax errors detected` для обоих.

```bash
git add bb/ajax_category_suggest.php bb/assets/js/category_picker.js
git commit -m "$(cat <<'EOF'
feat(bb): полный список категорий по клику на пустое поле

ajax_category_suggest.php отдавал items:[] при q='' — путь не был нужен,
пока поле требовало ввода. minQuery:0 в category_picker.js включает
поведение выпадашки: 115 категорий нормально просматриваются целиком.
EOF
)"
```

---

### Task 3: `ajax_producer_suggest.php` — полный список по пустому запросу

**Files:**
- Modify: `bb/ajax_producer_suggest.php`
- Modify: `bb/assets/js/producer_picker.js`

**Interfaces:**
- Produces: при `q=''` эндпоинт отдаёт всех активных производителей (без обрезки); если
  передан `cat_id>0` — впереди встречавшиеся в этой категории (та же сортировка, что уже
  применяется при непустом `q`).

- [ ] **Step 1: Смоук-тест — зафиксировать текущее поведение**

```bash
cat > /tmp/smoke_prod_empty.php <<'PHP'
<?php
session_start();
$_SESSION['svoi'] = 8941;
$_SESSION['level'] = 7;
$_REQUEST['q'] = '';
$_GET['q'] = '';

ob_start();
include '/var/www/html/bb/ajax_producer_suggest.php';
$out = ob_get_clean();
$data = json_decode($out, true);

echo 'items count: ' . count($data['items']) . "\n";
if (count($data['items']) === 0) {
    echo "FAIL: пустой список при q=''\n";
} else {
    echo "OK: список непустой\n";
}
PHP
docker compose cp /tmp/smoke_prod_empty.php app:/tmp/smoke_prod_empty.php
docker compose exec -T app php /tmp/smoke_prod_empty.php
```

Expected (до правки): `items count: 0`, `FAIL: пустой список при q=''`.

- [ ] **Step 2: Вынести сортировку и отдачу за пределы `if ($query !== '')`**

В `bb/ajax_producer_suggest.php` заменить блок от `$response = ['items' => [];` до конца
файла на:

```php
$response = ['items' => []];

if ($query === '') {
    $items = $active;
} else {
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
}

if ($catId > 0) {
    usort($items, function ($a, $b) use ($usedInCat) {
        $au = isset($usedInCat[$a->getName()]) ? 0 : 1;
        $bu = isset($usedInCat[$b->getName()]) ? 0 : 1;
        return $au <=> $bu ?: strcmp($a->getName(), $b->getName());
    });
}

$mapped = array_map(function ($p) {
    return [
        'id'     => $p->getName(),
        'name'   => $p->getName(),
        'hidden' => !$p->isActive(),
    ];
}, $items);

$response['items'] = $query === '' ? $mapped : array_slice($mapped, 0, 15);

if ($check && $query !== '') {
    $dup = \bb\classes\Producer::findDuplicates($query);

    $response['exact'] = $dup['exact'] ? ['id' => $dup['exact']->getName(), 'name' => $dup['exact']->getName()] : null;
    $response['similar'] = array_map(function ($m) {
        return ['id' => $m['producer']->getName(), 'name' => $m['producer']->getName(), 'score' => $m['score']];
    }, $dup['similar']);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
```

(Логика `check`-режима не меняется, просто оказывается после общего блока вместо вложенности
в старый `if`.)

- [ ] **Step 3: Повторить смоук-тест**

Run: `docker compose cp /tmp/smoke_prod_empty.php app:/tmp/smoke_prod_empty.php && docker compose exec -T app php /tmp/smoke_prod_empty.php`
Expected: `items count: <число активных производителей>`, `OK: список непустой`.

Проверить сортировку по категории отдельным прогоном:

```bash
cat > /tmp/smoke_prod_cat.php <<'PHP'
<?php
session_start();
$_SESSION['svoi'] = 8941;
$_SESSION['level'] = 7;
$_REQUEST['q'] = '';
$_REQUEST['cat_id'] = '1';
$_GET['q'] = '';
$_GET['cat_id'] = '1';

ob_start();
include '/var/www/html/bb/ajax_producer_suggest.php';
$out = ob_get_clean();
$data = json_decode($out, true);
echo 'first item: ' . $data['items'][0]['name'] . "\n";
echo 'total: ' . count($data['items']) . "\n";
PHP
docker compose cp /tmp/smoke_prod_cat.php app:/tmp/smoke_prod_cat.php
docker compose exec -T app php /tmp/smoke_prod_cat.php
```

Сверить первый элемент с `SELECT DISTINCT producer FROM tovar_rent WHERE
tovar_rent_cat_id=1 LIMIT 1` — он должен быть среди первых в ответе (если для категории 1
есть производители в базе).

Удалить временные файлы: `rm /tmp/smoke_prod_empty.php /tmp/smoke_prod_cat.php`

- [ ] **Step 4: Включить полный список по фокусу в виджете**

В `bb/assets/js/producer_picker.js`, в `init()`, в конфиге `picker` — после `valueKey:
'name',` добавить:

```js
			minQuery:  0,
```

- [ ] **Step 5: Синтаксическая проверка и коммит**

Run: `php -l bb/ajax_producer_suggest.php && node --check bb/assets/js/producer_picker.js`
Expected: `No syntax errors detected` для обоих.

```bash
git add bb/ajax_producer_suggest.php bb/assets/js/producer_picker.js
git commit -m "$(cat <<'EOF'
feat(bb): полный список производителей по клику на пустое поле

Та же правка, что и для категорий: ajax_producer_suggest.php отдавал
items:[] при q=''. Сортировка «встречавшиеся в категории — первыми»
(уже была для непустого запроса) теперь работает и для полного списка.
EOF
)"
```

---

### Task 4: `bb/ajax_model_suggest.php` — новый эндпоинт

**Files:**
- Create: `bb/ajax_model_suggest.php`

**Interfaces:**
- Consumes: `$_REQUEST['q']` (строка, опц.), `$_REQUEST['cat_id']` (int, опц.),
  `$_REQUEST['producer']` (строка, опц.).
- Produces: JSON `{"items": [...]}`, каждый элемент —
  `{id:int, name:string, cat_id:int, cat_name:string, cat_dog_name:string, producer:string,
  color:string, set:string, agr_price:string, agr_price_cur:string, price_new:string,
  lom_srok:string, model_addr:string, ph_addr:string, age_from:string, age_to:string,
  weight_from:string, weight_to:string, m_sex:string, collateral:string, ny:string,
  zv:string, tale:string, rez1:string, rez2:string}` — одна строка `tovar_rent` на элемент.
  Если не задан ни один из трёх параметров — `items: []` без запроса к БД. Используется в
  Task 5.

- [ ] **Step 1: Смоук-тест — эндпоинта ещё нет**

```bash
cat > /tmp/smoke_model.php <<'PHP'
<?php
session_start();
$_SESSION['svoi'] = 8941;
$_SESSION['level'] = 7;

function run($params, $label) {
    foreach ($params as $k => $v) { $_REQUEST[$k] = $v; $_GET[$k] = $v; }
    ob_start();
    include '/var/www/html/bb/ajax_model_suggest.php';
    $out = ob_get_clean();
    $data = json_decode($out, true);
    echo "--- $label ---\n";
    echo 'items: ' . (isset($data['items']) ? count($data['items']) : 'N/A') . "\n";
    if (isset($data['items'][0])) {
        $row = $data['items'][0];
        echo 'sample keys: ' . implode(',', array_keys($row)) . "\n";
    }
    $_REQUEST = []; $_GET = [];
}

run(['q' => '', 'cat_id' => '', 'producer' => ''], 'no filters, empty q -> должно быть 0');
run(['q' => 'о'], 'только q -> общая подстрока по всей базе');
PHP
docker compose cp /tmp/smoke_model.php app:/tmp/smoke_model.php
docker compose exec -T app php /tmp/smoke_model.php
```

Expected (до создания файла): `Warning: include(...): Failed to open stream` или похожая
ошибка — файла ещё нет.

- [ ] **Step 2: Собрать точные значения для проверки фильтров**

```bash
docker compose exec -T db mysql -utiktakby_tiktak -p"$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)" tiktakby_tiktak -e "
SELECT tovar_rent_id, tovar_rent_cat_id, producer, model, color FROM tovar_rent LIMIT 1;
"
```

Записать полученные `tovar_rent_cat_id`, `producer`, `model` — понадобятся в Step 4 как
конкретные значения для проверки фильтрации по `cat_id`/`producer`/`q`.

- [ ] **Step 3: Создать эндпоинт**

Создать `bb/ajax_model_suggest.php`:

```php
<?php
/**
 * Живой поиск моделей для bb/tovar_new_mod.php — обе вкладки (создание/редактирование).
 *
 * Отдаёт полные строки tovar_rent (категория+фирма+модель+ЦВЕТ — это и есть единица
 * уникальности в этой таблице), отфильтрованные по тому, что уже выбрано:
 *   q         — подстрока по имени модели, ищет по всей базе;
 *   cat_id    — только эта категория;
 *   producer  — только эта фирма (точное совпадение).
 * Параметры сочетаются через AND, не заданные — игнорируются. Если не задан НИ ОДИН из
 * трёх — отдаём пустой список: моделей 1627 уникальных троек категория+фирма+модель,
 * высыпать их все по клику на пустое поле бессмысленно (в отличие от категории и фирмы,
 * которых на порядок меньше — см. bb/ajax_category_suggest.php, bb/ajax_producer_suggest.php).
 *
 * Клиентский JS (bb/assets/js/model_picker.js) сам решает, показывать ли цвет отдельной
 * строкой (вкладка «редактировать») или схлопывать по имени (вкладка «новая модель») —
 * здесь всегда полная гранулярность, по одной строке на tovar_rent_id.
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

$query    = trim($_REQUEST['q'] ?? '');
$catId    = (int) ($_REQUEST['cat_id'] ?? 0);
$producer = trim($_REQUEST['producer'] ?? '');

$conditions = [];
if ($catId > 0) {
    $conditions[] = 'tr.tovar_rent_cat_id = ' . $catId;
}
if ($producer !== '') {
    $conditions[] = "tr.producer = '" . $mysqli->real_escape_string($producer) . "'";
}
if ($query !== '') {
    $conditions[] = "tr.model LIKE '%" . $mysqli->real_escape_string($query) . "%'";
}

if (!$conditions) {
    echo json_encode(['items' => []]);
    exit;
}

$sql = "SELECT tr.tovar_rent_id AS id, tr.model AS name, tr.tovar_rent_cat_id AS cat_id,
               c.rent_cat_name AS cat_name, c.dog_name AS cat_dog_name,
               tr.producer AS producer, tr.color AS color, tr.`set` AS `set`,
               tr.agr_price AS agr_price, tr.agr_price_cur AS agr_price_cur,
               tr.price_new AS price_new, tr.lom_srok AS lom_srok,
               tr.model_addr AS model_addr, tr.ph_addr AS ph_addr,
               tr.age_from AS age_from, tr.age_to AS age_to,
               tr.weight_from AS weight_from, tr.weight_to AS weight_to,
               tr.m_sex AS m_sex, tr.collateral AS collateral,
               tr.ny AS ny, tr.zv AS zv, tr.tale AS tale, tr.rez1 AS rez1, tr.rez2 AS rez2
          FROM tovar_rent tr
          JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = tr.tovar_rent_cat_id
         WHERE " . implode(' AND ', $conditions) . "
      ORDER BY tr.model
         LIMIT 200";

$result = $mysqli->query($sql);
if (!$result) {
    echo json_encode(['items' => [], 'error' => 'Сбой при доступе к базе данных']);
    exit;
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $row['id']     = (int) $row['id'];
    $row['cat_id'] = (int) $row['cat_id'];
    $items[] = $row;
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
```

- [ ] **Step 4: Повторить смоук-тест + проверить каждый фильтр отдельно**

Run: `docker compose cp /tmp/smoke_model.php app:/tmp/smoke_model.php && docker compose exec -T app php /tmp/smoke_model.php`
Expected: первый блок — `items: 0`; второй блок — `items: <N>` с `sample keys:
id,name,cat_id,cat_name,cat_dog_name,producer,color,set,agr_price,agr_price_cur,price_new,lom_srok,model_addr,ph_addr,age_from,age_to,weight_from,weight_to,m_sex,collateral,ny,zv,tale,rez1,rez2`.

Дополнительно проверить фильтр по `cat_id`/`producer` конкретными значениями из Step 2:

```bash
cat > /tmp/smoke_model2.php <<'PHP'
<?php
session_start();
$_SESSION['svoi'] = 8941;
$_SESSION['level'] = 7;

// Подставить реальные значения, полученные в Step 2.
$_REQUEST['cat_id'] = 'ЗНАЧЕНИЕ_ИЗ_STEP_2';
$_GET['cat_id'] = 'ЗНАЧЕНИЕ_ИЗ_STEP_2';

ob_start();
include '/var/www/html/bb/ajax_model_suggest.php';
$out = ob_get_clean();
$data = json_decode($out, true);

$catId = (int) $_REQUEST['cat_id'];
$allMatch = true;
foreach ($data['items'] as $row) {
    if ((int) $row['cat_id'] !== $catId) { $allMatch = false; }
}
echo 'items: ' . count($data['items']) . "\n";
echo $allMatch ? "OK: все строки этой категории\n" : "FAIL: попались чужие категории\n";
PHP
```

(Заменить `ЗНАЧЕНИЕ_ИЗ_STEP_2` на реальный `tovar_rent_cat_id` перед запуском.)

Run: `docker compose cp /tmp/smoke_model2.php app:/tmp/smoke_model2.php && docker compose exec -T app php /tmp/smoke_model2.php`
Expected: `OK: все строки этой категории`

Удалить временные файлы: `rm /tmp/smoke_model.php /tmp/smoke_model2.php`

- [ ] **Step 5: Синтаксическая проверка и коммит**

Run: `php -l bb/ajax_model_suggest.php`
Expected: `No syntax errors detected`

```bash
git add bb/ajax_model_suggest.php
git commit -m "$(cat <<'EOF'
feat(bb): ajax_model_suggest.php — живой поиск моделей для tovar_new_mod.php

Отдаёт полные строки tovar_rent (категория+фирма+модель+цвет), с
фильтрами по q/cat_id/producer, сочетаемыми через AND. Один эндпоинт для
обеих вкладок будущего виджета модели — группировку по имени для
вкладки создания делает клиентский JS (см. следующую задачу).
EOF
)"
```

---

### Task 5: `bb/assets/js/model_picker.js` — виджет модели (обе вкладки)

**Files:**
- Create: `bb/assets/js/model_picker.js`
- Create: `bb/assets/js/model_picker.test.js`
- Modify: `bb/assets/js/category_picker.js`
- Modify: `bb/assets/js/producer_picker.js`

**Interfaces:**
- Consumes: `window.LivePicker` (Task 1: конфиг `onItems`, автоматически работающий
  `syncOnBlur`). `/bb/ajax_model_suggest.php` (Task 4: контракт элемента). Ожидает на
  странице DOM-элементы (появятся в Task 6): `model_search`, `model_new` (hidden),
  `model_results`, `model_hint`, `cat_select_new`, `producer_select_new`, `model_id`
  (hidden), `tab_new`, `tab_edit`, `submit_btn`, и поля для заполнения на вкладке
  редактирования: `color_new`, `m_set_new`, `m_price_new`, `m_price_cur_new`,
  `price_new_new`, `lom_srok_new`, `model_addr_new`, `ph_addr_new`, `age_from_new`,
  `age_to_new`, `weight_from_new`, `weight_to_new`, `m_sex`, `collateral_new`, `ny_new`,
  `zv_new`, `tale_new`, `rez1_new`, `rez2_new`. Читает `window.TOVAR_MOD_INITIAL_TAB`
  (`'new'` или `'edit'`, выставляется PHP в Task 6) для начального режима.
- Produces: `window.categoryPicker` и `window.producerPicker` — существующие виджеты
  категории/фирмы становятся доступны глобально (нужно виджету модели, чтобы вызывать их
  `.choose()` для жёсткого выбора). `window.__modelPickerTestHooks = {groupByName,
  resolveHint}` — только для `model_picker.test.js`, в браузере не используется.
  Самоинициализируется на `DOMContentLoaded`, как `category_picker.js`/`producer_picker.js`.

- [ ] **Step 1: Открыть доступ к существующим виджетам категории и фирмы**

В `bb/assets/js/category_picker.js`, в `init()`, после присваивания `picker = new
window.LivePicker({...});` (после закрывающей `});` вызова конструктора) добавить:

```js
		window.categoryPicker = picker;
```

В `bb/assets/js/producer_picker.js`, в `init()`, аналогично после конструктора `picker = new
window.LivePicker({...});` добавить:

```js
		window.producerPicker = picker;
```

- [ ] **Step 2: Написать автономный тест на чистые функции виджета**

Создать `bb/assets/js/model_picker.test.js`:

```js
// Автономный тест без фреймворка, как live_picker.test.js. Запуск:
// node bb/assets/js/model_picker.test.js
'use strict';

var assert = require('assert');

global.window = global.window || {};
global.document = global.document || { addEventListener: function () {}, readyState: 'complete' };
require('./live_picker.js');
require('./model_picker.js');

var hooks = global.window.__modelPickerTestHooks;
assert.ok(hooks, '__modelPickerTestHooks должен быть выставлен для тестов');

// --- groupByName: схлопывает строки с одинаковым именем, собирает цвета ---
var rows = [
	{ id: 1, name: 'Fox', cat_id: 7, cat_name: 'Коляски 3-в-1', cat_dog_name: 'коляска', producer: 'Bugaboo', color: 'красный' },
	{ id: 2, name: 'Fox', cat_id: 7, cat_name: 'Коляски 3-в-1', cat_dog_name: 'коляска', producer: 'Bugaboo', color: 'синий' },
	{ id: 3, name: 'Cameleon', cat_id: 7, cat_name: 'Коляски 3-в-1', cat_dog_name: 'коляска', producer: 'Bugaboo', color: 'чёрный' }
];
var grouped = hooks.groupByName(rows);

assert.strictEqual(grouped.length, 2, 'Fox должен схлопнуться в одну запись');
assert.strictEqual(grouped[0].name, 'Fox');
assert.deepStrictEqual(grouped[0].colors, ['красный', 'синий']);
assert.strictEqual(grouped[0].cat_id, 7, 'категория берётся из первой строки группы');
assert.strictEqual(grouped[1].name, 'Cameleon');
assert.deepStrictEqual(grouped[1].colors, ['чёрный']);

assert.deepStrictEqual(hooks.groupByName([]), [], 'пустой вход -> пустой выход');

// --- resolveHint: что показать под полем модели ---
assert.strictEqual(
	hooks.resolveHint({ mode: 'new', query: '', hasFilter: false, groups: [] }),
	'empty',
	'пустой запрос без категории/фирмы -> подсказка начать вводить'
);
assert.strictEqual(
	hooks.resolveHint({ mode: 'new', query: '', hasFilter: true, groups: [{ name: 'Fox', colors: ['красный'] }] }),
	null,
	'пустой запрос, но категория/фирма уже выбраны -> просто список, без подсказки'
);
assert.strictEqual(
	hooks.resolveHint({ mode: 'new', query: 'Fox', hasFilter: true, groups: [{ name: 'Fox', colors: ['красный', 'синий'] }] }),
	'exists',
	'точное совпадение имени на вкладке создания -> подсказка «уже есть»'
);
assert.strictEqual(
	hooks.resolveHint({ mode: 'new', query: 'Fo', hasFilter: true, groups: [{ name: 'Fox', colors: ['красный'] }] }),
	null,
	'частичное совпадение — это не точный дубль, подсказки не нужно'
);
assert.strictEqual(
	hooks.resolveHint({ mode: 'edit', query: '', hasFilter: false, groups: [] }),
	'empty',
	'на вкладке редактирования тот же пустой-фокус-без-фильтра сценарий'
);
assert.strictEqual(
	hooks.resolveHint({ mode: 'edit', query: 'Fox', hasFilter: true, groups: [{ name: 'Fox', colors: ['красный'] }] }),
	null,
	'на вкладке редактирования подсказки «уже есть» не бывает — сюда и пришли, чтобы редактировать'
);

console.log('model_picker.test.js: OK (12 assertions)');
```

- [ ] **Step 3: Запустить тест, убедиться, что падает**

Run: `node bb/assets/js/model_picker.test.js`
Expected: ошибка `Cannot find module './model_picker.js'` — файла ещё нет.

- [ ] **Step 4: Создать `model_picker.js`**

Создать `bb/assets/js/model_picker.js`:

```js
/**
 * Поле «Модель» на bb/tovar_new_mod.php — живой поиск с двумя режимами.
 *
 * Один и тот же эндпоинт (bb/ajax_model_suggest.php) отдаёт полные строки tovar_rent
 * (категория+фирма+модель+ЦВЕТ — единица уникальности в этой таблице). Разница между
 * вкладками — только в том, как эти строки показываются и что происходит при выборе:
 *
 *   «Новая модель»            — строки схлопываются по имени (groupByName), цвет не
 *                                показывается отдельно — на этой вкладке он вводится вручную
 *                                ниже. Выбор жёстко проставляет категорию и фирму, остальные
 *                                поля не трогает. Если набранное имя точно совпадает с
 *                                существующей записью в выбранных категории+фирме — рядом
 *                                появляется подсказка со ссылкой на вкладку редактирования.
 *
 *   «Редактировать действующую» — строки показываются как есть, по одной на tovar_rent_id,
 *                                цвет — во второй строке подсказки. Выбор жёстко проставляет
 *                                категорию, фирму, ID модели и ВСЕ остальные поля формы —
 *                                редактируемо, как today's action=редактировать.
 *
 * Категория и фирма — общие виджеты (category_picker.js, producer_picker.js), их LivePicker
 * инстансы открыты в window.categoryPicker/window.producerPicker специально для того, чтобы
 * этот файл мог вызвать их .choose() и проставить значение в обход обычного поиска.
 */
(function () {
	'use strict';

	var CHECK = { NEW: 'new', EDIT: 'edit' };

	var EDIT_FIELD_MAP = {
		color:         'color_new',
		set:           'm_set_new',
		agr_price:     'm_price_new',
		agr_price_cur: 'm_price_cur_new',
		price_new:     'price_new_new',
		lom_srok:      'lom_srok_new',
		model_addr:    'model_addr_new',
		ph_addr:       'ph_addr_new',
		age_from:      'age_from_new',
		age_to:        'age_to_new',
		weight_from:   'weight_from_new',
		weight_to:     'weight_to_new',
		m_sex:         'm_sex',
		collateral:    'collateral_new',
		ny:            'ny_new',
		zv:            'zv_new',
		tale:          'tale_new',
		rez1:          'rez1_new',
		rez2:          'rez2_new'
	};

	var els = {};
	var picker = null;
	var mode = CHECK.NEW;
	var pendingEditGroup = null;

	function $(id) {
		return document.getElementById(id);
	}

	/** Схлопывает строки с одинаковым именем в одну запись со списком цветов. */
	function groupByName(items) {
		var order = [];
		var byName = {};

		items.forEach(function (item) {
			if (!byName[item.name]) {
				byName[item.name] = {
					id:           item.name,
					name:         item.name,
					cat_id:       item.cat_id,
					cat_name:     item.cat_name,
					cat_dog_name: item.cat_dog_name,
					producer:     item.producer,
					colors:       []
				};
				order.push(item.name);
			}
			byName[item.name].colors.push(item.color);
		});

		return order.map(function (name) { return byName[name]; });
	}

	/**
	 * Что показать под полем: 'empty' — подсказка начать вводить/выбрать категорию,
	 * 'exists' — подсказка о существующей модели (только вкладка «новая»), null — ничего.
	 */
	function resolveHint(state) {
		if (state.query === '' && !state.hasFilter) {
			return 'empty';
		}

		if (state.mode === CHECK.NEW && state.query !== '') {
			var needle = window.LivePicker.normalize(state.query);
			var exact = state.groups.filter(function (g) {
				return window.LivePicker.normalize(g.name) === needle;
			});
			if (exact.length) {
				return 'exists';
			}
		}

		return null;
	}

	function currentFilterState(query) {
		var catVal = els.cat ? Number(els.cat.value) : 0;
		var prodVal = els.prod ? els.prod.value : '';
		return {
			mode:      mode,
			query:     query,
			hasFilter: catVal > 0 || !!prodVal
		};
	}

	function showHint(html) {
		els.hint.innerHTML = html;
		els.hint.className = 'catp__warn catp__warn--hint';
		els.hint.style.display = 'block';
	}

	function hideHint() {
		els.hint.style.display = 'none';
		els.hint.innerHTML = '';
	}

	function updateHint(rawItems, query) {
		var groups = mode === CHECK.NEW ? groupByName(rawItems) : [];
		var state = currentFilterState(query);
		state.groups = groups;

		var verdict = resolveHint(state);

		if (verdict === 'empty') {
			showHint('Начните вводить название модели или сначала выберите категорию/фирму');
			return;
		}

		if (verdict === 'exists') {
			var needle = window.LivePicker.normalize(query);
			var match = groups.filter(function (g) {
				return window.LivePicker.normalize(g.name) === needle;
			})[0];
			pendingEditGroup = match;
			showHint('Такая модель уже есть (цвета: ' + match.colors.join(', ') + ') — '
				+ '<a href="#" id="model_edit_link">редактировать</a>');
			return;
		}

		pendingEditGroup = null;
		hideHint();
	}

	/** Жёсткий выбор категории и фирмы из найденной записи — общий для обеих вкладок. */
	function hardSelectCategoryAndProducer(item) {
		if (window.categoryPicker) {
			window.categoryPicker.choose({
				id:       item.cat_id,
				name:     item.cat_name,
				dog_name: item.cat_dog_name
			});
		}
		if (window.producerPicker) {
			window.producerPicker.choose({ name: item.producer });
		}
	}

	/** Только на вкладке «редактировать»: подтягивает все остальные поля формы. */
	function fillEditFields(item) {
		if (els.modelId) {
			els.modelId.value = item.id;
		}
		Object.keys(EDIT_FIELD_MAP).forEach(function (key) {
			var field = $(EDIT_FIELD_MAP[key]);
			if (field && item[key] !== undefined) {
				field.value = item[key];
			}
		});
	}

	function setMode(newMode) {
		mode = newMode;
		pendingEditGroup = null;
		hideHint();

		if (els.tabNew) {
			els.tabNew.classList.toggle('catp-tab--active', mode === CHECK.NEW);
		}
		if (els.tabEdit) {
			els.tabEdit.classList.toggle('catp-tab--active', mode === CHECK.EDIT);
		}
		if (els.search) {
			els.search.placeholder = mode === CHECK.NEW
				? 'начните вводить название модели'
				: 'найдите модель для редактирования';
		}
		if (els.submitBtn) {
			els.submitBtn.value = mode === CHECK.NEW ? 'сохранить' : 'обновить';
		}
		if (picker) {
			picker.reset();
		}
	}

	function switchToEditWithQuery(name) {
		setMode(CHECK.EDIT);
		if (els.search) {
			els.search.value = name;
			els.search.dispatchEvent(new Event('input'));
			els.search.focus();
		}
	}

	function init() {
		els.search    = $('model_search');
		els.hidden    = $('model_new');
		els.results   = $('model_results');
		els.hint      = $('model_hint');
		els.cat       = $('cat_select_new');
		els.prod      = $('producer_select_new');
		els.modelId   = $('model_id');
		els.tabNew    = $('tab_new');
		els.tabEdit   = $('tab_edit');
		els.submitBtn = $('submit_btn');

		if (!els.search || !els.hidden || !els.results || !window.LivePicker) {
			return;
		}

		picker = new window.LivePicker({
			inputId:   'model_search',
			hiddenId:  'model_new',
			resultsId: 'model_results',
			url:       '/bb/ajax_model_suggest.php',
			valueKey:  'name',
			minQuery:  0,
			extraParams: function () {
				var params = {};
				if (els.cat && Number(els.cat.value) > 0) {
					params.cat_id = els.cat.value;
				}
				if (els.prod && els.prod.value) {
					params.producer = els.prod.value;
				}
				return params;
			},
			onItems: function (items, query) {
				updateHint(items, query);
				return mode === CHECK.NEW ? groupByName(items) : items;
			},
			renderMeta: function (item) {
				if (mode === CHECK.NEW) {
					return item.producer + ' · ' + item.cat_name
						+ (item.colors.length > 1 ? ' · цветов: ' + item.colors.length : '');
				}
				return item.producer + ' · ' + item.cat_name + ' · ' + item.color;
			},
			onChoose: function (item) {
				hardSelectCategoryAndProducer(item);
				if (mode === CHECK.EDIT) {
					fillEditFields(item);
				}
				hideHint();
			}
		});

		if (els.hint) {
			els.hint.addEventListener('click', function (event) {
				if (event.target && event.target.id === 'model_edit_link') {
					event.preventDefault();
					if (pendingEditGroup) {
						switchToEditWithQuery(pendingEditGroup.name);
					}
				}
			});
		}

		if (els.tabNew) {
			els.tabNew.addEventListener('click', function () { setMode(CHECK.NEW); });
		}
		if (els.tabEdit) {
			els.tabEdit.addEventListener('click', function () { setMode(CHECK.EDIT); });
		}

		setMode(window.TOVAR_MOD_INITIAL_TAB === 'edit' ? CHECK.EDIT : CHECK.NEW);
	}

	window.__modelPickerTestHooks = { groupByName: groupByName, resolveHint: resolveHint };

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
```

- [ ] **Step 5: Запустить тест снова**

Run: `node bb/assets/js/model_picker.test.js`
Expected: `model_picker.test.js: OK (12 assertions)`

- [ ] **Step 6: Синтаксическая проверка всех затронутых файлов**

Run: `node --check bb/assets/js/model_picker.js && node --check bb/assets/js/category_picker.js && node --check bb/assets/js/producer_picker.js`
Expected: без вывода, код возврата 0 для всех трёх.

- [ ] **Step 7: Коммит**

```bash
git add bb/assets/js/model_picker.js bb/assets/js/model_picker.test.js \
        bb/assets/js/category_picker.js bb/assets/js/producer_picker.js
git commit -m "$(cat <<'EOF'
feat(bb): model_picker.js — живой поиск модели, вкладки создание/редактирование

Один виджет на bb/ajax_model_suggest.php, режим переключается setMode():
на вкладке «новая модель» результаты схлопываются по имени и не трогают
остальные поля формы, на «редактировать действующую» показываются по
цвету и подтягивают все поля модели. Категория и фирма — общие виджеты,
их LivePicker-инстансы теперь открыты в window.categoryPicker/
window.producerPicker специально для жёсткого выбора отсюда.
EOF
)"
```

---

### Task 6: `bb/tovar_new_mod.php` — вкладки, разметка модели, PHP-обвязка

**Files:**
- Modify: `bb/tovar_new_mod.php`
- Modify: `bb/assets/styles/category_picker.css`

**Interfaces:**
- Consumes: `model_picker.js` (Task 5), `category_picker.js`/`producer_picker.js` (уже
  подключены). Ожидает `window.TOVAR_MOD_INITIAL_TAB`, задаётся PHP-веткой ниже.
- Produces: страница с рабочими вкладками; `$_POST['model_new']` — новое единое имя поля
  модели вместо пары `model_select_new`/`model_input_new`.

- [ ] **Step 1: Добавить CSS вкладок**

В `bb/assets/styles/category_picker.css` добавить в конец файла:

```css

/* ------------------------------------------------------------ вкладки модели */

.catp-tabs {
    margin-bottom: 12px;
}

.catp-tab {
    display: inline-block;
    padding: 7px 14px;
    margin-right: 4px;
    font-size: 14px;
    cursor: pointer;
    background: #eef1f5;
    border: 1px solid #b5b5b5;
    border-bottom: none;
    border-radius: 3px 3px 0 0;
}

.catp-tab--active {
    background: #fff;
    font-weight: bold;
}
```

- [ ] **Step 2: Добавить id полям карнавального блока (нужны виджету для авто-заполнения)**

В `bb/tovar_new_mod.php` найти блок «Для карнавала» (внутри строки таблицы с `Залог:`) и
заменить:

```php
			Залог: <input type="number" step="any" min="0" name="collateral" style="width:70px;"  value="' . $model_def['collateral'] . '" /> руб.;
			Новый год:
			<select name="ny" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['ny'], '1') . '>да</option>
			</select>;

			Зверь:
			<select name="zv" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['zv'], '1') . '>да</option>
			</select>;

			Сказка:
			<select name="tale" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['tale'], '1') . '>да</option>
			</select>;

		<span style="display:none;">
			Резерв1:
			<select name="rez1" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['rez1'], '1') . '>да</option>
			</select>;

			Резерв2:
			<select name="rez2" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['rez2'], '1') . '>да</option>
			</select>;

		</span>
```

на (то же самое + `id` у каждого поля):

```php
			Залог: <input type="number" step="any" min="0" name="collateral" id="collateral_new" style="width:70px;"  value="' . $model_def['collateral'] . '" /> руб.;
			Новый год:
			<select name="ny" id="ny_new" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['ny'], '1') . '>да</option>
			</select>;

			Зверь:
			<select name="zv" id="zv_new" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['zv'], '1') . '>да</option>
			</select>;

			Сказка:
			<select name="tale" id="tale_new" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['tale'], '1') . '>да</option>
			</select>;

		<span style="display:none;">
			Резерв1:
			<select name="rez1" id="rez1_new" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['rez1'], '1') . '>да</option>
			</select>;

			Резерв2:
			<select name="rez2" id="rez2_new" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['rez2'], '1') . '>да</option>
			</select>;

		</span>
```

- [ ] **Step 3: Заменить разметку поля «Модель» на живой поиск, добавить вкладки**

Найти блок (текущие строки 456–518 файла) от `echo '` с `<form name="tovar" ...>` до
закрытия строки таблицы «Модель» и заменить целиком на:

```php
echo '
<form name="tovar" action="tovar_new_mod.php" method="post">
<div id="new_model_div" class="new_div_r">
<strong>ID модели: ' . $model_id . '<br /></strong>

<div class="catp-tabs">
	<button type="button" id="tab_new" class="catp-tab">Новая модель</button>
	<button type="button" id="tab_edit" class="catp-tab">Редактировать действующую</button>
</div>

<table border="1" cellspacing="0">
	<tr>
		<td>Категория товара:</td>
		<td>
			<div class="catp">
				<input type="text" id="cat_search" class="catp__input" autocomplete="off"
					placeholder="начните вводить название категории"
					value="' . good_print($cat_def['rent_cat_name']) . '" />
				<div id="cat_results" class="catp__results"></div>
				<div id="cat_chosen" class="catp__chosen"' . ($cat_id > 0 ? ' style="display:block;"' : '') . '>'
					. ($cat_id > 0 ? 'Выбрана категория #' . (int) $cat_id . ' — ' . good_print($cat_def['rent_cat_name']) : '') . '</div>
			</div>
			<input type="hidden" name="cat_select_new" id="cat_select_new" value="' . (int) $cat_id . '" />
			<input type="button" id="cat_create_open" value="+ создать категорию" />

			<br />и для договора (ед.ч.):
			<input type="text" name="cat_input_dog_new" size="30" id="cat_input_dog_new" readonly="readonly" value="' . good_print($cat_def['dog_name']) . '"/>
			<span class="catp-modal__hint">подставляется из категории; изменить — в <a href="/bb/category_management.php">справочнике категорий</a></span>
			<input type="hidden" name="model_id" id="model_id" value="' . $model_id . '" />

		</td>
	</tr>
	<tr>
		<td>Альтернативное название категории для печати в договоре (если стандарт - оставляем пустое поле):</td>
		<td><input type="text" name="model_addr_new" size="70" id="model_addr_new" value="' . good_print($model_def['model_addr']) . '" /><br />
			<span style="display:none;">Адрес фото:<input type="text" name="ph_addr_new" size="70" id="ph_addr_new" value="' . good_print($model_def['ph_addr']) . '" /></span>
			</td>
	</tr>
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

	<tr>
		<td>Модель:</td>
		<td>
			<div class="catp">
				<input type="text" id="model_search" class="catp__input" autocomplete="off"
					placeholder="начните вводить название модели"
					value="' . good_print($model_def['model']) . '" />
				<div id="model_results" class="catp__results"></div>
				<div id="model_hint" class="catp__warn"></div>
			</div>
			<input type="hidden" name="model_new" id="model_new" value="' . good_print($model_def['model']) . '" />
		</td>
	</tr>
';
```

Убрать больше не нужную сборку списка моделей — в блоке чуть выше (сейчас строки ~447–452)
удалить:

```php
//chose model list
$query_model = "SELECT DISTINCT model FROM tovar_rent ORDER BY model";
$result_model = $mysqli->query($query_model);
if (!$result_model) {
	die('Сбой при доступе к базе данных: ' . $query_model . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
```

- [ ] **Step 4: Добавить id и глобальную переменную начальной вкладки на кнопку сабмита**

Найти строку (текущая ~634):

```php
' . ($action == 'редактировать' ? '<input type="submit" name="action" value="обновить" onclick="return send_form_ch();"/>' : '<input type="submit" name="action" value="сохранить" onclick="return send_form_ch();"/>') . '
```

Заменить на:

```php
' . ($action == 'редактировать' ? '<input type="submit" name="action" id="submit_btn" value="обновить" onclick="return send_form_ch();"/>' : '<input type="submit" name="action" id="submit_btn" value="сохранить" onclick="return send_form_ch();"/>') . '
```

- [ ] **Step 5: Подключить `model_picker.js` и передать начальную вкладку**

Найти блок подключения скриптов (текущие строки 741–753) и заменить:

```php
<script src="/bb/assets/js/live_picker.js?v=2"></script>
<script>
// Подразделы отдаём прямо в страницу: их 30, отдельный запрос не нужен.
window.SUB_RAZDELS = ' . json_encode(array_map(function ($sr) {
	return array(
		'id'   => (int) $sr->getIdSubRazdel(),
		'name' => $sr->getNameSubRazdelText(),
	);
}, $sub_razdels_all), JSON_UNESCAPED_UNICODE) . ';
</script>
<script src="/bb/assets/js/category_picker.js?v=2"></script>
<script src="/bb/assets/js/producer_picker.js?v=1"></script>
';
```

на:

```php
<script src="/bb/assets/js/live_picker.js?v=3"></script>
<script>
// Подразделы отдаём прямо в страницу: их 30, отдельный запрос не нужен.
window.SUB_RAZDELS = ' . json_encode(array_map(function ($sr) {
	return array(
		'id'   => (int) $sr->getIdSubRazdel(),
		'name' => $sr->getNameSubRazdelText(),
	);
}, $sub_razdels_all), JSON_UNESCAPED_UNICODE) . ';
window.TOVAR_MOD_INITIAL_TAB = ' . json_encode($action === 'редактировать' ? 'edit' : 'new') . ';
</script>
<script src="/bb/assets/js/category_picker.js?v=3"></script>
<script src="/bb/assets/js/producer_picker.js?v=2"></script>
<script src="/bb/assets/js/model_picker.js?v=1"></script>
';
```

(Версии `?v=` у изменённых файлов подняты, чтобы браузер не отдавал старую версию из кэша.)

- [ ] **Step 6: Обновить PHP-обработку сохранения/обновления под новое имя поля**

В `case 'сохранить'` заменить:

```php
			//определяем наименование модели
			if ($model_select_new != '0') {
				$model_name = $model_select_new;
			} else {
				$model_name = $model_input_new;
			}
```

на:

```php
			//определяем наименование модели — приходит из живого поиска (bb/assets/js/model_picker.js)
			$model_name = trim($model_new);
			if ($model_name === '') {
				die('Модель не указана. Введите название в поле «Модель».');
			}
```

В `case 'обновить'` заменить:

```php
			$model_select_new == '0' ? $model_name = $model_input_new : $model_name = $model_select_new;
```

на:

```php
			$model_name = trim($model_new);
			if ($model_name === '') {
				die('Модель не указана. Введите название в поле «Модель».');
			}
```

- [ ] **Step 7: Обновить клиентскую валидацию**

В `send_form_ch()` заменить:

```php
		if (document.getElementById('model_select_new').value == "0" && document.getElementById('model_input_new').value == "") {
			model_chcc = "Модель, ";
			valid = false;
		}
```

на:

```php
		if (document.getElementById('model_new').value.trim() == "") {
			model_chcc = "Модель, ";
			valid = false;
		}
```

Также удалить больше не нужную функцию `select_ch2()` (использовалась только парой
`model_select_new`/`model_input_new`) — найти и удалить блок:

```php
	function select_ch2(sel, new_f) {

		if (document.getElementById(sel).value == 0) {
			document.getElementById(new_f).disabled = false;
		}
		else {
			document.getElementById(new_f).disabled = true;
			document.getElementById(new_f).value = '';
		}

	}
```

- [ ] **Step 8: Синтаксическая проверка**

Run: `php -l bb/tovar_new_mod.php`
Expected: `No syntax errors detected in bb/tovar_new_mod.php`

- [ ] **Step 9: Смоук-тест сохранения новой модели**

```bash
cat > /tmp/smoke_save.php <<'PHP'
<?php
session_start();
$_SESSION['svoi'] = 8941;
$_SESSION['level'] = 7;
$_SESSION['user_fio'] = 'phpunit-smoke';

// Взять реальную категорию из базы для теста.
require_once '/var/www/html/bb/Db.php';
$mysqli = \bb\Db::getInstance()->getConnection();
$cat = $mysqli->query('SELECT tovar_rent_cat_id FROM tovar_rent_cat LIMIT 1')->fetch_assoc();

$_POST = [
	'action'              => 'сохранить',
	'cat_select_new'      => $cat['tovar_rent_cat_id'],
	'cat_input_dog_new'   => 'тест-договор',
	'producer_select_new' => '__phpunit_smoke_producer__',
	'model_new'           => '__phpunit_smoke_model__',
	'color_new'           => 'красный',
	'm_set_new'           => 'база',
	'm_price_new'         => '100',
	'm_price_cur_new'     => 'USD',
	'price_new_new'       => '300',
	'lom_srok_new'        => '3',
	'age_from'            => '0',
	'age_to'              => '36',
	'weight_from'         => '0',
	'weight_to'           => '15',
	'm_sex'               => '0',
	'collateral'          => '0',
	'ny'                  => '0',
	'zv'                  => '0',
	'tale'                => '0',
	'rez1'                => '0',
	'rez2'                => '0',
];

ob_start();
include '/var/www/html/bb/tovar_new_mod.php';
$out = ob_get_clean();

echo (strpos($out, 'Модель успешно заведена') !== false) ? "OK: модель создана\n" : "FAIL: " . substr($out, 0, 300) . "\n";

$check = $mysqli->query("SELECT tovar_rent_id, model, producer FROM tovar_rent WHERE model='__phpunit_smoke_model__' AND producer='__phpunit_smoke_producer__'");
echo 'rows in DB: ' . $check->num_rows . "\n";

// уборка
$mysqli->query("DELETE FROM tovar_rent WHERE model='__phpunit_smoke_model__' AND producer='__phpunit_smoke_producer__'");
PHP
docker compose cp /tmp/smoke_save.php app:/tmp/smoke_save.php
docker compose exec -T app php /tmp/smoke_save.php
```

Expected: `OK: модель создана`, `rows in DB: 1`.

Удалить временный файл: `rm /tmp/smoke_save.php`

- [ ] **Step 10: Коммит**

```bash
git add bb/tovar_new_mod.php bb/assets/styles/category_picker.css
git commit -m "$(cat <<'EOF'
feat(bb): tovar_new_mod.php — вкладки создание/редактирование, живой поиск модели

Поле «Модель» — живой поиск (model_picker.js) вместо select на ~1800
имён. Вкладки формализуют существующий, но недоступный из UI
action=обновить: раньше редактирование модели открывалось только по
прямой ссылке с готовым model_id, теперь — ещё и поиском с нуля.
PHP-обработка сохранения/обновления читает единое поле model_new вместо
пары model_select_new/model_input_new.
EOF
)"
```

---

### Task 7: `docs/prod_pending.md` — обновление + сквозная проверка в браузере

**Files:**
- Modify: `docs/prod_pending.md`

- [ ] **Step 1: Обновить описание ветки №3 в очереди**

В `docs/prod_pending.md`, в таблице «Очередь веток», строку

```
| 3 | `feature/producers-directory` (в работе) | Справочник производителей: таблица `producers`, живой поиск и попапы на страницах модели и товара, логотип бренда одной строкой | 1 и 2 |
```

заменить на:

```
| 3 | `feature/producers-directory` (в работе) | Справочник производителей: таблица `producers`, живой поиск и попапы на страницах модели и товара, логотип бренда одной строкой; живой поиск модели с каскадом категория/фирма и вкладками создание/редактирование на tovar_new_mod.php | 1 и 2 |
```

- [ ] **Step 2: Добавить пункт в «После заливки»**

В раздел «Проверить глазами» добавить пункт:

```
- `tovar_new_mod.php` — обе вкладки («Новая модель» / «Редактировать действующую»): живой
  поиск категории/фирмы/модели, жёсткий выбор категории+фирмы при клике на подсказку модели,
  на вкладке редактирования — подтягиваются все поля модели.
```

- [ ] **Step 3: Коммит**

```bash
git add docs/prod_pending.md
git commit -m "docs: prod_pending — добавить каскад модели в описание ветки producers-directory"
```

- [ ] **Step 4: Сквозная ручная проверка в браузере (обязательно перед завершением)**

Открыть `http://localhost/bb/tovar_new_mod.php` в браузере (Docker/Laragon уже поднят) и
руками пройти:

1. Вкладка «Новая модель» активна по умолчанию (пустая форма).
2. Клик в поле категории на пустом поле — открывается полный список категорий, можно
   прокрутить.
3. Ввести буквы в поле фирмы, не выбирая вариант, кликнуть в другое поле — текст должен
   вернуться к пустому (был не выбран ничего).
4. Выбрать категорию, затем в поле модели — должен подгружаться список моделей ИМЕННО этой
   категории (сверить с `kr_baza_new.php` для той же категории).
5. Напечатать точное существующее имя модели в выбранной категории+фирме — должна появиться
   подсказка «Такая модель уже есть» со ссылкой «редактировать».
6. Кликнуть «редактировать» в подсказке — переключается вкладка «Редактировать действующую»,
   в поле модели остаётся то же имя, после выбора конкретного цвета из подсказки —
   заполняются категория, фирма и все остальные поля формы.
7. На вкладке «Редактировать действующую» кнопка сохранения показывает «обновить».
8. Открыть страницу по прямой ссылке с `model_id` (через «редактировать модель» на
   `kr_baza_new.php`) — вкладка «Редактировать» выбрана сразу, поиск не требуется.

Если что-то из этого не работает — доработать перед тем, как считать задачу завершённой,
дописав недостающие шаги в этот план или отдельным коммитом с фиксом.

---

## Self-Review (выполнено автором плана)

**Покрытие спеки:** три доработки core-виджета (рассинхрон, `onItems`) — Task 1; полный
список по фокусу для категории/фирмы — Tasks 2–3; новый эндпоинт модели — Task 4; два режима
поля модели (группировка по имени / полная гранулярность, жёсткий выбор, поле «уже
есть»-подсказка) — Task 5; вкладки, разметка, PHP-обвязка, клиентская валидация — Task 6;
запись в `prod_pending.md` — Task 7. Все пункты раздела «Решение» спеки закрыты.

**Плейсхолдеры:** не найдены — единственное место с `ЗНАЧЕНИЕ_ИЗ_STEP_2` (Task 4, Step 4) —
явно помечено как «подставить перед запуском», это смоук-скрипт, а не код продукта.

**Согласованность типов/имён:** `LivePicker.resolveBlurAction`/`syncOnBlur`/`onItems` —
имена одинаковы в Task 1 (реализация) и Task 5 (использование). Контракт
`ajax_model_suggest.php` (Task 4) — те же ключи объекта, что в `EDIT_FIELD_MAP` и
`renderMeta`/`fillEditFields` (Task 5). DOM id, ожидаемые `model_picker.js` (Task 5,
секция Interfaces), — все заведены в разметке Task 6 (`model_search`, `model_new`,
`model_results`, `model_hint`, `model_id`, `tab_new`, `tab_edit`, `submit_btn`,
`collateral_new`, `ny_new`, `zv_new`, `tale_new`, `rez1_new`, `rez2_new`).
