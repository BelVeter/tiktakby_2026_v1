<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = new mysqli("localhost", "root", "", "tiktakby_tiktak");
$mysqli->set_charset("utf8mb4");

$queries = [
    "Автокресло" => "pebble",
    "Стульчик" => "siesta",
    "Прыгунки" => "precious_planet",
    "Коляска Volo" => "volo",
    "Коврик" => "tiny_love",
    "Радионяня 711" => "scd711"
];

foreach ($queries as $label => $term) {
    $q = "SELECT model, pic1, pic2 FROM tovar_rent WHERE pic1 LIKE '%$term%' OR pic2 LIKE '%$term%' OR model LIKE '%$term%' LIMIT 1";
    $res = $mysqli->query($q);
    if ($res && $row = $res->fetch_assoc()) {
        echo "$label -> " . ($row['pic1'] ?: $row['pic2'] ?: 'no pic') . "\n";
    } else {
        echo "$label -> not found\n";
    }
}
$mysqli->close();
