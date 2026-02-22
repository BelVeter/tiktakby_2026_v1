<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Razdel.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/SubRazdel.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/SubRazdelPage.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
//(\bb\models\User::getCurrentUser()->hasPermission(5) ? '' : 'readonly="readonly"')

echo \bb\Base::PageStartAdvansed('Под-разделы.');
\bb\Base::loginCheck();

//\bb\Base::PostCheckVarDumpEcho();
//\bb\Base::varDamp($_FILES);

$razdel_id = 0;
$subrazdel_id = 0;

if (isset($_GET['razdel_id']))
    $razdel_id = \bb\Base::getGet('razdel_id');
if (isset($_GET['subrazdel_id']))
    $subrazdel_id = \bb\Base::getGet('subrazdel_id');

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'save_new':
            $sr = new \bb\classes\SubRazdel();
            if (isset($_POST['id_sub_razdel']))
                $sr->setIdSubRazdel($_POST['id_sub_razdel']);
            $sr->setNameSubRazdelText($_POST['name_sub_razdel_text']);
            $sr->setNameSubRazdelText($_POST['name_sub_razdel_text_en'], 'en');
            $sr->setNameSubRazdelText($_POST['name_sub_razdel_text_lt'], 'lt');
            $sr->setUrlSubRazdelName($_POST['url_sub_razdel_name']);
            $sr->setOrderNumSubRazd($_POST['order_num_sub_razd']);
            $sr->setMainRazdelId($_POST['main_razdel_id']);
            $sr->addRazdelId($_POST['main_razdel_id']);


            if (isset($_POST['razdel'])) {
                foreach ($_POST['razdel'] as $id) {
                    $sr->addRazdelId($id);
                }
            }
            if (isset($_POST['category'])) {
                foreach ($_POST['category'] as $id) {
                    $sr->addCategoryId($id);
                }
            }

            //icon
            $dir_short = '/public/img/topmenu/';
            $dir_full = $_SERVER['DOCUMENT_ROOT'] . $dir_short;

            //if file sent
            if (key_exists('url_sub_razdel_icon', $_FILES) && $_FILES['url_sub_razdel_icon']['name'] != '') {
                //echo 'file set part launched';
                $file_name = $_FILES['url_sub_razdel_icon']['name'];

                if (key_exists('url_sub_razdel_icon', $_FILES)) {
                    $savedPath = \bb\Base::processAndSaveImageAsWebp($dir_short, $_FILES['url_sub_razdel_icon']['tmp_name'], $file_name);
                    if ($savedPath)
                        $sr->setUrlSubRazdelIcon($savedPath);
                }
                //delete old file if exists
                if ($sr->getIdSubRazdel() > 0) {
                    $old_sr = \bb\classes\SubRazdel::getById($sr->getIdSubRazdel());
                    if ($old_sr->getUrlSubRazdelIcon() != '') {
                        \bb\Base::delFile($old_sr->getUrlSubRazdelIcon());
                    }
                }
            } elseif ($sr->getIdSubRazdel() > 0) {//if file not sent - get existing link
                $old_sr = \bb\classes\SubRazdel::getById($sr->getIdSubRazdel());
                $sr->setUrlSubRazdelIcon($old_sr->getUrlSubRazdelIcon());
            }


            //            \bb\Base::varDamp($sr);
            $sr->save();

            break;
        case 'delete':
            $sr = \bb\classes\SubRazdel::getById($_POST['id_sub_razdel']);

            if ($sr->getIdSubRazdel() > 0 && $sr->getUrlSubRazdelIcon() != '') {
                \bb\Base::delFile($sr->getUrlSubRazdelIcon());
            }

            $sr->delete();

            break;
    }
}




$p = new \bb\classes\SubRazdelPage('ru', $razdel_id, $subrazdel_id);
?>
<link rel="stylesheet" href="/bb/assets/styles/sub_razd.css?v=2">
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup"
        aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
        <div class="navbar-nav">
            <a class="nav-item nav-link" href="/bb/kr_baza_new.php">Товары</a>
            <a class="nav-item nav-link" href="/bb/razdel_manage.php">Управление разделами</a>
            <a class="nav-item nav-link active" href="/bb/sub_razdel_manage.php">Управление подразделами</a>
            <a class="nav-item nav-link" href="/bb/category_management.php">Управление категориями</a>

        </div>
    </div>
