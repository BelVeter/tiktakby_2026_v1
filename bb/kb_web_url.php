<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');
// Get the parameter
if (isset($_POST['kb_id'])) $kbId = $_POST['kb_id'];
else $kbId=false;

if (isset($_POST['item_id'])) $itemId = $_POST['item_id'];
else $itemId=false;

$result=false;

$modelWeb=false;
if ($kbId) {
  $kb = \bb\KBron::getById($kbId);

  if ($kb) {
    $item = \bb\classes\tovar::getTovarByInvN($kb->inv_n);
  } else {
    $item = false;
  }
}

if ($itemId){
  $item = \bb\classes\tovar::geTovarById($itemId);
}

if ($item){
  $modelWeb = \bb\classes\ModelWeb::getByModelId($item->getModelId());
}

if ($modelWeb) {
  $result = true;
  $url = $modelWeb->getUrlPageAddress();
} else {
  $result = false;
  $url = '---'; // Or some error message
}


// Send JSON response
header('Content-Type: application/json'); // Important!
echo json_encode([$result, $url]);

// ... your existing PHP code ...
