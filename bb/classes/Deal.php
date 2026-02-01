<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 15.12.2018
 * Time: 15:49
 */

namespace bb\classes;


use bb\Base;
use bb\Db;
use bb\models\User;
use phpDocumentor\Reflection\Types\True_;

class Deal
{

  /**
   * @var SubDeal[]
   */
  private $subdeals;
  /**
   * @var Client
   */
  private $client;

  private static $deal_table_name = 'rent_deals_act';


  public $arch_deal_id;
  /**
   * @var \DateTime
   */
  public $arch_time;

  public $deal_id;
  public $client_id;
  public $item_inv_n;
  /**
   * @var \DateTime
   */
  public $start_date;
  /**
   * @var \DateTime
   */
  public $return_date;
  public $delivery_yn;//1, 0
  public $delivery_to_pay;
  public $delivery_paid;
  public $r_to_pay;
  public $r_paid;
  public $collateral_amount;
  public $collateral_cur;
  public $deal_status;
  public $deal_info;
  public $acc_person_id;
  public $cr_who_id;
  /**
   * @var \DateTime
   */
  public $cr_time;
  /**
   * @var \DateTime
   */
  public $last_sub_deal_ch_time;
  /**
   * @var \DateTime
   */
  public $planned_return_date;
  public $deal_set;
  public $first_rent_place;

  //-----//
  private $dealStatus;

  /**
   * @return SubDeal[]
   */
  public function getSubdeals(): array
  {
    return $this->subdeals;
  }

  /**
   * @param SubDeal[] $subdeals
   */
  public function setSubdeals(array $subdeals): void
  {
    $this->subdeals = $subdeals;
  } //act, arch


  /**
   * @return string
   */
  public function getActArchStatus()
  {
    if ($this->arch_deal_id > 0) return 'arch';
    else return 'act';
  }

  public static function changeDeliveryDate(int $dealId, \DateTime $newDate)
  {

    if ($dealId && $dealId>0) {
      $deal = Deal::getByDealId($dealId);


      //I assume for my case only one SubDeal in place
      $subDeal=false;
      $subDeals = SubDeal::getAllByDealId($deal->deal_id, true);
      foreach ($subDeals as $subD){
        if($subD->delivery_yn=='1') $subDeal = $subD;
      }

      if (!$subDeal) return false;

      $startDate = $subDeal->from;
      $endDate = $subDeal->to;

      if ($newDate<=$startDate) return false;

      $interval = $startDate->diff($newDate);

      $endDate->add($interval);
      $startDate=$newDate;

      Db::startTransaction();
        $deal->changeStartAndEndDates($startDate,$endDate);
        $subDeal->changeStartAndEndDates($startDate,$endDate);
      Db::commitTransaction();

      return true;
    }
    else{
      return false;
    }

  }

