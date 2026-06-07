<?php
session_start();
ini_set('display_errors', (isset($_SESSION['svoi']) && $_SESSION['svoi'] == 8941) ? 1 : 0);
error_reporting(E_ALL);


require_once __DIR__ . '/auth_guard.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/ModelWeb.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Model.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Category.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Razdel.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/SubRazdel.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');



$mysqli = \bb\Db::getInstance()->getConnection();

// Проверка поддержки WebP
if (!function_exists('imagewebp')) {
    die("Ошибка: GD библиотека не поддерживает WebP. Установите PHP с поддержкой WebP.");
}

// Генерация CSRF токена для защиты
if (!isset($_SESSION['webp_csrf_token'])) {
    $_SESSION['webp_csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Логирование операций конвертации
 * @param string $message Сообщение для лога
 * @param string $level Уровень (INFO, ERROR, WARNING)
 */
function logConversion($message, $level = 'INFO')
{
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/bb/logs/webp_conversion.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Конвертирует изображение в WebP формат
 * @param string $absolutePath Абсолютный путь к исходному файлу
 * @param string $newAbsolutePath Абсолютный путь для нового WebP файла
 * @param string $ext Расширение исходного файла (jpg, jpeg, png)
 * @return array ['success' => bool, 'error' => string|null]
 */
function convertImageToWebP($absolutePath, $newAbsolutePath, $ext)
{
    $image = null;

    // Создаем изображение из исходного файла
    if ($ext === 'png') {
        $image = imagecreatefrompng($absolutePath);
        if ($image === false) {
            return ['success' => false, 'error' => "Не удалось загрузить PNG: $absolutePath"];
        }
        // Обработка прозрачности для PNG
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
    } else {
        $image = imagecreatefromjpeg($absolutePath);
        if ($image === false) {
            return ['success' => false, 'error' => "Не удалось загрузить JPEG: $absolutePath"];
        }
    }

    // Конвертируем в WebP
    $result = imagewebp($image, $newAbsolutePath, 85);
    imagedestroy($image);

    if (!$result) {
        return ['success' => false, 'error' => "Не удалось сохранить WebP: $newAbsolutePath"];
    }

    // Проверяем, что файл создан и читается
    if (!file_exists($newAbsolutePath)) {
        return ['success' => false, 'error' => "WebP файл не создан: $newAbsolutePath"];
    }

    // Проверяем, что WebP файл валидный (можно открыть)
    $testImage = imagecreatefromwebp($newAbsolutePath);
    if ($testImage === false) {
        @unlink($newAbsolutePath); // Удаляем поврежденный файл
        return ['success' => false, 'error' => "Созданный WebP файл поврежден: $newAbsolutePath"];
    }
    imagedestroy($testImage);

    return ['success' => true, 'error' => null];
}

// --- AJAX Обработчик Конвертации ---
if (isset($_POST['action']) && $_POST['action'] === 'convert_model') {
    // Отключаем вывод ошибок для AJAX запросов (будем их логировать)
    ini_set('display_errors', '0');

    // Устанавливаем кастомный обработчик ошибок
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        logConversion("PHP Error [$errno]: $errstr in $errfile:$errline", 'ERROR');
        // Не останавливаем выполнение, просто логируем
        return true;
    });

    // Обработчик фатальных ошибок
    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            logConversion("Fatal Error: {$error['message']} in {$error['file']}:{$error['line']}", 'ERROR');
            if (ob_get_length())
                ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'msg' => 'Фатальная ошибка: ' . $error['message']
            ]);
        }
    });

    // Очищаем любой вывод, который мог произойти до этого
    if (ob_get_length())
        ob_clean();

    header('Content-Type: application/json');

    // Детальная отладка CSRF
    if (!isset($_POST['csrf_token'])) {
        logConversion("CSRF token not provided in POST", 'ERROR');
        echo json_encode(['success' => false, 'msg' => 'CSRF токен не предоставлен']);
        exit;
    }

    if (!isset($_SESSION['webp_csrf_token'])) {
        logConversion("CSRF token not found in session", 'ERROR');
        echo json_encode(['success' => false, 'msg' => 'CSRF токен не найден в сессии']);
        exit;
    }

    if ($_POST['csrf_token'] !== $_SESSION['webp_csrf_token']) {
        logConversion("CSRF token mismatch. POST: " . $_POST['csrf_token'] . ", SESSION: " . $_SESSION['webp_csrf_token'], 'ERROR');
        echo json_encode(['success' => false, 'msg' => 'Неверный CSRF токен']);
        exit;
    }

    $model_id = (int) $_POST['model_id'];
    logConversion("Starting conversion for model_id: $model_id", 'INFO');

    try {
        // Получаем данные модели (используем стандартный подход как в Db.php)
        $query = "SELECT web_id, l2_pic, m_pic_big, logo FROM rent_model_web WHERE model_id = " . intval($model_id);
        $result = $mysqli->query($query);

        if (!$result) {
            throw new Exception("Failed to execute query: " . $mysqli->error);
        }

        $model = $result->fetch_assoc();

        if (!$model) {
            logConversion("Model $model_id not found", 'ERROR');
            echo json_encode(['success' => false, 'msg' => 'Модель не найдена']);
            exit;
        }
    } catch (Exception $e) {
        logConversion("Database error: " . $e->getMessage(), 'ERROR');
        echo json_encode(['success' => false, 'msg' => 'Ошибка БД: ' . $e->getMessage()]);
        exit;
    }

    $web_id = $model['web_id'];
    $updates = [];
    $converted_count = 0;
    $errors = [];

    try {
        $fields = ['l2_pic' => $model['l2_pic'], 'm_pic_big' => $model['m_pic_big'], 'logo' => $model['logo']];

        foreach ($fields as $colName => $path) {
            // Пропускаем пустые пути и внешние URL (начинаются с http:// или https://)
            if (empty($path) || preg_match('#^https?://#i', $path)) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                continue;
            }


            $newRelativePath = substr($path, 0, strrpos($path, '.')) . '.webp';
            $newAbsolutePath = $_SERVER['DOCUMENT_ROOT'] . $newRelativePath;

            $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $path;
            if (!file_exists($absolutePath)) {
                // Если оригинал отсутствует, но WebP уже есть (общий файл) - просто обновляем БД
                if (file_exists($newAbsolutePath)) {
                    $updates[$colName] = $newRelativePath;
                    $converted_count++;
                } else {
                    $errors[] = "Файл не найден на диске: $path";
                    logConversion("Missing file for model $model_id, field $colName: $path", 'WARNING');
                }
                continue;
            }

            // Конвертируем используя helper функцию
            $conversionResult = convertImageToWebP($absolutePath, $newAbsolutePath, $ext);

            if ($conversionResult['success']) {
                $updates[$colName] = $newRelativePath;
                $converted_count++;
                // Удаляем старый файл ТОЛЬКО после успешной конвертации и проверки
                if (!unlink($absolutePath)) {
                    $errors[] = "Не удалось удалить исходный файл: $path";
                }
            } else {
                $errors[] = $conversionResult['error'];
            }
        }

        // Обновляем rent_model_web (используем стандартный подход как в Db.php)
        if (!empty($updates)) {
            $setClauses = [];
            foreach ($updates as $col => $val) {
                $escaped_val = $mysqli->real_escape_string($val);
                $setClauses[] = "$col = '$escaped_val'";
            }

            $sql = "UPDATE rent_model_web SET " . implode(", ", $setClauses) . " WHERE web_id = " . intval($web_id);
            if (!$mysqli->query($sql)) {
                logConversion("Failed to execute UPDATE: " . $mysqli->error . " SQL: $sql", 'ERROR');
                $errors[] = "Ошибка обновления БД: " . $mysqli->error;
            }
        }

        // Обрабатываем доп фотки (dop_photos) - используем стандартный подход как в Db.php
        $query_dop = "SELECT dop_id, src FROM dop_photos WHERE model_id = " . intval($model_id);
        $result_dop = $mysqli->query($query_dop);

        $dops = [];
        if ($result_dop) {
            while ($row = $result_dop->fetch_assoc()) {
                $dops[] = $row;
            }
        } else {
            logConversion("Failed to query dop_photos: " . $mysqli->error, 'ERROR');
        }

        foreach ($dops as $dop) {
            $path = $dop['src'];

            // Пропускаем пустые пути и внешние URL
            if (empty($path) || preg_match('#^https?://#i', $path)) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                continue;
            }


            $newRelativePath = substr($path, 0, strrpos($path, '.')) . '.webp';
            $newAbsolutePath = $_SERVER['DOCUMENT_ROOT'] . $newRelativePath;

            $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $path;
            if (!file_exists($absolutePath)) {
                if (file_exists($newAbsolutePath)) {
                    // Обновляем запись в БД
                    $sql_dop = "UPDATE dop_photos SET src = '" . $mysqli->real_escape_string($newRelativePath) . "' WHERE dop_id = " . intval($dop['dop_id']);
                    if ($mysqli->query($sql_dop)) {
                        $converted_count++;
                    }
                } else {
                    $errors[] = "Доп.фото не найдено: $path";
                    logConversion("Missing dop_photo for model $model_id, dop_id={$dop['dop_id']}: $path", 'WARNING');
                }
                continue;
            }

            // Конвертируем используя helper функцию
            $conversionResult = convertImageToWebP($absolutePath, $newAbsolutePath, $ext);

            if ($conversionResult['success']) {
                // Обновляем запись в БД (используем стандартный подход как в Db.php)
                $escaped_path = $mysqli->real_escape_string($newRelativePath);
                $dop_id = intval($dop['dop_id']);
                $update_sql = "UPDATE dop_photos SET src = '$escaped_path' WHERE dop_id = $dop_id";

                if ($mysqli->query($update_sql)) {
                    $converted_count++;
                    // Удаляем старый файл ТОЛЬКО после успешного обновления БД
                    if (!unlink($absolutePath)) {
                        $errors[] = "Не удалось удалить доп.фото: $path";
                    }
                } else {
                    $errors[] = "Не удалось обновить БД для доп.фото: $path";
                    // Удаляем созданный WebP, т.к. БД не обновилась
                    @unlink($newAbsolutePath);
                }
            } else {
                $errors[] = $conversionResult['error'];
            }
        }

        logConversion("Completed conversion for model_id: $model_id. Converted: $converted_count, Errors: " . count($errors), 'INFO');

        echo json_encode([
            'success' => true,
            'converted' => $converted_count,
            'errors' => $errors
        ]);
    } catch (Exception $e) {
        logConversion("Fatal error during conversion for model_id $model_id: " . $e->getMessage(), 'ERROR');
        echo json_encode([
            'success' => false,
            'msg' => 'Критическая ошибка: ' . $e->getMessage(),
            'converted' => $converted_count,
            'errors' => $errors
        ]);
    }
    exit;
}

