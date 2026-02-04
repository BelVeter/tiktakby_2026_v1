<?php


namespace bb\classes;


use bb\Db;

class Model
{

  public $model_id;
  public $cat_id;
  public $producer;
  public $model;
  public $set;
  public $color;
  public $agr_price;
  private $age_from;
  private $age_to;
  private $collateral;
  public $price_new;


  public function getModelId()
  {
    return $this->model_id;
  }

  /**
   * @return mixed
   */
  public function getCatId()
  {
    return $this->cat_id;
  }

  /**
   * @param mixed $cat_id
   */
  public function setCatId($cat_id): void
  {
    $this->cat_id = $cat_id;
  }

  /**
   * @return mixed
   */
  public function getCollateral()
  {
    return $this->collateral;
  }

  /**
   * @param mixed $collateral
   */
  public function setCollateral($collateral): void
  {
    $this->collateral = $collateral;
  }

  /**
   * @return mixed
   */
  public function getPriceNew()
  {
    return $this->price_new;
  }

  /**
   * @param mixed $price_new
   */
  public function setPriceNew($price_new): void
  {
    $this->price_new = $price_new;
  }



  /**
   * @return mixed
   */
  public function getProducer()
  {
    return $this->producer;
  }

  /**
   * @param mixed $producer
   */
  public function setProducer($producer): void
  {
    $this->producer = $producer;
  }





  /**
   * @return mixed
   */
  public function getAgeFrom()
  {
    return $this->age_from;
  }

  /**
   * @param mixed $age_from
   */
  public function setAgeFrom($age_from): void
  {
    $this->age_from = $age_from;
  }

  /**
   * @return mixed
   */
  public function getAgeTo()
  {
    return $this->age_to;
  }

  /**
   * @param mixed $age_to
   */
  public function setAgeTo($age_to): void
  {
    $this->age_to = $age_to;
  }



  /**
   * @var Model
   */
  private static $_model;


  /**
   * @param Model $m
   * @return bool|void
   */
  public static function addToCach(Model $m)
  {
    if (!is_array(self::$_model))
      self::$_model = [];

    self::$_model[$m->model_id] = $m;
  }

  /**
   * @param $from
   * @param $to
   * @return array
   */
  public static function getModelIdsArrayByAge($from = 0, $to = 0)
  {
    $rez = [];
    $mysqli = \bb\Db::getInstance()->getConnection();

    $query = "SELECT DISTINCT(tovar_rent.tovar_rent_id) as model_id
                    FROM `tovar_rent`
                    LEFT JOIN tovar_rent_items ON tovar_rent.tovar_rent_id = tovar_rent_items.model_id
                    WHERE ((tovar_rent.age_from <= '$from' AND tovar_rent.age_to>'$from') OR (tovar_rent.age_from <= '$to' AND tovar_rent.age_to>'$to')) AND tovar_rent_items.model_id>0";
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
   * @param $producer
   * @return array|false
   */
  public static function getModelIdsArrayByProducer($producer = '', $hasItems = 1)
  {
    if ($producer == '')
      return false;

    $mysqli = \bb\Db::getInstance()->getConnection();
    if ($hasItems == 1) {
      $query = "SELECT DISTINCT(tovar_rent.tovar_rent_id) as model_id
                    FROM `tovar_rent`
                    LEFT JOIN tovar_rent_items ON tovar_rent.tovar_rent_id = tovar_rent_items.model_id
                    WHERE tovar_rent.producer='$producer' AND tovar_rent_items.model_id>0";
    } else {
      $query = "SELECT tovar_rent.tovar_rent_id as model_id
                    FROM `tovar_rent`
                    WHERE tovar_rent.producer='$producer'";
    }

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
   * @param $id
   * @return false
   */
  public static function getFromCachById($id)
  {
    if (is_array(self::$_model) && key_exists($id, self::$_model))
      return self::$_model[$id];
    else
      return false;
  }

  /**
   * @param int $model_id
   * @return Model
   */
  public static function getById($model_id)
  {
    if ($model_id < 1)
      return false;

    if ($rez = self::getFromCachById($model_id))
      return $rez;

    $m = new self();

    $mysqli = \bb\Db::getInstance()->getConnection();

    $query = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$model_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows < 1)
      return false;

    $row = $result->fetch_assoc();

    $m->model_id = $row['tovar_rent_id'];
    $m->cat_id = $row['tovar_rent_cat_id'];
    $m->producer = $row['producer'];
    $m->model = $row['model'];
    $m->set = $row['set'];
    $m->color = $row['color'];
    $m->agr_price = $row['agr_price'];
    $m->setAgeFrom($row['age_from']);
    $m->setAgeTo($row['age_to']);
    $m->setCollateral($row['collateral']);
    $m->price_new = $row['price_new'];

    self::addToCach($m);

    return $m;

  }

  /**
   * @return string
   */
  public function getFullName()
  {
    return $this->getCatName() . ': ' . $this->model . ', ' . $this->producer . ' (цвет:' . $this->color . ', стандартная комплектация: ' . $this->set . ')';
  }

  /**
   * @return string
   */
  public function getShortName()
  {
    return $this->getCatName() . ': ' . $this->model . ', ' . $this->producer . ' (цвет:' . $this->color . ')';
  }

  /**
   * @return string
   */
  public function getShortNameModelOnly($color = true)
  {
    if ($color)
      return $this->model . ' (цвет:' . $this->color . ')';
    else
      return $this->model;

  }

  public function getCatName()
  {
    $mysqli = \bb\Db::getInstance()->getConnection();

    $query = "SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='$this->cat_id'";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $row = $result->fetch_assoc();

    return $row['dog_name'];
  }


  public static function getModelIdSrch($str)
  {
    $ids = array();

    $mysqli = \bb\Db::getInstance()->getConnection();
    $query = "SELECT model_id FROM tovar_rent WHERE model LIKE '%$str%'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    while ($row = $result->fetch_assoc()) {
      $ids[] = $row['model_id'];
    }
    if (count($ids) > 0) {
      return $ids;
    } else
      return false;
  }

