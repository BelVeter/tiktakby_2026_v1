<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных


echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/tt.css" rel="stylesheet" type="text/css" />
<style>

</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<title>WEB инфо моделей.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: '.$_SESSION['office'].'</form> </div>
<div id="zv_div"></div>

';

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

?>

<script language="javascript">

</script>




<?php 

$x_date='2015-11-20';
$sex='f';
$rost='100';

foreach ($_GET as $key => $value) {
	$$key = get_get($key);
}

//входящий sex (мальчик или девочка. юнисекс всега выводится)
//входящий ny (новый год)
//входящий zv (звери)
//входящий tale (сказка)
//входящий rost (в сантиметрах)
//входящий x_date (дата утренника)

$x_date=strtotime($x_date);
$return_limit=$x_date+13*60*60;//если возврат после 13 => выдача в 17 - на этот день позже. не должно быть
$out_limit=$x_date+16*60*60;//если есть выдача до 16, то на утро (12) уже никто не успеет взять. не должно быть
$x_date_next=$x_date+24*60*60;//след день для сквоздной брони


$k_srch='';

if ($ny=='1') {
	$k_srch.=" AND tovar_rent.ny='1'";
}
elseif ($ny=='-1') {
	$k_srch.=" AND tovar_rent.ny!='1'";
}

if ($zv=='1') {
	$k_srch.=" AND tovar_rent.zv='1'";
}
elseif ($zv=='-1') {
	$k_srch.=" AND tovar_rent.zv!='1'";
}

if ($tale=='1') {
	$k_srch.=" AND tovar_rent.tale='1'";
}
elseif ($tale=='-1') {
	$k_srch.=" AND tovar_rent.tale!='1'";
}


$k_srch.=" AND tovar_rent_items.item_rost1<='$rost' AND tovar_rent_items.item_rost2>='$rost'";

//основной запрос
$k_mod_q= "SELECT * FROM tovar_rent_items LEFT JOIN tovar_rent ON (tovar_rent_items.model_id = tovar_rent.tovar_rent_id) WHERE tovar_rent.m_sex IN ('y','$sex') AND tovar_rent.tovar_rent_cat_id='2'$k_srch ORDER BY model_id";
$result_k_mod = $mysqli->query($k_mod_q);
if (!$result_k_mod) {die('Сбой при доступе к базе данных: '.$k_mod_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$k_mod_num=$result_k_mod->num_rows;


//echo 'Нашло:'.$k_mod_num;

$model_prev='';

while ($k_mod=$result_k_mod->fetch_assoc()) {
	
	if ($model_prev==$k_mod['tovar_rent_id']) {//если модель повторяется - отменяем
		continue;
	}
	
	$query_kb = "SELECT * FROM karn_brons WHERE inv_n='".$k_mod['item_inv_n']."' AND ((t_to>=$return_limit AND t_to<=$x_date_next) OR (t_from>=$x_date AND t_from<=$out_limit) OR (t_from<=$x_date AND t_to>=$x_date_next) ) AND `status`!='in_process'";
	$result_kb = $mysqli->query($query_kb);
	if (!$result_kb) {die('Сбой при доступе к базе данных: '.$query_kb.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$kb_rows=$result_kb->num_rows;
	
	if ($kb_rows>=1) {//елси нашли соответствующую бронь - не выводим
		continue;
	}
	
	
	//ищем web_info
	$m_info_q = "SELECT * FROM rent_model_web WHERE model_id='".$k_mod['tovar_rent_id']."'";
	$result_m_info = $mysqli->query($m_info_q);
	if (!$result_m_info) {die('Сбой при доступе к базе данных: '.$m_info_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$mi_num=$result_m_info->num_rows;
	$mi=$result_m_info->fetch_assoc();
	
	
	//ищем тариф
	$query_tarifs = "SELECT * FROM rent_tarif_act WHERE model_id='".$k_mod['tovar_rent_id']."' ORDER BY sort_num, kol_vo";
	$result_tarifs = $mysqli->query($query_tarifs);
	if (!$result_tarifs) {die('Сбой при доступе к базе данных: '.$query_tarifs.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$tarif_def=$result_tarifs->fetch_assoc();

	//ищем размеры
	$query_items = "SELECT * FROM tovar_rent_items WHERE model_id='".$k_mod['tovar_rent_id']."' ORDER BY item_rost1, item_rost2";
	$result_items = $mysqli->query($query_items);
	if (!$result_items) {die('Сбой при доступе к базе данных: '.$query_items.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$s_num=$result_items->num_rows;

	$k_sizes=array();
	$razm_ok='0';
	$k_i_size='';

	while ($k_item=$result_items->fetch_assoc()) {
			
		$k_i_size=$k_item['item_rost1'].'-'.$k_item['item_rost2'];

		if (!in_array($k_i_size, $k_sizes)) {
			$k_sizes[]=$k_i_size;
		}
			
	}

	

	$k_sizes_text='';
	foreach ($k_sizes as $value) {
		$k_sizes_text.=$value.',';
	}
	$k_sizes_text=substr($k_sizes_text,0,strlen($k_sizes_text)-1);//убираем последнюю запятую




	echo '
		<a href="'.$mi['page_addr'].'"><div class="k_top_2">
			<div class= "k_l2_text">'.$mi['l2_name'].'</div>
			<div class= "k_box_img"><img src="'.$mi['l2_pic'].'" alt="'.$mi['l2_alt'].'" /></div>
			<div class="k_down_1l">Размер:</div><div class="k_down_1r"><div class="k_line">'.$k_sizes_text.'</div></div>
			<div class="k_down_1l">Тариф:</div><div class="k_down_2r"><div class="k_line">'.number_format($tarif_def['rent_amount'], 0, ',', ' ').' тыс / сутки</div></div>
			<div class="k_down_1l">On-line</div><div class="k_down_3r"><div class="k_line">БРОНИРОВАНИЕ</div></div>
		</div> </a>
			';

	$model_prev=$k_mod['tovar_rent_id'];

}//end of while



function get_get($var)
{

	return mysql_real_escape_string($_GET[$var]);
}

function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}

?>