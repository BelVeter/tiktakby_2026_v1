<?php

use bb\Base;

session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/adv_monitor.php');

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');

$q_type='';
/*$fio='Дима';
$tel_pr='44';
$tel='7680743';
$dop_info='доп инфо';
$br_office='1';

$item_1_id='369';
$item_2_id='1051';
$br_time='1425219194';

*/



//Проверка входящей информации
//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//foreach ($_POST as $key => $value) {
//	echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

/*echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Товары.</title>
<body>';
*/



foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

if ($q_type=='br_start') {
	
	$br_time=time()+7*60;//на бронь 7 минут
	
	$model_def_q = "SELECT * FROM rent_model_web WHERE model_id='$model_id' LIMIT 1";
	$result_model_def = $mysqli->query($model_def_q);
	if (!$result_model_def) {die('Сбой при доступе к базе данных: '.$model_def_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$model_def=$result_model_def->fetch_assoc();
	
	//блокируем таблицу
	$q_lock = "LOCK TABLE tovar_rent_items WRITE";
	$result_lock = $mysqli->query($q_lock);
	if (!$result_lock) {die('Сбой при доступе к базе данных: '.$q_lock.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	
	$free_q1 = "SELECT (item_id) FROM tovar_rent_items WHERE model_id='$model_id' AND (`status`='to_rent' OR (`status`='t_bron' AND br_time<".time().")) AND item_place='1' LIMIT 1";
	$result_free1 = $mysqli->query($free_q1);
	if (!$result_free1) {die('Сбой при доступе к базе данных: '.$free_q1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$free1=$result_free1->num_rows;
	$free1_id=$result_free1->fetch_assoc();
	
		if ($free1>0) {//меняем статус для того, чтобы дать 7 минут на бронь
			
			$query_upd = "UPDATE tovar_rent_items SET `status`='t_bron', br_time='".$br_time."' WHERE item_id='".$free1_id['item_id']."'";
			$result = $mysqli->query($query_upd);
			if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		}
	
	
	
	$free_q2 = "SELECT (item_id) FROM tovar_rent_items WHERE model_id='$model_id' AND (`status`='to_rent' OR (`status`='t_bron' AND br_time<".time().")) AND item_place='2' LIMIT 1";
	$result_free2 = $mysqli->query($free_q2);
	if (!$result_free2) {die('Сбой при доступе к базе данных: '.$free_q2.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$free2=$result_free2->num_rows;
	$free2_id=$result_free2->fetch_assoc();
	
		if ($free2>0) {//меняем статус для того, чтобы дать 7 минут на бронь
				
			$query_upd = "UPDATE tovar_rent_items SET `status`='t_bron', br_time='".$br_time."' WHERE item_id='".$free2_id['item_id']."'";
			$result = $mysqli->query($query_upd);
			if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		}
		
		
	$free_q3 = "SELECT (item_id) FROM tovar_rent_items WHERE model_id='$model_id' AND (`status`='to_rent' OR (`status`='t_bron' AND br_time<".time().")) AND item_place='3' LIMIT 1";
	$result_free3 = $mysqli->query($free_q3);
	if (!$result_free3) {die('Сбой при доступе к базе данных: '.$free_q3.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$free3=$result_free3->num_rows;
	$free3_id=$result_free3->fetch_assoc();
		
		if ($free3>0) {//меняем статус для того, чтобы дать 7 минут на бронь
		
			$query_upd = "UPDATE tovar_rent_items SET `status`='t_bron', br_time='".$br_time."' WHERE item_id='".$free3_id['item_id']."'";
			$result = $mysqli->query($query_upd);
			if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		}
	
	//снимаем блокировку таблиц
	$q_lock = "UNLOCK TABLES";
	$result_lock = $mysqli->query($q_lock);
	if (!$result_lock) {die('Сбой при доступе к базе данных: '.$q_lock.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	
	
	
	
	$output='
			<input type="hidden" id="item_1_id" value="'.$free1_id['item_id'].'" />
			<input type="hidden" id="item_2_id" value="'.$free2_id['item_id'].'" />
			<input type="hidden" id="item_3_id" value="'.$free3_id['item_id'].'" />
			<input type="hidden" id="br_time" value="'.$br_time.'" />
			';
	
	if ($free1<1 && $free2<1 && $free3<1) {
		$output.='Сожалеем. В настоящий момент данного товара нет в наличии. <br />Однако, Вы можете оставить заявку.<br />Как только товар освободится наш менеджер свяжется с Вами. <br /><br />
			<table>
				<tr>
					<td>ФИО:</td>
					<td><input type="text" name="fio" id="fio" style="width:300px;" /></td>
				</tr>
				<tr>
					<td>Контактный телефон:</td>
					<td>+375 <input type="text" id="tel" style="width:120px; font-size: 14px;" /> </td>
				</tr>
				<tr>
					<td>Срок действия заявки:</td>
					<td>
						<input type="radio" name="br_tenor" id="br_tenor_1"> <label for="br_tenor_1">1 неделя (по '.date("d.m.Y", (time()+24*7*3600)).')</label><br />
						<input type="radio" name="br_tenor" id="br_tenor_2"> <label for="br_tenor_2">2 недели (по '.date("d.m.Y", (time()+24*14*3600)).')</label><br />
						<input type="radio" name="br_tenor" id="br_tenor_3"> <label for="br_tenor_3">3 недели (по '.date("d.m.Y", (time()+24*21*3600)).')</label>
					</td>
				</tr>	
				<tr>
					<td>Доп. информация:</td>
					<td><textarea name="dop_info" id="dop_info" rows="4" cols="50"></textarea></td>
				</tr>
				
			</table>
				
				<input type="button" value="Оставить заявку" onclick="fbq(\\\'track\\\', \\\'AddToWishlist\\\'); br_zayav(\\\''.$model_id.'\\\');" /> 
				<input type="button" value="Закрыть" onclick="br_zakr();" />';
	}
	elseif ($free1>0 || $free2>0 || $free3>0) {
		$dost_visible='';
		if ($model_def['cat_id']==7 || $model_def['cat_id']==57) {
			$dost_visible='style="display:none;"';
		}
		$output.='
				'.addslashes($model_def['item_name_main']).' в наличии по адресу:<br />
						'.($free1>0 ? '- ул. Литературная 22 <br />' : '').'
						'.($free2>0 ? '- ул.Ложинская 5 (ст. м. "Уручье")' : '').' 
						'.($free3>0 ? '- пр-т. Победителей 125 (район Минск-Арена)' : '').' 
						
						<br /> <br />
				
				Выберите способ передачи товара:<br /> 
					<input type="radio" name="takeaway_br" id="takeaway_br_1" onchange="pered_tov(\\\'office\\\');"><label for="takeaway_br_1">Самовывоз</label>
					<input type="radio" name="takeaway_br" id="takeaway_br_2" onchange="pered_tov(\\\'deliv\\\');" '.$dost_visible.'><label for="takeaway_br_2" '.$dost_visible.'>Доставка</label>
				<div id="br_of_div" style="display:none;">
				
			<table>
				<tbody id="sam_vivoz" style="display:none;">
				<tr>
					<td>Самовывоз с:</td>
					<td>
				';
		
		
		
		
		if ($free1>0) {
			$output.='<input type="radio" name="br_of_radio" id="br_of_radio_1"><label for="br_of_radio_1">Литературная, 22</label> ';
		}
		if ($free2>0) {
			$output.='<input type="radio" name="br_of_radio" id="br_of_radio_2"><label for="br_of_radio_2">Ложинская, 5</label> ';
		}
		if ($free3>0) {
			$output.='<br><input type="radio" name="br_of_radio" id="br_of_radio_3"><label for="br_of_radio_3">Победителей 125</label> ';
		}
				
				
			$output.='	
				</td>
				</tr>				
				</tbody>				
				<tr>
					<td>ФИО:</td>
					<td><input type="text" name="fio" id="fio" style="width:300px;" /></td>
				</tr>
				<tr>
					<td>Контактный телефон:</td>
					<td>+375 <input type="text" id="tel" style="width: 120px; font-size: 14px;" /> 
					    <br><span style="font-style: italic;position: relative;left: 61px;font-weight: normal;font-size: 12px;color: #e4cbcb;">29-111-11-11</span>
					    </td>
				</tr>
				<tbody id="deliv_row" style="display:none;">
				<tr>
					<td>Адрес доставки:</td>
					<td><input type="text" style="width:300px;" id="deliv_addr" /></td>
				</tr>					
				</tbody>	
				<tr>
					<td>Доп. информация:</td>
					<td><textarea name="dop_info" id="dop_info" rows="4" cols="50"></textarea></td>
				</tr>
			</table>
				<br /><input type="button" value="Заказать" onclick="fbq(\\\'track\\\', \\\'AddToCart\\\'); br_office(\\\''.$model_id.'\\\');" /> 
				</div>
				<br /><input type="button" value="Отмена" onclick="br_cans();" />	
				';
	
	
	}//end of elseif	
	
	
	
	$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет
	
	echo 'document.getElementById(\'bron_main_div\').innerHTML=\''.$output.'\';
		
			';

	/*
	document.getElementById(\'last_temp_br_id\').value=\''.$br_id.'\';
			document.getElementById(\'last_inv_n_br\').value=\''.$inv_n.'\';
	*/
	
}//end o br_start if


if ($q_type=='sam_bron') {
	
	//блокируем таблицу
	$q_lock = "LOCK TABLES tovar_rent_items WRITE, rent_orders WRITE";
	$result_lock = $mysqli->query($q_lock);
	if (!$result_lock) {die('Сбой при доступе к базе данных: '.$q_lock.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	
	
if ($br_sposob=='office') {	//для самозабора
	if ($br_office==1) {
		$item_id=$item_1_id;
		//освобождаем - меняем статус другого товара
		$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent', br_time='0' WHERE item_id='$item_2_id' AND br_time='$br_time' AND `status`='t_bron'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		
	}
	elseif ($br_office==2) {
		$item_id=$item_2_id;
		
		//освобождаем - меняем статус другого товара
		$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent', br_time='0' WHERE item_id='$item_1_id' AND br_time='$br_time' AND `status`='t_bron'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		
	}
	elseif ($br_office==3) {
		$item_id=$item_3_id;
	
		//освобождаем - меняем статус другого товара
		$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent', br_time='0' WHERE item_id='$item_3_id' AND br_time='$br_time' AND `status`='t_bron'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	
	}
}
else { //для доставки
	if ($item_1_id>0) { // сначала оформляем доставку с Литературная, и только если товара нет - с Ложинской
		$item_id=$item_1_id;
		//освобождаем - меняем статус другого товара
		$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent', br_time='0' WHERE (item_id='$item_2_id' OR item_id='$item_3_id') AND br_time='$br_time' AND `status`='t_bron'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		
	}
	elseif ($item_2_id>0) {
		$item_id=$item_2_id;
		
		//освобождаем - меняем статус другого товара
		$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent', br_time='0' WHERE (item_id='$item_1_id' OR item_id='$item_3_id') AND br_time='$br_time'  AND `status`='t_bron'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	}
	
	elseif ($item_3_id>0) {
		$item_id=$item_3_id;
	
		//освобождаем - меняем статус другого товара
		$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent', br_time='0' WHERE (item_id='$item_1_id' OR item_id='$item_2_id') AND br_time='$br_time'  AND `status`='t_bron'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	}
	
}	
	//сначала проверяем, не занят ли тот товар, который мы бронируем (может долго бронировали и его уже бронирует другой)
	$free_q1 = "SELECT * FROM tovar_rent_items WHERE item_id='$item_id' LIMIT 1";
	$result_free1 = $mysqli->query($free_q1);
	if (!$result_free1) {die('Сбой при доступе к базе данных: '.$free_q1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$free1=$result_free1->num_rows;
	$free1_def=$result_free1->fetch_assoc();

	
	if ((($free1_def['br_time']==$br_time) || ($free1_def['br_time']<time()))) {//проверяем, по времени, если время наше, либо чужое но уже прошедшее то бронируем

		//меняем статус товара
		$query_upd = "UPDATE tovar_rent_items SET `status`='bron', br_time='0' WHERE item_id='$item_id'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		
		//заносим бронь
		$ac_date=strtotime(date("Y-m-d"));
		$validity=strtotime(date("Y-m-d"))+2*24*60*60;

		//$info=$fio.($br_sposob=='deliv' ? '<br />Адрес доставки:'.$deliv_addr.'<br />' : '').'+375-'..'-'.$tel.'<br />'.$dop_info;

		$tel=Base::getNumbersOnly($tel);
		//разбор fio
        $fio_ar = explode(' ', $fio, 3);
        $f='';
        $i='';
        $o='';
        if (count($fio_ar>=1)) {
            $f=$fio_ar[0];
        }
        if (count($fio_ar>=2)) {
            $i=$fio_ar[1];
        }
        if (count($fio_ar>=3)) {
            $o=$fio_ar[2];
        }
		
		$br_sposob=='deliv' ? $deliv_yn=1 : $deliv_yn=0;
		$br_sposob=='deliv' ? $type_2_q='deliv' : $type_2_q='bron';

		$query = "INSERT INTO rent_orders VALUES ('', 'strong', '$ac_date', '$tel', '', '$f', '$i', '$o', '', '$deliv_addr', '$validity', '".$free1_def['item_inv_n']."', '".$free1_def['model_id']."', '".$free1_def['cat_id']."', '$type_2_q', '".Base::getAdvCompId()."', '$dop_info', '', '1', '".time()."', '', '', '', '".Base::getAdvCompId()."', '', '', '".$_SERVER['REMOTE_ADDR']."', '', '')";
		//echo '1:'.$query;
		$result = $mysqli->query($query);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		
		$edit_id=$mysqli->insert_id;	
		
		//сообщение
		if ($br_sposob=='office') {
			if ($free1_def['item_place']==1) {
				$dop_out='<p>Товар можно забрать по адресу: ул. Литературная 22</p></p> График работы: <br /> Пн-пт: 10:00 - 19:00 <br /> Сб,Вс: 10:00 - 16:00. </p>
					<p>Телефоны:<br />+375(29) 630-35-32 <br />
					</p>';
			}
			elseif ($free1_def['item_place']==2) {
				$dop_out='<p>Товар нужно забрать по адресу: ул.Ложинская 5 (ст. м. "Уручье")</p><p>График работы: <br /> Пн-пт: 10:00 - 19:00 <br /> Сб,Вс: 11:00-15:00</p>
					<p>Телефоны:<br />+375(29) 630-35-58 <br />
					</p>';
			}
			else {//третий офис
				$dop_out='<p>Товар нужно забрать по адресу: пр-т Победителй 125 (р-н Минск-Арена)</p><p>График работы: <br /> Пн-пт: 10:00 - 19:00 <br /> Сб: 11:00-15:00<br>Вс: 11:00-15:00</p>
					<p>Телефоны:<br />+375(29) 694-40-40 <br />
					</p>';
			}
		}
		else {
		$dop_out='<p>Доставка осуществляется в понедельник-пятница с 13.00 до 21.00, только в пределах МКАД.<br />
				Курьер с Вами свяжется заранее.</p>';
		}
		
		$output='<p>БЛАГОДАРИМ ЗА ЗАКАЗ!</p><p>Товар успешно забронирован. Номер брони: <span style="background-color:#FFF; color:#3ba1c8; padding:3px;">'.$edit_id.'&nbsp;</span></p>
				<p>ВНИМАНИЕ! Срок действия Вашей брони: 1 (одни) сутки. По истечении суток с момента бронирования бронь будет автоматически аннулирована.</p>
				'.$dop_out.'<br /><input type="button" value="Закрыть" onclick="br_zakr();" />';
	}
	else {//если наш товар занят, нужно поискать другой такой же на том же офисе
		
		$free_zap_q = "SELECT * FROM tovar_rent_items WHERE model_id='$model_id' AND (`status`='to_rent' OR (`status`='t_bron' AND br_time<".time().")) AND item_place='$br_office' LIMIT 1";
		$result_free_zap = $mysqli->query($free_zap_q);
		if (!$result_free_zap) {die('Сбой при доступе к базе данных: '.$free_zap_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$free_zap_n=$result_free_zap->num_rows;
		$free_zap=$result_free_zap->fetch_assoc();

		if ($free_zap_n>0) {
			//меняем статус товара
			$query_upd = "UPDATE tovar_rent_items SET `status`='bron', br_time='0' WHERE item_id='".$free_zap['item_id']."'";
			$result = $mysqli->query($query_upd);
			if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
			
			//заносим бронь
			$ac_date=strtotime(date("Y-m-d"));
			$validity=strtotime(date("Y-m-d"))+2*24*60*60;
			
			$info=$fio.($br_sposob=='deliv' ? '<br />Адрес доставки:'.$deliv_addr.'<br />' : '').'+375-'.$tel.'<br />'.$dop_info;
			
			$br_sposob=='deliv' ? $deliv_yn=1 : $deliv_yn=0;
			$br_sposob=='deliv' ? $type_2_q='deliv' : $type_2_q='bron';

            $tel=Base::getNumbersOnly($tel);
            //разбор fio
            $fio_ar = explode(' ', $fio, 3);
            $f='';
            $i='';
            $o='';
            if (count($fio_ar>=1)) {
                $f=$fio_ar[0];
            }
            if (count($fio_ar>=2)) {
                $i=$fio_ar[1];
            }
            if (count($fio_ar>=3)) {
                $o=$fio_ar[2];
            }
			
			$query = "INSERT INTO rent_orders VALUES ('', 'strong', '$ac_date', '$tel', '', '$f', '$i', '$o', '', '$deliv_addr', '$validity', '".$free_zap['item_inv_n']."', '".$free_zap['model_id']."', '".$free_zap['cat_id']."', '$type_2_q', '".Base::getAdvCompId()."', '$dop_info', '', '1', '".time()."', '', '', '', '".Base::getAdvCompId()."', '', '', '".$_SERVER['REMOTE_ADDR']."', '', '')";
            //echo '2:'.$query;
			$result = $mysqli->query($query);
			if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
			
			$edit_id=$mysqli->insert_id;
			
			//сообщение
			if ($br_sposob=='office') {
				if ($free1_def['item_place']==1) {
					$dop_out='<p>Товар можно забрать по адресу: ул. Литературная, 22 </p></p> График работы: <br /> Пн-пт: 10:00 - 19:00 <br /> Сб,Вс: выходной. </p>
						<p>Телефоны:<br />+375(29) 630-35-32 <br />
						 </p>';
				}
				elseif ($free1_def['item_place']==2) {
					$dop_out='<p>Товар нужно забрать по адресу: ул.Ложинская 5 (ст. м. "Уручье")</p><p>График работы: <br /> Пн-пт: 10:00 - 19:00 <br /> Сб,Вс: 11:00-15:00</p>
						<p>Телефоны:<br />+375(29) 630-35-58 <br />
						</p>';
				}
				else {//третий офис
					$dop_out='<p>Товар нужно забрать по адресу: пр-т Победителй 125 (р-н Минск-Арена)</p><p>График работы: <br /> Пн-пт: 10:00 - 19:00 <br /> Сб, Вс: 11:00-15:00</p>
						<p>Телефоны:<br />+375(29) 694-40-40 <br />
						</p>';
				}
			}
			else {
				$dop_out='Доставка осуществляется в понедельник-пятница с 13.00 до 21.00, только в пределах МКАД.<br />
				Курьер с Вами свяжется заранее.';
			}
			
			$output='Товар успешно забронирован. Срок действия брони: 2 суток. № Вашей брони: '.$edit_id.'<br />'.$dop_out.'<br /><input type="button" value="Закрыть" onclick="br_zakr();" />';
		}
		else {//если не нашло - то выводим сори-текстовку
			$output='К сожалению в связи с длительным временем бронирования, данный товар забронировал кто-то другой. Свободные товары отсутствуют. <br /><input type="button" value="Закрыть" onclick="br_zakr();" />';
		}
		
	}
	

	//снимаем блокировку таблиц
	$q_lock = "UNLOCK TABLES";
	$result_lock = $mysqli->query($q_lock);
	if (!$result_lock) {die('Сбой при доступе к базе данных: '.$q_lock.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	
	
	
	$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет
	
	echo 'document.getElementById(\'bron_main_div\').innerHTML=\''.$output.'\';';
	
	
	
	
}//end of sam_bron if



if ($q_type=='sam_zayav') {//заявка
    $adv_id='';
    if (isset($_SESSION['aid'])) {
        $adv_id=Base::getGet('aid');
    }
	
	$ac_date=strtotime(date("Y-m-d"));
	$validity=strtotime(date("Y-m-d"))+($zayav_tenor*7*24*60*60);

	//$info=$fio.'<br /> +375-'.$tel.'<br />'.$dop_info;
    $info=$dop_info;
    //разбор fio
    $fio_ar = explode(' ', $fio, 3);
    $f='';
    $i='';
    $o='';
    if (count($fio_ar>=1)) {
        $f=$fio_ar[0];
    }
    if (count($fio_ar>=2)) {
        $i=$fio_ar[1];
    }
    if (count($fio_ar>=3)) {
        $o=$fio_ar[2];
    }

    $tel=Base::getNumbersOnly($tel);

	$free_m = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$model_id' LIMIT 1";
	$result_m = $mysqli->query($free_m);
	if (!$result_m) {die('Сбой при доступе к базе данных: '.$free_m.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$model_m=$result_m->fetch_assoc();



	$query = "INSERT INTO rent_orders VALUES ('', 'zayavka', '$ac_date', '$tel', '', '$f', '$i', '$o', '', '$deliv_addr', '$validity', '', '$model_id', '".$model_m['tovar_rent_cat_id']."', 'zayavka', '".Base::getAdvCompId()."', '$info', '', '1', '".time()."', '', '', '', '', '', '', '".$_SERVER['REMOTE_ADDR']."', '', '')";
    //echo '3:'.$query;
	$result = $mysqli->query($query);
	if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	
	$output='Заявка успешно оформлена. Срок действия заявки: по '.date("d.m.Y", $validity).'<br /> В случае освобождения товара в течение срока действия Вашей заявки, наш менеджер свяжется с Вами.<br /><br /><input type="button" value="Закрыть" onclick="br_zakr();" />';
	
	$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет
	
	echo 'document.getElementById(\'bron_main_div\').innerHTML=\''.$output.'\';';
}




if ($q_type=='br_cans') {

	if ($item_1_id>0) {
		$item_id=$item_1_id;
		//освобождаем - меняем статус другого товара
		$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent', br_time='0' WHERE item_id='$item_1_id' AND br_time='$br_time' AND `status`='t_bron'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	}
	
	if ($item_2_id>0) {
		$item_id=$item_2_id;
	
		//освобождаем - меняем статус другого товара
		$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent', br_time='0' WHERE item_id='$item_2_id' AND br_time='$br_time'  AND `status`='t_bron'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	}
	
	if ($item_3_id>0) {
		$item_id=$item_3_id;
	
		//освобождаем - меняем статус другого товара
		$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent', br_time='0' WHERE item_id='$item_3_id' AND br_time='$br_time'  AND `status`='t_bron'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	}
	

	//$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет
	
	echo '
			document.getElementById(\'bron_main_div\').innerHTML=\'\';
			document.getElementById(\'bron_main_div\').style.display="none";
			';
	
}//end of br_cans








function get_post($var)
{
	GLOBAL $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}

?>