<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Razdel.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/FavoriteTovars.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Model.php'); //

echo \bb\Base::pageStartB5('Pages.');
\bb\Base::loginCheck();
//\bb\Base::PostCheckVarDumpEcho();

$action='';
$model_id=0;
$active_model_id=0;

//$_POST['action']='new';
//$_POST['inv_n']='719232';

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    switch ($action){
        case 'new':
            isset($_POST['model_id']) ? $model_id=$_POST['model_id'] : '';
            if ($model_id>0) {
                if (!\bb\classes\Model::getById($model_id)) {
                    echo '<div class="alert alert-primary">Модель c ID '.$model_id.' не найдена в базе.</div>';
                    $model_id=0;
                    $action='';
                }
            }
            elseif (isset($_POST['inv_n'])) {
                $model_id = \bb\classes\tovar::getModelIdForInvN($_POST['inv_n']);
                if (!$model_id) {
                    echo '<div class="alert alert-primary">Модель для инвентарного номера '.$_POST['inv_n'].' не найдена в базе.</div>';
                    $model_id=0;
                    $action='';
                }
            }

            if ($rez=\bb\classes\FavoriteTovars::getByModelId($model_id)) {
                echo '<div class="alert alert-primary">Модель уже заведена как популярная (см. заливку желтым ниже).</div>';
                $model_id=0;
                $action='';
                $active_model_id=$rez->getModelId();
            }
            break;
        case 'save':
            $ft = new \bb\classes\FavoriteTovars();


            if (isset($_POST['id'])) $ft->setId($_POST['id']);
            $ft->setModelId($_POST['model_id']);
            $ft->setPicAlt($_POST['pic_alt']);
            $ft->setNameText($_POST['name_text']);
            $ft->setDescription($_POST['description']);

            $dir_short='/public/img/';
            $dir_full=$_SERVER['DOCUMENT_ROOT'].$dir_short;
            //if file sent
            if (key_exists('pic_url', $_FILES) && $_FILES['pic_url']['name'] != '') {
                //echo 'file set part launched';
                $file_name = $_FILES['pic_url']['name'];
                $newFileName=\bb\Base::getUniqueFileName($dir_short, $file_name);
                    move_uploaded_file($_FILES['pic_url']['tmp_name'], $dir_full.$newFileName);
                    $ft->setPicUrl($dir_short.$newFileName);

                //delete old file if exists
                if ($ft->getId()>0) {
                    $old_ft = \bb\classes\FavoriteTovars::getBylId($ft->getId());
                    if ($old_ft->getPicUrl()!='') {
                        if(!unlink($_SERVER['DOCUMENT_ROOT'].$old_ft->getPicUrl())){
                            echo 'error on old file deleting';
                        };
                    }
                }
            }//end if
            elseif ($ft->getId()>0) {//if file not sent - get existing link
                $old_ft = \bb\classes\FavoriteTovars::getBylId($ft->getId());
                $ft->setPicUrl($old_ft->getPicUrl());
            }

//            \bb\Base::varDamp($ft);

            $ft->save();

            $active_model_id=$ft->getModelId();

            break;

        case 'delete':
            $ft = \bb\classes\FavoriteTovars::getBylId($_POST['id']);

            if ($ft->getId()>0 && $ft->getPicUrl() != '' && is_file($_SERVER['DOCUMENT_ROOT'].$ft->getPicUrl())) {
                unlink($_SERVER['DOCUMENT_ROOT'].$ft->getPicUrl());
            }

            $ft->delete();

            break;
    }
}

$fts = \bb\classes\FavoriteTovars::getAll();

?>

<link rel="stylesheet" href="/bb/assets/styles/fav_tovs.css">

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
        <div class="navbar-nav">
            <a class="nav-item nav-link" href="/bb/kr_baza_new.php">Товары</a>
            <a class="nav-item nav-link" href="/bb/page_management.php">Главная страница</a>
        </div>
    </div>
</nav>

