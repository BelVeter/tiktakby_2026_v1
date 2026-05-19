<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');

isset($_SESSION['svoi']) ? : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941) {
    die('<a href="/bb/index.php">Залогиниться</a>');
}

$mysqli = \bb\Db::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    header('Content-Type: application/json; charset=utf-8');

    $client_id   = intval($_POST['client_id'] ?? 0);
    $inv_ns      = array_map('intval',   $_POST['inv_n']       ?? []);
    $start_dates = array_map('strval',   $_POST['start_date']  ?? []);
    $ret_dates   = array_map('strval',   $_POST['return_date'] ?? []);
    $tarif_ids   = array_map('intval',   $_POST['tarif_id']    ?? []);
    $tarif_steps = array_map('strval',   $_POST['tarif_step']  ?? []);
    $tarif_vals  = array_map('floatval', $_POST['tarif_value'] ?? []);
    $r_to_pays   = array_map('floatval', $_POST['r_to_pay']    ?? []);
    $pay_amounts = array_map('floatval', $_POST['pay_amount']  ?? []);
    $pay_type    = preg_replace('/[^a-z_]/', '', $_POST['pay_type'] ?? '');

    $n = count($inv_ns);
    if ($client_id <= 0 || $n < 1) {
        echo json_encode(['status' => 'error', 'msg' => 'invalid input']);
        exit;
    }

    $rc = $mysqli->query("SELECT client_id FROM clients WHERE client_id = $client_id");
    if (!$rc || $rc->num_rows === 0) {
        echo json_encode(['status' => 'error', 'msg' => 'client not found']);
        exit;
    }

    for ($i = 0; $i < $n; $i++) {
        $inv = $inv_ns[$i];
        $rr = $mysqli->query("SELECT status, br_time FROM tovar_rent_items WHERE item_inv_n = $inv");
        if (!$rr || $rr->num_rows === 0) {
            echo json_encode(['status' => 'error', 'msg' => "item $inv not found"]);
            exit;
        }
        $it = $rr->fetch_assoc();
        $ok = ($it['status'] === 'to_rent')
           || (in_array($it['status'], ['bron', 't_bron']) && $it['br_time'] < time());
        if (!$ok) {
            echo json_encode(['status' => 'error', 'msg' => "item $inv not available: {$it['status']}"]);
            exit;
        }
    }

    $office  = intval($_SESSION['office']);
    $user_id = intval($_SESSION['user_id']);
    $now     = time();
    $created_deals = [];

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

        $ri = $mysqli->query("SELECT item_set, item_place FROM tovar_rent_items WHERE item_inv_n = $inv");
        $itRow     = $ri->fetch_assoc();
        $deal_set  = $mysqli->real_escape_string($itRow['item_set'] ?? '');
        $sub_place = intval($itRow['item_place'] ?? $office);

        $mysqli->query("INSERT INTO rent_deals_act VALUES(
            '', '$client_id', '$inv', '$start_ts', '$ret_ts',
            '0', '0.00', '0.00', '$rtp', '0.00',
            '0.00', 'BYN', 'active', '',
            '$user_id', '$user_id', '$now', '$now', '$start_ts',
            '$deal_set', '$sub_place')");
        $deal_id = $mysqli->insert_id;

        $mysqli->query("INSERT INTO rent_sub_deals_act VALUES(
            '', '$deal_id', 'first_rent', '10',
            '$start_ts', '$ret_ts',
            '$tid', '$tstep', '$tval', '$days', '$rtp',
            '0', '0.00', '0',
            '', '', '', '', 'active', '',
            '$now', '$user_id', '', '', '', '$start_ts', '$sub_place',
            '', '', '', '')");
        $sub_id = $mysqli->insert_id;

        if (($pay_amounts[$i] ?? 0) > 0) {
            $pt = $mysqli->real_escape_string($pay_type);
            $mysqli->query("INSERT INTO rent_sub_deals_act VALUES(
                '', '$deal_id', 'payment', '30',
                '$start_ts', '', '', '', '', '', '',
                '0', '0.00', '0',
                '$pay_a', '0.00', '$pt', '', 'pure_payment', '',
                '$now', '$user_id', '', '', '$sub_id', '$start_ts', '$sub_place',
                '', '', '', '')");
            $mysqli->query("UPDATE rent_deals_act SET r_paid = '$pay_a' WHERE deal_id = '$deal_id'");
        }

        $mysqli->query("UPDATE tovar_rent_items SET status = 'rented', active_deal_id = '$deal_id' WHERE item_inv_n = $inv");

        $created_deals[] = $deal_id;
    }

    echo json_encode(['status' => 'ok', 'deal_ids' => $created_deals]);
    exit;
}

// ── GET ?print placeholder (filled in Task 8) ─────────────────────────────
if (isset($_GET['print']) && $_GET['print'] == '1') {
    echo '<p>RTF generation not yet implemented (Task 8)</p>';
    exit;
}

