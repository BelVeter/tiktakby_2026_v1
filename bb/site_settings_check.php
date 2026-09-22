<?php
/**
 * ОДНОРАЗОВЫЙ диагностический скрипт. Удалить следующим PR.
 *
 * Только читает. Отвечает на один вопрос: доехала ли на прод таблица
 * `site_settings` (миграция 2026_09_22_120000_create_site_settings_table),
 * если `php artisan migrate` в Deploy.php трижды напечатал
 * «Nothing to migrate».
 *
 * Ничего не меняет: ни схему, ни данные.
 *
 * Гейт — тот же ключ, что у Deploy.php:
 *   https://tiktak.by/bb/site_settings_check.php?key=Deploy-Mb8941
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['key']) || $_GET['key'] !== 'Deploy-Mb8941') {
    http_response_code(403);
    exit("нет доступа\n");
}

require_once __DIR__ . '/Db.php';

$mysqli = \bb\Db::getInstance()->getConnection();

echo "БД: " . $mysqli->query("SELECT DATABASE()")->fetch_row()[0] . "\n";
echo "Время сервера: " . $mysqli->query("SELECT NOW()")->fetch_row()[0] . "\n\n";

$exists = $mysqli->query("SHOW TABLES LIKE 'site_settings'")->num_rows > 0;
echo "Таблица site_settings: " . ($exists ? "ЕСТЬ" : "НЕТ") . "\n";

if ($exists) {
    echo "\nСхема:\n";
    $result = $mysqli->query("SHOW COLUMNS FROM `site_settings`");
    while ($row = $result->fetch_assoc()) {
        printf("  %-15s %-15s null=%-3s key=%s\n", $row['Field'], $row['Type'], $row['Null'], $row['Key']);
    }

    echo "\nСтроки:\n";
    $result = $mysqli->query("SELECT * FROM `site_settings`");
    if ($result->num_rows === 0) {
        echo "  (пусто — переключатель ещё не трогали)\n";
    }
    while ($row = $result->fetch_assoc()) {
        printf("  %s = %s  (%s, %s)\n", $row['setting_key'], var_export($row['setting_value'], true),
            $row['updated_at'], $row['updated_by']);
    }
}

echo "\nЗарегистрирована ли миграция:\n";
$result = $mysqli->query(
    "SELECT migration, batch FROM `migrations`
     WHERE migration LIKE '%create_site_settings_table%'"
);
if ($result->num_rows === 0) {
    echo "  нет записи в migrations\n";
}
while ($row = $result->fetch_assoc()) {
    printf("  batch %-3s %s\n", $row['batch'], $row['migration']);
}

echo "\nПоследние 5 миграций:\n";
$result = $mysqli->query("SELECT migration, batch FROM `migrations` ORDER BY id DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    printf("  batch %-3s %s\n", $row['batch'], $row['migration']);
}
