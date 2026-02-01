<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Razdel.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubRazdel.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubRazdelPage.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Category.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //


echo \bb\Base::PageStartAdvansed('Категории');
\bb\Base::loginCheck();

//\bb\Base::PostCheckVarDumpEcho();

$razdel_filter=0;

if (isset($_POST['razdel_filter'])) $razdel_filter=$_POST['razdel_filter'];

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'save_new':
            $newCat = new \bb\classes\Category();
            $newCat->setMainSubRazdelId(trim($_POST['main_razdel_id']));
            $newCat->setName(trim($_POST['name']));
              $newCat->setName(trim($_POST['name_en']), 'en');
              $newCat->setName(trim($_POST['name_lt']), 'lt');
            $newCat->setDogName(trim($_POST['dog_name']));
            $newCat->setCatUrlKey(trim($_POST['cat_url_key']));
            $newCat->setCatType(trim($_POST['cat_type']));
            $newCat->setCatSort(trim($_POST['cat_sort']));

            if (isset($_POST['cat_id'])) $newCat->setId($_POST['cat_id']);


            $newCat->save();

            break;

        case 'delete':
            if (isset($_POST['cat_id'])) {
                \bb\Db::startTransaction();

                $cat_ = \bb\classes\Category::getById($_POST['cat_id']);
                $cat_->archAndDelete();

                \bb\Db::commitTransaction();
            }
            break;
    }
}

$cats = \bb\classes\Category::getAllCategoriesTovarCount($razdel_filter);
?>

<link rel="stylesheet" href="/bb/assets/styles/cat.css">
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
        <div class="navbar-nav">
            <a class="nav-item nav-link" href="/bb/kr_baza_new.php">Товары</a>
            <a class="nav-item nav-link" href="/bb/razdel_manage.php">Управление разделами</a>
            <a class="nav-item nav-link" href="/bb/sub_razdel_manage.php">Управление подразделами</a>
            <a class="nav-item nav-link active" href="/bb/category_management.php">Управление категориями</a>

        </div>
    </div>
</nav>

<form method="post" name="filter" id="filter"></form>

<div class="mt-3" style="display: flex; justify-content: space-between">
  <?php $razdels = \bb\classes\Razdel::getAll(); ?>
  <select class="form-control" name="razdel_filter" style="width: fit-content" form="filter" onchange="this.form.submit();">
    <option value="0">все разделы</option>
    <?php foreach ($razdels as $razdel): ?>
      <option value="<?= $razdel->getIdRazdel() ?>" <?= \bb\Base::sel_d($razdel->getIdRazdel(), $razdel_filter) ?>><?= $razdel->getNameRazdelText() ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-info new-btn">Ввести новую категорию</button>
</div>

