<?php
namespace bb;

session_start();

ini_set("display_errors", 1);
error_reporting(E_ALL);

set_time_limit(300);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);



//------- proverka paroley

$in_level = array(0, 5, 7);

isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	</head>
	<body>

	<div class="top_menu">
		<a class="div_item" href="/bb/index.php">Залогиниться</a>
	</div>

	</form></body></html>');
}

//-----------proverka paroley

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/DealRow.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Payment.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Payment.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/User.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Office.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Kassa.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Collateral.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Permission.php');

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css?new" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
' . Base::getBarCodeReaderScript() . '
<title>Сделки. NEW.</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400&display=swap" rel="stylesheet">
</head>
<body>
';

$user = new User();
$user->id_user = $_SESSION['user_id'];
$user->level = $_SESSION['level'];
$user->user_name = $_SESSION['user_fio'];
User::$current_user = $user;

$ch_num_id = '';
$sub_info_update = '';
$ch_num_update = '';
$ch_num_value = '';
$sub_deal_id = 0;
$sub_info_id = 0;
$sub_info_value = '';

$iDate = false;
$i_date = date("Y-m-d");
//echo $i_date;
$place = $_SESSION['office'];
$op_type = 'all';
$payment_type = 'all';


foreach ($_POST as $key => $value) {
    $$key = get_post($key);
}

//for payment bank sales count
$iDate = new \DateTime($i_date);


if (isset($_POST['action']) && $_POST['action'] == 'сохранить остаток') {
    echo Kassa::SaveKassa();
}



