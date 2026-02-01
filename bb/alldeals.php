<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных

//------- proverka paroley

isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941) {
	die('
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			</head>
	<title>Авторизация</title>
	<body>
	
	<form action="index.php" method="post">
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>
	');
}

//-----------proverka paroley


echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<title>Сделки</title>
<body>';
?>
		
<script language="javascript">

function send_form () {

	valid = true;

	k_from = k_to = "";

	// проверка клиента
	if (document.getElementById('i_from_date').value=="")
	{k_from="Период с, ";
     valid = false;}

	if (document.getElementById('i_to_date').value=="")
	{k_to="Период по, ";
     valid = false;}

	
	if (valid==false){
	alert ('Заполните даты периода! В частности: ' + k_from + k_to);
		}

	return valid;

}//enf of valid if
		
		
</script>		

<?php 

//Проверка входящей информации
//		echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//		foreach ($_POST as $key => $value) {
//			echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//		}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";



echo '
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a> 
	<a class="div_item" href="/bb/dogovor.php">Новый договор</a>
</div>

';

$result_dl=FALSE;
$cat_id='def';
$i_from_date='';
$i_to_date='';
$prev_action='no';
$cat_qr='';

	$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
	$result_cats = mysql_query($query_cats);
	if (!$result_cats) die("Сбой при доступе к базе данных: '$query_cats'".mysql_error());



foreach ($_POST as $key => $value) {
				$$key = get_post($key);
			}	

//if (isset($_POST['action'])) {$prev_action='no';}//if an action button is pressed,  no prev_action should be
			

if ($cat_id!='def') {
	if ($cat_id<10) {
		$cat_qr=" AND item_inv_n LIKE '70".$cat_id."%'";
	}
	else {
		$cat_qr=" AND item_inv_n LIKE '7".$cat_id."%'";
	}
}			
			
	if ($prev_action!='no' && !isset($_POST['action'])) {
		$action=$prev_action;
		$_POST['action']=$action;//that is to activate switch, which is linked to POST action (not $action)
		
	}		

if (isset($_POST['action'])) {
	
	
	switch ($action) {

	case 'сегодня':
		
		$date_s=strtotime(date("Y-m-d"));
		
		$query_dl = "SELECT * FROM deals WHERE start_date = '$date_s'".$cat_qr." ORDER BY deal_id DESC";
		$result_dl = mysql_query($query_dl);
		if (!$result_dl) die("Сбой при доступе к базе данных: '$query_dl'".mysql_error());
		$dl_num=mysql_num_rows($result_dl);
		
		$prev_action='сегодня';
		
		$i_from_date=date("Y-m-d");
		$i_to_date=date("Y-m-d");
					
		break;
		
	case 'вчера':
		
		$today=getdate(time());
		
		$from_date=mktime(0, 0, 0, $today['mon'], ($today['mday']-1), $today['year']);
				
		$query_dl = "SELECT * FROM deals WHERE start_date = '$from_date'".$cat_qr." ORDER BY deal_id DESC";
		$result_dl = mysql_query($query_dl);
		if (!$result_dl) die("Сбой при доступе к базе данных: '$query_dl'".mysql_error());
		$dl_num=mysql_num_rows($result_dl);
		
		$prev_action='вчера';
		$i_from_date=date("Y-m-d", $from_date);
		$i_to_date=date("Y-m-d", $from_date);
		
		break;
		
	case 'завтра+':
		
		$today=getdate(time());
		
		$from_date=mktime(0, 0, 0, $today['mon'], ($today['mday']+1), $today['year']);
		
		$query_dl = "SELECT * FROM deals WHERE start_date >= '$from_date'".$cat_qr." ORDER BY deal_id DESC";
		$result_dl = mysql_query($query_dl);
		if (!$result_dl) die("Сбой при доступе к базе данных: '$query_dl'".mysql_error());
		$dl_num=mysql_num_rows($result_dl);
		
		$prev_action='завтра+';
		$i_from_date=date("Y-m-d", $from_date);
		$i_to_date='';
				
	break;
		
	case 'показать':
		
		$from_date=strtotime($i_from_date);
		$to_date=strtotime($i_to_date);
		
		$query_dl = "SELECT * FROM deals WHERE start_date >= '$from_date' AND start_date <= '$to_date'".$cat_qr." ORDER BY deal_id DESC";
		$result_dl = mysql_query($query_dl);
		if (!$result_dl) die("Сбой при доступе к базе данных: '$query_dl'".mysql_error());
		$dl_num=mysql_num_rows($result_dl);
		
		$prev_action='показать';
		
		break;		
		
	}//end of switch
}//end of if
else {
			
		$query_dl = "SELECT * FROM deals WHERE deal_id = '0'";
		$result_dl = mysql_query($query_dl);
		if (!$result_dl) die("Сбой при доступе к базе данных: '$query_dl'".mysql_error());
		$dl_num=mysql_num_rows($result_dl);
}

/*


		$from_date=strtotime('2013-08-01');
		$to_date=strtotime('2013-08-04');
		echo $from_date.'t'.date("d.m.Y",$from_date).'<br />';
		echo $to_date.'t'.date("d.m.Y",$to_date).'<br />';

		$query_dl = "SELECT * FROM deals WHERE start_date >= '$from_date' AND start_date <= '$to_date'";
		$result_dl = mysql_query($query_dl);
		if (!$result_dl) die("Сбой при доступе к базе данных: '$query_dl'".mysql_error());
	*/			
		
echo '
<form name="dates" method="post" id="srch_form" action="alldeals.php">
<input type="submit" name="action" value="сегодня" /> <input type="submit" name="action" value="вчера" /> <input type="submit" name="action" value="завтра+" /> За период:
c <input type="date" name="i_from_date" id="i_from_date" value="'.$i_from_date.'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.$i_to_date.'" /> <input type="submit" name="action" value="показать" onclick="return send_form();" /><br />
Фильтр сделок по категории:
<select name="cat_id" id="new_select" onchange="document.getElementById(\'srch_form\').submit();">
			  		<option value="def">все категории</option>
			    ';
			while ($cat_names = mysql_fetch_array($result_cats)) {
			echo '<option value="'.$cat_names['tovar_rent_cat_id'].'" '.sel_d($cat_names['tovar_rent_cat_id'], $cat_id).' >'.good_print($cat_names['rent_cat_name']).'</option>';
			}
			
			echo '    	
    </select>
<input type="hidden" name="prev_action" id="prev_action" value="'.$prev_action.'" />		
</form>
<div id="svod"></div>
<table border="1" cellspacing="0">
<tr>
	<th>id</th>
	<th style="width:400px;">Товар</th>
	<th style="width:130px;">Дата сделки<br />период</th>
	<th>Ст-ть аренды</th>
	<th>Адрес (доставки), ФИО, телефоны</th>
	<th>Доп. инфо</th>
	<th>Статус</th>
	<th>Действия</th>
</tr>';

if (isset($result_dl)) {
$dl_sum=0;

while ($dl_def=mysql_fetch_array($result_dl)) {

	$query_cl_def = "SELECT * FROM clients WHERE client_id='".$dl_def['client_id']."'";
		$result_cl_def = mysql_query($query_cl_def);
		if (!$result_cl_def) die("Сбой при доступе к базе данных: '$query_cl_def'".mysql_error());
		$cl_def=mysql_fetch_array($result_cl_def);
	
$dl_sum=$dl_sum+$dl_def['r_to_pay'];	
	
	echo'
<tr>
	<td>'.$dl_def['deal_id'].'</td>
	<td>'.$dl_def['tovar'].'</td>
	<td> '.date("d.m.y", $dl_def['start_date']).' <i>('.date("H:i", $dl_def['cr_time']).')</i><br />'.number_format($dl_def['rent_tenor'], 0, ',', ' ').' '.$dl_def['step'].'</td>
	<td>'.number_format($dl_def['r_to_pay'], 0, ',', ' ').'</td>
	<td>'.$cl_def['family'].' '.$cl_def['name'].' '.$cl_def['otch'].', '.$cl_def['city'].', ул. '.$cl_def['str'].', '.$cl_def['dom'].'-'.$cl_def['kv'].', тел.: '.$cl_def['phone_1'].', '.$cl_def['phone_2'].'</td>
	<td>'.$dl_def['deal_info'].'</td>
	<td>'.($dl_def['deal_status']=='new' ? 'не оформлено' : $dl_def['deal_status']).'</td>
	<td>
	<form action="/bb/dogovor.php" method="post">
	<input type="hidden" name="client_id" value="'.$cl_def['client_id'].'" />
	<input type="hidden" name="deal_id" value="'.$dl_def['deal_id'].'" />
	<input type="submit" name="" value="оформить" />
	</form>
	</td>
</tr>
';
}
} //end of if
echo'
</table>
<script type="text/javascript">
document.getElementById(\'svod\').innerHTML="Всего сделок за период: '.$dl_num.', на сумму: '.number_format($dl_sum, 0, ',', ' ').' тыс. руб.";
</script>
	
';




function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}



function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}



function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}


?>