<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Task.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php'); //


// ajax requests
if (isset($_POST['a_action'])) {
  $action = $_POST['a_action'];
  switch ($action){
    case 'done':
      $rez = new \stdClass();

      $id = $_POST['id'];
      $taskTmp = \bb\classes\Task::getById($id);
      $taskTmp->makeDone();
      $taskTmp->save();

      $rez->result='ok';
      $rez->newStatus=$taskTmp->getStatusTextName();
      $rez->newComments=$taskTmp->getComments();

      echo json_encode($rez);
      die();
      break;
    case 'in-process':
      $rez = new \stdClass();

      $id = $_POST['id'];
      $taskTmp = \bb\classes\Task::getById($id);
      $taskTmp->makeAccepted();
      $taskTmp->save();

      $rez->result='ok';
      $rez->newStatus=$taskTmp->getStatusTextName();
      $rez->newComments=$taskTmp->getComments();

      echo json_encode($rez);
      die();
      break;
    case 'approved':
      $rez = new \stdClass();

      $id = $_POST['id'];
      $taskTmp = \bb\classes\Task::getById($id);
      $taskTmp->makeApproved();
      $taskTmp->save();

      $rez->result='ok';
      $rez->newStatus=$taskTmp->getStatusTextName();
      $rez->newComments=$taskTmp->getComments();

      echo json_encode($rez);
      die();
      break;
    case 'add_comment':
      $rez = new \stdClass();

      $id = $_POST['id'];
      $message = $_POST['comments'];

      $taskM=\bb\classes\Task::getById($id);
      $taskM->addMessage($message);
      $taskM->save();

      $rez->result='ok';
      $rez->newComments=$taskM->getComments();

      echo json_encode($rez);
      die();
      break;
    case 'delete':
      $rez = new \stdClass();

      $id = $_POST['id'];
      $taskTmp = \bb\classes\Task::getById($id);
      $taskTmp->delete();

      $rez->result='ok';
      echo json_encode($rez);
      die();

      break;
  }
}

echo \bb\Base::pageStartB5('Задачи');
include_once($_SERVER['DOCUMENT_ROOT'] . '/bb/top_menu.php');
$v=15;
?>
<link rel="stylesheet" href="/bb/assets/styles/tasks.css?v=<?= $v ?>">
<?php

\bb\Base::loginCheck();

$targetUsers = \bb\models\User::getSalaryUsers();
$today = new DateTime();

$targetUsersIds = array_map(function (\bb\models\User $a){
  return $a->getId();
},$targetUsers);

$targetSrch=\bb\models\User::getCurrentUser()->getId();
if (!in_array($targetSrch, $targetUsersIds)) {
  $targetSrch=0;
}

$taskStatusSrch='all';
//\bb\Base::PostCheckVarDumpEcho();

if (isset($_POST['target-srch'])) {
  $targetSrch = $_POST['target-srch'];
}
if (isset($_POST['task-status-srch'])) {
  $taskStatusSrch = $_POST['task-status-srch'];
}

if (isset($_POST['action'])){
  echo 'works';
  $action = $_POST['action'];
  switch ($action){
    case 'new_task':
      if ($targetSrch=='all'){
        foreach ($targetUsersIds as $id) {
          $task = \bb\classes\Task::createTask(\bb\models\User::getCurrentUser()->getId(), $id, $_POST['task_text'], (new DateTime($_POST['deadline'])));
        }
      }
      else {
        $task = \bb\classes\Task::createTask(\bb\models\User::getCurrentUser()->getId(), $_POST['target-srch'], $_POST['task_text'], (new DateTime($_POST['deadline'])));
      }
      break;
    case 'add_comment':
      $id = $_POST['task_id'];
      $message = $_POST['comments'];

      $taskM=\bb\classes\Task::getById($id);
      $taskM->addMessage($message);
      $taskM->save();
      break;

    case 'accept':
      $id = $_POST['task_id'];
      $message = $_POST['comments'];

      $taskM=\bb\classes\Task::getById($id);
      $taskM->makeAccepted();
      $taskM->save();

      break;

    case 'done':
      $id = $_POST['task_id'];
      $taskM=\bb\classes\Task::getById($id);
      $taskM->makeDone();
      $taskM->save();

      break;

    case 'remove':
      $id = $_POST['task_id'];
      $taskM=\bb\classes\Task::getById($id);
      $taskM->makeRemoved();
      $taskM->save();

      break;

    case 'owner-update':
      $id = $_POST['task_id'];
      $taskM=\bb\classes\Task::getById($id);
      $taskM->makeOwnerChanges($_POST['task_text'], (new DateTime($_POST['deadline'])), ($_POST['comments']));
      $taskM->save();
      break;
  }
}

