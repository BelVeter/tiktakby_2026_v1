# Хардening страницы «Новая модель» + чистка дублей каталога

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Починить `bb/tovar_new_mod.php` (создание/редактирование модели), закрыть пути появления дублей моделей и категорий, вычистить накопившийся мусор в каталоге.

**Architecture:** Три независимых слоя. (1) Код страницы — точечные правки легаси-PHP без рефакторинга архитектуры. (2) Код `bb/tovar_del.php` — источник висячих ссылок. (3) Данные — чистка Laravel-миграциями с явными ID и защитными пере-проверками.

**Tech Stack:** PHP 7.4, MariaDB 10.6, легаси `bb/` на `\bb\Db` (mysqli), Laravel-миграции для данных, docker compose для прогона.

## Global Constraints

- Всё тестируется локально в docker; прод трогаем только через `Deploy.php` после ревью владельцем.
- `bb/` — легаси на mysqli через `\bb\Db::getInstance()->getConnection()`. НЕ смешивать с Laravel DB в одном файле.
- Никаких позиционных `INSERT ... VALUES(...)` — только именованные колонки (см. [docs/db_notes.md](../../db_notes.md), ловушка №1).
- Перед любой чисткой данных — дамп затрагиваемых таблиц.
- Миграции чистки — идемпотентные и самопроверяющиеся: если строка уже не подходит под условие, миграция её пропускает, а не падает.
- `php artisan migrate` на проде работает с 12.07.2026, обходной SQL не нужен.

---

## Что установлено (аудит 27.07.2026, прод + локаль совпадают)

