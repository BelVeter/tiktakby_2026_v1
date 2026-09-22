<?php
/**
 * Переключатель «Доставка: сегодня / завтра» в карточках товара на сайте.
 *
 * Дёргается со страницы курьера (bb/cur_page2.php) по AJAX.
 * Логика и флаг — bb/classes/DeliverySchedule.php.
 */

use bb\classes\DeliverySchedule;

session_start();
date_default_timezone_set('Europe/Minsk');
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/DeliverySchedule.php');

//------- proverka paroley
$in_level = array(0, 5, 7, -1);

isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Нет доступа — войдите заново.'), JSON_UNESCAPED_UNICODE);
    exit;
}
//-----------proverka paroley

$on = isset($_POST['moved_to_tomorrow']) && $_POST['moved_to_tomorrow'] == '1';
$who = isset($_SESSION['user_fio']) ? $_SESSION['user_fio'] : null;

if (!DeliverySchedule::setMovedToTomorrow($on, $who)) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'Не удалось сохранить — сообщите Диме.'), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(array(
    'ok' => true,
    'moved_to_tomorrow' => DeliverySchedule::isMovedToTomorrow(true),
    'delivery_text' => DeliverySchedule::text(),
    'hint' => DeliverySchedule::switchHint(),
), JSON_UNESCAPED_UNICODE);
