<?php
/**
 * Создание категории из модалки на `bb/tovar_new_mod.php`.
 *
 * Заменяет позиционный `INSERT INTO tovar_rent_cat VALUES('','$name','$dog')`,
 * который стоял в `tovar_new_mod.php` (строки 99 и 245) и был сломан: в таблице
 * 9 колонок, а вставка передавала 3 — категорию завести было невозможно
 * (docs/db_notes.md, п.1 про позиционные INSERT).
 *
 * Пишет через `\bb\classes\Category`, поэтому заполняются и `cat_url_key`
 * (адрес категории на сайте), и привязка к дереву каталога `subrazdel_category`
 * — без неё категория не появляется в навигации.
 *
 * Защита от дублей двухуровневая: точный дубль после нормализации отклоняется
 * всегда, похожие названия отклоняются один раз и пропускаются при `confirm=1`,
 * когда оператор осознанно подтвердил.
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Similarity.php');

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload)
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    respond(['error' => 'Нет доступа']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Только POST']);
}

// Названий на en/lt форма не спрашивает: сайт русскоязычный, `/en` и `/lt`
// редиректятся на `/ru` (routes/web.php). Колонки в таблице остаются пустыми —
// если языки когда-нибудь включат, заполнять их будет справочник категорий.
$name     = trim($_POST['name'] ?? '');
$dogName  = trim($_POST['dog_name'] ?? '');
$urlKey   = trim($_POST['cat_url_key'] ?? '');
$subRazd  = (int) ($_POST['main_sub_razdel_id'] ?? 0);
$catType  = (int) ($_POST['cat_type'] ?? 0);
$catSort  = (int) ($_POST['cat_sort'] ?? 0);
$confirm  = !empty($_POST['confirm']);

$errors = [];

if (mb_strlen($name) < 2) {
    $errors[] = 'Укажите название категории.';
}
if ($dogName === '') {
    $errors[] = 'Укажите название для договора в единственном числе — оно печатается в договоре.';
}
if ($subRazd < 1) {
    $errors[] = 'Выберите подраздел: без него категория не попадёт в меню каталога.';
}
if ($urlKey === '') {
    $errors[] = 'Укажите URL-ключ: без него у категории не будет адреса на сайте.';
}
if ($urlKey !== '' && !preg_match('/^[a-z0-9-]+$/', $urlKey)) {
    $errors[] = 'URL-ключ может содержать только латиницу в нижнем регистре, цифры и дефис.';
}

if (!empty($errors)) {
    respond(['error' => implode(' ', $errors)]);
}

$mysqli = \bb\Db::getInstance()->getConnection();

$result = $mysqli->query("SELECT tovar_rent_cat_id, rent_cat_name, cat_url_key FROM tovar_rent_cat");
if (!$result) {
    respond(['error' => 'Сбой при доступе к базе данных.']);
}

$labels = [];
$rows = [];
while ($row = $result->fetch_assoc()) {
    $id = (int) $row['tovar_rent_cat_id'];
    $labels[$id] = $row['rent_cat_name'];
    $rows[$id] = $row;

    if ($row['cat_url_key'] !== '' && strcasecmp($row['cat_url_key'], $urlKey) === 0) {
        respond([
            'error' => 'URL-ключ «' . $urlKey . '» уже занят категорией «' . $row['rent_cat_name'] . '».',
        ]);
    }
}

// Точный дубль после нормализации: «Автокресла» и «авто кресла» — одно и то же.
$exactId = \bb\classes\Similarity::findExact($name, $labels);
if ($exactId !== false) {
    respond([
        'error'    => 'Такая категория уже есть: «' . $labels[$exactId] . '». Выберите её в списке.',
        'existing' => ['id' => (int) $exactId, 'name' => $labels[$exactId]],
    ]);
}

// Похожие названия — предупреждаем один раз, дальше решает оператор.
if (!$confirm) {
    $similar = \bb\classes\Similarity::findSimilar($name, $labels);
    if (!empty($similar)) {
        respond([
            'needs_confirm' => true,
            'similar'       => array_map(function ($item) {
                return ['id' => (int) $item['key'], 'name' => $item['label'], 'score' => $item['score']];
            }, $similar),
        ]);
    }
}

$category = new \bb\classes\Category();
$category->setMainSubRazdelId($subRazd);
$category->setName($name);
$category->setDogName($dogName);
$category->setCatUrlKey($urlKey);
$category->setCatType($catType);
$category->setCatSort($catSort);

\bb\Db::startTransaction();
$category->save();
\bb\Db::commitTransaction();

respond([
    'ok' => true,
    'category' => [
        'id'       => (int) $category->getId(),
        'name'     => $name,
        'dog_name' => $dogName,
        'url_key'  => $urlKey,
        'models'   => 0,
        'in_tree'  => true,
    ],
]);
