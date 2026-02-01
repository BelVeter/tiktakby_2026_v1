<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 21.10.2018
 * Time: 19:40
 */

namespace bb;


class Base
{
    /**
     * @param string $header
     * @return string
     */
    public static function PageStartHTML($header='No header') {
        return '
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
            <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>'.$header.'</title>
            </head>
            <body>
        ';
    }

    public static function varDamp ($var) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    }

    /**
     * @return string
     */
    public static function PageEndHTML() {
        return'
        </body>
        </html>
        ';
    }


    /**
     *
     */
    public static function PostCheck(){
        //Проверка входящей информации
        echo "<div style='clear: both;'></div>Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
        foreach ($_POST as $key => $value) {
            echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
        }
        echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";
    }


    /**
     * @param $var
     * @return string
     */
    public static function GetPost($var) {
        $db = Db::getInstance();
        $mysqli = $db->getConnection();
        return $mysqli->real_escape_string($_POST[$var]);
    }

    public static function GetAllPostGlobal() {
        foreach ($_POST as $key => $value) {
            global $$key;
            $$key=self::GetPost($key);
        }
    }

    /**
     * @param $n
     * @return string
     */
    public static function sum2words($n) {
        $words=array(
            900=>'девятьсот',
            800=>'восемьсот',
            700=>'семьсот',
            600=>'шестьсот',
            500=>'пятьсот',
            400=>'четыреста',
            300=>'триста',
            200=>'двести',
            100=>'сто',
            90=>'девяносто',
            80=>'восемьдесят',
            70=>'семьдесят',
            60=>'шестьдесят',
            50=>'пятьдесят',
            40=>'сорок',
            30=>'тридцать',
            20=>'двадцать',
            19=>'девятнадцать',
            18=>'восемнадцать',
            17=>'семнадцать',
            16=>'шестнадцать',
            15=>'пятнадцать',
            14=>'четырнадцать',
            13=>'тринадцать',
            12=>'двенадцать',
            11=>'одиннадцать',
            10=>'десять',
            9=>'девять',
            8=>'восемь',
            7=>'семь',
            6=>'шесть',
            5=>'пять',
            4>'четыре',
            3=>'три',
            2=>'два',
            1=>'один',
        );

        $level=array(
            4=>array('миллиард', 'миллиарда', 'миллиардов'),
            3=>array('миллион', 'миллиона', 'миллионов'),
            2=>array('тысяча', 'тысячи', 'тысяч'),
        );

        list($rub,$kop)=explode('.',number_format($n,2));
        $parts=explode(',',$rub);

        for($str='', $l=count($parts), $i=0; $i<count($parts); $i++, $l--) {
            if (intval($num=$parts[$i])) {
                foreach($words as $key=>$value) {
                    if ($num>=$key) {
                        // Fix для одной тысячи
                        if ($l==2 && $key==1) {
                            $value='одна';
                        }
                        // Fix для двух тысяч
                        if ($l==2 && $key==2) {
                            $value='две';
                        }
                        $str.=($str!=''?' ':'').$value;
                        $num-=$key;
                    }
                }
                if (isset($level[$l])) {
                    $str.=' '.self::num2word($parts[$i],$level[$l]);
                }
            }
        }

        if (intval($rub=str_replace(',','',$rub))) {
            $str.=' '.self::num2word($rub,array('рубль', 'рубля', 'рублей'));
        }

        $str.=($str!=''?' ':'').$kop;
        $str.=' '.self::num2word($kop,array('копейка', 'копейки', 'копеек'));

        return mb_strtoupper(mb_substr($str,0,1,'utf-8'),'utf-8').
            mb_substr($str,1,mb_strlen($str,'utf-8'),'utf-8');
    }
    //--------------------------------------------------------
// Функция для склонения числительных
//--------------------------------------------------------
    /**
     * @param $n
     * @param $words
     * @return mixed
     */
    public static function num2word($n, $words) {
        return ($words[($n=($n=$n%100)>19?($n%10):$n)==1?0 : (($n>1&&$n<=4)?1:2)]);
    }

}