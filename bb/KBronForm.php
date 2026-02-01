<?php
/**
 * Created by PhpStorm.
 * User: AcerPC
 * Date: 02.12.2018
 * Time: 18:55
 */

namespace bb;


use bb\classes\Collateral;
use bb\classes\KBChange;
use bb\classes\SpeedTrack;

class KBronForm
{
    public static $scc='<link href="/bb/KBronForm.css" rel="stylesheet" type="text/css" />';
    public static $js='<script src="/bb/KarnavalBron.js"></script>';
    public static $jquery='<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>';

    public static $_podg_btns_counter=0;

    public $inv_n;
    public $start_time;
    public $end_time;
    public $event_time;

    public $days_form_quantity;

    public static $h_day_px=40;//px
    public static $w_day_px=144;//px   = 6 в час

    public static $px_per_hour = 6;

    /**
     * @var KBron[]
     */
    public $free_periods = array();

    public static function RequiredEcho(){
        echo self::$scc;
        echo self::$jquery;
        echo self::$js;
        echo '
            <form id="active_values" action="kb_ajax_eng.php" method="post">
                <input type="hidden" id="active_inv_n" name="active_inv_n" value="">
                <input type="hidden" name="action" value="br_cancel">
                <input type="hidden" id="active_a_id" name="" value="active_a_id">
                <input type="hidden" id="active_tmp_bron_id" name="active_tmp_bron_id" value="">
            </form>
            ';
    }

