<?php
session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных

$t_to=strtotime("2016-11-01");



$query_kb = "SELECT * FROM karn_brons WHERE t_to<'$t_to'";
$result_kb = $mysqli->query($query_kb);
if (!$result_kb) {die('Сбой при доступе к базе данных: '.$query_kb.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$main_rows=$result_kb->num_rows;

while ($kb=$result_kb->fetch_assoc()) { 

	$done="yes";
	
	
		
	//запускаем транзакцию
	$query_start = "START TRANSACTION";
	$result_start = $mysqli->query($query_start);
	if (!$result_start) {die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
	$done="no";
	}

	$br_time=$kb['t_to'];
	$arch_info='автоматическое архивирование старых броней';
	//перенос брони в архив
	$query_arch = "INSERT INTO karn_brons_arch SELECT '', '".time()."', '".$_SESSION['user_id']."', '$arch_info', kb_id, inv_n, cl_id, t_from, t_to, `status`, `info`, payment_k1, payment_k2, payment_term, payment_bank, payment_date, cr_time, br_max_num, br_num, fio, phone1, phone2, `mail`, dl_link, appr_time, appr_who, vidacha, vid_who_id, vozvrat, vozvr_who_id, nedozvon FROM karn_brons WHERE kb_id='".$kb['kb_id']."'";
	$result_arch = $mysqli->query($query_arch);
	if (!$result_arch) {die('Сбой при доступе к базе данных: '.$query_arch.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
	$done="no";
	}
		

	$query_del = "DELETE FROM karn_brons WHERE kb_id='".$kb['kb_id']."'";
	$result_del = $mysqli->query($query_del);
	if (!$result_del) {die('Сбой при доступе к базе данных: '.$query_del.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
	$done="no";
	}
		
	//завершение
	if ($done=='yes') {
		$query_fin = "COMMIT";
		$result_fin = $mysqli->query($query_fin);
		if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		echo '<p style="color:red;"><strong>Бронь успешно удалена! '.date("d.m.Y", $br_time).'</strong> </p>';
	}
	else {
		$query_fin = "ROLLBACK";
		$result_fin = $mysqli->query($query_fin);
		if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

		echo '<p style="color:red;"><strong>Возникли проблемы с удалением брони. Обратитесь к разработчику.</strong> </p>';
	}

}

?>