<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 02.12.2018
 * Time: 15:58
 */

use bb\Base;
use bb\classes\LastRent;
use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\Db;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';

Base::loginCheck();

echo Base::PageStartAdvansed('Test Page');

$from = new DateTime('2023-01-01');
$to = new DateTime('2023-01-31');

$from2 = clone $from;
    $from2->modify('-1 year');
$to2 = clone $to;
    $to2->modify('-1 year');

$rez1 = \bb\classes\Deal::getSalesCategorySplit($from, $to);
$rez2 = \bb\classes\Deal::getSalesCategorySplit($from2, $to2);


Base::varDamp($rez1);
echo '---';
Base::varDamp($rez2);

echo Base::PageEndHTML();


?>
