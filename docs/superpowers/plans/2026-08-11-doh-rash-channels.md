# doh-rash.php: каналы оплаты + польский дизайн — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Убрать из `bb/doh-rash.php` выбор офиса, свести кассы к пяти каналам оплаты (К1, К2, Банк, Сейф, Курьер) и перенести вёрстку польской версии страницы.

**Architecture:** Один справочник каналов в начале файла — единственный источник правды для фильтра, формы, валидации и расшифровки истории. Кодировка в БД не меняется (`channel` + `kassa` как раньше), плоский список существует только в интерфейсе; преобразование делает `channel_to_office_kassa()`. Вёрстка портируется из `~/sites/tiktakrentpl/bb/doh-rash.php` с сохранением белорусских функций (правка расхода, удаление связанной пары, права `$in_del`).

**Tech Stack:** PHP 7.4 (legacy `bb/`, mysqli через `\bb\Db`), нативный JS без библиотек, Laravel + PHPUnit только для части с MCP API.

**Спека:** [docs/superpowers/specs/2026-08-11-doh-rash-channels-design.md](../specs/2026-08-11-doh-rash-channels-design.md)

---

## File Structure

| Файл | Что с ним | Ответственность |
|---|---|---|
| `bb/doh-rash.php` | переписывается | вся страница: справочник каналов, обработка POST, выборка, вёрстка |
| `app/Http/Controllers/Mcp/FinanceEntriesController.php` | правка ~4 строк | пропустить канал `safe` в write-API |
| `tests/Feature/Mcp/FinanceEntriesTest.php` | +2 теста | `safe` принимается, невалидная пара отклоняется |

Страница остаётся **одним файлом** — так же, как в польском проекте (987 строк). Разбиение на
модули развело бы две версии и усложнило перенос правок между проектами. Дублирующиеся `switch`
по каналам (сейчас ~200 строк из 1102) сворачиваются в функции, поэтому файл станет короче.

**Референс для вёрстки:** `/Users/kristinanaydenova/sites/tiktakrentpl/bb/doh-rash.php` — читать
целиком перед Task 5.

---

### Task 1: Справочник каналов и функции преобразования

**Files:**
- Modify: `bb/doh-rash.php` — блок функций в конце файла (сейчас `of_print` строки 995-1031, `kassa_print` строки 1033-1057)

- [ ] **Step 1: Заменить `of_print()` и `kassa_print()` и добавить справочник**

Удалить существующие `function of_print($of)` и `function kassa_print($of)` целиком и вставить на их место:

