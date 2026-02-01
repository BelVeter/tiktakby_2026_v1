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

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}



echo '

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">

<head>

<link href="/bb/stile.css" rel="stylesheet" type="text/css" />

<style>



</style>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

</head>

<title>База Кристины.</title>

<body>



<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: '.$_SESSION['office'].'</form> </div>

<div id="zv_div"></div>



<div class="top_menu">

	<a class="div_item" href="/bb/index.php">На главную</a>

	<a class="div_item" href="/bb/rent_deals_all.php">Все сделки (новые)</a>

	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>

	<a class="div_item" href="/bb/rent_orders.php">Брони</a>

</div>

';

require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам



//Проверка входящей информации

//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";

//	foreach ($_POST as $key => $value) {

//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";

//	}

//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";



//основной запрос информации о товаре

$query_k = "SELECT * FROM kassas WHERE `channel`='$item_place' AND kassa='k1' AND acc_date<=$vchera ORDER BY cr_when DESC";
$result_k = $mysqli->query($query_k);
if (!$result_k) {die('Сбой при доступе к базе данных: '.$query_k.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


echo '
<table border="1" cellspacing="0" style="background-color:#AFDC7E; display:block; float:left; margin: 0 20px;" id="stats2">
	<tr>
		<th>id</th>
		<th>acc_date</th>
		<th>канал</th>
		<th>касса</th>
		<th>вх остаток</th>
		<th>выручка</th>
		<th>расходы</th>
		<th>исх остаток</th>
		<th>время создания</th>
</tr>
		
';

while ($kk = $result_k->fetch_assoc()) {
	echo '
<tr>
	<td>'.$kk['k_id'].'</td>		
	<td>'.$kk['acc_date'].'</td>
	<td>'.$kk['chanel'].'</td>		
			
			
</tr>
			
			
			
			
			';
	
}


echo '</table>';

?>