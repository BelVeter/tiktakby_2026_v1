<?php
/**
 * Живой поиск моделей для bb/tovar_new_mod.php — обе вкладки (создание/редактирование).
 *
 * Отдаёт полные строки tovar_rent (категория+фирма+модель+ЦВЕТ — это и есть единица
 * уникальности в этой таблице), отфильтрованные по тому, что уже выбрано:
 *   q         — подстрока по имени модели, ищет по всей базе;
 *   cat_id    — только эта категория;
 *   producer  — только эта фирма (точное совпадение).
 * Параметры сочетаются через AND, не заданные — игнорируются. Если не задан НИ ОДИН из
 * трёх — отдаём пустой список: моделей 1627 уникальных троек категория+фирма+модель,
 * высыпать их все по клику на пустое поле бессмысленно (в отличие от категории и фирмы,
 * которых на порядок меньше — см. bb/ajax_category_suggest.php, bb/ajax_producer_suggest.php).
 *
 * Клиентский JS (bb/assets/js/model_picker.js) сам решает, показывать ли цвет отдельной
 * строкой (вкладка «редактировать») или схлопывать по имени (вкладка «новая модель») —
 * здесь всегда полная гранулярность, по одной строке на tovar_rent_id.
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

$query    = trim($_REQUEST['q'] ?? '');
$catId    = (int) ($_REQUEST['cat_id'] ?? 0);
$producer = trim($_REQUEST['producer'] ?? '');

$conditions = [];
if ($catId > 0) {
    $conditions[] = 'tr.tovar_rent_cat_id = ' . $catId;
}
if ($producer !== '') {
    $conditions[] = "tr.producer = '" . $mysqli->real_escape_string($producer) . "'";
}
if ($query !== '') {
    $conditions[] = "tr.model LIKE '%" . $mysqli->real_escape_string($query) . "%'";
}

if (!$conditions) {
    echo json_encode(['items' => []]);
    exit;
}

$sql = "SELECT tr.tovar_rent_id AS id, tr.model AS name, tr.tovar_rent_cat_id AS cat_id,
               c.rent_cat_name AS cat_name, c.dog_name AS cat_dog_name,
               tr.producer AS producer, tr.color AS color, tr.`set` AS `set`,
               tr.agr_price AS agr_price, tr.agr_price_cur AS agr_price_cur,
               tr.price_new AS price_new, tr.lom_srok AS lom_srok,
               tr.model_addr AS model_addr, tr.ph_addr AS ph_addr,
               tr.age_from AS age_from, tr.age_to AS age_to,
               tr.weight_from AS weight_from, tr.weight_to AS weight_to,
               tr.m_sex AS m_sex, tr.collateral AS collateral,
               tr.ny AS ny, tr.zv AS zv, tr.tale AS tale, tr.rez1 AS rez1, tr.rez2 AS rez2
          FROM tovar_rent tr
          JOIN tovar_rent_cat c ON c.tovar_rent_cat_id = tr.tovar_rent_cat_id
         WHERE " . implode(' AND ', $conditions) . "
      ORDER BY tr.model
         LIMIT 200";

$result = $mysqli->query($sql);
if (!$result) {
    echo json_encode(['items' => [], 'error' => 'Сбой при доступе к базе данных']);
    exit;
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $row['id']     = (int) $row['id'];
    $row['cat_id'] = (int) $row['cat_id'];
    $items[] = $row;
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
