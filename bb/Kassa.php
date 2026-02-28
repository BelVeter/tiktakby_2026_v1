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

    /** @var float sum of delivery_to_pay for the day (courier column) */
    public $delivery_sum = 0;

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

    /**
     * Get total delivery_to_pay for the day from the courier sub-deals (delivery_yn=1)
     */
    public function GetDeliveryForDay($acc_date_str, $office) {
        $db = Db::getInstance();
        $mysqli = $db->getConnection();

        $date_ts = strtotime($acc_date_str);

        $where_place = ($office != 'all') ? "AND sub1.place='$office'" : '';

        $sql = "
            SELECT COALESCE(SUM(sub1.delivery_to_pay), 0) AS total_delivery
            FROM rent_sub_deals_act AS sub1
            WHERE sub1.acc_date='$date_ts' AND sub1.delivery_yn='1' $where_place
            UNION ALL
            SELECT COALESCE(SUM(sub1.delivery_to_pay), 0)
            FROM rent_sub_deals_arch AS sub1
            WHERE sub1.acc_date='$date_ts' AND sub1.delivery_yn='1' $where_place
        ";

        $result = $mysqli->query($sql);
        $total = 0;
        while ($row = $result->fetch_row()) {
            $total += (float)$row[0];
        }
        $this->delivery_sum = $total;
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

        // Load delivery sum for courier column
        $this->GetDeliveryForDay($acc_date, $office);
    }

    public function PrintKassaTable(){
        if ($this->office=='all') return '';

        $k1_start  = $this->ostatok_start['k1'] !== null ? number_format($this->ostatok_start['k1'], 2, '.', ' ') : '—';
        $k2_start  = $this->ostatok_start['k2'] !== null ? number_format($this->ostatok_start['k2'], 2, '.', ' ') : '—';

        $k1_sales  = number_format($this->sales['k1'],   2, '.', ' ');
        $k2_sales  = number_format($this->sales['k2'],   2, '.', ' ');
        $card_sales= number_format($this->sales['card'],  2, '.', ' ');
        $bank_sales= number_format($this->sales['bank'],  2, '.', ' ');
        $deliv_sum = number_format($this->delivery_sum,   2, '.', ' ');

        $k1_rash   = number_format($this->doh_rash['k1'], 2, '.', ' ');
        $k2_rash   = number_format($this->doh_rash['k2'], 2, '.', ' ');

        $k1_end    = number_format($this->ostatok_end['k1'], 2, '.', ' ');
        $k2_end    = number_format($this->ostatok_end['k2'], 2, '.', ' ');

        $total_end = number_format($this->ostatok_end['k1'] + $this->ostatok_end['k2'], 2, '.', ' ');

        // ИТОГО = К1 + К2 + По картам + Счет (bank)
        $itogo_sales = number_format(
            $this->sales['k1'] + $this->sales['k2'] + $this->sales['card'] + $this->sales['bank'],
            2, '.', ' '
        );

        $saved_style = $this->KassaSavedStyle();

        $output = '
        <table class="kassa-table" id="stats2">
            <thead>
            <tr>
                <th></th>
                <th>К1</th>
                <th>К2</th>
                <th>По картам</th>
                <th>Счет</th>
                <th class="kassa-courier">Курьер</th>
                <th class="kassa-itogo">Итого</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td class="kassa-label">Вх. остаток</td>
                <td class="kassa-num kassa-muted">' . $k1_start . '</td>
                <td class="kassa-num kassa-muted">' . $k2_start . '</td>
                <td class="kassa-num kassa-muted">—</td>
                <td class="kassa-num kassa-muted">—</td>
                <td class="kassa-num kassa-muted">—</td>
                <td class="kassa-num kassa-muted">—</td>
            </tr>
            <tr>
                <td class="kassa-label">Выручка</td>
                <td class="kassa-num kassa-bold">' . $k1_sales . '</td>
                <td class="kassa-num kassa-bold">' . $k2_sales . '</td>
                <td class="kassa-num kassa-bold">' . $card_sales . '</td>
                <td class="kassa-num kassa-bold">' . $bank_sales . '</td>
                <td class="kassa-num kassa-courier">' . $deliv_sum . '</td>
                <td class="kassa-num kassa-itogo">' . $itogo_sales . '</td>
            </tr>
            <tr>
                <td class="kassa-label"><a href="#" onclick="rash_show(); return false;" style="color:#3a7bd5;text-decoration:none;">Расход(-)/доход(+)</a></td>
                <td class="kassa-num" style="color:' . ($this->doh_rash['k1'] < 0 ? '#e74c3c' : '#333') . ';">' . $k1_rash . '</td>
                <td class="kassa-num" style="color:' . ($this->doh_rash['k2'] < 0 ? '#e74c3c' : '#333') . ';">' . $k2_rash . '</td>
                <td class="kassa-num kassa-muted">—</td>
                <td class="kassa-num kassa-muted">—</td>
                <td class="kassa-num kassa-muted">—</td>
                <td class="kassa-num kassa-muted">—</td>
            </tr>
            <tr ' . $saved_style . '>
                <td class="kassa-label">Остаток, конец дня</td>
                <td class="kassa-num kassa-muted">' . $k1_end . '</td>
                <td class="kassa-num kassa-muted">' . $k2_end . '</td>
                <td class="kassa-num kassa-muted">—</td>
                <td class="kassa-num kassa-muted">—</td>
                <td class="kassa-num kassa-muted">—</td>
                <td class="kassa-num kassa-muted kassa-bold">' . $total_end . '</td>
            </tr>
            </tbody>
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
            <input form="srch_form" type="submit" name="action" value="сохранить остаток" style="margin-top:6px; padding:4px 12px; border-radius:5px; border:1px solid #c5d8fb; background:#e8f0fe; color:#2b72c8; font-size:0.82rem; cursor:pointer;" />
            
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
