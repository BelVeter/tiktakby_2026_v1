<?php

use bb\Base;
use bb\models\User;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); //

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/bron.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php'); // включаем класс
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/Office.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/ModelWeb.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Model.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Category.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/SubRazdel.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Razdel.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Picture.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Permission.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Deal.php'); //
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/DohRash.php'); //


set_time_limit(30);
//------- proverka paroley
$in_level= array(0,5,7);

$mysqli=\bb\Db::getInstance()->getConnection();

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
?>
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

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<link href="/bb/assets/css/brons.css?v=2" rel="stylesheet" type="text/css" />
<style>
    .tovar_page_link{
        color: black;
        text-decoration: none;
        transition: all 0.3s;
    }
    .tovar_page_link:hover{
        color: #0f5132;
        border-bottom: solid #0f5132;
        border-bottom-width: 0.3px;
    }
</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
'.Base::getBarCodeReaderScript().'
</head>
<title>Брони.</title>
<body>

'.Base::officeLoggedInfo().'


<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/rent_zayavk.php">Заявки</a>
	<a class="div_item" href="/bb/obrabotka.php">Обработка</a>
	<a class="div_item" style="background-color: red;" href="/bb/rent_orders_arch.php">Удаленные брони</a>
</div><br />
	<form method="post" action="/bb/kr_baza_new.php" style="display:inline-block;">
			<input type="hidden" name="cat_id" value="2" /><input type="submit" value="КАРНАВАЛЫ" style="width:100px; height:35px; background-color:green; color:white" />
		</form>
<div class="row zv-row">
    <div class="col alert-danger h2 text-center" id="zv_div"></div>
</div>
';
require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show2.php');


?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<script>
history.pushState(null, null, location.href);
window.onpopstate = function(event) {
    history.go(1);
};

function radio_ch(id) {
    if (document.getElementById('radio_deliv_s_'+id).checked){
        //alert('samovivoz');
        document.getElementById('address_'+id).style.display="none";
    }
    else {
        //alert('delivery');
        document.getElementById('address_'+id).style.display="block";
    }
}

function pic_size(id) {

	if (document.getElementById('item_pic_'+id).style.width=="80px") {
		document.getElementById('item_pic_'+id).style.width="250px";
		document.getElementById('item_pic_'+id).style.height="250px"
	}
	else {
		document.getElementById('item_pic_'+id).style.width="80px";
		document.getElementById('item_pic_'+id).style.height="80px";
	}
}

function show_edit(id) {

	if (document.getElementById('edit_show_'+id).value=="подтвердить") {

	document.getElementById('edit_show_'+id).value="отмена";
	document.getElementById('save_podtv_'+id).style.display="inline-block";
	document.getElementById('info_div_'+id).style.display="inline-block";
	document.getElementById('br_valid_'+id).style.display="inline-block";
	}
	else {
	document.getElementById('edit_show_'+id).value="подтвердить";
	document.getElementById('save_podtv_'+id).style.display="none";
	document.getElementById('info_div_'+id).style.display="none";
	document.getElementById('br_valid_'+id).style.display="none";
	}
}

function office_move_show(id) {
    //alert('OK');
    if (document.getElementById('office_move_form_'+id).style.display=="none") {
        document.getElementById('office_move_form_'+id).style.display="inline-block";
    }
    else {
        document.getElementById('office_move_form_'+id).style.display="none";
    }
}



function rem_t_ch (id) {
	document.getElementById('info_div_'+id).style.display="inline-block";
	document.getElementById('rem_t_but_'+id).style.display="none";
	document.getElementById('cans_t_but_'+id).style.display="inline-block";
	document.getElementById('save_t_but_'+id).style.display="inline-block";
}

function cans_t (id) {
	document.getElementById('info_div_'+id).style.display="none";
	document.getElementById('rem_t_but_'+id).style.display="inline-block";
	document.getElementById('cans_t_but_'+id).style.display="none";
	document.getElementById('save_t_but_'+id).style.display="none";
}

function save_t (id) {
	document.getElementById('save_t_but_'+id).value="сохранить изменения";
	document.getElementById('save_t_but_'+id).style.display="none";
	document.getElementById('cans_t_but_'+id).style.display="none";
	return true;

}

function br_del_ch (id) {
	document.getElementById('info_div_'+id).style.display="inline-block";
    if (document.getElementById('br_2_t').value=="bron" || document.getElementById('br_2_t').value=="deliv") {
        document.getElementById('br_del_span_' + id).style.display = "inline";
    }
	document.getElementById('rem_t_but_'+id).style.display="none";
	document.getElementById('cans_t_but_'+id).style.display="inline-block";
	document.getElementById('save_t_but_'+id).style.display="inline-block";
	var tmp = document.getElementById('info_'+id).innerHTML;
	var d = new Date();
	//tmp+="\n"+d.getDate()+"."+(d.getMonth()+1)+"."+d.getFullYear()+"("+d.getHours()+":"+d.getMinutes()+")"+" "+document.getElementById('cur_user_name').value+" ткнул кнопку с тележкой";
	//document.getElementById('info_'+id).innerHTML=tmp;
}

function cans_val (id) {
	document.getElementById('br_valid_'+id).style.display="none";
	document.getElementById('cans_val_but_'+id).style.display="none";
	document.getElementById('save_val_but_'+id).style.display="none";
}

function save_val (id) {
	document.getElementById('save_val_but_'+id).value="сохранить изменения";
	document.getElementById('save_val_but_'+id).style.display="none";
	document.getElementById('cans_val_but_'+id).style.display="none";
	return true;

}

function pl_show (id) {
	document.getElementById('place_status_'+id).style.display="inline-block";
	document.getElementById('cans_t_but_pl_'+id).style.display="inline-block";
	document.getElementById('save_t_but_pl_'+id).style.display="inline-block";
	document.getElementById('ch_a_pl_'+id).style.display="none";
}

function cans_t_pl (id) {
	document.getElementById('place_status_'+id).style.display="none";
	document.getElementById('cans_t_but_pl_'+id).style.display="none";
	document.getElementById('save_t_but_pl_'+id).style.display="none";
	document.getElementById('ch_a_pl_'+id).style.display="block";
}

function save_t_pl (id) {
	document.getElementById('save_t_but_pl_'+id).value="сохранить изменения";
	document.getElementById('save_t_but_pl_'+id).style.display="none";
	document.getElementById('cans_t_but_pl_'+id).style.display="none";
	return true;

}

function val_show (id) {
	document.getElementById('br_valid_'+id).style.display="inline-block";
	document.getElementById('cans_val_but_'+id).style.display="inline-block";
	document.getElementById('save_val_but_'+id).style.display="inline-block";
	document.getElementById('ch_a_pl_'+id).style.display="none";
}

function resp_show (id) {
	document.getElementById('rem_resp_'+id).style.display="inline-block";
	document.getElementById('cans_resp_but_'+id).style.display="inline-block";
	document.getElementById('save_resp_but_'+id).style.display="inline-block";
	document.getElementById('ch_a_pl_'+id).style.display="none";
}

function cans_resp (id) {
	document.getElementById('rem_resp_'+id).style.display="none";
	document.getElementById('cans_resp_but_'+id).style.display="none";
	document.getElementById('save_resp_but_'+id).style.display="none";
}

