<?php

namespace bb\classes;

use bb\Base;
use bb\Db;

class Client {
  private $mysqli;

	public $client_id;
	public $family;
	public $name;
	public $otch;
	public $city;
	public $str;
	public $dom;
	public $kv;
	public $pas_n;
	public $pas_ln;
	public $pas_date;
	public $pas_who;
	public $reg_city;
	public $reg_str;
	public $reg_dom;
	public $reg_kv;
	public $phone_1;
	public $phone_2;
	public $info;
	public $status;
	public $cr_time;
	public $arch_n;
	public $arch_amount;
	public $arch_l_date;
	public $cr_who;
  public $source;

	public $main_cl_id; // use for clients clean up - the id of active client dublicate

	private $ru_en_wp = array(
			"А" => "A",
			"Б" => "Б",
			"В" => "B",
			"Г" => "Г",
			"Д" => "Д",
			"Е" => "E",
			"Ё" => "E",
			"Ж" => "Ж",
			"З" => "З",
			"И" => "И",
			"Й" => "Й",
			"К" => "K",
			"Л" => "Л",
			"М" => "M",
			"Н" => "H",
			"О" => "O",
			"П" => "П",
			"Р" => "P",
			"С" => "C",
			"Т" => "T",
			"У" => "Y",
			"Ф" => "Ф",
			"Х" => "X",
			"Ц" => "Ц",
			"Ч" => "Ч",
			"Ш" => "Ш",
			"Щ" => "Щ",
			"Ъ" => "Ъ",
			"Ы" => "Ы",
			"Ь" => "Ь",
			"Э" => "Э",
			"Ю" => "Ю",
			"Я" => "Я",
			"Q" => "Q",
			"W" => "W",
			"E" => "E",
			"R" => "R",
			"T" => "T",
			"Y" => "Y",
			"U" => "U",
			"I" => "I",
			"O" => "O",
			"P" => "P",
			"A" => "A",
			"S" => "S",
			"D" => "D",
			"F" => "F",
			"G" => "G",
			"H" => "H",
			"J" => "J",
			"K" => "K",
			"L" => "L",
			"Z" => "Z",
			"X" => "X",
			"C" => "C",
			"V" => "V",
			"B" => "B",
			"N" => "N",
			"M" => "M",
			"1" => "1",
			"2" => "2",
			"3" => "3",
			"4" => "4",
			"5" => "5",
			"6" => "6",
			"7" => "7",
			"8" => "8",
			"9" => "9",
			"0" => "0");


	function __construct() {
	    $this->mysqli = \bb\Db::getInstance()->getConnection();
	}

