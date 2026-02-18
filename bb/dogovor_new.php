<?php

use bb\Base;
use bb\classes\Deal;
use bb\Db;
use bb\models\User;

session_start();
ini_set("display_errors", 0);
error_reporting(E_ALL) & ~E_DEPRECATED;

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/bron.php'); // включаем класс
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Deal.php'); // включаем класс
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php'); // включаем класс
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Permission.php'); // включаем класс
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/tovar.php'); // включаем класс
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php'); // включаем класс
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Delivery.php'); // включаем подключение к базе данных


//!!! селать обработку всех входящих числовых параметров на замену запятой на точку (в т.ч. в Java скриптах)

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Signature.php'); //
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

	<div class="top_menu">
		<a class="div_item" href="/bb/index.php">Залогиниться</a>
	</div>

	</body></html>');
}

//-----------proverka paroley

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	$dl_id = Base::GetPost('active_deal_id');
	$dl_info = Base::GetPost('deal_item_set');

	$mysqli = Db::getInstance()->getConnection();
	$q = "UPDATE rent_deals_act SET deal_set='$dl_info' WHERE deal_id=$dl_id";
	$result = $mysqli->query($q);
	if (!$result) {
		$res['status'] = 'not_ok';
		$res['param'] = 'Сбой при доступе к базе данных: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
	} else {
		$res['status'] = 'ok';
		$res['result'] = $dl_id;
	}

	$result = json_encode($res);
	echo $result;

	exit();
}


