<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Tariff.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); // включаем подключение к базе данных

echo '

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Панель администратора BB</title>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
';


//------- proverka paroley
$in_level= array(3,5,7);

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level) || \bb\models\User::getCurrentUser()->getId()==26)) {
	die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	<body>

	<form action="index.php" method="post">
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>
	</head>
');
}

//-----------proverka paroley

?>


<script language="javascript">

function calc_step(id){

	document.getElementById('rent_step_amount_'+id).value=document.getElementById('rent_amount_'+id).value/document.getElementById('kol_vo_'+id).value;

}

function calc_total(id){

	document.getElementById('rent_amount_'+id).value=document.getElementById('rent_step_amount_'+id).value*document.getElementById('kol_vo_'+id).value;

}


function cat_ch () {

	cat_id=document.getElementById('new_select').value;
	par2='cat_producer';

	if (cat_id==0) {
		document.getElementById('inv_n_cat').innerHTML='';
		document.getElementById('producer_select').innerHTML='<option value="0">----------</option>';
		document.getElementById('model_name_select').innerHTML='<option value="0">----------</option>';
		document.getElementById('color_select').innerHTML='<option value="0">----------</option>';
		document.getElementById('m_set').value='';
		document.getElementById('m_price').value='';

		return false;
	}

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cat_ch.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'cat_id=' + encodeURIComponent(cat_id) + '&par2=' + encodeURIComponent(par2);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
	     	   document.getElementById('producer_select').innerHTML=xmlhttp.responseText;
		     	  document.getElementById('cat_id_fff').value=document.getElementById('new_select').value;
			   }
	  		}
		}

	var xmlhttp2 = getXmlHttp()
	xmlhttp2.open("POST", '/bb/cat_ch.php', true)
	xmlhttp2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	par3='cat_model';
	params2 = 'cat_id=' + encodeURIComponent(cat_id) + '&par2=' + encodeURIComponent(par3);


	xmlhttp2.send(params2);

	xmlhttp2.onreadystatechange = function() {
	  if (xmlhttp2.readyState == 4) {
	     if(xmlhttp2.status == 200) {
	     	   document.getElementById('model_name_select').innerHTML=xmlhttp2.responseText;
	           }
	  		}
		}

	  document.getElementById('color_select').innerHTML='<option value="0">----------</option>';
	  document.getElementById('m_set').value='';
	  document.getElementById('m_price').value='';
	  document.getElementById('m_price_cur').value='0';

	}//end of cat_ch


function prod_ch () {

	cat_id=document.getElementById('new_select').value;
	producer=document.getElementById('producer_select').value;

	if (producer==0) {
		document.getElementById('model_name_select').innerHTML='<option value="0">----------</option>';
		return false;
	}

	par2='producer';

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cat_ch.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'cat_id=' + encodeURIComponent(cat_id) + '&par2=' + encodeURIComponent(par2) + '&producer=' + encodeURIComponent(producer);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
			document.getElementById('model_name_select').innerHTML=xmlhttp.responseText;
	           }
	  		}
		}

	  document.getElementById('color_select').innerHTML='<option value="0">----------</option>';
	  document.getElementById('m_set').value='';
	  document.getElementById('m_price').value='';
	  document.getElementById('m_price_cur').value='0';

	}//end of prod_ch


