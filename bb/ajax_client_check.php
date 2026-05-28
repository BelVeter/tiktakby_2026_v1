<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');

header('Content-Type: application/json');

// Проверка авторизации
$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    echo json_encode(['error' => 'Доступ запрещен.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$field = $_POST['field'] ?? '';
$value = trim($_POST['value'] ?? '');

if (empty($value)) {
    echo json_encode(['exists' => false]);
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();

$client = null;

if ($field === 'pas_ln' && mb_strlen($value) >= 14) {
    $val = $mysqli->real_escape_string($value);
    $query = "SELECT client_id, family, name, otch FROM clients WHERE pas_ln = '$val' LIMIT 1";
    $res = $mysqli->query($query);
    if ($res && $res->num_rows > 0) {
        $client = $res->fetch_assoc();
    }
} elseif ($field === 'phone_1') {
    $clean_phone = preg_replace('/[^0-9]/', '', $value);
    if (strlen($clean_phone) >= 7) {
        $cp = $mysqli->real_escape_string($clean_phone);
        $clean_expr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone_1, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')";
        $clean_expr2 = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone_2, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')";
        $query = "SELECT client_id, family, name, otch FROM clients WHERE $clean_expr = '$cp' OR $clean_expr2 = '$cp' LIMIT 1";
        $res = $mysqli->query($query);
        if ($res && $res->num_rows > 0) {
            $client = $res->fetch_assoc();
        }
    }
}

if ($client) {
    echo json_encode(['exists' => true, 'client' => $client]);
} else {
    echo json_encode(['exists' => false]);
}
