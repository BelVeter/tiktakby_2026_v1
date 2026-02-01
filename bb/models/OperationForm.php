<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 30.05.2019
 * Time: 22:28
 */

namespace bb\models;


use bb\Base;

class OperationForm
{
    public static $scc='<link href="/bb/models/OperationForm.css" rel="stylesheet" type="text/css" />';
    public static $js='<script src="/bb/models/OperationForm.js"></script>';

    public $dr_id;
    /**
     * @var \DateTime
     */
    public $operation_date;
    public $channel;//combined value (channel_type+_+channel_number)
    public $operation_type;
    public $op_amount;
    public $operation_type2;
    public $kassa_type;
    public $info;
    public $pesonal_op_id;
    public $shift_to_channel;
    public $kassa_type_shift;
    public $zpl_month;
    public $zpl_year;


    /**
     * @var \DateTime
     */
    public $date_srch_from;
    /**
     * @var \DateTime
     */
    public $date_srch_to;
    public $channel_srch;
    public $kassa_type_srch;
    public $type1_srch;//rash, doh, shift
    public $type2_srch;
    public $shift_to_channel_srch;
    public $kassa_type_shift_srch;
    public $pesonal_op_id_srch;

    public $form_action_address;
    public $form_name;
    public $total_op_amount=0;

    /**
     * @var KassaChannel[]
     */
    public $allowed_channels;


    public static function RequiredEcho(){
        //echo self::$scc;
        echo self::$js;
    }



