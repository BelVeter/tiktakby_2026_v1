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
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/bron.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Category.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Razdel.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); // включаем класс


require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/KBronForm.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');


//------- proverka paroley
$in_level= array(0,5,7);

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level))) {
	die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	</head>
	<body>
        <a href="/bb/index.php">Залогиниться</a>
	</body></html>');
}

//-----------proverka paroley

//Проверка входящей информации
//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//foreach ($_POST as $key => $value) {
//	echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

if (isset($_POST['action'])) {
  $action = Base::GetPost('action');
    switch ($action){
      case 'sortn':
        //echo 'start';
        $modelIds = $_POST['modelid'];
        $sortNs = $_POST['sortn'];

        $count = count($modelIds);

        for ($i=0;$i<$count;$i++){
          ModelWeb::updateSortN($modelIds[$i], $sortNs[$i]);
        }

//        Base::varDamp($modelIds);
//        Base::varDamp($sortNs);
        break;
    }
}

//ajax requests processing
$isAjax = false ;
if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $isAjax = true ;

    // сюда попадаем в случае AJAX-запроса
    if (isset($_POST['action'])) {
        $action = Base::GetPost('action');
        switch ($action){
          case 'qr_change':
            $item_id = Base::GetPost('item_id');
            $qr_yn = Base::GetPost('qr_yn');

            if ($qr_yn==0) {
                $qr_yn=1;
            }
            else {
                $qr_yn=0;
            }

            $tov = \bb\classes\tovar::getTovForQr($item_id, $qr_yn);

            $tov->qrUpdate();

            $res['status']='ok';
            $res['result']= $tov->krBasaQr();
            $res['param']='';

            $result=json_encode($res);
            echo $result;

            break;
        }

    }


    exit();
}


$razdel=1;
$cat_id='';
$item_status='all';
$item_place='all';
$producer_srch='';
$last_rent='all';
$el_srch='';
$karnaval_models = Category::getKarnavalCatIdsArray();
$qr_srch='all';
$inv_srch='';
$model_srch='';

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}


if (isset($move_action)) {
	switch ($move_action) {
		case 'move':
			$tov=new \bb\classes\tovar();
			$tov->item_id_load($item_id);
			if ($tov->item_place!=$place_1 || $tov->to_move>0) {
				die ('Кто-то уже изменил расположение товара. Попробуйте заново.');
			}
			else {
				$tov->to_move=$place_2;
				$tov->item_update();

				bron::unsetCallToCustomer($tov->item_inv_n);

				echo 'Товар успешно отправлен на '.$tov->to_move.' офис';
			}

		break;
		case 'back':
			$tov=new \bb\classes\tovar();
			$tov->item_id_load($item_id);
			if ($tov->item_place!=$place_1 && $tov->to_move!=$tov->to_move) {
				die ('Кто-то уже изменил расположение товара. Попробуйте заново.');
			}
			else {
				$tov->to_move='0';
				$tov->item_update();
				echo 'Товар успешно возвращен на '.$tov->item_place.' офис';
			}

		break;
		case 'acs': //acsept
			$tov=new \bb\classes\tovar();
			$tov->item_id_load($item_id);
			if ($tov->item_place!=$place_1 && $tov->to_move!=$tov->to_move) {
				die ('Кто-то уже изменил расположение товара. Попробуйте заново.');
			}
			else {
				$tov->to_move='0';
				$tov->item_place=$place_2;
				$tov->item_update();
				echo 'Товар успешно принят на '.$tov->item_place.' офис';
				if ($tov->status=='bron'){
				    bron::setCallToCustomer($tov->item_inv_n);
                }
			}
		break;

	}
}

$inv_srch=Base::getNumbersOnly($inv_srch);
if ($inv_srch>0 || $model_srch!='') {//clear all the filters if we have model or inv srch
    $cat_id='all';
    //$item_status='all';
    //$item_place='all';
    //$elza='all';
    //$qr_srch='all';
    if ($inv_srch>0) {
        $model_srch='';
    }
}

$srch_ar=array();

if ($item_place!='all') {
    $srch_ar[]="item_place='$item_place'";
}

if ($cat_id!='all') {
    $srch_ar[]="tovar_rent.tovar_rent_cat_id='$cat_id'";
}
else{
  $r = Razdel::getById($razdel);
  $catIds = Category::getCatIdsForRazdelUrlName($r->getUrlRazdelName());
  if ($catIds) $srch_ar[]="tovar_rent.tovar_rent_cat_id IN('".(implode("', '", $catIds))."')";
}


