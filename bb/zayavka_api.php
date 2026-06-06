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
        $z = Zayavka::load((int)($_GET['order_id'] ?? 0));
        echo json_encode(['ok' => true, 'zayavka' => [
            'order_id'     => $z->order_id,
            'model_id'     => $z->model_id,
            'phone'        => $z->phone,
            'family'       => $z->family,
            'info'         => $z->info,
            'info2'        => $z->info2,
            'planned_date' => $z->planned_date,
            'z_status'     => $z->z_status,
            'ch_time'      => $z->ch_time,
        ]]);
    } elseif ($action === 'save') {
        $z = Zayavka::load((int)($_POST['order_id'] ?? 0));
        $z->update([
            'info'         => $_POST['info'] ?? '',
            'planned_date' => $_POST['planned_date'] ?? null,
            'last_ch_time' => $_POST['last_ch_time'] ?? $z->ch_time,
        ]);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'change_model') {
        $z = Zayavka::load((int)($_POST['order_id'] ?? 0));
        $z->changeModel((int)($_POST['model_id'] ?? 0));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'set_status') {
        $z = Zayavka::load((int)($_POST['order_id'] ?? 0));
        $z->setStatus($_POST['status'] ?? '', $_POST['reason'] ?? null);
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
    }
} catch (\Throwable $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
}