```php
/**
 * Справочник каналов оплаты — единственный источник правды для формы, фильтра,
 * валидации и расшифровки истории.
 *
 * Ключ массива — код канала в интерфейсе. Поля:
 *   text        — как называется в интерфейсе
 *   channel     — что писать в doh_rash.channel
 *   kassa       — что писать в doh_rash.kassa
 *   shift_only  — канал доступен только на вкладке «сдача в кассу» (сейф)
 *   restricted  — виден только сотрудникам из channels_privileged_users()
 *
 * Кодировка channel/kassa унаследована от версии с четырьмя офисами и намеренно
 * не меняется: на неё опираются дневные остатки (bb/models/KassaOstatok.php) и
 * write-API /finance/entries. Плоский список — только в интерфейсе.
 *
 * @return array
 */
function channels_all()
{
	return array(
		'k1'   => array('text' => 'К1',     'channel' => '1',    'kassa' => 'k1',   'shift_only' => false, 'restricted' => false),
		'k2'   => array('text' => 'К2',     'channel' => '1',    'kassa' => 'k2',   'shift_only' => false, 'restricted' => false),
		'bank' => array('text' => 'Банк',   'channel' => 'bank', 'kassa' => 'bank', 'shift_only' => false, 'restricted' => true),
		'safe' => array('text' => 'Сейф',   'channel' => 'safe', 'kassa' => 'safe', 'shift_only' => true,  'restricted' => true),
		'cur'  => array('text' => 'Курьер', 'channel' => 'cur',  'kassa' => 'k1',   'shift_only' => false, 'restricted' => false),
	);
}

/**
 * Кому видны Банк и Сейф. Список исторический — те же id, что были захардкожены
 * в фильтре офиса до перехода на каналы.
 *
 * @return array
 */
function channels_privileged_users()
{
	return array(2, 3, 5, 9);
}

/**
 * @return bool
 */
function channels_user_can_see_all()
{
	return in_array((int)$_SESSION['user_id'], channels_privileged_users(), true);
}

/**
 * Каналы для выпадающего списка формы.
 *
 * @param string $type1 rash | doh | shift
 * @return array код => название
 */
function channels_for_form($type1)
{
	$canSeeAll = channels_user_can_see_all();

	$rez = array();
	foreach (channels_all() as $code => $ch) {
		if ($ch['restricted'] && !$canSeeAll) continue;
		if ($ch['shift_only'] && $type1 !== 'shift') continue;

		$rez[$code] = $ch['text'];
	}
	return $rez;
}

/**
 * Код канала → [channel, kassa] для записи в doh_rash.
 *
 * @param string $code
 * @return array
 */
function channel_to_office_kassa($code)
{
	$all = channels_all();
	if (isset($all[$code])) return array($all[$code]['channel'], $all[$code]['kassa']);

	return array('HZ', 'HZ');
}

/**
 * Код канала → условие WHERE для выборки.
 *
 * Курьер отдаёт условие без кассы: до перехода курьерских касс было две, и обе
 * должны остаться видимыми под одним пунктом фильтра.
 *
 * @param string $code
 * @return string
 */
function channel_sql_filter($code)
{
	$all = channels_all();
	if (!isset($all[$code])) return '';

	$ch = $all[$code];
	if ($ch['channel'] === 'cur')  return " AND `channel`='cur'";
	if ($ch['channel'] === 'bank') return " AND `channel`='bank'";
	if ($ch['channel'] === 'safe') return " AND `channel`='safe'";

	return " AND `channel`='".$ch['channel']."' AND `kassa`='".$ch['kassa']."'";
}

/**
 * Название офиса/канала для колонки «канал» в таблице операций.
 *
 * Знает и закрытые точки (Ложинская, Победителей, Склад) — они убраны из
 * списков, но их операции остаются в истории и должны читаться.
 *
 * @param $of
 * @return string
 */
function of_print($of)
{
	switch ($of) {
		case '1':    return 'Литературная_22_';
		case '2':    return 'Ложинская_5_';
		case '3':    return 'Победителей_127_';
		case '4':    return 'Склад_';
		case 'cur':  return 'Курьер_';
		case 'bank': return 'Банк';
		case 'safe': return 'Сейф';
		default:     return 'Нет';
	}
}

/**
 * @param $of
 * @return string
 */
function kassa_print($of)
{
	switch ($of) {
		case 'k1':   return '1';
		case 'k2':   return '2';
		case 'bank': return '';
		case 'safe': return '';
		default:     return 'Нет';
	}
}
```

- [ ] **Step 2: Проверить синтаксис**

Run: `php -l bb/doh-rash.php`
Expected: `No syntax errors detected in bb/doh-rash.php`

- [ ] **Step 3: Проверить справочник изолированно**

Создать временный файл `/tmp/ch_test.php`:

```php
<?php
$_SESSION = array('user_id' => 2);
require '/Users/kristinanaydenova/sites/tiktakby_2026_v1/bb/ch_funcs_extract.php';
var_dump(channel_to_office_kassa('safe'));   // ['safe','safe']
var_dump(channel_to_office_kassa('k2'));     // ['1','k2']
var_dump(channel_sql_filter('cur'));         // " AND `channel`='cur'"
var_dump(array_keys(channels_for_form('rash')));  // k1,k2,bank,cur — без safe
var_dump(array_keys(channels_for_form('shift'))); // k1,k2,bank,safe,cur
```

Чтобы не тянуть весь `doh-rash.php` (он требует сессии и БД), скопировать блок функций из Step 1
в `bb/ch_funcs_extract.php`, прогнать, затем **удалить** временные файлы:

Run: `php /tmp/ch_test.php && rm /tmp/ch_test.php bb/ch_funcs_extract.php`
Expected: значения совпадают с комментариями выше; `channels_for_form('rash')` не содержит `safe`.

- [ ] **Step 4: Commit**

```bash
git add bb/doh-rash.php
git commit -m "feat(bb): справочник каналов оплаты в doh-rash.php"
```

---

### Task 2: Запись операции через справочник + серверная защита сейфа

**Files:**
- Modify: `bb/doh-rash.php:104-280` — `case 'сохранить'` целиком

- [ ] **Step 1: Заменить оба `switch ($channel)` / `switch ($type2)` на вызовы функции**

Заменить блок от `case 'сохранить':` до закрывающего `break;` (сейчас строки 104-280) на:

