<?php

namespace bb;

/**
 * Class DealRow
 */
class DealRow
{

    //base fields of deal
    public $id_deal;
    public $acc_date_deal;
    public $from_deal;
    public $to_deal;
    public $first_rent_place;
    public $first_rent_delivery_yn;
    public $acc_person_id;



    //base fields of sub-deal
    public $acc_date_sub_deal;
    public $type_sub_deal;
    public $id_sub_deal;
    public $from_sub_deal;
    public $to_sub_deal; //for return, this field (if not null) means last paid rent date (period)
    public $r_to_pay_sub;
    public $delivery_to_pay_sub;
    public $place_sub_deal;
    public $info_sub_deal;
    public $cr_who_sub_deal;
    public $previous_return_date; //for sub_deal with type 'close'
    public $just_payment;//payment in a day, different from sub-deal
    public $delivery_yn_sub;
    public $rent_tenor_sub;
    public $tarif_step_sub;
    public $status_sub_deal;//act or arch
    public $status_sub_prev;//real sub_status
    public $r_paid;
    public $del_paid;
    public $cr_time_sub;
    public $l2_pic;
    public $courier_id;

    private $payment_filter_ammount;

    public static $kassa_r_total = array();//rent payment total
    //public static $kassa_d_total = array();//payments for delivery total


    /**
     * @var Payment[]
     */
    public $payments = array();

    //fields for tovar
    public $inv_n_item;
    public $item_color;
    public $cat_dog_name;
    public $producer;
    public $model_name;
    public $model_color;

    public $family;
    public $name;
    public $otch;
    public $city;
    public $str;
    public $dom;
    public $kv;
    public $phone_1;
    public $phone_2;
    public $id_client;

    public static function LoadFromArray($arr)
    {
        $result = new self();

        foreach ($result as $key => $value) {

            if (isset($arr[$key])) {
                $result->$key = $arr[$key];
            }
            //            $real_key=db_field($key);
//            if (isset($arr[$real_key])) {
//                $result->$key = $arr[$real_key];
//            }
        }

        return $result;
    }


    /**
     * @param $acc_date
     * @return DealRow[]
     */
    public static function GetLines($acc_date, $place, $sub_type = 'all', $payment_type, $cur = 'no_filter')
    {
        $date = strtotime($acc_date);

        /**
         * @var DealRow[]
         */
        $subs = array();

        $subs = self::LineEngine('act', $date, $place, $payment_type, $cur);
        $subs += self::LineEngine('arch', $date, $place, $payment_type, $cur);
        //echo '<pre>';
//print_r($subs);
//echo '</pre>';

        if ($sub_type != 'all') {
            foreach ($subs as $key => $value) {
                if ($subs[$key]->type_sub_deal != $sub_type)
                    unset($subs[$key]);
            }
        }
        if ($payment_type != 'all') {
            foreach ($subs as $key => $value) {
                if (($subs[$key]->r_paid + $subs[$key]->del_paid) == 0)
                    unset($subs[$key]);
            }
        }

        krsort($subs);

        return $subs;
    }

