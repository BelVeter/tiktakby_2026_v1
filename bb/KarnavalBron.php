<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 28.11.2018
 * Time: 21:21
 */

namespace bb;


class KarnavalBron
{
    public static $pause_time_h=4;//h
    public static $min_bron_time_h=4;//h

    public $id_kb;
    public $inv_n;
    public $cr_time;

    /**
     * @var \DateTime
     */
    public $from_kb;
    /**
     * @var \DateTime
     */
    public $to_kb;
    public $staus_kb;
    /**
     * @var \DateTime
     */
    public $valid_time;

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


    /**
     * @param $inv_n
     * @param $event_date
     * @return KarnavalBron[]
     */
    public static function getFreePeriodsInv($inv_n, \DateTime $start_time, \DateTime $end_time){
//        Base::varDamp($start_time);
//        Base::varDamp($end_time);

        $brons=KarnavalBron::getBrons($inv_n, $start_time, $end_time);
//        Base::varDamp($brons);

//        Base::varDamp($start_time);
//        Base::varDamp($end_time);
        /**
         * @var KarnavalBron[]
         */
        $free_brons=array();


        if ($brons==false) {//если брони отсутствуют - создаем один большой период свободности
            $kb_free = new KarnavalBron();
            $kb_free->from_kb=clone $start_time;
            $kb_free->to_kb=clone $end_time;
            $kb_free->inv_n=$inv_n;

            $free_brons[]=$kb_free;

        }
        else {
            //проходимся по броням
            foreach ($brons as $bron) {
                if ($bron->from_kb>$end_time) break;
                if ($time_array=self::getBronTimeFromFreePeriod($start_time, $bron->from_kb)) {
                    //создаем новую свободную бронь
                    $kb_free = new KarnavalBron();
                    $kb_free->from_kb=$time_array[0];
                    $kb_free->to_kb=$time_array[1];
                    $kb_free->inv_n=$inv_n;

                    $free_brons[]=$kb_free;
                }

                //а теперь сдвигаем "период с"
                $start_time = clone $bron->to_kb;
            }
            //последий период (если оставется после брони)
            if (self::getBronTimeFromFreePeriod($start_time, $end_time)) {
                //создаем новую свободную бронь
                $kb_free = new KarnavalBron();
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
    public static function getBronTimeFromFreePeriod (\DateTime $start_time, \DateTime $end_time) {//I need star_time, end_time, pause_hours, min_bron_duration_hours
        //бронь обязательно должна быть внутри периода, т.е. "с" и "по" могут только сокращаться, но не разрастаться
        $start_correction = true;
        $end_correction = true;
        if ($end_time<=$start_time) return false;
        else {//если период менее минимально возможного для брони - сразу выводим отказ
            $diff0=$start_time->diff($end_time);
            $diff0_h=$diff0->h+$diff0->days*24;
            if ($diff0_h < self::$min_bron_time_h) return false;
        }
        $start_t = clone $start_time;
        //если начало не совпадает с началом рабочего дня (значит конец какой-то брони)
        if ($start_t->format("H")!=Schedule::getOpenHour($start_t)) {
            $start_t->modify("+".self::$pause_time_h." hour");
        }
        while (!Schedule::isWorkingTime($start_t)) {
            $start_t->modify("+1 hour");
        }

        $end_t = clone $end_time;
        if ($end_t->format("H")!=Schedule::getCloseHour($end_t)) {
            $end_t->modify("-".self::$pause_time_h." hour");
        }
        while (!Schedule::isWorkingTime($end_t)) {
            $end_t->modify("-1 hour");
        }



        $diff=$start_t->diff($end_t);
        $diff_h=$diff->h+$diff->days*24;

        if ($diff_h>=self::$min_bron_time_h) {
            $free_period = array($start_t, $end_t);
            return $free_period;
        }
        else {
            return false;
        }
    }

    /**
     * @param \DateTime $event_time
     * @return bool | KarnavalBron[]
     */
    public static function getBrons($inv_n, \DateTime $start_time_obj, \DateTime $end_time_obj, array $excluded_br_ids=null){

        /**
         * @var KarnavalBron[]
         */
        $brons=array();

        if ($excluded_br_ids!=null) {
            $id_excl_filt=" AND `kb_id` NOT IN (". implode(',', array_map('intval', $excluded_br_ids)).")";
        }
        else $id_excl_filt = '';


        $start_time=(int)$start_time_obj->getTimestamp();
        $end_time=(int)$end_time_obj->getTimestamp();

        $mysqli = Db::getInstance()->getConnection();

        $br_validity_time =(int) (time()+self::getPauseTimeSec());

        //var_dump($end_time);

        //запрашиваем брони
        $q_from = "SELECT * FROM karn_brons WHERE inv_n= '$inv_n' AND (`status` IN ('new', 'ok') OR (`status`='in_process' 
        AND `cr_time` >= '$br_validity_time')) AND ((t_from BETWEEN '$start_time' AND '$end_time') OR (t_to BETWEEN '$start_time' 
        AND '$end_time'))$id_excl_filt ORDER BY t_to";

        //echo $q_from.'<br><br>';
        $q_result=$mysqli->query($q_from);
        if (!$q_result) {die('Сбой при доступе к базе данных: '.$q_from.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $rows_n = $q_result->num_rows;

        if ($rows_n<1) {
            return false;
        }

        while ($row=$q_result->fetch_assoc()) {
            $kb=KarnavalBron::getFromDbArray($row);
            $brons[]=$kb;
        }

        return $brons;
    }

    public static function getFromDbArray($db_ar) {
        $kb=new self();
        $kb->id_kb=$db_ar['kb_id'];
        $kb->inv_n=$db_ar['inv_n'];
        $kb->from_kb= new \DateTime();
            $kb->from_kb->setTimestamp($db_ar['t_from']);
        $kb->to_kb = new \DateTime();
            $kb->to_kb->setTimestamp($db_ar['t_to']);
        $kb->staus_kb=$db_ar['status'];

        return $kb;
    }

    public static function tmpValid() {

    }

    public static function setTmpBron($inv_n, \DateTime $start_time, \DateTime $end_time){
        $mysqli = Db::getInstance()->getConnection();

        $query = "LOCK TABLES karn_brons WRITE";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}


        if (!self::getBrons($inv_n, $start_time, $end_time)) {//if no crossingbronsexists - save new tmp bron
            $kb = new self();
            $kb->inv_n=$inv_n;
            $kb->from_kb=$start_time;
            $kb->to_kb=$end_time;
            $kb->staus_kb='in_process';

        }



        $query = "UNLOCK TABLES";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
    }

}