# Multi-Item Contract Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create `dogovor_multi_new.php` — a page where a staff member selects a client, adds multiple items with auto-calculated rental cost per item, enters one payment split proportionally, and prints a single RTF contract covering all items.

**Architecture:** New isolated files in `bb/` — `dogovor_multi_new.php` (UI + save logic), `get_item_tarifs.php` (AJAX: inv_n → JSON tariffs), `ndk_multi.rtf` (RTF template). Each item stored as separate `rent_deals_act` row (DB structure unchanged). One minor addition to `dogovor_new.php` (menu link).

**Tech Stack:** PHP 7.4, MySQLi (legacy `\bb\Db` singleton), vanilla JS (no frameworks), RTF_Template class (defined in `dogovor_new.php`, copy needed), Windows-1251 RTF encoding.

---

## Key References

Before starting, read these files:
- `bb/item_ch_new.php` lines 250–315 — tariff query pattern
- `bb/dogovor_new.php` lines 3529–3625 — `RTF_Template` class + `encode_for_rtf()` + `money_print()`
- `bb/dogovor_new.php` lines 490–553 — `calculateNew()` / `getRentToPay()` JS logic to replicate
- `bb/dog3_ajax.php` — client search AJAX (reuse as-is)

## DB Tables Used

```
tovar_rent_items   item_inv_n, model_id, status, item_set, item_place
tovar_rent         tovar_rent_id=model_id, model, agr_price, agr_price_cur, model_addr, producer, tovar_rent_cat_id
tovar_rent_cat     tovar_rent_cat_id, dog_name
rent_tarif_act     model_id, tarif_id, step, kol_vo, kol_vo_min, rent_amount, rent_per_step, sort_num
                   JS: data-days = sort_num*kol_vo, value = rent_amount
rent_deals_act     deal_id, client_id, item_inv_n, start_date(unix), return_date(unix), delivery_yn,
                   delivery_to_pay, delivery_paid, r_to_pay, r_paid, collateral_amount, collateral_cur,
                   deal_status, deal_info, acc_person_id, cr_who_id, cr_time(unix), last_sub_deal_ch_time,
                   planned_return_date, deal_set, first_rent_place
rent_sub_deals_act sub_deal_id, deal_id, type, type_sort_n, from(unix), to(unix), tarif_id, tarif_step,
                   tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id,
                   r_paid, delivery_paid, r_payment_type, del_payment_type, status, info,
                   cr_time(unix), cr_who_id, ch_time, ch_who_id, link, acc_date(unix), place,
                   ch_num, sd_cat_id, sd_model_id, sd_inv_n
clients            client_id, family, name, otch, city, str, dom, kv, phone_1, phone_2,
                   pas_n, pas_date, pas_who, reg_city, reg_str, reg_dom, reg_kv, info
```

---

## Task 1: AJAX endpoint `get_item_tarifs.php`

**Files:**
- Create: `bb/get_item_tarifs.php`

- [ ] **Step 1.1: Create the file**

```php
<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');

isset($_SESSION['svoi']) ? : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$inv_n = intval($_GET['inv_n'] ?? 0);
if ($inv_n <= 0) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();

// Item + model + category
$inv_n_safe = $mysqli->real_escape_string($inv_n);
$q = "SELECT i.item_inv_n, i.model_id, i.item_set, i.item_place, i.status, i.br_time,
             m.model, m.agr_price, m.agr_price_cur, m.model_addr, m.producer,
             c.dog_name
      FROM tovar_rent_items i
      JOIN tovar_rent m ON m.tovar_rent_id = i.model_id
      JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = m.tovar_rent_cat_id
      WHERE i.item_inv_n = '$inv_n_safe'";

$res = $mysqli->query($q);
if (!$res || $res->num_rows === 0) {
    echo json_encode(['status' => 'not_found']);
    exit;
}
$item = $res->fetch_assoc();

// Availability check
$available = ($item['status'] === 'to_rent')
    || (in_array($item['status'], ['bron', 't_bron']) && $item['br_time'] < time());
if (!$available) {
    echo json_encode(['status' => 'not_available', 'item_status' => $item['status']]);
    exit;
}

// Tariffs
$model_id = intval($item['model_id']);
$q_t = "SELECT tarif_id, step, kol_vo, kol_vo_min, rent_amount, rent_per_step, sort_num
        FROM rent_tarif_act
        WHERE model_id = '$model_id'
        ORDER BY sort_num, kol_vo";
$res_t = $mysqli->query($q_t);

$tarifs = [];
while ($t = $res_t->fetch_assoc()) {
    $tarifs[] = [
        'tarif_id'     => (int)$t['tarif_id'],
        'step'         => $t['step'],
        'kol_vo'       => (int)$t['kol_vo'],
        'kol_vo_min'   => (int)$t['kol_vo_min'],
        'rent_amount'  => (float)$t['rent_amount'],
        'rent_per_step'=> (float)$t['rent_per_step'],
        'sort_num'     => (int)$t['sort_num'],
        'days'         => (int)$t['sort_num'] * (int)$t['kol_vo'],
    ];
}

$item_name = ($item['model_addr'] !== '' ? $item['model_addr'] : $item['dog_name'])
           . ': ' . $item['model']
           . ' (инв.№' . substr($inv_n, 0, 3) . '-' . substr($inv_n, 3) . ')';

echo json_encode([
    'status'        => 'ok',
    'item_name'     => $item_name,
    'item_set'      => $item['item_set'],
    'model_id'      => $model_id,
    'agr_price'     => (float)$item['agr_price'],
    'agr_price_cur' => $item['agr_price_cur'],
    'tarifs'        => $tarifs,
], JSON_UNESCAPED_UNICODE);
```

- [ ] **Step 1.2: Verify in browser**

Open: `http://localhost/bb/get_item_tarifs.php?inv_n=XXXXXX` (use a real inv_n from the DB).

Expected for a valid free item:
```json
{"status":"ok","item_name":"Коляска...(инв.№701-001)","tarifs":[{"tarif_id":5,"step":"day",...},...]}
```

Expected for unknown inv_n: `{"status":"not_found"}`
Expected for occupied item: `{"status":"not_available","item_status":"rented"}`

- [ ] **Step 1.3: Commit**

```bash
git add bb/get_item_tarifs.php
git commit -m "feat: add get_item_tarifs AJAX endpoint"
```

---

## Task 2: Page skeleton — auth, layout, client search

**Files:**
- Create: `bb/dogovor_multi_new.php`

