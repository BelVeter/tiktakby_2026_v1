<?php

use bb\Base;
use bb\Db;
use bb\models\User;

session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/bron.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Permission.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
set_time_limit(60);
//------- proverka paroley
$in_level = array(0, 5, 7);

$mysqli = Db::getInstance()->getConnection();

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
.zayavk_btn { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; margin: 4px; border: none; border-radius: 4px; cursor: pointer; color: #fff; transition: opacity 0.2s; vertical-align: middle; }
.zayavk_btn:hover { opacity: 0.8; }
.zayavk_btn * { pointer-events: none; }
.zayavk_btn::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 110%;
    left: 50%;
    transform: translateX(-50%);
    background-color: #333;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.1s ease-in-out;
    pointer-events: none;
    z-index: 1000;
}
.zayavk_btn:hover::after {
    opacity: 1;
    visibility: visible;
}
.z_btn_phone { background-color: #0084ff; }
.z_btn_cancel { background-color: #6c757d; }
.z_btn_save { background-color: #28a745; }
.z_btn_del { background-color: #dc3545; }
.z_btn_missed { background-color: #ffc107; color: #333; }
</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
' . Base::getBarCodeReaderScript() . '
</head>
<title>Заявки.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> ' . $_SESSION['user_fio'] . '</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>
<div id="zv_div"></div>

';

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/bb_nav.php');

echo '
<br />
';
require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show.php'); // включаем подключение к звонкам


?>

<script language="javascript">

	history.pushState(null, null, location.href);
	window.onpopstate = function (event) {
		history.go(1);
	};

	function show_edit(id) {

		let btn = document.getElementById('edit_show_' + id);
		if (btn.value == "оформить звонок") {

			btn.value = "отмена";
			btn.className = "zayavk_btn z_btn_cancel";
			btn.setAttribute("data-tooltip", "Отмена");
			btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;

			document.getElementById('save_podtv_' + id).style.display = "inline-block";
			document.getElementById('free_inv_n_' + id).style.display = "inline-block";
			document.getElementById('info_div_' + id).style.display = "inline-block";
			document.getElementById('br_valid_' + id).style.display = "inline-block";
		}
		else {
			btn.value = "оформить звонок";
			btn.className = "zayavk_btn z_btn_save";
			btn.setAttribute("data-tooltip", "Оформить звонок");
			btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;

			document.getElementById('save_podtv_' + id).style.display = "none";
			document.getElementById('free_inv_n_' + id).style.display = "none";
			document.getElementById('info_div_' + id).style.display = "none";
			document.getElementById('br_valid_' + id).style.display = "none";
		}
	}

	function rem_ch(id) {
		if (document.getElementById('rem_ch_' + id).value == "изменить") {
			document.getElementById('place_status_' + id).style.display = "inline-block";
			document.getElementById('info_div_' + id).style.display = "inline-block";
			document.getElementById('rem_ch_but_' + id).style.display = "inline-block";
			document.getElementById('rem_type_' + id).disabled = false;
			document.getElementById('rem_resp_' + id).disabled = false;
			document.getElementById('br_valid_' + id).style.display = "inline-block";

			document.getElementById('rem_ch_' + id).value = "отмена";
		}
		else {
			document.getElementById('place_status_' + id).style.display = "none";
			document.getElementById('info_div_' + id).style.display = "none";
			document.getElementById('rem_ch_but_' + id).style.display = "none";
			document.getElementById('rem_type_' + id).disabled = true;
			document.getElementById('rem_resp_' + id).disabled = true;
			document.getElementById('br_valid_' + id).style.display = "none";

			document.getElementById('rem_ch_' + id).value = "изменить";
		}

	}

	function br_ch_ch() {
		if (document.getElementById('br_2_t').value == "remont") {
			document.getElementById('place_status_new').style.display = "inline-block";
			document.getElementById('rem_type_new').style.display = "inline-block";
			document.getElementById('rem_resp_new').style.display = "inline-block";
		}
		else {
			document.getElementById('place_status_new').style.display = "none";
			document.getElementById('rem_type_new').style.display = "none";
			document.getElementById('rem_resp_new').style.display = "none";
		}

	}


	function new_br() {
		if (document.getElementById('br_2_t').value == "") {
			alert('Введите тип брони!');
			return false;
		}
		else {
			return true;
		}
	}


	function br_br(type2) {
		document.getElementById('filter_type2').value = type2;
		document.getElementById('br_filter').submit();

	}



	function reload() { location = '/bb/index.php' };



</script>

<?php

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$type1 = 'strong'; // потом убрать
$type2 = 'zayavka';
$action = '';
$vidan = 0;//показывает кнопку изменения
$alert = '';
$item_inv_n = '';
$to_sam = 0;
$office = $_SESSION['office'];
$free_items = '';
$free_i_of = '';



foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

// создаем перечень пользователей
$rd_lp = "SELECT * FROM logpass";
$result_lp = $mysqli->query($rd_lp);
if (!$result_lp) {
	die('Сбой при доступе к базе данных: ' . $rd_lp . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$lp_list[0] = '';
while ($lp_l = $result_lp->fetch_assoc()) {
	$lp_list[$lp_l['logpass_id']] = $lp_l['lp_fio'];
}

// создаем перечень офисов
$rd_of = "SELECT * FROM offices";
$result_of = $mysqli->query($rd_of);
if (!$result_of) {
	die('Сбой при доступе к базе данных: ' . $rd_of . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$off_pic[0] = '';
while ($t_of = $result_of->fetch_assoc()) {
	$off_pic[$t_of['number']] = $t_of['pic_addr'];
}

if (isset($_POST['action'])) {

	switch ($action) {

		case 'сохранить звонок':
			$br_upd = new \bb\classes\bron();
			$br_upd->br_load($order_id);
			if ($br_upd->ch_time != $last_ch_time) {
				die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');
			}

			$new_validity = strtotime($br_valid);

			//$br_upd->info=$info;
			if ($info != '' || $new_validity != $br_upd->validity) {
				if ($new_validity != $br_upd->validity) {
					$add = ', срок действия ' . date("d.m.Y", $br_upd->validity) . ' --> ' . date("d.m.Y");
				} else {
					$add = '';
				}

				$br_upd->info2 .= '<p class="bron_hist_unit"><span>' . date("d") . ' ' . Base::getShortMonth(date("m")) . '<sup>' . date("H:i") . '</sup> ' . User::getCurrentUser()->user_name . ':</span> ' . $info . $add . ' </p>';
			}

			$br_upd->validity = strtotime($br_valid);
			$br_upd->ch_who_id = $_SESSION['user_id'];
			$br_upd->ch_time = time();

			$br_upd->update();
			unset($br_upd);

			break;

		case 'недозвон':
			$br_upd = new \bb\classes\bron();
			$br_upd->br_load($order_id);
			//echo 'inv_n:'.$br_upd->inv_n.'<br />';
			//$br_upd->info.='<br />'.date("d.m.y - H:i", time()).' н/д - '.$lp_list[$br_upd->user_id];
			$br_upd->info2 .= '<p class="bron_hist_unit"><span>' . date("d") . ' ' . Base::getShortMonth(date("m")) . '<sup>' . date("H:i") . '</sup> ' . User::getCurrentUser()->user_name . ':</span> недозвон. </p>';
			$br_upd->update();
			unset($br_upd);

			break;

		case 'самовывоз':
			$br = new \bb\classes\bron();
			$br->br_load($order_id);
			$br->inv_n = $inv_n;
			//$br->info=$info;
			$br->validity = strtotime($br_valid);
			if ($br->ch_time != $last_ch_time) {
				die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');
			}
			$br->info2 .= '<p class="bron_hist_unit"><span>' . date("d") . ' ' . Base::getShortMonth(date("m")) . '<sup>' . date("H:i") . '</sup> ' . User::getCurrentUser()->user_name . ':</span> заявка --> бронь' . ($info != '' ? ': ' . $info : '') . '</p>';

			$br->z_to_br();

			unset($br);

			break;



		case 'удалить':

			$br = new \bb\classes\bron();
			$br->br_load($order_id);
			$br->arch_copy();
			$br->del_br();

			break;

	}
}


$query_or = "SELECT * FROM rent_orders WHERE type2='zayavka' ORDER BY (info2 IS NULL OR info2 = '') DESC, validity";
$result_or = $mysqli->query($query_or);
if (!$result_or) {
	die('Сбой при доступе к базе данных: ' . $query_or . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
$type2_num = $result_or->num_rows;
echo $type2_num;
?>

<?php

echo '

<table border="1" cellspacing="0">
  <tr>
      <th style="width:80px; text-align:center;">Фото</th>
	  <th style="width:350px; text-align:center;">Товар</th>
      <th style="width:350px; text-align:center;">коментари<br>сортировать по дате заявки <button type="button" data-sort="start" class="sort-btn" value="новые наверх">новые наверх</button></th>
	  <th style="width:81px; text-align:center;">дата действия<br><button type="button" data-sort="finish" class="sort-btn" value="новые наверх">новые наверх</button></th>
	  <!--<th style="width:90px; text-align:center;">созд/подтв</th>-->
      <th style="text-align:center;">действия</th>
	</tr>

			';



$svg_phone = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
$svg_check = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
$svg_trash = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';
$svg_phone_off = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7 2 2 0 0 1 1.72 2v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"></path><line x1="23" y1="1" x2="1" y2="23"></line></svg>';

while ($ord = $result_or->fetch_assoc()) {
	$br_line = new \bb\classes\bron();
	$br_line->br_line($ord);
	$br_line->web_load();

	//поиск свободных товаров
	$query_f = "SELECT item_inv_n, item_place FROM tovar_rent_items WHERE model_id='$br_line->model_id' AND (`status`='to_rent' OR (`status`='t_bron' AND br_time<'" . time() . "'))";
	$res_f = $mysqli->query($query_f);
	if (!$res_f) {
		die('Сбой при доступе к базе данных: ' . $query_f . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	}
	$it_free_num = $res_f->num_rows;
	//echo $it_free_num.'<br />';
	$free_items = '';
	if ($it_free_num > 0) {

		while ($it_free = $res_f->fetch_assoc()) {
			$free_items .= '<option value="' . $it_free['item_inv_n'] . '">' . $it_free['item_inv_n'] . '[' . $it_free['item_place'] . ']</option>';
			$free_i_of = $it_free['item_place'];
		}
	}




	$is_new = ($br_line->info2 === null || $br_line->info2 === '');
	echo '
	<tr data-start="' . date("Y-m-d", $br_line->order_date) . '" data-finish="' . date("Y-m-d", $br_line->validity) . '"' . ($is_new ? ' style="background-color:#e3f2fd;"' : '') . '>
		<td style="text-align: center;"><img src="' . $br_line->small_pic . '" style="max-height: 80px; max-width: 80px; width: auto; object-fit: contain;" /></td>
		<td ' . ($it_free_num > 0 ? 'style="background-color:#acf398;"' : '') . '>' . $br_line->cat_dog_name . ' ' . $br_line->producer . ': ' . $br_line->model . '. Цвет: "' . $br_line->br_color . '" <br />
		' . (User::getCurrentUser()->isAdmin() ? 'br_id:' . $br_line->order_id : '') . '
			<div id="free_inv_n_' . $br_line->order_id . '" style="display:none;">
			<select name="inv_n" form="order_' . $br_line->order_id . '">
				' . $free_items . '
			</select>';
	if ($it_free_num > 0) {
		echo '
				<input type="submit" name="action" form="order_' . $br_line->order_id . '" id="save_br_' . $br_line->order_id . '" value="самовывоз"><br />
				';
	}

	echo '
			</div>';
	if ($it_free_num > 0) {
		echo '<img style="width:25px; height:25px; float:right;" src="' . $off_pic[$free_i_of] . '"/>';
	}

	echo '</td>
		<td ' . ($br_line->appr_id > 0 ? 'style="background-color:#acf398;"' : '') . '>
		    <div style="width: 788px;">
		        <div style="float:left; width: 88px; color: #005d9e">' . date("d", $br_line->cr_time) . ' ' . Base::getShortMonth(date("m", $br_line->cr_time)) . '<sup>' . date("H:i", $br_line->cr_time) . '</sup>
		            </div>
		        <div style="float:left; width: 700px;">';
	if ($br_line->getFioFull()) {
		echo $br_line->getFioFull() . '<br>' . $br_line->getDeliveryAddress() . '<br>';
	}
	if ($br_line->phone > 1) {
		echo Base::phone_print($br_line->phone) . '<br>';
	}
	echo '

                     ' . $br_line->info . '
		        </div>
		        <div style="clear: both;"></div>
            </div>
            ' . $br_line->info2 . '
			<div style="display:none;" id="info_div_' . $br_line->order_id . '">
				<textarea rows="5" cols="70" name="info" id="info_' . $br_line->order_id . '" form="order_' . $br_line->order_id . '"></textarea><br />
      		</div>
      	</td>
		<td>' . date("d.m.y", $br_line->validity) . '
      		<div style="position:relative; z-index:2; background-color:#FFF;"><input style="display:none;" type="date" name="br_valid" id="br_valid_' . $br_line->order_id . '" form="order_' . $br_line->order_id . '" value="' . date("Y-m-d", $br_line->validity) . '"></div>
      		</td>
    	<!--<td ' . ($br_line->web == 1 ? 'style="background-color:#F60"' : '') . '>' . $lp_list[$br_line->cr_who_id] . '/' . $lp_list[$br_line->appr_id] . '</td>-->
		<td>
			<form name="order_' . $br_line->order_id . '" id="order_' . $br_line->order_id . '" action="rent_zayavk.php" method="post" ' . ($br_line->type2 == 'out' ? 'style="display:none;"' : '') . '>
			<div>
				<input type="hidden" name="user_id" id="user_id_' . $br_line->order_id . '" value="' . $_SESSION['user_id'] . '">
				<input type="hidden" name="order_id" id="order_id_' . $br_line->order_id . '" value="' . $br_line->order_id . '">
				<input type="hidden" name="type2" id="type2_' . $br_line->order_id . '" value="' . $br_line->type2 . '">
      			<input type="hidden" name="last_ch_time" value="' . $br_line->ch_time . '">

				<button type="button" name="action" class="zayavk_btn z_btn_save" data-tooltip="Оформить звонок" id="edit_show_' . $br_line->order_id . '" value="оформить звонок" onclick="show_edit(\'' . $br_line->order_id . '\');">' . $svg_phone . '</button>
				<button type="submit" name="action" class="zayavk_btn z_btn_save" data-tooltip="Сохранить звонок" id="save_podtv_' . $br_line->order_id . '" value="сохранить звонок" style="display:none;">' . $svg_check . '</button>
      	  		<button type="submit" name="action" class="zayavk_btn z_btn_missed" data-tooltip="Недозвон" id="obnov_' . $br_line->order_id . '" value="недозвон" onclick="return obnov(\'' . $br_line->order_id . '\');">' . $svg_phone_off . '</button>
				<button type="submit" name="action" class="zayavk_btn z_btn_del" data-tooltip="Удалить" id="del_but_' . $br_line->order_id . '" onclick="return confirm(\'Вы точно хотите удалить эту бронь?\');" value="удалить">' . $svg_trash . '</button>
			</div>
			</form>
		</td>
	</tr>



			';
	$free_i_of = '';
	unset($br_line);
}







function get_post($var)
{
	global $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}


function sel_d($value, $pattern)
{
	if ($value == $pattern) {
		return 'selected="selected"';
	} else {
		return '';
	}
}

function good_print($var)
{
	$var = htmlspecialchars(stripslashes($var));
	return $var;
}


function user_select($id)
{
	return '
		<option value="">не определен</option>
      		<option ' . sel_d('2', $id) . ' value="2">Кристина</option>
			<option ' . sel_d('5', $id) . ' value="5">Аня</option>
			<option ' . sel_d('4', $id) . ' value="4">Андрей</option>
			<option ' . sel_d('9', $id) . ' value="9">Света</option>
			<option ' . sel_d('12', $id) . ' value="12">Алексей</option>
			<option ' . sel_d('13', $id) . ' value="13">Таня</option>
			<option ' . sel_d('16', $id) . ' value="16">Люовь Алексеевна</option>
			<option ' . sel_d('18', $id) . ' value="18">Марго</option>

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
</table>
<script>
	document.querySelectorAll('.sort-btn').forEach((el) => {
		el.addEventListener('click', sortTable);
	});

	// Function to sort table
	function sortTable(e) {
		let sortType = e.target.dataset.sort;
		let switcher = 1;
		console.log(e.target.value);
		if (e.target.value == 'новые наверх') {
			e.target.innerHTML = 'старые наверх';
			e.target.value = 'старые наверх';
			switcher = -1;
		}
		else {
			e.target.innerHTML = 'новые наверх';
			e.target.value = 'новые наверх';
			switcher = 1;
		}
		var table = document.querySelector('table'); // Select the table
		var rows = Array.from(table.rows); // Convert HTMLCollection to Array

		rows.shift(); // Remove the header row if exists

		// Sort rows Array
		rows.sort(function (a, b) {
			var dateA = new Date(a.getAttribute('data-' + sortType)); // Convert to Date object
			var dateB = new Date(b.getAttribute('data-' + sortType)); // Convert to Date object
			return (dateA - dateB) * switcher; // Sort in ascending order
		});

		// Append sorted rows back into the table
		for (let row of rows) {
			table.appendChild(row);
		}
	}

</script>
</body>

</html>