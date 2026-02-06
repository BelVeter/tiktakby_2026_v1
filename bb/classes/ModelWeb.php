<?php


namespace bb\classes;


use bb\Base;
use bb\Db;
use function PHPUnit\Framework\stringStartsWith;

class ModelWeb
{
  private $web_id;
  private $lang;
  public $model_id;
  public $page_addr;
  public $title;
  public $meta_description;
  public $breadcrumbs_name;

  public $l2_pic;//full url
  public $l2_name;
  public $l2_alt;

  public $item_name_main;

  public $m_pic_big;
  public $m_pic_alt;
  public $m_a_title;

  public $logo;//url of logo file - to change with producer
  public $main_descr;

  private $sort_num;
  private $tarif_line_period;
  private $tarif_base_days;
  private $keywords;
  private $status;//show, not_show

  private $_catId;

  private $dopCatArray;//cat_id, dop_cat_pic_url;

  private $l2_availability_show;//1,0

  /**
   * @var \DateTime
   */
  private $change_time;//to do - implement load

  /**
   * @var Picture[]
   */
  private $dop_pics;

  /**
   * @var []ModelWeb[]
   */
  private static $_modelWebArray;

  /**
   * @param $lang
   */
  public function __construct($model_id, $lang = '')
  {
    if ($lang == '')
      $lang = 'ru';
    $this->lang = $lang;
    $this->model_id = $model_id;
    $this->dopCatArray = [];

    //default values
    $this->l2_availability_show = 1;
  }

  /**
   * @return mixed
   */
  public function getKeywords()
  {
    return $this->keywords;
  }

  /**
   * @param mixed $keywords
   */
  public function setKeywords($keywords): void
  {
    $this->keywords = $keywords;
  }

  /**
   * @return mixed
   */
  public function getStatus()
  {
    return $this->status;
  }

  /**
   * @param mixed $status
   */
  public function setStatus($status): void
  {
    $this->status = $status;
  }

  /**
   * @return mixed
   */
  public function getMetaDescription()
  {
    return $this->meta_description;
  }

  /**
   * @param mixed $meta_description
   */
  public function setMetaDescription($meta_description): void
  {
    $str = mb_ereg_replace('"', '', $meta_description);
    $str = mb_ereg_replace("'", "", $str);
    $this->meta_description = $str;
  }




  /**
   * @return mixed
   */
  public function getTarifLinePeriod()
  {
    return $this->tarif_line_period;
  }

  /**
   * @param mixed $tarif_line_period
   */
  public function setTarifLinePeriod($tarif_line_period): void
  {
    $this->tarif_line_period = $tarif_line_period;
  }

  /**
   * @return mixed
   */
  public function getTarifBaseDays()
  {
    return $this->tarif_base_days;
  }

  /**
   * @param mixed $tarif_base_days
   */
  public function setTarifBaseDays($tarif_base_days): void
  {
    $this->tarif_base_days = $tarif_base_days;
  }


  /**
   * @param $catId
   * @param $dopCatPicUrl
   * @return bool
   */
  public function addDopCat($catId, $dopCatPicUrl)
  {
    $catId = $catId * 1;
    $this->dopCatArray[] = [$catId, $dopCatPicUrl];
    return true;
  }

  /**
   * @return bool
   */
  public function saveDopCats()
  {
    $this->deleteDopCats();
    foreach ($this->dopCatArray as $arr) {
      $this->saveDopCat($arr[0], $arr[1]);
    }
    return true;
  }

