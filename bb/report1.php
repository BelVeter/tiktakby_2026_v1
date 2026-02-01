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


//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//		foreach ($_POST as $key => $value) {
//			echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//		}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";





$cat_id='def';
$cat_qr='';



foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}


$from_date=strtotime($i_from_date);
$to_date=strtotime($i_to_date);


if ($cat_id!='def') {
		$cat_qr=" WHERE tovar_rent_cat_id='$cat_id'";
}


$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
$result_cats = mysql_query($query_cats);
if (!$result_cats) die("Сбой при доступе к базе данных: '$query_cats'".mysql_error());




echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Сделки</title>
</head>
<body>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a> 
	<a class="div_item" href="/bb/alldeals.php">Все сделки</a>
</div>		

<strong>Внимание !!! Отчет работает только по тем товарам, которые введены в базу !!! (т.е. с новым инвентарным номером).</strong><br />

<form name="dates" method="post" id="srch_form" action="report1.php">
Анализ: за период:
c <input type="date" name="i_from_date" id="i_from_date" value="'.$i_from_date.'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.$i_to_date.'" /> 
по категории:
<select name="cat_id" id="new_select" onchange="document.getElementById(\'srch_form\').submit();">
			  		<option value="def">все категории</option>
			    ';
			while ($cat_names = mysql_fetch_array($result_cats)) {
			echo '<option value="'.$cat_names['tovar_rent_cat_id'].'" '.sel_d($cat_names['tovar_rent_cat_id'], $cat_id).' >'.good_print($cat_names['rent_cat_name']).'</option>';
			}
			
			echo '    	
    </select> 
<input type="submit" name="action" value="показать" onclick="return send_form();" />

</form>

		
		
		
		
		
		
';

echo'
<table border="1" cellspacing="0" style="text-align: right;">
<tr>
<th>модель</th>
<th>кол-во тов.</th>
<th>Кол-во сдач</th>
<th>Сумма сделок</th>
<th>Цена пр-я</th>
		
<th>Ср. кол-во сделок на товар</th>
<th>Ср. сумма сделки</th>
		
<th>кол-во тов. 2013</th>
<th>Кол-во сдач 2013</th>
<th>Сумма сделок 2013</th>
<th>Цена пр-я 2013</th>
<th>Резервный столб</th>
</tr>';

if (isset($i_from_date)) {

$query_md = "SELECT * FROM tovar_rent".$cat_qr." ORDER BY tovar_rent_cat_id, model";
$result_md = mysql_query($query_md);
if (!$result_md) die("Сбой при доступе к базе данных: '$query_md'".mysql_error());
//$md_num=mysql_num_rows($result_md);

while ($model=mysql_fetch_array($result_md)) {
	
	$query_cats = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$model['tovar_rent_cat_id']."'";
	$result_cats = mysql_query($query_cats);
	if (!$result_cats) die("Сбой при доступе к базе данных: '$query_cats'".mysql_error());
	$cat=mysql_fetch_array($result_cats);
	
	
	

		$model_amount=0;
		$buy_price=0;
		$item_num_2013=0;
		$buy_price_2013=0;
		$deal_num_all=0;
	
		$query_it = "SELECT * FROM tovar_rent_items WHERE model_id='".$model['tovar_rent_id']."'";
		$result_it = mysql_query($query_it);
		if (!$result_it) die("Сбой при доступе к базе данных: '$query_it'".mysql_error());
		$item_num_all=mysql_num_rows($result_it);
			

		while ($item=mysql_fetch_array($result_it)) {
			$item['buy_date']>=strtotime('2013-01-01') ? $item_num_2013++ : '';
			$buy_price=$buy_price + $item['buy_price']*$item['exch_to_byr'];
			$item['buy_date']>=strtotime('2013-01-01') ? $buy_price_2013=$buy_price_2013 + $item['buy_price']*$item['exch_to_byr'] : '';

				$query_dl = "SELECT * FROM deals WHERE item_inv_n='".$item['item_inv_n']."' AND start_date >= '$from_date' AND start_date <= '$to_date'";
				$result_dl = mysql_query($query_dl);
				if (!$result_dl) die("Сбой при доступе к базе данных: '$query_dl'".mysql_error());
				$deal_num_all=$deal_num_all+mysql_num_rows($result_dl);
				
				while ($deal=mysql_fetch_array($result_dl)) {
					$model_amount=$model_amount + $deal['r_to_pay'];
				}
					
				
				
				
				
			}//end of it-while
	
	
	$item_num_all>0 ? $mid_deal_num=$deal_num_all/$item_num_all : $mid_deal_num=0;
	$deal_num_all>0 ? $mid_deal_amount=$model_amount/$deal_num_all : $mid_deal_amount=0;
	
	
echo '
<tr>
	<td style="text-align: left;"> <strong>'.$cat['rent_cat_name'].'</strong> '.$model['model'].'</td>
	<td>'.number_format($item_num_all, 0, ',', ' ').'</td>
	<td>'.number_format($deal_num_all, 0, ',', ' ').'</td>
	<td>'.number_format($model_amount, 0, ',', ' ').'</td>
	<td>'.number_format(($buy_price*9.600), 0, ',', ' ').'</td>	
					
	<td>'.number_format($mid_deal_num, 1, ',', ' ').'</td>
	<td>'.number_format($mid_deal_amount, 1, ',', ' ').'</td>
			
	<td>'.number_format($item_num_2013, 0, ',', ' ').'</td>
	<td>'.number_format(($mid_deal_num*$item_num_2013), 1, ',', ' ').'</td>
	<td>'.number_format(($mid_deal_num*$item_num_2013)*$mid_deal_amount, 1, ',', ' ').'</td>
			
	<td>'.number_format(($buy_price_2013*9.600), 1, ',', ' ').'</td>	
	<td></td>
</tr>			
		';
}//end of model-while
}//end of main if for I-from_date


echo '</table></body></html>';






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