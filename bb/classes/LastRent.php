<?php


namespace bb\classes;


use bb\Db;

class LastRent
{
    public $id;
    public $inv_n;

    private $f0_url;//main pic url
    private $f1_url;//dop pic usrl
    private $f2_url;//dop pic usrl
    private $f3_url;//dop pic usrl
    private $f4_url;//dop pic usrl
    private $f5_url;//dop pic usrl

    public $dop_info;

    public $sale_price;

    public $updated;

    /**
     * @var Model
     */
    public $model;

    public $model_web;

    /**
     * @return bool
     */
    public function save(){

        if ($this->id<1){
            $this->create();

            $mysqli=Db::getInstance()->getConnection();

            $q="UPDATE tovar_rent_items SET `state`='3' WHERE item_inv_n='$this->inv_n'";
            //echo $q;
            $result = $mysqli->query($q);
            if (!$result) {die('Сбой при вставке временной брони в MYSQL: '.$q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

            return true;
        }
        else{
            $this->update();
            //echo 'Updated -------------------';
        }

        return true;

    }

    /**
     * @return bool
     */
    public function create(){

        $mysqli=Db::getInstance()->getConnection();

        $q="INSERT INTO last_rent SET inv_n='$this->inv_n', f0_url='$this->f0_url', f1_url='$this->f1_url', f2_url='$this->f2_url', f3_url='$this->f3_url', f4_url='$this->f4_url', f5_url='$this->f5_url', dop_info='".htmlspecialchars($this->dop_info)."', sale_price='$this->sale_price', updated=".time();
        //echo $q;
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при вставке временной брони в MYSQL: '.$q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        $this->id=$mysqli->insert_id;

        return true;

    }

    public function update(){
        $mysqli=Db::getInstance()->getConnection();

        $q="UPDATE last_rent SET inv_n='$this->inv_n', f0_url='$this->f0_url', f1_url='$this->f1_url', f2_url='$this->f2_url', f3_url='$this->f3_url', f4_url='$this->f4_url', f5_url='$this->f5_url', dop_info='".htmlspecialchars($this->dop_info)."', sale_price='$this->sale_price', updated=".time()." WHERE id='$this->id'";
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при вставке временной брони в MYSQL: '.$q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        $this->id=$mysqli->insert_id;

        return true;
    }

    /**
     * @param $inv_n
     * @return LastRent
     */
    public static function getByInvN($inv_n){

        $mysqli= Db::getInstance()->getConnection();

        $lr=new self($inv_n);

        $q="SELECT * FROM last_rent WHERE inv_n='$inv_n'";
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при вставке временной брони в MYSQL: '.$q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        $rez=$result->fetch_assoc();

        $lr->id=$rez['id'];;

        $lr->f0_url=$rez['f0_url'];
        $lr->f1_url=$rez['f1_url'];
        $lr->f2_url=$rez['f2_url'];
        $lr->f3_url=$rez['f3_url'];
        $lr->f4_url=$rez['f4_url'];
        $lr->f5_url=$rez['f5_url'];

        $lr->dop_info=htmlspecialchars_decode($rez['dop_info']);
        $lr->sale_price=$rez['sale_price'];
        $lr->updated=$rez['updated'];

        return $lr;

    }

    /**
     * @param $inv_n
     * @return string
     */
    static function get3LUrl($inv_n){
        return '/prokat/last_rent/last_rent_tovar.php?inv_n='.$inv_n;
    }



    /**
     * @return int
     */
    public function getDopPhotoNum(){
        $i=0;

        if ($this->f1_url!=null) $i++;
        if ($this->f2_url!=null) $i++;
        if ($this->f3_url!=null) $i++;
        if ($this->f4_url!=null) $i++;
        if ($this->f5_url!=null) $i++;

        return $i;
    }

    /**
     * @return array|bool
     */
    public function getDopPhotoUrlArray(){
        if(self::getDopPhotoNum()>0) {
            $a = array();
            if ($this->f1_url != null) $a[] = $this->f1_url;
            if ($this->f2_url != null) $a[] = $this->f2_url;
            if ($this->f3_url != null) $a[] = $this->f3_url;
            if ($this->f4_url != null) $a[] = $this->f4_url;
            if ($this->f5_url != null) $a[] = $this->f5_url;
            return $a;
        }
        else return false;
    }

    public function getMainPhotoUrl(){
        if ($this->f0_url != null) return $this->f0_url;
        else return '';
    }

    /**
     * @param int $n
     * @param str $url
     * @return bool
     */
    public function addFileUrl($n, $url){

        if ($n>5 || $n<0) return false;//file number controll

        $property_name='f'.$n.'_url';
        $this->$property_name=$url;

        return true;
    }

    /**
     * @param $i
     * @return bool
     */
    public function delFile($i){

        if ($i>5 || $i<0) return false;//file number controll
        $property_name='f'.$i.'_url';
        $this->$property_name=null;
        return true;

    }

    public function getFileUrl($n) {
        $property_name='f'.$n.'_url';
        if(strlen($this->$property_name)>0) {
            return $this->$property_name;
        }
        else{
            return false;
        }
    }

    public function __construct($inv_n){

        $this->inv_n=$inv_n;

        $mysqli=Db::getInstance()->getConnection();

        $q="SELECT model_id FROM tovar_rent_items WHERE item_inv_n='$inv_n' LIMIT 1";
        //echo $q;
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при вставке в MYSQL: '.$q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        $row=$result->fetch_assoc();

        $model_id=$row['model_id'];

        $this->model=Model::getById($model_id);
    }


}