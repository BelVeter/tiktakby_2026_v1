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
				
switch ($par2) {

	case 'cat_producer':
		$query = "SELECT DISTINCT producer FROM tovar_rent WHERE tovar_rent_cat_id='$cat_id' ORDER BY producer";
		$result = mysql_query($query);
		if (!$result) die("Сбой при доступе к базе данных: '$query'".mysql_error());
		$rows = mysql_num_rows($result);

		if ($rows==0) {
			echo'<option value="0">нет данных</option>';
		}
		
		if ($rows>0) {
				
			echo'<option value="0">выберите производителя</option>';
			while ($producer=mysql_fetch_array($result)) {
				echo'<option value="'.good_print($producer['producer']).'">'.good_print($producer['producer']).'</option>';
			}
		}
	break;
	
	
	case 'cat_model':
		$query = "SELECT DISTINCT model FROM tovar_rent WHERE tovar_rent_cat_id='$cat_id' ORDER BY model";
		$result = mysql_query($query);
		if (!$result) die("Сбой при доступе к базе данных: '$query'".mysql_error());
		
		$rows = mysql_num_rows($result);
		
		if ($rows==0) {
			echo'<option value="0">нет данных</option>';
		}
		
		if ($rows>0) {
			echo'<option value="0">выберите модель</option>';
				while ($model=mysql_fetch_array($result)) {
					echo'<option value="'.good_print($model['model']).'">'.good_print($model['model']).'</option>';
					}
		}
		
	break;
	
	
	case 'producer':
		$query = "SELECT DISTINCT model FROM tovar_rent WHERE tovar_rent_cat_id='$cat_id' AND producer='$producer' ORDER BY model";
		$result = mysql_query($query);
		if (!$result) die("Сбой при доступе к базе данных: '$query'".mysql_error());

	echo'<option value="0">выберите модель</option>';
	while ($model=mysql_fetch_array($result)) {
		echo'<option value="'.good_print($model['model']).'">'.good_print($model['model']).'</option>';
	}
		
	break;
	
	
	case 'model':
		$query = "SELECT * FROM tovar_rent WHERE model='$model_name' AND tovar_rent_cat_id='$cat_id' AND producer='$producer'";
		$result = mysql_query($query);
		if (!$result) die("Сбой при доступе к базе данных: '$query'".mysql_error());

	$model_rows = mysql_num_rows($result);
			
		if ($model_rows==1) {
			$model=mysql_fetch_array($result);
				
			echo'
			model_rows=1;
			model_producer=\''.good_print2($model['producer']).'\';
			model_color=\'<option value="'.good_print2($model['color']).'">'.good_print2($model['color']).'</option>\';
			model_id=\''.$model['tovar_rent_id'].'\';
			model_set=\''.good_print2($model['set']).'\';
			model_price=\''.$model['agr_price'].'\';
			model_price_cur=\''.good_print2($model['agr_price_cur']).'\';
			lom_srok=\''.good_print2($model['lom_srok']).'\';
			';
			}
		
		else {
			echo 'model_rows=2; color_names=\'<option value="0">----------</option>';
			while ($model=mysql_fetch_array($result)) {
				$producer_w=good_print2($model['producer']);
				echo '<option value="'.good_print2($model['color']).'">'.good_print2($model['color']).'</option>';
				}
			echo '\'; 	  model_producer=\''.$producer_w.'\';';
		}
		
	
	
		
	break;
	

	case 'color':
		$query = "SELECT * FROM tovar_rent WHERE model='$model_name' AND color='$color_name' AND tovar_rent_cat_id='$cat_id' AND producer='$producer'";
		$result = mysql_query($query);
		if (!$result) die("Сбой при доступе к базе данных: '$query'".mysql_error());

			$model=mysql_fetch_array($result);
				
			echo'
			model_producer=\''.good_print($model['producer']).'\';
			model_color=\'<option value="'.good_print($model['color']).'">'.good_print($model['color']).'</option>\';
			model_id=\''.$model['tovar_rent_id'].'\';
			model_set=\''.good_print($model['set']).'\';
			model_price=\''.$model['agr_price'].'\';
			model_price_cur=\''.good_print($model['agr_price_cur']).'\';
			lom_srok=\''.good_print($model['lom_srok']).'\';
			';
					
	
	break;
	
	
	case 'dog_name_select':
		
		$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$cat_id."'";
		$result_cat = mysql_query($query_cat);
		if (!$result_cat) die("Сбой при доступе к базе данных: '$query_cat'".mysql_error());
		$cat_def=mysql_fetch_array($result_cat);
		
		echo $cat_def['dog_name'];
		
		
	break;
	
	
}




function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}

function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;	
}

function good_print2($var)
{
	$var=addslashes(htmlspecialchars(stripslashes($var)));
	return $var;
}

?>