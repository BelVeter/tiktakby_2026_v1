<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Base.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Task.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/classes/Model.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); // включаем подключение к базе данных
require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

//------- proverka paroley

\bb\Base::loginCheck();

//-----------proverka paroley

$action='';
$limit=50;
$message='';

// ── A1 ВАТС: обработка действий ──────────────────────────────────────────────
if (isset($_POST['a1_action']) && in_array($_POST['a1_action'], ['called_back', 'false_call'], true)) {
    $a1Db    = \bb\Db::getInstance()->getConnection();
    $a1Id    = (int)($_POST['a1_id'] ?? 0);
    $a1Type  = $_POST['a1_action'];
    $a1Fio   = $_SESSION['user_fio'] ?? 'unknown';
    $a1Uid   = (int)($_SESSION['user_id'] ?? 0);
    if ($a1Id > 0) {
        $a1Stmt = $a1Db->prepare(
            "UPDATE a1_missed_calls SET action_type=?, action_user_id=?, action_user_name=?, action_at=NOW() WHERE id=? AND action_type='none'"
        );
        $a1Stmt->bind_param('ssis', $a1Type, $a1Uid, $a1Fio, $a1Id);
        $a1Stmt->execute();
        $a1Stmt->close();
    }
    header('Location: /bb/zv_ch.php');
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────


//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$mysqli = \bb\Db::getInstance()->getConnection();

foreach ($_POST as $key => $value) {
	$$key = get_post($key);
}

if ($action=='create_zayavka'){
  $z = \bb\classes\Zvonok::getById($zv_id);
  $z->info = $dop_info.' [Оформлена заявка]';
  $z->zayavkaDone();

  $m = \bb\classes\Model::getById($model_id);

  $validity = new DateTime();
    $validity->modify('+'.$days_num.' days');


  $zayavka = \bb\classes\bron::createZayavka($model_id, preg_replace("/[^0-9]/", "", $z->phone), $z->z_name, '', '', $validity, $dop_info, 1);
  if ($zayavka && $zayavka->insert_id && $zv_id) {
      (new \bb\classes\Zayavka())->linkAfterCreate((int)$zayavka->insert_id, (int)$zv_id);
  }
  $message = "Заявка оформлена";
}

if ($action=='zv_check') {
$query_zv = "SELECT zv_id FROM zvonki WHERE `status`='new'";
$result_zv = $mysqli->query($query_zv);
if (!$result_zv) {die('Сбой при доступе к базе данных: '.$query_zv.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}
$zv_n = $result_zv->num_rows;
if ($zv_n>=0) {
	echo $zv_n;
	die();
}
else {
	echo '0';
	die();
}
}//end of action if

if ($action=='a1_check') {
    $a1DbCheck = \bb\Db::getInstance()->getConnection();
    $a1ChkRes  = $a1DbCheck->query("SELECT COUNT(*) as n FROM a1_missed_calls WHERE action_type='none'");
    $count = $a1ChkRes ? (int)$a1ChkRes->fetch_assoc()['n'] : 0;
    echo $count;
    die();
}


if ($action=='звонок сделан') {
    $mysqli=\bb\Db::getInstance()->getConnection();

	$query_cl_upd = "UPDATE zvonki SET `status`='done', react_time='".time()."', person_id='".$_SESSION['user_id']."' WHERE zv_id='$zv_id'";
    $result=$mysqli->query($query_cl_upd);
    if (!$result) {die('Сбой при работе с MYSQL: '.$query_cl_upd.' ('.$mysqli->connect_errno.') '.$mysqli->connect_error);}
}

if ($action=='get_tasks_number'){
  $rez = new \stdClass();
  $count=0;
  $userId=\bb\models\User::getCurrentUser()->getId();
  //echo $userId;
  $tasks = \bb\classes\Task::getAllForUserStatus(\bb\models\User::getCurrentUser()->getId());
  if ($tasks){
    $count=count($tasks);
  }

  $rez->result='ok';
  $rez->count=$count;
//
  echo json_encode($rez);
  die();
}


echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<link href="/bb/assets/css/zvonki_bb.css" rel="stylesheet" type="text/css" />

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Звонки</title>
<body>

<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /></form> </div>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
	<a class="div_item" href="/bb/cur_page2.php">Страница курьера</a>
	<a class="div_item" href="/bb/rda.php">Все сделки (новые)</a>
	<a class="div_item" href="/bb/dogovor_new.php">Новый договор / сделка</a>
</div>
<form method="post" action="zv_ch.php" style="display:inline;">
	<input type="submit" name="action" value="показать только примерки" style="height:40px;" />
</form>

<form method="post" action="zv_ch.php" style="display:inline;">
	<input type="submit" name="action" value="показать все" style="height:40px;" />
</form>
';

// ══════════════════════════════════════════════════════════════════════════════
// A1 ВАТС — пропущенные звонки (MySQL)
// ══════════════════════════════════════════════════════════════════════════════

$a1StatusLabels = [
    'NOT_ANSWERED_COMMON'             => 'Нет ответа',
    'CANCELLED_BY_CALLER'             => 'Сброшен',
    'DENIED_DUE_TO_NOT_WORK_TIME'     => 'Вне рабочего времени',
    'DENIED_DUE_TO_MAX_SESSION'       => 'Перегрузка',
    'DENIED_DUE_TO_MAX_CHANNEL_LIMIT' => 'Перегрузка каналов',
];

$a1Db2       = \bb\Db::getInstance()->getConnection();
$a1CurUser   = \bb\models\User::getCurrentUser();
$a1IsDima    = $a1CurUser && $a1CurUser->isDima();

// Статистика для Димы
$a1DimaStats = null;
if ($a1IsDima) {
    $a1StDate  = date('Y-m-d');
    $a1StRow   = $a1Db2->query(
        "SELECT COUNT(*) AS total, SUM(action_type='called_back') AS called_back,
                SUM(action_type='false_call') AS false_call, SUM(action_type='none') AS pending,
                SUM(action_type='client_called_back') AS client_called_back
         FROM a1_missed_calls WHERE DATE(FROM_UNIXTIME(call_timestamp))='$a1StDate'"
    );
    $a1ApiRow  = $a1Db2->query(
        "SELECT COUNT(*) AS total_fetches, SUM(status='success') AS ok, SUM(status='error') AS errors
         FROM a1_api_fetch_log WHERE DATE(fetched_at)='$a1StDate'"
    );
    $a1LastErr = null;
    $a1ErrRes  = $a1Db2->query(
        "SELECT error_message, fetched_at FROM a1_api_fetch_log
         WHERE DATE(fetched_at)='$a1StDate' AND status='error' ORDER BY fetched_at DESC LIMIT 1"
    );
    if ($a1ErrRes && ($a1ErrRow = $a1ErrRes->fetch_assoc())) {
        $a1LastErr = $a1ErrRow;
    }
    if ($a1StRow && $a1ApiRow) {
        $st = $a1StRow->fetch_assoc();
        $ap = $a1ApiRow->fetch_assoc();
        $a1DimaStats = [
            'total'       => (int)$st['total'],
            'called_back' => (int)$st['called_back'],
            'false_call'  => (int)$st['false_call'],
            'pending'            => (int)$st['pending'],
            'client_called_back' => (int)$st['client_called_back'],
            'api_total'   => (int)$ap['total_fetches'],
            'api_ok'      => (int)$ap['ok'],
            'api_errors'  => (int)$ap['errors'],
            'last_error'  => $a1LastErr,
        ];
    }
}

// Считаем необработанные
$a1NewCount = 0;
$a1NcRes = $a1Db2->query("SELECT COUNT(*) as n FROM a1_missed_calls WHERE action_type='none'");
if ($a1NcRes) $a1NewCount = (int)$a1NcRes->fetch_assoc()['n'];

// Загружаем: необработанные + обработанные за последние 10 минут
$a1VisibleRes = $a1Db2->query(
    "SELECT * FROM a1_missed_calls
     WHERE action_type = 'none'
        OR (action_type != 'none' AND action_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE))
     ORDER BY call_timestamp DESC
     LIMIT 100"
);
$a1VisibleCalls = [];
while ($a1Row = $a1VisibleRes->fetch_assoc()) {
    $a1Row['crm_active_deals'] = json_decode($a1Row['crm_active_deals'] ?? '[]', true) ?: [];
    $a1VisibleCalls[] = $a1Row;
}

// Последнее обновление = последний добавленный звонок
$a1LastUpdated = null;
$a1LuRes = $a1Db2->query("SELECT MAX(created_at) as lu FROM a1_missed_calls");
if ($a1LuRow = $a1LuRes->fetch_assoc()) {
    $a1LastUpdated = $a1LuRow['lu'] ? strtotime($a1LuRow['lu']) : null;
}

$a1DefaultCollapsed = $a1NewCount === 0 ? '1' : '0';

echo '<div id="a1-calls-box" data-default-collapsed="'.$a1DefaultCollapsed.'" style="background:#f0f4fa;border:2px solid #4a90d9;border-radius:6px;padding:12px 16px;margin-bottom:18px;">';
echo '<div style="display:flex;align-items:center;gap:12px;">';
echo '<button type="button" id="a1-calls-toggle" aria-expanded="true" style="cursor:pointer;background:#ffc107;color:#000;border:1px solid #d39e00;border-radius:4px;font-size:14px;font-weight:bold;padding:4px 12px;line-height:1;display:inline-flex;align-items:center;gap:6px;box-shadow:0 1px 2px rgba(0,0,0,0.15);" title="Свернуть / развернуть"><span class="a1-toggle-arrow">▼</span><span class="a1-toggle-label">Свернуть</span></button>';
echo '<strong style="font-size:16px;">📵 Пропущенные звонки A1 ВАТС</strong>';
if ($a1NewCount > 0) {
    echo '<span style="background:#dc3545;color:#fff;padding:2px 10px;border-radius:12px;font-size:13px;font-weight:bold;">'.$a1NewCount.' не обработано</span>';
} else {
    echo '<span style="background:#198754;color:#fff;padding:2px 10px;border-radius:12px;font-size:13px;">Все обработаны</span>';
}
if ($a1LastUpdated) {
    echo '<span style="color:#666;font-size:12px;margin-left:auto;">Обновлено: '.date('d.m.Y H:i', $a1LastUpdated).'</span>';
}
echo '</div>';

// Статистика для Димы
if ($a1IsDima && $a1DimaStats) {
    $ds = $a1DimaStats;
    echo '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:10px 14px;margin-top:10px;font-size:13px;">';
    echo '<strong>📊 Сегодня ('.date('d.m').'):</strong>&nbsp;&nbsp;';
    echo 'всего <strong>'.$ds['total'].'</strong>&nbsp;&nbsp;';
    echo '<span style="color:#198754;">✓ перезвонили: <strong>'.$ds['called_back'].'</strong></span>&nbsp;&nbsp;';
    echo '<span style="color:#0dcaf0;">↩ сам перезвонил: <strong>'.$ds['client_called_back'].'</strong></span>&nbsp;&nbsp;';
    echo '<span style="color:#6c757d;">🚫 ложных: <strong>'.$ds['false_call'].'</strong></span>&nbsp;&nbsp;';
    if ($ds['pending'] > 0) {
        echo '<span style="color:#dc3545;">⏳ не обработано: <strong>'.$ds['pending'].'</strong></span>&nbsp;&nbsp;';
    }
    echo '&nbsp;|&nbsp;&nbsp;';
    if ($ds['api_total'] === 0) {
        echo '<span style="color:#888;">API: нет данных</span>';
    } else {
        echo 'API: '.$ds['api_total'].' запросов&nbsp;';
        echo '<span style="color:#198754;">✓'.$ds['api_ok'].'</span>&nbsp;';
        if ($ds['api_errors'] > 0) {
            echo '<span style="color:#dc3545;">✗'.$ds['api_errors'].'</span>';
            if ($ds['last_error']) {
                $errTime = substr($ds['last_error']['fetched_at'], 11, 5);
                $errMsg  = mb_substr($ds['last_error']['error_message'] ?? '', 0, 80);
                echo ' <span style="color:#dc3545;font-size:11px;">('.$errTime.': '.htmlspecialchars($errMsg).')</span>';
            }
        } else {
            echo '<span style="color:#198754;"> — всё OK</span>';
        }
    }
    echo '</div>';
}

echo '<div id="a1-calls-body" style="margin-top:10px;">';

if (empty($a1VisibleCalls)) {
    echo '<p style="color:#888;font-style:italic;">Нет новых пропущенных звонков.</p>';
} else {
    echo '<table border="1" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px;">';
    echo '<tr style="background:#d0e4f7;">';
    echo '<th style="width:80px;padding:5px;">Дата / Время</th>';
    echo '<th style="padding:5px;">Номер звонящего</th>';
    echo '<th style="padding:5px;">Статус</th>';
    echo '<th style="padding:5px;">Клиент / Аренды</th>';
    echo '<th style="width:150px;padding:5px;">Обработка</th>';
    echo '</tr>';

    foreach ($a1VisibleCalls as $call) {
        $callId      = (int)$call['id'];
        $actionType  = $call['action_type'] ?? 'none';
        $isPending      = ($actionType === 'none');
        $isCalledBack   = ($actionType === 'called_back');
        $isAutoCallback = ($actionType === 'client_called_back');
        $caller      = $call['caller_number']  ?? '—';
        $callTs      = $call['call_timestamp'] ?? 0;
        $status      = $call['call_status']    ?? '';
        $crmFio      = $call['crm_client_fio'] ?? null;
        $activeDeals = $call['crm_active_deals'];
        $lastReturn  = $call['crm_last_return']  ?? null;
        $totalDeals  = $call['crm_total_deals']  ?? 0;
        $statusLabel = $a1StatusLabels[$status] ?? $status;

        if ($isCalledBack)        { $rowBg = '#f6fff6'; $borderLeft = 'border-left:4px solid #198754;'; }
        elseif ($isAutoCallback)  { $rowBg = '#f0fbff'; $borderLeft = 'border-left:4px solid #0dcaf0;'; }
        elseif (!$isPending)      { $rowBg = '#f8f9fa'; $borderLeft = 'border-left:4px solid #6c757d;'; }
        else                      { $rowBg = '#fff8dc'; $borderLeft = 'border-left:4px solid #dc3545;'; }

        $rowAttrs = ' class="a1-call-row"';
        if (!$isPending && $call['action_at']) {
            $rowAttrs .= ' data-processed-ts="'.strtotime($call['action_at']).'"';
        }
        echo '<tr'.$rowAttrs.' style="background:'.$rowBg.';'.$borderLeft.'">';

        echo '<td style="padding:5px;text-align:center;">';
        echo ($callTs ? date('d.m.y', $callTs).'<br>'.date('H:i', $callTs) : '—');
        echo '</td>';

        echo '<td style="padding:5px;font-weight:bold;">';
        echo htmlspecialchars(\bb\Base::formatPhone($caller));
        echo '</td>';

        echo '<td style="padding:5px;color:#555;">'.htmlspecialchars($statusLabel).'</td>';

        echo '<td style="padding:5px;">';
        if ($crmFio) {
            echo '<strong>'.htmlspecialchars($crmFio).'</strong>';
            if ($totalDeals > 0) echo ' <span style="color:#888;font-size:11px;">('.$totalDeals.' сд.)</span>';
            if (!empty($activeDeals)) {
                echo '<div style="color:#0a5c36;margin-top:3px;">';
                foreach ($activeDeals as $deal) {
                    echo '📦 '.htmlspecialchars($deal['model'] ?? '');
                    echo ' <span style="color:#888;">'.htmlspecialchars($deal['rented_from'] ?? '').'–'.htmlspecialchars($deal['return_due'] ?? '').'</span><br>';
                }
                echo '</div>';
            } elseif ($lastReturn) {
                echo '<div style="color:#888;font-size:12px;margin-top:2px;">возврат: '.$lastReturn.'</div>';
            } else {
                echo '<div style="color:#bbb;font-size:12px;">нет аренд</div>';
            }
        } else {
            echo '<span style="color:#aaa;font-style:italic;">не в базе</span>';
        }
        echo '</td>';

        echo '<td style="padding:5px;text-align:center;">';
        if ($isPending) {
            echo '<form method="post" action="zv_ch.php" style="margin:0;display:flex;flex-direction:column;gap:4px;">';
            echo '<input type="hidden" name="a1_id" value="'.$callId.'">';
            echo '<button type="submit" name="a1_action" value="called_back" style="cursor:pointer;padding:3px 8px;background:#198754;color:#fff;border:none;border-radius:4px;" onclick="return confirm(\'Перезвонили?\')">Перезвонили</button> ';
            echo '<button type="submit" name="a1_action" value="false_call"  style="cursor:pointer;padding:3px 8px;background:#6c757d;color:#fff;border:none;border-radius:4px;" onclick="return confirm(\'Ложный вызов?\')">Ложный вызов</button>';
            echo '</form>';
        } elseif ($isAutoCallback) {
            $cbMin = (int)($call['callback_minutes'] ?? 0);
            $cbLabel = $cbMin >= 60 ? floor($cbMin/60).' ч '.($cbMin%60).' мин' : ($cbMin > 0 ? $cbMin.' мин' : '< 1 мин');
            echo '<span style="color:#0dcaf0;font-weight:bold;">↩ Сам перезвонил</span><br>';
            echo '<small style="color:#888;">через '.$cbLabel.'</small>';
        } elseif ($isCalledBack) {
            echo '<span style="color:#198754;font-weight:bold;">✓ Перезвонили</span><br>';
            echo '<small style="color:#888;">'.htmlspecialchars(substr($call['action_at'] ?? '', 0, 16)).'<br>'.htmlspecialchars($call['action_user_name'] ?? '').'</small>';
        } else {
            echo '<span style="color:#6c757d;">🚫 Ложный</span><br>';
            echo '<small style="color:#888;">'.htmlspecialchars(substr($call['action_at'] ?? '', 0, 16)).'<br>'.htmlspecialchars($call['action_user_name'] ?? '').'</small>';
        }
        echo '</td>';

        echo '</tr>';
    }
    echo '</table>';
}
echo '</div>'; // #a1-calls-body
echo '</div>'; // #a1-calls-box

echo '<script>
(function(){
  var box    = document.getElementById("a1-calls-box");
  if (!box) return;
  var body   = document.getElementById("a1-calls-body");
  var btn    = document.getElementById("a1-calls-toggle");
  var arrow  = btn.querySelector(".a1-toggle-arrow");
  var label  = btn.querySelector(".a1-toggle-label");
  var KEY    = "a1_calls_collapsed";
  var stored = localStorage.getItem(KEY);
  var collapsed = stored === null
    ? (box.dataset.defaultCollapsed === "1")
    : (stored === "1");

  function apply() {
    body.style.display = collapsed ? "none" : "";
    arrow.textContent  = collapsed ? "▶" : "▼";
    label.textContent  = collapsed ? "Развернуть" : "Свернуть";
    btn.setAttribute("aria-expanded", collapsed ? "false" : "true");
  }
  apply();

  btn.addEventListener("click", function(){
    collapsed = !collapsed;
    localStorage.setItem(KEY, collapsed ? "1" : "0");
    apply();
  });

  var TTL = 10 * 60 * 1000;
  document.querySelectorAll(".a1-call-row[data-processed-ts]").forEach(function(row){
    var ts = parseInt(row.dataset.processedTs, 10) * 1000;
    if (!ts) return;
    var remaining = ts + TTL - Date.now();
    if (remaining <= 0) { row.remove(); }
    else { setTimeout(function(){ row.remove(); }, remaining); }
  });
})();
</script>';

// ══════════════════════════════════════════════════════════════════════════════


if ($action=='показать только примерки') {
	//echo '1';
	$pr_today=time()-24*60*60;
	$query_zv = "SELECT * FROM zvonki WHERE tema='примерка' AND pr_time > $pr_today ORDER BY `pr_time`";
	$result_zv = $mysqli->query($query_zv);
	if (!$result_zv) die('Сбой при доступе к базе данных: '.$query_zv.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);


}
else {
	//echo '2';
	$query_zv = "SELECT z.*, ro.z_status AS zay_status FROM zvonki z LEFT JOIN rent_orders ro ON ro.order_id = z.order_id WHERE z.`status`!='arch' ORDER BY z.`status` DESC, z.cr_time DESC LIMIT $limit";
	$result_zv = $mysqli->query($query_zv);
	if (!$result_zv) die('Сбой при доступе к базе данных: '.$query_zv.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);
}


echo '
<br>
<form method="post" action="zv_ch.php" style="display:inline;">
<input type="number" step="1" min="0" name="limit" value="'.$limit.'">
	<input type="submit" name="action" value="изменить лимит" style="height:20px;" />
</form>';

if ($message != '') {
  echo '<div style="font-size: 22px; color: red; font-weight: bold">'.$message.'</div>';
}

echo '

<table border="1" cellspacing="0">
		<tr>
			<th scope="col" style="width:70px;">дата, время заказа</th>
			<th scope="col" style="width:560px;">Детали звонка</th>
			<th scope="col" style="width:120px;">Статус</th>
			<th scope="col" style="width:160px;">Действия</th>
		</tr>';

while ($zv=$result_zv->fetch_assoc()) {
  $m = \bb\classes\Model::getById($zv['model_id']);
	echo '
		<tr '.($zv['status']=='new' ? ($zv['tema']=='примерка' ? 'style="background-color:pink;"' : 'style="background-color:yellow;"') : '').' data-modelid="'.$zv['model_id'].'" data-type1="'.$zv['type1'].'" data-validity-days="'.$zv['validity_days'].'" data-zv-id="'.$zv['zv_id'].'">
			<td>'.date("d-m-y", $zv['cr_time']).'<br />'.date("H:i", $zv['cr_time']).'</td>
			<td>'.$zv['tema'].'<br />Имя: <strong>'.$zv['z_name'].'</strong>, Телефон: ('.$zv['operator'].') - '.$zv['phone'].' <br /> Доп. информация: <span class="dop_info">'.$zv['info'].'</span>
			  <div>'.($m ? $m->getFullName() : '').'</div>
			</td>
			<td>'.($zv['status']=='new' ? 'не обработан' : 'обработан '.date("d-m-y", $zv['react_time']).'<br />'.date("H:i", $zv['react_time']).' ').'</td>
			<td>
					<form method="post" action="zv_ch.php">
						<input type="hidden" name="zv_id" value="'.$zv['zv_id'].'" />
					'.($zv['status']=='new' ? '<input type="submit" name="action" value="звонок сделан" />' : '').'
					</form>
        '.(!empty($zv['order_id'])
            ? '<button type="button" class="zay_edit_btn" data-orderid="'.$zv['order_id'].'" data-zvid="'.$zv['zv_id'].'">Заявка</button>'
            : ($zv['type1']=='zayavka' ? '<button type="button" class="zay_create_btn" data-zvid="'.$zv['zv_id'].'" data-phone="'.htmlspecialchars($zv['phone']).'" data-family="'.htmlspecialchars($zv['z_name']).'" data-modelid="'.(int)$zv['model_id'].'" data-modellabel="'.($m ? htmlspecialchars($m->getFullName()) : '').'" data-crtime="'.$zv['cr_time'].'">Оформить заявку</button>' : '')).'
			</td>
		</tr>

			';
}




