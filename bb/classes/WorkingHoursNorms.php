<?php

namespace bb\classes;

use bb\Db;

class WorkingHoursNorms
{
  private $year;
  private $months;

  /**
   * @return bool|void
   */
  private function delete(){
    $mysqli = Db::getInstance()->getConnection();

    $query = "DELETE FROM info_work_hours_months WHERE year='$this->year'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при подключении к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    return true;
  }

  /**
   * @return bool|void
   */
  public function save(){
    
    if ($this->year<1) return false;
    $mysqli = Db::getInstance()->getConnection();

    $this->delete();

    for ($i=1; $i<=12; $i++) {
      if (isset($this->months[$i])) {
        $hours=$this->months[$i];

        $query = "INSERT INTO info_work_hours_months SET  year='$this->year', month='$i', hours='$hours'";
        $result = $mysqli->query($query);
        if (!$result) die('Сбой при подключении к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
      }
    }
    return true;
  }


  /**
   * @param $monthNum
   * @param $hours
   * @return bool
   */
  public function setHours($monthNum, $hours){
    if ($monthNum<1 || $monthNum>12 || $hours<0) return false;

    $this->months[$monthNum]=$hours;
    return true;
  }

  /**
   * @param $year
   * @return WorkingHoursNorms
   */
  public static function getWorkingHoursNorms($year){
    return new self($year);
  }

  /**
   * @param $monthNum
   * @return int|mixed
   */
  public function getWorkingHoursForMonth($monthNum){
    if (isset($this->months[$monthNum])) return $this->months[$monthNum];
    else return 0;
  }

  public function __construct($year)
  {
    $this->year=$year;
    $this->months=[];

    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * FROM info_work_hours_months WHERE year='$this->year'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при подключении к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    if ($result->num_rows>0) {
      while ($row = $result->fetch_assoc()) {
        $this->months[$row['month']]=$row['hours'];
      }
    }
  }
}