    public static function getQRLine(KBron $br, \bb\classes\tovar $tov){

        $kb_rez='';

        $kb_rez.='
        <tr style="background-color: '.$br->getColorTableRow().'">
        <th scope="row">'.$br->br_num.(\bb\models\User::getCurrentUser()->id_user==3 ? '<br>('.$br->id_kb.')' : '').'</th>
        <td><span>'.$br->from_kb->format("d ").Base::$short_months_text[$br->from_kb->format("m")].'</span><br><span>'.$br->from_kb->format("H:i").'</span>';

        if ($br->payment_k1>0) {
            $kb_rez.= '<br><span style="font-size: 25px;color: green;">+</span><span style="color: green; font-style: italic;"> '.\bb\models\User::GetUserName($br->payment_k2).'</span>';
        }

        $kb_rez.='
        </td>
        <td><span>'.$br->to_kb->format("d ").Base::$short_months_text[$br->to_kb->format("m")].'</span><br><span>'.$br->to_kb->format("H:i").'</span></td>
        <td>'.($br->dl_link>0 ? '№'.$br->dl_link.'<br>'.number_format(\bb\classes\Deal::getAmountPaid($br->dl_link),2,',', ' ') : '').' руб.';
//            if ($col=$br->getCollateral()) {
//                $kb_rez.= '<br>залог:'.$col->getCollateralText();
//            }

            //форма оплаты
        if ($br->dl_link>0) {
            $kb_rez.='
                <br><input type="button" class="btn btn-sm btn-primary" style="padding:0 5px;" value="оплата" id="pay_but_'.$br->id_kb.'" onclick="pay_show(\''.$br->id_kb.'\')" />
                            <form action="scanner_tovar.php" method="post" id="pay_form_'.$br->id_kb.'" class="alert-primary text-center" style="padding: 10px; display: none;">
                                <input type="hidden" name="item_inv_n" value="'.$br->inv_n.'">
                                <input type="hidden" name="deal_id" value="'.$br->dl_link.'" />
                                Дата оплаты: <br><input type="date" readonly class="form-control form-control-sm" style="width: 150px; display: inline-block;" name="payment_date" id="payment_date_'.$br->id_kb.'" value="'.date("Y-m-d").'" />,<br>
                                <input type="number" class="form-control form-control-sm" style="width: 100px; display: inline-block;" step="0.01" name="r_paid" id="r_paid_'.$br->id_kb.'" size="10" value="" /> руб.,<br>
                                <select name="rent_payment_type" id="rent_payment_type_'.$br->id_kb.'" class="form-control form-control-sm" style="width: 110px; display: inline-block" onchange="date_avail(\''.$br->id_kb.'\')">
                                        <option value="nal_no_cheque">нал без чека</option>
                                        <option value="nal_cheque">нал с чеком</option>
                                        <option value="card">карточка</option>
                                        <option value="bank">банк</option>
                                </select><br />
                                <input type="submit" class="btn btn-sm btn-info" name="action" value="сохранить оплату" onclick="return pay_check(\''.$br->id_kb.'\')" />
                                <input type="button" class="btn btn-sm btn-primary" value="отмена" onclick="pay_hide(\''.$br->id_kb.'\')" />
                            </form>';
        }
            $kb_rez.= '               
                            </td>
                        <td style="font-size: 12px;">';
            //выводим статусы
            if ($br->appr_time->getTimestamp()>0) {
                $kb_rez.= 'подтв:'.$br->appr_time->format("d-m (H:i").'-'.\bb\models\User::GetUserName($br->appr_who).')';
            }
            if ($br->vidacha!=null && $br->vidacha->getTimestamp()>0) {
                $kb_rez.= '<br>выдан:'.$br->vidacha->format("d-m (H:i").'-'.\bb\models\User::GetUserName($br->vid_who_id).')';
            }
            if ($br->vozvrat!=null && $br->vozvrat->getTimestamp()>0) {
                $kb_rez.= '<br>возвр.:'.$br->vozvrat->format("d-m (H:i").'-'.\bb\models\User::GetUserName($br->vid_who_id).')';
            }

            //выводим историю платежей
            if ($br->dl_link>0) {
                $kb_rez.= '<span style="color: #0000CC">';
                $ps = \bb\classes\Payment::getPaymentsForDeal($br->dl_link);
                if ($ps==null) {
                    $kb_rez.= '<br>нет оплат';
                }
                else {
                    foreach ($ps as $p) {
                        $kb_rez.= '<br>'.number_format($p->r_paid, 2, ',',' ').'р. '.'<span style="font-style: italic">'.$p->cr_time->format("d.m (H:i)").'-'.\bb\models\User::GetUserName($p->cr_who).'</span>';
                    }
                }
                $kb_rez.= '</span>';
            }
            $kb_rez.= '
                        </td>
                        <td>'.$br->fio.'<br>'.Base::phone_print($br->phone1).'<br>'.($br->phone2>0 ? Base::phone_print($br->phone2) : '').'</td>
                        <td>'.$br->info.'
                        ';
            if ($br->btnsToVidacha()) {
                $kb_rez.='<textarea name="info" id="info_'.$br->id_kb.'" class="form-control form-control-sm" form="br_form_'.$br->id_kb.'"></textarea>';
            }
            if ($br->isVidanOnly() && $col_a=Collateral::getCollateralByDl($br->dl_link)) {
                $kb_rez.='<span style="color: red"><br>Информация по выдаче:<br>'.$col_a->info.'</span>';
            }

            $kb_rez.='
                        </td>
                        <td>';

            if ($br->vidacha->getTimestamp() <1 && self::$_podg_btns_counter<1 && $br->payment_k1<1) {
                self::$_podg_btns_counter++;
                $kb_rez.='
                        <form method="post" action="scanner_tovar.php" name="br_form_'.$br->id_kb.'">
                                    <input type="hidden" name="item_inv_n" value="'.$tov->item_inv_n.'">
                                    <input type="hidden" name="br_id" value="'.$br->id_kb.'">
                                    <input type="submit" name="action" class="btn btn-warning btn-lg" value="Костюм подготовлен">
                        </form>            
                                    
                                    ';

            }

                        if ($br->btnsToVidacha()) {
                            $kb_rez.='
                              
                                <form method="post" action="scanner_tovar.php" name="br_form_'.$br->id_kb.'" id="br_form_'.$br->id_kb.'">
            Залог:
                                    <input type="hidden" name="item_inv_n" value="'.$tov->item_inv_n.'">
                                    <input type="hidden" name="deal_id" value="'.$br->dl_link.'">
                                    <input type="hidden" name="br_id" value="'.$br->id_kb.'">
                                    <!--
                                    <select name="col_type" class="form-control form-control-sm" id="vid_type_'.$br->id_kb.'" onchange="vidacha_type(\''.$br->id_kb.'\')">
                                        <option value="money">деньги</option>
                                        <option value="other">другое</option>
                                    </select>
                                    -->
                                    <input name="amount" id="col_amount_'.$br->id_kb.'" type="number" class="form-control form-control-sm" value="" step="0.01" min="0" style="display: inline-block; width: 80px;" id="col_amount_'.$br->id_kb.'" >
                                    <!--
                                    <select name="currency" class="form-control form-control-sm custom-select-sm" style="display: inline-block; width: 70px;" id="col_cur_'.$br->id_kb.'">
                                        <option>BYN</option>
                                        <option>USD</option>
                                        <option>EUR</option>
                                        <option>RUB</option>
                                    </select>
                                    -->
                                    
                                    <input type="submit" name="action" class="btn btn-info" value="Выдать" onclick="return vid_check(\''.$br->id_kb.'\');">
                                    
                                </form>
        ';
                        }
                        if ($br->vidacha->getTimestamp()<1 || ($br->vozvrat->getTimestamp()>0 && (time() < $br->vozvrat->getTimestamp()))) {//no vidacha
                            $kb_rez.= '
                                                            <form method="post" action="scanner_tovar.php">
                                                                    <input type="hidden" name="item_inv_n" value="'.$tov->item_inv_n.'">
                                                                    <input type="hidden" name="arch_info" id="arch_info_'.$br->id_kb.'" value="">
                                                                    <input type="hidden" name="br_id" value="'.$br->id_kb.'">
                                                                    <input type="submit" class="btn btn-sm btn-danger" name="action" value="Удалить бронь" onclick="return del_check(\''.$br->id_kb.'\');">
                                                            </form>';
                        }

                        if ((time()-$br->vidacha->getTimestamp())<(24*10*60*60) && $br->vozvrat->getTimestamp()<1) {//less then 1 hour
                            $kb_rez.= '
                                            <form method="post" action="scanner_tovar.php">
                                                    <input type="hidden" name="item_inv_n" value="'.$tov->item_inv_n.'">
                                                    <input type="hidden" name="deal_id" value="'.$br->dl_link.'">
                                                    <input type="hidden" name="br_id" value="'.$br->id_kb.'">
                                                    <input type="submit" class="btn btn-sm btn-danger" name="action" value="отменить выдачу" onclick="return confirm(\'Вы действительно хотите отменить выдачу товара?\');">
                                            </form>';
                        }
                        if ($br->vidacha->getTimestamp()>0 && $br->vozvrat->getTimestamp()>0) {//less then 1 hour
                            $kb_rez.= '
                                            <form method="post" action="scanner_tovar.php">
                                                    <input type="hidden" name="item_inv_n" value="'.$tov->item_inv_n.'">
                                                    <input type="hidden" name="br_id" value="'.$br->id_kb.'">
                                                    <input type="submit" class="btn btn-sm btn-danger" name="action" value="отменить возврат" onclick="return confirm(\'Вы действительно хотите отменить возврат товара?\');">
                                            </form>';
                            }


                            $kb_rez.='
                            <!--
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#exampleModal">
            Запустить модальное окно
        </button>
        -->
                            </td>
                    </tr>';

        return $kb_rez;
    }




