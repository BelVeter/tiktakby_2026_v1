<?php

use bb\Base;
use bb\models\Office;
use bb\models\User;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/bron.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); //

set_time_limit(30);
//------- proverka paroley
$in_level= array(0,5,7);

$mysqli = \bb\Db::getInstance()->getConnection();


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
<title>Обработка.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/rent_orders.php">Брони</a>
</div><br />
';
require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам


?>

<script language="javascript">

history.pushState(null, null, location.href);
window.onpopstate = function(event) {
    history.go(1);
};

function pic_size(id) {

	if (document.getElementById('item_pic_'+id).style.width=="80px") {
		document.getElementById('item_pic_'+id).style.width="250px";
		document.getElementById('item_pic_'+id).style.height="250px"
	}
	else {
		document.getElementById('item_pic_'+id).style.width="80px";
		document.getElementById('item_pic_'+id).style.height="80px";
	}
}

function ch_show (id) {
	document.getElementById('info_'+id).style.display="inline-block";
	document.getElementById('cans_t_but_'+id).style.display="inline-block";
	document.getElementById('save_t_but_'+id).style.display="inline-block";
}

function ch_cans (id) {
	document.getElementById('info_'+id).style.display="none";
	document.getElementById('cans_t_but_'+id).style.display="none";
	document.getElementById('save_t_but_'+id).style.display="none";
}

function ch_save (id) {
	document.getElementById('save_t_but_'+id).value="сохранить";
	document.getElementById('save_t_but_'+id).style.display="none";
	document.getElementById('cans_t_but_'+id).style.display="none";
	return true;

}

</script>

<?php

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";


$type2='stirka';
$office=Office::getCurrentOffice()->getNumber();


foreach ($_POST as $key => $value) {
		$$key = get_post($key);
	}

