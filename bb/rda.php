<?php
namespace bb;

session_start();

ini_set("display_errors",1);
error_reporting(E_ALL);

set_time_limit(300);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);



//------- proverka paroley

$in_level= array(0,5,7);

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

	</form></body></html>');
}

//-----------proverka paroley

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/DealRow.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Payment.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Payment.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Office.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Kassa.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Collateral.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php');

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css?new" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
'.Base::getBarCodeReaderScript().'
<title>Сделки. NEW.</title>
</head>
<body>
';

$user = new User();
$user->id_user=$_SESSION['user_id'];
$user->level=$_SESSION['level'];
$user->user_name=$_SESSION['user_fio'];
User::$current_user=$user;

$ch_num_id='';
$sub_info_update='';
$ch_num_update='';
$ch_num_value='';
$sub_deal_id=0;
$sub_info_id=0;
$sub_info_value='';

$iDate=false;
$i_date=date("Y-m-d");
//echo $i_date;
$place=$_SESSION['office'];
$op_type='all';
$payment_type='all';


foreach ($_POST as $key => $value) {
    $$key = get_post($key);
}

//for payment bank sales count
$iDate = new \DateTime($i_date);


if (isset($_POST['action']) && $_POST['action']=='сохранить остаток') {
    echo Kassa::SaveKassa();
}



