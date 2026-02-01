<?php

namespace bb\classes;

use bb\Db;
use phpDocumentor\Reflection\Types\False_;

class Razdel
{
    private $id_razdel;
    private $name_razdel_text;
    private $name_razdel_text_en;
    private $name_razdel_text_lt;
    private $url_razdel_name;
    private $url_icon_razdel;
    private $url_icon2_razdel;
    private $razdel_order_num;
    private $razdel_change_time;

    private $_current_lang;

    /**
     * @var Razdel[]
     */
    private static $_razdelsStorage;

    /**
     * @var SubRazdel[]
     */
    private $subRazdels;

    public function __construct($lang='ru')
    {
        $this->_current_lang=$lang;
    }


    /**
     * @return bool|SubRazdel[]
     */
    public function getSubRazdels($currentSubRazdelUrlCode='')
    {
        $tmpSubRazdels = $this->subRazdels;

        if(!is_array($this->subRazdels)) return false;
        elseif (count($this->subRazdels)<1) return false;
        if($currentSubRazdelUrlCode!== '') {
            usort($tmpSubRazdels, function ($a, $b) use ($currentSubRazdelUrlCode) {
                if ($a->getUrlSubRazdelName() == $currentSubRazdelUrlCode) return -1;
                elseif ($b->getUrlSubRazdelName() == $currentSubRazdelUrlCode) return 1;
                else return 0;
            });
        }
        //$tmpSubRazdels[0]->getUrlSubRazdelName()
        return $tmpSubRazdels;
    }

    /**
     * @param SubRazdel[] $subRazdels
     */
    public function setSubRazdels(array $subRazdels): void
    {
        $this->subRazdels = $subRazdels;
    }

    /**
     * @return mixed
     */
    public function getIdRazdel()
    {
        return $this->id_razdel;
    }

    /**
     * @param mixed $id_razdel
     */
    public function setIdRazdel($id_razdel): void
    {
        $this->id_razdel = $id_razdel;
    }

    /**
     * @return mixed
     */
    public function getNameRazdelText($lang='', $strict=0)
    {
        //echo $lang.'---'.$strict;

        if ($lang=='') $lang = $this->_current_lang;

        if ($strict==0){
            if ($lang=='en' && $this->name_razdel_text_en != '') return $this->name_razdel_text_en;
            elseif ($lang=='lt' && $this->name_razdel_text_lt != '') return $this->name_razdel_text_lt;
            else return $this->name_razdel_text;
        }
        else{
            if ($lang=='en') return $this->name_razdel_text_en;
            elseif ($lang=='lt') return $this->name_razdel_text_lt;
            else return $this->name_razdel_text;
        }


    }

    /**
     * @param mixed $name_razdel_text
     */
    public function setNameRazdelText($name_razdel_text, $lang='ru'): void
    {
        switch ($lang){
            case 'ru':
                $this->name_razdel_text = $name_razdel_text;
                break;
            case 'en':
                $this->name_razdel_text_en = $name_razdel_text;
                break;
            case 'lt':
                $this->name_razdel_text_lt = $name_razdel_text;
                break;
        }
    }

    /**
     * @return mixed
     */
    public function getUrlRazdelName()
    {
        return $this->url_razdel_name;
    }

    /**
     * @param mixed $url_razdel_name
     */
    public function setUrlRazdelName($url_razdel_name): void
    {
        $this->url_razdel_name = $url_razdel_name;
    }

    /**
     * @return mixed
     */
    public function getUrlIconRazdel()
    {
        return $this->url_icon_razdel;
    }

    /**
     * @param mixed $url_icon_razdel
     */
    public function setUrlIconRazdel($url_icon_razdel): void
    {
        $this->url_icon_razdel = $url_icon_razdel;
    }

    /**
     * @return mixed
     */
    public function getUrlIcon2Razdel()
    {
        return $this->url_icon2_razdel;
    }

