<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Deal.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubDeal.php');
require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

echo \bb\Base::pageStartB5();
echo \bb\Base::loginCheck();
//\bb\classes\Deal::archiveFullDeal(96269);

$today = new DateTime();
$stop = new DateTime('2000-01-01');

while ($today>$stop){
  echo $today->format("Y-m-d").': '.number_format((\bb\classes\tovar::getAvgAgeForCats([],$today)/365),1,',',' ').'<br>';
  $today->modify('-1 year');
}

?>

<?php

echo \bb\Base::pageEndHtmlB5();
?>