```php
	case 'сохранить':

		$acc_date = strtotime($acc_date);

		if ($type1 == 'rash' || $type1 == 'shift') {
			$amount = abs($amount) * (-1);
		}
		else {
			$amount = abs($amount);
		}

		// Сейф пополняется и опускается только переводом. Проверка обязана быть
		// на сервере: в браузере канал подменяется правкой поля формы.
		$formChannels = channels_for_form($type1);
		if (!isset($formChannels[$channel])) {
			die('Недопустимый канал оплаты для этой операции.');
		}
		if ($type1 == 'shift' && !isset($formChannels[$type2])) {
			die('Недопустимый канал-получатель.');
		}
		if ($type1 == 'shift' && $channel == $type2) {
			die('Канал-получатель должен отличаться от канала-источника.');
		}

		list($office, $kassa) = channel_to_office_kassa($channel);

		if ($type1 == 'shift') {
			//делаем расход по каналу-источнику
			$type1 = 'shift_minus';
			$ins_q = "INSERT INTO doh_rash VALUES('', '$acc_date', '$amount', '$type1', '$type2', '$office', '$kassa', '', '$info', '".time()."', '".$_SESSION['user_id']."', '$zp_name')";
			$result_ins = $mysqli->query($ins_q);
			if (!$result_ins) {
				die('Сбой при доступе к базе данных: '.$ins_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
			}
			$link_id1 = $mysqli->insert_id;

			//делаем доход по каналу-получателю
			list($office, $kassa) = channel_to_office_kassa($type2);

			$amount = abs($amount);
			$type1  = 'shift_plus';
			$ins_q = "INSERT INTO doh_rash VALUES('', '$acc_date', '$amount', '$type1', '$channel', '$office', '$kassa', '$link_id1', '$info', '".time()."', '".$_SESSION['user_id']."', '$zp_name')";
			$result_ins = $mysqli->query($ins_q);
			if (!$result_ins) {
				die('Сбой при доступе к базе данных: '.$ins_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
			}
			$link_id2 = $mysqli->insert_id;

			//обновляем линк по расходу
			$upd_q = "UPDATE doh_rash SET link_to='$link_id2' WHERE dr_id='$link_id1'";
			$result_upd = $mysqli->query($upd_q);
			if (!$result_upd) {
				die('Сбой при доступе к базе данных: '.$upd_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
			}
		}
		else {
			$ins_q = "INSERT INTO doh_rash VALUES('', '$acc_date', '$amount', '$type1', '$type2', '$office', '$kassa', '', '$info', '".time()."', '".$_SESSION['user_id']."', '$zp_name')";
			$result_ins = $mysqli->query($ins_q);
			if (!$result_ins) {
				die('Сбой при доступе к базе данных: '.$ins_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
			}
			$link_id1 = $mysqli->insert_id;
		}

		$flashMessage = 'Операция сохранена.';

		break;
```

Обратите внимание: строка `$of = substr($channel, 0, 3);` из старого кода удалена — переменная
`$of` дальше нигде не читается.

- [ ] **Step 2: Объявить `$flashMessage` среди параметров**

В блоке инициализации (сейчас строки 86-95, рядом с `$action = '';`) добавить строку:

```php
$flashMessage = '';
```

- [ ] **Step 3: Добавить флеш-сообщение в ветку удаления**

В `case 'удалить':` заменить `echo '<strong>Операция(-и) успешно удалена.</strong>';` на:

```php
		$flashMessage = 'Операция удалена.';
```

Причина: `echo` до открытия `<html>` ломает новую вёрстку — сообщение должно выводиться внутри
страницы (Task 5).

- [ ] **Step 4: Проверить синтаксис**

Run: `php -l bb/doh-rash.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add bb/doh-rash.php
git commit -m "feat(bb): запись операции через справочник каналов, защита сейфа на сервере"
```

---

### Task 3: Фильтр по каналу вместо офиса и кассы

**Files:**
- Modify: `bb/doh-rash.php:326-355` — блок построения `$srch`

- [ ] **Step 1: Заменить блок построения `$srch`**

Заменить строки от `if ($item_place == 'all' && ($_SESSION['user_id'] == '2' ...` до
`if ($zp_sel_s != 'all') {...}` включительно на:

```php
// Офисного фильтра больше нет — есть фильтр по каналу оплаты. Банк и Сейф
// сотрудникам без прав не показываем даже при выборе «все».
if ($kassa_s != 'all' && array_key_exists($kassa_s, channels_all())) {
	$srch = channel_sql_filter($kassa_s);
}
else {
	$srch = channels_user_can_see_all() ? '' : " AND `channel` NOT IN ('bank', 'safe')";
}

if ($type1_s == 'doh') {
	$srch .= " AND `type1`='doh'";
}
elseif ($type1_s == 'rash') {
	$srch .= " AND `type1`='rash'";
}
elseif ($type1_s == 'shift') {
	$srch .= " AND `type1` LIKE 'shift%'";
}

if ($type2_s  != 'all') $srch .= " AND `type2`='$type2_s'";
if ($zp_sel_s != 'all') $srch .= " AND `dr_name_id`='$zp_sel_s'";
```

