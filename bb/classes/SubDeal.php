<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 15.12.2018
 * Time: 20:48
 */

namespace bb\classes;


use bb\Db;
use bb\models\User;
use phpDocumentor\Reflection\Types\True_;

class SubDeal
{
  private $arch_sub_deal_id;
  /**
   * @var \DateTime
   */
  public $arch_time;
  public $sub_deal_id;
  public $deal_id;
  public $type; //first_rent, payment, close, cl_payment, extention, cur_return, takeaway_plan
  public $type_sort_n;//sort number: 5(takeaway_plan), 10(first_rent), 20(extention), 30(payment), 80(close)
  /**
   * @var \DateTime
   */
  public $from;
  /**
   * @var \DateTime
   */
  public $to;
  public $tarif_id;//?
  public $tarif_step;// day, week, month
  public $tarif_value;
  public $rent_tenor;
  public $r_to_pay;
  public $delivery_yn;
  public $delivery_to_pay;
  public $courier_id;
  public $r_paid;
  public $delivery_paid;
  public $r_payment_type;
  public $del_payment_type;
  public $status; // for_cur, delivered, no_status
  public $info;
  /**
   * @var \DateTime
   */
  public $cr_time;
  public $cr_who_id;
  /**
   * @var \DateTime
   */
  public $ch_time;
  public $ch_who_id;
  public $link; //used for payments
  /**
   * @var \DateTime
   */
  public $acc_date;
  public $place;
  public $ch_num; //number of fiscal note

  public $sd_cat_id;//???
  public $sd_model_id;//???
  public $sd_inv_n;//???

  private $statusActArch; // act, arch

  /**
   * @return mixed
   */
  public function getStatusActArch()
  {
    return $this->statusActArch;
  }

  /**
   * @param mixed $statusActArch
   */
  public function setStatusActArch($statusActArch): void
  {
    $this->statusActArch = $statusActArch;
  }

  /**
   * @return string
   */
  public function getOperationName()
  {
    switch ($this->type) {//first_rent, payment, close, cl_payment, extention, cur_return, takeaway_plan
      case 'first_rent':
        return 'Выдача товара';
        break;
      case 'payment':
        return 'Оплата';
      case 'close':
        return 'Возврат';
        break;
      case 'cl_payment':
        return 'Оплата';
        break;
      case 'extention':
        return 'Продление';
        break;
      case 'cur_return':
        return 'Возврат';
        break;
      case 'takeaway_plan':
        return 'Планируемая выдача';
        break;
      default:
        return 'не определено';
    }
  }

  /**
   * @return mixed
   */
  public function getDealId()
  {
    return $this->deal_id;
  }

  /**
   * @param $dl_id
   * @return SubDeal[]
   */
  public static function loadByDlIdAll($dl_id)
  {
    /**
     * @var SubDeal
     */
    $dls = array();

    $db = Db::getInstance();
    $mysqli = $db->getConnection();
    $query = "SELECT * FROM rent_sub_deals_arch WHERE deal_id = $dl_id";
    $result = $mysqli->query($query);
    if (!$result) {
      echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
    }

    while ($dd = $result->fetch_assoc()) {
      $dls[] = self::createFromArray($dd);
    }

    return $dls;
  }

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @param $subDealType
   * @return mixed
   */
  public static function getSubDealCount(\DateTime $from, \DateTime $to, array $subDealTypes=[]){
    $mysqli = Db::getInstance()->getConnection();

    $filter='';
    if ($subDealTypes && count($subDealTypes)>0){
      $filter = " AND `type` IN ('".implode("', '",$subDealTypes)."')";
    }

    $query = "SELECT COUNT(sub_deal_id) as num FROM rent_sub_deals_arch
                WHERE acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."' $filter ";
    $result = $mysqli->query($query);
    if (!$result) {
      echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
    }

    $rezArch = $result->fetch_assoc()['num'];

      $query = "SELECT COUNT(sub_deal_id) as num FROM rent_sub_deals_act
                  WHERE acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."' $filter ";
      $result = $mysqli->query($query);
      if (!$result) {
        echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
      }

      $rezAct = $result->fetch_assoc()['num'];

    return $rezArch + $rezAct;
  }

