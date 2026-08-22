# Вкладка «Редактировать действующую» — двухфазный режим (locate/unlocked) — план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** На вкладке «Редактировать действующую» (`bb/tovar_new_mod.php`) ввести два под-состояния — `locate` (поиск/предпросмотр, поля заблокированы, категория/фирма жёстко сужают друг друга, создать новую категорию/фирму нельзя) и `unlocked` (после явного клика «Внести изменения» — поля редактируемы, категория/фирма без ограничений, доступна живая проверка на дубликат, есть «Сохранить»/«Отмена»).

**Architecture:** Вся логика состояния — в `bb/assets/js/model_picker.js` (единая точка правды: чистая функция `resolveEditPhaseUI()` вычисляет, что показать/скрыть/заблокировать, `applyPhaseUI()` применяет это к DOM). Бэкенд получает необязательный флаг `filter=1` на двух существующих эндпоинтах подсказок (жёсткая фильтрация вместо мягкой сортировки) — без флага поведение не меняется, `tovar_new.php` не задет вообще (использует свои файлы, эти виджеты не подключает). Разметка `tovar_new_mod.php` заранее (на сервере) рендерит состояние `locate`, если открыта прямая ссылка с готовым `model_id` — чтобы не было мигания незаблокированных полей до отработки JS.

**Tech Stack:** Ванильный JS (без фреймворков и сборки — как весь `bb/`), процедурный PHP без autoload (`require_once`), MariaDB через `mysqli`.

## Global Constraints

- Уровень доступа новых/трогаемых эндпоинтов не меняется: `$in_level = array(0, 5, 7)` — копируется из уже существующих `bb/ajax_*_suggest.php`, ничего нового здесь не создаётся.
- Экранирование: `(int)` для числовых параметров, `$mysqli->real_escape_string()` для строковых — как во всех существующих `bb/ajax_*.php`.
- JSON-ответы (там, где меняются) — `json_encode(..., JSON_UNESCAPED_UNICODE)`; в этом плане PHP-эндпоинты не меняют формат ответа, только фильтруют список — эта константа уже стоит в обоих файлах, трогать не нужно.
- JS-тестов через фреймворк нет (`jest`/`mocha` не установлены) — автономные Node-скрипты через встроенный `assert`, с `global.window = {}`/`global.document = {...}` шимом, как `live_picker.test.js`/`model_picker.test.js`/`model_picker_init.test.js`. Новые проверки добавляются В ЭТИ ЖЕ файлы, не в новые.
- Цвета фаз (см. `docs/superpowers/specs/2026-08-16-tovar-new-mod-edit-lock-design.md`, раздел «Визуальная индикация»): New — `#f2f8ff`, Edit/locate — `#fff6da`, Edit/unlocked — `#fff0c2`, баннер режима редактирования — фон `#f5b400`, текст `#4a3800`.
- Новые id в разметке: `model_edit_start` (кнопка «Внести изменения»), `model_edit_cancel` (кнопка «Отмена»), `edit_phase_banner` (баннер режима редактирования). Существующие переиспользуются без переименования: `new_model_div`, `submit_btn`, `cat_create_open`, `prod_create_open`, `prod_edit_open`, `color_new`, все 19 id из `EDIT_FIELD_MAP` (`model_picker.js`).
- Кэш-бастинг: любой файл, который правит задача, должен получить бампнутый `?v=N` там, где он подключается в `tovar_new_mod.php` — это отдельный шаг в задаче 7, не полагаемся на память по ходу работы (в прошлой итерации именно это было забыто и поймано только адверсариал-ревью).
- `tovar_new.php` не подключает ни `category_picker.js`, ни `producer_picker.js`, ни `model_picker.js` (только свой `live_picker.js?v=3` и `category_picker.css?v=2` с собственным номером версии) — версию там бампать не нужно, задачи 3-7 его не касаются.
- Работа — локально на ветке `feature/producers-directory`, прод не трогаем (см. `docs/prod_pending.md`).

---

### Task 1: `ajax_producer_suggest.php` — жёсткий фильтр по категории

**Files:**
- Modify: `bb/ajax_producer_suggest.php`
- Test: разовый скрипт `/tmp/test_producer_filter.php` внутри контейнера (не коммитится)

**Interfaces:**
- Produces: query-параметр `filter=1` (в сочетании с уже существующим `cat_id=`) — при истинном значении список `items` содержит ТОЛЬКО производителей, у которых есть хотя бы одна строка `tovar_rent` с этим `tovar_rent_cat_id`. Без `filter=1` (или без `cat_id`) — поведение как сегодня (мягкая сортировка, полный список).

- [ ] **Step 1: Написать смоук-тест (RED) — с `filter=1` результат сегодня НЕ отличается от результата без него**

Скопировать в контейнер и запустить:

```bash
cat > /tmp/test_producer_filter.php << 'PHPEOF'
<?php
session_start();
$_SESSION['svoi'] = 8941;
$_SESSION['level'] = 7;

require_once('/var/www/html/bb/Db.php');
$mysqli = \bb\Db::getInstance()->getConnection();

// Категория с небольшим, но реальным набором производителей.
$catRow = $mysqli->query(
    'SELECT tovar_rent_cat_id, COUNT(DISTINCT producer) c FROM tovar_rent GROUP BY tovar_rent_cat_id HAVING c BETWEEN 2 AND 15 LIMIT 1'
)->fetch_assoc();
$catId = (int) $catRow['tovar_rent_cat_id'];

$expected = [];
$r = $mysqli->query("SELECT DISTINCT producer FROM tovar_rent WHERE tovar_rent_cat_id=$catId");
while ($row = $r->fetch_assoc()) {
    $expected[$row['producer']] = true;
}
echo "cat_id=$catId, ожидается производителей: " . count($expected) . "\n";

function callEndpoint($params) {
    $_REQUEST = $params;
    $_GET = $params;
    ob_start();
    include '/var/www/html/bb/ajax_producer_suggest.php';
    return json_decode(ob_get_clean(), true);
}

$withoutFilter = callEndpoint(['cat_id' => $catId]);
$withFilter    = callEndpoint(['cat_id' => $catId, 'filter' => 1]);

echo "без filter=1: " . count($withoutFilter['items']) . " записей\n";
echo "с filter=1:   " . count($withFilter['items']) . " записей\n";

if (count($withFilter['items']) === count($withoutFilter['items'])) {
    echo "RED OK: filter=1 пока ничего не меняет (ожидаемо, ещё не реализовано)\n";
} else {
    echo "НЕОЖИДАННО: filter=1 уже что-то фильтрует — проверьте, не реализовано ли уже\n";
    exit(1);
}
PHPEOF
docker compose cp /tmp/test_producer_filter.php tiktakby-app:/tmp/test_producer_filter.php
docker compose exec -T app php /tmp/test_producer_filter.php
```

Expected: `RED OK: filter=1 пока ничего не меняет`.

- [ ] **Step 2: Реализовать фильтр**

В `bb/ajax_producer_suggest.php` найти:

```php
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
```

Заменить на:

```php
$query = trim($_REQUEST['q'] ?? '');
$check = !empty($_REQUEST['check']);
$catId = (int) ($_REQUEST['cat_id'] ?? 0);
$filter = !empty($_REQUEST['filter']);

$usedInCat = [];
if ($catId > 0) {
    $result = $mysqli->query("SELECT DISTINCT producer FROM tovar_rent WHERE tovar_rent_cat_id=$catId");
    while ($row = $result->fetch_assoc()) {
        $usedInCat[$row['producer']] = true;
    }
}

$active = \bb\classes\Producer::getAllActive();

// Жёсткий фильтр (tovar_new_mod.php, вкладка «Редактировать», фаза locate):
// только производители, у которых реально есть модели в категории $catId.
// Без &filter=1 ничего не меняется — мягкая сортировка по $usedInCat ниже
// как работала, так и работает (её использует tovar_new.php).
if ($catId > 0 && $filter) {
    $active = array_values(array_filter($active, function ($p) use ($usedInCat) {
        return isset($usedInCat[$p->getName()]);
    }));
}
```

Также обновить докблок в начале файла (после строки `&cat_id=<id> — бренды, уже встречавшиеся в этой категории, идут первыми.`) — добавить:

```
 *   &cat_id=<id>&filter=1      — то же самое, но жёстко: только те, у кого
 *                                реально есть модели в этой категории.
```

- [ ] **Step 3: Запустить смоук-тест снова (GREEN)**

```bash
docker compose cp bb/ajax_producer_suggest.php tiktakby-app:/var/www/html/bb/ajax_producer_suggest.php
docker compose exec -T app php /tmp/test_producer_filter.php
```

Добавить в конец `/tmp/test_producer_filter.php` (перед финальным echo) проверку состава:

