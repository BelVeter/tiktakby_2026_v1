<?php

namespace bb;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

use bb\models;
use bb\classes;

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/tovar.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/phpqrcode/qrlib.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/tcpdf/tcpdf.php');

Base::loginCheck();

$rez='
<!DOCTYPE HTML>
<html lang="ru-RU">
<head>
    <title> Bootstrap-select плагин jQuery с Bootstrap 4 не работает - html | Qaru</title>
    <meta charset="utf-8">
    <meta name="viewport" content="initial-scale=1.0, width=device-width">
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/css/bootstrap-select.min.css">

    <!-- Latest Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <style>
        .div_cont {
            margin: 0;
            padding: 0;
            position: relative;
            width: 2cm;

            text-align: center;
            float: left;
        }
        .item_number {
            margin: 26px 0 0 0;
            padding: 0;
            font-size: 30px;
            text-align: center;
            z-index: 5;
            position: relative;
        }
        .site {
            margin: 0;
            padding: 0;
            font-size: 10px;
            z-index: 5;
            position: relative;
        }
        .name {
            margin: 0;
            padding: 0;
            font-size: 10px;
            text-align: center;
            position: relative;
            z-index: 5;
            top: -8px;
        }
        .gif_c{
            margin: 0;
            padding: 0;
            z-index: 1;
            position: relative;
            left: -3px;
            top: -3px;
        }
        table tr td {
            overflow: hidden;
            vertical-align: top;
        }
    </style>

</head>
<body>';

if (isset($_GET['cat_id'])) {
    $cat_id= Base::getGet('cat_id');
}
else {
    $cat_id=0;
}

$cats=classes\Category::getAllCategories();

$tovs=classes\tovar::getTovarsByCategory($cat_id);

$rez= '
<table border="1" style="table-layout: fixed; overflow: hidden; width: 3.8cm; z-index: 3">';
$n=0;
foreach ($tovs as $tov) {
    $n++;

    if (!($n%2==0)){
        $rez.= '<tr>
                <td style="width: 1.9cm;">'.$tov->getBarCodeHTML().'</td> ';
    }
    else {
        $rez.= '    <td style="width: 1.9cm;">'.$tov->getBarCodeHTML().'</td>    
              </tr>';
    }
}

if (!$n%2==0) {//if no last element close row manually
    $rez.= '<td></td> </tr>';
}


$rez.= '
</table>
';

$rez.='
    </body>
    </html>
';

$pdf= new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->AddPage();
$pdf->writeHTML($rez);
$pdf->lastPage();
$pdf->Output('temp.pdf', 'D');

//Base::varDamp($tovs);

?>







?>
