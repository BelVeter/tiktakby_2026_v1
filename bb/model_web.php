<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Model.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/ModelWeb.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Model.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Picture.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Razdel.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/SubRazdel.php'); // включаем подключение к базе данных

\bb\Base::loginCheck();

$modelId = 0;
$lang = 'ru';

if (isset($_POST['model_id'])) $modelId = $_POST['model_id'];
if (isset($_POST['lang'])) $lang = $_POST['lang'];
if (isset($_POST['form_check'])){
  $result = new stdClass();

  $urlCode = $_POST['url_code'];

  $hasUrlDublicates = \bb\classes\ModelWeb::hasDublicatesPageUrlCode($urlCode, $modelId);

  $result->status='ok';
  $result->hasUrlDublicates = $hasUrlDublicates;

  header("Content-Type: application/json; charset=UTF-8");
  echo json_encode($result);
  die();
}

?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>3-й уровень</title>
  <link href="/public/css/bootstrap.min.css?v={{$v}}" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
  <link href="/bb/assets/styles/wm.css" rel="stylesheet">
</head>
<body>

<?php

//echo \bb\Base::PostCheck();

if (isset($_POST['action_command'])) {
  $action = \bb\Base::GetPost('action_command');
  switch ($action) {
    case 'save':
      $model = \bb\classes\Model::getById($modelId);
      //\bb\Base::varDamp($model->getCatId());
      $category = \bb\classes\Category::getById($model->getCatId());


      $mwOld = \bb\classes\ModelWeb::getByModelId($modelId, $lang);
        if (!$mwOld) $mwOld =  \bb\classes\ModelWeb::getByModelId($modelId, 'ru');
          if (!$mwOld) $mwOld = new \bb\classes\ModelWeb($modelId, $lang);

      $mw = \bb\classes\ModelWeb::getByModelId($modelId, $lang);
        if(!$mw) $mw = new \bb\classes\ModelWeb($modelId, $lang);

      $mw->setItemNameMain($_POST['name_main']);
      $mw->setL2Name($_POST['l2_name']);
      $mw->setTitle($_POST['title']);
      $mw->setMetaDescription($_POST['meta_description']);
      $mw->setMPicAlt($_POST['m_pic_alt']);
        $mw->setL2Alt($_POST['m_pic_alt']);
      $mw->setBreadcrumbsName($_POST['breadcrumbs_name']);
      $mw->setPageUrlCode($_POST['url_code']);
      $mw->setMATitle($_POST['m_a_title']);
      $mw->setMainDescrHtml($_POST['main_descr']);
      $mw->setTarifLinePeriod($_POST['tarif_line_period']);
      $mw->setTarifBaseDays($_POST['tarif_base_days']);
      $mw->setKeywords( str_replace(['"', "'"], '', $_POST['keywords']) );
      $mw->setStatus($_POST['status']);


      if (isset($_FILES['m_pic_big']) && $_FILES['m_pic_big']['name'] != '') {
        if ($_POST['m_pic_new_name']=='') $_POST['m_pic_new_name']=$_FILES['m_pic_big']['name'];

        $dir = '/public/rent/images/'.$category->getCatUrlKey().'/'.$mw->getPageUrlCode().'/';
        $newFileName=$_POST['m_pic_new_name'].'.'.(pathinfo($_FILES['m_pic_big']['name'])['extension']);

        $savedFilePath = bb\Base::saveFile($dir, $_FILES['m_pic_big']['tmp_name'], $newFileName);

        \bb\Base::delFile($mw->getMPicBigUrlAddress());

        $savedFilePath = \bb\Base::removeQuotesFromFile($savedFilePath);

        $mw->setMPicBigUrlAddress($savedFilePath);
      }
      else{
        $mw->setMPicBigUrlAddress(\bb\Base::removeQuotesFromFile($mwOld->getMPicBigUrlAddress()));
      }

      if (isset($_FILES['l2_pic']) && $_FILES['l2_pic']['name'] != '') {
        if ($_POST['l2_pic_new_name']=='') $_POST['l2_pic_new_name']=$_FILES['l2_pic']['name'];

        $dir = '/public/rent/images/'.$category->getCatUrlKey().'/'.$mw->getPageUrlCode().'/';

        $newFileName=$_POST['l2_pic_new_name'].'.'.(pathinfo($_FILES['l2_pic']['name'])['extension']);

        $savedFilePath = bb\Base::saveFile($dir, $_FILES['l2_pic']['tmp_name'], $newFileName);

        \bb\Base::delFile($mw->getL2PicUrlAddress());

        $savedFilePath=\bb\Base::removeQuotesFromFile($savedFilePath);

        $mw->setL2PicUrlAddress($savedFilePath);
      }
      else{
        $mw->setL2PicUrlAddress(\bb\Base::removeQuotesFromFile($mwOld->getL2PicUrlAddress()));
      }

      //logo
      if (isset($_FILES['logo_pic']) && $_FILES['logo_pic']['name'] != '') {
        if ($_POST['logo_pic_new_name']=='') $_POST['logo_pic_new_name']=$_FILES['logo_pic']['name'];

        $dir = '/public/rent/images/'.$category->getCatUrlKey().'/'.$mw->getPageUrlCode().'/';

        $newFileName=$_POST['logo_pic_new_name'].'.'.(pathinfo($_FILES['logo_pic']['name'])['extension']);

        $savedFilePath = bb\Base::saveFile($dir, $_FILES['logo_pic']['tmp_name'], $newFileName);

        \bb\Base::delFile($mw->getLogoUrlAddress());
        $savedFilePath = \bb\Base::removeQuotesFromFile($savedFilePath);
        $mw->setLogoUrlAddress($savedFilePath);
        $mw->updateLogoUrlForAll();
      }
      else{
        $mw->setLogoUrlAddress(\bb\Base::removeQuotesFromFile($mwOld->getLogoUrlAddress()));
      }

      if (isset($_FILES['dop_pic']) && $_FILES['dop_pic']['name'] != '') {
        if ($_POST['dop_pic_new_name']=='') $_POST['dop_pic_new_name'] = $_FILES['dop_pic']['name'];

        $dir = '/public/rent/images/'.$category->getCatUrlKey().'/'.$mw->getPageUrlCode().'/';

        $newFileName=$_POST['dop_pic_new_name'].'.'.(pathinfo($_FILES['dop_pic']['name'])['extension']);

        $savedFilePath = bb\Base::saveFile($dir, $_FILES['dop_pic']['tmp_name'], $newFileName);

        $savedFilePath = \bb\Base::removeQuotesFromFile($savedFilePath);

        $mw->saveDopPicture($savedFilePath);
      }

      if (isset($_POST['dop_to_del'])) {
        foreach ($_POST['dop_to_del'] as $srcToDel) {
          \bb\Base::delFile($srcToDel);
          \bb\classes\ModelWeb::deleteDopPictureBySrc($srcToDel);
        }
      }

      if (isset($_POST['dop_cat'])){
        foreach ($_POST['dop_cat'] as $dopCatId) {
          $mw->addDopCat($dopCatId, $_POST['add_cat_pic_url_'.$dopCatId]);
        }
      }
      $mw->save();
      $mw->saveDopCats();

      //\bb\Base::varDamp($mw);

      unset($mw);
      break;
  }
}


