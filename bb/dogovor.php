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
	</head>

<body>
');
}

//-----------proverka paroley



if (!isset($_POST['action']) || $_POST['action']!='распечатать договор') {
echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>База. Договор</title> 
</head>

<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a> 
	<a class="div_item" href="/bb/deals.php">Отложено для курьера</a>
	<a class="div_item" href="/bb/alldeals.php">Все сделки</a>
</div>

';



//Проверка входящей информации
//		echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//		foreach ($_POST as $key => $value) {
//			echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//		}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";
}

// создание нулевых поисковых значени по умолчанию - для устранения ошибки в поиске
		$s_family=NULL;
		$s_name=NULL; 
		$s_otch=NULL;
		$s_str=NULL;
		$s_dom=NULL;
		$s_kv=NULL;
		$s_pas_n=NULL;
		$s_client_id=NULL;
		
		
		$d_disabled='';
		$d_disabled_d='';
		$edit_all=0;
		$deliv_disabled='disabled="disabled"';
		$del_ch='';
		
		$client_update=0;
		$deal_update=0;

		$deal_id='';
		$client_id='';
		
		$for_kuryer='';
		$dl_def['coll_amount']='';
		$dl_def['coll_cur']='';
		$dl_def['item_inv_n']='';
		
if (isset($_POST['action'])) {
		foreach ($_POST as $key => $value) {
					$$key = get_post($key);
				}

	
//новый вариант реагирования на отложено для курьера				
if ($action=='сохранить для курьера') {
		$action='сохранить';
		$for_kuryer='yes';
		$family==NULL ? $family='_______________' : $family=$family;
		$name==NULL ? $name='___________' : $name=$name;
		$otch==NULL ? $otch='________________' : $otch=$otch;
		$str==NULL ? $str='________________________________________' : $str=$str;
		$reg_str==NULL ? $reg_str='_______________________________' : $reg_str=$reg_str;
		$pas_n==NULL ? $pas_n='___________' : $pas_n=$pas_n;
		$pas_who==NULL ? $pas_who='________________' : $pas_who=$pas_who;
	}
				
	switch ($action) {

	case 'отменить выдачу':
		
		$query_dl_upd = "UPDATE deals SET deal_status='отмена', deal_status_info='$deal_status_info' WHERE deal_id='$deal_id'";
		   	if (!mysql_query($query_dl_upd, $db_server)) {
			echo "Сбой при вставке данных: '$query_dl_upd' <br />".mysql_error()."<br /><br />";
			}
		
		//логирование действий пользователя	
		$query_log = "INSERT INTO users_log VALUES('', '".$_SESSION['user_id']."', '$client_id', 'отмена выдачи', 'deals', '$deal_id', '".time()."', '".mysql_real_escape_string($query_dl_upd)."')";
			if (!mysql_query($query_log, $db_server)) {
			echo "Сбой при вставке данных в раздел СОКОЛИНЫЙ ГЛАЗ - сообщите Диме!: '$query_log' <br />".mysql_error()."<br /><br />";
			}
		//---------------------
				
		die('
		Сделка отменена.<br />
		<a href="dogovor.php" class="div_item">Перейти к оформлению новой сделки</a>
		</body>
		</html>
		');
		
		break;
		
		
		case 'оплачено':
		
		$query_dl_upd = "UPDATE deals SET deal_status='оплачено', payment_type='$payment_type', paid='$paid' WHERE deal_id='$deal_id'";
		   	if (!mysql_query($query_dl_upd, $db_server)) {
			echo "Сбой при вставке данных: '$query_dl_upd' <br />".mysql_error()."<br /><br />";
			}
		
			
			//логирование действий пользователя	
		$query_log = "INSERT INTO users_log VALUES('', '".$_SESSION['user_id']."', '$client_id', 'статус оплачено', 'deals', '$deal_id', '".time()."', '".mysql_real_escape_string($query_dl_upd)."')";
			if (!mysql_query($query_log, $db_server)) {
			echo "Сбой при вставке данных в раздел СОКОЛИНЫЙ ГЛАЗ - сообщите Диме!: '$query_log' <br />".mysql_error()."<br /><br />";
			}
			//------------	
			
			
		$total_to_pay=phone_to_n($total_to_pay);
			
		$final='';
		if ($total_to_pay>$paid) {
			$final='<strong><font color="red"> Внимание! Долг клиента составляет'.($total_to_pay-$paid).' тыс.бел. руб.</font></strong><br />';
		}
		
		if ($total_to_pay<$paid) {
			$final='<strong><font color="red"> Внимание! Клиент переплатил'.($paid-$total_to_pay).' тыс.бел. руб.</font></strong><br />';
		}

		
		die('
		Оплата внесена.<br />
		К оплате: '.number_format($total_to_pay, 0, ',', ' ').' тыс. бел. руб.<br />
		Оплачено: '.number_format($paid, 0, ',', ' ').' тыс.бел. руб.<br />
		'.$final.'
		<a href="dogovor.php" class="div_item">Перейти к оформлению новой сделки</a>
		</body>
		</html>
		');
		
		break;
		

case 'отложить для курьера':
		
		$query_dl_upd = "UPDATE deals SET deal_status='для курьера' WHERE deal_id='$deal_id'";
		   	if (!mysql_query($query_dl_upd, $db_server)) {
			echo "Сбой при вставке данных: '$query_dl_upd' <br />".mysql_error()."<br /><br />";
			}
		
			
			//логирование действий пользователя	
		$query_log = "INSERT INTO users_log VALUES('', '".$_SESSION['user_id']."', '$client_id', 'статус для курьера', 'deals', '$deal_id', '".time()."', '".mysql_real_escape_string($query_dl_upd)."')";
			if (!mysql_query($query_log, $db_server)) {
			echo "Сбой при вставке данных в раздел СОКОЛИНЫЙ ГЛАЗ - сообщите Диме!: '$query_log' <br />".mysql_error()."<br /><br />";
			}
			//------------	
			
			
			
		die('
		Сделка отложена для курьера.<br />
		Посмотреть все сделки для курьера - по ссылке "Отложено для курьера" вверху.<br />
		<br />
		<a href="dogovor.php" class="div_item">Перейти к оформлению новой сделки</a>
		</body>
		</html>
		');
		
		break;
		
		
		

		
		
	case 'распечатать договор':
		
		//формируем печатную форму
			//запрос актуальной информации о клиенте
		$query_cl_def = "SELECT * FROM clients WHERE client_id='$client_id'";
		$result_cl_def = mysql_query($query_cl_def);
		if (!$result_cl_def) die("Сбой при доступе к базе данных: '$query_cl_def'".mysql_error());
		$cl_def=mysql_fetch_array($result_cl_def);

			//запрос актуальной информации о сделке
		$query_dl_def = "SELECT * FROM deals WHERE deal_id='$deal_id'";
		$result_dl_def = mysql_query($query_dl_def);
		if (!$result_dl_def) die("Сбой при доступе к базе данных: '$query_dl_def'".mysql_error());
		$dl_def=mysql_fetch_array($result_dl_def);
		
		//запрос информации о товаре по инв. номеру (если он есть)
		if ($dl_def['item_inv_n']>0) {
		
		$query_item_def = "SELECT * FROM tovar_rent_items WHERE item_inv_n='".$dl_def['item_inv_n']."'";
		$result_item_def = mysql_query($query_item_def);
		if (!$result_item_def) die("Сбой при доступе к базе данных: '$query_item_def'".mysql_error());
		$item_def=mysql_fetch_array($result_item_def);
		
		$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$item_def['model_id']."'";
		$result_model_def = mysql_query($query_model_def);
		if (!$result_model_def) die("Сбой при доступе к базе данных: '$query_model_def'".mysql_error());
		$model_def=mysql_fetch_array($result_model_def);
		
		}
		else {$item_def='';}
		
		
		
			//логирование действий пользователя	
		$query_log = "INSERT INTO users_log VALUES('', '".$_SESSION['user_id']."', '$client_id', 'печать договора', '', '$deal_id', '".time()."', '')";
			if (!mysql_query($query_log, $db_server)) {
			echo "Сбой при вставке данных в раздел СОКОЛИНЫЙ ГЛАЗ - сообщите Диме!: '$query_log' <br />".mysql_error()."<br /><br />";
			}
		
		
		
	//подготовка некоторых значений		
  	$fio=encode_for_rtf($cl_def['family'].' '.$cl_def['name'].' '.$cl_def['otch']);
	//$fio2=encode_for_rtf($cl_def['family'].' '.mb_substr($cl_def['name'], 0, 1, 'UTF-8').'.'.mb_substr($cl_def['otch'], 0, 1, 'UTF-8').'.');
  	$address=encode_for_rtf($cl_def['city'].', '.$cl_def['str'].' '.$cl_def['dom'].'-'.$cl_def['kv']);
  	$reg_address=encode_for_rtf($cl_def['reg_city'].', '.$cl_def['reg_str'].' '.$cl_def['reg_dom'].'-'.$cl_def['reg_kv']);
	$coll_cur_pr=money_print($dl_def['coll_amount'], $dl_def['coll_cur']);
  	
  	
	
  if ($dog_form=='standart'){
  			
  	
  	$rtf = new RTF_Template('nd.rtf');
	$rtf->parse('fio', $fio);
	$rtf->parse('fio2', $fio);
	$rtf->parse('address', $address);
	$rtf->parse('reg_address', $reg_address);
	$rtf->parse('pas_n', encode_for_rtf($cl_def['pas_n']));
	$rtf->parse('pas_date', encode_for_rtf($cl_def['pas_date']==0 ? '_________' : date("d.m.Y", $cl_def['pas_date'])));
	$rtf->parse('pas_who', encode_for_rtf($cl_def['pas_who']));
	$rtf->parse('phone_1', encode_for_rtf(phone_print($cl_def['phone_1'])));
	$rtf->parse('phone_2', encode_for_rtf(phone_print($cl_def['phone_2'])));
	
	
	$rtf->parse('tovar', encode_for_rtf($dl_def['tovar']));
	$rtf->parse('step', encode_for_rtf($dl_def['step']));
	$rtf->parse('tarif', encode_for_rtf(number_format($dl_def['rent_tarif'], 0, ',', ' ')));
	$rtf->parse('tovar_currency', encode_for_rtf($dl_def['tovar_currency']));
	$rtf->parse('tovar_price', encode_for_rtf(number_format($dl_def['tovar_price'], 0, ',', ' ')));
	$rtf->parse('start_date', encode_for_rtf(date("d.m.Y", $dl_def['start_date'])));
	$rtf->parse('rto_pay', encode_for_rtf(number_format($dl_def['r_to_pay'], 0, ',', ' ')));
	$rtf->parse('return_date', encode_for_rtf(date("d.m.Y", $dl_def['return_date'])));
	
	$rtf->out_h('nd1.rtf');
	$rtf->out_f('/1/nd1.rtf');
	echo $rtf->out(); //viewport
	
  }
  
  elseif ($dog_form=='karn'){
  	$rtf = new RTF_Template('ndk.rtf');
	$rtf->parse('fio', $fio);
	$rtf->parse('fio2', $fio);
	$rtf->parse('address', $address);
	$rtf->parse('reg_address', $reg_address);
	$rtf->parse('pas_n', encode_for_rtf($cl_def['pas_n']));
	$rtf->parse('pas_date', encode_for_rtf($cl_def['pas_date']==0 ? '_________' : date("d.m.Y", $cl_def['pas_date'])));
	$rtf->parse('pas_who', encode_for_rtf($cl_def['pas_who']));
	$rtf->parse('phone_1', encode_for_rtf(phone_print($cl_def['phone_1'])));
	$rtf->parse('phone_2', encode_for_rtf(phone_print($cl_def['phone_2'])));
	
	$rtf->parse('coll', encode_for_rtf(money_print($dl_def['coll_amount'], $dl_def['coll_cur'])));
	$rtf->parse('coll_a', encode_for_rtf(number_format($dl_def['coll_amount'], 0, ',', ' ')));
	$rtf->parse('its', encode_for_rtf($item_def['item_set']));
	$rtf->parse('itsize', encode_for_rtf($item_def['item_size']));
	$rtf->parse('tenor', encode_for_rtf($dl_def['rent_tenor']));
	
	$item_name=$model_def['model'].', цвет: '.$model_def['color'];
		$rtf->parse('itemname', encode_for_rtf($item_name));
	$rtf->parse('agr_price', encode_for_rtf($model_def['agr_price']));
	$rtf->parse('agrcur', encode_for_rtf(money_print($model_def['agr_price'], $model_def['agr_price_cur'])));
	
	//$rtf->parse('tovar', encode_for_rtf($dl_def['tovar']));
	//$rtf->parse('step', encode_for_rtf($dl_def['step']));
	//$rtf->parse('tarif', encode_for_rtf(number_format($dl_def['rent_tarif'], 0, ',', ' ')));
	//$rtf->parse('tovar_currency', encode_for_rtf($dl_def['tovar_currency']));
	//$rtf->parse('tovar_price', encode_for_rtf(number_format($dl_def['tovar_price'], 0, ',', ' ')));
	$rtf->parse('start_date', encode_for_rtf(date("d.m.Y", $dl_def['start_date'])));
	$rtf->parse('rto_pay', encode_for_rtf(number_format($dl_def['r_to_pay'], 0, ',', ' ')));
	$rtf->parse('rto_pay_cur', encode_for_rtf(money_print($dl_def['r_to_pay'], 'TBYR')));
	$rtf->parse('return_date', encode_for_rtf(date("d.m.Y", $dl_def['return_date'])));
	
	$rtf->out_h('ndk1.rtf');
	$rtf->out_f('/1/ndk1.rtf');
	echo $rtf->out(); //viewport
  	
  }		
		
		break;
	
		
		
		
		
		
	case 'найти':  //ищем клиентов
		 
		$s_family==NULL ? $ss_family='%' : $ss_family='%'.$s_family.'%';
		$s_name==NULL ? $ss_name='' : $ss_name=' AND name LIKE \'%'.$s_name.'%\''; 
		$s_otch==NULL ? $ss_otch='' : $ss_otch=' AND otch LIKE \'%'.$s_otch.'%\'';
		$s_str==NULL ? $ss_str='' : $ss_str=' AND str LIKE \'%'.$s_str.'%\'';
		$s_dom==NULL ? $ss_dom='' : $ss_dom=' AND dom LIKE \'%'.$s_dom.'%\'';
		$s_kv==NULL ? $ss_kv='' : $ss_kv=' AND kv LIKE \'%'.$s_kv.'%\'';
		$s_pas_n==NULL ? $ss_pas_n='' : $ss_pas_n=' AND pas_n LIKE \'%'.$s_pas_n.'%\'';
		$s_client_id==NULL ? $ss_client_id='' : $ss_client_id=' AND client_id LIKE \'%'.$s_client_id.'%\'';
		
		//$query_cl = "SELECT * FROM clients";
		$query_cl = "SELECT * FROM clients WHERE family LIKE '$ss_family'".$ss_name.$ss_otch.$ss_str.$ss_dom.$ss_kv.$ss_pas_n.$ss_client_id;
		//echo $query_cl;
		$result_cl = mysql_query($query_cl);
		if (!$result_cl) die("Сбой при доступе к базе данных: '$query_cl'".mysql_error());
		$rows_cl = mysql_num_rows($result_cl);
		
		$client_search=1;
		
		break;
		
		
	case 'сохранить':  //ввод клиента в БД
				
		if (isset($client_id)&&($client_id==NULL)) {
			
			$phone_1=phone_to_n($phone_1);
			$phone_2=phone_to_n($phone_2);
			
			$family=mb_convert_case($family, MB_CASE_TITLE, 'UTF-8');
			$name=mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
			$otch=mb_convert_case($otch, MB_CASE_TITLE, 'UTF-8');
			$city=mb_convert_case($city, MB_CASE_TITLE, 'UTF-8');
			$str=mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
			$reg_city=mb_convert_case($reg_city, MB_CASE_TITLE, 'UTF-8');
			$reg_str=mb_convert_case($reg_str, MB_CASE_TITLE, 'UTF-8');
			
			
				                               //две кавычки под поле id    вставляем нового клиента
		$pas_date=strtotime($pas_date); //приводим в формат юникс дату календаря гггг-мм-дд
		$query = "INSERT INTO clients VALUES('', '$family', '$name', '$otch', '$city', '$str', '$dom', '$kv', '$pas_n', '$pas_date', '$pas_who', '$reg_city', '$reg_str', '$reg_dom', '$reg_kv', '$phone_1', '$phone_2', '$info', '', ".time().")";
			if (!mysql_query($query, $db_server)) {
			echo "Сбой при вставке данных: '$query' <br />".mysql_error()."<br /><br />";
											}
		$client_id=mysql_insert_id();
		$_POST['client_id']=$client_id;

				//логирование действий пользователя	
		$query_log = "INSERT INTO users_log VALUES('', '".$_SESSION['user_id']."', '$client_id', 'ввод нового клиента', 'clients', '', '".time()."', '".mysql_real_escape_string($query)."')";
			if (!mysql_query($query_log, $db_server)) {
			echo "Сбой при вставке данных в раздел СОКОЛИНЫЙ ГЛАЗ - сообщите Диме!: '$query_log' <br />".mysql_error()."<br /><br />";
			}
		
		
		}
		
		
		
		
		if ($client_id!=NULL && $client_update==1) {
			
			$phone_1=phone_to_n($phone_1);
			$phone_2=phone_to_n($phone_2);
			
			$family=mb_convert_case($family, MB_CASE_TITLE, 'UTF-8');
			$name=mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
			$otch=mb_convert_case($otch, MB_CASE_TITLE, 'UTF-8');
			$city=mb_convert_case($city, MB_CASE_TITLE, 'UTF-8');
			$str=mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
			$reg_city=mb_convert_case($reg_city, MB_CASE_TITLE, 'UTF-8');
			$reg_str=mb_convert_case($reg_str, MB_CASE_TITLE, 'UTF-8');
						
			$query_cl_upd = "UPDATE clients SET family='$family', name='$name', otch='$otch', city='$city', str='$str', dom='$dom', kv='$kv', pas_n='$pas_n', pas_date='$pas_date', pas_who='$pas_who', reg_city='$reg_city', reg_str='$reg_str', reg_dom='$reg_dom', reg_kv='$reg_kv', phone_1='$phone_1', phone_2='$phone_2', info='$info' WHERE client_id='$client_id'";
		   	if (!mysql_query($query_cl_upd, $db_server)) {
			echo "Сбой при вставке данных: '$query_cl_upd' <br />".mysql_error()."<br /><br />";
			}
		
				//логирование действий пользователя	
		$query_log = "INSERT INTO users_log VALUES('', '".$_SESSION['user_id']."', '$client_id', 'изменение информации клиента', 'clients', '', '".time()."', '".mysql_real_escape_string($query_cl_upd)."')";
			if (!mysql_query($query_log, $db_server)) {
			echo "Сбой при вставке данных в раздел СОКОЛИНЫЙ ГЛАЗ - сообщите Диме!: '$query_log' <br />".mysql_error()."<br /><br />";
			}
					
		
		}
		

		
		if (isset($deal_id)&&($deal_id==NULL)) {
						
				                               //две кавычки под поле id    вставляем нового клиента
		$start_date=strtotime($start_date); //приводим в формат юникс дату календаря гггг-мм-дд
		$return_date=strtotime($return_date); //приводим в формат юникс дату календаря гггг-мм-дд
		isset($delivery) ? $delivery=1 : $delivery=0;
		isset($delivery_price) ? $delivery_price=$delivery_price : $delivery_price=0;
		
		$query = "INSERT INTO deals VALUES('', '$client_id', '$item_inv_n', '$tovar', '$tovar_price', '$tovar_currency', '$start_date', '$rent_tarif', '$step', '$rent_tenor', '$delivery', '$delivery_price', '$r_to_pay', '$return_date', '$deal_info', '', '', 'new', '', '$coll_amount', '$coll_cur', '".time()."')";
			if (!mysql_query($query, $db_server)) {
			echo "Сбой при вставке данных: '$query' <br />".mysql_error()."<br /><br />";
											}
		$deal_id=mysql_insert_id();
		$_POST['deal_id']=$deal_id;
		
				//логирование действий пользователя	
		$query_log = "INSERT INTO users_log VALUES('', '".$_SESSION['user_id']."', '$client_id', 'ввод новой сделки', 'deals', '$deal_id', '".time()."', '".mysql_real_escape_string($query)."')";
			if (!mysql_query($query_log, $db_server)) {
			echo "Сбой при вставке данных в раздел СОКОЛИНЫЙ ГЛАЗ - сообщите Диме!: '$query_log' <br />".mysql_error()."<br /><br />";
			}
		
					
		}
		
		if ($deal_id!=NULL && $deal_update==1) {
			
			$start_date=strtotime($start_date); //приводим в формат юникс дату календаря гггг-мм-дд
			$return_date=strtotime($return_date); //приводим в формат юникс дату календаря гггг-мм-дд
			isset($delivery) ? $delivery=1 : $delivery=0;
			isset($delivery_price) ? $delivery_price=$delivery_price : $delivery_price=0;
						
			$query_dl_upd = "UPDATE deals SET tovar='$tovar', tovar_price='$tovar_price', tovar_currency='$tovar_currency', start_date='$start_date', rent_tarif='$rent_tarif', step='$step', rent_tenor='$rent_tenor', delivery='$delivery', delivery_price='$delivery_price', r_to_pay='$r_to_pay', return_date='$return_date', deal_info='$deal_info', coll_amount='$coll_amount', coll_cur='$coll_cur', item_inv_n='$item_inv_n' WHERE deal_id='$deal_id'";
		   	if (!mysql_query($query_dl_upd, $db_server)) {
			echo "Сбой при вставке данных: '$query_dl_upd' <br />".mysql_error()."<br /><br />";
			}
			
				//логирование действий пользователя	
		$query_log = "INSERT INTO users_log VALUES('', '".$_SESSION['user_id']."', '$client_id', 'изменение данных сделки', 'deals', '$deal_id', '".time()."', '".mysql_real_escape_string($query_dl_upd)."')";
			if (!mysql_query($query_log, $db_server)) {
			echo "Сбой при вставке данных в раздел СОКОЛИНЫЙ ГЛАЗ - сообщите Диме!: '$query_log' <br />".mysql_error()."<br /><br />";
			}
			
		}
		
		
		
		//добавляем реакцию на новую кнопку откладывания для курьера
	if ($for_kuryer=='yes') {
		$query_dl_upd = "UPDATE deals SET deal_status='для курьера' WHERE deal_id='$deal_id'";
		   	if (!mysql_query($query_dl_upd, $db_server)) {
			echo "Сбой при вставке данных: '$query_dl_upd' <br />".mysql_error()."<br /><br />";
			}
		
			
			//логирование действий пользователя	
		$query_log = "INSERT INTO users_log VALUES('', '".$_SESSION['user_id']."', '$client_id', 'статус для курьера', 'deals', '$deal_id', '".time()."', '".mysql_real_escape_string($query_dl_upd)."')";
			if (!mysql_query($query_log, $db_server)) {
			echo "Сбой при вставке данных в раздел СОКОЛИНЫЙ ГЛАЗ - сообщите Диме!: '$query_log' <br />".mysql_error()."<br /><br />";
			}
			//------------	
			
			
			
		die('
		Сделка отложена для курьера.<br />
		Посмотреть все сделки для курьера - по ссылке "Отложено для курьера" вверху.<br />
		<br />
		<a href="dogovor.php" class="div_item">Перейти к оформлению новой сделки</a>
		</body>
		</html>
		');
	}//end of for_kuryer if	
		
		
		
		
	break;	
	
	
	case 'редактировать':
		$edit_all=1;
		$client_update=1;
		$deal_update=1;	
		
	break;
	
	
	
	}//end switch
} //end if for "action"

?>

<script language="javascript">

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

function apply_tarif (tarif_id) {
	document.getElementById('rent_tarif').value=document.getElementById('rent_per_step_'+tarif_id).value;

	if (value=document.getElementById('step_'+tarif_id).value=='day') {document.getElementById('step').value='день';}
	if (value=document.getElementById('step_'+tarif_id).value=='week') {document.getElementById('step').value='неделя';}
	if (value=document.getElementById('step_'+tarif_id).value=='month') {document.getElementById('step').value='месяц';}

	document.getElementById('rent_tenor').value=document.getElementById('kol_vo_'+tarif_id).value;
}


function chose_item() {

	item_id=document.getElementById('item_inv_n').value;
	
	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/item_ch.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'item_inv_n=' + encodeURIComponent(item_id);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
			 
	    	 eval (xmlhttp.responseText);
	    				
			if (ch_result=='no') {
				alert ('Товар с таким инвентарным номером не найден!');
				}

			else {
				document.getElementById('tovar').value=model_name;
				document.getElementById('tovar_price').value=dog_price;

			}


	    	 
			   }
	  		}
		}
}




function copy_addr() {
	document.getElementById('reg_str').value=document.getElementById('str').value; 
	document.getElementById('reg_dom').value=document.getElementById('dom').value;
	document.getElementById('reg_kv').value=document.getElementById('kv').value;
	document.getElementById('reg_city').value=document.getElementById('city').value;
	}


function calculate() {

	r_step=document.getElementById('step').value;
	rent_date = new Date(document.getElementById('start_date').value);
	r_tenor=document.getElementById('rent_tenor').value;
	
	switch (r_step) {
	  case 'день':
		rent_date.setDate(rent_date.getDate()+(r_tenor)*1);
		document.getElementById('return_date').value=formatDate(rent_date);
		document.getElementById('r_to_pay').value=document.getElementById('rent_tarif').value*r_tenor;
		document.getElementById('total_to_pay').value=document.getElementById('r_to_pay').value*1+document.getElementById('delivery_price').value*1;
	    break
	    

	  case 'неделя':
		rent_date.setDate(rent_date.getDate()+(r_tenor)*7);
		document.getElementById('return_date').value=formatDate(rent_date);
		document.getElementById('r_to_pay').value=document.getElementById('rent_tarif').value*r_tenor;
		document.getElementById('total_to_pay').value=document.getElementById('r_to_pay').value*1+document.getElementById('delivery_price').value*1;
	    break
	    
	  case 'месяц':
		rent_date.setMonth(rent_date.getMonth()+(r_tenor)*1);
		document.getElementById('return_date').value=formatDate(rent_date);
		document.getElementById('r_to_pay').value=document.getElementById('rent_tarif').value*r_tenor;
		document.getElementById('total_to_pay').value=document.getElementById('r_to_pay').value*1+document.getElementById('delivery_price').value*1;
	    break
	}



	r_tarif=document.getElementById('rent_tarif').value;
	//alert(r_tarif);

	

	
	
}

function formatDate(date) {

	  var dd = date.getDate()
	  if ( dd < 10 ) dd = '0' + dd;

	  var mm = date.getMonth()+1
	  if ( mm < 10 ) mm = '0' + mm;

	  var yyyy = date.getFullYear();
	
	  return yyyy+'-'+mm+'-'+dd;
	}

function disable_0 (id) {
	if (document.getElementById(id).disabled==true) {
		document.getElementById(id).disabled=false;
	}
	else {
	document.getElementById(id).disabled=true;
	document.getElementById(id).value=0;
	}
}


function disable (id) {
	if (document.getElementById(id).disabled==true) {
		document.getElementById(id).disabled=false;
	}
	else {
	document.getElementById(id).disabled=true;
	}
}

function hide_client () {

	disable('family');
	disable('name');
	disable('otch');
	disable('str');
	disable('dom');
	disable('kv');
	disable('city');
	disable('pas_n');
	disable('pas_date');
	disable('pas_who');
	disable('reg_str');
	disable('reg_dom');
	disable('reg_kv');
	disable('reg_city');
	disable('phone_1');
	disable('phone_2');
	disable('info');
	disable('address_copy');

	if (document.getElementById('client_update').value==0)
	{document.getElementById('client_update').value=1;}
	else {document.getElementById('client_update').value=0;}

	if (document.getElementById('cl_edit_button').value=='редактировать информацию клиента')
	{document.getElementById('cl_edit_button').value='отменить редактирование информации клиента';
	}
	else
	{document.getElementById('cl_edit_button').value='редактировать информацию клиента';}
	
	
	//client_update 
	
}

// проверка заполнения клиента
function form_check () 
{
	valid = true;

	family = name = otch = city = str = dom = kv = pas_n = pas_who = pas_date = reg_city = reg_str = reg_dom = reg_kv = phone_1 = phone_2 = tovar = tovar_price = start_date = rent_tarif = rent_tenor = r_to_pay = return_date = "";

	// проверка клиента
	if (document.getElementById('family').value=="")
	{family="Фамилия, ";
     valid = false;}

	if (document.getElementById('name').value=="")
	{name="Имя, ";
     valid = false;}

	if (document.getElementById('otch').value=="")
	{otch="Отчество, ";
     valid = false;}

	if (document.getElementById('city').value=="")
	{city="Адрес (город), ";
     valid = false;}

	if (document.getElementById('str').value=="")
	{str="Адрес (улица), ";
     valid = false;}

	
	if (document.getElementById('dom').value=="")
	{dom="Адрес (дом), ";
     valid = false;}
	
	if (document.getElementById('kv').value=="")
	{kv="Адрес (квартира), ";
     valid = false;}
	
	if (document.getElementById('pas_n').value=="")
	{pas_n="№ паспорта, ";
     valid = false;}

	if (document.getElementById('pas_date').value=="")
	{pas_date="Дата выдачи паспорта, ";
     valid = false;}

	if (document.getElementById('pas_who').value=="")
	{pas_who="орган, выдавший паспорт, ";
     valid = false;}

	if (document.getElementById('reg_city').value=="")
	{reg_city="Прописка (город), ";
     valid = false;}

	if (document.getElementById('reg_str').value=="")
	{reg_str="Прописка (улица), ";
     valid = false;}

	if (document.getElementById('reg_dom').value=="")
	{reg_dom="Прописка (дом), ";
     valid = false;}
	
	if (document.getElementById('reg_kv').value=="")
	{reg_kv="Прописка (квартира), ";
     valid = false;}

	if (document.getElementById('phone_1').value=="")
	{phone_1="Телефон №1, ";
     valid = false;}

	if (document.getElementById('phone_2').value=="")
	{phone_2="Телефон №2, ";
     valid = false;}


	//проверка сдеки
	if (document.getElementById('tovar').value=="")
	{tovar="Наименование товара, ";
     valid = false;}

	if (document.getElementById('tovar_price').value=="")
	{tovar_price="Оценочная стоимость, ";
     valid = false;}
    
	if (document.getElementById('start_date').value=="")
	{start_date="Дата выдачи, ";
     valid = false;}

	if (document.getElementById('rent_tarif').value=="" || document.getElementById('rent_tarif').value=="0")
	{rent_tarif="Тариф, ";
     valid = false;}

	if (document.getElementById('rent_tenor').value=="")
	{rent_tenor="количество по тарифу, ";
     valid = false;}

	if (document.getElementById('r_to_pay').value=="" || document.getElementById('r_to_pay').value=="0")
	{r_to_pay="Стоимость аренды, ";
     valid = false;}

	if (document.getElementById('return_date').value=="")
	{return_date="Дата возврата, ";
     valid = false;}
	
	
if (valid==false){
	alert ('Заполните все поля формы! В частности: ' + family + name + otch + city + str + dom + kv + pas_n + pas_date + pas_who + reg_city + reg_str + reg_dom + reg_kv + phone_1 + phone_2 + tovar + tovar_price + start_date + rent_tarif + rent_tenor + r_to_pay + return_date);
}

	
	return valid
	
	
}

</script>

<?php


	if (isset($_POST['client_id'])) {
		
		$client_id=get_post('client_id');
		$edit_all==1 ? $d_disabled='' : $d_disabled='disabled="disabled"';
		
		$query_cl_def = "SELECT * FROM clients WHERE client_id='$client_id'";
		$result_cl_def = mysql_query($query_cl_def);
		if (!$result_cl_def) die("Сбой при доступе к базе данных: '$query_cl_def'".mysql_error());
		
		$rows_cl_def = mysql_num_rows($result_cl_def);
		
		$cl_def=mysql_fetch_array($result_cl_def);
	}
	else {  // создание переменных для устранения ошибок в заполнении формы клиента
		$client_id='';
		$cl_def['family']='';
		$cl_def['name']='';
		$cl_def['otch']='';
		$cl_def['str']='';
		$cl_def['dom']='';
		$cl_def['kv']='';
		$cl_def['city']='Минск';
		$cl_def['pas_n']='';
		$cl_def['pas_date']='';
		$cl_def['pas_who']='';
		$cl_def['reg_str']='';
		$cl_def['reg_dom']='';
		$cl_def['reg_kv']='';
		$cl_def['reg_city']='';
		$cl_def['phone_1']='';
		$cl_def['phone_2']='';
		$cl_def['info']='';
	}


	
	
	
	if (isset($_POST['deal_id'])) {
		
		$deal_id=get_post('deal_id');
		$edit_all==1 ? $d_disabled_d='' : $d_disabled_d='disabled="disabled"';
		
		$query_dl_def = "SELECT * FROM deals WHERE deal_id='$deal_id'";
		$result_dl_def = mysql_query($query_dl_def);
		if (!$result_dl_def) die("Сбой при доступе к базе данных: '$query_dl_def'".mysql_error());
		$dl_def=mysql_fetch_array($result_dl_def);
		
		//подготовка правильных значений для заполнения полей сделки
		$st_date=date("Y-m-d", $dl_def['start_date']);
		$ret_date=date("Y-m-d", $dl_def['return_date']);
		$tov_r_step='<option selected="selected" value="'.$dl_def['step'].'">'.$dl_def['step'].'</option>';
		
		if ($dl_def['delivery']==1) {
			$del_ch='checked="checked"';
			$d_disabled_d=='disabled="disabled"' ? $deliv_disabled='disabled="disabled"' : $deliv_disabled='';
		}
		 
		
		
			
	}
	else {  // создание переменных для устранения ошибок в заполнении формы клиента
		$tov_pr_cur='';
		$st_date=date("Y-m-d");
		$ret_date='';
		$tov_r_step='';
		$del_ch='';
		
		$dl_def['deal_id']='';
		$dl_def['tovar']='';
		$dl_def['tovar_price']='';
		$dl_def['rent_tarif']='0';
		$dl_def['rent_tenor']='';
		$dl_def['delivery_price']='';
		$dl_def['r_to_pay']='0';
		$dl_def['deal_info']='';
	}	
	
	
	
if (!$client_id>0) {
	
echo '
<div class="find_cl">
<span class="div_header"> Найти клиента: </span>
<form name="poisk" method="post" action="dogovor.php">
	№ клиента: <input type="text" name="s_client_id" size="10" value="'.$s_client_id.'" /> 
	№ паспорта: <input type="text" name="s_pas_n" size="10" value="'.$s_pas_n.'" /> <br /> 
	Фамилия:<input type="text" name="s_family" size="20" value="'.$s_family.'" />  
	+ Имя: <input type="text" name="s_name" size="10" value="'.$s_name.'" /> 
	+ Отчество:<input type="text" name="s_otch" size="10" value="'.$s_otch.'"/>  
	+ улица:<input type="text" name="s_str" size="30" value="'.$s_str.'" />  
	+ дом:<input type="text" name="s_dom" value="'.$s_dom.'" /> 
	+ квартира:<input type="text" name="s_kv" value="'.$s_kv.'" />
	<input type="submit" name="action" value="найти" />
</form>
</div>
';
}
// выводим результаты поиска клиентов (при наличии)

if (isset($client_search)) {

echo '
<table border="1" cellspacing="0">
<tr>
	<th>id</th>
	<th>ФИО</th>
	<th>Адрес</th>
	<th>Прописка</th>
	<th>Паспорт</th>
	<th>Телефоны</th>
	<th>Инфо</th>
	<th>Действия</th>
</tr>';

while ($client_list=mysql_fetch_array($result_cl)) {
	echo '
	<form name="client_s_'.$client_list['client_id'].'" method="post" action="dogovor.php" >
	<tr>
		<td>'.$client_list['client_id'].' <input type="hidden" name="client_id" value="'.$client_list['client_id'].'" /> </td>
    	<td>'.$client_list['family'].' '.$client_list['name'].' '.$client_list['otch'].'</td>
    	<td>'.$client_list['str'].' '.$client_list['dom'].'-'.$client_list['kv'].','.$client_list['city'].'</td>
    	<td>'.$client_list['reg_str'].' '.$client_list['reg_dom'].'-'.$client_list['reg_kv'].','.$client_list['reg_city'].'</td>
    	<td>'.$client_list['pas_n'].', выдан '.date("d-m-Y", $client_list['pas_date']).' '.$client_list['pas_who'].'</td>
    	<td>'.phone_print($client_list['phone_1']).'<br />'.phone_print($client_list['phone_2']).'</td>
    	<td>'.$client_list['info'].'</td>
    	<td><input type="submit" value="выбрать" /></td>
	</tr>
	</form>
	';
}
echo '</table>';

if ($rows_cl==0) echo '<font color="red"><h3>Извините, по Вашему запросу ничего не найдено</h3></font>';


}

// основаная форма по клиенту
echo '
<div class="find_cl">
';

if ($cl_def['pas_date']!=NULL) $cl_def['pas_date']=date("Y-m-d", $cl_def['pas_date']);

if (isset($client_id)&&($client_id!=NULL)&&!$deal_id>0) {echo'<span class="div_header">Информация о клиенте (№'.$client_id.'): <input type="button" value="редактировать информацию клиента" id="cl_edit_button" onclick="hide_client(); return false;" /></span>';}
elseif (isset($client_id)&&($client_id!=NULL)) {echo '<span class="div_header">Информация о клиенте (№'.$client_id.'):</span>';}
else {echo '<span class="div_header">Ввести нового клиента:</span>';}


echo '
<form name="order" method="post" action="dogovor.php" >
<input type="hidden" name="client_id" id="client_id" value="'.$client_id.'" />
<input type="hidden" name="client_update" id="client_update" value="'.$client_update.'" />
Фамилия:<input type="text" name="family" id="family" size="30" '.$d_disabled.' value="'.$cl_def['family'].'" />
Имя: <input type="text" name="name" id="name" size="30" '.$d_disabled.' value="'.$cl_def['name'].'" />
Отчество:<input type="text" name="otch" id="otch" size="30" '.$d_disabled.' value="'.$cl_def['otch'].'" /><br />

Адрес: улица:<input type="text" name="str" id="str" size="30" '.$d_disabled.' value="'.$cl_def['str'].'" />, дом:<input type="text" name="dom" id="dom" size="3" '.$d_disabled.' value="'.$cl_def['dom'].'" />, квартира:<input type="text" name="kv" id="kv" size="3" '.$d_disabled.' value="'.$cl_def['kv'].'" />, город:<input type="text" name="city" id="city" size="10" '.$d_disabled.' value="'.$cl_def['city'].'" /> <input type="button" value="копировать адрес в прописку" id="address_copy" '.$d_disabled.' onclick="copy_addr(); return false;" /><br />
Прописка: улица:<input type="text" name="reg_str" id="reg_str" '.$d_disabled.' size="30" value="'.$cl_def['reg_str'].'" />, дома:<input type="text" name="reg_dom" id="reg_dom" size="3" '.$d_disabled.' value="'.$cl_def['reg_dom'].'" />, квартира:<input type="text" name="reg_kv" id="reg_kv" size="3" '.$d_disabled.' value="'.$cl_def['reg_kv'].'" />, город:<input type="text" name="reg_city" id="reg_city" size="10" '.$d_disabled.' value="'.$cl_def['reg_city'].'" /> <br />

№ паспорта:<input type="text" name="pas_n" id="pas_n" size="30" '.$d_disabled.' value="'.$cl_def['pas_n'].'" />
выдан (дата):<input type="date" name="pas_date" id="pas_date" '.$d_disabled.' value="'.$cl_def['pas_date'].'" />
выдан (кем):<input type="text" name="pas_who" id="pas_who" size="30" '.$d_disabled.' value="'.$cl_def['pas_who'].'" /><br />

Телефон 1 (+375):<input type="text" name="phone_1" id="phone_1" size="30" '.$d_disabled.' value="'.phone_print($cl_def['phone_1']).'" />
Телефон 2 (+375):<input type="text" name="phone_2" id="phone_2" size="30" '.$d_disabled.' value="'.phone_print($cl_def['phone_2']).'" /> <i>Если 2-й телефон отсутствует - ставьте 0 (нуль)!!!</i><br />
Дополнительная информация:<br/> <textarea cols="100" rows="3" name="info" id="info" '.good_print($d_disabled).'>'.$cl_def['info'].'</textarea><br />
</div>


<div class="find_cl">
<span class="div_header">Сделка'.($dl_def['deal_id']!='' ? (' (№'.$dl_def['deal_id'].')') : '').':</span></br>
<input type="hidden" name="deal_id" id="deal_id" value="'.$dl_def['deal_id'].'" />
<input type="hidden" name="deal_update" id="deal_update" value="'.$deal_update.'" />
Инвентарный номер: <input type="text" name="item_inv_n" id="item_inv_n" value="'.$dl_def['item_inv_n'].'" /> <input type="button" value="выбрать товар" onclick="chose_item(); return false;" /> <br />
Наименование товара:<input type="text" name="tovar" id="tovar" size="100" '.$d_disabled_d.' value="'.good_print($dl_def['tovar']).'" /><br />
Оценочная стоимость:<input type="text" name="tovar_price" id="tovar_price" size="5" '.$d_disabled_d.' value="'.$dl_def['tovar_price'].'" />
<input type="hidden" name="tovar_currency" value="долларов США" /> долларов США<br />

Дата выдачи:<input type="date" name="start_date" id="start_date" '.$d_disabled_d.' value="'.$st_date.'"/><br />

<div id="tarif_div"></div>

Тариф:<input type="text" name="rent_tarif" id="rent_tarif" '.$d_disabled_d.' value="'.$dl_def['rent_tarif'].'" />тыс.бел. руб.

<select name="step" id="step" '.$d_disabled_d.'>
	'.$tov_r_step.'
	<option value="день">в день</option>
	<option selected="selected" value="неделя">в неделю</option>
	<option value="месяц">в месяц</option>
</select>
количество (д/н/м - по тарифу):<input type="text" name="rent_tenor" id="rent_tenor" '.$d_disabled_d.' value="'.$dl_def['rent_tenor'].'" size="10"/><br/>
Доставка:<input '.$d_disabled_d.' type="checkbox" '.$del_ch.' name="delivery" onchange="disable_0(\'delivery_price\')">
Стоимость доставки:<input type="text" '.$deliv_disabled.' name="delivery_price" id="delivery_price" size="10" value="'.$dl_def['delivery_price'].'" />тыс.бел. руб. <br /><input type="button" value="рассчитать" id="calc_button" onclick="calculate(); return false;" /><br />

Стоимость аренды:<input type="text" name="r_to_pay" id="r_to_pay" size="10" '.$d_disabled_d.' value="'.$dl_def['r_to_pay'].'" />, к оплате с учетом доставки:<input type="text" name="total_to_pay" id="total_to_pay" size="10" readonly="readonly" value="'.number_format(($dl_def['r_to_pay']+$dl_def['delivery_price']), 0, ',', ' ').'" />тыс.бел. руб.<br />

Дата возврата:<input type="date" name="return_date" id="return_date" '.$d_disabled_d.' value="'.$ret_date.'"/>

&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>Для карнавальных костюмов:</b>
залог: <input type="text" name="coll_amount" value="'.($dl_def['coll_amount']>0 ? $dl_def['coll_amount'] : '300').'" size="5" />
<select name="coll_cur" id="coll_cur_id">
		    	<option value="TBYR" '.($dl_def['coll_cur']=='TBYR' ? 'selected="selected"' : '').'>тыс.бел.руб.</option>
                <option value="USD" '.($dl_def['coll_cur']=='USD' ? 'selected="selected"' : '').'>USD</option>
		    	<option value="EUR" '.($dl_def['coll_cur']=='EUR' ? 'selected="selected"' : '').'>EUR</option>
		    	<option value="RUB" '.($dl_def['coll_cur']=='RUB' ? 'selected="selected"' : '').'>рос. руб.</option>
</select>
<br />

Дополнительная информация по сделке:<br/> <textarea cols="100" rows="3" name="deal_info" id="deal_info" '.good_print($d_disabled_d).'>'.$dl_def['deal_info'].'</textarea><br />
</div>

';

if ($client_id==NULL || $deal_id==NULL || $edit_all==1) {
echo '<input type="submit" name="action" value="сохранить" onclick="return form_check( );" /> <input type="submit" name="action" value="сохранить для курьера" /> <input type="button" value="Отмена" onclick="location.href=\'/bb/dogovor.php\';" />
';
}

if ($client_id>0 && $deal_id>0 && $edit_all==0) {
	$cat_num=mb_substr($dl_def['item_inv_n'], 0,3);
	$dl_def['delivery']==1 ? $deliv_button='<input type="submit" name="action" value="отложить для курьера" />' : $deliv_button='';
	echo '
	<label><input type="radio" name="dog_form" value="standart" '.($cat_num!='702' ? 'checked="checked"' : '').' />станд.</label><label><input type="radio" name="dog_form" value="karn" '.($cat_num=='702' ? 'checked="checked"' : '').' />карн.</label><br />
	<input type="submit" name="action" value="распечатать договор" />
	<input type="submit" name="action" value="редактировать" />
	<input type="submit" name="action" value="отменить выдачу" /><input type="text" name="deal_status_info" size="30" value="почему" /> (причина отмены) 
	'.$deliv_button.'
	<br />
	<input type="text" name="paid" id="paid" size="30" value="'.($dl_def['r_to_pay']+$dl_def['delivery_price']).'" /> тыс.бел.руб.
	<select name="payment_type">
		<option value="касса_1">нал в кассу 1</option>
		<option value="касса_2">нал в кассу 2</option>
		<option value="карта">пластиковая карта</option>
	</select>
	<input type="submit" name="action" value="оплачено" /> !!!с учетом стоимости доставки
			
	';
}

echo '
</form>

</body>
</html>';



function money_print ($amount, $cur) {

		if ($amount<20 && $amount!=10) {$cut=$amount;}
		else {$cut=substr($amount, -1, 1);}	
		
switch ($cur) {

	case 'TBYR': 
		if ($cut=='1') {return 'тысяча рублей';} 
		elseif ($cut=='0') {return 'тысяч рублей';}
		elseif ($cut>1 && $cut <5) {return 'тысячи рублей';}
		elseif ($cut>4 && $cut <20) {return 'тысяч рублей';}
	
	break;	
		
		
	case 'USD': 
		if ($cut=='1') {return 'доллар США';} 
		elseif ($cut=='0') {return 'долларов США';}
		elseif ($cut>1 && $cut <5) {return 'доллара США';}
		elseif ($cut>4 && $cut <20) {return 'долларов США';}
	
	break;
	
	
	case 'EUR': 
		return 'евро';
		
	break;

	
	case 'RUB': 
		if ($cut=='1') {return 'росс. рубль';} 
		elseif ($cut=='0') {return 'рос. рублей';}
		elseif ($cut>1 && $cut <5) {return 'росс. рубля';}
		elseif ($cut>4 && $cut <20) {return 'росс. рублей';}
	
	break;	

}//end of switch
}//end of function




function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}



