<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных

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
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Сделки. NEW.</title>
<body>
';

echo '
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: '.$_SESSION['office'].'</form> </div>
<div id="zv_div"></div>
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/cur_page.php">Страница курьера</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
	<a class="div_item" href="/bb/rent_orders.php">Брони</a>
</div>


		';
require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам
?>


<script language="javascript">

function send_form () {

	valid = true;

	k_from = k_to = "";

	// проверка клиента
	if (document.getElementById('i_from_date').value=="")
	{k_from="Период с, ";
     valid = false;}

	if (document.getElementById('i_to_date').value=="")
	{k_to="Период по, ";
     valid = false;}

	
	if (valid==false){
	alert ('Заполните даты периода! В частности: ' + k_from + k_to);
		}

	return valid;

}//enf of valid if
		
		
</script>		



<?php
//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";




$no_action=0;
$i_from_date='';
$i_to_date='';
$prev_action='';
$payment_type='all';
$doh_rash='all';
$cat_s='all';
$op_type='all';
$item_place=$_SESSION['office'];


/*
$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
$result_cats = mysql_query($query_cats);
if (!$result_cats) die("Сбой при доступе к базе данных: '$query_cats'".mysql_error());
*/


/*
if ($cat_id!='def') {
	if ($cat_id<10) {
		$cat_qr=" AND item_inv_n LIKE '70".$cat_id."%'";
	}
	else {
		$cat_qr=" AND item_inv_n LIKE '7".$cat_id."%'";
	}
}
	
if ($prev_action!='no' && !isset($_POST['action'])) {
	$action=$prev_action;
	$_POST['action']=$action;//that is to activate switch, which is linked to POST action (not $action)

}

*/

if (!isset($_POST['action']) && isset($_POST['prev_action'])) {$_POST['action']=$_POST['prev_action'];}

if (!isset($_POST['action'])) {$_POST['action']='сегодня';}

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}



if (isset($_POST['action'])) {


	switch ($action) {

		case 'сегодня':

			$from_date=strtotime(date("Y-m-d"));
			
			//для фильтра по операциям
			if ($op_type!='all') {
				$op_srch=" AND `type`='$op_type'";
			}
			else {
				$op_srch='';
			}

			$sort_cl=" WHERE (acc_date='".$from_date."')".$op_srch;
			$sort_date="(acc_date='".$from_date."')";

			$prev_action='сегодня';

			$i_from_date=date("Y-m-d");
			$i_to_date=date("Y-m-d");
				
			break;

		case 'вчера':

			$today=getdate(time());

			$from_date=mktime(0, 0, 0, $today['mon'], ($today['mday']-1), $today['year']);
			
			//для фильтра по операциям
			if ($op_type!='all') {
				$op_srch=" AND `type`='$op_type'";
			}
			else {
				$op_srch='';
			}
			
			
			$sort_cl=" WHERE (acc_date='".$from_date."')".$op_srch;
			$sort_date="(acc_date='".$from_date."')";
			
			$prev_action='вчера';
			
			$i_from_date=date("Y-m-d", $from_date);
			$i_to_date=date("Y-m-d", $from_date);

			break;

		case 'завтра+':

			$today=getdate(time());

			$from_date=mktime(0, 0, 0, $today['mon'], ($today['mday']+1), $today['year']);

			
			//для фильтра по операциям
			if ($op_type!='all') {
				$op_srch=" AND `type`='$op_type'";
			}
			else {
				$op_srch='';
			}
			
			$sort_cl=" WHERE (acc_date>='".$from_date."')".$op_srch;
			$sort_date="(acc_date>='".$from_date."')";

			$prev_action='завтра+';
			$i_from_date=date("Y-m-d", $from_date);
			$i_to_date='';

			break;

		case 'показать':
			
			$from_date=strtotime($i_from_date);
			$to_date=strtotime($i_to_date);
			
			//для фильтра по операциям
			if ($op_type!='all') {
				$op_srch=" AND `type`='$op_type'";
			}
			else {
				$op_srch='';
			}
			
			$sort_cl=" WHERE (acc_date BETWEEN '".$from_date."' AND '".$to_date."')".$op_srch;
			$sort_date="(acc_date BETWEEN '".$from_date."' AND '".$to_date."')";

			$prev_action='показать';

			break;

	}//end of switch
}//end of if
else {
		$no_action=1;
}

$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
$result_cats = mysql_query($query_cats);
if (!$result_cats) die("Сбой при доступе к базе данных: '$query_cats'".mysql_error());



echo '
		<form name="srch_form" method="post" id="srch_form" action="rent_deals_all.php">
			<input type="submit" name="action" value="сегодня" /> <input type="submit" name="action" value="вчера" /> <input type="submit" name="action" value="завтра+" /> За период:
			c <input type="date" name="i_from_date" id="i_from_date" value="'.$i_from_date.'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.$i_to_date.'" /> <input type="submit" name="action" value="показать" onclick="return send_form();" /><br />
	<select name="cat_s" id="cat_s" onchange="document.getElementById(\'srch_form\').submit();">
			<option value="all">все категории</option>';
				while ($cat_names = mysql_fetch_array($result_cats)) {
					echo '<option value="'.$cat_names['tovar_rent_cat_id'].'" '.sel_d($cat_names['tovar_rent_cat_id'], $cat_s).' >'.good_print($cat_names['rent_cat_name']).'</option>';
				}
echo '							
	</select>
	<select name="doh_rash" id="doh_rash" onchange="document.getElementById(\'srch_form\').submit();">
			<option value="all" '.sel_d($doh_rash, 'all').'>доходы и расходы</option>
			<option value="doh" '.sel_d($doh_rash, 'doh').'>только доходы</option>
			<option value="rash" '.sel_d($doh_rash, 'rash').'>только расходы</option>
	</select>
 							
