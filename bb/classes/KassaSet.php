<?php


namespace bb\classes;


use bb\Base;
use bb\Db;
use bb\models\Kassa;

class KassaSet
{
    /**
     * @var Kassa|Kassa[]
     */
    public $nal_cheque;//id
    /**
     * @var Kassa|Kassa[]
     */
    public $nal_no_cheque;//id
    /**
     * @var Kassa|Kassa[]
     */
    public $card;//id
    /**
     * @var Kassa[]
     */
    public $bank;//id

    public $status='empty';//ok, multi, empty

    /**
     * @return Kassa[]
     */
    public function getAllKassaItems(){
        $rez=array();
        $rez[]=$this->nal_cheque;
        $rez[]=$this->nal_no_cheque;
        $rez[]=$this->card;
        $rez[]=$this->bank;

        return $rez;
    }

    /**
     * @return array Kassa[]
     */
    public function getMultiKassaItems(){
        $k_types_list=array('nal_cheque','nal_no_cheque','card','bank');
        $rez=array();
        foreach ($k_types_list as $k_type) {
            if (is_array($this->$k_type)){
                $rez[]=$this->$k_type;
            }
        }
        if (count($rez)>0) {
            return $rez;
        }
        else{
            return false;
        }
    }



    /**
     * @return bool
     */
    public function sessionRegister(){
        $_SESSION['kassaset']=serialize($this);
        return true;
    }

    /**
     * @return KassaSet|bool
     */
    public static function getFromSession(){
        if (isset($_SESSION['kassaset'])) {

            $ks=unserialize($_SESSION['kassaset']);
            return $ks;
        }
        else return false;

    }

    /**
     * @return KassaSet|bool
     */
    public static function getKassaSetForOfficeLE($office_type, $office_num, $le_id){
        $ks=new self();

        //k1 ->nal_cheque
        $kassa = Kassa::getKassasForChannel($office_type,$office_num,$le_id,'nal_cheque');
            if (count($kassa)==1){
                $ks->nal_cheque = $kassa[0];
            }
            elseif (count($kassa)>1){
                $ks->nal_cheque = $kassa;
            }
        unset($kassa);

        //k2 ->nal_no_cheque   - only one kassa for all LEs at 1 office
        $kassa = Kassa::getKassasForChannel($office_type,$office_num,'all','nal_no_cheque');
            $ks->nal_no_cheque = clone $kassa[0];
        unset($kassa);

        //card
        $kassa = Kassa::getKassasForChannel($office_type,$office_num,$le_id,'card');
            if (count($kassa)==1){
                $ks->card = $kassa[0];
            }
            elseif (count($kassa)>1){
                $ks->card = $kassa;
            }
        unset($kassa);

        //bank
        $kassa = Kassa::getKassasForChannel($office_type,$office_num,$le_id,'bank');
        if (count($kassa)==1){
            $ks->bank = $kassa[0];
        }
        elseif (count($kassa)>1){
            $ks->bank = $kassa;
        }
        unset($kassa);

        return $ks;
    }

    /**
     * @param $channel
     * @param $channel_num
     * @return array|null
     */
    public static function getLEsForOffice($channel, $channel_num){
        //Исходим из того, что если ЮЛ "обитает" на офисе, то будет хотя бы одна касса (оффиц, карта) привязана к этому офису
        $mysqli=Db::getInstance()->getConnection();
        $query="SELECT DISTINCT(`id_le`) FROM kassa_list WHERE channel='$channel' AND channel_num='$channel_num'";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);
        if ($result->num_rows<1) {
            return null;
        }
        else{
            $rez=array();
            while ($line=$result->fetch_assoc()){
                //echo '---<br>';
                //Base::varDamp($line);
                $rez[]=$line['id_le'];
            }
            return $rez;
        }
    }

}