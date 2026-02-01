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
	<title>јвторизаци€</title>
	<body>
	
	<form action="index.php" method="post">
		Ћогин:<input type="text" value="" name="login" /><br />
		ѕароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>
	');
}

//-----------proverka paroley




		$query_cl_def = "SELECT * FROM clients";
		$result_cl_def = mysql_query($query_cl_def);
		if (!$result_cl_def) die("—бой при доступе к базе данных: '$query_cl_def'".mysql_error());
		
		
		while ($cl_def=mysql_fetch_array($result_cl_def)) {
			
			$family=mb_convert_case($cl_def['family'], MB_CASE_TITLE, 'UTF-8');
			$name=mb_convert_case($cl_def['name'], MB_CASE_TITLE, 'UTF-8');
			$otch=mb_convert_case($cl_def['otch'], MB_CASE_TITLE, 'UTF-8');
			$city=mb_convert_case($cl_def['city'], MB_CASE_TITLE, 'UTF-8');
			$str=mb_convert_case($cl_def['str'], MB_CASE_TITLE, 'UTF-8');
			$reg_city=mb_convert_case($cl_def['reg_city'], MB_CASE_TITLE, 'UTF-8');
			$reg_str=mb_convert_case($cl_def['reg_str'], MB_CASE_TITLE, 'UTF-8');
			$client_id=$cl_def['client_id'];
			
			$query_cl_upd = "UPDATE clients SET family='$family', name='$name', otch='$otch', city='$city', str='$str', reg_city='$reg_city', reg_str='$reg_str' WHERE client_id='$client_id'";
		   	if (!mysql_query($query_cl_upd, $db_server)) {
			echo "—бой при вставке данных: '$query_cl_upd' <br />".mysql_error()."<br /><br />";
			}
			
			
			
			
			
			
			
		}
	



?>