    public static function getChangesArray($kb_id) {
        $rez = array('from'=>'', 'to'=>'', 'who'=>'');

        if ($kbchs=KBChange::getAllChanges($kb_id)) {
            foreach ($kbchs as $kbch) {
                $rez['from'].='<br><span style="color: #6c757d; font-style: italic">'.$kbch->from_old->format("d.m (H:i)").'</span>';
                $rez['to'].='<br><span style="color: #6c757d; font-style: italic">'.$kbch->to_old->format("d.m (H:i)").'</span>';
                $rez['who'].='<br><span style="color: #6c757d; font-style: italic">'.\bb\models\User::GetUserName($kbch->appr_who_old_id).': '.$kbch->ch_time->format("d.m. (H:i)").'</span>';
            }
        }

        return $rez;
    }

    public static function dateKbFormat1(\DateTime $d){
        $rez='
        <div style="position: relative; background-color: #00a0d0">
            <div style="float: left;">
                <div>'.$d->format("d").'</div>
                <div>'.Base::$months_text[$d->format("m")].'</div>
            </div>
            <div class="h-100" style="float: left; text-align: center; background-color: #00FF00">
                '.$d->format("H:i").'
            </div>
        </div>    
        ';
        return $rez;
    }


    public static function RusDay(\DateTime $date){

        $day_num=$date->format("w"); //0=воскресенье

        switch ($day_num) {
                case '1':
                    return 'Понедельник';
                    break;

                case '2':
                    return 'Вторник';
                    break;

                case '3':
                    return 'Среда';
                    break;

                case '4':
                    return 'Четверг';
                    break;

                case '5':
                    return 'Пятница';
                    break;

                case '6':
                    return 'Суббота';
                    break;

                case '0':
                    return 'Воскресенье';
                    break;
                default:
                    return 'День не определен';
                    break;
            }
    }

