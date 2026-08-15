<?php
/**
 * Живой поиск производителей для bb/tovar_new_mod.php + проверка вводимого
 * названия на дубль/опечатку. Калька bb/ajax_category_suggest.php.
 *
 * Режимы:
 *   q=<строка>                — подсказки (активные; скрытый бренд — только
 *                                при точном совпадении, помечен hidden:true);
 *   q=<строка>&check=1        — плюс exact/similar для модалки создания;
 *   &cat_id=<id>               — бренды, уже встречавшиеся в этой категории,
 *                                идут первыми.
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Producer.php');
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
$catId = (int) ($_REQUEST['cat_id'] ?? 0);

$usedInCat = [];
if ($catId > 0) {
    $result = $mysqli->query("SELECT DISTINCT producer FROM tovar_rent WHERE tovar_rent_cat_id=$catId");
    while ($row = $result->fetch_assoc()) {
        $usedInCat[$row['producer']] = true;
    }
}

$active = \bb\classes\Producer::getAllActive();

$response = ['items' => []];

if ($query !== '') {
    $needle = \bb\classes\Similarity::normalize($query);

    $items = [];
    foreach ($active as $p) {
        if (mb_strpos(\bb\classes\Similarity::normalize($p->getName()), $needle) !== false) {
            $items[] = $p;
        }
    }

    // Точное совпадение среди СКРЫТЫХ — находится, даже если is_active=0
    // (спека: скрытый бренд нельзя было бы включить обратно иначе).
    $exactAny = \bb\classes\Producer::getByName($query);
    $alreadyThere = false;
    foreach ($items as $p) {
        if ($p->getName() === $query) { $alreadyThere = true; break; }
    }
    if ($exactAny && !$exactAny->isActive() && !$alreadyThere) {
        $items[] = $exactAny;
    }

    if ($catId > 0) {
        usort($items, function ($a, $b) use ($usedInCat) {
            $au = isset($usedInCat[$a->getName()]) ? 0 : 1;
            $bu = isset($usedInCat[$b->getName()]) ? 0 : 1;
            return $au <=> $bu ?: strcmp($a->getName(), $b->getName());
        });
    }

    $response['items'] = array_slice(array_map(function ($p) {
        return [
            'id'     => $p->getName(),
            'name'   => $p->getName(),
            'hidden' => !$p->isActive(),
        ];
    }, $items), 0, 15);
}

if ($check && $query !== '') {
    $dup = \bb\classes\Producer::findDuplicates($query);

    $response['exact'] = $dup['exact'] ? ['id' => $dup['exact']->getName(), 'name' => $dup['exact']->getName()] : null;
    $response['similar'] = array_map(function ($m) {
        return ['id' => $m['producer']->getName(), 'name' => $m['producer']->getName(), 'score' => $m['score']];
    }, $dup['similar']);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
