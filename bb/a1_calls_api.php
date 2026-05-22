<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Auth: requires bb/ session AND must be Дима (user_id=3)
if (!isset($_SESSION['svoi']) || $_SESSION['svoi'] != 8941) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] !== 3) {
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php';

$mysqli = \bb\Db::getInstance()->getConnection();

$action = $_POST['action'] ?? '';

if ($action === 'reset_analysis') {
    $uuid = trim($_POST['uuid'] ?? '');
    if (!$uuid || !preg_match('/^[a-zA-Z0-9_-]+$/', $uuid)) {
        echo json_encode(['error' => 'Invalid uuid']);
        exit;
    }
    $safeUuid = $mysqli->real_escape_string($uuid);
    $result = $mysqli->query(
        "UPDATE a1_call_analysis SET ai_status='pending', updated_at=NOW() WHERE recording_uuid='{$safeUuid}'"
    );
    if ($result && $mysqli->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Record not found or already pending']);
    }
    exit;
}

echo json_encode(['error' => 'Unknown action']);
