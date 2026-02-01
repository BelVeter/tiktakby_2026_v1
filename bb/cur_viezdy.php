<?php
session_set_cookie_params(28800);
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Delivery.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/DeliveryPage.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Client.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubDeal.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php');


$version = 47;

\bb\Base::loginCheck([0,5,7,-1]);

$dateFilter='today';
$curFilter = 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(count($_POST) > 0) {
        if ($_POST['action'] == 'filter') {
            $dateFilter = $_POST['period'];
            $curFilter = $_POST['cur'];
//            switch ($_POST['period']) {
//                case 'today':
//                    $date = new DateTime();
//                        $date->setTime(0,0,0);
//                    break;
//                case 'tomorrow':
//                    $date = new DateTime();
//                        $date->setTime(0,0,0);
//                        $date->modify("+1 day");
//                    break;
//                case 'yesterday':
//                    $date = new DateTime();
//                        $date->setTime(0,0,0);
//                        $date->modify("-1 day");
//                    break;
//
//            }
        }
    }
    else {
//    data.invN;
//    data.curId;
//    data.delId;
//    data.currentStatus;

        //var_dump(json_decode(file_get_contents("php://input")));
        $rez = json_decode(file_get_contents("php://input"));
        //var_dump($rez);

        if ($rez->action == 'tovarToggle') {

            $v = Delivery::getDeliveryById($rez->delId);

            //var_dump($v);

            $rezOut = new stdClass();

            if ($v->getCurId() != $rez->curId) {
                $rezOut->success = true;
                $rezOut->newStatus = '';//free, my, notmy, ''
                $rezOut->message = 'Состояние доставки устарело, необходимо обновить страницу';
            } elseif ($rez->currentStatus == 'free') { // free

                $v->assignCur(\bb\models\User::getCurrentUser()->id_user);

                $rezOut->success = true;//false
                $rezOut->newStatus = 'my';//free, my, notmy, ''
                $rezOut->curId = \bb\models\User::getCurrentUser()->id_user;
                $rezOut->message = '';

            } elseif ($rez->currentStatus == 'my') { //my

                $v->assignCur(0);

                $rezOut->success = true;//false
                $rezOut->newStatus = 'free';//free, my, notmy, ''
                $rezOut->curId = '0';
                $rezOut->message = '';

            } else {//taken
                $rezOut->success = true;//false
                $rezOut->newStatus = '';//free, my, notmy, ''
                $rezOut->message = 'Доставка закреплена за ' . \bb\models\User::GetUserName($v->getCurId()) . ' !';
            }

            echo json_encode($rezOut);

            exit();
        }
        elseif ($rez->action == 'modal') {
            $rezOut = new stdClass();
            $body = new stdClass();
            //echo $rez->delId;
            if ($rez->delId*1 < 1) {
                $rezOut->success = 0;
                $rezOut->error = 'Не получен айди (суб) сделки';
                echo json_encode($rezOut);
                exit();
            }
            $delivery = Delivery::getDeliveryById($rez->delId);

            $client = \bb\classes\Client::getClientById($delivery->getClientId());
                $body->delid=$rez->delId;
                if ($delivery->getCurId()<1 || $delivery->getCurId() != \bb\models\User::getCurrentUser()->getId()) $body->statusButClass='hide';
                if ($delivery->getStatus()=='done') $body->statusButFailClass='hide';
                    else $body->statusButFailClass='';
                if ($delivery->getStatus()=='fail') $body->statusButDoneClass='hide';
                    else $body->statusButDoneClass='';

                $body->address = $client->getAddressNoCity();
                if (!\bb\Base::isMobileDevise()) {
                    $body->yandexurl = 'https://yandex.ru/maps/?text=' . urlencode($client->getAddressForNavigation());
                }
                else {
                    $body->yandexurl = 'yandexmaps://maps.yandex.com/?text=' . urlencode($client->getAddressForNavigation());
                }
                $body->fio = $client->getFioFull();
                $body->client_info = $client->info;
                $body->phone1 = $client->phonePrint(1);
                $body->phone2 = $client->phonePrint(2);
                    $body->phone1url = 'tel:'.$client->getPhoneNumberToDeal(1);
                    $body->phone2url = 'tel:'.$client->getPhoneNumberToDeal(2);


            $tovar = \bb\classes\tovar::getTovarByInvN($delivery->getInvN());
                $body->tovar_name = $tovar->getFullName();
                $body->img = $tovar->getPicAddress();
            $subDeal = \bb\classes\SubDeal::getByIdAct($delivery->getSubDlId());
                $body->delivery_description = $subDeal->getOperationName();
                $body->activeDealId = $subDeal->getDealId();
                $body->delivery_message = $delivery->getInfo().' '.$delivery->getComments();
                $body->rtopay = number_format($subDeal->r_to_pay,2,',', ' ');
                $body->deltopay = number_format($subDeal->delivery_to_pay,2,',', ' ');
                $body->totaltopay = number_format(($subDeal->delivery_to_pay + $subDeal->r_to_pay),2,',', ' ');
                //$body->rtopay = $subDeal->sub_deal_id;
            $rezOut->success = true;//false
            $rezOut->body = $body;

            echo json_encode($rezOut);
            exit();
        }
        elseif ($rez->action == 'newstatus') {

            $rezOut = new stdClass();
            $rezOut->delId=$rez->delId;


            $d = Delivery::getDeliveryById($rez->delId);
            //var_dump($d);
            switch ($rez->newStatus) {
                case 'done':
                    if ($d->getStatus()=='done') {
                        $d->makeNewAgain();
                        $rezOut->newStatus='new';
                    }
                    else {
                        $d->makeDone();
                        $rezOut->newStatus='done';
                    }
                    break;
                case 'fail':
                    if ($d->getStatus()=='fail') {
                        $d->makeNewAgain();
                        $rezOut->newStatus='new';
                    }
                    else {
                        $d->makeFail();
                        $rezOut->newStatus='fail';
                    }
                    break;
                default:
                    break;
            }

            $rezOut->success = true;

            echo json_encode($rezOut);
            exit();
        }
    }
}