// создаем перечень пользователей
	$rd_lp = "SELECT * FROM logpass";
	$result_lp = $mysqli->query($rd_lp);
	if (!$result_lp) {die('Сбой при доступе к базе данных: '.$rd_lp.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

	$lp_list[0]='';
	while ($lp_l=$result_lp->fetch_assoc()) {
		$lp_list[$lp_l['logpass_id']]=$lp_l['lp_fio'];
	}

// создаем перечень офисов
	$rd_of = "SELECT * FROM offices";
	$result_of = $mysqli->query($rd_of);
	if (!$result_of) {die('Сбой при доступе к базе данных: '.$rd_of.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

	$off_pic[0]='';
	while ($t_of=$result_of->fetch_assoc()) {
		$off_pic[$t_of['number']]=$t_of['pic_addr'];
	}


if (isset($_POST['action'])) {

	switch ($action) {

		case 'сохранить':
			$bron = new \bb\classes\bron();
			$bron->br_load($order_id);

			$bron->info=$info;

			$bron->update();

			unset($bron);

		break;

		case 'исполнено':
			$bron = new \bb\classes\bron();
			$bron->br_load($order_id);
			$bron->item_load();
			$bron->info.='<br><strong>Обработка осуществлена '.User::getCurrentUser()->user_name.'</strong>';
			$bron->update();

			$bron->arch_copy();

			$office=$bron->item_place;

			$bron->del_br();

			unset($bron);
		break;
	}
}

//for rows count
$iii=0;

//основной запрос
$query_or = "SELECT * FROM rent_orders WHERE type2='stirka' ORDER BY cr_time";
$result_or = $mysqli->query($query_or);
if (!$result_or) {die('Сбой при доступе к базе данных: '.$query_or.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
$type2_num=$result_or->num_rows;

//для расчета необработанных
	$query_or_new = "SELECT * FROM rent_orders LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n WHERE (rent_orders.type2='bron' OR rent_orders.type2='deliv') AND rent_orders.appr_id<1 AND tovar_rent_items.item_place='$office'";
		if ($office=='all') {
			$query_or_new = "SELECT * FROM rent_orders WHERE type2='bron' AND appr_id<1";
		}
	$result_or_new = $mysqli->query($query_or_new);
	if (!$result_or_new) {die('Сбой при доступе к базе данных: '.$query_or_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	$br_new=$result_or_new->num_rows;


//фильтры по офисам

$off_filter='<select name="office" id="office_select" form="br_filter" onchange="document.getElementById(\'br_filter\').submit();" style="width:80px;">
				<option value="all" '.sel_d($office, 'all').'>все офисы</option>
				';

$q_of = "SELECT * FROM offices WHERE `active`='1' AND `type`='office'";
$q_of = "SELECT * FROM offices WHERE `active`='1' AND `type`='office'";
$result_of = $mysqli->query($q_of);
if (!$result_of) {die('Сбой при доступе к базе данных: '.$q_of.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

while ($offs = $result_of->fetch_assoc()) {
	$off_filter.='<option value="'.$offs['number'].'" '.sel_d($office, $offs['number']).'>'.$offs['name'].'</option>';
}

$off_filter.='</select>';



echo '

<form name="br_filter" id="br_filter" method="post" action="obrabotka.php" > <!-- для работы фильтра по офисам -->

</form>


<table border="1" cellspacing="0">
  <tr style="background-color:#afcb82;">
      <th style="width:81px; text-align:center;">Фото товара</th>
	  <th style="width:350px; text-align:center;">Товар'.$off_filter.'</th>
      <th style="width:81px; text-align:center;">Срок</th>
	  <th style="width:350px; text-align:center;">комментарий исполнителя</th>
      <th style="width:90px; text-align:center;">принял</th>
      <th style="text-align:center;">действия</th>
  </tr>';

$to_deliver_rows_1='';
$to_deliver_rows_2='';
$other_rows='';
$bron_rows='';

$today=getdate(time());
$today_date=mktime(0, 0, 0, $today['mon'], ($today['mday']), $today['year']);
$tomorrow_date=mktime(0, 0, 0, $today['mon'], ($today['mday']+1), $today['year']);


while ($ord = $result_or->fetch_assoc()) {
	$br_line = new \bb\classes\bron();
	$br_line->br_line($ord);
	$br_line->web_load();
	$del_style='';
	$to_ready='';

	//удаляем стирку по уже выданным товарам, либо по товарам без инв. номера ==> глюки
	if (($br_line->item_status=='rented_out' || $br_line->inv_n<1 || substr($br_line->inv_n, 0, 3)=='702' || substr($br_line->inv_n, 0, 3)=='761' || substr($br_line->inv_n, 0, 3)=='749') && $br_line->type2=='stirka') {
		$br_line->del_br();
		continue;
	}

	if ($office!='all' && $office!=$br_line->item_place) {//если не нужно показывать все, либо если не совпадает с фильтром по офисам, то пропускаем печать данных
		continue;
	}

	//раскрашиваем строки к доставке
	if ($br_line->item_status=='to_deliver') {

		$subdl_q="SELECT * FROM rent_sub_deals_act WHERE deal_id='$br_line->active_deal_id' AND `status`='for_cur'";
		$result_subdl = $mysqli->query($subdl_q);
		if (!$result_subdl) {die('Сбой при доступе к базе данных: '.$subdl_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		$subdl = $result_subdl->fetch_assoc();

		if ($subdl['from']==$today_date || $subdl['from']<$today_date) {
			$del_style='style="background-color:#82b7f3;"';
			$to_ready='Сегодня до 12:00';
		}
		if ($subdl['from']>$today_date) {
			$del_style='style="background-color:#bad7f8;"';
			$to_ready='Завтра до 12:00';
		}

	}
	elseif ($br_line->item_status=='bron') {
		$del_style='style="background-color:#f5d9f3;"';
	}


	$t_row= '
	<tr '.$del_style.'>
		<td><img src="'.$br_line->small_pic.'" style="width:80px; heigth:80px;" id="item_pic_'.$br_line->order_id.'" onclick="pic_size(\''.$br_line->order_id.'\')" /></td>
		<td>'.$br_line->cat_dog_name.' '.$br_line->producer.': '.$br_line->model.'. Цвет: "'.$br_line->br_color.'"<br /><strong>№'.$br_line->inv_n.'</strong>['.$br_line->item_place.']</td>
		<td>'.($br_line->validity>0 ? date("d.m.y", $br_line->validity).'<br>'.date("H:i", $br_line->validity) : $to_ready).'</td>
		<td> <a href="#" onclick="ch_show(\''.$br_line->order_id.'\'); return false;" id="ch_a_show_'.$br_line->order_id.'">
					'.($br_line->info=='' ? '---' : $br_line->info).' </a>
			<textarea rows="3" cols="32" style="display:none;" name="info" id="info_'.$br_line->order_id.'" form="order_'.$br_line->order_id.'">'.good_print($br_line->info).'</textarea><br />
			<input type="button" id="cans_t_but_'.$br_line->order_id.'" style="background-image:url(/bb/cans.png); width:33px; height:33px; float:right; display:none;" value="" onclick="ch_cans(\''.$br_line->order_id.'\');" />
			<input type="submit" name="action" id="save_t_but_'.$br_line->order_id.'" style="background-image:url(/bb/save.png); width:33px; height:33px; float:right; display:none;" value="" form="order_'.$br_line->order_id.'" onclick="return ch_save(\''.$br_line->order_id.'\');" />
			</td>
    	<td>'.$lp_list[$br_line->cr_who_id].'</td>
		<td>

			<input type="submit" name="action" id="save_but_'.$br_line->order_id.'" value="исполнено" form="order_'.$br_line->order_id.'" style="height:50px;" />

			<form name="order_'.$br_line->order_id.'" id="order_'.$br_line->order_id.'" action="obrabotka.php" method="post" >
				<input type="hidden" name="user_id" id="user_id_'.$br_line->order_id.'" value="'.$_SESSION['user_id'].'">
				<input type="hidden" name="order_id" id="order_id_'.$br_line->order_id.'" value="'.$br_line->order_id.'">
			</form>

			</td>
	</tr>



			';
	$iii++;
	//распределяем вывод согласно очередности
	if ($br_line->item_status=='to_deliver') {
			if ($subdl['from']<=$today_date) {
				$to_deliver_rows_1.=$t_row;
			}
			if ($subdl['from']>$today_date) {
				$to_deliver_rows_2.=$t_row;
			}
	}
	elseif ($br_line->item_status=='bron') {
		$bron_rows.=$t_row;
	}
	else {
		$other_rows.=$t_row;
	}

	$t_row='';

	unset($br_line);
}

echo $iii;

echo $to_deliver_rows_1;
echo $to_deliver_rows_2;
echo $bron_rows;
echo $other_rows;






function get_post($var)
{
	GLOBAL $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}


function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}

function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}


function user_select ($id) {
	return '
		<option value="">не определен</option>
      		<option '.sel_d('2', $id).' value="2">Кристина</option>
			<option '.sel_d('5', $id).' value="5">Аня</option>
			<option '.sel_d('4', $id).' value="4">Андрей</option>
			<option '.sel_d('9', $id).' value="9">Света</option>
			<option '.sel_d('12', $id).' value="12">Алексей</option>
			<option '.sel_d('13', $id).' value="13">Таня</option>
			<option '.sel_d('16', $id).' value="16">Любовь Алексеевна</option>
			<option '.sel_d('18', $id).' value="18">Марго</option>

				';
}



/*описание некоторых подходов
 * Возможные варианты жесткой брони: type2 bron, deliv, remont, out
 * Варианты нежесткой брони: stirka_rent, заявка
 *
 *
 *
 *
 *
 *
 *
 *
 * */


?>
