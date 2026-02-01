<?php
session_start();
//ini_set("display_errors",1);
//error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Razdel.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');

echo \bb\Base::PageStartAdvansed('Разделы.');
\bb\Base::loginCheck();

//\bb\Base::PostCheckVarDumpEcho();
//\bb\Base::varDamp($_FILES);

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'save_new':
            $r = new \bb\classes\Razdel();
            if(key_exists('id_razdel', $_POST)) $r->setIdRazdel($_POST['id_razdel']);
            $r->setNameRazdelText($_POST['name_razdel_text'], 'ru');
                $r->setNameRazdelText($_POST['name_razdel_text_en'], 'en');
                $r->setNameRazdelText($_POST['name_razdel_text_lt'], 'lt');
            $r->setUrlRazdelName($_POST['url_razdel_name']);
            $r->setRazdelOrderNum($_POST['razdel_order_num']);

                $dir_short='/public/img/topmenu/';
                $dir_full=$_SERVER['DOCUMENT_ROOT'].$dir_short;

                $file_name = $_FILES['url_icon_razdel']['name'];
                    $file_name2 = $_FILES['url_icon2_razdel']['name'];
                //get unique filename if already taken
                if (key_exists('url_icon_razdel', $_FILES) && $file_name !='') {
                    $file_name = \bb\Base::getUniqueFileName($dir_short, $file_name);
                }
                //2
                    if (key_exists('url_icon2_razdel', $_FILES) && $file_name2 !='') {
                        $file_name2 = \bb\Base::getUniqueFileName($dir_short, $file_name2);
                    }

            if (key_exists('url_icon_razdel', $_FILES) && move_uploaded_file($_FILES['url_icon_razdel']['tmp_name'], $dir_full.$file_name)) {
                $r->setUrlIconRazdel($dir_short.$file_name);
            }
            //2
            if (key_exists('url_icon2_razdel', $_FILES) && move_uploaded_file($_FILES['url_icon2_razdel']['tmp_name'], $dir_full.$file_name2)) {
                $r->setUrlIcon2Razdel($dir_short.$file_name2);
            }

            //existing cat change
            if ($r->getIdRazdel()>0) {
                $r_old=\bb\classes\Razdel::getById($r->getIdRazdel());
                //new icon-file chosen
                if ($file_name!='') {
                    //delete old icon file
                    if (file_exists($_SERVER['DOCUMENT_ROOT'].$r_old->getUrlIconRazdel()) && $r_old->getUrlIconRazdel() != ''){
                        if(!unlink($_SERVER['DOCUMENT_ROOT'].$r_old->getUrlIconRazdel())){
                            echo 'error on old file deleting_1';
                        };
                    }
                }
                else { //no new file
                    $r->setUrlIconRazdel($r_old->getUrlIconRazdel());
                }
                //2
                if ($file_name2!='') {
                    //delete old icon file
                    if (file_exists($_SERVER['DOCUMENT_ROOT'].$r_old->getUrlIcon2Razdel()) && $r_old->getUrlIcon2Razdel() != ''){
                        if(!unlink($_SERVER['DOCUMENT_ROOT'].$r_old->getUrlIcon2Razdel())){
                            echo 'error on old file deleting_2';
                        };
                    }
                }
                else { //no new file
                    $r->setUrlIcon2Razdel($r_old->getUrlIcon2Razdel());
                }

            }

            $r->save();

            break;
        case 'delete':
            $r = \bb\classes\Razdel::getById($_POST['id_razdel']);

            if ($r->getIdRazdel()>0 && $r->getUrlIconRazdel()!='' && is_file($_SERVER['DOCUMENT_ROOT'].$r->getUrlIconRazdel())) {
                unlink($_SERVER['DOCUMENT_ROOT'].$r->getUrlIconRazdel());
            }
            //2
            if ($r->getIdRazdel()>0 && $r->getUrlIcon2Razdel()!='' && is_file($_SERVER['DOCUMENT_ROOT'].$r->getUrlIcon2Razdel())) {
                unlink($_SERVER['DOCUMENT_ROOT'].$r->getUrlIcon2Razdel());
            }

            $r->delete();
            break;
    }
}

$razdels=\bb\classes\Razdel::getAll();
?>

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
<link rel="stylesheet" href="/bb/assets/styles/razdel.css?v=1">
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
        <div class="navbar-nav">
            <a class="nav-item nav-link" href="/bb/kr_baza_new.php">Товары</a>
            <a class="nav-item nav-link active" href="/bb/razdel_manage.php">Управление разделами</a>
            <a class="nav-item nav-link" href="/bb/sub_razdel_manage.php">Управление подразделами</a>
            <a class="nav-item nav-link" href="/bb/category_management.php">Управление категориями</a>
        </div>
    </div>
