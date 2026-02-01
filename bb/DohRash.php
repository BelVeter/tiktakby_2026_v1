<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 08.10.2018
 * Time: 21:56
 */

namespace bb;


class DohRash
{
public $id_doh_rash;
public $acc_date;
public $amount;
public $channel;
public $kassa;
public $info;
public $type2;
public $cr_who_id;
public $cr_time;

public static $doh_items = array(
    'av_return'=>'возврат аванса',
    'prod_tovar'=>'продажа товара',
    'other'=>'прочие',
    'bank_interest'=>'проценты банка',
    'money_plus'=>'недостача (плюсовая)',
    'vznos_plus'=>'Взнос учредителя',
);

public static $rach_items = array(
    'tovar'=>'покупка товара',
    'avans'=>'аванс на расходы',
    'double_benz'=>'двойной бензин',
    'fuel'=>'топливо',
    'car'=>'машина (не топливо)',
    'zpl'=>'зарплата',
    'remont_tov'=>'ремонт товара',
    'op_rash'=>'операционные расходы',
    'other'=>'прочие расходы',
    'dividends'=>'амортизация_',
    'debt_rep'=>'возврат кредитов',
    'adv'=>'реклама',
    'fszn_tax'=>'ФСЗН',
    'pod_tax'=>'Подоходный налог',
    'bgs_tax'=>'Белгосстрах',
    'ed_nal_tax'=>'Единый налог',
    'bank_fee'=>'Комиссия банка',
    'invest'=>'вложения (инвестиции)',
    'of1_rent'=>'Аренда Машерова',
    'of2_rent'=>'Аренда Ложинская',
    'money_loss'=>'недостача',
    'connect'=>'связь',
    'vznos_return'=>'Возврат взноса учредителя',
    'r3_rent'=>'Аренда Победителей',
);


public static function load_from_db($db_result) {
    $dr = new self();
    $dr->id_doh_rash=$dr['dr_id'];
    $dr->kassa=$dr['kassa'];
    $dr->amount=$dr['amount'];


}

    /**
     * @param $date
     * @param $channel
     * @return DohRash[]
     */
    public static function GetRashs($date, $channel){
    /**
     * @var DohRash[]
     */
    $dr_reult=array();

    $db = Db::getInstance();
    $mysqli = $db->getConnection();

    $sql_query = "SELECT dr_id AS id_doh_rash, `acc_date`, `amount`, `channel`, `kassa`, `info`, `type2`, `cr_who_id`, `cr_time` WHERE acc_date='$date' AND `channel`='$channel'";
    $result = $mysqli->query($sql_query);

    while ($row = $result->fetch_assoc()) {
        $dr_reult[]=self::LoadFromArray($row);
    }
    return $dr_reult;
}

public static function RdaRashs($date, $office){

        $drs= DohRash::GetRashs($date, $office);

        $result ='
        <table border="1" cellspacing="0" style="clear: both;" id="rash_table">
            <tbody><tr>
                <th>дата</th>
                <th>касса</th>
                <th>сумма</th>
                <th>тип</th>
                <th>информация</th>
                <th>кто?</th>
            </tr>
                ';
        foreach ($drs as $dr) {
            $result.='
            <tr>
                <td>'.$date("d.m.y", $dr->acc_date).'</td>
                <td>'.$dr->kassa.'</td>
                <td>'.number_format($dr->amount, 2, ',', ' ').'</td>
                <td>'.($dr->amount<0 ? self::$rach_items[$dr->type2] : self::$doh_items[$dr->type2]).'</td>
                <td>'.$dr->info.'</td>
                <td>'.User::GetUserName($dr->cr_who_id).' ('.date("H:i", $dr->cr_who_id).')</td>
            </tr>
            ';
        }

        $result.='</tbody></table>';
}


    public static function LoadFromArray($arr)
    {
        $result = new self();

        foreach ($result as $key => $value) {

            if (isset($arr[$key])) {
                $result->$key=$arr[$key];
            }
//            $real_key=db_field($key);
//            if (isset($arr[$real_key])) {
//                $result->$key = $arr[$real_key];
//            }
        }

        return $result;
    }



}