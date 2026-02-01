<?php

namespace bb\classes;

use bb\Db;

class Announcement
{
  private $id;
  private $typeCode;
  private $active; //1 or 0
  private $message;
  private $timeControll;//1 or 0
  /**
   * @var \DateTime
   */
  private $startTime;
  /**
   * @var \DateTime
   */
  private $finishTime;

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
  public function getTypeCode()
  {
    return $this->typeCode;
  }

  /**
   * @param mixed $typeCode
   */
  public function setTypeCode($typeCode): void
  {
    $this->typeCode = $typeCode;
  }

  /**
   * @return mixed
   */
  public function getActive()
  {
    return $this->active;
  }

  /**
   * @param mixed $active
   */
  public function setActive($active): void
  {
    $this->active = $active;
  }

  /**
   * @return mixed
   */
  public function getMessage()
  {
    return $this->message;
  }

  /**
   * @param mixed $message
   */
  public function setMessage($message): void
  {
    $this->message = $message;
  }

  /**
   * @return mixed
   */
  public function getTimeControll()
  {
    return $this->timeControll;
  }

  /**
   * @param mixed $timeControll
   */
  public function setTimeControll($timeControll): void
  {
    $this->timeControll = $timeControll;
  }

  /**
   * @return \DateTime
   */
  public function getStartTime()
  {
    return $this->startTime;
  }

  /**
   * @param \DateTime $startTime
   */
  public function setStartTime(\DateTime $startTime): void
  {
    $this->startTime = $startTime;
  }

  /**
   * @return \DateTime
   */
  public function getFinishTime()
  {
    return $this->finishTime;
  }

  /**
   * @param \DateTime $finishTime
   */
  public function setFinishTime(\DateTime $finishTime): void
  {
    $this->finishTime = $finishTime;
  }


  /**
   * @param $messageType
   * @return Announcement|false|void
   * @throws \Exception
   */
  public static function getMessageByType($messageType){
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * FROM announcement WHERE type_code='$messageType'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }
    if ($result->num_rows>0){
      return self::getFromDbArray($result->fetch_assoc());
    }
    else {
      return false;
    }
  }

  /**
   * @return bool|void
   */
  public function save(){
    $mysqli = Db::getInstance()->getConnection();

    if ($this->getId()>0) {

      $this->update();
      return true;
    }

    //else
    $query = "INSERT INTO announcement SET type_code='$this->typeCode', active='$this->active', message='".addslashes($this->message)."', time_controll='$this->timeControll', start_time='".($this->startTime ? $this->startTime->format("Y-m-d H:i:s") : '')."', finish_time='".($this->finishTime ? $this->finishTime->format("Y-m-d H:i:s") : '')."'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }

    $this->setId($mysqli->insert_id);
    return true;
  }

  /**
   * @return bool|void
   */
  public function update(){
    $mysqli = Db::getInstance()->getConnection();

    $query = "UPDATE announcement SET type_code='$this->typeCode', active='$this->active', message='".addslashes($this->message)."', time_controll='$this->timeControll', start_time='".($this->startTime ? $this->startTime->format("Y-m-d H:i:s") : '')."', finish_time='".($this->finishTime ? $this->finishTime->format("Y-m-d H:i:s") : '')."' WHERE id='$this->id'";
//    echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }

    return true;
  }

  /**
   * @param $arr
   * @return Announcement
   * @throws \Exception
   */
  private static function getFromDbArray($arr){
    $an = new self();
    $an->setId($arr['id']);
    $an->setTypeCode($arr['type_code']);
    $an->setActive($arr['active']);
    $an->setMessage(stripslashes($arr['message']));
    $an->setTimeControll($arr['time_controll']);
    if ($arr['start_time']){
      $an->setStartTime(new \DateTime($arr['start_time']));
    }
    if ($arr['finish_time']){
      $an->setFinishTime(new \DateTime($arr['finish_time']));
    }

    return $an;
  }

  public function toShow(){
    if (!$this->active) return false;
    if ($this->timeControll) {
      if (!$this->startTime || !$this->finishTime) return false;
      $now = new \DateTime();
      if ($now<$this->startTime) return false;
      if ($now>$this->finishTime) return false;
    }
    return true;
  }

}
