<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Razdel.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/L3KarnDop.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/SubRazdel.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/app/MyClasses/MainPage.php'); //


echo \bb\Base::pageStartB5('Pages.');
\bb\Base::loginCheck();
//\bb\Base::PostCheckVarDumpEcho();


if (isset($_POST['action']) && $_POST['action']=='сохранить'){
  $dop = new \bb\classes\L3KarnDop();
  $dop->setTarif($_POST['tarif']);
  $dop->setDelivery($_POST['delivery']);
  $dop->setAddress($_POST['address']);
  $dop->setCollateral($_POST['collateral']);

  $dop->save();
}


$dop = \bb\classes\L3KarnDop::get();

?>

  <link rel="stylesheet" href="/bb/assets/styles/page.css">
<style>
  textarea{
    width: 100%;
    max-width: 700px;
  }
</style>
  <!--code editor -->
<!--  <script src="https://cdn.tiny.cloud/1/bacqe8vq7etx5keuau4n12gqdblggd9ttqjey812a2qk0d83/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>-->
<!--  <script>-->
<!--    tinymce.init({-->
<!--      relative_urls : false,-->
<!--      selector: '.code-filed',-->
<!--      plugins: 'image autolink lists media table code',-->
<!--      toolbar: 'a11ycheck addcomment showcomments casechange checklist bullist numlist code export formatpainter image editimage pageembed permanentpen table tableofcontents undo redo bold italic alignleft aligncenter alignright alignjustify outdent indent link image forecolor backcolor emoticons',-->
<!--      toolbar_mode: 'floating',-->
<!--      tinycomments_mode: 'embedded',-->
<!--      tinycomments_author: 'Author name',-->
<!--    });-->
<!--  </script>-->
  <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
      <div class="navbar-nav">
        <a class="nav-item nav-link" href="/bb/page_management.php">Работа со страницами</a>
      </div>
    </div>
  </nav>


  <form method="post" class="container-fluid">
    <div class="row">
      <div class="col">
        <div>Текст для тарифа:</div>
        <textarea name="tarif"><?= $dop->getTarif() ?></textarea>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <div>Текст для залога:</div>
        <textarea name="collateral"><?= $dop->getCollateral() ?></textarea>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <div>Текст для адреса:</div>
        <textarea name="address"><?= $dop->getAddress() ?></textarea>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <div>Текст для доставки:</div>
        <textarea name="delivery"><?= $dop->getDelivery() ?></textarea>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <input type="submit" name="action" value="сохранить">
      </div>
    </div>
  </form>

<!--  <textarea class="code-filed" name="code2" id="code2"></textarea>-->

<?php

echo \bb\Base::pageEndHtmlB5();