if ($item_status!='all') {

	if ($item_status=='to_rent') {
        $srch_ar[]="(`status`='$item_status' OR (`status`='t_bron' AND br_time<".time()."))";
	}
	elseif ($item_status=='all_off') {//товары на офисе

        $srch_ar[]="(`status`!='rented_out' OR item_inv_n IN (
            SELECT rent_deals_act.item_inv_n
            FROM rent_sub_deals_act
            LEFT JOIN rent_deals_act ON rent_deals_act.deal_id=rent_sub_deals_act.deal_id
            WHERE `status`='for_cur' AND `type`='first_rent'
        ))";

	}
	elseif ($item_status=='on_move') {
        $srch_ar[]="(`status`!='rented_out' AND item_place>0 AND to_move>0)";
    }
    elseif ($item_status=='on_move_tome') {
        $srch_ar[]="(`status`!='rented_out' AND item_place>0 AND to_move=".\bb\models\Office::getCurrentOffice()->number.")";
    }
	else{
        $srch_ar[]="`status`='$item_status'";
	}
}

if ($last_rent!='all') {
    $srch_ar[]="`state`='3'";
}


if ($producer_srch!='') {
    $srch_ar[]="tovar_rent_items.producer LIKE '%$producer_srch%'";
}

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="initial-scale=1.0, width=device-width">

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/css/bootstrap-select.min.css">

    <!-- Latest Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">


    <!-- JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>



    <!-- Latest compiled and minified JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/js/bootstrap-select.min.js"></script>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<style>
    .cont{
      display: flex;
      gap: 30px;
    }
    .sort_n_input{
        width: 100%;
    }
    .hide{
        display: none;
    }
</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
';

echo Base::getBarCodeReaderScript();
?>

<!--Увеличение картинок-->
    <script type="text/javascript">
function pic_size(id) {
    target='#pic_'+id;
    //alert(target);
    p=$(target);
    if (p.width()<=40) {
        p.width('100%');
        p.height('100%');
    }
    else {
        p.width('40');
        p.height('40');
    }
}
</script>

<?php

KBronForm::RequiredEcho();

echo'
</head>
<title>База товаров.</title>
<body>


';

include_once ($_SERVER['DOCUMENT_ROOT'].'/bb/top_menu.php');
?>

    <div class="px-2">
        <a class="btn btn-secondary mr-2" style="background-color: #3b66a2" href="/bb/kr_baza.php">Товары к возврату</a>
        <a class="btn btn-secondary mr-2" style="background-color: #12b2ea" href="/bb/scanner_tovar.php">Карточка товара</a>
        <!--<a class="btn btn-danger mr-2" style="background-color: #f48b80" href="#">Списанные товары</a>-->
    </div>



<script language="javascript">

history.pushState(null, null, location.href);
window.onpopstate = function(event) {
    history.go(1);
};

function of_menu_hide (id) {
	document.getElementById('div_place_'+id).style.display="none";
}

function place_show (id) {
	//alert ('zapusk');

	document.getElementById('div_place_'+id).style.display="block";

}//end of place_show

function move_off (item_id, pl1, pl2, action) {
	document.getElementById('off_ch_item_id').value=item_id;
	document.getElementById('off_ch_place_1').value=pl1;
	document.getElementById('off_ch_place_2').value=pl2;
	document.getElementById('off_ch_move').value="1";
	document.getElementById('off_ch_move_action').value=action;
	document.getElementById('cat_ch_sel').submit();
}


function menu_show (item_id, inv_n) {
	//alert ('zapusk');

	document.getElementById('hist_'+item_id).innerHTML='<ul class="i_menu"> <li><a href="#" onclick="hist_show(\'tov_hist\', \''+item_id+'\', \''+inv_n+'\'); return false;">История</a></li>  <?php echo '<li><a href="#" onclick="document.getElementById(\\\'web_info_\'+item_id+\'\\\').submit(); return false;">WEB info</a></li> <li><a href="#" onclick="document.getElementById(\\\'tovar_lr_\'+item_id+\'\\\').submit(); return false;">в последний прокат</a></li>'; echo ($_SESSION['level']>=5 || \bb\models\User::getCurrentUser()->getId()==26) ? ' <li><a href="#" onclick="document.getElementById(\\\'tovar_tarif_\'+item_id+\'\\\').submit(); return false;">Тарифы</a></li>   <li><a href="#" onclick="document.getElementById(\\\'tovar_edit_\'+item_id+\'\\\').submit(); return false;">Редактировать товар</a></li>   <li><a href="#" onclick="document.getElementById(\\\'model_edit_\'+item_id+\'\\\').submit(); return false;">Редактировать модель</a></li>   <li><a href="#" onclick="document.getElementById(\\\'fav_tovar_\'+item_id+\'\\\').submit(); return false;">В популярные товары</a></li>    <li><a href="#" onclick="document.getElementById(\\\'tovar_del_\'+item_id+\'\\\').submit(); return false;">Удаление</a></li>' : ''; ?> </ul>  	<input type="button" value="х" onclick="document.getElementById(\'hist_'+item_id+'\').innerHTML=\'\'; return false;" style="position:absolute; top:5px; left:160px; z-index:3;"/>';

}//end of menu_show


function hist_show (action, model_id, inv_n) {
	//alert ('zapusk');
	document.getElementById('hist_'+model_id).innerHTML='<img src="w.gif" />';

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/baza_ads.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'action=' + encodeURIComponent(action) + '&model_id=' + encodeURIComponent(model_id) + '&item_inv_n=' + encodeURIComponent(inv_n);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {

	    	 document.getElementById('hist_'+model_id).innerHTML=xmlhttp.responseText;

			   }
	  		}
		}

}//end of hist_show