    public static function StartButton($inv_n) {
        $result='<input type="button" name="action" value="к_бронь" id="kb_button_'.$inv_n.'" onclick="kb_first(\'kb_form_'.$inv_n.'\',\''.$inv_n.'\')" />
        <div id="k_container_'.$inv_n.'" style="position:relative; width: 0; height: 0;">';
        $result.=self::StartForm($inv_n);
        $result.='</div>';
        return $result;
    }

    /**
     * @param $inv_n
     * @return string
     */
    public static function StartForm($inv_n){
        return '
        
            <form id="kb_form_'.$inv_n.'" action="kb_ajax_eng.php" method="post">
               <input type="hidden" name="inv_n" value="'.$inv_n.'">
               <input type="hidden" name="action" value="first_bron">
            </form>    
        ';
    }

    public static function DateForm($inv_n) {
        $result='
        <form method="post" id="kb_form_'.$inv_n.'" class="kb_date_choose">
        Дата праздника:<br>
        <input type="date" name="event_date" value="'.date("Y-m-d").'">
            <input type="hidden" name="inv_n" value="'.$inv_n.'">
            <input type="hidden" id="kb_form_date_action_'.$inv_n.'" name="action" value="date_choosen">
        <input type="button" class="action" value="далее" onclick="kb_date_sent(\'kb_form_'.$inv_n.'\',\'k_container_'.$inv_n.'\')">
        <input type="button" class="action" value="отмена" onclick="kb_cancel();">
        </form>
        ';
        return $result;
    }

    public function pxFromStart(\DateTime $date) {
        $start_h_add_on=$this->start_time->format("H")+$this->start_time->format("i")/60;
        $diff=$this->start_time->diff($date);
        $h_diff=$diff->h + $diff->days*24+$diff->i/60+$start_h_add_on;
        $px=round($h_diff*self::$px_per_hour);

        return $px;
    }

    public static function LineForm($inv_n, $event_date) {
        $result='';


        return $result;
    }

    public function bronLengthPx(KBron $bron) {
        $diff = $bron->from_kb->diff($bron->to_kb);
        $h_diff = $diff->h+$diff->days*24+$diff->i/60;
        $px = round($h_diff*self::$px_per_hour);
        return $px;
    }

