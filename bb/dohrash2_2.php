<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 02.12.2018
 * Time: 15:58
 */

namespace bb;

use bb\classes\DohRash;
use bb\classes\DohRashesAnalisys;
use bb\classes\Model;
use bb\classes\WorkShift;
use bb\classes\WorkShiftMonthReport;
use Classes\Deal;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);


require ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Deal.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/DohRash.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/KBron.php');
require ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');

Base::loginCheck();

echo Base::pageStartB5('Доходы и расходы');
//echo Base::PostCheck();

$year= (new \DateTime())->format("Y");
if (isset($_POST['year'])) $year=$_POST['year'];
$drA = DohRashesAnalisys::getYearDohRashes($year);

//Base::varDamp($drA);





?>
<div class="container-fluid">
  <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
      <div class="navbar-nav">
        <a class="nav-item nav-link" href="/bb/sales_breakdown.php">Динамика выручки</a>
        <a class="nav-item nav-link" href="/bb/cat_analysis.php">Анализ выдач по категориям</a>
        <a class="nav-item nav-link" href="/bb/tovar_report.php">Товары (динамика)</a>
      </div>
    </div>
  </nav>
<div class="row">
  <div class="col">


  </div>

</div>
<table class="table table-hover table-sm" data-year="<?=$year?>">
  <thead>
    <tr>
      <th scope="col" class="text-right">Position
        <form action="" method="post" class="d-inline">
          <input name="year" type="number" min="2000" max="<?= (new \DateTime())->format("Y")?>" value="<?= $year ?>"><input type="submit" value="->">
        </form>
      </th>
      <th scope="col" class="text-right">01</th>
      <th scope="col" class="text-right">02</th>
      <th scope="col" class="text-right">03</th>
      <th scope="col" class="text-right">04</th>
      <th scope="col" class="text-right">05</th>
      <th scope="col" class="text-right">06</th>
      <th scope="col" class="text-right">07</th>
      <th scope="col" class="text-right">08</th>
      <th scope="col" class="text-right">09</th>
      <th scope="col" class="text-right">10</th>
      <th scope="col" class="text-right">11</th>
      <th scope="col" class="text-right">12</th>
      <th scope="col" class="text-right">Итого</th>
    </tr>
  </thead>
  <tbody>
    <tr class="total sales-row">
      <th scope="row">Выручка от проката</th>
      <td class="text-right"><?= $drA->getSlesForMonthString(1) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(2) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(3) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(4) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(5) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(6) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(7) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(8) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(9) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(10) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(11) ?></td>
      <td class="text-right"><?= $drA->getSlesForMonthString(12) ?></td>
      <td class="text-right"><?= number_format($drA->getTotalYearSales(),0,',', ' ') ?></td>
    </tr>
    <tr class="sales non-karnaval">
      <th class="text-right details" scope="row">в т.ч. основная (не карнавал)</th>
        <?php for ($m=1; $m<=12; $m++): ?>
          <td class="text-right"><?= $drA->getNonKarnavalSlesForMonthString($m) ?></td>
        <?php endfor; ?>
      <td class="text-right"><?= number_format($drA->getTotalYearSales()-$drA->getTotalKarnYearSales(),0,',', ' ') ?></td>
    </tr>
    <tr class="sales karnaval">
      <th class="text-right details" scope="row">в т.ч. карнавал</th>
      <?php for ($m=1; $m<=12; $m++): ?>
        <td class="text-right"><?= $drA->getKarnavalSlesForMonthString($m) ?></td>
      <?php endfor; ?>
      <td class="text-right"><?= number_format($drA->getTotalKarnYearSales(),0,',', ' ') ?></td>
    </tr>
    <tr class="total delivery">
      <th class="" scope="row">Оплата за доставку курьеру</th>
      <?php for ($m=1; $m<=12; $m++): ?>
        <td class="text-right"><?= $drA->getDelivPaymentsForMonthString($m) ?></td>
      <?php endfor; ?>
      <td class="text-right"><?= number_format($drA->getTotalDelivYearPayments(),0,',', ' ') ?></td>
    </tr>


    <tr class="total">
      <th scope="row">Всего расходы <input type="button" value="+" class="rash-btn"></th>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(1) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(2) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(3) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(4) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(5) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(6) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(7) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(8) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(9) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(10) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(11) ?></td>
      <td class="text-right"><?= $drA->getRashTotalForMonthString(12) ?></td>
      <td class="text-right"><?= number_format($drA->getRashTotalYear(),0,',', ' ') ?></td>
    </tr>
    <?php foreach (DohRashesAnalisys::getRashKeyItemsArrayCorrected() as $key=>$value): ?>
      <tr class="rash-row d-none" data-type2="<?=$key?>" data-type1="rash">
        <th scope="row"><?= $value ?></th>
        <td class="text-right details" data-month="1" data-to="<?= (((new \DateTime())->setDate($year, 1,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 1) ?></td>
        <td class="text-right details" data-month="2" data-to="<?= (((new \DateTime())->setDate($year, 2,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 2) ?></td>
        <td class="text-right details" data-month="3" data-to="<?= (((new \DateTime())->setDate($year, 3,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 3) ?></td>
        <td class="text-right details" data-month="4" data-to="<?= (((new \DateTime())->setDate($year, 4,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 4) ?></td>
        <td class="text-right details" data-month="5" data-to="<?= (((new \DateTime())->setDate($year, 5,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 5) ?></td>
        <td class="text-right details" data-month="6" data-to="<?= (((new \DateTime())->setDate($year, 6,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 6) ?></td>
        <td class="text-right details" data-month="7" data-to="<?= (((new \DateTime())->setDate($year, 7,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 7) ?></td>
        <td class="text-right details" data-month="8" data-to="<?= (((new \DateTime())->setDate($year, 8,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 8) ?></td>
        <td class="text-right details" data-month="9" data-to="<?= (((new \DateTime())->setDate($year, 9,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 9) ?></td>
        <td class="text-right details" data-month="10" data-to="<?= (((new \DateTime())->setDate($year, 10,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 10) ?></td>
        <td class="text-right details" data-month="11" data-to="<?= (((new \DateTime())->setDate($year, 12,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 11) ?></td>
        <td class="text-right details" data-month="12" data-to="<?= (((new \DateTime())->setDate($year, 12,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getRashForMonthString($key, 12) ?></td>
        <td class="text-right"><?= number_format($drA->getRashForYearTotal($key),0,',', ' ') ?></td>
      </tr>
    <?php endforeach;?>
    <tr class="total" style="background-color: yellow">
      <th scope="row">Операционный результат (прибыль/(-) убыток) <input type="button" value="+" class="own-rez-btn"></th>
      <td class="text-right"><?= $drA->getOperResultForMonthString(1) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(2) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(3) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(4) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(5) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(6) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(7) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(8) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(9) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(10) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(11) ?></td>
      <td class="text-right"><?= $drA->getOperResultForMonthString(12) ?></td>
      <td class="text-right"><?= number_format($drA->getOperResultTotalYear(),0,',', ' ') ?></td>
    </tr>
    <tr class="total hide no-own-zpl-rez" style="background-color: yellow">
      <th scope="row">Операционный результат без зарплат собственников (прибыль/(-) убыток)</th>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(1) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(2) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(3) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(4) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(5) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(6) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(7) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(8) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(9) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(10) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(11) ?></td>
      <td class="text-right"><?= $drA->getOperResultNoOwnersZplForMonthString(12) ?></td>
      <td class="text-right"><?= number_format($drA->getOperResultTotalYear(),0,',', ' ') ?></td>
    </tr>
    <tr class="total">
      <th scope="row">Всего прочие доходы<input type="button" value="+" class="doh-btn"></th>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(1) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(2) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(3) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(4) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(5) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(6) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(7) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(8) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(9) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(10) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(11) ?></td>
      <td class="text-right"><?= $drA->getDohTotalForMonthString(12) ?></td>
      <td class="text-right"><?= number_format($drA->getTotalDohForYear(),0,',', ' ') ?></td>
    </tr>
    <?php foreach (DohRashesAnalisys::getDohKeyItemsArrayCorrected() as $key=>$value): ?>
      <tr class="doh-row d-none" data-type2="<?=$key?>" data-type1="doh">
        <th scope="row"><?= $value ?></th>
        <td class="text-right details" data-month="1" data-to="<?= (((new \DateTime())->setDate($year, 1,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 1) ?></td>
        <td class="text-right details" data-month="2" data-to="<?= (((new \DateTime())->setDate($year, 2,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 2) ?></td>
        <td class="text-right details" data-month="3" data-to="<?= (((new \DateTime())->setDate($year, 3,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 3) ?></td>
        <td class="text-right details" data-month="4" data-to="<?= (((new \DateTime())->setDate($year, 4,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 4) ?></td>
        <td class="text-right details" data-month="5" data-to="<?= (((new \DateTime())->setDate($year, 5,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 5) ?></td>
        <td class="text-right details" data-month="6" data-to="<?= (((new \DateTime())->setDate($year, 6,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 6) ?></td>
        <td class="text-right details" data-month="7" data-to="<?= (((new \DateTime())->setDate($year, 7,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 7) ?></td>
        <td class="text-right details" data-month="8" data-to="<?= (((new \DateTime())->setDate($year, 8,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 8) ?></td>
        <td class="text-right details" data-month="9" data-to="<?= (((new \DateTime())->setDate($year, 9,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 9) ?></td>
        <td class="text-right details" data-month="10" data-to="<?= (((new \DateTime())->setDate($year, 10,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 10) ?></td>
        <td class="text-right details" data-month="11" data-to="<?= (((new \DateTime())->setDate($year, 11,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 11) ?></td>
        <td class="text-right details" data-month="12" data-to="<?= (((new \DateTime())->setDate($year, 12,1))->modify('last day of this month')->format("Y-m-d")) ?>"><?= $drA->getDohForMonthString($key, 12) ?></td>
        <td class="text-right"><?= number_format($drA->getDohForYear($key),0,',', ' ') ?></td>
      </tr>
    <?php endforeach;?>
    <tr class="total" style="background-color: #0a98ff">
      <th scope="row">Итоговый результат<input type="button" value="+" class="doh-btn"></th>
      <td class="text-right"><?= $drA->getTotalResultString(1) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(2) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(3) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(4) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(5) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(6) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(7) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(8) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(9) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(10) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(11) ?></td>
      <td class="text-right"><?= $drA->getTotalResultString(12) ?></td>
      <td class="text-right"><?= number_format($drA->getTotalDohForYear(),0,',', ' ') ?></td>
    </tr>

  </tbody>
</table>

</div>
<style>
  .total td{
    font-weight: bold;
  }
  .rash-row th,
  .doh-row th,
  .sales th
  {
    padding-left: 20px;
    font-weight: normal;
    font-style: italic;
  }
  tr:hover{
    background-color: rgba(0, 0, 0, 0.14);
    transition: 0.3s;
  }

  .text-right{
    text-align: right;
  }

  .hide{
    display: none;
  }
</style>


<script src="/bb/assets/js/rash-anays.js?v=5"></script>
<?php

//$from = new \DateTime($year.'-01-01');
//  $from->setTime(0,0,1);
//$to = new \DateTime($year.'-12-31');
//  $to->setTime(23,59,59);
//
//Base::varDamp(\bb\classes\Deal::getCountClientsDelivryAndNot($from, $to));
//Base::varDamp(\bb\classes\Deal::getCountClientsOfficeNonDelivry($from, $to));


echo Base::pageEndHtmlB5();


?>
