<?php
use bb\Base;
use bb\Db;
use bb\classes\ModelWeb;
use bb\classes\Model;
use bb\classes\Category;
use bb\classes\SubRazdel;
use bb\classes\Razdel;
use bb\classes\Picture;

session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/ModelWeb.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Model.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/SubRazdel.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Razdel.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Picture.php'); // For dop photos

// Проверка авторизации
$in_level = array(0, 5, 7);
if (!isset($_SESSION['svoi']))
    $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    die('Access denied');
}

$mysqli = \bb\Db::getInstance()->getConnection();

// --- Фильтры и Пагинация ---
$filter_mode = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // 'all' or 'with_items'
$limit = 50;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

// Базовый запрос (БЕЗ LIMIT)
$sql_from = "FROM rent_model_web rmw
             LEFT JOIN tovar_rent_items tri ON rmw.model_id = tri.model_id
             LEFT JOIN tovar_rent tr ON rmw.model_id = tr.tovar_rent_id
             LEFT JOIN tovar_rent_cat trc ON tr.tovar_rent_cat_id = trc.tovar_rent_cat_id";

$sql_where = "WHERE 1";
$sql_group = "GROUP BY rmw.model_id";

if ($filter_mode == 'with_items') {
    $sql_where .= " AND tri.item_id IS NOT NULL";
}

// Запрашиваем ВСЕ модели для проверки (но выбираем меньше полей для скорости)
$query = "SELECT 
            rmw.*, 
            tr.tovar_rent_cat_id as cat_id,
            tr.model,
            trc.dog_name as category_name
          $sql_from 
          $sql_where
          $sql_group
          ORDER BY trc.dog_name, tr.model";

$result = $mysqli->query($query);
if (!$result)
    die('Error: ' . $mysqli->error);

// Загружаем ВСЕ доп фото одним запросом (Оптимизация N+1)
$dop_photos = [];
$dop_query = "SELECT model_id, src FROM dop_photos";
$dop_res = $mysqli->query($dop_query);
if ($dop_res) {
    while ($row = $dop_res->fetch_assoc()) {
        $dop_photos[$row['model_id']][] = $row['src'];
    }
}

// Функция проверки файла
function checkImage($path)
{
    if (empty($path))
        return ['status' => 'empty', 'msg' => 'Не указана'];

    $full_disk_path = $_SERVER['DOCUMENT_ROOT'] . $path;

    if (file_exists($full_disk_path)) {
        return ['status' => 'ok', 'msg' => 'OK', 'path' => $path];
    } else {
        return ['status' => 'missing', 'msg' => 'Отсутствует!', 'path' => $full_disk_path];
    }
}

// --- Фильтрация в PHP (ищем ТОЛЬКО проблемы) ---
$problems = [];
$all_rows_count = 0;

while ($row = $result->fetch_assoc()) {
    $all_rows_count++;

    // Проверка L2
    $l2_path = $row['l2_pic'];
    // $l2_url_corrected = \bb\classes\ModelWeb::getURLCorrectPathFor($l2_path);
    $l2_res = checkImage($l2_path);

    // Проверка L3 Main
    $l3_path = $row['m_pic_big'];
    // $l3_url_corrected = \bb\classes\ModelWeb::getURLCorrectPathFor($l3_path);
    $l3_res = checkImage($l3_path);

    // Проверка слайдера
    $model_dops = isset($dop_photos[$row['model_id']]) ? $dop_photos[$row['model_id']] : [];
    $dop_results = [];
    foreach ($model_dops as $src) {
        $dop_results[] = checkImage($src); // src is from DB
    }

    // Есть ли проблема?
    $has_problem = false;
    if ($l2_res['status'] == 'missing')
        $has_problem = true;
    if ($l3_res['status'] == 'missing')
        $has_problem = true;
    foreach ($dop_results as $dr) {
        if ($dr['status'] == 'missing')
            $has_problem = true;
    }

    if ($has_problem) {
        // Сохраняем результаты проверки
        $row['__validation'] = [
            'l2' => $l2_res,
            'l3' => $l3_res,
            'dop' => $dop_results
        ];
        $problems[] = $row;
    }
}