</nav>
<div style="display: flex; justify-content: flex-end"><button class="btn btn-info new-btn">Ввести новый раздел</button></div>
<table class="table">
    <thead>
        <tr>
            <th scope="col">id</th>
            <th scope="col">Наименование</th>
            <th scope="col">url-ключ (латиница)</th>
            <th scope="col">Иконка 1</th>
            <th scope="col">Иконка 2</th>
            <th scope="col">№ для сортировки</th>
            <th>действия</th>
        </tr>
    </thead>
    <tbody>
        <tr class="new_razdel">
            <td></td>
            <td>
                <input form="new_razdel" class="form-control" type="text" name="name_razdel_text" value="" placeholder="ru"><br>
                <input form="new_razdel" class="form-control" type="text" name="name_razdel_text_en" value="" placeholder="en"><br>
                <input form="new_razdel" class="form-control" type="text" name="name_razdel_text_lt" value="" placeholder="lt">
            </td>
            <td><input form="new_razdel" class="form-control" type="text" name="url_razdel_name" data-controll="url" value=""></td>
            <td><input form="new_razdel" class="form-control-file" type="file" name="url_icon_razdel" accept=".gif,.jpg,.jpeg,.png,.svg"></td>
            <td><input form="new_razdel" class="form-control-file" type="file" name="url_icon2_razdel" accept=".gif,.jpg,.jpeg,.png,.svg"></td>
            <td><input form="new_razdel" type="number" class="form-control form-order-num" name="razdel_order_num" value="0" step="1"></td>
            <td>
                <form method="post" name="new_razdel" id="new_razdel" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_new">
                    <input type="button" class="btn btn-warning form-new-cancel" value="отмена">
                    <input type="button" class="btn btn-success save-btn" value="сохранить">
                </form>

            </td>
        </tr>
        <?php if ($razdels): ?>
        <?php foreach ($razdels as $r): ?>
        <tr>
            <td><?= $r->getIdRazdel()?> <input form="new_razdel_<?= $r->getIdRazdel() ?>" type="hidden" name="id_razdel" value="<?= $r->getIdRazdel()?>"></td>
            <td><?= $r->getNameRazdelText()?>
                <input form="new_razdel_<?= $r->getIdRazdel() ?>" class="form-control form-edit" type="text" name="name_razdel_text" value="<?= $r->getNameRazdelText()?>" placeholder="ru"><br>
                <input form="new_razdel_<?= $r->getIdRazdel() ?>" class="form-control form-edit" type="text" name="name_razdel_text_en" value="<?= $r->getNameRazdelText('en', 1)?>" placeholder="en"><br>
                <input form="new_razdel_<?= $r->getIdRazdel() ?>" class="form-control form-edit" type="text" name="name_razdel_text_lt" value="<?= $r->getNameRazdelText('lt', 1)?>" placeholder="lt">
            </td>
            <td><?= $r->getUrlRazdelName()?><input form="new_razdel_<?= $r->getIdRazdel() ?>" class="form-control form-edit" type="text" name="url_razdel_name" data-controll="url" <?= (\bb\models\User::getCurrentUser()->hasPermission(5) ? '' : 'readonly="readonly"') ?> value="<?= $r->getUrlRazdelName()?>"></td>
            <td class="image-back"><img class="razd-icon" src="<?= $r->getUrlIconRazdel()?>"><input form="new_razdel_<?= $r->getIdRazdel() ?>" class="form-control-file form-edit" type="file" name="url_icon_razdel" accept=".gif,.jpg,.jpeg,.png,.svg"></td>
            <td><img class="razd-icon" src="<?= $r->getUrlIcon2Razdel()?>"><input form="new_razdel_<?= $r->getIdRazdel() ?>" class="form-control-file form-edit" type="file" name="url_icon2_razdel" accept=".gif,.jpg,.jpeg,.png,.svg"></td>
            <td><?= $r->getRazdelOrderNum()?><input form="new_razdel_<?= $r->getIdRazdel() ?>" type="number" class="form-control form-order-num form-edit" name="razdel_order_num" value="<?= $r->getRazdelOrderNum()?>" step="1"></td>
            <td>
                <form method="post" name="new_razdel_<?= $r->getIdRazdel() ?>" id="new_razdel_<?= $r->getIdRazdel() ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_new">
                    <input type="button" class="btn btn-info change-btn show-block" value="изменить">
                    <input type="button" class="btn btn-warning form-edit cancel-btn" value="отмена">
                    <input type="button" class="btn btn-success save-btn form-edit" value="сохранить">
                    <input type="button" class="btn btn-danger form-edit delete-btn" value="удалить">
                </form>

            </td>
        </tr>
        <?php endforeach;?>
    <?php endif;?>
    </tbody>


</table>

<script src="/bb/assets/js/razdel.js"></script>
<?php


echo \bb\Base::PageEndHTML();


?>
