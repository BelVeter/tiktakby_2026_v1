<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/Office.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/WorkShift.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/WorkingHoursNorms.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Permission.php'); // включаем подключение к базе данных

\bb\Base::loginCheck();

if (isset($_POST['a_action'])) {
  $mysqli = \bb\Db::getInstance()->getConnection();
  $action = $mysqli->real_escape_string($_POST['a_action']);
  switch ($action) {
    case 'get-schedule':
      $rez = new \stdClass();

      $userId = $_POST['employee'];

      $office = $_POST['place'];
      if (is_numeric($office)) {
        $office_id = $office;
        $office_type = 'office';
      }
      else{
        $office_id = '';
        $office_type = $office;
      }
      $off = \bb\models\Office::getOfficeByNumber($office);
      if (!$off) {
        $off = new \bb\models\Office();
        $off->setWorkingTime(10,0,19,0,10,0,19,0);
      }

      $startDate = new DateTime($_POST['start_date']);
      $rezStr = '';

      if(\bb\classes\WorkShift::getAllForWeekForUserPlace($userId, $startDate, $office_type, $office_id)){
        $userHasSomethingOnWeel=true;
      }
      else{
        $userHasSomethingOnWeel=false;
      }

      for ($i=0; $i<7; $i++) {

        $tmpShift = \bb\classes\WorkShift::getAllForUserDayPlace($userId, $startDate, $office_type, $office_id);
        if ($tmpShift){
          $t=$tmpShift[0];
          $selectedOpen=$t->getStartTime();
          $selectedClose=$t->getFinishTime();
        }
        else{
          $selectedOpen=false;
          $selectedClose=false;
        }

        if($userHasSomethingOnWeel) $rezStr.=getDayBlockFor($startDate, $off, $selectedOpen, $selectedClose);
        else $rezStr.=getDayBlockFor($startDate, $off, $off->getOpenDateTimeObject($startDate)->format('H.i'), $off->getCloseDateTimeObject($startDate)->format('H.i'));
        $startDate->modify('+1 day');
      }

      $rezStr.='<input class="btn btn-outline-success" type="submit" name="action" value="Сохранить">';

      $rez->result='ok';
      $rez->options = $rezStr;

      echo json_encode($rez);
      die();
      break;
  }
}
if (isset($_POST['action'])) {
  $action = $_POST['action'];

  switch ($action){
    case 'Сохранить':
      $startDate = new DateTime($_POST['start_date']);
      $userId=$_POST['employee'];

      $office = $_POST['place'];
      if (is_numeric($office)) {
        $office_id = $office;
        $office_type = 'office';
      }
      else{
        $office_id = '';
        $office_type = $office;
      }

      $shifts = [];

      for ($i=1; $i<=7; $i++) {
        if ($_POST['start-'.$i]=='0' || $_POST['finish-'.$i]=='0') continue;

        $sh = new \bb\classes\WorkShift();
        $sh->setUserId($userId);

        if (is_numeric($_POST['place'])){
          $sh->setPlaceType('office');
          $sh->setPlaceId($_POST['place']);
        }
        else{
          $sh->setPlaceType($_POST['place']);
        }

        if ($i==1) {
          $sh->setDate($startDate);
        }
        else{
          $tmpDate = clone $startDate;
          $tmpDate->modify('+'.($i-1).' days');
          $sh->setDate($tmpDate);
        }
        $sh->setStartTime($_POST['start-'.$i]);
        $sh->setFinishTime($_POST['finish-'.$i]);

        $shifts[] = $sh;
      }

      \bb\classes\WorkShift::deleteWeekForUserAndPlace($userId, $office_type, $office_id, $startDate);

      foreach ($shifts as $s) {
        $s->save();
      }

//      \bb\Base::varDamp($shifts);

      break;
  }
}
echo \bb\Base::pageStartB5();

//\bb\Base::PostCheckVarDumpEcho();