```php
$names = array_column($withFilter['items'], 'name');
$extra = array_diff($names, array_keys($expected));
$missing = array_diff(array_keys($expected), $names);
if ($extra) {
    echo "FAIL: лишние в ответе: " . implode(', ', $extra) . "\n";
    exit(1);
}
if ($missing) {
    echo "FAIL: отсутствуют в ответе: " . implode(', ', $missing) . "\n";
    exit(1);
}
if (count($withFilter['items']) >= count($withoutFilter['items'])) {
    echo "FAIL: filter=1 должен был сузить список\n";
    exit(1);
}
echo "GREEN OK: filter=1 возвращает ровно производителей категории $catId, список короче полного\n";
```

Перезалить файл и перезапустить:

```bash
docker compose cp /tmp/test_producer_filter.php tiktakby-app:/tmp/test_producer_filter.php
docker compose exec -T app php /tmp/test_producer_filter.php
```

Expected: `GREEN OK: filter=1 возвращает ровно производителей категории N, список короче полного`.

- [ ] **Step 4: Регрессия — без `filter=1` результат побайтово тот же, что и до правки**

```bash
docker compose exec -T app php -r '
session_start();
$_SESSION["svoi"] = 8941; $_SESSION["level"] = 7;
$_REQUEST = $_GET = [];
ob_start();
include "/var/www/html/bb/ajax_producer_suggest.php";
$out = ob_get_clean();
$data = json_decode($out, true);
echo "пустой запрос без параметров: " . count($data["items"]) . " записей (ожидается полный активный список)\n";
'
```

Ожидается то же число, что было в этой странице до правки (полный список активных производителей — без `cat_id` фильтр даже не участвует).

- [ ] **Step 5: Commit**

```bash
git add bb/ajax_producer_suggest.php
git commit -m "$(cat <<'EOF'
feat(bb): ajax_producer_suggest — необязательный жёсткий фильтр filter=1

Без флага поведение не меняется (используется tovar_new.php и мягкая
сортировка на вкладке «Новая модель»). С флагом — только производители,
у которых реально есть модели в переданной категории; нужен для фазы
locate на вкладке «Редактировать действующую».
EOF
)"
```

---

### Task 2: `ajax_category_suggest.php` — жёсткий фильтр по производителю

**Files:**
- Modify: `bb/ajax_category_suggest.php`
- Test: разовый скрипт `/tmp/test_category_filter.php` внутри контейнера (не коммитится)

**Interfaces:**
- Produces: новый query-параметр `producer=<имя>` + `filter=1` — список `items` содержит ТОЛЬКО категории, в которых есть хотя бы одна строка `tovar_rent` с этим `producer`. Без `producer=`/`filter=1` — поведение как сегодня.

- [ ] **Step 1: Написать смоук-тест (RED)**

```bash
cat > /tmp/test_category_filter.php << 'PHPEOF'
<?php
session_start();
$_SESSION['svoi'] = 8941;
$_SESSION['level'] = 7;

require_once('/var/www/html/bb/Db.php');
$mysqli = \bb\Db::getInstance()->getConnection();

// Производитель с небольшим, но реальным набором категорий.
$prodRow = $mysqli->query(
    'SELECT producer, COUNT(DISTINCT tovar_rent_cat_id) c FROM tovar_rent GROUP BY producer HAVING c BETWEEN 2 AND 15 LIMIT 1'
)->fetch_assoc();
$producer = $prodRow['producer'];

$expected = [];
$r = $mysqli->query("SELECT DISTINCT tovar_rent_cat_id FROM tovar_rent WHERE producer='" . $mysqli->real_escape_string($producer) . "'");
while ($row = $r->fetch_assoc()) {
    $expected[(int) $row['tovar_rent_cat_id']] = true;
}
echo "producer=$producer, ожидается категорий: " . count($expected) . "\n";

function callEndpoint($params) {
    $_REQUEST = $params;
    $_GET = $params;
    ob_start();
    include '/var/www/html/bb/ajax_category_suggest.php';
    return json_decode(ob_get_clean(), true);
}

$withoutFilter = callEndpoint(['producer' => $producer]);
$withFilter    = callEndpoint(['producer' => $producer, 'filter' => 1]);

echo "без filter=1: " . count($withoutFilter['items']) . " записей\n";
echo "с filter=1:   " . count($withFilter['items']) . " записей\n";

if (count($withFilter['items']) === count($withoutFilter['items'])) {
    echo "RED OK: filter=1 пока ничего не меняет (ожидаемо)\n";
} else {
    echo "НЕОЖИДАННО: filter=1 уже что-то фильтрует\n";
    exit(1);
}
PHPEOF
docker compose cp /tmp/test_category_filter.php tiktakby-app:/tmp/test_category_filter.php
docker compose exec -T app php /tmp/test_category_filter.php
```

Expected: `RED OK: filter=1 пока ничего не меняет`.

- [ ] **Step 2: Реализовать фильтр**

В `bb/ajax_category_suggest.php` найти:

```php
$query = trim($_REQUEST['q'] ?? '');
$check = !empty($_REQUEST['check']);
```

Заменить на:

```php
$query = trim($_REQUEST['q'] ?? '');
$check = !empty($_REQUEST['check']);
$producer = trim($_REQUEST['producer'] ?? '');
$filter = !empty($_REQUEST['filter']);
```

Найти (сразу после цикла, которым строится `$all`, до `$response = ['items' => []];`):

```php
$all = [];
while ($row = $result->fetch_assoc()) {
    $row['id']     = (int) $row['id'];
    $row['models'] = (int) $row['models'];
    $row['in_tree'] = $row['tree_path'] !== null && $row['tree_path'] !== '';
    $all[] = $row;
}

$response = ['items' => []];
```

Заменить на:

```php
$all = [];
while ($row = $result->fetch_assoc()) {
    $row['id']     = (int) $row['id'];
    $row['models'] = (int) $row['models'];
    $row['in_tree'] = $row['tree_path'] !== null && $row['tree_path'] !== '';
    $all[] = $row;
}

// Жёсткий фильтр (tovar_new_mod.php, вкладка «Редактировать», фаза locate):
// только категории, в которых реально есть модели этого производителя. Без
// &filter=1 (или без producer=) поведение не меняется.
if ($producer !== '' && $filter) {
    $usedByProducer = [];
    $result2 = $mysqli->query(
        "SELECT DISTINCT tovar_rent_cat_id FROM tovar_rent WHERE producer='"
        . $mysqli->real_escape_string($producer) . "'"
    );
    while ($row2 = $result2->fetch_assoc()) {
        $usedByProducer[(int) $row2['tovar_rent_cat_id']] = true;
    }
    $all = array_values(array_filter($all, function ($row) use ($usedByProducer) {
        return isset($usedByProducer[$row['id']]);
    }));
}

$response = ['items' => []];
```

Также обновить докблок в начале файла — добавить после описания `q=<строка>&check=1`:

```
 *   q=<строка>&producer=<имя>&filter=1 — плюс жёсткий фильтр: только
 *                           категории, где реально есть модели этого
 *                           производителя.
```

- [ ] **Step 3: Запустить смоук-тест снова (GREEN)**

Добавить в конец `/tmp/test_category_filter.php` (перед финальным echo):

```php
$ids = array_column($withFilter['items'], 'id');
$extra = array_diff($ids, array_keys($expected));
$missing = array_diff(array_keys($expected), $ids);
if ($extra) {
    echo "FAIL: лишние id в ответе: " . implode(', ', $extra) . "\n";
    exit(1);
}
if ($missing) {
    echo "FAIL: отсутствуют id: " . implode(', ', $missing) . "\n";
    exit(1);
}
if (count($withFilter['items']) >= count($withoutFilter['items'])) {
    echo "FAIL: filter=1 должен был сузить список\n";
    exit(1);
}
echo "GREEN OK: filter=1 возвращает ровно категории producer=$producer, список короче полного\n";
```

```bash
docker compose cp bb/ajax_category_suggest.php tiktakby-app:/var/www/html/bb/ajax_category_suggest.php
docker compose cp /tmp/test_category_filter.php tiktakby-app:/tmp/test_category_filter.php
docker compose exec -T app php /tmp/test_category_filter.php
```

Expected: `GREEN OK: ...`.

- [ ] **Step 4: Регрессия — без `producer=`/`filter=1` результат тот же, что и до правки**

```bash
docker compose exec -T app php -r '
session_start();
$_SESSION["svoi"] = 8941; $_SESSION["level"] = 7;
$_REQUEST = $_GET = [];
ob_start();
include "/var/www/html/bb/ajax_category_suggest.php";
$out = ob_get_clean();
$data = json_decode($out, true);
echo "пустой запрос: " . count($data["items"]) . " категорий (ожидается полный список ~117)\n";
'
```

- [ ] **Step 5: Commit**

