<?php

namespace bb\classes;

use bb\Db;

class Zvonok
{
    public $id;
    public $z_name;
    public $pr_time;
    public $operator;
    public $phone;
    public $tema;
    public $info;
    public $cr_time;
    public $status; //new, done
    public $react_time;
    public $person_id;

  public $validityDaysNum;
  public $type1;//'', zayavka
  public $model_id;

    public $zv_arch_id;
  /**
   * @var \DateTime
   */
  public $zv_arch_time;

    private $messages;




    public function save(){
        $mysqli=Db::getInstance()->getConnection();

        //spam filter
        if ($this->isSpam()) return false;


        $query_zv = "INSERT INTO zvonki SET
                    z_name = '$this->z_name',
                    pr_time = 0,
                    operator = '$this->operator',
                    phone = '$this->phone',
                    tema = '$this->tema',
                    info = '".(str_replace("'", '', $this->info))."',
                    cr_time = '".time()."',
                    `status` = 'new',
                    react_time = 0,
                    person_id = 0,
                    validity_days = '$this->validityDaysNum',
                    type1 = '$this->type1',
                    model_id = '$this->model_id'
                    ";

        if (!$mysqli->query($query_zv)) {
            $this->messages="По техническим причинам Ваша заявка не была отправлена. <br /> Приносим свои извинения.<br /> Свяжитесь с нашими операторами по телефону.<br />";
        }
        else {
            $this->messages="Заявка принята!<br /> Оператор свяжется с Вами в ближайшее время. <br />";
            $to = "anna.kuyumdzhi@gmail.com";
            $subject = "Заявка с tiktak.by";

            $message = $this->info;

            $header = "From:info@tiktak.by \r\n";
            $header .= "Cc:dmitry.nayd@gmail.com \r\n";
            $header .= "MIME-Version: 1.0\r\n";
            $header .= "Content-type: text/html\r\n";

//            $retval = mail ($to,$subject,$message,$header);
        }
    }

  /**
   * @param $id
   * @return true|void
   */
  public static function delete($id){
      $mysqli=Db::getInstance()->getConnection();

      $query = "DELETE FROM zvonki WHERE zv_id='$id'";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при обращзении к базе MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
      }
      return true;
    }

  /**
   * @return true|null
   */
  public function deleteSelf(){
      return self::delete($this->id);
    }

    public function archCopy__NotFinished(){
//      $query = "INSERT INTO zvonki_arch SET arch_time='".$this->zv_arch_time->format("Y-m-d H:i")."', zv_id='$this->id', z_name='$this->z_name', pr_time='$this->pr_time', operator='$this->operator', phone='$this->phone', tema='$this->tema', info='$this->info', cr_time='$this->cr_time'";
//      $result = $mysqli->query($query);
//      if (!$result) {
//        die('Сбой при обращзении к базе MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
//      }
    }

  /**
   * @return bool|void
   */
  public function isDublicate(){
      $mysqli = Db::getInstance()->getConnection();

      $timeToConciderForDublicates = time() - 1*60*60;

      $query = "SELECT zv_id FROM zvonki WHERE z_name='$this->z_name' AND info='$this->info' AND info!='' AND cr_time>'$timeToConciderForDublicates'";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
      }
      if ($result->num_rows>0) return true;
      else return false;
    }

    public function getMessage(){
        return $this->messages;
    }

    /**
     * @param $fio
     * @param $phone
     * @param $info
     * @param $model_id
     * @return Zvonok
     */
    public static function addLitZvonok($fio, $phone, $info, $model_id=null, $type1=null, $validityDaysNum=null){
        $z=new self();

        if ($model_id!=null) {
            $m=Model::getById($model_id);
            if ($m) {
              $z->tema='<strong>Поступила заявка:</strong>';
              //$z->info=$info.' ['.($m->getFullName()).']';
              $z->info=$info;
            }
            else{
              $z->info='Ошибка. Модель товара не определена. ID модели:'.$model_id;
            }

        }
        else {
            $z->tema='Обратный звонок или письмо';
            $z->info=$info;
        }

        $z->z_name=$fio;
        $z->phone=$phone;

        $z->type1 = $type1;
        $z->validityDaysNum = $validityDaysNum;
        $z->model_id = $model_id;

        if(!$z->isDublicate()) $z->save();

        return $z;
    }

  /**
   * @return void
   */
  public function done(){
      $mysqli = Db::getInstance()->getConnection();

      $query_cl_upd = "UPDATE zvonki SET `status`='done', info='$this->info', react_time='".time()."', person_id='".$_SESSION['user_id']."' WHERE zv_id='$this->id'";
      if (!$mysqli->query($query_cl_upd)) {
        die('Сбой при доступе к базе данных: '.$query_cl_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
      }
    }

  /**
   * @return void
   */
  public function zayavkaDone(){
    $mysqli = Db::getInstance()->getConnection();

    $query_cl_upd = "UPDATE zvonki SET `status`='done', info='$this->info', react_time='".time()."', person_id='".$_SESSION['user_id']."', type1='zayavka_done' WHERE zv_id='$this->id'";
    if (!$mysqli->query($query_cl_upd)) {
      die('Сбой при доступе к базе данных: '.$query_cl_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
    }
  }

  /**
   * @param $id
   * @return Zvonok|false|void
   */
  static function getById($id){
      $mysqli = Db::getInstance()->getConnection();

      $query = "SELECT * FROM zvonki WHERE zv_id='$id'";

      $result = $mysqli->query($query);

      if (!$mysqli->query($query)) {
        die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
      }

      if ($result && $result->num_rows>0){
        return self::getFromDbArray($result->fetch_assoc());
      }
      else{
        return false;
      }
    }

  /**
   * @param $row
   * @return Zvonok
   */
  public static function getFromDbArray($row){
      $z = new self();

      $z->id = $row['zv_id'];
      $z->z_name = $row['z_name'];
      $z->pr_time = $row['pr_time'];
      $z->operator = $row['operator'];
      $z->phone = $row['phone'];
      $z->tema = $row['tema'];
      $z->info = $row['info'];
      $z->cr_time = $row['cr_time'];
      $z->status = $row['status'];
      $z->react_time = $row['react_time'];
      $z->person_id = $row['person_id'];
      $z->validityDaysNum = $row['validity_days'];
      $z->type1 = $row['type1'];
      $z->model_id = $row['model_id'];

      return $z;

    }

  /**
   * @return bool
   */
  public function isSpam(){
    $spamList = [
      'go.',
      'snitssoke'
    ];
    $result = false;

    foreach ($spamList as $item) {
      if (strpos($this->info, $item)!== false) $result=true;
      if (strpos($this->z_name, $item)!== false) $result=true;
    }

    return$result;
  }

}
