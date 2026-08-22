<?php

namespace App\MyClasses;

use bb\Base;
use bb\classes\Model;
use bb\classes\ModelWeb;
use bb\classes\Producer;
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

    if ($lang == '')
      $lang = 'ru';

    $this->lang = $lang;
    $this->breadcrumbs = [];
    $this->favoriteTovarsModels = [];

    switch ($lang) {
      case 'en':
        $this->breadcrumbs['Rental service'] = '/en/';
        break;
      case 'lt':
        $this->breadcrumbs['Nuomos paslauga'] = '/lt/';
        break;
      default:
        $this->breadcrumbs['Главная'] = '/ru';
        break;
    }


  }

  public function getCanonicalUrlBy()
  {
    return 'https://tiktak.by' . $this->modelWeb->getUrlPageAddress('ru');
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
    if (is_array($this->messages) && count($this->messages) > 0)
      return true;
    else
      return false;
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
  public function getMetaDescription()
  {
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
    if ($this->model) {
      $producer = Producer::getByName($this->model->getProducer());
      if ($producer && $producer->getLogo() !== '') {
        return $producer->getLogo();
      }
    }

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
    if ($this->modelWeb->isKarnaval())
      return true;
    else
      return false;
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
   * @return array  [{question, answer}, ...]
   */
  public function getFaqArray(): array
  {
    return $this->modelWeb->getFaqArray();
  }

  /**
   * @param $urlName
   * @return L3Page
   */
  public static function getPageByUrlName($urlName, $lang = '', $razdelUrlCode, $currentSubRazdelUrlCode)
  {
    if ($lang == '')
      $lang = 'ru';

    $p = new self($lang);
    $p->lang = $lang;

    $p->modelWeb = ModelWeb::getByUrlNameLangSafe($urlName, $lang);

    if (!$p->modelWeb) {
      return null;
    }

    $p->tariffs = TariffModel::getTarifModelForModelId($p->modelWeb->model_id);
    $p->model = Model::getById($p->modelWeb->model_id);

    // Caching the recommendation list for 60 minutes
    // Key depends on model, razdel, and subrazdel (since context matters)
    $cacheKey = 'rec_view_ids_m' . $p->modelWeb->model_id . '_r' . $razdelUrlCode . '_s' . $currentSubRazdelUrlCode;

    $favModelIds = \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($p, $razdelUrlCode, $currentSubRazdelUrlCode) {
      return Model::getModelIdsArrayForFavoriteTovSlider($p->model, $razdelUrlCode, $currentSubRazdelUrlCode, 16);
    });

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
  public function getCollateralAmmount()
  {
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
    } else {
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
  public function getTarifLinePeriodDaysNumber()
  {
    if ($this->isKarnaval())
      return 1;
    else {
      switch ($this->modelWeb->getTarifLinePeriod()) {
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
  public function getBaseDaysForPlusMinus()
  {
    if ($this->modelWeb->getTarifBaseDays() < 1) {
      if ($this->isKarnaval())
        return 1;
      else
        return 3;
    } else {
      return $this->modelWeb->getTarifBaseDays();
    }
  }

  /**
   * @return int|mixed
   */
  public function getBaseDaysNumForTarifCalc()
  {
    if ($this->isKarnaval())
      return 1;
    if ($this->modelWeb->getTarifBaseDays() < 1)
      return 3;
    else
      return $this->modelWeb->getTarifBaseDays();
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

  /**
   * Builds the Product Schema.org JSON-LD string for the product page (L3).
   *
   * @return string
   */
  public function getSchemaJsonLd()
  {
      $l3Url         = $this->getCanonicalUrlBy();
      $l3PrimaryStep = $this->modelWeb->getTarifLinePeriod() ?: 'week';

      // Build Schema.org offers via TariffModel — filtered by primary rental period
      $l3SchemaOffers = $this->getTarifModel() ? $this->getTarifModel()->getSchemaOffers($l3PrimaryStep, $l3Url) : null;
      if ($l3SchemaOffers) {
          if ($this->model->hasFreeItems()) {
              $availability = 'https://schema.org/InStock';
              $availabilityStarts = null;
          } else {
              $availability = 'https://schema.org/BackOrder';
              $returnDate = \bb\classes\tovar::getEarliestReturnDateForModelId((int)$this->model->model_id);
              $availabilityStarts = $returnDate ? $returnDate->format('Y-m-d') : null;
          }
          if (isset($l3SchemaOffers['@type'])) {
              $l3SchemaOffers['availability'] = $availability;
              if ($availabilityStarts) $l3SchemaOffers['availabilityStarts'] = $availabilityStarts;
          } else {
              foreach ($l3SchemaOffers as &$offer) {
                  $offer['availability'] = $availability;
                  if ($availabilityStarts) $offer['availabilityStarts'] = $availabilityStarts;
              }
              unset($offer);
          }
      } else {
          return ''; // OPTION B: No offers -> No Product Schema
      }

      // Images: all slider photos as absolute URLs
      $l3Images = [];
      foreach ($this->getPicsForSlider() as $pic) {
          $src = $pic->getSrc();
          if ($src) {
              $l3Images[] = strpos($src, 'http') === 0 ? $src : 'https://tiktak.by' . $src;
          }
      }

      // Description: prefer main_descr (943/980 products), fall back to meta_description
      $l3RawDesc = $this->getDescription(); // = modelWeb->main_descr (HTML)
      if (!$l3RawDesc) {
          $l3RawDesc = $this->getMetaDescription();
      }
      $l3Description = trim(html_entity_decode(strip_tags($l3RawDesc), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

      if (!$l3Description) {
          $minOffer = $this->getTarifModel() ? $this->getTarifModel()->getSchemaMinOffer($l3Url) : null;
          $minPrice = $minOffer && isset($minOffer['price']) ? $minOffer['price'] : '';
          $priceText = $minPrice ? " от {$minPrice} BYN" : '';
          $productName = html_entity_decode(strip_tags($this->getL3MainName()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
          $l3Description = "Прокат " . $productName . " в Минске" . $priceText . ".";
      }

      // Brand: only output if the producer field contains a real brand name
      $l3Producer   = $this->model ? (string)$this->model->getProducer() : '';
      $l3BrandValid = $l3Producer && strlen($l3Producer) <= 50
          && !preg_match('/\d+\s*(см|кг|лет|мес|×)/iu', $l3Producer);

      $l3Schema = [
          '@context' => 'https://schema.org',
          '@type'    => 'Product',
          '@id'      => $l3Url,
          'url'      => $l3Url,
          'name'     => html_entity_decode(strip_tags($this->getL3MainName()), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
      ];

      if ($l3Description) {
          $l3Schema['description'] = $l3Description;
      }

      if (count($l3Images) === 1) {
          $l3Schema['image'] = $l3Images[0];
      } elseif (count($l3Images) > 1) {
          $l3Schema['image'] = $l3Images;
      }

      if ($l3SchemaOffers) {
          $l3Schema['offers'] = $l3SchemaOffers;
      }

      if ($l3BrandValid) {
          $l3Schema['brand'] = ['@type' => 'Brand', 'name' => $l3Producer];
      } else {
          $l3Schema['brand'] = ['@type' => 'Brand', 'name' => 'Без бренда'];
      }

      $l3AdditionalProps = [
          ['@type' => 'PropertyValue', 'name' => 'Доставка по Минску', 'value' => 'бесплатно'],
      ];

      $l3AgeFrom = $this->model ? (int)$this->model->getAgeFrom() : 0;
      $l3AgeTo   = $this->model ? (int)$this->model->getAgeTo()   : 0;
      if ($l3AgeFrom > 0 && $l3AgeTo > 0) {
          $l3AdditionalProps[] = [
              '@type' => 'PropertyValue',
              'name'  => 'Возраст',
              'value' => 'от ' . self::formatAgeMonths($l3AgeFrom) . ' до ' . self::formatAgeMonths($l3AgeTo),
          ];
      }

      if ($this->isKarnaval()) {
          $heightStr = self::formatHeightRanges(
              \bb\classes\Model::getHeightRangeForModelId((int)$this->modelWeb->getModelId())
          );
          if ($heightStr) {
              $l3AdditionalProps[] = [
                  '@type' => 'PropertyValue',
                  'name'  => 'Рост ребёнка',
                  'value' => $heightStr,
              ];
          }
      }

      $l3Schema['additionalProperty'] = $l3AdditionalProps;

      $output = '<script type="application/ld+json">' . json_encode($l3Schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

      // FAQPage JSON-LD (if product page has FAQ)
      $l3FaqItems = $this->getFaqArray();
      if (!empty($l3FaqItems)) {
          $l3FaqEntities = [];
          foreach ($l3FaqItems as $l3FaqItem) {
              if (!isset($l3FaqItem['question']) || !isset($l3FaqItem['answer']) || trim($l3FaqItem['question']) === '' || trim($l3FaqItem['answer']) === '') continue;
              $l3FaqEntities[] = [
                  '@type' => 'Question',
                  'name'  => trim($l3FaqItem['question']),
                  'acceptedAnswer' => [
                      '@type' => 'Answer',
                      'text'  => trim(nl2br(strip_tags($l3FaqItem['answer'], '<a><b><strong><i><em><ul><li><ol><p><br><h1><h2><h3><h4><h5><h6>'))),
                  ],
              ];
          }
          if (!empty($l3FaqEntities)) {
              $l3FaqSchema = [
                  '@context'   => 'https://schema.org',
                  '@type'      => 'FAQPage',
                  'mainEntity' => $l3FaqEntities,
              ];
              $output .= '<script type="application/ld+json">' . json_encode($l3FaqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
          }
      }

      return $output;
  }

  private static function formatHeightRanges(array $ranges): string
  {
      if (empty($ranges)) return '';
      if (count($ranges) === 1) {
          return 'от ' . $ranges[0][0] . ' до ' . $ranges[0][1] . ' см';
      }
      return implode(', ', array_map(fn($r) => $r[0] . '-' . $r[1], $ranges)) . ' см';
  }

  private static function formatAgeMonths(int $months): string
  {
      if ($months < 12) return $months . ' мес';
      $years = intdiv($months, 12);
      $rem   = $months % 12;
      if ($rem === 0) {
          if ($years === 1) return '1 год';
          if ($years <= 4) return $years . ' года';
          return $years . ' лет';
      }
      return $years . ' г ' . $rem . ' мес';
  }

}
