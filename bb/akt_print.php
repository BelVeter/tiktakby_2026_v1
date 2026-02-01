<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 18.05.2019
 * Time: 22:27
 */
namespace bb;
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/tovar.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/client.php'); // включаем класс

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');


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
			Офис:<select name="of_select" id="of_select">
				<option value="0">не выбран</option>
				<option value="1">Литературная</option>
				<option value="2">Ложинская</option>
			</select><br />
		Логин:<input type="text" value="" name="login" /><br />
		Пароль:<input type="password" value="" name="pass" /><br />
		<input type="submit" value="войти" />
	</form></body></html>');
}

//-----------proverka paroley

//Проверка входящей информации
//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//foreach ($_POST as $key => $value) {
//	echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$sub_id=Base::GetPost('payment_id');

$mysqli = Db::getInstance()->getConnection();
$query=sub_querty_prep('arch', $sub_id);
$result = $mysqli->query($query);
if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
if ($result->num_rows<1) {
    $query=sub_querty_prep('act', $sub_id);
    $result = $mysqli->query($query);
    if ($result->num_rows<1) {
        die('Ошибка: операция не найдена.');
    }
}

$sub = $result->fetch_assoc();
$client = new \client();
    $client->load($sub['client_id']);
$tovar = new \tovar(1);
    $tovar->item_load($sub['item_inv_n']);

//    echo '<pre>';
//    var_dump($sub);
//    var_dump($client);
//    var_dump($tovar);


//подготовка некоторых значений
//$fio=encode_for_rtf($cl_def['family'].' '.$cl_def['name'].' '.$cl_def['otch']);

$change_date = new \DateTime("2017-07-01");
$devaluation_date = new \DateTime("2016-07-01");
$sub_date = new \DateTime();
    $sub_date->setTimestamp($sub['acc_date']);

if ($sub_date<$change_date) {
    $rtf = new RTF_Template('act1.rtf');
}
else {
    $rtf = new RTF_Template('act2.rtf');
}

$price_rub=$tovar->agr_price*2*0.7;
$price_rub=round($price_rub);

if ($sub_date<$devaluation_date) {
    $price_rub=$price_rub*10000;
}

$pass_date = new \DateTime();
    $pass_date->setTimestamp($client->pas_date);

$d1=new \DateTime("2015-01-01");
$d2=new \DateTime("2015-06-02");

$signator = 'в лице директора Сташенина Андрея Николаевича, действующего на основании Устава';
if ($sub_date>$d1 && $sub_date<$d2 && $sub['place']==2) {
    $signator='в лице юриста Крутиковой Светланы Леонидовны, действующего на основании доверенности №1 от 01.01.2015г.';
}
if ((int)$sub_date->format("Y")==2016 && $sub['place']==2) {
    $signator='в лице главного бухгалтера Найденовой Кристины Николаевны, действующего на основании доверенности №2 от 01.01.2015';
}
$d3 = new \DateTime("2014-08-01");
$d4 = new \DateTime("2014-12-31");
if ($sub_date>$d3 && $sub_date<$d4 && $sub['place']==2) {
    $signator='в лице юриста Крутиковой Светланы Леонидовны, действующего на основании доверенности №2 от 01.08.2014г.';
}

if ($sub_date->format("Y")=="2017" && (int)$sub_date->format("n")>=9 && $sub['place']==3) {
    $signator='в лице главного бухгалтера Найденовой Кристины Николаевны, действующего на основании доверенности №1 от 01.01.2017';
}


$rtf->parse('startdat', encode_for_rtf($sub_date->format("d.m.Y")));
$rtf->parse('fio', encode_for_rtf($client->getFIO()));
$rtf->parse('tovar', encode_for_rtf($tovar->getDogTextFull()));
$rtf->parse('price', encode_for_rtf(number_format($price_rub, 2, ',', ' ')));
$rtf->parse('actaddress', encode_for_rtf($client->getAddressLiv()));
$rtf->parse('regaddress', encode_for_rtf($client->getAddressLiv()));
$rtf->parse('pas_n', encode_for_rtf($client->pas_n));
$rtf->parse('pas_date', encode_for_rtf($pass_date->format("d.m.Y")));
$rtf->parse('pas_who', encode_for_rtf($client->pas_who));
$rtf->parse('signator', encode_for_rtf($signator));

$rtf->out_h('act.rtf');
//$rtf->out_f('/1/nd1.rtf');
echo $rtf->out(); //viewport




function sub_querty_prep ($table, $sub_id) {
    if ($table=='act') {
        $sub_table='rent_sub_deals_act';
        $deal_table='rent_deals_act';
    }
    else {
        $sub_table='rent_sub_deals_arch';
        $deal_table='rent_deals_arch';
    }

    $q="
        SELECT sub.`acc_date`, sub.`place`, deal.client_id, deal.item_inv_n FROM `$sub_table` AS sub

        LEFT JOIN $deal_table AS deal ON sub.`deal_id` = deal.`deal_id`

        WHERE sub_deal_id=$sub_id

    ";
    return $q;
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
    public function parse($block_name, $value, $start_tag = '', $end_tag = ''){
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