//var_dump($date);

$p = new DeliveryPage($dateFilter, $curFilter);
//\bb\Base::varDamp($p->getCurViezdsFiltered());
//$d1=new DateTime('2022-01-14');
//$d2=new DateTime('2022-02-12');
//echo $d2->diff($d1)->d;
//\bb\Base::varDamp($p->getCurViezdsFiltered());
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="/bb/assets/styles/cur_style.css?v=<?=$version?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap" rel="stylesheet">

    <title>Document</title>
</head>
<body>
<!-- modal -->
<div class="modal">
    <input type="hidden" data-modal="delid" value="">
    <div class="client">
        <div class="address-div">
            <div class="img-logo"><img src="/bb/assets/images/svg/map.svg" alt="map"></div>
            <a href="" data-modal="address">Тут должен быть адрес</a>
            <form class="print-form" method="post" action="/bb/dogovor_new.php">
                <input type="hidden" data-modal="active_deal_id" name="active_deal_id" value="">
                <input type="hidden" name="action" id="action_print" value="распечатать договор" />
                <img class="print-img" src="/bb/assets/images/png/printer.png" alt="принтер" onclick="this.closest('form').submit();">
            </form>
            <button>X</button>
        </div>
        <div class="fio-div">
            <div class="img-logo"><img src="/bb/assets/images/svg/man.svg" alt="man"></div>
            <span data-modal="fio">Фамилия Имя Отчество(14/2)</span>
        </div>
        <div class="info-div">
            <div class="img-logo"><img src="/bb/assets/images/svg/info.svg" alt="info"></div>
            <span data-modal="client_info">Доп. инфо по клиенту</span>
        </div>
    </div>
    <div class="deliv-deal">
        <div class="pic-div">
            <img src="/bb/assets/images/jpg/tov_modal.jpg" data-modal="img">
            <span>720-167</span>
        </div>
        <div class="operation-info">
            <span class="line1" data-modal="tovar_name">Название товара</span>
            <span class="line2" data-modal="delivery_description">Описание операции</span>
            <span class="line3" data-modal="delivery_message">Комментарий по операции</span>
        </div>
        <div class="topay" data-modal="rtopay">20,00</div>
    </div>
    <div class="delivery-status" data-modal="deliverystatus" data-currentstatus="new">
        <img data-modal="deliveryfail" data-newstatus="fail" src="/bb/assets/images/png/fail.png">
        <img data-modal="deliverydone" data-newstatus="done" src="/bb/assets/images/png/done.png">
    </div>
    <div class="final-div">
        <a href="tel:"><img src="/bb/assets/images/jpg/phone.jpg"></a>
        <div class="totals">
            <div class="line1">
                <span>Товары (1 шт)</span>
                <span data-modal="rtopay">11,11</span>
            </div>
            <div class="line2">
                <span>Доставка </span>
                <span data-modal="deltopay">22,22</span>
            </div>
            <div class="line3">
                <span>ИТОГО:</span>
                <span data-modal="totaltopay">33,33</span>
            </div>
        </div>
    </div>
    <div class="modal-phones">
        <a href="tel:" data-modal="phone1">1234567</a>
        <a href="tel:" data-modal="phone2">+37544...</a>
    </div>

