<?php

session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Razdel.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/SubRazdel.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Model.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/ModelWeb.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Picture.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/KbZayavka.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/app/MyClasses/MainPage.php'); //


echo \bb\Base::pageStartB5('Карнавальные заявки.');
\bb\Base::loginCheck();
//\bb\Base::PostCheckVarDumpEcho();
//\bb\Base::varDamp($_FILES);
?>
<style>
  .limited{
    max-height: 50px;
  }
  .img{
    cursor: pointer;
    transition: all 0.5s;

  }
</style>


  <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
      <div class="navbar-nav">
        <a class="nav-item nav-link" href="/bb/kb.php">Карнавал</a>
      </div>
    </div>
  </nav>


<?php

  $zayavki = \bb\classes\KbZayavka::getAll();
//  \bb\Base::varDamp($zayavki);
?>

  <table class="table table-hover">
    <thead>
      <tr>
        <th scope="col" style="width: 100px;">Дата заявки</th>
        <th scope="col" style="width: 100px;">Дата утренника</th>
        <th scope="col" style="width: 100px;">Фото</th>
        <th scope="col" style="width: 500px;">Костюм</th>
        <th scope="col" style="width: 100px;">Рост</th>
        <th scope="col" style="width: 170px;">Телефон</th>
        <th scope="col">Действия</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($zayavki as $z): ?>
      <?php
        $today = new DateTime();
        $today->setTime(0,0,0);
        if ($z->getEventDate()<$today) continue;
      ?>
        <tr>
          <td style="font-size: 12px; color: #d3d3d4;">
            <?= $z->getCrWhen()->format('d.m.Y') ?>
            <?= $z->getCrWhen()->format('H:i') ?>
          </td>
          <td><?= $z->getEventDate()->format('d.m.Y') ?></td>
          <td><img class="img limited" src="<?= (\bb\classes\ModelWeb::getByModelId($z->getModelId())->getL2PicUrlAddress()) ?>"></td>
          <td>
            <?= (\bb\classes\Model::getById($z->getModelId())->getShortNameModelOnly()) ?>
            <?= ('<span style="display: none;">id модели: '.$z->getModelId().'</span>')?>
          </td>
          <td><?= ($z->getRostFrom().'-'.$z->getRostTo()) ?></td>
          <td><?= $z->getPhone() ?></td>
          <td>---</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <script>
    let pics = document.querySelectorAll('.img');
    pics.forEach(el=>{
      el.addEventListener('click', picClick);
    });


    function picClick(e){
      e.currentTarget.classList.toggle('limited');
    }

  </script>

<?php

echo \bb\Base::pageEndHtmlB5();