  /**
   * @param $dl_id
   * @param $activeOnly
   * @return SubDeal[];
   */
  public static function getAllByDealId($dl_id, $activeOnly = false){
    $subDls = [];

    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * FROM rent_sub_deals_act WHERE deal_id = $dl_id";
    $result = $mysqli->query($query);
    if (!$result) {
      echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
    }
    while ($row = $result->fetch_assoc()) {
      $subDl = self::createFromArray($row);
        $subDl->setStatusActArch('act');
      $subDls[] = $subDl;
    }

    if (!$activeOnly){
      $query = "SELECT * FROM rent_sub_deals_arch WHERE deal_id = $dl_id";
      $result = $mysqli->query($query);
      if (!$result) {
        echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
      }
      while ($row = $result->fetch_assoc()) {
        $subDl = self::createFromArray($row);
        $subDl->setStatusActArch('arch');
        $subDls[] = $subDl;
      }
    }

    return $subDls;
  }

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @param $activeOnly
   * @return SubDeal[]
   */
  public static function getAllByAccDates(\DateTime $from, \DateTime $to, $activeOnly=false){
    $subDls = [];

    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * FROM rent_sub_deals_act WHERE acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'";
    $result = $mysqli->query($query);
    if (!$result) {
      echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
    }
    while ($row = $result->fetch_assoc()) {
      $subDl = self::createFromArray($row);
      $subDl->setStatusActArch('act');
      $subDls[] = $subDl;
    }

    if (!$activeOnly){
      $query = "SELECT * FROM rent_sub_deals_arch WHERE acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'";
      $result = $mysqli->query($query);
      if (!$result) {
        echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
      }
      while ($row = $result->fetch_assoc()) {
        $subDl = self::createFromArray($row);
        $subDl->setStatusActArch('arch');
        $subDls[] = $subDl;
      }
    }

    return $subDls;
  }

  /**
   * @param $id
   * @param $activeOnly
   * @return void
   */
  public static function  delete($id, $activeOnly=true){
    $mysqli = Db::getInstance()->getConnection();
    $query = "DELETE FROM rent_sub_deals_act WHERE sub_deal_id = '$id'";
    $result = $mysqli->query($query);
    if (!$result) {
      echo 'Сбой при удалении из базы данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
    }
    if (!$activeOnly){
      $query = "DELETE FROM rent_sub_deals_arch WHERE sub_deal_id = '$id'";
      $result = $mysqli->query($query);
      if (!$result) {
        echo 'Сбой при удалении из базы данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
      }
    }
  }

  /**
   * @return void
   */
  public function deletePublic(){
    self::delete($this->sub_deal_id);
  }