    private static function LineEngine($table, $date, $place, $payment_type, $cur = 'no_filter')
    {
        /**
         * @var DealRow[]
         */
        $subs = array();
        $pays = array();

        $result = self::GetSubDeals('-1', $table, $date, $place, $cur);
        //echo 'Количество строк:'.$result->num_rows.'<br>';
        while ($row = $result->fetch_assoc()) {
            //print_r($row); echo'<br><br>';
            /**
             * @var DealRow[] $subs
             * @var DealRow $line
             */
            if ($row['type_sub_deal'] == 'first_rent' || $row['type_sub_deal'] == 'close' || $row['type_sub_deal'] == 'extention' || $row['type_sub_deal'] == 'takeaway_plan' || $row['type_sub_deal'] == 'cur_return') {
                $line = DealRow::LoadFromArray($row);
                if ($table == 'act') {
                    $line->status_sub_deal = 'act';
                } else {
                    $line->status_sub_deal = 'arch';
                }

                if ($line->producer == '') {//если товар в архиве (удален), то подгружаем по отделььному запросу
                    $db = Db::getInstance();
                    $mysqli = $db->getConnection();

                    $sql_query = "

                    SELECT
                    
                    tovar_rent_items_arch.item_color AS item_color,
                    
                    tovar_rent.producer, tovar_rent.model AS model_name, tovar_rent.color AS model_color, tovar_rent_cat.dog_name AS cat_dog_name

                    FROM tovar_rent_items_arch

                    LEFT JOIN tovar_rent ON tovar_rent_items_arch.model_id=tovar_rent.tovar_rent_id
                    LEFT JOIN tovar_rent_cat ON tovar_rent.tovar_rent_cat_id=tovar_rent_cat.tovar_rent_cat_id

                    WHERE tovar_rent_items_arch.item_inv_n='$line->inv_n_item'
                    LIMIT 1
                    ";

                    //echo '<br>'.$sql_query;
                    $result2 = $mysqli->query($sql_query);
                    //                    if (!is_object($result2)) {
//                        echo $sql_query;
//                    }
                    if ($result2->num_rows > 0) {
                        $row = $result2->fetch_assoc();
                        foreach ($row as $key => $value) {
                            $line->$key = $row[$key];
                        }
                    } else {
                        $line->model_name = 'Товар не найден в базе (удален)';
                    }
                }

                $subs[$line->id_sub_deal] = $line;
            } else {//if payment

                $p = Payment::LoadFromArray($row);
                DealRow::payment_count($p);

                if ($payment_type != 'all') {
                    if ($p->r_payment_type != $payment_type)
                        $p->r_paid = 0;
                    if ($p->del_payment_type != $payment_type)
                        $p->delivery_paid = 0;
                    if (($p->r_paid + $p->delivery_paid) > 0)
                        $pays[] = $p;
                } else
                    $pays[] = $p;
            }
        }
        //        echo '<pre>';
//        print_r($subs);
//        print_r($pays);
//        echo '</pre>';

        //Base::varDamp($pays);

        foreach ($pays as $payment) {//connecting payment to deals
            if ($payment->link < 1) {
                continue;
                echo '<pre>';
                var_dump($payment);
                echo '</pre>';
            }

            /**
             * @var Payment $payment
             */
            if (isset($subs[$payment->link])) {
                $subs[$payment->link]->payments[] = $payment;
                $subs[$payment->link]->r_paid += $payment->r_paid;
                $subs[$payment->link]->del_paid += $payment->delivery_paid;
                //$subs[$payment->link]->payment_count($payment);
            } else {//only payment (not sub_deal this day)   earlier instead of ->id_sub_deal was ->link I've changed due to sort difficulties
                //print_r($payment);
                if ($payment->id_sub_deal > 0 && isset($subs[$payment->id_sub_deal])) {
                    echo 'Передайте скриншот Диме. <br>fatal error: payment id (' . $payment->id_sub_deal . ') of pure payment operation is already exists as sub_deal_id in the main array';
                    //                    echo '<pre>';
//                    var_dump($payment);
//                    echo '</pre>';
                }
                $subs[$payment->id_sub_deal] = DealRow::GetLine($payment->link);
                $subs[$payment->id_sub_deal]->payments[] = $payment;
                $subs[$payment->id_sub_deal]->just_payment = 1;
                $subs[$payment->id_sub_deal]->acc_date_sub_deal = $payment->acc_date_sub_deal;

                $subs[$payment->id_sub_deal]->r_paid += $payment->r_paid;
                $subs[$payment->id_sub_deal]->del_paid += $payment->delivery_paid;
                //$subs[$payment->link]->payment_count($payment);
            }
        }

        return $subs;
    }

