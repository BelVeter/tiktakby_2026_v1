<?php
/**
 * AJAX endpoint: returns count of unprocessed bookings as JSON.
 * Used by bb_nav.php badge refresh.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// Auth check
if (!isset($_SESSION['svoi']) || $_SESSION['svoi'] != 8941) {
    echo json_encode(['count_bron' => 0, 'count_zayavk_new' => 0, 'count_zayavk_avail' => 0]);
    exit;
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');

$mysqli = \bb\Db::getInstance()->getConnection();

// Count unhandled Broni + Deliv
$query_bron = "SELECT COUNT(*) as cnt FROM rent_orders WHERE (type2='bron' OR type2='deliv') AND appr_id<1";
$result_bron = $mysqli->query($query_bron);
$count_bron = 0;
if ($result_bron && $row = $result_bron->fetch_assoc()) {
    $count_bron = (int) $row['cnt'];
}

// Count NEW unprocessed Zayavki (no staff actions — info2 is empty)
$query_new = "SELECT COUNT(*) as cnt FROM rent_orders WHERE type2='zayavka' AND (info2 IS NULL OR info2 = '')";
$result_new = $mysqli->query($query_new);
$count_zayavk_new = 0;
if ($result_new && $row = $result_new->fetch_assoc()) {
    $count_zayavk_new = (int) $row['cnt'];
}

// Count Zayavki where the item has become available
$now = time();
$query_avail = "SELECT COUNT(DISTINCT ro.order_id) as cnt
    FROM rent_orders ro
    INNER JOIN tovar_rent_items tri ON tri.model_id = ro.model_id
    WHERE ro.type2 = 'zayavka'
      AND (tri.status = 'to_rent' OR (tri.status = 't_bron' AND tri.br_time < $now))";
$result_avail = $mysqli->query($query_avail);
$count_zayavk_avail = 0;
if ($result_avail && $row = $result_avail->fetch_assoc()) {
    $count_zayavk_avail = (int) $row['cnt'];
}

echo json_encode(['count_bron' => $count_bron, 'count_zayavk_new' => $count_zayavk_new, 'count_zayavk_avail' => $count_zayavk_avail]);
