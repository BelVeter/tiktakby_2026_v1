<?php
namespace bb;

class tovar {

	public $mysqli;
	private $db_hostname;
	private $db_username;
	private $db_password;
	private $db_database;
	
	public $item_id;
	public $cat_id;
	public $producer;
	public $model_id;
	public $agr_price;
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
	public $status;
	public $active_deal_id;
	public $item_color;
	public $item_place;
	public $br_time;
	public $state;
	public $to_move;
	
	public $user_id;
	
	
	//for del
	public $out_status;
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
	

	function __construct($mysqli=NULL) {//передаем строчку (массив) из mysql запроса

	    try {
            $this->mysqli = \bb\Db::getInstance()->getConnection();
        }
        catch (\Exception $ex) {
            if ($mysqli == NULL) {
                /*//require_once ($_SERVER['DOCUMENT_ROOT'].'/dimanay2.php'); // подключаем базу данных
                $this->db_hostname = '127.0.0.1';
                $this->db_database = 'tiktak';
                $this->db_username = 'veter';
                $this->db_password = 'mb8941';
                */
                $this->db_hostname = '127.0.0.1';
                $this->db_database = 'tiktakby_tiktak';
                $this->db_username = 'robot';
                $this->db_password = 'mb8941';

                //подключаемся к mysqlсерверу
                $this->mysqli = new \mysqli($this->db_hostname, $this->db_username, $this->db_password, $this->db_database);
                if ($this->mysqli->connect_error) {
                    die('Ошибка соединения с MYSQL сервером: (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
                }

                // выбор правильной кодировки при работе с БД
                $this->mysqli->query('set character_set_client="utf8"'); // в какой кодировке получать данные от клиента
                $this->mysqli->query('set character_set_results="utf8"'); // в какой кодировке получать данные от БД для вывода клиенту
                $this->mysqli->query('set collation_connection="utf8_general_ci"'); // кодировка в которой будут посылаться служебные команды для сервера

                global $_SESSION;
                $this->user_id = $_SESSION['user_id'];
            } else {
                $this->mysqli = \bb\Db::getInstance()->getConnection();
            }
        }
        $this->user_id=$_SESSION['user_id'];
	}// end of construct

    /**
     * @param $inv_n
     * @return tovar
     */
    public static function getTovar($inv_n) {
        $item=new self();
        $item->item_load($inv_n);
        return $item;
    }



	function item_load($item_inv_n) {

		if ($item_inv_n>0) {
			//SELECT * FROM tovar_rent_items LEFT JOIN tovar_rent_cat ON
			//tovar_rent_items.cat_id=tovar_rent_cat.tovar_rent_cat_id
			//left join tovar_rent ON
	        //tovar_rent_items.model_id=tovar_rent.tovar_rent_id
			
			$query_ch = "SELECT * FROM tovar_rent_items 
					LEFT JOIN tovar_rent ON tovar_rent_items.model_id=tovar_rent.tovar_rent_id
					LEFT JOIN tovar_rent_cat ON tovar_rent.tovar_rent_cat_id=tovar_rent_cat.tovar_rent_cat_id
			 		WHERE tovar_rent_items.item_inv_n='$item_inv_n'";
			$result_ch = $this->mysqli->query($query_ch);
			if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}

			if($result_ch->num_rows<1) {
                $query_ch = "SELECT * FROM tovar_rent_items_arch 
					LEFT JOIN tovar_rent ON tovar_rent_items_arch.model_id=tovar_rent.tovar_rent_id
					LEFT JOIN tovar_rent_cat ON tovar_rent.tovar_rent_cat_id=tovar_rent_cat.tovar_rent_cat_id
			 		WHERE tovar_rent_items_arch.item_inv_n='$item_inv_n'";
                $result_ch = $this->mysqli->query($query_ch);
                if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
            }





			$it_ch = $result_ch->fetch_assoc();
			$it_num = $result_ch->num_rows;

				$this->item_id=$it_ch['item_id'];
				$this->cat_id=$it_ch['cat_id'];
				$this->producer=$it_ch['producer'];
				$this->model_id=$it_ch['model_id'];
				$this->item_n=$it_ch['item_n'];
				$this->item_inv_n=$it_ch['item_inv_n'];
				$this->sex=$it_ch['sex'];
				$this->item_size=$it_ch['item_size'];
				$this->real_item_size=$it_ch['real_item_size'];
				$this->item_rost1=$it_ch['item_rost1'];
				$this->item_rost2=$it_ch['item_rost2'];
				$this->item_set=$it_ch['item_set'];
				$this->buy_date=$it_ch['buy_date'];
				$this->buy_price=$it_ch['buy_price'];
				$this->buy_price_cur=$it_ch['buy_price_cur'];
				$this->exch_to_byr=$it_ch['exch_to_byr'];
				$this->seller=$it_ch['seller'];
				$this->item_info=$it_ch['item_info'];
				$this->cr_ch_date=$it_ch['cr_ch_date'];
				$this->user=$it_ch['user'];
				$this->status=$it_ch['status'];
				$this->active_deal_id=$it_ch['active_deal_id'];
				$this->item_color=$it_ch['item_color'];
				$this->item_place=$it_ch['item_place'];
				$this->br_time=$it_ch['br_time'];
				$this->state=$it_ch['state'];
				$this->to_move=$it_ch['to_move'];
				
				$this->cat_name=$it_ch['rent_cat_name'];
				$this->cat_dog_name=$it_ch['dog_name'];
				$this->model_color=$it_ch['color'];
				$this->model_name=$it_ch['model'];
				$this->model_set=$it_ch['set'];
				$this->agr_price=$it_ch['agr_price'];
				
				
		}
	}//end of item_load