    /**
     * @param mixed $url_icon2_razdel
     */
    public function setUrlIcon2Razdel($url_icon2_razdel): void
    {
        $this->url_icon2_razdel = $url_icon2_razdel;
    }

    /**
     * @return mixed
     */
    public function getRazdelOrderNum()
    {
        return $this->razdel_order_num;
    }

    /**
     * @param mixed $razdel_order_num
     */
    public function setRazdelOrderNum($razdel_order_num): void
    {
        $this->razdel_order_num = $razdel_order_num;
    }

    /**
     * @return mixed
     */
    public function getRazdelChangeTime()
    {
        return $this->razdel_change_time;
    }

    /**
     * @param mixed $razdel_change_time
     */
    public function setRazdelChangeTime($razdel_change_time): void
    {
        $this->razdel_change_time = $razdel_change_time;
    }


    /**
     * @return bool|void
     */
    public function save(){
        if ($this->getIdRazdel()>0) $this->update();
        else {
            $mysqli = Db::getInstance()->getConnection();

            $query = "INSERT INTO razdel SET id_razdel='$this->id_razdel', name_razdel_text='$this->name_razdel_text', name_razdel_text_en='$this->name_razdel_text_en', name_razdel_text_lt='$this->name_razdel_text_lt', url_razdel_name='$this->url_razdel_name', url_icon_razdel='$this->url_icon_razdel', url_icon2_razdel='$this->url_icon2_razdel', razdel_order_num='$this->razdel_order_num'";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
            $this->setIdRazdel($mysqli->insert_id);
        }
        return true;
    }

    /**
     * @param $catId
     * @return array|false|void
     */
    public static function getRazdelUrlNamesForCatId($catId){
        $mysqli = Db::getInstance()->getConnection();

        $query ="SELECT razdel.url_razdel_name
                    FROM razdel
                    LEFT JOIN razdel_subrazdel ON razdel.id_razdel = razdel_subrazdel.id_razdel
                    LEFT JOIN subrazdel_category ON razdel_subrazdel.id_sub_razdel = subrazdel_category.id_sub_razdel
                    WHERE subrazdel_category.tovar_rent_cat_id = $catId
        ";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}
        if ($result->num_rows<1) return false;
        else{
            $rez = [];
            while ($row = $result->fetch_assoc()){
                $rez[]=$row['url_razdel_name'];
            }

            return $rez;
        }
    }

    /**
     * @return bool|void
     */
    public function isChanged(){
        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT id_razdel FROM razdel WHERE id_razdel='$this->id_razdel' AND razdel_change_time='$this->chzdel_change_time'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}