    private function calcDaysFormQuantityFromFreePeriods(array $free_periods) {
        /**
         * @var KBron[] $free_periods
         */
        //Base::varDamp($free_periods);

        $this->start_time= clone $free_periods[0]->from_kb;

        $t1=clone $free_periods[0]->from_kb;
        $rec_num=count($free_periods);
        if ($rec_num==1) {
            $t2=clone $free_periods[0]->to_kb;
            $this->end_day= clone $free_periods[0]->to_kb;
        }
        else {
            $t2=clone $free_periods[$rec_num-1]->to_kb;
            $this->end_time= clone $free_periods[$rec_num-1]->to_kb;
        }
            $t1->setTime(0,0);
            $t2->setTime(0,0);

        $diff=$t1->diff($t2);
        $this->days_form_quantity=$diff->days+1;

    }

    private function calcDaysFormQuantity() {

        $t1=clone $this->start_time;
        $t2=clone $this->end_time;

        $t1->setTime(0,0);
        $t2->setTime(0,0);

        $diff=$t1->diff($t2);
        $this->days_form_quantity=$diff->days+1;

    }

    public static function getPauseDivs(\DateTime $from, \DateTime $to) {//!!!todo: make variant with no space for green line (i.e. only one yellow box)
        $from_p_end = clone $from;
            $from_p_end->modify("+".KBron::$pause_time_h." hour");
        $to_p_start = clone $to;
            $to_p_start->modify("-".KBron::$pause_time_h." hour");

        $diff=$to->diff($from);
        $diff_h=round($diff->h+$diff->days*24+$diff->i/60);

        if ($diff_h>KBron::$pause_time_h*2) {
            $result = '
            <div class="pause_div" style="width:' . (KBron::$pause_time_h * (self::$w_day_px / 24)) . 'px; left: 0px">
                 <div class="br_free_h_left" style="left: 0px;">' . $from->format("H") . '</div>
                 <div class="br_free_h_left" style="left: ' . (self::$px_per_hour + 20) . 'px;">' . $from_p_end->format("H") . '<sup>' . $from_p_end->format("i") . '</sup></div>
            </div>
            <div class="pause_div" style="width:' . (KBron::$pause_time_h * (self::$w_day_px / 24)) . 'px; right: 0px">
                <div class="br_free_h_left" style="left: 6px; top: 20px;">' . $to->format("H") . '</div>
                <div class="br_free_h_left" style="left: ' . (self::$px_per_hour * KBron::$pause_time_h - 53) . 'px; top: 17px;">' . $to_p_start->format("H") . '<sup>' . $to_p_start->format("i") . '</sup></div>
            </div>
            ';
        }
        elseif($diff_h>=3) {
            $result = '
            <div class="pause_div" style="width:' . ($diff_h * (self::$w_day_px / 24)) . 'px; left: 0px">
                 <div class="br_free_h_left" style="left: 0px;">' . $from->format("H") . '</div>
                 <div class="br_free_h_left" style="left: 6px; top: 20px;">' . $to->format("H") . '</div>
            </div>
            ';
        }
        else {
            $result = '
            <div class="pause_div" style="width:' . ($diff_h * (self::$w_day_px / 24)) . 'px; left: 0px">
                 
            </div>
            ';
        }
            return $result;
    }