Дополнительная защита: если сотрудник без прав вручную выберет `kassa_s=safe`, `channel_sql_filter`
вернёт условие по сейфу и он увидит чужие строки. Поэтому перед блоком добавить:

```php
// канал, недоступный сотруднику, сбрасываем в «все»
$allChannels = channels_all();
if ($kassa_s != 'all' && isset($allChannels[$kassa_s])
	&& $allChannels[$kassa_s]['restricted'] && !channels_user_can_see_all()) {
	$kassa_s = 'all';
}
```

- [ ] **Step 2: Зафиксировать `$item_place`**

Найти строку `$item_place = $_SESSION['office'];` (сейчас строка 89) и заменить на:

```php
$item_place = 'all';   // офисного фильтра больше нет, значение осталось для совместимости форм
```

- [ ] **Step 3: Удалить старый справочник названий касс**

Удалить строки `$rash["of1k1"] = ...` … `$rash["bank"] = "Банк";` (сейчас 357-367) и заменить на:

```php
// названия каналов для колонки «статья» в переводах: там type2 хранит код канала
$rash = array();
foreach (channels_all() as $code => $ch) {
	$rash[$code] = $ch['text'];
}
$doh = $rash;
```

- [ ] **Step 4: Проверить синтаксис**

Run: `php -l bb/doh-rash.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add bb/doh-rash.php
git commit -m "feat(bb): фильтр по каналу оплаты вместо офиса и кассы"
```

---

### Task 4: Данные для плиток итогов и распределения по статьям

**Files:**
- Modify: `bb/doh-rash.php` — после выборки `$result_dr` (сейчас строка 732)

- [ ] **Step 1: Собрать строки в массив и посчитать итоги**

Сразу после выполнения запроса `$dr_q` и проверки `$result_dr` вставить:

```php
$rows       = array();
$totalRash  = 0.0;   // расходы за период, положительным числом
$totalDohDr = 0.0;   // прочие доходы из doh_rash
$byItem     = array();

while ($dr = $result_dr->fetch_assoc()) {
	$rows[] = $dr;

	// переводы — это не расход, а перекладывание денег между каналами
	if (strpos($dr['type1'], 'shift') === 0) continue;

	if ($dr['type1'] == 'rash' || $dr['amount'] < 0) {
		$amt = abs((float)$dr['amount']);
		$totalRash += $amt;

		$code = $dr['type2'];
		if (!isset($byItem[$code])) $byItem[$code] = 0.0;
		$byItem[$code] += $amt;
	}
	else {
		$totalDohDr += (float)$dr['amount'];
	}
}

arsort($byItem);

// Выручка за период. Методика зафиксирована в app/Http/Controllers/Mcp/CLAUDE.md:
// SUM(r_paid + delivery_paid) по UNION(rent_sub_deals_act, rent_sub_deals_arch)
// с фильтром по acc_date — дате, когда деньги реально пришли.
$totalSales = 0.0;
foreach (array('rent_sub_deals_act', 'rent_sub_deals_arch') as $tbl) {
	$q = "SELECT SUM(r_paid) AS rent, SUM(delivery_paid) AS delivery
			FROM $tbl
			WHERE acc_date BETWEEN '".$from_date."' AND '".$to_date."'";
	$res = $mysqli->query($q);
	if (!$res) {
		die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
	}
	$r = $res->fetch_assoc();
	$totalSales += (float)$r['rent'] + (float)$r['delivery'];
}

$saldo = $totalSales - $totalRash;

// палитра точек для статей
$dotColors = array('#4a7dfc', '#22a06b', '#2bb8c4', '#f0b429', '#ef4444', '#8b93a7',
                   '#3f4b5b', '#e46bb2', '#6c5ce7', '#12b886', '#f97316', '#0ea5e9');
```

Внимание: `$from_date`/`$to_date` уже посчитаны выше (`strtotime($i_from_date)`), новые
переменные заводить не нужно. Цикл `while` по `$result_dr` в старой вёрстке (строка 825) после
этого читает пустой результат — в Task 5 он заменяется на обход `$rows`.

- [ ] **Step 2: Расширить период до конца дня**

