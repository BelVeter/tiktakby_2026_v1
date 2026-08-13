<?php
/**
 * ОДНОРАЗОВЫЙ установщик таблицы rent_ext_calc — удалить сразу после прогона на проде.
 *
 * `php artisan migrate` на проде сломан ionCube-лоадером (docs/db_notes.md, п.7),
 * поэтому DDL применяем напрямую и вручную отмечаем миграцию в `migrations`,
 * чтобы Laravel не пытался накатить её повторно.
 *
 * Идемпотентен: повторный запуск ничего не меняет, только показывает состояние.
 * Ключ доступа — тот же, что у Deploy.php.
 */

$secret_key = 'Deploy-Mb8941';
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die('Access Denied');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');

header('Content-Type: text/plain; charset=utf-8');

$migration = '2026_08_13_120000_create_rent_ext_calc_table';
$mysqli = \bb\Db::getInstance()->getConnection();

$res = $mysqli->query("SHOW TABLES LIKE 'rent_ext_calc'");
$existed = $res && $res->num_rows > 0;
echo "таблица rent_ext_calc до запуска: " . ($existed ? "есть\n" : "нет\n");

if (!$existed) {
    $ddl = "CREATE TABLE `rent_ext_calc` (
        `calc_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `deal_id` int(11) NOT NULL,
        `item_inv_n` int(11) NOT NULL,
        `ext_from` int(11) NOT NULL,
        `ext_to` int(11) NOT NULL,
        `ext_days` int(11) NOT NULL,
        `amount` decimal(11,2) NOT NULL,
        `calc_time` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        PRIMARY KEY (`calc_id`),
        KEY `idx_rec_client` (`client_id`),
        KEY `idx_rec_deal` (`deal_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

    if (!$mysqli->query($ddl)) {
        echo "ОШИБКА CREATE TABLE: " . $mysqli->error . "\n";
        exit;
    }
    echo "таблица создана\n";
}

// отметка миграции, чтобы будущий рабочий `artisan migrate` её не повторил
$stmt = $mysqli->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->bind_param('s', $migration);
$stmt->execute();
$stmt->bind_result($marked);
$stmt->fetch();
$stmt->close();

if ($marked > 0) {
    echo "миграция уже отмечена в migrations\n";
} else {
    $res = $mysqli->query("SELECT COALESCE(MAX(batch), 0) + 1 AS b FROM migrations");
    $batch = (int)$res->fetch_assoc()['b'];

    $stmt = $mysqli->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
    $stmt->bind_param('si', $migration, $batch);
    if (!$stmt->execute()) {
        echo "ОШИБКА INSERT migrations: " . $stmt->error . "\n";
        exit;
    }
    $stmt->close();
    echo "миграция отмечена, batch $batch\n";
}

$res = $mysqli->query("SHOW CREATE TABLE rent_ext_calc");
echo "\n" . $res->fetch_row()[1] . "\n";

echo "\nГотово. Файл bb/install_rent_ext_calc.php больше не нужен — удалить.\n";