    public function getNewOperationForm() {
        $this->form_name='new_op_form';
        $rez='<div class="card">';
        $rez.='<div class="card-header text-center">';

        $rez.='
        <input type="radio" form="'.$this->form_name.'" name="operation_type" value="rash" id="radio_rash" '.$this->op_type_checked('rash').' style="display:none;" onchange="op_form_sent(\''.$this->form_name.'\', \'new_operation_div\')"> 
            <label for="radio_rash" style="margin: 0"><div class="btn '.($this->operation_type=='rash' ? 'btn-warning' : 'btn-primary').'" style="margin: 5px 0;">Расходы</div></label>
        <input type="radio" form="'.$this->form_name.'" name="operation_type" value="doh" id="radio_dox" '.$this->op_type_checked('doh').' style="display:none;" onchange="op_form_sent(\''.$this->form_name.'\', \'new_operation_div\')"> 
            <label for="radio_dox" style="margin: 0"><div class="btn '.($this->operation_type=='doh' ? 'btn-warning' : 'btn-primary').'" style="margin: 5px 0;">Доходы</div></label>
        <input type="radio" form="'.$this->form_name.'" name="operation_type" value="shift" id="radio_shift" '.$this->op_type_checked('shift').' style="display:none;" onchange="op_form_sent(\''.$this->form_name.'\', \'new_operation_div\')"> 
            <label for="radio_shift" style="margin: 0"><div class="btn '.($this->operation_type=='shift' ? 'btn-warning' : 'btn-primary').'" style="margin: 5px 0;">Сдача в кассу</div></label>';
        $rez.='</div>';

        $rez.='<div class="card-body text-center">';
        $rez.='<form method="post" action="'.$this->form_action_address.'" name ="'.$this->form_name.'" id="'.$this->form_name.'" class="form-inline">';
        $rez.='<div class="form-group">';
        //$rez.='<input type="hidden" name="opetation_type" id="operation_type" value="'.$this->operation_type.'">';

        $rez.='</div>';
        $rez.='<div class="form-group">';

        $rez.='<select name="channel" id="channel" class="form-control" onchange="op_form_sent(\''.$this->form_name.'\', \'new_operation_div\')">';

        $chs=$this->allowed_channels;

        foreach ($chs as $ch) {

                $rez .= '<option value="' . $ch->makeSelectValue() . '" '.$this->channelSelected($ch->makeSelectValue()).'>'.$ch->makeSelectText().'</option>';
                if (isset($_POST['channel'])) $_POST['channel']=$ch->makeSelectValue();
        }

        $rez.='</select>';
            $read_o='';
            if (substr($this->channel,0,4)!='bank') {
                $read_o='readonly="readonly"';
            }

        $rez.='<input type="date" '.$read_o.' name="operation_date" id="operation_date" class="form-control" value="'.$this->operation_date->format("Y-m-d").'">';

        $kassas= Kassa::getKassaTypesListOperations($this->operation_type, $this->channel);

        $rez.='
            <select name="kassa_type" id="kassa_type" onchange="op_form_sent(\''.$this->form_name.'\', \'new_operation_div\')" class="form-control">';

        foreach ($kassas as $ks) {
            $rez.='<option value="'.$ks->kassa_type.'" '.$this->kassa_selected($ks->kassa_type).'>'.$ks->kassa_name.'</option>';
        }

        $rez.='</select>';

        $rez.='
        <input type="number" step="0.01" name="op_amount" id="op_amount" value="'.$this->op_amount.'" class="form-control text-center" placeholder="сумма: 0,00">
        ';
        $rez.='</div>';
        $rez.='<div class="form-group">';
            if ($this->operation_type!='shift') {
            $rez .= '
                <select name="operation_type2" id="operation_type2" class="selectpicker form-control" data-live-search="true" onchange="op_form_sent(\'' . $this->form_name . '\', \'new_operation_div\')">
                        <option value="0">не выбрано</option>';
                    $ops = KassaOperation::getOperationTypes2($this->channel, $this->operation_type);
                    //for clearing names if the value is not present in the list (лишний селект с именами)
                $type2present=0;
                    foreach ($ops as $op) {
                        $rez .= '<option value="' . $op->type2 . '" ' . $this->type2_selected($op->type2) . '>' . $op->name2 . '</option>';
                        if ($op->type2==$this->operation_type2) {
                            $type2present=1;
                        }
                    }
                    $rez .= '</select>';
            }
            if ($this->operation_type=='shift') {
                $rez.='<select name="shift_to_channel" id="shift_to_channel" class="form-control" onchange="op_form_sent(\''.$this->form_name.'\', \'new_operation_div\')">';

                $chls=KassaChannel::getAllowedChannels('shift_to_channel');
                $rez .= '<option value="">не выбрано</option>';

                foreach ($chls as $ch) {

                    $rez .= '<option value="' . $ch->makeSelectValue() . '" '.self::sel_d($ch->makeSelectValue(), $this->shift_to_channel).'>'.$ch->makeSelectText().'</option>';
                    if (isset($_POST['shift_to_channel'])) $_POST['shift_to_channel']=$ch->makeSelectValue();
                }

                $rez.='</select>';
                $kassas= Kassa::getKassaTypesListOperations($this->operation_type, $this->channel);

                $rez.='
                <select name="kassa_type_shift" id="kassa_type_shift" onchange="op_form_sent(\''.$this->form_name.'\', \'new_operation_div\')" class="form-control">';
                $rez.='<option value="">не выбрано</option>';
                foreach ($kassas as $ks) {
                    $rez.='<option value="'.$ks->kassa_type.'" '.self::sel_d($ks->kassa_type, $this->kassa_type_shift).'>'.$ks->kassa_name.'</option>';
                }

                $rez.='</select>';
                $this->operation_type2='';
            }
            //if an operation like zpl or advance has personal link - the person should be chosen
        if ($this->operation_type=='rash' && in_array($this->operation_type2, array('avans','zpl'), true) && $type2present==1) {
            $rez.='<select name="pesonal_op_id" id="pesonal_op_id" class="selectpicker form-control" data-live-search="true">
                    <option value="0">кому?(выберите)</option>';
            $pers_users=User::getAdvanceUsers();
            foreach ($pers_users as $u) {
                $rez.='<option value="'.$u->id_user.'" '.self::sel_d($u->id_user, $this->pesonal_op_id).'>'.$u->getShortName().'</option>';
            }

           $rez.='</select>';
        }

        if ($this->operation_type=='rash' && $this->operation_type2=='zpl') {
                $rez.='
                <select name="zpl_month" id="zpl_month" class="form-control">
                    <option value="0" '.self::sel_d($this->zpl_month, 0).'>месяц</option>
                    <option value="1" '.self::sel_d($this->zpl_month, 1).'>январь</option>
                    <option value="2" '.self::sel_d($this->zpl_month, 2).'>февраль</option>
                    <option value="3" '.self::sel_d($this->zpl_month, 3).'>март</option>
                    <option value="4" '.self::sel_d($this->zpl_month, 4).'>апрель</option>
                    <option value="5" '.self::sel_d($this->zpl_month, 5).'>май</option>
                    <option value="6" '.self::sel_d($this->zpl_month, 6).'>июнь</option>
                    <option value="7" '.self::sel_d($this->zpl_month, 7).'>июль</option>
                    <option value="8" '.self::sel_d($this->zpl_month, 8).'>август</option>
                    <option value="9" '.self::sel_d($this->zpl_month, 9).'>сентябрь</option>
                    <option value="10" '.self::sel_d($this->zpl_month, 10).'>октябрь</option>
                    <option value="11" '.self::sel_d($this->zpl_month, 11).'>ноябрь</option>
                    <option value="12" '.self::sel_d($this->zpl_month, 12).'>декабрь</option>
                </select>
                ';

                $rez.='
                <input type="number" name="zpl_year" id="zpl_year" min="2019" maxlength="4" max="9999" class="form-control" value="'.$this->zpl_year.'">
                ';
        }

        $rez.='</div>';
        $rez.='<div class="form-group">';

        $rez.='<textarea cols="80" rows="3" name="op_info" id="op_info" class="form-control">'.$this->info.'</textarea>';

        $rez.='<input type="submit" name="action" value="Сохранить" class="btn btn-secondary" onclick="return new_rash_check();" style="margin: 0 10px;">';

        $rez.='</div>';
        $rez.='</form>';
        $rez.='</div>';
        $rez.='</div>';

        return $rez;
    }

