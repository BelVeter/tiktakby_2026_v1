<?php
/**
 * Справочный расчёт массового продления по клиенту (таблица «У клиента на руках»
 * в bb/dogovor_new.php).
 *
 * Суммы считает фронт — тем же кодом (calcRentToPay/capByOldTarif), которым
 * считает обычная форма продления, чтобы цифры совпали, когда сотрудник дойдёт
 * до реального оформления. Сюда они приходят только на хранение: расчёт
 * справочный, деньги пишутся в rent_sub_deals_act при оформлении.
 *
 * Даты и количество дней сервер берёт из rent_deals_act сам и клиенту не верит.
 *
 * Действия (POST, JSON):
 *   save  {client_id, ext_to: 'Y-m-d', rows: [{deal_id, amount}]}
 *   clear {client_id}
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');

header('Content-Type: application/json; charset=utf-8');

$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ?: $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !in_array($_SESSION['level'], $in_level)) {
    http_response_code(403);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    echo json_encode(['status' => 'error', 'msg' => 'Пустой или неразобранный запрос'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();

$action    = isset($payload['action']) ? $payload['action'] : '';
$client_id = isset($payload['client_id']) ? (int)$payload['client_id'] : 0;
$user_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($client_id <= 0) {
    echo json_encode(['status' => 'error', 'msg' => 'Не передан клиент'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Отдаёт сохранённый расчёт клиента в том же виде, в каком его рисует страница.
 */
function ext_calc_snapshot($mysqli, $client_id)
{
    $stmt = $mysqli->prepare(
        "SELECT c.deal_id, c.item_inv_n, c.ext_from, c.ext_to, c.ext_days, c.amount,
                c.calc_time, c.user_id, l.lp_fio
         FROM rent_ext_calc c
         LEFT JOIN logpass l ON l.logpass_id = c.user_id
         WHERE c.client_id = ?
         ORDER BY c.calc_id"
    );
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows      = [];
    $total     = 0;
    $ext_to    = 0;
    $calc_time = 0;
    $user_fio  = '';

    while ($row = $res->fetch_assoc()) {
        $rows[(string)$row['deal_id']] = [
            'deal_id'    => (int)$row['deal_id'],
            'item_inv_n' => (int)$row['item_inv_n'],
            'ext_days'   => (int)$row['ext_days'],
            'amount'     => (float)$row['amount'],
        ];
        $total    += (float)$row['amount'];
        $ext_to    = (int)$row['ext_to'];
        $calc_time = (int)$row['calc_time'];
        $user_fio  = $row['lp_fio'] !== null ? $row['lp_fio'] : '';
    }
    $stmt->close();

    if (!$rows) {
        return null;
    }

    return [
        'ext_to'      => date('Y-m-d', $ext_to),
        'calc_time'   => $calc_time,
        'calc_time_h' => date('d.m.Y H:i', $calc_time),
        'user_fio'    => $user_fio,
        'rows'        => $rows,
        'total'       => round($total, 2),
    ];
}

if ($action === 'clear') {
    $stmt = $mysqli->prepare("DELETE FROM rent_ext_calc WHERE client_id = ?");
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'ok', 'calc' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action !== 'save') {
    echo json_encode(['status' => 'error', 'msg' => 'Неизвестное действие'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ext_to_str = isset($payload['ext_to']) ? $payload['ext_to'] : '';
$ext_to     = strtotime($ext_to_str . ' 00:00:00');
if (!$ext_to) {
    echo json_encode(['status' => 'error', 'msg' => 'Не разобрана дата продления'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rows_in = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
if (!$rows_in) {
    echo json_encode(['status' => 'error', 'msg' => 'Не выбрано ни одной позиции'], JSON_UNESCAPED_UNICODE);
    exit;
}

// сделки клиента: сервер сам решает, что можно продлить и с какой даты
$stmt = $mysqli->prepare("SELECT deal_id, item_inv_n, return_date FROM rent_deals_act WHERE client_id = ?");
$stmt->bind_param('i', $client_id);
$stmt->execute();
$res = $stmt->get_result();
$deals = [];
while ($deal = $res->fetch_assoc()) {
    $deals[(int)$deal['deal_id']] = $deal;
}
$stmt->close();

$to_save   = [];
$skipped   = [];
$calc_time = time();

foreach ($rows_in as $row) {
    $deal_id = isset($row['deal_id']) ? (int)$row['deal_id'] : 0;
    if (!isset($deals[$deal_id])) {
        $skipped[] = $deal_id; // сделка чужая или уже закрыта, пока считали
        continue;
    }

    $ext_from = (int)$deals[$deal_id]['return_date'];
    $ext_days = (int)round(($ext_to - $ext_from) / 86400);
    if ($ext_days < 1) {
        $skipped[] = $deal_id; // товар уже сдан по эту дату
        continue;
    }

    $amount = isset($row['amount']) ? round((float)$row['amount'], 2) : -1;
    if ($amount < 0) {
        $skipped[] = $deal_id;
        continue;
    }

    $to_save[] = [
        'deal_id'    => $deal_id,
        'item_inv_n' => (int)$deals[$deal_id]['item_inv_n'],
        'ext_from'   => $ext_from,
        'ext_days'   => $ext_days,
        'amount'     => $amount,
    ];
}

if (!$to_save) {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Ни одну из выбранных позиций продлить по эту дату нельзя',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// расчёт хранится только последний: старые строки клиента заменяем целиком
$mysqli->begin_transaction();
try {
    $stmt = $mysqli->prepare("DELETE FROM rent_ext_calc WHERE client_id = ?");
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare(
        "INSERT INTO rent_ext_calc
            (client_id, deal_id, item_inv_n, ext_from, ext_to, ext_days, amount, calc_time, user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($to_save as $row) {
        $stmt->bind_param(
            'iiiiiidii',
            $client_id,
            $row['deal_id'],
            $row['item_inv_n'],
            $row['ext_from'],
            $ext_to,
            $row['ext_days'],
            $row['amount'],
            $calc_time,
            $user_id
        );
        $stmt->execute();
    }
    $stmt->close();

    $mysqli->commit();
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['status' => 'error', 'msg' => 'Не удалось сохранить расчёт'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status'  => 'ok',
    'calc'    => ext_calc_snapshot($mysqli, $client_id),
    'skipped' => $skipped,
], JSON_UNESCAPED_UNICODE);
