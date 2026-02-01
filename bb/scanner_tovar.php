<?php

namespace bb;
use bb\classes\Collateral;
use bb\classes\KarnStirka;
use bb\classes\SpeedTrack;
use bb\classes\tovar;

session_start();

ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Client.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/KBron.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/KBronForm.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/bron.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/TariffModel.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Tariff.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/KarnStirka.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Deal.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Payment.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Collateral.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SpeedTrack.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Category.php'); // включаем класс

//SpeedTrack::start();

Base::loginCheck();

echo Base::PageStartAdvansed('QR: работа с товаром', 0);
//Base::PostCheckVarDumpEcho();

echo Base::getBarCodeReaderScript('', array('target'=>'/bb/scanner_tovar.php'));
?>

<style>
    .collateral_act{
        position: absolute;
        background: white;
        right: -13px;
        top: -9px;
        border-style: solid;
        border-color: red;
        padding: 0px 5px;
        border-width: 1px;
        color: red;

        border-radius:5px;
        -webkit-border-radius:5px;
        -moz-border-radius:5px;
        -khtml-border-radius:5px;
    }

    .collateral_dop {
        position: absolute;
        right: 0px;
        bottom: -27px;
        font-size: 40px;
        font-weight: bold;
        color: red;
    }
</style>
<script>

    history.pushState(null, null, location.href);
    window.onpopstate = function(event) {
        history.go(1);
    };

    function del_check(id) {
        rez=prompt("Причина удаления брони?");

        if (rez==null) {
            return false;
        }
        else {
            document.getElementById('arch_info_'+id).value=rez;
            return true;
        }

    }
    function KarnStirka() {
        var inv = $('#item_inv_n').val();
        //alert(inv);
        var info = prompt('Введите комментарий:');

        if (info) {
            var f = document.createElement('form');
            f.setAttribute('method', 'post');
            f.setAttribute('action', '/bb/scanner_tovar.php');

            var i = document.createElement('input');
            i.setAttribute('type', 'text');
            i.setAttribute('name', 'item_inv_n');
            i.setAttribute('value', inv);
            f.appendChild(i);

            var i = document.createElement('input');
            i.setAttribute('type', 'text');
            i.setAttribute('name', 'info');
            i.setAttribute('value', info);
            f.appendChild(i);

            var i = document.createElement('input');
            i.setAttribute('type', 'text');
            i.setAttribute('name', 'action');
            i.setAttribute('value', 'стирка');
            f.appendChild(i);

            document.getElementsByTagName('body')[0].appendChild(f);

            f.submit();
        }
    }

    function pay_show(id) {
        $('#pay_form_'+id).show();
        $('#pay_but_'+id).hide();
    }
    function pay_hide(id) {
        $('#pay_form_'+id).hide();
        $('#pay_but_'+id).show();
    }
    function date_avail(id) {
        if ($('#rent_payment_type_'+id).val()=='bank') {
            $('#payment_date_'+id).attr('readonly', false);
        }
        else {
            dd = new Date();
            d=dd.getFullYear()+'-';
            if (dd.getMonth()<10) {
                d+='0'+dd.getMonth()+'-';
            }
            else {
                d+=dd.getMonth()+'-';
            }

            if (dd.getDate()<10) {
                d+='0'+dd.getDate();
            }
            else {
                d+=dd.getDate();
            }



            $('#payment_date_'+id).val(d);
            $('#payment_date_'+id).attr('readonly', true);
        }
    }

    function pay_check(id) {
        rez = true;
        mesaga='';
        now = new Date();
        today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
        dd = new Date(document.getElementById('payment_date_'+id).value);
        dd.setHours(0);
        dd2=dd.getTime();
        if (dd2>today) {
            rez=false;
            mesaga+='Дата платежа не может быть в будущем!';
        }
        if (document.getElementById('r_paid_'+id).value==0 || document.getElementById('r_paid_'+id).value=='') {
            rez=false;
            mesaga+=' Заполните сумму платежа!';
        }

        if (!rez) {
            alert(mesaga);
        }

        return rez;
    }

    function vozvr_check() {
        rez = true;
        mesaga='';
        if (($('#vozvr_r_paid').val()=='0' || $('#vozvr_r_paid').val()=='') && $('#vozvr_rent_payment_type').val()!='0') {
            rez=false;
            mesaga+=' Заполните сумму платежа!';
        }

        if (($('#vozvr_r_paid').val()!='0' && $('#vozvr_r_paid').val()!='') && $('#vozvr_rent_payment_type').val()=='0') {
            rez=false;
            mesaga+=' Выберите кассу!';
        }

        if (!rez) {
            alert(mesaga);
        }

        return rez;
    }

    function vidacha(id) {
        $('#br_form_'+id).show("slow");
        $('#btn_br_vid_'+id).hide();
    }

    function vidacha_cans(id) {
        $('#br_form_'+id).hide();
        $('#btn_br_vid_'+id).show("slow");
    }

    function vidacha_type(id) {
        //alert(1);
        if ($('#vid_type_'+id).val()=='money') {
            //alert(2);
            $('#col_amount_'+id).show();
            $('#col_cur_'+id).show();
        }
        else {
            //alert(3);
            $('#col_amount_'+id).hide();
            $('#col_cur_'+id).hide();
        }
    }

    function vid_check(id) {
        if ($('#col_amount_'+id).val()=='') {
            alert('Заполните поле залог либо суммой, либо цифрой' +
                ' 0');
            return false;
        }

        if((parseInt($('#col_amount_'+id).val())==0 || $('#col_amount_'+id).val()=='') && $('#info_'+id).val()=='') {
            alert('Внесите комментарий!');
            return false;
        }
        return true;
    }


