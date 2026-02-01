<?php

use bb\Base;

session_start();
//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //

$mysqli=\bb\Db::getInstance()->getConnection();

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
<title>Карнавал. Брони.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/kb.php"><strong>Актуальные брони</strong></a>
	<a class="div_item" href="/bb/kb_lines.php">Карнавальная таблица</a>
</div><br />

';
?>

<script language="javascript">

function appr_show (br_id) {

	if (document.getElementById('phone1_'+br_id).style.display=="none") {
		document.getElementById('phone1_'+br_id).style.display="";
		document.getElementById('phone2_'+br_id).style.display="";
		document.getElementById('info_'+br_id).style.display="";
		document.getElementById('subm_but_'+br_id).style.display="";
		document.getElementById('dl_link_'+br_id).style.display="";

		if (document.getElementById('user_id').value=="2" || document.getElementById('user_id').value=="3") {
				document.getElementById('br_date_from_'+br_id).style.display="";
				document.getElementById('br_hour_from_'+br_id).style.display="";
				document.getElementById('br_date_to_'+br_id).style.display="";
				document.getElementById('br_hour_to_'+br_id).style.display="";
		}

		document.getElementById('show_but_'+br_id).value="отмена";
	}
	else {
		document.getElementById('phone1_'+br_id).style.display="none";
		document.getElementById('phone2_'+br_id).style.display="none";
		document.getElementById('info_'+br_id).style.display="none";
		document.getElementById('subm_but_'+br_id).style.display="none";
		document.getElementById('br_date_from_'+br_id).style.display="none";
		document.getElementById('br_hour_from_'+br_id).style.display="none";
		document.getElementById('br_date_to_'+br_id).style.display="none";
		document.getElementById('br_hour_to_'+br_id).style.display="none";
		document.getElementById('dl_link_'+br_id).style.display="none";

		document.getElementById('show_but_'+br_id).value="подтвердить";
	}


}


function s_ch() {

	var start_date=new Date(document.getElementById('start_date').value);
	var finish_date=new Date(document.getElementById('finish_date').value);

	if (start_date>finish_date) {
		alert ('Дата "C" фильтра должна быть меньше либо равна дате "по" фильтра');
		return false;
	}
	else {
		return true;
	}

}


function del_ch (kb_id) {
if (confirm ('Точно хотите удалить бронь?')) {
			return true;
		}
	else {
		return false;
	}

}//end of del_ch function


</script>

<?php


require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$br_ch_id='';
$inv_n='';
$t_from=date("Y-m-d", time());
$t_to=date("Y-m-d", time()+31*3*24*60*60);
$br_status='all';
$order_br='t_from';
$br_hour_from='';
$br_hour_to='';
$main_rows=0;

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

$t_from_n=strtotime($t_from);
$t_to_n=strtotime($t_to);

