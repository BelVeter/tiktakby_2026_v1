<?php
require_once(__DIR__.'/Db.php'); // включаем подключение к базе данных
require_once(__DIR__.'/Base.php'); // включаем подключение к базе данных

require_once (__DIR__.'/models/User.php');
require_once (__DIR__.'/models/KassaOstatok.php');
require_once (__DIR__.'/models/Office.php');

//\bb\KassaOstatok::calculateAndSaveAllUpToday();

$date = new DateTime();
$info=$date->format('H:i:s').' run
';
file_put_contents(__DIR__.'/log_auto.txt', $info, FILE_APPEND);
echo $info;
?>
