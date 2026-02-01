<?php

namespace App\MyClasses;

use bb\Base;
use bb\classes\Category;
use bb\classes\Model;
use bb\classes\Producer;
use bb\classes\Razdel;
use bb\classes\SubRazdel;
use bb\Db;
use bb\classes\FavoriteTovars;
use phpDocumentor\Reflection\Types\True_;

class MainPage
{
    private $id;
    private $level_code; //main,razdel,subrazdel,category
    private $url_key;
    private $lang;//ru/lt/en

    private $title;
    private $meta_description;
    private $h1;
    private $h1_pic_url;
    private $h1_long_text;
    private $code_block_1;
    private $block_2_title;
    private $code_block_2;

    private $change_time;

    private $_listingLimit;
    private $_showPageNumber;
    private $_totalModelsNum;

    private $_devInfo;

  /**
   * @var Razdel;
   */
  public $_razdel;
  /**
   * @var SubRazdel;
   */
  public $_subRazdel;
  /**
   * @var Category;
   */
  public $_category;

  public function getCanonicalUrlBy()
  {
    //main,razdel,subrazdel,category
    if ($this->getLevelCode()=='razdel') return $this->_razdel->getUrlForPage('ru');
    elseif ($this->getLevelCode()=='subrazdel') return $this->_subRazdel->getUrlForPage('ru');
    elseif ($this->getLevelCode()=='category') return $this->_category->getUrlForPage('ru');
    else return false;
  }
    public function isKarnaval(){
      try {
        if (!$this->_razdel) {
          if ($this->getLevelCode()=='razdel') $razdel = Razdel::getByUrlName($this->url_key);
          elseif ($this->getLevelCode()=='subrazdel'){
            $subRazdel = SubRazdel::getByUrlName($this->url_key);
            if($subRazdel && $subRazdel->getMainRazdelId()) $razdel = Razdel::getById($subRazdel->getMainRazdelId());
            else $razdel = new Razdel();
          }
          elseif ($this->getLevelCode()=='category'){
            $cat = Category::getByUrlName($this->url_key);
            if ($cat) $subRazdel = SubRazdel::getById($cat->getMainSubRazdelId());
            else{
              $subRazdel = new SubRazdel();
            }
            if($subRazdel && $subRazdel->getMainRazdelId()) $razdel = Razdel::getById($subRazdel->getMainRazdelId());
            else $razdel = new Razdel();
          }
          $this->_razdel = $razdel;
        }

        return $this->_razdel->isKarnaval();
      }
      catch (\Exception $e){
        return false;
      }

    }

  /**
   * @return array
   */
  public function getDevInfo(): array
  {
    return $this->_devInfo;
  }

  /**
   * @param array $devInfo
   */
  public function addDevInfo($devInfo): void
  {
    $this->_devInfo[] = $devInfo;
  }


  /**
   * @return mixed
   */
  public function getTotalModelsNum()
  {
    return $this->_totalModelsNum;
  }

  /**
   * @param mixed $totalModels
   */
  public function setTotalModelsNum($totalModels): void
  {
    $this->_totalModelsNum = $totalModels;
    $maxPageNumber = ceil($totalModels/$this->getListingLimit());
    if ($this->_showPageNumber > $maxPageNumber) $this->setShowPageNumber($maxPageNumber);
  }

  /**
   * @return float|int
   */
  public function getStartListingNumber(){
    return ($this->getListingLimit()*$this->getShowPageNumber() - $this->getListingLimit()+1);
  }

  /**
   * @return float|int|mixed
   */
  public function getEndListingNumber(){
    $rez = ($this->getListingLimit() * $this->getShowPageNumber());
    if ($rez > $this->getTotalModelsNum()) $rez = $this->getTotalModelsNum();
    return $rez;
  }

  /**
   * @return int
   */
  public function getListingLimit(): int
  {
    return $this->_listingLimit;
  }

  /**
   * @param int $listingLimit
   */
  public function setListingLimit(int $listingLimit): void
  {
    $this->_listingLimit = $listingLimit;
  }

