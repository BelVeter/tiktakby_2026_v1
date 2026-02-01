<?php
// подключаемся к базе и указываем правильную кодировку
		require_once ($_SERVER['DOCUMENT_ROOT'].'/dimanay.php');
		//подключаемся к mysqlсерверу
		$db_server = mysql_connect($db_hostname,$db_username,$db_password);
		if (!$db_server) die("Невозможно подключиться к MYsql: ".mysql_error());
		//выбираем базу данных
		mysql_select_db($db_database)
		or die("Невозможно выбрать базу данных: ".mysql_error());
		
		// выбор правильной кодировки при работе с БД
		mysql_query('set character_set_client="utf8"'); // в какой кодировке получать данные от клиента
		mysql_query('set character_set_results="utf8"'); // в какой кодировке получать данные от БД для вывода клиенту
		mysql_query('set collation_connection="utf8_general_ci"'); // кодировка в которой будут посылаться служебные команды для сервера
?>