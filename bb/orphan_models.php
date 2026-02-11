<?php
use bb\Base;
use bb\Db;
use bb\classes\ModelWeb;
use bb\classes\Model;
use bb\classes\Category;
use bb\classes\SubRazdel;
use bb\classes\Razdel;

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

// Проверка авторизации
$in_level = array(0, 5, 7);
if (!isset($_SESSION['svoi']))
    $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    die('Access denied');
}

$mysqli = \bb\Db::getInstance()->getConnection();

// --- Обработка удаления (Одиночное и Множественное) ---
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Одиночное удаление (кнопка в строке)
    if (isset($_POST['delete_single']) && intval($_POST['delete_single']) > 0) {
        $web_id_to_del = intval($_POST['delete_single']);
        $q_del = "DELETE FROM rent_model_web WHERE web_id='$web_id_to_del'";
        if ($mysqli->query($q_del)) {
            $msg .= '<div class="alert success">Запись web_id=' . $web_id_to_del . ' удалена!</div>';
        } else {
            $msg .= '<div class="alert error">Ошибка удаления: ' . $mysqli->error . '</div>';
        }
    }

    // 2. Массовое удаление (чекбоксы)
    if (isset($_POST['action']) && $_POST['action'] == 'delete_bulk' && !empty($_POST['web_ids'])) {
        $ids = array_map('intval', $_POST['web_ids']); // Безопасность
        if (count($ids) > 0) {
            $ids_str = implode(',', $ids);
            $q_del = "DELETE FROM rent_model_web WHERE web_id IN ($ids_str)";
            if ($mysqli->query($q_del)) {
                $msg .= '<div class="alert success">Удалено записей: ' . $mysqli->affected_rows . ' (IDs: ' . $ids_str . ')</div>';
            } else {
                $msg .= '<div class="alert error">Ошибка массового удаления: ' . $mysqli->error . '</div>';
            }
        }
    }
}

// --- Пагинация ---
$limit = 50;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// Считаем общее количество записей
$count_query = "SELECT COUNT(*) as exact_count
                FROM rent_model_web rmw
                LEFT JOIN tovar_rent_items tri ON rmw.model_id = tri.model_id
                WHERE tri.item_id IS NULL";
$count_result = $mysqli->query($count_query);
$count_row = $count_result->fetch_assoc();
$total_rows = $count_row['exact_count'];
$total_pages = ceil($total_rows / $limit);

// Основной запрос данных с лимитом
$query = "SELECT 
            rmw.*, 
            tr.tovar_rent_cat_id as cat_id,
            tr.model,
            trc.dog_name as category_name
          FROM 
            rent_model_web rmw
          LEFT JOIN 
            tovar_rent_items tri ON rmw.model_id = tri.model_id
          LEFT JOIN
            tovar_rent tr ON rmw.model_id = tr.tovar_rent_id
          LEFT JOIN
            tovar_rent_cat trc ON tr.tovar_rent_cat_id = trc.tovar_rent_cat_id
          WHERE 
            tri.item_id IS NULL
          ORDER BY trc.dog_name, tr.model
          LIMIT $offset, $limit";

$result = $mysqli->query($query);
if (!$result) {
    die('Ошибка запроса: ' . $mysqli->error);
}

