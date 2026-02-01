<?php

namespace bb\classes;

use bb\Db;

class VideoYt
{
  private $id;
  private $link;
  private $name;
  private $orderNumber;
  private $isIlia;
  private $isMasha;

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
  public function getLink()
  {
    return $this->link;
  }

  /**
   * @param mixed $link
   */
  public function setLink($link): void
  {
    $this->link = $link;
  }

  /**
   * @return mixed
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * @param mixed $name
   */
  public function setName($name): void
  {
    $this->name = $name;
  }

  /**
   * @return mixed
   */
  public function getOrderNumber()
  {
    return $this->orderNumber;
  }

  /**
   * @param mixed $orderNumber
   */
  public function setOrderNumber($orderNumber): void
  {
    $this->orderNumber = $orderNumber;
  }

  /**
   * @return mixed
   */
  public function getIsIlia()
  {
    return $this->isIlia;
  }

  /**
   * @param mixed $isIlia
   */
  public function setIsIlia($isIlia): void
  {
    $this->isIlia = $isIlia;
  }

  /**
   * @return mixed
   */
  public function getIsMasha()
  {
    return $this->isMasha;
  }

  /**
   * @param mixed $isMasha
   */
  public function setIsMasha($isMasha): void
  {
    $this->isMasha = $isMasha;
  }


  /**
   * @return VideoYt[]|false|void
   */
  public static function getAll(){
    $mysqli = Db::getInstance()->getConnection();

    $query= "SELECT * FROM video_links";

    $result = $mysqli->query($query);

    if (!$result) {
      die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
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
   * @return VideoYt
   */
  private static function createFromDbArray($row){
    $v = new self();
      $v->setId($row['id']);
      $v->setLink($row['link']);
      $v->setName($row['name']);
      $v->setOrderNumber($row['order_number']);
      $v->setIsIlia($row['is_ilia']);
      $v->setIsMasha($row['is_masha']);
    return $v;
  }

}
