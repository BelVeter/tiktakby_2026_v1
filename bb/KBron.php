<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 28.11.2018
 * Time: 21:21
 */

namespace bb;


use bb\classes\Collateral;
use Illuminate\Support\Facades\Date;

class KBron
{
    /**
     * @var KBron
     */
    public static $active_k_bron=null;
    public static $_first_kbron_btns_showed=0;

    public static $pause_time_h=4;//h
    public static $min_bron_time_h=6;//h
    public static $tmp_valid_min=1;//min-7

    public $id_kb;
    public $inv_n;
    public $cl_id;
    /**
     * @var \DateTime
     */
    public $from_kb;
    /**
     * @var \DateTime
     */
    public $to_kb;

    public $staus_kb;
    public $info;
    public $payment_k1;//podg_time
    public $payment_k2;//podg_who
    public $payment_term;
    public $payment_bank;// ???
    public $payment_date; //aid code


    public $fio;
    public $phone1;
    public $phone2;
    public $email;
    /**
     * @var \DateTime
     */
    public $cr_time;
    public $cr_who_id;
    /**
     * @var \DateTime
     */
    public $valid_time;
    /**
     * @var \DateTime
     */
    public $ch_time;

    public $br_max_num;
    public $br_num;
    /**
     * @var \DateTime
     */
    public $appr_time;
    public $appr_who;
    /**
     * @var \DateTime
     */
    public $vidacha;
    public $vid_who_id;
    /**
     * @var \DateTime
     */
    public $vozvrat;
    public $vozvr_who_id;
    public $dl_link;
    /**
     * @var \DateTime
     */
    public $nedozvon;


    private $change_history;


  /**
   * @return string
   */
  public function getBrNumFormated(){
      return mb_substr($this->br_num, 0,3).'-'.mb_substr($this->br_num, 3);
    }

    public static function getRentedOutBron(){
        return self::$active_k_bron;
    }

    public function readyToVidacha(){

        if ($this->dl_link>0 && !$this->isVidan()){
            return true;
        }
        else {
            return false;
        }
    }

    public function btnsToVidacha(){

        if ($this->readyToVidacha() && (KBron::$_first_kbron_btns_showed==$this->id_kb || KBron::$_first_kbron_btns_showed==0 && !KBron::$active_k_bron)){
            if (KBron::$_first_kbron_btns_showed==0) {
                self::$_first_kbron_btns_showed=$this->id_kb;
            }
            return true;
        }
        else {
            return false;
        }
    }

    public static function getPauseTimeSec() {
        return self::$pause_time_h*60*60;
    }

    public function getUniqId(){
        return $this->inv_n.'_'.$this->from_kb->format("YmdHi");
    }

    /**
     * @param \DateTime $start_time
     * @return float|int
     */
    public function hoursFromStart(\DateTime $start_time) {
        $diff=$start_time->diff($this->from_kb);
        $h_diff=$diff->h + $diff->days*24;
        return $h_diff;
    }

    public function hoursDuration(){
      $diff = $this->to_kb->diff($this->from_kb);
      $h_diff=$diff->h + $diff->days*24;
      return $h_diff;
    }

  /**
   * @param $invN
   * @param $from
   * @param $to
   * @return bool
   */
  public static function checkIfInFreePeriodInvWithControll($invN, \DateTime $from, \DateTime $to){

      $fromForPeriodSrch = clone $from;
        $fromForPeriodSrch->setTime(0,0,1);
      $toForPeriodSrch = clone $to;
        $toForPeriodSrch->setTime(23,0,0);

      $freePeriods = self::getFreePeriodsInv($invN, $fromForPeriodSrch, $toForPeriodSrch,1);
      foreach ($freePeriods as $fp){
        if ($fp->isIncludePeriod($from, $to)) return true;
      }
      return false;
  }

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @return bool
   */
  public function isIncludePeriod(\DateTime $from, \DateTime $to){
      $selfFrom = clone $this->from_kb;
        $selfFrom->setTime($selfFrom->format('H'), $selfFrom->format('i'),0);
      $selfTo = clone $this->to_kb;
        $selfTo->setTime($selfTo->format('H'), $selfTo->format('i'),0);

      if ($selfFrom <= $from && $selfTo >= $to) return true;
      return false;
  }

