<?php

namespace bb\classes;

use bb\Base;

class KbZayavka
{
  private $id;
  private $model_id;
  /**
   * @var \DateTime
   */
  private $event_date;
  private $rost_from;
  private $rost_to;
  private $phone;
  private $info;
  /**
   * @var \DateTime
   */
  private $cr_when;

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
  public function getModelId()
  {
    return $this->model_id;
  }

  /**
   * @param mixed $model_id
   */
  public function setModelId($model_id): void
  {
    $this->model_id = $model_id;
  }

  /**
   * @return \DateTime
   */
  public function getEventDate(): \DateTime
  {
    return $this->event_date;
  }

  /**
   * @param \DateTime $event_date
   */
  public function setEventDate(\DateTime $event_date): void
  {
    $this->event_date = $event_date;
  }

  /**
   * @return mixed
   */
  public function getRostFrom()
  {
    return $this->rost_from;
  }

  /**
   * @param mixed $rost_from
   */
  public function setRostFrom($rost_from): void
  {
    $this->rost_from = $rost_from;
  }

  /**
   * @return mixed
   */
  public function getRostTo()
  {
    return $this->rost_to;
  }

  /**
   * @param mixed $rost_to
   */
  public function setRostTo($rost_to): void
  {
    $this->rost_to = $rost_to;
  }

  /**
   * @return mixed
   */
  public function getPhone()
  {
    return $this->phone;
  }

  /**
   * @param mixed $phone
   */
  public function setPhone($phone): void
  {
    $this->phone = $phone;
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
   * @return KbZayavka[]|false|void
   */
  public static function getAll(){
    $mysqli = \bb\Db::getInstance()->getConnection();
    $query = "SELECT * FROM `kb_zayavki` ORDER BY event_date";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при запросе к MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
    }
    if ($result->num_rows<1) return false;

    $rez=[];
    while ($row = $result->fetch_assoc()) {
      $rez[] = self::createFromDbArray($row);
    }

    return $rez;

  }


  /**
   * @param $row
   * @return KbZayavka
   */
  public static function createFromDbArray($row){
    $z = new self();
    $z->setId($row['id']);
    $z->setModelId($row['model_id']);
      $eventDate=new \DateTime($row['event_date']);
    $z->setEventDate($eventDate);
    $z->setRostFrom($row['rost_from']);
    $z->setRostTo($row['rost_to']);
    $z->setPhone($row['phone']);
    $z->setInfo($row['info']);
      $crDate = new \DateTime();
      $crDate->setTimestamp($row['cr_when']);
    $z->setCrWhen($crDate);

    return $z;
  }

}