    public static function sel_d($v1, $v2){
        if ($v1==$v2) {
            return 'selected="selected"';
        }
        else return null;
    }

    private function op_type_checked($op_type){
        if ($this->operation_type==$op_type) {
            return 'checked';
        }
    }

    private function kassa_selected($pattern){
        if ($this->kassa_type==$pattern) {
            return 'selected="selected"';
        }
        else return '';
    }

    private function type2_selected($pattern){
        if ($this->operation_type2==$pattern) {
            return 'selected="selected"';
        }
        else return '';
    }


    public function getSrchForm() {
        $rez='<form method="post" action="'.$this->form_action_address.'" name="srch_form" id="srch_form" class="form-inline">';

        $rez.='<div class="input-group">';
        $rez.='<label for="date_srch_from">C</label>';
        $rez.='<input type="date" name="date_srch_from" id="date_srch_from" class="form-control" aria-describedby="basic-addon3" value="'.$this->date_srch_from->format("Y-m-d").'">';
        $rez.='<label for="date_srch_to">По</label>';
        $rez.='<input type="date" name="date_srch_to" id="date_srch_to" class="form-control" aria-describedby="basic-addon3" value="'.$this->date_srch_to->format("Y-m-d").'">';
        $rez.='</div>';
        $rez.='<select name="channel_srch" id="channel_srch" class="form-control" onchange="this.form.submit();">';
                $rez.='<option value="">все каналы</option>';
        $chs=$this->allowed_channels;

        foreach ($chs as $ch) {

            $rez .= '<option value="' . $ch->makeSelectValue() . '" '.self::sel_d($ch->makeSelectValue(), $this->channel_srch).'>'.$ch->makeSelectText().'</option>';
            if (isset($_POST['channel'])) $_POST['channel']=$ch->makeSelectValue();
        }

        $rez.='</select>';
        //$rez.=$inputs_add_on;




        $rez.='<input type="submit" value="Показать">';
        $rez.='</form>';

        return $rez;
    }