// ── Client pre-fill from GET ?client_id=N (filled in Task 6) ─────────────
$prefill_client = null;
if (isset($_GET['client_id']) && intval($_GET['client_id']) > 0) {
    $cid = intval($_GET['client_id']);
    $rc = $mysqli->query("SELECT * FROM clients WHERE client_id = $cid");
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
    Вы зашли как: <strong><?= htmlspecialchars($_SESSION['user_fio'] ?? '') ?></strong>
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
    <tbody id="items-tbody"></tbody>
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

// Intercept dog3_ajax client-select forms rendered in cl_search_results
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('cl_search_results').addEventListener('click', function(e) {
        var btn = e.target.closest('input[type=submit], button[type=submit], button:not([type])');
        if (!btn) return;
        var form = btn.closest('form');
        if (!form) return;
        e.preventDefault();

        var id     = (form.querySelector('[name=client_id]')  || {}).value || '';
        var family = (form.querySelector('[name=family]')     || {}).value || '';
        var name   = (form.querySelector('[name=name]')       || {}).value || '';
        var otch   = (form.querySelector('[name=otch]')       || {}).value || '';
        var phone1 = (form.querySelector('[name=phone_1]')    || {}).value || '';
        var phone2 = (form.querySelector('[name=phone_2]')    || {}).value || '';
        if (id) selectClient(id, family, name, otch, phone1, phone2);
    });
});

// ── Item row management (Task 4) ─────────────────────────────────────────
function addRow() {
    var i = rowCount++;
    var today = new Date().toISOString().slice(0, 10);
    var tbody = document.getElementById('items-tbody');
    var tr = document.createElement('tr');
    tr.id = 'row-' + i;
    tr.innerHTML =
        '<td style="text-align:center;" class="row-num">' + (i + 1) + '</td>' +
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
        var numCell = tr.querySelector('.row-num');
        if (numCell) numCell.textContent = idx + 1;
    });
}

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
        var data;
        try { data = JSON.parse(xhr.responseText); } catch(e) {
            statusEl.className = 'row-status err';
            statusEl.textContent = 'ошибка ответа';
            return;
        }
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
        statusEl.className = 'row-status ok';
        statusEl.textContent = '✓';
        document.getElementById('item_name_' + i).textContent = data.item_name;
        document.getElementById('tarifs_json_' + i).value = JSON.stringify(data.tarifs);
        calculateRow(i);
    };
    xhr.send();
}

function getDayDiff(d1str, d2str) {
    var d1 = new Date(d1str), d2 = new Date(d2str);
    return Math.round((d2 - d1) / 86400000);
}

function getRentToPayFromTarifs(days, tarifs) {
    if (!tarifs || tarifs.length === 0 || days < 1) return 0;
    var asc  = tarifs.slice().sort(function(a, b) { return a.days - b.days; });
    var desc = tarifs.slice().sort(function(a, b) { return b.days - a.days; });

    var theTarif = asc[0];
    asc.forEach(function(t) { if (days >= t.days) theTarif = t; });

    var perDay = Math.round((theTarif.rent_amount / theTarif.days) * 100) / 100;
    var amount = Math.round(days * perDay * 100) / 100;

    var ceiling = null;
    desc.forEach(function(t) { if (t.days > theTarif.days && ceiling === null) ceiling = t.rent_amount * 1; });
    if (ceiling !== null && amount > ceiling) amount = ceiling;

    return amount;
}

function calculateRow(i) {
    var startEl  = document.getElementById('start_date_' + i);
    var returnEl = document.getElementById('return_date_' + i);
    var tarifsEl = document.getElementById('tarifs_json_' + i);
    if (!startEl || !returnEl || !tarifsEl) return;

    var startVal  = startEl.value;
    var returnVal = returnEl.value;
    var tarifsRaw = tarifsEl.value;
    if (!startVal || !returnVal || !tarifsRaw) return;

    var days   = getDayDiff(startVal, returnVal);
    var tarifs = JSON.parse(tarifsRaw);
    var amount = getRentToPayFromTarifs(days, tarifs);

    document.getElementById('days_' + i).textContent    = days > 0 ? days : '—';
    document.getElementById('r_to_pay_' + i).textContent = days > 0 ? amount.toFixed(2) : '—';

    if (tarifs.length > 0 && days > 0) {
        var asc  = tarifs.slice().sort(function(a, b) { return a.days - b.days; });
        var best = asc[0];
        asc.forEach(function(t) { if (days >= t.days) best = t; });
        document.getElementById('tarif_id_' + i).value    = best.tarif_id;
        document.getElementById('tarif_step_' + i).value  = best.step;
        document.getElementById('tarif_value_' + i).value = (amount / days).toFixed(2);
    }

    calculateTotal();
    updatePayRows();
}

