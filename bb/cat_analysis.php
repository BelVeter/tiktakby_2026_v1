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
use bb\classes\Razdel;
use bb\classes\SubRazdel;
use bb\classes\WorkShift;
use bb\classes\WorkShiftMonthReport;
use Classes\Deal;
use bb\classes\tovar;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);
set_time_limit(120);

require ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');

Base::loginCheck();

echo Base::pageStartB5('Доходы и расходы');
//echo Base::PostCheck();

$year= (new \DateTime())->format("Y");
if (isset($_POST['year'])) $year=$_POST['year'];

$showModels = false;
if (isset($_POST['show_models'])) $showModels=true;

$modelIds=[];

//Base::varDamp($drA);
$cat_ids=[1];
if (isset($_POST['cat_ids'])) {
  $cat_ids=$_POST['cat_ids'];
}
//Base::varDamp($_POST['cat_ids']);
//Base::varDamp($cat_ids);
$cats = [];
$allCats = Category::getAllCategories();

foreach ($cat_ids as $id) {
  $catTmp = Category::getById($id);
    $cats[] = $catTmp;

  if ($showModels){
    $modelIdsTmp = Model::getModelIdsForCategoryId($id);
    if ($modelIdsTmp) {
      $modelIds = array_merge($modelIds, $modelIdsTmp);
    }
  }
}

//$modelIds = array_slice($modelIds, 0, 5);

$modelTovarNum = [];
$modelRentedPercent = [];

$models = [];
if ($showModels){
  foreach ($modelIds as $mId){
    $model = Model::getById($mId);
    if ($model) {
      $models[]=$model;
    }
  }
}


$startOfYear = new \DateTime($year.'-01-01');
  $startOfYear->setTime(0,0,0);

//mail values
$startDay = clone $startOfYear;
$months = [];
$endOfMonth = [];
$daysInMonth=[];
$tovarKolVo = [];
$daysRentedOut = [];

for ($i=1; $i<=12; $i++){
  $months[$i]=clone $startDay;

    $endDayOfMonth = clone $startDay;
    $endDayOfMonth->modify('last day of this month');
  $endOfMonth[$i] = clone $endDayOfMonth;

  $daysInMonth[$i] = date('t', $startDay->getTimestamp());
  $tovarKolVo[$i] = \bb\classes\tovar::getTovNumberForCatsForDate($months[$i], $cat_ids);
  $daysRentedOut[$i] = \bb\classes\Deal::getRentDaysNumForCatIds($months[$i], $endOfMonth[$i], $cat_ids)*1;

  foreach ($models as $m) {
    if (!isset($modelTovarNum[$m->model_id])) $modelTovarNum[$m->model_id]=[];
    $modelTovarNum[$m->model_id][$i] = tovar::getTovNumberForModelIdsForDate($startDay, [$m->model_id]);

    if (!isset($modelRentedPercent[$m->model_id])) $modelRentedPercent[$m->model_id]=[];
    if ($modelTovarNum[$m->model_id][$i]>0) {
      $modelRentedPercent[$m->model_id][$i] = Model::getRentedOutDaysPercent($startDay, $endDayOfMonth, [$m->model_id]);
    }
    else{
      $modelRentedPercent[$m->model_id][$i] = 0;
    }

  }

  $startDay->modify('+1 month');
}
$daysRentedOut['total'] = Category::getRentedOutDaysPercent($startOfYear,(new \DateTime()),$cat_ids);

foreach ($models as $m) {
  if (array_sum( $modelTovarNum[$m->model_id])>0) {
    $modelRentedPercent[$m->model_id]['total'] = number_format((Model::getRentedOutDaysPercent($startOfYear, (new \DateTime()), [$m->model_id])),1,',',' ');
  }
  else{
    $modelRentedPercent[$m->model_id]['total'] = '--';
  }
}

//Base::varDamp($modelRentedPercent);

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
        <a class="nav-item nav-link" href="/bb/dohrash2.php">Свод доходов и расходов</a>
        <a class="nav-item nav-link" href="/bb/tovar_report.php">Товары (динамика)</a>
      </div>
    </div>
  </nav>
<div class="row">
  <div class="col">


  </div>

</div>
  <?php foreach ($cats as $cat): ?>
    <?= $cat->getName().', ' ?>
  <?php endforeach; ?>
  <div class="row">
    <div class="col">
      <select form="srch_form" name="cat_ids[]" multiple id="cat_ids_select" class="form-control-sm subrazdel-filter choices-multiple" aria-label="multiple select example">
        <?php foreach ($allCats as $c): ?>
          <option value="<?= $c->getId() ?>" <?= (in_array($c->getId(), $cat_ids) ? 'selected' : '') ?>><?= $c->getName() ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
