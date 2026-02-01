<?php
use bb\Base;

session_start();

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //

$mysqli = \bb\Db::getInstance()->getConnection();

$t = floatval(Base::getGet('t'));  // Use floatval() for conversion
$h = floatval(Base::getGet('h'));  // Use floatval() for conversion

if (!is_numeric($t) || !is_numeric($h)) {  // Input validation
  echo 'Invalid numeric values provided.';
  exit; // Stop further execution to prevent errors
}

if (!$t || !$h){
  echo 'no values provided';
}
$query = "INSERT INTO t SET `t`='$t', `h`='$h', `time`='".(time())."'";
$result = $mysqli->query($query);
if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
