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

/*
 * разобраться с датой оказания услуг в части выдачи не в день подписания договора (особенно актуально для карнавалов);
 * 
 * 
 * */

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

set_time_limit(150);

$i_from_date=date("Y-m-d");
$i_to_date=date("Y-m-d");


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

$i_from_date=strtotime($i_from_date);
$i_to_date=strtotime($i_to_date);

echo '
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>


<form name="srch_form" method="post" id="srch_form" action="doh_rash_book.php">
	За период:
		c <input type="date" name="i_from_date" id="i_from_date" value="'.date("Y-m-d", $i_from_date).'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.date("Y-m-d", $i_to_date).'" /> <input type="submit" name="action" value="показать" onclick="" /><br />
		</form>
';


$dl_id_sub='';
$prev_dl_id_sub='';
$p_total=0;

$client_id='';
$client_id_prev='-1';

$k_docs = array('', '', '', '', '', '', '', '', '', '', '', '', '');
$k_amounts = array('', '', '', '', '', '', '', '', '', '', '', '', '');
$totals = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

//выбираем платежи
$sort_cl=" WHERE (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('payment', 'cl_payment') AND `r_payment_type` IN ('nal_cheque', 'card', 'bank')";

$query_ofpay = "(SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, place, ch_num FROM rent_sub_deals_act".$sort_cl.") UNION ALL (SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, place, ch_num FROM rent_sub_deals_arch".$sort_cl.") ORDER BY deal_id, acc_date";

