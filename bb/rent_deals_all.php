<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

set_time_limit(300);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

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

	<div class="top_menu">
		<a class="div_item" href="/bb/index.php">Залогиниться</a>
	</div>

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
	<a class="div_item" href="/bb/cur_page2.php">Страница курьера</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
	<a class="div_item" href="/bb/rent_orders.php">Брони</a>
	<a class="div_item" href="/bb/doh-rash.php">Расходы</a><br />
		<form method="post" action="/bb/kr_baza_new.php" style="display:inline-block;">
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


function rash_show() {
	//alert ('ok');
	if (document.getElementById('rash_table').style.display=="none") {
		document.getElementById('rash_table').style.display="";
	}
	else {
		document.getElementById('rash_table').style.display="none";
	}
}//end of dunction


function ch_num_close (chnid) {
	document.getElementById('ch_div_'+chnid).style.display="none";
	document.getElementById('ch_num_update').value="no";
	document.getElementById('ch_num_id').value="";
	document.getElementById('ch_num_value').value="";
}


function ch_num_show (chnid) {
	document.getElementById('ch_div_'+chnid).style.display="block";
	document.getElementById('ch_num_update').value="yes";
	document.getElementById('ch_num_id').value=chnid;

}

function ch_num_save (chnid) {
	document.getElementById('ch_num_value').value=document.getElementById('ch_num_new_'+chnid).value;
	document.getElementById('srch_form').submit();
}


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
$ostatki_ok='';
$ch_num_update='no';

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

if ($ch_num_update=='yes') {

	$q_arch_act = "SELECT * FROM rent_sub_deals_act WHERE sub_deal_id='$ch_num_id'";
	$result_aa = mysql_query($q_arch_act);
	if (!$result_aa) die("Сбой при доступе к базе данных: '$q_arch_act'".mysql_error());
	$aa_rows = mysql_num_rows($result_aa);

	if ($aa_rows>=1) {
		$srch_table='rent_sub_deals_act';
	}
	else {
		$srch_table='rent_sub_deals_arch';
	}


	$query_dl_upd = "UPDATE $srch_table SET ch_num='$ch_num_value' WHERE sub_deal_id='$ch_num_id'";
	if (!mysql_query($query_dl_upd, $db_server)) {
		echo "Сбой при вставке данных: '$query_dl_upd' <br />".mysql_error()."<br /><br />";
	}
}


