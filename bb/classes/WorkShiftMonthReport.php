<?php

namespace bb\classes;

use bb\Base;

class WorkShiftMonthReport
{
  /**
   * @var \DateTime[];
   */
  public $startDate;
  /**
   * @var \DateTime[];
   */
  public $endDate;
  public $user_ids;
  /**
   * @var \DateTime[];
   */
  public $daysArray;

  private $workHours; //array uid[day=>hours]
  private $workHoursPlaceType; //array uid[day=>PlaceType]//office, vacation, home
  private $workHoursPlaceId; //array uid[day=>PlaceType]//office, vacation, home

  public function __construct()
  {
    $this->user_ids = [];
    $this->daysArray = [];
    $this->workHours = [];
  }


  /**
   * @param $id
   * @return void
   */
  public function addUserId($id){
    if (!in_array($id,$this->user_ids)) $this->user_ids[]=$id;
  }

  public function addWorkHours($userId, $dayNum, $hours, $placeType, $ofId){
    if (!isset($this->workHours[$userId])) {
      $this->workHours[$userId] = [];
      $this->workHoursPlaceType[$userId] = [];
      $this->workHoursPlaceId[$userId] = [];
    }
    if (isset($this->workHours[$userId][$dayNum])){
      $this->workHours[$userId][$dayNum] += $hours;

      if (isset($this->workHoursPlaceType[$userId][$dayNum])) $this->workHoursPlaceType[$userId][$dayNum] = 'multi';
      else $this->workHoursPlaceType[$userId][$dayNum] = $placeType;

      $this->workHoursPlaceId[$userId][$dayNum] = $ofId;
    }
    else{
      $this->workHours[$userId][$dayNum] = $hours;
      $this->workHoursPlaceType[$userId][$dayNum] = $placeType;
      $this->workHoursPlaceId[$userId][$dayNum] = $ofId;
    }

  }

  public function getWorkHours($userId, $dayNum){

    if ($this->getWorkHoursPlaceTypeClass($userId, $dayNum)=='vacation') return '';

    if (isset($this->workHours[$userId][$dayNum])) {
      return $this->workHours[$userId][$dayNum];
    }
    else{
      return '';
    }
  }

  public function getWorkHoursPlaceTypeClass($userId, $dayNum){//office1, vacation, home, multi
    $placeType='';
    if (isset($this->workHoursPlaceType[$userId][$dayNum])) $placeType = $this->workHoursPlaceType[$userId][$dayNum];

    if ($placeType=='vacation' || $placeType == 'home' || $placeType == 'multi') return $placeType;

    if ($placeType == 'office') {
      $ofId = '';
      if (isset($this->workHoursPlaceId[$userId][$dayNum])) $ofId = $this->workHoursPlaceId[$userId][$dayNum];

      return $placeType.$ofId;
    }


  }

  /**
   * @return \DateTime[]
   */
  public function getDaysArray(){
    return $this->daysArray;
  }

  public function getUserIdsArray(){
    return $this->user_ids;
  }

  /**
   * @param \DateTime $date
   * @return self
   */
  public static function getMonthReport(\DateTime $date){
    $rep = new self();

    $startDate = clone $date;
      $startDate->modify('first day of');
      $startDate->setTime(0,0,1);
      $rep->startDate=$startDate;

    $endDate = clone $date;
      $endDate->modify('last day of');
      $endDate->setTime(23,59,59);
      $rep->endDate=$endDate;

    $tmpDate = clone $rep->startDate;
    $i=0;
    while ($tmpDate<=$rep->endDate){
      $rep->daysArray[$tmpDate->format('d')*1] = clone $tmpDate;
      $tmpDate->modify('+1 day');
      $i++;
      if ($i>33) break;
    }

    $wShs = WorkShift::getAllForPeriod($startDate, $endDate);

    if ($wShs){
      foreach ($wShs as $wsh){
        $rep->addUserId($wsh->getUserId());
        $rep->addWorkHours($wsh->getUserId(), $wsh->getDate()->format('d')*1, $wsh->getHoursDiff(), $wsh->getPlaceType(), $wsh->getPlaceId());
      }
    }


    return $rep;
  }


}