- [ ] **Step 2.1: Create skeleton with auth + client search block**

```php
<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');

isset($_SESSION['svoi']) ? : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941) {
    die('<a href="/bb/index.php">Залогиниться</a>');
}

$mysqli = \bb\Db::getInstance()->getConnection();

// ── POST handler placeholder (Task 7 fills this) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    // TODO Task 7
    echo json_encode(['status' => 'not_implemented']);
    exit;
}

// ── Client lookup for pre-fill (GET ?client_id=N) ─────────────────────────
$prefill_client = null;
if (isset($_GET['client_id']) && intval($_GET['client_id']) > 0) {
    $cid = intval($_GET['client_id']);
    $rc = $mysqli->query("SELECT * FROM clients WHERE client_id='$cid'");
    if ($rc && $rc->num_rows > 0) $prefill_client = $rc->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8"/>
<title>Мультидоговор</title>
<link href="/bb/stile.css" rel="stylesheet" type="text/css"/>
<link href="/bb/dogovor_new_style.css" rel="stylesheet" type="text/css"/>
<style>
  .multi-table { border-collapse:collapse; width:100%; margin-bottom:12px; }
  .multi-table th, .multi-table td { border:1px solid #ccc; padding:4px 8px; font-size:13px; }
  .multi-table th { background:#e8eaf6; }
  .row-status { font-size:11px; color:#888; }
  .row-status.ok { color:green; }
  .row-status.err { color:red; }
  .pay-row { display:flex; align-items:center; gap:12px; margin:4px 0; font-size:13px; }
  .pay-row input[type=number] { width:90px; }
  #pay-diff { font-weight:bold; }
  #pay-diff.over { color:orange; }
  #pay-diff.under { color:red; }
  #pay-diff.exact { color:green; }
</style>
</head>
<body>

<div class="user">
  <form name="выход" method="post" action="index.php">
    Вы зашли как: <strong><?= htmlspecialchars($_SESSION['user_fio']) ?></strong>
    <input type="submit" name="exit" value="Выйти"/>
  </form>
</div>

<div class="top_menu">
  <a class="div_item" href="/bb/index.php">На главную</a>
  <a class="div_item" href="/bb/dogovor_new.php">Одиночный договор</a>
</div>

<h2 style="margin:12px 0 4px 0;">Мультидоговор — несколько товаров</h2>

<!-- ── CLIENT SEARCH ─────────────────────────────────────────────────────── -->
<div class="find_cl" id="client_search_div">
  <span class="div_header">Поиск клиента:</span><br/>
  <input type="text" id="cl_search_input" placeholder="Фамилия или телефон" size="30"/>
  <input type="button" value="Найти" onclick="searchClient()"/>
  <div id="cl_search_results"></div>
</div>

<!-- ── CLIENT INFO (hidden until selected) ───────────────────────────────── -->
<div class="find_cl" id="client_info_div" style="display:none;">
  <span class="div_header">Клиент:</span>
  <strong id="cl_display_name"></strong>
  &nbsp;|&nbsp; тел: <span id="cl_display_phone"></span>
  &nbsp;|&nbsp; <a href="#" onclick="resetClient(); return false;">сменить клиента</a>
  <input type="hidden" id="client_id" name="client_id" value=""/>
</div>

<!-- ── ITEMS TABLE ───────────────────────────────────────────────────────── -->
<div class="find_cl" id="items_div" style="display:none;">
  <span class="div_header">Товары:</span>
  <table class="multi-table" id="items-table">
    <thead>
      <tr>
        <th style="width:30px;">#</th>
        <th style="width:110px;">Инв. №</th>
        <th>Товар</th>
        <th style="width:110px;">Выдача</th>
        <th style="width:110px;">Возврат</th>
        <th style="width:50px;">Дней</th>
        <th style="width:100px;">К оплате</th>
        <th style="width:30px;"></th>
      </tr>
    </thead>
    <tbody id="items-tbody">
      <!-- rows added by JS -->
    </tbody>
  </table>
  <button type="button" onclick="addRow()">+ Добавить товар</button>
  <div style="margin-top:8px; font-size:14px;">
    <strong>Итого к оплате: <span id="grand-total">0.00</span> руб.</strong>
  </div>
</div>

<!-- ── PAYMENT BLOCK ─────────────────────────────────────────────────────── -->
<div class="find_cl" id="payment_div" style="display:none;">
  <span class="div_header">Оплата:</span><br/>
  <div style="margin:8px 0;">
    Тип оплаты:
    <select id="pay-type">
      <option value="nal_cheque">Нал с чеком</option>
      <option value="nal_no_cheque">Нал без чека</option>
      <option value="card">Карта</option>
      <option value="bank">Банк</option>
    </select>
    &nbsp;&nbsp;
    Сумма: <input type="number" step="0.01" id="pay-total-input" value="0" style="width:100px;"/>
    <button type="button" onclick="distributePayment()">Разнести →</button>
  </div>
  <div id="pay-rows-container"></div>
  <div style="margin-top:6px; font-size:13px;">
    Итого оплачено: <strong id="pay-sum-display">0.00</strong> руб.
    &nbsp;|&nbsp; К оплате: <strong id="pay-total-display">0.00</strong> руб.
    &nbsp;|&nbsp; Разница: <span id="pay-diff">0.00</span> руб.
  </div>
</div>

<!-- ── ACTIONS ───────────────────────────────────────────────────────────── -->
<div class="find_cl" id="actions_div" style="display:none;">
  <button type="button" id="btn-save" onclick="submitForm()">Сохранить и распечатать</button>
  &nbsp;
  <button type="button" onclick="location.href='/bb/dogovor_new.php'">Отмена</button>
</div>

<script>
// ── will be filled in Tasks 3–6 ──
</script>
</body>
</html>
```

- [ ] **Step 2.2: Verify page loads**

Open `http://localhost/bb/dogovor_multi_new.php` — should show top menu, client search input, no JS errors in console.

- [ ] **Step 2.3: Commit**

```bash
git add bb/dogovor_multi_new.php
git commit -m "feat: dogovor_multi_new skeleton with layout"
```

---

## Task 3: Client search JS

**Files:**
- Modify: `bb/dogovor_multi_new.php` — replace `// ── will be filled in Tasks 3–6 ──` with JS

- [ ] **Step 3.1: Add client search JS** (replace the `<script>` block)

