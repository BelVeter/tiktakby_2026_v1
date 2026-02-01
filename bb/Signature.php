<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 28.10.2018
 * Time: 20:21
 */

namespace bb;


class Signature
{
    public $id_signature;
    public $id_user;
    public $office;
    public $start_text;
    public $short_signature;



    public function StartText() {
        return $this->start_text;
    }

    public function ShortSignature() {
        return $this->short_signature;
    }

    /**
     * @param $id_user
     * @param null $id_le
     * @return Signature|bool|\mysqli_result
     */
    public static function GetSignature($id_user, $id_le=null) {

        if ($id_user<1) die('No valid user id provided. Call to Dima.');
        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        $sql_query="SELECT * FROM signature WHERE signature.id_user='$id_user' LIMIT 1";
        //echo $sql_query;
        $result = $mysqli->query($sql_query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$sql_query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $row = $result->fetch_assoc();

        $result = self::LoadFromArray($row);

        return $result;
    }

    /**
     * @param $arr
     * @return Signature
     */
    public static function LoadFromArray($arr){
        //print_r($arr);
        $result = new self();

        foreach ($result as $key => $value) {
            //echo 'key:'.$key.', ';
            if (isset($arr[$key])) {
                $result->$key = $arr[$key];
            }
        }

        //echo '<br><br>';
        //print_r($result);

        return $result;
    }


}