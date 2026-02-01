<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 29.05.2019
 * Time: 22:11
 */

namespace bb;


class KassaOstatok
{
  public $k_id;

  /**
   * @var \DateTime
   */
  public $acc_date;//db - timestamp

  //public $le_id;
  public $channel; //office id
  public $kassa;//k1, k2

  public $k_amount_start;
  public $sales;
  public $doh_rash; //+doh, - rash
  public $k_amount_end;

  public $cr_who;
  /**
   * @var \DateTime
   */
  public $cr_when;
  public $k_type;


  /**
   * @return mixed
   */
  public function getKId()
  {
    return $this->k_id;
  }

  /**
   * @param mixed $k_id
   */
  public function setKId($k_id): void
  {
    $this->k_id = $k_id;
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
   */
  public function setAccDate(\DateTime $acc_date): void
  {
    $this->acc_date = $acc_date;
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
  public function getKAmountStart()
  {
    return $this->k_amount_start;
  }

  /**
   * @param mixed $k_amount_start
   */
  public function setKAmountStart($k_amount_start): void
  {
    $this->k_amount_start = $k_amount_start;
  }

  /**
   * @return mixed
   */
  public function getSales()
  {
    return $this->sales;
  }

  /**
   * @param mixed $sales
   */
  public function setSales($sales): void
  {
    $this->sales = $sales;
  }

  /**
   * @return mixed
   */
  public function getDohRash()
  {
    return $this->doh_rash;
  }

  /**
   * @param mixed $doh_rash
   */
  public function setDohRash($doh_rash): void
  {
    $this->doh_rash = $doh_rash;
  }

  /**
   * @return mixed
   */
  public function getKAmountEnd()
  {
    return $this->k_amount_end;
  }

  /**
   * @param mixed $k_amount_end
   */
  public function setKAmountEnd($k_amount_end): void
  {
    $this->k_amount_end = $k_amount_end;
  }

  /**
   * @return mixed
   */
  public function getCrWho()
  {
    return $this->cr_who;
  }

  /**
   * @param mixed $cr_who
   */
  public function setCrWho($cr_who): void
  {
    $this->cr_who = $cr_who;
  }

  /**
   * @return \DateTime
   */
  public function getCrWhen(): \DateTime
  {
    return $this->cr_when;
  }

  /**
   * @param \DateTime $cr_when
   */
  public function setCrWhen(\DateTime $cr_when): void
  {
    $this->cr_when = $cr_when;
  }

  /**
   * @return mixed
   */
  public function getKType()
  {
    return $this->k_type;
  }

  /**
   * @param mixed $k_type
   */
  public function setKType($k_type): void
  {
    $this->k_type = $k_type;
  }//final, final2 (but I donk know why)


  /**
   * @return true
   */
  public static function calculateAndSaveAllUpToday(){
    $today = new \DateTime();
      $today->setTime(0,0,0);
    $yesterday = clone $today;
      $yesterday->modify('-1 day');

    $officeList = \bb\models\Office::getAllActiveOffices();

    $kassaList = ['k1', 'k2'];

    foreach ($officeList as $office) {
      foreach ($kassaList as $k) {

        $lastAvailableOstatok = self::getCurrentOrLastPreviousOstatokForDate($yesterday,$office->getNumber(),$k);

        $nextToLastOstatokDate = clone $lastAvailableOstatok->getAccDate();
          $nextToLastOstatokDate->modify('+1 day');

        self::recalculateOstatkiFromStart($nextToLastOstatokDate,$today,$office->getNumber(),$k);
      }
    }
    return true;
  }

  /**
   * @return void
   */
  public function save(){
    if ($this->k_id>0) {
      $this->update();
    }
    else{
      $this->insertNew();
    }
  }

  /**
   * @return bool|void
   */
  private function insertNew(){
    if (isset($_SESSION['user_id'])) {
      $userId=\bb\models\User::getCurrentId();
    }
    else{
      $userId=0;
    }

    $mysqli = Db::getInstance()->getConnection();
    $query = "INSERT into kassas SET acc_date='".$this->acc_date->getTimestamp()."', channel='$this->channel', kassa='$this->kassa', k_amount_start='$this->k_amount_start', sales='$this->sales', doh_rash='$this->doh_rash', k_amount_end='$this->k_amount_end', cr_who='$userId', cr_when='".time()."', k_type='final'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обращзении к базе MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
      return false;
    }
    return true;
  }

  /**
   * @return bool|void
   */
  private function update(){
    if (isset($_SESSION['user_id'])) {
      $userId=\bb\models\User::getCurrentId();
    }
    else{
      $userId=0;
    }

    $mysqli = Db::getInstance()->getConnection();
    $query = "UPDATE kassas SET acc_date='".$this->acc_date->getTimestamp()."', channel='$this->channel', kassa='$this->kassa', k_amount_start='$this->k_amount_start', sales='$this->sales', doh_rash='$this->doh_rash', k_amount_end='$this->k_amount_end', cr_who='$userId', cr_when='".time()."', k_type='final' WHERE k_id='$this->k_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обращзении к базе MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
      return false;
    }
    return true;
  }