    public function getLineForm() {

        $interval= new \DateInterval("P1D");
        $daterange = new \DatePeriod($this->start_time, $interval, $this->end_time);

        //Base::varDamp($daterange);

        $result_form='<div class="br_wh_cont">
                        <div class="br_cont" style="width: '.(144*$this->days_form_quantity).'px;">
                        <input type="button" style="position: absolute; top: -5px; right: -5px; z-index: 10;" class="action" value="X" onclick="kb_cancel();">
                        ';

        //рисуем сетку дней
        $days_counter=0;
        foreach ($daterange as $date) {
            $result_form.='
            <div class="br_day_name" style="left:'.(self::$w_day_px*$days_counter).'px;"><strong>'.$date->format("d.m.Y").'<br>'.self::RusDay($date).'</strong></div>
            <div class="br_day" style="left:'.(self::$w_day_px*$days_counter).'px;"></div>
            ';
            $days_counter++;
        }



        //рисуем свободные периоды
//Base::varDamp($this->free_periods);
        foreach ($this->free_periods as $fp) {
$tmp='
<div class="br_free_h_left">'.$fp->from_kb->format("H").'<sup>'.$fp->from_kb->format("i").'</sup></div>
                <div class="br_free_h_right">'.$fp->to_kb->format("H").'<sup>'.$fp->to_kb->format("i").'</sup></div>
                ';

            $result_form.='
            <a class="br_free_div action" href="#" id="a_id_'.$fp->getUniqId().'" onclick="kb_sent2(\''.$fp->getUniqId().'\',\'k_container_'.$fp->inv_n.'\', \'a_id_'.$fp->getUniqId().'\'); return false;" style="left:'.$this->pxFromStart($fp->from_kb).'px; width:'.$this->bronLengthPx($fp).'px;">
                '.KBronForm::getPauseDivs($fp->from_kb, $fp->to_kb).'
            </a>
            <form id="'.$fp->getUniqId().'" action="/bb/kb_ajax_eng.php" method="post" style="display: none;">
                <input type="hidden" name="action" value="free_period_first">
                <input type="hidden" name="tmp_br_id" value=""> 
                <input type="hidden" name="inv_n" value="'.$fp->inv_n.'">
                <input type="hidden" name="from_kb" value="'.$fp->from_kb->format("Y-m-d H:i").'">
                <input type="hidden" name="to_kb" value="'.$fp->to_kb->format("Y-m-d H:i").'">
                
            </form>
            ';
        }

        $result_form.='</div></div>';

        $result='
                
            
            <input type="hidden" id="last_temp_br_id" value="">
            <input type="hidden" id="last_inv_n_br" value="">
                        
            </div>
	    </div> 
        ';

        return $result_form;

    }

    public static function getLineBronForm(KBron $kb) {

        $result= '
    <form method="post" action="" name="" id="kb_info_form_'.$kb->inv_n.'" class="bron_form">
    <span>Период: '.$kb->from_kb->format("d.m (H:i)").' - '.$kb->to_kb->format("d.m (H:i)").'</span>
    <br><br>Выберите дату и время выдачи костюма:<br>	
    <select name="kb_start_date" id="kb_start_date_select_'.$kb->inv_n.'" onchange="getWorkingHours(\'kb_start_date_select_'.$kb->inv_n.'\', \'kb_start_hour_select_'.$kb->inv_n.'\')">
        <option value="0">день</option>';

        $day=clone $kb->from_kb;
        $end_day=clone $kb->to_kb;
        $end_day->setTime(23, 59);
        $days_options='';
        while ($day<=$end_day) {
            if (Schedule::isWorkingDay($day)) $days_options.=Base::dayOption($day);
            $day->modify("+1 day");
        };

        $result.=$days_options;

     $result.='</select>	в 
     <select name="kb_start_time" id="kb_start_hour_select_'.$kb->inv_n.'">
        <option value="0">время</option>
     </select><sup>00</sup>
     
     <br>Время и дата возврата костюма в салон:<br>	
     <select name="kb_end_date" id="kb_end_date_select_'.$kb->inv_n.'" onchange="getWorkingHours(\'kb_end_date_select_'.$kb->inv_n.'\', \'kb_end_hour_select_'.$kb->inv_n.'\')">
        <option value="0">день</option>';
        $result.=$days_options;
     $result.='
     </select>	до 
     <select name="kb_end_time" id="kb_end_hour_select_'.$kb->inv_n.'">
        <option value="0">время</option>
     </select><sup>00</sup><br>
     Ф.И.О.:<input type="text" name="fio" id="br_fio_'.$kb->inv_n.'" size="45">*<br>
     Телефон 1*:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>+375-</strong><input type="text" name="phone1" id="br_phone1_'.$kb->inv_n.'"><br>
     используется для подтверждения брони<br>
     Телефон 2:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>+375-</strong><input type="text" name="phone2" id="br_phone2_'.$kb->inv_n.'"><br>
     e-mail:<input type="email" name="email" id="br_mail_'.$kb->inv_n.'"><br>
     Дополнительная информация:<br><textarea name="info" cols="50" rows="4" id="br_info_'.$kb->inv_n.'"></textarea><br>
     <input type="hidden" value="'.KBron::$min_bron_time_h.'" name="br_min_h" id="br_min_h_'.$kb->inv_n.'">
     <input type="hidden" value="'.$kb->id_kb.'" name="br_tmp_id" id="br_tmp_id_'.$kb->inv_n.'">
     <input type="hidden" value="'.$kb->inv_n.'" name="inv_n">
     <input type="hidden" value="'.$kb->to_kb->format("Y-m-d H:i").'" name="" id="max_time_'.$kb->inv_n.'">
     <input type="hidden" value="'.$kb->from_kb->format("Y-m-d H:i").'" name="" id="min_time_'.$kb->inv_n.'">
     <input type="hidden" value="'.$kb->ch_time->getTimestamp().'" name="ch_time" id="">
     <input type="hidden" name="action" value="bron_create">
     <input type="button" class="action" onclick="bron_check(\''.$kb->inv_n.'\');" value="сохранить бронь">
     <input type="button" class="action" onclick="kb_cancel();" value="отмена"><br><i>* обязательные поля</i>		
    </form>
    ';
     return $result;
    }