Найти `$to_date = strtotime($i_to_date);` и заменить на:

```php
$to_date = strtotime($i_to_date.' 23:59:59');
```

Причина: `acc_date` хранит timestamp с временем; при `strtotime('2026-08-11')` = 00:00:00
операции, внесённые в течение последнего дня периода, в выборку не попадали.

- [ ] **Step 3: Проверить синтаксис**

Run: `php -l bb/doh-rash.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add bb/doh-rash.php
git commit -m "feat(bb): итоги периода и распределение расходов по статьям"
```

---

### Task 5: Вёрстка страницы

**Files:**
- Modify: `bb/doh-rash.php` — весь вывод HTML (сейчас строки 54-78 шапка и 742-894 таблица)
- Reference: `/Users/kristinanaydenova/sites/tiktakrentpl/bb/doh-rash.php:314-664`

- [ ] **Step 1: Прочитать референс целиком**

Прочитать `/Users/kristinanaydenova/sites/tiktakrentpl/bb/doh-rash.php` строки 314-664 — это
блок `<style>`, шапка, фильтры, плитки, таблица и распределение по статьям.

- [ ] **Step 2: Перенести блок `<style>` без изменений**

Скопировать `<style>…</style>` (референс, строки 321-424) в белорусский файл как есть — классы
`rx-*` не конфликтуют с `stile.css`.

- [ ] **Step 3: Перенести разметку с белорусскими адаптациями**

Скопировать разметку из референса (строки 426-664) и внести ровно эти изменения:

| Что в польской версии | Чем заменить в белорусской |
|---|---|
| `<?php echo nav_icons('doh-rash'); ?>` | блок `.top_menu` из текущего белорусского файла (строки 68-78): «Вы зашли как», «На главную», «Все сделки (новые)» |
| `Base::plToday(...)` | `date(...)` — польского часового пояса в белорусском `Base` нет |
| `<?php echo $CUR; ?>` | `BYN` |
| селект «Офис» (строки 472-484) | удалить целиком, оставив `<input type="hidden" name="item_place" value="all" />` |
| `kassa_names()` в фильтре «Канал оплаты» | `channels_for_form('shift')` — в фильтре видны все доступные сотруднику каналы, включая сейф |
| `of_print($dr['channel']).kassa_print($dr['kassa'])` | оставить как есть — функции уже белорусские (Task 1) |
| `while ($dr = $result_dr->fetch_assoc())` | `foreach ($rows as $dr)` — строки собраны в Task 4 |

Добавить в `<head>`, которого нет в польской версии:

```php
<?php echo Base::getBarCodeReaderScript(); ?>
```

- [ ] **Step 4: Сохранить белорусские элементы таблицы**

В строке таблицы операций перенести из текущей версии (строки 829-874) без изменения логики:

- показ `dr_id` для Димы: `(\bb\models\User::getCurrentUser()->isDima() ? '<br><span style="font-size: 10px">['.$dr['dr_id'].']</span>' : '')`;
- кнопку «i» и форму правки `update_form_<dr_id>` с классами `edit-btn-show`, `type2_update`,
  `zp_name_id_update`, `info_upd`, `correct-btn` — на них завязан JS из Task 6;
- форму удаления `del_form_<dr_id>` со скрытыми полями `dr_id`, `dr_id_link` (`$dr['link_to']`),
  `i_from_date`, `i_to_date`, `kassa_s`, `type1_s`, `type2_s` и кнопкой, видимой только при
  `in_array($_SESSION['user_id'], $in_del)`.

Скрытое поле `item_place` в этих формах заменить на `value="all"`.

- [ ] **Step 5: Проверить синтаксис**

Run: `php -l bb/doh-rash.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add bb/doh-rash.php
git commit -m "feat(bb): вёрстка страницы расходов по образцу польской версии"
```

---

### Task 6: Модалка внесения операции и JS

**Files:**
- Modify: `bb/doh-rash.php` — блок `<script>` (сейчас строки 458-622 и 897-983)
- Reference: `/Users/kristinanaydenova/sites/tiktakrentpl/bb/doh-rash.php:666-860`

- [ ] **Step 1: Удалить старый JS формы**

Удалить функции `rash_but()`, `doh_but()`, `shift_but()`, `dr_sel()`, `new_rash_send()`,
`rash_show()`, `zp_show()`, `zp_name_show()`, `type1s_show()` (строки 465-620) — их заменяет
модалка. Функции `showHideEditFunctionality()`, `updateType2Change()`, `correctSubmit()` в конце
файла **оставить**: правка расхода работает по-старому.

