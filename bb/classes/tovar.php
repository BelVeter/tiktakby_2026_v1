<?php
namespace bb\classes;

use bb\Base;
use bb\Db;
use bb\models\Office;
use bb\classes\bron;

class tovar
{

  public $mysqli;
  private $db_hostname;
  private $db_username;
  private $db_password;
  private $db_database;

  public $item_id;
  public $cat_id;
  public $producer;
  public $model_id;
  public $item_n;
  public $item_inv_n;
  public $sex;
  public $item_size;
  public $real_item_size;
  public $item_rost1;
  public $item_rost2;
  public $item_set;
  public $buy_date;
  public $buy_price;
  public $buy_price_cur;
  public $exch_to_byr;
  public $seller;
  public $item_info;
  public $cr_ch_date;
  public $user;
  public $status; //rented_out, to_rent, bron, t_bron, to_deliver, not_to_rent, repair,
  public $active_deal_id;
  public $item_color;
  public $item_place;
  public $br_time;
  public $state;
  public $to_move;
  public $qr_yn;
  public $collateral;

  public $user_id;

  //for del
  public $out_status; //... sold, no_return, bron_delete
  public $sell_amount_byr;
  public $rent_payment_type;
  public $sell_amount_usd;
  public $item_del_info;


  //вывод сообщения при необходимости
  public $return_info;

  //инфо по категории
  public $cat_dog_name;
  public $cat_name;

  //инфо мо модели !!! потом добавить поля???
  public $model_name;
  public $model_color;
  public $model_set;
  public $model_addr;

  private $isDirtyChecked;
  private $isDirty;

  //arch info
  public $item_arch_id;
  public $arch_date;
  public $arch_time;
  public $arch_who_id;
  //public $out_status; --- see above/dublication
  public $out_sell_tbyr;
  public $out_sell_kassa;
  public $out_sell_usd;
  public $out_info;



  public function getBuyPriceBYN()
  {
    //exchtobyr= exch to usd
    return $this->buy_price / $this->exch_to_byr * 3.2;
  }

  public function getModelId()
  {
    return $this->model_id;
  }

  /**
   * @return \DateTime
   */
  public function getBuyDate()
  {
    $d = new \DateTime();
    $d->setTimestamp($this->buy_date);
    return $d;
  }

  public function getSeller()
  {
    return $this->seller;
  }

  public function getUserName()
  {
    return $this->user;
  }


  public function getMonthsToPayBack()
  {
    $monthSales = $this->getTariffModel()->getAmmountForDaysPeriod(30);
    $monthSales = $monthSales / 30 * 30.5;

    if (!$monthSales > 0)
      return -1;

    $ms = $this->getBuyPriceBYN() / $monthSales;

    return round($ms, 1);
  }


  public function getInvN()
  {
    return $this->item_inv_n;
  }


  /**
   * @param $arr
   */
  public function getTovarArchAddInfoFromArray($arr)
  {
    $this->item_arch_id = $arr['item_arch_id'];
    $this->arch_date = $arr['arch_date'];
    $this->arch_time = $arr['arch_time'];
    $this->arch_who_id = $arr['arch_who_id'];
    $this->out_status = $arr['out_status'];
    $this->out_sell_tbyr = $arr['out_sell_tbyr'];
    $this->out_sell_kassa = $arr['out_sell_kassa'];
    $this->out_sell_usd = $arr['out_sell_usd'];
    $this->out_info = $arr['out_info'];
  }

  /**
   * @param $arr
   * @return tovar
   */
  public static function getArchTovarFromArray($arr)
  {
    $t = self::getTovarFromArray($arr);
    $t->getTovarArchAddInfoFromArray($arr);
    return $t;
  }

  public function getBarCodeHTML()
  {

    $width = $height = 60;
    $base_url = '/bb/qr_png.php?text=' . urlencode($this->item_inv_n);

    return '
        <div class="div_cont align-content-center">
            <div class="item_number" ' . ($this->isKarnaval() ? '' : 'style="font-size:16px;"') . '>' . ($this->isKarnaval() ? $this->item_n : $this->invNPrint('-')) . '</div>
            <div class="site">www.tiktak.by</div>
            <img class="gif_c" src="' . $base_url . '" alt="QR code">
            <div class="name">
                ' . $this->getNameForBarCode() . '
            </div>
        </div>
        ';
  }

  /**
   * @return mixed
   */
  public function getRostFrom()
  {
    return $this->item_rost1;
  }

  /**
   * @return mixed
   */
  public function getRostTo()
  {
    return $this->item_rost2;
  }

  /**
   * @return mixed
   */
  public function getItemSize()
  {
    return $this->item_size;
  }


  public function getStatusText()
  {
    $st_color = '';

    switch ($this->status) {//rented_out, to_rent, bron, t_bron, to_deliver, not_to_rent, repair
      case 'to_rent':
      case 't_bron':
        $val = 'Свободен';
        $st_color = "color: green;";
        break;
      case 'rented_out':
        $val = 'На руках';
        break;
      case 'bron':
        $val = 'Бронь';
        $st_color = "color: red;";
        break;
      case 'to_deliver':
        $val = 'Доставка';
        break;
      case 'not_to_rent':
        $val = 'Не для сдачи.';
        break;
      case 'repair':
        $val = 'В ремонте';
        break;
      default:
        $val = 'статус не определен';
        break;
    }

    //$rez='<'.$tag.' style="'.$st_color.' '.$style.'">'.$val.'</'.$tag.'>';
    return $val;

  }

  public function isTmpBronValid()
  {
    if (($this->br_time + 7 * 60 - time()) < 0) {
      return true;
    } else {
      return false;
    }
  }

