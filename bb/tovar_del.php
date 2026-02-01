<?php
session_start();

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); // включаем подключение к базе данных

echo '

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Панель администратора BB</title>
<link href="stile.css" rel="stylesheet" type="text/css" />


';
?>

<script language="javascript">

function one_del () {
	if (confirm ('Внимание! Товар будет удален без возможности восстановления! Точно хотите удалить товар?')) {
		return true;
	}
	else {
		return false;
	}
}
</script>


<?php

//------- proverka paroley

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || !$_SESSION['level']>4) {
	die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	<body>

	<form action="index.php" method="post">
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>
	</head>

<body>
');
}

//-----------proverka paroley


//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//		foreach ($_POST as $key => $value) {
//			echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//		}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

$mysqli = \bb\Db::getInstance()->getConnection();

if (isset($_POST['action'])) {

	switch ($action) {
		case 'сохранить выбытие':

			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
			if (!$result_start) {
        $done="no";
        die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
      }

			$done="yes";

			$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$model_id."'";
			$result_model_def = $mysqli->query($query_model_def);
			if (!$result_model_def) die('Сбой при доступе к базе данных: '.$query_model_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
			$model_def=$result_model_def->fetch_assoc();


			$query_item_def = "SELECT * FROM tovar_rent_items WHERE item_id='".$item_id."'";
			$result_item_def = $mysqli->query($query_item_def);
			if (!$result_item_def) {
        $done="no";
        die('Сбой при доступе к базе данных: '.$query_item_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
      }
			$item_def=$result_item_def->fetch_assoc();
			$item_rows = $result_item_def->num_rows;
			if ($item_rows!=1) {
				die ('Операция не удалась. Сообщите Диме о проблеме. Скорее всего товар уже удален/перемещен.');
			}

			//проверка на наличие броней
			$q_br_ch = "SELECT * FROM rent_orders WHERE inv_n='".$item_def['item_inv_n']."'";
			$result_br_ch = $mysqli->query($q_br_ch);
			if (!$result_br_ch) die('Сбой при доступе к базе данных: '.$q_br_ch.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
			$br_ch_num=$result_br_ch->num_rows;

			if ($br_ch_num>0) {
				die ('Операция не удалась. Удалите брони на товар.');
			}

			//проверка на наличие активных сделок
			$q_br_ch = "SELECT * FROM rent_deals_act WHERE item_inv_n='".$item_def['item_inv_n']."'";
			$result_br_ch = $mysqli->query($q_br_ch);
			if (!$result_br_ch) die('Сбой при доступе к базе данных: '.$q_br_ch.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
			$br_ch_num=$result_br_ch->num_rows;

			if ($br_ch_num>0) {
				die ('Операция не удалась. Удалите активные сделки.');
			}




			if ($item_def['status']=='rented_out') {
				die ('Товар на руках! Сначала оформите возврат.');
			}
			if ($item_def['status']=='to_deliver') {
				die ('Товар отложен для курьера! Отмените выезд.');
			}
			if ($item_def['status']=='bron') {
				die ('На товар оформлена бронь! Удалите бронь.');
			}


			foreach ($item_def as $key => $value) {
				$$key = $value;
			}

			$a_date=getdate(time());
			$arch_date=mktime(0, 0, 0, $a_date['mon'], ($a_date['mday']), $a_date['year']);

			$query_ins = "INSERT INTO tovar_rent_items_arch VALUES('', '$arch_date', '".time()."', '".$_SESSION['user_id']."', '$out_status', '$sell_amount_byr', '$rent_payment_type', '$sell_amount_usd', '$info', '$item_id', '$cat_id', '$producer', '$model_id', '$item_n', '$item_inv_n', '$sex', '$item_size', '$real_item_size', '$item_rost1', '$item_rost2', '$item_set', '$buy_date', '$buy_price', '$buy_price_cur', '$exch_to_byr', '$seller', '$item_info', '$cr_ch_date', '$user', '$status', '$active_deal_id', '$item_color', '$item_place', '$br_time', '$state', '', '$to_move', '')";
			$result_ins = $mysqli->query($query_ins);
			if (!$result_ins) {
        $done="no";
        die('Сбой при доступе к базе данных: '.$query_ins.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
      }


			$query_del = "DELETE FROM tovar_rent_items WHERE item_id='$item_id'";
			$result_del = $mysqli->query($query_del);
			if (!$result_del) {
        $done="no";
        die('Сбой при доступе к базе данных: '.$query_del.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
      }


			if ($done=='yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


				die('
					</head>
					<body>
				Товар успешно перемещен в архив ! <br />
				<div class="top_menu">
					<a class="div_item" href="/bb/index.php">На главную</a>
				</div>
				<br /><br />

				<div class="top_menu">
					<a class="div_item" href="/bb/tovar.php">Новый товар</a>
					<a class="div_item" href="#" onclick="document.getElementById(\'cat_ch_sel\').submit(); return false;">Просмотр всех товаров (этой категории)</a>
				</div>
				<br /><br />

				<form name="cat_chose" action="kr_baza_new.php" method="post" id="cat_ch_sel">
				<input type="hidden" name="cat_id" value="'.$model_def['tovar_rent_cat_id'].'" />
				</form>

				</body></html>
				');


			}
			else {

				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
			}

		break;

		case 'удалить данный товар':

			$query_item_def = "SELECT * FROM tovar_rent_items WHERE item_id='".$item_id."'";
			$result_item_def = $mysqli->query($query_item_def);
			if (!$result_item_def) {
        $done="no";
        die('Сбой при доступе к базе данных: '.$query_item_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
      }
			$item_def=$result_item_def->fetch_assoc();
			$item_rows = $result_item_def->num_rows;
			if ($item_rows!=1) {
				die ('Операция не удалась. Сообщите Диме о проблеме. Скорее всего товар уже удален/перемещен.');
			}

			if ($item_def['status']=='rented_out') {
				die ('Товар на руках! Сначала оформите возврат.');
			}

			$query_del = "DELETE FROM tovar_rent_items WHERE item_id='$item_id'";
			$result_del = $mysqli->query($query_del);
			if (!$result_del) {die('Сбой при доступе к базе данных: '.$query_del.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

			$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$model_id."'";
			$result_model_def = $mysqli->query($query_model_def);
			if (!$result_model_def) die('Сбой при доступе к базе данных: '.$query_model_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
			$model_def=$result_model_def->fetch_assoc();

					die('
					</head>
					<body>
				Товар успешно удален ! <br />
				<div class="top_menu">
					<a class="div_item" href="/bb/index.php">На главную</a>
				</div>
				<br /><br />

				<div class="top_menu">
					<a class="div_item" href="/bb/tovar.php">Новый товар</a>
					<a class="div_item" href="#" onclick="document.getElementById(\'cat_ch_sel\').submit(); return false;">Просмотр всех товаров (этой категории)</a>
				</div>
				<br /><br />

				<form name="cat_chose" action="kr_baza_new.php" method="post" id="cat_ch_sel">
				<input type="hidden" name="cat_id" value="'.$model_def['tovar_rent_cat_id'].'" />
				</form>

				</body></html>
				');


break;

		case 'удалить все (модель и все товары)':

		$done="yes";

		$query_start = "START TRANSACTION";
		$result_start = $mysqli->query($query_start);
		if (!$result_start) {
      $done="no";
      die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }

		$query_del_mod = "DELETE FROM tovar_rent WHERE tovar_rent_id='$model_id'";
		$result_del_mod = $mysqli->query($query_del_mod);
		if (!$result_del_mod) {
      $done="no";
      die('Сбой при доступе к базе данных: '.$query_del_mod.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }

		$query_del_it = "DELETE FROM tovar_rent_items WHERE model_id='$model_id'";
		$result_del_it = $mysqli->query($query_del_it);
		if (!$result_del_it) {
      $done="no";
      die('Сбой при доступе к базе данных: '.$query_del_it.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }

		if ($done=='yes') {
			$query_fin = "COMMIT";
			$result_fin = $mysqli->query($query_fin);
			if (!$result_fin) die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		}
		else {
			$query_fin = "ROLLBACK'";
			$result_fin = $mysqli->query($query_fin);
			if (!$result_fin) die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		}


		$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$model_id."'";
		$result_model_def = $mysqli->query($query_model_def);
		if (!$result_model_def) die('Сбой при доступе к базе данных: '.$query_model_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		$model_def=$result_model_def->fetch_assoc();

		die('
					</head>
					<body>
				Модель и все товары успешно удалены ! <br />
				<div class="top_menu">
					<a class="div_item" href="/bb/index.php">На главную</a>
				</div>
				<br /><br />

				<div class="top_menu">
					<a class="div_item" href="/bb/tovar.php">Новый товар</a>
					<a class="div_item" href="#" onclick="document.getElementById(\'cat_ch_sel\').submit(); return false;">Просмотр всех товаров (этой категории)</a>
				</div>
				<br /><br />

				<form name="cat_chose" action="kr_baza_new.php" method="post" id="cat_ch_sel">
				<input type="hidden" name="cat_id" value="'.$model_def['tovar_rent_cat_id'].'" />
				</form>

				</body></html>
				');







		break;

	}
}





$query_items = "SELECT * FROM tovar_rent_items WHERE item_id='$item_id' ORDER BY cr_ch_date DESC ";
//$query_items = "SELECT * FROM tovar_rent_items ORDER BY cr_ch_date DESC LIMIT 0,50";
$result_items = $mysqli->query($query_items);
if (!$result_items) die('Сбой при доступе к базе данных: '.$query_items.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
$item_def=$result_items->fetch_assoc();


$query_mod_num = "SELECT * FROM tovar_rent_items WHERE model_id='$model_id'";
$result_mod_num = $mysqli->query($query_mod_num);
if (!$result_mod_num) die('Сбой при доступе к базе данных: '.$query_mod_num.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
$mod_tov_n=$result_mod_num->num_rows;

$query_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$model_id'";
$result_model = $mysqli->query($query_model);
if (!$result_model) die('Сбой при доступе к базе данных: '.$query_model.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
$model_def=$result_model->fetch_assoc();


$query_cats = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$model_def['tovar_rent_cat_id']."'";
$result_cats = $mysqli->query($query_cats);
if (!$result_cats) die('Сбой при доступе к базе данных: '.$query_cats.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
$cat_def=$result_cats->fetch_assoc();


echo'

		<div class="top_menu">
			<a class="div_item" href="/bb/index.php">На главную</a>
			<a class="div_item" href="#" onclick="document.getElementById(\'cat_ch_sel\').submit(); return false;">Просмотр всех товаров (этой категории)</a>

				<form name="cat_chose" action="kr_baza_new.php" method="post" id="cat_ch_sel">
				<input type="hidden" name="cat_id" value="'.$model_def['tovar_rent_cat_id'].'" />
				</form>
		</div>



';


echo '

<table border="1" cellspacing="0">
	<tr>
		<td>Категория товара:</td>
		<td>'.$cat_def['rent_cat_name'].'</td>
	</tr>
	<tr>
		<td>Фирма/производитель:</td>
		<td>'.good_print($model_def['producer']).'</td>
	</tr>

	<tr>
		<td>Модель:</td>
		<td>'.good_print($model_def['model']).'</td>
	</tr>

	<tr>
		<td>Цвет:</td>
		<td>'.good_print($model_def['color']).'</td>
	</tr>

	<tr>
		<td>Комплектация модели (стандарт):</td>
		<td>'.good_print($model_def['set']).'</td>
	</tr>

	<tr>
		<td>Оценочная стоимость:</td>
		<td>'.good_print($model_def['agr_price']).' '.good_print($model_def['agr_price_cur']).'</td>
	</tr>

	<tr>
		<td>Прогноз срока службы (непрервыное использование):</td>
		<td>'.good_print($model_def['lom_srok']).'</td>
	</tr>

</table>
';

echo 'У данной модели имеется <font color="red"><strong> '.$mod_tov_n.' единиц(-ы) товара.</strong></font><br />
<form method="post" action="tovar_del.php" style="display:inline-block;">
<input type="hidden" name="model_id" value="'.$model_id.'">
<input type="hidden" name="item_id" value="'.$item_id.'">
<!--<input type="submit" name="action" value="удалить все (модель и все товары)" onclick="return one_del ();">--><input type="button" value="отмена" onclick="document.getElementById(\'cat_ch_sel\').submit();" />
</form> <br /><br />
';

echo 'выбрать (другой) товар по инв. номеру: <br />';
while ($item_num=$result_mod_num->fetch_assoc()) {
	if ($item_num['item_inv_n']==$item_def['item_inv_n']) {continue;}
	echo '
<form method="post" action="tovar_del.php" style="display:inline-block;">
	<input type="hidden" name="model_id" value="'.$item_num['model_id'].'">
	<input type="hidden" name="item_id" value="'.$item_num['item_id'].'">
	<input type="submit" name="action" value="'.$item_num['item_inv_n'].'">
</form>
			';
}



echo'<br /><br />
<table border="1" cellspacing="0">
<tr>
	<td>Инвентарный номер товара:</td>
	<td><span id="inv_n_cat"></span>'.$item_def['item_inv_n'].'</td>
</tr>
<tr>
	<td>Для одежды - размер:</td>
	<td>'.good_print($item_def['item_size']).'</td>
</tr>
<tr>
	<td>Фактическая комплектация товара:</td>
	<td>'.good_print($item_def['item_set']).'</td>
</tr>
<tr>
	<td>Дата приобретения:</td>
	<td><input type="date" name="buy_date" id="buy_date" size="10" value="'.date("Y-m-d", $item_def['buy_date']).'" readonly="readonly" /></td>
</tr>
<tr>
	<td>Цена приобретения:</td>
	<td>'.$item_def['buy_price'].' '.$item_def['buy_price_cur'].'</td>
</tr>
<tr>
	<td>Курс пересчета в USD:</td>
	<td>'.$item_def['exch_to_byr'].'</td>
</tr>
<tr>
	<td>Продавец:</td>
	<td>'.good_print($item_def['seller']).'</td>
</tr>
<tr>
	<td>Дополнительная информация о товаре:</td>
	<td>'.good_print($item_def['item_info']).'</td>
</tr>

</table>
';










echo '
<form method="post" action="tovar_del.php" style="display:inline-block;">
<input type="hidden" name="model_id" value="'.$model_id.'">
<input type="hidden" name="item_id" value="'.$item_id.'">
<!--<input type="submit" name="action" value="удалить данный товар" onclick="return one_del ();">--><input type="button" value="отмена" onclick="document.getElementById(\'cat_ch_sel\').submit();" />

<br /><br />
Дата выбытия: <input type="date" name="out_date" id="out_date" value="'.date("Y-m-d", time()).'" /><br />
Тип выбытия: <select name="out_status" id="out_status"">
			  		<option value="lom_out">сломался/износился</option>
					<option value="cl_out">не вернул клиент</option>
					<option value="sell">продажа (клиенту)</option>
					<option value="our_out">сами потеряли</option>
					<option value="other_out">другое</option>
				</select>
<br />
Получено при выбытии: <input type="text" name="sell_amount_byr" /> тыс.бел.руб. = <input type="text" name="sell_amount_usd" /> эквивалент USD
<select name="rent_payment_type" id="rent_payment_type">
					<option value="no_payment">не оплачено</option>
					<option value="nal_no_cheque">нал без чека</option>
					<option value="nal_cheque">нал с чеком</option>
					<option value="card">карточка</option>
					<option value="bank">банк</option>
</select>
<!--
<select name="sell_currency" id="sell_price_cur">
	<option value="TBYR" >тыс.бел.руб.</option>
	<option value="USD" >доллары США</option>
    <option value="EUR" >евро</option>
   	<option value="RUB" >росс.руб.</option>
</select>

Курс пересчета в USD:<input type="text" name="exchange_rate" id="exch_rate" size="10" value="1" /> -->

		<br />
Дополнительная информация по выбытию:<br />
<textarea name="info" rows="4" cols="70" id="item_info"></textarea><br />
<input type="submit" name="action" value="сохранить выбытие" onclick=""/>
</form>
';






function get_post($var)
{
  $mysqli=\bb\Db::getInstance()->getConnection();

	return $mysqli->real_escape_string($_POST[$var]);
}


function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}

?>
