<?php
/**
 * AJAX endpoint: search tovar_rent models by name or ID.
 * GET ?q=<query>  → JSON [{id, label, free_count}, ...]
 * If q is all-digits → search by tovar_rent_id too.
 * Returns max 10 results with free item count (status=to_rent or expired t_bron).
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
$now = time();

$freeJoin = "LEFT JOIN tovar_rent_items ti ON ti.model_id = tr.tovar_rent_id
                  AND (ti.`status`='to_rent' OR (ti.`status`='t_bron' AND ti.br_time < $now))";

if (ctype_digit($q)) {
    $sql = "SELECT tr.tovar_rent_id, CONCAT(tr.producer, ' ', tr.model) AS label,
                   COUNT(ti.item_id) AS free_count
            FROM tovar_rent tr
            $freeJoin
            WHERE tr.tovar_rent_id = " . (int)$q . "
               OR CONCAT(tr.producer, ' ', tr.model) LIKE '%" . $esc . "%'
            GROUP BY tr.tovar_rent_id
            ORDER BY tr.tovar_rent_id = " . (int)$q . " DESC, tr.producer, tr.model
            LIMIT 10";
} else {
    $sql = "SELECT tr.tovar_rent_id, CONCAT(tr.producer, ' ', tr.model) AS label,
                   COUNT(ti.item_id) AS free_count
            FROM tovar_rent tr
            $freeJoin
            WHERE CONCAT(tr.producer, ' ', tr.model) LIKE '%" . $esc . "%'
            GROUP BY tr.tovar_rent_id
            ORDER BY tr.producer, tr.model
            LIMIT 10";
}

$res = $mysqli->query($sql);
$out = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id'         => (int)$row['tovar_rent_id'],
            'label'      => $row['label'],
            'free_count' => (int)$row['free_count'],
        ];
    }
}
echo json_encode($out);
