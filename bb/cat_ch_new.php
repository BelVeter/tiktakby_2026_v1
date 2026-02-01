<?php
session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); // включаем подключение к базе данных

\bb\Base::loginCheck();

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

$mysqli = \bb\Db::getInstance()->getConnection();

switch ($par2) {

  case 'cur_change':
      //date
      $date = new DateTime();
      if (isset($_POST['date'])){
        $date = new DateTime($_POST['date']);
      }
      echo \bb\Base::getExchRateToUsd($date, $cur);
    break;

	case 'cat_producer':
		$query = "SELECT DISTINCT producer FROM tovar_rent WHERE tovar_rent_cat_id='$cat_id' ORDER BY producer";
		$result = $mysqli->query($query);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


		$rows=$result->num_rows;

		if ($rows==0) {
			$output='<option value="0">нет данных</option>';
		}

		if ($rows>0) {

			$output='<option value="0">"выберите производителя"</option>';
			while ($producer=$result->fetch_assoc()) {
				$output.='<option value="'.good_print($producer['producer']).'">'.good_print($producer['producer']).'</option>';
			}
		}


		$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет

		echo 'document.getElementById(\'producer_select_old\').innerHTML=\''.$output.'\';';

		break;


	case 'producer':
		$query = "SELECT DISTINCT model FROM tovar_rent WHERE tovar_rent_cat_id='$cat_id' AND producer='$producer' ORDER BY model";
		$result = $mysqli->query($query);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

		$rows=$result->num_rows;

		if ($rows==0) {
			$output='<option value="0">нет данных</option>';
		}

		if ($rows>0) {
		$output='<option value="0">выберите модель</option>';
			while ($model=$result->fetch_assoc()) {
				$output.='<option value="'.good_print($model['model']).'">'.good_print($model['model']).'</option>';
			}
		}


		$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет

		echo 'document.getElementById(\'model_select_old\').innerHTML=\''.$output.'\';';

	break;

	case 'model':
		$query = "SELECT * FROM tovar_rent WHERE model='$model_name' AND tovar_rent_cat_id='$cat_id' AND producer='$producer'";
		$result = $mysqli->query($query);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

		$model_rows=$result->num_rows;

		if ($model_rows==0) {
			echo'document.getElementById(\'color_select_old\').innerHTML=\'<option value="0">нет данных</option>\';
				//обнуляем последующие поля
				document.getElementById(\'model_id\').value=\'\';
				document.getElementById(\'m_set_old\').value=\'\';
				document.getElementById(\'m_price_old\').value=\'\';
				document.getElementById(\'m_price_cur_old\').value=\'\';
				document.getElementById(\'age_from_old\').value=\'\';
				document.getElementById(\'age_to_old\').value=\'\';
				document.getElementById(\'weight_from_old\').value=\'\';
				document.getElementById(\'weight_to_old\').value=\'\';
					';

		}

		if ($model_rows==1) {

			$model=$result->fetch_assoc();


			echo '
			document.getElementById(\'color_select_old\').innerHTML=\'<option value="'.good_print($model['color']).'">'.good_print($model['color']).'</option>\';
			document.getElementById(\'m_set_old\').value=\''.good_print($model['set']).'\';
			document.getElementById(\'m_price_old\').value=\''.$model['agr_price'].'\';
			document.getElementById(\'m_price_cur_old\').value=\''.$model['agr_price_cur'].'\';
			document.getElementById(\'lom_srok_old\').value=\''.$model['lom_srok'].'\';
			document.getElementById(\'model_id\').value=\''.$model['tovar_rent_id'].'\';
			document.getElementById(\'model_addr_old\').value=\''.$model['model_addr'].'\';
			document.getElementById(\'ph_addr_old\').value=\''.$model['ph_addr'].'\';
			document.getElementById(\'old_model_id_span\').innerHTML=\''.$model['tovar_rent_id'].'\';
			document.getElementById(\'age_from_old\').value=\''.$model['age_from'].'\';
			document.getElementById(\'age_to_old\').value=\''.$model['age_to'].'\';
			document.getElementById(\'weight_from_old\').value=\''.$model['weight_from'].'\';
			document.getElementById(\'weight_to_old\').value=\''.$model['weight_to'].'\';

					';
		}

		else {
			$output='<option value="выберите цвет">выберите цвет</option>';
			while ($model=$result->fetch_assoc()) {
				$output.='<option value="'.good_print($model['color']).'">'.good_print($model['color']).'</option>';
			}

			$output=str_replace(array("\r\n", "\r", "\n"), "", $output); //превращаем в одну строку, иначе javascript не поймет

			echo 'document.getElementById(\'color_select_old\').innerHTML=\''.$output.'\';

				//обнуляем последующие поля
				document.getElementById(\'model_id\').value=\'\';
				document.getElementById(\'m_set_old\').value=\'\';
				document.getElementById(\'m_price_old\').value=\'\';
				document.getElementById(\'m_price_cur_old\').value=\'\';
				document.getElementById(\'lom_srok_old\').value=\'\';
				document.getElementById(\'model_addr_old\').value=\'\';
				document.getElementById(\'ph_addr_old\').value=\'\';
				document.getElementById(\'age_from_old\').value=\'\';
				document.getElementById(\'age_to_old\').value=\'\';
				document.getElementById(\'weight_from_old\').value=\'\';
				document.getElementById(\'weight_to_old\').value=\'\';

			';

		}

	break;

	case 'color':
		$query = "SELECT * FROM tovar_rent WHERE model='$model_name' AND color='$color_name' AND tovar_rent_cat_id='$cat_id' AND producer='$producer'";
		$result = $mysqli->query($query);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

		$model_rows=$result->num_rows;


		if ($model_rows==0) {
			echo'document.getElementById(\'m_set_old\').innerHTML=\'нет данных\';

				//обнуляем последующие поля
				document.getElementById(\'model_id\').value=\'\';
				document.getElementById(\'m_price_old\').value=\'\';
				document.getElementById(\'m_price_cur_old\').value=\'\';
				document.getElementById(\'age_from_old\').value=\'\';
				document.getElementById(\'age_to_old\').value=\'\';
				document.getElementById(\'weight_from_old\').value=\'\';
				document.getElementById(\'weight_to_old\').value=\'\';

					';

		}


		if ($model_rows==1) {

			$model=$result->fetch_assoc();

			echo '
			document.getElementById(\'m_set_old\').value=\''.good_print($model['set']).'\';
			document.getElementById(\'m_price_old\').value=\''.$model['agr_price'].'\';
			document.getElementById(\'m_price_cur_old\').value=\''.$model['agr_price_cur'].'\';
			document.getElementById(\'lom_srok_old\').value=\''.$model['lom_srok'].'\';
			document.getElementById(\'model_addr_old\').value=\''.$model['model_addr'].'\';
			document.getElementById(\'ph_addr_old\').value=\''.$model['ph_addr'].'\';
			document.getElementById(\'model_id\').value=\''.$model['tovar_rent_id'].'\';
			document.getElementById(\'old_model_id_span\').innerHTML=\''.$model['tovar_rent_id'].'\';
			document.getElementById(\'age_from_old\').value=\''.$model['age_from'].'\';
			document.getElementById(\'age_to_old\').value=\''.$model['age_to'].'\';
			document.getElementById(\'weight_from_old\').value=\''.$model['weight_from'].'\';
			document.getElementById(\'weight_to_old\').value=\''.$model['weight_to'].'\';

					';
		}
		if ($model_rows>1) {
			echo'document.getElementById(\'m_set_old\').value=\'Сбой: более 1 модели соответствует выбранным параметрам. Обратитесь к разработчику.\';

				//обнуляем последующие поля
				document.getElementById(\'model_id\').value=\'\';
				document.getElementById(\'m_price_old\').value=\'\';
				document.getElementById(\'m_price_cur_old\').value=\'\';
				document.getElementById(\'age_from_old\').value=\'\';
				document.getElementById(\'age_to_old\').value=\'\';
				document.getElementById(\'weight_from_old\').value=\'\';
				document.getElementById(\'weight_to_old\').value=\'\';

					';

		}

	break;


	case 'dog_name_select':

		$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$cat_id."'";
		$result = $mysqli->query($query);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$rows=$result->num_rows;

		if ($rows==1) {
			echo $cat_def['dog_name'];
		}
		elseif ($rows<1) {
			echo 'не найдено';
		}
		elseif ($rows>1) {
			echo 'более 1 модели. обратитесь к разработчику';
		}




	break;

	case 'model_cat_ch':
		echo 'alert ("Запустилась проверка категории и модели");';

	break;

}//end of switch

/*
echo '

		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Авторизация</title>
	</head>
	<body>';
*/


//-----------------------------------------------------------------------------------------------------------
function get_post($var)
{
    $mysqli = \bb\Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
}


function good_print($var)
{
	$var=htmlspecialchars((stripslashes($var)), ENT_QUOTES, "UTF-8");
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
