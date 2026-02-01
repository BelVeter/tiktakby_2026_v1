<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
set_time_limit(30);
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


echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<style>

</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<title>Брони.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/rent_zayavk.php">Заявки</a>
</div><br />
';
require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам


?>

<script language="javascript">

function show_edit(id) {

	if (document.getElementById('edit_show_'+id).value=="подтвердить") {
		
	document.getElementById('edit_show_'+id).value="отмена";
	document.getElementById('save_podtv_'+id).style.display="inline-block";
	document.getElementById('info_div_'+id).style.display="inline-block";
	document.getElementById('br_valid_'+id).style.display="inline-block";
	}
	else {
	document.getElementById('edit_show_'+id).value="подтвердить";
	document.getElementById('save_podtv_'+id).style.display="none";
	document.getElementById('info_div_'+id).style.display="none";
	document.getElementById('br_valid_'+id).style.display="none";
	}
}



function rem_t_ch (id) {
	document.getElementById('info_div_'+id).style.display="inline-block";
	document.getElementById('rem_t_but_'+id).style.display="none";
	document.getElementById('cans_t_but_'+id).style.display="inline-block";
	document.getElementById('save_t_but_'+id).style.display="inline-block";
}

function cans_t (id) {
	document.getElementById('info_div_'+id).style.display="none";
	document.getElementById('rem_t_but_'+id).style.display="inline-block";
	document.getElementById('cans_t_but_'+id).style.display="none";
	document.getElementById('save_t_but_'+id).style.display="none";
}

function save_t (id) {
	document.getElementById('save_t_but_'+id).value="сохранить изменения";
	document.getElementById('save_t_but_'+id).style.display="none";
	document.getElementById('cans_t_but_'+id).style.display="none";
	return true;
	
}

function br_del_ch (id) {
	document.getElementById('info_div_'+id).style.display="inline-block";
	document.getElementById('br_del_span_'+id).style.display="inline";
	document.getElementById('rem_t_but_'+id).style.display="none";
	document.getElementById('cans_t_but_'+id).style.display="inline-block";
	document.getElementById('save_t_but_'+id).style.display="inline-block";
}

function cans_val (id) {
	document.getElementById('br_valid_'+id).style.display="none";
	document.getElementById('cans_val_but_'+id).style.display="none";
	document.getElementById('save_val_but_'+id).style.display="none";
}

function save_val (id) {
	document.getElementById('save_val_but_'+id).value="сохранить изменения";
	document.getElementById('save_val_but_'+id).style.display="none";
	document.getElementById('cans_val_but_'+id).style.display="none";
	return true;
	
}

function pl_show (id) {
	document.getElementById('place_status_'+id).style.display="inline-block";
	document.getElementById('cans_t_but_pl_'+id).style.display="inline-block";
	document.getElementById('save_t_but_pl_'+id).style.display="inline-block";
	document.getElementById('ch_a_pl_'+id).style.display="none";
}

function cans_t_pl (id) {
	document.getElementById('place_status_'+id).style.display="none";
	document.getElementById('cans_t_but_pl_'+id).style.display="none";
	document.getElementById('save_t_but_pl_'+id).style.display="none";
	document.getElementById('ch_a_pl_'+id).style.display="block";
}

function save_t_pl (id) {
	document.getElementById('save_t_but_pl_'+id).value="сохранить изменения";
	document.getElementById('save_t_but_pl_'+id).style.display="none";
	document.getElementById('cans_t_but_pl_'+id).style.display="none";
	return true;
	
}

function val_show (id) {
	document.getElementById('br_valid_'+id).style.display="inline-block";
	document.getElementById('cans_val_but_'+id).style.display="inline-block";
	document.getElementById('save_val_but_'+id).style.display="inline-block";
	document.getElementById('ch_a_pl_'+id).style.display="none";
}

function resp_show (id) {
	document.getElementById('rem_resp_'+id).style.display="inline-block";
	document.getElementById('cans_resp_but_'+id).style.display="inline-block";
	document.getElementById('save_resp_but_'+id).style.display="inline-block";
	document.getElementById('ch_a_pl_'+id).style.display="none";
}

function cans_resp (id) {
	document.getElementById('rem_resp_'+id).style.display="none";
	document.getElementById('cans_resp_but_'+id).style.display="none";
	document.getElementById('save_resp_but_'+id).style.display="none";
}

function save_resp (id) {
	document.getElementById('save_resp_but_'+id).value="сохранить изменения";
	document.getElementById('save_resp_but_'+id).style.display="none";
	document.getElementById('cans_resp_but_'+id).style.display="none";
	return true;
	
}


function br_ch_ch () {
	if (document.getElementById('br_2_t').value=="remont") {
		document.getElementById('place_status_new').style.display="inline-block";
		document.getElementById('rem_type_new').style.display="inline-block";
		document.getElementById('rem_resp_new').style.display="inline-block";
	}
	else {
		document.getElementById('place_status_new').style.display="none";
		document.getElementById('rem_type_new').style.display="none";
		document.getElementById('rem_resp_new').style.display="none";
	}
	
}

function new_order_f() {

	if (document.getElementById('new_form_row').style.display=="") {
		document.getElementById('new_form_row').style.display="none";
		document.getElementById('new_order_but').value="новая бронь";		
	}
	else {
		document.getElementById('new_form_row').style.display="";
		document.getElementById('new_order_but').value="отмена";
	}
	
}


