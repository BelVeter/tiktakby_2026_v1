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
            $items[] = ['id' => intval($row['id']), 'url' => '/ru/' . $row['url'], 'name' => $row['name']];
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
                'name' => $row['name'],
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
                'name' => $row['name'],
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

        $result = $mysqli->query("SELECT rmw.page_addr as url, rmw.l2_name as name
            FROM rent_model_web rmw
            JOIN tovar_rent tr ON rmw.model_id = tr.tovar_rent_id
            WHERE rmw.lang='ru' AND rmw.status='show' AND tr.tovar_rent_cat_id = $catId
            ORDER BY rmw.l2_name");
        $items = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = [
                    'url' => '/ru/' . $row['url'],
                    'name' => $row['name'],
                ];
            }
        }
        echo json_encode($items, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
}
