<?php
// auto_prepend_file — подключается PHP-движком перед ЛЮБЫМ .php в bb/
// (см. bb/.htaccess). Логирует заход залогиненного сотрудника на реальную
// (не техническую) страницу в bb_page_visits. Никогда не должен ронять
// страницу, которую открывает сотрудник — ошибки логирования проглатываются.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    return;
}

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
if ($scriptName === '') {
    return;
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    return;
}

require_once __DIR__ . '/PageVisitCatalog.php';

if (\bb\classes\PageVisitCatalog::isTechnical($scriptName)) {
    return;
}

require_once __DIR__ . '/../Db.php';

try {
    $mysqli = \bb\Db::getInstance()->getConnection();
    $userId = (int) $_SESSION['user_id'];
    $page   = $mysqli->real_escape_string($scriptName);
    $mysqli->query("INSERT INTO bb_page_visits (user_id, page, visited_at) VALUES ({$userId}, '{$page}', NOW())");
} catch (\Throwable $e) {
    error_log('PageVisitTracker: ' . $e->getMessage());
}
