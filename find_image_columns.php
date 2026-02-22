<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::select('SHOW TABLES');
echo "Tables with image-like columns:\n";
foreach ($tables as $table) {
    $table_name = reset($table);
    $columns = DB::select("SHOW COLUMNS FROM `{$table_name}`");
    $found = false;
    foreach ($columns as $col) {
        $field = strtolower($col->Field);
        if (strpos($field, 'pic') !== false || strpos($field, 'img') !== false || strpos($field, 'photo') !== false || strpos($field, 'image') !== false || strpos($field, 'icon') !== false) {
            if (!$found) {
                echo "\nTable: " . $table_name . "\n";
                $found = true;
            }
            echo "- " . $col->Field . " (" . $col->Type . ")\n";
        }
    }
}
