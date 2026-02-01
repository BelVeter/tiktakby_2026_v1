<?php

namespace bb\classes;

use bb\Base;
use Illuminate\Support\Facades\Date;

class DohRashesAnalisys
{
  /**
   * @var DohRash[]
   */
  private $rashArray;
  /**
   * @var DohRash[]
   */
  private $dohArray;
  /**
   * @var array
   */
  private $rashResultArray; // rashResult

  /**
   * @var array
   */
  private $dohResultArray; // rushResult

  /**
   * @var array
   */
  private $salesResult;

  /**
   * @var array
   */
  private $salesKarnavalResult;

  /**
   * @var array
   */
  private $delivPaymentsResult;

  /**
   * @var array
   */
  private $zplOwnersArray;

  public function getDohArray(): array
  {
    return $this->dohArray;
  }

  public function setDohArray(array $dohArray): void
  {
    $this->dohArray = $dohArray;
  }

  public function getRashResultArray(): array
  {
    return $this->rashResultArray;
  }

  public function setRashResultArray(array $rashResultArray): void
  {
    $this->rashResultArray = $rashResultArray;
  }

  public function getDohResultArray(): array
  {
    return $this->dohResultArray;
  }

  public function setDohResultArray(array $dohResultArray): void
  {
    $this->dohResultArray = $dohResultArray;
  }// 0-11 array



  public function getRashArray(): array
  {
    return $this->rashArray;
  }

  public function setRashArray(array $rashArray): void
  {
    $this->rashArray = $rashArray;
  }

  public function getSalesResult(): array
  {
    return $this->salesResult;
  }

  public function setSalesResult(array $salesResult): void
  {
    $this->salesResult = $salesResult;
  }

  public function getDelivPaymentsResult(): array
  {
    return $this->delivPaymentsResult;
  }

  public function setDelivPaymentsResult(array $delivPaymentsResult): void
  {
    $this->delivPaymentsResult = $delivPaymentsResult;
  }




  public static function getYearDohRashes($year){
    $from = new \DateTime();
      $from->setDate($year,1,1);
      $from->setTime(0,0,0);
    $to= new \DateTime();
      $to->setDate($year, 12, 31);
      $to->setTime(23,59,59);



    $drA = new self();
    $drA->setRashArray(DohRash::getAllFiltered($from, $to,'rash'));
    $drA->setDohArray(DohRash::getAllFiltered($from, $to,'doh'));


    $drA->calculateDohRashResultArray();
    $drA->loadSalesResults($year);
    $drA->loadKarnavalResults($year);
    $drA->calculateOwnersZplArray($from, $to);


    return $drA;
  }


  public function calculateOwnersZplArray(\DateTime $from, \DateTime $to)
  {
    $this->zplOwnersArray = [0,0,0,0,0,0,0,0,0,0,0,0,0];

    $fromF = clone $from;
      $fromF->setTime(0,0,0);

    $toF = clone $fromF;
      $toF->modify('last day of this month');


    while ($toF<=$to){
      $zplAmount = DohRash::getDohRashByType2s(['zpl'], $fromF, $toF, [2,5]);

      $this->zplOwnersArray[$fromF->format('m')] = $zplAmount;

      $fromF->modify('+1month');
      $toF = clone $fromF;
        $toF->modify('last day of this month');
    }
  }

  public function mergeAvansesToRash(){
    for ($m=0; $m<=11;$m++){
      $this->rashResultArray['avans'][$m] += $this->dohResultArray['av_return'][$m];
    }
  }

  public static function getDohKeyItemsArrayCorrected()
  {
    $rez = DohRash::getAllDohKeyValues();
    $rez['zalog_vozvrat']='Залог возврат';
    $rez['tovar']='покупка товара';
    return $rez;
  }
  public static function getRashKeyItemsArrayCorrected()
  {
    $rez = DohRash::getAllRashKeyValues();
    unset($rez['zalog_vozvrat']);
    unset($rez['tovar']);
    return $rez;
  }