```html
<script>
// ── State ────────────────────────────────────────────────────────────────
var rowCount = 0;

// ── Client search ────────────────────────────────────────────────────────
function searchClient() {
    var q = document.getElementById('cl_search_input').value.trim();
    if (!q) { alert('Введите фамилию или телефон'); return; }

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.open('POST', '/bb/dog3_ajax.php', true);
    xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
            document.getElementById('cl_search_results').innerHTML = xmlhttp.responseText;
        }
    };
    xmlhttp.send('action_type=client-all-srch-clients&q=' + encodeURIComponent(q));
}

// Called by search results HTML (injected by dog3_ajax.php)
function selectClient(id, family, name, otch, phone1, phone2) {
    document.getElementById('client_id').value = id;
    document.getElementById('cl_display_name').textContent = family + ' ' + name + ' ' + otch;
    document.getElementById('cl_display_phone').textContent = phone1 + (phone2 ? ' / ' + phone2 : '');
    document.getElementById('cl_search_results').innerHTML = '';
    document.getElementById('client_search_div').style.display = 'none';
    document.getElementById('client_info_div').style.display = '';
    document.getElementById('items_div').style.display = '';
    document.getElementById('payment_div').style.display = '';
    document.getElementById('actions_div').style.display = '';
    if (rowCount === 0) addRow();
}

function resetClient() {
    document.getElementById('client_id').value = '';
    document.getElementById('client_search_div').style.display = '';
    document.getElementById('client_info_div').style.display = 'none';
    document.getElementById('items_div').style.display = 'none';
    document.getElementById('payment_div').style.display = 'none';
    document.getElementById('actions_div').style.display = 'none';
}

// ── Tasks 4–6 JS goes here ───────────────────────────────────────────────
</script>
```

- [ ] **Step 3.2: Check dog3_ajax.php response format**

Open `http://localhost/bb/dog3_ajax.php` POST with `action_type=client-all-srch-clients&q=Иванов`.  
Look at the HTML it returns — find how client rows call back. It uses a `<form>` with `action="dogovor_new.php"` and hidden fields. We need it to call `selectClient()` instead.

- [ ] **Step 3.3: Add a wrapper form action override**

In `dog3_ajax.php` the results render a form submitting to `dogovor_new.php`. Since we can't change that file, render results and intercept with JS. Add to the `<script>` block after `resetClient()`:

```js
// Intercept dog3_ajax client-select forms rendered in cl_search_results
document.getElementById('cl_search_results').addEventListener('click', function(e) {
    var btn = e.target.closest('input[type=submit], button');
    if (!btn) return;
    var form = btn.closest('form');
    if (!form) return;
    e.preventDefault();

    var id     = (form.querySelector('[name=client_id]') || {}).value || '';
    var family = (form.querySelector('[name=family]') || {}).value || '';
    var name   = (form.querySelector('[name=name]') || {}).value || '';
    var otch   = (form.querySelector('[name=otch]') || {}).value || '';
    var phone1 = (form.querySelector('[name=phone_1]') || {}).value || '';
    var phone2 = (form.querySelector('[name=phone_2]') || {}).value || '';
    if (id) selectClient(id, family, name, otch, phone1, phone2);
});
```

- [ ] **Step 3.4: Verify**

Search for a client — results appear. Click select — client info shows, item table and payment block appear.

- [ ] **Step 3.5: Commit**

```bash
git add bb/dogovor_multi_new.php
git commit -m "feat: client search in multi-contract page"
```

---

## Task 4: Item row HTML + `loadItemTarifs` + `calculateRow`

**Files:**
- Modify: `bb/dogovor_multi_new.php` — replace `// ── Tasks 4–6 JS goes here ──`

- [ ] **Step 4.1: Add `addRow()` and `removeRow()`**

```js
function addRow() {
    var i = rowCount++;
    var today = new Date().toISOString().slice(0, 10);
    var tbody = document.getElementById('items-tbody');
    var tr = document.createElement('tr');
    tr.id = 'row-' + i;
    tr.innerHTML =
        '<td style="text-align:center;">' + (i + 1) + '</td>' +
        '<td>' +
            '<input type="text" id="inv_n_' + i + '" style="width:90px;"' +
            ' onblur="loadItemTarifs(' + i + ')"' +
            ' onkeypress="if(event.keyCode==13){loadItemTarifs(' + i + ');return false;}"/>' +
            '<div class="row-status" id="status_' + i + '"></div>' +
        '</td>' +
        '<td id="item_name_' + i + '" style="font-size:12px;color:#555;">—</td>' +
        '<td><input type="date" id="start_date_' + i + '" value="' + today + '"' +
            ' onchange="calculateRow(' + i + ')"/></td>' +
        '<td><input type="date" id="return_date_' + i + '" value=""' +
            ' onchange="calculateRow(' + i + ')"/></td>' +
        '<td id="days_' + i + '" style="text-align:center;">—</td>' +
        '<td id="r_to_pay_' + i + '" style="text-align:right;font-weight:bold;">—</td>' +
        '<td><button type="button" onclick="removeRow(' + i + ')">✕</button></td>' +
        // Hidden fields for save
        '<td style="display:none;">' +
            '<input type="hidden" id="tarif_id_' + i + '" value=""/>' +
            '<input type="hidden" id="tarif_step_' + i + '" value=""/>' +
            '<input type="hidden" id="tarif_value_' + i + '" value=""/>' +
            '<input type="hidden" id="tarifs_json_' + i + '" value=""/>' +
        '</td>';
    tbody.appendChild(tr);
    updateRowNumbers();
}

function removeRow(i) {
    var tr = document.getElementById('row-' + i);
    if (tr) tr.remove();
    calculateTotal();
    updatePayRows();
}

function updateRowNumbers() {
    var rows = document.getElementById('items-tbody').querySelectorAll('tr');
    rows.forEach(function(tr, idx) {
        tr.querySelector('td:first-child').textContent = idx + 1;
    });
}
```

- [ ] **Step 4.2: Add `loadItemTarifs(i)`**

```js
function loadItemTarifs(i) {
    var inv = document.getElementById('inv_n_' + i).value.trim();
    if (!inv) return;
    var statusEl = document.getElementById('status_' + i);
    statusEl.className = 'row-status';
    statusEl.textContent = '…';

    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/bb/get_item_tarifs.php?inv_n=' + encodeURIComponent(inv), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4 || xhr.status !== 200) return;
        var data = JSON.parse(xhr.responseText);
        if (data.status === 'not_found') {
            statusEl.className = 'row-status err';
            statusEl.textContent = 'не найден';
            document.getElementById('item_name_' + i).textContent = '—';
            document.getElementById('tarifs_json_' + i).value = '';
            return;
        }
        if (data.status === 'not_available') {
            statusEl.className = 'row-status err';
            statusEl.textContent = 'занят (' + data.item_status + ')';
            return;
        }
        // ok
        statusEl.className = 'row-status ok';
        statusEl.textContent = '✓';
        document.getElementById('item_name_' + i).textContent = data.item_name;
        document.getElementById('tarifs_json_' + i).value = JSON.stringify(data.tarifs);
        calculateRow(i);
    };
    xhr.send();
}
```

