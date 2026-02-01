<?php


namespace bb\classes;


use bb\Db;

class Report
{

    public static function getNewClientsNumber(\DateTime $from, \DateTime $to){

//        $sql = 'SELECT locations.id, title, name, hours.lobby
//                FROM locations
//                LEFT JOIN states ON states.id = locations.state_id
//                LEFT JOIN (SELECT location_id, type_id AS lobby FROM location_hours
//                            WHERE type_id IS NOT NULL) AS hours ON locations.id = hours.location_id
//                GROUP BY locations.id';

        $mysqli=Db::getInstance()->getConnection();

        $query="SELECT COUNT(client_id) AS cl_num FROM clients WHERE cr_time BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
            $done="no";
        }

        $line=$result->fetch_assoc();

        $rez=$line['cl_num'];

        return $rez;

    }

    public static function getNumberOfDealsBreakdown(\DateTime $from, \DateTime $to){
        $mysqli=Db::getInstance()->getConnection();

        $query="
            SELECT dls2.dls_num, COUNT(dls2.client_id) AS cl_num, SUM(dls2.paid_total) as r_paid_total 
                FROM (SELECT dls.client_id, COUNT(dls.deal_id) AS dls_num, SUM(dls.r_paid_total) AS paid_total 
                    FROM (SELECT rent_deals_arch.client_id, rent_deals_arch.deal_id, SUM(rent_sub_deals_arch.r_paid) AS r_paid_total
                        FROM rent_deals_arch
                        LEFT JOIN rent_sub_deals_arch ON rent_deals_arch.deal_id = rent_sub_deals_arch.deal_id
                        WHERE start_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'
                        GROUP BY rent_deals_arch.deal_id) AS dls
                    GROUP BY dls.client_id) dls2
            GROUP BY dls2.dls_num
        ";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
            $done="no";
        }

        $rez=array();

        while ($line=$result->fetch_assoc()) {
            $rez[$line['dls_num']] = array('cl_num'=>$line['cl_num'], 'r_paid_total'=>$line['r_paid_total']);
        }
        return $rez;
    }



}