<?php

namespace bb;
session_start();

ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Client.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/KBron.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/bron.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/TariffModel.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Tariff.php'); // включаем класс

Base::loginCheck();

echo Base::PageStartAdvansed('QR: работа с товаром', 0);
//Base::PostCheckVarDumpEcho();

echo Base::getBarCodeReaderScript('', array('target'=>'/bb/scanner_tovar.php'));
$item_inv_n=0;
$tov=null;
//$item_inv_n=75741;

//$item_inv_n=7523; //Bron
//$item_inv_n=719214;//free
$shift_to='';

if (isset($_POST['item_inv_n'])) {
    $item_inv_n= Base::GetPost('item_inv_n');
}

if ($item_inv_n>0) {
    $tov=new \bb\classes\tovar();
    $tov->item_load($item_inv_n);
    if ($tov->item_id<1) {
        $tov=null;
    }
}

if (isset($_POST['shift_to'])) {
    $shift_to=Base::GetPost('shift_to');

    if ($shift_to=='move_canсel') {
        $tov->moveCancel();
    }
    elseif ($shift_to=='move_accept') {
        $tov->moveAccept();
    }
    elseif ($shift_to>0) {
        $tov->moveTo($shift_to, \bb\models\Office::getCurrentOffice()->number);
    }
}

echo '
<nav class="navbar navbar-expand-lg navbar-light bg-light">
 <!--<a class="navbar-brand" href="/bb/index.php">Главная</a>
 <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
 <span class="navbar-toggler-icon"></span>
 </button>-->

 <div class="collapse navbar-collapse" id="navbarSupportedContent">
     <ul class="navbar-nav mr-auto">
         <li class="nav-item active">
             <a class="nav-link" href="/bb/index.php">Главная <span class="sr-only">(current)</span></a>
         </li>
         <li class="nav-item active">
             <a class="nav-link" href="/bb/kr_baza_new.php">Все товары<span class="sr-only">(current)</span></a>
         </li>
         <!--<li class="nav-item">
            <a class="nav-link" href="#">Link</a>
         </li>
         <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            Dropdown
            </a>
             <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                <a class="dropdown-item" href="#">Action</a>
                <a class="dropdown-item" href="#">Another action</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#">Something else here</a>
            </div>
         </li>-->
         <!--<li class="nav-item">
            <a class="nav-link disabled" href="#">Disabled Link</a>
         </li>-->
     </ul>
 </div>
<!--/.nav-collapse -->
</nav>
<!--/.navbar -->

<div class="jumbotron text-center" style="padding: 0px;margin: 0">
  <h1 style="margin: 0; padding: 0">'.($item_inv_n>0 ? 'Страница товара' : 'Отсканируйте товар.').'
    <form method="post" class="form-inline" action="scanner_tovar.php" style="display: inline-block">
        <input type="text" class="form-control form-control-sm" name="item_inv_n" value="'.$item_inv_n.'">
        <input type="submit" value="submit" style="display: none;">
    </form>
  </h1> 