if (isset($_POST['action'])) {

	switch ($action) {



		case 'удалить совсем':

			$query_del = "DELETE FROM karn_brons_arch WHERE kb_id='$br_id'";
			$result_del = $mysqli->query($query_del);
			if (!$result_del) {die('Сбой при доступе к базе данных: '.$query_del.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
				$done="no";
			}

			echo '<p style="color:red;"><strong>Бронь успешно удалена из архива!</strong> </p>';

		break;



	}//end of switch
}//end of spost if



echo '
		<strong><p style="color:red; font-size:28px;">Страница <u>удаленных</u> броней</p></strong>
	<form name="br_srch" action="kb_arch.php" method="post" id="br_srch">
		Инв.№:<input type="text" name="inv_n" value="'.$inv_n.'" size="5" />(пусто=все), брони в период c <input type="date" name="t_from" id="start_date" value="'.$t_from.'" /> по <input type="date" name="t_to" id="finish_date" value="'.$t_to.'" />,
		со статусом:
		<select name="br_status">
			<option value="all" '.sel_d('all', $br_status).'>все</option>
        	<option value="new" '.sel_d('new', $br_status).'>не проверено</option>
        	<option value="ok" '.sel_d('ok', $br_status).'>подтверждено</option>
			<option value="in_process" '.sel_d('in_process', $br_status).'>временные</option>
		</select>
		сортировка по:
		<select name="order_br">
			<option value="t_from" '.sel_d('t_from', $order_br).'>времени выдачи (ближайшее-вверху)</option>
        	<option value="cr_time" '.sel_d('cr_time', $order_br).'>времени заведения (последние-вверху)</option>
		</select>

				<br />
		<input name="action" type="submit" onclick="return s_ch();" value="фильтр" />
	</form>
		<input type="hidden" id="user_id" value="'.$_SESSION['user_id'].'" />
		';





//основная выборка броней
if ($inv_n>0) {
	$srch=" WHERE inv_n='$inv_n'";
}
else {
	$srch=" WHERE inv_n>0";
}


//время
$t_to_n2=$t_to_n+24*3600-1;
if ($t_from_n>0 && $t_to_n>0) {
	$srch.=" AND ((t_from>=$t_from_n AND t_from<=$t_to_n2) OR (t_to>=$t_from_n AND t_to<=$t_to_n2))";
}
elseif ($t_from_n>0 && $t_to_n==0) {
	$srch.=" AND ((t_from>=$t_from_n) OR (t_to>=$t_from_n))";
}
elseif ($t_from_n==0 && $t_to_n>0) {
	$srch.=" AND ((t_from<=$t_to_n2) OR (t_to<=$t_to_n2))";
}

if ($br_status!='all') {
	$srch.=" AND `status`='$br_status'";
}

if ($order_br=='t_from') {
	$order_cl=" ORDER BY t_from";
}
elseif ($order_br=='cr_time') {
	$order_cl=" ORDER BY cr_time DESC";
}

$query_kb = "SELECT * FROM karn_brons_arch$srch$order_cl";
$result_kb = $mysqli->query($query_kb);
if (!$result_kb) {die('Сбой при доступе к базе данных: '.$query_kb.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$main_rows=$result_kb->num_rows;


echo '<br /><br /><br />
'.$main_rows.'
<table border="1" cellspacing="0">
	<tr>
		<td>время удаления</td>
		<td>причина</td>
		<td>№ брони</td>
		<td>инв. н</td>
		<td>с</td>
		<td>по</td>
		<td>договор</td>
		<td>статус</td>
		<td>фио</td>
		<td>телефон1<br />(+375-)</td>
		<td>телефон2<br />(+375-)</td>
		<td>почта</td>
		<td>доп. инфо</td>
		<td>Действия</td>
	</tr>
		';

while ($kb=$result_kb->fetch_assoc()) {
	$st_color='';
	$query_item="SELECT * FROM tovar_rent_items LEFT JOIN tovar_rent ON (tovar_rent_items.model_id = tovar_rent.tovar_rent_id) WHERE item_inv_n='".$kb['inv_n']."'";
	$result_item = $mysqli->query($query_item);
	if (!$result_item) {die('Сбой при доступе к базе данных: '.$query_item.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$item=$result_item->fetch_assoc();

	$dl_info='';

	if ($kb['status']=='ok') {
		$st_color=' style="	background-color:#39C;"';
	}
	if ($br_ch_id==$kb['kb_id']) {
		$st_color=' style="background-color:blue"';
	}

	if ($kb['dl_link']>0) {

		$query_dl = "SELECT * FROM rent_deals_act WHERE deal_id='".$kb['dl_link']."'";
		$result_dl = $mysqli->query($query_dl);
		if (!$result_dl) {die('Сбой при доступе к базе данных: '.$query_dl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$dl_rows=$result_dl->num_rows;

			if ($dl_rows<1) {
				$query_dl = "SELECT * FROM rent_deals_arch WHERE deal_id='".$kb['dl_link']."'";
				$result_dl = $mysqli->query($query_dl);
				if (!$result_dl) {die('Сбой при доступе к базе данных: '.$query_dl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				$dl_rows=$result_dl->num_rows;
			}

	if ($dl_rows==1) {
		$dl=$result_dl->fetch_assoc();
		$dl_info='опл.:'.number_format($dl['r_paid'], 0, ',', ' ');
		$st_color=' style="background-color:yellow"';
	}
	elseif ($dl_rows<1) {
		$dl_info='сделок не найдено. обратитесь к разработчику';
	}
	else {
		$dl_info='более 1 сделки. обратитесь к разработчику';
	}
	}//end of link>0 if




	echo '
	<tr '.$st_color.'>
        <td><i>'.date("d.m (H:i)", $kb['arch_time']).'</i><br />'.user_name($kb['arch_who']).'</td>
        <td>'.$kb['arch_info'].'</td>
		<td>'.$kb['br_num'].'<br /><i>'.date("d.m (H:i)", $kb['cr_time']).'</i></td>
		<td>'.inv_print($kb['inv_n']).'<br />'.$item['model'].' ('.$item['item_size'].' / '.$item['item_rost1'].'-'.$item['item_rost2'].'см.)<br />
		др№'.$kb['dl_link'].'
			</td>
		<td>'.date("d.m.y (H:i)", $kb['t_from']).'<br />'.rus_day(date("w", $kb['t_from'])).'</td>
		<td>'.date("d.m.y (H:i)", $kb['t_to']).'<br />'.rus_day(date("w", $kb['t_to'])).'</td>
		<td>'.$dl_info.'</td>
		<td>'.stat_print($kb['status']).'<br />'.($kb['appr_time']>0 ? date("d.m (H:i)", $kb['appr_time']).'<br />'.user_name($kb['appr_who']) : '').'</td>
		<td>'.$kb['fio'].'</td>
		<td>'.phone_print($kb['phone1']).'</td>
		<td>'.phone_print($kb['phone2']).'</td>
		<td>'.$kb['mail'].'</td>
		<td>'.$kb['info'].'</td>
		<td>

			<form name="br_appr" action="kb_arch.php" method="post" id="br_appr_'.$kb['kb_id'].'" style="display:inline-block;">
				<input type="hidden" name="br_id" value="'.$kb['kb_id'].'" />
				'.($_SESSION['level']>=5 ? '<input name="action" type="submit" value="удалить совсем" onclick="return del_ch(\''.$kb['kb_id'].'\');" style=" padding:4px; margin:2px;" />' : '').'

		<input type="hidden" name="inv_n" value="'.$inv_n.'" />
		<input type="hidden" name="t_from" value="'.$t_from.'" />
		<input type="hidden" name="t_to" value="'.$t_to.'" />
		<input type="hidden" name="br_status" value="'.$br_status.'" />
		<input type="hidden" name="order_br" value="'.$order_br.'" />

			</form>
		</td>
	</tr>

			';
}
echo '</table>';

echo '</body></html>';




function get_post($var)
{
	GLOBAL $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
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
			return 'Света';
			break;
		case '13':
			return 'Татьяна';
			break;
		case '22':
				return 'Катя';
			break;
		case '18':
			return 'Марго';
			break;
		case '22':
			return 'Катя';
			break;
		case '24':
			return 'Марина';
			break;
		case '25':
			return 'Кристина_2';
			break;
		case '26':
			return 'Юля';
			break;
		case '28':
			return 'Алена';
			break;
		case '30':
			return 'Маргарита';
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

?>