// --- Frontend Отображение ---

// Pagination
$per_page = 100;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Сначала получаем общее количество
// Находим модели, у которых есть хотя бы одно НЕ-WebP изображение
$count_query = "SELECT COUNT(DISTINCT rmw.model_id) as total
          FROM rent_model_web rmw
          LEFT JOIN dop_photos dp ON rmw.model_id = dp.model_id
          WHERE (
              (rmw.l2_pic IS NOT NULL AND rmw.l2_pic != '' AND rmw.l2_pic NOT LIKE '%.webp' AND rmw.l2_pic NOT LIKE 'http%')
              OR (rmw.m_pic_big IS NOT NULL AND rmw.m_pic_big != '' AND rmw.m_pic_big NOT LIKE '%.webp' AND rmw.m_pic_big NOT LIKE 'http%')
              OR (rmw.logo IS NOT NULL AND rmw.logo != '' AND rmw.logo NOT LIKE '%.webp' AND rmw.logo NOT LIKE 'http%')
              OR (dp.src IS NOT NULL AND dp.src != '' AND dp.src NOT LIKE '%.webp' AND dp.src NOT LIKE 'http%')
          )
          AND (
              rmw.l2_pic LIKE '%.jpg' OR rmw.l2_pic LIKE '%.jpeg' OR rmw.l2_pic LIKE '%.png'
              OR rmw.m_pic_big LIKE '%.jpg' OR rmw.m_pic_big LIKE '%.jpeg' OR rmw.m_pic_big LIKE '%.png'
              OR rmw.logo LIKE '%.jpg' OR rmw.logo LIKE '%.jpeg' OR rmw.logo LIKE '%.png'
              OR (dp.src LIKE '%.jpg' OR dp.src LIKE '%.jpeg' OR dp.src LIKE '%.png')
          )";

