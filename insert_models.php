<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'tiktakby');
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8");

$models = [
    ['name' => 'Подписка Premium Start - Базовый', 'price' => 100],
    ['name' => 'Подписка Premium Start - Оптимальный', 'price' => 200],
    ['name' => 'Подписка Premium Start - Премиум', 'price' => 300]
];

foreach ($models as $m) {
    $stmt = $mysqli->prepare("INSERT INTO tovar_rent (tovar_rent_cat_id, producer, model, \`set\`, color, age_from, age_to, weight_from, weight_to, collateral, m_sex, agr_price_cur, user, model_addr, ph_addr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $cat_id = 27; // "услуги/сертификаты"
    $producer = 'TikTak';
    $model = $m['name'];
    $set = 'стандартный'; 
    $color = 'multicolor';
    $age_from = 0;
    $age_to = 36;
    $w_from = 0; $w_to = 99;
    $collat = 0;
    $m_sex = 'u';
    $agr_price_cur = 'BYN';
    $user = 'admin';
    $model_addr = ''; $ph_addr = '';
    
    $stmt->bind_param("issssiiiiiissss", $cat_id, $producer, $model, $set, $color, $age_from, $age_to, $w_from, $w_to, $collat, $m_sex, $agr_price_cur, $user, $model_addr, $ph_addr);
    $stmt->execute();
    
    $model_id = $mysqli->insert_id;
    echo $model . " ID: " . $model_id . "\n";
    
    // Create one item so it's formally valid
    $stmt2 = $mysqli->prepare("INSERT INTO tovar_rent_items (cat_id, producer, model_id, item_n, item_inv_n, sex, item_size, real_item_size, item_rost1, item_rost2, item_set, status, item_color, seller, user, item_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $item_n = 1; 
    $item_inv_n = $model_id * 1000 + 1; // Fake inv_n
    $size = 'n/a';
    $r1 = 0; $r2 = 99;
    $status = 'to_rent';
    $seller = 'owner';
    $info = 'virtual item for subscriptions';
    $stmt2->bind_param("isiissssiissssss", $cat_id, $producer, $model_id, $item_n, $item_inv_n, $m_sex, $size, $size, $r1, $r2, $set, $status, $color, $seller, $user, $info);
    $stmt2->execute();
}
echo "Done.\n";