if ($ch_num_update == 'yes' || $sub_info_update == 'yes') {

    $db = Db::getInstance();
    $mysqli = $db->getConnection();

    $sub_id = 0;
    if ($ch_num_id > 0)
        $sub_id = $ch_num_id;
    if ($sub_info_id > 0)
        $sub_id = $sub_info_id;

    $q_arch_act = "SELECT * FROM rent_sub_deals_act WHERE sub_deal_id='$sub_id' LIMIT 1";
    $result = $mysqli->query($q_arch_act);
    if (!$result) {
        die('Сбой при доступе к базе данных: ' . $q_arch_act . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $aa_rows = $result->num_rows;

    if ($aa_rows >= 1) {
        $srch_table = 'rent_sub_deals_act';
    } else {
        $srch_table = 'rent_sub_deals_arch';
    }

    if ($ch_num_update == 'yes' && $ch_num_id > 0) {
        $query_dl_upd = "UPDATE $srch_table SET ch_num='$ch_num_value' WHERE sub_deal_id='$ch_num_id'";
        //echo $query_dl_upd;
        if (!$mysqli->query($query_dl_upd)) {
            echo 'Сбой при доступе к базе данных: ' . $q_arch_act . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
        }
    }
    if ($sub_info_update == 'yes' && $sub_info_id > 0) {
        $query_dl_upd = "UPDATE $srch_table SET info='$sub_info_value' WHERE sub_deal_id='$sub_info_id'";
        //echo $query_dl_upd;
        if (!$mysqli->query($query_dl_upd)) {
            echo 'Сбой при доступе к базе данных: ' . $q_arch_act . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
        }
    }

}


use bb\classes\Collateral;

?>

<script language="javascript">

    history.pushState(null, null, location.href);
    window.onpopstate = function (event) {
        history.go(1);
    };

    // Kebab menu toggle
    function toggleKebab(uid) {
        var menu = document.querySelector('#' + uid + ' .km-menu');
        if (!menu) return;
        var isOpen = menu.classList.contains('open');
        // close all menus
        document.querySelectorAll('.km-menu.open').forEach(function (m) { m.classList.remove('open'); });
        if (!isOpen) menu.classList.add('open');
    }
    // Close kebab on outside click
    document.addEventListener('click', functio n(e) {
        if(!e.target.closest('.km-wrap')) {
        document.querySelectorAll('.km-menu.open').forEach(functi on(m) { m.classList.remove('open'); });
    }
    });

    function ch_num_close(chnid) {

        document.getElementById('ch_num_update').value = "no";
        document.getElementById('ch_num_id').value = "";
        document.getElementById('ch_num_value').value = "";

        var divsToHide = document.getElementsByClassName("ch_div_st");

        for (var i = 0; i < divsToHide.length; i++) {
            divsToHide[i].style.display = "none";
        }

    }


    function ch_num_show(chnid) {
        document.getElementById('ch_div_' + chnid).style.display = "block";
        document.getElementById('ch_num_update').value = "yes";
        document.getElementById('ch_num_id').value = document.getElementById('ch_num_id_' + chnid).value;;

    }

    function ch_num_save(chnid) {
        document.getElementById('ch_num_value').value = document.getElementById('ch_num_new_' + chnid).value;
        document.getElementById('srch_form').submit();
    }



    function sub_show(subid) {
        sub_info_close(subid);

        document.getElementById('sub_info_div_' + subid).style.display = "block";
        document.getElementById('sub_info_update').value = "yes";
        document.getElementById('sub_info_id').value = subid;
    }

    function sub_info_close(subid) {
        document.getElementById('sub_info_update').value = "no";
        document.getElementById('sub_info_id').value = "";
        document.getElementById('sub_info_value').value = "";

        var divsToHide = document.getElementsByClassName("sub_info");

        for (var i = 0; i < divsToHide.length; i++) {
            divsToHide[i].style.display = "none";
        }

    }

    function sub_info_save(subid) {
        document.getElementById('sub_info_value').value = document.getElementById('sub_info_new_' + subid).value;
        document.getElementById('srch_form').submit();
    }


</script>


<style>
    /* === RDA PAGE REDESIGN === */
    body {
        background: #f7f8fa;
        font-family: 'Nunito', sans-serif;
        color: #333;
    }

    .zv-row {
        display: flex;
        flex-flow: column nowrap;
        gap: 10px;
    }

    .alert-danger {
        text-align: center !important;
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
        font-size: 20px;
    }

    .btn-danger {
        padding: 1px 10px;
        text-decoration: none;
        margin-left: 30px;
        cursor: pointer;
        font-size: 1.25rem;
        line-height: 1.5;
        border-radius: 0.3rem;
        color: #fff;
        background-color: #dc3545;
        border-color: #dc3545;
        display: inline-block;
        font-weight: 400;
        text-align: center;
        vertical-align: middle;
        border: 1px solid transparent;
    }

    .btn-pale-blue {
        padding: 5px 14px;
        text-decoration: none;
        margin-left: 0;
        cursor: pointer;
        font-size: 0.85rem;
        line-height: normal;
        border-radius: 6px;
        color: #3a7bd5;
        background-color: #e8f0fe;
        border: 1px solid #c5d8fb;
        display: inline-block;
        font-weight: 600;
        font-family: 'Nunito', sans-serif;
        transition: background 0.15s, color 0.15s;
    }

    .btn-pale-blue:hover {
        background-color: #d0e3fc;
        color: #1a56c4;
    }

    /* === TOOLBAR === */
    .rda-toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        padding: 10px 0 8px 0;
        margin-bottom: 4px;
    }

    .rda-toolbar label {
        font-size: 0.85rem;
        color: #666;
        font-weight: 600;
    }

    .rda-toolbar input[type=date] {
        border: 1px solid #dde3f0;
        border-radius: 6px;
        padding: 5px 10px;
        font-size: 0.85rem;
        font-family: 'Nunito', sans-serif;
        color: #333;
        background: #fff;
        outline: none;
        cursor: pointer;
    }

    .rda-toolbar input[type=date]:focus {
        border-color: #4a90d9;
    }

    .rda-toolbar input[type=submit],
    .rda-toolbar button[type=submit] {
        padding: 5px 16px;
        border-radius: 6px;
        border: none;
        background: #4a90d9;
        color: #fff;
        font-size: 0.85rem;
        font-family: 'Nunito', sans-serif;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
    }

    .rda-toolbar input[type=submit]:hover,
    .rda-toolbar button[type=submit]:hover {
        background: #2b72c8;
    }

    /* === KASSA SUMMARY TABLE === */
    .kassa-table {
        border-collapse: collapse;
        margin: 10px 0 12px 0;
        font-size: 0.82rem;
        font-family: inherit;
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 5px rgba(0, 0, 0, 0.07);
    }

    .kassa-table thead tr {
        background: #f3f6fa;
    }

    .kassa-table th {
        padding: 7px 14px;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7a99;
        border-bottom: 1px solid #e8ecf2;
        text-align: right;
        white-space: nowrap;
    }

    .kassa-table th:first-child {
        text-align: left;
    }

    .kassa-table td {
        padding: 6px 14px;
        border-bottom: 1px solid #f0f3f8;
        vertical-align: middle;
    }

    .kassa-table tbody tr:last-child td {
        border-bottom: none;
    }

    .kassa-label {
        font-size: 0.8rem;
        color: #555;
        white-space: nowrap;
    }

    .kassa-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .kassa-muted {
        color: #9aa3b5;
    }

    .kassa-bold {
        font-weight: 700;
        color: #1e293b;
    }

    .kassa-courier th,
    .kassa-courier {
        color: #8b5cf6 !important;
    }

    .kassa-itogo {
        font-weight: 700;
        color: #2b72c8;
    }

    /* === MAIN TABLE === */
    .rda-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 6px rgba(0, 0, 0, 0.06);
        margin-top: 14px;
        clear: both;
        font-size: 0.83rem;
    }

    .rda-table thead th {
        background: #fff;
        color: #888;
        font-weight: 700;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 10px 10px 8px;
        border-bottom: 2px solid #eef0f5;
        white-space: nowrap;
        vertical-align: bottom;
    }

    .rda-table thead th select {
        font-family: 'Nunito', sans-serif;
        font-size: 0.78rem;
        color: #555;
        border: 1px solid #dde3f0;
        border-radius: 5px;
        padding: 2px 4px;
        background: #f7f8fa;
        cursor: pointer;
        margin-top: 3px;
    }

    .rda-table tbody tr {
        border-bottom: 1px solid #f0f2f7;
        transition: background 0.12s;
    }

    .rda-table tbody tr:hover {
        background: #f4f7ff !important;
    }

    .rda-table tbody td {
        padding: 10px 10px;
        vertical-align: middle;
        font-size: 0.83rem;
        color: #333;
        border-bottom: 1px solid #f0f2f7;
    }

    /* Row highlight overrides (keep existing color logic) */
    .rda-table tbody tr[style*="background-color:#80C4F0"] td,
    .rda-table tbody tr[style*="background-color:#80C4F0"]:hover td {
        background: #d0ecff !important;
    }

    .rda-table tbody tr[style*="background-color:#C4F4F2"] td,
    .rda-table tbody tr[style*="background-color:#C4F4F2"]:hover td {
        background: #dff7f5 !important;
    }

    .rda-table tbody tr[style*="background-color:#FF0"] td,
    .rda-table tbody tr[style*="background-color:#FF0"]:hover td {
        background: #fffce0 !important;
    }

    .rda-table tbody tr[style*="background-color:#F5C138"] td,
    .rda-table tbody tr[style*="background-color:#F5C138"]:hover td {
        background: #fef6e0 !important;
    }

    /* Override inline style from SubColorRowStyle */
    .rda-table tbody tr[style] {
        background: transparent;
    }

    /* Deal date / acc date cell */
    .rda-date-cell {
        color: #777;
        font-size: 0.78rem;
        white-space: nowrap;
    }

    .rda-date-cell .deal-num {
        color: #aaa;
        font-size: 0.72rem;
    }

    /* Operation badge */
    .op-badge {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .op-badge.op-vydacha {
        background: #e3f7ed;
        color: #1a874a;
    }

    .op-badge.op-prodlenie {
        background: #e8f0fe;
        color: #2b72c8;
    }

    .op-badge.op-bron {
        background: #fff8e1;
        color: #b07c00;
    }

    .op-badge.op-vozvrat {
        background: #fdecea;
        color: #c0392b;
    }

    .op-badge.op-other {
        background: #f0f2f7;
        color: #555;
    }

    /* Payment highlight */
    .pay-red {
        color: #e74c3c;
        font-weight: 700;
    }

    .deliv_num {
        color: #7c8dc0;
        font-size: 0.75rem;
        display: block;
    }

    /* Client name emphasis */
    .rda-client strong {
        font-weight: 700;
        color: #222;
    }

    .rda-client .phones {
        color: #888;
        font-size: 0.76rem;
    }

    /* === ICON ACTION BUTTONS === */
    .rda-actions {
        display: flex;
        gap: 4px;
        justify-content: center;
        align-items: center;
    }

    .rda-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 7px;
        border: 1px solid #c5d8fb;
        background: #eef3ff;
        color: #3a7bd5;
        cursor: pointer;
        transition: all 0.15s ease;
        padding: 0;
        position: relative;
    }

    .rda-icon-btn svg {
        width: 15px;
        height: 15px;
        pointer-events: none;
    }

    .rda-icon-btn:hover {
        background: #3a7bd5;
        color: #fff;
        border-color: #3a7bd5;
        box-shadow: 0 2px 8px rgba(58, 123, 213, 0.25);
        transform: translateY(-1px);
    }

    .rda-icon-btn--archive {
        border-color: #d5c8f0;
        background: #f0ecff;
        color: #7c5cbf;
    }

    .rda-icon-btn--archive:hover {
        background: #7c5cbf;
        color: #fff;
        border-color: #7c5cbf;
        box-shadow: 0 2px 8px rgba(124, 92, 191, 0.25);
    }

    /* === OFFICE DOT INDICATOR === */
    .rda-office-dot {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        cursor: default;
    }

    .rda-office-dot--1 {
        background: #27ae60;
        box-shadow: 0 0 0 2px #d5f5e3;
    }

    .rda-office-dot--2 {
        background: #f0b429;
        box-shadow: 0 0 0 2px #fef9c3;
    }