  /**
   * @param $invN
   * @param $modelId
   * @param $rostFrom
   * @param $rostTo
   * @param \DateTime $from
   * @param \DateTime $to
   * @param $phoneNum
   * @param $fio
   * @param $info
   * @return bool|KBron
   */
  public static function saveNewBronSafeAtOneStepConciderOtherInvs($invN, $modelId, $rostFrom, $rostTo, \DateTime $from, \DateTime $to, $phoneNum, $fio, $info)
  {

    $newKB = false;
    if ($invN > 0) {
      if (self::checkIfInFreePeriodInvWithControll($invN, $from, $to)) {
        $newKB = KBron::setTmpBron($invN, $from, $to);
      }
    }

    if (!$newKB) {
      $invNsArray = \bb\classes\tovar::getInvNArrayByModelIdAndRost($modelId, $rostFrom, $rostTo);

      foreach ($invNsArray as $inv) {

        if (self::checkIfInFreePeriodInvWithControll($inv, $from, $to)) {
          $newKB = KBron::setTmpBron($inv, $from, $to);
          if ($newKB) break; //end foreach
        }

      }

    }

    if (!$newKB) return false;//no success in creating tmp bron => exit

    //if success
    $newKB->phone1 = $phoneNum;
    $newKB->fio = $fio;
    $newKB->info = $info;

    $rez = $newKB->saveRealBron();

    if ($rez) return $newKB;
    else return false;
  }

  public static function filterFreeModelIds(array $modelIds, \DateTime $eventDate, $rost=false, $gender=false){
    $pauseSecDuration = self::$pause_time_h * 60 * 60;

    $filterAddOnQuery='';
    if ($gender){
      if ($gender=='m'){
        $filterAddOnQuery.="AND (tovar_rent.m_sex IN('m', 'u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('m', 'u')))";
      }
      elseif ($gender=='f'){
        $filterAddOnQuery.="AND (tovar_rent.m_sex IN('f', 'u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('f', 'u')))";
      }
      elseif ($gender=='u'){
        $filterAddOnQuery.="AND (tovar_rent.m_sex IN('u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('u')))";
      }
    }
    if ($rost && $rost > 0){
      $filterAddOnQuery.=" AND (tovar_rent_items.item_rost1 <= '$rost' AND tovar_rent_items.item_rost2 >= '$rost'";
    }

    $startTime = clone $eventDate;
      $startTime->setTime(Schedule::getOpenHour($eventDate),0,1);
    $endTime = clone $eventDate;
      $endTime->setTime(Schedule::getCloseHour($eventDate),0,0);

      $query ="SELECT tovar_rent.tovar_rent_id, tovar_rent_items.item_inv_n, SUM(karn_brons.kb_id) as brons FROM tovar_rent
                LEFT JOIN tovar_rent_items ON tovar_rent.tovar_rent_id = tovar_rent_items.model_id
                LEFT JOIN karn_brons ON
                    tovar_rent_items.item_inv_n = karn_brons.inv_n
                    AND(
                    (karn_brons.t_from <= '" . $startTime->getTimestamp() . "' AND karn_brons.t_to >= '" . $endTime->getTimestamp() . "')
                    OR (karn_brons.t_to > '" . $startTime->getTimestamp() . "' AND (karn_brons.t_to + $pauseSecDuration) < '" . $endTime->getTimestamp() . "')
                    OR (karn_brons.t_from < '" . $endTime->getTimestamp() . "' AND (karn_brons.t_from - $pauseSecDuration) > '" . $startTime->getTimestamp() . "')
                    )
                WHERE tovar_rent.tovar_rent_id IN('".implode("', '",$modelIds)."') $filterAddOnQuery
                GROUP BY tovar_rent_items.item_inv_n";

      echo $query;
  }



  /**
   * @param $modelId
   * @param \DateTime $start_time
   * @param \DateTime $end_time
   * @param $rostFrom
   * @param $rostTo
   * @return KBron[]
   */
  public static function getFreePeriodsForModelIdAndRosts($modelId, \DateTime $start_time, \DateTime $end_time, $rostFrom, $rostTo){
      $freePeriods = [];
      $invNs = \bb\classes\tovar::getInvNArrayByModelIdAndRost($modelId, $rostFrom, $rostTo);
      foreach ($invNs as $invN){
        $freePerTmp = KBron::getFreePeriodsInv($invN, $start_time, $end_time);
        if (is_array($freePerTmp) && count($freePerTmp)>0) {
          $freePeriods = array_merge($freePeriods, $freePerTmp);
        }
      }
      return $freePeriods;
    }

  /**
   * @param $modelId
   * @param \DateTime $start_time
   * @param \DateTime $end_time
   * @param $rostFrom
   * @param $rostTo
   * @param \DateTime $date
   * @return KBron[]
   */
  public static function getFreePeriodsForModelIdAndRostsCrossingDate($modelId, \DateTime $start_time, \DateTime $end_time, $rostFrom, $rostTo, \DateTime $date){
    $freePeriods = self::getFreePeriodsForModelIdAndRosts($modelId, $start_time, $end_time, $rostFrom, $rostTo);
    $crossedFreePeriods = [];
    foreach ($freePeriods as $fp){
      if ($fp->isCrossingDate($date)){
        $crossedFreePeriods[] = $fp;
      }
    }
    return $crossedFreePeriods;
  }

