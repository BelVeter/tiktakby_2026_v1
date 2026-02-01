<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Deal.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubDeal.php');
require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

echo \bb\Base::pageStartB5("Динамика выручки - отчет");
\bb\Base::loginCheck();
//\bb\classes\Deal::archiveFullDeal(96269);

$to = new DateTime((new DateTime())->format("Y-m-d"));
$from = new DateTime($to->format("Y-m-01"));
//echo \bb\Base::PostCheck();

if (isset($_POST['from'])) {
  $from = new DateTime($_POST['from']);
}
if (isset($_POST['to'])) {
  $to = new DateTime($_POST['to']);
}

$from2 = clone $from;
$to2 = clone $to;

$from1 = clone $from;
  $from1->modify('- 1 year');
$to1 = clone $to;
  $to1->modify('-1 year');


$rez1 =\bb\classes\Deal::getSalesCategorySplit($from1, $to1);
$rez2 =\bb\classes\Deal::getSalesCategorySplit($from2, $to2);

$rez = [];

foreach ($rez1 as $key=>$value){
  $rez[$key] = [];
  $rez[$key][0]=$value;
  $rez[$key][1]=0;
}

foreach ($rez2 as $key=>$value){
  if (isset($rez[$key])) {
    $rez[$key][1] = $value;
  }
  else{
    $rez[$key] = [];
    $rez[$key][0] = 0;
    $rez[$key][1]=$value;
  }
}

$karnavalRez=[];
  $karnavalRez[0]=0;
  $karnavalRez[1]=0;


$sales_1_1 = \bb\classes\Deal::getSalesRentDeliv($from1, $to1,1,0);
$sales_1_2 = \bb\classes\Deal::getSalesRentDeliv($from2, $to2,1,0);

$sales_2_1 = \bb\classes\Deal::getSalesRentDeliv($from1, $to1,2,0);
$sales_2_2 = \bb\classes\Deal::getSalesRentDeliv($from2, $to2,2,0);

$sales_3_1 = \bb\classes\Deal::getSalesRentDeliv($from1, $to1,3,0);
$sales_3_2 = \bb\classes\Deal::getSalesRentDeliv($from2, $to2,3,0);

$sales_deliv_1 = \bb\classes\Deal::getSalesRentDeliv($from1, $to1,'all',1);
$sales_deliv_2 = \bb\classes\Deal::getSalesRentDeliv($from2, $to2,'all',1);

$sales_all_1 = \bb\classes\Deal::getSalesRentDeliv($from1, $to1);
$sales_all_2 = \bb\classes\Deal::getSalesRentDeliv($from2, $to2);

