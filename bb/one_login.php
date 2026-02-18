<?php

echo '

		
<script language="javascript">

function log_ch() {
		output=true;
	
	if (document.getElementById(\'of_select\').value==0) {

		alert (\'Выберите офис!\');
		output=false;
	}
	return output;
}


</script>
	
		';

isset($_SESSION['level']) ? $_SESSION['level'] = $_SESSION['level'] : $_SESSION['level'] = '';

//списки офисов
$offs = array();
$ips = array();
$off_options = '';

$query_def_of = "SELECT * FROM offices WHERE off_ip='" . $_SERVER['REMOTE_ADDR'] . "'";
$result_def_of = $mysqli->query($query_def_of);
if (!$result_def_of) {
	die('Сбой при доступе к базе данных: ' . $query_def_of . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
$def_srch = $result_def_of->fetch_assoc();
$def_office = $def_srch['number'];



$query_offs = "SELECT * FROM offices ORDER BY number";
$result_offs = $mysqli->query($query_offs);
if (!$result_offs) {
	die('Сбой при доступе к базе данных: ' . $query_offs . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
while ($offs_r = $result_offs->fetch_assoc()) {
	$offs[$offs_r['number']] = $offs_r['name'];
	$ips[$offs_r['number']] = $offs_r['off_ip'];

	$off_options .= '<option value="' . $offs_r['number'] . '" ' . sel_d_log($offs_r['number'], $def_office) . '>' . $offs_r['name'] . '</option>';

}


if (isset($_POST['login'])) {
	$site_log = get_post('login');
	$site_pass = get_post('pass');
	$ip_addr = $_SERVER['REMOTE_ADDR'];

	$query_lp = "SELECT * FROM logpass WHERE log='$site_log' AND pass='$site_pass'";
	$result_lp = $mysqli->query($query_lp);
	if (!$result_lp) {
		die('Сбой при доступе к базе данных: ' . $query_lp . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	}
	$rows_lp = $result_lp->num_rows;
	$lpch = $result_lp->fetch_assoc();


	if ($rows_lp < 1) {
		die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	<body>
	Неверное сочетание логина-пароля. <br /><br />
	<form action="index.php" method="post">
		Офис:<select name="of_select" id="of_select">
				<option value="0">не выбран</option>
				' . $off_options . '
			</select><br />
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" onclick="return log_ch();" />
	</form></body></html>
	');
	}


	if ($lpch['ip_yn'] == 1 && ($ips[$of_select] != 0 && $ip_addr != $ips[$of_select])) {
		die('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	<body>

				Неверный IP адрес. Обратитесь к Кристине.');
	} else {
		$_SESSION['svoi'] = 8941;
		$_SESSION['login'] = $lpch['log'];
		$_SESSION['user_id'] = $lpch['logpass_id'];
		$_SESSION['user_fio'] = $lpch['lp_fio'];
		$_SESSION['level'] = $lpch['level'];
		$_SESSION['office'] = $of_select;

		setcookie('tt_is_logged_in', '1', time() + 86400 * 30, '/');

		$lg_log = "INSERT INTO logpass_track VALUES('', '" . $lpch['logpass_id'] . "', 'login', '" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', '" . $_SESSION['office'] . "')";
		$result_log = $mysqli->query($lg_log);
		if (!$result_log) {
			die('Сбой при доступе к базе данных: ' . $lg_log . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}

	}

	if ($_SESSION['office'] < 1) {

		unset($_COOKIE[session_name()]);
		unset($_COOKIE[session_id()]);
		session_unset();
		session_destroy();

		die('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	<body>

				Не уазан офис. Залогиньтесь еще раз.');
	}
}

isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;

$level_ok = true;

if (isset($in_level)) {
	$level_ok = (in_array($_SESSION['level'], $in_level));
}


if ($_SESSION['svoi'] != 8941 || !$level_ok) {
	die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	<body>
	<form action="index.php" method="post">
		Офис:<select name="of_select" id="of_select">
				<option value="0">не выбран</option>
				' . $off_options . '
			</select><br />
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" onclick="return log_ch();" />
	</form></body></html>
	');
}

//-----------proverka paroley

function sel_d_log($value, $pattern)
{
	if ($value == $pattern) {
		return 'selected="selected"';
	} else {
		return '';
	}
}
?>