<?php

namespace bb\classes;

use bb\Base;
use bb\Db;

class Subscription
{
  private $id;
  private $email;

  /**
   * @var \DateTime
   */
  private $crDate;

  public function __construct()
  {
    $this->crDate = new \DateTime();
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
  public function getEmail()
  {
    return $this->email;
  }

  /**
   * @param mixed $email
   */
  public function setEmail($email): void
  {
    $this->email = Base::cleanEmail($email);
  }

  /**
   * @return \DateTime
   */
  public function getCrDate(): \DateTime
  {
    return $this->crDate;
  }

  /**
   * @param \DateTime $crDate
   */
  public function setCrDate(\DateTime $crDate): void
  {
    $this->crDate = $crDate;
  }



  /**
   * @return bool|void
   */
  public function save(){
    if ($this->id>0) return $this->update();

    $mysqli = Db::getInstance()->getConnection();

    $query = "INSERT INTO subscriptions SET email='$this->email', cr_date='".$this->crDate->format('Y-m-d H:i')."'";
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

    $query = "UPDATE subscriptions SET email='$this->email', cr_date='".$this->crDate->format('Y-m-d H:i')."' WHERE id='$this->id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }
    return true;
  }


  /**
   * @return Subscription[]|false|void
   * @throws \Exception
   */
  public static function getAll(){
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * FROM subscriptions";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }

    if ($result->num_rows<1) return false;

    $rez = [];

    while ($row = $result->fetch_assoc()) {
      $rez[] = self::createFromDBArray($row);
    }

    return $rez;

  }

  /**
   * @param $row
   * @return Subscription
   * @throws \Exception
   */
  private static function createFromDBArray($row){
    $s = new self();

    $s->setId($row['id']);
    $s->setEmail($row['email']);
    $s->setCrDate(new \DateTime($row['cr_date']));

    return$s;
  }

}