```bash
git add bb/ajax_category_suggest.php
git commit -m "$(cat <<'EOF'
feat(bb): ajax_category_suggest — необязательный жёсткий фильтр producer+filter=1

Без параметров поведение не меняется. С producer=<имя>&filter=1 —
только категории, в которых реально есть модели этого производителя;
пара к Task 1 (ajax_producer_suggest), обе стороны каскада для фазы
locate на вкладке «Редактировать действующую».
EOF
)"
```

---

### Task 3: `category_picker.js` / `producer_picker.js` — включение фильтра и кросс-хуки

**Files:**
- Modify: `bb/assets/js/category_picker.js`
- Modify: `bb/assets/js/producer_picker.js`

**Interfaces:**
- Consumes: `window.TOVAR_MOD_LOCATE_FILTER` (bool, будет выставляться в Task 4 из `model_picker.js`; на страницах без него — `undefined`, что ведёт себя как `false`).
- Produces: `window.__onCategoryChosen` / `window.__onProducerChosen` — необязательные глобальные колбэки без аргументов; если определены (Task 5), вызываются сразу после `onChoose` соответствующего пикера.

- [ ] **Step 1: `producer_picker.js` — добавить `filter=1` к `extraParams` и вызов хука в `onChoose`**

Найти:

```js
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
```

Заменить на:

```js
			extraParams: function () {
				var catField = $('cat_select_new');
				var params = catField && catField.value ? { cat_id: catField.value } : {};
				if (window.TOVAR_MOD_LOCATE_FILTER) {
					params.filter = 1;
				}
				return params;
			},
			renderMeta: function (item) {
				return item.hidden ? 'скрыт' : '';
			},
			onChoose: function () {
				toggleEditButton();
				if (window.__onProducerChosen) {
					window.__onProducerChosen();
				}
			}
```

- [ ] **Step 2: `category_picker.js` — добавить `extraParams` (сегодня его нет) и вызов хука в `onChoose`**

Найти:

```js
		picker = new window.LivePicker({
			inputId:   'cat_search',
			hiddenId:  'cat_select_new',
			resultsId: 'cat_results',
			chosenId:  'cat_chosen',
			url:       '/bb/ajax_category_suggest.php',
			minQuery:  0,
			renderMeta: function (item) {
				return (item.tree_path ? item.tree_path : 'вне дерева каталога')
					+ ' · моделей: ' + item.models;
			},
			onChoose: function (item) {
				if (els.dogName) {
					els.dogName.value = item.dog_name || '';
				}
			}
		});
```

Заменить на:

```js
		picker = new window.LivePicker({
			inputId:   'cat_search',
			hiddenId:  'cat_select_new',
			resultsId: 'cat_results',
			chosenId:  'cat_chosen',
			url:       '/bb/ajax_category_suggest.php',
			minQuery:  0,
			extraParams: function () {
				var prodField = $('producer_select_new');
				if (window.TOVAR_MOD_LOCATE_FILTER && prodField && prodField.value) {
					return { producer: prodField.value, filter: 1 };
				}
				return {};
			},
			renderMeta: function (item) {
				return (item.tree_path ? item.tree_path : 'вне дерева каталога')
					+ ' · моделей: ' + item.models;
			},
			onChoose: function (item) {
				if (els.dogName) {
					els.dogName.value = item.dog_name || '';
				}
				if (window.__onCategoryChosen) {
					window.__onCategoryChosen();
				}
			}
		});
```

- [ ] **Step 3: Проверка вручную (нет юнит-тестов на эти файлы — только чистые функции тестируются)**

```bash
node -e "
global.window = {};
global.document = { addEventListener: function(){}, readyState: 'loading', getElementById: function(){ return null; } };
require('./bb/assets/js/live_picker.js');
require('./bb/assets/js/category_picker.js');
require('./bb/assets/js/producer_picker.js');
console.log('OK: оба файла грузятся без ошибок синтаксиса/времени выполнения');
"
```

Expected: `OK: оба файла грузятся без ошибок синтаксиса/времени выполнения`.

- [ ] **Step 4: Commit**

```bash
git add bb/assets/js/category_picker.js bb/assets/js/producer_picker.js
git commit -m "$(cat <<'EOF'
feat(bb): category/producer_picker — жёсткий фильтр по window.TOVAR_MOD_LOCATE_FILTER

extraParams передаёт filter=1 (+ producer=/cat_id=) только когда флаг
включён — на страницах, где флага нет (tovar_new.php), поведение не
меняется. onChoose дополнительно зовёт window.__on{Category,Producer}Chosen,
если они определены — нужно для живой проверки на дубликат (Task 5).
EOF
)"
```

---

### Task 4: `model_picker.js` — состояние `editPhase` (locate/unlocked)

**Files:**
- Modify: `bb/assets/js/model_picker.js`
- Modify: `bb/assets/js/model_picker.test.js` (добавить тесты `resolveEditPhaseUI`)
- Modify: `bb/assets/js/model_picker_init.test.js` (добавить сценарии перехода фаз)

**Interfaces:**
- Consumes: `els.color` = `$('color_new')`, `EDIT_FIELD_MAP` (уже существует), `window.TOVAR_MOD_INITIAL_MODEL` (новый глобал из Task 7 — полная строка `tovar_rent` + `id`/`cat_id`/`cat_name`/`cat_dog_name`, либо `null`).
- Produces: `resolveEditPhaseUI(mode, editPhase, hasModel) -> {showEditStart, showSubmit, showCancel, fieldsDisabled, createControlsVisible, filterActive}` — чистая функция, экспортируется в `window.__modelPickerTestHooks.resolveEditPhaseUI` для тестов. `window.TOVAR_MOD_LOCATE_FILTER` (bool) — выставляется в `applyPhaseUI()`, читается `category_picker.js`/`producer_picker.js` (Task 3).

- [ ] **Step 1: Дописать в `model_picker.test.js` тесты на `resolveEditPhaseUI` (RED)**

В конец файла, перед `console.log('model_picker.test.js: OK (12 assertions)');`, добавить:

```js
// --- resolveEditPhaseUI: что показать/скрыть/заблокировать для режим+фаза ---
assert.deepStrictEqual(
	hooks.resolveEditPhaseUI('new', 'locate', false),
	{ showEditStart: false, showSubmit: true, showCancel: false, fieldsDisabled: false, createControlsVisible: true, filterActive: false },
	'вкладка «Новая модель» — всегда как сегодня, фаза не влияет'
);
assert.deepStrictEqual(
	hooks.resolveEditPhaseUI('edit', 'locate', false),
	{ showEditStart: false, showSubmit: false, showCancel: false, fieldsDisabled: true, createControlsVisible: false, filterActive: true },
	'locate без выбранной модели — всё заблокировано, кнопки «Внести изменения» ещё нет'
);
assert.deepStrictEqual(
	hooks.resolveEditPhaseUI('edit', 'locate', true),
	{ showEditStart: true, showSubmit: false, showCancel: false, fieldsDisabled: true, createControlsVisible: false, filterActive: true },
	'locate с выбранной моделью — предпросмотр, появляется «Внести изменения»'
);
assert.deepStrictEqual(
	hooks.resolveEditPhaseUI('edit', 'unlocked', true),
	{ showEditStart: false, showSubmit: true, showCancel: true, fieldsDisabled: false, createControlsVisible: true, filterActive: false },
	'unlocked — полное редактирование, «Сохранить»/«Отмена», фильтр выключен'
);

console.log('model_picker.test.js: OK (16 assertions)');
```

(Заменить старую последнюю строку `console.log(...(12 assertions))` на новую с `(16 assertions)` — учитывает 4 новых `deepStrictEqual`.)

Запустить, убедиться в падении по причине отсутствия функции:

```bash
node bb/assets/js/model_picker.test.js
```

Expected: `TypeError: hooks.resolveEditPhaseUI is not a function` (или похожее — функции ещё нет).

- [ ] **Step 2: Реализовать `resolveEditPhaseUI` и состояние `editPhase`/`currentEditItem` в `model_picker.js`**

Найти:

```js
	var els = {};
	var picker = null;
	var mode = CHECK.NEW;
	var pendingEditGroup = null;
```

Заменить на:

```js
	var els = {};
	var picker = null;
	var mode = CHECK.NEW;
	var pendingEditGroup = null;
	var editPhase = 'locate';
	var currentEditItem = null;

	var EDIT_PHASE_FIELD_IDS = Object.keys(EDIT_FIELD_MAP).map(function (key) {
		return EDIT_FIELD_MAP[key];
	}).concat(['color_multicolor_btn']);
```

Найти (сразу после `function currentFilterState(query) {...}`, перед `function showHintText(text) {`):

```js
	function showHintText(text) {
```

Вставить перед этой функцией:

