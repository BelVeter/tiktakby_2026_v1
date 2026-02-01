<?php

namespace App\MyClasses;

use bb\Base;
use bb\classes\tovar;
use bb\KBron;

class KBronLine
{
  /**
   * @var \DateTime
   */
  private $startDateTime;
  /**
   * @var \DateTime
   */
  private $endDateTime;
  /**
   * @var \DateTime
   */
  private $targetDate;

  private $invNum;

  private $freePeriods;
  private $brons;

  private $pixelPerHour;
  private $hourGAP;

  /**
   * @var tovar
   */
  private $tovar;

  public function __construct()
  {
    $this->pixelPerHour = 6;
    $this->hourGAP = 6;
  }

  /**
   * @return tovar
   */
  public function getTovar(): tovar
  {
    return $this->tovar;
  }
  /**
   * @param tovar $tovar
   */
  public function setTovar(tovar $tovar): void
  {
    $this->tovar = $tovar;
  }

  /**
   * @return \DateTime
   */
  public function getStartDateTime(): \DateTime
  {
    return $this->startDateTime;
  }

  /**
   * @param \DateTime $startDateTime
   */
  public function setStartDateTime(\DateTime $startDateTime): void
  {
    $this->startDateTime = $startDateTime;
  }

  /**
   * @return \DateTime
   */
  public function getEndDateTime(): \DateTime
  {
    return $this->endDateTime;
  }

  /**
   * @param \DateTime $endDateTime
   */
  public function setEndDateTime(\DateTime $endDateTime): void
  {
    $this->endDateTime = $endDateTime;
  }

  /**
   * @return \DateTime
   */
  public function getTargetDate(): \DateTime
  {
    return $this->targetDate;
  }

  /**
   * @param \DateTime $targetDate
   */
  public function setTargetDate(\DateTime $targetDate): void
  {
    $this->targetDate = $targetDate;
  }

  /**
   * @return mixed
   */
  public function getInvNum()
  {
    return $this->invNum;
  }

  /**
   * @param mixed $invNum
   */
  public function setInvNum($invNum): void
  {
    $this->invNum = $invNum;
  }

  /**
   * @return KBron[]
   */
  public function getFreePeriods()
  {
    return $this->freePeriods;
  }

  /**
   * @param mixed $freePeriods
   */
  public function setFreePeriods($freePeriods): void
  {
    $this->freePeriods = $freePeriods;
  }

  /**
   * @return mixed
   */
  public function getBrons()
  {
    return $this->brons;
  }

  /**
   * @param mixed $brons
   */
  public function setBrons($brons): void
  {
    $this->brons = $brons;
  }

  /**
   * @return int
   */
  public function getPixelPerHour(): int
  {
    return $this->pixelPerHour;
  }

  /**
   * @return int
   */
  public function getHourGAP(): int
  {
    return $this->hourGAP;
  }

  public function getInvNFormated(){
    return substr($this->invNum, 0, 3).'-'.substr($this->invNum, 3);
  }


  /**
   * @param $invNum
   * @param \DateTime $targetDate
   * @return KBronLine
   */
  public static function getLine($invNum, \DateTime $targetDate){
    $l = new self();

    $l->setTovar(tovar::getTovarByInvN($invNum));

    $l->setInvNum($invNum);

    $startDate = clone $targetDate;
      $startDate->modify('-1 day');
      $startDate->setTime(0,0,0);
      $startDate->modify('-'.$l->getHourGAP().' hours');
    $l->setStartDateTime($startDate);

    $endDate = clone $targetDate;
    //!!! it is improtant to add 2 days - no obvious that otherwise there will be no 3 days period
      $endDate->setTime(0,0,0);
      $endDate->modify('+2 day');
      $endDate->modify('+'.$l->getHourGAP().' hours');
    $l->setEndDateTime($endDate);

    $l->setFreePeriods(KBron::getFreePeriodsInv($invNum, $startDate, $endDate));
    //dd(KBron::getBrons($invNum,$startDate,$endDate));
    //dd($l->getFreePeriods());
    return $l;

  }



  /**
   * @return float|int
   */
  public function getLineWidthInPixels(){
    $hourDiff = $this->endDateTime->diff($this->startDateTime);
    //dd($this->startDateTime, $this->endDateTime, $hourDiff);
    $px = ($hourDiff->d*24 + $hourDiff->h) * $this->pixelPerHour;
    return $px;
  }

  public function getDaysCircleArray(){

  }

  /**
   * @return float[]|int[]|mixed
   */
  public function getFreePeriodsCssArray(){//return left, width
    $rez=[];

    foreach ($this->getFreePeriods() as $fp) {
      $rez[]=[$fp->hoursFromStart($this->getStartDateTime())*$this->pixelPerHour, $fp->hoursDuration()*$this->pixelPerHour];
    }

    return $rez;
  }

  public function getDayCirclesCss(){//left, free/bussy class
    $rez=[];
    $counter = 0;
    $day = clone $this->getStartDateTime();
      $day->setTime(0,0,0);
      $day->modify('+1 day');
    while ($day<=$this->getEndDateTime()){
      $dif = $day->diff($this->startDateTime);
      $hDiff = $dif->d*24 + $dif->h;

      $class = 'busy';
      foreach ($this->getFreePeriods() as $f){
        if ($day>=$f->from_kb && $day<=$f->to_kb) $class='free';
      }

      $rez[]=[$hDiff*$this->pixelPerHour, $class];

      $day->modify('+1 day');
      $counter++;
      if ($counter>10) break;
    }
    return $rez;
  }

}
