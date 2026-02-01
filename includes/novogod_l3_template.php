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
$query_tarifs = "SELECT * FROM rent_tarif_act WHERE model_id='".$model_id."' ORDER BY sort_num, kol_vo";
$result_tarifs = $mysqli->query($query_tarifs);
if (!$result_tarifs) {die('Сбой при доступе к базе данных: '.$query_tarifs.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$tarif_rows=$result_tarifs->num_rows;
$tarif_def=$result_tarifs->fetch_assoc();


//ищем размеры
$query_items = "SELECT * FROM tovar_rent_items WHERE model_id='$model_id' AND `state`!=3 ORDER BY item_rost1, item_rost2"; // state=3 means стыдо сдавать
$result_items = $mysqli->query($query_items);
if (!$result_items) {die('Сбой при доступе к базе данных: '.$query_items.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

$k_sizes=array();

while ($k_item=$result_items->fetch_assoc()) {
	$k_i_size=$k_item['item_rost1'].'-'.$k_item['item_rost2'];
		
	if (!in_array($k_i_size, $k_sizes)) {
		$k_sizes[]=$k_i_size;
	}
}

$k_sizes_text='';
foreach ($k_sizes as $value) {
	$k_sizes_text.=$value.',';
}
$k_sizes_text=substr($k_sizes_text,0,strlen($k_sizes_text)-1);//убираем последнюю запятую
//все, нашли размеры




function model_yn_of($id, $office) {
	global $mysqli;

	$free_q1 = "SELECT (item_id) FROM tovar_rent_items WHERE model_id='$id' AND (`status`='to_rent' OR (`status`='t_bron' AND br_time<".time().")) AND item_place='$office' AND `state`!=3 LIMIT 1"; // state=3 means стыдо сдавать
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
<link href="../tt_karn.css" rel="stylesheet" type="text/css" />
<link href="../tiktak.ico" rel="shortcut icon" type="image/x-icon" />

<meta name="keywords" content="<?php echo $m_i['keywords']; ?>">


<script type="text/javascript">
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
try {
var pageTracker = _gat._getTracker("UA-15543442-1");
pageTracker._trackPageview();
} catch(err) {}</script>

<link rel="stylesheet" type="text/css" href="/fancybox/jquery.fancybox.css">
<script type="text/javascript" src="/fancybox/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/fancybox/jquery.easing.1.3.js"></script>
<script type="text/javascript" src="/fancybox/jquery.fancybox-1.2.1.pack.js"></script>
<script type="text/javascript">
$(document).ready(function() { 
$("a.first").fancybox(); 
$("a.two").fancybox(); 
$("a.video").fancybox({"frameWidth":520,"frameHeight":400}); 
$("a.content").fancybox({"frameWidth":600,"frameHeight":300}); 
});
</script>
 <script type="text/JavaScript" src="/js/jquery.slider.js"></script>
 
 
 
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
 
 

</head>


<body>

<div id="container">

<!--Включаем шапку и верхнее меню-->
<?php include_once ($_SERVER['DOCUMENT_ROOT'].'/includes/header_karnaval.html'); ?>

    
 <div id="katalog">
  <div id="katalog_left"><img src="../images/katalog_11.gif" width="200" height="70" /></div>
<div id="katalog_right">
<span style="font-family: verdana, sens-serif; font-size: 12px; color: #00a0d0; font-style: italic;"><?php echo $m_i['web_way']; ?></span>

 </div>
 </div>
   
<!--Включаем левое меню -->
<?php include_once ($_SERVER['DOCUMENT_ROOT'].'/includes/left_menu_karnaval.html'); ?>

  <div id="main">
    <div  id="box">
     <div id="box_p">
   <div id="box_left"><a class="first" title="<?php echo $m_i['m_pic_alt']; ?>" href="<?php echo $m_i['m_pic_big']; ?>"><img src="<?php echo $m_i['m_pic_small']; ?>"></a></div>
   
<div id="box_right_karnaval">

<table class="karnaval_table">
	<tbody>
	<tr>
		<td colspan="2" class="karnaval_1"><?php echo $m_i['item_name_main']; ?></td>
	</tr>
	<tr>
		<td colspan="2" class="karnaval_2">Размеры: <?php echo $k_sizes_text; ?></td>
	</tr>
	<tr>
		<td class="karnaval_3">Тариф</td>
		<td class="karnaval_33"> <?php echo number_format($tarif_def['rent_amount'], 1, ',', ' '); ?>руб./сутки</td>
	</tr>
	<tr>
		<td class="karnaval_4">Залог</td>
		<td class="karnaval_44"><?php echo $m_baza['collateral']; ?> рублей</td>
	</tr>
	<tr>
		<td colspan="2" class="karnaval_2">Оценочная стоимость: <?php echo number_format($m_baza['agr_price'], 0, ',', ' '); ?> у.е.</td>
	</tr>
	<tr>
		<td colspan="2" class="karnaval_6" style="margin:0; padding:0;" >
			<div style="position:relative; margin:0; padding:0;">
		<div style="position:absolute;" id="br_mp_<?php echo $m_baza['tovar_rent_id'];?>"></div>
		<a href="#" onclick="show_mbr('first_step', '<?php echo $m_baza['tovar_rent_id']; ?>'); return false;" id="main_br_a"><img src="/includes/zakaz_button.jpg" alt="заказать" /></a>
		
	<input type="number" step="1" min="0" max="31" id="br_day" style="width:35px; height:15px; position:absolute; top:14px; left:10px;" />		
	<select name="" id="br_y_m" style="position:absolute; top:14px; left:55px; height:21px; width:90px;">
		<?php echo m_plus(0).m_plus(1).m_plus(2); ?>
    </select>		
			
			</div></td>
	</tr>
	</tbody>
</table>

</div>
   </div>
  <div class="bodycopy" id="box_d">

      

<blockquote>
<?php echo $m_i['main_descr']; ?>
</blockquote> 

  
<blockquote><img src="/karnaval/img/mask.gif" class="floatLeft" /><i>Наши любимые и обожаемые клиенты!<br />
Прежде чем звонить нашему консультанту убедительно просим Вас внимательно ознакомиться с представленной ниже информацией, возможно вы найдете исчерпывающие ответы на все ваши вопросы. Благодарим за понимание!<br /> 
•	<strong>Адрес салона</strong> (<a href="https://google.com/maps/search/?api=1&query=%D1%83%D0%BB.%20%D0%9B%D0%B8%D1%82%D0%B5%D1%80%D0%B0%D1%82%D1%83%D1%80%D0%BD%D0%B0%D1%8F,%2022">мы на карте</a>) – ул. Литературная, 22<br />
•	<strong>График работы:</strong> <br>&nbsp&nbsp&nbsp&nbsp понедельник – пятница: 10.00 -19.00, <br>&nbsp&nbsp&nbsp&nbsp&nbspсуббота: с 10.00 до 16.00, воскресенье: с 10:00 до 16:00 <br />
•	<a href="http://www.tiktak.by/karnaval/terms_karnaval.htm"> Условия бронирования и проката карнавальных костюмов</a><br />
•	Записаться на примерку можно <a href="http://www.tiktak.by/about/bron_primerka.htm">ТУТ</a> <br />
</i> </blockquote> 

<h2>&nbsp&nbsp&nbspС ЭТИМ КОСТЮМОМ ТАКЖЕ ПРОСМАТРИВАЮТ:</h2>
<div id="slider-x">
<div class="Vwidget">
<div class="VjCarouselLite">
<ul>
<li>
<div>
<a href="http://www.tiktak.by/karnaval/drakonchik.htm"><img src="http://www.tiktak.by/karnaval/img/novogodnii_kostum_drakon_top.jpg" alt="прокат новогодних костюмов для детей" title="новогодний костюм для малыша" /></a><br />
Карнавальный костюм<br/><strong>Дракончик</strong> 1-1,5 года<br/><strong></strong>
</div>
</li>
<li>
<div>
<a href="http://www.tiktak.by/karnaval/karnaval.htm#lvionok"><img src="http://www.tiktak.by/karnaval/img/novogodnii_kostum_lvionok_top.jpg" alt="прокат карнавальных костмов" title="костюм для малыша" /></a><br />
Карнавальный костюм<br/><strong>Львёнок</strong> 1,5-2,5 года<br/><strong></bold> </strong>
</div>
</li>
<li>
<div>
<a href="http://www.tiktak.by/karnaval/karnaval.htm#stinker"><img src="http://www.tiktak.by/karnaval/img/novogodnii_kostum_pirat_top.jpg" alt="карнавальные костюмы для малышей" title="костюм пирата для малыша" /></a><br />
Карнавальный костюм<br/><strong>Капитан Стинкер</strong> 1-2 года<br/><strong></bold> </strong>
</div>
</li>
  <li>
<div>
<a href="http://www.tiktak.by/karnaval/karnaval.htm#tsvetok"><img src="http://www.tiktak.by/karnaval/img/novogodnii_kostum_tsvetok_top.jpg" alt="прокат детских карнавлаьных костюмов Минск" title="карнавальный костюм Цветок" /></a><br />
Карнавальный костюм<br/><strong>Цветочек</strong> 1,5-2,5 года<br/><strong></bold></strong>
</div>
</li>
<li>
<div>
<a href="http://www.tiktak.by/karnaval/karnaval.htm#pingvin"><img src="http://www.tiktak.by/karnaval/img/novogodnii_kostum_pingvin_top.jpg" alt="прокат новогодних костюмов для детей Минск" title="Костюм пингвина" /></a><br />
Карнавальный костюм<br/><strong>Пингвинчик</strong> 1,5-2,5 года<br/><strong></bold></strong>
</div>
</li>
</ul>
</div>
</div>
</div>
     </div>
</div>
    </div>

<!--Включаем футер по крутому -->
<?php include_once ($_SERVER['DOCUMENT_ROOT'].'/includes/footer.html'); ?>
</body>
</html>

<?php

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


?>