	function item_id_load($item_id) {
	
		if ($item_id>0) {
	
			$query_ch = "SELECT * FROM tovar_rent_items WHERE item_id='$item_id'";
			$result_ch = $this->mysqli->query($query_ch);
			if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
			$it_ch = $result_ch->fetch_assoc();
			$it_num = $result_ch->num_rows;
	
			$this->item_id=$it_ch['item_id'];
			$this->cat_id=$it_ch['cat_id'];
			$this->producer=$it_ch['cat_id'];
			$this->model_id=$it_ch['model_id'];
			$this->item_n=$it_ch['item_n'];
			$this->item_inv_n=$it_ch['item_inv_n'];
			$this->sex=$it_ch['sex'];
			$this->item_size=$it_ch['item_size'];
			$this->real_item_size=$it_ch['real_item_size'];
			$this->item_rost1=$it_ch['item_rost1'];
			$this->item_rost2=$it_ch['item_rost2'];
			$this->item_set=$it_ch['item_set'];
			$this->buy_date=$it_ch['buy_date'];
			$this->buy_price=$it_ch['buy_price'];
			$this->buy_price_cur=$it_ch['buy_price_cur'];
			$this->exch_to_byr=$it_ch['exch_to_byr'];
			$this->seller=$it_ch['seller'];
			$this->item_info=$it_ch['item_info'];
			$this->cr_ch_date=$it_ch['cr_ch_date'];
			$this->user=$it_ch['user'];
			$this->status=$it_ch['status'];
			$this->active_deal_id=$it_ch['active_deal_id'];
			$this->item_color=$it_ch['item_color'];
			$this->item_place=$it_ch['item_place'];
			$this->br_time=$it_ch['br_time'];
			$this->state=$it_ch['state'];
			$this->to_move=$it_ch['to_move'];
		}
	}//end of item_load
	
	
	function item_update() {
		//Пока меняем только места/офисы. Потом нужно доработать замену всего !!!
		$query_upd = "UPDATE tovar_rent_items SET `item_place`='$this->item_place', to_move='$this->to_move' WHERE item_id='$this->item_id'";
		$result_upd = $this->mysqli->query($query_upd);
		if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
		
	}
		
	function del_item () {
		if ($this->item_inv_n<1) {
			die ('Товар не найден. Сообщите Диме о проблеме. Скорее всего товар уже удален/перемещен.');
		}
		
		if ($this->status=='rented_out') {
			die ('Товар на руках! Сначала оформите возврат.');
		}
		if ($this->status=='to_deliver') {
			die ('Товар оформен на доставку курьером! Сначала оформите возврат.');
		}
		
		//проверка по броням
		$query_ch = "SELECT * FROM rent_orders WHERE inv_n='$this->item_inv_n' AND (type2='bron' OR type2='deliv')";
		$result_ch = $this->mysqli->query($query_ch);
		if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
		$it_num = $result_ch->num_rows;
		if ($it_num>0) {
			die ('На товар есть бронь (самовывоз либо доставка)! Сначала удалите бронь.');
		}
		
		//проверка на наличие активных сделок
			$query_ch = "SELECT * FROM rent_deals_act WHERE item_inv_n='$this->item_inv_n'";
			$result_ch = $this->mysqli->query($query_ch);
			if (!$result_ch) {die('Сбой при доступе к базе данных: '.$query_ch.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
			$it_num = $result_ch->num_rows;
				
			if ($it_num>0) {
				die ('Операция не удалась. Удалите активные сделки.');
			}
		
		//переносим в архив	
		$a_date=getdate(time());
		$arch_date=mktime(0, 0, 0, $a_date['mon'], ($a_date['mday']), $a_date['year']);
			
		$query_ins = "INSERT INTO tovar_rent_items_arch VALUES('', '$arch_date', '".time()."', '$this->user_id', '$this->out_status', '$this->sell_amount_byr', '$this->rent_payment_type', '$this->sell_amount_usd', '$this->item_del_info', '$this->item_id', '$this->cat_id', '$this->producer', '$this->model_id', '$this->item_n', '$this->item_inv_n', '$this->sex', '$this->item_size', '$this->real_item_size', '$this->item_rost1', '$this->item_rost2', '$this->item_set', '$this->buy_date', '$this->buy_price', '$this->buy_price_cur', '$this->exch_to_byr', '$this->seller', '$this->item_info', '$this->cr_ch_date', '$this->user', '$this->status', '$this->active_deal_id', '$this->item_color', '$this->item_place', '$this->br_time', '$this->state', '$this->to_move', '')";
		$result_ins = $this->mysqli->query($query_ins);
		if (!$result_ins) {die('Сбой при доступе к базе данных: '.$query_ins.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
			
				
		$query_del = "DELETE FROM tovar_rent_items WHERE item_id='$this->item_id'";
		$result_del = $this->mysqli->query($query_del);
		if (!$result_del) {die('Сбой при доступе к базе данных: '.$query_del.' ('.$this->mysqli->connect_errno.') '. $this->mysqli->connect_error);}
	
	$this->return_info='Товар удален успешно!';	
			
	}// end of del_item		
		
		
    function getDogTextFull() {

	    if ($this->model_color=0) {
            $color_text = '';
        }
        elseif ($this->model_color='multicolor') {
	        $color_text=', цвет: '.$this->item_color;
        }
        else {
            $color_text=', цвет: '.$this->model_color;
        }

	    $rez= $this->cat_dog_name.': '.$this->model_name.$color_text.'пр-во: '.$this->producer.',(в комплекте: '.$this->model_set.') (инв.№'.$this->item_inv_n.').';

	    return $rez;
    }
		
		

	

}//end of class bron
?>