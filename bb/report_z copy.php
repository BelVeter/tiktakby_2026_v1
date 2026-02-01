<?php

use bb\Base;
use bb\classes\Report;

session_start();

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Report.php'); //


Base::loginCheck();


$rp= new Report();

$y=2016;
$th='<th></th>';
$td='<td>Новые клиенты:</td>';

while ($y<=2019) {
    $start = new DateTime();
        $start->setTime(0,0,0);
        $start->setDate($y, 1,1);
    $end = new DateTime();
        $end->setTime(23,59,59);
        $end->setDate($y,12,31);

    $num=Report::getNewClientsNumber($start, $end);
    $th.='<th>'.$y.'</th>';
    $td.='<td>'.$num.'</td>';

    $y++;

}

echo '
    <table border="1" cellspacing="0">
        <tr>'.$th.'</tr>
        <tr>'.$td.'</tr>
    </table>';



?>
