<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 29.05.2019
 * Time: 22:27
 */

namespace bb\models;


use bb\Base;
use bb\Db;

class Office
{
    /**
     * @var Office[]
     */
    private static $_all_offices;

    public $id_office;
    public $type;//office, courier
    public $number;
    public $pic_addr;
    public $name_short;
    public $address_short;
    public $active;
    public $off_ip;
    public $cssColor;
    private $openWeekHours;
    private $openWeekMinutes;
    private $closeWeekHours;
    private $closeWeekMinutes;
    private $openWeekEndHours;
    private $openWeekEndMinutes;
    private $closeWeekEndHours;
    private $closeWeekEndMinutes;

  /**
   * @return mixed
   */
  public function getIdOffice()
  {
    return $this->id_office;
  }

  /**
   * @param mixed $id_office
   */
  public function setIdOffice($id_office)
  {
    $this->id_office = $id_office;
  }

  /**
   * @return mixed
   */
  public function getType()
  {
    return $this->type;
  }

  /**
   * @param mixed $type
   */
  public function setType($type)
  {
    $this->type = $type;
  }

  /**
   * @return mixed
   */
  public function getPicAddr()
  {
    return $this->pic_addr;
  }

  /**
   * @param mixed $pic_addr
   */
  public function setPicAddr($pic_addr)
  {
    $this->pic_addr = $pic_addr;
  }

  /**
   * @return mixed
   */
  public function getNameShort()
  {
    return $this->name_short;
  }

  /**
   * @param mixed $name_short
   */
  public function setNameShort($name_short)
  {
    $this->name_short = $name_short;
  }

  /**
   * @return mixed
   */
  public function getAddressShort()
  {
    return $this->address_short;
  }

