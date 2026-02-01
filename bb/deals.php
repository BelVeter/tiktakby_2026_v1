<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных

//------- proverka paroley

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941) {
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
	');
}

//-----------proverka paroley


echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Договоры для курьера</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a> 
	<a class="div_item" href="/bb/dogovor.php">Новый договор</a>
	<a class="div_item" href="/bb/alldeals.php">Все сделки</a>
</div>

';

		$query_dl = "SELECT * FROM deals WHERE deal_status='для курьера' ORDER BY deal_id DESC";
		$result_dl = mysql_query($query_dl);
		if (!$result_dl) die("Сбой при доступе к базе данных: '$query_dl'".mysql_error());
				
		
echo '
<table border="1" cellspacing="0">
<tr>
	<th>id</th>
	<th>Товар</th>
	<th>Период аренды</th>
	<th>Стоимость</th>
	<th>Доставка</th>
	<th>Адрес (доставки)</th>
	<th>ФИО, телефоны</th>
	<th>Доп. инфо</th>
	<th>Действия</th>
</tr>';

while ($dl_def=mysql_fetch_array($result_dl)) {

	$query_cl_def = "SELECT * FROM clients WHERE client_id='".$dl_def['client_id']."'";
		$result_cl_def = mysql_query($query_cl_def);
		if (!$result_cl_def) die("Сбой при доступе к базе данных: '$query_cl_def'".mysql_error());
		$cl_def=mysql_fetch_array($result_cl_def);
	
	
	
	echo'
<tr>
	<td>'.$dl_def['deal_id'].'</td>
	<td>'.$dl_def['tovar'].'</td>
	<td> '.$dl_def['rent_tenor'].' '.$dl_def['step'].'<br />'.date("d.m.Y", $dl_def['start_date']).'--'.date("d.m.Y", $dl_def['return_date']).'</td>
	<td>'.number_format($dl_def['r_to_pay'], 0, ',', ' ').' бел. руб.</td>
	<td>'.($dl_def['delivery']==1 ? 'Да' : 'Нет').' <br /> '.$dl_def['delivery_price'].' бел. руб.</td>
	<td>'.$cl_def['city'].', ул. '.$cl_def['str'].', '.$cl_def['dom'].'-'.$cl_def['kv'].'</td>
	<td>'.$cl_def['family'].' '.$cl_def['name'].' '.$cl_def['otch'].' <br /> '.$cl_def['phone_1'].', '.$cl_def['phone_2'].'</td>
	<td>'.$dl_def['deal_info'].'</td>
	<td>
	<form action="/bb/dogovor.php" method="post">
	<input type="hidden" name="client_id" value="'.$cl_def['client_id'].'" />
	<input type="hidden" name="deal_id" value="'.$dl_def['deal_id'].'" />
	<input type="submit" name="" value="оформить" />
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


?>