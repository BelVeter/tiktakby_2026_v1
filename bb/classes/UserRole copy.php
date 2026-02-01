<?php


namespace bb\classes;


use bb\Base;
use bb\Db;
use bb\models\User;

class UserRole
{
    public $id;
    public $user_id;
    public $role;//owner, courier, consultant, admin, coder, accountant


    /**
     * @param $user_id
     * @return UserRole[]
     */
    public static function getRolesForUser($user_id){
        $roles = array();

        $mysqli= Db::getInstance()->getConnection();

        $sql_query="SELECT * FROM `user_role` WHERE user_id='$user_id'";
        $result = $mysqli->query($sql_query);

        while ($row = $result->fetch_assoc()) {
            $roles[]=self::loadFromArray($row);
        }

        if (count($roles)<1) {//set default role if no roles exists
            $rl=new self();
            $rl->id=0;
            $rl->user_id=User::getCurrentUser()->id_user;
            $rl->role='consultant';
            $roles[]=$rl;
        }

        return $roles;
    }

    /**
     * @return bool
     */
    public function isCurier(){
        if ($this->role=='courier') {
            return true;
        }
        else{
            return false;
        }
    }

    /**
     * @param $role_name
     * @param $user_id
     * @return UserRole
     */
    public static function getRoleByRoleName($role_name, $user_id){
        $mysqli= Db::getInstance()->getConnection();

        $sql_query="SELECT * FROM `user_role` WHERE user_id='$user_id' AND `role`='$role_name'";
        $result = $mysqli->query($sql_query);

        $row = $result->fetch_assoc();

        return self::loadFromArray($row);
    }

    /**
     * @return UserRole|bool
     */
    public static function getCurrentRole(){
        return self::getFromSession();
    }

    /**
     * @param $row
     * @return UserRole
     */
    public static function loadFromArray($row){
        $r = new self();

        $r->id=$row['id'];
        $r->user_id=$row['user_id'];
        $r->role=$row['role'];

        return $r;
    }


    public function sessionRegister(){
        Base::varDamp($this);
        if ($this->id==null) die('no role provided');
        $_SESSION['role']=array();
        foreach ($this as $key=>$value) {
            $_SESSION['role'][$key]=$value;
        }
        return true;
    }

    public static function getFromSession(){
        $r=new self();
        if (!isset($_SESSION['role'])) {
            return false;
        }
        else {
            foreach ($_SESSION['role'] as $key=>$value) {
                $r->$key=$value;
            }
            return $r;
        }
    }

    public function getRoleTextName(){
        switch ($this->role){
            case 'owner':
                return 'Собственник';
                break;
            case 'courier':
                return 'Курьер';
                break;
            case 'consultant':
                return 'Консультант';
                break;
            case 'admin':
                return 'Администратор';
                break;
            case 'coder':
                return 'Программист';
                break;
            case 'accountant':
                return 'Бухгалтер';
                break;
            default:
                return 'не определено';
                break;
        }
    }

    public static function isRoleChosen(){
        if (isset($_SESSION['role']) && isset($_SESSION['role']['role'])) {
            return true;
        }
        else{
            return false;
        }
    }

}