<?php

use bb\Base;

session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php');

$mysqli = \bb\Db::getInstance()->getConnection();

// Provreka paroley (similar to doh-rash.php)
$in_level = array(0, 5, 7);
if (!isset($_SESSION['svoi']) || $_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    die('Авторизация требуется.');
}

$message = '';

// Handle Updates
// Handle Updates
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'save_rash') {
        $id = (int) $_POST['ri_id'];
        $text = $mysqli->real_escape_string($_POST['ri_text']);
        $order = (int) $_POST['ri_order'];
        $code = $mysqli->real_escape_string($_POST['ri_code']);
        $bank_yn = isset($_POST['bank_yn']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $query = "UPDATE rash_items SET ri_text='$text', ri_order='$order', ri_code='$code', bank_yn='$bank_yn', is_active='$is_active' WHERE ri_id='$id'";
        if ($mysqli->query($query)) {
            $message = '<div style="color: green; font-weight: bold;">Расход успешно обновлен.</div>';
        } else {
            $message = '<div style="color: red; font-weight: bold;">Ошибка: ' . $mysqli->error . '</div>';
        }
    } elseif ($_POST['action'] == 'add_rash') {
        $text = $mysqli->real_escape_string($_POST['ri_text']);
        $order = (int) $_POST['ri_order'];
        $code = $mysqli->real_escape_string($_POST['ri_code']);
        $bank_yn = isset($_POST['bank_yn']) ? 1 : 0;
        $query = "INSERT INTO rash_items (ri_text, ri_order, ri_code, bank_yn, is_active) VALUES ('$text', '$order', '$code', '$bank_yn', 1)";
        if ($mysqli->query($query)) {
            $message = '<div style="color: green; font-weight: bold;">Новая статья расхода добавлена.</div>';
        } else {
            $message = '<div style="color: red; font-weight: bold;">Ошибка: ' . $mysqli->error . '</div>';
        }
    } elseif ($_POST['action'] == 'save_doh') {
        $id = (int) $_POST['rd_id'];
        $text = $mysqli->real_escape_string($_POST['rd_text']);
        $order = (int) $_POST['rd_order'];
        $code = $mysqli->real_escape_string($_POST['rd_code']);
        $bank_yn = isset($_POST['bank_yn']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $query = "UPDATE doh_items SET rd_text='$text', rd_order='$order', rd_code='$code', bank_yn='$bank_yn', is_active='$is_active' WHERE rd_id='$id'";
        if ($mysqli->query($query)) {
            $message = '<div style="color: green; font-weight: bold;">Доход успешно обновлен.</div>';
        } else {
            $message = '<div style="color: red; font-weight: bold;">Ошибка: ' . $mysqli->error . '</div>';
        }
    } elseif ($_POST['action'] == 'add_doh') {
        $text = $mysqli->real_escape_string($_POST['rd_text']);
        $order = (int) $_POST['rd_order'];
        $code = $mysqli->real_escape_string($_POST['rd_code']);
        $bank_yn = isset($_POST['bank_yn']) ? 1 : 0;
        $query = "INSERT INTO doh_items (rd_text, rd_order, rd_code, bank_yn, is_active) VALUES ('$text', '$order', '$code', '$bank_yn', 1)";
        if ($mysqli->query($query)) {
            $message = '<div style="color: green; font-weight: bold;">Новая статья дохода добавлена.</div>';
        } else {
            $message = '<div style="color: red; font-weight: bold;">Ошибка: ' . $mysqli->error . '</div>';
        }
    }
}

echo '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Управление категориями</title>
    <link href="/bb/stile.css" rel="stylesheet" type="text/css" />
    <style>
        table { border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 5px; border: 1px solid #ccc; }
        input[type="text"], input[type="number"] { width: 100%; box-sizing: border-box; }
        .section-title { background: #f0f0f0; padding: 10px; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>

<div class="top_menu">
    <a class="div_item" href="/bb/index.php">На главную</a>
    <a class="div_item" href="/bb/doh-rash.php">Доходы-расходы</a>
</div>

<h1>Управление статьями расходов и доходов</h1>

' . $message . '

<div class="section-title">Статьи расходов</div>
<table>
    <tr>
        <th>ID</th>
        <th>Название</th>
        <th>Порядок</th>
        <th>Код</th>
        <th>Банк?</th>
        <th>Активно</th>
        <th>Действие</th>
    </tr>';

$res = $mysqli->query("SELECT * FROM rash_items ORDER BY ri_order");
while ($row = $res->fetch_assoc()) {
    echo '
    <form method="post">
    <tr>
        <td>' . $row['ri_id'] . '</td>
        <td><input type="text" name="ri_text" value="' . htmlspecialchars($row['ri_text']) . '"></td>
        <td><input type="number" name="ri_order" value="' . $row['ri_order'] . '" style="width: 70px;"></td>
        <td><input type="text" name="ri_code" value="' . htmlspecialchars($row['ri_code']) . '" style="width: 100px;"></td>
        <td style="text-align: center;"><input type="checkbox" name="bank_yn" value="1" ' . ($row['bank_yn'] ? 'checked' : '') . '></td>
        <td style="text-align: center;"><input type="checkbox" name="is_active" value="1" ' . ($row['is_active'] ? 'checked' : '') . ' style="width: 20px;"></td>
        <td>
            <input type="hidden" name="ri_id" value="' . $row['ri_id'] . '">
            <button type="submit" name="action" value="save_rash">Сохранить</button>
        </td>
    </tr>
    </form>';
}
echo '
    <form method="post" style="background-color: #e8f5e9;">
    <tr>
        <td style="color: blue; font-weight: bold;">НОВАЯ</td>
        <td><input type="text" name="ri_text" placeholder="Название новой статьи"></td>
        <td><input type="number" name="ri_order" value="1000" style="width: 70px;"></td>
        <td><input type="text" name="ri_code" placeholder="код (англ)" style="width: 100px;"></td>
        <td style="text-align: center;"><input type="checkbox" name="bank_yn" value="1"></td>
        <td style="text-align: center;">Да</td>
        <td>
            <button type="submit" name="action" value="add_rash" style="background-color: #4caf50; color: white;">Добавить</button>
        </td>
    </tr>
    </form>';

echo '</table>

<div class="section-title">Статьи доходов</div>
<table>
    <tr>
        <th>ID</th>
        <th>Название</th>
        <th>Порядок</th>
        <th>Код</th>
        <th>Банк?</th>
        <th>Активно</th>
        <th>Действие</th>
    </tr>';

$res = $mysqli->query("SELECT * FROM doh_items ORDER BY rd_order");
while ($row = $res->fetch_assoc()) {
    echo '
    <form method="post">
    <tr>
        <td>' . $row['rd_id'] . '</td>
        <td><input type="text" name="rd_text" value="' . htmlspecialchars($row['rd_text']) . '"></td>
        <td><input type="number" name="rd_order" value="' . $row['rd_order'] . '" style="width: 70px;"></td>
        <td><input type="text" name="rd_code" value="' . htmlspecialchars($row['rd_code']) . '" style="width: 100px;"></td>
        <td style="text-align: center;"><input type="checkbox" name="bank_yn" value="1" ' . ($row['bank_yn'] ? 'checked' : '') . '></td>
        <td style="text-align: center;"><input type="checkbox" name="is_active" value="1" ' . ($row['is_active'] ? 'checked' : '') . ' style="width: 20px;"></td>
        <td>
            <input type="hidden" name="rd_id" value="' . $row['rd_id'] . '">
            <button type="submit" name="action" value="save_doh">Сохранить</button>
        </td>
    </tr>
    </form>';
}
echo '
    <form method="post" style="background-color: #e8f5e9;">
    <tr>
        <td style="color: blue; font-weight: bold;">НОВАЯ</td>
        <td><input type="text" name="rd_text" placeholder="Название новой статьи"></td>
        <td><input type="number" name="rd_order" value="1000" style="width: 70px;"></td>
        <td><input type="text" name="rd_code" placeholder="код (англ)" style="width: 100px;"></td>
        <td style="text-align: center;"><input type="checkbox" name="bank_yn" value="1"></td>
        <td style="text-align: center;">Да</td>
        <td>
            <button type="submit" name="action" value="add_doh" style="background-color: #4caf50; color: white;">Добавить</button>
        </td>
    </tr>
    </form>';

echo '</table>

</body>
</html>';
