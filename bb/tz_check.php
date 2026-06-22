<?php
// TEMPORARY diagnostic — delete after fix
// Check: curl https://tiktak.by/bb/tz_check.php
header('Content-Type: text/plain; charset=utf-8');
echo "PHP timezone:      " . date_default_timezone_get() . "\n";
echo "PHP date('Y-m-d'): " . date('Y-m-d') . "\n";
echo "PHP date('H:i:s'): " . date('H:i:s') . "\n";
echo "PHP time():        " . time() . "\n";
echo "PHP NOW human:     " . date('Y-m-d H:i:s') . "\n";

// Try to get MySQL timezone
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$db   = getenv('DB_DATABASE') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $row = $pdo->query("SELECT @@global.time_zone AS g, @@session.time_zone AS s, NOW() AS now, UTC_TIMESTAMP() AS utc")->fetch(PDO::FETCH_ASSOC);
    echo "\nMySQL global tz:   " . $row['g'] . "\n";
    echo "MySQL session tz:  " . $row['s'] . "\n";
    echo "MySQL NOW():       " . $row['now'] . "\n";
    echo "MySQL UTC_TS():    " . $row['utc'] . "\n";
} catch (Exception $e) {
    echo "\nMySQL connect error: " . $e->getMessage() . "\n";
}

echo "\n.user.ini date.timezone: ";
$ini = parse_ini_file(dirname(__DIR__) . '/.user.ini') ?: [];
echo ($ini['date.timezone'] ?? '(not set)') . "\n";

echo ".htaccess check: see PHP tz above.\n";