```js
	/**
	 * Что показать/скрыть/заблокировать для комбинации режим+фаза. Чистая
	 * функция — без DOM, тестируется в model_picker.test.js. Единственная
	 * точка правды: applyPhaseUI() только переносит это на DOM, никакой
	 * своей логики видимости не содержит.
	 */
	function resolveEditPhaseUI(mode, editPhase, hasModel) {
		if (mode === CHECK.NEW) {
			return {
				showEditStart: false,
				showSubmit: true,
				showCancel: false,
				fieldsDisabled: false,
				createControlsVisible: true,
				filterActive: false
			};
		}

		if (editPhase === 'unlocked') {
			return {
				showEditStart: false,
				showSubmit: true,
				showCancel: true,
				fieldsDisabled: false,
				createControlsVisible: true,
				filterActive: false
			};
		}

		// editPhase === 'locate'
		return {
			showEditStart: hasModel,
			showSubmit: false,
			showCancel: false,
			fieldsDisabled: true,
			createControlsVisible: false,
			filterActive: true
		};
	}

	/** Переносит resolveEditPhaseUI() на реальный DOM. */
	function applyPhaseUI() {
		var ui = resolveEditPhaseUI(mode, editPhase, !!currentEditItem);

		EDIT_PHASE_FIELD_IDS.forEach(function (id) {
			var field = $(id);
			if (field) {
				field.disabled = ui.fieldsDisabled;
			}
		});

		if (els.editStartBtn) { els.editStartBtn.style.display = ui.showEditStart ? 'inline-block' : 'none'; }
		if (els.submitBtn)    { els.submitBtn.style.display = ui.showSubmit ? 'inline-block' : 'none'; }
		if (els.cancelBtn)    { els.cancelBtn.style.display = ui.showCancel ? 'inline-block' : 'none'; }
		if (els.catCreateBtn)  { els.catCreateBtn.style.display = ui.createControlsVisible ? 'inline-block' : 'none'; }
		if (els.prodCreateBtn) { els.prodCreateBtn.style.display = ui.createControlsVisible ? 'inline-block' : 'none'; }
		if (els.prodEditBtn) {
			// Дублирует условие producer_picker.js:toggleEditButton() одной
			// строкой — заводить общий API ради одной строки не стали;
			// порядок подключения скриптов (после producer_picker.js)
			// гарантирует, что здесь будет последнее слово.
			els.prodEditBtn.style.display = (ui.createControlsVisible && els.prod && els.prod.value) ? 'inline-block' : 'none';
		}

		window.TOVAR_MOD_LOCATE_FILTER = ui.filterActive;

		if (els.area) {
			els.area.classList.toggle('catp-phase--new', mode === CHECK.NEW);
			els.area.classList.toggle('catp-phase--locate', mode === CHECK.EDIT && editPhase === 'locate');
			els.area.classList.toggle('catp-phase--unlocked', mode === CHECK.EDIT && editPhase === 'unlocked');
		}

		if (els.phaseBanner) {
			var showBanner = mode === CHECK.EDIT && editPhase === 'unlocked';
			els.phaseBanner.style.display = showBanner ? 'block' : 'none';
			els.phaseBanner.textContent = showBanner
				? 'Режим редактирования — изменения сохранятся только по кнопке «Сохранить»'
				: '';
		}
	}

	function enterUnlocked() {
		editPhase = 'unlocked';
		applyPhaseUI();
	}

	/** Откатывает к состоянию сразу после выбора модели — как будто «Внести изменения» не нажималась. */
	function cancelEdit() {
		if (currentEditItem) {
			fillEditFields(currentEditItem);
			hardSelectCategoryAndProducer(currentEditItem);
		}
		editPhase = 'locate';
		hideHint();
		applyPhaseUI();
	}

```

- [ ] **Step 3: Подключить `applyPhaseUI`/`currentEditItem` к `setMode`, `onChoose`, `init`**

Найти:

```js
	function setMode(newMode, options) {
		var shouldReset = !options || options.reset !== false;

		mode = newMode;
		pendingEditGroup = null;
		hideHint();
```

Заменить на:

```js
	function setMode(newMode, options) {
		var shouldReset = !options || options.reset !== false;

		mode = newMode;
		editPhase = 'locate';
		pendingEditGroup = null;
		hideHint();

		if (shouldReset) {
			currentEditItem = null;
		}
```

Найти (конец `setMode`):

```js
		if (picker && shouldReset) {
			picker.reset();
		}
	}
```

Заменить на:

```js
		if (picker && shouldReset) {
			picker.reset();
		}

		applyPhaseUI();
	}
```

Найти:

```js
			onChoose: function (item) {
				hardSelectCategoryAndProducer(item);
				if (mode === CHECK.EDIT) {
					fillEditFields(item);
				}
				hideHint();
			}
```

Заменить на:

```js
			onChoose: function (item) {
				hardSelectCategoryAndProducer(item);
				if (mode === CHECK.EDIT) {
					currentEditItem = item;
					fillEditFields(item);
					applyPhaseUI();
				}
				hideHint();
			}
```

Найти:

```js
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
```

Заменить на:

```js
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
		els.editStartBtn  = $('model_edit_start');
		els.cancelBtn     = $('model_edit_cancel');
		els.phaseBanner   = $('edit_phase_banner');
		els.area          = $('new_model_div');
		els.catCreateBtn  = $('cat_create_open');
		els.prodCreateBtn = $('prod_create_open');
		els.prodEditBtn   = $('prod_edit_open');
		els.color         = $('color_new');

		if (!els.search || !els.hidden || !els.results || !window.LivePicker) {
			return;
		}

		if (window.TOVAR_MOD_INITIAL_MODEL) {
			currentEditItem = window.TOVAR_MOD_INITIAL_MODEL;
		}
```

Найти (в конце `init()`, после блока с `els.tabNew`/`els.tabEdit` addEventListener):

```js
		if (els.tabNew) {
			els.tabNew.addEventListener('click', function () { setMode(CHECK.NEW); });
		}
		if (els.tabEdit) {
			els.tabEdit.addEventListener('click', function () { setMode(CHECK.EDIT); });
		}

		setMode(window.TOVAR_MOD_INITIAL_TAB === 'edit' ? CHECK.EDIT : CHECK.NEW, { reset: false });
	}
```

Заменить на:

```js
		if (els.tabNew) {
			els.tabNew.addEventListener('click', function () { setMode(CHECK.NEW); });
		}
		if (els.tabEdit) {
			els.tabEdit.addEventListener('click', function () { setMode(CHECK.EDIT); });
		}
		if (els.editStartBtn) {
			els.editStartBtn.addEventListener('click', function () {
				if (mode === CHECK.EDIT && currentEditItem) {
					enterUnlocked();
				}
			});
		}
		if (els.cancelBtn) {
			els.cancelBtn.addEventListener('click', function () {
				cancelEdit();
			});
		}

		setMode(window.TOVAR_MOD_INITIAL_TAB === 'edit' ? CHECK.EDIT : CHECK.NEW, { reset: false });
	}
```

Найти:

```js
	window.__modelPickerTestHooks = { groupByName: groupByName, resolveHint: resolveHint };
```

Заменить на:

```js
	window.__modelPickerTestHooks = { groupByName: groupByName, resolveHint: resolveHint, resolveEditPhaseUI: resolveEditPhaseUI };
```

- [ ] **Step 4: Запустить тесты (GREEN)**

```bash
node bb/assets/js/model_picker.test.js
```

Expected: `model_picker.test.js: OK (16 assertions)`.

- [ ] **Step 5: Расширить `model_picker_init.test.js` — реальный DOM-стаб на переход фаз**

Найти:

```js
var STUB_IDS = [
	'model_search', 'model_new', 'model_results', 'model_hint',
	'cat_select_new', 'producer_select_new', 'model_id',
	'tab_new', 'tab_edit', 'submit_btn'
];
```

Заменить на:

```js
var STUB_IDS = [
	'model_search', 'model_new', 'model_results', 'model_hint',
	'cat_select_new', 'producer_select_new', 'model_id',
	'tab_new', 'tab_edit', 'submit_btn',
	'model_edit_start', 'model_edit_cancel', 'edit_phase_banner', 'new_model_div',
	'cat_create_open', 'prod_create_open', 'prod_edit_open',
	'color_new', 'm_set_new', 'color_multicolor_btn'
];
```

Найти:

