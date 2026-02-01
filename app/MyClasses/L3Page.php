<?php

namespace App\MyClasses;

use bb\Base;
use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\classes\Tariff;
use bb\classes\TariffModel;
use bb\classes\tovar;
use bb\Db;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Utils;

class L3Page
{
  public $lang;
  /**
   * @var ModelWeb
   */
  public $modelWeb;

  /**
   * @var TariffModel
   */
  public $tariffs;

  /**
   * @var Model
   */
  public $model;

  /**
   * @var array
   */
  private $messages;

  /**
   * @var array
   */
  private $breadcrumbs;

  /**
   * @var Pic[]
   */
  private $addPics;

  /**
   * @var L2ModelWeb[]
   */
  private $favoriteTovarsModels;

  public function __construct($lang = '')
  {

    if ($lang=='') $lang = 'ru';

    $this->lang=$lang;
    $this->breadcrumbs = [];
    $this->favoriteTovarsModels = [];

    switch ($lang){
      case 'en':
        $this->breadcrumbs['Rental service'] = '/en/';
        break;
      case 'lt':
        $this->breadcrumbs['Nuomos paslauga'] = '/lt/';
        break;
      default:
        $this->breadcrumbs['Главная'] = '/ru/';
        break;
    }


  }

  public function getCanonicalUrlBy()
  {
    return $this->modelWeb->getUrlPageAddress('ru');
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
   * @return L2ModelWeb[]
   */
  public function getFavoriteTovarsModels(): array
  {
    return $this->favoriteTovarsModels;
  }

  /**
   * @param L2ModelWeb[] $favoriteTovarsModels
   */
  public function setFavoriteTovarsModels(array $favoriteTovarsModels): void
  {
    $this->favoriteTovarsModels = $favoriteTovarsModels;
  }

  /**
   * @param L2ModelWeb $mw
   * @return void
   */
  public function addFavoriteTovModelWeb(L2ModelWeb $mw): void
  {
    $this->favoriteTovarsModels[] = $mw;
  }


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

  public function getItemDogPrice()
  {
    return $this->model->agr_price;
  }

  /**
   * @return string
   */
  public function getPageTitle()
  {
    return $this->modelWeb->getPageTitle();
  }

  /**
   * @return mixed
   */
  public function getMetaDescription(){
    return $this->modelWeb->getMetaDescription();
  }

  public function getL3MainName()
  {
    return $this->modelWeb->getItemNameMain();
  }

  public function getMainBigPicUrl()
  {
    return $this->modelWeb->getMPicBigUrlAddress();
  }

  public function getMainSmallPicUrl()
  {
    return $this->modelWeb->getMPicBigUrlAddress();
  }

  public function getMainPicAlt()
  {
    return $this->modelWeb->getMPicAlt();
  }

  public function hasLogo()
  {
    if ($this->modelWeb->logo == '') {
      return false;
    } else {
      return true;
    }

  }

  public function getProducerLogoUrl()
  {
    return $this->modelWeb->getLogoUrlAddress();
  }


  /**
   * @return array|void
   */
  public function getRostSizeArray()
  {
    return tovar::getRostSizeArrayByModelId($this->getModelId());
  }

  /**
   * @return bool
   */
  public function isKarnaval()
  {
    if ($this->modelWeb->isKarnaval()) return true;
    else return false;
  }

  public function getModelPrice()
  {
    return $this->model->agr_price;
  }

  public function getModelId()
  {
    return $this->modelWeb->getModelId();
  }

  /**
   * @return Tariff[]
   */
  public function getTarifs($order = 1)
  {
    //return $this->tariffs->getTarifs();
    $rez = $this->tariffs->getTarifs();
    if ($order != 1) {
      usort($rez, function ($a, $b) {
        return $b->getDaysCalculatedNumber() - $a->getDaysCalculatedNumber();
      });
    }

    return $rez;
  }

  /**
   * @return TariffModel
   */
  public function getTarifModel()
  {
    return $this->tariffs;
  }

  /**
   * @return Tariff
   */
  public function getSmallestTarif()
  {
    $tars = $this->getTarifs(-1);
    $lastIndex = count($tars) - 1;

    return $tars[$lastIndex];
  }


  /**
   * @return mixed
   */
  public function getDescription()
  {
    return $this->modelWeb->main_descr;
  }

  /**
   * @param $urlName
   * @return L3Page
   */
  public static function getPageByUrlName($urlName, $lang = '', $razdelUrlCode, $currentSubRazdelUrlCode)
  {
    if ($lang == '') $lang = 'ru';

    $p = new self($lang);
    $p->lang = $lang;

    $p->modelWeb = ModelWeb::getByUrlNameLangSafe($urlName, $lang);
    $p->tariffs = TariffModel::getTarifModelForModelId($p->modelWeb->model_id);
    $p->model = Model::getById($p->modelWeb->model_id);

    $favModelIds = Model::getModelIdsArrayForFavoriteTovSlider($p->model, $razdelUrlCode, $currentSubRazdelUrlCode, 16);

    //dd($favModelIds);

    if (count($favModelIds) > 0) {
      foreach ($favModelIds as $mid) {
        $newMW = L2ModelWeb::getL2ModelWebById($mid, $lang);
        if ($newMW) {
          $p->addFavoriteTovModelWeb($newMW);
        }
      }
    }

    return $p;
  }

  /**
   * @return mixed
   */
  public function getCollateralAmmount(){
    return $this->model->getCollateral();
  }

  /**
   * @param $cat_url_name
   */
  public function addBreadCrumbsCat($cat_url_name)
  {
    $m = CatMenuItem::getItemByUrlName($cat_url_name);
    $this->addBreadCrumbs($m->getCatNameText(), $m->getUrl());
  }

  /**
   * @param $name
   * @param $url
   * @return bool
   */
  public function addBreadCrumbs($name, $url)
  {
    $this->breadcrumbs[$name] = $url;
    return true;
  }

  /**
   * @return array
   */
  public function getBreadCrumbsArray()
  {
    if ($this->modelWeb->getBreadcrumbsName() != '') {
      $this->addBreadCrumbs($this->modelWeb->getBreadcrumbsName(), '');
    }
    else{
      $this->addBreadCrumbs($this->getL3MainName(), '');
    }
    return $this->breadcrumbs;
  }

  /**
   * @return Pic[]
   */
  public function getPicsForSlider()
  {

    if ($this->addPics != null) {
      return $this->addPics;
    }

    $rez = array();

    $pic = new Pic();
    $pic->setSrcSmall($this->modelWeb->getMPicBigUrlAddress());
    $pic->setSrcBig($this->modelWeb->getMPicBigUrlAddress());
    $pic->setAlt($this->modelWeb->getMPicAlt());
    $pic->setTitle($this->modelWeb->getMATitle());

    $rez[] = $pic;

    if ($this->modelWeb->getDopPicturesNum() > 0) {
      foreach ($this->modelWeb->getDopPictures() as $dp) {
        $pic = new Pic();
        $pic->setSrcBig($dp->getSrc());
        $pic->setSrcSmall($dp->getSrc());
        $pic->setAlt($dp->getAlt());
        $pic->setTitle($dp->getTitle());

        $rez[] = $pic;
      }
    }


    $this->addPics = $rez;

    return $rez;
  }

  /**
   * @return int
   */
  public function getPicsSliderNum()
  {
    return count($this->getPicsForSlider());
  }

  public function getFromDateHtml()
  {
    $d = new \DateTime();
    return $d->format("Y-m-d");
  }

  public function getToDateHtml()
  {
    $d = new \DateTime();
    $daysModify = $this->getSmallestTarif()->getDaysCalculatedNumber();
    $d->modify('+' . $daysModify . ' days');
    return $d->format("Y-m-d");
  }

  public function getHtmlTarifInputs()
  {
    return \bb\classes\TariffModel::getHtmlTarifInputs($this->getModelId());
  }

  /**
   * @return int
   */
  public function getTarifLinePeriodDaysNumber(){
    if ($this->isKarnaval()) return 1;
    else {
      switch ($this->modelWeb->getTarifLinePeriod()){
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
          return 7;
          break;
      }
    }
  }

  /**
   * @return int|mixed
   */
  public function getBaseDaysForPlusMinus(){
    if ($this->modelWeb->getTarifBaseDays()<1) {
      if ($this->isKarnaval()) return 1;
      else return 3;
    }
    else {
      return $this->modelWeb->getTarifBaseDays();
    }
  }

  /**
   * @return int|mixed
   */
  public function getBaseDaysNumForTarifCalc(){
    if ($this->isKarnaval()) return 1;
    if ($this->modelWeb->getTarifBaseDays()<1) return 3;
    else return $this->modelWeb->getTarifBaseDays();
  }

  public function translate($textRU){
    if ($this->lang=='ru' || $this->lang=='') return $textRU;

    $this->lang == 'lt' ? $langIndex = 0 : $langIndex = 1; //lt = 0, en = 1
    $translatedText='';

    if (isset(self::$_translations[$textRU])){
      $translatedText=self::$_translations[$textRU][$langIndex];
    }

    return $translatedText;
  }

  /**
   * @param $str
   * @param $pattern
   * @return array|mixed|string|string[]
   */
  public function translateStringInside($str, $pattern){
    if ($this->lang=='ru' || $this->lang=='') return $str;

    $this->lang == 'lt' ? $langIndex = 0 : $langIndex = 1; //lt = 0, en = 1

    $translatedPattern = '';

    if (isset(self::$_translations[$pattern])){
      $translatedPattern=self::$_translations[$pattern][$langIndex];
    }
    $rez = str_replace($pattern, $translatedPattern, $str);

    return $rez;
  }

  private static $_translations = [
    "Оценочная стоимость" => [
      "Apskaičiuota vertė",
      "Estimated value"
    ],
    "без учета износа" => [
      "be nusidėvėjimo",
      "excl. depreciation"
    ],
    "Выбран размер" => [
      "Pasirinktas dydis",
      "Size selected"
    ],
    "Рост" => [
      "Vaiko ūgis",
      "Height"
    ],
    "Возраст" => [
      "Amžius",
      "Age"
    ],
    "Стоимость проката" => [
      "Nuomos kaina",
      "Rental price"
    ],
    "сутки" => [
      "diena",
      "day"
    ],
    "суток" => [
      "dienos",
      "days"
    ],
    "неделя" => [
      "savaitė",
      "week"
    ],
    "недели" => [
      "savaitės",
      "weeks"
    ],
    "месяц" => [
      "mėnuo",
      "month"
    ],
    "месяца" => [
      "mėnesiai",
      "months"
    ],
    "месяцeв" => [
      "mėnesiai",
      "months"
    ],
    "Взять напрокат" => [
      "Nuoma",
      "Rent"
    ],
    "Вам может понравится" => [
      "Jums gali patikti",
      "You might like"
    ],
    "Подробнее" => [
      "Daugiau",
      "More info"
    ],
    "Для бронирования, введите ваши данные" => [
      "Norėdami atlikti užsakymą, įveskite savo duomenis",
      "To make the reservation, please enter your details"
    ],
    "Ваше имя" => [
      "Jūsų vardas ir pavardė",
      "Name"
    ],
    "Телефон" => [
      "Telefono numeris",
      "Phone number"
    ],
    "Дополнительная информация" => [
      "Daugiau informacijos",
      "Additional information"
    ],
    "Забронировать" => [
      "Rezervacija",
      "Place order"
    ],
    "Отмена" => [
      "Atšaukimas",
      "Cancel"
    ],
    "Выберите период или количество суток проката" => [
      "Pasirinkite nuomos laikotarpį arba dienų skaičių",
      "Choose a rental period or number of days"
    ],
    "выдача" => [
      "pradėti",
      "start"
    ],
    "возврат" => [
      "grąžina",
      "end"
    ],
    "Тариф за сутки" => [
      "Tarifas už dieną",
      "Tariff per day"
    ],
    "Всего за период" => [
      "Iš viso",
      "Total"
    ],
    "лет" => [
      "metų",
      "years"
    ],
  ];

}
