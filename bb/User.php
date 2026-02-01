<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 27.09.2018
 * Time: 21:06
 */

namespace bb;


class User
{

    /**
     * @var User[]
     */
    private static $_users = array();
    public $id_user;
    public $login;
    public $level;
    public $password;
    public $user_name;
    public $ip_yn;

    // legal info
    public $family;
    public $name;
    public $otch;
    public $position;

    public $document_type;
    public $document_number;
    public $document_date;

    /**
     * @var User
     */
    public static $current_user;

    private static function GetDbName($name) {
        $names = array(
            'id_user'=>'logpass_id',
            'login'=>'log',
            'password'=>'pass',
            'user_name'=>'lp_fio',
        );

        if (isset($names[$name])) return $names[$name];
        else return $name;
    }

    public static function IsLoggedIn() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']==1) return true;
        else return false;
    }

    public function SessionRegister() {
        if ($this->id_user<1) die('no user provided');
        $_SESSION['logged_in']=1;
        $_SESSION['login']=$this->login;
        $_SESSION['user_id']=$this->id_user;
        $_SESSION['user_fio']=$this->user_name;
        $_SESSION['level']=$this->level;
    }

    public static function LogIn($login='', $pas='') {
        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        $query= "SELECT * FROM logpass WHERE log='$login' AND pass='$pas'";
        $result = $mysqli->query($query);
        if ($result->num_rows<1) {
            $query = "INSERT INTO logpass_wrong VALUES(".time().", '".$login."', '$pas', '".$_SERVER['REMOTE_ADDR']."', 'wr_logpass')";
            $result = $mysqli->query($query);
            if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);

            return null;
        }
        else {
            $row = $result->fetch_assoc();
            $user = self::LoadFromArray($row);
            //print_r($user);
            return $user;
        }

    }

    public static function Register() {
        //echo 'register_start';

        if (isset($_POST['action']) && Base::GetPost('action')=='войти') {
            //echo 'started2';
            $user = self::LogIn(Base::GetPost('login'), Base::GetPost('pass'));
            if ($user==null) {
                echo Base::PageStartHTML();
                echo 'Неверный пароль или логин.<br> <a href="index2.php">Попробовать еще раз.</a> ';
                echo Base::PageEndHTML();
                die();
            }

            $user->SessionRegister();

            // ip controll
            if ($user->ip_yn==1) {
                $office = Office::LoadOfiiceByIp();
                if ($office!=null) {
                    $office->SessionRegister();
                }
                else echo 'Проблемы с логином с ограниченных IP. Обратитесь к Кристине';
            }

        }
        elseif (isset($_POST['action']) && Base::GetPost('action')=='выбрать офис') {
            Office::SessionRegisterByNumber(Base::GetPost('office'));
        }

        if (!self::IsLoggedIn()) {
            echo Base::PageStartHTML('Авторизация');
            echo'             
            <form action="index2.php" method="post">
                Логин:<input type="text" value="" name="login" /><br />
                Пароль:<input type="password" value="" name="pass" /><br />
                <input type="submit" name="action" value="войти" onclick="return log_ch();" />
            </form></body></html>
            ';
            echo Base::PageEndHTML();
            die();
        }

        if (self::IsLoggedIn() && (!isset($_SESSION['office']) || $_SESSION['office']<1)) {
                echo Base::PageStartHTML('Выбор офиса');
                $offices=Office::GetOffices();

                echo '
                <form action="index2.php" method="post">
                <select name="office">';
                    foreach ($offices as $office) {
                        echo '<option value="'.$office->number.'">'.$office->name.'</option>';
                    }
                echo '
                </select>
                <input type="submit" name="action" value="выбрать офис" onclick="return log_ch();" />
                </form>
                
                
                ';
            }
    }


    public static function GetUsers(){
        if(!self::$_users) {
            $db = Db::getInstance();
            $mysqli = $db->getConnection();

            $sql_query="SELECT `logpass_id`, lp_fio, `level` FROM `logpass`";
            $result = $mysqli->query($sql_query);

            while ($row = $result->fetch_assoc()) {
                $line=self::LoadFromArray($row);
                self::$_users[$line->id_user]=$line;
            }


        }
        return self::$_users;
    }

    public static function GetUserName($id) {
        self::GetUsers();

        if ($id<1) return '';

        return self::$_users[$id]->user_name;
    }

    public static function LoadFromArray($arr){
        //print_r($arr);
        $result = new self();

        foreach ($result as $key => $value) {

            $db_key=self::GetDbName($key);

            if (isset($arr[$db_key])) {
                $result->$key = $arr[$db_key];
            }
        }

        //echo '<br><br>';
        //print_r($result);

        return $result;
    }

    /**
     * @return integer
     */
    public static function getCurrentId(){
        if (isset($_SESSION['user_id'])) return $_SESSION['user_id'];
        else return null;
    }






}