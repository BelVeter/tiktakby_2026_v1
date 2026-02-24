<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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
    $stmt = $mysqli->prepare("INSERT INTO tovar_rent (tovar_rent_cat_id, producer, model, `set`, color, age_from, age_to, weight_from, weight_to, collateral, m_sex, agr_price_cur, user, model_addr, ph_addr, agr_price, lom_srok, cr_ch_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        die("Prepare failed: " . $mysqli->error);
    }
    $cat_id = 27; // "услуги/сертификаты"
    $producer = 'TikTak';
    $model_name = $m['name'];
    $set = 'стандартный';
    $color = 'multicolor';
    $age_from = 0;
    $age_to = 36;
    $w_from = 0;
    $w_to = 99;
    $collat = 0;
    $m_sex = 'u';
    $agr_price_cur = 'BYN';
    $user = 'admin';
    $model_addr = '';
    $ph_addr = '';
    $agr_price = 0;
    $lom_srok = 0;
    $cr_ch_date = time();

    $stmt->bind_param("issssiiiiiissssddi", $cat_id, $producer, $model_name, $set, $color, $age_from, $age_to, $w_from, $w_to, $collat, $m_sex, $agr_price_cur, $user, $model_addr, $ph_addr, $agr_price, $lom_srok, $cr_ch_date);
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    $model_id = $mysqli->insert_id;
    echo $model_name . " ID: " . $model_id . "\n";

    // Create one item so it's formally valid
    $stmt2 = $mysqli->prepare("INSERT INTO tovar_rent_items (cat_id, producer, model_id, item_n, item_inv_n, sex, item_size, real_item_size, item_rost1, item_rost2, item_set, status, item_color, seller, user, item_info, cr_ch_date, buy_date, buy_price, buy_price_cur, exch_to_byr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt2) {
        die("Prepare2 failed: " . $mysqli->error);
    }
    $item_n = 1;
    $item_inv_n = $model_id * 1000 + 1; // Fake inv_n
    $size = 'n/a';
    $r1 = 0;
    $r2 = 99;
    $status = 'to_rent';
    $seller = 'owner';
    $info = 'virtual item for subscriptions';
    $buy_date = time();
    $buy_price = 0;
    $buy_price_cur = 'BYN';
    $exch = 1;

    $stmt2->bind_param("isiissssiissssssiidss", $cat_id, $producer, $model_id, $item_n, $item_inv_n, $m_sex, $size, $size, $r1, $r2, $set, $status, $color, $seller, $user, $info, $cr_ch_date, $buy_date, $buy_price, $buy_price_cur, $exch);
    if (!$stmt2->execute()) {
        die("Execute2 failed: " . $stmt2->error);
    }
}
echo "Done.\n";