  /**
   * @param \DateTime $date
   * @return bool
   */
  public function isCrossingDateTime(\DateTime $date) {
      $rez = false;

      if ($date >= $this->from_kb && $date <= $this->to_kb) $rez = true;

      return$rez;
    }

  /**
   * @param \DateTime $date
   * @return bool
   */
  public function isCrossingDate(\DateTime $date)
  {
    $startOfDay = clone $date;
      $startOfDay->setTime(0,0,1);
    $endOfDay = clone $date;
      $endOfDay->setTime(23,59,59);

    $rez = false;

    if ($this->from_kb >= $startOfDay && $this->from_kb <= $endOfDay) $rez = true;
    if ($this->to_kb >= $startOfDay && $this->to_kb <= $endOfDay) $rez = true;
    if ($this->from_kb <= $startOfDay && $this->to_kb >= $endOfDay) $rez = true;

    return $rez;
  }

  /**
   * @param \DateTime $from
   * @param \DateTime $to
   * @return false|float|int
   */
  public function getInterlappingHours(\DateTime $from, \DateTime $to){
      if ($from > $this->from_kb) $rFrom = $from;
      else $rFrom = $this->from_kb;

      if ($to < $this->to_kb) $rTo = $to;
      else $rTo = $this->to_kb;

      if ($rTo<=$rFrom) return false;

      $diff = $rFrom->diff($rTo);
      $hDiff = $diff->d*24 + $diff->h;
      return $hDiff;
    }

    /**
     * @param $inv_n
     * @param $event_date
     * @return KBron[]
     */
    public static function getFreePeriodsInv($inv_n, \DateTime $start_time, \DateTime $end_time, $all_controll=1){
//        Base::varDamp($start_time);
//        Base::varDamp($end_time);

        $brons=KBron::getBrons($inv_n, $start_time, $end_time);

//        Base::varDamp($brons);

//        Base::varDamp($start_time);
//        Base::varDamp($end_time);
        /**
         * @var KBron[]
         */
        $free_brons=array();


        if ($brons==false) {//если брони отсутствуют - создаем один большой период свободности


            $time_array = self::getBronTimeFromFreePeriod($start_time, $end_time, $all_controll);

            $kb_free = new KBron();
            $kb_free->from_kb=$time_array[0];
            $kb_free->to_kb=$time_array[1];
            $kb_free->inv_n=$inv_n;

            $free_brons[]=$kb_free;

        }
        else {
            //проходимся по броням
            foreach ($brons as $bron) {
                if ($bron->from_kb>$end_time) break;

                if ($time_array=self::getBronTimeFromFreePeriod($start_time, $bron->from_kb, $all_controll)) {
                    //создаем новую свободную бронь
                    $kb_free = new KBron();
                    $kb_free->from_kb=$time_array[0];
                    $kb_free->to_kb=$time_array[1];
                    $kb_free->inv_n=$inv_n;

                    $free_brons[]=$kb_free;
                }

                //а теперь сдвигаем "период с"
                $start_time = clone $bron->to_kb;
            }
            //последий период (если оставется после брони)
            if ($time_array=self::getBronTimeFromFreePeriod($start_time, $end_time, $all_controll)) {
                //создаем новую свободную бронь
                $kb_free = new KBron();
                $kb_free->from_kb=$time_array[0];
                $kb_free->to_kb=$time_array[1];
                $kb_free->inv_n=$inv_n;

                $free_brons[]=$kb_free;
            }
        }

        return $free_brons;
    }


