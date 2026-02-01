<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 29.05.2019
 * Time: 21:37
 */

namespace bb\models;

use bb\Base;
use bb\Db;

class KassaOperation
{
    private static $_doh_items;
    private static $_rash_items;

    public $id_operation;
    /**
     * @var \DateTime
     */
    public $acc_date;
    public $amount;
    public $type; //rash, doh, shift_plus, shift_minus
    public $type2; //[any]
    public $channel; //1,2,3 bank, cur             office, personal, bank        poka chto if number == office
    public $channel_num; // for office - office num, for bank - account num, for personal - id of user
    //                      !!! when I change all the rashodi na functions from this class - change the SQL base logic: channel-office, office num - chnnel_num
    public $kassa; //k1, k2, bank
    public $link_to; //for type=shift - correspondind shift operation_id, for other operations, it is link to advance operation (id), for avans - 1 if not (fully) used, 0 if fully used
    public $info;

    public $cr_who;
    /**
     * @var \DateTime
     */
    public $cr_time;
    public $dr_name_id; //id of User to which doh or rash opperation is connected (salary, avance)
    public $id_le;
    /**
     * @var \DateTime
     */
    public $zpl_period;

    /**
     * @return string
     */
    private static function getTableName() {
        return 'doh_rash';
    }

    /**
     * @param OperationForm $form
     * @return bool
     */
    public function formLoad(OperationForm $form) {
        $this->acc_date=$form->operation_date;
        switch ($form->operation_type) {
            case 'rash':
            case 'shift_minus':
                $this->amount=-abs($form->op_amount);
                break;
            case 'doh':
            case 'shift_plus':
                $this->amount=abs($form->op_amount);
                break;
            default:
                $this->amount=$form->op_amount;
                break;
        }
        $this->type=$form->operation_type;
        $this->type2=$form->operation_type2;
        //пока что в канале: если число - значит номер офиса, если cur = personal
        $channel=new KassaChannel();
        $channel->loadFromSelect($form->channel);
            $this->channel=$channel->type;
            $this->channel_num=$channel->channel_number;
        $this->kassa=$form->kassa_type;
        if ($this->type2=='avans' && $this->amount<0) {
            $this->link_to=1;
        }
        $this->info=$form->info;
        $this->dr_name_id=$form->pesonal_op_id;
        //!!!$this->id_le='';
        $this->zpl_period = $form->dateFromZplPeriod();

        return true;
    }

