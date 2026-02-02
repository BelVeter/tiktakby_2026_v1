<?php

namespace App\MyClasses;

use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\classes\Tariff;
use bb\classes\TariffModel;
use bb\classes\tovar;
use http\Env\Request;
use phpDocumentor\Reflection\Types\True_;
use PhpParser\Node\Expr\Array_;

class L2ModelWeb
{
  /**
   * @var Model
   */
  private $model;

  /**
   * @var ModelWeb
   */
  private $model_web;

  /**
   * @var Tariff
   */
  private $tarif;

  /**
   * @var Tariff[]
   */
  private $tarifs_all;

  /**
   * @var TariffModel
   */
  private $tarifModel;

  private $lang;
  /**
   * @var []
   */
  private $_availableAtOfficesIds;

  public function __construct($lang = '')
  {
    if ($lang == '')
      $lang = 'ru';

    $this->lang = $lang;
    $this->_availableAtOfficesIds = [];
  }

  /**
   * @return ModelWeb
   */
  public function getModelWeb()
  {
    return $this->model_web;
  }

  /**
   * @return TariffModel
   */
  public function getTarifModel(): TariffModel
  {
    return $this->tarifModel;
  }

  /**
   * @param TariffModel $tarifModel
   */
  public function setTarifModel(TariffModel $tarifModel): void
  {
    $this->tarifModel = $tarifModel;
  }


  /**
   * @param $id
   * @return L2ModelWeb|false
   * @throws \Exception
   */
  public static function getL2ModelWebById($id, $lang = '')
  {
    if ($lang == '')
      $lang = 'ru';

    $l2 = new self($lang);
    //if no web info - no need to print model
    if ($mw = ModelWeb::getByModelIdLangSafe($id, $lang)) {
      $l2->setModelWeb($mw);
    } else
      return false;
    if ($mw->getStatus() == 'not_show')
      return false;

    if ($m = Model::getById($id))
      $l2->setModel($m);

    if ($t = TariffModel::getChippestTarifByModelId($id))
      $l2->setTarif($t);
    else
      $l2->setTarif(new Tariff());

    $tars = TariffModel::getTariffsForModel($id);
    $l2->addTariffsAll($tars);

    $l2->setTarifModel(TariffModel::getTarifModelForModelId($id));

    $l2->loadOfficeAvailability();

    return $l2;
  }

  /**
   * @param $tars
   */
  public function addTariffsAll($tars)
  {
    $this->tarifs_all = $tars;
  }

  /**
   * @return Tariff[]
   */
  public function getTariffsAll()
  {
    return $this->tarifs_all;
  }

  /**
   * @return array|string[]
   */
  public function getHighLevelsOfRostArray($maxShowNumber = 3)
  {
    try {
      $rostSizeArray = tovar::getRostSizeArrayByModelId($this->getModelId());
      $rez = [];
      $count = 0;
      foreach ($rostSizeArray as $rs) {
        $count++;
        if ($count > $maxShowNumber) {
          $rez[$maxShowNumber - 1] .= '...';
          break;
        }
        $rez[] = $rs[1];
      }
      return $rez;
    } catch (\Exception $e) {
      return ['n.a.'];
    }
  }

  /**
   * @return bool
   */
  public function isL2AvailabilityVisible()
  {
    if ($this->getModelWeb()->getL2AvailabilityShow())
      return true;
    return false;
  }

  /**
   * @return float
   */
  public function getEstimatedValue()
  {
    if ($this->model && $this->model->agr_price > 0) {
      return $this->model->agr_price;
    }
    return 0;
  }

  /**
   * @return float
   */
  public function getDayTarifValue()
  {
    try {
      $t = self::getTariffsAll()[0];
      return round(($t->getTotalAmount() / $t->getDaysCalculatedNumber()), 2);
    } catch (\Exception $e) {
      return 0;
    }

  }

  /**
   * @param ModelWeb $mw
   */
  public function setModelWeb(ModelWeb $mw)
  {
    $this->model_web = $mw;
  }

