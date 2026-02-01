<?php

use bb\Base;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //

$mysqli=\bb\Db::getInstance()->getConnection();

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
'.Base::getBarCodeReaderScript().'
</head>
<title>Карнавал. Брони-таблица.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/kb.php"><strong>Карнавальные брони</strong></a>
	<a class="div_item" href="/bb/karn_free.php"><strong>Свободные по дате</strong></a>
	<a class="div_item" href="/bb/rda.php">Все сделки</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a><br />
		<form method="post" action="/bb/kr_baza_new.php" style="display:inline-block;">
			<input type="hidden" name="cat_id" value="2" /><input type="submit" value="КАРНАВАЛЫ" style="width:100px; height:35px; background-color:green; color:white" />
		</form>
</div><br />

';
?>

<script language="javascript">
function show_a (br_id) {

	if (document.getElementById('br_dop_info_'+br_id).style.display=="none") {
		document.getElementById('br_dop_info_'+br_id).style.display="";
	}
	else {
		document.getElementById('br_dop_info_'+br_id).style.display="none";
	}

}

function getWebPageUrl(itemId) {
  fetch('kb_web_url.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `item_id=${itemId}`,
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json(); // Parse the JSON response
    })
    .then(data => {
      if (data[0]) { // Check the boolean result (data[0])
        theLink = document.querySelector('div#pic_'+itemId+' a');
        theLink.href=data[1];
      }
      else {
        console.log('web url fail');
      }
    })
    .catch(error => {
      console.error("Request error:", error); // Handle fetch errors
    });
}

function show_pic (item_id) {

  getWebPageUrl(item_id);

	if (document.getElementById('pic_'+item_id).style.display=="none") {
		document.getElementById('pic_'+item_id).style.display="";
	}
	else {
		document.getElementById('pic_'+item_id).style.display="none";
	}

}


function s_ch() {

	var start_date=new Date(document.getElementById('start_date').value);
	var finish_date=new Date(document.getElementById('finish_date').value);

	if (start_date>finish_date) {
		alert ('Дата "C" фильтра должна быть меньше либо равна дате "по" фильтра');
		return false;
	}
	else {
		return true;
	}

}

</script>

<?php





$start_date=date("Y-m-d");
$finish_date=date("Y-m-d", time()+7*24*3600);
$item_info_width=300; //px

$inv_n='';
$sex='all';

$x_date_text='';




foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

$start_date=strtotime($start_date);
$finish_date=strtotime($finish_date)+23*3600;


if (isset($x_date) && $x_date>0) {
	$x_date=strtotime($x_date);
	$x_date_text=date("Y-m-d", $x_date);

	$start_date=$x_date-24*3600;
	$finish_date=$x_date+(24+23)*3600;
}


echo '

<form name="br_srch" action="kb_lines.php" method="post" id="br_srch">
		Инв.№:<input type="text" name="inv_n" value="'.$inv_n.'" size="5" />(пусто=все), брони в период
		c <input type="date" name="start_date" id="start_date" value="'.date("Y-m-d", $start_date).'" />
		по <input type="date" name="finish_date" id="finish_date" value="'.date("Y-m-d", $finish_date).'" />,
		ЛИБО СВОБОДНЫЙ НА ДАТУ: <input type="date" name="x_date" id="x_date" value="'.$x_date_text.'" />, (пока в СВОБОДНЫЙ НА ДАТУ стоит дата, период не работает!!!)
				<br />


Пол:<select name="sex" id="item_sex">
		<option value="all" '.sel_d($sex, 'all').'>все</option>
		<option value="m" '.sel_d($sex, 'm').'>для мальчиков</option>
		<option value="f" '.sel_d($sex, 'f').'>для девочек</option>
    	<option value="u" '.sel_d($sex, 'u').'>унисекс</option>
    	<option value="0" '.sel_d($sex, '0').'>не определено</option>
</select>

	<input name="action" type="submit" onclick="return s_ch();" value="фильтр" />

</form>


		';

