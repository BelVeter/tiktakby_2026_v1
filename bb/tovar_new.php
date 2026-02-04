<?php

use bb\Base;
use bb\classes\tovar;
use bb\models\Office;

session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/KBronForm.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/Office.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/tovar.php');

//------- proverka paroley

$in_level = array(0, 5, 7);

$mysqli = \bb\Db::getInstance()->getConnection();

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
<title>Товары.</title>
<body>


<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> ' . $_SESSION['user_fio'] . '</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/kr_baza_new.php">Просмотр всех товаров</a>
	<a class="div_item" href="/bb/tovar_new_mod.php">Внести новую модел</a>
</div>


		';
require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show.php'); // включаем подключение к звонкам

//Проверка входящей информации
//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//foreach ($_POST as $key => $value) {
//	echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";



$tovar = NULL;

$action = '';
$model_id = '';
$cat_id = '';
$producers_list = '';
$model_options = '';
$color_option = '';

$model_def['set'] = '';
$model_def['agr_price'] = '';
$model_def['agr_price_cur'] = '';
$model_def['lom_srok'] = '';
$model_def['age_from'] = '';
$model_def['age_to'] = '';
$model_def['weight_from'] = '';
$model_def['weight_to'] = '';
$model_def['m_sex'] = '';
$model_def['collateral'] = '';
$model_def['ny'] = '';
$model_def['zv'] = '';
$model_def['tale'] = '';
$model_def['rez1'] = '';
$model_def['rez2'] = '';


$item_def['buy_date'] = time();
$cat_def['dog_name'] = '';

$item_def['exch_to_byr'] = 1;
$inv_n_upd = '';

$item_def['item_inv_n'] = '';
$item_def['item_color'] = '';
$item_def['sex'] = '';
$item_def['item_size'] = '';
$item_def['item_rost1'] = '';
$item_def['item_rost2'] = '';

$item_def['real_item_size'] = '';
$item_def['item_set'] = '';
$item_def['buy_price'] = '';
$item_def['buy_price_cur'] = '';
$item_def['seller'] = '';
$item_def['status'] = '';
$item_def['item_place'] = '';
$item_def['item_info'] = '';
$item_def['state'] = '';
//$item_def['to_clean']='';





