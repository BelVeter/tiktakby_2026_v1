<?php

namespace bb;


class Payment
{
    public $id_sub_deal;
    public $r_paid;
    public $delivery_paid;
    public $r_payment_type;
    public $del_payment_type;
    public $ch_num;
    public $acc_date_sub_deal;
    public $type_sub_deal;
    public $link;


    public static function LoadFromArray($arr){
        //print_r($arr);
        $result = new self();

        foreach ($result as $key => $value) {
            //echo 'key:'.$key.', ';
            if (isset($arr[$key])) {
                $result->$key = $arr[$key];
            }
        }

        //echo '<br><br>';
        //print_r($result);

        return $result;
    }

    public function KassaPrint ($kassa) {
        switch ($kassa) {
            case 'nal_no_cheque':
                return 'к2';
                break;

            case 'nal_cheque':
                return 'к1';
                break;

            case 'card':
                return 'кт';
                break;

            case 'bank':
                return 'бк';
                break;

            case '':
            case '0':
            case 'no_payment':
                return '';
                break;

            default:
                return 'ХЗК';
                break;
        }
    }


}