$today = new DateTime();
$periodLengthInDays = 7;

if (isset($_POST['start-monday'])) {
  $startMonday = new DateTime($_POST['start-monday']);
}
else{
  $startMonday = clone $today;
  $startMonday->modify('-'.($startMonday->format('N')-1).' days');
}

$previousMonday = clone $startMonday;
  $previousMonday->modify('-7 days');
$nextMonday = clone $startMonday;
  $nextMonday->modify('+7 days');


$finishSunday = clone $startMonday;
  $finishSunday->modify('+6 days');
$previousSunday = clone $finishSunday;
  $previousSunday->modify('-7 days');
$nextSunday = clone $finishSunday;
  $nextSunday->modify('+7 days');

$officeTypesArray=[
  ['office', 1, 'Лит.'],
  ['office', 2, 'Лож.']
];

if(\bb\classes\WorkShift::hasAnyForOfficeAndPriod('home', '', $startMonday, $finishSunday)){
  $officeTypesArray[]=['home', '', 'Дом'];
}
if(\bb\classes\WorkShift::hasAnyForOfficeAndPriod('vacation', '', $startMonday, $finishSunday)){
  $officeTypesArray[]=['vacation', '', 'Отп.'];
}

$tmpDate = clone $startMonday;

function getDayBlockFor(DateTime $day, \bb\models\Office $office, $selectedOpen=false, $selectedClose=false){
  $now = new DateTime();
//  echo var_dump($selectedOpen).'||';
  $rez = '
      <div class="sch-day">
        <span class="week-day">'.\bb\Base::getDayNameLong($day->format("N")).'</span>
        <select name="start-'.$day->format('N').'">
          '.getTimeSelectOptions($office->getOpenDateTimeObject($day), $office->getCloseDateTimeObject($day), $selectedOpen).'
        </select>
        <select name="finish-'.$day->format('N').'">
          '.getTimeSelectOptions($office->getOpenDateTimeObject($day), $office->getCloseDateTimeObject($day), $selectedClose).'
        </select>
        <button class="btn btn-close day-cancel '.(($now>(clone $day)->setTime(23,59) && !(\bb\models\User::getCurrentUser()->isManagement() || \bb\models\User::getCurrentUser()->getId()==9) ) ? 'invisible' : '').'" type="button"></button>
      </div>
  ';
  return $rez;
}

function getTimeSelectOptions(DateTime $startTime, DateTime $finishTime, $selectedTime=false) {
  $now = new DateTime();
    $now->setTime($startTime->format('H'),$startTime->format('i'));

  $startTimeEndOfDay = clone $startTime;
    $startTimeEndOfDay->setTime(23,59);


  $start = clone $startTime;
    $start->modify('-1 hour');
  $finish = clone $finishTime;
    $finish->modify('+1 hour');
  $rez= '<option value="0">--</option>';
    $restrictedRez = '<option value="0">--</option>';;
  $count = 0;
  while ($count<=24 && $start<=$finish) {
    $rez.= '<option value="'.$start->format("H.i").'" '.($start->format("H.i")==$selectedTime ? 'selected' : '').'>'.$start->format("H.i").'</option>';

    if ($start->format("H.i")==$selectedTime) {
      $restrictedRez = '<option value="'.$start->format("H.i").'" '.($start->format("H.i")==$selectedTime ? 'selected' : '').'>'.$start->format("H.i").'</option>';
    }

    $start->modify("+30minutes");
    $count++;
  }
  //earlier it was if ($now<$startTime
  if ($now<$startTimeEndOfDay || \bb\models\User::getCurrentUser()->isManagement() || \bb\models\User::getCurrentUser()->getId()==9) return $rez;
  else return $restrictedRez;
//  return $rez;
}

//calculation of work time
$weekHours = \bb\classes\WorkShift::getWorkHorsCalculationForUsers($startMonday, $finishSunday);

