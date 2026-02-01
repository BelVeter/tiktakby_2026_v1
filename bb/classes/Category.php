<?php


namespace bb\classes;


use App\MyClasses\Pic;
use bb\Base;
use bb\Db;
use bb\models\User;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\True_;

class Category
{
    public $id;
    private $main_sub_razdel_id;
    public $name;
      private $name_en;
      private $name_lt;
    public $dog_name;
    private $cat_url_key;
    private $cat_sort_number;
    private $cat_change_time;
    private $cat_type;
    public $cat_sort;

    private $arch_id;
    /**
     * @var \DateTime
     */
    private $arch_time;
    private $arch_who_id;

    private $_tov_num;

    private $_current_lang;

    /**
     * @var Category[]
     */
    private static $_categories;

    /**
     * @var array
     */
    private static $_karnavalKats;

  public function __construct($lang='')
  {
    if ($lang=='') $lang = 'ru';

    $this->_current_lang = $lang;
  }

  /**
     * @return mixed
     */
    public function getCatType()
    {
        return $this->cat_type;
    }

    /**
     * @param mixed $cat_type
     */
    public function setCatType($cat_type): void
    {
        $this->cat_type = $cat_type;
    }

    /**
     * @return mixed
     */
    public function getCatSort()
    {
        return $this->cat_sort;
    }

    /**
     * @param mixed $cat_sort
     */
    public function setCatSort($cat_sort)
    {
        $this->cat_sort = $cat_sort;
    }


    /**
     * @param Category $cat
     * @return void
     */
    public static function addToCach(Category $cat) {
        if(!is_array(self::$_categories)) self::$_categories = [];

        self::$_categories[$cat->getId()]=$cat;
    }


    /**
     * @param $id
     * @return Category|false
     */
    public static function getFromCachById($id){
        if (is_array(self::$_categories) && key_exists($id, self::$_categories)) return self::$_categories[$id];
        else return false;
    }