$tasks=\bb\classes\Task::getAllForUserStatus($targetSrch, $taskStatusSrch);
if (!$tasks) $tasks=[];
//\bb\Base::varDamp($tasks);
?>

<div class="conteiner-fluid">
  <input type="hidden" name="current-user-id" value="<?= \bb\models\User::getCurrentUser()->getId() ?>">
  <form method="post">
    <div class="row">
      <div class="col">
        <div class="form-floating">
          <select class="form-select" id="target-srch" name="target-srch" onchange="this.form.submit();">
            <option value="0" <?= \bb\Base::sel_d($targetSrch, "0") ?>>сотрудник</option>
            <?php if (\bb\models\User::getCurrentUser()->isManagement()): ?>
              <option value="all" <?= \bb\Base::sel_d($targetSrch, 'all') ?>>все сотрудники</option>
              <?php foreach ($targetUsers as $tUser): ?>
                <option value="<?= $tUser->getId() ?>" <?= \bb\Base::sel_d($targetSrch, $tUser->getId()) ?>><?= $tUser->getShortName() ?></option>
              <?php endforeach; ?>
            <?php else: ?>
              <option value="<?= \bb\models\User::getCurrentUser()->getId() ?>" <?= \bb\Base::sel_d($targetSrch, \bb\models\User::getCurrentUser()->getId()) ?>><?= \bb\models\User::getCurrentUser()->getShortName() ?></option>
            <?php endif; ?>
          </select>
          <label for="target-srch">Сотрудник</label>
        </div>
      </div>
      <div class="d-none d-md-block col-md-4">
        <?php if (\bb\models\User::getCurrentUser()->isManagement() && $targetSrch != '0' && $targetSrch != \bb\models\User::getCurrentUser()->getId()): ?>
          <button class="new-task-btn" type="button"><span>+</span></button>
        <?php endif; ?>
      </div>
      <div class="col">
        <div class="form-floating">
          <select class="form-select" id="task-status-srch" name="task-status-srch" onchange="this.form.submit();">
            <option value="all" <?= \bb\Base::sel_d('all', $taskStatusSrch) ?>>все задачи</option>
            <option value="actual" <?= \bb\Base::sel_d('actual', $taskStatusSrch) ?>>актуальные</option>
            <?php if(\bb\models\User::getCurrentUser()->isManagement()): ?>
              <option value="new" <?= \bb\Base::sel_d('new', $taskStatusSrch) ?>>непрочитанные</option>
            <?php endif; ?>
            <option value="done" <?= \bb\Base::sel_d('done', $taskStatusSrch) ?>>выполненные</option>
          </select>
          <label for="task-status-srch">Статус задачи</label>
        </div>
      </div>
    </div>
<!--    new task -->
    <div class="row new-task-row d-none">
      <div class="col">
        <div class="row mt-3">
          <div class="col-6">
            <div class="form-floating">
              <textarea class="form-control" placeholder="Задача" id="task_text" name="task_text" style="height: 100px"></textarea>
              <label for="task_text">Задача</label>
            </div>
          </div>
          <div class="col-2">
            <div class="form-floating">
              <input type="datetime-local" class="form-control" placeholder="deadline" id="deadline" name="deadline" value="<?= $today->format('Y-m-d 20:00') ?>"></input>
              <label for="deadline">Deadline</label>
            </div>
          </div>
        </div>
        <div class="row mt-1">
          <div class="col-3">
            <button type="button" name="action" value="new_task" class="btn btn-info new-task-submit-btn">Поставить задачу</button>
          </div>
        </div>
      </div>
    </div>
  </form>

  <?php
//  if ($targetSrch=='0' || !in_array($targetSrch, $targetUsers)){
//    die('</div></body></html>');
//  }

    ?>