<table class="table">
    <thead>
        <tr>
            <th>id</th>
            <th>Основной подраздел</th>
            <th>Название</th>
            <th>Название для договора</th>
            <th>url-ключ</th>
            <th>тип категории</th>
            <th>кол-во товаров</th>
            <th>sort</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
    <tr class="new-form-row">
        <td></td>
        <td>
          <?php
            $subrazdelsAll = \bb\classes\SubRazdel::getAll();
            usort($subrazdelsAll, function ($a, $b){
              //$a = new \bb\classes\SubRazdel();
              return strcmp($a->getNameSubRazdelText(), $b->getNameSubRazdelText());
            });
          ?>
            <select class="form-control" name="main_razdel_id" form="new_cat">
                <option value="0">Выбрать основной подраздел</option>
                <?php foreach ($subrazdelsAll as $sr): ?>
                    <option value="<?= $sr->getIdSubRazdel()?>"> <?=$sr->getNameSubRazdelText() ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
          <input form="new_cat" class="form-control" data-name="" type="text" name="name" value="" placeholder="ru">
          <input form="new_cat" class="form-control" data-name="" type="text" name="name_en" value="" placeholder="en">
          <input form="new_cat" class="form-control" data-name="" type="text" name="name_lt" value="" placeholder="lt">
        </td>
        <td><input form="new_cat" class="form-control" data-name="" type="text" name="dog_name" value=""></td>
        <td><input form="new_cat" class="form-control" data-controll="url" type="text" name="cat_url_key" value=""></td>
        <td>
            <select class="form-control" name="cat_type" form="new_cat">
                <option value="0">стандарт</option>
                <option value="1">карнавал</option>
            </select>
        </td>
        <td></td>
        <td><input form="new_cat" class="form-control" data-controll="url" type="number" name="cat_sort" value="" style="width: 80px;"></td>
        <td>
            <form method="post" name="new_cat" id="new_cat" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_new">
                <input type="button" class="btn btn-warning form-new-cancel" value="отмена">
                <input type="button" class="btn btn-success save-btn" value="сохранить">
            </form>
        </td>
    </tr>
    <?php foreach ($cats as $cat): ?>
        <tr>
            <td><?= $cat->getId() ?><input form="new_cat_<?= $cat->getId() ?>" type="hidden" name="cat_id" value="<?= $cat->getId() ?>"></td>
            <td><?php
                $sr = \bb\classes\SubRazdel::getById($cat->getMainSubRazdelId());
                if ($sr) {
                    echo $sr->getNameSubRazdelText();
                    $sr_id=$sr->getIdSubRazdel();
                }
                else {
                    echo 'подраздел не выбран';
                    $sr_id=0;
                }

                ?>
                <select class="form-control form-edit" name="main_razdel_id" form="new_cat_<?= $cat->getId() ?>">
                    <option value="0">Выбрать основной подраздел</option>
                    <?php foreach ($subrazdelsAll as $sr2): ?>
                        <option value="<?= $sr2->getIdSubRazdel()?>" <?= ($sr2->getIdSubRazdel()==$sr_id ? 'selected' : '') ?>> <?=$sr2->getNameSubRazdelText() ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><?= $cat->getName() ?>
                <input form="new_cat_<?= $cat->getId() ?>" class="form-control form-edit" type="text" name="name" data-name="" value="<?= $cat->getName()?>"><br>
                <input form="new_cat_<?= $cat->getId() ?>" class="form-control form-edit" type="text" name="name_en" data-name="" value="<?= $cat->getName('en', 1)?>" placeholder="en"><br>
                <input form="new_cat_<?= $cat->getId() ?>" class="form-control form-edit" type="text" name="name_lt" data-name="" value="<?= $cat->getName('lt', 1)?>" placeholder="lt"><br>
            </td>
            <td><?= $cat->getDogName() ?>
                <input form="new_cat_<?= $cat->getId() ?>" class="form-control form-edit" type="text" name="dog_name" data-name="" value="<?= $cat->getDogName()?>">
            </td>
            <td><?= $cat->getCatUrlKey() ?>
                <input form="new_cat_<?= $cat->getId() ?>" class="form-control form-edit" type="text" name="cat_url_key" data-controll="url" value="<?= $cat->getCatUrlKey()?>">
            </td>
            <td> <?= ($cat->getCatType()==1 ? 'карнавал' : 'стандарт')  ?>
                <select class="form-control form-edit" name="cat_type" form="new_cat_<?= $cat->getId() ?>">
                    <option value="0">стандарт</option>
                    <option value="1" <?= \bb\Base::sel_d('1', $cat->getCatType()) ?>>карнавал</option>
                </select>
            </td>
            <td><?= $cat->getTovNum() ?></td>
            <td><?= $cat->getCatSort(); ?><input form="new_cat_<?= $cat->getId() ?>" class="form-control form-edit" data-controll="url" type="number" name="cat_sort" value="<?= $cat->getCatSort(); ?>" style="width: 80px;"></td>
            <td>
                <form method="post" name="new_cat_<?= $cat->getId() ?>" id="new_cat_<?= $cat->getId()?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_new">
                    <input type="button" class="btn btn-info change-btn show-block" value="изменить">
                    <input type="button" class="btn btn-warning form-edit cancel-btn" value="отмена">
                    <input type="button" class="btn btn-success save-btn form-edit" value="сохранить">
                    <?php if ($cat->getTovNum()==0): ?>
                    <input type="button" class="btn btn-danger form-edit delete-btn" value="удалить">
                    <?php endif; ?>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>

</table>

<script src="/bb/assets/js/cat.js?v=7"></script>
<?php
\bb\Base::PageEndHTML();
?>
