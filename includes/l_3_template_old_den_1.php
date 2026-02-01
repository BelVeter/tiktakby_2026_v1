<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
// выбираем информацию о модели
$m_info = "SELECT * FROM rent_model_web WHERE model_id='$model_id' LIMIT 1";
$result_m_info = $mysqli->query($m_info);
if (!$result_m_info) {die('Сбой при доступе к базе данных: '.$m_info.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//$m_i_num=$result_m_info->num_rows;
$m_i=$result_m_info->fetch_assoc();

//выбираем доп. фото
$dop_p_q = "SELECT * FROM dop_photos WHERE model_id='$model_id' LIMIT 5";
$result_dop_p = $mysqli->query($dop_p_q);
if (!$result_dop_p) {die('Сбой при доступе к базе данных: '.$dop_p_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$dop_p_num=$result_dop_p->num_rows;

//выбираем информацию о модели из базы
$m_baza_q = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$model_id' LIMIT 1";
$result_m_baza = $mysqli->query($m_baza_q);
if (!$result_m_baza) {die('Сбой при доступе к базе данных: '.$m_baza_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//$m_i_num=$result_m_info->num_rows;
$m_baza=$result_m_baza->fetch_assoc();


//выбираем тарифы
$tarif_q = "SELECT * FROM rent_tarif_act WHERE model_id='$model_id' ORDER BY sort_num DESC, kol_vo DESC";
$result_tarif = $mysqli->query($tarif_q);
if (!$result_tarif) {die('Сбой при доступе к базе данных: '.$tarif_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$tar_num=$result_tarif->num_rows;
//echo 'тарифов:'.$tar_num;


function model_yn_of($id, $office) {
	global $mysqli;

	$free_q1 = "SELECT (item_id) FROM tovar_rent_items WHERE model_id='$id' AND (`status`='to_rent' OR (`status`='t_bron' AND br_time<".time().")) AND item_place='$office' LIMIT 1";
	$result_free1 = $mysqli->query($free_q1);
	if (!$result_free1) {die('Сбой при доступе к базе данных: '.$free_q1.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$free1=$result_free1->num_rows;

	if ($free1>0) {
		return 'available_line.png';
	}
	else {
		return 'notavailable_line.png';
	}


}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?php echo $m_i['title']; ?></title>
<link href="/tt.css" rel="stylesheet" type="text/css" />
<link href="/tiktak.ico" rel="shortcut icon" type="image/x-icon" />

<meta name="keywords" content="<?php echo $m_i['keywords']; ?>">



<!--Увеличение картинок-->
<link rel="stylesheet" type="text/css" href="/fancybox/jquery.fancybox.css">
<script type="text/javascript" src="/fancybox/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/fancybox/jquery.easing.1.3.js"></script>
<script type="text/javascript" src="/fancybox/jquery.fancybox-1.2.1.pack.js"></script>
<script type="text/javascript">
$(document).ready(function() { 
$("a.first").fancybox(); 
$("a.two").fancybox(); 
$("a.video").fancybox({"frameWidth":600,"frameHeight":400}); 
$("a.content").fancybox({"frameWidth":680,"frameHeight":300}); 
});
</script>

<script type="text/JavaScript" src="/js/jquery.slider.js"></script>

<script type="text/JavaScript">

function addr_hide (id) {
	
	if (document.getElementById(id+'_a_show').value==1) {
		return false;
	}
	
	document.getElementById(id).style.width=0;
	document.getElementById(id).style.height=0;
	document.getElementById(id).style.border='none';
}

function addr_show (id) {
	document.getElementById(id).style.width='347px';
	document.getElementById(id).style.height='180px';
	document.getElementById(id).style.border="3px solid #a0cbca";
	
}


function addr_a_show (id) {
	if (document.getElementById(id+'_a_show').value==0) {
		document.getElementById(id+'_a_show').value=1;
		addr_show(id);
	}
	else {
		document.getElementById(id+'_a_show').value=0;
		addr_hide(id);
	}
}



/*бронь*/
function br_zakr () {
	document.getElementById('bron_main_div').style.display="none";
}


function br_start (q_type, model_id) {
	
	document.getElementById('bron_main_div').style.display="block";
	
	document.getElementById('bron_main_div').innerHTML='<img src="/includes/loading.gif" />';
	
	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/includes/l_3_br.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	
	var params = 'q_type=' + encodeURIComponent(q_type) + '&model_id=' + encodeURIComponent(model_id);
	
	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
	        //alert (xmlhttp.responseText);
		    eval (xmlhttp.responseText);
	           }
	  		}
		}
}


function pered_tov (sposob) {
	if (sposob=="office") {
	
		document.getElementById('br_of_div').style.display="";
		document.getElementById('deliv_row').style.display="none";
		document.getElementById('sam_vivoz').style.display="";
		
			if (document.getElementById('br_of_radio_1')) {document.getElementById('br_of_radio_1').checked=false;}
			if (document.getElementById('br_of_radio_2')) {document.getElementById('br_of_radio_2').checked=false;}
	}
	if (sposob=="deliv") {
	
		document.getElementById('br_of_div').style.display="";
		document.getElementById('deliv_row').style.display="";
		document.getElementById('sam_vivoz').style.display="none";
		
				
			if (document.getElementById('br_of_radio_1')) {
				if (document.getElementById('br_of_radio_1')) {document.getElementById('br_of_radio_1').checked=true;}
			}
			else {
				if (document.getElementById('br_of_radio_2')) {document.getElementById('br_of_radio_2').checked=true;}
			}
		
	}			
}


function br_office (model_id) {
	
	var valid=true;
	var soobsch='';
	
	ch1=ch2=0;
	
	if (document.getElementById('br_of_radio_1')) {
		if (document.getElementById('br_of_radio_1').checked!=true) {
			ch1=0;
		}
		else {
			ch1=1;
		}
	}
	
	if (document.getElementById('br_of_radio_2')) {
		if (document.getElementById('br_of_radio_2').checked!=true) {
			ch2=0;
		}
		else {
			ch2=1;
		}
	}
	
	if ((ch1+ch2)<1) {
		valid=false;
		soobsch+='адрес для самовывоза, ';
	}
	
	if (document.getElementById('fio').value=='') {
		valid=false;
		soobsch+='ФИО, ';
	}
	
	if (document.getElementById('tel_pr').value=='') {
		valid=false;
		soobsch+='префикс телефона, ';
	}
	if (document.getElementById('tel').value=='') {
		valid=false;
		soobsch+='номер телефона, ';
	}
	
	if (valid==false) {
		alert ('Необходимо заполнить: '+soobsch);
	}
	else {
		q_type='sam_bron';
		
		if (document.getElementById('br_of_radio_1') && document.getElementById('br_of_radio_1').checked==true) {
			br_office=1;
		}
		else {			if (document.getElementById('br_of_radio_2') && document.getElementById('br_of_radio_2').checked==true) {
				br_office=2;							}			else {				br_office=0;			}
		}		
		
		
		if (document.getElementById('takeaway_br_1').checked==true) {
			br_sposob='office';
		}
		else {
			br_sposob='deliv';
		}
		
		
		fio=document.getElementById('fio').value;
		tel_pr=document.getElementById('tel_pr').value;
		tel=document.getElementById('tel').value;
		dop_info=document.getElementById('dop_info').value;
		deliv_addr=document.getElementById('deliv_addr').value;
		
		item_1_id=document.getElementById('item_1_id').value;
		item_2_id=document.getElementById('item_2_id').value;
		br_time=document.getElementById('br_time').value;
		
		
		document.getElementById('bron_main_div').innerHTML='<img src="/includes/loading.gif" />';
	
		var xmlhttp = getXmlHttp()
		xmlhttp.open("POST", '/includes/l_3_br.php', true)
		xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		
		var params = 'q_type=' + encodeURIComponent(q_type) + '&model_id=' + encodeURIComponent(model_id) + '&br_office=' + encodeURIComponent(br_office) + '&fio=' + encodeURIComponent(fio) + '&tel_pr=' + encodeURIComponent(tel_pr) + '&tel=' + encodeURIComponent(tel) + '&dop_info=' + encodeURIComponent(dop_info) + '&item_1_id=' + encodeURIComponent(item_1_id) + '&item_2_id=' + encodeURIComponent(item_2_id) + '&br_time=' + encodeURIComponent(br_time) + '&deliv_addr=' + encodeURIComponent(deliv_addr) + '&br_sposob=' + encodeURIComponent(br_sposob);
		
		xmlhttp.send(params);
		xmlhttp.onreadystatechange = function() {
		  if (xmlhttp.readyState == 4) {
		     if(xmlhttp.status == 200) {
		        //alert (xmlhttp.responseText);
			    
			   // document.getElementById('bron_main_div').innerHTML=xmlhttp.responseText;
			    eval (xmlhttp.responseText);
		           }
		  		}
			}
		
	}
}//end of main func br_of




function br_zayav (model_id) {
	
	var valid=true;
	var soobsch='';
	
	ch1=ch2=0;
	
		if (document.getElementById('br_tenor_1') && document.getElementById('br_tenor_1').checked!=true && document.getElementById('br_tenor_2').checked!=true && document.getElementById('br_tenor_3').checked!=true) {
			valid=false;
			soobsch+='срок действия заявки, ';
		}
		
	
	if (document.getElementById('fio').value=='') {
		valid=false;
		soobsch+='ФИО, ';
	}
	
	if (document.getElementById('tel_pr').value=='') {
		valid=false;
		soobsch+='префикс телефона, ';
	}
	if (document.getElementById('tel').value=='') {
		valid=false;
		soobsch+='номер телефона, ';
	}
	
	if (valid==false) {
		alert ('Необходимо заполнить: '+soobsch);
	}
	else {
		q_type='sam_zayav';
		
		if (document.getElementById('br_tenor_1').checked==true) {
			zayav_tenor=1;
		}
		if (document.getElementById('br_tenor_2').checked==true) {
			zayav_tenor=2;
		}
		if (document.getElementById('br_tenor_3').checked==true) {
			zayav_tenor=3;
		}		
		
		fio=document.getElementById('fio').value;
		tel_pr=document.getElementById('tel_pr').value;
		tel=document.getElementById('tel').value;
		dop_info=document.getElementById('dop_info').value;
		
		document.getElementById('bron_main_div').innerHTML='<img src="/includes/loading.gif" />';
	
		var xmlhttp = getXmlHttp()
		xmlhttp.open("POST", '/includes/l_3_br.php', true)
		xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		
		var params = 'q_type=' + encodeURIComponent(q_type) + '&model_id=' + encodeURIComponent(model_id) + '&zayav_tenor=' + encodeURIComponent(zayav_tenor) + '&fio=' + encodeURIComponent(fio) + '&tel_pr=' + encodeURIComponent(tel_pr) + '&tel=' + encodeURIComponent(tel) + '&dop_info=' + encodeURIComponent(dop_info);
		
		xmlhttp.send(params);
		xmlhttp.onreadystatechange = function() {
		  if (xmlhttp.readyState == 4) {
		     if(xmlhttp.status == 200) {
		        //alert (xmlhttp.responseText);
			    
			   // document.getElementById('bron_main_div').innerHTML=xmlhttp.responseText;
			    eval (xmlhttp.responseText);
		           }
		  		}
			}
		
	}
}//end of main func




function br_cans () {

		q_type='br_cans';
	
		item_1_id=document.getElementById('item_1_id').value;
		item_2_id=document.getElementById('item_2_id').value;
		br_time=document.getElementById('br_time').value;
		
		
		document.getElementById('bron_main_div').innerHTML='<img src="/includes/loading.gif" />';
	
		var xmlhttp = getXmlHttp()
		xmlhttp.open("POST", '/includes/l_3_br.php', true)
		xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		
		var params = 'q_type=' + encodeURIComponent(q_type) + '&item_1_id=' + encodeURIComponent(item_1_id) + '&item_2_id=' + encodeURIComponent(item_2_id) + '&br_time=' + encodeURIComponent(br_time);
		
		xmlhttp.send(params);
		xmlhttp.onreadystatechange = function() {
		  if (xmlhttp.readyState == 4) {
		     if(xmlhttp.status == 200) {
		        //alert (xmlhttp.responseText);
			    
			   // document.getElementById('bron_main_div').innerHTML=xmlhttp.responseText;
			    eval (xmlhttp.responseText);
		           }
		  		}
			}
}//end of main func




</script>




</head>


<body>

<div id="container">

<!--Включаем шапку и верхнее меню-->

<?php include_once ($_SERVER['DOCUMENT_ROOT'].'/includes/header.html'); ?>

<?php include_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zakaz_inpage.php'); ?>

    
<div id="katalog">
  <div id="katalog_left"><img src="/images/katalog_11.gif" width="200" height="70" /></div>
<div id="katalog_right">
<!--Хлебные крошки-->
<span style="font-family: verdana, sens-serif; font-size: 12px; color: #00a0d0; font-style: italic;"><?php echo $m_i['web_way']; ?></span>
<div class="kristy_1"><?php echo $m_i['item_name_main']; ?></div>
 </div>
 </div>
   
<!--Включаем левое меню -->
<?php include_once ($_SERVER['DOCUMENT_ROOT'].'/includes/left_menu.html'); ?>


  <div id="main">
  
<div id="dm_box">
   <div id="box_left"><a class="first" title="<?php echo $m_i['m_a_title']; ?>" href="<?php echo $m_i['m_pic_big']; ?>"><img src="<?php echo $m_i['m_pic_small']; ?>" alt="<?php echo $m_i['m_pic_alt']; ?>"></a></div>
 
<div id="box_right_new">
		<div class="dima_1"><img src="<?php echo $m_i['logo']; ?>" /></div>
     
<table class="tarifs_table_new">
	<tr class="t_h_r">
    	<td class="t_h_1">Период<br />проката</td>
        <td class="t_h_2">Тариф<br />руб/нед</td>
        <td class="t_h_3">Стоимость<br />руб.</td>
    </tr>
<?php

$tar_line=0;
while ($tar=$result_tarif->fetch_assoc()) {
	$tar_line+=1;
	echo '
	<tr>
		<td class="'.($tar_line!=$tar_num ? 't_l_1' : 't_el_1').'" '.($tar_line==1 ? 'style="padding:5px 10px 0 10px;"' : '').'>'.number_format($tar['kol_vo'], 0, ',', ' ').' '.mwd_pr($tar['step'], $tar['kol_vo']).'</td>
        <td class="'.($tar_line!=$tar_num ? 't_l_2' : 't_el_2').'" '.($tar_line==1 ? 'style="padding:5px 10px 0 10px;"' : '').'>'.($tar['step']=='month' ? number_format($tar['rent_per_step']/4, 2, ',', ' ') : number_format($tar['rent_per_step'], 2, ',', ' ')).'</td>
        <td class="'.($tar_line!=$tar_num ? 't_l_3' : 't_el_3').'" '.($tar_line==1 ? 'style="padding:5px 10px 0 10px;"' : '').'>'.number_format($tar['rent_amount'], 2, ',', ' ').'</td>    
    </tr>	
			';
}

$theof1=model_yn_of($model_id, '1');
$theof2=model_yn_of($model_id, '2');

if ($theof1=='notavailable_line.png' && $theof2=='notavailable_line.png') {
	$br_but_text='ОФОРМИТЬ ЗАЯВКУ';
}
else {
	$br_but_text='ЗАБРОНИРОВАТЬ';
}

?>
    
</table>
       <div class="dima_1" id="ots_st_t">Оценочная стоимость: <?php echo number_format($m_baza['agr_price'], 0, ',', ' '); ?> у.е.</div>
       <button class="br_but_big" onclick="br_start('br_start', '<?php echo $model_id; ?>'); return false;" /><?php echo $br_but_text; ?></button>
       <input type="hidden" id="model_id" value="<?php echo $model_id; ?>" />
       <div id="bron_main_div"></div>
    </div> 
    
</div>

<a href="#" class="small_line_1" onclick="addr_a_show('addr_1'); return false;" onmouseenter="addr_show('addr_1'); return false;" onmouseleave="addr_hide('addr_1'); return false;">Адрес пункта проката №1:<br /><strong>пр-т Машерова 20</strong><img class="yesnoline" src="/images/<?php echo $theof1; ?>" /><img class="arrow_d" src="/images/arrow_down.png" /></a>
<a href="#" class="small_line_2" onclick="addr_a_show('addr_2'); return false;" onmouseenter="addr_show('addr_2'); return false;" onmouseleave="addr_hide('addr_2'); return false;">Адрес пункта проката №2:<br /><strong>ул. Ложинская 5</strong><img class="yesnoline" src="/images/<?php echo $theof2; ?>" /><img class="arrow_d" src="/images/arrow_down.png" /></a>

<div class="addr_1" id="addr_1">
<input type="button" class="addr_close" value="X" onclick="addr_a_show('addr_1'); return false;" />
<div class="addr_w_hours">
	<strong><u>&nbspЧасы работы:</u></strong><br /><br /><input type="hidden" id="addr_1_a_show" value="0" />
	<table border="0">
		<tr class="addr_t_row">
			<td style="width:65px;">Пн-пт:</td>
			<td>10<sup>00</sup> - 20<sup>00</sup></td>
		</tr>
		<tr class="addr_t_row">
			<td>Сб:</td>
			<td>10<sup>00</sup> - 18<sup>00</sup></td>
		</tr>
		<tr class="addr_t_row">
			<td>Вс:</td>
			<td>выходной</td>
		</tr>
	</table> 
	</div>
	
<div class="addr_phones">
	<strong><u>&nbspТелефоны:</u></strong><br /><br />
	<table border="0">
		<tr class="addr_t_row">
			<td style="width:30px; text-align:center;"><img src="/images/velcom.png" style="width:18px; height:18px;" /></td>
			<td>(29) 630-35-32</td>
		</tr>
		<tr class="addr_t_row">
			<td style="text-align:center;"><img src="/images/mts.png" style="width:18px; height:18px;" /></td>
			<td>(29) 730-35-34</td>
		</tr>
		<tr class="addr_t_row">
			<td style="text-align:center;"><img src="/images/gorod.png" style="width:18px; height:18px;" /></td>
			<td>(17) 286-33-38</td>
		</tr>
	</table> 
	
	</div>
<div class="addr_text">Для заказа доставки данного товара или бронирования с последующим самовывозом воспользуйтесь кнопкой «ЗАБРОНИРОВАТЬ»</div>


</div><!-- end of addr_1 -->


<div class="addr_2" id="addr_2">
<input type="button" class="addr_close" value="X" onclick="addr_a_show('addr_2'); return false;" />
<div class="addr_w_hours">
	<strong><u>&nbspЧасы работы:</u></strong><br /><br /><input type="hidden" id="addr_2_a_show" value="0" />
	<table border="0">
		<tr class="addr_t_row">
			<td style="width:65px;">Пн-пт:</td>
			<td>10<sup>00</sup> - 20<sup>00</sup></td>
		</tr>
		<tr class="addr_t_row">
			<td>Сб,вс:</td>
			<td>11<sup>00</sup> - 15<sup>00</sup></td>
		</tr>
		<tr class="addr_t_row">
			<td></td>
			<td></td>
		</tr>
	</table> 
	</div>
	
<div class="addr_phones">
	<strong><u>&nbspТелефоны:</u></strong><br /><br />
	<table border="0">
		<tr class="addr_t_row">
			<td style="width:30px; text-align:center;"><img src="/images/velcom.png" style="width:18px; height:18px;" /></td>
			<td>(29) 630-35-58</td>
		</tr>
		<tr class="addr_t_row">
			<td style="text-align:center;"><img src="/images/mts.png" style="width:18px; height:18px;" /></td>
			<td>(29) 735-35-95</td>
		</tr>
		<tr class="addr_t_row">
			<td style="text-align:center;"><img src="/images/gorod.png" style="width:18px; height:18px;" /></td>
			<td>(17) 240-33-96</td>
		</tr>
	</table> 
	
	</div>
<div class="addr_text">Для заказа доставки данного товара или бронирования с последующим самовывозом воспользуйтесь кнопкой «ЗАБРОНИРОВАТЬ»</div>


</div><!-- end of addr_2 -->




<?php
if ($dop_p_num>0) {
	echo '<div id="box_left_1">
       <br/>
		';	

	while ($dop_p=$result_dop_p->fetch_assoc()) {
		echo '
		<a class="two" rel="group" title="'.$dop_p['title'].'" href="'.$dop_p['big'].'"><img src="'.$dop_p['small'].'" alt="'.$dop_p['alt'].'" /></a>
				';
	}

	echo '
		<div class="kristy" id="kristy"><i>Кликните на изображение для увеличения</i></div>
 
</div>
		
		
		';
	
}//end of if
?>
   
<div class="bodycopy" id="box_d">
   
<blockquote><?php echo $m_i['main_descr']; ?></blockquote> 

<blockquote>
<?php
if ($m_i['bat_pic']!='') {
	echo '<img src="'.$m_i['bat_pic'].'" class="floatLeft" />';
}
echo $m_i['but_descr']; 
?>
</blockquote> 

<h2>&nbsp&nbsp&nbsp ВОЗМОЖНО ВАС ЗАИНТЕРЕСУЮТ СЛЕДУЮЩИЕ ТОВАРЫ:</h2>
<div id="slider-x">
<div class="Vwidget">
<div class="VjCarouselLite">
<ul>
<li>
<div>
<a href="http://www.tiktak.by/vesy/prokat_detskih_vesov_beurer_jby_80.htm"><img src="http://www.tiktak.by/vesy/img/prokat_detskih_vesov_beurer_jby_80_top.jpg" alt="Весы для новорожденных электронные beurer jby 80" title="Весы для новорожденных электронные beurer jby 80" /></a><br />
Весы электронные<br/><strong>Beurer</strong> JBY 80<br/><strong>14 руб./месяц</strong>
</div>
</li>
<li>
<div>
<a href="http://www.tiktak.by/vesy/vesy_detskie_momert_6400.htm"><img src="http://www.tiktak.by/vesy/img/vesy_detskie_momert_6400_top.jpg" alt="прокат детских весов" title="Весы электронные для детей momert 6400" /></a><br />
Весы электронные<br/><strong>Momert</strong> 6400<br/><strong>14 руб./месяц</strong>
</div>
</li>
<li>
<div>
<a href="http://www.tiktak.by/vesy/vesy_detskie_naprokat_momert_6420.htm"><img src="http://www.tiktak.by/vesy/img/vesy_detskie_naprokat_momert_6420_top.jpg" alt="прокат весов для младенцев" title="Весы для новорожденных Momert 6420" /></a><br />
Весы для новорожденных<br/><strong>Momert</strong> 6420<br/><strong>14 руб./месяц</strong>
</div>
</li>
  <li>
<div>
<a href="http://www.tiktak.by/vesy/prokat_vesov_dlia_detei_maman.htm"><img src="http://www.tiktak.by/vesy/img/prokat_vesov_dlia_detei_maman_top.jpg" alt="прокат детских карнавлаьных костюмов Минск" title="прокат весов для детей Maman SBBC-208" /></a><br />
Весы электронные<br/><strong>Maman</strong> SBBC-208<br/><strong>14 руб./месяц</strong>
</div>
</li>
<li>
<div>
<a href="http://www.tiktak.by/vesy/vesy_dlia_novorozhdennyh_gamma.htm"><img src="http://www.tiktak.by/vesy/img/vesy_dlia_novorozhdennyh_gamma_top.jpg" alt="прокат весов для детей Минск" title="Весы детские электронные Gamma" /></a><br />
Весы электронные<br/><strong>Gamma</strong> Я расту!<br/><strong>14 руб./месяц</strong>
</div>
</li>
</ul>
</div>
</div>
</div>  

  
 </div>
</div>
  

  
<!--Включаем футер по крутому -->
<?php include_once ($_SERVER['DOCUMENT_ROOT'].'/includes/footer_temp.html'); ?>
</div>

</body>
</html>
<?php 
function mwd_pr ($step, $num) {
	
	$num=substr($num, -1);
	
	switch ($step) {
		case 'week':
		if ($num==1) {
			return 'неделя';
		}
		elseif ($num>=2 && $num<=4) {
			return 'недели';
		}
		elseif ($num>=5) {
			return 'недель';
		}		
		break;
		
		case 'month':
			if ($num==1) {
				return 'месяц';
			}
			elseif ($num>=2 && $num<=4) {
				return 'месяца';
			}
			elseif ($num>=5) {
				return 'месяцев';
			}
		break;
		
		case 'day':
			if ($num==1) {
				return 'день';
			}
			elseif ($num>=2 && $num<=4) {
				return 'дня';
			}
			elseif ($num>=5) {
				return 'дней';
			}
		break;
	}

}



?>