- [ ] **Step 4.3: Add `calculateRow(i)` — replicates dogovor_new.php `getRentToPay` logic**

```js
function getDayDiff(d1str, d2str) {
    var d1 = new Date(d1str), d2 = new Date(d2str);
    return Math.round((d2 - d1) / 86400000);
}

function getRentToPayFromTarifs(days, tarifs) {
    if (!tarifs || tarifs.length === 0 || days < 1) return 0;
    // Sort ascending by days threshold
    var asc = tarifs.slice().sort(function(a, b) { return a.days - b.days; });
    var desc = tarifs.slice().sort(function(a, b) { return b.days - a.days; });

    // Find best fitting tarif (highest threshold <= days)
    var theTarif = asc[0];
    asc.forEach(function(t) { if (days >= t.days) theTarif = t; });

    // perDay = rent_amount / days_threshold
    var perDay = Math.round((theTarif.rent_amount / theTarif.days) * 100) / 100;
    var amount = Math.round(days * perDay * 100) / 100;

    // Ceiling: next tarif's rent_amount caps current
    var ceiling = null;
    desc.forEach(function(t) { if (t.days > theTarif.days && ceiling === null) ceiling = t.rent_amount * 1; });
    if (ceiling !== null && amount > ceiling) amount = ceiling;

    return amount;
}

function calculateRow(i) {
    var startVal  = (document.getElementById('start_date_' + i)  || {}).value;
    var returnVal = (document.getElementById('return_date_' + i) || {}).value;
    var tarifsRaw = (document.getElementById('tarifs_json_' + i) || {}).value;

    if (!startVal || !returnVal || !tarifsRaw) return;

    var days   = getDayDiff(startVal, returnVal);
    var tarifs = JSON.parse(tarifsRaw);
    var amount = getRentToPayFromTarifs(days, tarifs);

    document.getElementById('days_' + i).textContent   = days > 0 ? days : '—';
    document.getElementById('r_to_pay_' + i).textContent = days > 0 ? amount.toFixed(2) : '—';

    // Store selected tarif_id and tarif_value for save
    if (tarifs.length > 0 && days > 0) {
        var asc = tarifs.slice().sort(function(a, b) { return a.days - b.days; });
        var best = asc[0];
        asc.forEach(function(t) { if (days >= t.days) best = t; });
        document.getElementById('tarif_id_' + i).value    = best.tarif_id;
        document.getElementById('tarif_step_' + i).value  = best.step;
        document.getElementById('tarif_value_' + i).value = days > 0 ? (amount / days).toFixed(2) : 0;
    }

    calculateTotal();
    updatePayRows();
}
```

- [ ] **Step 4.4: Add `calculateTotal()`**

```js
function calculateTotal() {
    var total = 0;
    document.getElementById('items-tbody').querySelectorAll('tr').forEach(function(tr) {
        var id = tr.id.replace('row-', '');
        var cell = document.getElementById('r_to_pay_' + id);
        if (cell) {
            var v = parseFloat(cell.textContent);
            if (!isNaN(v)) total += v;
        }
    });
    document.getElementById('grand-total').textContent = total.toFixed(2);
    document.getElementById('pay-total-input').value   = total.toFixed(2);
    document.getElementById('pay-total-display').textContent = total.toFixed(2);
    updatePayDiff();
}
```

- [ ] **Step 4.5: Verify**

1. Add a row → enter a valid inv_n → green ✓ and item name appear
2. Enter a return date → days and r_to_pay calculate
3. Enter inv_n for a rented item → red "занят" appears
4. Grand total updates

- [ ] **Step 4.6: Commit**

```bash
git add bb/dogovor_multi_new.php
git commit -m "feat: item rows with tariff loading and per-row calculation"
```

---

## Task 5: Payment block JS

**Files:**
- Modify: `bb/dogovor_multi_new.php` — append to `<script>` block

- [ ] **Step 5.1: Add payment JS**

```js
function updatePayRows() {
    var container = document.getElementById('pay-rows-container');
    container.innerHTML = '';
    document.getElementById('items-tbody').querySelectorAll('tr').forEach(function(tr) {
        var id = tr.id.replace('row-', '');
        var nameEl = document.getElementById('item_name_' + id);
        var name = nameEl ? nameEl.textContent : ('Товар ' + id);
        var rToPayEl = document.getElementById('r_to_pay_' + id);
        var rToPay = rToPayEl ? (parseFloat(rToPayEl.textContent) || 0) : 0;

        var div = document.createElement('div');
        div.className = 'pay-row';
        div.id = 'pay-row-' + id;
        div.innerHTML =
            '<span style="min-width:260px;display:inline-block;">' + name + '</span>' +
            '<span>к оплате: <strong>' + rToPay.toFixed(2) + '</strong></span>' +
            '<span>оплата: <input type="number" step="0.01" id="pay_amount_' + id + '"' +
                ' value="' + rToPay.toFixed(2) + '" style="width:90px;"' +
                ' oninput="updatePayDiff()"/></span>';
        container.appendChild(div);
    });
    updatePayDiff();
}

function distributePayment() {
    var totalPay = parseFloat(document.getElementById('pay-total-input').value) || 0;
    var grandTotal = parseFloat(document.getElementById('grand-total').textContent) || 0;
    if (grandTotal <= 0) return;

    var rows = document.getElementById('items-tbody').querySelectorAll('tr');
    var ids = [];
    rows.forEach(function(tr) { ids.push(tr.id.replace('row-', '')); });
    if (ids.length === 0) return;

    var distributed = 0;
    ids.forEach(function(id, idx) {
        var rToPay = parseFloat((document.getElementById('r_to_pay_' + id) || {}).textContent) || 0;
        var share, rounded;
        if (idx < ids.length - 1) {
            share = totalPay * (rToPay / grandTotal);
            rounded = Math.round(share * 100) / 100;
            distributed += rounded;
        } else {
            // Last row gets remainder to avoid rounding drift
            rounded = Math.round((totalPay - distributed) * 100) / 100;
        }
        var inp = document.getElementById('pay_amount_' + id);
        if (inp) inp.value = rounded.toFixed(2);
    });
    updatePayDiff();
}

function updatePayDiff() {
    var totalPay = parseFloat(document.getElementById('pay-total-input').value) || 0;
    var sumPaid  = 0;
    document.getElementById('items-tbody').querySelectorAll('tr').forEach(function(tr) {
        var id = tr.id.replace('row-', '');
        var inp = document.getElementById('pay_amount_' + id);
        if (inp) sumPaid += parseFloat(inp.value) || 0;
    });
    sumPaid = Math.round(sumPaid * 100) / 100;
    var diff = Math.round((sumPaid - totalPay) * 100) / 100;

    document.getElementById('pay-sum-display').textContent = sumPaid.toFixed(2);
    var diffEl = document.getElementById('pay-diff');
    diffEl.textContent = diff.toFixed(2);
    diffEl.className = (diff === 0) ? 'exact' : (diff > 0 ? 'over' : 'under');
}
```

