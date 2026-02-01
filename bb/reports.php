<?php
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubDeal.php'); //

$mysqli=\bb\Db::getInstance()->getConnection();

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
$rash_ar[]=0;


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

$fromDate = new DateTime($i_from_date);
  $fromDate->setTime(0,0,0);
$toDate = new DateTime($i_to_date);
  $toDate->setTime(23,59,59);

$i_from_date=strtotime($i_from_date);
$i_to_date=strtotime($i_to_date);


echo '
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/reports3.php">Расчет прибыли и денежных потоков</a>
</div>


<form name="srch_form" method="post" id="srch_form" action="reports.php">
	За период:
		c <input type="date" name="i_from_date" id="i_from_date" value="'.date("Y-m-d", $i_from_date).'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.date("Y-m-d", $i_to_date).'" /> <input type="submit" name="action" value="показать" onclick="" /><br />
</form>


<table border="1" cellspacing="0">
<tr>
	<th style="width:200px;">Показатель</th>
	<th style="width:60px;">офис 1</th>
	<th style="width:60px;">офис 2</th>
	<th style="width:60px;">офис 3</th>
	<th style="width:60px;">курьер</th>
	<th style="width:60px; font-weight:bold;">итого</th>
</tr>

		';

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
//	$query_item_carn = "SELECT * FROM rent_deals_act WHERE item_inv_n LIKE '702%' OR item_inv_n LIKE '761%'";
//	$result_carn = $mysqli->query($query_item_carn);
//	if (!$result_carn) {die('Сбой при доступе к базе данных: '.$query_item_carn.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//	while ($carn=$result_carn->fetch_assoc()) {
//		$carn_ar[]=$carn['deal_id'];
//		//$count+=1;
//	}
//	$query_item_carn_a = "SELECT COUNT(rent_sub_deals_arch.r_paid), rent_sub_deals_arch.place AS num FROM rent_sub_deals_arch
//                            LEFT JOIN rent_deals_arch ON rent_deals_arch.deal_id=rent_sub_deals_arch.deal_id
//                            WHERE rent_deals_arch.item_inv_n LIKE '702%' OR item_inv_n LIKE '761%'
//	                        GROUP BY rent_sub_deals_arch.r_paid";
//	$result_carn_a = $mysqli->query($query_item_carn_a);
//	if (!$result_carn_a) {die('Сбой при доступе к базе данных: '.$query_item_carn_a.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//
//	while ($carn_a=$result_carn_a->fetch_assoc()) {
//		$carn_ar[]=$carn_a['deal_id'];
//		//$count+=1;
//	}

