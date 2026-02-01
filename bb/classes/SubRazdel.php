<?php

namespace bb\classes;

use bb\Base;
use bb\Db;
use PhpParser\Node\Stmt\If_;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;

class SubRazdel
{
    private $id_sub_razdel;
    private $main_razdel_id;
    private $name_sub_razdel_text;
      private $name_sub_razdel_text_en;
      private $name_sub_razdel_text_lt;
    private $url_sub_razdel_name;
    private $url_sub_razdel_icon;
    private $order_num_sub_razd;
    private $sub_razdel_change_time;

    private $_current_lang;

    /**
     * @param $catId
     * @return array|false|void
     */
    public static function getRazdelUrlNamesForSubUrlCode($urlCode){
        $mysqli = Db::getInstance()->getConnection();

        $query ="SELECT razdel.url_razdel_name
                    FROM razdel
                    LEFT JOIN razdel_subrazdel ON razdel.id_razdel = razdel_subrazdel.id_razdel
                    LEFT JOIN sub_razdel ON sub_razdel.id_sub_razdel = razdel_subrazdel.id_sub_razdel
                    WHERE sub_razdel.url_sub_razdel_name = '$urlCode'
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
   * @param $razdelId
   * @return SubRazdel[]|false|void
   */
  public static function getSubrazdelsForRazdelId($razdelId){
      $mysqli = Db::getInstance()->getConnection();
      $query = "SELECT * FROM sub_razdel WHERE main_razdel_id='$razdelId'";
      $result = $mysqli->query($query);
      if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}
      if ($result->num_rows<1) return false;
      else{
        $rez = [];
        while ($row = $result->fetch_assoc()){
          $rez[]=self::createFromDbArray($row);
        }
        return $rez;
      }
    }

    /**
     * @var []
     */
    private $_razdelIds;

    /**
     * @var []
     */
    private $_categoryIds;

    /**
     * @var Category[]
     */
    private $categories;

    /**
     * @var SubRazdel[]
     */
    private static $_subRazdelCash;


    /**
     * @return void
     */
    public function sortCategoriesBySortNumber(){
        usort($this->categories, function ($a, $b) {
            return $a->getCatSort() - $b->getCatSort();
        });
    }

    /**
     * @param $sr
     * @return bool
     */
    private static function addSubRazdelToCash($sr) {
        if (!is_array(self::$_subRazdelCash)) self::$_subRazdelCash = [];
        self::$_subRazdelCash[]=$sr;
        return true;
    }


    /**
     * @param $sRId
     * @return SubRazdel|false|mixed
     */
    private static function getSubRazdelFromCashById($sRId){
        $rez = [];

        if (is_array(self::$_subRazdelCash)) {
            $rez=array_filter(self::$_subRazdelCash, function ($el) use ($sRId) {
                return $el->getIdSubRazdel()==$sRId;
            });
        }
        if (count($rez) < 1) {
            return false;
        }
        else {
            return array_pop($rez);
        }
    }

    /**
     * @param $urlName
     * @return SubRazdel|false|mixed
     */
    private static function getSubRazdelFromCashByUrlName($urlName){
        $rez = [];

        if (is_array(self::$_subRazdelCash)) {
            $rez=array_filter(self::$_subRazdelCash, function ($el) use ($urlName) {
                return $el->getUrlSubRazdelName()==$urlName;
            });
        }
        if (count($rez) < 1) {
            return false;
        }
        else {
            return array_pop($rez);
        }
    }

    /**
     * @return mixed
     */
    public function getMainRazdelId()
    {
        return $this->main_razdel_id;
    }

    /**
     * @param mixed $main_razdel_id
     */
    public function setMainRazdelId($main_razdel_id): void
    {
        $this->main_razdel_id = $main_razdel_id;
    }

    /**
     * @return Category[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @param Category[] $categories
     */
    public function setCategories(array $categories): void
    {
        $this->categories = $categories;
    }

    /**
     * @return mixed
     */
    public function getIdSubRazdel()
    {
        return $this->id_sub_razdel;
    }

    /**
     * @param mixed $id_sub_razdel
     */
    public function setIdSubRazdel($id_sub_razdel): void
    {
        $this->id_sub_razdel = $id_sub_razdel;
    }