- [ ] **Step 5.2: Verify**

1. Add 2 rows with valid items and return dates
2. Grand total shows combined sum
3. Click «Разнести» → payment inputs fill proportionally
4. Edit one payment manually → diff updates with colour

- [ ] **Step 5.3: Commit**

```bash
git add bb/dogovor_multi_new.php
git commit -m "feat: proportional payment distribution block"
```

---

## Task 6: Client pre-fill from GET param + pre-fill if returning from save

**Files:**
- Modify: `bb/dogovor_multi_new.php`

- [ ] **Step 6.1: Add PHP pre-fill JSON output before `</body>`**

In the PHP section (before `</body>`), emit client data as JS if pre-fill exists:

```php
<?php if ($prefill_client): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    selectClient(
        <?= (int)$prefill_client['client_id'] ?>,
        <?= json_encode($prefill_client['family'], JSON_UNESCAPED_UNICODE) ?>,
        <?= json_encode($prefill_client['name'],   JSON_UNESCAPED_UNICODE) ?>,
        <?= json_encode($prefill_client['otch'],   JSON_UNESCAPED_UNICODE) ?>,
        <?= json_encode($prefill_client['phone_1'],JSON_UNESCAPED_UNICODE) ?>,
        <?= json_encode($prefill_client['phone_2'],JSON_UNESCAPED_UNICODE) ?>
    );
});
</script>
<?php endif; ?>
```

- [ ] **Step 6.2: Verify**

Open `http://localhost/bb/dogovor_multi_new.php?client_id=1234` (real client ID).  
Client info should show and items block should appear.

- [ ] **Step 6.3: Commit**

```bash
git add bb/dogovor_multi_new.php
git commit -m "feat: client pre-fill via GET param"
```

---

## Task 7: PHP POST save handler

**Files:**
- Modify: `bb/dogovor_multi_new.php` — replace `// TODO Task 7` section

- [ ] **Step 7.1: Replace the POST placeholder with full save logic**

