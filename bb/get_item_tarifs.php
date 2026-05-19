<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');

isset($_SESSION['svoi']) ? : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$inv_n = intval($_GET['inv_n'] ?? 0);
if ($inv_n <= 0) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();

$inv_n_safe = $mysqli->real_escape_string($inv_n);
$q = "SELECT i.item_inv_n, i.model_id, i.item_set, i.item_place, i.status, i.br_time,
             m.model, m.agr_price, m.agr_price_cur, m.model_addr, m.producer,
             c.dog_name
      FROM tovar_rent_items i
      JOIN tovar_rent m ON m.tovar_rent_id = i.model_id
      JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = m.tovar_rent_cat_id
      WHERE i.item_inv_n = '$inv_n_safe'";

$res = $mysqli->query($q);
if (!$res || $res->num_rows === 0) {
    echo json_encode(['status' => 'not_found']);
    exit;
}
$item = $res->fetch_assoc();

$available = ($item['status'] === 'to_rent')
    || (in_array($item['status'], ['bron', 't_bron']) && $item['br_time'] < time());
if (!$available) {
    echo json_encode(['status' => 'not_available', 'item_status' => $item['status']]);
    exit;
}

$model_id = intval($item['model_id']);
$q_t = "SELECT tarif_id, step, kol_vo, kol_vo_min, rent_amount, rent_per_step, sort_num
        FROM rent_tarif_act
        WHERE model_id = '$model_id'
        ORDER BY sort_num, kol_vo";
$res_t = $mysqli->query($q_t);

$tarifs = [];
while ($t = $res_t->fetch_assoc()) {
    $tarifs[] = [
        'tarif_id'      => (int)$t['tarif_id'],
        'step'          => $t['step'],
        'kol_vo'        => (int)$t['kol_vo'],
        'kol_vo_min'    => (int)$t['kol_vo_min'],
        'rent_amount'   => (float)$t['rent_amount'],
        'rent_per_step' => (float)$t['rent_per_step'],
        'sort_num'      => (int)$t['sort_num'],
        'days'          => (int)$t['sort_num'] * (int)$t['kol_vo'],
    ];
}

$item_name = ($item['model_addr'] !== '' ? $item['model_addr'] : $item['dog_name'])
           . ': ' . $item['model']
           . ' (инв.№' . substr((string)$inv_n, 0, 3) . '-' . substr((string)$inv_n, 3) . ')';

echo json_encode([
    'status'        => 'ok',
    'item_name'     => $item_name,
    'item_set'      => $item['item_set'],
    'model_id'      => $model_id,
    'agr_price'     => (float)$item['agr_price'],
    'agr_price_cur' => $item['agr_price_cur'],
    'tarifs'        => $tarifs,
], JSON_UNESCAPED_UNICODE);
