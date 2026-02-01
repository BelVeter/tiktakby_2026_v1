<?php

use bb\classes\DohRashesAnalisys;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Deal.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubDeal.php');
require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

echo \bb\Base::pageStartB5('Отчет по товарам');
echo \bb\Base::loginCheck();
//\bb\classes\Deal::archiveFullDeal(96269);

$today = new DateTime();

$year= $today->format("Y");
if (isset($_POST['year'])) {
  $year=$_POST['year'];
  $today->setDate($year,12,31);
}


$startOfCurrentYear = clone $today;
  $startOfCurrentYear->modify('first day of January');
$endOfCurrentYear = clone $startOfCurrentYear;
  $endOfCurrentYear->modify('last day of December');
$year_3 = clone $today;
  $year_3->modify('-3 years');
  $year_3_start=clone $year_3;
    $year_3_start->modify('first day of January');
  $year_3_end=clone  $year_3;
    $year_3_end->modify('last day of December');

  $year_2_start=clone $year_3_start;
    $year_2_start->modify('+1 year');
  $year_2_end=clone $year_3_end;
    $year_2_end->modify('+1 year');

  $year_1_start=clone $year_2_start;
    $year_1_start->modify('+1 year');
  $year_1_end=clone $year_2_end;
    $year_1_end->modify('+1 year');

//  \bb\Base::varDamp($today);
//  \bb\Base::varDamp($year_3_start);
//  \bb\Base::varDamp($year_3_end);
//  \bb\Base::varDamp($year_2_start);
//  \bb\Base::varDamp($year_2_end);
//  \bb\Base::varDamp($year_1_start);
//  \bb\Base::varDamp($year_1_end);

$rez=[];
$rez['кол-во товара на начало периода'] = [];
$rez['кол-во товара на начало периода'][]=\bb\classes\tovar::getTovNumberForCatsForDate($year_3_start);
$rez['кол-во товара на начало периода'][]=\bb\classes\tovar::getTovNumberForCatsForDate($year_2_start);
$rez['кол-во товара на начало периода'][]=\bb\classes\tovar::getTovNumberForCatsForDate($year_1_start);
$rez['кол-во товара на начало периода'][]=\bb\classes\tovar::getTovNumberForCatsForDate($startOfCurrentYear);

$rez['выбыло'] = [];
$rez['выбыло'][]=\bb\classes\tovar::getArchivedTovsForCatsForPeriod($year_3_start, $year_3_end,[]);
$rez['выбыло'][]=\bb\classes\tovar::getArchivedTovsForCatsForPeriod($year_2_start, $year_2_end,[]);
$rez['выбыло'][]=\bb\classes\tovar::getArchivedTovsForCatsForPeriod($year_1_start, $year_1_end,[]);
$rez['выбыло'][]=\bb\classes\tovar::getArchivedTovsForCatsForPeriod($startOfCurrentYear, $endOfCurrentYear,[]);

$rez['купили'] = [];
$rez['купили'][]=\bb\classes\tovar::getBoutghTovsForCats($year_3_start, $year_3_end,[]);
$rez['купили'][]=\bb\classes\tovar::getBoutghTovsForCats($year_2_start, $year_2_end,[]);
$rez['купили'][]=\bb\classes\tovar::getBoutghTovsForCats($year_1_start, $year_1_end,[]);
$rez['купили'][]=\bb\classes\tovar::getBoutghTovsForCats($startOfCurrentYear, $endOfCurrentYear,[]);

$rez['кол-во товара на конец периода'] = [];
$rez['кол-во товара на конец периода'][]=\bb\classes\tovar::getTovNumberForCatsForDate($year_3_end);
$rez['кол-во товара на конец периода'][]=\bb\classes\tovar::getTovNumberForCatsForDate($year_2_end);
$rez['кол-во товара на конец периода'][]=\bb\classes\tovar::getTovNumberForCatsForDate($year_1_end);
$rez['кол-во товара на конец периода'][]=\bb\classes\tovar::getTovNumberForCatsForDate($endOfCurrentYear);

$rez['средний возраст товара (лет)'] = [];
$rez['средний возраст товара (лет)'][]=number_format(\bb\classes\tovar::getAvgAgeForCats([], $year_3_end)/365,1,',', ' ');
$rez['средний возраст товара (лет)'][]=number_format(\bb\classes\tovar::getAvgAgeForCats([], $year_2_end)/365,1,',', ' ');
$rez['средний возраст товара (лет)'][]=number_format(\bb\classes\tovar::getAvgAgeForCats([], $year_1_end)/365,1,',', ' ');
$rez['средний возраст товара (лет)'][]=number_format(\bb\classes\tovar::getAvgAgeForCats([], $today<$endOfCurrentYear ? $today : $endOfCurrentYear)/365,1,',', ' ');