  public function calculateDohRashResultArray(){
    //rashs
    $itemsArray = DohRash::getAllRashKeyValues();

    $monthInitArray=[0,0,0,0,0,0,0,0,0,0,0,0];
    foreach ($itemsArray as $key=>$value) {
      $itemsArray[$key] = $monthInitArray;
    }
    foreach ($this->rashArray as $r){
      $month = $r->getAccDate()->format('m')*1-1;
      $itemsArray[$r->getType2()][$month]+=$r->getAmount();
    }
    $this->rashResultArray = $itemsArray;

    //Dohs
    $itemsArray = DohRash::getAllDohKeyValues();

    $monthInitArray=[0,0,0,0,0,0,0,0,0,0,0,0];
    foreach ($itemsArray as $key=>$value) {
      $itemsArray[$key] = $monthInitArray;
    }
    foreach ($this->dohArray as $d){
      $month = $d->getAccDate()->format('m')*1-1;
      $itemsArray[$d->getType2()][$month]+=$d->getAmount();
    }

    $this->dohResultArray = $itemsArray;


    //correction: move all zalogs to other doh-rash
    $this->dohResultArray['zalog_vozvrat'] = $this->rashResultArray['zalog_vozvrat'];
    unset($this->rashResultArray['zalog_vozvrat']);

    //correction: move tovar_buy to other doh-rash
    $this->dohResultArray['tovar'] = $this->rashResultArray['tovar'];
    unset($this->rashResultArray['tovar']);

  }

  public function getRashRezultArray(){
    return $this->rashResultArray;
  }

  public function getRashForMonth($rash, $m){
    if (isset($this->rashResultArray[$rash][$m-1])) return $this->rashResultArray[$rash][$m-1];
    else return 0;
  }
  public function getRashForMonthString($rash, $m){
    return number_format($this->getRashForMonth($rash, $m),0,',',' ');
  }

  public function getRashForYearTotal($rash){
    $rez = 0;
    for ($m=1; $m<=12;$m++){
      $rez+=$this->getRashForMonth($rash, $m);
    }
    return $rez;
  }

  public function getRashTotalForMonth($m){
    $total=0;
    foreach ($this->rashResultArray as $key=>$value){
      $total+=$this->rashResultArray[$key][$m-1];
    }
    return $total;
  }

  public function getOperResultForMonthString($m)
  {
    return number_format($this->getOperResultForMonth($m),0,',',' ');
  }

  public function getOperResultNoOwnersZplForMonthString($m)
  {
    return number_format(($this->getOperResultForMonth($m)-$this->getOwnersZplForMonth($m)),0,',',' ');
  }

  public function getOperResultForMonth($m)
  {
    $rez = $this->getSalesForMonth($m)+$this->getRashTotalForMonth($m);
    return $rez;
  }


  public function getRashTotalForMonthString($m){
    return number_format($this->getRashTotalForMonth($m),0,',',' ');
  }

  public function getRashTotalYear(){
    $rez = 0;
    for ($m=1; $m<=12;$m++){
      $rez+=$this->getRashTotalForMonth($m);
    }
    return $rez;
  }

  public function getOperResultTotalYear(){
    $rez = 0;
    for ($m=1; $m<=12;$m++){
      $rez+=$this->getOperResultForMonth($m);
    }
    return $rez;
  }

  public function getDohForMonthString($doh, $m){
    return number_format($this->getDohForMonth($doh, $m),0,',',' ');
  }

  public function getDohForMonth($doh, $m){
    if (isset($this->dohResultArray[$doh][$m-1])) return $this->dohResultArray[$doh][$m-1];
    else return 0;
  }

  public function getDohForYear($doh){
    $rez = 0;
    for ($m=1; $m<=12;$m++){
      $rez+=$this->getDohForMonth($doh, $m);
    }
    return $rez;
  }

  public function getDohTotalForMonth($m){
    $total=0;
    foreach ($this->dohResultArray as $key=>$value){
      $total+=$this->dohResultArray[$key][$m-1];
    }
    return $total;
  }

  public function getOwnersZplForMonth($m)
  {
    return $this->zplOwnersArray[$m];
  }

