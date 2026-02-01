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
use bb\classes\WorkShift;
use bb\classes\WorkShiftMonthReport;
use Classes\Deal;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);


require ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');

Base::loginCheck();

echo Base::pageStartB5('Покупка/продажа товаров');
//echo Base::PostCheck();

$to = new \DateTime();
$from = clone $to;
  $from->modify('first day of this month');

if (isset($_POST['from'])){
  $from = new \DateTime($_POST['from']);
}
if (isset($_POST['to'])){
  $to = new \DateTime($_POST['to']);
}

$tovars = \bb\classes\tovar::getBoutghTovsForPeriod($from, $to);

$tovsByCatAndModel = [];

foreach ($tovars as $t){
  $model = Model::getById($t->getModelId());
  if (!isset($tovsByCatAndModel[$model->getCatId()])) $tovsByCatAndModel[$model->getCatId()] = [];
  if (!isset($tovsByCatAndModel[$model->getCatId()][$model->getModelId()])) $tovsByCatAndModel[$model->getCatId()][$model->getModelId()] = [];

  $tovsByCatAndModel[$model->getCatId()][$model->getModelId()][]=$t;
}


function countTovsByModId($modelId)
{
  global $tovsByCatAndModel;

  foreach ($tovsByCatAndModel as $catId => $tovarrayByModels){
    if (isset($tovarrayByModels[$modelId])) return count($tovarrayByModels[$modelId]);
  }

  return 0;
}

function priceTovsByModId($modelId)
{
  global $tovsByCatAndModel;

  $amount = 0;

  foreach ($tovsByCatAndModel as $catId => $tovarrayByModels){
    if (isset($tovarrayByModels[$modelId])) {
      foreach ($tovarrayByModels[$modelId] as $t){
        //$t = new \bb\classes\tovar();
        $amount += $t->getBuyPriceBYN();
      }
    }
  }

  return $amount;
}

function priceTovsByCatId($catId)
{
  global $tovsByCatAndModel;

  $amount = 0;

  if (isset($tovsByCatAndModel[$catId])) {
    foreach ($tovsByCatAndModel[$catId] as $tovs){
      foreach ($tovs as $t){
        //$t = new \bb\classes\tovar();
        $amount += $t->getBuyPriceBYN();
      }
    }
  }

  return $amount;
}

function countTovsByCatId($catId)
{
  global $tovsByCatAndModel;

  $num = 0;

  if (isset($tovsByCatAndModel[$catId])) {
    foreach ($tovsByCatAndModel[$catId] as $tovs){
      $num += count($tovs);
    }
  }

  return $num;
}

function countTovsAll()
{
  global $tovsByCatAndModel;
  $num = 0;

  foreach ($tovsByCatAndModel as $catId => $tovs){
    $num += countTovsByCatId($catId);
  }

  return $num;
}

function priceTovsAll()
{
  global $tovsByCatAndModel;
  $num = 0;

  foreach ($tovsByCatAndModel as $catId => $tovs){
    $num += priceTovsByCatId($catId);
  }

  return $num;
}

//Base::varDamp($tovars);





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
<table class="table table-hover table-sm">
  <thead>
    <tr>
      <th scope="col" class="text-right">Товары приобретенные
        <form action="" method="post" class="d-inline">
          c <input class="form-control-sm" type="date" name="from" value="<?= $from->format('Y-m-d') ?>">
          по <input class="form-control-sm" type="date" name="to" value="<?= $to->format('Y-m-d') ?>">
          <button type="submit">--></button>
        </form>
      </th>
      <th scope="col" class="text-right">кол-во</th>
      <th scope="col" class="text-right">бел.руб.</th>
      <th scope="col" class="text-right">окупаемость (мес.)</th>
    </tr>
  </thead>
  <tbody>
    <tr class="cat" style="font-weight: bold; background-color: #c7c7c7">
      <th scope="row">Итого</th>
      <td class="text-right"><?= countTovsAll(); ?></td>
      <td class="text-right"><?= number_format(priceTovsAll(),0,',',' ') ?></td>
      <td class="text-right"></td>
    </tr>
    <?php foreach ($tovsByCatAndModel as $catId => $tovsByModel): ?>
      <tr class="cat" style="font-weight: bold;">
        <th scope="row"><?= Category::getById($catId)->getName() ?></th>
        <td class="text-right"><?= countTovsByCatId($catId) ?></td>
        <td class="text-right"><?= number_format(priceTovsByCatId($catId),0,',',' ') ?></td>
        <td class="text-right"></td>
      </tr>
      <?php foreach ($tovsByModel as $modelId => $tovs): ?>
       <tr class="model">
        <td>---<?= Model::getById($modelId)->getShortName() ?></td>
        <td class="text-right"><?= countTovsByModId($modelId) ?></td>
        <td class="text-right"><!--<?= number_format(priceTovsByModId($modelId),0,',',' ') ?>--></td>
        <td class="text-right"></td>
      </tr>
        <?php foreach ($tovs as $t): ?>
          <?php //$t = new \bb\classes\tovar() ?>
          <tr class="model">
            <td class="text-right"><?= $t->getBuyDate()->format('d.m.Y').' инв.н: <span style="cursor: pointer;" class="inv-n" data-item_id="'.$t->item_id.'">'.$t->getInvN().'</span> <i>[ввел(а) '.$t->getUserName().']</i>' ?></td>
            <td class="text-right"></td>
            <td class="text-right"><?= number_format($t->getBuyPriceBYN(),0,',',' ') ?></td>
            <td class="text-right"><?= $t->getMonthsToPayBack() ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>

  </tbody>
</table>

</div>
<style>
  .text-right{
    text-align: right;
  }
</style>

<script>
  let invNs = document.querySelectorAll('span.inv-n');
  invNs.forEach((el)=>{
    el.addEventListener('dblclick', invNDoubleClick);
  });
  invNs.forEach((el)=>{
    el.addEventListener('doubletap', invNDoubleClick);
  });

  function invNDoubleClick(e){
    let item_id = e.target.dataset.item_id;


    var form = document.createElement('form');
    form.action = '/bb/tovar_new.php'; // Replace with your actual endpoint URL
    form.method = 'POST';
    form.target = '_blank';

    // Add data to the form
    var data = {
      action: 'редактировать',
      item_id: item_id,
    };

    for (var key in data) {
      if (data.hasOwnProperty(key)) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = data[key];
        form.appendChild(input);
      }
    }

    // Append the form to the body
    document.body.appendChild(form);

    // Submit the form
    form.submit();

    // Remove the form from the DOM
    document.body.removeChild(form);

  }
</script>

<!--<script src="/bb/assets/js/rash-anays.js?v=5"></script>-->
<?php

echo Base::pageEndHtmlB5();


?>
