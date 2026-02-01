<?php

namespace bb\classes;

use App\Models\User;
use bb\Base;
use bb\Db;

class DohRash
{
  public $id;
  /**
   * @var \DateTime
   */
  public $acc_date;
  public $amount;
  public $type1;//doh, rach, shift_plus, shift_minus
  public $type2;//...prod_tovar...
  public $channel;//bank,1,2,cur,3, HZ,4
  public $kassa;//bank, k1, k2, HZ
  public $link_to;
  public $info;
  /**
   * @var \DateTime
   */
  public $cr_time;
  public $cr_who_id;
  public $dr_name_id;
  /**
   * @var \DateTime
   */
  public $del_time;
  public $del_who_id;

  static $_rashKeyValue;
  static $_dohKeyValue;

  public function __construct()
  {
    $this->cr_time = new \DateTime();
  }

  public function getDelTime(): \DateTime
  {
    return $this->del_time;
  }

  public function setDelTime(\DateTime $del_time): void
  {
    $this->del_time = $del_time;
  }

  /**
   * @return mixed
   */
  public function getDelWhoId()
  {
    return $this->del_who_id;
  }

  /**
   * @param mixed $del_who_id
   */
  public function setDelWhoId($del_who_id): void
  {
    $this->del_who_id = $del_who_id;
  }



  /**
   * @return mixed
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param mixed $id
   */
  public function setId($id): void
  {
    $this->id = $id;
  }

  /**
   * @return \DateTime
   */
  public function getAccDate(): \DateTime
  {
    return $this->acc_date;
  }

  /**
   * @param \DateTime $acc_date
   * @return void
   */
  public function setAccDate(\DateTime $acc_date): void
  {
    $this->acc_date = $acc_date;
  }

  /**
   * @return mixed
   */
  public function getAmount()
  {
    return $this->amount;
  }

  /**
   * @param mixed $amount
   */
  public function setAmount($amount): void
  {
    $this->amount = $amount;
  }

  /**
   * @return mixed
   */
  public function getType1()
  {
    return $this->type1;
  }

  /**
   * @param mixed $type1
   */
  public function setType1($type1): void
  {
    $this->type1 = $type1;
  }

  /**
   * @return mixed
   */
  public function getType2()
  {
    return $this->type2;
  }

  /**
   * @param mixed $type2
   */
  public function setType2($type2): void
  {
    $this->type2 = $type2;
  }

  /**
   * @return mixed
   */
  public function getChannel()
  {
    return $this->channel;
  }

  /**
   * @param mixed $channel
   */
  public function setChannel($channel): void
  {
    $this->channel = $channel;
  }

  /**
   * @return mixed
   */
  public function getKassa()
  {
    return $this->kassa;
  }

  /**
   * @param mixed $kassa
   */
  public function setKassa($kassa): void
  {
    $this->kassa = $kassa;
  }

  /**
   * @return mixed
   */
  public function getLinkTo()
  {
    return $this->link_to;
  }

  /**
   * @param mixed $link_to
   */
  public function setLinkTo($link_to): void
  {
    $this->link_to = $link_to;
  }

  /**
   * @return mixed
   */
  public function getInfo()
  {
    return $this->info;
  }

  /**
   * @param mixed $info
   */
  public function setInfo($info): void
  {
    $this->info = $info;
  }

  /**
   * @return \DateTime
   */
  public function getCrTime(): \DateTime
  {
    return $this->cr_time;
  }

  /**
   * @param \DateTime $cr_time
   * @return void
   */
  public function setCrTime(\DateTime $cr_time): void
  {
    $this->cr_time = $cr_time;
  }

  /**
   * @return mixed
   */
  public function getCrWhoId()
  {
    return $this->cr_who_id;
  }

  /**
   * @param mixed $cr_who_id
   */
  public function setCrWhoId($cr_who_id): void
  {
    $this->cr_who_id = $cr_who_id;
  }

  /**
   * @return mixed
   */
  public function getDrNameId()
  {
    return $this->dr_name_id;
  }

  /**
   * @param mixed $dr_name_id
   */
  public function setDrNameId($dr_name_id): void
  {
    $this->dr_name_id = $dr_name_id;
  }

