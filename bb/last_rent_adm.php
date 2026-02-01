<?php

use bb\Base;
use bb\classes\LastRent;
use bb\classes\Model;
use bb\Db;


session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/LastRent.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Model.php'); //

Base::loginCheck();

echo Base::PageStartAdvansed('Последний прокат', 2);

echo '<style type="text/css">
.img2{
width: 200px;
}
</style>';

//Base::PostCheckVarDumpEcho();
//echo php_ini_loaded_file();
$inv_n=Base::GetPost('inv_n');

if (isset($_POST['action']) && $_POST['action']=='Сохранить') {
    $inv_n=Base::GetPost('inv_n');

    $lr=LastRent::getByInvN($inv_n);

    $lr->dop_info=Base::GetPost('dop_info');
    $lr->inv_n=Base::GetPost('inv_n');
    $lr->sale_price=Base::GetPost('sale_price');

    for ($i = 0; $i <= 5; $i++) {

        $post_name='f'.$i;
        $del_name=$post_name.'_del';
        if (isset($_POST[$del_name])){
            $lr->delFile($i);
            continue;
        }

        if (!(isset($_FILES[$post_name]['name']) && $_FILES[$post_name]['name']!='')) continue; //skip empty files
            //echo 'run';
            $rez = Base::imgUpload($post_name, $inv_n.'_'.$i, '../images/last_rent/');
            if (!$rez[0]) {
                echo '<div class="row">';
                echo $rez[1];
                echo '</div>';
            }
            else {
                $lr->addFileUrl($i, '/images/last_rent/'.$rez[2]);
                //echo '---'.$rez[2].'<br>';
            }
    }

    //Base::varDamp($lr);

    $lr->save();
}

//$inv_n=719197;
$lr=LastRent::getByInvN($inv_n);
//Base::varDamp($lr);

echo '
<script type="text/javascript">
function save_f() {
    if ($("#dop_info").val()=="") {
        alert ("Заполните причину выбытия");
        return false;
    }
    if($("#sale_price").val()<1){
        alert ("Заполните цену последнего проката");
        return false;
    }
    
    return true;
  
}

</script>
';


echo '
<nav class="navbar navbar-expand-md navbar-light bg-light">
    <a class="navbar-brand" href="/bb/index.php">Главная</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <form method="post" action="/bb/kr_baza_new.php" style="display: none"><input type=""></form>
                <a class="nav-link" href="/bb/kr_baza_new.php">Все товары</a>
            </li>
        </ul>
    </div>
</nav>
';

echo '
<div class="row">
    <div class="card">
        <div class="card-body">
            <p class="card-text alert-info">'.$lr->model->getFullName().'</p>
        </div>
    </div>
</div>
';

echo '
        <form name="lr_form" id="lr_form" method="post" enctype="multipart/form-data" action="last_rent_adm.php" class="col">
          <div class="form-group row">
            инв. номер:'.$lr->inv_n.'
            <input type="hidden" id="inv_n" name="inv_n" value="'.$lr->inv_n.'">
          </div>
          <div class="form-group row">
            <label for="dop_info">Причина выбытия:</label>
            <textarea class="form-control" rows="4" name="dop_info" id="dop_info">'.$lr->dop_info.'</textarea>
          </div>
          <div class="form-group row">
            <label for="sale_price">Цена последнего проката (бел.руб.):</label>
            <input type="number" class="text-right" name="sale_price" id="sale_price" step="1" min="0" value="'.$lr->sale_price.'">
          </div>
          <div class="form-group row">
              <div class="card" style="width: 18rem">
                '.($lr->getFileUrl(0) ? '<img class="card-img-top" src="'.$lr->getFileUrl(0).'">
                    <div class="form-check mx-auto">
                        <input type="checkbox" class="form-check-input" name="f0_del" id="f0_del">
                        <label class="form-check-label" for="f0_del">удалить файл</label>
                    </div>
                                                                                                    ' : '').'
                <h5 class="card-title">Главное фото (квадратное!):</h5>
                <div class="card-body">
                <input type="file" class="form-control-file" id="f0" name="f0">
                </div>
              </div>  ';

    for ($i=1; $i<6;$i++) {
             echo'
              <div class="card" style="width: 18rem">
              '.($lr->getFileUrl($i) ? '<img class="card-img-top" src="'.$lr->getFileUrl($i).'"><br>
                        <div class="form-check mx-auto">
                            <input type="checkbox" class="form-check-input" name="f'.$i.'_del" id="f'.$i.'_del">
                            <label class="form-check-label" for="f'.$i.'_del">удалить файл</label>
                        </div>
                                        ' : '').'
                <h5>доп. фотка №'.$i.':</h5>
                <div class="card-body">
                    <input type="file" class="form-control-file" id="f'.$i.'" name="f'.$i.'">
                </div>
              </div>';
    }



echo '
          
      </div>   
                  
          
         
          <div class="form-group row">
            <input type="submit" class="btn btn-success" onclick="return save_f();" name="action" value="Сохранить" >
          </div>
          
        </form>



';




echo Base::PageEndHTML();
?>