  /**
   * @return int
   */
  public function getShowPageNumber(): int
  {
    return $this->_showPageNumber;
  }

  /**
   * @param int $showPageNumber
   */
  public function setShowPageNumber(int $showPageNumber): void
  {
    $this->_showPageNumber = $showPageNumber;
  }

    /**
     * @param $razdelUrlCode
     * @return bool
     */
    public function isInRazdel($razdelUrlCode){
      try {
        if ($this->level_code == 'category') {
          $cat = Category::getByUrlName($this->url_name);
          $razdelUrls = Razdel::getRazdelUrlNamesForCatId($cat->getId());
        } elseif ($this->level_code == 'subrazdel') {
          $razdelUrls = SubRazdel::getRazdelUrlNamesForSubUrlCode($this->url_key);
        } else {
          $razdelUrls[] = $this->url_key;
        }

        if (in_array($razdelUrlCode, $razdelUrls)) {
          return true;
        } else {
          return false;
        }
      }
      catch (\Exception $e) {
        return false;
      }
    }

    /**
     * @var L2ModelWeb []
     */
    private $l2ModelsWeb;

    /**
     * @var array
     */
    private $breadCrumbsArray;

    private $_showAgeFilter;

  /**
   * @var array
   */
  private $messages;



  /**
   * @param $message
   */
  public function addMessage($message)
  {
    if ($message) {
      $this->messages[] = $message;
    }
  }

  /**
   * @return bool
   */
  public function hasMessages()
  {
    if (is_array($this->messages) && count($this->messages) > 0) return true;
    else return false;
  }

  /**
   * @return array
   */
  public function getMessages()
  {
    return $this->messages;
  }


  /**
   * @param $lang
   * @param $level_code
   * @param $url_key
   */
  public function __construct($lang, $level_code, $url_key)
    {
      $this->_listingLimit = 24;
      $this->_showPageNumber = 1;
      $this->_devInfo=[];

        $this->lang=$lang;
        $this->level_code=$level_code;
        $this->url_key=$url_key;

        $this->l2ModelsWeb = [];
        $this->breadCrumbsArray = [];
        $this->messages=[];

          switch ($lang){
            case 'en':
              $this->breadCrumbsArray['Rental service'] = '/en/';
              break;
            case 'lt':
              $this->breadCrumbsArray['Nuomos paslauga'] = '/lt/';
              break;
            default:
              $this->breadCrumbsArray['Главная'] = '/ru/';
              break;
          }
    }

    /**
     * @return mixed
     */
    public function getShowAgeFilter()
    {
        return $this->_showAgeFilter;
    }

    /**
     * @param mixed $showAgeFilter
     */
    public function setShowAgeFilter($showAgeFilter): void
    {
        $this->_showAgeFilter = $showAgeFilter;
    }

    /**
     * @return mixed
     */
    public function getH1LongText()
    {
        return $this->h1_long_text;
    }

    /**
     * @param mixed $h1_long_text
     */
    public function setH1LongText($h1_long_text): void
    {
        $this->h1_long_text = $h1_long_text;
    }

    /**
     * @return mixed
     */
    public function getH1PicUrl()
    {
        return $this->h1_pic_url;
    }

    /**
     * @param mixed $h1_pic_url
     */
    public function setH1PicUrl($h1_pic_url): void
    {
        $this->h1_pic_url = $h1_pic_url;
    }

    /**
     * @return mixed
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param mixed $lang
     */
    public function setLang($lang): void
    {
        $this->lang = $lang;
    }

    /**
     * @return mixed
     */
    public function getBlock2Title()
    {
        return $this->block_2_title;
    }

    /**
     * @param mixed $block_2_title
     */
    public function setBlock2Title($block_2_title): void
    {
        $this->block_2_title = $block_2_title;
    }



    /**
     * @return mixed
     */
    public function getCodeBlock2()
    {
        return $this->code_block_2;
    }

    /**
     * @param mixed $code_block_2
     */
    public function setCodeBlock2($code_block_2): void
    {
        $this->code_block_2 = $code_block_2;
    }