if (!isset($_POST['action']) || $_POST['action'] != 'распечатать договор') {
	echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<!-- JQuery -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<style>
.radio-toolbar input[type="radio"] {
  display: none;
}
.radio-toolbar label {
  display: inline-block;
  background-color: #00adef;
  padding: 7px 10px;
  font-family: Arial;
  font-size: 16px;
  cursor: pointer;
    border-radius:10px;
	-webkit-border-radius:10px;
	-moz-border-radius:10px;
	-khtml-border-radius:10px;
}

.radio-toolbar input[type="radio"]:checked+label {
  background-color: yellow;
}

</style>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<link href="/bb/dogovor_new_style.css" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>База. Новый договор</title>
';
	echo Base::getBarCodeReaderScript('new_dogovor');
	?>

	<script language="javascript">

		history.pushState(null, null, location.href);
		window.onpopstate = function (event) {
			history.go(1);
		};


		function getXmlHttp() {
			var xmlhttp;
			try {
				xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
			} catch (e) {
				try {
					xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
				} catch (E) {
					xmlhttp = false;
				}
			}
			if (!xmlhttp && typeof XMLHttpRequest != 'undefined') {
				xmlhttp = new XMLHttpRequest();
			}
			return xmlhttp;
		}//end of getXmlHttp


		function hist_a_show(deal_id) {
			// значек ожидания - чтобы было видно запрос в процессе
			document.getElementById('a_hist_div').innerHTML = '<img src="w.gif" />';

			action_type = 'hist_a_show';

			var xmlhttp = getXmlHttp()
			xmlhttp.open("POST", '/bb/item_ch_new_arch.php', true)
			xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

			var params = 'deal_id=' + encodeURIComponent(deal_id) + '&action_type=' + encodeURIComponent(action_type);

			xmlhttp.send(params);
			xmlhttp.onreadystatechange = function () {
				if (xmlhttp.readyState == 4) {
					if (xmlhttp.status == 200) {

						//alert (xmlhttp.responseText);
						eval(xmlhttp.responseText);

					}
				}
			}
		}//end of hist_a_show function

		function predzakaz() {
			var client_id;

			if (document.getElementById('client_id').value) {
				client_id = document.getElementById('client_id').value;
			}
			else {
				client_id = 0;
			}

			item_inv_n = document.getElementById('item_inv_n').value;


			if (client_id <= 0 || item_inv_n <= 0) {
				alert('Выберите клиента и\или введите корректный инвентарный номер!');
			}
			else {
				var params = 'inv_n=' + encodeURIComponent(item_inv_n) + '&client_id=' + encodeURIComponent(client_id);
				window.open("/bb/predzakaz.php?" + params);
			}

		}


		function chose_item(action_type, bron) {
			// значек ожидания - чтобы было видно запрос в процессе
			if (action_type == "select") {
				document.getElementById('deal_div').innerHTML = '<img src="w.gif" />';
			}

			if (action_type == "extend" || action_type == "payment" || action_type == "return" || action_type == "cur_return") {
				document.getElementById('ext_div').innerHTML = '<img src="w.gif" />';
			}

			if (action_type == "arch_hist") {
				document.getElementById('arch_hist_div').innerHTML = '<img src="w.gif" />';
			}

			// для истории клиента из архива
			if (document.getElementById('client_id').value) {
				client_id = document.getElementById('client_id').value;
			}
			else {
				client_id = '';
			}

			document.getElementById('inv_n_in_find').value = document.getElementById('item_inv_n').value;

			item_id = document.getElementById('item_inv_n').value;

			var xmlhttp = getXmlHttp()
			xmlhttp.open("POST", '/bb/item_ch_new.php', true)
			xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

			var params = 'item_inv_n=' + encodeURIComponent(item_id) + '&action_type=' + encodeURIComponent(action_type) + '&client_id=' + encodeURIComponent(client_id) + '&bron=' + encodeURIComponent(bron);

			xmlhttp.send(params);
			xmlhttp.onreadystatechange = function () {
				if (xmlhttp.readyState == 4) {
					if (xmlhttp.status == 200) {

						//alert (xmlhttp.responseText);
						eval(xmlhttp.responseText);

						if (ch_result == 'no') {
							document.getElementById('inv_n_ok').value = 'no';
							alert('Товар с таким инвентарным номером не найден!');
						}
						if (ch_result == 'ok') {
							document.getElementById('inv_n_ok').value = 'ok';
						}


					}
				}
			}
		}// end of choose_item


		function chose_del(sub_type, id, p_from) {

			var xmlhttp = getXmlHttp()
			xmlhttp.open("POST", '/bb/del_ch.php', true)
			xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

			var params = 'sub_id=' + encodeURIComponent(id) + '&action_type=' + encodeURIComponent('delete') + '&sub_type=' + encodeURIComponent(sub_type) + '&p_from=' + encodeURIComponent(p_from);

			xmlhttp.send(params);
			xmlhttp.onreadystatechange = function () {
				if (xmlhttp.readyState == 4) {
					if (xmlhttp.status == 200) {

						eval(xmlhttp.responseText);
						chose_item('select', '');
						if (sub_type == 'payment') {
							alert('Оплата успешно удалена');
						}
						if (sub_type == 'extention') {
							alert('Продление успешно удалено');
						}

					}
				}
			}
		}// end of choose_del


		function copy_addr() {
			document.getElementById('reg_str').value = document.getElementById('str').value;
			document.getElementById('reg_dom').value = document.getElementById('dom').value;
			document.getElementById('reg_kv').value = document.getElementById('kv').value;
			document.getElementById('reg_city').value = document.getElementById('city').value;
		}


		function br_select(kb_id) {
			start1_date = new Date(document.getElementById('start_date').value);
			from1_date = new Date(document.getElementById('br_d_from_' + kb_id).value);

			document.getElementById('br_reg').value = kb_id;

			if (start1_date == from1_date) {
				document.getElementById('now_later').value = 'now'
				takeaway_show();
			}
			else {
				document.getElementById('now_later').value = 'later'
				takeaway_show();
			}

			if (document.getElementById('now_later').value == 'now') {
				document.getElementById('start_date').value = document.getElementById('br_d_from_' + kb_id).value;
			}

			document.getElementById('takeaway_date').value = document.getElementById('br_d_from_' + kb_id).value;
			document.getElementById('br_hour_from').value = document.getElementById('br_h_from_' + kb_id).value;
			document.getElementById('return_date').value = document.getElementById('br_d_to_' + kb_id).value;
			document.getElementById('br_hour_to').value = document.getElementById('br_h_to_' + kb_id).value;

			let from1 = new Date(document.getElementById('takeaway_date').value);
			from1.setHours(document.getElementById('br_hour_from').value, 0);
			let to1 = new Date(document.getElementById('return_date').value);
			to1.setHours(document.getElementById('br_hour_to').value, 0);
			let dayDiff = Math.ceil((to1 - from1) / 1000 / 60 / 60 / 24);

			document.getElementById('rent_tenor').value = dayDiff;


			document.getElementById('r_to_pay').value = '';
			document.getElementById('rent_tarif').value = '';

		}//end of function



		function hide_client() {

			disable('family');
			disable('name');
			disable('otch');
			disable('str');
			disable('dom');
			disable('kv');
			disable('city');
			disable('pas_n');
			disable('pas_ln');
			disable('pas_date');
			disable('pas_who');
			disable('reg_str');
			disable('reg_dom');
			disable('reg_kv');
			disable('reg_city');
			disable('phone_1');
			disable('phone_2');
			disable('info');
			disable('address_copy');
			disable('action_save_cl');
			//disable('source_sel');


			if (document.getElementById('client_update').value == 0) { document.getElementById('client_update').value = 1; }
			else { document.getElementById('client_update').value = 0; }

			if (document.getElementById('cl_edit_button').value == 'редактировать информацию клиента') {
				document.getElementById('cl_edit_button').value = 'отменить редактирование информации клиента';
			}
			else { document.getElementById('cl_edit_button').value = 'редактировать информацию клиента'; }


		}



		function disable(id) {
			if (document.getElementById(id).disabled == true) {
				document.getElementById(id).disabled = false;
			}
			else {
				document.getElementById(id).disabled = true;
			}
		}

		function disable_deliv() {
			if (document.getElementById('del_ch_b').checked == true) {
				document.getElementById('delivery_price').value = 35;
				document.getElementById('delivery_price').disabled = false;
				document.getElementById('return_p_kassa_deliv').disabled = false;
			}
			else {
				document.getElementById('delivery_price').value = 0;
				document.getElementById('delivery_price').disabled = true;
				document.getElementById('return_p_kassa_deliv').disabled = true;
			}
		}



		function delivery_ch(id) {
			if (document.getElementById(id).disabled == true) {
				document.getElementById(id).value = 35;
				document.getElementById(id).disabled = false;
				document.getElementById('del_payment_type').disabled = false;

			}
			else {
				document.getElementById(id).disabled = true;
				document.getElementById('del_payment_type').disabled = true;
				document.getElementById(id).value = 0;
			}
		}


		function apply_tarif(tarif_id) {
			document.getElementById('rent_tarif').value = document.getElementById('rent_per_step_' + tarif_id).value;

			if (value = document.getElementById('step_' + tarif_id).value == 'day') { document.getElementById('step').value = 'day'; }
			if (value = document.getElementById('step_' + tarif_id).value == 'week') { document.getElementById('step').value = 'week'; }
			if (value = document.getElementById('step_' + tarif_id).value == 'month') { document.getElementById('step').value = 'month'; }

			document.getElementById('rent_tenor').value = document.getElementById('kol_vo_' + tarif_id).value;
			document.getElementById('tarif_id').value = tarif_id;

			calculate();
		}


		function calculate() {

			r_step = document.getElementById('step').value;
			rent_date = new Date(document.getElementById('start_date').value);
			if (document.getElementById('now_later') && document.getElementById('now_later').value == 'later') {
				rent_date = new Date(document.getElementById('takeaway_date').value);
			}

			r_tenor = document.getElementById('rent_tenor').value;

			switch (r_step) {
				case 'day':
					rent_date.setDate(rent_date.getDate() + (r_tenor) * 1);

					if (!document.getElementById('br_hour_from')) {//по карнавалам не пересчитываем дату, т.к. она вводится через брони + 25 часов = 2 дня
						document.getElementById('return_date').value = formatDate(rent_date);
					}
					document.getElementById('r_to_pay').value = (document.getElementById('rent_tarif').value * r_tenor).toFixed(2);

					if (document.getElementById('br_hour_from')) {//за 25 часов берем как за двое суток!!!
						if (document.getElementById('start_date').value == 'now') {
							start_date = new Date(document.getElementById('start_date').value);
						}
						else {
							start_date = new Date(document.getElementById('takeaway_date').value);
						}


						start_date.setHours(start_date.getHours() + (document.getElementById('br_hour_from').value) * 1);

						return_date = new Date(document.getElementById('return_date').value);
						return_date.setHours(return_date.getHours() + (document.getElementById('br_hour_to').value) * 1);

						hours_dif = (return_date - start_date) / 1000 / 60 / 60;
						days_dif = hours_dif / 24;
						pay_days = Math.ceil(days_dif);

						document.getElementById('rent_tenor').value = pay_days;
						document.getElementById('r_to_pay').value = (document.getElementById('rent_tarif').value * pay_days).toFixed(2);
					}


					break


				case 'week':
					rent_date.setDate(rent_date.getDate() + (r_tenor) * 7);
					document.getElementById('return_date').value = formatDate(rent_date);
					document.getElementById('r_to_pay').value = (document.getElementById('rent_tarif').value * r_tenor).toFixed(2);
					//document.getElementById('total_to_pay').value=document.getElementById('r_to_pay').value*1+document.getElementById('delivery_price').value*1;
					break

				case 'month':
					rent_date.setMonth(rent_date.getMonth() + (r_tenor) * 1);
					document.getElementById('return_date').value = formatDate(rent_date);
					document.getElementById('r_to_pay').value = (document.getElementById('rent_tarif').value * r_tenor).toFixed(2);
					//document.getElementById('total_to_pay').value=document.getElementById('r_to_pay').value*1+document.getElementById('delivery_price').value*1;
					break
			}

			if (document.getElementById('delivery_flag')) {
				document.getElementById('total_to_pay').value = document.getElementById('r_to_pay').value * 1 + document.getElementById('delivery_price').value * 1;
			}


		}

		function formatDate(date) {

			var dd = date.getDate()
			if (dd < 10) dd = '0' + dd;

			var mm = date.getMonth() + 1
			if (mm < 10) mm = '0' + mm;

			var yyyy = date.getFullYear();

			return yyyy + '-' + mm + '-' + dd;
		}

		//new tarif calculations
		function calculateNew() {
			//
			let perDay = document.querySelector('#rent_tarif');
			let total = document.querySelector('#r_to_pay');
			let oldTarifBase = document.querySelector('.tarifPrev');
			let oldTarif = false;

			if (oldTarifBase != undefined) {
				oldTarif = {
					days: oldTarifBase.dataset.days * 1,
					perDay: (Math.round((oldTarifBase.value / oldTarifBase.dataset.days) * 100) / 100),
					total: (Math.round((oldTarifBase.value / oldTarifBase.dataset.days) * 100) / 100) * oldTarifBase.dataset.days * 1,
				}
			}

			let days = getDayDiffDates();

			let totalPay = getRentToPay(days);
			if (oldTarif) {
				oldTotal = days * oldTarif.perDay;
				if (oldTotal < totalPay) totalPay = oldTotal;
			}

			let perDayPay = totalPay / days;

			perDay.value = perDayPay.toFixed(2);
			total.value = totalPay.toFixed(2);

			//console.log(tarifs, tarifsCopy);
		}

		function getRentToPay(days) {
			let tarifs = [];
			document.querySelectorAll('.tarif').forEach((el) => {
				tarifs.push({
					days: el.dataset.days * 1,
					perDay: (Math.round((el.value / el.dataset.days) * 100) / 100),
					total: (Math.round((el.value / el.dataset.days) * 100) / 100) * el.dataset.days * 1,
				});
			});
			tarifs.sort((a, b) => {
				return a.days - b.days;
			});

			let tarifsCopy = tarifs.map((x) => x);
			tarifsCopy.sort((a, b) => {
				return -a.days + b.days;
			});

			let theTarif = getTarifForDaysPeriod(days, tarifs);
			let dayTarif = getDayTarifForDaysPeriod(days, tarifs);
			let discountInput = document.querySelector('#discount');
			console.log(discountInput);
			let discount = 0;
			if (discountInput != undefined) {
				discount = Math.round(discountInput.value * 1);
				dayTarif = Math.round(dayTarif * (1 - discount / 100) * 100) / 100;
			}
			let amount = Math.round(days * dayTarif * 100) / 100;
			let ceilingAmount = getCeilingAmountForTarif(theTarif, tarifsCopy);
			//console.log(amount, ceilingAmount);
			if (ceilingAmount && amount > ceilingAmount) amount = ceilingAmount;
			//console.log(amount, ceilingAmount);
			return amount;
		}

		function getTarifForDaysPeriod(days, tarifs) {
			if (days < 1) return 0;

			let tarif = tarifs[0];

			tarifs.forEach((el) => {
				if (days >= el.days) {
					tarif = el;
				}
			});

			return tarif;
		}

		function getDayTarifForDaysPeriod(days, tarifs) {
			if (days < 1) return 0;

			let tarif = tarifs[0];

			tarifs.forEach((el) => {
				if (days >= el.days) tarif = el;
			});

			return tarif.perDay;
		}

		function getCeilingAmountForTarif(tar, tarifsCopy) {
			let tarif = false;
			tarifsCopy.forEach((el) => {
				if (el.days > tar.days) tarif = el;
			});

			if (tarif) {
				return tarif.total * 1;
			}
			else return false;

		}


		function daysChange() {
			let daysInput = document.querySelector('#rent_tenor');

			if (daysInput.value * 1 < 1) daysInput.value = 1;

			let date1Input = document.querySelector('#start_date');
			let date2Input = document.querySelector('#return_date');

			let date1 = new Date(date1Input.value);
			let date2 = new Date(date1Input.value);
			date2.setDate(date2.getDate() + daysInput.value * 1);
			let dateString = date2.getFullYear() + '-' + ("0" + (date2.getMonth() + 1)).slice(-2) + '-' + ("0" + date2.getDate()).slice(-2);
			date2Input.value = dateString;
			calculateNew();
		}

		function dateChange() {
			let daysInput = document.querySelector('#rent_tenor');

			daysInput.value = getDayDiffDates();
			calculateNew();
		}

		function getDayDiffDates() {
			let date1Input = document.querySelector('#start_date');
			let date2Input = document.querySelector('#return_date');


			let date1 = new Date(date1Input.value);
			let date2 = new Date(date2Input.value);


			if (document.querySelector('#now_later') && document.querySelector('#now_later').value == 'later') {
				let takeAwayInput = document.querySelector('#takeaway_date');
				let takeAwayDate = new Date(takeAwayInput.value);
				date1 = takeAwayDate;
			}


			let days = (date2.getTime() - date1.getTime()) / (1000 * 60 * 60 * 24);
			if (days > 1) return days;
			else return 1;
		}

		//end of new tarif calculation


		//проверка заполнения только клиента
		function form_check_cl() {
			valid = true;

			family = name = otch = city = str = dom = kv = pas_n = pas_who = pas_date = reg_city = reg_str = reg_dom = reg_kv = phone_1 = phone_2 = r_source = "";

			// проверка клиента
			if (document.getElementById('family').value == "") {
				family = "Фамилия, ";
				valid = false;
			}

			if (document.getElementById('name').value == "") {
				name = "Имя, ";
				valid = false;
			}

			if (document.getElementById('otch').value == "") {
				otch = "Отчество, ";
				valid = false;
			}

			if (document.getElementById('city').value == "") {
				city = "Адрес (город), ";
				valid = false;
			}

			if (document.getElementById('str').value == "") {
				str = "Адрес (улица), ";
				valid = false;
			}


			if (document.getElementById('dom').value == "") {
				dom = "Адрес (дом), ";
				valid = false;
			}

			if (document.getElementById('kv').value == "") {
				kv = "Адрес (квартира), ";
				valid = false;
			}

			if (document.getElementById('pas_n').value == "") {
				pas_n = "№ паспорта, ";
				valid = false;
			}

			if (document.getElementById('pas_date').value == "") {
				pas_date = "Дата выдачи паспорта, ";
				valid = false;
			}

			if (document.getElementById('pas_who').value == "") {
				pas_who = "орган, выдавший паспорт, ";
				valid = false;
			}

			if (document.getElementById('reg_city').value == "") {
				reg_city = "Прописка (город), ";
				valid = false;
			}

			if (document.getElementById('reg_str').value == "") {
				reg_str = "Прописка (улица), ";
				valid = false;
			}

			if (document.getElementById('reg_dom').value == "") {
				reg_dom = "Прописка (дом), ";
				valid = false;
			}

			if (document.getElementById('reg_kv').value == "") {
				reg_kv = "Прописка (квартира), ";
				valid = false;
			}

			if (document.getElementById('phone_1').value == "") {
				phone_1 = "Телефон №1, ";
				valid = false;
			}

			if (document.getElementById('phone_2').value == "") {
				phone_2 = "Телефон №2, ";
				valid = false;
			}



			if (valid == false) {
				alert('Заполните все поля формы! В частности: ' + family + name + otch + city + str + dom + kv + pas_n + pas_date + pas_who + reg_city + reg_str + reg_dom + reg_kv + phone_1 + phone_2 + r_source);
			}

			return valid;


		}



		//проверка заполнения клиента
		function form_check() {
			valid = true;

			family = name = otch = city = str = dom = kv = pas_n = pas_who = pas_date = reg_city = reg_str = reg_dom = reg_kv = phone_1 = phone_2 = inv_n_ok = start_date = rent_tarif = rent_tenor = r_to_pay = return_date = pl_date_ch = takeaway_info = r_source = set_is_checked = "";

			// проверка клиента
			if (document.getElementById('family').value == "") {
				family = "Фамилия, ";
				valid = false;
			}

			if (document.getElementById('name').value == "") {
				name = "Имя, ";
				valid = false;
			}

			if (document.getElementById('otch').value == "") {
				otch = "Отчество, ";
				valid = false;
			}

			if (document.getElementById('city').value == "") {
				city = "Адрес (город), ";
				valid = false;
			}

			if (document.getElementById('str').value == "") {
				str = "Адрес (улица), ";
				valid = false;
			}


			if (document.getElementById('dom').value == "") {
				dom = "Адрес (дом), ";
				valid = false;
			}

			if (document.getElementById('kv').value == "") {
				kv = "Адрес (квартира), ";
				valid = false;
			}

			if (document.getElementById('pas_n').value == "") {
				pas_n = "№ паспорта, ";
				valid = false;
			}

			if (document.getElementById('pas_date').value == "") {
				pas_date = "Дата выдачи паспорта, ";
				valid = false;
			}

			if (document.getElementById('pas_who').value == "") {
				pas_who = "орган, выдавший паспорт, ";
				valid = false;
			}

			if (document.getElementById('reg_city').value == "") {
				reg_city = "Прописка (город), ";
				valid = false;
			}

			if (document.getElementById('reg_str').value == "") {
				reg_str = "Прописка (улица), ";
				valid = false;
			}

			if (document.getElementById('reg_dom').value == "") {
				reg_dom = "Прописка (дом), ";
				valid = false;
			}

			if (document.getElementById('reg_kv').value == "") {
				reg_kv = "Прописка (квартира), ";
				valid = false;
			}

			if (document.getElementById('phone_1').value == "") {
				phone_1 = "Телефон №1, ";
				valid = false;
			}

			if (document.getElementById('phone_2').value == "") {
				phone_2 = "Телефон №2, ";
				valid = false;
			}




			//проверка сдеки
			if (document.getElementById('inv_n_ok').value != "ok") {
				inv_n_ok = "Выберите товар, ";
				valid = false;
			}

			if (document.getElementById('start_date').value == "") {
				start_date = "Дата выдачи, ";
				valid = false;
			}

			if (document.getElementById('rent_tarif').value == "" || document.getElementById('rent_tarif').value == "0") {
				rent_tarif = "Тариф, ";
				valid = false;
			}

			if (document.getElementById('rent_tenor').value == "") {
				rent_tenor = "количество по тарифу, ";
				valid = false;
			}

			if (document.getElementById('r_to_pay').value == "" || document.getElementById('r_to_pay').value == "0") {
				r_to_pay = "Стоимость аренды, ";
				valid = false;
			}

			if (document.getElementById('return_date').value == "") {
				return_date = "Дата возврата, ";
				valid = false;
			}

			//set_is_checked
			if (document.getElementById('set_is_checked').checked == false) {
				set_is_checked = "Подтвердите комплектацию товара, ";
				valid = false;
			}

			if (document.getElementById('now_later') && document.getElementById('now_later').value == 'later') {

				if (!document.getElementById('br_hour_from')) {//тут прописываем без часов
					var today_d2 = new Date();
					var takeaway_date2 = new Date(document.getElementById('takeaway_date').value);
					var return_date2 = new Date(document.getElementById('return_date').value);
					if (takeaway_date2 > return_date2) {
						takeaway_info = 'дата возврата не может быть ранее даты плановой выдачи, ';
						valid = false;
					}
					if (takeaway_date2 < today_d2) {
						takeaway_info = takeaway_info + 'плановая дата выдачи не может быть в прошлом, ';
						valid = false;
					}
				}//end of br_hour if
				else {//тут прописываем с часами
					var today_d3 = new Date();
					today_d3.setHours(1);//ставим часы на 1 ночи, чтобы при опоздании на пару часов все-равно давало бы выдать.
					var takeaway_date3 = new Date(document.getElementById('takeaway_date').value);
					takeaway_date3.setHours(takeaway_date3.getHours() + document.getElementById('br_hour_from').value * 1);//добавляем часы

					var return_date3 = new Date(document.getElementById('return_date').value);
					return_date3.setHours(return_date3.getHours() + document.getElementById('br_hour_to').value * 1);//добавляем часы

					if (takeaway_date3 >= return_date3) {
						takeaway_info = 'дата И ВРЕМЯ возврата не может быть ранее, либо равно дате И ВРЕМЕНИ выдачи, ';
						valid = false;
					}
					if (takeaway_date3 < (today_d3)) {
						takeaway_info = takeaway_info + 'плановая дата выдачи не может быть в прошлом, ';
						valid = false;
					}
				}//end of else

			}//end of later if

			if (document.getElementById('now_later') && document.getElementById('now_later').value == 'now') {

				if (!document.getElementById('br_hour_from')) {//тут прописываем без часов
					var today_d2 = new Date();
					var takeaway_date2 = new Date(document.getElementById('start_date').value);
					var return_date2 = new Date(document.getElementById('return_date').value);
					if (takeaway_date2 > return_date2) {
						takeaway_info = 'дата возврата не может быть ранее даты выдачи, ';
						valid = false;
					}
					/*	if (takeaway_date2<today_d2) {
							takeaway_info=takeaway_info+'дата выдачи не может быть в прошлом, ';
							valid = false;
						}*/
				}//end of br_hour if
				else {//тут прописываем с часами
					var today_d3 = new Date();

					var takeaway_date3 = new Date(document.getElementById('start_date').value);
					takeaway_date3.setHours(takeaway_date3.getHours() + document.getElementById('br_hour_from').value * 1);//добавляем часы

					var return_date3 = new Date(document.getElementById('return_date').value);
					return_date3.setHours(return_date3.getHours() + document.getElementById('br_hour_to').value * 1);//добавляем часы

					if (takeaway_date3 >= return_date3) {
						takeaway_info = 'дата И ВРЕМЯ возврата не может быть ранее, либо равно дате И ВРЕМЕНИ выдачи, ';
						valid = false;
					}
					/*
					if (takeaway_date3<today_d3) {
						takeaway_info=takeaway_info+'дата выдачи не может быть в прошлом, ';
						valid = false;
					}*/
				}//end of else

			}//end of later if


			if (document.getElementById('action_save').value == 'сохранить продление') {
				var today_d = new Date();
				var pl_date = new Date(document.getElementById('payment_date').value);
				if (pl_date > today_d) {
					pl_date_ch = 'Дата платежа не может быть в будущем, ';
					valid = false;
				}
			}




			if (valid == false) {
				alert('Заполните все поля формы! В частности: ' + family + name + otch + city + str + dom + kv + pas_n + pas_date + pas_who + reg_city + reg_str + reg_dom + reg_kv + phone_1 + phone_2 + r_source + inv_n_ok + start_date + rent_tarif + rent_tenor + r_to_pay + return_date + pl_date_ch + takeaway_info + set_is_checked);
			}


			if (document.getElementById('action_save').value == "сохранить продление" && document.getElementById('rent_payment_type').value == "no_payment") {

				valid = false;
				alert('Невозможно сохранить продление без оплаты. Внесите канал оплаты!');

			}

			if (document.getElementById('action_save').value == "сохранить продление" && document.getElementById('rent_payment_type').value == 'multi' && (document.getElementById('rent_p_k1').value * 1 + document.getElementById('rent_p_k2').value * 1 + document.getElementById('rent_p_card').value * 1 + document.getElementById('rent_p_bank').value * 1) != document.getElementById('r_to_pay').value * 1) {
				alert('Сумма оплат по каналам не равна общей сумме мульти-оплаты!');
				valid = false;
			}


			if (valid == true) {
				//alert ('!OK');
				document.getElementById('action_save').style.display = "none";
				document.getElementById('action_delivery').style.display = "none";
				document.getElementById('inv_n_select_but').style.display = "none";

			}


			return valid

		}



		function new_payment() {

			valid = true;

			var today_d = new Date();
			var start_date = new Date(document.getElementById('from_date_value').value);
			var pl_date = new Date(document.getElementById('start_date').value);

			if (pl_date > today_d) {
				alert('Дата платежа не может быть в будущем!');
				valid = false;
			}

			if (pl_date < start_date) {
				alert('Дата платежа не может быть ранее даты сделки!');
				valid = false;
			}
			if (document.getElementById('rent_payment_type').value == 'multi' && (document.getElementById('rent_p_k1').value * 1 + document.getElementById('rent_p_k2').value * 1 + document.getElementById('rent_p_card').value * 1 + document.getElementById('rent_p_bank').value * 1) != document.getElementById('rent_payment').value * 1) {
				alert('Сумма оплат по каналам не равна общей сумме мульти-оплаты!');
				valid = false;
			}


			return valid;

		}


		function form_check_cur() {
			valid = true;

			city = str = dom = kv = phone_1 = inv_n_ok = start_date = r_source = "";

			// проверка клиента

			if (document.getElementById('city').value == "") {
				city = "Адрес (город), ";
				valid = false;
			}

			if (document.getElementById('str').value == "") {
				str = "Адрес (улица), ";
				valid = false;
			}


			if (document.getElementById('dom').value == "") {
				dom = "Адрес (дом), ";
				valid = false;
			}

			if (document.getElementById('kv').value == "") {
				kv = "Адрес (квартира), ";
				valid = false;
			}

			if (document.getElementById('phone_1').value == "") {
				phone_1 = "Телефон №1, ";
				valid = false;
			}


			//проверка сдеки
			if (document.getElementById('inv_n_ok').value != "ok") {
				inv_n_ok = "Выберите товар, ";
				valid = false;
			}

			if (document.getElementById('start_date').value == "") {
				start_date = "Дата выдачи, ";
				valid = false;
			}




			if (valid == false) {
				alert('Заполните все поля формы! В частности: ' + city + str + dom + kv + phone_1 + inv_n_ok + start_date + r_source);
			}
			else {
				document.getElementById('del_to_pay').value = prompt("Стоимость доставки (руб.коп.):");
			}

			return valid

		}






		function ret_ch() {
			var out_r = false;

			var deal_result = (document.getElementById('deal_result').value * 1 + document.getElementById('to_pay_pastdue').value * 1);

			var today_temp = new Date();
			var today = new Date(today_temp.getFullYear(), today_temp.getMonth(), today_temp.getDate());

			var ret_date_temp = new Date(document.getElementById('ret_date_value').value);
			var ret_date = new Date(ret_date_temp.getFullYear(), ret_date_temp.getMonth(), ret_date_temp.getDate());


			if (deal_result >= 0 && (ret_date - today) <= 0) {
				out_r = true;
			}

			if (deal_result < 0) {
				if (confirm('Внимание, у клиента долг в размере ' + deal_result + ' бел.руб. Оформить возврат без оплаты?')) {
					out_r = true;
				}
				else {
					out_r = false;
				}

			}

			if (deal_result >= 0 && ret_date > today) {
				if (confirm('Срок возврата еще не наступил. Оформляем возврат товара без возврата денег клиенту?')) {
					out_r = true;
				}
				else {
					out_r = false;
				}

			}

			return out_r;

		}


		function takeaway_show() {
			if (document.getElementById('now_later').value == "later") {
				document.getElementById('future_takeaway').style.display = "";
				document.getElementById('deliv_but_info').style.display = "none";

			}
			else {
				document.getElementById('future_takeaway').style.display = "none";
				document.getElementById('deliv_but_info').style.display = "";
			}
		}



		function hist_show() {

			if (document.getElementById('hist_table').style.display == "none") {
				document.getElementById('hist_table').style.display = "";
				document.getElementById('hist_button').value = "скрыть";
			}
			else {
				document.getElementById('hist_table').style.display = "none";
				document.getElementById('hist_button').value = "показать";
			}

		}

		function cl_displ() {

			if (document.getElementById('client_info_div').style.display == "none") {
				document.getElementById('client_info_div').style.display = "";
				document.getElementById('cl_displ_button').value = "Cкрыть информацию о клиенте";

			}
			else {
				document.getElementById('client_info_div').style.display = "none";
				document.getElementById('cl_displ_button').value = "Отобразить подробную информацию о клиенте";
			}
		}

		function checkSearchFields() {
			var container = document.getElementById('client_search_fields');
			if (!container) return;

			var inputs = container.querySelectorAll('input[type="text"]');
			var isFilled = false;
			for (var i = 0; i < inputs.length; i++) {
				if (inputs[i].value && inputs[i].value.trim() !== "") {
					isFilled = true;
					break;
				}
			}

			if (isFilled) {
				container.classList.add('is-filled');
			} else {
				container.classList.remove('is-filled');
			}
		}

		// Attach listeners when DOM is ready
		document.addEventListener("DOMContentLoaded", function (event) {
			var container = document.getElementById('client_search_fields');
			if (container) {
				var inputs = container.querySelectorAll('input[type="text"]');
				for (var i = 0; i < inputs.length; i++) {
					inputs[i].addEventListener('input', checkSearchFields);
					inputs[i].addEventListener('change', checkSearchFields);
				}
				// Initial check
				checkSearchFields();
			}
		});

		function ret_st() {

			if (document.getElementById('return_status').value == "ok") {
				document.getElementById('sub_deal_span').style.display = "none";
				document.getElementById('sub_deal_info').value = '';
			}
			else {
				document.getElementById('sub_deal_span').style.display = "";
			}

		}


		function ret_save() {

			valid = true;

			if (document.getElementById('return_status').value == 'not_ok' && document.getElementById('sub_deal_info').value == "") {
				alert('Заполните комментарии по некомплекту!')
				valid = false;
			}
			if ((document.getElementById('ret_payment_amount').value == "" || document.getElementById('ret_payment_amount').value == "0") && document.getElementById('return_p_kassa').value != "no_payment") {
				alert('Не проставлена сумма при выбранной кассе. Либо поставьте сумму, либо выберите "не оплачено"');
				valid = false;
			}
			if (document.getElementById('ret_payment_amount').value != "" && document.getElementById('ret_payment_amount').value != '0' && document.getElementById('return_p_kassa').value == "no_payment") {
				alert('Если указали сумму, то указывайте и кассу. Либо очистите сумму и выберите "не оплачено"');
				valid = false;
			}


			var today_d = new Date();
			var start_date = new Date(document.getElementById('from_date_value').value);
			var ret_date = new Date(document.getElementById('start_date').value);

			if (ret_date > today_d) {
				alert('Дата возврата не может быть в будущем!');
				valid = false;
			}

			if (ret_date < start_date) {
				alert('Дата возврата не может быть ранее даты сделки!');
				valid = false;
			}



			return valid;
		}

		function item_return() {

			if ((document.getElementById('deal_result').value * 1 + document.getElementById('to_pay_pastdue').value * 1) < 0) {
				if (confirm('У клиента долг в размере  ' + (document.getElementById('deal_result').value * 1 + document.getElementById('to_pay_pastdue').value * 1) + ' бел. руб. Необходимо сначала оформить продление / оплату, а потом возврат. Вы хотите оформить возврат не оплачено?')) {
					return true;
				}
				else {
					return false;
				}
			}

			if ((document.getElementById('deal_result').value * 1 + document.getElementById('to_pay_pastdue').value * 1) > 0) {
				if (confirm('У клиента переплата в размере  ' + (document.getElementById('deal_result').value * 1 + document.getElementById('to_pay_pastdue').value * 1) + ' бел. руб. Необходимо сначала оформить возврат денег, а потом возврат товара. Вы хотите оформить возврат не возвращая оплату?')) {
					return true;
				}
				else {
					return false;
				}

			}




		}//end of itemv return


		function deal_del() {

			if (confirm('Вы точно хотите удалить сделку целиком (т.е. сдачу, оплаты, продления ...)?')) {
				return true;
			}
			else {
				return false;
			}
		}


		function chose_item_cl(inv_n) {

			document.getElementById('item_inv_n').value = inv_n;
			chose_item('select', '');
		}


		function past_due_recalc(id) {

			document.getElementById('past_due_word').value = '---';

			var action_type = 'past_due_calc';
			var ret_date = document.getElementById('start_date').value;

			var xmlhttp = getXmlHttp()
			xmlhttp.open("POST", '/bb/item_ch_new.php', true)
			xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

			var params = 'deal_id=' + encodeURIComponent(id) + '&action_type=' + encodeURIComponent(action_type) + '&ret_date=' + encodeURIComponent(ret_date);

			xmlhttp.send(params);
			xmlhttp.onreadystatechange = function () {
				if (xmlhttp.readyState == 4) {
					if (xmlhttp.status == 200) {

						eval(xmlhttp.responseText);

						if (ch_result == 'no') {
							document.getElementById('inv_n_ok').value = 'no';
							alert('Глюк - сообщите Диме!');
						}

					}
				}
			}

		}



		function multi_ch() {

			if (document.getElementById('rent_payment_type').value == 'multi') {
				document.getElementById('multi_pay').style.display = "";
			}
			else {
				document.getElementById('multi_pay').style.display = "none";
			}

			if (document.getElementById('rent_payment_type').value == 'nal_cheque' || document.getElementById('rent_payment_type').value == 'card' || document.getElementById('rent_payment_type').value == 'bank') {
				document.getElementById('ch_num_span').style.display = "";
			}
			else {
				document.getElementById('ch_num_span').style.display = "none";
			}

		}

		function voz_ch() {
			if (document.getElementById('return_p_kassa').value == 'nal_cheque' || document.getElementById('return_p_kassa').value == 'card' || document.getElementById('return_p_kassa').value == 'bank') {
				document.getElementById('ch_num_span').style.display = "";
			}
			else {
				document.getElementById('ch_num_span').style.display = "none";
			}

		}


		function cnahge_set() {
			//alert('w');
			st = $("#deal_item_set");
			but = $("#set_ch_but");
			s_but = $("#save_ch_but");

			if (st.prop('disabled') == true) {
				st.prop('disabled', false);
				but.val('Отмена');
				s_but.show();
			}
			else {
				st.prop('disabled', true);
				but.val('Изменить комплектацию');
				s_but.hide();
			}
		}

		function save_set() {
			var $form = $("#main_form");
			//$("#qr_div_" + $tov_id).append('<img src="/bb/w2.gif" style="width: 70px;"/>');
			$.ajax(
				{
					type: $form.attr('method'),
					url: "/bb/dogovor_new.php",
					data: $form.serialize(),
				}
			).done(function (data) {
				var rez = JSON.parse(data);
				if (rez.status == 'ok') {
					alert('Изменения внесены.');
					cnahge_set();
				}
				else {
					alert('Ошибка:' + rez.param);
				}

				//alert (rez.result);

			});
		}

	</script>

	<style>
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

		/* new delivery date*/
		.hide {
			display: none;
		}
	</style>

	<?php
	echo '
</head>

<body>

<div class="user">
  <form name="выход" method="post" action="index.php">Вы зашли как: <strong> ' . $_SESSION['user_fio'] . '</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: ' . $_SESSION['office'] . '
  </form>
</div>

';
	include_once($_SERVER['DOCUMENT_ROOT'] . '/bb/bb_nav.php');
	echo '
<div class="row zv-row">
    <div class="col alert-danger h2 text-center" id="zv_div"></div>
</div>
';
	require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show2.php');
	//  require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам

	//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";


} // end of if not isset POST action or nor print of dogovor = start headers




