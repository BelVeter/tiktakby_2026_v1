<?php

use bb\Base;
use bb\classes\Deal;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/bron.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Deal.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //

//------- proverka paroley
$in_level= array(0,5,7,-1);

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
<title>Страница курьера</title>
'.Base::getBarCodeReaderScript().'
</head>
<body>
';

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

?>

<script language="javascript">

history.pushState(null, null, location.href);
window.onpopstate = function(event) {
    history.go(1);
};

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
}//end of getXmlHttp


function chose_item(action_type, sub_id, ret_date) {

	ret_date = ret_date || 1;

	document.getElementById('post_div_'+sub_id).innerHTML='<img src="w.gif" />';

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cur_ch_new.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'sub_dl_id=' + encodeURIComponent(sub_id) + '&action_type=' + encodeURIComponent(action_type) + '&ret_date=' + encodeURIComponent(ret_date);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {

	    	 eval (xmlhttp.responseText);

			if (ch_result=='no') {
				document.getElementById('inv_n_ok').value='no';
				alert ('Глюк - сообщите Диме!');
				}

			   }
	  		}
		}
}// end of choose_item


function past_due_recalc (sub_id) {

	document.getElementById('dolg_past_due_rent_'+sub_id).value='---';
//	document.getElementById('dolg_past_due_total_'+sub_id).value='---';
//	document.getElementById('dolg_total_rent_'+sub_id).value='---';






	var action_type='past_due_calc';
	var ret_date=document.getElementById('start_date_'+sub_id).value;

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/cur_ch_new.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = 'sub_dl_id=' + encodeURIComponent(sub_id) + '&action_type=' + encodeURIComponent(action_type) + '&ret_date=' + encodeURIComponent(ret_date);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {

	    	 eval (xmlhttp.responseText);
	    	 document.getElementById('dolg_past_due_total_'+sub_id).value=document.getElementById('dolg_past_due_rent_'+sub_id).value*1-document.getElementById('dolg_past_due_deliv_'+sub_id).value*1;
	    	 document.getElementById('dolg_total_rent_'+sub_id).value=document.getElementById('dolg_rent_'+sub_id).value*1+document.getElementById('dolg_past_due_rent_'+sub_id).value*1;
	    	 document.getElementById('dolg_total_deliv_'+sub_id).value=document.getElementById('dolg_deliv_'+sub_id).value*1-document.getElementById('dolg_past_due_deliv_'+sub_id).value*1;
	    	 document.getElementById('dolg_total_'+sub_id).value=document.getElementById('dolg_total_rent_'+sub_id).value*1+document.getElementById('dolg_total_deliv_'+sub_id).value*1;

	    	 document.getElementById('ret_payment_amount_'+sub_id).value=-document.getElementById('dolg_total_rent_'+sub_id).value*1;
	    	 document.getElementById('delivery_price_'+sub_id).value=-document.getElementById('dolg_total_deliv_'+sub_id).value*1;


			if (ch_result=='no') {
				document.getElementById('inv_n_ok').value='no';
				alert ('Глюк - сообщите Диме!');
				}

			   }
	  		}
		}

}



function hide_client (sub_id) {

	disable('family_'+sub_id);
	disable('name_'+sub_id);
	disable('otch_'+sub_id);
	disable('str_'+sub_id);
	disable('dom_'+sub_id);
	disable('kv_'+sub_id);
	disable('city_'+sub_id);
	disable('pas_n_'+sub_id);
	disable('pas_date_'+sub_id);
	disable('pas_who_'+sub_id);
	disable('reg_str_'+sub_id);
	disable('reg_dom_'+sub_id);
	disable('reg_kv_'+sub_id);
	disable('reg_city_'+sub_id);
	disable('phone_1_'+sub_id);
	disable('phone_2_'+sub_id);
	disable('info_'+sub_id);
	disable('address_copy_'+sub_id);
	disable('pas_ln_'+sub_id);
	//disable('action_save_cl_'+sub_id);

	if (document.getElementById('client_update_'+sub_id).value==0)
	{document.getElementById('client_update_'+sub_id).value=1;}
	else {document.getElementById('client_update_'+sub_id).value=0;}

	if (document.getElementById('cl_edit_button_'+sub_id).value=='редактировать информацию клиента')
	{document.getElementById('cl_edit_button_'+sub_id).value='отменить редактирование информации клиента';
	}
	else
	{document.getElementById('cl_edit_button_'+sub_id).value='редактировать информацию клиента';}


}


function disable (id) {
	if (document.getElementById(id).disabled==true) {
		document.getElementById(id).disabled=false;
	}
	else {
	document.getElementById(id).disabled=true;
	}
}


function copy_addr(sub_id) {
	document.getElementById('reg_str_'+sub_id).value=document.getElementById('str_'+sub_id).value;
	document.getElementById('reg_dom_'+sub_id).value=document.getElementById('dom_'+sub_id).value;
	document.getElementById('reg_kv_'+sub_id).value=document.getElementById('kv_'+sub_id).value;
	document.getElementById('reg_city_'+sub_id).value=document.getElementById('city_'+sub_id).value;
	}


function cans(id) {
	document.getElementById(id).innerHTML='';
}



function apply_tarif (tarif_id, sub_id) {

	document.getElementById('rent_tarif_'+sub_id).value=document.getElementById('rent_per_step_'+sub_id+'_'+tarif_id).value;

	if (value=document.getElementById('step_'+sub_id+'_'+tarif_id).value=='day') {document.getElementById('step_'+sub_id).value='day';}
	if (value=document.getElementById('step_'+sub_id+'_'+tarif_id).value=='week') {document.getElementById('step_'+sub_id).value='week';}
	if (value=document.getElementById('step_'+sub_id+'_'+tarif_id).value=='month') {document.getElementById('step_'+sub_id).value='month';}

	document.getElementById('rent_tenor_'+sub_id).value=document.getElementById('kol_vo_'+sub_id+'_'+tarif_id).value;
	document.getElementById('tarif_id_'+sub_id).value=tarif_id;

	calculate(sub_id);
}


function calculate(sub_id) {

	r_step=document.getElementById('step_'+sub_id).value;
	rent_date = new Date(document.getElementById('start_date_'+sub_id).value);
	r_tenor=document.getElementById('rent_tenor_'+sub_id).value.replace (/\,/, '.');

	switch (r_step) {
	  case 'day':
		rent_date.setDate(rent_date.getDate()+(r_tenor)*1);
		document.getElementById('return_date_'+sub_id).value=formatDate(rent_date);
		document.getElementById('r_to_pay_'+sub_id).value=(document.getElementById('rent_tarif_'+sub_id).value.replace (/\,/, '.')*r_tenor).toFixed(2);
			document.getElementById('r_paid_'+sub_id).value=document.getElementById('r_to_pay_'+sub_id).value;
			document.getElementById('del_paid_'+sub_id).value=document.getElementById('del_to_pay_'+sub_id).value;

	    break


	  case 'week':
		rent_date.setDate(rent_date.getDate()+(r_tenor)*7);
		document.getElementById('return_date_'+sub_id).value=formatDate(rent_date);
		document.getElementById('r_to_pay_'+sub_id).value=(document.getElementById('rent_tarif_'+sub_id).value*r_tenor).toFixed(2);
		//document.getElementById('total_to_pay').value=document.getElementById('r_to_pay').value*1+document.getElementById('delivery_price').value*1;
			document.getElementById('r_paid_'+sub_id).value=document.getElementById('r_to_pay_'+sub_id).value;
			document.getElementById('del_paid_'+sub_id).value=document.getElementById('del_to_pay_'+sub_id).value;


		break

	  case 'month':
		rent_date.setMonth(rent_date.getMonth()+(r_tenor)*1);
		document.getElementById('return_date_'+sub_id).value=formatDate(rent_date);
		document.getElementById('r_to_pay_'+sub_id).value=(document.getElementById('rent_tarif_'+sub_id).value*r_tenor).toFixed(2);
		//document.getElementById('total_to_pay').value=document.getElementById('r_to_pay').value*1+document.getElementById('delivery_price').value*1;
			document.getElementById('r_paid_'+sub_id).value=document.getElementById('r_to_pay_'+sub_id).value;
			document.getElementById('del_paid_'+sub_id).value=document.getElementById('del_to_pay_'+sub_id).value;

	    break
	}

	if(document.getElementById('delivery_flag_'+sub_id)) {
		document.getElementById('total_to_pay_'+sub_id).value=document.getElementById('r_to_pay_'+sub_id).value*1+document.getElementById('delivery_price_'+sub_id).value*1;
		}


}

