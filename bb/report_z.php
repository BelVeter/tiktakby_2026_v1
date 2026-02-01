<?php

use bb\Base;
use bb\classes\Report;

session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Report.php'); //

echo Base::PageStartAdvansed('Отчет 2');

Base::loginCheck();


$rp= new Report();
$y_start=2016;

$y=$y_start;
$th='<th></th>';
$td='<td>Новые клиенты:</td>';

while ($y<=2019) {
    $start = new DateTime();
        $start->setTime(0,0,1);
        $start->setDate($y, 1,1);
    $end = new DateTime();
        $end->setTime(23,59,59);
        $end->setDate($y,12,31);

    $num=Report::getNewClientsNumber($start, $end);
    $th.='<th>'.$y.'</th>';
    $td.='<td>'.number_format($num,0,',',' ').'</td>';

    $y++;

}

echo '
    <table border="1" cellspacing="0">
        <tr>'.$th.'</tr>
        <tr>'.$td.'</tr>
    </table>';


$y=$y_start;

$dls_ar=array();

$rows = array();
while ($y<=2019) {
    $start = new DateTime();
       $start->setTime(0,0,0);
        $start->setDate($y, 1,1);
    $end = new DateTime();
        $end->setTime(23,59,59);
        $end->setDate($y,12,31);

    $dls=Report::getNumberOfDealsBreakdown($start, $end);

    foreach ($dls as $key=>$value) {
        $rows[]='<tr>
            <td>'.$key.'</td>
            <td>'.$value['cl_num'].'</td>
            <td>'.$value['r_paid_total'].'</td>
            <td>'.$y.'</td>
        </tr>';
    }

    $y++;
}

echo '<table border="1" cellspacing="0">
    <th>кол-во сделок</th>
    <th>кол-во клиентов</th>
    <th>уплаченная сумма</th>
    <th>год</th>';

foreach ($rows as $row) {
    echo $row;
}

echo '
</table>';




?>
