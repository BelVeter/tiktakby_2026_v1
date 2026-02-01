<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 30.09.2018
 * Time: 9:42
 */

namespace bb;


class Kassa
{
    public $acc_date;
    public $office;

    public $ostatok_start;
    public $sales;
    public $doh_rash;
    public $ostatok_end;
    public $ostatok_last_saved;

    public $cr_who;
    public $cr_when;

    public static $_total_ostatok;

    /**
     * Kassa constructor.
     */
    public function __construct()
    {
        $this->ostatok_start=array('k1'=>0, 'k2'=>0);
        $this->sales=array('k1'=>0, 'k2'=>0, 'card'=>0, 'bank'=>0);
        $this->doh_rash=array('k1'=>0, 'k2'=>0);
        $this->ostatok_end=array('k1'=>0, 'k2'=>0);
        $this->ostatok_last_saved=array('k1'=>0, 'k2'=>0);
    }


    public static function GetKassaLastOstatok($acc_date, $office, $kassa, $type='before') {
        //$acc_date=strtotime($acc_date);

        $result='что-то не получилось';

        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        if ($type=='before') {
            $sql_query = "SELECT `k_amount_end` FROM kassas WHERE `channel`='$office' AND kassa='$kassa' AND acc_date<$acc_date ORDER BY cr_when DESC LIMIT 1";
        }
        elseif ($type=='now') {
            $sql_query = "SELECT `k_amount_end` FROM kassas WHERE `channel`='$office' AND kassa='$kassa' AND acc_date=$acc_date ORDER BY cr_when DESC LIMIT 1";
        }

        $result = $mysqli->query($sql_query);

        if (!$result) {
            echo 'Ошибка. Сделайте скриншот для Димы: '.$sql_query;
        }

        if ($row = $result->fetch_assoc()) {
            $result = (float)$row['k_amount_end'];
        }
        else $result = null;



        return $result;

    }

    public function GetRashs(){
        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        $sql_query = "
          SELECT kassa, SUM(amount) FROM `doh_rash`
            WHERE channel='$this->office' AND acc_date='$this->acc_date'
            GROUP BY kassa
        ";
        //echo $sql_query;

        $result = $mysqli->query($sql_query);
        while ($row = $result->fetch_assoc()) {
            $this->doh_rash[$row['kassa']]=$row['SUM(amount)'];
        }
    }

    public function LoadKassa($acc_date, $office, array $sales) {
        $this->acc_date=strtotime($acc_date);
        $this->office=$office;

        $this->ostatok_start['k1']=self::GetKassaLastOstatok($this->acc_date, $this->office, 'k1', 'before');
        $this->ostatok_start['k2']=self::GetKassaLastOstatok($this->acc_date, $this->office, 'k2', 'before');

        if (isset($sales['nal_cheque'])) $this->sales['k1'] = $sales['nal_cheque'];
        if (isset($sales['nal_no_cheque'])) $this->sales['k2'] = $sales['nal_no_cheque'];
        if (isset($sales['card'])) $this->sales['card'] = $sales['card'];
        if (isset($sales['bank'])) $this->sales['bank'] = $sales['bank'];

        $this->GetRashs();

        $this->ostatok_end['k1']=$this->ostatok_start['k1']+$this->sales['k1']+$this->doh_rash['k1'];
        $this->ostatok_end['k2']=$this->ostatok_start['k2']+$this->sales['k2']+$this->doh_rash['k2'];

        $this->ostatok_last_saved['k1']=self::GetKassaLastOstatok($this->acc_date, $this->office, 'k1', 'now');
        $this->ostatok_last_saved['k2']=self::GetKassaLastOstatok($this->acc_date, $this->office, 'k2', 'now');

    }

    public function PrintKassaTable(){
        if ($this->office=='all') return '';

        $output= '
        <table border="1" cellspacing="0" style="background-color:#AFDC7E; display:block; float:left; margin: 0 20px;" id="stats2">
        <tr>
            <th></th>
            <th>Касса 1</th>
            <th>Касса 2</th>
            <th>Терминал</th>

        </tr>
        <tr>
            <td>Входящий остаток:</td>
            <td style="text-align:right">'.number_format($this->ostatok_start['k1'], 2, ',', ' ').'</td>
            <td style="text-align:right">'.$this->ostatok_start['k2'].'</td>
            <td style="text-align:right">X</td>
        </tr>
        <tr>
            <td>Выручка:</td>
            <td style="text-align:right">'.number_format($this->sales['k1'], 2, ',', ' ').'</td>
            <td style="text-align:right">'.number_format($this->sales['k2'], 2, ',', ' ').'</td>
            <td style="text-align:right">'.number_format($this->sales['card'], 2, ',', ' ').'</td>
        </tr>
        <tr>
            <td><a href="#" onclick="rash_show(); return false;">Расход(-)\доход(+):</a></td>
            <td style="text-align:right">'.number_format($this->doh_rash['k1'], 2, ',', ' ').'</td>
            <td style="text-align:right">'.number_format($this->doh_rash['k2'], 2, ',', ' ').'</td>
            <td style="text-align:right">X</td>
        </tr>
        <tr '.$this->KassaSavedStyle().'>
            <td>Остаток, конец дня:</td>
            <td style="text-align:right"><span>'.number_format($this->ostatok_end['k1'], 2, ',', ' ').'</span></td>
            <td style="text-align:right"><span>'.number_format($this->ostatok_end['k2'], 2, ',', ' ').'</span></td>
            <td style="text-align:right; font-weight: bold;"><span>'.number_format(($this->ostatok_end['k2']+$this->ostatok_end['k1']), 2, ',', ' ').'</td>
        </tr>
        

    </table> 
    ';
        self::$_total_ostatok=$this->ostatok_end['k2']+$this->ostatok_end['k1'];

        if (($this->acc_date+60*60*24)>time()) $output.=$this->PrintSaveFormFields();

        return $output;
    }

