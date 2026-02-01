<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 27.09.2018
 * Time: 21:06
 */

namespace bb\models;


use bb\classes\Permission;
use bb\classes\UserRole;
use bb\Db;
use phpDocumentor\Reflection\Types\True_;

class User
{

    /**
     * @var User[]
     */
    public static $_users = array();
    public $id_user;
    public $login;
    public $password;
    public $user_name;
    public $role;
    public $ip_yn;
    public $level;
    public $zp_yn;
    public $delivery;
    public $active;

    // legal info
    public $family;
    public $name;
    public $otch;
    public $main_role;
    private $color;

  /**
   * @var[]
   */
  private $permissions;

    //public $position;

    //public $document_type;
    //public $document_number;
    //public $document_date;
    /**
     * @var User
     */
    private static $current_user;

  /**
   * @return mixed
   */
  public function getColor()
  {
    return $this->color;
  }

  /**
   * @param mixed $color
   */
  public function setColor($color): void
  {
    $this->color = $color;
  }


    public static function setCurrentUser(User $user){
        self::$current_user=$user;
    }

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

    /**
     * @return mixed
     */
    public function getId(){
        return $this->id_user;
    }

    /**
     * @return bool
     */
    public static function isLoggedIn() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']==1) return true;
        else return false;
    }

    public function sessionRegister() {
        if ($this->id_user<1) die('no user provided');
        //old register
        //$_SESSION['svoi']=8941;
        $_SESSION['logged_in']=1;
        $_SESSION['login']=$this->login;
        $_SESSION['user_id']=$this->id_user;
        $_SESSION['user_fio']=$this->user_name;
        $_SESSION['level']=$this->level;


        $_SESSION['user']=array();
        foreach ($this as $key=>$value) {
            if ($key=='password'){//do not save password
                $_SESSION['user'][$key]='';
                continue;
            }
            $_SESSION['user'][$key]=$value;
        }
        return true;
    }

    public function isIpRestricted(){//is not correct
        if ($this->ip_yn==1) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * @param string $login
     * @param string $pas
     * @return User|null
     */
    public static function LogIn($login='', $pas='') {
        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        $query= "SELECT logpass_id FROM logpass WHERE log='$login' AND pass='$pas' AND `active`>0";
        //echo $query;
        $result = $mysqli->query($query);
        if ($result->num_rows<1) {
            $query = "INSERT INTO logpass_wrong VALUES(".time().", '".$login."', '$pas', '".$_SERVER['REMOTE_ADDR']."', 'wr_logpass')";
            $result = $mysqli->query($query);
            if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);

            return null;
        }
        else {
            $row = $result->fetch_assoc();
            $user = self::getUserById($row['logpass_id']);

              $lg_log = "INSERT INTO logpass_track VALUES('', $user->id_user, 'login', '".time()."', '".$_SERVER['REMOTE_ADDR']."', '')";
              $result_log = $mysqli->query($lg_log);
              if (!$result_log) {die('Сбой при доступе к базе данных: '.$lg_log.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

            //print_r($user);
            return $user;
        }

    }

    /**
     * @return bool
     */
    public function save(){
        $mysqli=Db::getInstance()->getConnection();

        if (self::isLoginExists($this->login)) {
            echo 'Ошибка: пользователь с таким именем уже существует.';
            return false;
        }

        $query="INSERT INTO logpass SET log='$this->login', pass='$this->password', lp_fio='$this->user_name', delivery='$this->delivery', `level`='$this->level', ip_yn='$this->ip_yn', zp_yn='$this->zp_yn', `active`='$this->active', ip_addr='86.57.139.9', ip_addr_2='82.209.203.36', ip_addr_3='86.57.159.29', main_role='$this->main_role', color='$this->color'";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);

        $this->id_user=$mysqli->insert_id;

        return true;
    }

    /**
     * @return bool
     */
    public function isCourier(){
        if ($this->delivery==1) return true;
        else return false;
    }

    /**
     * @return bool
     */
    public function isCourierAndRoleChoosen(){
        if ($this->isCourier() && UserRole::isRoleChosen()=='courier') return true;
        else return false;
    }

    /**
     * @return bool
     */
    public function isCoder(){
        if ($this->id_user==3){
            return true;
        }
        else{
            return false;
        }
    }

    public function update(){
        $mysqli=Db::getInstance()->getConnection();

        if ($this->password!=null) {
            //echo 'Сработал апдейт пароля';
            $pass_add=", pass='$this->password'";
        }
        else{
            //echo 'не сработал апдейт пароля';
            $pass_add='';
        }


        $query="UPDATE logpass SET log='$this->login', lp_fio='$this->user_name', delivery='$this->delivery', ip_yn='$this->ip_yn', zp_yn='$this->zp_yn', `active`='$this->active',main_role='$this->main_role', color='$this->color'$pass_add WHERE logpass_id='$this->id_user'";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);

        return true;
    }

    /**
     * @param $log
     * @return bool
     */
    public static function isLoginExists($log){
        $mysqli=Db::getInstance()->getConnection();

        $query="SELECT log FROM logpass WHERE log='$log'";
        $result = $mysqli->query($query);
        if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);
        if ($result->num_rows>0) {
            return true;
        }
        else{
            return false;
        }
    }

    /**
     * @return User|bool
     */
    public static function getUserFromSession(){
        $user=new self();
        if (!isset($_SESSION['user'])) {
            return false;
        }
        else {
            foreach ($_SESSION['user'] as $key=>$value) {
                $user->$key=$value;
            }
            return $user;
        }

    }

    /**
     * @param array $admin_ids
     * @return bool
     */
    public function isAdmin($admin_ids=array(2,3,5)){

        if (in_array(self::getCurrentUser()->id_user, $admin_ids)){
            return true;
        }
        else{
            return false;
        }
    }

    /**
     * @param $user_id
     * @param $password
     * @return bool
     */
    public static function isPasswordCorrect($user_id, $password){

        $mysqli = Db::getInstance()->getConnection();
        $query="SELECT COUNT(logpass_id) as num FROM logpass WHERE logpass_id='$user_id' AND pass='$password'";
        $result = $mysqli->query($query);
        if (!$result) printf("Mysqli Errormessage: %s\n", $mysqli->error);
        //echo $query;

        $row = $result->fetch_assoc();
        //var_dump($row);
        if ($row['num']<1){
            return false;
        }
        else{
            return true;
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

            $user->sessionRegister();

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

        if (!self::isLoggedIn()) {
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

        if (self::isLoggedIn() && (!isset($_SESSION['office']) || $_SESSION['office']<1)) {
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



    /**
     * @param array|null $close
     * @return User[]
     */
    public static function getUsers(array $close=null){

        $q_closes=array();

        if ($close!=null) {
            if (isset($close['active'])) {
                if ($close['active']==1){
                    $q_closes[]='`active`=1';
                }
                elseif ($close['active']==0){
                    $q_closes[]='`active`=0';
                }
            }
        }

        if (count($q_closes)>0) {
            $q_add=Db::makeQueryConditionFromArray($q_closes);
        }
        else{
            $q_add='';
        }

        if (!is_array(self::$_users)) {
          self::$_users=[];
        }

        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        $sql_query="SELECT * FROM `logpass` $q_add";
        $result = $mysqli->query($sql_query);

        while ($row = $result->fetch_assoc()) {
          $line=self::LoadFromArray($row);
          self::$_users[$line->id_user]=$line;
        }

        return self::$_users;
    }

    /**
     * @param $id
     * @return User|User[]
     */
    public static function getUserById($id){
      if (!is_array(self::$_users)) {
        self::$_users = [];
      }
      if (array_key_exists($id, self::$_users)) {
        if (!self::$_users[$id]->isPermissionsLoaded()) {
          self::$_users[$id]->loadPermissions();
        }
        return self::$_users[$id];
      }
      else{
        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        $sql_query="SELECT * FROM `logpass` WHERE logpass_id='$id'";
        $result = $mysqli->query($sql_query);

        $row = $result->fetch_assoc();
        $user=self::LoadFromArray($row);

        $user->loadPermissions();

        self::$_users[$user->id_user] = $user;

        return $user;
      }
    }

  /**
   * @return void
   */
  public function loadPermissions(){
      $this->permissions = Permission::getIntCodeArrayForUserId($this->id_user);
    }

  /**
   * @return bool
   */
  public function isPermissionsLoaded(){
      if (is_array($this->permissions)) return true;
      else return false;
    }

    public static function GetUserName($id) {
        self::getUsers();

        if ($id<1) return '';

        return self::$_users[$id]->user_name;
    }

    /**
     * @param $arr
     * @return User
     */
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

    public function getRoleName(){
        return UserRole::getRoleNameByText($this->main_role);
    }

  /**
   * @return bool
   */
  public function isManagement(){
      if (in_array($this->id_user, [2,3,5,22])) return true;
      else return false;
    }

    //!!! доработать
    public static function getCurrentUser() {
      try {
        $user = self::getUserById($_SESSION['user_id']);
        // $user->role='owner';
        return $user;
      }
      catch (\Exception $e){
        return false;
      }

    }

    public function isOwner() {
        if (in_array($this->id_user, array(2,3,5))) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isDima() {
        if (in_array($this->id_user, array(3))) {
            return true;
        }
        else {
            return false;
        }
    }

    public function isPersonalKassaAllowed(){
        return true;
    }

    /**
     * @param string $role
     * @return bool
     */
    public function hasRole($role) {
        if ($this->role==$role) {
            return true;
        }
        else return false;
    }
    public function hasPersonalKassa(){
        return true;
    }

  /**
   * @param $taskId
   * @return bool
   */
  public function hasPermission($taskId){
      if ($this->isOwner()) return true;
      if (in_array($taskId, $this->permissions)) return true;

      return false;
    }

    public function getShortName() {
        return $this->user_name;
    }

  /**
   * @return User[]
   */
  public static function getSalaryUsers(){
      $users = array();

      $mysqli = Db::getInstance()->getConnection();

      $sql_query="SELECT * FROM `logpass` WHERE active='1' AND zp_yn='1'";
      $result = $mysqli->query($sql_query);

      while ($row = $result->fetch_assoc()) {
        $users[]=self::LoadFromArray($row);
      }

      return $users;
    }

    /**
     * @return User[]
     */
    public static function getAdvanceUsers(){
        $users = array();

        $mysqli = Db::getInstance()->getConnection();

        $sql_query="SELECT `logpass_id`, lp_fio, `level` FROM `logpass` WHERE active<1";
        $result = $mysqli->query($sql_query);

        while ($row = $result->fetch_assoc()) {
            $users[]=self::LoadFromArray($row);
        }

        return $users;
    }

    public function __construct(){
        $this->active=1;
        $this->ip_yn=1;
    }

}