  /**
   * @return bool
   */
  public function isKarnaval()
  {
    if ($this->model_web->isKarnaval())
      return true;
    else
      return false;
  }

  /**
   * @return bool
   */
  public function loadOfficeAvailability()
  {
    $rez = tovar::getFreeItemsOfficeArrayForModelId($this->getModelId());
    if ($rez && is_array($rez))
      $this->_availableAtOfficesIds = $rez;
    return true;
  }

  /**
   * @param $num
   * @return bool
   */
  public function isAvailableAtOffice($num)
  {
    if (is_array($this->_availableAtOfficesIds) && in_array($num, $this->_availableAtOfficesIds))
      return true;
    else
      return false;
  }


  /**
   * @return string
   */
  public function getL3Url($lang = '')
  {
    if ($lang == '')
      $lang = $this->lang;
    if ($lang == '')
      $lang = 'ru';
    $razd_name = \Illuminate\Support\Facades\Request::route('razdel');
    $cat = \Illuminate\Support\Facades\Request::route('cat');
    if ($cat == '') {
      return $this->model_web->getUrlPageAddress($lang);
    } else {
      return '/ru/prokat/' . \Illuminate\Support\Facades\Request::route('cat') . '/' . $this->model_web->getNameForUrl();
    }

  }

  public function setModel(Model $m)
  {
    $this->model = $m;
  }

  public function setTarif(Tariff $t)
  {
    $this->tarif = $t;
  }

  /**
   * @return int
   */
  public function getTarifLinePeriodDaysNumber()
  {
    if ($this->isKarnaval())
      return 1;
    else {
      switch ($this->model_web->getTarifLinePeriod()) {
        case 'day':
          return 1;
          break;
        case 'week':
          return 7;
          break;
        case 'month':
          return 30;
          break;
        default:
          return 28;
          break;
      }
    }
  }

  /**
   * @return int|mixed
   */
  public function getBaseDaysForPlusMinus()
  {
    if ($this->model_web->getTarifBaseDays() < 1) {
      if ($this->isKarnaval())
        return 1;
      else
        return 28;
    } else {
      return $this->model_web->getTarifBaseDays();
    }
  }

  public function getModelId()
  {
    return $this->model->model_id;
  }

  /**
   * @param $maxLettersNum
   * @return string
   */
  public function getName($maxLettersNum = 'all')
  {
    $name = $this->translateStringInside($this->model_web->getL2Name(), 'лет');
    if ($maxLettersNum == 'all')
      return $name;
    else {
      $lngth = mb_strlen($name);
      if ($lngth <= $maxLettersNum)
        return $name;
      else {
        return mb_substr($name, 0, $lngth - 1) . '...';
      }

    }
    //return $this->model_web->getL2Name();
  }

  /**
   * @param $maxLettersNum
   * @return string
   */
  public function getNameNoBr($maxLettersNum = 'all')
  {
    $name = $this->translateStringInside($this->model_web->getL2Name(), 'лет');
    $name = strip_tags($name);

    if ($maxLettersNum == 'all')
      return $name;
    else {
      $lngth = mb_strlen($name);
      if ($lngth <= $maxLettersNum)
        return $name;
      else {
        return mb_substr($name, 0, $maxLettersNum - 1) . '...';
      }

    }
  }

  public function getPicUrl()
  {
    return $this->model_web->getL2PicUrlAddress();
  }

  public function changePicUrlAddWeb($pic_url)
  {
    $this->model_web->l2_pic = $pic_url;
  }

  public function getPicAltText()
  {
    return $this->model_web->l2_alt;
  }

  public function getTarifValue()
  {
    return $this->tarif->rent_per_step;
  }

  public function getTarifStepName()
  {
    return $this->tarif->step;
  }

  public function getTarifPeriodsStartNum()
  {
    return $this->tarif->kol_vo;
  }

  public function getTotalAmmountForPeriod()
  {
    return $this->tarif->kol_vo * $this->tarif->rent_per_step;
  }