?>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <a class="navbar-brand" href="/bb/">Главная</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
    <div class="navbar-nav">
      <a class="nav-item nav-link" href="/bb/razdel_manage.php">Работа с меню</a>
      <a class="nav-item nav-link" href="/bb/page_management.php">Страницы-листинги+</a>
      <a class="nav-item nav-link" href="/bb/favorite_tovars_management.php">Популярные товары</a>
      <form>
        <a class="nav-item nav-link" href="#" onclick="document.querySelector('#tovar_cat').submit();">Перейти в категорию</a>
      </form>
    </div>
  </div>
</nav>

<?php if ($modelId<1): ?>
<div class="container-fluid">
  <div class="row">
    <div class="col alert-danger">
      Ошибка: отсутствует id модели. Зайдите на страницу заново.
    </div>
  </div>
</div>
<?php die(); ?>
<?php endif;?>
<?php
//main load
$mw = \bb\classes\ModelWeb::getByModelId($modelId, $lang);
if (!$mw) {
  $mw = \bb\classes\ModelWeb::getByModelId($modelId, 'ru');
  if (!$mw) {
    $lang = 'ru';
    $mw = new \bb\classes\ModelWeb($modelId, $lang);
    $mw->loadLastProducerLogo();
    \bb\Base::addErrorMessage('Отсутствует модель для русского языка. Она заводится в первую очередь, как базовая.');
  }
  $mw->setWebId('');
  $mw->setLang($lang);
  \bb\Base::addErrorMessage('Для данного языка модель еще не заведена. Поля предзаполнены из русского аналога (при наличии).');
}
//\bb\Base::varDamp($mw);