//$query_ofpay = "SELECT * FROM `rent_sub_deals_arch` WHERE (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('payment', 'cl_payment') AND `r_payment_type` IN ('nal_cheque', 'card', 'bank') ORDER BY deal_id, acc_date";
$result_ofpay = $mysqli->query($query_ofpay);
if (!$result_ofpay) {die('Сбой при доступе к базе данных: '.$query_ofpay.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

while ($act_p=$result_ofpay->fetch_assoc()) {
	$dl_id_sub=$act_p['deal_id'];
	
	if ($dl_id_sub!=$prev_dl_id_sub) {//при переходе на следующую сделку делаем выборку по основной сделке 
		$query_dl = "SELECT * FROM `rent_deals_act` WHERE deal_id='".$act_p['deal_id']."'";
		$result_dl = $mysqli->query($query_dl);
		if (!$result_dl) {die('Сбой при доступе к базе данных: '.$query_dl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$dl=$result_dl->fetch_assoc();
		$dl_num=$result_dl->num_rows;
		
		$client_id=$dl['client_id'];
			if ($dl_num<1) {
				$query_dl = "SELECT * FROM `rent_deals_arch` WHERE deal_id='".$act_p['deal_id']."'";
				$result_dl = $mysqli->query($query_dl);
				if (!$result_dl) {die('Сбой при доступе к базе данных: '.$query_dl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				$dl=$result_dl->fetch_assoc();
				$dl_num=$result_dl->num_rows;	
				
				$client_id=$dl['client_id'];
			}
		
	}	
	//echo 'ids:'.$client_id.'-'.$client_id_prev.'<br />';
	//ищем информацию о клиенте
	if ($client_id!=$client_id_prev) {
		$query_cl = "SELECT * FROM `clients` WHERE client_id='$client_id'";
		$result_cl = $mysqli->query($query_cl);
		if (!$result_cl) {die('Сбой при доступе к базе данных: '.$query_cl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$cl=$result_cl->fetch_assoc();
		//$cl_num=$result_cl->num_rows;
	}
	
	
	//если доставка по оф. каналу - добавляем в чек
	if ($act_p['del_payment_type']!='nal_no_cheque') {
		$k_amounts[date("n", $act_p['acc_date'])].=($act_p['r_paid']*1000+$act_p['delivery_paid']*1000).'<br />';
		$totals[date("n", $act_p['acc_date'])]+=($act_p['r_paid']*1000+$act_p['delivery_paid']*1000);
		$p_total+=($act_p['r_paid']*1000+$act_p['delivery_paid']*1000);
	}
	else{
		$k_amounts[date("n", $act_p['acc_date'])].=($act_p['r_paid']*1000).'<br />';
		$totals[date("n", $act_p['acc_date'])]+=($act_p['r_paid']*1000);
		$p_total+=$act_p['r_paid']*1000;
	}
	
	if ($act_p['ch_num']=='') {
		$k_docs[date("n", $act_p['acc_date'])].='нет № чека <br />';
	}
	else {
		if ($act_p['r_payment_type']=='bank') {
			$ch_type='п/о №';
		}
		else {
			$ch_type='чек №';
		}
		
		
		$k_docs[date("n", $act_p['acc_date'])].=$ch_type.$act_p['ch_num'].' от '.date("d.m.Y", $act_p['acc_date']).'<br />';
	}
	
	
	if ($dl_id_sub!=$prev_dl_id_sub) {//при переходе на следующую сделку - формируем строку вывода основного массива и скидываем значения техн. массивов

		$tr[]='
		<tr>
			<td>'.date("d.m.Y", $dl['start_date']).'</td>
			<td>'.$cl['family'].' '.$cl['name'].' '.$cl['otch'].', договор №'.$dl['deal_id'].' от '.date("d.m.Y", $dl['start_date']).'</td>	
			<td>'.array_sum($k_amounts).'</td>
					
					
			<td>'.$k_docs[1].'</td>
			<td>'.$k_amounts[1].'</td>

			<td>'.$k_docs[2].'</td>
			<td>'.$k_amounts[2].'</td>
			
			<td>'.$k_docs[3].'</td>
			<td>'.$k_amounts[3].'</td>

			<td>'.$k_docs[4].'</td>
			<td>'.$k_amounts[4].'</td>
			
			<td>'.$k_docs[5].'</td>
			<td>'.$k_amounts[5].'</td>
				
			<td>'.$k_docs[6].'</td>
			<td>'.$k_amounts[6].'</td>
				
			<td>'.$k_docs[7].'</td>
			<td>'.$k_amounts[7].'</td>
			
			<td>'.$k_docs[8].'</td>
			<td>'.$k_amounts[8].'</td>
			
			<td>'.$k_docs[9].'</td>
			<td>'.$k_amounts[9].'</td>
			
			<td>'.$k_docs[10].'</td>
			<td>'.$k_amounts[10].'</td>

			<td>'.$k_docs[11].'</td>
			<td>'.$k_amounts[11].'</td>
					
			<td>'.$k_docs[12].'</td>
			<td>'.$k_amounts[12].'</td>
		</tr>		
				
				';
		
		
		//сбросы значений техн. массивов
		$p_total=0;
		$k_docs = array('', '', '', '', '', '', '', '', '', '', '', '', '');
		$k_amounts = array('', '', '', '', '', '', '', '', '', '', '', '', '');
	}
	
	$prev_dl_id_sub=$dl_id_sub;
	$client_id_prev=$client_id;

}
		
	echo '
<table border="1" cellspacing="0">
			
<tr>
	<th>Дата оказания услуги</th>
	<th>ФИО, договор</th>
	<th>стоимость услуг</th>
	<th colspan="2">Январь</th>
	<th colspan="2">Февраль</th>
	<th colspan="2">Март</th>
	<th colspan="2">Апрель</th>
	<th colspan="2">Май</th>
	<th colspan="2">Июнь</th>
	<th colspan="2">Июль</th>
	<th colspan="2">Август</th>
	<th colspan="2">Сентябрь</th>
	<th colspan="2">Октябрь</th>
	<th colspan="2">Ноябрь</th>					
	<th colspan="2">Декабрь</th>
</tr>			
			
			';	
foreach ($tr as $value) {
	echo $value;
}

echo '
<tr>
			<td>Итого:</td>
			<td></td>
			<td>'.array_sum($totals).'</td>
			
			
			<td></td>
			<td>'.$totals[1].'</td>

			<td></td>
			<td>'.$totals[2].'</td>
			
			<td></td>
			<td>'.$totals[3].'</td>

			<td></td>
			<td>'.$totals[4].'</td>
			
			<td></td>
			<td>'.$totals[5].'</td>
				
			<td></td>
			<td>'.$totals[6].'</td>
				
			<td></td>
			<td>'.$totals[7].'</td>
			
			<td></td>
			<td>'.$totals[8].'</td>
			
			<td></td>
			<td>'.$totals[9].'</td>
			
			<td></td>
			<td>'.$totals[10].'</td>

			<td></td>
			<td>'.$totals[11].'</td>
					
			<td></td>
			<td>'.$totals[12].'</td>
		</tr>		
</table>		
		';
	
	
	
//echo $query_ofpay.'<br /><br /><br />';

	
	




function get_post($var)
{
    GLOBAL $mysqli;
    return $mysqli->real_escape_string($_POST[$var]);
}



?>