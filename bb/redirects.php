<?php
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');

echo \bb\Base::pageStartB5('Перенаправления (Redirects).');
\bb\Base::loginCheck();

$db = \bb\Db::getInstance();
$mysqli = $db->getConnection();

// --- Обработка действий ---
$message = '';

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    switch ($action) {
        case 'add':
            $source = trim($mysqli->real_escape_string($_POST['source_url']));
            $target = trim($mysqli->real_escape_string($_POST['target_url']));
            $code = intval($_POST['status_code']);
            $is_regex = isset($_POST['is_regex']) ? 1 : 0;
            $comment = trim($mysqli->real_escape_string($_POST['comment'] ?? ''));
            if ($source === '' || $target === '') {
                $message = '<div class="alert alert-danger">Заполните оба поля URL.</div>';
            } else {
                if (!$is_regex && $source[0] !== '/')
                    $source = '/' . $source;
                $sql = "INSERT INTO redirects (source_url, target_url, status_code, is_regex, comment)
                        VALUES ('$source', '$target', $code, $is_regex, '$comment')";
                if ($mysqli->query($sql)) {
                    $message = '<div class="alert alert-success">Перенаправление добавлено.</div>';
                } else {
                    $message = ($mysqli->errno === 1062)
                        ? '<div class="alert alert-danger">Такой исходный URL уже существует.</div>'
                        : '<div class="alert alert-danger">Ошибка: ' . $mysqli->error . '</div>';
                }
            }
            break;
        case 'update':
            $id = intval($_POST['id']);
            $source = trim($mysqli->real_escape_string($_POST['source_url']));
            $target = trim($mysqli->real_escape_string($_POST['target_url']));
            $code = intval($_POST['status_code']);
            $is_regex = isset($_POST['is_regex']) ? 1 : 0;
            $comment = trim($mysqli->real_escape_string($_POST['comment'] ?? ''));
            if (!$is_regex && $source[0] !== '/')
                $source = '/' . $source;
            $message = $mysqli->query("UPDATE redirects SET source_url='$source', target_url='$target', status_code=$code, is_regex=$is_regex, comment='$comment' WHERE id=$id")
                ? '<div class="alert alert-success">Обновлено.</div>'
                : '<div class="alert alert-danger">Ошибка: ' . $mysqli->error . '</div>';
            break;
        case 'delete':
            $id = intval($_POST['id']);
            $message = $mysqli->query("DELETE FROM redirects WHERE id=$id")
                ? '<div class="alert alert-success">Удалено.</div>'
                : '<div class="alert alert-danger">Ошибка: ' . $mysqli->error . '</div>';
            break;
        case 'toggle':
            $id = intval($_POST['id']);
            $message = $mysqli->query("UPDATE redirects SET is_active = NOT is_active WHERE id=$id")
                ? '<div class="alert alert-success">Статус изменён.</div>'
                : '<div class="alert alert-danger">Ошибка: ' . $mysqli->error . '</div>';
            break;
        case 'reset_hits':
            $id = intval($_POST['id']);
            $message = $mysqli->query("UPDATE redirects SET hit_count = 0, last_hit_at = NULL WHERE id=$id")
                ? '<div class="alert alert-success">Счётчик сброшен.</div>'
                : '<div class="alert alert-danger">Ошибка: ' . $mysqli->error . '</div>';
            break;
    }
}

// --- Получение данных (Пагинация + Сортировка) ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Сортировка
// --- Вспомогательная функция для ссылок сортировки ---
function sortLink($key, $label, $currentSort, $currentDir)
{
    $newDir = ($currentSort === $key && $currentDir === 'DESC') ? 'asc' : 'desc';
    $arrow = '';
    if ($currentSort === $key) {
        $arrow = ($currentDir === 'DESC') ? ' ▼' : ' ▲';
    }
    $url = "?page=1&sort=$key&dir=$newDir";
    return "<a href=\"$url\" class=\"sort-link\">$label<span class=\"sort-arrow\">$arrow</span></a>";
}

$sortMap = [
    'id' => 'id',
    'source' => 'source_url',
    'target' => 'target_url',
    'code' => 'status_code',
    'status' => 'is_active',
    'hits' => 'hit_count',
    'last' => 'last_hit_at',
    'comment' => 'comment'
];
$sortKey = $_GET['sort'] ?? 'id';
$sortCol = $sortMap[$sortKey] ?? 'id';
$dir = isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc' ? 'ASC' : 'DESC';

// Общее количество
$totalRes = $mysqli->query("SELECT COUNT(*) as cnt FROM redirects");
$totalRows = $totalRes ? $totalRes->fetch_assoc()['cnt'] : 0;
$totalPages = ceil($totalRows / $limit);

// Данные
$sql = "SELECT * FROM redirects ORDER BY $sortCol $dir LIMIT $limit OFFSET $offset";
$result = $mysqli->query($sql);
$redirects = [];
if ($result) {
    while ($row = $result->fetch_assoc())
        $redirects[] = $row;
}
?>