    public static function GetSubDeals($sub_id = -1, $table, $date = '', $place = '', $cur = 'no_filter')
    {
        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        if ($sub_id == -1) {//main request
            $where_close = "WHERE sub1.acc_date='$date'";
            if ($place != 'all')
                $where_close .= " AND sub1.`place`=$place";
            if ($cur == 'all_not_delivered') {
                $where_close = "WHERE sub1.acc_date>'$date' AND sub1.status='for_cur'";
            }

        } else {
            $where_close = "WHERE sub1.sub_deal_id='$sub_id'";
        }

        $sql_query = "
      SELECT 

sub1.sub_deal_id AS id_sub_deal, sub1.from AS from_sub_deal, sub1.to as to_sub_deal, sub1.r_to_pay AS r_to_pay_sub, sub1.delivery_to_pay AS delivery_to_pay_sub, sub1.place AS place_sub_deal, sub1.info AS info_sub_deal, sub1.cr_who_id AS cr_who_sub_deal, sub1.cr_time AS cr_time_sub, sub1.acc_date AS acc_date_sub_deal, sub1.type AS type_sub_deal, sub1.delivery_yn as delivery_yn_sub, sub1.rent_tenor as rent_tenor_sub, sub1.tarif_step as tarif_step_sub, sub1.status AS status_sub_prev, courier_id,

sub1.r_paid, sub1.delivery_paid, sub1.r_payment_type, sub1.del_payment_type, sub1.ch_num, sub1.link,

dl1.start_date AS acc_date_deal, dl1.deal_id AS id_deal, dl1.start_date AS from_deal, dl1.return_date AS to_deal, dl1.first_rent_place, dl1.delivery_yn AS first_rent_delivery_yn, dl1.item_inv_n AS inv_n_item, dl1.acc_person_id,

tovar_rent_items.item_color AS item_color,
        
tovar_rent.producer, tovar_rent.model AS model_name, tovar_rent_cat.dog_name AS cat_dog_name, tovar_rent.color AS model_color,

rent_model_web.l2_pic,

clients.family, clients.name, clients.otch, clients.city, clients.str, clients.dom, clients.kv, clients.phone_1, clients.phone_2, clients.client_id AS id_client
        
        FROM `rent_sub_deals_$table` AS sub1
        
        LEFT JOIN rent_deals_$table AS dl1 ON sub1.deal_id=dl1.deal_id
        
        LEFT JOIN tovar_rent_items ON dl1.item_inv_n=tovar_rent_items.item_inv_n
        LEFT JOIN tovar_rent ON tovar_rent_items.model_id=tovar_rent.tovar_rent_id
        LEFT JOIN tovar_rent_cat ON tovar_rent.tovar_rent_cat_id=tovar_rent_cat.tovar_rent_cat_id
        LEFT JOIN rent_model_web ON tovar_rent.tovar_rent_id=rent_model_web.model_id
        LEFT JOIN clients ON dl1.client_id=clients.client_id AND sub1.type NOT IN ('payment', 'cl_payment')

        " . $where_close;

        //        if ($sub_id>0) {
//            echo '<br><br>'.$sql_query.'<br><br>';
//        }
//        echo '<br>'.$sql_query.'<br><br>';


        $result = $mysqli->query($sql_query);
        return $result;
    }

    public static function GetLine($sub_id)
    {
        if (!$sub_id > 0) {
            throw new \Exception('sub_deal_id should be more than 0');
        }

        $result = self::GetSubDeals($sub_id, 'act');
        if ($result->num_rows < 1)
            $result = self::GetSubDeals($sub_id, 'arch');

        $line = new self();

        if ($row = $result->fetch_assoc()) {
            $line = DealRow::LoadFromArray($row);
        } else {
            $line->type_sub_deal = 'Оибка - не нашли ни одной записи по оплате в день, отличный от сделки' . $sub_id;
        }

        return $line;
    }

    public function operation_print()
    {
        if ($this->just_payment == 1) {
            $add_just = 'оплата: ';
        } else
            $add_just = '';

        $sub_op_tenor = '<br /><i> на ' . number_format($this->rent_tenor_sub, 0, ',', ' ') . ' ' . $this->step_print() . '</i>';

        switch ($this->type_sub_deal) {
            case 'first_rent':
                $output = $add_just . 'выдача';
                break;

            case 'takeaway_plan':
                $output = $add_just . 'бронь';
                break;

            case 'extention':
                $output = $add_just . 'продление';
                break;

            case 'close':
            case 'cur_return':
                $output = $add_just . 'возврат';
                $sub_op_tenor = '';
                break;

            //            case 'payment':
//            case 'cl_payment':
//                $output='оплата';
//                break;

            default:
                $output = $add_just . $this->type_sub_deal;
                break;
        }

        if ($this->delivery_yn_sub == '1') {
            $output .= '<strong> курьером</strong>';
        }

        if ($this->acc_date_sub_deal != $this->from_sub_deal) {
            $output .= ' c ' . date("d.m.y", $this->from_sub_deal);
        }

        $output .= $sub_op_tenor;

        return $output;
    }

    public function step_print()
    {
        switch ($this->tarif_step_sub) {
            case 'day':
                return 'дн.';
                break;

            case 'week':
                return 'нед.';
                break;

            case 'month':
                return 'мес.';
                break;

            default:
                return '-';
                break;
        }
    }

    public function inv_n_print()
    {

        $output = substr($this->inv_n_item, 0, 3) . '-' . substr($this->inv_n_item, 3);

        return $output;

    }

