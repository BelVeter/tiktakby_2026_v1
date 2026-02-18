<?php

use bb\Base;
use bb\classes\Collateral;
use bb\classes\KBChange;
use bb\classes\SpeedTrack;
use bb\KBron;
use bb\KBronForm;
use bb\models\Office;
use bb\models\User;

session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/KBron.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Schedule.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/KBChange.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/KBronForm.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Permission.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/SpeedTrack.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Collateral.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/Office.php'); //

//------- proverka paroley
$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
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

//-----------proverka paroley

$deleted_inv_n = [];

?>
<style>
	.hidden_form {
		display: none;
	}

	.zv-row {
		display: flex;
		flex-flow: column nowrap;
		gap: 10px;
	}

	.alert-danger {
		text-align: center !important;
		color: #721c24;
		background-color: #f8d7da;
		border-color: #f5c6cb;
		font-size: 20px;
	}

	.btn-danger {
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

	.inv_ns {
		cursor: pointer;
		text-decoration: underline;
	}
</style>
<?php

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<style>

</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
' . Base::getBarCodeReaderScript() . '
</head>
<title>Карнавал. Брони.</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> ' . $_SESSION['user_fio'] . '</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>


';
include_once($_SERVER['DOCUMENT_ROOT'] . '/bb/bb_nav.php');
echo '
<div class="row zv-row">
    <div class="col alert-danger h2 text-center" id="zv_div"></div>
</div>
';
require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show2.php');

?>

<script>

	history.pushState(null, null, location.href);
	window.onpopstate = function (event) {
		history.go(1);
	};

	function podtv_save(id) {
		$from = new Date(document.getElementById('br_date_from_' + id).value);
		$from.setHours(document.getElementById('br_hour_from_' + id).value)
		$from.setMinutes(0);
		$to = new Date(document.getElementById('br_date_to_' + id).value);
		$to.setHours(document.getElementById('br_hour_to_' + id).value)
		$to.setMinutes(0);

		if ($from > $to || $from == $to) {
			alert('Дата "с" должна быть ранее даты "по"!');
			return false;
		}
		else {
			return true;
		}
	}

	function type_button() {
		if (document.getElementById('type_but').value == "все брони") {
			document.getElementById('type_but').value = "неподтвержденные";
			document.getElementById('br_status_id').value = "all_bez";
			document.getElementById('rows_limit').value = 10;

		}
		else {
			document.getElementById('type_but').value = "все брони";
			document.getElementById('br_status_id').value = "new";
			document.getElementById('rows_limit').value = 0;
		}
		document.getElementById('type_but').disabled = "disabled";
		document.getElementById('submit_but').click();
	}

	function getWebPageUrl(kbId) {
		fetch('kb_web_url.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: `kb_id=${kbId}`,
		})
			.then(response => {
				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}
				return response.json(); // Parse the JSON response
			})
			.then(data => {
				if (data[0]) { // Check the boolean result (data[0])
					theLink = document.querySelector('div#pic_' + kbId + ' a');
					theLink.href = data[1];
				}
				else {
					console.log('web url fail');
				}
			})
			.catch(error => {
				console.error("Request error:", error); // Handle fetch errors
			});
	}

	function show_pic(item_id, pic_url) {

		if (pic_url != '') {
			document.getElementById('kb_pic_' + item_id).src = pic_url;
		}

		getWebPageUrl(item_id);

		if (document.getElementById('pic_' + item_id).style.display == "none") {
			document.getElementById('pic_' + item_id).style.display = "";
		}
		else {
			document.getElementById('pic_' + item_id).style.display = "none";
		}

	}

	function formatDate(date) {

		var dd = date.getDate()
		if (dd < 10) dd = '0' + dd;

		var mm = date.getMonth() + 1
		if (mm < 10) mm = '0' + mm;

		var yyyy = date.getFullYear();

		return yyyy + '-' + mm + '-' + dd;
	}

	function prov_free(br_id) {

		var start_date = new Date(document.getElementById('br_date_from_' + br_id).value);
		var finish_date = new Date(document.getElementById('br_date_to_' + br_id).value);

		if (start_date > finish_date) {
			alert('Дата "C" фильтра должна быть меньше либо равна дате "по"!');
			return false;
		}
		else {
			start_date.setDate(start_date.getDate() - 2);
			finish_date.setDate(finish_date.getDate() + 2);

			document.getElementById('prov_start_' + br_id).value = formatDate(start_date);
			document.getElementById('prov_finish_' + br_id).value = formatDate(finish_date);
			document.getElementById('prov_form_' + br_id).submit();
		}
		document.getElementById('prov_start_' + br_id).value = "";
		document.getElementById('prov_finish_' + br_id).value = "";

	}


	function appr_show(br_id) {

		if (document.getElementById('phone1_' + br_id).style.display == "none") {
			document.getElementById('phone1_' + br_id).style.display = "";
			document.getElementById('phone2_' + br_id).style.display = "";
			document.getElementById('info_' + br_id).style.display = "";
			document.getElementById('subm_but_' + br_id).style.display = "";
			document.getElementById('dl_link_' + br_id).style.display = "";
			document.getElementById('prov_but_' + br_id).style.display = "";

			$bron_edit_allow = Array('2', '3', '5', '22', '24', '9', '28', '26', '33');
			if (1 == 1) {//$bron_edit_allow.indexOf(document.getElementById('user_id').value) != -1    !!! для контроля доступа к изменению
				//document.getElementById('user_id').value=="2" || document.getElementById('user_id').value=="3" || document.getElementById('user_id').value=="5" || document.getElementById('user_id').value=="22" || document.getElementById('user_id').value=="24"
				document.getElementById('br_date_from_' + br_id).style.display = "";
				document.getElementById('br_hour_from_' + br_id).style.display = "";
				document.getElementById('br_date_to_' + br_id).style.display = "";
				document.getElementById('br_hour_to_' + br_id).style.display = "";
				document.getElementById('from_div_' + br_id).style.display = "";
				document.getElementById('to_div_' + br_id).style.display = "";

			}

			document.getElementById('show_but_' + br_id).value = "отмена";
		}
		else {
			document.getElementById('phone1_' + br_id).style.display = "none";
			document.getElementById('phone2_' + br_id).style.display = "none";
			document.getElementById('info_' + br_id).style.display = "none";
			document.getElementById('subm_but_' + br_id).style.display = "none";
			document.getElementById('br_date_from_' + br_id).style.display = "none";
			document.getElementById('br_hour_from_' + br_id).style.display = "none";
			document.getElementById('br_date_to_' + br_id).style.display = "none";
			document.getElementById('br_hour_to_' + br_id).style.display = "none";
			document.getElementById('dl_link_' + br_id).style.display = "none";
			document.getElementById('prov_but_' + br_id).style.display = "none";
			document.getElementById('to_div_' + br_id).style.display = "none";
			document.getElementById('from_div_' + br_id).style.display = "none";

			document.getElementById('show_but_' + br_id).value = "подтвердить";
		}


	}


	function payment_show(br_id) {

		if (document.getElementById('pay_form_' + br_id).style.display == "none") {
			document.getElementById('pay_form_' + br_id).style.display = "";
		}
		else {
			document.getElementById('pay_form_' + br_id).style.display = "none";
		}


	}



	function s_ch() {

		var start_date = new Date(document.getElementById('start_date').value);
		var finish_date = new Date(document.getElementById('finish_date').value);

		if (start_date > finish_date) {
			alert('Дата "C" фильтра должна быть меньше либо равна дате "по" фильтра');
			return false;
		}
		else {
			return true;
		}

	}


	function del_ch(kb_id) {
		if (confirm('Точно хотите удалить бронь?')) {
			var otvet = prompt('Причина удаления?(не использовать кавычки!!!)', 'не указана');
			if (otvet == null) {
				return false;
			}
			else {
				document.getElementById('arch_info_' + kb_id).value = otvet;
				return true;
			}
		}
		else {
			return false;
		}

	}//end of del_ch function
