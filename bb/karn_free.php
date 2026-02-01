<?php

use bb\Base;

session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
$mysqli = \bb\Db::getInstance()->getConnection();

//------- proverka paroley
$in_level= array(0,5,7);
isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level))) {
	die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	</head>
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
	</form></body></html>');
}

//-----------proverka paroley

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<style>

</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
'.Base::getBarCodeReaderScript().'
</head>
<title>Карнавал. Свободные по дате.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/kb_arch.php">Удаленные брони</a>
	<a class="div_item" href="/bb/kb_lines.php"><strong>Карнавальная таблица</strong></a>
	<a class="div_item" href="/bb/rda.php">Все сделки</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
</div><br />

';

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$x_date=date("Y-m-d");
$x_date_next='';
$sex='all';
$srch1='';
$return_limit=0;
$out_limit=0;
$model_prev=0;


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}


$x_date=strtotime($x_date);
$return_limit=$x_date+13*60*60;//если возврат после 13 => выдача в 17 - на этот день позже. не должно быть
$out_limit=$x_date+16*60*60;//если есть выдача до 16, то на утро (12) уже никто не успеет взять. не должно быть
$x_date_next=$x_date+24*60*60;//след день для сквоздной брони


$srch1=" WHERE tovar_rent.tovar_rent_cat_id='2'";

if ($sex!='all') {
	$srch1.=" AND tovar_rent_items.sex='$sex'";
}









echo '
	<form name="day_srch" action="karn_free.php" method="post" id="day_srch" value="'.date("Y-m-d", $x_date).'" />

<!--	<input type="number" step="1" min="0" max="31" id="free_day" style="width:35px; height:15px; position:absolute; top:14px; left:10px;" />
	<select name="" id="free_y_m" style="position:absolute; top:14px; left:55px; height:21px; width:90px;">
		'.m_plus(0).m_plus(1).m_plus(2).'
    </select>
-->

			<br /><br />
			<input type="date" name="x_date" value="'.date("Y-m-d", $x_date).'" />
	Пол:<select name="sex" id="item_sex">
			<option value="all" '.sel_d($sex, 'all').'>все</option>
			<option value="m" '.sel_d($sex, 'm').'>для мальчиков</option>
			<option value="f" '.sel_d($sex, 'f').'>для девочек</option>
		    <option value="u" '.sel_d($sex, 'u').'>унисекс</option>
		</select>

		<input name="action" type="submit" value="найти" />
	</form>	';



$query_item="SELECT * FROM tovar_rent_items LEFT JOIN tovar_rent ON (tovar_rent_items.model_id = tovar_rent.tovar_rent_id)$srch1 ORDER BY tovar_rent.model";
$result_item = $mysqli->query($query_item);
if (!$result_item) {die('Сбой при доступе к базе данных: '.$query_item.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$item_rows=$result_item->num_rows;

$free_it_num=0;
while ($item=$result_item->fetch_assoc()) {

	$query_kb = "SELECT * FROM karn_brons WHERE inv_n='".$item['item_inv_n']."' AND ((t_to>=$return_limit AND t_to<=$x_date_next) OR (t_from>=$x_date AND t_from<=$out_limit) OR (t_from<=$x_date AND t_to>=$x_date_next) ) AND `status`!='in_process' ORDER BY t_to";
	$result_kb = $mysqli->query($query_kb);
	if (!$result_kb) {die('Сбой при доступе к базе данных: '.$query_kb.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$kb_rows=$result_kb->num_rows;

	if ($kb_rows<1) {

		if ($item['model_id']==$model_prev) {continue;}//не выводим повторно товары одной и той же модели

		echo ' '.inv_print($item['item_inv_n']).' '.$item['model'].' ('.$item['item_size'].' / '.$item['item_rost1'].'-'.$item['item_rost2'].'см.)<br />';
		$free_it_num+=1;
		$model_prev=$item['model_id'];
	}
}//end of item while
echo '<br /><br /><br />Служебный счетчик:'.$item_rows.'/'.$free_it_num.'<br />';







function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}

function rus_day ($day) {
	switch ($day) {
		case '1':
			return 'Понедельник';
			break;

		case '2':
			return 'Вторник';
			break;

		case '3':
			return 'Среда';
			break;

		case '4':
			return 'Четверг';
			break;

		case '5':
			return 'Пятница';
			break;

		case '6':
			return 'Суббота';
			break;

		case '0':
			return 'Воскресенье';
			break;
		default:
			return 'День не определен';
			break;
	}
}


function inv_print ($inv_n) {

	$output=substr($inv_n, 0, 3).'-'.substr($inv_n, 3);

	return $output;

}

function good_print($var) {
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}

function phone_print ($ph) {
	if ($ph=='') {return '';}

	$dl=strlen($ph);

	if ($dl<7) {return $ph;}

	$dl>7 ? $dl_to=$dl-7 : $dl_to=0;
	$ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
	return $ph_out;

}

function phone_to_n ($ph) {
	$ph=preg_replace("|[^0-9]|i", "", $ph);
	return $ph;
}

function user_name ($id) {
	switch ($id) {
		case '1':
			return 'тестовый пользователь';
			break;

		case '2':
			return 'Кристина';
			break;

		case '3':
			return 'Дима';
			break;

		case '4':
			return 'Андрей';
			break;

		case '5':
			return 'Аня';
			break;

		case '6':
			return 'Денис';
			break;

		case '9':
			return 'Света
			';
			break;
		default:
			return 'ХЗ';
			break;
	}
}

function stat_print ($stat) {
	switch ($stat) {
		case 'new':
			return 'не проверено';
			break;

		case 'ok':
			return 'подтверждено';
			break;

		case 'in_process':
			return 'временная бронь';
			break;

		default:
			return 'ХЗ';
			break;
	}
}


function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}

function tonum ($value) {

	$output=floatval(str_replace(',','.',$value));
	return $output;

}

function m_plus ($m_plus) {
	$cur_y=date("Y");
	$cur_m=date("m");

	$new_m=$cur_m+$m_plus;
	if ($new_m>12) {
		$new_y=$cur_y+1;
		$new_m=$new_m-12;
	}
	else {
		$new_y=$cur_y;
	}

	if ($new_m<10) {
		$new_m='0'.$new_m;
	}

	$output='<option value="'.$new_y.'-'.$new_m.'">'.rus_month1($new_m*1).'</option>';

	return $output;

}//end of function


function rus_month1 ($month) {
	$month=$month*1;

	switch ($month) {
		case '1':
			return 'января';
			break;

		case '2':
			return 'февраля';
			break;

		case '3':
			return 'марта';
			break;

		case '4':
			return 'апреля';
			break;

		case '5':
			return 'мая';
			break;

		case '6':
			return 'июня';
			break;

		case '7':
			return 'июля';
			break;

		case '8':
			return 'августа';
			break;


		case '9':
			return 'сентября';
			break;

		case '10':
			return 'октября';
			break;

		case '11':
			return 'ноября';
			break;

		case '12':
			return 'декабря';
			break;

		default:
			return 'Месяц не определен';
			break;
	}
}

?>
