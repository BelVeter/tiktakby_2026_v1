<?php

use bb\Base;

session_start();
ini_set('display_errors', (isset($_SESSION['svoi']) && $_SESSION['svoi'] == 8941) ? 1 : 0);
error_reporting(E_ALL);

//require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/database_new.php'); // включаем подключение к базе данных

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php'); //
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php'); //
require($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');

$mysqli = \bb\Db::getInstance()->getConnection();


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

$in_del = array(2, 3, 5, 22);


if ($_SESSION['level'] < 5) {
	$dates_readonly = 'readonly="readonly"';
} else {
	$dates_readonly = '';
}


echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
	.hide { display: none; }

	body.rx-body { margin:0; background:#eceff2; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; color:#1f2733; }
	.rx-wrap { max-width:1440px; margin:0 auto; padding:0 18px 40px; }
	.rx-wrap * { box-sizing:border-box; }

	/* шапка */
	.rx-head { display:flex; align-items:center; gap:14px; background:#fff; border-radius:14px; padding:18px 22px; margin:10px 0 16px; box-shadow:0 1px 3px rgba(20,35,60,.08); }
	.rx-head h1 { margin:0; font-size:22px; font-weight:700; letter-spacing:.2px; }
	.rx-head .rx-period { color:#8b93a7; font-size:13px; margin-left:2px; }
	.rx-add { margin-left:auto; width:44px; height:44px; flex:0 0 auto; border:none; border-radius:50%; background:#4a7dfc; color:#fff; font-size:26px; line-height:1; cursor:pointer; box-shadow:0 4px 12px rgba(74,125,252,.35); transition:transform .12s, background .12s, margin-right .12s; }
	.rx-add:hover { background:#3a6bea; transform:translateY(-1px); }
	/* плашка «заказ на обратный звонок» висит в правом верхнем углу поверх страницы
	   и накрывает «+». Пока она на экране, кнопка уходит левее; класс на body
	   ставит includes/zv_show.php. На широких экранах плашка до карточки
	   не достаёт — там сдвиг не нужен. */
	body.zv-alert .rx-add { margin-right:160px; }
	@media (min-width:1760px) { body.zv-alert .rx-add { margin-right:0; } }

	/* фильтры */
	.rx-filters { background:#fff; border-radius:14px; padding:16px 20px; margin-bottom:16px; box-shadow:0 1px 3px rgba(20,35,60,.08); }
	.rx-frow { display:flex; flex-wrap:wrap; align-items:flex-end; gap:14px; }
	.rx-frow + .rx-frow { margin-top:14px; padding-top:14px; border-top:1px solid #eef1f5; align-items:center; }
	.rx-field { display:flex; flex-direction:column; gap:5px; }
	.rx-field label { font-size:12px; font-weight:600; color:#6b7686; }
	.rx-field input[type=date], .rx-field select { height:38px; padding:0 10px; border:1px solid #dfe4ea; border-radius:9px; background:#fff; font-size:14px; color:#1f2733; }
	.rx-presets { display:flex; gap:0; border:1px solid #dfe4ea; border-radius:9px; overflow:hidden; }
	.rx-presets button { border:none; background:#fff; padding:0 14px; height:38px; font-size:13px; color:#4a5567; cursor:pointer; border-right:1px solid #eef1f5; }
	.rx-presets button:last-child { border-right:none; }
	.rx-presets button:hover { background:#f3f6fb; }
	.rx-presets button.active { background:#eef3ff; color:#2f57c9; font-weight:700; }
	.rx-show { height:38px; padding:0 26px; border:none; border-radius:9px; background:#4a7dfc; color:#fff; font-size:14px; font-weight:600; cursor:pointer; margin-left:auto; }
	.rx-show:hover { background:#3a6bea; }
	.rx-frow--sub .rx-field label { font-size:11px; }
	.rx-frow--sub select { height:34px; font-size:13px; }

	/* плитки итогов */
	.rx-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin-bottom:16px; }
	.rx-kpi { background:#fff; border-radius:14px; padding:20px 24px; box-shadow:0 1px 3px rgba(20,35,60,.08); }
	.rx-kpi-lbl { font-size:12px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; color:#8b93a7; }
	.rx-kpi-val { margin-top:10px; font-size:30px; font-weight:700; letter-spacing:-.5px; }
	.rx-kpi-val small { font-size:14px; font-weight:600; margin-left:4px; color:inherit; opacity:.75; }
	.rx-kpi-hint { margin-top:6px; font-size:12px; color:#8b93a7; }
	.rx-kpi--rash .rx-kpi-val { color:#ef4444; }
	.rx-kpi--doh  .rx-kpi-val { color:#22a06b; }
	.rx-kpi--saldo .rx-kpi-val.neg { color:#ef4444; }
	.rx-kpi--saldo .rx-kpi-val.pos { color:#22a06b; }

	/* карточки */
	.rx-cols { display:grid; grid-template-columns:minmax(0,1.55fr) minmax(0,1fr); gap:16px; align-items:start; }
	@media (max-width:1100px) { .rx-cols { grid-template-columns:1fr; } }
	.rx-card { background:#fff; border-radius:14px; box-shadow:0 1px 3px rgba(20,35,60,.08); overflow:hidden; }
	.rx-card-head { display:flex; align-items:center; gap:10px; padding:16px 20px; border-bottom:1px solid #eef1f5; }
	.rx-card-head h2 { margin:0; font-size:15px; font-weight:700; }
	.rx-badge { margin-left:auto; background:#1f2733; color:#fff; font-size:11px; font-weight:700; padding:3px 9px; border-radius:20px; }

	/* таблица операций */
	.rx-scroll { max-height:620px; overflow:auto; }
	.rx-tbl { width:100%; border-collapse:collapse; font-size:13px; }
	.rx-tbl th { position:sticky; top:0; z-index:1; background:#f7f9fc; text-align:left; font-size:11px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; color:#8b93a7; padding:8px 10px; border-bottom:1px solid #eef1f5; white-space:nowrap; }
	.rx-tbl td { padding:8px 10px; border-bottom:1px solid #f2f4f8; vertical-align:top; }
	.rx-tbl .rx-chan { white-space:nowrap; }
	.rx-tbl .rx-info { overflow-wrap:anywhere; } /* длинный комментарий переносится, а не растягивает таблицу вбок */
	.rx-tbl tr:hover td { background:#fafbfe; }
	.rx-tbl .rx-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; font-weight:600; }
	.rx-tbl .rx-date { white-space:nowrap; color:#4a5567; }
	.rx-chip { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700; background:#fdeaea; color:#d13b3b; }
	.rx-chip--doh { background:#e6f6ef; color:#178a5b; }
	.rx-chip--shift { background:#eef1f5; color:#6b7686; }
	.rx-muted { color:#8b93a7; }
	.rx-del { border:none; background:none; color:#c4ccd8; font-size:15px; cursor:pointer; padding:0 2px; }
	.rx-del:hover { color:#ef4444; }
	.rx-empty { padding:30px 20px; text-align:center; color:#8b93a7; font-size:14px; }
	.rx-foot td { background:#f7f9fc; font-weight:700; border-top:1px solid #e6eaf0; position:sticky; bottom:0; }
	.rx-edit { border:none; background:#f3f5f8; color:#6b7686; border-radius:6px; width:22px; height:22px; cursor:pointer; font-size:11px; }
	.rx-edit:hover { background:#e6eaf0; }
	.rx-editform { margin-top:8px; display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
	.rx-editform.hide { display:none; } /* .hide одной специфичности с .rx-editform и объявлен выше — без этого форма видна всегда */
	.rx-editform select, .rx-editform textarea { border:1px solid #dfe4ea; border-radius:7px; padding:5px 8px; font-size:12px; font-family:inherit; }
	.rx-editform button { border:none; border-radius:7px; background:#22a06b; color:#fff; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; }

	/* распределение по статьям */
	.rx-dist-row { display:grid; grid-template-columns:minmax(0,1fr) 100px 150px; align-items:center; gap:10px; padding:10px 14px; border-bottom:1px solid #f2f4f8; font-size:13px; }
	.rx-dist-row:last-child { border-bottom:none; }
	.rx-dist-name { display:flex; align-items:center; gap:9px; min-width:0; }
	.rx-dot { width:9px; height:9px; border-radius:50%; flex:0 0 auto; }
	.rx-dist-name span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
	.rx-dist-sum { text-align:right; font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap; }
	.rx-dist-share { display:flex; align-items:center; gap:8px; }
	.rx-dist-share em { font-style:normal; font-size:12px; color:#8b93a7; width:38px; text-align:right; flex:0 0 auto; font-variant-numeric:tabular-nums; }
	.rx-bar { flex:1; height:7px; border-radius:4px; background:#eef1f5; overflow:hidden; }
	.rx-bar i { display:block; height:100%; border-radius:4px; }
	.rx-dist-head { display:grid; grid-template-columns:minmax(0,1fr) 100px 150px; gap:10px; padding:9px 14px; background:#f7f9fc; border-bottom:1px solid #eef1f5; font-size:11px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; color:#8b93a7; }
	.rx-dist-head span:nth-child(2) { text-align:right; }

	/* сообщение */
	.rx-flash { background:#e6f6ef; color:#178a5b; border-radius:10px; padding:11px 16px; margin-bottom:14px; font-size:14px; font-weight:600; }

	/* модалка внесения операции */
	.rx-modal-bg { position:fixed; inset:0; background:rgba(18,28,45,.5); display:none; align-items:flex-start; justify-content:center; z-index:100; padding:40px 16px; overflow:auto; }
	.rx-modal-bg.open { display:flex; }
	.rx-modal { background:#fff; border-radius:16px; width:100%; max-width:560px; box-shadow:0 20px 50px rgba(15,25,45,.28); }
	.rx-modal-head { display:flex; align-items:center; padding:18px 22px; border-bottom:1px solid #eef1f5; }
	.rx-modal-head h2 { margin:0; font-size:17px; font-weight:700; }
	.rx-modal-close { margin-left:auto; border:none; background:#f3f5f8; width:32px; height:32px; border-radius:9px; font-size:18px; color:#6b7686; cursor:pointer; }
	.rx-modal-body { padding:20px 22px 22px; }
	.rx-tabs { display:flex; gap:8px; margin-bottom:18px; }
	.rx-tabs button { flex:1; height:38px; border:1px solid #dfe4ea; background:#fff; border-radius:9px; font-size:13px; font-weight:600; color:#4a5567; cursor:pointer; }
	.rx-tabs button.active { background:#4a7dfc; border-color:#4a7dfc; color:#fff; }
	.rx-mrow { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
	.rx-mrow.one { grid-template-columns:1fr; }
	.rx-mfield { display:flex; flex-direction:column; gap:5px; }
	.rx-mfield label { font-size:12px; font-weight:600; color:#6b7686; }
	.rx-mfield input, .rx-mfield select, .rx-mfield textarea { border:1px solid #dfe4ea; border-radius:9px; padding:9px 11px; font-size:14px; font-family:inherit; color:#1f2733; }
	.rx-mfield textarea { resize:vertical; min-height:70px; }
	.rx-save { width:100%; height:44px; border:none; border-radius:10px; background:#22a06b; color:#fff; font-size:15px; font-weight:700; cursor:pointer; }
	.rx-save:hover { background:#1c8b5c; }
	.rx-mhint { font-size:12px; color:#8b93a7; margin-top:10px; text-align:center; }
</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
' . Base::getBarCodeReaderScript() . '
</head>
<title>BB: Доходы-расходы.</title>
<body class="rx-body">

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> ' . $_SESSION['user_fio'] . '</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: ' . $_SESSION['office'] . '</form> </div>
<div id="zv_div"></div>
<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/rda.php">Все сделки (новые)</a>
</div>

		';
require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show.php'); // включаем подключение к звонкам

//Проверка входящей информации
//echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//foreach ($_POST as $key => $value) {
//	echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//}
//echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";


$action = '';
$flashMessage = '';
$i_from_date = date("Y-m-d");
$i_to_date = date("Y-m-d");
$item_place = 'all';   // офисного фильтра больше нет, значение осталось для совместимости форм
$type2s = 'all';
$type1_s = 'all';
$type2_s = 'all';
$kassa_s = 'all';
$t2_select = '<option value="all">все</option>';
$zp_sel_s = 'all';

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}


switch ($action) {

	case 'сохранить':

		$acc_date = strtotime($acc_date);

		if ($type1 == 'rash' || $type1 == 'shift') {
			$amount = abs($amount) * (-1);
		}
		else {
			$amount = abs($amount);
		}

		// Сейф пополняется и опустошается только переводом, а Банк и Сейф доступны
		// не всем. Проверка обязана быть на сервере: параметры приезжают из POST
		// через $$key-извлечение выше, в браузере канал подменяется правкой поля.
		$formChannels = channels_for_form($type1);
		if (!isset($formChannels[$channel])) {
			$flashMessage = 'Недопустимый канал оплаты для этой операции.';
			break;
		}
		if ($type1 == 'shift' && !isset($formChannels[$type2])) {
			$flashMessage = 'Недопустимый канал-получатель.';
			break;
		}
		if ($type1 == 'shift' && $channel == $type2) {
			$flashMessage = 'Канал-получатель должен отличаться от канала-источника.';
			break;
		}

		list($office, $kassa) = channel_to_office_kassa($channel);

		if ($type1 == 'shift') {
			//делаем расход по каналу-источнику
			$type1 = 'shift_minus';
			$ins_q = "INSERT INTO doh_rash VALUES('', '$acc_date', '$amount', '$type1', '$type2', '$office', '$kassa', '', '$info', '".time()."', '".$_SESSION['user_id']."', '$zp_name')";
			$result_ins = $mysqli->query($ins_q);
			if (!$result_ins) {
				die('Сбой при доступе к базе данных: '.$ins_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
			}
			$link_id1 = $mysqli->insert_id;

			//делаем доход по каналу-получателю
			list($office, $kassa) = channel_to_office_kassa($type2);

			$amount = abs($amount);
			$type1  = 'shift_plus';
			$ins_q = "INSERT INTO doh_rash VALUES('', '$acc_date', '$amount', '$type1', '$channel', '$office', '$kassa', '$link_id1', '$info', '".time()."', '".$_SESSION['user_id']."', '$zp_name')";
			$result_ins = $mysqli->query($ins_q);
			if (!$result_ins) {
				// MyISAM: транзакций нет, поэтому откатываем первую строку вручную,
				// иначе в кассе-источнике повиснет расход без парного прихода
				$mysqli->query("DELETE FROM doh_rash WHERE dr_id='$link_id1'");
				die('Сбой при доступе к базе данных: '.$ins_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
			}
			$link_id2 = $mysqli->insert_id;

			//обновляем линк по расходу
			$upd_q = "UPDATE doh_rash SET link_to='$link_id2' WHERE dr_id='$link_id1'";
			$result_upd = $mysqli->query($upd_q);
			if (!$result_upd) {
				// см. выше: откатываем обе строки, иначе останется пара без связи
				$mysqli->query("DELETE FROM doh_rash WHERE dr_id IN ('$link_id1', '$link_id2')");
				die('Сбой при доступе к базе данных: '.$upd_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
			}
		}
		else {
			$ins_q = "INSERT INTO doh_rash VALUES('', '$acc_date', '$amount', '$type1', '$type2', '$office', '$kassa', '', '$info', '".time()."', '".$_SESSION['user_id']."', '$zp_name')";
			$result_ins = $mysqli->query($ins_q);
			if (!$result_ins) {
				die('Сбой при доступе к базе данных: '.$ins_q.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
			}
			$link_id1 = $mysqli->insert_id;
		}

		$flashMessage = 'Операция сохранена.';

		break;

	case 'удалить':  //оплата

		if ($dr_id_link > 0) {
			$dohRash = \bb\classes\DohRash::getById($dr_id);
			if ($dohRash)
				$dohRash->logBeforeDelete();
			$dohRash = \bb\classes\DohRash::getById($dr_id_link);
			if ($dohRash)
				$dohRash->logBeforeDelete();

			$del_q = "DELETE FROM doh_rash WHERE dr_id IN ('$dr_id', '$dr_id_link')";

		} else {
			$dohRash = \bb\classes\DohRash::getById($dr_id);
			if ($dohRash)
				$dohRash->logBeforeDelete();

			$del_q = "DELETE FROM doh_rash WHERE dr_id IN ('$dr_id')";
		}
		$result_del = $mysqli->query($del_q);
		if (!$result_del) {
			die('Сбой при доступе к базе данных: ' . $del_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}

		$flashMessage = 'Операция удалена.';

		break;

	case 'update_rash':  //оплата
		$zplAdd = '';
		if ($type2 == 'zpl') {
			$zplAdd = ", dr_name_id='$zp_name' ";
		}
		$query = "UPDATE doh_rash SET type2='$type2', info='$info_upd'$zplAdd WHERE dr_id='$dr_id'";
		$result_upd = $mysqli->query($query);
		if (!$result_upd) {
			die('Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}
		break;


}//end of switch


// Офисного фильтра больше нет — фильтруем по каналу оплаты. Права и неизвестные
// коды channel_sql_filter() разбирает сама, поэтому обёрток вокруг неё не нужно.
$srch = channel_sql_filter($kassa_s);

if ($type1_s == 'doh') {
	$srch .= " AND `type1`='doh'";
} elseif ($type1_s == 'rash') {
	$srch .= " AND `type1`='rash'";
} elseif ($type1_s == 'shift') {
	$srch .= " AND `type1` LIKE 'shift%'";
}

if ($type2_s != 'all') {
	$srch .= " AND `type2`='$type2_s'";
}

if ($zp_sel_s != 'all') {
	$srch .= " AND `dr_name_id`='$zp_sel_s'";
}

// названия каналов для колонки «статья»: в переводах type2 хранит код канала,
// а не код статьи расхода
$rash = array();
foreach (channels_all() as $code => $ch) {
	$rash[$code] = $ch['text'];
}

// Коды каналов из версии с четырьмя офисами: старые переводы хранят их в type2.
// Из выпадающих списков эти точки убраны, но история по ним должна читаться.
$rash['of1k1'] = $rash['k1'];   // та же касса, что К1 — старый код канала
$rash['of1k2'] = $rash['k2'];   // та же касса, что К2
$rash['of2k1'] = 'Ложинская_1';
$rash['of2k2'] = 'Ложинская_2';
$rash['of3k1'] = 'Победителей_127_1';
$rash['of3k2'] = 'Победителей_127_2';
$rash['of4k1'] = 'Склад_1';
$rash['of4k2'] = 'Склад_2';
$rash['curk1'] = $rash['cur']; // курьерских касс было две, канал один — как в фильтре
$rash['curk2'] = $rash['cur'];

$doh = $rash;

// Статьи расходов. Флаг bank_yn раньше прятал часть статей до выбора «офиса»
// Банк; при плоском списке каналов такого состояния нет, поэтому берём весь
// список одним запросом.
$ri_q = "SELECT * FROM rash_items ORDER BY ri_order";
$result_ri = $mysqli->query($ri_q);
if (!$result_ri) {
	die('Сбой при доступе к базе данных: ' . $ri_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$rashItems = array();   // активные статьи — для формы внесения
$ri_t1     = '';        // те же статьи готовым html — для формы правки в таблице
$ri_t1_s   = '';        // все статьи, включая выключенные — для фильтра
while ($ri_def = $result_ri->fetch_assoc()) {
	if (($ri_def['is_active'] ?? 1) == 1) {
		$rashItems[$ri_def['ri_code']] = $ri_def['ri_text'];
		$ri_t1 .= '<option value="' . $ri_def['ri_code'] . '">' . $ri_def['ri_text'] . '</option>';
	}
	$ri_t1_s .= '<option value="' . $ri_def['ri_code'] . '" ' . sel_d($ri_def['ri_code'], $type2_s) . '>' . $ri_def['ri_text'] . '</option>';
	$rash[$ri_def['ri_code']] = $ri_def['ri_text'];
}

//формируем перечень доходов
$rd_q = "SELECT * FROM doh_items WHERE bank_yn!=1 ORDER BY rd_order";
$result_rd = $mysqli->query($rd_q);
if (!$result_rd) {
	die('Сбой при доступе к базе данных: ' . $rd_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$dohItems = array();
$rd_t1_s  = '';
while ($rd_def = $result_rd->fetch_assoc()) {
	if (($rd_def['is_active'] ?? 1) == 1) {
		$dohItems[$rd_def['rd_code']] = $rd_def['rd_text'];
	}
	$rd_t1_s .= '<option value="' . $rd_def['rd_code'] . '" ' . sel_d($rd_def['rd_code'], $type2_s) . '>' . $rd_def['rd_text'] . '</option>';
	$doh[$rd_def['rd_code']] = $rd_def['rd_text'];
}

//формируем вывод для фильтра type2
if ($type1_s == 'doh') {
	$t2_select = '<option value="all">все</option>' . $rd_t1_s;
} elseif ($type1_s == 'rash') {
	$t2_select = '<option value="all">все</option>' . $ri_t1_s;
} elseif ($type1_s == 'shift') {
	// у переводов в type2 лежит код канала-получателя, а не статья
	$t2_select = '<option value="all">все</option>';
	foreach (channels_for_form('shift') as $code => $text) {
		$t2_select .= '<option value="' . htmlspecialchars($code) . '" ' . sel_d($code, $type2_s) . '>' . htmlspecialchars($text) . '</option>';
	}
} else {
	$t2_select = '<option value="all">все</option>';
}


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
?>

<script language="javascript">

	history.pushState(null, null, location.href);
	window.onpopstate = function (event) {
		history.go(1);
	};

	// смена типа операции сбрасывает зависимые фильтры: список статей у доходов,
	// расходов и переводов разный, а выбранная статья к новому типу не подходит
	function type1s_show() {
		document.getElementById('zp_sel_s').value = "all";
		document.getElementById('type2_s').value = "all";
		document.getElementById('srch_form').submit();
	}

	// фильтр по сотруднику имеет смысл только для зарплаты и аванса
	function zp_name_show() {
		var v = document.getElementById('type2_s').value;
		if (v !== "zpl" && v !== "avans") {
			document.getElementById('zp_sel_s').value = "all";
		}
		document.getElementById('srch_form').submit();
	}

</script>


<?php



//формируем список для фильтра по type1
$rash_select = '';
$rl_q = "SELECT * FROM rash_items ORDER BY ri_order";
$result_rl = $mysqli->query($rl_q);
if (!$result_rl) {
	die('Сбой при доступе к базе данных: ' . $rl_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
while ($rl = $result_rl->fetch_assoc()) {
	$rash_select .= '<option value="' . $rl['ri_code'] . '" ' . sel_d($rl['ri_code'], $type2_s) . '>' . $rl['ri_text'] . '</option>';
}

//формируем список людей для зарплаты
$zp_select = '';
$zp_select_s = '';
$zp_q = "SELECT * FROM logpass WHERE zp_yn='1' AND active='1' ORDER BY lp_fio";
$result_zp = $mysqli->query($zp_q);
if (!$result_zp) {
	die('Сбой при доступе к базе данных: ' . $zp_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
while ($zp = $result_zp->fetch_assoc()) {
	$zp_select .= '<option value="' . $zp['logpass_id'] . '">' . $zp['lp_fio'] . '</option>';
	$zp_select_s .= '<option value="' . $zp['logpass_id'] . '" ' . sel_d($zp['logpass_id'], $zp_sel_s) . '>' . $zp['lp_fio'] . '</option>';
}

$from_date = strtotime($i_from_date);
$to_date = strtotime($i_to_date . ' 23:59:59');

//выборка информации по доходам-расходам
$dr_q = "SELECT * FROM doh_rash WHERE (acc_date BETWEEN '" . $from_date . "' AND '" . $to_date . "')$srch ORDER BY acc_date DESC";
$result_dr = $mysqli->query($dr_q);
if (!$result_dr) {
	die('Сбой при доступе к базе данных: ' . $dr_q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$rows       = array();
$totalRash  = 0.0;   // расходы за период, положительным числом
$totalDohDr = 0.0;   // прочие доходы из doh_rash
$byItem     = array();

while ($dr = $result_dr->fetch_assoc()) {
	$rows[] = $dr;

	// переводы между каналами — не расход, а перекладывание денег
	if (strpos($dr['type1'], 'shift') === 0) continue;

	if ($dr['type1'] == 'rash' || $dr['amount'] < 0) {
		$amt = abs((float)$dr['amount']);
		$totalRash += $amt;

		$code = $dr['type2'];
		if (!isset($byItem[$code])) $byItem[$code] = 0.0;
		$byItem[$code] += $amt;
	}
	else {
		$totalDohDr += (float)$dr['amount'];
	}
}

arsort($byItem);

// Выручка за период. Методика зафиксирована в app/Http/Controllers/Mcp/CLAUDE.md:
// SUM(r_paid + delivery_paid) по UNION(rent_sub_deals_act, rent_sub_deals_arch)
// с фильтром по acc_date — дате, когда деньги реально пришли.
$totalSales = 0.0;
foreach (array('rent_sub_deals_act', 'rent_sub_deals_arch') as $tbl) {
	$q = "SELECT SUM(r_paid) AS rent, SUM(delivery_paid) AS delivery
			FROM $tbl
			WHERE acc_date BETWEEN '" . $from_date . "' AND '" . $to_date . "'";
	$res = $mysqli->query($q);
	if (!$res) {
		die('Сбой при доступе к базе данных: ' . $q . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	}
	$r = $res->fetch_assoc();
	$totalSales += (float)$r['rent'] + (float)$r['delivery'];
}

$saldo = $totalSales - $totalRash;

// палитра точек для статей в блоке распределения
$dotColors = array('#4a7dfc', '#22a06b', '#2bb8c4', '#f0b429', '#ef4444', '#8b93a7',
                   '#3f4b5b', '#e46bb2', '#6c5ce7', '#12b886', '#f97316', '#0ea5e9');
?>
<div class="rx-wrap">

	<div class="rx-head">
		<h1>Расходы</h1>
		<span class="rx-period"><?php echo date("d.m.Y", strtotime($i_from_date)); ?> — <?php echo date("d.m.Y", strtotime($i_to_date)); ?></span>
		<button type="button" class="rx-add" id="rx_add_but" title="Внести операцию">+</button>
	</div>

	<?php if ($flashMessage): ?>
		<div class="rx-flash"><?php echo htmlspecialchars($flashMessage); ?></div>
	<?php endif; ?>

	<form class="rx-filters" name="srch_form" method="post" id="srch_form" action="doh-rash.php">
		<input type="hidden" name="item_place" value="all" />

		<div class="rx-frow">
			<div class="rx-field">
				<label for="i_from_date">Дата начала</label>
				<input type="date" name="i_from_date" id="i_from_date" value="<?php echo $i_from_date; ?>" <?php echo $dates_readonly; ?> />
			</div>
			<div class="rx-field">
				<label for="i_to_date">Дата окончания</label>
				<input type="date" name="i_to_date" id="i_to_date" value="<?php echo $i_to_date; ?>" <?php echo $dates_readonly; ?> />
			</div>

			<?php if (!$dates_readonly): ?>
			<div class="rx-field">
				<label>Быстрый выбор</label>
				<div class="rx-presets">
					<button type="button" onclick="rxPreset('this_month');">Этот месяц</button>
					<button type="button" onclick="rxPreset('prev_month');">Прошлый месяц</button>
					<button type="button" onclick="rxPreset('d30');">30 дней</button>
					<button type="button" onclick="rxPreset('ytd');">С начала года</button>
				</div>
			</div>
			<?php endif; ?>

			<button type="submit" name="action" value="показать" class="rx-show">Показать</button>
		</div>

		<div class="rx-frow rx-frow--sub">
			<div class="rx-field">
				<label for="kassa_s">Канал оплаты</label>
				<select name="kassa_s" id="kassa_s" onchange="document.getElementById('srch_form').submit();">
					<option value="all" <?php echo sel_d($kassa_s, 'all'); ?>>все</option>
					<?php foreach (channels_for_form('shift') as $code => $text) {
						echo '<option value="' . htmlspecialchars($code) . '" ' . sel_d($kassa_s, $code) . '>' . htmlspecialchars($text) . '</option>';
					} ?>
				</select>
			</div>
			<div class="rx-field">
				<label for="type1_s">Тип операции</label>
				<select name="type1_s" id="type1_s" onchange="type1s_show();">
					<option value="all" <?php echo sel_d($type1_s, 'all'); ?>>все</option>
					<option value="rash" <?php echo sel_d($type1_s, 'rash'); ?>>расходы</option>
					<option value="doh" <?php echo sel_d($type1_s, 'doh'); ?>>доходы</option>
					<option value="shift" <?php echo sel_d($type1_s, 'shift'); ?>>переводы</option>
				</select>
			</div>
			<div class="rx-field">
				<label for="type2_s">Статья</label>
				<select name="type2_s" id="type2_s" onchange="zp_name_show();">
					<?php echo $t2_select; ?>
				</select>
			</div>
			<div class="rx-field" id="zp_sel_span" <?php echo (($type2_s == 'zpl' || $type2_s == 'avans') ? '' : 'style="display:none;"'); ?>>
				<label for="zp_sel_s">Сотрудник</label>
				<select name="zp_sel_s" id="zp_sel_s" onchange="document.getElementById('srch_form').submit();">
					<option value="all">все сотрудники</option>
					<?php echo $zp_select_s; ?>
				</select>
			</div>
		</div>
	</form>

	<div class="rx-kpis">
		<div class="rx-kpi rx-kpi--rash">
			<div class="rx-kpi-lbl">Общие расходы</div>
			<div class="rx-kpi-val"><?php echo number_format($totalRash, 2, ',', ' '); ?><small>BYN</small></div>
			<div class="rx-kpi-hint">без переводов между каналами</div>
		</div>
		<div class="rx-kpi rx-kpi--doh">
			<div class="rx-kpi-lbl">Доходы за период</div>
			<div class="rx-kpi-val"><?php echo number_format($totalSales, 2, ',', ' '); ?><small>BYN</small></div>
			<div class="rx-kpi-hint">оплаты по сделкам за период<?php
				echo $totalDohDr > 0 ? ' · прочие доходы: ' . number_format($totalDohDr, 2, ',', ' ') . ' BYN' : ''; ?></div>
		</div>
		<div class="rx-kpi rx-kpi--saldo">
			<div class="rx-kpi-lbl">Сальдо</div>
			<div class="rx-kpi-val <?php echo $saldo < 0 ? 'neg' : 'pos'; ?>"><?php echo number_format($saldo, 2, ',', ' '); ?><small>BYN</small></div>
			<div class="rx-kpi-hint">доходы минус расходы</div>
		</div>
	</div>

	<div class="rx-cols">

		<section class="rx-card">
			<div class="rx-card-head">
				<h2>Детализация операций</h2>
				<span class="rx-badge"><?php echo count($rows); ?> зап.</span>
			</div>

			<?php if (count($rows) == 0): ?>
				<div class="rx-empty">За выбранный период операций нет</div>
			<?php else: ?>
			<div class="rx-scroll">
				<table class="rx-tbl">
					<thead>
						<tr>
							<th>Дата</th>
							<th>Статья</th>
							<th>Канал оплаты</th>
							<th>Комментарий</th>
							<th>Кто внёс</th>
							<th style="text-align:right">Сумма</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php
					$total_am = 0;
					foreach ($rows as $dr):
						$total_am += $dr['amount'];

						$isShift = (strpos($dr['type1'], 'shift') === 0);
						$isDoh   = (!$isShift && $dr['amount'] >= 0);

						if ($isShift) {
							$itemText  = ($dr['type1'] == 'shift_minus' ? 'перевод в: ' : 'поступление из: ')
								. (isset($rash[$dr['type2']]) ? $rash[$dr['type2']] : $dr['type2']);
							$chipClass = 'rx-chip--shift';
						}
						elseif ($isDoh) {
							$itemText  = isset($doh[$dr['type2']]) ? $doh[$dr['type2']] : $dr['type2'];
							$chipClass = 'rx-chip--doh';
						}
						else {
							$itemText  = isset($rash[$dr['type2']]) ? $rash[$dr['type2']] : $dr['type2'];
							$chipClass = '';
						}

						$canEdit = (\bb\models\User::getCurrentUser()->isOwner() && $dr['type1'] == 'rash');
					?>
						<tr>
							<td class="rx-date"><?php echo date("d.m.Y", $dr['acc_date']); ?><?php
								echo \bb\models\User::getCurrentUser()->isDima()
									? '<br /><span class="rx-muted" style="font-size:10px">[' . $dr['dr_id'] . ']</span>' : ''; ?></td>

							<td data-type2="<?php echo htmlspecialchars($dr['type2']); ?>" data-salary_user_id="<?php echo $dr['dr_name_id']; ?>">
								<span class="rx-chip <?php echo $chipClass; ?>"><?php echo htmlspecialchars($itemText); ?></span>
								<?php echo $dr['dr_name_id'] > 0
									? '<div class="rx-muted" style="font-size:11px; margin-top:3px;">' . htmlspecialchars(\bb\models\User::GetUserName($dr['dr_name_id'])) . '</div>' : ''; ?>

								<input type="button" class="rx-edit edit-btn-show <?php echo $canEdit ? '' : 'hide'; ?>" value="i" title="исправить статью и комментарий">

								<form method="post" class="rx-editform hide" id="update_form_<?php echo $dr['dr_id']; ?>" action="doh-rash.php">
									<select name="type2" class="type2_update">
										<option value="0">не выбрано</option>
										<?php echo $ri_t1; ?>
									</select>
									<select name="zp_name" class="zp_name_id_update <?php echo ($dr['type2'] == 'zpl' ? '' : 'hide'); ?>">
										<option value="0">не выбрано</option>
										<?php echo $zp_select; ?>
									</select>
									<input type="hidden" name="dr_id" value="<?php echo $dr['dr_id']; ?>" />
									<input type="hidden" name="i_from_date" value="<?php echo $i_from_date; ?>" />
									<input type="hidden" name="i_to_date" value="<?php echo $i_to_date; ?>" />
									<input type="hidden" name="item_place" value="all" />
									<input type="hidden" name="kassa_s" value="<?php echo htmlspecialchars($kassa_s); ?>" />
									<input type="hidden" name="type1_s" value="<?php echo htmlspecialchars($type1_s); ?>" />
									<input type="hidden" name="type2_s" value="<?php echo htmlspecialchars($type2_s); ?>" />
									<input type="hidden" name="action" value="update_rash" />
									<button class="correct-btn">исправить</button>
								</form>
							</td>

							<td class="rx-muted rx-chan"><?php echo htmlspecialchars(channel_print($dr['channel'], $dr['kassa'])); ?></td>

							<td class="rx-info">
								<?php echo htmlspecialchars($dr['info']); ?>
								<textarea form="update_form_<?php echo $dr['dr_id']; ?>" name="info_upd" class="info_upd hide"><?php echo htmlspecialchars($dr['info']); ?></textarea>
							</td>

							<td class="rx-muted" style="font-size:11px; white-space:nowrap;">
								<?php echo htmlspecialchars(user_name($dr['cr_who_id'])); ?><br /><?php echo date("H:i", $dr['cr_time']); ?>
							</td>

							<td class="rx-num" style="color:<?php echo $dr['amount'] < 0 ? '#d13b3b' : '#178a5b'; ?>">
								<?php echo number_format($dr['amount'], 2, ',', ' '); ?>
							</td>

							<td>
								<?php if (in_array($_SESSION['user_id'], $in_del)): ?>
								<form name="del_form_<?php echo $dr['dr_id']; ?>" method="post" id="del_form_<?php echo $dr['dr_id']; ?>" action="doh-rash.php" style="margin:0">
									<input type="hidden" name="dr_id" value="<?php echo $dr['dr_id']; ?>" />
									<input type="hidden" name="dr_id_link" value="<?php echo $dr['link_to']; ?>" />
									<input type="hidden" name="i_from_date" value="<?php echo $i_from_date; ?>" />
									<input type="hidden" name="i_to_date" value="<?php echo $i_to_date; ?>" />
									<input type="hidden" name="item_place" value="all" />
									<input type="hidden" name="kassa_s" value="<?php echo htmlspecialchars($kassa_s); ?>" />
									<input type="hidden" name="type1_s" value="<?php echo htmlspecialchars($type1_s); ?>" />
									<input type="hidden" name="type2_s" value="<?php echo htmlspecialchars($type2_s); ?>" />
									<button type="submit" name="action" value="удалить" class="rx-del" title="удалить операцию"
											onclick="return confirm('Вы точно хотите удалить эту операцию?');">✕</button>
								</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr class="rx-foot">
							<td colspan="5">Итого по отфильтрованным операциям</td>
							<td class="rx-num"><?php echo number_format($total_am, 2, ',', ' '); ?></td>
							<td></td>
						</tr>
					</tfoot>
				</table>
			</div>
			<?php endif; ?>
		</section>

		<section class="rx-card">
			<div class="rx-card-head">
				<h2>Распределение расходов по статьям</h2>
			</div>

			<?php if (count($byItem) == 0): ?>
				<div class="rx-empty">Расходов за период нет</div>
			<?php else: ?>
				<div class="rx-dist-head">
					<span>Статья расходов</span>
					<span>Сумма</span>
					<span>Доля в расходах</span>
				</div>
				<?php $i = 0; foreach ($byItem as $code => $sum):
					$share = $totalRash > 0 ? ($sum / $totalRash * 100) : 0;
					$color = $dotColors[$i % count($dotColors)];
					$i++;
					$name  = isset($rash[$code]) ? $rash[$code] : $code;
				?>
					<div class="rx-dist-row">
						<div class="rx-dist-name">
							<span class="rx-dot" style="background:<?php echo $color; ?>"></span>
							<span title="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></span>
						</div>
						<div class="rx-dist-sum"><?php echo number_format($sum, 2, ',', ' '); ?></div>
						<div class="rx-dist-share">
							<em><?php echo number_format($share, 1, ',', ' '); ?>%</em>
							<span class="rx-bar"><i style="width:<?php echo max(1, round($share)); ?>%; background:<?php echo $color; ?>"></i></span>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>

	</div>
</div>

<script>
	// быстрый выбор периода: проставляем даты и отправляем форму фильтров
	function rxPreset(kind) {
		var now  = new Date();
		var from = new Date();
		var to   = new Date();

		if (kind === 'this_month')      { from = new Date(now.getFullYear(), now.getMonth(), 1); }
		else if (kind === 'prev_month') { from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
		                                  to   = new Date(now.getFullYear(), now.getMonth(), 0); }
		else if (kind === 'd30')        { from.setDate(from.getDate() - 29); }
		else if (kind === 'ytd')        { from = new Date(now.getFullYear(), 0, 1); }

		function fmt(d) {
			var m = String(d.getMonth() + 1).padStart(2, '0');
			var t = String(d.getDate()).padStart(2, '0');
			return d.getFullYear() + '-' + m + '-' + t;
		}

		document.getElementById('i_from_date').value = fmt(from);
		document.getElementById('i_to_date').value   = fmt(to);
		document.getElementById('srch_form').submit();
	}
</script>

<!-- ------------------------------------------------- модалка внесения операции -->
<div class="rx-modal-bg" id="rx_modal">
	<div class="rx-modal">
		<div class="rx-modal-head">
			<h2 id="rx_modal_title">Внести расход</h2>
			<button type="button" class="rx-modal-close" id="rx_modal_close">✕</button>
		</div>
		<div class="rx-modal-body">
			<form name="new_rash" method="post" action="doh-rash.php" id="new_rash" onsubmit="return rxValidate();">
				<input type="hidden" name="type1" id="type1" value="rash" />
				<input type="hidden" name="i_from_date" value="<?php echo $i_from_date; ?>" />
				<input type="hidden" name="i_to_date" value="<?php echo $i_to_date; ?>" />
				<input type="hidden" name="item_place" value="all" />

				<div class="rx-tabs">
					<button type="button" class="active" data-type1="rash"  onclick="rxSetType1('rash');">Расход</button>
					<button type="button"                data-type1="doh"   onclick="rxSetType1('doh');">Доход</button>
					<button type="button"                data-type1="shift" onclick="rxSetType1('shift');">Сдача в кассу</button>
				</div>

				<div class="rx-mrow">
					<div class="rx-mfield">
						<label for="acc_date">Дата</label>
						<input type="date" name="acc_date" id="acc_date" value="<?php echo date("Y-m-d"); ?>" />
					</div>
					<div class="rx-mfield">
						<label for="amount">Сумма, BYN</label>
						<input type="number" step="0.01" name="amount" id="amount" value="" placeholder="0,00" />
					</div>
				</div>

				<div class="rx-mrow">
					<div class="rx-mfield">
						<label for="channel" id="channel_label">Канал оплаты</label>
						<select name="channel" id="channel">
							<option value="0">не выбрано</option>
						</select>
					</div>
					<div class="rx-mfield">
						<label for="type2" id="type2_label">Статья расхода</label>
						<select name="type2" id="type2">
							<option value="0">не выбрано</option>
						</select>
					</div>
				</div>

				<div class="rx-mrow one" id="rx_zp_wrap" style="display:none;">
					<div class="rx-mfield">
						<label for="zp_name">Кому (сотрудник)</label>
						<select name="zp_name" id="zp_name">
							<option value="0">не выбрано</option>
							<?php echo $zp_select; ?>
						</select>
					</div>
				</div>

				<div class="rx-mrow one">
					<div class="rx-mfield">
						<label for="info">Комментарий</label>
						<textarea name="info" id="info" placeholder="за что платим"></textarea>
					</div>
				</div>

				<button type="submit" name="action" value="сохранить" class="rx-save">Сохранить</button>
				<div class="rx-mhint">для зарплаты и аванса обязательны сотрудник и комментарий</div>
			</form>
		</div>
	</div>
</div>

<script>
	// Статьи и каналы приходят с сервера. Каналы отличаются по вкладкам: Сейф
	// доступен только в переводах, Банк и Сейф — только сотрудникам с правами.
	var RX_ITEMS = {
		rash:  <?php echo json_encode($rashItems, JSON_UNESCAPED_UNICODE); ?>,
		doh:   <?php echo json_encode($dohItems,  JSON_UNESCAPED_UNICODE); ?>,
		shift: <?php echo json_encode(channels_for_form('shift'), JSON_UNESCAPED_UNICODE); ?>
	};

	var RX_CHANNELS = {
		rash:  <?php echo json_encode(channels_for_form('rash'),  JSON_UNESCAPED_UNICODE); ?>,
		doh:   <?php echo json_encode(channels_for_form('doh'),   JSON_UNESCAPED_UNICODE); ?>,
		shift: <?php echo json_encode(channels_for_form('shift'), JSON_UNESCAPED_UNICODE); ?>
	};

	var rxModal = document.getElementById('rx_modal');
	document.getElementById('rx_add_but').onclick     = function () { rxModal.classList.add('open'); };
	document.getElementById('rx_modal_close').onclick = function () { rxModal.classList.remove('open'); };
	rxModal.onclick = function (e) { if (e.target === rxModal) rxModal.classList.remove('open'); };

	function rxFill(selectId, map) {
		var sel  = document.getElementById(selectId);
		var prev = sel.value;

		sel.innerHTML = '<option value="0">не выбрано</option>';
		for (var code in map) {
			if (!map.hasOwnProperty(code)) continue;
			var o = document.createElement('option');
			o.value = code;
			o.text  = map[code];
			sel.appendChild(o);
		}
		if (map.hasOwnProperty(prev)) sel.value = prev;
	}

	function rxSetType1(t1) {
		document.getElementById('type1').value = t1;

		var tabs = document.querySelectorAll('.rx-tabs button');
		for (var i = 0; i < tabs.length; i++) {
			tabs[i].className = (tabs[i].getAttribute('data-type1') === t1) ? 'active' : '';
		}

		document.getElementById('rx_modal_title').innerText =
			t1 === 'doh' ? 'Внести доход' : (t1 === 'shift' ? 'Сдача в кассу' : 'Внести расход');
		document.getElementById('channel_label').innerText =
			t1 === 'shift' ? 'Откуда' : 'Канал оплаты';
		document.getElementById('type2_label').innerText =
			t1 === 'doh' ? 'Статья дохода' : (t1 === 'shift' ? 'Куда' : 'Статья расхода');

		rxFill('channel', RX_CHANNELS[t1] || RX_CHANNELS.rash);
		rxFill('type2',   RX_ITEMS[t1]    || RX_ITEMS.rash);
		rxZpToggle();
	}

	// «кому» показываем только для зарплаты и аванса
	function rxZpToggle() {
		var v    = document.getElementById('type2').value;
		var show = (v === 'zpl' || v === 'avans');
		document.getElementById('rx_zp_wrap').style.display = show ? '' : 'none';
		if (!show) document.getElementById('zp_name').value = '0';
	}
	document.getElementById('type2').onchange = rxZpToggle;

	function rxValidate() {
		var errors  = [];
		var t1      = document.getElementById('type1').value;
		var channel = document.getElementById('channel').value;
		var t2      = document.getElementById('type2').value;

		if (channel === '0') errors.push(t1 === 'shift' ? 'выберите канал-источник' : 'выберите канал оплаты');
		if (t2 === '0')      errors.push(t1 === 'shift' ? 'выберите канал-получатель' : 'выберите статью');

		// перевод сам в себя денег не двигает — это всегда ошибка оператора
		if (t1 === 'shift' && channel === t2) {
			errors.push('канал-получатель должен отличаться от канала-источника');
		}

		var amount = document.getElementById('amount').value;
		if (amount === '' || parseFloat(amount) === 0) errors.push('заполните сумму');

		if ((t2 === 'zpl' || t2 === 'avans') && document.getElementById('zp_name').value === '0') {
			errors.push('выберите сотрудника');
		}
		if (t2 === 'zpl' && document.getElementById('info').value === '') {
			errors.push('для зарплаты обязателен комментарий');
		}

		var today = new Date(); today.setHours(23, 59, 59, 0);
		var accDate = new Date(document.getElementById('acc_date').value);
		if (accDate > today) errors.push('дата платежа не может быть в будущем');

		if (errors.length > 0) {
			alert('Заполните форму: ' + errors.join(', ') + '.');
			return false;
		}
		return true;
	}

	rxSetType1('rash');
</script>

<?php

?>

<script>
	document.querySelectorAll('.edit-btn-show').forEach((el) => {
		el.addEventListener('click', showHideEditFunctionality);
	});

	document.querySelectorAll('.correct-btn').forEach((el) => {
		el.addEventListener('click', correctSubmit);
	});

	document.querySelectorAll('.type2_update').forEach((el) => {
		el.addEventListener('change', updateType2Change);
	});


	//update rashod functionality
	function showHideEditFunctionality(e) {
		let btn = e.target;
		let td = e.target.closest('td');
		let form = e.target.closest('td').querySelector('form');
		let infoTextArea = e.target.closest('tr').querySelector('.info_upd');
		let type2 = td.dataset.type2;
		let salaryUserId = td.dataset.salary_user_id;
		let selectRash = td.querySelector('.type2_update');
		let selectZpId = td.querySelector('.zp_name_id_update');

		if (btn.value == 'i') {
			btn.value = 'x';
			form.classList.remove('hide');
			infoTextArea.classList.remove('hide');
			selectRash.value = type2;
			if (type2 == 'zpl') {
				selectZpId.value = salaryUserId;
				selectZpId.classList.remove('hide');
			}
			else {
				selectZpId.classList.add('hide');
			}
		}
		else {
			btn.value = 'i';
			form.classList.add('hide');
			infoTextArea.classList.add('hide');
		}

	}

	function updateType2Change(e) {
		let td = e.target.closest('td');
		let selectRash = td.querySelector('.type2_update');
		let selectZpId = td.querySelector('.zp_name_id_update');

		if (selectRash.value == 'zpl') {
			selectZpId.classList.remove('hide');
		}
		else {
			selectZpId.classList.add('hide');
		}

	}

	function correctSubmit(e) {
		e.preventDefault();
		let rez = true;
		let message = '';
		let td = e.target.closest('td');
		let form = e.target.closest('td').querySelector('form');
		let selectRash = td.querySelector('.type2_update');
		let selectZpId = td.querySelector('.zp_name_id_update');

		if (selectRash.value == 0 || selectRash.value == '') {
			rez = false;
			message += 'Выберите новый тип расхода, ';
		}
		if (selectRash.value == 'zpl' && (selectZpId.value == 0 || (selectZpId.value == ''))) {
			rez = false;
			message += 'Выберите сотрудника по зарплате, ';
		}

		if (rez) {
			form.submit();
		}
		else {
			alert(message);
		}
	}

</script>

<?php



function get_post($var)
{
	global $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}

/**
 * Справочник каналов оплаты — единственный источник правды для формы, фильтра,
 * валидации и расшифровки истории.
 *
 * Ключ массива — код канала в интерфейсе. Поля:
 *   text        — как называется в интерфейсе
 *   channel     — что писать в doh_rash.channel
 *   kassa       — что писать в doh_rash.kassa
 *   shift_only  — канал доступен только на вкладке «сдача в кассу» (сейф)
 *   restricted  — виден только сотрудникам из channels_privileged_users()
 *   sql_kassa   — фильтровать ли по кассе; у курьера false, потому что исторических
 *                 касс было две и обе должны остаться видимы
 *
 * Кодировка channel/kassa унаследована от версии с четырьмя офисами и намеренно
 * не меняется: на неё опираются дневные остатки (bb/models/KassaOstatok.php) и
 * write-API /finance/entries. Плоский список — только в интерфейсе.
 *
 * bb/models/KassaChannel.php намеренно не используется: он построен на ролях,
 * а не на списке id, не знает про safe, и его getDbChannelValue() не возвращает
 * значения.
 *
 * @return array
 */
function channels_all()
{
	return array(
		'k1'   => array('text' => 'К1',     'channel' => '1',    'kassa' => 'k1',   'shift_only' => false, 'restricted' => false, 'sql_kassa' => true),
		'k2'   => array('text' => 'К2',     'channel' => '1',    'kassa' => 'k2',   'shift_only' => false, 'restricted' => false, 'sql_kassa' => true),
		'bank' => array('text' => 'Банк',   'channel' => 'bank', 'kassa' => 'bank', 'shift_only' => false, 'restricted' => true,  'sql_kassa' => false),
		'safe' => array('text' => 'Сейф',   'channel' => 'safe', 'kassa' => 'safe', 'shift_only' => true,  'restricted' => true,  'sql_kassa' => false),
		'cur'  => array('text' => 'Курьер', 'channel' => 'cur',  'kassa' => 'k1',   'shift_only' => false, 'restricted' => false, 'sql_kassa' => false),
	);
}

/**
 * Кому видны Банк и Сейф. Список исторический — те же id, что были захардкожены
 * в фильтре офиса до перехода на каналы.
 *
 * @return array
 */
function channels_privileged_users()
{
	return array(2, 3, 5, 9);
}

/**
 * @return bool
 */
function channels_user_can_see_all()
{
	$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
	return in_array($uid, channels_privileged_users(), true);
}

/**
 * Каналы для выпадающего списка формы.
 *
 * @param string $type1 rash | doh | shift
 * @return array код => название
 */
function channels_for_form($type1)
{
	$canSeeAll = channels_user_can_see_all();

	$rez = array();
	foreach (channels_all() as $code => $ch) {
		if ($ch['restricted'] && !$canSeeAll) continue;
		if ($ch['shift_only'] && $type1 !== 'shift') continue;

		$rez[$code] = $ch['text'];
	}
	return $rez;
}

/**
 * Код канала → [channel, kassa] для записи в doh_rash.
 *
 * @param string $code
 * @return array
 */
function channel_to_office_kassa($code)
{
	$all = channels_all();
	if (isset($all[$code])) return array($all[$code]['channel'], $all[$code]['kassa']);

	return array('HZ', 'HZ');
}

/**
 * Код канала → условие WHERE для выборки.
 *
 * @param string $code
 * @return string
 */
function channel_sql_filter($code)
{
	$all = channels_all();

	// неизвестный код (в т.ч. мусор из POST) либо закрытый канал у сотрудника без
	// прав → безопасный минимум, а не «покажи только его»
	if ($code !== 'all'
		&& (!isset($all[$code]) || ($all[$code]['restricted'] && !channels_user_can_see_all()))) {
		$code = 'all';
	}

	if ($code === 'all') {
		if (channels_user_can_see_all()) return '';

		$hidden = array();
		foreach ($all as $ch) {
			if ($ch['restricted']) $hidden[] = "'".$ch['channel']."'";
		}
		return $hidden ? " AND `channel` NOT IN (".implode(',', $hidden).")" : '';
	}

	$ch = $all[$code];

	return $ch['sql_kassa']
		? " AND `channel`='".$ch['channel']."' AND `kassa`='".$ch['kassa']."'"
		: " AND `channel`='".$ch['channel']."'";
}

/**
 * Название канала для колонки «канал оплаты» — теми же словами, что
 * в фильтре и в форме внесения (справочник channels_all()).
 *
 * У курьера и банка касса не различается (sql_kassa=false) — точно так же,
 * как это делает фильтр. Закрытые точки (Ложинская, Победителей, Склад)
 * в справочнике не значатся, но их операции остаются в истории — для них
 * старая расшифровка.
 *
 * @param $channel
 * @param $kassa
 * @return string
 */
function channel_print($channel, $kassa)
{
	foreach (channels_all() as $ch) {
		if ($ch['channel'] == $channel && (!$ch['sql_kassa'] || $ch['kassa'] == $kassa)) {
			return $ch['text'];
		}
	}

	return of_print($channel) . kassa_print($kassa);
}

/**
 * Название офиса/канала для колонки «канал» в таблице операций.
 *
 * Знает и закрытые точки (Ложинская, Победителей, Склад) — они убраны из
 * списков, но их операции остаются в истории и должны читаться.
 *
 * @param $of
 * @return string
 */
function of_print($of)
{
	switch ($of) {
		case '1':    return 'Литературная_22_';
		case '2':    return 'Ложинская_5_';
		case '3':    return 'Победителей_127_';
		case '4':    return 'Склад_';
		case 'cur':  return 'Курьер_';
		case 'bank': return 'Банк';
		case 'safe': return 'Сейф';
		default:     return 'Нет';
	}
}

/**
 * @param $of
 * @return string
 */
function kassa_print($of)
{
	switch ($of) {
		case 'k1':   return '1';
		case 'k2':   return '2';
		case 'bank': return '';
		case 'safe': return '';
		default:     return 'Нет';
	}
}

function dr_print($of)
{

	switch ($of) {
		case 'doh':
			$output = 'доход';
			break;

		case 'rash':
			$output = 'расход';
			break;

		case 'shift_minus':
			$output = 'перевод в:';
			break;

		case 'shift_plus':
			$output = 'поступл. из:';
			break;


		default:
			$output = 'Нет';
			break;
	}

	return $output;

}

function sel_d($value, $pattern)
{
	if ($value == $pattern) {
		return 'selected="selected"';
	} else {
		return '';
	}
}

function user_name($id)
{
	return \bb\models\User::getUserById($id)->getShortName();
}

?>