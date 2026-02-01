<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once $_SERVER['DOCUMENT_ROOT'].'/bb/classes/client.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bb/classes/rtf.php';

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";


foreach ($_GET as $key => $value) {
	$$key = get_get($key);
}

//$client_id='37';
//$inv_n='71920';


$cl = new client($mysqli);
$cl->load($client_id);

$tov= new tovar($mysqli);
$tov->item_load($inv_n);


$rtf = new RTF_Template('predzakaz.rtf');


$rtf->parse('fioone', encode_for_rtf($cl->family.' '.$cl->name.' '.$cl->otch));
$rtf->parse('regaddress', encode_for_rtf($cl->reg_city.', '.$cl->reg_str.' '.$cl->reg_dom.'-'.$cl->reg_kv));
$rtf->parse('passn', encode_for_rtf($cl->pas_n));
$rtf->parse('passdate', encode_for_rtf($cl->pas_date==0 ? '_________' : date("d.m.Y", $cl->pas_date)));
$rtf->parse('passwho', encode_for_rtf($cl->pas_who));
$rtf->parse('actaddress', encode_for_rtf($cl->city.', '.$cl->str.' '.$cl->dom.'-'.$cl->kv));
$rtf->parse('tovartov', encode_for_rtf($tov->cat_dog_name.', цвет: '.$tov->model_color.', производитель: '.$tov->producer.', в комплекте: '.$tov->model_set));
$rtf->parse('today', encode_for_rtf(date("d.m.Y", time())));
$rtf->parse('phone1', encode_for_rtf(phone_print($cl->phone_1)));
$rtf->parse('phone2', encode_for_rtf(phone_print($cl->phone_2)));
$rtf->parse('cattwo', encode_for_rtf($tov->cat_dog_name));

$rtf->out_h('nd1.rtf');
$rtf->out_f('/1/nd1.rtf');
echo $rtf->out(); //viewport


function get_get($var)
{
	global $mysqli;

	return mysqli_real_escape_string($mysqli, $_GET[$var]);
}
?>