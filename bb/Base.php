<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 21.10.2018
 * Time: 19:40
 */

namespace bb;


use phpDocumentor\Reflection\Types\True_;

class Base
{
  public static function getBynExchangeRateOnDateByNumCode(\DateTime $date, $code){
    $URL = "https://api.nbrb.by/exrates/rates/$code?ondate=".$date->format('Y-m-d')."&periodicity=0&parammode=1";
    $rez_text = file_get_contents($URL);
    $rez = json_decode($rez_text, true);
    return $rez['Cur_OfficialRate'];
  }

  public static function getExchRateToUsd(\DateTime $date, $letterCode){
    if ($letterCode=='USD') { //840
      return 1;
    }
    elseif ($letterCode=='EUR'){ //643
      $eur = self::getBynExchangeRateOnDateByNumCode($date, 978);
      $usd = self::getBynExchangeRateOnDateByNumCode($date, 840);

      return round($eur/$usd, 5) ;
    }
    elseif ($letterCode=='RUB'){ //978
      $rub = self::getBynExchangeRateOnDateByNumCode($date, 643)/100;
      $usd = self::getBynExchangeRateOnDateByNumCode($date, 840);

      return round($rub/$usd,5);
    }
    elseif ($letterCode=='BYN') { //933
      return self::getBynExchangeRateOnDateByNumCode($date, 840);
    }
    else{
      return 0;
    }

  }
    /**
     * @param $msg
     * @return void
     */
    public static function addErrorMessage($msg){
        if(!isset($_SESSION['myerrors'])) $_SESSION['myerrors'] = [];
        else $_SESSION['myerrors'][]=$msg;
    }

  /**
   * @param $msg
   * @return void
   */
  public static function addClientMessage($msg){
    //return $msg;
    if(!isset($_SESSION['client_message'])) {
      $_SESSION['client_message'] = [];
    }

    $_SESSION['client_message'][]=$msg;
  }



  public static function logObjectToFile($object, $filename) {
    // Convert the object to JSON format
    $jsonObject = json_encode($object);

    $today = new \DateTime();

    $filename='logs/'.$today->format('Y-m-d').'_'.$filename;

    // Open the file for appending
    $file = fopen($filename, 'a');

    // Write the JSON object to the file
    fwrite($file, $jsonObject . PHP_EOL);

    // Close the file
    fclose($file);
  }

  public static function getDayNameLong($dayNum){
    switch ($dayNum){
      case 1:
        return 'Понедельник';
        break;
      case 2:
        return 'Вторник';
        break;
      case 3:
        return 'Среда';
        break;
      case 4:
        return 'Четверг';
        break;
      case 5:
        return 'Пятница';
        break;
      case 6:
        return 'Суббота';
        break;
      case 7:
        return 'Воскресенье';
        break;
      default:
        return $dayNum;
        break;
    }
  }

  public static function getDayNameShort($dayNum){
    switch ($dayNum){
      case 1:
        return 'Пн';
        break;
      case 2:
        return 'Вт';
        break;
      case 3:
        return 'Ср';
        break;
      case 4:
        return 'Чт';
        break;
      case 5:
        return 'Пт';
        break;
      case 6:
        return 'Сб';
        break;
      case 7:
        return 'Вс';
        break;
      default:
        return $dayNum;
        break;
    }
  }


  /**
   * @return bool
   */
  public static function hasClientMessages(){
    if(isset($_SESSION['client_message']) && is_array($_SESSION['client_message']) && count($_SESSION['client_message'])>0) return true;
    else return false;
  }

  /**
   * @return array|false
   */
  public static function getClientMessages(){
    if(isset($_SESSION['client_message']) && is_array($_SESSION['client_message']) && count($_SESSION['client_message'])>0) {
      return $_SESSION['client_message'];
      unset($_SESSION['client_message']);
    }
    else {
      return false;
    }
  }