Find the comment `// TODO Task 7` and replace the entire `if POST` block:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    header('Content-Type: application/json; charset=utf-8');

    // ── Collect and validate input ─────────────────────────────────────
    $client_id   = intval($_POST['client_id'] ?? 0);
    $inv_ns      = array_map('intval',   $_POST['inv_n']      ?? []);
    $start_dates = array_map('strval',   $_POST['start_date'] ?? []);
    $ret_dates   = array_map('strval',   $_POST['return_date']?? []);
    $tarif_ids   = array_map('intval',   $_POST['tarif_id']   ?? []);
    $tarif_steps = array_map('strval',   $_POST['tarif_step'] ?? []);
    $tarif_vals  = array_map('floatval', $_POST['tarif_value']?? []);
    $r_to_pays   = array_map('floatval', $_POST['r_to_pay']   ?? []);
    $pay_amounts = array_map('floatval', $_POST['pay_amount'] ?? []);
    $pay_type    = preg_replace('/[^a-z_]/', '', $_POST['pay_type'] ?? '');

    $n = count($inv_ns);
    if ($client_id <= 0 || $n < 1) {
        echo json_encode(['status' => 'error', 'msg' => 'invalid input']);
        exit;
    }

    // Verify client exists
    $rc = $mysqli->query("SELECT client_id FROM clients WHERE client_id='$client_id'");
    if (!$rc || $rc->num_rows === 0) {
        echo json_encode(['status' => 'error', 'msg' => 'client not found']);
        exit;
    }

    // Verify all items are free
    for ($i = 0; $i < $n; $i++) {
        $inv = $inv_ns[$i];
        $rr = $mysqli->query(
            "SELECT status, br_time FROM tovar_rent_items WHERE item_inv_n='$inv'"
        );
        if (!$rr || $rr->num_rows === 0) {
            echo json_encode(['status' => 'error', 'msg' => "item $inv not found"]);
            exit;
        }
        $it = $rr->fetch_assoc();
        $ok = ($it['status'] === 'to_rent')
           || (in_array($it['status'], ['bron','t_bron']) && $it['br_time'] < time());
        if (!$ok) {
            echo json_encode(['status' => 'error', 'msg' => "item $inv not available: {$it['status']}"]);
            exit;
        }
    }

    $office      = intval($_SESSION['office']);
    $user_id     = $mysqli->real_escape_string($_SESSION['user_id']);
    $now         = time();
    $created_deals = [];

    // ── Insert each deal ───────────────────────────────────────────────
    for ($i = 0; $i < $n; $i++) {
        $inv      = $inv_ns[$i];
        $start_ts = strtotime($start_dates[$i] . ' 00:00:00');
        $ret_ts   = strtotime($ret_dates[$i]   . ' 23:59:00');
        $rtp      = number_format($r_to_pays[$i], 2, '.', '');
        $tid      = $tarif_ids[$i];
        $tstep    = $mysqli->real_escape_string($tarif_steps[$i]);
        $tval     = number_format($tarif_vals[$i], 2, '.', '');
        $days     = max(1, intval(round(($ret_ts - $start_ts) / 86400)));
        $pay_a    = number_format($pay_amounts[$i] ?? 0, 2, '.', '');

        // Get item_set for deal_set
        $ri = $mysqli->query("SELECT item_set, item_place FROM tovar_rent_items WHERE item_inv_n='$inv'");
        $itRow = $ri->fetch_assoc();
        $deal_set  = $mysqli->real_escape_string($itRow['item_set'] ?? '');
        $sub_place = intval($itRow['item_place'] ?? $office);

        // rent_deals_act
        $q_deal = "INSERT INTO rent_deals_act VALUES(
            '', '$client_id', '$inv', '$start_ts', '$ret_ts',
            '0', '0.00', '0.00', '$rtp', '0.00',
            '0.00', 'BYN', 'active', '',
            '$user_id', '$user_id', '$now', '$now', '$start_ts',
            '$deal_set', '$sub_place')";
        $mysqli->query($q_deal);
        $deal_id = $mysqli->insert_id;

        // rent_sub_deals_act — first_rent
        $q_sub = "INSERT INTO rent_sub_deals_act VALUES(
            '', '$deal_id', 'first_rent', '10',
            '$start_ts', '$ret_ts',
            '$tid', '$tstep', '$tval', '$days', '$rtp',
            '0', '0.00', '0',
            '', '', '', '', 'active', '',
            '$now', '$user_id', '', '', '', '$start_ts', '$sub_place',
            '', '', '', '')";
        $mysqli->query($q_sub);
        $sub_id = $mysqli->insert_id;

        // payment sub_deal if amount > 0
        if ($pay_amounts[$i] > 0) {
            $pt = $mysqli->real_escape_string($pay_type);
            $q_pay = "INSERT INTO rent_sub_deals_act VALUES(
                '', '$deal_id', 'payment', '30',
                '$start_ts', '', '', '', '', '', '',
                '0', '0.00', '0',
                '$pay_a', '0.00', '$pt', '', 'pure_payment', '',
                '$now', '$user_id', '', '', '$sub_id', '$start_ts', '$sub_place',
                '', '', '', '')";
            $mysqli->query($q_pay);
            // Update r_paid in deal
            $mysqli->query("UPDATE rent_deals_act SET r_paid='$pay_a' WHERE deal_id='$deal_id'");
        }

        // Mark item as rented
        $mysqli->query(
            "UPDATE tovar_rent_items SET status='rented', active_deal_id='$deal_id' WHERE item_inv_n='$inv'"
        );

        $created_deals[] = $deal_id;
    }

    // Return deal IDs for RTF generation — Task 8 will use these
    echo json_encode(['status' => 'ok', 'deal_ids' => $created_deals]);
    exit;
}
```

- [ ] **Step 7.2: Add `submitForm()` JS**

Add to the `<script>` block:

```js
function submitForm() {
    var clientId = document.getElementById('client_id').value;
    if (!clientId) { alert('Выберите клиента'); return; }

    var rows = document.getElementById('items-tbody').querySelectorAll('tr');
    if (rows.length === 0) { alert('Добавьте хотя бы один товар'); return; }

    var data = new URLSearchParams();
    data.append('action', 'save');
    data.append('client_id', clientId);
    data.append('pay_type', document.getElementById('pay-type').value);

    var ok = true;
    rows.forEach(function(tr) {
        var id = tr.id.replace('row-', '');
        var inv      = (document.getElementById('inv_n_' + id)       || {}).value || '';
        var start    = (document.getElementById('start_date_' + id)   || {}).value || '';
        var ret      = (document.getElementById('return_date_' + id)  || {}).value || '';
        var tid      = (document.getElementById('tarif_id_' + id)     || {}).value || '';
        var tstep    = (document.getElementById('tarif_step_' + id)   || {}).value || '';
        var tval     = (document.getElementById('tarif_value_' + id)  || {}).value || '0';
        var rtp      = parseFloat((document.getElementById('r_to_pay_' + id) || {}).textContent) || 0;
        var payAmt   = parseFloat((document.getElementById('pay_amount_' + id) || {}).value) || 0;

        if (!inv || !start || !ret || !tid) { ok = false; return; }

        data.append('inv_n[]',      inv);
        data.append('start_date[]', start);
        data.append('return_date[]',ret);
        data.append('tarif_id[]',   tid);
        data.append('tarif_step[]', tstep);
        data.append('tarif_value[]',tval);
        data.append('r_to_pay[]',   rtp.toFixed(2));
        data.append('pay_amount[]', payAmt.toFixed(2));
    });

    if (!ok) { alert('Заполните все поля (инв. №, даты, тариф) для каждого товара'); return; }

    document.getElementById('btn-save').disabled = true;
    document.getElementById('btn-save').textContent = 'Сохраняется…';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/bb/dogovor_multi_new.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        document.getElementById('btn-save').disabled = false;
        document.getElementById('btn-save').textContent = 'Сохранить и распечатать';
        if (xhr.status !== 200) { alert('Ошибка сервера'); return; }
        var resp = JSON.parse(xhr.responseText);
        if (resp.status !== 'ok') { alert('Ошибка: ' + resp.msg); return; }
        // Redirect to print — Task 8
        window.location.href = '/bb/dogovor_multi_new.php?print=1&ids=' + resp.deal_ids.join(',');
    };
    xhr.send(data.toString());
}
```

- [ ] **Step 7.3: Verify save**

1. Select a client
2. Add 2 items with valid inv_n and return dates
3. Click «Разнести», then «Сохранить и распечатать»
4. Check DB: 2 rows in `rent_deals_act`, 2 `first_rent` + 2 `payment` rows in `rent_sub_deals_act`
5. Check `tovar_rent_items`: both items have `status='rented'`

```sql
-- Run in docker:
SELECT deal_id, client_id, item_inv_n, r_to_pay, r_paid FROM rent_deals_act ORDER BY deal_id DESC LIMIT 4;
SELECT deal_id, type, r_to_pay, r_paid, r_payment_type FROM rent_sub_deals_act ORDER BY sub_deal_id DESC LIMIT 8;
```

- [ ] **Step 7.4: Commit**

```bash
git add bb/dogovor_multi_new.php
git commit -m "feat: POST save handler — creates deals and payments"
```

---

## Task 8: RTF generation

**Files:**
- Create: `bb/ndk_multi.rtf`
- Modify: `bb/dogovor_multi_new.php`

- [ ] **Step 8.1: Create `ndk_multi.rtf`**

Copy `ndk_1.rtf` to `ndk_multi.rtf`:

```bash
cp bb/ndk_1.rtf bb/ndk_multi.rtf
```

Open `ndk_multi.rtf` in a text editor (not Word). Find (Ctrl+F) `itemname` — this is the product name placeholder. The rows around it form the goods table. Replace the contents of the goods table section (from the first `\trowd` of the goods table up to and including the final `\row` of the last goods row, **but keep the header row**) with the text `items_rows` — plain ASCII, no RTF escaping.

The table header row (labels like "Наименование", "Стоимость" etc.) stays. Only the data rows get replaced by `items_rows`.

Also:
- Find `rto_pay_sum` (total rental cost) and add `rto_pay_total` nearby (or replace if it's the only total)
- Find `rto_pay_cur` and add `rto_pay_total_words` nearby

Save as Windows-1251 encoding (or as UTF-8 — `RTF_Template` constructor handles encoding).

- [ ] **Step 8.2: Add RTF generation PHP code**

At the top of `dogovor_multi_new.php`, add these helper functions in the PHP section (before the `if POST` block):

```php
function encode_for_rtf_multi(string $str): string {
    $out = '';
    $len = mb_strlen($str, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($str, $i, 1, 'UTF-8');
        $code = mb_ord($char, 'UTF-8');
        if ($code < 128) {
            $out .= $char;
        } else {
            $win = iconv('UTF-8', 'Windows-1251//IGNORE', $char);
            if ($win !== false && $win !== '') {
                $out .= '\\\''. sprintf('%02x', ord($win));
            }
        }
    }
    return $out;
}