    private function channelSelected($pattern) {
        if ($this->channel==$pattern) {
            return 'selected="selected"';
        }
        else return '';
    }

    public function postLoad(){
        if (isset($_POST['channel'])) {
            $this->channel=$_POST['channel'];
        }
        if (isset($_POST['operation_date'])) {
            $this->operation_date=new \DateTime($_POST['operation_date']);
        }
        if (isset($_POST['operation_type'])) {
            $this->operation_type=$_POST['operation_type'];
        }
        if (isset($_POST['kassa_type'])) {
            $this->kassa_type=$_POST['kassa_type'];
        }
        if (isset($_POST['op_amount'])) {
            $this->op_amount=$_POST['op_amount'];
        }
        if (isset($_POST['operation_type2'])) {
            $this->operation_type2=$_POST['operation_type2'];
        }
        if (isset($_POST['pesonal_op_id'])) {
            $this->pesonal_op_id=$_POST['pesonal_op_id'];
        }
        if (isset($_POST['op_info'])) {
            $this->info=$_POST['op_info'];
        }
        if (isset($_POST['shift_to_channel'])) {
            $this->shift_to_channel=$_POST['shift_to_channel'];
        }
        if (isset($_POST['kassa_type_shift'])) {
            $this->kassa_type_shift=$_POST['kassa_type_shift'];
        }
        if (isset($_POST['zpl_month'])) {
            $this->zpl_month=$_POST['zpl_month'];
        }
        if (isset($_POST['zpl_year'])) {
            $this->zpl_year=$_POST['zpl_year'];
        }

        if (isset($_POST['date_srch_from'])) {
            $this->date_srch_from=new \DateTime($_POST['date_srch_from']);
        }
        if (isset($_POST['date_srch_to'])) {
            $this->date_srch_to=new \DateTime($_POST['date_srch_to']);
        }
        if (isset($_POST['channel_srch'])) {
            $this->channel_srch=$_POST['channel_srch'];
        }
        if (isset($_POST['type1_srch'])) {
            $this->type1_srch=$_POST['type1_srch'];
        }
        if (isset($_POST['type2_srch'])) {
            $this->type2_srch=$_POST['type2_srch'];
        }
        if (isset($_POST['shift_to_channel_srch'])) {
            $this->shift_to_channel_srch=$_POST['shift_to_channel_srch'];
        }
        if (isset($_POST['kassa_type_shift_srch'])) {
            $this->kassa_type_shift_srch=$_POST['kassa_type_shift_srch'];
        }
        if (isset($_POST['kassa_type_srch'])) {
            $this->kassa_type_srch=$_POST['kassa_type_srch'];
        }
        if (isset($_POST['pesonal_op_id_srch'])) {
            $this->pesonal_op_id_srch=$_POST['pesonal_op_id_srch'];
        }
        if (isset($_POST['dr_id'])) {
            $this->dr_id=$_POST['dr_id'];
        }


        return true;
    }


    /**
     * @return \DateTime|null
     */
    public function dateFromZplPeriod() {
        if ($this->zpl_month>0 && $this->zpl_year>0) {
            $d = new \DateTime();
            $d->setDate($this->zpl_year, $this->zpl_month, 1);
            return $d;
        }
        else return null;
    }