<input type="hidden" name="prev_action" id="prev_action" value="'.$prev_action.'" />		
</form>';

			

			
			if ($no_action!=1) {
				
				//запрос информации о суб сделках
				$query_sub_dl_def = "(SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, place FROM rent_sub_deals_act".$sort_cl.") UNION ALL (SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, place FROM rent_sub_deals_arch".$sort_cl.") ORDER BY `acc_date` DESC, `cr_time` DESC";
				$result_sub_dl_def = mysql_query($query_sub_dl_def);
				if (!$result_sub_dl_def) die("Сбой при доступе к базе данных: '$query_sub_dl_def'".mysql_error());
				
				
				
				//расчет касс всего, т.е. с учетом всех доходов и расходов

				$of_nal_no_ch=kassa('r_paid', 'nal_no_cheque','office', $sort_date);
				$of_nal_ch=kassa('r_paid', 'nal_cheque','office', $sort_date);
				$of_card=kassa('r_paid', 'card','office', $sort_date);
				$of_bank=kassa('r_paid', 'bank','office', $sort_date);
				
				$cur_nal_no_ch=kassa('r_paid', 'nal_no_cheque','cur', $sort_date);
				$cur_nal_ch=kassa('r_paid', 'nal_cheque','cur', $sort_date);
				$cur_card=kassa('r_paid', 'card','cur', $sort_date);
				$cur_bank=kassa('r_paid', 'bank','cur', $sort_date);
				
				$del_nal_no_ch=kassa('delivery_paid', 'nal_no_cheque','cur', $sort_date);
				$del_nal_ch=kassa('delivery_paid', 'nal_cheque','cur', $sort_date);
				$del_card=kassa('delivery_paid', 'card','cur', $sort_date);
				$del_bank=kassa('delivery_paid', 'bank','cur', $sort_date);
				
				
				
				//получаем id сделки, на которой весят расходы
				$query_item_r = "SELECT * FROM tovar_rent_items WHERE item_inv_n='7401'";
				$result_item_r = mysql_query($query_item_r);
				if (!$result_item_r) die("Сбой при доступе к базе данных: '$query_item_r'".mysql_error());
				$item_r=mysql_fetch_array($result_item_r);
				$item_r_rows=mysql_num_rows($result_item_r);
				$r_dl_id=$item_r['active_deal_id'];
				
			}
			else {die ('<br /><br /><font size="4">Выберите период для начала работы.</font>');}

			
			
echo '
	<div id="svod"></div>
<table border="1" cellspacing="0" style="background-color:#AFDC7E; display:block; float:left;" id="stats">
<tr>
	<th>платежи (ВСЕ)</th>
	<th>№2</th>
	<th>№1</th>
	<th>терм.</th>				
	<th>банк</th>
	<th>Итого</th>				
</tr>
<tr>
	<td>офис:</td>
	<td style="text-align:right">'.number_format($of_nal_no_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of_nal_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of_card, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_bank+$of_card+$of_nal_ch+$of_nal_no_ch), 1, ',', ' ').'</td>
</tr>
<tr>
	<td>курьер:</td>
	<td style="text-align:right">'.number_format($cur_nal_no_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($cur_nal_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($cur_card, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($cur_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($cur_bank+$cur_card+$cur_nal_ch+$cur_nal_no_ch), 1, ',', ' ').'</td>
</tr>
<tr style="font-weight:bold; font-style:italic;">
	<td>итого (без д.):</td>
	<td style="text-align:right">'.number_format(($of_nal_no_ch+$cur_nal_no_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_nal_ch+$cur_nal_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_card+$cur_card), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_bank+$cur_bank), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format((($of_bank+$of_card+$of_nal_ch+$of_nal_no_ch)+($cur_bank+$cur_card+$cur_nal_ch+$cur_nal_no_ch)), 1, ',', ' ').'</td>
</tr>										
<tr>
	<td>за доставку:</td>
	<td style="text-align:right">'.number_format($del_nal_no_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($del_nal_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($del_card, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($del_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($del_bank+$del_card+$del_nal_ch+$del_nal_no_ch), 1, ',', ' ').'</td>
</tr>		
<tr style="font-weight:bold;">
	<td>всего:</td>
	<td style="text-align:right">'.number_format(($of_nal_no_ch+$cur_nal_no_ch+$del_nal_no_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_nal_ch+$cur_nal_ch+$del_nal_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_card+$cur_card+$del_card), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_bank+$cur_bank+$del_bank), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format((($of_bank+$of_card+$of_nal_ch+$of_nal_no_ch)+($cur_bank+$cur_card+$cur_nal_ch+$cur_nal_no_ch)+($del_bank+$del_card+$del_nal_ch+$del_nal_no_ch)), 1, ',', ' ').'</td>
</tr>										
</table>';



if ($no_action!=1) {
	//далее делаем расчет платежей без расходов
	$of_nal_no_ch=kassa_n_r('r_paid', 'nal_no_cheque','office', $sort_date, $r_dl_id);
	$of_nal_ch=kassa_n_r('r_paid', 'nal_cheque','office', $sort_date, $r_dl_id);
	$of_card=kassa_n_r('r_paid', 'card','office', $sort_date, $r_dl_id);
	$of_bank=kassa_n_r('r_paid', 'bank','office', $sort_date, $r_dl_id);

	$cur_nal_no_ch=kassa_n_r('r_paid', 'nal_no_cheque','cur', $sort_date, $r_dl_id);
	$cur_nal_ch=kassa_n_r('r_paid', 'nal_cheque','cur', $sort_date, $r_dl_id);
	$cur_card=kassa_n_r('r_paid', 'card','cur', $sort_date, $r_dl_id);
	$cur_bank=kassa_n_r('r_paid', 'bank','cur', $sort_date, $r_dl_id);

	$del_nal_no_ch=kassa_n_r('delivery_paid', 'nal_no_cheque','cur', $sort_date, $r_dl_id);
	$del_nal_ch=kassa_n_r('delivery_paid', 'nal_cheque','cur', $sort_date, $r_dl_id);
	$del_card=kassa_n_r('delivery_paid', 'card','cur', $sort_date, $r_dl_id);
	$del_bank=kassa_n_r('delivery_paid', 'bank','cur', $sort_date, $r_dl_id);
}




