<?php
/**
 * AJAX endpoint: returns count of unprocessed bookings as JSON.
 * Used by bb_nav.php badge refresh.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// Auth check
if (!isset($_SESSION['svoi']) || $_SESSION['svoi'] != 8941) {
    echo json_encode(['count_bron' => 0, 'count_zayavk' => 0]);
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

// Count unhandled Zayavki
$query_zayavk = "SELECT COUNT(*) as cnt FROM rent_orders WHERE type2='zayavka' AND appr_id<1";
$result_zayavk = $mysqli->query($query_zayavk);
$count_zayavk = 0;
if ($result_zayavk && $row = $result_zayavk->fetch_assoc()) {
    $count_zayavk = (int) $row['cnt'];
}

echo json_encode(['count_bron' => $count_bron, 'count_zayavk' => $count_zayavk]);
