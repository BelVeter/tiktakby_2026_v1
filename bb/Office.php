<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 27.09.2018
 * Time: 21:55
 */

namespace bb;


class Office
{
    /**
     * @var Office[]
     */
    private static $_offices;
    public $number;
    public $name;

    public function SessionRegister(){
        $_SESSION['office']=$this->number;
    }

    public static function SessionRegisterByNumber($number){
        if ($number<1) die('No office number provided.');
        $_SESSION['office']=$number;
    }

    public static function LoadOfiiceByIp(){
        $ip=$_SERVER['REMOTE_ADDR'];

        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        $query="SELECT * FROM offices WHERE off_ip='$ip'";
        $result = $mysqli->query($query);
        if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);
        if ($result->num_rows==1) {
            $row = $result->fetch_assoc();
            $office = self::LoadFromArray($row);
            return $office;
        }
        elseif ($result->num_rows>1) echo 'Office error. Multiple allowed ip found. Dont know what to do';
        else return null;
    }

    public static function GetOffices(){
        if(!self::$_offices) {
            $db = Db::getInstance();
            $mysqli = $db->getConnection();

            $sql_query="SELECT `number`, `name` FROM `offices` WHERE `active`=1 AND `type`='office'";
            $result = $mysqli->query($sql_query);

            while ($row = $result->fetch_assoc()) {
                $line=self::LoadFromArray($row);
                self::$_offices[$line->number]=$line;
            }

        }
        return self::$_offices;
    }

    public static function GetOfficeName($id) {
        self::GetOffices();

        if ($id<1) return '';

        return self::$_offices[$id]->name;
    }

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

    public static function OptionsList($selected_place, User $user=null) {
        self::GetOffices();
        $output="";
        if ($user!=null && $user->level>=5) $output.='<option value="all" '.self::sel_d($selected_place, 'all').'>все</option>';
        foreach (self::$_offices as $office) {
            $output.='<option value="'.$office->number.'" '.self::sel_d($selected_place, $office->number).'>'.$office->name.'</option>';
        }

        return $output;
    }

    public static function sel_d($value, $pattern) {
        if ($value==$pattern) {
            return 'selected="selected"';
        }
        else {
            return '';
        }
    }


}