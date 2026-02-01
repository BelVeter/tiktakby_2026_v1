<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/User.php'); //

echo '

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>BB: Отчетность</title>
<link href="stile.css" rel="stylesheet" type="text/css" />
';

/*
 * разобраться с датой оказания услуг в части выдачи не в день подписания договора (особенно актуально для карнавалов);
 * 
 * 
 * */

//------- proverka paroley
$in_level= array(3,5,7);

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level))) {
	die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	<body>

	<form action="/bb/index.php" method="post">
		Офис:<select name="of_select" id="of_select">
				<option value="0">не выбран</option>
				<option value="1">Литературная</option>
				<option value="2">Ложинская</option>
			</select><br />
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>
	</head>

<body>
');
}

//-----------proverka paroley

set_time_limit(150);

$i_from_date=date("Y-m-d");
$i_to_date=date("Y-m-d");
$acts_filter='all';
$place='all';
//$kassa='nal_cheque';

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";



$i_from_date=strtotime($i_from_date);
$i_to_date=strtotime($i_to_date);


echo '
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>


<form name="srch_form" method="post" id="srch_form" action="doh_rash_book_kr.php">
	За период:
		c <input type="date" name="i_from_date" id="i_from_date" value="'.date("Y-m-d", $i_from_date).'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.date("Y-m-d", $i_to_date).'" />		
	        <select name="acts_filter">
                <option value="all" '.sel_d($acts_filter, 'all').'>показывать все</option>
                <option value="acts_only" '.sel_d($acts_filter, 'acts_only').'>только акты</option>
            </select>
            <select name="place">
                <option value="all" '.sel_d($place, 'all').'>все офисы</option>
                <option value="1" '.sel_d($place, '1').'>Литературная</option>
                <option value="2" '.sel_d($place, '2').'>Ложинская</option>
                <option value="3" '.sel_d($place, '3').'>Победителей</option>
            </select>
            
             <input type="submit" name="action" value="показать" onclick="" /><br />	
		</form>
';



$data=array();

$q=query_rpep('act', $i_from_date, $i_to_date);
//echo $q.'<br /><br />';
$result = $mysqli->query($q);
if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

while ($dl=$result->fetch_assoc()) {
    if ($acts_filter=='all') {
        if ($place!='all' && $place!=$dl['place']) continue;
        $row = new_row($dl);
        $data[] = $row;
    }
    else {
       if ($dl['acc_date']==$dl['start_date']) {
           if ($place!='all' && $place!=$dl['place']) continue;
           $row = new_row($dl);
           $data[] = $row;
       }
    }
}


$q=query_rpep('arch', $i_from_date, $i_to_date);
//echo $q.'<br /><br />';
$result = $mysqli->query($q);
if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

while ($dl=$result->fetch_assoc()) {
    if ($acts_filter=='all') {
        if ($place!='all' && $place!=$dl['place']) continue;
        $row = new_row($dl);
        $data[] = $row;
    }
    else {
        if ($dl['acc_date']==$dl['start_date']) {
            if ($place!='all' && $place!=$dl['place']) continue;
            $row = new_row($dl);
            $data[] = $row;
        }
    }
}


ksort($data);

	echo '
<table border="1" cellspacing="0">
			
<tr>
	<th>Дата оказания услуги (платежа)</th>
	<th>сумма(руб)</th>
	<th>№ чека</th>
	<th>тип оплаты</th>
	<th>офис</th>
	<th>Курьер?</th>
	<th>клиент</th>
	<th>договор</th>
	<th>кто ввел платеж</th>
</tr>			
			
			';
//$of_n='';
foreach ($data as $key=>$value) {
    echo $value;
}

echo '
	
</table>		
		';
	
	
	
//echo $query_ofpay.'<br /><br /><br />';


function new_row ($dl) {
    if ($dl['r_payment_type']==$dl['del_payment_type']) {
        $dl['r_paid']=$dl['r_paid']+$dl['delivery_paid'];
    }
    $id_sub='';
    //$id_sub='<br />('.$dl['sub_deal_id'].')';
    $output= '
<tr>
    <td>'.date("d.m.Y", $dl['acc_date']).$id_sub.'</td>
    <td>'.number_format($dl['r_paid'], 2, ',', ' ').'</td>
    <td>'.($dl['ch_num']=='' ? 'без № чека' : $dl['ch_num']).'</td>
    <td>'.kassa_print($dl['r_payment_type']).'</td>
    <td>'.of_print($dl['place']).'</td>
    <td>'.$dl['delivery_yn'].'</td>
    <td>'.$dl['family'].' '.$dl['name'].' '.$dl['otch'].'</td>
    <td> дог. №'.$dl['deal_id'].' от '.date("d.m.Y", $dl['start_date']).'</td>
    <td>'.\bb\User::GetUserName($dl['cr_who_id']);

    if ($dl['acc_date']==$dl['start_date']) {
        //$dl['two_type']=='first_rent' || $dl['two_type']=='takeaway_plan'
        $output.='
        <form method="post" action="/bb/akt_print.php">
            <input type="hidden" name="payment_id" value="'.$dl['sub_deal_id'].'">
            <input type="submit" value="печать акта">
        </form>
        ';
    }

    $output.='</td>
</tr>
';



    return $output;
}




function get_post($var)
{
    GLOBAL $mysqli;
    return $mysqli->real_escape_string($_POST[$var]);
}

//основной запрос
function query_rpep($period, $from_date, $to_date)
{
    if ($period=='act') {
        $sub_table='rent_sub_deals_act';
        $deal_table='rent_deals_act';
    }
    else {
        $sub_table='rent_sub_deals_arch';
        $deal_table='rent_deals_arch';
    }

    return "
SELECT 

$sub_table.sub_deal_id, $sub_table.r_paid, $sub_table.delivery_paid, $sub_table.r_payment_type,  $sub_table.ch_num, $sub_table.place, $sub_table.deal_id, $sub_table.delivery_yn, $sub_table.acc_date, $sub_table.del_payment_type, $sub_table.cr_who_id,

$deal_table.start_date,

clients.family, clients.name, clients.otch,

two.`type` AS two_type, two.sub_deal_id AS two_sub_id

FROM $sub_table 

LEFT JOIN $deal_table ON $sub_table.deal_id=$deal_table.deal_id
LEFT JOIN clients ON $deal_table.client_id=clients.client_id
LEFT JOIN $sub_table AS two ON $sub_table.link=two.sub_deal_id

WHERE ($sub_table.acc_date BETWEEN '$from_date' AND '$to_date') AND $sub_table.type IN ('payment', 'cl_payment') AND $sub_table.r_payment_type NOT IN ('nal_no_cheque', 'no_payment')

ORDER BY `$sub_table`.`acc_date`
";
}

function sel_d($value, $pattern) {
    if ($value==$pattern) {
        return 'selected="selected"';
    }
    else {
        return '';
    }
}

function kassa_print ($of) {
    switch ($of) {
        case 'nal_cheque':
            return 'Нал';
            break;
        case 'bank':
            return 'Банк';
            break;
        case 'card':
            return 'Карта';
            break;

    }
}

function of_print ($of) {
    switch ($of) {
        case '1':
            return 'Литературная';
            break;
        case '2':
            return 'Ложинская';
            break;
        case '3':
            return 'Победителей';
            break;
        case 'курьер':
            return 'курьер';
            break;
        default:
            return '';

    }
}
?>