?>
<form method="post" action="/bb/kr_baza_new.php" id="tovar_cat">
  <input type="hidden" name="cat_id" value="<?= $mw->getCatId() ?>">
</form>
<form method="post" name="filter-form" id="filter-form">
  <input type="hidden" name="model_id" value="<?= $modelId ?>">
</form>
<?php if ($err = \bb\Base::getErrorsString()): ?>
<div class="row">
  <div class="col alert alert-warning">
    <?= $err ?>
  </div>
</div>
<?php endif; ?>

<div class="container-fluid mt-3">
  <div class="row">
    <div class="col d-flex justify-content-between">
      <button class="btn btn-outline-success" form="main-form" type="submit" name="action_command" value="save">Сохранить</button>
      <?= ($mw->getWebId()>0 ? '<a class="btn btn-outline-info" href="'.$mw->getUrlPageAddress().'" target="_blank">проверить результат</a>' : '') ?>
      <select class="form-select filter-select" style="width: unset" name="status" form="main-form">
        <option value="show" <?= \bb\Base::sel_d('show', $mw->getStatus()) ?>>показывать</option>
        <option value="not_show" <?= \bb\Base::sel_d('not_show', $mw->getStatus()) ?>>не показывать</option>
      </select>
      <select class="form-select filter-select" data-live-search="true" style="width: unset" name="lang" form="filter-form" onchange="this.form.submit()">
        <option value="ru" <?= \bb\Base::sel_d('ru', $lang) ?>>RU</option>
        <option value="en" <?= \bb\Base::sel_d('en', $lang) ?>>EN</option>
        <option value="lt" <?= \bb\Base::sel_d('lt', $lang) ?>>LT</option>
      </select>
    </div>
  </div>
