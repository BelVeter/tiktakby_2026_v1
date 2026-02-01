<?php


namespace bb\classes;


use bb\Base;
use bb\Db;

class Payment
{
    public $id;
    /**
     * @var \DateTime
     */
    public $acc_date;

    private $type='payment';
    private $type_sort_n=30;

    public $r_paid;
    public $delivery_paid;
    public $r_kassa_type;
    public $delivery_kassa_type;

    public $dl_id;
    public $cr_who;
    /**
     * @var \DateTime
     */
    public $cr_time;

    public $link;
    public $place;
    public $ch_num;

    /**
     * @var \DateTime
     */
    public $from; // ???

    /**
     * @param $dial_id
     * @param null $fields
     * @return Payment[]|null
     * @throws \Exception
     */
    public static function getPaymentsForDeal($dial_id, $fields=null){
        /**
         * @var Payment[]
         */
        $rez = array();
        $mysqli = Db::getInstance()->getConnection();

        $query="SELECT r_paid, cr_time, cr_who_id FROM rent_sub_deals_arch WHERE `type`='payment' AND deal_id='$dial_id'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        $pay_num=$result->num_rows;

            if ($pay_num<1) {
                $query="SELECT r_paid, cr_time, cr_who_id FROM rent_sub_deals_act WHERE `type`='payment' AND deal_id='$dial_id'";
                $result = $mysqli->query($query);
                if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

                $pay_num=$result->num_rows;
            }
        if ($pay_num<1) {
            return null;
        }
        else {
            while ($line = $result->fetch_assoc()) {
                $rez[]=self::getFromDbArray($line);
            }
            return $rez;
        }
    }

    /**
     * @param $sub_dl_id
     * @return bool
     */
    public static function deleteSubDealStatic($sub_dl_id){
        $mysqli = Db::getInstance()->getConnection();

        $query="DELETE FROM rent_sub_deals_act WHERE sub_deal_id='$sub_dl_id'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        $query="DELETE FROM rent_sub_deals_arch WHERE sub_deal_id='$sub_dl_id'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        return true;
    }

    /**
     * @param $sub_dl_id
     * @return bool
     */
    public static function getDlIdStatic($sub_dl_id) {
        $mysqli = Db::getInstance()->getConnection();

        $query="SELECT deal_id FROM rent_sub_deals_arch WHERE sub_deal_id='$sub_dl_id'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        $pay_num=$result->num_rows;

        if ($pay_num<1) {
            $query="SELECT deal_id FROM rent_sub_deals_act WHERE sub_deal_id='$sub_dl_id'";
            $result = $mysqli->query($query);
            if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
            $pay_num=$result->num_rows;
            if ($pay_num<1) return false;
        }

        $rez=$result->fetch_assoc();

        return $rez['deal_id'];
    }

    /**
     * @param $arr
     * @return Payment
     * @throws \Exception
     */
    public static function getFromDbArray($arr){
        $p = new self();

        //$p->id=$arr['sub_deal_id'];
        $p->r_paid=$arr['r_paid'];
        //$p->delivery_paid=$arr['delivery_paid'];
        //$p->acc_date = new \DateTime();
            //$p->acc_date->setTimestamp($arr['acc_date']);

        $p->cr_who=$arr['cr_who_id'];
        $p->cr_time = new \DateTime();
            $p->cr_time->setTimestamp($arr['cr_time']);

        return $p;
    }

  /**
   * @param \DateTime $date
   * @param $place
   * @param $channel
   * @param $kassa
   * @return int|mixed
   */
  public static function getSumForDate(\DateTime $date, $channel='all', $place='all')
    {
      $mysqli = Db::getInstance()->getConnection();
      $sum = 0;
      $srchAdd='';

      if ($channel!='all'){
        $srchAdd.=" AND r_payment_type='$channel'";
      }

      if ($place!='all'){
        $srchAdd.=" AND place='$place'";
      }


      //main active deal sales
      $query = "SELECT SUM(r_paid) as rez FROM rent_sub_deals_act WHERE acc_date='".$date->getTimestamp()."' $srchAdd";
      $result = $mysqli->query($query);
      if (!$result) {
        echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
      }
      $row = $result->fetch_assoc();
      $sum+=$row['rez'];

      //main archive deal sales
      $query = "SELECT SUM(r_paid) as rez FROM rent_sub_deals_arch WHERE acc_date='".$date->getTimestamp()."' $srchAdd";
      $result = $mysqli->query($query);
      if (!$result) {
        echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
      }
      $row = $result->fetch_assoc();
      $sum+=$row['rez'];

      //delivery sales
      $srchAdd='';

      if ($channel!='all'){
        $srchAdd.=" AND del_payment_type='$channel'";
      }
      if ($place!='all'){
        $srchAdd.=" AND place='$place'";
      }

      //main active deal sales
      $query = "SELECT SUM(delivery_paid) as rez FROM rent_sub_deals_act WHERE acc_date='".$date->getTimestamp()."' $srchAdd";
      $result = $mysqli->query($query);
      if (!$result) {
        echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
      }
      $row = $result->fetch_assoc();
      $sum+=$row['rez'];


      //main archive deal sales
      $query = "SELECT SUM(delivery_paid) as rez FROM rent_sub_deals_arch WHERE acc_date='".$date->getTimestamp()."' $srchAdd";
      $result = $mysqli->query($query);
      if (!$result) {
        echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
      }
      $row = $result->fetch_assoc();
      $sum+=$row['rez'];
      
      return $sum;
    }
}
