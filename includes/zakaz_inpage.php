<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database.php'); // включаем подключение к базе данных
?>
<script language="javascript">


function show_mbr (q_type, model_id) {
		
	if (document.getElementById('br_day').value=='') {
		alert ('Пожалуйста, заполните день для брони (число).');
		return false;
	}
	
	var br_y_m=document.getElementById('br_y_m').value;
	var br_d=parseInt(document.getElementById('br_day').value);
	
	if (isNaN(br_d)) {
		alert ('В качестве даты введено НЕ число!');
		return false;
	}
	
	var today_temp=new Date();
		var today_d=new Date(today_temp.getFullYear(), today_temp.getMonth(), today_temp.getDate());
	
	var br_date=br_y_m+'-'+br_d;
	var br_date_ch= new Date(br_date);
	
	if (br_date_ch<today_d) {
			
			alert ('Дата брони не может быть в прошлом!');
			return false;
		}
	
	
	document.getElementById('br_mp_' + model_id).innerHTML='<img src="/includes/loading.gif" /><br /> идет запрос информации ...';

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/includes/zakaz2.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	
		var params = 'model_id=' + encodeURIComponent(model_id) + '&q_type=' + encodeURIComponent(q_type) + '&br_date=' + encodeURIComponent(br_date);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
		    document.getElementById('br_mp_' + model_id).innerHTML=xmlhttp.responseText;
	           }
	  		}
		}
}//end of function


function show_mbr2 (q_type, model_id, zzz) {
if (zzz=='1') {
	valid = true;

	size_ch=document.getElementById('size_'+model_id).value;

		if (size_ch==0) {
			valid = false;
			alert ('Выберите размер костюма!');
		}
	
	
	var br_date=document.getElementById('year_'+model_id).value + '-' + document.getElementById('month_'+model_id).value + '-' + document.getElementById('day_'+model_id).value; 
	var inv_n=document.getElementById('size_'+model_id).value;
	
	
		var today_temp=new Date();
		var today_d=new Date(today_temp.getFullYear(), today_temp.getMonth(), today_temp.getDate());
		
		var br_date_ch=new Date(br_date);
	
		if (br_date_ch<today_d) {
			valid = false;
			alert ('Дата брони не должна быть в прошлом!');
		}
	
		if (valid==false) {
			return valid;
		}	
}
else {
	var br_date=document.getElementById('x_date').value;
	var inv_n=document.getElementById('size_inv_n').value;
}

	document.getElementById('br_mp_' + model_id).innerHTML='<img src="/includes/loading.gif" /><br /> идет запрос информации ...';
	
	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/includes/zakaz2.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	
		var params = 'model_id=' + encodeURIComponent(model_id) + '&q_type=' + encodeURIComponent(q_type) + '&br_date=' + encodeURIComponent(br_date) + '&inv_n=' + encodeURIComponent(inv_n);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
		    document.getElementById('br_mp_' + model_id).innerHTML=xmlhttp.responseText;
	           }
	  		}
		}
}//end of function


function br_close (model_id) {
	document.getElementById('br_mp_' + model_id).innerHTML='';
}


function show_a (q_type, inv_n, t_from, t_to, model_id) {
	
	prev_br_id=document.getElementById('last_temp_br_id').value;
	prev_inv_n=document.getElementById('last_inv_n_br').value;
	
	if (prev_inv_n!='' && inv_n==prev_inv_n) {
		return false;
	}
	if (prev_inv_n!='' && inv_n!=prev_inv_n) {
		bron_cans('bron_cans', prev_inv_n, prev_br_id, model_id, '3');
	}
	 
	document.getElementById('br_info_div_' + inv_n).innerHTML='<img src="/includes/loading.gif" /><br /> идет запрос информации ...';
	
	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/includes/zakaz2.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	
		var params = '&q_type=' + encodeURIComponent(q_type) + '&inv_n=' + encodeURIComponent(inv_n) + '&t_from=' + encodeURIComponent(t_from) + '&t_to=' + encodeURIComponent(t_to) + '&model_id=' + encodeURIComponent(model_id);

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
	    	//alert (xmlhttp.responseText);
			eval (xmlhttp.responseText);
	           }
	  		}
		}
}//end of function