function generateRtfItemRows(array $items): string {
    // Each $item: ['name', 'inv_n', 'start_ts', 'return_ts', 'r_to_pay']
    // Generates simple RTF paragraph lines (no table) — safe & portable
    $rows = '';
    foreach ($items as $k => $item) {
        $n    = $k + 1;
        $line = $n . '. ' . $item['name']
              . ': c ' . date('d.m.Y', $item['start_ts'])
              . ' \\\'' . sprintf('%02x', ord(iconv('UTF-8','Windows-1251//IGNORE','п'))) // по
              . ' ' . date('d.m.Y', $item['return_ts'])
              . ' - ' . number_format($item['r_to_pay'], 2, ',', ' ') . ' \\\'' . sprintf('%02x', ord(iconv('UTF-8','Windows-1251//IGNORE','р'))) . '.';
        $rows .= '\\par ' . encode_for_rtf_multi($line);
    }
    return $rows;
}
```

> **Note:** `encode_for_rtf_multi` is a self-contained copy of `encode_for_rtf` from `dogovor_new.php` — do not import from that file to avoid coupling.

- [ ] **Step 8.3: Add GET print handler**

In `dogovor_multi_new.php`, add this block after the auth check and before the POST handler:

```php
// ── GET ?print=1&ids=5,6,7 ────────────────────────────────────────────────
if (isset($_GET['print']) && $_GET['print'] == '1' && isset($_GET['ids'])) {
    $raw_ids = explode(',', $_GET['ids']);
    $deal_ids = array_map('intval', $raw_ids);
    $deal_ids = array_filter($deal_ids, fn($id) => $id > 0);
    if (empty($deal_ids)) die('No deals');

    $ids_sql = implode(',', $deal_ids);

    // Load deals + sub_deals + client
    $q = "SELECT d.*, s.tarif_id, s.tarif_step, s.tarif_value, s.rent_tenor,
                 s.r_to_pay as sd_r_to_pay, s.`from` as from_ts, s.`to` as to_ts,
                 i.item_set,
                 m.model, m.agr_price, m.agr_price_cur, m.model_addr, m.producer,
                 c_cat.dog_name
          FROM rent_deals_act d
          JOIN rent_sub_deals_act s ON s.deal_id = d.deal_id AND s.type = 'first_rent'
          JOIN tovar_rent_items i ON i.item_inv_n = d.item_inv_n
          JOIN tovar_rent m ON m.tovar_rent_id = i.model_id
          JOIN tovar_rent_cat c_cat ON c_cat.tovar_rent_cat_id = m.tovar_rent_cat_id
          WHERE d.deal_id IN ($ids_sql)
          ORDER BY d.deal_id";
    $res = $mysqli->query($q);
    if (!$res || $res->num_rows === 0) die('Deals not found');

    $deals = [];
    $client_id_chk = null;
    while ($row = $res->fetch_assoc()) {
        if ($client_id_chk === null) $client_id_chk = $row['client_id'];
        if ($row['client_id'] != $client_id_chk) die('Mixed client IDs');
        $deals[] = $row;
    }

    // Load client
    $rc = $mysqli->query("SELECT * FROM clients WHERE client_id='$client_id_chk'");
    $cl = $rc->fetch_assoc();

    // Build items array for RTF
    $items = [];
    $total = 0;
    foreach ($deals as $d) {
        $name = ($d['model_addr'] !== '' ? $d['model_addr'] : $d['dog_name'])
              . ': ' . $d['model']
              . ' (инв.№' . substr($d['item_inv_n'], 0, 3) . '-' . substr($d['item_inv_n'], 3) . ')';
        $r_to_pay = floatval($d['sd_r_to_pay']);
        $total   += $r_to_pay;
        $items[] = [
            'name'     => $name,
            'inv_n'    => $d['item_inv_n'],
            'start_ts' => $d['from_ts'],
            'return_ts'=> $d['to_ts'],
            'r_to_pay' => $r_to_pay,
        ];
    }

    // FIO
    $fio = trim($cl['family'] . ' ' . $cl['name'] . ' ' . $cl['otch']);
    // Address
    $address = 'г.' . $cl['city'] . ', ул.' . $cl['str'] . ', д.' . $cl['dom']
             . ($cl['kv'] ? ', кв.' . $cl['kv'] : '');
    $reg_address = 'г.' . $cl['reg_city'] . ', ул.' . $cl['reg_str'] . ', д.' . $cl['reg_dom']
                 . ($cl['reg_kv'] ? ', кв.' . $cl['reg_kv'] : '');
    // Passport initials
    $pln = mb_substr($cl['family'], 0, 1, 'UTF-8') . '.'
         . mb_substr($cl['name'],   0, 1, 'UTF-8') . '.'
         . mb_substr($cl['otch'],   0, 1, 'UTF-8') . '.';
    // Phone format: remove non-digits, add +375
    $fmt_phone = function($ph) {
        $d = preg_replace('/\D/', '', $ph);
        return $d ? '+375' . ltrim($d, '375') : '';
    };

    // nomer_dogovora — all deal IDs
    $nomer = implode(', ', array_column($deals, 'deal_id'));

    // RTF
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Signature.php');

    class RTF_Multi extends RTF_Template {}
    // Reuse RTF_Template defined later in this file — but since we're in the same file,
    // we must ensure class is available. Move RTF_Template to a separate include or
    // duplicate the class at top of this file (see Step 8.4).

    $rtf = new RTF_Template_Multi(__DIR__ . '/ndk_multi.rtf');
    $rtf->parse('fioone',         encode_for_rtf_multi($fio));
    $rtf->parse('fiotwo',         encode_for_rtf_multi($fio));
    $rtf->parse('nomer_dogovora', encode_for_rtf_multi($nomer));
    $rtf->parse('actaddress',     encode_for_rtf_multi($address));
    $rtf->parse('reg_address',    encode_for_rtf_multi($reg_address));
    $rtf->parse('pas_n',          encode_for_rtf_multi($cl['pas_n']));
    $rtf->parse('pas_date',       encode_for_rtf_multi(
        $cl['pas_date'] == 0 ? '_________' : date('d.m.Y', $cl['pas_date'])
    ));
    $rtf->parse('pas_who',        encode_for_rtf_multi($cl['pas_who']));
    $rtf->parse('phone_1',        encode_for_rtf_multi($fmt_phone($cl['phone_1'])));
    $rtf->parse('phone_2',        encode_for_rtf_multi($fmt_phone($cl['phone_2'])));
    $rtf->parse('pasln',          encode_for_rtf_multi($pln));
    $rtf->parse('acc_date',       encode_for_rtf_multi(date('d.m.Y')));
    $rtf->parse('start_date',     encode_for_rtf_multi(date('d.m.Y', $deals[0]['from_ts'])));
    $rtf->parse('items_rows',     generateRtfItemRows($items));
    $rtf->parse('rto_pay_total',  encode_for_rtf_multi(number_format($total, 2, ',', ' ')));
    $rtf->parse('rto_pay_total_words', encode_for_rtf_multi(\bb\Base::sum2words($total)));

    $sgn = new \bb\Signature($cl['pas_n']);
    $rtf->parse('signaturestart', encode_for_rtf_multi($sgn->StartText()));
    $rtf->parse('signatureend',   encode_for_rtf_multi($sgn->ShortSignature()));

    $rtf->out_h('ndk_multi.rtf');
    echo $rtf->out();
    exit;
}
```

- [ ] **Step 8.4: Add `RTF_Template_Multi` class at top of file**

The `RTF_Template` class is defined at the bottom of `dogovor_new.php` — not available here. Copy it verbatim to `dogovor_multi_new.php`, renamed to `RTF_Template_Multi` to avoid conflicts if ever both files are included:

Add just before the closing `?>` or at the bottom of the PHP section — after all requires, before HTML output:

```php
class RTF_Template_Multi {
    private $content;
    public function __construct($filename) {
        $this->content = file_get_contents($filename);
        if (strpos($this->content, '\ansicpg1251') === false) {
            $this->content = preg_replace('/\\\\ansicpg[0-9]+/', '', $this->content);
            $this->content = preg_replace('/\{\\\\rtf1\\\\ansi/', '{\\\\rtf1\\\\ansi\\\\ansicpg1251', $this->content, 1);
        }
    }
    public function parse($block_name, $value) {
        $this->content = str_ireplace($block_name, $value, $this->content);
    }
    public function out_h($filename) {
        ob_clean();
        header('Content-type: plaintext/rtf');
        header("Content-Disposition: attachment; filename=$filename");
        echo $this->content;
    }
    public function out() { return $this->content; }
}
```

Update the `new RTF_Template_Multi(...)` call in the print handler to use `__DIR__ . '/ndk_multi.rtf'` (absolute path, since working directory may vary).

- [ ] **Step 8.5: Verify RTF output**

1. Save a 2-item contract via the UI
2. Browser should redirect to `?print=1&ids=X,Y` and prompt download of `ndk_multi.rtf`
3. Open in Word/LibreOffice — verify client name, deal numbers, item list, total amount appear correctly
4. Verify Cyrillic text is not garbled

- [ ] **Step 8.6: Commit**

```bash
git add bb/dogovor_multi_new.php bb/ndk_multi.rtf
git commit -m "feat: RTF generation for multi-item contract"
```

---

## Task 9: Menu link + final smoke test

**Files:**
- Modify: `bb/dogovor_new.php` line ~1445 (top_menu div)

- [ ] **Step 9.1: Add menu link to `dogovor_new.php`**

Find the `top_menu` div in `dogovor_new.php` (around line 1445):

```php
echo '<div class="top_menu">
    <a class="div_item" href="/bb/index.php">На главную</a>
    ...