    /**
     * @return mixed
     */
    public function getChangeTime()
    {
        return $this->change_time;
    }

    /**
     * @param mixed $change_time
     */
    public function setChangeTime($change_time): void
    {
        $this->change_time = $change_time;
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
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getLevelCode()
    {
        return $this->level_code;
    }

    public function getLangCode(){
        return $this->getLang();
    }

    /**
     * @param mixed $level_code
     */
    public function setLevelCode($level_code): void
    {
        $this->level_code = $level_code;
    }

    /**
     * @return mixed
     */
    public function getUrlKey()
    {
        return $this->url_key;
    }

    /**
     * @param mixed $url_key
     */
    public function setUrlKey($url_key): void
    {
        $this->url_key = $url_key;
    }

    /**
     * @return mixed
     */
    public function getTitle($html=1)
    {
        if($html==1) return htmlspecialchars($this->title);
        else return $this->title;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title): void
    {
        $this->title = $title;
    }

    /**
     * @return mixed
     */
    public function getMetaDescription($html=1)
    {
        if ($html==1) return htmlspecialchars($this->meta_description);
        else return $this->meta_description;
    }

    /**
     * @param mixed $meta_description
     */
    public function setMetaDescription($meta_description): void
    {
        $this->meta_description = $meta_description;
    }

    /**
     * @return mixed
     */
    public function getH1($html=1)
    {
        if ($html==1) return htmlspecialchars($this->h1);
        else return $this->h1;
    }

    /**
     * @param mixed $h1
     */
    public function setH1($h1): void
    {
        $this->h1 = $h1;
    }

    /**
     * @return mixed
     */
    public function getCodeBlock1()
    {
        return $this->code_block_1;
    }

    /**
     * @param mixed $code_block_1
     */
    public function setCodeBlock1($code_block_1): void
    {
        $this->code_block_1 = $code_block_1;
    }


    /**
     * @param $text
     * @param $urlKey
     * @return void
     */
    public function addBreadCrumbItem($text, $urlKey){
        $this->breadCrumbsArray[$text] = $urlKey;
    }

    /**
     * @return array
     */
    public function getBreadCrumbsArray(){
        return $this->breadCrumbsArray;
    }

    /**
     * @param $lang
     * @param $levelCode
     * @param $urlKey
     * @return MainPage|false|void
     */
    public static function getPage($lang='ru', $levelCode, $urlKey) {
        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT * FROM pages WHERE level_code='$levelCode' AND url_key='$urlKey' AND lang='$lang'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        if ($result->num_rows<1) return false;

        return self::createFromDbArray($result->fetch_assoc());
    }

    /**
     * @param $lang
     * @param $urlCode
     * @return MainPage|false|void
     */
    public static function getRazdelPageForWeb($lang, $urlCode, $showPageNumber, $filter=false){

      $mysqli = Db::getInstance()->getConnection();
      $lang = $mysqli->real_escape_string($lang);
      $urlCode = $mysqli->real_escape_string($urlCode);


        $p = self::getPageOrFillInfroFromRuOrCreateNew($lang, 'razdel', $urlCode);

        $p->setShowPageNumber($showPageNumber);

        $razdel = Razdel::getByUrlName($urlCode, $lang);

        $p->_razdel = $razdel;

        if ($razdel) {
            if ($p->getTitle() == '') $p->setTitle('Прокат '.$razdel->getNameRazdelText().' в Минске');
            if ($p->getMetaDescription() == '') $p->setMetaDescription($razdel->getNameRazdelText().' напрокат в Минске');
            if ($p->getH1()=='') $p->setH1($razdel->getNameRazdelText());

            $p->addBreadCrumbItem($razdel->getNameRazdelText(), '');

            $modelIdArray = Model::getModelIdsArrayByRazdelUrlName($urlCode, $filter); //model_id, free_num

            $p->setTotalModelsNum(count($modelIdArray));

            //sort
          if ($p->isKarnaval()) {
            usort($modelIdArray, function ($a, $b){
              return $a[2] - $b[2];
            });
          }

            $cuttedModelIdArray = array_slice($modelIdArray, $p->getStartModelNumberForPage(), $p->getListingLimit());
              $cuttedModelIdArray = array_map(function ($a){
                return $a[0];
              },$cuttedModelIdArray);
//            $startCount=0;//!!! tmp restricion
            foreach ($cuttedModelIdArray as $mid) {
                $l2m=L2ModelWeb::getL2ModelWebById($mid, $lang);
//                    if ($l2m && $l2m->isKarnaval()) continue;//!!! tmp restricion
                if ($l2m) $p->addL2ModelWeb($l2m);

//                $startCount++;//!!! tmp restricion
//                if ($startCount>50) break;//!!! tmp restricion

            }
        }
        else {
            $p->setH1('Раздел не найден.');
        }

        return $p;
    }


    /**
     * @param $lang
     * @param $razdelUrl
     * @param $subRazdelUrl
     * @return MainPage|false|void
     */
    public static function getWebPageBySubRazdelAndRazdel ($lang='ru', $razdelUrl, $subRazdelUrl, $showPageNumber, $filter=false){

      $mysqli = Db::getInstance()->getConnection();
      $lang = $mysqli->real_escape_string($lang);
      $razdelUrl = $mysqli->real_escape_string($razdelUrl);
      $subRazdelUrl = $mysqli->real_escape_string($subRazdelUrl);


        $p = self::getPageOrFillInfroFromRuOrCreateNew($lang, 'subrazdel', $subRazdelUrl);

        $p->setShowPageNumber($showPageNumber);

        $razdel = Razdel::getByUrlName($razdelUrl, $lang);
          $p->_razdel = $razdel;
        $subRazdel = SubRazdel::getByUrlName($subRazdelUrl, $lang);
          $p->_subRazdel =$subRazdel;

        if ($razdel && $subRazdel) {
            if ($p->getTitle() == '') $p->setTitle('Прокат '.$subRazdel->getNameSubRazdelText().' в Минске');
            if ($p->getMetaDescription() == '') $p->setMetaDescription($subRazdel->getNameSubRazdelText().' напрокат в Минске');
            if ($p->getH1()=='') $p->setH1($subRazdel->getNameSubRazdelText());
            $p->addBreadCrumbItem($razdel->getNameRazdelText(), $razdel->getUrlForPage($lang));
            $p->addBreadCrumbItem($subRazdel->getNameSubRazdelText(), '');

            $modelIdArray = Model::getModelIdsArrayByRazdelAndSubRazdelNames($razdelUrl, $subRazdelUrl, $filter);

          if ($p->isKarnaval()) {
            usort($modelIdArray, function ($a, $b){
              return $a[2] - $b[2];
            });
          }

            $p->setTotalModelsNum(count($modelIdArray));
            $cuttedModelIdArray = array_slice($modelIdArray, $p->getStartModelNumberForPage(), $p->getListingLimit());
            $cuttedModelIdArray = array_map(function ($a){
              return $a[0];
            },$cuttedModelIdArray);

            foreach ($cuttedModelIdArray as $mid) {
                if ($l2m=L2ModelWeb::getL2ModelWebById($mid, $lang)) $p->addL2ModelWeb($l2m);
            }
        }
        else {
            $p->setH1('Раздел не найден.');
        }

        return $p;
    }

    /**
     * @param $lang
     * @param $razdelUrl
     * @param $subRazdelUrl
     * @param $catUrlName
     * @return MainPage|false|void
     */
    public static function getWebPageByCategoryAndSubRazdelAndRazdel($lang='ru', $razdelUrl, $subRazdelUrl, $catUrlName, $showPageNumber, $filter=false){

      $mysqli = Db::getInstance()->getConnection();
      $lang = $mysqli->real_escape_string($lang);
      $razdelUrl = $mysqli->real_escape_string($razdelUrl);
      $subRazdelUrl = $mysqli->real_escape_string($subRazdelUrl);
      $catUrlName = $mysqli->real_escape_string($catUrlName);

        $p = self::getPageOrFillInfroFromRuOrCreateNew($lang, 'category', $catUrlName);

        $p->setShowPageNumber($showPageNumber);

        $razdel = Razdel::getByUrlName($razdelUrl, $lang);
          $p->_razdel = $razdel;
        $subRazdel = SubRazdel::getByUrlName($subRazdelUrl, $lang);
          $p->_subRazdel = $subRazdel;
        $cat = Category::getByUrlName($catUrlName, $lang);
          $p->_category = $cat;

        if ($razdel && $subRazdel && $cat) {
            if ($p->getTitle() == '') $p->setTitle('Прокат '.$cat->getName().' в Минске');
            if ($p->getMetaDescription() == '') $p->setMetaDescription($cat->getName().' напрокат в Минске');
            if ($p->getH1()=='') $p->setH1($cat->getName());
            $p->addBreadCrumbItem($razdel->getNameRazdelText(), $razdel->getUrlForPage($lang));
            $p->addBreadCrumbItem($subRazdel->getNameSubRazdelText(), $subRazdel->getUrlForPage($lang, $razdel->getUrlRazdelName()));
            $p->addBreadCrumbItem($cat->getName(), '');

            $modelIdArray = Model::getModelIdsArrayByCategoryId($cat->getId(), $filter);

            if ($p->isKarnaval()) {
              usort($modelIdArray, function ($a, $b){
                return $a[2] - $b[2];
              });
            }
            else{
              usort($modelIdArray, function ($a, $b){
                return -$a[1] + $b[1];
              });
            }

            $p->setTotalModelsNum(count($modelIdArray));
            $cuttedModelIdArray = array_slice($modelIdArray, $p->getStartModelNumberForPage(), $p->getListingLimit());
//            it is not needed here as taken from subrazdel. here cutted array is already normal array
//            $cuttedModelIdArray = array_map(function ($a){
//              return $a[0];
//            },$cuttedModelIdArray);

            foreach ($cuttedModelIdArray as $mid) {
                if ($l2m=L2ModelWeb::getL2ModelWebById($mid[0], $lang)) $p->addL2ModelWeb($l2m);
            }
        }
        else {
            $p->setH1('Раздел не найден.');
        }

        return $p;
    }


    /**
     * @param $l2mw
     * @return bool
     */
    public function addL2ModelWeb($l2mw) {
        $this->l2ModelsWeb[]=$l2mw;
        return true;
    }


    /**
     * @return L2ModelWeb[]|array
     */
    public function getModels()
    {

//      if (!$this->isKarnaval()){
//        usort($this->l2ModelsWeb, function (L2ModelWeb $a, L2ModelWeb $b){
//          return $a->hasItemsAvailable() - $b->hasItemsAvailable();
//        });
//      }

        return $this->l2ModelsWeb;
    }

    /**
     * @param $id
     * @return MainPage|false|void
     */
    public static function getPageById($id) {
        $mysqli = Db::getInstance()->getConnection();

        $query = "SELECT * FROM pages WHERE id='$id'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        if ($result->num_rows<1) return false;

        return self::createFromDbArray($result->fetch_assoc());
    }

    /**
     * @param $lang
     * @param $levelCode
     * @param $urlKey
     * @return MainPage|false|void
     */
    public static function getPageOrFillInfroFromRuOrCreateNew($lang, $levelCode, $urlKey){
        $page = self::getPage($lang, $levelCode, $urlKey);
        if(!$page) {//if no page for respective language - look for russian base
            $page=self::getPage('ru', $levelCode, $urlKey);
                if (!$page) {//create absolutely new page
                    $page = new MainPage($lang, $levelCode, $urlKey);
                }
            $page->setId(null);
            $page->setLang($lang);
        }

      return $page;
    }

    /**
     * @return bool
     */
    public function save(){

        if ($this->getId()>0) $this->update();
        else $this->create();
        return true;
    }

    /**
     * @return bool|void
     */
    public function create(){
        $mysqli = Db::getInstance()->getConnection();

        $query = "INSERT INTO pages SET level_code='$this->level_code', url_key='$this->url_key', lang='$this->lang', title='".addslashes($this->title)."', meta_description='".addslashes($this->meta_description)."', h1='".addslashes($this->h1)."', h1_pic_url='$this->h1_pic_url', h1_long_text='".addslashes($this->h1_long_text)."', code_block_1='".addslashes($this->code_block_1)."', block_2_title='".addslashes($this->block_2_title)."', code_block_2='".addslashes($this->code_block_2)."'";
        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        $this->id=$mysqli->insert_id;

        $this->updateCommon();

        return true;
    }

    /**
     * @return bool|void
     */
    public function update(){
        $mysqli = Db::getInstance()->getConnection();

        $query = "UPDATE pages SET level_code='$this->level_code', url_key='$this->url_key',lang='$this->lang', title='".addslashes($this->title)."', meta_description='".addslashes($this->meta_description)."', h1='".addslashes($this->h1)."', h1_pic_url='$this->h1_pic_url', h1_long_text='".addslashes($this->h1_long_text)."', code_block_1='".addslashes($this->code_block_1)."', block_2_title='".addslashes($this->block_2_title)."', code_block_2='".addslashes($this->code_block_2)."' WHERE id='$this->id'";

        $result = $mysqli->query($query);
        if (!$result) {
            die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }

        $this->updateCommon();

        return true;
    }

  /**
   * @return void
   */
  public function updateCommon(){
      $mysqli = Db::getInstance()->getConnection();

      $query = "UPDATE pages SET h1_pic_url='$this->h1_pic_url' WHERE level_code='$this->level_code' AND url_key='$this->url_key'";
//      echo $query;
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
    }

    public function delete(){
        //!!! implement some check
        self::deleteById($this->getId());
        return true;
    }

    /**
     * @param $id
     * @return bool|void
     */
    public static function deleteById($id){
        $mysqli = Db::getInstance()->getConnection();

        $query="DELETE FROM pages WHERE id='$id'";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);}