$count_result = $mysqli->query($count_query);
$total_to_convert = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_to_convert / $per_page);

// Находим все модели, у которых есть хотя бы один jpg/png (с пагинацией)
// Исключаем модели, которые уже полностью сконвертированы в WebP
$query = "SELECT rmw.model_id, rmw.web_id, tr.model, trc.dog_name, rmw.page_addr, rmw.l2_pic, rmw.m_pic_big, rmw.logo
          FROM rent_model_web rmw
          LEFT JOIN tovar_rent tr ON rmw.model_id = tr.tovar_rent_id
          LEFT JOIN tovar_rent_cat trc ON tr.tovar_rent_cat_id = trc.tovar_rent_cat_id
          WHERE (
              (rmw.l2_pic LIKE '%.jpg' OR rmw.l2_pic LIKE '%.jpeg' OR rmw.l2_pic LIKE '%.png')
              OR (rmw.m_pic_big LIKE '%.jpg' OR rmw.m_pic_big LIKE '%.jpeg' OR rmw.m_pic_big LIKE '%.png')
              OR (rmw.logo LIKE '%.jpg' OR rmw.logo LIKE '%.jpeg' OR rmw.logo LIKE '%.png')
              OR rmw.model_id IN (SELECT model_id FROM dop_photos WHERE (src LIKE '%.jpg' OR src LIKE '%.jpeg' OR src LIKE '%.png') AND src NOT LIKE 'http%')
          )
          GROUP BY rmw.model_id
          ORDER BY trc.dog_name, tr.model
          LIMIT $per_page OFFSET $offset";