<table class="table table-hover table-sm" data-year="<?=$year?>">
  <thead>
    <tr>
      <th scope="col">Год
        <form action="" method="post" class="d-inline" id="srch_form" name="srch_form">
          <input name="year" type="number" min="2000" max="<?= (new \DateTime())->format("Y")?>" value="<?= $year ?>"><input type="submit" value="->">
          <div class="form-check form-check-inline ms-4">
            <label class="form-check-label" for="show_models">детали по моделям</label>
            <input class="form-check-input" type="checkbox" id="show_models" name="show_models" value="1" <?= ($showModels ? 'checked' : '') ?> >
          </div>
        </form>
      </th>
      <?php for ($i=1; $i<=12; $i++): ?>
        <th scope="col" class="text-right"><?= $i ?></th>
      <?php endfor; ?>
      <th scope="col" class="text-right">Итого</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Количесвто дней в месяце</td>
      <?php for ($i=1; $i<=12; $i++): ?>
        <td class="text-right"><?= $daysInMonth[$i] ?></td>
      <?php endfor; ?>
      <td class="text-right">---</td>

    </tr>
    <tr class="">
      <td>Количество товара</td>
      <?php for ($i=1; $i<=12; $i++): ?>
        <td class="text-right"><?= $tovarKolVo[$i] ?></td>
      <?php endfor; ?>
      <td class="text-right">---</td>
    </tr>

    <tr class="">
      <?php $startDay = clone $startOfYear; ?>
      <td>Количество дней для сдачи</td>
      <?php for ($i=1; $i<=12; $i++): ?>
        <td class="text-right"><?= ($daysInMonth[$i] * $tovarKolVo[$i]) ?></td>
      <?php endfor; ?>
      <td class="text-right">---</td>
    </tr>

    <tr class="">
      <?php $startDay = clone $startOfYear; ?>
      <td>Количество дней сдано</td>
      <?php for ($i=1; $i<=12; $i++): ?>
        <td class="text-right"><?= $daysRentedOut[$i] ?></td>
      <?php endfor; ?>
      <td class="text-right">---</td>
    </tr>

    <tr class="">
      <?php $startDay = clone $startOfYear; ?>
      <td>% нахождения в аренде</td>
      <?php for ($i=1; $i<=12; $i++): ?>
        <td class="text-right"><?= (($daysInMonth[$i] * $tovarKolVo[$i])>0 ? number_format( $daysRentedOut[$i]/($daysInMonth[$i] * $tovarKolVo[$i])*100,1,',',' ') : 0)  ?>%</td>
      <?php endfor; ?>
      <td class="text-right"><?= number_format( $daysRentedOut['total'],1,',',' ') ?>%</td>
    </tr>

  </tbody>
</table>

<?php if ($showModels): ?>
  <table class="table table-hover table-sm" data-year="<?=$year?>">
  <thead>
  <tr>
    <th scope="col">Модель</th>
    <?php for ($i=1; $i<=12; $i++): ?>
      <th scope="col" class="text-right"><?= $i ?></th>
    <?php endfor; ?>
    <th scope="col" class="text-right">Итого</th>
  </tr>
  </thead>
  <tbody>
  <?php foreach ($models as $m): ?>
  <?php if (array_sum($modelTovarNum[$m->model_id])<1) continue; ?>
    <tr>
      <td><?= $m->getShortName() ?></td>
      <?php for ($i=1; $i<=12; $i++): ?>
        <td class="text-right"><?= $modelTovarNum[$m->model_id][$i] ?> <br><span style="font-style: italic"> <?= number_format(($modelRentedPercent[$m->model_id][$i]),1,',',' ') ?>%</span></td>
      <?php endfor; ?>
      <td class="text-right"><br><span style="font-style: italic"> <?= $modelRentedPercent[$m->model_id]['total'] ?>%</span></td>
    </tr>
  <?php endforeach; ?>

  </tbody>
</table>
<?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js@9.0.1/public/assets/styles/choices.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/choices.js@9.0.1/public/assets/scripts/choices.min.js"></script>

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

//dropdown
  .choices__list--dropdown .choices__item--selectable:after {
    content: '';
    font-size: unset;
    opacity: unset;
    position: unset;
    right: unset;
    top: unset;
    transform: unset;
  }
  .choices__list--dropdown .choices__item--selectable {
    padding-right: 5px;
  }
  th .choices{
    font-weight: normal!important;
  }
</style>

<script>
  new Choices(document.querySelector('[name="cat_ids[]"]'));
</script>

<?php



echo Base::pageEndHtmlB5();


?>
