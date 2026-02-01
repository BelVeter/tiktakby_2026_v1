<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных



//------- proverka paroley
$in_level= array(0,5,7);

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level))) {
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

</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<title>WEB инфо моделей.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: '.$_SESSION['office'].'</form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/kr_baza_new.php"><strong>Все товары</strong></a>
	<a class="div_item" href="/bb/tovar_new.php">Новый товар</a>
</div>
';
require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

?>

<script language="javascript">

function add_w_del (id) {
	
	document.getElementById('add_id_del_form').value=document.getElementById('add_w_'+id).value;
	document.getElementById('add_web_del_f').submit();

}//function end

</script>




<?php 

//$model_id='122';

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}


if (isset($_POST['action'])) {

	switch ($action) {

		case 'сохранить':
		
			$query_new = "INSERT INTO rent_model_web VALUES('', '$model_id', '$cat_id', '', '$page_addr', '$title', '$keywords', '$l2_pic', '$l2_name', '$l2_alt', '$web_way', '$item_name_main', '$m_pic_big', '$m_pic_small', '$m_pic_alt', '$m_a_title', '$logo', '$main_descr', '$but_descr', '$bat_pic', '$sort_n')";
			$result = $mysqli->query($query_new);
			if (!$result) {die('Сбой при доступе к базе данных: '.$query_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

			for ($i=1; $i<=$dop_ph_num; $i++) {
				
				$big2='big_'.$i;
				$big3=$$big2;
				
				$small2='small_'.$i;
				$small3=$$small2;
				
				$alt2='alt_'.$i;
				$alt3=$$alt2;
				
				$title2='title_'.$i;
				$title3=$$title2;
				
				if ($big3=='' && $small3=='') { continue; }
				
				$query_new = "INSERT INTO dop_photos VALUES('', '$model_id', '$big3', '$small3', '$alt3', '$title3')";
				$result = $mysqli->query($query_new);
				if (!$result) {die('Сбой при доступе к базе данных: '.$query_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
				
			}
			
	break;
		
		
	case 'обновить':
		
		//обновление основной информации
		$query_upd = "UPDATE rent_model_web SET page_addr='$page_addr', `title`='$title', `keywords`='$keywords', l2_pic='$l2_pic', l2_name='$l2_name', l2_alt='$l2_alt', web_way='$web_way', item_name_main='$item_name_main', m_pic_big='$m_pic_big', m_pic_small='$m_pic_small', m_pic_alt='$m_pic_alt', m_a_title='$m_a_title', `logo`='$logo', main_descr='$main_descr', but_descr='$but_descr', bat_pic='$bat_pic', sort_n='$sort_n' WHERE model_id='$model_id'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		
	
			
			for ($i=1; $i<=$dop_ph_num; $i++) {
			
				$big2='big_'.$i;
				$big3=$$big2;
				
				$small2='small_'.$i;
				$small3=$$small2;
				
				$alt2='alt_'.$i;
				$alt3=$$alt2;
				
				$title2='title_'.$i;
				$title3=$$title2;
				
				$dop_id2='dop_id_'.$i;
				if (isset($$dop_id2)) {	$dop_id3=$$dop_id2;	}
				
				
				if ($big3=='' && $small3=='' && $i<=$dop_ph_act_num) { // если обнулили значения - удаляем
					$q_del = "DELETE FROM dop_photos WHERE dop_id='$dop_id3'";
					$result = $mysqli->query($q_del);
					if (!$result) {die('Сбой при доступе к базе данных: '.$q_del.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
					
					continue;
				}
				
				if ($big3!='' && $small3!='' && $i>$dop_ph_act_num) {//вставляем доп. фотос
					echo 'сработало';
					$query_new = "INSERT INTO dop_photos VALUES('', '$model_id', '$big3', '$small3', '$alt3', '$title3')";
					$result = $mysqli->query($query_new);
					if (!$result) {die('Сбой при доступе к базе данных: '.$query_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
								
					continue;
				}
				
				if ($big3=='' && $small3=='' && $i>$dop_ph_act_num) {//пропускаем пустые свыше акт				
					continue;
				}
				
			$query_upd = "UPDATE dop_photos SET `big`='$big3', `small`='$small3', `alt`='$alt3', `title`='$title3' WHERE dop_id='$dop_id3'";
			$result = $mysqli->query($query_upd);
			if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
			
			}
			
			
		//добавляем мульти-категории на web
		if ($new_add_cat>0) {
			$query_mw = "INSERT INTO multi_web VALUES('', '$model_id', '$new_add_cat', '$l2_pic_add', '$cat_id')";
			$result = $mysqli->query($query_mw);
			if (!$result) {die('Сбой при доступе к базе данных: '.$query_mw.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
				
		}
		
		
	break;
	
	case 'add_web_del':
		
		$q_del = "DELETE FROM multi_web WHERE id='$add_id'";
		$result = $mysqli->query($q_del);
		if (!$result) {die('Сбой при доступе к базе данных: '.$q_del.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
			
	break;
	
	
	}//end of switch
}//end of isset




$m_info_q = "SELECT * FROM rent_model_web WHERE model_id='$model_id' LIMIT 1";
$result_m_info = $mysqli->query($m_info_q);
if (!$result_m_info) {die('Сбой при доступе к базе данных: '.$m_info_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$mi_num=$result_m_info->num_rows;
$mi=$result_m_info->fetch_assoc();


//определяем категорию и модель
$m_b_info_q = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$model_id' LIMIT 1";
$result_b_info = $mysqli->query($m_b_info_q);
if (!$result_b_info) {die('Сбой при доступе к базе данных: '.$m_b_info_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$mbi_num=$result_b_info->num_rows;
$mbi=$result_b_info->fetch_assoc();

$cat_id=$mbi['tovar_rent_cat_id'];

//запрос информации о категории
$cat_q="SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='$cat_id' LIMIT 1";
$result_cat_def = $mysqli->query($cat_q);
if (!$result_cat_def) {die('Сбой при доступе к базе данных: '.$cat_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$cat_def=$result_cat_def->fetch_assoc();

$cat_name=$cat_def['rent_cat_name'].' ('.$cat_def['tovar_rent_cat_id'].')';



$dop_ph_q = "SELECT * FROM dop_photos WHERE model_id='$model_id'";
$result_dop_ph = $mysqli->query($dop_ph_q);
if (!$result_dop_ph) {die('Сбой при доступе к базе данных: '.$dop_ph_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$dop_ph_num=$result_dop_ph->num_rows;


$color_pics='';
if ($mbi['color']=='multicolor') {
	//выбор имеющихся цветов для web картинок
	$color_pics_q="SELECT DISTINCT (item_color) FROM tovar_rent_items WHERE model_id='$model_id'";
	$result_color_pics = $mysqli->query($color_pics_q);
	if (!$result_color_pics) {die('Сбой при доступе к базе данных: '.$color_pics_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
}



echo '<br /><strong>Модель №'.$model_id.' - '.$cat_name.': '.$mbi['model'].'/'.$mbi['producer'].'</strong>

<br />(двойные, одинарные кавычки - д.б. только в коде. Вместно них используем <em>&amp;quot;</em>, <em>&amp;apos;</em>)<br />

<form action="/bb/l_3_ch.php" method="post" target="_blank" id="check_form">
	<input type="hidden" name="model_id" value="'.$model_id.'">
	<input type="hidden" name="cat_id" value="'.$cat_def['tovar_rent_cat_id'].'">
	<input type="hidden" name="action" value="проверить результат" />
</form>


<form action="model_web.php" method="post">
<input type="hidden" name="cat_id" value="'.$cat_id.'" />
<input type="hidden" name="model_id" value="'.$model_id.'" />		
<input type="button" onclick="document.getElementById(\'check_form\').submit(); return false;" value="проверить результат" >
';

if ($mi_num>0) {
	echo '<input type="submit" name="action" value="обновить" />';
}

echo'
<table border="1" cellspacing="0">
	<tr>
		<td style="width:200px;">Название модели для 2-го уровня:</td>
		<td><input size="100" type="text" name="l2_name" value="'.htmlspecialchars($mi['l2_name']).'" /><br />
			порядок отображения: <input style="width:50px;" type="number" name="sort_n" value="'.$mi['sort_n'].'" />
			</td>
	</tr>
	<tr>
		<td>Адрес фото 2-го уровня (180x180) ОТ КОРНЯ:</td>
		<td><input size="100" type="text" name="l2_pic" value="'.$mi['l2_pic'].'" />
			';

	if ($mbi['color']=='multicolor') {
		echo '<br />Имеющиеся цвета:<br />';
		
		while ($color_pics=$result_color_pics->fetch_assoc()) {
			echo 'цвет:'.good_print($color_pics['item_color']).'<br />';
		}
		
		
		
		
		
	}

			
echo '			</td>
	</tr>
	<tr>
		<td>Alt для фото 2-го уровня</td>
		<td><input size="100" type="text" name="l2_alt" value="'.htmlspecialchars($mi['l2_alt']).'" /></td>
	</tr>		
	<tr>
		<td>Адрес страницы 3-го уровня: ОТ КОРНЯ</td>
		<td><input size="100" type="text" name="page_addr" value="'.$mi['page_addr'].'" /></td>
	</tr>
	<tr>
		<td>Хлебные крошки</td>
		<td><textarea name="web_way" cols="100">'.htmlspecialchars($mi['web_way']).'</textarea></td>
	</tr>
	<tr>
		<td>Title страницы 3-го уровня:</td>
		<td><input size="100" type="text" name="title" value="'.htmlspecialchars($mi['title']).'" /></td>
	</tr>
				
	<tr>
		<td>Keywords страницы 3-го уровня:</td>
		<td><textarea name="keywords" cols="100">'.htmlspecialchars($mi['keywords']).'</textarea></td>
	</tr>
	<tr>
		<td>Наименование товара 3-й уровень:</td>
		<td><input size="100" type="text" name="item_name_main" value="'.htmlspecialchars($mi['item_name_main']).'" /></td>
	</tr>
	<tr>
		<td>Адрес основной картинки 3-й уровень (350x350) ОТ КОРНЯ:</td>
		<td><input size="100" type="text" name="m_pic_small" value="'.$mi['m_pic_small'].'" /></td>
	</tr>
	<tr>
		<td>ALT для основной картинки 3-й уровень:</td>
		<td><input size="100" type="text" name="m_pic_alt" value="'.htmlspecialchars($mi['m_pic_alt']).'" /></td>
	</tr>
	<tr>
		<td>адрес для увеличенной основной картинки 3-й уровень (ANY x ANY):</td>
		<td><input size="100" type="text" name="m_pic_big" value="'.$mi['m_pic_big'].'" /></td>
	</tr>
	<tr>
		<td>TITLE ссылки с основной картинки 3-й уровень:</td>
		<td><input size="100" type="text" name="m_a_title" value="'.htmlspecialchars($mi['m_a_title']).'" /></td>
	</tr>			
	<tr>
		<td>LOGO производителя:</td>
		<td><input size="100" type="text" name="logo" value="'.$mi['logo'].'" /></td>
	</tr>			
	<tr>
		<td>Основное описание товара на 3-м уровне:</td>
		<td><textarea name="main_descr" cols="100" rows="10">'.htmlspecialchars($mi['main_descr']).'</textarea></td>
	</tr>
	<tr>
		<td>Адрес картинки-батарейки:</td>
		<td><input size="100" type="text" name="bat_pic" value="'.$mi['bat_pic'].'" /></td>
	</tr>			
	<tr>
		<td>Описание батарейки на 3-м уровне:</td>
		<td><textarea name="but_descr" cols="100" rows="5">'.htmlspecialchars($mi['but_descr']).'</textarea></td>
	</tr>			
	<tr>
		<td>Доп. картинки для 3-го уровня (выводится только 5 шт) адреса ОТ КОРНЯ:</td>
		<td>
				<table border="1" cellspacing="0">
					<tr>
						<td>большая</td>
						<td>маленькая</td>
						<td>ALT (для маленькой)</td>
						<td>TITLE (для большой)</td>
					</tr>';
	$r_num=0;
	$r_act_num=0;
	while ($dop_ph=$result_dop_ph->fetch_assoc()) {
		$r_num+=1;
		$r_act_num+=1;
		echo'		<tr>
						<td><input size="30" type="text" name="big_'.$r_num.'" value="'.$dop_ph['big'].'" /></td>
						<td><input size="30" type="text" name="small_'.$r_num.'" value="'.$dop_ph['small'].'" /></td>
						<td><input size="30" type="text" name="alt_'.$r_num.'" value="'.htmlspecialchars($dop_ph['alt']).'" /></td>
						<td><input size="30" type="text" name="title_'.$r_num.'" value="'.htmlspecialchars($dop_ph['title']).'" />
							<input type="hidden" name="dop_id_'.$r_num.'" value="'.$dop_ph['dop_id'].'" />	
							</td>
					</tr>';
	}

	for ($i=1; $i<=5; $i++) {
		$r_num+=1;
			echo'		<tr>
						<td><input size="30" type="text" name="big_'.$r_num.'" value="" /></td>
						<td><input size="30" type="text" name="small_'.$r_num.'" value="" /></td>
						<td><input size="30" type="text" name="alt_'.$r_num.'" value="" /></td>
						<td><input size="30" type="text" name="title_'.$r_num.'" value="" /></td>
					</tr>';
	}
	
echo ' 			</table>
				
				
				</td>
	</tr>
</table>';

if ($mi_num>0) {
	
	echo'			
	Основная категория: <strong>'.$cat_name.'</strong><br />
	Также показывать (в интернет) в следующих категориях:<br />	';
//select additional cathegories
$query_add = "SELECT * FROM multi_web WHERE model_id='$model_id' ORDER BY add_cat_id";
$result_add = $mysqli->query($query_add);
if (!$result_add) {die('Сбой при доступе к базе данных: '.$query_add.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

while ($add_c = $result_add->fetch_assoc()) {
	$cat_aq="SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$add_c['add_cat_id']."' LIMIT 1";
	$result_cat_add = $mysqli->query($cat_aq);
	if (!$result_cat_add) {die('Сбой при доступе к базе данных: '.$cat_aq.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$cat_add_def=$result_cat_add->fetch_assoc();
	
	echo $cat_add_def['rent_cat_name'].', доп. фото:[<i>'.$add_c['l2_pic_add'].'</i>]  
			<input type="hidden" value="'.$add_c['id'].'" id="add_w_'.$add_c['id'].'" />
			<input type="button" value="удалить из показа" onclick="add_w_del(\''.$add_c['id'].'\');" /><br />';
}


//chose tovar cathegory
$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
$result_cats = $mysqli->query($query_cats);
if (!$result_cats) {die('Сбой при доступе к базе данных: '.$query_cats.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

echo 'Добавить:
	<select name="new_add_cat" id="new_add_cat">
		<option value="0">выберите категорию</option>';
while ($cat_names = $result_cats->fetch_assoc()) {
	echo'<option value="'.$cat_names['tovar_rent_cat_id'].'" >'.good_print($cat_names['rent_cat_name']).' ('.$cat_names['tovar_rent_cat_id'].')</option>';
}



echo '
	</select>	адрес картинки 2-го уровня ОТ КОРНЯ (если стандарт - 0)<input size="50" type="text" name="l2_pic_add" value="'.htmlspecialchars($mi['l2_pic_add']).'" />			<br />					';	
}
	
echo'
	<input type="hidden" name="dop_ph_num" value="'.$r_num.'" />	
	<input type="hidden" name="dop_ph_act_num" value="'.$r_act_num.'" />
		';

	if ($mi_num>0) {
		echo '<input type="submit" name="action" value="обновить" />';
	}
	else {
		echo '<input type="submit" name="action" value="сохранить" />';
	}		
		
echo '</form>
		
<form action="model_web.php" method="post" id="add_web_del_f">
	<input type="hidden" name="add_id" id="add_id_del_form" value="" />
	<input type="hidden" name="action" value="add_web_del" />
	<input type="hidden" name="model_id" value="'.$model_id.'">							
</form>
	
		
		
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

?>