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
 *   apply {client_id, amount, payment_type, ch_num, payment_date} — разноска оплаты
 *
 * apply создаёт РЕАЛЬНЫЕ продления и платежи по сохранённому расчёту, поэтому
 * срабатывает, только если оплата до копейки равна расчёту, расчёт свежий и с
 * момента расчёта ни по одной позиции ничего не изменилось. Иначе — вручную.
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Deal.php');

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
                c.calc_time, c.user_id, c.applied_time, c.applied_user_id,
                l.lp_fio, la.lp_fio AS applied_fio
         FROM rent_ext_calc c
         LEFT JOIN logpass l ON l.logpass_id = c.user_id
         LEFT JOIN logpass la ON la.logpass_id = c.applied_user_id
         WHERE c.client_id = ?
         ORDER BY c.calc_id"
    );
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows         = [];
    $total        = 0;
    $ext_to       = 0;
    $calc_time    = 0;
    $user_fio     = '';
    $applied_time = 0;
    $applied_fio  = '';

    while ($row = $res->fetch_assoc()) {
        $rows[(string)$row['deal_id']] = [
            'deal_id'    => (int)$row['deal_id'],
            'item_inv_n' => (int)$row['item_inv_n'],
            'ext_days'   => (int)$row['ext_days'],
            'amount'     => (float)$row['amount'],
        ];
        $total       += (float)$row['amount'];
        $ext_to       = (int)$row['ext_to'];
        $calc_time    = (int)$row['calc_time'];
        $user_fio     = $row['lp_fio'] !== null ? $row['lp_fio'] : '';
        $applied_time = (int)$row['applied_time'];
        $applied_fio  = $row['applied_fio'] !== null ? $row['applied_fio'] : '';
    }
    $stmt->close();

    if (!$rows) {
        return null;
    }

    return [
        'ext_to'         => date('Y-m-d', $ext_to),
        'calc_time'      => $calc_time,
        'calc_time_h'    => date('d.m.Y H:i', $calc_time),
        'user_fio'       => $user_fio,
        'rows'           => $rows,
        'total'          => round($total, 2),
        'applied'        => $applied_time > 0,
        'applied_time_h' => $applied_time > 0 ? date('d.m.Y H:i', $applied_time) : '',
        'applied_fio'    => $applied_fio,
    ];
}

/**
 * Сумма в копейках — сравнивать оплату с расчётом во float нельзя.
 */
function ext_calc_cents($amount)
{
    return (int)round($amount * 100);
}

/**
 * Полночь указанной даты или false.
 *
 * Через strtotime() проверять нельзя: strtotime(' 00:00:00') от пустой даты
 * возвращает сегодняшнюю полночь, и пропущенное поле молча становится «сегодня».
 */
function ext_calc_date($str)
{
    if (!is_string($str) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
        return false;
    }
    $date = DateTime::createFromFormat('Y-m-d|', $str);
    if (!$date || $date->format('Y-m-d') !== $str) {
        return false; // 2026-02-31 и прочие несуществующие даты
    }
    return $date->getTimestamp();
}

if ($action === 'clear') {
    $stmt = $mysqli->prepare("DELETE FROM rent_ext_calc WHERE client_id = ?");
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'ok', 'calc' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'apply') {
    ext_calc_apply($mysqli, $client_id, $user_id, $payload);
    exit;
}