  /**
   * @param $model_id
   * @return void
   */
  public static function getFreeItemsNumber($model_id)
  {//!!! check, seems not works
    $mysqli = \bb\Db::getInstance()->getConnection();

    $query = "SELECT (item_id) FROM tovar_rent_items WHERE model_id='$model_id' AND (`status`='to_rent' OR (`status`='t_bron' AND br_time<" . time() . ")) LIMIT 1";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $free_num = $result->num_rows;

    return $free_num;
  }

  /**
   * @return bool
   */
  public function hasFreeItems()
  {
    $num = self::getFreeItemsNumber($this->model_id);
    if ($num > 0)
      return true;
    else
      return false;
  }

  /**
   * @param $urlName
   * @return array
   */
  public static function getModelIdsArrayByRazdelUrlName($urlName, $filter = false)
  {
    $rez = [];
    $mysqli = \bb\Db::getInstance()->getConnection();

    //filter add on
    $filterAddOnQuery = '';
    if ($filter && isset($filter['gender'])) {

      if ($filter['gender'] == 'm') {
        //$filterAddOnQuery.="AND (tovar_rent_items.sex IN('m', 'u') OR tovar_rent.m_sex IN('m', 'u'))";
        $filterAddOnQuery .= "AND (tovar_rent.m_sex IN('m', 'u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('m', 'u')))";
      } elseif ($filter['gender'] == 'f') {
        $filterAddOnQuery .= "AND (tovar_rent.m_sex IN('f', 'u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('f', 'u')))";
      } elseif ($filter['gender'] == 'u') {
        $filterAddOnQuery .= "AND (tovar_rent.m_sex IN('u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('u')))";
      }

    }
    if ($filter && isset($filter['rost']) && $filter['rost'] > 0) {
      $rost = $filter['rost'];
      $rostVariance = 3;

      $filterAddOnQuery .= " AND (tovar_rent_items.item_rost1-3)<='$rost' AND (tovar_rent_items.item_rost2+3)>='$rost'";
    }


    $query = "SELECT tovar_rent.tovar_rent_id as model_id, SUM(IF(tovar_rent_items.status IN('to_rent', 't_bron'), 1, 0)) AS free_num, MAX(rent_model_web.sort_n) as sort_n
                    FROM `subrazdel_category`
                    LEFT JOIN razdel_subrazdel ON subrazdel_category.id_sub_razdel = razdel_subrazdel.id_sub_razdel
                    LEFT JOIN razdel ON razdel_subrazdel.id_razdel = razdel.id_razdel
                    LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_cat_id = subrazdel_category.tovar_rent_cat_id
                    LEFT JOIN rent_model_web ON tovar_rent.tovar_rent_id = rent_model_web.model_id
                    LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id = tovar_rent.tovar_rent_id
                    WHERE razdel.url_razdel_name = '$urlName' AND tovar_rent.tovar_rent_id>0 AND tovar_rent_items.item_id>0
                      AND rent_model_web.web_id>0 $filterAddOnQuery
                    GROUP BY tovar_rent.tovar_rent_id
                    ORDER BY `free_num` DESC
                    ";
    $result = $mysqli->query($query);
    if (!$result) {
      printf("Mysqli Errormessage: %s\n", $mysqli->error);
    }

    if ($result->num_rows < 1)
      return [];
    while ($row = $result->fetch_assoc()) {
      $rez[] = [$row['model_id'], $row['free_num'], $row['sort_n']];
    }

    return $rez;
  }