$tmpStartDay = clone $startOfCurrentYear;
while ($tmpStartDay <= $endOfCurrentYear){
  $tmpEndDay = clone $tmpStartDay;
    $tmpEndDay->modify('last day of this month');

  if ($tmpStartDay <= $today) {
    $rez['кол-во товара на начало периода'][]=\bb\classes\tovar::getTovNumberForCatsForDate($tmpStartDay);
    $rez['выбыло'][]=\bb\classes\tovar::getArchivedTovsForCatsForPeriod($tmpStartDay, $tmpEndDay,[]);
    $rez['купили'][]=\bb\classes\tovar::getBoutghTovsForCats($tmpStartDay, $tmpEndDay,[]);
    $rez['кол-во товара на конец периода'][]=\bb\classes\tovar::getTovNumberForCatsForDate($tmpEndDay);
    $rez['средний возраст товара (лет)'][]=number_format(\bb\classes\tovar::getAvgAgeForCats([], $tmpEndDay)/365,1,',', ' ');
  }
  else{
    $rez['кол-во товара на начало периода'][]=0;
    $rez['выбыло'][]=0;
    $rez['купили'][]=0;
    $rez['кол-во товара на конец периода'][]=0;
    $rez['средний возраст товара (лет)'][]=0;
  }


  $tmpStartDay->modify('+1 month');
}

?>
<div class="container-fluid">
  <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
      <div class="navbar-nav">
        <a class="nav-item nav-link" href="/bb/doh-rash.php">Расходы</a>
        <a class="nav-item nav-link" href="/bb/kr_baza_new.php">Товары</a>
        <a class="nav-item nav-link" href="/bb/rda.php">Сделки</a>
        <a class="nav-item nav-link" href="/bb/tovar_dinamics.php">детали по товарам</a>
      </div>
    </div>
  </nav>
  <table class="table cats">
    <thead>
    <tr>
      <th scope="col" class="text-right">Position
        <form action="" method="post" class="d-inline">
          <input name="year" type="number" min="2000" max="<?= (new \DateTime())->format("Y")?>" value="<?= $year ?>"><input type="submit" value="->">
        </form>
      </th>
      <th class="text-end" scope="col"><?= $year_3_start->format('Y') ?></th>
      <th class="text-end" scope="col"><?= $year_2_start->format('Y') ?></th>
      <th class="text-end" scope="col"><?= $year_1_start->format('Y') ?></th>
      <th class="text-end" scope="col"><?= $year ?> весь</th>
      <?php for ($i=1;$i<=12;$i++): ?>
        <th class="text-end" scope="col"><?= $year.'-'.$i ?></th>
      <?php endfor; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rez as $index=>$r): ?>
      <tr class="data <?= ($index=='купили' ? 'buy' : '') ?>">
        <td><?= $index ?></td>
        <td class="text-end data" data-start="<?= $year_3_start->format('Y') ?>-01-01" data-end="<?= $year_3_start->format('Y') ?>-12-31"><?= $r[0] ?></td>
        <td class="text-end data" data-start="<?= $year_2_start->format('Y') ?>-01-01" data-end="<?= $year_2_start->format('Y') ?>-12-31"><?= $r[1] ?></td>
        <td class="text-end data" data-start="<?= $year_1_start->format('Y') ?>-01-01" data-end="<?= $year_1_start->format('Y') ?>-12-31"><?= $r[2] ?></td>
        <td class="text-end data" data-start="<?= $year ?>-01-01" data-end="<?= $year ?>-12-31"><?= $r[3] ?></td>
        <?php for ($i=1;$i<=12;$i++): ?>
          <td class="text-end data" data-start="<?= $year.'-'.($i<10 ? '0'.$i : $i) ?>-01" data-end="<?= (new DateTime($year.'-'.($i<10 ? '0'.$i : $i).'-01'))->modify('last day of this month')->format('Y-m-d') ?>"><?= $r[$i+3] ?></td>
        <?php endfor; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<style>
  .buy .data{
    cursor: pointer;
  }
</style>

<script>
  let sellTDs = document.querySelectorAll('.buy .data');

  sellTDs.forEach((el)=>{
    el.addEventListener('dblclick', sellClick);
  });

  function sellClick(e){
    let from = e.target.dataset.start;
    let to = e.target.dataset.end;



    var form = document.createElement('form');
    form.action = '/bb/tovar_dinamics.php'; // Replace with your actual endpoint URL
    form.method = 'POST';
    form.target = '_blank';

    // Add data to the form
    var data = {
      from: from,
      to: to,
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

<?php

echo \bb\Base::pageEndHtmlB5();
?>
