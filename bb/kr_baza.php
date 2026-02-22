<?php

use bb\Base;

session_start();
//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //

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

	<form action="/bb/index.php" method="post">
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
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
' . Base::getBarCodeReaderScript() . '
</head>
<title>К возврату.</title>
<body>

';
require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show.php'); // включаем подключение к звонкам

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

?>

<script language="javascript">
	function zvonok(dl_id) {

		if (document.getElementById('info_' + dl_id).disabled == true) {
			document.getElementById('info_' + dl_id).disabled = false;
			document.getElementById('sohr_' + dl_id).style.display = "";

			document.getElementById('zvonok_' + dl_id).value = "отмена";
		}
		else {
			document.getElementById('info_' + dl_id).disabled = true;
			document.getElementById('sohr_' + dl_id).style.display = "none";

			document.getElementById('zvonok_' + dl_id).value = "звонок"
		}


	}



	function but_sh(dl_id) {
		//alert ('ok');
		if (document.getElementById('status_but_' + dl_id).style.display == "none") {
			document.getElementById('status_but_' + dl_id).style.display = "";
		}
		else {
			document.getElementById('status_but_' + dl_id).style.display = "none";
		}
	}//end of function




</script>


<?php

$mysqli = \bb\Db::getInstance()->getConnection();

$deal_id = '';
$zpl_id = 'all';
$today = getdate(time());
$phone_list = '';
$office_filter = 0;

$to_date = mktime(0, 0, 0, $today['mon'], ($today['mday'] + 1), $today['year']);


$now_date = mktime(0, 0, 0, $today['mon'], ($today['mday']), $today['year']);

$sms_date = $now_date + 24 * 60 * 60;

$m1_date = mktime(0, 0, 0, $today['mon'] - 1, ($today['mday']), $today['year']);

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