$total_problems = count($problems);
$total_pages = ceil($total_problems / $limit);

// Срез для текущей страницы
$current_page_items = array_slice($problems, ($page - 1) * $limit, $limit);


// Построение ссылок пагинации (копия из orphan)
function getPaginationLinks($page, $total_pages, $filter_mode)
{
    if ($total_pages <= 1)
        return '';
    $links = '<div class="pagination">';
    $urlParams = "&filter=$filter_mode";

    if ($page > 1)
        $links .= '<a href="?page=' . ($page - 1) . $urlParams . '">&laquo; Назад</a>';
    else
        $links .= '<span class="disabled">&laquo; Назад</span>';

    $start = max(1, $page - 3);
    $end = min($total_pages, $page + 3);

    if ($start > 1)
        $links .= '<span>...</span>';

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $page)
            $links .= '<span class="current">' . $i . '</span>';
        else
            $links .= '<a href="?page=' . $i . $urlParams . '">' . $i . '</a>';
    }

    if ($end < $total_pages)
        $links .= '<span>...</span>';

    if ($page < $total_pages)
        $links .= '<a href="?page=' . ($page + 1) . $urlParams . '">Вперед &raquo;</a>';
    else
        $links .= '<span class="disabled">Вперед &raquo;</span>';

    $links .= '</div>';
    return $links;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Валидация картинок: ТОЛЬКО ОШИБКИ</title>
    <link href="/bb/stile.css" rel="stylesheet" type="text/css" />
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            font-size: 14px;
            background: #fff0f0;
        }

        /* Red background hint */
        .filter-panel {
            margin-bottom: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            padding: 8px 15px;
            text-decoration: none;
            color: #333;
            border: 1px solid #ccc;
            background: #eee;
            border-radius: 4px;
            margin-right: 10px;
        }

        .btn.active {
            background: #d9534f;
            color: white;
            border-color: #d43f3a;
        }

        /* Red active button */
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 10px;
            background: #fff;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f2f2f2;
        }

        .status-ok {
            color: green;
            font-weight: bold;
        }

        .status-missing {
            color: red;
            font-weight: bold;
            background: #ffeeee;
            display: block;
            padding: 5px;
            margin-top: 5px;
            border: 1px solid red;
        }

        .status-empty {
            color: #999;
            font-style: italic;
        }

        .path-info {
            font-size: 11px;
            color: #666;
            font-family: monospace;
            display: block;
            margin-top: 2px;
        }

        .pagination {
            margin: 20px 0;
            display: flex;
            gap: 5px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: #fff;
            text-decoration: none;
            color: #337ab7;
        }

        .pagination .current {
            background: #d9534f;
            color: white;
            border-color: #d43f3a;
        }

        /* Red pagination */
        .pagination .disabled {
            color: #ccc;
        }
    </style>
</head>

