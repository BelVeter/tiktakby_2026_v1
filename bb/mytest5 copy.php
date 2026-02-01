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

Base::varDamp($from);
Base::varDamp($to);

echo Base::PageEndHTML();


?>