function formatDate(date) {

	  var dd = date.getDate()
	  if ( dd < 10 ) dd = '0' + dd;

	  var mm = date.getMonth()+1
	  if ( mm < 10 ) mm = '0' + mm;

	  var yyyy = date.getFullYear();

	  return yyyy+'-'+mm+'-'+dd;
	}



function save_first(sub_id) {
	var output=true;
	var output1=true;
	var output2=true;

	if (document.getElementById('rent_payment_type_'+sub_id).value=='no_payment' && document.getElementById('r_paid_'+sub_id).value>0) {
		output1=confirm ('Сохранить выезд без оплаты аренды?');
	}

	if (document.getElementById('del_payment_type_'+sub_id).value=='no_payment' && document.getElementById('del_paid_'+sub_id).value>0) {
		output2=confirm ('Сохранить выезд без оплаты доставки?');
	}

	if(output1==true && output2==true) {
		output=true;
	}
	else {
		output=false;
	}


	if (!document.getElementById('viezd_date_'+sub_id)) {
	var today_d=new Date();
	var start_date = new Date(document.getElementById('start_date_'+sub_id).value);
	//var pl_date = new Date(document.getElementById('start_date').value);

	if (start_date>today_d) {
		alert ('Дата выезда/платежа не может быть в будущем!');
		output=false;
	}

	}



	if (document.getElementById('viezd_date_'+sub_id)) {
		var today_d=new Date();
		var start_date = new Date(document.getElementById('viezd_date_'+sub_id).value);
		if (start_date>today_d) {
			alert ('Дата выезда курьера не может быть в будущем!');
			output=false;
		}

	}



	return output;
}

/*function ret_st (sub_id) {

	if (document.getElementById('return_status_'+sub_id).value=="ok") {
		document.getElementById('sub_deal_span_'+sub_id).style.display="none";
		document.getElementById('sub_deal_info_'+sub_id).value='';
	}
	else {
		document.getElementById('sub_deal_span_'+sub_id).style.display="";
	}

}
*/



function ret_save (sub_id) {

	valid=true;

	if (document.getElementById('return_status_'+sub_id).value=='not_ok' && document.getElementById('sub_deal_info_'+sub_id).value=="") {
		alert ('Заполните комментарии по некомплекту!')
		valid=false;
	}

	if ((document.getElementById('ret_payment_amount_'+sub_id).value=="" || document.getElementById('ret_payment_amount_'+sub_id).value=="0") && document.getElementById('return_p_kassa_'+sub_id).value!="no_payment") {
		alert ('Не проставлена сумма при выбранной кассе. Либо поставьте сумму, либо выберите "не оплачено"');
		valid=false;
	}
	if (document.getElementById('ret_payment_amount_'+sub_id).value!="" && document.getElementById('ret_payment_amount_'+sub_id).value!='0' && document.getElementById('return_p_kassa_'+sub_id).value=="no_payment") {
		alert ('Если указали сумму, то указывайте и кассу. Либо очистите сумму и выберите "не оплачено"');
		valid=false;
	}

	if (document.getElementById('delivery_price_'+sub_id).value!='' && document.getElementById('delivery_price_'+sub_id).value!='0' && document.getElementById('return_p_kassa_deliv_'+sub_id).value=='no_payment') {
		alert ('Если указали сумму за выезд курьера, то указывайте и кассу. Либо очистите сумму за выезд курьера и выберите "не оплачено"');
		valid=false;
	}
	if ((document.getElementById('delivery_price_'+sub_id).value=='' || document.getElementById('delivery_price_'+sub_id).value=='0') && document.getElementById('return_p_kassa_deliv_'+sub_id).value!='no_payment') {
		alert ('Если указали сумму за выезд курьера, то указывайте и кассу. Либо очистите сумму за выезд курьера и выберите "не оплачено"');
		valid=false;
	}

	if (document.getElementById('of_select_'+sub_id).value=='0') {
		alert ('Выберите офис, в который возвращается товар !!!')
		valid=false;
	}


		var today_d=new Date();
		var start_date = new Date(document.getElementById('start_date_'+sub_id).value);
		//var pl_date = new Date(document.getElementById('start_date').value);

		if (start_date>today_d) {
			alert ('Дата возврата не может быть в будущем!');
			valid=false;
		}



	return valid;
}


function ch_num_ch(dl_id) {

	if ((document.getElementById('rent_payment_type_'+dl_id).value=='nal_cheque' || document.getElementById('rent_payment_type_'+dl_id).value=='card' || document.getElementById('rent_payment_type_'+dl_id).value=='bank') || (document.getElementById('del_payment_type_'+dl_id).value=='nal_cheque' || document.getElementById('del_payment_type_'+dl_id).value=='card' || document.getElementById('del_payment_type_'+dl_id).value=='bank')) {
		document.getElementById('ch_num_span').style.display="";
	}
	else {
		document.getElementById('ch_num_span').style.display="none";
	}
}

function ch_num_ch_ret(dl_id) {

	if ((document.getElementById('return_p_kassa_'+dl_id).value=='nal_cheque' || document.getElementById('return_p_kassa_'+dl_id).value=='card' || document.getElementById('return_p_kassa_'+dl_id).value=='bank') || (document.getElementById('return_p_kassa_deliv_'+dl_id).value=='nal_cheque' || document.getElementById('return_p_kassa_deliv_'+dl_id).value=='card' || document.getElementById('return_p_kassa_deliv_'+dl_id).value=='bank')) {
		document.getElementById('ch_num_span').style.display="";
	}
	else {
		document.getElementById('ch_num_span').style.display="none";
	}
}



</script>





<?php

echo '
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: '.$_SESSION['office'].'</form> </div>
<div id="zv_div"></div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/rda.php">Все сделки</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
</div>


		';
if ($_SESSION['level']!=-1) {
	require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам
}


$cur_show='for_cur';
$payment='not_ok';
$payment_id='';
$i_from_date='';
$i_to_date='';
$one_sub_dl_id='';
$action_per='def';
$action_prev_per='';
$prev_action='';
$cur_id=0;



foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

if ($action_per=='def' && $action_prev_per!='') {$action_per=$action_prev_per;}