<body>

    <div class="top_menu">
        <a class="div_item" href="/bb/index.php">На главную</a>
    </div>

    <h2>Валидация картинок: ТОЛЬКО ОШИБКИ</h2>
    <p>Проверено моделей: <?= $all_rows_count ?>. Найдено проблемных: <strong><?= $total_problems ?></strong></p>

    <div class="filter-panel">
        <strong>Фильтр:</strong>
        <a href="?filter=all" class="btn <?= $filter_mode == 'all' ? 'active' : '' ?>">Все модели</a>
        <a href="?filter=with_items" class="btn <?= $filter_mode == 'with_items' ? 'active' : '' ?>">Только с
            товарами</a>
    </div>

    <?= getPaginationLinks($page, $total_pages, $filter_mode) ?>

    <table>
        <thead>
            <tr>
                <th>ID (Web/Mod)</th>
                <th>Категория</th>
                <th>Модель</th>
                <th>Ссылка L3</th>
                <th>L2 Pic</th>
                <th>L3 Main</th>
                <th>Slider</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($current_page_items as $row): ?>
                <?php
                try {
                    // Создаем объекты ТОЛЬКО для отображаемых строк
                    // (нам нужны URL и красивые имена)
                    $mw = \bb\classes\ModelWeb::getFromDbArray($row);
                    // $mw->loadDopPictures(); // Не загружаем, у нас уже есть результаты проверки
            
                    $link = $mw->getUrlPageAddress();

                    // Получаем категорию и модель через классы (как просили)
                    $model_obj = \bb\classes\Model::getById($mw->getModelId());
                    $cat_name = '---';
                    $model_name = '---';

                    if ($model_obj) {
                        $model_name = $model_obj->model; // Public field access
                        $cat = \bb\classes\Category::getById($model_obj->getCatId());
                        if ($cat) {
                            $cat_name = $cat->getDogName();
                        }
                    }

                    // Результаты проверки (уже подсчитаны)
                    $l2_res = $row['__validation']['l2'];
                    $l3_res = $row['__validation']['l3'];
                    $dop_results = $row['__validation']['dop'];

                } catch (Exception $e) {
                    echo '<tr><td colspan="7">Error loading model ' . $row['model_id'] . ': ' . $e->getMessage() . '</td></tr>';
                    continue;
                }
                ?>
                <tr>
                    <td style="font-size: 11px;">
                        W:<?= $row['web_id'] ?> / M:<?= $row['model_id'] ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($cat_name) ?>
                    </td>
                    <td>
                        <strong>
                            <?= htmlspecialchars($model_name) ?>
                        </strong>
                    </td>
                    <td>
                        <a href="<?= $link ?>" target="_blank">Link</a>
                    </td>

                    <!-- L2 Checker -->
                    <td style="text-align: center;">
                        <?php if ($l2_res['status'] == 'ok'): ?>
                            <span class="status-ok">OK</span>
                        <?php elseif ($l2_res['status'] == 'missing'): ?>
                            <span class="status-missing">MISS</span>
                            <span class="path-info">
                                <?= $l2_res['path'] ?>
                            </span>
                        <?php else: ?>
                            <span class="status-empty">-</span>
                        <?php endif; ?>
                    </td>

                    <!-- L3 Main Checker -->
                    <td style="text-align: center;">
                        <?php if ($l3_res['status'] == 'ok'): ?>
                            <span class="status-ok">OK</span>
                        <?php elseif ($l3_res['status'] == 'missing'): ?>
                            <span class="status-missing">MISS</span>
                            <span class="path-info">
                                <?= $l3_res['path'] ?>
                            </span>
                        <?php else: ?>
                            <span class="status-empty">-</span>
                        <?php endif; ?>
                    </td>

                    <!-- Slider Checker -->
                    <td style="font-size: 11px;">
                        <?php if (empty($dop_results)): ?>
                            <span class="status-empty">-</span>
                        <?php else: ?>
                            <?php foreach ($dop_results as $idx => $dr): ?>
                                <?php if ($dr['status'] == 'missing'): ?>
                                    <div style="border-bottom: 1px dotted #ccc;">
                                        #<?= $idx + 1 ?>: <span class="status-missing">MISS</span>
                                        <span class="path-info">
                                            <?= $dr['path'] ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php
                            $ok_count = 0;
                            foreach ($dop_results as $dr)
                                if ($dr['status'] == 'ok')
                                    $ok_count++;
                            if ($ok_count == count($dop_results))
                                echo '<span class="status-ok">OK (' . $ok_count . ')</span>';
                            else
                                echo '<div>Total: ' . count($dop_results) . '</div>';
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?= getPaginationLinks($page, $total_pages, $filter_mode) ?>

</body>

</html>