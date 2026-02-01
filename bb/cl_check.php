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
				<option value="1">Литературная</option>
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
<title>Чистка клиентов.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: '.$_SESSION['office'].'</form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>
';
require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам

?>



<script language="javascript">

function send_form (cl_id) {

	valid = true;

	// проверка клиента
	if (document.getElementById('slave_cl_'+cl_id).value=="") {
		alert ('Заполните ID поглощаемого клиента');
     valid = false;}
	
	if (document.getElementById('slave_cl_'+cl_id).value==document.getElementById('m_client_id_'+cl_id).value) {
		alert ('ID поглощаемого клиента не должно совпадать с ID главного клиента');
     valid = false;}
	
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

$start_n=0;
$end_n=100;
$action='';


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

switch ($action) {

	case 'сделать главным':
		
		//начинаем транзакцию
		$query_start = "START TRANSACTION";
		$result_start = $mysqli->query($query_start);
		if (!$result_start) {die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		$done="yes";
		
		
		//выбираем информацию о действуюих клиентах
		$cl_qs = "SELECT * FROM clients WHERE client_id='$slave_cl'";
		$result_cls = $mysqli->query($cl_qs);
		if (!$result_cls) {$done="no"; die('Сбой при доступе к базе данных: '.$cl_qs.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$cl_s=$result_cls->fetch_assoc();
		
		$cl_qm = "SELECT * FROM clients WHERE client_id='$m_client_id'";
		$result_clm = $mysqli->query($cl_qm);
		if (!$result_clm) {$done="no"; die('Сбой при доступе к базе данных: '.$cl_qm.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$cl_m=$result_clm->fetch_assoc();
		
		
		
		//меняем клиента на действующих сделкаих
		$upd_q = "UPDATE rent_deals_act SET client_id='$m_client_id' WHERE client_id='$slave_cl'";
		$result_upd = $mysqli->query($upd_q);
		if (!$result_upd) {$done="no"; die('Сбой при доступе к базе данных: '.$upd_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		//меняем клиента на архивных сделкаих
		$upd_q = "UPDATE rent_deals_arch SET client_id='$m_client_id' WHERE client_id='$slave_cl'";
		$result_upd = $mysqli->query($upd_q);
		if (!$result_upd) {$done="no"; die('Сбой при доступе к базе данных: '.$upd_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		//меняем клиента на старых сделкаих
		$upd_q = "UPDATE deals SET client_id='$m_client_id' WHERE client_id='$slave_cl'";
		$result_upd = $mysqli->query($upd_q);
		if (!$result_upd) {$done="no"; die('Сбой при доступе к базе данных: '.$upd_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		//обновляем архивную сумму на основном клиенте
		$upd_q = "UPDATE clients SET arch_amount='".($cl_m['arch_amount']+$cl_s['arch_amount'])."', arch_n='".($cl_m['arch_n']+$cl_s['arch_n'])."' WHERE client_id='$m_client_id'";
		$result_upd = $mysqli->query($upd_q);
		if (!$result_upd) {$done="no"; die('Сбой при доступе к базе данных: '.$upd_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		//скидываем поглощаемого клиента в архив
		foreach ($cl_s as $key => $value) {//проименовываем поля в переменный, чтоб далее вставить
			$$key = $value;
		}
		$ins_q="INSERT INTO clients_arch VALUES('', '".time()."', '".$_SESSION['user_id']."', '$m_client_id', '$client_id', '$family', '$name', '$otch', '$city', '$str', '$dom', '$kv', '$pas_n', '$pas_ln', '$pas_date', '$pas_who', '$reg_city', '$reg_str', '$reg_dom', '$reg_kv', '$phone_1', '$phone_2', '$info', '$status', '$cr_time', '$arch_n', '$arch_amount', '$arch_l_date', '$cr_who')";
		$result_ins = $mysqli->query($ins_q);
		if (!$result_ins) {$done="no"; die('Сбой при доступе к базе данных: '.$ins_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		//при необходимости обновляем действующего клиента (телефоны и т.д.)
		if ($cl_m['phone_1']!=$cl_s['phone_1'] && $cl_m['phone_2']!=$cl_s['phone_2']) {
			
			$new_info=$cl_m['info'].'---'.$cl_s['info'].'---Другой телефон 1:'.$cl_s['phone_1'].'Другой телефон 2:'.$cl_s['phone_2'];
			
			$upd_q = "UPDATE clients SET info='$new_info' WHERE client_id='$m_client_id'";
			$result_upd = $mysqli->query($upd_q);
			if (!$result_upd) {$done="no"; die('Сбой при доступе к базе данных: '.$upd_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			
		}
		

		//удаляем объединяемого клиента
		$query_del = "DELETE FROM clients WHERE client_id='$slave_cl'";
		$result_del = $mysqli->query($query_del);
		if (!$result_del) {$done="no"; die('Сбой при доступе к базе данных: '.$query_del.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		
		//завершение
		if ($done=='yes') {
			$query_fin = "COMMIT";
			$result_fin = $mysqli->query($query_fin);
			if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			
		}
		else {
			$query_fin = "ROLLBACK";
			$result_fin = $mysqli->query($query_fin);
			if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		}
		
	break;
	
	
	case 'удалить':
		//начинаем транзакцию
		$query_start = "START TRANSACTION";
		$result_start = $mysqli->query($query_start);
		if (!$result_start) {die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		$done="yes";
		
		//выбираем информацию о действуюих клиентах
		$cl_qm = "SELECT * FROM clients WHERE client_id='$m_client_id'";
		$result_clm = $mysqli->query($cl_qm);
		if (!$result_clm) {$done="no"; die('Сбой при доступе к базе данных: '.$cl_qm.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$cl_m=$result_clm->fetch_assoc();
		
		
		//скидываем поглощаемого клиента в архив
		foreach ($cl_m as $key => $value) {
			$$key = $value;
		}
		$ins_q="INSERT INTO clients_arch VALUES('', '".time()."', '".$_SESSION['user_id']."', '0', '$client_id', '$family', '$name', '$otch', '$city', '$str', '$dom', '$kv', '$pas_n', '$pas_ln', '$pas_date', '$pas_who', '$reg_city', '$reg_str', '$reg_dom', '$reg_kv', '$phone_1', '$phone_2', '$info', '$status', '$cr_time', '$arch_n', '$arch_amount', '$arch_l_date', '$cr_who')";
		$result_ins = $mysqli->query($ins_q);
		if (!$result_ins) {$done="no"; die('Сбой при доступе к базе данных: '.$ins_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		//удаляем объединяемого клиента
		$query_del = "DELETE FROM clients WHERE client_id='$m_client_id'";
		$result_del = $mysqli->query($query_del);
		if (!$result_del) {$done="no"; die('Сбой при доступе к базе данных: '.$query_del.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		
		//завершение
		if ($done=='yes') {
			$query_fin = "COMMIT";
			$result_fin = $mysqli->query($query_fin);
			if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
		}
		else {
			$query_fin = "ROLLBACK";
			$result_fin = $mysqli->query($query_fin);
			if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		}
		
		
	break;
}




$cl_q = "SELECT * FROM clients ORDER by family, name, otch, str, dom, kv LIMIT $start_n,$end_n";
$result_cl = $mysqli->query($cl_q);
if (!$result_cl) {die('Сбой при доступе к базе данных: '.$cl_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$cl_n_num=$result_cl->num_rows;


$family='';
$name='';
$otch='';

echo '
<form name="main_nums" method="post" action="cl_check.php" >		
	<input type="text" name="start_n" id="start_n" value="'.$start_n.'" size="5" />		
	<input type="text" name="end_n" id="end_n" value="'.$end_n.'" size="5" />
	<input type="submit" value="просмотр" />
</form>
		
<table border="1" cellspacing="0">
	<tr>
		<th>id</th>
		<th>ФИО</th>
		<th>Адрес</th>
		<th>Прописка</th>
		<th>Паспорт</th>
		<th>Телефоны</th>
		<th>Инфо</th>
		<th>Сумма заказов</th>
		<th>Действия</th>
	</tr>';

$cl_prev='';
$cl_act='';

while ($cl=$result_cl->fetch_assoc()) {

	//считаем, на сколько клиент у нас назаказывал
	$deal_act_q = "SELECT SUM(`r_to_pay`) FROM rent_deals_act WHERE client_id='".$cl['client_id']."'";
	$result_dl_act = $mysqli->query($deal_act_q);
	if (!$result_dl_act) {die('Сбой при доступе к базе данных: '.$deal_act_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$dl_act=$result_dl_act->fetch_assoc();
	
	$deal_arch_q = "SELECT SUM(`r_to_pay`) FROM rent_deals_arch WHERE client_id='".$cl['client_id']."'";
	$result_dl_arch = $mysqli->query($deal_arch_q);
	if (!$result_dl_arch) {die('Сбой при доступе к базе данных: '.$deal_arch_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$dl_arch=$result_dl_arch->fetch_assoc();
	
	
	$deal_old_q = "SELECT SUM(`r_to_pay`) FROM deals WHERE client_id='".$cl['client_id']."'";
	$result_dl_old = $mysqli->query($deal_old_q);
	if (!$result_dl_old) {die('Сбой при доступе к базе данных: '.$deal_old_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$dl_old=$result_dl_old->fetch_assoc();
	
	
	$r_to_pay=$dl_act['SUM(`r_to_pay`)']+$dl_arch['SUM(`r_to_pay`)'];
	
	
	$cl_act='
			
	<form name="client_s_'.$cl['client_id'].'" method="post" action="cl_check.php" >
	<tr '; 
		if($family==$cl['family'] && $name==$cl['name'] && $otch==$cl['otch']) {$cl_act.='style="background-color:yellow;"';}
		$cl_act.= '>
		<td>'.$cl['client_id'].' <input type="hidden" name="m_client_id" id="m_client_id_'.$cl['client_id'].'" value="'.$cl['client_id'].'" /> </td>
    	<td>'.$cl['family'].' '.$cl['name'].' '.$cl['otch'].'</td>
    	<td>'.$cl['str'].' '.$cl['dom'].'-'.$cl['kv'].','.$cl['city'].'</td>
    	<td>'.$cl['reg_str'].' '.$cl['reg_dom'].'-'.$cl['reg_kv'].','.$cl['reg_city'].'</td>
    	<td>'.$cl['pas_n'].' / '.$cl['pas_ln'].', выдан '.date("d-m-Y", $cl['pas_date']).' '.$cl['pas_who'].'</td>
    	<td>'.phone_print($cl['phone_1']).'<br />'.phone_print($cl['phone_2']).'</td>
    	<td>'.$cl['info'].'</td>
    	<td>'.number_format($r_to_pay, 0, ',', ' ').' / '.number_format($dl_old['SUM(`r_to_pay`)'], 0, ',', ' ').'<br /></td>
    	<td> поглощаемый клиент: <input type="text" name="slave_cl" id="slave_cl_'.$cl['client_id'].'" size="3" />
						<input type="submit" name="action" value="сделать главным" onclick="return send_form(\''.$cl['client_id'].'\')" /><br />
						<input type="submit" name="action" value="удалить" /></td>
    			
    						<input type="hidden" name="start_n" value="'.$start_n.'" />		
							<input type="hidden" name="end_n" value="'.$end_n.'" />
    			
	</tr>
	</form>
	';
	
	if ($family==$cl['family'] && $name==$cl['name'] && $otch==$cl['otch']) {
		echo $cl_prev.$cl_act;
	}
		
	$family=$cl['family'];
	$name=$cl['name'];
	$otch=$cl['otch'];
	$cl_prev=$cl_act;
	$cl_act='';
	
	}
	
echo '</table>';
			
			




function get_post($var) {
	return mysql_real_escape_string($_POST[$var]);
}


function good_print($var) {
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}


function phone_print ($ph) {
	if ($ph=='') {return '';}

	$dl=strlen($ph);

	if ($dl<7) {return $ph;}

	$dl>7 ? $dl_to=$dl-7 : $dl_to=0;
	$ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
	return $ph_out;

}




?>