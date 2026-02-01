<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 02.12.2018
 * Time: 15:58
 */

namespace bb;

use bb\classes\Category;
use bb\classes\DohRash;
use bb\classes\DohRashesAnalisys;
use bb\classes\Model;
use bb\classes\SubDeal;
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

echo Base::pageStartB5('Test Page');

Base::loginCheck();

$to = new \DateTime();
$from = clone $to;
$from->modify('first day of this month');

if (isset($_POST['from'])){
  $from = new \DateTime($_POST['from']);
}
if (isset($_POST['to'])){
  $to = new \DateTime($_POST['to']);
}


$sales = \bb\classes\Deal::getSalesByKassa($from, $to);
$totalSales = $sales['nal_no_cheque']+$sales['nal_cheque']+$sales['bank']+$sales['card'];

$shiftPlus=[];
  $shiftPlus['nal_cheque'] = DohRash::getShiftPlusForKassa($from, $to,'k1');
  $shiftPlus['nal_no_cheque'] = DohRash::getShiftPlusForKassa($from, $to,'k2');
  $shiftPlus['bank'] = DohRash::getShiftPlusForKassa($from, $to,'bank');

$shiftMinus=[];
  $shiftMinus['nal_cheque'] = DohRash::getShiftMinusForKassa($from, $to,'k1');
  $shiftMinus['nal_no_cheque'] = DohRash::getShiftMinusForKassa($from, $to,'k2');
  $shiftMinus['bank'] = DohRash::getShiftMinusForKassa($from, $to,'bank');
$doh=[];
  $doh['nal_cheque'] = DohRash::getDohForKassa($from, $to,'k1');
  $doh['nal_no_cheque'] = DohRash::getDohForKassa($from, $to,'k2');
  $doh['bank'] = DohRash::getDohForKassa($from, $to,'bank');

$rash=[];
  $rash['nal_cheque'] = DohRash::getRashForKassa($from, $to,'k1');
  $rash['nal_no_cheque'] = DohRash::getRashForKassa($from, $to,'k2');
  $rash['bank'] = DohRash::getRashForKassa($from, $to,'bank');

$ostatokStart=[];

?>

<table class="table table-hover table-sm">
  <thead>
  <tr>
    <th scope="col" class="text-right">
      <form action="" method="post" class="d-inline">
        c <input class="form-control-sm" type="date" name="from" value="<?= $from->format('Y-m-d') ?>">
        по <input class="form-control-sm" type="date" name="to" value="<?= $to->format('Y-m-d') ?>">
        <button type="submit">--></button>
      </form>
    </th>
    <th scope="col" class="text-right">нал</th>
    <th scope="col" class="text-right">нал_ч</th>
    <th scope="col" class="text-right">безнал</th>
  </tr>
  </thead>
  <tbody>
  <tr>
    <td>выручка</td>
    <td><?= number_format($sales['nal_no_cheque'], 0,',', ' ') ?> / <i><?= number_format($sales['nal_no_cheque']/$totalSales*100, 1,',', ' ') ?>%</i></td>
    <td><?= number_format($sales['nal_cheque'], 0,',', ' ') ?> / <i><?= number_format($sales['nal_cheque']/$totalSales*100, 1,',', ' ') ?>%</i></td>
    <td><?= number_format($sales['bank']+$sales['card'], 0,',', ' ') ?> / <i><?= number_format(($sales['bank']+$sales['card'])/$totalSales*100, 1,',', ' ') ?>%</i></td>
  </tr>
  <tr>
    <td>---карта</td>
    <td></td>
    <td></td>
    <td><?= number_format($sales['card'], 0,',', ' ') ?> / <i><?= number_format($sales['card']/$totalSales*100, 1,',', ' ') ?>%</i></td>
  </tr>
  <tr>
    <td>---банк</td>
    <td></td>
    <td></td>
    <td><?= number_format($sales['bank'], 0,',', ' ') ?> / <i><?= number_format($sales['bank']/$totalSales*100, 1,',', ' ') ?>%</i></td>
  </tr>
  <tr>
    <td>перевод из касс: поступление (+)</td>
    <td><?= number_format($shiftPlus['nal_no_cheque'], 0,',',' ') ?></td>
    <td><?= number_format($shiftPlus['nal_cheque'], 0,',',' ') ?></td>
    <td><?= number_format($shiftPlus['bank'], 0,',',' ') ?></td>
  </tr>
  <tr>
    <td>перевод в кассы: расход (-)</td>
    <td><?= number_format($shiftMinus['nal_no_cheque'], 0,',',' ') ?></td>
    <td><?= number_format($shiftMinus['nal_cheque'], 0,',',' ') ?></td>
    <td><?= number_format($shiftMinus['bank'], 0,',',' ') ?></td>
  </tr>
  <tr>
    <td>доходы</td>
    <td><?= number_format($doh['nal_no_cheque'], 0,',',' ') ?></td>
    <td><?= number_format($doh['nal_cheque'], 0,',',' ') ?></td>
    <td><?= number_format($doh['bank'], 0,',',' ') ?></td>
  </tr>
  <tr>
    <td>расходы</td>
    <td><?= number_format($rash['nal_no_cheque'], 0,',',' ') ?></td>
    <td><?= number_format($rash['nal_cheque'], 0,',',' ') ?></td>
    <td><?= number_format($rash['bank'], 0,',',' ') ?></td>
  </tr>
  </tbody>
</table>

<?php

//Base::varDamp($rep);

echo Base::pageEndHtmlB5();


?>