```

Add one line:

```php
<a class="div_item" href="/bb/dogovor_multi_new.php">Мультидоговор</a>
```

- [ ] **Step 9.2: Full end-to-end smoke test**

1. Open `dogovor_new.php` — confirm «Мультидоговор» link appears in menu
2. Click link → `dogovor_multi_new.php` opens
3. Search and select a client
4. Add 2 items, set return dates
5. Click «Разнести» — amounts distribute
6. Click «Сохранить и распечатать»
7. RTF downloads and opens correctly
8. Check DB: both items now `status='rented'` in `tovar_rent_items`
9. Open `dogovor_new.php`, enter one of the new deal's item inv_n — it should show as "занят"

- [ ] **Step 9.3: Final commit**

```bash
git add bb/dogovor_new.php
git commit -m "feat: add Мультидоговор link to dogovor_new.php menu"
```

---

## Self-Review Notes

- **Spec §auth**: covered in every file (Task 1 step 1, Task 2 step 1) ✓
- **Spec §get_item_tarifs**: Task 1 ✓
- **Spec §client search**: Task 3 ✓ (with interception workaround for dog3_ajax.php forms)
- **Spec §tariff calculation**: Task 4 step 3 — replicates `getRentToPay` from `dogovor_new.php` ✓
- **Spec §payment distribution**: Task 5 ✓ with last-row rounding correction ✓
- **Spec §PHP save — rent_deals_act INSERT**: Task 7 ✓
- **Spec §PHP save — rent_sub_deals_act first_rent**: Task 7 ✓
- **Spec §PHP save — payment**: Task 7 ✓
- **Spec §tovar_rent_items status update**: Task 7 ✓
- **Spec §ndk_multi.rtf**: Task 8 step 1 — note: requires manual RTF editing; see Step 8.1 instructions ✓
- **Spec §dogovor_new.php menu link**: Task 9 ✓
- **Spec §client pre-fill via GET**: Task 6 ✓
- **Spec limitation — no delivery**: not implemented (by design) ✓
- **Spec limitation — one payment type**: implemented (single `pay_type` field) ✓
