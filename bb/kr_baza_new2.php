<?php
namespace bb;
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php'); // включаем класс

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/KBronForm.php');


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

	<form action="/bb/index.php" method="post">
			Офис:<select name="of_select" id="of_select">
				<option value="0">не выбран</option>
				<option value="1">Машерова</option>
				<option value="2">Ложинская</option>
			</select><br />
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>');
}

//-----------proverka paroley

//Проверка входящей информации
//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//foreach ($_POST as $key => $value) {
//	echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";



$cat_id='';
$item_status='all';
$item_place='all';
$elza='all';
$el_srch='';
$karnaval_models = array(2,61);

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}


if (isset($move_action)) {
	switch ($move_action) {
		case 'move':
			$tov=new tovar();
			$tov->item_id_load($item_id);
			if ($tov->item_place!=$place_1 || $tov->to_move>0) {
				die ('Кто-то уже изменил расположение товара. Попробуйте заново.');
			}
			else {
				$tov->to_move=$place_2;
				$tov->item_update();
				echo 'Товар успешно отправлен на '.$tov->to_move.' офис';	
			}
			
		break;
		case 'back':
			$tov=new tovar();
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
			$tov=new tovar();
			$tov->item_id_load($item_id);
			if ($tov->item_place!=$place_1 && $tov->to_move!=$tov->to_move) {
				die ('Кто-то уже изменил расположение товара. Попробуйте заново.');
			}
			else {
				$tov->to_move='0';
				$tov->item_place=$place_2;
				$tov->item_update();
				echo 'Товар успешно принят на '.$tov->item_place.' офис';
			}
		break;
		
	}
}


if ($item_place!='all') {
	$place_add=" AND item_place='$item_place'";
}
else {
	$place_add='';
}

