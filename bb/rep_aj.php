<?php

use bb\Base;
use bb\Db;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //

Base::loginCheck();

//echo Base::PageStartAdvansed();

//$_POST['action']='all_affices_by_days';
//$_POST['from']='2019-01-01';
//$_POST['to']='2019-01-20';



if (!isset($_POST['action'])) {
    die('no command recieved');
}

$action=Base::GetPost('action');
$period=Base::GetPost('period');
$mysqli=Db::getInstance()->getConnection();


switch ($action){
    case 'all_affices_by_days':
        $from = new DateTime(Base::GetPost('from'));
            $from->setTime(0,0,0);
            $from_to = clone $from;

            switch ($period){
                case 1:
                    //no need to change
                    break;
                case 7:
                    $from_to->modify('+6 days');
                    break;
                case 30:
                    $from_to->modify('+1 month');
                    $from_to->modify('-1 day');

                    break;
            }


        $to = new DateTime(Base::GetPost('to'));
            $to->setTime(0,0,0);
        if ($to<$from) {
            return false;
        }

        $rez=array();
        $rez[0]=array("Дата", "Офис1", "Офис2", "Офис3", "курьер", "total");

        while ($from<$to) {
            $rez[$from->getTimestamp()]= array($from->format("d.m"), 0,0,0,0,0);

            $query = "SELECT SUM(r_paid) as total, place FROM rent_sub_deals_arch
                    WHERE acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $from_to->getTimestamp() . "'
                    GROUP BY place ORDER BY place";
            $result = $mysqli->query($query);
            //echo $query;
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
            $line_rez=array();
            if ($result->num_rows>0){
                while ($line=$result->fetch_assoc()){
                    //if ($line['place']<1) continue;

                    $line_rez[$line['place']]=$line['total'];
                    //Base::varDamp($line_rez);
               }
               //Base::varDamp($rez[$from->getTimestamp()]);

                if (isset($line_rez['1'])) {
                    $rez[$from->getTimestamp()]['1'] += $line_rez['1'];
                    $rez[$from->getTimestamp()]['5'] += $line_rez['1'];
                }
                if (isset($line_rez['2'])) {
                    $rez[$from->getTimestamp()]['2'] += $line_rez['2'];
                    $rez[$from->getTimestamp()]['5'] += $line_rez['2'];
                }
                if (isset($line_rez['3'])) {
                    $rez[$from->getTimestamp()]['3'] += $line_rez['3'];
                    $rez[$from->getTimestamp()]['5'] += $line_rez['3'];
                }
                if (isset($line_rez['0'])) {
                    $rez[$from->getTimestamp()]['4'] += $line_rez['0'];
                    $rez[$from->getTimestamp()]['5'] += $line_rez['0'];
                }
            }

//now add up active deals
            $query = "SELECT SUM(r_paid) as total, place FROM rent_sub_deals_act
                    WHERE acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $from_to->getTimestamp() . "'
                    GROUP BY place ORDER BY place";
            $result = $mysqli->query($query);
            //echo $query;
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
            $line_rez=array();
            if ($result->num_rows>0){
                while ($line=$result->fetch_assoc()){
                    //if ($line['place']<1) continue;

                    $line_rez[$line['place']]=$line['total'];
                    //Base::varDamp($line_rez);
                }


                if (isset($line_rez['1'])) {
                    $rez[$from->getTimestamp()]['1'] += $line_rez['1'];
                    $rez[$from->getTimestamp()]['5'] += $line_rez['1'];
                }
                if (isset($line_rez['2'])) {
                    $rez[$from->getTimestamp()]['2'] += $line_rez['2'];
                    $rez[$from->getTimestamp()]['5'] += $line_rez['2'];
                }
                if (isset($line_rez['3'])) {
                    $rez[$from->getTimestamp()]['3'] += $line_rez['3'];
                    $rez[$from->getTimestamp()]['5'] += $line_rez['3'];
                }
                if (isset($line_rez['0'])) {
                    $rez[$from->getTimestamp()]['4'] += $line_rez['0'];
                    $rez[$from->getTimestamp()]['5'] += $line_rez['0'];
                }
            }

            //echo '---'.$from->format("d.m.Y").'-'.$from_to->format('d.m.Y').'---';

            switch ($period){
                case 1:
                    $from->modify('+1 day');
                    $from_to->modify('+1 day');
                    break;
                case 7:
                    $from->modify('+7 days');
                    $from_to->modify('+7 days');
                    break;
                case 30:
                    $from->modify('+1 month');
                        $from_to=clone $from;
                        $from_to->modify('+1 month');
                        $from_to->modify('-1 day');
                    break;
            }

        }

//        $rez=array(
//            array("дата", "офис1", "офис2", "офис3"),
//            array("01-07-2020", 10,20,30),
//            array("02-07-2020", 15,22,31),
//            array("03-07-2020", 16,23,26),
//            array("04-07-2020", 12,25,57)
//        );

        $tmp_rez=array_values($rez);
        $out_rez=json_encode($tmp_rez);
        echo $out_rez;
        break;

    case 'all_affices_total':
        $from = new DateTime(Base::GetPost('from'));
        $from->setTime(0,0,0);
        $from_to = clone $from;

        switch ($period){
            case 1:
                //no need to change
                break;
            case 7:
                $from_to->modify('+6 days');
                break;
            case 30:
                $from_to->modify('+1 month');
                $from_to->modify('-1 day');

                break;
        }


        $to = new DateTime(Base::GetPost('to'));
        $to->setTime(0,0,0);
        if ($to<$from) {
            return false;
        }

        $rez=array();
        $rez[0]=array("Дата", "Выручка");

        while ($from<$to) {
            $rez[$from->getTimestamp()]= array($from->format("d.m"), 0);

            $query = "SELECT SUM(r_paid) as total FROM rent_sub_deals_arch
                    WHERE acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $from_to->getTimestamp() . "'";
            $result = $mysqli->query($query);
            //echo $query;
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
            $line_rez=array();
            if ($result->num_rows>0){
                $line=$result->fetch_assoc();
                $line_rez['total']=$line['total'];
                //Base::varDamp($rez[$from->getTimestamp()]);

                if (isset($line_rez['total'])) {
                  if (!isset($rez[$from->getTimestamp()])) $rez[$from->getTimestamp()] = [];
                  if (!isset($rez[$from->getTimestamp()]['total'])) $rez[$from->getTimestamp()]['total'] = 0;
                  $rez[$from->getTimestamp()]['total']+=$line_rez['total'];
                }

            }

//now add up active deals
            $query = "SELECT SUM(r_paid) as total, place FROM rent_sub_deals_act
                    WHERE acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $from_to->getTimestamp() . "'";
            $result = $mysqli->query($query);
            //echo $query;
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
            $line_rez=array();
            if ($result->num_rows>0){
                $line=$result->fetch_assoc();
                $line_rez['total']=$line['total'];

                if (isset($line_rez['total'])) {
                  if (!isset($rez[$from->getTimestamp()])) $rez[$from->getTimestamp()] = [];
                  if (!isset($rez[$from->getTimestamp()]['total'])) $rez[$from->getTimestamp()]['total'] = 0;
                  $rez[$from->getTimestamp()]['total']+=$line_rez['total'];
                }

            }

            //echo '---'.$from->format("d.m.Y").'-'.$from_to->format('d.m.Y').'---';

            switch ($period){
                case 1:
                    $from->modify('+1 day');
                    $from_to->modify('+1 day');
                    break;
                case 7:
                    $from->modify('+7 days');
                    $from_to->modify('+7 days');
                    break;
                case 30:
                    $from->modify('+1 month');
                    $from_to=clone $from;
                    $from_to->modify('+1 month');
                    $from_to->modify('-1 day');
                    break;
            }

        }

//        $rez=array(
//            array("дата", "офис1", "офис2", "офис3"),
//            array("01-07-2020", 10,20,30),
//            array("02-07-2020", 15,22,31),
//            array("03-07-2020", 16,23,26),
//            array("04-07-2020", 12,25,57)
//        );

        $tmp_rez=array_values($rez);
        $out_rez=json_encode($tmp_rez);
        echo $out_rez;
        break;
}


//echo Base::PageEndHTML();

?>
