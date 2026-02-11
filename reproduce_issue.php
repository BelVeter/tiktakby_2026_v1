<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);


require_once(__DIR__ . '/bb/Db.php');
require_once(__DIR__ . '/bb/classes/ModelWeb.php');


$mysqli = \bb\Db::getInstance()->getConnection();

echo "Checking L2 and L3 image paths...\n";

// Fetch 20 models
$query = "SELECT model_id, l2_pic, m_pic_big FROM rent_model_web LIMIT 20";
$result = $mysqli->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $l2 = $row['l2_pic'];
        $l3 = $row['m_pic_big'];

        // $l2_fixed = \bb\classes\ModelWeb::getURLCorrectPathFor($l2);
        // $l3_fixed = \bb\classes\ModelWeb::getURLCorrectPathFor($l3);

        echo "Model ID: " . $row['model_id'] . "\n";
        echo "  L2 Raw:   [" . $l2 . "]\n";
        // echo "  L2 Fixed: [" . $l2_fixed . "]\n";
        echo "  L3 Raw:   [" . $l3 . "]\n";
        // echo "  L3 Fixed: [" . $l3_fixed . "]\n";

        $l2_exists = file_exists($_SERVER['DOCUMENT_ROOT'] . $l2) ? "OK" : "MISSING";
        $l3_exists = file_exists($_SERVER['DOCUMENT_ROOT'] . $l3) ? "OK" : "MISSING";

        echo "  L2 Check: " . $l2_exists . " (" . $_SERVER['DOCUMENT_ROOT'] . $l2 . ")\n";
        echo "  L3 Check: " . $l3_exists . " (" . $_SERVER['DOCUMENT_ROOT'] . $l3 . ")\n";

        echo "--------------------------------------------------\n";
    }
} else {
    echo "Query failed: " . $mysqli->error . "\n";
}
