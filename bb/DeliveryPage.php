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
    public function getHeartCssStyleName($curId) {
        $userId=\bb\models\User::getCurrentUser()->id_user;
        $userId=\bb\models\User::getCurrentUser()->id_user;
        if ($curId == 0 || $curId == '') return '';
        elseif ($userId == $curId) return 'taken';
        else return 'notmy';
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

        $this->viezds = Delivery::getDeliveries($date, 'all');
//        \bb\Base::varDamp($this->viezds);
    }

}