function new_br() {
	if (document.getElementById('br_2_t').value=="") {
		alert ('Введите тип брони!');
		return false;
	}
	else {
		return true;
	}
}


function br_br(type2) {
	document.getElementById('filter_type2').value=type2;
	document.getElementById('br_filter').submit();
	
}



function reload() {location = '/bb/index.php'}; 



</script>

<?php 

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$type1='strong'; // потом убрать
$type2='bron';	
$action='';
$vidan=0;//показывает кнопку изменения
$alert='';
$item_inv_n='';
$to_sam=0;
$office=$_SESSION['office'];
$place_status='';
$rem_type='';
$rem_resp='';


foreach ($_POST as $key => $value) {
		$$key = get_post($key);
	}
	
// создаем перечень пользователей
	$rd_lp = "SELECT * FROM logpass";
	$result_lp = $mysqli->query($rd_lp);
	if (!$result_lp) {die('Сбой при доступе к базе данных: '.$rd_lp.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	
	$lp_list[0]='';
	while ($lp_l=$result_lp->fetch_assoc()) {
		$lp_list[$lp_l['logpass_id']]=$lp_l['lp_fio'];
	}

// создаем перечень офисов
	$rd_of = "SELECT * FROM offices";
	$result_of = $mysqli->query($rd_of);
	if (!$result_of) {die('Сбой при доступе к базе данных: '.$rd_of.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	
	$off_pic[0]='';
	while ($t_of=$result_of->fetch_assoc()) {
		$off_pic[$t_of['number']]=$t_of['pic_addr'];
	}
	
	
if (isset($_POST['action'])) {
	
	switch ($action) {
	
		case 'сохранить':
			$bron = new bron();
			
			$bron->inv_n=$inv_n;
			$bron->type2=$br_2_t;
			$bron->info=$info;
			$bron->order_date=strtotime(date("Y-m-d"));//сегодня
			$bron->validity=strtotime($br_valid);
			if ($br_2_t=='remont') {
				$bron->place_status=$place_status;
				$bron->rem_type=$rem_type;
				$bron->appr_id=$rem_resp;
			}
			
			$bron->bron_insert();
			
			if ($bron->failure==1) {
				echo 'Ошибка !!!'.$bron->alert;
			}
			else {
				echo 'Бронь внесена успешно.';
			}
			
		break;
		
		case 'недозвон':
			$br_upd= new bron();
			$br_upd->br_load($order_id);
			$br_upd->info.='<br />'.date("d.m.y - H:i", time()).' н/д - '.$lp_list[$br_upd->user_id];
			$br_upd->update();
			unset($br_upd);
			
			break;
			
		case 'Отправить на выбытие':
			$br_upd= new bron();
			$br_upd->br_load($order_id);
			if ($br_upd->ch_time!=$last_ch_time) {die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');}
			$br_upd->ch_who_id=$_SESSION['user_id'];
			$br_upd->ch_time=time();
			
			$br_upd->type2='out';
			$br_upd->info.='<br />'.date("d.m.y - H:i", time()).' отправлено на выбытие - '.$lp_list[$br_upd->user_id];
			
			$br_upd->update();
			unset($br_upd);
			
			break;
			
		case 'Исполнено':
			$br_upd= new bron();
			$br_upd->br_load($order_id);
			if ($br_upd->ch_time!=$last_ch_time) {die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');}
			$br_upd->item_load();
			
			if ($br_upd->item_status=='bron') {
				//меняем статус на свободно
				$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent' WHERE item_inv_n='$br_upd->inv_n'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			}
			else {
				echo ('Статус товара не равен bron!');
			}
			
			$br_upd->arch_copy();
			$br_upd->del_br();
			
			break;
		
		case 'сохранить изменения':	
			$br_upd= new bron();
			$br_upd->br_load($order_id);
			if ($br_upd->ch_time!=$last_ch_time) {die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');}
			
			$br_upd->place_status=$place_status;
			$br_upd->info=$info;
			$br_upd->rem_type=$rem_type;
			$br_upd->validity=strtotime($br_valid);
			
			if ($br_upd->type2=='remont') {
				$br_upd->appr_id=$rem_resp;
			}
			
			
			$br_upd->ch_who_id=$_SESSION['user_id'];
			$br_upd->ch_time=time();
			
			if ($br_upd->type2=='bron' || $br_upd->type2=='deliv') {
				if ($radio_deliv=='bron') {
					$br_upd->type2='bron';
				}
				else {
					$br_upd->type2='deliv';
				}
			}
			
			$br_upd->update();
			unset($br_upd);
			
			break;
			
		case 'подтвердить':
			$br_upd= new bron();
			$br_upd->br_load($order_id);
			if ($br_upd->ch_time!=$last_ch_time) {die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');}
			
			$br_upd->ch_who_id=$_SESSION['user_id'];
			$br_upd->ch_time=time();
				
				//при подтверждении 1-й раз - проставляется отметка подверждающего
				if ($br_upd->appr_id<1) {
					$br_upd->appr_id=$_SESSION['user_id'];
					$br_upd->appr_time=time();
				}
			
			
			$br_upd->update();
			unset($br_upd);
			
			break;
			
		case 'изменить статус':
				
			
			break;
		
		case 'удалить':
			//!!! внимание таблицы не блокируются - иначе все виснит. нужно разобраться с блокировками
			
			
			$br = new bron();
			$br->br_load($order_id);
			
			//выбираем инфо по конкретному товару (inv_n);
			$q_tov = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$br->inv_n'";
			$result_tov = $mysqli->query($q_tov);
			if (!$result_tov) {die('Сбой при доступе к базе данных: '.$q_tov.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			$i_tov = $result_tov->fetch_assoc();
			if ($result_tov->num_rows!==1) {
				die('при проверке товара по инв. номеру: либо товар отсутствует, либо кол-во товаров больше 1');
			}
			else {
				if ($i_tov['status']=='bron') {//если бронь - меняем на свободно
					$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent' WHERE item_inv_n='$br->inv_n'";
					$result_upd = $mysqli->query($query_upd);
					if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				}
			}
			
			//копирование брони в архив
			$br->arch_copy();
			
			//удаление брони
			$br->del_br();
			
			//разблокируем таблицы
			$query = "UNLOCK TABLES";
			$result = $mysqli->query($query);
			if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}			
		break;
	}
}

$type2s=$type2;
if ($type2=='bron' || $type2=='deliv') {
	$type2s="bron', 'deliv";
}
//основной запрос	
$query_or = "SELECT * FROM rent_orders WHERE type2 IN ('$type2s') ORDER BY type2 DESC, cr_time";
$result_or = $mysqli->query($query_or);
if (!$result_or) {die('Сбой при доступе к базе данных: '.$query_or.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
$type2_num=$result_or->num_rows;

//для расчета необработанных
	$query_or_new = "SELECT * FROM rent_orders LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n WHERE rent_orders.type2='bron' AND rent_orders.appr_id<1 AND tovar_rent_items.item_place='$office'";
		if ($office=='all') {
			$query_or_new = "SELECT * FROM rent_orders WHERE type2='bron' AND appr_id<1";
		}
	$result_or_new = $mysqli->query($query_or_new);
	if (!$result_or_new) {die('Сбой при доступе к базе данных: '.$query_or_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	$br_new=$result_or_new->num_rows;

	
	$query_or_new = "SELECT * FROM rent_orders LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n WHERE rent_orders.type2='deliv' AND rent_orders.appr_id<1 AND tovar_rent_items.item_place='$office'";
	if ($office=='all') {
		$query_or_new = "SELECT * FROM rent_orders WHERE type2='deliv' AND appr_id<1";
	}
	$result_or_new = $mysqli->query($query_or_new);
	if (!$result_or_new) {die('Сбой при доступе к базе данных: '.$query_or_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	$deliv_new=$result_or_new->num_rows;
	
		
	$query_or_new = "SELECT * FROM rent_orders LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n WHERE rent_orders.type2='remont' AND tovar_rent_items.item_place='$office'";
	if ($office=='all') {
		$query_or_new = "SELECT * FROM rent_orders WHERE type2='remont'";
	}
	$result_or_new = $mysqli->query($query_or_new);
	if (!$result_or_new) {die('Сбой при доступе к базе данных: '.$query_or_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	$remont_new=$result_or_new->num_rows;
	
	$query_or_new = "SELECT * FROM rent_orders LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n WHERE rent_orders.type2='out' AND tovar_rent_items.item_place='$office'";
	if ($office=='all') {
		$query_or_new = "SELECT * FROM rent_orders WHERE type2='out'";
	}
	$result_or_new = $mysqli->query($query_or_new);
	if (!$result_or_new) {die('Сбой при доступе к базе данных: '.$query_or_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	$out_new=$result_or_new->num_rows;

//перечень инв. номеров для новой формы
$query_free = "SELECT item_inv_n FROM tovar_rent_items WHERE `status`='to_rent' OR (`status`='t_bron' AND br_time<'".time()."')";
$result_free = $mysqli->query($query_free);
if (!$result_free) {die('Сбой при доступе к базе данных: '.$query_free.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

$free_ns='';
while ($free_inv = $result_free->fetch_assoc()) {
	$free_ns.='<option value="'.$free_inv['item_inv_n'].'" '.sel_d($item_inv_n, $free_inv['item_inv_n']).'>'.$free_inv['item_inv_n'].'</option>';
}

//фильтры по офисам

$off_filter='<select name="office" id="office_select" form="br_filter" onchange="document.getElementById(\'br_filter\').submit();" style="width:80px;">
				<option value="all" '.sel_d($office, 'all').'>все офисы</option>
				';

$q_of = "SELECT * FROM offices WHERE `active`='1'";
$result_of = $mysqli->query($q_of);
if (!$result_of) {die('Сбой при доступе к базе данных: '.$q_of.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

while ($offs = $result_of->fetch_assoc()) {
	$off_filter.='<option value="'.$offs['number'].'" '.sel_d($office, $offs['number']).'>'.$offs['name'].'</option>';
}

$off_filter.='</select>';



echo '
<!--Форма для работы ссылок -->
<form name="br_filter" id="br_filter" method="post" action="rent_orders.php" >
		<input type="hidden" name="type2" id="filter_type2" value="'.$type2.'" />
</form>
					
					
<a href="#" class="'.(($type2=='bron' || $type2=='deliv') ? 'br_br_act' : 'br_br').'" onclick="br_br(\'bron\'); return false;">Бронь <sup style="color:red;">+'.$br_new.'</sup></a>
<a href="#" class="'.($type2=='remont' ? 'br_zayavk_act' : 'br_zayavk').'" onclick="br_br(\'remont\'); return false;">Ремонт<sup style="color:red;">'.$remont_new.'</sup></a>
<a href="#" class="'.($type2=='out' ? 'br_zayavk_act' : 'br_zayavk').'" onclick="br_br(\'out\'); return false;" >Выбытие<sup style="color:red;">'.$out_new.'</sup></a>
<table border="1" cellspacing="0">
  <tr>
      <th style="width:81px; text-align:center;">Дата/№</th>
	  <th style="width:350px; text-align:center;">Товар'.$off_filter.'</th>
      <th style="width:350px; text-align:center;">коментарий</th>
	  <th style="width:81px; text-align:center;">';
		switch ($type2) {
			case 'remont':
				echo 'срок исполнения';
			break;
			
			default:
				echo 'дата действия';
			break;
		}
		
		echo '</th>
      <th style="width:90px; text-align:center;">созд/подтв</th>
      <th style="text-align:center;"><div style="position:relative"><input type="button" style="position:absolute; top:-65px; left:100px; height:50px; width:100px;" value="'.($action=='бронь' ? 'отмена' : 'новая бронь').'" id="new_order_but" onclick="new_order_f(); return false;"></div>
		действия</th>
  </tr>
			

<tbody '.($action=='бронь' ? '' : 'style="display:none;"').' id="new_form_row">
	<tr>
      <td>сейчас</td>
      <td><select name="inv_n" id="inv_n_new" form="new_order"><option value="">не указан</option>'.$free_ns.'</select><br />
      	Тип брони:<select name="br_2_t" id="br_2_t" form="new_order" onchange="br_ch_ch();">
      			<option value="">не указан</option>
      			<option value="bron">самовывоз</option>
				<option value="deliv">доставка</option>
				<option value="remont">ремонт</option>
				<option value="out">выбытие</option>
      		</select><br />
			<select name="place_status" form="new_order" id="place_status_new" style="display:none;">
				<option value="off">на офисе</option>
				<option value="rem">у ремонтера</option>
			</select>
      	</td>
	  <td><textarea rows="3" cols="32" name="info" id="info_new" form="new_order"></textarea></td>
	  <td><select name="rem_type" form="new_order" id="rem_type_new" style="width:80px; display:none;">
				<option value="">выберите тип ремонта</option>
				<option value="stir">сложная стирка</option>
				<option value="meh">механ/электр</option>
				<option value="tex">текстиль</option>
				<option value="oth">иное</option>
		 </select>
			<div style="position:relative; z-index:2; background-color:#FFF;"><input type="date" name="br_valid" id="br_valid_new" form="new_order" value="'.date("Y-m-d", time()).'"></div></td>
      <td> <select style="width:80px; display:none;" name="rem_resp" form="new_order" id="rem_resp_new">
				'.user_select('0').'
			</select>
			<input type="hidden" name="user_id" id="user_id_new" value="'.$_SESSION['user_id'].'" form="new_order">'.$_SESSION['user_fio'].'</td>
      <td><form name="new_order" id="new_order" action="rent_orders.php" method="post">
			<input type="submit" name="action" value="сохранить" onclick="return new_br();">
		</form>
		</td>
    </tr>
	</tbody>';

//для того, чтобы как хочет Кристина при переходе на нов. бронь не показывалось ничего более.
		if ($action=='бронь') {
			die('</table>');
		}

if ($type2=='remont') {
	$show1='style="display:none;"';
	$show2='';
	$rem_show='';
}
else {
	$show1='';
	$show2='style="display:none;"';
	$rem_show='display:none;';
}

		
while ($ord = $result_or->fetch_assoc()) {
	$br_line = new bron();
	$br_line->br_line($ord);
	
	if ($office!='all' && $office!=$br_line->item_place) {//если не нужно показывать все, либо если не совпадает с фильтром по офисам, то пропускаем печать данных
		continue;
	}
	

	echo '
	<tr>
		<td>'.date("d.m.y", $br_line->order_date).'<br /><i>('.date("H:i", $br_line->cr_time).')</i><br /> №'.$br_line->order_id.' </td>
		<td>'.$br_line->cat_dog_name.' '.$br_line->producer.': '.$br_line->model.'. Цвет: "'.$br_line->br_color.'"<br /><strong>'.$br_line->inv_n.'</strong> 
				'.($br_line->type2=='deliv' ? 'ДОСТАВКА' : '').'
				<input type="button" id="cans_t_but_pl_'.$br_line->order_id.'" style="background-image:url(/bb/cans.png); width:33px; height:33px; float:right; display:none;" value="" onclick="cans_t_pl(\''.$br_line->order_id.'\');" />
				<input type="submit" name="action" id="save_t_but_pl_'.$br_line->order_id.'" style="background-image:url(/bb/save.png); width:33px; height:33px; float:right; display:none;" value="" form="order_'.$br_line->order_id.'" onclick="return save_t_pl(\''.$br_line->order_id.'\');" />
			';
		if ($br_line->type2=='remont') {
			echo '<a href="#" onclick="pl_show('.$br_line->order_id.'); return false;" id="ch_a_pl_'.$br_line->order_id.'"><img style="width:25px; height:25px; float:right;" src="'.$off_pic[$br_line->item_place].'"/></a>';
		}
		else {
			echo '<img style="width:25px; height:25px; float:right;" src="'.$off_pic[$br_line->item_place].'"/>';
		}
	
	
	
	echo '
			<select name="place_status" form="order_'.$br_line->order_id.'" id="place_status_'.$br_line->order_id.'" style="display:none;">
				<option value="off" '.sel_d('off', $br_line->place_status).'>на офисе</option>
				<option value="rem" '.sel_d('rem', $br_line->place_status).'>у ремонтера</option>
			</select>
			</td>
		<td '.((($br_line->type2=='bron' || $br_line->type2=='deliv') && $br_line->appr_id>0) ? 'style="background-color:#acf398;"' : '').'>'.$br_line->info.' <br />
			<div style="display:none;" id="info_div_'.$br_line->order_id.'">
				<textarea rows="3" cols="32" name="info" id="info_'.$br_line->order_id.'" form="order_'.$br_line->order_id.'">'.good_print($br_line->info).'</textarea><br />
				<span id="br_del_span_'.$br_line->order_id.'" '.(($br_line->type2!='deliv' && $br_line->type2!='bron') ? 'style="display:none;"' : '').'>
					<label for="radio_deliv_s_'.$br_line->order_id.'"><img src="/bb/sam_vivoz.png" /></label><input type="radio" name="radio_deliv" value="bron" '.($br_line->type2=='bron' ? 'checked="checked"' : '').' id="radio_deliv_s_'.$br_line->order_id.'" form="order_'.$br_line->order_id.'" style="width:25px; height:25px;" />
					<input type="radio" name="radio_deliv" value="deliv" '.($br_line->type2=='deliv' ? 'checked="checked"' : '').' id="radio_deliv_d_'.$br_line->order_id.'" form="order_'.$br_line->order_id.'" style="width:25px; height:25px;" /><label for="radio_deliv_d_'.$br_line->order_id.'"><img src="/bb/deliv.jpg" /></label></span>
				<select '.$show2.' name="rem_type" form="order_'.$br_line->order_id.'" id="rem_type_'.$br_line->order_id.'">
					<option value="" '.sel_d('', $br_line->rem_type).'>не определено</option>
					<option value="stir" '.sel_d('stir', $br_line->rem_type).'>сложная стирка</option>
					<option value="meh" '.sel_d('meh', $br_line->rem_type).'>механ/электр</option>
					<option value="tex" '.sel_d('tex', $br_line->rem_type).'>текстиль</option>
					<option value="oth" '.sel_d('oth', $br_line->rem_type).'>иное</option>
				</select>
		</div>
		
		<input type="button" id="cans_t_but_'.$br_line->order_id.'" style="background-image:url(/bb/cans.png); width:33px; height:33px; float:right; display:none;" value="" onclick="cans_t(\''.$br_line->order_id.'\');" />
		<input type="submit" name="action" id="save_t_but_'.$br_line->order_id.'" style="background-image:url(/bb/save.png); width:33px; height:33px; float:right; display:none;" value="" form="order_'.$br_line->order_id.'" onclick="return save_t(\''.$br_line->order_id.'\');" />
      	';
	if ($br_line->type2=='bron' || $br_line->type2=='deliv') {
		echo '<input type="button" id="rem_t_but_'.$br_line->order_id.'" style="background-image:url('.($br_line->type2=='bron' ? '/bb/sam_vivoz.png' : '/bb/deliv.jpg').'); width:33px; height:33px; float:right;" value="" onclick="br_del_ch(\''.$br_line->order_id.'\');" />';
	}
	else {
		echo '<input type="button" id="rem_t_but_'.$br_line->order_id.'" style="background-image:url('.$br_line->rem_pic_url.'); width:33px; height:33px; float:right;" value="" onclick="rem_t_ch(\''.$br_line->order_id.'\');" />';	
	}
	
	echo '	</td>
		<td>
		
		<input type="button" id="cans_val_but_'.$br_line->order_id.'" style="background-image:url(/bb/cans.png); width:33px; height:33px; float:right; display:none;" value="" onclick="cans_val(\''.$br_line->order_id.'\');" />
		<input type="submit" name="action" id="save_val_but_'.$br_line->order_id.'" style="background-image:url(/bb/save.png); width:33px; height:33px; float:right; display:none;" value="" form="order_'.$br_line->order_id.'" onclick="return save_val(\''.$br_line->order_id.'\');" />
		<a href="#" onclick="val_show('.$br_line->order_id.'); return false;" id="val_show'.$br_line->order_id.'">
		'.date("d.m.y", $br_line->validity).'</a>
      		<div style="position:relative; z-index:2; background-color:#FFF; clear:both;"><input style="display:none;" type="date" name="br_valid" id="br_valid_'.$br_line->order_id.'" form="order_'.$br_line->order_id.'" value="'.date("Y-m-d", $br_line->validity).'"></div>
      		</td>
    	<td>
	  		<select style="width:80px; display:none;'.$rem_show.'" name="rem_resp" form="order_'.$br_line->order_id.'" id="rem_resp_'.$br_line->order_id.'">
				'.user_select($br_line->appr_id).'
			</select><br />
					
							';
					if ($br_line->type2=='remont') {
						
						echo '<a href="#" onclick="resp_show('.$br_line->order_id.'); return false;" id="rest_show_'.$br_line->order_id.'">'.($br_line->appr_id>0 ? $lp_list[$br_line->appr_id] : '---').'</a>';
					}
					else {
						echo ($br_line->web==1 ? 'сайт' : $lp_list[$br_line->cr_who_id]).'/'.$lp_list[$br_line->appr_id];
					}		
		echo '
			<input type="button" id="cans_resp_but_'.$br_line->order_id.'" style="background-image:url(/bb/cans.png); width:33px; height:33px; float:right; display:none;" value="" onclick="cans_resp(\''.$br_line->order_id.'\');" />
			<input type="submit" name="action" id="save_resp_but_'.$br_line->order_id.'" style="background-image:url(/bb/save.png); width:33px; height:33px; float:right; display:none;" value="" form="order_'.$br_line->order_id.'" onclick="return save_resp(\''.$br_line->order_id.'\');" />			
						
						
						</td>
		<td> 
			<form name="order_'.$br_line->order_id.'" id="order_'.$br_line->order_id.'" action="rent_orders.php" method="post" '.($br_line->type2=='out' ? 'style="display:none;"' : '').'>
			<div '.$show1.'> 	
				<input type="hidden" name="user_id" id="user_id_'.$br_line->order_id.'" value="'.$_SESSION['user_id'].'">
				<input type="hidden" name="order_id" id="order_id_'.$br_line->order_id.'" value="'.$br_line->order_id.'">
				<input type="hidden" name="type2" id="type2_'.$br_line->order_id.'" value="'.$br_line->type2.'">
      			<input type="hidden" name="last_ch_time" value="'.$br_line->ch_time.'">
				<input type="hidden" name="office" value="'.$office.'" />
      			
    	  		'.($br_line->appr_id>0 ? '' : '<input type="submit"  name="action" id="edit_show_'.$br_line->order_id.'" value="подтвердить">').'
      			<input type="submit" style="display:none;" name="action" id="save_podtv_'.$br_line->order_id.'" value="сохранить подтверждение">
				<input type="submit" style="background-color:#ed8886; float:right;" name="action" id="del_but_'.$br_line->order_id.'" id="action_'.$br_line->order_id.'" onclick="return confirm(\'Вы точно хотите удалить эту бронь?\');" value="удалить"><br />	
      	  		<input type="submit" style="background-color:#e4ccf2" name="action" id="obnov_'.$br_line->order_id.'" value="недозвон" onclick="return obnov(\''.$br_line->order_id.'\');">
			</div>	
			
			<div '.$show2.'>
	  			<input type="submit" name="action" value="Исполнено" style="background-color:#0F3;" />
      			<input type="submit" name="action" value="Отправить на выбытие" style="background-color:#F63;" /><br />
				
	  		</div>
			
			</form>
		
			<div '.$show1.'>
      			<form method="post" action="dogovor_new.php" style="display:inline-block; '.($br_line->type2=='out' ? ' display:none;' : '').'"><input type="hidden" name="item_inv_n" value="'.$br_line->inv_n.'" /><input type="submit" style="background-color:#acf398;" id="new_dog_but_'.$br_line->order_id.'" value="нов.договор" /></form>
			</div>
		</td>
	</tr>
		
		
		
			';
	unset($br_line);
}


	
	
	
	

function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}


function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}

function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}


function user_select ($id) {
	return '
		<option value="">не определен</option>
      		<option '.sel_d('2', $id).' value="2">Кристина</option>
			<option '.sel_d('5', $id).' value="5">Аня</option>
			<option '.sel_d('4', $id).' value="4">Андрей</option>
			<option '.sel_d('9', $id).' value="9">Света</option>
			<option '.sel_d('12', $id).' value="12">Алексей</option>
			<option '.sel_d('13', $id).' value="13">Таня</option>
			<option '.sel_d('16', $id).' value="16">Люовь Алексеевна</option>
			<option '.sel_d('18', $id).' value="18">Марго</option>		
			
				';
}



class bron {
	
public $mysqli;
private $db_hostname;
private $db_username;
private $db_password;
private $db_database;

public $user_id;

public $insert_id;

public $order_id;
public $type;
public $order_date; 
public $from;
public $to;
public $validity;
public $inv_n;
public $model_id;
public $cat_id;
public $type2;
public $client_id;
public $info;
public $web;
public $cr_time;
public $cr_who_id;
public $ch_time;
public $ch_who_id;
public $status;
public $appr_id;
public $appr_time;
public $cr_ip;
public $place_status;
public $rem_type;
public $rem_pic_url;

public $item_status;
public $item_place;
public $item_color;
public $cat_dog_name;
public $model;
public $mod_color;
public $producer;

public $br_color;

public $strong_t2_array = array ("bron", "deliv", "remont", "out");
public $failure = 0;
public $alert = '';


function __construct() {//передаем строчку (массив) из mysql запроса

	//require_once ($_SERVER['DOCUMENT_ROOT'].'/dimanay2.php'); // подключаем базу данных
	$this->db_hostname = '127.0.0.1';
	$this->db_database = 'tiktakby_tiktak';
	$this->db_username = 'tiktakby_tiktak';
	$this->db_password = 'Vai7evahch';
	
	//подключаемся к mysqlсерверу
	$this->mysqli = new mysqli($this->db_hostname, $this->db_username, $this->db_password, $this->db_database);
	if ($this->mysqli->connect_error) {
		die('Ошибка соединения с MYSQL сервером: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	}
	
	// выбор правильной кодировки при работе с БД
	$this->mysqli->query('set character_set_client="utf8"'); // в какой кодировке получать данные от клиента
	$this->mysqli->query('set character_set_results="utf8"'); // в какой кодировке получать данные от БД для вывода клиенту
	$this->mysqli->query('set collation_connection="utf8_general_ci"'); // кодировка в которой будут посылаться служебные команды для сервера
		
	global $_SESSION;
	$this->user_id=$_SESSION['user_id'];
	
}// end of construct

function br_line ($ord_line) {//заполнение при передачи брони из MySQL
	
		$this->order_id=$ord_line['order_id'];
		$this->type=$ord_line['type'];
		$this->order_date=$ord_line['order_date'];
		$this->from=$ord_line['from'];
		$this->to=$ord_line['to'];
		$this->validity=$ord_line['validity'];
		$this->inv_n=$ord_line['inv_n'];
		$this->model_id=$ord_line['model_id'];
		$this->cat_id=$ord_line['cat_id'];
		$this->type2=$ord_line['type2'];
		$this->client_id=$ord_line['client_id'];
		$this->info=$ord_line['info'];
		$this->web=$ord_line['web'];
		$this->cr_time=$ord_line['cr_time'];
		$this->cr_who_id=$ord_line['cr_who_id'];
		$this->ch_time=$ord_line['ch_time'];
		$this->ch_who_id=$ord_line['ch_who_id'];
		$this->status=$ord_line['status'];
		$this->appr_id=$ord_line['appr_id'];
		$this->appr_time=$ord_line['appr_time'];
		$this->cr_ip=$ord_line['cr_ip'];
		$this->rem_type=$ord_line['rem_type'];
		$this->place_status=$ord_line['place_status'];
		
		$this->item_load();
		$this->rem_pics();
	
	
		
		
}

function item_load() {
	
	if ($this->inv_n>0) {
	
		$query_ch = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$this->inv_n'";
		$result_ch = $this->mysqli->query($query_ch);
		if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
		$it_ch = $result_ch->fetch_assoc();
		$it_num = $result_ch->num_rows;
	
		if ($it_num>0) {
			$this->model_id=$it_ch['model_id'];
			$this->item_status=$it_ch['status'];
			$this->item_place=$it_ch['item_place'];
			$this->item_color=$it_ch['item_color'];
			$this->br_color=$it_ch['item_color'];
		}
	
	}
	if ($this->model_id>0) {
	
		$model_q = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$this->model_id'";
		$result_model_def = $this->mysqli->query($model_q);
		if (!$result_model_def) {die('Сбой при доступе к базе данных: '.$model_q.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
		$model_def=$result_model_def->fetch_assoc();
		$mod_num = $result_model_def->num_rows;
	
		if ($mod_num>0) {
			$this->cat_id=$model_def['tovar_rent_cat_id'];
			$this->model=$model_def['model'];
			$this->producer=$model_def['producer'];
			$this->mod_color=$model_def['color'];
			if ($this->mod_color!='multicolor' || $this->inv_n<1) {// чтобы и в заявках отражались цвета
				$this->br_color=$this->mod_color;
			}
		}
		else {//если не нашло модели, значит ее кто-то удалил
			$this->failure=1;
			$this->alert.='Для order_id:'.$this->order_id.'ни одной модели не обнаружено';
		}
	
		if ($this->cat_id>0) {
				
			$cat_q="SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='$this->cat_id' LIMIT 1";
			$result_cat_def = $this->mysqli->query($cat_q);
			if (!$result_cat_def) {die('Сбой при доступе к базе данных3: '.$cat_q.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
			$cat_def = $result_cat_def->fetch_assoc();
			$cat_num = $result_cat_def->num_rows;
				
			if ($cat_num>0) {
				$this->cat_dog_name=$cat_def['dog_name'];
			}
			else {//если не нашло катеории, значит ее кто-то удалил
				$this->failure=1;
				$this->alert.='Для order_id:'.$this->order_id.'ни одной категории не обнаружено';
			}
				
		}
	
	
	}
}//end of item_load


function lock_orders () {
	//блокируем таблицы
	$query = "LOCK TABLES tovar_rent_items WRITE, rent_orders WRITE";
	$result = $this->mysqli->query($query);
	if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
}

function unlock_orders() {
	//разблокируем таблицы
	$query = "UNLOCK TABLES";
	$result = $this->mysqli->query($query);
	if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
}

function bron_insert() {
	
	$this->cr_time=time();
	$this->cr_who_id=$this->user_id;
	
	if (in_array($this->type2, $this->strong_t2_array)) {
		$this->type='strong';
	}
	else {
		$this->type='zayavka';
	}
	
	if ($this->inv_n>0) {
		
		//блокируем таблицы
		$query = "LOCK TABLES tovar_rent_items WRITE, rent_orders WRITE";		
		$result = $this->mysqli->query($query);
		if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
		
		//выбираем инфо по конкретному товару (inv_n);
		
		$q_tov = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$this->inv_n'";
		$result_tov = $this->mysqli->query($q_tov);
		if (!$result_tov) {die('Сбой при доступе к базе данных: '.$q_tov.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
		$i_tov = $result_tov->fetch_assoc();
			if ($result_tov->num_rows!==1) {
				$this->failure=1;
				$this->alert.='при проверке товара по инв. номеру: либо товар отсутствует, либо кол-во товаров больше 1';
			}
			else {
				$this->model_id=$i_tov['model_id'];
				$this->cat_id=$i_tov['cat_id'];
				
				if ($i_tov['status']=='rented_out' || $i_tov['status']=='to_deliver' || $i_tov['status']=='bron') {
					$this->failure=1;
					$this->alert.='Товар уже выдан!';;
				}
				else {
					//ставим бронь на товар
					$query_upd = "UPDATE tovar_rent_items SET `status`='bron' WHERE item_inv_n='$this->inv_n'";
					$result_upd = $this->mysqli->query($query_upd);
					if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
				
					//вносим бронь
						// для учета того, кто внес бронь
						if ($this->type2=='bron' || $this->type2=='deiv') {
							$this->appr_id=$this->user_id;
							$this->appr_time=time();
						}
						
							
					$this->insert();
					
					
					//разблокируем таблицы
					$query = "UNLOCK TABLES";
					$result = $this->mysqli->query($query);
					if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
					
				}
			}
		
		}//end of inv_n if
		else {
			$this->failure=1;
			$this->alert.='Не передан инвентарный номер!';
		}

		//разблокируем таблицы
		$query = "UNLOCK TABLES";
		$result = $this->mysqli->query($query);
		if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
		
	}//end of function bron_insert

	
function insert() {
	$query = "INSERT INTO rent_orders VALUES ('', '$this->type', '$this->order_date', '$this->from', '$this->to', '$this->validity', '$this->inv_n', '$this->model_id', '$this->cat_id', '$this->type2', '$this->client_id', '$this->info', '$this->web', '$this->cr_time', '$this->cr_who_id', '$this->ch_time', '$this->ch_who_id', '$this->status', '$this->appr_id', '$this->appr_time', '$this->cr_ip', '$this->place_status', '$this->rem_type')";
	$result = $this->mysqli->query($query);
	if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
	
	$this->insert_id=$this->mysqli->insert_id;
}

function update() {

	$query_upd = "UPDATE rent_orders SET `type`='$this->type', `order_date`='$this->order_date', `from`='$this->from', `to`='$this->to', `validity`='$this->validity', `inv_n`='$this->inv_n', `model_id`='$this->model_id', `cat_id`='$this->cat_id', `type2`='$this->type2', `client_id`='$this->client_id', `info`='$this->info', `web`='$this->web', `cr_time`='$this->cr_time', `cr_who_id`='$this->cr_who_id', `ch_time`='$this->ch_time', `ch_who_id`='$this->ch_who_id', `status`='$this->status', `appr_id`='$this->appr_id', `appr_time`='$this->appr_time', `cr_ip`='$this->cr_ip', place_status='$this->place_status', rem_type='$this->rem_type' WHERE `order_id`='$this->order_id'";
	$result_upd = $this->mysqli->query($query_upd);
	if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}

}

function br_load($id) {

	$query_or = "SELECT * FROM rent_orders WHERE order_id='$id'";
	$result_or = $this->mysqli->query($query_or);
	if (!$result_or) {die('Сбой при доступе к базе данных: '.$query_or.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
	$r_num = $result_or->num_rows;
		if ($r_num<1) {
			die('Не найдено ни одной брони с id:'.$id);
		}
	
	$ord_line = $result_or->fetch_assoc();

	$this->order_id=$id;
	$this->type=$ord_line['type'];
	$this->order_date=$ord_line['order_date'];
	$this->from=$ord_line['from'];
	$this->to=$ord_line['to'];
	$this->validity=$ord_line['validity'];
	$this->inv_n=$ord_line['inv_n'];
	$this->model_id=$ord_line['model_id'];
	$this->cat_id=$ord_line['cat_id'];
	$this->type2=$ord_line['type2'];
	$this->client_id=$ord_line['client_id'];
	$this->info=$ord_line['info'];
	$this->web=$ord_line['web'];
	$this->cr_time=$ord_line['cr_time'];
	$this->cr_who_id=$ord_line['cr_who_id'];
	$this->ch_time=$ord_line['ch_time'];
	$this->ch_who_id=$ord_line['ch_who_id'];
	$this->status=$ord_line['status'];
	$this->appr_id=$ord_line['appr_id'];
	$this->appr_time=$ord_line['appr_time'];
	$this->cr_ip=$ord_line['cr_ip'];
	$this->place_status=$ord_line['place_status'];
	$this->rem_type=$ord_line['rem_type'];
}

function del_br() {
	$query_del = "DELETE FROM rent_orders WHERE order_id='$this->order_id'";
	$result_del = $this->mysqli->query($query_del);
	if (!$result_del) {die('Сбой при доступе к базе данных: '.$query_del.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
}

function arch_copy() {
	//копирование брони в архив
		$query_arch = "INSERT INTO rent_orders_arch SELECT '', '".time()."', '".$_SESSION['user_id']."', order_id, `type`, order_date, `from`, `to`, `validity`, `inv_n`, model_id, cat_id, type2, client_id, info, web, cr_time, cr_who_id, ch_time, ch_who_id, status, `appr_id`, `appr_time`, `cr_ip`, `place_status`, `rem_type` FROM rent_orders WHERE order_id='$this->order_id'";
		$result_arch = $this->mysqli->query($query_arch);
		if (!$result_arch) {die('Сбой при доступе к базе данных: '.$query_arch.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
			
}

function rem_pics() {
	switch ($this->rem_type) {
		case 'stir':
			$this->rem_pic_url='/bb/stir.png';
		break;
		
		case 'meh':
			$this->rem_pic_url='/bb/meh.png';
		break;
			
		case 'tex':
			$this->rem_pic_url='/bb/textil.png';
		break;
		
		case 'oth':
			$this->rem_pic_url='/bb/inoe.png';
		break;
		
		default:
			$this->rem_pic_url='';
		break;
	}	
}
	
}//end of class bron

/*описание некоторых подходов
 * Возможные варианты жесткой брони: type2 bron, deliv, remont, out
 * Варианты нежесткой брони: stirka_rent, заявка
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 * */


?>