    public function KassaSavedStyle(){
        if ($this->ostatok_last_saved['k1']==$this->ostatok_end['k1'] && (string)$this->ostatok_last_saved['k2']==(string)$this->ostatok_end['k2']) {
            return 'style="color:red"';
        }
    }

    public function PrintSaveFormFields(){
        if ($this->office=='all') return '';
        return '
            <input form="srch_form" type="hidden" name="k_acc_date" value="'.$this->acc_date.'" />
            <input form="srch_form" type="hidden" name="k_office" value="'.$this->office.'" />	
            <input form="srch_form" type="hidden" name="k1_start" value="'.$this->ostatok_start['k1'].'" />
            <input form="srch_form" type="hidden" name="k2_start" value="'.$this->ostatok_start['k2'].'" />
            <input form="srch_form" type="hidden" name="k1_sales" value="'.$this->sales['k1'].'" />
            <input form="srch_form" type="hidden" name="k2_sales" value="'.$this->sales['k2'].'" />
            <input form="srch_form" type="hidden" name="k1_rash" value="'.$this->doh_rash['k1'].'" />
            <input form="srch_form" type="hidden" name="k2_rash" value="'.$this->doh_rash['k2'].'" />
            <input form="srch_form" type="hidden" name="k1_end" value="'.$this->ostatok_end['k1'].'" />
            <input form="srch_form" type="hidden" name="k2_end" value="'.$this->ostatok_end['k2'].'" />
            <input form="srch_form" type="submit" name="action" value="сохранить остаток" style="position:relative; top:85px;" />
            
        ';
    }

    public static function SaveKassa () {
        $new_kassa= new Kassa();
            $new_kassa->acc_date=self::get_post('k_acc_date');
            $new_kassa->office=self::get_post('k_office');
            $new_kassa->ostatok_start['k1']=self::get_post('k1_start');
            $new_kassa->ostatok_start['k2']=self::get_post('k2_start');
            $new_kassa->sales['k1']=self::get_post('k1_sales');
            $new_kassa->sales['k2']=self::get_post('k2_sales');
            $new_kassa->doh_rash['k1']=self::get_post('k1_rash');
            $new_kassa->doh_rash['k2']=self::get_post('k2_rash');
            $new_kassa->ostatok_end['k1']=self::get_post('k1_end');
            $new_kassa->ostatok_end['k2']=self::get_post('k2_end');

        //!!! добавить проверку на измененные даты, когда страница долго висит, и кто-то пытается сохранить: в т.ч. проверку на дату = сегодня, а также на все обороты и расходы

        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        $sql_query = "INSERT INTO kassas VALUES('', '$new_kassa->acc_date', '$new_kassa->office', 'k1', '".$new_kassa->ostatok_start['k1']."', '".$new_kassa->sales['k1']."', '".$new_kassa->doh_rash['k1']."', '".$new_kassa->ostatok_end['k1']."', '".$_SESSION['user_id']."', '".time()."', 'final')";
        $result = $mysqli->query($sql_query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$sql_query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        $sql_query = "INSERT INTO kassas VALUES('', '$new_kassa->acc_date', '$new_kassa->office', 'k2', '".$new_kassa->ostatok_start['k2']."', '".$new_kassa->sales['k2']."', '".$new_kassa->doh_rash['k2']."', '".$new_kassa->ostatok_end['k2']."', '".$_SESSION['user_id']."', '".time()."', 'final')";
        $result = $mysqli->query($sql_query);
        if (!$result) {die('Сбой при доступе к базе данных: '.$sql_query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

        return "<strong>Остаток успешно сохранен</strong>";

    }

    private static function get_post($var) {
        $db = Db::getInstance();
        $mysqli = $db->getConnection();
        return $mysqli->real_escape_string($_POST[$var]);
    }



}