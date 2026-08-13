<?php
/**
 * ОДНОРАЗОВАЯ проверка/досоздание схемы rent_ext_calc — удалить после прогона.
 *
 * `php artisan migrate` в Deploy.php сообщает «Nothing to migrate», хотя миграция
 * новая, поэтому состояние схемы на проде проверяем явно. Идемпотентен.
 * Ключ доступа — тот же, что у Deploy.php.
 */

$secret_key = 'Deploy-Mb8941';
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die('Access Denied');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
header('Content-Type: text/plain; charset=utf-8');

$mysqli = \bb\Db::getInstance()->getConnection();

$res = $mysqli->query("SHOW TABLES LIKE 'rent_ext_calc'");
if (!$res || $res->num_rows === 0) {
    echo "таблицы rent_ext_calc НЕТ — сначала нужна миграция создания\n";
    exit;
}

$columns = [];
$res = $mysqli->query("SHOW COLUMNS FROM rent_ext_calc");
while ($row = $res->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo "колонки rent_ext_calc: " . implode(', ', $columns) . "\n\n";

$added = [];
foreach (['applied_time', 'applied_user_id'] as $column) {
    if (in_array($column, $columns, true)) {
        echo "$column: есть\n";
        continue;
    }
    if (!$mysqli->query("ALTER TABLE rent_ext_calc ADD `$column` INT(11) NOT NULL DEFAULT 0")) {
        echo "$column: ОШИБКА ALTER — " . $mysqli->error . "\n";
        exit;
    }
    echo "$column: добавлена\n";
    $added[] = $column;
}

$migration = '2026_08_13_180000_add_applied_to_rent_ext_calc';
$stmt = $mysqli->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->bind_param('s', $migration);
$stmt->execute();
$stmt->bind_result($marked);
$stmt->fetch();
$stmt->close();

if ($marked > 0) {
    echo "\nмиграция отмечена в migrations\n";
} else {
    $res = $mysqli->query("SELECT COALESCE(MAX(batch), 0) + 1 AS b FROM migrations");
    $batch = (int)$res->fetch_assoc()['b'];
    $stmt = $mysqli->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
    $stmt->bind_param('si', $migration, $batch);
    $stmt->execute();
    $stmt->close();
    echo "\nмиграция отмечена вручную, batch $batch\n";
}

echo "\nпоследние записи migrations:\n";
$res = $mysqli->query("SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 6");
while ($row = $res->fetch_assoc()) {
    echo "  {$row['batch']}  {$row['migration']}\n";
}

echo "\nГотово. Файл bb/install_rent_ext_calc.php удалить.\n";