if (isset($_POST['action'])) {
		$st_ch='';


		if ($action=='сохранить выезд') { // первая выдача + продление

			$action='обновить информацию';

			if ($rent_payment_type!='no_payment' || $del_payment_type!='no_payment') {
				$payment='ok';
			}
			//изменение статуса суб. сделки
			$st_ch=', `status`=\'delivered\'';
			$rent_payment_type=='no_payment' ? $r_paid=0 : $r_paid=$r_paid;
			$del_payment_type=='no_payment' ? $del_paid=0 : $del_paid=$del_paid;

		}

	switch ($action) {

	case 'обновить информацию': // обновление информации + сохранение выезда в случае первой выдачи и продления

		$query_sub_dl = "SELECT * FROM rent_sub_deals_act WHERE sub_deal_id='$sub_deal_id'";
		$result_sub_dl = mysql_query($query_sub_dl);
		if (!$result_sub_dl) die("Сбой при доступе к базе данных: '$query_sub_dl'".mysql_error());
		$sub_dl=mysql_fetch_array($result_sub_dl);

		//запрос актуальной информации о сделке
		$query_dl_def1 = "SELECT * FROM rent_deals_act WHERE deal_id='".$sub_dl['deal_id']."'";
		$result_dl_def1 = mysql_query($query_dl_def1);
		if (!$result_dl_def1) die("Сбой при доступе к базе данных: '$query_dl_def1'".mysql_error());
		$dl_def1=mysql_fetch_array($result_dl_def1);


		if ($client_id>0 && $client_update==1) {       //обновление информации о клиенте,

			if ($family=='' || $name=='') {die('Попытка обновить информацию о клиенте пустыми значениями. Запомните, что и как Вы делали и сообщите Диме!');}

			$phone_1=phone_to_n($phone_1);
			$phone_2=phone_to_n($phone_2);
			$pas_date=strtotime($pas_date); //приводим в формат юникс дату календаря гггг-мм-дд

			$family=mb_convert_case($family, MB_CASE_TITLE, 'UTF-8');
			$name=mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
			$otch=mb_convert_case($otch, MB_CASE_TITLE, 'UTF-8');
			$city=mb_convert_case($city, MB_CASE_TITLE, 'UTF-8');
			//$str=mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
			$reg_city=mb_convert_case($reg_city, MB_CASE_TITLE, 'UTF-8');
			//$reg_str=mb_convert_case($reg_str, MB_CASE_TITLE, 'UTF-8');

			$query_cl_upd = "UPDATE clients SET family='$family', name='$name', otch='$otch', city='$city', str='$str', dom='$dom', kv='$kv', pas_n='$pas_n', pas_ln='$pas_ln', pas_date='$pas_date', pas_who='$pas_who', reg_city='$reg_city', reg_str='$reg_str', reg_dom='$reg_dom', reg_kv='$reg_kv', phone_1='$phone_1', phone_2='$phone_2', info='$info' WHERE client_id='$client_id'";
			if (!mysql_query($query_cl_upd, $db_server)) {
				echo "Сбой при вставке данных: '$query_cl_upd' <br />".mysql_error()."<br /><br />";
			}

			$client_update=0;

		}


		if ($sub_dl['type']=='first_rent' || $sub_dl['type']=='extention') {

		$query_start = "START TRANSACTION";
		$result_start = mysql_query($query_start);
		if (!$result_start) {$done="no"; die("Сбой при доступе к базе данных: '$query_start'".mysql_error());}

		$done="yes";

		$start_date=strtotime($start_date); //приводим в формат юникс дату календаря гггг-мм-дд
		$return_date=strtotime($return_date); //приводим в формат юникс дату календаря гггг-мм-дд

		$sub_dl['type']=='extention' ? $acc_date=strtotime($viezd_date) : $acc_date=$start_date;

		//внесение платежа, при наличии
		if ($payment=='ok') {
			// вносим суб-сделку (история + подробности)
			$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '".$sub_dl['deal_id']."', 'payment', '30', '$start_date', '', '', '', '', '', '', '1', '', '$courier_id', '$r_paid', '$del_paid', '$rent_payment_type', '$del_payment_type', 'cur_payment', '', '".time()."', '".$_SESSION['user_id']."', '', '', '".$sub_dl['sub_deal_id']."', '$acc_date', '0', '$ch_num', '', '', '')";
			if (!mysql_query($sub_query, $db_server)) {
				$done="no"; echo "Сбой при вставке данных: '$sub_query' <br />".mysql_error()."<br /><br />";
			}
			$payment_id=mysql_insert_id($db_server);

			$query_dl_up = "SELECT * FROM rent_deals_act WHERE deal_id='".$sub_dl['deal_id']."'";
			$result_dl_up = mysql_query($query_dl_up);
			if (!$result_dl_up) {$done="no"; die("Сбой при доступе к базе данных: '$query_dl_up'".mysql_error());}
			$dl_up=mysql_fetch_array($result_dl_up);

			//обновление сделки
			$query_dl_upd2 = "UPDATE rent_deals_act SET r_paid='".($dl_up['r_paid']+$r_paid)."', delivery_paid='".($dl_up['delivery_paid']+$del_paid)."', last_sub_deal_ch_time='".time()."' WHERE deal_id='".$sub_dl['deal_id']."'";
			if (!mysql_query($query_dl_upd2, $db_server)) {
				$done="no"; echo "Сбой при вставке данных: '$query_dl_upd2' <br />".mysql_error()."<br /><br />";
			}

		}



		//обновление суб сделки
		$query_sub_dl_upd = "UPDATE rent_sub_deals_act SET acc_date='$acc_date', delivery_yn='1', `from`='$start_date', `to`='$return_date', tarif_id='$tarif_id', tarif_step='$step', tarif_value='$rent_tarif', rent_tenor='$rent_tenor', r_to_pay='$r_to_pay', delivery_to_pay='$del_to_pay', courier_id='$courier_id', `info`='$deal_info', `link`='$payment_id', ch_time='".time()."', ch_who_id='".$_SESSION['user_fio']."'".$st_ch." WHERE sub_deal_id='$sub_deal_id'";
		if (!mysql_query($query_sub_dl_upd, $db_server)) {
			$done="no"; echo "Сбой при вставке данных: '$query_sub_dl_upd' <br />".mysql_error()."<br /><br />";
		}

		//обновление сделки
		$query_dl_upd = "UPDATE rent_deals_act SET start_date='$start_date', return_date='$return_date', delivery_to_pay='$del_to_pay', r_to_pay='$r_to_pay', deal_set='$deal_item_set', last_sub_deal_ch_time='".time()."' WHERE deal_id='".$sub_dl['deal_id']."'";
		if (!mysql_query($query_dl_upd, $db_server)) {
			$done="no"; echo "Сбой при вставке данных: '$query_dl_upd' <br />".mysql_error()."<br /><br />";
		}

		// меняем статус товара на "rented_out" (убираем to_deliver)
		$query_upd = "UPDATE tovar_rent_items SET status='rented_out' WHERE item_inv_n='".$dl_def1['item_inv_n']."'";
		if (!mysql_query($query_upd, $db_server)) {
			$done="no";
			echo "Сбой при вставке данных: '$query_upd' <br />".mysql_error()."<br /><br />";
		}


		if ($done=='yes') {
			$query_fin = "COMMIT";
			$result_fin = mysql_query($query_fin);
			if (!$result_fin) {die("Сбой при доступе к базе данных: '$query_fin'".mysql_error());}

		}
		else {
			$query_fin = "ROLLBACK";
			$result_fin = mysql_query($query_fin);
			if (!$result_fin) die("Сбой при доступе к базе данных: '$query_fin'".mysql_error());
		}

            Deal::recalculateAmounts($sub_dl['deal_id']);
	}//end of if


/*
	if ($sub_dl['type']=='cur_return') {//этот раздел работает только на обновление, т.к. сохранение выезда по возврату идет по другому алгоритму (см. далее)

		$query_start = "START TRANSACTION";
		$result_start = mysql_query($query_start);
		if (!$result_start) {$done="no"; die("Сбой при доступе к базе данных: '$query_start'".mysql_error());}

		$done="yes";

		$start_date=strtotime($start_date); //приводим в формат юникс дату календаря гггг-мм-дд

		//обновление суб сделки
		$query_sub_dl_upd = "UPDATE rent_sub_deals_act SET `from`='$start_date', acc_date='$start_date', delivery_to_pay='$del_to_pay', courier_id='$courier_id', `info`='$sub_deal_info', ch_time='".time()."', ch_who_id='".$_SESSION['user_fio']."'".$st_ch." WHERE sub_deal_id='$sub_dl_id'";
		if (!mysql_query($query_sub_dl_upd, $db_server)) {
			$done="no"; echo "Сбой при вставке данных: '$query_sub_dl_upd' <br />".mysql_error()."<br /><br />";
		}

		if ($done=='yes') {
			$query_fin = "COMMIT";
			$result_fin = mysql_query($query_fin);
			if (!$result_fin) {die("Сбой при доступе к базе данных: '$query_fin'".mysql_error());}

		}
		else {
			$query_fin = "ROLLBACK";
			$result_fin = mysql_query($query_fin);
			if (!$result_fin) die("Сбой при доступе к базе данных: '$query_fin'".mysql_error());
		}
	}//end of if
*/




	break;






	case 'сохранить возврат':
		// !!!-!!! проработать все еще раз и протестировать: были проблемы с рефанд вместо пэймент + подумать, должны ли быть особенности возвращения из архива сделки с курьером, и вообще, нужен ли тип платежа "рефанд"

		$start_date=strtotime($start_date); //приводим в формат юникс дату календаря гггг-мм-дд
		$ret_payment_amount=tonum($ret_payment_amount);//меняем запятую на точку + убираем пробелы и лишние символы
		$delivery_paid=tonum($delivery_paid);//меняем запятую на точку + убираем пробелы и лишние символы
		$to_pay_pastdue=tonum($to_pay_pastdue);//меняем запятую на точку + убираем пробелы и лишние символы
		isset($delivery) ? $delivery=1 : $delivery=0;
		if ($ret_payment_amount<0) {$to_pay_pastdue+=$ret_payment_amount;}//возврат денег уменьшает сумму к оплате

		$query_start = "START TRANSACTION";
		$result_start = mysql_query($query_start);
		if (!$result_start) {$done="no"; die("Сбой при доступе к базе данных: '$query_start'".mysql_error());}

		$done="yes";


		if ($return_p_kassa!='no_payment' || $return_p_kassa_deliv!='no_payment') { // вносим оплату (при наличии)
			$t_type='cl_payment';

			// вносим суб-сделку - оплата
			$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$active_deal_id', '$t_type', '30', '$start_date', '', '', '', '', '', '', '1', '', '$courier_id', '$ret_payment_amount', '$delivery_paid', '$return_p_kassa', '$return_p_kassa_deliv', 'cur_payment', '', '".time()."', '".$_SESSION['user_id']."', '', '', '".$sub_dl_id."', '$start_date', '', '$ch_num', '', '', '')";
			if (!mysql_query($sub_query, $db_server)) {
				$done="no"; echo "Сбой при вставке данных: '$sub_query' <br />".mysql_error()."<br /><br />";
			}
		}


		// обновляем суб-сделку - возврат
		$sub_query = "UPDATE rent_sub_deals_act SET `from`='$start_date', acc_date='$start_date', `type`='close', r_to_pay='$to_pay_pastdue', delivery_yn='1', delivery_to_pay='$del_to_pay', courier_id='$courier_id', `status`='delivered', `info`='$sub_deal_info', ch_time='".time()."', ch_who_id='".$_SESSION['user_id']."' WHERE sub_deal_id='$sub_dl_id'";
		if (!mysql_query($sub_query, $db_server)) {
			$done="no"; echo "Сбой при вставке данных: '$sub_query' <br />".mysql_error()."<br /><br />";
		}

		// корректируем сделку
				//запрос актуальной информации о сделке
				$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='$active_deal_id'";
				$result_dl_def = mysql_query($query_dl_def);
				if (!$result_dl_def) die("Сбой при доступе к базе данных: '$query_dl_def'".mysql_error());
				$dl_def=mysql_fetch_array($result_dl_def);

		$r_to_pay=$dl_def['r_to_pay']+$to_pay_pastdue;
		$del_to_pay=$dl_def['delivery_to_pay']+$del_to_pay;
		$delivery_paid=$dl_def['delivery_paid']+$delivery_paid;
		$r_paid=$dl_def['r_paid']+$ret_payment_amount;

		($r_to_pay+$del_to_pay)>($r_paid+$delivery_paid) ? $f_status='closed_loss' : $f_status='closed';
		$ret_status=='not_ok' ? $f_status=$f_status.'_problem' : $f_status=$f_status;


		$query_dl_upd = "UPDATE rent_deals_act SET delivery_paid='$delivery_paid', r_paid='$r_paid', r_to_pay='$r_to_pay', delivery_to_pay='$del_to_pay', return_date='$start_date', deal_status='$f_status', deal_set='$deal_item_set', last_sub_deal_ch_time='".time()."' WHERE deal_id='$active_deal_id'";
		if (!mysql_query($query_dl_upd, $db_server)) {
			$done="no";
			echo "Сбой при вставке данных: '$query_dl_upd' <br />".mysql_error()."<br /><br />";
		}

			//перенос записей суб. сделок в архив
			$query_arch_sub = "INSERT INTO rent_sub_deals_arch SELECT '', '".time()."', sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, `info`, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place`, ch_num, sd_cat_id, sd_model_id, sd_inv_n FROM rent_sub_deals_act WHERE deal_id='$active_deal_id'";
			if (!mysql_query($query_arch_sub, $db_server)) {
			$done="no";
			echo "Сбой при вставке данных: '$query_arch_sub' <br />".mysql_error()."<br /><br />";
			}

			// далее делаем удаление суб. сделок
			$query_del_sub = "DELETE FROM rent_sub_deals_act WHERE deal_id='$active_deal_id'";
			if (!mysql_query($query_del_sub, $db_server)) {
			$done="no";
			echo "Сбой при удалении данных: '$query_del_sub' <br />".mysql_error()."<br /><br />";
			}

			//перенос записей основной сделок в архив
			$query_arch_dl = "INSERT INTO rent_deals_arch SELECT '', '".time()."', deal_id, client_id, item_inv_n, start_date, return_date, delivery_yn, delivery_to_pay, delivery_paid, r_to_pay, r_paid, collateral_amount, collateral_cur, deal_status, deal_info, acc_person_id, cr_who_id, cr_time, last_sub_deal_ch_time, planned_return_date, deal_set, first_rent_place FROM rent_deals_act WHERE deal_id='$active_deal_id'";
			if (!mysql_query($query_arch_dl, $db_server)) {
			$done="no";
			echo "Сбой при вставке данных: '$query_arch_dl' <br />".mysql_error()."<br /><br />";
			}


			// далее делаем удаление сделок
			$query_del_dl = "DELETE FROM rent_deals_act WHERE deal_id='$active_deal_id'";
			if (!mysql_query($query_del_dl, $db_server)) {
			$done="no";
			echo "Сбой при удалении данных: '$query_del_dl' <br />".mysql_error()."<br /><br />";
			}


			// меняем статус товара на "свободно" + убираем deal_id
			$query_upd = "UPDATE tovar_rent_items SET status='to_rent', active_deal_id='', item_place='$of_select' WHERE item_inv_n='".$dl_def['item_inv_n']."'";
			if (!mysql_query($query_upd, $db_server)) {
			$done="no";
			echo "Сбой при вставке данных: '$query_upd' <br />".mysql_error()."<br /><br />";
			}

		// делаем корректировку информации на клиенте (для быстрого вывода истории)
			//через id сделки ищем всю сделку
			$query_cldl = "SELECT * FROM rent_deals_arch WHERE deal_id='$active_deal_id'";
			$result_cldl = mysql_query($query_cldl);
			if (!$result_cldl) {$done="no"; die("Сбой при доступе к базе данных: '$query_cldl'".mysql_error());}
			$cldl=mysql_fetch_array($result_cldl);

			// через id клиента ищем клиента
			$query_cl = "SELECT * FROM clients WHERE client_id='".$cldl['client_id']."'";
			$result_cl = mysql_query($query_cl);
			if (!$result_cl) {$done="no"; die("Сбой при доступе к базе данных: '$query_cl'".mysql_error());}
			$cl=mysql_fetch_array($result_cl);

			// обновляем информацию о сделках клиента
			$query_cl_upd = "UPDATE clients SET arch_n='".($cl['arch_n']+1)."', arch_amount='".($cl['arch_amount']+$r_to_pay)."', arch_l_date='$start_date' WHERE client_id='".$cldl['client_id']."'";
			if (!mysql_query($query_cl_upd, $db_server)) {
				$done="no";
				echo "Сбой при вставке данных: '$query_cl_upd' <br />".mysql_error()."<br /><br />";
			}


		//завершение
		if ($done=='yes') {
		$query_fin = "COMMIT";
		$result_fin = mysql_query($query_fin);
					if (!$result_fin) die("Сбой при доступе к базе данных: '$query_fin'".mysql_error());
			}
		else {
		$query_fin = "ROLLBACK";
		$result_fin = mysql_query($query_fin);
		if (!$result_fin) die("Сбой при доступе к базе данных: '$query_fin'".mysql_error());
									}
        Deal::recalculateAmounts($active_deal_id);
        Deal::recalculateAmounts($active_deal_id);

		//вставка на стирку
		$bron = new \bb\classes\bron();
		$bron->inv_n=$dl_def['item_inv_n'];
		$bron->item_load();

		$bron->type2='stirka';
		$bron->cr_time=time();
		$bron->cr_who_id=$bron->user_id;

		//print_r($bron);

		$bron->insert();

		unset($bron);



	break;

	}//end of switch


}//end of post if

