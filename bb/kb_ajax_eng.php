<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 02.12.2018
 * Time: 16:13
 */

namespace bb;

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/KBronForm.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Schedule.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/KBron.php');

$action='';
$inv_n='';
$event_date='';
$from_kb='';
$to_kb ='';
$active_inv_n='';
$tmp_br_id='';
$start_date='';
$kb_start_date='';
$kb_start_time='';
$kb_end_date='';
$kb_end_time='';
$fio='';
$phone1='';
$phone2='';
$email='';
$info='';
$br_tmp_id='';
$active_tmp_bron_id='';
$ch_time='';

//Base::PostCheck();
Base::GetAllPostGlobal();


//foreach ($_POST as $key => $value) {
//    $$key=Base::GetPost($key);
//}

switch ($action) {
    case 'br_cancel'://active_inv_n

        KBron::delete($active_tmp_bron_id);

        $res['status']='ok';
        $res['result']=KBronForm::StartForm($active_inv_n);
        $res['param']='';

        $result=json_encode($res);
        echo $result;

        break;

    case 'first_bron'://incoming: action, inv_n
        $res['status']='ok';
        $res['result']=KBronForm::DateForm($inv_n);
        $res['param']='';

        $result=json_encode($res);
        echo $result;

        break;


    case 'date_choosen'://inv_n, (string)event_date
        $event_datetime= new \DateTime($event_date);
            $event_datetime->setTime(0,0);
        $kf=new KBronForm();
            if ($kf->createFromEventDate($event_datetime, $inv_n,5,0))  {
                $result['result'] = $kf->getLineForm();
            }
            else {
                $result['result'] =
                    '<div class="br_wh_cont">
                        <div class="br_cont" style="text-align: center; font-size: 20px;">
                            К сожалению, свободные периоды на '.$event_datetime->format("d.m.Y").' отсутствуют.<br> 
                            <input type="button" value="OK" onclick="kb_cancel();">
                        </div>
                     </div>';
            }
        $result['status']='ok';

        $result=json_encode($result);
        echo $result;

        break;

    case 'free_period_first': //in data: from_kb, to_kb, inv_n, tmp_br_id
        if ($tmp_br_id>0) {
            KBron::delete($tmp_br_id);
        }

        $from_time=new \DateTime($from_kb);
            $from_time->modify("+1 second");//!!! delete after synhronising with site
        $to_time=new \DateTime($to_kb);
            $to_time->modify("-1 second");//!!! delete after synhronising with site
        if ($kb=KBron::setTmpBron($inv_n, $from_time, $to_time)) {
            $res['status']='ok';
            $res['result']=KBronForm::getLineBronForm($kb);

            $res['params']['kb_id']=$kb->id_kb;
        }
        else {
            $res['status']='not_ok';
            $res['result']=
                '<div class="br_wh_cont">
                    <div class="br_cont" style="text-align: center; font-size: 20px;">
                       К сожалению, выбранный период уже занят. Оформите бронь заново.<br> 
                    <input type="button" value="OK" onclick="kb_cancel();">
                    </div>
                 </div>';
        }

        $result = json_encode($res);
        echo $result;

        break;

    case 'get_hours_options'://start_date

        $res['status']='ok';
        if ($start_date!=0) {
            $date = new \DateTime($start_date);
            $res['result'] = KBronForm::getWorkingHoursOptions($date);
        }
        else {
            $res['result']='<option value="0">время</option>';
        }

        $result = json_encode($res);
        echo $result;

        break;

    case 'bron_create'://
        $kb = new KBron();
        $kb->id_kb=$br_tmp_id;

        $kb->from_kb = new \DateTime($kb_start_date);
            $kb->from_kb->setTime($kb_start_time, 0, 1);//set start from 1t second - else bron conflict
        $kb->to_kb= new \DateTime($kb_end_date);
            $kb->to_kb->setTime($kb_end_time, 0);
                $kb->to_kb->modify("-1 second");//!!! delete after synhronising with site
        $kb->ch_time = new \DateTime();
            $kb->ch_time->setTimestamp($ch_time);
        $kb->fio = $fio;
        $kb->inv_n = $inv_n;

        $kb->phone1=preg_replace("/[^0-9]/", '', $phone1);
        $kb->phone2=preg_replace("/[^0-9]/", '', $phone2);
        $kb->email=$email;
        $kb->info=$info;
        $kb->staus_kb='new';


        $mysqli = Db::getInstance()->getConnection();

        $query = "LOCK TABLES karn_brons WRITE";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

        $a=array($kb->id_kb);
        if (!KBron::getBrons($kb->inv_n, $kb->from_kb, $kb->to_kb, $a)) {//if no crossing-brons exists - save new bron

            if ($kb->isChanged()) {
                $res['status'] = 'not_ok'; //if ch_time is not equal - means
                $res['result'] = '<div>Изменения не внесены. Скорее всего бронь была изменена на другой вкладке. Попробуйте заново </div>';
            }
            else {
                $kb->calculateBronNumber();
                $kb->update();
                $res['status']= 'ok';
                $res['result'] = '
                    <div class="br_wh_cont">
                        <div class="br_cont" style="text-align: center; font-size: 20px; background-color: green;">
                            Бронь успешно сохранена<br> 
                            <input type="button" value="OK" onclick="kb_cancel();">
                        </div>
                     </div>
                ';
            }
        }


        $query = "UNLOCK TABLES";
        $result = $mysqli->query($query);
        if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}


        $result = json_encode($res);
        echo $result;

        break;

    default:
        return 'Неизвестная команда (непрописанный action). Свяжитесь с разработчиком.';
        break;
}


if (isset($_POST['action']) && $_POST['action']=='inv_br_start') {

    $kf=new KBronForm('2018-12-02', '20', $inv_n);
    echo $kf->getForm();


}
elseif (isset($_POST['action']) && $_POST['action']=='inv_br_form') {
    $result='';

    echo $result;
}