  /**
   * @param $deal_id
   * @param $activeOnly
   * @return void
   */
  public static function deleteAllForDealId($deal_id, $activeOnly = true){
    $mysqli = Db::getInstance()->getConnection();
    $query = "DELETE FROM rent_sub_deals_act WHERE deal_id = '$deal_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      echo 'Сбой при удалении из базы данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
    }
    if (!$activeOnly){
      $query = "DELETE FROM rent_sub_deals_arch WHERE deal_id = '$deal_id'";
      $result = $mysqli->query($query);
      if (!$result) {
        echo 'Сбой при удалении из базы данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
      }
    }
  }

  /**
   * @param \DateTime $startDate
   * @param \DateTime $endDate
   * @return true|void
   */
  public function changeStartAndEndDates(\DateTime $startDate, \DateTime $endDate)
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "UPDATE rent_sub_deals_act SET `from`='".$startDate->getTimestamp()."', `to`='".$endDate->getTimestamp()."', acc_date='".$startDate->getTimestamp()."', ch_time='".time()."', ch_who_id='".User::getCurrentUser()->getId()."' WHERE sub_deal_id='$this->sub_deal_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return true;
  }

  /**
   * @return void
   */
  public function updateDatesInDataBase(){
    $mysqli = Db::getInstance()->getConnection();

    if ($this->getStatusActArch()=='act') $table = 'rent_sub_deals_act';
    else $table = 'rent_sub_deals_arch';

    $query="UPDATE $table SET `from`='".$this->from->getTimestamp()."', `to`='".$this->to->getTimestamp()."', acc_date='".$this->acc_date->getTimestamp()."' WHERE sub_deal_id='$this->sub_deal_id'";
    //echo $query.'<br>';
    $result = $mysqli->query($query);
    if (!$result) {
      echo 'Сбой при обновлении даты: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error.'<br>';
    }
  }

  /**
   * @param $id
   * @return SubDeal
   */
  public static function getByIdAct($id)
  {

    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM rent_sub_deals_act WHERE sub_deal_id = $id";
    $result = $mysqli->query($query);
    if (!$result) {
      echo 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
    }

    $row = $result->fetch_assoc();

    return self::createFromArray($row);

  }

  public function printSubDeal()
  {
    return 'операция: ' . $this->type . ' (№:' . $this->sub_deal_id . ') с: ' . $this->from->format("d.m.Y (H:i)") . ', по ' . $this->to->format("d.m.Y (H:i)") . '
            <br>' . $this->cr_time->format("d.m.Y (H:i)") . '(' . User::GetUserName($this->cr_who_id) . '-' . $this->cr_who_id . '), сумма к олп:' . $this->r_to_pay . ' Сумма опл ' . $this->r_paid;
  }


  /**
   * @param array $arr
   * @return SubDeal
   */
  public static function createFromArray(array $arr)
  {
    $dl = new self();
    foreach ($dl as $key => $value) {
      if (key_exists($key, $arr)) {
        if ($dl->$key instanceof \DateTime) {
          $dl->$key->setTimestamp($arr[$key]);
        } else {
          $dl->$key = $arr[$key];
        }
      }
    }
    return $dl;
  }

  public function __construct()
  {
    $this->arch_time = new \DateTime("1970-01-01");
    $this->from = new \DateTime("1970-01-01");
    $this->to = new \DateTime("1970-01-01");
    $this->cr_time = new \DateTime("1970-01-01");
    $this->ch_time = new \DateTime("1970-01-01");
    $this->acc_date = new \DateTime("1970-01-01");

  }

  /**
   * @return bool|void
   */
  private function archCopy(){
    $mysqli = Db::getInstance()->getConnection();

    $query = "INSERT INTO rent_sub_deals_arch SET
                        arch_time = '".time()."',
                        sub_deal_id = '$this->sub_deal_id',
                        deal_id = '$this->deal_id',
                        `type` = '$this->type',
                        type_sort_n = '$this->type_sort_n',
                        `from` = '".$this->from->getTimestamp()."',
                        `to` = '".$this->to->getTimestamp()."',
                        tarif_id = '$this->tarif_id',
                        tarif_value = '$this->tarif_value',
                        rent_tenor = '$this->rent_tenor',
                        r_to_pay = '$this->r_to_pay',
                        delivery_yn='$this->delivery_yn',
                        delivery_to_pay = '$this->delivery_to_pay',
                        courier_id = '$this->courier_id',
                        r_paid = '$this->r_paid',
                        delivery_paid = '$this->delivery_paid',
                        r_payment_type = '$this->r_payment_type',
                        del_payment_type = '$this->del_payment_type',
                        `status` = '$this->status',
                        info = '$this->info',
                        cr_time = '".$this->cr_time->getTimestamp()."',
                        cr_who_id = '$this->cr_who_id',
                        ch_time = '".$this->ch_time->getTimestamp()."',
                        ch_who_id = '$this->ch_who_id',
                        link = '$this->link',
                        acc_date = '".$this->acc_date->getTimestamp()."',
                        place = '$this->place',
                        ch_num = '$this->ch_num',
                        sd_cat_id = '$this->sd_cat_id',
                        sd_model_id = '$this->sd_model_id',
                        sd_inv_n = '$this->sd_inv_n'
                        ";

    $result = $mysqli->query($query);

    if (!$result) {
      die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }

    return true;
  }

  /**
   * @return bool
   */
  public function archAndDelete(){
    $this->archCopy();
    $this->deletePublic();
    return true;
  }

}