    /**
     * @param $urlName
     * @return false|mixed|null
     */
    public static function getFromCachByUrlName($urlName) {
        $rez = [];

        if (is_array(self::$_categories)) {
            $rez=array_filter(self::$_categories, function ($el) use ($urlName) {
                return $el->getCatUrlKey()==$urlName;
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
    public function getMainSubRazdelId()
    {
        return $this->main_sub_razdel_id;
    }

    /**
     * @param mixed $main_sub_razdel_id
     */
    public function setMainSubRazdelId($main_sub_razdel_id): void
    {
        $this->main_sub_razdel_id = $main_sub_razdel_id;
    }

    /**
     * @return mixed
     */
    public function getTovNum()
    {
        return $this->_tov_num;
    }

    /**
     * @param mixed $tov_num
     */
    public function setTovNum($tov_num): void
    {
        $this->_tov_num = $tov_num;
    }

    /**
     * @return mixed
     */
    public function getCatUrlKey()
    {
        return $this->cat_url_key;
    }

    /**
     * @param mixed $cat_url_key
     */
    public function setCatUrlKey($cat_url_key): void
    {
        $this->cat_url_key = $cat_url_key;
    }

    /**
     * @return mixed
     */
    public function getCatSortNumber()
    {
        return $this->cat_sort_number;
    }

    /**
     * @param mixed $cat_sort_number
     */
    public function setCatSortNumber($cat_sort_number): void
    {
        $this->cat_sort_number = $cat_sort_number;
    }

    /**
     * @return mixed
     */
    public function getCatChangeTime()
    {
        return $this->cat_change_time;
    }

    /**
     * @param mixed $cat_change_time
     */
    public function setCatChangeTime($cat_change_time): void
    {
        $this->cat_change_time = $cat_change_time;
    }


    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getName($lang='', $strict=0)
    {
      if ($lang=='') $lang = $this->_current_lang;

      if ($strict==0){
        if ($lang=='en' && $this->name_en != '') return $this->name_en;
        elseif ($lang=='lt' && $this->name_lt != '') return $this->name_lt;
        else return $this->name;
      }
      else{
        if ($lang=='en') return $this->name_en;
        elseif ($lang=='lt') return $this->name_lt;
        else return $this->name;
      }

    }

    /**
     * @param mixed $name
     */
    public function setName($name, $lang=''): void
    {
      if ($lang=='') $lang = 'ru';

      switch ($lang){
        case 'ru':
          $this->name = $name;
          break;
        case 'en':
          $this->name_en = $name;
          break;
        case 'lt':
          $this->name_lt = $name;
          break;
      }

    }

    /**
     * @return mixed
     */
    public function getDogName()
    {
        return $this->dog_name;
    }

    /**
     * @param mixed $dog_name
     */
    public function setDogName($dog_name): void
    {
        $this->dog_name = $dog_name;
    }



    /**
     * @return string
     */
    public static function getMysqlTableName() {
        return 'tovar_rent_cat';
    }

    /**
     * @return bool|void
     */
    public function save(){
        if ($this->getId()>0) $this->update();
        else {
            $mysqli = Db::getInstance()->getConnection();

            $query = "INSERT INTO tovar_rent_cat SET main_sub_razdel_id='$this->main_sub_razdel_id', rent_cat_name='".addslashes($this->name)."', rent_cat_name_en='".addslashes($this->name_en)."', rent_cat_name_lt='".addslashes($this->name_lt)."', dog_name='$this->dog_name', cat_url_key='$this->cat_url_key', cat_type='$this->cat_type', cat_sort='$this->cat_sort'";
            $result = $mysqli->query($query);
            if (!$result) {
                die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }
            $this->setId($mysqli->insert_id);
        }
        return true;
    }

    /**
     * @return bool|void
     */
    public function update(){
        $mysqli = Db::getInstance()->getConnection();

        $query = "UPDATE tovar_rent_cat SET main_sub_razdel_id='$this->main_sub_razdel_id', rent_cat_name='".addslashes($this->name)."', rent_cat_name_en='".addslashes($this->name_en)."', rent_cat_name_lt='".addslashes($this->name_lt)."', dog_name='$this->dog_name', cat_url_key='$this->cat_url_key', cat_type='$this->cat_type', cat_sort='$this->cat_sort' WHERE tovar_rent_cat_id='$this->id'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        return true;
    }

    private function delete(){
        $mysqli = Db::getInstance()->getConnection();

        $query = "DELETE FROM tovar_rent_cat WHERE tovar_rent_cat_id='$this->id'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        return true;
    }

    /**
     * @return Category|false|void
     */
    public static function getById($id){
        if ($id<1) return false;

        if ($trez=self::getFromCachById($id)) return $trez;

        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='$id'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        if ($result->num_rows<1) {
          $query = "SELECT * FROM tovar_rent_cat_arch WHERE tovar_rent_cat_id='$id'";
          $result = $mysqli->query($query);
          if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
          }
          if ($result->num_rows<1) return false;
        }

        $row = $result->fetch_assoc();

        $rez=self::createFromDbArray($row);

        self::addToCach($rez);

        return $rez;

    }

  /**
   * @return Category[]|false
   */
  public static function getByIdsArray($ids){
    if (!is_array($ids) || count($ids)<1) return false;

    //if ($trez=self::getFromCachById($id)) return $trez;

    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id IN (".join(',', $ids).")";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    if ($result->num_rows<1) return false;

    $cats=[];
    while ($row = $result->fetch_assoc()) {
      $cat=self::createFromDbArray($row);
      $cats[$cat->getId()]=$cat;
    }

    //self::addToCach($rez);

    return $cats;

  }

    /**
     * @return array|void
     */
    public static function getKarnavalCatIdsArray(){
        if (is_array(self::$_karnavalKats)) return self::$_karnavalKats;

        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT tovar_rent_cat_id FROM tovar_rent_cat WHERE cat_type=1";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        if ($result->num_rows<1) {
            self::$_karnavalKats=[];
            return [];
        }
        while ($row = $result->fetch_assoc()) {
            self::$_karnavalKats[]=$row['tovar_rent_cat_id'];
        }

        return self::$_karnavalKats;
    }

    /**
     * @param $cat_id
     * @return bool
     */
    public static function isCatIdKarnaval($cat_id){
        self::getKarnavalCatIdsArray();
        if (in_array($cat_id, self::$_karnavalKats)) return true;
        else return false;
    }

    public function isKarnaval()
    {
      return self::isCatIdKarnaval($this->getId());
    }

    /**
     * @param $name
     * @return Category|false|void
     */
    public static function getByUrlName($name, $lang=''){
      if ($lang=='') $lang='ru';

        if ($trez = self::getFromCachByUrlName($name)) return $trez;

        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT * FROM tovar_rent_cat WHERE cat_url_key='$name'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        if ($result->num_rows<1) return false;

        $row = $result->fetch_assoc();

        $rez=self::createFromDbArray($row, $lang);

        self::addToCach($rez);

        return $rez;

    }

    /**
     * @return bool|void
     */
    public function archCopy(){
        $mysqli = Db::getInstance()->getConnection();

        $query = "INSERT INTO tovar_rent_cat_arch SET arch_time='".time()."', arch_who_id='".User::getCurrentUser()->id_user."', tovar_rent_cat_id='$this->id', main_sub_razdel_id='$this->main_sub_razdel_id', rent_cat_name='$this->name', rent_cat_name_en='$this->name_en', rent_cat_name_lt='$this->name_lt', dog_name='$this->dog_name', cat_url_key='$this->cat_url_key', cat_type='$this->cat_type', cat_sort='$this->cat_sort'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        $this->arch_id = $mysqli->insert_id;

        return true;
    }

    /**
     * @return bool
     */
    public function archAndDelete(){
        $this->archCopy();
        $this->delete();
        return true;
    }

    /**
     * @param $row
     * @return Category
     */
    private static function createFromDbArray($row, $lang=''){
      if($lang=='') $lang='ru';

        $rez = new self($lang);

        $rez->setId($row['tovar_rent_cat_id']);
        $rez->setMainSubRazdelId($row['main_sub_razdel_id']);
        $rez->setName($row['rent_cat_name']);
          $rez->setName($row['rent_cat_name_en'], 'en');
          $rez->setName($row['rent_cat_name_lt'], 'lt');
        $rez->setDogName($row['dog_name']);
        $rez->setCatUrlKey($row['cat_url_key']);
        $rez->setCatType($row['cat_type']);
        $rez->setCatSort($row['cat_sort']);

        if (isset($row['tov_num'])) $rez->setTovNum($row['tov_num']);
        if (isset($row['tovar_rent_cat_arch_id'])) $rez->arch_id = $row['tovar_rent_cat_arch_id'];
        if (isset($row['arch_time'])) {
            $tmpDate = new \DateTime();
            $tmpDate->setTimestamp($row['arch_time']);
            $rez->arch_time = clone $tmpDate;
        }
        if (isset($row['arch_who_id'])) $rez->arch_who_id = $row['arch_who_id'];
        if (isset($row['tovar_rent_cat_arch_id'])) $rez->arch_id = $row['tovar_rent_cat_arch_id'];

        return $rez;
    }

    /**
     * @return Category[]
     */
    public static function getAllCategoriesTovarCount($razdelIdFilter=0, $subrazdelIdFilter=0){
        $mysqli=\bb\Db::getInstance()->getConnection();

        $srch ='';

        if ($razdelIdFilter!=0) {
          $srch = "WHERE razdel.id_razdel = '$razdelIdFilter'";
        }

        /**
         * @var Category[]
         */
        $rez=[];
        $query="SELECT tovar_rent_cat.*, COUNT(tovar_rent_items.cat_id) as tov_num FROM `tovar_rent_cat`
                    LEFT JOIN tovar_rent_items ON tovar_rent_cat.tovar_rent_cat_id=tovar_rent_items.cat_id
                    LEFT JOIN sub_razdel ON sub_razdel.id_sub_razdel = tovar_rent_cat.main_sub_razdel_id
                    LEFT JOIN razdel ON razdel.id_razdel = sub_razdel.main_razdel_id
                    $srch
                    GROUP BY tovar_rent_cat.tovar_rent_cat_id
                    ORDER BY `tovar_rent_cat`.`rent_cat_name`";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
        }
        while ($rez_db=$result->fetch_assoc()) {

            $cat=self::createFromDbArray($rez_db);

            $rez[$cat->getId()] = $cat;

        }

        return $rez;
    }


    /**
     * @return Category[]
     */
    public static function getAllCategories($lang = '') {
      if ($lang == '') $lang = 'ru';

        $mysqli=\bb\Db::getInstance()->getConnection();

        /**
         * @var Category[]
         */
        $rez=array();

        $query="SELECT * FROM ".self::getMysqlTableName()." ORDER BY rent_cat_name";
        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {
            printf("Mysqli Errormessage: %s\n", $mysqli->error);
        }
        while ($rez_db=$result->fetch_assoc()) {

            $cat=self::createFromDbArray($rez_db, $lang);

            $rez[$cat->getId()] = $cat;

        }

        return $rez;

    }


    /**
     * @param $cat_id
     * @param int $tov_exist
     * @return array|false
     */
    public static function getModelsForCategoryById($cat_id, $tov_exist=0) {
        /**
         * @var Category[]
         */
        $rez=array();

        $mysqli=\bb\Db::getInstance()->getConnection();
        //just to memorize
//        $base_query="
//            SELECT tovar_rent.`tovar_rent_id`, tovar_rent.`tovar_rent_cat_id`, COUNT(tovar_rent_items.`status`) AS num FROM tovar_rent
//            LEFT JOIN tovar_rent_items ON tovar_rent.tovar_rent_id = tovar_rent_items.model_id
//            WHERE `tovar_rent_items`.`status`='to_rent' AND tovar_rent.tovar_rent_cat_id>1
//            GROUP BY tovar_rent.tovar_rent_id";

        if ($tov_exist==1){
            $query="
                SELECT tovar_rent.`tovar_rent_id`, tovar_rent.`tovar_rent_cat_id`, COUNT(tovar_rent_items.`status`) AS num FROM tovar_rent
                LEFT JOIN tovar_rent_items ON tovar_rent.tovar_rent_id = tovar_rent_items.model_id
                WHERE `tovar_rent_items`.`item_id`>0 AND tovar_rent.tovar_rent_cat_id='$cat_id'
                GROUP BY tovar_rent.tovar_rent_id
            ";
        }
        else{
            $query="SELECT tovar_rent_id FROM
                          tovar_rent WHERE tovar_rent_cat_id = '$cat_id'";
        }

        //echo $query;
        $result = $mysqli->query($query);
        if (!$result) {printf("Mysqli Errormessage: %s\n", $mysqli->error);}

        if ($result->num_rows<1) return false;

        while ($rez_db=$result->fetch_assoc()) {
            $rez[]=$rez_db['tovar_rent_id'];
        }

        return $rez;
    }

    /**
     * @param $razdelUrlName
     * @return array
     */
    public static function getCatIdsForRazdelUrlName($razdelUrlName) {
        $rez =[];
        $mysqli=\bb\Db::getInstance()->getConnection();

        $query = "SELECT DISTINCT(subrazdel_category.tovar_rent_cat_id) as cat_id
                    FROM `subrazdel_category`
                    LEFT JOIN razdel_subrazdel ON subrazdel_category.id_sub_razdel = razdel_subrazdel.id_sub_razdel
                    LEFT JOIN razdel ON razdel_subrazdel.id_razdel=razdel.id_razdel
                    WHERE razdel.url_razdel_name = '$razdelUrlName'";

        $result = $mysqli->query($query);
        if (!$result) {printf("Mysqli Errormessage: %s\n", $mysqli->error);}

        if ($result->num_rows<1) return [];
        while ($row = $result->fetch_assoc()) {
            $rez[]=$row['cat_id'];
        }

        return $rez;
    }

    /**
     * @param $lang
     * @param $razdel_url_key
     * @param $subRazdelUrlKey
     * @return string
     */
    public function getUrlForPage($lang, $razdel_url_key='', $subRazdelUrlKey=''){
      if ($lang=='') $lang='ru';
        $sr = SubRazdel::getById($this->getMainSubRazdelId());
        if ($sr) {
            $r = Razdel::getById($sr->getMainRazdelId());
        }
        else $r=false;

        if ($sr && $r) {
            return '/'.$lang.'/'.$r->getUrlRazdelName().'/'.$sr->getUrlSubRazdelName().'/'.$this->getCatUrlKey();
        }
        else {
            return '/'.$lang.'/';
        }
    }

  /**
   * @param $razdelId
   * @return Category[]|false
   */
  public static function getCategoriesForRazdel($razdelId){
      /**
       * @var Category[]
       */
      $rez=array();

      $mysqli=\bb\Db::getInstance()->getConnection();

      $query="
              SELECT * FROM `tovar_rent_cat`
              LEFT JOIN subrazdel_category ON subrazdel_category.tovar_rent_cat_id = tovar_rent_cat.tovar_rent_cat_id
              LEFT JOIN razdel_subrazdel ON razdel_subrazdel.id_sub_razdel = subrazdel_category.id_sub_razdel
              WHERE razdel_subrazdel.id_razdel=$razdelId
            ";

      //echo $query;
      $result = $mysqli->query($query);
      if (!$result) {printf("Mysqli Errormessage: %s\n", $mysqli->error);}

      if ($result->num_rows<1) return false;

      while ($row=$result->fetch_assoc()) {
        $cat = self::createFromDbArray($row);
        $rez[$cat->getId()] = $cat;
      }

      return $rez;
    }

  /**
   * @param $subRazdelId
   * @return Category[]|false
   */
  public static function getCategoriesForSubRazdel($subRazdelId){
    $rez=[];

    $mysqli=\bb\Db::getInstance()->getConnection();

    $query="SELECT * FROM `tovar_rent_cat` WHERE main_sub_razdel_id='$subRazdelId'";

    $result = $mysqli->query($query);
    if (!$result) {printf("Mysqli Errormessage: %s\n", $mysqli->error);}

    if ($result->num_rows<1) return false;

    while ($row=$result->fetch_assoc()) {
      $cat = self::createFromDbArray($row);
      $rez[$cat->getId()] = $cat;
    }

    return $rez;
  }

  public static function getRentedOutDaysPercent(\DateTime $from, \DateTime $to, array $catIds)
  {
    $tovNum1 = \bb\classes\tovar::getTovNumberForCatsForDate($from, $catIds);
    $tovNum2 = \bb\classes\tovar::getTovNumberForCatsForDate($to, $catIds);
    $tovNumAvg = ($tovNum1+$tovNum2)/2;

    $interval = $to->diff($from);
    $daysKolVo = $interval->days;

    $daysForRent = $daysKolVo * $tovNumAvg;

    $daysRentedOut = Deal::getRentDaysNumForCatIds($from, $to, $catIds);

    $ratio = $daysRentedOut/$daysForRent*100;

    return $ratio;
  }

}