// Построение ссылок пагинации
function getPaginationLinks($page, $total_pages)
{
    if ($total_pages <= 1)
        return '';
    $links = '<div class="pagination">';

    // Предыдущая
    if ($page > 1) {
        $links .= '<a href="?page=' . ($page - 1) . '">&laquo; Назад</a>';
    } else {
        $links .= '<span class="disabled">&laquo; Назад</span>';
    }

    // Номера страниц (simple: just numbers)
    // Show current +- 3 pages
    $start = max(1, $page - 3);
    $end = min($total_pages, $page + 3);

    if ($start > 1)
        $links .= '<span>...</span>';

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $page) {
            $links .= '<span class="current">' . $i . '</span>';
        } else {
            $links .= '<a href="?page=' . $i . '">' . $i . '</a>';
        }
    }

    if ($end < $total_pages)
        $links .= '<span>...</span>';

    // Следующая
    if ($page < $total_pages) {
        $links .= '<a href="?page=' . ($page + 1) . '">Вперед &raquo;</a>';
    } else {
        $links .= '<span class="disabled">Вперед &raquo;</span>';
    }

    $links .= '</div>';
    return $links;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Очистка пустых веб-моделей</title>
    <link href="/bb/stile.css" rel="stylesheet" type="text/css" />
    <style>
        body { font-family: sans-serif; padding: 20px; font-size: 14px; background: #f9f9f9; }
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; }
        .success { background-color: #dff0d8; border-color: #d6e9c6; color: #3c763d; }
        .error { background-color: #f2dede; border-color: #ebccd1; color: #a94442; }
        
        table { border-collapse: collapse; width: 100%; margin-top: 10px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: middle; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:hover { background-color: #f1f1f1; }
        
        .img-preview { max-width: 50px; max-height: 50px; object-fit: contain; }
        
        .btn { padding: 5px 10px; cursor: pointer; border-radius: 3px; border: none; font-size: 13px; }
        .btn-del { background-color: #d9534f; color: white; }
        .btn-del:hover { background-color: #c9302c; }
        
        .btn-bulk { background-color: #d9534f; color: white; font-size: 14px; padding: 8px 16px; margin-right: 10px; }
        .btn-bulk:hover { background-color: #c9302c; }

        .btn-select { background-color: #5bc0de; color: white; font-size: 14px; padding: 8px 16px; margin-right: 10px; }
        .btn-select:hover { background-color: #46b8da; }
        
        .top-panel { display: flex; justify-content: flex-start; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .checkbox-col { width: 30px; text-align: center; }

        /* Pagination styles */
        .pagination { margin: 20px 0; font-size: 16px; display: flex; gap: 5px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; text-decoration: none; color: #337ab7; background: #fff; border-radius: 3px; }
        .pagination a:hover { background-color: #eee; }
        .pagination .current { background-color: #337ab7; color: white; border-color: #337ab7; }
        .pagination .disabled { color: #999; cursor: not-allowed; }
    </style>
    <script>
        // Global state for selection
        var allSelected = false;

        function toggleAllRows() {
            var checkboxes = document.getElementsByName('web_ids[]');
            allSelected = !allSelected; // Toggle state
            
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = allSelected;
            }
            
            // Update button text optionally? Or just rely on visual feedback
            // var btn = document.getElementById('selectBtn');
            // btn.innerText = allSelected ? "Снять выделение" : "Выделить все на странице";
        }

        // Keep explicit checkbox in header synced too if needed, but per request we need a button.
        // We can keep the header checkbox as a simple toggle too.
        function toggleAllFromHeader(source) {
            var checkboxes = document.getElementsByName('web_ids[]');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
            allSelected = source.checked;
        }
    </script>
</head>
<body>

<div class="top_menu">
    <a class="div_item" href="/bb/index.php">На главную</a>
</div>

<h2>Список веб-моделей без товаров (Всего: <?= $total_rows ?>)</h2>

<?= $msg ?>

<form method="POST" id="mainForm">
    
    <?php if ($result->num_rows > 0): ?>
            <div class="top-panel">
                <button type="button" id="selectBtn" class="btn btn-select" onclick="toggleAllRows()">Выделить все на странице</button>
                <button type="submit" name="action" value="delete_bulk" class="btn btn-bulk" onclick="return confirm('Удалить ВСЕ омеченные галочками записи?');">Удалить выбранные</button>
            </div>
        
            <!-- Pagination Top -->
            <?= getPaginationLinks($page, $total_pages) ?>

            <table>
                <thead>
                    <tr>
                        <th class="checkbox-col"><input type="checkbox" onclick="toggleAllFromHeader(this)"></th>
                        <th>Web ID</th>
                        <th>Model ID</th>
                        <th>Категория</th>
                        <th>Модель</th>
                        <th>Картинка</th>
                        <th>Ссылка</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        try {
                            $mw = \bb\classes\ModelWeb::getFromDbArray($row);
                            $link = $mw->getUrlPageAddress();
                            $pic = \bb\classes\ModelWeb::getURLCorrectPathFor($mw->getL2PicUrlAddress());
                        } catch (Exception $e) {
                            $link = '#error';
                            $pic = '';
                            $row['model'] .= ' (Err)';
                        }
                        ?>
                        <tr>
                            <td class="checkbox-col">
                                <input type="checkbox" name="web_ids[]" value="<?= $row['web_id'] ?>">
                            </td>
                            <td><?= $row['web_id'] ?></td>
                            <td><?= $row['model_id'] ?></td>
                            <td><?= htmlspecialchars($row['category_name'] ?? '---') ?></td>
                            <td><?= htmlspecialchars($row['model'] ?? '---') ?></td>
                            <td>
                                <?php if (!empty($mw->getL2PicUrlAddress())): ?>
                                        <img src="<?= $pic ?>" class="img-preview" alt="pic">
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= $link ?>" target="_blank"><?= $link ?></a>
                            </td>
                            <td>
                                <button type="submit" name="delete_single" value="<?= $row['web_id'] ?>" class="btn btn-del" onclick="return confirm('Удалить web_id=<?= $row['web_id'] ?>?');">Удалить</button>
                            </td>
                        </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination Bottom -->
            <?= getPaginationLinks($page, $total_pages) ?>

    <?php else: ?>
            <h3 style="color: green;">Список пуст!</h3>
    <?php endif; ?>

</form>

</body>
</html>