```js
function loadPage(initialTab, overrides) {
	overrides = overrides || {};

	var elements = {};
	STUB_IDS.forEach(function (id) {
		elements[id] = makeElement(overrides[id]);
	});

	var docListeners = {};
	global.document = {
		readyState: 'loading',
		getElementById: function (id) {
			return elements.hasOwnProperty(id) ? elements[id] : null;
		},
		addEventListener: function (type, handler) {
			(docListeners[type] = docListeners[type] || []).push(handler);
		},
		createElement: function () { return makeElement(); },
		createTextNode: function (text) { return { textContent: text }; }
	};

	global.window = {};
	global.window.TOVAR_MOD_INITIAL_TAB = initialTab;

	delete require.cache[require.resolve(LIVE_PICKER_PATH)];
	delete require.cache[require.resolve(MODEL_PICKER_PATH)];
	require(LIVE_PICKER_PATH);
	require(MODEL_PICKER_PATH);

	// document.readyState === 'loading' => model_picker.js не вызывает init()
	// сразу при require (тот самый bootstrap-runs-at-require-time фикс, что и в
	// model_picker.test.js), а регистрирует его на DOMContentLoaded. Стаб-элементы
	// уже готовы — теперь можно «долистать» загрузку страницы вручную.
	(docListeners.DOMContentLoaded || []).forEach(function (fn) { fn(); });

	return elements;
}
```

Заменить на:

```js
function loadPage(initialTab, overrides, initialModel) {
	overrides = overrides || {};

	var elements = {};
	STUB_IDS.forEach(function (id) {
		elements[id] = makeElement(overrides[id]);
	});

	var docListeners = {};
	global.document = {
		readyState: 'loading',
		getElementById: function (id) {
			return elements.hasOwnProperty(id) ? elements[id] : null;
		},
		addEventListener: function (type, handler) {
			(docListeners[type] = docListeners[type] || []).push(handler);
		},
		createElement: function () { return makeElement(); },
		createTextNode: function (text) { return { textContent: text }; }
	};

	global.window = {};
	global.window.TOVAR_MOD_INITIAL_TAB = initialTab;
	global.window.TOVAR_MOD_INITIAL_MODEL = initialModel || null;

	delete require.cache[require.resolve(LIVE_PICKER_PATH)];
	delete require.cache[require.resolve(MODEL_PICKER_PATH)];
	require(LIVE_PICKER_PATH);
	require(MODEL_PICKER_PATH);

	// document.readyState === 'loading' => model_picker.js не вызывает init()
	// сразу при require (тот самый bootstrap-runs-at-require-time фикс, что и в
	// model_picker.test.js), а регистрирует его на DOMContentLoaded. Стаб-элементы
	// уже готовы — теперь можно «долистать» загрузку страницы вручную.
	(docListeners.DOMContentLoaded || []).forEach(function (fn) { fn(); });

	return elements;
}
```

Найти (перед `console.log('model_picker_init.test.js: OK (4 assertions)');`):

```js
console.log('model_picker_init.test.js: OK (4 assertions)');
```

Заменить на:

```js
// --- Сценарий 4: заход по прямой ссылке с готовым model_id -> сразу locate с найденной моделью ---
var initialModel = { id: 42, name: 'Fox', cat_id: 7, cat_name: 'Коляски', cat_dog_name: 'коляска', producer: 'Bugaboo', color: 'красный' };
var els4 = loadPage('edit', { model_search: 'Fox', model_new: 'Fox', color_new: 'красный' }, initialModel);
assert.strictEqual(els4.model_edit_start.style.display, 'inline-block', 'модель уже найдена деплинком -> «Внести изменения» видна сразу');
assert.strictEqual(els4.submit_btn.style.display, 'none', 'в locate сабмит скрыт, даже придя по прямой ссылке');
assert.strictEqual(els4.color_new.disabled, true, 'поля предпросмотра заблокированы в locate');

// --- Сценарий 5: клик «Внести изменения» -> unlocked, поля разблокированы ---
els4.model_edit_start.dispatchEvent('click');
assert.strictEqual(els4.color_new.disabled, false, 'после «Внести изменения» поле цвета редактируемо');
assert.strictEqual(els4.submit_btn.style.display, 'inline-block', 'в unlocked сабмит виден');
assert.strictEqual(els4.model_edit_cancel.style.display, 'inline-block', 'в unlocked видна «Отмена»');
assert.strictEqual(els4.cat_create_open.style.display, 'inline-block', 'в unlocked доступно «+ создать категорию»');

// --- Сценарий 6: «Отмена» откатывает правки и блокирует поля обратно ---
els4.color_new.value = 'зелёный';
els4.model_edit_cancel.dispatchEvent('click');
assert.strictEqual(els4.color_new.value, 'красный', '«Отмена» возвращает исходный цвет из снэпшота');
assert.strictEqual(els4.color_new.disabled, true, '«Отмена» блокирует поля обратно');
assert.strictEqual(els4.submit_btn.style.display, 'none', '«Отмена» снова прячет сабмит (обратно в locate)');
assert.strictEqual(els4.model_edit_start.style.display, 'inline-block', '«Отмена» возвращает «Внести изменения»');

console.log('model_picker_init.test.js: OK (13 assertions)');
```

- [ ] **Step 6: Запустить и убедиться, что все проходят**

```bash
node bb/assets/js/model_picker_init.test.js
```

Expected: `model_picker_init.test.js: OK (13 assertions)`.

- [ ] **Step 7: Commit**

```bash
git add bb/assets/js/model_picker.js bb/assets/js/model_picker.test.js bb/assets/js/model_picker_init.test.js
git commit -m "$(cat <<'EOF'
feat(bb): model_picker — состояние editPhase (locate/unlocked)

resolveEditPhaseUI() — единая чистая функция, решающая, что показать/
скрыть/заблокировать для режим+фаза; applyPhaseUI() переносит это на DOM.
«Внести изменения» отпирает поля и категорию/фирму (полный список,
кнопки создания), «Отмена» откатывает к снэпшоту, снятому в момент
выбора модели. Прямая ссылка с готовым model_id тоже стартует в locate
(currentEditItem сеется из window.TOVAR_MOD_INITIAL_MODEL, задача 7).
EOF
)"
```

---

### Task 5: `model_picker.js` — живая проверка на дубликат в unlocked-фазе

**Files:**
- Modify: `bb/assets/js/model_picker.js`
- Modify: `bb/assets/js/model_picker.test.js` (добавить тесты `findDuplicateMatch`)

**Interfaces:**
- Consumes: `resolveEditPhaseUI`/`editPhase`/`currentEditItem`/`els.color` (Task 4).
- Produces: `findDuplicateMatch(items, {name, color, excludeId}) -> item|null` — чистая функция, экспортируется в `window.__modelPickerTestHooks.findDuplicateMatch`.

- [ ] **Step 1: Дописать в `model_picker.test.js` тесты на `findDuplicateMatch` (RED)**

В конец файла, перед `console.log(...(16 assertions))`, добавить:

```js
// --- findDuplicateMatch: точный дубль (имя+цвет), кроме самой редактируемой записи ---
var dupRows = [
	{ id: 10, name: 'Fox', color: 'красный', producer: 'Bugaboo', cat_name: 'Коляски' },
	{ id: 11, name: 'Fox', color: 'синий', producer: 'Bugaboo', cat_name: 'Коляски' }
];
assert.strictEqual(
	hooks.findDuplicateMatch(dupRows, { name: 'Fox', color: 'красный', excludeId: 10 }),
	null,
	'совпадение только с самой редактируемой записью (id=10) — не дубль'
);
assert.strictEqual(
	hooks.findDuplicateMatch(dupRows, { name: 'Fox', color: 'красный', excludeId: 999 }),
	dupRows[0],
	'совпадение имя+цвет с ДРУГОЙ записью — дубль'
);
assert.strictEqual(
	hooks.findDuplicateMatch(dupRows, { name: 'Fox', color: 'зелёный', excludeId: 999 }),
	null,
	'то же имя, но другой цвет — не дубль (это и есть штатная штатная новая вариация)'
);
assert.strictEqual(
	hooks.findDuplicateMatch(dupRows, { name: 'Cameleon', color: 'красный', excludeId: 999 }),
	null,
	'другое имя — не дубль'
);

console.log('model_picker.test.js: OK (20 assertions)');
```

(Заменить предыдущую последнюю строку `console.log(...(16 assertions))`.)

```bash
node bb/assets/js/model_picker.test.js
```

Expected: `TypeError: hooks.findDuplicateMatch is not a function`.

- [ ] **Step 2: Реализовать `findDuplicateMatch` и подключить проверку в unlocked-фазе**

Найти (после `resolveEditPhaseUI`/`applyPhaseUI`/`enterUnlocked`/`cancelEdit`, перед `function showHintText(text) {`):

```js
	function showHintText(text) {
```

Вставить перед этой функцией:

```js
	/**
	 * Ищет среди подсказок точное совпадение по имени+цвету, кроме самой
	 * редактируемой записи. Используется только в unlocked-фазе — до этого
	 * подсказка «уже есть» показывает совсем другое (см. resolveHint).
	 */
	function findDuplicateMatch(items, params) {
		var needle = window.LivePicker.normalize(params.name);

		var matches = items.filter(function (item) {
			return window.LivePicker.normalize(item.name) === needle
				&& item.color === params.color
				&& item.id !== params.excludeId;
		});

		return matches.length ? matches[0] : null;
	}

	/** Только unlocked-фаза: предупреждает, если правки сольются с ДРУГОЙ записью. */
	function checkDuplicateAndWarn(items, query) {
		var name = query.trim();
		if (name === '') {
			hideHint();
			return;
		}

		var excludeId = currentEditItem ? currentEditItem.id : null;
		var match = findDuplicateMatch(items, {
			name: name,
			color: els.color ? els.color.value : '',
			excludeId: excludeId
		});

		if (match) {
			showHintText('Эта комбинация совпадает с моделью #' + match.id
				+ ' (' + match.producer + ' · ' + match.cat_name + ') — сохранение объединит записи');
			return;
		}

		hideHint();
	}

```

Найти:

```js
			onItems: function (items, query) {
				updateHint(items, query);
				return mode === CHECK.NEW ? groupByName(items) : items;
			},
```

Заменить на:

```js
			onItems: function (items, query) {
				if (mode === CHECK.EDIT && editPhase === 'unlocked') {
					checkDuplicateAndWarn(items, query);
					return [];
				}
				updateHint(items, query);
				return mode === CHECK.NEW ? groupByName(items) : items;
			},
```

Найти (в `init()`, после установки `els.color = $('color_new');` — добавленной в Task 4):

```js
		els.color         = $('color_new');
```

Заменить на:

```js
		els.color         = $('color_new');
		if (els.color) {
			els.color.addEventListener('input', function () {
				if (mode === CHECK.EDIT && editPhase === 'unlocked') {
					picker.search();
				}
			});
		}
```

Найти (в `init()`, рядом с обработчиками кнопок «Внести изменения»/«Отмена», добавленными в Task 4 — вставить ПОСЛЕ них, всё ещё внутри `init()`):

```js
		setMode(window.TOVAR_MOD_INITIAL_TAB === 'edit' ? CHECK.EDIT : CHECK.NEW, { reset: false });
	}
```

Заменить на:

```js
		window.__onCategoryChosen = function () {
			if (mode === CHECK.EDIT && editPhase === 'unlocked') {
				picker.search();
			}
		};
		window.__onProducerChosen = function () {
			if (mode === CHECK.EDIT && editPhase === 'unlocked') {
				picker.search();
			}
		};

		setMode(window.TOVAR_MOD_INITIAL_TAB === 'edit' ? CHECK.EDIT : CHECK.NEW, { reset: false });
	}
```

Найти:

```js
	window.__modelPickerTestHooks = { groupByName: groupByName, resolveHint: resolveHint, resolveEditPhaseUI: resolveEditPhaseUI };
```

Заменить на:

```js
	window.__modelPickerTestHooks = { groupByName: groupByName, resolveHint: resolveHint, resolveEditPhaseUI: resolveEditPhaseUI, findDuplicateMatch: findDuplicateMatch };
```

- [ ] **Step 3: Запустить тесты (GREEN)**

```bash
node bb/assets/js/model_picker.test.js
node bb/assets/js/model_picker_init.test.js
```

Expected: `model_picker.test.js: OK (20 assertions)` и `model_picker_init.test.js: OK (13 assertions)` (второй файл не менялся в этой задаче, но должен остаться зелёным — регрессия).

- [ ] **Step 4: Commit**

```bash
git add bb/assets/js/model_picker.js bb/assets/js/model_picker.test.js
git commit -m "$(cat <<'EOF'
feat(bb): model_picker — живая проверка на дубликат в unlocked-фазе

findDuplicateMatch() — точное совпадение имя+цвет среди подсказок,
исключая саму редактируемую запись по id. Триггеры: ввод в «Модель»
(существующий путь через onItems), изменение «Цвет», выбор новой
категории/фирмы (window.__on{Category,Producer}Chosen из Task 3).
Не блокирует сохранение — только предупреждает, тем же принципом, что
и подсказка о дубле на вкладке «Новая модель».
EOF
)"
```

---

### Task 6: CSS — фон по вкладке/фазе, баннер режима, кнопки

**Files:**
- Modify: `bb/assets/styles/category_picker.css`

**Interfaces:**
- Consumes: классы `catp-phase--new` / `catp-phase--locate` / `catp-phase--unlocked` на `#new_model_div` (Task 4), id `edit_phase_banner`/`model_edit_start`/`model_edit_cancel`/`submit_btn` (Task 4/7).

- [ ] **Step 1: Добавить правила в конец `bb/assets/styles/category_picker.css`**

```css

/* ------------------------------------------------------- фазы вкладки-редактирования */

#new_model_div.catp-phase--new {
    background: #f2f8ff;
    padding: 10px;
    border-radius: 4px;
}

#new_model_div.catp-phase--locate {
    background: #fff6da;
    padding: 10px;
    border-radius: 4px;
}

#new_model_div.catp-phase--unlocked {
    background: #fff0c2;
    padding: 10px;
    border-radius: 4px;
}

.catp-phase-banner {
    margin-bottom: 12px;
    padding: 8px 10px;
    background: #f5b400;
    color: #4a3800;
    font-weight: bold;
    border-radius: 3px;
}

#model_edit_start,
#submit_btn {
    background: #2a7a2a;
    color: #fff;
    border: none;
    padding: 7px 14px;
    border-radius: 3px;
    cursor: pointer;
}

#model_edit_cancel {
    background: #eef1f5;
    color: #555;
    border: 1px solid #b5b5b5;
    padding: 7px 14px;
    border-radius: 3px;
    cursor: pointer;
    margin-left: 6px;
}
```

- [ ] **Step 2: Проверка (без браузера — только присутствие правил и синтаксическая валидность)**

```bash
grep -c "catp-phase--new\|catp-phase--locate\|catp-phase--unlocked\|catp-phase-banner\|model_edit_start\|model_edit_cancel" bb/assets/styles/category_picker.css
```

Expected: `6` (или больше — 6 разных селекторов встречаются минимум по разу).

Визуальную проверку (действительно ли цвет читаемый, не режет глаз) в этой среде провести нельзя — браузерного инструмента нет. Отмечается как открытый пункт, см. задачу 8 / `docs/prod_pending.md`.

- [ ] **Step 3: Commit**

```bash
git add bb/assets/styles/category_picker.css
git commit -m "$(cat <<'EOF'
feat(bb): category_picker.css — фон по фазе вкладки «Редактировать» + кнопки

Новая модель — бледно-синий, locate — бледно-жёлтый (предпросмотр),
unlocked — тёплый жёлтый + баннер режима редактирования. «Внести
изменения»/«Сохранить» — зелёные, «Отмена» — нейтральная, обычная
семантика подтверждения/отмены.
EOF
)"
```

---

### Task 7: `tovar_new_mod.php` — разметка, блокировка полей, деплинк в locate, кэш-баст

**Files:**
- Modify: `bb/tovar_new_mod.php`
- Test: разовый скрипт `/tmp/test_edit_lock_markup.php` внутри контейнера (не коммитится)

**Interfaces:**
- Produces: `window.TOVAR_MOD_INITIAL_MODEL` (JSON или `null`) — потребляется `model_picker.js` (Task 4).

- [ ] **Step 1: Переменные состояния перед большим `echo` — блокировка/скрытие по умолчанию + сид для JS**

Найти:

```php
echo '
<form name="tovar" action="tovar_new_mod.php" method="post">
```

Заменить на:

```php
// Фаза «locate» вкладки «Редактировать»: поля — только предпросмотр найденной
// записи, править нельзя, пока не нажата «Внести изменения». model_picker.js
// делает то же самое динамически при живом поиске; здесь — чтобы не было
// мигания незаблокированных полей при заходе по прямой ссылке с готовым
// model_id (см. docs/superpowers/specs/2026-08-16-tovar-new-mod-edit-lock-design.md).
$locate_disabled = ($action === 'редактировать') ? ' disabled="disabled"' : '';
$locate_hidden    = ($action === 'редактировать') ? ' style="display:none;"' : '';

$initial_model_json = 'null';
if ($action === 'редактировать') {
    $initial_model_json = json_encode(array_merge($model_def, array(
        'id'           => (int) $model_id,
        'cat_id'       => (int) $cat_id,
        'cat_name'     => $cat_def['rent_cat_name'],
        'cat_dog_name' => $cat_def['dog_name'],
    )), JSON_UNESCAPED_UNICODE);
}

echo '
<form name="tovar" action="tovar_new_mod.php" method="post">
```

- [ ] **Step 2: Баннер режима редактирования под вкладками**

Найти:

```php
<div class="catp-tabs">
	<button type="button" id="tab_new" class="catp-tab">Новая модель</button>
	<button type="button" id="tab_edit" class="catp-tab">Редактировать действующую</button>
</div>
```

Заменить на:

```php
<div class="catp-tabs">
	<button type="button" id="tab_new" class="catp-tab">Новая модель</button>
	<button type="button" id="tab_edit" class="catp-tab">Редактировать действующую</button>
</div>

<div id="edit_phase_banner" class="catp-phase-banner" style="display:none;"></div>
```

- [ ] **Step 3: Скрыть «+ создать категорию»/«+ создать производителя» в исходном locate-состоянии**

Найти:

```php
			<input type="hidden" name="cat_select_new" id="cat_select_new" value="' . (int) $cat_id . '" />
			<input type="button" id="cat_create_open" value="+ создать категорию" />
```

Заменить на:

```php
			<input type="hidden" name="cat_select_new" id="cat_select_new" value="' . (int) $cat_id . '" />
			<input type="button" id="cat_create_open" value="+ создать категорию"' . $locate_hidden . ' />
```

Найти:

```php
			<input type="hidden" name="producer_select_new" id="producer_select_new" value="' . good_print($model_def['producer']) . '" />
			<input type="button" id="prod_create_open" value="+ создать производителя" />
			<input type="button" id="prod_edit_open" value="редактировать" style="display:none;" />
```

Заменить на:

```php
			<input type="hidden" name="producer_select_new" id="producer_select_new" value="' . good_print($model_def['producer']) . '" />
			<input type="button" id="prod_create_open" value="+ создать производителя"' . $locate_hidden . ' />
			<input type="button" id="prod_edit_open" value="редактировать" style="display:none;" />
```

- [ ] **Step 4: `disabled` на 19 полях `EDIT_FIELD_MAP` + кнопке multicolor**

Найти каждую из следующих строк и заменить ровно как показано (одна замена на строку, все — в пределах `bb/tovar_new_mod.php`):

```php
<td><input type="text" name="model_addr_new" size="70" id="model_addr_new" value="' . good_print($model_def['model_addr']) . '" /><br />
```
→
```php
<td><input type="text" name="model_addr_new" size="70" id="model_addr_new" value="' . good_print($model_def['model_addr']) . '"' . $locate_disabled . ' /><br />
```

```php
<span style="display:none;">Адрес фото:<input type="text" name="ph_addr_new" size="70" id="ph_addr_new" value="' . good_print($model_def['ph_addr']) . '" /></span>
```
→
```php
<span style="display:none;">Адрес фото:<input type="text" name="ph_addr_new" size="70" id="ph_addr_new" value="' . good_print($model_def['ph_addr']) . '"' . $locate_disabled . ' /></span>
```

```php
		<td> <input type="text" name="color_new" size="30" id="color_new" value="' . good_print($model_def['color']) . '" /> нет цвета - ставим "0", <input type="button" value="multicolor" onclick="document.getElementById(\'color_new\').value=\'multicolor\'" /></td>
```
→
```php
		<td> <input type="text" name="color_new" size="30" id="color_new" value="' . good_print($model_def['color']) . '"' . $locate_disabled . ' /> нет цвета - ставим "0", <input type="button" id="color_multicolor_btn" value="multicolor" onclick="document.getElementById(\'color_new\').value=\'multicolor\'"' . $locate_disabled . ' /></td>
```

```php
		<td><input type="text" name="m_set_new" size="70" id="m_set_new" value="' . good_print($model_def['set']) . '" /></td>
```
→
```php
		<td><input type="text" name="m_set_new" size="70" id="m_set_new" value="' . good_print($model_def['set']) . '"' . $locate_disabled . ' /></td>
```

```php
			<input type="number" step="any" min="0" name="m_price_new" size="70" id="m_price_new" value="' . $model_def['agr_price'] . '" />
			<select name="m_price_cur_new" id="m_price_cur_new">
```
→
```php
			<input type="number" step="any" min="0" name="m_price_new" size="70" id="m_price_new" value="' . $model_def['agr_price'] . '"' . $locate_disabled . ' />
			<select name="m_price_cur_new" id="m_price_cur_new"' . $locate_disabled . '>
```

```php
            <input type="number" step="1" min="0" name="price_new_new" size="70" id="price_new_new" value="' . (isset($model_def['price_new']) ? $model_def['price_new'] : '') . '" /> бел. руб.
```
→
```php
            <input type="number" step="1" min="0" name="price_new_new" size="70" id="price_new_new" value="' . (isset($model_def['price_new']) ? $model_def['price_new'] : '') . '"' . $locate_disabled . ' /> бел. руб.
```

```php
			<input type="number" step="any" min="0" name="lom_srok_new" size="5" id="lom_srok_new" value="' . $model_def['lom_srok'] . '" /> года (лет).
```
→
```php
			<input type="number" step="any" min="0" name="lom_srok_new" size="5" id="lom_srok_new" value="' . $model_def['lom_srok'] . '"' . $locate_disabled . ' /> года (лет).
```

```php
			<select name="m_sex" id="m_sex">
```
→
```php
			<select name="m_sex" id="m_sex"' . $locate_disabled . '>
```

```php
			<input type="number" step="any" min="0" name="age_from" size="5" id="age_from_new" value="' . $model_def['age_from'] . '" /> месяцев.
```
→
```php
			<input type="number" step="any" min="0" name="age_from" size="5" id="age_from_new" value="' . $model_def['age_from'] . '"' . $locate_disabled . ' /> месяцев.
```

```php
			<input type="number" step="any" min="0" name="age_to" size="5" id="age_to_new" value="' . $model_def['age_to'] . '" /> месяцев.
```
→
```php
			<input type="number" step="any" min="0" name="age_to" size="5" id="age_to_new" value="' . $model_def['age_to'] . '"' . $locate_disabled . ' /> месяцев.
```

```php
			<input type="number" step="any" min="0" name="weight_from" size="5" id="weight_from_new" value="' . $model_def['weight_from'] . '" /> кг.
```
→
```php
			<input type="number" step="any" min="0" name="weight_from" size="5" id="weight_from_new" value="' . $model_def['weight_from'] . '"' . $locate_disabled . ' /> кг.
```

```php
			<input type="number" step="any" min="0" name="weight_to" size="5" id="weight_to_new" value="' . $model_def['weight_to'] . '" /> кг.
```
→
```php
			<input type="number" step="any" min="0" name="weight_to" size="5" id="weight_to_new" value="' . $model_def['weight_to'] . '"' . $locate_disabled . ' /> кг.
```

```php
			Залог: <input type="number" step="any" min="0" name="collateral" id="collateral_new" style="width:70px;"  value="' . $model_def['collateral'] . '" /> руб.;
			Новый год:
			<select name="ny" id="ny_new" style="width:50px;" >
```
→
```php
			Залог: <input type="number" step="any" min="0" name="collateral" id="collateral_new" style="width:70px;"  value="' . $model_def['collateral'] . '"' . $locate_disabled . ' /> руб.;
			Новый год:
			<select name="ny" id="ny_new" style="width:50px;"' . $locate_disabled . ' >
```

```php
			Зверь:
			<select name="zv" id="zv_new" style="width:50px;" >
```
→
```php
			Зверь:
			<select name="zv" id="zv_new" style="width:50px;"' . $locate_disabled . ' >
```

```php
			Сказка:
			<select name="tale" id="tale_new" style="width:50px;" >
```
→
```php
			Сказка:
			<select name="tale" id="tale_new" style="width:50px;"' . $locate_disabled . ' >
```

```php
			Резерв1:
			<select name="rez1" id="rez1_new" style="width:50px;" >
```
→
```php
			Резерв1:
			<select name="rez1" id="rez1_new" style="width:50px;"' . $locate_disabled . ' >
```

```php
			Резерв2:
			<select name="rez2" id="rez2_new" style="width:50px;" >
```
→
```php
			Резерв2:
			<select name="rez2" id="rez2_new" style="width:50px;"' . $locate_disabled . ' >
```

- [ ] **Step 5: Кнопки «Внести изменения»/«Отмена» рядом с сабмитом**

Найти:

```php
<br /><br />
' . ($action == 'редактировать' ? '<input type="submit" name="action" id="submit_btn" value="обновить" onclick="return send_form_ch();"/>' : '<input type="submit" name="action" id="submit_btn" value="сохранить" onclick="return send_form_ch();"/>') . '

</form>
```

Заменить на:

```php
<br /><br />
<input type="button" id="model_edit_start" value="Внести изменения" style="display:none;" />
<input type="button" id="model_edit_cancel" value="Отмена" style="display:none;" />
' . ($action == 'редактировать' ? '<input type="submit" name="action" id="submit_btn" value="обновить" onclick="return send_form_ch();"' . $locate_hidden . '/>' : '<input type="submit" name="action" id="submit_btn" value="сохранить" onclick="return send_form_ch();"/>') . '

</form>
```