    public static function getRDItemName($op_type, $rd_code) {
        $mysqli = Db::getInstance()->getConnection();
        switch ($op_type) {
            case 'doh':
                if (self::$_doh_items==null) {
                    self::$_doh_items=array();

                    $ri_q = "SELECT * FROM doh_items ORDER BY rd_order";
                    $result_ri = $mysqli->query($ri_q);
                    if (!$result_ri) {die('Сбой при доступе к базе данных: '.$ri_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

                    while ($ri=$result_ri->fetch_assoc()) {
                        self::$_doh_items[$ri['rd_code']]=$ri['rd_text'];
                    }
                }
                if (isset(self::$_doh_items[$rd_code])) {
                    return self::$_doh_items[$rd_code];
                }
                else return 'не найдено';
                break;

            case 'rash':
                if (self::$_rash_items==null) {
                    self::$_rash_items=array();

                    $ri_q = "SELECT * FROM rash_items ORDER BY ri_order";
                    $result_ri = $mysqli->query($ri_q);
                    if (!$result_ri) {die('Сбой при доступе к базе данных: '.$ri_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

                    while ($ri=$result_ri->fetch_assoc()) {
                        self::$_rash_items[$ri['ri_code']]=$ri['ri_text'];
                    }
                }
                if (isset(self::$_rash_items[$rd_code])) {
                    return self::$_rash_items[$rd_code];
                }
                else {
                    return '<br><br>--'.$rd_code.'не найдено';
                }
                break;

            case 'shift_plus':
                return 'перевод из кассы';
                break;
            case 'shift_minus':
                return 'перевод в кассу';
                break;

            default:
                return 'тип не описан';
                break;
        }



        if ($bank_yn==0) {
            $ri_q = "SELECT * FROM doh_items WHERE bank_yn!=1 ORDER BY rd_order";
        }
        else {
            $ri_q = "SELECT * FROM doh_items ORDER BY rd_order";
        }

        $result_ri = $mysqli->query($ri_q);
        if (!$result_ri) {die('Сбой при доступе к базе данных: '.$ri_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        while ($ri=$result_ri->fetch_assoc()) {
            $op=new self();
            $op->load_type2_array_db($ri);
            $rez[]=$op;
        }
    }


    /**
     * @param $op_type
     * @param int $bank_yn
     * @return KassaOperation[]
     */
    public static function getOperationTypes2($channel, $op_type){
        $mysqli = Db::getInstance()->getConnection();
        if ($channel!='bank') $bank_yn=0;
        else $bank_yn=1;

        $rez=array();
        switch ($op_type) {
            case 'doh':
                if ($bank_yn==0) {
                    $ri_q = "SELECT * FROM doh_items WHERE bank_yn!=1 ORDER BY rd_order";
                }
                else {
                    $ri_q = "SELECT * FROM doh_items ORDER BY rd_order";
                }

                $result_ri = $mysqli->query($ri_q);
                if (!$result_ri) {die('Сбой при доступе к базе данных: '.$ri_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

                while ($ri=$result_ri->fetch_assoc()) {
                    $op=new self();
                    $op->load_type2_array_db($ri);
                    $rez[]=$op;
                }

                break;
            case 'rash':

                if ($bank_yn==0) {
                    $ri_q = "SELECT * FROM rash_items WHERE bank_yn!=1 ORDER BY ri_order";
                }
                else {
                    $ri_q = "SELECT * FROM rash_items ORDER BY ri_order";
                }

                $result_ri = $mysqli->query($ri_q);
                if (!$result_ri) {die('Сбой при доступе к базе данных: '.$ri_q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

                while ($ri=$result_ri->fetch_assoc()) {
                    $op=new self();
                    $op->load_type2_array_db($ri);
                    $rez[]=$op;
                }
                break;
        }
        return $rez;
    }

    public function zplDateMysql() {
        if ($this->zpl_period==null) {
            return "null";
        }
        else {
            return "'".$this->zpl_period->format("Y-m-d")."'";
        }
    }

    /**
     * @return bool
     */
    public function save(){
        $ok=true;
        $mysqli = Db::getInstance()->getConnection();

        if ($this->channel=='office') {
            $channel_save=$this->channel_num;
        }
        else {
            $channel_save=$this->channel;
        }

        $query="INSERT INTO ".self::getTableName()." SET acc_date='".$this->acc_date->getTimestamp()."', amount='$this->amount', type1='$this->type', type2='$this->type2', channel='$channel_save', kassa='$this->kassa', link_to='".(int)$this->link_to."', info='".mysqli_real_escape_string($mysqli, $this->info)."', cr_time='".time()."', cr_who_id='".User::getCurrentUser()->id_user."', dr_name_id='".(int)$this->dr_name_id."', le_id='".(int)$this->id_le."', channel_num='$this->channel_num', zpl_period=".$this->zplDateMysql();
        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
            //echo $query;
            $ok=false;
        }
        $this->id_operation=$mysqli->insert_id;
        return $ok;
    }

    public static function deleteId($id){
        $ok=true;
        $mysqli = Db::getInstance()->getConnection();
        Db::startTransaction();
        $op=KassaOperation::getOperation($id);
        $del_ids=array();
        $del_ids[]=$op->id_operation;
            if ($op->isShift()) {
                $del_ids[]=$op->link_to;
            }

        $query="DELETE from doh_rash WHERE dr_id IN(".implode(',', $del_ids).")";
        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
            //echo $query;
            $ok=false;
        }

        if ($ok) {
            Db::commitTransaction();
        }
        else {
            Db::rollBackTransaction();
        }
        //echo $query;

        return $ok;

    }

    /**
     * @return bool
     */
    public function isShift() {
        if ($this->type=='shift_plus' || $this->type=='shift_minus') {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function update(){
        $mysqli = Db::getInstance()->getConnection();

        $query = "UPDATE ".self::getTableName()." SET link_to='".$this->link_to."' WHERE dr_id='".$this->id_operation."'";
        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
        }
        return true;
    }

    public function load_db($id){

    }

    public function load_type2_array_db($ar) {
        if (isset($ar['ri_id'])) {
            $this->type='rash';
            $this->id_operation=$ar['ri_id'];
            $this->name2=$ar['ri_text'];
            $this->type2=$ar['ri_code'];
        }
        elseif (isset($ar['rd_id'])) {
            $this->type='doh';
            $this->id_operation=$ar['rd_id'];
            $this->name2=$ar['rd_text'];
            $this->type2=$ar['rd_code'];
        }
    }

    public function load_array_db($ar) {
            $this->id_operation=$ar['dr_id'];
            $this->acc_date=new \DateTime();
                $this->acc_date->setTimestamp($ar['acc_date']);
            $this->amount=$ar['amount'];
            $this->type=$ar['type1'];
            $this->type2=$ar['type2'];
            if (is_numeric($ar['channel'])) {
                $this->channel='office';
            }
            else {
                $this->channel=$ar['channel'];
            }
            $this->kassa=$ar['kassa'];
            $this->link_to=$ar['link_to'];
            $this->info=$ar['info'];
            $this->cr_time= new \DateTime();
                $this->cr_time->setTimestamp($ar['cr_time']);
            $this->cr_who=$ar['cr_who_id'];
            $this->dr_name_id=$ar['dr_name_id'];
            $this->id_le=$ar['le_id'];
            if ($ar['channel_num']>0) {
                $this->channel_num=$ar['channel_num'];
            }
            else {
                if ($ar['type1']>0) {
                    $this->channel_num=$ar['type1'];
                }
            }

            if ($ar['zpl_period']!=NULL) {
                $this->zpl_period=new \DateTime($ar['zpl_period']);
            }
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return KassaOperation[]
     */
    public static function getOperations (\DateTime $from, \DateTime $to, $op_type1, KassaChannel $channel, $op_type2, OperationForm $form) {
        /**
         * @var KassaOperation
         */
        $ops=array();

        $srch_close='';
        switch ($channel->type) {
            case 'bank':
                $srch_close.=" AND `channel`='bank'";
                break;

            case 'office':
                $srch_close.=" AND `channel`='$channel->channel_number'";
                break;

            case 'personal':
                $srch_close.=" AND `channel`='personal' AND channel_num='$channel->channel_number'";
                break;
        }
        if ($op_type1!='') {
            if ($op_type1=='shift') {
                $srch_close.=" AND (`type1`='shift_plus' OR `type1`='shift_minus')";
                if ($form->shift_to_channel_srch!='' && $form->kassa_type_shift_srch=='') {
                    $ch=new KassaChannel();
                    $ch->loadFromSelect($form->shift_to_channel_srch);
                    $srch_close.=" AND (`type2` LIKE '$ch->type-$ch->channel_number%')";
                }
                elseif ($form->shift_to_channel_srch=='' && $form->kassa_type_shift_srch!='') {
                    $srch_close.=" AND (`type2` LIKE '%$form->kassa_type_shift_srch')";
                }
                elseif ($form->shift_to_channel_srch!='' && $form->kassa_type_shift_srch!='') {
                    $ch=new KassaChannel();
                    $ch->loadFromSelect($form->shift_to_channel_srch);
                    $srch_close.=" AND `type2`='$ch->type-$ch->channel_number-$form->kassa_type_shift_srch'";
                }
            }
            else {
                $srch_close.=" AND `type1`='$op_type1'";
            }

        }
        if ($op_type2!='') {
            if ($op_type1=='shift') {
                //$srch_close.="AND (`type1`='shift_plus' OR `type1`='shift_minus')";
            }
            else {
                $srch_close.=" AND `type2`='$op_type2'";
            }

        }
        if ($form->kassa_type_srch!='') {
            $srch_close.=" AND `kassa`='$form->kassa_type_srch'";
        }
        if ($form->type1_srch=='rash' && in_array($form->type2_srch, array('zpl', 'adv')) && $form->pesonal_op_id_srch>0) {
            $srch_close.=" AND `dr_name_id`='$form->pesonal_op_id_srch'";
        }
        //!!! убрать - т.к. не нужен, но проверить, вдруг нужен
        if (1!=1 && ($form->shift_to_channel_srch!='' || $form->kassa_type_shift_srch!='')) {
            $ops_id=array();
            $srch_close2=" AND type1='shift_plus'";
            if ($form->shift_to_channel_srch!='') {
                $srch_chan=KassaChannel::loadFormSelect($form->shift_to_channel_srch);
                $srch_close2.=" AND channel='".$srch_chan->getDbChannelValue()."' AND channel_num='$srch_chan->channel_number";
            }
            if ($form->kassa_type_shift_srch!='') {
                $srch_close2.=" AND `kassa`='".$form->kassa_type_shift_srch."'";
            }

            $mysqli2 = Db::getInstance()->getConnection();
            $query2 = "SELECT * FROM ".self::getTableName()." WHERE acc_date>='".$from->getTimestamp()."' AND acc_date<='".$to->getTimestamp()."' $srch_close2 ORDER BY acc_date DESC, dr_id DESC";
            //echo $query;
            $result2 = $mysqli2->query($query2);
            if (!$result2) {
                printf("Mysqli Errormessage: %s\n", $mysqli2->error);
            }
            while ($op2=$result2->fetch_assoc()) {
                $ops_id[]=$op2['dr_id'];
            }

            if (array_count_values($ops_id)>0) {

            }
        }

//echo "--".$op_type2;

        $mysqli = Db::getInstance()->getConnection();
        $query = "SELECT * FROM ".self::getTableName()." WHERE acc_date>='".$from->getTimestamp()."' AND acc_date<='".$to->getTimestamp()."' $srch_close ORDER BY acc_date DESC, dr_id DESC";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
        }
        while ($op=$result->fetch_assoc()) {
            $t_opp=new self();
            $t_opp->load_array_db($op);
            $ops[]=clone $t_opp;
        }
        return $ops;
    }

    /**
     * @param KassaOperation $op
     * @return string
     */
    public static function getDbShiftToString(KassaOperation $op){
        $rez=$op->channel.'-'.$op->channel_num.'-'.$op->kassa;
        return $rez;
    }

    public static function decodeDbShiftToString($string){

    }

    public function getShiftToString() {
        $ar=explode('-', $this->type2);
        return KassaChannel::getKassaChannelName($ar[0], $ar[1]);

    }

    /**
     * @param $id
     * @return KassaOperation
     */
    public static function getOperation ($id)
    {
        $op = new self();

        $mysqli = Db::getInstance()->getConnection();
        $query = "SELECT * FROM " . self::getTableName() . " WHERE dr_id='" . $id . "'";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
        }
        $op_rez = $result->fetch_assoc();
        $op->load_array_db($op_rez);

        return $op;
    }

    public function __construct()
    {
        $this->channel_num=0;
        $this->acc_date= new \DateTime();
        $this->cr_time= new \DateTime();
    }
}