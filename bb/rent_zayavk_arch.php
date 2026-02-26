<?php

use bb\Base;

session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/bron.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/tovar.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');

$mysqli = \bb\Db::getInstance()->getConnection();

set_time_limit(30);

$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    die('
    <!DOCTYPE html><html><head><meta charset="utf-8"><title>Авторизация</title></head><body>
    <form action="/bb/index.php" method="post">
        Офис:<select name="of_select"><option value="0">не выбран</option><option value="1">Литературная</option><option value="2">Ложинская</option></select><br />
        Логин:<input type="text" value="" name="login" /><br />
        Пароль:<input type="password" value="" name="pass" /><br />
        <input type="submit" value="войти" />
    </form></body></html>');
}

// список пользователей
$rd_lp = "SELECT * FROM logpass";
$result_lp = $mysqli->query($rd_lp);
if (!$result_lp)
    die('Сбой: ' . $rd_lp);
$lp_list[0] = 'автомат';
while ($lp_l = $result_lp->fetch_assoc()) {
    $lp_list[$lp_l['logpass_id']] = $lp_l['lp_fio'];
}

// период запроса — последние 30 дней
$from = new DateTime();
$from->modify('-30 day');

$query_or = "SELECT * FROM rent_orders_arch WHERE type2='zayavka' AND arch_time>" . $from->getTimestamp() . " ORDER BY arch_time DESC";
$result_or = $mysqli->query($query_or);
if (!$result_or)
    die('Сбой: ' . $query_or . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

echo '
<!DOCTYPE html><html><head>
<meta charset="utf-8">
<link href="/bb/stile.css" rel="stylesheet" type="text/css" />
' . Base::getBarCodeReaderScript() . '
<title>Удаленные заявки</title>
</head><body>';

$_bb_nav_active = 'rent_zayavk_arch.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/bb_nav.php');

echo '
<h2 style="margin:16px;">Удаленные заявки <span style="color:#888;font-size:14px;">(за последние 30 дней)</span></h2>
<table border="1" cellspacing="0" style="margin:0 16px;">
  <tr style="background-color:#ef5350;color:#fff;">
      <th style="padding:6px 10px;">Дата / №</th>
      <th style="padding:6px 10px;">Фото</th>
      <th style="padding:6px 10px;min-width:250px;">Товар</th>
      <th style="padding:6px 10px;min-width:200px;">Комментарий</th>
      <th style="padding:6px 10px;">Телефон</th>
      <th style="padding:6px 10px;">Действует до</th>
      <th style="padding:6px 10px;">Кто удалил</th>
  </tr>';

while ($ord = $result_or->fetch_assoc()) {
    $br_line = new \bb\classes\bron();
    $br_line->br_line($ord);
    $br_line->item_load();

    $del_time = new DateTime();
    $del_time->setTimestamp($ord['arch_time']);

    echo '
    <tr>
        <td style="padding:4px 8px;text-align:center;">' . date("d.m.y", $br_line->order_date) . '<br /><i>(' . date("H:i", $br_line->cr_time) . ')</i><br />№' . $br_line->order_id . '</td>
        <td style="padding:4px 8px;text-align:center;"><img src="' . $br_line->small_pic . '" style="max-height:60px;max-width:60px;object-fit:contain;" /></td>
        <td style="padding:4px 8px;">' . $br_line->cat_dog_name . ' ' . $br_line->producer . ': ' . $br_line->model . '. Цвет: "' . $br_line->br_color . '"<br /><strong>' . $br_line->inv_n . '</strong></td>
        <td style="padding:4px 8px;">' . $br_line->info . '<br />' . $br_line->info2 . '</td>
        <td style="padding:4px 8px;">' . ($br_line->phone ?? '') . '</td>
        <td style="padding:4px 8px;text-align:center;">' . date("d.m.y", $br_line->validity) . '</td>
        <td style="padding:4px 8px;">' . $lp_list[$ord['arch_who']] . '<br />' . $del_time->format("d.m.Y H:i") . '</td>
    </tr>';

    unset($br_line);
}

echo '</table>';

require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/zv_show.php');

?>
</body>

</html>