  public function getDohTotalForMonthString($m){
    return number_format($this->getDohTotalForMonth($m),0,',',' ');
  }

  public function getTotalResult($m)
  {
    return $this->getDohTotalForMonth($m) + $this->getOperResultForMonth($m);
  }

  public function getTotalResultString($m)
  {
    return number_format($this->getTotalResult($m),0,',',' ');
  }

  public function getTotalDohForYear(){
    $rez = 0;
    for ($m=1; $m<=12;$m++){
      $rez+=$this->getDohTotalForMonth($m);
    }
    return $rez;
  }


  public function loadSalesResults($year){
    $this->salesResult=[0,0,0,0,0,0,0,0,0,0,0,0];
    $this->delivPaymentsResult=[0,0,0,0,0,0,0,0,0,0,0,0];

    $from = new \DateTime();
      $from->setDate($year,1,1);
      $from->setTime(0,0,0);
    $to= new \DateTime();
      $to->setDate($year, 12, 31);
      $to->setTime(23,59,59);

      $fromTmp = clone $from;
      $toTmp = clone $to;


    for ($month=1; $month <= 12; $month++) {
      $fromTmp->setDate($year,$month,1);
      $toTmp->setDate($year,$month+1,1)->modify("-1day");
      $monthSalesArray = Deal::getSalesRentDeliv($fromTmp,$toTmp);
      $this->salesResult[$month-1] = $monthSalesArray['sales'];
      $this->delivPaymentsResult[$month-1] = $monthSalesArray['deliv'];
    }
  }

  public function loadKarnavalResults($year){
    $this->salesKarnavalResult=[0,0,0,0,0,0,0,0,0,0,0,0];

    $from = new \DateTime();
    $from->setDate($year,1,1);
    $from->setTime(0,0,0);
    $to= new \DateTime();
    $to->setDate($year, 12, 31);
    $to->setTime(23,59,59);

    $fromTmp = clone $from;
    $toTmp = clone $to;


    for ($month=1; $month <= 12; $month++) {
      $fromTmp->setDate($year,$month,1);
      $toTmp->setDate($year,$month+1,1)->modify("-1day");

      $karnavalCatIds = Category::getKarnavalCatIdsArray();

      $monthSalesArray = Deal::getSalesRentCatFilter($fromTmp,$toTmp,$karnavalCatIds);
      $this->salesKarnavalResult[$month-1] = $monthSalesArray['sales'];
    }
  }

  public function getSalesForMonth($m){
    return $this->salesResult[$m-1];
  }

  public function getSlesForMonthString($m){
    return number_format($this->getSalesForMonth($m),0,',', ' ');
  }

  public function getDelivPaymentsForMonth($m){
    return $this->delivPaymentsResult[$m-1];
  }

  public function getDelivPaymentsForMonthString($m){
    return number_format($this->getDelivPaymentsForMonth($m),0,',', ' ');
  }

  public function getKarnavalSalesForMonth($m){
    return $this->salesKarnavalResult[$m-1];
  }

  public function getKarnavalSlesForMonthString($m){
    return number_format($this->getKarnavalSalesForMonth($m),0,',', ' ');
  }

  public function getNonKarnavalSalesForMonth($m){
    return ($this->salesResult[$m-1] - $this->salesKarnavalResult[$m-1]);
  }

  public function getNonKarnavalSlesForMonthString($m){
    return number_format($this->getNonKarnavalSalesForMonth($m),0,',', ' ');
  }


  public function getTotalYearSales(){
    $rez = 0;
    for ($m=1; $m<=12;$m++){
      $rez+=$this->getSalesForMonth($m);
    }
    return $rez;
  }

  public function getTotalKarnYearSales(){
    $rez = 0;
    for ($m=1; $m<=12;$m++){
      $rez+=$this->getKarnavalSalesForMonth($m);
    }
    return $rez;
  }

  public function getTotalDelivYearPayments(){
    $rez = 0;
    for ($m=1; $m<=12;$m++){
      $rez+=$this->getDelivPaymentsForMonth($m);
    }
    return $rez;
  }

}
