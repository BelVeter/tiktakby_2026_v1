<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/bron.php'); // включаем класс 
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php'); // включаем класс


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
	<a class="div_item" href="/bb/rent_orders.php">Брони</a>
	
</div><br />
	
';
require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам


?>

<script language="javascript">

history.pushState(null, null, location.href);
window.onpopstate = function(event) {
    history.go(1);
};

function pic_size(id) {
	
	if (document.getElementById('item_pic_'+id).style.width=="80px") {
		document.getElementById('item_pic_'+id).style.width="250px";
		document.getElementById('item_pic_'+id).style.height="250px"
	}
	else {
		document.getElementById('item_pic_'+id).style.width="80px";
		document.getElementById('item_pic_'+id).style.height="80px";
	}
}

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
	var tmp = document.getElementById('info_'+id).innerHTML;
	var d = new Date();
	tmp+="\n"+d.getDate()+"."+(d.getMonth()+1)+"."+d.getFullYear()+"("+d.getHours()+":"+d.getMinutes()+")"+" "+document.getElementById('cur_user_name').value+" ткнул кнопку с тележкой";
	document.getElementById('info_'+id).innerHTML=tmp;
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
$office='all';
$place_status='';
$rem_type='';
$rem_resp='';
$uzhe_vidan=0;
$rem_person_id='all';


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
	
//Имя текущего пользователия
echo '<input type="hidden" id="cur_user_name" value="'.$lp_list[$_SESSION['user_id']].'" />';

