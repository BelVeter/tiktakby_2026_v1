<?php
session_start();

mail("info@tiktak.by", "Dima Test", "Тестовы текст от Димы", "Content-type: text/html; charset=UTF-8 \r\n"); 

?>