function model_ch () {

	model_name=document.getElementById('model_name_select').value;
	cat_id=document.getElementById('new_select').value;
	producer=document.getElementById('producer_select').value;

	par2='model';

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cat_ch.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'model_name=' + encodeURIComponent(model_name) + '&par2=' + encodeURIComponent(par2) + '&cat_id=' + encodeURIComponent(cat_id) + '&producer=' + encodeURIComponent(producer);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {

			  eval (xmlhttp.responseText);

			  if(model_rows==1){
				  document.getElementById('producer_select').value=model_producer;
				  document.getElementById('color_select').innerHTML=model_color;
				  document.getElementById('m_set').value=model_set;
				  document.getElementById('m_price').value=model_price;
				  document.getElementById('m_price_cur').value=model_price_cur;
				  document.getElementById('model_id').value=model_id;
				  if (model_id>0) {
					  document.getElementById('show_next').style.display="";
					  document.getElementById('new_mod_ch').style.display="";}
			  }

			  if(model_rows==2){
				  document.getElementById('color_select').innerHTML=color_names;

				  document.getElementById('m_set').value='';
				  document.getElementById('m_price').value='';
				  document.getElementById('m_price_cur').value='0';
			  }



			}
	  	}
	}

	}//end model_ch



function color_ch () {

	cat_id=document.getElementById('new_select').value;
	producer=document.getElementById('producer_select').value;
	model_name=document.getElementById('model_name_select').value;
	color_name=document.getElementById('color_select').value;

	par2='color';

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cat_ch.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'model_name=' + encodeURIComponent(model_name) + '&color_name=' + encodeURIComponent(color_name) + '&par2=' + encodeURIComponent(par2) + '&cat_id=' + encodeURIComponent(cat_id) + '&producer=' + encodeURIComponent(producer);


	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
			  eval (xmlhttp.responseText);

				  document.getElementById('m_set').value=model_set;
				  document.getElementById('m_price').value=model_price;
				  document.getElementById('m_price_cur').value=model_price_cur;
				  document.getElementById('model_id').value=model_id;
				  if (model_id!=0) {
					  document.getElementById('show_next').style.display="";
					  document.getElementById('new_mod_ch').style.display="";
					  }


			}
	  	}
	}


	}//end color_ch



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




if(document.getElementsByClassName) {

	getElementsByClass = function(classList, node) {
		return (node || document).getElementsByClassName(classList)
	}

} else {

	getElementsByClass = function(classList, node) {
		var node = node || document,
		list = node.getElementsByTagName('*'),
		length = list.length,
		classArray = classList.split(/\s+/),
		classes = classArray.length,
		result = [], i,j
		for(i = 0; i < length; i++) {
			for(j = 0; j < classes; j++)  {
				if(list[i].className.search('\\b' + classArray[j] + '\\b') != -1) {
					result.push(list[i])
					break
				}
			}
		}

		return result
	}
}


function show_t_edit (id) {
	var classid='t_edit_'+id;

	var t_list = document.getElementsByClassName(classid);

	var length = t_list.length;

	for (var i = 0; i < length; i++) {
		  t_list[i].style.display="";
		}

	document.getElementById('edit_but_'+id).style.display="";
	document.getElementById('corr_but_'+id).style.display="none";
}

function hide_t_edit (id) {
	var classid='t_edit_'+id;

	var t_list = document.getElementsByClassName(classid);

	var length = t_list.length;

	for (var i = 0; i < length; i++) {
		  t_list[i].style.display="none";
		}



	document.getElementById('edit_but_'+id).style.display="none";
	document.getElementById('corr_but_'+id).style.display="";
}


</script>



<?php

$tarif_id=''; //для подсветки откорректированного тарифа
$cat_def['tovar_rent_cat_id']='';//нулевое значение

foreach ($_POST as $key => $value) {
					$$key = get_post($key);
				}