if ($ch_num_update=='yes' || $sub_info_update=='yes') {

    $db = Db::getInstance();
    $mysqli = $db->getConnection();

    $sub_id=0;
    if ($ch_num_id>0) $sub_id=$ch_num_id;
    if ($sub_info_id>0) $sub_id=$sub_info_id;

    $q_arch_act = "SELECT * FROM rent_sub_deals_act WHERE sub_deal_id='$sub_id' LIMIT 1";
    $result = $mysqli->query($q_arch_act);
    if (!$result) {die('Сбой при доступе к базе данных: '.$q_arch_act.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
    $aa_rows = $result->num_rows;

    if ($aa_rows>=1) {
        $srch_table='rent_sub_deals_act';
    }
    else {
        $srch_table='rent_sub_deals_arch';
    }

    if ($ch_num_update=='yes' && $ch_num_id>0) {
        $query_dl_upd = "UPDATE $srch_table SET ch_num='$ch_num_value' WHERE sub_deal_id='$ch_num_id'";
        //echo $query_dl_upd;
        if (!$mysqli->query($query_dl_upd)) {
            echo 'Сбой при доступе к базе данных: ' . $q_arch_act . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
        }
    }
    if ($sub_info_update=='yes' && $sub_info_id>0) {
        $query_dl_upd = "UPDATE $srch_table SET info='$sub_info_value' WHERE sub_deal_id='$sub_info_id'";
        //echo $query_dl_upd;
        if (!$mysqli->query($query_dl_upd)) {
            echo 'Сбой при доступе к базе данных: ' . $q_arch_act . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
        }
    }

}


use bb\classes\Collateral;

?>

<script language="javascript">

    history.pushState(null, null, location.href);
    window.onpopstate = function(event) {
        history.go(1);
    };


    function ch_num_close (chnid) {

        document.getElementById('ch_num_update').value="no";
        document.getElementById('ch_num_id').value="";
        document.getElementById('ch_num_value').value="";

        var divsToHide = document.getElementsByClassName("ch_div_st");

        for(var i = 0; i < divsToHide.length; i++)
        {
            divsToHide[i].style.display="none";
        }

    }


    function ch_num_show (chnid) {
        document.getElementById('ch_div_'+chnid).style.display="block";
        document.getElementById('ch_num_update').value="yes";
        document.getElementById('ch_num_id').value=document.getElementById('ch_num_id_'+chnid).value;;

    }

    function ch_num_save (chnid) {
        document.getElementById('ch_num_value').value=document.getElementById('ch_num_new_'+chnid).value;
        document.getElementById('srch_form').submit();
    }



    function sub_show (subid) {
        sub_info_close (subid);

        document.getElementById('sub_info_div_'+subid).style.display="block";
        document.getElementById('sub_info_update').value="yes";
        document.getElementById('sub_info_id').value=subid;
    }

    function sub_info_close (subid) {
        document.getElementById('sub_info_update').value="no";
        document.getElementById('sub_info_id').value="";
        document.getElementById('sub_info_value').value="";

        var divsToHide = document.getElementsByClassName("sub_info");

        for(var i = 0; i < divsToHide.length; i++)
        {
            divsToHide[i].style.display="none";
        }

    }

    function sub_info_save (subid) {
        document.getElementById('sub_info_value').value=document.getElementById('sub_info_new_'+subid).value;
        document.getElementById('srch_form').submit();
    }


</script>


<style>
  .zv-row{
    display: flex;
    flex-flow: column nowrap;
    gap: 10px;
  }

  .alert-danger{
    text-align: center!important;
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
    font-size: 20px;
  }

  .btn-danger{
    padding: 1px 10px;
    text-decoration: none;
    margin-left: 30px;
    cursor: pointer;
    font-size: 1.25rem;
    line-height: 1.5;
    border-radius: 0.3rem;
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
    display: inline-block;
    font-weight: 400;
    text-align: center;
    vertical-align: middle;
    border: 1px solid transparent;
  }

</style>


<?php

$dls = DealRow::GetLines($i_date, $place, $op_type, $payment_type);
$kassa = new Kassa();
$kassa->LoadKassa($i_date, $place, DealRow::$kassa_r_total);


echo '
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: '.$_SESSION['office'].'</form> </div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/cur_page2.php">Страница курьера</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
	<a class="div_item" href="/bb/rent_orders.php">Брони</a>
	<a class="div_item" href="/bb/doh-rash.php">Расходы</a><br />
		<form method="post" action="/bb/kr_baza_new.php" style="display:inline-block;">
			<input type="hidden" name="cat_id" value="2" /><input type="submit" value="КАРНАВАЛЫ" style="width:100px; height:35px; background-color:green; color:white" />
		</form>

</div>

<div class="row zv-row">
    <div class="col alert-danger h2 text-center" id="zv_div"></div>
</div>
';
require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show2.php');

//print_r(DealRow::$kassa_r_total);
//echo '<br><br>';

//print_r(DealRow::$kassa_d_total);

?>

    <form name="srch_form" method="post" id="srch_form" action="rda.php">
        Операции за дату:<input type="date" name="i_date" id="i_date" value="<?= $i_date ?>" />
        <input form="srch_form" type="hidden" name="ch_num_update" id="ch_num_update" value="no" />
        <input form="srch_form" type="hidden" name="ch_num_id" id="ch_num_id" value="" />
        <input form="srch_form" type="hidden" name="ch_num_value" id="ch_num_value" value="" />

        <input form="srch_form" type="hidden" name="sub_info_update" id="sub_info_update" value="no" />
        <input form="srch_form" type="hidden" name="sub_info_id" id="sub_info_id" value="" />
        <input form="srch_form" type="hidden" name="sub_info_value" id="sub_info_value" value="" />

        <input type="submit" name="action" value="показать" />
    </form>
<?php if (\bb\models\User::getCurrentUser()->isOwner() && $iDate): ?>
  <div style="background-color: #d5ddf9;">Сумма выручки по банку (только поступления, оффис: '<?= $place ?>): <strong><?php echo number_format(\bb\classes\Payment::getSumForDate($iDate, 'bank', $place), 2,',', ' ') ?></strong> руб. </div>
<?php endif; ?>
<?= $kassa->PrintKassaTable() ?>
<!--
    <table border="1" cellspacing="0" style="background-color: lightblue; position: absolute; left: 400px;">
        <tr>
            <th>Сумма залогов в кассе</th>
        </tr>
        <tr>
            <?php

            $col_amount = Collateral::getOstatok($place);
            Kassa::$_total_ostatok+=$col_amount;
            $total_money=Kassa::$_total_ostatok;

            echo '
            <td>'.number_format($col_amount, 2,',', ' ').'</td>
            ';
            ?>
        </tr>
    </table> -->

    <table border="1" cellspacing="0" style="clear:both;">
        <tr>
            <th style="width:60px;">уч.дата</th>
            <th style="width:80px;">операция<br />
                <select name="op_type" id="op_type" form="srch_form" onchange="document.getElementById('srch_form').submit();" style="width:80px;">
                    <option value="all" <?= DealRow::sel_d($op_type, 'all')?>>все</option>
                    <option value="first_rent" <?= DealRow::sel_d($op_type, 'first_rent')?>>Выдачи</option>
                    <option value="extention" <?= DealRow::sel_d($op_type, 'extention')?>>Продления</option>
                    <option value="takeaway_plan" <?= DealRow::sel_d($op_type, 'takeaway_plan')?>>Предоплата/бронь</option>
                    <option value="close" <?= DealRow::sel_d($op_type, 'close')?>>Возвраты</option>
                </select>
            </th>
            <th style="width:250px; position: relative;">Товар <div style="position: absolute; top: 0; right: 0; font-weight: normal; font-size: 12px;"><?php echo number_format($total_money, 2,',', ' ') ?></div></th>
            <th style="width:90px;"><span title="факт период по договору (было оплачено по)">Даты сделки</span></th>
            <th style="width:50px;">к опл.<br /></th>
            <th style="width:90px;"><span title="принял заказ, оплаты и касса, №чека">опл-о</span>
                <select name="payment_type" id="rent_payment_type" form="srch_form" onchange="document.getElementById('srch_form').submit();" style="width:50px;">
                    <option value="all" <?= DealRow::sel_d($payment_type, 'all')?>>все</option>
                    <option value="nal_no_cheque" <?= DealRow::sel_d($payment_type, 'nal_no_cheque')?>>Касса №2</option>
                    <option value="nal_cheque" <?= DealRow::sel_d($payment_type, 'nal_cheque')?>>Касса №1</option>
                    <option value="card" <?= DealRow::sel_d($payment_type, 'card')?>>карточка</option>
                    <option value="bank" <?= DealRow::sel_d($payment_type, 'bank')?>>банк</option>
                </select>
            </th>
            <th style="width:60px;">Офис<br />
                <select name="place" id="place_select" form="srch_form" style="display:inline-block; width:60px" onchange="document.getElementById('srch_form').submit();">
                    <?= Office::OptionsList($place, $user) ?>
                </select>
            </th>
            <th style="width:270px;">Адрес (доставки), ФИО, телефоны</th>
            <th>Доп. инфо</th>
            <th>Действия</th>
        </tr>


<?php
//Проверка входящей информации
//	echo "<div style='clear: both;'></div>Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";


foreach ($dls as $dl) {
    echo '
    <tr '.$dl->SubColorRowStyle().'>
        <td>'.date("d.m.y", $dl->acc_date_sub_deal).'<br>('.date("H:i", $dl->cr_time_sub).')<br> д№'.$dl->id_deal.'</td>
        <td>'.$dl->operation_print().'</td>
        <td style="position:relative;"><b>'.$dl->inv_n_print().$dl->FirstPlacePic().'</b><br>'.$dl->cat_dog_name.' '.$dl->model_name.' '.$dl->producer.'</td>
        <td>'.date("d.m.y", $dl->from_deal).'-'.date("d.m.y", $dl->to_deal).$dl->LastExtensionDatePrint().'</td>
        <td style="text-align:right;">'.number_format($dl->r_to_pay_sub, 2, ',', ' ').($dl->delivery_to_pay_sub>0 ? '<br /><span class="deliv_num">'.number_format($dl->delivery_to_pay_sub, 2, ',', ' ').'</span>' : '').'</td>
        <td style="text-align:right"><span style="font-size: 11px;">'.User::GetUserName($dl->acc_person_id).'</span><br>'.$dl->PrintPayment().'</td>
        <td>Оф'.$dl->place_sub_deal.'</td>
        <td>'.$dl->ClientPrint();
    //Base::varDamp($dl->payments);
    echo '</td>
        <td>'.User::GetUserName($dl->cr_who_sub_deal).'<br>'.$dl->PrintSubInfo().$dl->DeveloperInfo().'</td>
        <td>'.$dl->ActionPrint().'</td>
    </tr>
    ';

}

?>
    </table>
<?php
function get_post($var) {
    $db = Db::getInstance();
    $mysqli = $db->getConnection();
    return $mysqli->real_escape_string($_POST[$var]);
}


?>
