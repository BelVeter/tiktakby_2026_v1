<?php
session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); // включаем подключение к базе данных
$mysqli = \bb\Db::getInstance()->getConnection();

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
	<a class="div_item" href="/bb/reports.php">Сводный отчет</a>
</div>


<form name="srch_form" method="post" id="srch_form" action="reports3.php">
	За период:
		c <input type="date" name="i_from_date" id="i_from_date" value="'.date("Y-m-d", $i_from_date).'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.date("Y-m-d", $i_to_date).'" /> <input type="submit" name="action" value="показать" onclick="" /><br />
</form>


<table border="1" cellspacing="0">
<tr>
	<th style="width:200px;">Показатель</th>
	<th style="width:60px;">офис 1</th>
	<th style="width:60px;">офис 2</th>
	<th style="width:60px;">курьер</th>
	<th style="width:60px; font-weight:bold;">итого</th>
</tr>

		';

$rash_ar=[];
//формируем список сделок-расходов
$query_item_r = "SELECT * FROM rent_deals_act WHERE item_inv_n='7401'";
$result_r = $mysqli->query($query_item_r);
if (!$result_r) {die('Сбой при доступе к базе данных: '.$query_item_r.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	while ($rash=$result_r->fetch_assoc()) {
		$rash_ar[]=$rash['deal_id'];
		//$count+=1;
	}
$query_item_ra = "SELECT * FROM rent_deals_arch WHERE item_inv_n='7401'";
$result_ra = $mysqli->query($query_item_ra);
if (!$result_ra) {die('Сбой при доступе к базе данных: '.$query_item_ra.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

	while ($rash_a=$result_ra->fetch_assoc()) {
		$rash_ar[]=$rash_a['deal_id'];
		//$count+=1;
	}


//формируем список сделок-карнавалов
	$query_item_carn = "SELECT * FROM rent_deals_act WHERE item_inv_n LIKE '702%'";
	$result_carn = $mysqli->query($query_item_carn);
	if (!$result_carn) {die('Сбой при доступе к базе данных: '.$query_item_carn.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	while ($carn=$result_carn->fetch_assoc()) {
		$carn_ar[]=$carn['deal_id'];
		//$count+=1;
	}
	$query_item_carn_a = "SELECT * FROM rent_deals_arch WHERE item_inv_n LIKE '702%'";
	$result_carn_a = $mysqli->query($query_item_carn_a);
	if (!$result_carn_a) {die('Сбой при доступе к базе данных: '.$query_item_carn_a.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

	while ($carn_a=$result_carn_a->fetch_assoc()) {
		$carn_ar[]=$carn_a['deal_id'];
		//$count+=1;
	}




//оффис 1
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='1' AND delivery_yn!='1'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of1=$of1_res['SUM(r_paid)'];

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='1' AND delivery_yn!='1'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of1+=$of1_res['SUM(r_paid)'];

//оффис 2
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='2' AND delivery_yn!='1'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of2=$of1_res['SUM(r_paid)'];

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='2' AND delivery_yn!='1'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of2+=$of1_res['SUM(r_paid)'];


//курьер
	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND delivery_yn='1'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$deliv=$of1_res['SUM(r_paid)'];

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND delivery_yn='1'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$deliv+=$of1_res['SUM(r_paid)'];


//оффис 1 банк
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='1' AND delivery_yn!='1' AND r_payment_type='bank'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of1_bank=$of1_res['SUM(r_paid)'];

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='1' AND delivery_yn!='1' AND r_payment_type='bank'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of1_bank+=$of1_res['SUM(r_paid)'];


//оффис 2 банк
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='2' AND delivery_yn!='1' AND r_payment_type='bank'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of2_bank=$of1_res['SUM(r_paid)'];

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='2' AND delivery_yn!='1' AND r_payment_type='bank'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of2_bank+=$of1_res['SUM(r_paid)'];






//оффис 1 карнавал
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id IN ('".implode("', '", $carn_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='1' AND delivery_yn!='1'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$carn_1=$of1_res['SUM(r_paid)'];

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id IN ('".implode("', '", $carn_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='1' AND delivery_yn!='1'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$carn_1+=$of1_res['SUM(r_paid)'];

//оффис 2 карнавал
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id IN ('".implode("', '", $carn_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='2' AND delivery_yn!='1'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$carn_2=$of1_res['SUM(r_paid)'];

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id IN ('".implode("', '", $carn_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='2' AND delivery_yn!='1'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$carn_2+=$of1_res['SUM(r_paid)'];


//курьер карнавал
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id IN ('".implode("', '", $carn_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND delivery_yn='1'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$carn_d=$of1_res['SUM(r_paid)'];

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id IN ('".implode("', '", $carn_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND delivery_yn='1'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$carn_d+=$of1_res['SUM(r_paid)'];






$query_of1 = "SELECT COUNT(`item_id`) FROM tovar_rent_items WHERE item_place='1' AND item_inv_n NOT LIKE '702%' AND `status` IN ('to_rent', 'rented_out', 'bron', 't_bron') AND cat_id!='49'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_num_1=$of1_res['COUNT(`item_id`)'];

$query_of1 = "SELECT COUNT(`item_id`) FROM tovar_rent_items WHERE item_place='2' AND item_inv_n NOT LIKE '702%' AND `status` IN ('to_rent', 'rented_out', 'bron', 't_bron')  AND cat_id!='49'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_num_2=$of1_res['COUNT(`item_id`)'];

$query_of1 = "SELECT COUNT(`item_id`) FROM tovar_rent_items WHERE item_place='1' AND item_inv_n NOT LIKE '702%' AND `status` IN ('to_rent', 't_bron') AND cat_id!='49'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_num_1_free=$of1_res['COUNT(`item_id`)'];

$query_of1 = "SELECT COUNT(`item_id`) FROM tovar_rent_items WHERE item_place='2' AND item_inv_n NOT LIKE '702%' AND `status` IN ('to_rent', 't_bron') AND cat_id!='49'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_num_2_free=$of1_res['COUNT(`item_id`)'];



//считаем сумму вложений
$query_of1 = "SELECT SUM(`buy_price`) FROM tovar_rent_items WHERE item_place='1' AND item_inv_n NOT LIKE '702%' AND `buy_price_cur`='USD'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_sum_1_usd=$of1_res['SUM(`buy_price`)'];

$query_of1 = "SELECT SUM(`buy_price`) FROM tovar_rent_items WHERE item_place='1' AND item_inv_n NOT LIKE '702%' AND `buy_price_cur`='TBYR'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_sum_1_usd_byr=$of1_res['SUM(`buy_price`)']/15000;

$query_of1 = "SELECT SUM(`buy_price`) FROM tovar_rent_items WHERE item_place='2' AND item_inv_n NOT LIKE '702%' AND `buy_price_cur`='USD'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_sum_2_usd=$of1_res['SUM(`buy_price`)'];

$query_of1 = "SELECT SUM(`buy_price`) FROM tovar_rent_items WHERE item_place='2' AND item_inv_n NOT LIKE '702%' AND `buy_price_cur`='TBYR'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_sum_2_usd_byr=$of1_res['SUM(`buy_price`)']/15000;


//считаем расходы всего
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='rash' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$r_rash=$of1_res['SUM(`amount`)'];

//считаем зарплату
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='rash' AND type2='zpl' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$r_zpl=$of1_res['SUM(`amount`)'];

//считаем дивиденды
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='rash' AND type2='dividends' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$r_div=$of1_res['SUM(`amount`)'];

//считаем покупку товаров
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='rash' AND type2='tovar' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$r_tovar=$of1_res['SUM(`amount`)'];


//считаем погашение кредитов
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='rash' AND type2='debt_rep' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$r_debt=$of1_res['SUM(`amount`)'];


//считаем возвраты авансов
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='doh' AND type2='av_return' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$av_return=$of1_res['SUM(`amount`)'];

//считаем прочие доходы
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='doh' AND type2!='av_return' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$other_sales=$of1_res['SUM(`amount`)'];

//считаем получение взносов учредителей
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='doh' AND type2='vznos_plus' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$vznos_plus=$of1_res['SUM(`amount`)'];

//считаем возвраты взносов учредителей
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='rash' AND type2='vznos_return' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$vznos_return=$of1_res['SUM(`amount`)'];

//считаем налоги на з\пл
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='rash' AND type2 IN ('fszn_tax', 'pod_tax', 'bgs_tax') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$zp_tax=$of1_res['SUM(`amount`)'];

//считаем налоги (единый)
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='rash' AND type2 IN ('ed_nal_tax') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$ed_nal_tax=$of1_res['SUM(`amount`)'];

//считаем инвестиции
$query_of1 = "SELECT SUM(`amount`) FROM doh_rash WHERE type1='rash' AND type2 IN ('invest') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$r_invest=$of1_res['SUM(`amount`)'];

$sales=($of1+$of2+$deliv);
$clear_rash=($r_rash-$sales*0.20-$r_tovar-$r_debt-$r_div+$av_return-$r_invest-$vznos_return);

echo'
<tr>
	<td>выручка вся (млн)</td>
	<td style="text-align:right">'.number_format($of1, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of2, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($deliv, 1, ',', ' ').'</td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($of1+$of2+$deliv), 1, ',', ' ').'</td>
</tr>

<tr style="text-align:right; font-style:italic">
	<td>в т.ч. банк</td>
	<td style="text-align:right">'.number_format($of1_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of2_bank, 1, ',', ' ').'</td>
	<td style="text-align:right">---</td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($of1_bank+$of2_bank), 1, ',', ' ').'</td>
</tr>
<tr>
	<td>доп. доходы (млн)</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($other_sales-$vznos_plus), 1, ',', ' ').'</td>
</tr>
<tr style="background-color:#FF0;">
	<td>итого доход (млн)</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($other_sales-$vznos_plus+$sales), 1, ',', ' ').'</td>
</tr>
<tr>
	<td>в т.ч. выручка карнавалы (млн)</td>
	<td style="text-align:right">'.number_format($carn_1, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($carn_2, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($carn_d, 1, ',', ' ').'</td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($carn_1+$carn_2+$carn_d), 1, ',', ' ').'</td>
</tr>

<tr>
	<td>товар - некостюм всего (шт)</td>
	<td style="text-align:right">'.number_format($tov_num_1, 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($tov_num_2, 0, ',', ' ').'</td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format(($tov_num_1+$tov_num_2), 0, ',', ' ').'</td>
</tr>

<tr>
	<td>товар - некостюм (usd)</td>
	<td style="text-align:right">'.number_format(($tov_sum_1_usd+$tov_sum_1_usd_byr), 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($tov_sum_2_usd+$tov_sum_2_usd_byr), 0, ',', ' ').'</td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format(($tov_sum_1_usd+$tov_sum_1_usd_byr+$tov_sum_2_usd+$tov_sum_2_usd_byr), 0, ',', ' ').'</td>
</tr>
<tr>
	<td>% выручки на вложения</td>
	<td style="text-align:right">'.number_format((($of1/16/($tov_sum_1_usd+$tov_sum_1_usd_byr))*100), 2, ',', ' ').'</td>
	<td style="text-align:right">'.number_format((($of2/16/($tov_sum_2_usd+$tov_sum_2_usd_byr))*100), 2, ',', ' ').'</td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format(($tov_sum_1_usd+$tov_sum_1_usd_byr+$tov_sum_2_usd+$tov_sum_2_usd_byr), 0, ',', ' ').'</td>
</tr>
<tr>
	<td>расходы (без кредитов, дивидендов, товаров+аморт):</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format($clear_rash, 1, ',', ' ').'</td>
</tr>
<tr>
	<td style="text-align:right; font-style:italic">в т.ч. зарплата:</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format($r_zpl, 1, ',', ' ').'</td>
</tr>
<tr>
	<td style="text-align:right; font-style:italic">в т.ч. налоги на зарплату:</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format($zp_tax, 1, ',', ' ').'</td>
</tr>
<tr style="text-align:right; font-style:italic">
	<td>в т.ч. амортизация (20%):</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format((-$sales*0.20), 1, ',', ' ').'</td>
</tr>
<tr style="text-align:right; font-style:italic">
	<td>в т.ч. единый налог:</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format($ed_nal_tax, 1, ',', ' ').'</td>
</tr>
<tr style="background-color:#FF0;">
	<td>Прибыль:</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format(($sales+$clear_rash+$other_sales-$vznos_plus), 1, ',', ' ').'</td>
</tr>
<tr>
	<td>взносы учредителей</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right; font-weight:bold;">'.number_format($vznos_plus, 1, ',', ' ').'</td>
</tr>
<tr>
	<td>Денежный поток (со взносами учр):</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format(($sales+$clear_rash+$other_sales+$sales*0.20), 1, ',', ' ').'</td>
</tr>

<tr style="text-align:right; font-style:italic">
	<td>покупка товаров:</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format($r_tovar, 1, ',', ' ').'</td>
</tr>
<tr style="text-align:right; font-style:italic">
	<td>вложения (инвестиции):</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format($r_invest, 1, ',', ' ').'</td>
</tr>
<tr style="text-align:right; font-style:italic">
	<td>погашение кредитов:</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format($r_debt, 1, ',', ' ').'</td>
</tr>
<tr style="text-align:right; font-style:italic">
	<td>возврат взносов учредителей:</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format($vznos_return, 1, ',', ' ').'</td>
</tr>

<tr style="text-align:right; font-style:italic">
	<td>дивиденды:</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format($r_div, 1, ',', ' ').'</td>
</tr>
<tr>
	<td>Свободный денежный поток:</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format(($sales+$other_sales+$clear_rash+$r_invest+$sales*0.20+$r_tovar+$r_debt+$r_div+$vznos_return), 1, ',', ' ').'</td>
</tr>


';


echo '</table>';







function dl_time ($office, $i_from_date, $i_to_date) {
	global $mysqli;
	$sbd_time=array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

	//формируем список сделок-расходов
	$query_ds = "SELECT * FROM rent_sub_deals_act WHERE place='$office' AND delivery_yn!='1' AND `type` IN ('first_rent', 'close') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
	$result_ds = $mysqli->query($query_ds);
	if (!$result_ds) {die('Сбой при доступе к базе данных: '.$query_ds.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	while ($sbd=$result_ds->fetch_assoc()) {
		$hour=date("G", $sbd['cr_time']);

		$sbd_time[$hour]+=1;
		//$count+=1;
	}

	$query_ds = "SELECT * FROM rent_sub_deals_arch WHERE place='$office' AND delivery_yn!='1' AND `type` IN ('first_rent', 'close') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
	$result_ds = $mysqli->query($query_ds);
	if (!$result_ds) {die('Сбой при доступе к базе данных: '.$query_ds.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	while ($sbd=$result_ds->fetch_assoc()) {
		$hour=date("G", $sbd['cr_time']);

		$sbd_time[$hour]+=1;
		//$count+=1;
	}


	return $sbd_time;

}















function get_post($var)
{
  $mysqli = \bb\Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
}


?>
