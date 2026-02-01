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
<div class="row">
  <div class="col">
    <a href="/bb/index.php">На главную</a>
    <a href="/bb/doh-rash.php">Расходы</a>
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
    <?php foreach (DohRash::getAllRashKeyValues() as $key=>$value): ?>
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
    <?php foreach (DohRash::getAllDohKeyValues() as $key=>$value): ?>
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

  </tbody>
</table>

</div>
<style>
  .total td{
    font-weight: bold;
  }
  .rash-row th,
  .doh-row th
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
</style>


<script src="/bb/assets/js/rash-anays.js?v=2"></script>
<?php

//Base::varDamp($rep);

echo Base::pageEndHtmlB5();


?>