$period_no_start=1;

if ($cur_show=='all') {$st_list='for_cur\', \'delivered';}
else {$st_list=$cur_show;}

if ($one_sub_dl_id>0) {

	$query_sub_dl_def = "SELECT * FROM rent_sub_deals_act WHERE `sub_deal_id` = '$one_sub_dl_id' ORDER BY `from` DESC";
	$result_sub_dl_def = mysql_query($query_sub_dl_def);
	if (!$result_sub_dl_def) die("Сбой при доступе к базе данных: '$query_sub_dl_def'".mysql_error());
//!!! добавить поиск по архиву
}
else {
	switch ($action_per) {
		case 'сегодня':

			$from_date=strtotime(date("Y-m-d"));
			$sort_date="(acc_date='".$from_date."')";
			$period_no_start=0;

			$sort_cl=" WHERE `status` IN ('$st_list') AND acc_date='$from_date' ORDER BY `from` DESC";
			$query_sub_dl_def = "(SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_act".$sort_cl.") UNION ALL (SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_arch".$sort_cl.") ORDER BY `acc_date` DESC, `cr_time` DESC";
			$result_sub_dl_def = mysql_query($query_sub_dl_def);
			if (!$result_sub_dl_def) die("Сбой при доступе к базе данных: '$query_sub_dl_def'".mysql_error());

			$prev_action='сегодня';

			$i_from_date=date("Y-m-d");
			$i_to_date=date("Y-m-d");

			break;

			case 'вчера':

				$today=getdate(time());
				$from_date=mktime(0, 0, 0, $today['mon'], ($today['mday']-1), $today['year']);
				$sort_date="(acc_date='".$from_date."')";
				$period_no_start=0;

				$sort_cl=" WHERE `status` IN ('$st_list') AND acc_date='$from_date' ORDER BY `from`";
				$query_sub_dl_def = "(SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_act".$sort_cl.") UNION ALL (SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_arch".$sort_cl.") ORDER BY `acc_date` DESC, `cr_time` DESC";
				$result_sub_dl_def = mysql_query($query_sub_dl_def);
				if (!$result_sub_dl_def) die("Сбой при доступе к базе данных: '$query_sub_dl_def'".mysql_error());

				$prev_action='вчера';
				$i_from_date=date("Y-m-d", $from_date);
				$i_to_date=date("Y-m-d", $from_date);

				break;

			case 'завтра+':

				$today=getdate(time());
				$from_date=mktime(0, 0, 0, $today['mon'], ($today['mday']+1), $today['year']);
				$sort_date="(acc_date>='".$from_date."')";
				$period_no_start=0;

				$sort_cl=" WHERE `status` IN ('$st_list') AND acc_date>='$from_date' ORDER BY `from`";
				$query_sub_dl_def = "(SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_act".$sort_cl.") UNION ALL (SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_arch".$sort_cl.") ORDER BY `acc_date`, `cr_time`";
				$result_sub_dl_def = mysql_query($query_sub_dl_def);
				if (!$result_sub_dl_def) die("Сбой при доступе к базе данных: '$query_sub_dl_def'".mysql_error());

				$prev_action='завтра+';
				$i_from_date=date("Y-m-d", $from_date);
				$i_to_date='';

				break;

        case 'завтра':

            $today=getdate(time());
            $from_date=mktime(0, 0, 0, $today['mon'], ($today['mday']+1), $today['year']);
            $sort_date="(acc_date='".$from_date."')";
            $period_no_start=0;

            $sort_cl=" WHERE `status` IN ('$st_list') AND acc_date='$from_date' ORDER BY `from`";
            $query_sub_dl_def = "(SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_act".$sort_cl.") UNION ALL (SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_arch".$sort_cl.") ORDER BY `acc_date`, `cr_time`";
            $result_sub_dl_def = mysql_query($query_sub_dl_def);
            if (!$result_sub_dl_def) die("Сбой при доступе к базе данных: '$query_sub_dl_def'".mysql_error());

            $prev_action='завтра';
            $i_from_date=date("Y-m-d", $from_date);
            $i_to_date=$i_from_date;

            break;

			case 'показать':

				$from_date=strtotime($i_from_date);
				$to_date=strtotime($i_to_date);
				$sort_date="(acc_date BETWEEN '".$from_date."' AND '".$to_date."')";
				$period_no_start=0;

				$sort_cl=" WHERE `status` IN ('$st_list') AND (acc_date BETWEEN '$from_date' AND '$to_date') ORDER BY `from` DESC";
				$query_sub_dl_def = "(SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_act".$sort_cl.") UNION ALL (SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_arch".$sort_cl.") ORDER BY `acc_date` DESC, `cr_time` DESC";
				$result_sub_dl_def = mysql_query($query_sub_dl_def);
				if (!$result_sub_dl_def) die("Сбой при доступе к базе данных: '$query_sub_dl_def'".mysql_error());

				$prev_action='показать';

			break;

		default:
			//!!! оставить только условие, запрос сдвинуть за свитч

			$sort_cl=" WHERE `status` IN ('$st_list') ORDER BY `from` DESC";
			$query_sub_dl_def = "(SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_act".$sort_cl.") UNION ALL (SELECT sub_deal_id, deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value, rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid, delivery_paid, r_payment_type, del_payment_type, `status`, info, cr_time, cr_who_id, ch_time, ch_who_id, `link`, acc_date, `place` FROM rent_sub_deals_arch".$sort_cl.") ORDER BY `acc_date` DESC, `cr_time` DESC";
			$result_sub_dl_def = mysql_query($query_sub_dl_def);
			if (!$result_sub_dl_def) die("Сбой при доступе к базе данных: '$query_sub_dl_def'".mysql_error());
			$i_from_date='';
			$i_to_date='';

			$sort_date="(acc_date>='0')";
			$period_no_start=1;

		break;
	}

}
//echo $query_sub_dl_def;




