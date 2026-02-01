<?php


namespace bb\classes;


use bb\Db;
use bb\models\User;

class KarnStirka
{
    public $id;
    public $inv_n;
    public $info;
    /**
     * @var \DateTime
     */
    public $cr_time;
    /**
     * @var \bb\models\User
     */
    public $cr_who;
    /**
     * @var \DateTime
     */
    public $arch_time;
    /**
     * @var \bb\models\User
     */
    public $arch_who;



    public function save(){

        $mysqli = Db::getInstance()->getConnection();

        $query = "INSERT INTO karn_stirka SET inv_n='$this->inv_n', info='$this->info', cr_time='".$this->cr_time->format("Y-m-d H:i:s")."', cr_who='".$this->cr_who->id_user."'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
        $this->id=$mysqli->insert_id;
    }

    /**
     * @param $inv_n
     * @return KarnStirka|null
     * @throws \Exception
     */
    public static function getByInvN($inv_n){
        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT * FROM karn_stirka WHERE inv_n='$inv_n'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        if ($result->num_rows==1) {
            $line=$result->fetch_assoc();

            $rez = new self();
            $rez->id=$line['id'];
            $rez->inv_n=$line['inv_n'];
            $rez->info=$line['info'];
            $rez->cr_time = new \DateTime($line['cr_time']);
            $rez->cr_who = User::getUserById($line['cr_who']);

            return $rez;
        }
        elseif ($result->num_rows>1) {
            die('Ошибка: Более 1-й стирки на инвентарный номер.');
        }
        elseif ($result->num_rows<1) {
            return null;
        }

    }

    public function __construct($inv_n)
    {
        $this->inv_n=$inv_n;
        $this->cr_time= new \DateTime();
        $this->cr_who=User::getCurrentUser();
    }

}