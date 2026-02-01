<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 28.11.2018
 * Time: 21:57
 */

namespace bb;


class Schedule
{
    public static $schedule = array(//day=> open, close - 24h if open = -1 - day off
        '1'=>[10,19],
        '2'=>[10,19],
        '3'=>[10,19],
        '4'=>[10,19],
        '5'=>[10,19],
        '6'=>[10,16],
        '0'=>[10,16],//воскресенье
    );

    public static function isWorkingDay (\DateTime $date, $office='', $le='') {

        //проверяем исключения - нужно доработать функцию
        if(false) {
            //нужно доработать функцию
        }
        else {
            $day=$date->format("w");
            if (self::$schedule[$day][0]<1) {//if day-off
                return false;
            }
            else {
                return true;
            }
        }
    }

  /**
   * @param \DateTime $fromSource
   * @param \DateTime $toSource
   * @return array|false
   */
  public static function getDateOpenCloseHoursArrayForPreiod(\DateTime $fromSource, \DateTime $toSource){
      $rez = [];
      $from = clone $fromSource;
      $to = clone $toSource;
      if ($from>$to) return false;

      while ($from<=$to){
        $rez[]=[$from->format("Y-m-d"),self::getOpenHour($from), self::getCloseHour($from)];
        $from->modify("+1day");
      }
      return $rez;
    }

    public static function isWorkingTime(\DateTime $datetime) {
        //доработать проверку на рабочий день

        //проверка на рабочий час
        $h=$datetime->format("H");
        if ($h>=self::getOpenHour($datetime) && $h<=self::getCloseHour($datetime)) {
            return true;
        }
        else {
            return false;
        }
    }

    public static function getOpenHour(\DateTime $date, $office='', $le='') {
        //проверяем исключения - нужно доработать функцию в т.ч. в части сохранения в статике уже запрошенных исключений (штоб сократить количесво запросов к базе)
        if(false) {
            //нужно доработать функцию
        }
        else {
            $day=$date->format("w");
            return self::$schedule[$day][0];
        }
    }

    public static function getCloseHour(\DateTime $date, $office='', $le='') {
        //проверяем исключения - нужно доработать функцию в т.ч. в части сохранения в статике уже запрошенных исключений (штоб сократить количесво запросов к базе)
        if(false) {
            //нужно доработать функцию
        }
        else {
            $day=$date->format("w");
            return self::$schedule[$day][1];
        }
    }

    public static function setOpenDayTimeLeft(\DateTime $dateTime) {
        while (!Schedule::isWorkingDay($dateTime)) {
            $dateTime->modify("-1 day");
        }
        $dateTime->setTime(Schedule::getOpenHour($dateTime), 00);
        return true;
    }
    public static function setCloseDayTimeRight(\DateTime $dateTime) {
        while (!Schedule::isWorkingDay($dateTime)) {
            $dateTime->modify("+1 day");
        }
        $dateTime->setTime(Schedule::getCloseHour($dateTime), 00);
    }



}
