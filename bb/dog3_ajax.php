<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Category.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Client.php'); //


\bb\Base::loginCheck();

$mysqli = \bb\Db::getInstance()->getConnection();

if (isset($_POST['action'])) {
  $action = $mysqli->real_escape_string($_POST['action']);

  switch ($action){
    case 'client-fio-srch-num':

      $fioStr = $mysqli->real_escape_string($_POST['fio']);
      $rez = new \stdClass();

      $clsNum = \bb\classes\Client::getClientsByFioString($fioStr, true);

      $rez->result='ok';
      $rez->clientsNum = $clsNum;

      echo json_encode($rez);

      break;

    case 'client-phone-srch-num':
      //\bb\Base::varDamp($_POST);

      $phoneStr = $mysqli->real_escape_string($_POST['phone']);
      $phoneStr = \bb\Base::getNumbersOnly($phoneStr);

      $rez = new \stdClass();

      $phNum = \bb\classes\Client::getClientsByPhoneNumber($phoneStr, true);

      $rez->result='ok';
      $rez->Num = $phNum;

      echo json_encode($rez);

      break;

    case 'client-addr-srch-num':
      //\bb\Base::varDamp($_POST);

      $strStr = $mysqli->real_escape_string($_POST['str']);
      $domStr = $mysqli->real_escape_string($_POST['dom']);

      $rez = new \stdClass();

      $addrNum = \bb\classes\Client::getClientsByAddress($strStr, $domStr, true);

      $rez->result='ok';
      $rez->Num = $addrNum;

      echo json_encode($rez);

      break;

    case 'client-all-srch-num':
      //\bb\Base::varDamp($_POST);
      $fioStr = $mysqli->real_escape_string($_POST['fio']);
      $phoneStr = $mysqli->real_escape_string($_POST['phone']);
      $phone = \bb\Base::getNumbersOnly($phoneStr);
      $strStr = $mysqli->real_escape_string($_POST['str']);
      $domStr = $mysqli->real_escape_string($_POST['dom']);

      $rez = new \stdClass();

      $addrNum = \bb\classes\Client::getClientsByComplexSrch($fioStr, $phone, $strStr, $domStr, true);

      $rez->result='ok';
      $rez->Num = $addrNum;

      echo json_encode($rez);

      break;

    case 'client-all-srch-clients':
      //\bb\Base::varDamp($_POST);
      $fioStr = $mysqli->real_escape_string($_POST['fio']);
      $phoneStr = $mysqli->real_escape_string($_POST['phone']);
      $phone = \bb\Base::getNumbersOnly($phoneStr);
      $strStr = $mysqli->real_escape_string($_POST['str']);
      $domStr = $mysqli->real_escape_string($_POST['dom']);

      $rez = new \stdClass();

      $clients = \bb\classes\Client::getClientsByComplexSrch($fioStr, $phone, $strStr, $domStr, false);

      $rez->result='ok';
      $rez->clients = $clients;

      echo json_encode($rez);

      break;
  }
}


?>
