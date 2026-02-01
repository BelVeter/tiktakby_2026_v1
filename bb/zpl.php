<?php
session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных

echo '

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>BB: Отчетность</title>
<link href="stile.css" rel="stylesheet" type="text/css" />
';


//------- proverka paroley
$in_level= array(3,5,7);

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level))) {
	die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	<body>

	<form action="/bb/index.php" method="post">
		Офис:<select name="of_select" id="of_select">
				<option value="0">не выбран</option>
				<option value="1">Литературная</option>
				<option value="2">Ложинская</option>
			</select><br />
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>
	</head>

<body>
');
}

//-----------proverka paroley


//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

?>


<script language="javascript">

function zpl_sh() {
	//alert ('ok');
	if (document.getElementById('zp_ch').style.display=="none") {
		document.getElementById('zp_ch').style.display="";
		document.getElementById('zp_ch_but').value=" - ";
		
	}
	else {
		document.getElementById('zp_ch').style.display="none";
		document.getElementById('zp_ch_but').value=" + ";
	}
}//end of function


function sb_sh() {
	//alert ('ok');
	if (document.getElementById('sb_details').style.display=="none") {
		document.getElementById('sb_details').style.display="";
		
	}
	else {
		document.getElementById('sb_details').style.display="none";
	
	}
}//end of function


</script>




<?php 
$i_from_date=date("Y-m-d");
$i_to_date=date("Y-m-d");
$i_bad_date=mktime(0,0,0,(date("m")-1),1,date("Y")); $i_bad_date=date("Y-m-d", $i_bad_date);
$i_bad_date_to=date("Y-m-d");
$zpl_id="0";
$w_d_num=21.5;
$main_share=5;
$sb_share=10;



foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}


if (isset($action) && $action=='изменить оклад') {
	$query_upd = "UPDATE logpass SET oklad='$new_oklad' WHERE logpass_id='$logpass_id'";
	$result_upd = $mysqli->query($query_upd);
	if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

}





$i_from_date=strtotime($i_from_date);
$i_to_date=strtotime($i_to_date);
$i_bad_date=strtotime($i_bad_date);
$i_bad_date_to=strtotime($i_bad_date_to);

$sb = array();
$vs = array();