    /**
     * @param \DateTime $start_time
     * @param \DateTime $end_time
     * @return \DateTime[]|bool
     */
  public static function getBronTimeFromFreePeriod(\DateTime $start_time, \DateTime $end_time, $all_controll = 1)
  {//I need star_time, end_time, pause_hours, min_bron_duration_hours
    //функция корректирует входящий временной период свободности товара: сокращает периоды на время паузы между сдачами + сдвигает в случае попадания на нерабочее время
    //бронь обязательно должна быть внутри периода, т.е. "с" и "по" могут только сокращаться, но не разрастаться

    if ($end_time <= $start_time) return false;//dates correctness check

    //min duration checks
    $diff0 = $start_time->diff($end_time);
    $diff0_h = $diff0->h + $diff0->days * 24 + round($diff0->i / 60);

    if ($diff0_h < 1) return false;

    if ($all_controll == 1) {//если ТРЕБУЕТСЯ КОНТРОЛЬ и период менее минимально возможного для брони - сразу выводим отказ
      if ($diff0_h < self::$min_bron_time_h) return false;
    }

    $start_t = clone $start_time;
    //add 1 second, or there will be bron conflict with another bron
    $start_t->modify("+1 second");

    //если  ТРЕБУЕТСЯ КОТРОЛЬ И начало не совпадает с началом рабочего дня (значит конец какой-то брони) => добавляем время паузы
    if ($all_controll == 1 && $start_t->format("H") != Schedule::getOpenHour($start_t)) {
      $start_t->modify("+" . self::$pause_time_h . " hour");
    }

    if ($all_controll == 1) {//если ТРЕБУЕТСЯ КОТРОЛЬ на расписание - до тех пор пока время не рабочее - сдвигаем на 1 час вперед
      while (!Schedule::isWorkingTime($start_t)) {
        if ((int)$start_time->format("i") != 0) $start_time->setTime($start_time->format("G"), 0, 0);//set minutes to zero
        $start_t->modify("+1 hour");
        if ($end_time <= $start_time) return false; // если вдруг, увеличивая старт, заскочили за финиш - выход
      }
    }

    $end_t = clone $end_time;

    if ((int)$end_t->format("s") == 1) $end_t->modify("-1 second"); //if it is start of other bron and it considers 1 second shift of begining reduce it in order to avoid conflicts


    //если конец не попадает в рабочее время - значит попадает в ночь. Следовательно, сдвигаем влево до конца рабочего дня
    if ($all_controll == 1 && !Schedule::isWorkingTime($end_t)) {
      $end_t->setTime(Schedule::getCloseHour($end_t), 0, 0);
    }

    //если  ТРЕБУЕТСЯ КОТРОЛЬ И конец не совпадает с концом рабочего дня (значит начало какой-то следующей брони) => сокращаем на время паузы
    if ($all_controll == 1 && $end_t->format("H") != Schedule::getCloseHour($end_t)) {
      $end_t->modify("-" . self::$pause_time_h . " hour");
    }

    if ($all_controll == 1) {//если ТРЕБУЕТСЯ КОТРОЛЬ на расписание - до тех пор пока время не рабочее - сдвигаем на 1 час назад
      while (!Schedule::isWorkingTime($end_t)) {
        if ((int)$end_t->format("i") != 0) $end_t->setTime($end_t->format("G"), 0, 0);//set minutes to zero
        $end_t->modify("-1 hour");
        if ($end_t <= $start_t) return false; // если вдруг, сокращая финиш, заскочили за старт - выход
      }
    }


    $diff = $start_t->diff($end_t);
    $diff_h = $diff->h + $diff->days * 24 + round($diff->i / 60);

    //если НЕ БЕРЕМ в расчет паузу, либо берем в расчет и время паузы соблюдается - возвращаем свободный период
    if ($all_controll != 1) {
      $free_period = array($start_t, $end_t);
      return $free_period;
    } elseif ($diff_h >= self::$min_bron_time_h) {
      $free_period = array($start_t, $end_t);
      return $free_period;
    } else {
      return false;
    }
  }

    /**
     * @return bool
     */
    public function arch($arch_info=''){
        $mysqli = Db::getInstance()->getConnection();

        $query="INSERT INTO karn_brons_arch SET arch_time='".time()."', arch_who='".\bb\models\User::getCurrentUser()->id_user."', arch_info='$arch_info', kb_id='$this->id_kb', inv_n='$this->inv_n', cl_id='$this->cl_id', t_from='".$this->from_kb->getTimestamp()."', t_to='".$this->to_kb->getTimestamp()."', `status`='$this->staus_kb', info='$this->info', payment_k1='$this->payment_k1', payment_k2='$this->payment_k2', payment_term='$this->payment_term', payment_bank='$this->payment_bank', payment_date='$this->payment_date', cr_time='".$this->cr_time->getTimestamp()."', br_max_num='$this->br_max_num', br_num='$this->br_num', fio='$this->fio', phone1='$this->phone1', phone2='$this->phone2', mail='$this->email', dl_link='$this->dl_link', appr_time='".$this->appr_time->getTimestamp()."', appr_who='$this->appr_who', vidacha='".$this->vidacha->getTimestamp()."', vid_who_id='$this->vid_who_id', vozvrat='".$this->vozvrat->getTimestamp()."', vozvr_who_id='$this->vozvr_who_id', nedozvon='".$this->nedozvon->getTimestamp()."'";
        //echo $query;
        $result=$mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        return true;
    }

