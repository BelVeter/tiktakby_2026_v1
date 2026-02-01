<?php

class client {
	public $mysqli;
	
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
	
	
	function __construct(&$db_connect=null) {
		if ($db_connect!=null) {
            $this->mysqli = $db_connect;
        }
        else {
		    $this->mysqli = \bb\Db::getInstance()->getConnection();
        }
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
	
	function find_duble($param) {
		//$query_ch = "SELECT * FROM clients WHERE family='$this->family' AND name='$this->name' AND otch='$this->otch' AND city='$this->city' AND str='$this->str' AND dom='$this->dom' AND kv='$this->kv'";
		//$result_ch = $this->mysqli->query($query_ch);
		//if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
		//$it_ch = $result_ch->fetch_assoc();
		//!!!
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