echo '
		<form name="dates" method="post" id="cur_show_form" action="cur_page.php">
			<input type="submit" name="action_per" value="сегодня" /><span '.($_SESSION['level']==-1 ? 'style="display:none;"' : '').'>
				<input type="submit" name="action_per" value="вчера" /> <input type="submit" name="action_per" value="завтра" /> <input type="submit" name="action_per" value="завтра+" /> <input type="submit" name="action_per" value="все" /> За период:
			c <input type="date" name="i_from_date" id="i_from_date" value="'.$i_from_date.'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.$i_to_date.'" /> <input type="submit" name="action_per" value="показать" onclick="return send_form();" /><br /></span>

		Показать:
		<select name="cur_show" id="cur_show" onchange="document.getElementById(\'cur_show_form\').submit();">
			<option value="for_cur" '.($cur_show=='for_cur' ? 'selected="selected"' : '').'>не доставлено</option>
			<option value="delivered" '.($cur_show=='delivered' ? 'selected="selected"' : '').'>доставлено</option>
			<option value="all" '.($cur_show=='all' ? 'selected="selected"' : '').'>все</option>
		</select>

		<input type="hidden" name="action_prev_per" id="prev_action" value="'.$prev_action.'" />

					</form>
			    ';

echo '<input type="hidden" id="cur_show" value="'.$cur_show.'" />';

$query_curs = "SELECT * FROM logpass WHERE delivery>0 ORDER BY lp_fio";
$result_curs = mysql_query($query_curs);
if (!$result_curs) die("Сбой при доступе к базе данных: '$query_curs'".mysql_error());
$curs_select='<select form="cur_show_form" name="cur_id" id="cur_id" onchange="document.getElementById(\'cur_show_form\').submit();">
					<option value="0">все</option>

					';
while ($cur_names = mysql_fetch_array($result_curs)) {
	$curs_select.='<option value="'.$cur_names['logpass_id'].'" '.sel_d($cur_names['logpass_id'], $cur_id).' >'.$cur_names['lp_fio'].'</option>';
}

$curs_select.='</select>';


