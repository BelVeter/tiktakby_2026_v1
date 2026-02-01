<?php


namespace bb\classes;


use bb\Base;

class TariffModel
{
    private $model_id;
    /**
     * @var []Tariff[]
     */
    private static $all_tariffs;
    /**
     * @var Tariff[]
     */
    private $tariffs;

    public function __construct()
    {
        $this->tariffs=[];
    }

    /**
     * @param $model_id
     * @return Tariff[]|bool
     * @throws \Exception
     */
    public static function getTariffsForModel($model_id) {

        if (isset(self::$all_tariffs[$model_id])) {//check if already loaded

            return self::$all_tariffs[$model_id];
        }
        else {

            $tarifs = [];

            $mysqli = \bb\Db::getInstance()->getConnection();
            //запрашиваем тарифы
            $query = "SELECT * FROM rent_tarif_act WHERE model_id='$model_id' ORDER BY sort_num, kol_vo";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }


            while ($row = $result->fetch_assoc()) {
                $tar = Tariff::getFromDbArray($row);
                $tarifs[] = clone $tar;
            }
            //Base::varDamp(self::$tariffs);
            self::$all_tariffs[$model_id] = $tarifs;
            //Base::varDamp(self::$all_tariffs);

            return $tarifs;
        }
    }

    /**
     * @param $modelId
     * @return TariffModel|bool
     * @throws \Exception
     */
    public static function getTarifModelForModelId($modelId){
        $mod_tariffs = new self();
            $mod_tariffs->tariffs = self::getTariffsForModel($modelId);
        return $mod_tariffs;
    }

    /**
     * @return Tariff[]
     */
    public function getTarifs(){
        return $this->tariffs;
    }

    /**
     * @param $model_id
     * @return Tariff|void|null
     * @throws \Exception
     */
    public static function getChippestTarifByModelId($model_id){

        $mysqli = \bb\Db::getInstance()->getConnection();
        //запрашиваем тариф the smallest one
        $query = "SELECT *, (rent_amount/(kol_vo*sort_num)) as sort_f FROM rent_tarif_act WHERE model_id='$model_id' ORDER BY sort_f LIMIT 1";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        if ($result->num_rows<1) return null;
        else {
            return Tariff::getFromDbArray($result->fetch_assoc());
        }
    }

    public function allTariffsText() {
        if (count($this->tariffs)<1) {
            return 'нет';
        }
        else {
            $rez = '';
            foreach ($this->tariffs as $t) {
                $rez .= $t->kol_vo . ' ' . $t->shortStepText() . ' - '.number_format($t->rent_amount, 2, ',', ' ').'<br>';
            }
            return $rez;
        }
    }

    public function tarNum(){
        if(is_array($this->tariffs)) return count($this->tariffs);
        else return 0;

    }

    public static function getHtmlTarifInputs($model_id){
        $rez='';
        $tars = self::getTariffsForModel($model_id)->getTarifs();
        if ($tars & count($tars)>0) {
            foreach ($tars as $t){
                $rez.='<input type="hidden" class="tarifs-in-days" name="'.$t->getPeriodInDays().'" value="'.$t->getTotalAmount().'">';
            }
            return $rez;
        }
        else return false;

    }

    /**
     * @param $days
     * @return float
     */
    public function getAmmountForDaysPeriod($days){
        if (count($this->getTarifs()) <1 ) return 0;

        $tarifs = [];

        foreach ($this->getTarifs() as $t) {
          if ($t->getDaysCalculatedNumber()==0) {
            $tarifs[] = [0, 0];
          }
          else{
            $tarifs[] = [$t->getDaysCalculatedNumber(), round(($t->getTotalAmount()/$t->getDaysCalculatedNumber()),2)];
          }

        }

        usort($tarifs, function ($a,$b){
            return $a[0]-$b[0];
        });

        $tarifPerDay = $tarifs[0][1];
        foreach ($tarifs as $tar) {
            if ($days>=$tar[0]) $tarifPerDay = $tar[1];
        }

        return round(($days*$tarifPerDay),2);
    }

    /**
     * @param $days
     * @return float
     */
    public function getDaylyTarifForDaysPeriod($days){

        if (count($this->getTarifs()) <1 ) return 0;

        $tarifs = [];

        foreach ($this->getTarifs() as $t) {
          if ($t->getDaysCalculatedNumber()==0){
            $tarifs[] = [0, 0];
          }
          else{
            $tarifs[] = [$t->getDaysCalculatedNumber(), round(($t->getTotalAmount()/$t->getDaysCalculatedNumber()),2)];
          }

        }

//        dd($tarifs);
        usort($tarifs, function ($a,$b){
            return $a[0]-$b[0];
        });

        $tarifPerDay = $tarifs[0][1];

        foreach ($tarifs as $tar) {
            if ($days>=$tar[0]) $tarifPerDay = $tar[1];
        }

        return round($tarifPerDay,2);
    }



}