- [ ] **Step 2: Перенести модалку и её JS**

Скопировать из референса разметку модалки (строки 666-739) и скрипт (строки 741-860) с одним
изменением: источники списков каналов должны учитывать вкладку.

```php
	var RX_ITEMS = {
		rash:  <?php echo json_encode($rashItems, JSON_UNESCAPED_UNICODE); ?>,
		doh:   <?php echo json_encode($dohItems,  JSON_UNESCAPED_UNICODE); ?>,
		shift: <?php echo json_encode(channels_for_form('shift'), JSON_UNESCAPED_UNICODE); ?>
	};

	// каналы-источники: на вкладке «сдача в кассу» доступен сейф, на остальных — нет
	var RX_CHANNELS = {
		rash:  <?php echo json_encode(channels_for_form('rash'),  JSON_UNESCAPED_UNICODE); ?>,
		doh:   <?php echo json_encode(channels_for_form('doh'),   JSON_UNESCAPED_UNICODE); ?>,
		shift: <?php echo json_encode(channels_for_form('shift'), JSON_UNESCAPED_UNICODE); ?>
	};
```

Добавить перезаполнение списка каналов при смене вкладки — в польской версии его нет, потому что
там сейфа не существует:

```javascript
	function rxFillChannels() {
		var t1  = document.getElementById('type1').value;
		var map = RX_CHANNELS[t1] || RX_CHANNELS.rash;
		var sel = document.getElementById('channel');
		var prev = sel.value;

		sel.innerHTML = '<option value="0">не выбрано</option>';
		for (var code in map) {
			if (!map.hasOwnProperty(code)) continue;
			var o = document.createElement('option');
			o.value = code;
			o.text  = map[code];
			sel.appendChild(o);
		}
		// сохраняем выбор, если канал доступен и на новой вкладке
		if (map.hasOwnProperty(prev)) sel.value = prev;
	}
```

И вызывать её первой строкой в `rxSetType1()`:

```javascript
	function rxSetType1(t1) {
		document.getElementById('type1').value = t1;
		rxFillChannels();
		var tabs = document.querySelectorAll('.rx-tabs button');
		...
```

- [ ] **Step 3: Собрать справочники статей для модалки**

Польская версия строит `$rashItems` и `$dohItems` как плоские массивы код => текст. В белорусском
файле статьи сейчас собираются сразу в HTML (`$ri_t1`, `$rd_t1`). Заменить сбор на:

```php
// статьи расходов: bank_yn прятал часть статей до выбора «офиса» Банк — при плоском
// списке каналов это разделение потеряло смысл, показываем весь активный список
$rashItems = array();
$ri_q = "SELECT * FROM rash_items ORDER BY ri_order";
$result_ri = $mysqli->query($ri_q);
if (!$result_ri) {
	die('Сбой при доступе к базе данных: '.$ri_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
}
while ($ri_def = $result_ri->fetch_assoc()) {
	if (($ri_def['is_active'] ?? 1) == 1) $rashItems[$ri_def['ri_code']] = $ri_def['ri_text'];
	$rash[$ri_def['ri_code']] = $ri_def['ri_text'];   // для расшифровки истории — включая выключенные
}

// статьи доходов
$dohItems = array();
$rd_q = "SELECT * FROM doh_items WHERE bank_yn!=1 ORDER BY rd_order";
$result_rd = $mysqli->query($rd_q);
if (!$result_rd) {
	die('Сбой при доступе к базе данных: '.$rd_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
}
while ($rd_def = $result_rd->fetch_assoc()) {
	if (($rd_def['is_active'] ?? 1) == 1) $dohItems[$rd_def['rd_code']] = $rd_def['rd_text'];
	$doh[$rd_def['rd_code']] = $rd_def['rd_text'];
}
```

Старые переменные `$ri_t1`, `$ri_t2`, `$ri_t1_s`, `$ri_t2_s`, `$rd_t1`, `$rd_t1_s`, `$t2_select`
удалить, кроме одного места: форма правки расхода в таблице (Task 5, Step 4) использует `$ri_t1`
для селекта статьи. Собрать его из `$rashItems` рядом с формой:

```php
$ri_t1 = '';
foreach ($rashItems as $code => $text) {
	$ri_t1 .= '<option value="'.htmlspecialchars($code).'">'.htmlspecialchars($text).'</option>';
}
```

- [ ] **Step 4: Проверить синтаксис**