function calculateTotal() {
    var total = 0;
    document.getElementById('items-tbody').querySelectorAll('tr').forEach(function(tr) {
        var id   = tr.id.replace('row-', '');
        var cell = document.getElementById('r_to_pay_' + id);
        if (cell) {
            var v = parseFloat(cell.textContent);
            if (!isNaN(v)) total += v;
        }
    });
    total = Math.round(total * 100) / 100;
    document.getElementById('grand-total').textContent       = total.toFixed(2);
    document.getElementById('pay-total-input').value         = total.toFixed(2);
    document.getElementById('pay-total-display').textContent = total.toFixed(2);
    updatePayDiff();
}

// ── Payment block (Task 5) ────────────────────────────────────────────────
function updatePayRows() {
    var container = document.getElementById('pay-rows-container');
    container.innerHTML = '';
    document.getElementById('items-tbody').querySelectorAll('tr').forEach(function(tr) {
        var id     = tr.id.replace('row-', '');
        var nameEl = document.getElementById('item_name_' + id);
        var name   = (nameEl && nameEl.textContent !== '—') ? nameEl.textContent : 'Товар ' + (parseInt(id) + 1);
        var rToPayEl = document.getElementById('r_to_pay_' + id);
        var rToPay   = rToPayEl ? (parseFloat(rToPayEl.textContent) || 0) : 0;

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
    var totalPay   = parseFloat(document.getElementById('pay-total-input').value) || 0;
    var grandTotal = parseFloat(document.getElementById('grand-total').textContent) || 0;
    if (grandTotal <= 0) return;

    var ids = [];
    document.getElementById('items-tbody').querySelectorAll('tr').forEach(function(tr) {
        ids.push(tr.id.replace('row-', ''));
    });
    if (ids.length === 0) return;

    var distributed = 0;
    ids.forEach(function(id, idx) {
        var rToPay = parseFloat((document.getElementById('r_to_pay_' + id) || {}).textContent) || 0;
        var rounded;
        if (idx < ids.length - 1) {
            rounded = Math.round(totalPay * (rToPay / grandTotal) * 100) / 100;
            distributed += rounded;
        } else {
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
        var id  = tr.id.replace('row-', '');
        var inp = document.getElementById('pay_amount_' + id);
        if (inp) sumPaid += parseFloat(inp.value) || 0;
    });
    sumPaid = Math.round(sumPaid * 100) / 100;
    var diff = Math.round((sumPaid - totalPay) * 100) / 100;

    document.getElementById('pay-sum-display').textContent = sumPaid.toFixed(2);
    var diffEl = document.getElementById('pay-diff');
    diffEl.textContent = diff.toFixed(2);
    diffEl.className   = (diff === 0) ? 'exact' : (diff > 0 ? 'over' : 'under');
}

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
        var id    = tr.id.replace('row-', '');
        var inv   = (document.getElementById('inv_n_' + id)      || {}).value || '';
        var start = (document.getElementById('start_date_' + id)  || {}).value || '';
        var ret   = (document.getElementById('return_date_' + id) || {}).value || '';
        var tid   = (document.getElementById('tarif_id_' + id)    || {}).value || '';
        var tstep = (document.getElementById('tarif_step_' + id)  || {}).value || '';
        var tval  = (document.getElementById('tarif_value_' + id) || {}).value || '0';
        var rtp   = parseFloat((document.getElementById('r_to_pay_' + id) || {}).textContent) || 0;
        var payA  = parseFloat((document.getElementById('pay_amount_' + id) || {}).value) || 0;

        if (!inv || !start || !ret || !tid) { ok = false; return; }

        data.append('inv_n[]',       inv);
        data.append('start_date[]',  start);
        data.append('return_date[]', ret);
        data.append('tarif_id[]',    tid);
        data.append('tarif_step[]',  tstep);
        data.append('tarif_value[]', tval);
        data.append('r_to_pay[]',    rtp.toFixed(2));
        data.append('pay_amount[]',  payA.toFixed(2));
    });

    if (!ok) { alert('Заполните инв. №, даты выдачи и возврата для каждого товара'); return; }

    var btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.textContent = 'Сохраняется…';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/bb/dogovor_multi_new.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        btn.disabled = false;
        btn.textContent = 'Сохранить и распечатать';
        if (xhr.status !== 200) { alert('Ошибка сервера'); return; }
        var resp;
        try { resp = JSON.parse(xhr.responseText); } catch(e) { alert('Ответ сервера не распознан'); return; }
        if (resp.status !== 'ok') { alert('Ошибка: ' + (resp.msg || resp.status)); return; }
        window.location.href = '/bb/dogovor_multi_new.php?print=1&ids=' + resp.deal_ids.join(',');
    };
    xhr.send(data.toString());
}
</script>

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

</body>
</html>