  public static function getModelIdsArrayForFavoriteTovSlider(Model $model, $razdelUrlCode, $currentSubRazdelUrlCode, $maxNum = 0)
  {
    $rez = [];
    $mysqli = \bb\Db::getInstance()->getConnection();

    if ($model->getAgeFrom() > 0 || $model->getAgeTo() > 0) {
      $srch = " AND ((tovar_rent.age_from > '" . $model->getAgeFrom() . "' AND tovar_rent.age_from < '" . $model->getAgeTo() . "')  OR ((tovar_rent.age_to > '" . $model->getAgeFrom() . "' AND tovar_rent.age_to < '" . $model->getAgeTo() . "')))";
    } else {
      $srch = '';
    }

    $query = "SELECT tovar_rent.tovar_rent_id as model_id, razdel.url_razdel_name, sub_razdel.url_sub_razdel_name, COUNT(tovar_rent_items.item_id) as tov_count

                    FROM `tovar_rent`

                    LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id = tovar_rent.tovar_rent_id
                    LEFT JOIN tovar_rent_cat ON tovar_rent_cat.tovar_rent_cat_id = tovar_rent.tovar_rent_cat_id
                    LEFT JOIN sub_razdel ON sub_razdel.id_sub_razdel = tovar_rent_cat.main_sub_razdel_id
                    LEFT JOIN razdel ON sub_razdel.main_razdel_id = razdel.id_razdel

                    WHERE tovar_rent_items.item_id>0 AND razdel.url_razdel_name='$razdelUrlCode' AND sub_razdel.url_sub_razdel_name!='$currentSubRazdelUrlCode'$srch

                    GROUP BY tovar_rent.tovar_rent_id
                    ORDER BY RAND()";
    $result = $mysqli->query($query);
    if (!$result) {
      printf("Query:" . $query . " Mysqli Errormessage: %s\n", $mysqli->error);
    }
    if ($result->num_rows < 1)
      return [];

    $tmpArray = [];

    while ($row = $result->fetch_assoc()) {
      if (isset($tmpArray[$row['url_sub_razdel_name']])) { // we have item
        $tmpArray[$row['url_sub_razdel_name']][] = $row['model_id'];
      } else {//new item
        $tmpArray[$row['url_sub_razdel_name']] = [$row['model_id']];
      }
    }

    $maxIndex = 0;
    foreach ($tmpArray as $array) {
      if (count($array) > ($maxIndex - 1))
        $maxIndex = count($array) - 1;
    }

    for ($index = 0; $index <= $maxIndex; $index++) {
      foreach ($tmpArray as $ar) {
        if ((count($ar) - 1) >= $index)
          $rez[] = $ar[$index];
      }
    }

    $maxNumber = $maxNum;
    if ($maxNum > count($rez))
      $maxNumber = count($rez);

    if ($maxNum > 0) {
      return array_slice($rez, 0, $maxNumber);
    } else {//return all
      return $rez;
    }
  }