// класс для печати договора
/**
 * Class RTF template
 * 2011 Igor Artasevych, Andrey Yaroshenko
 *
*/
class RTF_Template{
/*****************************************************************************/
/* variables */
    private $content;
/* functions */
    /**
     * RTF_Template::__construct()
     *
     * @param mixed $filename
     * @return
     */
    public function __construct($filename){
        $this->content = file_get_contents($filename);
    }//construct
    /*************************************************************************/
    /**
     * RTF_Template::parse()
     *
     * @param mixed $block_name
     * @param mixed $value
     * @param string $start_tag
     * @param string $end_tag
     * @return
     */
    public function parse($block_name, $value, $start_tag = '\{', $end_tag = '\}'){
       $this->content = str_ireplace($start_tag.$block_name.$end_tag, $value, $this->content);
    }//
    /*************************************************************************/
    /**
     * RTF_Template::out_f()
     *
     * @param mixed $filename
     * @return
     */
    public function out_f($filename){
        file_put_contents($filename, $this->content);
    }//
    /*************************************************************************/
    /**
     * RTF_Template::out_h()
     *
     * @param mixed $filename
     * @return
     */
    public function out_h($filename){
        ob_clean();
        header("Content-type: plaintext/rtf");
        header("Content-Disposition: attachment; filename=$filename");
        echo $this->content;
    }//
    /*************************************************************************/
    /**
     * RTF_Template::out()
     *
     * @param mixed $filename
     * @return
     */
    public function out(){
        return $this->content;
    }//
}//class


	function encode_for_rtf ($str) {
    $str = bin2hex(iconv('utf-8','windows-1251',$str));
    $str = preg_replace("/([a-zA-Z0-9]{2})/","\'$1",$str);
    
    return $str;
  	}

  	function phone_to_n ($ph) {
  		$ph=preg_replace("|[^0-9]|i", "", $ph);
  		return $ph;
  	}
  	
  	function phone_print ($ph) {
  		if ($ph=='') {return '';}
  		
  		$dl=strlen($ph);
  		
  		if ($dl<7) {return $ph;}
  		  		
  		$dl>7 ? $dl_to=$dl-7 : $dl_to=0;
  		$ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
  		return $ph_out;  		
  		
  	}

function good_print($var) {
	$var=htmlspecialchars(stripslashes($var));
	return $var;	
}
  	
  	
?>