// создаем перечень офисов
	$rd_of = "SELECT * FROM offices";
	$result_of = $mysqli->query($rd_of);
	if (!$result_of) {die('Сбой при доступе к базе данных: '.$rd_of.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	
	$off_pic[0]='';
	while ($t_of=$result_of->fetch_assoc()) {
		$off_pic[$t_of['number']]=$t_of['pic_addr'];
	}




//распределение ремонтов    appr_id = то, на кого распределяют (id юзера)  + type2=remont
$remont_users_count = array(
    '4'=>0,//Андрей
    '22'=>0,//Катя
    '24'=>0,//Марина
    '26'=>0,//Юля
);

$remont_users_new = array();

$query_r="SELECT `order_id` FROM `rent_orders` WHERE type2='remont' AND `appr_id`=''";
$result_r = $mysqli->query($query_r);
if (!$result_r) {die('Сбой при доступе к базе данных: '.$query_r.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

if ($result_r->num_rows>0) {

    //запрос информации о распределенных ремонтах
    $query_r_id = "
            SELECT COUNT(`order_id`), `appr_id` FROM `rent_orders` 
            WHERE type2='remont' AND `appr_id`!=0
            GROUP BY `appr_id`
            ";
    $result_rem_id = $mysqli->query($query_r_id);
    if (!$result_rem_id) die( 'Сбой при доступе к базе данных: ' . $query_r_id . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

    //assigning number of remont to array
    while ($rem_id=$result_rem_id->fetch_assoc()) {
        if (!array_key_exists($rem_id['appr_id'], $remont_users_count)) continue; //if not active user - skip
        else $remont_users_count[$rem_id['appr_id']]+=$rem_id['COUNT(`order_id`)'];
    }
    //делим неподеленные ремонты
    while ($rem_none=$result_r->fetch_assoc()) {
        $min_user_id=array_keys($remont_users_count, min($remont_users_count))[0];  # array('$remont_users_count')

        $remont_users_count[$min_user_id]+=1;
        $remont_users_new[$min_user_id][]=$rem_none['order_id'];
    }

    foreach ($remont_users_new as $key=>$value) {
        $query_upd = "UPDATE rent_orders SET `appr_id`='$key' WHERE order_id IN (". implode(',', array_map('intval', $value)).") ";
        $upd_result = $mysqli->query($query_upd);
        if (!$upd_result) die( 'Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
}


if (isset($_POST['action'])) {
	
	switch ($action) {
	
		case 'сохранить':
			$bron = new bron();
			
			$bron->inv_n=$inv_n;
			$bron->stirka();

			$bron->type2=$br_2_t;
			$bron->info=$info;
			$bron->order_date=strtotime(date("Y-m-d"));//сегодня
			$bron->validity=strtotime($br_valid);
			if ($br_2_t=='remont') {
				$bron->place_status=$place_status;
				$bron->rem_type=$rem_type;
				$bron->appr_id=$rem_resp;
			}
			if ($bron->type2=='remont' && $bron->in_stirka==1) {
				$bron->del_br_id($bron->stir_id);

			}

			if (isset($_POST['kidsiki']) && $_POST['kidsiki'] == 'yes') {
			    $bron->client_id=1;
            }
			//var_dump($bron);
			$bron->bron_insert();
			
			if ($bron->failure==1) {
				echo 'Ошибка !!!'.$bron->alert;
			}
			else {
				echo 'Бронь внесена успешно.';
			}
			$type2=$bron->type2;
			
		break;
	}
}

$type2s=$type2;
if ($type2=='bron' || $type2=='deliv') {
	$type2s="bron', 'deliv";
}



//период запроса удаленных броней
$from= new DateTime();
    $from->modify('-137 day');

//основной запрос	
$query_or = "SELECT * FROM rent_orders_arch WHERE arch_time>".$from->getTimestamp()." ORDER BY cr_time";
//echo $query_or;
$result_or = $mysqli->query($query_or);
if (!$result_or) {die('Сбой при доступе к базе данных: '.$query_or.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
$type2_num=$result_or->num_rows;

//echo '<pre>';
//var_dump($remont_users_count);
//echo '<pre>';

echo '
<h1>Брони, удаленные в течение последних 7-и дней</h1>
<table border="1" cellspacing="0">
  <tr style="background-color:red;">
      <th style="width:81px; text-align:center;">Дата/№</th>
	  <th style="width:81px;">фото</th>
	  <th style="width:350px; text-align:center;">Товар</th>
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
      <th style="width:90px; text-align:center;">созд/подтв';

      echo '
      </th>
      <th style="text-align:center;">
		Кто удалил</th>
  </tr>
			

';

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
	$br_line->web_load();

	
	($br_line->item_status=='rented_out' || $br_line->item_status=='to_deliver') ? $uzhe_vidan=1 : $uzhe_vidan=0;

	echo '
	<tr>
		<td>'.date("d.m.y", $br_line->order_date).'<br /><i>('.date("H:i", $br_line->cr_time).')</i><br /> №'.$br_line->order_id.' </td>
		<td><img src="'.$br_line->small_pic.'" style="width:80px; heigth:80px;" id="item_pic_'.$br_line->order_id.'"</td>
		<td '.($uzhe_vidan==1 ? 'style="background-color:yellow;"' : '').'>
			'.($br_line->in_stirka=='1' ? '<img alt="В стирке" title="в стирке" style="width:25px; height:25px; float:right;" src="/bb/clean.png"/>' : '').'
			'.$br_line->cat_dog_name.' '.$br_line->producer.': '.$br_line->model.'. Цвет: "'.$br_line->br_color.'"<br /><strong>'.$br_line->inv_n.' '.($br_line->isKidsiki() ? '<img src="/bb/kidsiki.jpg">' : '').($br_line->isSpelenok() ? '<img src="/bb/spelenok.jpg">' : '').' '.($br_line->client_id==1 ? '<img src="/bb/kidzakaz.jpg">' : '').'</strong> 
				

			';
		if ($br_line->type2=='remont') {
			echo '<a href="#" id="ch_a_pl_'.$br_line->order_id.'"><img style="width:25px; height:25px; float:right;" src="'.$off_pic[$br_line->item_place].'"/></a>';
		}
		else {
			echo '<img style="width:25px; height:25px; float:right;" src="'.$off_pic[$br_line->item_place].'"/>';
		}
	
	
	
	echo '
			
			</td>
		<td '.((($br_line->type2=='bron' || $br_line->type2=='deliv') && $br_line->appr_id>0) ? 'style="background-color:#acf398;"' : '').'>'.$br_line->info.' <br />
			
	

      	';
	if ($br_line->type2=='bron' || $br_line->type2=='deliv') {

	}
	else {

	}
	
	echo '	</td>
		<td>
		
		<a href="#"  id="val_show'.$br_line->order_id.'">
		'.date("d.m.y", $br_line->validity).'</a>
      		
      		</td>
    	<td>
					
							';
					if ($br_line->type2=='remont') {
						
						echo '<a href="#" id="rest_show_'.$br_line->order_id.'">'.($br_line->appr_id>0 ? $lp_list[$br_line->appr_id] : '---').'</a>';
					}
					else {
						echo ($br_line->web==1 ? 'сайт' : $lp_list[$br_line->cr_who_id]).'/'.$lp_list[$br_line->appr_id].'<br />'.($br_line->web==1 ? $br_line->cr_ip : '');
					}		
		echo '
					
						
						</td>
		<td> 
			';
					$del_time=new DateTime();
					$del_time->setTimestamp($ord['arch_time']);

					echo ' Удалено: '.$lp_list[$ord['arch_who']].'<br>'.$del_time->format("d.m.Y H:i").' <br/>
		</td>
	</tr>
		
		
		
			';
	unset($br_line);
}

//echo '<pre>';
//var_dump($remont_users_count);
//var_dump($remont_users_new);
//echo '<pre>';
	
	
	
	

function get_post($var)
{
	GLOBAL $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);}


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
			<option '.sel_d('16', $id).' value="16">Любовь Алексеевна</option>
			<option '.sel_d('18', $id).' value="18">Марго</option>		
			
				';
}



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