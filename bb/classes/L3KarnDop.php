<?php

namespace bb\classes;

use bb\Db;
use phpDocumentor\Reflection\Types\True_;

class L3KarnDop
{
  private $tarif;
  private $collateral;
  private $address;
  private $delivery;

  /**
   * @var L3KarnDop
   */
  private static $_self;

  public function __construct()
  {
  }

  /**
   * @return mixed
   */
  public function getTarif()
  {
    return $this->tarif;
  }

  /**
   * @param mixed $tarif
   */
  public function setTarif($tarif): void
  {
    $this->tarif = $tarif;
  }

  /**
   * @return mixed
   */
  public function getCollateral()
  {
    return $this->collateral;
  }

  /**
   * @param mixed $collateral
   */
  public function setCollateral($collateral): void
  {
    $this->collateral = $collateral;
  }

  /**
   * @return mixed
   */
  public function getAddress()
  {
    return $this->address;
  }

  /**
   * @param mixed $address
   */
  public function setAddress($address): void
  {
    $this->address = $address;
  }

  /**
   * @return mixed
   */
  public function getDelivery()
  {
    return $this->delivery;
  }

  /**
   * @param mixed $delivery
   */
  public function setDelivery($delivery): void
  {
    $this->delivery = $delivery;
  }


  /**
   * @return L3KarnDop|void
   */
  public static function get(){
    if (self::$_self) return self::$_self;

    $mysqli = Db::getInstance()->getConnection();
    $query="SELECT * FROM l3_karn_dop_fields WHERE id=1";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }

    $row = $result->fetch_assoc();

    $p = new self();
    $p->setTarif($row['tarif']);
    $p->setCollateral($row['collateral']);
    $p->setAddress($row['address']);
    $p->setDelivery($row['delivery']);

    self::$_self=$p;

    return $p;
  }

  /**
   * @return bool|void
   */
  public function save(){
    $mysqli = Db::getInstance()->getConnection();

    $query="UPDATE l3_karn_dop_fields SET tarif='$this->tarif', collateral='$this->collateral', address='$this->address', delivery='$this->delivery' WHERE id=1";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }

    return true;
  }




}