// создание нулевых значенй по умолчанию - для устранения ошибок в выводе
// нулевые значения для полей поиска клиента
$s_family = NULL;
$s_name = NULL;
$s_otch = NULL;
$s_str = NULL;
$s_dom = NULL;
$s_kv = NULL;
$s_pas_n = NULL;
$s_client_id = NULL;
$s_ph = NULL;

$client_id = ''; // для вывода формы поиска

$courier_id = '';

$messaga = '';

$d_disabled = '';

$client_update = 0;

$dl_def['deal_id'] = '';
$dl_def['item_inv_n'] = '';

$deal_id = '';
$item_inv_n = '';

$cl_only = '';
$cl_only_upd = '';

$delivery = '';
$delivery_price = 0;

$coll_amount = '';
$coll_cur = '';

$ext_status = 'no_status';
$return_p_kassa_deliv = '';
$status = '';

$payment_date = '';
$no_cur = 'no_cur';
$cur_ext_messaga = '';
$cl_def['arch_n'] = '';
$cl_def['arch_amount'] = '';
$cl_def['arch_l_date'] = 0;
$cl_def['pas_ln'] = '';

$delivery_to_pay = '';

$source = '';

if ($rez = Base::getErrorsString()) {
	echo $rez;
}


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

if (isset($_POST['action'])) {

	// стандартный возврат
	if ($action == 'Возврат-стандарт') {
		$action = 'сохранить возврат';
		$start_date = date("Y-m-d");
		$ret_payment_amount = '';
		$delivery_price = '';
		$return_p_kassa = $return_p_kassa_deliv = 'no_payment';
		$ret_status = 'ok';
		$sub_deal_info = 'стандартный возврат';



		$st_vozvr = 'yes';
	}



	// сохранение только клиента
	if ($action == 'сохранить только клиента') {
		$action = 'сохранить';
		$cl_only = 'yes';
	}

	// обновляем инфу только по клиенту
	if ($action == 'обновить информацию только по клиенту') {
		$action = 'сохранить';
		$cl_only_upd = 'yes';
	}




	if ($action == 'сохранить для курьера') { //новый вариант реагирования на отложено для курьера

		$action = 'сохранить';
		$for_kuryer = 'yes';
		$status = 'for_cur';
		$no_cur = 'for_cur';

		if ($client_id < 1) {
			$family == NULL ? $family = '_______________' : $family = $family;
			$name == NULL ? $name = '___________' : $name = $name;
			$otch == NULL ? $otch = '________________' : $otch = $otch;
			$str == NULL ? $str = '________________________________________' : $str = $str;
			$reg_str == NULL ? $reg_str = '________________________________________________________________________' : $reg_str = $reg_str;
			$pas_n == NULL ? $pas_n = '___________' : $pas_n = $pas_n;
			$pas_who == NULL ? $pas_who = '________________' : $pas_who = $pas_who;
		}

		$delivery = 1;
		$delivery_price = tonum($del_to_pay);

	}


	if ($action == 'сохранить продление для курьера') { //новый вариант реагирования на отложено для курьера по продлению
		$action = 'сохранить продление';
		$delivery = '1';
		$delivery_price = tonum($del_to_pay);
		$ext_status = 'for_cur';
		$cur_ext_messaga = 'yes';
		$no_cur = 'for_cur';
	}



	switch ($action) {

		case 'сохранить оплату':  //оплата
			$mysqli = Db::getInstance()->getConnection();

			$start_date = strtotime($start_date); //приводим в формат юникс дату календаря гггг-мм-дд
			$rent_payment = tonum($rent_payment);//меняем точку на запятую + убираем пробелы и лишние символы
			$del_payment = tonum($del_payment);//меняем точку на запятую + убираем пробелы и лишние символы

			$rent_p_k1 = tonum($rent_p_k1);
			$rent_p_k2 = tonum($rent_p_k2);
			$rent_p_card = tonum($rent_p_card);
			$rent_p_bank = tonum($rent_p_bank);


			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
			if (!$result_start) {
				die('Сбой при доступе к базе данных: ' . $result_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			$done = "yes";

			//выбираем "первую сдачу" (либо "аванс"), к которой привязываем платеж
			$query_sub_dl_first = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$active_deal_id' AND `type` IN ('first_rent', 'takeaway_plan') ORDER by cr_time DESC";
			$result_sub_dl_first = $mysqli->query($query_sub_dl_first);
			if (!$result_sub_dl_first) {
				die('Сбой при доступе к базе данных: ' . $result_sub_dl_first . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$sub_dl_first = $result_sub_dl_first->fetch_assoc();


			// вносим суб-сделку (история + подробности)
			if ($rent_payment_type == 'multi') {

				if ($rent_p_k1 > 0) {
					$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'payment', '30', '$start_date', '', '', '', '', '', '', '', '', '', '$rent_p_k1', '$del_payment', 'nal_cheque', '$del_payment_type', 'pure_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '" . $sub_dl_first['sub_deal_id'] . "', '$start_date', '" . $_SESSION['office'] . "', '$ch_num_p_k1', '', '', '')";
					if (!$mysqli->query($sub_query)) {
						$done = "no";
						echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
					}
				}//end of if

				if ($rent_p_k2 > 0) {
					$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'payment', '30', '$start_date', '', '', '', '', '', '', '', '', '', '$rent_p_k2', '$del_payment', 'nal_no_cheque', '$del_payment_type', 'pure_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '" . $sub_dl_first['sub_deal_id'] . "', '$start_date', '" . $_SESSION['office'] . "', '$ch_num', '', '', '')";
					if (!$mysqli->query($sub_query)) {
						$done = "no";
						echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
					}
				}//end of if

				if ($rent_p_card > 0) {
					$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'payment', '30', '$start_date', '', '', '', '', '', '', '', '', '', '$rent_p_card', '$del_payment', 'card', '$del_payment_type', 'pure_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '" . $sub_dl_first['sub_deal_id'] . "', '$start_date', '" . $_SESSION['office'] . "', '$ch_num_p_card', '', '', '')";
					if (!$mysqli->query($sub_query)) {
						$done = "no";
						echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
					}
				}//end of if

				if ($rent_p_bank > 0) {
					$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'payment', '30', '$start_date', '', '', '', '', '', '', '', '', '', '$rent_p_bank', '$del_payment', 'bank', '$del_payment_type', 'pure_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '" . $sub_dl_first['sub_deal_id'] . "', '$start_date', '" . $_SESSION['office'] . "', '$ch_num_p_bank', '', '', '')";
					if (!$mysqli->query($sub_query)) {
						$done = "no";
						echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
					}
				}//end of if

			} else {

				$sub_dl_fr['place'] = $_SESSION['office'];

				if ($rent_payment_type == 'bank') { //в случае банковского платежа - ищем офис, на котором была первая сдач, чтобы поставить праильный номер офиса. !!! asume, that bank payment could be done only for active deals (=no search in arch deals table)

					$query_sub_fr = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$active_deal_id' AND `type` IN ('first_rent', 'takeaway_plan') ORDER by cr_time DESC";
					$result_sub_fr = $mysqli->query($query_sub_fr);
					if (!$result_sub_fr) {
						die('Сбой при доступе к базе данных: ' . $query_sub_fr . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					}
					$sub_dl_fr = $result_sub_fr->fetch_assoc();
				}

				//вносим платеж
				$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'payment', '30', '$start_date', '', '', '', '', '', '', '', '', '', '$rent_payment', '$del_payment', '$rent_payment_type', '$del_payment_type', 'pure_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '" . $sub_dl_first['sub_deal_id'] . "', '$start_date', '" . $sub_dl_fr['place'] . "', '$ch_num', '', '', '')";
				if (!$mysqli->query($sub_query)) {
					$done = "no";
					echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
				}
			}//end of else

			// корректируем сделку
			$delivery_paid = $del_payment + $main_deal_delivery_paid;
			$r_paid = $rent_payment + $main_deal_r_paid;


			$query_dl_upd = "UPDATE rent_deals_act SET delivery_paid='$delivery_paid', r_paid='$r_paid', last_sub_deal_ch_time='" . time() . "' WHERE deal_id='$active_deal_id'";
			if (!$mysqli->query($query_dl_upd)) {
				$done = "no";
				echo 'Сбой при вставке данных: ' . $query_dl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}


			if ($done == 'yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}

				$_POST['client_id'] = $rez_client_id;

			} else {
				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			}
			$messaga = '<strong><br /><span style="color: red;" size="5">Оплата успешно сохранена! Можно переходить к оформлению следующей сделки.</span></strong>';

			Deal::recalculateAmounts($active_deal_id);

			break;



		case 'сохранить возврат': // сохранение детального возврата

			Deal::dealIdArchDublicateFix($active_deal_id);

			$mysqli = Db::getInstance()->getConnection();

			$start_date = strtotime($start_date); //приводим в формат юникс дату календаря гггг-мм-дд
			$ret_payment_amount = tonum($ret_payment_amount);//меняем запятую на точку + убираем пробелы и лишние символы
			$to_pay_pastdue = tonum(-$to_pay_pastdue);//меняем запятую на точку + убираем пробелы и лишние символы
			if ($ret_payment_amount < 0) {
				$to_pay_pastdue += $ret_payment_amount;
			}//возврат денег уменьшает сумму к оплате

			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
			if (!$result_start) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}

			$done = "yes";

			//через id сделки ищем всю сделку для того, чтобы указать дату ее действия до возврата
			$query_last = "SELECT * FROM rent_deals_act WHERE deal_id='$active_deal_id'";
			$result_last = $mysqli->query($query_last);
			if (!$result_last) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_last . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}
			$last_o = $result_last->fetch_assoc();


			// вносим суб-сделку - возврат
			$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'close', '80', '$start_date', '" . $last_o['return_date'] . "', '', '', '', '', '$to_pay_pastdue', '', '', '', '', '', '', '', '$ret_status', '$sub_deal_info', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '', '$start_date', '" . $_SESSION['office'] . "', '', '', '', '')";
			if (!$mysqli->query($sub_query)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}
			$ret_id = $mysqli->insert_id;

			if ($return_p_kassa != 'no_payment') { // вносим оплату (при наличии)

				// вносим суб-сделку - оплата
				$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'cl_payment', '30', '$start_date', '', '', '', '', '', '', '', '', '', '$ret_payment_amount', '', '$return_p_kassa', '', 'cl_payment_dog', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '$ret_id', '$start_date', '" . $_SESSION['office'] . "', '$ch_num', '', '', '')";
				if (!$mysqli->query($sub_query)) {
					$done = "no";
					echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
				}

			}




			// корректируем сделку
			$r_to_pay = $main_deal_r_to_pay + $to_pay_pastdue;
			$r_paid = $ret_payment_amount + $main_deal_r_paid;

			$r_to_pay > $r_paid ? $f_status = 'closed_loss' : $f_status = 'closed';
			$ret_status == 'not_ok' ? $f_status = $f_status . '_problem' : $f_status = $f_status;


			$query_dl_upd = "UPDATE rent_deals_act SET r_paid='$r_paid', r_to_pay='$r_to_pay', return_date='$start_date', deal_status='$f_status', last_sub_deal_ch_time='" . time() . "' WHERE deal_id='$active_deal_id'";
			if (!$mysqli->query($query_dl_upd)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_dl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}

			//перенос записей суб. сделок в архив
			$query_arch_sub = "INSERT INTO rent_sub_deals_arch SELECT '', '" . time() . "', sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, `info`, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place`, ch_num, sd_cat_id, sd_model_id, sd_inv_n FROM rent_sub_deals_act WHERE deal_id='$active_deal_id'";
			if (!$mysqli->query($query_arch_sub)) {
				$done = "no";
				echo 'Сбой при вставке данных: ' . $query_arch_sub . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}

			// далее делаем удаление суб. сделок
			$query_del_sub = "DELETE FROM rent_sub_deals_act WHERE deal_id='$active_deal_id'";
			if (!$mysqli->query($query_del_sub)) {
				$done = "no";
				echo 'Сбой при удалении данных: ' . $query_del_sub . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}

			//перенос записей основной сделок в архив
			$query_arch_dl = "INSERT INTO rent_deals_arch SELECT '', '" . time() . "', deal_id, client_id, item_inv_n, start_date, return_date, delivery_yn, delivery_to_pay, delivery_paid, r_to_pay, r_paid, collateral_amount, collateral_cur, deal_status, deal_info, acc_person_id, cr_who_id, cr_time, last_sub_deal_ch_time, planned_return_date, deal_set, first_rent_place FROM rent_deals_act WHERE deal_id='$active_deal_id'";
			if (!$mysqli->query($query_arch_dl)) {
				$done = "no";
				echo 'Сбой при удалении данных: ' . $query_arch_dl . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}


			// далее делаем удаление сделок
			$query_del_dl = "DELETE FROM rent_deals_act WHERE deal_id='$active_deal_id'";
			if (!$mysqli->query($query_del_dl)) {
				$done = "no";
				echo 'Сбой при удалении данных: ' . $query_del_dl . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}


			// меняем статус товара на "свободно" + убираем deal_id
			$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent', active_deal_id='',  item_place='" . $_SESSION['office'] . "' WHERE item_inv_n='$item_inv_n'";
			if (!$mysqli->query($query_upd)) {
				$done = "no";
				echo 'Сбой при обновлении данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}


			// делаем корректировку информации на клиенте (для быстрого вывода истории)
			//через id сделки ищем всю сделку
			$query_cldl = "SELECT * FROM rent_deals_arch WHERE deal_id='$active_deal_id'";
			$result_cldl = $mysqli->query($query_cldl);
			if (!$result_cldl) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $query_cldl . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$cldl = $result_cldl->fetch_assoc();

			// через id клиента ищем клиента
			$query_cl = "SELECT * FROM clients WHERE client_id='" . $cldl['client_id'] . "'";
			$result_cl = $mysqli->query($query_cl);
			if (!$result_cl) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $query_cl . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$cl = $result_cl->fetch_assoc();

			// обновляем информацию о сделках клиента
			$query_cl_upd = "UPDATE clients SET arch_n='" . ($cl['arch_n'] + 1) . "', arch_amount='" . ($cl['arch_amount'] + $r_to_pay) . "', arch_l_date='$start_date' WHERE client_id='" . $cldl['client_id'] . "'";
			if (!$mysqli->query($query_cl_upd)) {
				$done = "no";
				die('Сбой при обновлении данных: ' . $query_cl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}



			//завершение
			if ($done == 'yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin)
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				$item_inv_n_or = $item_inv_n;
				$item_inv_n = '';
			} else {
				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin)
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			// ищем товар для определения модели
			$query_itm = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$item_inv_n_or'";
			$result_itm = $mysqli->query($query_itm);
			if (!$result_itm) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $query_itm . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$itm = $result_itm->fetch_assoc();

			$query_or = "SELECT * FROM rent_orders WHERE `type`='zayavka' AND model_id='" . $itm['model_id'] . "'";
			$result_or = $mysqli->query($query_or);
			if (!$result_or) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $query_or . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$or_rows = $result_or->num_rows;

			//$br_text='инв.н='.$item_inv_n.'<br/><br />'.$query_itm.'<br /><br />'.$query_or;
			if ($or_rows > 0) {
				$br_text = '<div style="background-color:yellow; font-size:36px; color:red; width: 500px;">Внимание !!! На данный товар имеется заявка.<a class="div_item" href="/bb/rent_orders.php">Перейти на страницу броней.</a></div>';
			} else {
				$br_text = '';
			}

			Deal::recalculateAmounts($active_deal_id);


			$del_text = '';
			if (isset($_POST['tov_sold']) && $_POST['tov_sold'] != '0') {//архивируем товар

				$tov = new \bb\classes\tovar();

				$reason = Base::GetPost('tov_sold');

				$tov->item_load($item_inv_n_or);

				if ($reason == 'sold') {
					$tov->item_del_info = 'Товар продан клиенту. ';
				} elseif ($reason = 'no_return') {
					$tov->item_del_info = 'Товар не возвращен клиентом. ';
				}
				$tov->item_del_info .= 'Операция проведена: ' . User::getCurrentUser()->getShortName() . ' (id:' . User::getCurrentUser()->id_user . ').';
				$tov->out_status = $reason;

				$tov->del_item();

				$del_text .= '<br><span style="color: red; font-size: 22px; font-weight: bold;">Товар списан!</span>';

				unset($tov);
			} else {
				//вставка на стирку
				$bron = new \bb\classes\bron();
				$bron->inv_n = $item_inv_n_or;
				$bron->item_load();

				$bron->type2 = 'stirka';
				$bron->cr_time = time();
				$bron->cr_who_id = $bron->user_id;

				//print_r($bron);

				$bron->insert();

				unset($bron);
			}


			$messaga = '<strong><br /><font color="red" size="5">Возврат успешно осуществлен! Можно переходить к оформлению следующей сделки.</font></strong>' . $br_text . $del_text;

			break;


		case 'сохранить':
			$mysqli = Db::getInstance()->getConnection();

			if ($client_id < 1) { 				//ввод нового клиента в БД
				$mysqli = Db::getInstance()->getConnection();

				$phone_1 = phone_to_n($phone_1);
				$phone_2 = phone_to_n($phone_2);

				$family = mb_convert_case($family, MB_CASE_TITLE, 'UTF-8'); // Слово с заглавной буквы
				$name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
				$otch = mb_convert_case($otch, MB_CASE_TITLE, 'UTF-8');
				$city = mb_convert_case($city, MB_CASE_TITLE, 'UTF-8');
				//$str=mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
				$reg_city = mb_convert_case($reg_city, MB_CASE_TITLE, 'UTF-8');
				//$reg_str=mb_convert_case($reg_str, MB_CASE_TITLE, 'UTF-8');

				$pas_date = strtotime($pas_date); //приводим в формат юникс дату календаря гггг-мм-дд

				$query = "INSERT INTO clients VALUES('', '$family', '$name', '$otch', '$city', '$str', '$dom', '$kv', '$pas_n', '$pas_ln', '$pas_date', '$pas_who', '$reg_city', '$reg_str', '$reg_dom', '$reg_kv', '$phone_1', '$phone_2', '$info', '', " . time() . ", '', '','', '" . $_SESSION['user_id'] . "', '$source')";
				if (!$mysqli->query($query)) {
					echo 'Сбой при вставке клиента в базу данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
				}
				$client_id = $mysqli->insert_id;
				$_POST['client_id'] = $client_id;

			}
			if ($cl_only == 'yes') {
				break;
			} // выход, если ТОЛЬКО сохраняем клиента


			if ($client_id > 0 && $client_update == 1) {       //обновление информации о клиенте

				if ($family == '' || $name == '') {
					die('Попытка обновить информацию о клиенте пустыми значениями. Запоните, что и как Вы делали и сообщите Диме!');
				}

				$phone_1 = phone_to_n($phone_1);
				$phone_2 = phone_to_n($phone_2);
				$pas_date = strtotime($pas_date); //приводим в формат юникс дату календаря гггг-мм-дд

				$family = mb_convert_case($family, MB_CASE_TITLE, 'UTF-8');
				$name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
				$otch = mb_convert_case($otch, MB_CASE_TITLE, 'UTF-8');
				$city = mb_convert_case($city, MB_CASE_TITLE, 'UTF-8');
				//$str=mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
				$reg_city = mb_convert_case($reg_city, MB_CASE_TITLE, 'UTF-8');
				//$reg_str=mb_convert_case($reg_str, MB_CASE_TITLE, 'UTF-8');

				$query_cl_upd = "UPDATE clients SET family='$family', name='$name', otch='$otch', city='$city', str='$str', dom='$dom', kv='$kv', pas_n='$pas_n', pas_ln='$pas_ln', pas_date='$pas_date', pas_who='$pas_who', reg_city='$reg_city', reg_str='$reg_str', reg_dom='$reg_dom', reg_kv='$reg_kv', phone_1='$phone_1', phone_2='$phone_2', info='$info', `source`='$source' WHERE client_id='$client_id'";
				if (!$mysqli->query($query_cl_upd)) {
					echo 'Сбой при обновлении данных клиента в базе данных: ' . $query_cl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
				}

				//update delivery if any
				Delivery::updateAddressForClient($client_id, $str . ' ' . $dom . '-' . $kv);

				$client_update = 0;

			}
			if ($cl_only_upd == 'yes') {
				break;
			} // выход, если обновляем инфу ТОЛЬКО по клиенту


			$no_cur == 'no_cur' ? $courier_id = '' : $courier_id = $courier_id;

			$mysqli = Db::getInstance()->getConnection();

			// пришлось продублировать из "контроль других пользователей, вдруг кто-то еще уже сдал" т.к. нужен офис для вставки информации о первой сдаче в сделку
			$query_ch = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$item_inv_n' AND (`status`='to_rent' OR `status`='bron' OR (`status`='t_bron' AND br_time<" . time() . "))";
			$result_ch = $mysqli->query($query_ch);
			if (!$result_ch)
				die('Сбой при обновлении данных клиента в базе данных: ' . $query_ch . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			;
			$item_rows = $result_ch->num_rows;
			$item_pl_ch = $sub_dl_fr = $result_ch->fetch_assoc();

			if ($no_cur == 'for_cur') {
				$sub_dl_place = $item_pl_ch['item_place'];
			} else {
				$sub_dl_place = $_SESSION['office'];
			}


			if (!$deal_id > 0) {// сохранение новой сделки

				$item_cat_n = substr($item_inv_n, 0, 3);

				$start_date = strtotime($start_date); //приводим в формат юникс дату календаря гггг-мм-дд
				$return_date = strtotime($return_date); //приводим в формат юникс дату календаря гггг-мм-дд
				//меняем запятые на точки
				$rent_tarif = tonum($rent_tarif);
				//          // applying discount
//            if (isset($discount) && $discount>0) {
//              $rent_tarif = round($rent_tarif * (1-$discount/100), 2);
//            }
				$r_to_pay = tonum($r_to_pay);
				$rent_tenor = tonum($rent_tenor);


				$query_start = "START TRANSACTION";
				$result_start = $mysqli->query($query_start);
				if (!$result_start) {
					$done = "no";
					die('Сбой при обновлении данных клиента в базе данных: ' . $query_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}

				$done = "yes";

				// контроль других пользователей, вдруг кто-то еще уже сдал
				$query_ch = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$item_inv_n' AND (`status`='to_rent' OR `status`='bron' OR (`status`='t_bron' AND br_time<" . time() . "))";
				$result_ch = $mysqli->query($query_ch);
				if (!$result_ch)
					die('Сбой при доступе к базе данных: ' . $query_ch . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				$item_rows = $result_ch->num_rows;
				$item_pl_ch = $sub_dl_fr = $result_ch->fetch_assoc();
				if ($item_rows != 1) {
					echo '<strong><br /><span style="color: red" size="5">К сожалению, товар уже не доступен для сдачи! Может, сдал кто-то другой.</span></strong>';
					$done = "no";
				}

				if ($takeaway_status == 'now') {//отражаем плановую выдачу в изначальном поле планового возврата. если case = сейчас - то никакой плановой выдачи нет
					$takeaway_pl_date = '';
					if (isset($br_hour_from)) {
						$br_hour_from = $br_hour_from * 3600;
					} else {
						$br_hour_from = 0;
					}
					if (isset($br_hour_to)) {
						$br_hour_to = $br_hour_to * 3600;
					} else {
						$br_hour_to = 0;
					}
				} else {
					$takeaway_pl_date = strtotime($takeaway_date);
					if (isset($br_hour_from)) {
						$takeaway_pl_date = $takeaway_pl_date + $br_hour_from * 3600;
					}
					if (isset($br_hour_to)) {
						$return_date = $return_date + $br_hour_to * 3600;
					}
				}

				//вносим основную сделку
				if (isset($bron_cr_id) && $bron_cr_id > 0) {
					$acc_person = $bron_cr_id;
				} else {
					$acc_person = $_SESSION['user_id'];
				}

				//определяем на кого вешать
				$deal_status = '';
				if ($takeaway_status == 'later') {//помечаем сделку как бронь для карнавалов. т.к. товар по карнавалам не бронируется
					$deal_status = 'bron';
					$query = "INSERT INTO rent_deals_act VALUES('', '$client_id', '$item_inv_n', '$takeaway_pl_date', '$return_date', '$delivery', '$delivery_price', '', '$r_to_pay', '', '$coll_amount', '$coll_cur', '$deal_status', '$deal_info', '$acc_person', '" . $_SESSION['user_id'] . "', '" . time() . "', '" . time() . "', '$start_date', '$deal_item_set', '$sub_dl_place')";
					if (!$mysqli->query($query)) {
						$done = "no";
						echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
					}
				} elseif ($takeaway_status == 'now') {//помечаем сделку как бронь для карнавалов. т.к. товар по карнавалам не бронируется
					$query = "INSERT INTO rent_deals_act VALUES('', '$client_id', '$item_inv_n', '$start_date', '$return_date', '$delivery', '$delivery_price', '', '$r_to_pay', '', '$coll_amount', '$coll_cur', '$deal_status', '$deal_info', '$acc_person', '" . $_SESSION['user_id'] . "', '" . time() . "', '" . time() . "', '$start_date', '$deal_item_set', '$sub_dl_place')";
					if (!$mysqli->query($query)) {
						$done = "no";
						echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
					}
				}
				$deal_id = $mysqli->insert_id;


			}


			// вносим суб-сделку (история + подробности)
			if ($takeaway_status == 'now') {//для сейчас - вносим выдачу
				$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$deal_id', 'first_rent', '10', '" . ($start_date + $br_hour_from) . "', '" . ($return_date + $br_hour_to) . "', '$tarif_id', '$step', '$rent_tarif', '$rent_tenor', '$r_to_pay', '$delivery', '$delivery_price', '$courier_id', '', '', '', '', '$status', '$deal_info', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '', '$start_date', '$sub_dl_place', '', '', '', '')";
				if (!$mysqli->query($sub_query)) {
					$done = "no";
					echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
				}
			}//end of takeaway if


			if ($takeaway_status == 'later') {//для потом - вносим плановую выдачу
				$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$deal_id', 'takeaway_plan', '5', '$takeaway_pl_date', '$return_date', '$tarif_id', '$step', '$rent_tarif', '$rent_tenor', '$r_to_pay', '$delivery', '$delivery_price', '$courier_id', '', '', '', '', '$status', '$deal_info', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '', '$start_date', '$sub_dl_place', '', '', '', '')";
				if (!$mysqli->query($sub_query)) {
					$done = "no";
					echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
				}

			}

			$tmp_sub_dl_id = $mysqli->insert_id;

			//Create delivery!!!
			if ($no_cur == 'for_cur') {
				//echo '---2';
				if (!Delivery::createDeliveryBySubDlId($tmp_sub_dl_id)) {
					//echo '---3';
					$done = 'no';
					Base::addErrorMessage('Не удалось создать выезд курьера для выдачи товара клиенту.');
				}
				;
			}

			// меняем статус товара на "занято" + добавляем deal_id
			$no_cur == 'for_cur' ? $tov_status = 'to_deliver' : $tov_status = 'rented_out';//для доставки ставим (новый) спец. статус

			$query_upd = "UPDATE tovar_rent_items SET `status`='$tov_status', active_deal_id='$deal_id' WHERE item_inv_n='$item_inv_n'";
			if (!$mysqli->query($query_upd)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}

			if (isset($br_reg) && $br_reg > 0) {
				$query_upd = "UPDATE karn_brons SET dl_link='$deal_id' WHERE kb_id='$br_reg'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {
					die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			}

			//удаляем брони
			\bb\classes\bron::removeStrongBrons($item_inv_n, 'dogovor_new');


			if ($done == 'yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					$deal_id = '';
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}


			} else {
				$deal_id = '';

				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			}

			$done == 'yes' ? $messaga = '<strong><br /><font color="red" size="5">Сделка успешно сохранена! Не забудьте оформить оплату!!!</font></strong>' : $messaga = '<strong><br /><font color="red" size="5">Сделка не сохранена!</font></strong>';


			break;


		case 'сохранить продление':
			$mysqli = Db::getInstance()->getConnection();

			$start_date = strtotime($start_date); //приводим в формат юникс дату календаря гггг-мм-дд
			$return_date = strtotime($return_date); //приводим в формат юникс дату календаря гггг-мм-дд
			$r_to_pay = tonum($r_to_pay);//меняем точку на запятую + убираем пробелы и лишние символы
			$r_paid = 0;


			if ($client_id > 0 && $client_update == 1) {       //обновление информации о клиенте

				if ($family == '' || $name == '') {
					die('Попытка обновить информацию о клиенте пустыми значениями. Запомните, что и как Вы делали и сообщите Диме!');
				}

				$phone_1 = phone_to_n($phone_1);
				$phone_2 = phone_to_n($phone_2);
				$pas_date = strtotime($pas_date); //приводим в формат юникс дату календаря гггг-мм-дд

				$family = mb_convert_case($family, MB_CASE_TITLE, 'UTF-8');
				$name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
				$otch = mb_convert_case($otch, MB_CASE_TITLE, 'UTF-8');
				$city = mb_convert_case($city, MB_CASE_TITLE, 'UTF-8');
				//$str=mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
				$reg_city = mb_convert_case($reg_city, MB_CASE_TITLE, 'UTF-8');
				//$reg_str=mb_convert_case($reg_str, MB_CASE_TITLE, 'UTF-8');

				$query_cl_upd = "UPDATE clients SET family='$family', name='$name', otch='$otch', city='$city', str='$str', dom='$dom', kv='$kv', pas_n='$pas_n', pas_ln='$pas_ln', pas_date='$pas_date', pas_who='$pas_who', reg_city='$reg_city', reg_str='$reg_str', reg_dom='$reg_dom', reg_kv='$reg_kv', phone_1='$phone_1', phone_2='$phone_2', info='$info' WHERE client_id='$client_id'";
				if (!$mysqli->query($query_cl_upd)) {
					echo 'Сбой при доступе к базе данных: ' . $query_cl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
				}

				//update delivery if any
				Delivery::updateAddressForClient($client_id, $str . ' ' . $dom . '-' . $kv);

				$client_update = 0;

			}
			if ($cl_only_upd == 'yes') {
				break;
			} // выход, если обновляем инфу ТОЛЬКО по клиенту




			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
			if (!$result_start) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $query_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			$done = "yes";

			if ($tarif_id == 'old') {
				$tarif_id = 0;
			}

			$payment_date = strtotime($payment_date);
			$no_cur == 'no_cur' ? $courier_id = '' : $courier_id = $courier_id;


			$sub_dl_fr['place'] = $_SESSION['office'];

			if ($rent_payment_type == 'bank') { //в случае банковского платежа - ищем офис, на котором была первая сдач, чтобы поставить праильный номер офиса. !!! asume, that bank payment could be done only for active deals (=no search in arch deals table)

				$query_sub_fr = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$active_deal_id' AND `type` IN ('first_rent', 'takeaway_plan') ORDER by cr_time DESC";
				$result_sub_fr = $mysqli->query($query_sub_fr);
				if (!$result_sub_fr) {
					die('Сбой при доступе к базе данных: ' . $query_sub_fr . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
				$sub_dl_fr = $result_sub_fr->fetch_assoc();
			}



			// вносим суб-сделку (история + подробности)
			$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'extention', '20', '$start_date', '$return_date', '$tarif_id', '$step', '$rent_tarif', '$rent_tenor', '$r_to_pay', '$delivery', '$delivery_price', '$courier_id', '', '', '', '', '$ext_status', '$deal_info', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '', '$payment_date', '" . $sub_dl_fr['place'] . "', '', '', '', '')";
			if (!$mysqli->query($sub_query)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}
			$ext_id = $mysqli->insert_id;

			// вносим суб-сделку-платеж, если проставлен канал оплаты
			if ($rent_payment_type != 'no_payment' && $no_cur != 'for_cur') {
				$r_paid = $r_to_pay = tonum($r_to_pay);

				if ($rent_payment_type == 'multi') {

					if ($rent_p_k1 > 0) {
						$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$rent_p_k1', '', 'nal_cheque', '', 'ext_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '$ext_id', '$payment_date', '" . $_SESSION['office'] . "', '$ch_num_p_k1', '', '', '')";
						if (!$mysqli->query($sub_query)) {
							$done = "no";
							echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
						}
					}//end of if

					if ($rent_p_k2 > 0) {
						$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$rent_p_k2', '', 'nal_no_cheque', '', 'ext_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '$ext_id', '$payment_date', '" . $_SESSION['office'] . "', '$ch_num', '', '', '')";
						if (!$mysqli->query($sub_query)) {
							$done = "no";
							echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
						}
					}//end of if

					if ($rent_p_card > 0) {
						$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$rent_p_card', '', 'card', '', 'ext_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '$ext_id', '$payment_date', '" . $_SESSION['office'] . "', '$ch_num_p_card', '', '', '')";
						if (!$mysqli->query($sub_query)) {
							$done = "no";
							echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
						}
					}//end of if

					if ($rent_p_bank > 0) {
						$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$rent_p_bank', '', 'bank', '', 'ext_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '$ext_id', '$payment_date', '" . $sub_dl_fr['place'] . "', '$ch_num_p_bank', '', '', '')";
						if (!$mysqli->query($sub_query)) {
							$done = "no";
							echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
						}
					}//end of if

				}//end of multi if
				else {//для одноканальной оплаты

					$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$r_to_pay', '', '$rent_payment_type', '', 'ext_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '$ext_id', '$payment_date', '" . $sub_dl_fr['place'] . "', '$ch_num', '', '', '')";
					if (!$mysqli->query($sub_query)) {
						$done = "no";
						echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
					}
				}//end of else
			}

			// корректируем сделку

			$j_r_to_pay = $r_to_pay + $main_deal_r_to_pay;

			if ($rent_payment_type != 'no_payment' && $no_cur != 'for_cur') {//не курьер
				$r_paid = $r_to_pay + $main_deal_r_paid;
				$delivery_to_pay = $main_deal_delivery_to_pay;
			} else { // курьер
				$r_paid = $main_deal_r_paid; // оплата не вносится
				$delivery_to_pay = $delivery_price + $main_deal_delivery_to_pay;
			}

			//Create delivery!!!
			//echo '---1';
			if ($no_cur == 'for_cur') {
				//echo '---2';
				if (!Delivery::createDeliveryBySubDlId($ext_id)) {
					//echo '---3';
					$done = 'no';
					Base::addErrorMessage('Не удалось создать выезд курьера для продления товара клиенту.');
				}
				;
			}


			$query_dl_upd = "UPDATE rent_deals_act SET return_date='$return_date', delivery_to_pay='$delivery_to_pay', r_to_pay='$j_r_to_pay', r_paid='$r_paid', last_sub_deal_ch_time='" . time() . "' WHERE deal_id='$active_deal_id'";
			if (!$mysqli->query($query_dl_upd)) {
				echo 'Сбой при доступе к базе данных: ' . $query_dl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}


			if ($done == 'yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}


			} else {

				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			}

			Deal::recalculateAmounts($active_deal_id);

			$messaga = '<strong><br /><font color="red" size="5">Продление и его оплата успешно сохранены! Можно переходить к оформлению следующей сделки.</font></strong>';
			if ($cur_ext_messaga == 'yes') {
				$messaga = '<strong><br /><span style="color: red;" size="5">Заказ забора денег за продление успешно сохранен! Можно переходить к оформлению следующей сделки.</span></strong>';
			}

			break;


		case 'сохранить заказ забора курьером':

			$mysqli = Db::getInstance()->getConnection();

			$start_date = strtotime($start_date); //приводим в формат юникс дату календаря гггг-мм-дд

			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
			if (!$result_start) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $query_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			$done = "yes";

			// вносим суб-сделку
			$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', 'cur_return', '80', '$start_date', '', '', '', '', '', '$r_to_pay', '1', '$del_to_pay', '$courier_id', '', '', '', '', 'for_cur', '$sub_deal_info', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '', '$start_date', '" . $_SESSION['office'] . "', '', '', '', '')";
			if (!$mysqli->query($sub_query)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}
			$tmp_sub_dl_z = $mysqli->insert_id;

			//Create delivery!!!
			//echo '---1';
			if (1 == 1) {
				//echo '---2';
				if (!Delivery::createDeliveryBySubDlId($tmp_sub_dl_z)) {
					//echo '---3';
					$done = 'no';
					Base::addErrorMessage('Не удалось создать выезд курьера для забора товара клиенту.');
				}
				;
			}


			// корректируем сделку
			$query_dl_upd = "UPDATE rent_deals_act SET deal_status='for_cur', last_sub_deal_ch_time='" . time() . "' WHERE deal_id='$active_deal_id'";
			if (!$mysqli->query($query_dl_upd)) {
				echo 'Сбой при доступе к базе данных: ' . $query_dl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}


			if ($done == 'yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			} else {
				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			}

			$messaga = '<strong><br /><font color="red" size="5">Заказ забора курьером сохранен! Можно переходить к оформлению следующей сделки.</font></strong>';


			break;


		case 'найти':  //ищем клиентов
			$mysqli = Db::getInstance()->getConnection();

			$s_ph = phone_to_n($s_ph);//удаляем пробелы и всякую другую фигню


			$s_family == NULL ? $ss_family = '%' : $ss_family = '%' . $s_family . '%';
			$s_name == NULL ? $ss_name = '' : $ss_name = ' AND name LIKE \'%' . $s_name . '%\'';
			$s_otch == NULL ? $ss_otch = '' : $ss_otch = ' AND otch LIKE \'%' . $s_otch . '%\'';
			$s_str == NULL ? $ss_str = '' : $ss_str = ' AND str LIKE \'%' . $s_str . '%\'';
			$s_dom == NULL ? $ss_dom = '' : $ss_dom = ' AND dom LIKE \'%' . $s_dom . '%\'';
			$s_kv == NULL ? $ss_kv = '' : $ss_kv = ' AND kv LIKE \'%' . $s_kv . '%\'';
			$s_pas_n == NULL ? $ss_pas_n = '' : $ss_pas_n = ' AND pas_n LIKE \'%' . $s_pas_n . '%\'';
			$s_client_id == NULL ? $ss_client_id = '' : $ss_client_id = ' AND client_id=\'' . $s_client_id . '\'';
			$s_ph == NULL ? $ss_ph = '' : $ss_ph = ' AND (phone_1 LIKE \'%' . $s_ph . '%\' OR phone_2 LIKE \'%' . $s_ph . '%\')';

			$query_cl = "SELECT c.*, COUNT(r.deal_id) as act_num 
                         FROM clients c 
                         LEFT JOIN rent_deals_act r ON c.client_id=r.client_id 
                         WHERE c.family LIKE '$ss_family'" . $ss_name . $ss_otch . $ss_str . $ss_dom . $ss_kv . $ss_pas_n . $ss_client_id . $ss_ph . "
                         GROUP BY c.client_id
                         ORDER BY act_num DESC, c.family
                         LIMIT 100";
			//echo $query_cl;
			$result_cl = $mysqli->query($query_cl);
			if (!$result_cl) {
				die('Сбой при доступе к базе данных: ' . $result_cl . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$rows_cl = $result_cl->num_rows;

			$client_search = 1;

			break;



		case 'удалить ВСЮ сделку':
			$mysqli = Db::getInstance()->getConnection();

			$from_d_sluzh = strtotime($from_d_sluzh);

			if (!($_SESSION['user_id'] < 4 || $_SESSION['user_id'] == 5) && ((time() - $from_d_sluzh) > (24 * 60 * 60))) {
				die('Для удаления сделки заведенной не сегодя - обращайтесь к Кристине или Ане');
			}



			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
			if (!$result_start) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $result_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			$done = "yes";
			//for delivery
			$subDealsIds = [];
			$q = "SELECT sub_deal_id FROM rent_sub_deals_act WHERE deal_id='$active_deal_id'";
			if (!$result_d = $mysqli->query($q)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}
			while ($row = $result_d->fetch_assoc()) {
				$subDealsIds[] = $row['sub_deal_id'];
			}

			foreach ($subDealsIds as $id) {
				Delivery::cancelDelivery($id);
			}



			// делаем удаление суб. сделок
			$query_del_sub = "DELETE FROM rent_sub_deals_act WHERE deal_id='$active_deal_id'";
			if (!$mysqli->query($query_del_sub)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_del_sub . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}

			// далее делаем удаление сделки
			$query_del_dl = "DELETE FROM rent_deals_act WHERE deal_id='$active_deal_id'";
			if (!$mysqli->query($query_del_dl)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_del_dl . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}


			// меняем статус товара на "свободно" + убираем deal_id
			//проверяем наличие броней
			$query_br = "SELECT * FROM rent_orders WHERE `type`='strong' AND inv_n='$item_inv_n'";
			$result_br = $mysqli->query($query_br);
			if (!$result_br) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_br . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}
			$br_num = $result_br->num_rows;

			if ($br_num > 0) {
				$tovar_br_stat = 'bron';
			} else {
				$tovar_br_stat = 'to_rent';
			}


			$query_upd = "UPDATE tovar_rent_items SET `status`='$tovar_br_stat', active_deal_id='' WHERE item_inv_n='$item_inv_n'";
			if (!$mysqli->query($query_upd)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}


			if ($done == 'yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}

				$messaga = '<strong><br /><font color="red" size="5">Сделка успешно удалена!</font></strong>';
				$client_id = '';
				$item_inv_n = $_POST['client_id'] = '';
			} else {

				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			}



			break;


		case 'удалить архивную сделку':

			$mysqli = Db::getInstance()->getConnection();

			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
			if (!$result_start) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $query_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			$done = "yes";


			//выбираем информацию об удаляемой сделке и корректируем данные на клиенте.
			$query_dl_def2 = "SELECT * FROM rent_deals_arch WHERE deal_id='$arch2_deal_id'";
			$result_dl_def2 = $mysqli->query($query_dl_def2);
			if (!$result_dl_def2) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $query_dl_def2 . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$dl_def2 = $result_dl_def2->fetch_assoc();

			$query_cl2 = "SELECT * FROM clients WHERE client_id='" . $dl_def2['client_id'] . "'";
			$result_cl2 = $mysqli->query($query_cl2);
			if (!$result_cl2) {
				$done = "no";
				die('Сбой при доступе к базе данных: ' . $query_cl2 . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$cl2 = $result_cl2->fetch_assoc();

			$query_cl_upd = "UPDATE clients SET arch_n='" . ($cl2['arch_n'] - 1) . "', arch_amount='" . ($cl2['arch_amount'] - $dl_def2['r_to_pay']) . "' WHERE client_id='" . $dl_def2['client_id'] . "'";
			if (!$mysqli->query($query_cl_upd)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_cl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}



			// делаем удаление суб. сделок
			$query_del_sub = "DELETE FROM rent_sub_deals_arch WHERE deal_id='$arch2_deal_id'";
			if (!$mysqli->query($query_del_sub)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_del_sub . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}

			// далее делаем удаление сделки
			$query_del_dl = "DELETE FROM rent_deals_arch WHERE deal_id='$arch2_deal_id'";
			if (!$mysqli->query($query_del_dl)) {
				$done = "no";
				echo 'Сбой при доступе к базе данных: ' . $query_del_dl . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
			}

			if ($done == 'yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_del_dl . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}

				$messaga = '<strong><br /><font color="red" size="5">Архивная сделка успешно удалена!</font></strong>';
			} else {

				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			}




			break;


		case 'распечатать договор':

			$mysqli = Db::getInstance()->getConnection();
			//начинаем с active deal id

			//запрос актуальной информации о сделке
			$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$active_deal_id'";
			$result_dl_def = $mysqli->query($query_dl_def);
			if (!$result_dl_def) {
				die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$dl_def = $result_dl_def->fetch_assoc();


			//запрос актуальной информации о суб. сделке - первая сдача
			$query_sub_dl_def = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$active_deal_id' AND `type` IN ('first_rent', 'takeaway_plan') ORDER by cr_time DESC";
			$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
			if (!$result_sub_dl_def) {
				die('Сбой при доступе к базе данных: ' . $query_sub_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$sub_dl_def = $result_sub_dl_def->fetch_assoc();



			//запрос актуальной информации о клиенте
			$query_cl_def = "SELECT * FROM clients WHERE client_id='" . $dl_def['client_id'] . "'";
			$result_cl_def = $mysqli->query($query_cl_def);
			if (!$result_cl_def) {
				die('Сбой при доступе к базе данных: ' . $query_cl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$cl_def = $result_cl_def->fetch_assoc();


			//запрос информации о товаре по инв. номеру
			$query_item_def = "SELECT * FROM tovar_rent_items WHERE item_inv_n='" . $dl_def['item_inv_n'] . "'";
			$result_item_def = $mysqli->query($query_item_def);
			if (!$result_item_def) {
				die('Сбой при доступе к базе данных: ' . $query_item_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$item_def = $result_item_def->fetch_assoc();

			$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='" . $item_def['model_id'] . "'";
			$result_model_def = $mysqli->query($query_model_def);
			if (!$result_model_def) {
				die('Сбой при доступе к базе данных: ' . $query_model_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$model_def = $result_model_def->fetch_assoc();


			//запрос информации о категории товара
			$query_cat_def = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='" . $model_def['tovar_rent_cat_id'] . "'";
			$result_cat_def = $mysqli->query($query_cat_def);
			if (!$result_cat_def) {
				die('Сбой при доступе к базе данных: ' . $query_cat_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$cat_def = $result_cat_def->fetch_assoc();


			//подготовка некоторых значений
			$fio = encode_for_rtf($cl_def['family'] . ' ' . $cl_def['name'] . ' ' . $cl_def['otch']);
			$address = encode_for_rtf($cl_def['city'] . ', ' . $cl_def['str'] . ' ' . $cl_def['dom'] . '-' . $cl_def['kv']);
			$reg_address = encode_for_rtf($cl_def['reg_city'] . ', ' . $cl_def['reg_str'] . ' ' . $cl_def['reg_dom'] . '-' . $cl_def['reg_kv']);

			//выбираем цвет
			if ($model_def['color'] == '0') {
				$it_color = '';
			} elseif ($model_def[color] == 'multicolor') {
				$it_color = ', цвет: ' . $item_def['item_color'];
			} else {
				$it_color = ', цвет: ' . $model_def[color];
			}

			$tovar = ($model_def['model_addr'] == '' ? $cat_def['dog_name'] : $model_def['model_addr']) . ': ' . $model_def['model'] . $it_color . ', пр-во: ' . $model_def['producer'] . ',(в комплекте: ' . ($dl_def['deal_set']) . ') (инв.№' . substr($dl_def['item_inv_n'], 0, 3) . '-' . substr($dl_def['item_inv_n'], 3) . ').';

			$tovar = preg_replace("#[^0-9а-яА-ЯA-Za-z;:_.,?!° -%№]+#u", '', $tovar);


			if ($dl_def['delivery_yn'] == '1')
				$sgn = \bb\Signature::GetSignature(4); // as currently there is only one curier - concreete id is stated
			else
				$sgn = \bb\Signature::GetSignature($dl_def['cr_who_id']);


			$itemTov = \bb\classes\tovar::getTovarByInvN($dl_def['item_inv_n']);

			if (!$itemTov->isKarnaval()) {

				if ($sub_dl_def['delivery_yn'] == '1') {
					$rtf = new RTF_Template('nd_3.rtf');
				} elseif ($sub_dl_def['place'] == '2') {
					$rtf = new RTF_Template('nd_2.rtf');
				} elseif ($sub_dl_def['place'] == '3' || $sub_dl_def['delivery_yn'] == '1') {
					$rtf = new RTF_Template('nd_3.rtf');
				} else {
					$rtf = new RTF_Template('nd_1.rtf');
				}
				$rtf->parse('signaturestart', encode_for_rtf($sgn->StartText()));
				$rtf->parse('signatureend', encode_for_rtf($sgn->ShortSignature()));


				$rtf->parse('fioone', $fio);
				$rtf->parse('fiotwo', $fio);
				$rtf->parse('actaddress', $address);
				$rtf->parse('reg_address', $reg_address);
				$rtf->parse('pas_n', encode_for_rtf($cl_def['pas_n']));
				$rtf->parse('pas_date', encode_for_rtf($cl_def['pas_date'] == 0 ? '_________' : date("d.m.Y", $cl_def['pas_date'])));
				$rtf->parse('pas_who', encode_for_rtf($cl_def['pas_who']));
				$rtf->parse('phone_1', encode_for_rtf(phone_print($cl_def['phone_1'])));
				$rtf->parse('phone_2', encode_for_rtf(phone_print($cl_def['phone_2'])));

				$pln = mb_convert_case($cl_def['pas_ln'], MB_CASE_UPPER, "UTF-8");
				$rtf->parse('pasln', encode_for_rtf($pln));

				$rtf->parse('tovar_tov', encode_for_rtf($tovar));
				$rtf->parse('step', encode_for_rtf(step_pr($sub_dl_def['tarif_step'])));
				$rtf->parse('tarif', encode_for_rtf(number_format($sub_dl_def['tarif_value'], 2, ',', ' ')));
				$rtf->parse('tovar_currency', encode_for_rtf(cur_pr($model_def['agr_price_cur'])));
				$rtf->parse('tovar_price', encode_for_rtf(number_format($model_def['agr_price'], 2, ',', ' ')));
				$rtf->parse('start_date', encode_for_rtf(date("d.m.Y", $sub_dl_def['from'])));
				$rtf->parse('rto_pay_sum', encode_for_rtf(number_format($sub_dl_def['r_to_pay'], 2, ',', ' ')));

				$rtf->parse('rtopaywords', encode_for_rtf(\bb\Base::sum2words($sub_dl_def['r_to_pay'])));



				$rtf->parse('return_date', encode_for_rtf(date("d.m.Y", $sub_dl_def['to'])));
				$rtf->parse('dog_n', encode_for_rtf($sub_dl_def['deal_id']));

				if ($sub_dl_def['delivery_yn'] == '1') {
					$curadd = '% износа:___';
				} else {
					$curadd = '';
				}
				$rtf->parse('curadd', encode_for_rtf($curadd));

				$rtf->out_h('nd1.rtf');
				//$rtf->out_f('/1/nd1.rtf');
				echo $rtf->out(); //viewport

			} elseif ($itemTov->isKarnaval()) {

				$hours = ($sub_dl_def['to'] - $sub_dl_def['from']) / 60 / 60;

				if ($sub_dl_def['place'] == '1') {
					$rtf = new RTF_Template('ndk_1.rtf');
				} elseif ($sub_dl_def['place'] == '3') {
					$rtf = new RTF_Template('ndk_3.rtf');
				} else {
					$rtf = new RTF_Template('ndk_2.rtf');
				}


				if ($dl_def['delivery_yn'] == '1')
					$sgn = \bb\Signature::GetSignature(4); // as currently there is only one curier - concreete id is stated
				else
					$sgn = \bb\Signature::GetSignature($dl_def['cr_who_id']);


				//$rtf->parse('signaturestart', encode_for_rtf($sgn->StartText()));
				$rtf->parse('signaturestart', encode_for_rtf($sgn->StartText()));
				$rtf->parse('signatureend', encode_for_rtf($sgn->ShortSignature()));


				//$rtf = new RTF_Template('ndk.rtf');
				$rtf->parse('fioone', $fio);
				$rtf->parse('fiotwo', $fio);
				$rtf->parse('actaddress', $address);
				$rtf->parse('reg_address', $reg_address);
				$rtf->parse('pas_n', encode_for_rtf($cl_def['pas_n']));
				$rtf->parse('pas_date', encode_for_rtf($cl_def['pas_date'] == 0 ? '_________' : date("d.m.Y", $cl_def['pas_date'])));
				$rtf->parse('pas_who', encode_for_rtf($cl_def['pas_who']));
				$rtf->parse('phone_1', encode_for_rtf(phone_print($cl_def['phone_1'])));
				$rtf->parse('phone_2', encode_for_rtf(phone_print($cl_def['phone_2'])));
				$pln = mb_convert_case($cl_def['pas_ln'], MB_CASE_UPPER, "UTF-8");
				$rtf->parse('pasln', encode_for_rtf($pln));

				$rtf->parse('itset', encode_for_rtf($dl_def['deal_set']));
				$rtf->parse('itsize', encode_for_rtf($item_def['item_size']));
				$rtf->parse('tenor', encode_for_rtf($hours));
				$rtf->parse('tarif', encode_for_rtf(number_format($sub_dl_def['r_to_pay'], 2, ',', ' ')));

				$rtf->parse('nomer_dogovora', encode_for_rtf($dl_def['deal_id']));


				//выбираем цвет
				if ($model_def[color] == '0') {
					$it_color = '';
				} elseif ($model_def[color] == 'multicolor') {
					$it_color = ', цвет: ' . $item_def['item_color'];
				} else {
					$it_color = ', цвет: ' . $model_def[color];
				}


				$item_name = $model_def['model'] . $it_color . '(инв.№' . substr($dl_def['item_inv_n'], 0, 3) . '-' . substr($dl_def['item_inv_n'], 3) . ').';
				$rtf->parse('itemname', encode_for_rtf($item_name));
				$rtf->parse('agr_price', encode_for_rtf($model_def['agr_price']));
				$rtf->parse('agrcur', encode_for_rtf(money_print($model_def['agr_price'], $model_def['agr_price_cur'])));

				$rtf->parse('acc_date', encode_for_rtf(date("d.m.Y", $sub_dl_def['acc_date'])));
				$rtf->parse('start_date', encode_for_rtf(date("d.m.Y", $sub_dl_def['from'])));
				$rtf->parse('start_hour', encode_for_rtf(date("H:i", $sub_dl_def['from'])));
				$rtf->parse('pay_date', encode_for_rtf(date("d.m.Y", $sub_dl_def['acc_date'])));
				$rtf->parse('rto_pay_sum', encode_for_rtf(number_format($sub_dl_def['r_to_pay'], 2, ',', ' ')));
				$rtf->parse('rto_pay_cur', encode_for_rtf(money_print($sub_dl_def['r_to_pay'], 'TBYR')));
				$rtf->parse('return_date', encode_for_rtf(date("d.m.Y", $sub_dl_def['to'])));
				$rtf->parse('return_hour', encode_for_rtf(date("H:i", $sub_dl_def['to'])));


				$rtf->out_h('ndk1.rtf');
				//$rtf->out_f('/1/ndk1.rtf');
				echo $rtf->out(); //viewport

			}

			break;





	}//end of action switch
}// end of where is switch of actions




if (isset($_POST['client_id']) || (isset($client_id) && $client_id > 0)) {

	$mysqli = Db::getInstance()->getConnection();

	$client_id = get_post('client_id');
	(isset($edit_all) && $edit_all == 1) ? $d_disabled = '' : $d_disabled = 'disabled="disabled"';

	$query_cl_def = "SELECT * FROM clients WHERE client_id='$client_id'";
	$result_cl_def = $mysqli->query($query_cl_def);
	if (!$result_cl_def) {
		die('Сбой при доступе к базе данных: ' . $query_cl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	}

	$rows_cl_def = $result_cl_def->num_rows;

	$cl_def = $result_cl_def->fetch_assoc();
} else {  // создание переменных для устранения ошибок в заполнении формы клиента
	$client_id = '';
	$cl_def['family'] = '';
	$cl_def['name'] = '';
	$cl_def['otch'] = '';
	$cl_def['str'] = '';
	$cl_def['dom'] = '';
	$cl_def['kv'] = '';
	$cl_def['city'] = 'Минск';
	$cl_def['pas_n'] = '';
	$cl_def['pas_date'] = '';
	$cl_def['pas_who'] = '';
	$cl_def['reg_str'] = '';
	$cl_def['reg_dom'] = '';
	$cl_def['reg_kv'] = '';
	$cl_def['reg_city'] = 'Минск';
	$cl_def['phone_1'] = '';
	$cl_def['phone_2'] = '';
	$cl_def['info'] = '';
}










echo $messaga;


if (!$client_id > 0) {  // форма поиска клиентов

	// Check if we have data to determine if the form is "filled"
	$is_filled_class = ($s_family || $s_name || $s_otch || $s_ph || $s_str || $s_dom || $s_kv || $s_client_id || $s_pas_n) ? 'is-filled' : '';

	echo '
<div class="find_cl" id="client_find_div">
<span class="div_header"> Найти клиента: </span>
<form name="poisk" method="post" action="dogovor_new.php">
	<div class="search-fields ' . $is_filled_class . '" id="client_search_fields">
		<div class="search-item">
			<label>Фамилия:</label>
			<input type="text" name="s_family" placeholder="Фамилия" value="' . $s_family . '" />
		</div>
		<div class="search-item">
			<label>Имя:</label>
			<input type="text" name="s_name" placeholder="Имя" value="' . $s_name . '" />
		</div>
		<div class="search-item">
			<label>Отчество:</label>
			<input type="text" name="s_otch" placeholder="Отчество" value="' . $s_otch . '"/>
		</div>
		<div class="search-item">
			<label>Телефон:</label>
			<input type="text" name="s_ph" placeholder="Телефон" value="' . $s_ph . '" />
		</div>

		<div class="search-item">
			<label>Улица:</label>
			<input type="text" name="s_str" placeholder="Улица" value="' . $s_str . '" />
		</div>
		<div class="search-item">
			<label>Дом:</label>
			<input type="text" name="s_dom" placeholder="Дом" value="' . $s_dom . '" />
		</div>
		<div class="search-item">
			<label>Кв.:</label>
			<input type="text" name="s_kv" placeholder="Кв." value="' . $s_kv . '" />
		</div>

		<div class="search-item">
			<label>№ клиента:</label>
			<input type="text" name="s_client_id" placeholder="№ клиента" value="' . $s_client_id . '" />
		</div>
		<div class="search-item">
			<label>№ паспорта:</label>
			<input type="text" name="s_pas_n" placeholder="№ паспорта" value="' . $s_pas_n . '" />
		</div>
	</div>
</div>
	<input type="submit" name="action" value="найти" /> <input type="button" value="Завести нового клиента" id="" onclick="cl_displ (); return false;" />
	<input type="hidden" name="item_inv_n" id="inv_n_in_find" value="" />
</form>
</div>
';
} else {
	echo '<input type="hidden" name="item_inv_n" id="inv_n_in_find" value="" />';
}//для корректной обработки поиска после вводи инв. н. иначе глючит основной скрипт выборки айтема, т.к. не находит поля в поиске клиентов для присвоения инв. номера


// выводим результаты поиска клиентов
if (isset($client_search)) {

	echo '
<table border="1" cellspacing="0">
<tr>
	<th>id</th>
	<th>ФИО [тов. на руках]</th>
	<th>Адрес</th>
	<th>Прописка</th>
	<th>Паспорт</th>
	<th>Телефоны</th>
	<th>Инфо</th>
	<th>Действия</th>
</tr>';

	while ($client_list = $result_cl->fetch_assoc()) {
		$cl_act_deals_num = $client_list['act_num'];


		echo '
	<form name="client_s_' . $client_list['client_id'] . '" method="post" action="dogovor_new.php" >
	<tr ' . ($cl_act_deals_num > 0 ? 'style="background-color:#7CFC00;"' : '') . '>
		<td>' . $client_list['client_id'] . ' <input type="hidden" name="client_id" value="' . $client_list['client_id'] . '" /> </td>
    	<td>' . $client_list['family'] . ' ' . $client_list['name'] . ' ' . $client_list['otch'] . ' ' . ($cl_act_deals_num > 0 ? '[' . $cl_act_deals_num . ']' : '') . ' </td>
    	<td>' . $client_list['str'] . ' ' . $client_list['dom'] . '-' . $client_list['kv'] . ',' . $client_list['city'] . '</td>
    	<td>' . $client_list['reg_str'] . ' ' . $client_list['reg_dom'] . '-' . $client_list['reg_kv'] . ',' . $client_list['reg_city'] . '</td>
    	<td>' . $client_list['pas_n'] . ', выдан ' . date("d-m-Y", $client_list['pas_date']) . ' ' . $client_list['pas_who'] . '</td>
    	<td>' . phone_print($client_list['phone_1']) . '<br />' . phone_print($client_list['phone_2']) . '</td>
    	<td>' . $client_list['info'] . '</td>
    	<td><input type="hidden" name="item_inv_n" id="inv_n_in_find" value="' . $item_inv_n . '" />

						<input type="submit" value="выбрать" /></td>
	</tr>
	</form>
	';
	}
	echo '</table>';

	if ($rows_cl == 0)
		echo '<font color="red"><h3>Извините, по Вашему запросу ничего не найдено</h3></font>';

}//end of cl search if



// основаная форма по клиенту
echo '
<form name="main" id="main_form" method="post" action="dogovor_new.php" >

<div class="find_cl" id="client_info_div" ' . ($client_id > 0 ? '' : 'style="display:none;"') . '>
';

if ($cl_def['pas_date'] != NULL)
	$cl_def['pas_date'] = date("Y-m-d", $cl_def['pas_date']);

if ($client_id > 0) {
	echo '<span class="div_header" id="client_header">Информация о клиенте (№' . $client_id . '): <input type="button" value="редактировать информацию клиента" id="cl_edit_button" onclick="hide_client(); return false;" /></span>';
} else {
	echo '<span class="div_header" id="client_header">Ввести нового клиента:</span>';
	if (\bb\models\User::getCurrentUser()->isOwner()) {
		echo ' <input type="button" value="Заполнить плейсхолдерами" onclick="fill_placeholders(); return false;" />';
	}
}


echo '<br />
<input type="hidden" name="client_id" id="client_id" value="' . $client_id . '" />
<input type="hidden" name="client_update" id="client_update" value="' . $client_update . '" />
Фамилия:<input type="text" name="family" id="family" size="30" ' . $d_disabled . ' value="' . $cl_def['family'] . '" />
Имя: <input type="text" name="name" id="name" size="30" ' . $d_disabled . ' value="' . $cl_def['name'] . '" />
Отчество:<input type="text" name="otch" id="otch" size="30" ' . $d_disabled . ' value="' . $cl_def['otch'] . '" /><br />

Адрес: улица:<input type="text" name="str" id="str" size="30" ' . $d_disabled . ' value="' . $cl_def['str'] . '" />, дом:<input type="text" name="dom" id="dom" size="3" ' . $d_disabled . ' value="' . $cl_def['dom'] . '" />, квартира:<input type="text" name="kv" id="kv" size="3" ' . $d_disabled . ' value="' . $cl_def['kv'] . '" />, город:<input type="text" name="city" id="city" size="10" ' . $d_disabled . ' value="' . $cl_def['city'] . '" /> <input type="button" value="копировать адрес в прописку" id="address_copy" ' . $d_disabled . ' onclick="copy_addr(); return false;" /><br />
Прописка: улица:<input type="text" name="reg_str" id="reg_str" ' . $d_disabled . ' size="30" value="' . $cl_def['reg_str'] . '" />, дома:<input type="text" name="reg_dom" id="reg_dom" size="3" ' . $d_disabled . ' value="' . $cl_def['reg_dom'] . '" />, квартира:<input type="text" name="reg_kv" id="reg_kv" size="3" ' . $d_disabled . ' value="' . $cl_def['reg_kv'] . '" />, город:<input type="text" name="reg_city" id="reg_city" size="10" ' . $d_disabled . ' value="' . $cl_def['reg_city'] . '" /> <br />

№ паспорта:<input type="text" name="pas_n" id="pas_n" size="30" ' . $d_disabled . ' value="' . $cl_def['pas_n'] . '" />
выдан (дата):<input type="date" name="pas_date" id="pas_date" ' . $d_disabled . ' value="' . $cl_def['pas_date'] . '" />
выдан (кем):<input type="text" name="pas_who" id="pas_who" size="30" ' . $d_disabled . ' value="' . $cl_def['pas_who'] . '" /><br />
Личный номер:<input type="text" name="pas_ln" id="pas_ln" size="14" maxlength="14" ' . $d_disabled . ' value="' . $cl_def['pas_ln'] . '" /><br />

Телефон 1 (+375):<input type="text" name="phone_1" id="phone_1" size="30" ' . $d_disabled . ' value="' . phone_print($cl_def['phone_1']) . '" />
Телефон 2 (+375):<input type="text" name="phone_2" id="phone_2" size="30" ' . $d_disabled . ' value="' . phone_print($cl_def['phone_2']) . '" /> <i>Если 2-й телефон отсутствует - ставьте 0 (нуль)!!!</i><br />
Дополнительная информация:<br/> <textarea cols="100" rows="3" name="info" id="info" ' . good_print($d_disabled) . '>' . $cl_def['info'] . '</textarea><br />';

if ($client_id > 0) {
	echo '
        <select name="source" ' . $d_disabled . ' id="source_sel">
            <option value="0">Не заполнено</option>
            <option value="search" ' . Base::sel_d($cl_def['source'], 'search') . '>Поиск: google\yandex</option>
            <option value="instagram" ' . Base::sel_d($cl_def['source'], 'instagram') . '>Инстаграм</option>
            <option value="kufar" ' . Base::sel_d($cl_def['source'], 'kufar') . '>Куфар</option>
            <option value="friends" ' . Base::sel_d($cl_def['source'], 'friends') . '>Рекомендация</option>
            <option value="vyveska" ' . Base::sel_d($cl_def['source'], 'vyveska') . '>Вывеска</option>
            <option value="other" ' . Base::sel_d($cl_def['source'], 'other') . '>Иное</option>
        </select>
    ';
} else {
	echo '
        <div class="radio-toolbar" id="source_radio_div">
            <input type="radio" name="source" value="search" id="search">     <label for="search">Поиск: google\yandex</label>
            <input type="radio" name="source" value="instagram" id="instagram">     <label for="instagram">Инстаграм</label>
            <input type="radio" name="source" value="kufar" id="kufar">     <label for="kufar">Куфар</label>
            <input type="radio" name="source" value="friends" id="friends">     <label for="friends">Рекомендация</label>
            <input type="radio" name="source" value="vyveska" id="vyveska">     <label for="vyveska">Вывеска</label>
            <input type="radio" name="source" value="other" id="other">     <label for="other">Иное</label>
        </div>
    ';
}
?>
<script language="javascript">
	function fill_placeholders() {
		// Fields to fill with "____"
		var textFields = ['name', 'otch', 'city', 'str', 'dom', 'kv', 'pas_n', 'pas_who', 'reg_city', 'reg_str', 'reg_dom', 'reg_kv'];

		textFields.forEach(function (id) {
			var el = document.getElementById(id);
			if (el && !el.value) {
				el.value = "____";
			}
		});

		// Date field
		var pasDate = document.getElementById('pas_date');
		if (pasDate && !pasDate.value) {
			pasDate.value = "1900-01-01";
		}

		// Phone 2
		var phone2 = document.getElementById('phone_2');
		if (phone2 && !phone2.value) {
			phone2.value = "0";
		}
	}
</script>
<?php
echo '
<br /><input type="submit" name="action" id="action_save_cl" ' . ($client_id > 0 ? 'value="обновить информацию только по клиенту" disabled="disabled"' : 'value="сохранить только клиента"') . ' onclick="return form_check_cl();" /> 
';


echo '
<br />
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
					<td><input type="button" value="показать товары" onclick="chose_item(\'arch_hist\', \'\'); return false;" /></td>
				</tr>
</table>
<div id="arch_hist_div"></div>
</div>
 <br />
';
$mysqli = Db::getInstance()->getConnection();
// смотрим, что у клиента на руках
$query_cl_onh = "SELECT * FROM rent_deals_act WHERE client_id='$client_id'";
$result_cl_onh = $mysqli->query($query_cl_onh);
if (!$result_cl_onh) {
	die('Сбой при доступе к базе данных: ' . $query_cl_onh . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
$cl_onh_num = $result_cl_onh->num_rows;

if ($cl_onh_num > 0) {
	$total_to_pay = 0;
	$total_paid = 0;
	$past_due_total = 0;
	echo '<strong>У клиента на руках (у учетом в пути у курьера) ' . $cl_onh_num . ' товар(-ов):</strong>

				<table border="1" cellspacing="0">
				<tr>
					<th>инв.№</th>
					<th>Товар</th>
					<th>с</th>
					<th>по</th>
					<th>Сумма сделки</th>
					<th>Долг <br />(- нам, + мы)</th>
					<th>За просрочку</th>
					<th>Действия</th>
				</tr>

					';

	while ($cl_onh = $result_cl_onh->fetch_assoc()) {
		$to_pay_cl = ($cl_onh['r_to_pay'] + $cl_onh['delivery_to_pay']);
		$paid_cl = ($cl_onh['r_paid'] + $cl_onh['delivery_paid']);
		$past_due_2 = pay_calc($cl_onh['deal_id'], mktime(0, 0, 0), 'num');

		$total_paid += $paid_cl;
		$total_to_pay += $to_pay_cl;
		$past_due_total += $past_due_2;

		$query = "SELECT * FROM tovar_rent_items WHERE item_inv_n='" . $cl_onh['item_inv_n'] . "'";
		$result = $mysqli->query($query);
		if (!$result) {
			die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}
		$item = $result->fetch_assoc();

		$query_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='" . $item['model_id'] . "'";
		$result_model = $mysqli->query($query_model);
		if (!$result_model) {
			die('Сбой при доступе к базе данных: ' . $query_model . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}
		$model = $result_model->fetch_assoc();

		$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='" . $model['tovar_rent_cat_id'] . "'";
		$result_cat = $mysqli->query($query_cat);
		if (!$result_cat) {
			die('Сбой при доступе к базе данных: ' . $query_cat . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}
		$cat = $result_cat->fetch_assoc();

		$model['color'] == '0' ? ($color = '') : ($color = ', цвет: ' . $model['color'] . ': ' . $item['item_color']); // если цвет отсутствует - то ничего не выводим, иначе выводим цвет

		//ищем место первой сдачи
		$query_sub_first = "SELECT * FROM rent_sub_deals_act WHERE deal_id='" . $cl_onh['deal_id'] . "' AND `type`='first_rent'";
		$result_first = $mysqli->query($query_sub_first);
		if (!$result_first) {
			die('Сбой при доступе к базе данных: ' . $query_sub_first . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}
		$sub_first = $result_first->fetch_assoc();

		if ($sub_first['delivery_yn'] == 1) {
			$fr_col = 'style="background-color:blue"';
		} elseif ($sub_first['place'] == 1) {
			$fr_col = 'style="background-color:green"';
		} elseif ($sub_first['place'] == 2) {
			$fr_col = 'style="background-color:orange"';
		} else {
			$fr_col = '';
		}


		echo '
				<tr>
					<td ' . $fr_col . '>' . $cl_onh['item_inv_n'] . '</td>
					<td>' . ($model['model_addr'] != '' ? $model['model_addr'] : $cat['dog_name']) . ' ' . $model['producer'] . ', модель: ' . $model['model'] . $color . '</td>
					<td>' . date("d.m.Y", $cl_onh['start_date']) . '</td>
					<td ';

		if ($cl_onh['return_date'] == mktime(0, 0, 0)) {
			echo 'style="background-color:yellow"';
		} elseif ($cl_onh['return_date'] < mktime(0, 0, 0)) {
			echo 'style="background-color:red"';
		} else {
			echo '';
		}



		echo '>' . date("d.m.Y", $cl_onh['return_date']) . '</td>
					<td>' . number_format($to_pay_cl, 2, ',', ' ') . '</td>
					<td>' . number_format(($paid_cl - $to_pay_cl), 2, ',', ' ') . '</td>
					<td>' . $past_due_2 . '</td>
					<td>
						<input type="button" name="noname" value="оформить" onclick="chose_item_cl(\'' . $cl_onh['item_inv_n'] . '\'); return false;" />

						</td>
				</tr>

					';
	}

	echo '		<tr>
						<td></td>
						<td><strong>Итого:</strong></td>
						<td></td>
						<td></td>
						<td><strong>' . number_format($total_to_pay, 2, ',', ' ') . '</strong></td>
						<td><strong>' . number_format(($total_paid - $total_to_pay), 2, ',', ' ') . '</strong></td>
						<td><strong>' . $past_due_total . '</strong></td>
						<td><input type="submit" value="пересчитать"></td>
					</tr>

						</table>';


} else {
	echo '<strong>У клиента нет товаров на руках.</strong>';
}


echo '
</div>';




//выбираем курьеров
$query_cur = "SELECT * FROM logpass WHERE delivery='1' ORDER BY lp_fio DESC";
$result_cur = $mysqli->query($query_cur);
if (!$result_cur) {
	die('Сбой при доступе к базе данных: ' . $query_cur . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}




echo '
<div class="find_cl" id="deal_area">
	<span class="div_header">Сделка' . ($dl_def['deal_id'] != '' ? (' (№' . $dl_def['deal_id'] . ')') : '') . ':</span></br>
	<input type="hidden" name="inv_n_ok" id="inv_n_ok" value="" />	<!--Для неотрпвки формы без выбора правильного инв. номера-->

	Инвентарный номер: <input type="text" name="item_inv_n" id="item_inv_n" value="' . $item_inv_n . '" onkeypress="if ( event.keyCode == 13 ) {chose_item(\'select\', \'\'); return false;}" /> <input type="button" id="inv_n_select_but" value="выбрать товар" onclick="chose_item(\'select\', \'\'); return false;" />
																																															<input type="button" value="печать договора предзаказа" onclick="predzakaz()" />только после выбора клиента и ввода инвентарного номера<br />

<div id="deal_div">
</div>


</div>


		';


if (isset($item_inv_n) && $item_inv_n > 0) {
	echo '
<script language="javascript">
			chose_item(\'select\', \'\');
</script>
			';
}

echo '<div id="main_buttons" style="display:none;"><input type="submit" name="action" id="action_save" value="сохранить" onclick="return form_check( );" /> <span id="deliv_but_info"> <input type="submit" id="action_delivery" name="action" value="сохранить для курьера" onclick="return form_check_cur();" />

<span style="display: none;">
Курьер:
<select name="courier_id" id="courier_id">
    <option value="0">не выбрано</option>
	';

while ($cur = $result_cur->fetch_assoc()) {

	//echo'<option value="'.$cur['logpass_id'].'" '.($cur['logpass_id']==$sub_dl_def['courier_id'] ? 'selected="selected"' : '').'>'.$cur['lp_fio'].'</option>';
}
echo '</select>
</span>
</span>



	<input type="button" value="Отмена" onclick="location.href=\'/bb/dogovor_new.php\';" /></div>';


$pr_displ = 'style="display:none;"';

if (isset($item_inv_n) && $item_inv_n > 0) {
	$pr_displ = '';
}
echo '<div id="print_buttons" ' . $pr_displ . '>
				<input type="submit" name="action" id="action_print" value="распечатать договор" />
				<input type="button" name="action" id="action_print" value="новый договор/сделка" onclick="location.href=\'/bb/dogovor_new.php\';" />
			</div>';



echo '
</form>';
?>

<!-- New delivery date script -->
<script>

	function delivery_change_start_btn() {
		let startBtn = document.querySelector('.new_deliv_start_btn');
		let delivDiv = document.querySelector('.delivery_change_div');
		startBtn.classList.toggle('hide');
		delivDiv.classList.toggle('hide');
	}


	async function change_delivery_date() {
		let dealId = document.querySelector('[name="active_deal_id"]').value;
		let newDateValue = document.querySelector('[name="new_delivery_date"]').value;
		phpUrl = '/bb/item_ch_new.php';

		// --- 1. Prepare the data to send ---
		// We use FormData, which is easy for PHP to read via $_POST
		const formData = new FormData();
		formData.append('action', 'delivery_date_change'); // Key name 'parameter1' can be accessed in PHP via $_POST['parameter1']
		formData.append('deal_id', dealId); // Key name 'parameter2' can be accessed in PHP via $_POST['parameter2']
		formData.append('new_date', newDateValue); // Key name 'parameter3' can be accessed in PHP via $_POST['parameter3']

		// --- 2. Configure the fetch request ---
		const fetchOptions = {
			method: 'POST', // Use POST method to send data
			body: formData, // Attach the FormData object as the request body
			// Note: When using FormData, the browser automatically sets the
			// 'Content-Type' header to 'multipart/form-data'.
			// No need to set headers manually for this case.
		};

		// --- 3. Perform the asynchronous request ---
		try {
			console.log(`Sending data to: ${phpUrl}`);
			const response = await fetch(phpUrl, fetchOptions);

			// --- 4. Check if the HTTP request itself was successful ---
			if (!response.ok) {
				// Handle HTTP errors (e.g., 404 Not Found, 500 Internal Server Error)
				const errorText = `HTTP error! Status: ${response.status} ${response.statusText}`;
				console.error('Fetch error:', errorText);
				if (onError) onError(errorText); // Call the error callback
				return; // Stop execution
			}
			// --- 5. Parse the JSON response from PHP ---
			// We assume the PHP script sends back JSON
			const result = await response.json();
			console.log('Received response:', result);

			// --- 6. Process the response based on the 'success' boolean ---
			if (result && typeof result.success === 'boolean') {
				if (result.success) {
					// Action on success
					console.log('Operation successful:', result.message);
					if (onSuccess) onSuccess(result.message); // Call the success callback
				} else {
					// Action on failure (as indicated by the PHP script)
					console.warn('Operation failed:', result.message);
					if (onError) onError(result.message); // Call the error callback with the PHP message
				}
			} else {
				// Handle cases where the response is not the expected JSON format
				const formatError = 'Invalid response format from server.';
				console.error(formatError, result);
				if (onError) onError(formatError); // Call the error callback
			}

		} catch (error) {
			// --- 7. Handle network errors or other issues during fetch/parsing ---
			console.error('Error during fetch operation:', error);
			if (onError) onError(error); // Call the error callback with the caught error object
		}
	}

	function onError(text) {
		console.log('error');
		console.log(text);
	}

	function onSuccess(text) {
		console.log('success');
		console.log(text);
		document.querySelector('#inv_n_select_but').click();
	}

</script>
<?php

echo '
</body>
</html>';








function get_post($var)
{
	$mysqli = Db::getInstance()->getConnection();
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


function phone_to_n($ph)
{
	$ph = preg_replace("|[^0-9]|i", "", $ph);
	return $ph;
}



function tonum($value)
{

	$output = floatval(str_replace(',', '.', $value));
	return $output;

}



// класс для печати договора
/**
 * Class RTF template
 * 2011 Igor Artasevych, Andrey Yaroshenko
 *
 */
class RTF_Template
{
	/*****************************************************************************/
	/* variables */
	private $content;
	/* functions */
	/**
	 * RTF_Template::__construct()
	 *
	 * @param mixed $filename
	 * @return
	 */
	public function __construct($filename)
	{
		$this->content = file_get_contents($filename);
	}//construct
	/*************************************************************************/
	/**
	 * RTF_Template::parse()
	 *
	 * @param mixed $block_name
	 * @param mixed $value
	 * @param string $start_tag
	 * @param string $end_tag
	 * @return
	 */
	public function parse($block_name, $value, $start_tag = '', $end_tag = '')
	{
		$this->content = str_ireplace($start_tag . $block_name . $end_tag, $value, $this->content);
	}//
	/*************************************************************************/
	/**
	 * RTF_Template::out_f()
	 *
	 * @param mixed $filename
	 * @return
	 */
	public function out_f($filename)
	{
		file_put_contents($filename, $this->content);
	}//
	/*************************************************************************/
	/**
	 * RTF_Template::out_h()
	 *
	 * @param mixed $filename
	 * @return
	 */
	public function out_h($filename)
	{
		ob_clean();
		header("Content-type: plaintext/rtf");
		header("Content-Disposition: attachment; filename=$filename");
		echo $this->content;
	}//
	/*************************************************************************/
	/**
	 * RTF_Template::out()
	 *
	 * @param mixed $filename
	 * @return
	 */
	public function out()
	{
		return $this->content;
	}//
}//class


function encode_for_rtf($str)
{
	$str = bin2hex(iconv('utf-8', 'windows-1251', $str));
	$str = preg_replace("/([a-zA-Z0-9]{2})/", "\'$1", $str);

	return $str;
}


function money_print($amount, $cur)
{

	if ($amount < 20 && $amount != 10) {
		$cut = $amount;
	} else {
		$cut = substr($amount, -1, 1);
	}

	switch ($cur) {

		case 'TBYR':
			if ($cut == '1') {
				return 'рубль';
			} elseif ($cut == '0') {
				return 'рублей';
			} elseif ($cut > 1 && $cut < 5) {
				return 'рубля';
			} elseif ($cut > 4 && $cut < 20) {
				return 'рублей';
			}

			break;


		case 'USD':
			if ($cut == '1') {
				return 'доллар США';
			} elseif ($cut == '0') {
				return 'долларов США';
			} elseif ($cut > 1 && $cut < 5) {
				return 'доллара США';
			} elseif ($cut > 4 && $cut < 20) {
				return 'долларов США';
			}

			break;


		case 'EUR':
			return 'евро';

			break;


		case 'RUB':
			if ($cut == '1') {
				return 'росс. рубль';
			} elseif ($cut == '0') {
				return 'рос. рублей';
			} elseif ($cut > 1 && $cut < 5) {
				return 'росс. рубля';
			} elseif ($cut > 4 && $cut < 20) {
				return 'росс. рублей';
			}

			break;

	}//end of switch
}//end of function


function step_pr($step)
{
	switch ($step) {
		case 'day':
			return 'день';
			break;

		case 'week':
			return 'неделя';
			break;

		case 'month':
			return 'месяц';
			break;

		default:
			return 'не определено';
			break;
	}
}


function cur_pr($cur)
{
	switch ($cur) {
		case 'USD':
			return 'доллары США';
			break;

		case 'EUR':
			return 'евро';
			break;

		case 'TBYR':
			return 'бел. руб.';
			break;

		default:
			return 'не определено';
			break;
	}
	;
}


function pay_calc($deal_id, $ret_date, $num)
{

	$mysqli = Db::getInstance()->getConnection();

	//запрос информации о сделке
	$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
	$result_dl_def = $mysqli->query($query_dl_def);
	if (!$result_dl_def) {
		die('Сбой при доступе к базе данных: ' . $query_dl_def . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	}
	$dl_def = $result_dl_def->fetch_assoc();

	//вытягиваем последний примененный тариф
	$query_sub_dl_tarif = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND type IN ('first_rent', 'extention', 'takeaway_plan') ORDER BY `from` DESC";
	$result_sub_dl_tarif = $mysqli->query($query_sub_dl_tarif);
	if (!$result_sub_dl_tarif) {
		die('Сбой при доступе к базе данных: ' . $query_sub_dl_tarif . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	}
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


		//$output=substr($inv_n, 0, 3).'-'.substr($inv_n, 3);
	} elseif ($ret_date == $dl_def['return_date']) {
		$num == 'num' ? $morepay = '0' : $morepay = 'срок возврата сегодня';
		$to_pay_ad = '0';
	} else {
		$num == 'num' ? $morepay = '0' : $morepay = 'срок не наступил';
		$to_pay_ad = '0';
	}





	return $morepay;
}//end of pay_calc func


function inv_print($inv_n)
{

	$output = substr($inv_n, 0, 3) . '-' . substr($inv_n, 3);

	return $output;

}



?>