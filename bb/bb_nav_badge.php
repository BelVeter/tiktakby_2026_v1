<?php
/**
 * AJAX endpoint: returns count of unprocessed bookings as JSON.
 * Used by bb_nav.php badge refresh.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// Auth check
if (!isset($_SESSION['svoi']) || $_SESSION['svoi'] != 8941) {
    echo json_encode(['count' => 0]);
    exit;
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');

$mysqli = \bb\Db::getInstance()->getConnection();

$query = "SELECT COUNT(*) as cnt FROM rent_orders WHERE (type2='bron' OR type2='deliv') AND appr_id<1";
$result = $mysqli->query($query);

$count = 0;
if ($result) {
    $row = $result->fetch_assoc();
    $count = (int) $row['cnt'];
}

echo json_encode(['count' => $count]);