if (isset($_POST['action'])) {

	if ($action=='сохранить остаток') {
		//вносим остаток по 1-й кассе
		$sub_query = "INSERT INTO kassas VALUES('', '$k_acc_date', '$k_office', 'k1', '$k1_start', '$k1_sales', '$k1_rash', '$k1_end', '".$_SESSION['user_id']."', '".time()."', 'final')";
		if (!mysql_query($sub_query, $db_server)) {
			$done="no"; echo "Сбой при вставке данных: '$sub_query' <br />".mysql_error()."<br /><br />";
		}

		//вносим остаток по 2-й кассе
		$sub_query = "INSERT INTO kassas VALUES('', '$k_acc_date', '$k_office', 'k2', '$k2_start', '$k2_sales', '$k2_rash', '$k2_end', '".$_SESSION['user_id']."', '".time()."', 'final')";
		if (!mysql_query($sub_query, $db_server)) {
			$done="no"; echo "Сбой при вставке данных: '$sub_query' <br />".mysql_error()."<br /><br />";
		}

		$ostatki_ok="<strong>Остаток успешно сохранен</strong>";

		$action='показать';
	}


	switch ($action) {

		case 'сегодня':

			$from_date=$to_date=strtotime(date("Y-m-d"));

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

			$from_date=$to_date=mktime(0, 0, 0, $today['mon'], ($today['mday']-1), $today['year']);

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
			$to_date=mktime(0, 0, 0, $today['mon'], ($today['mday']+1), ($today['year']+10));

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


//формируем перечень пользователей

$rd_lp = "SELECT * FROM logpass";
$result_lp = mysql_query($rd_lp);
if (!$result_lp) die("Сбой при доступе к базе данных: '$rd_lp'".mysql_error());


$lp_list='';

while ($lp_l=mysql_fetch_array($result_lp)) {
	$lp_list[$lp_l['logpass_id']]=$lp_l['lp_fio'];
}




$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
$result_cats = mysql_query($query_cats);
if (!$result_cats) die("Сбой при доступе к базе данных: '$query_cats'".mysql_error());

echo $ostatki_ok;

echo '
		<form name="srch_form" method="post" id="srch_form" action="rda.php">
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



				//получаем id сделки, на которой висят расходы
				$query_item_r = "SELECT * FROM tovar_rent_items WHERE item_inv_n='7401'";
				$result_item_r = mysql_query($query_item_r);
				if (!$result_item_r) die("Сбой при доступе к базе данных: '$query_item_r'".mysql_error());
				$item_r=mysql_fetch_array($result_item_r);
				$item_r_rows=mysql_num_rows($result_item_r);
				$r_dl_id=$item_r['active_deal_id'];

			}
			else {die ('<br /><br /><font size="4">Выберите период для начала работы.</font>');}




//новая таблица и расходы

$of_nal_no_ch_2=kassa_n_r('r_paid', 'nal_no_cheque','office', $sort_date, $r_dl_id);
$of_nal_ch_1=kassa_n_r('r_paid', 'nal_cheque','office', $sort_date, $r_dl_id);
$of_card2=kassa_n_r('r_paid', 'card','office', $sort_date, $r_dl_id);
$rash_k1=rash_sum('k1');
$rash_k2=rash_sum('k2');

$vchera=strtotime(date("Y-m-d"))-24*60*60;
$now_day=strtotime(date("Y-m-d"));
//echo date("Y-m-d", '1431464400').'<br />';
//echo $vchera;
//выбираем последний остаток по 1-й кассе
$query_k1 = "SELECT * FROM kassas WHERE `channel`='$item_place' AND kassa='k1' AND acc_date<$now_day ORDER BY cr_when DESC";
$result_k1 = mysql_query($query_k1);
if (!$result_k1) die("Сбой при доступе к базе данных: '$query_k1'".mysql_error());
$k1_start=mysql_fetch_array($result_k1);

//выбираем последний остаток по 2-й кассе
$query_k2 = "SELECT * FROM kassas WHERE `channel`='$item_place' AND kassa='k2' AND acc_date<$now_day ORDER BY cr_when DESC";
$result_k2 = mysql_query($query_k2);
if (!$result_k2) die("Сбой при доступе к базе данных: '$query_k2'".mysql_error());
$k2_start=mysql_fetch_array($result_k2);

//проверяем наличие сохраненного остатка за сегодня
$query_k_now = "SELECT * FROM kassas WHERE `channel`='$item_place' AND kassa='k1' AND acc_date=$now_day ORDER BY cr_when DESC";
$result_k1_now = mysql_query($query_k_now);
if (!$result_k1_now) die("Сбой при доступе к базе данных: '$query_k_now'".mysql_error());
$k_now_rows=mysql_num_rows($result_k1_now);;

//новая таблица
echo'

<table border="1" cellspacing="0" style="background-color:#AFDC7E; display:block; float:left; margin: 0 20px;" id="stats2">
<tr>
	<th></th>
	<th>Касса 1</th>
	<th>Касса 2</th>
	<th>Терминал</th>

</tr>
<tr>
	<td>Входящий остаток:</td>
	<td style="text-align:right">'.number_format(($k1_start['k_amount_end']), 2, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($k2_start['k_amount_end']), 2, ',', ' ').'</td>
	<td style="text-align:right">X</td>
</tr>
<tr>
	<td>Выручка:</td>
	<td style="text-align:right">'.number_format(($of_nal_ch_1), 2, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_nal_no_ch_2), 2, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($of_card2), 2, ',', ' ').'</td>
</tr>
<tr>
	<td><a href="#" onclick="rash_show(); return false;">Расход(-)\доход(+):</a></td>
	<td style="text-align:right">'.number_format($rash_k1, 2, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($rash_k2, 2, ',', ' ').'</td>
	<td style="text-align:right">X</td>
</tr>
<tr '.($k_now_rows>0 ? 'style="color:red"' : '').'>
	<td>Остаток, конец дня:</td>
	<td style="text-align:right">'.number_format(($k1_start['k_amount_end']+$rash_k1+$of_nal_ch_1), 2, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($k2_start['k_amount_end']+$rash_k2+$of_nal_no_ch_2), 2, ',', ' ').'</td>
	<td style="text-align:right">X</td>
</tr>

</table>

	<input form="srch_form" type="hidden" name="ch_num_update" id="ch_num_update" value="no" />
	<input form="srch_form" type="hidden" name="ch_num_id" id="ch_num_id" value="" />
	<input form="srch_form" type="hidden" name="ch_num_value" id="ch_num_value" value="" />

	<input form="srch_form" type="hidden" name="k_acc_date" value="'.strtotime(date("Y-m-d")).'" />
	<input form="srch_form" type="hidden" name="k_office" value="'.$item_place.'" />
	<input form="srch_form" type="hidden" name="k1_start" value="'.$k1_start['k_amount_end'].'" />
	<input form="srch_form" type="hidden" name="k2_start" value="'.$k2_start['k_amount_end'].'" />
	<input form="srch_form" type="hidden" name="k1_sales" value="'.$of_nal_ch_1.'" />
	<input form="srch_form" type="hidden" name="k2_sales" value="'.$of_nal_no_ch_2.'" />
	<input form="srch_form" type="hidden" name="k1_rash" value="'.$rash_k1.'" />
	<input form="srch_form" type="hidden" name="k2_rash" value="'.$rash_k2.'" />
	<input form="srch_form" type="hidden" name="k1_end" value="'.($k1_start['k_amount_end']+$rash_k1+$of_nal_ch_1).'" />
	<input form="srch_form" type="hidden" name="k2_end" value="'.($k2_start['k_amount_end']+$rash_k2+$of_nal_no_ch_2).'" />
	<input form="srch_form" type="submit" name="action" value="сохранить остаток" style="position:relative; top:85px;" />



';

//расходы
$rash["of1k1"]="Машерова_18_1";
$rash["of1k2"]="Машерова_18_2";
$rash["of2k1"]="Ложинская_1";
$rash["of2k2"]="Ложинская_2";
$rash["of3k1"]="Победителей_127_1";
$rash["of3k2"]="Победителей_127_2";
$rash["of4k1"]="Машерова_20_1";
$rash["of4k2"]="Машерова_20_2";
$rash["curk1"]="Курьер_1";
$rash["curk2"]="Курьер_2";
$rash["bank"]="Банк";

$doh=$rash;

//формируем перечень расходов
$ri_q = "SELECT * FROM rash_items WHERE bank_yn!=1 ORDER BY ri_order";
$result_ri = mysql_query($ri_q);
if (!$result_ri) die("Сбой при доступе к базе данных: '$ri_q'".mysql_error());

$ri_t1='';
while ($ri_def=mysql_fetch_array($result_ri)) {
	$ri_t1.='<option value="'.$ri_def['ri_code'].'">'.$ri_def['ri_text'].'</option>';
	$rash[$ri_def['ri_code']]=$ri_def['ri_text'];
}

$ri_q = "SELECT * FROM rash_items WHERE bank_yn=1 ORDER BY ri_order";
$result_ri = mysql_query($ri_q);
if (!$result_ri) die("Сбой при доступе к базе данных: '$ri_q'".mysql_error());

$ri_t2=$ri_t1;
while ($ri_def=mysql_fetch_array($result_ri)) {
	$ri_t2.='<option value="'.$ri_def['ri_code'].'">'.$ri_def['ri_text'].'</option>';
	$rash[$ri_def['ri_code']]=$ri_def['ri_text'];
}


//формируем перечень доходов
$rd_q = "SELECT * FROM doh_items WHERE bank_yn!=1 ORDER BY rd_order";
$result_rd = mysql_query($rd_q);
if (!$result_rd) die("Сбой при доступе к базе данных: '$rd_q'".mysql_error());

$rd_t1='';
while ($rd_def=mysql_fetch_array($result_rd)) {
	$rd_t1.='<option value="'.$rd_def['rd_code'].'">'.$rd_def['rd_text'].'</option>';
	$doh[$rd_def['rd_code']]=$rd_def['rd_text'];
}



//выборка информации по доходам-расходам
$dr_q = "SELECT * FROM doh_rash WHERE (acc_date BETWEEN '".$from_date."' AND '".$to_date."') AND `channel`='$item_place'";
$result_dr = mysql_query($dr_q);
if (!$result_dr) die("Сбой при доступе к базе данных: '$dr_q'".mysql_error());


//$item_r=mysql_fetch_array($result_item_r);
//$item_r_rows=mysql_num_rows($result_item_r);
//$r_dl_id=$item_r['active_deal_id'];





echo '
<table border="1" cellspacing="0" style="clear:both; display:none" id="rash_table">
	<tr>
		<th>дата</th>
		<th>касса</th>
		<th>сумма</th>
		<th>тип</td>
		<th>информация</td>
		<th>кто?</th>
	</tr>
';
while ($dr=mysql_fetch_array($result_dr)) {
	echo '
	<tr>
		<td>'.date("d.m.Y", $dr['acc_date']).'</td>
		<td>'.of_print2($dr['channel']).kassa_print2($dr['kassa']).'</td>
		<td>'.number_format($dr['amount'], 2, ',', ' ').'</td>
		<td>'.($dr['amount']<0 ? $rash[$dr['type2']] : $doh[$dr['type2']]).'</td>
		<td>'.$dr['info'].'</td>
		<td>'.$lp_list[$dr['cr_who_id']].' ('.date("H:i", $dr['cr_time']).')</td>
	</tr>
						';
}


echo '
</table>

';

//пошли сделки


echo'
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
			<th style="width:90px;">опл-о
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
						<option value="1" '.sel_d($item_place, '1').'>Маш-18</option>
						<option value="2" '.sel_d($item_place, '2').'>Ложинская</option>
						<option value="3" '.sel_d($item_place, '3').'>Поб-127</option>
						<option value="4" '.sel_d($item_place, '4').'>Маш-20</option>
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


	//запрос информации о первой выдаче
	$query_fr = "SELECT * FROM rent_sub_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."' AND (`type`='first_rent' OR `type`='takeaway_plan')";
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
	elseif ($fr['place']==3 && $fr['delivery_yn']!=1) {
		$fr_chanell='<img src="/images/3.gif" style="position:absolute; right:0; top:0; width:25px; heght:25px;" />';
	}
	elseif ($fr['delivery_yn']==1) {
		$fr_chanell='<img src="/images/k.png" style="position:absolute; right:0; top:0; width:25px; heght:25px;" />';
	}
	else {
		$fr_chanell='';
	}

	$r_paid=0;
	$del_paid=0;
	$r_p_ch='';
	$d_p_ch='';
	$multi_k='';
	$payment_output='';

	while ($sub_pay=mysql_fetch_array($result_sub_pay)) {

		//если включен фильтр платежей и нет соответствия - не обрабатываем платеж
		if ($payment_type!='all' AND $sub_pay['r_payment_type']!=$payment_type AND $sub_pay['del_payment_type']!=$payment_type) {
			$no_payment_filter='yes';
			continue;
		}


		if ($payment_type=='all' || $sub_pay['r_payment_type']==$payment_type) {
			$r_paid+=$sub_pay['r_paid'];
				//проверка на незаполнение чека
				if (($sub_pay['r_payment_type']=='nal_cheque' || $sub_pay['r_payment_type']=='card' || $sub_pay['r_payment_type']=='bank') && ($sub_pay['ch_num']=='')) {
					$no_ch='<span style="color:red;">!внесите!</span>';
				}
				else {
					$no_ch='';
				}


			$payment_output='<strong>'.number_format($sub_pay['r_paid'], 2, ',', ' ').sh_kassa($sub_pay['r_payment_type']).'</strong> <i class="ch_n"><a href="#" onclick="ch_num_show(\''.$sub_pay['sub_deal_id'].'\'); return false;">'.$sub_pay['ch_num'].$no_ch.'</a><div class="ch_div_st" id="ch_div_'.$sub_pay['sub_deal_id'].'"><input type="text" name="ch_num_new" id="ch_num_new_'.$sub_pay['sub_deal_id'].'" value="'.$sub_pay['ch_num'].'" /><input type="hidden" id="ch_num_id_'.$sub_pay['sub_deal_id'].'" value="'.$sub_pay['sub_deal_id'].'" /><br /><input type="button" value="обновить" onclick="ch_num_save(\''.$sub_pay['sub_deal_id'].'\');" /><input type="button" value="отмена" onclick="ch_num_close(\''.$sub_pay['sub_deal_id'].'\');" /></div></i><br />';//есть дубли этого кода ниже (в доставке и чистых платежах) для одной оплаты, это и останется, для мульти - выведем потом следующую строчку

			$multi_k.=$payment_output;//формируем список касс и номеров чеков для мультиоплаты
		}
		else {
			$r_paid+=0;
			//$r_p_ch=''; при формировании мульти чеков если все будет ок после фильтров - убрать эту строку
		}

		if ($payment_type=='all' || $sub_pay['del_payment_type']==$payment_type) {
			$del_paid+=$sub_pay['delivery_paid'];

				//проверка на незаполнение чека
				if (($sub_pay['r_payment_type']=='nal_cheque' || $sub_pay['r_payment_type']=='card' || $sub_pay['r_payment_type']=='bank') && ($sub_pay['ch_num']=='')) {
					$no_ch='<span style="color:red;">!внесите!</span>';
				}
				else {
					$no_ch='';
				}
			//  доставка всегда оплачивается по одному каналу
				$d_p_ch=sh_kassa($sub_pay['del_payment_type']).' <i class="ch_n"><a href="#" onclick="ch_num_show(\''.$sub_pay['sub_deal_id'].'d'.'\'); return false;">'.$sub_pay['ch_num'].$no_ch.'</a><div class="ch_div_st" id="ch_div_'.$sub_pay['sub_deal_id'].'d"><input type="text" name="ch_num_new" id="ch_num_new_'.$sub_pay['sub_deal_id'].'d" value="'.$sub_pay['ch_num'].'" /><input type="hidden" id="ch_num_id_'.$sub_pay['sub_deal_id'].'d" value="'.$sub_pay['sub_deal_id'].'" /><br /><input type="button" value="обновить" onclick="ch_num_save(\''.$sub_pay['sub_deal_id'].'d\');" /><input type="button" value="отмена" onclick="ch_num_close(\''.$sub_pay['sub_deal_id'].'d\');" /></div></i>';//добавляем номер чека !!!делаем корректировку на d для оставки, чтобы избежать одинаковых id полей

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
		$payment_output=$multi_k;//замена последнего платежа получившегося по алгоритму выше, на перечень всех платежей в томже алгоритме. А доставка всегда одним каналом!!!
		//$d_p_ch='>1';
	}

	}//end of non_payment if
	else {//а тут мы обрабатываем "чистые платежи"

		if ($payment_type=='all' || $sub_dl_def['r_payment_type']==$payment_type) {
			$r_paid=$sub_dl_def['r_paid'];

			//проверка на незаполнение чека
			if (($sub_dl_def['r_payment_type']=='nal_cheque' || $sub_dl_def['r_payment_type']=='card' || $sub_dl_def['r_payment_type']=='bank') && ($sub_dl_def['ch_num']=='')) {
				$sub_dl_def['ch_num']='<span style="color:red;">!внесите!</span>';
			}

			$payment_output='<strong>'.number_format($r_paid, 2, ',', ' ').sh_kassa($sub_dl_def['r_payment_type']).'</strong><i class="ch_n"><a href="#" onclick="ch_num_show(\''.$sub_dl_def['sub_deal_id'].'\'); return false;">'.$sub_dl_def['ch_num'].'</a><div class="ch_div_st" id="ch_div_'.$sub_dl_def['sub_deal_id'].'"><input type="text" name="ch_num_new" id="ch_num_new_'.$sub_dl_def['sub_deal_id'].'" value="'.$sub_dl_def['ch_num'].'" /><input type="hidden" id="ch_num_id_'.$sub_dl_def['sub_deal_id'].'" value="'.$sub_dl_def['sub_deal_id'].'" /><br /><input type="button" value="обновить" onclick="ch_num_save(\''.$sub_dl_def['sub_deal_id'].'\');" /><input type="button" value="отмена" onclick="ch_num_close(\''.$sub_dl_def['sub_deal_id'].'\');" /></div></i>';

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
			elseif ((($r_paid+$del_paid)-($sub_dl_def['r_to_pay']+$sub_dl_def['delivery_to_pay']))<=-2) {

				echo 'style="background-color:#F5C138"';
			}

	//устанавливаем цвета для имен учета сделок
	switch ($dl_def['acc_person_id']) {
		case '4':
			$acc_p_class='acc_pers_4';
		break;

		case '12':
			$acc_p_class='acc_pers_12';
		break;

		default:
			$acc_p_class='def_acc_pers';
		break;
	}



	echo'	>'.($_SESSION['user_id']==3 ? '<td>'.$sub_dl_def['deal_id'].'<br />'.$sub_dl_def['sub_deal_id'].'</td>' : '').
			'<td '.((($sub_dl_def['cr_time']-$sub_dl_def['acc_date'])>(24*60*60) && ($_SESSION['user_id']<4 || $_SESSION['user_id']==5) && $sub_dl_def['cr_who_id']!=9) ? 'style="background-color:red;" ' : '').'>'.date("d.m.y", $sub_dl_def['acc_date']).'<br /><i>('.(($_SESSION['user_id']<4 || $_SESSION['user_id']==5) ? date("d.m.y", $sub_dl_def['cr_time']) : '').'-'.date("H:i", $sub_dl_def['cr_time']).')</i><br />д№'.$sub_dl_def['deal_id'].'</td>
			<td>'.op_print($sub_dl_def['type'], $sub_dl_def['delivery_yn']).
                ($sub_dl_def['acc_date']==$sub_dl_def['from'] ? '' : ' c '.date("d.m.y", $sub_dl_def['from'])).
        ($sub_dl_def['type']!='return' && $sub_dl_def['type']!='close' ? '<br /><i> на '.number_format($sub_dl_def['rent_tenor'], 0, ',', ' ').' '.step_pr($sub_dl_def['tarif_step']).'</i>' : '').'</td>
			<td style="position:relative;"> <strong>№ '.inv_print($dl_def['item_inv_n']).$fr_chanell.'</strong><br />'.$cat_def['dog_name'].' '.$model_def['model'].', '.$model_def['producer'].$color.'</td>
			<td>'.date("d.m.y", $dl_def['start_date']).' - '.date("d.m.y", $dl_def['return_date']).$ret_d_text.'</td>
			<td style="text-align:right; '.$dolg_color.'">'.number_format($sub_dl_def['r_to_pay'], 2, ',', ' ').($sub_dl_def['delivery_to_pay']!=0 ? '<br /><span class="deliv_num">'.number_format($sub_dl_def['delivery_to_pay'], 2, ',', ' ').'</span>' : '').'</td>
			<td style="text-align:right"> '.($dl_def['acc_person_id'] > 0 ? '<span class="'.$acc_p_class.'">'.$lp_list[$dl_def['acc_person_id']].'</span><br />' : '').$payment_output.($del_paid!=0 ? '<span class="deliv_num"><strong>'.number_format($del_paid, 2, ',', ' ').$d_p_ch.'</span></strong>' : '</strong>').'</td>
			<td>'.of_print($sub_dl_def['place']).'</td>
			<td>'.$cl_def['family'].' '.$cl_def['name'].' '.$cl_def['otch'].', '.$cl_def['city'].', ул. '.$cl_def['str'].', '.$cl_def['dom'].'-'.$cl_def['kv'].', тел.: '.phone_print($cl_def['phone_1']).', '.phone_print($cl_def['phone_2']).'</td>
			<td>'.$lp_list[$sub_dl_def['cr_who_id']].'<br />'.$sub_dl_def['info'].'</td>
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
		<form method="post" action="cur_page2.php">
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

/* подсчет скриптом количества сделок и причитающихся сумм
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

*/

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


function rash_sum ($kassa) {

	global $from_date;
	global $to_date;
	global $item_place;

	if ($item_place=='all')
	{
		$dr_s = "SELECT SUM(amount) FROM doh_rash WHERE (acc_date BETWEEN '".$from_date."' AND '".$to_date."') AND kassa='$kassa'";
	}
	else {
		$dr_s = "SELECT SUM(amount) FROM doh_rash WHERE (acc_date BETWEEN '".$from_date."' AND '".$to_date."') AND `channel`='$item_place' AND kassa='$kassa'";
	}

	$result_drs = mysql_query($dr_s);
	if (!$result_drs) die("Сбой при доступе к базе данных: '$dr_s'".mysql_error());
	$drs=mysql_fetch_array($result_drs);

	return $drs['SUM(amount)'];

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
		case '13':
			return 'Татьяна';
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

		case '3':
			$output='Оф3';
			break;

		case '4':
			$output='Оф4';
			break;

		default:
			$output='Нет';
			break;
	}

	return $output;

}


function of_print2 ($of) {

	switch ($of) {
		case '1':
			$output='Машерова_18_';
			break;

		case '2':
			$output='Ложинская_';
			break;

		case '3':
			$output='Победителей_';
			break;
		case '4':
			$output='Машерова_20_';
			break;

		case 'cur':
			$output='Курьер_';
			break;

		case 'bank':
			$output='Банк';
			break;


		default:
			$output='Нет';
			break;
	}

	return $output;

}

function kassa_print2 ($of) {

	switch ($of) {
		case 'k1':
			$output='1';
			break;

		case 'k2':
			$output='2';
			break;

		case 'bank':
			$output='';
			break;


		default:
			$output='Нет';
			break;
	}

	return $output;

}


?>
