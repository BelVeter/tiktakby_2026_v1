<?php

namespace bb;

session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

use bb\models;
use bb\classes;

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/tovar.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/phpqrcode/qrlib.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/Office.php');

Base::loginCheck();

$cat_id = 0;
$place = 'all';
$free = 'all';
$qr = 'all';


if (isset($_POST['cat_id'])) {
    $cat_id = Base::GetPost('cat_id');
}
if (isset($_POST['place'])) {
    $place = Base::GetPost('place');
}
if (isset($_POST['free'])) {
    $free = Base::GetPost('free');
}
if (isset($_POST['qr'])) {
    $qr = Base::GetPost('qr');
}

?>

<!DOCTYPE HTML>
<html lang="ru-RU">

<head>
    <title>QR коды</title>
    <meta charset="utf-8">
    <meta name="viewport" content="initial-scale=1.0, width=device-width">
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/css/bootstrap-select.min.css">

    <!-- Latest Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
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

        .gif_c {
            margin: 5px;
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

        .inv_n {
            text-align: center;
        }

        .qr_gif2 {}

        .qr_name_2 {
            font-size: 12px;
            word-break: break-all;
            height: 40px;
            overflow: hidden;
        }

        .qr_lines {
            border-style: solid;
            border-width: 1px 0 1px 0;

            height: 5px;
            margin: 0;
            padding: 0;
        }

        .qr_phone {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
        }

        .qr_operators {
            font-size: 8px;
            text-align: center;
        }

        .site2 {
            font-size: 11px;
            text-align: center;
        }
    </style>

</head>

<body>
    <div class="top_menu" id="thd1">
        <a class="div_item" href="/bb/index.php">На главную</a>
        <?php
        echo '<a class="div_item" href="/bb/pdf.php?cat_id=' . $cat_id . '">PDF</a>';

        ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            'use strict';
            var input = '';
            var pause_time = 1000;
            var last_stroke = 0;

            document.addEventListener('keydown', event => {
                if ((Date.now() - last_stroke) > pause_time) {
                    input = '';
                }
                last_stroke = Date.now();

                var key_pressed = event.key.toLowerCase();
                if (event.keyCode >= 48 && event.keyCode <= 57) {
                    input += key_pressed;
                }

                if (event.keyCode == 13 && input.length > 3) {
                    event.preventDefault();
                    //console.log('enter catch');
                    document.getElementById('item_inv_n').value = input;
                    document.getElementById('inv_n_select_but').click();
                }

                //console.log('l='+input.length);
                //console.log(input);

            });


            console.log('content loaded');
        });

    </script>
    <?php

    $cats = classes\Category::getAllCategories();
    echo '<form action="qrs.php" method="post" id="thd2">
<input type="button" value="скрыть лишнее" onclick="document.getElementById(\'thd1\').style.display=\'none\'; document.getElementById(\'thd2\').style.display=\'none\';" style="position: absolute; left: 350px;"><br>
    <select name="cat_id" onchange="this.form.submit();">
        <option value="0">выберите категорию</option>';
    foreach ($cats as $cat) {
        echo '<option value="' . $cat->id . '" ' . Base::sel_d($cat_id, $cat->id) . '>' . $cat->name . '</option>';
    }
    echo '
    </select><br>
    
    <select name="place" onchange="this.form.submit();">
        <option value="all">все</option>
    ';
    $ofs = models\Office::getAllOffices();
    foreach ($ofs as $of) {
        echo '<option value="' . $of->number . '" ' . Base::sel_d($place, $of->number) . '>' . $of->name_short . '</option>';
    }
    echo '
        
    </select><br>
    
    <select name="free" onchange="this.form.submit();">
        <option value="all" ' . Base::sel_d($free, 'all') . '>все</option>
        <option value="free" ' . Base::sel_d($free, 'free') . '>не на руках</option>
        <option value="not_free" ' . Base::sel_d($free, 'not_free') . '>на руках</option>
    </select><br>
    
    <select name="qr" onchange="this.form.submit();">
        <option value="all" ' . Base::sel_d($qr, 'all') . '>все</option>
        <option value="qr" ' . Base::sel_d($qr, 'qr') . '>с QR</option>
        <option value="no_qr" ' . Base::sel_d($qr, 'no_qr') . '>без QR</option>
    </select>
</form>';

    $params = array();
    $params['place'] = $place;
    $params['free'] = $free;
    $params['qr'] = $qr;

    $tovs = classes\tovar::getTovarsByCategory($cat_id, $params);
    $rez = '';

    if (in_array($cat_id, array(2, 12, 5, 21))) {
        $rez .= '
    <table border="1" style="table-layout: fixed; overflow: hidden; width: 3.8cm; z-index: 3">';
        $n = 0;
        foreach ($tovs as $tov) {
            $n++;

            if (!($n % 2 == 0)) {
                $rez .= '<tr>
                    <td style="width: 1.9cm;">' . $tov->getBarCodeHTML() . '</td> ';
            } else {
                $rez .= '    <td style="width: 1.9cm;">' . $tov->getBarCodeHTML() . '</td>    
                  </tr>';
            }
        }

        if (!$n % 2 == 0) {//if no last element close row manually
            $rez .= '<td></td> </tr>';
        }


        $rez .= '
    </table>
    ';
    } else {
        $rez .= '
    <table border="0" style="table-layout: fixed; overflow: hidden; width: 4.2cm; z-index: 3">';
        $n = 0;
        foreach ($tovs as $tov) {
            $rez .= '<tr style="width: 2.5cm">
                    <td style="width: 2.1cm; position: relative; text-align: center;">' . $tov->getBarCodeHTML1() . '</td> 
                    <td style="width: 2.1cm; position: relative;">' . $tov->getBarCodeHTML2() . '</td>    
                  </tr>';
        }
        $rez .= '
    </table>
    ';
    }


    echo $rez;

    //Base::varDamp($tovs);
    
    ?>




</body>

</html>