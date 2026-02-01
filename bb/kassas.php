<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных по новому

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
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Касса/расходы.</title>
<body>
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>
		
';




//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";
?>

<script language="javascript">

	function line_calc () {
		
		document.getElementById('new_ish_ost').value=document.getElementById('new_vh_ost').value*1+document.getElementById('new_prihod').value*1+document.getElementById('new_rashod').value*1;
		
	}


	function save_ch () {
		line_calc ();
		
		var output=true;
		
		var last_date = new Date(document.getElementById('last_line_acc_date').value);
		var new_date = new Date(document.getElementById('new_line_acc_date').value);
		var today_date=new Date();

		if (new_date>today_date) {
			alert ('Нельзя внести информацию в кассу в будущем! Проверьте дату.');
			output=false;
		}

		if (new_date<last_date) {
			alert ('Нельзя внести информацию в кассу ранее последней операции! Проверьте дату.');
			output=false;
		}

		if (document.getElementById('new_prihod').value<=0 && document.getElementById('new_rashod').value>=0) {
			alert ('Нет смысла вносить операцию с пустым приходом И раходом!');
			output=false;
		}

		

		return output;		
	}


	function main_show () {
		var output2=true;

		if (document.getElementById('kassa_ch_inp').value=='no_kassa') {
			alert ('Выберите кассу!');
			output2=false;
		}

		if (document.getElementById('kassa_from').value=='') {
			alert ('Выберите дату!');
			output2=false;
		}

		return output2;
		
	}



</script>

<?php 
$kassa_ch='no_kassa';//!!! временная
$vh_new=0;
$last_l_date=0;
$button='';

$today_t=getdate(time());

$today=mktime(0, 0, 0, $today_t['mon'], ($today_t['mday']), $today_t['year']);
$yesterday=mktime(0, 0, 0, $today_t['mon'], ($today_t['mday']-1), $today_t['year']);
$kassa_from=date ("Y-m-d", $yesterday);// с какой даты показываем по умолчанию


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}