| Находка | Доказательство |
|---|---|
| Создание новой категории на странице падает **всегда** | `INSERT INTO tovar_rent_cat VALUES('','$a','$b')` — 3 значения на 9 колонок → `ERROR 1136 Column count doesn't match value count` |
| AJAX «и для договора (ед.ч.)» мёртв | `getXmlHttp` не определена на странице → `ReferenceError`, поле навсегда в «... ждите ...» |
| Оба AJAX-эндпоинта нерабочие | `/bb/cat_ch.php` → HTTP 500 (функции `mysql_*` удалены в PHP 7); `bb/cat_ch_new.php:229` выполняет `$query` вместо `$query_cat` и не делает `fetch_assoc()` |
| «обновить» пишет дубль, о котором предупреждает | `bb/tovar_new_mod.php:272-278` — сообщение «Внесите изменения» выводится, но `UPDATE` уже выполнен |
| Полноценный CRUD категорий уже существует | `bb/category_management.php` — задаёт `main_sub_razdel_id`, `cat_url_key`, `cat_type`, `cat_sort`, удаляет через `Category::archAndDelete()` (в `tovar_rent_cat_arch` 40 строк) |
| Источник висячих ссылок | `bb/tovar_del.php:266` удаляет `tovar_rent` + `tovar_rent_items` и **ничего больше** из 13 таблиц с `model_id` |
| Висячие ссылки в проде | `rent_orders_arch` 222 строки / 5 моделей; `rent_tarif_act` 172 / 25; `tovar_rent_items_arch` 3 / 2; `dop_photos` 15 / 3 |
| Дубли категорий | ровно один: «Принцессы Диснея» = 158 и 178 |
| Дубли моделей (cat+producer+model+color) | 4 пары: (819,850), (1273,1274), (1062,1063), (1069,1203) |
| Модели-сироты (нет юнитов, нет страницы) | 60 шт., из них **39 не имеют вообще ни одной ссылки** ни в одной из 13 таблиц |
| Дубли web-страниц | нет (`rent_model_web` чист — следствие PR #246) |

### Разбор четырёх «дублей» моделей

| Пара | Вердикт |
|---|---|
| 819 / 850 Happy Baby SLEEPER beige | Обе архивные (по 3 arch-юнита, 33 и 34 сделки), страниц нет. **Не трогать** — история обеих нужна отчётности. |
| 1273 / 1274 Kiddieland «Ковчег ноя» | 1274 живая (46 сделок, страница `noi-kovcheg`). **1273 — чистая сирота: 0 юнитов, 0 сделок, 0 тарифов, 0 ссылок где-либо. Удалить.** |
| 1062 / 1063 BAMBOLA Bambino Одуванчик | 1062 живая (юнит 719213, 50 сделок, страница). **1063 — «надгробие»**: юнита нет, но за ним висят 69 заявок, 1 звонок, 7 тарифов. Заявки исторически относятся к юниту 719213, который сейчас числится за 1062. **Слить 1063 → 1062.** |
| 1069 / 1203 Nania Cosmo SP Animals Elephant | **Не дубль, а опечатка в названии.** Обе живые, у обеих юниты, сделки и разные страницы; у 1203 `page_addr = avtokreslo_nania_driver_animals_elefant` — это модель **Driver**, а в поле `model` ошибочно записано «Cosmo SP». **Переименовать 1203, не сливать.** |

### Разбор дубля категорий

| | 158 | 178 |
|---|---|---|
| `cat_url_key` | `kostyum-princessy-naprokat` | `disney-princesses` |
| Раздел / подраздел | `prokat-detskih-tovarov` / `detskaya-komnata` ⚠️ | `karnavalnye-kostyumy` / `kostumy-zverei` ✅ |
| Связей в `subrazdel_category` | **0** (в дереве каталога не видна) | 1 |
| Моделей / юнитов | 1 (модель 730) / 2 акт + 1 арх | 12 / 20 акт + 1 арх |
| SEO-строка в `pages` | id 98 | id 99 |
| Заявки по `cat_id` | 0 | — |

Категория 158 лежит в разделе «Прокат детских товаров / Детская комната» — карнавальный костюм принцессы Диснея. Точное происхождение недоказуемо (позиционный `INSERT` со страницы модели упал бы, а `cat_url_key` у неё заполнен — значит создавали не оттуда), но системная причина видна: **`Category::save()` не создаёт связь в `subrazdel_category`** (её пишет только `bb/classes/SubRazdel.php:512` из `sub_razdel_manage.php`). Поэтому категория рождается вне дерева каталога, и ошибочный подраздел никак не всплывает. Модель 730 живая (26 сделок), поэтому категория **сливается**, а не удаляется, и её L3-URL меняется → нужен 301.

---

## Файловая структура

| Файл | Что делает после правок |
|---|---|
| `bb/tovar_new_mod.php` | Создание/редактирование модели. Создание категорий отсюда убрано; дубли блокируются до записи; JS-AJAX жив |
| `bb/cat_ch_new.php` | AJAX-справочник; ветка `dog_name_select` чинится |
| `bb/tovar_del.php` | Удаление модели/товара; больше не оставляет висячих ссылок |
| `database/migrations/2026_07_27_*` | Пять миграций чистки данных |
| `docs/db_notes.md` | Новый раздел про 13 таблиц с `model_id` и правило слияния моделей |

---

## Фаза 0. Страховка

### Задача 0: Дамп затрагиваемых таблиц

**Files:** нет правок кода.

- [ ] **Шаг 1: Снять дамп с прода**

```bash
ssh h149208@vh164.hoster.by \
  'mysqldump -utiktakby_tiktak -p"$PASS" tiktakby_tiktak \
   tovar_rent tovar_rent_cat tovar_rent_items tovar_rent_items_arch \
   rent_tarif_act rent_orders rent_orders_arch zvonki dop_photos rent_model_web pages \
   > ~/backup_catalog_2026-07-27.sql'
```

- [ ] **Шаг 2: Проверить размер дампа**

Run: `ssh h149208@vh164.hoster.by 'ls -lh ~/backup_catalog_2026-07-27.sql'`
Expected: файл не пустой, > 1 МБ.

---

## Фаза 1. Починить блокеры страницы

### Задача 1: Оживить AJAX «и для договора (ед.ч.)»

**Files:**
- Modify: `bb/tovar_new_mod.php` (добавить `getXmlHttp`, сменить URL эндпоинта на `:388`)
- Modify: `bb/cat_ch_new.php:226-246`

**Interfaces:**
- Produces: рабочий `POST /bb/cat_ch_new.php` с `par2=dog_name_select&cat_id=N` → plain-text `dog_name` категории.

- [ ] **Шаг 1: Починить ветку `dog_name_select`**

В `bb/cat_ch_new.php` заменить тело `case 'dog_name_select':` на:

```php
	case 'dog_name_select':

		$query_cat = "SELECT dog_name FROM tovar_rent_cat WHERE tovar_rent_cat_id='" . $cat_id . "'";
		$result = $mysqli->query($query_cat);
		if (!$result) {
			die('Сбой при доступе к базе данных: ' . $query_cat . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}

		if ($result->num_rows == 1) {
			$cat_def = $result->fetch_assoc();
			echo $cat_def['dog_name'];
		} elseif ($result->num_rows < 1) {
			echo 'не найдено';
		} else {
			echo 'более 1 категории. обратитесь к разработчику';
		}

		break;
```

- [ ] **Шаг 2: Проверить эндпоинт напрямую**

Run:
```bash
docker compose exec -T app php -l bb/cat_ch_new.php
curl -s -o /dev/null -w "%{http_code}\n" -X POST -d "cat_id=89&par2=dog_name_select" http://localhost/bb/cat_ch_new.php
```
Expected: `No syntax errors detected`, затем `200` (не `500`).

- [ ] **Шаг 3: Добавить `getXmlHttp` на страницу**

В `bb/tovar_new_mod.php` сразу после `<script language="javascript">` (строка 338) вставить каноничное определение (идентично `bb/kr_baza_new.php:441`):

```javascript
	function getXmlHttp() {
		var xmlhttp;
		try {
			xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try {
				xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (E) {
				xmlhttp = false;
			}
		}
		if (!xmlhttp && typeof XMLHttpRequest != 'undefined') {
			xmlhttp = new XMLHttpRequest();
		}
		return xmlhttp;
	}
```

- [ ] **Шаг 4: Переключить AJAX на живой эндпоинт**

В `bb/tovar_new_mod.php:388` заменить:

```javascript
			xmlhttp.open("POST", '/bb/cat_ch.php', true)
```

на:

```javascript
			xmlhttp.open("POST", '/bb/cat_ch_new.php', true)
```

- [ ] **Шаг 5: Обработать неуспешный ответ**

В том же `select_ch3`, в `onreadystatechange`, добавить ветку ошибки, чтобы поле не зависало навсегда:

```javascript
			xmlhttp.onreadystatechange = function () {
				if (xmlhttp.readyState == 4) {
					if (xmlhttp.status == 200) {
						document.getElementById('cat_input_dog_new').value = xmlhttp.responseText;
					} else {
						document.getElementById('cat_input_dog_new').value = '';
						alert('Не удалось получить название категории для договора (код ' + xmlhttp.status + '). Заполните поле вручную.');
					}
				}
			}
```

- [ ] **Шаг 6: Проверить в браузере**

Открыть `http://localhost/bb/tovar_new_mod.php`, выбрать категорию в «Категория товара».
Expected: поле «и для договора (ед.ч.)» заполняется названием, консоль чистая (ошибка `mce-autosize-textarea` — от расширения браузера, не наша).

- [ ] **Шаг 7: Коммит**

```bash
git add bb/tovar_new_mod.php bb/cat_ch_new.php
git commit -m "fix(bb): оживить AJAX названия категории для договора на странице новой модели"
```

### Задача 2: Перекрыть сломанный inline-путь создания категории

**Files:**
- Modify: `bb/tovar_new_mod.php:79-107` (ветка `сохранить`), `:210-254` (ветка `обновить`), `:557` (дропдаун)

**Обоснование:** позиционный `INSERT` сюда физически не лезет (3 значения на 9 колонок), а даже починенный он создаёт категорию без `cat_url_key`, без осмысленного `main_sub_razdel_id` и без связи в `subrazdel_category` — то есть невидимую в дереве каталога.

**Это временная заглушка на одну-две недели.** Полноценное создание категории с этой страницы возвращается в Задаче 14 (попап), уже через `\bb\classes\Category` и с обязательным подразделом. Цель заглушки — чтобы до появления попапа оператор получал понятное сообщение, а не `Column count doesn't match value count`.

- [ ] **Шаг 1: Заменить опцию «ввести новую категорию» на ссылку-подсказку**

В `bb/tovar_new_mod.php:557` в `<select name="cat_select_new">` удалить `<option value="0">ввести новую категорию</option>` и поставить подсказку под селектом:

```php
			<div style="font-size:12px; color:#555;">
				Нужной категории нет? Создайте её в
				<a href="/bb/category_management.php" target="_blank">Управлении категориями</a>,
				затем вернитесь сюда и обновите страницу.
			</div>
```

- [ ] **Шаг 2: Заменить создание категории на явную ошибку (ветка `сохранить`)**

Заменить блок `bb/tovar_new_mod.php:79-107` на:

```php
			//категория берётся только из справочника; создание категорий — в category_management.php
			$cat_id = (int) $cat_select_new;
			if ($cat_id <= 0) {
				die('Категория не выбрана. Создайте категорию в <a href="/bb/category_management.php">Управлении категориями</a> и повторите.');
			}
```

- [ ] **Шаг 3: То же для ветки `обновить`**

Заменить блок `bb/tovar_new_mod.php:210-254` на:

```php
			$cat_id = (int) ($cat_edit_status == 'yes' ? $cat_edit_id : $cat_select_new);
			if ($cat_id <= 0) {
				die('Категория не выбрана. Создайте категорию в <a href="/bb/category_management.php">Управлении категориями</a> и повторите.');
			}

			if ($cat_edit_status == 'yes') {
				$query_upd = "UPDATE tovar_rent_cat SET rent_cat_name='" . trim($cat_input_new) . "', dog_name='" . trim($cat_input_dog_new) . "' WHERE tovar_rent_cat_id='" . $cat_id . "'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {
					die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			}
```

> Переименование существующей категории оставляем — оно рабочее и не создаёт дублей.

- [ ] **Шаг 4: Проверить, что путь создания категории закрыт**

Run: `grep -n "INSERT INTO tovar_rent_cat" bb/tovar_new_mod.php`
Expected: пусто.

- [ ] **Шаг 5: Проверить синтаксис и сохранение модели**

Run: `docker compose exec -T app php -l bb/tovar_new_mod.php`
Затем в браузере завести тестовую модель в существующей категории.
Expected: «Модель успешно заведена. ID модели: N».

- [ ] **Шаг 6: Коммит**

```bash
git add bb/tovar_new_mod.php
git commit -m "fix(bb): убрать сломанное создание категорий со страницы модели"
```

---

## Фаза 2. Закрыть появление дублей на входе

### Задача 3: «обновить» не должен записывать дубль

**Files:**
- Modify: `bb/tovar_new_mod.php:272-278`

- [ ] **Шаг 1: Прерывать до `UPDATE`**

Заменить:

```php
			if ($mod_num > 0) {
				$model_double_text = 'Внимание!!! Вы задублировали существующую модель, т.к. категория, название модели, производителя и цвет - дублируют действующую модель. <br />Внесите изменения.';
			}
```

на:

```php
			if ($mod_num > 0) {
				$dup = $result_mod->fetch_assoc();
				die('Изменения НЕ сохранены: категория, название, производитель и цвет полностью совпадают с существующей моделью ID '
					. $dup['tovar_rent_id'] . ' («' . good_print($dup['producer'] . ' ' . $dup['model']) . '»).<br />'
					. 'Измените данные или используйте существующую модель.<br /><br />'
					. '<a href="/bb/tovar_new_mod.php">Вернуться</a>');
			}
```

- [ ] **Шаг 2: Проверить вручную**

Открыть на редактирование модель 1063 (или любую тестовую), выставить категорию/название/производителя/цвет как у соседней модели, нажать «обновить».
Expected: страница с текстом «Изменения НЕ сохранены…», в БД `tovar_rent` не изменилась.

- [ ] **Шаг 3: Убедиться, что нормальное обновление не сломано**

Отредактировать ту же модель, поменяв только цену. Expected: «Модель успешно обновлена».

- [ ] **Шаг 4: Коммит**

```bash
git add bb/tovar_new_mod.php
git commit -m "fix(bb): блокировать сохранение модели-дубля вместо предупреждения постфактум"
```

### Задача 4: Защита от двойного сабмита

**Files:**
- Modify: `bb/tovar_new_mod.php` (функция `send_form_ch`, `:420`)

**⚠️ Важно:** `action` приходит из `name="action"` самой кнопки submit. Отключать кнопку через `disabled` НЕЛЬЗЯ — тогда её значение не уйдёт в POST и сохранение сломается. Только флаг.

- [ ] **Шаг 1: Добавить флаг**

Перед `function send_form_ch()` объявить:

```javascript
	var tovarFormSubmitted = false;
```

В начало `send_form_ch()` добавить:

```javascript
		if (tovarFormSubmitted) {
			alert('Форма уже отправлена, дождитесь ответа сервера.');
			return false;
		}
```

Перед финальным `return true;` этой же функции:

```javascript
		tovarFormSubmitted = true;
```

- [ ] **Шаг 2: Проверить**

Дважды быстро кликнуть «сохранить». Expected: второй клик даёт alert, в БД одна новая модель.

- [ ] **Шаг 3: Коммит**

```bash
git add bb/tovar_new_mod.php
git commit -m "fix(bb): защита от двойной отправки формы модели"
```

---

## Фаза 3. Остановить утечку висячих ссылок

### Задача 5: `tovar_del.php` — переносить в архив, а не удалять ✅ ВЫПОЛНЕНО (Правка 6)

**Files:**
- Create: `bb/classes/ModelArchive.php`
- Create: `database/migrations/2026_07_28_160000_extend_tovar_rent_arch_for_archiving.php`
- Modify: `bb/tovar_del.php`, `bb/category_management.php`, `bb/classes/Category.php`

**Обоснование:** удалялись только `tovar_rent` и `tovar_rent_items`. Это и породило 222 висячие заявки, 178 тарифов, 15 фото и 3 архивных юнита без модели.

**Решение оказалось проще, чем «чистить все 13 таблиц»:** архитектура архивации моделей уже была заложена — отчёты резолвят модель через `UNION(tovar_rent, tovar_rent_arch)` (`Deal.php:1003,1027,1061,1085`, `tovar.php:1546,1562`). Не хватало записи в `tovar_rent_arch` (0 строк, схема отстала от живой). Достроили таблицу и стали в неё писать — заявки, звонки и сделки продолжают разрешаться, чистить их не нужно.

**Три дыры, закрытые одним заходом:**

| Дыра | Было | Стало |
|---|---|---|
| Проверка прав `tovar_del.php:39` | `!$_SESSION['level']>4` — всегда `false`, удалять мог любой сотрудник | `can_destroy()` = level 5/7; сама страница по-прежнему открыта всем (нужна для выбытия) |
| «удалить все (модель и все товары)» | `DELETE` двух таблиц из тринадцати | «перенести модель в архив»: копия в `tovar_rent_arch` + снимок спутников в `arch_snapshot`; блокируется при живых юнитах |
| «удалить данный товар» | `DELETE` юнита, проверялся только `rented_out` | разрешено только для единицы без истории (сделки/заявки/карнавальные брони по `item_inv_n`) |

**Категории:** серверная проверка `Category::archiveBlockers()` (в разметке кнопка показывалась при `tov_num==0`, но `tov_num` считает **юниты**, а не модели — категория с моделями без юнитов кнопку получала), гейт по уровню, и `Category::delete()` теперь снимает привязку `subrazdel_category`.

**Почему таблица, а не флаг `is_archived`:** `tovar_rent` читается в 140 местах в 61 файле легаси-SQL — с флагом пропуск любого условия молча выпускает архивную модель в выпадашку, прайс или на сайт. С отдельной таблицей все 140 запросов правильны по умолчанию. Подробнее — `docs/db_notes.md`, п.11.

- [ ] **Шаг 1: Запретить удаление при наличии истории**

В начало ветки, до `START TRANSACTION`, добавить:

```php
		$model_id = (int) $model_id;

		$history = $mysqli->query("
			SELECT
				(SELECT COUNT(*) FROM tovar_rent_items_arch WHERE model_id = {$model_id}) AS items_arch,
				(SELECT COUNT(*) FROM rent_orders          WHERE model_id = {$model_id}) AS orders,
				(SELECT COUNT(*) FROM rent_orders_arch     WHERE model_id = {$model_id}) AS orders_arch,
				(SELECT COUNT(*) FROM zvonki               WHERE model_id = {$model_id}) AS calls
		")->fetch_assoc();

		if (array_sum(array_map('intval', $history)) > 0) {
			die('Удаление запрещено: у модели есть история — архивных товаров: ' . $history['items_arch']
				. ', заявок: ' . ($history['orders'] + $history['orders_arch'])
				. ', звонков: ' . $history['calls'] . '.<br />'
				. 'Такую модель можно только скрыть (снять веб-страницу), но не удалять — иначе отчёты потеряют связь с историей.');
		}
```

- [ ] **Шаг 2: Дочистить безопасные связи в той же транзакции**

После `DELETE FROM tovar_rent_items ...` добавить:

```php
		foreach ([
			"DELETE FROM rent_tarif_act  WHERE model_id = {$model_id}",
			"DELETE FROM rent_tarif_prev WHERE model_id = {$model_id}",
			"DELETE FROM rent_model_web  WHERE model_id = {$model_id}",
			"DELETE FROM dop_photos      WHERE model_id = {$model_id}",
			"DELETE FROM multi_web       WHERE model_id = {$model_id}",
			"DELETE FROM favorite_tovars WHERE model_id = {$model_id}",
		] as $q) {
			if (!$mysqli->query($q)) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
		}
```

- [ ] **Шаг 3: Починить опечатку в ROLLBACK**

Заменить `$query_fin = "ROLLBACK'";` на `$query_fin = "ROLLBACK";`

- [ ] **Шаг 4: Проверить**

Run: `docker compose exec -T app php -l bb/tovar_del.php`
Затем локально: попытаться удалить модель 1062 (есть история) → отказ; удалить свежесозданную тестовую модель без истории → удаляется вместе с тарифами.

- [ ] **Шаг 5: Коммит**

```bash
git add bb/tovar_del.php
git commit -m "fix(bb): не оставлять висячие ссылки при удалении модели"
```

---

## Фаза 4. Чистка данных

Все миграции — в `database/migrations/`, каждая с рабочим `down()` где это осмысленно, и с пере-проверкой условия внутри `up()`.

### Задача 6: Удалить 39 полностью несвязанных моделей

**Files:**
- Create: `database/migrations/2026_07_28_100000_cleanup_unreferenced_models.php`

- [ ] **Шаг 1: Зафиксировать список до правок**

Run:
```bash
docker compose exec -T db mysql --default-character-set=utf8 -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT GROUP_CONCAT(tr.tovar_rent_id) FROM tovar_rent tr
      WHERE NOT EXISTS (SELECT 1 FROM tovar_rent_items i WHERE i.model_id=tr.tovar_rent_id)
        AND NOT EXISTS (SELECT 1 FROM tovar_rent_items_arch a WHERE a.model_id=tr.tovar_rent_id)
        AND NOT EXISTS (SELECT 1 FROM rent_model_web w WHERE w.model_id=tr.tovar_rent_id)
        AND NOT EXISTS (SELECT 1 FROM rent_orders o WHERE o.model_id=tr.tovar_rent_id)
        AND NOT EXISTS (SELECT 1 FROM rent_orders_arch oa WHERE oa.model_id=tr.tovar_rent_id)
        AND NOT EXISTS (SELECT 1 FROM zvonki z WHERE z.model_id=tr.tovar_rent_id)
        AND NOT EXISTS (SELECT 1 FROM dop_photos p WHERE p.model_id=tr.tovar_rent_id)
        AND NOT EXISTS (SELECT 1 FROM multi_web m WHERE m.model_id=tr.tovar_rent_id)
        AND NOT EXISTS (SELECT 1 FROM favorite_tovars f WHERE f.model_id=tr.tovar_rent_id)
        AND NOT EXISTS (SELECT 1 FROM kb_zayavki k WHERE k.model_id=tr.tovar_rent_id)
        AND NOT EXISTS (SELECT 1 FROM karnaval_zakaz kz WHERE kz.model_id=tr.tovar_rent_id);"
```
Expected (на 27.07.2026): 39 ID, включая 1273.

- [ ] **Шаг 2: Написать миграцию**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Удаляет модели каталога, на которые не ссылается ни одна из 13 таблиц с model_id.
 * Список ID не хардкодится: условие пере-проверяется на момент запуска, поэтому
 * миграция безопасна, даже если за время до деплоя модель успела обрасти данными.
 */
class CleanupUnreferencedModels extends Migration
{
    private const REFERENCING_TABLES = [
        'tovar_rent_items', 'tovar_rent_items_arch', 'rent_model_web',
        'rent_orders', 'rent_orders_arch', 'zvonki', 'dop_photos',
        'multi_web', 'favorite_tovars', 'kb_zayavki', 'karnaval_zakaz',
    ];

    public function up(): void
    {
        $notExists = implode(' AND ', array_map(
            fn ($t) => "NOT EXISTS (SELECT 1 FROM {$t} x WHERE x.model_id = tr.tovar_rent_id)",
            self::REFERENCING_TABLES
        ));

        $ids = DB::select("SELECT tr.tovar_rent_id AS id FROM tovar_rent tr WHERE {$notExists}");
        $ids = array_map(fn ($r) => (int) $r->id, $ids);

        if (empty($ids)) {
            return;
        }

        DB::transaction(function () use ($ids) {
            DB::table('rent_tarif_act')->whereIn('model_id', $ids)->delete();
            DB::table('rent_tarif_prev')->whereIn('model_id', $ids)->delete();
            DB::table('tovar_rent')->whereIn('tovar_rent_id', $ids)->delete();
        });

        logger()->info('CleanupUnreferencedModels: удалено моделей', ['count' => count($ids), 'ids' => $ids]);
    }

    public function down(): void
    {
        // Необратимо: восстановление — из дампа backup_catalog_2026-07-27.sql
    }
}
```

- [ ] **Шаг 3: Прогнать локально**

Run: `docker compose exec -T app php artisan migrate`
Expected: `Migrated: 2026_07_28_100000_cleanup_unreferenced_models`

- [ ] **Шаг 4: Проверить результат**

Run:
```bash
docker compose exec -T db mysql -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT COUNT(*) models FROM tovar_rent; SELECT COUNT(*) still_1273 FROM tovar_rent WHERE tovar_rent_id=1273;"
```
Expected: `models` = 1801 (было 1840, минус 39), `still_1273` = 0.

- [ ] **Шаг 5: Коммит**

```bash
git add database/migrations/2026_07_28_100000_cleanup_unreferenced_models.php
git commit -m "chore(db): удалить 39 моделей каталога без единой ссылки"
```

### Задача 7: Слить модель-надгробие 1063 → 1062

**Files:**
- Create: `database/migrations/2026_07_27_110000_merge_model_1063_into_1062.php`

- [ ] **Шаг 1: Написать миграцию**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Модель 1063 (BAMBOLA Bambino Одуванчик) — дубль 1062: физических юнитов нет,
 * но за ней остались 69 архивных заявок и 1 звонок, относящихся к юниту 719213,
 * который сейчас числится за 1062. Переносим ссылки и удаляем дубль.
 */
class MergeModel1063Into1062 extends Migration
{
    private const DUP  = 1063;
    private const KEEP = 1062;

    public function up(): void
    {
        $dupExists  = DB::table('tovar_rent')->where('tovar_rent_id', self::DUP)->exists();
        $keepExists = DB::table('tovar_rent')->where('tovar_rent_id', self::KEEP)->exists();

        if (!$dupExists || !$keepExists) {
            return; // уже слито или данные изменились — не трогаем
        }

        if (DB::table('tovar_rent_items')->where('model_id', self::DUP)->exists()
            || DB::table('tovar_rent_items_arch')->where('model_id', self::DUP)->exists()) {
            throw new RuntimeException('У модели 1063 появились юниты — слияние отменено, нужен ручной разбор.');
        }

        DB::transaction(function () {
            DB::table('rent_orders')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]);
            DB::table('rent_orders_arch')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]);
            DB::table('zvonki')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]);
            DB::table('dop_photos')->where('model_id', self::DUP)->delete();
            DB::table('rent_tarif_act')->where('model_id', self::DUP)->delete();
            DB::table('rent_tarif_prev')->where('model_id', self::DUP)->delete();
            DB::table('tovar_rent')->where('tovar_rent_id', self::DUP)->delete();
        });
    }

    public function down(): void
    {
        // Необратимо: восстановление — из дампа backup_catalog_2026-07-27.sql
    }
}
```

- [ ] **Шаг 2: Прогнать и проверить**

Run:
```bash
docker compose exec -T app php artisan migrate
docker compose exec -T db mysql -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT COUNT(*) model_1063 FROM tovar_rent WHERE tovar_rent_id=1063;
      SELECT model_id, COUNT(*) n FROM rent_orders_arch WHERE model_id IN (1062,1063) GROUP BY model_id;"
```
Expected: `model_1063` = 0; все заявки (17 + 69 = 86) на `model_id` = 1062.

- [ ] **Шаг 3: Коммит**

```bash
git add database/migrations/2026_07_27_110000_merge_model_1063_into_1062.php
git commit -m "chore(db): слить модель-дубль 1063 в 1062 с переносом заявок"
```

### Задача 8: Слить дубль Nania 1203 → 1069 (Cosmo SP)

**Files:**
- Create: `database/migrations/2026_07_28_120000_merge_model_1203_into_1069.php`

**⚠️ Первоначальная гипотеза была НЕВЕРНОЙ.** Я считал, что 1069 и 1203 — разные кресла (Cosmo SP и Driver), а ошибка только в поле `tovar_rent.model` у 1203. Основания были весомые: у 1203 весь контент страницы говорил «Driver» — слаг, `title`, `item_name_main`, `l2_name`, alt'ы, keywords, путь к папке фото и описание с техническими размерами Driver (54×45×61 см). Плюс в каталоге уже есть семейство Driver Animals (Panda, Zebra).

**Сотрудник подтвердил обратное (28.07.2026):** кресла **одинаковые**, правильное название — **Cosmo SP**, правильный юнит — **719219** (тот, что дороже). То есть неверна не одна строка в БД, а **вся страница 1203**: её заполнили описанием и названием чужой модели. Фото не помогли рассудить — Nania делает Cosmo SP и Driver на одинаковом корпусе, в расцветке Animals Elefant они неотличимы.

**Вывод для методики:** согласованность десяти полей контента между собой ничего не доказывает, если их заполняли копированием из чужой карточки. Единственный надёжный источник по физическому товару — человек, который держал его в руках. Проверять у сотрудника ДО правки, а не после.

**Состояние на проде:**

| | 1069 (оставляем) | 1203 (удаляем) |
|---|---|---|
| Юнит | 719219, `bron`, куплен 20.06.2018 за 65 руб. | 719242, **`rented_out`, активная сделка 137506**, куплен 29.04.2019 за 45 руб. |
| Тарифы | день 10 / 12 / 15, неделя 18 / 27 / 30 / 35 | день 9.10 / 10.40 / 11.70, неделя 13 / 17 / 20 / 25 |
| Заявки `rent_orders_arch` | 79 | 64 |
| Веб-страница | `avtokreslo_nania_cosmo_sp_animals_elefant` | `avtokreslo_nania_driver_animals_elefant` (живая, в индексе) |
| Прочие ссылки | — | звонков, фото, multi_web, избранного нет |

Тарифы 1069 выше — это и есть «правильные», как сказал сотрудник («который дороже»).

**Про активную аренду юнита 719242 (сделка 137506) — проверено, риск минимальный.** Применённый тариф хранится в самой сделке: `rent_sub_deals_act.tarif_value` / `tarif_step` (заполнены у 688 из 701 продлений). Расчёт возврата и просрочки берёт **последний применённый** тариф оттуда, а не текущий из `rent_tarif_act` — `bb/dogovor_new2.php:2807`, комментарий в коде: «вытягиваем последний примененный тариф». Значит уже оплаченные периоды не пересчитываются.

Единственный нюанс: форма **нового** продления строит список из актуальных тарифов модели (`bb/get_item_tarifs.php:44-47` — `rent_tarif_act WHERE model_id = <модель юнита>`), поэтому там появятся цены 1069. Но рядом форма показывает блок «Последний использованный тариф» с прежней ценой и кнопкой применения (`bb/item_ch_new.php:1014-1022`). Автоматического подорожания нет — выбор оператора. Достаточно предупредить сотрудников.

**Выпрямление цепочки 301 (найдено при локальном прогоне).** На страницу Driver уже вёл редирект с исторического адреса `/prokat/autokresla/avtokreslo_nania_driver_animals_elefant.htm`. Если просто добавить новый 301 с Driver на Cosmo SP, получится цепочка в два перехода: `CheckRedirects` делает один переход за запрос и по цепочке не идёт (`app/Http/Middleware/CheckRedirects.php:100`). Поэтому миграция дополнительно переписывает цель всех редиректов, ведущих на URL Driver, сразу на Cosmo SP. Этот шаг вынесен **до** guard'а существования моделей, чтобы повторный запуск миграции всё равно выпрямлял цепочку — проверено локально: второй прогон дал `chains_flattened: 1` при уже выполненном слиянии.

- [ ] **Шаг 1: Написать миграцию**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Сливает дубль Nania 1203 в 1069. Обе записи — одно и то же кресло
 * Nania Cosmo SP Animals Elefant (подтверждено сотрудником 28.07.2026:
 * «получается одинаковые», правильный юнит 719219, правильное название Cosmo SP).
 *
 * Страницу 1203 заполнили по ошибке названием и описанием модели Driver,
 * поэтому её слаг и весь контент говорят «Driver» — доверять им нельзя.
 *
 * Тарифы 1069 (дороже) остаются как правильные, тарифы 1203 удаляются.
 * Юнит 719242 переезжает на модель 1069.
 */
class MergeModel1203Into1069 extends Migration
{
    private const DUP     = 1203;
    private const KEEP    = 1069;
    private const OLD_URL = '/ru/prokat-detskih-tovarov/autokresla/avtokreselo-naprokat/avtokreslo_nania_driver_animals_elefant';
    private const NEW_URL = '/ru/prokat-detskih-tovarov/autokresla/avtokreselo-naprokat/avtokreslo_nania_cosmo_sp_animals_elefant';

    public function up(): void
    {
        if (!DB::table('tovar_rent')->where('tovar_rent_id', self::DUP)->exists()
            || !DB::table('tovar_rent')->where('tovar_rent_id', self::KEEP)->exists()) {
            logger()->info('MergeModel1203Into1069: пропущено, одной из моделей нет');
            return;
        }

        DB::transaction(function () {
            $moved = [
                'items'            => DB::table('tovar_rent_items')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
                'items_arch'       => DB::table('tovar_rent_items_arch')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
                'rent_orders'      => DB::table('rent_orders')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
                'rent_orders_arch' => DB::table('rent_orders_arch')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
                'zvonki'           => DB::table('zvonki')->where('model_id', self::DUP)->update(['model_id' => self::KEEP]),
            ];

            $deleted = [
                'rent_tarif_act'  => DB::table('rent_tarif_act')->where('model_id', self::DUP)->delete(),
                'rent_tarif_prev' => DB::table('rent_tarif_prev')->where('model_id', self::DUP)->delete(),
                'rent_model_web'  => DB::table('rent_model_web')->where('model_id', self::DUP)->delete(),
                'dop_photos'      => DB::table('dop_photos')->where('model_id', self::DUP)->delete(),
                'multi_web'       => DB::table('multi_web')->where('model_id', self::DUP)->delete(),
                'favorite_tovars' => DB::table('favorite_tovars')->where('model_id', self::DUP)->delete(),
                'tovar_rent'      => DB::table('tovar_rent')->where('tovar_rent_id', self::DUP)->delete(),
            ];

            DB::table('redirects')->updateOrInsert(
                ['source_url' => self::OLD_URL],
                ['target_url' => self::NEW_URL, 'status_code' => 301, 'is_active' => 1]
            );

            logger()->info('MergeModel1203Into1069: слито', ['moved' => $moved, 'deleted' => $deleted]);
        });
    }

    public function down(): void
    {
        // Необратимо: восстановление — из дампа, снятого перед выкладкой.
    }
}
```

- [ ] **Шаг 2: Прогнать локально и проверить**

Run:
```bash
docker compose exec -T app php artisan migrate
docker compose exec -T db mysql --default-character-set=utf8 -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT COUNT(*) model_1203 FROM tovar_rent WHERE tovar_rent_id=1203;
      SELECT model_id, item_inv_n, status FROM tovar_rent_items WHERE item_inv_n IN (719219,719242);
      SELECT COUNT(*) orders_1069 FROM rent_orders_arch WHERE model_id=1069;
      SELECT model_id, step, kol_vo, rent_amount FROM rent_tarif_act WHERE model_id=1069 ORDER BY sort_num, kol_vo;
      SELECT source_url, target_url, status_code FROM redirects WHERE source_url LIKE '%driver_animals_elefant%';"
```
Expected: `model_1203` = 0; оба юнита на модели 1069; заявок у 1069 = 143 (79 + 64); тарифы 1069 не изменились (10/12/15, 18/27/30/35); редирект 301 на месте.

- [ ] **Шаг 3: Проверить страницы после деплоя**

Старый URL Driver должен отдавать 301 на страницу Cosmo SP, новая страница — 200 и показывать оба кресла как один товар с двумя юнитами.

- [ ] **Шаг 4: Коммит**

```bash
git add database/migrations/2026_07_28_120000_merge_model_1203_into_1069.php
git commit -m "chore(db): слить дубль Nania 1203 в 1069 — это одна модель Cosmo SP"
```

### Задача 9: Слить категорию 158 → 178 и поставить 301 — ✅ ВЫПОЛНЕНО 28.07.2026

**Files:**
- Create: `database/migrations/2026_07_27_130000_merge_category_158_into_178.php`

**⚠️ Перед выполнением:** проверить в Google Search Console трафик на
`/ru/prokat-detskih-tovarov/detskaya-komnata/kostyum-princessy-naprokat/costume_printsesy_sophia_prekrasnaya`.

**Уточнение после проверки на проде 28.07.2026 (см. Приложение А, п. 15):** старый URL после слияния **не отдаст 404** — L3-роут резолвит модель только по её слагу, а сегменты раздела/подраздела/категории игнорирует. Меняется только `<link rel="canonical">`, который начнёт указывать на новый путь. Поэтому 301 нужен не для предотвращения 404, а чтобы старый проиндексированный адрес консолидировался быстрее и не жил как soft-дубль. Значит правка менее рискованная, чем казалось, но редирект всё равно ставим.

- [ ] **Шаг 1: Написать миграцию**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Категория 158 «Принцессы Диснея» — дубль 178: лежит в разделе
 * «Прокат детских товаров / Детская комната» и не привязана к subrazdel_category,
 * поэтому в дереве каталога не видна.
 * Переносим единственную модель 730 и её юниты в 178 и ставим 301 на новый URL.
 */
class MergeCategory158Into178 extends Migration
{
    private const DUP  = 158;
    private const KEEP = 178;

    private const OLD_URL = '/ru/prokat-detskih-tovarov/detskaya-komnata/kostyum-princessy-naprokat/costume_printsesy_sophia_prekrasnaya';
    private const NEW_URL = '/ru/karnavalnye-kostyumy/kostumy-zverei/disney-princesses/costume_printsesy_sophia_prekrasnaya';

    public function up(): void
    {
        if (!DB::table('tovar_rent_cat')->where('tovar_rent_cat_id', self::DUP)->exists()) {
            return;
        }

        DB::transaction(function () {
            DB::table('tovar_rent')->where('tovar_rent_cat_id', self::DUP)
                ->update(['tovar_rent_cat_id' => self::KEEP]);
            DB::table('tovar_rent_items')->where('cat_id', self::DUP)->update(['cat_id' => self::KEEP]);
            DB::table('tovar_rent_items_arch')->where('cat_id', self::DUP)->update(['cat_id' => self::KEEP]);
            DB::table('rent_orders')->where('cat_id', self::DUP)->update(['cat_id' => self::KEEP]);
            DB::table('rent_orders_arch')->where('cat_id', self::DUP)->update(['cat_id' => self::KEEP]);

            DB::table('pages')->where('url_key', 'kostyum-princessy-naprokat')->delete();
            DB::table('tovar_rent_cat')->where('tovar_rent_cat_id', self::DUP)->delete();

            DB::table('redirects')->updateOrInsert(
                ['source_url' => self::OLD_URL],
                ['target_url' => self::NEW_URL, 'status_code' => 301, 'is_active' => 1]
            );
        });
    }

    public function down(): void
    {
        // Необратимо: восстановление — из дампа backup_catalog_2026-07-27.sql
    }
}
```

- [ ] **Шаг 2: Прогнать и проверить**

Run:
```bash
docker compose exec -T app php artisan migrate
docker compose exec -T db mysql --default-character-set=utf8 -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT COUNT(*) cat_158 FROM tovar_rent_cat WHERE tovar_rent_cat_id=158;
      SELECT tovar_rent_id, tovar_rent_cat_id FROM tovar_rent WHERE tovar_rent_id=730;
      SELECT rent_cat_name, COUNT(*) n FROM tovar_rent_cat GROUP BY rent_cat_name HAVING n>1;"
```
Expected: `cat_158` = 0; модель 730 в категории 178; дублей имён категорий нет.

- [ ] **Шаг 3: Проверить страницу и редирект**

Открыть новый URL — страница костюма отдаётся; старый URL — 301 на новый.

- [ ] **Шаг 4: Коммит**

```bash
git add database/migrations/2026_07_27_130000_merge_category_158_into_178.php
git commit -m "chore(db): слить дубль категории Принцессы Диснея с 301 на новый URL"
```

### Задача 9а: Удалить направление «строительный инструмент» — ✅ ВЫПОЛНЕНО 28.07.2026

**Files:**
- Created: `database/migrations/2026_07_28_130000_purge_construction_tools_catalog.php`
- Created: `docs/archive/2026-07-28-instrument-broni-i-zayavki.csv`

Обнаружено при разборе категорий вне дерева каталога (Приложение А, п. 8). Владелец подтвердил: инструмент был коллаборацией с другим прокатом, коллаборация прекращена, товары не наши — удалять целиком вместе со сделками.

**Что удалено:** 37 категорий (подразделы 17, 20, 28-33, 36-41 — все несуществующие), 130 моделей, 129 юнитов, 129 тарифов, 129 web-страниц, 2 сделки + 5 sub-deals, 44-46 строк `rent_orders_arch` (33 брони, 1 заявка, остальное — служебные записи доставки/стирки/выдачи), 13 доп. фото.

**Почему именно удаление:**
- 129 чужих юнитов стояли в статусе `to_rent` и попадали в остатки, в знаменатель `utilization` и в исторические срезы инвентаря с июля 2022;
- 129 страниц отдавали 200 с `canonical` на главную (полный путь не строился — раздела «инструмент» в `razdel` нет вообще), то есть сообщали поисковику «мы дубликаты главной»;
- страницы продолжали собирать брони на несуществующий товар: последняя 23.07.2026, всего 33 брони за 2022-2026.

**`status='not_show'` здесь бесполезен:** `ModelWeb::getByUrlName()` статус не фильтрует (`bb/classes/ModelWeb.php:1083-1107`), страница продолжила бы отдавать 200. Удаление строки `rent_model_web` включает штатный фолбэк `L3Controller::showCategoryWithNotice()` — адрес отдаёт 404.

**Финансовый след:** 70.00 руб. одного платежа (август 2022, кусторез Makita DUH523, инв. 1023). Выручка августа 2022 уменьшилась на эту сумму. `LegacyParityTest` зелёный — легаси и MCP считают по одним таблицам.

**История сохранена** перед удалением: `docs/archive/2026-07-28-instrument-broni-i-zayavki.csv` — 46 строк (дата, тип, категория, фирма, модель, телефон, ФИО, комментарий). Одно ФИО осталось в повреждённой кодировке: в БД часть строк записана дважды закодированным UTF-8, и в этой конкретной потерян байт — восстановить без искажения нельзя.

---

### Задача 10: Вычистить висячие ссылки ✅ ВЫПОЛНЕНО (Правка 5)

**Files:**
- Create: `database/migrations/2026_07_28_150000_repoint_orphaned_model_refs.php`

**Первоначальный план был неверен.** Предполагалось «тарифы и фото удалить, заявки оставить как есть». Разбор 28.07.2026 показал, что заявки восстановимы: это не удалённые товары, а **недоделанные слияния дублей** — юниты переносили на правильную модель, дубль удаляли, заявки забывали. У заявки хранится `inv_n`, а по инвентарному номеру однозначно известен сегодняшний владелец юнита.

| Таблица | Строк | Решение | Итог |
|---|---|---|---|
| `rent_tarif_act` / `rent_tarif_prev` | 178 | удалить | 178 удалено |
| `dop_photos` | 15 | удалить (файлы на диске остаются, у 152/362 те же снимки прописаны у живых моделей) | 15 удалено |
| `rent_orders_arch` | 222 | **перепривязать по `inv_n`** | 167 восстановлено, 55 не опознаются |
| `tovar_rent_items_arch` | 3 | **перепривязать** | 3 восстановлено, висяков 0 |

**Восстановленные связи:** 362 Fisher-price «Сад бабочек» → 107 (60 заявок + 2 юнита); 1456 Lorelli манеж-кровать → 1565 «Torino 1» (91 заявка + 1 юнит) и 1458 «Moonlight» (16 заявок).

**Не восстановлены:** 55 веб-заявок моделей 257/479/482 — `inv_n=0` и `cat_id=0`, товар не определяется. 482 по снимкам = «Развивающий мяч Tiny love», живого аналога нет. Заглушки не создавали: выдуманная модель попала бы в справочники и отчёты как настоящая.

**Когда удаляли:** после 06.07.2022 (в этот день все 25 затронутых моделей ещё участвовали в массовом обновлении тарифов — 2863 строки у 955 моделей). Верхней границы нет: журнала действий в БД нет, `users_log` пишет только сделки и оборвался в 2015.

- [ ] **Шаг 1: Написать миграцию**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Удаляет строки-спутники, ссылающиеся на несуществующие модели. Тарифы и фото
 * без модели никогда не будут показаны. Заявки (rent_orders_arch) и архивные
 * юниты НЕ трогаем — они нужны истории и отчётности.
 */
class CleanupDanglingModelRefs extends Migration
{
    public function up(): void
    {
        foreach (['rent_tarif_act', 'rent_tarif_prev', 'dop_photos'] as $table) {
            $deleted = DB::table($table)
                ->where('model_id', '>', 0)
                ->whereNotIn('model_id', function ($q) {
                    $q->select('tovar_rent_id')->from('tovar_rent');
                })
                ->delete();

            logger()->info('CleanupDanglingModelRefs', ['table' => $table, 'deleted' => $deleted]);
        }
    }

    public function down(): void
    {
        // Необратимо: восстановление — из дампа backup_catalog_2026-07-27.sql
    }
}
```

- [ ] **Шаг 2: Прогнать и проверить**

Run:
```bash
docker compose exec -T app php artisan migrate
docker compose exec -T db mysql -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT COUNT(*) dangling_tarifs FROM rent_tarif_act t
      WHERE t.model_id>0 AND NOT EXISTS (SELECT 1 FROM tovar_rent tr WHERE tr.tovar_rent_id=t.model_id);
      SELECT COUNT(*) dangling_photos FROM dop_photos p
      WHERE p.model_id>0 AND NOT EXISTS (SELECT 1 FROM tovar_rent tr WHERE tr.tovar_rent_id=p.model_id);"
```
Expected: оба = 0.

- [ ] **Шаг 3: Коммит**

```bash
git add database/migrations/2026_07_27_140000_cleanup_dangling_model_refs.php
git commit -m "chore(db): удалить тарифы и фото несуществующих моделей"
```

---

## Фаза 5. Защита на уровне схемы

### Задача 11: Уникальный индекс на имя категории

**Files:**
- Create: `database/migrations/2026_07_27_150000_add_unique_index_category_name.php`

**Предусловие:** Задача 9 выполнена (дублей имён категорий больше нет).

- [ ] **Шаг 1: Убедиться, что дублей нет**

Run:
```bash
docker compose exec -T db mysql --default-character-set=utf8 -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT rent_cat_name, COUNT(*) n FROM tovar_rent_cat GROUP BY rent_cat_name HAVING n>1;"
```
Expected: пусто. Если нет — сначала разобрать руками, индекс не ставить.

- [ ] **Шаг 2: Написать миграцию**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUniqueIndexCategoryName extends Migration
{
    public function up(): void
    {
        $dups = DB::select("SELECT rent_cat_name FROM tovar_rent_cat GROUP BY rent_cat_name HAVING COUNT(*) > 1");
        if (!empty($dups)) {
            throw new RuntimeException('В tovar_rent_cat остались дубли имён — индекс не ставим. Разберите вручную.');
        }

        Schema::table('tovar_rent_cat', function ($table) {
            $table->unique('rent_cat_name', 'uniq_rent_cat_name');
        });
    }

    public function down(): void
    {
        Schema::table('tovar_rent_cat', function ($table) {
            $table->dropUnique('uniq_rent_cat_name');
        });
    }
}
```

- [ ] **Шаг 3: Прогнать и проверить, что дубль больше не создаётся**

Run:
```bash
docker compose exec -T app php artisan migrate
docker compose exec -T db mysql --default-character-set=utf8 -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "START TRANSACTION;
      INSERT INTO tovar_rent_cat (main_sub_razdel_id, rent_cat_name, rent_cat_name_en, rent_cat_name_lt, dog_name, cat_url_key, cat_type, cat_sort)
      VALUES (0,'Принцессы Диснея','','','тест','',0,0);
      ROLLBACK;"
```
Expected: `ERROR 1062 Duplicate entry`.

- [ ] **Шаг 4: Проверить, что `category_management.php` показывает ошибку понятно**

Попробовать создать категорию с существующим именем через UI.
Expected: страница не падает молча; если падает — добавить предварительную проверку имени в `Category::save()`.

- [ ] **Шаг 5: Коммит**

```bash
git add database/migrations/2026_07_27_150000_add_unique_index_category_name.php
git commit -m "chore(db): уникальный индекс на имя категории"
```

### Задача 12: Задокументировать правило слияния моделей — ✅ ВЫПОЛНЕНО 28.07.2026

**Files:**
- Modified: `docs/db_notes.md` — добавлены пункты 8, 9, 10 в раздел «Главные ловушки БД»

Сделано по ходу Правки 3 (слияние Nania 1203 → 1069), содержание шире изначально запланированного:

- **п. 8 — применённый тариф хранится в сделке.** `rent_sub_deals_act.tarif_value` / `tarif_step` (заполнены у 688 из 701 продлений; `tarif_id` — только у 4, опираться на него нельзя). Возврат и просрочка считаются по последнему применённому тарифу из сделки (`bb/dogovor_new2.php:2807`), поэтому правка прайса не переоценивает оплаченные периоды. Форма нового продления при этом показывает актуальные тарифы модели (`bb/get_item_tarifs.php:44-47`) плюс кнопку «Последний использованный тариф» (`bb/item_ch_new.php:1014-1022`).
- **п. 9 — `model_id` в 13 таблицах**, искать по шаблону `%model%id%` (иначе пропускается `sd_model_id` — мёртвая колонка, 0 из 517 789 строк). Отдельно зафиксировано, что **карнавальные брони `karn_brons` ссылаются на `inv_n`, а не на модель**, поэтому слияние моделей их не касается.
- **п. 10 — при слиянии правится только `model_id`,** собственный `item_id` строки и `item_inv_n` не трогаются: на инвентарные номера ссылаются сделки и брони.

---

## Фаза 6. Живой поиск + создание через попап + ловля опечаток

Цель фазы: убрать три `<select>` (153 категории, 361 производитель, **~1800 моделей** — последний физически непригоден для выбора глазами) и заменить их одним переиспользуемым виджетом «живой поиск → если не нашлось, создать здесь же». Создание — только для категории (попап) и производителя (попап-lite); «модель» — это не сущность справочника, а название, поэтому там попап не нужен (обоснование в Задаче 15).

**Почему это и есть настоящее лечение дублей:** дубли рождаются не от злого умысла, а от того, что нужное не нашлось. Дропдаун на 1800 позиций гарантирует «не нашлось».

### Задача 13: Общий движок — endpoint поиска + виджет + нормализация

**Files:**
- Create: `bb/lookup.php` — единый AJAX-справочник
- Create: `bb/classes/Similarity.php` — нормализация и оценка похожести
- Create: `bb/js/combobox.js` — виджет живого поиска
- Modify: `bb/Base.php` — подключить `combobox.js` рядом с jQuery (`:502`)

**Interfaces:**
- Produces: `GET /bb/lookup.php?kind=category|producer|model&q=<строка>&cat_id=<N>` → JSON
  ```json
  {"exact": [{"id":1062,"label":"...","meta":"..."}],
   "similar":[{"id":1069,"label":"...","meta":"...","score":0.78}]}
  ```
- Produces: `\bb\classes\Similarity::normalize(string): string`, `::score(string,string): float`,
  `::findSimilar(string $needle, array $haystack, float $min = 0.55): array`

**Дизайн нормализации (два уровня, ровно как просил владелец):**

| Уровень | Правило | Реакция |
|---|---|---|
| **Точный дубль** | совпадение `normalize()` — регистр, пробелы, дефисы, подчёркивания, кавычки не учитываются; гомоглифы кириллица↔латиница приводятся к латинице | жёсткая блокировка сохранения со ссылкой на существующую запись |
| **Похоже (опечатка)** | триграммная схожесть Жаккара ≥ 0.55 по нормализованной строке | мягкий ворнинг: список до 5 кандидатов + чекбокс «Всё равно создать новую» |

> **Почему триграммы, а не `levenshtein()`:** PHP-функции `levenshtein()` и `similar_text()` работают **по байтам**. В UTF-8 кириллический символ — 2 байта, поэтому «Коляска» vs «Коляски» даёт расстояние 2, а не 1, и любой порог на кириллице ведёт себя не так, как на латинице. Триграммы по массиву символов (`preg_split('//u')`) от кодировки не зависят.
>
> **Про гомоглифы:** сейчас в данных таких дублей **нет** (проверено — все 3 группы вариантов написания различаются только пробелом/дефисом). Добавляем на будущее: каталог двуязычный, а «Cybex» с кириллической «С» отличается от латинской на 1 символ из 5 — ни один порог схожести это как подозрительное не отметит, хотя это гарантированный дубль. Стоит одну таблицу замен.

- [ ] **Шаг 1: Написать `bb/classes/Similarity.php`**

```php
<?php

namespace bb\classes;

/**
 * Нормализация и оценка похожести названий справочников (категории, производители,
 * модели). Только чтение, без обращений к БД.
 */
class Similarity
{
    /** Кириллические символы, визуально совпадающие с латинскими. */
    private const HOMOGLYPHS = [
        'а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h',
        'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x',
    ];

    /** Приводит название к сравнимому виду: регистр, разделители, гомоглифы. */
    public static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, self::HOMOGLYPHS);
        return preg_replace('/[\s\-_«»"\'`.,()]+/u', '', $s);
    }

    /** @return string[] массив триграмм нормализованной строки */
    private static function trigrams(string $s): array
    {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $n = count($chars);
        if ($n < 3) {
            return $n ? [implode('', $chars)] : [];
        }
        $out = [];
        for ($i = 0; $i <= $n - 3; $i++) {
            $out[] = $chars[$i] . $chars[$i + 1] . $chars[$i + 2];
        }
        return array_values(array_unique($out));
    }

    /** Схожесть Жаккара по триграммам: 1.0 — идентичны, 0.0 — ничего общего. */
    public static function score(string $a, string $b): float
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);
        if ($na === '' || $nb === '') {
            return 0.0;
        }
        if ($na === $nb) {
            return 1.0;
        }
        $ta = self::trigrams($na);
        $tb = self::trigrams($nb);
        $union = count(array_unique(array_merge($ta, $tb)));
        return $union ? count(array_intersect($ta, $tb)) / $union : 0.0;
    }

    /**
     * @param array<int|string,string> $haystack  ключ => название
     * @return array<int,array{key:int|string,label:string,score:float}> по убыванию похожести
     */
    public static function findSimilar(string $needle, array $haystack, float $min = 0.55): array
    {
        $out = [];
        foreach ($haystack as $key => $label) {
            $score = self::score($needle, (string) $label);
            if ($score >= $min && $score < 1.0) {
                $out[] = ['key' => $key, 'label' => (string) $label, 'score' => round($score, 3)];
            }
        }
        usort($out, fn ($x, $y) => $y['score'] <=> $x['score']);
        return array_slice($out, 0, 5);
    }
}
```

- [ ] **Шаг 2: Проверить нормализацию и схожесть на реальных данных**

Run:
```bash
docker compose exec -T app php -r '
require "/var/www/html/bb/classes/Similarity.php";
use bb\classes\Similarity as S;
var_dump(S::normalize("Maxi-Cosi") === S::normalize("Maxi Cosi"));          // true — точный дубль
var_dump(S::normalize("Kinder Kraft") === S::normalize("Kinderkraft"));     // true
var_dump(S::normalize("Baby Mamy") === S::normalize("Babymamy"));           // true
printf("%.2f\n", S::score("Коляска прогулочная", "Коляска прогулочнная")); // опечатка → высокий
printf("%.2f\n", S::score("Автокресло", "Велосипед"));                     // разное → низкий
printf("%.2f\n", S::score("MaxiCosy", "Maxi-Cosi"));                       // опечатка латиницей
var_dump(S::normalize("Сybex") === S::normalize("Cybex"));                 // гомоглиф → true
'
```
Expected (значения замерены на реальных данных 27.07.2026): три `bool(true)`, затем `0.83`, `0.00`, `0.71`, `bool(true)`.

**Порог 0.55 проверен на всех 361 названии производителей: 7 срабатываний из 64 980 возможных пар.** Из них 5 — настоящие дубли или требующие решения (`Maxi-Cosi`/`Maxi Cosi` 1.00, `Kinder Kraft`/`Kinderkraft` 1.00, `Baby Mamy`/`Babymamy` 1.00, `I love mum`/`I love mum, РФ` 0.75, `Simple Parenting Doona`/`Simple Parenting` 0.72) и 2 — шум (`Medela`/`Medel` 0.75 — разные бренды; `ABC design`/`Design` 0.57). При 0.60 остаётся 6 пар, при 0.50 — 12. **Берём 0.55**: шума почти нет, оператор не привыкает игнорировать ворнинги.

- [ ] **Шаг 3: Написать `bb/lookup.php`**

```php
<?php