</style>


<?php

$dls = DealRow::GetLines($i_date, $place, $op_type, $payment_type);
$kassa = new Kassa();
$kassa->LoadKassa($i_date, $place, DealRow::$kassa_r_total);


echo '
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> ' . $_SESSION['user_fio'] . '</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: ' . $_SESSION['office'] . '</form> </div>

';
include_once($_SERVER['DOCUMENT_ROOT'] . '/bb/bb_nav.php');
echo '
<div class="row zv-row">
    <div class="col alert-danger h2 text-center" id="zv_div"></div>
</div>
';
require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show2.php');

//print_r(DealRow::$kassa_r_total);
//echo '<br><br>';

//print_r(DealRow::$kassa_d_total);

?>

<div class="rda-toolbar">
    <form name="srch_form" method="post" id="srch_form" action="rda.php"
        style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <label for="i_date">Операции за дату:</label>
        <input type="date" name="i_date" id="i_date" value="<?= $i_date ?>" />
        <input form="srch_form" type="hidden" name="ch_num_update" id="ch_num_update" value="no" />
        <input form="srch_form" type="hidden" name="ch_num_id" id="ch_num_id" value="" />
        <input form="srch_form" type="hidden" name="ch_num_value" id="ch_num_value" value="" />
        <input form="srch_form" type="hidden" name="sub_info_update" id="sub_info_update" value="no" />
        <input form="srch_form" type="hidden" name="sub_info_id" id="sub_info_id" value="" />
        <input form="srch_form" type="hidden" name="sub_info_value" id="sub_info_value" value="" />
        <input type="submit" name="action" value="Показать" />
    </form>
    <form action="/bb/doh-rash.php" method="post" style="margin:0;">
        <input type="hidden" name="i_from_date" value="<?= $i_date ?>">
        <input type="hidden" name="i_to_date" value="<?= $i_date ?>">
        <input type="hidden" name="item_place" value="<?= $place ?>">
        <input type="submit" value="Расходы" class="btn-pale-blue">
    </form>
