<?php

use bb\Db;

session_start();
//!!! везьде добавить обработку числовых значений - замену запятой на точку
ini_set("display_errors",0);
//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); // включаем подключение к базе данных

//------- proverka paroley

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941) {
	die('
	<form action="index.php" method="post">
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>');
}

//-----------proverka paroley

$start_code='

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	</head>
	<body>


		';


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

//echo $start_code;
//$sub_dl_id=190;
//$action_type='viezd';

$mysqli = Db::getInstance()->getConnection();

switch ($action_type) {
	case 'viezd':
		//запрос информации по суб.сделке
        $mysqli = Db::getInstance()->getConnection();

		$query_sub_dl_def = "SELECT * FROM rent_sub_deals_act WHERE `sub_deal_id`='$sub_dl_id'";
		$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
		if (!$result_sub_dl_def) {
            die('Сбой при доступе к базе данных: ' . $query_sub_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
		$sub_dl_def=$result_sub_dl_def->fetch_assoc();

		//запрос информации о сделке
		$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."'";
		$result_dl_def = $mysqli->query($query_dl_def);
		if (!$result_dl_def) {
            die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
		$dl_def=$result_dl_def->fetch_assoc();

		//запрос актуальной информации о клиенте
		$query_cl_def = "SELECT * FROM clients WHERE client_id='".$dl_def['client_id']."'";
		$result_cl_def = $mysqli->query($query_cl_def);
		if (!$result_cl_def) {
            die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
		$cl_def=$result_cl_def->fetch_assoc();


		//запрос информации о товаре по инв. номеру
		$query_item_def = "SELECT * FROM tovar_rent_items WHERE item_inv_n='".$dl_def['item_inv_n']."'";
		$result_item_def = $mysqli->query($query_item_def);
		if (!$result_item_def) {
            die('Сбой при доступе к базе данных: ' . $query_item_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
		$item_def=$result_item_def->fetch_assoc();

		$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$item_def['model_id']."'";
		$result_model_def = $mysqli->query($query_model_def);
		if (!$result_model_def) {
            die('Сбой при доступе к базе данных: ' . $query_model_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
		$model_def=$result_model_def->fetch_assoc();


		//запрос информации о категории товара
		$query_cat_def = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$model_def['tovar_rent_cat_id']."'";
		$result_cat_def = $mysqli->query($query_cat_def);
		if (!$result_cat_def) {
            die('Сбой при доступе к базе данных: ' . $query_cat_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
		$cat_def=$result_cat_def->fetch_assoc();

		//выбираем действующие тарифы
		$query_tarif = "SELECT * FROM rent_tarif_act WHERE model_id='".$item_def['model_id']."' ORDER BY sort_num, kol_vo";
		$result_tarif = $mysqli->query($query_tarif);
		if (!$result_tarif) {
            die('Сбой при доступе к базе данных: ' . $query_tarif . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
		$tarif_rows = $result_tarif->num_rows;

		//выбираем курьеров
		$query_cur = "SELECT * FROM logpass WHERE delivery='1' ORDER BY lp_fio";
		$result_cur = $mysqli->query($query_cur);
		if (!$result_cur) {
            die('Сбой при доступе к базе данных: ' . $query_cur . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }

		$model_def['color'] == 0 ? $color='' : $color=', цвет: '.$model_def['color'];



		if ($tarif_rows>0) {

			// выборка действующих тарифов
			$tarif_code='
			<table border="1" cellspacing="0">
				  <tr>
				    <th scope="col">сумма</th>
				    <th scope="col">за весь период</th>
				    <th scope="col">за шаг</th>
				    <th scope="col">выбрать тариф</th>
				  </tr>';

			while ($tarif=$result_tarif->fetch_assoc()) {
				$tarif_code=$tarif_code.'
				  <tr>
				    <td>'.$tarif['rent_amount'].' руб. <input type="hidden" value="'.$tarif['rent_amount'].'" id="rent_amount_'.$sub_dl_id.'_'.$tarif['tarif_id'].'" /></td>
				   	<td> за '.$tarif['kol_vo'].' '.tenor_print($tarif['step'], $tarif['kol_vo']).'<input type="hidden" value="'.$tarif['kol_vo'].'" id="kol_vo_'.$sub_dl_id.'_'.$tarif['tarif_id'].'" /><input type="hidden" value="'.$tarif['kol_vo_min'].'" id="kol_vo_min_'.$sub_dl_id.'_'.$tarif['tarif_id'].'" /><input type="hidden" value="'.$tarif['step'].'" id="step_'.$sub_dl_id.'_'.$tarif['tarif_id'].'" /></td>
				   	<td>='.$tarif['rent_per_step'].' руб. в '.tenor_print($tarif['step'], 'd').' <input type="hidden" value="'.$tarif['rent_per_step'].'" id="rent_per_step_'.$sub_dl_id.'_'.$tarif['tarif_id'].'" /></td>
				    <td><input type="button" name="button" id="button" value="Выбрать" onclick="apply_tarif(\\\''.$tarif['tarif_id'].'\\\',\\\''.$sub_dl_id.'\\\' ); return false;" /></td>
				  </tr>';
			}

			$tarif_code=$tarif_code.'</table>';
		}
		else {$tarif_code='<p style="font-weight:bold; font-size:18px; color:#F00;">Для данного товара тарифы еще не введены. Кристина должна ввести тариф!</p>';}





		$item_output='
	<form name="main" method="post" action="cur_page2.php" style="position:absolute; top:0px; right:300px;">

	<div class="find_cl" id="client_info_div_'.$sub_dl_id.'">
		<span class="div_header" id="client_header_'.$sub_dl_id.'">Информация о клиенте (№'.$dl_def['client_id'].'): <input type="button" value="редактировать информацию клиента" id="cl_edit_button_'.$sub_dl_id.'" onclick="hide_client(\\\''.$sub_dl_id.'\\\'); return false;" /><input type="button" value="отмена" id="cl_cans_'.$sub_dl_id.'" onclick="cans(\\\'post_div_'.$sub_dl_id.'\\\'); return false;" style="position:relative; right:-200px;"/></span><br />

			<input type="hidden" name="client_id" id="client_id_'.$sub_dl_id.'" value="'.$dl_def['client_id'].'" />
			<input type="hidden" name="client_update" id="client_update_'.$sub_dl_id.'" value="0" />
			Фамилия:<input type="text" name="family" id="family_'.$sub_dl_id.'" size="30" disabled="disabled" value="'.addslashes($cl_def['family']).'" />
			Имя: <input type="text" name="name" id="name_'.$sub_dl_id.'" size="30" disabled="disabled" value="'.addslashes($cl_def['name']).'" />
			Отчество:<input type="text" name="otch" id="otch_'.$sub_dl_id.'" size="30" disabled="disabled" value="'.$cl_def['otch'].'" /><br />

			Адрес: улица:<input type="text" name="str" id="str_'.$sub_dl_id.'" size="30" disabled="disabled" value="'.addslashes($cl_def['str']).'" />, дом:<input type="text" name="dom" id="dom_'.$sub_dl_id.'" size="3" disabled="disabled" value="'.$cl_def['dom'].'" />, квартира:<input type="text" name="kv" id="kv_'.$sub_dl_id.'" size="3" disabled="disabled" value="'.$cl_def['kv'].'" />, город:<input type="text" name="city" id="city_'.$sub_dl_id.'" size="10" disabled="disabled" value="'.$cl_def['city'].'" /> <input type="button" value="копировать адрес в прописку" id="address_copy_'.$sub_dl_id.'" disabled="disabled" onclick="copy_addr(\\\''.$sub_dl_id.'\\\'); return false;" /><br />
			Прописка: улица:<input type="text" name="reg_str" id="reg_str_'.$sub_dl_id.'" disabled="disabled" size="30" value="'.addslashes($cl_def['reg_str']).'" />, дома:<input type="text" name="reg_dom" id="reg_dom_'.$sub_dl_id.'" size="3" disabled="disabled" value="'.$cl_def['reg_dom'].'" />, квартира:<input type="text" name="reg_kv" id="reg_kv_'.$sub_dl_id.'" size="3" disabled="disabled" value="'.$cl_def['reg_kv'].'" />, город:<input type="text" name="reg_city" id="reg_city_'.$sub_dl_id.'" size="10" disabled="disabled" value="'.$cl_def['reg_city'].'" /> <br />

			№ паспорта:<input type="text" name="pas_n" id="pas_n_'.$sub_dl_id.'" size="30" disabled="disabled" value="'.$cl_def['pas_n'].'" />
			выдан (дата):<input type="date" name="pas_date" id="pas_date_'.$sub_dl_id.'" disabled="disabled" value="'.date("Y-m-d", $cl_def['pas_date']).'" />
			выдан (кем):<input type="text" name="pas_who" id="pas_who_'.$sub_dl_id.'" size="30" disabled="disabled" value="'.$cl_def['pas_who'].'" /><br />
			Личный номер:<input type="text" name="pas_ln" id="pas_ln_'.$sub_dl_id.'" size="14" maxlength="14" disabled="disabled" value="'.$cl_def['pas_ln'].'" /><br />

			Телефон 1 (+375):<input type="text" name="phone_1" id="phone_1_'.$sub_dl_id.'" size="30" disabled="disabled" value="'.phone_print($cl_def['phone_1']).'" />
			Телефон 2 (+375):<input type="text" name="phone_2" id="phone_2_'.$sub_dl_id.'" size="30" disabled="disabled" value="'.phone_print($cl_def['phone_2']).'" /> <i>Если 2-й телефон отсутствует - ставьте 0 (нуль)!!!</i><br />
			Дополнительная информация:<br/> <textarea cols="100" rows="3" name="info" id="info_'.$sub_dl_id.'" disabled="disabled" >'.$cl_def['info'].'</textarea><br />

			<br />
	</div>

		<div class="find_cl">';




// действия по типу
	if ($sub_dl_def['type']=='first_rent' || $sub_dl_def['type']=='extention') {

		$hello_w='Первоначальная выдача напрокат:';
		$prev_tarif='';//использованный тариф для продления
		$from_w='Дата выдачи/выезда';
		$to_w='Дата возврата';

		if ($sub_dl_def['type']=='extention') {

			$from_w='Продление с';
			$to_w='Продление по';

			$hello_w='Продление:';

            $mysqli = Db::getInstance()->getConnection();

			//запрос информации по суб.сделке
			$query_sub_dl_def2 = "SELECT * FROM rent_sub_deals_act WHERE `deal_id`='".$sub_dl_def['deal_id']."' AND `type` IN ('first_rent', 'extention') ORDER BY sub_deal_id DESC";
			$result_sub_dl_def2 = $mysqli->query($query_sub_dl_def2);
			if (!$result_sub_dl_def2) {
                die('Сбой при доступе к базе данных: ' . $query_sub_dl_def2 . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
			$sub_dl_def2=$result_sub_dl_def2->fetch_assoc();
			$sub_dl_def2=$result_sub_dl_def2->fetch_assoc(); 	// два раза, т.к. уже рассматриваем текущую сделку для курьера, а нам нужен последний примененный тариф

			$prev_tarif='
				<strong>Последний использованный тариф:</strong><br />
					<table border="1" cellspacing="0">
					<tr><td>
						<input type="hidden" value="1" id="kol_vo_'.$sub_dl_id.'_old" /><input type="hidden" value="1" id="kol_vo_min_'.$sub_dl_id.'_old" /><input type="hidden" value="'.$sub_dl_def2['tarif_step'].'" id="step_'.$sub_dl_id.'_old" />
				   		'.$sub_dl_def2['tarif_value'].' руб. в '.tenor_print($sub_dl_def2['tarif_step'], 'd').' <input type="hidden" value="'.$sub_dl_def2['tarif_value'].'" id="rent_per_step_'.$sub_dl_id.'_old" /></td>
						<td><input type="button" name="button" id="button" value="Выбрать" onclick="apply_tarif(\\\'old\\\', \\\''.$sub_dl_id.'\\\'); return false;" />
					</td></tr>
		</table><br/>

					';

		}

	$item_output.='


		<font style="color:green; font-size:18px;">'.$hello_w.'</font>
				<table border="1" cellspacing="0">
				  <tr>
				    <td>Товар:</td>
					<td>'.addslashes($cat_def['dog_name']).' '.addslashes($model_def['producer']).', модель: '.addslashes($model_def['model']).addslashes($color).'</td>
				  </tr>

				  <tr>
				    <td>Комплектация:</td>
					<td><input name="deal_item_set" type="text" size="80" value="'.addslashes($dl_def['deal_set']).'" /></td>
				  </tr>

				  <tr>
				    <td>Оценочная стоимость:</td>
					<td>'.$model_def['agr_price'].' '.$model_def['agr_price_cur'].'</td>
				  </tr>
				</table>

<input type="hidden" name="sub_deal_id" id="sub_deal_id_'.$sub_dl_id.'" value="'.$sub_dl_id.'" />
'.$from_w.':<input type="date" name="start_date" id="start_date_'.$sub_dl_id.'" value="'.date("Y-m-d", $sub_dl_def['from']).'" '.($sub_dl_def['type']=='extention' ? 'readonly="readonly"' : '').' /> '.($sub_dl_def['type']=='extention' ? 'Дата выезда курьера: <input type="date" name="viezd_date" id="viezd_date_'.$sub_dl_id.'" value="'.date("Y-m-d", $sub_dl_def['acc_date']).'"/>' : '').'<br />
		'.$prev_tarif.'

'.$tarif_code.'

Тариф:<input type="number" step="0.01" name="rent_tarif" id="rent_tarif_'.$sub_dl_id.'" value="'.$sub_dl_def['tarif_value'].'" />бел. руб.
	<input type="hidden" name="tarif_id" id="tarif_id_'.$sub_dl_id.'" value="'.$sub_dl_def['tarif_id'].'" />	<!--сохранение выбранного тарифа-->

<select name="step" id="step_'.$sub_dl_id.'">
	<option value="day" '.($sub_dl_def['tarif_step']=='day' ? 'selected="selected"' : '').'>в день</option>
	<option value="week" '.($sub_dl_def['tarif_step']=='week' ? 'selected="selected"' : '').'>в неделю</option>
	<option value="month" '.($sub_dl_def['tarif_step']=='month' ? 'selected="selected"' : '').'>в месяц</option>
</select>
количество (д/н/м - по тарифу):<input type="text" name="rent_tenor" id="rent_tenor_'.$sub_dl_id.'" value="'.$sub_dl_def['rent_tenor'].'" size="10" /><br/>
<input type="button" value="пересчитать" id="calc_button" onclick="calculate(\\\''.$sub_dl_id.'\\\'); return false;" /><br />

Стоимость аренды:<input type="number" step="0.01" name="r_to_pay" id="r_to_pay_'.$sub_dl_id.'" size="10" value="'.$sub_dl_def['r_to_pay'].'" /> бел. руб.
Стоимость доставки:<input type="number" step="0.01" name="del_to_pay" id="del_to_pay_'.$sub_dl_id.'" size="10" value="'.$sub_dl_def['delivery_to_pay'].'" /> бел. руб. <br />

'.$to_w.':<input type="date" name="return_date" id="return_date_'.$sub_dl_id.'" value="'.date("Y-m-d", $sub_dl_def['to']).'"/><br />

Оплата аренды:<input type="number" step="0.01" name="r_paid" id="r_paid_'.$sub_dl_id.'" size="10" value="'.$sub_dl_def['r_to_pay'].'" /> бел. руб.
		<select name="rent_payment_type" id="rent_payment_type_'.$sub_dl_id.'" onclick="ch_num_ch(\\\''.$sub_dl_id.'\\\');">
					<option value="no_payment">не оплачено</option>
					<option value="nal_no_cheque">нал без чека</option>
					<option value="nal_cheque">нал с чеком</option>
					<option value="card">карточка</option>
					<option value="bank">банк</option>
		</select><br />
Оплата доставки:<input type="number" step="0.01" name="del_paid" id="del_paid_'.$sub_dl_id.'" size="10" value="'.$sub_dl_def['delivery_to_pay'].'" /> бел. руб.
		<select name="del_payment_type" id="del_payment_type_'.$sub_dl_id.'" onclick="ch_num_ch(\\\''.$sub_dl_id.'\\\');">
					<option value="no_payment">не оплачено</option>
					<option value="nal_no_cheque">нал без чека</option>
					<option value="nal_cheque">нал с чеком</option>
					<option value="card">карточка</option>
					<option value="bank">банк</option>
		</select>
<span id="ch_num_span" style="display:none;"><br /> № документа (аренда и доставка):<input type="text" name="ch_num" id="ch_num" value="" size="10" /></span>

'.(substr($dl_def['item_inv_n'], 0, 3)=='702' ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>Для карнавальных костюмов:</b>
залог: <input type="text" name="coll_amount" value="" size="5" />
<select name="coll_cur" id="coll_cur_id_'.$sub_dl_id.'">
		    	<option value="TBYR">бел.руб.</option>
                <option value="USD">USD</option>
		    	<option value="EUR">EUR</option>
		    	<option value="RUB">рос. руб.</option>
</select>' : '').'
<br />

Дополнительная информация по сделке:<br/> <textarea cols="100" rows="3" name="deal_info" id="deal_info_'.$sub_dl_id.'">'.addslashes($sub_dl_def['info']).'</textarea><br />

Курьер:
<select name="courier_id" id="courier_id_'.$sub_dl_id.'">

	';

	while ($cur=$result_cur->fetch_assoc()) {
		$item_output.='<option value="'.$cur['logpass_id'].'" '.($cur['logpass_id']==$sub_dl_def['courier_id'] ? 'selected="selected"' : '').'>'.$cur['lp_fio'].'</option>';
	}
	$item_output.='</select>';



	$item_output.='<br />
			<input type="submit" name="action" id="action_main" value="сохранить выезд"  onclick="return save_first(\\\''.$sub_dl_id.'\\\');" /> (=выезд осуществлен, оплата (при наличии) получена)<br />
			<!--<input type="submit" name="action" id="action_main" value="обновить информацию" /> (=выезд НЕ осуществлен, оплата НЕ получена)<br />-->
			<input type="button" value="отмена" id="cl_cans_'.$sub_dl_id.'" onclick="cans(\\\'post_div_'.$sub_dl_id.'\\\'); return false;" />

	<!--Это для приемственности вида после одновления/забора -->
	<input type="hidden" name="one_sub_dl_id" id="one_sub_dl_id_new_'.$sub_dl_id.'" value="" />
	<input type="hidden" name="action_per" id="action_per_'.$sub_dl_id.'" value="" />
	<input type="hidden" name="cur_show" id="cur_show_'.$sub_dl_id.'" value="" />
	<input type="date" style="display:none" name="i_from_date" id="i_from_date_'.$sub_dl_id.'" value="" />
	<input type="date" style="display:none" name="i_to_date" id="i_to_date_'.$sub_dl_id.'" value="" />


			</div>
			</form>';



	}






if ($sub_dl_def['type']=='cur_return') {

	$past_due=pay_calc($sub_dl_id, strtotime(date("Y-m-d")));
	$past_due<0 ? $past_due=$past_due : $past_due=0;

	$total_rent=($dl_def['r_paid']-$dl_def['r_to_pay']+$past_due);
	$total_del=($dl_def['delivery_to_pay']-$dl_def['delivery_paid']-$sub_dl_def['delivery_to_pay']);


		$item_output.='

	Оформление возврата:<br />

		<input type="hidden" name="sub_dl_id" value="'.$sub_dl_id.'" />
		<input type="hidden" name="active_deal_id" value="'.$dl_def['deal_id'].'" />
		<input type="hidden" name="to_pay_pastdue" value="'.(-$past_due).'" />

	<table border="1" cellspacing="0">
	<tr>
		<td>
		<div id="pay_status_'.$sub_dl_id.'">
				<table border="1" cellspacing="0">
					<tr>
						<td></td>
						<td>аренда</td>
						<td>доставка</td>
						<td><strong>итого</strong></td>
					</tr>
					<tr>
						<td>по сделке (- нам, + мы)</td>
						<td><input type="text" id="dolg_rent_'.$sub_dl_id.'" readonly="readonly" disabled="disabled" size="2" value="'.($dl_def['r_paid']-$dl_def['r_to_pay']).'" /></td>
						<td><input type="text" id="dolg_deliv_'.$sub_dl_id.'" readonly="readonly" disabled="disabled" size="2" value="'.($dl_def['delivery_to_pay']-$dl_def['delivery_paid']).'" /></td>
						<td><input type="text" id="dolg_deal_total_'.$sub_dl_id.'" readonly="readonly" disabled="disabled" size="2" value="'.($dl_def['r_paid']-$dl_def['r_to_pay']+$dl_def['delivery_to_pay']-$dl_def['delivery_paid']).'" /></td>
					</tr>
					<tr>
						<td>за просрочку и забор</td>
						<td><input type="text" id="dolg_past_due_rent_'.$sub_dl_id.'" readonly="readonly" disabled="disabled" size="2" value="'.pay_calc($sub_dl_id, strtotime(date("Y-m-d"))).'"  /></span></td>
						<td>-<input type="text" id="dolg_past_due_deliv_'.$sub_dl_id.'" name="del_to_pay" value="'.round($sub_dl_def['delivery_to_pay'], 2).'" size="1" /></td>
						<td><input type="text" id="dolg_past_due_total_'.$sub_dl_id.'" readonly="readonly" disabled="disabled" size="2" value="'.($past_due-$sub_dl_def['delivery_to_pay']).'" /></td>
					</tr>
					<tr>
						<td><strong>Итого:</strong></td>
						<td><input type="text" id="dolg_total_rent_'.$sub_dl_id.'" readonly="readonly" disabled="disabled" size="2" value="'.$total_rent.'" /></td>
						<td><input type="text" id="dolg_total_deliv_'.$sub_dl_id.'" readonly="readonly" disabled="disabled" size="2" value="'.$total_del.'" /></td>
						<td><input type="text" id="dolg_total_'.$sub_dl_id.'" readonly="readonly" disabled="disabled" size="2" value="'.($total_rent+$total_del).'" /></td>
					</tr>
				</table>


				</div>
		дата возврата:<input type="date" name="start_date" id="start_date_'.$sub_dl_id.'" value="'.date("Y-m-d", $sub_dl_def['from']).'"/><input type="button" value="пересчитать просрочку" onclick="past_due_recalc(\\\''.$sub_dl_def['sub_deal_id'].'\\\');" /><br />
		состояние:
				<select name="ret_status" id="return_status_'.$sub_dl_id.'" >
					<option value="ok">приемлемое состояние</option>
					<option value="not_ok">повреждения, поломки, некомплект</option>
				</select><br />

	прокат оплата(+)/возврат(-): <input type="number" step="0.01" value="'.(-$total_rent).'" name="ret_payment_amount" id="ret_payment_amount_'.$sub_dl_id.'" size="5" /> бел. руб.

			<select name="return_p_kassa" id="return_p_kassa_'.$sub_dl_id.'" onclick="ch_num_ch_ret(\\\''.$sub_dl_id.'\\\');">
				<option value="no_payment">не оплачено</option>
				<option value="nal_no_cheque">нал без чека</option>
				<option value="nal_cheque">нал с чеком</option>
				<option value="card">карточка</option>
				<option value="bank">банк</option>
			</select> <br />

				Оплачено за курьера:<input type="number" step="0.01" name="delivery_paid" id="delivery_price_'.$sub_dl_id.'" size="10" value="'.(-$total_del).'" /> бел. руб.
				<select name="return_p_kassa_deliv" id="return_p_kassa_deliv_'.$sub_dl_id.'" onclick="ch_num_ch_ret(\\\''.$sub_dl_id.'\\\');">
					<option value="no_payment">не оплачено</option>
					<option value="nal_no_cheque">нал без чека</option>
					<option value="nal_cheque">нал с чеком</option>
					<option value="card">карточка</option>
					<option value="bank">банк</option>
				</select>
				<span id="ch_num_span" style="display:none;"><br /> № документа (аренда и доставка):<input type="text" name="ch_num" id="ch_num" value="" size="10" /></span>
				<br />
		Дополнительная информация/комментарии по некомплекту: <br /><textarea name="sub_deal_info" id="sub_deal_info_'.$sub_dl_id.'" cols="100" rows="3">'.addslashes($sub_dl_def['info']).'</textarea><br />
		Комплектация при возврате:<input name="deal_item_set" type="text" size="80" value="'.addslashes($dl_def['deal_set']).'" /><br />
		Завез на офис:<select name="of_select" id="of_select_'.$sub_dl_id.'">
				<option value="0">не выбран</option>
				<option value="1">Литературная</option>
				<option value="2">Ложинская</option>
				<option value="3">Победителей</option>
			</select><br />
		Курьер:
		<select name="courier_id" id="courier_id_'.$sub_dl_id.'">

		';

	while ($cur=$result_cur->fetch_assoc()) {
		$item_output.='<option value="'.$cur['logpass_id'].'" '.($cur['logpass_id']==$sub_dl_def['courier_id'] ? 'selected="selected"' : '').'>'.$cur['lp_fio'].'</option>';
	}
	$item_output.='</select> <br />


			<input type="submit" name="action" value="сохранить возврат" onclick="return ret_save(\\\''.$sub_dl_id.'\\\');" />(=выезд осуществлен, оплата (при наличии) получена)<br />
			<!--<input type="submit" name="action" value="обновить информацию" />(=выезд осуществлен, оплата (при наличии) получена)<br />-->
			<input type="button" value="отмена" id="cl_cans_'.$sub_dl_id.'" onclick="cans(\\\'post_div_'.$sub_dl_id.'\\\'); return false;" />

				<select name="tovar_status" id="tovar_status_'.$sub_dl_id.'" style="display:none;">
					<option value="to_rent">доступен для сдачи</option>
					<option value="stop">не доступен для сдачи</option>
				</select>


								<br />
		</td>
	</tr>


</table>

	<!--Это для приемственности вида после одновления/забора -->
	<input type="hidden" name="one_sub_dl_id" id="one_sub_dl_id_new_'.$sub_dl_id.'" value="" />
	<input type="hidden" name="action_per" id="action_per_'.$sub_dl_id.'" value="" />
	<input type="hidden" name="cur_show" id="cur_show_'.$sub_dl_id.'" value="" />
	<input type="date" style="display:none" name="i_from_date" id="i_from_date_'.$sub_dl_id.'" value="" />
	<input type="date" style="display:none" name="i_to_date" id="i_to_date_'.$sub_dl_id.'" value="" />



				</div>
			</form>';
}







		$item_output=str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет



		echo'
			ch_result=\'ok\';
			document.getElementById(\'post_div_'.$sub_dl_id.'\').innerHTML=\''.$item_output.'\';
			document.getElementById(\'one_sub_dl_id_new_'.$sub_dl_id.'\').value=document.getElementById(\'one_sub_dl_id_old_'.$sub_dl_id.'\').value;
			document.getElementById(\'action_per_'.$sub_dl_id.'\').value=document.getElementById(\'prev_action\').value;
			document.getElementById(\'cur_show_'.$sub_dl_id.'\').value=document.getElementById(\'cur_show\').value;
			document.getElementById(\'i_from_date_'.$sub_dl_id.'\').value=document.getElementById(\'i_from_date\').value;
			document.getElementById(\'i_to_date_'.$sub_dl_id.'\').value=document.getElementById(\'i_to_date\').value;
			';





	break;

	case 'past_due_calc':

		$ret_date=strtotime($ret_date);

		$pas_due_amount=pay_calc($sub_dl_id, $ret_date);
		$pas_due_amount<0 ? $pas_due_amount=$pas_due_amount : $pas_due_amount=0;

		$item_output='
				document.getElementById(\'dolg_past_due_rent_\'+sub_id).value=\''.$pas_due_amount.'\';';



		$item_output=str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет
		echo $item_output;

		break;


	case 'pay_status':




		$item_output='А тут мы выведем информацию о платжеах!';

		$item_output=str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет

		echo'
				ch_result=\'ok\';
				document.getElementById(\'pay_status_'.$sub_dl_id.'\').innerHTML=\''.$item_output.'\';
			';




	default:
		;
	break;
}



function pay_calc($sub_dl_id, $ret_date) {
    $mysqli = Db::getInstance()->getConnection();

	//запрос информации по суб.сделке
	$query_sub_dl_def = "SELECT * FROM rent_sub_deals_act WHERE `sub_deal_id`='$sub_dl_id'";
	$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
	if (!$result_sub_dl_def) {
        die('Сбой при доступе к базе данных: ' . $query_sub_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
	$sub_dl_def=$result_sub_dl_def->fetch_assoc();

	//запрос информации о сделке
	$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."'";
	$result_dl_def = $mysqli->query($query_dl_def);
	if (!$result_dl_def) {
        die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
	$dl_def=$result_dl_def->fetch_assoc();

	//вытягиваем последний примененный тариф
	$query_sub_dl_tarif = "SELECT * FROM rent_sub_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."' AND type IN ('first_rent', 'extention') ORDER BY `from` DESC";
	$result_sub_dl_tarif = $mysqli->query($query_sub_dl_tarif);
	if (!$result_sub_dl_tarif) {
        die('Сбой при доступе к базе данных: ' . $query_sub_dl_tarif . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
	$sub_dl_tarif=$result_sub_dl_tarif->fetch_assoc();




	//расчет платы за просрочку
	if ($ret_date>$dl_def['return_date']) {
		$morepay='просрочка';
		switch ($sub_dl_tarif['tarif_step']) {
			case 'month':

				if (date("j",$ret_date)>=date("j",$dl_def['return_date'])) { //вариант расчета, если текущий день равен, либо больше дня возврата
					$m_dif=(date("Y",$ret_date)*12+date("n",$ret_date))-(date("Y",$dl_def['return_date'])*12+date("n",$dl_def['return_date'])); // считаем разницу в месяцах
					$day_rent=$sub_dl_tarif['tarif_value']/30;
					$to_pay_ad=-($m_dif*$sub_dl_tarif['tarif_value']+(date("j",$ret_date)-date("j",$dl_def['return_date']))*$day_rent);
					$morepay=round($to_pay_ad, 2);
				}

				if (date("j",$ret_date)<date("j",$dl_def['return_date'])) { //вариант расчета, если текущий менее дня возврата
					$m_dif=(date("Y",$ret_date)*12+date("n",$ret_date)-1)-(date("Y",$dl_def['return_date'])*12+date("n",$dl_def['return_date'])); // считаем разницу в месяцах
					$day_rent=$sub_dl_tarif['tarif_value']/30;
					$to_pay_ad=-($m_dif*$sub_dl_tarif['tarif_value']+(date("j",$ret_date)+date("t",$dl_def['return_date'])-date("j",$dl_def['return_date']))*$day_rent);
					$morepay=round($to_pay_ad, 2);
				}
				break;

			case 'week';
			$day_dif=floor(($ret_date-$dl_def['return_date'])/60/60/24);
			$week_dif=floor($day_dif/7);
			$day_dif_left=$day_dif-$week_dif*7;
			$day_tarif=$sub_dl_tarif['tarif_value']/7;
			$to_pay_ad=-($week_dif*$sub_dl_tarif['tarif_value']+$day_dif_left*$day_tarif);
			$morepay=round($to_pay_ad, 2);

			break;

			case 'day':

				$day_dif=floor(($ret_date-$dl_def['return_date'])/60/60/24);
				$to_pay_ad=-($day_dif*$sub_dl_tarif['tarif_value']);
				$morepay=round($to_pay_ad, 2);

				break;


			default:
				echo 'не считает функция просрочки';
				break;
		}



	}
	elseif ($ret_date==$dl_def['return_date']) {
		$morepay='срок возврата сегодня';
		$to_pay_ad='0';
	}
	else {
		$morepay='срок не наступил';
		$to_pay_ad='0';
	}





	return $morepay;
}// end of past calc func





function get_post($var)
{
    $mysqli = Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
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


function tenor_print($step_name, $value) {

	switch ($step_name) {

		case 'day':

			if ($value=='1') {return 'день';}
			elseif ($value=='0') {return 'дней';}
			elseif ($value>1 && $value <5) {return 'дня';}
			elseif ($value>4 && $value <20) {return 'дней';}
			elseif ($value=='d') {return 'день';}

			break;


		case 'week':

			if ($value=='1') {return 'неделя';}
			elseif ($value=='0') {return 'недели';}
			elseif ($value>1 && $value <5) {return 'недели';}
			elseif ($value>4 && $value <20) {return 'недель';}
			elseif ($value=='d') {return 'неделю';}

			break;


		case 'month':

			if ($value=='1') {return 'месяц';}
			elseif ($value=='0') {return 'месяцев';}
			elseif ($value>1 && $value <5) {return 'месяца';}
			elseif ($value>4 && $value <20) {return 'месяцев';}
			elseif ($value=='d') {return 'месяц';}

			break;

	}//end of switch
}//end of function



?>
