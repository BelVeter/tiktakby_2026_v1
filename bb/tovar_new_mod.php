<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');

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
<title>Товары.</title>
<body>


<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> ' . $_SESSION['user_fio'] . '</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/kr_baza_new.php">Просмотр всех товаров</a>
</div>


		';
//require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам

//Проверка входящей информации
//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//foreach ($_POST as $key => $value) {
//	echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$model_id = '';
$cat_select_new = '';
$model_double_text = '';


if (isset($_POST['action'])) {

	foreach ($_POST as $key => $value) {
		$$key = get_post($key);
	}

	switch ($action) {

		case 'сохранить':

			//получаем id категории (если нужно - создаем ее)
			if ($cat_select_new != '0') {
				$cat_id = $cat_select_new;
			} else {//проверяем, есть ли такое наименование в категории, если есть - то берем id этой категории и не создаем новую, если нет - создаем новую категорию

				$mysqli = \bb\Db::getInstance()->getConnection();

				$query_cat_ch = "SELECT * FROM tovar_rent_cat WHERE rent_cat_name='$cat_input_new'";
				$result_cat_ch = $mysqli->query($query_cat_ch);
				if (!$result_cat_ch) {
					die('Сбой при доступе к базе данных: ' . $query_cat_ch . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
				$cat_num = $result_cat_ch->num_rows;

				if ($cat_num > 0) {
					$cat_ch = $result_cat_ch->fetch_assoc();
					$cat_id = $cat_ch['tovar_rent_cat_id'];
				} else {
					$mysqli = \bb\Db::getInstance()->getConnection();

					$query_newcat = "INSERT INTO tovar_rent_cat VALUES('', '$cat_input_new', '$cat_input_dog_new')";
					$result_newcat = $mysqli->query($query_newcat);
					if (!$result_newcat) {
						die('Сбой при доступе к базе данных: ' . $query_newcat . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					}

					$cat_id = $mysqli->insert_id;
				}
			}

			//определяем наименование производителя
			if ($producer_select_new != '0') {
				$producer_name = $producer_select_new;
			} else {
				$producer_name = $producer_input_new;
			}

			//определяем наименование модели
			if ($model_select_new != '0') {
				$model_name = $model_select_new;
			} else {
				$model_name = $model_input_new;
			}


			// проверяем наличие аналогичной модели, если имеется таковая, то просто используем ее id, иначе - создаем новую модель
			$mysqli = \bb\Db::getInstance()->getConnection();

			$query_mod = "SELECT * FROM tovar_rent WHERE model='$model_name' AND tovar_rent_cat_id='$cat_id' AND producer='$producer_name' AND color='$color_new'";
			$result_mod = $mysqli->query($query_mod);
			if (!$result_mod) {
				die('Сбой при доступе к базе данных: ' . $query_mod . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$mod_num = $result_mod->num_rows;

			if ($mod_num > 0) {
				$mod_ch = $result_mod->fetch_assoc();
				$model_id = $mod_ch['tovar_rent_id'];
				$model_double_text = 'Внимание!!! Новая модель не создана, т.к. категория, название модели, производителя и цвет - дублируют действующую модель. <br />Использована действующая модель.';
			} else {

				//создаем модель на основании полученных данных и получаем ее id:
				$mysqli = \bb\Db::getInstance()->getConnection();

				$query_new_model = "INSERT INTO tovar_rent VALUES('', '$cat_id', '$producer_name', '$model_name', '$m_set_new', '$color_new', '$m_price_new', '$m_price_cur_new', '$lom_srok_new', '" . time() . "', '" . $_SESSION['user_fio'] . "', '$model_addr_new', '$ph_addr_new', '$age_from', '$age_to', '$weight_from', '$weight_to', '$ny', '$zv', '$tale', '$rez1', '$rez2', '$collateral', '$m_sex', '$price_new_new')";
				$result_new_model = $mysqli->query($query_new_model);
				if (!$result_new_model) {
					die('Сбой при доступе к базе данных: ' . $query_new_model . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}

				$model_id = $mysqli->insert_id;
				//echo 'model_id='.$model_id.'<br />';
			}


			echo 'Модель успешно заведена. <br /> ID модели:' . $model_id . '<br />' . $model_double_text;



			die('
			</head>
			<form method="post" id="tovar_tarif" action="rent_tarifs.php">
					<input type="hidden" name="model_id" value="' . $model_id . '" />
					<input type="submit" name="action" value="редактировать тарифы (эта модель)" />
				</form>

				<form method="post" id="tovar_tarif" action="kr_baza_new.php">
					<input type="hidden" name="cat_id" value="' . $cat_id . '" />
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

		case 'редактировать':
			//предполагается входящий model_id
			$mysqli = \bb\Db::getInstance()->getConnection();

			$query_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$model_id'";
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

			break;


		case 'обновить':
			$model_start_id = $model_id;
			//получаем id категории (если нужно - создаем ее)
			if ($cat_select_new > 0) {
				$cat_id = $cat_select_new;
			} //выбрана конкретная категория без изменений категории
			else {

				if ($cat_edit_status == 'yes') {// выбрана категория и она меняется

					$mysqli = \bb\Db::getInstance()->getConnection();

					$query_upd = "UPDATE tovar_rent_cat SET rent_cat_name='$cat_input_new', dog_name='$cat_input_dog_new' WHERE tovar_rent_cat_id='$cat_edit_id'";
					$result_upd = $mysqli->query($query_upd);
					if (!$result_upd) {
						die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					}

					$cat_id = $cat_edit_id;

				} else {//категория не выбрана - создаем новую
					//проверяем, есть ли такое наименование в категории, если есть - то берем id этой категории и не создаем новую, если нет - создаем новую категорию
					$mysqli = \bb\Db::getInstance()->getConnection();

					$query_cat_ch = "SELECT * FROM tovar_rent_cat WHERE rent_cat_name='$cat_input_new'";
					$result_cat_ch = $mysqli->query($query_cat_ch);
					if (!$result_cat_ch) {
						die('Сбой при доступе к базе данных: ' . $query_cat_ch . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					}
					$cat_num = $result_cat_ch->num_rows;

					if ($cat_num > 0) {
						$cat_ch = $result_cat_ch->fetch_assoc();
						$cat_id = $cat_ch['tovar_rent_cat_id'];
					} else {
						$mysqli = \bb\Db::getInstance()->getConnection();

						$query_newcat = "INSERT INTO tovar_rent_cat VALUES('', '$cat_input_new', '$cat_input_dog_new')";
						$result_newcat = $mysqli->query($query_newcat);
						if (!$result_newcat) {
							die('Сбой при доступе к базе данных: ' . $query_newcat . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
						}

						$cat_id = $mysqli->insert_id;
					}
				}
			}
			//здесь мы получаем итоговый id категории

			//далее, апдейтим модель
			$producer_select_new == '0' ? $producer_name = $producer_input_new : $producer_name = $producer_select_new;
			$model_select_new == '0' ? $model_name = $model_input_new : $model_name = $model_select_new;


			// проверяем наличие аналогичной модели, если имеется таковая, то просто используем ее id, иначе - создаем новую модель
			$mysqli = \bb\Db::getInstance()->getConnection();

			$query_mod = "SELECT * FROM tovar_rent WHERE model='$model_name' AND tovar_rent_cat_id='$cat_id' AND producer='$producer_name' AND color='$color_new' AND tovar_rent_id!='$model_start_id'";
			$result_mod = $mysqli->query($query_mod);
			if (!$result_mod) {
				die('Сбой при доступе к базе данных: ' . $query_mod . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			$mod_num = $result_mod->num_rows;

			if ($mod_num > 0) {
				$model_double_text = 'Внимание!!! Вы задублировали существующую модель, т.к. категория, название модели, производителя и цвет - дублируют действующую модель. <br />Внесите изменения.';
			}

			$mysqli = \bb\Db::getInstance()->getConnection();

			$query_upd = "UPDATE tovar_rent SET tovar_rent_cat_id='$cat_id', producer='$producer_name', model='$model_name', color='$color_new', `set`='$m_set_new',  agr_price='$m_price_new', agr_price_cur='$m_price_cur_new', lom_srok='$lom_srok_new', model_addr='$model_addr_new', ph_addr='$ph_addr_new', cr_ch_date='" . time() . "', user='" . $_SESSION['user_fio'] . "', age_from='$age_from', age_to='$age_to', weight_from='$weight_from', weight_to='$weight_to', ny='$ny', zv='$zv', tale='$tale', rez1='$rez1', rez2='$rez2', collateral='$collateral', m_sex='$m_sex', price_new='$price_new_new' WHERE tovar_rent_id='$model_id'";
			$result_upd = $mysqli->query($query_upd);
			if (!$result_upd) {
				die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			//				//не забываем обновить информацию по web моделям
//				$query_upd2 = "UPDATE rent_model_web SET cat_id='$cat_id' WHERE model_id='$model_id'";
//				$result_upd2 = $mysqli->query($query_upd2);
//				if (!$result_upd2) {die('Сбой при доступе к базе данных: '.$query_upd2.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


			//обновляем информацию по изменившимся моделям и категориям по таблице товаров
			$query_items_upd = "UPDATE tovar_rent_items SET cat_id='$cat_id', model_id='$model_id' WHERE model_id='$model_start_id'";
			$result_items_upd = $mysqli->query($query_items_upd);
			if (!$result_items_upd) {
				die('Сбой при доступе к базе данных: ' . $query_items_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			$query_items_upd = "UPDATE tovar_rent_items_arch SET cat_id='$cat_id', model_id='$model_id' WHERE model_id='$model_start_id'";
			$result_items_upd = $mysqli->query($query_items_upd);
			if (!$result_items_upd) {
				die('Сбой при доступе к базе данных: ' . $query_items_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			die('
			</head>
			<body>
				Модель успешно обновлена. <br />' . $model_double_text . '
				<form method="post" id="tovar_tarif" action="rent_tarifs.php">
					<input type="hidden" name="model_id" value="' . $model_id . '" />
					<input type="submit" name="action" value="редактировать тарифы (эта модель)" />
				</form>

				<form method="post" id="tovar_tarif" action="kr_baza_new.php">
					<input type="hidden" name="cat_id" value="' . $cat_id . '" />
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

	}//end of switch
}// end of main action if



?>

<script language="javascript">


	function cat_edit() {

		if (document.getElementById('cat_edit_but').value == 'редактировать категорию') {
			document.getElementById('cat_input_new').disabled = false;
			document.getElementById('cat_input_dog_new').readOnly = false;

			document.getElementById('cat_edit_id').value = document.getElementById('cat_select_new').value;

			c2 = document.getElementById('cat_select_new');
			c2 = c2.options[c2.selectedIndex].text;

			document.getElementById('cat_input_new').value = c2;
			document.getElementById('cat_select_new').disabled = true;

			document.getElementById('cat_edit_status').value = 'yes';

			document.getElementById('cat_edit_but').value = 'отменить редактирование категории';
		}
		else {
			document.getElementById('cat_select_new').disabled = false;
			document.getElementById('cat_edit_but').value = 'редактировать категорию';
			document.getElementById('cat_edit_status').value = 'no';
			document.getElementById('cat_edit_id').value = '';
			select_ch3('cat_select_new', 'cat_input_new');
		}

	}


	function select_ch3(sel, new_f) {

		if (document.getElementById(sel).value == 0) {
			document.getElementById(new_f).disabled = false;
			document.getElementById('cat_input_dog_new').readOnly = false;
			document.getElementById('cat_input_dog_new').value = '';
		}
		else {
			document.getElementById(new_f).disabled = true;
			document.getElementById('cat_input_dog_new').readOnly = true;
			document.getElementById(new_f).value = '';

			document.getElementById('cat_input_dog_new').value = '... ждите ...';

			var cat_id = document.getElementById(sel).value;
			par2 = 'dog_name_select';

			var xmlhttp = getXmlHttp()
			xmlhttp.open("POST", '/bb/cat_ch.php', true)
			xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

			var params = 'cat_id=' + encodeURIComponent(cat_id) + '&par2=' + encodeURIComponent(par2);

			xmlhttp.send(params);
			xmlhttp.onreadystatechange = function () {
				if (xmlhttp.readyState == 4) {
					if (xmlhttp.status == 200) {
						document.getElementById('cat_input_dog_new').value = xmlhttp.responseText;
					}
				}
			}
		}//end of else

	}//end of select_ch3


	function select_ch2(sel, new_f) {

		if (document.getElementById(sel).value == 0) {
			document.getElementById(new_f).disabled = false;
		}
		else {
			document.getElementById(new_f).disabled = true;
			document.getElementById(new_f).value = '';
		}

	}



	function send_form_ch() {

		cat_chcc = cat_dogcc = prod_chcc = model_chcc = color_chcc = set_chcc = price_chcc = price_cur_chcc = lom_srokcc = buy_date_chcc = buy_price_chcc = buy_price_cur_chcc = exch_rate_chcc = seller_chcc = item_set_chcc = item_color_chcc = age_from = age_to = weight_from = weight_to = '';

		valid = true;

		if (document.getElementById('age_from_new').value * 1 > document.getElementById('age_to_new').value * 1) {
			alert('Возраст должен быть заполнен в двух полях (ОТ и ДО). При этом, ОТ должно быть меньше либо равно ДО');
			valid = false;
		}

		if (document.getElementById('weight_from_new').value * 1 > document.getElementById('weight_to_new').value * 1) {
			alert('Вес должен быть заполнен в двух полях (ОТ и ДО). При этом, ОТ должно быть меньше либо равно ДО');
			valid = false;
		}


		if (document.getElementById('cat_select_new').value == "0" && document.getElementById('cat_input_new').value == "") {
			cat_chcc = "Категория товара, ";
			valid = false;
		}

		if (document.getElementById('cat_input_dog_new').value == "") {
			cat_dogcc = "Категория товара для договора, ";
			valid = false;
		}

		if (document.getElementById('producer_select_new').value == "0" && document.getElementById('producer_input_new').value == "") {
			prod_chcc = "фирма, ";
			valid = false;
		}

		if (document.getElementById('model_select_new').value == "0" && document.getElementById('model_input_new').value == "") {
			model_chcc = "Модель, ";
			valid = false;
		}

		if (document.getElementById('color_new').value == "") {
			color_chcc = "Цвет, ";
			valid = false;
		}

		if (document.getElementById('m_set_new').value == "") {
			set_chcc = "Комплектация, ";
			valid = false;
		}

		if (document.getElementById('m_price_new').value == "") {
			price_chcc = "Оценочная стоимость, ";
			valid = false;
		}

		if (document.getElementById('m_price_cur_new').value == "") {
			price_cur_chcc = "Валюта оценочной стоимости, ";
			valid = false;
		}

		if (document.getElementById('lom_srok_new').value == "") {
			lom_srokcc = "Срок службы, ";
			valid = false;
		}


		if (document.getElementById('age_from_new').value == "") {
			age_from = "Возраст от, ";
			valid = false;
		}

		if (document.getElementById('age_to_new').value == "") {
			age_to = "Возраст до, ";
			valid = false;
		}

		if (document.getElementById('weight_from_new').value == "") {
			weight_from = "Вес от, ";
			valid = false;
		}

		if (document.getElementById('weight_to_new').value == "") {
			weight_to = "Вес до, ";
			valid = false;
		}

		if (valid == false) {
			alert('Заполните все поля формы! В частности: ' + cat_chcc + cat_dogcc + prod_chcc + model_chcc + color_chcc + set_chcc + price_chcc + price_cur_chcc + lom_srokcc + item_color_chcc + item_set_chcc + buy_date_chcc + buy_price_chcc + buy_price_cur_chcc + exch_rate_chcc + seller_chcc + age_from + age_to + weight_from + weight_to);
		}

		return valid;

	}//end of send_form_ch function






</script>

<?php

$cat_list = '';



//выбираем категории
$mysqli = \bb\Db::getInstance()->getConnection();

$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
$result_cats = $mysqli->query($query_cats);
if (!$result_cats) {
	die('Сбой при доступе к базе данных: ' . $query_cats . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

//chose tovar producers
$query_prod = "SELECT DISTINCT producer FROM tovar_rent ORDER BY producer";
$result_prod = $mysqli->query($query_prod);
if (!$result_prod) {
	die('Сбой при доступе к базе данных: ' . $query_prod . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}


//chose model list
$query_model = "SELECT DISTINCT model FROM tovar_rent ORDER BY model";
$result_model = $mysqli->query($query_model);
if (!$result_model) {
	die('Сбой при доступе к базе данных: ' . $query_model . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}


echo '
<form name="tovar" action="tovar_new_mod.php" method="post">
<div id="new_model_div" class="new_div_r">
<strong>ID модели: ' . $model_id . '<br /></strong>
<table border="1" cellspacing="0">
	<tr>
		<td>Категория товара:</td>
		<td>
			<select name="cat_select_new" id="cat_select_new" onchange="select_ch3(\'cat_select_new\', \'cat_input_new\');" style="width:220px;" >
				<option value="0">ввести новую категорию</option>';
while ($cat_names = $result_cats->fetch_assoc()) {
	echo '<option value="' . $cat_names['tovar_rent_cat_id'] . '" ' . sel_d($cat_names['tovar_rent_cat_id'], $cat_id) . ' >' . good_print($cat_names['rent_cat_name']) . '</option>';
}

echo '
			</select>

			<input type="text" name="cat_input_new" size="30" id="cat_input_new" ' . ($action == 'редактировать' ? 'disabled="disabled"' : '') . ' />

				и для договора (ед.ч.):
			<input type="text" name="cat_input_dog_new" size="30" id="cat_input_dog_new" value="' . good_print($cat_def['dog_name']) . '"/> <br />
			' . ($action == 'редактировать' ? '<input type="button" value="редактировать категорию" id="cat_edit_but" onclick="cat_edit();" />' : '') . '
			<input type="hidden" name="cat_edit_status" id="cat_edit_status" value="no" />
			<input type="hidden" name="cat_edit_id" id="cat_edit_id" value="" />
			<input type="hidden" name="model_id" value="' . $model_id . '" />

		</td>
	</tr>
	<tr>
		<td>Альтернативное название категории для печати в договоре (если стандарт - оставляем пустое поле):</td>
		<td><input type="text" name="model_addr_new" size="70" id="model_addr_new" value="' . good_print($model_def['model_addr']) . '" /><br />
			<span style="display:none;">Адрес фото:<input type="text" name="ph_addr_new" size="70" id="ph_addr_new" value="' . good_print($model_def['ph_addr']) . '" /></span>
			</td>
	</tr>
	<tr>
		<td>Фирма:</td>
		<td>
			<select name="producer_select_new" id="producer_select_new" onchange="select_ch2(\'producer_select_new\', \'producer_input_new\');" style="width:220px;" >
			    	<option value="0">ввести нового производителя</option>';
while ($prod_names = $result_prod->fetch_assoc()) {
	echo '
					<option value="' . good_print($prod_names['producer']) . '" ' . sel_d($model_def['producer'], $prod_names['producer']) . '>' . good_print($prod_names['producer']) . '</option>
					';
}
echo '</select>
			<input type="text" name="producer_input_new" size="30" id="producer_input_new" ' . ($action == 'редактировать' ? 'disabled="disabled"' : '') . ' />
		</td>
	</tr>

	<tr>
		<td>Модель:</td>
		<td>
			<select name="model_select_new" id="model_select_new" onchange="select_ch2(\'model_select_new\', \'model_input_new\');" style="width:220px;" >
	    		<option value="0">ввести новую модель</option>';

while ($model_list = $result_model->fetch_assoc()) {
	echo '<option value="' . good_print($model_list['model']) . '" ' . sel_d($model_def['model'], $model_list['model']) . '>' . good_print($model_list['model']) . '</option>';
}

echo '
	    	</select>
	    	<input type="text" name="model_input_new" size="30" id="model_input_new" ' . ($action == 'редактировать' ? 'disabled="disabled"' : '') . ' />
		</td>
	</tr>

	<tr>
		<td>Цвет:</td>
		<td> <input type="text" name="color_new" size="30" id="color_new" value="' . good_print($model_def['color']) . '" /> нет цвета - ставим "0", <input type="button" value="multicolor" onclick="document.getElementById(\'color_new\').value=\'multicolor\'" /></td>
	</tr>

	<tr>
		<td>Комплектация модели (стандарт):</td>
		<td><input type="text" name="m_set_new" size="70" id="m_set_new" value="' . good_print($model_def['set']) . '" /></td>
	</tr>

	<tr>
		<td>Оценочная стоимость:</td>
		<td>
			<input type="number" step="any" min="0" name="m_price_new" size="70" id="m_price_new" value="' . $model_def['agr_price'] . '" />
			<select name="m_price_cur_new" id="m_price_cur_new">
		    	<option value="USD" ' . sel_d($model_def['agr_price_cur'], 'USD') . ' >USD</option>
		 	  	<option value="EUR" ' . sel_d($model_def['agr_price_cur'], 'EUR') . ' >EUR</option>
		    	<option value="TBYR" ' . sel_d($model_def['agr_price_cur'], 'TBYR') . ' >бел.руб.</option>
		    </select>
		</td>
	</tr>
    <tr>
        <td>Цена нового:</td>
        <td>
            <input type="number" step="1" min="0" name="price_new_new" size="70" id="price_new_new" value="' . (isset($model_def['price_new']) ? $model_def['price_new'] : '') . '" /> бел. руб.
        </td>
    </tr>

	<tr>
		<td>Прогноз срока службы (непрервыное использование):</td>
		<td>
			<input type="number" step="any" min="0" name="lom_srok_new" size="5" id="lom_srok_new" value="' . $model_def['lom_srok'] . '" /> года (лет).
		</td>
	</tr>
	<tr>
		<td>Пол:</td>
		<td>
			<select name="m_sex" id="m_sex">
				<option value="0" ' . sel_d($model_def['m_sex'], '0') . '>на товаре</option>
				<option value="u" ' . sel_d($model_def['m_sex'], 'u') . '>унисекс</option>
				<option value="m" ' . sel_d($model_def['m_sex'], 'm') . '>для мальчиков</option>
				<option value="f" ' . sel_d($model_def['m_sex'], 'f') . '>для девочек</option>
			</select>
		</td>
	</tr>
	<tr>
		<td>возраст от:</td>
		<td>
			<input type="number" step="any" min="0" name="age_from" size="5" id="age_from_new" value="' . $model_def['age_from'] . '" /> месяцев.
		</td>
	</tr>
	<tr>
		<td>возраст до (включительно):</td>
		<td>
			<input type="number" step="any" min="0" name="age_to" size="5" id="age_to_new" value="' . $model_def['age_to'] . '" /> месяцев.
		</td>
	</tr>
	<td>вес от:</td>
		<td>
			<input type="number" step="any" min="0" name="weight_from" size="5" id="weight_from_new" value="' . $model_def['weight_from'] . '" /> кг.
		</td>
	</tr>
	<tr>
	<td>вес до (включительно):</td>
		<td>
			<input type="number" step="any" min="0" name="weight_to" size="5" id="weight_to_new" value="' . $model_def['weight_to'] . '" /> кг.
		</td>
	</tr>
	<tr>
	<td>Для карнавала:</td>
		<td>
			Залог: <input type="number" step="any" min="0" name="collateral" style="width:70px;"  value="' . $model_def['collateral'] . '" /> руб.;
			Новый год:
			<select name="ny" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['ny'], '1') . '>да</option>
			</select>;

			Зверь:
			<select name="zv" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['zv'], '1') . '>да</option>
			</select>;

			Сказка:
			<select name="tale" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['tale'], '1') . '>да</option>
			</select>;

		<span style="display:none;">
			Резерв1:
			<select name="rez1" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['rez1'], '1') . '>да</option>
			</select>;

			Резерв2:
			<select name="rez2" style="width:50px;" >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['rez2'], '1') . '>да</option>
			</select>;

		</span>
		</td>
	</tr>
</table>




</div>

<br /><br />
' . ($action == 'редактировать' ? '<input type="submit" name="action" value="обновить" onclick="return send_form_ch();"/>' : '<input type="submit" name="action" value="сохранить" onclick="return send_form_ch();"/>') . '

</form>

				';




echo '</body>';






function get_post($var)
{
	global $mysqli;
	$mysqli = \bb\Db::getInstance()->getConnection();
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