echo'
			
<table border="1" cellspacing="0" style="background-color:#AFDC7E; display:block; float:left; margin: 0 20px;" id="stats2">
<tr>
	<th>платежи (без расх)</th>
	<th>№2</th>
	<th>№1</th>
	<th>терм.</th>				
	<th>банк</th>
	<th>Итого</th>				
</tr>
<tr>
	<td>офис:</td>
	<td style="text-align:right">'.number_format($of_nal_no_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of_nal_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of_card, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_bank+$of_card+$of_nal_ch+$of_nal_no_ch), 1, ',', ' ').'</td>
</tr>
<tr>
	<td>курьер:</td>
	<td style="text-align:right">'.number_format($cur_nal_no_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($cur_nal_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($cur_card, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($cur_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($cur_bank+$cur_card+$cur_nal_ch+$cur_nal_no_ch), 1, ',', ' ').'</td>
</tr>
<tr style="font-weight:bold; font-style:italic;">
	<td>итого (без д.):</td>
	<td style="text-align:right">'.number_format(($of_nal_no_ch+$cur_nal_no_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_nal_ch+$cur_nal_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_card+$cur_card), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_bank+$cur_bank), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format((($of_bank+$of_card+$of_nal_ch+$of_nal_no_ch)+($cur_bank+$cur_card+$cur_nal_ch+$cur_nal_no_ch)), 1, ',', ' ').'</td>
</tr>										
<tr>
	<td>за доставку:</td>
	<td style="text-align:right">'.number_format($del_nal_no_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($del_nal_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($del_card, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($del_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($del_bank+$del_card+$del_nal_ch+$del_nal_no_ch), 1, ',', ' ').'</td>
</tr>		
<tr style="font-weight:bold;">
	<td>всего:</td>
	<td style="text-align:right">'.number_format(($of_nal_no_ch+$cur_nal_no_ch+$del_nal_no_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_nal_ch+$cur_nal_ch+$del_nal_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_card+$cur_card+$del_card), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_bank+$cur_bank+$del_bank), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format((($of_bank+$of_card+$of_nal_ch+$of_nal_no_ch)+($cur_bank+$cur_card+$cur_nal_ch+$cur_nal_no_ch)+($del_bank+$del_card+$del_nal_ch+$del_nal_no_ch)), 1, ',', ' ').'</td>
</tr>										
</table>';


if ($no_action!=1) {
	//далее делаем расчет платежей без расходов
	$of_nal_no_ch=kassa_rsh('r_paid', 'nal_no_cheque','office', $sort_date, $r_dl_id);
	$of_nal_ch=kassa_rsh('r_paid', 'nal_cheque','office', $sort_date, $r_dl_id);
	$of_card=kassa_rsh('r_paid', 'card','office', $sort_date, $r_dl_id);
	$of_bank=kassa_rsh('r_paid', 'bank','office', $sort_date, $r_dl_id);

	$cur_nal_no_ch=kassa_rsh('r_paid', 'nal_no_cheque','cur', $sort_date, $r_dl_id);
	$cur_nal_ch=kassa_rsh('r_paid', 'nal_cheque','cur', $sort_date, $r_dl_id);
	$cur_card=kassa_rsh('r_paid', 'card','cur', $sort_date, $r_dl_id);
	$cur_bank=kassa_rsh('r_paid', 'bank','cur', $sort_date, $r_dl_id);

	$del_nal_no_ch=kassa_rsh('delivery_paid', 'nal_no_cheque','cur', $sort_date, $r_dl_id);
	$del_nal_ch=kassa_rsh('delivery_paid', 'nal_cheque','cur', $sort_date, $r_dl_id);
	$del_card=kassa_rsh('delivery_paid', 'card','cur', $sort_date, $r_dl_id);
	$del_bank=kassa_rsh('delivery_paid', 'bank','cur', $sort_date, $r_dl_id);
}




echo'
		
<table border="1" cellspacing="0" style="background-color:#AFDC7E; display:block; float:left; margin: 0 20px;" id="stats2">
<tr>
	<th>платежи (РАСХОДЫ)</th>
	<th>№2</th>
	<th>№1</th>
	<th>терм.</th>
	<th>банк</th>
	<th>Итого</th>
</tr>
<tr>
	<td>офис:</td>
	<td style="text-align:right">'.number_format($of_nal_no_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of_nal_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of_card, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_bank+$of_card+$of_nal_ch+$of_nal_no_ch), 1, ',', ' ').'</td>
</tr>
<tr>
	<td>курьер:</td>
	<td style="text-align:right">'.number_format($cur_nal_no_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($cur_nal_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($cur_card, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($cur_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($cur_bank+$cur_card+$cur_nal_ch+$cur_nal_no_ch), 1, ',', ' ').'</td>
</tr>
<tr style="font-weight:bold; font-style:italic;">
	<td>итого (без д.):</td>
	<td style="text-align:right">'.number_format(($of_nal_no_ch+$cur_nal_no_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_nal_ch+$cur_nal_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_card+$cur_card), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_bank+$cur_bank), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format((($of_bank+$of_card+$of_nal_ch+$of_nal_no_ch)+($cur_bank+$cur_card+$cur_nal_ch+$cur_nal_no_ch)), 1, ',', ' ').'</td>
</tr>
<tr>
	<td>за доставку:</td>
	<td style="text-align:right">'.number_format($del_nal_no_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($del_nal_ch, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($del_card, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($del_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($del_bank+$del_card+$del_nal_ch+$del_nal_no_ch), 1, ',', ' ').'</td>
