<?php


namespace bb\classes;


use bb\models\User;

class SpeedTrack
{
    public static $start;
    public static $finish;
    public static $rezs= array();
    private static $_counter=0;
    private static $_max_num=1;


    public static function start($max_num=1){
        self::$_counter++;
        if (self::$_counter>self::$_max_num) {
            return false;
        }
        if (!self::$start) {
            self::$start=microtime(true);
        }
    }

    public static function finish(){
        //echo self::$_counter;
        if (self::$_counter>self::$_max_num) {
            return false;
            //echo '111';
        }

        if (!self::$finish) {
            self::$finish=microtime(true);
            //echo '222';
        }
    }


    public static function meashure(){
        if (self::$_counter>self::$_max_num) {
            return false;
        }

        self::$rezs[]=microtime(true);
    }

    public static function getResult(){

        $rez = 'Время выполнения скрипта:' . round((self::$finish - self::$start), 4) . ' sec.<br>';
        $prev=self::$start;
        $count=1;
        foreach (self::$rezs as $r) {
            $rez.='отм '.$count.'+'.round(($r-$prev), 4).' sec.<br>';
            $prev=$r;
            $count++;
        }
        $rez.='фин '.$count.'+'.round((self::$finish-$prev), 4).' sec.<br>';
        return $rez;
//
//        if (1==1/*User::getCurrentUser()->id_user==3*/) {
//
//        }
//        else return '';
    }

}