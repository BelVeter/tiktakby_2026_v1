<?php

use bb\Base;
use bb\classes\LastRent;

session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/LastRent.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Model.php'); //

$inv_n=Base::getGet('inv_n');

$lr=LastRent::getByInvN($inv_n);

$model_id=$lr->model->model_id;

require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/lr_l_3_template.php'); // включаем форму страницы

?>