</tr>
<tr style="font-weight:bold;">
	<td>всего:</td>
	<td style="text-align:right">'.number_format(($of_nal_no_ch+$cur_nal_no_ch+$del_nal_no_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_nal_ch+$cur_nal_ch+$del_nal_ch), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_card+$cur_card+$del_card), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_bank+$cur_bank+$del_bank), 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format((($of_bank+$of_card+$of_nal_ch+$of_nal_no_ch)+($cur_bank+$cur_card+$cur_nal_ch+$cur_nal_no_ch)+($del_bank+$del_card+$del_nal_ch+$del_nal_no_ch)), 1, ',', ' ').'</td>
	</tr>
	</table>

			
			
			
			
			
	<table border="1" cellspacing="0" style="clear:both;">
		<tr>'.($_SESSION['user_id']==3 ? '<th>id-s</th>' : '').
		'<th style="width:60px;">уч.дата</th>
			<th style="width:80px;">операция<br />
					<select name="op_type" id="op_type" form="srch_form" onchange="document.getElementById(\'srch_form\').submit();" style="width:80px;">
							<option value="all" '.sel_d('all', $op_type).'>все</option>
							<option value="first_rent" '.sel_d('first_rent', $op_type).'>Выдачи</option>
							<option value="extention" '.sel_d('extention', $op_type).'>Продления</option>
							<option value="takeaway_plan" '.sel_d('takeaway_plan', $op_type).'>Предоплата/бронь</option>
							<option value="close" '.sel_d('close', $op_type).'>Возвраты</option>
					</select>
				</th>
			<th style="width:250px;">Товар</th>
			<th style="width:90px;">Даты сделки</th>
			<th style="width:50px;">к опл.<br /></th>
			<th style="width:65px;">опл-о
				<select name="payment_type" id="rent_payment_type" form="srch_form" onchange="document.getElementById(\'srch_form\').submit();" style="width:50px;">
							<option value="all" '.sel_d('all', $payment_type).'>все</option>
							<option value="nal_no_cheque" '.sel_d('nal_no_cheque', $payment_type).'>Касса №2</option>
							<option value="nal_cheque" '.sel_d('nal_cheque', $payment_type).'>Касса №1</option>
							<option value="card" '.sel_d('card', $payment_type).'>карточка</option>
							<option value="bank" '.sel_d('bank', $payment_type).'>банк</option>
					</select>			
				</th>
			<th>Офис<br />
				<select name="item_place" id="place_select" form="srch_form" style="display:inline-block; width:90px" onchange="document.getElementById(\'srch_form\').submit();">
				  		<option value="all" '.sel_d($item_place, 'all').'>все</option>
						<option value="1" '.sel_d($item_place, '1').'>Машерова</option>
						<option value="2" '.sel_d($item_place, '2').'>Ложинская</option>
					</select>
				</th>
			<th style="width:270px;">Адрес (доставки), ФИО, телефоны</th>
			<th>Доп. инфо</th>
			<th>Действия</th>
		</tr>';

$r_to_pay_total=0;
$del_to_pay_total=0;
$r_paid_total=0;
$del_paid_total=0;
$sub_dl_count=0;
$prev_date='';
	
