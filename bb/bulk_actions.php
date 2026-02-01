<?php
namespace bb;
use bb\classes\bron;
use bb\classes\Category;
use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\classes\Razdel;
use bb\classes\Tariff;
use bb\classes\TariffModel;

session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);


//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Tariff.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/ModelWeb.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/TariffModel.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Category.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Model.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Razdel.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); // включаем класс


require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');


echo \bb\Base::pageStartB5('Массовые операции.');
\bb\Base::loginCheck();
//Base::PostCheckVarDumpEcho();

$razdel_id=0;
$cat_id=0;
$message=[];

if (isset($_POST['razdel_id'])) $razdel_id = $_POST['razdel_id'];

$razdels = Razdel::getAll();

$cats=[];
if ($razdel_id >0 ) $cats = Category::getCategoriesForRazdel($razdel_id);
if (!$cats) $cats = [];

if (isset($_POST['action'])){
  switch ($_POST['action']){
    case 'apply':
      $baseDays = $_POST['tarif_base_days'];
        $baseDays = intval($baseDays);
      $linePeriod = $_POST['tarif_line_period'];
        $modelIds = [];
        $catIds = $_POST['cat_id'];
        foreach ($catIds as $catId) {
          //echo 'cat_id: '.$catId.'<br>';
          $newIds = Category::getModelsForCategoryById($catId);
          //Base::varDamp($newIds);
          if ($newIds && count($newIds)>0)  $modelIds = array_merge($modelIds, $newIds);

        }
        //Base::varDamp($modelIds);

        //$modelIds = array_unique($modelIds);
        $mysqli = Db::getInstance()->getConnection();
        $query = "UPDATE rent_model_web SET tarif_line_period = '$linePeriod', tarif_base_days = '$baseDays' WHERE model_id IN (".implode(',', $modelIds).")";
        $result = $mysqli->query($query);
        if (!$result) {printf("Mysqli Errormessage: %s\n", $mysqli->error);}
        Base::addClientMessage('Изменения внесены!');
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
        <a class="nav-item nav-link" href="/bb/kr_baza_new.php">Товары</a>
<!--        <a class="nav-item nav-link" href="/bb/favorite_tovars_management.php">Популярные товары</a>-->
<!--        <a class="nav-item nav-link" href="/bb/l3_karn_dop.php">Доп.поля карнавала</a>-->
      </div>
    </div>
  </nav>

  <?php
    if (!\bb\models\User::getCurrentUser()->hasPermission(2)){
      echo "Нет доступа к странице (обратитесь к администратору)";
      echo \bb\Base::pageEndHtmlB5();
      die();
    }


  ?>

  <form class="container-fluid" method="post">
    <div class="row">
      <div class="col alert-danger text-center">Внимание, действия массовые и необратимые. Будьте внимательны!</div>
    </div>
    <?php if (Base::hasClientMessages()): ?>
      <div class="row">
        <?php foreach (Base::getClientMessages() as $clientMessage): ?>
          <div class="col alert-success text-center"><?= $clientMessage ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
   <div class="row mt-3">
     <div class="col-3">
       <div class="row">
         <div class="col-3">
           Разделы
         </div>
         <div class="col">
           <select class="form-select" name="razdel_id" onchange="this.form.submit()">
             <option value="0">Выберите раздел</option>
             <?php foreach ($razdels as $r): ?>
               <option value="<?= $r->getIdRazdel() ?>" <?= Base::sel_d($r->getIdRazdel(), $razdel_id) ?>><?= $r->getNameRazdelText() ?></option>
             <?php endforeach;?>
           </select>
         </div>
       </div>
     </div>
     <div class="col-3">
       <div class="row">
         <div class="col-3">
           Категории (ctrl, shift)
         </div>
         <div class="col">
           <select class="form-select" name="cat_id[]" multiple size="<?= count($cats)<=1 ? 1 : (min(10, count($cats))) ?>">
             <?php if ($razdel_id < 1): ?>
               <option value="-">Выберите раздел</option>
             <?php else: ?>
                <?php if (count($cats)<1): ?>
                 <option value="0">категории отсутствуют</option>
                <?php endif; ?>

              <?php foreach ($cats as $c): ?>
               <option value="<?= $c->getId() ?>" <?= Base::sel_d($c->getId(), $cat_id) ?>><?= $c->getName() ?></option>
              <?php endforeach;?>

             <?php endif; ?>


           </select>
         </div>
       </div>
     </div>
     <div class="col">
       <div class="row photo-row">
         <div class="col-6">
           период для линии тарифов
         </div>
         <div class="col">
           <select class="form-select" name="tarif_line_period">
             <option value="day">день</option>
             <option value="week">неделя</option>
             <option value="month">месяц</option>
           </select>
         </div>
       </div>
       <div class="row photo-row">
         <div class="col-6">
           база для +\- тарифов (в сутках)
         </div>
         <div class="col">
           <input class="form-control" name="tarif_base_days" type="number" min="1" value="0">
         </div>
       </div>
     </div>
   </div>
   <div class="row">
     <div class="col">
       <button class="btn btn-warning" type="submit" name="action" value="apply" onclick="return bulkCheck1();">Применить</button>
     </div>
   </div>
  </form>

  <script>
    function bulkCheck1(){
      let razdel = document.querySelector('[name="razdel_id"]');
      let cats = document.querySelector('[name="cat_id[]"]');

      if (razdel.value*1 < 1){
        alert('Выберите раздел');
        return false;
      }
      if (cats.selectedOptions.length<1){
        alert('Выберите категорию (-и)');
        return false;
      }

    }
  </script>

<?php

  echo \bb\Base::pageEndHtmlB5();