function getXmlHttp(){
	  var xmlhttp;
	  try {
	    xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
	  } catch (e) {
	    try {
	      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	    } catch (E) {
	      xmlhttp = false;
	    }
	  }
	  if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
	    xmlhttp = new XMLHttpRequest();
	  }
	  return xmlhttp;
}

function qr_send($tov_id) {

    var qr = $("#qr_yn_"+$tov_id).val();

    if (qr*1==0 || (qr*1==1 && confirm('Вы точно хотите снять отметку о вводе QR кода на товар?'))) {

        var $form = $("#qr_form_" + $tov_id);
        //$("#kb_button_"+inv_n).hide('slow');
        $("#qr_div_" + $tov_id).empty();
        $("#qr_div_" + $tov_id).append('<img src="/bb/w2.gif" style="width: 70px;"/>');
        $.ajax(
            {
                type: $form.attr('method'),
                url: "/bb/kr_baza_new.php",
                data: $form.serialize(),
            }
        ).done(function (data) {
            var rez = JSON.parse(data);
            //alert (rez);
            $("#qr_div_" + $tov_id).empty();
            $("#qr_div_" + $tov_id).append(rez.result);

        });
    }
}

window.onload = ()=>{
  let razdelRadios = document.querySelectorAll('[name="razdel"]');
  // console.log(razdelRadios);
  razdelRadios.forEach((r)=>{
    //console.log(r);
    r.addEventListener('change', ()=>{
      // document.querySelector('#cat_ch_sel').submit();
      document.querySelector('[name="cat_id"]').value='';
      r.form.submit();
    });
  });
};

function catSelect(){
  let form1 = document.querySelector('form[name="cat_chose"]');
  let razdelId = ul = document.querySelector('ul.nav.nav-tabs label.active').nextElementSibling.value;

  let razdelInput = document.createElement('input');
    razdelInput.type = 'hidden';
    razdelInput.name = 'razdel';
    razdelInput.value = razdelId;

  form1.append(razdelInput);

}

</script>




<?php


if ($qr_srch!='all') {
    $srch_ar[]="qr_yn='$qr_srch'";
}

$inv_srch=Base::getNumbersOnly($inv_srch);

if ($inv_srch>0) {
    $srch_ar = array();//skidivayem vse filtri
    $srch_ar[]='item_inv_n='.$inv_srch;
}
elseif ($model_srch!='') {
  $srch_ar=[];
    $srch_ar[]="model_id IN (SELECT tovar_rent_id FROM tovar_rent WHERE tovar_rent.model LIKE '%$model_srch%' OR tovar_rent.producer LIKE '%$model_srch%') OR model_id='$model_srch'";
}

if (!$srch=Db::makeQueryConditionFromArray($srch_ar)) {
    $srch='';
}

//для списка категорий
$mysqli = \bb\Db::getInstance()->getConnection();

//$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
//$result_cats = $mysqli->query($query_cats);
//if (!$result_cats) {die('Сбой при доступе к базе данных: '.$query_cats.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
if ($razdel!='all') {
  $cats = Category::getCategoriesForRazdel($razdel);
  if (is_array($cats)) {
    usort($cats, function (Category $a, Category $b){
      return strcmp($a->getName(), $b->getName());
    });
  }
  //Base::varDamp($cats);
}
else{
  $cats = Category::getAllCategories();
}


//для списка офисов
$offs = \bb\models\Office::getAllOffices();
//$offs = array();
//$query_offs = "SELECT * FROM offices ORDER BY number";
//$result_offs = $mysqli->query($query_offs);
//if (!$result_offs) {die('Сбой при доступе к базе данных: '.$query_offs.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//		while ($offs_r = $result_offs->fetch_assoc()) {
//			$offs[$offs_r['number']]=$offs_r['name'];
//		}

