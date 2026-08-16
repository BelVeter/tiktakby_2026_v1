<?php
/**
 * Живой поиск категорий для `bb/tovar_new_mod.php` + проверка на дубль и опечатку.
 *
 * Два режима:
 *   q=<строка>            — подсказки для поля выбора категории;
 *   q=<строка>&check=1    — плюс проверка вводимого НОВОГО названия: точный
 *                           дубль после нормализации и похожие названия.
 *   q=<строка>&producer=<имя>&filter=1 — плюс жёсткий фильтр: только
 *                           категории, где реально есть модели этого
 *                           производителя.
 *
 * Отдаёт `dog_name` прямо в подсказке, поэтому поле «для договора (ед.ч.)»
 * заполняется без второго запроса. Раньше этим занимался `select_ch3()`,
 * который дёргал `/bb/cat_ch.php` через несуществующий `getXmlHttp()` — поле
 * навсегда зависало на «... ждите ...».
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Similarity.php');

header('Content-Type: application/json; charset=utf-8');

$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    echo json_encode(['items' => [], 'error' => 'Нет доступа']);
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();

$query = trim($_REQUEST['q'] ?? '');
$check = !empty($_REQUEST['check']);
$producer = trim($_REQUEST['producer'] ?? '');
$filter = !empty($_REQUEST['filter']);

/**
 * Все категории с местом в дереве каталога и числом моделей.
 *
 * Категория может быть привязана к нескольким подразделам, поэтому пути
 * собираются GROUP_CONCAT'ом по подзапросу — джойнить `subrazdel_category`
 * напрямую в запросе со счётчиком нельзя, счётчик размножится (см.
 * docs/db_notes.md про many-to-many).
 */
$sql = "SELECT c.tovar_rent_cat_id AS id,
               c.rent_cat_name      AS name,
               c.dog_name           AS dog_name,
               c.cat_url_key        AS url_key,
               c.cat_type           AS cat_type,
               (SELECT COUNT(*) FROM tovar_rent t WHERE t.tovar_rent_cat_id = c.tovar_rent_cat_id) AS models,
               (SELECT GROUP_CONCAT(DISTINCT CONCAT(r.name_razdel_text, ' / ', s.name_sub_razdel_text) SEPARATOR '; ')
                  FROM subrazdel_category sc
                  JOIN sub_razdel s        ON s.id_sub_razdel = sc.id_sub_razdel
                  LEFT JOIN razdel_subrazdel rs ON rs.id_sub_razdel = s.id_sub_razdel
                  LEFT JOIN razdel r       ON r.id_razdel = rs.id_razdel
                 WHERE sc.tovar_rent_cat_id = c.tovar_rent_cat_id) AS tree_path
          FROM tovar_rent_cat c
      ORDER BY c.rent_cat_name";

$result = $mysqli->query($sql);
if (!$result) {
    echo json_encode(['items' => [], 'error' => 'Сбой при доступе к базе данных']);
    exit;
}

$all = [];
while ($row = $result->fetch_assoc()) {
    $row['id']     = (int) $row['id'];
    $row['models'] = (int) $row['models'];
    $row['in_tree'] = $row['tree_path'] !== null && $row['tree_path'] !== '';
    $all[] = $row;
}

// Жёсткий фильтр (tovar_new_mod.php, вкладка «Редактировать», фаза locate):
// только категории, в которых реально есть модели этого производителя. Без
// &filter=1 (или без producer=) поведение не меняется.
if ($producer !== '' && $filter) {
    $usedByProducer = [];
    $result2 = $mysqli->query(
        "SELECT DISTINCT tovar_rent_cat_id FROM tovar_rent WHERE producer='"
        . $mysqli->real_escape_string($producer) . "'"
    );
    while ($row2 = $result2->fetch_assoc()) {
        $usedByProducer[(int) $row2['tovar_rent_cat_id']] = true;
    }
    $all = array_values(array_filter($all, function ($row) use ($usedByProducer) {
        return isset($usedByProducer[$row['id']]);
    }));
}

$response = ['items' => []];

if ($query === '') {
    $response['items'] = $all;
} elseif ($query !== '') {
    $needle = \bb\classes\Similarity::normalize($query);

    // 1. Подстрока по нормализованному названию — то, что человек печатает.
    $items = [];
    foreach ($all as $row) {
        if (mb_strpos(\bb\classes\Similarity::normalize($row['name']), $needle) !== false) {
            $items[] = $row;
        }
    }

    // 2. Если прямых попаданий мало — добираем похожими, чтобы опечатка
    //    в запросе не прятала существующую категорию.
    if (count($items) < 5) {
        $labels = [];
        foreach ($all as $i => $row) {
            $labels[$i] = $row['name'];
        }

        $found = [];
        foreach ($items as $row) {
            $found[$row['id']] = true;
        }

        foreach (\bb\classes\Similarity::findSimilar($query, $labels, 0.40, 8) as $similar) {
            $row = $all[$similar['key']];
            if (!isset($found[$row['id']])) {
                $row['fuzzy'] = true;
                $items[] = $row;
            }
        }
    }

    $response['items'] = array_slice($items, 0, 15);
}

if ($check && $query !== '') {
    $labels = [];
    foreach ($all as $i => $row) {
        $labels[$i] = $row['name'];
    }

    $exactKey = \bb\classes\Similarity::findExact($query, $labels);
    $response['exact'] = $exactKey === false ? null : $all[$exactKey];

    $response['similar'] = [];
    foreach (\bb\classes\Similarity::findSimilar($query, $labels) as $similar) {
        $row = $all[$similar['key']];
        $row['score'] = $similar['score'];
        $response['similar'][] = $row;
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