<link rel="stylesheet" href="/bb/assets/styles/cur_style.css?v=1">
<style>
    .sort-link {
        color: #fff;
        text-decoration: none;
        display: block;
        position: relative;
    }

    .sort-link:hover {
        color: #ddd;
    }

    .sort-arrow {
        font-size: 10px;
        margin-left: 5px;
        opacity: 0.7;
    }

    .rc {
        max-width: 1600px;
        margin: 20px auto;
        padding: 0 15px;
    }

    /* Сворачиваемая форма */
    .add-toggle {
        cursor: pointer;
        user-select: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: #e9ecef;
        border-radius: 6px;
        font-weight: 600;
        color: #495057;
        border: 1px solid #ced4da;
        transition: all .2s;
        margin-bottom: 15px;
    }

    .add-toggle:hover {
        background: #dee2e6;
    }

    .add-toggle .arrow {
        transition: transform .2s;
        display: inline-block;
    }

    .add-toggle.open .arrow {
        transform: rotate(90deg);
    }

    .add-form-wrap {
        display: none;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .add-form-wrap.open {
        display: block;
    }

    /* Инлайн-редактирование */
    .edit-row {
        display: none;
        background: #fff9e6 !important;
    }

    .edit-row.active {
        display: table-row;
    }

    .edit-row td {
        padding: 12px !important;
    }

    .ef {
        display: flex;
        gap: 8px;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .ef .fg {
        display: flex;
        flex-direction: column;
    }

    .ef .fg label {
        font-size: 11px;
        color: #666;
        margin-bottom: 2px;
    }

    /* Searchable dropdown & Live Search items */
    .sd-wrap,
    .ls-wrap {
        position: relative;
    }

    .sd-list {
        position: absolute;
        z-index: 1000;
        top: 100%;
        left: 0;
        right: 0;
        max-height: 250px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #ced4da;
        border-top: 0;
        border-radius: 0 0 4px 4px;
        display: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
    }

    .sd-list.open {
        display: block;
    }

    .sd-item {
        padding: 6px 10px;
        cursor: pointer;
        font-size: 13px;
        display: flex;
        justify-content: space-between;
        border-bottom: 1px solid #f0f0f0;
    }

    .sd-item:hover,
    .sd-item.highlighted {
        background: #e7f1ff;
    }

    .ls-tree {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        background: #fff;
        border-radius: 4px;
        margin-top: 5px;
    }

    .ls-node {
        padding-left: 15px;
    }

    .ls-row {
        display: flex;
        align-items: center;
        padding: 4px 8px;
        cursor: pointer;
        border-bottom: 1px solid #f8f9fa;
        gap: 8px;
    }

    .ls-row:hover {
        background: #f0f8ff;
    }

    .ls-row.selected {
        background: #e7f1ff;
        border-left: 3px solid #0d6efd;
    }

    .ls-children {
        border-left: 1px solid #eee;
        margin-left: 6px;
    }

    .ls-name {
        font-weight: 500;
        font-size: 13px;
        color: #333;
    }

    .ls-url {
        font-family: monospace;
        font-size: 11px;
        color: #999;
        margin-left: auto;
    }

    .field-error {
        color: #dc3545;
        font-size: 12px;
        margin-top: 4px;
        display: none;
    }

    .field-error.visible {
        display: block;
    }

    .selected-url {
        margin-top: 6px;
        padding: 6px 10px;
        background: #d4edda;
        border: 1px solid #b7dfbf;
        border-radius: 5px;
        font-size: 13px;
        color: #155724;
        display: none;
    }

    .selected-url.visible {
        display: block;
    }

    .selected-url a {
        font-weight: 600;
        text-decoration: underline;
        color: #0d6efd;
    }


    .table td,
    .table th {
        vertical-align: middle;
        font-size: 14px;
    }

    .badge-active {
        background-color: #28a745;
    }

    .badge-inactive {
        background-color: #dc3545;
    }

    .btn-actions {
        display: flex;
        gap: 4px;
        flex-wrap: nowrap;
    }

    .source-url {
        font-family: monospace;
        color: #0d6efd;
        font-size: 13px;
        word-break: break-all;
        min-width: 200px;
        max-width: 400px;
    }

    .target-url {
        font-family: monospace;
        color: #198754;
        font-size: 13px;
        word-break: break-all;
        min-width: 200px;
        max-width: 400px;
    }

    .status-code {
        font-weight: bold;
    }

    .status-301 {
        color: #6f42c1;
    }

    .status-302 {
        color: #fd7e14;
    }

    /* Сортировка */
    th.sortable {
        cursor: pointer;
        user-select: none;
        position: relative;
    }

    th.sortable:hover {
        background-color: #444;
        /* Hover effect for dark header */
    }

    th.sortable::after {
        content: '↕';
        font-size: 10px;
        margin-left: 5px;
        opacity: 0.5;
    }

    th.sortable.asc::after {
        content: '▲';
        opacity: 1;
    }

    th.sortable.desc::after {
        content: '▼';
        opacity: 1;
    }

    /* Сворачиваемая форма */
    .add-toggle {
        cursor: pointer;
        user-select: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: #e9ecef;
        border-radius: 6px;
        font-weight: 600;
        color: #495057;
        border: 1px solid #ced4da;
        transition: all .2s;
        margin-bottom: 15px;
    }

    .add-toggle:hover {
        background: #dee2e6;
    }

    .add-toggle .arrow {
        transition: transform .2s;
        display: inline-block;
    }

    .add-toggle.open .arrow {
        transform: rotate(90deg);
    }

    .add-form-wrap {
        display: none;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .add-form-wrap.open {
        display: block;
    }

    /* Searchable dropdown */
    .sd-wrap {
        position: relative;
        margin-bottom: 4px;
    }

    .sd-input {
        width: 100%;
        padding: 5px 10px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 13px;
    }

    .sd-list {
        position: absolute;
        z-index: 100;
        top: 100%;
        left: 0;
        right: 0;
        max-height: 250px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #ced4da;
        border-top: 0;
        border-radius: 0 0 4px 4px;
        display: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
    }

    .sd-list.open {
        display: block;
    }

    .sd-item {
        padding: 6px 10px;
        cursor: pointer;
        font-size: 13px;
        display: flex;
        justify-content: space-between;
        border-bottom: 1px solid #f0f0f0;
    }

    .sd-item:hover,
    .sd-item.highlighted {
        background: #e7f1ff;
    }

    .sd-item .sd-url {
        color: #999;
        font-family: monospace;
        font-size: 11px;
    }

    .sd-item-root {
        font-weight: 600;
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    /* Каскадные шаги */
    .cascade-step {
        margin-top: 6px;
    }

    .cascade-step label {
        font-size: 11px;
        color: #888;
        margin-bottom: 2px;
        display: block;
    }

    .target-manual {
        display: block;
    }

    .target-select {
        display: none;
    }

    .target-search {
        display: none;
    }

    .target-mode-btns {
        display: flex;
        gap: 6px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }

    .target-mode-btns .btn {
        font-size: 12px;
        padding: 2px 10px;
    }

    /* Дерево результатов живого поиска */
    .ls-wrap {
        position: relative;
    }

    .ls-input {
        width: 100%;
        padding: 7px 36px 7px 32px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 14px;
        background: #fff url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%23999" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg>') no-repeat 10px center / 16px;
    }

    .ls-clear {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        width: 28px;
        height: 28px;
        border: none;
        background: none;
        font-size: 18px;
        color: #aaa;
        cursor: pointer;
        display: none;
        border-radius: 50%;
        transition: background .15s, color .15s;
        text-align: center;
        line-height: 28px;
    }

    .ls-clear:hover {
        color: #dc3545;
        background: #fff0f0;
    }

    .ls-hint {
        font-size: 11px;
        color: #999;
        margin-top: 3px;
    }

    .ls-tree {
        margin-top: 8px;
        max-height: 350px;
        overflow-y: auto;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        background: #fff;
    }

    .ls-tree:empty {
        border: 0;
    }

    .ls-node {
        border-bottom: 1px solid #f0f0f0;
    }

    .ls-node:last-child {
        border-bottom: 0;
    }

    .ls-row {
        display: flex;
        align-items: center;
        padding: 4px 8px;
        cursor: pointer;
        transition: background .15s;
        gap: 4px;
        flex-wrap: wrap;
    }

    .ls-row:hover {
        background: #e7f1ff;
    }

    .ls-row.selected {
        background: #d1ecf1;
    }

    .ls-icon {
        flex-shrink: 0;
        width: 18px;
        text-align: center;
        font-size: 12px;
    }

    .ls-name {
        font-size: 13px;
    }

    .ls-name mark {
        background: #fff3cd;
        padding: 0 1px;
        border-radius: 2px;
    }

    .ls-url {
        font-family: monospace;
        font-size: 11px;
        color: #999;
        width: 100%;
        padding-left: 22px;
        margin-top: -2px;
    }

    .ls-type {
        font-size: 10px;
        padding: 1px 5px;
        border-radius: 3px;
        flex-shrink: 0;
        font-weight: 600;
    }

    .ls-type-razdel {
        background: #e2e3f1;
        color: #4a4e8a;
    }

    .ls-type-subrazdel {
        background: #d4edda;
        color: #2d6a4f;
    }

    .ls-type-category {
        background: #fff3cd;
        color: #856404;
    }

    .ls-type-model {
        background: #f8d7da;
        color: #842029;
    }

    .ls-children {
        padding-left: 20px;
    }

    .selected-url {
        margin-top: 6px;
        padding: 6px 10px;
        background: #d4edda;
        border: 1px solid #b7dfbf;
        border-radius: 5px;
        font-size: 13px;
        color: #155724;
        display: none;
    }

    .selected-url.visible {
        display: block;
    }

    .selected-url code {
        font-size: 13px;
        font-weight: 600;
        color: #0d6efd;
    }

    .selected-url a {
        font-size: 13px;
        font-weight: 600;
        color: #0d6efd;
        text-decoration: underline;
    }

    .selected-url a:hover {
        color: #0a58ca;
    }

    .field-error {
        color: #dc3545;
        font-size: 12px;
        margin-top: 4px;
        display: none;
    }

    .field-error.visible {
        display: block;
    }

    /* Инлайн-редактирование */
    .edit-row {
        display: none;
        background: #fff9e6 !important;
    }

    .edit-row.active {
        display: table-row;
    }

    .edit-row td {
        padding: 12px !important;
    }

    .ef {
        display: flex;
        gap: 8px;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .ef .fg {
        display: flex;
        flex-direction: column;
    }

    .ef .fg label {
        font-size: 11px;
        color: #666;
        margin-bottom: 2px;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navTop"><span
            class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navTop">
        <div class="navbar-nav">
            <a class="nav-item nav-link" href="/bb/page_management.php">Страницы</a>
            <a class="nav-item nav-link active" href="/bb/redirects.php"><strong>Перенаправления</strong></a>
        </div>
    </div>
</nav>

<div class="rc">
    <h2>Управление перенаправлениями</h2>
    <p class="text-muted">Когда пользователь заходит на «Откуда», его автоматически перенаправляет на «Куда».</p>
    <?= $message ?>

    <!-- Кнопка-аккордеон -->
    <div class="add-toggle" onclick="toggleAddForm()">
        <span class="arrow">▶</span> Добавить новое перенаправление
    </div>

    <!-- Форма добавления (скрыта) -->
    <div class="add-form-wrap" id="addFormWrap">
        <form method="post" id="addForm">
            <input type="hidden" name="action" value="add">
            <div class="row g-3 align-items-start">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Откуда (исходный URL)</label>
                    <input type="text" class="form-control" name="source_url" placeholder="/old-page" required>
                    <small class="text-muted">Начинается с /</small>
                    <div class="field-error" id="add-source-error"></div>
                </div>
                <div class="col-md-5" id="add-target-col">
                    <label class="form-label fw-bold">Куда (целевой URL)</label>
                    <div class="target-mode-btns">
                        <button type="button" class="btn btn-sm btn-outline-secondary active"
                            onclick="setTargetMode('manual','add',this)">✍️ Вручную</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            onclick="setTargetMode('search','add',this)">🔍 Живой поиск</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            onclick="setTargetMode('select','add',this)">📋 Из структуры</button>
                    </div>
                    <div class="selected-url" id="add-selected-url">✅ Выбрано: <a href="#" target="_blank"></a>
                    </div>
                    <div class="target-manual" id="add-manual">
                        <input type="text" class="form-control" name="target_url" id="add-target-url"
                            placeholder="/new-page или https://...">
                    </div>
                    <div class="target-search" id="add-search">
                        <div class="ls-wrap">
                            <input type="text" class="ls-input" id="add-ls-input"
                                placeholder="Поиск по названию страницы..." autocomplete="off">
                            <button type="button" class="ls-clear" onclick="clearLiveSearch('add')">×</button>
                        </div>
                        <div class="ls-hint">Минимум 2 символа. Поиск по разделам, подразделам, категориям и
                            моделям.
                        </div>
                        <div class="ls-tree" id="add-ls-tree"></div>
                    </div>
                    <div class="target-select" id="add-select">
                        <div id="add-cascade"></div>
                    </div>
                    <div class="field-error" id="add-target-error">⚠️ Укажите целевой URL: введите вручную, найдите
                        через поиск или выберите из структуры.</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Код</label>
                    <select class="form-select" name="status_code">
                        <option value="301" selected>301 — Постоянный</option>
                        <option value="302">302 — Временный</option>
                        <option value="307">307 — Временный (сохр.)</option>
                        <option value="308">308 — Постоянный (сохр.)</option>
                    </select>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="is_regex" id="add-is-regex" value="1" onchange="onAddRegexChange(this)">
                        <label class="form-check-label" for="add-is-regex" title="Источник — PCRE-паттерн (#^/old/(.+)$#), цель — строка замены (/new/$1)">
                            REGEX <span class="text-muted" style="font-size:11px">(?)</span>
                        </label>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Комментарий</label>
                    <input type="text" class="form-control" name="comment" placeholder="—">
                    <button type="submit" class="btn btn-success w-100 mt-2">➕ Добавить</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Таблица -->
    <div class="table-responsive">
        <table class="table table-striped table-hover" id="redirectsTable">
            <thead class="table-dark">
                <tr>
                    <th><?= sortLink('id', '#', $sortKey, $dir) ?></th>
                    <th><?= sortLink('source', 'Откуда', $sortKey, $dir) ?></th>
                    <th><?= sortLink('target', 'Куда', $sortKey, $dir) ?></th>
                    <th><?= sortLink('code', 'Код', $sortKey, $dir) ?></th>
                    <th>Тип</th>
                    <th><?= sortLink('status', 'Статус', $sortKey, $dir) ?></th>
                    <th><?= sortLink('hits', 'Переходы', $sortKey, $dir) ?></th>
                    <th><?= sortLink('last', 'Посл.', $sortKey, $dir) ?></th>
                    <th><?= sortLink('comment', 'Комм.', $sortKey, $dir) ?></th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($redirects)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">Пока нет перенаправлений.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($redirects as $r): ?>
                        <tr class="<?= $r['is_active'] ? '' : 'table-secondary' ?>">
                            <td><?= $r['id'] ?></td>
                            <td class="source-url"><?= htmlspecialchars($r['source_url']) ?></td>
                            <td class="target-url"><?= htmlspecialchars($r['target_url']) ?></td>
                            <td><span class="status-code status-<?= $r['status_code'] ?>"><?= $r['status_code'] ?></span>
                            </td>
                            <td><?= $r['is_regex'] ? '<span class="badge bg-warning text-dark" title="Регулярное выражение PCRE">REGEX</span>' : '<span class="badge bg-light text-secondary">exact</span>' ?></td>
                            <td><span
                                    class="badge <?= $r['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $r['is_active'] ? 'Вкл' : 'Выкл' ?></span>
                            </td>
                            <td><span
                                    class="badge bg-<?= $r['hit_count'] > 0 ? 'primary' : 'secondary' ?>"><?= intval($r['hit_count']) ?></span>
                            </td>
                            <td><small><?= $r['last_hit_at'] ? date('d.m.y H:i', strtotime($r['last_hit_at'])) : '—' ?></small>
                            </td>
                            <td><small><?= htmlspecialchars($r['comment'] ?: '—') ?></small></td>
                            <td>
                                <div class="btn-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary" title="Редактировать"
                                        onclick="toggleEdit(<?= $r['id'] ?>)">✏️</button>
                                    <form method="post" style="margin:0"><input type="hidden" name="action"
                                            value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <button type="submit"
                                            class="btn btn-sm btn-outline-<?= $r['is_active'] ? 'warning' : 'success' ?>"
                                            title="<?= $r['is_active'] ? 'Выкл' : 'Вкл' ?>"><?= $r['is_active'] ? '⏸️' : '▶️' ?></button>
                                    </form>
                                    <?php if ($r['hit_count'] > 0): ?>
                                        <form method="post" style="margin:0" onsubmit="return confirm('Сбросить?')"><input
                                                type="hidden" name="action" value="reset_hits"><input type="hidden" name="id"
                                                value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-info" title="Сбросить">🔄</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" style="margin:0" onsubmit="return confirm('Удалить?')"><input
                                            type="hidden" name="action" value="delete"><input type="hidden" name="id"
                                            value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <!-- Строка редактирования -->
                        <tr class="edit-row" id="edit-<?= $r['id'] ?>">
                            <td colspan="10">
                                <form method="post" class="ef">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <div class="fg"><label>Откуда</label>
                                        <input type="text" class="form-control form-control-sm" name="source_url"
                                            value="<?= htmlspecialchars($r['source_url']) ?>" style="width:200px" required>
                                        <div class="field-error" id="e<?= $r['id'] ?>-source-error"></div>
                                    </div>
                                    <div class="fg" style="min-width:320px">
                                        <label>Куда</label>
                                        <div class="target-mode-btns">
                                            <button type="button" class="btn btn-sm btn-outline-secondary active"
                                                onclick="setTargetMode('manual','e<?= $r['id'] ?>',this)">✍️
                                                Вручную</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                onclick="setTargetMode('search','e<?= $r['id'] ?>',this)">🔍 Поиск</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                onclick="setTargetMode('select','e<?= $r['id'] ?>',this)">📋
                                                Структура</button>
                                        </div>
                                        <div class="selected-url" id="e<?= $r['id'] ?>-selected-url">✅ <a href="#"
                                                target="_blank"></a></div>
                                        <div class="target-manual" id="e<?= $r['id'] ?>-manual">
                                            <input type="text" class="form-control form-control-sm" name="target_url"
                                                id="e<?= $r['id'] ?>-target-url"
                                                value="<?= htmlspecialchars($r['target_url']) ?>" style="width:300px">
                                        </div>
                                        <div class="target-search" id="e<?= $r['id'] ?>-search" style="display:none">
                                            <div class="ls-wrap">
                                                <input type="text" class="ls-input" id="e<?= $r['id'] ?>-ls-input"
                                                    placeholder="Поиск по названию..." autocomplete="off">
                                                <button type="button" class="ls-clear"
                                                    onclick="clearLiveSearch('e<?= $r['id'] ?>')">×</button>
                                            </div>
                                            <div class="ls-hint" style="font-size:12px;color:#888;margin-top:4px">Минимум 2
                                                символа</div>
                                            <div class="ls-tree" id="e<?= $r['id'] ?>-ls-tree"></div>
                                        </div>

                                        <div class="target-select" id="e<?= $r['id'] ?>-select">
                                            <div id="e<?= $r['id'] ?>-cascade"></div>
                                        </div>
                                        <div class="field-error" id="e<?= $r['id'] ?>-target-error">⚠️ Укажите целевой URL</div>
                                    </div>
                                    <div class="fg"><label>Код</label>
                                        <select class="form-select form-select-sm" name="status_code" style="width:80px">
                                            <option value="301" <?= $r['status_code'] == 301 ? 'selected' : '' ?>>301</option>
                                            <option value="302" <?= $r['status_code'] == 302 ? 'selected' : '' ?>>302</option>
                                            <option value="307" <?= $r['status_code'] == 307 ? 'selected' : '' ?>>307</option>
                                            <option value="308" <?= $r['status_code'] == 308 ? 'selected' : '' ?>>308</option>
                                        </select>
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" name="is_regex" id="er-regex-<?= $r['id'] ?>" value="1" <?= $r['is_regex'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="er-regex-<?= $r['id'] ?>" style="font-size:11px">REGEX</label>
                                        </div>
                                    </div>
                                    <div class="fg"><label>Комм.</label>
                                        <input type="text" class="form-control form-control-sm" name="comment"
                                            value="<?= htmlspecialchars($r['comment'] ?? '') ?>" style="width:130px">
                                    </div>
                                    <div class="fg"><label>&nbsp;</label>
                                        <div style="display:flex;gap:4px">
                                            <button type="submit" class="btn btn-sm btn-success">💾</button>
                                            <button type="button" class="btn btn-sm btn-secondary"
                                                onclick="toggleEdit(<?= $r['id'] ?>)">✕</button>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Пагинация -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <!-- First / Prev -->
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link"
                        href="?page=1&sort=<?= $sortKey ?>&dir=<?= $dir === 'ASC' ? 'asc' : 'desc' ?>">&laquo;</a>
                </li>
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link"
                        href="?page=<?= max(1, $page - 1) ?>&sort=<?= $sortKey ?>&dir=<?= $dir === 'ASC' ? 'asc' : 'desc' ?>">‹</a>
                </li>

                <!-- Page range helper -->
                <?php
                $start = max(1, $page - 3);
                $end = min($totalPages, $page + 3);
                if ($start > 1)
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link"
                            href="?page=<?= $i ?>&sort=<?= $sortKey ?>&dir=<?= $dir === 'ASC' ? 'asc' : 'desc' ?>"><?= $i ?></a>
                    </li>
                <?php endfor;
                if ($end < $totalPages)
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                ?>

                <!-- Next / Last -->
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link"
                        href="?page=<?= min($totalPages, $page + 1) ?>&sort=<?= $sortKey ?>&dir=<?= $dir === 'ASC' ? 'asc' : 'desc' ?>">›</a>
                </li>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link"
                        href="?page=<?= $totalPages ?>&sort=<?= $sortKey ?>&dir=<?= $dir === 'ASC' ? 'asc' : 'desc' ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <div class="row text-center mb-4">
        <div class="col text-muted small">
            Всего записей: <strong><?= $totalRows ?></strong>. Показано: <?= count($redirects) ?>.
        </div>
    </div>
    <small class="text-muted"><strong>Подсказка:</strong> 301 — постоянный (SEO), 302 — временный. Исходный URL
        начинается с <code>/</code>.</small>
</div>

<script>
    // === Валидация формы ===
    const URL_PATH_RE = /^\/[a-zA-Z0-9\-_\/\.%~:@!$&'()*+,;=]*$/;
    const URL_FULL_RE = /^https?:\/\/[^\s<>"{}|\\^`]+$/;

    function showFieldError(id, msg) {
        const el = document.getElementById(id);
        if (el) { el.textContent = '⚠️ ' + msg; el.classList.add('visible'); }
    }
    function hideFieldError(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('visible');
    }

    function onAddRegexChange(cb) {
        const src = document.querySelector('#addForm [name="source_url"]');
        if (src) src.placeholder = cb.checked ? '#^/old/(.+)$#' : '/old-page';
        // В режиме regex скрываем режимы выбора target (только ручной ввод)
        if (cb.checked) setTargetMode('manual', 'add', document.querySelector('.target-mode-btns .btn'));
    }

    function validateRedirectForm(form, prefix) {
        let ok = true;
        const isRegex = form.querySelector('[name="is_regex"]')?.checked || false;

        // Проверка source_url
        hideFieldError(prefix + '-source-error');
        const sourceInput = form.querySelector('[name="source_url"]');
        if (sourceInput) {
            const src = sourceInput.value.trim();
            if (!src) {
                showFieldError(prefix + '-source-error', 'Заполните исходный URL.');
                ok = false;
            } else if (isRegex) {
                // Для regex-паттернов только базовая проверка
                if (/\s/.test(src)) {
                    showFieldError(prefix + '-source-error', 'Паттерн не должен содержать пробелов.');
                    ok = false;
                }
            } else if (!src.startsWith('/')) {
                showFieldError(prefix + '-source-error', 'Исходный URL должен начинаться с /');
                ok = false;
            } else if (/\s/.test(src)) {
                showFieldError(prefix + '-source-error', 'URL не должен содержать пробелов.');
                ok = false;
            } else if (!URL_PATH_RE.test(src)) {
                showFieldError(prefix + '-source-error', 'URL содержит недопустимые символы (разрешены: буквы, цифры, -, _, /, ., %).');
                ok = false;
            }
        }

        // Проверка target_url
        hideFieldError(prefix + '-target-error');
        const targetInput = form.querySelector('[name="target_url"]');
        if (!targetInput || !targetInput.value.trim()) {
            // Определяем активный режим для контекстного сообщения
            const selectDiv = document.getElementById(prefix + '-select');
            const searchDiv = document.getElementById(prefix + '-search');
            let msg = 'Укажите целевой URL.';
            if (selectDiv && selectDiv.style.display === 'block') {
                // Режим «Из структуры» — определяем, на каком шаге остановились
                const cascade = document.getElementById(prefix + '-cascade');
                const steps = cascade ? cascade.querySelectorAll('.cascade-step') : [];
                if (steps.length === 0) msg = 'Выберите тип страницы из выпадающего списка.';
                else if (steps.length === 1) {
                    const sel = steps[0].querySelector('select');
                    if (sel && sel.value) msg = 'Выберите конкретную страницу из списка ниже.';
                    else msg = 'Выберите тип страницы из выпадающего списка.';
                } else msg = 'Завершите выбор — кликните на нужную страницу в списке.';
            } else if (searchDiv && searchDiv.style.display === 'block') {
                msg = 'Найдите и кликните на нужную страницу в результатах поиска.';
            } else {
                msg = 'Введите целевой URL вручную (начиная с / или https://).';
            }
            showFieldError(prefix + '-target-error', msg);
            ok = false;
        } else {
            const tgt = targetInput.value.trim();
            if (/\s/.test(tgt)) {
                showFieldError(prefix + '-target-error', 'URL не должен содержать пробелов.');
                ok = false;
            } else if (!isRegex && !URL_PATH_RE.test(tgt) && !URL_FULL_RE.test(tgt)) {
                showFieldError(prefix + '-target-error', 'Некорректный URL. Допускается путь /... или полный адрес https://...');
                ok = false;
            }
        }

        return ok;
    }
    // Навешиваем на все формы с action=add / action=update
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form').forEach(f => {
            const action = f.querySelector('[name="action"]');
            if (action && action.value === 'add') {
                f.addEventListener('submit', (e) => { if (!validateRedirectForm(f, 'add')) e.preventDefault(); });
            } else if (action && action.value === 'update') {
                const idInput = f.querySelector('[name="id"]');
                const prefix = idInput ? 'e' + idInput.value : 'add';
                f.addEventListener('submit', (e) => { if (!validateRedirectForm(f, prefix)) e.preventDefault(); });
            }
        });
    });

    // === Сворачиваемая форма ===
    function toggleAddForm() {
        const btn = document.querySelector('.add-toggle');
        const form = document.getElementById('addFormWrap');
        btn.classList.toggle('open');
        form.classList.toggle('open');
    }

    // === Инлайн-редактирование ===
    function toggleEdit(id) {
        document.querySelectorAll('.edit-row.active').forEach(r => { if (r.id !== 'edit-' + id) r.classList.remove('active'); });
        document.getElementById('edit-' + id).classList.toggle('active');
    }

    // === Переключение ручной / поиск / структура ===
    function setTargetMode(mode, prefix, btn) {
        btn.parentElement.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const manual = document.getElementById(prefix + '-manual');
        const select = document.getElementById(prefix + '-select');
        const search = document.getElementById(prefix + '-search');
        const selUrl = document.getElementById(prefix + '-selected-url');
        manual.style.display = 'none';
        select.style.display = 'none';
        if (search) search.style.display = 'none';
        hideFieldError(prefix + '-target-error');
        if (mode === 'manual') {
            manual.style.display = 'block';
            // В ручном режиме скрываем «Выбрано»
            if (selUrl) selUrl.classList.remove('visible');
        } else if (mode === 'search') {
            // Сброс значения при переключении на поиск
            const input = document.getElementById(prefix + '-target-url');
            if (input) input.value = '';
            if (selUrl) selUrl.classList.remove('visible');
            if (search) { search.style.display = 'block'; initLiveSearch(prefix); }
        } else {
            // Сброс значения при переключении на структуру
            const input = document.getElementById(prefix + '-target-url');
            if (input) input.value = '';
            if (selUrl) selUrl.classList.remove('visible');
            select.style.display = 'block';
            initCascade(prefix);
        }
    }

    // === Каскадные фильтруемые списки ===
    const API = '/bb/redirects_api.php';

    function initCascade(prefix) {
        const container = document.getElementById(prefix + '-cascade');
        if (container.dataset.inited) return;
        container.dataset.inited = '1';

        // Первый шаг — выбор типа
        const step = createStep('Тип страницы', [
            { name: 'Первый уровень (главная, контакты...)', value: 'main' },
            { name: 'Раздел', value: 'razdel' },
        ], (val) => onTypeSelected(val, container, prefix));
        container.appendChild(step);
    }

    function onTypeSelected(type, container, prefix) {
        // Очищаем всё после первого шага
        clearStepsAfter(container, 0);

        if (type === 'main') {
            // Загружаем main pages
            fetchItems('main_pages', {}, (items) => {
                const step = createSearchableStep('Страница', items, (item) => {
                    setTargetUrl(prefix, item.url);
                });
                container.appendChild(step);
            });
        } else if (type === 'razdel') {
            fetchItems('razdels', {}, (items) => {
                const step = createSearchableStep('Раздел', items, (item) => {
                    setTargetUrl(prefix, item.url);
                    clearStepsAfter(container, 1);
                    // Загружаем подразделы
                    fetchItems('subrazels', { razdel_id: item.id }, (subItems) => {
                        const rootItem = { name: '📁 Корневая страница раздела', url: item.url, isRoot: true };
                        subItems.unshift(rootItem);
                        const step2 = createSearchableStep('Подраздел', subItems, (sub) => {
                            setTargetUrl(prefix, sub.url);
                            if (sub.isRoot) { clearStepsAfter(container, 2); return; }
                            clearStepsAfter(container, 2);
                            // Загружаем категории
                            fetchItems('categories', { subrazdel_id: sub.id }, (catItems) => {
                                const rootSub = { name: '📁 Корневая страница подраздела', url: sub.url, isRoot: true };
                                catItems.unshift(rootSub);
                                const step3 = createSearchableStep('Категория', catItems, (cat) => {
                                    setTargetUrl(prefix, cat.url);
                                    if (cat.isRoot) { clearStepsAfter(container, 3); return; }
                                    clearStepsAfter(container, 3);
                                    // Загружаем модели
                                    fetchItems('models', { cat_id: cat.id }, (modelItems) => {
                                        if (modelItems.length === 0) return;
                                        const rootCat = { name: '📁 Корневая страница категории', url: cat.url, isRoot: true };
                                        modelItems.unshift(rootCat);
                                        const step4 = createSearchableStep('Модель', modelItems, (model) => {
                                            setTargetUrl(prefix, model.url);
                                        });
                                        container.appendChild(step4);
                                    });
                                });
                                container.appendChild(step3);
                            });
                        });
                        container.appendChild(step2);
                    });
                });
                container.appendChild(step);
            });
        }
    }

    function setTargetUrl(prefix, url) {
        const input = document.getElementById(prefix + '-target-url');
        if (input) input.value = url;
        // Показать подтверждение выбора
        const sel = document.getElementById(prefix + '-selected-url');
        if (sel) {
            const link = sel.querySelector('a');
            if (link) { link.textContent = url; link.href = url; }
            sel.classList.add('visible');
        }
        // Скрыть ошибку
        const err = document.getElementById(prefix + '-target-error');
        if (err) err.classList.remove('visible');
    }

    // === Fetch helper ===
    function fetchItems(action, params, callback) {
        const qs = new URLSearchParams({ action, ...params });
        fetch(API + '?' + qs).then(r => r.json()).then(callback).catch(e => console.error(e));
    }

    // === Очистка шагов после N-го ===
    function clearStepsAfter(container, keepCount) {
        const steps = container.querySelectorAll('.cascade-step');
        steps.forEach((s, i) => { if (i > keepCount) s.remove(); });
    }

    // === Простой select-шаг (для первого выбора типа) ===
    function createStep(label, options, onSelect) {
        const div = document.createElement('div');
        div.className = 'cascade-step';
        div.innerHTML = '<label>' + label + '</label>';
        const sel = document.createElement('select');
        sel.className = 'form-select form-select-sm';
        sel.innerHTML = '<option value="">— Выберите —</option>';
        options.forEach(o => {
            const opt = document.createElement('option');
            opt.value = o.value; opt.textContent = o.name;
            sel.appendChild(opt);
        });
        sel.addEventListener('change', () => onSelect(sel.value));
        div.appendChild(sel);
        return div;
    }

    // === Searchable dropdown шаг ===
    function createSearchableStep(label, items, onSelect) {
        const div = document.createElement('div');
        div.className = 'cascade-step';

        const lbl = document.createElement('label');
        lbl.textContent = label + ' (' + items.length + ')';
        div.appendChild(lbl);

        const wrap = document.createElement('div');
        wrap.className = 'sd-wrap';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'sd-input';
        input.placeholder = 'Начните вводить для фильтрации...';

        const list = document.createElement('div');
        list.className = 'sd-list';

        let highlightedIdx = -1;

        function renderItems(filter) {
            list.innerHTML = '';
            let filtered = items;
            if (filter) {
                const f = filter.toLowerCase();
                filtered = items.filter(it => it.name.toLowerCase().includes(f) || it.url.toLowerCase().includes(f));
            }
            highlightedIdx = -1;
            filtered.forEach((it, idx) => {
                const d = document.createElement('div');
                d.className = 'sd-item' + (it.isRoot ? ' sd-item-root' : '');
                d.innerHTML = '<span>' + escHtml(it.name) + '</span><span class="sd-url">' + escHtml(it.url) + '</span>';
                d.addEventListener('click', () => {
                    input.value = it.name;
                    list.classList.remove('open');
                    onSelect(it);
                });
                list.appendChild(d);
            });
            if (filtered.length > 0) list.classList.add('open');
            else list.classList.remove('open');
        }

        input.addEventListener('focus', () => renderItems(input.value));
        input.addEventListener('input', () => renderItems(input.value));

        input.addEventListener('keydown', (e) => {
            const items = list.querySelectorAll('.sd-item');
            if (e.key === 'ArrowDown') { e.preventDefault(); highlightedIdx = Math.min(highlightedIdx + 1, items.length - 1); updateHighlight(items); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlightedIdx = Math.max(highlightedIdx - 1, 0); updateHighlight(items); }
            else if (e.key === 'Enter') { e.preventDefault(); if (highlightedIdx >= 0 && items[highlightedIdx]) items[highlightedIdx].click(); }
            else if (e.key === 'Escape') { list.classList.remove('open'); }
        });

        function updateHighlight(items) {
            items.forEach((it, i) => it.classList.toggle('highlighted', i === highlightedIdx));
            if (items[highlightedIdx]) items[highlightedIdx].scrollIntoView({ block: 'nearest' });
        }

        // Закрытие при клике вне
        document.addEventListener('click', (e) => {
            if (!wrap.contains(e.target)) list.classList.remove('open');
        });

        wrap.appendChild(input);
        wrap.appendChild(list);
        div.appendChild(wrap);
        return div;
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function clearLiveSearch(prefix) {
        const input = document.getElementById(prefix + '-ls-input');
        const tree = document.getElementById(prefix + '-ls-tree');
        if (input) {
            input.value = '';
            input.focus();
            const btn = input.nextElementSibling;
            if (btn && btn.classList.contains('ls-clear')) btn.style.display = 'none';
        }
        if (tree) tree.innerHTML = '';
    }

    // === Живой поиск ===
    function initLiveSearch(prefix) {
        const input = document.getElementById(prefix + '-ls-input');
        const tree = document.getElementById(prefix + '-ls-tree');
        if (!input) return;

        // Ensure we don't double-bind, but allow checking initial state
        if (!input.dataset.inited) {
            input.dataset.inited = '1';

            let timer = null;
            input.addEventListener('input', () => {
                const btn = input.nextElementSibling;
                if (btn && btn.classList.contains('ls-clear')) {
                    btn.style.display = input.value ? 'flex' : 'none';
                }

                clearTimeout(timer);
                const q = input.value.trim();
                if (q.length < 2) { tree.innerHTML = ''; return; }
                timer = setTimeout(() => {
                    fetch(API + '?action=search&q=' + encodeURIComponent(q))
                        .then(r => r.json())
                        .then(data => renderTree(tree, data, q, prefix))
                        .catch(e => { tree.innerHTML = '<div style="padding:10px;color:#dc3545">Ошибка запроса</div>'; });
                }, 300);
            });
        }

        // Always check initial state of clear button when entering mode
        const btn = input.nextElementSibling;
        if (btn && btn.classList.contains('ls-clear')) {
            btn.style.display = input.value ? 'flex' : 'none';
        }

        input.focus();
    }

    function renderTree(container, nodes, query, prefix) {
        container.innerHTML = '';
        if (!nodes || nodes.length === 0) {
            container.innerHTML = '<div style="padding:12px;color:#999;text-align:center">Ничего не найдено</div>';
            return;
        }
        nodes.forEach(node => container.appendChild(buildNode(node, query, prefix)));
    }

    function buildNode(node, query, prefix) {
        const div = document.createElement('div');
        div.className = 'ls-node';

        const row = document.createElement('div');
        row.className = 'ls-row';

        // Иконка уровня
        const icons = { razdel: '📂', subrazdel: '📁', category: '🏷️', model: '📄' };
        const typeLabels = { razdel: 'Раздел', subrazdel: 'Подраздел', category: 'Категория', model: 'Модель' };
        const icon = document.createElement('span');
        icon.className = 'ls-icon';
        icon.textContent = icons[node.type] || '•';
        row.appendChild(icon);

        // Бейджик типа
        const typeBadge = document.createElement('span');
        typeBadge.className = 'ls-type ls-type-' + node.type;
        typeBadge.textContent = typeLabels[node.type] || node.type;
        row.appendChild(typeBadge);

        // Название с подсветкой
        const name = document.createElement('span');
        name.className = 'ls-name';
        name.innerHTML = highlightMatch(node.name, query);
        row.appendChild(name);

        // URL
        const url = document.createElement('span');
        url.className = 'ls-url';
        url.textContent = node.url;
        row.appendChild(url);

        // Клик — выбрать URL
        row.addEventListener('click', (e) => {
            e.stopPropagation();
            setTargetUrl(prefix, node.url);
            // Подсветить выбранный
            document.querySelectorAll('.ls-row.selected').forEach(r => r.classList.remove('selected'));
            row.classList.add('selected');
        });

        div.appendChild(row);

        // Дочерние элементы
        if (node.children && node.children.length > 0) {
            const childrenDiv = document.createElement('div');
            childrenDiv.className = 'ls-children';
            node.children.forEach(ch => childrenDiv.appendChild(buildNode(ch, query, prefix)));
            div.appendChild(childrenDiv);
        }

        return div;
    }

    function highlightMatch(text, query) {
        if (!query) return escHtml(text);
        const idx = text.toLowerCase().indexOf(query.toLowerCase());
        if (idx === -1) return escHtml(text);
        const before = text.substring(0, idx);
        const match = text.substring(idx, idx + query.length);
        const after = text.substring(idx + query.length);
        return escHtml(before) + '<mark>' + escHtml(match) + '</mark>' + escHtml(after);
    }
</script>

<?php echo \bb\Base::pageEndHtmlB5(); ?>