//основной запрос информации о товаре
$item_q="SELECT * FROM tovar_rent_items LEFT JOIN tovar_rent ON (tovar_rent_items.model_id = tovar_rent.tovar_rent_id) $srch ORDER BY cat_id, tovar_rent_items.producer, model";
//echo $item_q;
$result_item_def = $mysqli->query($item_q);
if (!$result_item_def) {die('Сбой при доступе к базе данных: '.$item_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

echo $result_item_def->num_rows;
//echo $item_q;

$razdels = Razdel::getAll();

?>
<ul class="nav nav-tabs">
  <?php foreach ($razdels as $r): ?>
    <li class="nav-item">
      <label for="razdel-<?= $r->getIdRazdel() ?>" class="nav-link <?= ($razdel==$r->getIdRazdel() ? 'active' : '') ?>" <?= ($razdel==$r->getIdRazdel() ? 'aria-current="page"' : '') ?>><?= $r->getNameRazdelText() ?></label>
      <input type="radio" id="razdel-<?= $r->getIdRazdel() ?>" name="razdel" value="<?= $r->getIdRazdel() ?>" class="d-none" form="cat_ch_sel">
    </li>
  <?php endforeach; ?>


</ul>
<?php

echo'<br>
<div style="width: 100px;">
<form name="cat_chose" method="post" id="cat_ch_sel" style="display:inline-block;">
<div class="cont">
					<select name="cat_id" class="form-control form-control-sm selectpicker form-control" style="width: 100px;" data-live-search="true" id="cat_select" onchange="$(\'#model_srch\').val(\'\'); $(\'#inv_srch\').val(\'\'); catSelect(); document.getElementById(\'cat_ch_sel\').submit(); return false;">
				  		<option value="">категория не выбрана</option>
						<option value="all" '.sel_d('all', $cat_id).'>все</option>';
foreach ($cats as $c) {
  echo '<option value="'.$c->getId().'" '.sel_d($c->getId(), $cat_id).' >'.good_print($c->getName()).'</option>';
}
//                        while ($cat_names = $result_cats->fetch_assoc()) {
//                            echo '<option value="'.$cat_names['tovar_rent_cat_id'].'" '.sel_d($cat_names['tovar_rent_cat_id'], $cat_id).' >'.good_print($cat_names['rent_cat_name']).'</option>';
//                        }
echo '
      </select>
      <input class="btn btn-warning btn-sm sort_btn" type="button" value="показать сортировку">
      <input class="btn btn-success btn-sm sort_btn_save hide" type="button" value="сохранить сортировку">
</div>
    </div>';

                        echo '
	<table border="1" cellspacing="0" class="krb_table">
		<tr>
			<th class="krb_cat">Категория<br />
			    <select form="cat_ch_sel" name="last_rent" id="last_rent" onchange="$(\'#inv_srch\').val(\'\'); document.getElementById(\'cat_ch_sel\').submit(); return false;" style="width:80px;">
						<option value="all" '.sel_d('all', $last_rent).'>все</option>
						<option value="3" '.sel_d('3', $last_rent).'>Последний прокат</option>
				</select>

				<input type="hidden" name="item_id" id="off_ch_item_id" value="">
				<input type="hidden" name="place_1" id="off_ch_place_1" value="">
				<input type="hidden" name="place_2" id="off_ch_place_2" value="">
				<input type="hidden" name="move" id="off_ch_move" value="">
				<input type="hidden" name="move_action" id="off_ch_move_action" value="">

				</form>
			</th>
			<th class="krb_prod">Фирма
			        <input type="text" name="producer_srch" id="producer_srch" form="cat_ch_sel" class="form-control form-control-sm" style="height: 25px;" value="'.$producer_srch.'">
			</th>
			<th class="krb_model">Название (model_id)
			    <input type="text" name="model_srch" id="model_srch" form="cat_ch_sel" class="form-control form-control-sm" style="height: 25px;" value="'.$model_srch.'">
			    </th>
			<th class="krb_color">Цвет/размер</th>
			<th style="width: 80px;">Место
					<select name="item_place" id="place_select" form="cat_ch_sel" style="display:inline-block; width:77px" onchange="$(\'#inv_srch\').val(\'\'); document.getElementById(\'cat_ch_sel\').submit(); return false;">
				  		<option value="all" '.sel_d($item_place, 'all').'>все</option>';

						foreach ($offs as $off) {
								echo '<option value="'.$off->getNumber().'" '.sel_d($item_place, $off->getNumber()).'>'.$off->getShortName().'</option>';
						}

		echo '
					</select>


				</th>
			<th class="krb_invn">Инв.№
			    <input type="submit" value="->" form="cat_ch_sel" class="btn_inv" style="display: none;"><br><input form="cat_ch_sel" type="text" name="inv_srch" id="inv_srch" value="'.$inv_srch.'" class="form-control" style="height: 25px; font-size: 14px;">

			</th>
			<th class="krb_tarif">тариф</th>
			<th class="krb_from">С <br />
					<select name="item_status" id="item_status" form="cat_ch_sel" onchange="$(\'#inv_srch\').val(\'\'); document.getElementById(\'cat_ch_sel\').submit(); return false;" style="display:inline-block; width:80px">
				  		<option value="all" '.sel_d($item_status, 'all').'>все</option>
						<option value="to_rent" '.sel_d($item_status, 'to_rent').'>свободные</option>
						<option value="rented_out" '.sel_d($item_status, 'rented_out').'>на руках</option>
						<option value="all_off" '.sel_d($item_status, 'all_off').'>на офисе</option>
						<option value="on_move" '.sel_d($item_status, 'on_move').'>в пути</option>
						<option value="on_move_tome" '.sel_d($item_status, 'on_move_tome').'>в пути на мой офис</option>
					</select>
					</th>
			<th class="krb_to">По</th>
			<th class="krb_to">URLs<br>
			    <select form="cat_ch_sel" name="qr_srch" onchange="$(\'#inv_srch\').val(\'\'); document.getElementById(\'cat_ch_sel\').submit(); return false;">
			        <option value="all" '.sel_d($qr_srch, 'all').'>все</option>
			        <option value="1" '.sel_d($qr_srch, '1').'>c QR</option>
			        <option value="0" '.sel_d($qr_srch, '0').'>без QR</option>
                </select>
			</th>
			<th class="">Действия</th>
		</tr>';

$cat_id='';
$producer='';
$model_id='';

while ($item_def=$result_item_def->fetch_assoc()) {
    $mysqli = \bb\Db::getInstance()->getConnection();

	//смотрим WEB информацию

	if ($item_def['model_id']!=$model_id) {
		$web_i_q = "SELECT * FROM rent_model_web WHERE model_id='".$item_def['model_id']."'";
		$result_web = $mysqli->query($web_i_q);
		if (!$result_web) {die('Сбой при доступе к базе данных: '.$web_i_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$model_web=$result_web->fetch_assoc();
		$web_rows=$result_web->num_rows;
	}


	//смотрим фамилию для выверки
		$cl_output='';
	if ($item_def['status']=='rented_out') {
		$deal_q = "SELECT * FROM rent_deals_act WHERE deal_id='".$item_def['active_deal_id']."'";
		$result_deal = $mysqli->query($deal_q);
		if (!$result_deal) {die('Сбой при доступе к базе данных: '.$deal_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$fio_deal=$result_deal->fetch_assoc();
		$fio_deal_num=$result_deal->num_rows;

		$cl_q = "SELECT * FROM clients WHERE client_id='".$fio_deal['client_id']."'";
		$result_cl = $mysqli->query($cl_q);
		if (!$result_cl) {die('Сбой при доступе к базе данных: '.$cl_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$cl_res=$result_cl->fetch_assoc();
		$cl_res_num=$result_cl->num_rows;
		$cl_output='<br />'.$cl_res['family'].' '.$cl_res['name'].' '.$cl_res['otch'];
	}

	$trfs=TariffModel::getTarifModelForModelId($item_def['model_id']);

//	$tarifs_q = "SELECT * FROM rent_tarif_act WHERE model_id='".$item_def['model_id']."' ORDER BY sort_num, kol_vo";
//	$result_tarifs = $mysqli->query($tarifs_q);
//	if (!$result_tarifs) {die('Сбой при доступе к базе данных: '.$tarifs_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

	if ($trfs->tarNum()>0) {
		$tarifs='<a href="#" onclick="$(\'#tar_div_'.$item_def['item_id'].'\').show();return false;">да>1</a>';
            $tarifs.='<div id="tar_div_'.$item_def['item_id'].'" class="tar_div">';
            if ($trfs) $tarifs.=$trfs->allTariffsText();
            $tarifs.='
            <input type="button" value="X" class="tar_btn" onclick="$(\'#tar_div_'.$item_def['item_id'].'\').hide();return false;" />
            </div>';


//		if ($result_tarifs->num_rows==1) {
//			$trf=$result_tarifs->fetch_assoc();
//			$tarifs=number_format($trf['rent_amount'], 0, ',', ' ').'-'.$trf['kol_vo'].step_translate($trf['step']);
//		}
	}
	else {
		$tarifs='---';
	}

	$to_stile='';

if ($cat_id!=$item_def['tovar_rent_cat_id']) {

    $mysqli = \bb\Db::getInstance()->getConnection();

	//запрос информации о категории
	$cat_q="SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id=".$item_def['tovar_rent_cat_id']." LIMIT 1";
	$result_cat_def = $mysqli->query($cat_q);
	if (!$result_cat_def) {die('Сбой при доступе к базе данных: '.$cat_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$cat_def=$result_cat_def->fetch_assoc();

	$cat_name=$cat_def['rent_cat_name'].' ('.$cat_def['tovar_rent_cat_id'].')';
}
//else {
//	$cat_name='';
//}



if ($producer!=$item_def['producer']) {
	$producer_name=$item_def['producer'];
}
else {
	$producer_name='';
}



if ($model_id!=$item_def['model_id']) {
    $mysqli = \bb\Db::getInstance()->getConnection();

	$model_q = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$item_def['model_id']."' LIMIT 0,1";
	$result_model_def = $mysqli->query($model_q);
	if (!$result_model_def) {die('Сбой при доступе к базе данных: '.$model_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$model_def=$result_model_def->fetch_assoc();

	$model_name=$model_def['model'];
}
//else {
//	$model_name='';
//}

if ($item_def['status']=='to_rent' || ($item_def['status']=='t_bron')) {    //!!! changed to show buttons for tmp bron ::if ($item_def['status']=='to_rent' || ($item_def['status']=='t_bron' && $item_def['br_time']<time())) {
	$from='свободно';
	$to='';

	$status_style='style="background-color:#D6FBC8"';


	$buttons='
		<form method="post" action="dogovor_new.php" style="display:inline-block;">
					<input type="hidden" name="item_inv_n" value="'.$item_def['item_inv_n'].'" />

					<input type="submit" value="нов.договор" />
		</form>
		<form method="post" action="rent_orders.php" style="display:inline-block;">
					<input type="hidden" name="item_inv_n" value="'.$item_def['item_inv_n'].'" />
						';
	if (!in_array($item_def['tovar_rent_cat_id'], $karnaval_models)) {
        $buttons.='<input type="submit" name="action" value="бронь" />';
    }
	$buttons.='
		</form>
					';
    if (in_array($item_def['tovar_rent_cat_id'], $karnaval_models)) {
        $buttons.=KBronForm::StartButton($item_def['item_inv_n']);
    }
}
elseif ($item_def['status']=='rented_out' || $item_def['status']=='to_deliver') {
    $mysqli = \bb\Db::getInstance()->getConnection();

	$deal_q = "SELECT * FROM rent_deals_act WHERE deal_id='".$item_def['active_deal_id']."' LIMIT 0,1";
	$result_deal_def = $mysqli->query($deal_q);
	if (!$result_deal_def) {die('Сбой при доступе к базе данных: '.$deal_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$deal_def=$result_deal_def->fetch_assoc();

	$item_def['status']=='to_deliver' ? $status_style='style="background-color:#80C4F0"' : $status_style='';

	$from=date("d-m-y", $deal_def['start_date']);
	$to=date("d-m-y", $deal_def['return_date']);

	$today_t=getdate(time());
	$today=mktime(0, 0, 0, $today_t['mon'], ($today_t['mday']), $today_t['year']);

	if ($deal_def['return_date']==$today) {
		$to_stile='style="background-color:#FF0"';
	}
	elseif ($deal_def['return_date']<$today) {
		$to_stile='style="background-color:#F00"';
	}
	else {
		$to_stile='';
	}


	$buttons='
		<form method="post" action="dogovor_new.php" style="display:inline-block;">
					<input type="hidden" name="item_inv_n" value="'.$deal_def['item_inv_n'].'" />
					<input type="hidden" name="client_id" value="'.$deal_def['client_id'].'" />
					<input type="submit" value="к договору" />
		</form>
					';

	if ($item_def['status']=='to_deliver') {
		$status_style='style="background-color:#82d9ee"';
	}

}
elseif ($item_def['status']=='bron') {
    $mysqli = \bb\Db::getInstance()->getConnection();

	$from='Бронь';
	$to='';

	$status_style='style="background-color:#f5d9f3"';

	$query_or = "SELECT * FROM rent_orders WHERE inv_n='".$item_def['item_inv_n']."' AND type2!='stirka'";
	$result_ord = $mysqli->query($query_or);
	if (!$result_ord) {die('Сбой при доступе к базе данных: '.$query_or.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$ord=$result_ord->fetch_assoc();

	if ($ord && $ord['type2']=='remont') {
		$status_style='style="background-color:#f9e874"';
		$from='В ремонте';
	}
	elseif ($ord && $ord['type2']=='deliv') {
		$status_style='style="background-color:#82d9ee"';
		$from='На доставку';
	}
	elseif ($ord && $ord['type2']=='bron') {
		$status_style='style="background-color:#f0c7f4"';
		$from='Самовывоз';
	}
	elseif ($ord && $ord['type2']=='out') {
		$status_style='style="background-color:#bfbfbf"';
		$from='На списании';
	}

	$buttons='
		<a href="/bb/rent_orders.php">Страница броней</a>
					';


}
else {
	$status_style='';

	switch ($item_def['status']) {
		case 'for_sale':
			$from='на продаже';
		break;

		case 'not_to_rent':
			$from='недоступен для сдачи';
		break;

		case 'repair':
			$from='ремонт';
		break;

		case 'cleaning':
			$from='стирка';
		break;

		case 'bron':
			$from='бронь';
		break;

		case 'on_move':
			$from='в пути на др. офис';
			$status_style='style="background-color:#ECC980"';
		break;

		case 't_bron':
				$from='бронируется в инете';
				$status_style='style="background-color:#F60"';
		break;

		default:
			$from='ХЗ-статус';
		break;
	}



	$to='';
	$buttons='';

}



	echo'
		<tr id="tr_'.$item_def['item_id'].'" '.$status_style.'>
			<td>'.$cat_name.'</td>
			<td style="overflow: hidden;">'.$producer_name.'<br /><span class="sort_n_span">['.($model_web['sort_n'] ?? '').']</span><input class="sort_n_input hide" type="number" data-inv="'.$item_def['item_inv_n'].'" data-modelid="'.$item_def['model_id'].'" value="'.($model_web['sort_n'] ?? 0).'" step="1"></td>
			<td> <div class="t_pic">
					<img class="img_size" src="'.($model_web['l2_pic'] ?? 'no-model-web').'" id="pic_'.$item_def['item_id'].'" alt="" onclick="pic_size('.$item_def['item_id'].')" />
					</div>
				 <div style="position:relative;">'.($item_def['seller']=='elizavetka.by' ? '<img style="position:absolute; top:0; right:0;" src="/bb/el.png"/>' : '').'</div>
	    		 <div id="hist_'.$item_def['item_id'].'" style="display:inline-block; position:relative;"></div><a href="#" onclick="menu_show(\''.$item_def['item_id'].'\', \''.$item_def['item_inv_n'].'\'); return false;">'.$model_name.' </a> <i>('.$item_def['model_id'].')</i>'.' <div id="hist_'.$item_def['item_id'].'" style="display:inline-block; position:relative;"></div><br />'.($_SESSION['level']>='5' ? number_format($item_def['buy_price'], 0, ',', ' ').'/'.number_format($item_def['agr_price'], 0, ',', ' ').'---'.date("d.m.Y", $item_def['buy_date']) : '').'</td>
			<td class="krb_color">'.(($model_def['color']=='0'|| $model_def['color']=='') ? '-' : $model_def['color']).($model_def['color']=='multicolor' ? ': '.$item_def['item_color'] : '').' ('.$item_def['item_size'].' / '.$item_def['item_rost1'].'-'.$item_def['item_rost2'].'см.)<br /> ['.$item_def['real_item_size'].']</td>
			<td><div id="place_value_'.$item_def['item_id'].'" style="display:inline-block; position:relative;">';

				if (($item_def['status']=='to_rent' || $item_def['status']=='t_bron' || $item_def['status']=='bron') && ($item_def['item_place']==$_SESSION['office'] || $item_def['to_move']==$_SESSION['office'])) {
							echo'	<a href="#" onclick="place_show(\''.$item_def['item_id'].'\'); return false;">Оф.'.$item_def['item_place'].($item_def['to_move']>0 ? '-->'.$item_def['to_move'] : '').'</a>';
	    	 	}
	    	 	elseif (($item_def['status']=='to_rent' || $item_def['status']=='t_bron' || $item_def['status']=='bron') && $item_def['item_place']!=$_SESSION['office']) {
	    	 		echo 'Оф.'.$item_def['item_place'].($item_def['to_move']>0 ? '-->'.$item_def['to_move'] : '');
	    	 	}
	    	 	if ($item_def['item_place']=='0') {
					echo 'Оффис определен не корректно (=о). Свяжитесь с Кристиной либо Димой.';
	    	 	}
	    	 	if ($item_def['status']=='t_bron' && $item_def['br_time']>time()) {
					echo '<h4>Внимание !!! Товар сейчас кто-то бронирует на сайте.</h4>';
	    	 	}

	 echo '<div style="display:none; position:relative;" id="div_place_'.$item_def['item_id'].'">';

	    if ($item_def['item_place']==0) {
	    	echo 'Ошибка !!! месторасположение товара не определено.';

	    }
	    elseif ($item_def['item_place']>0 && $item_def['to_move']<1) {
			echo '<ul class="i_menu">';

				foreach ($offs as $off) {
						if ($item_def['item_place']!=$key) {
							echo '<li><a href="#" onclick="move_off(\''.$item_def['item_id'].'\', \''.$item_def['item_place'].'\', \''.$off->getNumber().'\', \'move\'); return false;">-> отпр. на '.$off->getShortName().'</a></li>';
						}
				}

			echo '</ul> <input type="button" value="х" onclick="of_menu_hide('.$item_def['item_id'].');" style="position:absolute; top:5px; left:200px; z-index:3;"/>';
		}
		elseif ($item_def['item_place']>0 && $item_def['to_move']>0) {
	    	echo '<ul class="i_menu">';

	    	if ($item_def['item_place']==$_SESSION['office']) {
				echo '<li><a href="#" onclick="move_off(\''.$item_def['item_id'].'\', \''.$item_def['item_place'].'\', \''.$item_def['to_move'].'\', \'back\'); return false;">-> вернуть на '.$item_def['item_place'].'</a></li>';
			}
			elseif ($item_def['to_move']==$_SESSION['office']) {
				echo '<li><a href="#" onclick="move_off(\''.$item_def['item_id'].'\', \''.$item_def['item_place'].'\', \''.$item_def['to_move'].'\', \'acs\'); return false;">-> принять на '.$item_def['to_move'].'</a></li>';
			}

			echo '</ul> <input type="button" value="х" onclick="of_menu_hide('.$item_def['item_id'].');" style="position:absolute; top:5px; left:200px; z-index:3;"/>';

	    }

	    	$tovar=\bb\classes\tovar::getTovForQr($item_def['item_id'], $item_def['qr_yn']);


	    echo '	 			</div></div></td>
			<td style="position:relative;">'.($item_def['state']==3 ? '<img title="стыдно сдавать" style="position:absolute; right: 0px; top: 0px;" src="red_cross.png" />': '').'
			    '.inv_print($item_def['item_inv_n']).'</td>
			<td style="position: relative;">'.$tarifs.'</td>
			<td class="krb_from" id="td_from_'.$item_def['item_id'].'">'.$from.'</td>
			<td class="krb_to" '.$to_stile.'>'.$to.'</td>
			<td class="krb_to" '.($web_rows>0 ? '' : 'style="background-color:red"').'>'.($web_rows>0 ? 'WEB-ОК' : 'WEB-нет')./*$cl_output.*/'
			    <div id="qr_div_'.$tovar->item_id.'">'.$tovar->krBasaQr().'</div>
			</td>
			<td style="position: relative;">'.$buttons.'
				<form method="post" id="tovar_del_'.$item_def['item_id'].'" action="tovar_del.php" style="display:none;">
					<input type="hidden" name="model_id" value="'.$item_def['model_id'].'">
					<input type="hidden" name="item_id" value="'.$item_def['item_id'].'">
					<input type="submit" name="action" value="удалить">
				</form>

				<form method="post" id="tovar_lr_'.$item_def['item_id'].'" action="last_rent_adm.php" style="display:none;">
					<input type="hidden" name="model_id" value="'.$item_def['model_id'].'">
					<input type="hidden" name="inv_n" value="'.$item_def['item_inv_n'].'">
					<input type="submit" name="action" value="последний прокат">
				</form>

				<form method="post" id="tovar_edit_'.$item_def['item_id'].'" action="tovar_new.php" style="display:none;">
					<input type="hidden" name="item_id" value="'.$item_def['item_id'].'">
					<input type="hidden" name="action" value="редактировать">
				</form>

				<form method="post" id="model_edit_'.$item_def['item_id'].'" action="tovar_new_mod.php" style="display:none;">
					<input type="hidden" name="model_id" value="'.$item_def['model_id'].'">
					<input type="hidden" name="action" value="редактировать">
				</form>

				<form method="post" id="web_info_'.$item_def['item_id'].'" action="/bb/model_web.php" style="display:none;">
					<input type="hidden" name="model_id" value="'.$item_def['model_id'].'">
				</form>

				<form method="post" id="fav_tovar_'.$item_def['item_id'].'" action="/bb/favorite_tovars_management.php" style="display:none;">
					<input type="hidden" name="action" value="new">
					<input type="hidden" name="model_id" value="'.$item_def['model_id'].'">
				</form>

				<form method="post" id="tovar_tarif_'.$item_def['item_id'].'" action="rent_tarifs.php" style="display:none;">
					<input type="hidden" name="model_id" value="'.$item_def['model_id'].'">
					<input type="hidden" name="item_id" value="'.$item_def['item_id'].'">
					<input type="hidden" name="item_inv_n2" value="'.$item_def['item_inv_n'].'" />
				</form>
							</td>

		</tr>';

	$cat_id=$item_def['tovar_rent_cat_id'];
//	$producer=$item_def['producer'];
	$model_id=$item_def['model_id'];

	}//end of while


echo '</table>';


/*$query_item_def = "SELECT * FROM tovar_rent_items ORDER BY cat_id";
$result_item_def = mysql_query($query_item_def);
if (!$result_item_def) die("Сбой при доступе к базе данных: '$query_item_def'".mysql_error());
$item_def=mysql_fetch_array($result_item_def);*/

/*while ($item_def=$result_item_def->fetch_assoc()) {

	print_r($item_def);

}
*/

function inv_print ($inv_n) {

	$output=substr($inv_n, 0, 3).'-'.substr($inv_n, 3);

	return $output;

}


function get_post($var)
{
	GLOBAL $mysqli;
    $mysqli = \bb\Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
}


function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}

function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}


function step_translate ($step) {
	switch ($step) {
		case 'day':
			return 'день';
		break;

		case 'week':
			return 'неделя';
		break;

		case 'month':
			return 'месяц';
		break;
	}

}

?>

<script>
  //cat_ch_sel
  let sortNSpans = document.querySelectorAll('.sort_n_span');
  let sortNInputs = document.querySelectorAll('.sort_n_input');
  let sortBtnShow = document.querySelector('.sort_btn');
  let sortBtnSave = document.querySelector('.sort_btn_save');
  let form = document.querySelector('#cat_ch_sel');

  sortBtnShow.addEventListener('click',showHideToggle);
  sortBtnSave.addEventListener('click', save);

  function showHideToggle(e){
    sortBtnSave.classList.toggle('hide');
    if (e.currentTarget.value=='показать сортировку') e.currentTarget.value='отмена по сортировке';
    else e.currentTarget.value='показать сортировку';

    let done = [];
    sortNSpans.forEach(el=>{
      el.classList.toggle('hide');
    });
    sortNInputs.forEach(el=>{
      if (!done.includes(el.dataset.modelid)){
        el.classList.toggle('hide');
        done.push(el.dataset.modelid);
      }
      else {
        el.closest('tr').classList.toggle('hide');
      }
    });
  }

  function save(){
    let done = [];

    sortNInputs.forEach(el=>{
      if (!done.includes(el.dataset.modelid)) {
        let input1 = document.createElement('input');
        input1.type = 'hidden';
        input1.name = 'modelid[]';
        input1.value = el.dataset.modelid;

        let input2 = document.createElement('input');
        input2.type = 'hidden';
        input2.name = 'sortn[]';
        input2.value = el.value;

        form.appendChild(input1);
        form.appendChild(input2);

        done.push(el.dataset.modelid);
      }
    });
    let action = document.createElement('input');
      action.type="hidden";
      action.name="action";
      action.value="sortn";
      form.appendChild(action);

    form.submit();
  }
</script>
