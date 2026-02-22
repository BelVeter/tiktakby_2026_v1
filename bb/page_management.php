<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Razdel.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/SubRazdel.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/app/MyClasses/MainPage.php'); //


echo \bb\Base::pageStartB5('Pages.');
\bb\Base::loginCheck();
//\bb\Base::PostCheckVarDumpEcho();
//\bb\Base::varDamp($_FILES);

$levelCode = 0;
$urlKey = 0;
$lang = 'ru';
$urlKeyArray = [];
//$_POST['level_code'] = 'razdel';

if (isset($_POST['level_code'])) {
  $levelCode = $_POST['level_code'];
  switch ($levelCode) {
    case 'main':
      $urlKeyArray[] = ['main', 'Главная страница'];
      $urlKeyArray[] = ['about', 'О нас'];
      $urlKeyArray[] = ['conditions', 'Условия проката'];
      $urlKeyArray[] = ['delivery', 'Доставка и оплата'];
      $urlKeyArray[] = ['contacts', 'Контакты'];
      $urlKeyArray[] = ['policy', 'Политика по данным'];
      break;
    case 'razdel':
      $razdels = \bb\classes\Razdel::getAll();
      foreach ($razdels as $r) {
        $urlKeyArray[] = [$r->getUrlRazdelName(), $r->getNameRazdelText()];
      }
      break;
    case 'subrazdel':
      $subRazdels = \bb\classes\SubRazdel::getAll();
      foreach ($subRazdels as $r) {
        $urlKeyArray[] = [$r->getUrlSubRazdelName(), $r->getNameSubRazdelText()];
      }
      break;
    case 'category':
      $cats = \bb\classes\Category::getAllCategories();
      foreach ($cats as $cat) {
        $urlKeyArray[] = [$cat->getCatUrlKey(), $cat->getName()];
      }
      break;
  }
}
if (isset($_POST['url_key'])) {
  $urlKey = $_POST['url_key'];
  //in case Razdel changed = reset the URL key
  $rez = array_filter($urlKeyArray, function ($a) use ($urlKey) {
    return $a[0] == $urlKey;
  });
  if (count($rez) < 1) {
    $urlKey = 0;
  }
}
if (isset($_POST['lang'])) {
  $lang = $_POST['lang'];
}

if (isset($_POST['action'])) {
  switch ($_POST['action']) {
    case 'save':
      $np = new \App\MyClasses\MainPage($_POST['lang'], $_POST['level_code'], $_POST['url_key']);
      if (isset($_POST['id']))
        $np->setId($_POST['id']);
      $np->setMetaDescription($_POST['meta_description']);
      $np->setTitle($_POST['title']);
      $np->setH1($_POST['h1']);
      $np->setH1LongText($_POST['h1_long_text']);
      $np->setCodeBlock1($_POST['code1']);
      $np->setBlock2Title($_POST['block_2_title']);
      $np->setCodeBlock2($_POST['code2']);


      //icon
      $dir_short = '/public/img/topmenu/';
      $dir_full = $_SERVER['DOCUMENT_ROOT'] . $dir_short;

      //if file sent
      if (key_exists('h1_pic_file', $_FILES) && $_FILES['h1_pic_file']['name'] != '') {
        //echo 'file set part launched';
        $file_name = $_FILES['h1_pic_file']['name'];

        if (key_exists('h1_pic_file', $_FILES)) {
          $savedPath = \bb\Base::processAndSaveImageAsWebp($dir_short, $_FILES['h1_pic_file']['tmp_name'], $file_name);
          if ($savedPath)
            $np->setH1PicUrl($savedPath);
        }
        //delete old file if exists
        if ($np->getId() > 0) {
          $old_page = \App\MyClasses\MainPage::getPageById($np->getId());
          if ($old_page->getH1PicUrl() != '') {
            \bb\Base::delFile($old_page->getH1PicUrl());
          }
        }
      } else {//if file not sent - get existing link
        $old_page = \App\MyClasses\MainPage::getPageOrFillInfroFromRuOrCreateNew($_POST['lang'], $_POST['level_code'], $_POST['url_key']);
        $np->setH1PicUrl($old_page->getH1PicUrl());
      }


      $np->save();
      break;
  }
}


$p = \App\MyClasses\MainPage::getPageOrFillInfroFromRuOrCreateNew($lang, $levelCode, $urlKey);
?>
<link rel="stylesheet" href="/bb/assets/styles/page.css">
<!--code editor -->
<script src="https://cdn.tiny.cloud/1/bacqe8vq7etx5keuau4n12gqdblggd9ttqjey812a2qk0d83/tinymce/6/tinymce.min.js"
  referrerpolicy="origin"></script>
<script>
  tinymce.init({
    relative_urls: false,
    selector: '.code-filed',
    plugins: 'image autolink lists media table code link',
    toolbar: 'a11ycheck addcomment showcomments casechange checklist bullist numlist code export formatpainter image editimage pageembed permanentpen table tableofcontents undo redo bold italic alignleft aligncenter alignright alignjustify outdent indent link image forecolor backcolor emoticons link',
    toolbar_mode: 'floating',
    tinycomments_mode: 'embedded',
    tinycomments_author: 'Author name',
  });
</script>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <a class="navbar-brand" href="/bb/">Главная</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup"
    aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
    <div class="navbar-nav">
      <a class="nav-item nav-link" href="/bb/kr_baza_new.php">Товары</a>
      <a class="nav-item nav-link" href="/bb/favorite_tovars_management.php">Популярные товары</a>
      <a class="nav-item nav-link" href="/bb/webp_converter.php">Конвертер WebP</a>
      <a class="nav-item nav-link" href="/bb/l3_karn_dop.php">Доп.поля карнавала</a>
    </div>
  </div>