//основная выборка броней
if ($inv_n>0) {
	$srch1=" WHERE tovar_rent_items.item_inv_n='$inv_n'";
}
else {
	//$srch1=" WHERE tovar_rent.tovar_rent_cat_id IN ('2', '61')";
	$srch1=" WHERE tovar_rent_cat.cat_type='1'";
}

if ($sex!='all') {
	$srch1.=" AND tovar_rent_items.sex='$sex'";
}








$days_num=($finish_date-$start_date-23*3600)/(24*3600);

//рисуем даты/дни недели
$days_line='<div class="br_cont" style="width:'.(($days_num+1)*24*6+$item_info_width).'px; background-color:#FFF;">';

$days_line.='<div class="item_info"></div>';

	for ($i=0; $i<=$days_num; $i++) {
		$days_line.='
		<div class="br_day_name" style="left:'.($i*24*6+$item_info_width).'px;">'.date("d.m.Y", ($start_date+$i*24*3600)).'<br />'.rus_day(date("w", $start_date+$i*24*3600)).'</div>

			';
	}

$days_line.= '</div>';

echo $days_line;

$query_item="SELECT * FROM tovar_rent_items
                LEFT JOIN tovar_rent ON (tovar_rent_items.model_id = tovar_rent.tovar_rent_id)
                LEFT JOIN tovar_rent_cat ON tovar_rent_cat.tovar_rent_cat_id = tovar_rent.tovar_rent_cat_id
                $srch1 ORDER BY tovar_rent.model";