if (isset($_POST['action'])) {

	switch ($action) {

		case 'сохранить':
			//вставка линии в кассу
			$new_line_acc_date=strtotime($new_line_acc_date); //приводим в формат юникс дату календаря гггг-мм-дд
			
			$line_q="INSERT INTO ".$kassa_ch." VALUES('', '$new_line_acc_date', '', '$new_vh_ost', '$new_prihod', '$new_rashod', '$new_ish_ost', 'final', '', '".time()."', '".$_SESSION['user_id']."', '','')";
			$result_line = $mysqli->query($line_q);
			if (!$result_line) {die('Сбой при доступе к базе данных: '.$line_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			
			break;
		
		case 'удалить':
			$del_q="DELETE FROM '$kassa_ch' WHERE id='$line_id'";
			$result_del = $mysqli->query($del_q);
			if (!$result_del) {die('Сбой при доступе к базе данных: '.$del_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			
			break;

	}//end of switch

}//end of action if

if ($kassa_ch!='no_kassa') {
//выборка записей по кассе
$acc_from=strtotime($kassa_from);
	
$line_q="SELECT * FROM ".$kassa_ch." WHERE acc_date>='$acc_from' ORDER BY acc_date";
$result_line2 = $mysqli->query($line_q);
if (!$result_line2) {die('Сбой при доступе к базе данных: '.$line_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

if ($result_line2->num_rows<1) {
	$line_q="SELECT * FROM ".$kassa_ch." ORDER BY acc_date DESC LIMIT 0,1";
	$result_line2 = $mysqli->query($line_q);
	if (!$result_line2) {die('Сбой при доступе к базе данных: '.$line_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	
}

}//end of no_kassa if


//onchange="document.getElementById(\'srch_form\').submit();"
		
echo'
<form name="kassa_inp" id="kassa_inp" method="post" action="kassas.php">
<select name="kassa_ch" id="kassa_ch_inp">
		<option value="no_kassa">Выберите кассу</option>
		<option value="kassa_1" '.sel_d($kassa_ch, 'kassa_1').'>Касса №1</option>
		<option value="kassa_2" '.sel_d($kassa_ch, 'kassa_2').'>Касса №2</option>				
		<option value="kassa_1k" '.sel_d($kassa_ch, 'kassa_1k').'>Касса №1K</option>
		<option value="kassa_2k" '.sel_d($kassa_ch, 'kassa_2k').'>Касса №2K</option>				
		
</select>
Показывать операции с: <input type="date" name="kassa_from" id="kassa_from" value="'.$kassa_from.'" />
		<input type="submit" value="показать" onclick="return main_show ();" />
</form>

';

if ($kassa_ch=='no_kassa') {die('<h1>Выберите кассу!</h1>');}

echo '
		
<form name="new_line" id="new_line" method="post" action="kassas.php"></form>

<h1>'.kassa_name($kassa_ch).'</h1>		
<table border="1" cellspacing="0" id="stats">
<tr>
	<th>дата</th>
	<th>вх.остаток</th>
	<th>приход (+)</th>
	<th>расход (-)</th>				
	<th>исх.остаток</th>
	<th>действия</th>				
</tr>';

$num=0;
while ($line = $result_line2->fetch_assoc()) {
	$num+=1;
	if ($result_line2->num_rows==$num) {
		$button='
		<form name="del_form" id="del_form" method="post" action="kassas.php">
			<input type="hidden" name="line_id" value="'.$line['id'].'" />
			<input type="submit" name="action" value="удалить" />
			
					<input type="hidden" name="kassa_ch" value="'.$kassa_ch.'" />
					<input type="hidden" name="kassa_from" value="'.$kassa_from.'" />
			
		</form>
		';
	}	
	echo '
<tr>
	<td><input type="date" name="line_acc_date" value="'.date("Y-m-d", $line['acc_date']).'" readonly="readonly" /></td>
	<td><input class="kassa_num" type="number" step="any" name="vh_ost" id="vh_ost" value="'.$line['vh_ost'].'" readonly="readonly" /></td>
	<td><input class="kassa_num" type="number" step="any" min="0" name="prihod" id="prihod" value="'.$line['prihod'].'" readonly="readonly" /></td>
	<td><input class="kassa_num" type="number" step="any" min="0" name="rashod" id="rashod" value="'.$line['rashod'].'" readonly="readonly" /></td>
	<td><input class="kassa_num" type="number" step="any" name="ish_ost" id="ish_ost" value="'.$line['ish_ost'].'" readonly="readonly" /></td>
	<td>'.$button.'</td>
</tr>		
		';
	$vh_new=$line['ish_ost'];
	$last_l_date=$line['acc_date'];
}

echo '
					
<tr>
	<td>
					<input type="date" name="last_line_acc_date" id="last_line_acc_date" value="'.date("Y-m-d", $last_l_date).'" style="display:none;" form="new_line"/>
					<input type="hidden" name="kassa_ch" id="kassa_ch_new" value="'.$kassa_ch.'" form="new_line" />
					<input type="hidden" name="kassa_from" id="kassa_from_new" value="'.$kassa_from.'" form="new_line" />
	<input type="date" name="new_line_acc_date" id="new_line_acc_date" value="'.date("Y-m-d").'" form="new_line" /></td>
	<td><input class="kassa_num" type="number" step="any" name="new_vh_ost" id="new_vh_ost" value="'.$vh_new.'" form="new_line" readonly="readonly" /></td>
	<td><input class="kassa_num" type="number" step="any" min="0" name="new_prihod" id="new_prihod" value="" form="new_line" /></td>
	<td><input class="kassa_num" type="number" step="any" max="0" name="new_rashod" id="new_rashod" value="" form="new_line" /></td>
	<td><input class="kassa_num" type="number" step="any" name="new_ish_ost" id="new_ish_ost" value="" form="new_line" readonly="readonly" /></td>
	<td><input type="button" value="пересчет" onclick="line_calc ();" /><input type="submit" name="action" value="сохранить" form="new_line" onclick="return save_ch ();"></td>
</tr>		
</table>		
		
		
		';






function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}


function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}


function kassa_name ($k_name) {
	switch ($k_name) {
		case 'kassa_1':
			return 'Касса №1.';
		break;
		
		case 'kassa_2':
			return 'Касса №2.';
		break;
		
		default:
			return 'Касса ХЗ.';
		break;
	}
	;
}


?>