<?php

class DeliveryPage
{
    /**
     * @var DateTime
     */
    private $date;

    /**
     * @var bool|Delivery[]
     */
    private $viezds;

    private $dateFilter = 'today';//today, tomorrow, tomorrow++, yesterday
    private $curFilter = 'all';//all, free, my, notmy

    /**
     * @var Delivery[]
     */
    private $deliget_deliveries;



    /**
     * @return bool|Delivery[]
     */
    public function getCurViezdsAll() {
        return $this->viezds;
    }

    /**
     * @return bool|Delivery[]
     */
    public function getCurViezdsFiltered() {
        switch ($this->curFilter){
            case 'my':
                return array_filter($this->viezds, function ($v) {
                   return \bb\models\User::getCurrentUser()->id_user == $v->getCurId();
                });
                break;
            case 'free':
                return array_filter($this->viezds, function ($v) {
                    return $v->getCurId() == 0;
                });
                break;
            case 'notmy':
                return array_filter($this->viezds, function ($v) {
                    return \bb\models\User::getCurrentUser()->id_user != $v->getCurId() && $v->getCurId()>0;
                });
                break;
        }
        return $this->viezds;// all
    }

    public static function inv_print ($inv_n) {

        $output=substr($inv_n, 0, 3).'-'.substr($inv_n, 3);

        return $output;

    }

    /**
     * @param $curId
     * @return string
     */
    public function getHeartCssStyleName($curId, $status) {
        $class='';
        $userId=\bb\models\User::getCurrentUser()->id_user;
        if ($curId == 0 || $curId == '') $class='';
        elseif ($userId == $curId) $class = 'taken';
        else $class = 'notmy';

        if ($status=='done') $class .= ' done';
        if ($status=='fail') $class .= ' fail';

        return $class;
    }

    /**
     * @return int
     */
    public function getMyViyezdCount() {
        if (!$this->getCurViezdsAll()) return 0;

        $totalMy = count(array_filter($this->getCurViezdsAll(), function ($v) {
            if ($v->getCurId()==\bb\models\User::getCurrentUser()->id_user) return true;
            else return false;
        }));
        if(!$totalMy) $totalMy=0;
        return $totalMy;
    }

    /**
     * @return int
     */
    public function getTotalViyezdCount() {
        if (!$this->getCurViezdsAll()) return 0;

        $totalMy = count($this->getCurViezdsAll());
        if(!$totalMy) $totalMy=0;
        return $totalMy;
    }

    /**
     * @return int
     */
    public function getNotMyViyezdCount() {
        if (!$this->getCurViezdsAll()) return 0;

        $totalMy = count(array_filter($this->getCurViezdsAll(), function ($v) {
            if ($v->getCurId()!=\bb\models\User::getCurrentUser()->id_user && $v->getCurId() > 0) return true;
            else return false;
        }));

        return $totalMy;
    }

    /**
     * @return int
     */
    public function getFreeViyezdCount() {
        if (!$this->getCurViezdsAll()) return 0;

        $totalMy = count(array_filter($this->getCurViezdsAll(), function ($v) {
            if ($v->getCurId() == 0) return true;
            else return false;
        }));
        return $totalMy;
    }

    /**
     * @return DateTime
     */
    public function getPageDate(){
        return $this->date;
    }

    /**
     * @return string
     */
    public function getHeaderDateString(){
        return  \bb\Base::strUpperFirstLetter(\bb\Base::getShortWeekDay($this->date->format("w"))).' '.\bb\Base::strUpperFirstLetter(\bb\Base::getShorMonthText($this->date->format("m"))).' '.$this->date->format("d");
    }

    /**
     * @return string|void
     */
    public function getDayTextForHeader(){
        $today = new DateTime();
            $today->setTime(0,0,0);

        if($today->getTimestamp() == $this->date->getTimestamp()) return 'Сегодня';
        else {
            $dayDiff=$today->diff($this->date)->days;
            if ($dayDiff==1) {
                if ($today->getTimestamp()>$this->date->getTimestamp()) return 'Вчера';
                else return 'Завтра';
            }
        }
    }

    /**
     * @return int
     */
    public function getDelitedNum(){
        if (is_array($this->deliget_deliveries)) {
            return count($this->deliget_deliveries);
        }
        return 0;
    }


    /**
     * @return array|Delivery[]
     */
    public function getDelitedDeliveries(){
        return $this->deliget_deliveries;
    }

    function __construct($dateFilter='today', $cur_id='all'){

        $this->dateFilter=$dateFilter;
        $this->curFilter=$cur_id;

        switch ($dateFilter) {
            case 'tomorrow':
                $date = new DateTime();
                    $date->setTime(0,0,0);
                    $date->modify("+1 day");
                break;
            case 'yesterday':
                $date = new DateTime();
                    $date->setTime(0,0,0);
                    $date->modify("-1 day");
                break;
            case 'tomorrow++':
                $date = new DateTime();
                $date->setTime(0,0,0);
                $date->modify("+2 day");
                break;
            default: // today
                $date = new DateTime();
                $date->setTime(0,0,0);
                break;
        }
        $this->date = $date;

        $all_deliveries = Delivery::getDeliveries($date, 'all');

        if ($all_deliveries) {
            for ($i=0; $i<count($all_deliveries); $i++) {
                //echo $i.'-'.$this->viezds[$i]->getOffice().'-<br>';
                if ($all_deliveries[$i]->getOffice()<1) {
                    //echo 'brak';
                    $this->deliget_deliveries[] = $all_deliveries[$i];
                }
                else {
                    $this->viezds[] = $all_deliveries[$i];
                }
            }
        }


    }

}