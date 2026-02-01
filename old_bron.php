<?php

class bron {

	public $mysqli;
	private $db_hostname;
	private $db_username;
	private $db_password;
	private $db_database;

	public $user_id;

	public $insert_id;

	public $order_id;
	public $type;
	public $order_date;
	public $from;
	public $to;
	public $validity;
	public $inv_n;
	public $model_id;
	public $cat_id;
	public $type2;
	public $client_id;
	public $info;
	public $web;
	public $cr_time;
	public $cr_who_id;
	public $ch_time;
	public $ch_who_id;
	public $status;
	public $appr_id;
	public $appr_time;
	public $cr_ip;
	public $place_status;
	public $rem_type;
	public $rem_pic_url;

	public $item_status;
	public $item_place;
	public $item_color;
	public $cat_dog_name;
	public $model;
	public $mod_color;
	public $producer;
	public $active_deal_id;
	public $in_stirka;
    public $stir_id;

	public $br_color;

	public $strong_t2_array = array ("bron", "deliv", "remont", "out");
	public $failure = 0;
	public $alert = '';
	
	public $big_pic;
	public $small_pic;


	function __construct() {//передаем строчку (массив) из mysql запроса

		//require_once ($_SERVER['DOCUMENT_ROOT'].'/dimanay2.php'); // подключаем базу данных
		/*$this->db_hostname = '127.0.0.1';
		$this->db_database = 'tiktak';
		$this->db_username = 'veter';
		$this->db_password = 'mb8941';
		*/
		$this->db_hostname = '86.57.246.177';
		$this->db_database = 'tiktakby_tiktak';
		$this->db_username = 'robot';
		$this->db_password = 'mb8941';

		//подключаемся к mysqlсерверу
		$this->mysqli = new mysqli($this->db_hostname, $this->db_username, $this->db_password, $this->db_database);
		if ($this->mysqli->connect_error) {
			die('Ошибка соединения с MYSQL сервером: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}

		// выбор правильной кодировки при работе с БД
		$this->mysqli->query('set character_set_client="utf8"'); // в какой кодировке получать данные от клиента
		$this->mysqli->query('set character_set_results="utf8"'); // в какой кодировке получать данные от БД для вывода клиенту
		$this->mysqli->query('set collation_connection="utf8_general_ci"'); // кодировка в которой будут посылаться служебные команды для сервера

		global $_SESSION;
		$this->user_id=$_SESSION['user_id'];

	}// end of construct

	function br_line ($ord_line) {//заполнение при передачи брони из MySQL

		$this->order_id=$ord_line['order_id'];
		$this->type=$ord_line['type'];
		$this->order_date=$ord_line['order_date'];
		$this->from=$ord_line['from'];
		$this->to=$ord_line['to'];
		$this->validity=$ord_line['validity'];
		$this->inv_n=$ord_line['inv_n'];
		$this->model_id=$ord_line['model_id'];
		$this->cat_id=$ord_line['cat_id'];
		$this->type2=$ord_line['type2'];
		$this->client_id=$ord_line['client_id'];
		$this->info=$ord_line['info'];
		$this->web=$ord_line['web'];
		$this->cr_time=$ord_line['cr_time'];
		$this->cr_who_id=$ord_line['cr_who_id'];
		$this->ch_time=$ord_line['ch_time'];
		$this->ch_who_id=$ord_line['ch_who_id'];
		$this->status=$ord_line['status'];
		$this->appr_id=$ord_line['appr_id'];
		$this->appr_time=$ord_line['appr_time'];
		$this->cr_ip=$ord_line['cr_ip'];
		$this->rem_type=$ord_line['rem_type'];
		$this->place_status=$ord_line['place_status'];

		$this->item_load();
		$this->rem_pics();
		$this->stirka();
	}
	
	function stirka () {
		$query_or = "SELECT * FROM rent_orders WHERE inv_n='$this->inv_n' AND type2='stirka'";
		$result_or = $this->mysqli->query($query_or);
		if (!$result_or) {die('Сбой при доступе к базе данных: '.$query_or.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
		$r_num = $result_or->num_rows;
        $stir_rez = $result_or->fetch_assoc();
		if ($r_num>0) {
			$this->in_stirka=1;
            $this->stir_id=$stir_rez['order_id'];
		}	
	}

	function item_load() {

		if ($this->inv_n>0) {

			$query_ch = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$this->inv_n'";
			$result_ch = $this->mysqli->query($query_ch);
			if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
			$it_ch = $result_ch->fetch_assoc();
			$it_num = $result_ch->num_rows;

			if ($it_num>0) {
				$this->model_id=$it_ch['model_id'];
				$this->item_status=$it_ch['status'];
				$this->item_place=$it_ch['item_place'];
				$this->item_color=$it_ch['item_color'];
				$this->br_color=$it_ch['item_color'];
				$this->active_deal_id=$it_ch['active_deal_id'];
			}

		}
		if ($this->model_id>0) {

			$model_q = "SELECT * FROM tovar_rent WHERE tovar_rent_id='$this->model_id'";
			$result_model_def = $this->mysqli->query($model_q);
			if (!$result_model_def) {die('Сбой при доступе к базе данных: '.$model_q.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
			$model_def=$result_model_def->fetch_assoc();
			$mod_num = $result_model_def->num_rows;

			if ($mod_num>0) {
				$this->cat_id=$model_def['tovar_rent_cat_id'];
				$this->model=$model_def['model'];
				$this->producer=$model_def['producer'];
				$this->mod_color=$model_def['color'];
				if ($this->mod_color!='multicolor' || $this->inv_n<1) {// чтобы и в заявках отражались цвета
					$this->br_color=$this->mod_color;
				}
			}
			else {//если не нашло модели, значит ее кто-то удалил
				$this->failure=1;
				$this->alert.='Для order_id:'.$this->order_id.'ни одной модели не обнаружено';
			}

			if ($this->cat_id>0) {

				$cat_q="SELECT * FROM tovar_rent_cat WHERE tovar_rent_cat_id='$this->cat_id' LIMIT 1";
				$result_cat_def = $this->mysqli->query($cat_q);
				if (!$result_cat_def) {die('Сбой при доступе к базе данных3: '.$cat_q.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
				$cat_def = $result_cat_def->fetch_assoc();
				$cat_num = $result_cat_def->num_rows;

				if ($cat_num>0) {
					$this->cat_dog_name=$cat_def['dog_name'];
				}
				else {//если не нашло катеории, значит ее кто-то удалил
					$this->failure=1;
					$this->alert.='Для order_id:'.$this->order_id.'ни одной категории не обнаружено';
                }

            }


		}
	}//end of item_load
	
function web_load () {
	$query_ch = "SELECT * FROM rent_model_web WHERE model_id='$this->model_id'";
	$result_ch = $this->mysqli->query($query_ch);
	if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
	$mod_ch = $result_ch->fetch_assoc();
	$mod_num = $result_ch->num_rows;
	
	$this->small_pic=$mod_ch['m_pic_small'];
	$this->big_pic=$mod_ch['m_pic_big'];
	
	
}//end of web_load


	function lock_orders () {
		//блокируем таблицы
		$query = "LOCK TABLES tovar_rent_items WRITE, rent_orders WRITE";
		$result = $this->mysqli->query($query);
		if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
	}

	function unlock_orders() {
		//разблокируем таблицы
		$query = "UNLOCK TABLES";
		$result = $this->mysqli->query($query);
		if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
	}

	function bron_insert() {

		$this->cr_time=time();
		$this->cr_who_id=$this->user_id;

		if (in_array($this->type2, $this->strong_t2_array)) {
			$this->type='strong';
		}
		else {
			$this->type='zayavka';
		}

		if ($this->inv_n>0) {

			//блокируем таблицы
			$query = "LOCK TABLES tovar_rent_items WRITE, rent_orders WRITE";
			$result = $this->mysqli->query($query);
			if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}

			//выбираем инфо по конкретному товару (inv_n);

			$q_tov = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$this->inv_n'";
			$result_tov = $this->mysqli->query($q_tov);
			if (!$result_tov) {die('Сбой при доступе к базе данных: '.$q_tov.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
			$i_tov = $result_tov->fetch_assoc();
			if ($result_tov->num_rows!==1) {
				$this->failure=1;
				$this->alert.='при проверке товара по инв. номеру: либо товар отсутствует, либо кол-во товаров больше 1';
			}
			else {
				$this->model_id=$i_tov['model_id'];
				$this->cat_id=$i_tov['cat_id'];

				if ($i_tov['status']=='rented_out' || $i_tov['status']=='to_deliver' || $i_tov['status']=='bron') {
					$this->failure=1;
					$this->alert.='Товар уже выдан!';;
				}
				else {
					//ставим бронь на товар
					$query_upd = "UPDATE tovar_rent_items SET `status`='bron' WHERE item_inv_n='$this->inv_n'";
					$result_upd = $this->mysqli->query($query_upd);
					if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}

					//вносим бронь
					// для учета того, кто внес бронь
					if ($this->type2=='bron' || $this->type2=='deiv') {
						$this->appr_id=$this->user_id;
						$this->appr_time=time();
					}

						
					$this->insert();
						
						
					//разблокируем таблицы
					$query = "UNLOCK TABLES";
					$result = $this->mysqli->query($query);
					if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
						
				}
			}

		}//end of inv_n if
		else {
			$this->failure=1;
			$this->alert.='Не передан инвентарный номер!';
		}

		//разблокируем таблицы
		$query = "UNLOCK TABLES";
		$result = $this->mysqli->query($query);
		if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}

	}//end of function bron_insert


	function insert() {
		$query = "INSERT INTO rent_orders VALUES ('', '$this->type', '$this->order_date', '$this->from', '$this->to', '$this->validity', '$this->inv_n', '$this->model_id', '$this->cat_id', '$this->type2', '$this->client_id', '$this->info', '$this->web', '$this->cr_time', '$this->cr_who_id', '$this->ch_time', '$this->ch_who_id', '$this->status', '$this->appr_id', '$this->appr_time', '$this->cr_ip', '$this->place_status', '$this->rem_type')";
		$result = $this->mysqli->query($query);
		if (!$result) {die('Сбой при доступе к базе данных: '.$query.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}

		$this->insert_id=$this->mysqli->insert_id;
	}

	function update() {

		$query_upd = "UPDATE rent_orders SET `type`='$this->type', `order_date`='$this->order_date', `from`='$this->from', `to`='$this->to', `validity`='$this->validity', `inv_n`='$this->inv_n', `model_id`='$this->model_id', `cat_id`='$this->cat_id', `type2`='$this->type2', `client_id`='$this->client_id', `info`='$this->info', `web`='$this->web', `cr_time`='$this->cr_time', `cr_who_id`='$this->cr_who_id', `ch_time`='$this->ch_time', `ch_who_id`='$this->ch_who_id', `status`='$this->status', `appr_id`='$this->appr_id', `appr_time`='$this->appr_time', `cr_ip`='$this->cr_ip', place_status='$this->place_status', rem_type='$this->rem_type' WHERE `order_id`='$this->order_id'";
		$result_upd = $this->mysqli->query($query_upd);
		if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}

	}

	function br_load($id) {

		$query_or = "SELECT * FROM rent_orders WHERE order_id='$id'";
		$result_or = $this->mysqli->query($query_or);
		if (!$result_or) {die('Сбой при доступе к базе данных: '.$query_or.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
		$r_num = $result_or->num_rows;
		if ($r_num<1) {
			die('Не найдено ни одной брони с id:'.$id);
		}

		$ord_line = $result_or->fetch_assoc();

		$this->order_id=$id;
		$this->type=$ord_line['type'];
		$this->order_date=$ord_line['order_date'];
		$this->from=$ord_line['from'];
		$this->to=$ord_line['to'];
		$this->validity=$ord_line['validity'];
		$this->inv_n=$ord_line['inv_n'];
		$this->model_id=$ord_line['model_id'];
		$this->cat_id=$ord_line['cat_id'];
		$this->type2=$ord_line['type2'];
		$this->client_id=$ord_line['client_id'];
		$this->info=$ord_line['info'];
		$this->web=$ord_line['web'];
		$this->cr_time=$ord_line['cr_time'];
		$this->cr_who_id=$ord_line['cr_who_id'];
		$this->ch_time=$ord_line['ch_time'];
		$this->ch_who_id=$ord_line['ch_who_id'];
		$this->status=$ord_line['status'];
		$this->appr_id=$ord_line['appr_id'];
		$this->appr_time=$ord_line['appr_time'];
		$this->cr_ip=$ord_line['cr_ip'];
		$this->place_status=$ord_line['place_status'];
		$this->rem_type=$ord_line['rem_type'];
	}

	function del_br() {
		$query_del = "DELETE FROM rent_orders WHERE order_id='$this->order_id'";
		$result_del = $this->mysqli->query($query_del);
		if (!$result_del) {die('Сбой при доступе к базе данных: '.$query_del.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
	}

    function del_br_id($id) {
        $query_del = "DELETE FROM rent_orders WHERE order_id='$id'";
        $result_del = $this->mysqli->query($query_del);
        if (!$result_del) {die('Сбой при доступе к базе данных: '.$query_del.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
    }

	function arch_copy() {
		//копирование брони в архив
		$query_arch = "INSERT INTO rent_orders_arch SELECT '', '".time()."', '".$_SESSION['user_id']."', order_id, `type`, order_date, `from`, `to`, `validity`, `inv_n`, model_id, cat_id, type2, client_id, info, web, cr_time, cr_who_id, ch_time, ch_who_id, status, `appr_id`, `appr_time`, `cr_ip`, `place_status`, `rem_type` FROM rent_orders WHERE order_id='$this->order_id'";
		$result_arch = $this->mysqli->query($query_arch);
		if (!$result_arch) {die('Сбой при доступе к базе данных: '.$query_arch.' ('.$this->mysqli->connect_errno.') '.$this->mysqli->connect_error);}
			
	}

	function rem_pics() {
		switch ($this->rem_type) {
			case 'stir':
				$this->rem_pic_url='/bb/stir.png';
				break;

			case 'meh':
				$this->rem_pic_url='/bb/meh.png';
				break;
					
			case 'tex':
				$this->rem_pic_url='/bb/textil.png';
				break;

			case 'oth':
				$this->rem_pic_url='/bb/inoe.png';
				break;

			default:
				$this->rem_pic_url='';
				break;
		}
	}

}//end of class bron
?>