if ($sms_date != ($now_date + 24 * 60 * 60)) {
	$sms_date = strtotime($sms_date);
}
//Base::varDamp($sms_date);
if (1 == 1) {    //if (isset($action) && $action=='поделить плохие долги') {
	$db = \bb\Db::getInstance();
	$mysqli = $db->getConnection();

	//перечисляем ID участвующих сотрудников
	$bad_solvers_count = array(
		'4' => 0,
		'22' => 0,
		'24' => 0,
		'26' => 0,
	);

	$bad_solvers_clients = array();
	$new_bad_solvers = array();


	//запрос информации о просроченных НЕПОДЕЛЕННЫХ сделках (более месяца)
	$bad_d_q = "SELECT `client_id`, `deal_status`, `deal_id` FROM rent_deals_act WHERE return_date<='$m1_date' AND deal_status = '' ORDER BY return_date";
	$bad_d_result = $mysqli->query($bad_d_q);
	if (!$bad_d_result)
		die('Сбой при доступе к базе данных: ' . $bad_d_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

	//далее идем, только если есть что делить
	if ($bad_d_result->num_rows > 0) {
		echo "number of records to assign:" . $bad_d_result->num_rows . "<br><br>";
		//запрос информации о просроченных ПОДЕЛЕННЫХ клиентах с просроченными (более месяца) сделками
		$bad_d_q2 = "
            SELECT deal_status, `client_id`
            FROM rent_deals_act
            WHERE deal_status NOT IN ('', 'bron', 'for_cur') AND return_date<='$m1_date'
            ORDER BY `client_id`
            ";
		$bad_d_result2 = $mysqli->query($bad_d_q2);
		if (!$bad_d_result2)
			die('Сбой при доступе к базе данных: ' . $bad_d_result2 . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

		//заполняем массив уже поделенных клиентов
		$prev_client_id = '';
		while ($bad_d2 = $bad_d_result2->fetch_assoc()) {
			if (!array_key_exists($bad_d2['deal_status'], $bad_solvers_count))
				continue; //if not active user - skip
			$bad_solvers_clients[$bad_d2['client_id']] = $bad_d2['deal_status']; //for every client user is assigned. If duplication - just overwright :).
			if ($prev_client_id != $bad_d2['client_id'])
				$bad_solvers_count[$bad_d2['deal_status']] += 1; //increase counter

			$prev_client_id = $bad_d2['client_id'];
		}

		//делим неподеленных клиентов
		while ($bad_d = $bad_d_result->fetch_assoc()) {
			//check is the client for the deal is already assigned to users
			if (isset($bad_solvers_clients[$bad_d['client_id']]))
				$min_user_id = $bad_solvers_clients[$bad_d['client_id']];
			else
				$min_user_id = array_keys($bad_solvers_count, min($bad_solvers_count))[0];  # array('$bad_solvers_count')

			$bad_solvers_count[$min_user_id] += 1;
			$new_bad_solvers[$min_user_id][] = $bad_d['deal_id'];
		}

		//        echo '<pre>';
//        var_dump($bad_solvers_count);
//        echo '<br><br><br>';
//        var_dump($bad_solvers_clients);
//        echo '<br><br><br>';
//        var_dump($new_bad_solvers);
//        echo '</pre>';
//    //IN (' . implode(',', array_map('intval', $array)) . ')'

		//update assignment in database
		foreach ($new_bad_solvers as $key => $value) {
			$query_upd = "UPDATE rent_deals_act SET deal_status='$key' WHERE deal_id IN (" . implode(',', array_map('intval', $value)) . ") ";
			$upd_result = $mysqli->query($query_upd);
			if (!$upd_result)
				die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}

		echo 'Долги распределены';

	}


}




if (isset($action) && $action == 'сохранить обзвон') {
	$query_dl_upd = "UPDATE rent_deals_act SET deal_info='$info' WHERE deal_id='$deal_id'";
	$result_dl_upd = $mysqli->query($query_dl_upd);
	if (!$result_dl_upd)
		die('Сбой при доступе к базе данных: ' . $query_dl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
} elseif (isset($action) && $action == '->') {
	$query_dl_upd = "UPDATE rent_deals_act SET deal_status='$dl_status' WHERE deal_id='$deal_id'";
	$result_dl_upd = $mysqli->query($query_dl_upd);
	if (!$result_dl_upd)
		die('Сбой при доступе к базе данных: ' . $query_dl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}


//$to_date=strtotime(date("Y-m-d"));


if ($zpl_id == 'all') {
	$srch_zpl = "";
} elseif ($zpl_id == '0' || $zpl_id == '') {
	$srch_zpl = " AND (`deal_status`='0' OR `deal_status`='')";
} elseif ($zpl_id == 'no_penalty') {
	$srch_zpl = " AND (`deal_status`='$zpl_id')";
} elseif ($zpl_id > 0) {
	$srch_zpl = " AND (`deal_status`='$zpl_id')";
}


//запрос актуальной информации о сделках
$query_dl_def = "SELECT * FROM rent_deals_act WHERE return_date<='$sms_date'$srch_zpl ORDER BY return_date DESC";
$result_dl_def = $mysqli->query($query_dl_def);
if (!$result_dl_def)
	die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);





echo '
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> ' . $_SESSION['user_fio'] . '</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>
		<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/rda.php">Все сделки</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
</div>
	<h2>Товары к возврату:</h2>

Долги, числящиеся за:

<form name="srch_form" method="post" id="srch_form" action="kr_baza.php">
		<select name="zpl_id" id="zpl_id">
			<option value="all" ' . ($zpl_id == 'all' ? 'selected="selected"' : '') . '>Показать все</option>
			<option value="0" ' . ($zpl_id == '0' ? 'selected="selected"' : '') . '>Без конкретного закрепления</option>
			<option value="4" ' . ($zpl_id == '4' ? 'selected="selected"' : '') . '>Андреем</option>
			<option value="22" ' . ($zpl_id == '22' ? 'selected="selected"' : '') . '>Катей</option>
			<option value="24" ' . ($zpl_id == '24' ? 'selected="selected"' : '') . '>Мариной</option>
			<option value="26" ' . ($zpl_id == '26' ? 'selected="selected"' : '') . '>Юлей</option>
			<option value="no_penalty" ' . ($zpl_id == 'no_penalty' ? 'selected="selected"' : '') . '>Исключены</option>
		</select>
		<select name="office_filter" onchange="this.form.submit();">
			<option value="0" ' . (Base::sel_d($office_filter, "0")) . '>все офисы</option>
			<option value="1" ' . (Base::sel_d($office_filter, "1")) . '>Литературная</option>
			<option value="2" ' . (Base::sel_d($office_filter, "2")) . '>Ложинская</option>
			<option value="3" ' . (Base::sel_d($office_filter, "3")) . '>Победителей</option>
		</select>
		<input type="submit" name="action" value="показать" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" ' . ($_SESSION['level'] >= 5 ? '' : 'disabled="disabled"') . ' name="action" value="поделить плохие долги" />   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;   <input type="button" value="показать телефоны для СМС на" onclick="document.getElementById(\'sms_place\').innerHTML=document.getElementById(\'sms_ph_list\').value;"/>: <form action="kr_baza.php" method="post" style="display:inline;"><input type="date" value="' . date("Y-m-d", $sms_date) . '" id="sms_date" name="sms_date" /> <input type="submit" value="сменить дату" />(от указанной даты и ранее)</form>
</form>


<div id="sms_place"></div>



	<table border="1" cellspacing="0" style="table-layout:fixed">
		<tr>
			<th>товар</th>
			<th>с</th>
			<th>по</th>
			<th>к. продл</th>
			<th>клиент</th>
			<th>долг</th>
			<th>обзвоны</th>
			<th>действие</th>
		</tr>
	';

$prev_date = '';

while ($dl_def = $result_dl_def->fetch_assoc()) {

	//запрос актуальной информации о клиенте
	$query_cl_def = "SELECT * FROM clients WHERE client_id='" . $dl_def['client_id'] . "'";
	$result_cl_def = $mysqli->query($query_cl_def);
	if (!$result_cl_def)
		die('Сбой при доступе к базе данных: ' . $query_cl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$cl_def = $result_cl_def->fetch_assoc();


	//запрос информации о товаре по инв. номеру
	$query_item_def = "SELECT * FROM tovar_rent_items WHERE item_inv_n='" . $dl_def['item_inv_n'] . "'";
	$result_item_def = $mysqli->query($query_item_def);
	if (!$result_item_def)
		die('Сбой при доступе к базе данных: ' . $query_item_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$item_def = $result_item_def->fetch_assoc();

	$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='" . $item_def['model_id'] . "'";
	$result_model_def = $mysqli->query($query_model_def);
	if (!$result_model_def)
		die('Сбой при доступе к базе данных: ' . $query_model_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$model_def = $result_model_def->fetch_assoc();

	$model_def['color'] == 0 ? $color = '' : $color = ', цвет: ' . $model_def['color'];

	//запрос информации о категории товара
	$query_cat_def = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='" . $model_def['tovar_rent_cat_id'] . "'";
	$result_cat_def = $mysqli->query($query_cat_def);
	if (!$result_cat_def)
		die('Сбой при доступе к базе данных: ' . $query_cat_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$cat_def = $result_cat_def->fetch_assoc();

	//считаем количество продлений
	$query_sub_dl_def = "SELECT * FROM rent_sub_deals_act WHERE `deal_id`='" . $dl_def['deal_id'] . "' AND `type`='extention'";
	$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
	if (!$result_sub_dl_def)
		die('Сбой при доступе к базе данных: ' . $query_sub_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$ext_num = $result_sub_dl_def->fetch_assoc();

	//ищем первую сдачу
	$query_fr_def = "SELECT * FROM rent_sub_deals_act WHERE `deal_id`='" . $dl_def['deal_id'] . "' AND `type`='first_rent'";
	$result_fr_def = $mysqli->query($query_fr_def);
	if (!$result_fr_def)
		die('Сбой при доступе к базе данных: ' . $query_fr_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$fr_def = $result_fr_def->fetch_assoc();

	//office filter
	if ($office_filter != '0') {
		if ($fr_def['place'] != $office_filter * 1 || $fr_def['delivery_yn'] == 1)
			continue;
	}


	//разделительная полоса
	if ($prev_date > 0 && $dl_def['return_date'] != $prev_date) {
		echo '<tr style="background-color:#3300FF; height:20px;">
			' . ($_SESSION['user_id'] == 3 ? '<td></td>' : '') . '<td></td><td></td><td></td><td></td><td></td><td></td><td></td>
		</tr>';
	}
	$prev_date = $dl_def['return_date'];

	if ($deal_id == $dl_def['deal_id']) {
		$act_style = 'style="background-color:yellow;"';
	} else {
		$act_style = '';
	}

	$of_pic = '';

	if ($fr_def['place'] == '1') {
		$of_pic = 'оф1<img src="/bb/assets/images/1.gif" alt="офис 1" style="position:absolute; top:0px; right:0px;" />';
	} elseif ($fr_def['place'] == '2') {
		$of_pic = 'оф2<img src="/bb/assets/images/2.gif" alt="офис 2" style="position:absolute; top:0px; right:0px;" />';
	}


	//формирование списка телефонов
	if ($dl_def['return_date'] == $sms_date) {
		$phone_list .= $cl_def['phone_1'] . '<br />';
	}


	echo '
		<tr ' . $act_style . '>
			<td><div style="position:relative;">' . $of_pic . '<strong>№' . inv_print($dl_def['item_inv_n']) . '</strong><br />' . $cat_def['dog_name'] . ' ' . $model_def['model'] . ', ' . $model_def['producer'] . $color . ' ' . ($_SESSION['user_id'] == 3 ? '<br /><i>' . $dl_def['deal_id'] . '</i></strong>' : '') . '</div></td>
			<td>' . date("d.m.y", $dl_def['start_date']) . '</td>
			<td>' . date("d.m.y", $dl_def['return_date']) . '</td>
			<td>' . $ext_num . '</td>
			<td>' . $cl_def['family'] . ' ' . $cl_def['name'] . ' ' . $cl_def['otch'] . '<br />' . phone_print($cl_def['phone_1']) . ', ' . phone_print($cl_def['phone_2']) . '</td>
			<td>' . pay_calc($dl_def['deal_id'], $now_date) . '
				<form method="post" id="status_ch_' . $dl_def['deal_id'] . '" action="kr_baza.php">
					<select ' . (($_SESSION['level'] >= 5 || $_SESSION['user_id'] == '13') ? '' : 'disabled="disabled"') . ' name="dl_status" id="of_select" onchange="but_sh(\'' . $dl_def['deal_id'] . '\')" ' . ($dl_def['deal_status'] == 'no_penalty' ? 'style="background-color:green;"' : '') . '>
						<option value="0" ' . sel_d($dl_def['deal_status'], '0') . '>Не распределено</option>
						<option value="4" ' . sel_d($dl_def['deal_status'], '4') . '>Андрей</option>
						<option value="22" ' . sel_d($dl_def['deal_status'], '22') . '>Катя</option>
						<option value="24" ' . sel_d($dl_def['deal_status'], '24') . '>Марина</option>
						<option value="26" ' . sel_d($dl_def['deal_status'], '26') . '>Юля</option>
						<option value="no_penalty" ' . sel_d($dl_def['deal_status'], 'no_penalty') . '>искл.</option>
					</select><br />' . $dl_def['deal_status'] . '
					<input type="hidden" name="deal_id" value="' . $dl_def['deal_id'] . '" />
					<input type="hidden" name="zpl_id" value="' . $zpl_id . '" />
					<input id="status_but_' . $dl_def['deal_id'] . '" name="action" type="submit" value="->" style="display:none;" />
				</form

				</td>
			<td><textarea cols="40" rows="3" name="info" id="info_' . $dl_def['deal_id'] . '" disabled="disabled" form="deal_info_' . $dl_def['deal_id'] . '">' . good_print($dl_def['deal_info']) . '</textarea></td>
			<td><form method="post" action="dogovor_new.php">
					<input type="hidden" name="item_inv_n" value="' . $dl_def['item_inv_n'] . '" />
					<input type="hidden" name="client_id" value="' . $dl_def['client_id'] . '" />
					<input type="submit" value="к договору" />
				</form>
				<form method="post" action="kr_baza.php" id="deal_info_' . $dl_def['deal_id'] . '">
					<input type="hidden" name="deal_id" value="' . $dl_def['deal_id'] . '" />
					<input type="hidden" name="zpl_id" value="' . $zpl_id . '" />
					<input type="submit" name="action" value="сохранить обзвон" id="sohr_' . $dl_def['deal_id'] . '" style="display:none;" />
				</form>
				<input type="button" value="звонок" onclick="zvonok(\'' . $dl_def['deal_id'] . '\')" id="zvonok_' . $dl_def['deal_id'] . '"/>
				</td>
		</tr>
	';
}

echo '
	</table>

<input type="hidden" id="sms_ph_list" value="Телефоны на дату возврата <strong>' . date('d.m.Y', $sms_date) . '</strong><br>' . $phone_list . '" />
		';


function inv_print($inv_n)
{

	$output = substr($inv_n, 0, 3) . '-' . substr($inv_n, 3);

	return $output;

}


function get_post($var)
{
	$mysqli = \bb\Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
}


function good_print($var)
{
	$var = htmlspecialchars(stripslashes($var));
	return $var;
}


function phone_print($ph)
{
	if ($ph == '') {
		return '';
	}

	$dl = strlen($ph);

	if ($dl < 7) {
		return $ph;
	}

	$dl > 7 ? $dl_to = $dl - 7 : $dl_to = 0;
	$ph_out = substr($ph, 0, $dl_to) . '-' . substr($ph, -7, 3) . '-' . substr($ph, -4, 2) . '-' . substr($ph, -2, 2);
	return $ph_out;
}



function pay_calc($deal_id, $ret_date)
{
	$mysqli = \bb\Db::getInstance()->getConnection();

	//запрос информации о сделке
	$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
	$result_dl_def = $mysqli->query($query_dl_def);
	if (!$result_dl_def)
		die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$dl_def = $result_dl_def->fetch_assoc();

	//вытягиваем последний примененный тариф
	$query_sub_dl_tarif = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND type IN ('first_rent', 'extention') ORDER BY `from` DESC";
	$result_sub_dl_tarif = $mysqli->query($query_sub_dl_tarif);
	if (!$result_sub_dl_tarif)
		die('Сбой при доступе к базе данных: ' . $query_sub_dl_tarif . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$sub_dl_tarif = $result_sub_dl_tarif->fetch_assoc();




	//расчет платы за просрочку
	if ($ret_date > $dl_def['return_date']) {
		$morepay = 'просрочка';
		switch ($sub_dl_tarif['tarif_step']) {
			case 'month':

				if (date("j", $ret_date) >= date("j", $dl_def['return_date'])) { //вариант расчета, если текущий день равен, либо больше дня возврата
					$m_dif = (date("Y", $ret_date) * 12 + date("n", $ret_date)) - (date("Y", $dl_def['return_date']) * 12 + date("n", $dl_def['return_date'])); // считаем разницу в месяцах
					$day_rent = $sub_dl_tarif['tarif_value'] / 30;
					$to_pay_ad = -($m_dif * $sub_dl_tarif['tarif_value'] + (date("j", $ret_date) - date("j", $dl_def['return_date'])) * $day_rent);
					$morepay = round($to_pay_ad, 1);
				}

				if (date("j", $ret_date) < date("j", $dl_def['return_date'])) { //вариант расчета, если текущий менее дня возврата
					$m_dif = (date("Y", $ret_date) * 12 + date("n", $ret_date) - 1) - (date("Y", $dl_def['return_date']) * 12 + date("n", $dl_def['return_date'])); // считаем разницу в месяцах
					$day_rent = $sub_dl_tarif['tarif_value'] / 30;
					$to_pay_ad = -($m_dif * $sub_dl_tarif['tarif_value'] + (date("j", $ret_date) + date("t", $dl_def['return_date']) - date("j", $dl_def['return_date'])) * $day_rent);
					$morepay = round($to_pay_ad, 1);
				}
				break;

			case 'week';
				$day_dif = floor(($ret_date - $dl_def['return_date']) / 60 / 60 / 24);
				$week_dif = floor($day_dif / 7);
				$day_dif_left = $day_dif - $week_dif * 7;
				$day_tarif = $sub_dl_tarif['tarif_value'] / 7;
				$to_pay_ad = -($week_dif * $sub_dl_tarif['tarif_value'] + $day_dif_left * $day_tarif);
				$morepay = round($to_pay_ad, 1);

				break;

			case 'day':

				$day_dif = floor(($ret_date - $dl_def['return_date']) / 60 / 60 / 24);
				$to_pay_ad = -($day_dif * $sub_dl_tarif['tarif_value']);
				$morepay = round($to_pay_ad, 1);

				break;


			default:
				echo 'не считает функция просрочки, сделка:' . $deal_id;
				break;
		}



	} elseif ($ret_date == $dl_def['return_date']) {
		$morepay = 'срок возврата сегодня';
		$to_pay_ad = '0';
	} else {
		$morepay = 'срок завтра'; // реально показывае все, что далее сегодня, но на этой странице, просто выборка ограничена завтрашним днем.
		$to_pay_ad = '0';
	}



	return $morepay;
}// end of pay_calc function

function sel_d($value, $pattern)
{
	if ($value == $pattern) {
		return 'selected="selected"';
	} else {
		return '';
	}
}


?>