//расчет таблицы платежей
if ($period_no_start==0) {

$query_curs2 = "SELECT * FROM logpass ORDER BY lp_fio";
$result_curs2 = mysql_query($query_curs2);
if (!$result_curs2) die("Сбой при доступе к базе данных: '$query_curs2'".mysql_error());

echo '
	<table border="1" cellspacing="0" style="table-layout:fixed">
		<tr>
			<th scope="col" style="width:70px;">курьер</th>
			<th scope="col" style="width:70px;"></th>
			<th scope="col" style="width:70px;">к оплате</th>
			<th scope="col" style="width:70px;">К1</th>
			<th scope="col" style="width:70px;">К2</th>
			<th scope="col" style="width:30px;">Тер.</th>
			<th scope="col" style="width:70px;">Всего</th>
		</tr>

						';

$rtp_t=0;
$k1_t=0;
$k2_t=0;
$card_t=0;
$total_t=0;
$dtp_t=0;
$dk1_t=0;
$dk2_t=0;
$dcard_t=0;
$dtotal_t=0;

while ($curs2 = mysql_fetch_array($result_curs2)) {

//считаем оплаты по сделкам
$query_rtp = "SELECT SUM(`r_to_pay`) FROM rent_sub_deals_act WHERE courier_id='".$curs2['logpass_id']."' AND $sort_date";
$result_rtp = mysql_query($query_rtp);
if (!$result_rtp) die("Сбой при доступе к базе данных: '$query_rtp'".mysql_error());
$rtp=mysql_fetch_array($result_rtp);

	$query_rtp_a = "SELECT SUM(`r_to_pay`) FROM rent_sub_deals_arch WHERE courier_id='".$curs2['logpass_id']."' AND $sort_date";
	$result_rtp_a = mysql_query($query_rtp_a);
	if (!$result_rtp_a) die("Сбой при доступе к базе данных: '$query_rtp_a'".mysql_error());
	$rtp_a=mysql_fetch_array($result_rtp_a);

$query_k1 = "SELECT SUM(`r_paid`) FROM rent_sub_deals_act WHERE  r_payment_type='nal_cheque' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
$result_k1 = mysql_query($query_k1);
if (!$result_k1) die("Сбой при доступе к базе данных: '$query_k1'".mysql_error());
$k1=mysql_fetch_array($result_k1);

	$query_k1_a = "SELECT SUM(`r_paid`) FROM rent_sub_deals_arch WHERE  r_payment_type='nal_cheque' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
	$result_k1_a = mysql_query($query_k1_a);
	if (!$result_k1_a) die("Сбой при доступе к базе данных: '$query_k1_a'".mysql_error());
	$k1_a=mysql_fetch_array($result_k1_a);


$query_card = "SELECT SUM(`r_paid`) FROM rent_sub_deals_act WHERE  r_payment_type='card' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
$result_card = mysql_query($query_card);
if (!$result_card) die("Сбой при доступе к базе данных: '$query_card'".mysql_error());
$card=mysql_fetch_array($result_card);

	$query_card_a = "SELECT SUM(`r_paid`) FROM rent_sub_deals_arch WHERE  r_payment_type='card' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
	$result_card_a = mysql_query($query_card_a);
	if (!$result_card_a) die("Сбой при доступе к базе данных: '$query_card_a'".mysql_error());
	$card_a=mysql_fetch_array($result_card_a);

$query_k2 = "SELECT SUM(`r_paid`) FROM rent_sub_deals_act WHERE  r_payment_type='nal_no_cheque' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
$result_k2 = mysql_query($query_k2);
if (!$result_k2) die("Сбой при доступе к базе данных: '$query_k2'".mysql_error());
$k2=mysql_fetch_array($result_k2);

	$query_k2_a = "SELECT SUM(`r_paid`) FROM rent_sub_deals_arch WHERE  r_payment_type='nal_no_cheque' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
	$result_k2_a = mysql_query($query_k2_a);
	if (!$result_k2_a) die("Сбой при доступе к базе данных: '$query_k2_a'".mysql_error());
	$k2_a=mysql_fetch_array($result_k2_a);

$total=($rtp['SUM(`r_to_pay`)']+$rtp_a['SUM(`r_to_pay`)'])+($k1['SUM(`r_paid`)']+$k1_a['SUM(`r_paid`)'])+($card['SUM(`r_paid`)']+$card_a['SUM(`r_paid`)'])+($k2['SUM(`r_paid`)']+$k2_a['SUM(`r_paid`)']);




//считаем оплаты за доставки
$query_dtp = "SELECT SUM(`delivery_to_pay`) FROM rent_sub_deals_act WHERE courier_id='".$curs2['logpass_id']."' AND $sort_date";
$result_dtp = mysql_query($query_dtp);
if (!$result_dtp) die("Сбой при доступе к базе данных: '$query_dtp'".mysql_error());
$dtp=mysql_fetch_array($result_dtp);

	$query_dtp_a = "SELECT SUM(`delivery_to_pay`) FROM rent_sub_deals_arch WHERE courier_id='".$curs2['logpass_id']."' AND $sort_date";
	$result_dtp_a = mysql_query($query_dtp_a);
	if (!$result_dtp_a) die("Сбой при доступе к базе данных: '$query_dtp_a'".mysql_error());
	$dtp_a=mysql_fetch_array($result_dtp_a);

$query_dk1 = "SELECT SUM(`delivery_paid`) FROM rent_sub_deals_act WHERE  del_payment_type='nal_cheque' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
$result_dk1 = mysql_query($query_dk1);
if (!$result_dk1) die("Сбой при доступе к базе данных: '$query_dk1'".mysql_error());
$dk1=mysql_fetch_array($result_dk1);

	$query_dk1_a = "SELECT SUM(`delivery_paid`) FROM rent_sub_deals_arch WHERE  del_payment_type='nal_cheque' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
	$result_dk1_a = mysql_query($query_dk1_a);
	if (!$result_dk1_a) die("Сбой при доступе к базе данных: '$query_dk1_a'".mysql_error());
	$dk1_a=mysql_fetch_array($result_dk1_a);


$query_dcard = "SELECT SUM(`delivery_paid`) FROM rent_sub_deals_act WHERE  del_payment_type='card' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
$result_dcard = mysql_query($query_dcard);
if (!$result_dcard) die("Сбой при доступе к базе данных: '$query_dcard'".mysql_error());
$dcard=mysql_fetch_array($result_dcard);

	$query_dcard_a = "SELECT SUM(`delivery_paid`) FROM rent_sub_deals_arch WHERE  del_payment_type='card' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
	$result_dcard_a = mysql_query($query_dcard_a);
	if (!$result_dcard_a) die("Сбой при доступе к базе данных: '$query_dcard_a'".mysql_error());
	$dcard_a=mysql_fetch_array($result_dcard_a);

$query_dk2 = "SELECT SUM(`delivery_paid`) FROM rent_sub_deals_act WHERE  del_payment_type='nal_no_cheque' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
$result_dk2 = mysql_query($query_dk2);
if (!$result_dk2) die("Сбой при доступе к базе данных: '$query_dk2'".mysql_error());
$dk2=mysql_fetch_array($result_dk2);

	$query_dk2_a = "SELECT SUM(`delivery_paid`) FROM rent_sub_deals_arch WHERE  del_payment_type='nal_no_cheque' AND courier_id='".$curs2['logpass_id']."' AND $sort_date";
	$result_dk2_a = mysql_query($query_dk2_a);
	if (!$result_dk2_a) die("Сбой при доступе к базе данных: '$query_dk2_a'".mysql_error());
	$dk2_a=mysql_fetch_array($result_dk2_a);


$rtp_pr=($rtp['SUM(`r_to_pay`)']+$rtp_a['SUM(`r_to_pay`)']);
$k1_pr=($k1['SUM(`r_paid`)']+$k1_a['SUM(`r_paid`)']);
$k2_pr=($k2['SUM(`r_paid`)']+$k2_a['SUM(`r_paid`)']);
$card_pr=($card['SUM(`r_paid`)']+$card_a['SUM(`r_paid`)']);

$dtp_pr=($dtp['SUM(`delivery_to_pay`)']+$dtp_a['SUM(`delivery_to_pay`)']);
$dk1_pr=($dk1['SUM(`delivery_paid`)']+$dk1_a['SUM(`delivery_paid`)']);
$dk2_pr=($dk2['SUM(`delivery_paid`)']+$dk2_a['SUM(`delivery_paid`)']);
$dcard_pr=($dcard['SUM(`delivery_paid`)']+$dcard_a['SUM(`delivery_paid`)']);

$total=($k1['SUM(`r_paid`)']+$k1_a['SUM(`r_paid`)'])+($card['SUM(`r_paid`)']+$card_a['SUM(`r_paid`)'])+($k2['SUM(`r_paid`)']+$k2_a['SUM(`r_paid`)']);
$dtotal=($dk1['SUM(`delivery_paid`)']+$dk1_a['SUM(`delivery_paid`)'])+($dcard['SUM(`delivery_paid`)']+$dcard_a['SUM(`delivery_paid`)'])+($dk2['SUM(`delivery_paid`)']+$dk2_a['SUM(`delivery_paid`)']);


//подсчет значений для итогов
$rtp_t+=$rtp_pr;
$k1_t+=$k1_pr;
$k2_t+=$k2_pr;
$card_t+=$card_pr;
$total_t+=$total;

$dtp_t+=$dtp_pr;
$dk1_t+=$dk1_pr;
$dk2_t+=$dk2_pr;
$dcard_t+=$dcard_pr;
$dtotal_t+=$dtotal;

if ($total==0 && $dtotal==0) {continue;}


echo '
<tr style="text-align:right">
	<td style="text-align:left" rowspan="3">'.$curs2['lp_fio'].'</td>
	<td style="text-align:left">сделки</td>
	<td>'.number_format($rtp_pr, 2, ',', ' ').'</td>
	<td>'.number_format($k1_pr, 2, ',', ' ').'</td>
	<td>'.number_format($k2_pr, 2, ',', ' ').'</td>
	<td>'.number_format($card_pr, 2, ',', ' ').'</td>
	<td>'.number_format($total, 2, ',', ' ').'</td>
</tr>

				';

echo '
<tr style="text-align:right; font-style:italic;">

	<td style="text-align:left">выезды</td>
	<td>'.number_format($dtp_pr, 2, ',', ' ').'</td>
	<td>'.number_format($dk1_pr, 2, ',', ' ').'</td>
	<td>'.number_format($dk2_pr, 2, ',', ' ').'</td>
	<td>'.number_format($dcard_pr, 2, ',', ' ').'</td>
	<td>'.number_format($dtotal, 2, ',', ' ').'</td>
</tr>

				';

echo '
<tr style="font-weight:bold; text-align:right">

	<td style="text-align:left">всего</td>
	<td>'.number_format((($dtp['SUM(`delivery_to_pay`)']+$dtp_a['SUM(`delivery_to_pay`)'])+($rtp['SUM(`r_to_pay`)']+$rtp_a['SUM(`r_to_pay`)'])), 2, ',', ' ').'</td>
	<td>'.number_format((($dk1['SUM(`delivery_paid`)']+$dk1_a['SUM(`delivery_paid`)'])+($k1['SUM(`r_paid`)']+$k1_a['SUM(`r_paid`)'])), 2, ',', ' ').'</td>
	<td>'.number_format((($dk2['SUM(`delivery_paid`)']+$dk2_a['SUM(`delivery_paid`)'])+($k2['SUM(`r_paid`)']+$k2_a['SUM(`r_paid`)'])), 2, ',', ' ').'</td>
	<td>'.number_format((($dcard['SUM(`delivery_paid`)']+$dcard_a['SUM(`delivery_paid`)'])+($card['SUM(`r_paid`)']+$card_a['SUM(`r_paid`)'])), 2, ',', ' ').'</td>
	<td>'.number_format(($dtotal+$total), 2, ',', ' ').'</td>
</tr>

				';


}//end of while

echo'
<tr style="text-align:right; background-color:yellow;">
	<td rowspan="3" style="text-align:left;">Итого</td>
	<td style="text-align:left">сделки</td>
	<td>'.number_format($rtp_t, 2, ',', ' ').'</td>
	<td>'.number_format($k1_t, 2, ',', ' ').'</td>
	<td>'.number_format($k2_t, 2, ',', ' ').'</td>
	<td>'.number_format($card_t, 2, ',', ' ').'</td>
	<td>'.number_format($total_t, 2, ',', ' ').'</td>
</tr>
<tr style="text-align:right; font-style:italic; background-color:yellow;">

	<td style="text-align:left">выезды</td>
	<td>'.number_format($dtp_t, 2, ',', ' ').'</td>
	<td>'.number_format($dk1_t, 2, ',', ' ').'</td>
	<td>'.number_format($dk2_t, 2, ',', ' ').'</td>
	<td>'.number_format($dcard_t, 2, ',', ' ').'</td>
	<td>'.number_format($dtotal_t, 2, ',', ' ').'</td>
</tr>

<tr style="text-align:right; background-color:yellow; font-weight:bold;">

	<td style="text-align:left">всего</td>
	<td>'.number_format(($dtp_t+$rtp_t), 2, ',', ' ').'</td>
	<td>'.number_format(($dk1_t+$k1_t), 2, ',', ' ').'</td>
	<td>'.number_format(($dk2_t+$k2_t), 2, ',', ' ').'</td>
	<td>'.number_format(($dcard_t+$card_t), 2, ',', ' ').'</td>
	<td>'.number_format(($dtotal_t+$total_t), 2, ',', ' ').'</td>
</tr>
						';

echo '</table>';

}//end of period_no_start if