</nav>
<div style="display: flex; justify-content: flex-end"><button class="btn btn-info new-btn">Ввести новый
        подраздел</button></div>
<table class="table">
    <thead>
        <tr>
            <th>Разделы<br>
                <select form="srch_form" class="form-control-sm subrazdel-filter" name="razdel_id" <?= ($subrazdel_id > 0 ? 'disabled' : '') ?>>
                    <option value="0" <?= ($razdel_id == 0 ? 'selected' : '') ?>>все разделы</option>
                    <?php foreach ($p->getAllRazdels() as $r): ?>
                        <option value="<?= $r->getIdRazdel() ?>" <?= ($razdel_id == $r->getIdRazdel() ? 'selected' : '') ?>>
                            <?= $r->getNameRazdelText() ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </th>
            <th>id</th>
            <th>Подраздел<br>
                <select form="srch_form" class="form-control-sm subrazdel-filter choices-single" name="subrazdel_id"
                    <?= ($razdel_id > 0 ? 'disabled' : '') ?>>
                    <option value="0" <?= ($subrazdel_id == 0 ? 'selected' : '') ?>>все подразделы</option>
                    <?php
                    $subrazdels = $p->getSubRazdelsAll();
                    usort($subrazdels, function (\bb\classes\SubRazdel $a, \bb\classes\SubRazdel $b) {
                        return strcmp($a->getNameSubRazdelText(), $b->getNameSubRazdelText());
                    });
                    ?>
                    <?php foreach ($subrazdels as $sr): ?>
                        <option value="<?= $sr->getIdSubRazdel() ?>" <?= ($subrazdel_id == $sr->getIdSubRazdel() ? 'selected' : '') ?>><?= $sr->getNameSubRazdelText() ?></option>
                    <?php endforeach; ?>
                </select>
            </th>
            <th>url-ключ</th>
            <th>иконка</th>
            <th>№ сортировка</th>
            <th>Категории</th>
            <th>действия
                <form name="srch_form" id="srch_form" method="get"></form>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr class="new-form-row">
            <td><select class="form-control-sm" name="main_razdel_id" form="new_form">
                    <option value="0">Выбрать основной раздел</option>
                    <?php foreach ($p->getAllRazdels() as $r): ?>
                        <option value="<?= $r->getIdRazdel() ?>"><?= $r->getNameRazdelText() ?></option>
                    <?php endforeach; ?>
                </select><br>
                <?= $p->getRazdelCheckBoxes('new_form') ?></td>
            <td></td>
            <td><input class="form-control" type="text" name="name_sub_razdel_text" form="new_form"><br>
                <input class="form-control" type="text" name="name_sub_razdel_text_en" form="new_form"
                    placeholder="en"><br>
                <input class="form-control" type="text" name="name_sub_razdel_text_lt" form="new_form" placeholder="lt">
            </td>
            <td><input class="form-control" type="text" name="url_sub_razdel_name" form="new_form" data-controll="url">
            </td>
            <td><input class="form-control" type="file" name="url_sub_razdel_icon" form="new_form"
                    accept=".gif,.jpg,.jpeg,.png,.svg"></td>
            <td><input class="form-control" type="number" step="1" name="order_num_sub_razd" form="new_form" value="0">
            </td>
            <td>
                <div class="form-check">
                    <input class="form-check-input filter_input" type="checkbox" value="1">
                    <input title="фильтр" placeholder="фильтр" type="text"
                        class="form-control form-control-sm cat_filter_text">
                </div>
                <?= $p->getCatCheckBoxes('new_form') ?>
            </td>
            <td>
                <form name="new_form" id="new_form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_new">
                    <input type="button" class="btn btn-warning form-new-cancel" value="отмена">
                    <input type="button" class="btn btn-success save-btn" value="сохранить">
                </form>
            </td>
        </tr>
        <?php if ($p->getSubRazdelsAll()): ?>
            <?php foreach ($p->getSubRazdelsAll() as $sr): ?>
                <tr>
                    <td>
                        <span
                            class="hide-when-edit"><?= $p->getRazdelsString($sr->getBindedRazdelIds(), $sr->getMainRazdelId()) ?></span>
                        <?= $p->getMainRazdelSelect('new_form', $sr->getIdSubRazdel(), $sr->getMainRazdelId()) ?>
                        <div class="edit-field">
                            <?= $p->getRazdelCheckBoxes('new_form_' . $sr->getIdSubRazdel(), $sr->getBindedRazdelIds(), $sr->getMainRazdelId()); ?>
                        </div>
                    </td>
                    <td><?= $sr->getIdSubRazdel() ?></td>
                    <td><?= $sr->getNameSubRazdelText() ?>
                        <input class="form-control edit-field" type="text" name="name_sub_razdel_text"
                            form="new_form_<?= $sr->getIdSubRazdel() ?>" value="<?= $sr->getNameSubRazdelText() ?>"><br>
                        <input class="form-control edit-field" type="text" name="name_sub_razdel_text_en"
                            form="new_form_<?= $sr->getIdSubRazdel() ?>" value="<?= $sr->getNameSubRazdelText('en', 1) ?>"
                            placeholder="en"><br>
                        <input class="form-control edit-field" type="text" name="name_sub_razdel_text_lt"
                            form="new_form_<?= $sr->getIdSubRazdel() ?>" value="<?= $sr->getNameSubRazdelText('lt', 1) ?>"
                            placeholder="lt">
                    </td>
                    <td><?= $sr->getUrlSubRazdelName() ?>
                        <input class="form-control edit-field" type="text" name="url_sub_razdel_name"
                            form="new_form_<?= $sr->getIdSubRazdel() ?>" <?= (\bb\models\User::getCurrentUser()->hasPermission(5) ? '' : 'readonly="readonly"') ?> value="<?= $sr->getUrlSubRazdelName() ?>" data-controll="url">
                    </td>
                    <td><img class="form-icon" src="<?= $sr->getUrlSubRazdelIcon() ?>">
                        <input class="form-control edit-field" type="file" name="url_sub_razdel_icon"
                            form="new_form_<?= $sr->getIdSubRazdel() ?>">
                    </td>
                    <td><?= $sr->getOrderNumSubRazd() ?>
                        <input class="form-control edit-field" type="number" step="1" name="order_num_sub_razd"
                            form="new_form_<?= $sr->getIdSubRazdel() ?>" value="<?= $sr->getOrderNumSubRazd() ?>">
                    </td>
                    <td>
                        <span class="hide-when-edit"><?= $p->getCategoriesString($sr->getBindedCatIds()) ?></span>
                        <div class="form-check edit-field">
                            <input class="form-check-input filter_input" type="checkbox" value="1">
                            <input title="фильтр" placeholder="фильтр" type="text"
                                class="form-control form-control-sm cat_filter_text">
                        </div>
                        <div class="edit-field">
                            <?= $p->getCatCheckBoxes('new_form_' . $sr->getIdSubRazdel(), $sr->getBindedCatIds()); ?>
                        </div>
                    </td>
                    <td>
                        <form name="new_form_<?= $sr->getIdSubRazdel() ?>" id="new_form_<?= $sr->getIdSubRazdel() ?>"
                            method="post" enctype="multipart/form-data">
                            <input type="hidden" name="id_sub_razdel" value="<?= $sr->getIdSubRazdel() ?>">
                            <input type="button" class="btn btn-info change-btn hide-when-edit show-block" value="изменить">
                            <input type="hidden" name="action" value="save_new">
                            <input type="button" class="btn btn-warning cancel-btn edit-field" value="отмена">
                            <input type="button" type="submit" class="btn btn-success save-btn edit-field" value="сохранить">
                            <input type="button" class="btn btn-danger edit-field delete-btn" value="удалить">
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script src="/bb/assets/js/sub_razdel.js?v=5"></script>


<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js@9.0.1/public/assets/styles/choices.min.css" />
<script src="https://cdn.jsdelivr.net/npm/choices.js@9.0.1/public/assets/scripts/choices.min.js"></script>

<script>
    new Choices(document.querySelector('[name="subrazdel_id"]'));
</script>

<style>
    .choices__list--dropdown .choices__item--selectable:after {
        content: '';
        font-size: unset;
        opacity: unset;
        position: unset;
        right: unset;
        top: unset;
        transform: unset;
    }

    .choices__list--dropdown .choices__item--selectable {
        padding-right: 5px;
    }

    th .choices {
        font-weight: normal !important;
    }
</style>

<?php
\bb\Base::PageEndHTML();
?>