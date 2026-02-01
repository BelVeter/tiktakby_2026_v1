<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных

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
<title>Товары.</title>
<body>

		
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/kr_baza_new.php">Просмотр всех товаров</a>
</div>
		

		';
require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам

//Проверка входящей информации
//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//foreach ($_POST as $key => $value) {
//	echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";




$action='';
$model_id='';
$cat_id='';

$item_def['buy_date']=time();
$cat_def['dog_name']='';

$item_def['exch_to_byr']=1;
$inv_n_upd='';





if (isset($_POST['action'])) {

	foreach ($_POST as $key => $value) {
		$$key = get_post($key);
	}

switch ($action) {
	
	case 'сохранить':
		if ($model_action=='new') {
			
			//получаем id категории (если нужно - создаем ее)
			if ($cat_select_new!='0') {$cat_id=$cat_select_new;}
			else {//проверяем, есть ли такое наименование в категории, если есть - то берем id этой категории и не создаем новую, если нет - создаем новую категорию
				
				$query_cat_ch = "SELECT * FROM tovar_rent_cat WHERE rent_cat_name='$cat_input_new'";
				$result_cat_ch = $mysqli->query($query_cat_ch);
				if (!$result_cat_ch) {die('Сбой при доступе к базе данных: '.$query_cat_ch.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				$cat_num=$result_cat_ch->num_rows;
				
				if ($cat_num>0) {
					$cat_ch=$result_cat_ch->fetch_assoc();
					$cat_id=$cat_ch['tovar_rent_cat_id'];
				}
				else {
					$query_newcat = "INSERT INTO tovar_rent_cat VALUES('', '$cat_input_new', '$cat_input_dog_new')";
					$result_newcat = $mysqli->query($query_newcat);
					if (!$result_newcat) {die('Сбой при доступе к базе данных: '.$query_newcat.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
					
					$cat_id=$mysqli->insert_id;
				}
			}
			
			//определяем наименование производителя
			if ($producer_select_new!='0') {$producer_name=$producer_select_new;}
			else {$producer_name=$producer_input_new;}
			
			//определяем наименование модели
			if ($model_select_new!='0') {$model_name=$model_select_new;}
			else {$model_name=$model_input_new;}
			
			
			// проверяем наличие аналогичной модели, если имеется таковая, то просто используем ее id, иначе - создаем новую модель
			
			$query_mod = "SELECT * FROM tovar_rent WHERE model='$model_name' AND tovar_rent_cat_id='$cat_id' AND producer='$producer_name' AND color='$color_new'";
			$result_mod = $mysqli->query($query_mod);
			if (!$result_mod) {die('Сбой при доступе к базе данных: '.$query_mod.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			$mod_num=$result_mod->num_rows;
			
			if ($mod_num>0) {
				$mod_ch=$result_mod->fetch_assoc();
				$model_id=$mod_ch['tovar_rent_id'];
			}
			else {
			
			//создаем модель на основании полученных данных и получаем ее id:
			$query_new_model = "INSERT INTO tovar_rent VALUES('', '$cat_id', '$producer_name', '$model_name', '$m_set_new', '$color_new', '$m_price_new', '$m_price_cur_new', '$lom_srok_new', '".time()."', '".$_SESSION['user_fio']."', '$model_addr_new', '$ph_addr_new', '', '', '', '')";
			$result_new_model = $mysqli->query($query_new_model);
			if (!$result_new_model) {die('Сбой при доступе к базе данных: '.$query_new_model.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
			$model_id=$mysqli->insert_id;
			//echo 'model_id='.$model_id.'<br />';
			}
		
		}//end of model_action=new
		
		//нужно чтоб обязательно был кат айди. новый получаем вышe, старый сейчас
		if ($model_action=='old') {
			$cat_id=$cat_select_old;
		}
		
		
		//item number within the cathegory calculation
		$query_item_n = "SELECT item_n FROM tovar_rent_items WHERE cat_id='$cat_id' ORDER BY item_n DESC LIMIT 0,1";
		$result_item_n = $mysqli->query($query_item_n);
		if (!$result_item_n) {die('Сбой при доступе к базе данных: '.$query_item_n.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
			$num1=$result_item_n->num_rows;
			
			if ($num1>0) {
				$item1=$result_item_n->fetch_assoc();
				$max_num1=$item1['item_n'];
			}
			else {
				$max_num1=0;
			}
			
		$query_item_n2 = "SELECT item_n FROM tovar_rent_items_arch WHERE cat_id='$cat_id' ORDER BY item_n DESC LIMIT 0,1";
		$result_item_n2 = $mysqli->query($query_item_n2);
		if (!$result_item_n2) {die('Сбой при доступе к базе данных: '.$query_item_n2.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			
			$num2=$result_item_n2->num_rows;
				
			if ($num2>0) {
				$item2=$result_item_n2->fetch_assoc();
				$max_num2=$item2['item_n'];
			}
			else {
				$max_num2=0;
			}

		
		if ($max_num1>=$max_num2) {
			$max_n=$max_num1;
		}
		if ($max_num1<$max_num2) {
			$max_n=$max_num2;
		}	
		

		$item_n=$max_n+1;

	//--- end of max_n calculation

		//item inv n calculation
		if ($cat_id<10) {$cat_n_pl=70;}
		elseif ($cat_id<100) {$cat_n_pl=7;}
		elseif ($cat_id<1000) {$cat_n_pl='';}
		$item_inv_n=$cat_n_pl.$cat_id.$item_n;

		//gotovim nekotorie znachtniya
		$buy_date=strtotime($buy_date);
		if ($model_action=='old') {
			$producer_name=$producer_select_old;
		}
		
		
		$query_new_item = "INSERT INTO tovar_rent_items VALUES('', '$cat_id', '$producer_name', '$model_id', '$item_n', '$item_inv_n', '$item_sex', '$tovar_size', '$real_tovar_size', '$tovar_rost1', '$tovar_rost2', '$item_set', '$buy_date', '$buy_price', '$buy_currency', '$exchange_rate', '$seller', '$info', '".time()."', '".$_SESSION['user_fio']."', '$tovar_status', '', '$item_color', '$tovar_place', '')";
		$result_new_item = $mysqli->query($query_new_item);
		if (!$result_new_item) {die('Сбой при доступе к базе данных: '.$query_new_item.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		
		echo 'Товар успешно введен. <be /> Инвентарный номер:'.$item_inv_n.'<br />';
		
		
		
		die('
			</head>
			<body>
						
				<div class="top_menu">
					<a class="div_item" href="/bb/tovar_new.php">Новый товар</a>
				</div>
				<br /><br />
						
				<div class="top_menu">
					<a class="div_item" href="/bb/rent_tarifs.php">Работа с тарифами</a>
				</div>
				</body></html>
		');
		
		
		
		
	break;
	
	case 'редактировать':
		$query_item = "SELECT * FROM tovar_rent_items WHERE item_id='$item_id'";
		$result_item = $mysqli->query($query_item);
		if (!$result_item) {die('Сбой при доступе к базе данных: '.$query_item.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$item_def=$result_item->fetch_assoc();
		
		$model_id=$item_def['model_id'];
	
		$query_model = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$item_def['model_id']."'";
		$result_model = $mysqli->query($query_model);
		if (!$result_model) {die('Сбой при доступе к базе данных: '.$query_model.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$model_def=$result_model->fetch_assoc();
			
		$query_cat = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$model_def['tovar_rent_cat_id']."'";
		$result_cat = $mysqli->query($query_cat);
		if (!$result_cat) {die('Сбой при доступе к базе данных: '.$query_cat.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
		$cat_def=$result_cat->fetch_assoc();
		$cat_id=$cat_def['tovar_rent_cat_id'];

		$old_style='style="display:none;"';
		$new_style='style="display:inline-block;"';
	
	
	break;
	
	case 'обновить':
		if ($model_action=='old') {

			//пересчитываем инвентарный номер, в случае, если произошла смена категории
			if ($prev_cat_id!=$cat_select_old) {
				//item number within the cathegory calculation
				$cat_id=$cat_select_old; // для расчета инв. номера
				
				$query_item_n = "SELECT item_n FROM tovar_rent_items WHERE cat_id='$cat_id' ORDER BY item_n DESC LIMIT 0,1";
				$result_item_n = $mysqli->query($query_item_n);
				if (!$result_item_n) {die('Сбой при доступе к базе данных: '.$query_item_n.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
				$num1=$result_item_n->num_rows;
					
				if ($num1>0) {
					$item1=$result_item_n->fetch_assoc();
					$max_num1=$item1['item_n'];
				}
				else {
					$max_num1=0;
				}
					
				$query_item_n2 = "SELECT item_n FROM tovar_rent_items_arch WHERE cat_id='$cat_id' ORDER BY item_n DESC LIMIT 0,1";
				$result_item_n2 = $mysqli->query($query_item_n2);
				if (!$result_item_n2) {die('Сбой при доступе к базе данных: '.$query_item_n2.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
					
				$num2=$result_item_n2->num_rows;
				
				if ($num2>0) {
					$item2=$result_item_n2->fetch_assoc();
					$max_num2=$item2['item_n'];
				}
				else {
					$max_num2=0;
				}
				
				
				if ($max_num1>=$max_num2) {
					$max_n=$max_num1;
				}
				if ($max_num1<$max_num2) {
					$max_n=$max_num2;
				}
				
				
				$item_n=$max_n+1;
				
				//--- end of max_n calculation
				
				//item inv n calculation
				if ($cat_id<10) {$cat_n_pl=70;}
				elseif ($cat_id<100) {$cat_n_pl=7;}
				elseif ($cat_id<1000) {$cat_n_pl='';}
				$item_inv_n=$cat_n_pl.$cat_id.$item_n;
				
				//часть кода updatа товара, в случае, если меняется категория
				$inv_n_upd=" cat_id='$cat_select_old', item_inv_n='$item_inv_n', item_n='$item_n', ";
				
			}//end of cat changed if
			
			$buy_date=strtotime($buy_date);
			
			$query_upd = "UPDATE tovar_rent_items SET producer='$producer_select_old', model_id='$model_id',".$inv_n_upd." item_color='$item_color', sex='$item_sex', item_size='$tovar_size', real_item_size='$real_tovar_size', item_rost1='$tovar_rost1', item_rost2='$tovar_rost2', item_set='$item_set', buy_date='$buy_date', buy_price='$buy_price', buy_price_cur='$buy_currency', exch_to_byr='$exchange_rate', seller='$seller', item_info='$info', `status`='$tovar_status', item_place='$tovar_place', cr_ch_date='".time()."', user='".$_SESSION['user_fio']."' WHERE item_id='$item_id_upd'";
			$result_upd = $mysqli->query($query_upd);
			if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
				
			
		}//end of old action
		
		
		
		
		
		if ($model_action=='new') {
			
			if ($update=='update') {
				if ($cat_edit_old!=$cat_edit) {
					$query_cat_ch = "SELECT * FROM tovar_rent_cat WHERE rent_cat_name='$cat_edit' AND rent_cat_name!='$cat_edit_old'";
					$result_cat_ch = $mysqli->query($query_cat_ch);
					if (!$result_cat_ch) {die('Сбой при доступе к базе данных: '.$query_cat_ch.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
					//$cat_names = $result_cats->fetch_assoc();
					$cat_ch_num=$result_cat_ch->num_rows;
					
					if ($cat_ch_num>=1) {
						die('Измененное название категории уже существует! Нельзя повторно вводить одну и ту же категорию!');
					}
					else {
						//выполняем код апдейта категории
						$query_upd = "UPDATE tovar_rent_cat SET rent_cat_name='$cat_edit', dog_name='$cat_input_dog_new' WHERE tovar_rent_cat_id='$prev_cat_id'";
						$result_upd = $mysqli->query($query_upd);
						if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
							
					}
					
				}//end of cat-edit/old
				else {//если категория не менялась - обновляем только наименование договора (на всякий случай)
					$query_upd = "UPDATE tovar_rent_cat SET dog_name='$cat_input_dog_new' WHERE tovar_rent_cat_id='$prev_cat_id'";
					$result_upd = $mysqli->query($query_upd);
					if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				}
			
				
				$producer_select_new=='0' ? $producer_name=$producer_input_new : $producer_name=$producer_select_new;
				$model_select_new=='0' ? $model_name=$model_input_new : $model_name=$model_select_new;
				
				$query_upd = "UPDATE tovar_rent SET producer='$producer_name', model='$model_name', color='$color_new', `set`='$m_set_new',  agr_price='$m_price_new', agr_price_cur='$m_price_cur_new', lom_srok='$lom_srok_new', model_addr='$model_addr_new', ph_addr='$ph_addr_new', cr_ch_date='".time()."', user='".$_SESSION['user_fio']."' WHERE tovar_rent_id='$model_id'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
				
				
				//обновляем информацию об айтеме
				
				$buy_date=strtotime($buy_date);
					
				$query_upd = "UPDATE tovar_rent_items SET producer='$producer_name', item_color='$item_color', sex='$item_sex', item_size='$tovar_size', real_item_size='$real_tovar_size', item_rost1='$tovar_rost1', item_rost2='$tovar_rost2', item_set='$item_set', buy_date='$buy_date', buy_price='$buy_price', buy_price_cur='$buy_currency', exch_to_byr='$exchange_rate', seller='$seller', item_info='$info', `status`='$tovar_status', item_place='$tovar_place', cr_ch_date='".time()."', user='".$_SESSION['user_fio']."' WHERE item_id='$item_id_upd'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
				
			}//end of update=update
			
			
			
			if ($update=='new') {
				
				if ($cat_select_new=='0') {//проверяем, есть ли такое наименование в категории, если есть - то берем id этой категории и не создаем новую, если нет - создаем новую категорию
										
					$query_cat_ch = "SELECT * FROM tovar_rent_cat WHERE rent_cat_name='$cat_input_new'";
					$result_cat_ch = $mysqli->query($query_cat_ch);
					if (!$result_cat_ch) {die('Сбой при доступе к базе данных: '.$query_cat_ch.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
					$cat_num=$result_cat_ch->num_rows;
					
					if ($cat_num>0) {
						$cat_ch=$result_cat_ch->fetch_assoc();
						$cat_id=$cat_ch['tovar_rent_cat_id'];
					}
					else {
						$query_newcat = "INSERT INTO tovar_rent_cat VALUES('', '$cat_input_new', '$cat_input_dog_new')";
						$result_newcat = $mysqli->query($query_newcat);
						if (!$result_newcat) {die('Сбой при доступе к базе данных: '.$query_newcat.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
							
						$cat_id=$mysqli->insert_id;
					}
						
				}
				else {
					$cat_id=$cat_select_new;
				}
				
				
				
				//пересчитываем инвентарный номер, в случае, если произошла смена категории, либо заведена новая категория
				if ($prev_cat_id!=$cat_id) {
					//item number within the cathegory calculation
				//	if ($cat_select_new!=0) {$cat_id=$cat_select_new;} // для расчета инв. номера. Если введена новая категория, то cat_id ее и берется, иначе - берем из нового селекта
				
					$query_item_n = "SELECT item_n FROM tovar_rent_items WHERE cat_id='$cat_id' ORDER BY item_n DESC LIMIT 0,1";
					$result_item_n = $mysqli->query($query_item_n);
					if (!$result_item_n) {die('Сбой при доступе к базе данных: '.$query_item_n.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
					$num1=$result_item_n->num_rows;
						
					if ($num1>0) {
						$item1=$result_item_n->fetch_assoc();
						$max_num1=$item1['item_n'];
					}
					else {
						$max_num1=0;
					}
						
					$query_item_n2 = "SELECT item_n FROM tovar_rent_items_arch WHERE cat_id='$cat_id' ORDER BY item_n DESC LIMIT 0,1";
					$result_item_n2 = $mysqli->query($query_item_n2);
					if (!$result_item_n2) {die('Сбой при доступе к базе данных: '.$query_item_n2.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
						
					$num2=$result_item_n2->num_rows;
				
					if ($num2>0) {
						$item2=$result_item_n2->fetch_assoc();
						$max_num2=$item2['item_n'];
					}
					else {
						$max_num2=0;
					}
				
				
					if ($max_num1>=$max_num2) {
						$max_n=$max_num1;
					}
					if ($max_num1<$max_num2) {
						$max_n=$max_num2;
					}
				
				
					$item_n=$max_n+1;
				
					//--- end of max_n calculation
				
					//item inv n calculation
					if ($cat_id<10) {$cat_n_pl=70;}
					elseif ($cat_id<100) {$cat_n_pl=7;}
					elseif ($cat_id<1000) {$cat_n_pl='';}
					$item_inv_n=$cat_n_pl.$cat_id.$item_n;
				
					//часть кода updatа товара, в случае, если меняется категория
					$inv_n_upd=" cat_id='$cat_id', item_inv_n='$item_inv_n', item_n='$item_n', ";
				
				}//end of cat changed if
				
			//создали категорию, рассчитали инв. номер, теперь создаем модель	

				//определяем наименование производителя
				if ($producer_select_new!='0') {$producer_name=$producer_select_new;}
				else {$producer_name=$producer_input_new;}
					
				//определяем наименование модели
				if ($model_select_new!='0') {$model_name=$model_select_new;}
				else {$model_name=$model_input_new;}
					
					
				// проверяем наличие аналогичной модели, если имеется таковая, то просто используем ее id, иначе - создаем новую модель
					
				$query_mod = "SELECT * FROM tovar_rent WHERE model='$model_name' AND tovar_rent_cat_id='$cat_id' AND producer='$producer_name' AND color='$color_new'";
				$result_mod = $mysqli->query($query_mod);
				if (!$result_mod) {die('Сбой при доступе к базе данных: '.$query_mod.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				$mod_num=$result_mod->num_rows;
					
				if ($mod_num>0) {
					$mod_ch=$result_mod->fetch_assoc();
					$model_id=$mod_ch['tovar_rent_id'];
				}
				else {
						
					//создаем модель на основании полученных данных и получаем ее id:
					$query_new_model = "INSERT INTO tovar_rent VALUES('', '$cat_id', '$producer_name', '$model_name', '$m_set_new', '$color_new', '$m_price_new', '$m_price_cur_new', '$lom_srok_new', '".time()."', '".$_SESSION['user_fio']."', '$model_addr_new', '$ph_addr_new', '', '', '', '')";
					$result_new_model = $mysqli->query($query_new_model);
					if (!$result_new_model) {die('Сбой при доступе к базе данных: '.$query_new_model.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
					$model_id=$mysqli->insert_id;
					//echo 'model_id='.$model_id.'<br />';
				}
					

				
				//обновляем информацию об айтеме
				
				$buy_date=strtotime($buy_date);
					
				$query_upd = "UPDATE tovar_rent_items SET producer='$producer_name', model_id='$model_id',".$inv_n_upd." item_color='$item_color', sex='$item_sex', item_size='$tovar_size', real_item_size='$real_tovar_size', item_rost1='$tovar_rost1', item_rost2='$tovar_rost2', item_set='$item_set', buy_date='$buy_date', buy_price='$buy_price', buy_price_cur='$buy_currency', exch_to_byr='$exchange_rate', seller='$seller', item_info='$info', `status`='$tovar_status', item_place='$tovar_place', cr_ch_date='".time()."', user='".$_SESSION['user_fio']."' WHERE item_id='$item_id_upd'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				
				
				
			}//end of update=new
			
			
			
			
				
		}//end of model_actio=new
		
		// для работы кнопки на редактирование товара от страницы тарифов: если инвентарный номер рассчитывался - то берем его рассчетное значение, иначе при обновлении товара берем его из передаваемой информации (х\з откуда она передается. 
		if (isset($item_inv_n)) {
			$inv_n_2=$item_inv_n;
		}
		else {
			$inv_n_2=$inv_n;
		}
		
		die('
			</head>
			<body>
				Товар успешно обновлен. <br />
				<form method="post" id="tovar_tarif" action="rent_tarifs.php">
					<input type="hidden" name="model_id" value="'.$model_id.'" />
					<input type="hidden" name="item_id" value="'.$item_id_upd.'" />
					<input type="hidden" name="item_inv_n2" value="'.$inv_n_2.'" />
					<input type="submit" name="action" value="редактировать тарифы (эта модель)" />
				</form>
				
				<form method="post" id="tovar_tarif" action="kr_baza_new.php">
					<input type="hidden" name="cat_id" value="'.($cat_id==0 ? $prev_cat_id : $cat_id).'" />
					<input type="submit" name="action" value="к товарам (эта категория)" />
				</form>
				
				<div class="top_menu">
					<a class="div_item" href="/bb/tovar_new.php">Новый товар</a>
				</div>
				<br /><br />
		
				<div class="top_menu">
					<a class="div_item" href="/bb/rent_tarifs.php">Работа с тарифами</a>
				</div>
				</body></html>
		');
		
		
	break;

}
}// end of main action if



?>

<script language="javascript">


function send_form_ch () {

	cat_chcc=cat_dogcc=prod_chcc=model_chcc=color_chcc=set_chcc=price_chcc=price_cur_chcc=lom_srokcc=buy_date_chcc=buy_price_chcc=buy_price_cur_chcc=exch_rate_chcc=seller_chcc=item_set_chcc=item_color_chcc='';

	valid = true;

	if(document.getElementById('model_action').value=="old" && document.getElementById('model_id').value=="") {
		alert ('Выберите характеристики модели до конца, или введите новую модель!');
		valid = false;
		}

	if(document.getElementById('tovar_rost1').value*1 > document.getElementById('tovar_rost2').value*1) {
		alert ('Рост должен быть заполнен в двух полях (ОТ и ДО). При этом, ОТ должно быть меньше либо равно ДО');
		valid = false;
		}

	if(document.getElementById('model_action').value=="new") {
		
		if (document.getElementById('cat_select_new').value=="0" && document.getElementById('cat_input_new').value=="")
		{cat_chcc="Категория товара, ";
	     valid = false;}

		if (document.getElementById('cat_input_dog_new').value=="")
		{cat_dogcc="Категория товара для договора, ";
	     valid = false;}

		if (document.getElementById('producer_select_new').value=="0" && document.getElementById('producer_input_new').value=="")
		{prod_chcc="фирма, ";
	     valid = false;}

		if (document.getElementById('model_select_new').value=="0" && document.getElementById('model_input_new').value=="")
		{model_chcc="Модель, ";
	     valid = false;}

		if (document.getElementById('color_new').value=="")
		{color_chcc="Цвет, ";
	     valid = false;}
		
		if (document.getElementById('m_set_new').value=="")
		{set_chcc="Комплектация, ";
	     valid = false;}

		if (document.getElementById('m_price_new').value=="")
		{price_chcc="Оценочная стоимость, ";
	     valid = false;}
		
		if (document.getElementById('m_price_cur_new').value=="")
		{price_cur_chcc="Валюта оценочной стоимости, ";
	     valid = false;}

		if (document.getElementById('lom_srok_new').value=="")
		{lom_srokcc="Срок службы, ";
	     valid = false;}

	}//end of model_action if


	
	if ((document.getElementById('model_action').value=="old" && document.getElementById('color_select_old').value=="multicolor" && document.getElementById('item_color').value=='') || (document.getElementById('model_action').value=="new" && document.getElementById('color_new').value=="multicolor" && document.getElementById('item_color').value==''))
	{item_color_chcc="Цвет модели с multicolor, ";
     valid = false;}
     
	if (document.getElementById('item_set').value=="")
	{item_set_chcc="Фактическая комплектация товара, ";
     valid = false;}
	
	if (document.getElementById('buy_date').value=="")
	{buy_date_chcc="Дата приобретения, ";
     valid = false;}

	if (document.getElementById('buy_price').value=="")
	{buy_price_chcc="Цена приобретения, ";
     valid = false;}

	if (document.getElementById('buy_price_cur').value=="")
	{buy_price_cur_chcc="Валюта цены приобретения, ";
     valid = false;}

	if (document.getElementById('exch_rate').value=="")
	{exch_rate_chcc="Курс пересчета, ";
     valid = false;}

	if (document.getElementById('seller').value=="")
	{seller_chcc="Продавец, ";
     valid = false;}


if (valid==false){
			alert ('Заполните все поля формы! В частности: ' + cat_chcc + cat_dogcc + prod_chcc + model_chcc + color_chcc + set_chcc + price_chcc + price_cur_chcc + lom_srokcc + item_color_chcc + item_set_chcc + buy_date_chcc + buy_price_chcc + buy_price_cur_chcc + exch_rate_chcc + seller_chcc);
		}

	return valid;

}//end of send_form_ch function




function show_new_model(){
	if (document.getElementById('model_action').value=="new") {return false;}
	
	document.getElementById('old_model_div').style.display="none";
	document.getElementById('new_model_div').style.display="inline-block";
	document.getElementById('old_model_id').value=document.getElementById('model_id').value;
	document.getElementById('model_id').value="";
	document.getElementById('model_action').value="new";
}

function show_old_model(){
	if (document.getElementById('model_action').value=="old") {return false;}
	
	document.getElementById('new_model_div').style.display="none";
	document.getElementById('old_model_div').style.display="inline-block";
	document.getElementById('model_id').value=document.getElementById('old_model_id').value;
	document.getElementById('old_model_id').value="";
	document.getElementById('model_action').value="old";
}

function new_model_radio() {
	document.getElementById('edit_model').style.display="none";
	document.getElementById('edit_model_1').style.display="inline-block";
	document.getElementById('worning').style.display="inline-block";
	
}

function edit_model_radio() {
	document.getElementById('edit_model').style.display="inline-block";
	document.getElementById('edit_model_1').style.display="none";
	document.getElementById('worning').style.display="none";
	
}


function cat_ch () {
	
	cat_id=document.getElementById('cat_select_old').value;
	par2='cat_producer';

	document.getElementById('producer_select_old').innerHTML='<option value="-">...ждите...</option>';

	if (cat_id==0) {
		document.getElementById('inv_n_cat').innerHTML='';
		document.getElementById('model_id').value='';
		document.getElementById('producer_select_old').innerHTML='<option value="0">----------</option>';
		document.getElementById('model_select_old').innerHTML='<option value="0">----------</option>';
		document.getElementById('color_select_old').innerHTML='<option value="0">----------</option>';	
		document.getElementById('m_set_old').value='';	
		document.getElementById('m_price_old').value='';
		document.getElementById('m_price_cur_old').value='';
		document.getElementById('lom_srok_old').value='';
		document.getElementById('model_addr_old').value='';
		document.getElementById('ph_addr_old').value='';
		document.getElementById('old_model_id_span').innerHTML='';
		
		return false;
	}
	
	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cat_ch_new.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'cat_id=' + encodeURIComponent(cat_id) + '&par2=' + encodeURIComponent(par2);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {

		     eval (xmlhttp.responseText);
			   }
	  		}
		}
		

	//обнуляем ранее выбранные последующие позиции при изменении категории - все на сброс
	document.getElementById('model_id').value='';
	document.getElementById('model_select_old').innerHTML='<option value="0">----------</option>';
	document.getElementById('color_select_old').innerHTML='<option value="0">----------</option>';	
	document.getElementById('m_set_old').value='';	
	document.getElementById('m_price_old').value='';
	document.getElementById('m_price_cur_old').value='';
	document.getElementById('lom_srok_old').value='';
	document.getElementById('model_addr_old').value='';
	document.getElementById('ph_addr_old').value='';
	

}//end of cat_ch



function prod_ch () {

	cat_id=document.getElementById('cat_select_old').value;
	producer=document.getElementById('producer_select_old').value;

	document.getElementById('model_select_old').innerHTML='<option value="-">...ждите...</option>';

	if (producer==0) {

		document.getElementById('model_id').value='';
		document.getElementById('model_select_old').innerHTML='<option value="0">----------</option>';
		document.getElementById('color_select_old').innerHTML='<option value="0">----------</option>';	
		document.getElementById('m_set_old').value='';	
		document.getElementById('m_price_old').value='';
		document.getElementById('m_price_cur_old').value='';
		document.getElementById('lom_srok_old').value='';
		document.getElementById('model_addr_old').value='';
		document.getElementById('ph_addr_old').value='';
		document.getElementById('old_model_id_span').innerHTML='';
		
		return false;
	}

	par2='producer';

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cat_ch_new.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'cat_id=' + encodeURIComponent(cat_id) + '&par2=' + encodeURIComponent(par2) + '&producer=' + encodeURIComponent(producer);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
	    	 
			eval (xmlhttp.responseText);
	           }
	  		}
		}

	//обнуляем ранее выбранные последующие позиции при изменении производителя - все на сброс
	document.getElementById('model_id').value='';	
	document.getElementById('color_select_old').innerHTML='<option value="0">----------</option>';	
	document.getElementById('m_set_old').value='';	
	document.getElementById('m_price_old').value='';
	document.getElementById('m_price_cur_old').value='';	
	document.getElementById('lom_srok_old').value='';
	document.getElementById('model_addr_old').value='';
	document.getElementById('ph_addr_old').value='';

}//end of prod_ch


function model_ch () {
	
	cat_id=document.getElementById('cat_select_old').value;
	producer=document.getElementById('producer_select_old').value;
	model_name=document.getElementById('model_select_old').value;

	document.getElementById('color_select_old').innerHTML='<option value="-">...ждите...</option>';


	if (model_name==0) {

		document.getElementById('model_id').value='';
		document.getElementById('color_select_old').innerHTML='<option value="0">----------</option>';	
		document.getElementById('m_set_old').value='';	
		document.getElementById('m_price_old').value='';	
		document.getElementById('m_price_cur_old').value='';
		document.getElementById('lom_srok_old').value='';
		document.getElementById('model_addr_old').value='';
		document.getElementById('ph_addr_old').value='';
		document.getElementById('old_model_id_span').innerHTML='';
		
		return false;
	}
		
	par2='model';
	
	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cat_ch_new.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'model_name=' + encodeURIComponent(model_name) + '&par2=' + encodeURIComponent(par2) + '&cat_id=' + encodeURIComponent(cat_id) + '&producer=' + encodeURIComponent(producer);
	
	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {

			  eval (xmlhttp.responseText);
			  
			}
	  	  }
	   }
	 

}//end model_ch


function color_ch () {
	
	cat_id=document.getElementById('cat_select_old').value;
	producer=document.getElementById('producer_select_old').value;
	model_name=document.getElementById('model_select_old').value;
	color_name=document.getElementById('color_select_old').value;

	if (color_name=='выберите цвет') {

		document.getElementById('model_id').value='';
		document.getElementById('m_set_old').value='';	
		document.getElementById('m_price_old').value='';	
		document.getElementById('m_price_cur_old').value='';
		document.getElementById('lom_srok_old').value='';
		document.getElementById('model_addr_old').value='';
		document.getElementById('ph_addr_old').value='';
		document.getElementById('old_model_id_span').innerHTML='';
		
		return false;
	}

	
	document.getElementById('m_set_old').value='...ждите...';
	
	par2='color';

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cat_ch_new.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'model_name=' + encodeURIComponent(model_name) + '&color_name=' + encodeURIComponent(color_name) + '&par2=' + encodeURIComponent(par2) + '&cat_id=' + encodeURIComponent(cat_id) + '&producer=' + encodeURIComponent(producer);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {

	    	 eval (xmlhttp.responseText);
	  
			}
	  	}
	}

		
}//end color_ch



function select_ch2 (sel, new_f) {

	if (document.getElementById(sel).value==0) {
		document.getElementById(new_f).disabled=false;
	}
	else {
	document.getElementById(new_f).disabled=true;
	document.getElementById(new_f).value='';
	}
	
}



function select_ch3 (sel, new_f) {

	if (document.getElementById(sel).value==0) {
		document.getElementById(new_f).disabled=false;
		document.getElementById('cat_input_dog_new').readOnly=false;
		document.getElementById('cat_input_dog_new').value='';
	}
	else {
	document.getElementById(new_f).disabled=true;
	document.getElementById('cat_input_dog_new').readOnly=true;
	document.getElementById(new_f).value='';

	document.getElementById('cat_input_dog_new').value='... ждите ...';

	var cat_id=document.getElementById(sel).value;
	par2='dog_name_select';

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cat_ch.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'cat_id=' + encodeURIComponent(cat_id) + '&par2=' + encodeURIComponent(par2);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
	     	   document.getElementById('cat_input_dog_new').value=xmlhttp.responseText;
			   }
	  		}
		}
	}//end of else
		
}//end of select_ch3


	

function getXmlHttp(){
	  var xmlhttp;
	  try {
	    xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
	  } catch (e) {
	    try {
	      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	    } catch (E) {
	      xmlhttp = false;
	    }
	  }
	  if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
	    xmlhttp = new XMLHttpRequest();
	  }
	  return xmlhttp;
}





function copy_set () {
	if (document.getElementById('model_action').value=='new') {
		document.getElementById('item_set').value=document.getElementById('m_set_new').value;
	}//if end
	else {
		document.getElementById('item_set').value=document.getElementById('m_set_old').value;
	}
}//function end


</script>

<?php 

//выбираем значения на селектов (новая модель)

//chose tovar cathegory
$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
$result_cats = $mysqli->query($query_cats);
if (!$result_cats) {die('Сбой при доступе к базе данных: '.$query_cats.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$cat_list='';
while ($cat_names = $result_cats->fetch_assoc()) {
	$cat_list.='<option value="'.$cat_names['tovar_rent_cat_id'].'" '.sel_d($cat_names['tovar_rent_cat_id'], $cat_id).' >'.good_print($cat_names['rent_cat_name']).'</option>';
}

//chose tovar producers
$query_prod = "SELECT DISTINCT producer FROM tovar_rent ORDER BY producer";
$result_prod = $mysqli->query($query_prod);
if (!$result_prod) {die('Сбой при доступе к базе данных: '.$query_prod.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

//chose model list
$query_model = "SELECT DISTINCT model FROM tovar_rent ORDER BY model";
$result_model = $mysqli->query($query_model);
if (!$result_model) {die('Сбой при доступе к базе данных: '.$query_model.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}



echo'
<form name="tovar" action="tovar_new.php" method="post">

<input type="hidden" name="model_action" id="model_action" value="'.($action=='редактировать' ? 'new' : 'old').'">
<input type="hidden" name="model_id" id="model_id" value="'.$model_id.'">
<input type="hidden" name="item_id_upd" id="item_id_upd" value="'.(isset($item_id) ? $item_id : '').'">
<input type="hidden" name="old_model_id" id="old_model_id" value="">
<input type="hidden" name="prev_cat_id" id="prev_cat_id" value="'.($action=='редактировать' ? $cat_def['tovar_rent_cat_id'] : '').'">

		
		
		
		
		
<a href="#" class="link_ch_old" onclick="show_old_model(); return false;">выбрать существующую модель</a>	<a href="#" class="link_ch_new" onclick="show_new_model(); return false;">'.($action=='редактировать' ? 'обновить действующую / ' : '').'внести новую модель</a>
<br />
		
		
<div id="old_model_div" class="old_div'.($action=='редактировать' ? '_r' : '').'">

<table border="1" cellspacing="0">
	<tr>
		<td>Категория товара:</td>
		<td>
			<select name="cat_select_old" id="cat_select_old" onchange="cat_ch();">
				<option value="0">выберите категорию</option>
				'.$cat_list.'
			</select>
			'.($action=='редактировать' ? '<strong>!!!При смене категории будет пересчитан инвентарный номер!!!</strong>' : '').'
			'.($action=='редактировать' ? '<script language="javascript"> document.getElementById(\'cat_select_old\').value="0"; </script>' : '').'	
		</td>
	</tr>
	<tr>
		<td>Фирма:</td>
		<td>
			<select name="producer_select_old" id="producer_select_old" onchange="prod_ch();">
    			<option value="0">----------</option>    	
    		</select>
	  		
	  		<textarea id="produceer_sel_temp" readonly="readonly" style="display:none"></textarea> <!--- это чтобы кавычки двойные правильно сравнивались -->
		</td>
	</tr>

	<tr>
		<td>Модель(<span id="old_model_id_span"></span>):</td>
		<td>
			<select name="model_select_old" id="model_select_old" onchange="model_ch();">
	    		<option value="0">------------</option>
	    	</select>
		</td>
	</tr>	
	
	<tr>
		<td>Цвет:</td>
		<td>
			<select name="color_select_old" id="color_select_old" onchange="color_ch();">
    			<option value="0">------------</option>
    		</select>
		</td>
	</tr>	
	
	<tr>
		<td>Комплектация модели (стандарт):</td>
		<td><input type="text" name="m_set_old" size="70" id="m_set_old" readonly="readonly" /></td>
	</tr>	
	
	<tr>
		<td>Оценочная стоимость:</td>
		<td>
			<input type="text" name="m_price_old" size="10" id="m_price_old" readonly="readonly"/>
			<input type="text" name="m_price_cur_old" size="5" id="m_price_cur_old" readonly="readonly"/>
		</td>
	</tr>

	<tr>
		<td>Прогноз срока службы (непрервыное использование):</td>
		<td>
			<input type="text" name="lom_srok_old" size="5" id="lom_srok_old" readonly="readonly" /> года (лет).
		</td>
	</tr>

	<tr style="display:none;">
		<td>Инфо для сайта</td>
		<td>Адрес страницы товара:<input type="text" name="model_addr_old" size="70" id="model_addr_old" value="" readonly="readonly" /><br />
			Адрес фото:<input type="text" name="ph_addr_old" size="70" id="ph_addr_old" value="" readonly="readonly" />	
				</td>			
	</tr>
							
			
</table>
			
</div>



		
		
		

<div id="new_model_div" class="new_div'.($action=='редактировать' ? '_r' : '').'"> '.

($action=='редактировать' ? ('<input name="update" type="radio" value="update" checked="checked" onclick="edit_model_radio();"> - обновить модель (!для всех товаров этой модели), <input name="update" type="radio" value="new" onclick="new_model_radio();"> - создать новую модель.') : '')

.'
		

<table border="1" cellspacing="0">
	<tr>
		<td>Категория товара:</td>
		<td>
			<span id="edit_model_1" '.($action=='редактировать' ? 'style="display:none;"' : '').'>
			<select name="cat_select_new" id="cat_select_new" onchange="select_ch3(\'cat_select_new\', \'cat_input_new\');" style="width:220px;" >
				<option value="0">ввести новую категорию</option>
				'.$cat_list.'
			</select>
			<input type="text" name="cat_input_new" size="30" id="cat_input_new" '.($action=='редактировать' ? 'disabled="disabled"' : '').' /> 
			</span>
			'.($action=='редактировать' ? '<span id="edit_model">Изменить название категории (для всех): <input type="text" name="cat_edit" size="30" id="cat_edit" value="'.good_print($cat_def['rent_cat_name']).'" />  <input type="hidden" name="cat_edit_old" size="30" id="cat_edit_old" value="'.good_print($cat_def['rent_cat_name']).'" /></span>' : '').'
					
					и для договора (ед.ч.):
			<input type="text" name="cat_input_dog_new" size="30" id="cat_input_dog_new" value="'.good_print($cat_def['dog_name']).'"/> <br />
					'.($action=='редактировать' ? '<span id="worning" style="display:none;"><strong>!!!При смене категории будет пересчитан инвентарный номер!!!</strong></span>' : '').'						
		</td>
	</tr>
	<tr>
		<td>Фирма:</td>
		<td>
			<select name="producer_select_new" id="producer_select_new" onchange="select_ch2(\'producer_select_new\', \'producer_input_new\');" style="width:220px;" >
			    	<option value="0">ввести нового производителя</option>';
			    	while ($prod_names = $result_prod->fetch_assoc()) {
						echo'
					<option value="'.good_print($prod_names['producer']).'" '.sel_d($model_def['producer'], $prod_names['producer']).'>'.good_print($prod_names['producer']).'</option>
					';
			    	}
			    echo '</select>
			<input type="text" name="producer_input_new" size="30" id="producer_input_new" '.($action=='редактировать' ? 'disabled="disabled"' : '').'/>
		</td>
	</tr>

	<tr>
		<td>Модель:</td>
		<td>
			<select name="model_select_new" id="model_select_new" onchange="select_ch2(\'model_select_new\', \'model_input_new\');" style="width:220px;" >
	    		<option value="0">ввести новую модель</option>';
	    		
	    		while ($model_list=$result_model->fetch_assoc()) {
				echo'<option value="'.good_print($model_list['model']).'" '.sel_d($model_def['model'], $model_list['model']).'>'.good_print($model_list['model']).'</option>';
			}
	   
		echo '	    		
	    	</select>
	    	<input type="text" name="model_input_new" size="30" id="model_input_new" '.($action=='редактировать' ? 'disabled="disabled"' : '').' />
		</td>
	</tr>	
	
	<tr>
		<td>Цвет:</td>
		<td> <input type="text" name="color_new" size="30" id="color_new" value="'.good_print($model_def['color']).'" /> нет цвета - ставим "0", <input type="button" value="multicolor" onclick="document.getElementById(\'color_new\').value=\'multicolor\'" /></td>
	</tr>	
	
	<tr>
		<td>Комплектация модели (стандарт):</td>
		<td><input type="text" name="m_set_new" size="70" id="m_set_new" value="'.good_print($model_def['set']).'" /></td>
	</tr>	
	
	<tr>
		<td>Оценочная стоимость:</td>
		<td>
			<input type="number" step="any" min="0" name="m_price_new" size="70" id="m_price_new" value="'.$model_def['agr_price'].'" />
			<select name="m_price_cur_new" id="m_price_cur_new">
		    	<option value="USD" '.sel_d($model_def['agr_price_cur'], 'USD').' >USD</option>
		    	<option value="EUR" '.sel_d($model_def['agr_price_cur'], 'EUR').' >EUR</option>
		    	<option value="TBYR" '.sel_d($model_def['agr_price_cur'], 'TBYR').' >тыс.бел.руб.</option>
		    </select>
		</td>
	</tr>
				
	<tr>
		<td>Прогноз срока службы (непрервыное использование):</td>
		<td>
			<input type="number" step="any" min="0" name="lom_srok_new" size="5" id="lom_srok_new" value="'.$model_def['lom_srok'].'" /> года (лет).
		</td>
	</tr>			

	<tr style="display:none;">
		<td>Инфо для сайта</td>
		<td>Адрес страницы товара:<input type="text" name="model_addr_new" size="70" id="model_addr_new" value="'.good_print($model_def['model_addr']).'" /><br />
			Адрес фото:<input type="text" name="ph_addr_new" size="70" id="ph_addr_new" value="'.good_print($model_def['ph_addr']).'" />	
				</td>			
	</tr>

</table>			
			
			
			
			
</div>

<br />
Инвентарный номер товара: <span id="inv_n_cat"></span><input type="text" size="10" name="inv_n" readonly="readonly" value="'.$item_def['item_inv_n'].'" /><br />
		

Цвет (для варианта "multicolor"): <input type="text" name="item_color" id="item_color" size="65" value="'.good_print($item_def['item_color']).'" /><br />
Пол:<select name="item_sex" id="item_sex">
		<option value="0" '.sel_d($item_def['sex'], '0').'>не определено</option>
		<option value="m" '.sel_d($item_def['sex'], 'm').'>для мальчиков</option>
		<option value="f" '.sel_d($item_def['sex'], 'f').'>для девочек</option>
    	<option value="u" '.sel_d($item_def['sex'], 'u').'>унисекс</option>
	</select>
				
Для одежды - размер: <input type="text" name="tovar_size" id="tovar_size" size="10" value="'.good_print($item_def['item_size']).'" />, 
рост: от <input type="number" step="any" min="0" name="tovar_rost1" id="tovar_rost1" size="5" value="'.good_print($item_def['item_rost1']).'" /> до <input type="number" step="any" min="0" name="tovar_rost2" id="tovar_rost2" size="5" value="'.good_print($item_def['item_rost2']).'" /><br />				
реальный размер для карнавальных костюмов:<input type="text" name="real_tovar_size" id="real_tovar_size" size="10" value="'.good_print($item_def['real_item_size']).'" />,<br />
Фактическая комплектация товара: <input type="text" name="item_set" id="item_set" size="65" value="'.good_print($item_def['item_set']).'" /> <input type="button" value="копировать стандарт" id="copy" onclick="copy_set(); return false;" /><br />

Дата приобретения:<input type="date" name="buy_date" id="buy_date" size="10" value="'.date("Y-m-d", $item_def['buy_date']).'" /><br />
Цена приобретения:<input type="number" step="any" min="0" name="buy_price" id="buy_price" size="10" value="'.$item_def['buy_price'].'" />
Валюта приобретения:
<select name="buy_currency" id="buy_price_cur">
	<option value="USD" '.sel_d($item_def['buy_price_cur'], 'USD').'>доллары США</option>
	<option value="TBYR" '.sel_d($item_def['buy_price_cur'], 'TBYR').'>тыс.бел.руб.</option>
    <option value="EUR" '.sel_d($item_def['buy_price_cur'], 'EUR').'>евро</option>
   	<option value="RUB" '.sel_d($item_def['buy_price_cur'], 'RUB').'>росс.руб.</option>
</select>

Курс пересчета в USD:<input type="text" name="exchange_rate" id="exch_rate" size="10" value="'.$item_def['exch_to_byr'].'" /><br />
Продавец:<input type="text" name="seller" size="70" id="seller" value="'.good_print($item_def['seller']).'" /><br />

Статус товара:	<select name="tovar_status" id="tovar_status"">
			  		<option value="to_rent" '.sel_d($item_def['status'], 'to_rent').'>доступен для сдачи</option>
					<option value="bron" '.sel_d($item_def['status'], 'bron').'>бронь</option>
					<option value="t_bron" '.sel_d($item_def['status'], 't_bron').'>временная бронь (интернет)</option>
					<option value="cleaning" '.sel_d($item_def['status'], 'cleaning').'>на стирке</option>
					<option value="repair" '.sel_d($item_def['status'], 'repair').'>требуется ремонт</option>
					<option value="not_to_rent" '.sel_d($item_def['status'], 'not_to_rent').'>недоступен для сдачи</option>
					<option value="rented_out" '.sel_d($item_def['status'], 'rented_out').'>товар сдан/на руках</option>
					<option value="rented_out" '.sel_d($item_def['status'], 'for_sale').'>на продаже</option>
				</select>
<br />

Местонахождение товара:	
	    	 	<select name="tovar_place" id="tovar_place"">
	    	 		<option value="0" '.sel_d($item_def['item_place'], '0').'>Не определено</option>
			  		<option value="1" '.sel_d($item_def['item_place'], '1').'>Офис №1</option>
					<option value="2" '.sel_d($item_def['item_place'], '2').'>Офис №2</option>
				</select>
	<br />
			
Дополнительная информация о товаре:<br />
<textarea name="info" rows="4" cols="70" id="item_info">'.good_print($item_def['item_info']).'</textarea><br />
'.($action=='редактировать' ? '<input type="submit" name="action" value="обновить" onclick="return send_form_ch();"/>' : '<input type="submit" name="action" value="сохранить" onclick="return send_form_ch();"/>').'
</form>

';




echo '</body>';






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