  public function getStatusTextColor()
  {
    $st_color = '';
    switch ($this->status) {//rented_out, to_rent, bron, t_bron, to_deliver, not_to_rent, repair
      case 'to_rent':
      case 't_bron':
        $st_color = "green;";
        break;
      case 'rented_out':
        break;
      case 'bron':
        $st_color = "red;";
        break;
      case 'to_deliver':
        $st_color = 'blue';
        break;
      case 'not_to_rent':
        $st_color = 'red';
        break;
      case 'repair':
        break;
      default:
        $st_color = 'yellow';
        break;
    }

    return $st_color;

  }

  public function getPicAddress()
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT l2_pic FROM rent_model_web WHERE model_id='$this->model_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $row = $result->fetch_assoc();

    return $row['l2_pic'];
  }

  public function isDirty()
  {
    if ($this->isDirtyChecked != 1) {
      $bron = new \bb\classes\bron();
      $bron->inv_n = $this->item_inv_n;
      $bron->stirka();
      if ($bron->in_stirka == 1) {
        $this->isDirty = 1;
      }
      $this->isDirtyChecked = 1;
    }

    if ($this->isDirty == 1) {
      return true;
    } else {
      return false;
    }
  }

  public function isDirtyText()
  {
    if ($this->isDirty()) {
      return '<br><h3 style="color: red; font-weight: bold; text-align: center;">Грязный</h3>';
    } else {
      return '';
    }
  }

  public function invNPrint($separator = '')
  {
    if ($separator != '') {
      $rez = $output = substr($this->item_inv_n, 0, 3) . $separator . substr($this->item_inv_n, 3);
    } else {
      $rez = $this->item_inv_n;
    }
    return $rez;
  }

  public function getFullName()
  {
    $rez = $this->cat_dog_name . ' ' . $this->model_name . ' (' . $this->producer . ')';

    return $rez;
  }

  public function isDirtyPic($style = '')
  {
    $st = '';
    $rez = '';
    if ($this->isDirty()) {
      if ($style != '') {
        $st = ' style="' . $style . '"';
      }

      $rez = '<img src="/bb/clean.png" ' . $st . '>';
    }
    return $rez;
  }

  public function getColor()
  {
    if ($this->model_color == '' || $this->model_color == 0) {
      return null;
    } elseif ($this->model_color == 'multicolor') {
      return 'цвет: ' . $this->item_color;
    } else {
      return 'цвет: ' . $this->model_color;
    }
  }


  /**
   * @return Tariff[]|bool
   * @throws \Exception
   */
  public function getTariffs()
  {
    $tars = TariffModel::getTariffsForModel($this->model_id);
    return $tars;
  }

  /**
   * @return TariffModel|bool
   * @throws \Exception
   */
  public function getTariffModel()
  {
    $tars = TariffModel::getTarifModelForModelId($this->model_id);
    return $tars;
  }

  public function getSet()
  {
    if ($this->item_set != '' && $this->item_set != 0) {
      return $this->item_set;
    } else {
      return $this->model_set;
    }
  }

  public function getSizeRost()
  {
    $rez = '';
    if ($this->item_size != '' || $this->real_item_size != '') {
      $rez .= ', размер: ';
      if ($this->real_item_size != '') {
        $rez .= $this->real_item_size;
        if ($this->item_size != '') {
          $rez .= '(' . $this->item_size . ')';
        }
      } else {
        if ($this->item_size != '') {
          $rez .= $this->item_size;
        }
      }

    }
    if ($this->item_rost1 != '' && $this->item_rost1 > 0) {
      $rez .= ', рост: ' . $this->item_rost1 . '-' . $this->item_rost2;
    }
    return $rez;
  }


  public function getBarCodeHTML1()
  {

    $width = $height = 60;
    $base_url = '/bb/qr_png.php?text=' . urlencode($this->item_inv_n) . '&size=2.5';

    return '
        <div class="inv_n">' . $this->item_inv_n . '</div>
        <img class="qr_gif2" src="' . $base_url . '" alt="QR code">
        ';
  }

  public function getBarCodeHTML2()
  {
    return '
	        <div class="qr_name_2">' . $this->getNameForBarCodeNonKarn() . '</div>
	        <div class="qr_operators"><img src="ph_nk.jpg" style="width: 80px;"></div>
	    ';
  }

  /**
   * @return string
   */
  public function krBasaQr()
  {
    $rez = '';
    if ($this->qr_yn == 1) {
      $rez_val = 'QR есть';
    } else
      $rez_val = 'нет QR';

    $rez .= '<a href="#" onclick="qr_send(\'' . $this->item_id . '\'); return false;">' . $rez_val . '</a>';

    $rez .= '
        <form id="qr_form_' . $this->item_id . '" action="kr_baza_new.php" method="post" style="display: none;">
            <input type="hidden" name="qr_yn" id="qr_yn_' . $this->item_id . '" value="' . $this->qr_yn . '">
            <input type="hidden" name="item_id" value="' . $this->item_id . '">
            <input type="hidden" name="action" value="qr_change">
        </form>

        ';

    return $rez;
  }

  public function statusText()
  {
    switch ($this->status) {
      case 'to_rent':
        return 'Свободно';
        break;
      case 'rented_out':
        return 'На руках';
        break;
      case 'bron':
        return 'Бронь';
        break;

      default:
        return 'не определено:' . $this->status;
        break;
    }
  }

  public function qrUpdate()
  {
    $mysqli = Db::getInstance()->getConnection();

    $query_upd = "UPDATE tovar_rent_items SET `qr_yn`='$this->qr_yn' WHERE item_id='$this->item_id'";
    $result_upd = $mysqli->query($query_upd);
    if (!$result_upd) {
      die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

  }

  /**
   * @param $inv_n
   * @param $qr_yn
   * @return tovar
   */
  public static function getTovForQr($item_id, $qr_yn)
  {
    $tov = new self();
    $tov->item_id = $item_id;
    $tov->qr_yn = $qr_yn;

    return $tov;
  }

  /**
   * @return string
   */
  public function getNameForBarCode()
  {
    $rez = '';

    if ($this->isKarnaval()) {
      $rez .= $this->model_name . '<br>';
      if ($this->real_item_size != '' && $this->real_item_size != NULL) {
        $rez .= 'р-р ' . $this->real_item_size . '<br>';
      }
      if ($this->item_size != '' && $this->item_size != NULL) {
        $rez .= $this->item_size . '<br>';
      }
      if ($this->item_rost1 > 0) {
        $rez .= $this->item_rost1 . ' - ' . $this->item_rost2 . ' см.<br>';
      }
    } else {
      $rez .= $this->producer . ', ' . $this->model_name;
    }

    return $rez;

  }
  /**
   * @return string
   */
  public function getNameForBarCodeNonKarn()
  {
    $rez = '';

    $rez .= $this->cat_dog_name;
    $rez .= ', ' . $this->producer;

    $str_length = 24;

    $char_n = mb_strlen($rez);

    if ($char_n > $str_length) {
      $rez = mb_substr($rez, 0, $str_length, 'UTF-8');
    }

    return $rez;

  }

  public function getContractName()
  {
    if ($this->model_addr != '') {
      return $this->model_addr;
    } else {
      return $this->cat_dog_name;
    }
  }

  function __construct($mysqli = NULL)
  {//передаем строчку (массив) из mysql запроса

    $this->mysqli = \bb\Db::getInstance()->getConnection();
    if (isset($_SESSION['user_id']))
      $this->user_id = $_SESSION['user_id'];

  }// end of construct

  /**
   * @param $cat_id
   * @return tovar[]
   */
  public static function getTovarsByCategory($cat_id, array $params)
  {
    $srch_dop = '';

    if ($params['place'] != 'all') {
      $srch_dop .= " AND tovar_rent_items.item_place='" . $params['place'] . "'";
    }
    if ($params['free'] == 'free') {
      $srch_dop .= " AND tovar_rent_items.status!='rented_out'";
    }
    if ($params['free'] == 'not_free') {
      $srch_dop .= " AND tovar_rent_items.status='rented_out'";
    }
    if ($params['qr'] == 'qr') {
      $srch_dop .= " AND tovar_rent_items.qr_yn=1";
    }
    if ($params['qr'] == 'no_qr') {
      $srch_dop .= " AND tovar_rent_items.qr_yn=0";
    }

    /**
     * @var tovar[]
     */
    $rez = array();
    $mysqli = Db::getInstance()->getConnection();

    $query_ch = "SELECT * FROM tovar_rent_items
					LEFT JOIN tovar_rent ON tovar_rent_items.model_id=tovar_rent.tovar_rent_id
					LEFT JOIN tovar_rent_cat ON tovar_rent.tovar_rent_cat_id=tovar_rent_cat.tovar_rent_cat_id
			 		WHERE tovar_rent.tovar_rent_cat_id=$cat_id" . $srch_dop;
    $result_ch = $mysqli->query($query_ch);
    if (!$result_ch) {
      die('Сбой при доступе к базе данных: ' . $query_ch . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    while ($row = $result_ch->fetch_assoc()) {
      $rez[] = clone self::getTovarFromArray($row);
    }

    return $rez;
  }

  /**
   * @param $inv_n
   * @return false|mixed|void
   */
  public static function getModelIdForInvN($inv_n)
  {
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT model_id FROM tovar_rent_items WHERE item_inv_n='$inv_n'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    if ($result->num_rows < 1)
      return false;
    else
      return $result->fetch_assoc()['model_id'];
  }


  /**
   * @param string $it_ch
   * @return tovar
   */
  public static function getTovarFromArray($it_ch)
  {
    $tov = new self();

    $tov->item_id = $it_ch['item_id'];
    $tov->cat_id = $it_ch['cat_id'];
    $tov->producer = $it_ch['producer'];
    $tov->model_id = $it_ch['model_id'];
    $tov->item_n = $it_ch['item_n'];
    $tov->item_inv_n = $it_ch['item_inv_n'];
    $tov->sex = $it_ch['sex'];
    $tov->item_size = $it_ch['item_size'];
    $tov->real_item_size = $it_ch['real_item_size'];
    $tov->item_rost1 = $it_ch['item_rost1'];
    $tov->item_rost2 = $it_ch['item_rost2'];
    $tov->item_set = $it_ch['item_set'];
    $tov->buy_date = $it_ch['buy_date'];
    $tov->buy_price = $it_ch['buy_price'];
    $tov->buy_price_cur = $it_ch['buy_price_cur'];
    $tov->exch_to_byr = $it_ch['exch_to_byr'];
    $tov->seller = $it_ch['seller'];
    $tov->item_info = $it_ch['item_info'];
    $tov->cr_ch_date = $it_ch['cr_ch_date'];
    $tov->user = $it_ch['user'];
    $tov->status = $it_ch['status'];
    $tov->active_deal_id = $it_ch['active_deal_id'];
    $tov->item_color = $it_ch['item_color'];
    $tov->item_place = $it_ch['item_place'];
    $tov->br_time = $it_ch['br_time'];
    $tov->state = $it_ch['state'];
    $tov->to_move = $it_ch['to_move'];
    $tov->qr_yn = $it_ch['qr_yn'];

    if (isset($it_ch['rent_cat_name']))
      $tov->cat_name = $it_ch['rent_cat_name'];
    if (isset($it_ch['dog_name']))
      $tov->cat_dog_name = $it_ch['dog_name'];
    if (isset($it_ch['color']))
      $tov->model_color = $it_ch['color'];
    if (isset($it_ch['model']))
      $tov->model_name = $it_ch['model'];
    if (isset($it_ch['set']))
      $tov->model_set = $it_ch['set'];
    if (isset($it_ch['collateral']))
      $tov->collateral = $it_ch['collateral'];
    if (isset($it_ch['model_addr']))
      $tov->model_addr = $it_ch['model_addr'];

    return $tov;
  }

  /**
   * @return bool
   */
  public function isLastRent()
  {
    if ($this->state == 3)
      return true;
    else
      return false;
  }

  /**
   * @return bool
   */
  public function isKarnaval()
  {
    if (Category::isCatIdKarnaval($this->cat_id)) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * @param $model_id
   * @return bool|void
   */
  public static function hasFreeItemsForModelId($model_id)
  {
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT item_id FROM tovar_rent_items WHERE model_id = '$model_id' AND `status`='to_rent' OR `status`='t_bron'";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows > 0)
      return true;
    else
      return false;
  }

  /**
   * @return void
   */
  public function setStatusAsBron()
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "UPDATE tovar_rent_items SET `status`='bron', br_time='0' WHERE item_id='$this->item_id'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
  }

  /**
   * @param $model_id
   * @param $officeId
   * @return tovar[]|false|void
   */
  public static function getFreeTovarsForModelIdAndOffice($model_id, $officeId)
  {

    $mysqli = Db::getInstance()->getConnection();
    if ($officeId == 'all')
      $query = "SELECT * from tovar_rent_items WHERE model_id = '$model_id' AND (`status`='to_rent' OR `status`='t_bron')";
    else
      $query = "SELECT * from tovar_rent_items WHERE model_id = '$model_id' AND item_place='$officeId' AND (`status`='to_rent' OR `status`='t_bron')";


    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows < 1)
      return false;

    $rez = [];
    while ($row = $result->fetch_assoc()) {
      $tov = self::getTovarFromArray($row);
      $rez[] = $tov;
    }
    return $rez;

  }

  public static function getFreeItemsOfficeArrayForModelId($model_id)
  {
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT DISTINCT(item_place) FROM tovar_rent_items WHERE model_id = '$model_id' AND (`status`='to_rent' OR `status`='t_bron')";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    //dd($result->num_rows);
    if ($result->num_rows < 1)
      return false;
    else {
      $offs = [];
      while ($row = $result->fetch_assoc()) {

        $offs[] = $row['item_place'];
      }
      return $offs;
    }
    ;
  }

  /**
   * Get the earliest return date for rented products of this model
   * @param $model_id
   * @return \DateTime|null
   */
  public static function getEarliestReturnDateForModelId($model_id)
  {
    $mysqli = Db::getInstance()->getConnection();
    $currentTime = time();

    // Use rent_deals_act.return_date as it reflects the expected return date for active deals (as seen in Admin Panel)
    $query = "SELECT MIN(rent_deals_act.return_date) as earliest_return
              FROM rent_deals_act
              LEFT JOIN tovar_rent_items ON tovar_rent_items.item_inv_n = rent_deals_act.item_inv_n
              WHERE tovar_rent_items.model_id = '$model_id'
                AND tovar_rent_items.status = 'rented_out'
                AND rent_deals_act.return_date > $currentTime";

    $result = $mysqli->query($query);
    if (!$result) {
      // Log error but don't die - return null instead
      error_log('DB Error in getEarliestReturnDateForModelId: ' . $mysqli->connect_error);
      return null;
    }

    if ($result->num_rows < 1)
      return null;

    $row = $result->fetch_assoc();
    if ($row['earliest_return'] && $row['earliest_return'] > 0) {
      $date = new \DateTime();
      $date->setTimestamp($row['earliest_return']);
      return $date;
    }

    return null;
  }


  /**
   * @param $inv_n
   * @return bool|void
   */
  public static function isKarnavalByInvN($inv_n)
  {
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT tovar_rent.tovar_rent_cat_id as cat_id FROM tovar_rent_items
					LEFT JOIN tovar_rent ON tovar_rent_items.model_id=tovar_rent.tovar_rent_id
			 		WHERE tovar_rent_items.item_inv_n='$inv_n'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows < 1)
      return false;
    $cat_id = $result->fetch_assoc()['cat_id'];

    return Category::isCatIdKarnaval($cat_id);

  }

  /**
   * @param $modelId
   * @return array|void
   */
  public static function getRostSizeArrayByModelId($modelId)
  {
    $rez = [];
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT item_rost1, item_rost2, item_size FROM tovar_rent_items WHERE model_id='$modelId' ORDER BY item_rost1";
    //dd($query);
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows < 1)
      return [];

    while ($row = $result->fetch_assoc()) {
      $new_array = [$row['item_rost1'], $row['item_rost2'], $row['item_size']];
      if (!in_array($new_array, $rez))
        $rez[] = $new_array;
    }
    return $rez;
  }

  /**
   * @param $modelId
   * @param $rostFrom
   * @param $rostTo
   * @return array|void
   */
  public static function getInvNArrayByModelIdAndRost($modelId, $rostFrom, $rostTo)
  {
    $rez = [];
    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT item_inv_n FROM tovar_rent_items WHERE model_id='$modelId' AND item_rost1='$rostFrom' AND item_rost2='$rostTo' AND `status` != 'not_to_rent'";
    //dd($query);
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows < 1)
      return [];

    while ($row = $result->fetch_assoc()) {
      $rez[] = $row['item_inv_n'];
    }

    return $rez;
  }


  public function isInMove()
  {
    if ($this->item_place > 1 && $this->to_move > 0 && $this->status != 'rented_out') {
      return true;
    } else {
      return false;
    }
  }

  /**
   * @param $of_to_num
   * @param string $of_from_num
   * @return bool
   */
  public function moveTo($of_to_num, $of_from_num = '')
  {

    if ($of_from_num > 0) {
      if ($of_from_num != $this->item_place) {
        die('Долго думали: товар уже кто-то переместил!');
      }
    }
    $this->to_move = $of_to_num;
    $this->item_update();

    \bb\classes\bron::unsetCallToCustomer($this->item_inv_n);

    return $this->item_update();
  }

  public function moveCancel()
  {
    $this->to_move = null;
    $this->item_update();
  }
  public function moveAccept()
  {
    if ($this->to_move != Office::getCurrentOffice()->number) {
      die('Вы пытаетесь принять товар, который отправлен не на Ваш офис. Если это не так - свяжитесь с администратором.');
    }
    $this->item_place = $this->to_move;
    $this->to_move = null;
    $this->item_update();

    //for brons (set call_to_customer flag
    if ($this->status == 'bron') {
      \bb\classes\bron::setCallToCustomer($this->item_inv_n);
    }
  }

  /**
   * @return bool
   */
  public function isAtCurrentOffice()
  {
    if ($this->item_place == Office::getCurrentOffice()->number) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * @return bool
   */
  public function isVPuti()
  {
    if ($this->item_place > 0 && $this->to_move > 0) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * @return bool
   */
  public function isRentedOut()
  {
    if ($this->status == 'rented_out') {
      return true;
    } else
      return false;
  }

  /**
   * @return bool
   */
  public function isForRent()
  {
    if ($this->status == 'to_rent' || ($this->status == 't_bron' && $this->br_time < time())) {
      return true;
    } else
      return false;
  }

  /**
   * @param $inv_n
   * @return tovar
   */
  public static function getTovarByInvN($inv_n)
  {
    $t = new self();
    $t->item_load($inv_n);
    if ($t->model_id < 1)
      return false;
    return $t;
  }


  function item_load($item_inv_n)
  {

    if ($item_inv_n > 0) {
      //SELECT * FROM tovar_rent_items LEFT JOIN tovar_rent_cat ON
      //tovar_rent_items.cat_id=tovar_rent_cat.tovar_rent_cat_id
      //left join tovar_rent ON
      //tovar_rent_items.model_id=tovar_rent.tovar_rent_id

      $query_ch = "SELECT * FROM tovar_rent_items
					LEFT JOIN tovar_rent ON tovar_rent_items.model_id=tovar_rent.tovar_rent_id
					LEFT JOIN tovar_rent_cat ON tovar_rent.tovar_rent_cat_id=tovar_rent_cat.tovar_rent_cat_id
			 		WHERE tovar_rent_items.item_inv_n='$item_inv_n'";
      //echo $query_ch;
      $result_ch = $this->mysqli->query($query_ch);
      if (!$result_ch) {
        die('Сбой при доступе к базе данных: ' . $query_ch . ' (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
      }
      $it_ch = $result_ch->fetch_assoc();
      $it_num = $result_ch->num_rows;

      $this->item_id = $it_ch['item_id'];
      $this->cat_id = $it_ch['cat_id'];
      $this->producer = $it_ch['producer'];
      $this->model_id = $it_ch['model_id'];
      $this->item_n = $it_ch['item_n'];
      $this->item_inv_n = $it_ch['item_inv_n'];
      $this->sex = $it_ch['sex'];
      $this->item_size = $it_ch['item_size'];
      $this->real_item_size = $it_ch['real_item_size'];
      $this->item_rost1 = $it_ch['item_rost1'];
      $this->item_rost2 = $it_ch['item_rost2'];
      $this->item_set = $it_ch['item_set'];
      $this->buy_date = $it_ch['buy_date'];
      $this->buy_price = $it_ch['buy_price'];
      $this->buy_price_cur = $it_ch['buy_price_cur'];
      $this->exch_to_byr = $it_ch['exch_to_byr'];
      $this->seller = $it_ch['seller'];
      $this->item_info = $it_ch['item_info'];
      $this->cr_ch_date = $it_ch['cr_ch_date'];
      $this->user = $it_ch['user'];
      $this->status = $it_ch['status'];
      $this->active_deal_id = $it_ch['active_deal_id'];
      $this->item_color = $it_ch['item_color'];
      $this->item_place = $it_ch['item_place'];
      $this->br_time = $it_ch['br_time'];
      $this->state = $it_ch['state'];
      $this->to_move = $it_ch['to_move'];
      $this->qr_yn = $it_ch['qr_yn'];
      $this->collateral = $it_ch['collateral'];

      $this->cat_name = $it_ch['rent_cat_name'];
      $this->cat_dog_name = $it_ch['dog_name'];
      $this->model_color = $it_ch['color'];
      $this->model_name = $it_ch['model'];
      $this->model_set = $it_ch['set'];


    }
  }//end of item_load

  function item_id_load($item_id)
  {

    if ($item_id > 0) {

      $query_ch = "SELECT * FROM tovar_rent_items WHERE item_id='$item_id'";
      $result_ch = $this->mysqli->query($query_ch);
      if (!$result_ch) {
        die('Сбой при доступе к базе данных: ' . $query_ch . ' (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
      }
      $it_ch = $result_ch->fetch_assoc();
      $it_num = $result_ch->num_rows;

      $this->item_id = $it_ch['item_id'];
      $this->cat_id = $it_ch['cat_id'];
      $this->producer = $it_ch['producer'];
      $this->model_id = $it_ch['model_id'];
      $this->item_n = $it_ch['item_n'];
      $this->item_inv_n = $it_ch['item_inv_n'];
      $this->sex = $it_ch['sex'];
      $this->item_size = $it_ch['item_size'];
      $this->real_item_size = $it_ch['real_item_size'];
      $this->item_rost1 = $it_ch['item_rost1'];
      $this->item_rost2 = $it_ch['item_rost2'];
      $this->item_set = $it_ch['item_set'];
      $this->buy_date = $it_ch['buy_date'];
      $this->buy_price = $it_ch['buy_price'];
      $this->buy_price_cur = $it_ch['buy_price_cur'];
      $this->exch_to_byr = $it_ch['exch_to_byr'];
      $this->seller = $it_ch['seller'];
      $this->item_info = $it_ch['item_info'];
      $this->cr_ch_date = $it_ch['cr_ch_date'];
      $this->user = $it_ch['user'];
      $this->status = $it_ch['status'];
      $this->active_deal_id = $it_ch['active_deal_id'];
      $this->item_color = $it_ch['item_color'];
      $this->item_place = $it_ch['item_place'];
      $this->br_time = $it_ch['br_time'];
      $this->state = $it_ch['state'];
      $this->to_move = $it_ch['to_move'];
      $this->qr_yn = $it_ch['qr_yn'];
      //$this->collateral=$it_ch['collateral'];
    }
  }//end of item_load


  function item_update()
  {
    //Пока меняем только места/офисы. Потом нужно доработать замену всего !!!
    $query_upd = "UPDATE tovar_rent_items SET `item_place`='$this->item_place', to_move='$this->to_move' WHERE item_id='$this->item_id'";
    $result_upd = $this->mysqli->query($query_upd);
    if (!$result_upd) {
      die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
    }

  }

  /**
   * @param $id
   * @return tovar
   */
  public static function geTovarById($id)
  {
    $tovar = new self();
    $tovar->item_id_load($id);
    return $tovar;
  }

  public function getImgAddress()
  {
    $mysqli = Db::getInstance()->getConnection();
  }

  public function del_item($reason = '')
  {
    if ($this->item_inv_n < 1) {
      die('Товар не найден. Сообщите Диме о проблеме. Скорее всего товар уже удален/перемещен.');
    }

    if ($this->status == 'rented_out') {
      die('Товар на руках! Сначала оформите возврат.');
    }
    if ($this->status == 'to_deliver') {
      die('Товар оформен на доставку курьером! Сначала оформите возврат.');
    }
    $this->item_del_info .= $reason;

    //проверка по броням
    $query_ch = "SELECT * FROM rent_orders WHERE inv_n='$this->item_inv_n' AND (type2='bron' OR type2='deliv')";
    $result_ch = $this->mysqli->query($query_ch);
    if (!$result_ch) {
      die('Сбой при доступе к базе данных: ' . $query_ch . ' (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
    }
    $it_num = $result_ch->num_rows;
    if ($it_num > 0) {
      die('На товар есть бронь (самовывоз либо доставка)! Сначала удалите бронь.');
    }

    //проверка на наличие активных сделок
    $query_ch = "SELECT * FROM rent_deals_act WHERE item_inv_n='$this->item_inv_n'";
    $result_ch = $this->mysqli->query($query_ch);
    if (!$result_ch) {
      die('Сбой при доступе к базе данных: ' . $query_ch . ' (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
    }
    $it_num = $result_ch->num_rows;

    if ($it_num > 0) {
      die('Операция не удалась. Удалите активные сделки.');
    }

    //переносим в архив
    $a_date = getdate(time());
    $arch_date = mktime(0, 0, 0, $a_date['mon'], ($a_date['mday']), $a_date['year']);

    $query_ins = "INSERT INTO tovar_rent_items_arch VALUES('', '$arch_date', '" . time() . "', '$this->user_id', '$this->out_status', '$this->sell_amount_byr', '$this->rent_payment_type', '$this->sell_amount_usd', '" . (addslashes($this->item_del_info)) . "', '$this->item_id', '$this->cat_id', '" . (addslashes($this->producer)) . "', '$this->model_id', '$this->item_n', '$this->item_inv_n', '$this->sex', '$this->item_size', '$this->real_item_size', '$this->item_rost1', '$this->item_rost2', '$this->item_set', '$this->buy_date', '$this->buy_price', '$this->buy_price_cur', '$this->exch_to_byr', '$this->seller', '$this->item_info', '$this->cr_ch_date', '$this->user', '$this->status', '$this->active_deal_id', '$this->item_color', '$this->item_place', '$this->br_time', '$this->state', '', '$this->to_move', '$this->qr_yn')";
    $result_ins = $this->mysqli->query($query_ins);
    if (!$result_ins) {
      die('Сбой при доступе к базе данных: ' . $query_ins . ' (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
    }


    $query_del = "DELETE FROM tovar_rent_items WHERE item_id='$this->item_id'";
    $result_del = $this->mysqli->query($query_del);
    if (!$result_del) {
      die('Сбой при доступе к базе данных: ' . $query_del . ' (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
    }

    $this->return_info = 'Товар удален успешно!';

  }// end of del_item


  /**
   * @param $modelId
   * @param $rostFrom
   * @param $rostTo
   * @return tovar[]|false|void
   */
  public static function getByModelIdAndRostStrict($modelId, $rostFrom, $rostTo)
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM tovar_rent_items WHERE model_id='$modelId' AND item_rost1='$rostFrom' AND item_rost2='$rostTo'";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows < 1)
      return false;
    $rez = [];
    while ($row = $result->fetch_assoc()) {
      $t = self::getTovarFromArray($row);
      $rez[] = $t;
    }

    return $rez;
  }

  /**
   * @param $modelId
   * @return tovar[]|false|void
   */
  public static function getByModelId($modelId)
  {
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM tovar_rent_items WHERE model_id='$modelId'";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ($result->num_rows < 1)
      return false;
    $rez = [];
    while ($row = $result->fetch_assoc()) {
      $t = self::getTovarFromArray($row);
      $rez[] = $t;
    }

    return $rez;
  }

  /**
   * @param array $catIds // [] for all
   * @param \DateTime $date
   * @return float|int|void //avg age in days
   */
  public static function getAvgAgeForCats(array $catIds, \DateTime $date)
  {
    $mysqli = Db::getInstance()->getConnection();

    $srchLine = '';
    if (count($catIds) > 0) {
      $srchLine = "WHERE tovar_rent.tovar_rent_cat_id IN ('" . implode("', '", $catIds) . "')";
    }

    $query = "SELECT COUNT(items.item_id) AS items_num, SUM((" . $date->getTimestamp() . "-items.buy_date)/(24*60*60)) AS age_days FROM
                (SELECT item_id, buy_date, model_id FROM `tovar_rent_items` WHERE buy_date<=" . $date->getTimestamp() . "
                  UNION
                  SELECT item_id, buy_date, model_id FROM `tovar_rent_items_arch` WHERE buy_date<=" . $date->getTimestamp() . " AND arch_time>=" . $date->getTimestamp() . ") AS items
              LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_id = items.model_id
              $srchLine
    ";
    //    echo $query;

    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $row = $result->fetch_assoc();
    //Base::varDamp($row);
    if ($row['items_num'] == 0)
      return 0;
    return round($row['age_days'] / $row['items_num'], 0);
  }

  /**
   * @param \DateTime $startDate
   * @param \DateTime $endDate
   * @param array $catIds // [] for all
   * @return int|mixed|void
   */
  public static function getBoutghTovsForCats(\DateTime $startDate, \DateTime $endDate, array $catIds)
  {
    $mysqli = Db::getInstance()->getConnection();

    $srchLine = '';
    if (count($catIds) > 0) {
      $srchLine = "WHERE tovar_rent.tovar_rent_cat_id IN ('" . implode("', '", $catIds) . "')";
    }

    $query = "SELECT COUNT(items.item_id) AS items_num FROM
                (SELECT item_id, buy_date, model_id FROM `tovar_rent_items` WHERE buy_date BETWEEN " . $startDate->getTimestamp() . " AND " . $endDate->getTimestamp() . "
                  UNION
                  SELECT item_id, buy_date, model_id FROM `tovar_rent_items_arch` WHERE buy_date BETWEEN " . $startDate->getTimestamp() . " AND " . $endDate->getTimestamp() . ") AS items
              LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_id = items.model_id
              $srchLine
    ";
    //    echo $query;

    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $row = $result->fetch_assoc();
    //Base::varDamp($row);
    if ($row['items_num'] == 0)
      return 0;
    return $row['items_num'];
  }


  /**
   * @param \DateTime $startDate
   * @param \DateTime $endDate
   * @return tovar[]
   */
  public static function getBoutghTovsForPeriod(\DateTime $startDate, \DateTime $endDate)
  {

    $rez = [];

    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * FROM `tovar_rent_items` WHERE buy_date BETWEEN " . $startDate->getTimestamp() . " AND " . $endDate->getTimestamp() . " AND `state` != '-1'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    while ($row = $result->fetch_assoc()) {
      $rez[] = self::createFromDbArray($row);
    }

    $query = "SELECT * FROM `tovar_rent_items_arch` WHERE buy_date BETWEEN " . $startDate->getTimestamp() . " AND " . $endDate->getTimestamp() . " AND `state` != '-1'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    while ($row = $result->fetch_assoc()) {
      $rez[] = self::createFromDbArray($row);
    }

    return $rez;
  }

  /**
   * @return tovar[]
   */
  public static function getAllAct()
  {
    $mysqli = Db::getInstance()->getConnection();

    $rez = [];

    $query = "SELECT * FROM `tovar_rent_items`";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    while ($row = $result->fetch_assoc()) {
      $rez[] = self::createFromDbArray($row);
    }

    return $rez;
  }

  /**
   * @return true|void
   */
  public function updateExchRate()
  {
    $mysqli = Db::getInstance()->getConnection();

    $date = new \DateTime();
    $date->setTimestamp($this->buy_date);
    $cur = $this->buy_price_cur;
    if ($this->buy_price_cur == 'TBYR')
      $cur = 'BYN';
    $newExchRate = \bb\Base::getExchRateToUsd($date, $cur);

    $query = "UPDATE `tovar_rent_items` SET buy_price_cur='$newExchRate' WHERE item_inv_n='$this->item_inv_n'";
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    return true;
  }

  /**
   * @param array $row
   * @return self
   */
  public static function createFromDbArray(array $row)
  {
    $t = new self();

    //arch info
    if (isset($row['item_arch_id']))
      $t->item_arch_id = $row['item_arch_id'];
    if (isset($row['arch_date']))
      $t->arch_date = $row['arch_date'];
    if (isset($row['arch_time']))
      $t->arch_time = $row['arch_time'];
    if (isset($row['arch_who_id']))
      $t->arch_who_id = $row['arch_who_id'];
    if (isset($row['out_status']))
      $t->out_status = $row['out_status'];
    if (isset($row['out_sell_tbyr']))
      $t->out_sell_tbyr = $row['out_sell_tbyr'];
    if (isset($row['out_sell_kassa']))
      $t->out_sell_kassa = $row['out_sell_kassa'];
    if (isset($row['out_sell_usd']))
      $t->out_sell_usd = $row['out_sell_usd'];
    if (isset($row['out_info']))
      $t->out_info = $row['out_info'];

    //normal info
    $t->item_id = $row['item_id'];
    $t->cat_id = $row['cat_id'];
    $t->producer = $row['producer'];
    $t->model_id = $row['model_id'];
    $t->item_n = $row['item_n'];
    $t->item_inv_n = $row['item_inv_n'];
    $t->sex = $row['sex'];
    $t->item_size = $row['item_size'];
    $t->real_item_size = $row['real_item_size'];
    $t->item_rost1 = $row['item_rost1'];
    $t->item_rost2 = $row['item_rost2'];
    $t->item_set = $row['item_set'];
    $t->buy_date = $row['buy_date'];
    $t->buy_price = $row['buy_price'];
    $t->buy_price_cur = $row['buy_price_cur'];
    $t->exch_to_byr = $row['exch_to_byr'];
    $t->seller = $row['seller'];
    $t->item_info = $row['item_info'];
    $t->cr_ch_date = $row['cr_ch_date'];
    $t->user = $row['user'];
    $t->status = $row['status'];
    $t->active_deal_id = $row['active_deal_id'];
    $t->item_color = $row['item_color'];
    $t->item_place = $row['item_place'];
    $t->br_time = $row['br_time'];
    $t->state = $row['state'];
    $t->to_move = $row['to_move'];
    $t->qr_yn = $row['qr_yn'];

    return $t;

  }


  /**
   * @param \DateTime $startDate
   * @param \DateTime $endDate
   * @param array $catIds
   * @return int|mixed|void
   */
  public static function getArchivedTovsForCatsForPeriod(\DateTime $startDate, \DateTime $endDate, array $catIds)
  {
    $mysqli = Db::getInstance()->getConnection();

    $srchLine = '';
    if (count($catIds) > 0) {
      $srchLine = " AND tovar_rent.tovar_rent_cat_id IN ('" . implode("', '", $catIds) . "')";
    }

    $query = "SELECT COUNT(tovar_rent_items_arch.item_id) AS items_num FROM `tovar_rent_items_arch`
              LEFT JOIN tovar_rent ON tovar_rent.tovar_rent_id = tovar_rent_items_arch.model_id
              WHERE tovar_rent_items_arch.arch_date BETWEEN " . $startDate->getTimestamp() . " AND " . $endDate->getTimestamp() . " $srchLine
    ";
    //    echo $query.'<br><br>';

    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    $row = $result->fetch_assoc();
    //Base::varDamp($row);
    if ($row['items_num'] == 0)
      return 0;
    return $row['items_num'];
  }

  /**
   * @param \DateTime $date
   * @param $catIds //'all' for all - default
   * @return int|mixed|void
   */
  public static function getTovNumberForCatsForDate(\DateTime $date, $catIds = 'all')
  {
    $mysqli = Db::getInstance()->getConnection();
    $count = 0;

    $srchLine = '';
    if ($catIds != 'all' && is_array($catIds)) {
      $srchLine = " AND models.tovar_rent_cat_id IN ('" . implode("', '", $catIds) . "')";
    }

    $query = "
      SELECT COUNT(tovar_rent_items.item_id) as num FROM `tovar_rent_items`
        LEFT JOIN (SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent`
           UNION
           SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent_arch`) as models ON models.tovar_rent_id = tovar_rent_items.model_id
        WHERE tovar_rent_items.buy_date <= " . $date->getTimestamp() . " $srchLine;
    ";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }


    $count += $result->fetch_assoc()['num'];

    $query = "
      SELECT COUNT(tovar_rent_items_arch.item_id) as num FROM `tovar_rent_items_arch`
        LEFT JOIN (SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent`
           UNION
           SELECT tovar_rent_id, tovar_rent_cat_id FROM `tovar_rent_arch`) as models ON models.tovar_rent_id = tovar_rent_items_arch.model_id
        WHERE tovar_rent_items_arch.buy_date <= " . $date->getTimestamp() . " AND tovar_rent_items_arch.arch_time >= " . $date->getTimestamp() . " $srchLine;
    ";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $count += $result->fetch_assoc()['num'];

    return $count;
  }

  public static function getTovNumberForModelIdsForDate(\DateTime $date, array $modelIds)
  {
    $mysqli = Db::getInstance()->getConnection();
    $count = 0;

    $srchLine = '';


    $query = "
      SELECT COUNT(tovar_rent_items.item_id) as num FROM `tovar_rent_items`
        WHERE tovar_rent_items.buy_date <= " . $date->getTimestamp() . " AND tovar_rent_items.model_id IN ('" . implode("', '", $modelIds) . "');
    ";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }


    $count += $result->fetch_assoc()['num'];

    $query = "
      SELECT COUNT(tovar_rent_items_arch.item_id) as num FROM `tovar_rent_items_arch`
        WHERE tovar_rent_items_arch.buy_date <= " . $date->getTimestamp() . " AND tovar_rent_items_arch.arch_time >= " . $date->getTimestamp() . " AND tovar_rent_items_arch.model_id IN ('" . implode("', '", $modelIds) . "');
    ";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) {
      die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $count += $result->fetch_assoc()['num'];

    return $count;
  }

  //$mysqli = \bb\Db::getInstance()->getConnection();
//{die('Сбой при доступе к базе данных: '.$query_mod.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//




}

