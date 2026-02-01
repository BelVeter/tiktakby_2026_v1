<?php


namespace bb\classes;


use bb\Db;

class DeletedTovarsPageLine
{

    /**
     * @var tovar
     */
    public $tovar;


    /**
     * @return DeletedTovarsPageLine
     */
    public static function getLineFromArray($arr) {
        $l=new self();
            $l->tovar=tovar::getArchTovarFromArray($arr);

        return $l;
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return DeletedTovarsPageLine[]
     */
    public static function getDelTovLines(\DateTime $from, \DateTime $to){
        $dtp_lines=array();

        //forming new lines
        $mysqli=Db::getInstance()->getConnection();
        $q="SELECT * FROM tovar_rent_items_arch 
            LEFT JOIN tovar_rent ON tovar_rent_items_arch.model_id=tovar_rent.tovar_rent_id
            LEFT JOIN tovar_rent_cat ON tovar_rent.tovar_rent_cat_id=tovar_rent_cat.tovar_rent_cat_id
        WHERE tovar_rent_items_arch.arch_date BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'";
        //echo $q;
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        while ($ln=$result->fetch_assoc()) {
            $dtp_lines[]=self::getLineFromArray($ln);
        }

        return $dtp_lines;
    }

    /**
     * @return \DateTime
     * @throws \Exception
     */
    public function getDelDate(){
        $d=new \DateTime();
            $d->setTimestamp($this->tovar->arch_time);
        return $d;
    }


    /**
     * @return string
     */
    public function getInvN($sep=''){
        if ($sep=='') return $this->tovar->item_inv_n;

        else {
            return $this->tovar->invNPrint($sep);
        }

    }

    /**
     * @return mixed
     */
    public function getPicAddr(){
        return $this->tovar->getPicAddress();
    }

    /**
     * @return string
     */
    public function getTovarName(){
        return $this->tovar->getFullName().' '.$this->tovar->getColor();
    }

    /**
     * @return mixed
     */
    public function getDelWhoId(){
        return $this->tovar->arch_who_id;
    }

    /**
     * @return bool
     */
    public function isDealInvolved(){
        if($this->tovar->out_status == 'sold' || $this->tovar->out_status=='no_return') {
            return true;
        }
        else{
            return false;
        }
    }

    /**
     * @return string
     */
    public function getDelTypeText(){
        switch ($this->tovar->out_status){
            case 'sold':
                return 'Продано клиенту.';
                break;
            case 'no_return':
                return 'Не вернул клиент (списано через договор).';
                break;



            default:
                return 'Причина не была указана:('.$this->tovar->out_status.')';
                break;
        }

    }



}