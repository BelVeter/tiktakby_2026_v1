<?php

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных

$q_type='';


foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}



if ($q_type=='karnaval') {
	

	$no_list = array(2, 3, 5, 6, 8, 11, 13, 14, 15, 29, 30, 31, 36, 37, 49, 66, 67, 68, 69, 71, 72, 75, 77, 89, 90); //здесь перечисляем id моделей, !!!Которые нельзя бронировать до 27 декабря
	
	if (in_array($model_id, $no_list)) {
		$no_bron='no_bron';
	
	}
	else {
		$no_bron='yes_bron';
	}
		
	
	
	
	
	
echo '

     <form action="/includes/zakaz.php" name="zakaz" method="post" class="zakaz_form" id="zakaz_form_'.$model_id.'">
	    Ваше имя*: <input type="text" name="name" id="name_'.$model_id.'" value="" /><br />
		Телефон*: 
	    		<select name="operator" id="operator_'.$model_id.'">
							<option value="Velcom">Velcom</option>
		               		<option value="МТС">МТС</option>
		                    <option value="Life">Life:)</option>
		                    <option value="город">городской</option>
				</select>
	    		<input type="text" name="phone" id="phone_'.$model_id.'" value="" /><br />	
					
		    <br />
		
		Дата утренника*: <input type="text" name="date_d" id="date_d_'.$model_id.'" size="3" />
						<select name="date_m" id="date_m_'.$model_id.'">
							<option value="04">Апреля</option>
					                <option value="05">Мая</option>
							<option value="06">Июня</option>
						</select><br />
		                
		Время утренника: <input type="text" name="k_time" id="k_time_'.$model_id.'" size="5" /><br />
		Рост ребенка*: <input type="text" name="rost" id="rost_'.$model_id.'" size="5" />см.<br />
		Комплекция ребенка:
				<select name="kompl" id="kompl_'.$model_id.'">
							<option value="Худенький">Худенький</option>
		               		<option value="Средний" selected="selected">Средний</option>
		                    <option value="Полненький">Полненький</option>
		        </select>
						
		<br />
		Дополнительная информация: <br />
		<textarea name="info" id="info_'.$model_id.'" cols="30" rows="5"></textarea><br />
							
			
        <input type="button" name="action" value="отправить заявку" onclick="send_form(\''.$model_id.'\', \''.$no_bron.'\')" />
     	<input type="button" class="zakaz_otm" id="zakaz_otm_'.$model_id.'" value="отмена" onclick="cans_form(\''.$model_id.'\')" />
     					
				
	</form>';
}
elseif ($q_type=='form_send') {
	
	
	//Проверка входящей информации
	//		echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
	//		foreach ($_POST as $key => $value) {
	//			echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
	//		}
	//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";
	
	
	
	
	$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$model_id."'";
	$result_model_def = mysql_query($query_model_def);
	if (!$result_model_def) die("Сбой при доступе к базе данных: '$query_model_def'".mysql_error());
	$model_def=mysql_fetch_array($result_model_def);
	
	$date_m=='01' ? $year='2014' : $year='2013'; 
	
	
	
	//вставка данных в базу
	$query_zakaz = "INSERT INTO karnaval_zakaz VALUES('', '$model_id', '$k_name', '$phone', '$operator', '$date_d', '$date_m', '$year', '$k_time', '$rost', '$kompl', '$info', '".time()."', '', 'new', '', '')";
	if (!mysql_query($query_zakaz, $db_server)) {
		echo "Сбой при вставке данных - сообщите администратору!: '$query_zakaz' <br />".mysql_error()."<br /><br />";
	}
	$zakaz_id=mysql_insert_id();
	
	
	
	
	$text='Номер брони: <strong>'.$zakaz_id.'A</strong> <br />Костюм: '.$model_def['model'].', <br /> Имя:'.$k_name.',<br /> Телефон:'.$phone.' - '.$operator.',<br /> Дата: '.$date_d.'-'.$date_m.'-'.$year.', <br /> Время: '.$k_time.', <br /> Рост: '.$rost.' см., <br /> Комплекция: '.$kompl.', <br /> Дополнительная информация: '.$info.'<br />';
	
	$result=mail("info@tiktak.by", "Заказ карнавального костюма! - ".$model_def['model'], "$text", "Content-type: text/html; charset=UTF-8 \r\n");
	
		
	if ($result) {
		echo '<div class="blag">Благодарим Вас!<br /> Ваша заявка принята к рассмотрению. Номер Вашей заявки: <strong>'.$zakaz_id.'A</strong> <br />Наши операторы свяжутся с Вами в течение суток для подтверждения или отказа в брони. <br /><b>ВНИМАНИЕ!</b> Примерить костюм можно<br />
ПН.-ПЯТ. с <b>10.00-19.30</b><br />
СУББОТА с <b>11.00-15.00</b><br /> по адресу: <b>пр-т Машерова, 18</b>/вход с проспекта<br /> вход с проспекта под вывеску "TikTak ПРОКАТ" (рядом з-д Горизонт, "Белая вежа", военный госпиталь) <br /> 
<input type="button" class="zakaz_otm" id="zakaz_otm_'.$model_id.'" value="OK" onclick="cans_form(\''.$model_id.'\')" />
		</div>';
	}
	else {
		echo '<div class="blag">По техническим причинам Ваша заявка не была отправлена. <br /> Приносим свои извинения.<br /> Свяжитесь с нашими операторами по телефону.<br /> 
<input type="button" class="zakaz_otm" id="zakaz_otm_'.$model_id.'" value="OK" onclick="cans_form(\''.$model_id.'\')" />
		
			</div>';
	}
	
	
/*	echo '<div class="blag">Благодарим Вас!<br /> Ваша заявка принята к рассмотрению. <br />Наши операторы свяжутся с Вами в ближайшее время для подтверждения брони. <br /> 
<input type="button" class="zakaz_otm" id="zakaz_otm_'.$model_id.'" value="OK" onclick="cans_form(\''.$model_id.'\')" />
		
			</div>';*/
	

}