//формируем список сотрудников
$query_zpl = "SELECT * FROM logpass WHERE zp_yn='1' ORDER BY lp_fio";
$result_zpl = $mysqli->query($query_zpl);
if (!$result_zpl) {die('Сбой при доступе к базе данных: '.$query_zpl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

//формируем перечень суббот и воскресений
for ($i=$i_from_date; $i<=($i_to_date); $i+=(24*60*60)) {
//echo date("m.d.Y", $i).'('.date("w", $i).') --- '.strtotime(date("Y-m-d", $i)).' --- '.$i.'<br />';
	
	if (date("w", $i)==6) {
		$sb[]=$i;
	}
	if (date("w", $i)==0) {
		$vs[]=$i;
	}

}
$sb_vs = array();
$sb_vs=array_merge($sb,$vs);



echo '
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>
		
		
<form name="srch_form" method="post" id="srch_form" action="zpl.php">
	За период:
		c <input type="date" name="i_from_date" id="i_from_date" value="'.date("Y-m-d", $i_from_date).'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.date("Y-m-d", $i_to_date).'" /><br />
Сотрудник: <select name="zpl_id" id="zpl_id">
			<option value="0" >не выбран</option>
				';
			
	while ($zpl_s=$result_zpl->fetch_assoc()) {
		echo'<option value="'.$zpl_s['logpass_id'].'" '.($zpl_s['logpass_id']==$zpl_id ? 'selected="selected"' : '').'>'.$zpl_s['lp_fio'].'</option>';
	}
	
	echo'</select>	
				<input type="submit" name="action" value="рассчитать" /><br />		
</form>		

';

	if ($zpl_id<1) {
		die('<br /><br /><span style="color:red; font-size:18px;">Выберите сотрудника для расчета.</span>');
	}
			
if ($zpl_id>0) {
	$okl_q = "SELECT * FROM logpass WHERE logpass_id='$zpl_id'";
	$result_okl = $mysqli->query($okl_q);
	if (!$result_okl) {die('Сбой при доступе к базе данных: '.$okl_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$user_zpl=$result_okl->fetch_assoc();
	
	echo 'оклад сотрудника: '.number_format($user_zpl['oklad'], 1, ',', ' ').' тыс. руб. <input id="zp_ch_but" type="button" value=" + " onclick="zpl_sh();" />
	<span id="zp_ch" style="display:none;">		
		<input type="number" name="new_oklad" value="'.$user_zpl['oklad'].'" form="srch_form" style="width:70px;" />
		<input type="hidden" name="logpass_id" value="'.$zpl_id.'" form="srch_form" />
		<input type="submit" name="action" value="изменить оклад" form="srch_form" />		
		<br />		
	</span>
	<br />	Количество рабочих дней в периоде: <input type="number" step="any" name="w_d_num" form="srch_form" value="'.$w_d_num.'" style="width:70px;"  /><br />
	Процент от основной выручки: <input type="number" step="any" name="main_zp_proc" form="srch_form" value="'.$main_share.'" style="width:70px;"  />%<br />
	Процент от выручки суббот и воскресений: <input type="number" step="any" name="main_zp_proc" form="srch_form" value="'.$sb_share.'" style="width:70px;"  />%<br />
	<br />		
			
			';
}

echo '		
<table border="1" cellspacing="0">
<tr>
	<th style="width:200px;">Показатель</th>
	<th style="width:60px;">все</th>
	<th style="width:60px;">сотрудник</th>
	
</tr>			
		';

	if (function_exists('date_default_timezone_set')) {
		date_default_timezone_set('Europe/Minsk');
	}
//выручка всего проката
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of1=$of1_res['SUM(r_paid)'];
	
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of1+=$of1_res['SUM(r_paid)'];
	$total_sales=$of1;


//вся выручка субботы, воскр
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE acc_date IN ('".implode($sb_vs, "', '")."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of1=$of1_res['SUM(r_paid)'];
//echo $query_of1;

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE acc_date IN ('".implode($sb_vs, "', '")."')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of1+=$of1_res['SUM(r_paid)'];
	$sb_vs_total=$of1;


//выручка субботы, воскр по сотруднику
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE acc_date IN ('".implode($sb_vs, "', '")."') AND cr_who_id='$zpl_id'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of1=$of1_res['SUM(r_paid)'];
//echo $query_of1;
	
	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE acc_date IN ('".implode($sb_vs, "', '")."') AND cr_who_id='$zpl_id'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of1+=$of1_res['SUM(r_paid)'];
	$sb_vs_sotr_total=$of1;

	
	$sb_vs_sotr = array();
	$sb_vs_num=count($sb_vs);
	$sb_vs_sotr_num=0;
	
	
foreach ($sb_vs as $value) {
		
	//выручка субботы, воскр по сотруднику
	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE acc_date='$value' AND cr_who_id='$zpl_id'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of1=$of1_res['SUM(r_paid)'];
	//echo $query_of1;
	
		$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE acc_date='$value' AND cr_who_id='$zpl_id'";
		$result_of1 = $mysqli->query($query_of1);
		if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$of1_res=$result_of1->fetch_assoc();
		$of1+=$of1_res['SUM(r_paid)'];
		$sb_vs_sotr_amount=$of1;
		
		if ($sb_vs_sotr_amount>0) {
			$sb_vs_sotr_num+=1;
			$sb_vs_sotr[$value]=$sb_vs_sotr_amount;
		}
		
}	
	
	
	//$zpl_id
	
	
//считаем плохие долги
$query_dl_bad = "SELECT * FROM rent_deals_act WHERE return_date<='$i_bad_date' AND (deal_status='".$zpl_id."') ORDER BY return_date DESC";
$result_dl_bad = $mysqli->query($query_dl_bad);
if (!$result_dl_bad) {die('Сбой при доступе к базе данных: '.$query_dl_bad.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

$bad_amount=0;

while ($dl_bad=$result_dl_bad->fetch_assoc()) {
	$prosr=pay_calc($dl_bad['deal_id'], $i_bad_date_to);
	//echo $prosr.'<br />';
	if ($prosr<0) {
		$bad_amount+=$prosr;
	}
}



echo '
<tr>
	<td>Выручка всего (тыс. руб.)</td>
	<td>'.number_format($total_sales, 1, ',', ' ').'</td>
	<td></td>
</tr>		
<tr>
	<td><a href="#" onclick="sb_sh(); return false;">Выручка сб, вс (тыс. руб.)</a></td>
	<td>'.number_format($sb_vs_total, 1, ',', ' ').'</td>
	<td>'.number_format($sb_vs_sotr_total, 1, ',', ' ').'</td>
</tr>

<tbody id="sb_details" style="display:none;">
';
foreach ($sb_vs_sotr as $key => $value) {
	echo '
			
<tr style="font-style:italic;">
	<td>Выручка за '.date("d.m.Y (l)", $key).'</td>
	<td></td>
	<td>'.number_format($value, 1, ',', ' ').'</td>
</tr>			
			
			
			';
}		

			
echo '
</tbody>
			
<tr>
	<td>кол-во сб-вс</td>
	<td>'.number_format($sb_vs_num, 0, ',', ' ').'</td>
	<td>'.number_format($sb_vs_sotr_num, 0, ',', ' ').'</td>
</tr>	

<tr>
	<td>Плохие долги: <br />
			товары не возвращенные с (включительно):<input form="srch_form" type="date" name="i_bad_date" id="i_bad_date" value="'.date("Y-m-d", $i_bad_date).'" /><br />
			расчет суммы просрочки по состоянию на <input form="srch_form" type="date" name="i_bad_date_to" id="i_bad_date_to" value="'.date("Y-m-d", $i_bad_date_to).'" />
			
			</td>
	<td>'.number_format($bad_amount, 1, ',', ' ').'</td>
	<td></td>
</tr>
		
		';



echo '</table>';

//!!! плохие долги считаются с минусом !!!
$zpl_result=($total_sales-$sb_vs_total+4*$bad_amount)*($main_share/100) + $user_zpl['oklad'] + 2*($user_zpl['oklad']/$w_d_num)*$sb_vs_sotr_num+$sb_vs_sotr_total*($sb_share/100);

echo 'Зарплата сотрудника: '.number_format($zpl_result, 1, ',', ' ');

function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}




function pay_calc($deal_id, $ret_date) {

	global $mysqli;
	
	//запрос информации о сделке
	$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
	$result_dl_def = $mysqli->query($query_dl_def);
	if (!$result_dl_def) {die('Сбой при доступе к базе данных: '.$query_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$dl_def=$result_dl_def->fetch_assoc();

	//вытягиваем последний примененный тариф
	$query_sub_dl_tarif = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND type IN ('first_rent', 'extention') ORDER BY `from` DESC";
	$result_sub_dl_tarif = $mysqli->query($query_sub_dl_tarif);
	if (!$result_sub_dl_tarif) {die('Сбой при доступе к базе данных: '.$query_sub_dl_tarif.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$sub_dl_tarif=$result_sub_dl_tarif->fetch_assoc();




	//расчет платы за просрочку
	if ($ret_date>$dl_def['return_date']) {
		$morepay='просрочка';
		switch ($sub_dl_tarif['tarif_step']) {
			case 'month':
					
				if (date("j",$ret_date)>=date("j",$dl_def['return_date'])) { //вариант расчета, если текущий день равен, либо больше дня возврата
					$m_dif=(date("Y",$ret_date)*12+date("n",$ret_date))-(date("Y",$dl_def['return_date'])*12+date("n",$dl_def['return_date'])); // считаем разницу в месяцах
					$day_rent=$sub_dl_tarif['tarif_value']/30;
					$to_pay_ad=-($m_dif*$sub_dl_tarif['tarif_value']+(date("j",$ret_date)-date("j",$dl_def['return_date']))*$day_rent);
					$morepay=round($to_pay_ad, 1);
				}
					
				if (date("j",$ret_date)<date("j",$dl_def['return_date'])) { //вариант расчета, если текущий менее дня возврата
					$m_dif=(date("Y",$ret_date)*12+date("n",$ret_date)-1)-(date("Y",$dl_def['return_date'])*12+date("n",$dl_def['return_date'])); // считаем разницу в месяцах
					$day_rent=$sub_dl_tarif['tarif_value']/30;
					$to_pay_ad=-($m_dif*$sub_dl_tarif['tarif_value']+(date("j",$ret_date)+date("t",$dl_def['return_date'])-date("j",$dl_def['return_date']))*$day_rent);
					$morepay=round($to_pay_ad, 1);
				}
				break;

			case 'week';
			$day_dif=floor(($ret_date-$dl_def['return_date'])/60/60/24);
			$week_dif=floor($day_dif/7);
			$day_dif_left=$day_dif-$week_dif*7;
			$day_tarif=$sub_dl_tarif['tarif_value']/7;
			$to_pay_ad=-($week_dif*$sub_dl_tarif['tarif_value']+$day_dif_left*$day_tarif);
			$morepay=round($to_pay_ad, 1);

			break;

			case 'day':
					
				$day_dif=floor(($ret_date-$dl_def['return_date'])/60/60/24);
				$to_pay_ad=-($day_dif*$sub_dl_tarif['tarif_value']);
				$morepay=round($to_pay_ad, 1);
					
				break;
					

			default:
				echo 'не считает функция просрочки';
				break;
		}
			
			
			
	}
	elseif ($ret_date==$dl_def['return_date']) {
		$morepay='срок возврата сегодня';
		$to_pay_ad='0';
	}
	else {
		$morepay='срок завтра'; // реально показывае все, что далее сегодня, но на этой странице, просто выборка ограничена завтрашним днем.
		$to_pay_ad='0';
	}



	return $morepay;
}// end of pay_calc function

?>