function save_resp (id) {
	document.getElementById('save_resp_but_'+id).value="сохранить изменения";
	document.getElementById('save_resp_but_'+id).style.display="none";
	document.getElementById('cans_resp_but_'+id).style.display="none";
	return true;

}


function br_ch_ch () {
    if (document.getElementById('br_2_t').value=="bron" || document.getElementById('br_2_t').value=="deliv") {
        document.getElementById('cust_info').style.display="inline-block";
        document.getElementById('rem_type_new').style.display="none";
        document.getElementById('place_status_new').style.display="none";

        if (document.getElementById('br_2_t').value=="deliv") {
            document.getElementById('new_address').style.display="inline-block";
        }
        else {
            document.getElementById('new_address').style.display="none";
        }

    }

	if (document.getElementById('br_2_t').value=="remont") {
        document.getElementById('cust_info').style.display="none";
        document.getElementById('new_address').style.display="none";

		document.getElementById('place_status_new').style.display="inline-block";
		document.getElementById('rem_type_new').style.display="inline-block";
		//document.getElementById('rem_resp_new').style.display="inline-block";
	}

    if (document.getElementById('br_2_t').value=="out" || document.getElementById('br_2_t').value=="sell") {
		document.getElementById('place_status_new').style.display="none";
		document.getElementById('rem_type_new').style.display="none";
		//document.getElementById('rem_resp_new').style.display="none";

        document.getElementById('cust_info').style.display="none";
        document.getElementById('new_address').style.display="none";
	}

    if (document.getElementById('br_2_t').value=="") {
        document.getElementById('new_br_main').style.display="none";
    }
    else {
        document.getElementById('new_br_main').style.display="inline-block";
    }

}

function new_order_f() {

	if (document.getElementById('new_form_row').style.display=="") {
		document.getElementById('new_form_row').style.display="none";
		document.getElementById('new_order_but').value="новая бронь";
	}
	else {
		document.getElementById('new_form_row').style.display="";
		document.getElementById('new_order_but').value="отмена";
	}

}


function new_br() {
    rez=true;
	if (document.getElementById('br_2_t').value=="") {
		rez=false;
	    alert ('Введите тип брони!');

	}
    if (document.getElementById('inv_n_new').value=="") {
        rez=false;
        alert ('Не выбран инвентарный номер!');

    }

	return rez;
}


function br_br(type2) {
	document.getElementById('filter_type2').value=type2;
	document.getElementById('br_filter').submit();

}



function reload() {location = '/bb/index.php'};

function appr($form_id) {
    var $result_div_id = "k_container_"+inv_n;
    var $form = $("#"+$form_id);
    $("#kb_button_"+inv_n).hide('slow');
    $("#"+$result_div_id).append('<img src="/bb/w2.gif" class="kb_w2"/>');
    $.ajax(
        {
            type: $form.attr('method'),
            url: "/bb/kb_ajax_eng.php",
            data: $form.serialize(),
        }
    ).done(function (data) {
        var rez=JSON.parse(data);
        //alert (rez);
        $("#"+$result_div_id).empty();
        $("#"+$result_div_id).append(rez.result);
        $("#active_inv_n").val(inv_n);
    });

}

</script>

<?php

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$type1='strong'; // потом убрать
$type2='bron';
$action='';
$vidan=0;//показывает кнопку изменения
$alert='';
$item_inv_n='';
$to_sam=0;
$office='all';
$place_status='';
$rem_type='';
$rem_resp='';
$uzhe_vidan=0;
$rem_person_id='all';
$br_2_t='';
$karn_s='all';

//для формы перемещения товара
$current_office = \bb\models\Office::getCurrentOffice();
$ofs = \bb\models\Office::getAllOffices();

//Base::PostCheckVarDumpEcho();

foreach ($_POST as $key => $value) {
		$$key = get_post($key);
	}
