<?php
session_start();

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Signature.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //

//------- proverka paroley

$mysqli = \bb\Db::getInstance()->getConnection();

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


$start_code='

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	</head>
	<body>


		';





foreach ($_POST as $key => $value) {
					$$key = get_post($key);
				}

if ($action_type=='hist_a_show') {

					// должен быть входящий $deal_id

					$query_dl_def = "SELECT * FROM rent_deals_arch WHERE deal_id='$deal_id'";
					$result_dl_def = $mysqli->query($query_dl_def);
					if (!$result_dl_def) {
            die('Сбой при доступе к базе данных: '.$query_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
          }
					$dl_def=$result_dl_def->fetch_assoc();

					$query_sub_dl_def = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='$deal_id' ORDER BY sub_deal_id DESC";
					$result_sub_dl_def = $mysqli->query($query_sub_dl_def);
					if (!$result_sub_dl_def) {
            die('Сбой при доступе к базе данных: '.$query_sub_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
          }

					$item_output='';

					$item_output.='
	<strong>Детальная информация по сделке/История:</strong>
	<table border="1" cellspacing="0" id="hist_table" style="background-color:#73C4CA;">
	<tr>
	<th>операция</th>
	<th>с</th>
	<th>по</th>
	<th>период</th>
	<th>сумма</th>
	<th>дата оплаты</th>
	<th>оплачено</th>
	<th>доставка</th>
	<th style="width:100px;">Действия</th>
	</tr>';

					$prodl=0;// для того, чтобы напечатать удаление только для первого-последнего продления и для того, чтобы сделать disable кнопки первого платежа при наличии продления
					$first_r_only=1;
					while ($sub_dl_def=$result_sub_dl_def->fetch_assoc()) {

						$_SESSION['user_id']==3 ? $sub_id_show='('.$sub_dl_def['sub_deal_id'].') - '.$sub_dl_def['cr_who_id'] : $sub_id_show='';
						$_SESSION['user_id']==3 ? $link_id_show='('.$sub_dl_def['link'].')  - '.$sub_dl_def['cr_who_id'] : $link_id_show='';

						switch ($sub_dl_def['type']) {
							case 'first_rent':

								$query_sub_pay = "SELECT * FROM rent_sub_deals_arch WHERE `type`='payment' AND link='".$sub_dl_def['sub_deal_id']."' ORDER BY sub_deal_id DESC";
								$result_sub_pay = $mysqli->query($query_sub_pay);
								if (!$result_sub_pay) {
                  die('Сбой при доступе к базе данных: '.$query_sub_pay.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
                }

								$p_dates='';
								$p_sums='';

								$p_date=0;
								$p_del_b='';
								while ($sub_pay=$result_sub_pay->fetch_assoc()) {
									$p_dates.=date("d.m.y", $sub_pay['from']).'<br/>';
									$p_sums.=$sub_pay['r_paid'].' '.sh_kassa($sub_pay['r_payment_type']).($sub_pay['r_payment_type']!='nal_no_cheque' ? '('.$sub_pay['ch_num'].')' : '').'<br/>';

									$p_date=$sub_pay['from'];

								}

								$item_output.='
						<tr>
							<td>'.($sub_dl_def['status']=='for_cur' ? '<strong>Отложено для курьера:</strong><br />' : '').'выдача ['.$sub_dl_def['place'].']</td>
							<td>'.date("d.m.Y",$sub_dl_def['from']).'</td>
							<td>'.date("d.m.Y",$sub_dl_def['to']).'</td>
							<td>'.number_format($sub_dl_def['rent_tenor'], 1, ',', ' ').' '.step_pr($sub_dl_def['tarif_step']).'</td>
							<td>'.number_format($sub_dl_def['r_to_pay'], 1, ',', ' ').'</td>
							<td>'.$p_dates.'</td>
							<td>'.$p_sums.'</td>
							<td>'.($sub_dl_def['delivery_yn']=='1' ? 'Да' : 'Нет').'</td>
							<td>	'.($first_r_only==1 ? $p_del_b : '').'
								</td>
	</tr>';

								break;

							case 'takeaway_plan':

								$query_sub_pay = "SELECT * FROM rent_sub_deals_arch WHERE `type`='payment' AND link='".$sub_dl_def['sub_deal_id']."' ORDER BY sub_deal_id DESC";
								$result_sub_pay = $mysqli->query($query_sub_pay);
								if (!$result_sub_pay) {
                  die('Сбой при доступе к базе данных: '.$query_sub_pay.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
                }

								$p_dates='';
								$p_sums='';

								$p_date=0;
								$p_del_b='';
								while ($sub_pay=$result_sub_pay->fetch_assoc()) {
									$p_dates.=date("d.m.y", $sub_pay['from']).'<br/>';
									$p_sums.=$sub_pay['r_paid'].' '.sh_kassa($sub_pay['r_payment_type']).($sub_pay['r_payment_type']!='nal_no_cheque' ? '('.$sub_pay['ch_num'].')' : '').'<br/>';

									$p_date=$sub_pay['from'];

								}

								$item_output.='
	<tr>
		<td>'.($sub_dl_def['status']=='for_cur' ? '<strong>Отложено для курьера:</strong><br />' : '').'бронь</td>
		<td>'.($item_cat_n=='702' ? date("d.m.Y (H:i)",$sub_dl_def['from']) : date("d.m.Y",$sub_dl_def['from'])).'</td>
		<td>'.($item_cat_n=='702' ? date("d.m.Y (H:i)",$sub_dl_def['to']) : date("d.m.Y",$sub_dl_def['to'])).'</td>
		<td>'.number_format($sub_dl_def['rent_tenor'], 1, ',', ' ').' '.step_pr($sub_dl_def['tarif_step']).'</td>
		<td>'.number_format($sub_dl_def['r_to_pay'], 1, ',', ' ').'</td>
		<td>'.$p_dates.'</td>
		<td>'.$p_sums.'</td>
		<td>'.($sub_dl_def['delivery_yn']=='1' ? 'Да' : 'Нет').'</td>
		<td>	'.($first_r_only==1 ? $p_del_b : '').'

			</td>
	</tr>';

								break;

							case 'extention':
								$first_r_only=0;

								$query_sub_pay = "SELECT * FROM rent_sub_deals_arch WHERE `type`='payment' AND link='".$sub_dl_def['sub_deal_id']."' ORDER BY sub_deal_id DESC";
								$result_sub_pay = $mysqli->query($query_sub_pay);
								if (!$result_sub_pay) {
                  die('Сбой при доступе к базе данных: '.$query_sub_pay.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
                }

								$p_dates='';
								$p_sums='';

								while ($sub_pay=$result_sub_pay->fetch_assoc()) {
									$p_dates.=date("d.m.y", $sub_pay['from']).'<br/>';
									$p_sums.=$sub_pay['r_paid'].' '.sh_kassa($sub_pay['r_payment_type']).($sub_pay['r_payment_type']!='nal_no_cheque' ? '('.$sub_pay['ch_num'].')' : '').'<br/>';
								}

								$item_output.='
	<tr>
		<td>'.($sub_dl_def['status']=='for_cur' ? '<strong>Забор курьером: </strong><br />' : '').'продление</td>
		<td>'.date("d.m.Y",$sub_dl_def['from']).'</td>
		<td>'.date("d.m.Y",$sub_dl_def['to']).'</td>
		<td>'.number_format($sub_dl_def['rent_tenor'], 1, ',', ' ').' '.step_pr($sub_dl_def['tarif_step']).'</td>
		<td>'.number_format($sub_dl_def['r_to_pay'], 1, ',', ' ').'</td>
		<td>'.$p_dates.'</td>
		<td>'.$p_sums.'</td>
		<td>'.($sub_dl_def['delivery_yn']=='1' ? 'Да' : 'Нет').'</td>
		<td>
			</td>
	</tr>';
								$prodl+=1;

								break;

	case 'payment':
			continue;
	break;
	case 'cl_payment':
		continue;
	break;


	case 'cur_return':
		$first_r_only=0;

		$item_output.='
	<tr>
		<td><strong>заказ курьера на возврат</strong></td>
		<td>'.date("d.m.Y",$sub_dl_def['from']).'</td>
		<td colspan="5"><strong>Доп. информация по возврату:</strong> <br />'.$sub_dl_def['info'].'</td>
		<td>Да</td>
		<td></td>
	</tr>';

								break;

							case 'close':

								$query_sub_pay = "SELECT * FROM rent_sub_deals_arch WHERE `type` IN ('payment', 'cl_payment') AND link='".$sub_dl_def['sub_deal_id']."' ORDER BY sub_deal_id DESC";
								$result_sub_pay = $mysqli->query($query_sub_pay);
								if (!$result_sub_pay) {
                  die('Сбой при доступе к базе данных: '.$query_sub_pay.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
                }

								$p_dates='';
								$p_sums='';

								$p_date=0;
								$p_del_b='';
								while ($sub_pay=$result_sub_pay->fetch_assoc()) {
									$p_dates.=date("d.m.y", $sub_pay['from']).'<br/>';
									$p_sums.=$sub_pay['r_paid'].' '.sh_kassa($sub_pay['r_payment_type']).($sub_pay['r_payment_type']!='nal_no_cheque' ? '('.$sub_pay['ch_num'].')' : '').'<br/>';

									$p_date=$sub_pay['from'];

								}

								$item_output.='
						<tr>
							<td> возврат['.$sub_dl_def['place'].']</td>
							<td>'.date("d.m.Y",$sub_dl_def['from']).'</td>
							<td></td>
							<td></td>
							<td>'.number_format($sub_dl_def['r_to_pay'], 1, ',', ' ').'</td>
							<td>'.$p_dates.'</td>
							<td>'.$p_sums.'</td>
							<td>'.($sub_dl_def['delivery_yn']=='1' ? 'Да' : 'Нет').'</td>
							<td>	'.($first_r_only==1 ? $p_del_b : '').'
								</td>
	</tr>';

								break;



							default:
								$item_output.='
	<tr>
		<td>случай не прописан'.$sub_id_show.'</td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
	</tr>';

								break;
						}


					}
					$item_output.='</table> <br />
	<input type="hidden" name="arch2_deal_id" value="'.$deal_id.'" />
	<input type="submit" name="action" value="удалить архивную сделку" />


';



					$item_output=str_replace(array("\r\n", "\r", "\n"), "", $item_output); //превращаем в одну строку, иначе javascript не поймет


					die('
			document.getElementById(\'a_hist_div\').innerHTML=\''.$item_output.'\';');


}



function get_post($var)
{
  $mysqli=\bb\Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
}




function tenor_print($step_name, $value) {

switch ($step_name) {

	case 'day':

		if ($value=='1') {return 'день';}
		elseif ($value=='0') {return 'дней';}
		elseif ($value>1 && $value <5) {return 'дня';}
		elseif ($value>4 && $value <20) {return 'дней';}
		elseif ($value=='d') {return 'день';}

	break;


	case 'week':

		if ($value=='1') {return 'неделя';}
		elseif ($value=='0') {return 'недели';}
		elseif ($value>1 && $value <5) {return 'недели';}
		elseif ($value>4 && $value <20) {return 'недель';}
		elseif ($value=='d') {return 'неделю';}

	break;


	case 'month':

		if ($value=='1') {return 'месяц';}
		elseif ($value=='0') {return 'месяцев';}
		elseif ($value>1 && $value <5) {return 'месяца';}
		elseif ($value>4 && $value <20) {return 'месяцев';}
		elseif ($value=='d') {return 'месяц';}

	break;

}//end of switch
}//end of function


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


function phone_print ($ph) {
	if ($ph=='') {return '';}

	$dl=strlen($ph);

	if ($dl<7) {return $ph;}

	$dl>7 ? $dl_to=$dl-7 : $dl_to=0;
	$ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
	return $ph_out;

}


function good_print($var) {
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}


function pay_calc($deal_id, $ret_date) {
  $mysqli=\bb\Db::getInstance()->getConnection();
	//запрос информации о сделке
	$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
	$result_dl_def = $mysqli->query($query_dl_def);
	if (!$result_dl_def) {
    die('Сбой при доступе к базе данных: '.$query_dl_def.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
  }
	$dl_def=$result_dl_def->fetch_assoc();

	//вытягиваем последний примененный тариф
	$query_sub_dl_tarif = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND type IN ('first_rent', 'extention', 'takeaway_plan') ORDER BY `from` DESC";
	$result_sub_dl_tarif = $mysqli->query($query_sub_dl_tarif);
	if (!$result_sub_dl_tarif) {
    die('Сбой при доступе к базе данных: '.$query_sub_dl_tarif.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
  }
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
		$morepay='срок не наступил';
		$to_pay_ad='0';
	}





	return $morepay;
}// end of pay_calc function

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

function sh_kassa ($kassa) {
	switch ($kassa) {
		case 'nal_no_cheque':
			return 'к2';
			break;

		case 'nal_cheque':
			return 'нал';
			break;

		case 'card':
			return 'карт';
			break;

		case 'bank':
			return 'банк';
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

function inv_print ($inv_n) {

	$output=substr($inv_n, 0, 3).'-'.substr($inv_n, 3);

	return $output;

}

function rus_day ($day) {
	switch ($day) {
		case '1':
			return 'Понедельник';
			break;

		case '2':
			return 'Вторник';
			break;

		case '3':
			return 'Среда';
			break;

		case '4':
			return 'Четверг';
			break;

		case '5':
			return 'Пятница';
			break;

		case '6':
			return 'Суббота';
			break;

		case '0':
			return 'Воскресенье';
			break;
		default:
			return 'День не определен';
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
			return 'Света
			';
			break;
		default:
			return 'ХЗ';
			break;
	}
}

function stat_print ($stat) {
	switch ($stat) {
		case 'new':
			return 'не проверено';
			break;

		case 'ok':
			return 'подтверждено';
			break;

		case 'in_process':
			return 'временная бронь';
			break;

		default:
			return 'ХЗ';
			break;
	}
}
?>
