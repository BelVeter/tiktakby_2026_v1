<?php

use bb\Base;

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');

if (isset($_GET['aid'])) {
    $_SESSION['aid']=Base::getGet('aid');
}

?>