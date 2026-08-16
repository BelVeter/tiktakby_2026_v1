<?php
session_start();
ini_set('display_errors', (isset($_SESSION['svoi']) && $_SESSION['svoi'] == 8941) ? 1 : 0);
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
<link href="/bb/assets/styles/category_picker.css?v=7" rel="stylesheet" type="text/css" />
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
$cat_id = 0;

// Заполняются только в режиме «редактировать»; в режиме новой модели поля
// должны быть пустыми, а не падать в notice при обращении к ключу.
$action = '';
$cat_def = array('rent_cat_name' => '', 'dog_name' => '');
$model_def = array_fill_keys(array(
	'age_from', 'age_to', 'agr_price', 'agr_price_cur', 'collateral', 'color',
	'lom_srok', 'model', 'model_addr', 'm_sex', 'ny', 'ph_addr', 'price_new',
	'producer', 'rez1', 'rez2', 'set', 'tale', 'tovar_rent_cat_id',
	'weight_from', 'weight_to', 'zv',
), '');


if (isset($_POST['action'])) {

	foreach ($_POST as $key => $value) {
		$$key = get_post($key);
	}

	switch ($action) {

		case 'сохранить':

			// Категория выбирается живым поиском, а новая заводится в модалке
			// через bb/ajax_category_create.php — сюда приходит только готовый id.
			// Раньше здесь стоял позиционный INSERT на 3 колонки из 9: завести
			// категорию с этой страницы было невозможно (docs/db_notes.md, п.1).
			$cat_id = (int) $cat_select_new;
			if ($cat_id < 1) {
				die('Категория не выбрана. Найдите её в поле «Категория товара» '
					. 'или создайте кнопкой «+ создать категорию».');
			}

			// Производитель выбирается живым поиском по справочнику; новый
			// заводится в модалке через bb/ajax_producer_create.php — сюда
			// приходит уже готовое имя (не '0'/пусто — форма это не пускает
			// дальше на клиенте, но подстрахуемся и на сервере).
			$producer_name = trim($producer_select_new);
			if ($producer_name === '' || $producer_name === '0') {
				die('Производитель не выбран. Найдите его в поле «Фирма» '
					. 'или создайте кнопкой «+ создать производителя».');
			}

			//определяем наименование модели — приходит из живого поиска (bb/assets/js/model_picker.js)
			$model_name = trim($model_new);
			if ($model_name === '' || $model_name === '0') {
				die('Модель не указана. Введите название в поле «Модель».');
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

			if ((int) $model_id < 1) {
				die('Модель для редактирования не выбрана. Найдите её в поле «Модель».');
			}

			// Защита от неполного POST (см. docs/superpowers/plans/2026-08-16-tovar-new-mod-edit-lock.md,
			// whole-branch review C1): в фазе locate все 19 полей предпросмотра —
			// disabled, браузер их не отправляет вовсе; случайный implicit submit
			// (Enter в живом поиске) должен быть невозможен уже на клиенте
			// (submit_btn.disabled), но эта проверка ловит его и здесь, если
			// клиентская защита когда-нибудь окажется обойдена.
			if (!isset($_POST['color_new'], $_POST['m_set_new'], $_POST['m_price_new'], $_POST['m_price_cur_new'], $_POST['price_new_new'], $_POST['lom_srok_new'], $_POST['model_addr_new'], $_POST['ph_addr_new'], $_POST['age_from'], $_POST['age_to'], $_POST['weight_from'], $_POST['weight_to'], $_POST['collateral'], $_POST['m_sex'], $_POST['ny'], $_POST['zv'], $_POST['tale'], $_POST['rez1'], $_POST['rez2'])) {
				die('Форма отправлена не полностью — перезагрузите страницу, найдите модель и нажмите «Внести изменения».');
			}

			// Как и в «сохранить»: id приходит из живого поиска либо из модалки.
			// Переименование категории отсюда убрано — оно правило только название
			// и `dog_name`, оставляя `cat_url_key` от старого имени. Полное
			// редактирование живёт в /bb/category_management.php.
			$cat_id = (int) $cat_select_new;
			if ($cat_id < 1) {
				die('Категория не выбрана. Найдите её в поле «Категория товара» '
					. 'или создайте кнопкой «+ создать категорию».');
			}

			//далее, апдейтим модель
			$producer_name = trim($producer_select_new);
			if ($producer_name === '' || $producer_name === '0') {
				die('Производитель не выбран. Найдите его в поле «Фирма» '
					. 'или создайте кнопкой «+ создать производителя».');
			}
			$model_name = trim($model_new);
			if ($model_name === '' || $model_name === '0') {
				die('Модель не указана. Введите название в поле «Модель».');
			}


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


	// cat_edit() и select_ch3() удалены вместе со старым списком категорий.
	// select_ch3() звал getXmlHttp(), которого в проекте нет ни в одном файле,
	// — поэтому поле «для договора (ед.ч.)» намертво зависало на «... ждите ...»
	// при любом выборе категории. Теперь `dog_name` приходит вместе с подсказкой
	// из bb/ajax_category_suggest.php, второй запрос не нужен.
	// Переименование категории живёт в /bb/category_management.php.





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


		// Категория теперь всегда приходит готовым id из живого поиска или модалки.
		if ((document.getElementById('cat_select_new').value * 1) < 1) {
			cat_chcc = "Категория товара, ";
			valid = false;
		}

		if (document.getElementById('cat_input_dog_new').value == "") {
			cat_dogcc = "Категория товара для договора, ";
			valid = false;
		}

		// Производитель теперь всегда приходит готовым именем из живого поиска или модалки.
		if (document.getElementById('producer_select_new').value == "") {
			prod_chcc = "фирма, ";
			valid = false;
		}

		if (document.getElementById('model_new').value.trim() == "") {
			model_chcc = "Модель, ";
			valid = false;
		}

		if (document.getElementById('submit_btn').value == "обновить" && (document.getElementById('model_id').value * 1) <= 0) {
			model_chcc = "Модель для редактирования (выберите из подсказки), ";
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



$mysqli = \bb\Db::getInstance()->getConnection();

// Список категорий больше не выгружается в разметку: их 115+, и выпадающий
// список приходилось листать глазами. Теперь живой поиск через
// bb/ajax_category_suggest.php.

// Подразделы нужны модалке создания категории: без привязки к подразделу
// категория не попадает в меню каталога (docs/db_notes.md).
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/SubRazdel.php');
$sub_razdels_all = \bb\classes\SubRazdel::getAll();
usort($sub_razdels_all, function ($a, $b) {
	return strcmp($a->getNameSubRazdelText(), $b->getNameSubRazdelText());
});

// Фаза «locate» вкладки «Редактировать»: поля — только предпросмотр найденной
// записи, править нельзя, пока не нажата «Внести изменения». model_picker.js
// делает то же самое динамически при живом поиске; здесь — чтобы не было
// мигания незаблокированных полей при заходе по прямой ссылке с готовым
// model_id (см. docs/superpowers/specs/2026-08-16-tovar-new-mod-edit-lock-design.md).
$locate_disabled = ($action === 'редактировать') ? ' disabled="disabled"' : '';
$locate_hidden    = ($action === 'редактировать') ? ' style="display:none;"' : '';

$initial_model_json = 'null';
if ($action === 'редактировать') {
    $initial_model_json = json_encode(array_merge($model_def, array(
        'id'           => (int) $model_id,
        'cat_id'       => (int) $cat_id,
        'cat_name'     => $cat_def['rent_cat_name'],
        'cat_dog_name' => $cat_def['dog_name'],
        'name'         => $model_def['model'],
    )), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: 'null';
}

echo '
<form name="tovar" action="tovar_new_mod.php" method="post">
<div id="new_model_div" class="new_div_r ' . ($action === 'редактировать' ? 'catp-phase--locate' : 'catp-phase--new') . '">
<div id="ajax_spinner" class="catp-spinner" style="display:none;" aria-hidden="true"></div>
<strong>ID модели: ' . $model_id . '<br /></strong>

<div class="catp-tabs">
	<button type="button" id="tab_new" class="catp-tab">Новая модель</button>
	<button type="button" id="tab_edit" class="catp-tab">Редактировать действующую</button>
</div>

<div id="edit_phase_banner" class="catp-phase-banner" style="display:none;"></div>

<table border="1" cellspacing="0">
	<tr>
		<td>Категория товара:</td>
		<td>
			<div class="catp">
				<input type="text" id="cat_search" class="catp__input" autocomplete="off"
					placeholder="начните вводить название категории"
					value="' . good_print($cat_def['rent_cat_name']) . '" />
				<div id="cat_results" class="catp__results"></div>
				<div id="cat_chosen" class="catp__chosen"' . ($cat_id > 0 ? ' style="display:block;"' : '') . '>'
					. ($cat_id > 0 ? 'Выбрана категория #' . (int) $cat_id . ' — ' . good_print($cat_def['rent_cat_name']) : '') . '</div>
			</div>
			<input type="hidden" name="cat_select_new" id="cat_select_new" value="' . (int) $cat_id . '" />
			<input type="button" id="cat_create_open" value="+ создать категорию"' . $locate_hidden . ' />

			<br />и для договора (ед.ч.):
			<input type="text" name="cat_input_dog_new" size="30" id="cat_input_dog_new" readonly="readonly" value="' . good_print($cat_def['dog_name']) . '"/>
			<span class="catp-modal__hint">подставляется из категории; изменить — в <a href="/bb/category_management.php">справочнике категорий</a></span>
			<input type="hidden" name="model_id" id="model_id" value="' . $model_id . '" />

		</td>
	</tr>
	<tr>
		<td>Альтернативное название категории для печати в договоре (если стандарт - оставляем пустое поле):</td>
		<td><input type="text" name="model_addr_new" size="70" id="model_addr_new" value="' . good_print($model_def['model_addr']) . '"' . $locate_disabled . ' /><br />
			<span style="display:none;">Адрес фото:<input type="text" name="ph_addr_new" size="70" id="ph_addr_new" value="' . good_print($model_def['ph_addr']) . '"' . $locate_disabled . ' /></span>
			</td>
	</tr>
	<tr>
		<td>Фирма:</td>
		<td>
			<div class="catp">
				<input type="text" id="prod_search" class="catp__input" autocomplete="off"
					placeholder="начните вводить название производителя"
					value="' . good_print($model_def['producer']) . '" />
				<div id="prod_results" class="catp__results"></div>
				<div id="prod_chosen" class="catp__chosen"' . ($model_def['producer'] !== '' ? ' style="display:block;"' : '') . '>'
					. ($model_def['producer'] !== '' ? 'Выбрано: ' . good_print($model_def['producer']) : '') . '</div>
			</div>
			<input type="hidden" name="producer_select_new" id="producer_select_new" value="' . good_print($model_def['producer']) . '" />
			<input type="button" id="prod_create_open" value="+ создать производителя"' . $locate_hidden . ' />
			<input type="button" id="prod_edit_open" value="редактировать" style="display:none;" />
		</td>
	</tr>

	<tr>
		<td>Модель:</td>
		<td>
			<div class="catp">
				<input type="text" id="model_search" class="catp__input" autocomplete="off"
					placeholder="начните вводить название модели"
					value="' . good_print($model_def['model']) . '" />
				<div id="model_results" class="catp__results"></div>
				<div id="model_hint" class="catp__warn"></div>
			</div>
			<input type="hidden" name="model_new" id="model_new" value="' . good_print($model_def['model']) . '" />
		</td>
	</tr>

	<tr>
		<td>Цвет:</td>
		<td> <input type="text" name="color_new" size="30" id="color_new" value="' . good_print($model_def['color']) . '"' . $locate_disabled . ' /> нет цвета - ставим "0", <input type="button" id="color_multicolor_btn" value="multicolor" onclick="document.getElementById(\'color_new\').value=\'multicolor\';document.getElementById(\'color_new\').dispatchEvent(new Event(\'input\'))"' . $locate_disabled . ' /></td>
	</tr>

	<tr>
		<td>Комплектация модели (стандарт):</td>
		<td><input type="text" name="m_set_new" size="70" id="m_set_new" value="' . good_print($model_def['set']) . '"' . $locate_disabled . ' /></td>
	</tr>

	<tr>
		<td>Оценочная стоимость:</td>
		<td>
			<input type="number" step="any" min="0" name="m_price_new" size="70" id="m_price_new" value="' . $model_def['agr_price'] . '"' . $locate_disabled . ' />
			<select name="m_price_cur_new" id="m_price_cur_new"' . $locate_disabled . '>
		    	<option value="USD" ' . sel_d($model_def['agr_price_cur'], 'USD') . ' >USD</option>
		 	  	<option value="EUR" ' . sel_d($model_def['agr_price_cur'], 'EUR') . ' >EUR</option>
		    	<option value="TBYR" ' . sel_d($model_def['agr_price_cur'], 'TBYR') . ' >бел.руб.</option>
		    </select>
		</td>
	</tr>
    <tr>
        <td>Цена нового:</td>
        <td>
            <input type="number" step="1" min="0" name="price_new_new" size="70" id="price_new_new" value="' . (isset($model_def['price_new']) ? $model_def['price_new'] : '') . '"' . $locate_disabled . ' /> бел. руб.
        </td>
    </tr>

	<tr>
		<td>Прогноз срока службы (непрервыное использование):</td>
		<td>
			<input type="number" step="any" min="0" name="lom_srok_new" size="5" id="lom_srok_new" value="' . $model_def['lom_srok'] . '"' . $locate_disabled . ' /> года (лет).
		</td>
	</tr>
	<tr>
		<td>Пол:</td>
		<td>
			<select name="m_sex" id="m_sex"' . $locate_disabled . '>
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
			<input type="number" step="any" min="0" name="age_from" size="5" id="age_from_new" value="' . $model_def['age_from'] . '"' . $locate_disabled . ' /> месяцев.
		</td>
	</tr>
	<tr>
		<td>возраст до (включительно):</td>
		<td>
			<input type="number" step="any" min="0" name="age_to" size="5" id="age_to_new" value="' . $model_def['age_to'] . '"' . $locate_disabled . ' /> месяцев.
		</td>
	</tr>
	<td>вес от:</td>
		<td>
			<input type="number" step="any" min="0" name="weight_from" size="5" id="weight_from_new" value="' . $model_def['weight_from'] . '"' . $locate_disabled . ' /> кг.
		</td>
	</tr>
	<tr>
	<td>вес до (включительно):</td>
		<td>
			<input type="number" step="any" min="0" name="weight_to" size="5" id="weight_to_new" value="' . $model_def['weight_to'] . '"' . $locate_disabled . ' /> кг.
		</td>
	</tr>
	<tr>
	<td>Для карнавала:</td>
		<td>
			Залог: <input type="number" step="any" min="0" name="collateral" id="collateral_new" style="width:70px;"  value="' . $model_def['collateral'] . '"' . $locate_disabled . ' /> руб.;
			Новый год:
			<select name="ny" id="ny_new" style="width:50px;"' . $locate_disabled . ' >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['ny'], '1') . '>да</option>
			</select>;

			Зверь:
			<select name="zv" id="zv_new" style="width:50px;"' . $locate_disabled . ' >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['zv'], '1') . '>да</option>
			</select>;

			Сказка:
			<select name="tale" id="tale_new" style="width:50px;"' . $locate_disabled . ' >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['tale'], '1') . '>да</option>
			</select>;

		<span style="display:none;">
			Резерв1:
			<select name="rez1" id="rez1_new" style="width:50px;"' . $locate_disabled . ' >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['rez1'], '1') . '>да</option>
			</select>;

			Резерв2:
			<select name="rez2" id="rez2_new" style="width:50px;"' . $locate_disabled . ' >
			    <option value="0">нет</option>
				<option value="1" ' . sel_d($model_def['rez2'], '1') . '>да</option>
			</select>;

		</span>
		</td>
	</tr>
</table>




</div>

<br /><br />
<input type="button" id="model_reset_search" value="Начать поиск заново" style="display:none;" />
<input type="button" id="model_edit_start" value="Внести изменения" style="display:none;" />
<input type="button" id="model_edit_cancel" value="Отмена" style="display:none;" />
' . ($action == 'редактировать' ? '<input type="submit" name="action" id="submit_btn" value="обновить" onclick="return send_form_ch();"' . $locate_hidden . $locate_disabled . '/>' : '<input type="submit" name="action" id="submit_btn" value="сохранить" onclick="return send_form_ch();"/>') . '

</form>

				';


// Модалка создания категории. Поля повторяют /bb/category_management.php,
// чтобы категория рождалась полноценной: с адресом (`cat_url_key`) и с местом
// в дереве каталога (подраздел). Раньше со страницы модели можно было завести
// категорию только с названием — отсюда 57 категорий вне дерева, найденных
// аудитом 27.07.2026.
echo '
<div id="cat_modal" class="catp-modal">
	<div class="catp-modal__box">
		<h3>Новая категория</h3>

		<div id="newcat_warning" class="catp__warn"></div>

		<div class="catp-modal__row">
			<label>Название (ru) *</label>
			<input type="text" id="newcat_name" />
		</div>

		<div class="catp-modal__row">
			<label>Название для договора, ед. ч. * <span class="catp-modal__hint">печатается в договоре: «коляска», а не «коляски»</span></label>
			<input type="text" id="newcat_dog_name" />
		</div>

		<div class="catp-modal__row">
			<label>URL-ключ * <span class="catp-modal__hint">адрес категории на сайте; подставляется транслитом, можно поправить</span></label>
			<input type="text" id="newcat_url_key" />
		</div>

		<div class="catp-modal__row">
			<label>Подраздел * <span class="catp-modal__hint">без него категория не появится в меню каталога; кликните для списка или начните печатать</span></label>
			<div class="catp catp--full">
				<input type="text" id="newcat_sub_razdel_search" class="catp__input" autocomplete="off"
					placeholder="кликните для списка или начните печатать" />
				<div id="newcat_sub_razdel_results" class="catp__results"></div>
			</div>
			<input type="hidden" id="newcat_sub_razdel" value="0" />
		</div>

		<div class="catp-modal__row">
			<label>Тип</label>
			<select id="newcat_cat_type">
				<option value="0" selected="selected">стандарт</option>
				<option value="1">карнавал</option>
			</select>
		</div>

		<div class="catp-modal__row">
			<label>Сортировка</label>
			<input type="number" id="newcat_cat_sort" value="0" />
		</div>

		<div class="catp-modal__actions">
			<input type="button" id="newcat_cancel" value="отмена" />
			<input type="button" id="newcat_save" value="создать категорию" />
		</div>
	</div>
</div>

<div id="prod_create_modal" class="catp-modal">
	<div class="catp-modal__box">
		<h3>Новый производитель</h3>
		<div id="newprod_warning" class="catp__warn"></div>
		<div class="catp-modal__row">
			<label>Название</label>
			<input type="text" id="newprod_name" />
		</div>
		<div class="catp-modal__row">
			<label>Комментарий (необязательно)</label>
			<input type="text" id="newprod_comment" />
		</div>
		<div class="catp-modal__actions">
			<input type="button" id="newprod_cancel" value="отмена" />
			<input type="button" id="newprod_save" value="создать производителя" />
		</div>
	</div>
</div>

<div id="prod_edit_modal" class="catp-modal">
	<div class="catp-modal__box">
		<h3>Редактировать производителя</h3>
		<div id="editprod_warning" class="catp__warn"></div>
		<div class="catp-modal__row">
			<label>Название</label>
			<input type="text" id="editprod_name" />
		</div>
		<div class="catp-modal__row">
			<label>Комментарий</label>
			<input type="text" id="editprod_comment" />
		</div>
		<div class="catp-modal__row catp-modal__row--checkbox">
			<input type="checkbox" id="editprod_active" />
			<label for="editprod_active">Показывать в подсказках</label>
		</div>
		<div id="editprod_preview" class="catp__preview"></div>
		<div class="catp-modal__actions">
			<input type="button" id="editprod_cancel" value="отмена" />
			<input type="button" id="editprod_save" value="сохранить" />
		</div>
	</div>
</div>

<script src="/bb/assets/js/live_picker.js?v=5"></script>
<script>
// Подразделы отдаём прямо в страницу: их 30, отдельный запрос не нужен.
window.SUB_RAZDELS = ' . json_encode(array_map(function ($sr) {
	return array(
		'id'   => (int) $sr->getIdSubRazdel(),
		'name' => $sr->getNameSubRazdelText(),
	);
}, $sub_razdels_all), JSON_UNESCAPED_UNICODE) . ';
window.TOVAR_MOD_INITIAL_TAB = ' . json_encode($action === 'редактировать' ? 'edit' : 'new') . ';
window.TOVAR_MOD_INITIAL_MODEL = ' . $initial_model_json . ';

// Индикатор в углу заливки — иначе на время ответа сервера (~200-600мс на
// живой поиск) поле выглядит просто пустым, как будто ничего не нашлось
// (реальная жалоба при первом ручном тестировании 16.08.2026). Считаем
// открытые запросы: несколько виджетов могут спрашивать одновременно,
// прятать индикатор можно только когда закрылся последний.
(function () {
	var ajaxCount = 0;
	window.__onAjaxStart = function () {
		ajaxCount++;
		var el = document.getElementById(\'ajax_spinner\');
		if (el) { el.style.display = \'block\'; }
	};
	window.__onAjaxEnd = function () {
		ajaxCount = Math.max(0, ajaxCount - 1);
		if (ajaxCount === 0) {
			var el = document.getElementById(\'ajax_spinner\');
			if (el) { el.style.display = \'none\'; }
		}
	};
})();
</script>
<script src="/bb/assets/js/category_picker.js?v=4"></script>
<script src="/bb/assets/js/producer_picker.js?v=4"></script>
<script src="/bb/assets/js/model_picker.js?v=4"></script>
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