//считаем суммы карнавалов
$k_r_paid = array('0'=>0,'1'=>0,'2'=>0,'3'=>0, );
//archive
$query_k="
        SELECT rent_sub_deals_arch.place, SUM(rent_sub_deals_arch.r_paid) AS r_paid_sum FROM rent_sub_deals_arch
        LEFT JOIN rent_deals_arch ON rent_sub_deals_arch.deal_id = rent_deals_arch.deal_id
        WHERE
            (rent_deals_arch.item_inv_n LIKE('702%') OR rent_deals_arch.item_inv_n LIKE('761%') OR rent_deals_arch.item_inv_n LIKE('766%'))
            AND (rent_sub_deals_arch.acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')
        GROUP BY rent_sub_deals_arch.place
";
//echo $query_k;
$result_k_ar = $mysqli->query($query_k);
if (!$result_k_ar) {die('Сбой при доступе к базе данных: '.$query_k.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

while ($k_rez=$result_k_ar->fetch_assoc()) {
    $k_r_paid[$k_rez['place']]+=$k_rez['r_paid_sum'];
}
//actual
$query_k2="
        SELECT rent_sub_deals_act.place, SUM(rent_sub_deals_act.r_paid) AS r_paid_sum FROM rent_sub_deals_act
        LEFT JOIN rent_deals_act ON rent_sub_deals_act.deal_id = rent_deals_act.deal_id
        WHERE
            (rent_deals_act.item_inv_n LIKE('702%') OR rent_deals_act.item_inv_n LIKE('761%') OR rent_deals_act.item_inv_n LIKE('766%'))
            AND (rent_sub_deals_act.acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')
        GROUP BY rent_sub_deals_act.place
";
$result_k_ar2 = $mysqli->query($query_k2);
if (!$result_k_ar2) {die('Сбой при доступе к базе данных: '.$query_k2.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

while ($k_rez2=$result_k_ar2->fetch_assoc()) {
    $k_r_paid[$k_rez2['place']]+=$k_rez2['r_paid_sum'];
}


//считаем сделки по карнавалам
//считаем суммы карнавалов
$k_num = array('0'=>0,'1'=>0,'2'=>0,'3'=>0, );
//archive
$query_k_num="
        SELECT rent_sub_deals_arch.place, COUNT(rent_sub_deals_arch.deal_id) AS rent_num FROM rent_sub_deals_arch
        LEFT JOIN rent_deals_arch ON rent_sub_deals_arch.deal_id = rent_deals_arch.deal_id
        WHERE
            (rent_deals_arch.item_inv_n LIKE('702%') OR rent_deals_arch.item_inv_n LIKE('761%') OR rent_deals_arch.item_inv_n LIKE('766%'))
            AND (rent_sub_deals_arch.acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')
            AND (rent_sub_deals_arch.type IN ('first_rent', 'takeaway_plan'))
        GROUP BY rent_sub_deals_arch.place
";
//echo $query_k;
$result_k_num_ar = $mysqli->query($query_k_num);
if (!$result_k_num_ar) {die('Сбой при доступе к базе данных: '.$query_k_num.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

while ($k_rez=$result_k_num_ar->fetch_assoc()) {
    $k_num[$k_rez['place']]+=$k_rez['rent_num'];
}
//actual
$query_k2="
        SELECT rent_sub_deals_act.place, COUNT(rent_sub_deals_act.deal_id) AS rent_num FROM rent_sub_deals_act
        LEFT JOIN rent_deals_act ON rent_sub_deals_act.deal_id = rent_deals_act.deal_id
        WHERE
            (rent_deals_act.item_inv_n LIKE('702%') OR rent_deals_act.item_inv_n LIKE('761%') OR rent_deals_act.item_inv_n LIKE('766%'))
            AND (rent_sub_deals_act.acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')
            AND (rent_sub_deals_act.type IN ('first_rent', 'takeaway_plan'))
        GROUP BY rent_sub_deals_act.place
";
$result_k_ar2 = $mysqli->query($query_k2);
if (!$result_k_ar2) {die('Сбой при доступе к базе данных: '.$query_k2.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

while ($k_rez2=$result_k_ar2->fetch_assoc()) {
    $k_num[$k_rez2['place']]+=$k_rez2['rent_num'];
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

//оффис 2
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='3' AND delivery_yn!='1'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of3=$of1_res['SUM(r_paid)'];

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='3' AND delivery_yn!='1'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of3+=$of1_res['SUM(r_paid)'];

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


//курьер за доставку
	$query_of1 = "SELECT SUM(delivery_paid) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND delivery_yn='1'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$delivery_paid=$of1_res['SUM(delivery_paid)'];

	$query_of1 = "SELECT SUM(delivery_paid) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND delivery_yn='1'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$delivery_paid+=$of1_res['SUM(delivery_paid)'];




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

//оффис 2 банк
$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='3' AND delivery_yn!='1' AND r_payment_type='bank'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of3_bank=$of1_res['SUM(r_paid)'];

	$query_of1 = "SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='3' AND delivery_yn!='1' AND r_payment_type='bank'";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of3_bank+=$of1_res['SUM(r_paid)'];




//оффис 1 выдачи
$query_of1 = "SELECT COUNT(deal_id) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='1' AND delivery_yn!='1' AND `type` IN ('first_rent', 'takeaway_plan')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of1_num=$of1_res['COUNT(deal_id)'];

	$query_of1 = "SELECT COUNT(deal_id) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='1' AND delivery_yn!='1' AND `type` IN ('first_rent', 'takeaway_plan')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of1_num+=$of1_res['COUNT(deal_id)'];

//оффис 2 выдачи
$query_of1 = "SELECT COUNT(deal_id) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='2' AND delivery_yn!='1' AND `type` IN ('first_rent', 'takeaway_plan')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of2_num=$of1_res['COUNT(deal_id)'];

	$query_of1 = "SELECT COUNT(deal_id) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='2' AND delivery_yn!='1' AND `type` IN ('first_rent', 'takeaway_plan')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of2_num+=$of1_res['COUNT(deal_id)'];

//оффис 3 выдачи
$query_of1 = "SELECT COUNT(deal_id) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='3' AND delivery_yn!='1' AND `type` IN ('first_rent', 'takeaway_plan')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$of3_num=$of1_res['COUNT(deal_id)'];

	$query_of1 = "SELECT COUNT(deal_id) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND place='3' AND delivery_yn!='1' AND `type` IN ('first_rent', 'takeaway_plan')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$of3_num+=$of1_res['COUNT(deal_id)'];

//курьер выдачи
$query_of1 = "SELECT COUNT(deal_id) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND delivery_yn='1' AND `type` IN ('first_rent', 'takeaway_plan')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$del_num=$of1_res['COUNT(deal_id)'];

	$query_of1 = "SELECT COUNT(deal_id) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND delivery_yn='1' AND `type` IN ('first_rent', 'takeaway_plan')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$del_num+=$of1_res['COUNT(deal_id)'];





//все выдачи - начисленная сумма
$query_of1 = "SELECT SUM(r_to_pay) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('first_rent', 'takeaway_plan')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$fr_sum=$of1_res['SUM(r_to_pay)'];

	$query_of1 = "SELECT SUM(r_to_pay) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('first_rent', 'takeaway_plan')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$fr_sum+=$of1_res['SUM(r_to_pay)'];



$query_of1 = "SELECT SUM(`from`) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('first_rent', 'takeaway_plan')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$from_sec_act=$of1_res['SUM(`from`)'];

	$query_of1 = "SELECT SUM(`from`) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('first_rent', 'takeaway_plan')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$from_sec_arch=$of1_res['SUM(`from`)'];

$from_sec=$from_sec_act+$from_sec_arch;

$query_of1 = "SELECT SUM(`to`) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('first_rent', 'takeaway_plan')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$to_sec_act=$of1_res['SUM(`to`)'];

	$query_of1 = "SELECT SUM(`to`) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('first_rent', 'takeaway_plan')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$to_sec_arch=$of1_res['SUM(`to`)'];

$to_sec=$to_sec_act+$to_sec_arch;
$days_fr=($to_sec-$from_sec)/(24*60*60);

$query_of1 = "SELECT COUNT(`to`) FROM rent_sub_deals_act WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('first_rent', 'takeaway_plan')";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$new_frs_num_act=$of1_res['COUNT(`to`)'];

	$query_of1 = "SELECT COUNT(`to`) FROM rent_sub_deals_arch WHERE deal_id NOT IN ('".implode("', '", $rash_ar)."') AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('first_rent', 'takeaway_plan')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$new_frs_num_arch=$of1_res['COUNT(`to`)'];

$new_frs_num=$new_frs_num_act+$new_frs_num_arch;

$query_of1 = "SELECT COUNT(`item_id`) FROM tovar_rent_items WHERE item_place='1' AND item_inv_n NOT LIKE '702%' AND item_inv_n NOT LIKE '761%' AND `status` IN ('to_rent', 'rented_out', 'bron', 't_bron') AND cat_id!='49'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_num_1=$of1_res['COUNT(`item_id`)'];

$query_of1 = "SELECT COUNT(`item_id`) FROM tovar_rent_items WHERE item_place='2' AND item_inv_n NOT LIKE '702%' AND item_inv_n NOT LIKE '761%' AND `status` IN ('to_rent', 'rented_out', 'bron', 't_bron')  AND cat_id!='49'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_num_2=$of1_res['COUNT(`item_id`)'];

$query_of1 = "SELECT COUNT(`item_id`) FROM tovar_rent_items WHERE item_place='3' AND item_inv_n NOT LIKE '702%' AND item_inv_n NOT LIKE '761%' AND `status` IN ('to_rent', 'rented_out', 'bron', 't_bron')  AND cat_id!='49'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_num_3=$of1_res['COUNT(`item_id`)'];



$query_of1 = "SELECT COUNT(`item_id`) FROM tovar_rent_items WHERE item_place='1' AND item_inv_n NOT LIKE '702%' AND item_inv_n NOT LIKE '761%' AND `status` IN ('to_rent', 't_bron') AND cat_id!='49'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_num_1_free=$of1_res['COUNT(`item_id`)'];

$query_of1 = "SELECT COUNT(`item_id`) FROM tovar_rent_items WHERE item_place='2' AND item_inv_n NOT LIKE '702%' AND item_inv_n NOT LIKE '761%' AND `status` IN ('to_rent', 't_bron') AND cat_id!='49'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_num_2_free=$of1_res['COUNT(`item_id`)'];

$query_of1 = "SELECT COUNT(`item_id`) FROM tovar_rent_items WHERE item_place='3' AND item_inv_n NOT LIKE '702%' AND item_inv_n NOT LIKE '761%' AND `status` IN ('to_rent', 't_bron') AND cat_id!='49'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_num_3_free=$of1_res['COUNT(`item_id`)'];



//считаем сумму вложений
$query_of1 = "SELECT SUM(`buy_price`) FROM tovar_rent_items WHERE item_place='1' AND item_inv_n NOT LIKE '702%' AND item_inv_n NOT LIKE '761%' AND `buy_price_cur`='USD'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_sum_1_usd=$of1_res['SUM(`buy_price`)'];

$query_of1 = "SELECT SUM(`buy_price`) FROM tovar_rent_items WHERE item_place='1' AND item_inv_n NOT LIKE '702%' AND item_inv_n NOT LIKE '761%' AND `buy_price_cur`='TBYR'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_sum_1_usd_byr=$of1_res['SUM(`buy_price`)']/15;

$query_of1 = "SELECT SUM(`buy_price`) FROM tovar_rent_items WHERE item_place='2' AND item_inv_n NOT LIKE '702%' AND item_inv_n NOT LIKE '761%' AND `buy_price_cur`='USD'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_sum_2_usd=$of1_res['SUM(`buy_price`)'];

$query_of1 = "SELECT SUM(`buy_price`) FROM tovar_rent_items WHERE item_place='2' AND item_inv_n NOT LIKE '702%' AND item_inv_n NOT LIKE '761%' AND `buy_price_cur`='TBYR'";
$result_of1 = $mysqli->query($query_of1);
if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$result_of1->fetch_assoc();
$tov_sum_2_usd_byr=$of1_res['SUM(`buy_price`)']/15;


/*
//считаем результативность сотрудников
$query_sotr = "SELECT max(`cr_who_id`), sum(`r_paid`) FROM `rent_sub_deals_act` WHERE (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('payment', 'cl_payment') GROUP BY `cr_who_id`";
$result_sotr = $mysqli->query($query_sotr);
if (!$result_sotr) {die('Сбой при доступе к базе данных: '.$query_sotr.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

	$query_sotr_a = "SELECT max(`cr_who_id`), sum(`r_paid`) FROM `rent_sub_deals_arch` WHERE (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('payment', 'cl_payment') GROUP BY `cr_who_id`";
	$result_sotr_a = $mysqli->query($query_sotr_a);
	if (!$result_sotr_a) {die('Сбой при доступе к базе данных: '.$query_sotr_a.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}



	//формируем перечень пользователей

	$rd_lp = "SELECT * FROM logpass";

	$result_lp = $mysqli->query($rd_lp);

	if (!$result_lp) {die('Сбой при доступе к базе данных: '.$rd_lp.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}



	$lp_list='';

	while ($lp_l=$result_lp->fetch_assoc()) {

		$lp_list[$lp_l['logpass_id']]=$lp_l['lp_fio'];

	}

*/

	//считаем кол-во веб броней SELECT * FROM `rent_orders_arch` where cr_ip IN (SELECT off_ip from offices)
	$query_of1 = "SELECT COUNT(`order_id`) FROM rent_orders_arch WHERE `type2`='bron' AND web='1' AND (cr_time BETWEEN '".$i_from_date."' AND '".($i_to_date+24*60*60-1)."')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$web_br_num_arch=$of1_res['COUNT(`order_id`)'];

	$query_of1 = "SELECT COUNT(`order_id`) FROM rent_orders WHERE `type2`='bron' AND web='1' AND (cr_time BETWEEN '".$i_from_date."' AND '".($i_to_date+24*60*60-1)."')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$web_br_num=$of1_res['COUNT(`order_id`)'];

	//считаем кол-во СВОИХ веб броней
	$query_of1 = "SELECT COUNT(`order_id`) FROM rent_orders_arch WHERE `type2`='bron' AND web='1' AND (cr_time BETWEEN '".$i_from_date."' AND '".($i_to_date+24*60*60-1)."') AND cr_ip IN (SELECT off_ip from offices)";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$web_br_num_arch_svoi=$of1_res['COUNT(`order_id`)'];

	$query_of1 = "SELECT COUNT(`order_id`) FROM rent_orders WHERE `type2`='bron' AND web='1' AND (cr_time BETWEEN '".$i_from_date."' AND '".($i_to_date+24*60*60-1)."') AND cr_ip IN (SELECT off_ip from offices)";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$web_br_num_svoi=$of1_res['COUNT(`order_id`)'];

	//считаем кол-во веб заявок
	$query_of1 = "SELECT COUNT(`order_id`) FROM rent_orders_arch WHERE `type2`='zayavka' AND web='1' AND (cr_time BETWEEN '".$i_from_date."' AND '".($i_to_date+24*60*60-1)."')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$web_zayav_num_arch=$of1_res['COUNT(`order_id`)'];

	$query_of1 = "SELECT COUNT(`order_id`) FROM rent_orders WHERE `type2`='zayavka' AND web='1' AND (cr_time BETWEEN '".$i_from_date."' AND '".($i_to_date+24*60*60-1)."')";
	$result_of1 = $mysqli->query($query_of1);
	if (!$result_of1) {die('Сбой при доступе к базе данных: '.$query_of1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$of1_res=$result_of1->fetch_assoc();
	$web_zayav_num=$of1_res['COUNT(`order_id`)'];




	$query_cl="SELECT `source`, COUNT(client_id) AS cl_num FROM `clients`
                WHERE cr_time BETWEEN '".$i_from_date."' AND '".($i_to_date+24*60*60-1)."'
                GROUP BY `source`";
    $result_cl = $mysqli->query($query_cl);
    if (!$result_cl) {die('Сбой при доступе к базе данных: '.$query_cl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
    $cl_text='';
    while ($cls=$result_cl->fetch_assoc()) {
        if ($cls['source']=='') continue;
        $cl_text.=$cls['source'].': '.$cls['cl_num'].'<br>';
    }



//считаем результативность сотрудников по первой сделке
/*
$query_fd_p = "SELECT * FROM `rent_sub_deals_act` WHERE (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('first_rent')";
$result_fd = $mysqli->query($query_fd_p);
if (!$result_fd) {die('Сбой при доступе к базе данных: '.$query_fd_p.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

$fd_act=array();


	while ($fd=$result_fd->fetch_assoc()) {
		//считаем оплаты по сделке
		$query_p = "SELECT SUM(`r_paid`) FROM `rent_sub_deals_act` WHERE deal_id='".$fd['deal_id']."' AND `type` IN ('payment', 'cl_payment')";
		$result_p = $mysqli->query($query_p);
		if (!$result_p) {die('Сбой при доступе к базе данных: '.$query_p.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

		$fd_p=$result_p->fetch_assoc();
		//print_r($fd_p);

		if (!array_key_exists($fd['cr_who_id'], $fd_act)) {
			$fd_act[$fd['cr_who_id']]=0;
		}

		$fd_act[$fd['cr_who_id']]+=$fd_p['SUM(`r_paid`)'];
	}


	$query_fd_p = "SELECT * FROM `rent_sub_deals_arch` WHERE (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND `type` IN ('first_rent')";
	$result_fd = $mysqli->query($query_fd_p);
	if (!$result_fd) {die('Сбой при доступе к базе данных: '.$query_fd_p.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

	while ($fd=$result_fd->fetch_assoc()) {
		//считаем оплаты по сделке
		$query_p = "SELECT SUM(`r_paid`) FROM `rent_sub_deals_arch` WHERE deal_id='".$fd['deal_id']."' AND `type` IN ('payment', 'cl_payment')";
		$result_p = $mysqli->query($query_p);
		if (!$result_p) {die('Сбой при доступе к базе данных: '.$query_p.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

		$fd_p=$result_p->fetch_assoc();
		//print_r($fd_p);

		if (!array_key_exists($fd['cr_who_id'], $fd_act)) {
			$fd_act[$fd['cr_who_id']]=0;
		}

		$fd_act[$fd['cr_who_id']]+=$fd_p['SUM(`r_paid`)'];
	}

*/


	//оффис 1
	$eliz_tov = "SELECT item_inv_n FROM tovar_rent_items WHERE seller='elizavetka.by'";
	$result_tov = $mysqli->query($eliz_tov);
	$el_invs='';
	if (!$result_tov) {die('Сбой при доступе к базе данных: '.$eliz_tov.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

		$el_num=$result_tov->num_rows;
		$num_act_num=0;
	while ($el_tov = $result_tov->fetch_assoc()) {
		$num_act_num++;
		if ($el_num==$num_act_num) {
			$el_invs.=$el_tov['item_inv_n'];
		}
		else {
			$el_invs.=$el_tov['item_inv_n'].', ';
		}
	}
	if ($el_num<1) {
		$el_invs='0';
	}



	$dl_q="SELECT SUM(rent_sub_deals_act.r_paid) FROM rent_deals_act LEFT JOIN rent_sub_deals_act ON (rent_deals_act.deal_id = rent_sub_deals_act.deal_id) WHERE rent_deals_act.item_inv_n IN ($el_invs) AND (rent_sub_deals_act.acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
	$result_dl = $mysqli->query($dl_q);
	if (!$result_dl) {die('Сбой при доступе к базе данных: '.$dl_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$dls=$result_dl->fetch_assoc();
	$dls_num=$result_tov->num_rows;
	$elez_sum=$dls['SUM(rent_sub_deals_act.r_paid)'];

		$dl_q="SELECT SUM(rent_sub_deals_arch.r_paid) FROM rent_deals_arch LEFT JOIN rent_sub_deals_arch ON (rent_deals_arch.deal_id = rent_sub_deals_arch.deal_id) WHERE rent_deals_arch.item_inv_n IN ($el_invs) AND (rent_sub_deals_arch.acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
		$result_dl = $mysqli->query($dl_q);
		if (!$result_dl) {die('Сбой при доступе к базе данных: '.$dl_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$dls=$result_dl->fetch_assoc();
		$dls_num=$result_tov->num_rows;
		$elez_sum+=$dls['SUM(rent_sub_deals_arch.r_paid)'];



//а теперь считаем по кидсикам
//оффис 1
$kids_tov_q = "SELECT item_inv_n FROM tovar_rent_items WHERE seller='кидсики'";
$result_tov2 = $mysqli->query($kids_tov_q);
$kids_invs='';
if (!$result_tov2) {die('Сбой при доступе к базе данных: '.$kids_tov_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

$kids_num=$result_tov2->num_rows;
$num_act_num=0;
while ($kids_tov = $result_tov2->fetch_assoc()) {
    $num_act_num++;
    if ($kids_num==$num_act_num) {
        $kids_invs.=$kids_tov['item_inv_n'];
    }
    else {
        $kids_invs.=$kids_tov['item_inv_n'].', ';
    }
}
if ($kids_num<1) {
    $kids_invs='0';
}

    $dl_q="SELECT SUM(rent_sub_deals_act.r_paid) FROM rent_deals_act LEFT JOIN rent_sub_deals_act ON (rent_deals_act.deal_id = rent_sub_deals_act.deal_id) WHERE rent_deals_act.item_inv_n IN ($kids_invs) AND (rent_sub_deals_act.acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
    $result_dl = $mysqli->query($dl_q);
    if (!$result_dl) {die('Сбой при доступе к базе данных: '.$dl_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
    $dls=$result_dl->fetch_assoc();
    $dls_num=$result_tov->num_rows;
    $kids_sum=$dls['SUM(rent_sub_deals_act.r_paid)'];

        $dl_q="SELECT SUM(rent_sub_deals_arch.r_paid) FROM rent_deals_arch LEFT JOIN rent_sub_deals_arch ON (rent_deals_arch.deal_id = rent_sub_deals_arch.deal_id) WHERE rent_deals_arch.item_inv_n IN ($kids_invs) AND (rent_sub_deals_arch.acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
        $result_dl = $mysqli->query($dl_q);
        if (!$result_dl) {die('Сбой при доступе к базе данных: '.$dl_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $dls=$result_dl->fetch_assoc();
        $dls_num=$result_tov->num_rows;
        $kids_sum+=$dls['SUM(rent_sub_deals_arch.r_paid)'];


//а теперь считаем по спеленкам
//оффис 1
$spel_tov_q = "SELECT item_inv_n FROM tovar_rent_items WHERE seller='спелёнок'";
$result_tov3 = $mysqli->query($spel_tov_q);
$spel_invs='';
if (!$result_tov3) {die('Сбой при доступе к базе данных: '.$spel_tov_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

$spel_num=$result_tov3->num_rows;
$num_act_num=0;
while ($spel_tov = $result_tov3->fetch_assoc()) {
    $num_act_num++;
    if ($spel_num==$num_act_num) {
        $spel_invs.=$spel_tov['item_inv_n'];
    }
    else {
        $spel_invs.=$spel_tov['item_inv_n'].', ';
    }
}
if ($spel_num<1) {
    $spel_invs='0';
}

$dl_q="SELECT SUM(rent_sub_deals_act.r_paid) FROM rent_deals_act LEFT JOIN rent_sub_deals_act ON (rent_deals_act.deal_id = rent_sub_deals_act.deal_id) WHERE rent_deals_act.item_inv_n IN ($spel_invs) AND (rent_sub_deals_act.acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_dl = $mysqli->query($dl_q);
if (!$result_dl) {die('Сбой при доступе к базе данных: '.$dl_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$dls=$result_dl->fetch_assoc();
$dls_num=$result_tov->num_rows;
$spel_sum=$dls['SUM(rent_sub_deals_act.r_paid)'];

$dl_q="SELECT SUM(rent_sub_deals_arch.r_paid) FROM rent_deals_arch LEFT JOIN rent_sub_deals_arch ON (rent_deals_arch.deal_id = rent_sub_deals_arch.deal_id) WHERE rent_deals_arch.item_inv_n IN ($spel_invs) AND (rent_sub_deals_arch.acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."')";
$result_dl = $mysqli->query($dl_q);
if (!$result_dl) {die('Сбой при доступе к базе данных: '.$dl_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$dls=$result_dl->fetch_assoc();
$dls_num=$result_tov->num_rows;
$spel_sum+=$dls['SUM(rent_sub_deals_arch.r_paid)'];

$from_time=new DateTime();
    $from_time->setTimestamp($i_from_date);
$to_time = new DateTime();
    $to_time->setTimestamp($i_to_date);

// отчет по карнавальным броням
$qkb = "SELECT COUNT(`kb_id`) AS num FROM karn_brons WHERE `status` IN ('new', 'ok') AND (cr_time BETWEEN '".$i_from_date."' AND '".($i_to_date+24*60*60-1)."')";
$rezkb = $mysqli->query($qkb);
if (!$rezkb) {die('Сбой при доступе к базе данных: '.$qkb.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$rezkb->fetch_assoc();
$kbn_this_year=$of1_res['num'];

$from_time->modify("-1 year");
$to_time->modify("-1 year");

$qkb = "SELECT COUNT(`kb_id`) AS num FROM karn_brons WHERE `status` IN ('new', 'ok') AND (cr_time BETWEEN '".$from_time->getTimestamp()."' AND '".($to_time->getTimestamp()+24*60*60-1)."')";
$rezkb = $mysqli->query($qkb);
if (!$rezkb) {die('Сбой при доступе к базе данных: '.$qkb.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$of1_res=$rezkb->fetch_assoc();
$kbn_prev_year=$of1_res['num'];




echo'
<tr>
	<td>выручка вся (руб)</td>
	<td style="text-align:right">'.number_format($of1, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of2, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of3, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($deliv, 1, ',', ' ').'<br /><i>+'.number_format(($delivery_paid), 1, ',', ' ').'</i></td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($of1+$of2+$of3+$deliv)/1, 1, ',', ' ').' <br /><i>+'.number_format(($delivery_paid)/1, 1, ',', ' ').'</i></td>
</tr>

<tr>
	<td>выручка по товарам Елизаветки (руб)</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right; font-weight:bold;">'.number_format($elez_sum/1, 1, ',', ' ').'</td>
</tr>
<tr>
	<td>выручка по товарам Кидсиков (руб)</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right; font-weight:bold;">'.number_format($kids_sum/1, 1, ',', ' ').'</td>
</tr>
<tr>
	<td>выручка по товарам Спеленок (руб)</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right; font-weight:bold;">'.number_format($spel_sum/1, 1, ',', ' ').'</td>
</tr>

<tr>
	<td>в т.ч. выручка выдачи (руб)</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right; font-weight:bold;">'.number_format($fr_sum/1, 1, ',', ' ').'</td>
</tr>

<tr>
	<td>в т.ч. банк</td>
	<td style="text-align:right">'.number_format($of1_bank/1, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of2_bank/1, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of3_bank/1, 1, ',', ' ').'</td>
	<td style="text-align:right">---</td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($of1_bank+$of2_bank+$of3_bank)/1, 1, ',', ' ').'</td>
</tr>


<tr>
	<td>количество выдач всех</td>
	<td style="text-align:right">'.number_format($of1_num, 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of2_num, 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($of3_num, 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($del_num, 0, ',', ' ').'</td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($of1_num+$of2_num+$of3_num+$del_num), 0, ',', ' ').' </td>
</tr>

<tr>
  <td>количество продлений всех</td>
  <td style="text-align:right"></td>
  <td style="text-align:right"></td>
  <td style="text-align:right"></td>
  <td style="text-align:right"></td>
  <td style="text-align:right; font-weight:bold;">'.number_format(\bb\classes\SubDeal::getSubDealCount($fromDate, $toDate, ['extention']), 0, ',', ' ').'</td>
</tr>


<tr style="background-color:yellow;">
	<td>в т.ч. выручка карнавалы (руб)</td>
	<td style="text-align:right">'.number_format($k_r_paid['1']/1, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($k_r_paid['2']/1, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($k_r_paid['3']/1, 1, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($k_r_paid['0']/1, 1, ',', ' ').'</td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($k_r_paid['0']+$k_r_paid['1']+$k_r_paid['2']+$k_r_paid['3'])/1, 1, ',', ' ').'</td>
</tr>

<tr>
	<td>в т.ч. кол-во выдач карнавалов</td>
	<td style="text-align:right">'.number_format($k_num['1'], 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($k_num['2'], 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($k_num['3'], 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($k_num['0'], 0, ',', ' ').'</td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($k_num['0']+$k_num['1']+$k_num['2']+$k_num['3']), 0, ',', ' ').'</td>
</tr>
<tr>
	<td>средний срок первой выдачи (дней)</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right; font-weight:bold;">'.number_format(($days_fr/$new_frs_num), 0, ',', ' ').'</td>
</tr>

<tr>
	<td>товар - некостюм всего (шт)</td>
	<td style="text-align:right">'.number_format($tov_num_1, 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($tov_num_2, 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format($tov_num_3, 0, ',', ' ').'</td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format(($tov_num_1+$tov_num_2+$tov_num_3), 0, ',', ' ').'</td>
</tr>
<tr>
	<td>товар - некостюм свободно (шт)</td>
	<td style="text-align:right">'.number_format($tov_num_1_free, 0, ',', ' ').' <i>('.number_format(($tov_num_1_free/$tov_num_1*100), 0, ',', ' ').'%)</i></td>
	<td style="text-align:right">'.number_format($tov_num_2_free, 0, ',', ' ').' <i>('.number_format(($tov_num_2_free/$tov_num_2*100), 0, ',', ' ').'%)</i></td>
	<td style="text-align:right">'.number_format($tov_num_3_free, 0, ',', ' ').' <i>('.number_format(($tov_num_3_free/$tov_num_3*100), 0, ',', ' ').'%)</i></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format(($tov_num_1_free+$tov_num_2_free+$tov_num_3), 0, ',', ' ').' <i>('.number_format((($tov_num_2_free+$tov_num_1_free+$tov_num_3)/($tov_num_2+$tov_num_1+$tov_num_3)*100), 0, ',', ' ').'%)</i></td>
</tr>
<tr>
	<td>товар - некостюм (usd)</td>
	<td style="text-align:right">'.number_format(($tov_sum_1_usd+$tov_sum_1_usd_byr), 0, ',', ' ').'</td>
	<td style="text-align:right">'.number_format(($tov_sum_2_usd+$tov_sum_2_usd_byr), 0, ',', ' ').'</td>
	<td style="text-align:right"></td>
	<td style="text-align:right">посчитать потом</td>
	<td style="text-align:right">'.number_format(($tov_sum_1_usd+$tov_sum_1_usd_byr+$tov_sum_2_usd+$tov_sum_2_usd_byr), 0, ',', ' ').'</td>
</tr>
<tr>
	<td>% выручки на вложения</td>
	<td style="text-align:right">'.number_format((($of1/16/($tov_sum_1_usd+$tov_sum_1_usd_byr))*100), 2, ',', ' ').'</td>
	<td style="text-align:right">'.number_format((($of2/16/($tov_sum_2_usd+$tov_sum_2_usd_byr))*100), 2, ',', ' ').'</td>
	<td style="text-align:right"></td>
	<td style="text-align:right">посчитать потом</td>
	<td style="text-align:right">'.number_format(($tov_sum_1_usd+$tov_sum_1_usd_byr+$tov_sum_2_usd+$tov_sum_2_usd_byr), 0, ',', ' ').'</td>
</tr>

<tr>
	<td>кол-во веб.броней за период (c 21.12):</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format(($web_br_num+$web_br_num_arch), 0, ',', ' ').' <br>
			<i>svoi:('.number_format(($web_br_num_svoi+$web_br_num_arch_svoi), 0, ',', ' ').')</i>
			</td>
</tr>
<tr>
	<td>кол-во веб.заявок за период:</td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right"></td>
	<td style="text-align:right">'.number_format(($web_zayav_num+$web_zayav_num_arch), 0, ',', ' ').'</td>
</tr>

';


echo '</table>';



//считаем сделки по часам
$of1_t=dl_time('1', $i_from_date, $i_to_date);
$of2_t=dl_time('2', $i_from_date, $i_to_date);
$of3_t=dl_time('3', $i_from_date, $i_to_date);

	$of1_10=0;
	$of2_10=0;
	$of3_10=0;

	$of1_20=0;
	$of2_20=0;
	$of3_20=0;

for ($i=0; $i<=9; $i++) {
	$of1_10+=$of1_t[$i];
}
for ($i=0; $i<=9; $i++) {
	$of2_10+=$of2_t[$i];
}
for ($i=0; $i<=9; $i++) {
	$of3_10+=$of3_t[$i];
}

for ($i=20; $i<=23; $i++) {
	$of1_20+=$of1_t[$i];
}
for ($i=20; $i<=23; $i++) {
	$of2_20+=$of2_t[$i];
}
for ($i=20; $i<=23; $i++) {
	$of3_20+=$of3_t[$i];
}

echo '<br /><br />Посещения (не курьер) за период (выдачи и возвраты без продлений), шт:
	<table border="1" cellspacing="0">
		<tr>
			<th>часы</th>
			<th>Оф1</th>
			<th>Оф2</th>
			<th>Оф3</th>
		</tr>
		<tr>
			<td>до10</td>
			<td>'.$of1_10.'</td>
			<td>'.$of2_10.'</td>
			<td>'.$of3_10.'</td>
		</tr>
		<tr>
			<td>10-11</td>
			<td>'.$of1_t[10].'</td>
			<td>'.$of2_t[10].'</td>
			<td>'.$of3_t[10].'</td>
		</tr>
		<tr>
			<td>11-12</td>
			<td>'.$of1_t[11].'</td>
			<td>'.$of2_t[11].'</td>
			<td>'.$of3_t[11].'</td>
		</tr>
		<tr>
			<td>12-13</td>
			<td>'.$of1_t[12].'</td>
			<td>'.$of2_t[12].'</td>
			<td>'.$of3_t[12].'</td>
		</tr>
		<tr>
			<td>13-14</td>
			<td>'.$of1_t[13].'</td>
			<td>'.$of2_t[13].'</td>
			<td>'.$of3_t[13].'</td>
		</tr>
		<tr>
			<td>14-15</td>
			<td>'.$of1_t[14].'</td>
			<td>'.$of2_t[14].'</td>
			<td>'.$of3_t[14].'</td>
		</tr>
		<tr>
			<td>15-16</td>
			<td>'.$of1_t[15].'</td>
			<td>'.$of2_t[15].'</td>
			<td>'.$of3_t[15].'</td>
		</tr>
		<tr>
			<td>16-17</td>
			<td>'.$of1_t[16].'</td>
			<td>'.$of2_t[16].'</td>
			<td>'.$of3_t[16].'</td>
		</tr>
		<tr>
			<td>17-18</td>
			<td>'.$of1_t[17].'</td>
			<td>'.$of2_t[17].'</td>
			<td>'.$of3_t[17].'</td>
		</tr>
		<tr>
			<td>18-19</td>
			<td>'.$of1_t[18].'</td>
			<td>'.$of2_t[18].'</td>
			<td>'.$of3_t[18].'</td>
		</tr>
		<tr>
			<td>19-20</td>
			<td>'.$of1_t[19].'</td>
			<td>'.$of2_t[19].'</td>
			<td>'.$of3_t[19].'</td>
		</tr>
		<tr>
			<td>после 20</td>
			<td>'.$of1_20.'</td>
			<td>'.$of2_20.'</td>
			<td>'.$of3_20.'</td>
		</tr>
		<tr>
			<td>всего:</td>
			<td>'.array_sum($of1_t).'</td>
			<td>'.array_sum($of2_t).'</td>
			<td>'.array_sum($of3_t).'</td>
		</tr>


	</table>

		';

echo $cl_text;
$act_t=array();
$arch_t=array();

echo 'Карнавальные брони:<br> текущий период'.$kbn_this_year.'<br> период предыдущего года'.$kbn_prev_year;

//расчеты по сотрудникам выключены для ускорения
/*
while ($act_p=$result_sotr->fetch_assoc()) {
	$act_t[$act_p['max(`cr_who_id`)']]=$act_p['sum(`r_paid`)'];
}

while ($arch_p=$result_sotr_a->fetch_assoc()) {

	if (!array_key_exists($arch_p['max(`cr_who_id`)'], $act_t)) {
		$act_t[$arch_p['max(`cr_who_id`)']]=0;
	}


	$act_t[$arch_p['max(`cr_who_id`)']]+=$arch_p['sum(`r_paid`)'];
}

foreach ($act_t as $key => $value) {
	echo $lp_list[$key].'='.number_format($value, 0, ',', ' ').'<br />';
}


echo '--------------------------------------<br />';

foreach ($fd_act as $key => $value) {
	echo $lp_list[$key].'='.number_format($value, 0, ',', ' ').'<br />';
}

*/



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
	GLOBAL $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}

echo $i_from_date.'---'.$i_to_date;

?>
