<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 02.12.2018
 * Time: 15:58
 */

namespace bb;
use bb\classes\Deal;
use bb\classes\KassaSet;
use bb\classes\ReportInsta;
use bb\classes\Tariff;
use bb\classes\TariffModel;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/ReportInsta.php');

Base::loginCheck();

echo Base::PageStartHTML('Инста-отчет');
//echo Base::PostCheck();

echo '
<link href="/bb/KBronForm.css" rel="stylesheet" type="text/css" />
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="/bb/KarnavalBron.js"></script>
';


//echo '<pre>';
//var_dump($kf);
//echo '</pre>';

?>


<?php
$from=new \DateTime();
$to=new \DateTime();


if (isset($_POST['from'])) {
    $from= new \DateTime(Base::GetPost('from'));
    $from->setTime(0,0,1);
}
if (isset($_POST['to'])) {
    $to=new \DateTime(Base::GetPost('to'));
    $to->setTime(0,0,1);
}



echo '
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>
<form method="post" action="report_insta.php">
С: <input type="date" name="from" value="'.$from->format("Y-m-d").'"> 
по: <input type="date" name="to" value="'.$to->format("Y-m-d").'"><br>
<input type="submit" name="action" value="Показать">
</form>

';

if ($to->getTimestamp()<$from->getTimestamp()) {
    //echo $to->getTimestamp().'--'.$from->getTimestamp();
    die('Дата "По" должна быть не раньше даты "С"');
}


$dls=ReportInsta::getAllData($from, $to);
$rez=array();
$total=0;
echo '<table border="1" cellspacing="0">
        <tr>
            <th>Клиент</th>
            <th>Сделка№</th>
            <th>Сумму (руб)</th>
        
        </tr>';
    if ($dls){
        foreach ($dls as $dl){
            if (isset($rez[$dl->getDealNumber()])){
                $rez[$dl->getDealNumber()]+=$dl->r_paid_total;
            }
            else{
                $rez[$dl->getDealNumber()]=$dl->r_paid_total;
            }
            $total+=$dl->r_paid_total;
            echo '
            <tr>
                <td>'.$dl->getClientFio().'</td>
                <td>'.$dl->getDealNumber().'</td>
                <td>'.number_format($dl->r_paid_total,2,',',' ').'</td>
            </tr>
            ';
        }
    }
echo '
<tr style="font-weight: bold;">
    <td>Всего:</td>
    <td></td>
    <td>'.number_format($total,2,',',' ').'</td>   
</tr>';
    foreach ($rez as $key=>$value) {
        echo '
        <tr style="font-style: italic;">
            <td>в т.ч.:</td>
            <td>'.$key.'</td>
            <td>'.number_format($value,2,',',' ').'</td>   
        </tr>
        ';
    }
    echo '
</table>
';




    Base::PageEndHTML();


?>