        if ($result->num_rows>0) return false;
        else return true;
    }

    /**
     * @param $safeUpdate
     * @return bool|void
     */
    public function update($safeUpdate=0){
        $mysqli = Db::getInstance()->getConnection();

        $query = "UPDATE razdel SET name_razdel_text='$this->name_razdel_text', name_razdel_text_en='$this->name_razdel_text_en', name_razdel_text_lt='$this->name_razdel_text_lt', url_razdel_name='$this->url_razdel_name', url_icon_razdel='$this->url_icon_razdel', url_icon2_razdel='$this->url_icon2_razdel', razdel_order_num='$this->razdel_order_num'
                    WHERE id_razdel='$this->id_razdel'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}
        return true;
    }

    /**
     * @return void
     */
    public function delete(){
        if ($this->checkForSubRazdelBind()) echo 'Невозможно удалить раздел '.$this->getNameRazdelText().', т.к. есть привязанные подразделы. Сначала отвяжите субразделы.';
        else self::deleteById($this->getIdRazdel());
    }

    /**
     * @return bool|void
     */
    public function checkForSubRazdelBind(){
        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT * FROM razdel_subrazdel WHERE id_razdel='$this->id_razdel'";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}
        if ($result->num_rows>0) return true;
        else return false;
    }

    /**
     * @param $id
     * @return Razdel|false|void
     */
    public static function getById($id) {
        if ($id<1) return false;

        $mysqli = Db::getInstance()->getConnection();

        $query="SELECT * FROM razdel WHERE id_razdel='$id'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}

        if ($result->num_rows<1) return false;
        else return self::createFromDbArray($result->fetch_assoc());
    }

    /**
     * @param $urlName
     * @return Razdel|false|void
     */
    public static function getByUrlName($urlName, $lang='') {
      if ($lang=='') $lang = 'ru';

        $mysqli = Db::getInstance()->getConnection();

        $query="SELECT * FROM razdel WHERE url_razdel_name='$urlName'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}

        if ($result->num_rows<1) return false;
        else return self::createFromDbArray($result->fetch_assoc(), $lang);
    }

  /**
   * @return bool
   */
  public function isKarnaval(){
      if ($this->getIdRazdel()==6) return true;
      else return false;
    }

    /**
     * @return Razdel[]|false|void
     */
    public static function getAll($lang='ru'){
        $rez = [];
        $mysqli = Db::getInstance()->getConnection();

        $query="SELECT * FROM razdel ORDER BY razdel_order_num, id_razdel";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}

        if ($result->num_rows<1) return false;
        else {
            while ($row = $result->fetch_assoc()) {
                $newR = self::createFromDbArray($row, $lang);
                $rez [$newR->getIdRazdel()]= $newR;
            }
        }
        return $rez;
    }

    /**
     * @param $id
     * @return bool|void
     */
    public static function deleteById($id){
        $mysqli = Db::getInstance()->getConnection();

        $query="DELETE FROM razdel WHERE id_razdel='$id'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}

        return true;
    }

    /**
     * @param $razdel_id
     * @param $sub_razdel_id
     * @return bool|void
     */
    public static function bindSubRazdelToRazdel($razdel_id, $sub_razdel_id){
        $mysqli = Db::getInstance()->getConnection();

        $query = "INSERT INTO razdel_subrazdel SET id_razdel='$razdel_id', id_sub_razdel='$sub_razdel_id'";
//        echo $query.'<br>';
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}

        return true;
    }

    /**
     * @param $razdel_id
     * @param $sub_razdel_ids
     * @return bool
     */
    public static function bindSubRazdelsToRazdel ($razdel_id, $sub_razdel_ids){
        if(!is_array($sub_razdel_ids)) return false;
        foreach ($sub_razdel_ids as $sub_id) {
            self::bindSubRazdelsToRazdel($razdel_id, $sub_id);
        }
        return true;
    }

    /**
     * @param $subRazdelId
     * @return bool|void
     */
    public static function clearSubRazdelToRazdelsBinding($subRazdelId){
        $mysqli = Db::getInstance()->getConnection();

        $query = "DELETE FROM razdel_subrazdel WHERE id_sub_razdel='$subRazdelId'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}

        return true;
    }

    /**
     * @param $row
     * @return Razdel
     */
    public static function createFromDbArray($row, $lang='ru'){
        $r = new self($lang);

        $r->setIdRazdel($row['id_razdel']);
        $r->setNameRazdelText($row['name_razdel_text'], 'ru');
            $r->setNameRazdelText($row['name_razdel_text_en'], 'en');
            $r->setNameRazdelText($row['name_razdel_text_lt'], 'lt');
        $r->setUrlRazdelName($row['url_razdel_name']);
        $r->setUrlIconRazdel($row['url_icon_razdel']);
        $r->setUrlIcon2Razdel($row['url_icon2_razdel']);
        $r->setRazdelOrderNum($row['razdel_order_num']);
        $r->setRazdelChangeTime($row['razdel_change_time']);

        return $r;
    }

    public function addSubRazdel(SubRazdel $sr){
        $this->subRazdels[$sr->getIdSubRazdel()] = $sr;
    }

    /**
     * @param $lang
     * @return string
     */
    public function getUrlForPage($lang=''){
        if (!$lang) $lang=$this->_current_lang;
        if (!$lang) $lang='ru';
        return '/'.$lang.'/'.$this->getUrlRazdelName();
    }

}
