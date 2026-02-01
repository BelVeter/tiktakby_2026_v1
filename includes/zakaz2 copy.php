<?php

use bb\Base;

session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');

ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
//require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/KBron.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/tcpdf/tcpdf.php');
//$q_type= "free";
//$inv_n=70248
//$t_from=1558681200
//$t_to=1558890000
//$model_id=47

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}


$day_start=10;//начало рабочего дня в часах
$day_end=20;//окончание рабочего дня в часах
$rent_min=6;//минимальное время аренды в часах
$rent_zap=4;//время запаса между сдачами в часах
$bron_time=5;//время на бронирование в минутах
	$time_limit=time()-$bron_time*60;


//массив для месяцев
$r_months=array( 
"01" => "января", 
"02" => "февраля", 
"03" => "марта", 
"04" => "апреля", 
"05" => "мая", 
"06" => "июня", 
"07" => "июля", 
"08" => "августа", 
"09" => "сентября", 
"10" => "октября", 
"11" => "ноября", 
"12" => "декабря"); 
		
	
	
if ($q_type=='first_step') {
	//входящие: q_type, model_id
	$q_items = "SELECT * FROM tovar_rent_items WHERE model_id='$model_id' AND `status` IN ('to_rent', 'rented_out', 'bron') AND `state`!=3 ORDER BY item_rost1, item_rost2";
	$result_items = $mysqli->query($q_items);
	if (!$result_items) {die('Сбой при доступе к базе данных: '.$q_items.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$items_rows=$result_items->num_rows;
	
	if ($items_rows<1) {
		die('<div class="br_main_div" style="width:450px;">Ошибка. Костюм не найден в базе. Свяжитесь, пожалуйста с нами по телефону.</div>');
	}
	
	$q_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$model_id' LIMIT 0,1";
	$result_model = $mysqli->query($q_model);
	if (!$result_model) {die('Сбой при доступе к базе данных: '.$q_model.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$model_rows=$result_model->num_rows;
	if ($model_rows<1) {
		die('<div class="br_main_div" style="width:450px;">Ошибка. Костюм не найден в базе. Свяжитесь, пожалуйста с нами по телефону.</div>');
	}
	$model=$result_model->fetch_assoc();

$x_date2=strtotime($br_date);
	
$output1='<div class="br_main_div" style="width:450px;">
		<table border="0" cellspacing="0">
			<tr>
				<td>Карнавальный костюм: </td>
				<td><strong>'.$model['model'].'</strong></td>
			</tr>
			<tr>
				<td>Дата праздника: </td>
				<td><strong>'.date("d", $x_date2).' '.rus_month1(date("m", $x_date2)).' '.date("Y", $x_date2).'</strong></td>
			</tr>
		</table>
<br />
		<strong>Пожалуйста, выберите нужный размер:</strong><br />
	<select class="" id="size_'.$model_id.'">
		<option value="0">рост/размер</option>';

$prev_rost1='10000000000';
$prev_rost2='10000000000';
$rost_num=0;
$inv_n='';


while ($items=$result_items->fetch_assoc()) {
	if ($prev_rost1==$items['item_rost1'] && $prev_rost2==$items['item_rost2']) {continue;}
	$rost_num=$rost_num+1;
	
	$output1.='<option value="'.$items['item_inv_n'].'">'.$items['item_rost1'].'-'.$items['item_rost2'].' ('.$items['item_size'].')</option>';
	$prev_rost1=$items['item_rost1'];
	$prev_rost2=$items['item_rost2'];
	$inv_n=$items['item_inv_n'];
}
$output1.='</select><br />
		<input type="hidden" id="day_'.$model_id.'" value="'.date("d", $x_date2).'">
		<input type="hidden" id="month_'.$model_id.'" value="'.date("m", $x_date2).'">
		<input type="hidden" id="year_'.$model_id.'" value="'.date("Y", $x_date2).'">
		
		<input type="button" onclick="show_mbr2(\'karnaval_main\', \''.$model_id.'\', \'1\'); return false;" value="далее" />
		<input type="button" onclick="br_close(\''.$model_id.'\'); return false;" value="отмена" />			
		';
	
$output1.='</div">';

//если ростовок более одной - то выводим окно с выбором моделей, если ростовка одна - сразу переходим к заказу
if ($rost_num>1) {
	echo $output1;
}
else {
	$q_type='karnaval_main';
	//инвентарный номер (inv_n) определяется выше.
	//br_date поставляется на входе, и пока ника не изменяется в данном ифе.
}
	
}//end of first_step if
	


if ($q_type=='karnaval_main') {

	$x_date=strtotime($br_date);
	//echo 'дата проверки:'.$br_d.rus_day(date("w", $x_date)).'<br /><br />';

	$q_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$model_id' LIMIT 0,1";
	$result_model = $mysqli->query($q_model);
	if (!$result_model) {die('Сбой при доступе к базе данных: '.$q_model.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$model_rows=$result_model->num_rows;
	if ($model_rows<1) {
		die('<div class="br_main_div" style="width:450px;">Ошибка. Костюм не найден в базе. Свяжитесь, пожалуйста с нами по телефону.</div>');
	}
	$model=$result_model->fetch_assoc();
	
	//выбираем правильный размер на основе инвентарного номера
	$q_size = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$inv_n' ";
	$result_size = $mysqli->query($q_size);
	if (!$result_size) {die('Сбой при доступе к базе данных: '.$q_size.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	//$items_rows=$result_items->num_rows;
	$size=$result_size->fetch_assoc();
	
	
$q_items = "SELECT * FROM tovar_rent_items WHERE model_id='$model_id' AND item_rost1='".$size['item_rost1']."' AND item_rost2='".$size['item_rost2']."' AND `status` IN ('to_rent', 'rented_out', 'bron') AND `state`!=3 ORDER BY item_rost1";
$result_items = $mysqli->query($q_items);
if (!$result_items) {die('Сбой при доступе к базе данных: '.$q_items.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$items_rows=$result_items->num_rows;

if ($items_rows<1) {
	die('Ошибка. Костюм не найден в базе. Свяжитесь, пожалуйста с нами по телефону.');
}

echo '<div class="br_main_div">';

echo '
		<table border="0" cellspacing="0">
			<tr>
				<td>Карнавальный костюм: </td>
				<td><strong>'.$model['model'].'</strong></td>
			</tr>
			<tr>
				<td>Дата праздника: </td>
				<td><strong>'.date("d", $x_date).' '.rus_month1(date("m", $x_date)).' '.date("Y", $x_date).'</strong></td>
			</tr>
			<tr>
				<td>Размер: </td>
				<td><strong>'.$size['item_rost1'].'-'.$size['item_rost2'].' ('.$size['item_size'].')</strong></td>
			</tr>
		</table>
		
			<input type="hidden" id="size_inv_n" value="'.$inv_n.'" />
			<input type="hidden" id="x_date" value="'.$br_date.'" />
			<input type="hidden" id="last_temp_br_id" value="" />
			<input type="hidden" id="last_inv_n_br" value="" />
				';

$prev_free_from='hz';
$prev_next_br='hz';
$prev_place='hz';

$free_items_count=0;//для контроля свободных костюмов
while ($items=$result_items->fetch_assoc()) {
	
	//определение времени освобождения товара по выдачам ранее начала дня Х
	$q_from = "SELECT * FROM karn_brons WHERE inv_n='".$items['item_inv_n']."' AND (`status` IN ('new', 'ok') OR (`status`='in_process' AND cr_time>=$time_limit)) AND t_from<$x_date ORDER BY t_to DESC";
	$result_from = $mysqli->query($q_from);
	if (!$result_from) {die('Сбой при доступе к базе данных: '.$q_from.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$from_rows=$result_from->num_rows;
	
	if ($from_rows>0) {
		$last_br=$result_from->fetch_assoc();
		$free_from=$last_br['t_to'];
	}
	else {
		$free_from=0;
	}
	
	
	//определение следующей сдачи после начала дня Х
	$q_next = "SELECT * FROM karn_brons WHERE inv_n='".$items['item_inv_n']."' AND (`status` IN ('new', 'ok') OR (`status`='in_process' AND cr_time>=$time_limit)) AND t_from>=$x_date ORDER BY t_to";
	$result_next = $mysqli->query($q_next);
	if (!$result_next) {die('Сбой при доступе к базе данных: '.$q_next.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$next_rows=$result_next->num_rows;
	
	if ($next_rows>0) {
		$next=$result_next->fetch_assoc();
		
		$next_br_t=$next['t_from']-$rent_zap*3600;
		
		//если время выдачи с запасом менее времени открытия - время возврата ставим конец предыдущего дня, если предыдущий день оказывается воскресеньем, сдвигаем еще на один день
		if (date("G", $next_br_t)<$day_start) {
	        //старый if - т.к. в новом не будет срабатывать корректировка на воскресенье
		    //if (date ("w", $next_br_t)!=1)
			if (1==1) {//не понедельник, т.е. предыдущий день НЕ ВОСКРЕСЕНЬЕ
				$next_br=mktime($day_end, 0, 0, date("n", $next_br_t), (date("j", $next_br_t)-1), date("Y", $next_br_t));//ч,м,с  мес, день, год
			}
			else {//понедельник, следовательно предыдущий день воскресенье = -2
				$next_br=mktime($day_end, 0, 0, date("n", $next_br_t), (date("j", $next_br_t)-2), date("Y", $next_br_t));//ч,м,с  мес, день, год
			}
		}
		else {
			$next_br=$next_br_t;
		}
	}
	else {
		$next_br=$x_date+24*3600+$day_end*3600;
		//здесь я закомменитровал контроль на воскресенье. если что - убрать.
		//if (date("w", $next_br)==0) {$next_br=$next_br+24*3600;}//если попадаем на воскресенье - то сдвигаем еще на один день вперед
		
		$next['t_from']=$next['t_to']=$x_date+48*3600;//при расчете возможности второй сдачи используются результаты выборки next [], поэтому next должон быть!
	}
	
	
	//корректировка даты освобождения на запас между сдачами (если в результате не попадает на рабочее время)
	if ((date("G", $free_from)+$rent_zap)>$day_end) {

	    //убрал if (date ("w", $free_from)!=6) для того, чтобы уйти от контроля на воскресенье
		if (1==1) {//(если освобождается после 16:00 и не суббота -> перенос на след день.
			$br_start=mktime($day_start, 0, 0, date("n", $free_from), (date("j", $free_from)+1), date("Y", $free_from));//ч,м,с  мес, день, год
		}
		else {//(если освобождается после 16:00 И СУББОТА -> перенос на понедельник (+2дня)
			//echo'сработал контроль раб времени СУББОТА<br />';
			$br_start=mktime($day_start, 0, 0, date("n", $free_from), (date("j", $free_from)+2), date("Y", $free_from));//ч,м,с  мес, день, год
		}
	}
	else {
		$br_start=$free_from+$rent_zap*3600;
	}
	
	//контроль на слишком ранне освобождение более чем -1 день
    //убрал контроль на воскресенье, ранее было: if ($br_start<($x_date-24*3600) && date ("w", $x_date)!=1) + еще закомментировал следующи elseif
	if ($br_start<($x_date-24*3600)) { //для х даты не понедельник: если освобождается очень ранее чем -1 день от даты проверки убираем 1 день и добавляем 10 часов (время начала работы проката)
		$br_start=$x_date-24*3600+$day_start*3600;
	}
//	elseif ($br_start<($x_date-48*3600) && date ("w", $x_date)==1) {//для понедельника(1) отнимаем 2 дня
//		$br_start=$x_date-48*3600+$day_start*3600;
//	}
	$ttt_next=$next_br;//!!!
	
	//контроль на слишком позднюю сдачу, более чем +1 день
    //убрал контроль на воскресенье, ранее было: if ($next_br>($x_date+24*2*3600) && date ("w", $x_date)!=6) + закомментировал следующий elseif
	if ($next_br>($x_date+24*2*3600)) { //для х даты не субботы: если освобождается очень поздно - более чем -1 день от даты проверки убираем 1 день и добавляем 10 часов (время начала работы проката)
		$next_br=$x_date+24*3600+$day_end*3600;
		$ttt_next=$next_br;//!!!
	}
//	elseif ($next_br>($x_date+24*3*3600) && date ("w", $x_date)==6) {//для субботы (6) добавляем 2 дня + раб время
//		$next_br=$x_date+24*2*3600+$day_end*3600;
//	}
	
	
	
	
	
	
	$first_rent='no';
	
	//если старт брони зашкаливает за текущий день - пишем, что сдача невозожна
	if ($br_start>=($x_date)+$day_end*3600) {
		$first_rent='no';//echo 'костюм недоступен для сдачи в выбранную Вами дату<br />';
	}
	elseif (($next_br-$br_start)>=$rent_min*3600) {
		$first_rent='yes';//echo 'костюм можно забрать начиная с '.date("H:i - d.m.y", $br_start).' ('.rus_day(date("w", $br_start)).')<br /> костюм необходимо вернуть не позднее '.date("d.m.y (H:i)", $next_br).'.';
	}
	else {
		$first_rent='no';//echo 'костюм недоступен для сдачи в выбранную Вами дату<br />';
	}
	
	
	
	
	$second_rent='no';
	$next_br2='';
	$br_start2='';
	
	//проверка возможности второй сдачи (т.е. если была сдача и возврат в этот день.
	if (($next['t_from']>$x_date && $next['t_from']<($x_date+24*3600)) && ($next['t_to']>$x_date && $next['t_to']<($x_date+24*3600)) && (($next['t_to']+$rent_zap*3600)<=($x_date+$day_end*3600))) {//и выдача и сдача в рамках дня Х + сдача с учетом запаса все еще до окончания рабочего дня
		$second_rent='yes';
	
		$br_start2=$next['t_to']+$rent_zap*3600; //начало одинаково для обоих случаев, концы просчитываем по-разному.
	
		if ($next_rows==1) {
			$next_br2=$x_date+24*3600+$day_end*3600;
			if (date("w", $next_br2)==0) {
				$next_br2=$next_br2+24*3600;
			}
		}
		elseif ($next_rows>1) {
			$next2=$result_next->fetch_assoc();
			$next_br2=$next2['t_from']-$rent_zap*3600;
	
			//корректировка даты освобождения на запас между сдачами (если в результате не попадает на рабочее время)
			if (date("G", $next_br2)<$day_start) {
	            //убрал контроль на воскресенье, ранее было if (date ("w", $next_br2)!=1)
				if (1==1) {//не понедельник, т.е. предыдущий день НЕ ВОСКРЕСЕНЬЕ
					$next_br2=mktime($day_end, 0, 0, date("n", $next_br2), (date("j", $next_br2)-1), date("Y", $next_br2));//ч,м,с  мес, день, год
				}
				else {//понедельник, следовательно предыдущий день воскресенье = -2
					$next_br2=mktime($day_end, 0, 0, date("n", $next_br2), (date("j", $next_br2)-2), date("Y", $next_br2));//ч,м,с  мес, день, год
				}
			}
	
			//проверяем 6 часов
			if (($next_br2-$br_start2)>=$rent_min*3600) {
				$second_rent='yes';//echo 'костюм можно забрать начиная с '.date("H:i - d.m.y", $br_start).' ('.rus_day(date("w", $br_start)).')<br /> костюм необходимо вернуть не позднее '.date("d.m.y (H:i)", $next_br).'.';
			}
			else {
				$second_rent='no';//echo 'костюм недоступен для сдачи в выбранную Вами дату<br />';
			}
		}
	
		//echo '2 костюм можно забрать начиная с '.date("H:i - d.m.y", $br_start2).' ('.rus_day(date("w", $br_start2)).')<br /> костюм необходимо вернуть не позднее '.date("H:i - d.m.y", $next_br2).' ('.rus_day(date("w", $next_br2)).')';
	}//end of second rent
	
	// если "свободность" совпадает, не выводим товар лишний раз - ПОКА только для случаев без второй сдачи
	if ($prev_free_from==$br_start && $prev_next_br==$next_br && $second_rent=='no' && $prev_place==$items['item_place']) {
		continue;
	}
	$prev_free_from=$br_start;
	$prev_next_br=$next_br;
	$prev_place=$items['item_place'];
	
	//если нет свободных - нефиг показывать
	if ($first_rent=='no' && $second_rent=='no') {continue;}
	else {$free_items_count=$free_items_count+1;}
	if ($free_items_count==1) {
		echo '<p style="font-size:20px;"><strong>Кликните по зеленому полю подходящего периода проката:</strong><br /></p>';
	}
		
		
	//доработать !!! (если ничего не подходит - написать, что нет свободных
	
	
	echo '<strong><i>Вариант №'.$free_items_count.'</i></strong>(место выдачи костюма:'.(($items['item_place']=='1' || $items['item_place']=='3') ? ' пр-т Победителей 127' : ' пр-т Победителей 127').')<br />';
	
	//графика
	//убрал контроль на воскресенье, ранее было if (date("w", $x_date)!=1 && date("w", $x_date)!=6)
	if (1==1) {//рисуем для вт-пт + воскр, т.е. 3 дня и воскресенья либо нет, либо оно посередине
	
		//левая точка отсчета
		$left_start=($x_date-24*3600);
		//рисуем первый возможный период свободности
		if ($first_rent=='yes') {
			$b_w=(($next_br-$br_start)/3600)*6;
			$b_s=(($br_start-$left_start)/3600)*6;
			$f_r_div='<a class="br_free_div" name="1" href="#" onclick="show_a(\'free\', \''.$items['item_inv_n'].'\', \''.$br_start.'\', \''.$next_br.'\', \''.$model_id.'\'); return false;" style="left:'.$b_s.'px; width:'.($b_w).'px;"><div class="br_free_h_left">'.date("G", $br_start).'<sup>00</sup></div><div class="br_free_h_right">'.date("G", $next_br).'<sup>00</sup></div></a>';
	
		}
		else {
			$f_r_div='';
		}
	
		//рисуем второй возможный период свободности
		if ($second_rent=='yes') {
			$b_w2=(($next_br2-$br_start2)/3600)*6;
			$b_s2=(($br_start2-$left_start)/3600)*6;
			$f_r_div2='<a class="br_free_div" name="2" href="#" onclick="show_a(\'free\', \''.$items['item_inv_n'].'\', \''.$br_start2.'\', \''.$next_br2.'\', \''.$model_id.'\'); return false;" style="left:'.$b_s2.'px; width:'.($b_w2).'px;"><div class="br_free_h_left">'.date("G", $br_start2).'<sup>00</sup></div><div class="br_free_h_right">'.date("G", $next_br2).'<sup>00</sup></div></a>';
	
		}
		else {
			$f_r_div2='';
		}
	
	
	
		echo '
	<div class="br_wh_cont">
	<div class="br_cont">
	
		<div class="br_day_name">'.date("d.m.Y", ($x_date-24*3600)).'<br />'.rus_day(date("w", $x_date-24*3600)).'</div>
		<div class="br_day_name" style="left:144px;"><strong>'.date("d.m.Y", $x_date).'<br />'.rus_day(date("w", $x_date)).'</strong></div>
		<div class="br_day_name" style="left:288px;">'.date("d.m.Y", ($x_date+24*3600)).'<br />'.rus_day(date("w", $x_date+24*3600)).'</div>
	
		<div class="br_day"></div>
		<div class="br_day" style="left:144px;"></div>
		<div class="br_day" style="left:288px;"></div>
	
		'.$f_r_div.$f_r_div2.'
	
	<input type="hidden" id="last_temp_br_id" value="" />
	<input type="hidden" id="last_inv_n_br" value="" />
				
	</div>
	</div>
	<div class="br_info_div" id="br_info_div_'.$items['item_inv_n'].'"></div>
	------------------------------------------<br />			';
	}
	elseif (date("w", $x_date)==1) {//рисуем для понедельника, т.е. воскресенье есть и оно слева
	
		//левая точка отсчета
		$left_start=($x_date-48*3600);
		//рисуем первый возможный период свободности
		if ($first_rent=='yes') {
			$b_w=(($next_br-$br_start)/3600)*6;
			$b_s=(($br_start-$left_start)/3600)*6;
			$f_r_div='<a class="br_free_div" href="#" onclick="show_a(\'free\', \''.$items['item_inv_n'].'\', \''.$br_start.'\', \''.$next_br.'\', \''.$model_id.'\'); return false;" style="left:'.$b_s.'px; width:'.($b_w).'px;"><div class="br_free_h_left">'.date("G", $br_start).'<sup>00</sup></div><div class="br_free_h_right">'.date("G", $next_br).'<sup>00</sup></div></a>';
	
		}
		else {
			$f_r_div='';
		}
	
		//рисуем второй возможный период свободности
		if ($second_rent=='yes') {
			$b_w2=(($next_br2-$br_start2)/3600)*6;
			$b_s2=(($br_start2-$left_start)/3600)*6;
			$f_r_div2='<a class="br_free_div" href="#" onclick="show_a(\'free\', \''.$items['item_inv_n'].'\', \''.$br_start.'\', \''.$next_br.'\', \''.$model_id.'\'); return false;" style="left:'.$b_s2.'px; width:'.($b_w2).'px;"><div class="br_free_h_left">'.date("G", $br_start2).'<sup>00</sup></div><div class="br_free_h_right">'.date("G", $next_br2).'<sup>00</sup></div></a>';
	
		}
		else {
			$f_r_div2='';
		}
	
		echo '
	<div class="br_wh_cont">
	<div class="br_cont_1">
	
		<div class="br_day_name">'.date("d.m.Y", ($x_date-48*3600)).'<br />'.rus_day(date("w", $x_date-48*3600)).'</div>
		<div class="br_day_name" style="left:144px;">'.date("d.m.Y", ($x_date-24*3600)).'<br />'.rus_day(date("w", $x_date-24*3600)).'</div>
		<div class="br_day_name" style="left:288px;"><strong>'.date("d.m.Y", $x_date).'<br />'.rus_day(date("w", $x_date)).'</strong></div>
		<div class="br_day_name" style="left:432px;">'.date("d.m.Y", ($x_date+24*3600)).'<br />'.rus_day(date("w", $x_date+24*3600)).'</div>
	
		<div class="br_day"></div>
		<div class="br_day" style="left:144px;"></div>
		<div class="br_day" style="left:288px;"></div>
		<div class="br_day" style="left:432px;"></div>
		
		'.$f_r_div.$f_r_div2.'
				
	</div>
	</div>
	<div class="br_info_div" id="br_info_div_'.$items['item_inv_n'].'"></div>
	------------------------------------------	<br />';
	}
	elseif (date("w", $x_date)==6) {//рисуем для субботы, т.е. воскресенье есть и оно справа
	
		//левая точка отсчета
		$left_start=($x_date-24*3600);
		//рисуем первый возможный период свободности
		if ($first_rent=='yes') {
			$b_w=(($next_br-$br_start)/3600)*6;
			$b_s=(($br_start-$left_start)/3600)*6;
			$f_r_div='<a class="br_free_div" href="#" onclick="show_a(\'free\', \''.$items['item_inv_n'].'\', \''.$br_start.'\', \''.$next_br.'\', \''.$model_id.'\'); return false;" style="left:'.$b_s.'px; width:'.($b_w).'px;"><div class="br_free_h_left">'.date("G", $br_start).'<sup>00</sup></div><div class="br_free_h_right">'.date("G", $next_br).'<sup>00</sup></div></a>';
	
		}
		else {
			$f_r_div='';
		}
	
		//рисуем второй возможный период свободности
		if ($second_rent=='yes') {
			$b_w2=(($next_br2-$br_start2)/3600)*6;
			$b_s2=(($br_start2-$left_start)/3600)*6;
			$f_r_div2='<a class="br_free_div" href="#" onclick="show_a(\'free\', \''.$items['item_inv_n'].'\', \''.$br_start.'\', \''.$next_br.'\', \''.$model_id.'\'); return false;" style="left:'.$b_s2.'px; width:'.($b_w2).'px;"><div class="br_free_h_left">'.date("G", $br_start2).'<sup>00</sup></div><div class="br_free_h_right">'.date("G", $next_br2).'<sup>00</sup></div></a>';
	
		}
		else {
			$f_r_div2='';
		}
	
	
		echo '
	<div class="br_wh_cont">
	<div class="br_cont_1">
	
		<div class="br_day_name">'.date("d.m.Y", ($x_date-24*3600)).'<br />'.rus_day(date("w", $x_date-24*3600)).'</div>
		<div class="br_day_name" style="left:144px;"><strong>'.date("d.m.Y", $x_date).'<br />'.rus_day(date("w", $x_date)).'</strong></div>
		<div class="br_day_name" style="left:288px;">'.date("d.m.Y", ($x_date+24*3600)).'<br />'.rus_day(date("w", $x_date+24*3600)).'</div>
		<div class="br_day_name" style="left:432px;">'.date("d.m.Y", ($x_date+48*3600)).'<br />'.rus_day(date("w", $x_date+48*3600)).'</div>
	
		<div class="br_day"></div>
		<div class="br_day" style="left:144px;"></div>
		<div class="br_day" style="left:288px;"></div>
		<div class="br_day" style="left:432px;"></div>
	
		'.$f_r_div.$f_r_div2.'
					
	</div>
	</div>
	<div class="br_info_div" id="br_info_div_'.$items['item_inv_n'].'"></div>
	------------------------------------------	<br />';
	}

}//end of items while	

if ($free_items_count<1) {
	echo 'Извините, на указанную дату ('.date("d.m.Y", $x_date).') нет свободных котюмов Вашего размера. Выберите другой костюм/размер/дату, либо попробуйте проверить ее позже. Возможно, кто-то откажется от брони.<input type="button" value="OK" id="general_cans" onclick="general_cans(\''.$model_id.'\');"/>';
}

	echo '
	<input type="button" value="X" id="general_cans" onclick="general_cans(\''.$model_id.'\');" style="position:absolute; right:15px; top:15px;" />
	<input type="button" value="Отмена" id="general_cans2" onclick="general_cans(\''.$model_id.'\');" />
		</div>';

}//end of karnaval_main if


if ($q_type=='bron_ok') {
	
	$t_from=strtotime($bron_date_from)+$bron_hour_from*3600;
	$t_to=strtotime($bron_date_to)+$bron_hour_to*3600;
	
	$bron_phone1=phone_to_n($bron_phone1);
	$bron_phone2=phone_to_n($bron_phone2);
	
	
	//сначала блокируем таблицу с бронями
	$q_lock = "LOCK TABLE karn_brons WRITE";
	$result_lock = $mysqli->query($q_lock);
	if (!$result_lock) {die('Сбой при доступе к базе данных: '.$q_lock.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	//$items_rows=$result_items->num_rows;
	
	//проверяем, свободно ли это время, если нет - удаляем временную бронь и выдаем сообщение, если свободно - продолжаем
	$t_from2=$t_from-$rent_zap*3600+1;
	$t_to2=$t_to+$rent_zap*3600-1;
	
	$q_check = "SELECT * FROM karn_brons WHERE inv_n='".$inv_n."' AND (`status` IN ('new', 'ok') OR (`status`='in_process' AND cr_time>=$time_limit)) AND ((t_from BETWEEN '$t_from2' AND '$t_to2') OR (t_to BETWEEN '$t_from2' AND '$t_to2')) AND kb_id!='$bron_temp_id'";
	$result_check = $mysqli->query($q_check);
	if (!$result_check) {die('Сбой при доступе к базе данных: '.$q_check.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$check_rows=$result_check->num_rows;
	
	if ($check_rows>0) {
		
		$output='Извините, но это время уже занято кем-то другим. Пожалуйста обновите состояние броней и выберите другое время.';
			$q_del = "DELETE FROM karn_brons WHERE kb_id='$bron_temp_id'";
			$result_del = $mysqli->query($q_del);
			if (!$result_del) {die('Сбой при доступе к базе данных: '.$q_del.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$br_id='';
		die('document.getElementById(\'br_mp_'.$model_id.'\').innerHTML=\'<div class="br_main_div" style="width:450px; text-align:justify;">'.$output.'<input type="button" value="OK" id="general_cans" onclick="br_close(\\\''.$model_id.'\\\');"/></div>\';');
	
	}
	else {//рассчитываем номер брони вносим изменения во временную бронь
		// формируем первую часть номера брони: порядковый номер товара без категории
		$tov_n_1=mb_substr($inv_n, 3);
		if ($tov_n_1>99) {
			$tov_n_1=$tov_n_1;
		}
		elseif ($tov_n_1>9) {
			$tov_n_1='9'.$tov_n_1;
		}
		else {
			$tov_n_1='90'.$tov_n_1;
		}
		
		
		// формируем вторую часть номера брони (макс. номер в рамках одного и того же инвентарного номера
		$q_max_n = "SELECT * FROM karn_brons WHERE inv_n='".$inv_n."' ORDER BY br_max_num DESC LIMIT 0,1";
		$result_max_n = $mysqli->query($q_max_n);
		if (!$result_max_n) {die('Сбой при доступе к базе данных: '.$q_max_n.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$max_n_rows=$result_max_n->num_rows;
		
		if ($max_n_rows>0) {
			$max_n_res=$result_max_n->fetch_assoc();
			$max_n=$max_n_res['br_max_num'];
		}
		else {
			$max_n=0;
		}
		
		$tov_n_2=$max_n+1;
		
		$br_num=$tov_n_1.$tov_n_2;//итоговый номер брони
		
		// проверяем, существует ли эта временная бронь, если уже нет (более часа), то выдаем, чтобы еще раз попробовали
		$q_yest = "SELECT * FROM karn_brons WHERE kb_id='$bron_temp_id'";
		$result_yest = $mysqli->query($q_yest);
		if (!$result_yest) {die('Сбой при доступе к базе данных: '.$q_yest.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$yest_num=$result_yest->num_rows;
		
		if ($yest_num!=1) {
			$output='Извините, в связи с длительным временем бронирования, возможно, этот товар уже занят кем-то другим. Пожалуйста попробуйте его забронировать еще раз. Если это сообщение вылазит повторно, свяжитесь с нами по телефону.';
			die('document.getElementById(\'br_mp_'.$model_id.'\').innerHTML=\'<div class="br_main_div" style="width:450px; text-align:justify;">'.$output.'<input type="button" value="OK" id="general_cans" onclick="br_close(\\\''.$model_id.'\\\');"/></div>\';');
		}
		
		$query_upd = "UPDATE karn_brons SET t_from='$t_from', t_to='$t_to', `status`='new', info='$bron_info', br_max_num='$tov_n_2', br_num='$br_num', fio='$bron_fio', phone1='$bron_phone1', phone2='$bron_phone2', `mail`='$bron_mail' WHERE kb_id='$bron_temp_id'";
		$result = $mysqli->query($query_upd);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		
	}
//снимаем блокировку таблиц
$q_lock = "UNLOCK TABLES";
$result_lock = $mysqli->query($q_lock);
if (!$result_lock) {die('Сбой при доступе к базе данных: '.$q_lock.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


$q_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$model_id' LIMIT 0,1";
$result_model = $mysqli->query($q_model);
if (!$result_model) {die('Сбой при доступе к базе данных: '.$q_model.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$model=$result_model->fetch_assoc();

$q_item = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$inv_n' ";
$result_item = $mysqli->query($q_item);
if (!$result_item) {die('Сбой при доступе к базе данных: '.$q_item.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//$items_rows=$result_items->num_rows;
$item=$result_item->fetch_assoc();



$text='ФИО:'.$bron_fio.'<br />Тел.1:'.$bron_phone1.'<br />Тел.2:'.$bron_phone2.'<br />e-mail:'.$bron_mail.'<br />доп. инфо:'.$bron_info.'<br /> инв.№'.$inv_n.'<br />';


	$output='<p>ВНИМАНИЕ! Вы забронировали <br /><strong>Костюм '.$model['model'].', размер:'.$item['item_rost1'].'-'.$item['item_rost2'].' ('.$item['item_size'].'). </strong></p>
<p>Забрать костюм из салона проката можно не ранее <br /><strong>'.date("H", $t_from).'</strong><sup>00</sup><strong>'.date(" d ", $t_from).$r_months[date("m", $t_from)].date(" Y", $t_from).'г.</strong> по адресу:<br /></p><p class="karn_br_addr">'.(($item['item_place']=='1' || $item['item_place']=='3') ? ' пр-т Победителей 127' : ' пр-т Победителей 127').'</p>
<p>Вернуть костюм в салон проката необходимо не позднее <strong>'.date("H", $t_to).'</strong><sup>00</sup><strong>'.date(" d ", $t_to).$r_months[date("m", $t_to)].date(" Y", $t_to).'г.</strong></p>
<p>Номер вашей брони: <strong>'.$tov_n_1.'-'.$tov_n_2.'</strong> (сделайте скрин этого сообщения или запишите номер брони - это ВАЖНО!)</p>
<p style="text-align: center;"><img style="border-style: solid; border-bottom-color: white; border-width: 5px;" src="'.$base_url='/bb/qr_png.php?text='.urlencode("brnum".$br_num).'&size=4"></p>
<p>Если вы забронировали костюм более чем за 3 дня до нужной даты – необходимо внести предоплату в размере 100% суточной арендной платы не позднее 3 дней с момента бронирования, в противном случае бронь будет аннулирована.
</p>
	<input style="border-style: solid; border-color: white; border-width: 10px;" type="button" value="OK" id="general_cans" onclick="br_close(\\\''.$model_id.'\\\');"/>
			';
		
	$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет
	
	echo 'document.getElementById(\'br_mp_'.$model_id.'\').innerHTML=\'<div class="br_main_div" style="width:450px; text-align:justify;">'.$output.'</div>\';
		
			';

$output2=$text.$output;
$output2=str_replace(array("\r\n", "\r", "\n"), "", $output2); //превращаем в одну строку, иначе javascript не поймет
	
$result2=@mail("interests@mail.ru", "Заказ карнавального костюма! - ".$model['model'], "$output2", "Content-type: text/html; charset=UTF-8 \r\n");//@ - для того, чтобы не выводить ошибки

$headers = 'From: info@tiktak.by' . "\r\n" .
		'Reply-To: info@tiktak.by' . "\r\n" .
		'Content-type: text/html; charset=UTF-8' . "\r\n" .
		'X-Mailer: PHP/' . phpversion();
$result=@mail("$bron_mail", "Заказ карнавального костюма! - ".$model['model'], "$output", "$headers"); //@ - для того, чтобы не выводить ошибки
	/*
 document.getElementById(\'last_temp_br_id\').value=\'\';
	document.getElementById(\'last_inv_n_br\').value=\'\';
	 */
}//end of bron ok if




if ($q_type=='bron_cans') {

	$q_del = "DELETE FROM karn_brons WHERE kb_id='$br_id'";
	$result_del = $mysqli->query($q_del);
	if (!$result_del) {die('Сбой при доступе к базе данных: '.$q_del.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	
}//end of bron_cans if




if ($q_type=='free') {
	
//сначала блокируем таблицу с бронями
	$q_lock = "LOCK TABLE karn_brons WRITE";
	$result_lock = $mysqli->query($q_lock);
	if (!$result_lock) {die('Сбой при доступе к базе данных: '.$q_lock.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	//$items_rows=$result_items->num_rows;

//проверяем, свободно ли это время, если нет - выдаем сообщение, если свободно - продолжаем
	$t_from2=$t_from-$rent_zap*3600+1;
	$t_to2=$t_to+$rent_zap*3600-1;
	 
	$q_check = "SELECT * FROM karn_brons WHERE inv_n='".$inv_n."' AND (`status` IN ('new', 'ok') OR (`status`='in_process' AND cr_time>=$time_limit)) AND ((t_from BETWEEN '$t_from2' AND '$t_to2') OR (t_to BETWEEN '$t_from2' AND '$t_to2'))";
	$result_check = $mysqli->query($q_check);
	if (!$result_check) {die('Сбой при доступе к базе данных: '.$q_check.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$check_rows=$result_check->num_rows;
	
	if ($check_rows>0) {
		$output='Извините, но это время уже занято кем-то другим. Пожалуйста обновите состояние броней и выберите другое время.';
		$br_id='';
	}
	else {//вносим временную бронь
        $query_new = "INSERT INTO karn_brons SET inv_n='$inv_n', t_from='$t_from', t_to='$t_to', `status`='in_process', cr_time='".time()."', valid_time='', payment_date='".Base::getAdvCompId()."'";
	    //$query_new = "INSERT INTO karn_brons VALUES(NULL, '$inv_n', '', '$t_from', '$t_to', 'in_process', '', '0', '0', '0', '0', '', '".time()."', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '')";//corrected
		$result = $mysqli->query($query_new);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		$br_id=$mysqli->insert_id;
		
				
		$output='
				<form method="post" action="" name="">
<br /><strong><span style="font-size:16px;">Внимание! При указании периода аренды не превышайте 24 часа, если Вы планируете оплачивать одни сутки проката. За неполные сутки взымается оплата как за полные сутки.</span></strong><br /><br />
Выберите дату и время выдачи костюма:<br />
	<select name="" id="br_date_from_'.$inv_n.'">
		<option value="0">день</option>';
		//$output.='from'.$t_from2.'to'.$t_to2;
		for ($i=$t_from2; $i<=$t_to2; $i=$i+24*3600) {

		    //убрал контроль на воскресенье - закомментировал
			//if (date("w", $i)==0) {continue;}
			$output.= '<option value="'.date("Y-m-d", $i).'">'.date("d", $i).' '.rus_month1(date("m", $i)).' '.date("Y", $i).'</option>';
		}
$output.='
    </select>
	в <select name="" id="br_hour_from_'.$inv_n.'">
		<option value="0">время</option>
        <option value="10">10</option>
        <option value="11">11</option>
		<option value="12">12</option>
		<option value="13">13</option>
		<option value="14">14</option>
		<option value="15">15</option>
		<option value="16">16</option>
		<option value="17">17</option>
		<option value="18">18</option>
		<option value="19">19</option>
		<option value="20">20</option>		
    </select><sup>00</sup><br />
		
Время и дата возврата костюма в салон:<br />
	<select name="" id="br_date_to_'.$inv_n.'">
		<option value="0">день</option>';
	for ($i=$t_from2-3600; $i<=$t_to2; $i=$i+24*3600) {

        //убрал контроль на воскресенье - закомментировал
	    //if (date("w", $i)==0) {continue;}
		$output.= '<option value="'.date("Y-m-d", $i).'">'.date("d", $i).' '.rus_month1(date("m", $i)).' '.date("Y", $i).'</option>';
	}

$output.='
		</select>
	до <select name="" id="br_hour_to_'.$inv_n.'">
		<option value="0">время</option>
        <option value="10">10</option>
        <option value="11">11</option>
		<option value="12">12</option>
		<option value="13">13</option>
		<option value="14">14</option>
		<option value="15">15</option>
		<option value="16">16</option>
		<option value="17">17</option>
		<option value="18">18</option>
		<option value="19">19</option>
		<option value="20">20</option>
    </select><sup>00</sup><br />
		
Ф.И.О.:<input type="text" name="fio" id="br_fio_'.$inv_n.'" size="45" />*<br />
Телефон 1*:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>+375-</strong><input type="text" name="phone1" id="br_phone1_'.$inv_n.'" /><br />используется для подтверждения брони<br />
Телефон 2:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>+375-</strong><input type="text" name="phone2" id="br_phone2_'.$inv_n.'" /><br />
e-mail:<input type="email" name="email" id="br_mail_'.$inv_n.'" /><br />
Дополнительная информация:<br />
<textarea cols="50" rows="4" id="br_text_'.$inv_n.'"></textarea><br />
<input type="hidden" value="'.$br_id.'" id="br_temp_id_'.$inv_n.'" />
<input type="button" onclick="fbq(\\\'track\\\', \\\'SubmitApplication\\\'); bron_ok(\\\'bron_ok\\\', \\\''.$inv_n.'\\\', \\\''.$model_id.'\\\');" value="сохранить бронь" /><input type="button" onclick="bron_cans(\\\'bron_cans\\\', \\\''.$inv_n.'\\\', \\\''.$br_id.'\\\', \\\''.$model_id.'\\\', \\\'1\\\');" value="отмена" />
<br /><i>* обязательные поля</i>
		</form>
				';
		
	} 
	
//снимаем блокировку таблиц
	$q_lock = "UNLOCK TABLES";
	$result_lock = $mysqli->query($q_lock);
	if (!$result_lock) {die('Сбой при доступе к базе данных: '.$q_lock.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}	
	
	
	
		
	
	$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет
	
	echo 'document.getElementById(\'br_info_div_'.$inv_n.'\').innerHTML=\''.$output.'\';
			document.getElementById(\'last_temp_br_id\').value=\''.$br_id.'\';
			document.getElementById(\'last_inv_n_br\').value=\''.$inv_n.'\';
			
			';
	
	
	
	
	
}//end of free if
	
	
	
	



function get_post($var)
{
	GLOBAL $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
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

function rus_month1 ($month) {
	$month=$month*1;
	
	switch ($month) {
		case '1':
			return 'января';
			break;

		case '2':
			return 'февраля';
			break;

		case '3':
			return 'марта';
			break;

		case '4':
			return 'апреля';
			break;

		case '5':
			return 'мая';
			break;

		case '6':
			return 'июня';
			break;

		case '7':
			return 'июля';
			break;

		case '8':
			return 'августа';
			break;


		case '9':
			return 'сентября';
			break;

		case '10':
			return 'октября';
			break;

		case '11':
			return 'ноября';
			break;

		case '12':
			return 'декабря';
			break;
						
		default:
			return 'Месяц не определен';
			break;
	}
}

function phone_to_n ($ph) {
	$ph=preg_replace("|[^0-9]|i", "", $ph);
	return $ph;
}

?>