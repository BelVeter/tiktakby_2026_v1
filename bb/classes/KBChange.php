<?php


namespace bb\classes;


use bb\Db;
use bb\models\User;

class KBChange
{
    public $id;
    public $kb_id;
    /**
     * @var \DateTime
     */
    public $from_old;
    /**
     * @var \DateTime
     */
    public $to_old;
    /**
     * @var \DateTime
     */
    public $from_new;
    /**
     * @var \DateTime
     */
    public $to_new;
    /**
     * @var \DateTime
     */
    public $ch_time;
    public $ch_who_id;
    public $appr_who_old_id;

    public function save(){
        $mysqli = Db::getInstance()->getConnection();
        $q="INSERT INTO kb_change SET kb_id='$this->kb_id', from_old='".$this->from_old->getTimestamp()."', to_old='".$this->to_old->getTimestamp()."', from_new='".$this->to_new->getTimestamp()."', to_new='".$this->to_new->getTimestamp()."', ch_time='".time()."', ch_who_id='".User::getCurrentUser()->id_user."', appr_who_old_id='$this->appr_who_old_id'";
        $result = $mysqli->query($q);
        if (!$result) {die('Сбой при доступе к базе данных: '.$q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
        $this->id=$mysqli->insert_id;
    }

    /**
     * @param $kb_id
     * @return KBChange[]|null
     */
    public static function getAllChanges($kb_id) {
        $mysqli=Db::getInstance()->getConnection();

        $query="SELECT * FROM kb_change WHERE kb_id='$kb_id' ORDER BY id DESC";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        if ($result->num_rows<1) {
            return null;
        }
        else {
            /**
             * @var KBChange[]
             */
            $rez = array();

            while ($row = $result->fetch_assoc()) {
                $rez[]=self::getFromDbArray($row);
            }

            return $rez;
        }

    }

    public static function getFromDbArray($db){
        $ch=new self();

        $ch->id=$db['id'];
        $ch->kb_id=$db['kb_id'];
        $ch->from_old=new \DateTime();
            $ch->from_old->setTimestamp($db['from_old']);
        $ch->to_old = new \DateTime();
            $ch->to_old->setTimestamp($db['to_old']);
        $ch->from_new=new \DateTime();
            $ch->from_new->setTimestamp($db['from_new']);
        $ch->to_new = new \DateTime();
            $ch->to_new->setTimestamp($db['to_new']);
        $ch->ch_time = new \DateTime();
            $ch->ch_time->setTimestamp($db['ch_time']);
        $ch->ch_who_id=$db['ch_who_id'];
        $ch->appr_who_old_id=$db['appr_who_old_id'];

        return$ch;
    }

}