    /**
     * @return bool
     */
    public static function isMobileDevise(){
        $useragent=$_SERVER['HTTP_USER_AGENT'];

        if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4))) {
            return true;
        }
        else return false;

    }

    /**
     * @param $str
     * @return string
     */
    public static function strUpperFirstLetter($str) {
        $first_letter = mb_substr($str,0,1, 'UTF-8');
        $first_letter = mb_strtoupper($first_letter);
        return $first_letter.mb_substr($str, 1,null, 'UTF-8');
    }


    public static function getShorMonthText($mm){
        return self::$short_months_text[$mm];
    }

    public static function getShortWeekDay($php_num){
        return self::$short_weekdays_text[$php_num];
    }


    /**
     * @return false|string
     */
    public static function getErrorsString(){
        if(isset($_SESSION['myerrors']) && count($_SESSION['myerrors']) > 0) {
            $rez = '';
            foreach ($_SESSION['myerrors'] as $er) {
                $rez.= $er.'<br>';
            }
            $_SESSION['myerrors'] = [];

            return $rez;
        }
        else return false;
    }






    public static function l3CreatePage($addr, $model_id) {
$str = '<?php
session_start();
$model_id=\''.$model_id.'\';
require_once ($_SERVER[\'DOCUMENT_ROOT\'].\'/'.$addr.'\'); // включаем форму страницы
?>';

echo '---'.$_SERVER['DOCUMENT_ROOT'].$addr;
file_put_contents($_SERVER['DOCUMENT_ROOT'].$addr, $str);

if (file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$addr)) return true;
else return false;

    }
    //массив для месяцев
    public static $months_text=array(
        "01" => "января",
        "02" => "февраля",
        "03" => "марта",
        "04" => "апреля",
        "05" => "мая",
        "06" => "июня",
        "07" => "июля",
        "08" => "августа",
        "09" => "сентября",
        "10" => "октября",
        "11" => "ноября",
        "12" => "декабря");

    public static $short_months_text=array(
        "01" => "янв",
        "02" => "фев",
        "03" => "мар",
        "04" => "апр",
        "05" => "мая",
        "06" => "июн",
        "07" => "июл",
        "08" => "авг",
        "09" => "сен",
        "10" => "окт",
        "11" => "ноя",
        "12" => "дек");

    public static $short_weekdays_text=array(
        "вс",
        "пн",
        "вт",
        "ср",
        "чт",
        "пт",
        "сб"
    );



    public static function getShortMonth($m_num_2d){
        if (isset(self::$short_months_text[$m_num_2d])) {
            return self::$short_months_text[$m_num_2d];
        }
        else {
            return null;
        }
    }

    public static function prepareForDogovor($str){
        //$str = iconv('utf-8','windows-1251',$str);
        //$str = preg_replace("/([a-zA-Z0-9]{2})/","\'$1",$str);
        $str = htmlspecialchars($str);
        return $str;
    }

    /**
     * @return string
     */
    public static function historyClearScript(){
        return '
        <script language="javascript">
            history.pushState(null, null, location.href);
                window.onpopstate = function(event) {
                    history.go(1);
                };
        </script>
        ';
    }

    public static function getBarCodeReaderScript($variant='', array $params=null) {
        $target='/bb/scanner_tovar.php';
        if ($params!=null){
            if (isset($params['target'])) {
                $target=$params['target'];
            }
        }

        $rez= "
            <script>
            document.addEventListener('DOMContentLoaded', () => {
                'use strict';
                var input='';
                var cmd='';
                var pause_time = 50;
                var last_stroke=0;

                document.addEventListener('keydown', event => {
                    if ((Date.now()-last_stroke)>pause_time) {
                        input='';
                        cmd='';
                    }
                    last_stroke=Date.now();

                    var key_pressed=event.key.toLowerCase();
                    if (event.keyCode >= 48 && event.keyCode <= 57) {
                        input += key_pressed;
                    }
                    if (event.keyCode >= 65 && event.keyCode <= 90) {
                        cmd+= key_pressed;
                    }
                    if (event.keyCode==13 && input.length>3) {
                        event.preventDefault();
                        //console.log('enter catch');
                        //document.getElementById('item_inv_n').value=input;
                        //document.getElementById('inv_n_select_but').click();

                        switch (cmd) {
                            case 'brnum':
                            case 'иктгь':

                                         var f = document.createElement('form');
                                        f.setAttribute('method', 'post');
                                        f.setAttribute('action', '/bb/kb.php');

                                        var i =document.createElement('input');
                                        i.setAttribute('type', 'text');
                                        i.setAttribute('name', 'br_num_s');
                                        i.setAttribute('value', input);

                                        f.appendChild(i);

                                        document.getElementsByTagName('body')[0].appendChild(f);

                                        f.submit();

                                break;
                            case '':";

                                if ($variant=='new_dogovor') {
                                    $rez.="
                                                document.getElementById('item_inv_n').value=input;
                                                document.getElementById('inv_n_select_but').click();
                                    ";

                                }
                                else {

                                    $rez .= "

                                                var f = document.createElement('form');
                                                f.setAttribute('method', 'post');
                                                f.setAttribute('action', '$target');

                                                var i =document.createElement('input');
                                                i.setAttribute('type', 'text');
                                                i.setAttribute('name', 'item_inv_n');
                                                i.setAttribute('value', input);

                                                f.appendChild(i);

                                                document.getElementsByTagName('body')[0].appendChild(f);

                                                f.submit();";
                                }



                    $rez.="
                                break;
                        }



                        ";


        $rez.="
                    }

                    //console.log('l='+input.length);
                    //console.log(input);
                    //console.log(cmd);

                });
            });
            </script>
        ";


        return $rez;
    }

    public static function tonumDotComma ($value) {

        $output=floatval(str_replace(',','.',$value));
        return $output;

    }

    public static function phone_print ($ph, $code=null) {
        if ($ph=='') {return '';}

        $dl=strlen($ph);

        if ($dl<7) {return $ph;}

        $dl>7 ? $dl_to=$dl-7 : $dl_to=0;
        $ph_out='('.substr($ph, 0, $dl_to).') '.substr($ph, -7, 3).' '.substr($ph, -4, 2).' '.substr($ph, -2, 2);
        return $ph_out;

    }

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

    public static function PageStartAdvansed($header='No header', $container_start=1) {
        $rez= '
<!DOCTYPE HTML>
<html lang="ru-RU">
<head>
    <title> '.$header.'</title>
    <meta charset="utf-8">
    <meta name="viewport" content="initial-scale=1.0, width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/css/bootstrap-select.min.css">

    <!-- Latest Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">


    <!-- JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>



    <!-- Latest compiled and minified JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/js/bootstrap-select.min.js"></script>

    <script language="javascript">
        history.pushState(null, null, location.href);
        window.onpopstate = function(event) {
            history.go(1);
        };
    </script>

</head>
<body>
        ';
if ($container_start==1) {
    $rez.='<div class="container-fluid">';
}
elseif ($container_start==2){
    $rez.='<div class="container">';
}
//    <!-- (Optional) Latest compiled and minified JavaScript translation files -->
//    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/js/i18n/defaults-*.min.js"></script>
return $rez;

    }


    public static function pageStartB5($header='No header') {
        $rez= '
<!DOCTYPE HTML>
<html lang="ru-RU">
<head>
    <title> '.$header.'</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <!-- Latest Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <script>
        history.pushState(null, null, location.href);
        window.onpopstate = function(event) {
            history.go(1);
        };
    </script>

</head>
<body>
        ';
        return $rez;

    }


  /**
   * @param $var
   * @return void
   */
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
        </div><!-- end of main container-->
        </body>
        </html>
        ';
    }

    /**
     * @return string
     */
    public static function pageEndHtmlB5(){
        return'
            <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js" integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
        </body>
        </html>
        ';
    }

  /**
   * @param $mNum
   * @return mixed
   */
  public static function getMonthNameForDay($mNum){
      try {
        $month = self::$months_text[$mNum];
        return $month;
      }
      catch (\Exception $e){
        return 'месяц не найден';
      }
    }

    public static function monthName($m) {

        switch ((int)$m) {
            case 1:
                $rez='январь';
                break;
            case 2:
                $rez='февраль';
                break;
            case 3:
                $rez='март';
                break;
            case 4:
                $rez='апрель';
                break;
            case 5:
                $rez='май';
                break;
            case 6:
                $rez='июнь';
                break;
            case 7:
                $rez='июль';
                break;
            case 8:
                $rez='август';
                break;
            case 9:
                $rez='сентябрь';
                break;
            case 10:
                $rez='октябрь';
                break;
            case 11:
                $rez='ноябрь';
                break;
            case 12:
                $rez='декабрь';
                break;
            default:
                $rez='нет такого месяца';
                break;
        }
        return $rez;
    }

    public static function bootstrapRequiredEcho () {
        $rez= '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">';
        $rez.= '
            <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
            <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
        ';
        //<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
        //
        return $rez;
    }


    /**
     *
     */
    public static function PostCheck(){
        //Проверка входящей информации
        $rez= "<div style='clear: both;'></div>Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
        foreach ($_POST as $key => $value) {
            $rez.= "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
        }
        $rez.= "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";
        return $rez;
    }

    public static function dateKrFormat($int) {
        return date("d", $int).' '.self::getShortMonth(date('m',$int)).' '.date('y',$int);
    }

    public static function PostCheckVarDumpEcho(){
        //Проверка входящей информации
        echo '<pre>';
        var_dump($_POST);
        var_dump($_FILES);
        echo '</pre>';
    }

    public static function compaignRegister(){
        if (isset($_SESSION) && isset($_GET['aid'])) {
            $_SESSION['aid']=self::getGet('aid');
        }
    }

    public static function getAdvCompId(){
        if (isset($_SESSION['aid'])) {
            return $_SESSION['aid'];
        }
        else return 0;
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
    /**
     * @param $var
     * @return string
     */
    public static function getGet($var) {
        $db = Db::getInstance();
        $mysqli = $db->getConnection();
        return $mysqli->real_escape_string($_GET[$var]);
    }

    public static function GetAllPostGlobal() {
        foreach ($_POST as $key => $value) {
            global $$key;
            $$key=self::GetPost($key);
        }
    }

    public static function killKavichki($str){
        $rez = str_replace('"', '', $str);
        $rez = str_replace("'", "", $rez);
        return $rez;
    }

    public static function ubratSpecSimvoly($str){
        return preg_replace("#[^0-9а-яА-ЯA-Za-z;:_.,?!° -%№]+#u", '', $str);
    }

    /**
     * @param \DateTime $time
     * @return string
     */
    public static function dayOption(\DateTime $time){
        return '<option value="'.$time->format("Y-m-d").'">'.$time->format("d").' '.Base::$months_text[$time->format("m")].' '.$time->format("Y").'</option>';
    }

    /**
     * @param \DateTime $time
     * @return string
     */
    public static function hourOption(\DateTime $time) {

        return '<option value="'.$time->format("H").'">'.$time->format("H").'</option>';

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

    public static function sel_d($par1, $par2) {
      //if ($par1==4) echo '-----'.$par1.'=='.$par2.'('.($par1==$par2).')';
        //echo $par1.'=='.$par2.'('.($par1==$par2).')<br>';
        if ($par1===$par2) return 'selected';
    }


    /**
     * @return bool
     */
    public static function logOut(){
        unset($_COOKIE[session_name()]);
        unset($_COOKIE[session_id()]);
        session_unset();
        session_destroy();

        return true;
    }

    public static function officeLoggedInfo(){
        $rez= '
        <div class="user"><form name="выход" method="post" action="index.php" style="margin: 0;">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /><br/></form>
        ';


            $offs=\bb\models\Office::getAllActiveOffices();
            $rez.='
            <form action="\bb\index.php" method="post">
                <input type="hidden" name="action" value="office_change">
                Офис:';
            if (\bb\models\User::getCurrentUser()->isOwner()) {
                $rez .= ' <select name="office_change_to" onchange="this.form.submit();">';
                foreach ($offs as $of) {
                    $rez .= '<option value="' . $of->number . '" ' . Base::sel_d($of->number, \bb\models\Office::getCurrentOffice()->number) . '>' . $of->getFullName() . '</option>';
                }
                $rez .= '
                </select>';
            }
            else {
                $rez.=' '.\bb\models\Office::getCurrentOffice()->getFullName();
            }
                $rez.='
            </form>
            ';

        $rez.='</div>';
        return $rez;
    }

    public static function officeLoggedInfo2(){
        $rez='';

        $offs=\bb\models\Office::getAllActiveOffices();
        $rez.='
            <form class="form-inline my-2 my-lg-0" action="\bb\index.php" method="post" style="margin: 0 10px;">
                <input type="hidden" name="action" value="office_change">
                ';
        if (\bb\models\User::getCurrentUser()->isOwner()) {
            $rez .= ' <select class="form-control" style="max-width: 180px;" name="office_change_to" onchange="this.form.submit();">';
            foreach ($offs as $of) {
                $rez .= '<option value="' . $of->number . '" ' . Base::sel_d($of->number, \bb\models\Office::getCurrentOffice()->number) . '>' . $of->getFullName() . '</option>';
            }
            $rez .= '
                </select>';
        }
        else {
            $tmp_of=\bb\models\Office::getCurrentOffice();
            $rez .= ' <select class="form-control" style="max-width: 180px;" name="office_change_to" onchange="this.form.submit();">';

            $rez .= '<option value="' . $tmp_of->number . '" ' . Base::sel_d($tmp_of->number, \bb\models\Office::getCurrentOffice()->number) . '>' . $tmp_of->getFullName() . '</option>';

            $rez .= '
                </select>';

            //$rez.=' <span>'.\bb\models\Office::getCurrentOffice()->getFullName().'</span>';
        }
        $rez.='
            </form>
            ';

        return $rez;
    }

    /**
     * @return string
     */
    public static function getLoggedInAndExit(){
        return '
        <form class="form-inline my-2 my-lg-0" method="post" action="index.php" style="margin: 0;"><strong>'.\bb\models\User::getCurrentUser()->getShortName().'</strong> <input type="submit" class="btn btn-danger" name="action" value="Выйти" /></form>
        ';
    }

    public static function getLoginForm(){
        return '<form action="/bb/index.php" method="post">
                    <div class="form-group">
                        <label for="log">Имя пользователя: </label>
                            <input type="text" name="log" id="log" class="form-control col-12 col-sm-6 col-md-4 col-lg-3 col-lg-2">
                    </div>
                    <div class="form-group">
                        <label for="pass">Пароль: </label>
                            <input type="password" name="pass" id="pass" class="form-control col-12 col-sm-6 col-md-4 col-lg-3 col-lg-2">
                    </div>
                    <div class="form-group">
                        <input type="submit" name="action" value="Войти" class="form-control btn btn-info form-control col-12 col-sm-6 col-md-4 col-lg-3 col-lg-2">
                    </div>
                </form>';
    }

    /**
     * @param $short_path
     * @param $filename
     * @return string
     */
    public static function getUniqueFileName($short_path, $filename){
        $i=0;
        //$strLength = mb_strlen($filename);
        $dot_pos = mb_strrpos($filename, '.');
        $name= mb_substr($filename, 0, $dot_pos);
        $extention = mb_substr($filename, $dot_pos);
        while (file_exists($_SERVER['DOCUMENT_ROOT'].$short_path.$filename)) {
            $i++;
            $filename = $name.'_'.$i.$extention;
        }
        return $filename;
    }

  /**
   * @param $targetDir
   * @param $tmpFileName
   * @param $newFileName
   * @return false|string
   */
  public static function saveFile($targetDir, $tmpFileName, $newFileName){
      if ($targetDir=='' || $tmpFileName == '' || $newFileName == '') return false;
      if(substr($targetDir,-1) != '/') $targetDir = $targetDir.'/';

      //create folder
      $fullDir = $_SERVER['DOCUMENT_ROOT'].$targetDir;
      if(!is_dir($fullDir)){//Directory does not exist, so lets create it.
        mkdir($fullDir, 0755, true);
      }

      $newFileName = self::getUniqueFileName($targetDir, $newFileName);

      //echo 'file set part launched';
      move_uploaded_file($tmpFileName, $fullDir.$newFileName);
      return $targetDir.$newFileName;
    }

    public static function delFile($path){
      if ($path == '') return false;
      if (is_file($_SERVER['DOCUMENT_ROOT'].$path)) {
        unlink($_SERVER['DOCUMENT_ROOT'].$path);
        return true;
      }
      else{
        return false;
      }
    }

    public static function loginCheck($in_level= array(0,5,7)){
        //------- proverka paroley
        isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
        if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level))) {
            die('
                <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml">
                <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <title>Авторизация</title>
                </head>
                <body>

                <div class="top_menu">
                    <a class="div_item" href="/bb/index.php">Залогиниться</a>
                </div>

                </body></html>');
        }

    }

    public static function isAllLoggedIn(){

        if (isset($_SESSION['svoi']) && $_SESSION['svoi']==8941) {
            return true;
        }
        else {
            return false;
        }
    }

    public static function PngToGpg($png){
        $image = imagecreatefrompng($png);
        imagejpeg($image);
        return $image;
    }

  /**
   * @param $str
   * @return string
   */
  public static function getNumbersOnly($str) {
        $matches='';
        preg_match_all('!\d+!', $str, $matches);
        $var = implode('', $matches[0]);
        return $var;
    }


    /**
     * @param $post_name
     * @param $new_name_prefix
     * @param $uploadFileDir
     * @return array (result, error, url)
     */
    public static function imgUpload($post_name, $new_name_prefix, $uploadFileDir){
        $message = '';
        $rez=array();
        if (isset($_FILES[$post_name]) && $_FILES[$post_name]['error'] === UPLOAD_ERR_OK)
        {
            $ok=false;
            // get details of the uploaded file
            $fileTmpPath = $_FILES[$post_name]['tmp_name'];
            $fileName = $_FILES[$post_name]['name'];
            $fileSize = $_FILES[$post_name]['size'];
            $fileType = $_FILES[$post_name]['type'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            $fileBody=$fileNameCmps[0];

            // sanitize file-name
                //$newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $newFileName = $new_name_prefix.'_'.$fileBody.'.'. $fileExtension;

            // check if file has one of the following extensions
                //$allowedfileExtensions = array('jpg', 'gif', 'png', 'zip', 'txt', 'xls', 'doc');
            $allowedfileExtensions = array('jpg', 'jpeg', 'gif', 'png');

            if (in_array($fileExtension, $allowedfileExtensions))
            {
                // directory in which the uploaded file will be moved
                    //$uploadFileDir = './uploaded_files/';
                $dest_path = $uploadFileDir . $newFileName;
                //echo 'Route:'.$dest_path;
                if(move_uploaded_file($fileTmpPath, $dest_path))
                {
                    $message ='Файл '.$fileName.' успешно загружен.<br>';
                    $ok=true;
                }
                else
                {
                    $message = 'Ошибка загрузки файла '.$fileName.' в основную директорию. Обратитесь к администратору (возможно некорректно установлены права на папку).<br>';
                }
            }
            else
            {
                $message = 'Загрузка файла '.$fileName.' прервана. Разрешенные типы (разрешения) файлов: ' . implode(',', $allowedfileExtensions).'<br>';
            }
        }
        else
        {
            $message = 'Ошибка загрузки файла. Проверьте следующие ошибки.<br>';
            $message .= 'Ошибка:' . $_FILES['uploadedFile']['error'].'<br>';
        }

        $rez[1]=$message;
        $rez[2]=$newFileName;

        if ($ok){
            $rez[0]=true;
        }
        else{
            $rez[0]=false;
        }
        return $rez;
    }

    /**
     * @param $str
     * @return string
     */
    public static function translit($str){
        $str = trim(mb_strtolower($str));

        $t=[
            'а'=>'a',
            'б'=>'b',
            'в'=>'v',
            'г'=>'g',
            'д'=>'d',
            'е'=>'e',
            'ё'=>'e',
            'ж'=>'zh',
            'з'=>'z',
            'и'=>'i',

            'й'=>'j',
            'к'=>'k',
            'л'=>'l',
            'м'=>'m',
            'н'=>'n',
            'о'=>'o',
            'п'=>'p',
            'р'=>'r',
            'с'=>'s',
            'т'=>'t',

            'у'=>'u',
            'ф'=>'f',
            'х'=>'h',
            'ц'=>'c',
            'ч'=>'ch',
            'ш'=>'sh',
            'щ'=>'sch',
            'ъ'=>'',
            'ы'=>'y',
            'ь'=>'',

            'э'=>'e',
            'ю'=>'u',
            'я'=>'ya',

            ' '=>'-',
            '-'=>'-',
            '_'=>'_',
        ];

        $rez = '';

        $strAr = mb_str_split($str);
        foreach ($strAr as $s) {
            if (key_exists($s, $t)) {
                $rez.=$t[$s];
            }
        }

        return $rez;
    }


  /**
   * @param $filePath
   * @return array|string|string[]
   */
  public static function removeQuotesFromFile($filePath){

    if ((strpos($filePath, "'")!= false || strpos($filePath, '"')!= false)){

      $newFilePath=str_replace("'", "",$filePath);
      $newFilePath=str_replace('"', "",$newFilePath);

      if (file_exists($_SERVER['DOCUMENT_ROOT'].$filePath)) {
        rename($_SERVER['DOCUMENT_ROOT'].$filePath,$_SERVER['DOCUMENT_ROOT'].$newFilePath);
      }

      return $newFilePath;
    }
    else {
      return $filePath;
    }
  }

  /**
   * @param $email
   * @return string
   */
  public static function cleanEmail($email){
  //Применим очищающий фильтр FILTER_SANITIZE_EMAIL, который удаляет все символы, кроме букв, цифр и !#$%&'*+-=?^_`{|}~@.[]
    $email =  filter_var ( $email, FILTER_SANITIZE_EMAIL);

  //3.  Удалим повторяющиеся точки
    $email  = preg_replace('/\.{2,}/', ".", $email );

  //4.  Разделим емайл на имя и домен по последнему символу собаки @
    preg_match('/(.*)@(.*)$/', $email, $arResult);

    $arResult[1]; //имя

    $arResult[2]; //домен

  //5. Вычистим имя

    $arResult[1] = preg_replace('/^\./', "", $arResult[1]); //удаляем первый символ точки

    $arResult[1] = preg_replace('/\.$/', "", $arResult[1]); //удаляем последний символ точки

    $arResult[1] = preg_replace('/@/', "", $arResult[1]); //удаляем собачки


  //6.  Вычистим домен

    $arResult[2] = preg_replace('/^\./', "", $arResult[2]); //удаляем первый символ точки

    $arResult[2] = preg_replace('/\.$/', "", $arResult[2]); //удаляем последний символ точки

    $arResult[2] = preg_replace('/[^a-z0-9_\.-]/iu', "", $arResult[2]); //удаляем все запрещенные символы в домене кроме, латинских букв, точек, знака тире и нижнего подчеркивания

  //7. Удалим, т.к. там лежит еще не обработанный email из 3 пункта

    unset($arResult[0]);

  //8. Выведем результат

  $strResult = implode("@", $arResult);

  return $strResult;
  }

}
