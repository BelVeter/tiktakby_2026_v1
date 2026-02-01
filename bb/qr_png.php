<?php
namespace bb;

session_start();

//ini_set("display_errors", 1);
//error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/phpqrcode/qrlib.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');

//Base::loginCheck();
$size=2.5;

$param = Base::getGet('text');

if (isset($_GET['size'])) {
    $size = Base::getGet('size');
}
if (isset($_GET['border'])) {
    $border = Base::getGet('border');
}


// we need to be sure ours script does not output anything!!!
// otherwise it will break up PNG binary!

$codeText = $param;

// outputs image directly into browser, as PNG stream
\QRcode::png($codeText, false, QR_ECLEVEL_H, $size, 1);



?>