  /**
   * @param $razdelUrl
   * @param $subRazdelUrl
   * @return array
   */
  public static function getModelIdsArrayByRazdelAndSubRazdelNames($razdelUrl, $subRazdelUrl, $filter = false)
  {
    $rez = [];
    $mysqli = \bb\Db::getInstance()->getConnection();

    //filter add on
    $filterAddOnQuery = '';
    if ($filter && isset($filter['gender'])) {

      if ($filter['gender'] == 'm') {
        //$filterAddOnQuery.="AND (tovar_rent_items.sex IN('m', 'u') OR tovar_rent.m_sex IN('m', 'u'))";
        $filterAddOnQuery .= "AND (tovar_rent.m_sex IN('m', 'u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('m', 'u')))";
      } elseif ($filter['gender'] == 'f') {
        $filterAddOnQuery .= "AND (tovar_rent.m_sex IN('f', 'u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('f', 'u')))";
      } elseif ($filter['gender'] == 'u') {
        $filterAddOnQuery .= "AND (tovar_rent.m_sex IN('u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('u')))";
      }

    }
    if ($filter && isset($filter['rost']) && $filter['rost'] > 0) {
      $rost = $filter['rost'];
      $rostVariance = 3;

      $filterAddOnQuery .= " AND (tovar_rent_items.item_rost1-3)<='$rost' AND (tovar_rent_items.item_rost2+3)>='$rost'";
    }

    $query = "SELECT tovar_rent.tovar_rent_id as model_id, SUM(IF(tovar_rent_items.status IN('to_rent', 't_bron'), 1, 0)) AS free_num, MAX(rent_model_web.sort_n) as sort_n
                    FROM `subrazdel_category`
                    LEFT JOIN razdel_subrazdel ON subrazdel_category.id_sub_razdel = razdel_subrazdel.id_sub_razdel
                    LEFT JOIN sub_razdel ON razdel_subrazdel.id_sub_razdel = sub_razdel.id_sub_razdel
                    LEFT JOIN razdel ON razdel_subrazdel.id_razdel=razdel.id_razdel
                    LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_cat_id=subrazdel_category.tovar_rent_cat_id
                    LEFT JOIN rent_model_web ON tovar_rent.tovar_rent_id = rent_model_web.model_id
                    LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id = tovar_rent.tovar_rent_id
                    WHERE razdel.url_razdel_name = '$razdelUrl' AND sub_razdel.url_sub_razdel_name = '$subRazdelUrl' AND tovar_rent.tovar_rent_id>0 AND tovar_rent_items.item_id>0
                        AND rent_model_web.web_id>0 $filterAddOnQuery
                    GROUP BY tovar_rent.tovar_rent_id
                    ORDER BY `free_num` DESC
                ";
    $result = $mysqli->query($query);
    if (!$result) {
      printf("Mysqli Errormessage: %s\n", $mysqli->error);
    }

    if ($result->num_rows < 1)
      return [];
    while ($row = $result->fetch_assoc()) {
      $rez[] = [$row['model_id'], $row['free_num'], $row['sort_n'],];
    }

    //model_ids for dop categories
    $subRazdel = SubRazdel::getByUrlName($subRazdelUrl);

    $query = "SELECT multi_web.model_id, SUM(IF(tovar_rent_items.status IN('to_rent', 't_bron'), 1, 0)) AS free_num, MAX(rent_model_web.sort_n) as sort_n
                    FROM multi_web
                    LEFT JOIN tovar_rent_cat ON tovar_rent_cat.tovar_rent_cat_id=multi_web.add_cat_id
                    LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id = multi_web.model_id
                    LEFT JOIN rent_model_web ON multi_web.model_id = rent_model_web.model_id
                    LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_id = tovar_rent_items.model_id
                    WHERE tovar_rent_cat.main_sub_razdel_id=" . $subRazdel->getIdSubRazdel() . " $filterAddOnQuery
                    GROUP BY multi_web.model_id
                    ORDER BY `free_num` DESC";
    $result = $mysqli->query($query);
    if (!$result) {
      printf("Mysqli Errormessage: %s\n", $mysqli->error);
    }
    if ($result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        $arrToInclude = [$row['model_id'], $row['free_num'], $row['sort_n'],];
        $toInclude = true;

        foreach ($rez as $r) {
          if ($r[0] == $arrToInclude[0]) {
            $toInclude = false;
            break;
          }
        }
        if ($toInclude) {
          $rez[] = $arrToInclude;
        }
      }


    }


