<?php

use bb\Base;

session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php'); //
require($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');

$mysqli = \bb\Db::getInstance()->getConnection();


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

	</body></html>');
}

//-----------proverka paroley

$in_del = array(2, 3, 5, 22);


if ($_SESSION['level'] < 5) {
	$dates_readonly = 'readonly="readonly"';
} else {
	$dates_readonly = '';
}


echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<style>
    .hide{
        display: none;
    }
</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
' . Base::getBarCodeReaderScript() . '
</head>
<title>BB: Доходы-расходы.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> ' . $_SESSION['user_fio'] . '</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: ' . $_SESSION['office'] . '</form> </div>
<div id="zv_div"></div>
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/rda.php">Все сделки (новые)</a>
</div>

		';
require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show.php'); // включаем подключение к звонкам

//Проверка входящей информации
//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//foreach ($_POST as $key => $value) {
//	echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";


$action = '';
$i_from_date = date("Y-m-d");
$i_to_date = date("Y-m-d");
$item_place = $_SESSION['office'];
$type2s = 'all';
$type1_s = 'all';
$type2_s = 'all';
$kassa_s = 'all';
$t2_select = '<option value="all">все</option>';
$zp_sel_s = 'all';

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}


switch ($action) {

	case 'сохранить':  //оплата

		$acc_date = strtotime($acc_date);
		$of = substr($channel, 0, 3);

		if ($type1 == 'rash' || $type1 == 'shift') {
			$amount = abs($amount) * (-1);
		} else {
			$amount = abs($amount);
		}



		switch ($channel) {
			case 'of1k1':
				$office = '1';
				$kassa = 'k1';
				break;

			case 'of1k2':
				$office = '1';
				$kassa = 'k2';
				break;

			case 'of2k1':
				$office = '2';
				$kassa = 'k1';
				break;

			case 'of2k2':
				$office = '2';
				$kassa = 'k2';
				break;

			case 'of3k1':
				$office = '3';
				$kassa = 'k1';
				break;

			case 'of3k2':
				$office = '3';
				$kassa = 'k2';
				break;

			case 'of4k1':
				$office = '4';
				$kassa = 'k1';
				break;

			case 'of4k2':
				$office = '4';
				$kassa = 'k2';
				break;

			case 'curk1':
				$office = 'cur';
				$kassa = 'k1';
				break;

			case 'curk2':
				$office = 'cur';
				$kassa = 'k2';
				break;

			case 'bank':
				$office = 'bank';
				$kassa = 'bank';
				break;

			default:
				$office = 'HZ';
				$kassa = 'HZ';
				break;
		}


		if ($type1 == 'shift') {
			//делаем расход по первой кассе
			$type1 = 'shift_minus';
			$ins_q = "INSERT INTO doh_rash VALUES('', '$acc_date', '$amount', '$type1', '$type2', '$office', '$kassa', '', '$info', '" . time() . "', '" . $_SESSION['user_id'] . "', '$zp_name')";
			$result_ins = $mysqli->query($ins_q);
			if (!$result_ins) {
				die('Сбой при доступе к базе данных: ' . $ins_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$link_id1 = $mysqli->insert_id;

			//делаем доход по второй кассе
			switch ($type2) {
				case 'of1k1':
					$office = '1';
					$kassa = 'k1';
					break;

				case 'of1k2':
					$office = '1';
					$kassa = 'k2';
					break;

				case 'of2k1':
					$office = '2';
					$kassa = 'k1';
					break;

				case 'of2k2':
					$office = '2';
					$kassa = 'k2';
					break;

				case 'of3k1':
					$office = '3';
					$kassa = 'k1';
					break;

				case 'of3k2':
					$office = '3';
					$kassa = 'k2';
					break;

				case 'of4k1':
					$office = '4';
					$kassa = 'k1';
					break;

				case 'of4k2':
					$office = '4';
					$kassa = 'k2';
					break;

				case 'curk1':
					$office = 'cur';
					$kassa = 'k1';
					break;

				case 'curk2':
					$office = 'cur';
					$kassa = 'k2';
					break;

				case 'bank':
					$office = 'bank';
					$kassa = 'bank';
					break;

				default:
					$office = 'HZ';
					$kassa = 'HZ';
					break;
			}

			$amount = abs($amount);

			$type1 = 'shift_plus';
			$ins_q = "INSERT INTO doh_rash VALUES('', '$acc_date', '$amount', '$type1', '$channel', '$office', '$kassa', '$link_id1', '$info', '" . time() . "', '" . $_SESSION['user_id'] . "', '$zp_name')";
			$result_ins = $mysqli->query($ins_q);
			if (!$result_ins) {
				die('Сбой при доступе к базе данных: ' . $ins_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$link_id2 = $mysqli->insert_id;

			//обновляем линк по расходу
			$upd_q = "UPDATE doh_rash SET link_to='$link_id2' WHERE dr_id='$link_id1'";
			$result_upd = $mysqli->query($upd_q);
			if (!$result_upd) {
				die('Сбой при доступе к базе данных: ' . $upd_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

		} else {
			$ins_q = "INSERT INTO doh_rash VALUES('', '$acc_date', '$amount', '$type1', '$type2', '$office', '$kassa', '', '$info', '" . time() . "', '" . $_SESSION['user_id'] . "', '$zp_name')";
			$result_ins = $mysqli->query($ins_q);
			if (!$result_ins) {
				die('Сбой при доступе к базе данных: ' . $ins_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$link_id1 = $mysqli->insert_id;

		}

		break;

	case 'удалить':  //оплата

		if ($dr_id_link > 0) {
			$dohRash = \bb\classes\DohRash::getById($dr_id);
			if ($dohRash)
				$dohRash->logBeforeDelete();
			$dohRash = \bb\classes\DohRash::getById($dr_id_link);
			if ($dohRash)
				$dohRash->logBeforeDelete();

			$del_q = "DELETE FROM doh_rash WHERE dr_id IN ('$dr_id', '$dr_id_link')";

		} else {
			$dohRash = \bb\classes\DohRash::getById($dr_id);
			if ($dohRash)
				$dohRash->logBeforeDelete();

			$del_q = "DELETE FROM doh_rash WHERE dr_id IN ('$dr_id')";
		}
		$result_del = $mysqli->query($del_q);
		if (!$result_del) {
			die('Сбой при доступе к базе данных: ' . $del_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}

		echo '<strong>Операция(-и) успешно удалена.</strong>';

		break;

	case 'update_rash':  //оплата
		$zplAdd = '';
		if ($type2 == 'zpl') {
			$zplAdd = ", dr_name_id='$zp_name' ";
		}
		$query = "UPDATE doh_rash SET type2='$type2', info='$info_upd'$zplAdd WHERE dr_id='$dr_id'";
		$result_upd = $mysqli->query($query);
		if (!$result_upd) {
			die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}
		break;


}//end of switch


if ($item_place == 'all' && ($_SESSION['user_id'] == '2' || $_SESSION['user_id'] == '3' || $_SESSION['user_id'] == '5' || $_SESSION['user_id'] == '9')) {
	$srch = '';
} elseif ($item_place == 'all') {
	$srch = " AND `channel`!='bank'";
} else {
	$srch = " AND `channel`='$item_place'";
}


if ($type1_s == 'doh') {
	$srch .= " AND `type1`='doh'";
} elseif ($type1_s == 'rash') {
	$srch .= " AND `type1`='rash'";
} elseif ($type1_s == 'shift') {
	$srch .= " AND `type1` LIKE 'shift%'";
}

if ($kassa_s == 'k1') {
	$srch .= " AND `kassa`='k1'";
} elseif ($kassa_s == 'k2') {
	$srch .= " AND `kassa`='k2'";
}

if ($type2_s != 'all') {
	$srch .= " AND `type2`='$type2_s'";
}

if ($zp_sel_s != 'all') {
	$srch .= " AND `dr_name_id`='$zp_sel_s'";
}

$rash["of1k1"] = "Литературная_22_1";
$rash["of1k2"] = "Литературная_2_2";
$rash["of2k1"] = "Ложинская_1";
$rash["of2k2"] = "Ложинская_2";
$rash["of3k1"] = "Победителей_127_1";
$rash["of3k2"] = "Победителей_127_2";
$rash["of4k1"] = "Склад_1";
$rash["of4k2"] = "Склад_2";
$rash["curk1"] = "Курьер_1";
$rash["curk2"] = "Курьер_2";
$rash["bank"] = "Банк";

$doh = $rash;

//формируем перечень расходов
$ri_q = "SELECT * FROM rash_items WHERE bank_yn!=1 ORDER BY ri_order";
$result_ri = $mysqli->query($ri_q);
if (!$result_ri) {
	die('Сбой при доступе к базе данных: ' . $ri_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$ri_t1 = '';
$ri_t1_s = '';
while ($ri_def = $result_ri->fetch_assoc()) {
	if ($ri_def['is_active'] == 1) {
		$ri_t1 .= '<option value="' . $ri_def['ri_code'] . '">' . $ri_def['ri_text'] . '</option>';
	}
	$ri_t1_s .= '<option value="' . $ri_def['ri_code'] . '" ' . sel_d($ri_def['ri_code'], $type2_s) . '>' . $ri_def['ri_text'] . '</option>';
	$rash[$ri_def['ri_code']] = $ri_def['ri_text'];
}

$ri_q = "SELECT * FROM rash_items WHERE bank_yn=1 ORDER BY ri_order";
$result_ri = $mysqli->query($ri_q);
if (!$result_ri) {
	die('Сбой при доступе к базе данных: ' . $ri_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$ri_t2 = $ri_t1;
$ri_t2_s = $ri_t1_s;
while ($ri_def = $result_ri->fetch_assoc()) {
	if ($ri_def['is_active'] == 1) {
		$ri_t2 .= '<option value="' . $ri_def['ri_code'] . '">' . $ri_def['ri_text'] . '</option>';
	}
	$ri_t2_s .= '<option value="' . $ri_def['ri_code'] . '" ' . sel_d($ri_def['ri_code'], $type2_s) . '>' . $ri_def['ri_text'] . '</option>';
	$rash[$ri_def['ri_code']] = $ri_def['ri_text'];
}


//формируем перечень доходов
$rd_q = "SELECT * FROM doh_items WHERE bank_yn!=1 ORDER BY rd_order";
$result_rd = $mysqli->query($rd_q);
if (!$result_rd) {
	die('Сбой при доступе к базе данных: ' . $rd_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$rd_t1 = '';
$rd_t1_s = '';
while ($rd_def = $result_rd->fetch_assoc()) {
	if ($rd_def['is_active'] == 1) {
		$rd_t1 .= '<option value="' . $rd_def['rd_code'] . '">' . $rd_def['rd_text'] . '</option>';
	}
	$rd_t1_s .= '<option value="' . $rd_def['rd_code'] . '" ' . sel_d($rd_def['rd_code'], $type2_s) . '>' . $rd_def['rd_text'] . '</option>';
	$doh[$rd_def['rd_code']] = $rd_def['rd_text'];
}

//формируем вывод для фильтра type2
if ($type1_s == 'all') {
	$t2_select = '<option value="all">все</option>';
} elseif ($type1_s == 'doh') {
	$t2_select = '<option value="all">все</option>' . $rd_t1_s;
} elseif ($type1_s == 'rash') {
	$t2_select = '<option value="all">все</option>' . $ri_t2_s;
} elseif ($type1_s == 'shift') {
	$t2_select = '<option value="all">все</option>
				<option value="of1k1" style="background-color:#b1ebb1;">Литературная_20_1</option>
				<option value="of1k2" style="background-color:#b1ebb1;">Литературная_20_2</option>
				<option value="of2k1" style="background-color:#ffe400;">Уручье_1</option>
				<option value="of2k2" style="background-color:#ffe400;">Уручье_2</option>
				<option value="of3k1" style="background-color:#b1ebb1;">Победителей_127_1</option>
				<option value="of3k2" style="background-color:#b1ebb1;">Победителей_127_2</option>
				<option value="of4k1" style="background-color:#b1ebb1;">Склад_1</option>
				<option value="of4k2" style="background-color:#b1ebb1;">Склад_2</option>
				<option value="curk1" style="background-color:#c6edf0;">Курьер_1</option>
				<option value="curk2" style="background-color:#c6edf0;">Курьер_2</option>
				<option value="bank">банк</option>';
}


//формируем перечень пользователей
$rd_lp = "SELECT * FROM logpass";
$result_lp = $mysqli->query($rd_lp);
if (!$result_lp) {
	die('Сбой при доступе к базе данных: ' . $rd_lp . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$lp_list = '';
while ($lp_l = $result_lp->fetch_assoc()) {
	$lp_list[$lp_l['logpass_id']] = $lp_l['lp_fio'];
}
?>

<script language="javascript">

	history.pushState(null, null, location.href);
	window.onpopstate = function (event) {
		history.go(1);
	};

	function rash_but() {
		document.getElementById('new_rash_but').style.backgroundColor = 'yellow';
		document.getElementById('new_doh_but').style.backgroundColor = '';
		document.getElementById('new_shift_but').style.backgroundColor = '';

		document.getElementById('type1').value = 'rash';
		dr_sel();
	}

	function doh_but() {
		document.getElementById('new_rash_but').style.backgroundColor = '';
		document.getElementById('new_doh_but').style.backgroundColor = 'yellow';
		document.getElementById('new_shift_but').style.backgroundColor = '';

		document.getElementById('type1').value = 'doh';
		dr_sel();
	}

	function shift_but() {
		document.getElementById('new_rash_but').style.backgroundColor = '';
		document.getElementById('new_doh_but').style.backgroundColor = '';
		document.getElementById('new_shift_but').style.backgroundColor = 'yellow';

		document.getElementById('type1').value = 'shift';
		dr_sel();
	}


	function dr_sel() {
		if (document.getElementById('type1').value == 'rash' && document.getElementById('channel').value != 'bank') {
			document.getElementById('type2td').innerHTML = '<select form="new_rash" name="type2" id="type2" onchange="zp_show();"><option value="0">не выбрано</option><?php echo $ri_t1; ?></select>';
			document.getElementById('zp_name').value = "0";
			document.getElementById('zp_span').style.display = "none";
		}
		if (document.getElementById('type1').value == 'rash' && document.getElementById('channel').value == 'bank') {
			document.getElementById('type2td').innerHTML = '<select form="new_rash" name="type2" id="type2" onchange="zp_show();"><option value="0">не выбрано</option><?php echo $ri_t2; ?></select>';
			document.getElementById('zp_name').value = "0";
			document.getElementById('zp_span').style.display = "none";
		}
		if (document.getElementById('type1').value == 'doh') {
			document.getElementById('type2td').innerHTML = '<select form="new_rash" name="type2" id="type2" onchange="zp_show();"><option value="0">не выбрано</option><?php echo $rd_t1; ?></select>';
			document.getElementById('zp_name').value = "0";
			document.getElementById('zp_span').style.display = "none";
		}
		if (document.getElementById('type1').value == 'shift') {
			document.getElementById('type2td').innerHTML = '<select form="new_rash" name="type2" id="type2" onchange="zp_show();"><option value="0">не выбрано</option>	<option value="of1k1" style="background-color:#b1ebb1;">Литературная_22_1</option>	<option value="of1k2" style="background-color:#b1ebb1;">Литературная_22_2</option>	<option value="of2k1" style="background-color:#ffe400;">Уручье_1</option>	<option value="of2k2" style="background-color:#ffe400;">Уручье_2</option> 				<option value="of3k1" style="background-color:#b1ebb1;">Победителей_127_1</option>	<option value="of3k2" style="background-color:#b1ebb1;">Победителей_127_2</option>			<option value="of4k1" style="background-color:#b1ebb1;">Склад_1</option>	<option value="of4k2" style="background-color:#b1ebb1;">Склад_2</option>        <option value="curk1" style="background-color:#c6edf0;">Курьер_1</option><option value="curk2" style="background-color:#c6edf0;">Курьер_2</option><option value="bank">банк</option></select>';
		}
		document.getElementById('zp_name').value = "0";
		document.getElementById('zp_span').style.display = "none";
	}


	function new_rash_send() {

		valid = true;
		var output_t = '';

		// проверка клиента
		if (document.getElementById('channel').value == "0") {
			output_t += "выберите кассу, ";
			valid = false;
		}

		if (document.getElementById('type2').value == "0") {
			output_t += "выберите тип операции, ";
			valid = false;
		}

		if (document.getElementById('amount').value == "0" || document.getElementById('amount').value == "") {
			output_t += "заполните сумму, ";
			valid = false;
		}

		if ((document.getElementById('type2').value == "zpl" || document.getElementById('type2').value == "avans") && document.getElementById('zp_name').value == "0") {
			output_t += "выберите сотрудника (для зарплаты или аванса), ";
			valid = false;
		}

		if (document.getElementById('type2').value == "zpl" && document.getElementById('info').value == "") {
			output_t += "для зарплаты обязательно указывайте комментарий, ";
			valid = false;
		}


		var today_d = new Date();
		var pl_date = new Date(document.getElementById('acc_date').value);
		today_d.setHours(pl_date.getHours(), pl_date.getMinutes(), 0, 0);
		pl_date.setHours(pl_date.getHours(), pl_date.getMinutes(), 0, 0);
		console.log(today_d, pl_date);
		if (pl_date > today_d) {
			output_t += 'дата платежа не может быть в будущем, ';
			valid = false;
		}


		if (valid == false) {
			alert('Заполните все поля формы: ' + output_t);
		}

		return valid;


	}



	function rash_show() {
		//alert ('ok');
		if (document.getElementById('new_rash_tb').style.display == "none") {
			document.getElementById('new_rash_tb').style.display = "";
			document.getElementById('dr_buttons').style.display = "";
			document.getElementById('new_order_but').value = "отмена";
		}
		else {
			document.getElementById('new_rash_tb').style.display = "none";
			document.getElementById('dr_buttons').style.display = "none";
			document.getElementById('new_order_but').value = "внести расход";
		}
	}//end of dunction


	function zp_show() {
		//alert ('ok');
		if (document.getElementById('type2').value == "zpl" || document.getElementById('type2').value == "avans") {
			//alert ('ok1');
			document.getElementById('zp_span').style.display = "inline";
		}
		else {
			//alert ('ok2');
			document.getElementById('zp_span').style.display = "none";
			document.getElementById('zp_name').value = "0";
		}
	}//end of dunction

	function zp_name_show() {
		//alert ('ok');
		if (document.getElementById('type2_s').value == "zpl" || document.getElementById('type2_s').value == "avans") {
			//alert ('ok1');
			document.getElementById('zp_sel_span').style.display = "inline";
			document.getElementById('srch_form').submit();
		}
		else {
			//alert ('ok2');
			document.getElementById('zp_sel_span').style.display = "none";
			document.getElementById('zp_sel_s').value = "all";
			document.getElementById('srch_form').submit();
		}
	}//end of dunction


	function type1s_show() {
		document.getElementById('zp_sel_s').value = "all";
		document.getElementById('type2_s').value = "all";
		document.getElementById('srch_form').submit();

	}//end of dunction

</script>


<?php
echo '<form name="srch_form" method="post" id="srch_form" action="doh-rash.php">
Период c <input type="date" name="i_from_date" id="i_from_date" value="' . $i_from_date . '" ' . $dates_readonly . ' /> по <input type="date" name="i_to_date" id="i_to_date" value="' . $i_to_date . '" ' . $dates_readonly . ' /> <input type="submit" name="action" value="показать" onclick="" /><br />
Офис:		<select name="item_place" id="place_select" form="srch_form" style="display:inline-block; width:110px" onchange="document.getElementById(\'srch_form\').submit();">
		  		<option value="all" ' . sel_d($item_place, 'all') . '>все</option>
				<option value="1" ' . sel_d($item_place, '1') . '>Литературная_22</option>
				<option value="2" ' . sel_d($item_place, '2') . '>Ложинская</option>
				<option value="3" ' . sel_d($item_place, '3') . '>Победителей_127</option>
				<option value="4" ' . sel_d($item_place, '4') . '>Склад</option>
				<option value="cur" ' . sel_d($item_place, 'cur') . '>Курьер</option>
				' . (($_SESSION['user_id'] == '2' || $_SESSION['user_id'] == '3' || $_SESSION['user_id'] == '5' || $_SESSION['user_id'] == '9') ? '<option value="bank" ' . sel_d($item_place, 'bank') . '>Банк</option>' : '') . '


			</select>

</form>
';

if ($item_place == 'all' && ($_SESSION['user_id'] == '2' || $_SESSION['user_id'] == '3' || $_SESSION['user_id'] == '5' || $_SESSION['user_id'] == '9')) {
	$channels = '
			<option value="of1k1" style="background-color:#b1ebb1;">Литературная_22_1</option>
			<option value="of1k2" style="background-color:#b1ebb1;">Литературная_22_2</option>
			<option value="of2k1" style="background-color:#ffe400;">Уручье_1</option>
			<option value="of2k2" style="background-color:#ffe400;">Уручье_2</option>
			<option value="of3k1" style="background-color:#b1ebb1;">Победителей_127_1</option>
			<option value="of3k2" style="background-color:#b1ebb1;">Победителей_127_2</option>
			<option value="of4k1" style="background-color:#b1ebb1;">Склад_1</option>
			<option value="of4k2" style="background-color:#b1ebb1;">Склад_2</option>
			<option value="curk1" style="background-color:#c6edf0;">Курьер_1</option>
			<option value="curk2" style="background-color:#c6edf0;">Курьер_2</option>
			<option value="bank">банк</option>';
} elseif ($item_place == '1') {
	$channels = '
			<option value="of1k1" style="background-color:#b1ebb1;">Литературная_22_1</option>
			<option value="of1k2" style="background-color:#b1ebb1;">Литературная_22_2</option>
			<option value="curk1" style="background-color:#c6edf0;">Курьер_1</option>
			<option value="curk2" style="background-color:#c6edf0;">Курьер_2</option>';
} elseif ($item_place == '2') {
	$channels = '
			<option value="of2k1" style="background-color:#ffe400;">Уручье_1</option>
			<option value="of2k2" style="background-color:#ffe400;">Уручье_2</option>
			<option value="curk1" style="background-color:#c6edf0;">Курьер_1</option>
			<option value="curk2" style="background-color:#c6edf0;">Курьер_2</option>';
} elseif ($item_place == '3') {
	$channels = '
			<option value="of3k1" style="background-color:#ffe400;">Победителей_127_1</option>
			<option value="of3k2" style="background-color:#ffe400;">Победителей_127_2</option>
			<option value="curk1" style="background-color:#c6edf0;">Курьер_1</option>
			<option value="curk2" style="background-color:#c6edf0;">Курьер_2</option>';
} elseif ($item_place == '4') {
	$channels = '
			<option value="of4k1" style="background-color:#ffe400;">Склад_1</option>
			<option value="of4k2" style="background-color:#ffe400;">Склад_2</option>
			<option value="curk1" style="background-color:#c6edf0;">Курьер_1</option>
			<option value="curk2" style="background-color:#c6edf0;">Курьер_2</option>';
} elseif ($item_place == 'cur') {
	$channels = '
			<option value="curk1" style="background-color:#c6edf0;">Курьер_1</option>
			<option value="curk2" style="background-color:#c6edf0;">Курьер_2</option>';
} elseif ($item_place == 'bank') {
	$channels = '
			<option value="bank">банк</option>';
} else {
	$channels = '
			<option value="of1k1" style="background-color:#b1ebb1;">Литературная_22_1</option>
			<option value="of1k2" style="background-color:#b1ebb1;">Литературная_22_2</option>
			<option value="of2k1" style="background-color:#ffe400;">Уручье_1</option>
			<option value="of2k2" style="background-color:#ffe400;">Уручье_2</option>
			<option value="of3k1" style="background-color:#b1ebb1;">Победителей_127_1</option>
			<option value="of3k2" style="background-color:#b1ebb1;">Победителей_127_2</option>
			<option value="of4k1" style="background-color:#b1ebb1;">Склад_1</option>
			<option value="of4k2" style="background-color:#b1ebb1;">Склад_2</option>
			<option value="curk1" style="background-color:#c6edf0;">Курьер_1</option>
			<option value="curk2" style="background-color:#c6edf0;">Курьер_2</option>
';
}



//формируем список для фильтра по type1
$rash_select = '';
$rl_q = "SELECT * FROM rash_items ORDER BY ri_order";
$result_rl = $mysqli->query($rl_q);
if (!$result_rl) {
	die('Сбой при доступе к базе данных: ' . $rl_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
while ($rl = $result_rl->fetch_assoc()) {
	$rash_select .= '<option value="' . $rl['ri_code'] . '" ' . sel_d($rl['ri_code'], $type2_s) . '>' . $rl['ri_text'] . '</option>';
}

//формируем список людей для зарплаты
$zp_select = '';
$zp_select_s = '';
$zp_q = "SELECT * FROM logpass WHERE zp_yn='1' AND active='1' ORDER BY lp_fio";
$result_zp = $mysqli->query($zp_q);
if (!$result_zp) {
	die('Сбой при доступе к базе данных: ' . $zp_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
while ($zp = $result_zp->fetch_assoc()) {
	$zp_select .= '<option value="' . $zp['logpass_id'] . '">' . $zp['lp_fio'] . '</option>';
	$zp_select_s .= '<option value="' . $zp['logpass_id'] . '" ' . sel_d($zp['logpass_id'], $zp_sel_s) . '>' . $zp['lp_fio'] . '</option>';
}

$from_date = strtotime($i_from_date);
$to_date = strtotime($i_to_date);

//выборка информации по доходам-расходам
$dr_q = "SELECT * FROM doh_rash WHERE (acc_date BETWEEN '" . $from_date . "' AND '" . $to_date . "')$srch ORDER BY acc_date DESC";
$result_dr = $mysqli->query($dr_q);
if (!$result_dr) {
	die('Сбой при доступе к базе данных: ' . $dr_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
/*
 * onclick="rash_show(); return false;"
 *
 * */

echo '
<table border="1" cellspacing="0">
	<tr>
		<th style="width:80px;">дата</th>
		<th style="width:80px;">касса
			<select name="kassa_s" id="kassa_s" form="srch_form" onchange="document.getElementById(\'srch_form\').submit();">
				<option value="all" ' . sel_d($kassa_s, 'all') . '>все</option>
				<option value="k1" ' . sel_d($kassa_s, 'k1') . '>Касса 1</option>
				<option value="k2" ' . sel_d($kassa_s, 'k2') . '>Касса 2</option>
			</select>

								</th>
		<th style="width:50px;">сумма</th>
		<th style="width:150px;">тип<br />

			<select name="type1_s" id="type1_s" form="srch_form" style="width:50px;" onchange="type1s_show();">
				<option value="all" ' . sel_d($type1_s, 'all') . '>все</option>
				<option value="doh" ' . sel_d($type1_s, 'doh') . '>Доходы</option>
				<option value="rash" ' . sel_d($type1_s, 'rash') . '>Расходы</option>
				<option value="shift" ' . sel_d($type1_s, 'shift') . '>Переводы</option>
			</select>

			<select name="type2_s" id="type2_s" form="srch_form" onchange="zp_name_show();" style="width:80px;">
				' . $t2_select . '
			</select>
			<span id="zp_sel_span" ' . (($type2_s == 'zpl' || $type2_s == 'avans') ? '' : 'style="display:none;"') . '><select form="srch_form" name="zp_sel_s" id="zp_sel_s" style="width:90px;" onchange="document.getElementById(\'srch_form\').submit();">
				<option value="all">все сотрудники</option>
				' . $zp_select_s . '
			</select></span>
						</th>
		<th style="width:200px;">информация
						<div id="dr_buttons" style="position:relative; display:none;">
							<input type="hidden" form="new_rash" name="type1" id="type1" value="rash" />
							<input type="button" style="position:absolute; top:-60px; left:-110px; height:40px; width:100px; background-color:yellow" value="расход" id="new_rash_but" onclick="rash_but(); return false;">
							<input type="button" style="position:absolute; top:-60px; left:0px; height:40px; width:100px;" value="доход" id="new_doh_but" onclick="doh_but(); return false;">
							<input type="button" style="position:absolute; top:-60px; left:110px; height:40px; width:100px;" value="сдача в кассу" id="new_shift_but" onclick="shift_but(); return false;">

							</div>

						</th>
		<th style="width:100px;">ихто?</th>
		<th>действия
						<div style="position:relative"><input type="button" style="position:absolute; top:-70px; left:0px; height:50px; width:100px;" value="внести расход" id="new_order_but" onclick="rash_show(); return false;"></div>
						</th>

	</tr>
	<tbody id="new_rash_tb" style="display:none;">
	<tr>
		<td> <form name="new_rash" action="doh-rash.php" method="post" id="new_rash"></form>
			<input form="new_rash" type="date" name="acc_date" id="acc_date" value="' . date("Y-m-d") . '" /></td>
		<td>

			<select form="new_rash" name="channel" id="channel" onchange="dr_sel();">
				<option value="0">не выбрано</option>
				' . $channels . '
			</select>
			</td>
		<td><input form="new_rash" type="number" step="0.01" name="amount" id="amount" value="" style="width:50px;" />т.руб.</td>
		<td>
			<span id="type2td"><select form="new_rash" name="type2" id="type2" onchange="zp_show();">
				<option value="0">не выбрано</option>
				' . $ri_t1 . '
			</select>
			</span>
			<span id="zp_span" style="display:none;">кому:<select form="new_rash" name="zp_name" id="zp_name" style="width:90px;">
				<option value="0">не выбрано</option>
				' . $zp_select . '
			</select></span>

			</td>
		<td><textarea form="new_rash" cols="40" rows="3" name="info" id="info"></textarea></td>
		<td></td>
		<td>
				<input form="new_rash" type="hidden" name="i_from_date" value="' . $i_from_date . '" /><input form="new_rash" type="hidden" name="i_to_date" value="' . $i_to_date . '" />
				<input form="new_rash" type="hidden" name="item_place" value="' . $item_place . '" />

			<input form="new_rash" type="submit" name="action" value="сохранить" onclick="return new_rash_send();" /></td>

	</tr>
	</tbody>
';

$total_am = 0;
while ($dr = $result_dr->fetch_assoc()) {
	$total_am += $dr['amount'];
	echo '
	<tr>
		<td>' . date("d.m.Y", $dr['acc_date']) . (\bb\models\User::getCurrentUser()->isDima() ? '<br><span style="font-size: 10px">[' . $dr['dr_id'] . ']</span>' : '') . '</td>
		<td>' . of_print($dr['channel']) . kassa_print($dr['kassa']) . '</td>
		<td style="text-align:right">' . number_format($dr['amount'], 2, ',', ' ') . '</td>
		<td style="position: relative;" data-type2="' . $dr['type2'] . '" data-salary_user_id="' . $dr['dr_name_id'] . '">' . ($dr['amount'] < 0 ? $rash[$dr['type2']] : $doh[$dr['type2']]) . ($dr['dr_name_id'] > 0 ? '<br />' . \bb\models\User::GetUserName($dr['dr_name_id']) : '') . '
          <input type="button" style="position: absolute; top: 0; right: 0;" class="edit-btn-show ' . ((\bb\models\User::getCurrentUser()->isOwner() && $dr['type1'] == 'rash') ? '' : 'hide') . '" value="i">
          <form method="post" class="hide" id="update_form_' . $dr['dr_id'] . '">
            <select name="type2" id="type2" class="type2_update">
				        <option value="0">не выбрано</option>
				        ' . $ri_t1 . '
			      </select>
			      <select name="zp_name" style="width:90px;" class="zp_name_id_update ' . ($dr['type2'] == 'zpl' ? '' : 'hide') . '">
				        <option value="0">не выбрано</option>
				        ' . $zp_select . '
			      </select>
			      <input type="hidden" name="dr_id" value="' . $dr['dr_id'] . '" />
            <input type="hidden" name="i_from_date" value="' . $i_from_date . '" />
            <input type="hidden" name="i_to_date" value="' . $i_to_date . '" />
				    <input type="hidden" name="item_place" value="' . $item_place . '" />
				    <input type="hidden" name="kassa_s" value="' . $kassa_s . '" />
				    <input type="hidden" name="type1_s" value="' . $type1_s . '" />
				    <input type="hidden" name="type2_s" value="' . $type2_s . '" />
				    <input type="hidden" name="type2_s" value="' . $type2_s . '" />
            <input type="hidden" name="action" value="update_rash">

			      <button class="correct-btn">исправить</button>



          </form>
		    </td>
		<td>' . $dr['info'] . '
		    <textarea form="update_form_' . $dr['dr_id'] . '" name="info_upd" class="info_upd hide">' . $dr['info'] . '</textarea>
		    </td>
		<td>' . user_name($dr['cr_who_id']) . ' (' . date("H:i", $dr['cr_time']) . ')</td>
		<td>
			<form name="del_form_' . $dr['dr_id'] . '" method="post" id="del_form_' . $dr['dr_id'] . '" action="doh-rash.php">
				<input type="hidden" name="dr_id" value="' . $dr['dr_id'] . '" />
				<input type="hidden" name="dr_id_link" value="' . $dr['link_to'] . '" />
				<input type="hidden" name="i_from_date" value="' . $i_from_date . '" /><input type="hidden" name="i_to_date" value="' . $i_to_date . '" />
				<input type="hidden" name="item_place" value="' . $item_place . '" />
				<input type="hidden" name="kassa_s" value="' . $kassa_s . '" />
				<input type="hidden" name="type1_s" value="' . $type1_s . '" />
				<input type="hidden" name="type2_s" value="' . $type2_s . '" />
				<input type="submit" ' . ((in_array($_SESSION['user_id'], $in_del)) ? '' : 'style="display:none;"') . ' name="action" value="удалить" onclick="return confirm(\'Вы точно хотите удалить эту операцию?\');" />
			</form>
			</td>

	</tr>
						';
}


echo '
<tr>
		<td><strong>Итого:</strong></td>
		<td></td>
		<td style="text-align:right"><strong>' . number_format($total_am, 2, ',', ' ') . '</strong></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
	</tr>

</table>

';
?>

<script>
	document.querySelectorAll('.edit-btn-show').forEach((el) => {
		el.addEventListener('click', showHideEditFunctionality);
	});

	document.querySelectorAll('.correct-btn').forEach((el) => {
		el.addEventListener('click', correctSubmit);
	});

	document.querySelectorAll('.type2_update').forEach((el) => {
		el.addEventListener('change', updateType2Change);
	});


	//update rashod functionality
	function showHideEditFunctionality(e) {
		let btn = e.target;
		let td = e.target.closest('td');
		let form = e.target.closest('td').querySelector('form');
		let infoTextArea = e.target.closest('tr').querySelector('.info_upd');
		let type2 = td.dataset.type2;
		let salaryUserId = td.dataset.salary_user_id;
		let selectRash = td.querySelector('.type2_update');
		let selectZpId = td.querySelector('.zp_name_id_update');

		if (btn.value == 'i') {
			btn.value = 'x';
			form.classList.remove('hide');
			infoTextArea.classList.remove('hide');
			selectRash.value = type2;
			if (type2 == 'zpl') {
				selectZpId.value = salaryUserId;
				selectZpId.classList.remove('hide');
			}
			else {
				selectZpId.classList.add('hide');
			}
		}
		else {
			btn.value = 'i';
			form.classList.add('hide');
			infoTextArea.classList.add('hide');
		}

	}

	function updateType2Change(e) {
		let td = e.target.closest('td');
		let selectRash = td.querySelector('.type2_update');
		let selectZpId = td.querySelector('.zp_name_id_update');

		if (selectRash.value == 'zpl') {
			selectZpId.classList.remove('hide');
		}
		else {
			selectZpId.classList.add('hide');
		}

	}

	function correctSubmit(e) {
		e.preventDefault();
		let rez = true;
		let message = '';
		let td = e.target.closest('td');
		let form = e.target.closest('td').querySelector('form');
		let selectRash = td.querySelector('.type2_update');
		let selectZpId = td.querySelector('.zp_name_id_update');

		if (selectRash.value == 0 || selectRash.value == '') {
			rez = false;
			message += 'Выберите новый тип расхода, ';
		}
		if (selectRash.value == 'zpl' && (selectZpId.value == 0 || (selectZpId.value == ''))) {
			rez = false;
			message += 'Выберите сотрудника по зарплате, ';
		}

		if (rez) {
			form.submit();
		}
		else {
			alert(message);
		}
	}

</script>

<?php



function get_post($var)
{
	global $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}

function of_print($of)
{

	switch ($of) {
		case '1':
			$output = 'Литературная_22_';
			break;

		case '2':
			$output = 'Ложинская_5_';
			break;

		case '3':
			$output = 'Победителей_127_';
			break;

		case '4':
			$output = 'Склад_';
			break;

		case 'cur':
			$output = 'Курьер_';
			break;

		case 'bank':
			$output = 'Банк';
			break;


		default:
			$output = 'Нет';
			break;
	}

	return $output;

}

function kassa_print($of)
{

	switch ($of) {
		case 'k1':
			$output = '1';
			break;

		case 'k2':
			$output = '2';
			break;

		case 'bank':
			$output = '';
			break;


		default:
			$output = 'Нет';
			break;
	}

	return $output;

}

function dr_print($of)
{

	switch ($of) {
		case 'doh':
			$output = 'доход';
			break;

		case 'rash':
			$output = 'расход';
			break;

		case 'shift_minus':
			$output = 'перевод в:';
			break;

		case 'shift_plus':
			$output = 'поступл. из:';
			break;


		default:
			$output = 'Нет';
			break;
	}

	return $output;

}

function sel_d($value, $pattern)
{
	if ($value == $pattern) {
		return 'selected="selected"';
	} else {
		return '';
	}
}

function user_name($id)
{
	return \bb\models\User::getUserById($id)->getShortName();
}

?>