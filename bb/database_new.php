<?php
// подключаемся к базе и указываем правильную кодировку
		require_once ($_SERVER['DOCUMENT_ROOT'].'/dimanay.php');
		//подключаемся к mysqlсерверу
		$mysqli = new mysqli($db_hostname, $db_username, $db_password, $db_database);
		if ($mysqli->connect_error) {
			die('Ошибка соединения с MYSQL сервером: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}
		
		
		// выбор правильной кодировки при работе с БД
		$mysqli->query('set character_set_client="utf8"'); // в какой кодировке получать данные от клиента
		$mysqli->query('set character_set_results="utf8"'); // в какой кодировке получать данные от БД для вывода клиенту
		$mysqli->query('set collation_connection="utf8_general_ci"'); // кодировка в которой будут посылаться служебные команды для сервера
?>