Run: `php -l bb/doh-rash.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Проверить, что не осталось ссылок на удалённые переменные**

Run: `grep -n 'ri_t2\|rd_t1_s\|t2_select\|zp_select\b\|new_rash_send\|dr_sel()' bb/doh-rash.php`
Expected: пустой вывод (кроме `$zp_select_s`, если он ещё используется фильтром сотрудников)

- [ ] **Step 6: Commit**

```bash
git add bb/doh-rash.php
git commit -m "feat(bb): модальная форма внесения операции с вкладками"
```

---

### Task 7: Канал `safe` в write-API `/finance/entries`

**Files:**
- Modify: `app/Http/Controllers/Mcp/FinanceEntriesController.php:548`, `:556`, `:560-565` (`channelKassaPairValid`), `:16-17` (докблок)
- Test: `tests/Feature/Mcp/FinanceEntriesTest.php`

- [ ] **Step 1: Написать падающий тест**

Добавить в `tests/Feature/Mcp/FinanceEntriesTest.php`:

```php
public function test_store_accepts_safe_channel(): void
{
    $response = $this->withToken(config('mcp.token'))->postJson('/api/mcp/v1/finance/entries', [
        'entries' => [[
            'type1'   => 'shift_plus',
            'type2'   => 'k1',
            'date'    => '2026-08-11',
            'amount'  => 100.00,
            'kassa'   => 'safe',
            'channel' => 'safe',
            'info'    => 'перевод в сейф',
        ]],
    ]);

    $response->assertStatus(201);
}