?>
<div class="container-fluid">
  <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
      <div class="navbar-nav">
        <a class="nav-item nav-link" href="/bb/dohrash2.php">Свод доходов и расходов</a>
        <a class="nav-item nav-link" href="/bb/cat_analysis.php">Анализ выдач по категориям</a>
        <a class="nav-item nav-link" href="/bb/tovar_report.php">Товары (динамика)</a>
      </div>
    </div>
  </nav>
  <form method="post" class="row">
    <div class="col form-group">
      <label for="from">С</label>
      <input type="date" class="form-control" id="from" name="from" aria-describedby="emailHelp" value="<?= $from->format('Y-m-d') ?>">
    </div>
    <div class="col form-group">
      <label for="to">По</label>
      <input type="date" class="form-control" id="to" name="to" aria-describedby="emailHelp" value="<?= $to->format('Y-m-d') ?>">
    </div>
    <div class="col d-flex form-group align-content-end">
      <button type="submit" class="btn btn-primary">смотреть</button>
    </div>
  </form>

  <table class="table">
    <thead>
      <tr>
        <th scope="col">Офис</th>
        <th class="text-end" scope="col">Выручка с <?= $from1->format('d.m.Y') ?> по <?= $to1->format('d.m.Y') ?></th>
        <th class="text-end" scope="col">Выручка с <?= $from2->format('d.m.Y') ?> по <?= $to2->format('d.m.Y') ?></th>
        <th class="text-end" scope="col">Разница (руб)</th>
        <th class="text-end" scope="col">Темп. роста (%)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <th scope="row">Всего</th>
        <td class="text-end" data-value="<?= $sales_all_1['sales'] ?>"><?= number_format($sales_all_1['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= $sales_all_2['sales'] ?>"><?= number_format($sales_all_2['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($sales_all_2['sales']-$sales_all_1['sales']) ?>"><?= number_format($sales_all_2['sales']-$sales_all_1['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($sales_all_1['sales']>0 ? $sales_all_2['sales']/$sales_all_1['sales']*100 : 1000) ?>"><?= number_format(($sales_all_1['sales']>0 ? $sales_all_2['sales']/$sales_all_1['sales']*100 : 1000), 1, ',',' ') ?>%</td>
      </tr>
      <tr>
        <th class="fw-normal" scope="row">Офис 1</th>
        <td class="text-end" data-value="<?= $sales_1_1['sales'] ?>"><?= number_format($sales_1_1['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= $sales_1_2['sales'] ?>"><?= number_format($sales_1_2['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($sales_1_2['sales']-$sales_1_1['sales']) ?>"><?= number_format($sales_1_2['sales']-$sales_1_1['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($sales_1_1['sales']>0 ? $sales_1_2['sales']/$sales_1_1['sales']*100 : 1000) ?>"><?= number_format(($sales_1_1['sales']>0 ? $sales_1_2['sales']/$sales_1_1['sales']*100 : 1000), 1, ',',' ') ?>%</td>
      </tr>
      <tr>
        <th class="fw-normal" scope="row">Офис 2</th>
        <td class="text-end" data-value="<?= $sales_2_1['sales'] ?>"><?= number_format($sales_2_1['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= $sales_2_2['sales'] ?>"><?= number_format($sales_2_2['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($sales_2_2['sales']-$sales_2_1['sales']) ?>"><?= number_format($sales_2_2['sales']-$sales_2_1['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($sales_2_1['sales']>0 ? $sales_2_2['sales']/$sales_2_1['sales']*100 : 1000) ?>"><?= number_format(($sales_2_1['sales']>0 ? $sales_2_2['sales']/$sales_2_1['sales']*100 : 1000), 1, ',',' ') ?>%</td>
      </tr>
      <tr>
        <th class="fw-normal" scope="row">Офис 3</th>
        <td class="text-end" data-value="<?= $sales_3_1['sales'] ?>"><?= number_format($sales_3_1['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= $sales_3_2['sales'] ?>"><?= number_format($sales_3_2['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($sales_3_2['sales']-$sales_3_1['sales']) ?>"><?= number_format($sales_3_2['sales']-$sales_3_1['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($sales_3_1['sales']>0 ? $sales_3_2['sales']/$sales_3_1['sales']*100 : 1000) ?>"><?= number_format(($sales_3_1['sales']>0 ? $sales_3_2['sales']/$sales_3_1['sales']*100 : 1000), 1, ',',' ') ?>%</td>
      </tr>
      <tr>
        <th class="fw-normal" scope="row">Курьер</th>
        <td class="text-end" data-value="<?= $sales_deliv_1['sales'] ?>"><?= number_format($sales_deliv_1['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= $sales_deliv_2['sales'] ?>"><?= number_format($sales_deliv_2['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($sales_deliv_2['sales']-$sales_deliv_1['sales']) ?>"><?= number_format($sales_deliv_2['sales']-$sales_deliv_1['sales'], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($sales_deliv_1['sales']>0 ? $sales_deliv_2['sales']/$sales_deliv_1['sales']*100 : 1000) ?>"><?= number_format(($sales_deliv_1['sales']>0 ? $sales_deliv_2['sales']/$sales_deliv_1['sales']*100 : 1000), 1, ',',' ') ?>%</td>
      </tr>
    </tbody>
  </table>

  <table class="table cats">
    <thead>
    <tr>
      <th scope="col">Категория</th>
      <th class="text-end" scope="col" ondblclick="sortTable(1)">Выручка с <?= $from1->format('d.m.Y') ?> по <?= $to1->format('d.m.Y') ?></th>
      <th class="text-end" scope="col" ondblclick="sortTable(2)">Выручка с <?= $from2->format('d.m.Y') ?> по <?= $to2->format('d.m.Y') ?></th>
      <th class="text-end" scope="col" ondblclick="sortTable(3)">Разница (руб)</th>
      <th class="text-end" scope="col" ondblclick="sortTable(4)">Темп. роста (%)</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rez as $index=>$r): ?>
    <?php
      if ($index<1) continue;
      $cat = \bb\classes\Category::getById($index);
      if ($cat && $cat->isKarnaval()) {
        $karnavalRez[0]+=$r[0];
        $karnavalRez[1]+=$r[1];
        continue;
      }
    ?>
      <tr class="data">
        <td data-cat_id="<?= $index?>"><?= ($cat) ? $cat->name : $index?> [ <?= \bb\classes\tovar::getTovNumberForCatsForDate($to1, [$index]) ?> / <?= \bb\classes\tovar::getTovNumberForCatsForDate($to2, [$index]) ?> ] - <?= round(\bb\classes\tovar::getAvgAgeForCats([$index], $to1)/365,1) ?> / <?= round(\bb\classes\tovar::getAvgAgeForCats([$index], $to2)/365,1) ?> лет</td>
        <td class="text-end" data-value="<?= $r[0] ?>"><?= number_format($r[0], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= $r[1] ?>"><?= number_format($r[1], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($r[1]-$r[0]) ?>"><?= number_format($r[1]-$r[0], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($r[0]>0 ? $r[1]/$r[0]*100 : 1000) ?>"><?= number_format(($r[0]>0 ? $r[1]/$r[0]*100 : 1000), 1, ',',' ') ?>%</td>
      </tr>
    <?php endforeach; ?>
      <tr class="data">
        <td>Карнавалы</td>
        <td class="text-end" data-value="<?= $karnavalRez[0] ?>"><?= number_format($karnavalRez[0], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= $karnavalRez[1] ?>"><?= number_format($karnavalRez[1], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($karnavalRez[1]-$karnavalRez[0]) ?>"><?= number_format($karnavalRez[1]-$karnavalRez[0], 0, ',',' ') ?></td>
        <td class="text-end" data-value="<?= ($karnavalRez[0]>0 ? $karnavalRez[1]/$karnavalRez[0]*100 : 1000) ?>"><?= number_format(($karnavalRez[0]>0 ? $karnavalRez[1]/$karnavalRez[0]*100 : 1000), 1, ',',' ') ?>%</td>
      </tr>
    </tbody>
  </table>
</div>
<script>

  function sortTable(columnIndex) {
    const table = document.querySelector(".cats tbody");
    const rows = Array.from(table.querySelectorAll(("tr.data"))); // Exclude header row

    rows.sort((a, b) => {
      const aValue = a.querySelectorAll("td")[columnIndex].dataset.value*1;
      const bValue = b.getElementsByTagName("td")[columnIndex].dataset.value*1;
      return bValue-aValue;
    });

    // Clear the table body
    while (table.rows.length > 1) {
      table.deleteRow(1);
    }

    // Append sorted rows
    rows.forEach(row => {
      table.appendChild(row);
    });
  }
</script>

</script>


<?php

echo \bb\Base::pageEndHtmlB5();
?>
