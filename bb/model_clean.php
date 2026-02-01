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
$in_level= array(3,5,7);

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level))) {
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

echo '
<table border="1" cellspacing="0" class="rent_all_table">
<tr>
	<th class="rent_all_t_cat">Категория</th>
	<th class="rent_all_t_prod">Произв/продавец</th>
	<th class="rent_all_t_mod">Модель</th>
	<th class="rent_all_t_col">Цвет</th>
	<th class="rent_all_t_col">Комплектация</th>
	<th class="rent_all_t_size">кол-во товаров акт</th>
	<th class="rent_all_t_size">кол-во товаров архив</th>    
</tr>		
		
		';



$query_mod = "SELECT * FROM tovar_rent ORDER BY tovar_rent_cat_id, producer, model, color";
$result_mod = mysql_query($query_mod);
if (!$result_mod) die("Сбой при доступе к базе данных: '$query_mod'".mysql_error());
$mod_num=mysql_num_rows($result_mod);

$prod_p='';
$model_p='';
$color_p='';

while ($mod=mysql_fetch_array($result_mod)) {
	
	$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id=".$mod['tovar_rent_cat_id']." LIMIT 0,1";
	$result_cat = mysql_query($query_cat);
	if (!$result_cat) die("Сбой при доступе к базе данных: '$query_cat'".mysql_error());
	$cat=mysql_fetch_array($result_cat);
	
	$query_items = "SELECT * FROM tovar_rent_items WHERE model_id='".$mod['tovar_rent_id']."'";
	$result_items = mysql_query($query_items);
	if (!$result_items) die("Сбой при доступе к базе данных: '$query_items'".mysql_error());
	$num1=mysql_num_rows($result_items);
	
	$query_items = "SELECT * FROM tovar_rent_items_arch WHERE model_id='".$mod['tovar_rent_id']."'";
	$result_items = mysql_query($query_items);
	if (!$result_items) die("Сбой при доступе к базе данных: '$query_items'".mysql_error());
	$num2=mysql_num_rows($result_items);
	
echo'
<tr>
	<td>'.$cat['rent_cat_name'].'</td>
	<td '.($mod['producer']==$prod_p ? 'style="background-color:yellow"' : '').'>'.$mod['producer'].'</td>		
	<td '.($mod['model']==$model_p ? 'style="background-color:yellow"' : '').'>'.$mod['model'].' ('.$mod['tovar_rent_id'].')</td>
	<td '.($mod['color']==$color_p? 'style="background-color:yellow"' : '').'>'.$mod['color'].'</td>
	<td>'.$mod['set'].'</td>
	<td>'.$num1.'</td>
	<td>'.$num2.'</td>
</tr>			
			
			';

$prod_p=$mod['producer'];
$model_p=$mod['model'];
$color_p=$mod['color'];
	
	
}
echo '</table>';





?>