        return true;
    }

    /**
     * @param $row
     * @return MainPage
     */
    private static function createFromDbArray($row){
        $p = new self($row['lang'], $row['level_code'], $row['url_key']);

        $p->setId($row['id']);
        $p->setTitle($row['title']);
        $p->setMetaDescription($row['meta_description']);
        $p->setH1($row['h1']);
        $p->setH1PicUrl($row['h1_pic_url']);
        $p->setH1LongText($row['h1_long_text']);
        $p->setCodeBlock1($row['code_block_1']);
        $p->setBlock2Title($row['block_2_title']);
        $p->setCodeBlock2($row['code_block_2']);
        $p->setChangeTime($row['change_time']);

        return $p;
    }

    /**
     * @return bool
     */
    public function hasH1LongText(){
        if ($this->getH1LongText() =='' || $this->getH1LongText()==null || mb_strlen($this->getH1LongText())<5) return false;
        else return true;
    }

    /**
     * @return int
     */
    public function getModelsNum(){
        if (is_array($this->l2ModelsWeb)) {
            return count($this->l2ModelsWeb);
        }
        else{
            return 0;
        }
    }

    /**
     * @return false|FavoriteTovars[]|void
     */
    public function getFavoriteTovars(){
        return FavoriteTovars::getAll();
    }

    /**
     * @return Producer[]|false|void
     */
    public function getProducers(){
        return Producer::getAllProducersTovExists();
    }

  /**
   * @return bool
   */
  public function hasPreviousModelsButton(){
      if ($this->getStartModelNumberForPage() >= $this->_listingLimit) return true;
      else return false;
    }

  /**
   * @return bool
   */
  public function hasNextModelsButton(){
      if (($this->getTotalModelsNum() - $this->getStartModelNumberForPage()) > $this->getListingLimit()) return true;
      else return false;
    }

  /**
   * @return float|int
   */
  private function getStartModelNumberForPage(): int{
    return $this->_listingLimit * $this->_showPageNumber - $this->_listingLimit;
  }

  /**
   * @return bool
   */
  public function isRealPage()
  {
    if ($this->getId()>0) return true;
    else if($this->_razdel) return true;
    else return false;
  }

}
