<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 29.05.2019
 * Time: 21:36
 */

namespace bb\models;


use bb\Db;

class Kassa
{
    public $id_kassa;
    public $id_le;

    //? - убрать
    public $office;//office number

    public $channel; //office, personal, bank, courier
    public $channel_num; //for office: number of office; for personal: id of User; for bank - account number
    public $kassa_type; //k1(nal_cheque), k2 (nal_no_cheque), card, bank
    public $kassa_name;
    public $official_num;
    public $active;



    public function saveOstatok(){

    }

    public function getKassaNameForUser(){
        return $this->getKassaTypeText().': '.$this->official_num;
    }

    public function getKassaTypeText(){
        switch ($this->kassa_type){
            case 'nal_cheque':
                return 'Касса №1';
                break;
            case 'nal_cheque':
                return 'Касса №2';
                break;
            case 'card':
                return 'Терминал (карточка)';
                break;
            case 'bank':
                return 'Банк';
                break;
            default:
                return 'Тип кассы не определен';
                break;
        }
    }

    /**
     * @param $channel_type
     * @param $channel_num
     * @param string $le_id
     * @param string $kassa_type
     * @return Kassa[]|bool
     */
    public static function getKassasForChannel($channel_type, $channel_num, $le_id='all', $kassa_type='all'){
        $mysqli=Db::getInstance()->getConnection();

        $srch='';
        if ($kassa_type!='all') {
            $srch.=" AND kassa_type='$kassa_type'";
        }
        if ($le_id!='all') {
            $srch.=" AND id_le='$le_id'";
        }

        $query="SELECT * FROM kassa_list WHERE channel='$channel_type' AND channel_num='$channel_num'$srch";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);
        if ($result->num_rows>0){

            $rez=array();
            while ($line=$result->fetch_assoc()){
                $kassa=self::getFromDbArray($line);
                $rez[]=clone $kassa;
            }
            return $rez;

        }
        else {
            return false;
        }

    }

    public static function getFromDbArray($ar){
        $k=new self();

        foreach ($k as $key=>$value) {
            if (isset($ar[$key])){
                $k->$key=$ar[$key];
            }
        }
        return $k;
    }

    /**
     * @param $op_type
     * @param $channel
     * @return Kassa[]
     */
    public static function getKassaTypesListOperations($op_type, $channel_select){
        $ch = new KassaChannel();
        $ch->loadFromSelect($channel_select);

        $rez=array();
        switch ($ch->type) {
            case 'bank':
                $ks = new self();
                $ks->kassa_type='1';
                $ks->kassa_name='счёт № 1';
                $rez[]=$ks;
                break;
            default:
                $ks = new self();
                $ks->kassa_type='k1';
                $ks->kassa_name='Касса 1';
                $rez[]=$ks;

                $ks = new self();
                $ks->kassa_type='k2';
                $ks->kassa_name='Касса 2';
                $rez[]=$ks;
                break;
        }
        return $rez;
        
    }

}