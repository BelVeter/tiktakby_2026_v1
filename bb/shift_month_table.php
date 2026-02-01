<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 02.12.2018
 * Time: 15:58
 */

namespace bb;

use bb\classes\WorkShift;
use bb\classes\WorkShiftMonthReport;
use Classes\Deal;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');

Base::loginCheck();

//echo Base::PageStartAdvansed('Test Page');
//echo Base::PostCheck();

if (isset($_POST['date'])) $date = new \DateTime($_POST['date']);
else $date = new \DateTime();

$rep = WorkShiftMonthReport::getMonthReport($date);


?>

<table class="shift_table">
  <tr>
    <th></th>
    <th style="text-align: center;" colspan="<?= count($rep->getDaysArray()) ?>"><?= $rep->getDaysArray()[1]->format('F') ?></th>
  </tr>
  <tr>
    <th></th>
    <?php foreach ($rep->getDaysArray() as $d): ?>
      <th class="day_header week_day_<?= $d->format('w') == 0 ? '7' : $d->format('w') ?>"><?= $d->format('d') ?></th>
    <?php endforeach; ?>
  </tr>
  <?php foreach ($rep->getUserIdsArray() as $uId): ?>
    <tr>
      <td><?= \bb\models\User::getUserById($uId)->getShortName() ?></td>
      <?php foreach ($rep->getDaysArray() as $d): ?>
        <td class="week_day week_day_<?= $d->format('w') == 0 ? '7' : $d->format('w') ?> <?= $rep->getWorkHoursPlaceTypeClass($uId, $d->format('d')*1) ?>"
            title="<?php
              $tt = $rep->getWorkHoursPlaceTypeClass($uId, $d->format('d')*1);
              if ($tt=='vacation') echo 'Отпуск';
              if ($tt=='home') echo 'Работа из дома';
              if ($tt=='office1') echo 'Литературная';
              if ($tt=='office2') echo 'Ложинская';

            ?>">
          <?= $rep->getWorkHours($uId, $d->format('d')*1) ?>
        </td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
</table>



<?php

//Base::varDamp($rep);

echo Base::PageEndHTML();


?>

