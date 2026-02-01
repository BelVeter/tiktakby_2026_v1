<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Announcement.php'); //

echo \bb\Base::pageStartB5('Pages.');
\bb\Base::loginCheck();
//\bb\Base::PostCheckVarDumpEcho();

$typeCode='main';

if (isset($_POST['action'])) {
  switch ($_POST['action']){
    case 'save':

      $anOld = \bb\classes\Announcement::getMessageByType($_POST['type_code']);
      $an = new \bb\classes\Announcement();
      if ($anOld){
        $an->setId($anOld->getId());
      }

      $an->setTypeCode($_POST['type_code']);
      if (isset($_POST['active'])) $an->setActive(1);
        else $an->setActive(0);
      $an->setMessage($_POST['message']);
      if(isset($_POST['time_controll'])) $an->setTimeControll(1);
        else $an->setTimeControll(0);
      if (isset($_POST['start_time']) && $_POST['start_time'] != ''){
        $startTime = new DateTime($_POST['start_time']);
        $an->setStartTime($startTime);
      }
      if (isset($_POST['finish_time']) && $_POST['finish_time'] != ''){
        $finishTime = new DateTime($_POST['finish_time']);
        $an->setFinishTime($finishTime);
      }

      $an->save();

      break;
  }
}

$an = \bb\classes\Announcement::getMessageByType($typeCode);
if (!$an) {
  $an = new \bb\classes\Announcement();
  $an->setTypeCode($typeCode);
}
//else{
//  echo \bb\Base::varDamp($an);
//}


?>

<style>
  .main-container{
    display: flex;
    flex-flow: column nowrap;
    gap: 15px;
    max-width: 800px;
  }
  .first_line_container{
    display: flex;
    flex-flow: row wrap;
    align-items: center;
    gap: 15px;
  }
  .type_select{
    width: min-content !important;
  }
  .time_controll{
    display: flex;
    flex-flow: row wrap;
    gap: 15px;
  }
  .time_checkbox{
    display: flex;
    flex-flow: column nowrap;
    justify-content: center;
    align-items: center;
  }

</style>

  <nav class="navbar navbar-expand-lg navbar-light bg-light" style="padding-left: 20px">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
      <div class="navbar-nav">
<!--        <a class="nav-item nav-link" href="/bb/kr_baza_new.php">Товары</a>-->
<!--        <a class="nav-item nav-link" href="/bb/favorite_tovars_management.php">Популярные товары</a>-->
<!--        <a class="nav-item nav-link" href="/bb/l3_karn_dop.php">Доп.поля карнавала</a>-->
      </div>
    </div>
  </nav>
<div class="container-fluid mt-2">
  <form method="post" class="main-container">
    <div class="first_line_container">
      <select class="form-select type_select" aria-label="type_select" name="type_code">
        <option value="main">Основное объявление</option>
      </select>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="flexSwitchCheckDefault" name="active" <?= ($an->getActive() ? 'checked': '') ?>>
        <label class="form-check-label" for="flexSwitchCheckDefault">Включено/Выключено</label>
      </div>
    </div>
    <div class="form-floating">
      <textarea class="form-control" id="textarea1" title="Можно использовать HTML тэги" name="message"><?= $an->getMessage() ?></textarea>
      <label for="textarea1">Текст объявления</label>
    </div>
    <div class="time_controll">
      <div class="form-check form-switch time_checkbox">
        <input class="form-check-input" type="checkbox" id="ch2" name="time_controll" <?= ($an->getTimeControll() ? 'checked': '') ?>>
        <label class="form-check-label" for="ch2">Показывать в кнкретное время</label>
      </div>
      <div class="form-floating time <?= ($an->getTimeControll() ? '' : 'd-none') ?>">
        <input type="datetime-local" class="form-control" id="time_from" name="start_time" value="<?php
          if ($an->getStartTime()) {
            echo $an->getStartTime()->format("Y-m-d H:i");
          }
        ?>">
        <label for="time_from">действует с:</label>
      </div>
      <div class="form-floating time <?= ($an->getTimeControll() ? '' : 'd-none') ?>">
        <input type="datetime-local" class="form-control" id="time_to" name="finish_time" value="<?php
        if ($an->getFinishTime()) {
          echo $an->getFinishTime()->format("Y-m-d H:i");
        }
        ?>">
        <label for="time_to">действует по:</label>
      </div>
    </div>
    <div>
      <button class="btn btn-success savebtn" name="action" value="save">Сохранить</button>
    </div>
  </form>
</div>

<script>
  let chTime = document.querySelector('#ch2');
  let timeDivs = document.querySelectorAll('.time');
  let saveBtn = document.querySelector('.savebtn');
  let timeFrom = document.querySelector('#time_from');
  let timeTo = document.querySelector('#time_to');

  chTime.addEventListener('change', chTimeChange);
  saveBtn.addEventListener('click', saveBtnClick);

  function chTimeChange(){
    if (chTime.checked) {
      timeDivs.forEach(el=>{
        el.classList.remove('d-none');
      })
    }
    else {
      timeDivs.forEach(el=>{
        el.classList.add('d-none');
      })
    }
  }

  function saveBtnClick(e){
    let rez = true;
    let message = '';

    if (chTime.checked) {
      if (timeFrom.value=='' || timeTo.value=='') {
        e.preventDefault();
        rez=false;
        alert('Заполните поля времени')
      }
      else {
        from = new Date(timeFrom.value);
        to = new Date(timeTo.value);
        if (to<from) {
          e.preventDefault();
          rez=false;
          alert('поле "По", ранее поля "С"');
        }
      }
    }
  }
</script>

<?php
//var_dump($an->toShow());
echo \bb\Base::pageEndHtmlB5();
