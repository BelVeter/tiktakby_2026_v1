<?php
/**
 * ОДНОРАЗОВЫЙ скрипт: проверяет на БОЕВОЙ базе, что ветка feature/repeat-client-detection
 * реально работает, а не просто «страница открылась».
 *
 * Админ-страницы без сессии отдают форму логина, поэтому новый код на них не выполняется —
 * убедиться в работоспособности можно только прогнав классы напрямую здесь.
 *
 * Запуск: https://tiktak.by/bb/one_time_verify_rent_period.php?key=Deploy-Mb8941
 *
 * Пишущая проверка одна: вставка брони и её немедленное удаление по insert_id.
 * Без транзакции намеренно — движок таблицы не проверяется, а удаление по id
 * детерминировано при любом. Товар и его статус не трогаются (bron::insert() напрямую,
 * не createBronStrong), поэтому на каталог это не влияет.
 *
 * Удалить следующим PR.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/Db.php');
require_once(__DIR__ . '/Base.php');
require_once(__DIR__ . '/classes/bron.php');
require_once(__DIR__ . '/classes/ClientLookup.php');

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['key']) || $_GET['key'] !== 'Deploy-Mb8941') {
    http_response_code(403);
    echo "Нет доступа.\n";
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();
$mysqli->set_charset('utf8');

echo "=== 1. Схема ===\n";
foreach (array('rent_orders', 'rent_orders_arch') as $t) {
    $found = array();
    $res = $mysqli->query("SHOW COLUMNS FROM `$t`");
    while ($res && $row = $res->fetch_assoc()) {
        if (in_array($row['Field'], array('rent_days', 'date_from', 'date_to'), true)) {
            $found[] = $row['Field'];
        }
    }
    echo "  $t: " . count($found) . "/3 " . implode(', ', $found) . "\n";
}

echo "\n=== 2. Поиск повторных клиентов на реальных бронях ===\n";
$rows = array();
$phones = array();
$res = $mysqli->query("SELECT order_id, phone, family FROM rent_orders
                       WHERE phone > 1 AND phone <> 2147483647 LIMIT 150");
while ($res && $row = $res->fetch_assoc()) {
    $rows[] = $row;
    $phones[] = $row['phone'];
}
echo "  взято броней/заявок с телефоном: " . count($rows) . "\n";

$t0 = microtime(true);
$map = \bb\classes\ClientLookup::forPhones($phones);
$ms = round((microtime(true) - $t0) * 1000, 1);
echo "  один батч-запрос: {$ms} мс\n";

$auto = 0; $choose = 0; $shared = 0; $none = 0;
foreach ($rows as $r) {
    $k = \bb\classes\ClientLookup::phoneKey($r['phone']);
    $ms_ = ($k !== null && isset($map[$k])) ? $map[$k] : array();
    if (!$ms_) { $none++; continue; }
    if (\bb\classes\ClientLookup::isSharedPhone($ms_)) { $shared++; }
    elseif (count($ms_) > 1) { $choose++; }
    else { $auto++; }
}
echo "  клиент подставится сразу:   $auto\n";
echo "  откроется выбор:            $choose\n";
echo "  номер помечен общим:        $shared\n";
echo "  новый клиент:               $none\n";

echo "\n=== 3. Разбор срока из текста старых броней ===\n";
$ok = 0; $fail = 0;
$res = $mysqli->query("SELECT info FROM rent_orders_arch
                       WHERE info LIKE '%клиент указал%' ORDER BY arch_time DESC LIMIT 2000");
while ($res && $row = $res->fetch_assoc()) {
    $p = \bb\classes\bron::parseRentPeriodFromInfo($row['info']);
    if ($p['days'] === null) { $fail++; } else { $ok++; }
}
echo "  проверено записей: " . ($ok + $fail) . "\n";
echo "  срок извлечён:     $ok\n";
echo "  не распознано:     $fail\n";

echo "\n=== 4. Запись брони со сроком (вставка + чтение + удаление) ===\n";
$b = new \bb\classes\bron();
$b->type = 'strong';
$b->type2 = 'bron';
$b->order_date = time();
$b->phone = 0;
$b->family = 'СЛУЖЕБНАЯ ПРОВЕРКА';
$b->validity = time();
$b->inv_n = 0;
$b->model_id = 0;
$b->cat_id = 0;
$b->info = 'служебная проверка схемы, удаляется тут же';
$b->cr_time = time();
$b->rent_days = 14;
$b->date_from = strtotime('2026-09-01');
$b->date_to = strtotime('2026-09-15');
$b->insert();
$id = (int)$b->insert_id;

if ($id > 0) {
    $chk = $mysqli->query("SELECT rent_days, date_from, date_to FROM rent_orders WHERE order_id=$id")->fetch_assoc();
    echo "  вставлено order_id=$id, дней={$chk['rent_days']}, "
        . "с=" . date('d.m.Y', $chk['date_from']) . ", по=" . date('d.m.Y', $chk['date_to']) . "\n";

    $period = \bb\classes\bron::rentPeriodFromRow(
        $mysqli->query("SELECT * FROM rent_orders WHERE order_id=$id")->fetch_assoc()
    );
    echo "  прочитано обратно: дней={$period['days']}\n";

    $mysqli->query("DELETE FROM rent_orders WHERE order_id=$id");
    $left = $mysqli->query("SELECT COUNT(*) c FROM rent_orders WHERE order_id=$id")->fetch_assoc();
    echo "  удалено: " . ($left['c'] == 0 ? 'да, следов не осталось' : 'НЕТ — ОСТАЛАСЬ СТРОКА ' . $id) . "\n";
} else {
    echo "  ОШИБКА: вставка не вернула id\n";
}

echo "\n=== 5. Учёт миграции ===\n";
$res = $mysqli->query("SELECT migration, batch FROM migrations
                       WHERE migration LIKE '%rent_period%' OR migration LIKE '%2026_08_27%'");
$anyMig = false;
while ($res && $row = $res->fetch_assoc()) {
    $anyMig = true;
    echo "  записана: {$row['migration']} (batch {$row['batch']})\n";
}
if (!$anyMig) {
    echo "  НЕ записана в `migrations` — на работу не влияет (колонки уже стоят,\n";
    echo "  а сама миграция защищена Schema::hasColumn), но при следующем migrate\n";
    echo "  она отработает вхолостую и зарегистрируется.\n";
}
$last = $mysqli->query("SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 3");
echo "  последние записи в migrations:\n";
while ($last && $row = $last->fetch_assoc()) {
    echo "    batch {$row['batch']}  {$row['migration']}\n";
}

echo "\nГотово.\n";