- [ ] **Step 6: Передать снэпшот найденной модели в JS**

Найти:

```php
window.TOVAR_MOD_INITIAL_TAB = ' . json_encode($action === 'редактировать' ? 'edit' : 'new') . ';
```

Заменить на:

```php
window.TOVAR_MOD_INITIAL_TAB = ' . json_encode($action === 'редактировать' ? 'edit' : 'new') . ';
window.TOVAR_MOD_INITIAL_MODEL = ' . $initial_model_json . ';
```

- [ ] **Step 7: Кэш-баст — бампнуть версии всех правленных в этом плане файлов**

Найти:

```php
<link href="/bb/assets/styles/category_picker.css?v=3" rel="stylesheet" type="text/css" />
```
→
```php
<link href="/bb/assets/styles/category_picker.css?v=4" rel="stylesheet" type="text/css" />
```

Найти:

```php
<script src="/bb/assets/js/category_picker.js?v=3"></script>
<script src="/bb/assets/js/producer_picker.js?v=3"></script>
<script src="/bb/assets/js/model_picker.js?v=2"></script>
```
→
```php
<script src="/bb/assets/js/category_picker.js?v=4"></script>
<script src="/bb/assets/js/producer_picker.js?v=4"></script>
<script src="/bb/assets/js/model_picker.js?v=3"></script>
```

(`live_picker.js?v=4` не трогаем — этот файл в этом плане не менялся.)

- [ ] **Step 8: `php -l` — синтаксис файла цел после всех точечных правок**

```bash
docker compose exec -T app php -l /var/www/html/bb/tovar_new_mod.php
```

Expected: `No syntax errors detected in /var/www/html/bb/tovar_new_mod.php`.

- [ ] **Step 9: Структурный HTML-смоук — оба состояния (свежая форма и деплинк с `model_id`)**

```bash
docker compose cp bb/tovar_new_mod.php tiktakby-app:/var/www/html/bb/tovar_new_mod.php
cat > /tmp/test_edit_lock_markup.php << 'PHPEOF'
<?php
session_start();
$_SESSION['svoi'] = 8941;
$_SESSION['level'] = 7;
$_SESSION['user_fio'] = 'test';

require_once('/var/www/html/bb/Db.php');
$mysqli = \bb\Db::getInstance()->getConnection();
$row = $mysqli->query('SELECT tovar_rent_id FROM tovar_rent LIMIT 1')->fetch_assoc();
$modelId = (int) $row['tovar_rent_id'];

function renderPage($post) {
    $_POST = $post;
    $_REQUEST = $post;
    ob_start();
    include '/var/www/html/bb/tovar_new_mod.php';
    return ob_get_clean();
}

// State (a): свежая форма (без action) — поля НЕ заблокированы, кнопка «Внести изменения» скрыта.
$freshHtml = renderPage([]);
$checksA = [
    'model_edit_start скрыта' => strpos($freshHtml, 'id="model_edit_start" value="Внести изменения" style="display:none;"') !== false,
    'color_new НЕ disabled'   => preg_match('/id="color_new" value="[^"]*" \/>/', $freshHtml) === 1,
    'cat_create_open видна'   => preg_match('/id="cat_create_open" value="\+ создать категорию" \/>/', $freshHtml) === 1,
];
foreach ($checksA as $label => $ok) {
    echo ($ok ? 'OK' : 'FAIL') . ": (a) $label\n";
}

// State (b): заход по прямой ссылке с готовым model_id — locate, поля заблокированы.
$editHtml = renderPage(['action' => 'редактировать', 'model_id' => $modelId]);
$checksB = [
    'submit_btn скрыт (locate)'  => preg_match('/id="submit_btn" value="обновить" onclick="return send_form_ch\(\);" style="display:none;"\/>/', $editHtml) === 1,
    'color_new disabled'         => preg_match('/id="color_new" value="[^"]*" disabled="disabled" \/>/', $editHtml) === 1,
    'cat_create_open скрыта'     => preg_match('/id="cat_create_open" value="\+ создать категорию" style="display:none;" \/>/', $editHtml) === 1,
    'TOVAR_MOD_INITIAL_MODEL не null' => strpos($editHtml, 'window.TOVAR_MOD_INITIAL_MODEL = null;') === false,
];
foreach ($checksB as $label => $ok) {
    echo ($ok ? 'OK' : 'FAIL') . ": (b) $label\n";
}

$allOk = !in_array(false, array_merge(array_values($checksA), array_values($checksB)), true);
echo $allOk ? "ВСЕ ПРОВЕРКИ ПРОШЛИ\n" : "ЕСТЬ ПАДЕНИЯ\n";
exit($allOk ? 0 : 1);
PHPEOF
docker compose cp /tmp/test_edit_lock_markup.php tiktakby-app:/tmp/test_edit_lock_markup.php
docker compose exec -T app php /tmp/test_edit_lock_markup.php
```

Expected: все строки `OK: ...`, и в конце `ВСЕ ПРОВЕРКИ ПРОШЛИ`.

- [ ] **Step 10: Commit**

```bash
git add bb/tovar_new_mod.php
git commit -m "$(cat <<'EOF'
feat(bb): tovar_new_mod.php — разметка locate/unlocked, деплинк в locate, кэш-баст

19 полей EDIT_FIELD_MAP + кнопка multicolor блокируются на сервере
сразу, если открыта прямая ссылка с готовым model_id (без этого поля
мигали бы разблокированными до отработки JS) — тем самым прямая ссылка
теперь тоже стартует в locate, не в unlocked, как раньше. Кнопки
«Внести изменения»/«Отмена» + баннер режима редактирования. Версии
category_picker.css/js, producer_picker.js, model_picker.js бампнуты.
EOF
)"
```

---

### Task 8: `docs/prod_pending.md` — обновить чеклист

**Files:**
- Modify: `docs/prod_pending.md`

**Interfaces:** нет (документация).

- [ ] **Step 1: Прочитать текущее состояние файла**

```bash
grep -n "producers-directory\|tovar_new_mod" docs/prod_pending.md
```

- [ ] **Step 2: Дополнить описание ветки #3 (или актуальный номер строки из Step 1) и добавить пункт в «Проверить глазами»**

В строку с описанием ветки `feature/producers-directory` добавить через запятую: «двухфазный режим (locate/unlocked) на вкладке «Редактировать действующую» — жёсткий каскад категория↔фирма, блокировка полей до явного «Внести изменения», живая проверка на дубликат».

В секцию «Проверить глазами» добавить пункт:

```markdown
- [ ] `tovar_new_mod.php`, вкладка «Редактировать действующую»: найти модель поиском
  (с разных стартовых полей — категория/фирма/модель), убедиться что поля
  заблокированы до клика «Внести изменения»; после клика — поменять цвет/цену,
  нажать «Отмена» — проверить, что откатилось; повторить и нажать «Сохранить» —
  проверить, что применилось. Отдельно — прямая ссылка «редактировать модель» с
  `tovar_new.php` тоже должна открываться заблокированной (не сразу редактируемой).
  Живая проверка на дубликат при вводе существующей комбинации имя+цвет.
  **Браузерный клик-тест не проводился (нет инструмента автоматизации браузера в
  среде разработки) — это первая живая проверка.**
```

- [ ] **Step 3: Commit**

```bash
git add docs/prod_pending.md
git commit -m "docs: prod_pending — чеклист для двухфазного режима вкладки «Редактировать»"
```

---

## Self-Review (пройден автором плана)

1. **Покрытие спеки:** двухфазное состояние (locate/unlocked) — Task 4; жёсткий кросс-фильтр — Task 1+2+3; визуальная индикация — Task 6+7; живая проверка на дубликат — Task 5; деплинк в locate — Task 4 (JS) + Task 7 (PHP-сид); переключение вкладок сбрасывает фазу — Task 4 (`shouldReset` в `setMode`); кэш-баст — Task 7 Step 7; чеклист — Task 8. Всё из спеки 2026-08-16 покрыто.
2. **Плейсхолдеры:** не найдено — каждый шаг содержит реальный код/команду.
3. **Согласованность типов/имён:** `resolveEditPhaseUI`, `findDuplicateMatch`, `applyPhaseUI`, `enterUnlocked`, `cancelEdit`, `currentEditItem`, `editPhase`, `EDIT_PHASE_FIELD_IDS` — используются одинаково во всех задачах, где встречаются (проверено сквозным просмотром Task 4→5→6→7). Id `model_edit_start`/`model_edit_cancel`/`edit_phase_banner`/`new_model_div` совпадают между Task 4 (JS), Task 6 (CSS) и Task 7 (разметка).