if (isset($_POST['action'])) {


switch ($action) {

	case 'внести новый тариф':
        $mysqli = \bb\Db::getInstance()->getConnection();
	$query_new_tarif = "INSERT INTO rent_tarif_act VALUES('', '$model_id', '".strtotime($start_date)."', '$step', '$kol_vo', '$kol_vo_min', '$rent_amount', '$rent_per_step', '".sort_num($step)."', '".time()."', '".$_SESSION['user_fio']."')";
    if (!$mysqli->query($query_new_tarif)) {die('Сбой при доступе к базе данных: '.$query_new_tarif.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$tarif_id=$mysqli->insert_id;

	break;


	case 'сохранить тариф':
        $mysqli = \bb\Db::getInstance()->getConnection();

		$query_t_upd = "UPDATE rent_tarif_act SET step='$step', kol_vo='$kol_vo', kol_vo_min='$kol_vo_min', rent_amount='$rent_amount', rent_per_step='$rent_per_step', sort_num='".sort_num($step)."', start_date='".strtotime($start_date)."' WHERE tarif_id='$tarif_id'";
		   	if (!$mysqli->query($query_t_upd)) {die('Сбой при доступе к базе данных: '.$query_t_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

	break;
    case 'авто расчет':
        //$mysqli = \bb\Db::getInstance()->getConnection();
        $t_base = \bb\classes\Tariff::getById($tarif_id);
        $t3 = clone $t_base;
        $t2 = clone $t_base;
        $t1 = clone $t_base;

        $t3->tarif_id=null;
        $t3->kol_vo=3;
        $t3->rent_amount = $t_base->rent_amount * 0.9;

        $t2->tarif_id=null;
        $t2->kol_vo=2;
        $t2->rent_amount = $t3->rent_amount * 0.85;

        $t1->tarif_id=null;
        $t1->kol_vo=1;
        $t1->rent_amount = $t2->rent_amount * 0.7;

        $t1->t4AutoCalcAndFill();
        $t2->t4AutoCalcAndFill();
        $t3->t4AutoCalcAndFill();
        $t_base->t4AutoCalcAndFill();

        \bb\Db::startTransaction();
            $t1->hardSave();
            $t2->hardSave();
            $t3->hardSave();
        \bb\Db::commitTransaction();


    break;


	case 'удалить':
        $mysqli = \bb\Db::getInstance()->getConnection();
		$done="yes";

		$query_tarifs = "SELECT * FROM rent_tarif_act WHERE tarif_id='$tarif_id'";
		$result_tarifs = $mysqli->query($query_tarifs);
		if (!$result_tarifs) {die('Сбой при доступе к базе данных: '.$query_tarifs.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$trf=$result_tarifs->fetch_assoc();


		$query_start = "START TRANSACTION";
		$result_start = $mysqli->query($query_start);
		if (!$result_start) {$done="no"; die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

		$query_move = "INSERT INTO rent_tarif_prev VALUES('', '$tarif_id', '".$trf['model_id']."', '".$trf['start_date']."', '".$trf['step']."', '".$trf['kol_vo']."', '".$trf['kol_vo_min']."', '".$trf['rent_amount']."', '".$trf['rent_per_step']."', '".$trf['sort_num']."', '".time()."', '".$_SESSION['user_fio']."')";
		$result_move = $mysqli->query($query_move);
		if (!$result_move) {$done="no"; die('Сбой при доступе к базе данных: '.$query_move.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

		$query_del = "DELETE FROM rent_tarif_act WHERE tarif_id='$tarif_id'";
		$result_del = $mysqli->query($query_del);
		if (!$result_del) {$done="no"; die('Сбой при доступе к базе данных: '.$query_del.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

		if ($done=='yes') {
			$query_fin = "COMMIT";
			$result_fin = $mysqli->query($query_fin);
			if (!$result_fin) die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		}
		else {
			$query_fin = "ROLLBACK'";
			$result_fin = $mysqli->query($query_fin);
			if (!$result_fin) die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		}



	break;


}//end of switch
}//end of if



if (isset($model_id) && $model_id!=0) {
    $mysqli = \bb\Db::getInstance()->getConnection();

	$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$model_id."'";
	$result_model_def = $mysqli->query($query_model_def);
	if (!$result_model_def) die('Сбой при доступе к базе данных: '.$query_model_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
	$model_def=$result_model_def->fetch_assoc();


	$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$model_def['tovar_rent_cat_id']."'";
	$result_cat = $mysqli->query($query_cat);
	if (!$result_cat) die('Сбой при доступе к базе данных: '.$query_cat.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
	$cat_def=$result_cat->fetch_assoc();

	$query_tarifs = "SELECT * FROM rent_tarif_act WHERE model_id='".$model_def['tovar_rent_id']."' ORDER BY sort_num, kol_vo";
	$result_tarifs = $mysqli->query($query_tarifs);
	if (!$result_tarifs) die('Сбой при доступе к базе данных: '.$query_tarifs.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
	$tarif_rows = $result_tarifs->num_rows;

}






//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//		foreach ($_POST as $key => $value) {
//			echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//		}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$mysqli = \bb\Db::getInstance()->getConnection();
		//chose tovar cathegory
		$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
		$result_cats = $mysqli->query($query_cats);
		if (!$result_cats) die('Сбой при доступе к базе данных: '.$query_cats.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

echo '
</head>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" id="new_mod_ch" href="/bb/rent_tarifs.php" '.(isset($model_id) ? '' : 'style="display:none"').'>Выбрать другую модель</a>
	<a class="div_item" href="/bb/tovar_rent_all.php" onclick="document.getElementById(\'cat_ch_sel\').submit(); return false;">Просмотр всех товаров (этой категории)</a>

<form name="cat_chose" action="'.($_SESSION['level']==3 ? 'tovar_rent_all.php' : 'kr_baza_new.php').'" method="post" id="cat_ch_sel">
<input type="hidden" name="cat_id" id="cat_id_fff" value="'.$cat_def['tovar_rent_cat_id'].'" />
</form>

</div>


';

if (!isset($model_id) || $model_id=='') {

echo '
<form name="model" action="rent_tarifs.php" method="post">

<div id="old_model_div" class="old_div">
<table border="1" cellspacing="0">
	<tr>
		<td>Категория товара:</td>
		<td>
					<select name="cat_select_old" id="new_select" onchange="cat_ch();">
			  		<option value="0">выберите категорию</option>
			    ';
			while ($cat_names = $result_cats->fetch_assoc()) {
				echo '<option value="'.$cat_names['tovar_rent_cat_id'].'" >'.good_print($cat_names['rent_cat_name']).'</option>';
			}

		echo '
			    </select>

		</td>
	</tr>
	<tr>
		<td>Фирма:</td>
		<td>
			<select name="producer_select_old" id="producer_select" onchange="prod_ch();">
    			<option value="0">----------</option>
    		</select>
		</td>
	</tr>

	<tr>
		<td>Модель:</td>
		<td>
			<select name="model_select_old" id="model_name_select" onchange="model_ch();">
	    		<option value="0">------------</option>
	    	</select>
		</td>
	</tr>

	<tr>
		<td>Цвет:</td>
		<td>
			<select name="color_select_old" id="color_select" onchange="color_ch();">
    			<option value="0">------------</option>
    		</select>
    		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    		<i>id модели=<input type="text" readonly="readonly" name="model_id" id="model_id" value="" size="5"></i>
		</td>
	</tr>

	<tr>
		<td>Комплектация модели (стандарт):</td>
		<td><input type="text" name="set_old" size="70" id="m_set" readonly="readonly"/></td>
	</tr>

	<tr>
		<td>Оценочная стоимость:</td>
		<td>
			<input type="text" name="price_old" size="70" id="m_price" readonly="readonly"/>
			<select name="price_cur_old" id="m_price_cur" readonly="readonly">
		    	<option value="USD">USD</option>
		    	<option value="EUR">EUR</option>
		    	<option value="TBYR">тыс.бел.руб.</option>
		    </select>
		</td>
	</tr>

</table>
</div>

<br><input type="submit" id="show_next" value="приступить к работе с тарифами" style="display:none" />

</form>
';
}

if (isset($model_id) && $model_id>0) {

echo '
	<table border="1" cellspacing="0">
	<tr>
		<td>Категория товара:</td>
		<td>'.$cat_def['rent_cat_name'].'</td>
	</tr>
	<tr>
		<td>Фирма:</td>
		<td>'.$model_def['producer'].'</td>
	</tr>

	<tr>
		<td>Модель:</td>
		<td>'.$model_def['model'].' <i> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;id модели='.$model_def['tovar_rent_id'].'</i></td>
	</tr>

	<tr>
		<td>Цвет:</td>
		<td>'.$model_def['color'].'</td>
	</tr>

	<tr>
		<td>Комплектация модели (стандарт):</td>
		<td>'.$model_def['set'].'</td>
	</tr>

	<tr>
		<td>Оценочная стоимость:</td>
		<td>'.good_print($model_def['agr_price']).' '.$model_def['agr_price_cur'].'</td>
	</tr>

</table>';

if ($tarif_rows>0) {

	echo'
<table border="1" cellspacing="0">

	<tr>
		<td>id тарифа</td>
		<td>начало действия</td>
		<td>шаг</td>
		<td>кол-во шагов</td>
		<td>мин. кол-во</td>
		<td>стоимость за период</td>
		<td>стоимость за шаг</td>
		<td>действия</td>
	</tr>';

while ($tarif_def=$result_tarifs->fetch_assoc()) { //печатаем тарифы

	echo '
	<form name="tarif" action="rent_tarifs.php" method="post">
	<tr '.($tarif_id==$tarif_def['tarif_id'] ? 'style="background-color:#FF0"' : '').'>
		<td>'.$tarif_def['tarif_id'].'
				<input type="hidden" name="tarif_id" value="'.$tarif_def['tarif_id'].'" />
				<input type="hidden" name="model_id" value="'.$model_id.'" /> </td>
		<td>'.date("d.m.Y", $tarif_def['start_date']).'<span class="t_edit_'.$tarif_def['tarif_id'].'" style="display:none"><br /><input type="date" name="start_date" value="'.date("Y-m-d", $tarif_def['start_date']).'" size="5" /></span></td>
		<td>'.r_step($tarif_def['step']).'<span class="t_edit_'.$tarif_def['tarif_id'].'" style="display:none"><br />
				<select name="step" id="step">
	  				<option value="day" '.sel_d($tarif_def['step'], 'day').'>день</option>
	   				<option value="week" '.sel_d($tarif_def['step'], 'week').'>неделя</option>
	        		<option value="month" '.sel_d($tarif_def['step'], 'month').'>месяц</option>
	    		</select>
			</span></td>
		<td>'.$tarif_def['kol_vo'].'<span class="t_edit_'.$tarif_def['tarif_id'].'" style="display:none"><br /><input type="text" name="kol_vo" id="kol_vo_'.$tarif_def['tarif_id'].'" value="'.$tarif_def['kol_vo'].'" size="5" /></span></td>
		<td>'.$tarif_def['kol_vo_min'].'<span class="t_edit_'.$tarif_def['tarif_id'].'" style="display:none"><br /><input type="text" name="kol_vo_min" value="'.$tarif_def['kol_vo_min'].'" size="5" /></span></td>
		<td>'.$tarif_def['rent_amount'].'<span class="t_edit_'.$tarif_def['tarif_id'].'" style="display:none"><br /><input type="text" name="rent_amount" id="rent_amount_'.$tarif_def['tarif_id'].'" value="'.$tarif_def['rent_amount'].'" size="5" /><input type="button" value="=" onclick="calc_step(\''.$tarif_def['tarif_id'].'\'); return false;" /></span></td>
		<td>'.$tarif_def['rent_per_step'].'<span class="t_edit_'.$tarif_def['tarif_id'].'" style="display:none"><br /><input type="text" name="rent_per_step" id="rent_step_amount_'.$tarif_def['tarif_id'].'" value="'.$tarif_def['rent_per_step'].'" size="5" /><input type="button" value="=" onclick="calc_total(\''.$tarif_def['tarif_id'].'\'); return false;" /></span></td>
		<td><input type="submit" name="action" value="удалить" /><input type="button" id="corr_but_'.$tarif_def['tarif_id'].'" value="корректировать" onclick="show_t_edit(\''.$tarif_def['tarif_id'].'\'); return false;" /><span id="edit_but_'.$tarif_def['tarif_id'].'" style="display:none"><br /><input type="submit" name="action" value="сохранить тариф" /><input type="button" value="отмена" onclick="hide_t_edit(\''.$tarif_def['tarif_id'].'\'); return false;" /></span>'.(($tarif_def['step']=='week' && $tarif_def['kol_vo']==4) ? '<input style="background-color: lightgreen" type="submit" name="action" value="авто расчет" title="(0.5 round): t3=t4*0,9  t2=t3*0.85  t1=t2*0.7" >' : '').'</td>

	</tr>
	</form>';
}

echo'
</table>';
	}
else {
	echo '<br /><strong>Для данной модели не введен ни один тариф. Пожалуйста, введите новые тарифы.</strong> <br /><br />';
}

echo '
<input type="button" value="добавить новый тариф" onclick="document.getElementById(\'tarif_new_form\').style.display=\'\';" />

<div id="tarif_new_form" style="display:none">
<form action="rent_tarifs.php" method="post">

<table border="1" cellspacing="0">

<tr>
	<th>начало действия</th>
	<th>шаг</th>
	<th>кол-во шагов</th>
	<th>мин. кол-во</th>
	<th>стоимость период</th>
	<th>стоимость за шаг</th>
	<th>действия</th>
</tr>

<tr>
	<td><input type="date" name="start_date" value="'.date("Y-m-d", time()).'" />
		<input type="hidden" name="model_id" value="'.$model_id.'" /></td>
		</td>

	<td>
		<select name="step" id="step">
	  		<option value="week">неделя</option>
			<option value="day">день</option>
	   		<option value="month">месяц</option>
	    </select>
	</td>

	<td><input type="text" name="kol_vo" id="kol_vo_0" value="" size="2" /></td>
	<td><input type="text" name="kol_vo_min" value="" size="1" /></td>
	<td><input type="text" name="rent_amount" id="rent_amount_0" value="" size="5" />тыс.<input type="button" value="=" id="copy" onclick="calc_step(\'0\'); return false;" /></td>
	<td><input type="text" name="rent_per_step" id="rent_step_amount_0" value="" size="5" />тыс.<input type="button" value="=" onclick="calc_total(\'0\'); return false;" /></td>
	<td><input type="submit" name="action" value="внести новый тариф" /></td>
</tr>

</table>

</form>

<input type="button" value="отмена" onclick="document.getElementById(\'tarif_new_form\').style.display=\'none\';" />
</div>
';

if (isset($item_id)) {

	echo '<br /><br />
	<form method="post" id="tovar_edit" action="tovar_new.php">
		<input type="hidden" name="item_id" value="'.$item_id.'">
		<input type="submit" name="action" value="редактировать"> (товар инв.№'.$item_inv_n2.')
	</form>';

}


}//end of main if (output if model_id is set













echo '</body></html>';


function get_post($var)
{
    $mysqli = \bb\Db::getInstance()->getConnection();

	return $mysqli->real_escape_string($_POST[$var]);
}

function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}

function r_step ($step) {
	if ($step=='day') {return 'день';}
	if ($step=='week') {return 'неделя';}
	if ($step=='month') {return 'месяц';}
}

function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}

function sort_num ($step) {
	switch ($step) {
		case 'day':
			return '1';
		break;

		case 'week':
			return '7';
		break;

		case 'month':
			return '30';
		break;

		case 'year':
			return '365';
		break;

		default:
			return '0';
		break;
	}

}

?>