</div>

<?= $kassa->PrintKassaTable() ?>

<!--
    <table border="1" cellspacing="0" style="background-color: lightblue; position: absolute; left: 400px;">
        <tr>
            <th>Сумма залогов в кассе</th>
        </tr>
        <tr>
            <?php

            $col_amount = Collateral::getOstatok($place);
            Kassa::$_total_ostatok += $col_amount;
            $total_money = Kassa::$_total_ostatok;

            echo '
            <td>' . number_format($col_amount, 2, ',', ' ') . '</td>
            ';
            ?>
        </tr>
    </table> -->

<table class="rda-table">
    <thead>
        <tr>
            <th style="width:70px;">Дата</th>
            <th style="width:90px;">
                Операция<br>
                <select name="op_type" id="op_type" form="srch_form"
                    onchange="document.getElementById('srch_form').submit();">
                    <option value="all" <?= DealRow::sel_d($op_type, 'all') ?>>все</option>
                    <option value="first_rent" <?= DealRow::sel_d($op_type, 'first_rent') ?>>Выдачи</option>
                    <option value="extention" <?= DealRow::sel_d($op_type, 'extention') ?>>Продления</option>
                    <option value="takeaway_plan" <?= DealRow::sel_d($op_type, 'takeaway_plan') ?>>Бронь</option>
                    <option value="close" <?= DealRow::sel_d($op_type, 'close') ?>>Возвраты</option>
                </select>
            </th>
            <th style="width:240px; position:relative;">
                Товар
                <span
                    style="position:absolute;top:4px;right:8px;font-weight:normal;font-size:0.72rem;color:#aaa;"><?php echo number_format($total_money, 2, ',', ' ') ?></span>
            </th>
            <th style="width:100px;"><span title="Период по договору">Период</span></th>
            <th style="width:55px;">К опл.</th>
            <th style="width:100px;">
                <span title="Оплачено, касса, №чека">Оплачено</span><br>
                <select name="payment_type" id="rent_payment_type" form="srch_form"
                    onchange="document.getElementById('srch_form').submit();">
                    <option value="all" <?= DealRow::sel_d($payment_type, 'all') ?>>все</option>
                    <option value="nal_no_cheque" <?= DealRow::sel_d($payment_type, 'nal_no_cheque') ?>>Касса №2</option>
                    <option value="nal_cheque" <?= DealRow::sel_d($payment_type, 'nal_cheque') ?>>Касса №1</option>
                    <option value="card" <?= DealRow::sel_d($payment_type, 'card') ?>>Карточка</option>
                    <option value="bank" <?= DealRow::sel_d($payment_type, 'bank') ?>>Банк</option>
                </select>
            </th>
            <th style="width:40px;" title="Офис, где выдан">
                <span title="Зел. = Офис 1, Жел. = Офис 2">&#9679;</span><br>
                <select name="place" id="place_select" form="srch_form" style="width:36px;font-size:0.7rem;"
                    onchange="document.getElementById('srch_form').submit();">
                    <?= Office::OptionsList($place, $user) ?>
                </select>
            </th>
            <th style="width:260px;">Клиент / Адрес</th>
            <th>Доп. инфо</th>
            <th style="width:44px;"></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($dls as $dl) {
            // Map type to badge class
            $opClass = 'op-other';
            switch ($dl->type_sub_deal) {
                case 'first_rent':
                    $opClass = 'op-vydacha';
                    break;
                case 'extention':
                    $opClass = 'op-prodlenie';
                    break;
                case 'takeaway_plan':
                    $opClass = 'op-bron';
                    break;
                case 'close':
                case 'cur_return':
                    $opClass = 'op-vozvrat';
                    break;
            }
            $dot_office = intval($dl->first_rent_place);
            $dot_class = ($dot_office === 2) ? 'rda-office-dot--2' : 'rda-office-dot--1';
            $dot_title = 'Выдан на Офис ' . $dot_office;
            $dot_html = '<span class="rda-office-dot ' . $dot_class . '" title="' . $dot_title . '"></span>';
            echo '
    <tr ' . $dl->SubColorRowStyle() . '>
        <td class="rda-date-cell">
            ' . date("d.m.y", $dl->acc_date_sub_deal) . '<br>
            <span style="color:#aaa;">' . date("H:i", $dl->cr_time_sub) . '</span><br>
            <span class="deal-num">д№' . $dl->id_deal . '</span>
        </td>
        <td><span class="op-badge ' . $opClass . '">' . $dl->operation_print() . '</span></td>
        <td style="position:relative;"><strong>' . $dl->inv_n_print() . '</strong>' . $dl->FirstPlacePic() . '<br><span style="color:#555;">' . $dl->cat_dog_name . ' ' . $dl->model_name . ' ' . $dl->producer . '</span></td>
        <td style="white-space:nowrap;">' . date("d.m.y", $dl->from_deal) . '&nbsp;–&nbsp;' . date("d.m.y", $dl->to_deal) . $dl->LastExtensionDatePrint() . '</td>
        <td style="text-align:right;">' . number_format($dl->r_to_pay_sub, 2, ',', ' ') . ($dl->delivery_to_pay_sub > 0 ? '<br><span class="deliv_num">' . number_format($dl->delivery_to_pay_sub, 2, ',', ' ') . '</span>' : '') . '</td>
        <td><span style="font-size:0.75rem;color:#999;">' . User::GetUserName($dl->acc_person_id) . '</span><br>' . $dl->PrintPayment() . '</td>
        <td style="text-align:center;">' . $dot_html . '</td>
        <td class="rda-client">' . $dl->ClientPrint() . '</td>
        <td>' . User::GetUserName($dl->cr_who_sub_deal) . '<br>' . $dl->PrintSubInfo() . $dl->DeveloperInfo() . '</td>
        <td style="text-align:center;">' . $dl->ActionPrint() . '</td>
    </tr>
    ';

        }
        ?>
    </tbody>
</table>
<?php
function get_post($var)
{
    $db = Db::getInstance();
    $mysqli = $db->getConnection();
    return $mysqli->real_escape_string($_POST[$var]);
}


?>