    public static function getWorkingHoursOptions(\DateTime $time) {

        $start=clone $time;
            $start->setTime(Schedule::getOpenHour($start), 0);
        $end = clone $time;
            $end->setTime(Schedule::getCloseHour($start), 00);


        //echo Schedule::getOpenHour($start).'---'.Schedule::getCloseHour($start);

        $result='<option value="0">время</option>';
        while ($start<=$end) {
            //var_dump($start);

            if (Schedule::isWorkingTime($start)) {
                $result.=Base::hourOption($start);
            }
            $start->modify("+1 hour");
        }
        return $result;

    }


    public function daysNum() {
        return 3;
    }

    public static function tooLateForm(\DateTime $date) {
        return '<div class="br_wh_cont">
                        <div class="br_cont" style="text-align: center; font-size: 20px;">
                            К сожалению, свободные периоды на '.$date->format("d.m.Y").' отсутствуют.<br> 
                            <input type="button" value="OK" class="action" onclick="kb_cancel();">
                        </div>
                     </div>';
    }

    public function createFromEventDate(\DateTime $event_time, $inv_n, $days_for_form=3, $all_controll=1) {
        $days_step=($days_for_form-1)/2 - ($days_for_form-1)%2;
        $this->inv_n=$inv_n;

        $this->event_time = clone $event_time;

        $start_date=clone $this->event_time;
        $start_date->modify("-".$days_step." day");
        Schedule::setOpenDayTimeLeft($start_date);
        $this->start_time=$start_date;

        $end_date= clone $this->event_time;
        $end_date->modify("+".$days_step." day");
        //Base::varDamp($end_date);
        Schedule::setCloseDayTimeRight($end_date);
        //Base::varDamp($end_date);
        $this->end_time=$end_date;

        $this->free_periods = KBron::getFreePeriodsInv($inv_n, $this->start_time, $this->end_time, $all_controll);
        if (count($this->free_periods)>0) {
            //$this->calcDaysFormQuantityFromFreePeriods($this->free_periods);
            $this->calcDaysFormQuantity();
            return true;
        }
        else {
            return false;
        }
    }

    public function __construct()
    {
        $this->event_time = new \DateTime("1970-01-01 00:00");
        $this->start_time = new \DateTime("1970-01-01 00:00");
        $this->end_time = new \DateTime("1970-01-01 00:00");
    }

}