$result_item = $mysqli->query($query_item);
if (!$result_item) {die('Сбой при доступе к базе данных: '.$query_item.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

$item_line_num=0;
while ($item=$result_item->fetch_assoc()) {

	$m_info_q = "SELECT * FROM rent_model_web WHERE model_id='".$item['model_id']."' LIMIT 1";
	$result_m_info = $mysqli->query($m_info_q);
	if (!$result_m_info) {die('Сбой при доступе к базе данных: '.$m_info_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$mi_num=$result_m_info->num_rows;
	$mi=$result_m_info->fetch_assoc();


	if (isset($x_date) && $x_date>0) { // для работы вывода свободности на конкретную дату. несвободные не печатаем

		$return_limit=$x_date+13*60*60;//если возврат после 13 => выдача в 17 - на этот день позже. не должно быть
		$out_limit=$x_date+16*60*60;//если есть выдача до 16, то на утро (12) уже никто не успеет взять. не должно быть
		$x_date_next=$x_date+24*60*60;//след день для сквоздной брони


		$query_kb = "SELECT * FROM karn_brons WHERE inv_n='".$item['item_inv_n']."' AND ((t_to>=$return_limit AND t_to<=$x_date_next) OR (t_from>=$x_date AND t_from<=$out_limit) OR (t_from<=$x_date AND t_to>=$x_date_next) ) AND `status`!='in_process' ORDER BY t_to";
		$result_kb = $mysqli->query($query_kb);
		if (!$result_kb) {die('Сбой при доступе к базе данных: '.$query_kb.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$kb_rows=$result_kb->num_rows;

		if ($kb_rows>=1) {
			continue;
		}
	}


$item_line_num+=1;
//рисуем полосу
echo '<div class="br_cont" style="width:'.(($days_num+1)*24*6+$item_info_width).'px;">
    <div style="display:none;" class="br_pic" id="pic_'.$item['item_id'].'"><a href="'.$mi['page_addr'].'" target="_blank"><img src="'.$mi['l2_pic'].'" /></a><input type="button" class="pic_cl_but" value="X" onclick="show_pic(\''.$item['item_id'].'\'); return false;" /></div>

    			';

	echo '<div class="item_info">'.inv_print($item['item_inv_n']).' <span style="background-color:'.($item['item_place']=='1' ? '#090;' : 'orange;').'">['.$item['item_place'].']</span><a href="#" onclick="show_pic(\''.$item['item_id'].'\'); return false;"> '.$item['model'].'</a> ('.$item['item_size'].' / '.$item['item_rost1'].'-'.$item['item_rost2'].'см. / '.$item['real_item_size'].')</div>';

	for ($i=0; $i<=$days_num; $i++) {
	echo '
			<div class="br_day" style="left:'.($i*24*6+$item_info_width).'px;"></div>
			';
	}

//рисуем занятость
	$finish_date_1=$finish_date-24*3600;
	$query_kb = "SELECT * FROM karn_brons WHERE inv_n='".$item['item_inv_n']."' AND ((t_to<=$finish_date AND t_to>=$start_date) OR (t_from>=$start_date AND t_from<=$finish_date)) AND `status`!='in_process' ORDER BY t_to";
	$result_kb = $mysqli->query($query_kb);
	if (!$result_kb) {die('Сбой при доступе к базе данных: '.$query_kb.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$main_rows=$result_kb->num_rows;

while ($kb=$result_kb->fetch_assoc()) {
	echo '
		<!--<div class="kb_yellow" style="left:'.(($kb['t_from']-$start_date)/3600*6+$item_info_width-4*6).'px;"><div class="br_free_h_left">'.date("G", $kb['t_from']-4*3600).'</div></div>--><a class="br_busy_div" name="2" href="#" id="" onclick="show_a(\''.$kb['kb_id'].'\'); return false;" style="left:'.(($kb['t_from']-$start_date)/3600*6+$item_info_width).'px; width:'.(($kb['t_to']-$kb['t_from'])/3600*6).'px;"><div class="br_free_h_left">'.date("G", $kb['t_from']).'<sup>00</sup></div><div class="br_free_h_right">'.date("G", $kb['t_to']).'<sup>00</sup></div></a>
		<!--<div class="kb_yellow" style="left:'.(($kb['t_from']-$start_date)/3600*6+$item_info_width+(($kb['t_to']-$kb['t_from'])/3600*6)).'px;"><div class="br_free_h_right">'.date("G", $kb['t_to']+4*3600).'</div></div>-->
    		<div class="kb_dop_info" id="br_dop_info_'.$kb['kb_id'].'" style="display:none; left:'.(($kb['t_from']-$start_date)/3600*6+$item_info_width).'px;">
			№брони '.$kb['br_num'].'. Заведена: <i>'.date("d.m (H:i)", $kb['cr_time']).'</i> <br />
			С '.date("(H:i:s) d.m.Y", $kb['t_from']).' по '.date("(H:i:s) d.m.Y", $kb['t_to']).' <br />
			ФИО:'.$kb['fio'].'<br />
			Тел1:'.phone_print($kb['phone1']).'<br />
			Тел2:'.phone_print($kb['phone2']).'<br />
			Почта:'.$kb['mail'].'<br />
			Статус:'.stat_print($kb['status']).': '.($kb['appr_time']>0 ? date("d.m (H:i)", $kb['appr_time']).' - '.user_name($kb['appr_who']) : '').'<br />
			Доп.инфо: '.$kb['info'].'<br />

		<form name="br_srch" action="kb.php" method="post" id="br_srch"  style="display:inline-block;">
			<input type="hidden" name="inv_n" value="'.$kb['inv_n'].'" />
			<input type="hidden" name="t_from" value="'.date("Y-m-d", $kb['t_from']).'" />
			<input type="hidden" name="t_to" value="'.date("Y-m-d", $kb['t_to']).'" />
			<input name="action" type="submit" value="к брони" />
		</form>
		<input type="button" value="отмена" onclick="show_a(\''.$kb['kb_id'].'\'); return false;" />

			</div>
			';
}
echo '</div>';

$line_period=10; //выводить каждыие ... строк
if ($item_line_num/$line_period==round($item_line_num/$line_period)) {
	echo $days_line;
}

}//end of tovar line_while



echo '</body></html>';


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


function inv_print ($inv_n) {

	$output=substr($inv_n, 0, 3).'-'.substr($inv_n, 3);

	return $output;

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

function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}


function phone_to_n ($ph) {
	$ph=preg_replace("|[^0-9]|i", "", $ph);
	return $ph;
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