  /**
   * @param $catId
   * @param $dopPhotoUrl
   * @return bool|void
   */
  private function saveDopCat($catId, $dopPhotoUrl)
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "INSERT INTO multi_web SET model_id='$this->model_id', add_cat_id='$catId', l2_pic_add='$dopPhotoUrl'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при удалении доп категорий показа в MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return true;
  }

  /**
   * @return bool|void
   */
  public function loadLastProducerLogo()
  {
    $mysqli = Db::getInstance()->getConnection();
    $model = Model::getById($this->getModelId());
    if ($model) {
      $model_ids = Model::getModelIdsArrayByProducer($model->getProducer());

      if ($model_ids && count($model_ids) > 0) {
        $query = "SELECT logo FROM rent_model_web WHERE model_id IN (" . implode(',', $model_ids) . ") ORDER BY model_id DESC";
        $result = $mysqli->query($query);
        if (!$result) {
          die('Сбой при доступе к БД MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        while ($row = $result->fetch_assoc()) {
          if (substr($row['logo'], 0, 1) == '/') {
            $this->setLogoUrlAddress($row['logo']);
            return true;
          }
        }
      }
    }

    return false;

  }

  /**
   * @return bool
   */
  public function updateLogoUrlForAll()
  {
    $mysqli = Db::getInstance()->getConnection();
    $model = Model::getById($this->getModelId());
    $modelIds = Model::getModelIdsArrayByProducer($model->getProducer(), 0);
    $query = "UPDATE rent_model_web SET logo='$this->logo' WHERE model_id IN (" . implode(',', $modelIds) . ")";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обновлении url лого в MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return true;
  }

  /**
   * @return bool|void
   */
  private function deleteDopCats()
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "DELETE FROM multi_web WHERE model_id='$this->model_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при удалении доп категорий показа в MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return true;
  }


  /**
   * @param $src
   * @param $alt
   * @param $title
   * @return bool
   */
  public function addPicture($src, $alt = '', $title = '')
  {
    if (!is_array($this->dop_pics))
      $this->dop_pics = [];
    $this->dop_pics[] = new Picture($src, $alt, $title);
    return true;
  }

  /**
   * @return false|void
   */
  public function loadDopPictures()
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM dop_photos WHERE model_id='$this->model_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при запросе доп фото в MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    if ($result->num_rows < 1)
      return false;
    $rez = [];
    while ($row = $result->fetch_assoc()) {
      $rez[] = new Picture($row['src'], $row['alt'], $row['title']);
    }
    $this->dop_pics = $rez;
  }

  /**
   * @return Picture[]
   */
  public function getDopPictures()
  {
    return $this->dop_pics;
  }

  public function getDopPicturesNum()
  {
    if (is_array($this->dop_pics) && count($this->dop_pics) > 0)
      return count($this->dop_pics);
    else
      return 0;
  }

  /**
   * @param $src
   * @return void
   */
  public static function deleteDopPictureBySrc($src)
  {
    $mysqli = Db::getInstance()->getConnection();
    $src_escaped = $mysqli->real_escape_string($src);
    $query = "DELETE FROM dop_photos WHERE src='$src_escaped'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при удалении доп фото в MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
  }

  /**
   * @param $src
   * @param $alt
   * @param $title
   * @return void
   */
  public function saveDopPicture($src, $alt = '', $title = '')
  {
    self::saveDopPictureStatic($this->model_id, $src, $alt = '', $title = '');
  }


  /**
   * @param $model_id
   * @param $src
   * @param $alt
   * @param $title
   * @return void
   */
  public static function saveDopPictureStatic($model_id, $src, $alt = '', $title = '')
  {
    $mysqli = Db::getInstance()->getConnection();
    $src_escaped = $mysqli->real_escape_string($src);
    $alt_escaped = $mysqli->real_escape_string($alt);
    $title_escaped = $mysqli->real_escape_string($title);
    $query = "INSERT INTO dop_photos SET model_id='$model_id', src='$src_escaped', alt='$alt_escaped', `title`='$title_escaped'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при вставке доп фото в MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
  }

  /**
   * @return mixed
   */
  public function getCatId()
  {
    return $this->_catId;
  }

  /**
   * @param mixed $catId
   */
  public function setCatId($catId): void
  {
    $this->_catId = $catId;
  }

  /**
   * @return mixed
   */
  public function getL2AvailabilityShow()
  {
    return $this->l2_availability_show;
  }

  /**
   * @param mixed $l2_availability_show
   */
  public function setL2AvailabilityShow($l2_availability_show): void
  {
    $this->l2_availability_show = $l2_availability_show;
  }




  /**
   * @return \DateTime
   */
  public function getChangeTime(): \DateTime
  {
    return $this->change_time;
  }

  /**
   * @return mixed
   */
  public function getBreadcrumbsName($htmlSpecialChars = 1)
  {
    if ($htmlSpecialChars)
      return htmlspecialchars($this->breadcrumbs_name);
    return $this->breadcrumbs_name;
  }

  /**
   * @param mixed $breadcrumbs_name
   */
  public function setBreadcrumbsName($breadcrumbs_name): void
  {
    $this->breadcrumbs_name = $breadcrumbs_name;
  }

  /**
   * @param \DateTime $change_time
   */
  public function setChangeTime(\DateTime $change_time): void
  {
    $this->change_time = $change_time;
  }


  /**
   * @return mixed
   */
  public function getWebId()
  {
    return $this->web_id;
  }

  /**
   * @param mixed $web_id
   */
  public function setWebId($web_id): void
  {
    $this->web_id = $web_id;
  }

  /**
   * @return mixed|string
   */
  public function getLang()
  {
    return $this->lang;
  }

  /**
   * @param mixed|string $lang
   */
  public function setLang($lang): void
  {
    $this->lang = $lang;
  }

  /**
   * @return mixed
   */
  public function getModelId()
  {
    return $this->model_id;
  }

  /**
   * @param mixed $model_id
   */
  public function setModelId($model_id): void
  {
    $this->model_id = $model_id;
  }

  /**
   * @return mixed
   */
  public function getPageUrlCode()
  {
    return $this->page_addr;
  }

  /**
   * @param mixed $page_addr
   */
  public function setPageUrlCode($page_addr): void
  {
    $this->page_addr = $page_addr;
  }

  /**
   * @return mixed
   */
  public function getTitle($htmlSpecialChars = 0)
  {
    if ($htmlSpecialChars)
      return htmlspecialchars($this->title);
    return $this->title;
  }

  /**
   * @param mixed $title
   */
  public function setTitle($title): void
  {
    $str = mb_ereg_replace('"', '', $title);
    $str = mb_ereg_replace("'", "", $str);
    $this->title = $str;
  }

  /**
   * @return mixed
   */
  public function getL2PicUrlAddress()
  {
    return $this->l2_pic;
  }

  /**
   * @param mixed $l2_pic
   */
  public function setL2PicUrlAddress($l2_pic): void
  {
    $this->l2_pic = $l2_pic;
  }

  /**
   * @return mixed
   */
  public function getL2Name($htmlSpecialChars = 0)
  {
    if ($htmlSpecialChars)
      return htmlspecialchars($this->l2_name);
    return $this->l2_name;
  }

  /**
   * @param mixed $l2_name
   */
  public function setL2Name($l2_name): void
  {
    $this->l2_name = $l2_name;
  }

  /**
   * @return mixed
   */
  public function getL2Alt($htmlSpecialChars = 0)
  {
    return $this->l2_alt;
  }

  /**
   * @param mixed $l2_alt
   */
  public function setL2Alt($l2_alt): void
  {
    $this->l2_alt = $l2_alt;
  }

  /**
   * @return mixed
   */
  public function getItemNameMain($htmlSpecialChars = 0)
  {
    if ($htmlSpecialChars)
      return htmlspecialchars($this->item_name_main);
    return $this->item_name_main;
  }

  /**
   * @param mixed $item_name_main
   */
  public function setItemNameMain($item_name_main): void
  {
    $this->item_name_main = $item_name_main;
  }

  /**
   * @return mixed
   */
  public function getMPicBigUrlAddress()
  {
    return $this->m_pic_big;
  }

  /**
   * @param mixed $m_pic_big
   */
  public function setMPicBigUrlAddress($m_pic_big): void
  {
    $this->m_pic_big = $m_pic_big;
  }

  /**
   * @return mixed
   */
  public function getMPicAlt($htmlSpecialChars = 0)
  {
    return $this->m_pic_alt;
  }

  /**
   * @param mixed $m_pic_alt
   */
  public function setMPicAlt($m_pic_alt): void
  {
    $this->m_pic_alt = $m_pic_alt;
  }

  /**
   * @return mixed
   */
  public function getMATitle($htmlSpecialChars = 1)
  {
    if ($htmlSpecialChars)
      return htmlspecialchars($this->m_a_title);
    return $this->m_a_title;
  }

  /**
   * @param mixed $m_a_title
   */
  public function setMATitle($m_a_title): void
  {
    $this->m_a_title = $m_a_title;
  }

  /**
   * @return mixed
   */
  public function getLogoUrlAddress()
  {
    return $this->logo;
  }

  /**
   * @param mixed $logo
   */
  public function setLogoUrlAddress($logo): void
  {
    $this->logo = $logo;
  }

  /**
   * @return mixed
   */
  public function getMainDescrHtml()
  {
    return $this->main_descr;
  }

  /**
   * @param mixed $main_descr
   */
  public function setMainDescrHtml($main_descr): void
  {
    $this->main_descr = $main_descr;
  }

  /**
   * @return mixed
   */
  public function getSortNum()
  {
    return $this->sort_num;
  }

  /**
   * @param mixed $sort_num
   */
  public function setSortNum($sort_num): void
  {
    $this->sort_num = $sort_num;
  }


  /**
   * @return bool|void
   */
  public function save()
  {
    $mysqli = Db::getInstance()->getConnection();

    if ($this->getWebId() < 1) {
      $query = "INSERT INTO rent_model_web SET lang='$this->lang', model_id='$this->model_id', page_addr='$this->page_addr', `title`='" . addslashes($this->title) . "', `meta_description`='" . addslashes($this->meta_description) . "', breadcrumbs_name='" . addslashes($this->breadcrumbs_name) . "',
                           l2_pic='$this->l2_pic', l2_name='" . addslashes($this->l2_name) . "', l2_alt='" . addslashes($this->l2_alt) . "', item_name_main='" . addslashes($this->item_name_main) . "',
                           m_pic_big='$this->m_pic_big', m_pic_alt='" . addslashes($this->m_pic_alt) . "', m_a_title='" . addslashes($this->m_a_title) . "', logo='$this->logo', main_descr='" . addslashes($this->main_descr) . "',
                           tarif_line_period='$this->tarif_line_period', tarif_base_days='$this->tarif_base_days', sort_n='$this->sort_num', keywords='$this->keywords', `status`='$this->status', l2_availability_show='$this->l2_availability_show'";
      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при вставке временной брони в MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }
      $this->setWebId($mysqli->insert_id);

      $this->updateCommon();

      return true;
    } else {
      $this->update();
    }
  }

  /**
   * @param $model_id
   * @param $newSortNum
   * @return bool|void
   */
  public static function updateSortN($model_id, $newSortNum)
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "UPDATE rent_model_web SET sort_n='$newSortNum' WHERE model_id='$model_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обновлении веб модели: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return true;
  }

  /**
   * @return bool|void
   */
  public function update()
  {
    if ($this->getWebId() < 1)
      throw new \Error('Update: id not set');

    $mysqli = Db::getInstance()->getConnection();

    $query = "UPDATE rent_model_web SET lang='$this->lang', model_id='$this->model_id', page_addr='$this->page_addr', `title`='" . addslashes($this->title) . "', `meta_description`='" . addslashes($this->meta_description) . "', breadcrumbs_name='" . addslashes($this->breadcrumbs_name) . "',
                           l2_pic='$this->l2_pic', l2_name='" . addslashes($this->l2_name) . "', l2_alt='" . addslashes($this->l2_alt) . "', item_name_main='" . addslashes($this->item_name_main) . "',
                           m_pic_big='$this->m_pic_big', m_pic_alt='" . addslashes($this->m_pic_alt) . "', m_a_title='" . addslashes($this->m_a_title) . "', logo='$this->logo', main_descr='" . addslashes($this->main_descr) . "',
                           tarif_line_period='$this->tarif_line_period', tarif_base_days='$this->tarif_base_days', sort_n='$this->sort_num', keywords='$this->keywords', `status`='$this->status', l2_availability_show='$this->l2_availability_show'
                       WHERE web_id='$this->web_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обновлении веб модели: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $this->updateCommon();

    return true;
  }

  /**
   * @return bool|void
   */
  private function updateCommon()
  {
    $mysqli = Db::getInstance()->getConnection();

    $query = "UPDATE rent_model_web SET page_addr='$this->page_addr', l2_pic='$this->l2_pic', m_pic_big='$this->m_pic_big', logo='$this->logo', sort_n='$this->sort_num',
                tarif_line_period='$this->tarif_line_period', tarif_base_days='$this->tarif_base_days', `status`='$this->status', l2_availability_show='$this->l2_availability_show'
                WHERE model_id='$this->model_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при обновлении веб модели: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return true;
  }


  /**
   * @return bool|void
   */
  public function delete()
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "DELETE FROM rent_model_web WHERE web_id='$this->web_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при удалении web модели в MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    return true;
  }

  /**
   * @param $row
   * @return ModelWeb
   * @throws \Exception
   */
  public static function getFromDbArray($row)
  {
    $mw = new self($row['model_id'], $row['lang']);

    $mw->setWebId($row['web_id']);
    $mw->setPageUrlCode($row['page_addr']);
    $mw->setTitle($row['title']);
    $mw->setMetaDescription($row['meta_description']);
    $mw->setBreadcrumbsName($row['breadcrumbs_name']);

    $mw->setL2PicUrlAddress($row['l2_pic']);
    $mw->setL2Alt($row['l2_alt']);
    $mw->setL2Name($row['l2_name']);

    $mw->setItemNameMain($row['item_name_main']);

    $mw->setMPicBigUrlAddress($row['m_pic_big']);
    $mw->setMPicAlt($row['m_pic_alt']);
    $mw->setMATitle($row['m_a_title']);

    $mw->setLogoUrlAddress($row['logo']);
    $mw->setMainDescrHtml($row['main_descr']);
    $mw->setSortNum($row['sort_n']);
    $mw->setTarifLinePeriod($row['tarif_line_period']);
    $mw->setTarifBaseDays($row['tarif_base_days']);
    $mw->setChangeTime(new \DateTime($row['change_time']));
    $mw->setKeywords($row['keywords']);
    $mw->setStatus($row['status']);
    $mw->setL2AvailabilityShow($row['l2_availability_show']);

    if (isset($row['cat_id']))
      $mw->setCatId($row['cat_id']);

    return $mw;
  }

  /**
   * @param $urlCode
   * @param $modelIdToExclude
   * @return false|mixed|void
   */
  public static function hasDublicatesPageUrlCode($urlCode, $modelIdToExclude)
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT web_id, model_id FROM rent_model_web WHERE page_addr = '$urlCode' AND model_id != '$modelIdToExclude'";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при проверке дубликата web адреса в MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows > 0)
      return $result->fetch_assoc()['model_id'];
    else
      return false;
  }


  /**
   * @return string
   */
  public function getLogoUrlCorrect()
  {
    if ($this->logo == '')
      return '';

    if (strpos($this->logo, self::getPreUrlConstantNoFinalSlash()) === 0) {
      return $this->logo;
    } else {
      return self::getPreUrlConstantNoFinalSlash() . $this->logo;
    }
  }

  /**
   * @param $path
   * @return mixed|string
   */
  public static function getURLCorrectPathFor($path)
  {
    if (strpos($path, self::getPreUrlConstantNoFinalSlash()) === 0) {
      return $path;
    } else {
      return self::getPreUrlConstantNoFinalSlash() . $path;
    }
  }

  /**
   * @return string
   */
  public static function getPreUrlConstantNoFinalSlash()
  {
    return '/public/pics';
  }

  /**
   * @return string
   */
  public static function getPreUrlConstantWithFinalSlash()
  {
    return '/public/pics/';
  }

  /**
   * @return false|string
   */
  public function getNameForUrl()
  {
    $str = $this->page_addr;
    $rez = $str;
    //        $dot=strripos($str, '.');
//        $sl=strripos($str, '/');
//
//        if ($dot && $sl) {
//            $pre_rez = substr($str, $sl+1, $dot-$sl-1);
//        }
//        else {
//            $pre_rez = $str;
//        }
//
//        $rez = mb_ereg_replace('/[^a-z0-9-_]|\s+|\r?\n|\r/gmi', '', $pre_rez);
//        $rez = str_replace('"', "", $rez);
//        $rez = str_replace("'", "", $rez);

    return $rez;
  }

  /**
   * @return bool|void
   */
  public function updateUrlKey()
  {
    $mysqli = Db::getInstance()->getConnection();

    $q = "UPDATE rent_model_web SET page_addr='$this->page_addr' WHERE web_id = '$this->web_id'";
    //echo $q;
    $result = $mysqli->query($q);
    if (!$result) {
      die('Сбой при вставке временной брони в MYSQL: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    return true;
  }

  /**
   * @return mixed
   */
  public function getPageTitle()
  {
    return $this->title;
  }

  /**
   * @return bool
   */
  public function isKarnaval()
  {
    return Category::isCatIdKarnaval($this->getCatId());
  }


  /**
   * @param $model_id
   * @param $lang
   * @return ModelWeb|false|void
   * @throws \Exception
   */
  public static function getByModelId($model_id, $lang = '', $loadDopPhotos = 1)
  {

    if ($lang == '')
      $lang = 'ru';

    if (isset(self::$_modelWebArray[$model_id . $lang])) {
      //Base::varDamp(self::$_modelWebArray[$model_id.$lang]);
      $mw = self::$_modelWebArray[$model_id . $lang];
      if ($loadDopPhotos)
        $mw->loadDopPictures();

      return $mw;
    } else {
      $mysqli = Db::getInstance()->getConnection();

      $query = "SELECT rent_model_web.*, tovar_rent.tovar_rent_cat_id as cat_id
                FROM rent_model_web
                LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_id = rent_model_web.model_id
                WHERE model_id ='$model_id' AND lang='$lang'";

      $result = $mysqli->query($query);
      if (!$result) {
        die('Сбой при загрузке веб модели MYSQL: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
      }

      if ($result->num_rows < 1)
        return false;

      $row = $result->fetch_assoc();

      $mw = self::getFromDbArray($row);

      if ($loadDopPhotos)
        $mw->loadDopPictures();

      if (!is_array(self::$_modelWebArray))
        self::$_modelWebArray = [];
      self::$_modelWebArray[$mw->getModelId() . $mw->getLang()] = $mw;

      return $mw;
    }
  }

  /**
   * @param $model_id
   * @param $lang
   * @return ModelWeb|false|void
   * @throws \Exception
   */
  public static function getByModelIdLangSafe($model_id, $lang = '')
  {
    if ($lang == '')
      $lang = 'ru';

    $mw = self::getByModelId($model_id, $lang);

    if (!$mw)
      $mw = self::getByModelId($model_id, 'ru');

    return $mw;
  }

  /**
   * @param $url_name
   * @param $lang
   * @return ModelWeb|void|null
   */
  public static function getByUrlNameLangSafe($url_name, $lang)
  {
    $p = self::getByUrlName($url_name, $lang);
    if (!$p)
      $p = self::getByUrlName($url_name, 'ru');

    return $p;
  }

  /**
   * @param $url_name
   * @return ModelWeb|void|null
   */
  public static function getByUrlName($url_name, $lang = '', $loadDopPhotos = 1)
  {
    if ($lang == '')
      $lang = 'ru';

    $mysqli = Db::getInstance()->getConnection();

    //$q = "SELECT * FROM rent_model_web WHERE page_addr LIKE '%$url_name%'";
    //$q = "SELECT * FROM rent_model_web WHERE page_addr = '$url_name'";

    $q = "SELECT rent_model_web.*, tovar_rent.tovar_rent_cat_id as cat_id
                FROM rent_model_web
                LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_id = rent_model_web.model_id
                WHERE page_addr = '$url_name' AND lang='$lang'";

    $result = $mysqli->query($q);
    if (!$result) {
      die('Сбой при вставке временной брони в MYSQL: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows < 1)
      return null;

    $row = $result->fetch_assoc();

    $mw = self::getFromDbArray($row);

    if ($loadDopPhotos)
      $mw->loadDopPictures();

    return $mw;
  }

  /**
   * @return ModelWeb[]|false|void
   */
  public static function getAll()
  {
    $rez = [];

    $mysqli = Db::getInstance()->getConnection();

    $q = "SELECT * FROM rent_model_web";
    //echo $q;
    $result = $mysqli->query($q);
    if (!$result) {
      die('Сбой при вставке временной брони в MYSQL: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows < 1)
      return false;

    while ($row = $result->fetch_assoc()) {
      $rez[] = self::getFromDbArray($row);
    }

    return $rez;
  }


  /**
   * @param $cat_id
   * @return array|false|void
   */
  public static function getAdditionalModelIdsForCat($cat_id, $tov_exists = 0)
  {
    $rez = array();

    $mysqli = Db::getInstance()->getConnection();
    if ($tov_exists == 1) {
      $q = "SELECT multi_web.model_id, multi_web.l2_pic_add, COUNT(tovar_rent_items.`status`)
                FROM multi_web
                LEFT JOIN tovar_rent_items ON multi_web.model_id = tovar_rent_items.model_id
                WHERE multi_web.add_cat_id ='$cat_id' AND `tovar_rent_items`.`item_id`>0";
    } else {
      $q = "SELECT model_id FROM multi_web WHERE add_cat_id ='$cat_id'";
    }

    $result = $mysqli->query($q);
    if (!$result) {
      die('Сбой при обращении к базе данных: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows < 1)
      return false;

    while ($row = $result->fetch_assoc()) {
      if ($row['model_id'] > 0)
        $rez[$row['model_id']] = $row['l2_pic_add'];
    }

    if (count($rez) < 1)
      return false;

    return $rez;
  }

  public function getUrlPageAddress($lang = '')
  {
    if ($lang == '')
      $lang = 'ru';

    $model = Model::getById($this->model_id);
    if ($model) {
      $cat_id = $model->cat_id;
    } else
      $cat_id = 0;

    $cat = Category::getById($cat_id);
    if ($cat) {
      $sr = SubRazdel::getById($cat->getMainSubRazdelId());
    } else
      $sr = false;

    if ($sr) {
      $r = Razdel::getById($sr->getMainRazdelId());
    } else
      $r = false;


    if ($cat && $sr && $r) {
      return '/' . $lang . '/' . $r->getUrlRazdelName() . '/' . $sr->getUrlSubRazdelName() . '/' . $cat->getCatUrlKey() . '/' . $this->getNameForUrl();
    } else {
      return '/' . $lang . '/';
    }
  }

  /**
   * @param $text
   * @return array
   */
  public static function getModelIdsFullTextSearch($text)
  {
    //SELECT * FROM rent_model_web WHERE MATCH(`title`, keywords, main_descr) AGAINST('автокресло')
    //CREATE FULLTEXT INDEX text_srch ON rent_model_web(title,keywords,main_descr)

    //        SELECT *,
//          MATCH(books.title) AGAINST('$q') as tscore,
//          MATCH(authors.authorName) AGAINST('$q') as ascore,
//          MATCH(chapters.content) AGAINST('$q') as cscore
//        FROM books
//          LEFT JOIN authors ON books.authorID = authors.authorID
//          LEFT JOIN chapters ON books.bookID = chapters.bookID
//        WHERE
//          MATCH(books.title) AGAINST('$q')
//                OR MATCH(authors.authorName) AGAINST('$q')
//                OR MATCH(chapters.content) AGAINST('$q')
//        ORDER BY (tscore + ascore + cscore) DESC
    $rez = [];

    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT rent_model_web.model_id, MATCH(rent_model_web.title, rent_model_web.l2_name, rent_model_web.item_name_main) AGAINST('$text') AS relevance FROM rent_model_web
                    LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id = rent_model_web.model_id
                    WHERE (
                        MATCH(rent_model_web.title, rent_model_web.l2_name, rent_model_web.item_name_main) AGAINST('$text')
                        )
                    AND tovar_rent_items.item_id>0
                    GROUP BY rent_model_web.model_id
                    ORDER BY relevance DESC";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      printf("Mysqli Errormessage: %s\n", $mysqli->error);
    }

    if ($result->num_rows < 1)
      return [];
    while ($row = $result->fetch_assoc()) {
      $rez[] = $row['model_id'];
    }


    return array_unique($rez);
  }

  /**
   * @return array|void
   */
  public function getAdditionalCategories()
  {
    $mysqli = Db::getInstance()->getConnection();

    $query_add = "SELECT * FROM multi_web WHERE model_id='" . $this->getModelId() . "' ORDER BY add_cat_id";
    //echo $query_add;
    $result_add = $mysqli->query($query_add);
    if (!$result_add) {
      die('Сбой при доступе к базе данных: ' . $query_add . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    if ($result_add->num_rows < 1)
      return [];
    $rez = [];
    while ($row = $result_add->fetch_assoc()) {
      $cat = Category::getById($row['add_cat_id']);
      $rez[] = [$row['add_cat_id'], $cat->getName(), $row['l2_pic_add']];
    }
    return $rez;
  }

}