<!--new-->
  <div class="tasks_container">
    <div data-status="in-process" class="drop-div in-process">
      <?php foreach ($tasks as $tt): ?>
      <?php if ($tt->isNewOrInProcess()): ?>
        <div class="drop-task <?= $tt->isPastDue() ? 'pastdue' : '' ?>" data-status="<?= $tt->getStatus() ?>" data-create_who="<?= $tt->getCreateWho() ?>" <?= ($tt->getTargetWho()==\bb\models\User::getCurrentUser()->getId() ? 'draggable="true"' : '') ?> data-id="<?= $tt->getId() ?>" id="task-<?= $tt->getId() ?>">
          <div class="line1"><span class="status"><?= $tt->getStatusTextName() ?></span></div>
          <div class="task-message-container">
            <div class="date-line">
              <span><?= $tt->getCreationDatetime()->format('d/m/y') ?></span>
              <span><?= $tt->getCreationDatetime()->format('H:i') ?></span>
              <span><?= \bb\models\User::getUserById($tt->getCreateWho())->getShortName() ?> > <?= \bb\models\User::getUserById($tt->getTargetWho())->getShortName() ?></span>
            </div>
            <div class="text"><?= $tt->getTaskText() ?></div>
            <div class="deadline"><span>Срок исполнения</span><span><?= $tt->getDeadline()->format('d/m/y') ?></span></div>
          </div>
          <div class="comments">
            <?= $tt->getComments() ?>
          </div>
          <div class="new-comment">
          <div class="show-new-btn">+ Добавить комментарий</div>
          <textarea class="form-control hide"></textarea>
          <div class="btns hide">
            <button type="button" class="btn btn-info save-comment-btn">Добавить комментарий</button>
            <button type="button" class="btn btn-close"></button>
          </div>

        </div>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <div data-status="done" class="drop-div done">
      <?php foreach ($tasks as $tt): ?>
        <?php if ($tt->isDone() || $tt->isApproved()): ?>
          <div class="drop-task <?= $tt->isPastDue() ? 'pastdue' : '' ?>" data-status="<?= $tt->getStatus() ?>" data-create_who="<?= $tt->getCreateWho() ?>" <?= ($tt->getTargetWho()==\bb\models\User::getCurrentUser()->getId() ? 'draggable="true"' : '') ?>  data-id="<?= $tt->getId() ?>" id="task-<?= $tt->getId() ?>">
            <div class="line1"><span class="status"><?= $tt->getStatusTextName() ?></span></div>
            <div class="task-message-container">
              <div class="date-line">
                <span><?= $tt->getCreationDatetime()->format('d/m/y') ?></span>
                <span><?= $tt->getCreationDatetime()->format('H:i') ?></span>
                <span><?= \bb\models\User::getUserById($tt->getCreateWho())->getShortName() ?> > <?= \bb\models\User::getUserById($tt->getTargetWho())->getShortName() ?></span>
              </div>
              <div class="text"><?= $tt->getTaskText() ?></div>
              <div class="deadline"><span>Срок исполнения</span><span><?= $tt->getDeadline()->format('d/m/y') ?></span></div>
            </div>
            <div class="comments">
              <?= $tt->getComments() ?>
            </div>
            <div class="new-comment">
              <div class="show-new-btn">+ Добавить комментарий</div>
              <textarea class="form-control hide"></textarea>
              <div class="btns hide">
                <button type="button" class="btn btn-info save-comment-btn">Добавить комментарий</button>
                <button type="button" class="btn btn-close"></button>
              </div>

            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

  </div>
<!--end of new-->
  <?php
  //make tasks read
    foreach ($tasks as $t3){
      if ($t3->isNew() && $t3->getTargetWho() == \bb\models\User::getCurrentUser()->getId()) {
        $t3->makeAccepted();
        $t3->save();
      }
    }
  ?>

</div>
<ul class="context-menu d-none">
  <li data-action="approved">Принять</li>
  <li data-action="back-to-in-process">Вернуть на доработку</li>
  <li data-action="delete">Удалить</li>
</ul>
<script src="/bb/assets/js/tasks.js?v=<?= $v ?>"></script>

<?php
echo \bb\Base::pageEndHtmlB5();
?>
