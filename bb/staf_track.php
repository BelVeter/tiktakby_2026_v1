<?php

use bb\Base;
use bb\models\User;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');

echo '

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>BB: Входы-выходы</title>
<link href="stile.css" rel="stylesheet" type="text/css" />
';
$mysqli = \bb\Db::getInstance()->getConnection();

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

	<form action="/bb/index.php" method="post">
		Офис:<select name="of_select" id="of_select">
				<option value="0">не выбран</option>
				<option value="1">Машерова</option>
				<option value="2">Ложинская</option>
			</select><br />
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>
	</head>

<body>
');
}

//-----------proverka paroley



$i_from_date=date("Y-m-d", time());
$i_to_date=date("Y-m-d", time());
$user_id=0;
$of_srch=0;
$type_srch2=0;

//echo Base::PostCheck();

foreach ($_POST as $key => $value) {
	$$key = Base::GetPost($key);
}

//echo $i_from_date.'---'.$i_to_date;

$i_from_date=strtotime($i_from_date);
$i_to_date=strtotime($i_to_date)+24*60*60-1;

$users = User::getUsers();

echo '
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>




<form name="srch_form" method="post" id="srch_form" action="staf_track.php">
	За период:
		c <input type="date" name="i_from_date" id="i_from_date" value="'.date("Y-m-d", $i_from_date).'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.date("Y-m-d", $i_to_date).'" /> <input type="submit" name="action" value="показать" onclick="" /><br />
</form>


<table border="1" cellspacing="0">
<tr>
	<th style="width:60px;">дата</th>
	<th style="width:60px;">время</th>
	<th style="width:60px;">тип
	        <select form="srch_form" name="type_srch2" id="type_srch2" onchange="document.getElementById(\'srch_form\').submit();">
					<option value="0" '.sel_d($type_srch2, "0").'>все</option>
					<option value="login" '.sel_d($type_srch2, "login").'>Вход</option>
					<option value="logout" '.sel_d($type_srch2, "logout").'>Выход</option>

			</select>
	    </th>
	<th style="width:60px;">сотрудник
				<select form="srch_form" name="user_id" id="user_id" onchange="document.getElementById(\'srch_form\').submit();">
					<option value="0" '.sel_d($user_id, '0').'>все</option>
					';
			  foreach ($users as $u){
          echo '<option value="'.$u->getId().'" '.sel_d($user_id, $u->getId()).'>'.$u->getShortName().'</option>';
        }
echo '

				</select>



				</th>
    <th style="width:60px; font-weight:bold;">office
        <select form="srch_form" name="of_srch" id="of_srch" onchange="document.getElementById(\'srch_form\').submit();">
					<option value="0" '.sel_d($of_srch, '0').'>все</option>
					<option value="1" '.sel_d($of_srch, '1').'>1</option>
					<option value="2" '.sel_d($of_srch, '2').'>2</option>
					<option value="3" '.sel_d($of_srch, '3').'>3</option>

		</select>
        </th>
	<th style="width:60px; font-weight:bold;">ip</th>
</tr>

		';

if ($user_id<1) {
	$srch="AND logpas_id NOT IN ('2', '3', '5') ";
}
else {
	$srch="AND logpas_id='$user_id' ";
}

if ($of_srch!=0){
    $srch.="AND office='$of_srch'";
}

if ((string)$type_srch2!="0"){
    $srch.="AND `type`='$type_srch2'";
}


//формируем список сделок-карнавалов
	$query_lpt = "SELECT * FROM logpass_track WHERE l_time BETWEEN '".$i_from_date."' AND '".$i_to_date."' ".$srch."ORDER BY l_time";
    echo $query_lpt;
	$result_lpt = $mysqli->query($query_lpt);
	if (!$result_lpt) {die('Сбой при доступе к базе данных: '.$query_lpt.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}



while ($lpt=$result_lpt->fetch_assoc()) {

	echo'
	<tr>
		<td>'.date("d.m.Y", $lpt['l_time']).'</td>
		<td>'.date("H:i", $lpt['l_time']).'</td>
		<td>'.$lpt['type'].'</td>
		<td>'.User::getUserById($lpt['logpas_id'])->user_name.'</td>
		<td>'.$lpt['office'].'</td>
		<td>'.$lpt['ip_log'].'</td>
	</tr>

';
	}//end of while

echo '</table>';


function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}

function user_name ($id) {
	switch ($id) {
		case '1':
			return 'тестовый пользователь';
			break;

		case '2':
			return 'Кристина';
			break;

		case '3':
			return 'Дима';
			break;

		case '4':
			return 'Андрей';
			break;

		case '5':
			return 'Аня';
			break;

		case '6':
			return 'Денис';
			break;

		case '9':
			return 'Света';
			break;
		case '11':
			return 'Артем';
			break;
		case '12':
			return 'Алексей';
			break;
		case '13':
			return 'Татьяна';
			break;
		default:
			return 'ХЗ';
			break;
	}
}



function sel_d($value, $pattern) {
    //echo '!!!'.$value.'-'.$pattern.'-'.($value==$pattern).'<br>';
	if ((string)$value==(string)$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}

?>