    public function startTable(){
        echo $this->getSrchForm();

        $rez= '<table class="table table-bordered table-sm">
                  <thead>
                    <tr>
                      <th scope="col">Дата</th>
                      <th scope="col">Касса';
                        $kassas= Kassa::getKassaTypesListOperations($this->type1_srch, $this->channel_srch);

                        $rez.='
                            <select name="kassa_type_srch" id="kassa_type_srch" form="srch_form" onchange="this.form.submit();" class="form-control form-control-sm" style="width: 100px; height: 30px;">';
                        $rez.='<option value="">все</option>';

                        foreach ($kassas as $ks) {
                            $rez.='<option value="'.$ks->kassa_type.'" '.self::sel_d($ks->kassa_type, $this->kassa_type_srch).'>'.$ks->kassa_name.'</option>';
                        }

                        $rez.='</select>';
                    $rez.='  </th>
                      <th scope="col">Сумма</th>
                      <th scope="col">Операция <div class="form-inline">
                        <select class="form-control form-control-sm" style="width: 100px; height: 30px;" name="type1_srch" id="type1_srch" form="srch_form" onchange="this.form.submit();">
                            <option value="">все</option>
                            <option value="doh" '.self::sel_d('doh', $this->type1_srch).'>доходы</option>
                            <option value="rash" '.self::sel_d('rash', $this->type1_srch).'>расходы</option>
                            <option value="shift" '.self::sel_d('shift', $this->type1_srch).'>переводы</option>
                        </select>';
                        if ($this->type1_srch!='shift') {
                            $rez.='
                                <select name="type2_srch" id="type2_srch" class="selectpicker form-control form-control-sm col-12 col-sm-12 col-md-3 col-lg-3" data-live-search="true" form="srch_form" onchange="this.form.submit();">
                                        <option value="">все</option>';
                            $ops = KassaOperation::getOperationTypes2($this->channel_srch, $this->type1_srch);
                            //!!!???for clearing names if the value is not present in the list (лишний селект с именами)
                            //$type2present=0;
                            foreach ($ops as $op) {
                                $rez .= '<option value="' . $op->type2 . '" ' . self::sel_d($op->type2, $this->type2_srch) . '>' . $op->name2 . '</option>';
                                if ($op->type2==$this->operation_type2) {
                                    $type2present=1;
                                }
                            }
                            $rez .= '</select>';
                        }
                        if ($this->type1_srch=='shift') {
                            $rez.='<select name="shift_to_channel_srch" id="shift_to_channel_srch" class="form-control form-control-sm" form="srch_form" onchange="this.form.submit();">';

                            $chls=KassaChannel::getAllowedChannels('shift_to_channel');
                            $rez .= '<option value="">все офисы</option>';

                            foreach ($chls as $ch) {

                                $rez .= '<option value="' . $ch->makeSelectValue() . '" '.self::sel_d($ch->makeSelectValue(), $this->shift_to_channel_srch).'>'.$ch->makeSelectText().'</option>';
                                if (isset($_POST['shift_to_channel'])) $_POST['shift_to_channel']=$ch->makeSelectValue();
                            }

                            $rez.='</select>';
                            $kassas= Kassa::getKassaTypesListOperations($this->type1_srch, $this->channel_srch);

                            $rez.='
                                <select name="kassa_type_shift_srch" id="kassa_type_shift_srch" class="form-control form-control-sm" form="srch_form" onchange="this.form.submit();">';
                            $rez.='<option value="">все кассы</option>';
                            foreach ($kassas as $ks) {
                                $rez.='<option value="'.$ks->kassa_type.'" '.self::sel_d($ks->kassa_type, $this->kassa_type_shift_srch).'>'.$ks->kassa_name.'</option>';
                            }

                            $rez.='</select>';
                            $this->operation_type2='';
                        }
                        if ($this->type1_srch=='rash' && in_array($this->type2_srch, array('zpl', 'avans'))) {
                            $rez.='<select name="pesonal_op_id_srch" id="pesonal_op_id_srch" form="srch_form" class="selectpicker form-control form-control-sm col-12 col-sm-12 col-md-3 col-lg-3" data-live-search="true" onchange="this.form.submit();">
                                <option value="">кому?(всем)</option>';
                            $pers_users=User::getAdvanceUsers();
                            foreach ($pers_users as $u) {
                                $rez.='<option value="'.$u->id_user.'" '.self::sel_d($u->id_user, $this->pesonal_op_id_srch).'>'.$u->getShortName().'</option>';
                            }

                            $rez.='</select>';
                        }

                      $rez.='</div>
                      </th>
                      <th scope="col">Информация</th>
                      <th scope="col">Сотрудник</th>
                      <th scope="col">Действия</th>
                    </tr>
                  </thead>
                  <tbody>';

        return $rez;
    }

