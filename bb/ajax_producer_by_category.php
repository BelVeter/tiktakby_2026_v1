<?php
/**
 * Производители, встречающиеся в конкретной категории — для живого поиска
 * на bb/tovar_new.php. НЕ справочник: производитель здесь сужает каскад до
 * существующей модели, а не назначается — если источником сделать
 * справочник, можно выбрать бренд без единой модели в этой категории и
 * упереться в пустой список моделей. Источник и запрос — как в
 * bb/cat_ch_new.php (case 'cat_producer'), только JSON вместо eval'имой JS.
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');

header('Content-Type: application/json; charset=utf-8');

$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    echo json_encode(['items' => [], 'error' => 'Нет доступа']);
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();

$catId = (int) ($_REQUEST['cat_id'] ?? 0);
$query = trim($_REQUEST['q'] ?? '');

if ($catId < 1) {
    echo json_encode(['items' => []]);
    exit;
}

$result = $mysqli->query("SELECT DISTINCT producer FROM tovar_rent WHERE tovar_rent_cat_id=$catId ORDER BY producer");
if (!$result) {
    echo json_encode(['items' => [], 'error' => 'Сбой при доступе к базе данных']);
    exit;
}

$needle = $query !== '' ? mb_strtolower($query, 'UTF-8') : null;

$items = [];
while ($row = $result->fetch_assoc()) {
    if ($needle !== null && mb_strpos(mb_strtolower($row['producer'], 'UTF-8'), $needle) === false) {
        continue;
    }
    $items[] = ['id' => $row['producer'], 'name' => $row['producer']];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
