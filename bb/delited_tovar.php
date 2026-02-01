<?php

use bb\Base;
use bb\classes\Deal;
use bb\classes\DeletedTovarsPageLine;
use bb\Db;
use bb\models\User;

session_start();

ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/UserRole.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/KassaSet.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Kassa.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/LegalEntity.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/DeletedTovarsPageLine.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/tovar.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Deal.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Client.php'); //


echo Base::PageStartAdvansed('Списанный товар');

$to= new DateTime();
$from=clone $to;

    $from->modify("-3 month");

    if (isset($_POST['from'])) {
        $from=new DateTime(Base::GetPost('from'));
    }

    if (isset($_POST['to'])) {
        $to=new DateTime(Base::GetPost('to'));
    }



    $dtp_lines = DeletedTovarsPageLine::getDelTovLines($from, $to);

    //Base::varDamp($dtp_lines);

?>

<nav class="navbar navbar-expand-md navbar-light bg-light">
    <a class="navbar-brand" href="/bb/index.php">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item active">
                <a class="nav-link" href="#" onclick="return false;">Списанный товар<span class="sr-only">(current)</span></a>
            </li>
        </ul>
        <ul class="navbar-nav">
            <li class="nav-item text-center">
                <?php echo Base::officeLoggedInfo2(); ?>
            </li>
            <li class="nav-item text-center">
                <?php echo Base::getLoggedInAndExit(); ?>
            </li>
        </ul>
    </div>
</nav>
<div class="row">
    <div class="col alert-danger h2 text-center" id="zv_div">
        <?
        //Base::PostCheckVarDumpEcho();
        ?>
    </div>
</div>
<div class="row">
    <div class="col">
        <form class="form-inline" method="post" action="delited_tovar.php">
            Товары, списанные в период с <input type="date" name="from" class="form-control" style="margin:5px;" value="<? echo $from->format("Y-m-d")?>"> по <input type="date" name="to" class="form-control" style="margin:5px;" value="<? echo $to->format("Y-m-d")?>">
            <input type="submit" value="показать" class="btn btn-info" style="margin-left: 10px;">
        </form>
    </div>
</div>
<div class="row">
    <table class="table table-bordered">
        <thead>
            <tr class="text-center">
                <th scope="col" class="col-1 align-middle">дата списания</th>
                <th scope="col" class="align-middle">Фото+№</th>
                <th scope="col" class="align-middle">Товар</th>
                <th scope="col" class="align-middle">История</th>
                <th scope="col" class="align-middle">Действия</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th scope="row" class="text-center">01.01.2021<br><span class="font-italic">15:46</span></th>
                <td class="text-center">
                    <span class="font-weight-bold">№ 705-215</span><br>
                    <img class="img-thumbnail rounded" src="../avtokresla/img/avtokreslo_9-36_s.jpg">

                </td>
                <td>Манеж игровой Lorelli: Play Station "Желтые слоны". Цвет: "желтый/серый"</td>
                <td>
                    отправлен на выбытие: 01.12.2019 (08:46) - Инна Васильевна<br>
                    списан (без договора): 02.04.2020 (09:45) - Василий Иванович<br>
                    списан (<a href="#">договор №4585</a>): 02.04.2020 (09:45) - Василий Иванович<br>
                    (Иванов И.И.: всего по договору - 205,45 руб; в день списания - 20,12 руб)
                </td>
                <td><button class="btn btn-warning">Отменить списание</button></td>
            </tr>
        </tbody>
<?php
foreach ($dtp_lines as $dl) {
    echo '
        <tr>
            <th scope="row" class="text-center">'.$dl->getDelDate()->format("d.m.Y").'<br><span class="font-italic">'.$dl->getDelDate()->format("H:i").'</span></th>
            <td class="text-center">
                <span class="font-weight-bold">№'.$dl->getInvN('-').'</span><br>
                <img class="img-thumbnail rounded" src="'.$dl->getPicAddr().'">

            </td>
            <td>'.$dl->getTovarName().'</td>
            <td>
                '.$dl->getDelTypeText().' '.$dl->getDelDate()->format("d.m.Y (H:i)").' - '.User::getUserById($dl->getDelWhoId())->getShortName().'<br>';

                if ($dl->isDealInvolved()) {
                    $deal=Deal::getLastDealByInv($dl->getInvN());
                    $cl = \bb\classes\Client::getClientById($deal->getClient_id());

                    echo 'Клиент: '.$cl->getFioFull().', договор №'.$deal->getDogovorNumber();
                }
                else{
                    echo '<br><br>прописать остальное';
                }

                echo '
                
            </td>
            <td><button class="btn btn-warning">Отменить списание</button></td>
        </tr>
    ';
}





?>

    </table>
</div>


<?php

echo Base::PageEndHTML();
?>