</div>
<div class="container-fluid">';
if ($tov) {
    echo '
<div class="row">
    <div class="col-sm-2 align-content-center">
        <div class="card h-100 align-content-center">
            <div class="card-body h-100 w-100 align-content-center justify-content-center">
                <img src="' . $tov->getPicAddress() . '" style="width: 100%;">
                ' . $tov->isDirtyText() . '
                ' . $tov->isDirtyPic("position:absolute; top:0; right:0;") . '
            </div>
        </div>
    </div>
    <div class="col-sm-6 align-content-center">
        <div class="card h-100">
            <div class="card-body h-100 w-100">';
    $position_right_px = 35;
    if ($tov->isInMove()) {
        echo '
                        <div style="position: absolute; top: 65px; right: 35px;">' . \bb\models\Office::getOfficeNameByNumber($tov->to_move, 3) . '</div>
                        <img style="float: right; position: relative;" src="' . \bb\models\Office::getOfficePicAddress($tov->to_move) . '" title="' . \bb\models\Office::getOfficeNameByNumber($tov->to_move) . '">
                        <img style="float: right; position: relative;" src="/bb/arrow.jpg">
                        ';
        $position_right_px = 134;
    }

    echo '
            
            <div style="position: absolute; top: 65px; right: ' . $position_right_px . 'px;">' . \bb\models\Office::getOfficeNameByNumber($tov->item_place, 3) . '</div>
            <img style="float: right; position: relative;" src="' . \bb\models\Office::getOfficePicAddress($tov->item_place) . '" title="' . \bb\models\Office::getOfficeNameByNumber($tov->item_place) . '">
                <h3>' . $tov->invNPrint('-') . '</h3>
                <h4>' . $tov->getFullName() . '</h4><br>
                <h5>Комплектность: ' . $tov->getSet() . '</h5>
            </div>
        </div>
    </div>
    <div class="col-sm-2 align-content-center">
        <div class="card h-100">
            <div class="card-body h-100 w-100">
                <h5 style="text-align: center;">' . $tov->getTariffs()->allTariffsText() . '</h5>
            </div>
        </div>
    </div>
    <div class="col-sm-2 align-content-center">
        <div class="card h-100">
            <div class="card-body h-100 w-100">
                <h3 class="text-center" style="color: ' . $tov->getStatusTextColor() . '">' . $tov->getStatusText() . '</h3>
                ';

    if ($tov->status == 'bron') {
        $br = \bron::getFirstBronForInv($tov->item_inv_n);
        echo 'от ' . date("d.m.Y", $br->order_date) . date(" (H:i)", $br->cr_time) . ', по ' . date("d.m.Y", $br->validity);
        echo '<br>' . $br->info;
        echo '
                        <form method="post" action="/bb/rent_orders.php" class="form-inline">
                            <input type="hidden" name="item_inv_n" value="' . $tov->item_inv_n . '">
                            <input class="btn btn-lg btn-outline-info btn-block" type="submit" value="Брони">            
                        </form>
                    ';

    }
    echo '
            </div>
        </div>
    </div>
    
</div> <!-- end of row -->
<div class="row">
    <div class="col-sm-12 text-center">
        
        <form method="post" action="/bb/dogovor_new.php" class="form-inline" style="display: inline-block;">
            <input type="hidden" name="item_inv_n" value="' . $tov->item_inv_n . '">
            <input class="btn btn-lg btn-primary" type="submit" value="К договору">            
        </form>';
    if (!$tov->isRentedOut() && $tov->isAtCurrentOffice()) {
        echo '
        <form method="post" action="/bb/rent_orders.php" class="form-inline" style="display: inline-block;">
            <input type="hidden" name="item_inv_n" value="' . $tov->item_inv_n . '">
            <input type="hidden" name="type2" value="remont">
            <input type="hidden" name="br_2_t" value="remont">
            <input type="hidden" name="action" value="бронь">
            <input class="btn btn-lg btn-secondary" type="submit" value="В ремонт">            
        </form>
        ';
        echo '
        <form method="post" action="/bb/rent_orders.php" class="form-inline" style="display: inline-block;">
            <input type="hidden" name="item_inv_n" value="' . $tov->item_inv_n . '">
            <input type="hidden" name="type2" value="out">
            <input type="hidden" name="br_2_t" value="out">
            <input type="hidden" name="action" value="бронь">
            <input class="btn btn-lg btn-secondary" type="submit" value="На выбытие">            
        </form>
        ';
    }


    if (!$tov->isRentedOut() && ($tov->isAtCurrentOffice() || $tov->to_move == \bb\models\Office::getCurrentOffice()->number)) {
        $office = \bb\models\Office::getCurrentOffice();
        $ofs = \bb\models\Office::getAllOffices();

        echo '
        <form method="post" action="/bb/scanner_tovar.php" class="form-inline" style="display: inline-block; margin: 10px;" onchange="if (confirm(\'Вы уверены, что хотите переместить товар?\')) {this.submit();} else {this.selected=0}">
            <input type="hidden" name="item_inv_n" value="'.$item_inv_n.'">
            <select name="shift_to" class="form-control form-control-lg custom-select-lg alert-info" style="color: #17a2b8;">';

        if ($tov->item_place == $office->number) {
            if ($tov->isVPuti()) {
                echo '<option>в пути: ' . \bb\models\Office::getOfficeNameByNumber($tov->item_place) . '-->' . \bb\models\Office::getOfficeNameByNumber($tov->to_move) . '</option>';
                echo '<option value="move_canсel">отменить статус --в пути--</option>';
            } else {//not v puti = at the office
                echo '<option>Переместить на офис</option>';
                foreach ($ofs as $of) {
                    if ($of->number == $office->number) continue;
                    echo '<option value="' . $of->number . '">' . $of->name_short . '</option>';
                }
            }

        } elseif ($tov->to_move == $office->number) {
            echo '<option>В пути: ' . \bb\models\Office::getOfficeNameByNumber($tov->item_place) . '-->' . \bb\models\Office::getOfficeNameByNumber($tov->to_move) . '</option>';
            echo '<option value="move_accept">принять товар на ' . \bb\models\Office::getOfficeNameByNumber($office->number) . '</option>';
        }
        echo '
            </select>            
        </form>';
    }
    echo '        
    </div><!-- end of col -->
</div><!-- end of row -->

';

    if ($item_inv_n < 0) { //разобраться и убрать

        echo '
    <div class="row">
    
    <div class="col-sm-12">
        ';


        if ($tov->isKarnaval()) {
            $from = new \DateTime();
            $to = new \DateTime();
            $to->modify('+ 3 months');

            $brs = KBron::getBrons($tov->item_inv_n, $from, $to);

            $out = '
    <table border="1">
    <tr style="text-align: center">
        <th>№ брони</th>
        <th>c</th>
        <th>по</th>
        <th>ФИО</th>
        <th>Телефон</th>
    </tr>    
    ';

            foreach ($brs as $br) {
                //var_dump($br);
                $out .= '
        <tr>
            <td>' . $br->br_num . '</td>
            <td>' . $br->from_kb->format("d.m.Y") . '</td>
            <td>' . $br->to_kb->format("d.m.Y") . '</td> 
            <td>' . $br->fio . '</td>
            <td>' . $br->getPhonesText() . '</td>
        </tr>
        ';
            }

            $out .= '</table>';

            echo $out;

        }

        if ($zs = \bron::getBronsForInv($tov->item_inv_n, 'zayavka')) {
            echo '<div class="col-sm-12">';
            foreach ($zs as $z) {
                echo '<div class="alert alert-primary" style="margin: 10px 0;">';
                echo '<h4>';
                echo 'Заявка на товар от ' . date("d.m.Y", $z->order_date) . date(" (H:i)", $z->cr_time) . ', сроком действия по ' . date("d.m.Y", $z->validity);
                echo '<br>' . $z->info;
                echo '</h4>';
                echo '</div>';
            }
            echo '</div>';
        }


        echo '
    </div> <!--end of col -->
    
    </div><!-- end of row -->
    
    ';

    }


}


Base::PageEndHTML();

?>