    public function FirstPlacePic()
    { //do not forgea about arch and act
        if (!($this->first_rent_place > 1)) {
            $db = Db::getInstance();
            $mysqli = $db->getConnection();
            if ($this->status_sub_deal == 'act') {
                $sql_query = "SELECT place FROM rent_sub_deals_act WHERE deal_id='$this->id_deal' AND (`type`='first_rent' OR `type`='takeaway_plan')";
            } else {
                $sql_query = "SELECT place FROM rent_sub_deals_arch WHERE deal_id='$this->id_deal' AND (`type`='first_rent' OR `type`='takeaway_plan')";
            }

            //echo $sql_query;
            $result = $mysqli->query($sql_query);
            $row = $result->fetch_assoc();
            if ($row['place'] > 0)
                $this->first_rent_place = $row['place'];
        }

        if ($this->first_rent_delivery_yn == 1) {
            $output = '<img src="/images/k.png" title="Выдано курьером" style="position:absolute; right:0; top:0; width:25px; heght:25px;" />';
        } else {
            $output = '<img src="/images/' . $this->first_rent_place . '.gif" title="Выдано на офисе №' . $this->first_rent_place . '" style="position:absolute; right:0; top:0; width:25px; heght:25px;">';
        }



        return $output;

    }

    public function LastExtensionDatePrint()
    {
        if ($this->type_sub_deal == 'close' || $this->type_sub_deal == 'cur_return') {
            if (!($this->to_sub_deal > 0)) {
                $db = Db::getInstance();
                $mysqli = $db->getConnection();
                if ($this->status_sub_deal == 'act') {
                    $sql_query = "SELECT `to` FROM rent_sub_deals_act WHERE deal_id='$this->id_deal' AND `type` IN ('first_rent', 'extention') ORDER BY sub_deal_id DESC LIMIT 1";
                } else {
                    $sql_query = "SELECT `to` FROM rent_sub_deals_arch WHERE deal_id='$this->id_deal' AND `type` IN ('first_rent', 'extention') ORDER BY sub_deal_id DESC LIMIT 1";
                }

                //echo $sql_query;
                $result = $mysqli->query($sql_query);
                $row = $result->fetch_assoc();
                if ($row['to'] > 0)
                    $this->to_sub_deal = $row['to'];
            }
            return '<br>(' . date("d.m.y", $this->to_sub_deal) . ')';
        }
        return '';
    }

    public function PrintPayment($form_print = 'yes')
    {
        $output = '';
        foreach ($this->payments as $payment) {

            if ($payment->r_paid != 0)
                $output .= '<strong>' . number_format($payment->r_paid, 2, ',', ' ') . $payment->KassaPrint($payment->r_payment_type) . '</strong>';
            if ($payment->delivery_paid != 0)
                $output .= '<span class="deliv_num">' . number_format($payment->delivery_paid, 2, ',', ' ') . '</span>';

            if ($form_print == 'yes') {
                if ($payment->r_payment_type != 'nal_no_cheque' && ($payment->r_paid + $payment->delivery_paid) != 0) {
                    $output .= '<br> <i class="ch_n"><a href="#" onclick="ch_num_show(\'' . $payment->id_sub_deal . 'd' . '\'); return false;">' . ($payment->ch_num != '' ? $payment->ch_num : '<span style="color:red;">!внесите!</span>') . '</a>
                <div class="ch_div_st" id="ch_div_' . $payment->id_sub_deal . 'd">
                    <input type="text" name="ch_num_new" id="ch_num_new_' . $payment->id_sub_deal . 'd" value="' . $payment->ch_num . '" />
                    <input type="hidden" id="ch_num_id_' . $payment->id_sub_deal . 'd" value="' . $payment->id_sub_deal . '" /><br />
                    <input type="button" value="обновить" onclick="ch_num_save(\'' . $payment->id_sub_deal . 'd\');" />
                    <input type="button" value="отмена" onclick="ch_num_close(\'' . $payment->id_sub_deal . 'd\');" />
                </div>
                </i>';//добавляем номер чека !!!делаем корректировку на d для оставки, чтобы избежать одинаковых id полей
                }
            }
            $output .= '<br>';

        }


        return $output;
    }

    public function PrintSubInfo()
    {
        $output = '';

        $output .= '<a href="#" onclick="sub_show(\'' . $this->id_sub_deal . '\'); return false;">' . ($this->info_sub_deal != '' ? $this->info_sub_deal : 'внести доп. инфо') . '</a>
                <div class="sub_info" id="sub_info_div_' . $this->id_sub_deal . '">
                    <textarea name="sub_info_new" id="sub_info_new_' . $this->id_sub_deal . '" >' . $this->info_sub_deal . '</textarea>
                    <input type="hidden" id="sub_deal_id_' . $this->id_sub_deal . '" value="' . $this->id_sub_deal . '" /><br />
                    <input type="button" value="обновить" onclick="sub_info_save(\'' . $this->id_sub_deal . '\');" />
                    <input type="button" value="отмена" onclick="sub_info_close(\'' . $this->id_sub_deal . '\');" />
                </div>
                ';


        return $output;

    }

