<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'tiktakby');
if ($mysqli->connect_error) { die('Connect Error: ' . $mysqli->connect_error); }
$res = $mysqli->query("SELECT url_name, l3_pic FROM rent_model_web WHERE url_name IN ('4momsmamaroosleep_bassinet', 'CybexBaliosSLux_2025_prokat', 'vesy_detskie_laica_MD6141', '4moms-mamaroo-40-prokat', 'StokkeFlexi_Bath', 'philips_avent_philips_avent_scd_501')");
if($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['url_name'] . ": " . $row['l3_pic'] . "\n";
    }
} else {
    echo "Query 1 failed: " . $mysqli->error . "\n";
}
$res2 = $mysqli->query("SELECT item_url, pic FROM tovars WHERE item_url IN ('4momsmamaroosleep_bassinet', 'CybexBaliosSLux_2025_prokat', 'vesy_detskie_laica_MD6141', '4moms-mamaroo-40-prokat', 'StokkeFlexi_Bath', 'philips_avent_philips_avent_scd_501')");
if($res2) {
    while ($row = $res2->fetch_assoc()) {
        echo $row['item_url'] . " (tovars): " . $row['pic'] . "\n";
    }
}
