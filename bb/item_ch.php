<?php
session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных

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

//$item_inv_n=7231;
				
$query = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$item_inv_n'";
$result = mysql_query($query);
if (!$result) die("Сбой при доступе к базе данных: '$query'".mysql_error());

$item_rows = mysql_num_rows($result);

	if ($item_rows==1) {
		
		$item=mysql_fetch_array($result);
			
			$query_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$item['model_id']."'";
			$result_model = mysql_query($query_model);
			if (!$result_model) die("Сбой при доступе к базе данных: '$query_model'".mysql_error());
				$model=mysql_fetch_array($result_model);
			
			$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$model['tovar_rent_cat_id']."'";
			$result_cat = mysql_query($query_cat);
			if (!$result_cat) die("Сбой при доступе к базе данных: '$query_cat'".mysql_error());
				$cat=mysql_fetch_array($result_cat);
				
			
			$query_tarif = "SELECT * FROM rent_tarif_act WHERE model_id='".$item['model_id']."' ORDER BY sort_num, kol_vo";
			$result_tarif = mysql_query($query_tarif);
			if (!$result_tarif) die("Сбой при доступе к базе данных: '$query_tarif'".mysql_error());
			$tarif_rows = mysql_num_rows($result_tarif);
				
			
			$model['color']=='0' ? ($color='') : ($color=', цвет:'.$model['color']); // если цвет отсутствует - то ничего не выводим, иначе выводим цвет

		if ($tarif_rows>0) {
			$tarif_code='			
			<table border="1" cellspacing="0">
				  <tr>
				    <th scope="col">сумма</th>
				    <th scope="col">за весь период</th>
				    <th scope="col">за шаг</th>
				    <th scope="col">выбрать тариф</th>
				  </tr>';
			
			while ($tarif=mysql_fetch_array($result_tarif)) {
				  	$tarif_code=$tarif_code.'
				  <tr>
				    <td>'.$tarif['rent_amount'].' тыс.руб. <input type="hidden" value="'.$tarif['rent_amount'].'" id="rent_amount_'.$tarif['tarif_id'].'" /></td>
				   	<td> за '.$tarif['kol_vo'].' '.tenor_print($tarif['step'], $tarif['kol_vo']).'<input type="hidden" value="'.$tarif['kol_vo'].'" id="kol_vo_'.$tarif['tarif_id'].'" /><input type="hidden" value="'.$tarif['kol_vo_min'].'" id="kol_vo_min_'.$tarif['tarif_id'].'" /><input type="hidden" value="'.$tarif['step'].'" id="step_'.$tarif['tarif_id'].'" /></td>
				   	<td>='.$tarif['rent_per_step'].' тыс. руб. в '.tenor_print($tarif['step'], 'd').' <input type="hidden" value="'.$tarif['rent_per_step'].'" id="rent_per_step_'.$tarif['tarif_id'].'" /></td>
				    <td><input type="button" name="button" id="button" value="Выбрать" onclick="apply_tarif(\\\''.$tarif['tarif_id'].'\\\'); return false;" /></td>
				  </tr>';
			}
				  
			$tarif_code=$tarif_code.'</table>';
			}
		else {$tarif_code='<p style="font-weight:bold; font-size:18px; color:#F00;">Для данного товара тарифы еще не введены. Заполняйте руками.</p>';}
			
			$tarif_code=str_replace(array("\r\n", "\r", "\n"), "", $tarif_code); //превращаем в одну строку, иначе javascript не поймет
			
						
			echo'
			ch_result=\'ok\';
			model_name=\''.$cat['dog_name'].' '.$model['producer'].', модель: '.$model['model'].$color.', в комплекте: '.$model['set'].', инв.№: '.$item_inv_n.' \';
			dog_price=\''.$model['agr_price'].'\';
			dog_price_cur=\''.$model['agr_price_cur'].'\';
			document.getElementById(\'tarif_div\').innerHTML=\''.$tarif_code.'\';
						
			';
			}//end of if
		
		else {
			echo '
			ch_result=\'no\';
			';
		}


				
				
				
				
				

				
function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
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



?>