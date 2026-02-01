<?php

namespace bb\classes;

use bb\Db;

class Permission
{
  private $id;
  private $int_code;

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
  public function getIntCode()
  {
    return $this->int_code;
  }

  /**
   * @param mixed $int_code
   */
  public function setIntCode($int_code): void
  {
    $this->int_code = $int_code;
  }



  /**
   * @param $userId
   * @return array|void
   */
  public static function getIntCodeArrayForUserId($userId){
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM user_permissions WHERE user_id='$userId'";
    $result = $mysqli->query($query);
    if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}
    if ($result->num_rows<1) {
      return [];
    }
    else{
      $arr=[];
      while ($row=$result->fetch_assoc()) {
        $arr[]=$row['permission_id'];
      }
      return $arr;
    }


  }

}