  /**
   * @param \DateTime $startDate
   * @param \DateTime $endDate
   * @return true|void
   */
  private function changeStartAndEndDates(\DateTime $startDate, \DateTime $endDate)
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "UPDATE rent_deals_act SET start_date='".$startDate->getTimestamp()."', return_date='".$endDate->getTimestamp()."', planned_return_date='".$endDate->getTimestamp()."', last_sub_deal_ch_time='".time()."' WHERE deal_id='$this->deal_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return true;
  }

  public static function getDealByInv($inv_n)
  {
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * from rent_deals_act WHERE item_inv_n='$inv_n'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $num = $result->num_rows;
    if ($num < 1) {
      $query = "SELECT * from rent_deals_act WHERE item_inv_n='$inv_n'";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
      $num = $result->num_rows;
    }
    if ($num < 1) return null;

    if ($num > 1) {
      die('Количество сделок более 1. Обратитесь к администратору.');
    } else {
      $line = $result->fetch_assoc();
      $dl = self::getFromDbArray($line);
    }

    return $dl;
  }


  public static function dealIdArchDublicateFix($dealId)
  {
    if($dealId<1) return false;

    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT deal_id from rent_deals_arch WHERE deal_id='$dealId'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    if ($result->num_rows>0){
      Db::startTransaction();

      $query = "SELECT deal_id from rent_deals_arch";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }

      $newDealId = false;

      $row = $result->fetch_assoc();//initialise prevDlId
      $prevDlId = $row['deal_id'];

      while ($row = $result->fetch_assoc()){//choosing new deal id if gap more than 1
        $nextDealId = $row['deal_id'];
        if (($nextDealId-$prevDlId)>1){
          $newDealId = $prevDlId+1;
          break;
        }
        $prevDlId = $nextDealId;
      }

      if ($newDealId) {
        //update subDealsArch id
        $query = "UPDATE rent_sub_deals_arch SET deal_id='$newDealId' WHERE deal_id='$dealId'";
        $result = $mysqli->query($query);
        if (!$result) {
          die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }

        //update dealsArch
        $query = "UPDATE rent_deals_arch SET deal_id='$newDealId' WHERE deal_id='$dealId'";
        $result = $mysqli->query($query);
        if (!$result) {
          die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }

        Db::commitTransaction();
        return true;
      }
    }

    return false;
  }


  /**
   * @param $id
   * @param $activeOnly
   * @return Deal|false|void
   */
  public static function getByDealId($id, $activeOnly = false)
  {
    $archDeal = false;
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * from rent_deals_act WHERE deal_id='$id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $num = $result->num_rows;
    if ($num < 1 && !$activeOnly) {
      $query = "SELECT * from rent_deals_arch WHERE deal_id='$id'";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
      $num = $result->num_rows;
    }
    if ($num < 1) return false;

    if ($num > 1) {
      die('Количество сделок более 1. Обратитесь к администратору.');
    } else {
      $row = $result->fetch_assoc();
      $dl = self::getFromDbArray($row);
    }

    return $dl;
  }


  /**
   * @return mixed
   */
  public function getClient_id()
  {
    return $this->client_id;
  }

  /**
   * @return mixed
   */
  public function getDogovorNumber()
  {
    return $this->deal_id;
  }

  /**
   * @param $inv_n
   * @return Deal|null
   */
  public static function getLastDealByInv($inv_n)
  {
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * from rent_deals_act WHERE item_inv_n='$inv_n' ORDER BY deal_id DESC";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $num = $result->num_rows;
    if ($num < 1) {
      $query = "SELECT * from rent_deals_arch WHERE item_inv_n='$inv_n' ORDER BY deal_id DESC";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
      $num = $result->num_rows;
    }
    if ($num < 1) return null;

    $line = $result->fetch_assoc();
    $dl = self::getFromDbArray($line);

    return $dl;
  }

  /**
   * @param array $ar
   * @return Deal
   */
  private static function getFromDbArray(array $ar)
  {
    $dl = new self();
    foreach ($ar as $key => $value) {
      if (property_exists($dl, $key)) {
        if (in_array($key, array('arch_time', 'start_date', 'return_date', 'cr_time', 'last_sub_deal_ch_time', 'planned_return_date'))) {
          $dd = new \DateTime();
          $dd->setTimestamp($value);
          $dl->$key = clone $dd;
          continue;
        }

        $dl->$key = $value;
      }
    }
    //Base::varDamp($dl);
    return $dl;
  }

  /**
   * @return void
   */
  public function updateDatesInDataBase()
  {
    $mysqli = Db::getInstance()->getConnection();
    if ($this->getActArchStatus() == 'act') $table = 'rent_deals_act';
    else $table = 'rent_deals_arch';

    $q = "UPDATE $table SET start_date='" . $this->start_date->getTimestamp() . "', return_date='" . $this->return_date->getTimestamp() . "' WHERE deal_id='$this->deal_id'";
    $result = $mysqli->query($q);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
  }

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @return Deal[]
   */
  public static function getAllForPeriod(\DateTime $from, \DateTime $to)
  {
    $deals = [];

    $mysqli = Db::getInstance()->getConnection();

    $q = "SELECT * FROM rent_deals_act WHERE start_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $to->getTimestamp() . "'";
    $result = $mysqli->query($q);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    while ($row = $result->fetch_assoc()) {
      $dl = self::getFromDbArray($row);
      if ($dl) $deals[] = $dl;
    }

    $q = "SELECT * FROM rent_deals_arch WHERE start_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $to->getTimestamp() . "'";
    $result = $mysqli->query($q);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    while ($row = $result->fetch_assoc()) {
      $dl = self::getFromDbArray($row);
      if ($dl) $deals[] = $dl;
    }

    return $deals;
  }

  /**
   * @param $dl_id
   * @return bool
   */
  public static function isForCurFirstRentAndNotDeliveredStat($dl_id)
  {
    $mysqli = Db::getInstance()->getConnection();

    $q = "SELECT `sub_deal_id` FROM rent_sub_deals_act WHERE deal_id=$dl_id AND `type`='first_rent' AND `status`='for_cur'";
    $result = $mysqli->query($q);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $n = $result->num_rows;

    if ($n > 0) return true;
    else return false;

  }

  public static function isDealActStatic($deal_id)
  {
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT deal_id FROM rent_deals_act WHERE deal_id='$deal_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $num = $result->num_rows;

    if ($num > 0) {
      return true;
    } else {
      return false;
    }
  }

  public static function updateRPaidForDealStatic($dl_id)
  {
    $mysqli = Db::getInstance()->getConnection();
    if (self::isDealActStatic($dl_id)) {
      $table_add = 'act';
    } else {
      $table_add = 'arch';
    }

    $query = "UPDATE rent_deals_$table_add SET r_paid=(
                    SELECT SUM(r_paid) FROM rent_sub_deals_$table_add WHERE deal_id='$dl_id' AND `type` IN ('payment', 'cl_payment')
                ) WHERE deal_id='$dl_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    return true;
  }

  public static function deletePaymentStatic($sub_dl_id)
  {

    $dl_id = Payment::getDlIdStatic($sub_dl_id);

    Payment::deleteSubDealStatic($sub_dl_id);

    self::updateRPaidForDealStatic($dl_id);

    return true;

  }

  public static function getAmountPaid($dl_id)
  {
    if ($dl_id < 1) {
      die('Не указан id');
      return false;
    }

    $paid = 0;
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT SUM(`r_paid`) as paid FROM rent_sub_deals_arch WHERE `type` IN ('payment', 'cl_payment') AND deal_id='$dl_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $row = $result->fetch_assoc();

    $paid += $row['paid'];

    if (is_null($row['paid']) || $row['paid'] == 0) {
      $query = "SELECT SUM(`r_paid`) as paid FROM rent_sub_deals_act WHERE `type` IN ('payment', 'cl_payment') AND deal_id='$dl_id'";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
      $row = $result->fetch_assoc();

      $paid += $row['paid'];
    }

    return $paid;
  }

  public static function getAmountToPay($dl_id)
  {
    if ($dl_id < 1) {
      die('Не указан id');
      return false;
    }
    $to_pay = 0;
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT SUM(`r_to_pay`) as r_to_pay FROM rent_sub_deals_arch WHERE `type` IN ('first_rent', 'close', 'extention', 'cur_return', 'takeaway_plan') AND deal_id='$dl_id'";
    //echo $query.'<br>';
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $row = $result->fetch_assoc();

    $to_pay += $row['r_to_pay'];

    if (is_null($row['r_to_pay']) || $row['r_to_pay'] == 0) {
      $query = "SELECT SUM(`r_to_pay`) as r_to_pay FROM rent_sub_deals_act WHERE `type` IN ('first_rent', 'close', 'extention', 'cur_return', 'takeaway_plan') AND deal_id='$dl_id'";
      //echo $query.'<br>';
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
      $row = $result->fetch_assoc();

      $to_pay += $row['r_to_pay'];
    }

    return $to_pay;
  }

  public static function recalculateAmounts($dl_id)
  {
    if ($dl_id < 1) {
      return false;
    }
    $r_to_pay = self::getAmountToPay($dl_id);
    $r_paid = self::getAmountPaid($dl_id);

    $mysqli = Db::getInstance()->getConnection();

    $query = "UPDATE rent_deals_act SET r_to_pay='$r_to_pay', r_paid='$r_paid' WHERE deal_id='$dl_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    if ($mysqli->affected_rows < 1) {
      $query = "UPDATE rent_deals_arch SET r_to_pay='$r_to_pay', r_paid='$r_paid' WHERE deal_id='$dl_id'";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
    }
    return true;
  }

  /**
   * @param $id
   * @param $activeOnly
   * @return bool|void
   */
  private static function deleteDealRecord($id, $activeOnly = true)
  {

    $mysqli = Db::getInstance()->getConnection();

    $query = "DELETE FROM rent_deals_act WHERE deal_id = '$id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при удалении из базы данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if (!$activeOnly) {
      $query = "DELETE FROM rent_deals_arch WHERE deal_id = '$id'";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при удалении из базы данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
    }

    return true;
  }

  /**
   * @param $activeOnly
   * @return void
   */
  public function deleteDealOnly($activeOnly = true)
  {
    self::deleteDealRecord($this->deal_id, $activeOnly);
  }

  /**
   * @return bool|void
   */
  private function archCopy()
  {
    $mysqli = Db::getInstance()->getConnection();

    $query = "INSERT INTO rent_deals_arch SET
                                arch_time='" . time() . "',
                                deal_id='$this->deal_id',
                                client_id='$this->client',
                                item_inv_n='$this->item_inv_n',
                                start_date='" . $this->start_date->getTimestamp() . "',
                                return_date='" . $this->return_date->getTimestamp() . "',
                                delivery_yn='$this->delivery_yn',
                                delivery_to_pay='$this->delivery_to_pay',
                                delivery_paid='$this->delivery_paid',
                                r_to_pay='$this->r_to_pay',
                                r_paid='$this->r_paid',
                                collateral_amount='$this->collateral_amount',
                                collateral_cur='$this->collateral_cur',
                                deal_status='$this->deal_status',
                                deal_info='$this->deal_info',
                                acc_person_id='$this->acc_person_id',
                                cr_who_id='$this->cr_who_id',
                                cr_time='" . $this->cr_time->getTimestamp() . "',
                                last_sub_deal_ch_time='" . $this->last_sub_deal_ch_time->getTimestamp() . "',
                                planned_return_date='" . $this->planned_return_date->getTimestamp() . "',
                                deal_set='$this->deal_set',
                                first_rent_place='" . $this->first_rent_place . "'
                            ";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при удалении из базы данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return true;
  }

  /**
   * @param $deal_id
   * @param $changeTovarStatus
   * @return void
   */
  public static function archiveFullDeal($deal_id, $changeTovarStatus = true)
  {
    Db::startTransaction();
    try {
      $deal = self::getByDealId($deal_id);
      $subDeals = SubDeal::getAllByDealId($deal_id, true);
//      Base::varDamp($deal);
//      Base::varDamp($subDeals);

      $deal->archCopy();

      foreach ($subDeals as $subDeal) {
        $subDeal->archAndDelete();
      }

      $deal->deleteDealOnly();

      Db::commitTransaction();
    } catch (\Exception $e) {
      Db::rollBackTransaction();
    }

  }

  public static function getSalesByKassa(\DateTime $from, \DateTime $to)
  {

    $mysqli = Db::getInstance()->getConnection();
    //act deals
    $query = "SELECT r_payment_type, SUM(sales1) as sales FROM
                ((SELECT r_payment_type, SUM(r_paid) as sales1 FROM rent_sub_deals_act WHERE rent_sub_deals_act.acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."' GROUP BY r_payment_type)
                  UNION
                 (SELECT r_payment_type, SUM(r_paid) as sales1 FROM rent_sub_deals_arch WHERE rent_sub_deals_arch.acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."' GROUP BY r_payment_type)
                ) t
              GROUP BY r_payment_type;
    ";
    //echo $query.'<br><br>';
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $rez =[];
      $rez['bank']=0;
      $rez['card']=0;
      $rez['nal_cheque']=0;
      $rez['nal_no_cheque']=0;
    while ($row = $result->fetch_assoc()){
      if ($row['r_payment_type']=='' || $row['r_payment_type']=='no_payment') continue;

      $rez[$row['r_payment_type']]+=$row['sales'];
    }
    return $rez;
  }

  // !!! to finish
  public static function getSalesCategorySplit(\DateTime $from_in, \DateTime $to_in){
    $from = clone $from_in;
    $to = clone $to_in;


    $rez=[]; //[cat_id][period_1_sales, period_2_sales]

    //period 2
      $mysqli = Db::getInstance()->getConnection();
      //act deals
      $query = "SELECT tovar_rent.tovar_rent_cat_id as cat_id, SUM(rent_sub_deals_act.r_paid) AS sales FROM rent_sub_deals_act
                LEFT JOIN rent_deals_act ON rent_deals_act.deal_id = rent_sub_deals_act.deal_id
                LEFT JOIN (SELECT item_inv_n, model_id FROM `tovar_rent_items_arch`
                           UNION
                           SELECT item_inv_n, model_id FROM `tovar_rent_items`) as tov ON tov.item_inv_n = rent_deals_act.item_inv_n
                LEFT JOIN `tovar_rent` ON tovar_rent.tovar_rent_id = tov.model_id
                WHERE rent_sub_deals_act.r_paid!=0 AND rent_sub_deals_act.acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $to->getTimestamp() . "'
                GROUP BY tovar_rent.tovar_rent_cat_id";
      //echo $query.'<br><br>';
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
      while ($row = $result->fetch_assoc()){
        if (!isset($rez[$row['cat_id']])) {
          $rez[$row['cat_id']] = $row['sales']*1;
        }
        else $rez[$row['cat_id']] += $row['sales']*1;
      }

      //arch deals
      $query = "SELECT tovar_rent.tovar_rent_cat_id as cat_id, SUM(rent_sub_deals_arch.r_paid) AS sales FROM rent_sub_deals_arch
                LEFT JOIN rent_deals_arch ON rent_deals_arch.deal_id = rent_sub_deals_arch.deal_id
                LEFT JOIN (SELECT item_inv_n, model_id FROM `tovar_rent_items_arch`
                           UNION
                           SELECT item_inv_n, model_id FROM `tovar_rent_items`) as tov ON tov.item_inv_n = rent_deals_arch.item_inv_n
                LEFT JOIN `tovar_rent` ON tovar_rent.tovar_rent_id = tov.model_id
                WHERE rent_sub_deals_arch.r_paid!=0 AND rent_sub_deals_arch.acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $to->getTimestamp() . "'
                GROUP BY tovar_rent.tovar_rent_cat_id";
      //echo $query.'<br><br>';
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
      while ($row = $result->fetch_assoc()){
        if (!isset($rez[$row['cat_id']])) {
          $rez[$row['cat_id']] = $row['sales']*1;
        }
        else $rez[$row['cat_id']] += $row['sales']*1;
      }


    return $rez;
  }

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @return array //0-sales, 1 deliv
   */
  public static function getSalesRentDeliv(\DateTime $from, \DateTime $to, $place='all', $delivYN='all')
  {
    $mysqli = Db::getInstance()->getConnection();
    $amount = ['sales'=>0,'deliv'=>0];

    $srchAdd='';
    if ($place!='all'){
      $srchAdd.=" AND place='$place'";
    }
    if ($delivYN!='all') {
      if ($delivYN == 1) {
        $srchAdd .= " AND delivery_yn='1'";
      } elseif ($delivYN == 0) {
        $srchAdd .= " AND delivery_yn!='1'";
      }
    }

    $query = "SELECT SUM(r_paid) as sales, SUM(delivery_paid) as deliv from rent_sub_deals_act WHERE acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $to->getTimestamp() . "' $srchAdd";

    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $row = $result->fetch_assoc();
    $amount['sales'] += $row['sales'];
    $amount['deliv'] += $row['deliv'];

    $query = "SELECT SUM(r_paid) as sales, SUM(delivery_paid) as deliv from rent_sub_deals_arch WHERE acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $to->getTimestamp() . "' $srchAdd";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $row = $result->fetch_assoc();
    $amount['sales'] += $row['sales'];
    $amount['deliv'] += $row['deliv'];

    return $amount;
  }

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @param array $catIds
   * @return int[]|void //['sales', 'deliv]
   */
  public static function getSalesRentCatFilter(\DateTime $from, \DateTime $to, array $catIds)
  {
    $mysqli = Db::getInstance()->getConnection();
    $amount = ['sales'=>0,'deliv'=>0];

    $query = "SELECT SUM(rent_sub_deals_act.r_paid) as sales, SUM(rent_sub_deals_act.delivery_paid) as deliv FROM rent_sub_deals_act
                LEFT JOIN rent_deals_act ON rent_deals_act.deal_id = rent_sub_deals_act.deal_id
                LEFT JOIN (SELECT item_inv_n, model_id FROM `tovar_rent_items_arch`
                  UNION
                  SELECT item_inv_n, model_id FROM `tovar_rent_items`) as tov ON tov.item_inv_n = rent_deals_act.item_inv_n
                LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_id = tov.model_id
              WHERE rent_sub_deals_act.acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $to->getTimestamp() . "'
                AND tovar_rent.tovar_rent_cat_id IN('".implode("', '", $catIds)."');
            ";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $row = $result->fetch_assoc();
    $amount['sales'] += $row['sales'];
    $amount['deliv'] += $row['deliv'];

    $query = "SELECT SUM(rent_sub_deals_arch.r_paid) as sales, SUM(rent_sub_deals_arch.delivery_paid) as deliv FROM rent_sub_deals_arch
                LEFT JOIN rent_deals_arch ON rent_deals_arch.deal_id = rent_sub_deals_arch.deal_id
                LEFT JOIN (SELECT item_inv_n, model_id FROM `tovar_rent_items_arch`
                  UNION
                  SELECT item_inv_n, model_id FROM `tovar_rent_items`) as tov ON tov.item_inv_n = rent_deals_arch.item_inv_n
                LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_id = tov.model_id
              WHERE rent_sub_deals_arch.acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $to->getTimestamp() . "'
                AND tovar_rent.tovar_rent_cat_id IN('".implode("', '", $catIds)."');
            ";
    //echo $query.'<br><br>';
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $row = $result->fetch_assoc();
    $amount['sales'] += $row['sales'];
    $amount['deliv'] += $row['deliv'];

    return $amount;
  }

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @param array $modelIds
   * @return int|mixed|void
   */
  public static function getSalesRentModelsFilter(\DateTime $from, \DateTime $to, array $modelIds){
    $mysqli = Db::getInstance()->getConnection();
    $amount = 0;

    function getQuery(&$dlActArch, &$tovActArch2, \DateTime $from, \DateTime $to, array $modelIds){
      if ($tovActArch2=='arch') $tovActArch = '_arch';
      else $tovActArch='';

      return $query = "SELECT SUM(rent_sub_deals_$dlActArch.r_paid) as sales FROM rent_sub_deals_$dlActArch
                        LEFT JOIN rent_deals_$dlActArch ON rent_deals_$dlActArch.deal_id = rent_sub_deals_$dlActArch.deal_id
                        LEFT JOIN tovar_rent_items$tovActArch ON tovar_rent_items$tovActArch.item_inv_n = rent_deals_$dlActArch.item_inv_n
                      WHERE rent_sub_deals_$dlActArch.acc_date BETWEEN '" . $from->getTimestamp() . "' AND '" . $to->getTimestamp() . "'
                        AND tovar_rent_items$tovActArch.model_id IN('".implode("', '",$modelIds)."')
                        ";
    }

    foreach (['act', 'arch'] as $dlSt) {
      foreach (['act', 'arch'] as $tovSt){
        $query=getQuery($dlSt, $tovSt, $from, $to, $modelIds);
        $result = $mysqli->query($query);
        if (!$result) {
          die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        $amount += $result->fetch_assoc()['sales'];
      }
    }




    return $amount;
  }

  public static function sellTovar(\DateTime $accDate, $invN, $amount, $officeID, $kassaType, $info){
    if ($invN<1) die('Не указан инвентарный номер');
    if ($officeID<1) die('не выбран офис');
    if ($kassaType=='') die('не выбрана касса');

    $d=false;
    $tovar = tovar::getTovarByInvN($invN);

    if (!$tovar) die('товар не найден');

    if ($tovar->isRentedOut()) die('товар выдан');

    if ($tovar){
      $d = DohRash::sellTovarAmount($accDate, $amount,$kassaType,$officeID, $info, \bb\models\User::getCurrentUser()->getId());
    }

    if ($d){
      $tovar->out_status='sold';
      $tovar->del_item('продажа товара:'.$info.' [id rashoda='.$d->getId().'] ');
      return true;
    }
    return false;
  }

  public static function getCountClientsDelivryAndNot(\DateTime $from, \DateTime $to, array $opeartionsArray=['first_rent', 'takeaway_plan']){
    $mysqli = Db::getInstance()->getConnection();
    $query="SELECT SUM(IF(deliv_num>0 AND non_deliv_num>0, 1, 0)) as both_num, SUM(IF(deliv_num>0 AND non_deliv_num<1, 1, 0)) as deliv_only, SUM(IF(deliv_num<1 AND non_deliv_num>0, 1, 0)) as non_deliv_only
          FROM (SELECT clients.phone_1 as clientid, SUM(IF(rent_sub_deals_arch.delivery_yn=1, 1, 0)) as deliv_num, SUM(IF(rent_sub_deals_arch.delivery_yn<1, 1, 0)) as non_deliv_num  FROM clients
            LEFT JOIN rent_deals_arch ON rent_deals_arch.client_id = clients.client_id
            LEFT JOIN rent_sub_deals_arch ON rent_deals_arch.deal_id = rent_sub_deals_arch.deal_id
            WHERE rent_sub_deals_arch.type IN('".implode("', '", $opeartionsArray)."') AND rent_sub_deals_arch.acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'
            GROUP BY clients.phone_1) t;";

    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return $result->fetch_assoc();
  }

  public static function getCountClientsOfficeNonDelivry(\DateTime $from, \DateTime $to, array $opeartionsArray=['first_rent', 'takeaway_plan']){
    $mysqli = Db::getInstance()->getConnection();
    $query="SELECT SUM(IF((of1>0 AND of2>0) OR (of2>0 AND of3>0) OR (of1>0 AND of3>0), 1, 0)) as multi_of, SUM(IF(of1>0 AND of2<1 AND of3<1, 1, 0)) as of_1, SUM(IF(of2>0 AND of1<1 AND of3<1, 1, 0)) as of_2, SUM(IF(of3>0 AND of1<1 AND of2<1, 1, 0)) as of_3
        FROM
        (SELECT clients.phone_1 as clientid, SUM(IF(rent_sub_deals_arch.place=1, 1, 0)) as of1, SUM(IF(rent_sub_deals_arch.place=2, 1, 0)) as of2, SUM(IF(rent_sub_deals_arch.place=3, 1, 0)) as of3
            FROM clients
            LEFT JOIN rent_deals_arch ON rent_deals_arch.client_id = clients.client_id
            LEFT JOIN rent_sub_deals_arch ON rent_deals_arch.deal_id = rent_sub_deals_arch.deal_id
            WHERE rent_sub_deals_arch.type IN('".implode("', '", $opeartionsArray)."') AND (rent_sub_deals_arch.acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') AND rent_sub_deals_arch.delivery_yn<1
            GROUP BY clients.phone_1) t;";

    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return $result->fetch_assoc();
  }

  public static function getRentDaysNumForCatIds(\DateTime $from, \DateTime $to, $catIds='all')
  {
    $mysqli = Db::getInstance()->getConnection();
    $srchLine = '';
    if ($catIds!='all' && is_array($catIds)){
      $srchLine = " AND models.tovar_rent_cat_id IN ('".implode("', '", $catIds)."')";
    }


    $query="
    SELECT SUM((date2 - date1)/60/60/24) as days FROM
      (SELECT IF(rent_deals_arch.start_date>".$from->getTimestamp().", rent_deals_arch.start_date, ".$from->getTimestamp().") as date1, IF(rent_deals_arch.return_date<".$to->getTimestamp().", rent_deals_arch.return_date, ".$to->getTimestamp().") as date2
      FROM `rent_deals_arch`
      LEFT JOIN (SELECT item_inv_n, model_id FROM `tovar_rent_items_arch`
                  UNION
                  SELECT item_inv_n, model_id FROM `tovar_rent_items`) as tov ON tov.item_inv_n = rent_deals_arch.item_inv_n
      LEFT JOIN (SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent`
           UNION
           SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent_arch`) as models ON models.tovar_rent_id = tov.model_id
        WHERE rent_deals_arch.return_date > rent_deals_arch.start_date $srchLine
          AND ((rent_deals_arch.return_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') OR (rent_deals_arch.start_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') OR (rent_deals_arch.start_date <= '".$from->getTimestamp()."' AND rent_deals_arch.return_date >= '".$to->getTimestamp()."'))
      ) t
    ";

    //echo $query;

    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $days = $result->fetch_assoc()['days'];

    $query="
    SELECT SUM((date2 - date1)/60/60/24) as days FROM
      (SELECT IF(rent_deals_act.start_date>".$from->getTimestamp().", rent_deals_act.start_date, ".$from->getTimestamp().") as date1, IF(rent_deals_act.return_date<".$to->getTimestamp().", rent_deals_act.return_date, ".$to->getTimestamp().") as date2
      FROM `rent_deals_act`
      LEFT JOIN (SELECT item_inv_n, model_id FROM `tovar_rent_items_arch`
                  UNION
                  SELECT item_inv_n, model_id FROM `tovar_rent_items`) as tov ON tov.item_inv_n = rent_deals_act.item_inv_n
      LEFT JOIN (SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent`
           UNION
           SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent_arch`) as models ON models.tovar_rent_id = tov.model_id
        WHERE rent_deals_act.return_date > rent_deals_act.start_date $srchLine
          AND ((rent_deals_act.return_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') OR (rent_deals_act.start_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') OR (rent_deals_act.start_date <= '".$from->getTimestamp()."' AND rent_deals_act.return_date >= '".$to->getTimestamp()."'))
      ) t
    ";

    //echo $query;

    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $days += $result->fetch_assoc()['days'];

    return $days;
  }

  public static function getRentDaysNumForModelIds(\DateTime $from, \DateTime $to, array $modelIds)
  {
    $mysqli = Db::getInstance()->getConnection();
    $srchLine = '';
    $srchLine = " AND models.tovar_rent_id IN ('".implode("', '", $modelIds)."')";


    $query="
    SELECT SUM((date2 - date1)/60/60/24) as days FROM
      (SELECT IF(rent_deals_arch.start_date>".$from->getTimestamp().", rent_deals_arch.start_date, ".$from->getTimestamp().") as date1, IF(rent_deals_arch.return_date<".$to->getTimestamp().", rent_deals_arch.return_date, ".$to->getTimestamp().") as date2
      FROM `rent_deals_arch`
      LEFT JOIN (SELECT item_inv_n, model_id FROM `tovar_rent_items_arch`
                  UNION
                  SELECT item_inv_n, model_id FROM `tovar_rent_items`) as tov ON tov.item_inv_n = rent_deals_arch.item_inv_n
      LEFT JOIN (SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent`
           UNION
           SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent_arch`) as models ON models.tovar_rent_id = tov.model_id
        WHERE rent_deals_arch.return_date > rent_deals_arch.start_date $srchLine
          AND ((rent_deals_arch.return_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') OR (rent_deals_arch.start_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') OR (rent_deals_arch.start_date <= '".$from->getTimestamp()."' AND rent_deals_arch.return_date >= '".$to->getTimestamp()."'))
      ) t
    ";

    //echo $query;

    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $days = $result->fetch_assoc()['days'];

    $query="
    SELECT SUM((date2 - date1)/60/60/24) as days FROM
      (SELECT IF(rent_deals_act.start_date>".$from->getTimestamp().", rent_deals_act.start_date, ".$from->getTimestamp().") as date1, IF(rent_deals_act.return_date<".$to->getTimestamp().", rent_deals_act.return_date, ".$to->getTimestamp().") as date2
      FROM `rent_deals_act`
      LEFT JOIN (SELECT item_inv_n, model_id FROM `tovar_rent_items_arch`
                  UNION
                  SELECT item_inv_n, model_id FROM `tovar_rent_items`) as tov ON tov.item_inv_n = rent_deals_act.item_inv_n
      LEFT JOIN (SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent`
           UNION
           SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent_arch`) as models ON models.tovar_rent_id = tov.model_id
        WHERE rent_deals_act.return_date > rent_deals_act.start_date $srchLine
          AND ((rent_deals_act.return_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') OR (rent_deals_act.start_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') OR (rent_deals_act.start_date <= '".$from->getTimestamp()."' AND rent_deals_act.return_date >= '".$to->getTimestamp()."'))
      ) t
    ";

    //echo $query;

    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $days += $result->fetch_assoc()['days'];

    return $days;
  }


//  public static function getRentDaysForInvNsForModelIdsAllTime(array $modelIds)
//  {
//    $mysqli = Db::getInstance()->getConnection();
//    $srchLine = '';
//    $srchLine = " AND tovar_rent.tovar_rent_id IN ('".implode("', '", $modelIds)."')";
//
//
//    $query="
//        SELECT tov.item_inv_n as inv_n, COUNT(rent_deals_arch.start_date) as deals_num, SUM((rent_deals_arch.return_date-rent_deals_arch.start_date)/60/60/24) as days
//        FROM (SELECT item_inv_n, model_id FROM `tovar_rent_items_arch` WHERE tovar_rent_items_arch.model_id IN('".implode("', '", $modelIds)."')
//            UNION
//            SELECT item_inv_n, model_id FROM `tovar_rent_items` WHERE tovar_rent_items.model_id IN('".implode("', '", $modelIds)."')) tov
//        LEFT JOIN rent_deals_arch ON rent_deals_arch.item_inv_n = tov.item_inv_n
//        GROUP BY tov.item_inv_n
//    ";
//
//    //echo $query;
//
//    $result = $mysqli->query($query);
//    if (!$result) {
//      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
//    }
//
//    $days = $result->fetch_assoc()['days'];
//
//    $query="
//    SELECT SUM((date2 - date1)/60/60/24) as days FROM
//      (SELECT IF(rent_deals_act.start_date>".$from->getTimestamp().", rent_deals_act.start_date, ".$from->getTimestamp().") as date1, IF(rent_deals_act.return_date<".$to->getTimestamp().", rent_deals_act.return_date, ".$to->getTimestamp().") as date2
//      FROM `rent_deals_act`
//      LEFT JOIN (SELECT item_inv_n, model_id FROM `tovar_rent_items_arch`
//                  UNION
//                  SELECT item_inv_n, model_id FROM `tovar_rent_items`) as tov ON tov.item_inv_n = rent_deals_act.item_inv_n
//      LEFT JOIN tovar_rent ON tov.model_id = tovar_rent.tovar_rent_id
//        WHERE rent_deals_act.return_date > rent_deals_act.start_date $srchLine
//          AND ((rent_deals_act.return_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') OR (rent_deals_act.start_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."') OR (rent_deals_act.start_date <= '".$from->getTimestamp()."' AND rent_deals_act.return_date >= '".$to->getTimestamp()."'))
//      ) t
//    ";
//
//    //echo $query;
//
//    $result = $mysqli->query($query);
//    if (!$result) {
//      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
//    }
//
//    $days += $result->fetch_assoc()['days'];
//
//    return $days;
//  }

}