while ($sub_dl_def=mysql_fetch_array($result_sub_dl_def)) {
		
	if ($sub_dl_def['type']=='payment' || $sub_dl_def['type']=='cl_payment') {
		
		//если включен фильтр платежей и нет соответствия - не обрабатываем платеж
		if ($payment_type!='all' AND $sub_dl_def['r_payment_type']!=$payment_type AND $sub_dl_def['del_payment_type']!=$payment_type) {
			$no_payment_filter='yes';
			continue;
		}
					
		$query_sub_1 = "SELECT * FROM rent_sub_deals_act WHERE sub_deal_id='".$sub_dl_def['link']."'";
		$result_sub_1 = mysql_query($query_sub_1);
		if (!$result_sub_1) die("Сбой при доступе к базе данных: '$query_sub_1'".mysql_error());
		$sub1_rows=mysql_num_rows($result_sub_1);
		$sub1_def=mysql_fetch_array($result_sub_1);
	
	
		if ($sub1_rows<1) {
				
			$query_sub_1 = "SELECT * FROM rent_sub_deals_arch WHERE sub_deal_id='".$sub_dl_def['link']."'";
			$result_sub_1 = mysql_query($query_sub_1);
			if (!$result_sub_1) die("Сбой при доступе к базе данных: '$query_sub_1'".mysql_error());
			$sub1_rows=mysql_num_rows($result_sub_1);
			$sub1_def=mysql_fetch_array($result_sub_1);
		}
	
		if ($sub1_def['acc_date']>=strtotime($i_from_date)) {
			continue;
		}
		
		else {
			$sub_dl_def['from']=$sub1_def['from'];
			$sub_dl_def['rent_tenor']=$sub1_def['rent_tenor'];
			$sub_dl_def['tarif_step']=$sub1_def['tarif_step'];
		}
	
	
	}
	
	
//разделительная полоса
if ($prev_date>0 && $sub_dl_def['acc_date']!=$prev_date) {
	echo '<tr style="background-color:#3300FF; height:20px;">
			'.($_SESSION['user_id']==3 ? '<td></td>' : '').'<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
		</tr>';
}
$prev_date=$sub_dl_def['acc_date'];

		
	//запрос актуальной информации о сделке
	$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."'";
	$result_dl_def = mysql_query($query_dl_def);
	if (!$result_dl_def) die("Сбой при доступе к базе данных: '$query_dl_def'".mysql_error());
	$dl_rows=mysql_num_rows($result_dl_def);
	$dl_def=mysql_fetch_array($result_dl_def);
	
	if ($dl_rows<1) {
		
		$query_dl_def = "SELECT * FROM rent_deals_arch WHERE deal_id='".$sub_dl_def['deal_id']."'";
		$result_dl_def = mysql_query($query_dl_def);
		if (!$result_dl_def) die("Сбой при доступе к базе данных: '$query_dl_def'".mysql_error());
		$dl_def=mysql_fetch_array($result_dl_def);	
	}
	
	//если включен фильтр по расходам = ИНВ.НОМЕРУ и нет соответствия - не обрабатываем операцию
	if ($doh_rash=='rash' AND $dl_def['item_inv_n']!='7401') {
		continue;
	}
	//если включен фильтр по расходам = ИНВ.НОМЕРУ и нет соответствия - не обрабатываем операцию
	if ($doh_rash=='doh' AND $dl_def['item_inv_n']=='7401') {
		continue;
	}
	
	
	//запрос информации о клиенте
	$query_cl_def = "SELECT * FROM clients WHERE client_id='".$dl_def['client_id']."'";
	$result_cl_def = mysql_query($query_cl_def);
	if (!$result_cl_def) die("Сбой при доступе к базе данных: '$query_cl_def'".mysql_error());
	$cl_def=mysql_fetch_array($result_cl_def);
	
	//запрос информации о товаре по инв. номеру
	$query_item_def = "SELECT * FROM tovar_rent_items WHERE item_inv_n='".$dl_def['item_inv_n']."'";
	$result_item_def = mysql_query($query_item_def);
	if (!$result_item_def) die("Сбой при доступе к базе данных: '$query_item_def'".mysql_error());
	$item_def=mysql_fetch_array($result_item_def);
	$item_rows=mysql_num_rows($result_item_def);
	
	if ($item_rows<1) {
			
		$query_item_def = "SELECT * FROM tovar_rent_items_arch WHERE item_inv_n='".$dl_def['item_inv_n']."'";
		$result_item_def = mysql_query($query_item_def);
		if (!$result_item_def) die("Сбой при доступе к базе данных: '$query_item_def'".mysql_error());
		$item_def=mysql_fetch_array($result_item_def);
	
	}
	
	
	//запрос информации о первой выдаче (ищем офис)
	$query_fr = "SELECT * FROM rent_sub_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."' AND `type`='first_rent'";
	$result_fr = mysql_query($query_fr);
	if (!$result_fr) die("Сбой при доступе к базе данных: '$query_fr'".mysql_error());
	$dl_rows=mysql_num_rows($result_fr);
	$fr=mysql_fetch_array($result_fr);
	
	if ($dl_rows<1) {
	
		$query_fr = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='".$sub_dl_def['deal_id']."' AND `type`='first_rent'";
		$result_fr = mysql_query($query_fr);
		if (!$result_fr) die("Сбой при доступе к базе данных: '$query_fr'".mysql_error());
		$fr=mysql_fetch_array($result_fr);
	}
	
	
	$ret_d_text='';
	
	if ($sub_dl_def['type']=='close') {

	//запрос информации о дате последнег продления\выдачи по операциям возврата
	$query_ret_d = "SELECT * FROM rent_sub_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."' AND `type` IN ('first_rent', 'extention') ORDER BY sub_deal_id DESC";
	$result_ret_d = mysql_query($query_ret_d);
	if (!$result_ret_d) die("Сбой при доступе к базе данных: '$query_ret_d'".mysql_error());
	$ret_d_rows=mysql_num_rows($result_ret_d);
		
		if ($ret_d_rows<1) {
			
			$query_ret_d = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='".$sub_dl_def['deal_id']."' AND `type` IN ('first_rent', 'extention') ORDER BY sub_deal_id DESC";
			$result_ret_d = mysql_query($query_ret_d);
			if (!$result_ret_d) die("Сбой при доступе к базе данных: '$query_ret_d'".mysql_error());
			$ret_d_rows=mysql_num_rows($result_ret_d);
			$ret_d=mysql_fetch_array($result_ret_d);
			$ret_d_text='<br />('.date("d.m.y", $ret_d['to']).')';
		}
		else {
			$ret_d=mysql_fetch_array($result_ret_d);
			$ret_d_text='<br />('.date("d.m.y", $ret_d['to']).')';
		}
	}
	
	//запрос информации и модели
	$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$item_def['model_id']."'";
	$result_model_def = mysql_query($query_model_def);
	if (!$result_model_def) die("Сбой при доступе к базе данных: '$query_model_def'".mysql_error());
	$model_def=mysql_fetch_array($result_model_def);
	
	$model_def['color'] == 0 ? $color='' : $color=', цвет: '.$model_def['color'];
	
	//запрос информации о категории товара
	$query_cat_def = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$model_def['tovar_rent_cat_id']."'";
	$result_cat_def = mysql_query($query_cat_def);
	if (!$result_cat_def) die("Сбой при доступе к базе данных: '$query_cat_def'".mysql_error());
	$cat_def=mysql_fetch_array($result_cat_def);

	//если включен фильтр категорий и нет соответствия - не обрабатываем операцию
	if ($cat_s!='all' AND $cat_def['tovar_rent_cat_id']!=$cat_s) {
		continue;
	}
	
	//если включен фильтр места и нет соответствия - не обрабатываем операцию
	if ($item_place!='all' AND $sub_dl_def['place']!=$item_place) {
		continue;
	}
	
	if ($sub_dl_def['type']!='payment' && $sub_dl_def['type']!='cl_payment') {
	//запрос информации о платежах
	$query_sub_pay = "SELECT * FROM rent_sub_deals_act WHERE `link`='".$sub_dl_def['sub_deal_id']."' AND `type`='payment' AND $sort_date";
	$result_sub_pay = mysql_query($query_sub_pay);
	if (!$result_sub_pay) die("Сбой при доступе к базе данных: '$query_sub_pay'".mysql_error());
	$pay_rows=mysql_num_rows($result_sub_pay);
		
		if ($pay_rows<1) {
			
			$query_sub_pay = "SELECT * FROM rent_sub_deals_arch WHERE `link`='".$sub_dl_def['sub_deal_id']."' AND `type` IN ('payment', 'cl_payment') AND $sort_date";
			$result_sub_pay = mysql_query($query_sub_pay);
			if (!$result_sub_pay) die("Сбой при доступе к базе данных: '$query_sub_pay'".mysql_error());
			$pay_rows=mysql_num_rows($result_sub_pay);
		}
	
	if ($fr['place']==1 && $fr['delivery_yn']!=1) {
		$fr_chanell='<img src="/images/1.gif" style="position:absolute; right:0; top:0; width:25px; heght:25px;" />';
	}
	elseif ($fr['place']==2 && $fr['delivery_yn']!=1) {
		$fr_chanell='<img src="/images/2.gif" style="position:absolute; right:0; top:0; width:25px; heght:25px;" />';
	}
	elseif ($fr['delivery_yn']==1) {
		$fr_chanell='<img src="/images/k.png" style="position:absolute; right:0; top:0; width:25px; heght:25px;" />';
	}	
		
	$r_paid=0;
	$del_paid=0;
	$r_p_ch='';
	$d_p_ch='';
	
		
	while ($sub_pay=mysql_fetch_array($result_sub_pay)) {
		
		//если включен фильтр платежей и нет соответствия - не обрабатываем платеж
		if ($payment_type!='all' AND $sub_pay['r_payment_type']!=$payment_type AND $sub_pay['del_payment_type']!=$payment_type) {
			$no_payment_filter='yes';
			continue;
		}
		
		
		if ($payment_type=='all' || $sub_pay['r_payment_type']==$payment_type) {
			$r_paid+=$sub_pay['r_paid'];
			$r_p_ch=sh_kassa($sub_pay['r_payment_type']);
		}
		else {
			$r_paid+=0;
			$r_p_ch='';
		}
		
		if ($payment_type=='all' || $sub_pay['del_payment_type']==$payment_type) {
			$del_paid+=$sub_pay['delivery_paid'];
			$d_p_ch=sh_kassa($sub_pay['del_payment_type']);
		}
		else {
			$del_paid+=0;
			$d_p_ch='';
		}
		
		/*$r_paid+=$sub_pay['r_paid']; было ранее до верхних двух ифов. потом удалить, если все будет работать нормально
		$del_paid=$sub_pay['delivery_paid'];
		
		$r_p_ch=sh_kassa($sub_pay['r_payment_type']);
		$d_p_ch=sh_kassa($sub_pay['del_payment_type']);
		*/
	}
	
	if ($payment_type!='all' AND ($r_paid+$del_paid)==0) {
		continue;
	}
	
	
	if ($pay_rows>1) {
		$r_p_ch='>1';
		$d_p_ch='>1';
	}
	
	}//end of non_payment if
	else {
		
		if ($payment_type=='all' || $sub_dl_def['r_payment_type']==$payment_type) {
			$r_paid=$sub_dl_def['r_paid'];
			$r_p_ch=sh_kassa($sub_dl_def['r_payment_type']);
		}
		else {
			$r_paid=0;
			$r_p_ch='';
		}
		
		if ($payment_type=='all' || $sub_dl_def['del_payment_type']==$payment_type) {
			$del_paid=$sub_dl_def['delivery_paid'];
			$d_p_ch=sh_kassa($sub_dl_def['del_payment_type']);
		}
		else {
			$del_paid=0;
			$d_p_ch='';
		}
		
		/*сохранил на всякий случай. потом удалить
		$r_paid=$sub_dl_def['r_paid'];
		$del_paid=$sub_dl_def['delivery_paid'];
		
		$r_p_ch=sh_kassa($sub_dl_def['r_payment_type']);
		$d_p_ch=sh_kassa($sub_dl_def['del_payment_type']);
		*/
	}
	
	//запрос информации о курьере
	if ($sub_dl_def['courier_id']>0) {
		$query_cur = "SELECT * FROM logpass WHERE logpass_id='".$sub_dl_def['courier_id']."'";
		$result_cur = mysql_query($query_cur);
		if (!$result_cur) die("Сбой при доступе к базе данных: '$query_cur'".mysql_error());
		$cur=mysql_fetch_array($result_cur);
		$cur_name='<br />('.$cur['lp_fio'].')';
	}
	else {
		$cur_name='';
	}
	
	
	if ((($r_paid+$del_paid)-($sub_dl_def['r_to_pay']+$sub_dl_def['delivery_to_pay']))<=-20) {
			
		$dolg_color='color:red; font-weight:bold;';
	}
	else {$dolg_color='';}
	
	
	echo '<tr ';
			if ($sub_dl_def['status']=='for_cur') {
				
				echo 'style="background-color:#80C4F0"';
			}
			elseif ($sub_dl_def['status']=='delivered') {
			
				echo 'style="background-color:#C4F4F2"';
			}
			elseif ($sub_dl_def['status']=='takeaway_plan') {
					
				echo 'style="background-color:#FF0"';
			}
			elseif ((($r_paid+$del_paid)-($sub_dl_def['r_to_pay']+$sub_dl_def['delivery_to_pay']))<=-20) {
					
				echo 'style="background-color:#F5C138"';
			}
				
			 
	echo'	>'.($_SESSION['user_id']==3 ? '<td>'.$sub_dl_def['deal_id'].'<br />'.$sub_dl_def['sub_deal_id'].'</td>' : '').
			'<td>'.date("d.m.y", $sub_dl_def['acc_date']).'<br /><i>('.date("H:i", $sub_dl_def['cr_time']).')</i><br />д№'.$sub_dl_def['deal_id'].'</td>
			<td>'.op_print($sub_dl_def['type'], $sub_dl_def['delivery_yn']).($sub_dl_def['acc_date']==$sub_dl_def['from'] ? '' : ' c '.date("d.m.y", $sub_dl_def['from'])).($sub_dl_def['type']!='return' && $sub_dl_def['type']!='close' ? '<br /><i> на '.number_format($sub_dl_def['rent_tenor'], 0, ',', ' ').' '.step_pr($sub_dl_def['tarif_step']).'</i>' : '').'</td>
			<td style="position:relative;"> <strong>№'.inv_print($dl_def['item_inv_n']).$fr_chanell.'</strong><br />'.$cat_def['dog_name'].' '.$model_def['model'].', '.$model_def['producer'].$color.'</td>
			<td>'.date("d.m.y", $dl_def['start_date']).' - '.date("d.m.y", $dl_def['return_date']).$ret_d_text.'</td>
			<td style="text-align:right; '.$dolg_color.'">'.number_format($sub_dl_def['r_to_pay'], 0, ',', ' ').($sub_dl_def['delivery_to_pay']!=0 ? '<br /><span class="deliv_num">'.number_format($sub_dl_def['delivery_to_pay'], 0, ',', ' ').'</span>' : '').'</td>
			<td style="text-align:right"><strong>'.number_format($r_paid, 0, ',', ' ').$r_p_ch.'<br />'.($del_paid!=0 ? '<span class="deliv_num">'.number_format($del_paid, 0, ',', ' ').$d_p_ch.'</span></strong>' : '</strong>').'</td>
			<td>'.of_print($sub_dl_def['place']).'</td>
			<td>'.$cl_def['family'].' '.$cl_def['name'].' '.$cl_def['otch'].', '.$cl_def['city'].', ул. '.$cl_def['str'].', '.$cl_def['dom'].'-'.$cl_def['kv'].', тел.: '.phone_print($cl_def['phone_1']).', '.phone_print($cl_def['phone_2']).'</td>
			<td>'.user_name($sub_dl_def['cr_who_id']).'<br />'.$sub_dl_def['info'].'</td>
			<td>';
	$r_to_pay_total+=$sub_dl_def['r_to_pay'];
	$del_to_pay_total+=$sub_dl_def['delivery_to_pay'];
	$r_paid_total+=$r_paid;
	$del_paid_total+=$del_paid;
	$sub_dl_count+=1;
	
	
	if ($dl_rows>=1) {
		
		echo'
					<form method="post" action="dogovor_new.php">
					<input type="hidden" name="item_inv_n" value="'.$dl_def['item_inv_n'].'" />
					<input type="hidden" name="client_id" value="'.$dl_def['client_id'].'" />
					<input type="submit" value="к договору" />
					</form>';
	}
	else {
		echo'
					<form method="post" action="deals_arch.php">
					<input type="hidden" name="deal_id" value="'.$dl_def['deal_id'].'" />
					<input type="submit" name="action" value="в архив" />
					</form>';
	}
	
	
	
	if ($sub_dl_def['status']=='for_cur') {
		
		echo '
		<form method="post" action="cur_page.php">
			<input type="hidden" name="one_sub_dl_id" value="'.$sub_dl_def['sub_deal_id'].'" />
			<input type="submit" name="action" value="страница курьера" />
		</form>';
	}
	
					
	echo '				
					</td>
		</tr>	
			
			
			';

}