    /**
     * @param \DateTime $event_time
     * @return bool | KBron[]
     */
    public static function getBrons($inv_n, \DateTime $start_time_obj, \DateTime $end_time_obj, array $excluded_br_ids=null){

        /**
         * @var KBron[]
         */
        $brons=array();

        if ($excluded_br_ids!=null) {
            $id_excl_filt=" AND `kb_id` NOT IN (". implode(',', array_map('intval', $excluded_br_ids)).")";
        }
        else $id_excl_filt = '';


        $start_time=(int)$start_time_obj->getTimestamp();
        $end_time=(int)$end_time_obj->getTimestamp();

        $mysqli = Db::getInstance()->getConnection();

        $br_validity_time =(int) (time()-self::getPauseTimeSec());

        //var_dump($end_time);

        //запрашиваем брони
        $q_from = "SELECT * FROM karn_brons WHERE inv_n= '$inv_n' AND (`status` IN ('new', 'ok') OR (`status`='in_process'
        AND `valid_time` >= '".time()."')) AND ((t_from BETWEEN '$start_time' AND '$end_time') OR (t_to BETWEEN '$start_time'
        AND '$end_time') OR (t_from<='$start_time' AND t_to>='$end_time') )$id_excl_filt ORDER BY t_to";

        //echo $q_from.'<br><br>';
        $q_result=$mysqli->query($q_from);
        if (!$q_result) {die('Сбой при доступе к базе данных: '.$q_from.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $rows_n = $q_result->num_rows;

        if ($rows_n<1) {
            return false;
        }

        while ($row=$q_result->fetch_assoc()) {
            $kb=KBron::getFromDbArray($row);
            $brons[]=$kb;
        }

        return $brons;
    }

    /**
     * @param $br_id
     * @return KBron|bool
     */
    public static function getById($br_id) {
        $mysqli = Db::getInstance()->getConnection();

        $q = "SELECT * FROM karn_brons WHERE kb_id='$br_id'";
        $result=$mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $rows_n = $result->num_rows;

        if ($rows_n!=1) {
            return false;
        }

        $row=$result->fetch_assoc();
        $kb=KBron::getFromDbArray($row);

        return $kb;
    }

    /**
     * @param $inv_n
     * @return KBron|bool
     */
    public static function getActiveBronByInv($inv_n) {
        $mysqli = Db::getInstance()->getConnection();

        $cr_time_start = new \DateTime();//с какого времени начинаем смотреть активные брони
            $cr_time_start->setDate(2019,9,1);

        $q = "SELECT * FROM karn_brons WHERE inv_n='$inv_n' AND vidacha>0 AND (vozvrat IS NULL OR vozvrat=0) AND cr_time>'".$cr_time_start->getTimestamp()."' LIMIT 1";
        //echo $q;
        $result=$mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $rows_n = $result->num_rows;

        if ($rows_n!=1) {
            return false;
        }

        $row=$result->fetch_assoc();
        $kb=KBron::getFromDbArray($row);

        return $kb;
    }

    /**
     * @return bool
     */
    public function isVidanOnly(){
        if ($this->vidacha->getTimestamp()>0 && $this->vozvrat->getTimestamp()<1) {
            return true;
        }
        else {
            return false;
        }
    }

    public function getColorTableRow(){
        if ($this->appr_time->getTimestamp()>0 && $this->vidacha->getTimestamp()<1 && $this->dl_link<1) {//только подтвержден
            return '#bad7f8';
        }
        elseif ($this->vidacha->getTimestamp()<1 && $this->dl_link>0) {//оформлен договор
            return '#f6f19f';
        }
        elseif ($this->vidacha->getTimestamp()>0 && $this->vozvrat->getTimestamp()<1) {//на руках
            return '#cbfaca';
        }
        elseif ($this->vidacha->getTimestamp()>0 && $this->vozvrat->getTimestamp()>0) {//возвращен
            return '#c8ccc7';
        }
        else {
            return 'white';
        }
    }

    public static function getLastVidanBron($inv_n){
        $mysqli = Db::getInstance()->getConnection();

        $q = "SELECT * FROM karn_brons WHERE inv_n='$inv_n' AND vidacha<".time()." ORDER BY vidacha DESC LIMIT 1";
        $result=$mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $rows_n = $result->num_rows;

        if ($rows_n!=1) {
            return false;
        }

        $row=$result->fetch_assoc();
        $kb=KBron::getFromDbArray($row);

        return $kb;
    }

    public static function getFromDbArray($db_ar) {
        $kb=new self();
        $kb->id_kb=$db_ar['kb_id'];
        $kb->inv_n=$db_ar['inv_n'];
        $kb->cl_id=$db_ar['cl_id'];
        $kb->from_kb= new \DateTime();
            $kb->from_kb->setTimestamp($db_ar['t_from']);
        $kb->to_kb = new \DateTime();
            $kb->to_kb->setTimestamp($db_ar['t_to']);
        $kb->info=$db_ar['info'];
        $kb->staus_kb=$db_ar['status'];
        $kb->payment_k1=$db_ar['payment_k1'];
        $kb->payment_k2=$db_ar['payment_k2'];
        $kb->payment_term=$db_ar['payment_term'];
        $kb->payment_bank=$db_ar['payment_bank'];
        $kb->payment_date=$db_ar['payment_date'];
        $kb->cr_time = new \DateTime();
            $kb->cr_time->setTimestamp($db_ar['cr_time']);
        $kb->br_num=$db_ar['br_num'];
        $kb->br_max_num=$db_ar['br_max_num'];

        $kb->fio=$db_ar['fio'];
        $kb->phone1=$db_ar['phone1'];
        $kb->phone2=$db_ar['phone2'];
        $kb->email=$db_ar['mail'];

        if ($db_ar['payment_bank']>0) {
            $kb->change_history=1;
        }
        else {
            $kb->change_history=0;
        }

        $kb->appr_time= new \DateTime();
            $kb->appr_time->setTimestamp($db_ar['appr_time']);
        $kb->appr_who=$db_ar['appr_who'];
        $kb->vidacha = new \DateTime();
            $kb->vidacha->setTimestamp($db_ar['vidacha']);
        $kb->vid_who_id=$db_ar['vid_who_id'];
        $kb->vozvrat = new \DateTime();
            $kb->vozvrat->setTimestamp($db_ar['vozvrat']);
        $kb->vozvr_who_id = $db_ar['vozvr_who_id'];
        $kb->dl_link = $db_ar['dl_link'];
        $kb->nedozvon = new \DateTime();
            $kb->nedozvon->setTimestamp($db_ar['nedozvon']);
        $kb->valid_time = new \DateTime();
            $kb->valid_time->setTimestamp($db_ar['valid_time']);
        $kb->ch_time = new \DateTime();
            $kb->cr_time->setTimestamp($db_ar['ch_time']);
        $kb->cr_who_id=$db_ar['cr_who'];

        return $kb;
    }

    /**
     * @return bool
     */
    public function vidacha(){
        $mysqli = Db::getInstance()->getConnection();
        $query_upd = "UPDATE karn_brons SET vidacha='".time()."', vid_who_id='".\bb\models\User::getCurrentUser()->id_user."' WHERE kb_id='$this->id_kb'";
        $result = $mysqli->query($query_upd);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        return true;
    }

    /**
     * @return bool
     */
    public static function podgotovitStatic($kb_id){
        $mysqli = Db::getInstance()->getConnection();
        $query_upd = "UPDATE karn_brons SET payment_k1='".time()."', payment_k2='".\bb\models\User::getCurrentUser()->id_user."' WHERE kb_id='$kb_id'";
        $result = $mysqli->query($query_upd);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        return true;
    }

    /**
     * @param $kb_id
     * @return bool
     */
    public static function vidachaStatic($kb_id){
        if ($kb_id<1) return false;

        $kb = new self();
        $kb->id_kb=$kb_id;
        return $kb->vidacha();
    }

    /**
     * @return Collateral|bool
     */
    public function getCollateral(){
        $rez = false;
        if ($this->vidacha->getTimestamp()>0 && $this->dl_link>0) {
            $rez=Collateral::getCollateralByDl($this->dl_link);
        }
        return $rez;
    }

    /**
     * @return bool
     */
    public function isVidan(){
        if ($this->vidacha->getTimestamp()>0) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isReturned(){
        if ($this->vozvrat->getTimestamp()>0) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isRentedOutKarn(){
        if ($this->isVidan() && !$this->isReturned()) {
            return true;
        }
        else {
            return false;
        }
    }

    public function vozvrat(){
        $mysqli = Db::getInstance()->getConnection();
        $query_upd = "UPDATE karn_brons SET vozvrat='".time()."', vozvr_who_id='".$_SESSION['user_id']."' WHERE kb_id='$this->id_kb'";
        $result = $mysqli->query($query_upd);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        return true;
    }

    public static function vozvratStatik($br_id){
        $kb=new self();
        $kb->id_kb=$br_id;
        $kb->vozvrat();
        return true;
    }

    /**
     * @param $kb_id
     * @return string
     */
    public static function getVozvratPaymentIdStatic($kb_id) {
        $mysqli=Db::getInstance()->getConnection();
        $query_upd = "SELECT dl_link FROM karn_brons WHERE kb_id='$kb_id'";
        $result = $mysqli->query($query_upd);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        $rez=$result->fetch_assoc();

        $dl_id=$rez['dl_link'];

        $query_upd = "SELECT sub_deal_id FROM rent_sub_deals_arch WHERE deal_id='$dl_id' AND `type` IN ('payment', 'cl_payment') AND `status`='br_vozvrat'";
        $result = $mysqli->query($query_upd);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        if ($result->num_rows<1) {
            $query_upd = "SELECT sub_deal_id FROM rent_sub_deals_act WHERE deal_id='$dl_id' AND `type` IN ('payment', 'cl_payment') AND `status`='br_vozvrat'";
            $result = $mysqli->query($query_upd);
            if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        }

        $rez2=$result->fetch_assoc();

        $sub_deal_id=$rez2['sub_deal_id'];

        return $sub_deal_id;

    }

    public static function vidachaCancelByBrId($br_id){
        $mysqli = Db::getInstance()->getConnection();

        //правим бронь
        $query_upd = "UPDATE karn_brons SET vidacha='', vid_who_id='' WHERE kb_id='$br_id'";
//        echo $query_upd.'<br><br>';
        $result = $mysqli->query($query_upd);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        //удаляем залог
        $query_upd = "DELETE FROM collateral WHERE br_id='$br_id'";
//        echo $query_upd.'<br><br>';
        $result = $mysqli->query($query_upd);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        return true;
    }

    public static function loadBrFull($id){
        $mysqli = Db::getInstance()->getConnection();

        $query="SELECT * FROM karn_brons WHERE kb_id='$id'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

    }

    public static function vozvratCancelByBrId($br_id) {
        $mysqli = Db::getInstance()->getConnection();

        //правим бронь
        $query_upd = "UPDATE karn_brons SET vozvrat='', vozvr_who_id='' WHERE kb_id='$br_id'";
//        echo $query_upd.'<br><br>';
        $result = $mysqli->query($query_upd);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        //правим залог
        $query_upd = "UPDATE collateral SET return_time='', return_who_id='' WHERE br_id='$br_id'";
//        echo $query_upd.'<br><br>';
        $result = $mysqli->query($query_upd);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}


    }



    public function getPhonesText() {
        return self::phone_print($this->phone1).'<br>'.self::phone_print($this->phone2);
    }

    private static function phone_print ($ph) {
        if ($ph=='') {return '';}

        $dl=strlen($ph);

        if ($dl<7) {return $ph;}

        $dl>7 ? $dl_to=$dl-7 : $dl_to=0;
        $ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
        return $ph_out;

    }

    public static function isTmpValid() {

    }

    public static function isTimeChanged($kb_id, $from_new, $to_new){
        $kb = self::getById($kb_id);
        if ($kb->from_kb==$from_new && $kb->to_kb==$to_new) {
            return false;
        }
        else {
            return $kb;
        }
    }

    /**
     * @return \DateTime
     */
    private function makeValidTime() {
        $validtime=new \DateTime();
        $validtime->modify("+".self::$tmp_valid_min." minutes");
        return $validtime;
    }

    public static function makeValidTimestamp() {
        $validtime=new \DateTime();
        $validtime->modify("+".self::$tmp_valid_min." minutes");
        return $validtime->getTimestamp();
    }

    /**
     * @param integer $inv_n
     * @param \DateTime $start_time
     * @param \DateTime $end_time
     * @return KBron|bool
     */
    public static function setTmpBron($inv_n, \DateTime $start_time, \DateTime $end_time){
        $mysqli = Db::getInstance()->getConnection();


        if ((int)$start_time->format("s")==0)  {//in order to eleminate bron conflicts
            $start_time->modify("+1 second");
        }

        $query = "LOCK TABLES karn_brons WRITE";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}


        if (!self::getBrons($inv_n, $start_time, $end_time)) {//if no crossingbronsexists - save new tmp bron
            $kb = new self();
            $kb->inv_n=$inv_n;
            $kb->from_kb=$start_time;
            $kb->to_kb=$end_time;
            $kb->staus_kb='in_process';
            $kb->save();
            $f_result = $kb;
        }
        else $f_result = false;

        $query = "UNLOCK TABLES";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        return $f_result;
    }

  /**
   * @return bool|void
   */
  public function saveRealBron() {
        $mysqli = Db::getInstance()->getConnection();

        $query = "LOCK TABLES karn_brons WRITE";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        if ((int)$this->from_kb->format("s")!=1) $this->from_kb->modify("+1 second");

        if (!self::getBrons($this->inv_n, $this->from_kb, $this->to_kb, [$this->id_kb,])) {//if no crossing-brons exists - save new bron
//            $tmp = new KBron();
//            $tmp->loadTmp($this->id_kb);
//
//            if ($tmp->ch_time!=$this->ch_time) return false; //if ch_time is not equal - means

          $this->staus_kb='new';
            $this->calculateBronNumber();
            $this->update();
            $f_result=true;
        }
        else $f_result = false;

        $query = "UNLOCK TABLES";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        return $f_result;
    }

    public function update() {//no update for cr_time, valid_time, inv_n
        $mysqli = Db::getInstance()->getConnection();

        $cur_time = time();

        $query = "UPDATE karn_brons SET cl_id='$this->cl_id', t_from='".$this->from_kb->getTimestamp()."', t_to='".$this->to_kb->getTimestamp()."', `status`='$this->staus_kb', info='$this->info', br_max_num='$this->br_max_num', br_num='$this->br_num', fio='$this->fio', phone1='$this->phone1', phone2='$this->phone2', `mail`='$this->email', ch_time='$cur_time' WHERE kb_id='$this->id_kb'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при обновлении брони в MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        $this->ch_time = new \DateTime();
            $this->ch_time->setTimestamp($cur_time);

        return true;
    }

    public static function delete($id) {
        $mysqli = Db::getInstance()->getConnection();

        $query = "DELETE from karn_brons WHERE kb_id='$id'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при удалении брони в MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        return true;

    }

    public function isChanged() {
        $mysqli = Db::getInstance()->getConnection();

        $query="SELECT (ch_time) FROM karn_brons WHERE kb_id='$this->id_kb'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $str=$result->fetch_assoc();

        if ($this->ch_time->getTimestamp()==$str['ch_time']) {
            return false;
        }
        else {
            return true;
        }

    }

    public function loadTmp($id) {

        $this->cr_time= new \DateTime();
            $this->cr_time->setTimestamp($str['cr_time']);
        $this->valid_time = new \DateTime();
            $this->valid_time->setTimestamp($str['valid_time']);
        $this->from_kb = new \DateTime();
            $this->from_kb->setTimestamp($str['t_from']);
        $this->to_kb = new \DateTime();
            $this->to_kb->setTimestamp($str['t_to']);
        $this->staus_kb=$str['status'];
        $this->ch_time = $str['ch_time'];

    }

    public function save() {
        $mysqli = Db::getInstance()->getConnection();

        $cur_time=time();

        $query = "INSERT INTO karn_brons SET inv_n='$this->inv_n', t_from='".$this->from_kb->getTimestamp()."', t_to='".$this->to_kb->getTimestamp()."', `status`='$this->staus_kb', cr_time='$cur_time', valid_time='".$this->makeValidTime()->getTimestamp()."', ch_time='$cur_time'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при вставке временной брони в MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        else {
            $this->id_kb=$mysqli->insert_id;
            $this->cr_time=new \DateTime();
                $this->cr_time->setTimestamp($cur_time);
            $this->ch_time=new \DateTime();
                $this->ch_time->setTimestamp($cur_time);
            return true;
        }
    }


    public function calculateBronNumber(){
        // формируем первую часть номера брони: порядковый номер товара без категории
        $tov_n_1=mb_substr($this->inv_n, 3);
        if ($tov_n_1>99) {
            $tov_n_1=$tov_n_1;
        }
        elseif ($tov_n_1>9) {
            $tov_n_1='9'.$tov_n_1;
        }
        else {
            $tov_n_1='90'.$tov_n_1;
        }

        $mysqli = Db::getInstance()->getConnection();

        // формируем вторую часть номера брони (макс. номер в рамках одного и того же инвентарного номера
        $q_max_n = "SELECT MAX(`br_max_num`) as br_max FROM `karn_brons` WHERE inv_n='$this->inv_n'";
        $result_max_n = $mysqli->query($q_max_n);
        if (!$result_max_n) {die('Сбой при доступе к базе данных: '.$q_max_n.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $max_n_res=$result_max_n->fetch_assoc();
        $max_n=$max_n_res['br_max'];

        if ($max_n==null) {
            $max_n=0;
        }

        $q_max_n2 = "SELECT MAX(`br_max_num`) as br_max FROM `karn_brons_arch` WHERE inv_n='$this->inv_n'";
        $result_max_n2 = $mysqli->query($q_max_n);
        if (!$result_max_n2) {die('Сбой при доступе к базе данных: '.$q_max_n2.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $max_n_res2=$result_max_n2->fetch_assoc();
        $max_n2=$max_n_res2['br_max'];

        if ($max_n2==null) {
            $max_n2=0;
        }

        if ($max_n2>$max_n) {
            $max_n=$max_n2;
        }

        $tov_n_2=$max_n+1;

        $br_num=$tov_n_1.$tov_n_2;//итоговый номер брони

        $this->br_max_num=$tov_n_2;
        $this->br_num=$br_num;

        return true;
    }

    public function __construct()
    {
        $this->cr_time = new \DateTime();
    }

}