  /**
   * @return float
   */
  public function getMinDayTarifValue()
  {
    $tar = $this->tarif;
    $days = $tar->getPeriodInDays();
    if ($days < 1)
      $days = 1;
    $day = $tar->getTotalAmount() / $days;
    $day = round($day, 2);
    return $day;
  }

  public function translate($textRU)
  {
    if ($this->lang == 'ru' || $this->lang == '')
      return $textRU;

    $this->lang == 'lt' ? $langIndex = 0 : $langIndex = 1; //lt = 0, en = 1
    $translatedText = '';

    if (isset(self::$_translations[$textRU])) {
      $translatedText = self::$_translations[$textRU][$langIndex];
    }

    return $translatedText;
  }

  public function hasItemsAvailable($rezType = 'bool')
  {
    $freeItems = count($this->_availableAtOfficesIds);
    if ($freeItems > 0) {
      if ($rezType == 'bool')
        return true;
      else
        return 1;
    } else {
      if ($rezType == 'bool')
        return false;
      else
        return 0;
    }
  }

  /**
   * Get availability information for product display
   * @return array Array with keys: hasAvailability (bool), offices (Office[]), message (string)
   */
  public function getAvailabilityInfo()
  {
    $offices = [];

    // Get office objects for each available office ID
    if (is_array($this->_availableAtOfficesIds) && count($this->_availableAtOfficesIds) > 0) {
      foreach ($this->_availableAtOfficesIds as $officeId) {
        $office = \bb\models\Office::getOfficeByNumber($officeId);
        if ($office) {
          $offices[] = $office;
        }
      }
    }

    // Determine the message
    $message = 'Товар ожидается';
    if (count($offices) > 0) {
      $message = 'В наличии:';
    } else {
      // No available products, check for earliest return date
      $returnDate = tovar::getEarliestReturnDateForModelId($this->getModelId());
      if ($returnDate) {
        // Format date in Russian: "Товар ожидается 14 февраля"
        $months = [
          'января',
          'февраля',
          'марта',
          'апреля',
          'мая',
          'июня',
          'июля',
          'августа',
          'сентября',
          'октября',
          'ноября',
          'декабря'
        ];
        $day = $returnDate->format('j');
        $monthIndex = (int) $returnDate->format('n') - 1;
        $message = 'Товар ожидается ' . $day . ' ' . $months[$monthIndex];
      }
    }

    return [
      'hasAvailability' => count($offices) > 0,
      'offices' => $offices,
      'message' => $message
    ];
  }

  /**
   * @param $str
   * @param $pattern
   * @return array|mixed|string|string[]
   */
  public function translateStringInside($str, $pattern)
  {
    if ($this->lang == 'ru' || $this->lang == '')
      return $str;

    $this->lang == 'lt' ? $langIndex = 0 : $langIndex = 1; //lt = 0, en = 1

    $translatedPattern = '';

    if (isset(self::$_translations[$pattern])) {
      $translatedPattern = self::$_translations[$pattern][$langIndex];
    }
    $rez = str_replace($pattern, $translatedPattern, $str);

    return $rez;
  }

  /**
   * @return int|mixed
   */
  public function getSorN()
  {
    try {
      return $this->model_web->getSortNum();
    } catch (\Exception $e) {
      return 0;
    }
  }

  private static $_translations = [
    "Тариф за сутки" => [
      "Tarifas už dieną",
      "Tariff per day"
    ],
    "Суток" => [
      "Dienų",
      "Days"
    ],
    "в сутки" => [
      "per dieną",
      "per day"
    ],
    "в неделю" => [
      "per savaitę",
      "per week"
    ],
    "в месяц" => [
      "per mėnesį",
      "per month"
    ],
    "ВСЕГО" => [
      "IŠ VISO",
      "TOTAL"
    ],
    "Взять напрокат" => [
      "Nuoma",
      "Rent"
    ],
    "Забронировать" => [
      "Užsisakykite",
      "Order"
    ],
    "лет" => [
      "metų",
      "years"
    ],
  ];

}