    /**
     * @return string
     */
    public function ClientPrint()
    {
        return $this->family . ' ' . $this->name . ' ' . $this->otch . ', ' . $this->city . ', ул. ' . $this->str . ' ' . $this->dom . '-' . $this->kv . '<br> тел.:' . $this->phone_print($this->phone_1) . ', ' . $this->phone_print($this->phone_2);
    }

    /**
     * @return string
     */
    public function getFIO()
    {
        return $this->family . ' ' . $this->name . ' ' . $this->otch;
    }

    /**
     * @return string
     */
    public function getClientAddressLiving()
    {
        return $this->city . ', ' . $this->str . ' ' . $this->dom . '-' . $this->kv;
    }

    /**
     * @return string
     */
    public function getAddrGoogleUrl()
    {
        return 'https://google.com/maps/search/?api=1&query=' . urldecode($this->city . ', ' . $this->str . ' ' . $this->dom);
    }

    /**
     * @return string
     */
    public function getItemModelText()
    {
        if ($this->model_color == 0 || $this->model_color == 'multicolor' || $this->model_color == '') {
            if ($this->item_color == 0 || $this->item_color == '') {
                $color = '';
            } else {
                $color = $this->item_color;
            }
        } else {
            $color = $this->model_color;
        }
        return $this->cat_dog_name . ' ' . $this->model_name . ' ' . $this->producer . ($color != '' ? '(' . $color . ')' : '');
    }

    function phone_print($ph)
    {
        if ($ph == '') {
            return '';
        }

        $dl = strlen($ph);

        if ($dl < 7) {
            return $ph;
        }

        $dl > 7 ? $dl_to = $dl - 7 : $dl_to = 0;
        $ph_out = substr($ph, 0, $dl_to) . '-' . substr($ph, -7, 3) . '-' . substr($ph, -4, 2) . '-' . substr($ph, -2, 2);
        return $ph_out;

    }

    public function ActionPrint()
    {
        if ($this->status_sub_deal == 'act') {
            return '
                <form method="post" action="dogovor_new.php">
					<input type="hidden" name="item_inv_n" value="' . $this->inv_n_item . '" />
					<input type="hidden" name="client_id" value="' . $this->id_client . '" />
					<input type="submit" value="к договору" />
				</form>';
        } else {
            return '
                <form method="post" action="deals_arch.php">
					<input type="hidden" name="deal_id" value="' . $this->id_deal . '" />
					<input type="submit" name="action" value="в архив" />
				</form>';
        }

    }

    public static function sel_d($value, $pattern)
    {
        if ($value == $pattern) {
            return 'selected="selected"';
        } else {
            return '';
        }
    }

    public static function payment_count(Payment $payment)
    {
        //        $this->r_paid+=$payment->r_paid;
//        $this->del_paid+= $payment->delivery_paid;

        //записываем тоталы все в 1 массив
        if (isset(self::$kassa_r_total[$payment->r_payment_type])) {//rent total
            self::$kassa_r_total[$payment->r_payment_type] += $payment->r_paid;
        } else {
            self::$kassa_r_total[$payment->r_payment_type] = $payment->r_paid;
        }
        if (isset(self::$kassa_r_total[$payment->del_payment_type])) {//delivery total
            self::$kassa_r_total[$payment->del_payment_type] += $payment->delivery_paid;
        } else {
            self::$kassa_r_total[$payment->del_payment_type] = $payment->delivery_paid;
        }
    }

    public function SubColorRowStyle()
    {
        switch ($this->status_sub_prev) {
            case 'for_cur':
                return 'style="background-color:#80C4F0"';
                break;
            case 'delivered':
                return 'style="background-color:#C4F4F2"';
                break;
            case 'takeaway_plan':
                return 'style="background-color:#FF0"';
                break;
            default:
                if ((($this->r_to_pay_sub + $this->delivery_to_pay_sub) - ($this->r_paid + $this->del_paid)) > 2) {
                    return 'style="background-color:#F5C138"';
                }

                break;
        }
    }

    public function DeveloperInfo()
    {
        $result = '';
        if ($_SESSION['user_id'] == 3) {
            $result .= '<br>(
            sub_id=' . $this->id_sub_deal . '<br>
            dl_id=' . $this->id_deal . '<br>
            acc_date=' . $this->acc_date_sub_deal . '
            )';
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isCurSubDeal()
    {
        if ($this->status_sub_prev == 'for_cur' || $this->status_sub_prev == 'delivered') {
            return true;
        } else {
            return false;
        }
    }
}

?>