<?php if ($model_id<1): ?>
<div class="container-fluid">
    <form class="row justify-content-end align-items-center" method="post">
        <div class="col-2">
            <input type="hidden" name="action" value="new">
            <label class="form-label" for="model_srch">id модели (или)</label>
            <input type="number" class="form-control" name="model_id" id="model_srch">
        </div>
        <div class="col-2">
            <label class="form-label" for="model_srch">(или) инвентарный номер</label>
            <input type="number" class="form-control" name="inv_n" id="inv_srch" >
        </div>
        <div class="col-2 align-self-end">
            <button type="submit" class="btn btn-info model_srch_but">добавить в популярные</button>
        </div>
    </form>
</div>
<?php endif; ?>
<table class="table">
    <thead>
        <tr>
            <th scope="col">model ID</th>
            <th scope="col">картинка</th>
            <th scope="col">Заголовок для веб</th>
            <th scope="col">Описание для веб</th>
            <th scope="col">Действия</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($action='new' && $model_id>0): ?>
        <tr class="table-info">
            <td>
                <input type="hidden" name="model_id" value="<?= $model_id ?>" form="new_form">
                <strong><?=$model_id?>: </strong><?= \bb\classes\Model::getById($model_id)->getFullName(); ?>
            </td>
            <td>
                <input class="form-control" type="file" name="pic_url" accept=".gif,.jpg,.jpeg,.png,.svg" form="new_form">
                <div class="form-floating">
                    <input class="form-control form-control-sm" name="pic_alt" form="new_form" id="pic_alt_new" placeholder="alt для картинки">
                    <label class="form-label" for="pic_alt_new">alt для картинки</label>
                </div>
            </td>
            <td><input class="form-control" name="name_text" form="new_form"></td>
            <td><input class="form-control" name="description" form="new_form"></td>
            <td>
                <form method="post" id="new_form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <button class="btn btn-success" type="submit" id="new_btn">Добавить</button>
                    <input type="button" class="btn btn-warning form-new-cancel" value="отмена">
                </form>
            </td>
        </tr>
    <?php endif; ?>
    <?php if ($fts && count($fts)>0): ?>
    <?php foreach ($fts as $ft): ?>
        <tr <?= ($active_model_id==$ft->getModelId() ? 'class="table-warning"' : '') ?>>
            <td>
                <input type="hidden" name="model_id" value="<?= $ft->getModelId() ?>" form="new_form_<?= $ft->getId() ?>">
                <strong><?=$ft->getModelId() ?>: </strong><?= \bb\classes\Model::getById($ft->getModelId())->getShortName(); ?>
            </td>
            <td>
                <img src="<?= $ft->getPicUrl()?>" width="70px">
                <input class="form-control edit-field" type="file" name="pic_url" accept=".gif,.jpg,.jpeg,.png,.svg" form="new_form_<?= $ft->getId() ?>">
                <div class="form-floating">
                    <input class="form-control edit-field" data-controll="noquotes" name="pic_alt" id="pic_alt_new" placeholder="alt для картинки" form="new_form_<?= $ft->getId() ?>" value="<?= $ft->getPicAlt() ?>">
                    <label class="form-label edit-field" for="pic_alt_new">alt для картинки</label>
                </div>
            </td>
            <td>
                <?= $ft->getNameText() ?>
                <input type="text" class="form-control edit-field" name="name_text" value="<?= $ft->getNameText() ?>" form="new_form_<?= $ft->getId() ?>">
            </td>
            <td>
                <?= $ft->getDescription() ?>
                <input type="text" class="form-control edit-field" name="description" value="<?= $ft->getDescription() ?>" form="new_form_<?= $ft->getId() ?>">
            </td>
            <td>
                <form method="post" id="new_form_<?= $ft->getId() ?>" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $ft->getId() ?>">
                    <input type="hidden" name="action" value="save">

                    <input type="button" class="btn btn-info change-btn hide-when-edit show-block" value="изменить">
                    <button class="btn btn-success edit-field" type="submit" id="new_btn">обновить</button>
                    <input type="button" class="btn btn-warning cancel-btn edit-field" value="отмена">
                    <input type="button" class="btn btn-danger edit-field delete-btn" value="удалить">
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>

</table>


<script src="/bb/assets/js/fav_tov.js"></script>



<?php