if ($cat_id!='all' && $item_status=='all') {
	$srch=" WHERE cat_id='$cat_id'$place_add";
}
elseif ($cat_id=='all' && $item_status!='all') {
	
	if ($item_status=='to_rent') {
		$srch=" WHERE (`status`='$item_status' OR (`status`='t_bron' AND br_time<".time()."))$place_add";
	}
	elseif ($item_status=='all_off') {//товары на офисе
		
		//есть дубликат этого кода ниже
		$cur_invs='';
		
		//выбираем сделки на доставку 
		$query_cur = "SELECT * FROM rent_sub_deals_act WHERE `status`='for_cur' AND `type`='first_rent'";
		$result_cur = $mysqli->query($query_cur);
		if (!$result_cur) {die('Сбой при доступе к базе данных: '.$query_cur.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

			$cur_num=$result_cur->num_rows;
			$cur_act_num=0;
			while ($cur_sub_dls = $result_cur->fetch_assoc()) {
				//находим сделку и пишем инвентарные номера
				$query_dl_cur = "SELECT * FROM rent_deals_act WHERE deal_id='".$cur_sub_dls['deal_id']."'";
				$result_dl_cur = $mysqli->query($query_dl_cur);
				if (!$result_dl_cur) {die('Сбой при доступе к базе данных: '.$query_dl_cur.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				$cur_dls = $result_dl_cur->fetch_assoc();				
								
				$cur_act_num++;
				if ($cur_act_num==$cur_num) {
					$cur_invs.=$cur_dls['item_inv_n'];
				}
				else {
					$cur_invs.=$cur_dls['item_inv_n'].', ';
				}
			}
			if ($cur_num<1) {
				$cur_invs='0';
			}
		
				
		$srch=" WHERE (`status`!='rented_out' OR (item_inv_n IN ($cur_invs)$place_add))$place_add";
	}	
	else{
		$srch=" WHERE `status`='$item_status'$place_add";
	}
}
elseif ($cat_id!='all' && $item_status!='all') {
	
	if ($item_status=='to_rent') {
		$srch=" WHERE cat_id='$cat_id' AND (`status`='$item_status' OR (`status`='t_bron' AND br_time<".time()."))$place_add";
	}
	elseif ($item_status=='all_off') {
		//это дубль кода. оригинал выше
	$cur_invs='';
		
		//выбираем сделки на доставку 
		$query_cur = "SELECT * FROM rent_sub_deals_act WHERE `status`='for_cur' AND `type`='first_rent'";
		$result_cur = $mysqli->query($query_cur);
		if (!$result_cur) {die('Сбой при доступе к базе данных: '.$query_cur.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

			$cur_num=$result_cur->num_rows;
			$cur_act_num=0;
			while ($cur_sub_dls = $result_cur->fetch_assoc()) {
				//находим сделку и пишем инвентарные номера
				$query_dl_cur = "SELECT * FROM rent_deals_act WHERE deal_id='".$cur_sub_dls['deal_id']."'";
				$result_dl_cur = $mysqli->query($query_dl_cur);
				if (!$result_dl_cur) {die('Сбой при доступе к базе данных: '.$query_dl_cur.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				$cur_dls = $result_dl_cur->fetch_assoc();				
								
				$cur_act_num++;
				if ($cur_act_num==$cur_num) {
					$cur_invs.=$cur_dls['item_inv_n'];
				}
				else {
					$cur_invs.=$cur_dls['item_inv_n'].', ';
				}
			}
			if ($cur_num<1) {
				$cur_invs='0';
			}
		
		
		
		
		
		$srch=" WHERE cat_id='$cat_id' AND (`status`!='rented_out' OR (item_inv_n IN ($cur_invs)$place_add))$place_add";
	}
	else {
		$srch=" WHERE cat_id='$cat_id' AND `status`='$item_status'$place_add";
	}
}
else {
	if ($item_place=='all') {
		$srch="";
	}
	else {
	$srch=" WHERE item_place='$item_place'";
	}
}

if ($elza=='elz') {

	if ($srch=='') {
		$el_srch=" WHERE `seller`='elizavetka.by'";
	}
	else {
		$el_srch=" AND `seller`='elizavetka.by'";
	}
}
	

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<style>

</style>		
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
';
?>	
		
<!--Увеличение картинок-->
<link rel="stylesheet" type="text/css" href="/fancybox/jquery.fancybox.css">
<script type="text/javascript" src="/fancybox/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/fancybox/jquery.easing.1.3.js"></script>
<script type="text/javascript" src="/fancybox/jquery.fancybox-1.2.1.pack.js"></script>
<script type="text/javascript">
$(document).ready(function() { 
$("a.first").fancybox(); 
$("a.two").fancybox(); 
$("a.video").fancybox({"frameWidth":600,"frameHeight":400}); 
$("a.content").fancybox({"frameWidth":680,"frameHeight":300}); 
});
</script>

<script type="text/JavaScript" src="/js/jquery.slider.js"></script>



<?php

KBronForm::RequiredEcho();

echo'		
</head>
<title>База товаров.</title>
<body>
		
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: '.$_SESSION['office'].'</form> </div>
<div id="zv_div"></div>
		
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/rda.php">Все сделки (быстрые)</a>
	<a class="div_item" href="/bb/rent_deals_all.php">Все сделки (старые)</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
	<a class="div_item" href="/bb/rent_orders.php">Брони</a> <br />
		<form method="post" action="/bb/kr_baza_new2.php" style="display:inline-block;">
			<input type="hidden" name="cat_id" value="2" /><input type="submit" value="КАРНАВАЛЫ" style="width:100px; height:35px; background-color:green; color:white" />
		</form>
</div>
';
require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам

?>



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

	document.getElementById('hist_'+item_id).innerHTML='<ul class="i_menu"> <li><a href="#" onclick="hist_show(\'tov_hist\', \''+item_id+'\', \''+inv_n+'\'); return false;">История</a></li> <?php echo '<li><a href="#" onclick="document.getElementById(\\\'web_info_\'+item_id+\'\\\').submit(); return false;">WEB info</a></li>'; echo $_SESSION['level']>=5 ? ' <li><a href="#" onclick="document.getElementById(\\\'tovar_tarif_\'+item_id+\'\\\').submit(); return false;">Тарифы</a></li>   <li><a href="#" onclick="document.getElementById(\\\'tovar_edit_\'+item_id+\'\\\').submit(); return false;">Редактировать товар</a></li>   <li><a href="#" onclick="document.getElementById(\\\'model_edit_\'+item_id+\'\\\').submit(); return false;">Редактировать модель</a></li>   <li><a href="#" onclick="document.getElementById(\\\'tovar_del_\'+item_id+\'\\\').submit(); return false;">Удаление</a></li>' : ''; ?> </ul>  	<input type="button" value="х" onclick="document.getElementById(\'hist_'+item_id+'\').innerHTML=\'\'; return false;" style="position:absolute; top:5px; left:160px; z-index:3;"/>';
	
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

</script>




<?php

//для списка категорий
$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
$result_cats = $mysqli->query($query_cats);
if (!$result_cats) {die('Сбой при доступе к базе данных: '.$query_cats.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

//для списка офисов
$offs = array();
$query_offs = "SELECT * FROM offices ORDER BY number";
$result_offs = $mysqli->query($query_offs);
if (!$result_offs) {die('Сбой при доступе к базе данных: '.$query_offs.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		while ($offs_r = $result_offs->fetch_assoc()) {
			$offs[$offs_r['number']]=$offs_r['name'];
		}

//основной запрос информации о товаре
$item_q="SELECT * FROM tovar_rent_items LEFT JOIN tovar_rent ON (tovar_rent_items.model_id = tovar_rent.tovar_rent_id)$srch$el_srch ORDER BY cat_id, tovar_rent_items.producer, model";
$result_item_def = $mysqli->query($item_q);
if (!$result_item_def) {die('Сбой при доступе к базе данных: '.$item_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

echo $result_item_def->num_rows;
//echo $item_q;

echo'
	<table border="1" cellspacing="0" class="krb_table">
		<tr>
			<th class="krb_cat">Категория<br />
				<form name="cat_chose" action="kr_baza_new2.php" method="post" id="cat_ch_sel" style="display:inline-block;">
					<select name="cat_id" id="cat_select" onchange="document.getElementById(\'cat_ch_sel\').submit(); return false;">
				  		<option value="">не выбрано</option>
						<option value="all" '.sel_d('all', $cat_id).'>все</option>';
						while ($cat_names = $result_cats->fetch_assoc()) {
							echo '<option value="'.$cat_names['tovar_rent_cat_id'].'" '.sel_d($cat_names['tovar_rent_cat_id'], $cat_id).' >'.good_print($cat_names['rent_cat_name']).'</option>';
						}		
			echo '</select>
				
				<input type="hidden" name="item_id" id="off_ch_item_id" value="">
				<input type="hidden" name="place_1" id="off_ch_place_1" value="">
				<input type="hidden" name="place_2" id="off_ch_place_2" value="">
				<input type="hidden" name="move" id="off_ch_move" value="">
				<input type="hidden" name="move_action" id="off_ch_move_action" value="">
				
				</form>
			</th>
			<th class="krb_prod">Фирма
					<select form="cat_ch_sel" name="elza" id="elza" onchange="document.getElementById(\'cat_ch_sel\').submit(); return false;" style="width:80px;">
						<option value="all" '.sel_d('all', $elza).'>все</option>
						<option value="elz" '.sel_d('elz', $elza).'>Елизаветка</option>
					</select>
			</th>
			<th class="krb_model">Название (model_id)</th>
			<th class="krb_color">Цвет/размер</th>
			<th>Место
					<select name="item_place" id="place_select" form="cat_ch_sel" style="display:inline-block; width:90px" onchange="document.getElementById(\'cat_ch_sel\').submit(); return false;">
				  		<option value="all" '.sel_d($item_place, 'all').'>все</option>';
						
						foreach ($offs as $key => $value) {
								echo '<option value="'.$key.'" '.sel_d($item_place, $key).'>'.$value.'</option>';
						}
		
		echo ' 
					</select>
			
			
				</th>
			<th class="krb_invn">Инв.№</th>
			<th class="krb_tarif">тариф</th>    
			<th class="krb_from">С <br />
					<select name="item_status" id="item_status" form="cat_ch_sel" onchange="document.getElementById(\'cat_ch_sel\').submit(); return false;" style="display:inline-block; width:80px">
				  		<option value="all" '.sel_d($item_status, 'all').'>все</option>
						<option value="to_rent" '.sel_d($item_status, 'to_rent').'>свободные</option>
						<option value="all_off" '.sel_d($item_status, 'all_off').'>на офисе</option>
						<option value="on_move" '.sel_d($item_status, 'on_move').'>в пути</option>
					</select>
					</th>    
			<th class="krb_to">По</th>
			<th class="krb_to">URLs</th>  
			<th class="">Действия</th>  
		</tr>';

$cat_id='';
$producer='';
$model_id='';

while ($item_def=$result_item_def->fetch_assoc()) {
	
	
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
	
	
	$tarifs_q = "SELECT * FROM rent_tarif_act WHERE model_id='".$item_def['model_id']."' ORDER BY sort_num, kol_vo";
	$result_tarifs = $mysqli->query($tarifs_q);
	if (!$result_tarifs) {die('Сбой при доступе к базе данных: '.$tarifs_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	
	if ($result_tarifs->num_rows>0) {
		$tarifs='да>1';
		if ($result_tarifs->num_rows==1) {
			$trf=$result_tarifs->fetch_assoc();
			$tarifs=number_format($trf['rent_amount'], 0, ',', ' ').'-'.$trf['kol_vo'].step_translate($trf['step']);
		}
	}
	else {
		$tarifs='-';
	}

	$to_stile='';
	
if ($cat_id!=$item_def['cat_id']) {
	
	//запрос информации о категории
	$cat_q="SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id=".$item_def['cat_id']." LIMIT 1";
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
	$model_q = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$item_def['model_id']."' LIMIT 0,1";
	$result_model_def = $mysqli->query($model_q);
	if (!$result_model_def) {die('Сбой при доступе к базе данных: '.$model_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$model_def=$result_model_def->fetch_assoc();

	$model_name=$model_def['model'];
}
//else {
//	$model_name='';
//}

if ($item_def['status']=='to_rent' || ($item_def['status']=='t_bron' && $item_def['br_time']<time())) {
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
	if (!in_array($item_def['cat_id'], $karnaval_models)) {
        $buttons.='<input type="submit" name="action" value="бронь" />';
    }
	$buttons.='
		</form>
					';
    if (in_array($item_def['cat_id'], $karnaval_models)) {
        $buttons.=KBronForm::StartButton($item_def['item_inv_n']);
    }
}
elseif ($item_def['status']=='rented_out' || $item_def['status']=='to_deliver') {
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
	$from='Бронь';
	$to='';

	$status_style='style="background-color:#f5d9f3"';
	
	$query_or = "SELECT * FROM rent_orders WHERE inv_n='".$item_def['item_inv_n']."' AND type2!='stirka'";
	$result_ord = $mysqli->query($query_or);
	if (!$result_ord) {die('Сбой при доступе к базе данных: '.$query_or.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$ord=$result_ord->fetch_assoc();
	
	if ($ord['type2']=='remont') {
		$status_style='style="background-color:#f9e874"';
		$from='В ремонте';
	}
	elseif ($ord['type2']=='deliv') {
		$status_style='style="background-color:#82d9ee"';
		$from='На доставку';
	}
	elseif ($ord['type2']=='bron') {
		$status_style='style="background-color:#f0c7f4"';
		$from='Самовывоз';
	}
	elseif ($ord['type2']=='out') {
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
			<td class="krb_cat">'.$cat_name.'</td>
			<td>'.$producer_name.'<br />['.$model_web['sort_n'].']</td>
			<td> <div class="t_pic">
					<a class="two" rel="group" title="" href="'.$model_web['l2_pic'].'"><img class="img_size" src="'.$model_web['l2_pic'].'" alt="" /></a>
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
	
				foreach ($offs as $key => $value) {
						if ($item_def['item_place']!=$key) {
							echo '<li><a href="#" onclick="move_off(\''.$item_def['item_id'].'\', \''.$item_def['item_place'].'\', \''.$key.'\', \'move\'); return false;">-> отпр. на '.$value.'</a></li>';
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
	    	 	
	    	 	
	    	 	
	    	 	
	    echo '	 			</div></div></td>
			<td style="position:relative;">'.($item_def['state']==3 ? '<img title="стыдно сдавать" style="position:absolute; right: 0px; top: 0px;" src="red_cross.png" />': '').'
			    '.inv_print($item_def['item_inv_n']).'</td>
			<td>'.$tarifs.'</td>
			<td class="krb_from" id="td_from_'.$item_def['item_id'].'">'.$from.'</td>
			<td class="krb_to" '.$to_stile.'>'.$to.'</td>
			<td class="krb_to" '.($web_rows>0 ? '' : 'style="background-color:red"').'>'.($web_rows>0 ? 'WEB-ОК' : 'WEB-нет')./*$cl_output.*/'</td>
			<td style="position: relative;">'.$buttons.'
				<form method="post" id="tovar_del_'.$item_def['item_id'].'" action="tovar_del.php" style="display:none;">
					<input type="hidden" name="model_id" value="'.$item_def['model_id'].'">
					<input type="hidden" name="item_id" value="'.$item_def['item_id'].'">
					<input type="submit" name="action" value="удалить">
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
		
				<form method="post" id="tovar_tarif_'.$item_def['item_id'].'" action="rent_tarifs.php" style="display:none;">
					<input type="hidden" name="model_id" value="'.$item_def['model_id'].'">
					<input type="hidden" name="item_id" value="'.$item_def['item_id'].'">
					<input type="hidden" name="item_inv_n2" value="'.$item_def['item_inv_n'].'" />
				</form>
							</td>
		
		</tr>';

	$cat_id=$item_def['cat_id'];
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