function bron_ok (q_type, inv_n, model_id) {
	
	var br_from_temp=new Date(document.getElementById('br_date_from_'+inv_n).value);
	var br_from=new Date(br_from_temp.getFullYear(), br_from_temp.getMonth(), br_from_temp.getDate(), document.getElementById('br_hour_from_'+inv_n).value);
	
	var br_to_temp=new Date(document.getElementById('br_date_to_'+inv_n).value);
	var br_to=new Date(br_to_temp.getFullYear(), br_to_temp.getMonth(), br_to_temp.getDate(), document.getElementById('br_hour_to_'+inv_n).value);	
	
	bron_date_from=document.getElementById('br_date_from_'+inv_n).value;
	bron_hour_from=document.getElementById('br_hour_from_'+inv_n).value;
	bron_date_to=document.getElementById('br_date_to_'+inv_n).value;
	bron_hour_to=document.getElementById('br_hour_to_'+inv_n).value;
	bron_fio=document.getElementById('br_fio_'+inv_n).value;
	bron_phone1=document.getElementById('br_phone1_'+inv_n).value;
	bron_phone2=document.getElementById('br_phone2_'+inv_n).value;
	bron_mail=document.getElementById('br_mail_'+inv_n).value;
	bron_info=document.getElementById('br_text_'+inv_n).value;
	bron_temp_id=document.getElementById('br_temp_id_'+inv_n).value;
	
	valid=true;
	
	if (bron_date_from==0 || bron_hour_from==0) {
			valid = false;
			alert ('Выберите дату И время сдачи!');
		}
	
	if (bron_date_to==0 || bron_hour_to==0) {
			valid = false;
			alert ('Выберите дату И время возврата!');
		}
	
	if (bron_fio==0 || bron_phone1==0) {
			valid = false;
			alert ('Заполните ФИО И первый телефон!');
		}
	
	if (valid==false) {
		return valid;
	}
	
	if((br_to.getTime()-br_from.getTime())/1000<(6*3600)) {
		alert ('Период брони должен быть не менее 6 часов!');
		return false;
	}
	
	document.getElementById('br_info_div_' + inv_n).innerHTML='<img src="/includes/loading.gif" /><br /> ... сохраняем бронь ...';
	
	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/includes/zakaz2.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	
		var params = '&q_type=' + encodeURIComponent(q_type) + '&inv_n=' + encodeURIComponent(inv_n) + '&bron_date_from=' + encodeURIComponent(bron_date_from) + '&bron_hour_from=' + encodeURIComponent(bron_hour_from) + '&bron_date_to=' + encodeURIComponent(bron_date_to) + '&bron_hour_to=' + encodeURIComponent(bron_hour_to) + '&bron_fio=' + encodeURIComponent(bron_fio) + '&bron_phone1=' + encodeURIComponent(bron_phone1) + '&bron_phone2=' + encodeURIComponent(bron_phone2) + '&bron_mail=' + encodeURIComponent(bron_mail) + '&bron_temp_id=' + encodeURIComponent(bron_temp_id) + '&model_id=' + encodeURIComponent(model_id) + '&bron_info=' + encodeURIComponent(bron_info);
	
	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
	    	//alert (xmlhttp.responseText);
			eval (xmlhttp.responseText);
	           }
	  		}
		}
	
	
}//end of function


function bron_cans (q_type, inv_n, br_id, model_id, vvv) {
	//vvv - варианты. Значение 1 - вывод обновленной информации о бронях. Значение 2 - убрать информацию о бронях. Значение 3 - ... 
	br_date=document.getElementById('x_date').value; 
	size_inv_n=document.getElementById('size_inv_n').value;
	
	document.getElementById('br_info_div_' + inv_n).innerHTML='<img src="/includes/loading.gif" /><br /> ... идет обновление информации о бронях ...';
		
	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/includes/zakaz2.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	
		var params = '&q_type=' + encodeURIComponent(q_type) + '&br_id=' + encodeURIComponent(br_id) + '&model_id=' + encodeURIComponent(model_id);
	
	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
	    	//alert (xmlhttp.responseText);
			//eval (xmlhttp.responseText);
			if (vvv=='1') {
			show_mbr2('karnaval_main', model_id, '2');
			}
			if (vvv=='2') {
				br_close (model_id);
			}
			if (vvv=='3') {
				document.getElementById('br_info_div_' + inv_n).innerHTML='';
			}
			
	           }
	  		}
		}
	
	
}//end of function


