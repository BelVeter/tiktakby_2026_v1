<?php

class Delivery
{
    private $id;
    /**
     * @var DateTime
     */
    private $date;
    private $addressFrom;
    private $addressTo;
    private $curId;
    private $type; //deal, free
    private $subDlId;
    private $status;//new, in_process, done, fail
    private $info;
    private $startTime;
    private $finishTime;
    private $comments;

    private $imgUrl;
    private $invN;
    private $modelId;
    private $office;
    private $dealId;
    private $clientId;

    /**
     * @var []
     */
    private $otherFreeLocations;


    /**
     * @return mixed
     */
    public function getModelId()
    {
        return $this->modelId;
    }

    /**
     * @param mixed $modelId
     */
    public function setModelId($modelId)
    {
        $this->modelId = $modelId;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @param mixed $clientId
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    /**
     * @return mixed
     */
    public function getDealId()
    {
        return $this->dealId;
    }

    /**
     * @param mixed $dealId
     */
    public function setDealId($dealId)
    {
        $this->dealId = $dealId;
    }



    /**
     * @return DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param DateTime $date
     */
    public function setDate(DateTime $date)
    {
        $this->date = $date;
    }

    /**
     * @return mixed
     */
    public function getSubDlId()
    {
        return $this->subDlId;
    }

    /**
     * @param mixed $subDlId
     */
    public function setSubDlId($subDlId)
    {
        $this->subDlId = $subDlId;
    }

    /**
     * @return mixed
     */
    public function getImgUrl()
    {
        return $this->imgUrl;
    }

    /**
     * @param mixed $imgUrl
     */
    public function setImgUrl($imgUrl)
    {
        $this->imgUrl = $imgUrl;
    }

    /**
     * @return mixed
     */
    public function getInvN()
    {
        return $this->invN;
    }

    /**
     * @param mixed $invN
     */
    public function setInvN($invN)
    {
        $this->invN = $invN;
    }

    /**
     * @return mixed
     */
    public function getOffice()
    {
        return $this->office;
    }

    /**
     * @param mixed $office
     */
    public function setOffice($office)
    {
        $this->office = $office;
    }

    /**
     * @return mixed
     */
    public function getOfficeLetter()
    {
        return $this->officeLetter;
    }


    /**
     * @return mixed
     */
    public function getOfficeColor()
    {
        return $this->officeColor;
    }

    /**
     * @return mixed
     */
    public function getAddressTo()
    {
        return mb_ereg_replace('г.Минск, ', '', $this->addressTo);
    }

    /**
     * @param mixed $addressTo
     */
    public function setAddressTo($addressTo)
    {
        $this->addressTo = $addressTo;
    }

    /**
     * @return mixed
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @param mixed $comments
     */
    public function setComments($comments)
    {
        $this->comments = $comments;
    }

    /**
     * @return mixed
     */
    public function getCurId()
    {
        return $this->curId;
    }

    /**
     * @param mixed $curId
     */
    public function setCurId($curId)
    {
        $this->curId = $curId;
    }




    /**
     * @param DateTime $date
     * @param $cur_id
     * @param $status
     * @return Delivery[]|bool
     */
    public static function getDeliveries(DateTime $date, $cur_id='all', $status='all') {
        $srch='';
//        if(!is_numeric($cur_id)) {
//            switch ($cur_id){
//                case 'my':
//                    $srch.=" AND rent_sub_deals_act.courier_id='".\bb\models\User::getCurrentUser()->id_user."'";
//                    break;
//                case 'free':
//                    $srch.=" AND rent_sub_deals_act.courier_id=0";
//                    break;
//                case 'notmy':
//                    $srch.=" AND rent_sub_deals_act.courier_id!='".\bb\models\User::getCurrentUser()->id_user."' AND rent_sub_deals_act.courier_id>0";
//                    break;
//                default:
//
//            }
//        }


        $rez = [];
        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "SELECT clients.city, clients.str, clients.dom, clients.kv, rent_model_web.l2_pic, tovar_rent_items.item_place, tovar_rent_items.model_id, rent_deals_act.deal_id, rent_deals_act.client_id, rent_deals_act.item_inv_n, rent_sub_deals_act.sub_deal_id, rent_sub_deals_act.deal_id, rent_sub_deals_act.`type`, rent_sub_deals_act.courier_id, rent_sub_deals_act.`status`, rent_sub_deals_act.info, rent_sub_deals_act.acc_date, `from` 
                FROM `rent_sub_deals_act`

                LEFT JOIN rent_deals_act ON rent_sub_deals_act.deal_id = rent_deals_act.deal_id
                LEFT JOIN tovar_rent_items ON tovar_rent_items.item_inv_n = rent_deals_act.item_inv_n
                LEFT JOIN rent_model_web ON rent_model_web.model_id = tovar_rent_items.model_id
                LEFT JOIN clients ON clients.client_id = rent_deals_act.client_id

                WHERE rent_sub_deals_act.`status` = 'for_cur' AND rent_sub_deals_act.acc_date = '".$date->getTimestamp()."'".$srch;

        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        if ($result->num_rows <1) return false;

        while ($row = $result->fetch_assoc()) {
            $rez[]=self::getFromDbArray($row);
        }

        usort($rez, function ($a, $b) {
            return strnatcasecmp($a->getAddressTo(), $b->getAddressTo());
        });

        self::loadAvailabilityOnOtherLocations($rez);
        return $rez;
    }

    /**
     * @param Delivery $array[]
     * @return Delivery
     */
    private static function loadAvailabilityOnOtherLocations ($array) {
        $modelIds = array_map(function ($v) {
            return $v->getModelId();
        }, $array);

        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "SELECT model_id, item_place, COUNT(item_id) as num FROM tovar_rent_items 
                WHERE `status` IN ('to_rent', 't-bron') 
                AND model_id IN (".implode(',', $modelIds).")
                GROUP BY model_id, item_place";
        //echo $q;
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        $free_items = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $free_items[] = [$row['model_id'], $row['item_place']];
            }
        }
        //\bb\Base::varDamp($free_items);
        if(count($free_items)>0) {
            foreach ($array as $v) {
                //$v=new self();
                //\bb\Base::varDamp($v);
                foreach ($free_items as $ff) {
                    if ($v->getModelId() == $ff[0]) {
                        if($v->getOffice() != $ff[1]) $v->otherFreeLocations[]=$ff[1];
                    }
                }
            }
        }

    }

    /**
     * @param $row
     * @return Delivery
     */
    private static function getFromDbArray($row){
        $cur = new self();
        $cur->setSubDlId($row['sub_deal_id']);
        $cur->setImgUrl($row['l2_pic']);
        $cur->setInvN($row['item_inv_n']);
        $cur->setAddressTo('г.'.$row['city'].', '.$row['str'].' '.$row['dom'].'-'.$row['kv']);
        $cur->setComments($row['info']);
        $cur->setOffice($row['item_place']);
        $cur->setDealId($row['deal_id']);
        $cur->setType($row['type']);
        $cur->setModelId($row['model_id']);

        $cur_date = new DateTime();
        $cur_date->setTimestamp($row['acc_date']);
        $cur->setDate($cur_date);
        $cur->setCurId($row['courier_id']);
        $cur->setClientId($row['client_id']);

        return $cur;
    }

    /**
     * @return array
     */
    public function getAdditionalFreeOffices() {
        return $this->otherFreeLocations;
    }

    /**
     * @return Delivery|false|void
     */
    public static function getDeliveryById($id){
        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "SELECT clients.city, clients.str, clients.dom, clients.kv, rent_model_web.l2_pic, tovar_rent_items.item_place, tovar_rent_items.model_id, rent_deals_act.deal_id, rent_deals_act.client_id, rent_deals_act.item_inv_n, rent_sub_deals_act.sub_deal_id, rent_sub_deals_act.deal_id, rent_sub_deals_act.`type`, rent_sub_deals_act.courier_id, rent_sub_deals_act.`status`, rent_sub_deals_act.info, rent_sub_deals_act.acc_date, `from` 
                FROM `rent_sub_deals_act`

                LEFT JOIN rent_deals_act ON rent_sub_deals_act.deal_id = rent_deals_act.deal_id
                LEFT JOIN tovar_rent_items ON tovar_rent_items.item_inv_n = rent_deals_act.item_inv_n
                LEFT JOIN rent_model_web ON rent_model_web.model_id = tovar_rent_items.model_id
                LEFT JOIN clients ON clients.client_id = rent_deals_act.client_id

                WHERE rent_sub_deals_act.sub_deal_id = '$id'";

        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        if ($result->num_rows <1) return false;

        $rez = self::getFromDbArray($result->fetch_assoc());
        self::loadAvailabilityOnOtherLocations($rez);

        return $rez;
    }

    /**
     * @param $id
     * @return bool|void
     */
    public function assignCur($id) {
        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "UPDATE rent_sub_deals_act SET courier_id='$id' WHERE sub_deal_id = '$this->subDlId'";
        //echo $q;
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        return true;
    }

    /**
     * @return string
     */
    public function getOneOfficeLetterForPage() {
        if ($this->getType() == 'first_rent' || $this->getType() == 'takeaway_plan') {
            return \bb\models\Office::getOfficeByNumber($this->getOffice())->getOneLetterName();
        }
        else {
            return '*';
        }

    }

    /**
     * @return mixed|string
     */
    public function getOfficeCollor(){
        if ($this->getType() == 'first_rent' || $this->getType() == 'takeaway_plan') {
            return \bb\models\Office::getOfficeByNumber($this->getOffice())->getCssColor();
        }
        else {
            return '#9400D4';
        }
    }

    function __construct(){
        $this->otherFreeLocations = [];
    }
}
