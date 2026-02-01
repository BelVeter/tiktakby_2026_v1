<?php

namespace App\MyClasses;

use bb\KBron;
use bb\Schedule;

class KBForm
{
  private $tovarName;
  private $rostFrom;
  private $rostTo;
  private $size;
  /**
   * @var KBron[]
   */
  private $freePeriods;

  /**
   * @var \DateTime[]
   */
  private $fromDays;
  /**
   * @var []
   */
  private $fromTimes;
  /**
   * @var \DateTime[]
   */
  private $toDays;
  /**
   * @var []
   */
  private $toTimes;

  /**
   * @var \DateTime
   */
  private $eventDate;

  public function __construct()
  {
    $this->fromDays=[];
    $this->toDays=[];

      $workingHoursArray=[9,10,11,12,13,14,15,16,17,18,19,20];
    $this->fromTimes=$workingHoursArray;
    $this->toTimes=$workingHoursArray;
  }


  /**
   * @return KBron[]
   */
  public function getFreePeriods(): array
  {
    return $this->freePeriods;
  }

  public function setFreePeriods(array $freePeriods): void
  {
    $this->freePeriods = $freePeriods;
  }



  /**
   * @param KBron $freePeriod
   * @return \DateTime[]
   */
  public static function getFromDaysArray(KBron $freePeriod, \DateTime $eventDay){
        if ($freePeriod->from_kb->format("Y-m-d") == $freePeriod->to_kb->format("Y-m-d")) {
      return [$freePeriod->from_kb];
    }

    $rez = [];
    $today = clone $eventDay;
      $today->setTime(23,59,59);
    $start = clone $freePeriod->from_kb;
    $end = clone $freePeriod->to_kb;
      $end->setTime(23,59,59);

    if ($end>$today) $end = $today;
          while ($start<=$end) {
        $rez[]=clone $start;
        $start->modify('+1day');
      }
    return $rez;
  }
  /**
   * @param KBron $freePeriod
   * @return \DateTime[]
   */
  public static function getToDaysArray(KBron $freePeriod, \DateTime $eventDay){
    if ($freePeriod->from_kb->format("Y-m-d") == $freePeriod->to_kb->format("Y-m-d")) return [$freePeriod->from_kb];

    $rez = [];
    $today = clone $eventDay;
      $today->setTime(23,59,59);

    $start = $today;
    $end = clone $freePeriod->to_kb;
      $end->setTime(23,59,59);
      while ($start<=$end) {
        $rez[]=clone $start;
        $start->modify('+1day');
      }
    return $rez;
  }

  /**
   * @param \DateTime $fromDay
   */
  public function addFromDay(\DateTime $fromDay): void
  {
    $this->fromDays[] = $fromDay;
  }

  /**
   * @return mixed
   */
  public function getFromTimes()
  {
    return $this->fromTimes;
  }

  /**
   * @param $fromTime
   */
  public function addFromTime($fromTime): void
  {
    $this->fromTimes[] = $fromTime;
  }

  /**
   * @return \DateTime []
   */
  public function getToDays()
  {
    return $this->toDays;
  }

  /**
   * @param \DateTime $toDay
   */
  public function addToDay(\DateTime $toDay): void
  {
    $this->toDays[] = $toDay;
  }

  /**
   * @return array
   */
  public function getToTimes(): array
  {
    return $this->toTimes;
  }

  /**
   * @param $toTime
   */
  public function addToTime($toTime): void
  {
    $this->toTimes[] = $toTime;
  }



  /**
   * @return \DateTime
   */
  public function getEventDate(): \DateTime
  {
    return $this->eventDate;
  }

  /**
   * @param \DateTime $eventDate
   */
  public function setEventDate(\DateTime $eventDate): void
  {
    $this->eventDate = $eventDate;
  }

  /**
   * @return mixed
   */
  public function getRostFrom()
  {
    return $this->rostFrom;
  }

  /**
   * @param mixed $rostFrom
   */
  public function setRostFrom($rostFrom): void
  {
    $this->rostFrom = $rostFrom;
  }

  /**
   * @return mixed
   */
  public function getRostTo()
  {
    return $this->rostTo;
  }

  /**
   * @param mixed $rostTo
   */
  public function setRostTo($rostTo): void
  {
    $this->rostTo = $rostTo;
  }

  /**
   * @return mixed
   */
  public function getTovarName()
  {
    return $this->tovarName;
  }

  /**
   * @param mixed $tovarName
   */
  public function setTovarName($tovarName): void
  {
    $this->tovarName = $tovarName;
  }

  /**
   * @return mixed
   */
  public function getSize()
  {
    return $this->size;
  }

  /**
   * @param mixed $size
   */
  public function setSize($size): void
  {
    $this->size = $size;
  }

  /**
   * @param \DateTime $day
   * @param KBron $freePeriod
   * @return array
   */
  public static function getWorkingHoursArray(\DateTime $day, KBron $freePeriod){
    $minHour = Schedule::getOpenHour($day);
    //correct min hour if start of free period is later and the day is the same as start of free period
    if ($day->format('Y-m-d') == $freePeriod->from_kb->format('Y-m-d')) {
      if ((int)$freePeriod->from_kb->format('H')>$minHour) $minHour = (int)$freePeriod->from_kb->format('H');
    }

    $maxHour = Schedule::getCloseHour($day);
    //correct max hour if end of free period is earlier and the day is the same as end of free period
    if ($day->format('Y-m-d') == $freePeriod->to_kb->format('Y-m-d')) {
      if ((int)$freePeriod->to_kb->format('H')<$maxHour) $maxHour = (int)$freePeriod->to_kb->format('H');
    }

    $rez = [];
    while ($minHour <= $maxHour){
      $rez[]=$minHour;
      $minHour=$minHour+1;
    }

    return $rez;
  }






}