    public function tableRows() {
        $channel=KassaChannel::loadFormSelect($this->channel_srch);
        $rez='';
        $ops=KassaOperation::getOperations($this->date_srch_from, $this->date_srch_to, $this->type1_srch, $channel, $this->type2_srch, $this);
        foreach ($ops as $op) {
            $rez.='
            <tr>
                <td>'.$op->acc_date->format("d.m.Y").'</td>
                <td>'.KassaChannel::getKassaChannelName($op->channel, $op->channel_num).' <strong>'.$op->kassa.'</strong></td>
                <td class="text-right">'.number_format($op->amount,2,',', ' ').'</td>
                <td>'.KassaOperation::getRDItemName($op->type, $op->type2).($op->type2=='zpl' || $op->type2=='avans' ? '('.User::GetUserName($op->dr_name_id).($op->type2=='zpl' ? ' за '.Base::monthName($this->zpl_month).' '.$this->zpl_year.'г.' : '').')' : '').' '.OperationForm::shiftKassaName($op).'</td>
                <td>'.$op->info.' <br> тех инфо (id\link): '.'('.$op->id_operation.'-'.$op->link_to.')'.'</td>
                <td>'.User::GetUserName($op->cr_who).'</td>
                <td>
                    <form method="post" action="'.$this->form_action_address.'" class="form-inline" id="del_'.$op->id_operation.'">
                        <div class="form-group">
                            <input type="hidden" name="dr_id" value="'.$op->id_operation.'">
                            <input type="hidden" name="action" value="Удалить" >
                        </div>
                    </form>
                    <input type="button" value="удалить" class="btn btn-sm btn-danger" onclick=" if (confirm(\'Вы точно хотите удалить эту операцию?\')) formAppendSend(\'del_'.$op->id_operation.'\', \'srch_form\');" >
                </td>
            </tr>
            ';
            $this->total_op_amount+=$op->amount;
        }
        $rez.='
            <tr>
                <td><strong>Итого:</strong></td>
                <td></td>
                <td class="text-right"><strong>'.number_format($this->total_op_amount,2,',', ' ').'</strong></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            ';
        return $rez;
    }

    public function endTable() {
        return '</tbody>
               </table>';
    }

    public static function shiftKassaName(KassaOperation $op){
        $rez='';
        if ($op->type=='shift_plus' || $op->type=='shift_minus') {
            $op_link=KassaOperation::getOperation($op->link_to);
            $rez.=KassaChannel::getKassaChannelName($op_link->channel, $op_link->channel_num).' '.$op_link->kassa;
        }

        return $rez;
    }

    public function __construct($form_addres='', $form_name='')
    {
        $this->form_action_address=$form_addres;
        $this->operation_date = new \DateTime();
        $this->operation_type='rash';
        $this->zpl_year= $this->operation_date->format("Y");
        //$this->zpl_month=$this->operation_date->format("n");

        $this->allowed_channels=KassaChannel::getAllowedChannels();
        $this->channel_srch=$this->allowed_channels[0]->makeSelectValue();

        $this->date_srch_from=new \DateTime();
            $this->date_srch_from->setTime(0,0,0);
        $this->date_srch_to=new \DateTime();
            $this->date_srch_to->setTime(0,0,0);
    }

}