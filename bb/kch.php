<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php'); // включаем подключение к базе данных
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/models/User.php'); // включаем подключение к базе данных
require_once $_SERVER['DOCUMENT_ROOT'].'/bb/classes/Сlient.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bb/classes/tovar.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bb/classes/rtf.php';

$mysqli = \bb\Db::getInstance()->getConnection();

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

	</body></html>');
}

//-----------proverka paroley


echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>База. Главная.</title>
<body>
<div class="user"><form name="выход" method="post" action="index.php">Вы зашли как: <strong> '.$_SESSION['user_fio'].'</strong> <input type="submit" name="exit" value="Выйти" /><br/>Офис: '.$_SESSION['office'].' </form></div>
<div id="zv_div"></div>';

//Проверка входящей информации
//	echo "Poluzhenniye filom danniye: <br> ---------------------- <br><br>";
//	foreach ($_POST as $key => $value) {
//		echo "<strong>".$key."</strong> imeet znacheniye: <strong>".$value."</strong><br>";
//	}
//	echo "<br>----------------------------------<br>konets poluchennih faylom dannih.<br><br>";

$i_from_date="2017-06-01";
$i_to_date=date("Y-m-d");
$i_who='5';
$of=2;
$kas='k2';

foreach ($_POST as $key => $value) {
		$$key = get_post($key);
}

$i_from_date=strtotime($i_from_date);
$i_to_date=strtotime($i_to_date);


$rd_lp = "SELECT * FROM logpass";
$result_lp = $mysqli->query($rd_lp);
if (!$result_lp) {die('Сбой при доступе к базе данных: '.$rd_lp.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


$lp_list='';

while ($lp_l=$result_lp->fetch_assoc()) {
	$lp_list[$lp_l['logpass_id']]=$lp_l['lp_fio'];
}



//$client_id='37';
//$inv_n='71920';

$prev_ost='';

//$q_ost = "SELECT * FROM kassas WHERE cr_who!='$i_who' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND channel='$of' AND kassa='$kas' ORDER BY cr_when DESC";
$q_ost = "SELECT * FROM kassas WHERE (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND channel='$of' AND kassa='$kas' ORDER BY cr_when DESC";
$result_ost = $mysqli->query($q_ost);
if (!$result_ost) {die('Сбой при доступе к базе данных: '.$q_ost.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}

echo '
<form name="srch_form" method="post" id="srch_form" action="kch.php">
	Офис:<select name="of" id="of_select">
				<option value="1" '.($of==1 ? 'selected="selected"' : '').'>1</option>
				<option value="2" '.($of==2 ? 'selected="selected"' : '').'>2</option>
				<option value="3" '.($of==3 ? 'selected="selected"' : '').'>3</option>
				<option value="4" '.($of==4 ? 'selected="selected"' : '').'>4</option>
			</select>,
	касса:<select name="kas" id="of_select">
				<option value="k1" '.($kas=="k1" ? 'selected="selected"' : '').'>k1</option>
				<option value="k2" '.($kas=="k2" ? 'selected="selected"' : '').'>k2</option>
			</select>
						<br />
	<br>За период:
		c <input type="date" name="i_from_date" id="i_from_date" value="'.date("Y-m-d", $i_from_date).'" /> по <input type="date" name="i_to_date" id="i_to_date" value="'.date("Y-m-d", $i_to_date).'" /> <input type="submit" name="action" value="показать" onclick="" /><br />
</form>


		';


echo '

<table border="1" cellspacing="0">
	<tr>
		<td>отч.дата</td>
		<td>вх. остаток</td>
		<td>выручка</td>
		<td>пересчет выручка</td>
		<td>расход</td>
		<td>пересчет расход</td>
		<td>остаток на конец</td>
		<td>кто сохранил</td>
		<td>время сохранения</td>
	</tr>

		';

while ($ost=$result_ost->fetch_assoc()) {
	//$q_plus1 = "SELECT * FROM rent_sub_deals_act WHERE type='payment' AND  cr_who='$i_who' AND (acc_date BETWEEN '".$i_from_date."' AND '".$i_to_date."') AND channel='$of' AND kassa='$kas' ORDER BY cr_when DESC";
	//$result_ost = $mysqli->query($q_ost);
	//if (!$result_ost) {die('Сбой при доступе к базе данных: '.$q_ost.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);}


	echo '
		<tr>
			<td>'.date("d.m.Y", $ost['acc_date']).'</td>
			<td>'.$ost['k_amount_start'].'</td>
			<td>'.$ost['sales'].'</td>
			<td></td>
			<td>'.$ost['doh_rash'].'</td>
			<td></td>
			<td>'.$ost['k_amount_end'].'</td>
			<td>'.\bb\models\User::getUserById($ost['cr_who'])->getShortName().'</td>
			<td>'.date("d.m.Y (H:i:s)", $ost['cr_when']).'</td>
		</tr>
			';

}


echo '</table>';


function get_post($var)
{
	GLOBAL $mysqli;
	return $mysqli->real_escape_string($_POST[$var]);
}

class ostatok {

	public $acc_date;
	public $office;
	public $kassa;
	public $start_ost;
	public $end_ost;
	public $prihod;


}

?>
