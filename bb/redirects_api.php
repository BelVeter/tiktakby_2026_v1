<?php
/**
 * AJAX API для каскадных списков на странице перенаправлений.
 * Возвращает JSON.
 */
session_start();
ini_set("display_errors", 0);
header('Content-Type: application/json; charset=utf-8');

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');

// Проверка авторизации
isset($_SESSION['svoi']) ? null : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = \bb\Db::getInstance();
$mysqli = $db->getConnection();

$action = $_GET['action'] ?? '';

switch ($action) {

    // Страницы первого уровня (статичные)
    case 'main_pages':
        echo json_encode([
            ['url' => '/ru', 'name' => 'Главная'],
            ['url' => '/ru/about', 'name' => 'О нас'],
            ['url' => '/ru/conditions', 'name' => 'Условия проката'],
            ['url' => '/ru/delivery', 'name' => 'Доставка и оплата'],
            ['url' => '/ru/contacts', 'name' => 'Контакты'],
            ['url' => '/ru/policy', 'name' => 'Политика по данным'],
        ], JSON_UNESCAPED_UNICODE);
        break;

    // Все разделы
    case 'razdels':
        $result = $mysqli->query("SELECT id_razdel as id, url_razdel_name as url, name_razdel_text as name
            FROM razdel ORDER BY razdel_order_num, id_razdel");
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = ['id' => intval($row['id']), 'url' => '/ru/' . $row['url'], 'name' => html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8')];
        }
        echo json_encode($items, JSON_UNESCAPED_UNICODE);
        break;

    // Подразделы конкретного раздела
    case 'subrazels':
        $razdelId = intval($_GET['razdel_id'] ?? 0);
        if (!$razdelId) {
            echo '[]';
            break;
        }
        // Получаем URL раздела для построения полного пути
        $rRes = $mysqli->query("SELECT url_razdel_name FROM razdel WHERE id_razdel=$razdelId");
        $razdelUrl = $rRes->fetch_assoc()['url_razdel_name'] ?? '';

        $result = $mysqli->query("SELECT sr.id_sub_razdel as id, sr.url_sub_razdel_name as url, sr.name_sub_razdel_text as name
            FROM razdel_subrazdel rs
            JOIN sub_razdel sr ON rs.id_sub_razdel = sr.id_sub_razdel
            WHERE rs.id_razdel = $razdelId
            ORDER BY sr.order_num_sub_razd, sr.id_sub_razdel");
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'id' => intval($row['id']),
                'url' => '/ru/' . $razdelUrl . '/' . $row['url'],
                'name' => html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8'),
            ];
        }
        echo json_encode($items, JSON_UNESCAPED_UNICODE);
        break;

    // Категории конкретного подраздела
    case 'categories':
        $subrazdelId = intval($_GET['subrazdel_id'] ?? 0);
        if (!$subrazdelId) {
            echo '[]';
            break;
        }
        // Получаем URL подраздела + раздела для полного пути
        $srRes = $mysqli->query("SELECT sr.url_sub_razdel_name, r.url_razdel_name
            FROM sub_razdel sr
            JOIN razdel_subrazdel rs ON sr.id_sub_razdel = rs.id_sub_razdel
            JOIN razdel r ON rs.id_razdel = r.id_razdel
            WHERE sr.id_sub_razdel = $subrazdelId LIMIT 1");
        $srRow = $srRes->fetch_assoc();
        $basePath = ($srRow ? $srRow['url_razdel_name'] . '/' . $srRow['url_sub_razdel_name'] : '');

        $result = $mysqli->query("SELECT trc.tovar_rent_cat_id as id, trc.cat_url_key as url, trc.rent_cat_name as name
            FROM subrazdel_category sc
            JOIN tovar_rent_cat trc ON sc.tovar_rent_cat_id = trc.tovar_rent_cat_id
            WHERE sc.id_sub_razdel = $subrazdelId
            ORDER BY trc.rent_cat_name");
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'id' => intval($row['id']),
                'url' => '/ru/' . $basePath . '/' . $row['url'],
                'name' => html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8'),
            ];
        }
        echo json_encode($items, JSON_UNESCAPED_UNICODE);
        break;

    // Модели конкретной категории
    case 'models':
        $catId = intval($_GET['cat_id'] ?? 0);
        if (!$catId) {
            echo '[]';
            break;
        }
        // Получаем полный путь категории: /ru/razdel/subrazdel/category
        $catPathRes = $mysqli->query("SELECT r.url_razdel_name, sr.url_sub_razdel_name, trc.cat_url_key
            FROM tovar_rent_cat trc
            JOIN subrazdel_category sc ON trc.tovar_rent_cat_id = sc.tovar_rent_cat_id
            JOIN sub_razdel sr ON sc.id_sub_razdel = sr.id_sub_razdel
            JOIN razdel_subrazdel rs ON sr.id_sub_razdel = rs.id_sub_razdel
            JOIN razdel r ON rs.id_razdel = r.id_razdel
            WHERE trc.tovar_rent_cat_id = $catId LIMIT 1");
        $catPathRow = $catPathRes ? $catPathRes->fetch_assoc() : null;
        $catBasePath = $catPathRow
            ? '/ru/' . $catPathRow['url_razdel_name'] . '/' . $catPathRow['url_sub_razdel_name'] . '/' . $catPathRow['cat_url_key']
            : '/ru';

        $result = $mysqli->query("SELECT rmw.page_addr as url, rmw.l2_name as name
            FROM rent_model_web rmw
            JOIN tovar_rent tr ON rmw.model_id = tr.tovar_rent_id
            WHERE rmw.lang='ru' AND rmw.status='show' AND tr.tovar_rent_cat_id = $catId
            ORDER BY rmw.l2_name");
        $items = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = [
                    'url' => $catBasePath . '/' . $row['url'],
                    'name' => html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8'),
                ];
            }
        }
        echo json_encode($items, JSON_UNESCAPED_UNICODE);
        break;

    // Живой поиск по всем уровням сразу
    case 'search':
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) {
            echo '[]';
            break;
        }
        $qEsc = $mysqli->real_escape_string($q);
        $qLike = '%' . $qEsc . '%';

        // 1) Собираем все разделы
        $razdelMap = []; // id => [name, url]
        $res = $mysqli->query("SELECT id_razdel, url_razdel_name, name_razdel_text FROM razdel ORDER BY razdel_order_num");
        while ($row = $res->fetch_assoc()) {
            $razdelMap[$row['id_razdel']] = ['name' => html_entity_decode($row['name_razdel_text'], ENT_QUOTES, 'UTF-8'), 'url' => '/ru/' . $row['url_razdel_name']];
        }

        // 2) Собираем подразделы + привязка к разделу
        $subMap = []; // id => [name, url, razdel_id]
        $res = $mysqli->query("SELECT sr.id_sub_razdel, sr.url_sub_razdel_name, sr.name_sub_razdel_text, rs.id_razdel
            FROM sub_razdel sr
            JOIN razdel_subrazdel rs ON sr.id_sub_razdel = rs.id_sub_razdel
            ORDER BY sr.order_num_sub_razd");
        while ($row = $res->fetch_assoc()) {
            $rId = $row['id_razdel'];
            $rUrl = $razdelMap[$rId]['url'] ?? '/ru';
            $subMap[$row['id_sub_razdel']] = [
                'name' => html_entity_decode($row['name_sub_razdel_text'], ENT_QUOTES, 'UTF-8'),
                'url' => $rUrl . '/' . $row['url_sub_razdel_name'],
                'razdel_id' => $rId,
            ];
        }

        // 3) Собираем категории + привязка к подразделу
        $catMap = []; // id => [name, url, sub_id]
        $res = $mysqli->query("SELECT trc.tovar_rent_cat_id, trc.rent_cat_name, trc.cat_url_key, sc.id_sub_razdel
            FROM tovar_rent_cat trc
            JOIN subrazdel_category sc ON trc.tovar_rent_cat_id = sc.tovar_rent_cat_id
            ORDER BY trc.rent_cat_name");
        while ($row = $res->fetch_assoc()) {
            $sId = $row['id_sub_razdel'];
            $sUrl = $subMap[$sId]['url'] ?? '/ru';
            $catMap[$row['tovar_rent_cat_id']] = [
                'name' => html_entity_decode($row['rent_cat_name'], ENT_QUOTES, 'UTF-8'),
                'url' => $sUrl . '/' . $row['cat_url_key'],
                'sub_id' => $sId,
            ];
        }

        // 4) Ищем модели по запросу
        $modelHits = [];
        $res = $mysqli->query("SELECT rmw.page_addr, rmw.l2_name, tr.tovar_rent_cat_id
            FROM rent_model_web rmw
            JOIN tovar_rent tr ON rmw.model_id = tr.tovar_rent_id
            WHERE rmw.lang='ru' AND rmw.status='show'
            AND rmw.l2_name LIKE '$qLike'
            ORDER BY rmw.l2_name LIMIT 50");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cId = intval($row['tovar_rent_cat_id']);
                // Полный URL модели = URL категории + page_addr
                $catUrl = isset($catMap[$cId]) ? $catMap[$cId]['url'] : '/ru';
                $modelHits[] = ['name' => html_entity_decode($row['l2_name'], ENT_QUOTES, 'UTF-8'), 'url' => $catUrl . '/' . $row['page_addr'], 'cat_id' => $cId];
            }
        }

        // 5) Ищем категории по запросу
        $catHits = [];
        $res = $mysqli->query("SELECT trc.tovar_rent_cat_id FROM tovar_rent_cat trc WHERE trc.rent_cat_name LIKE '$qLike'");
        if ($res) {
            while ($row = $res->fetch_assoc())
                $catHits[] = intval($row['tovar_rent_cat_id']);
        }

        // 6) Ищем подразделы по запросу
        $subHits = [];
        $res = $mysqli->query("SELECT id_sub_razdel FROM sub_razdel WHERE name_sub_razdel_text LIKE '$qLike'");
        if ($res) {
            while ($row = $res->fetch_assoc())
                $subHits[] = intval($row['id_sub_razdel']);
        }

        // 7) Ищем разделы по запросу
        $razdelHits = [];
        $res = $mysqli->query("SELECT id_razdel FROM razdel WHERE name_razdel_text LIKE '$qLike'");
        if ($res) {
            while ($row = $res->fetch_assoc())
                $razdelHits[] = intval($row['id_razdel']);
        }

        // === Строим иерархическое дерево ===
        // Нужно собрать дерево: razdel → subrazdel → category → model
        // Включаем узел если он сам совпал ИЛИ у него есть дочерний совпавший

        // Определяем какие категории нужны (совпали сами или имеют совпавшие модели)
        $neededCats = array_unique(array_merge($catHits, array_column($modelHits, 'cat_id')));
        // Определяем какие подразделы нужны
        $neededSubs = $subHits;
        foreach ($neededCats as $cId) {
            if (isset($catMap[$cId]))
                $neededSubs[] = $catMap[$cId]['sub_id'];
        }
        $neededSubs = array_unique($neededSubs);
        // Определяем какие разделы нужны
        $neededRazdels = $razdelHits;
        foreach ($neededSubs as $sId) {
            if (isset($subMap[$sId]))
                $neededRazdels[] = $subMap[$sId]['razdel_id'];
        }
        $neededRazdels = array_unique($neededRazdels);

        // Строим дерево
        $tree = [];
        foreach ($neededRazdels as $rId) {
            if (!isset($razdelMap[$rId]))
                continue;
            $rNode = [
                'type' => 'razdel',
                'name' => $razdelMap[$rId]['name'],
                'url' => $razdelMap[$rId]['url'],
                'matched' => in_array($rId, $razdelHits),
                'children' => []
            ];

            // Подразделы этого раздела
            foreach ($neededSubs as $sId) {
                if (!isset($subMap[$sId]) || $subMap[$sId]['razdel_id'] != $rId)
                    continue;
                $sNode = [
                    'type' => 'subrazdel',
                    'name' => $subMap[$sId]['name'],
                    'url' => $subMap[$sId]['url'],
                    'matched' => in_array($sId, $subHits),
                    'children' => []
                ];

                // Категории этого подраздела
                foreach ($neededCats as $cId) {
                    if (!isset($catMap[$cId]) || $catMap[$cId]['sub_id'] != $sId)
                        continue;
                    $cNode = [
                        'type' => 'category',
                        'name' => $catMap[$cId]['name'],
                        'url' => $catMap[$cId]['url'],
                        'matched' => in_array($cId, $catHits),
                        'children' => []
                    ];

                    // Модели этой категории
                    foreach ($modelHits as $m) {
                        if ($m['cat_id'] == $cId) {
                            $cNode['children'][] = ['type' => 'model', 'name' => $m['name'], 'url' => $m['url'], 'matched' => true];
                        }
                    }
                    $sNode['children'][] = $cNode;
                }
                $rNode['children'][] = $sNode;
            }
            $tree[] = $rNode;
        }

        echo json_encode($tree, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
}