$result = $mysqli->query($query);
$models = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $models[] = $row;
    }
}
$models_on_page = count($models);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Массовая Конвертация WebP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #fdfdfd;
            color: #333;
            margin: 20px;
        }

        h1 {
            color: #8F55A6;
        }

        .stats {
            background: #eef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ccd;
        }

        .controls {
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .btn {
            padding: 8px 15px;
            background: #8F55A6;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .btn:hover {
            background: #7A4293;
        }

        .btn-green {
            background: #4CAF50;
        }

        .btn-green:hover {
            background: #45a049;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: white;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f2f2f2;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending {
            background: #ffeeba;
            color: #856404;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
        }

        .progress-container {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }

        .progress-bar {
            width: 0%;
            height: 20px;
            background-color: #4CAF50;
            border-radius: 5px;
            transition: width 0.3s;
        }

        .links a {
            color: #0066cc;
            text-decoration: none;
            margin-right: 10px;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .row-converted {
            background-color: #d4edda !important;
            transition: background-color 0.5s ease;
        }

        .pagination {
            margin: 20px 0;
            text-align: center;
        }

        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #f0f0f0;
        }

        .pagination .current {
            background: #8F55A6;
            color: white;
            border-color: #8F55A6;
        }

        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>

<body>

    <h1>Массовая Конвертация Изображений в WebP</h1>

    <div class="stats">
        <strong>Всего моделей для конвертации:</strong> <?= $total_to_convert ?>
        <br>
        <strong>На текущей странице:</strong> <span id="page-count"><?= $models_on_page ?></span>
        <br>
        <strong>Страница:</strong> <?= $page ?> из <?= $total_pages ?>
        <p><i>Скрипт проверяет основные картинки (L2, L3), логотипы и все фотографии из слайдера (dop_photos).</i></p>
    </div>

    <div class="controls">
        <h3>Управление Конвертацией</h3>
        <p>Для предотвращения перегрузки сервера, конвертация выполняется по одной модели за раз через AJAX запросы.</p>

        <button class="btn btn-green" id="convert-page-btn" onclick="convertCurrentPage()"
            style="font-size: 16px; padding: 10px 20px; margin-right: 10px;">
            ▶ Сконвертировать ВСЮ страницу (<?= $models_on_page ?> моделей)
        </button>

        <label>или выборочно: </label>
        <input type="number" id="batch-size" value="10" min="1" max="100" style="width: 60px; padding: 5px;">
        <button class="btn btn-green" id="start-batch-btn" onclick="startBatch()">▶ Запустить Batch</button>

        <div class="progress-container" id="progress-container">
            <div class="progress-bar" id="progress-bar"></div>
            <div style="text-align: center; font-size: 12px; margin-top: 5px;" id="progress-text">0 / 0</div>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1">« Первая</a>
                <a href="?page=<?= $page - 1 ?>">‹ Назад</a>
            <?php else: ?>
                <span class="disabled">« Первая</span>
                <span class="disabled">‹ Назад</span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 3);
            $end = min($total_pages, $page + 3);
            for ($i = $start; $i <= $end; $i++):
                if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif;
            endfor;
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>">Вперед ›</a>
                <a href="?page=<?= $total_pages ?>">Последняя »</a>
            <?php else: ?>
                <span class="disabled">Вперед ›</span>
                <span class="disabled">Последняя »</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th width="50">ID</th>
                <th>Категория / Модель</th>
                <th>Просмотр на сайте</th>
                <th width="150">Статус</th>
                <th width="150">Действие</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($models as $idx => $m): ?>
                <?php
                // Use the dedicated class method to build the exact URL
                $mw = \bb\classes\ModelWeb::getByModelId($m['model_id']);
                $publicUrl = $mw ? $mw->getUrlPageAddress() : "/ru/";

                // Подсчитываем сколько изображений нужно конвертировать
                $to_convert = [];
                if (!empty($m['l2_pic']) && preg_match('/\.(jpg|jpeg|png)$/i', $m['l2_pic']))
                    $to_convert[] = 'L2';
                if (!empty($m['m_pic_big']) && preg_match('/\.(jpg|jpeg|png)$/i', $m['m_pic_big']))
                    $to_convert[] = 'L3';
                if (!empty($m['logo']) && preg_match('/\.(jpg|jpeg|png)$/i', $m['logo']))
                    $to_convert[] = 'Logo';

                // Проверяем доп фотки (используем стандартный подход как в Db.php)
                $mid = intval($m['model_id']);
                $dop_count_query = "SELECT COUNT(*) as cnt FROM dop_photos WHERE model_id = $mid AND (src LIKE '%.jpg' OR src LIKE '%.jpeg' OR src LIKE '%.png') AND src NOT LIKE 'http%'";
                $dop_count_result = $mysqli->query($dop_count_query);

                if ($dop_count_result) {
                    $dop_row = $dop_count_result->fetch_assoc();
                    $dop_count = $dop_row['cnt'];
                    if ($dop_count > 0)
                        $to_convert[] = "Slider($dop_count)";
                }

                $convert_info = implode(', ', $to_convert);
                ?>
                <tr id="row-<?= $m['model_id'] ?>" data-model-id="<?= $m['model_id'] ?>" class="model-row">
                    <td>
                        <?= $m['model_id'] ?>
                    </td>
                    <td>
                        <strong>
                            <?= htmlspecialchars($m['dog_name'] ?? 'Без категории') ?>
                        </strong><br>
                        <?= htmlspecialchars($m['model']) ?>
                        <?php if ($convert_info): ?>
                            <br><small style="color: #666;">📸 <?= $convert_info ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="links">
                        <a href="<?= $publicUrl ?>" target="_blank" title="Открыть карточку товара на сайте">🔍 Карточка</a>
                    </td>
                    <td>
                        <span class="status-badge status-pending" id="status-<?= $m['model_id'] ?>">Ожидает</span>
                    </td>
                    <td>
                        <button class="btn" onclick="convertSingle(<?= $m['model_id'] ?>)">Сконвертировать 1</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1">« Первая</a>
                <a href="?page=<?= $page - 1 ?>">‹ Назад</a>
            <?php else: ?>
                <span class="disabled">« Первая</span>
                <span class="disabled">‹ Назад</span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 3);
            $end = min($total_pages, $page + 3);
            for ($i = $start; $i <= $end; $i++):
                if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif;
            endfor;
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>">Вперед ›</a>
                <a href="?page=<?= $total_pages ?>">Последняя »</a>
            <?php else: ?>
                <span class="disabled">Вперед ›</span>
                <span class="disabled">Последняя »</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
        let queue = [];
        let isProcessing = false;
        let autoNext = false;
        let batchTotal = 0;
        let batchCurrent = 0;

        function convertSingle(modelId, isBatch = false) {
            if (!isBatch) {
                autoNext = false; // Stop batch if manual click
            }

            let statusSpan = document.getElementById('status-' + modelId);
            let row = document.getElementById('row-' + modelId);

            statusSpan.className = 'status-badge status-pending';
            statusSpan.innerText = 'Конвертация...';

            let formData = new FormData();
            formData.append('action', 'convert_model');
            formData.append('model_id', modelId);
            formData.append('csrf_token', '<?= $_SESSION['webp_csrf_token'] ?>');

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error('HTTP error ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        statusSpan.className = 'status-badge status-success';
                        let txt = 'Готово (' + data.converted + ' шт)';
                        if (data.errors && data.errors.length > 0) {
                            txt += ' +Ошибки';
                            statusSpan.title = data.errors.join('\n'); // Show errors on hover
                        }
                        statusSpan.innerText = txt;
                        row.classList.remove('model-row'); // remove from future batches

                        // Add green background to show success
                        row.classList.add('row-converted');

                        // Update Page Count
                        let pageCountSpan = document.getElementById('page-count');
                        let remaining = parseInt(pageCountSpan.innerText) - 1;
                        pageCountSpan.innerText = remaining;
                    } else {
                        statusSpan.className = 'status-badge status-error';
                        statusSpan.innerText = 'Ошибка: ' + (data.msg || 'Неизвестно');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    statusSpan.className = 'status-badge status-error';
                    statusSpan.innerText = 'Ошибка: ' + error.message;
                })
                .finally(() => {
                    if (isBatch && autoNext) {
                        batchCurrent++;
                        updateProgress();
                        setTimeout(processNextInQueue, 300); // 300ms delay between requests to spare CPU
                    }
                });
        }

        function convertCurrentPage() {
            if (isProcessing) return;

            // Find all pending rows on current page
            let rows = document.querySelectorAll('.model-row');
            if (rows.length === 0) {
                alert("Все модели на этой странице уже сконвертированы!");
                return;
            }

            if (!confirm(`Вы уверены, что хотите сконвертировать все ${rows.length} моделей на текущей странице?`)) {
                return;
            }

            queue = [];
            rows.forEach(row => {
                queue.push(row.getAttribute('data-model-id'));
            });

            batchTotal = queue.length;
            batchCurrent = 0;
            autoNext = true;
            isProcessing = true;

            document.getElementById('convert-page-btn').disabled = true;
            document.getElementById('start-batch-btn').disabled = true;
            document.getElementById('progress-container').style.display = 'block';
            updateProgress();

            processNextInQueue();
        }

        function startBatch() {
            if (isProcessing) return;

            let batchSize = parseInt(document.getElementById('batch-size').value);
            if (batchSize < 1 || batchSize > 100) {
                alert("Размер batch должен быть от 1 до 100");
                return;
            }

            // Find all pending rows
            let rows = document.querySelectorAll('.model-row');
            if (rows.length === 0) {
                alert("Нет моделей для конвертации!");
                return;
            }

            queue = [];
            for (let i = 0; i < Math.min(batchSize, rows.length); i++) {
                queue.push(rows[i].getAttribute('data-model-id'));
            }

            batchTotal = queue.length;
            batchCurrent = 0;
            autoNext = true;
            isProcessing = true;

            document.getElementById('convert-page-btn').disabled = true;
            document.getElementById('start-batch-btn').disabled = true;
            document.getElementById('progress-container').style.display = 'block';
            updateProgress();

            processNextInQueue();
        }

        function processNextInQueue() {
            if (queue.length === 0 || !autoNext) {
                isProcessing = false;
                document.getElementById('convert-page-btn').disabled = false;
                document.getElementById('start-batch-btn').disabled = false;
                if (autoNext && batchTotal > 0) {
                    let remaining = document.querySelectorAll('.model-row').length;
                    let msg = `Конвертация завершена!\nСконвертировано: ${batchTotal}\nОсталось на странице: ${remaining}`;
                    setTimeout(() => alert(msg), 500);
                }
                return;
            }

            let modelId = queue.shift();
            convertSingle(modelId, true);
        }

        function updateProgress() {
            let percent = (batchTotal === 0) ? 0 : Math.round((batchCurrent / batchTotal) * 100);
            document.getElementById('progress-bar').style.width = percent + '%';
            document.getElementById('progress-text').innerText = batchCurrent + ' / ' + batchTotal;
        }
    </script>

</body>

</html>