  /**
   * @return true|void
   */
  public static function loadDohRashKeyValue(){
    if (!(self::$_rashKeyValue && is_array(self::$_rashKeyValue))){
      $mysqli = Db::getInstance()->getConnection();

      $q = "select * FROM rash_items";
      $result=$mysqli->query($q);

      if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
      $rez = [];
      while ($row = $result->fetch_assoc()){
        $rez[$row['ri_code']] = $row['ri_text'];
      }
      self::$_rashKeyValue = $rez;
    }
    if (!(self::$_dohKeyValue && is_array(self::$_dohKeyValue))){
      $mysqli = Db::getInstance()->getConnection();

      $q = "select * FROM doh_items";
      $result=$mysqli->query($q);

      if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
      $rez = [];
      while ($row = $result->fetch_assoc()){
        $rez[$row['rd_code']] = $row['rd_text'];
      }
      self::$_dohKeyValue = $rez;
    }
    return true;
  }

  /**
   * @return bool
   */
  public function isRash(){
    if ($this->type1='rash') return true;
    else return false;
  }

  /**
   * @return bool
   */
  public function isDoh(){
    if ($this->type1='doh') return true;
    else return false;
  }

  /**
   * @return mixed|string
   */
  public function getNameText(){
    if ($this->isRash()) {
      if (isset(self::$_rashKeyValue[$this->type2])) return self::$_rashKeyValue[$this->type2];
      else return 'N/A';
    }
    elseif ($this->isDoh()){
      if (isset(self::$_dohKeyValue[$this->type2])) return self::$_dohKeyValue[$this->type2];
      else return 'N/A';
    }
    else return 'operation type not considered';
  }


  /**
   * @param $row
   * @return false|self
   */
  public static function createFromDbArray($row){
    if (!is_array($row)) return false;

    $dr = new self();
    $dr->setId($row['dr_id']);
    $accDate = new \DateTime();
      $accDate->setTimestamp($row['acc_date']);
      $dr->setAccDate($accDate);
    $dr->setAmount($row['amount']);
    $dr->setType1($row['type1']);
    $dr->setType2($row['type2']);
    $dr->setChannel($row['channel']);
    $dr->setKassa($row['kassa']);
    $dr->setLinkTo($row['link_to']);
    $dr->setInfo($row['info']);

    $crTime = new \DateTime();
      $crTime->setTimestamp($row['cr_time']);
      $dr->setCrTime($crTime);

    $dr->setCrWhoId($row['cr_who_id']);
    $dr->setDrNameId($row['dr_name_id']);

    return $dr;

  }

  /**
   * @return array
   */
  public static function getAllRashKeyValues(){
    self::loadDohRashKeyValue();
    return self::$_rashKeyValue;
  }

  /**
   * @return mixed
   */
  public static function getAllDohKeyValues(){
    self::loadDohRashKeyValue();
    return self::$_dohKeyValue;
  }

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @param $type1
   * @param $type2
   * @return DohRash[]|void
   */
  public static function getAllFiltered(\DateTime $from, \DateTime $to, $type1='all', $type2='all'){
    $fromF = clone $from;
      $fromF->setTime(0,0,0);
    $toF = clone $to;
      $toF->setTime(23,59,59);

    $srchAddString='';

    if ($type1!='all') {
      $srchAddString.=" AND `type1` = '$type1'";
    }
    if ($type2!='all') {
      $srchAddString.=" AND `type2` = '$type2'";
    }

    $mysqli = Db::getInstance()->getConnection();
    $q = "SELECT * FROM doh_rash WHERE acc_date BETWEEN '".$fromF->getTimestamp()."' AND '".$toF->getTimestamp()."' ".$srchAddString;
    $result=$mysqli->query($q);

    if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
    $rez = [];
    while ($row = $result->fetch_assoc()){
      $rez[]=self::createFromDbArray($row);
    }

    return $rez;
  }

  /**
   * @param $id
   * @return DohRash|false|void
   */
  public static function getById($id)
  {
    $mysqli = Db::getInstance()->getConnection();
    $q = "SELECT * FROM doh_rash WHERE dr_id='$id'";

    $result=$mysqli->query($q);

    if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

    if ($result->num_rows>0) {
      $row = $result->fetch_assoc();
      return self::createFromDbArray($row);
    }
    else{
      return false;
    }
  }

  public function logBeforeDelete()
  {
    $this->setDelTime((new \DateTime()));
    $this->setDelWhoId(\bb\models\User::getCurrentUser()->getId());
    Base::logObjectToFile($this, 'dohrash');
    return true;
  }