    /**
     * @return mixed
     */
    public function getNameSubRazdelText($lang='', $strict=0)
    {
      if ($lang=='') $lang = $this->_current_lang;

      if ($strict==0){
        if ($lang=='en' && $this->name_sub_razdel_text_en != '') return $this->name_sub_razdel_text_en;
        elseif ($lang=='lt' && $this->name_sub_razdel_text_lt != '') return $this->name_sub_razdel_text_lt;
        else return $this->name_sub_razdel_text;
      }
      else{
        if ($lang=='en') return $this->name_sub_razdel_text_en;
        elseif ($lang=='lt') return $this->name_sub_razdel_text_lt;
        else return $this->name_sub_razdel_text;
      }
    }

    /**
     * @param mixed $name_sub_razdel_text
     */
    public function setNameSubRazdelText($name_sub_razdel_text, $lang='ru'): void
    {
      switch ($lang){
        case 'ru':
          $this->name_sub_razdel_text = $name_sub_razdel_text;
          break;
        case 'en':
          $this->name_sub_razdel_text_en = $name_sub_razdel_text;
          break;
        case 'lt':
          $this->name_sub_razdel_text_lt = $name_sub_razdel_text;
          break;
      }

    }

    /**
     * @return mixed
     */
    public function getUrlSubRazdelName()
    {
        return $this->url_sub_razdel_name;
    }

    /**
     * @param mixed $url_sub_razdel_name
     */
    public function setUrlSubRazdelName($url_sub_razdel_name): void
    {
        $this->url_sub_razdel_name = $url_sub_razdel_name;
    }

    /**
     * @return mixed
     */
    public function getUrlSubRazdelIcon()
    {
        return $this->url_sub_razdel_icon;
    }

    /**
     * @param mixed $url_sub_razdel_icon
     */
    public function setUrlSubRazdelIcon($url_sub_razdel_icon): void
    {
        $this->url_sub_razdel_icon = $url_sub_razdel_icon;
    }

    /**
     * @return mixed
     */
    public function getOrderNumSubRazd()
    {
        return $this->order_num_sub_razd;
    }

    /**
     * @param mixed $order_num_sub_razd
     */
    public function setOrderNumSubRazd($order_num_sub_razd): void
    {
        $this->order_num_sub_razd = $order_num_sub_razd;
    }

    /**
     * @return mixed
     */
    public function getSubRazdelChangeTime()
    {
        return $this->sub_razdel_change_time;
    }

    /**
     * @param mixed $sub_razdel_change_time
     */
    public function setSubRazdelChangeTime($sub_razdel_change_time): void
    {
        $this->sub_razdel_change_time = $sub_razdel_change_time;
    }


    /**
     * @return bool|void
     */
    public function save(){
        if ($this->getIdSubRazdel()>0) $this->update();
        else {
            $mysqli = Db::getInstance()->getConnection();

            $query = "INSERT INTO sub_razdel SET main_razdel_id='$this->main_razdel_id', name_sub_razdel_text='".addslashes($this->name_sub_razdel_text)."', name_sub_razdel_text_en='".addslashes($this->name_sub_razdel_text_en)."', name_sub_razdel_text_lt='".addslashes($this->name_sub_razdel_text_lt)."', url_sub_razdel_name='$this->url_sub_razdel_name', url_sub_razdel_icon='$this->url_sub_razdel_icon', order_num_sub_razd='$this->order_num_sub_razd'";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
            $this->setIdSubRazdel($mysqli->insert_id);

            //binding to Razdels
            if (count($this->_razdelIds)>0) {
                foreach ($this->_razdelIds as $id) {
                    Razdel::bindSubRazdelToRazdel($id, $this->getIdSubRazdel());
                }
            }

            //binding of categories
            if (count($this->_categoryIds)>0) {
                self::bindCategoriesToSubRazdel($this->_categoryIds, $this->getIdSubRazdel());
            }

        }
        return true;
    }

    /**
     * @return bool|void
     */
    public function update(){
        $mysqli = Db::getInstance()->getConnection();

        $query = "UPDATE sub_razdel SET name_sub_razdel_text='$this->name_sub_razdel_text', main_razdel_id='$this->main_razdel_id', name_sub_razdel_text='".addslashes($this->name_sub_razdel_text)."', name_sub_razdel_text_en='".addslashes($this->name_sub_razdel_text_en)."', name_sub_razdel_text_lt='".addslashes($this->name_sub_razdel_text_lt)."', url_sub_razdel_name='$this->url_sub_razdel_name', url_sub_razdel_icon='$this->url_sub_razdel_icon', order_num_sub_razd='$this->order_num_sub_razd'
                    WHERE id_sub_razdel='$this->id_sub_razdel'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }

        //binding to Razdels

        Razdel::clearSubRazdelToRazdelsBinding($this->getIdSubRazdel());

        if (count($this->_razdelIds)>0) {
            foreach ($this->_razdelIds as $id) {
                Razdel::bindSubRazdelToRazdel($id, $this->getIdSubRazdel());
            }
        }

        //binding of categories
        self::clearBindingCategoriesToSubRazdel($this->getIdSubRazdel());

        if (count($this->_categoryIds)>0) {
            self::bindCategoriesToSubRazdel($this->_categoryIds, $this->getIdSubRazdel());
        }

        return true;
    }

    /**
     * @return bool
     */
    public function delete(){
        self::deleteById($this->getIdSubRazdel());
        self::clearBindingCategoriesToSubRazdel($this->getIdSubRazdel());
        Razdel::clearSubRazdelToRazdelsBinding($this->getIdSubRazdel());
        return true;
    }

    /**
     * @param $id
     * @return bool|void
     */
    public static function deleteById($id){
        $mysqli = Db::getInstance()->getConnection();

        $query = "DELETE FROM sub_razdel WHERE id_sub_razdel='$id'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        return true;
    }

    /**
     * @param $id
     * @return SubRazdel|false|void
     */
    public static function getById($id){
        if ($id<1) return false;

        if ($rez=self::getSubRazdelFromCashById($id)) {
            return $rez;
        }
        else {
            $mysqli=Db::getInstance()->getConnection();

            $query = "SELECT * FROM sub_razdel WHERE id_sub_razdel='$id'";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
            if ($result->num_rows>0) {
                $rez = self::createFromDbArray($result->fetch_assoc());
                self::addSubRazdelToCash($rez);
                return $rez;
            }
            else return false;
        }
    }

    /**
     * @param $urlName
     * @return SubRazdel|false|void
     */
    public static function getByUrlName($urlName, $lang=''){
      if ($lang=='') $lang='ru';

        if ($rez=self::getSubRazdelFromCashByUrlName($urlName)) {
            return $rez;
        }
        else {
            $mysqli=Db::getInstance()->getConnection();

            $query = "SELECT * FROM sub_razdel WHERE url_sub_razdel_name='$urlName'";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
            if ($result->num_rows>0) {
                $rez = self::createFromDbArray($result->fetch_assoc(), $lang);
                self::addSubRazdelToCash($rez);
                return $rez;
            }
            else return false;
        }


    }

    /**
     * @return SubRazdel[]|false|void
     */
    public static function getAll($lang='', $razdelIdForFilter=0, $subRazdelIdForFilter=0){
      if ($lang=='') $lang='ru';

        $mysqli=Db::getInstance()->getConnection();
        $rez=[];
        $srch_close='';

        if ($subRazdelIdForFilter>0) {
            $srch_close = " WHERE id_sub_razdel='$subRazdelIdForFilter'";
        }

        $query = "SELECT * FROM sub_razdel$srch_close ORDER BY order_num_sub_razd, id_sub_razdel";
            if ($razdelIdForFilter > 0) {
                $query="SELECT sub_razdel.*, razdel_subrazdel.id_razdel FROM `sub_razdel`
                            LEFT JOIN razdel_subrazdel ON sub_razdel.id_sub_razdel=razdel_subrazdel.id_sub_razdel
                            WHERE razdel_subrazdel.id_razdel=$razdelIdForFilter
                            ORDER BY `sub_razdel`.`order_num_sub_razd`, `sub_razdel`.`id_sub_razdel`";
            }
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        if ($result->num_rows>0) {
            while ($row=$result->fetch_assoc()) {
                $newSR = self::createFromDbArray($row, $lang);
                $rez[$newSR->getIdSubRazdel()]= $newSR;
            }
            //usort($rez, function ($a, $b) {return strcmp($a->getNameSubRazdelText(), $b->getNameSubRazdelText());});
            return $rez;
        }
        else return false;
    }

    /**
     * @param $row
     * @return SubRazdel
     */
    public static function createFromDbArray($row, $lang=''){
      if ($lang=='') $lang='ru';
        $sr = new self($lang);

        if (key_exists('id_sub_razdel', $row)) $sr->setIdSubRazdel($row['id_sub_razdel']);
        if (key_exists('main_razdel_id', $row)) $sr->setMainRazdelId($row['main_razdel_id']);
        if (key_exists('name_sub_razdel_text', $row)) $sr->setNameSubRazdelText($row['name_sub_razdel_text']);
          if (key_exists('name_sub_razdel_text_en', $row)) $sr->setNameSubRazdelText($row['name_sub_razdel_text_en'], 'en');
          if (key_exists('name_sub_razdel_text_lt', $row)) $sr->setNameSubRazdelText($row['name_sub_razdel_text_lt'], 'lt');
        if (key_exists('url_sub_razdel_name', $row)) $sr->setUrlSubRazdelName($row['url_sub_razdel_name']);
        if (key_exists('url_sub_razdel_icon', $row)) $sr->setUrlSubRazdelIcon($row['url_sub_razdel_icon']);
        if (key_exists('order_num_sub_razd', $row)) $sr->setOrderNumSubRazd($row['order_num_sub_razd']);
        if (key_exists('sub_razdel_change_time', $row)) $sr->setSubRazdelChangeTime($row['sub_razdel_change_time']);

        return $sr;
    }

    /**
     * @param $catIdsArray
     * @param $subRazdelId
     * @return bool|void
     */
    public static function bindCategoriesToSubRazdel($catIdsArray, $subRazdelId) {
        $mysqli = Db::getInstance()->getConnection();

        foreach ($catIdsArray as $cat_id) {
            $query = "INSERT INTO subrazdel_category SET id_sub_razdel='$subRazdelId', tovar_rent_cat_id='$cat_id'";
            $result = $mysqli->query($query);
            if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}
        }
        return true;
    }

    /**
     * @param $subRazdelId
     * @return bool|void
     */
    public static function clearBindingCategoriesToSubRazdel($subRazdelId) {
        $mysqli = Db::getInstance()->getConnection();

        $query = "DELETE FROM subrazdel_category WHERE id_sub_razdel='$subRazdelId'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}

        return true;
    }

    /**
     * @return array|void
     */
    public function getBindedRazdelIds(){
        if (count($this->_razdelIds)<1) {
            $mysqli = Db::getInstance()->getConnection();
            $query = "SELECT id_razdel FROM razdel_subrazdel WHERE id_sub_razdel='$this->id_sub_razdel'";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $this->addRazdelId($row['id_razdel']);
                }
            }
        }
        return $this->_razdelIds;
    }

    /**
     * @return array|void
     */
    public function getBindedCatIds(){
        if (count($this->_categoryIds)<1) {
            $mysqli = Db::getInstance()->getConnection();
            $query = "SELECT tovar_rent_cat_id FROM subrazdel_category WHERE id_sub_razdel='$this->id_sub_razdel'";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $this->addCategoryId($row['tovar_rent_cat_id']);
                }
            }
        }
        return $this->_categoryIds;
    }

    /**
     * @param $id
     * @return void
     */
    public function addRazdelId($id){
        //echo 'start('.$id.')';
        if($id>0 && !in_array($id*1, $this->_razdelIds)) {
            //echo 'added';
            $this->_razdelIds[] = $id*1;
        }
    }

    /**
     * @param $id
     * @return void
     */
    public function addCategoryId($id) {
        $this->_categoryIds[] = $id*1;
    }

    public function __construct($lang='')
    {
      if ($lang=='') $lang = 'ru';

        $this->_razdelIds=[];
        $this->_categoryIds=[];
        $this->categories=[];
        $this->_current_lang=$lang;
    }

    /**
     * @param Category $cat
     * @return void
     */
    public function addCategory(Category $cat){
        $this->categories[$cat->getId()] = clone $cat;
    }


    /**
     * @param $lang
     * @param $razdel_url_key
     * @return string
     */
    public function getUrlForPage($lang, $razdel_url_key=''){
      if ($lang=='') $lang='ru';
        $razdel=Razdel::getById($this->getMainRazdelId());
        if ($razdel) {
            return '/'.$lang.'/'.$razdel->getUrlRazdelName().'/'.$this->getUrlSubRazdelName();
        }
        else {
            return '/'.$lang.'/';
        }

    }
}
