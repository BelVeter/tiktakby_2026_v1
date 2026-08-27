<?php
/**
 * ОДНОРАЗОВЫЙ скрипт: добавляет колонки срока аренды в rent_orders и rent_orders_arch.
 *
 * Зачем отдельно от кода: `bron::insert()`/`update()` в ветке feature/repeat-client-detection
 * перечисляют rent_days/date_from/date_to явно. Если выкатить код раньше колонок, то между
 * деплоями любое сохранение брони — включая создание брони с сайта — упадёт. Поэтому DDL
 * приезжает отдельным пустым во всём остальном PR и выполняется ДО влития основной ветки.
 *
 * Запуск: https://tiktak.by/bb/one_time_add_rent_period.php?key=Deploy-Mb8941
 * Идемпотентен: повторный вызов ничего не делает и говорит об этом.
 * Удалить следующим PR после успешного прогона.
 *
 * SSH на прод с машины владельца не работает, поэтому DDL идёт по HTTPS
 * (см. память prod-ssh-blocked-migrate-works).
 */

// На проде display_errors выключен, и fatal обрывает вывод молча, выглядя как
// «скрипт просто не доработал». Включаем явно, иначе диагностировать нечем.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/Db.php');

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['key']) || $_GET['key'] !== 'Deploy-Mb8941') {
    http_response_code(403);
    echo "Нет доступа.\n";
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();
$mysqli->set_charset('utf8');

$columns = array(
    'rent_days' => "ADD COLUMN `rent_days` SMALLINT UNSIGNED NULL AFTER `validity`",
    'date_from' => "ADD COLUMN `date_from` INT NULL AFTER `rent_days`",
    'date_to'   => "ADD COLUMN `date_to` INT NULL AFTER `date_from`",
);

echo "=== Колонки срока аренды ===\n\n";

foreach (array('rent_orders', 'rent_orders_arch') as $table) {
    $existing = array();
    $res = $mysqli->query("SHOW COLUMNS FROM `$table`");
    if (!$res) {
        echo "$table: ОШИБКА чтения схемы: " . $mysqli->error . "\n";
        continue;
    }
    while ($row = $res->fetch_assoc()) {
        $existing[$row['Field']] = true;
    }

    $todo = array();
    foreach ($columns as $name => $clause) {
        if (!isset($existing[$name])) {
            $todo[] = $clause;
        }
    }

    if (!$todo) {
        echo "$table: все три колонки уже на месте, ничего не делаю\n";
        continue;
    }

    $sql = "ALTER TABLE `$table` " . implode(', ', $todo);
    if ($mysqli->query($sql)) {
        echo "$table: добавлено колонок " . count($todo) . "\n";
    } else {
        echo "$table: ОШИБКА: " . $mysqli->error . "\n";
        echo "  запрос: $sql\n";
    }
}

echo "\n=== Проверка результата ===\n";
foreach (array('rent_orders', 'rent_orders_arch') as $table) {
    $found = array();
    $res = $mysqli->query("SHOW COLUMNS FROM `$table`");
    while ($res && $row = $res->fetch_assoc()) {
        if (isset($columns[$row['Field']])) {
            $found[] = $row['Field'] . ' ' . $row['Type'] . ($row['Null'] === 'YES' ? ' NULL' : ' NOT NULL');
        }
    }
    echo "$table: " . (count($found) === 3 ? 'ОК — ' : 'НЕПОЛНО (' . count($found) . '/3) — ')
        . implode(', ', $found) . "\n";
}

// Строку в `migrations` намеренно НЕ вписываем: миграция 2026_08_27_120000 защищена
// Schema::hasColumn, поэтому штатный `migrate` при деплое отработает вхолостую и сам
// зарегистрируется. Ручная вставка тут только разошлась бы с нумерацией batch.
echo "\nГотово. Теперь можно вливать feature/repeat-client-detection.\n";
