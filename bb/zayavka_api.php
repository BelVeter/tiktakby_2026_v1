<?php
/**
 * AJAX endpoint for working with a single заявка (rent_orders.type2='zayavka').
 * All logic goes through bb\classes\Zayavka so the board (rent_zayavk.php) and the
 * popup on the calls page (zv_ch.php) share one backend.
 *
 * Actions (via ?action=):
 *   load         (GET)  order_id            -> {ok, zayavka}
 *   save         (POST) order_id, info?, planned_date?, last_ch_time
 *   change_model (POST) order_id, model_id
 *   set_status   (POST) order_id, status, reason?
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/bron.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Zayavka.php');

// auth — same gate as other bb/ admin pages
if (!isset($_SESSION['svoi']) || $_SESSION['svoi'] != 8941) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

use bb\classes\Zayavka;

$action = $_REQUEST['action'] ?? 'load';

try {
    if ($action === 'load') {
        $orderId = (int)($_GET['order_id'] ?? 0);
        $c = \bb\Db::getInstance()->getConnection();
        $ra = $c->query("SELECT * FROM rent_orders WHERE order_id=" . $orderId . " AND type2='zayavka' LIMIT 1");
        if ($ra && $ra->num_rows > 0) {
            $z = Zayavka::fromRow($ra->fetch_assoc(), $c);
            echo json_encode(['ok' => true, 'archived' => false, 'zayavka' => [
                'order_id'      => $z->order_id,
                'model_id'      => $z->model_id,
                'phone'         => $z->phone,
                'family'        => $z->family,
                'info'          => $z->info,
                'info2'         => $z->info2,
                'planned_date'  => $z->planned_date,
                'z_status'      => $z->z_status,
                'ch_time'       => $z->ch_time,
                'validity_date' => $z->validity ? date('Y-m-d', (int)$z->validity) : '',
            ]]);
        } else {
            $arch = $c->query("SELECT * FROM rent_orders_arch WHERE order_id=" . $orderId . " AND type2='zayavka' LIMIT 1");
            if (!$arch || $arch->num_rows < 1) {
                throw new \RuntimeException('Zayavka not found: ' . $orderId);
            }
            $z = Zayavka::fromRow($arch->fetch_assoc(), $c);
            echo json_encode(['ok' => true, 'archived' => true, 'zayavka' => [
                'order_id'        => $z->order_id,
                'model_id'        => $z->model_id,
                'phone'           => $z->phone,
                'family'          => $z->family,
                'info'            => $z->info,
                'info2'           => $z->info2,
                'z_status'        => $z->z_status,
                'z_reject_reason' => $z->z_reject_reason,
            ]]);
        }
    } elseif ($action === 'save') {
        if (!isset($_POST['last_ch_time'])) {
            http_response_code(400);
            echo json_encode(['error' => 'last_ch_time required (optimistic lock)']);
            exit;
        }
        $z = Zayavka::load((int)($_POST['order_id'] ?? 0));
        $z->update([
            'info'          => $_POST['info'] ?? '',
            'planned_date'  => $_POST['planned_date'] ?? null,
            'validity_date' => $_POST['validity_date'] ?? null,
            'last_ch_time'  => $_POST['last_ch_time'],
        ]);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'change_model') {
        $z = Zayavka::load((int)($_POST['order_id'] ?? 0));
        $z->changeModel((int)($_POST['model_id'] ?? 0));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'set_status') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['rejected', 'spam', 'deleted', 'done'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid status']);
            exit;
        }
        $reason  = isset($_POST['reason'])         && $_POST['reason']         !== '' ? $_POST['reason']         : null;
        $comment = isset($_POST['reason_comment']) && $_POST['reason_comment'] !== '' ? $_POST['reason_comment'] : null;
        $z = Zayavka::load((int)($_POST['order_id'] ?? 0));
        $z->setStatus($status, $reason, $comment);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'create_new') {
        $modelId = (int)($_POST['model_id'] ?? 0);
        $phone   = (int)preg_replace('/[^0-9]/', '', (string)($_POST['phone'] ?? ''));
        $family  = trim($_POST['family'] ?? '');
        $zvId    = (int)($_POST['zv_id'] ?? 0);
        if ($modelId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'model_id required']);
            exit;
        }
        $z   = new Zayavka();
        $res = $z->create(['model_id' => $modelId, 'phone' => $phone, 'family' => $family], 'crm');
        if ($res->isDuplicate) {
            http_response_code(409);
            echo json_encode(['error' => 'Уже есть активная заявка #' . $res->existing->order_id]);
        } else {
            if ($zvId > 0 && $res->orderId) {
                $z->linkAfterCreate($res->orderId, $zvId);
            }
            echo json_encode(['ok' => true, 'order_id' => $res->orderId]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
    }
} catch (\Throwable $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
}