if (isset($_POST['action'])) {

	foreach ($_POST as $key => $value) {
		$$key = get_post($key);
	}

	switch ($action) {

		case 'сохранить':

			//нужно чтоб обязательно был кат айди.
			$cat_id = $cat_select_old;

			//item inv n 1-t part calculation
			if ($cat_id < 10) {
				$cat_n_pl = 70;
				$cat_id_num = $cat_id;
			} elseif ($cat_id < 100) {
				$cat_n_pl = 7;
				$cat_id_num = $cat_id;
			} elseif ($cat_id < 1000) {
				$cat_n_pl = '';
				$cat_id_num = $cat_id;
			}
			if ($cat_id > 699) {
				$cat_id_num = $cat_id + 100;
			}

			$inv_start = $cat_n_pl . $cat_id_num;

			//item number within the cathegory calculation
			$query_item_n = "SELECT item_n FROM tovar_rent_items WHERE item_inv_n LIKE '$inv_start%' ORDER BY item_n DESC LIMIT 0,1";
			$result_item_n = $mysqli->query($query_item_n);
			if (!$result_item_n) {
				die('Сбой при доступе к базе данных: ' . $query_item_n . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			$num1 = $result_item_n->num_rows;

			if ($num1 > 0) {
				$item1 = $result_item_n->fetch_assoc();
				$max_num1 = $item1['item_n'];
			} else {
				$max_num1 = 0;
			}

			$query_item_n2 = "SELECT item_n FROM tovar_rent_items_arch WHERE item_inv_n LIKE '$inv_start%' ORDER BY item_n DESC LIMIT 0,1";
			$result_item_n2 = $mysqli->query($query_item_n2);
			if (!$result_item_n2) {
				die('Сбой при доступе к базе данных: ' . $query_item_n2 . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			$num2 = $result_item_n2->num_rows;

			if ($num2 > 0) {
				$item2 = $result_item_n2->fetch_assoc();
				$max_num2 = $item2['item_n'];
			} else {
				$max_num2 = 0;
			}


			if ($max_num1 >= $max_num2) {
				$max_n = $max_num1;
			}
			if ($max_num1 < $max_num2) {
				$max_n = $max_num2;
			}


			$item_n = $max_n + 1;

			//--- end of max_n calculation

			//item inv n calculation
			$item_inv_n = $cat_n_pl . $cat_id_num . $item_n;

			//gotovim nekotorie znachtniya
			$buy_date = strtotime($buy_date);
			$producer_name = $producer_select_old;

			$query_new_item = "INSERT INTO tovar_rent_items VALUES('', '$cat_id', '$producer_name', '$model_id', '$item_n', '$item_inv_n', '$item_sex', '$tovar_size', '$real_tovar_size', '$tovar_rost1', '$tovar_rost2', '$item_set', '$buy_date', '$buy_price', '$buy_currency', '$exchange_rate', '$seller', '$info', '" . time() . "', '" . $_SESSION['user_fio'] . "', '$tovar_status', '', '$item_color', '$tovar_place', '', '$tovar_state', '$tovar_clean', '')";
			$result_new_item = $mysqli->query($query_new_item);
			if (!$result_new_item) {
				die('Сбой при доступе к базе данных: ' . $query_new_item . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			echo 'Товар успешно введен. <be /> Инвентарный номер:' . $item_inv_n . '<br />';



			die('
			</head>
			<body>

				<div class="top_menu">
					<a class="div_item" href="/bb/tovar_new.php">Новый товар</a>
				</div>
				<br /><br />

				<div class="top_menu">
					<a class="div_item" href="/bb/rent_tarifs.php">Работа с тарифами</a>
				</div>
				</body></html>
		');




			break;


		case 'редактировать':

			$tovar = tovar::geTovarById($item_id);

			//предполагается входящий id товара
			$query_item = "SELECT * FROM tovar_rent_items WHERE item_id='$item_id'";
			$result_item = $mysqli->query($query_item);
			if (!$result_item) {
				die('Сбой при доступе к базе данных: ' . $query_item . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$item_def = $result_item->fetch_assoc();

			$model_id = $item_def['model_id'];

			$query_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='" . $item_def['model_id'] . "'";
			$result_model = $mysqli->query($query_model);
			if (!$result_model) {
				die('Сбой при доступе к базе данных: ' . $query_model . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$model_def = $result_model->fetch_assoc();

			$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='" . $model_def['tovar_rent_cat_id'] . "'";
			$result_cat = $mysqli->query($query_cat);
			if (!$result_cat) {
				die('Сбой при доступе к базе данных: ' . $query_cat . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$cat_def = $result_cat->fetch_assoc();
			$cat_id = $cat_def['tovar_rent_cat_id'];

			//chose tovar producers
			$query_prod = "SELECT DISTINCT producer FROM tovar_rent ORDER BY producer";
			$result_prod = $mysqli->query($query_prod);
			if (!$result_prod) {
				die('Сбой при доступе к базе данных: ' . $query_prod . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			while ($prod_names = $result_prod->fetch_assoc()) {
				$producers_list .= '
					<option value="' . good_print($prod_names['producer']) . '" ' . sel_d($model_def['producer'], $prod_names['producer']) . '>' . good_print($prod_names['producer']) . '</option>
					';
			}

			//chose model list
			$query_model = "SELECT DISTINCT model FROM tovar_rent ORDER BY model";
			$result_model = $mysqli->query($query_model);
			if (!$result_model) {
				die('Сбой при доступе к базе данных: ' . $query_model . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}


			while ($model_list = $result_model->fetch_assoc()) {
				$model_options .= '<option value="' . good_print($model_list['model']) . '" ' . sel_d($model_def['model'], $model_list['model']) . '>' . good_print($model_list['model']) . '</option>';
			}

			$color_option = '<option value="' . $model_def['color'] . '" selected="selected">' . $model_def['color'] . '</option>';

			break;


		case 'обновить':

			//инвентарный номер не пересчитываем !!!

			$buy_date = strtotime($buy_date);

			$query_upd = "UPDATE tovar_rent_items SET cat_id='$cat_select_old', producer='$producer_select_old', model_id='$model_id', item_color='$item_color', sex='$item_sex', item_size='$tovar_size', real_item_size='$real_tovar_size', item_rost1='$tovar_rost1', item_rost2='$tovar_rost2', item_set='$item_set', buy_date='$buy_date', buy_price='$buy_price', buy_price_cur='$buy_currency', exch_to_byr='$exchange_rate', seller='$seller', item_info='$info', `status`='$tovar_status', item_place='$tovar_place', `state`='$tovar_state', cr_ch_date='" . time() . "', user='" . $_SESSION['user_fio'] . "' WHERE item_id='$item_id_upd'";
			$result_upd = $mysqli->query($query_upd);
			if (!$result_upd) {
				die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}





			$inv_n_2 = $inv_n;

			die('
			</head>
			<body>
				Товар успешно обновлен. <br />
				<form method="post" id="tovar_tarif" action="rent_tarifs.php">
					<input type="hidden" name="model_id" value="' . $model_id . '" />
					<input type="hidden" name="item_id" value="' . $item_id_upd . '" />
					<input type="hidden" name="item_inv_n2" value="' . $inv_n_2 . '" />
					<input type="submit" name="action" value="редактировать тарифы (эта модель)" />
				</form>

				<form method="post" id="tovar_tarif" action="kr_baza_new.php">
					<input type="hidden" name="cat_id" value="' . $cat_select_old . '" />
					<input type="submit" name="action" value="к товарам (эта категория)" />
				</form>

				<div class="top_menu">
					<a class="div_item" href="/bb/tovar_new.php">Новый товар</a>
				</div>
				<br /><br />

				<div class="top_menu">
					<a class="div_item" href="/bb/rent_tarifs.php">Работа с тарифами</a>
				</div>
				</body></html>
		');


			break;

	}
}// end of main action if



?>

<script>

	document.querySelector('#buy_price_cur').addEventListener('change', onCurChange);

	function onCurChange(e) {
		console.log(e.target.value);
	}


	history.pushState(null, null, location.href);
	window.onpopstate = function (event) {
		history.go(1);
	};


	function send_form_ch() {

		place = cat_chcc = cat_dogcc = prod_chcc = model_chcc = color_chcc = set_chcc = price_chcc = price_cur_chcc = lom_srokcc = buy_date_chcc = buy_price_chcc = buy_price_cur_chcc = exch_rate_chcc = seller_chcc = item_set_chcc = item_color_chcc = '';

		valid = true;

		if (document.getElementById('model_id').value == "") {
			alert('Выберите характеристики модели до конца, или введите новую модель!');
			valid = false;
		}

		if (document.getElementById('tovar_rost1').value * 1 > document.getElementById('tovar_rost2').value * 1) {
			alert('Рост должен быть заполнен в двух полях (ОТ и ДО). При этом, ОТ должно быть меньше либо равно ДО');
			valid = false;
		}


		if (document.getElementById('color_select_old').value == "multicolor" && document.getElementById('item_color').value == '') {
			item_color_chcc = "Цвет модели с multicolor, ";
			valid = false;
		}

		if (document.getElementById('item_set').value == "") {
			item_set_chcc = "Фактическая комплектация товара, ";
			valid = false;
		}

		if (document.getElementById('buy_date').value == "") {
			buy_date_chcc = "Дата приобретения, ";
			valid = false;
		}

		if (document.getElementById('buy_price').value == "") {
			buy_price_chcc = "Цена приобретения, ";
			valid = false;
		}

		if (document.getElementById('buy_price_cur').value == "") {
			buy_price_cur_chcc = "Валюта цены приобретения, ";
			valid = false;
		}

		if (document.getElementById('exch_rate').value == "") {
			exch_rate_chcc = "Курс пересчета, ";
			valid = false;
		}

		if (document.getElementById('seller').value == "") {
			seller_chcc = "Продавец, ";
			valid = false;
		}

		if (document.getElementById('tovar_place').value * 1 < 1) {
			place = "Расположение (офис), ";
			valid = false;
		}


		if (valid == false) {
			alert('Заполните все поля формы! В частности: ' + cat_chcc + cat_dogcc + prod_chcc + model_chcc + color_chcc + set_chcc + price_chcc + price_cur_chcc + lom_srokcc + item_color_chcc + item_set_chcc + buy_date_chcc + buy_price_chcc + buy_price_cur_chcc + exch_rate_chcc + seller_chcc + place);
		}

		return valid;

	}//end of send_form_ch function



	function copy_set() {

		document.getElementById('item_set').value = document.getElementById('m_set_old').value;

	}//function end


	function cat_ch() {

		cat_id = document.getElementById('cat_select_old').value;
		par2 = 'cat_producer';

		document.getElementById('producer_select_old').innerHTML = '<option value="-">...ждите...</option>';

		if (cat_id == 0) {
			document.getElementById('inv_n_cat').innerHTML = '';
			document.getElementById('model_id').value = '';
			document.getElementById('producer_select_old').innerHTML = '<option value="0">----------</option>';
			document.getElementById('model_select_old').innerHTML = '<option value="0">----------</option>';
			document.getElementById('color_select_old').innerHTML = '<option value="0">----------</option>';
			document.getElementById('m_set_old').value = '';
			document.getElementById('m_price_old').value = '';
			document.getElementById('m_price_cur_old').value = '';
			document.getElementById('lom_srok_old').value = '';
			document.getElementById('model_addr_old').value = '';
			document.getElementById('ph_addr_old').value = '';
			document.getElementById('old_model_id_span').innerHTML = '';

			document.getElementById('age_from_old').value = '';
			document.getElementById('age_to_old').value = '';
			document.getElementById('weight_from_old').value = '';
			document.getElementById('weight_to_old').value = '';

			return false;
		}

		var xmlhttp = getXmlHttp()
		xmlhttp.open("POST", '/bb/cat_ch_new.php', true)
		xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

		var params = 'cat_id=' + encodeURIComponent(cat_id) + '&par2=' + encodeURIComponent(par2);

		xmlhttp.send(params);
		xmlhttp.onreadystatechange = function () {
			if (xmlhttp.readyState == 4) {
				if (xmlhttp.status == 200) {

					eval(xmlhttp.responseText);
				}
			}
		}


		//обнуляем ранее выбранные последующие позиции при изменении категории - все на сброс
		document.getElementById('model_id').value = '';
		document.getElementById('model_select_old').innerHTML = '<option value="0">----------</option>';
		document.getElementById('color_select_old').innerHTML = '<option value="0">----------</option>';
		document.getElementById('m_set_old').value = '';
		document.getElementById('m_price_old').value = '';
		document.getElementById('m_price_cur_old').value = '';
		document.getElementById('lom_srok_old').value = '';
		document.getElementById('model_addr_old').value = '';
		document.getElementById('ph_addr_old').value = '';

		document.getElementById('age_from_old').value = '';
		document.getElementById('age_to_old').value = '';
		document.getElementById('weight_from_old').value = '';
		document.getElementById('weight_to_old').value = '';


	}//end of cat_ch


	function prod_ch() {

		cat_id = document.getElementById('cat_select_old').value;
		producer = document.getElementById('producer_select_old').value;

		document.getElementById('model_select_old').innerHTML = '<option value="-">...ждите...</option>';

		if (producer == 0) {

			document.getElementById('model_id').value = '';
			document.getElementById('model_select_old').innerHTML = '<option value="0">----------</option>';
			document.getElementById('color_select_old').innerHTML = '<option value="0">----------</option>';
			document.getElementById('m_set_old').value = '';
			document.getElementById('m_price_old').value = '';
			document.getElementById('m_price_cur_old').value = '';
			document.getElementById('lom_srok_old').value = '';
			document.getElementById('model_addr_old').value = '';
			document.getElementById('ph_addr_old').value = '';
			document.getElementById('old_model_id_span').innerHTML = '';

			document.getElementById('age_from_old').value = '';
			document.getElementById('age_to_old').value = '';
			document.getElementById('weight_from_old').value = '';
			document.getElementById('weight_to_old').value = '';

			return false;
		}

		par2 = 'producer';

		var xmlhttp = getXmlHttp()
		xmlhttp.open("POST", '/bb/cat_ch_new.php', true)
		xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

		var params = 'cat_id=' + encodeURIComponent(cat_id) + '&par2=' + encodeURIComponent(par2) + '&producer=' + encodeURIComponent(producer);

		xmlhttp.send(params);
		xmlhttp.onreadystatechange = function () {
			if (xmlhttp.readyState == 4) {
				if (xmlhttp.status == 200) {

					eval(xmlhttp.responseText);
				}
			}
		}

		//обнуляем ранее выбранные последующие позиции при изменении производителя - все на сброс
		document.getElementById('model_id').value = '';
		document.getElementById('color_select_old').innerHTML = '<option value="0">----------</option>';
		document.getElementById('m_set_old').value = '';
		document.getElementById('m_price_old').value = '';
		document.getElementById('m_price_cur_old').value = '';
		document.getElementById('lom_srok_old').value = '';
		document.getElementById('model_addr_old').value = '';
		document.getElementById('ph_addr_old').value = '';
		document.getElementById('age_from_old').value = '';
		document.getElementById('age_to_old').value = '';
		document.getElementById('weight_from_old').value = '';
		document.getElementById('weight_to_old').value = '';

	}//end of prod_ch



	function model_ch() {

		cat_id = document.getElementById('cat_select_old').value;
		producer = document.getElementById('producer_select_old').value;
		model_name = document.getElementById('model_select_old').value;

		document.getElementById('color_select_old').innerHTML = '<option value="-">...ждите...</option>';


		if (model_name == 0) {

			document.getElementById('model_id').value = '';
			document.getElementById('color_select_old').innerHTML = '<option value="0">----------</option>';
			document.getElementById('m_set_old').value = '';
			document.getElementById('m_price_old').value = '';
			document.getElementById('m_price_cur_old').value = '';
			document.getElementById('lom_srok_old').value = '';
			document.getElementById('model_addr_old').value = '';
			document.getElementById('ph_addr_old').value = '';
			document.getElementById('old_model_id_span').innerHTML = '';

			document.getElementById('age_from_old').value = '';
			document.getElementById('age_to_old').value = '';
			document.getElementById('weight_from_old').value = '';
			document.getElementById('weight_to_old').value = '';

			return false;
		}

		par2 = 'model';

		var xmlhttp = getXmlHttp()
		xmlhttp.open("POST", '/bb/cat_ch_new.php', true)
		xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

		var params = 'model_name=' + encodeURIComponent(model_name) + '&par2=' + encodeURIComponent(par2) + '&cat_id=' + encodeURIComponent(cat_id) + '&producer=' + encodeURIComponent(producer);

		xmlhttp.send(params);
		xmlhttp.onreadystatechange = function () {
			if (xmlhttp.readyState == 4) {
				if (xmlhttp.status == 200) {

					eval(xmlhttp.responseText);

				}
			}
		}


	}//end model_ch


	function color_ch() {

		cat_id = document.getElementById('cat_select_old').value;
		producer = document.getElementById('producer_select_old').value;
		model_name = document.getElementById('model_select_old').value;
		color_name = document.getElementById('color_select_old').value;

		if (color_name == 'выберите цвет') {

			document.getElementById('model_id').value = '';
			document.getElementById('m_set_old').value = '';
			document.getElementById('m_price_old').value = '';
			document.getElementById('m_price_cur_old').value = '';
			document.getElementById('lom_srok_old').value = '';
			document.getElementById('model_addr_old').value = '';
			document.getElementById('ph_addr_old').value = '';
			document.getElementById('old_model_id_span').innerHTML = '';

			document.getElementById('age_from_old').value = '';
			document.getElementById('age_to_old').value = '';
			document.getElementById('weight_from_old').value = '';
			document.getElementById('weight_to_old').value = '';

			return false;
		}


		document.getElementById('m_set_old').value = '...ждите...';

		par2 = 'color';

		var xmlhttp = getXmlHttp()
		xmlhttp.open("POST", '/bb/cat_ch_new.php', true)
		xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

		var params = 'model_name=' + encodeURIComponent(model_name) + '&color_name=' + encodeURIComponent(color_name) + '&par2=' + encodeURIComponent(par2) + '&cat_id=' + encodeURIComponent(cat_id) + '&producer=' + encodeURIComponent(producer);

		xmlhttp.send(params);
		xmlhttp.onreadystatechange = function () {
			if (xmlhttp.readyState == 4) {
				if (xmlhttp.status == 200) {

					eval(xmlhttp.responseText);

				}
			}
		}


	}//end color_ch


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
	}


</script>

<?php

//chose tovar cathegory
$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
$result_cats = $mysqli->query($query_cats);
if (!$result_cats) {
	die('Сбой при доступе к базе данных: ' . $query_cats . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
$cat_list = '';
while ($cat_names = $result_cats->fetch_assoc()) {
	$cat_list .= '<option value="' . $cat_names['tovar_rent_cat_id'] . '" ' . sel_d($cat_names['tovar_rent_cat_id'], $cat_id) . ' >' . good_print($cat_names['rent_cat_name']) . '</option>';
}


echo '
<form method="post" id="model_edit" action="tovar_new_mod.php" style="display:none;" style="display:none;" >
		<input type="hidden" name="model_id" value="' . $model_id . '">
		<input type="hidden" name="action" value="редактировать">
</form>


<form name="tovar" action="tovar_new.php" method="post">

<input type="hidden" name="item_id_upd" id="item_id_upd" value="' . (isset($item_id) ? $item_id : '') . '">


 ' . ($action == 'редактировать' ? '<a href="" class="link_ch_new" onclick="document.getElementById(\'model_edit\').submit(); return false;">редактировать модель</a>' : '') . '<br />


<div id="old_model_div" class="old_div">

<table border="1" cellspacing="0">
	<tr>
		<td>Категория товара:</td>
		<td>
			<select name="cat_select_old" id="cat_select_old" onchange="cat_ch();">
				<option value="0">выберите категорию</option>
				' . $cat_list . '
			</select>
		</td>
	</tr>
	<tr>
		<td>Фирма:</td>
		<td>
			<select name="producer_select_old" id="producer_select_old" onchange="prod_ch();">
    			<option value="0">----------</option>
				' . $producers_list . '
    		</select>

	  		<textarea id="produceer_sel_temp" readonly="readonly" style="display:none"></textarea> <!--- это чтобы кавычки двойные правильно сравнивались -->
		</td>
	</tr>

	<tr>
		<td>Модель(<span id="old_model_id_span"></span>):</td>
		<td>
			<select name="model_select_old" id="model_select_old" onchange="model_ch();">
	    		<option value="0">------------</option>
				' . $model_options . '
	    	</select>
		</td>
	</tr>

	<tr>
		<td>Цвет:</td>
		<td>
			<select name="color_select_old" id="color_select_old" onchange="color_ch();">
    			<option value="0">------------</option>
				' . $color_option . '
    		</select>
		</td>
	</tr>

	<tr>
		<td>Комплектация модели (стандарт):</td>
		<td><input type="text" name="m_set_old" size="70" id="m_set_old" readonly="readonly" value="' . $model_def['set'] . '" /></td>
	</tr>

	<tr>
		<td>Оценочная стоимость:</td>
		<td>
			<input type="text" name="m_price_old" size="10" id="m_price_old" readonly="readonly" value="' . $model_def['agr_price'] . '" />
			<input type="text" name="m_price_cur_old" size="5" id="m_price_cur_old" readonly="readonly" value="' . $model_def['agr_price_cur'] . '" />
		</td>
	</tr>
	<tr>
		<td>Цена нового (BYN):</td>
		<td>
			<input type="text" name="price_new_old" size="10" id="price_new_old" readonly="readonly" value="' . (isset($model_def['price_new']) ? $model_def['price_new'] : '') . '" />
		</td>
	</tr>

	<tr>
		<td>Прогноз срока службы (непрервыное использование):</td>
		<td>
			<input type="text" name="lom_srok_old" size="5" id="lom_srok_old" readonly="readonly" value="' . $model_def['lom_srok'] . '" /> года (лет).
		</td>
	</tr>
	<tr>
		<td>Пол:</td>
		<td>
			<select name="m_sex" id="m_sex" disabled="disabled">
				<option value="0" ' . sel_d($model_def['m_sex'], '0') . '>на товаре</option>
				<option value="u" ' . sel_d($model_def['m_sex'], 'u') . '>унисекс</option>
				<option value="m" ' . sel_d($model_def['m_sex'], 'm') . '>для мальчиков</option>
				<option value="f" ' . sel_d($model_def['m_sex'], 'f') . '>для девочек</option>
			</select>
		</td>
	</tr>
	<tr>
		<td>Возраст от:</td>
		<td>
			<input type="text" name="age_from" size="5" id="age_from_old" readonly="readonly" value="' . $model_def['age_from'] . '" /> мес.
		</td>
	</tr>

	<tr>
		<td>Возраст до:</td>
		<td>
			<input type="text" name="age_to" size="5" id="age_to_old" readonly="readonly" value="' . $model_def['age_to'] . '" /> мес.
		</td>
	</tr>

	<tr>
		<td>Вес от:</td>
		<td>
			<input type="text" name="weight_from" size="5" id="weight_from_old" readonly="readonly" value="' . $model_def['weight_from'] . '" /> кг.
		</td>
	</tr>

	<tr>
		<td>Вес до:</td>
		<td>
			<input type="text" name="weight_to" size="5" id="weight_to_old" readonly="readonly" value="' . $model_def['weight_to'] . '" /> кг.
		</td>
	</tr>

	<tr>
	<td>Для карнавала:</td>
		<td>
			Залог: <input type="number" step="any" min="0" name="collateral" style="width:70px;" readonly="readonly" value="' . $model_def['collateral'] . '" /> руб.;
			Новый год:
			<select name="ny" style="width:50px;" disabled="disabled">
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['ny'], '1') . '>да</option>
			</select>;

			Зверь:
			<select name="zv" style="width:50px;" disabled="disabled">
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['zv'], '1') . '>да</option>
			</select>;

			Сказка:
			<select name="tale" style="width:50px;" disabled="disabled">
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['tale'], '1') . '>да</option>
			</select>;

		<span style="display:none;">
			Резерв1:
			<select name="rez1" style="width:50px;" disabled="disabled">
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['rez1'], '1') . '>да</option>
			</select>;

			Резерв2:
			<select name="rez2" style="width:50px;" disabled="disabled">
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['rez2'], '1') . '>да</option>
			</select>;

		</span>
		</td>
	</tr>

	<tr style="display:none;">
		<td>Инфо для сайта</td>
		<td>Адрес страницы товара:<input type="text" name="model_addr_old" size="70" id="model_addr_old" value="" readonly="readonly" /><br />
			Адрес фото:<input type="text" name="ph_addr_old" size="70" id="ph_addr_old" value="" readonly="readonly" />
				</td>
	</tr>


</table>
ID модели: <input type="text" name="model_id" id="model_id" value="' . $model_id . '" readonly="readonly" />
</div>





<br />
Инвентарный номер товара: <span id="inv_n_cat"></span><input type="text" size="10" name="inv_n" readonly="readonly" value="' . $item_def['item_inv_n'] . '" /><br />


Цвет (для варианта "multicolor"): <input type="text" name="item_color" id="item_color" size="65" value="' . good_print($item_def['item_color']) . '" /><br />
Пол:<select name="item_sex" id="item_sex">
	<!--<option value="0" ' . sel_d($item_def['sex'], '0') . '>не определено</option> -->
		<option value="u" ' . sel_d($item_def['sex'], 'u') . '>унисекс</option>
		<option value="m" ' . sel_d($item_def['sex'], 'm') . '>для мальчиков</option>
		<option value="f" ' . sel_d($item_def['sex'], 'f') . '>для девочек</option>
	</select>

Для одежды - размер: <input type="text" name="tovar_size" id="tovar_size" size="10" value="' . good_print($item_def['item_size']) . '" />,
рост: от <input type="number" step="any" min="0" name="tovar_rost1" id="tovar_rost1" size="5" value="' . good_print($item_def['item_rost1']) . '" /> до <input type="number" step="any" min="0" name="tovar_rost2" id="tovar_rost2" size="5" value="' . good_print($item_def['item_rost2']) . '" /><br />
реальный размер для карнавальных костюмов:<input type="text" name="real_tovar_size" id="real_tovar_size" size="10" value="' . good_print($item_def['real_item_size']) . '" />,<br />
Фактическая комплектация товара: <input type="text" name="item_set" id="item_set" size="65" value="' . good_print($item_def['item_set']) . '" /> <input type="button" value="копировать стандарт" id="copy" onclick="copy_set(); return false;" /><br />

Дата приобретения:<input type="date" name="buy_date" id="buy_date" size="10" value="' . date("Y-m-d", $item_def['buy_date']) . '" /><br />
Цена приобретения:<input type="number" step="any" min="0" name="buy_price" id="buy_price" size="10" value="' . $item_def['buy_price'] . '" />
Валюта приобретения:
<select name="buy_currency" id="buy_price_cur">
	<option value="USD" ' . sel_d($item_def['buy_price_cur'], 'USD') . '>доллары США</option>
	<option value="TBYR" ' . sel_d($item_def['buy_price_cur'], 'TBYR') . '>бел.руб.</option>
    <option value="EUR" ' . sel_d($item_def['buy_price_cur'], 'EUR') . '>евро</option>
   	<option value="RUB" ' . sel_d($item_def['buy_price_cur'], 'RUB') . '>росс.руб.</option>
</select>

Курс пересчета в USD:<input type="number" name="exchange_rate" min="0.00001" step="0.00001" id="exch_rate" value="' . $item_def['exch_to_byr'] . '" /><br />
Продавец:<input type="text" name="seller" size="70" id="seller" value="' . good_print($item_def['seller']) . '" /><br />

Статус товара:	<select name="tovar_status" id="tovar_status">
			  		<option value="to_rent" ' . sel_d($item_def['status'], 'to_rent') . '>доступен для сдачи</option>
					<option value="bron" ' . sel_d($item_def['status'], 'bron') . '>бронь</option>
					<option value="t_bron" ' . sel_d($item_def['status'], 't_bron') . '>временная бронь (интернет)</option>
					<option value="to_deliver" ' . sel_d($item_def['status'], 'to_deliver') . '>на доставку</option>
					<option value="repair" ' . sel_d($item_def['status'], 'repair') . '>требуется ремонт</option>
					<option value="not_to_rent" ' . sel_d($item_def['status'], 'not_to_rent') . '>недоступен для сдачи</option>
					<option value="rented_out" ' . sel_d($item_def['status'], 'rented_out') . '>товар сдан/на руках</option>
				</select>
<br />

Местонахождение товара:
	    	 	<select name="tovar_place" id="tovar_place">
	    	 	    <option value="0">Выберите офис</option>';
if ($tovar && $tovar->isVPuti()) {
	echo '<option value="' . $tovar->item_place . '" selected>Товар в пути: ' . $tovar->item_place . '-->' . $tovar->to_move . '</option>';
} else {
	$ofs = Office::getAllActiveOffices();
	//Base::varDamp($ofs);
	foreach ($ofs as $of) {
		echo '<option value="' . $of->number . '" ' . Base::sel_d($item_def['item_place'], $of->number) . '>' . $of->getShortName() . '</option>';
	}
}
echo '		</select>
	<br />

Состояние товара:';
if ($item_def['state'] == 3) {
	echo '
                <select name="tovar_state" id="tovar_state" >
					<option value="3" ' . sel_d($item_def['state'], '3') . '>Последний прокат</option>
				</select>
                ';
} else {
	echo '
                <select name="tovar_state" id="tovar_state" >
	    	 		<option value="0" ' . sel_d($item_def['state'], '0') . '>Новый</option>
			  		<option value="1" ' . sel_d($item_def['state'], '1') . '>Хорошее состояние</option>
					<option value="2" ' . sel_d($item_def['state'], '2') . '>Нормальное состояние</option>
					<option value="4" ' . sel_d($item_def['state'], '4') . '>Стыдное состояние</option>
					<option value="-1" ' . sel_d($item_def['state'], '-1') . '>Фейк (не настоящий)</option>
				</select>
                ';
}

echo '

	<br />


Дополнительная информация о товаре:<br />
<textarea name="info" rows="4" cols="70" id="item_info">' . good_print($item_def['item_info']) . '</textarea><br />
' . ($action == 'редактировать' ? '<input type="submit" name="action" value="обновить" onclick="return send_form_ch();"/>' : '<input type="submit" name="action" value="сохранить" onclick="return send_form_ch();"/>') . '
</form>

';




echo '</body>';






function get_post($var)
{
	global $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}


function good_print($var)
{
	$var = htmlspecialchars(stripslashes($var));
	return $var;
}


function sel_d($value, $pattern)
{
	if ($value == $pattern) {
		return 'selected="selected"';
	} else {
		return '';
	}
}

?>

<script>
	document.querySelector('#buy_price_cur').addEventListener('change', onCurChange);
	let targ = document.querySelector('#exch_rate');
	let dateInput = document.querySelector('#buy_date');

	function onCurChange(e) {
		let date = dateInput.value;
		let choice = e.target.value;

		if (choice == 'TBYR') cur = 'BYN';
		else cur = choice;

		var xmlhttp = getXmlHttp()
		xmlhttp.open("POST", '/bb/cat_ch_new.php', true)
		xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		console.log(date)
		var params = 'date=' + encodeURIComponent(date) + '&par2=cur_change' + '&cur=' + encodeURIComponent(cur);

		xmlhttp.send(params);
		xmlhttp.onreadystatechange = function () {
			if (xmlhttp.readyState == 4) {
				if (xmlhttp.status == 200) {
					targ.value = (xmlhttp.responseText);
				}
			}
		}
	}

</script>