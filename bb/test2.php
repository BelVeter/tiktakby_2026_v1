<?php
session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных


$tiktak = new domDocument("1.0", "utf-8"); // Создаём XML-документ версии 1.0 с кодировкой utf-8

//выбираем категории
$query_cats = "SELECT * FROM tovar_rent_cat ORDER BY rent_cat_name";
$result_cats = $mysqli->query($query_cats);
if (!$result_cats) {die('Сбой при доступе к базе данных: '.$query_cats.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

$cats_list='';
while ($cat_names=$result_cats->fetch_assoc()) {
	$cats_list.='<category id="'.$cat_names['tovar_rent_cat_id'].'">'.good_print($cat_names['rent_cat_name']).' напрокат</category>';
}


$content = '
<!DOCTYPE yml_catalog SYSTEM "shops.dtd">
<yml_catalog date="'.date("Y-m-d H:i").'">
  <shop>
    <name>ЧУП "Тоддлер Фан"</name>
	<company>ЧУП "Тоддлер Фан"</company>
    <url>http://tiktak.deal.by/</url>
    <currencies>
      <currency id="USD" rate="CB"/>
      <currency id="KZT" rate="CB"/>
      <currency id="RUR" rate="CB"/>
      <currency id="BYR" rate="1"/>
      <currency id="UAH" rate="CB"/>
      <currency id="EUR" rate="CB"/>
    </currencies>
	<categories>
  		'.$cats_list.'
    </categories>
  <offers>
		';

$m_info_q = "SELECT * FROM rent_model_web WHERE cat_id!='2'";
$result_m_info = $mysqli->query($m_info_q);
if (!$result_m_info) {die('Сбой при доступе к базе данных: '.$m_info_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$mi_num=$result_m_info->num_rows;

while ($mi=$result_m_info->fetch_assoc()) {

		
	if ($mi['m_pic_big']=='') {
		$pic=$mi['l2_pic'];
	}
	else {
		$pic=$mi['m_pic_big'];
	}
	
	
	//выбираем тарифы
	$tarif_q = "SELECT * FROM rent_tarif_act WHERE model_id='".$mi['model_id']."' ORDER BY sort_num DESC, kol_vo DESC";
	$result_tarif = $mysqli->query($tarif_q);
	if (!$result_tarif) {die('Сбой при доступе к базе данных: '.$tarif_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
	$tar_num=$result_tarif->num_rows;
	//echo 'тарифов:'.$tar_num;
	
	$tar_text='';
	$price='не определена';
	while ($tar=$result_tarif->fetch_assoc()) {
		$tar_text.=''.number_format($tar['kol_vo'], 0, ',', ' ').' '.mwd_pr($tar['step'], $tar['kol_vo']).' - '.number_format($tar['rent_amount']*1000, 0, ',', ' ').' руб.;
			';
		$price=$tar['rent_amount']*1000;
	}

	
	
	$content.='
<offer available="true" id="'.$mi['web_id'].'">
    <url>http://tiktak.deal.by/p'.$mi['web_id'].'-karnavalnyj-kostyum.html</url>
    <price>'.($price).'</price>
    <currencyId>BYR</currencyId>
    <categoryId>'.$mi['cat_id'].'</categoryId>
    <picture>http://www.tiktak.by'.htmlspecialchars($pic).'</picture>
    <pickup>true</pickup>
    <delivery>true</delivery>
    <name>Прокат: '.htmlspecialchars(html_entity_decode($mi['l2_name'])).'</name>
    <description>
    		Внимание! Цена указана за неделю проката. Все тарифы: '.$tar_text.'
    		Актуальная информация о тарифах и наличии (в реальном времени) находится на сайте по адресу: http://www.tiktak.by'.htmlspecialchars($mi['page_addr']).' (скопируйте адрес в браузер). Там же Вы можете забронировать товар.
    		
    				
	</description>
  </offer>
		';
	
	
}










$content.='
  </offers>
</shop>		
</yml_catalog>		
		
		';



$tiktak->loadXML($content);

$tiktak->save("second_yml.xml"); // Сохраняем полученный XML-документ в файл



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



function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}

?>