echo '<table border="1" cellspacing="0" style="table-layout:fixed">
		<tr>
			<th scope="col" style="width:70px;">с/дата выезда</th>
			<th scope="col" style="width:160px;">клиент/телефон</th>
			<th scope="col" style="width:120px;">адрес</th>
			<th scope="col" style="width:160px;">товар</th>
			<th scope="col" style="width:100px;">операция</th>
			<th scope="col" style="width:50px;">к оплате</th>
			<th scope="col" style="width:50px;">оплачено</th>
			<th scope="col" style="width:50px;">курьер<br />'.$curs_select.'</th>
			<th scope="col" style="width:10px;">информация</th>
			<th scope="col" style="width:50px;">принял</th>
			<th scope="col">действия</th>
		</tr>';

while ($sub_dl_def=mysql_fetch_array($result_sub_dl_def)) {

			//если включен фильтр курьеров и нет соответствия - не обрабатываем операцию
			if ($cur_id!=0 AND $sub_dl_def['courier_id']!=$cur_id) {
				continue;
			}

			//запрос информации о сделке
			$query_dl_def = "SELECT * FROM rent_deals_act WHERE deal_id='".$sub_dl_def['deal_id']."'";
			$result_dl_def = mysql_query($query_dl_def);
			if (!$result_dl_def) die("Сбой при доступе к базе данных: '$query_dl_def'".mysql_error());
			$dl_rows=mysql_num_rows($result_dl_def);
			$dl_def=mysql_fetch_array($result_dl_def);

			if ($dl_rows<1) {

				$query_dl_def = "SELECT * FROM rent_deals_arch WHERE deal_id='".$sub_dl_def['deal_id']."'";
				$result_dl_def = mysql_query($query_dl_def);
				if (!$result_dl_def) die("Сбой при доступе к базе данных: '$query_dl_def'".mysql_error());
				$dl_def=mysql_fetch_array($result_dl_def);
			}



			//запрос актуальной информации о клиенте
			$query_cl_def = "SELECT * FROM clients WHERE client_id='".$dl_def['client_id']."'";
			$result_cl_def = mysql_query($query_cl_def);
			if (!$result_cl_def) die("Сбой при доступе к базе данных: '$query_cl_def'".mysql_error());
			$cl_def=mysql_fetch_array($result_cl_def);


			//запрос информации о товаре по инв. номеру
			$query_item_def = "SELECT * FROM tovar_rent_items WHERE item_inv_n='".$dl_def['item_inv_n']."'";
			$result_item_def = mysql_query($query_item_def);
			if (!$result_item_def) die("Сбой при доступе к базе данных: '$query_item_def'".mysql_error());
			$item_def=mysql_fetch_array($result_item_def);

			$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$item_def['model_id']."'";
			$result_model_def = mysql_query($query_model_def);
			if (!$result_model_def) die("Сбой при доступе к базе данных: '$query_model_def'".mysql_error());
			$model_def=mysql_fetch_array($result_model_def);


			//запрос информации о категории товара
			$query_cat_def = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='".$model_def['tovar_rent_cat_id']."'";
			$result_cat_def = mysql_query($query_cat_def);
			if (!$result_cat_def) die("Сбой при доступе к базе данных: '$query_cat_def'".mysql_error());
			$cat_def=mysql_fetch_array($result_cat_def);

			$model_def['color'] == 0 ? $color='' : $color=', цвет: '.$model_def['color'];

			//запрос информации о курьере
			if ($sub_dl_def['courier_id']>0) {
				$query_cur = "SELECT * FROM logpass WHERE logpass_id='".$sub_dl_def['courier_id']."'";
				$result_cur = mysql_query($query_cur);
				if (!$result_cur) die("Сбой при доступе к базе данных: '$query_cur'".mysql_error());
				$cur=mysql_fetch_array($result_cur);
				$cur_name=$cur['lp_fio'];
			}
			else {
				$cur_name='';
			}

			//запрос информации о платежах
			$query_sub_pay = "SELECT * FROM rent_sub_deals_act WHERE `link`='".$sub_dl_def['sub_deal_id']."' AND `type`='payment' AND $sort_date";
			$result_sub_pay = mysql_query($query_sub_pay);
			if (!$result_sub_pay) die("Сбой при доступе к базе данных: '$query_sub_pay'".mysql_error());
			$pay_rows=mysql_num_rows($result_sub_pay);

			if ($pay_rows<1) {

				$query_sub_pay = "SELECT * FROM rent_sub_deals_arch WHERE `link`='".$sub_dl_def['sub_deal_id']."' AND `type` IN ('payment', 'cl_payment') AND $sort_date";
				$result_sub_pay = mysql_query($query_sub_pay);
				if (!$result_sub_pay) die("Сбой при доступе к базе данных: '$query_sub_pay'".mysql_error());
				$pay_rows=mysql_num_rows($result_sub_pay);
			}

			$pay_amount=0;
			$del_pay_amount=0;
			$r_pay_text='';
			$d_pay_text='';
			if ($pay_rows>=1) {
				while ($pay=mysql_fetch_array($result_sub_pay)) {
					$r_pay_text.=number_format($pay['r_paid'], 2, ',', ' ').sh_kassa($pay['r_payment_type']).'<br />';
					$d_pay_text.='<i>'.number_format($pay['delivery_paid'], 2, ',', ' ').sh_kassa($pay['del_payment_type']).'</i><br />';
				}

				$k_text='>1';;
			}


	echo'
		<tr ';

	if ($sub_dl_def['status']=='for_cur') {

		echo 'style="background-color:#80C4F0"';
	}
	elseif ($sub_dl_def['status']=='delivered') {

		echo 'style="background-color:#E7FEFD"';
	}
		//https://maps.google.com/?q=1200 Пенсильвания Авеню С.Е., Вашингтон, округ Колумбия, 20003
    $kl_addr=$cl_def['city'].', '.$cl_def['str'].' '.$cl_def['dom'];
	$kl_addr_url='https://google.com/maps/search/?api=1&query='.urldecode($kl_addr);
		echo '>
			<td>'.($sub_dl_def['type']=='extention' ? date("d.m.Y",$sub_dl_def['acc_date']) : date("d.m.Y",$sub_dl_def['from'])).($_SESSION['user_id']==3 ? '<br />dl:'.$sub_dl_def['deal_id'] : '').'</td>
			<td>'.$cl_def['family'].' '.$cl_def['name'].' '.$cl_def['otch'].'<br /><a href="tel:+375'.$cl_def['phone_1'].'">'.phone_print($cl_def['phone_1']).'</a><br /> <a href="tel:+375'.$cl_def['phone_2'].'">'.phone_print($cl_def['phone_2']).'</a></td>
			<td><a href="'.$kl_addr_url.'" target=_blank">'.$cl_def['city'].', '.$cl_def['str'].' '.$cl_def['dom'].'-'.$cl_def['kv'].'</a></td>
			<td> <strong>№'.inv_print($dl_def['item_inv_n']).'['.$sub_dl_def['place'].']'.'</strong><br />'.$cat_def['dog_name'].' '.$model_def['model'].', '.$model_def['producer'].$color.'</td>
			<td>'.print_operation($sub_dl_def['type']).'</td>
			<td style="text-align:right">'.number_format($sub_dl_def['r_to_pay'], 2, ',', ' ').' <br /><span class="deliv_num"> '.number_format($sub_dl_def['delivery_to_pay'], 2, ',', ' ').'</span></td>
			<td style="text-align:right">'.$r_pay_text.$d_pay_text.'</td>
			<td>'.$cur_name.'</td>
			<td>'.$sub_dl_def['info'].'</td>
			<td>'.user_name($sub_dl_def['cr_who_id']).'<br />'.date("d.m", $sub_dl_def['cr_time']).'<br /><i>'.date("(H:i)", $sub_dl_def['cr_time']).'</i></td>
			<td '.($_SESSION['level']==-1 ? 'style="display:none;"' : '').'> <div style="position:relative;" id="post_div_'.$sub_dl_def['sub_deal_id'].'"></div>
				<input type="hidden" name="one_sub_dl_id" id="one_sub_dl_id_old_'.$sub_dl_def['sub_deal_id'].'" value="'.$one_sub_dl_id.'" />';
			if ($sub_dl_def['status']=='for_cur') {
				echo'<input type="button" value="оформить (выезд)" onclick="chose_item(\'viezd\', \''.$sub_dl_def['sub_deal_id'].'\');" style="display:inline-block" />';
			}

		if ($dl_rows>=1) {

			echo'
				<form name="main" method="post" action="dogovor_new.php" style="display:inline-block">
					<input type="hidden" name="active_deal_id" value="'.$dl_def['deal_id'].'" />
					<input type="submit" name="action" id="action_print" value="распечатать договор" />
				</form>

				<form method="post" action="dogovor_new.php">
					<input type="hidden" name="item_inv_n" value="'.$dl_def['item_inv_n'].'" />
					<input type="hidden" name="client_id" value="'.$dl_def['client_id'].'" />
					<input type="submit" value="к договору" />
				</form>';
		}
		else {
			echo'
					<form method="post" action="deals_arch.php">
					<input type="hidden" name="deal_id" value="'.$dl_def['deal_id'].'" />
					<input type="submit" name="action" value="в архив" />
					</form>';
		}





		echo'
					</td>

		</tr>

			';
}