session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Similarity.php');

\bb\Base::loginCheck();

use bb\classes\Similarity;

header('Content-Type: application/json; charset=utf-8');

$mysqli = \bb\Db::getInstance()->getConnection();
$kind   = $_GET['kind'] ?? '';
$q      = trim($_GET['q'] ?? '');

/** @return array<int|string,array{label:string,meta:string}> */
$load = function (string $kind) use ($mysqli): array {
    $rows = [];
    if ($kind === 'category') {
        $sql = "SELECT c.tovar_rent_cat_id AS k, c.rent_cat_name AS label,
                       CONCAT(COALESCE(r.name_razdel_text,'вне дерева'), ' / ', COALESCE(sr.name_sub_razdel_text,'—')) AS meta
                FROM tovar_rent_cat c
                LEFT JOIN sub_razdel sr ON sr.id_sub_razdel = c.main_sub_razdel_id
                LEFT JOIN razdel r      ON r.id_razdel      = sr.main_razdel_id
                ORDER BY c.rent_cat_name";
    } elseif ($kind === 'producer') {
        $sql = "SELECT MIN(tovar_rent_id) AS k, producer AS label,
                       CONCAT(COUNT(*), ' моделей') AS meta
                FROM tovar_rent WHERE producer <> '' GROUP BY producer ORDER BY producer";
    } else { // model
        $sql = "SELECT tr.tovar_rent_id AS k,
                       CONCAT(tr.producer, ' ', tr.model) AS label,
                       CONCAT(c.rent_cat_name, COALESCE(CONCAT(', ', NULLIF(tr.color,'')), '')) AS meta
                FROM tovar_rent tr
                LEFT JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = tr.tovar_rent_cat_id
                ORDER BY tr.producer, tr.model";
    }
    $res = $mysqli->query($sql);
    if (!$res) {
        http_response_code(500);
        echo json_encode(['error' => $mysqli->error]);
        exit;
    }
    while ($r = $res->fetch_assoc()) {
        $rows[$r['k']] = ['label' => $r['label'], 'meta' => $r['meta']];
    }
    return $rows;
};