</div>
<!-- end of modal -->
<header class="container container-header">
    <form method="post" name="filter_form" class="filter_form" id="filter_form" style="display: none">
        <input type="hidden" name="period" value="<?= $dateFilter ?>">
        <input type="hidden" name="cur" value="<?= $curFilter ?>">
        <input type="hidden" name="action" value="filter">
    </form>
    <div class="date-menu">
        <button>X</button>
        <ul>
            <li data-period="today">Сегодня</li>
            <li data-period="tomorrow">Завтра</li>
            <li data-period="tomorrow++">(пока нераб)Послезавтра+</li>
            <li data-period="yesterday">Вчера</li>
        </ul>
    </div>
    <div class="cur-menu">
        <button>X</button>
        <ul>
            <li data-cur="all">Все выезды</li>
            <li data-cur="free">Свободные выезды</li>
            <li data-cur="my">Мои выезды</li>
            <li data-cur="notmy">Не мои выезды</li>
        </ul>
    </div>
    <div class="header-date">
        <span class="line1"><?= $p->getDayTextForHeader() ?></span>
        <span class="line2"><?= $p->getHeaderDateString() ?></span>
    </div>
    <div class="cur-flower">
        <img src="/bb/assets/images/cur_flower.png" alt="cur_logo">
        <div class="cur-total-taken" data-curtotal="<?= $p->getMyViyezdCount() ?>"><?= $p->getMyViyezdCount() ?></div>
        <div class="cur-total"><?= $p->getTotalViyezdCount() ?></div>
        <div class="cur-total-notmy"><?= $p->getNotMyViyezdCount() ?></div>
        <div class="cur-total-free" data-curfree="<?= $p->getFreeViyezdCount() ?>"><?= $p->getFreeViyezdCount() ?></div>
    </div>
</header>
<main class="container container-main">
    <?php if ($p->getCurViezdsAll()): ?>
        <?php foreach ($p->getCurViezdsFiltered() as $v): ?>
            <div class="cur-line">
                <div class="cur-tovar-container">
                    <div class="tov-item">
                        <img class="<?= ($v->isReturn() ? 'return gray-transparent' : '') ?>" src="<?= $v->getImgUrl() ?>" alt="tov">
                        <span class="inv-n"><?= DeliveryPage::inv_print($v->getInvN()) ?></span>
                    </div>
                    <div class="office-container">
                        <div class="office" style="background-color: <?= $v->getOfficeCollor() ?>; "><span title="<?= \bb\models\Office::getOfficeByNumber($v->getOffice())->getFullName() ?>"><?= $v->getOneOfficeLetterForPage() ?></span></div>
                    <?php if ($v->getAdditionalFreeOffices()): ?>
                        <?php foreach ($v->getAdditionalFreeOffices() as $adof): ?>
                            <div class="dop-office-free" style="background-color: <?= \bb\models\Office::getOfficeByNumber($adof)->getCssColor() ?>"></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="cur-client" data-delid="<?= $v->getId() ?>">
                    <span class="address line1 formodal"><?= $v->getAddressTo() ?></span>
                    <span class="comment line2 formodal"><?= $v->getInfo().' '.$v->getComments(); ?></span>
                </div>
                <div class="cur-action <?= $p->getHeartCssStyleName($v->getCurId(), $v->getStatus()) ?>" data-inv="<?= $v->getInvN() ?>" data-curid="<?= $v->getCurId() ?>" data-delid="<?= $v->getId() ?>"></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="cur-line">
            <div class="cur-client">
                <span class="address line1" style="padding: 20px 0;">Доставки на текущую дату не найдены.</span>
                <span class="comment line2"></span>
            </div>
        </div>
    <?php endif; ?>
    <?php if(\bb\models\User::getCurrentUser()->isDima()): ?>
        <div class="cur-line">
            <div class="cur-client">
                <span class="address line1" style="padding: 20px 0;"><strong>Количество удаленных сделок по доставкам: <?= $p->getDelitedNum() ?></strong></span>
                <span class="comment line2"></span>
            </div>
        </div>
        <?php if(is_array($p->getDelitedDeliveries()) && count($p->getDelitedDeliveries())>0): ?>
            <?php foreach ($p->getDelitedDeliveries() as $v): ?>
                <div class="cur-line">
                    <div class="cur-client">
                        <span class="address line1" style="padding: 20px 0;">Удаленный адрес: <?= $v->getAddressTo() ?><br></span>
                        <span class="comment line2"></span>
                    </div>
                </div>
            <?php endforeach ?>
        <?php endif; ?>
    <?php endif; ?>

</main>
<footer class="cur-footer">
    <ul>
        <li class="menu-gray-transparent">
            <img src="/bb/assets/images/png/cur_profile.png" alt="Cur profile" height="28" width="28">
            <span>Профиль</span>
        </li>
        <li class="menu-gray-transparent">
            <a href="/bb/"><img src="/bb/assets/images/png/car.png" alt="Car" height="28" width="45"></a>
            <span>Авто</span>
        </li>
        <li>
            <img src="/bb/assets/images/png/list.png" alt="List" width="25" height="32">
            <span>Выезды</span>
        </li>
        <li class="menu-gray-transparent">
            <img src="/bb/assets/images/png/kassa.png" alt="Kaccа" width="28" height="28">
            <span>Касса</span>
        </li>
    </ul>
</footer>

<script src="/bb/assets/js/cur.js?v=<?=$version?>"></script>
</body>
</html>