echo '</table>';


function phone_print ($ph) {
	if ($ph=='') {return '';}

	$dl=strlen($ph);

	if ($dl<7) {return $ph;}

	$dl>7 ? $dl_to=$dl-7 : $dl_to=0;
	$ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
	return $ph_out;

}



function get_post($var)
{

	return mysql_real_escape_string($_POST[$var]);
}


function tonum ($value) {

	$output=floatval(str_replace(',','.',$value));
	return $output;

}

function phone_to_n ($ph) {
	$ph=preg_replace("|[^0-9]|i", "", $ph);
	return $ph;
}

function print_operation ($op) {

	switch ($op) {
		case 'first_rent':
			return 'выдача';
			break;

		case 'extention':
			return 'забор платы за продление';
			break;

		case 'cur_return':
		case 'close':
			return 'возврат товара';
			break;

		default:
			return $op;
			break;
	}

}

function inv_print ($inv_n) {

	$output=substr($inv_n, 0, 3).'-'.substr($inv_n, 3);

	return $output;

}

function status_print ($status) {

	switch ($status) {
		case 'for_cur':
			return 'к доставке';
			break;

		case 'delivered':
			return 'доставлено';
			break;

		default:
			return $status;
			break;
	}

}

function sh_kassa ($kassa) {
	switch ($kassa) {
		case 'nal_no_cheque':
			return 'к2';
			break;

		case 'nal_cheque':
			return 'к1';
			break;

		case 'card':
			return 'кт';
			break;

		case 'bank':
			return 'бк';
			break;

		case '':
		case '0':
		case 'no_payment':
			return '';
			break;

		default:
			return 'ХЗК';
			break;
	}
}

function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
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
			return 'Света';
			break;
		case '11':
			return 'Артем';
			break;
		case '12':
			return 'Алексей';
			break;
        case '13':
            return 'Татьяна';
            break;
        case '16':
            return 'Любовь Алексеевна';
            break;
        case '22':
            return 'Катя';
            break;
        case '24':
            return 'Марина';
            break;
        case '25':
            return 'Кристина 2';
            break;
        case '26':
            return 'Юля';
            break;
        case '28':
            return 'Алена';
            break;
        case '30':
            return 'Маргарита';
            break;
        case '33':
            return 'Ульяна';
            break;
        case '34':
            return 'Маша';
            break;
		default:
			return 'ХЗ'.$id;
			break;
	}
}


?>