  /**
   * @param array $row
   * @return KassaOstatok
   */
  private static function createFromDbArray(array $row){
    //Base::varDamp($row);
    $o = new self();
    $o->setKId($row['k_id']);
    $accDate = new \DateTime();
      $accDate->setTimestamp($row['acc_date']);
      $o->setAccDate($accDate);
    $o->setChannel($row['channel']);
    $o->setKassa($row['kassa']);
    $o->setKAmountStart($row['k_amount_start']);
    $o->setSales($row['sales']);
    $o->setDohRash($row['doh_rash']);
    $o->setKAmountEnd($row['k_amount_end']);
    $o->setCrWho($row['cr_who']);
    $crWhen = new \DateTime();
      $crWhen->setTimestamp($row['cr_when']);
      $o->setCrWhen($crWhen);
    $o->setKType($row['k_type']);

    return $o;
  }

  /**
   * @param \DateTime $accDate
   * @param $officeId
   * @param $kassaType
   * @return int|mixed|void
   */
  public static function getCalculatedSales(\DateTime $accDate, $officeId, $kassaType){
    $mysqli = Db::getInstance()->getConnection();

    switch ($kassaType) {
      case 'k1':
        $kType='nal_cheque';
        break;
      case 'k2':
        $kType='nal_no_cheque';
        break;
      default:
        $kType='';
        break;
    }

    $tables = ['rent_sub_deals_act', 'rent_sub_deals_arch'];
    $sales = 0;

    foreach ($tables as $table){
      $query = "SELECT SUM(r_paid) as sales FROM $table WHERE acc_date='".$accDate->getTimestamp()."' AND place=$officeId AND r_payment_type='$kType'";
      //echo $query.'<br>';
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при обращении к базе MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
        return false;
      }
      $sales+=$result->fetch_assoc()['sales'];
    }