</nav>
<div class="container-fluid">
  <form name="filter-form" id="filter-form" method="post">
    <?php $levelCode . '---' . $urlKey . '---' . $lang ?>
    <div class="row">
      <div class="col-sm-12 col-md-3">
        <label for="level_code">Уровень страницы</label>
        <select class="form-select page-filter-select" name="level_code" id="level_code">
          <option value="0" <?= \bb\Base::sel_d(0, $levelCode) ?>>Выберите</option>
          <option value="main" <?= \bb\Base::sel_d('main', $levelCode) ?>>Первый уровень</option>
          <option value="razdel" <?= \bb\Base::sel_d('razdel', $levelCode) ?>>Раздел</option>
          <option value="subrazdel" <?= \bb\Base::sel_d('subrazdel', $levelCode) ?>>Подраздел</option>
          <option value="category" <?= \bb\Base::sel_d('category', $levelCode) ?>>Категория</option>
        </select>
      </div>
      <div class="col-sm-12 col-md-3" style="z-index: 10">
        <label for="level_code">конкретная страница</label>
        <select class="form-select page-filter-select choices-single concrete-page" name="url_key" id="level_code">
          <option value="0" <?= \bb\Base::sel_d(0, $p->getUrlKey()) ?>>Выберите страницу</option>
          <?php foreach ($urlKeyArray as $ar): ?>
            <option value="<?= $ar[0] ?>" <?= \bb\Base::sel_d($ar[0], $urlKey) ?>><?= $ar[1] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-1 col-md-1">
        <label for="level_code">язык</label>
        <select class="form-select page-filter-select" name="lang" id="lang">
          <option value="ru" <?= \bb\Base::sel_d('ru', $lang) ?>>RU</option>
          <option value="lt" <?= \bb\Base::sel_d('lt', $lang) ?>>LT</option>
          <option value="en" <?= \bb\Base::sel_d('en', $lang) ?>>EN</option>
        </select>
      </div>
    </div>

  </form>
  <?php if ($lang && $levelCode && $urlKey): ?>
    <form class="" name="page-form" id="page-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="lang" value="<?= $p->getLang() ?>">
      <input type="hidden" name="level_code" value="<?= $p->getLevelCode() ?>">
      <input type="hidden" name="url_key" value="<?= $p->getUrlKey() ?>">
      <div class="row">
        <div class="col text-center <?= ($p->getId() > 0 ? 'alert-success' : 'alert-warning') ?>">
          <?= ($p->getId() > 0 ? 'Редактируем существующую страницу.' : 'Создаем новую страницу') ?>
        </div>
      </div>
      <div class="row">
        <label class="col-2 col-form-label" for="title">Title страницы</label>
        <div class="col-4"><input class="form-control" type="text" name="title" id="title" value="<?= $p->getTitle() ?>">
        </div>
      </div>
      <div class="row">
        <label class="col-2 col-form-label" for="meta_description">Meta description (выдча в поиске гугл)</label>
        <div class="col-4"><input class="form-control" type="text" name="meta_description" id="meta_description"
            value="<?= $p->getMetaDescription() ?>"></div>
      </div>
      <div class="row mb-3 align-items-end">
        <label class="col-2 col-form-label" for="h1">H1</label>
        <div class="col-4"><input class="form-control" type="text" name="h1" id="h1" value="<?= $p->getH1() ?>"></div>
        <div class="col-3">
          <label for="h1pic" class="form-label">Картинка H1</label>
          <input name="h1_pic_file" class="form-control" type="file" id="h1pic" accept=".gif,.jpg,.jpeg,.png,.svg,.webp">
        </div>
        <div class="col-1">
          <img class="img img-fluid" src="<?= $p->getH1PicUrl() ?>">
        </div>
      </div>
      <div class="row">
        <label class="col-2 col-form-label" for="h1_long_text">Длинный текст на заголовок листинга</label>
        <div class="col-6">
          <textarea class="code-filed" name="h1_long_text" id="h1_long_text"><?= $p->getH1LongText() ?></textarea>
        </div>
      </div>
      <div class="row">
        <label class="col-2 col-form-label" for="h1">Code block 1</label>
        <div class="col-6">
          <textarea class="code-filed" name="code1" id="code1"><?= $p->getCodeBlock1() ?></textarea>
        </div>
      </div>
      <div class="row mt-3">
        <label class="col-2 col-form-label" for="block_2_title">Заголовок для блока 2:</label>
        <div class="col-4"><input class="form-control" type="text" name="block_2_title" id="block_2_title"
            value="<?= $p->getBlock2Title() ?>"></div>
      </div>
      <div class="row">
        <label class="col-2 col-form-label" for="code2">Code block 2</label>
        <div class="col-6">
          <textarea class="code-filed" name="code2" id="code2"><?= $p->getCodeBlock2() ?></textarea>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <input type="hidden" name="id" value="<?= $p->getId() ?>">
          <input type="hidden" name="action" value="save">
          <input class="btn btn-success" type="submit" value="Сохранить">
        </div>
      </div>
    </form>
  </div><!-- end of container -->
<?php endif; ?>


<script src="/bb/assets/js/page.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js@9.0.1/public/assets/styles/choices.min.css" />
<script src="https://cdn.jsdelivr.net/npm/choices.js@9.0.1/public/assets/scripts/choices.min.js"></script>

<script>
  new Choices(document.querySelector(".concrete-page"));
</script>

<?php

echo \bb\Base::pageEndHtmlB5();