function get_post($var)
{
    $mysqli = \bb\Db::getInstance()->getConnection();
	return $mysqli->real_escape_string($_POST[$var]);
}

?>
</table>
<!-- Modal Background -->
<form method="post" id="modalBackground" class="modal-background">
  <!-- Modal Content -->
  <div class="modal-content">
    <span class="close-button" id="closeModalBtn">&times;</span>
    <div class="modal-header">Оформить заявку:</div>
    <div class="line2">
      <div>срок действия <input type="number" min="0" step="1" name="days_num" class="days_num" type="text" placeholder=""> дней </div>
      <div>по <input class="validity-date" min="<?= date('Y-m-d'); ?>" type="date"></div>
    </div>

    <div>
      Доп. информация:<br>
      <textarea name="dop_info" class="textarea-field" placeholder="Enter your message"></textarea>
    </div>
    <input type="hidden" class="model_id" name="model_id" value="">
    <input type="hidden" class="zv_id" name="zv_id" value="">

    <input type="hidden" name="action" value="create_zayavka">
    <button type="submit" class="submit-button" onclick="this.form.submit();">Создать заявку</button>
  </div>
</form>

<!-- Edit Zayavka popup -->
<div id="zayEditOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;">
  <div style="background:#fff;max-width:560px;margin:60px auto;padding:20px;border-radius:6px;position:relative;">
    <span id="zayEditClose" style="position:absolute;top:8px;right:14px;cursor:pointer;font-size:24px;">&times;</span>
    <h3 style="margin-top:0;">Заявка <span id="zayEditTitle"></span></h3>
    <input type="hidden" id="zayEditOrderId">
    <input type="hidden" id="zayEditChTime">
    <input type="hidden" id="zayEditZvId">
    <div id="zayEditMeta" style="font-size:13px;color:#444;margin-bottom:8px;"></div>
    <div id="zayEditHistory" style="font-size:12px;color:#666;max-height:140px;overflow:auto;border:1px solid #eee;padding:6px;margin-bottom:10px;"></div>

    <div id="zayArchivedPanel" style="display:none;background:#fff3f3;border:1px solid #f5c6cb;border-radius:4px;padding:10px;margin-bottom:10px;">
      <div id="zayArchivedMsg" style="font-size:13px;margin-bottom:10px;"></div>
      <button type="button" id="zayCreateNew" style="background:#007bff;color:#fff;border:none;padding:6px 14px;border-radius:4px;cursor:pointer;">Создать новую заявку</button>
    </div>

    <input type="hidden" id="zayNewPhone">
    <input type="hidden" id="zayNewFamily">
    <div id="zayCreatePanel" style="display:none;">
      <div style="margin-bottom:8px;font-size:13px;">Модель:
        <div style="position:relative;display:inline-block;vertical-align:middle;">
          <input type="text" id="zayNewModelSearch" placeholder="название или ID…" autocomplete="off"
                 style="width:220px;padding:3px 6px;font-size:13px;">
          <input type="hidden" id="zayNewModel">
          <div id="zayNewModelDropdown" style="display:none;position:absolute;top:100%;left:0;width:340px;background:#fff;border:1px solid #ccc;border-radius:4px;z-index:2000;max-height:220px;overflow-y:auto;box-shadow:0 3px 8px rgba(0,0,0,.15);"></div>
        </div>
      </div>
      <label style="display:block;font-size:13px;">Комментарий:<br>
        <textarea id="zayNewInfo" rows="3" style="width:100%;"></textarea>
      </label>
      <label style="display:block;font-size:13px;margin-top:8px;">Планируемая дата выдачи:
        <input type="date" id="zayNewPlanned">
      </label>
      <label style="display:block;font-size:13px;margin-top:8px;">Срок действия заявки:
        <input type="date" id="zayNewValidity">
      </label>
      <div style="margin-top:12px;">
        <button type="button" id="zayNewSubmit" style="background:#28a745;color:#fff;border:none;padding:6px 14px;border-radius:4px;cursor:pointer;">Создать заявку</button>
      </div>
    </div>

    <div id="zayEditFields">
    <label style="display:block;font-size:13px;">Комментарий (добавится в историю):<br>
      <textarea id="zayEditInfo" rows="3" style="width:100%;"></textarea>
    </label>
    <label style="display:block;font-size:13px;margin-top:8px;">Планируемая дата выдачи:
      <input type="date" id="zayEditPlanned">
    </label>
    <label style="display:block;font-size:13px;margin-top:8px;">Срок действия заявки:
      <input type="date" id="zayEditValidity">
    </label>
    <div style="margin-top:8px;font-size:13px;">Сменить модель:
      <div style="position:relative;display:inline-block;vertical-align:middle;">
        <input type="text" id="zayEditModelSearch" placeholder="название или ID…" autocomplete="off"
               style="width:220px;padding:3px 6px;font-size:13px;">
        <input type="hidden" id="zayEditModel">
        <div id="zayEditModelDropdown" style="display:none;position:absolute;top:100%;left:0;width:340px;background:#fff;border:1px solid #ccc;border-radius:4px;z-index:2000;max-height:220px;overflow-y:auto;box-shadow:0 3px 8px rgba(0,0,0,.15);"></div>
      </div>
      <button type="button" id="zayEditModelBtn">сменить</button>
    </div>
    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
      <button type="button" id="zayEditSave" style="background:#28a745;color:#fff;border:none;padding:6px 14px;border-radius:4px;cursor:pointer;">Сохранить</button>
      <button type="button" id="zayEditDelete" style="background:#dc3545;color:#fff;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;">Удалить</button>
    </div>
    <div id="zayDelPanel" style="display:none;background:#fff3f3;border:1px solid #f5c6cb;border-radius:4px;padding:10px;margin-top:10px;">
      <div style="font-size:13px;font-weight:bold;margin-bottom:8px;">Причина:</div>
      <button type="button" id="zayDelSpam"   style="background:#6c757d;color:#fff;border:none;padding:5px 12px;border-radius:4px;cursor:pointer;margin-right:6px;">Спам</button>
      <button type="button" id="zayDelReject" style="background:#fd7e14;color:#fff;border:none;padding:5px 12px;border-radius:4px;cursor:pointer;margin-right:6px;">Отказался</button>
      <button type="button" id="zayDelOther"  style="background:#dc3545;color:#fff;border:none;padding:5px 12px;border-radius:4px;cursor:pointer;margin-right:6px;">Другое</button>
      <button type="button" id="zayDelCancel" style="background:#aaa;color:#fff;border:none;padding:5px 12px;border-radius:4px;cursor:pointer;">Отмена</button>
      <textarea id="zayDelComment" rows="2" placeholder="Комментарий (необязательно)" style="display:block;width:100%;margin-top:8px;font-size:13px;box-sizing:border-box;"></textarea>
    </div>
    </div><!-- /zayEditFields -->
    <div id="zayEditErr" style="color:#b00;margin-top:8px;font-size:13px;"></div>
  </div>