  /**
   * @param mixed $address_short
   */
  public function setAddressShort($address_short)
  {
    $this->address_short = $address_short;
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
  public function setActive($active)
  {
    $this->active = $active;
  }

  /**
   * @return mixed
   */
  public function getOffIp()
  {
    return $this->off_ip;
  }

  /**
   * @param mixed $off_ip
   */
  public function setOffIp($off_ip)
  {
    $this->off_ip = $off_ip;
  }

  /**
   * @param \DateTime $date
   * @return \DateTime
   */
  public function getOpenDateTimeObject(\DateTime $date){
    $weekDay = $date->format('N');
    $returnDate = clone $date;
    if ($weekDay<6) {
      $returnDate->setTime($this->openWeekHours, $this->openWeekMinutes);
    }
    else{
      $returnDate->setTime($this->openWeekEndHours, $this->openWeekEndMinutes);
    }

    return $returnDate;
  }

  public function setWorkingTime($openWeekHours, $openWeekMinutes, $closeWeekHours, $closeWeekMinutes, $openWeekEndHours, $openWeekEndMinutes, $closeWeekEndHours, $closeWeekEndMinutes){
    $this->openWeekHours = $openWeekHours;
    $this->openWeekMinutes = $openWeekMinutes;
    $this->closeWeekHours = $closeWeekHours;
    $this->closeWeekMinutes = $closeWeekMinutes;

    $this->openWeekEndHours = $openWeekEndHours;
    $this->openWeekEndMinutes = $openWeekEndMinutes;
    $this->closeWeekEndHours = $closeWeekEndHours;
    $this->closeWeekEndMinutes = $closeWeekEndMinutes;
  }

  /**
   * @param \DateTime $date
   * @return \DateTime
   */
  public function getCloseDateTimeObject(\DateTime $date){
    $weekDay = $date->format('N');
    $returnDate = clone $date;
    if ($weekDay<6) {
      $returnDate->setTime($this->closeWeekHours, $this->closeWeekMinutes);
    }
    else{
      $returnDate->setTime($this->closeWeekEndHours, $this->closeWeekEndMinutes);
    }

    return $returnDate;
  }


  /**
   * @param \DateTime $date
   * @return string[]
   */
  public function getOpenHoursMinutesArrayForDate(\DateTime $date){
    if ($date->format("N")<6) {
      $hours = $this->openWeekHours;
      $minutes = $this->openWeekMinutes;
    }
    else{
      $hours = $this->openWeekEndHours;
      $minutes = $this->openWeekEndMinutes;
    }

    $hours<10 ? $hours='0'.$hours : '';
    $minutes<10 ? $minutes='0'.$minutes : '';

    return [$hours, $minutes];
  }

  /**
   * @param \DateTime $date
   * @return string[]
   */
  public function getCloseHoursMinutesArrayForDate(\DateTime $date){
    if ($date->format("N")<6) {
      $hours = $this->closeWeekHours;
      $minutes = $this->closeWeekMinutes;
    }
    else{
      $hours = $this->closeWeekEndHours;
      $minutes = $this->closeWeekEndMinutes;
    }

    $hours<10 ? $hours='0'.$hours : '';
    $minutes<10 ? $minutes='0'.$minutes : '';

    return [$hours, $minutes];
  }


    public function getOfficesKassaOperations(User $user){

    }

    public static function changeOfficeLoggeIn($off_num){
        $_SESSION['office']=$off_num;
    }

    public static function getOfficePicAddress($office_num){
        switch ($office_num) {
            case 1:
                return '/bb/1_1.png';
                break;
            case 2:
                return '/bb/1_2.png';
                break;
            case 3:
                return '/bb/1_3.png';
                break;
            case 4:
                return '/bb/1_4.jpg';
                break;
            default:
                return null;
                break;
        }
    }


    /**
     * @return Office[]
     */
    public static function getAllOffices($type='office', $ip='all', $active=1) {
        $rez=array();

        $conditions=array();

        $mysqli = Db::getInstance()->getConnection();

        if ($type!='all') {
            $conditions[]="`type`='$type'";
        }
        if ($ip!='all') {
            $conditions[]="`off_ip`='$ip'";
        }
        if ($active!='all') {
            $conditions[]="`active`='$active'";
        }

        //Base::varDamp($conditions);


        $cond_add=Db::makeQueryConditionFromArray($conditions);

        $query= "SELECT * FROM offices $cond_add";
        //echo $query;
        $result = $mysqli->query($query);
        while ($row=$result->fetch_assoc()) {
            $rez[]=self::loadFromDbArray($row);
        }

        return $rez;
    }

    /**
     * @param $arr
     * @return Office
     */
    public static function loadFromDbArray($arr){
        $of = new self();

        $of->id_office=$arr['id'];
        $of->type=$arr['type'];
        $of->number=$arr['number'];
        $of->pic_addr=$arr['pic_addr'];
        $of->name_short=$arr['name'];
        $of->address_short=$arr['short_address'];
        $of->active=$arr['active'];
        $of->off_ip=$arr['off_ip'];
        $of->cssColor=$arr['css_color'];

        $of->openWeekHours=$arr['openWeekHours'];
        $of->openWeekMinutes=$arr['openWeekMinutes'];
        $of->closeWeekHours=$arr['closeWeekHours'];
        $of->closeWeekMinutes=$arr['closeWeekMinutes'];
        $of->openWeekEndHours=$arr['openWeekEndHours'];
        $of->openWeekEndMinutes=$arr['openWeekEndMinutes'];
        $of->closeWeekEndHours=$arr['closeWeekEndHours'];
        $of->closeWeekEndMinutes=$arr['closeWeekEndMinutes'];

        return $of;
    }

    public static function checkIpForOffice($num){

        if (self::getOfficeByNumber($num)->off_ip!=$_SERVER['REMOTE_ADDR']) {
            return false;
        }
        else {
            return true;
        }
    }

    public function getShortName() {
        return $this->name_short;
    }

    public function getNumber(){
        return $this->number;
    }

    public function getFullName() {
        return $this->name_short;
    }

    public static function isOfficeChosen(){
        if ($of = self::getOfficeFromSession()){
            if ($of->number>0) {
                return true;
            }
        }
        else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function sessionRegister(){
        if ($this->id_office<1) die('no office provided');
        //old register
        $_SESSION['office']=$this->number;


        $_SESSION['office_s']=array();
        foreach ($this as $key=>$value) {
            $_SESSION['office_s'][$key]=$value;
        }
        return true;
    }

    /**
     * @return string
     */
    public function getOneLetterName(){
        switch ($this->number) {
            case 2:
                return 'У';
                break;
            default:
                return mb_substr($this->getFullName(),0,1, 'UTF-8');

        }
    }


    /**
     * @param $num
     * @param $type
     * @return Office|false
     */
    public static function getOfficeByNumber($num, $type='office')
    {//type = office, courier
        if (isset(self::$_all_offices[$num])) return self::$_all_offices[$num];
        else {

            $mysqli = Db::getInstance()->getConnection();

            $query = "SELECT * FROM offices WHERE `type`='$type' AND `number`='$num'";
            //echo $query;
            $result = $mysqli->query($query);
            if ($result->num_rows>0) {
                $row = $result->fetch_assoc();
                $rez = self::loadFromDbArray($row);

                self::$_all_offices[$num] = $rez;

                return $rez;
            }
            else return false;
        }
    }


    /**
     * @return mixed
     */
    public function getCssColor(){
        return $this->cssColor;
    }

    /**
     * @return Office|bool
     */
    public static function getOfficeFromSession(){
        //echo 'S_session';
        //Base::varDamp($_SESSION['office_s']);
        $of=new Office();
        if (isset($_SESSION['office_s'])) {
            foreach ($of as $key=>$value){
                if (isset($_SESSION['office_s'][$key])) {
                    $of->$key=$_SESSION['office_s'][$key];
                }
            }
            return $of;
        }
        else{
            return false;
        }
    }

    /**
     * @return Office[]
     */
    public static function getAllActiveOffices(){
        return self::getAllOffices();
    }

    ///!!!

    /**
     * @return Office
     */
    public static function getCurrentOffice(){
        $of1=self::getOfficeFromSession();
//        $of1=new Office();
//        $of1->id_office=$_SESSION['office'];
//        $of1->number=$_SESSION['office'];
//        $of1->name_short=self::getOfficeNameByNumber($of1->number);

        if (!$of1) {//if ald office - remove after of rework
            $of1=new Office();
            $of1->id_office=$_SESSION['office'];
            $of1->number=$_SESSION['office'];
            $of1->name_short=self::getOfficeNameByNumber($of1->number);
        }

        return $of1;
    }

    public static function getOfficeNameByNumber($of_num, $str_len=0) {
        //echo '<br><br>'.$of_num;
        switch ($of_num) {
            case 1:
                $rez= 'Литературная';
                break;
            case 2:
                $rez= 'Ложинская';
                break;
            case 3:
                $rez= 'Победителей';
                break;
            case 4:
                $rez='Склад';
                break;
            default:
                $rez='Не определен';
                break;
        }
        if ($str_len!=0) {
            $rez = mb_substr($rez,0,$str_len, 'UTF-8');
        }
        return $rez;
    }

    public static function getOpenHours($office_num, $days){
        ///...
    }

}
