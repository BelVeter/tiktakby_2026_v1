<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 02.12.2018
 * Time: 15:58
 */

namespace bb;

use bb\classes\Category;
use bb\classes\DohRash;
use bb\classes\DohRashesAnalisys;
use bb\classes\Model;
use bb\classes\WorkShift;
use bb\classes\WorkShiftMonthReport;
use Classes\Deal;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);


require ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Deal.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/DohRash.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/KBron.php');
require ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');

Base::loginCheck();

echo Base::pageStartB5('Test Page');


/** php-curl and php-json must be installed **/

//function sendRocketSMS($phone, $message)
//{
//
//  $message = array(
//    "username" => "193137666",
//    "password" => md5("4FJyHev5"),
//    "phone"    => $phone,
//    "text"     => $message );
//
//  $messageQuery = http_build_query($message); // returns username=123456789&password=1fa...
//
//  $curl = curl_init();
//
//  curl_setopt($curl, CURLOPT_URL, 'https://api.rocketsms.by/simple/send');
//  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
//  curl_setopt($curl, CURLOPT_POST, true);
//  curl_setopt($curl, CURLOPT_POSTFIELDS, $messageQuery);
//
//  $result = @json_decode(curl_exec($curl), true);
//
//  Base::varDamp($result);
//
//  if ($result && isset($result['id'])) {
//    return "Message has been sent. MessageID=" . $result['id'];
//  } elseif ($result && isset($result['error'])) {
//    return "Error occurred while sending message. ErrorID=" . $result['error'];
//  } else {
//    return "Service error";
//  }
//}
//
//echo "<pre>";
//echo sendRocketSMS("375447680743", "Оставьте нам отзыв, пожалуйста 2 :)");
//echo "</pre>";

$tovs = \bb\classes\tovar::getAllAct();

foreach ($tovs as $t){
  $t->updateExchRate();
}


?>


<?php

//Base::varDamp($rep);

echo Base::pageEndHtmlB5();


?>
