<?php
	$this->db_hostname = '127.0.0.1';
	$this->db_database = 'tiktak';
	$this->db_username = 'veter';
	$this->db_password = 'mb8941';
	
	//подключаемся к mysqlсерверу
	$this->mysqli = new mysqli($this->db_hostname, $this->db_username, $this->db_password, $this->db_database);
	if ($this->mysqli->connect_error) {
		die('Ошибка соединения с MYSQL сервером: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	}
	
	// выбор правильной кодировки при работе с БД
	$this->mysqli->query('set character_set_client="utf8"'); // в какой кодировке получать данные от клиента
	$this->mysqli->query('set character_set_results="utf8"'); // в какой кодировке получать данные от БД для вывода клиенту
	$this->mysqli->query('set collation_connection="utf8_general_ci"'); // кодировка в которой будут посылаться служебные команды для сервера

?>
