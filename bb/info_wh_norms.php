<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/WorkingHoursNorms.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/Office.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php'); //


echo \bb\Base::loginCheck();

echo \bb\Base::pageStartB5('Нормы часов');

//\bb\Base::PostCheckVarDumpEcho();

$now = new DateTime();

$year=$now->format('Y');

if (isset($_POST['year'])) $year=$_POST['year'];
if (isset($_POST['action']) && $_POST['action']=='save'){

  $year=$_POST['year'];
  $wh = new \bb\classes\WorkingHoursNorms($year);
  for ($i=1; $i<=12; $i++) {
    if (isset($_POST['m'.$i])){
      $wh->setHours($i, $_POST['m'.$i]);
    }
  }
  $wh->save();
}

$whn = \bb\classes\WorkingHoursNorms::getWorkingHoursNorms($year);

?>

<style>
  .row1{
    display: flex;
    flex-flow: row nowrap;
    gap: 10px;
    justify-content: space-between;
    width: 300px;
  }
</style>
  <div class="row">
    <?php include_once ($_SERVER['DOCUMENT_ROOT'].'/bb/top_menu.php'); ?>
  </div>

<form method="post">
  <div class="row1">
    <select name="year" class="form-select" aria-label="Default select example" style="max-width: 150px" onchange="this.form.submit();">
      <option value="<?= $year-1 ?>" <?= \bb\Base::sel_d($year, $year-1) ?>><?= $year-1 ?></option>
      <option value="<?= $year ?>" <?= \bb\Base::sel_d($year, $year) ?>><?= $year ?></option>
      <option value="<?= $year+1 ?>" <?= \bb\Base::sel_d($year, $year+1) ?>><?= $year+1 ?></option>
    </select>
    <button class="btn btn-success" type="submit" name="action" value="save">Сохранить</button>
  </div>
  <table class="table" style="max-width: 300px">
    <thead>
      <tr>
        <th style="max-width: 60px;">месяц</th>
        <th>норма часов</th>
      </tr>
    </thead>
    <tbody>
      <?php for ($i=1; $i<=12; $i++): ?>
        <tr>
          <td><?= \bb\Base::monthName($i) ?></td>
          <td><input name="m<?= $i ?>" class="form-control" type="number" min="0" max="744" step="1" value="<?= $whn->getWorkingHoursForMonth($i) ?>" style="text-align: right"></td>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>
  <button class="btn btn-success" type="submit" name="action" value="save" style="margin-left: 190px;">Сохранить</button>
</form>


<?php
echo \bb\Base::pageEndHtmlB5();

