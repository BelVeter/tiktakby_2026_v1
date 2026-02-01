<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Category.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Razdel.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubRazdel.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Client.php'); //


\bb\Base::loginCheck();

$mysqli = \bb\Db::getInstance()->getConnection();

if (isset($_POST['action'])) {
  $action = $mysqli->real_escape_string($_POST['action']);

  switch ($action){
    case 'get_subrazdels_options':

      $razdelId = $mysqli->real_escape_string($_POST['add_razdel']);
      $subRazdels = \bb\classes\SubRazdel::getSubrazdelsForRazdelId($razdelId);
      if (!$subRazdels || count($subRazdels)<1) {
        $options = '<option value="0">вариантов нет :(</option>';
      }
      else {
        $options = '<option value="0">выберите подраздел</option>';

        foreach ($subRazdels as $sr) {
          $options .= '<option value="' . $sr->getIdSubRazdel() . '">' . $sr->getNameSubRazdelText() . '</option>';
        }
      }

      $rez = new \stdClass();

      $rez->result='ok';
      $rez->options = $options;

      echo json_encode($rez);

      break;
    case 'get_cat_options':

      $subRazdelId = $mysqli->real_escape_string($_POST['add_subrazdel']);
      $cats = \bb\classes\Category::getCategoriesForSubRazdel($subRazdelId);
      if (!$cats || count($cats)<1) {
        $options = '<option value="0">вариантов нет :(</option>';
      }
      else {
        $options = '<option value="0">выберите категорию</option>';

        foreach ($cats as $cat) {
          $options .= '<option value="' . $cat->getId() . '">' . $cat->getName() . '</option>';
        }
      }

      $rez = new \stdClass();

      $rez->result='ok';
      $rez->options = $options;

      echo json_encode($rez);

      break;
  }
}


?>