//echo  $karn_filter;
// создаем перечень пользователей
	$rd_lp = "SELECT * FROM logpass";
	$result_lp = $mysqli->query($rd_lp);
	if (!$result_lp) {die('Сбой при доступе к базе данных: '.$rd_lp.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

	$lp_list[0]='';
	while ($lp_l=$result_lp->fetch_assoc()) {
		$lp_list[$lp_l['logpass_id']]=$lp_l['lp_fio'];
	}

//Имя текущего пользователия
echo '<input type="hidden" id="cur_user_name" value="'.$lp_list[$_SESSION['user_id']].'" />';

// создаем перечень офисов
	$rd_of = "SELECT * FROM offices WHERE `type`='office'";
	$result_of = $mysqli->query($rd_of);
	if (!$result_of) {die('Сбой при доступе к базе данных: '.$rd_of.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

	$off_pic[0]='';
	while ($t_of=$result_of->fetch_assoc()) {
		$off_pic[$t_of['number']]=$t_of['pic_addr'];
	}




//распределение ремонтов    appr_id = то, на кого распределяют (id юзера)  + type2=remont
$remont_users_count = array(
    '4'=>0,//Андрей
    '22'=>0,//Катя
    '24'=>0,//Марина
    '26'=>0,//Юля
);

$remont_users_new = array();

$query_r="SELECT `order_id` FROM `rent_orders` WHERE type2='remont' AND `appr_id`=''";
$result_r = $mysqli->query($query_r);
if (!$result_r) {die('Сбой при доступе к базе данных: '.$query_r.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

if ($result_r->num_rows>0) {

    //запрос информации о распределенных ремонтах
    $query_r_id = "
            SELECT COUNT(`order_id`), `appr_id` FROM `rent_orders`
            WHERE type2='remont' AND `appr_id`!=0
            GROUP BY `appr_id`
            ";
    $result_rem_id = $mysqli->query($query_r_id);
    if (!$result_rem_id) die( 'Сбой при доступе к базе данных: ' . $query_r_id . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

    //assigning number of remont to array
    while ($rem_id=$result_rem_id->fetch_assoc()) {
        if (!array_key_exists($rem_id['appr_id'], $remont_users_count)) continue; //if not active user - skip
        else $remont_users_count[$rem_id['appr_id']]+=$rem_id['COUNT(`order_id`)'];
    }
    //делим неподеленные ремонты
    while ($rem_none=$result_r->fetch_assoc()) {
        $min_user_id=array_keys($remont_users_count, min($remont_users_count))[0];  # array('$remont_users_count')

        $remont_users_count[$min_user_id]+=1;
        $remont_users_new[$min_user_id][]=$rem_none['order_id'];
    }

    foreach ($remont_users_new as $key=>$value) {
        $query_upd = "UPDATE rent_orders SET `appr_id`='$key' WHERE order_id IN (". implode(',', array_map('intval', $value)).") ";
        $upd_result = $mysqli->query($query_upd);
        if (!$upd_result) die( 'Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
}


if (isset($_POST['action'])) {

	switch ($action) {

    case 'to_sell':

      $brId = $_POST['brid_context'];
      $brC = new \bb\classes\bron();
      $brC->br_load($brId);
      $brC->type2='sell';
      $brC->info2.='<p class="bron_hist_unit"><span>'.date("d").' '.Base::getShortMonth(date("m")).'<sup>'.date("H:i").'</sup> '.User::getCurrentUser()->user_name.':</span> отправлен на продажу </p>';
      $brC->update();

      break;

    case 'tovar_sell':
      $today = new DateTime();
      $invN=$_POST['invn-m'];
      $brId=$_POST['brid-m'];
      $ofNum = $_POST['office-m'];
        if ($ofNum<1) $ofNum = \bb\models\Office::getCurrentOffice()->getIdOffice();
      $amount = $_POST['amount-m'];
      $kassa = $_POST['kassa-m'];
      $dop_info = $_POST['dop_info-m'];

      $br = new \bb\classes\bron();
      $br->br_load($brId);

      \bb\classes\Deal::sellTovar($today, $invN,$amount,$ofNum, $kassa, $dop_info);

      $br->arch_copy();

      $br->del_br();

      unset($br);


      break;

    case 'office_move':
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

                $shift_to=Base::GetPost('shift_to');

                if ($shift_to=='move_canсel') {
                    $tov->moveCancel();
                }
                elseif ($shift_to=='move_accept') {
                    $tov->moveAccept();
                }
                elseif ($shift_to>0) {
                    $tov->moveTo($shift_to);
                }

            break;

		case 'сохранить':
			$bron = new \bb\classes\bron();

			$bron->inv_n=$inv_n;
			$bron->stirka();

			$bron->type2=$br_2_t;
			$bron->info=$info;
			$bron->order_date=strtotime(date("Y-m-d"));//сегодня
			$bron->validity=strtotime($br_valid);
			if (isset($family)) {
			    $bron->family=$family;
			    $bron->name=$name;
			    $bron->otch=$otch;
			    $bron->phone=Base::getNumbersOnly($phone);
			    $bron->address=$address;
            }



			if ($br_2_t=='remont') {
				$bron->place_status=$place_status;
				$bron->rem_type=$rem_type;
				$bron->appr_id=$rem_resp;
			}
			if ($bron->type2=='remont' && $bron->in_stirka==1) {
				$bron->del_br_id($bron->stir_id);

			}

			if (isset($_POST['kidsiki']) && $_POST['kidsiki'] == 'yes') {
			    $bron->client_id=1;
            }
			//var_dump($bron);
			$bron->bron_insert();

			if ($bron->failure==1) {
				echo 'Ошибка !!!'.$bron->alert;
			}
			else {
				echo 'Бронь внесена успешно.';
			}
			$type2=$bron->type2;

		break;

		case 'недозвон':
			$br_upd= new \bb\classes\bron();
			$br_upd->br_load($order_id);
			$br_upd->info2.='<p class="bron_hist_unit"><span>'.date("d").' '.Base::getShortMonth(date("m")).'<sup>'.date("H:i").'</sup> '.User::getCurrentUser()->user_name.':</span> недозвон. </p>';
			$br_upd->update();
			unset($br_upd);

			break;

		case 'Отправить на выбытие':
			$br_upd= new \bb\classes\bron();
			$br_upd->br_load($order_id);
			if ($br_upd->ch_time!=$last_ch_time) {die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');}
			$br_upd->ch_who_id=$_SESSION['user_id'];
			$br_upd->ch_time=time();

			$br_upd->type2='out';
			$br_upd->info.='<br />'.date("d.m.y - H:i", time()).' отправлено на выбытие - '.$lp_list[$br_upd->user_id];

			$br_upd->update();
			unset($br_upd);

			break;

		case 'Вернуть в ремонт':
			$br_upd= new \bb\classes\bron();
			$br_upd->br_load($order_id);

			if ($br_upd->ch_time!=$last_ch_time) {die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');}
			$br_upd->ch_who_id=$_SESSION['user_id'];
			$br_upd->ch_time=time();

			$br_upd->type2='remont';
			$br_upd->info.='<br />'.date("d.m.y - H:i", time()).' возвращено с выбытия - '.$lp_list[$br_upd->user_id];

			$br_upd->update();

			unset($br_upd);

			break;

		case 'Исполнено':
			$br_upd= new \bb\classes\bron();
			$br_upd->br_load($order_id);
			if ($br_upd->ch_time!=$last_ch_time) {die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');}
			$br_upd->item_load();

			if ($br_upd->item_status=='bron') {
				//меняем статус на свободно
				$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent' WHERE item_inv_n='$br_upd->inv_n'";
				$result_upd = $mysqli->query($query_upd);
				if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			}
			else {
				echo ('Статус товара не равен bron!');
			}

			$br_upd->arch_copy();
			$br_upd->del_br();

			break;

        case 'ИЗВЕСТИТЬ КЛИЕНТА':

            $br_upd= new \bb\classes\bron();
            $br_upd->br_load($order_id);

            $br_upd->info2.='<p class="bron_hist_unit"><span>'.date("d").' '.Base::getShortMonth(date("m")).'<sup>'.date("H:i").'</sup> '.User::getCurrentUser()->user_name.':</span> клиент извещен о перемещении товара </p>';
            $br_upd->client_id=0;

            $br_upd->update();
            unset($br_upd);
            break;

		case 'сохранить изменения':
			$change_add='';

		    $br_upd= new \bb\classes\bron();
			$br_upd->br_load($order_id);
			if ($br_upd->ch_time!=$last_ch_time) {die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');}

			$br_upd->place_status=$place_status;
			//$br_upd->info=$info;


            $new_validity=strtotime($br_valid);
            if ($br_upd->type2=='bron' || $br_upd->type2=='deliv') {//form change history for brons (invl deliv...ery)

                if ($br_upd->validity != $new_validity) {
                    $change_add .= ' (срок действия: ' . Base::dateKrFormat($br_upd->validity) . '-->' . Base::dateKrFormat($new_validity) . ')';
                }

                if ($br_upd->type2 != $radio_deliv) {
                    if ($br_upd->type2 == 'bron') {//was bron, now - deliv
                        $change_add .= ' (самовывоз --> доставка)';
                    } else {//was deliv, now - bron
                        $change_add .= ' (доставка --> самовывоз)';
                    }
                }

                if ($br_upd->type2 == 'deliv' && $radio_deliv == 'deliv') {
                    if ($br_upd->address != $address) {
                        $change_add .= ' (адрес доставки: ' . $br_upd->address . ' --> ' . $address . ')';
                    }
                }

            }

            if ($br_upd->isBron()) {
                $br_upd->info2.='<p class="bron_hist_unit"><span>'.date("d").' '.Base::getShortMonth(date("m")).'<sup>'.date("H:i").'</sup> '.User::getCurrentUser()->user_name.':</span> '.$info.$change_add.'. </p>';
            }
            if (!$br_upd->isBron()) {
                $br_upd->info=$info;
            }

			$br_upd->rem_type=$rem_type;
			$br_upd->validity=strtotime($br_valid);

			if ($br_upd->type2=='remont') {
				$br_upd->appr_id=$rem_resp;
			}


			$br_upd->ch_who_id=$_SESSION['user_id'];
			$br_upd->ch_time=time();

			if ($br_upd->type2=='bron' || $br_upd->type2=='deliv') {
				if ($radio_deliv=='bron') {
					$br_upd->type2='bron';
				}
				else {//radio_deliv=deliv
					$br_upd->type2='deliv';
					$br_upd->address=$address;
				}
			}

			$br_upd->update();
			unset($br_upd);

			break;

		case 'подтвердить':
			$br_upd= new \bb\classes\bron();
			$br_upd->br_load($order_id);
			if ($br_upd->ch_time!=$last_ch_time) {die('Бронь была изменена кем-то другим. Повторите операцию (только на этот раз не тормозите :)).');}

			$br_upd->ch_who_id=$_SESSION['user_id'];
			$br_upd->ch_time=time();
            $br_upd->info2.='<p class="bron_hist_unit"><span>'.date("d").' '.Base::getShortMonth(date("m")).'<sup>'.date("H:i").'</sup> '.User::getCurrentUser()->user_name.':</span> подтверждено '.$info.' </p>';
				//при подтверждении 1-й раз - проставляется отметка подверждающего
				if ($br_upd->appr_id<1) {
					$br_upd->appr_id=$_SESSION['user_id'];
					$br_upd->appr_time=time();
				}


			$br_upd->update();
			unset($br_upd);

			break;

		case 'Списать товар':
			$tov = new \bb\classes\tovar();
			$br = new \bb\classes\bron();

			$tov->item_load($del_inv_n);
			$br->br_load($order_id);

			$tov->item_del_info='Товар списан через страницу броней - выбытие.';

			$tov->out_status='bron_delete';

			$tov->del_item();
			$br->arch_copy();

			$br->del_br();

			echo $tov->return_info;

			unset($tov);
			unset($br);
			break;

		case 'удалить':
			//!!! внимание таблицы не блокируются - иначе все виснит. нужно разобраться с блокировками


			$br = new \bb\classes\bron();
			$br->br_load($order_id);

			//выбираем инфо по конкретному товару (inv_n);
			$q_tov = "SELECT * FROM tovar_rent_items WHERE item_inv_n='$br->inv_n'";
			$result_tov = $mysqli->query($q_tov);
			if (!$result_tov) {die('Сбой при доступе к базе данных: '.$q_tov.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
			$i_tov = $result_tov->fetch_assoc();
			if ($result_tov->num_rows!==1) {
				die('при проверке товара по инв. номеру: либо товар отсутствует, либо кол-во товаров больше 1');
			}
			else {
				if ($i_tov['status']=='bron') {//если бронь - меняем на свободно
					$query_upd = "UPDATE tovar_rent_items SET `status`='to_rent' WHERE item_inv_n='$br->inv_n'";
					$result_upd = $mysqli->query($query_upd);
					if (!$result_upd) {die('Сбой при доступе к базе данных: '.$query_upd.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
				}
			}

			//копирование брони в архив
			$br->arch_copy();

			//удаление брони
			$br->del_br();

			//разблокируем таблицы
			$query = "UNLOCK TABLES";
			$result = $mysqli->query($query);
			if (!$result) {die('Сбой при блокировке таблиц MYSQL: '.$query.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
		break;
	}
}

$type2s=$type2;
if ($type2=='bron' || $type2=='deliv') {
	$type2s="bron', 'deliv";
}


$srch_rem="";
if ($type2=='remont' && $rem_person_id!='all'){
    $srch_rem=" AND `appr_id`='$rem_person_id'";
}

if ($type2!='bron' ){
    $dop_ord=", cat_id ";
}
else{
    $dop_ord='';
}

//основной запрос
//$query_or = "SELECT * FROM rent_orders WHERE type2 IN ('$type2s')$srch_rem ORDER BY type2 DESC, cr_time";

$query_or = "SELECT rent_orders.*, tovar_rent_items.state, tovar_rent_items.model_id FROM rent_orders
            LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n
            WHERE type2 IN ('$type2s')$srch_rem ORDER BY type2".$dop_ord." DESC, cr_time";

//echo $query_or;
$result_or = $mysqli->query($query_or);
if (!$result_or) {die('Сбой при доступе к базе данных: '.$query_or.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
$type2_num=$result_or->num_rows;

//для расчета необработанных
	$query_or_new = "SELECT * FROM rent_orders LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n WHERE (rent_orders.type2='bron' OR rent_orders.type2='deliv') AND rent_orders.appr_id<1 AND tovar_rent_items.item_place='$office'";
		if ($office=='all') {
			$query_or_new = "SELECT * FROM rent_orders WHERE (type2='bron' OR type2='deliv') AND appr_id<1";
		}
	$result_or_new = $mysqli->query($query_or_new);
	if (!$result_or_new) {die('Сбой при доступе к базе данных: '.$query_or_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	$br_new=$result_or_new->num_rows;


	$query_or_new = "SELECT * FROM rent_orders LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n WHERE rent_orders.type2='deliv' AND rent_orders.appr_id<1 AND tovar_rent_items.item_place='$office'";
	if ($office=='all') {
		$query_or_new = "SELECT * FROM rent_orders WHERE type2='deliv' AND appr_id<1";
	}
	$result_or_new = $mysqli->query($query_or_new);
	if (!$result_or_new) {die('Сбой при доступе к базе данных: '.$query_or_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	$deliv_new=$result_or_new->num_rows;


	$query_or_new = "SELECT * FROM rent_orders LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n WHERE rent_orders.type2='remont' AND tovar_rent_items.item_place='$office'";
	if ($office=='all') {
		$query_or_new = "SELECT * FROM rent_orders WHERE type2='remont'";
	}
	$result_or_new = $mysqli->query($query_or_new);
	if (!$result_or_new) {die('Сбой при доступе к базе данных: '.$query_or_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	$remont_new=$result_or_new->num_rows;

	$query_or_new = "SELECT * FROM rent_orders LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n WHERE rent_orders.type2='out' AND tovar_rent_items.item_place='$office'";
	if ($office=='all') {
		$query_or_new = "SELECT * FROM rent_orders WHERE type2='out'";
	}
	$result_or_new = $mysqli->query($query_or_new);
	if (!$result_or_new) {die('Сбой при доступе к базе данных: '.$query_or_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
	$out_new=$result_or_new->num_rows;

  $query_or_new = "SELECT * FROM rent_orders LEFT JOIN tovar_rent_items ON rent_orders.inv_n=tovar_rent_items.item_inv_n WHERE rent_orders.type2='out' AND tovar_rent_items.item_place='$office'";
  if ($office=='all') {
    $query_or_new = "SELECT * FROM rent_orders WHERE type2='sell'";
  }
  $result_or_new = $mysqli->query($query_or_new);
  if (!$result_or_new) {die('Сбой при доступе к базе данных: '.$query_or_new.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
  $sell_new=$result_or_new->num_rows;

//перечень инв. номеров для новой формы
$query_free = "SELECT item_inv_n FROM tovar_rent_items WHERE `status`='to_rent' OR (`status`='t_bron' AND br_time<'".time()."')";
$result_free = $mysqli->query($query_free);
if (!$result_free) {die('Сбой при доступе к базе данных: '.$query_free.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

$free_ns='';
while ($free_inv = $result_free->fetch_assoc()) {
	$free_ns.='<option value="'.$free_inv['item_inv_n'].'" '.sel_d($item_inv_n, $free_inv['item_inv_n']).'>'.$free_inv['item_inv_n'].'</option>';
}

// Фильтр по карнавалам
$karn_filter='<select name="karn_s" id="karn_s" form="br_filter" onchange="document.getElementById(\'br_filter\').submit();" style="width:80px;">
				<option value="all" '.sel_d($karn_s, 'all').'>все товары</option>
				<option value="karn" '.sel_d($karn_s, 'karn').'>карнавалы</option>
				<option value="non_karn" '.sel_d($karn_s, 'non_karn').'>не карнавалы</option>
			</select>';


//фильтры по офисам

$off_filter='<select name="office" id="office_select" form="br_filter" onchange="document.getElementById(\'br_filter\').submit();" style="width:80px;">
				<option value="all" '.sel_d($office, 'all').'>все офисы</option>
				';

$q_of = "SELECT * FROM offices WHERE `active`='1'";
$result_of = $mysqli->query($q_of);
if (!$result_of) {die('Сбой при доступе к базе данных: '.$q_of.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}

while ($offs = $result_of->fetch_assoc()) {
	$off_filter.='<option value="'.$offs['number'].'" '.sel_d($office, $offs['number']).'>'.$offs['name'].'</option>';
}

$off_filter.='</select>';

//echo '<pre>';
//var_dump($remont_users_count);
//echo '<pre>';

echo '
<!--Форма для работы ссылок -->
<form name="br_filter" id="br_filter" method="post" action="rent_orders.php" >
		<input type="hidden" name="type2" id="filter_type2" value="'.$type2.'" />
</form>


<a href="#" class="'.(($type2=='bron' || $type2=='deliv') ? 'br_br_act' : 'br_br').'" onclick="br_br(\'bron\'); return false;">Бронь <sup style="color:red;">+'.$br_new.'</sup></a>
<a href="#" class="'.($type2=='remont' ? 'br_zayavk_act' : 'br_zayavk').'" onclick="br_br(\'remont\'); return false;">Ремонт<sup style="color:red;">'.$remont_new.'</sup></a>
<a href="#" class="'.($type2=='out' ? 'br_zayavk_act' : 'br_zayavk').'" onclick="br_br(\'out\'); return false;" >Выбытие<sup style="color:red;">'.$out_new.'</sup></a>
<a href="#" class="'.($type2=='sell' ? 'br_zayavk_act' : 'br_zayavk').'" onclick="br_br(\'sell\'); return false;" >Продажа<sup style="color:red;">'.$sell_new.'</sup></a>
<table border="1" cellspacing="0">
  <tr style="background-color:#afcb82;">
      <!--<th style="width:81px; text-align:center;">Дата/№</th>-->
	  <th style="width:81px;">фото</th>
	  <th style="width:350px; text-align:center;">Товар'.$off_filter.$karn_filter.'</th>
      <th style="width:880px; text-align:center;">история операций</th>
	  <th style="width:120px; text-align:center;">';
		switch ($type2) {
			case 'remont':
				echo 'срок исполнения';
			break;

			default:
				echo 'тел.\дата действия';
			break;
		}

		echo '</th>
      <!--<th style="width:90px; text-align:center;">'.($type2=='remont' ? 'отв.лицо' : 'созд/подтв').' ';
              if($type2=='remont') {
        //          echo '
        //            <select name="rem_person_id" id="rem_person_id" form="br_filter" onchange="document.getElementById(\'br_filter\').submit();">
        //                <option '.sel_d('all', $rem_person_id).' value="all">все</option>
        //                <option '.sel_d('4', $rem_person_id).' value="4">Андрей</option>
        //                <option '.sel_d('22', $rem_person_id).' value="22">Катя</option>
        //                <option '.sel_d('24', $rem_person_id).' value="24">Марина</option>
        //                <option '.sel_d('26', $rem_person_id).' value="26">Юля</option>
        //			</select>
        //          ';
              }
      echo '
      </th>-->
      <th style="text-align:center;"><div style="position:relative"><input type="button" style="position:absolute; top:-65px; left:100px; height:50px; width:100px;" value="'.($action=='бронь' ? 'отмена' : 'новая бронь').'" id="new_order_but" onclick="new_order_f(); return false;"></div>
		действия</th>
  </tr>


<tbody '.($action=='бронь' ? '' : 'style="display:none;"').' id="new_form_row">
	<tr>
      <!--<td>сейчас</td>-->
      <td></td>
      <td><select name="inv_n" id="inv_n_new" form="new_order"><option value="">не указан</option>'.$free_ns.'</select><br />
      	Тип брони:<select name="br_2_t" id="br_2_t" form="new_order" onchange="br_ch_ch();">
      			<option value="">не указан</option>
      			<option '.sel_d($br_2_t, 'bron').' value="bron">самовывоз</option>
				<option '.sel_d($br_2_t, 'deliv').' value="deliv">доставка</option>
				<option '.sel_d($br_2_t, 'remont').' value="remont">ремонт</option>
				<option '.sel_d($br_2_t, 'out').' value="out">выбытие</option>
				';
        if (User::getCurrentUser()->hasPermission(1)) {
          echo '<option '.sel_d($br_2_t, 'sell').' value="sell">продажа</option>';
        }
        echo '
      		</select><br />
			<select name="place_status" form="new_order" id="place_status_new" style="display:none;">
				<option value="off">на офисе</option>
				<option value="rem">у ремонтера</option>
			</select>
      	</td>
	  <td>
	  <div id="new_br_main" style="display: none">
            <p style="margin: 0" id="cust_info">
              Фамилия: <input type="text" form="new_order" name="family">
              Имя: <input type="text" form="new_order" name="name">
              Отчество: <input type="text" form="new_order" name="otch">
              <br>
              Телефон: +375-<input type="text" form="new_order" name="phone">
            </p>
	        <p style="margin: 0" id="new_address">Адрес доставки: <input type="text" form="new_order" name="address"></p>

	        <p style="margin: 0;">
	        <select name="rem_type" form="new_order" id="rem_type_new" style="width:80px; display:none;">
                    <option value="">выберите тип ремонта</option>
                    <option value="stir">сложная стирка</option>
                    <option value="meh">механ/электр</option>
                    <option value="tex">текстиль</option>
                    <option value="oth">иное</option>
             </select>
          </p>
	      Доп. информация:<br>
          <textarea rows="3" cols="100" name="info" id="info_new" form="new_order"></textarea><br>
          <label for="kidsiki">Кидсики?</label>
          <input type="checkbox" name="kidsiki" id="kidsiki" value="yes" form="new_order">
          <div style="position:relative; z-index:2; background-color:#FFF;">Дата действия:<input type="date" name="br_valid" id="br_valid_new" form="new_order" value="'.date("Y-m-d", time()).'"></div>
        </div>
	  </td>
	  <td></td>
      <!--<td> <select style="width:80px; display:none;" name="rem_resp" form="new_order" id="rem_resp_new">
				'.user_select('0').'
			</select>
			<input type="hidden" name="user_id" id="user_id_new" value="'.$_SESSION['user_id'].'" form="new_order">'.$_SESSION['user_fio'].'</td>-->
      <td><form name="new_order" id="new_order" action="rent_orders.php" method="post">
			<input type="submit" name="action" value="сохранить" onclick="return new_br();">
		</form>
		</td>
    </tr>
	</tbody>';

//для того, чтобы как хочет Кристина при переходе на нов. бронь не показывалось ничего более.
		if ($action=='бронь') {
			die('</table>');
		}

if ($type2=='remont') {
	$show1='style="display:none;"';
	$show2='';
	$rem_show='';
}
else {
	$show1='';
	$show2='style="display:none;"';
	$rem_show='display:none;';
}


while ($ord = $result_or->fetch_assoc()) {
	$br_line = new \bb\classes\bron();
	$br_line->br_line($ord);
	$br_line->web_load();

	if ($office!='all' && $office!=$br_line->item_place) {//если не нужно показывать все, либо если не совпадает с фильтром по офисам, то пропускаем печать данных
		continue;
	}
    if ($karn_s!='all') {//если не нужно показывать все, либо если не совпадает с фильтром по офисам, то пропускаем печать данных
        if (substr($br_line->inv_n, 0, 3)=='702' || substr($br_line->inv_n, 0, 3)=='761') {
            $karn=1;
        }
        else $karn=0;
        //echo $karn;
        if ($karn_s=='karn' && $karn!=1) {//if show karnaval and item is not karnaval
            continue;
        }
        if ($karn_s!='karn' && $karn==1) {//if show not karnaval and item is karnaval
            continue;
        }

    }

	($br_line->item_status=='rented_out' || $br_line->item_status=='to_deliver') ? $uzhe_vidan=1 : $uzhe_vidan=0;

	echo '
	<tr class="main-row" data-brid="'.$br_line->order_id.'" data-invn="'.$br_line->inv_n.'">
		<!--<td>'.date("d.m.y", $br_line->order_date).'<br /><i>('.date("H:i", $br_line->cr_time).')</i><br /> №'.$br_line->order_id.' </td>-->
		<td style="text-align: center;"><img src="'.$br_line->small_pic.'" style="width:80px; heigth:80px;" id="item_pic_'.$br_line->order_id.'" onclick="pic_size(\''.$br_line->order_id.'\')" />
		    <br><strong>'.$br_line->inv_n.'</strong>
		</td>
		<td '.($uzhe_vidan==1 ? 'style="background-color:yellow;"' : '').'>
			'.($br_line->in_stirka=='1' ? '<img alt="В стирке" title="в стирке" style="width:25px; height:25px; float:right;" src="/bb/clean.png"/>' : '').' ';
 if ($type2=='bron'){
   $mw = \bb\classes\ModelWeb::getByModelId($ord['model_id']);
   if ($mw) {
     echo '<a class="tovar_page_link" href="'.$mw->getUrlPageAddress().'" target="_blank">';
     $aClose='</a>';
   }
   else{
     $aClose='';
   }
 }
 else{
   $aClose='';
 }

			echo '<span class="'.(User::getCurrentUser()->hasPermission(1) ? 'tov_text' : '').'"> '.$br_line->cat_dog_name.' '.$br_line->producer.': '.$br_line->model.'. Цвет: "'.$br_line->br_color.'" </span><br /><strong> '.($br_line->isKidsiki() ? '<img src="/bb/kidsiki.jpg">' : '').($br_line->isSpelenok() ? '<img src="/bb/spelenok.jpg">' : '').' '.($br_line->client_id==1 ? '<img src="/bb/kidzakaz.jpg">' : '').'</strong>';
  echo $aClose;
  echo '
				<input type="button" id="cans_t_but_pl_'.$br_line->order_id.'" style="background-image:url(/bb/cans.png); width:33px; height:33px; float:right; display:none;" value="" onclick="cans_t_pl(\''.$br_line->order_id.'\');" />
				<input type="submit" name="action" id="save_t_but_pl_'.$br_line->order_id.'" style="background-image:url(/bb/save.png); width:33px; height:33px; float:right; display:none;" value="" form="order_'.$br_line->order_id.'" onclick="return save_t_pl(\''.$br_line->order_id.'\');" />
			';
		if ($br_line->type2=='remont') {
			echo '<a href="#" onclick="pl_show('.$br_line->order_id.'); return false;" id="ch_a_pl_'.$br_line->order_id.'"><img style="width:25px; height:25px; float:right;" src="'.$off_pic[$br_line->item_place].'" title="Офис №'.$br_line->item_place.'"/></a>';
		}
		else {
		    echo '<a href="#" onclick="office_move_show(\''.$br_line->order_id.'\'); return false;">';
            if ($br_line->item_to_move>0) {
                echo '<img style="width:25px; height:25px; float:right;" src="'.$off_pic[$br_line->item_to_move].'" title="Офис №'.$br_line->item_to_move.'"/>';
                echo '<img style="float: right; position: relative; width: 25px; height: 25px;" src="/bb/arrow.jpg">';
            }
			echo '<img style="width:25px; height:25px; float:right;" src="'.$off_pic[$br_line->item_place].'" title="Офис №'.$br_line->item_place.'"/>';
            echo '</a>';

            //форма перемещения товара

            echo '
        <form method="post" action="/bb/rent_orders.php" id="office_move_form_'.$br_line->order_id.'" class="form-inline" style="display: none; margin: 10px 0; float: right; clear: both;">
            <input type="hidden" name="item_inv_n" value="'.$br_line->inv_n.'">
            <input type="hidden" name="type2" value="'.$type2.'" />
            <input type="hidden" name="karn_s" value="'.$karn_s.'" />
            <input type="hidden" name="office" value="'.$office.'" />
            <input type="hidden" name="action" value="office_move" />


            <select name="shift_to" style="color: #17a2b8; font-size: 18px;" onchange="if (confirm(\'Вы уверены, что хотите переместить товар?\')) {this.form.submit();} else {this.options[0].selected=true}">';

            if ($br_line->item_to_move>0) {//=isVPuti
                echo '<option>в пути: ' . \bb\models\Office::getOfficeNameByNumber($br_line->item_place) . '-->' . \bb\models\Office::getOfficeNameByNumber($br_line->item_to_move) . '</option>';
                echo '<option value="move_canсel">отменить статус --в пути--</option>';
                if ($br_line->item_to_move == $current_office->number) {
                    echo '<option value="move_accept">принять товар на ' . \bb\models\Office::getOfficeNameByNumber($current_office->number) . '</option>';
                }
            } else {//not v puti = at the office
                echo '<option>Переместить на офис</option>';
                foreach ($ofs as $of) {
                    if ($of->number == $br_line->item_place) continue;
                    echo '<option value="' . $of->number . '">' . $of->name_short . '</option>';
                }
            }

            echo '
            </select>
        </form>';

		}



	echo '
			<select name="place_status" form="order_'.$br_line->order_id.'" id="place_status_'.$br_line->order_id.'" style="display:none;">
				<option value="off" '.sel_d('off', $br_line->place_status).'>на офисе</option>
				<option value="rem" '.sel_d('rem', $br_line->place_status).'>у ремонтера</option>
			</select>
			</td>
		<!-- История операций -->
		<td style="vertical-align: top; padding-top: 0; '.((($br_line->type2=='bron' || $br_line->type2=='deliv') && $br_line->appr_id>0) ? 'background-color:#bef5af"' : '').'">';
            if ($br_line->isLastRent()) echo'<div style="background-color: orangered">Последний прокат!</div>';
		//customer info
		    echo '
		    <div '.((($br_line->type2=='bron' || $br_line->type2=='deliv') && $br_line->appr_id>0) ? 'style="background-color:#acf398;"' : '').' onclick="br_del_ch(\''.$br_line->order_id.'\');">
		        <div style="float:left; width: 88px; color: #005d9e">'.date("d", $br_line->cr_time).' '.Base::getShortMonth(date("m", $br_line->cr_time)).'<sup>'.date("H:i", $br_line->cr_time).'</sup>
		        ';
		    if ($br_line->web==1) {
		        echo '<br><span> сайт</span>';
            }
		    else {
		        echo '<br><span>'.User::GetUserName($br_line->cr_who_id).'</span>';
            }
                echo '
                        <br>

		        </div>
		        <div style="float:left; width: 700px;">';
		            if ($br_line->type2=='bron' || $br_line->type2=='deliv') {
		                echo $br_line->getFioFull().'<br>'.$br_line->getDeliveryAddress().'<br>
                                '.$br_line->info;
                    }
		            else {
                        echo $br_line->info;
                    }

		       	echo '
		        </div>
		        <div style="clear: both;"></div>
            </div>
		    ';
                if ($br_line->type2=='bron' || $br_line->type2=='deliv') {
                    echo $br_line->info2;
                }

			echo '
			<div style="display:none;" id="info_div_'.$br_line->order_id.'">
				<textarea rows="3" cols="130" name="info" id="info_'.$br_line->order_id.'" form="order_'.$br_line->order_id.'">';
                if (!$br_line->isBron()) {
                    echo $br_line->info;
                }
                echo '
                </textarea><br />
				'.($br_line->isBron() ? 'Дата действия брони' : 'Срок исполнения').': <input type="date" name="br_valid" id="br_valid_'.$br_line->order_id.'" form="order_'.$br_line->order_id.'" value="'.date("Y-m-d", $br_line->validity).'" style="font-size: 18px;">
				<span id="br_del_span_'.$br_line->order_id.'" '.(($br_line->type2!='deliv' && $br_line->type2!='bron') ? 'style="display:none;"' : 'ddd').'>
					<label for="radio_deliv_s_'.$br_line->order_id.'"><img src="/bb/sam_vivoz.png"/></label><input type="radio" name="radio_deliv" value="bron" '.($br_line->type2=='bron' ? 'checked="checked"' : '').' id="radio_deliv_s_'.$br_line->order_id.'" form="order_'.$br_line->order_id.'" style="width:25px; height:25px;" onchange="radio_ch(\''.$br_line->order_id.'\')" />
					<input type="radio" name="radio_deliv" value="deliv" '.($br_line->type2=='deliv' ? 'checked="checked"' : '').' id="radio_deliv_d_'.$br_line->order_id.'" form="order_'.$br_line->order_id.'" style="width:25px; height:25px;" onchange="radio_ch(\''.$br_line->order_id.'\')" /><label for="radio_deliv_d_'.$br_line->order_id.'"><img src="/bb/deliv.jpg" /></label>
				</span>
				<select '.$show2.' name="rem_type" form="order_'.$br_line->order_id.'" id="rem_type_'.$br_line->order_id.'">
					<option value="" '.sel_d('', $br_line->rem_type).'>не определено</option>
					<option value="stir" '.sel_d('stir', $br_line->rem_type).'>сложная стирка</option>
					<option value="meh" '.sel_d('meh', $br_line->rem_type).'>механ/электр</option>
					<option value="tex" '.sel_d('tex', $br_line->rem_type).'>текстиль</option>
					<option value="oth" '.sel_d('oth', $br_line->rem_type).'>иное</option>
				</select>
				<p style="margin: 0;'.($br_line->type2=='deliv' ? '' : 'display:none;').'" id="address_'.$br_line->order_id.'">Адрес доставки: <input type="text" form="order_'.$br_line->order_id.'" name="address" value="'.$br_line->address.'" style="width: 300px; font-size: 16px;"></p>
		</div>

		<input type="button" id="cans_t_but_'.$br_line->order_id.'" style="background-image:url(/bb/cans.png); width:33px; height:33px; float:right; display:none;" value="" onclick="cans_t(\''.$br_line->order_id.'\');" />
		<input type="submit" name="action" id="save_t_but_'.$br_line->order_id.'" style="background-image:url(/bb/save.png); width:33px; height:33px; float:right; display:none;" value="" form="order_'.$br_line->order_id.'" onclick="return save_t(\''.$br_line->order_id.'\');" />
      	';
        if ($br_line->client_id<0) {
            echo '<input type="submit" name="action" value="ИЗВЕСТИТЬ КЛИЕНТА" class="cl_info_button" form="order_'.$br_line->order_id.'" onclick="return save_t(\''.$br_line->order_id.'\');" >';
        }

        echo '

	    </td>
		<td style="text-align: center; vertical-align: top;">';
            if ($br_line->isOutBron()) {
                echo  '
                <form id="appr_'.$br_line->order_id.'">
                    <input type="hidden" name="order_id" value="'.$br_line->order_id.'">
                ';
                if (!$br_line->isApproved()){
                    echo '<div style="background-color: #ffad0d; border-radius: 10px; margin: 3px; padding: 3px;">на согласовании</div>';
                }
                elseif ($br_line->isApproved()){
                    echo '<div style="background-color: #aeff00; border-radius: 10px; margin: 3px; padding: 3px;">согласовано</div>';
                }

                echo '</form>';

            }

            if ($br_line->isBron()) {
                echo '<span style="color: #005d9e;">'.Base::phone_print($br_line->phone).'</span>';
            }
        echo '
            <!--Дата действия брони-->

                    <br><br>
                    <span style="color: red; font-size: 18px; font-weight: bold;">'.Base::dateKrFormat($br_line->validity).'</span>';
                    if ($br_line->type2=='bron' || $br_line->type2=='deliv') {
                        echo '<input type="button" id="rem_t_but_'.$br_line->order_id.'" style="background-image:url('.($br_line->type2=='bron' ? '/bb/sam_vivoz.png' : '/bb/deliv.jpg').'); width:33px; height:33px; float:right;" value="" onclick="br_del_ch(\''.$br_line->order_id.'\');" />';
                    }
                    else {
                        echo '<input type="button" id="rem_t_but_'.$br_line->order_id.'" style="background-image:url('.$br_line->rem_pic_url.'); width:33px; height:33px; float:right;" value="" onclick="rem_t_ch(\''.$br_line->order_id.'\');" />';
                    }


                    echo '


        </td>
    	<!--<td>
	  		<select style="width:80px; display:none;'.$rem_show.'" name="rem_resp" form="order_'.$br_line->order_id.'" id="rem_resp_'.$br_line->order_id.'">
				'.user_select($br_line->appr_id).'
			</select><br />

							';
					if ($br_line->type2=='remont') {
						echo User::GetUserName($br_line->cr_who_id);
						//echo '<a href="#" onclick="resp_show('.$br_line->order_id.'); return false;" id="rest_show_'.$br_line->order_id.'">'.($br_line->appr_id>0 ? $lp_list[$br_line->appr_id] : '---').'</a>';
					}
					else {
						echo ($br_line->web==1 ? 'сайт' : $lp_list[$br_line->cr_who_id]).'/'.$lp_list[$br_line->appr_id].'<br />'.($br_line->web==1 ? $br_line->cr_ip : '');
					}
		    echo '
			<input type="button" id="cans_resp_but_'.$br_line->order_id.'" style="background-image:url(/bb/cans.png); width:33px; height:33px; float:right; display:none;" value="" onclick="cans_resp(\''.$br_line->order_id.'\');" />
			<input type="submit" name="action" id="save_resp_but_'.$br_line->order_id.'" style="background-image:url(/bb/save.png); width:33px; height:33px; float:right; display:none;" value="" form="order_'.$br_line->order_id.'" onclick="return save_resp(\''.$br_line->order_id.'\');" />


		</td>-->
		<td> <!-- actions -->
			<form name="order_'.$br_line->order_id.'" id="order_'.$br_line->order_id.'" action="rent_orders.php" method="post" '.(($br_line->type2=='out' || $br_line->type2=='sell') ? 'style="display:none;"' : '').'>
			<div '.$show1.'>
				<input type="hidden" name="user_id" id="user_id_'.$br_line->order_id.'" value="'.$_SESSION['user_id'].'">
				<input type="hidden" name="order_id" id="order_id_'.$br_line->order_id.'" value="'.$br_line->order_id.'">
				<input type="hidden" name="type2" id="type2_'.$br_line->order_id.'" value="'.$br_line->type2.'">
      			<input type="hidden" name="last_ch_time" value="'.$br_line->ch_time.'">
				<input type="hidden" name="office" value="'.$office.'" />

    	  		'.($br_line->appr_id>0 ? '' : '<input type="submit"  name="action" id="edit_show_'.$br_line->order_id.'" value="подтвердить">').'
      			<input type="submit" style="display:none;" name="action" id="save_podtv_'.$br_line->order_id.'" value="сохранить подтверждение">
				<input type="submit" style="background-color:#ed8886; float:right;" name="action" id="del_but_'.$br_line->order_id.'" id="action_'.$br_line->order_id.'" onclick="return confirm(\'Вы точно хотите удалить эту бронь?\');" value="удалить"><br />
      	  		<input type="submit" style="background-color:#e4ccf2" name="action" id="obnov_'.$br_line->order_id.'" value="недозвон" onclick="return obnov(\''.$br_line->order_id.'\');">
			</div>

			<div '.$show2.'>
	  			<input type="submit" name="action" value="Исполнено" style="background-color:#0F3;" />
      			<input type="submit" name="action" value="Отправить на выбытие" style="background-color:#F63;" /><br />

	  		</div>

			</form>
			<div '.(($br_line->type2=='out' && User::getCurrentUser()->hasPermission(1)) ? '' : 'style="display:none;"').'>
      			<input type="hidden" name="del_inv_n" id="del_inv_n_'.$br_line->order_id.'" value="'.$br_line->inv_n.'" form="order_'.$br_line->order_id.'" />
				<input type="submit" name="action" form="order_'.$br_line->order_id.'" value="Списать товар" style="background-color:#F63; height:40px;" onclick="return confirm(\'Вы точно хотите списать/удалить этот товар?\');" />
				<input type="submit" name="action" form="order_'.$br_line->order_id.'" value="Вернуть в ремонт" style="background-color:green; height:40px;" />
      </div>

      <div '.(($br_line->type2=='sell' && User::getCurrentUser()->hasPermission(1)) ? '' : 'style="display:none;"').'>
      			<input type="hidden" name="del_inv_n" id="del_inv_n_'.$br_line->order_id.'" value="'.$br_line->inv_n.'" form="order_'.$br_line->order_id.'" />
				<input type="submit" name="action" form="order_'.$br_line->order_id.'" value="Вернуть в ремонт" style="background-color:green; height:40px;" />
				<input class="sell-btn" type="button" name="action" form="order_'.$br_line->order_id.'" value="Продать" style="background-color:#00a0d0; height:40px;" />
      </div>

			<div '.$show1.'>
      			<form method="post" action="dogovor_new.php" style="display:inline-block; '.(($br_line->type2=='out' || $br_line->type2=='sell') ? ' display:none;' : '').'"><input type="hidden" name="item_inv_n" value="'.$br_line->inv_n.'" /><input type="submit" style="background-color:#acf398;" id="new_dog_but_'.$br_line->order_id.'" value="'.($uzhe_vidan==1 ? 'к договору' : 'нов.договор').'" /></form>
			</div>
			<span title="order_id, cl_id">'.(User::getCurrentUser()->id_user==3 ? $br_line->order_id.', cl_id='.$br_line->client_id : '').'</span>

		</td>
	</tr>



			';
	unset($br_line);
}
echo '</table>';
//echo '<pre>';
//var_dump($remont_users_count);
//var_dump($remont_users_new);
//echo '<pre>';





function get_post($var)
{
	GLOBAL $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);}


function sel_d($value, $pattern) {
	if ($value==$pattern) {
		return 'selected="selected"';
	}
	else {
		return '';
	}
}

function good_print($var)
{
	$var=htmlspecialchars(stripslashes($var));
	return $var;
}


function user_select ($id) {
	return '
		<option value="">не определен</option>
      		<option '.sel_d('2', $id).' value="2">Кристина</option>
			<option '.sel_d('5', $id).' value="5">Аня</option>
			<option '.sel_d('4', $id).' value="4">Андрей</option>
			<option '.sel_d('9', $id).' value="9">Света</option>
			<option '.sel_d('12', $id).' value="12">Алексей</option>
			<option '.sel_d('13', $id).' value="13">Таня</option>
			<option '.sel_d('16', $id).' value="16">Любовь Алексеевна</option>
			<option '.sel_d('18', $id).' value="18">Марго</option>

				';
}



/*описание некоторых подходов
 * Возможные варианты жесткой брони: type2 bron, deliv, remont, out
 * Варианты нежесткой брони: stirka_rent, заявка
 *
 *
 *
 *
 *
 *
 *
 *
 * */

?>

<form class="modal" method="post">
  <input type="hidden" name="invn-m" value="">
  <input type="hidden" name="brid-m" value="">
  <input type="hidden" name="type2-m" value="out">
  <input type="hidden" name="action" value="tovar_sell">
  <div class="modal-content">
    <div class="mrow" style="display: none">
      <span>Офис на который отнесется выручка</span>
      <select name="office-m">
        <option value="0">текущий офис</option>
        <option value="1">Литературная</option>
        <option value="2">Ложинская</option>
      </select>
    </div>
    <div class="mrow">
      <span>Сумма</span>
      <input name="amount-m" type="number" step="0.01" min="0">
    </div>
    <div class="mrow">
      <span>Касса</span>
      <select name="kassa-m">
        <option value="0">выберите кассу</option>
        <option value="k1">Касса 1</option>
        <option value="k2">Касса 2</option>
        <option value="card">Карта</option>
        <option value="bank">Банк</option>
      </select>
    </div>
    <div class="mrow">
      <span>Доп. инфо</span>
      <textarea name="dop_info-m" id="" cols="30" rows="10"></textarea>
    </div>
    <div class="mrow">
      <button class="btn-sell">Сохранить продажу</button>
    </div>
  </div>
</form>

<div id="contextMenu" class="context-menu">
  <ul>
    <li id="menuOption1">Отправить на продажу
      <form method="post" style="display: none">
        <input type="hidden" name="brid_context" value="">
        <input type="hidden" name="type2" value="<?= $type2 ?>">
        <input type="hidden" name="action" value="to_sell">
      </form>
      </li>
  </ul>
</div>

<script src="/bb/assets/js/brons.js?v=3"></script>
