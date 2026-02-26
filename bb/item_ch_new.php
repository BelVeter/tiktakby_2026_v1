<?php
//ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
//ini_set('display_startup_errors', 1);

use bb\Base;
use bb\classes\Deal;
use bb\tovar;

session_start();
//error_reporting(E_ALL ^ E_DEPRECATED);


//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Signature.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/tovar.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Deal.php'); // включаем класс
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php'); // включаем класс
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/tovar.php'); // включаем класс
require($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');


//------- proverka paroley

isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941) {
	die('
	<form action="index.php" method="post">
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>');
}

//-----------proverka paroley
//$_POST['action']='delivery_date_change';
//$_POST['deal_id']=116757;
//$_POST['new_date']='2023-09-02';

if (isset($_POST['action']) && $_POST['action'] == 'delivery_date_change') {
	// Set the header to indicate JSON content
	header('Content-Type: application/json');

	// Initialize response array
	$response = ['success' => false, 'message' => 'Invalid request.'];

	// Check if the expected POST parameters are set
	if (isset($_POST['deal_id'], $_POST['new_date'])) {

		// Retrieve the parameters (sanitize/validate these in a real application!)
		$dealId = $_POST['deal_id'];
		$newDateString = $_POST['new_date'];

		// --- Your PHP Logic Here ---
		// Process the parameters, interact with database, etc.
		// For this example, let's just simulate success based on param1

		$is_successful = false;
		$message = '';

		try {
			// Example processing: succeed if param1 is not empty
			if ($dealId && $newDateString && $dealId > 0) {

				$newDate = new DateTime($newDateString);
				$newDate->setTime(0, 0, 0);

				$rez = Deal::changeDeliveryDate($dealId, $newDate);

				// Simulate a successful operation
				if ($rez) {
					$is_successful = true;
					$message = "Дата доставки успешно изменена";
				} else {
					$is_successful = false;
					$message = "Дата доставки не изменена. Deal вернуло false";
				}

			} else {
				// Simulate a failure condition
				$is_successful = false;
				$message = "Processing failed: Parameters cannot be empty.";
			}

			$response['success'] = $is_successful;
			$response['message'] = $message;

		} catch (Exception $e) {
			// Catch any exceptions during processing
			$response['success'] = false;
			$response['message'] = 'Server error during processing: ' . $e->getMessage();
			// Log the detailed error for debugging (don't expose details to the client)
			error_log('PHP Processing Error: ' . $e->getMessage());
		}

	} else {
		$response['message'] = 'Missing required parameters.';
	}

	// Encode the response array as JSON and output it
	echo json_encode($response);
	exit; // Terminate script execution
}



$start_code = '

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	</head>
	<body>


		';



$item_inv_n = '';

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

//new delivery date



//$item_inv_n=719197;
//$action_type='select';
//echo $start_code;
//$client_id=27179;

$inv_d_srch = $item_inv_n;

if ($action_type == 'past_due_calc') {// обязательно здесь, т.к. заканчивается дайем и потом ничего выводить не нужно

	$ret_date = strtotime($ret_date);

	$pas_due_amount = pay_calc($deal_id, $ret_date);
	$pas_due_amount < 0 ? $pas_due_amount2 = number_format($pas_due_amount, 2, ',', ' ') : $pas_due_amount2 = 0;
	$pas_due_amount < 0 ? $pas_due_amount = number_format($pas_due_amount, 2, ',', ' ') : $pas_due_amount = number_format($pas_due_amount, 2, ',', ' ');

	$item_output = '
				document.getElementById(\'past_due_word\').innerHTML=\'' . $pas_due_amount . '\';
				document.getElementById(\'to_pay_pastdue\').value=\'' . $pas_due_amount2 . '\';';


	$item_output = str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет
	die($item_output);

}//end of if

$mysqli = \bb\Db::getInstance()->getConnection();

if ($action_type == 'arch_hist') {// обязательно здесь, т.к. заканчивается дайем и потом ничего выводить не нужно
	$query_dl_def = "SELECT * FROM rent_deals_arch WHERE client_id='$client_id'";
	$result_dl_def = $mysqli->query($query_dl_def);
	if (!$result_dl_def) {
		die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	}


	$item_output = '
		</br>
		<table border="1" cellspacing="0">
		  	<tr>
			    <th scope="col">товар</th>
			    <th scope="col">с</th>
			    <th scope="col">по</th>
		    	<th scope="col">сумма</th>
				<th scope="col">действия</th>
		  	</tr>';
	while ($dl_def = $result_dl_def->fetch_assoc()) {
		/**
		 * @var tovar
		 */
		$tov = tovar::getTovar($dl_def['item_inv_n']);

		$query = "SELECT * FROM tovar_rent_items WHERE item_inv_n='" . $dl_def['item_inv_n'] . "'";
		$result = $mysqli->query($query);
		if (!$result)
			die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		$item = $result->fetch_assoc();

		$query_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='" . $item['model_id'] . "'";
		$result_model = $mysqli->query($query_model);
		if (!$result_model)
			die('Сбой при доступе к базе данных: ' . $query_model . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		$model = $result_model->fetch_assoc();

		$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='" . $model['tovar_rent_cat_id'] . "'";
		$result_cat = $mysqli->query($query_cat);
		if (!$result_cat)
			die('Сбой при доступе к базе данных: ' . $result_cat . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		$cat = $result_cat->fetch_assoc();

		$tov->model_color == '0' ? ($color = '') : ($color = ', цвет: ' . $tov->model_color . ': ' . $tov->item_color); // если цвет отсутствует - то ничего не выводим, иначе выводим цвет

		$contractName = ($model['model_addr'] != '') ? $model['model_addr'] : $cat['dog_name'];
		$item_output .= '
			<tr>
				<td><strong>№' . inv_print($dl_def['item_inv_n']) . '</strong> ' . addslashes($contractName) . ' ' . addslashes($tov->producer) . ', модель: ' . addslashes($tov->model_name) . $color . '</td>
				<td>' . date("d.m.Y", $dl_def['start_date']) . '</td>
				<td>' . date("d.m.Y", $dl_def['return_date']) . '</td>
				<td>' . number_format($dl_def['r_to_pay'], 2, ',', ' ') . '</td>
				<td><input type="button" value="показать историю" onclick="hist_a_show(\\\'' . $dl_def['deal_id'] . '\\\');" /></td>
			</tr>';
	}

	$item_output .= '</table>
	<div id="a_hist_div"></div>
			';

	$item_output = str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет

	die('
			ch_result=\'ok\';
			document.getElementById(\'arch_hist_div\').innerHTML=\'' . $item_output . '\';

			');

}//end of arch_hist if

$mysqli = \bb\Db::getInstance()->getConnection();

//делаем поиск по номеру договора
//$trtr=mb_substr($item_inv_n, 0, 1, 'UTF-8');
if (mb_substr($item_inv_n, 0, 1, 'UTF-8') == 'д' || mb_substr($item_inv_n, 0, 1, 'UTF-8') == 'Д') {
	$dog_srch_n = mb_substr($item_inv_n, 1, 1000, 'UTF-8');

	$query_d_num = "SELECT * FROM rent_deals_act WHERE deal_id='$dog_srch_n'";
	$result_d_num = $mysqli->query($query_d_num);
	if (!$result_d_num)
		die('Сбой при доступе к базе данных: ' . $query_d_num . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$dog_num_def = $result_d_num->fetch_assoc();
	$item_inv_n = $dog_num_def['item_inv_n'];

}


$item_cat_n = substr($item_inv_n, 0, 3);

$query = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$item_inv_n'";
$result = $mysqli->query($query);
if (!$result)
	die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

$item_rows = $result->num_rows;

if ($item_rows == 1) {

	$item = $result->fetch_assoc();

	$query_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='" . $item['model_id'] . "'";
	$result_model = $mysqli->query($query_model);
	if (!$result_model)
		die('Сбой при доступе к базе данных: ' . $query_model . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$model = $result_model->fetch_assoc();

	$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='" . $model['tovar_rent_cat_id'] . "'";
	$result_cat = $mysqli->query($query_cat);
	if (!$result_cat)
		die('Сбой при доступе к базе данных: ' . $query_cat . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$cat = $result_cat->fetch_assoc();


	$query_tarif = "SELECT * FROM rent_tarif_act WHERE model_id='" . $item['model_id'] . "' ORDER BY sort_num, kol_vo";
	$result_tarif = $mysqli->query($query_tarif);
	if (!$result_tarif)
		die('Сбой при доступе к базе данных: ' . $query_tarif . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$tarif_rows = $result_tarif->num_rows;


	$model['color'] == '0' ? ($color = '') : ($color = ', цвет: ' . $model['color'] . ': ' . $item['item_color']); // если цвет отсутствует - то ничего не выводим, иначе выводим цвет



	if ($tarif_rows > 0) {

		// выборка действующих тарифов
		$tarif_idx = 0;
		$tarif_code = '
			<style>.tt-row:hover{background:#bbdefb!important;}</style>
			<table border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:13px;width:auto;">
				  <tr style="background:#455a64;color:#fff;">
				    <th style="padding:4px 10px;text-align:left;">Сумма</th>
				    <th style="padding:4px 10px;text-align:left;">Период</th>
				    <th style="padding:4px 10px;text-align:left;">За шаг</th>
				  </tr>';

		while ($tarif = $result_tarif->fetch_assoc()) {
			$bg = ($tarif_idx % 2 == 0) ? '#f5f5f5' : '#e8eaf6';
			$tarif_code = $tarif_code . '
				  <tr class="tt-row" style="cursor:pointer;background:' . $bg . ';" onclick="apply_tarif(\\\'' . $tarif['tarif_id'] . '\\\'); this.style.background=\\\'#a5d6a7\\\'; return false;">
				    <td style="padding:3px 10px;white-space:nowrap;">' . $tarif['rent_amount'] . ' р.<input type="hidden" value="' . $tarif['rent_amount'] . '" id="rent_amount_' . $tarif['tarif_id'] . '" /></td>
				   	<td style="padding:3px 10px;white-space:nowrap;">' . $tarif['kol_vo'] . ' ' . tenor_print($tarif['step'], $tarif['kol_vo']) . '<input type="hidden" value="' . $tarif['kol_vo'] . '" id="kol_vo_' . $tarif['tarif_id'] . '" /><input type="hidden" value="' . $tarif['kol_vo_min'] . '" id="kol_vo_min_' . $tarif['tarif_id'] . '" /><input type="hidden" value="' . $tarif['step'] . '" id="step_' . $tarif['tarif_id'] . '" /></td>
				   	<td style="padding:3px 10px;white-space:nowrap;">=' . $tarif['rent_per_step'] . ' р./' . tenor_print($tarif['step'], 'd') . '<input type="hidden" value="' . $tarif['rent_per_step'] . '" id="rent_per_step_' . $tarif['tarif_id'] . '" /><input type="hidden" class="tarif" data-days="' . ($tarif['sort_num'] * $tarif['kol_vo']) . '" value="' . ($tarif['rent_amount']) . '"></td>
				  </tr>';
			$tarif_idx++;
		}

		$tarif_code = $tarif_code . '</table>

';
	} else {
		$tarif_code = '<p style="font-weight:bold; font-size:18px; color:#F00;">Для данного товара тарифы еще не введены. Кристина должна ввести тариф!</p>';
	}


	// действия по типу
	if ($action_type == 'select') {
		$save_dis = 'document.getElementById(\'action_save\').style.display="";';

		if (($item['status'] == 'to_rent' || $item['status'] == 'bron' || ($item['status'] == 't_bron' && $item['br_time'] < time()))) {
			$item_output = '';
			$br_text = '';
			$today = date("Y-m-d");

			if ($item['item_place'] != $_SESSION['office']) {
				$item_output .= '<div style="background-color:yellow; height:100px;"><font style="color:red; font-size:26px;">Товар НЕ на Вашем офисе!!! Можно только оформлять для курьера!!!</font></div>';
				$save_dis = 'document.getElementById(\'action_save\').style.display="none";';
			}
			if ($item['status'] == 'bron') {
				$query_or = "SELECT * FROM rent_orders WHERE inv_n='" . $item['item_inv_n'] . "'";
				$result_or = $mysqli->query($query_or);
				if (!$result_or)
					die('Сбой при доступе к базе данных: ' . $query_or . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				$ord = $result_or->fetch_assoc();

				$new_info = $ord['family'] . ' ' . $ord['name'] . ' ' . $ord['otch'] . ', тел.:' . phone_print($ord['phone']) . ($ord['address'] != '' ? ', адрес доставки: ' . $ord['address'] : '') . '. ' . $ord['info'];

				$new_info = str_replace("'", "", $new_info);
				$new_info = str_replace('"', "", $new_info);

				$br_text = '<strong>Внимание, на товар оформлена бронь:</strong><br />
							Срок действия: ' . date("d.m.y", $ord['validity']) . '<br />
							Инфо: ' . good_print($new_info) . '<br />
							<input name="bron_cr_id" type="hidden" value="' . $ord['ch_who_id'] . '" />
							';
			}

			$item_output .= '<span style="color:red; font-size:18px;">' . $br_text . '</span>
				<table border="1" cellspacing="0">
				  <tr>
				    <td>Товар:</td>
					<td>' . addslashes($model['model_addr'] != '' ? $model['model_addr'] : $cat['dog_name']) . ' ' . addslashes($model['producer']) . ', модель: ' . addslashes($model['model']) . $color . '</td>
				  </tr>

				  <tr>
				    <td>Комплектация:</td>
					<td><input name="deal_item_set" type="text" size="80" value="' . good_print($model['set']) . '" />
					    <span>Комплектация проверена: </span><input type="checkbox" style="width: 24px; height: 24px;" id="set_is_checked">
					    </td>
				  </tr>

				  <tr>
				    <td>Оценочная стоимость:</td>
					<td>' . $model['agr_price'] . ' ' . $model['agr_price_cur'] . '</td>
				  </tr>
				  <tr>
				    <td>Местонахождение:</td>
					<td>Офис: ' . $item['item_place'] . '</td>
				  </tr>
				</table>
';

			if (\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) {
				$query_kb = "SELECT * FROM karn_brons WHERE inv_n='" . $item['item_inv_n'] . "' AND t_from>='" . (time() - 24 * 3600) . "' ORDER BY t_from";
				$result_kb = $mysqli->query($query_kb);
				if (!$result_kb)
					die('Сбой при доступе к базе данных: ' . $query_kb . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

				$item_output .= '<br />
			<table border="1" cellspacing="0">
			  <tr>
				<td>номер брони</td>
				<td>с</td>
				<td>по</td>
				<td>договор</td>
				<td>статус</td>
				<td>фио</td>
				<td>тел1</td>
				<td>тел2</td>
				<td>почта</td>
				<td>доп.инфо</td>
				<td>действия</td>
			  </tr>
			';

				while ($kb = $result_kb->fetch_assoc()) {
					$kb['t_to'] += 1;
					$item_output .= '
			<tr>
				<td>' . $kb['br_num'] . '<br /><i>' . date("d.m (H:i)", $kb['cr_time']) . '</i></td>
				<td>' . date("(H:i) d.m.y", $kb['t_from']) . '<br />' . rus_day(date("w", $kb['t_from'])) . '
					<input type="hidden" id="br_d_from_' . $kb['kb_id'] . '" value="' . date("Y-m-d", $kb['t_from']) . '" />
					<input type="hidden" id="br_h_from_' . $kb['kb_id'] . '" value="' . date("H", $kb['t_from']) . '" />
						</td>
				<td>' . date("(H:i) d.m.y", $kb['t_to']) . '<br />' . rus_day(date("w", $kb['t_to'])) . '
					<input type="hidden" id="br_d_to_' . $kb['kb_id'] . '" value="' . date("Y-m-d", $kb['t_to']) . '" />
					<input type="hidden" id="br_h_to_' . $kb['kb_id'] . '" value="' . date("H", $kb['t_to']) . '" />
						</td>
				<td>договор</td>
				<td>' . stat_print($kb['status']) . '<br />' . ($kb['appr_time'] > 0 ? date("d.m (H:i)", $kb['appr_time']) . '<br />' . user_name($kb['appr_who']) : '') . '</td>
				<td>' . str_replace("'", "", $kb['fio']) . '</td>
				<td>' . phone_print($kb['phone1']) . '</td>
				<td>' . phone_print($kb['phone2']) . '</td>
				<td>' . $kb['mail'] . '</td>
				<td>' . str_replace("'", "", $kb['info']) . '</td>
				<td><input type="button" value="выбрать бронь" onclick="br_select(\\\'' . $kb['kb_id'] . '\\\'); return false;" /></td>
			</tr>
				';

				}//end of while
				$item_output .= '</table><br />
				';

			}

			$nv_today = "2013-07-01";

			$item_output .= '
Дата договора/выдачи:<input type="date" name="start_date" id="start_date" value="' . ($_SESSION['user_id'] == 17 ? $nv_today : $today) . '" ' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? '' : '') . ' onchange="daysChange();" />
		<input type="hidden" name="br_reg" id="br_reg" value="0" />
		<select name="takeaway_status" id="now_later" onchange="takeaway_show();">
			<option value="now">выдаем сейчас</option>
        	<option value="later">выдаем потом</option>
		</select>

		<span id="future_takeaway" style="display:none;">, плановая дата выдачи:<input type="date" name="takeaway_date" id="takeaway_date" value="' . $today . '" ' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? 'readonly="readonly"' : '') . ' /></span>

		' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? '
	в <input type="text" name="br_hour_from" id="br_hour_from" readonly="readonly" style="width:25px;">
        <sup>00</sup>
		' : '') . '

		<br />

' . $tarif_code . '

<div style="display:flex;align-items:flex-start;gap:18px;margin-top:4px;">
<div style="font-size:13px;">
  <div style="margin-bottom:4px;">Тариф: <input type="number" step="0.01" name="rent_tarif" id="rent_tarif" value="" readonly style="background-color:#cacaca;width:70px;" /> бел. руб.
    <input type="hidden" name="tarif_id" id="tarif_id" value="" />
    <select name="step" id="step">
      <option value="day" selected="selected">в день</option>
      <option value="week">в неделю</option>
      <option value="month">в месяц</option>
    </select>
  </div>
  <div style="margin-bottom:4px;">Кол-во (д/н/м): <input type="number" step="any" name="rent_tenor" id="rent_tenor" onchange="daysChange();" onkeydown="return event.key != \\\'Enter\\\';" value="" style="width:60px;" ' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? 'readonly="readonly"' : '') . ' />
    &nbsp;&nbsp; Скидка: <input min="0" max="100" step="5" style="width:50px;font-size:14px;background-color:orange;text-align:center;border-radius:4px;border:1px solid #ccc;" type="number" name="discount" id="discount" value="0" onchange="calculateNew();"> %
  </div>
  <div><input type="button" value="пересчитать" id="calc_button" onclick="calculateNew(); return false;" style="padding:4px 12px;cursor:pointer;" /></div>
</div>
</div>

Стоимость аренды:<input type="number" step="0.01" name="r_to_pay" id="r_to_pay" size="10" value="" ' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? 'readonly="readonly"' : '') . ' />бел. руб.

Дата возврата:<input type="date" name="return_date" id="return_date" onchange="dateChange();" value="" ' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? 'readonly="readonly"' : '') . ' />
' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? '
	в <input type="text" name="br_hour_to" id="br_hour_to" style="width:25px" readonly="readonly" />
  <sup>00</sup>
		' : '') . '

' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>Для карнавальных костюмов:</b>
залог: <input type="number" step="0.01" name="coll_amount" value="" size="5" />
<select name="coll_cur" id="coll_cur_id">
		    	<option value="TBYR">бел.руб.</option>
                <option value="USD">USD</option>
		    	<option value="EUR">EUR</option>
		    	<option value="RUB">рос. руб.</option>
</select>' : '') . '
<br />

Дополнительная информация по сделке:<br/> <textarea cols="100" rows="3" name="deal_info" id="deal_info"></textarea><br />

		<input type="hidden" name="del_to_pay" id="del_to_pay" value="" />


	';



			$item_output = str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет

			echo '
			ch_result=\'ok\';
			document.getElementById(\'deal_div\').innerHTML=\'' . $item_output . '\';
			document.getElementById(\'action_save\').value=\'сохранить\';
			document.getElementById(\'action_delivery\').value=\'сохранить для курьера\';
			document.getElementById(\'main_buttons\').style.display="";
			document.getElementById(\'print_buttons\').style.display="none";
			document.getElementById(\'deal_area\').style.backgroundColor = \'#AFDC7E\';
			' . $save_dis . '
			';


		} elseif ((($item['status'] == 'to_rent' || $item['status'] == 'to_deliver' || $item['status'] == 'bron' || ($item['status'] == 't_bron' && $item['br_time'] < time())) && $item['item_place'] != $_SESSION['office']) || $item['status'] == 'on_move') {
			$br_text = '';
			$today = date("Y-m-d");


			$item_output = '<font style="color:red; font-size:18px;">Товар НЕ на Вашем офисе!!!</font>
				<table border="1" cellspacing="0">
				  <tr>
				    <td>Товар:</td>
					<td>' . addslashes($model['model_addr'] != '' ? $model['model_addr'] : $cat['dog_name']) . ' ' . addslashes($model['producer']) . ', модель: ' . addslashes($model['model']) . $color . '</td>
				  </tr>

				  <tr>
				    <td>Комплектация:</td>
					<td><input name="deal_item_set" type="text" size="80" value="' . good_print($model['set']) . '" /></td>
				  </tr>

				  <tr>
				    <td>Оценочная стоимость:</td>
					<td>' . $model['agr_price'] . ' ' . $model['agr_price_cur'] . '</td>
				  </tr>
				  <tr>
				    <td>Местонахождение:</td>
					<td>' . ($item['status'] == 'on_move' ? 'Товар перемещается на другой офис (в пути)' : 'Офис: ' . $item['item_place']) . '</td>
				  </tr>
				</table>';

			$item_output = str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет

			echo '
			ch_result=\'ok\';
			document.getElementById(\'deal_div\').innerHTML=\'' . $item_output . '\';
			document.getElementById(\'main_buttons\').style.display="none";
			document.getElementById(\'print_buttons\').style.display="none";
			document.getElementById(\'deal_area\').style.backgroundColor = \'yellow\';
			';


		} elseif ($item['status'] == 'rented_out' || $item['status'] == 'to_deliver') {

			$deal_id = $item['active_deal_id'];

			$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
			$result_dl_def = $mysqli->query($query_dl_def);
			if (!$result_dl_def)
				die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			$dl_def = $result_dl_def->fetch_assoc();

			$query_sub_dl_def = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' ORDER BY sub_deal_id DESC";
			$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
			if (!$result_sub_dl_def)
				die('Сбой при доступе к базе данных: ' . $query_sub_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

			// проверка наличия сделок у курьера
			$query_sub_cur = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND `status`='for_cur'";
			$result_sub_cur = $mysqli->query($query_sub_cur);
			if (!$result_sub_cur)
				die('Сбой при доступе к базе данных: ' . $query_sub_cur . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			$cur_rows = $result_sub_cur->num_rows;
			$cur_def = $result_sub_cur->fetch_assoc();


			$query_cl_def = "SELECT * FROM clients WHERE client_id='" . $dl_def['client_id'] . "'";
			$result_cl_def = $mysqli->query($query_cl_def);
			if (!$result_cl_def)
				die('Сбой при доступе к базе данных: ' . $result_cl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			$cl_def = $result_cl_def->fetch_assoc();

			$query_sub_dl_tarif = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND type IN ('first_rent', 'extention', 'takeaway_plan') ORDER BY sub_deal_id DESC";
			$result_sub_dl_tarif = $mysqli->query($query_sub_dl_tarif);
			if (!$result_sub_dl_tarif)
				die('Сбой при доступе к базе данных: ' . $query_sub_dl_tarif . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			$sub_dl_tarif = $result_sub_dl_tarif->fetch_assoc();



			//расчет платы за просрочку
			if (strtotime(date("Y-m-d")) > $dl_def['return_date']) {
				$morepay = 'просрочка';
				switch ($sub_dl_tarif['tarif_step']) {
					case 'month':

						if (date("j") >= date("j", $dl_def['return_date'])) { //вариант расчета, если текущий день равен, либо больше дня возврата
							$m_dif = (date("Y") * 12 + date("n")) - (date("Y", $dl_def['return_date']) * 12 + date("n", $dl_def['return_date'])); // считаем разницу в месяцах
							$day_rent = $sub_dl_tarif['tarif_value'] / 30;
							$to_pay_ad = -($m_dif * $sub_dl_tarif['tarif_value'] + (date("j") - date("j", $dl_def['return_date'])) * $day_rent);
							$morepay = number_format($to_pay_ad, 2, ',', ' ');
						}

						if (date("j") < date("j", $dl_def['return_date'])) { //вариант расчета, если текущий менее дня возврата
							$m_dif = (date("Y") * 12 + date("n") - 1) - (date("Y", $dl_def['return_date']) * 12 + date("n", $dl_def['return_date'])); // считаем разницу в месяцах
							$day_rent = $sub_dl_tarif['tarif_value'] / 30;
							$to_pay_ad = -($m_dif * $sub_dl_tarif['tarif_value'] + (date("j") + date("t", $dl_def['return_date']) - date("j", $dl_def['return_date'])) * $day_rent);
							$morepay = number_format($to_pay_ad, 2, ',', ' ');
						}
						break;

					case 'week';
						$day_dif = floor((strtotime(date("Y-m-d")) - $dl_def['return_date']) / 60 / 60 / 24);
						$week_dif = floor($day_dif / 7);
						$day_dif_left = $day_dif - $week_dif * 7;
						$day_tarif = $sub_dl_tarif['tarif_value'] / 7;
						$to_pay_ad = -($week_dif * $sub_dl_tarif['tarif_value'] + $day_dif_left * $day_tarif);
						$morepay = number_format($to_pay_ad, 2, ',', ' ');

						break;

					case 'day':

						$day_dif = floor((strtotime(date("Y-m-d")) - $dl_def['return_date']) / 60 / 60 / 24);
						$to_pay_ad = -($day_dif * $sub_dl_tarif['tarif_value']);
						$morepay = number_format($to_pay_ad, 2, ',', ' ');

						break;


					default:
						echo '';
						break;
				}



			} elseif (strtotime(date("Y-m-d")) == $dl_def['return_date']) {
				$morepay = 'срок возврата сегодня';
				$to_pay_ad = '0';
			} else {
				$morepay = 'срок не наступил';
				$to_pay_ad = '0';
			}


			$item_output = '<span style="font-weight:bold; font-size:30px; color:#F00;">Товар занят.</span> (№договора: ' . $dl_def['deal_id'] . ')<br />
				<strong>Клиент:</strong> ' . addslashes($cl_def['family']) . ' ' . addslashes($cl_def['name']) . ' ' . $cl_def['otch'] . '. <strong>Адрес:</strong> г.' . $cl_def['city'] . ', ул.: ' . $cl_def['str'] . ', дом: ' . $cl_def['dom'] . ', кв.: ' . $cl_def['kv'] . ' <input type="button" name="button" id="cl_displ_button" value="Отобразить подробную информацию о клиенте" style="display:none;" onclick="cl_displ(); return false;" /><br /><br />
						<input type="hidden" name="rez_client_id" value="' . $cl_def['client_id'] . '" />

				<table border="1" cellspacing="0">
				  <tr>
				    <td>Товар:</td>
					<td>' . addslashes($model['model_addr'] != '' ? $model['model_addr'] : $cat['dog_name']) . ' ' . addslashes($model['producer']) . ', модель: ' . addslashes($model['model']) . addslashes($color) . '</td>
				  </tr>

				  <tr>
				    <td>Комплектация:</td>
					<td><input name="deal_item_set" id="deal_item_set" type="text" size="80" value="' . addslashes($dl_def['deal_set']) . '" disabled="disabled" /><button type="button" onclick="showSet()" id="set_show_more_btn">показать все</button>';
			if (Deal::isForCurFirstRentAndNotDeliveredStat($dl_def['deal_id'])) {
				$item_output .= '<input type="button" onclick="cnahge_set();" id="set_ch_but" value="Изменить комплектацию">
                                           <input type="button" style="display: none" onclick="save_set();" id="save_ch_but" value="Сохранить изменения">
                            ';
			}


			$item_output .= '
					    </td>
				  </tr>

				  <tr>
				    <td>Оценочная стоимость:</td>
					<td>' . $model['agr_price'] . ' ' . $model['agr_price_cur'] . '</td>
				  </tr>
				</table>
						<br />

<strong>Информация по сделке:<br /></strong>
<div>' . $dl_def['deal_info'] . '</div>

<strong>Сводная информация по сделке:</strong>
<table border="1" cellspacing="0">
	<tr>
		<th>с</th>
		<th>по</th>
		<th>к оплате</th>
		<th>оплачено</th>
		<th>в т.ч. оф.</th>
		<th>(-)долг<br />(+)переплата</th>
		<th style="width:50px;">офис</th>
		<th style="width:100px;">К оплате за просрочку возврата</th>
		<th>Действия</th>
	</tr>
	<tr>
		<td>' . date("d.m.Y", $dl_def['start_date']) . '<input type="hidden" name="from_d_sluzh" id="from_date_value" value="' . ($dl_def['start_date'] < $dl_def['planned_return_date'] ? date("Y-m-d", $dl_def['start_date']) : date("Y-m-d", $dl_def['planned_return_date'])) . '"></td>
		<td>' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? date("d.m.Y (H:i)", $dl_def['return_date']) : date("d.m.Y", $dl_def['return_date'])) . '<input type="hidden" name="ret_d_sluzh" id="ret_date_value" value="' . date("Y-m-d", $dl_def['return_date']) . '" /></td>
		<td>' . number_format($dl_def['r_to_pay'], 2, ',', ' ') . '<br />' . number_format($dl_def['delivery_to_pay'], 2, ',', ' ') . '<input type="hidden" name="main_deal_r_to_pay" value="' . $dl_def['r_to_pay'] . '" /><input type="hidden" name="main_deal_delivery_to_pay" value="' . $dl_def['delivery_to_pay'] . '" /></td>
		<td>' . number_format($dl_def['r_paid'], 2, ',', ' ') . '<br />' . number_format($dl_def['delivery_paid'], 2, ',', ' ') . '<input type="hidden" name="main_deal_r_paid" value="' . $dl_def['r_paid'] . '" /><input type="hidden" name="main_deal_delivery_paid" value="' . $dl_def['delivery_paid'] . '" /></td>
		<td></td>
		<td>' . number_format(($dl_def['r_paid'] - $dl_def['r_to_pay']), 2, ',', ' ') . '<br />' . number_format(($dl_def['delivery_paid'] - $dl_def['delivery_to_pay']), 2, ',', ' ') . '
				<input type="hidden" name="deal_result" id="deal_result" value="' . ($dl_def['r_paid'] - $dl_def['r_to_pay'] + $dl_def['delivery_paid'] - $dl_def['delivery_to_pay']) . '" />
				</td>
		<td>';
			//ищем место первой сдачи
			$query_sub_first = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND `type`='first_rent'";
			$result_first = $mysqli->query($query_sub_first);
			if (!$result_first)
				die('Сбой при доступе к базе данных: ' . $query_sub_first . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			$sub_first = $result_first->fetch_assoc();

			if ($sub_first['delivery_yn'] == 1) {
				$item_output .= '<img src="1_3.png" />';
			} elseif ($sub_first['place'] == 1) {
				$item_output .= '<img src="1_1.png" />';
			} elseif ($sub_first['place'] == 2) {
				$item_output .= '<img src="1_2.png" />';
			} elseif ($sub_first['place'] == 3) {
				$item_output .= '<img src="1_3.png" />';
			} else {
				$item_output .= 'не опр.';
			}

			//show only if first rent
			$subDeals = \bb\classes\SubDeal::getAllByDealId($item['active_deal_id']);
			if ($subDeals && count($subDeals) == 1)
				$cur_date_change = true;
			else
				$cur_date_change = false;

			$item_output .= '</td>
		<td><span id="past_due_word">' . $morepay . '</span><br /><input type="hidden" name="to_pay_pastdue" id="to_pay_pastdue" value="' . round($to_pay_ad, 1) . '" /></td>
		<td>	<input type="hidden" name="active_deal_id" value="' . $item['active_deal_id'] . '" />
			' . ($cur_rows >= 1 ? 'Имеются незакрытые сделки у курьера. Закройте сделки у курьера.<br>

        <input type="button" class="new_deliv_start_btn ' . ($cur_date_change ? '' : 'hide') . '" value="Перенести дату выезда курьера" onclick="delivery_change_start_btn();">
        <div class="delivery_change_div hide">
          <input type="date" name="new_delivery_date" value="' . date("Y-m-d", $dl_def['start_date']) . '">
          <input type="button" class="new_deliv_action" value="Перенести" onclick="change_delivery_date();">
          <input type="button" class="new_deliv_cancel" value="отмена" onclick="delivery_change_start_btn();">
        </div>
					<!--<form method="post" action="cur_page.php">
						<input type="hidden" name="one_sub_dl_id" value="' . $cur_rows['sub_deal_id'] . '" />
						<input type="submit" name="action" value="страница курьера" />
					</form>-->

					' : '
			<table border="1" cellspacing="0">
				<tr>
					<td><input type="button" name="button" id="f_pay_button" value="Оформить оплату" onclick="chose_item(\\\'payment\\\', \\\'\\\'); return false;" /><br />Первая сдача только!!!</td>
					<td><input type="button" name="button" id="button" value="Продлить" onclick="chose_item(\\\'extend\\\', \\\'\\\'); return false;" /></td>
					<td>
						<input type="submit" name="action" id="button" value="Возврат-стандарт" onclick="return ret_ch();" />
						<input type="button" name="button" id="button" value="Возврат-нестандарт" onclick="chose_item(\\\'return\\\', \\\'\\\'); return false;" /><br />
						<input type="button" name="button" id="button" value="Возврат-курьером" onclick="chose_item(\\\'cur_return\\\', \\\'\\\'); return false;" /><br />

						</td>
				</tr>
			</table>') . '
		</td>

	</tr>


</table>

<div id="ext_div"></div>
<br />
<strong>Детальная информация по сделке/История:</strong><input type="button" value="показать" id="hist_button" onclick="hist_show(); return false;" />
<table border="1" cellspacing="0" id="hist_table" style="display:none; background-color:#73C4CA;">
	<tr>
		<th>операция</th>
		<th>с</th>
		<th>по</th>
		<th>период</th>
		<th>сумма</th>
		<th>дата оплаты</th>
		<th>оплачено</th>
		<th>доставка</th>
		<th style="width:100px;">Действия</th>
	</tr>';

			$prodl = 0;// для того, чтобы напечатать удаление только для первого-последнего продления и для того, чтобы сделать disable кнопки первого платежа при наличии продления
			$first_r_only = 1;
			while ($sub_dl_def = $result_sub_dl_def->fetch_assoc()) {

				$_SESSION['user_id'] == 3 ? $sub_id_show = '(' . $sub_dl_def['sub_deal_id'] . ') - ' . $sub_dl_def['cr_who_id'] : $sub_id_show = '';
				$_SESSION['user_id'] == 3 ? $link_id_show = '(' . $sub_dl_def['link'] . ')  - ' . $sub_dl_def['cr_who_id'] : $link_id_show = '';

				switch ($sub_dl_def['type']) {
					case 'first_rent':

						$query_sub_pay = "SELECT * FROM rent_sub_deals_act WHERE `type`='payment' AND link='" . $sub_dl_def['sub_deal_id'] . "' ORDER BY sub_deal_id DESC";
						$result_sub_pay = $mysqli->query($query_sub_pay);
						if (!$result_sub_pay)
							die('Сбой при доступе к базе данных: ' . $query_sub_pay . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

						$p_dates = '';
						$p_sums = '';

						$p_date = 0;
						$p_del_b = '';
						while ($sub_pay = $result_sub_pay->fetch_assoc()) {
							$p_dates .= date("d.m.y", $sub_pay['from']) . '<br/>';
							$p_sums .= $sub_pay['r_paid'] . ' ' . sh_kassa($sub_pay['r_payment_type']) . ($sub_pay['r_payment_type'] != 'nal_no_cheque' ? '(' . $sub_pay['ch_num'] . ')' : '') . '<br/>';

							if ($p_date != $sub_pay['from']) {
								$p_del_b .= '<input type="button" name="button" id="button" value="удалить оплаты (' . date("d.m.y", $sub_pay['from']) . ')" onclick="chose_del(\\\'payment\\\', \\\'' . $sub_pay['link'] . '\\\', \\\'' . $sub_pay['from'] . '\\\');" /><br />';
							}

							$p_date = $sub_pay['from'];

						}

						$item_output .= '
	<tr>
		<td>' . ($sub_dl_def['status'] == 'for_cur' ? '<strong>Отложено для курьера:</strong><br />' : '') . 'выдача [' . $sub_dl_def['place'] . ']</td>
		<td>' . date("d.m.Y", $sub_dl_def['from']) . '</td>
		<td>' . date("d.m.Y", $sub_dl_def['to']) . '</td>
		<td>' . number_format($sub_dl_def['rent_tenor'], 2, ',', ' ') . ' ' . step_pr($sub_dl_def['tarif_step']) . '</td>
		<td>' . number_format($sub_dl_def['r_to_pay'], 2, ',', ' ') . '</td>
		<td>' . $p_dates . '</td>
		<td>' . $p_sums . '</td>
		<td>' . ($sub_dl_def['delivery_yn'] == '1' ? 'Да' : 'Нет') . '</td>
		<td>	' . ($first_r_only == 1 ? $p_del_b : '') . '
				<input type="submit" name="action" id="button" value="удалить ВСЮ сделку" onclick="return deal_del();" />
			</td>
	</tr>';

						break;

					case 'takeaway_plan':

						$query_sub_pay = "SELECT * FROM rent_sub_deals_act WHERE `type`='payment' AND link='" . $sub_dl_def['sub_deal_id'] . "' ORDER BY sub_deal_id DESC";
						$result_sub_pay = $mysqli->query($query_sub_pay);
						if (!$result_sub_pay)
							die('Сбой при доступе к базе данных: ' . $query_sub_pay . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

						$p_dates = '';
						$p_sums = '';

						$p_date = 0;
						$p_del_b = '';
						while ($sub_pay = $result_sub_pay->fetch_assoc()) {
							$p_dates .= date("d.m.y", $sub_pay['from']) . '<br/>';
							$p_sums .= $sub_pay['r_paid'] . ' ' . sh_kassa($sub_pay['r_payment_type']) . ($sub_pay['r_payment_type'] != 'nal_no_cheque' ? '(' . $sub_pay['ch_num'] . ')' : '') . '<br/>';

							if ($p_date != $sub_pay['from']) {
								$p_del_b .= '<input type="button" name="button" id="button" value="удалить оплаты (' . date("d.m.y", $sub_pay['from']) . ')" onclick="chose_del(\\\'payment\\\', \\\'' . $sub_pay['link'] . '\\\', \\\'' . $sub_pay['from'] . '\\\');" /><br />';
							}

							$p_date = $sub_pay['from'];

						}

						$item_output .= '
	<tr>
		<td>' . ($sub_dl_def['status'] == 'for_cur' ? '<strong>Отложено для курьера:</strong><br />' : '') . 'бронь</td>
		<td>' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? date("d.m.Y (H:i)", $sub_dl_def['from']) : date("d.m.Y", $sub_dl_def['from'])) . '</td>
		<td>' . ((\bb\classes\tovar::isKarnavalByInvN($item_inv_n)) ? date("d.m.Y (H:i)", $sub_dl_def['to']) : date("d.m.Y", $sub_dl_def['to'])) . '</td>
		<td>' . number_format($sub_dl_def['rent_tenor'], 2, ',', ' ') . ' ' . step_pr($sub_dl_def['tarif_step']) . '</td>
		<td>' . number_format($sub_dl_def['r_to_pay'], 2, ',', ' ') . '</td>
		<td>' . $p_dates . '</td>
		<td>' . $p_sums . '</td>
		<td>' . ($sub_dl_def['delivery_yn'] == '1' ? 'Да' : 'Нет') . '</td>
		<td>	' . ($first_r_only == 1 ? $p_del_b : '') . '
				<input type="submit" name="action" id="button" value="удалить ВСЮ сделку" onclick="return deal_del();" />
			</td>
	</tr>';

						break;

					case 'extention':
						$first_r_only = 0;

						$query_sub_pay = "SELECT * FROM rent_sub_deals_act WHERE `type`='payment' AND link='" . $sub_dl_def['sub_deal_id'] . "' ORDER BY sub_deal_id DESC";
						$result_sub_pay = $mysqli->query($query_sub_pay);
						if (!$result_sub_pay)
							die('Сбой при доступе к базе данных: ' . $query_sub_pay . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

						$p_dates = '';
						$p_sums = '';

						while ($sub_pay = $result_sub_pay->fetch_assoc()) {
							$p_dates .= date("d.m.y", $sub_pay['from']) . '<br/>';
							$p_sums .= $sub_pay['r_paid'] . ' ' . sh_kassa($sub_pay['r_payment_type']) . ($sub_pay['r_payment_type'] != 'nal_no_cheque' ? '(' . $sub_pay['ch_num'] . ')' : '') . '<br/>';
						}

						$item_output .= '
	<tr>
		<td>' . ($sub_dl_def['status'] == 'for_cur' ? '<strong>Забор курьером: </strong><br />' : '') . 'продление</td>
		<td>' . date("d.m.Y", $sub_dl_def['from']) . '</td>
		<td>' . date("d.m.Y", $sub_dl_def['to']) . '</td>
		<td>' . number_format($sub_dl_def['rent_tenor'], 2, ',', ' ') . ' ' . step_pr($sub_dl_def['tarif_step']) . '</td>
		<td>' . number_format($sub_dl_def['r_to_pay'], 2, ',', ' ') . '</td>
		<td>' . $p_dates . '</td>
		<td>' . $p_sums . '</td>
		<td>' . ($sub_dl_def['delivery_yn'] == '1' ? 'Да' : 'Нет') . '</td>
		<td> ' . ($prodl == 0 ? '<input type="submit" name="action" id="button" value="удалить продление" onclick="chose_del(\\\'extention\\\', \\\'' . $sub_dl_def['sub_deal_id'] . '\\\', \\\'\\\');" />' : '') . '
			</td>
	</tr>';
						$prodl += 1;

						break;

					case 'payment':

						break;


					case 'cur_return':
						$first_r_only = 0;

						$item_output .= '
	<tr>
		<td><strong>заказ курьера на возврат</strong></td>
		<td>' . date("d.m.Y", $sub_dl_def['from']) . '</td>
		<td colspan="5"><strong>Доп. информация по возврату:</strong> <br />' . $sub_dl_def['info'] . '</td>
		<td>Да</td>
		<td><input type="button" name="button" id="button" value="удалить" onclick="chose_del(\\\'cur_return\\\', \\\'' . $sub_dl_def['sub_deal_id'] . '\\\',\\\'\\\');" /></td>
	</tr>';

						break;


					default:
						$item_output .= '
	<tr>
		<td>случай не прописан' . $sub_id_show . '</td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
	</tr>';

						break;
				}


			}
			$item_output .= '</table> <br />
';



			$item_output = str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет



			//делаем доступной кнопку оплаты только до тех пор, пока сумма оплаты (!всей сделки) менее суммы к оплате первой сдачи
// ищем первую сдачу
			$query_f = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND `type` IN ('first_rent', 'takeaway_plan')";
			$result_f = $mysqli->query($query_f);
			if (!$result_f)
				die('Сбой при доступе к базе данных: ' . $query_f . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			$f_r = $result_f->fetch_assoc();



			if ($f_r['r_to_pay'] <= $dl_def['r_paid']) {
				$pay_button_disable = 'document.getElementById(\'f_pay_button\').disabled="true";';
			} else {
				$pay_button_disable = '';
			}

			echo '
			ch_result=\'ok\';
			document.getElementById(\'deal_div\').innerHTML=\'' . $item_output . '\';
			document.getElementById(\'main_buttons\').style.display="none";
			document.getElementById(\'print_buttons\').style.display="";
			document.getElementById(\'deal_area\').style.backgroundColor = \'#F93\';
			' . $pay_button_disable . '
			';


		}//end of rented_out if
		else {
			$item_output = 'Статус товара не проставлен.';

			$item_output = str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет

			echo '
			ch_result=\'ok\';
			document.getElementById(\'deal_div\').innerHTML=\'' . $item_output . '\';
			';
		}

	}// end of action type select if

	if ($action_type == 'extend' && $item['status'] == 'rented_out') {

		$deal_id = $item['active_deal_id'];

		$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
		$result_dl_def = $mysqli->query($query_dl_def);
		if (!$result_dl_def)
			die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		$dl_def = $result_dl_def->fetch_assoc();

		$query_sub_dl_def = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND type IN ('first_rent', 'extention', 'takeaway_plan') ORDER BY sub_deal_id DESC";
		$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
		if (!$result_sub_dl_def)
			die('Сбой при доступе к базе данных: ' . $query_sub_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		$sub_dl_def = $result_sub_dl_def->fetch_assoc();

		$oldTarifDays = ($sub_dl_def['tarif_step'] == 'month' ? 30 : ($sub_dl_def['tarif_step'] == 'week' ? 7 : 1));

		$item_output = '<br />

<div style="display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap;">
  <div>
    <strong>Актуальные тарифы:</strong><br />
    ' . $tarif_code . '
  </div>
  <div style="display:flex;flex-direction:column;gap:10px;">
    <div>
      <strong>Последний использованный тариф:</strong><br />
      <div style="border:2px solid #e57373;border-radius:6px;padding:6px 14px;cursor:pointer;display:inline-block;margin-top:4px;" onclick="apply_tarif(\\\'old\\\'); this.style.background=\\\'#a5d6a7\\\'; return false;">
        <input type="hidden" value="1" id="kol_vo_old" /><input type="hidden" value="1" id="kol_vo_min_old" /><input type="hidden" value="' . $sub_dl_def['tarif_step'] . '" id="step_old" />
        ' . $sub_dl_def['tarif_value'] . ' руб. в ' . tenor_print($sub_dl_def['tarif_step'], 'd') . '
        <input type="hidden" value="' . $sub_dl_def['tarif_value'] . '" id="rent_per_step_old" />
        <input type="hidden" class="tarifPrev" data-days="' . ($oldTarifDays) . '" value="' . ($sub_dl_def['tarif_value']) . '">
      </div>
    </div>
    <div>
      Дополнительная информация по сделке:<br /> <textarea cols="50" rows="3" name="deal_info" id="deal_info"></textarea>
    </div>
    <div>
      <strong>Продление с:</strong> <input type="date" name="start_date" id="start_date" value="' . date("Y-m-d", $dl_def['return_date']) . '" readonly="readonly"/>
    </div>
  </div>
</div>

<div style="margin-top:12px;">
  Тариф: <input type="number" step="0.01" name="rent_tarif" id="rent_tarif" value="" readonly style="background-color:#cacaca;width:70px;" /> бел. руб.
  <input type="hidden" name="tarif_id" id="tarif_id" value="" />
  &nbsp;
  <select name="step" id="step">
    <option value="day">в день</option>
    <option value="week">в неделю</option>
    <option value="month">в месяц</option>
  </select>
  &nbsp; количество (д/н/м): <input type="number" name="rent_tenor" id="rent_tenor" onchange="daysChange();" value="" style="width:60px;"/>
  &nbsp;&nbsp; Скидка: <input min="0" max="100" step="5" style="width:50px;font-size:14px;background-color:orange;text-align:center;border-radius:4px;border:1px solid #ccc;" type="number" name="discount" id="discount" value="0" onchange="calculateNew();"> %
</div>

<div style="margin-top:8px;">
  <input type="button" value="Пересчитать" id="calc_button" onclick="calculateNew(); return false;" style="padding:8px 24px;font-size:16px;font-weight:bold;color:#e65100;background:#fff;border:2px solid #e65100;border-radius:20px;cursor:pointer;" />
</div>

<div style="margin-top:10px;">
Стоимость аренды: <input type="text" name="r_to_pay" id="r_to_pay" size="10" value="" />,
  <select name="rent_payment_type" id="rent_payment_type" onchange="multi_ch();">
    <option value="no_payment">не оплачено</option>
    <option value="nal_no_cheque">нал без чека</option>
    <option value="nal_cheque">нал с чеком</option>
    <option value="card">карточка</option>
    <option value="bank">банк</option>
    <option value="multi">мульти-оплата</option>
  </select>
  <span id="ch_num_span" style="display:none;">, № документа:<input type="text" name="ch_num" id="ch_num" value="" size="10" /></span>
  &nbsp; Дата оплаты: <input type="date" name="payment_date" id="payment_date" value="' . date("Y-m-d") . '" />
</div>

<div id="multi_pay" style="display:none; position:relative; left:135px;">
  <input type="number" step="any" name="rent_p_k1" id="rent_p_k1" value="" style="width:90px;" /> касса 1 (нч), № документа: <input type="text" name="ch_num_p_k1" id="ch_num_p_k1" value="" size="10" /><br />
  <input type="number" step="any" name="rent_p_k2" id="rent_p_k2" value="" style="width:90px;" /> касса 2 (нбч)<br />
  <input type="number" step="any" name="rent_p_card" id="rent_p_card" value="" style="width:90px;" /> карточка, № документа: <input type="text" name="ch_num_p_card" id="ch_num_p_card" value="" size="10" /><br />
  <input type="number" step="any" name="rent_p_bank" id="rent_p_bank" value="" style="width:90px;" /> банк, № документа: <input type="text" name="ch_num_p_bank" id="ch_num_p_bank" value="" size="10" /><br />
</div>

<div style="margin-top:8px;">
  Дата возврата: <input type="date" name="return_date" id="return_date" onchange="dateChange();" value=""/>
</div>

<input type="hidden" name="del_to_pay" id="del_to_pay" value="" />

<br />


';

		$query_cl_def = "SELECT * FROM clients WHERE client_id='" . $dl_def['client_id'] . "'";
		$result_cl_def = $mysqli->query($query_cl_def);
		if (!$result_cl_def)
			die('Сбой при доступе к базе данных: ' . $query_cl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		$cl_def = $result_cl_def->fetch_assoc();


		$cl_hist = '
<div id="cl_hist">
<strong>История клиента</strong> (без текущих сделок):
		<table border="1" cellspacing="0">
			<tr>
				<th>количеств выдач</th>
				<th>сумма</th>
				<th>дата посл. арх. сделки</th>
				<th>Действия</th>
			</tr>
			<tr>
				<td>' . $cl_def['arch_n'] . '</td>
				<td>' . $cl_def['arch_amount'] . '</td>
				<td>' . ($cl_def['arch_l_date'] > 0 ? date("d.m.Y", $cl_def['arch_l_date']) : '') . '</td>
				<td><input type="button" value="показать товары" onclick="chose_item(\\\'arch_hist\\\', \\\'\\\'); return false;" /></td>
			</tr>
		</table>
		<div id="arch_hist_div"></div>
</div>
		';

		$cl_hist = str_replace(array("\r\n", "\r", "\n"), "", $cl_hist); //превращаем в одну строку, иначе javascript не поймет

		$client_info = '
			document.getElementById(\'family\').value=\'' . addslashes($cl_def['family']) . '\';
			document.getElementById(\'family\').disabled=true;

			document.getElementById(\'name\').value=\'' . addslashes($cl_def['name']) . '\';
			document.getElementById(\'name\').disabled=true;

			document.getElementById(\'otch\').value=\'' . $cl_def['otch'] . '\';
			document.getElementById(\'otch\').disabled=true;

			document.getElementById(\'str\').value=\'' . addslashes($cl_def['str']) . '\';
			document.getElementById(\'str\').disabled=true;

			document.getElementById(\'dom\').value=\'' . $cl_def['dom'] . '\';
			document.getElementById(\'dom\').disabled=true;

			document.getElementById(\'kv\').value=\'' . $cl_def['kv'] . '\';
			document.getElementById(\'kv\').disabled=true;

			document.getElementById(\'city\').value=\'' . $cl_def['city'] . '\';
			document.getElementById(\'city\').disabled=true;

			document.getElementById(\'address_copy\').disabled=true;

			document.getElementById(\'reg_str\').value=\'' . addslashes($cl_def['reg_str']) . '\';
			document.getElementById(\'reg_str\').disabled=true;

			document.getElementById(\'reg_dom\').value=\'' . $cl_def['reg_dom'] . '\';
			document.getElementById(\'reg_dom\').disabled=true;

			document.getElementById(\'reg_kv\').value=\'' . $cl_def['reg_kv'] . '\';
			document.getElementById(\'reg_kv\').disabled=true;

			document.getElementById(\'reg_city\').value=\'' . $cl_def['reg_city'] . '\';
			document.getElementById(\'reg_city\').disabled=true;

			document.getElementById(\'pas_n\').value=\'' . $cl_def['pas_n'] . '\';
			document.getElementById(\'pas_n\').disabled=true;

			document.getElementById(\'pas_date\').value=\'' . date("Y-m-d", $cl_def['pas_date']) . '\';
			document.getElementById(\'pas_date\').disabled=true;

			document.getElementById(\'pas_who\').value=\'' . $cl_def['pas_who'] . '\';
			document.getElementById(\'pas_who\').disabled=true;

			document.getElementById(\'phone_1\').value=\'' . phone_print($cl_def['phone_1']) . '\';
			document.getElementById(\'phone_1\').disabled=true;

			document.getElementById(\'phone_2\').value=\'' . phone_print($cl_def['phone_2']) . '\';
			document.getElementById(\'phone_2\').disabled=true;

			document.getElementById(\'info\').value=\'' . good_print(str_replace(array("\r\n", "\r", "\n"), "", $cl_def['info'])) . '\';
			document.getElementById(\'info\').disabled=true;

			document.getElementById(\'action_save_cl\').disabled=true;

			document.getElementById(\'client_header\').innerHTML=\'Информация о клиенте (№' . $cl_def['client_id'] . '): <input type="button" value="редактировать информацию клиента" id="cl_edit_button" onclick="hide_client(); return false;" />\';

			document.getElementById(\'client_id\').value=\'' . $cl_def['client_id'] . '\';

			if(document.getElementById(\'client_find_div\')) document.getElementById(\'client_find_div\').style.display="none";
			//document.getElementById(\'client_info_div\').style.display="none";
			document.getElementById(\'cl_displ_button\').style.display="";
			document.getElementById(\'cl_hist\').innerHTML=\'' . $cl_hist . '\'
			';



		$item_output = str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет

		echo '
			ch_result=\'ok\';
			document.getElementById(\'ext_div\').innerHTML=\'' . $item_output . '\';
			document.getElementById(\'action_save\').value=\'сохранить продление\';
			document.getElementById(\'action_delivery\').value=\'сохранить продление для курьера\';
			document.getElementById(\'main_buttons\').style.display="";
			' . $client_info . '
			';

	}


	if ($action_type == 'payment' && $item['status'] == 'rented_out') {

		$deal_id = $item['active_deal_id'];

		$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
		$result_dl_def = $mysqli->query($query_dl_def);
		if (!$result_dl_def)
			die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		$dl_def = $result_dl_def->fetch_assoc();


		$item_output = '<br />

	Оформление оплаты:<br />

	<table border="1" cellspacing="0">
		<tr>
			<th scope="col" style="width:80px;">дата платежа</th>
			<th scope="col" style="width:80px;">оплата аренды, руб.</th>
			<th scope="col" style="width:80px;">канал оплаты</th>
			<th scope="col" style="width:80px; display:none;">оплата курьера, руб.</th>
			<th scope="col" style="width:80px; display:none;">канал оплаты</th>
			<th scope="col" style="width:80px;">действие</th>
		</tr>
		<tr>
			<td><input type="date" name="start_date" id="start_date" value="' . ($_SESSION['user_id'] == 17 ? date("Y-m-d", $dl_def['start_date']) : date("Y-m-d")) . '"/></td>
			<td><input type="number" step="0.01" name="rent_payment" id="rent_payment" value="' . (-$dl_def['r_paid'] + $dl_def['r_to_pay']) . '" size="10"/></td>
			<td>
				<select name="rent_payment_type" id="rent_payment_type" onchange="multi_ch();">
					<option value="nal_no_cheque">нал без чека</option>
					<option value="nal_cheque">нал с чеком</option>
					<option value="card">карточка</option>
					<option value="bank">банк</option>
					<option value="multi">мульти-оплата</option>
				</select>
				<span id="ch_num_span" style="display:none;"><br />№ документа:<input type="text" name="ch_num" id="ch_num" value="" size="10" /></span>
				</td>
			<td size="10" style="display:none;"><input type="text" name="del_payment" id="del_payment" value="' . (-$dl_def['delivery_paid'] + $dl_def['delivery_to_pay']) . '" /></td>
			<td size="10" style="display:none;">
				<select name="del_payment_type" id="del_payment_type">
					<option value="no_payment">не оплачено</option>
					<option value="nal_no_cheque">нал без чека</option>
					<option value="nal_cheque">нал с чеком</option>
					<option value="card">карточка</option>
					<option value="bank">банк</option>
				</select>
				</td>
			<td><input type="submit" name="action" value="сохранить оплату" onclick="return new_payment();" /></td>
		</tr>
	</table>
	<div id="multi_pay" style="display:none; position:relative; left:165px;">
		<input type="number" step="any" name="rent_p_k1" id="rent_p_k1" value="" style="width:90px;" />	касса 1 (нч), № документа: <input type="text" name="ch_num_p_k1" id="ch_num_p_k1" value="" size="10" /><br />
		<input type="number" step="any" name="rent_p_k2" id="rent_p_k2" value="" style="width:90px;" />	касса 2 (нбч)<br />
		<input type="number" step="any" name="rent_p_card" id="rent_p_card" value="" style="width:90px;" />	карточка, № документа: <input type="text" name="ch_num_p_card" id="ch_num_p_card" value="" size="10" /><br />
		<input type="number" step="any" name="rent_p_bank" id="rent_p_bank" value="" style="width:90px;" />	банк, № документа: <input type="text" name="ch_num_p_bank" id="ch_num_p_bank" value="" size="10" /><br />
	</div>

		';

		$item_output = str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет

		echo '
			ch_result=\'ok\';
			document.getElementById(\'ext_div\').innerHTML=\'' . $item_output . '\';
			document.getElementById(\'main_buttons\').style.display="none";

			';



	}// end of payment if


	if ($action_type == 'return' && $item['status'] == 'rented_out') {

		$deal_id = $item['active_deal_id'];

		$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
		$result_dl_def = $mysqli->query($query_dl_def);
		if (!$result_dl_def)
			die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		$dl_def = $result_dl_def->fetch_assoc();


		$item_output = '<br />

	<strong>Оформление возврата:</strong><br />

	<table border="1" cellspacing="0">
	<tr>
		<td>дата возврата:<input type="date" name="start_date" id="start_date" value="' . ($_SESSION['user_id'] == 17 ? date("Y-m-d", $dl_def['return_date']) : date("Y-m-d")) . '"/>
		<input type="button" value="пересчитать просрочку" onclick="past_due_recalc(\\\'' . $deal_id . '\\\');" /><br />
		состояние:
				<select name="ret_status" id="return_status" onchange="ret_st();">
					<option value="ok">приемлемое состояние</option>
					<option value="not_ok">повреждения, поломки, некомплект</option>
				</select><br />

		<span id="sub_deal_span" style="display:none;">Комментарии:<br /><textarea name="sub_deal_info" id="sub_deal_info" cols="50" rows="3"></textarea><br /></span>

	оплата(+)/возврат(-): <input type="number" step="0.01" value="" name="ret_payment_amount" id="ret_payment_amount" size="5" /> бел. руб.

			<select name="return_p_kassa" id="return_p_kassa" onchange="voz_ch();">
				<option value="no_payment">не оплачено</option>
				<option value="nal_no_cheque">нал без чека</option>
				<option value="nal_cheque">нал с чеком</option>
				<option value="card">карточка</option>
				<option value="bank">банк</option>
			</select>
				<span id="ch_num_span" style="display:none;">, № документа:<input type="text" name="ch_num" id="ch_num" value="" size="10" /></span><br><br>
				<div>Товар выкуплен/списан: <!--<input type="checkbox" name="tov_sold" id="tov_sold" style="width: 24px; height: 24px;" value="yes">-->
				<select name="tov_sold" id="tov_sold">
				    <option value="0">нет</option>
				    <option value="sold">товар выкуплен клиентом</option>
				    <option value="no_return">товар не возвращен - списываем</option>
                </select>
				</div>
				<br />
<!---				Выезд курьера:<input type="checkbox" id="del_ch_b" name="delivery" onchange="disable_deliv()">
				Оплачено за выезд:<input type="number" step="0.01" name="delivery_price" id="delivery_price" size="10" value="" disabled="disabled" />бел. руб.
				<select name="return_p_kassa_deliv" id="return_p_kassa_deliv" disabled="disabled">
					<option value="no_payment">не оплачено</option>
					<option value="nal_no_cheque">нал без чека</option>
					<option value="nal_cheque">нал с чеком</option>
					<option value="card">карточка</option>
					<option value="bank">банк</option>
				</select>
			-->

						<input type="submit" name="action" value="сохранить возврат" style="margin: 10px; padding: 5px; font-size: 16px;" onclick="return ret_save();" /><br />
		</td>
	</tr>


</table>




';


		$item_output = str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет
		echo '
			ch_result=\'ok\';
			document.getElementById(\'ext_div\').innerHTML=\'' . $item_output . '\';'
			. ($_SESSION['user_id'] == 17 ? '' : 'document.getElementById(\'ret_payment_amount\').value=(-document.getElementById(\'deal_result\').value*1-document.getElementById(\'to_pay_pastdue\').value*1);') .
			'document.getElementById(\'main_buttons\').style.display="none";




			';

	}//end of return if




	if ($action_type == 'cur_return' && $item['status'] == 'rented_out') {

		$item_output = '<br /><strong>Заказ возврата курьером:</strong><br />

	Дата выезда курьера (возврата): <input type="date" name="start_date" id="start_date" value="' . date("Y-m-d") . '"/> <br />
	К оплате (+)/к возврату клиенту (-): <input type="number" step="0.01" style="width:60px;" name="r_to_pay" id="r_to_pay" value="" /> бел. руб.<br />
	Стоимость выезда курьера: <input type="number" step="0.01" style="width:60px;" name="del_to_pay" id="del_to_pay" value="" /> бел. руб.<br />
	Доп. информация:<br /><textarea name="sub_deal_info" id="sub_deal_info" cols="50" rows="3"></textarea><br />

		<br /><input type="submit" name="action" value="сохранить заказ забора курьером" /><br />

			';




		$item_output = str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет


		echo '
			ch_result=\'ok\';
			document.getElementById(\'ext_div\').innerHTML=\'' . $item_output . '\';


					';
	}// end of cur_retutn if





}//end of numrow if
elseif ($item_rows > 1) {
	echo '
                alert (\'Несколько товаров с одинаковым инвентарным номером! Сообщите разработчику.\');
                document.getElementById(\'deal_div\').innerHTML=\'\';
                document.getElementById(\'main_buttons\').style.display="none";
                document.getElementById(\'deal_area\').style.backgroundColor = \'#CCC\';
                ';
} else {
	echo '
            ch_result=\'no\';
            document.getElementById(\'deal_div\').innerHTML=\'\';
            document.getElementById(\'main_buttons\').style.display="none";
            document.getElementById(\'deal_area\').style.backgroundColor = \'#CCC\';
            ';
}









function get_post($var)
{
	$mysqli = \bb\Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
}




function tenor_print($step_name, $value)
{

	switch ($step_name) {

		case 'day':

			if ($value == '1') {
				return 'день';
			} elseif ($value == '0') {
				return 'дней';
			} elseif ($value > 1 && $value < 5) {
				return 'дня';
			} elseif ($value > 4 && $value < 20) {
				return 'дней';
			} elseif ($value == 'd') {
				return 'день';
			}

			break;


		case 'week':

			if ($value == '1') {
				return 'неделя';
			} elseif ($value == '0') {
				return 'недели';
			} elseif ($value > 1 && $value < 5) {
				return 'недели';
			} elseif ($value > 4 && $value < 20) {
				return 'недель';
			} elseif ($value == 'd') {
				return 'неделю';
			}

			break;


		case 'month':

			if ($value == '1') {
				return 'месяц';
			} elseif ($value == '0') {
				return 'месяцев';
			} elseif ($value > 1 && $value < 5) {
				return 'месяца';
			} elseif ($value > 4 && $value < 20) {
				return 'месяцев';
			} elseif ($value == 'd') {
				return 'месяц';
			}

			break;

	}//end of switch
}//end of function


function p_type($p_type)
{
	switch ($p_type) {
		case 'nal_no_cheque':
			$output = 'нал без чека';
			break;

		case 'nal_cheque':
			$output = 'нал с чеком';
			break;

		case 'card':
			$output = 'карточка';
			break;

		case 'bank':
			$output = 'банк';
			break;

		case '':
			$output = '';
			break;

		default:
			$output = 'ХЗ';
			break;

	}
	return $output;

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


function good_print($var)
{
	$var = htmlspecialchars(stripslashes($var));
	return $var;
}


function pay_calc($deal_id, $ret_date)
{

	$mysqli = \bb\Db::getInstance()->getConnection();

	//запрос информации о сделке
	$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
	$result_dl_def = $mysqli->query($query_dl_def);
	if (!$result_dl_def)
		die('Сбой при доступе к базе данных: ' . $result_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	$dl_def = $result_dl_def->fetch_assoc();

	//вытягиваем последний примененный тариф
	$query_sub_dl_tarif = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND type IN ('first_rent', 'extention', 'takeaway_plan') ORDER BY `from` DESC";
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
					$morepay = round($to_pay_ad, 2);
				}

				if (date("j", $ret_date) < date("j", $dl_def['return_date'])) { //вариант расчета, если текущий менее дня возврата
					$m_dif = (date("Y", $ret_date) * 12 + date("n", $ret_date) - 1) - (date("Y", $dl_def['return_date']) * 12 + date("n", $dl_def['return_date'])); // считаем разницу в месяцах
					$day_rent = $sub_dl_tarif['tarif_value'] / 30;
					$to_pay_ad = -($m_dif * $sub_dl_tarif['tarif_value'] + (date("j", $ret_date) + date("t", $dl_def['return_date']) - date("j", $dl_def['return_date'])) * $day_rent);
					$morepay = round($to_pay_ad, 2);
				}
				break;

			case 'week';
				$day_dif = floor(($ret_date - $dl_def['return_date']) / 60 / 60 / 24);
				$week_dif = floor($day_dif / 7);
				$day_dif_left = $day_dif - $week_dif * 7;
				$day_tarif = $sub_dl_tarif['tarif_value'] / 7;
				$to_pay_ad = -($week_dif * $sub_dl_tarif['tarif_value'] + $day_dif_left * $day_tarif);
				$morepay = round($to_pay_ad, 2);

				break;

			case 'day':

				$day_dif = floor(($ret_date - $dl_def['return_date']) / 60 / 60 / 24);
				$to_pay_ad = -($day_dif * $sub_dl_tarif['tarif_value']);
				$morepay = round($to_pay_ad, 2);

				break;


			default:
				echo 'не считает функция просрочки';
				break;
		}



	} elseif ($ret_date == $dl_def['return_date']) {
		$morepay = 'срок возврата сегодня';
		$to_pay_ad = '0';
	} else {
		$morepay = 'срок не наступил';
		$to_pay_ad = '0';
	}





	return $morepay;
}// end of pay_calc function

function step_pr($step)
{
	switch ($step) {
		case 'day':
			return 'дн.';
			break;

		case 'week':
			return 'нед.';
			break;

		case 'month':
			return 'мес.';
			break;

		default:
			return '-';
			break;
	}
}

function sh_kassa($kassa)
{
	switch ($kassa) {
		case 'nal_no_cheque':
			return 'к2';
			break;

		case 'nal_cheque':
			return 'нал';
			break;

		case 'card':
			return 'карт';
			break;

		case 'bank':
			return 'банк';
			break;

		case '':
		case '0':
		case 'no_payment':
			return '';
			break;

		default:
			return 'ХЗК';
			break;
	}
}

function inv_print($inv_n)
{

	$output = substr($inv_n, 0, 3) . '-' . substr($inv_n, 3);

	return $output;

}

function rus_day($day)
{
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


function user_name($id)
{
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

function stat_print($stat)
{
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
?>