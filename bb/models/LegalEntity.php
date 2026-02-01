<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 29.05.2019
 * Time: 21:39
 */

namespace bb\models;


use bb\Db;

class LegalEntity
{
    public $id_le;
    public $full_name;
    public $short_name;
    public $address_legal;
    public $unp;
    public $iban;
    public $bank_name;
    public $bank_bic;
    public $site;
    public $mail;

    public static function getLeByOffice($type, $num) {

    }

    /**
     * @return bool
     */
    public function sessionRegister(){
        foreach ($this as $key=>$value) {
            $_SESSION['le'][$key]=$value;
        }
        return true;
    }

    /**
     * @return LegalEntity|bool
     */
    public static function getFromSession(){
        $le=new self();
        if (isset($_SESSION['le']) && is_array($_SESSION['le'])) {
            foreach ($_SESSION['le'] as $key=>$value) {
                $le->$key=$value;
            }
            return $le;
        }
        else{
            return false;
        }
    }

    /**
     * @return LegalEntity|bool
     */
    public static function isLeChosen(){
        return self::getFromSession();
    }

    /**
     * @param $id
     */
    public static function getLeById($id){
        $mysqli=Db::getInstance()->getConnection();

        $query = "SELECT * FROM legalentity WHERE id_le='$id'";
        $result = $mysqli->query($query);
        if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);

        $line = $result->fetch_assoc();

        return self::getFromDbArray($line);


    }


    /**
     * @param $ar
     * @return LegalEntity
     */
    public static function getFromDbArray($ar){
        $le=new self();
        foreach ($ar as $key=>$value) {
            $le->$key=$value;
        }

        return $le;
    }

    public function getShortName(){
        return $this->short_name;
    }

}