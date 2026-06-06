<?php
/**
 * AJAX endpoint: search tovar_rent models by name or ID.
 * GET ?q=<query>  → JSON [{id, label}, ...]
 * If q is all-digits → search by tovar_rent_id too.
 * Returns max 10 results.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['svoi']) || $_SESSION['svoi'] != 8941) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();
$esc = $mysqli->real_escape_string($q);

if (ctype_digit($q)) {
    // поиск по ID и по названию
    $sql = "SELECT tovar_rent_id, CONCAT(producer, ' ', model) AS label
            FROM tovar_rent
            WHERE tovar_rent_id = " . (int)$q . "
               OR CONCAT(producer, ' ', model) LIKE '%" . $esc . "%'
            ORDER BY tovar_rent_id = " . (int)$q . " DESC, producer, model
            LIMIT 10";
} else {
    $sql = "SELECT tovar_rent_id, CONCAT(producer, ' ', model) AS label
            FROM tovar_rent
            WHERE CONCAT(producer, ' ', model) LIKE '%" . $esc . "%'
            ORDER BY producer, model
            LIMIT 10";
}

$res = $mysqli->query($sql);
$out = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $out[] = ['id' => (int)$row['tovar_rent_id'], 'label' => $row['label']];
    }
}
echo json_encode($out);