</div>
<form class="container-fluid mt-3" method="post" action="model_web.php" enctype="multipart/form-data" id="main-form">
  <div class="row mb-2">
    <div class="col">
      <?php
        $m = \bb\classes\Model::getById($mw->getModelId());
        echo '<strong>'.$m->getFullName().'('.$mw->getModelId().')</strong>';
      ?>
    </div>
  </div>
  <div class="row">
    <div class="col-7">
      <div class="row mt-2">
        <div class="col-auto">
          <input type="hidden" name="model_id" value="<?= $modelId ?>">
          <input type="hidden" name="lang" value="<?= $lang ?>">
          <input type="hidden" name="action_command" value="save">
          <label class="form-label" for="name_main">Название (h1)</label>
        </div>
        <div class="col">
          <input class="form-control" name="name_main" id="name_main" type="text" value="<?= $mw->getItemNameMain(1) ?>">
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-auto">
          <label class="form-label" for="l2_name" title="Название на карточке товара на страницах-листингах">Название (листинг)</label>
        </div>
        <div class="col">
          <input class="form-control" name="l2_name" id="l2_name" type="text" value="<?= $mw->getL2Name(1) ?>">
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-auto">
          <label class="form-label" for="title" title="Не должно содержать кавычки. Отражается в названии закладки браузера.">Title (для страницы) <sup>i</sup></label>
        </div>
        <div class="col">
          <input class="form-control" name="title" id="title" type="text" value="<?= $mw->getTitle() ?>">
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-auto">
          <label class="form-label" for="meta_description" title="Не должно содержать кавычки. Отражается в результатах поиска гугл\яндекс.">Meta description <sup>i</sup></label>
        </div>
        <div class="col">
          <input class="form-control" name="meta_description" id="meta_description" type="text" value="<?= $mw->getMetaDescription() ?>">
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-auto">
          <label class="form-label" for="breadcrumbs_name">Название для хлебных крошек</label>
        </div>
        <div class="col">
          <input class="form-control" name="breadcrumbs_name" id="breadcrumbs_name" type="text" value="<?= $mw->getBreadcrumbsName(1) ?>">
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-auto">
          <label class="form-label" for="url_code" title="должен быть уникальным">URL код страницы <sup>i</sup></label>
        </div>
        <div class="col">
          <input class="form-control" name="url_code" id="url_code" type="text" data-controll="url" aria-describedby="url_code_feedback" value="<?= $mw->getPageUrlCode() ?>">
          <div class="invalid-feedback" id="url_code_feedback"></div>
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-auto">
          <label class="form-label" for="m_a_title" title="Важно для SEO, не должен будлировать title страницы. Для человека. Всплывает, если держать курсор над ссылкой.">Title для ссылки на страницу <sup>i</sup></label>
        </div>
        <div class="col">
          <input class="form-control" name="m_a_title" id="m_a_title" type="text" value="<?= $mw->getMATitle(1) ?>">
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-auto">
          <label class="form-label" for="m_a_keywords" title="Ключевые слова для своего поиска по сайту">Ключевые слова для поисковика <sup>i</sup></label>
        </div>
        <div class="col">
          <input class="form-control" name="keywords" id="m_a_keywords" type="text" value="<?= $mw->getKeywords() ?>">
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-12">
          <label class="form-label" for="main_descr">Основное описание товара:</label>
        </div>
        <div class="col-12">
          <textarea class="code-filed" name="main_descr" id="main_descr">
            <?= $mw->getMainDescrHtml() ?>
          </textarea>
        </div>
      </div>
    </div>

    <div class="col-5">
      <div class="row">
        <div class="col-3"><span>Alt для картинок:</span></div>
        <div class="col">
          <input class="form-control d-inline" name="m_pic_alt" type="text" placeholder="alt для картинки" value="<?= $mw->getMPicAlt() ?>">
        </div>
      </div>
      <div class="row photo-row">
        <div class="col-3 h-100 wm_file-col">
          <img src="<?= $mw->getMPicBigUrlAddress() ?>">
        </div>
        <div class="col">
          <label class="form-label d-block" title="Обязательно квадратная. Разрешение от 1000х1000 пикселей.">Главная картинка на страницу товара<sup>i</sup></label>
          <span class="file-name d-none">Имя файла:</span><input class="form-control w-100 d-none new-filename" data-noempty="" aria-describedby="m_pic_big_feedback" name="m_pic_new_name" type="text" data-controll="url" placeholder="новое имя файла">
          <div class="invalid-feedback" id="m_pic_big_feedback"></div>
          <input class="form-control" type="file" accept="image/*,.webp" name="m_pic_big" id="m_pic_big">
        </div>
      </div>
      <div class="row photo-row">
        <div class="col-3 h-100 wm_file-col">
          <img src="<?= $mw->getL2PicUrlAddress() ?>">
        </div>
        <div class="col">
          <label class="form-label d-block" title="Обязательно квадратная. Разрешение от 200x200 пикселей.">Картинка на листинг<sup>i</sup></label>
          <span class="file-name d-none">Имя файла:</span><input class="form-control w-100 d-none new-filename" data-noempty="" aria-describedby="l2_pic_feedback" name="l2_pic_new_name" type="text" data-controll="url" placeholder="новое имя файла">
          <div class="invalid-feedback" id="l2_pic_feedback"></div>
          <input class="form-control" type="file" accept="image/*,.webp" name="l2_pic" id="l2_pic">
        </div>
      </div>
      <div class="row photo-row">
        <div class="col-3 h-100 wm_file-col">
          <img src="<?= $mw->getLogoUrlAddress() ?>">
        </div>
        <div class="col">
          <label class="form-label d-block" title="Пока не прописаны требования.">Лого <sup>i</sup></label>
          <span class="file-name d-none">Имя файла:</span><input class="form-control w-100 d-none new-filename" data-noempty="" aria-describedby="logo_pic_feedback" name="logo_pic_new_name" type="text" data-controll="url" placeholder="новое имя файла">
          <div class="invalid-feedback" id="logo_pic_feedback"></div>
          <input class="form-control" type="file" accept="image/*,.webp" name="logo_pic" id="logo_pic">
        </div>
      </div>
      <div class="row photo-row alert-dark">
        <div class="col-3">Доп. фото</div>
        <div class="col">Удалить?</div>
      </div>
      <?php if (is_array($mw->getDopPictures()) && count($mw->getDopPictures())>0): ?>
        <?php foreach ($mw->getDopPictures() as $pic): ?>
          <div class="row photo-row">
            <div class="col-3 wm_file-col"><img src="<?= $pic->getSrc() ?>"></div>
            <div class="col-9"><input class="file-del-checkbox" type="checkbox" name="dop_to_del[]" value="<?= $pic->getSrc() ?>"><span data-copy="1" style="font-size: 10px; cursor: pointer;" title="Click to copy!"><?= $pic->getSrc() ?></span></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <div class="row photo-row">
        <div class="col-3 h-100 wm_file-col">
        </div>
        <div class="col">
          <label class="form-label d-block" title="Обязательно квадратная. Разрешение от 1000x1000 пикселей.">Доп. картинка<sup>i</sup></label>
          <span class="file-name d-none">Имя файла:</span><input class="form-control w-100 d-none new-filename" data-noempty="" aria-describedby="dop_pic_feedback" name="dop_pic_new_name" type="text" data-controll="url" placeholder="новое имя файла">
          <div class="invalid-feedback" id="dop_pic_feedback"></div>
          <input class="form-control" type="file" accept="image/*,.webp" name="dop_pic" id="dop_pic">
        </div>
      </div>
      <div class="row photo-row">
        <div class="col-6">
          период для линии тарифов
        </div>
        <div class="col">
          <select class="form-select" name="tarif_line_period">
            <option value="day" <?= \bb\Base::sel_d('day', $mw->getTarifLinePeriod()) ?>>день</option>
            <option value="week" <?= \bb\Base::sel_d('week', $mw->getTarifLinePeriod()) ?>>неделя</option>
            <option value="month" <?= \bb\Base::sel_d('month', $mw->getTarifLinePeriod()) ?>>месяц</option>
          </select>
        </div>
      </div>
      <div class="row photo-row">
        <div class="col-6">
          база для +\- тарифов (в сутках)
        </div>
        <div class="col">
          <input class="form-control" name="tarif_base_days" type="number" min="1" value="<?= $mw->getTarifBaseDays() ?>">
        </div>
      </div>
    </div>
  </div>
  <div class="row mt-3">
    <div class="col-12">Также показывать в категориях</div>
    <div class="col">
      <select class="form-select" name="add_razdel" id="add_razdel">
        <option value="0">выберите раздел</option>
        <?php foreach (\bb\classes\Razdel::getAll() as $r): ?>
          <option value="<?= $r->getIdRazdel(); ?>"><?= $r->getNameRazdelText() ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col">
      <select class="form-select" name="add_subrazdel" id="add_subrazdel">
        <option value="0">---</option>
      </select>
    </div>
    <div class="col" style="text-align: right;">
      <select class="form-select" name="add_category" id="add_category">
        <option value="0">---</option>
      </select>
      <input type="button" class="btn btn-outline-success" id="add-btn" value="->">
    </div>
    <div class="col" id="add-cat-container">
      <?php foreach ($mw->getAdditionalCategories() as $addCat): ?>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="dop_cat[]" value="<?= $addCat[0] ?>" id="cat-<?= $addCat[0] ?>" checked>
        <label class="form-check-label" for="cat-<?= $addCat[0] ?>"><?= $addCat[1] ?></label>
        <input class="form-control" type="text" name="add_cat_pic_url_<?= $addCat[0] ?>" value="<?= $addCat[2] ?>" placeholder="url доп. картинки листинг" data-controll="url-file">
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="row mt-3">
    <div class="col"><button class="btn btn-outline-success" form="main-form" type="submit" name="action_command" value="save">Сохранить</button></div>
  </div>
</form>


<script src="/public/js/popper.min.js?v={{$v}}" integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p" crossorigin="anonymous"></script>
<script src="/public/js/bootstrap.min.js?v={{$v}}" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>

<script src="https://cdn.tiny.cloud/1/bacqe8vq7etx5keuau4n12gqdblggd9ttqjey812a2qk0d83/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    relative_urls : false,
    selector: '.code-filed',
    plugins: 'image autolink lists media table code',
    toolbar: 'a11ycheck addcomment showcomments casechange checklist bullist numlist code export formatpainter image editimage pageembed permanentpen table tableofcontents undo redo bold italic alignleft aligncenter alignright alignjustify outdent indent link image forecolor backcolor emoticons',
    toolbar_mode: 'floating',
    tinycomments_mode: 'embedded',
    tinycomments_author: 'Author name',
  });
</script>
<script src="/bb/assets/js/model_web_new.js?v=4"></script>
</body>
</html>