</div>

<script>
(function(){
  var API = "/bb/zayavka_api.php";
  var overlay = document.getElementById("zayEditOverlay");
  var elId = document.getElementById("zayEditOrderId");
  var elCh = document.getElementById("zayEditChTime");
  var elErr = document.getElementById("zayEditErr");

  var reasonMap = {rejected:"Отказался", spam:"Спам", deleted:"Удалено", done:"Выполнено"};

  function open(orderId, zvId){
    elErr.textContent = "";
    document.getElementById("zayDelPanel").style.display = "none";
    document.getElementById("zayDelComment").value = "";
    document.getElementById("zayEditZvId").value = zvId || "";
    fetch(API + "?action=load&order_id=" + encodeURIComponent(orderId))
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d.ok) { alert(d.error || "Ошибка загрузки"); return; }
        var z = d.zayavka;
        elId.value = z.order_id;
        document.getElementById("zayEditTitle").textContent = "№" + z.order_id + " (" + z.z_status + ")";
        document.getElementById("zayEditMeta").innerHTML =
          "<strong>" + (z.family||"") + "</strong> " + (z.phone>1 ? z.phone : "<span style='color:#b00'>нет телефона</span>") +
          (z.info ? "<br>" + z.info : "");
        document.getElementById("zayEditHistory").innerHTML = z.info2 || "";

        if (d.archived) {
          var msg = "Заявка закрыта: <strong>" + (reasonMap[z.z_status] || z.z_status) + "</strong>";
          if (z.z_reject_reason) { msg += " (" + z.z_reject_reason + ")"; }
          document.getElementById("zayArchivedMsg").innerHTML = msg;
          var btn = document.getElementById("zayCreateNew");
          btn.dataset.modelId = z.model_id || "";
          btn.dataset.phone   = z.phone || "";
          btn.dataset.family  = z.family || "";
          document.getElementById("zayArchivedPanel").style.display = "block";
          document.getElementById("zayEditFields").style.display = "none";
        } else {
          elCh.value = z.ch_time;
          document.getElementById("zayArchivedPanel").style.display = "none";
          document.getElementById("zayEditFields").style.display = "block";
          document.getElementById("zayEditInfo").value = "";
          document.getElementById("zayEditPlanned").value = z.planned_date || "";
          document.getElementById("zayEditValidity").value = z.validity_date || "";
          document.getElementById("zayEditModel").value = "";
          document.getElementById("zayEditModelSearch").value = "";
        }
        overlay.style.display = "block";
      })
      .catch(function(){ alert("Сеть недоступна"); });
  }

  function post(params){
    var body = new URLSearchParams(params);
    return fetch(API, {method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString()})
      .then(function(r){ return r.json().then(function(j){ return {status:r.status, j:j}; }); });
  }

  function handle(res){
    if (res.j && res.j.ok) { location.reload(); }
    else { elErr.textContent = (res.j && res.j.error) ? res.j.error : "Ошибка"; }
  }

  function openCreate(zvId, phone, family, modelId, modelLabel){
    elErr.textContent = "";
    document.getElementById("zayEditZvId").value = zvId || "";
    document.getElementById("zayNewPhone").value  = phone  || "";
    document.getElementById("zayNewFamily").value = family || "";
    document.getElementById("zayEditTitle").textContent = "Новая заявка";
    document.getElementById("zayEditMeta").innerHTML =
      "<strong>" + (family || "") + "</strong> " + (phone > 1 ? phone : "<span style='color:#b00'>нет телефона</span>");
    document.getElementById("zayEditHistory").innerHTML = "";
    document.getElementById("zayArchivedPanel").style.display = "none";
    document.getElementById("zayEditFields").style.display = "none";
    var nsEl = document.getElementById("zayNewModelSearch");
    var nhEl = document.getElementById("zayNewModel");
    if (modelId && modelId !== "0") {
      nhEl.value = modelId;
      nsEl.value = modelLabel ? ("#" + modelId + " " + modelLabel) : ("#" + modelId);
    } else {
      nhEl.value = ""; nsEl.value = "";
    }
    document.getElementById("zayNewInfo").value    = "";
    document.getElementById("zayNewPlanned").value = "";
    document.getElementById("zayNewValidity").value= "";
    document.getElementById("zayCreatePanel").style.display = "block";
    overlay.style.display = "block";
  }

  document.querySelectorAll(".zay_edit_btn").forEach(function(b){
    b.addEventListener("click", function(){ open(b.dataset.orderid, b.dataset.zvid); });
  });

  document.querySelectorAll(".zay_create_btn").forEach(function(b){
    b.addEventListener("click", function(){
      var zvId = b.dataset.zvid, phone = b.dataset.phone, family = b.dataset.family;
      var modelId = b.dataset.modelid, modelLabel = b.dataset.modellabel, crTime = b.dataset.crtime;
      fetch(API + "?action=resolve&zv_id=" + encodeURIComponent(zvId)
               + "&phone="    + encodeURIComponent(phone)
               + "&model_id=" + encodeURIComponent(modelId)
               + "&cr_time="  + encodeURIComponent(crTime))
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (!d.ok) { alert(d.error || "Ошибка"); return; }
          if (d.found) { open(d.order_id, zvId); }
          else         { openCreate(zvId, phone, family, modelId, modelLabel); }
        })
        .catch(function(){ openCreate(zvId, phone, family, modelId, modelLabel); });
    });
  });
  function closeOverlay(){
    overlay.style.display = "none";
    document.getElementById("zayArchivedPanel").style.display = "none";
    document.getElementById("zayCreatePanel").style.display = "none";
    document.getElementById("zayEditFields").style.display = "block";
  }
  document.getElementById("zayEditClose").addEventListener("click", closeOverlay);

  document.getElementById("zayEditSave").addEventListener("click", function(){
    post({action:"save", order_id:elId.value, info:document.getElementById("zayEditInfo").value,
          planned_date:document.getElementById("zayEditPlanned").value,
          validity_date:document.getElementById("zayEditValidity").value,
          last_ch_time:elCh.value}).then(handle);
  });
  // live model search
  (function(){
    var searchEl  = document.getElementById("zayEditModelSearch");
    var hiddenEl  = document.getElementById("zayEditModel");
    var dropdown  = document.getElementById("zayEditModelDropdown");
    var timer     = null;

    function closeDropdown(){ dropdown.style.display = "none"; }

    searchEl.addEventListener("input", function(){
      clearTimeout(timer);
      hiddenEl.value = "";
      var q = searchEl.value.trim();
      if (q.length < 2) { closeDropdown(); return; }
      timer = setTimeout(function(){
        fetch("/bb/model_search.php?q=" + encodeURIComponent(q))
          .then(function(r){ return r.json(); })
          .then(function(items){
            if (!items.length) { closeDropdown(); return; }
            dropdown.innerHTML = "";
            items.forEach(function(item){
              var d = document.createElement("div");
              d.textContent = "#" + item.id + " " + item.label;
              var color = item.free_count > 0 ? "#155a1a" : "#a04000";
              d.style.cssText = "padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f0f0;color:" + color + ";font-weight:600;";
              d.addEventListener("mousedown", function(e){
                e.preventDefault();
                searchEl.value = "#" + item.id + " " + item.label;
                hiddenEl.value = item.id;
                closeDropdown();
              });
              d.addEventListener("mouseover", function(){ d.style.background="#e8f0fe"; });
              d.addEventListener("mouseout",  function(){ d.style.background=""; });
              dropdown.appendChild(d);
            });
            dropdown.style.display = "block";
          });
      }, 200);
    });

    searchEl.addEventListener("blur", function(){ setTimeout(closeDropdown, 150); });
  })();

  document.getElementById("zayEditModelBtn").addEventListener("click", function(){
    var mid = document.getElementById("zayEditModel").value;
    if (!mid) { elErr.textContent="Выберите модель из списка (начните вводить название)"; return; }
    if (!confirm("Сменить модель заявки?")) return;
    post({action:"change_model", order_id:elId.value, model_id:mid}).then(handle);
  });
  document.getElementById("zayCreateNew").addEventListener("click", function(){
    var btn = this;
    if (!confirm("Создать новую заявку?")) return;
    post({action:"create_new", model_id:btn.dataset.modelId, phone:btn.dataset.phone,
          family:btn.dataset.family, zv_id:document.getElementById("zayEditZvId").value}).then(handle);
  });

  // model search for create panel
  (function(){
    var searchEl = document.getElementById("zayNewModelSearch");
    var hiddenEl = document.getElementById("zayNewModel");
    var dropdown = document.getElementById("zayNewModelDropdown");
    var timer = null;
    function closeDd(){ dropdown.style.display = "none"; }
    searchEl.addEventListener("input", function(){
      clearTimeout(timer);
      hiddenEl.value = "";
      var q = searchEl.value.trim();
      if (q.length < 2) { closeDd(); return; }
      timer = setTimeout(function(){
        fetch("/bb/model_search.php?q=" + encodeURIComponent(q))
          .then(function(r){ return r.json(); })
          .then(function(items){
            if (!items.length) { closeDd(); return; }
            dropdown.innerHTML = "";
            items.forEach(function(item){
              var d = document.createElement("div");
              d.textContent = "#" + item.id + " " + item.label;
              var color = item.free_count > 0 ? "#155a1a" : "#a04000";
              d.style.cssText = "padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f0f0;color:" + color + ";font-weight:600;";
              d.addEventListener("mousedown", function(e){ e.preventDefault(); searchEl.value = "#" + item.id + " " + item.label; hiddenEl.value = item.id; closeDd(); });
              d.addEventListener("mouseover", function(){ d.style.background="#e8f0fe"; });
              d.addEventListener("mouseout",  function(){ d.style.background=""; });
              dropdown.appendChild(d);
            });
            dropdown.style.display = "block";
          });
      }, 200);
    });
    searchEl.addEventListener("blur", function(){ setTimeout(closeDd, 150); });
  })();

  document.getElementById("zayNewSubmit").addEventListener("click", function(){
    var mid = document.getElementById("zayNewModel").value;
    if (!mid) { elErr.textContent = "Выберите модель из списка"; return; }
    elErr.textContent = "";
    post({action:"create_new", model_id:mid,
          phone:  document.getElementById("zayNewPhone").value,
          family: document.getElementById("zayNewFamily").value,
          zv_id:  document.getElementById("zayEditZvId").value,
          info:         document.getElementById("zayNewInfo").value,
          planned_date: document.getElementById("zayNewPlanned").value,
          validity_date:document.getElementById("zayNewValidity").value}).then(handle);
  });

  document.getElementById("zayEditDelete").addEventListener("click", function(){
    document.getElementById("zayDelPanel").style.display = "block";
  });
  document.getElementById("zayDelCancel").addEventListener("click", function(){
    document.getElementById("zayDelPanel").style.display = "none";
    document.getElementById("zayDelComment").value = "";
  });
  function delWithReason(status, reason) {
    var comment = document.getElementById("zayDelComment").value.trim();
    post({action:"set_status", order_id:elId.value, status:status,
          reason: reason || "", reason_comment: comment}).then(handle);
  }
  document.getElementById("zayDelSpam").addEventListener("click",   function(){ delWithReason("spam",     null); });
  document.getElementById("zayDelReject").addEventListener("click", function(){ delWithReason("rejected", "changed_mind"); });
  document.getElementById("zayDelOther").addEventListener("click",  function(){ delWithReason("deleted",  null); });
})();
</script>

<script src="/bb/assets/js/zvonki_bb.js"></script>

</body>
</html>