  /**
   * @return true|void
   */
  private function insertNew(){
    $mysqli = Db::getInstance()->getConnection();

    $q = "INSERT INTO doh_rash SET
        dr_id='$this->id',
        acc_date='".$this->getAccDate()->getTimestamp()."',
        amount='$this->amount',
        type1='$this->type1',
        type2='$this->type2',
        channel='$this->channel',
        kassa='$this->kassa',
        link_to='$this->link_to',
        info='$this->info',
        cr_time='".$this->getCrTime()->getTimestamp()."',
        cr_who_id='$this->cr_who_id',
        dr_name_id='$this->dr_name_id'";

    $result=$mysqli->query($q);

    if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
    $this->id=$mysqli->insert_id;
    return true;
  }

  /**
   * @param \DateTime $acc_date
   * @param $amount
   * @param $kassaType
   * @param $officeId
   * @param $info
   * @param $userId
   * @return false|self
   */
  public static function sellTovarAmount(\DateTime $acc_date, $amount, $kassaType, $officeId, $info, $userId){
    $r = new self();
      $acc_date->setTime(0,0,0);
    $r->setAccDate($acc_date);
    $r->setAmount($amount);
    $r->setType1('doh');
    $r->setType2('prod_tovar');
    $r->setChannel($officeId);
    $r->setKassa($kassaType);
    $r->setInfo($info);
    $r->setCrWhoId($userId);

    if ($r->insertNew()) return $r;
    else return false;

  }


  public static function getShiftPlusForKassa(\DateTime $from, \DateTime $to, $kassa='')
  {
    if ($kassa=='') return false;
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT SUM(amount) as sum FROM doh_rash WHERE type1 = 'shift_plus' AND kassa='$kassa' AND acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return $result->fetch_assoc()['sum'];
  }

  public static function getShiftMinusForKassa(\DateTime $from, \DateTime $to, $kassa='')
  {
    if ($kassa=='') return false;
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT SUM(amount) as sum FROM doh_rash WHERE type1 = 'shift_minus' AND kassa='$kassa' AND acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return $result->fetch_assoc()['sum'];
  }

  public static function getDohForKassa(\DateTime $from, \DateTime $to, $kassa='')
  {
    if ($kassa=='') return false;
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT SUM(amount) as sum FROM doh_rash WHERE type1 = 'doh' AND kassa='$kassa' AND acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return $result->fetch_assoc()['sum'];
  }

  public static function getRashForKassa(\DateTime $from, \DateTime $to, $kassa='')
  {
    if ($kassa=='') return false;
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT SUM(amount) as sum FROM doh_rash WHERE type1 = 'rash' AND kassa='$kassa' AND acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return $result->fetch_assoc()['sum'];
  }

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @return int|mixed|void
   */
  public static function getDohRashSaldo(\DateTime $from, \DateTime $to, $exclude=false)
  {
    $srch = '';
    $mysqli = Db::getInstance()->getConnection();
    if ($exclude) {
      if (is_array($exclude)) {
        $srch = " AND type2 NOT IN('".join("','",$exclude)."')";
      }
      else{
        $srch = " AND type2 NOT IN($exclude)";
      }
    }
    $amount = 0;

    $query = "SELECT SUM(amount) as sum FROM doh_rash WHERE type1 NOT IN('shift_plus', 'shift_minus') $srch AND acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $amount += $result->fetch_assoc()['sum'];

    return $amount;
  }

  /**
   * @param array $types
   * @param \DateTime $from
   * @param \DateTime $to
   * @param array $zplIdsToFilter
   * @return mixed|void
   */
  public static function getDohRashByType2s(array $types, \DateTime $from, \DateTime $to, array $zplIdsToFilter=[]){
    $mysqli = Db::getInstance()->getConnection();

    $zplSrch='';
    if (is_array($zplIdsToFilter) && count($zplIdsToFilter)>0){
      $zplSrch = " AND dr_name_id IN('".join("','",$zplIdsToFilter)."')";
    }

    $query = "SELECT SUM(amount) as sum FROM doh_rash WHERE type2 IN('".join("','",$types)."') AND acc_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."' $zplSrch";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $amount = $result->fetch_assoc()['sum'];

    return $amount;
  }

}
