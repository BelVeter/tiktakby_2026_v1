<?php


namespace bb\classes;


use bb\Db;

class ReportInsta
{
    public $deal_id;
    public $r_paid_total;
    public $client_id;

    /**
     * @var \DateTime
     */
    public $start_date;
    public $family;
    public $name;
    public $otch;

    private $dl_number=null;

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return ReportInsta[]|bool
     * @throws \Exception
     */
    public static function getAllData(\DateTime $start, \DateTime $end){
//        $start=new \DateTime();
//        $start->setDate(2020,01,01);
//        $end=new \DateTime();

        $rez=array();

        $mysqli=Db::getInstance()->getConnection();

        $query = "
            SELECT rent_sub_deals_arch.deal_id, SUM(rent_sub_deals_arch.r_paid) AS r_paid_total, rent_deals_arch.client_id, rent_deals_arch.start_date, clients.family, clients.name, clients.otch
            FROM rent_sub_deals_arch
            
            LEFT JOIN rent_deals_arch ON rent_deals_arch.deal_id=rent_sub_deals_arch.deal_id
            LEFT JOIN clients ON rent_deals_arch.client_id=clients.client_id
            
            WHERE rent_sub_deals_arch.acc_date BETWEEN '".$start->getTimestamp()."' AND '".$end->getTimestamp()."' AND clients.source='instagram'
            GROUP BY deal_id
        ";
        $result = $mysqli->query($query);
        if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);
        if ($result->num_rows>0){
            while ($line=$result->fetch_assoc()){
                $rez[]=self::getFromDbLine($line);
            }
        }


        $query = "
            SELECT rent_sub_deals_act.deal_id, SUM(rent_sub_deals_act.r_paid) AS r_paid_total, rent_deals_act.client_id, rent_deals_act.start_date, clients.family, clients.name, clients.otch
            FROM rent_sub_deals_act
            
            LEFT JOIN rent_deals_act ON rent_deals_act.deal_id=rent_sub_deals_act.deal_id
            LEFT JOIN clients ON rent_deals_act.client_id=clients.client_id
            
            WHERE rent_sub_deals_act.acc_date BETWEEN '".$start->getTimestamp()."' AND '".$end->getTimestamp()."' AND clients.source='instagram'
            GROUP BY deal_id
        ";
        $result = $mysqli->query($query);
        if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);
        if ($result->num_rows>0){
            while ($line=$result->fetch_assoc()){
                $rez[]=self::getFromDbLine($line);
            }
        }

        if (count($rez)>0) {
            return $rez;
        }
        else{
            return false;
        }

    }

    /**
     * @param $ar
     * @return ReportInsta|bool
     */
    public static function getFromDbLine($ar=null){
        if ($ar==null) return false;

        $r=new self();

        foreach ($r as $key=>$value){
            if (isset($ar[$key])){
                if ($key=='start_date'){
                    $tmp = new \DateTime();
                        $tmp->setTimestamp($ar[$key]);
                    $r->$key = $tmp;
                    continue;
                }
                $r->$key=$ar[$key];
            }
        }

        return $r;
    }

    /**
     * @return int
     */
    public function getDealNumber(){
        if ($this->dl_number==null) {
            $mysqli = Db::getInstance()->getConnection();
            $query = "SELECT COUNT(deal_id) AS `num` FROM rent_deals_arch WHERE client_id='" . $this->client_id . "' AND start_date<" . $this->start_date->getTimestamp();
            $result = $mysqli->query($query);
            if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);
            if ($result->num_rows < 1) {

            } else {
                $line = $result->fetch_assoc();
                $num = $line['num'] + 1;
                $this->dl_number = $num;
            }
        }

        return $this->dl_number;

    }

    public function getClientFio(){
        return $this->family.' '.$this->name.' '.$this->otch;
    }
}