elseif ($q_type=='pas') {
	echo '
			
<form name="order" method="post" action="dogovor.php" class="pas_form">
<font color="red"> Форма заполняется клиентом только после подтверждения брони сотрудником проката!</font><br /><br /> 			
			
ВВЕДИТЕ НОМЕР ВАШЕЙ БРОНИ: <input type="text" name="info" id="info_'.$model_id.'" size="4" value="" maxlength="4"><br /><br />
			
<span class="div_header">Введите данные арендатора для договора проката (лица, которе будет забирать костюм):</span> <br /><br />
Фамилия:<input type="text" name="family" id="family_'.$model_id.'" size="20" value="">
Имя: <input type="text" name="name" id="name_'.$model_id.'" size="15" value="">
Отчество:<input type="text" name="otch" id="otch_'.$model_id.'" size="20" value=""><br>

Фактический адрес проживания: <br /> улица:<input type="text" name="str" id="str_'.$model_id.'" size="30" value="">, дом:<input type="text" name="dom" id="dom_'.$model_id.'" size="3" value="">, квартира:<input type="text" name="kv" id="kv_'.$model_id.'" size="3" value="">, город:<input type="text" name="city" id="city_'.$model_id.'" size="10" value="Минск"><br />
Прописка: <input type="button" value="копировать адрес в прописку" id="address_copy" onclick="copy_addr(\''.$model_id.'\'); return false;"><br />
улица:<input type="text" name="reg_str" id="reg_str_'.$model_id.'" size="30" value="">, дом:<input type="text" name="reg_dom" id="reg_dom_'.$model_id.'" size="3" value="">, квартира:<input type="text" name="reg_kv" id="reg_kv_'.$model_id.'" size="3" value="">, город:<input type="text" name="reg_city" id="reg_city_'.$model_id.'" size="10" value=""> <br>

серия, № паспорта:<input type="text" name="pas_n" id="pas_n_'.$model_id.'" size="15" value="">
выдан, дата(ДД-MM-ГГГГ):<input type="text" name="pas_date_d" id="pas_date_d_'.$model_id.'" value="" size="2" maxlength="2">-<input type="text" name="pas_date_m" id="pas_date_m_'.$model_id.'" value="" size="2" maxlength="2">-<input type="text" name="pas_date_y" id="pas_date_y_'.$model_id.'" value="" size="2" maxlength="4"><br />
кем выдан:<input type="text" name="pas_who" id="pas_who_'.$model_id.'" size="70" value=""><br>

Телефон 1 (+375):<input type="text" name="phone_1" id="phone_1_'.$model_id.'" size="30" value="">
Телефон 2 (+375):<input type="text" name="phone_2" id="phone_2_'.$model_id.'" size="30" value=""><br>

		<br />
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;		
     <input type="button" name="action" value="сохранить" onclick="send_pas(\''.$model_id.'\')" />
     <input type="button" class="zakaz_otm" id="zakaz_otm_'.$model_id.'" value="отмена" onclick="cans_pas(\''.$model_id.'\')" />
		
		
</form>	
			
			
			
			';
	
}

elseif ($q_type=='pas_send') {

$phone_1=phone_to_n($phone_1);
$phone_2=phone_to_n($phone_2);
	
//две кавычки под поле id    вставляем нового клиента
$pas_date=strtotime($pas_date_y.'-'.$pas_date_m.'-'.$pas_date_d); //приводим в формат юникс дату календаря гггг-мм-дд
$query = "INSERT INTO clients VALUES('', '$family', '$p_name', '$otch', '$city', '$str', '$dom', '$kv', '$pas_n', '$pas_date', '$pas_who', '$reg_city', '$reg_str', '$reg_dom', '$reg_kv', '$phone_1', '$phone_2', '$info', 'web', ".time().")";
if (!mysql_query($query, $db_server)) {
	echo "Сбой при вставке данных: '$query' <br />".mysql_error()."<br /><br />";
			}
$client_id=mysql_insert_id();


echo '<form name="order" method="post" action="dogovor.php" class="pas_form_ok">
		Благодарим Вас! <br />	
		Ваши данные успешно отправлены. <br />
		Ждем Вас по адресу: пр-т Машерова, 18.<br />
		При себе иметь: паспорт, залог в размере 300 тыс.руб + сумму для оплаты проката костюма<br />

<br /><input type="button" class="zakaz_otm" id="zakaz_otm_'.$model_id.'" value=" OK " onclick="cans_pas(\''.$model_id.'\')" />
		</form>';


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


function phone_to_n ($ph) {
	$ph=preg_replace("|[^0-9]|i", "", $ph);
	return $ph;
}
 


?>