    return $rez;
  }

  /**
   * @param $cat_id
   * @return array [model_id, free_num, sort_n];
   */
  public static function getModelIdsArrayByCategoryId($cat_id, $filter = false)
  {
    $rez = [];
    $mysqli = \bb\Db::getInstance()->getConnection();


    //filter add on
    $filterAddOnQuery = '';
    if ($filter && isset($filter['gender'])) {

      if ($filter['gender'] == 'm') {
        //$filterAddOnQuery.="AND (tovar_rent_items.sex IN('m', 'u') OR tovar_rent.m_sex IN('m', 'u'))";
        $filterAddOnQuery .= "AND (tovar_rent.m_sex IN('m', 'u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('m', 'u')))";
      } elseif ($filter['gender'] == 'f') {
        $filterAddOnQuery .= "AND (tovar_rent.m_sex IN('f', 'u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('f', 'u')))";
      } elseif ($filter['gender'] == 'u') {
        $filterAddOnQuery .= "AND (tovar_rent.m_sex IN('u') OR (tovar_rent.m_sex IN('', '0') AND tovar_rent_items.sex IN('u')))";
      }

    }
    if ($filter && isset($filter['rost']) && $filter['rost'] > 0) {
      $rost = $filter['rost'];
      $rostVariance = 3;

      $filterAddOnQuery .= " AND (tovar_rent_items.item_rost1-3)<='$rost' AND (tovar_rent_items.item_rost2+3)>='$rost'";
    }


    $query = "SELECT tovar_rent.tovar_rent_id as model_id, SUM(IF(tovar_rent_items.status IN('to_rent', 't_bron'), 1, 0)) AS free_num, MAX(rent_model_web.sort_n) as sort_n
                    FROM tovar_rent
                    LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id = tovar_rent.tovar_rent_id
                    LEFT JOIN rent_model_web ON tovar_rent.tovar_rent_id = rent_model_web.model_id
                    WHERE tovar_rent.tovar_rent_cat_id='$cat_id' AND tovar_rent_items.item_id>0 $filterAddOnQuery
                    GROUP BY tovar_rent.tovar_rent_id

                    ";
    //dd($query);
//        $query = "SELECT tovar_rent.tovar_rent_id as model_id, SUM(CASE WHEN tovar_rent_items.status = 'to_rent' THEN 1 ELSE 0 END) as free_num
//                    FROM tovar_rent
//                    LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id = tovar_rent.tovar_rent_id
//                    WHERE tovar_rent.tovar_rent_cat_id='$cat_id' AND tovar_rent_items.item_id>0
//                    GROUP BY tovar_rent.tovar_rent_id
//                    ORDER BY `free_num` DESC
//                    ";

    $result = $mysqli->query($query);
    if (!$result) {
      printf("Mysqli Errormessage: %s\n", $mysqli->error);
    }

    if ($result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        $rez[] = [$row['model_id'], $row['free_num'], $row['sort_n'],];
      }
    }

    $queryDop = "SELECT multi_web.model_id, SUM(IF(tovar_rent_items.status IN('to_rent', 't_bron'), 1, 0)) AS free_num, MAX(rent_model_web.sort_n) as sort_n
                    FROM multi_web
                    LEFT JOIN tovar_rent_items ON tovar_rent_items.model_id = multi_web.model_id
                    LEFT JOIN rent_model_web ON multi_web.model_id = rent_model_web.model_id
                    LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_id = tovar_rent_items.model_id
                    WHERE add_cat_id='$cat_id' AND tovar_rent_items.item_id>0 $filterAddOnQuery
                    GROUP BY multi_web.model_id
                    ";

    $resultDop = $mysqli->query($queryDop);
    if (!$resultDop) {
      printf("Mysqli Errormessage: %s\n", $mysqli->error, $queryDop);
    }
    if ($resultDop->num_rows > 0) {
      while ($row = $resultDop->fetch_assoc()) {
        $rez[] = [$row['model_id'], $row['free_num'], $row['sort_n'],];
      }
    }

    return $rez;
  }

  /**
   * @param $cat_id
   * @return array
   */
  public static function getModelIdsForCategoryId($cat_id)
  {
    $rez = [];
    $mysqli = \bb\Db::getInstance()->getConnection();


    $query = "SELECT tovar_rent.tovar_rent_id as model_id FROM tovar_rent WHERE tovar_rent.tovar_rent_cat_id='$cat_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      printf("Mysqli Errormessage: %s\n", $mysqli->error);
    }
    if ($result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        $rez[] = $row['model_id'];
      }
    }
    return $rez;
  }

  public static function getRentedOutDaysPercent(\DateTime $from, \DateTime $to, array $modelIds)
  {
    $tovNum1 = \bb\classes\tovar::getTovNumberForModelIdsForDate($from, $modelIds);
    $tovNum2 = \bb\classes\tovar::getTovNumberForModelIdsForDate($to, $modelIds);
    $tovNumAvg = ($tovNum1 + $tovNum2) / 2;
    if ($tovNumAvg == 0)
      return 0;

    $interval = $to->diff($from);
    $daysKolVo = $interval->days;
    if ($daysKolVo == 0)
      return 0;

    $daysForRent = $daysKolVo * $tovNumAvg;

    $daysRentedOut = Deal::getRentDaysNumForModelIds($from, $to, $modelIds);

    $ratio = $daysRentedOut / $daysForRent * 100;

    return $ratio;
  }

}