function general_cans (model_id) {
	if (document.getElementById('last_temp_br_id').value=='') {
		document.getElementById('br_mp_' + model_id).innerHTML='';
	}
	else {
		br_id=document.getElementById('last_temp_br_id').value;
		inv_n=document.getElementById('last_inv_n_br').value;
		
		bron_cans('bron_cans', inv_n, br_id, model_id, '2');
	}
}//end of function


function strtotime(ddd){
	return parseInt(new Date(ddd).getTime()/1000)
	}




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
}//end of getxmlhttp function


</script>

<?php 

function karnaval ($model_id) {
	
	$query_tarifs = "SELECT * FROM rent_tarif_act WHERE model_id='".$model_id."' ORDER BY sort_num, kol_vo";
	$result_tarifs = mysql_query($query_tarifs);
	if (!$result_tarifs) die("Сбой при доступе к базе данных: '$query_tarifs'".mysql_error());
	$tarif_rows = mysql_num_rows($result_tarifs);
	$tarif_def=mysql_fetch_array($result_tarifs);
	
	$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$model_id."'";
	$result_model_def = mysql_query($query_model_def);
	if (!$result_model_def) die("Сбой при доступе к базе данных: '$query_model_def'".mysql_error());
	$model_def=mysql_fetch_array($result_model_def);
	
	
	
	echo '
<div id="box_right_karnaval">
     <p class="bodysmall_karn">&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp<ins>Цена проката:</ins><br />
&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp'.number_format($tarif_def['rent_per_step'], 2, ',', ' ').' руб / сутки</p>
               <p class="bodysmall_karn">&nbsp&nbsp<ins>Залог:</ins> 
       30,0 руб</p>
     <p class="bodysmall_karn">&nbsp&nbsp<ins>Оценочная стоимость:</ins> <br />
       &nbsp&nbsp'.number_format($model_def['agr_price'], 0, ',', ' ').' у.е.</p>
     
<p class="bodysmall_karn"><br /><br />&nbsp&nbsp&nbsp&nbsp<ins>Принимаем бронь:</ins><br /> 
      &nbsp&nbsp&nbsp&nbspVelcom&nbsp&nbsp&nbsp    630-35-58<br />
      &nbsp&nbsp&nbsp&nbspМТС&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp735-35-95      <br />
      &nbsp&nbsp&nbsp&nbspВС-выходной
</p>
        <div class="zakaz_div" id="zakaz_div_'.$model_id.'">
            <a href="#" class="zakaz_a" onclick="show_form(\''.$model_id.'\', \'karnaval\'); return false;"><img src="img/price_karnaval.gif" /></a>
        </div>
        <div class="zakaz_pas_div" id="zakaz_pas_div_'.$model_id.'">
        	<a href="#" class="zakaz_a" onclick="show_pas(\''.$model_id.'\', \'pas\'); return false;">Внести данные для договора</a>
        </div>
    </div>
		';
	
}


