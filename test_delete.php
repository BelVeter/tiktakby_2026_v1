<?php
$_POST['action'] = 'удалить';
$_POST['order_id'] = '211053';
$_SESSION['user_id'] = 1;
require_once('bb/Base.php');
require_once('bb/Db.php');
require_once('bb/classes/bron.php');

$mysqli = \bb\Db::getInstance()->getConnection();
$br = new \bb\classes\bron();
$br->br_load(211053);
echo "Loaded: " . $br->order_id . "\n";
$br->arch_copy();
echo "Arch copied.\n";
$br->del_br();
echo "Deleted.\n";