</script>

<?php

if (isset($_POST['item_inv_n'])) $_POST['item_inv_n'] = preg_replace("/[^0-9]/", "", $_POST['item_inv_n']);

$item_inv_n=0;
$tov=null;
//$item_inv_n=75741;

//$item_inv_n=7523; //Bron
//$item_inv_n=719214;//free
$shift_to='';

if (isset($_POST['action'])) {
    $action= Base::GetPost('action');

    switch ($action) {
        case 'стирка':
            $stir=new KarnStirka($item_inv_n);
            $stir->info=Base::killKavichki(Base::GetPost('info'));
            $stir->save();
            break;
        case 'сохранить оплату':
            Base::GetAllPostGlobal();

            $mysqli=Db::getInstance()->getConnection();

            $done="yes";

            $query_start = "START TRANSACTION";
            $result_start = $mysqli->query($query_start);
            if (!$result_start) {die('Сбой при доступе к базе данных: '.$query_start.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                $done="no";
            }

            //выбираем основную сделку для апдейта
            $query_dl_first = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
            $result_dl_first = $mysqli->query($query_dl_first);
            if (!$result_dl_first) {die('Сбой при доступе к базе данных: '.$query_dl_first.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                $done="no";
            }
            $dl_fitst_num=$result_dl_first->num_rows;

            if ($dl_fitst_num>0) {
                $dl_first=$result_dl_first->fetch_assoc();
                $dl_base='act';
            }
            else {//если не нашли сделку в действующих, смотрим в архив
                $query_dl_first = "SELECT * FROM rent_deals_arch WHERE deal_id='$deal_id'";
                $result_dl_first = $mysqli->query($query_dl_first);
                if (!$result_dl_first) {die('Сбой при доступе к базе данных: '.$query_dl_first.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                    $done="no";
                }
                $dl_first=$result_dl_first->fetch_assoc();
                $dl_base='arch';
            }


            $payment_date=strtotime($payment_date); //приводим в формат юникс дату календаря гггг-мм-дд
            $r_paid=Base::tonumDotComma($r_paid);//меняем точку на запятую + убираем пробелы и лишние символы


            //выбираем "первую сдачу" (либо "аванс"), к которой привязываем платеж
            if ($dl_base=='act') {

                $query_sub_dl_first = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND `type` IN ('first_rent', 'takeaway_plan') ORDER by cr_time DESC";
                $result_sub_dl_first = $mysqli->query($query_sub_dl_first);
                if (!$result_sub_dl_first) {die('Сбой при доступе к базе данных: '.$query_sub_dl_first.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                    $done="no";
                }
                $sub_dl_first=$result_sub_dl_first->fetch_assoc();
            }
            else {
                $query_sub_dl_first = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='$deal_id' AND `type` IN ('first_rent', 'takeaway_plan') ORDER by cr_time DESC";
                $result_sub_dl_first = $mysqli->query($query_sub_dl_first);
                if (!$result_sub_dl_first) {die('Сбой при доступе к базе данных: '.$query_sub_dl_first.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                    $done="no";
                }
                $sub_dl_first=$result_sub_dl_first->fetch_assoc();
            }

            // вносим суб-сделку (история + подробности)
            if ($dl_base=='act') {

                $sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$r_paid', '', '$rent_payment_type', '', 'pure_payment', '', '".time()."', '".$_SESSION['user_id']."', '', '', '".$sub_dl_first['sub_deal_id']."', '$payment_date', '".$_SESSION['office']."', '', '', '', '')";
                $result_sub_query = $mysqli->query($sub_query);
                if (!$result_sub_query) {die('Сбой при доступе к базе данных: '.$sub_query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                    $done="no";
                }
            }
            else {
                //делаем вставку суб. сделки чтобы получить ID
                $sub_query0 = "INSERT INTO rent_sub_deals_act VALUES('', '$deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$r_paid', '', '$rent_payment_type', '', 'pure_payment', '', '".time()."', '".$_SESSION['user_id']."', '', '', '".$sub_dl_first['sub_deal_id']."', '$payment_date', '".$_SESSION['office']."', '', '', '', '')";
                $result_sub_query0 = $mysqli->query($sub_query0);
                if (!$result_sub_query0) {
                    die('Сбой при доступе к базе данных: '.$sub_query0.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                    $done="no";
                }
                $kb_payment_last_id=$mysqli->insert_id;

                $sub_query3 = "DELETE FROM rent_sub_deals_act WHERE sub_deal_id='$kb_payment_last_id'";
                $result_sub_query3 = $mysqli->query($sub_query3);
                if (!$result_sub_query3) {
                    die('Сбой при доступе к базе данных: '.$sub_query3.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                    $done="no";
                }

                $sub_query = "INSERT INTO rent_sub_deals_arch VALUES('', '".time()."', '$kb_payment_last_id', '$deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$r_paid', '', '$rent_payment_type', '', 'pure_payment', '', '".time()."', '".$_SESSION['user_id']."', '', '', '".$sub_dl_first['sub_deal_id']."', '$payment_date', '".$_SESSION['office']."', '', '', '', '')";
                $result_sub_query = $mysqli->query($sub_query);
                if (!$result_sub_query) {
                    die('Сбой при доступе к базе данных: '.$sub_query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                    $done="no";
                }
            }

            // корректируем сделку
            //$r_paid=$dl_first['r_paid']+$r_paid; сделал в запросе

            if ($dl_base=='act') {

                $query_dl_upd = "UPDATE rent_deals_act SET r_paid=r_paid+'$r_paid', last_sub_deal_ch_time='".time()."' WHERE deal_id='$deal_id'";
                $result_dl_upd = $mysqli->query($query_dl_upd);
                if (!$result_dl_upd) {die('Сбой при доступе к базе данных: '.$query_dl_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                    $done="no";
                }
            }
            else {
                $query_dl_upd = "UPDATE rent_deals_arch SET r_paid=r_paid+'$r_paid', last_sub_deal_ch_time='".time()."' WHERE deal_id='$deal_id'";
                $result_dl_upd = $mysqli->query($query_dl_upd);
                if (!$result_dl_upd) {die('Сбой при доступе к базе данных: '.$query_dl_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                    $done="no";
                }
            }


            //завершаем операцию
            if ($done=='yes') {
                $query_fin = "COMMIT";
                $result_fin = $mysqli->query($query_fin);
                if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
            }
            else {
                $query_fin = "ROLLBACK";
                $result_fin = $mysqli->query($query_fin);
                if (!$result_fin) {die('Сбой при доступе к базе данных: '.$query_fin.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
            }

            break;
        case 'Выдать':
            $mysqli=Db::getInstance()->getConnection();

            Db::startTransaction();

            $col=new Collateral();
            $col->deal_id=Base::GetPost('deal_id');
            $col->br_id=Base::GetPost('br_id');
                $amount=Base::GetPost('amount');
                $amount=Base::tonumDotComma($amount);
            $col->amount=$amount;
            $col->info=Base::GetPost('info');
            $col->cr_who_id=\bb\models\User::getCurrentUser()->id_user;
            $col->cr_time=new \DateTime();
            $col->place = \bb\models\Office::getCurrentOffice()->number;
                $today=new \DateTime();
                    $today->setTime(0,0,0);
            $col->acc_date = $today;

            if (KBron::vidachaStatic($col->br_id) && $col->save()) {
                echo 'Выдача костюма отражена';
                Db::commitTransaction();
            }
            else {
                Db::rollBackTransaction();
                echo 'Ошибка. Выдача не прошла. Свяжитесь с Димой.';
            }
            break;

        case 'Возврат':
            Base::GetAllPostGlobal();
            $mysqli=Db::getInstance()->getConnection();
            Db::startTransaction();

            $done="yes";

            if (!Collateral::saveVozvrat(Base::GetPost('br_id'))){
                $done='no';
            }

            if (!KBron::vozvratStatik(Base::GetPost('br_id'))){
                $done='no';
            }


            if (Base::GetPost('r_paid')!=0 && Base::GetPost('r_paid')!='') {

                //Base::GetAllPostGlobal();

                //выбираем основную сделку для апдейта
                $query_dl_first = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
                $result_dl_first = $mysqli->query($query_dl_first);
                if (!$result_dl_first) {die('Сбой при доступе к базе данных: '.$query_dl_first.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                    $done="no";
                }
                $dl_fitst_num=$result_dl_first->num_rows;

                if ($dl_fitst_num>0) {
                    $dl_first=$result_dl_first->fetch_assoc();
                    $dl_base='act';
                }
                else {//если не нашли сделку в действующих, смотрим в архив
                    $query_dl_first = "SELECT * FROM rent_deals_arch WHERE deal_id='$deal_id'";
                    $result_dl_first = $mysqli->query($query_dl_first);
                    if (!$result_dl_first) {die('Сбой при доступе к базе данных: '.$query_dl_first.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                        $done="no";
                    }
                    $dl_first=$result_dl_first->fetch_assoc();
                    $dl_base='arch';
                }
                //var_dump($dl_base);


                $payment_date=strtotime($payment_date); //приводим в формат юникс дату календаря гггг-мм-дд
                $r_paid=Base::tonumDotComma($r_paid);//меняем точку на запятую + убираем пробелы и лишние символы


                //выбираем "первую сдачу" (либо "аванс"), к которой привязываем платеж
                if ($dl_base=='act') {

                    $query_sub_dl_first = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND `type` IN ('first_rent', 'takeaway_plan') ORDER by cr_time DESC";
                    $result_sub_dl_first = $mysqli->query($query_sub_dl_first);
                    if (!$result_sub_dl_first) {die('Сбой при доступе к базе данных: '.$query_sub_dl_first.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                        $done="no";
                    }
                    $sub_dl_first=$result_sub_dl_first->fetch_assoc();
                }
                else {
                    $query_sub_dl_first = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='$deal_id' AND `type` IN ('first_rent', 'takeaway_plan') ORDER by cr_time DESC";
                    $result_sub_dl_first = $mysqli->query($query_sub_dl_first);
                    if (!$result_sub_dl_first) {die('Сбой при доступе к базе данных: '.$query_sub_dl_first.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                        $done="no";
                    }
                    $sub_dl_first=$result_sub_dl_first->fetch_assoc();
                    //var_dump($sub_dl_first);
                }

                // вносим суб-сделку (история + подробности)
                if ($dl_base=='act') {

                    $sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$r_paid', '', '$rent_payment_type', '', 'br_vozvrat', '', '".time()."', '".$_SESSION['user_id']."', '', '', '".$sub_dl_first['sub_deal_id']."', '$payment_date', '".$_SESSION['office']."', '', '', '', '')";
                    $result_sub_query = $mysqli->query($sub_query);
                    if (!$result_sub_query) {die('Сбой при доступе к базе данных: '.$sub_query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                        $done="no";
                    }
                }
                else {
                    //делаем вставку суб. сделки чтобы получить ID
                    $sub_query0 = "INSERT INTO rent_sub_deals_act VALUES('', '$deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$r_paid', '', '$rent_payment_type', '', 'br_vozvrat', '', '".time()."', '".$_SESSION['user_id']."', '', '', '".$sub_dl_first['sub_deal_id']."', '$payment_date', '".$_SESSION['office']."', '', '', '', '')";
                    $result_sub_query0 = $mysqli->query($sub_query0);
                    if (!$result_sub_query0) {
                        die('Сбой при доступе к базе данных: '.$sub_query0.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                        $done="no";
                    }
                    $kb_payment_last_id=$mysqli->insert_id;

                    $sub_query3 = "DELETE FROM rent_sub_deals_act WHERE sub_deal_id='$kb_payment_last_id'";
                    $result_sub_query3 = $mysqli->query($sub_query3);
                    if (!$result_sub_query3) {
                        die('Сбой при доступе к базе данных: '.$sub_query3.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                        $done="no";
                    }

                    $sub_query = "INSERT INTO rent_sub_deals_arch VALUES('', '".time()."', '$kb_payment_last_id', '$deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$r_paid', '', '$rent_payment_type', '', 'br_vozvrat', '', '".time()."', '".$_SESSION['user_id']."', '', '', '".$sub_dl_first['sub_deal_id']."', '$payment_date', '".$_SESSION['office']."', '', '', '', '')";
                    $result_sub_query = $mysqli->query($sub_query);
                    if (!$result_sub_query) {
                        die('Сбой при доступе к базе данных: '.$sub_query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                        $done="no";
                    }
                }

                // корректируем сделку
                //$r_paid=$dl_first['r_paid']+$r_paid; увеличение прямо в запросе

                if ($dl_base=='act') {

                    $query_dl_upd = "UPDATE rent_deals_act SET r_paid=r_paid+'$r_paid', last_sub_deal_ch_time='".time()."' WHERE deal_id='$deal_id'";
                    $result_dl_upd = $mysqli->query($query_dl_upd);
                    if (!$result_dl_upd) {die('Сбой при доступе к базе данных: '.$query_dl_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                        $done="no";
                    }
                }
                else {
                    $query_dl_upd = "UPDATE rent_deals_arch SET r_paid=r_paid+'$r_paid', last_sub_deal_ch_time='".time()."' WHERE deal_id='$deal_id'";
                    $result_dl_upd = $mysqli->query($query_dl_upd);
                    if (!$result_dl_upd) {die('Сбой при доступе к базе данных: '.$query_dl_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
                        $done="no";
                    }
                }
            }



            if ($done=='yes') {
                Db::commitTransaction();
            }
            else {
                Db::rollBackTransaction();
            }



            break;

        case 'отменить выдачу':
            //echo 'in process';
            if (KBron::vidachaCancelByBrId(Base::GetPost('br_id'))) {
                echo 'Выдача отменена';
            }
            break;
        case 'отменить возврат'://$br_id
            $br_id=Base::GetPost('br_id');

            $sub_dl_id=KBron::getVozvratPaymentIdStatic($br_id);
            \bb\classes\Deal::deletePaymentStatic($sub_dl_id);
            KBron::vozvratCancelByBrId($br_id);

            break;
        case 'Удалить бронь':
            //echo 'in process';
            Db::startTransaction();
            $done=true;
            $kb=KBron::getById(Base::GetPost('br_id'));
            if (!$kb->arch(Base::GetPost('arch_info'))) {
                $done=false;
            }

            if (!KBron::delete(Base::GetPost('br_id'))) {
                $done=false;
            }

            if ($done) {
                Db::commitTransaction();
                echo 'Бронь удалена.';
            }
            else {
                Db::rollBackTransaction();
            }
            break;

        case 'Костюм подготовлен':
            if (!KBron::podgotovitStatic(Base::GetPost('br_id'))) {
                $done=false;
            }
            echo 'Подготовка костюма отражена.';
            break;
    }

}


//Base::PostCheckVarDumpEcho();

if (isset($_POST['item_inv_n'])) {
    $item_inv_n= Base::GetPost('item_inv_n');
}

if ($item_inv_n>0) {
    $tov=new \bb\classes\tovar();
    $tov->item_load($item_inv_n);
    if ($tov->item_id<1) {
        $tov=null;
    }
}

if (isset($_POST['shift_to'])) {
    $shift_to=Base::GetPost('shift_to');

    if ($shift_to=='move_canсel') {
        $tov->moveCancel();
    }
    elseif ($shift_to=='move_accept') {
        $tov->moveAccept();
    }
    elseif ($shift_to>0) {
        $tov->moveTo($shift_to, \bb\models\Office::getCurrentOffice()->number);
    }
}

echo '
<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        ...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </div>
</div>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
 <!--<a class="navbar-brand" href="/bb/index.php">Главная</a>
 <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
 <span class="navbar-toggler-icon"></span>
 </button>-->

 <div class="collapse navbar-collapse" id="navbarSupportedContent">
     <ul class="navbar-nav mr-auto">
         <li class="nav-item active">
             <a class="nav-link" href="/bb/index.php">Главная <span class="sr-only">(current)</span></a>
         </li>
         <li class="nav-item active">
             <a class="nav-link" href="/bb/kr_baza_new.php">Все товары<span class="sr-only">(current)</span></a>
         </li>
         <li class="nav-item active">
             <a class="nav-link" href="/bb/rda.php">Все сделки<span class="sr-only">(current)</span></a>
         </li>
         <li class="nav-item active">
             <a class="nav-link" href="/bb/kb.php">Карнавальные брони<span class="sr-only">(current)</span></a>
         </li>
         <!--<li class="nav-item">
            <a class="nav-link" href="#">Link</a>
         </li>
         <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            Dropdown
            </a>
             <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                <a class="dropdown-item" href="#">Action</a>
                <a class="dropdown-item" href="#">Another action</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#">Something else here</a>
            </div>
         </li>-->
         <!--<li class="nav-item">
            <a class="nav-link disabled" href="#">Disabled Link</a>
         </li>-->
     </ul>
 </div>
<!--/.nav-collapse -->
</nav>
<!--/.navbar -->

<div class="jumbotron text-center" style="padding: 0px;margin: 0">
  <h1 style="margin: 0; padding: 0">'.($item_inv_n>0 ? 'Страница товара' : 'Отсканируйте товар.').'
    <form method="post" class="form-inline" action="scanner_tovar.php" style="display: inline-block">
        <input type="text" class="form-control form-control-sm" name="item_inv_n" id="item_inv_n" value="'.$item_inv_n.'">
        <input type="submit" value="submit" style="display: none;">
    </form>
  </h1>
</div>
<div class="container-fluid">';

if ($tov) {
    //грузим карнавальные брони вперед товара, чтоб правильно отразить выдан он, или свободен
    $kb_rez='';

    if ($tov->isKarnaval()) {

        $kb_rez.= '
            <div class="row">
                <table class="table table-bordered">
                    <thead class="thead-light">
                        <th scope="col">№брони</th>
                        <th scope="col" style="min-width: 80px;">С</th>
                        <th scope="col" style="min-width: 80px;">По</th>
                        <th scope="col">Договор</th>
                        <th scope="col" style="min-width: 180px;">Статус</th>
                        <th scope="col">ФИО/телефон</th>
                        <th scope="col">Доп.информация</th>
                        <th scope="col">Действия</th>
                    </thead>
                    <tbody>';

        $from = new \DateTime();
        $from->setTime(0,0,0);
        $to = new \DateTime();
        $to->modify("+1 year");
//SpeedTrack::meashure();//1
        if ($active_br=KBron::getActiveBronByInv($tov->item_inv_n)) {
            //var_dump($active_br);
            KBron::$active_k_bron=$active_br;
            $kb_rez.= KBronForm::getQRLine($active_br, $tov);
        }
        else {
            $active_br=null;
        }
//SpeedTrack::meashure();//2
        if ($brs = KBron::getBrons($tov->item_inv_n, $from, $to)) {
            //SpeedTrack::meashure();//3

            foreach ($brs as $br) {//для того, чтобы не задублировать активную бронь.
                if ($active_br && $active_br->id_kb == $br->id_kb) {
                    continue;
                }
//            if ($br->isRentedOutKarn()) {
//                KBron::$active_k_bron=$br;
//            }
                $kb_rez .= KBronForm::getQRLine($br, $tov);
            }
            $kb_rez .= '
                    </tbody>
                </table>
            </div>

            ';
        }
    }//закончили грузить карнавальные брони

//SpeedTrack::meashure();//4

    echo '
<div class="row">
    <div class="col-sm-2 align-content-center">
        <div class="card h-100 align-content-center">
            <div class="card-body h-100 w-100 align-content-center justify-content-center">
                <img src="' . $tov->getPicAddress() . '" style="width: 100%;">
                ' . $tov->isDirtyText() . '
                ' . $tov->isDirtyPic("position:absolute; top:0; right:0;") . '
            </div>
        </div>
    </div>
    <div class="col-sm-6 align-content-center">
        <div class="card h-100">
            <div class="card-body h-100 w-100">';
    $position_right_px = 35;
    if ($tov->isInMove()) {
        echo '
                        <div style="position: absolute; top: 65px; right: 35px;">' . \bb\models\Office::getOfficeNameByNumber($tov->to_move, 3) . '</div>
                        <img style="float: right; position: relative;" src="' . \bb\models\Office::getOfficePicAddress($tov->to_move) . '" title="' . \bb\models\Office::getOfficeNameByNumber($tov->to_move) . '">
                        <img style="float: right; position: relative;" src="/bb/arrow.jpg">
                        ';
        $position_right_px = 134;
    }

    echo '

            <div style="position: absolute; top: 65px; right: ' . $position_right_px . 'px;">' . \bb\models\Office::getOfficeNameByNumber($tov->item_place, 3) . '</div>
            <img style="float: right; position: relative;" src="' . \bb\models\Office::getOfficePicAddress($tov->item_place) . '" title="' . \bb\models\Office::getOfficeNameByNumber($tov->item_place) . '">
                <h3>' . $tov->invNPrint('-') . '</h3>
                <h4>' . $tov->getFullName() . $tov->getSizeRost().'</h4><br>
                <h5>Комплектность: ' . $tov->getSet() . '</h5>
            </div>';
    if ($tov->isKarnaval()) {
        echo '<div style="text-align: right; color: #005d9e; font-size: 17px;">Стандартный залог: '.number_format($tov->collateral, 0, ',', ' ').' руб.</div>';
    }
            echo '
        </div>
    </div>
    <div class="col-sm-2 align-content-center">
        <div class="card h-100">
            <div class="card-body h-100 w-100">
                <h5 style="text-align: center;">Тариф:<br>' . $tov->getTariffModel()->allTariffsText() . '</h5>
                ';
                    $br_a=KBron::getRentedOutBron();
                if ($br_a && $col_a=Collateral::getCollateralByDl($br_a->dl_link)) {
                    //echo '<h6 style="text-align: center;">ЗАЛОГ:<br> '.$col_a->getCollateralText().'</h6>';
                }

                if ($br_a) {
                    echo '
                        <div class="alert-primary text-center" style="padding: 10px;">
                            Доплата (+), возврат (-):<br>
                            <input type="number" form="vozvr_form_'.$br_a->id_kb.'" class="form-control form-control-sm" style="width: 100px; display: inline-block;" step="0.01" name="r_paid" id="vozvr_r_paid" size="10" value="" /><br>
                            <select form="vozvr_form_'.$br_a->id_kb.'" name="rent_payment_type" id="vozvr_rent_payment_type" class="form-control form-control-sm" style="width: 110px; display: inline-block" onchange="date_avail(\''.$br_a->id_kb.'\')">
                                    <option value="0">касса</option>
                                    <option value="nal_no_cheque">нал без чека</option>
                                    <option value="nal_cheque">нал с чеком</option>
                                    <option value="card">карточка</option>
                                    <!--<option value="bank">банк</option>-->
                            </select>
                        </div>

                    ';
                }
    echo '
            </div>
        </div>
    </div>
    <div class="col-sm-2 align-content-center">
        <div class="card h-100">
            <div class="card-body h-100 w-100">';
                if ($tov->isKarnaval() && $br_a=KBron::getRentedOutBron()) {

                    echo '<h3 class="text-center" style="color: red">Выдан</h3>';

                        echo '
                            <h6>
                                '.$br_a->fio.'

                            </h6>
                    ';
//                    if () {
//                        echo '<h6>ЗАЛОГ: '.$col_a->getCollateralText().'</h6>';
//                    }
                    $col_a=Collateral::getCollateralByDl($br_a->dl_link);
                    if(!$col_a) {
                        $col_a = new Collateral();
                        $col_a->info='Старая выдача (информация о залоге не сохранена в базе)';
                    }
                    $now= new \DateTime();
                        //$now->setDate(2022,1,1);
                    if ($br_a->to_kb>$now) {
                        $bg_act_date_col='#1c7430';
                    }
                    else {
                        $bg_act_date_col='red';
                    }

                    echo '<h6 style="color: '.$bg_act_date_col.'">'.$br_a->to_kb->format("H:i").'<sup>'.$br_a->to_kb->format("d ").Base::$months_text[$br_a->to_kb->format("m")].'</sup></h6>';
                    echo '
                    <form action="scanner_tovar.php" method="post" name="vozvr_form_'.$br_a->id_kb.'" id="vozvr_form_'.$br_a->id_kb.'" class="alert-primary text-center" style="padding: 10px; position:relative;">
                            <input type="hidden" name="item_inv_n" value="'.$br_a->inv_n.'">
                            <input type="hidden" name="deal_id" value="'.$br_a->dl_link.'" />
                            <input type="hidden" name="br_id" value="'.$br_a->id_kb.'" />
                            <input type="hidden" name="payment_date" value="'.date("Y-m-d").'" />


                            <input type="submit" class="btn btn-lg btn-success" name="action" value="Возврат" onclick="return vozvr_check();" />
                            <div class="collateral_act">'.number_format($col_a->amount, '2', ',', ' ').'</div>';
                    if ($col_a->info!='') {
                        echo '<div class="collateral_dop">!</div>';
                    }
                    echo '
                     </form>
                    ';
                }
                else {
                    echo '
                        <h3 class="text-center" style="color: ' . $tov->getStatusTextColor() . '">' . $tov->getStatusText() . '</h3>
                    ';
                }


    if ($tov->status == 'bron') {
        $br = \bron::getFirstBronForInv($tov->item_inv_n);
        if ($br->type2!='bron' && $br->type2!='deliv') {
            echo 'Статус:'.$br->bronTypeText().'<br>';
        }
        echo 'от ' . date("d.m.Y", $br->order_date) . date(" (H:i)", $br->cr_time) . ', по ' . date("d.m.Y", $br->validity);
        echo '<br>' . $br->info;
        echo '
                        <form method="post" action="/bb/rent_orders.php" class="form-inline">
                            <input type="hidden" name="item_inv_n" value="' . $tov->item_inv_n . '">
                            <input class="btn btn-lg btn-outline-info btn-block" type="submit" value="Брони">
                        </form>
                    ';

    }
    echo '
            </div>
        </div>
    </div>

</div> <!-- end of row -->
<div class="row">
    <div class="col-sm-12 text-center">

        <form method="post" action="/bb/dogovor_new.php" class="form-inline" style="display: inline-block;">
            <input type="hidden" name="item_inv_n" value="' . $tov->item_inv_n . '">
            <input class="btn btn-lg btn-primary" type="submit" value="К договору">
        </form>';
    if (!$tov->isRentedOut() && $tov->isAtCurrentOffice()) {
        echo '
        <form method="post" action="/bb/rent_orders.php" class="form-inline" style="display: inline-block;">
            <input type="hidden" name="item_inv_n" value="' . $tov->item_inv_n . '">
            <input type="hidden" name="type2" value="remont">
            <input type="hidden" name="br_2_t" value="remont">
            <input type="hidden" name="action" value="бронь">
            <input class="btn btn-lg btn-secondary" type="submit" value="В ремонт">
        </form>
        ';
        echo '
        <form method="post" action="/bb/rent_orders.php" class="form-inline" style="display: inline-block;">
            <input type="hidden" name="item_inv_n" value="' . $tov->item_inv_n . '">
            <input type="hidden" name="type2" value="out">
            <input type="hidden" name="br_2_t" value="out">
            <input type="hidden" name="action" value="бронь">
            <input class="btn btn-lg btn-secondary" type="submit" value="На выбытие">
        </form>
        ';
//        echo '
//        <form method="post" action="/bb/scanner_tovar.php" class="form-inline" style="display: inline-block;">
//            <input class="btn btn-lg btn-danger" type="button" value="Ремонт\Стирка" onclick="KarnStirka();">
//        </form>
//        ';
    }


    if (!$tov->isRentedOut() && ($tov->isAtCurrentOffice() || $tov->to_move == \bb\models\Office::getCurrentOffice()->number)) {
        $office = \bb\models\Office::getCurrentOffice();
        $ofs = \bb\models\Office::getAllOffices();

        echo '
        <form method="post" action="/bb/scanner_tovar.php" class="form-inline" style="display: inline-block; margin: 10px;" onchange="if (confirm(\'Вы уверены, что хотите переместить товар?\')) {this.submit();} else {this.selected=0}">
            <input type="hidden" name="item_inv_n" value="'.$item_inv_n.'">
            <select name="shift_to" class="form-control form-control-lg custom-select-lg alert-info" style="color: #17a2b8;">';

        if ($tov->item_place == $office->number) {
            if ($tov->isVPuti()) {
                echo '<option>в пути: ' . \bb\models\Office::getOfficeNameByNumber($tov->item_place) . '-->' . \bb\models\Office::getOfficeNameByNumber($tov->to_move) . '</option>';
                echo '<option value="move_canсel">отменить статус --в пути--</option>';
            } else {//not v puti = at the office
                echo '<option>Переместить на офис</option>';
                foreach ($ofs as $of) {
                    if ($of->number == $office->number) continue;
                    echo '<option value="' . $of->number . '">' . $of->name_short . '</option>';
                }
            }

        } elseif ($tov->to_move == $office->number) {
            echo '<option>В пути: ' . \bb\models\Office::getOfficeNameByNumber($tov->item_place) . '-->' . \bb\models\Office::getOfficeNameByNumber($tov->to_move) . '</option>';
            echo '<option value="move_accept">принять товар на ' . \bb\models\Office::getOfficeNameByNumber($office->number) . '</option>';
        }
        echo '
            </select>
        </form>';
    }
    echo '
    </div><!-- end of col -->
</div><!-- end of row -->

';

    echo $kb_rez;

}//end of tov if
else {
    echo '
    <div class="row">
        <div class="col-12 text-center">
            <h2 class="text-center" style="color: red">Товар с инвентарным номером '.$item_inv_n.' не найден.</h2>
        </div>
    </div>
    ';
}
//SpeedTrack::finish();

echo SpeedTrack::getResult();
Base::PageEndHTML();

?>
