<?php
session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных

echo '

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Панель администратора BB</title>
<link href="stile.css" rel="stylesheet" type="text/css" />
';


//------- proverka paroley

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || $_SESSION['level']!=1) {
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

$cat_id='def';
$srch_rules='';

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

if ($cat_id=='показать все товары') {
	$srch_rules='';
}
elseif ($cat_id>0) {
	$srch_rules='WHERE cat_id=\''.$cat_id.'\' ';
}
elseif ($cat_id=='def') {
	$srch_rules='WHERE cat_id=\'def\' ';
}

	
	//для списка категорий
	$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
	$result_cats = mysql_query($query_cats);
	if (!$result_cats) die("Сбой при доступе к базе данных: '$query_cats'".mysql_error());

	$query_items = "SELECT * FROM tovar_rent_items ".$srch_rules."ORDER BY cr_ch_date DESC ";
	//$query_items = "SELECT * FROM tovar_rent_items ORDER BY cr_ch_date DESC LIMIT 0,50";
	$result_items = mysql_query($query_items);
	if (!$result_items) die("Сбой при доступе к базе данных: '$query_items'".mysql_error());




echo '
<div class="top_menu">
	<a class="div_item" href="/bb/tovar.php">Новый товар</a>
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>
		
<form name="cat_chose" action="tovar_rent_all.php" method="post" id="cat_ch_sel">
	<select name="cat_id" id="new_select" onchange="document.getElementById(\'cat_ch_sel\').submit(); return false;">
			  		<option value="def">выберите категорию</option>
			    ';
			while ($cat_names = mysql_fetch_array($result_cats)) {
			echo '<option value="'.$cat_names['tovar_rent_cat_id'].'" '.sel_d($cat_names['tovar_rent_cat_id'], $cat_id).' >'.good_print($cat_names['rent_cat_name']).'</option>';
			}
			
			echo $cat_select_text.'    	
    </select> <input type="submit" name="cat_id" value="показать все товары">
</form>
<form name="cat_chose" action="tovar_rent_all.php" method="post">
	<input type="hidden" name="cat_id" value="показать все товары">
</form>';
		

echo'
<table border="1" cellspacing="0" class="rent_all_table">
<tr>
	<th class="rent_all_t_cat">Категория</th>
	<th class="rent_all_t_prod">Произв/продавец</th>
	<th class="rent_all_t_mod">Модель</th>
	<th class="rent_all_t_col">Цвет</th>
	<th class="rent_all_t_size">Размер</th>
	<th class="rent_all_t_set">Комплектация</th>    
	<th class="rent_all_t_dogpr">Оц. ст-ть</th>    
	<th class="rent_all_t_inv">Инв. №</th>
	<th class="rent_all_t_date">Дата приобр.</th>
	<th class="rent_all_t_pr">Цена приобр.</th>    
	<th class="rent_all_t_inf">Доп. инфо</th>
    <th>Действия</th>    
</tr>';

while ($items=mysql_fetch_array($result_items)) {

	$query_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id=".$items['model_id']." LIMIT 0,2000";
	$result_model = mysql_query($query_model);
	if (!$result_model) die("Сбой при доступе к базе данных: '$query_model'".mysql_error());
	$model=mysql_fetch_array($result_model);
	
	$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id=".$model['tovar_rent_cat_id']." LIMIT 0,1";
	$result_cat = mysql_query($query_cat);
	if (!$result_cat) die("Сбой при доступе к базе данных: '$query_cat'".mysql_error());
	$cat=mysql_fetch_array($result_cat);
	
	$query_tarifs = "SELECT * FROM rent_tarif_act WHERE model_id='".$items['model_id']."' ORDER BY sort_num, kol_vo";
	$result_tarifs = mysql_query($query_tarifs);
	if (!$result_tarifs) die("Сбой при доступе к базе данных: '$query_tarifs'".mysql_error());
	$tarif_rows = mysql_num_rows($result_tarifs);
	$tarif_def=mysql_fetch_array($result_tarifs);
	
//	(model_id='.$items['model_id'].') / '.$items['item_id'].'
echo '
<tr>
	<td class="rent_all_t_cat">'.$cat['rent_cat_name'].'</td>
	<td class="rent_all_t_prod">'.$model['producer'].' /<br />'.$items['seller'].'</td>
	<td class="rent_all_t_mod">'.$model['model'].'</td>
	<td class="rent_all_t_col">'.$model['color'].'</td>
	<td class="rent_all_t_size">'.$items['item_size'].'</td>
	<td class="rent_all_t_set">'.($model['set']==$items['item_set'] ? ('<strong>Стандарт: </strong>'.$model['set']) : ('<strong>Не стандартная комплектация:</strong>'.$items['item_set'])).'</td>
	<td class="rent_all_t_dogpr">'.number_format($model['agr_price'], 0, ',', ' ').' '.$model['agr_price_cur'].'</td>
    <td class="rent_all_t_inv">'.$items['item_inv_n'].'</td>
    <td class="rent_all_t_date">'.date("d.m.y", $items['buy_date']).'</td>
    <td class="rent_all_t_pr">'.number_format($items['buy_price'], 0, ',', ' ').' '.$items['buy_price_cur'].'</td>
    <td class="rent_all_t_inf"><i>тариф: '.$tarif_def['rent_per_step'].'=</i>'.$items['item_info'].' '.($_SESSION['level']==1 ? ('(model_id='.$items['model_id'].')') : '').'</td>
    <td>
		<form method="post" action="tovar.php" style="display:inline-block;">
			<input type="hidden" name="item_id" value="'.$items['item_id'].'">
			<input type="submit" name="action" value="редактировать">
		</form>
		<form method="post" action="rent_tarifs.php" style="display:inline-block;">
			<input type="hidden" name="model_id" value="'.$items['model_id'].'">
			<input type="submit" name="action" value="тарифы">
		</form>
		<form method="post" action="tovar_del.php" style="display:inline-block;">
			<input type="hidden" name="model_id" value="'.$items['model_id'].'">
			<input type="hidden" name="item_id" value="'.$items['item_id'].'">
			<input type="submit" name="action" value="удалить">
		</form>
    </td>
</tr>
';
}

echo'
</table>
';


function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}


function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}

function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}



?>