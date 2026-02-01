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

//Проверка входящей информации
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
