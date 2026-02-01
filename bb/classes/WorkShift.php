<?php

namespace bb\classes;

use bb\Db;
use bb\models\User;

class WorkShift
{
  private $id;
  private $userId;
  private $placeType;//office, car, vacation, home
  private $placeId;

  /**
   * @var \DateTime
   */
  private $date;
  private $startTime;
  private $finishTime;

  /**
   * @var \DateTime
   */
  private $changeTime;

  /**
   * @return \DateTime
   */
  public function getChangeTime(): \DateTime
  {
    return $this->changeTime;
  }

  /**
   * @param \DateTime $changeTime
   */
  public function setChangeTime(\DateTime $changeTime): void
  {
    $this->changeTime = $changeTime;
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
   * @return mixed
   */
  public function getUserId()
  {
    return $this->userId;
  }

  /**
   * @param mixed $userId
   */
  public function setUserId($userId): void
  {
    $this->userId = $userId;
  }

  /**
   * @return mixed
   */
  public function getPlaceType()
  {
    return $this->placeType;
  }

  /**
   * @param mixed $placeType
   */
  public function setPlaceType($placeType): void
  {
    $this->placeType = $placeType;
  }

  /**
   * @return mixed
   */
  public function getPlaceId()
  {
    return $this->placeId;
  }

  /**
   * @param mixed $placeId
   */
  public function setPlaceId($placeId): void
  {
    $this->placeId = $placeId;
  }

  /**
   * @return \DateTime
   */
  public function getDate()
  {
    return $this->date;
  }

  /**
   * @param \DateTime $date
   */
  public function setDate(\DateTime $date): void
  {
    $this->date = $date;
  }

  /**
   * @return mixed
   */
  public function getStartTime()
  {
    return $this->startTime;
  }

  /**
   * @return \DateTime
   */
  public function getStartTimeObject(){
    $t = explode('.', $this->startTime);
    $date = new \DateTime();
      $date->setTime($t[0], $t[1]);
    return $date;
  }

  /**
   * @return \DateTime
   */
  public function getFinishTimeObject(){
    //var_dump($this->finishTime);
    $t = explode('.', $this->finishTime);
    $date = new \DateTime();
      $date->setTime($t[0], $t[1]);
    return $date;
  }

  /**
   * @param mixed $startTime
   */
  public function setStartTime($startTime): void
  {
    $this->startTime = $startTime;
  }

  /**
   * @return mixed
   */
  public function getFinishTime()
  {
    return $this->finishTime;
  }

  /**
   * @param mixed $finishTime
   */
  public function setFinishTime($finishTime): void
  {
    $this->finishTime = $finishTime;
  }

  /**
   * @return void
   */
  public function save(){

    if (!$this->isAllDataForSave()) die('Ошибка: недостаточно данных для сохранения. Сделайте скрин и сообщите Диме.');

    $mysqli = Db::getInstance()->getConnection();
    $query = "INSERT INTO work_shifts SET user_id='$this->userId', place_type='$this->placeType', place_id='$this->placeId', date='".$this->getDate()->format("Y-m-d")."', start_time='$this->startTime', finish_time='$this->finishTime'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
  }

  /**
   * @return bool
   */
  public function isAllDataForSave(){
    if ($this->userId<0 || $this->startTime=='' || $this->finishTime=='') return false;
    return true;
  }

  public static function deleteWeekForUserAndPlace($userId, $placeType, $placeId, \DateTime $mondayDate){
    $start = clone $mondayDate;
    $dayNum = $mondayDate->format('N');
    if ($dayNum!=1) {
      $start->modify('-'.($dayNum-1).' days');
    }
    $finish = clone $start;
      $finish->modify('+6 days');

    $mysqli = Db::getInstance()->getConnection();
    $query = "DELETE FROM work_shifts WHERE user_id='$userId' AND place_type='$placeType' AND place_id='$placeId' AND (date BETWEEN '".$start->format('Y-m-d')."' AND '".$finish->format('Y-m-d')."')";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при удалении данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    return true;
  }

  /**
   * @param $row
   * @return WorkShift
   * @throws \Exception
   */
  private static function createFromDbArray($row){
    $ws = new self();
    $ws->setId($row['id']);
    $ws->setUserId($row['user_id']);
    $ws->setPlaceType($row['place_type']);
    $ws->setPlaceId($row['place_id']);
    $ws->setDate(new \DateTime($row['date']));
    $ws->setStartTime($row['start_time']);
    $ws->setFinishTime($row['finish_time']);
    $ws->setChangeTime(new \DateTime($row['change_time']));

    return $ws;
  }

  /**
   * @param \DateTime $day
   * @param $placeType
   * @param $place_id
   * @return WorkShift[]|void
   * @throws \Exception
   */
  public static function getAllForDay(\DateTime $day, $placeType='office', $place_id=''){
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM work_shifts WHERE date='".$day->format('Y-m-d')."' AND place_type='$placeType' AND place_id='$place_id'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при подключении к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($result->num_rows<1) return [];
    $rez = [];
    while ($row = $result->fetch_assoc()) {
      $rez[]=self::createFromDbArray($row);
    }
    return $rez;
  }

  /**
   * @param \DateTime $day
   * @param $placeType
   * @param $place_id
   * @return WorkShift[]|void
   * @throws \Exception
   */
  public static function getAllForPeriod(\DateTime $startDay, \DateTime $finishDay){
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM work_shifts WHERE date BETWEEN '".$startDay->format('Y-m-d')."' AND '".$finishDay->format('Y-m-d')."'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при подключении к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($result->num_rows<1) return [];
    $rez = [];
    while ($row = $result->fetch_assoc()) {
      $rez[]=self::createFromDbArray($row);
    }
    return $rez;
  }

  /**
   * @param $userId
   * @param \DateTime $day
   * @param $placeType
   * @param $place_id
   * @return WorkShift[]|false|void
   * @throws \Exception
   */
  public static function getAllForUserDayPlace($userId, \DateTime $day, $placeType='office', $place_id=''){
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM work_shifts WHERE user_id=$userId AND date='".$day->format('Y-m-d')."' AND place_type='$placeType' AND place_id='$place_id'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при подключении к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($result->num_rows<1) return false;
    $rez = [];
    while ($row = $result->fetch_assoc()) {
      $rez[]=self::createFromDbArray($row);
    }
    return $rez;
  }

  /**
   * @param $userId
   * @param \DateTime $day
   * @param $placeType
   * @param $place_id
   * @return WorkShift[]|false|void
   * @throws \Exception
   */
  public static function getAllForWeekForUserPlace($userId, \DateTime $day, $placeType='office', $place_id=''){
    $dayEnd = clone  $day;
      $dayEnd->modify("+6 days");
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM work_shifts WHERE user_id=$userId AND (date BETWEEN '".$day->format('Y-m-d')."' AND '".$dayEnd->format('Y-m-d')."') AND place_type='$placeType' AND place_id='$place_id'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при подключении к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($result->num_rows<1) return false;
    $rez = [];
    while ($row = $result->fetch_assoc()) {
      $rez[]=self::createFromDbArray($row);
    }
    return $rez;
  }

  /**
   * @param $officeType
   * @param $officeId
   * @param \DateTime $fromDate
   * @param \DateTime $toDate
   * @return bool|void
   */
  public static function hasAnyForOfficeAndPriod($officeType, $officeId, \DateTime $fromDate, \DateTime $toDate){
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT id FROM work_shifts WHERE (date BETWEEN '".$fromDate->format('Y-m-d')."' AND '".$toDate->format('Y-m-d')."') AND place_type='$officeType' AND place_id='$officeId'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при подключении к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($result->num_rows<1) return false;
    else return true;
  }

  /**
   * @param $hourLength
   * @return float|int
   */
  public function getMargingLeft($hourLength=18){
    $startDate = clone $this->getStartTimeObject();
      $startDate->setTime(9,00);
      $hoursDiff = ($this->getStartTimeObject()->getTimestamp()-$startDate->getTimestamp()) / 60 / 60;
      return round($hoursDiff * $hourLength, 0);
  }

  /**
   * @param $hourLength
   * @return float|int
   */
  public function getWidth($hourLength=18){
    $hoursDiff = (-$this->getStartTimeObject()->getTimestamp()+$this->getFinishTimeObject()->getTimestamp()) / 60 / 60;
    return round($hoursDiff * $hourLength, 0);
  }

  /**
   * @return float
   */
  public function getHoursDiff(){
    $hoursDiff = ($this->getFinishTimeObject()->getTimestamp() - $this->getStartTimeObject()->getTimestamp()) / 60 / 60;
    if (round($hoursDiff,1)-round($hoursDiff,0) == 0) {
      return round($hoursDiff,0);
    }
    else {
      return round($hoursDiff,1);
    }
  }

  /**
   * @param \DateTime $startDate
   * @param \DateTime $endDate
   * @return array|false|void
   * @throws \Exception
   */
  public static function getWorkHorsCalculationForUsers(\DateTime $startDate, \DateTime $endDate){
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * FROM `work_shifts` WHERE place_type != 'vacation' AND (date BETWEEN'".$startDate->format('Y-m-d')."' AND '".$endDate->format('Y-m-d')."')";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при подключении к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($result->num_rows<1) {
      return false;
    }


    $rez=[];

    while ($row = $result->fetch_assoc()) {
      $shift = self::createFromDbArray($row);
//      echo $shift->getId().'---'.$shift->getHoursDiff().'<br>';
      if (!isset($rez[$shift->getUserId()])) {
        $rez[$shift->getUserId()] = [User::getUserById($shift->getUserId())->getShortName(), $shift->getHoursDiff()];
      }
      else{
        $rez[$shift->getUserId()][1] +=  $shift->getHoursDiff();
      }
    }

    return $rez;
  }

}
