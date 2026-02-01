<?php
session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных

//------- proverka paroley

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941) {
	die('	
	<form action="index.php" method="post">
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>');
}

//-----------proverka paroley

$action='';

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

if ($action=='zv_check') {
$query_zv = "SELECT * FROM zvonki WHERE `status`='new'";
$result_zv = mysql_query($query_zv);
if (!$result_zv) die("Сбой при доступе к базе данных: '$query_zv'".mysql_error());
$zv_n=mysql_num_rows($result_zv);
if ($zv_n>=0) {
	echo $zv_n;
	die();
}
else {
	echo '0';
	die();
}
}//end of action if

if ($action=='звонок сделан') {
	
	$query_cl_upd = "UPDATE zvonki SET `status`='done', react_time='".time()."', person_id='".$_SESSION['user_id']."' WHERE zv_id='$zv_id'";
	if (!mysql_query($query_cl_upd, $db_server)) {
		echo "Сбой при вставке данных: '$query_cl_upd' <br />".mysql_error()."<br /><br />";
	}
	
}


echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Звонки</title>
<body>
		
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/zv_ch.php">Звонки</a>
	<a class="div_item" href="/bb/rent_deals_all.php">Все сделки (новые)</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
</div>

';

	$query_zv = "SELECT * FROM primerki ORDER BY `pr_time` cr_time DESC";
	$result_zv = mysql_query($query_zv);
	if (!$result_zv) die("Сбой при доступе к базе данных: '$query_zv'".mysql_error());	

echo '<table border="1" cellspacing="0">
		<tr>
			<th scope="col" style="width:70px;">дата, время заказа</th>
			<th scope="col" style="width:160px;">Детали звонка</th>
			<th scope="col" style="width:120px;">Статус</th>
			<th scope="col" style="width:160px;">Действия</th>
		</tr>';

while ($zv=mysql_fetch_array($result_zv)) {
	
	echo '
		<tr '.($zv['status']=='new' ? ($zv['tema']=='примерка' ? 'style="background-color:pink;"' : 'style="background-color:yellow;"') : '').'>
			<td>'.date("d-m-y", $zv['cr_time']).'<br />'.date("H:i", $zv['cr_time']).'</td>
			<td>'.$zv['tema'].'<br />Имя: <strong>'.$zv['z_name'].'</strong>, Телефон: ('.$zv['operator'].') - '.$zv['phone'].' <br /> Доп. информация: '.$zv['info'].'</td>
			<td>'.($zv['status']=='new' ? 'не обработан' : 'обработан '.date("d-m-y", $zv['react_time']).'<br />'.date("H:i", $zv['react_time']).' ').'</td>
			<td>
					<form method="post" action="zv_ch.php">
						<input type="hidden" name="zv_id" value="'.$zv['zv_id'].'" />
					'.($zv['status']=='new' ? '<input type="submit" name="action" value="звонок сделан" />' : '').'
					</form>
					
					</td>
		</tr>
			
			';
}
 



function get_post($var)
{
	GLOBAL $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}

?>