function karn_info ($k_name, $model_id) {
	
	$query_tarifs = "SELECT * FROM rent_tarif_act WHERE model_id='".$model_id."' ORDER BY sort_num, kol_vo";
	$result_tarifs = mysql_query($query_tarifs);
	if (!$result_tarifs) die("Сбой при доступе к базе данных: '$query_tarifs'".mysql_error());
	$tarif_rows = mysql_num_rows($result_tarifs);
	$tarif_def=mysql_fetch_array($result_tarifs);
	
	$query_model_def = "SELECT * FROM tovar_rent WHERE tovar_rent_id='".$model_id."'";
	$result_model_def = mysql_query($query_model_def);
	if (!$result_model_def) die("Сбой при доступе к базе данных: '$query_model_def'".mysql_error());
	$model_def=mysql_fetch_array($result_model_def);
	
	$query_items = "SELECT * FROM tovar_rent_items WHERE model_id='".$model_id."' AND `status` IN ('to_rent', 'rented_out') AND `state`!=3 ORDER BY item_rost1";
	$result_items = mysql_query($query_items);
	if (!$result_items) die("Сбой при доступе к базе данных: '$query_items'".mysql_error());
	
	$rrs=' ';
	$prev_rost1='x';
	$prev_rost2='x';
	$count=0;
	while ($items=mysql_fetch_array($result_items)) {
		if ($items['item_rost1']==$prev_rost1 && $items['item_rost2']==$prev_rost2) { continue;} //если повтор, не выводим модель --> только уникальные
		$prev_rost1=$items['item_rost1'];
		$prev_rost2=$items['item_rost2'];
		$rrs.=($count==0 ? '' : ', ').$items['item_rost1'].'-'.$items['item_rost2'].' ('.$items['item_size'].')';
		$count+=1;
	}
	
	
	
	echo '
<table class="karnaval_table">
	<tbody>
	<tr>
		<td colspan="2" class="karnaval_1">'.$k_name.'</td>
	</tr>
	<tr>
		<td colspan="2" class="karnaval_2">Размеры:'.$rrs.'</td>
	</tr>
	<tr>
		<td class="karnaval_3">Тариф</td>
		<td class="karnaval_33">'.number_format($tarif_def['rent_amount'], 1, ',', ' ').' руб/сутки</td>
	</tr>
	<tr>
		<td class="karnaval_4">Залог</td>
		<td class="karnaval_44">30,00 рублей</td>
	</tr>
	<tr>
		<td colspan="2" class="karnaval_2">Оценочная стоимость: '.number_format($model_def['agr_price'], 0, ',', ' ').' у.е.</td>
	</tr>
	<tr>
		<td colspan="2" class="karnaval_6" style="margin:0; padding:0;" >
			<div style="position:relative; margin:0; padding:0;">
		<div style="position:absolute;" id="br_mp_'.$model_def['tovar_rent_id'].'"></div>
		<a href="#" onclick="show_mbr(\'first_step\', \''.$model_def['tovar_rent_id'].'\'); return false;" id="main_br_a"><img src="/includes/zakaz_button.jpg" alt="заказать" /></a>
		
	<input type="number" step="1" min="0" max="31" id="br_day" style="width:35px; height:15px; position:absolute; top:14px; left:10px;" />		
	<select name="" id="br_y_m" style="position:absolute; top:14px; left:55px; height:21px; width:90px;">
		'.m_plus(0).m_plus(1).m_plus(2).'
    </select>		
			
			</div></td>
	</tr>
	</tbody>
</table>
	';
}


/*
 <input type="button" onclick="show_mbr(\'karnaval_main\', \''.$model_def['tovar_rent_id'].'\'); return false;" value="забронировать" />
 */
function rus_month1 ($month) {
	$month=$month*1;

	switch ($month) {
		case '1':
			return 'января';
			break;

		case '2':
			return 'февраля';
			break;

		case '3':
			return 'марта';
			break;

		case '4':
			return 'апреля';
			break;

		case '5':
			return 'мая';
			break;

		case '6':
			return 'июня';
			break;

		case '7':
			return 'июля';
			break;

		case '8':
			return 'августа';
			break;


		case '9':
			return 'сентября';
			break;

		case '10':
			return 'октября';
			break;

		case '11':
			return 'ноября';
			break;

		case '12':
			return 'декабря';
			break;

		default:
			return 'Месяц не определен';
			break;
	}
}


function m_plus ($m_plus) {
$cur_y=date("Y");
$cur_m=date("m");

$new_m=$cur_m+$m_plus;
if ($new_m>12) {
	$new_y=$cur_y+1;
	$new_m=$new_m-12;
}
else {
	$new_y=$cur_y;
}

if ($new_m<10) {
	$new_m='0'.$new_m;
}

$output='<option value="'.$new_y.'-'.$new_m.'">'.rus_month1($new_m*1).'</option>';

return $output;

}//end of function


function m_plus2 ($m_plus, $cur_my) {
	$cur_y=date("Y");
	$cur_m=date("m");

	$new_m=$cur_m+$m_plus;
	if ($new_m>12) {
		$new_y=$cur_y+1;
		$new_m=$new_m-12;
	}
	else {
		$new_y=$cur_y;
	}

	if ($new_m<10) {
		$new_m='0'.$new_m;
	}

	$output='<option value="'.$new_y.'-'.$new_m.'" '.sel_d($cur_my, ($new_y.'-'.$new_m)).'>'.rus_month1($new_m*1).'</option>';

	return $output;

}//end of function



function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}


?>