echo '</table>';


$svod='
	Всего '.$sub_dl_count.' операций на сумму '.number_format($r_to_pay_total, 0, ',', ' ').' тыс.бел.руб. Оплачено '.number_format($r_paid_total, 0, ',', ' ').' тыс.бел.руб. <br />
	Курьеру за выезды должны '.number_format($del_to_pay_total, 0, ',', ' ').' тыс.бел.руб. Оплачено '.number_format($del_paid_total, 0, ',', ' ').' тыс.бел.руб.
';

$svod=str_replace(array("\r\n", "\r", "\n"), "", $svod); //превращаем в одну строку, иначе javascript не поймет

echo '
	<script type="text/javascript">
		document.getElementById(\'svod\').innerHTML="'.$svod.'";
	</script>				
					
					';



function op_print ($op, $del) {
	switch ($op) {
		case 'first_rent':
			$output='выдача';
		break;
		
		case 'takeaway_plan':
			$output='бронь';
		break;
		
		case 'extention':
			$output='продление';
			break;
		
		case 'close':
		case 'cur_return':
			$output='возврат';
			break;
		
		case 'payment':
		case 'cl_payment':
			$output='оплата';
			break;
					
		
		default:
			$output='XZ';
		break;
	}
	
	if ($del=='1') {
		$output.='<strong> курьером</strong>';
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

function inv_print ($inv_n) {

$output=substr($inv_n, 0, 3).'-'.substr($inv_n, 3);

return $output;

}


function phone_print ($ph) {
	if ($ph=='') {return '';}

	$dl=strlen($ph);

	if ($dl<7) {return $ph;}

	$dl>7 ? $dl_to=$dl-7 : $dl_to=0;
	$ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
	return $ph_out;

}

function status_print ($status) {
	
	switch ($status) {
		case 'for_cur':
			return 'к доставке';
		break;
		
		case 'delivered':
			return 'доставлено';
		break;
		
		default:
			return $status;
		break;
	}

}


function step_pr($step) {
	switch ($step) {
		case 'day':
			return 'дн.';
			break;

		case 'week':
			return 'нед.';
			break;

		case 'month':
			return 'мес.';
			break;

		default:
			return '-';
			break;
	}
}

function kassa ($pole, $kassa, $place, $sort_date) {
	$pole=='r_paid' ? $p_type='r_payment_type' : $p_type='del_payment_type';
	$place=='office' ? $pl_srch="AND delivery_yn!='1' " : $pl_srch="AND delivery_yn='1' ";

	global $item_place;
	
	if ($item_place=='all') {
		$place_srch='';
	}
	else {
		$place_srch=' AND place=\''.$item_place.'\'';
	}
	
	$query_r_of_nal_no_check1 = "SELECT SUM($pole) FROM rent_sub_deals_act WHERE `type` IN ('payment', 'cl_payment') AND $p_type='$kassa' ".$pl_srch."AND $sort_date".$place_srch;
	$result_r_of_nal_no_check1 = mysql_query($query_r_of_nal_no_check1);
	if (!$result_r_of_nal_no_check1) die("Сбой при доступе к базе данных: '$query_r_of_nal_no_check1'".mysql_error());
	$r_pay_act=mysql_fetch_array($result_r_of_nal_no_check1);

	$query_r_of_nal_no_check2 = "SELECT SUM($pole) FROM rent_sub_deals_arch WHERE `type` IN ('payment', 'cl_payment') AND $p_type='$kassa' ".$pl_srch."AND $sort_date".$place_srch;
	$result_r_of_nal_no_check2 = mysql_query($query_r_of_nal_no_check2);
	if (!$result_r_of_nal_no_check2) die("Сбой при доступе к базе данных: '$query_r_of_nal_no_check2'".mysql_error());
	$r_pay_arch=mysql_fetch_array($result_r_of_nal_no_check2);

	//echo $query_r_of_nal_no_check1.'<br />'.$query_r_of_nal_no_check2.'<br />';
	
	$r_of_no_ch=$r_pay_act['SUM('.$pole.')']+$r_pay_arch['SUM('.$pole.')'];

	return $r_of_no_ch;

}


function kassa_n_r ($pole, $kassa, $place, $sort_date, $deal_id_rash) {
	$pole=='r_paid' ? $p_type='r_payment_type' : $p_type='del_payment_type';
	$place=='office' ? $pl_srch="AND delivery_yn!='1' " : $pl_srch="AND delivery_yn='1' ";

	global $item_place;
	
	if ($item_place=='all') {
		$place_srch='';
	}
	else {
		$place_srch=' AND place=\''.$item_place.'\'';
	}
	
	$query_r_of_nal_no_check1 = "SELECT SUM($pole) FROM rent_sub_deals_act WHERE `type` IN ('payment', 'cl_payment') AND deal_id!='$deal_id_rash' AND $p_type='$kassa' ".$pl_srch."AND $sort_date".$place_srch;
	$result_r_of_nal_no_check1 = mysql_query($query_r_of_nal_no_check1);
	if (!$result_r_of_nal_no_check1) die("Сбой при доступе к базе данных: '$query_r_of_nal_no_check1'".mysql_error());
	$r_pay_act=mysql_fetch_array($result_r_of_nal_no_check1);

	$query_r_of_nal_no_check2 = "SELECT SUM($pole) FROM rent_sub_deals_arch WHERE `type` IN ('payment', 'cl_payment') AND deal_id!='$deal_id_rash' AND $p_type='$kassa' ".$pl_srch."AND $sort_date".$place_srch;
	$result_r_of_nal_no_check2 = mysql_query($query_r_of_nal_no_check2);
	if (!$result_r_of_nal_no_check2) die("Сбой при доступе к базе данных: '$query_r_of_nal_no_check2'".mysql_error());
	$r_pay_arch=mysql_fetch_array($result_r_of_nal_no_check2);

	//echo $query_r_of_nal_no_check1.'<br />'.$query_r_of_nal_no_check2.'<br />';

	$r_of_no_ch=$r_pay_act['SUM('.$pole.')']+$r_pay_arch['SUM('.$pole.')'];

	return $r_of_no_ch;

}

function kassa_rsh ($pole, $kassa, $place, $sort_date, $deal_id_rash) {
	$pole=='r_paid' ? $p_type='r_payment_type' : $p_type='del_payment_type';
	$place=='office' ? $pl_srch="AND delivery_yn!='1' " : $pl_srch="AND delivery_yn='1' ";
	
	global $item_place;
	
	if ($item_place=='all') {
		$place_srch='';
	}
	else {
		$place_srch=' AND place=\''.$item_place.'\'';
	}
	
	$query_r_of_nal_no_check1 = "SELECT SUM($pole) FROM rent_sub_deals_act WHERE `type` IN ('payment', 'cl_payment') AND deal_id='$deal_id_rash' AND $p_type='$kassa' ".$pl_srch."AND $sort_date".$place_srch;
	$result_r_of_nal_no_check1 = mysql_query($query_r_of_nal_no_check1);
	if (!$result_r_of_nal_no_check1) die("Сбой при доступе к базе данных: '$query_r_of_nal_no_check1'".mysql_error());
	$r_pay_act=mysql_fetch_array($result_r_of_nal_no_check1);

	$query_r_of_nal_no_check2 = "SELECT SUM($pole) FROM rent_sub_deals_arch WHERE `type` IN ('payment', 'cl_payment') AND deal_id='$deal_id_rash' AND $p_type='$kassa' ".$pl_srch."AND $sort_date".$place_srch;
	$result_r_of_nal_no_check2 = mysql_query($query_r_of_nal_no_check2);
	if (!$result_r_of_nal_no_check2) die("Сбой при доступе к базе данных: '$query_r_of_nal_no_check2'".mysql_error());
	$r_pay_arch=mysql_fetch_array($result_r_of_nal_no_check2);

	//echo $query_r_of_nal_no_check1.'<br />'.$query_r_of_nal_no_check2.'<br />';

	$r_of_no_ch=$r_pay_act['SUM('.$pole.')']+$r_pay_arch['SUM('.$pole.')'];

	return $r_of_no_ch;

}


function sh_kassa ($kassa) {
	switch ($kassa) {
		case 'nal_no_cheque':
			return 'к2';
		break;
		
		case 'nal_cheque':
			return 'к1';
		break;
		
		case 'card':
			return 'кт';
		break;
		
		case 'bank':
			return 'бк';
		break;
		
		case '':
		case '0':
		case 'no_payment':
			return '';
		break;
		
		default:
			return 'ХЗК';
		break;
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
		default:
			return 'ХЗ';
		break;
	}
}



function of_print ($of) {

	switch ($of) {
		case '1':
			$output='Оф1';
			break;

		case '2':
			$output='Оф2';
			break;

		default:
			$output='Нет';
			break;
	}

	return $output;

}



?>