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

// ── A1 ВАТС: обработка отметки "перезвонили" ─────────────────────────────────
$a1StorageFile = $_SERVER['DOCUMENT_ROOT'] . '/storage/app/a1_missed_calls.json';
if (isset($_POST['a1_action']) && $_POST['a1_action'] === 'mark_processed') {
    $uuid = trim($_POST['a1_uuid'] ?? '');
    if ($uuid && file_exists($a1StorageFile)) {
        $a1Data = json_decode(file_get_contents($a1StorageFile), true);
        if (is_array($a1Data) && isset($a1Data[$uuid])) {
            $a1Data[$uuid]['processed_at'] = date('d.m.Y H:i');
            $a1Data[$uuid]['processed_by'] = $_SESSION['user_fio'] ?? 'unknown';
            file_put_contents($a1StorageFile, json_encode($a1Data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            @chmod($a1StorageFile, 0666); // сохранить права для Apache после записи artisan-командой
        }
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
    $count = 0;
    if (file_exists($a1StorageFile)) {
        $a1Data = json_decode(file_get_contents($a1StorageFile), true);
        if (is_array($a1Data)) {
            $count = count(array_filter($a1Data, function($c) { return empty($c['processed_at']); }));
        }
    }
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
// A1 ВАТС — пропущенные звонки
// ══════════════════════════════════════════════════════════════════════════════

$a1StatusLabels = [
    'NOT_ANSWERED_COMMON'            => 'Нет ответа',
    'CANCELLED_BY_CALLER'            => 'Сброшен',
    'DENIED_DUE_TO_NOT_WORK_TIME'    => 'Вне рабочего времени',
    'DENIED_DUE_TO_MAX_SESSION'      => 'Перегрузка',
    'DENIED_DUE_TO_MAX_CHANNEL_LIMIT'=> 'Перегрузка каналов',
];

$a1Calls       = [];
$a1NewCount    = 0;
$a1LastUpdated = null;

if (file_exists($a1StorageFile)) {
    $a1LastUpdated = filemtime($a1StorageFile);
    $a1Raw = json_decode(file_get_contents($a1StorageFile), true);
    if (is_array($a1Raw)) {
        // Сортировка: новые сначала
        uasort($a1Raw, function ($a, $b) {
            return ($b['callTimestamp'] ?? 0) - ($a['callTimestamp'] ?? 0);
        });
        $a1Calls    = $a1Raw;
        $a1NewCount = count(array_filter($a1Raw, function ($c) { return empty($c['processed_at']); }));
    }
}

echo '<div style="background:#f0f4fa;border:2px solid #4a90d9;border-radius:6px;padding:12px 16px;margin-bottom:18px;">';
echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">';
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

if (empty($a1Calls)) {
    echo '<p style="color:#888;font-style:italic;">Нет данных. Запустите: <code>php artisan a1:fetch-missed-calls</code></p>';
} else {
    echo '<table border="1" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px;">';
    echo '<tr style="background:#d0e4f7;">';
    echo '<th style="width:80px;padding:5px;">Дата / Время</th>';
    echo '<th style="padding:5px;">Номер звонящего</th>';
    echo '<th style="padding:5px;">Статус</th>';
    echo '<th style="padding:5px;">Клиент / Аренды</th>';
    echo '<th style="width:120px;padding:5px;">Обработка</th>';
    echo '</tr>';

    foreach ($a1Calls as $uuid => $call) {
        $isProcessed = !empty($call['processed_at']);
        $caller      = $call['callerNumber']    ?? '—';
        $callTs      = $call['callTimestamp']   ?? 0;
        $status      = $call['callStatus']      ?? '';
        $crmClient   = $call['crm_client']      ?? null;
        $activeDeals = $call['crm_active_deals'] ?? [];
        $lastReturn  = $call['crm_last_return']  ?? null;
        $totalDeals  = $call['crm_total_deals']  ?? 0;
        $statusLabel = $a1StatusLabels[$status] ?? $status;

        $rowBg = $isProcessed ? '#f6fff6' : '#fff8dc';
        $borderLeft = $isProcessed ? 'border-left:4px solid #198754;' : 'border-left:4px solid #dc3545;';

        echo '<tr style="background:'.$rowBg.';'.$borderLeft.'">';

        // Дата / время
        echo '<td style="padding:5px;text-align:center;">';
        echo ($callTs ? date('d.m.y', $callTs).'<br>'.date('H:i', $callTs) : '—');
        echo '</td>';

        // Номер
        echo '<td style="padding:5px;font-weight:bold;">';
        echo htmlspecialchars($caller);
        echo '</td>';

        // Статус
        echo '<td style="padding:5px;color:#555;">'.htmlspecialchars($statusLabel).'</td>';

        // CRM-блок
        echo '<td style="padding:5px;">';
        if ($crmClient) {
            echo '<strong>'.htmlspecialchars($crmClient['fio']).'</strong>';
            if ($totalDeals > 0) {
                echo ' <span style="color:#888;font-size:11px;">('.$totalDeals.' сд.)</span>';
            }
            if (!empty($activeDeals)) {
                echo '<div style="color:#0a5c36;margin-top:3px;">';
                foreach ($activeDeals as $deal) {
                    echo '📦 '.htmlspecialchars($deal['model']);
                    echo ' <span style="color:#888;">'.htmlspecialchars($deal['rented_from']).'–'.htmlspecialchars($deal['return_due']).'</span><br>';
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

        // Кнопка
        echo '<td style="padding:5px;text-align:center;">';
        if ($isProcessed) {
            echo '<span style="color:#198754;">✓</span><br>';
            echo '<small style="color:#888;">'.htmlspecialchars($call['processed_at']).'<br>'.htmlspecialchars($call['processed_by'] ?? '').'</small>';
        } else {
            echo '<form method="post" action="zv_ch.php" style="margin:0;">';
            echo '<input type="hidden" name="a1_action" value="mark_processed">';
            echo '<input type="hidden" name="a1_uuid"   value="'.htmlspecialchars($uuid).'">';
            echo '<input type="submit" value="Перезвонили" style="cursor:pointer;padding:3px 8px;background:#198754;color:#fff;border:none;border-radius:4px;">';
            echo '</form>';
        }
        echo '</td>';

        echo '</tr>';
    }
    echo '</table>';
}
echo '</div>';

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
	$query_zv = "SELECT * FROM zvonki WHERE `status`!='arch' ORDER BY `status` DESC, cr_time DESC LIMIT $limit";
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
        '.($zv['type1']=='zayavka' ? '<button type="button" class="zayavka_btn">Оформить заявку</button>' : '').'
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

<script src="/bb/assets/js/zvonki_bb.js"></script>

</body>
</html>
