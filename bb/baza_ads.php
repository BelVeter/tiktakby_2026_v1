<?php

use bb\tovar;

session_start();

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/tovar.php'); //


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
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>');
}

//-----------proverka paroley



/*
echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Авторизация</title>
</head>
<body>';
*/


//$action='tov_hist';
//$item_inv_n='7099';


//Проверка входящей информации
//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//foreach ($_POST as $key => $value) {
//	echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";



foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}


switch ($action) {

case 'tov_hist':
		//запрос информации о товаре

		$deal_q="SELECT * FROM rent_deals_arch WHERE item_inv_n='$item_inv_n' ORDER BY return_date DESC";
		$result_deal = $mysqli->query($deal_q);
		if (!$result_deal) {die('Сбой при доступе к базе данных: '.$deal_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

		$row_num=$result_deal->num_rows;

		$total=0;

		$tov = tovar::getTovar($item_inv_n);

if ($row_num<1) {
		$output='<div class="item_hist"> В базе история отсутствует. <br /> <input type="button" value="х" onclick="document.getElementById(\'hist_'.$model_id.'\').innerHTML=\'\'; return false;" style="position:absolute; top:5px; left:115px;"/></div>';
	}

if ($row_num>0) {

	$output='
	<div class="item_hist"> <input type="button" value="х" onclick="document.getElementById(\'hist_'.$model_id.'\').innerHTML=\'\'; return false;" style="position:absolute; right:0; top:0;"/>
	<table border="1" cellspacing="0" class="krb_table">
		<tr>
			<th style="width:60px; text-align:center;">с</th>
			<th style="width:60px; text-align:center;">по</th>
			<th style="width:200px;">клиент</th>
			<th style="width:200px;">адрес</th>
			<th style="width:140px;">телефоны</th>
			<th style="width:80px;">действия</th>
		</tr>';

	while ($deal=$result_deal->fetch_assoc()) {

		$cl_q="SELECT * FROM clients WHERE client_id='".$deal['client_id']."'";
		$result_cl = $mysqli->query($cl_q);
		if (!$result_cl) {die('Сбой при доступе к базе данных: '.$cl_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$cl=$result_cl->fetch_assoc();
		$total+=$deal['r_paid'];

	$output.='
		<tr>
			<td>'.date("d.m.y", $deal['start_date']).'</td>
			<td>'.date("d.m.y", $deal['return_date']).'</td>
			<td>'.$cl['family'].' '.$cl['name'].' '.$cl['otch'].'</td>
			<td>'.$cl['str'].', '.$cl['dom'].'-'.$cl['kv'].', '.$cl['city'].'</td>
			<td>'.phone_print($cl['phone_1']).'<br />'.phone_print($cl['phone_2']).' </td>
			<td>пока нет</td>
		</tr>
				';

	}
	$output.='</table>
Итого по товару оплачено: '.number_format($total, 2, ',', ' ').' руб. (цена: '.number_format($tov->buy_price, 2, ',', ' ').' '.$tov->buy_price_cur.')
</div>';
}//end of num if

	$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет

	echo $output;

	break;



	case 'move':

		$query_upd = "UPDATE tovar_rent_items SET item_place='0' WHERE item_id='$item_id'";
		$result_upd = $mysqli->query($query_upd);
		if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


		$output='
		<a href="#" onclick="place_show(\''.$item_id.'\', \''.$item_inv_n.'\', \'on_move\', \''.$move_to.'\'); return false;">в пути</a>

				';

		$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет

		echo $output;

	break;


	case 'accept':

		$query_upd = "UPDATE tovar_rent_items SET item_place='$move_to' WHERE item_id='$item_id'";
		$result_upd = $mysqli->query($query_upd);
		if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


		$output='
		<a href="#" onclick="place_show(\''.$item_id.'\', \''.$item_inv_n.'\', \'to_rent\', \''.$move_to.'\'); return false;">'.of_print($move_to).'</a>

				';

		$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет

		echo $output;

	break;
}//end of switch







function phone_print ($ph) {
	if ($ph=='') {return '';}

	$dl=strlen($ph);

	if ($dl<7) {return $ph;}

	$dl>7 ? $dl_to=$dl-7 : $dl_to=0;
	$ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
	return $ph_out;

}

function get_post($var)
{
	GLOBAL $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}


function good_print($var)
{
	$var=htmlspecialchars((stripslashes($var)), ENT_QUOTES, "UTF-8");
	return $var;
}


function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}


function of_print ($of) {

	switch ($of) {
		case '1':
			$output='Оф1';
			break;

		case '2':
			$output='Оф2';
			break;

		default:
			$output='Нет';
			break;
	}

	return $output;

}


?>