  /**
   * @param $str
   * @param $countOnly
   * @return Client[]|false|void
   */
  public static function getClientsByFioString($str, $countOnly = false) {
    if ($str=='') return false;

    $mysqli = Db::getInstance()->getConnection();

    $fioArray = explode(' ', $str, 3);
    if (!is_array($fioArray) && count($fioArray<1)) return false;

    //\bb\Base::varDamp($fioArray);
    $wordCount = count($fioArray);

    if ($wordCount==1) $query = "SELECT * FROM clients WHERE CONCAT(family,' ', name, ' ', otch) LIKE('%$fioArray[0]%');";
    elseif ($wordCount==2) $query = "SELECT * FROM clients WHERE family LIKE('%$fioArray[0]%') AND name LIKE('%$fioArray[1]%')";
    else{//3
      $query = "SELECT * FROM clients WHERE family LIKE('%$fioArray[0]%') AND name LIKE('%$fioArray[1]%') AND otch LIKE('%$fioArray[2]%')";
    }
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) die ('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($countOnly) {
      return $result->num_rows;
    }
    if ($result->num_rows>0) {
      $rez = [];
      while ($row=$result->fetch_assoc()) {
        $rez[] = self::createFromDbArray($row);
      }
      return $rez;
    }
    else return [];


  }

  /**
   * @param $phone
   * @param $countOnly
   * @return Client[]|false|void
   */
  public static function getClientsByPhoneNumber($phone, $countOnly = false) {
    if($phone<1) return false;

    $mysqli = Db::getInstance()->getConnection();

    $query = "SELECT * FROM clients WHERE phone_1 LIKE('%$phone') OR phone_2 LIKE('%$phone');";
    $result = $mysqli->query($query);
    if (!$result) die ('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($countOnly) {
      return $result->num_rows;
    }

    if ($result->num_rows>0) {
      $rez = [];
      while ($row=$result->fetch_assoc()) {
        $rez[] = self::createFromDbArray($row);
      }
      return $rez;
    }
    else return [];
  }

  /**
   * @param $phone
   * @return Client|false|void
   */
  public static function findByNumber($phone) {
    $phone = substr($phone, -9);
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM clients WHERE phone_1 LIKE '%$phone' OR phone_2 LIKE '%$phone'";
    $result = $mysqli->query($query);
    if (!$result) die ('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($result->num_rows>0) {
      return self::createFromDbArray($result->fetch_assoc());
    }
    else return false;

  }

  /**
   * @param $str
   * @param $dom
   * @param $countOnly
   * @return Client[]|false|void
   */
  public static function getClientsByAddress($str, $dom, $countOnly=false){
    $str = trim($str);
    $dom = trim($dom);
//    echo $str.'--'.$dom;

    if($str=='' && ($dom=='')) return false;

    $mysqli = Db::getInstance()->getConnection();

    if ($dom == '') $query = "SELECT * FROM clients WHERE (str LIKE('%$str%') OR reg_str LIKE('%$str%'));";
    elseif ($str == '') $query = "SELECT * FROM clients WHERE (dom LIKE('%$dom%') OR reg_dom LIKE('%$dom%'));";
    else $query = "SELECT * FROM clients WHERE (str LIKE('%$str%') OR reg_str LIKE('%$str%')) AND (dom LIKE('%$dom%') OR reg_dom LIKE('%$dom%'));";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) die ('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($countOnly) {
      return $result->num_rows;
    }

    if ($result->num_rows>0) {
      $rez = [];
      while ($row=$result->fetch_assoc()) {
        $rez[] = self::createFromDbArray($row);
      }
      return $rez;
    }
    else return [];

  }

  //$strFio, $phone, $str, $dom, $countOnly=false
  public static function getClientsByComplexSrch($strFio, $phone, $str, $dom, $countOnly=false){

    $srchCloses = [];
    $mysqli = Db::getInstance()->getConnection();

    if ($strFio != '') {
      $fioArray = explode(' ', $strFio, 3);
      if (!is_array($fioArray) && count($fioArray<1)) return false;
      $wordCount = count($fioArray);
      if ($wordCount==1) $srchCloses[] = "(CONCAT(family,' ', name, ' ', otch) LIKE('%$fioArray[0]%'))";
      elseif ($wordCount==2) $srchCloses[] = "(family LIKE('%$fioArray[0]%') AND name LIKE('%$fioArray[1]%'))";
      else{//3
        $srchCloses[] = "(family LIKE('%$fioArray[0]%') AND name LIKE('%$fioArray[1]%') AND otch LIKE('%$fioArray[2]%'))";
      }
    }

    if ($phone != '' && $phone*1>99999) {
      $srchCloses[] = "(phone_1 LIKE('%$phone') OR phone_2 LIKE('%$phone'))";
    }

    $str = trim($str);
    $dom = trim($dom);

    if($str!='' || $dom!=''){
      if ($dom == '') $srchCloses[] = "(str LIKE('%$str%') OR reg_str LIKE('%$str%'))";
      elseif ($str == '') $srchCloses[] = "(dom LIKE('%$dom%') OR reg_dom LIKE('%$dom%'))";
      else $srchCloses[] = "(str LIKE('%$str%') OR reg_str LIKE('%$str%')) AND (dom LIKE('%$dom%') OR reg_dom LIKE('%$dom%'))";
    }

    if (count($srchCloses)<1) return false;

    $srch = Db::makeQueryConditionFromArray($srchCloses);
    $query = "SELECT * FROM clients ".$srch;
//    Base::varDamp($srch);

    $result = $mysqli->query($query);
    if (!$result) die ('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($countOnly) {
      return $result->num_rows;
    }

    if ($result->num_rows>0) {
      $rez = [];
      while ($row=$result->fetch_assoc()) {
        $rez[] = self::createFromDbArray($row);
      }
      return $rez;
    }
    else return [];

    //Db::makeQueryConditionFromArray();

  }

    /**
     * @param $id
     * @return Client
     */
    public static function getClientById($id) {
	    $cl = new self();
	    $cl->load($id);
	    return $cl;
    }

	function load($cl_id) {
		$query_ch = "SELECT * FROM clients WHERE client_id='$cl_id'";
		$result_ch = $this->mysqli->query($query_ch);
		if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
		$it_ch = $result_ch->fetch_assoc();
		$it_num = $result_ch->num_rows;

		$this->client_id=$it_ch['client_id'];
		$this->family=$it_ch['family'];
		$this->name=$it_ch['name'];
		$this->otch=$it_ch['otch'];
		$this->city=$it_ch['city'];
		$this->str=$it_ch['str'];
		$this->dom=$it_ch['dom'];
		$this->kv=$it_ch['kv'];
		$this->pas_n=$it_ch['pas_n'];
		$this->pas_ln=$it_ch['pas_ln'];
		$this->pas_date=$it_ch['pas_date'];
		$this->pas_who=$it_ch['pas_who'];
		$this->reg_city=$it_ch['reg_city'];
		$this->reg_str=$it_ch['reg_str'];
		$this->reg_dom=$it_ch['reg_dom'];
		$this->reg_kv=$it_ch['reg_kv'];
		$this->phone_1=$it_ch['phone_1'];
		$this->phone_2=$it_ch['phone_2'];
		$this->info=$it_ch['info'];
		$this->status=$it_ch['status'];
		$this->cr_time=$it_ch['cr_time'];
		$this->arch_n=$it_ch['arch_n'];
		$this->arch_amount=$it_ch['arch_amount'];
		$this->arch_l_date=$it_ch['arch_l_date'];
		$this->cr_who=$it_ch['cr_who'];

	}

  /**
   * @param $row
   * @return Client
   */
  public static function createFromDbArray($row){
    $cl = new self();

    $cl->client_id=$row['client_id'];
    $cl->family=$row['family'];
    $cl->name=$row['name'];
    $cl->otch=$row['otch'];
    $cl->city=$row['city'];
    $cl->str=$row['str'];
    $cl->dom=$row['dom'];
    $cl->kv=$row['kv'];
    $cl->pas_n=$row['pas_n'];
    $cl->pas_ln=$row['pas_ln'];
    $cl->pas_date=$row['pas_date'];
    $cl->pas_who=$row['pas_who'];
    $cl->reg_city=$row['reg_city'];
    $cl->reg_str=$row['reg_str'];
    $cl->reg_dom=$row['reg_dom'];
    $cl->reg_kv=$row['reg_kv'];
    $cl->phone_1=$row['phone_1'];
    $cl->phone_2=$row['phone_2'];
    $cl->info=$row['info'];
    $cl->status=$row['status'];
    $cl->cr_time=$row['cr_time'];
    $cl->arch_n=$row['arch_n'];
    $cl->arch_amount=$row['arch_amount'];
    $cl->arch_l_date=$row['arch_l_date'];
    $cl->cr_who=$row['cr_who'];
    $cl->cr_who=$row['cr_who'];
    $cl->source=$row['source'];

    return $cl;

  }

	function find_duble($param) {
		//$query_ch = "SELECT * FROM clients WHERE family='$this->family' AND name='$this->name' AND otch='$this->otch' AND city='$this->city' AND str='$this->str' AND dom='$this->dom' AND kv='$this->kv'";
		//$result_ch = $this->mysqli->query($query_ch);
		//if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
		//$it_ch = $result_ch->fetch_assoc();
		//!!!
	}

    /**
     * @return string
     */
    public function getFioFull(){
        return $this->family.' '.$this->name.' '.$this->otch;
    }

	function correct_ln() {//нужно проверить

		$new_ln='';
		for ($i=0; $i<iconv_strlen($this->pas_ln, "UTF-8"); $i++) {

			$char=strtoupper(mb_substr($this->pas_ln, $i, 1, 'UTF-8'));

			if (array_key_exists($char, $this->ru_en_wp)) {
				$new_ln.= $this->ru_en_wp[$char];
			}
			else {
				$new_ln.=$char;
			}
		}
		$this->pas_ln=$new_ln;
	}

	function correct_pass_n () {
		//!!!

	}
    public function getAddressNoCity() {
        return $this->str.' '.$this->dom.'-'.$this->kv;
    }

    public function getAddressForNavigation() {
        return 'г.'.$this->city.' '.$this->str.' '.$this->dom;
    }

	function save() {
		$query = "INSERT INTO clients VALUES('', '$this->family', '$this->name', '$this->otch', '$this->city', '$this->str', '$this->dom', '$this->kv', '$this->pas_n', '$this->pas_ln', '$this->pas_date', '$this->pas_who', '$this->reg_city', '$this->reg_str', '$this->reg_dom', '$this->reg_kv', '$this->phone_1', '$this->phone_2', '$this->info', '', ".time().", '', '','', '".$_SESSION['user_id']."')";
		$result_ch = $this->mysqli->query($query);
		if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
		$this->client_id=$this->mysqli->insert_id;
	}

	function delete () {
		$query = "DELETE FROM clients WHERE client_id='$this->client_id'";
		$result_ch = $this->mysqli->query($query);
		if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
	}

	function arch() {
		$query = "INSERT INTO clients_arch VALUES('', ".time().", ".$_SESSION['user_id'].", '$this->main_cl_id', '$this->client_id', '$this->family', '$this->name', '$this->otch', '$this->city', '$this->str', '$this->dom', '$this->kv', '$this->pas_n', '$this->pas_ln', '$this->pas_date', '$this->pas_who', '$this->reg_city', '$this->reg_str', '$this->reg_dom', '$this->reg_kv', '$this->phone_1', '$this->phone_2', '$this->info', '', ".time().", '', '','', '$this->cr_who')";
		$result_ch = $this->mysqli->query($query);
		if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}

		$this->delete();
	}

	function update() {
		$query = "UPDATE clients SET family='$this->family', name='$this->name', otch='$this->otch', city='$this->city', str='$this->str', dom='$this->dom', kv='$this->kv', pas_n='$this->pas_n', pas_ln='$this->pas_ln', pas_date='$this->pas_date', pas_who='$this->pas_who', reg_city='$this->reg_city', reg_str='$this->reg_str', reg_dom='$this->reg_dom', reg_kv='$this->reg_kv', phone_1='$this->phone_1', phone_2='$this->phone_2', info='$this->info', status='$this->status', cr_time='$this->cr_time', arch_n='$this->arch_n', arch_amount='$this->arch_amount', arch_l_date='$this->arch_l_date', cr_who='$this->cr_who' WHERE client_id='$this->client_id'";
		$result_ch = $this->mysqli->query($query);
		if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}

	}

    public function getPhoneNumberToDeal($num=1) {
        if ($num==1) $phone = strval($this->phone_1);
        else $phone = strval($this->phone_2);

        if (strlen($phone) == 7) return $phone;
        elseif (strlen($phone) == 9) return '+375'.$phone;
        else return $phone;

    }

    function phonePrint ($num) {
        if ($num==1) $ph = $this->phone_1;
        else $ph = $this->phone_2;

        if ($ph=='') {return '';}

        $dl=strlen($ph);

        if ($dl<7) {return $ph;}

        $dl>7 ? $dl_to=$dl-7 : $dl_to=0;
        $ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
        return $ph_out;

    }

}

function phone_print ($ph) {
	if ($ph=='') {return '';}

	$dl=strlen($ph);

	if ($dl<7) {return $ph;}

	$dl>7 ? $dl_to=$dl-7 : $dl_to=0;
	$ph_out=substr($ph, 0, $dl_to).'-'.substr($ph, -7, 3).'-'.substr($ph, -4, 2).'-'.substr($ph, -2, 2);
	return $ph_out;

}


?>
