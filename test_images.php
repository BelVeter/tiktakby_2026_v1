<?php
require 'bb/Db.php';
require 'bb/classes/Level3.php';

$db = new \bb\Db();
$mysqli = $db->getConnection();
$aliases = ['4momsmamaroosleep_bassinet', 'CybexBaliosSLux_2025_prokat', 'vesy_detskie_laica_MD6141', '4moms-mamaroo-40-prokat', 'StokkeFlexi_Bath', 'philips_avent_philips_avent_scd_501'];

foreach ($aliases as $alias) {
    $res = $mysqli->query("SELECT l3_pic FROM rent_model_web WHERE url_name = '$alias'");
    if ($res && $row = $res->fetch_assoc()) {
        echo $alias . ": " . $row['l3_pic'] . "\n";
    } else {
        echo $alias . ": NOT FOUND\n";
    }
}