</script>

<?php

SpeedTrack::start();

//require_once ($_SERVER['DOCUMENT_ROOT'].'/includes/zv_show.php'); // включаем подключение к звонкам

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$br_ch_id = '';
$inv_n = '';
$t_from = date("Y-m-d");
//$t_to=date("Y-m-d");
$t_to = date("Y-m-d", (time() + 31 * 3 * 24 * 60 * 60));
$br_status = 'all_bez';
$order_br = 'cr_time';
$br_hour_from = '';
$br_hour_to = '';
$main_rows = 0;
$subdl_rows = 0;
$fio_s = '';
$mod_s = '';
$br_num_s = '';
$rows_num = 0;
$srch_limit = '';
$status_show = 'all';

$mysqli = \bb\Db::getInstance()->getConnection();

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}
//обработка входящих значений
$fio_s = trim($fio_s);
$br_num_s = phone_to_n($br_num_s);

if ($fio_s != '' || $br_num_s > 0 || $mod_s != '') {
	$rows_num = 0;
}


$t_from_n = strtotime($t_from);
$t_to_n = strtotime($t_to);

if (isset($_POST['action'])) {

	switch ($action) {

		case '`':
			$t_from = strtotime($from_d) + $from_h * 60 * 60;
			$t_to = strtotime($to_d) + $to_h * 60 * 60;

			$query_new = "INSERT INTO karn_brons VALUES('', '$inv_n', '', '$t_from', '$t_to', 'new', 'info', '0', '0', '0', '0', '', '" . time() . "', '', '', '', '', '')";
			$result = $mysqli->query($query_new);
			if (!$result) {
				die('Сбой при доступе к базе данных: ' . $query_new . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			break;

		case 'выдача':
			$query_upd = "UPDATE karn_brons SET vidacha='" . time() . "', vid_who_id='" . $_SESSION['user_id'] . "' WHERE kb_id='$br_id'";
			$result = $mysqli->query($query_upd);
			if (!$result) {
				die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			echo 'Выдача костюма отражена';

			break;

		case 'возврат':
			$query_upd = "UPDATE karn_brons SET vozvrat='" . time() . "', vozvr_who_id='" . $_SESSION['user_id'] . "' WHERE kb_id='$br_id'";
			$result = $mysqli->query($query_upd);
			if (!$result) {
				die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
			echo 'Возврат костюма отражен';

			break;


		case 'сохранить подтверждение':
			$phone1 = phone_to_n($phone1);
			$phone2 = phone_to_n($phone2);

			$br_time_from = strtotime($br_date_from) + $br_hour_from * 3600;
			$br_time_to = strtotime($br_date_to) + $br_hour_to * 3600;

			if ($dl_link > 0) {
				$query_dl_link = "SELECT * FROM rent_deals_act WHERE deal_id='$dl_link' AND item_inv_n='$item_inv_n'";
				$result_dl_link = $mysqli->query($query_dl_link);
				if (!$result_dl_link) {
					die('Сбой при доступе к базе данных: ' . $query_dl_link . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
				$dl1_rows = $result_dl_link->num_rows;

				if ($dl1_rows < 1) {
					$query_dl_link = "SELECT * FROM rent_deals_arch WHERE deal_id='$dl_link' AND item_inv_n='$item_inv_n'";
					$result_dl_link = $mysqli->query($query_dl_link);
					if (!$result_dl_link) {
						die('Сбой при доступе к базе данных: ' . $query_dl_link . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					}
					$dl1_rows = $result_dl_link->num_rows;
				}

				if ($dl1_rows < 1) {
					$dl_link_prev = $dl_link;
					$dl_link = 0;
					echo '<p style="color:red;"><strong>Связь с договором не внесена, т.к. для инвентарного номера: ' . $item_inv_n . ' не найден номер договора: ' . $dl_link_prev . ' !!!</strong></p>';
				}
			}

			$br_validity_time = time() - 5 * 60;
			;

			//            $query_check="SELECT * from `karn_brons` WHERE kb_id!='$br_id' AND ((t_from BETWEEN '$br_time_from' AND '$br_time_to') OR (t_to BETWEEN '$br_time_from' AND '$br_time_to')) AND (`status` IN ('new', 'ok') OR (`status`='in_process' AND `cr_time` >= '$br_validity_time'))";
//            $result_check = $mysqli->query($query_check);
//            if (!$result_check) {die('Сбой при доступе к базе данных: '.$query_check.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
//            if ($result_check->num_rows>0) {die('При попытке изменения времени брони вы залезли на другую. Изменения времени брони не сохранены');}

			$from_time = new DateTime();
			$from_time->setTimestamp($br_time_from);

			$to_time = new DateTime();
			$to_time->setTimestamp($br_time_to);

			$brs = \bb\KBron::getBrons($item_inv_n, $from_time, $to_time, [$br_id,]);
			if ($brs != false)
				die('<strong style="color: red;">Изменение брони не сохранено, т.к. новая бронь перекрывала одну и здейсвующих.</strong>');

			$kb_changed = '';
			if ($kb_old = KBron::isTimeChanged($br_id, $from_time, $to_time)) {
				//if changed
				$kb_ch = new KBChange();

				$kb_ch->kb_id = $br_id;
				$kb_ch->from_old = $kb_old->from_kb;
				$kb_ch->to_old = $kb_old->to_kb;
				$kb_ch->from_new = $from_time;
				$kb_ch->to_new = $to_time;
				$kb_ch->appr_who_old_id = $kb_old->appr_who;

				$kb_ch->save();

				$kb_changed = ", payment_bank='1'";
			}



			$query_upd = "UPDATE karn_brons SET `status`='ok', dl_link='" . ((int) $dl_link) . "', t_from='$br_time_from', t_to='$br_time_to', info='$info', phone1='$phone1', phone2='$phone2', appr_time='" . time() . "', appr_who='" . $_SESSION['user_id'] . "'$kb_changed WHERE kb_id='$br_id'";
			//echo $query_upd;
			$result = $mysqli->query($query_upd);
			if (!$result) {
				die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			$br_ch_id = $br_id;

			echo '<p style="color:blue;"><strong>Бронь подтверждена!</strong> </p>';
			break;

		case 'недозвон':

			$query_upd = "UPDATE karn_brons SET `nedozvon`='" . time() . "' WHERE kb_id='$br_id'";
			$result = $mysqli->query($query_upd);
			if (!$result) {
				die('Сбой при доступе к базе данных: ' . $query_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}

			$br_ch_id = $br_id;

			echo '<p style="color:blue;"><strong>Время недозвона сохранено.</strong> </p>';
			break;


		case 'удалить':

			$done = "yes";

			//запускаем транзакцию
			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
			if (!$result_start) {
				die('Сбой при доступе к базе данных: ' . $query_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				$done = "no";
			}


			//перенос брони в архив
			$query_arch = "INSERT INTO karn_brons_arch SELECT '', '" . time() . "', '" . $_SESSION['user_id'] . "', '$arch_info', kb_id, inv_n, cl_id, t_from, t_to, `status`, `info`, payment_k1, payment_k2, payment_term, payment_bank, payment_date, cr_time, br_max_num, br_num, fio, phone1, phone2, `mail`, dl_link, appr_time, appr_who, vidacha, vid_who_id, vozvrat, vozvr_who_id, nedozvon FROM karn_brons WHERE kb_id='$br_id'";
			$result_arch = $mysqli->query($query_arch);
			if (!$result_arch) {
				die('Сбой при доступе к базе данных: ' . $query_arch . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				$done = "no";
			}


			$query_del = "DELETE FROM karn_brons WHERE kb_id='$br_id'";
			$result_del = $mysqli->query($query_del);
			if (!$result_del) {
				die('Сбой при доступе к базе данных: ' . $query_del . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				$done = "no";
			}

			//завершение
			if ($done == 'yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
				echo '<p style="color:red;"><strong>Бронь успешно удалена!</strong> </p>';
			} else {
				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}

				echo '<p style="color:red;"><strong>Возникли проблемы с удалением брони. Обратитесь к разработчику.</strong> </p>';
			}


			break;

		case 'сохранить оплату':

			$done = "yes";

			$query_start = "START TRANSACTION";
			$result_start = $mysqli->query($query_start);
			if (!$result_start) {
				die('Сбой при доступе к базе данных: ' . $query_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				$done = "no";
			}

			//выбираем основную сделку для апдейта
			$query_dl_first = "SELECT * FROM rent_deals_act WHERE deal_id='$deal_id'";
			$result_dl_first = $mysqli->query($query_dl_first);
			if (!$result_dl_first) {
				die('Сбой при доступе к базе данных: ' . $query_dl_first . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				$done = "no";
			}
			$dl_fitst_num = $result_dl_first->num_rows;

			if ($dl_fitst_num > 0) {
				$dl_first = $result_dl_first->fetch_assoc();
				$dl_base = 'act';
			} else {//если не нашли сделку в действующих, смотрим в архив
				$query_dl_first = "SELECT * FROM rent_deals_arch WHERE deal_id='$deal_id'";
				$result_dl_first = $mysqli->query($query_dl_first);
				if (!$result_dl_first) {
					die('Сбой при доступе к базе данных: ' . $query_dl_first . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					$done = "no";
				}
				$dl_first = $result_dl_first->fetch_assoc();
				$dl_base = 'arch';
			}


			$payment_date = strtotime($payment_date); //приводим в формат юникс дату календаря гггг-мм-дд
			$r_paid = tonum($r_paid);//меняем точку на запятую + убираем пробелы и лишние символы


			//выбираем "первую сдачу" (либо "аванс"), к которой привязываем платеж
			if ($dl_base == 'act') {

				$query_sub_dl_first = "SELECT * FROM rent_sub_deals_act WHERE deal_id='$deal_id' AND `type` IN ('first_rent', 'takeaway_plan') ORDER by cr_time DESC";
				$result_sub_dl_first = $mysqli->query($query_sub_dl_first);
				if (!$result_sub_dl_first) {
					die('Сбой при доступе к базе данных: ' . $query_sub_dl_first . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					$done = "no";
				}
				$sub_dl_first = $result_sub_dl_first->fetch_assoc();
			} else {
				$query_sub_dl_first = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='$deal_id' AND `type` IN ('first_rent', 'takeaway_plan') ORDER by cr_time DESC";
				$result_sub_dl_first = $mysqli->query($query_sub_dl_first);
				if (!$result_sub_dl_first) {
					die('Сбой при доступе к базе данных: ' . $query_sub_dl_first . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					$done = "no";
				}
				$sub_dl_first = $result_sub_dl_first->fetch_assoc();
			}

			// вносим суб-сделку (история + подробности)
			if ($dl_base == 'act') {

				$sub_query = "INSERT INTO rent_sub_deals_act VALUES('', '$deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$r_paid', '', '$rent_payment_type', '', 'pure_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '" . $sub_dl_first['sub_deal_id'] . "', '$payment_date', '" . $_SESSION['office'] . "', '', '', '', '')";
				$result_sub_query = $mysqli->query($sub_query);
				if (!$result_sub_query) {
					die('Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					$done = "no";
				}
			} else {
				//делаем вставку суб. сделки чтобы получить ID
				$sub_query0 = "INSERT INTO rent_sub_deals_act VALUES('', '$deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$r_paid', '', '$rent_payment_type', '', 'pure_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '" . $sub_dl_first['sub_deal_id'] . "', '$payment_date', '" . $_SESSION['office'] . "', '', '', '', '')";
				$result_sub_query0 = $mysqli->query($sub_query0);
				if (!$result_sub_query0) {
					die('Сбой при доступе к базе данных: ' . $sub_query0 . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					$done = "no";
				}
				$kb_payment_last_id = $mysqli->insert_id;

				$sub_query3 = "DELETE FROM rent_sub_deals_act WHERE sub_deal_id='$kb_payment_last_id'";
				$result_sub_query3 = $mysqli->query($sub_query3);
				if (!$result_sub_query3) {
					die('Сбой при доступе к базе данных: ' . $sub_query3 . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					$done = "no";
				}

				$sub_query = "INSERT INTO rent_sub_deals_arch VALUES('', '" . time() . "', '$kb_payment_last_id', '$deal_id', 'payment', '30', '$payment_date', '', '', '', '', '', '', '', '', '', '$r_paid', '', '$rent_payment_type', '', 'pure_payment', '', '" . time() . "', '" . $_SESSION['user_id'] . "', '', '', '" . $sub_dl_first['sub_deal_id'] . "', '$payment_date', '" . $_SESSION['office'] . "', '', '', '', '')";
				$result_sub_query = $mysqli->query($sub_query);
				if (!$result_sub_query) {
					die('Сбой при доступе к базе данных: ' . $sub_query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					$done = "no";
				}
			}

			// корректируем сделку
			$r_paid = $dl_first['r_paid'] + $r_paid;

			if ($dl_base == 'act') {

				$query_dl_upd = "UPDATE rent_deals_act SET r_paid='$r_paid', last_sub_deal_ch_time='" . time() . "' WHERE deal_id='$deal_id'";
				$result_dl_upd = $mysqli->query($query_dl_upd);
				if (!$result_dl_upd) {
					die('Сбой при доступе к базе данных: ' . $query_dl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					$done = "no";
				}
			} else {
				$query_dl_upd = "UPDATE rent_deals_arch SET r_paid='$r_paid', last_sub_deal_ch_time='" . time() . "' WHERE deal_id='$deal_id'";
				$result_dl_upd = $mysqli->query($query_dl_upd);
				if (!$result_dl_upd) {
					die('Сбой при доступе к базе данных: ' . $query_dl_upd . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
					$done = "no";
				}
			}



			if ($done == 'yes') {
				$query_fin = "COMMIT";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			} else {
				$query_fin = "ROLLBACK";
				$result_fin = $mysqli->query($query_fin);
				if (!$result_fin) {
					die('Сбой при доступе к базе данных: ' . $query_fin . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
				}
			}

			break;

	}//end of switch
}//end of spost if




echo '
	<form name="br_srch" action="kb.php" method="post" id="br_srch">
	    <select name="status_show" class="form-control form-control-lg" onchange="this.form.submit();" style="font-size: 20px;">
	        <option value="all" ' . sel_d('all', $status_show) . '>все брони</option>
        	<option value="on_hand" ' . sel_d('on_hand', $status_show) . '>только на руках</option>
        	<option value="no_on_hand" ' . sel_d('no_on_hand', $status_show) . '>не выдававшиеся</option>
		</select><br>';

$std_srch_form = '

		<div class="karn_srch_bl" style="color:#00adef;">
			<span>ФАМИЛИЯ</span></br>
			<input type="text" name="fio_s" value="' . $fio_s . '" style="border-color:#00adef; width:100px;" title="фамилия" />
		</div>
		<div class="karn_srch_bl" style="color:#48ce32;">
			<span>МОДЕЛЬ</span></br>
			<input type="text" name="mod_s" value="' . $mod_s . '" style="border-color:#48ce32; width:100px;" title="название модели" />
		</div>
		<div class="karn_srch_bl" style="color:#ff0006;">
			<span>№ БРОНИ</span></br>
			<input type="text" name="br_num_s" value="' . $br_num_s . '" style="border-color:#ff0006; width:100px;" title="номер брони начинается с" />
		</div>
		<div class="karn_srch_bl" style="color:#ffa200; ">
			<span>Дата с:</span></br>
			<input type="date" name="t_from" id="start_date" value="' . $t_from . '" style="border-color:#ffa200; width:130px;" />
		</div>
		<div class="karn_srch_bl" style="color:#ffa200;">
			<span>по</span></br>
			<input type="date" name="t_to" id="finish_date" value="' . $t_to . '"  style="border-color:#ffa200; width:130px;" />
		</div>
		<div class="karn_srch_bl" style="color:#5a00ff;">
			<!--<span>Cтрок:</span></br>-->
			<input type="hidden" id="rows_limit" name="rows_num" value="0" style="width:40px;" /><br /><!-- (<a href="#" onclick="document.getElementById(\'rows_limit\').value=0;">0=все строки</a>)		-->
		</div>


		<div style="clear:both;"></div>

		<!--
		Инв.№:<input type="text" name="inv_n" value="' . $inv_n . '" size="5" />(пусто=все), брони в период c  по ,
		со статусом: -->
		<select name="br_status" id="br_status_id" style="display:none;">
			<option value="all_bez" ' . sel_d('all_bez', $br_status) . '>все без временных</option>
			<option value="all" ' . sel_d('all', $br_status) . '>все</option>
        	<option value="new" ' . sel_d('new', $br_status) . '>не проверено</option>
        	<option value="ok" ' . sel_d('ok', $br_status) . '>подтверждено</option>
			<option value="in_process" ' . sel_d('in_process', $br_status) . '>временные</option>
		</select>
		сортировка по:
		<select name="order_br" >
			<option value="t_from" ' . sel_d('t_from', $order_br) . '>времени выдачи (ближайшее-вверху)</option>
			<option value="t_to" ' . sel_d('t_to', $order_br) . '>времени возврата (ближайшее-вверху)</option>
        	<option value="cr_time" ' . sel_d('cr_time', $order_br) . '>времени заведения (последние-вверху)</option>
		</select>

				<br />
		<input type="button" id="type_but" value="' . ($br_status == 'all_bez' ? 'неподтвержденные' : 'все брони') . '" onclick="type_button();" style="width:150px; height:30px; background-color:yellow" />
				<br />
		<input name="action" id="submit_but" type="submit" onclick="return s_ch();" value="фильтр" style="width:150px; height:50px;" /> <-- для обновления страницы нажимайте кнопку "Фильтр"';

if ($status_show != 'on_hand') {
	echo $std_srch_form;
} else {
	$col_amount = Collateral::getOstatok(Office::getCurrentOffice()->number);
	echo '<br><span style="background-color: #7ed500; font-size: 21px; padding: 5px;">Принято залогов: ' . number_format($col_amount, 2, ',', ' ') . '</span>';
}

echo '
	</form>
		<input type="hidden" id="user_id" value="' . $_SESSION['user_id'] . '" />
		';


//формируем перечень пользователей

$rd_lp = "SELECT * FROM logpass";
$result_lp = $mysqli->query($rd_lp);
if (!$result_lp) {
	die('Сбой при доступе к базе данных: ' . $rd_lp . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$lp_list = '';

while ($lp_l = $result_lp->fetch_assoc()) {
	$lp_list[$lp_l['logpass_id']] = $lp_l['lp_fio'];
}





//основная выборка броней
if ($inv_n > 0) {
	$srch = " WHERE inv_n='$inv_n'";
} else {
	$srch = " WHERE inv_n>0";
}

SpeedTrack::meashure();

//время
$t_to_n2 = $t_to_n + 24 * 3600 - 1;
if ($t_from_n > 0 && $t_to_n > 0) {
	$srch .= " AND ((t_from>=$t_from_n AND t_from<=$t_to_n2) OR (t_to>=$t_from_n AND t_to<=$t_to_n2))";
} elseif ($t_from_n > 0 && $t_to_n == 0) {
	$srch .= " AND ((t_from>=$t_from_n) OR (t_to>=$t_from_n))";
} elseif ($t_from_n == 0 && $t_to_n > 0) {
	$srch .= " AND ((t_from<=$t_to_n2) OR (t_to<=$t_to_n2))";
}

if ($br_status == 'all_bez') {
	$srch .= " AND karn_brons.status!='in_process'";
} elseif ($br_status != 'all') {
	$srch .= " AND karn_brons.status='$br_status'";
}

if ($fio_s != '') {
	$srch .= " AND `fio` LIKE '%$fio_s%'";
	//$rows_num=0;  это описано выше
}
if ($br_num_s > 0) {
	$srch .= " AND `br_num` LIKE '$br_num_s%'";
	//$rows_num=0;  это описано выше
}

if ($mod_s != '') {
	$srch .= " AND model LIKE '%$mod_s%'";
	//$rows_num=0;  это описано выше
}

//сортировка
if ($order_br == 't_from') {
	$order_cl = " ORDER BY t_from";
} elseif ($order_br == 'cr_time') {
	$order_cl = " ORDER BY cr_time DESC";
} elseif ($order_br == 't_to') {
	$order_cl = " ORDER BY t_to";
} else {
	$order_cl = '';
}

if ($rows_num > 0) {
	$srch_limit = " LIMIT $rows_num";
} else {
	$srch_limit = '';
}

if ($status_show == 'on_hand') {
	$vozvr_date = new DateTime();
	$vozvr_date->setDate(2019, 10, 1);
	$vozvr_date->setTime(0, 0, 0);
	$srch = " WHERE (vidacha IS NOT NULL AND vidacha>0) AND (vozvrat IS NULL OR vozvrat<1) AND t_from>'" . $vozvr_date->getTimestamp() . "'";
	$order_cl = " ORDER BY t_to";
	$srch_limit = "";
} elseif ($status_show == 'no_on_hand') {
	$srch .= " AND (vidacha IS NULL OR vidacha=0)";
}

$query_kb = "SELECT kb_id, inv_n, cl_id, karn_brons.`status`, dl_link, vidacha, vid_who_id, vozvrat, vozvr_who_id, payment_bank, br_num, cr_time, t_from, t_to, appr_time, appr_who, fio, nedozvon, phone1, phone2, info, payment_k1, payment_k2, `mail`, tovar_rent.model,
    (
        COALESCE((SELECT SUM(r_paid) FROM rent_sub_deals_act WHERE deal_id = karn_brons.dl_link), 0) + 
        COALESCE((SELECT SUM(r_paid) FROM rent_sub_deals_arch WHERE deal_id = karn_brons.dl_link), 0)
    ) AS r_paid_total
    FROM karn_brons
    LEFT JOIN tovar_rent_items
        ON (tovar_rent_items.item_inv_n = karn_brons.inv_n)
    LEFT JOIN tovar_rent
        ON (tovar_rent_items.model_id = tovar_rent.tovar_rent_id)
    $srch$order_cl$srch_limit";
//echo $query_kb;
$result_kb = $mysqli->query($query_kb);
if (!$result_kb) {
	die('Сбой при доступе к базе данных: ' . $mysqli->error . ' (' . $mysqli->errno . ') ' . $query_kb);
}
$main_rows = $result_kb->num_rows;
SpeedTrack::meashure();
/*
echo '

<form action="/bb/kb.php" method="post">
	инв. номер <input type="text" name="inv_n" value="" />,<br />
	брони с выдачей начиная с <input name="from_d" type="date" /><input type="number" name="from_h" /> <br />
	с возвращением по <input name="to_d" type="date" /><input type="number" name="to_h" /> <br />
	<input name="action" type="submit" value="найти" />
</form>
<br /><br /><br />';
*/

echo '<br /><br /><br />
' . $main_rows . '
<table border="1" cellspacing="0" style="table-layout:fixed; width:1500px;" >
	<tr>
		<td style="width:50px;">№ брони</td>
		<td style="width:150px;">инв. н</td>
		<td style="width:100px;">с</td>
		<td style="width:100px;">по</td>
		<td style="width:100px;">договор</td>
		<td style="width:220px;">статус</td>
		<td>фио</td>
		<td style="width:100px;">телефон1/2<br />(+375-)</td>
		<td style="width:200px;">доп. инфо/почта</td>
		<td>Действия</td>
	</tr>
		';
//to save already loaded items to speed up the process
$tovs = array();
$mis = array();

//$test_br_col=array();

while ($kb = $result_kb->fetch_assoc()) {
	$st_color = '';

	if (isset($tovs[$kb['inv_n']])) {
		$item = $tovs[$kb['inv_n']];
	} else {
		$query_item = "SELECT model_id, item_place, model, item_size, item_rost1, item_rost2 FROM tovar_rent_items
                    LEFT JOIN tovar_rent ON (tovar_rent_items.model_id = tovar_rent.tovar_rent_id)
                WHERE item_inv_n='" . $kb['inv_n'] . "'";
		$result_item = $mysqli->query($query_item);
		if (!$result_item) {
			die('Сбой при доступе к базе данных: ' . $query_item . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}
		//search in deleted if no in acts
		if ($result_item->num_rows < 1) {
			$query_item = "SELECT model_id, item_place, model, item_size, item_rost1, item_rost2 FROM tovar_rent_items_arch
                      LEFT JOIN tovar_rent ON (tovar_rent_items_arch.model_id = tovar_rent.tovar_rent_id)
                  WHERE item_inv_n='" . $kb['inv_n'] . "'";
			$result_item = $mysqli->query($query_item);
			if ($result_item->num_rows > 0)
				$deleted_inv_n[] = $kb['inv_n'];
			if (!$result_item) {
				die('Сбой при доступе к базе данных: ' . $query_item . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
			}
		}
		$item = $result_item->fetch_assoc();
		if (!$item)
			echo $query_item;//!!!!
		$tovs[$kb['inv_n']] = $item;
	}

	if (isset($mis[$kb['inv_n']])) {
		$mi = $mis[$kb['inv_n']];
	} else {
		$m_info_q = "SELECT page_addr, l2_pic FROM rent_model_web WHERE model_id='" . $item['model_id'] . "' LIMIT 1";
		$result_m_info = $mysqli->query($m_info_q);
		if (!$result_m_info) {
			die('Сбой при доступе к базе данных: ' . $m_info_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}
		$mi_num = $result_m_info->num_rows;
		$mi = $result_m_info->fetch_assoc();
		$mis[$kb['inv_n']] = $mi;
	}


	$dl_info = '';

	if ($kb['status'] == 'ok') {
		$st_color = ' style="	background-color:#bad7f8;"';
	}
	if ($br_ch_id == $kb['kb_id']) {
		$st_color = ' style="background-color:blue"';
	}

	if ($kb['dl_link'] > 0) {
		if ($br_status == 'new') {
			continue;
		}

		//		$query_dl = "SELECT * FROM rent_deals_act WHERE deal_id='".$kb['dl_link']."'";
//
//		$result_dl = $mysqli->query($query_dl);
//		if (!$result_dl) {die('Сбой при доступе к базе данных: '.$query_dl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//		$dl_rows=$result_dl->num_rows;

		//			$query_subdl = "SELECT * FROM rent_sub_deals_act WHERE deal_id='".$kb['dl_link']."' AND `type`='payment'";
//
//			$result_subdl = $mysqli->query($query_subdl);
//			if (!$result_subdl) {die('Сбой при доступе к базе данных: '.$query_subdl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//			$subdl_rows=$result_subdl->num_rows;


		if ($subdl_rows < 1) {
			//				$query_dl = "SELECT * FROM rent_deals_arch WHERE deal_id='".$kb['dl_link']."'";
//
//				$result_dl = $mysqli->query($query_dl);
//				if (!$result_dl) {die('Сбой при доступе к базе данных: '.$query_dl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//				$dl_rows=$result_dl->num_rows;

			//				$query_subdl = "SELECT * FROM rent_sub_deals_arch WHERE deal_id='".$kb['dl_link']."' AND `type`='payment'";
//
//				$result_subdl = $mysqli->query($query_subdl);
//				if (!$result_subdl) {die('Сбой при доступе к базе данных: '.$query_subdl.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
//				$subdl_rows=$result_subdl->num_rows;
		}

		if ($kb['dl_link'] > 0) {
			//$dl=$result_dl->fetch_assoc();
			$dl_info = 'сум: ' . number_format($kb['r_paid_total'], 2, ',', ' ') . '<input type="button" value="оплата" onclick="payment_show (\'' . $kb['kb_id'] . '\'); return false;" />
			<div style="position:relative; top:-22px;">

			<form action="kb.php" method="post" style="display:none; position:absolute; background-color:green; width:400px; padding:15px;" id="pay_form_' . $kb['kb_id'] . '">
			<input type="hidden" name="deal_id" value="' . $kb['dl_link'] . '" />
			Дата оплаты: <input type="date" name="payment_date" id="payment_date" value="' . date("Y-m-d") . '" /><br />
			Сумма:<input type="number" step="0.01" name="r_paid" id="r_paid" size="10" value="" />,
			<select name="rent_payment_type" id="rent_payment_type" >
					<option value="nal_no_cheque">нал без чека</option>
					<option value="nal_cheque">нал с чеком</option>
					<option value="card">карточка</option>
					<option value="bank">банк</option>
			</select><br />
			<input type="submit" name="action" value="сохранить оплату" /><input type="button" value="отмена" onclick="payment_show(\'' . $kb['kb_id'] . '\')" />


			</form>


			</div>



			';
			if ($kb['vidacha'] > 0 && $kb['vozvrat'] < 1) {

				$col = Collateral::getCollateralByDl($kb['dl_link']);
				if ($col) {
					//$test_br_col[$kb['kb_id']]=$col->amount;
					if ($col->amount > 0) {
						$dl_info .= '<div style="color: red; float: right;">' . number_format($col->amount, 2, ',', ' ');
					}
					if ($col->info != '') {
						$dl_info .= '<span style="color: red; float: right;">(!)</span>';
					}
				}
			}
			$st_color = ' style="background-color:#f6f19f"';
		}
		//	elseif ($dl_rows<1) {
//		$dl_info='сделок не найдено. обратитесь к разработчику';
//	}
//	else {
//		$dl_info='более 1 сделки. обратитесь к разработчику';
//	}
	}//end of link>0 if

	if ($kb['vidacha'] > 0) {
		$st_color = ' style="	background-color:#cbfaca;"';
	}
	if ($kb['vozvrat'] > 0) {
		$st_color = ' style="	background-color:#c8ccc7;"';
	}

	//if there where saved changes
	$kb_hist_array = array('from' => '', 'to' => '', 'who' => '');
	if ($kb['payment_bank'] > 0) {
		$kb_hist_array = KBronForm::getChangesArray($kb['kb_id']);
		//var_dump($kb_hist_array);
	}
	echo '
	<tr ' . $st_color . '>
		<td ' . ($kb['cl_id'] == 111 ? 'style="background-color: red;"' : '') . '><span style="color:red;">' . mb_substr($kb['br_num'], 0, 3) . '-' . mb_substr($kb['br_num'], 3) . '</span><br /><i>' . date("d.m (H:i)", $kb['cr_time']) . '</i></td>
		<td style="position:relative;">
		    <div style="display:none;" class="br_pic2" id="pic_' . $kb['kb_id'] . '">
		      <a href="' . $mi['page_addr'] . '" target="blank"><img id="kb_pic_' . $kb['kb_id'] . '" src="" /></a>
		      <input type="button" class="pic_cl_but" value="X" onclick="show_pic(\'' . $kb['kb_id'] . '\', \'0\'); return false;" />
        </div>


				' . (in_array($kb['inv_n'], $deleted_inv_n) ? '<span style="color:red;">удален!</span>' : '') . '<span data-invn="' . $kb['inv_n'] . '" class="inv_ns">' . inv_print($kb['inv_n']) . '</span> <span style="color:' . ($item['item_place'] == '1' ? '#090;' : 'orange;') . '"> [оф: ' . $item['item_place'] . ']</span><br /><a href="#" onclick="show_pic(\'' . $kb['kb_id'] . '\', \'' . $mi['l2_pic'] . '\'); return false;" target="blank">' . $item['model'] . '</a> (' . $item['item_size'] . ' / ' . $item['item_rost1'] . '-' . $item['item_rost2'] . 'см.)<br />
			</td>
		<td>' . date("d.m (H:i)", $kb['t_from']) . '<br />' . rus_day(date("w", $kb['t_from'])) . $kb_hist_array['from'];
	if ($kb['payment_k1'] > 0) {
		echo '<br><span style="font-size: 25px;color: green;">+</span><span style="color: green; font-style: italic;"> ' . User::GetUserName($kb['payment_k2']) . '</span>';
	}
	echo '
		    <div id="from_div_' . $kb['kb_id'] . '" style="display: none;">
                <input form="br_appr_' . $kb['kb_id'] . '" type="date" name="br_date_from" id="br_date_from_' . $kb['kb_id'] . '" value="' . date("Y-m-d", $kb['t_from']) . '" style="display:none;" /><br />
                <select form="br_appr_' . $kb['kb_id'] . '" name="br_hour_from" id="br_hour_from_' . $kb['kb_id'] . '" style="display:none;">
                    <option value="10"' . sel_d('10', date("H", $kb['t_from'])) . '>10</option>
                    <option value="11" ' . sel_d('11', date("H", $kb['t_from'])) . '>11</option>
                    <option value="12" ' . sel_d('12', date("H", $kb['t_from'])) . '>12</option>
                    <option value="13" ' . sel_d('13', date("H", $kb['t_from'])) . '>13</option>
                    <option value="14" ' . sel_d('14', date("H", $kb['t_from'])) . '>14</option>
                    <option value="15" ' . sel_d('15', date("H", $kb['t_from'])) . '>15</option>
                    <option value="16" ' . sel_d('16', date("H", $kb['t_from'])) . '>16</option>
                    <option value="17" ' . sel_d('17', date("H", $kb['t_from'])) . '>17</option>
                    <option value="18" ' . sel_d('18', date("H", $kb['t_from'])) . '>18</option>
                    <option value="19" ' . sel_d('19', date("H", $kb['t_from'])) . '>19</option>
                    <option value="20" ' . sel_d('20', date("H", $kb['t_from'])) . '>20</option>
                </select>
                <br /><br />	<input form="prov_form_' . $kb['kb_id'] . '" type="button" value="Проверить даты" id="prov_but_' . $kb['kb_id'] . '" style="display:none; background-color:red" onclick="prov_free(\'' . $kb['kb_id'] . '\');" />
            </div>
							</td>
		<td>' . date("d.m (H:i)", $kb['t_to']) . '<br />' . rus_day(date("w", $kb['t_to'])) . $kb_hist_array['to'] . '
		    <div id="to_div_' . $kb['kb_id'] . '" style="display: none;">
                <input form="br_appr_' . $kb['kb_id'] . '" type="date" name="br_date_to" id="br_date_to_' . $kb['kb_id'] . '" value="' . date("Y-m-d", $kb['t_to']) . '" style="display:none;" /><br />
                <select form="br_appr_' . $kb['kb_id'] . '" name="br_hour_to" id="br_hour_to_' . $kb['kb_id'] . '" style="display:none;">
                    <option value="10"' . sel_d('10', date("H", $kb['t_to'])) . '>10</option>
                    <option value="11" ' . sel_d('11', date("H", $kb['t_to'])) . '>11</option>
                    <option value="12" ' . sel_d('12', date("H", $kb['t_to'])) . '>12</option>
                    <option value="13" ' . sel_d('13', date("H", $kb['t_to'])) . '>13</option>
                    <option value="14" ' . sel_d('14', date("H", $kb['t_to'])) . '>14</option>
                    <option value="15" ' . sel_d('15', date("H", $kb['t_to'])) . '>15</option>
                    <option value="16" ' . sel_d('16', date("H", $kb['t_to'])) . '>16</option>
                    <option value="17" ' . sel_d('17', date("H", $kb['t_to'])) . '>17</option>
                    <option value="18" ' . sel_d('18', date("H", $kb['t_to'])) . '>18</option>
                    <option value="19" ' . sel_d('19', date("H", $kb['t_to'])) . '>19</option>
                    <option value="20" ' . sel_d('20', date("H", $kb['t_to'])) . '>20</option>
                </select>
			</div>
			</td>
		<td>др№' . $kb['dl_link'] . ' <input form="br_appr_' . $kb['kb_id'] . '" type="text" name="dl_link" id="dl_link_' . $kb['kb_id'] . '" value="' . $kb['dl_link'] . '" size="5" style="display:none;"/>
		<input form="br_appr_' . $kb['kb_id'] . '" type="hidden" name="item_inv_n" id="item_inv_n_' . $kb['kb_id'] . '" value="' . $kb['inv_n'] . '" />
		<input form="br_appr_' . $kb['kb_id'] . '" type="hidden" name="item_inv_n" id="item_inv_n_' . $kb['kb_id'] . '" value="' . $kb['inv_n'] . '" />
		    ' . $dl_info . '</td>
		<td>' . stat_print($kb['status']) . ':' . ($kb['appr_time'] > 0 ? date("d.m (H:i)", $kb['appr_time']) . '-' . User::GetUserName($kb['appr_who']) : '') . '<br>
			' . ($kb['vidacha'] > 0 ? 'Выд:' . date("d.m (H:i)", $kb['vidacha']) . '-' . User::GetUserName($kb['vid_who_id']) . '<br>' : '');
	echo $kb_hist_array['who'];

	echo ($kb['vozvrat'] > 0 ? 'Возв:' . date("d.m (H:i)", $kb['vozvrat']) . '-' . User::GetUserName($kb['vozvr_who_id']) . '<br>' : '') . '

				</td>
		<td>' . $kb['fio'] . '</td>
		<td style="position: relative">' . (($kb['nedozvon'] > 0 && $kb['dl_link'] < 1 && $kb['appr_time'] < 1) ? '<img src="nedozvon.png" title="' . date("d.m (H:i)", $kb['nedozvon']) . '" style="position: absolute; right: 0; top: 0;">' : '') . '
		' . phone_print($kb['phone1']) . '<br />
			<input form="br_appr_' . $kb['kb_id'] . '" type="text" name="phone1" id="phone1_' . $kb['kb_id'] . '" value="' . phone_print($kb['phone1']) . '" style="display:none; width:95px;" /><br />
			' . phone_print($kb['phone2']) . '<br />
			<input form="br_appr_' . $kb['kb_id'] . '" type="text" name="phone2" id="phone2_' . $kb['kb_id'] . '" value="' . phone_print($kb['phone2']) . '" size="14" style="display:none; width:95px" />
				</td>
		<td>' . $kb['info'] . '
			<textarea form="br_appr_' . $kb['kb_id'] . '" cols="20" rows="3" name="info" id="info_' . $kb['kb_id'] . '" style="display:none;">' . good_print($kb['info']) . '</textarea>
			' . $kb['mail'] . '
				</td>
		<td>
			<form name="prov_form" target="_blank" action="kb_lines.php" method="post" id="prov_form_' . $kb['kb_id'] . '" style="display:none;">
				<input type="hidden" name="start_date" value="" id="prov_start_' . $kb['kb_id'] . '" />
				<input type="hidden" name="finish_date" value="" id="prov_finish_' . $kb['kb_id'] . '" />
				<input type="hidden" name="inv_n" value="' . $kb['inv_n'] . '" />
				<input type="hidden" name="action" value="фильтр" />
			</form>

			<form name="br_appr" action="kb.php" method="post" id="br_appr_' . $kb['kb_id'] . '" style="display:inline-block;">
				<input type="hidden" name="br_id" value="' . $kb['kb_id'] . '" />
				<input type="button" id="show_but_' . $kb['kb_id'] . '" value="подтвердить" onclick="appr_show(\'' . $kb['kb_id'] . '\')" style="padding:4px; margin:2px;" />
				<input name="action" id="subm_but_' . $kb['kb_id'] . '" type="submit" value="сохранить подтверждение" style="display:none; padding:4px; margin:2px;" onclick="return podtv_save(\'' . $kb['kb_id'] . '\')" />';

	if ($kb['vidacha'] > 0 && $kb['vozvrat'] < 1) {//if tovar vidan
		echo '';
	} else {
		echo '<input name="action" type="submit" value="удалить" onclick="return del_ch(\'' . $kb['kb_id'] . '\');" style=" padding:4px; margin:2px;" />';
	}
	echo '
				' . (($kb['appr_time'] < 1 && $kb['dl_link'] < 1) ? '<input name="action" type="submit" value="недозвон" style="background-color: red; padding:4px; margin:2px;">' : '') . '


				<br />';


	echo '

		<input type="hidden" name="arch_info" id="arch_info_' . $kb['kb_id'] . '" value="" />

		<input type="hidden" name="inv_n" value="' . $inv_n . '" />
		<input type="hidden" name="t_from" value="' . $t_from . '" />
		<input type="hidden" name="t_to" value="' . $t_to . '" />
		<input type="hidden" name="br_status" value="' . $br_status . '" />
		<input type="hidden" name="order_br" value="' . $order_br . '" />

			</form>
		<form action="/bb/dogovor_new.php" style="display:inline-block;" method="post">
			<input type="hidden" name="item_inv_n" value="' . $kb['inv_n'] . '" />
			<input type="submit" value="к договору" />
		</form>
		<form action="scanner_tovar.php" method="post">
		<input type="hidden" name="item_inv_n" value="' . $kb['inv_n'] . '" />
		';
	if ($kb['vidacha'] < 1) {
		echo '<input name="inv_n" type="submit" value="выдача" style="padding:4px; margin:2px;" />';
	} elseif ($kb['vozvrat'] < 1) {
		echo '<input name="inv_n" type="submit" value="возврат" style="padding:4px; margin:2px;" />';
	}
	echo '
       </form>
		</td>
	</tr>

			';
}
echo '</table>';

//echo '<table border="1" >';
//$total=0;
//foreach ($test_br_col as $key=>$value) {
//    $total+=$value;
//    echo '<tr>
//            <td>'.$key.'</td>
//            <td>'.number_format($value, 2, ',', ' ').'</td>
//        </tr>';
//}
//echo '<tr>
//        <td>Total:</td>
//        <td>'.(number_format($total, 2,',', ' ')).'</td>
//</tr>';
//echo '</table>';

SpeedTrack::finish();
echo SpeedTrack::getResult();

echo '</body></html>';




function get_post($var)
{
	global $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}

function rus_day($day)
{
	switch ($day) {
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


function inv_print($inv_n)
{

	$output = substr($inv_n, 0, 3) . '-' . substr($inv_n, 3);

	return $output;

}

function good_print($var)
{
	$var = htmlspecialchars(stripslashes($var));
	return $var;
}

function phone_print($ph)
{
	if ($ph == '') {
		return '';
	}

	$dl = strlen($ph);

	if ($dl < 7) {
		return $ph;
	}

	$dl > 7 ? $dl_to = $dl - 7 : $dl_to = 0;
	$ph_out = substr($ph, 0, $dl_to) . '-' . substr($ph, -7, 3) . '-' . substr($ph, -4, 2) . '-' . substr($ph, -2, 2);
	return $ph_out;

}

function phone_to_n($ph)
{
	$ph = preg_replace("|[^0-9]|i", "", $ph);
	return $ph;
}

function user_name($id)
{
	switch ($id) {
		case '1':
			return 'тестовый пользователь';
			break;

		case '2':
			return 'Кристина';
			break;

		case '3':
			return 'Дима';
			break;

		case '4':
			return 'Андрей';
			break;

		case '5':
			return 'Аня';
			break;

		case '6':
			return 'Денис';
			break;

		case '9':
			return 'Света';
			break;
		case '13':
			return 'Татьяна';
			break;
		case '12':
			return 'Alex';
			break;
		case '18':
			return 'Марго';
			break;
		case '22':
			return 'Катя';
			break;
		default:
			return 'ХЗ';
			break;
	}
}

function stat_print($stat)
{
	switch ($stat) {
		case 'new':
			return 'не проверено';
			break;

		case 'ok':
			return 'подтв';
			break;

		case 'in_process':
			return 'вр бронь';
			break;

		default:
			return 'ХЗ';
			break;
	}
}


function sel_d($value, $pattern)
{
	if ($value == $pattern) {
		return 'selected="selected"';
	} else {
		return '';
	}
}

function tonum($value)
{

	$output = floatval(str_replace(',', '.', $value));
	return $output;

}

?>

<script>

	let lnks = document.querySelectorAll('.inv_ns');
	lnks.forEach(el => {
		el.addEventListener('click', invNClick);
	});


	function invNClick(e) {
		let invN = e.currentTarget.dataset.invn;

		let form = document.createElement("form");
		form.method = "POST";
		form.action = "/bb/scanner_tovar.php";
		form.target = "_blank";
		form.classList.add('hidden_form');
		let input = document.createElement("input");
		input.name = "item_inv_n";
		input.value = invN;
		form.appendChild(input);


		document.body.appendChild(form);

		form.submit();
	}


</script>