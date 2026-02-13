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
            $comment = trim($mysqli->real_escape_string($_POST['comment'] ?? ''));
            if ($source === '' || $target === '') {
                $message = '<div class="alert alert-danger">Заполните оба поля URL.</div>';
            } else {
                if ($source[0] !== '/') $source = '/' . $source;
                $sql = "INSERT INTO redirects (source_url, target_url, status_code, comment)
                        VALUES ('$source', '$target', $code, '$comment')";
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
            $comment = trim($mysqli->real_escape_string($_POST['comment'] ?? ''));
            if ($source[0] !== '/') $source = '/' . $source;
            $message = $mysqli->query("UPDATE redirects SET source_url='$source', target_url='$target', status_code=$code, comment='$comment' WHERE id=$id")
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

// --- Получение данных ---
$result = $mysqli->query("SELECT * FROM redirects ORDER BY id DESC");
$redirects = [];
if ($result) { while ($row = $result->fetch_assoc()) $redirects[] = $row; }
?>

<link rel="stylesheet" href="/bb/assets/styles/cur_style.css?v=1">
<style>
    .rc { max-width: 1600px; margin: 20px auto; padding: 0 15px; }
    .table td, .table th { vertical-align: middle; font-size: 14px; }
    .badge-active { background-color: #28a745; } .badge-inactive { background-color: #dc3545; }
    .btn-actions { display: flex; gap: 4px; flex-wrap: nowrap; }
    .source-url { font-family: monospace; color: #0d6efd; font-size: 13px; }
    .target-url { font-family: monospace; color: #198754; font-size: 13px; }
    .status-code { font-weight: bold; }
    .status-301 { color: #6f42c1; } .status-302 { color: #fd7e14; }

    /* Сворачиваемая форма */
    .add-toggle { cursor: pointer; user-select: none; display: inline-flex; align-items: center; gap: 8px;
        padding: 8px 16px; background: #e9ecef; border-radius: 6px; font-weight: 600; color: #495057;
        border: 1px solid #ced4da; transition: all .2s; margin-bottom: 15px; }
    .add-toggle:hover { background: #dee2e6; }
    .add-toggle .arrow { transition: transform .2s; display: inline-block; }
    .add-toggle.open .arrow { transform: rotate(90deg); }
    .add-form-wrap { display: none; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
    .add-form-wrap.open { display: block; }

    /* Searchable dropdown */
    .sd-wrap { position: relative; margin-bottom: 4px; }
    .sd-input { width: 100%; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 13px; }
    .sd-list { position: absolute; z-index: 100; top: 100%; left: 0; right: 0; max-height: 250px; overflow-y: auto;
        background: #fff; border: 1px solid #ced4da; border-top: 0; border-radius: 0 0 4px 4px; display: none; box-shadow: 0 4px 12px rgba(0,0,0,.15); }
    .sd-list.open { display: block; }
    .sd-item { padding: 6px 10px; cursor: pointer; font-size: 13px; display: flex; justify-content: space-between; border-bottom: 1px solid #f0f0f0; }
    .sd-item:hover, .sd-item.highlighted { background: #e7f1ff; }
    .sd-item .sd-url { color: #999; font-family: monospace; font-size: 11px; }
    .sd-item-root { font-weight: 600; background: #f8f9fa; border-bottom: 2px solid #dee2e6; }

    /* Каскадные шаги */
    .cascade-step { margin-top: 6px; }
    .cascade-step label { font-size: 11px; color: #888; margin-bottom: 2px; display: block; }
    .target-manual { display: block; } .target-select { display: none; }
    .target-mode-btns { display: flex; gap: 6px; margin-bottom: 8px; }
    .target-mode-btns .btn { font-size: 12px; padding: 2px 10px; }

    /* Инлайн-редактирование */
    .edit-row { display: none; background: #fff9e6 !important; }
    .edit-row.active { display: table-row; }
    .edit-row td { padding: 12px !important; }
    .ef { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
    .ef .fg { display: flex; flex-direction: column; }
    .ef .fg label { font-size: 11px; color: #666; margin-bottom: 2px; }
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="/bb/">Главная</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navTop"><span class="navbar-toggler-icon"></span></button>
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
                </div>
                <div class="col-md-5" id="add-target-col">
                    <label class="form-label fw-bold">Куда (целевой URL)</label>
                    <div class="target-mode-btns">
                        <button type="button" class="btn btn-sm btn-outline-secondary active" onclick="setTargetMode('manual','add',this)">✍️ Вручную</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setTargetMode('select','add',this)">📋 Из структуры сайта</button>
                    </div>
                    <div class="target-manual" id="add-manual">
                        <input type="text" class="form-control" name="target_url" id="add-target-url" placeholder="/new-page или https://...">
                    </div>
                    <div class="target-select" id="add-select">
                        <div id="add-cascade"></div>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Код</label>
                    <select class="form-select" name="status_code">
                        <option value="301" selected>301 — Постоянный</option>
                        <option value="302">302 — Временный</option>
                        <option value="307">307 — Временный (сохр.)</option>
                        <option value="308">308 — Постоянный (сохр.)</option>
                    </select>
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
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th><th>Откуда</th><th>Куда</th><th>Код</th><th>Статус</th>
                    <th>Переходы</th><th>Посл.</th><th>Комм.</th><th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($redirects)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Пока нет перенаправлений.</td></tr>
            <?php else: ?>
                <?php foreach ($redirects as $r): ?>
                <tr class="<?= $r['is_active'] ? '' : 'table-secondary' ?>">
                    <td><?= $r['id'] ?></td>
                    <td class="source-url"><?= htmlspecialchars($r['source_url']) ?></td>
                    <td class="target-url"><?= htmlspecialchars($r['target_url']) ?></td>
                    <td><span class="status-code status-<?= $r['status_code'] ?>"><?= $r['status_code'] ?></span></td>
                    <td><span class="badge <?= $r['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $r['is_active'] ? 'Вкл' : 'Выкл' ?></span></td>
                    <td><span class="badge bg-<?= $r['hit_count'] > 0 ? 'primary' : 'secondary' ?>"><?= intval($r['hit_count']) ?></span></td>
                    <td><small><?= $r['last_hit_at'] ? date('d.m.y H:i', strtotime($r['last_hit_at'])) : '—' ?></small></td>
                    <td><small><?= htmlspecialchars($r['comment'] ?: '—') ?></small></td>
                    <td>
                        <div class="btn-actions">
                            <button type="button" class="btn btn-sm btn-outline-primary" title="Редактировать" onclick="toggleEdit(<?= $r['id'] ?>)">✏️</button>
                            <form method="post" style="margin:0"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $r['is_active'] ? 'warning' : 'success' ?>" title="<?= $r['is_active'] ? 'Выкл' : 'Вкл' ?>"><?= $r['is_active'] ? '⏸️' : '▶️' ?></button></form>
                            <?php if ($r['hit_count'] > 0): ?>
                            <form method="post" style="margin:0" onsubmit="return confirm('Сбросить?')"><input type="hidden" name="action" value="reset_hits"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-info" title="Сбросить">🔄</button></form>
                            <?php endif; ?>
                            <form method="post" style="margin:0" onsubmit="return confirm('Удалить?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить">🗑️</button></form>
                        </div>
                    </td>
                </tr>
                <!-- Строка редактирования -->
                <tr class="edit-row" id="edit-<?= $r['id'] ?>">
                    <td colspan="9">
                        <form method="post" class="ef">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <div class="fg"><label>Откуда</label>
                                <input type="text" class="form-control form-control-sm" name="source_url" value="<?= htmlspecialchars($r['source_url']) ?>" style="width:200px" required></div>
                            <div class="fg" style="min-width:320px">
                                <label>Куда</label>
                                <div class="target-mode-btns">
                                    <button type="button" class="btn btn-sm btn-outline-secondary active" onclick="setTargetMode('manual','e<?= $r['id'] ?>',this)">✍️ Вручную</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setTargetMode('select','e<?= $r['id'] ?>',this)">📋 Из структуры</button>
                                </div>
                                <div class="target-manual" id="e<?= $r['id'] ?>-manual">
                                    <input type="text" class="form-control form-control-sm" name="target_url" id="e<?= $r['id'] ?>-target-url" value="<?= htmlspecialchars($r['target_url']) ?>" style="width:300px"></div>
                                <div class="target-select" id="e<?= $r['id'] ?>-select"><div id="e<?= $r['id'] ?>-cascade"></div></div>
                            </div>
                            <div class="fg"><label>Код</label>
                                <select class="form-select form-select-sm" name="status_code" style="width:80px">
                                    <option value="301" <?= $r['status_code']==301?'selected':'' ?>>301</option>
                                    <option value="302" <?= $r['status_code']==302?'selected':'' ?>>302</option>
                                    <option value="307" <?= $r['status_code']==307?'selected':'' ?>>307</option>
                                    <option value="308" <?= $r['status_code']==308?'selected':'' ?>>308</option>
                                </select></div>
                            <div class="fg"><label>Комм.</label>
                                <input type="text" class="form-control form-control-sm" name="comment" value="<?= htmlspecialchars($r['comment'] ?? '') ?>" style="width:130px"></div>
                            <div class="fg"><label>&nbsp;</label>
                                <div style="display:flex;gap:4px">
                                    <button type="submit" class="btn btn-sm btn-success">💾</button>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleEdit(<?= $r['id'] ?>)">✕</button>
                                </div></div>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <small class="text-muted"><strong>Подсказка:</strong> 301 — постоянный (SEO), 302 — временный. Исходный URL начинается с <code>/</code>.</small>
</div>

<script>
// === Сворачиваемая форма ===
function toggleAddForm() {
    const btn = document.querySelector('.add-toggle');
    const form = document.getElementById('addFormWrap');
    btn.classList.toggle('open');
    form.classList.toggle('open');
}

// === Инлайн-редактирование ===
function toggleEdit(id) {
    document.querySelectorAll('.edit-row.active').forEach(r => { if (r.id !== 'edit-'+id) r.classList.remove('active'); });
    document.getElementById('edit-'+id).classList.toggle('active');
}

// === Переключение ручной / выбор ===
function setTargetMode(mode, prefix, btn) {
    btn.parentElement.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const manual = document.getElementById(prefix+'-manual');
    const select = document.getElementById(prefix+'-select');
    if (mode === 'manual') {
        manual.style.display = 'block'; select.style.display = 'none';
    } else {
        manual.style.display = 'none'; select.style.display = 'block';
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
</script>

<?php echo \bb\Base::pageEndHtmlB5(); ?>