if (!in_array($kind, ['category', 'producer', 'model'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'unknown kind']);
    exit;
}

$all    = $load($kind);
$labels = array_map(fn ($r) => $r['label'], $all);

$exact   = [];
$similar = [];

if ($q !== '') {
    $qn = Similarity::normalize($q);

    foreach ($all as $k => $r) {
        if (Similarity::normalize($r['label']) === $qn) {
            $exact[] = ['id' => $k, 'label' => $r['label'], 'meta' => $r['meta']];
        }
    }

    foreach (Similarity::findSimilar($q, $labels) as $hit) {
        $similar[] = [
            'id'    => $hit['key'],
            'label' => $hit['label'],
            'meta'  => $all[$hit['key']]['meta'],
            'score' => $hit['score'],
        ];
    }
}

// подстрочные совпадения для самого живого поиска (до 20 позиций)
$matches = [];
foreach ($all as $k => $r) {
    if ($q === '' || mb_stripos($r['label'], $q, 0, 'UTF-8') !== false) {
        $matches[] = ['id' => $k, 'label' => $r['label'], 'meta' => $r['meta']];
        if (count($matches) >= 20) {
            break;
        }
    }
}

echo json_encode(compact('matches', 'exact', 'similar'), JSON_UNESCAPED_UNICODE);
```

- [ ] **Шаг 4: Проверить endpoint**

Run:
```bash
docker compose exec -T app php -l bb/lookup.php
curl -s "http://localhost/bb/lookup.php?kind=producer&q=Maxi%20Cosi" | head -c 400
```
Expected: JSON, в котором `exact` содержит и `Maxi Cosi`, и `Maxi-Cosi` (нормализация их склеила).

- [ ] **Шаг 5: Написать виджет `bb/js/combobox.js`**

Один виджет на все три поля: текстовый `<input>` + выпадающий список результатов + скрытый `<input>` с выбранным значением + блок ворнингов.

```javascript
/* Живой поиск по справочникам bb/. Использование:
   <input type="text" class="tt-combobox" data-kind="producer"
          data-target="producer_select_new" data-create-url="/bb/producer_new_popup.php">
   <input type="hidden" name="producer_select_new" id="producer_select_new" value="">   */
