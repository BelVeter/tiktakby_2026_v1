<?php


namespace bb\classes;


use bb\Base;
use bb\Db;
use bb\models\User;

class Tariff
{
    public $tarif_id;
    public $model_id;
    /**
     * @var \DateTime
     */
    public $start_date;
    public $step; //day, week, month
    public $kol_vo;
    public $kol_vo_min;
    public $rent_amount;
    public $rent_per_step;
    public $sort_num;

    /**
     * @var \DateTime
     */
    public $change_date;
    public $change_who;

    public function shortStepText(){
        switch ($this->step) {
            case 'day':
                return 'дн';
                break;
            case 'week':
                return 'нед.';
                break;
            case 'month':
                return 'мес.';
                break;
            default:
                return 'н.о.';
                break;
        }
    }

    public function save(){
        if ($this->tarif_id < 1) {
            $this->saveNew();
        }
        else {
            $this->update();
        }
    }

    private function saveNew() {
        $mysqli = Db::getInstance()->getConnection();
        $query_new_tarif = "INSERT INTO rent_tarif_act
            SET model_id='$this->model_id', start_date='".$this->start_date->getTimestamp()."', step='$this->step', kol_vo='$this->kol_vo', kol_vo_min='$this->kol_vo_min', rent_amount='$this->rent_amount', rent_per_step='$this->rent_per_step', sort_num='$this->sort_num', change_date='".time()."', change_who='".User::getCurrentUser()->id_user."'";
        if (!$mysqli->query($query_new_tarif)) {die('Сбой при доступе к базе данных: '.$query_new_tarif.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $this->tarif_id=$mysqli->insert_id;

        return true;
    }
    private function update(){
        $mysqli = Db::getInstance()->getConnection();
        $query_new_tarif = "UPDATE rent_tarif_act
            SET model_id='$this->model_id', start_date='".$this->start_date->getTimestamp()."', step='$this->step', kol_vo='$this->kol_vo', kol_vo_min='$this->kol_vo_min', rent_amount='$this->rent_amount', rent_per_step='$this->rent_per_step', sort_num='$this->sort_num', change_date='".time()."', change_who='".User::getCurrentUser()->id_user."'
            WHERE tarif_id='$this->tarif_id'";
        if (!$mysqli->query($query_new_tarif)) {die('Сбой при доступе к базе данных: '.$query_new_tarif.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        return true;
    }


    /**
     * @return Tariff|false|void|null
     * @throws \Exception
     */
    public function isDublicated(){
        $mysqli = Db::getInstance()->getConnection();
        $query = "SELECT * FROM rent_tarif_act WHERE model_id='$this->model_id' AND step='$this->step' AND kol_vo='$this->kol_vo'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        if ($result->num_rows > 0) {
            $row=$result->fetch_assoc();
            $t = self::getFromDbArray($row);

            return $t;
        }
        else return false;
    }

    public function hardSave(){
        if ($tar_dubl=$this->isDublicated()) {
            $this->tarif_id = $tar_dubl->tarif_id;
        }
        $this->save();
    }

    /**
     * @param $num
     * @return float|int
     */
    public static function roundHalfEur($num) {
        $eur = floor($num);
        $cents=round(($num - $eur), 2);

        if ($cents < 0.25) {
            $cents = 0;
        }
        elseif ($cents >= 0.25 && $cents < 0.75) {
            $cents = 0.5;
        }
        else {
            $cents = 0;
            $eur += 1;
        }

        return $eur+$cents;
    }

    public function t4AutoCalcAndFill(){
        $this->rent_amount = self::roundHalfEur($this->rent_amount);
        $this->kol_vo_min=$this->kol_vo;
        $this->rent_per_step = round($this->rent_amount / $this->kol_vo, 2);

    }

    /**
     * @param $id
     * @return Tariff|false|void|null
     * @throws \Exception
     */
    public static function getById($id){
        $mysqli = Db::getInstance()->getConnection();
        $query = "SELECT * FROM rent_tarif_act WHERE tarif_id='$id'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        if ($result->num_rows>0){
            return self::getFromDbArray($result->fetch_assoc());
        }
        else{
            return false;
        }

    }

    public function getCentsTotalAmmount(){
        //
        $rez=round($this->getTotalAmount(),2);
        $rez=$rez - floor($rez);
        $rez = $rez * 100;

        $n=strlen($rez);
        if ($n==1) return $rez.'0';
        else return $rez;
    }

    public function getDaysCalculatedNumber(){
        switch ($this->step) {
            case 'day':
                return $this->kol_vo;
                break;
            case 'week':
                return 7 * $this->kol_vo;
                break;
            case 'month':
                return 30 * $this->kol_vo;;
                break;
            default:
                return 0;
                break;
        }
    }

    public function getStepNum(){
        return $this->kol_vo;
    }

    public function getTotalAmount(){
        return $this->rent_amount;
    }

    public function getPeriodInDays(){
        switch ($this->step){
            case 'day':
                return $this->kol_vo;
                break;
            case 'week':
                return $this->kol_vo*7;
                break;
            case 'month':
                return $this->kol_vo*30;
                break;
        }
    }

    public function getRentPerStepCalculated(){
        return $this->rent_amount/$this->kol_vo;
    }

    public function getPerStepAmount(){
        return $this->rent_per_step;
    }


    public function getSepNameText(){
        if ($this->step=='week'){
            if ($this->kol_vo==1) return 'неделя';
            elseif ($this->kol_vo>1 && $this->kol_vo<5) return 'недели';
            elseif ($this->kol_vo>4) return 'недель';
        }
        elseif ($this->step=='day'){
            if ($this->kol_vo==1) return 'день';
            elseif ($this->kol_vo>1 && $this->kol_vo<5) return 'дня';
            elseif ($this->kol_vo>4) return 'дней';
        }
        elseif ($this->step=='month'){
            if ($this->kol_vo==1) return 'месяц';
            elseif ($this->kol_vo>1 && $this->kol_vo<5) return 'месяца';
            elseif ($this->kol_vo>4) return 'месяцев';
        }
    }

    /**
     * @param array $arr
     * @return Tariff|null
     * @throws \Exception
     */
    public static function getFromDbArray(array $arr) {
      try {
        if (!is_array($arr) || array_count_values($arr)<1) {
          return null;
        }
      }
      catch (\Exception $exception) {
        return null;
      }

        $tar=new self();

        $tar->tarif_id=$arr['tarif_id'];
        $tar->model_id=$arr['model_id'];

            $start_date = new \DateTime();
            $start_date->setTimestamp($arr['start_date']);
        $tar->start_date=clone $start_date;
        $tar->step=$arr['step'];
        $tar->kol_vo=$arr['kol_vo'];
        $tar->kol_vo_min=$arr['kol_vo_min'];
        $tar->rent_amount=$arr['rent_amount'];
        $tar->rent_per_step=$arr['rent_per_step'];
        $tar->sort_num=$arr['sort_num'];
            $change_date = new \DateTime();
            $change_date->setTimestamp($arr['change_date']);
        $tar->change_date=clone $change_date;
        $tar->change_who=$arr['change_who'];

        return $tar;
    }


}
