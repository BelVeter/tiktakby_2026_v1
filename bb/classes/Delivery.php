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
    private $typeDelivery; //deal, custom
    private $typeSubDlType;//first_rent, extention, cur_return
    private $subDlId;
    private $status;//new, in_process, done, fail
    private $info;

    /**
     * @var DateTime
     */
    private $startTime;
    /**
     * @var DateTime
     */
    private $finishTime;

    private $comments;
    private $parrendId; // in case - multidelivery
    private $orderNum;//for sorting lines on cur-page

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
    public function getTypeSubDl()
    {
        return $this->typeSubDlType;
    }

    /**
     * @param mixed $typeSubDlType
     */
    public function setTypeSubDl($typeSubDlType)
    {
        $this->typeSubDlType = $typeSubDlType;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getAddressFrom()
    {
        return $this->addressFrom;
    }

    /**
     * @param mixed $addressFrom
     */
    public function setAddressFrom($addressFrom)
    {
        $this->addressFrom = $addressFrom;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * @param mixed $info
     */
    public function setInfo($info)
    {
        $this->info = $info;
    }

    /**
     * @return mixed
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @param mixed $startTime
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     * @return mixed
     */
    public function getFinishTime()
    {
        return $this->finishTime;
    }

    /**
     * @param mixed $finishTime
     */
    public function setFinishTime($finishTime)
    {
        $this->finishTime = $finishTime;
    }

    /**
     * @return mixed
     */
    public function getParrendId()
    {
        return $this->parrendId;
    }

    /**
     * @param mixed $parrendId
     */
    public function setParrendId($parrendId)
    {
        $this->parrendId = $parrendId;
    }

    /**
     * @return mixed
     */
    public function getOrderNum()
    {
        return $this->orderNum;
    }

    /**
     * @param mixed $orderNum
     */
    public function setOrderNum($orderNum)
    {
        $this->orderNum = $orderNum;
    }

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
    public function getTypeDelivery()
    {
        return $this->typeDelivery;
    }

    /**
     * @param mixed $typeDelivery
     */
    public function setTypeDelivery($typeDelivery)
    {
        $this->typeDelivery = $typeDelivery;
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
        $rez = [];
        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "SELECT delivery.*, rent_model_web.l2_pic, tovar_rent_items.item_place, tovar_rent_items.model_id, rent_deals_act.deal_id, rent_deals_act.item_inv_n, rent_sub_deals_act.info as sub_info
                FROM `delivery`

                LEFT JOIN rent_sub_deals_act ON rent_sub_deals_act.sub_deal_id = delivery.sub_deal_id
                LEFT JOIN rent_deals_act ON rent_sub_deals_act.deal_id = rent_deals_act.deal_id
                LEFT JOIN tovar_rent_items ON tovar_rent_items.item_inv_n = rent_deals_act.item_inv_n
                LEFT JOIN rent_model_web ON rent_model_web.model_id = tovar_rent_items.model_id

                WHERE delivery.date = '".$date->getTimestamp()."'";

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
     * @param $sub_deal_id
     * @return bool|void
     */
    public static function cancelDelivery($sub_deal_id){
        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "DELETE FROM delivery WHERE sub_deal_id='$sub_deal_id'";
        //echo $q;
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        return true;
    }

    /**
     * @param $row
     * @return Delivery
     */
    private static function getFromDbArray($row){
        $cur = new self();
        $cur->setId($row['id']);
            $cur_date = new DateTime();
            $cur_date->setTimestamp($row['date']);
        $cur->setDate($cur_date);
        $cur->setAddressFrom($row['address_from']);
        $cur->setAddressTo($row['address_to']);
        $cur->setCurId($row['cur_id']);
        $cur->setTypeDelivery($row['type_delivery']);
        $cur->setTypeSubDl($row['type_sub_dl']);
        $cur->setSubDlId($row['sub_deal_id']);
        $cur->setStatus($row['status']);

        if (isset($row['sub_info']) && $row['info'] != $row['sub_info']) {
          $cur->setInfo($row['info'].'<br>'.$row['sub_info']);//for office-customer
        }
        else {
          $cur->setInfo($row['info']);//for office-customer
        }

        $cur->setComments($row['comments']);//for curier
            $start_time = new DateTime();
            $start_time->setTimestamp($row['start_time']);
        $cur->setStartTime($start_time);
            $finish_time = new DateTime();
            $finish_time->setTimestamp($row['finish_time']);
        $cur->setFinishTime($finish_time);
        $cur->setOrderNum($row['order_num']);

//        private $imgUrl;
//          private $invN;
//          private $modelId;
//          private $office;
//          private $dealId;

        $cur->setImgUrl($row['l2_pic']);
        $cur->setInvN($row['item_inv_n']);
        $cur->setOffice($row['item_place']);
        $cur->setDealId($row['deal_id']);
        $cur->setModelId($row['model_id']);

        //for individual request
        if (isset($row['client_id'])) $cur->setClientId($row['client_id']);

        return $cur;
    }


    /**
     * @param Delivery $array[]
     * @return Delivery
     */
    private static function loadAvailabilityOnOtherLocations ($array) {

        if (count($array) < 1) return false;//if nothing to load

        $tmp_arr = array_filter($array, function ($item) {
            //$item = new self();
            return ($item->getTypeSubDl() == 'first_rent' && $item->getModelId()>0);
        });

        if (count($tmp_arr) < 1) return false;//if nothing to load


        $modelIds = array_map(function ($v) {
            return $v->getModelId();
        }, $tmp_arr);

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
     * @return void
     */
    private function getParrentIdAndAssign() {
        if ($this->curId=='' || $this->curId== null) $this->curId=0;
        $mysqli = \bb\Db::getInstance()->getConnection();
        $query = "SELECT id FROM delivery WHERE date='".$this->date->getTimestamp()."' AND address_to='$this->addressTo' AND cur_id='$this->curId'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        if ($result->num_rows<1) {
            $this->id = 0;
        }
        else {
            $this->id = $result->fetch_assoc()['id'];
        }

    }

    /**
     * @return array
     */
    public function getAdditionalFreeOffices() {
        return $this->otherFreeLocations;
    }

    /**
     * @return bool|void
     */
    public function checkForDublicatesSubDL(){
        $mysqli = \bb\Db::getInstance()->getConnection();
        $query = "SELECT * FROM delivery WHERE sub_deal_id='".$this->getSubDlId()."'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        if ($result->num_rows>0) return true;
        else return false;
    }

    /**
     * @return bool|void
     */
    public function saveNew(){
        $mysqli = \bb\Db::getInstance()->getConnection();
        $query = "INSERT INTO delivery SET
            date='".$this->getDate()->getTimestamp()."',
            address_from='".$this->getAddressFrom()."',
            address_to='".$this->getAddressTo()."',
            cur_id='".$this->getCurId()."',
            type_delivery='".$this->getTypeDelivery()."',
            type_sub_dl='".$this->getTypeSubDl()."',
            sub_deal_id='".$this->getSubDlId()."',
            `status`='new',
            info='".$this->getInfo()."',
            start_time='".$this->getStartTime()->getTimestamp()."',
            finish_time='".$this->getFinishTime()->getTimestamp()."',
            comments='".$this->getComments()."',
            order_num='".$this->getOrderNum()."'
            ";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $this->setId($mysqli->insert_id);

        return true;
    }


    /**
     * @param $sub_dl_id
     * @return bool|void
     */
    public static function createDeliveryBySubDlId($sub_dl_id){
        $delivery = self::getDeliveryBySubDlId($sub_dl_id);
        //\bb\Base::varDamp($delivery);
        if ($delivery->checkForDublicatesSubDL()) {
            \bb\Base::addErrorMessage('Попытка сохранения дубликата доставки. Уже существует доставка для операции с таким же ID операции');
            return false;
        }
        return $delivery->saveNew();

    }

    /**
     * @param $sub_dl_id
     * @return Delivery|false|void
     */
    private static function getDeliveryBySubDlId($sub_dl_id) {
        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "SELECT rent_sub_deals_act.sub_deal_id, rent_sub_deals_act.courier_id, rent_sub_deals_act.acc_date, rent_sub_deals_act.type, rent_sub_deals_act.info, clients.city, clients.str, clients.dom, clients.kv, rent_model_web.l2_pic, tovar_rent_items.item_place, tovar_rent_items.model_id, rent_deals_act.deal_id, rent_deals_act.client_id, rent_deals_act.item_inv_n

                FROM `rent_sub_deals_act`

                LEFT JOIN rent_deals_act ON rent_sub_deals_act.deal_id = rent_deals_act.deal_id
                LEFT JOIN clients ON clients.client_id = rent_deals_act.client_id
                LEFT JOIN tovar_rent_items ON tovar_rent_items.item_inv_n = rent_deals_act.item_inv_n
                LEFT JOIN rent_model_web ON rent_model_web.model_id = tovar_rent_items.model_id

                WHERE rent_sub_deals_act.sub_deal_id = '$sub_dl_id'";

        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        if ($result->num_rows <1) return false;

        $row = $result->fetch_assoc();


        $cur = new self();
        $cur->setId(0);
            $cur_date = new DateTime();
            $cur_date->setTimestamp($row['acc_date']);
        $cur->setDate($cur_date);
        $cur->setAddressTo('г.'.$row['city'].', '.$row['str'].' '.$row['dom'].'-'.$row['kv']);
        $cur->setCurId($row['courier_id']);
        $cur->setTypeDelivery('deal');
        $cur->setTypeSubDl($row['type']);
        $cur->setSubDlId($row['sub_deal_id']);
        $cur->setStatus('new');
        $cur->setInfo($row['info']);//for office-customer

        $cur->setImgUrl($row['l2_pic']);
        $cur->setInvN($row['item_inv_n']);
        $cur->setOffice($row['item_place']);
        $cur->setDealId($row['deal_id']);
        $cur->setModelId($row['model_id']);

        //for individual request
        if (isset($row['client_id'])) $cur->setClientId($row['client_id']);

        return $cur;


    }

    /**
     * @return Delivery|false|void
     */
    public static function getDeliveryById($id){
        //echo '---'.$id.'---';
        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "SELECT delivery.*, rent_model_web.l2_pic, tovar_rent_items.item_place, tovar_rent_items.model_id, rent_deals_act.deal_id, rent_deals_act.client_id, rent_deals_act.item_inv_n, rent_sub_deals_act.info as sub_info
                FROM `delivery`

                LEFT JOIN rent_sub_deals_act ON rent_sub_deals_act.sub_deal_id = delivery.sub_deal_id
                LEFT JOIN rent_deals_act ON rent_sub_deals_act.deal_id = rent_deals_act.deal_id
                LEFT JOIN tovar_rent_items ON tovar_rent_items.item_inv_n = rent_deals_act.item_inv_n
                LEFT JOIN rent_model_web ON rent_model_web.model_id = tovar_rent_items.model_id

                WHERE delivery.id = '$id'";

        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        if ($result->num_rows <1) return false;

        $rez = self::getFromDbArray($result->fetch_assoc());

        $tmp_array=[$rez];

        self::loadAvailabilityOnOtherLocations($tmp_array);

        return $tmp_array[0];
    }

    /**
     * @param $id
     * @return bool|void
     */
    public function assignCur($id) {
        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "UPDATE delivery SET cur_id='$id' WHERE id = '".$this->getId()."'";
        //echo $q;
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        return true;
    }

    /**
     * @param $sub_dl_id
     * @param $info
     * @return bool|void
     */
    public static function updateInfoReplace($sub_dl_id, $info) {
        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "UPDATE delivery SET info='$info' WHERE sub_deal_id = '$sub_dl_id'";
        //echo $q;
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        return true;
    }

  /**
   * @param $clientId
   * @param $address
   * @return bool|void
   */
  public static function updateAddressForClient($clientId, $address){
      $delivIds=[];

      $mysqli = \bb\Db::getInstance()->getConnection();
      $q = "SELECT delivery.id FROM `delivery`
          LEFT JOIN rent_sub_deals_act ON delivery.sub_deal_id=rent_sub_deals_act.sub_deal_id
          LEFT JOIN rent_deals_act ON rent_deals_act.deal_id = rent_sub_deals_act.deal_id
          WHERE rent_deals_act.client_id = $clientId";
      $result = $mysqli->query($q);
      if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

      while ($row = $result->fetch_assoc()) {
        $delivIds[]=$row['id'];
      }

      //echo 'deliv-'.var_dump($delivIds);

      if (count($delivIds)>0) {
        foreach ($delivIds as $subDlId) {
          $q = "UPDATE delivery SET address_to ='$address' WHERE id = '$subDlId'";
          $result = $mysqli->query($q);
          if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        }
      }

      return true;
    }

    /**
     * @return string
     */
    public function getOneOfficeLetterForPage() {
        if ($this->getTypeSubDl() == 'first_rent' || $this->getTypeSubDl() == 'takeaway_plan') {
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
        if ($this->getTypeSubDl() == 'first_rent' || $this->getTypeSubDl() == 'takeaway_plan') {
            return \bb\models\Office::getOfficeByNumber($this->getOffice())->getCssColor();
        }
        elseif ($this->isReturn()) {
            return '#FFFFFF';
        }
        else {
            return '#9400D4';
        }
    }

    function __construct(){
        $this->otherFreeLocations = [];
        $date_zero = new DateTime();
        $date_zero->setTimestamp(0);

        $this->date = clone $date_zero;
        $this->startTime = clone $date_zero;
        $this->finishTime = clone $date_zero;
    }

    public function isReturn(){
        if ($this->getTypeSubDl() == 'close' || $this->getTypeDelivery() == 'cur_return') return true;
    }

    /**
     * @return void
     */
    public static function creatDeliveriesFromSubDealsBulk(){
        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "SELECT sub_deal_id FROM rent_sub_deals_act WHERE `status` = 'for_cur'";
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        while ($row = $result->fetch_assoc()) {
            self::createDeliveryBySubDlId($row['sub_deal_id']);
        }
    }

    /**
     * @param $message
     * @return bool|void
     */
    public function makeDone($message=''){
        $this->setFinishTime(new DateTime());
        $this->setStatus('done');
        $this->setComments($message);

        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "UPDATE delivery SET finish_time='".$this->getFinishTime()->getTimestamp()."', `status` = 'done', comments='".$this->getComments()."' WHERE id = '".$this->getId()."'";
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        return true;
    }

    /**
     * @param $message
     * @return bool|void
     */
    public function makeFail($message=''){
        $this->setFinishTime(new DateTime());
        $this->setStatus('fail');
        $this->setComments($message);

        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "UPDATE delivery SET finish_time='".$this->getFinishTime()->getTimestamp()."', `status` = 'fail', comments='".$this->getComments()."' WHERE id = '".$this->getId()."'";
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        return true;
    }

    /**
     * @return bool|void
     */
    public function makeNewAgain(){
        $this->setStatus('new');

        $mysqli = \bb\Db::getInstance()->getConnection();
        $q = "UPDATE delivery SET finish_time='', `status` = 'new', comments='' WHERE id = '".$this->getId()."'";
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        return true;
    }
}//end of class