if ($action !== 'save') {
    echo json_encode(['status' => 'error', 'msg' => 'Неизвестное действие'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ext_to = ext_calc_date(isset($payload['ext_to']) ? $payload['ext_to'] : '');
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

/**
 * Разносит оплату по сохранённому расчёту: на каждую позицию создаёт продление
 * и платёж ровно на те сроки и суммы, которые были посчитаны.
 *
 * Проводки те же, что делает «сохранить продление» в bb/dogovor_new.php:
 * sub-сделка type='extention' + связанная с ней type='payment', затем новая
 * дата возврата в rent_deals_act и Deal::recalculateAmounts().
 *
 * Срабатывает только при полном совпадении: оплата = расчёт до копейки, расчёт
 * не старше EXT_CALC_TTL_DAYS, ещё не разнесён, и по каждой позиции сделка жива,
 * а её дата возврата не сдвинулась с момента расчёта. Иначе — разносить вручную.
 */
function ext_calc_apply($mysqli, $client_id, $user_id, $payload)
{
    $EXT_CALC_TTL_DAYS = 7;
    $channels = ['nal_no_cheque', 'nal_cheque', 'card', 'bank'];

    $payment_type = isset($payload['payment_type']) ? $payload['payment_type'] : '';
    if (!in_array($payment_type, $channels, true)) {
        ext_calc_fail('Не выбран канал оплаты');
    }

    $payment_date = ext_calc_date(isset($payload['payment_date']) ? $payload['payment_date'] : '');
    if (!$payment_date) {
        ext_calc_fail('Не указана или не разобрана дата оплаты');
    }

    $ch_num = isset($payload['ch_num']) ? substr(trim($payload['ch_num']), 0, 128) : '';
    $paid_cents = ext_calc_cents(isset($payload['amount']) ? $payload['amount'] : 0);
    if ($paid_cents <= 0) {
        ext_calc_fail('Не введена сумма оплаты');
    }

    // расчёт
    $stmt = $mysqli->prepare(
        "SELECT deal_id, item_inv_n, ext_from, ext_to, ext_days, amount, calc_time, applied_time
         FROM rent_ext_calc WHERE client_id = ? ORDER BY calc_id"
    );
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $calc_rows = [];
    while ($row = $res->fetch_assoc()) {
        $calc_rows[] = $row;
    }
    $stmt->close();

    if (!$calc_rows) {
        ext_calc_fail('По клиенту нет сохранённого расчёта продления');
    }
    if ((int)$calc_rows[0]['applied_time'] > 0) {
        ext_calc_fail('Этот расчёт уже разнесён — повторная разноска запрещена');
    }

    $calc_time = (int)$calc_rows[0]['calc_time'];
    $age_days  = floor((time() - $calc_time) / 86400);
    if ($age_days > $EXT_CALC_TTL_DAYS) {
        ext_calc_fail('Расчёт от ' . date('d.m.Y', $calc_time) . ' устарел (больше '
            . $EXT_CALC_TTL_DAYS . ' дней) — пересчитайте продление');
    }

    $calc_cents = 0;
    foreach ($calc_rows as $row) {
        $calc_cents += ext_calc_cents($row['amount']);
    }
    if ($paid_cents !== $calc_cents) {
        ext_calc_fail('Оплата ' . number_format($paid_cents / 100, 2, ',', ' ')
            . ' не совпадает с расчётом ' . number_format($calc_cents / 100, 2, ',', ' ')
            . ' — разнесите вручную');
    }

    // обстановка не должна была измениться с момента расчёта
    $stmt = $mysqli->prepare("SELECT deal_id, item_inv_n, return_date FROM rent_deals_act WHERE client_id = ?");
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $deals = [];
    while ($deal = $res->fetch_assoc()) {
        $deals[(int)$deal['deal_id']] = $deal;
    }
    $stmt->close();

    foreach ($calc_rows as $row) {
        $deal_id = (int)$row['deal_id'];
        if (!isset($deals[$deal_id])) {
            ext_calc_fail('Инв.№' . $row['item_inv_n'] . ' больше не числится за клиентом '
                . '(товар вернули или сделку закрыли) — пересчитайте продление');
        }
        if ((int)$deals[$deal_id]['return_date'] !== (int)$row['ext_from']) {
            ext_calc_fail('У инв.№' . $deals[$deal_id]['item_inv_n'] . ' дата возврата изменилась после расчёта '
                . '(было ' . date('d.m.Y', $row['ext_from']) . ', стало '
                . date('d.m.Y', $deals[$deal_id]['return_date']) . ') — пересчитайте продление');
        }
    }

    $office     = isset($_SESSION['office']) ? (int)$_SESSION['office'] : 0;
    $now        = time();
    $applied    = [];

    $mysqli->begin_transaction();
    try {
        foreach ($calc_rows as $row) {
            $deal_id  = (int)$row['deal_id'];
            $ext_from = (int)$row['ext_from'];
            $ext_to   = (int)$row['ext_to'];
            $days     = (int)$row['ext_days'];
            $amount   = (float)$row['amount'];

            // тариф пишем так же, как форма продления: всегда цена за день,
            // иначе pay_calc() посчитает будущую просрочку по неверной единице
            $tarif_value = round($amount / $days, 2);

            // по банковскому платежу деньги ложатся на офис первой сдачи, а не на текущий
            $place = $office;
            $first_place = 0;
            if ($payment_type === 'bank') {
                $q = $mysqli->prepare(
                    "SELECT place FROM rent_sub_deals_act
                     WHERE deal_id = ? AND type IN ('first_rent', 'takeaway_plan')
                     ORDER BY cr_time DESC LIMIT 1"
                );
                $q->bind_param('i', $deal_id);
                $q->execute();
                $q->bind_result($first_place);
                if ($q->fetch()) {
                    $place = (int)$first_place;
                }
                $q->close();
            }

            // продление; колонки перечислены полностью - таблица NOT NULL почти везде
            $stmt = $mysqli->prepare(
                "INSERT INTO rent_sub_deals_act
                    (deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value,
                     rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid,
                     delivery_paid, r_payment_type, del_payment_type, status, info,
                     cr_time, cr_who_id, ch_time, ch_who_id, link, acc_date, place, ch_num,
                     sd_cat_id, sd_model_id, sd_inv_n)
                 VALUES (?, 'extention', 20, ?, ?, 0, 'day', ?, ?, ?, '', 0, 0, 0,
                         0, '', '', 'no_status', ?, ?, ?, 0, 0, 0, ?, ?, '', 0, 0, 0)"
            );
            $info = 'Разнесено автоматически по расчёту от ' . date('d.m.Y H:i', $calc_time);
            $stmt->bind_param(
                'iiidddsiiii',
                $deal_id, $ext_from, $ext_to, $tarif_value, $days, $amount,
                $info, $now, $user_id, $payment_date, $place
            );
            $stmt->execute();
            $ext_id = $mysqli->insert_id;
            $stmt->close();

            // оплата продления, привязанная к нему через link
            $stmt = $mysqli->prepare(
                "INSERT INTO rent_sub_deals_act
                    (deal_id, `type`, type_sort_n, `from`, `to`, tarif_id, tarif_step, tarif_value,
                     rent_tenor, r_to_pay, delivery_yn, delivery_to_pay, courier_id, r_paid,
                     delivery_paid, r_payment_type, del_payment_type, status, info,
                     cr_time, cr_who_id, ch_time, ch_who_id, link, acc_date, place, ch_num,
                     sd_cat_id, sd_model_id, sd_inv_n)
                 VALUES (?, 'payment', 30, ?, 0, 0, '', 0, 0, 0, '', 0, 0, ?,
                         0, ?, '', 'ext_payment', '', ?, ?, 0, 0, ?, ?, ?, ?, 0, 0, 0)"
            );
            $stmt->bind_param(
                'iidsiiiiis',
                $deal_id, $payment_date, $amount, $payment_type,
                $now, $user_id, $ext_id, $payment_date, $place, $ch_num
            );
            $stmt->execute();
            $stmt->close();

            // новая дата возврата; суммы пересчитает Deal::recalculateAmounts() после коммита
            $stmt = $mysqli->prepare(
                "UPDATE rent_deals_act SET return_date = ?, last_sub_deal_ch_time = ? WHERE deal_id = ?"
            );
            $stmt->bind_param('iii', $ext_to, $now, $deal_id);
            $stmt->execute();
            $stmt->close();

            $applied[] = $deal_id;
        }

        $stmt = $mysqli->prepare(
            "UPDATE rent_ext_calc SET applied_time = ?, applied_user_id = ? WHERE client_id = ?"
        );
        $stmt->bind_param('iii', $now, $user_id, $client_id);
        $stmt->execute();
        $stmt->close();

        $mysqli->commit();
    } catch (Exception $e) {
        $mysqli->rollback();
        ext_calc_fail('Разноска не выполнена, ничего не записано: ' . $e->getMessage());
    }

    // вне транзакции: recalculateAmounts() при сбое делает die()
    foreach ($applied as $deal_id) {
        \bb\classes\Deal::recalculateAmounts($deal_id);
    }

    echo json_encode([
        'status'  => 'ok',
        'calc'    => ext_calc_snapshot($mysqli, $client_id),
        'applied' => count($applied),
    ], JSON_UNESCAPED_UNICODE);
}

function ext_calc_fail($msg)
{
    echo json_encode(['status' => 'error', 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