(function ($) {
	function render($box, data, $warn) {
		$box.empty();
		data.matches.forEach(function (m) {
			$('<div class="tt-cb-item"></div>')
				.text(m.label)
				.append($('<span class="tt-cb-meta"></span>').text(' — ' + m.meta))
				.data('item', m)
				.appendTo($box);
		});
		$box.toggle(data.matches.length > 0);

		$warn.empty();
		if (data.exact.length) {
			$warn.append($('<div class="tt-cb-error"></div>').text(
				'Уже существует: ' + data.exact.map(function (e) { return e.label + ' (' + e.meta + ')'; }).join('; ')
			));
		} else if (data.similar.length) {
			$warn.append($('<div class="tt-cb-warn"></div>').text(
				'Похоже на существующие: ' + data.similar.map(function (s) {
					return s.label + ' (' + Math.round(s.score * 100) + '%)';
				}).join('; ')
			));
		}
	}

	$(function () {
		$('.tt-combobox').each(function () {
			var $input = $(this);
			var $target = $('#' + $input.data('target'));
			var $box = $('<div class="tt-cb-list"></div>').insertAfter($input).hide();
			var $warn = $('<div class="tt-cb-warnings"></div>').insertAfter($box);
			var timer = null;

			$input.on('input', function () {
				$target.val('');                       // ручной ввод сбрасывает выбор из справочника
				clearTimeout(timer);
				var q = $input.val();
				timer = setTimeout(function () {
					$.getJSON('/bb/lookup.php', { kind: $input.data('kind'), q: q })
						.done(function (data) { render($box, data, $warn); })
						.fail(function () { $box.hide(); });
				}, 250);
			});

			$box.on('click', '.tt-cb-item', function () {
				var item = $(this).data('item');
				$input.val(item.label);
				$target.val(item.id);
				$box.hide();
				$warn.empty();
			});

			$(document).on('click', function (e) {
				if (!$(e.target).closest($input).length && !$(e.target).closest($box).length) {
					$box.hide();
				}
			});
		});
	});
})(jQuery);
```

- [ ] **Шаг 6: Подключить виджет глобально в `bb/`**

В `bb/Base.php` сразу после строки подключения jQuery (`:502`) добавить:

```php
    <script src="/bb/js/combobox.js"></script>
    <style>
        .tt-cb-list{position:absolute;z-index:50;background:#fff;border:1px solid #aaa;max-height:260px;overflow:auto;min-width:320px}
        .tt-cb-item{padding:4px 8px;cursor:pointer}
        .tt-cb-item:hover{background:#eef}
        .tt-cb-meta{color:#777;font-size:11px}
        .tt-cb-error{color:#a00;font-weight:bold;font-size:12px}
        .tt-cb-warn{color:#a60;font-size:12px}
    </style>
```

> jQuery в `bb/` уже подключена глобально из `bb/Base.php:502` — новая зависимость не нужна. Select2/Choices.js сознательно не берём: свой виджет — 60 строк и полный контроль над показом «похожих».

- [ ] **Шаг 7: Коммит**

```bash
git add bb/lookup.php bb/classes/Similarity.php bb/js/combobox.js bb/Base.php
git commit -m "feat(bb): движок живого поиска по справочникам с ловлей дублей и опечаток"
```

### Задача 14: Категория — живой поиск + создание через попап ✅ ВЫПОЛНЕНО (Правка 8)

**Files:**
- Create: `bb/classes/Similarity.php`, `bb/ajax_category_suggest.php`, `bb/ajax_category_create.php`
- Create: `bb/assets/js/category_picker.js`, `bb/assets/styles/category_picker.css`
- Modify: `bb/tovar_new_mod.php`, `bb/classes/Category.php`

**Отклонения от плана.** Вместо общего `bb/lookup.php` и `bb/js/combobox.js` (Задача 13) сделан узкий виджет под категорию: обобщать имеет смысл, когда есть второй потребитель — производитель и модель идут следующими, тогда и вынесем общее. `bb/Base.php` не трогали: страница подключает CSS/JS сама, без jQuery — владелец просил обойтись чистым JS.

**Что закрыто попутно (блокеры, из-за которых страница не работала):**
- позиционный `INSERT INTO tovar_rent_cat VALUES('','$name','$dog')` на строках 99 и 245 — 3 значения в таблицу из 9 колонок, категорию завести было нельзя. Создание переехало в `ajax_category_create.php` через `\bb\classes\Category`;
- `select_ch3()` звал `getXmlHttp()`, которого нет ни в одном файле проекта, — поле «для договора (ед.ч.)» намертво зависало на «... ждите ...». Теперь `dog_name` приходит вместе с подсказкой, второй запрос не нужен;
- `Category::save()` не писал `subrazdel_category` — категория рождалась вне дерева каталога. Это источник тех 57 категорий вне дерева из аудита; починка в самом классе, поэтому чинит и `category_management.php`.

**Убрано осознанно:** переименование категории со страницы модели (`cat_edit()` + ветка `cat_edit_status`). Оно правило только название и `dog_name`, оставляя `cat_url_key` от старого имени, — то есть тихо разъезжались имя и адрес. Полное редактирование живёт в `/bb/category_management.php`, на него стоит ссылка.

**Порог похожести 0.55** подтверждён тестами на реальных названиях: «Манежи-кроватки» ~ «Манежи-кровати» = 0.769 ловится, «Санки» ~ «Манежи-кровати» отсекается. Гомоглифы («Сybex» русской С) и дефисы нормализуются.

**Проверка:** 20 тестов (`Similarity`, SQL подсказок, создание категории с привязкой к дереву и идемпотентность повторного `save()`) — все зелёные. PHP-замечаний на странице: было 3979, стало 0.

**Обоснование выбора «попап, а не inline-поля»:** категории нужно 7 полей, чтобы родиться правильно (`main_sub_razdel_id`, имя ru/en/lt, `dog_name`, `cat_url_key`, `cat_type`, `cat_sort`). Разложить их inline на странице модели — это спрятать вторую форму внутри первой. Попап отделяет их визуально и логически, а запись идёт через **тот же** `\bb\classes\Category`, что и `category_management.php` — без дублирования SQL.

- [ ] **Шаг 1: Починить `Category::save()` — создавать связь с деревом**

В `bb/classes/Category.php` в `save()`, в ветке создания, после `$this->setId($mysqli->insert_id);` добавить:

```php
            // без строки в subrazdel_category категория не появится в дереве каталога
            if ($this->main_sub_razdel_id > 0) {
                $link = "INSERT INTO subrazdel_category SET id_sub_razdel='" . (int) $this->main_sub_razdel_id . "', tovar_rent_cat_id='" . (int) $this->getId() . "'";
                if (!$mysqli->query($link)) {
                    die('Сбой при доступе к базе данных: ' . $link . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
                }
            }
```

- [ ] **Шаг 2: Проверить, что новая категория попадает в дерево**

Run:
```bash
docker compose exec -T app php -l bb/classes/Category.php
```
Затем создать тестовую категорию через `bb/category_management.php` с выбранным подразделом и проверить:
```bash
docker compose exec -T db mysql --default-character-set=utf8 -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT c.tovar_rent_cat_id, c.rent_cat_name, sc.id_sub_razdel FROM tovar_rent_cat c
      LEFT JOIN subrazdel_category sc ON sc.tovar_rent_cat_id=c.tovar_rent_cat_id
      ORDER BY c.tovar_rent_cat_id DESC LIMIT 1;"
```
Expected: у новой категории `id_sub_razdel` не `NULL`.

- [ ] **Шаг 3: Написать попап `bb/category_new_popup.php`**

Обязательные поля: подраздел (селект из `sub_razdel`), имя, имя для договора. `cat_url_key` предлагается транслитом от имени с возможностью правки; `cat_type` наследуется от подраздела; `cat_sort` = 0.

```php
<?php

session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Similarity.php');

\bb\Base::loginCheck();

use bb\classes\Category;
use bb\classes\Similarity;

$mysqli = \bb\Db::getInstance()->getConnection();

if (($_POST['action'] ?? '') === 'create') {
    $name    = trim($_POST['name'] ?? '');
    $dogName = trim($_POST['dog_name'] ?? '');
    $subId   = (int) ($_POST['main_sub_razdel_id'] ?? 0);
    $urlKey  = trim($_POST['cat_url_key'] ?? '');
    $force   = !empty($_POST['force']);

    if ($name === '' || $dogName === '' || $subId <= 0 || $urlKey === '') {
        die('Заполните подраздел, название, название для договора и URL-ключ.');
    }

    $existing = [];
    $res = $mysqli->query("SELECT tovar_rent_cat_id, rent_cat_name FROM tovar_rent_cat");
    while ($r = $res->fetch_assoc()) {
        $existing[$r['tovar_rent_cat_id']] = $r['rent_cat_name'];
    }

    foreach ($existing as $id => $label) {
        if (Similarity::normalize($label) === Similarity::normalize($name)) {
            die('Категория «' . htmlspecialchars($label) . '» (ID ' . $id . ') уже существует. Выберите её в списке.');
        }
    }

    $similar = Similarity::findSimilar($name, $existing);
    if ($similar && !$force) {
        echo '<p style="color:#a60">Похоже на существующие категории:</p><ul>';
        foreach ($similar as $s) {
            echo '<li>' . htmlspecialchars($s['label']) . ' (' . round($s['score'] * 100) . '%)</li>';
        }
        echo '</ul><form method="post">'
            . '<input type="hidden" name="action" value="create">'
            . '<input type="hidden" name="name" value="' . htmlspecialchars($name) . '">'
            . '<input type="hidden" name="dog_name" value="' . htmlspecialchars($dogName) . '">'
            . '<input type="hidden" name="main_sub_razdel_id" value="' . $subId . '">'
            . '<input type="hidden" name="cat_url_key" value="' . htmlspecialchars($urlKey) . '">'
            . '<input type="hidden" name="force" value="1">'
            . '<button type="submit">Всё равно создать новую</button> '
            . '<button type="button" onclick="window.close()">Отмена</button>'
            . '</form>';
        exit;
    }

    $subRow  = $mysqli->query("SELECT cat_type FROM sub_razdel WHERE id_sub_razdel = " . $subId)->fetch_assoc();

    $cat = new Category();
    $cat->setMainSubRazdelId($subId);
    $cat->setName($name);
    $cat->setName('', 'en');
    $cat->setName('', 'lt');
    $cat->setDogName($dogName);
    $cat->setCatUrlKey($urlKey);
    $cat->setCatType((int) ($subRow['cat_type'] ?? 0));
    $cat->setCatSort(0);
    $cat->save();

    // отдаём результат родительскому окну и закрываемся
    echo '<script>window.opener.ttCategoryCreated(' . (int) $cat->getId() . ', '
        . json_encode($name, JSON_UNESCAPED_UNICODE) . '); window.close();</script>';
    exit;
}

// ---- форма ----
echo '<h3>Новая категория</h3><form method="post">
    <input type="hidden" name="action" value="create">
    Подраздел (обязательно):<br><select name="main_sub_razdel_id" required><option value="">— выберите —</option>';
$res = $mysqli->query("SELECT sr.id_sub_razdel, sr.name_sub_razdel_text, r.name_razdel_text
                       FROM sub_razdel sr
                       LEFT JOIN razdel r ON r.id_razdel = sr.main_razdel_id
                       ORDER BY r.name_razdel_text, sr.name_sub_razdel_text");
while ($r = $res->fetch_assoc()) {
    echo '<option value="' . $r['id_sub_razdel'] . '">'
        . htmlspecialchars(($r['name_razdel_text'] ?: 'вне раздела') . ' / ' . $r['name_sub_razdel_text'])
        . '</option>';
}
echo '</select><br><br>
    Название:<br><input type="text" name="name" id="cname" size="40" required><br><br>
    Название для договора (ед. ч.):<br><input type="text" name="dog_name" size="40" required><br><br>
    URL-ключ:<br><input type="text" name="cat_url_key" id="ckey" size="40" required><br><br>
    <button type="submit">Создать</button>
    <button type="button" onclick="window.close()">Отмена</button>
</form>';
```

- [ ] **Шаг 4: Добавить автотранслит URL-ключа в попап**

В конец `bb/category_new_popup.php` добавить:

```php
echo '<script>
    var TRANSLIT = {а:"a",б:"b",в:"v",г:"g",д:"d",е:"e",ё:"e",ж:"zh",з:"z",и:"i",й:"y",к:"k",л:"l",м:"m",
        н:"n",о:"o",п:"p",р:"r",с:"s",т:"t",у:"u",ф:"f",х:"h",ц:"c",ч:"ch",ш:"sh",щ:"sch",ъ:"",ы:"y",
        ь:"",э:"e",ю:"yu",я:"ya"};
    document.getElementById("cname").addEventListener("input", function () {
        var key = this.value.toLowerCase().split("").map(function (ch) {
            return (ch in TRANSLIT) ? TRANSLIT[ch] : ch;
        }).join("").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
        document.getElementById("ckey").value = key;
    });
</script>';
```

- [ ] **Шаг 5: Заменить `<select>` категории на виджет + кнопку попапа**

В `bb/tovar_new_mod.php` заменить блок `<select name="cat_select_new">` на:

```php
			<input type="text" class="tt-combobox" data-kind="category" data-target="cat_select_new"
			       size="40" autocomplete="off" placeholder="начните вводить название категории"
			       value="' . good_print($cat_def['rent_cat_name'] ?? '') . '">
			<input type="hidden" name="cat_select_new" id="cat_select_new" value="' . (int) ($cat_id ?? 0) . '">
			<button type="button" onclick="window.open(\'/bb/category_new_popup.php\',\'newcat\',\'width=560,height=560\')">+ новая категория</button>
```

- [ ] **Шаг 6: Принять результат из попапа**

В скриптовый блок `bb/tovar_new_mod.php` добавить:

```javascript
	function ttCategoryCreated(id, name) {
		document.getElementById('cat_select_new').value = id;
		var $inp = document.querySelector('.tt-combobox[data-kind="category"]');
		if ($inp) { $inp.value = name; }
		select_ch3('cat_select_new', 'cat_input_new');   // подтянуть dog_name для договора
	}
```

- [ ] **Шаг 7: Убрать заглушку из Задачи 2**

Вернуть в ветках `сохранить` и `обновить` работу с `$cat_id`, оставив проверку `$cat_id <= 0` (теперь она означает «оператор не выбрал категорию из справочника»), и удалить текст-подсказку про `category_management.php` из Шага 1 Задачи 2.

- [ ] **Шаг 8: Проверить сквозной сценарий**

1. Открыть `bb/tovar_new_mod.php`, ввести в поле категории «прогул» → в списке появляются подходящие категории с разделом/подразделом.
2. Нажать «+ новая категория», ввести имя, похожее на существующее («Коляски прогулочние») → попап показывает ворнинг с процентами и кнопкой «Всё равно создать новую».
3. Ввести имя, точно совпадающее с существующим по нормализации («коляски-прогулочные») → попап отказывает со ссылкой на существующую.
4. Создать реально новую → попап закрывается, поле в родительском окне заполнено, `cat_select_new` содержит ID, поле «для договора» подтянулось.
5. Проверить связь с деревом:
```bash
docker compose exec -T db mysql --default-character-set=utf8 -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT c.tovar_rent_cat_id, c.rent_cat_name, c.cat_url_key, sc.id_sub_razdel
      FROM tovar_rent_cat c LEFT JOIN subrazdel_category sc ON sc.tovar_rent_cat_id=c.tovar_rent_cat_id
      ORDER BY c.tovar_rent_cat_id DESC LIMIT 1;"
```
Expected: `cat_url_key` заполнен, `id_sub_razdel` не `NULL`.

- [ ] **Шаг 9: Коммит**

```bash
git add bb/category_new_popup.php bb/tovar_new_mod.php bb/classes/Category.php
git commit -m "feat(bb): создание категории из страницы модели через попап с проверкой дублей"
```

### Задача 15: Модель — живой поиск вместо селекта на 1800 позиций

**Files:**
- Modify: `bb/tovar_new_mod.php:601` (селект модели), `:534-543` (запросы справочников)

**Почему для модели попап НЕ нужен:** «модель» — не запись справочника, а строка `tovar_rent.model`. Уникальность модели определяется четвёркой **категория + производитель + название + цвет**, а все эти поля уже есть на этой странице — то есть страница `tovar_new_mod.php` **и есть** форма создания модели. Попап был бы формой внутри формы с теми же полями.

Хуже того, текущий селект прямо провоцирует ошибки: он заполняется `SELECT DISTINCT model FROM tovar_rent` — **глобально, без привязки к производителю и категории** (`:542`). Выбрав в нём «Polly», оператор берёт название, принадлежащее и Chicco, и GB. Поэтому селект убираем, оставляем текстовое поле с живой подсказкой «уже есть такие модели» — с производителем и категорией в каждой строке.

- [ ] **Шаг 1: Заменить селект модели на поле с подсказкой**

В `bb/tovar_new_mod.php` заменить блок `<select name="model_select_new">` и парный `<input name="model_input_new">` на:

```php
			<input type="text" name="model_input_new" id="model_input_new" class="tt-combobox"
			       data-kind="model" data-target="model_dup_id" size="40" autocomplete="off"
			       placeholder="название модели" value="' . good_print($model_def['model']) . '" required>
			<input type="hidden" name="model_dup_id" id="model_dup_id" value="">
			<div style="font-size:12px;color:#777">Если модель уже есть — она появится в подсказке; откройте её вместо создания новой.</div>
```

> `model_select_new` больше не отправляется, поэтому в ветках `сохранить`/`обновить` строки
> `$model_select_new == '0' ? ... : ...` заменяются на `$model_name = trim($model_input_new);`

- [ ] **Шаг 2: Убрать ненужный запрос справочника моделей**

Удалить `bb/tovar_new_mod.php:542-543` (`SELECT DISTINCT model FROM tovar_rent`) и цикл его вывода — 1800 `<option>` больше не рендерятся, страница становится ощутимо легче.

- [ ] **Шаг 3: Проверить**

Run: `docker compose exec -T app php -l bb/tovar_new_mod.php`
В браузере: ввести «Bambino» → подсказка показывает `BAMBOLA Bambino Одуванчик — Автокресла, т.синий/бирюза`. Сохранить модель с полностью совпадающей четвёркой → срабатывает блокировка из Задачи 3.

- [ ] **Шаг 4: Коммит**

```bash
git add bb/tovar_new_mod.php
git commit -m "feat(bb): живой поиск модели вместо глобального селекта на 1800 позиций"
```

### Задача 16: Производитель — живой поиск, попап-lite и подтягивание логотипа

**Files:**
- Modify: `bb/tovar_new_mod.php:586` (селект производителя)
- Create: `bb/producer_new_popup.php`

**Контекст (см. Приложение А, п. 9):** справочника производителей нет — `producer` это varchar в `tovar_rent`, 361 уникальное значение. Логотип живёт **не у производителя, а у каждой модели** (`rent_model_web.logo`), поэтому у одного бренда бывает 2–3 разных пути к логотипу (Fisher-price — 3, «РБ» — 3, Britax Romer — 2), а **545 из 974** русских страниц вообще без логотипа. Полноценный справочник производителей — Фаза 7, здесь делаем то, что даёт эффект сразу.

- [ ] **Шаг 1: Заменить селект производителя на виджет + попап**

```php
			<input type="text" name="producer_input_new" id="producer_input_new" class="tt-combobox"
			       data-kind="producer" data-target="producer_dup_id" size="30" autocomplete="off"
			       placeholder="начните вводить фирму" value="' . good_print($model_def['producer']) . '" required>
			<input type="hidden" name="producer_dup_id" id="producer_dup_id" value="">
			<button type="button" onclick="window.open(\'/bb/producer_new_popup.php\',\'newprod\',\'width=520,height=420\')">+ новая фирма</button>
```

> В ветках `сохранить`/`обновить` `$producer_name` теперь всегда `trim($producer_input_new)`.

- [ ] **Шаг 2: Написать попап-lite производителя**

Попап нужен ровно для одного: заставить оператора увидеть похожие названия до того, как он создаст «Maxi Cosi» рядом с «Maxi-Cosi».

```php
<?php

session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Similarity.php');

\bb\Base::loginCheck();

use bb\classes\Similarity;

$mysqli = \bb\Db::getInstance()->getConnection();

if (($_POST['action'] ?? '') === 'use') {
    $name  = trim($_POST['name'] ?? '');
    $force = !empty($_POST['force']);

    if ($name === '') {
        die('Введите название фирмы.');
    }

    $existing = [];
    $res = $mysqli->query("SELECT DISTINCT producer FROM tovar_rent WHERE producer <> ''");
    while ($r = $res->fetch_assoc()) {
        $existing[$r['producer']] = $r['producer'];
    }

    foreach ($existing as $label) {
        if (Similarity::normalize($label) === Similarity::normalize($name)) {
            die('Такая фирма уже есть: «' . htmlspecialchars($label) . '». Выберите её в списке — иначе появится дубль.');
        }
    }

    $similar = Similarity::findSimilar($name, $existing);
    if ($similar && !$force) {
        echo '<p style="color:#a60">Похоже на существующие фирмы:</p><ul>';
        foreach ($similar as $s) {
            echo '<li>' . htmlspecialchars($s['label']) . ' (' . round($s['score'] * 100) . '%)</li>';
        }
        echo '</ul><form method="post">
                <input type="hidden" name="action" value="use">
                <input type="hidden" name="name" value="' . htmlspecialchars($name) . '">
                <input type="hidden" name="force" value="1">
                <button type="submit">Всё равно новая фирма</button>
                <button type="button" onclick="window.close()">Отмена</button>
              </form>';
        exit;
    }

    echo '<script>window.opener.ttProducerChosen(' . json_encode($name, JSON_UNESCAPED_UNICODE) . '); window.close();</script>';
    exit;
}

echo '<h3>Новая фирма</h3><form method="post">
        <input type="hidden" name="action" value="use">
        Название:<br><input type="text" name="name" size="34" required><br><br>
        <button type="submit">Проверить и использовать</button>
        <button type="button" onclick="window.close()">Отмена</button>
      </form>';
```

- [ ] **Шаг 3: Принять результат в родительском окне**

В скриптовый блок `bb/tovar_new_mod.php`:

```javascript
	function ttProducerChosen(name) {
		var $inp = document.getElementById('producer_input_new');
		if ($inp) { $inp.value = name; }
		document.getElementById('producer_dup_id').value = '';
	}
```

- [ ] **Шаг 4: Подтягивать логотип от других моделей того же производителя**

В `bb/lookup.php` в ветке `producer` добавить в `meta` самый частый непустой логотип бренда, чтобы фронт мог его предложить:

```php
        $sql = "SELECT MIN(tr.tovar_rent_id) AS k, tr.producer AS label,
                       CONCAT(COUNT(*), ' моделей',
                              COALESCE(CONCAT(', лого: ', NULLIF(MAX(w.logo), '')), ', лого нет')) AS meta
                FROM tovar_rent tr
                LEFT JOIN rent_model_web w ON w.model_id = tr.tovar_rent_id AND w.lang = 'ru'
                WHERE tr.producer <> '' GROUP BY tr.producer ORDER BY tr.producer";
```

- [ ] **Шаг 5: Проверить**

В браузере ввести в поле фирмы «Maxi» → подсказка показывает оба написания и их логотипы. Нажать «+ новая фирма», ввести «Maxi Cosi» → отказ (нормализованный дубль). Ввести «MaxiCosy» → ворнинг с процентом и кнопкой «Всё равно новая фирма».

- [ ] **Шаг 6: Коммит**

```bash
git add bb/tovar_new_mod.php bb/producer_new_popup.php bb/lookup.php
git commit -m "feat(bb): живой поиск фирмы с проверкой дублей и подсказкой логотипа"
```

### Задача 17: Нормализовать существующие названия производителей

**Files:**
- Create: `database/migrations/2026_07_27_160000_normalize_producer_names.php`

> ⚠️ **Ловушка MySQL, из-за которой очевидное условие не работает.** Коллации `utf8mb3_general_ci` — PAD SPACE: при сравнении **замыкающие** пробелы игнорируются. Поэтому `WHERE producer <> TRIM(producer)` находит только названия с **ведущим** пробелом, а `'Chicco '` (77 моделей у `'Chicco'` против 1 у `'Chicco '`) считается равным `'Chicco'` и в выборку не попадает. Правильно — либо `WHERE producer <> BINARY TRIM(producer)`, либо безусловный `UPDATE` без `WHERE`. В миграции ниже используется безусловный вариант.

- [ ] **Шаг 1: Написать миграцию**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Приводит названия производителей к одному написанию (см. Приложение А, п. 9-10).
 * Написание-победитель выбрано по числу моделей.
 *
 * ВНИМАНИЕ: TRIM выполняется БЕЗ WHERE — коллация PAD SPACE считает 'Chicco ' = 'Chicco',
 * поэтому условие `producer <> TRIM(producer)` пропустило бы замыкающие пробелы.
 */
class NormalizeProducerNames extends Migration
{
    /** Написания, которые точно являются одним и тем же брендом. */
    private const MERGE = [
        'Maxi Cosi'    => 'Maxi-Cosi',
        'Babymamy'     => 'Baby Mamy',
        'Kinder Kraft' => 'Kinderkraft',
        'Chi lok BO'   => 'Chi Lok Bo',
        'MEDELA'       => 'Medela',
        'THULE'        => 'Thule',
    ];

    private const TABLES = ['tovar_rent', 'tovar_rent_items', 'tovar_rent_items_arch'];

    public function up(): void
    {
        // 1. схлопнуть варианты написания (сравнение по байтам, иначе ci-коллация
        //    сматчит и то написание, которое мы хотим оставить)
        foreach (self::MERGE as $from => $to) {
            foreach (self::TABLES as $table) {
                DB::statement("UPDATE {$table} SET producer = ? WHERE BINARY producer = ?", [$to, $from]);
            }
        }

        // 2. убрать ведущие/замыкающие пробелы — безусловно
        foreach (self::TABLES as $table) {
            DB::statement("UPDATE {$table} SET producer = TRIM(producer)");
        }
        DB::statement("UPDATE tovar_rent SET model = TRIM(model)");
    }

    public function down(): void
    {
        // Обратное схлопывание невозможно: какое написание было у какой модели — не сохраняем.
    }
}
```

- [ ] **Шаг 2: Прогнать и проверить, что скрытых вариантов не осталось**

Run:
```bash
docker compose exec -T app php artisan migrate
docker compose exec -T db mysql --default-character-set=utf8 -utiktakby_tiktak -pVai7evahch tiktakby_tiktak \
  -e "SELECT COUNT(DISTINCT producer) producers FROM tovar_rent;
      SELECT COUNT(*) untrimmed FROM tovar_rent WHERE producer <> BINARY TRIM(producer) OR model <> BINARY TRIM(model);
      SELECT COUNT(*) byte_variant_groups FROM (
        SELECT producer FROM tovar_rent GROUP BY CAST(producer AS BINARY)
      ) x GROUP BY producer HAVING COUNT(*) > 1;"
```
Expected: `producers` = 358 (было 361 — минус три группы, различавшиеся пробелом/дефисом; регистровые варианты `DISTINCT` и так не показывал), `untrimmed` = 0, третий запрос пуст.

- [ ] **Шаг 3: Коммит**

```bash
git add database/migrations/2026_07_27_160000_normalize_producer_names.php
git commit -m "chore(db): привести написания производителей к одному виду"
```

- [ ] **Шаг 4: Вынести спорные пары на решение владельца (НЕ автоматизировать)**

Fuzzy-скан нашёл две пары, которые похожи на один бренд, но требуют знания предметной области. Свести их миграцией без подтверждения нельзя:

| Пара | Моделей | Категории | Вопрос |
|---|---|---|---|
| `I love mum` / `I love mum, РФ` | 5 / 11 | обе в cat 12 | Один бренд? Суффикс «, РФ» выглядит как пометка страны, а не другой бренд |
| `Simple Parenting` / `Simple Parenting Doona` | 3 / 3 | 59+73 / 75 | «Doona» — продуктовая линейка того же бренда или отдельное написание? |

Ложное срабатывание того же скана, которое сливать **не надо**: `Medela` (молокоотсосы, cat 34) ~ `Medel` (ингаляторы, cat 20) — 0.75 схожести, но это разные производители.

---

## Фаза 7. Справочник производителей (отдельный проект, после Фазы 6)

Не расписываю задачами — сначала нужно решение владельца по объёму.

**Проблема:** 361 производитель как свободный текст в трёх таблицах; логотип хранится per-model в `rent_model_web.logo`, из-за чего 545 из 974 страниц без логотипа, а у одного бренда до трёх разных путей к картинке.

**Целевое состояние:** таблица `producers` (`id`, `name`, `name_norm` UNIQUE, `logo_url`, `is_active`) + `tovar_rent.producer_id`. Выбрал фирму → логотип подставился сам, дубли невозможны на уровне индекса.

**Объём:** миграция + бэкфилл 361 значения (с ручной вычиткой спорных) + правки `L3Page`/`ModelWeb` на чтение логотипа из справочника + UI управления производителями + обратная совместимость на время перехода (оставить `producer` varchar как денормализованную копию, чтобы не переписывать разом все отчёты и `bb/`-страницы).

**Что даёт:** единственное место, где заводится бренд; логотип автоматически на всех страницах бренда; фильтр по производителю на сайте (`SearchController`) начинает работать по ID, а не по строке.

---

## Фаза 8. Шрифт в договоре курьера (ОТДЕЛЬНЫЙ ЧАТ, в самом конце)

Задача от сотрудника (28.07.2026, в переписке по креслам Nania):

> «Посмотри пожалуйста договора курьера. Мы вручную исправляем шрифт. Сейчас печатает 12, нужно сделать 10. Чтобы влазило в 2 страницы.»

Суть: договор, который печатает курьер, выходит на 3 страницы вместо 2, и сотрудники каждый раз правят размер шрифта руками перед печатью. Нужно, чтобы печаталось сразу 10-м и помещалось в 2 страницы.

**Делать в отдельном чате, после того как закончим чистку каталога и страницу «Новая модель».**

Точки входа для разбора (не проверял вглубь, это стартовая разведка):

- `bb/bb_courier.css` — стили страницы курьера, `font-size: 12px` в строках 98 и 163. Подключается из `bb/cur_page2.php`.
- `bb/dogovor_new_style.css` — стили самого договора, размеры от 12px до 17px вперемешку, часть с `!important`.
- Файлы генерации договоров: `bb/dogovor.php`, `bb/dogovor_new.php`, `bb/dogovor_new2.php`, `bb/dogovor_new3.php`, `bb/dogovor_new4.php`, `bb/dogovor_multi_new.php` — нужно сначала выяснить, какой из них печатает курьер.

Что уточнить у сотрудника перед правкой: печать идёт из браузера (Ctrl+P) или через отдельную кнопку; какой именно договор — обычный, мультидоговор или курьерский; и обязательно посмотреть на реальную распечатку, потому что «влезает в 2 страницы» зависит ещё от полей и межстрочного интервала, а не только от кегля.

---

## Приложение А. Полная карта находок по БД (аудит 27.07.2026)

Прод и локальный снапшот совпали по всем пунктам. Все запросы — только чтение.

**1. Дубли категорий:** ровно один — «Принцессы Диснея» ID 158 и 178. Разбор в основной части плана (Задача 9).

**2. Дубли моделей по ключу «категория + производитель + название + цвет»:** 4 пары — (819, 850), (1273, 1274), (1062, 1063), (1069, 1203). Разбор и вердикт по каждой — в таблице в начале плана.

**3. Совпадения «производитель + название» без учёта категории и цвета:** 76 групп. Основная масса — легитимные разные товары (один бренд, разные категории/цвета), поэтому как основание для чистки не годится. Использовать только как материал для ручной вычитки. Показательные: `Fisher-price "Развивающий коврик"` — 4 модели в 4 категориях; `Cybex Balios S` — 4 модели в 3 категориях.

**4. Дубли web-страниц:** нет. `rent_model_web` чист и по `(model_id, lang)`, и по `(page_addr, lang)` — следствие PR #246.

**5. Дубли `cat_url_key`:** нет.

**6. Модели-сироты** (ни активных, ни архивных юнитов, ни web-страницы): **60**. Из них **39 не имеют ни одной ссылки** ни в одной из 13 таблиц с `model_id` → удаляются (Задача 6). Остальные 21 — «надгробия»: юнитов нет, но висят заявки/звонки/фото. Их удалять нельзя, только сливать поштучно (пример — 1063, Задача 7).

**7. Висячие ссылки на несуществующие модели:**

| Таблица | Строк | Моделей | Решение |
|---|---|---|---|
| `rent_orders_arch` | 222 | 5 | оставить — исторические заявки читаются по тексту `info` |
| `rent_tarif_act` + `rent_tarif_prev` | 172 | 25 | удалить (Задача 10) |
| `dop_photos` | 15 | 3 | удалить (Задача 10) |
| `tovar_rent_items_arch` | 3 | 2 | оставить — нужны историческим отчётам по инвентарю |
| `rent_orders`, `zvonki`, `rent_model_web`, `tovar_rent_items`, `multi_web` | 0 | 0 | чисто |

Источник мусора: `bb/tovar_del.php:266` удаляет только `tovar_rent` + `tovar_rent_items`, а `model_id` живёт в 13 таблицах (Задача 5).

**8. Категории вне дерева каталога:** **57 из 153** не имеют строки в `subrazdel_category`, то есть недостижимы навигацией (L3-страницы их моделей при этом резолвятся, т.к. URL строится по `tovar_rent_cat.main_sub_razdel_id`). Часть — явно намеренные «парковки»: ID 175 «К УДАЛЕНИЮ» (**60 моделей!**), ID 49 «Я-Технические товары» (39). Но часть выглядит как обычные рабочие категории, потерявшие связь: ID 11 «Качели напольные» (14 моделей), ID 19 «Автокресла до 18 кг» (13), ID 122 «Нивелиры» (12), ID 124 «Перфораторы, дрели» (11), ID 110 «Виброплиты» (10), ID 136 «Генераторы» (8), ID 17 «Стойки, турнички» (7), ID 62 «Конверты в коляску» (6), ID 103 «Пилы аккумуляторные» (6).

Системная причина найдена: **`Category::save()` не создаёт связь в `subrazdel_category`** — её пишет только `bb/classes/SubRazdel.php:512` (из `sub_razdel_manage.php`). То есть любая категория, созданная через `category_management.php`, рождается вне дерева, пока её отдельно не привяжут. Исправляется в Задаче 14, Шаг 1.

⚠️ **Требует решения владельца:** нужно ли возвращать в дерево те 9 категорий, что похожи на рабочие, и что делать с 60 моделями в «К УДАЛЕНИЮ». Это отдельная задача — в этот план не входит.

**9. Производители:** справочника нет, `producer` — varchar в `tovar_rent`, `tovar_rent_items`, `tovar_rent_items_arch`. **361** уникальное значение на 1839 моделей.

- Варианты написания одного бренда (после нормализации регистра/пробелов/дефисов): **3 группы** — `Maxi Cosi` + `Maxi-Cosi` (**16 моделей**), `Baby Mamy` + `Babymamy` (3), `Kinder Kraft` + `Kinderkraft` (3).
- Гомоглифных дублей (кириллица вместо латиницы) **нет**.
- Названия с ведущим пробелом: **3** — `[ 3A HealthCare]`, `[ Simple Parenting]`, `[ Gardena ]`. Плюс **15** названий моделей с пробелами по краям.
- **Скрытые побайтовые варианты — 6 групп**, невидимые для `DISTINCT`/`GROUP BY` из-за коллации:

  | Написания | Моделей | Различие |
  |---|---|---|
  | `Chicco` / `Chicco ` | 77 / 1 | замыкающий пробел |
  | `Cybex` / `Cybex ` | 43 / 1 | замыкающий пробел |
  | `Riko` / `Riko ` | 9 / 1 | замыкающий пробел |
  | `Chi lok BO` / `Chi Lok Bo` | 5 / 1 | регистр |
  | `MEDELA` / `Medela` | 2 / 1 | регистр |
  | `THULE` / `Thule` | 1 / 1 | регистр |

- **Вероятные дубли брендов, найденные fuzzy-сканом** (требуют решения владельца, автоматически не сводим): `I love mum` (5 моделей) + `I love mum, РФ` (11) — обе в категории 12; `Simple Parenting` (3, категории 59+73) + `Simple Parenting Doona` (3, категория 75).
- Подтверждённое ложное срабатывание: `Medela` (молокоотсосы, cat 34) ~ `Medel` (ингаляторы, cat 20), схожесть 0.75 — разные бренды, сливать нельзя.
- **Логотип бренда хранится per-model** в `rent_model_web.logo`: **545 из 974** русских страниц без логотипа; у одного бренда до 3 разных путей (`Fisher-price` — 3, `РБ` — 3, `Britax Romer`, `Anex`, `Eco`, `Vtech`, `Tiny love`, `Philips Avent`, `Bright-Starts` — по 2).

**10. Механизм появления дублей производителей** (устранён Задачей 16): дропдаун фирмы заполняется глобально (`SELECT DISTINCT producer FROM tovar_rent`, `:534`), а дропдаун модели — тоже глобально (`:542`, **~1800 `<option>`**). Найти нужное в списке такого размера глазами нельзя, поэтому оператор выбирает «ввести нового производителя» / «ввести новую модель» и печатает заново — с новым написанием.

**11. Схема против кода — позиционные `INSERT`:**

| Таблица | Колонок | Значений в `INSERT` | Статус |
|---|---|---|---|
| `tovar_rent_cat` (`bb/tovar_new_mod.php:99`, `:245`) | 9 | 3 | **сломан** — `ERROR 1136` |
| `tovar_rent` (`bb/tovar_new_mod.php:143`) | 25 | 25 | пока совпадает — мина на будущее |
| `tovar_rent_items` (`bb/tovar_new.php:214`) | 27 | 27 | пока совпадает — мина на будущее |

`Category::save()` (`bb/classes/Category.php:300`) использует правильный именованный `INSERT ... SET` — на него и опираемся.

**12. Мёртвый и неработоспособный код:**

- `bb/cat_ch.php` — функции `mysql_*`, удалённые в PHP 7 → гарантированный HTTP 500 (проверено curl'ом). Вызывается из `bb/tovar_new_mod.php:388`, `bb/rent_tarifs.php` (5 мест), `bb/tovar_new(old).php`.
- `bb/database.php` — тот же `mysql_connect`; подключается из `bb/cat_ch.php` и `bb/model_clean.php` → обе страницы нерабочие.
- `bb/cat_ch_new.php:229` — ветка `dog_name_select` выполняет `$query` вместо `$query_cat` и не делает `fetch_assoc()` (Задача 1).
- `app/Http/Controllers/McpAnalyticsController.php` — не упомянут в `routes/api.php` ни разу (0 совпадений), полностью мёртв.
- `bb/classes/tovar.php:729` `hasFreeItemsForModelId()` — баг приоритета операторов (`AND ... OR ...` без скобок → вернёт true, когда любой юнит в базе в `t_bron`); вызывающих нет.
- `bb/tovar_del.php` — `$query_fin = "ROLLBACK'"` со лишней кавычкой; плюс `$done='no'` выставляется перед `die()`, поэтому откат не выполняется никогда.
- `bb/tovar_new.php:63` — опечатка в тексте ссылки «Внести новую модел».
- Два `<form>` с одинаковым `id="tovar_tarif"` в выводе `bb/tovar_new_mod.php:160,165` и `:307,312`.

**13. `state` в `tovar_rent_items`:** `int(11) NOT NULL` в обеих таблицах (значит `state != '-1'` и NULL-безопасная форма эквивалентны). На 27.07.2026 значений `-1` в базе **нет** — четыре исторических «фейка» (реальные товары с ошибочной пометкой) приведены к `state=0` в этот же день, маркер свободен под фичу research-товаров.

**14. `@@sql_mode` на локальной MariaDB пуст** (STRICT выключен) — поэтому именованный `INSERT` без части NOT NULL-колонок локально пройдёт с неявными дефолтами, а на проде может упасть. Все новые `INSERT` перечисляют колонки явно.

**15. L3-роут игнорирует путь до модели** (проверено на проде 28.07.2026). `Route::get('/{lang}/{razdel}/{subrazdel}/{category}/{model}')` ограничен только `->where('lang','ru')`, а `L3Controller` находит модель **исключительно по слагу** `rent_model_web.page_addr`. Следствия:

```
/ru/prokat-detskih-tovarov/detskaya-komnata/kostyum-princessy-naprokat/costume_..._sophia...  → 200
/ru/karnavalnye-kostyumy/kostumy-zverei/disney-princesses/costume_..._sophia...               → 200
/ru/prokat-detskih-tovarov/detskaya-komnata/vsyakaya-chush/costume_..._sophia...              → 200
/ru/chush/chush2/chush3/costume_..._sophia...                                                 → 200
/ru/chush/chush2/chush3/net-takoy-modeli-12345                                                → 404
```

То есть **любая страница товара доступна по неограниченному числу URL**; 404 отдаётся только при несуществующем слаге модели. Спасает то, что `<link rel="canonical">` во всех случаях указывает на путь, собранный из настоящей категории модели, — то есть каноникализация работает правильно.

Практический вывод для Задачи 9: слияние категорий не создаёт 404 на старом адресе, меняется только canonical. Отдельный вывод для беклога: стоит рассмотреть 301 с «неправильного» пути на канонический, чтобы краулер не тратил бюджет на бесконечные варианты. В этот план не входит — задача SEO-уровня, а не чистки каталога.

---

## Self-review

**Покрытие находок аудита:** блокер `INSERT` категории → Задача 2 (заглушка) + Задача 14 (полноценная замена); `getXmlHttp` + мёртвые эндпоинты → Задача 1; дубль при «обновить» → Задача 3; двойной сабмит → Задача 4; источник висячих ссылок → Задача 5; 39 сирот → Задача 6; модель-надгробие 1063 → Задача 7; дубль Nania 1203 → Задача 8 (слияние; первоначальная гипотеза «опечатка в названии» опровергнута сотрудником); категория 158 → Задача 9; висячие тарифы/фото → Задача 10; повтор дублей категорий → Задача 11; знание в доках → Задача 12; движок поиска и ловли опечаток → Задача 13; категория через попап + починка `Category::save()` → Задача 14; селект моделей на 1800 позиций → Задача 15; производители + логотипы → Задача 16; написания производителей → Задача 17; справочник производителей → Фаза 7.

**Отложено в отдельный чат:** шрифт в договоре курьера — Фаза 8.

**Сознательно вне плана** (нужно решение владельца, зафиксировано в Приложении А): пара моделей 819/850 (обе с историей); 57 категорий вне дерева каталога, включая 60 моделей в «К УДАЛЕНИЮ»; две спорные пары брендов (`I love mum`, `Simple Parenting`); 222 висячие заявки в `rent_orders_arch`.

**Порядок и зависимости:**
- Фаза 1 (Задачи 1–2) → нужна, чтобы владелец мог заводить товар прямо сейчас.
- Фаза 4 (данные) технически независима от Фазы 3, но логически после неё — иначе мусор натечёт заново.
- Задача 11 (UNIQUE-индекс) строго после Задачи 9 (пока есть дубль имён — индекс не встанет).
- Задача 14 отменяет заглушку из Задачи 2 (её Шаг 7). Если Фаза 6 делается сразу, Задачу 2 можно пропустить целиком.
- Задачи 13–17 требуют, чтобы Задача 13 (движок) была первой в фазе.
- Задача 17 после Задачи 13: `Similarity` из Задачи 13 не используется в миграции, но список схлопывания получен тем же fuzzy-сканом.

**Риски:**
- Задача 9 меняет живой URL модели 730 → 301 внутри той же транзакции + проверка GSC до запуска.
- Задача 6 удаляет 39 строк, Задача 17 перезаписывает названия во трёх таблицах → страховка дампом из Задачи 0.
- Задача 15 убирает `model_select_new` из формы → нужно синхронно поправить обе ветки (`сохранить`, `обновить`), иначе `$model_name` останется пустым. Проверка — Шаг 3 той же задачи.
- Задача 14 (Шаг 1) меняет `Category::save()`, которым пользуется и `category_management.php` → проверить создание категории и оттуда тоже.
