<?php
require_once(__DIR__ . '/Db.php');

$mysqli = \bb\Db::getInstance()->getConnection();

$colName = 'price_new';
$tableName = 'tovar_rent';

// Check if column exists
$checkQuery = "SHOW COLUMNS FROM `$tableName` LIKE '$colName'";

$result = $mysqli->query($checkQuery);

if ($result && $result->num_rows > 0) {
    echo "Column '$colName' already exists in '$tableName'.\n";
} else {
    $sql = "ALTER TABLE `$tableName` ADD COLUMN `$colName` INT NOT NULL DEFAULT 0";
    if ($mysqli->query($sql) === TRUE) {
        echo "Column '$colName' added successfully to '$tableName'.\n";
    } else {
        echo "Error adding column: " . $mysqli->error . "\n";
    }
}
?>