    foreach ($tables as $table){
      $query = "SELECT SUM(delivery_paid) as sales FROM $table WHERE acc_date='".$accDate->getTimestamp()."' AND place=$officeId AND del_payment_type='$kType'";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при обращении к базе MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
        return false;
      }
      $sales+=$result->fetch_assoc()['sales'];
    }

    return $sales;
  }

  /**
   * @param \DateTime $accDate
   * @param $officeId
   * @param $kassaType
   * @return int|mixed|void
   */
  public static function getCalculatedDohRash(\DateTime $accDate, $officeId, $kassaType){
    $mysqli = Db::getInstance()->getConnection();

    $tables = ['rent_sub_deals_act', 'rent_sub_deals_act'];
    $sales = 0;

    $query = "SELECT SUM(amount) as dohrash FROM doh_rash WHERE acc_date='".$accDate->getTimestamp()."' AND channel=$officeId AND kassa='$kassaType'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обращении к базе MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
      return false;
    }
    $sales+=$result->fetch_assoc()['dohrash'];

    return $sales;
  }

  /**
   * @param \DateTime $accDate
   * @param $kassa
   * @return KassaOstatok|false|void
   */
  public static function getOstatokForDate(\DateTime $accDate, $officeId, $kassa){
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM kassas WHERE channel='$officeId' AND kassa = '$kassa' AND acc_date=".$accDate->getTimestamp()." ORDER BY cr_when DESC LIMIT 1";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обращзении к базе MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
      return false;
    }
    if ($result->num_rows<1) {
      return false;
    }
    else{
      return self::createFromDbArray($result->fetch_assoc());
    }
  }

  /**
   * @param \DateTime $accDate
   * @param $kassa
   * @return KassaOstatok|false|void
   */
  public static function getCurrentOrLastPreviousOstatokForDate(\DateTime $accDate, $officeId, $kassa){

    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM kassas WHERE kassa = '$kassa' AND channel='$officeId' AND acc_date<=".$accDate->getTimestamp()." ORDER BY acc_date DESC, cr_when DESC LIMIT 1";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обращзении к базе MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
      return false;
    }
    if ($result->num_rows<1) {
      return false;
    }
    else{
      return self::createFromDbArray($result->fetch_assoc());
    }
  }

  /**
   * @param \DateTime $accDate
   * @param $kassa
   * @return KassaOstatok|false|void
   */
  public static function getCurrentOrNextOstatokForDate(\DateTime $accDate, $officeId, $kassa){

    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM kassas WHERE kassa = '$kassa' AND channel='$officeId' AND acc_date>=".$accDate->getTimestamp()." ORDER BY acc_date, cr_when DESC LIMIT 1";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обращзении к базе MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
      return false;
    }
    if ($result->num_rows<1) {
      return false;
    }
    else{
      return self::createFromDbArray($result->fetch_assoc());
    }
  }

  /**
   * @param $startAmount
   * @return bool
   */
  public function calculateFromStart($startAmount){
    $this->setKAmountStart($startAmount);

    $sales = self::getCalculatedSales($this->getAccDate(), $this->getChannel(), $this->getKassa());
      if($sales) $this->setSales($sales);
    $dohRash = self::getCalculatedDohRash($this->getAccDate(), $this->getChannel(), $this->getKassa());
      if ($dohRash) $this->setDohRash($dohRash);

    $this->setKAmountEnd($this->getKAmountStart()+$this->getSales()+$this->getDohRash());

    return true;
  }

  /**
   * @param $endAmount
   * @return bool
   */
  public function calculateFromEnd($endAmount){
    $this->setKAmountEnd($endAmount);

    $sales = self::getCalculatedSales($this->getAccDate(), $this->getChannel(), $this->getKassa());
      if($sales) $this->setSales($sales*1);
    $dohRash = self::getCalculatedDohRash($this->getAccDate(), $this->getChannel(), $this->getKassa());
      if ($dohRash) $this->setDohRash($dohRash*1);

    $this->setKAmountStart($this->getKAmountEnd()-$this->getSales()-$this->getDohRash());

    return true;
  }

  public static function recalculateOstatkiFromEnd(\DateTime $endDate, \DateTime  $startDate, $officeId, $kassa){
    if ($endDate<$startDate) {
      echo 'Ошибка: дата конца, должна быть больше либо равна дате начала';
      return false;
    }
    $mainOstatok = self::getOstatokForDate($endDate, $officeId, $kassa);
    if (!$mainOstatok) {
      echo 'Ошибка: на конечную дату должен быть сохранен хотя бы 1 остаток, выберите другую конечную дату.';
      return false;
    }

    //Base::varDamp($mainOstatok);

    $d=clone $endDate;
      $d->setTime(0,0,0);
      $d->modify('-1 day');

    $iteration=0;
    while ($d>=$startDate) {
      $o = new self();
      $o->setAccDate(clone $d);
      $o->setChannel($officeId);
      $o->setKassa($kassa);
      $o->calculateFromEnd($mainOstatok->getKAmountStart());
      //Base::varDamp($o);
      $o->save();

      $mainOstatok=$o;
      $d->modify('-1 day');
      $iteration++;

      if ($iteration>365) break;
    }
    return $iteration;
  }

  /**
   * @param \DateTime $startDate
   * @param \DateTime $endDate
   * @param $officeId
   * @param $kassa
   * @return false|int
   */
  public static function recalculateOstatkiFromStart(\DateTime $startDate, \DateTime $endDate, $officeId, $kassa){
//    echo $startDate->format('Y-m-d').'---'.$endDate->format('Y-m-d').'<br>';
//    return false;
    if ($endDate<$startDate) {
      echo 'Ошибка: дата конца, должна быть больше либо равна дате начала';
      return false;
    }

    $beforeStartDate = clone $startDate;
      $beforeStartDate->setTime(0,0,0);
      $beforeStartDate->modify('-1 day');

    $mainOstatok = self::getOstatokForDate($beforeStartDate, $officeId, $kassa);
    if (!$mainOstatok) {
      echo 'Ошибка: на дату, предшествующую начальной должен быть сохранен хотя бы 1 остаток, выберите начальную конечную дату.';
      return false;
    }

    $d=clone $startDate;
      $d->setTime(0,0,0);
      //$d->modify('-1 day');
    $iteration=0;

    while ($d<=$endDate) {
      $o = new self();
      $o->setAccDate(clone $d);
      $o->setChannel($officeId);
      $o->setKassa($kassa);
      $o->calculateFromStart($mainOstatok->getKAmountEnd());
      //Base::varDamp($o);
      $o->save();

      $mainOstatok=$o;
      $d->modify('+1 day');
      $iteration++;

      if ($iteration>365) break;
    }

    return $iteration;

  }

}
