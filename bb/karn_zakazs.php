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
		</head>
		<body>
';


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
	</head>

<body>
');
}

//-----------proverka paroley

if (isset($_POST['action'])) {
	foreach ($_POST as $key => $value) {
		$$key = get_post($key);
	}

	switch ($action) {

		case 'сегодня':

			$date_s=strtotime(date("Y-m-d", time()));

			$query_cl = "SELECT * FROM clients WHERE status='web' AND cr_time >= '$date_s' ORDER BY cr_time DESC";
			$result_cl = mysql_query($query_cl);
			if (!$result_cl) die("Сбой при доступе к базе данных: '$query_cl'".mysql_error());
				
			break;

		case 'вчера':

			$today=getdate(time());
			$from_date=mktime(0, 0, 0, $today['mon'], ($today['mday']-1), $today['year']);
			
			$to_date=strtotime(date("Y-m-d", time()));

			$query_cl = "SELECT * FROM clients WHERE status='web' AND cr_time >= '$from_date' AND cr_time <= '$to_date' ORDER BY cr_time DESC";
			$result_cl = mysql_query($query_cl);
			if (!$result_cl) die("Сбой при доступе к базе данных: '$query_cl'".mysql_error());


			break;

		case 'показать':

			$from_date=strtotime($from_date);
			$to_date=strtotime($to_date);

			$query_cl = "SELECT * FROM clients WHERE status='web' AND cr_time >= '$from_date' AND cr_time <= '$to_date' ORDER BY cr_time DESC";
			$result_cl = mysql_query($query_cl);
			if (!$result_cl) die("Сбой при доступе к базе данных: '$query_cl'".mysql_error());
			
			break;

			case 'удалить':

				$query_del = "DELETE FROM clients WHERE client_id='$client_id'";
				$result_del = mysql_query($query_del);
				if (!$result_del) {die("Сбой при доступе к базе данных (удаление клиента не случилось :(): '$query_del'".mysql_error());}
				
				
			break;
			
			case 'поиск по брони':
			
				$query_cl = "SELECT * FROM clients WHERE status='web' AND info LIKE '%".$bron."%' ORDER BY cr_time DESC";
				$result_cl = mysql_query($query_cl);
				if (!$result_cl) die("Сбой при доступе к базе данных: '$query_cl'".mysql_error());
				
			
			
			break;
				

	}//end of switch
}//end of if


echo '
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>		
		
		
<form name="dates" method="post" action="karn_zakazs.php">
<input type="submit" name="action" value="сегодня" /> <input type="submit" name="action" value="вчера" /> За период:
c <input type="date" name="from_date" /> по <input type="date" name="to_date" /> <input type="submit" name="action" value="показать" />
</form>
		
<form name="dates" method="post" action="karn_zakazs.php">
<input type="text" name="bron" /> <input type="submit" name="action" value="поиск по брони" />
</form>

<table border="1" cellspacing="0">
<tr>
	<th>Время ввода</th>
	<th>№ брони</th>
	<th>Информация о клиенте</th>
	<th>Телефоны</th>
	<th>Действия</th>
</tr>
		
		';
if (isset($action) && $action!="удалить") {
while ($cl_def=mysql_fetch_array($result_cl)) {
	echo '
<tr>
	<td>'.date("d-m-Y", $cl_def['cr_time']).' <br /> <i>'.date("H:i", $cl_def['cr_time']).'</i> </td>
	<td>'.$cl_def['info'].'</td>
	<td>
		<strong>'.$cl_def['family'].' '.$cl_def['name'].' '.$cl_def['otch'].' </strong><br />
		<strong>Адрес:</strong> ул. '.$cl_def['str'].', дом '.$cl_def['dom'].', кв. '.$cl_def['kv'].', г. '.$cl_def['city'].'<br />
		<strong>Прописан:</strong> ул. '.$cl_def['reg_str'].', дом '.$cl_def['reg_dom'].', кв. '.$cl_def['reg_kv'].', г. '.$cl_def['reg_city'].'<br />
		<strong>Паспорт:</strong> '.$cl_def['pas_n'].', выдан '.date("d-m-Y", $cl_def['pas_date']).' '.$cl_def['pas_who'].'  
			</td>
	<td>тел1: (+375-)'.phone_print($cl_def['phone_1']).' <br />
		тел2: (+375-)'.phone_print($cl_def['phone_2']).' <br /></td>

	<td>
		<form method="post" action="dogovor.php" style="display:inline-block;">
			<input type="hidden" name="client_id" value="'.$cl_def['client_id'].'">
			<input type="submit" name="action" value="обработать">
		</form>
		
		<form method="post" action="karn_zakazs.php" style="display:inline-block;">
			<input type="hidden" name="client_id" value="'.$cl_def['client_id'].'">
			<input type="submit" name="action" value="удалить">
		</form>
				
				</td>
			
</tr>
			
			
			';
}
}



echo '</table> </body> </html>';
		




function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}


function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}


function phone_print ($ph) {
	if ($ph=='') {return '';}

	$dl=strlen($ph);

	if ($dl<7) {return $ph;}

	$dl>7 ? $dl_to=$dl-7 : $dl_to=0;
	$ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
	return $ph_out;
}
	 



?>