public function test_store_rejects_safe_kassa_with_office_channel(): void
{
    $response = $this->withToken(config('mcp.token'))->postJson('/api/mcp/v1/finance/entries', [
        'entries' => [[
            'type1'   => 'rash',
            'type2'   => 'arenda',
            'date'    => '2026-08-11',
            'amount'  => -50.00,
            'kassa'   => 'safe',
            'channel' => '1',
            'info'    => 'сейф не привязан к офису',
        ]],
    ]);

    $response->assertStatus(422);
}
```

Точный способ авторизации и формат тела скопировать из соседнего существующего теста в этом же
файле — заголовок токена и структура `entries` должны совпадать с тем, как тестируются остальные
операции создания.

- [ ] **Step 2: Запустить тест и убедиться, что он падает**

Run: `./vendor/bin/phpunit --filter=test_store_accepts_safe_channel`
Expected: FAIL — ответ 422 с сообщением `kassa must be one of: k1, k2, bank, card.`

- [ ] **Step 3: Расширить белые списки**

В `validateEntry()` заменить строку 548:

```php
        } elseif (!in_array($kassa, ['k1', 'k2', 'bank', 'card', 'safe'], true)) {
            $errors['kassa'] = 'kassa must be one of: k1, k2, bank, card, safe.';
```

и строку 556:

```php
        } elseif (!in_array($channel, ['bank', 'cur', 'safe'], true) && !$this->officeExists($channel)) {
            $errors['channel'] = 'channel must be an existing office number, "cur", "safe", or "bank".';
```

- [ ] **Step 4: Расширить правило пары**

Заменить `channelKassaPairValid()`:

```php
    private function channelKassaPairValid(?string $channel, ?string $kassa): bool
    {
        // сейф — отдельный канал наличных, живёт только в паре сам с собой
        if ($kassa === 'safe' || $channel === 'safe') {
            return $channel === 'safe' && $kassa === 'safe';
        }
        if ($kassa === 'bank') {
            return $channel === 'bank';
        }
        if ($channel === 'bank') {
            return false;
        }
        return $channel === 'cur' || $this->officeExists((string) $channel);
    }
```

- [ ] **Step 5: Обновить докблок**

Строки 16-17 файла привести к:

```php
 *   - channel WHERE it happened: office number ('1'-'4'), 'cur' (courier), 'bank', 'safe'
 *   - kassa   WHERE the money sits: 'k1'/'k2' (cash tills), 'card', 'bank', 'safe'
```

- [ ] **Step 6: Запустить тесты**

Run: `./vendor/bin/phpunit tests/Feature/Mcp/FinanceEntriesTest.php`
Expected: PASS, включая оба новых теста и все существующие

- [ ] **Step 7: Прогнать весь MCP-набор на регресс**

Run: `./vendor/bin/phpunit tests/Feature/Mcp/`
Expected: PASS — в частности `LegacyParityTest` не должен сломаться

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Mcp/FinanceEntriesController.php tests/Feature/Mcp/FinanceEntriesTest.php
git commit -m "feat(mcp): канал safe в /finance/entries"
```

---

### Task 8: Проверка страницы вживую

**Files:** нет правок, только проверка

- [ ] **Step 1: Поднять окружение**

Run: `docker-compose up -d`
Expected: контейнеры `app`, `db`, `phpmyadmin` в статусе Up

Если база пустая — восстановить дамп `~/sites/tiktakby_2026.sql` через phpMyAdmin
(`http://localhost:8088`) или `docker-compose exec -T db mysql -u tiktakby_tiktak -p tiktakby_tiktak < ~/sites/tiktakby_2026.sql`.

- [ ] **Step 2: Пройти чек-лист из спеки**

Открыть `http://localhost/bb/doh-rash.php` под сотрудником из списка {2,3,5,9} и проверить:

1. Расход по К1 сохраняется, появляется в таблице с каналом «Литературная_22_1».
2. Доход по К2 сохраняется.
3. Перевод К1 → Сейф создаёт **две** строки: `shift_minus` по К1 и `shift_plus` по Сейфу,
   у обеих заполнен `link_to` на соседнюю.
4. Перевод Сейф → К1 работает в обратную сторону.
5. Удаление одной строки перевода удаляет обе.
6. На вкладках «Расход» и «Доход» в списке каналов **нет** Сейфа; на «Сдача в кассу» — есть.
7. Фильтр «Курьер» показывает и старые записи по Курьер 2.
8. Фильтр «все» показывает записи по Складу / Победителей / Ложинской с их названиями.
9. Кнопка «i» открывает правку статьи и комментария, «исправить» сохраняет.
10. Плитка расходов совпадает с итогом колонки «сумма» по расходным строкам; переводы в неё
    не входят.

- [ ] **Step 3: Проверить ограничение прав**

Зайти под сотрудником **не** из списка {2,3,5,9} и убедиться: Банка и Сейфа нет ни в фильтре,
ни в форме, ни в таблице операций.

- [ ] **Step 4: Проверить серверную защиту сейфа**

В консоли браузера на вкладке «Расход» подменить значение канала и отправить форму:

```javascript
document.getElementById('channel').innerHTML = '<option value="safe" selected>x</option>';
document.getElementById('new_rash').submit();
```

Expected: страница отвечает `Недопустимый канал оплаты для этой операции.`, строка в БД не создана.

- [ ] **Step 5: Commit результатов, если потребовались правки**

```bash
git add bb/doh-rash.php
git commit -m "fix(bb): правки по итогам ручной проверки"
```

---

## Self-Review

**Покрытие спеки:**

| Требование спеки | Задача |
|---|---|
| Пять каналов, кодировка в БД не меняется | Task 1 |
| Курьер — один канал, пишется в `cur`+`k1` | Task 1 (справочник), Task 2 (запись) |
| Архивные каналы убраны из списков, читаются в истории | Task 1 (`of_print`), Task 3 (фильтр) |
| Сейф только через «сдачу в кассу», проверка на сервере | Task 2 (сервер), Task 6 (форма), Task 8 Step 4 (проверка) |
| Сейф и Банк видны только id 2,3,5,9 | Task 1 (`restricted`), Task 3 (`$srch`), Task 8 Step 3 |
| Фильтр «Офис» удалён, «Канал оплаты» фильтрует по паре | Task 3, Task 5 Step 3 |
| Польский дизайн целиком: шапка, пресеты, плитки, распределение, модалка | Task 4 (данные), Task 5 (вёрстка), Task 6 (модалка) |
| Белорусские особенности сохранены | Task 5 Step 4, Task 6 Step 1 |
| Выручка по методике MCP CLAUDE.md | Task 4 Step 1 |
| `safe` в write-API | Task 7 |
| Ручной чек-лист | Task 8 |

**Согласованность имён:** `channels_all()`, `channels_for_form()`, `channel_to_office_kassa()`,
`channel_sql_filter()`, `channels_user_can_see_all()`, `channels_privileged_users()` — определены
в Task 1 и используются под теми же именами в Tasks 2, 3, 5, 6. Переменные `$rows`, `$totalRash`,
`$totalDohDr`, `$byItem`, `$totalSales`, `$saldo`, `$dotColors`, `$flashMessage`, `$rashItems`,
`$dohItems` заведены в Tasks 2, 4, 6 и читаются в Task 5.

**Известное расхождение с польской версией:** там `kassa_names()` возвращает каналы оплаты сделок
(наличные / Револют / BANK), а `channels_all()` в белорусской версии — кассы одного офиса плюс
банк, сейф и курьер. Имена функций различаются намеренно: сущности разные, механический перенос
правок между проектами тут неуместен.
