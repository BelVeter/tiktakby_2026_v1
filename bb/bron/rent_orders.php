<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных

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
	<a class="div_item" href="/bb/rent_deals_all.php">Все сделки (новые)</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
</div><br />
';
require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам


?>

<script language="javascript">


function show_edit(id) {

	document.getElementById('br_valid_'+id).style.display="inline-block";
	document.getElementById('inv_n_'+id).style.display="inline-block";
	document.getElementById('info_'+id).style.display="inline-block";
	document.getElementById('action_'+id).style.display="inline-block";
	document.getElementById('cans_but_'+id).style.display="inline-block";
	document.getElementById('edit_but_'+id).style.display="none";

	document.getElementById('br_2_t_'+id).style.display="inline";

	if (document.getElementById('type1').value=="strong" && (document.getElementById('br_2_t_'+id).value=="of_bron" || document.getElementById('br_2_t_'+id).value=="web_new")) {
		document.getElementById('deliv_yn_span_'+id).style.display="inline";
	}
	
	
}

function cans_edit(id) {

	document.getElementById('br_valid_'+id).style.display="none";
	document.getElementById('inv_n_'+id).style.display="none";
	document.getElementById('info_'+id).style.display="none";
	document.getElementById('action_'+id).style.display="none";
	document.getElementById('cans_but_'+id).style.display="none";
	document.getElementById('edit_but_'+id).style.display="inline-block";

	document.getElementById('br_2_t_'+id).style.display="none";
	document.getElementById('deliv_yn_span_'+id).style.display="none";
	
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

function br_br() {
	document.getElementById('type1').value="strong";
	document.getElementById('br_filter').submit();
}

function br_zayavk() {
	document.getElementById('type1').value="zayavka";
	document.getElementById('br_filter').submit();
}

function br_deliv() {
	document.getElementById('type1').value="delivery";
	document.getElementById('br_filter').submit();
}

function br_other() {
	document.getElementById('type1').value="other";
	document.getElementById('br_filter').submit();
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

function br_ch_ch() {
	if ((document.getElementById('br_2_t').value=="of_bron" || document.getElementById('br_2_t').value=="remont") && document.getElementById('inv_n_new').value=="") {
		alert ('Бронь или Ремонт можно поставить только при выбранном инвентарном номере. Сначала выберите инвентарный номер.');
		document.getElementById('br_2_t').value="";
		document.getElementById('deliv_yn_span').style.display="none";
		document.getElementById('deliv_yn').value="0";	
	}

	if (document.getElementById('br_2_t').value=="of_bron") {
		document.getElementById('deliv_yn_span').style.display="inline";
	}
	else {
		document.getElementById('deliv_yn_span').style.display="none";
		document.getElementById('deliv_yn').value="0";
	}	
}

</script>

<?php 

//Проверка входящей информации
	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
	foreach ($_POST as $key => $value) {
		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
	}
	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

	
$inv_n='';
$item_inv_n='';
$action='';
$model_def['color']='';
$type1='strong';


foreach ($_POST as $key => $value) {
		$$key = get_post($key);
	}
	
if (isset($_POST['action'])) {
	
	

	switch ($action) {

		case 'сохранить':
			$ac_date=strtotime(date("Y-m-d"));
			$validity=strtotime($br_valid);
			
			if ($br_2_t=='of_bron') {
				$type_i='strong';
				$type_2i='of_bron';
			}
			if ($br_2_t=='remont') {
				$type_i='strong';
				$type_2i='remont';
			}
			else {
				$type_i='zayavka';
				$type_2i='of_zayavka';
			}
			
			
			$query = "INSERT INTO rent_orders VALUES ('', '$type_i', '$ac_date', '', '', '$validity', '$inv_n', '', '', '$type_2i', '', '$info', '$deliv_yn', '".time()."', '".$_SESSION['user_id']."', '', '', '', '', '')";
			$result = $mysqli->query($query);
			if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
				
			$edit_id=$mysqli->insert_id;
			
			if ($inv_n>0) {//если есть инв. номер устанавливаем статус товара - бронь

				$query_upd = "UPDATE tovar_rent_items SET `status`='bron' WHERE item_inv_n='$inv_n'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			}
			
		break;
		
		case 'обновить':
			$validity=strtotime($br_valid);
			
				if ($inv_n>0) {
					$q_tov = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$inv_n'";
					$result_tov = $mysqli->query($q_tov);
					if (!$result_tov) {die('Сбой при доступе к базе данных: '.$q_tov.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
					$i_tov = $result_tov->fetch_assoc();
					$mod_n=$i_tov['model_id'];
				}
			
			if ($br_2_t=='of_zayavka' || $br_2_t=='web_zayavka') {
				$type1='zayavka';
				$inv_n='';
				
			}
			else {
				$type1='strong';
			}
			
			$query_upd = "UPDATE rent_orders SET validity='$validity', `type`='$type1', deliv='$deliv_yn', type2='$br_2_t', inv_n='$inv_n', model_id='$mod_n', info='$info', ch_time='".time()."', ch_who_id='".$_SESSION['user_id']."' WHERE order_id='$order_id'";
			$result_upd = $mysqli->query($query_upd);
			if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			
			//!!!дописать контроль, вдруг новый инвентарный номер уже занят -- тогда делаем заявку а не бронь
			
			if ($inv_n!=$inv_n_old) {
				$query_upd = "UPDATE tovar_rent_items SET `status`='bron' WHERE item_inv_n='$inv_n'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
				$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent' WHERE item_inv_n='$inv_n_old'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			}
			
			/*
			if ($inv_n>0) {//если есть инв. номер устанавливаем статус товара - бронь
			
				$query_upd = "UPDATE tovar_rent_items SET `status`='bron' WHERE item_inv_n='$inv_n'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			}
			*/
		break;
			
			
		case 'удалить':
			
			//сначала проверяем, не изменился ли статус товара, и меняем на ту_рент только если тек. статус бронь
			$query_ch = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$inv_n_old'";
			$result_ch = $mysqli->query($query_ch);
			if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			$it_ch = $result_ch->fetch_assoc();
			
							
			if ($inv_n_old>0 && $it_ch['status']=='bron') {//если есть инв. номер устанавливаем статус товара - ту_рент
				
				$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent' WHERE item_inv_n='$inv_n_old'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			}
			
			$query_del = "DELETE FROM rent_orders WHERE order_id='$order_id'";
			$result_del = $mysqli->query($query_del);
			if (!$result_del) {die('Сбой при доступе к базе данных: '.$query_del.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
		break;
	
	}//end of switch
}	
	
$query_or = "SELECT * FROM rent_orders WHERE `type`='$type1' ORDER BY validity";

if ($type1=='strong') {
	$query_or = "SELECT * FROM rent_orders WHERE `type`='strong' AND deliv!='1' ORDER BY validity";
	$type1=='strong';
}
elseif ($type1=='zayavka') {
	$query_or = "SELECT * FROM rent_orders WHERE `type`='zayavka' ORDER BY validity";
	$type1=='zayavka';
}
elseif ($type1=='delivery') {
	$query_or = "SELECT * FROM rent_orders WHERE `type`='strong' AND deliv='1' ORDER BY validity";
	$type1=='strong';
}
elseif ($type1=='other') {
	$query_or = "SELECT * FROM rent_orders WHERE `type`='strong' AND deliv='1' ORDER BY validity";
	$type1=='strong';
}


$result_or = $mysqli->query($query_or);
if (!$result_or) {die('Сбой при доступе к базе данных: '.$query_or.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

//перечень инв. номеров новая форма
$query_free = "SELECT item_inv_n FROM tovar_rent_items WHERE `status`='to_rent' OR (`status`='t_bron' AND br_time<'".time()."')";
$result_free = $mysqli->query($query_free);
if (!$result_free) {die('Сбой при доступе к базе данных: '.$query_free.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

$free_ns='';
while ($free_inv = $result_free->fetch_assoc()) {
	$free_ns.='<option value="'.$free_inv['item_inv_n'].'" '.sel_d($item_inv_n, $free_inv['item_inv_n']).'>'.$free_inv['item_inv_n'].'</option>';
}	

//перечень инв. номеров для изменений
$query_free = "SELECT item_inv_n FROM tovar_rent_items WHERE `status`='to_rent'";
$result_free = $mysqli->query($query_free);
if (!$result_free) {die('Сбой при доступе к базе данных: '.$query_free.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

$free_ns2='';
while ($free_inv = $result_free->fetch_assoc()) {
	$free_ns2.='<option value="'.$free_inv['item_inv_n'].'">'.$free_inv['item_inv_n'].'</option>';
}


echo '
<form name="br_filter" id="br_filter" method="post" action="rent_orders.php" >
		<input type="hidden" name="type1" id="type1" value="'.$type1.'" />
</form>
						
						
						
<a href="#" class="'.($type1=='strong' ? 'br_br_act' : 'br_br').'" onclick="br_br(); return false;">Брони</a><a href="#" class="'.($type1=='strong' ? 'br_zayavk' : 'br_zayavk_act').'" onclick="br_zayavk(); return false;">Заявки</a><a href="#" class="'.($type1=='delivery' ? 'br_zayavk_act' : 'br_zayavk').'" onclick="br_deliv(); return false;">Доставки</a><a href="#" class="'.($type1=='strong' ? 'br_zayavk' : 'br_zayavk_act').'" onclick="br_zayavk(); return false;">Ремонт/стирка/выбытие</a>
  <table border="1" cellspacing="0" '.($type1=='strong' ? 'style="border-color:#728356; position:relative; z-index:1; table-layout:fixed; width:1200px;"' : 'style="border-color:#7eaea9; position:relative; z-index:1; table-layout:fixed; width:1200px;"').'>
	
	<tr '.($type1=='strong' ? 'style="background-color:#afcb82;"' : 'style="background-color:#addcd7;"').'>
      <th style="width:81px; text-align:center;">Дата</th>
	  <th style="width:350px; text-align:center;">инв.№</th>
      <th style="width:350px; text-align:center;">коментарий</th>
	  <th style="width:81px; text-align:center;">срок</th>
      <th style="width:90px; text-align:center;">инфо</th>
      <th style="text-align:center;"><div style="position:relative"><input type="button" style="position:absolute; top:-65px; left:100px; height:50px; width:100px;" value="'.($action=='бронь' ? 'отмена' : 'новая бронь').'" id="new_order_but" onclick="new_order_f(); return false;"></div>
		действия</th>
    </tr>
    <tbody '.($action=='бронь' ? '' : 'style="display:none;"').' id="new_form_row">
	<tr>
      <td>сейчас</td>
      <td><select name="inv_n" id="inv_n_new" form="new_order"><option value="">не указан</option>'.$free_ns.'</select><br />
      	Тип брони:<select name="br_2_t" id="br_2_t" form="new_order" onchange="br_ch_ch();">
      			<option value="">не указан</option>
      			<option value="of_bron">бронь</option>
      			<option value="of_zayavka">заявка</option>
      			<option value="remont">ремонт-выбытие</option>
      		</select><br />
      	<span id="deliv_yn_span" style="display:none;" >Доставка:<select name="deliv_yn" id="deliv_yn" form="new_order">
      			<option value="0">нет</option>
      			<option value="1">да</option>
      		</select></span>
      	</td>
	  <td><textarea rows="3" cols="32" name="info" id="info_new" form="new_order"></textarea></td>
	  <td><div style="position:relative; z-index:2; background-color:#FFF;"><input type="date" name="br_valid" id="br_valid_new" form="new_order" value="'.date("Y-m-d", time()).'"></div></td>
      <td><input type="hidden" name="user_id" id="user_id_new" value="'.$_SESSION['user_id'].'" form="new_order">'.$_SESSION['user_fio'].'</td>
      <td><form name="new_order" id="new_order" action="rent_orders.php" method="post">
			<input type="submit" name="action" value="сохранить" onclick="return new_br();">
		</form>
		</td>
    </tr>
	</tbody>';
      		
while ($ord = $result_or->fetch_assoc()) {
		$vnim_style='';
		$vnim_text='';
	$free_alert='';
	$vidan=0;
	//проверка, вдруг уже сдали по брони
	if ($ord['inv_n']>0) {//если есть инв. номер
		$query_ch = "SELECT * FROM tovar_rent_items WHERE item_inv_n='".$ord['inv_n']."'";
		$result_ch = $mysqli->query($query_ch);
		if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$it_ch = $result_ch->fetch_assoc();
		$num = $result_ch->num_rows;
		
		if ($num>0 && $it_ch['status']=='rented_out') {
			$vnim_style='style="background-color:yellow"';
			$vnim_text='товар выдан!';
			$vidan=1;
		}
		
		
		$model_q = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$it_ch['model_id']."' LIMIT 0,1";
		$result_model_def = $mysqli->query($model_q);
		if (!$result_model_def) {die('Сбой при доступе к базе данных: '.$model_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$model_def=$result_model_def->fetch_assoc();
		
		$cat_q="SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id=".$it_ch['cat_id']." LIMIT 1";
		$result_cat_def = $mysqli->query($cat_q);
		if (!$result_cat_def) {die('Сбой при доступе к базе данных: '.$cat_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$cat_def=$result_cat_def->fetch_assoc();
		
		
	}
	elseif ($ord['model_id']>0) {//если есть заявка на бронь
		
		$model_q = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$ord['model_id']."' LIMIT 0,1";
		$result_model_def = $mysqli->query($model_q);
		if (!$result_model_def) {die('Сбой при доступе к базе данных: '.$model_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$model_def=$result_model_def->fetch_assoc();
		
		$cat_q="SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id=".$model_def['tovar_rent_cat_id']." LIMIT 1";
		$result_cat_def = $mysqli->query($cat_q);
		if (!$result_cat_def) {die('Сбой при доступе к базе данных: '.$cat_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$cat_def=$result_cat_def->fetch_assoc();
		
		//свободные по модели
		$z_free_q="SELECT * FROM tovar_rent_items LEFT JOIN tovar_rent ON (tovar_rent_items.model_id = tovar_rent.tovar_rent_id) WHERE model_id='".$ord['model_id']."' AND (`status`='to_rent' OR (`status`='t_bron' AND br_time<'".time()."'))";
		$z_free_res = $mysqli->query($z_free_q);
		if (!$z_free_res) {die('Сбой при доступе к базе данных: '.$z_free_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$z_fre_mod_num = $z_free_res->num_rows;
		
		//а это по категории, но не модели
		$z_free_q_cat="SELECT * FROM tovar_rent_items LEFT JOIN tovar_rent ON (tovar_rent_items.model_id = tovar_rent.tovar_rent_id) WHERE cat_id='".$model_def['tovar_rent_cat_id']."' AND (model_id!='".$ord['model_id']."' AND (`status`='to_rent' OR (`status`='t_bron' AND br_time<'".time()."')))";
		$z_free_res_cat = $mysqli->query($z_free_q_cat);
		if (!$z_free_res_cat) {die('Сбой при доступе к базе данных: '.$z_free_q_cat.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$z_fre_cat_num = $z_free_res_cat->num_rows;
		
			$free_ns_z='';
		while ($z_free=$z_free_res->fetch_assoc()) {
			$free_ns_z.='<option value="'.$z_free['item_inv_n'].'">'.$z_free['item_inv_n'].' '.$z_free['producer'].' / '.$z_free['model'].' / '.($z_free['color']=='multicolor' ? $z_free['item_color'] : $z_free['color']).'</option>';
		}
			if ($z_fre_cat_num>0) {
				$free_ns_z.='<option value="0">-----другие свободные модели в категории:</option>';//добавляем разделитель
				
				while ($z_free_cat=$z_free_res_cat->fetch_assoc()) {
					$free_ns_z.='<option value="'.$z_free_cat['item_inv_n'].'">'.$z_free_cat['item_inv_n'].' '.$z_free_cat['producer'].' / '.$z_free_cat['model'].' / '.($z_free_cat['color']=='multicolor' ? $z_free_cat['item_color'] : $z_free_cat['color']).'</option>';
				}
				
			}
			
			if ($z_fre_mod_num>0) {//есть товар конкретной модели
				$free_alert='<strong style="color:red;">!!!есть товар!!!</strong> <br />';
			}
			elseif ($z_fre_cat_num>0) {//есть товар категории
				$free_alert='';
			}
	}

	
	
	//просроченная бронь
	if ($ord['validity'] < strtotime(date("Y-m-d"))) {
		$st_prosr='style="background-color:red"';
	}
	else {
		$st_prosr='-';
	}
	
	if ($model_def['color']==0) {
		$color='';
	}
	
	if ($model_def['color']=='multicolor' && $ord['model_id']==0) {//!!!проверить вывод цветов при брони по модели
		$color=' (цвет: '.$it_ch['item_color'].')';
	}
	else {
		$color=' (цвет: '.$model_def['color'].')';
	}
	
	
	if ($ord['model_id']>0) {
		$zayavk_text='<br /><span><strong>Заявка</strong> на:'.$cat_def['rent_cat_name'].': '.$model_def['producer'].' '.$model_def['model'].$color.'</span>';
	}
	else {
		$zayavk_text='';
	}
	
	
	echo '
	<tr>
		<td>'.date("d.m.y", $ord['order_date']).'<br /><i>('.date("H:i", $ord['cr_time']).')</i></td>
		<td '.$vnim_style.'>'.$free_alert.$ord['inv_n'].' '.$vnim_text.'<input type="hidden" name="inv_n_old" value="'.$ord['inv_n'].'" form="order_'.$ord['order_id'].'" />
				<select style="display:none; width:100px;" name="inv_n" id="inv_n_'.$ord['order_id'].'" form="order_'.$ord['order_id'].'"><option value="'.$ord['inv_n'].'">'.$ord['inv_n'].'</option>'.($ord['type']=='zayavka' ? $free_ns_z : $free_ns2).'</select>
				'.stat_select_edit ($ord['order_id'], $ord['type'], $ord['type2']).'			
				<span id="deliv_yn_span_'.$ord['order_id'].'" style="display:none;"><br />Доставка:<select name="deliv_yn" id="deliv_yn_'.$ord['order_id'].'" form="order_'.$ord['order_id'].'">
      				<option value="0" '.sel_d($ord['deliv'], '0').'>нет</option>
	      			<option value="1" '.sel_d($ord['deliv'], '1').'>да</option>
      			</select></span>
      			'.($ord['inv_n']>0 ? '<br /><strong>Товар на '.$it_ch['item_place'].' офисе</strong><br />'.$cat_def['rent_cat_name'].': '.$model_def['producer'].' '.$model_def['model'].$color : '').'
			 '.$zayavk_text.'
			</td>	
		<td '.($ord['ch_who_id']>0 ? 'style="background-color:green;"' : '').'>'.$ord['info'].'<br />
				<textarea rows="3" cols="32" style="display:none;" name="info" id="info_'.$ord['order_id'].'" form="order_'.$ord['order_id'].'">'.good_print($ord['info']).'</textarea></td>
		<td '.$st_prosr.'>'.date("d.m.y", $ord['validity']).'<br />
				<div style="position:relative; z-index:2; background-color:#FFF;"><input style="display:none;" type="date" name="br_valid" id="br_valid_'.$ord['order_id'].'" form="order_'.$ord['order_id'].'" value="'.date("Y-m-d", $ord['validity']).'"></div>
			</td>
    	<td '.($ord['type2']=='web_new' ? 'style="background-color:#F60"' : '').'> '.($ord['type2']=='web_new' ? 'сайт<br />' : '').'  '.user_name($ord['cr_who_id']).($ord['ch_who_id']>0 ? '/'.user_name($ord['ch_who_id']) : '').'</td>
		<td><form name="order_'.$ord['order_id'].'" id="order_'.$ord['order_id'].'" action="rent_orders.php" method="post">
				<input type="hidden" name="user_id" id="user_id_'.$ord['order_id'].'" value="'.$_SESSION['user_id'].'">
				<input type="hidden" name="order_id" id="order_id_'.$ord['order_id'].'" value="'.$ord['order_id'].'">
				<input type="hidden" name="type1" id="type1_'.$ord['order_id'].'" value="'.$ord['type'].'">
				
				'.($vidan==0 ? '<input type="button" value="изменить" id="edit_but_'.$ord['order_id'].'" onclick="show_edit(\''.$ord['order_id'].'\'); return false;">' : '').'
				
      			<input style="display:none;" type="submit" name="action" id="action_'.$ord['order_id'].'" value="обновить"><input type="submit" name="action" id="action_'.$ord['order_id'].'" value="удалить"><br />
				<input style="display:none;" type="button" value="отмена" id="cans_but_'.$ord['order_id'].'" onclick="cans_edit(\''.$ord['order_id'].'\'); return false;">
			</form>
			'.($ord['inv_n']>0 ? '<form method="post" action="dogovor_new.php" style="display:inline-block;"><input type="hidden" name="item_inv_n" value="'.$ord['inv_n'].'" /><input type="submit" value="нов.договор" /></form>' : '').'				
		  </td>
	</tr>
			
			
			
			';
	
}
      		
echo'      		
  </table>
		
		';


function stat_select_edit ($order_id, $type1, $type2) {
	
	if ($type2=='web_new') {
		$output='
			<select style="display:none;" name="br_2_t" id="br_2_t_'.$order_id.'" form="order_'.$order_id.'">
		    	<option value="web_new" '.sel_d($type1, 'strong').'>web_бронь</option>
		    	<option value="web_zayavka" '.sel_d($type1, 'zayavka').'>web_заявка</option>
			</select>';
	}
	
	else {
	
	$output='	<select style="display:none;" name="br_2_t" id="br_2_t_'.$order_id.'" form="order_'.$order_id.'">
					<option value="">не указан</option>
      				<option value="of_bron" '.(($type1=='strong' && $type2!='remont') ? 'selected="selected"' : '').'>бронь</option>
      				<option value="of_zayavka" '.($type1=='zayavka' ? 'selected="selected"' : '').'>заявка</option>
      				<option value="remont" '.(($type1=='strong' && $type2=='remont') ? 'selected="selected"' : '').'>ремонт-выбытие</option>
				</select>	
	
	';
	}
	
	return $output;
	
}


function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
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


function user_name ($id) {
	switch ($id) {
		case '1':
			return 'тестовый пользователь';
			break;

		case '2':
			return 'Кристина';
			break;

		case '3':
			return 'Дима';
			break;

		case '4':
			return 'Андрей';
			break;

		case '5':
			return 'Аня';
			break;

		case '6':
			return 'Денис';
			break;

		case '9':
			return 'Света';
			break;
		
		case '11':
			return 'Артем';
			break;
		case '12':
			return 'Алексей';
			break;
		case '13':
			return 'Татьяна';
			break;
			
		default:
			return 'ХЗ';
			break;
	}
}



?>