$startMonthDay = new DateTime();
  $startMonthDay->setDate($startMonday->format('Y'), $startMonday->format('m'), 1);
$endMonthDay = new DateTime();
  $endMonthDay->setDate($startMonday->format('Y'), $startMonday->format('m')+1, 1);
  $endMonthDay->modify('-1 day');
//echo $startMonthDay->format('d.m.Y').'---'.$endMonthDay->format('d.m.Y');
$monthHours = \bb\classes\WorkShift::getWorkHorsCalculationForUsers($startMonthDay, $endMonthDay);

$wshs=[];

foreach ($weekHours as $key => $value){
  $wshs[$key] = $value;
}
foreach ($monthHours as $key => $value){
  if (isset($wshs[$key])) $wshs[$key][]=$value[1];
  else $wshs[$key]=[$value[0],0,$value[1]];
}

$whNorms = new \bb\classes\WorkingHoursNorms($startMonthDay->format('Y'));
$whNorm = $whNorms->getWorkingHoursForMonth($startMonthDay->format('m')*1);

?>
<link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/bb/assets/styles/working_hours.css?v=9">
<link rel="stylesheet" href="/bb/stile.css">
<div class="main-container">
  <?php include_once ($_SERVER['DOCUMENT_ROOT'].'/bb/top_menu.php'); ?>
  <div class="months-row"><?= \bb\Base::monthName($startMonday->format('m')) ?>, <?= $startMonday->format('Y') ?> - <?= \bb\Base::monthName($finishSunday->format('m')) ?> <?= $finishSunday->format('Y') ?><button id="month_view" class="float-end btn btn-outline-danger">раскладка на месяц</button><a href="/bb/info_wh_norms.php" class="btn btn-info float-end">нормы</a> </div>
  <div class="dates-row">
    <div class="prev-next_date">
      <form method="post" onclick="this.submit();" id="prev-week-form" style="cursor: pointer;">
        <input type="hidden" name="start-monday" value="<?= $previousMonday->format('Y-m-d') ?>">
        <?= $previousMonday->format('d.m') ?>-<?= $previousSunday->format('d.m') ?>
      </form>
    </div>
    <div class="arrow" onclick="document.querySelector('#prev-week-form').submit();" style="cursor: pointer"><</div>
    <div data-from="<?= $startMonday->format('Y-m-d') ?>" class="current_date"><?= $startMonday->format('d.m') ?>-<?= $finishSunday->format('d.m') ?></div>
    <div class="arrow" onclick="document.querySelector('#next-week-form').submit();" style="cursor: pointer">></div>
    <div class="prev-next_date">
      <form method="post" onclick="this.submit();" id="next-week-form" style="cursor: pointer;">
        <input type="hidden" name="start-monday" value="<?= $nextMonday->format('Y-m-d') ?>">
        <?= $nextMonday->format('d.m') ?>-<?= $nextSunday->format('d.m') ?>
      </form>
    </div>
  </div>

  <!-- offices -->
  <div class="container-scroll">
    <?php foreach ($officeTypesArray as $ofType): ?>
      <div class="office_row <?= $ofType[0] ?>">
        <?php $tmpDate = clone $startMonday; ?>
        <?php for ($i=1; $i<=7; $i++): ?>
          <div class="day">
            <div class="hour-greed">
              <div class="hour"><span>9</span></div>
              <div class="hour"><span>10</span></div>
              <div class="hour"><span>11</span></div>
              <div class="hour"><span>12</span></div>
              <div class="hour"><span>13</span></div>
              <div class="hour"><span>14</span></div>
              <div class="hour"><span>15</span></div>
              <div class="hour"><span>16</span></div>
              <div class="hour"><span>17</span></div>
              <div class="hour"><span>18</span></div>
              <div class="hour"><span>19</span></div>
              <div class="hour"><span>20</span></div>
            </div>
            <?php foreach (\bb\classes\WorkShift::getAllForDay($tmpDate, $ofType[0], $ofType[1]) as $ws): ?>
              <div class="employee-hours" style="background-color: <?= \bb\models\User::getUserById($ws->getUserId())->getColor() ?>; width: <?= $ws->getWidth() ?>px; margin-left: <?= $ws->getMargingLeft() ?>px;"><?= \bb\models\User::getUserById($ws->getUserId())->getShortName() ?> </div>
            <?php endforeach; ?>
            <div class="current-office-name"><?= $ofType[2] ?></div>
            <div class="current-date"><?= \bb\Base::getDayNameShort($tmpDate->format('N')) ?> <?= \bb\Base::getShortMonth($tmpDate->format('m')) ?> <?= $tmpDate->format('d') ?></div>
          </div>
          <?php $tmpDate->modify('+1 day'); ?>
        <?php endfor; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <!-- end of offices -->


  <div class="new-shift_row">
    <?php if (\bb\models\User::getCurrentUser()->isManagement() || \bb\models\User::getCurrentUser()->getId()==9): ?>
      <form method="post" class="new-shift_container" id="new-data-form">
      <div class="form-floating">
        <input type="hidden" name="start_date" value="<?= $startMonday->format('Y-m-d') ?>">
        <input type="hidden" name="start-monday" value="<?= $startMonday->format('Y-m-d') ?>">
        <select class="form-select shift-select" name="employee" id="employee">
          <option value="0">Выберите сотрудника</option>
          <?php if (\bb\models\User::getCurrentUser()->isManagement()): ?>
            <?php foreach (\bb\models\User::getSalaryUsers() as $em): ?>
              <option value="<?= $em->getId() ?>"><?= $em->getShortName() ?></option>
            <?php endforeach; ?>
          <?php else: ?>
            <option value="9">Света</option>
          <?php endif; ?>
        </select>
        <label class="shift-select-label" for="employee">Сотрудник</label>
      </div>

      <div class="form-floating">
        <select class="form-select shift-select" name="place" id="place">
          <option value="0">Выберите место работы</option>
          <?php foreach (\bb\models\Office::getAllActiveOffices() as $of): ?>
            <option value="<?= $of->getIdOffice() ?>"><?= $of->getShortName() ?></option>
          <?php endforeach; ?>
          <option value="home">Работа на дому</option>
          <option value="vacation">Отпуск</option>
        </select>
        <label class="shift-select-label" for="place">Место работы</label>
      </div>

      <div class="week-schedule" id="week-schedule">

      </di>
    </div>
    </form>
    <?php endif; ?>
    <?php if ($wshs): ?>
      <table class="table table-hover work-hours" style="max-width: 700px;">
          <tr>
            <th rowspan="2" style="vertical-align: middle">Сотрудник</th>
            <th colspan="3" style="text-align: center">Количество часов</th>
          </tr>
        <tr>
            <th>Текущая неделя</th>
            <th>С начала месяца (<?= \bb\Base::monthName($startMonday->format('m')) ?>)</th>
            <th>Норма <?= $whNorm ?> ч.</th>
          </tr>
          <?php foreach ($wshs as $wsh): ?>
            <tr>
              <td><?= $wsh[0] ?></td>
              <td style="text-align: right; padding-right: 80px"><?= $wsh[1] ?></td>
              <td style="text-align: right; padding-right: 80px"><?= $wsh[2] ?></td>
              <td style="text-align: right;"><?= $wsh[2] - $whNorm ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
    <?php endif; ?>
  </div>

</div>
<!-- Modal Overlay -->
<div id="modalOverlay" class="modal-overlay" onclick="closeModal()"></div>

<!-- Modal -->
<div id="myModal" class="modal">
  <span class="close" onclick="closeModal()">&times;</span>
  <div id="modalContent"></div>
</div>



<script src="/bb/assets/js/working_hours.js?v=4"></script>

<?php
echo \bb\Base::pageEndHtmlB5();
?>


