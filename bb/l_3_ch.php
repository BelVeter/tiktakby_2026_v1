<?php
session_start();


require_once __DIR__ . '/auth_guard.php';
foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

if ($cat_id=='2') {
	require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/novogod_l3_template.php'); // включаем форму страницы карнавала
}
else {
	require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/l_3_template.php'); // включаем форму страницы
}

function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}
?>