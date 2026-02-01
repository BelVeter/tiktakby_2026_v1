<?php

use bb\Base;

session_start();

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //

//!!! потом предусмотреть, что товары могут быть как активные, так и в архиве



//------- proverka paroley

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941) {
	die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	</head>
	<body>

	<form action="/bb/index.php" method="post">
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>');
}

//-----------proverka paroley



foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

$worning='';
$sort_rool='';

$mysqli = \bb\Db::getInstance()->getConnection();

if (isset($_POST['action'])) {

	switch ($action) {
		case 'отменить возврат':

			$query = "SELECT * FROM rent_deals_arch WHERE deal_id='$deal_id'";
			$result = $mysqli->query($query);
            if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			$deals_def=$result->fetch_assoc();

		//проверка, а свободен ли тот товар, на который мы оформляем возврат
			$query_ch = "SELECT * FROM tovar_rent_items WHERE item_inv_n='".$deals_def['item_inv_n']."'";
			$result_ch = $mysqli->query($query_ch);
            if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			$inv_ch=$result_ch->fetch_assoc();

		if ($inv_ch['status']=='rented_out' || $inv_ch['status']=='bron' || $inv_ch['status']=='to_deliver') {
			$worning='<div class="cans_wor_no">Отмена возврата не осущестлена. Товар, по которому пытаетесь оформить отмену возврат, в настоящее время сдан кому-то еще, либо забронирован. Отмена возврата возможна только на свободный товар.</div>';
			break;
		}

			$query_sub_dl_def = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='".$deals_def['deal_id']."' AND `type`='close'";
			$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
            if (!$result_sub_dl_def) {die('Сбой при доступе к базе данных: '.$query_sub_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			$sub_dl_def_cl=$result_sub_dl_def->fetch_assoc();

			$query_sub_dl_pay = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='".$deals_def['deal_id']."' AND `type` IN ('cl_payment', 'cl_refund')";
			$result_sub_dl_pay = $mysqli->query($query_sub_dl_pay);
            if (!$result_sub_dl_pay) {die('Сбой при доступе к базе данных: '.$query_sub_dl_pay.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			$sub_dl_def_pay=$result_sub_dl_pay->fetch_assoc();

			$query_sub_last = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='".$deals_def['deal_id']."' AND `type` IN ('first_rent', 'extention', 'takeaway_plan') ORDER BY `from` DESC";
			$result_sub_last = $mysqli->query($query_sub_last);
            if (!$result_sub_last) {die('Сбой при доступе к базе данных: '.$query_sub_last.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			$sub_last=$result_sub_last->fetch_assoc();



			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
            if (!$result_start) {die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

			$done="yes";



			// корректируем сделку
			$r_to_pay=$deals_def['r_to_pay']-$sub_dl_def_cl['r_to_pay'];
			$del_to_pay=$deals_def['delivery_to_pay']-$sub_dl_def_cl['delivery_to_pay'];
			$delivery_paid=$deals_def['delivery_paid']-$sub_dl_def_pay['delivery_paid'];
			$r_paid=$deals_def['r_paid']-$sub_dl_def_pay['r_paid'];


			$query_dl_upd = "UPDATE rent_deals_arch SET delivery_paid='$delivery_paid', r_paid='$r_paid', r_to_pay='$r_to_pay', delivery_to_pay='$del_to_pay', return_date='".$sub_last['to']."', deal_status='', last_sub_deal_ch_time='".time()."' WHERE deal_id='$deal_id'";
			if (!$mysqli->query($query_dl_upd, $db_server)) {
				$done="no";
				echo "Сбой при вставке данных: '$query_dl_upd' <br />"." (".$mysqli->connect_errno.") ". $mysqli->connect_error;
			}

			//удаление закрытия и платежа
			$query_del_sub = "DELETE FROM rent_sub_deals_arch WHERE deal_id='$deal_id' AND `type` IN ('close', 'cl_payment', 'cl_refund')";
			if (!$mysqli->query($query_del_sub, $db_server)) {
				$done="no";
                echo "Сбой при вставке данных: '$query_del_sub' <br />"." (".$mysqli->connect_errno.") ". $mysqli->connect_error;
			}

				//перенос записей суб. сделок в активную базу
				$query_arch_sub = "INSERT INTO rent_sub_deals_act SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, `info`, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place`, ch_num, sd_cat_id, sd_model_id, sd_inv_n FROM rent_sub_deals_arch WHERE deal_id='$deal_id'";
				if (!$mysqli->query($query_arch_sub)) {
                    $done="no";
                    die('Сбой при доступе к базе данных: '.$query_arch_sub.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
				}

				// далее делаем удаление суб. сделок
				$query_del_sub = "DELETE FROM rent_sub_deals_arch WHERE deal_id='$deal_id'";
				if (!$mysqli->query($query_del_sub)) {
				    $done="no";
                    die('Сбой при доступе к базе данных: '.$query_del_sub.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
				}

				//перенос записей основной сделки в активную базу
				$query_arch_dl = "INSERT INTO rent_deals_act SELECT deal_id, client_id, item_inv_n, start_date, return_date, delivery_yn, delivery_to_pay, delivery_paid, r_to_pay, r_paid, collateral_amount, collateral_cur, deal_status, deal_info, acc_person_id, cr_who_id, cr_time, last_sub_deal_ch_time, planned_return_date, deal_set, first_rent_place FROM rent_deals_arch WHERE deal_id='$deal_id'";
				if (!$mysqli->query($query_arch_dl)) {
                    $done="no";
                    die('Сбой при доступе к базе данных: '.$query_arch_dl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
				}


				// далее делаем удаление сделок
				$query_del_dl = "DELETE FROM rent_deals_arch WHERE deal_id='$deal_id'";
				if (!$mysqli->query($query_del_dl)) {
                    $done="no";
                    die('Сбой при доступе к базе данных: '.$query_del_dl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
				}


				// меняем статус товара на "занято" + добавляем deal_id
				$query_upd = "UPDATE tovar_rent_items SET status='rented_out', active_deal_id='$deal_id' WHERE item_inv_n='".$deals_def['item_inv_n']."'";
				if (!$mysqli->query($query_upd)) {
				    $done="no";
                    die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
				}

				// делаем корректировку информации на клиенте (для быстрого вывода истории)
					//через id сделки ищем всю сделку
					$query_cldl = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
					$result_cldl = $mysqli->query($query_cldl);
					if (!$result_cldl) {
                        $done="no";
                        die('Сбой при доступе к базе данных: '.$query_cldl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
                    }
					$cldl=$result_cldl->fetch_assoc();

					// через id клиента ищем клиента
					$query_cl = "SELECT * FROM clients WHERE client_id='".$cldl['client_id']."'";
					$result_cl = $mysqli->query($query_cl);
					if (!$result_cl) {
                        $done="no";
                        die('Сбой при доступе к базе данных: '.$query_cl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
                    }
					$cl=$result_cl->fetch_assoc();

					// находим дату последней архивной сделки
					$query_last_adl = "SELECT * FROM rent_deals_arch WHERE client_id='".$cldl['client_id']."' ORDER BY return_date DESC LIMIT 1";
					$result_last_adl = $mysqli->query($query_last_adl);
					if (!$result_last_adl) {
                        $done="no";
                        die('Сбой при доступе к базе данных: '.$query_last_adl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
                    }
					$last_adl=$result_last_adl->fetch_assoc();

					// обновляем информацию о сделках клиента
					$query_cl_upd = "UPDATE clients SET arch_n='".($cl['arch_n']-1)."', arch_amount='".($cl['arch_amount']-$deals_def['r_to_pay'])."', arch_l_date='".$last_adl['return_date']."' WHERE client_id='".$cldl['client_id']."'";
					if (!$mysqli->query($query_cl_upd)) {
						$done="no";
                        die('Сбой при доступе к базе данных: '.$query_cl_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
					}



			//завершение
			if ($done=='yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
					if (!$result_fin) die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

					$worning='<div class="cans_wor">
							<form action="dogovor_new.php" method="post">

							Отмена возврата успешно осуществлена.

								<input type="hidden" name="item_inv_n" value="'.$deals_def['item_inv_n'].'" />
								<input type="hidden" name="client_id" value="'.$deals_def['client_id'].'" />
								<input type="submit" name="" value="перейти к договору" />

							</form></div>';

				}
			else {
				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
					if (!$result_fin) die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
				}



		break;

		case 'в архив':

			$sort_rool=' WHERE deal_id=\''.$deal_id.'\'';

		break;

		case 'удалить сделку':

			$done="yes";

			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
			if (!$result_start) {
                $done="no";
                die('Сбой при доступе к базе данных: '.$result_start.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
            }




			//удаление суб. сделок
					$query_del_sub = "DELETE FROM rent_sub_deals_arch WHERE deal_id='$deal_id'";
					if (!$mysqli->query($query_del_sub)) {
					    $done="no";
                        die('Сбой при доступе к базе данных: '.$query_del_sub.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
					}


			// далее делаем удаление сделок
					$query_del_dl = "DELETE FROM rent_deals_arch WHERE deal_id='$deal_id'";
					if (!$mysqli->query($query_del_dl)) {
					    $done="no";
                        die('Сбой при доступе к базе данных: '.$query_del_dl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
					}


			//завершение
					if ($done=='yes') {
							$query_fin = "COMMIT";
							$result_fin = $mysqli->query($query_fin);
							if (!$result_fin) die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

								$worning='<div class="cans_wor">Сделка успешно удалена.</div>';

				    }
			else {
				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
							if (!$result_fin) die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
            }
			break;
	}

}















echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
'.Base::getBarCodeReaderScript().'
</head>
<title>Архивные сделки</title>
<body>'.$worning.'
';

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

?>

<script language="javascript">

history.pushState(null, null, location.href);
window.onpopstate = function(event) {
    history.go(1);
};

function hist_displ (id) {

	if (document.getElementById('hist_div_'+id).style.display=="none") {
		document.getElementById('hist_div_'+id).style.display="";
		document.getElementById('hist_but_'+id).value="cкрыть историю";
	}
	else {
		document.getElementById('hist_div_'+id).style.display="none";
		document.getElementById('hist_but_'+id).value="история";
	}
}

function del_arch () {
	var output = true;

	if (confirm ('Вы уверены, что хотите удалить сделку из архива (информация не восстанавливается)?')) {
		output = true;
	}
	else {
		output = false;
	}

	return output;

}

</script>



<?php

echo '
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
</div>


		';



//главная выборка
$query = "SELECT * FROM rent_deals_arch".$sort_rool." order by arch_time DESC LIMIT 0,100";
$result = $mysqli->query($query);
if (!$result) die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);



echo '<table border="1" cellspacing="0">
		<tr>
			<th scope="col">архив</th>
			<th scope="col">клиент</th>
			<th scope="col">инв.№</th>
			<th scope="col">с</th>
			<th scope="col">по</th>
			<th scope="col">к оплате</th>
			<th scope="col">оплачено</th>
			<th scope="col" style="width:200px;">действия</th>
		</tr>';

while ($deals_def=$result->fetch_assoc()) {




	$query_sub_dl_def = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='".$deals_def['deal_id']."' ORDER BY sub_deal_id DESC";
	$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
	if (!$result_sub_dl_def) die('Сбой при доступе к базе данных: '.$query_sub_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);



$hist_info='

<table border="1" cellspacing="0" id="hist_table">
	<tr>
		<th>операция</th>
		<th>с</th>
		<th>по</th>
		<th>тариф</th>
		<th>шаг</th>
		<th>кол-во по тарифу</th>
		<th>ст-ть аренды</th>
		<th>канал оплаты</th>
		<th>доставка (да\нет)</th>
		<th>стоимость доставки</th>
		<th>канал оплаты</th>
		<th>итого к оплате</th>
		<th>итого оплачено</th>
		<th>iii</th>
		<th style="width:100px;">Действия</th>
	</tr>';

while ($sub_dl_def=$result_sub_dl_def->fetch_assoc()) {

switch ($sub_dl_def['type']) {
	case 'first_rent':
		$hist_info.='
	<tr>
		<td>первоначальная сдача - '.$sub_dl_def['cr_who_id'].'</td>
		<td>'.date("d.m.Y",$sub_dl_def['from']).'</td>
				<td>'.date("d.m.Y",$sub_dl_def['to']).'</td>
						<td>'.number_format($sub_dl_def['tarif_value'], 1, ',', ' ').'</td>
		<td>'.$sub_dl_def['tarif_step'].'</td>
				<td>'.number_format($sub_dl_def['rent_tenor'], 1, ',', ' ').'</td>
		<td>'.number_format($sub_dl_def['r_to_pay'], 1, ',', ' ').'</td>
		<td></td>
				<td>'.($sub_dl_def['delivery_yn']=='1' ? 'Да' : 'Нет').'</td>
		<td>'.number_format($sub_dl_def['delivery_to_pay'], 1, ',', ' ').'</td>
						<td></td>
		<td>'.number_format(($sub_dl_def['r_to_pay']+$sub_dl_def['delivery_to_pay']), 1, ',', ' ').'</td>
				<td></td>
		<td>'.$sub_dl_def['status'].'<br />'.$sub_dl_def['info'].' ('.date("d-m-Y", $sub_dl_def['cr_time']).'---'.date("H:i", $sub_dl_def['cr_time']).')</td>
		<td></td>
	</tr>';

				break;


	case 'takeaway_plan':
					$hist_info.='
	<tr>
		<td>бронь - '.$sub_dl_def['cr_who_id'].'</td>
		<td>'.date("d.m.Y",$sub_dl_def['from']).'</td>
				<td>'.date("d.m.Y",$sub_dl_def['to']).'</td>
						<td>'.number_format($sub_dl_def['tarif_value'], 1, ',', ' ').'</td>
		<td>'.$sub_dl_def['tarif_step'].'</td>
				<td>'.number_format($sub_dl_def['rent_tenor'], 1, ',', ' ').'</td>
		<td>'.number_format($sub_dl_def['r_to_pay'], 1, ',', ' ').'</td>
		<td></td>
				<td>'.($sub_dl_def['delivery_yn']=='1' ? 'Да' : 'Нет').'</td>
		<td>'.number_format($sub_dl_def['delivery_to_pay'], 1, ',', ' ').'</td>
						<td></td>
		<td>'.number_format(($sub_dl_def['r_to_pay']+$sub_dl_def['delivery_to_pay']), 1, ',', ' ').'</td>
				<td></td>
		<td>'.$sub_dl_def['status'].'<br />'.$sub_dl_def['info'].' ('.date("d-m-Y", $sub_dl_def['cr_time']).'---'.date("H:i", $sub_dl_def['cr_time']).')</td>
		<td></td>
	</tr>';

			break;

	case 'extention':
	$hist_info.='
	<tr>
		<td>продление - '.$sub_dl_def['cr_who_id'].'</td>
		<td>'.date("d.m.Y",$sub_dl_def['from']).'</td>
		<td>'.date("d.m.Y",$sub_dl_def['to']).'</td>
		<td>'.number_format($sub_dl_def['tarif_value'], 1, ',', ' ').'</td>
		<td>'.$sub_dl_def['tarif_step'].'</td>
		<td>'.number_format($sub_dl_def['rent_tenor'], 1, ',', ' ').'</td>
		<td>'.number_format($sub_dl_def['r_to_pay'], 1, ',', ' ').'</td>
		<td></td>
		<td>'.($sub_dl_def['delivery_yn']=='1' ? 'Да' : 'Нет').'</td>
		<td>'.number_format($sub_dl_def['delivery_to_pay'], 1, ',', ' ').'</td>
		<td></td>
		<td>'.number_format(($sub_dl_def['r_to_pay']+$sub_dl_def['delivery_to_pay']), 1, ',', ' ').'</td>
		<td></td>
		<td>'.$sub_dl_def['status'].'<br />'.$sub_dl_def['info'].' ('.date("d-m-Y", $sub_dl_def['cr_time']).'---'.date("H:i", $sub_dl_def['cr_time']).')</td>
		<td></td>
	</tr>';

				break;


	case 'close':
	case 'cur_close':
	$hist_info.='
	<tr>
		<td>закрытие - '.$sub_dl_def['cr_who_id'].'</td>
		<td>'.date("d.m.Y",$sub_dl_def['from']).'</td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td>'.number_format($sub_dl_def['r_to_pay'], 1, ',', ' ').'</td>
		<td></td>
		<td>'.($sub_dl_def['delivery_yn']=='1' ? 'Да' : 'Нет').'</td>
		<td>'.number_format($sub_dl_def['delivery_to_pay'], 1, ',', ' ').'</td>
		<td></td>
		<td>'.number_format(($sub_dl_def['r_to_pay']+$sub_dl_def['delivery_to_pay']), 1, ',', ' ').'</td>
		<td></td>
		<td>'.$sub_dl_def['status'].'<br />'.$sub_dl_def['info'].' ('.date("d-m-Y", $sub_dl_def['cr_time']).'---'.date("H:i", $sub_dl_def['cr_time']).')</td>
		<td><form action="deals_arch.php" method="post" style="display:inline-block;">
				<input type="hidden" name="deal_id" value="'.$deals_def['deal_id'].'" />
				<input type="submit" name="action" value="отменить возврат" />
			</form>
			</td>
	</tr>';

			break;






	case 'payment':
	case 'cl_payment':
		$hist_info.='
	<tr>
		<td>оплата - '.$sub_dl_def['cr_who_id'].'</td>
		<td>'.date("d.m.Y",$sub_dl_def['from']).'</td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td>'.number_format($sub_dl_def['r_paid'], 1, ',', ' ').'</td>
		<td>'.p_type($sub_dl_def['r_payment_type']).'</td>
		<td></td>
		<td>'.number_format($sub_dl_def['delivery_paid'], 1, ',', ' ').'</td>
		<td>'.p_type($sub_dl_def['del_payment_type']).'</td>
		<td></td>
		<td>'.number_format(($sub_dl_def['r_paid']+$sub_dl_def['delivery_paid']), 1, ',', ' ').'</td>
		<td>'.$sub_dl_def['status'].'<br />'.$sub_dl_def['info'].' ('.date("d-m-Y", $sub_dl_def['cr_time']).'---'.date("H:i", $sub_dl_def['cr_time']).')</td>
		<td></td>
	</tr>';




				break;


	default:
		$hist_info.='
	<tr>
		<td>случай не прописан</td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td>'.$sub_dl_def['type'].'</td>
		<td></td>
	</tr>';

				break;
}//end of switch

}// end of while
$hist_info.='</table>';





echo'
	<tr>
		<td>'.date("d.m.y", $deals_def['arch_time']).' <i>('.date("H:i", $deals_def['arch_time']).')</i> - '.$deals_def['cr_who_id'].'</td>
		<td>'.$deals_def['client_id'].'</td>
		<td>'.$deals_def['item_inv_n'].'</td>
		<td>'.date("d.m.y", $deals_def['start_date']).'</td>
		<td>'.date("d.m.y", $deals_def['return_date']).'</td>
		<td>'.($deals_def['delivery_to_pay']+$deals_def['r_to_pay']).'</td>
		<td>'.($deals_def['delivery_paid']+$deals_def['r_paid']).'</td>
		<td><input type="button" value="история" id="hist_but_'.$deals_def['arch_deal_id'].'" onclick="hist_displ(\''.$deals_def['arch_deal_id'].'\'); return false;" />';

	if ($_SESSION['level']>4) {
		echo '
			<form method="post" action="deals_arch.php" style="display:inline-block;">
					<input type="hidden" name="deal_id" value="'.$deals_def['deal_id'].'" />
					<input type="submit" name="action" value="удалить сделку" onclick="return del_arch();"/>
			</form>';
		}

		echo'<div style="position:relative;">
				<div class="hist_div" id="hist_div_'.$deals_def['arch_deal_id'].'" style="display:none;">'.$hist_info.'</div>
			</div>

		</td>
	</tr>';


}

echo '</table>';








function p_type ($p_type) {
	switch ($p_type) {
		case 'nal_no_cheque':
			$output='нал без чека';
			break;

		case 'nal_cheque':
			$output='нал с чеком';
			break;

		case 'card':
			$output='карточка';
			break;

		case 'bank':
			$output='банк';
			break;

		case '':
			$output='';
			break;

		default:
			$output='ХЗ';
			break;

	}
	return $output;

}


function get_post($var)
{
    $mysqli = \bb\Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
}



?>
