<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');

isset($_SESSION['svoi']) ? : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941) {
    die('<a href="/bb/index.php">Залогиниться</a>');
}

$mysqli = \bb\Db::getInstance()->getConnection();

// ── POST handler placeholder (filled in Task 7) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'not_implemented']);
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

// ── Item rows (Tasks 4–5 will fill these) ────────────────────────────────
function addRow() { /* Task 4 */ }
function removeRow(i) { /* Task 4 */ }
function updateRowNumbers() { /* Task 4 */ }
function loadItemTarifs(i) { /* Task 4 */ }
function calculateRow(i) { /* Task 4 */ }
function calculateTotal() { /* Task 4 */ }
function updatePayRows() { /* Task 5 */ }
function distributePayment() { /* Task 5 */ }
function updatePayDiff() { /* Task 5 */ }
function submitForm() { /* Task 7 */ alert('Save not yet implemented'); }
</script>

<?php if ($prefill_client): /* Task 6 prefill placeholder */ ?>
<script>
/* Task 6 will add pre-fill here */
</script>
<?php endif; ?>

</body>
</html>
