<?php
session_start();

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Delivery.php'); //

//------- proverka paroley

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941) {
	die('
	<form action="index.php" method="post">
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>');
}

//-----------proverka paroley



foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}
//$sub_type='payment';
//$sub_id='4506';
//$p_from='1403128800';

$mysqli=\bb\Db::getInstance()->getConnection();


switch ($sub_type) {
	case 'payment': //!!!остановился здесь - нужно переписать код удаления платежа с учетом возможного разного времени удаляемого платежа при одном линке

		$query_sub_dl_def = "SELECT * FROM rent_sub_deals_act WHERE sub_deal_id='$sub_id'";
		$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
		if (!$result_sub_dl_def) die('Сбой при доступе к базе данных: '.$query_sub_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		$sub_dl_def=$result_sub_dl_def->fetch_assoc();

		$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."'";
		$result_dl_def = $mysqli->query($query_dl_def);
		if (!$result_dl_def) die('Сбой при доступе к базе данных: '.$query_sub_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		$dl_def=$result_dl_def->fetch_assoc();

		//собираем информацию о платежах
		//аренда
		$query_sub_r_paid = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE `link`='$sub_id' AND `from`='$p_from'";
		$result_sub_r_paid = $mysqli->query($query_sub_r_paid);
        if (!$result_sub_r_paid) die('Сбой при доступе к базе данных: '.$query_sub_r_paid.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
        $sub_r_paid=$result_sub_r_paid->fetch_assoc();
        //доставка
		$query_sub_d_paid = "SELECT SUM(delivery_paid) FROM rent_sub_deals_act WHERE `link`='$sub_id' AND `from`='$p_from'";
		$result_sub_d_paid = $mysqli->query($query_sub_d_paid);
		if (!$result_sub_d_paid) die('Сбой при доступе к базе данных: '.$query_sub_d_paid.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		$sub_d_paid=$result_sub_d_paid->fetch_assoc();


		$query_start = "START TRANSACTION";
		$result_start = $mysqli->query($query_start);
		if (!$result_start) {
            $done="no";
            die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
        }

		$done="yes";


		// корректируем сделку
		$r_paid=$dl_def['r_paid']-$sub_r_paid['SUM(r_paid)'];
		$d_paid=$dl_def['delivery_paid']-$sub_d_paid['SUM(delivery_paid)'];

		$query_dl_upd = "UPDATE rent_deals_act SET r_paid='$r_paid', delivery_paid='$d_paid', last_sub_deal_ch_time='".time()."' WHERE deal_id='".$sub_dl_def['deal_id']."'";
		if (!$mysqli->query($query_dl_upd)) {
			echo 'Сбой при доступе к базе данных: '.$query_dl_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error;
		}

		// делаем удаление платежей
		$query_del_sub = "DELETE FROM rent_sub_deals_act WHERE link='$sub_id' AND `from`='$p_from'";
		if (!$mysqli->query($query_del_sub)) {
			$done="no";
            echo 'Сбой при доступе к базе данных: '.$query_del_sub.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error;
		}



			if ($done=='yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


			}
			else {

				$query_fin = "ROLLBACK'";
                $result_fin = $mysqli->query($query_fin);
                if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			}



	break;


	case 'cur_return':
        $mysqli = \bb\Db::getInstance()->getConnection();

		$query_sub_dl_def = "SELECT * FROM rent_sub_deals_act WHERE sub_deal_id='$sub_id'";
		$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
		if (!$result_sub_dl_def) die('Сбой при доступе к базе данных: '.$query_sub_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		$sub_dl_def=$result_sub_dl_def->fetch_assoc();

		$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."'";
		$result_dl_def = $mysqli->query($query_dl_def);
		if (!$result_dl_def) die('Сбой при доступе к базе данных: '.$query_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		$dl_def=$result_dl_def->fetch_assoc();


		$query_start = "START TRANSACTION";
		$result_start = $mysqli->query($query_start);
		if (!$result_start) {
            $done="no";
            die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
        }

		$done="yes";


		// корректируем сделку

		$query_dl_upd = "UPDATE rent_deals_act SET deal_status='', last_sub_deal_ch_time='".time()."' WHERE deal_id='".$sub_dl_def['deal_id']."'";
		if (!$mysqli->query($query_dl_upd)) {
			echo 'Сбой при доступе к базе данных: '.$query_dl_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error;
		}


		// делаем удаление суб. сделки
		$query_del_sub = "DELETE FROM rent_sub_deals_act WHERE sub_deal_id='$sub_id'";
		if (!$mysqli->query($query_del_sub)) {
		$done="no";
			echo 'Сбой при доступе к базе данных: '.$query_del_sub.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error;
		}

		if (!Delivery::cancelDelivery($sub_id)) {
			$done='no';
		}



				if ($done=='yes') {
				$query_fin = "COMMIT";
					$result_fin = $mysqli->query($query_fin);
					if (!$result_fin) {die('Сбой при доступе к базе данных: '.$result_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


					}
					else {

					$query_fin = "ROLLBACK'";
						$result_fin = $mysqli->query($query_fin);
                        if (!$result_fin) {die('Сбой при доступе к базе данных: '.$result_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
					}




		break;


	case 'extention':

        $mysqli=\bb\Db::getInstance()->getConnection();

		$query_sub_dl_def = "SELECT * FROM rent_sub_deals_act WHERE sub_deal_id='$sub_id'";
		$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
		if (!$result_sub_dl_def) die('Сбой при доступе к базе данных: '.$query_sub_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		$sub_dl_def=$result_sub_dl_def->fetch_assoc();

		$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."'";
		$result_dl_def = $mysqli->query($query_dl_def);
		if (!$result_dl_def) die('Сбой при доступе к базе данных: '.$query_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		$dl_def=$result_dl_def->fetch_assoc();


		//собираем информацию о платежах
			//аренда
		$query_sub_r_paid = "SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE `link`='$sub_id'";
		$result_sub_r_paid = $mysqli->query($query_sub_r_paid);
		if (!$result_sub_r_paid) die('Сбой при доступе к базе данных: '.$query_sub_r_paid.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		$sub_r_paid=$result_sub_r_paid->fetch_assoc();
			//доставка
		$query_sub_d_paid = "SELECT SUM(delivery_paid) FROM rent_sub_deals_act WHERE `link`='$sub_id'";
		$result_sub_d_paid = $mysqli->query($query_sub_d_paid);
		if (!$result_sub_d_paid) die('Сбой при доступе к базе данных: '.$query_sub_d_paid.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
		$sub_d_paid=$result_sub_d_paid->fetch_assoc();


		$query_start = "START TRANSACTION";
		$result_start = $mysqli->query($query_start);
		if (!$result_start) {
            $done="no";
            die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
        }

		$done="yes";


		// корректируем сделку
		$delivery_to_pay=$dl_def['delivery_to_pay']-$sub_dl_def['delivery_to_pay'];
		$r_to_pay=$dl_def['r_to_pay']-$sub_dl_def['r_to_pay'];

		$r_paid=$dl_def['r_paid']-$sub_r_paid['SUM(r_paid)'];
		$d_paid=$dl_def['delivery_paid']-$sub_d_paid['SUM(delivery_paid)'];

		$return_date=$sub_dl_def['from'];

		$query_dl_upd = "UPDATE rent_deals_act SET r_to_pay='$r_to_pay', delivery_to_pay='$delivery_to_pay', r_paid='$r_paid', delivery_paid='$d_paid', return_date='$return_date', last_sub_deal_ch_time='".time()."' WHERE deal_id='".$sub_dl_def['deal_id']."'";
		if (!$mysqli->query($query_dl_upd)) {
			echo 'Сбой при доступе к базе данных: '.$query_dl_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error;
		}


		// делаем удаление суб. сделки
		$query_del_sub = "DELETE FROM rent_sub_deals_act WHERE sub_deal_id='$sub_id'";
		if (!$mysqli->query($query_del_sub)) {
		$done="no";
            echo 'Сбой при доступе к базе данных: '.$query_del_sub.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error;
		}

		// удаление платежей
		$query_del_sub2 = "DELETE FROM rent_sub_deals_act WHERE link='$sub_id'";
		if (!$mysqli->query($query_del_sub2)) {
			$done="no";
            echo 'Сбой при доступе к базе данных: '.$query_del_sub2.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error;
		}

		if (!Delivery::cancelDelivery($sub_id)) {
			$done='no';
		}


		if ($done=='yes') {
		$query_fin = "COMMIT";
			$result_fin = $mysqli->query($query_fin);
			if (!$result_fin) {
                die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
            }

		}
		else {
			$query_fin = "ROLLBACK'";
            $result_fin = $mysqli->query($query_fin);
            if (!$result_fin) {
                die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
            }
		}




	break;





	default:
		;
	break;
}



function get_post($var)
{
    $mysqli=\bb\Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
}

?>
