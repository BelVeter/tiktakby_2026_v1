<?php


namespace bb\classes;


use bb\Base;
use bb\Db;
use bb\models\Office;
use bb\models\User;

class Collateral
{
    public $id;
    public $deal_id;
    public $br_id;
    public $col_type; //money, other
    public $amount;
    public $currency;
    public $info;
    public $cr_who_id;
    /**
     * @var \DateTime
     */
    public $cr_time;
    public $return_who_id;
    /**
     * @var \DateTime
     */
    public $return_time;
    public $place;
    /**
     * @var \DateTime
     */
    public $acc_date;


    /**
     * @return bool
     */
    public function save(){
        $mysqli= Db::getInstance()->getConnection();

        $this->info = Base::ubratSpecSimvoly($this->info);

        $query="INSERT INTO collateral SET deal_id='$this->deal_id', br_id='$this->br_id', amount='$this->amount', info='$this->info', cr_who_id='$this->cr_who_id', cr_time='".$this->cr_time->getTimestamp()."', place='".Office::getCurrentOffice()->number."', acc_date='".$this->acc_date->format("Y-m-d")."'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        $this->id = $mysqli->insert_id;

        return true;
    }

    /**
     * @param $dl_id
     * @return Collateral|bool
     */
    public static function getCollateralByDl($dl_id){
        $mysqli= Db::getInstance()->getConnection();

        $query="SELECT * FROM collateral WHERE deal_id='$dl_id' AND (return_time IS NULL OR return_time=0)";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        if ($result->num_rows<1) {
            return false;
        }
        else {
            $line = $result->fetch_assoc();

            $rez=self::getFromDbArray($line);

            return $rez;
        }
    }

    public static function deleteByBrId($br_id){
        $mysqli= Db::getInstance()->getConnection();

        $query="DELETE FROM collateral WHERE br_id='$br_id'";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        return true;
    }

    public static function getOstatok($place){
        $mysqli=Db::getInstance()->getConnection();
        $query="SELECT SUM(`amount`) AS paid_in FROM collateral WHERE place='$place' AND (return_time IS NULL OR return_time=0)";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        $q_rez= $result->fetch_assoc();

        if ($result->num_rows>0) {
            $in=$q_rez['paid_in'];
        }
        else{
            $in=0;
        }

//        $query="SELECT SUM(`amount`) AS paid_out FROM collateral WHERE place='$place' AND (return_time IS NOT NULL OR return_time>0)";
//        $result = $mysqli->query($query);
//        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
//        $q_rez= $result->fetch_assoc();
//
//        if ($result->num_rows>0) {
//            $out=$q_rez['paid_out'];
//        }
//        else{
//            $out = 0;
//        }

//        $total=$in-$out;
        $total = $in;
        return $total;

    }

    public static function getPaidInAmount(\DateTime $acc_date, $place){
        $from = clone $acc_date;
            $from->setTime(0,0,0);
        $to = clone $acc_date;
            $to->setTime(23,59,59);


        $mysqli=Db::getInstance()->getConnection();
        $query="SELECT SUM(`amount`) AS paid_in FROM collateral WHERE place='$place' AND cr_time>'".$from->getTimestamp()."' AND cr_time<'".$to->getTimestamp()."'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        $q_rez= $result->fetch_assoc();

        $rez=$q_rez['paid_in'];

        if ($result->num_rows>0) {
            return $rez;
        }
        else{
            return 0;
        }
    }

    public static function getPaidOutAmount(\DateTime $acc_date, $place){
        $from = clone $acc_date;
        $from->setTime(0,0,0);
        $to = clone $acc_date;
        $to->setTime(23,59,59);

        $mysqli=Db::getInstance()->getConnection();
        $query="SELECT SUM(`amount`) AS paid_in FROM collateral WHERE place='$place' AND (return_time IS NOT NULL AND return_time>'".$from->getTimestamp()."' AND return_time<'".$to->getTimestamp()."')";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        $q_rez= $result->fetch_assoc();

        $rez=$q_rez['paid_in'];

        if ($result->num_rows>0) {
            return $rez;
        }
        else{
            return 0;
        }
    }


    public static function saveVozvrat($br_id){
        $mysqli= Db::getInstance()->getConnection();

        $query="UPDATE collateral SET return_time='".time()."', return_who_id='".User::getCurrentUser()->id_user."' WHERE br_id='$br_id'";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        return true;
    }

    public function getCollateralText(){
        $rez='<span style="color: red;">'.number_format($this->amount, 2, ',', ' ').' руб</span><br><span style="font-style: italic;">'.$this->info.'</span>';

        return $rez;
    }

    public static function getFromDbArray($ar){
        $col = new self();

        $col->id=$ar['id'];
        $col->deal_id=$ar['deal_id'];
        $col->br_id=$ar['br_id'];
        $col->amount=$ar['amount'];
        $col->info=$ar['info'];
        $col->cr_who_id=$ar['cr_who_id'];
        $col->cr_time = new \DateTime();
            $col->cr_time->setTimestamp($ar['cr_time']);
        $col->return_who_id=$ar['return_who_id'];
        $col->return_time = new \DateTime();
            $col->return_time->setTimestamp($ar['return_time']);
        $col->place=$ar['place'];

        return $col;
    }

}