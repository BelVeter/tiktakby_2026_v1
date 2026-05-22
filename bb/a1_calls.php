<?php

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

\bb\Base::loginCheck();

$mysqli = \bb\Db::getInstance()->getConnection();

// ─── Параметры ────────────────────────────────────────────────────────────
$date     = trim($_GET['date'] ?? date('Y-m-d'));
$typeFilter = trim($_GET['type'] ?? 'all');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
if (!in_array($typeFilter, ['all', 'incoming', 'outgoing', 'missed'], true)) {
    $typeFilter = 'all';
}

$safeDate = $mysqli->real_escape_string($date);

// ─── Статистика за день ───────────────────────────────────────────────────
$statsRow = $mysqli->query("
    SELECT
        COUNT(*) AS total,
        SUM(call_type = 'incoming') AS incoming,
        SUM(call_type = 'outgoing') AS outgoing,
        SUM(call_type = 'missed')   AS missed
    FROM a1_cdr
    WHERE DATE(call_date) = '{$safeDate}'
")->fetch_assoc();

$total    = (int) ($statsRow['total']    ?? 0);
$incoming = (int) ($statsRow['incoming'] ?? 0);
$outgoing = (int) ($statsRow['outgoing'] ?? 0);
$missed   = (int) ($statsRow['missed']   ?? 0);

// ─── Дневная ИИ-сводка ────────────────────────────────────────────────────
$summaryRow = $mysqli->query("
    SELECT * FROM a1_daily_summaries WHERE summary_date = '{$safeDate}'
")->fetch_assoc();

// ─── Список звонков ───────────────────────────────────────────────────────
$typeWhere = $typeFilter !== 'all' ? " AND cdr.call_type = '{$mysqli->real_escape_string($typeFilter)}'" : '';

$calls = [];
$result = $mysqli->query("
    SELECT
        cdr.uuid,
        cdr.call_date,
        cdr.call_type,
        cdr.caller_number,
        cdr.callee_number,
        cdr.call_duration,
        cdr.recording_uuid,
        ca.ai_summary,
        ca.ai_result,
        ca.ai_status,
        ca.transcript
    FROM a1_cdr cdr
    LEFT JOIN a1_call_analysis ca ON ca.recording_uuid = cdr.recording_uuid
    WHERE DATE(cdr.call_date) = '{$safeDate}'
    {$typeWhere}
    ORDER BY cdr.call_date DESC
    LIMIT 500
");

while ($row = $result->fetch_assoc()) {
    $lookupNum = $row['call_type'] !== 'outgoing' ? $row['caller_number'] : $row['callee_number'];
    $digits = preg_replace('/\D/', '', $lookupNum);
    $last9  = substr($digits, -9);
    $clientName = '';
    if (strlen($last9) >= 7) {
        $safeNum = $mysqli->real_escape_string($last9);
        $cl = $mysqli->query("SELECT fio FROM clients WHERE REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(','') LIKE '%{$safeNum}' LIMIT 1");
        if ($cl && $clRow = $cl->fetch_assoc()) {
            $clientName = $clRow['fio'];
        }
    }
    $row['client_name'] = $clientName;
    $calls[] = $row;
}

// ─── Вспомогательные функции ──────────────────────────────────────────────
function formatDuration(int $secs): string {
    if ($secs === 0) return '—';
    return sprintf('%d:%02d', intdiv($secs, 60), $secs % 60);
}

function callTypeIcon(string $type): string {
    $icons = [
        'incoming' => '<span title="Входящий" style="color:#28a745;font-weight:bold;">↓</span>',
        'outgoing' => '<span title="Исходящий" style="color:#007bff;font-weight:bold;">↑</span>',
        'missed'   => '<span title="Пропущенный" style="color:#dc3545;font-weight:bold;">✗</span>',
    ];
    return $icons[$type] ?? '?';
}

function aiResultBadge(?string $result, ?string $status): string {
    if (!$result || $status !== 'done') {
        if ($status === 'processing') return '<span class="badge badge-secondary">обрабатывается</span>';
        if ($status === 'error')      return '<span class="badge badge-danger">ошибка</span>';
        return '<span class="text-muted small">—</span>';
    }
    $labels = [
        'new_client' => ['Новый клиент', 'success'],
        'booking'    => ['Бронирование', 'primary'],
        'complaint'  => ['Жалоба',       'danger'],
        'info'       => ['Инфо-запрос',  'info'],
        'other'      => ['Другое',       'secondary'],
    ];
    $entry = $labels[$result] ?? [$result, 'secondary'];
    $label = $entry[0];
    $color = $entry[1];
    return "<span class=\"badge badge-{$color}\">{$label}</span>";
}

$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$today    = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анализ звонков — <?= htmlspecialchars($date) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/bb/bb_nav.css?v=5">
    <style>
        body { padding-top: 60px; background: #f8f9fa; }
        .calls-header { background: #fff; padding: 16px 20px; border-bottom: 1px solid #dee2e6; margin-bottom: 20px; }
        .date-nav { display: flex; align-items: center; gap: 10px; }
        .date-nav a { color: #495057; text-decoration: none; font-size: 1.2rem; padding: 2px 8px; border: 1px solid #dee2e6; border-radius: 4px; }
        .date-nav a:hover { background: #e9ecef; }
        .stat-tiles { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .stat-tile { flex: 1; min-width: 120px; background: #fff; border-radius: 8px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; }
        .stat-tile .num { font-size: 2rem; font-weight: 700; }
        .stat-tile .lbl { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; letter-spacing: .5px; }
        .stat-total   .num { color: #343a40; }
        .stat-in      .num { color: #28a745; }
        .stat-out     .num { color: #007bff; }
        .stat-missed  .num { color: #dc3545; }
        .summary-block { background: #fff; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .summary-block .themes { margin-top: 8px; }
        .summary-block .themes .badge { margin-right: 4px; font-size: 0.85rem; }
        .filter-tabs .nav-link { cursor: pointer; }
        .calls-table { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .calls-table th { font-size: 0.8rem; text-transform: uppercase; letter-spacing: .5px; color: #6c757d; background: #f8f9fa; border-top: none; }
        .calls-table td { vertical-align: middle; font-size: 0.9rem; }
        .transcript-modal pre { white-space: pre-wrap; font-size: 0.85rem; max-height: 60vh; overflow-y: auto; }
        .audio-player { width: 200px; height: 32px; }
    </style>
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bb/bb_nav.php'; ?>

<div class="container-fluid" style="max-width:1400px;">
    <!-- Шапка с навигацией по дням -->
    <div class="calls-header rounded mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="date-nav">
                <a href="?date=<?= $prevDate ?>&type=<?= $typeFilter ?>">←</a>
                <input type="date" id="date-picker" value="<?= $date ?>" max="<?= $today ?>"
                       class="form-control form-control-sm" style="width:160px;"
                       onchange="window.location='?date='+this.value+'&type=<?= $typeFilter ?>'">
                <?php if ($date < $today): ?>
                <a href="?date=<?= $nextDate ?>&type=<?= $typeFilter ?>">→</a>
                <?php else: ?>
                <a style="opacity:.3;cursor:default;">→</a>
                <?php endif; ?>
            </div>

            <!-- Фильтр по типу -->
            <ul class="nav nav-pills filter-tabs">
                <?php foreach (['all' => 'Все', 'incoming' => 'Входящие', 'outgoing' => 'Исходящие', 'missed' => 'Пропущенные'] as $val => $lbl): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $typeFilter === $val ? 'active' : '' ?>"
                       href="?date=<?= $date ?>&type=<?= $val ?>"><?= $lbl ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Статистика -->
    <div class="stat-tiles">
        <div class="stat-tile stat-total"><div class="num"><?= $total ?></div><div class="lbl">Всего</div></div>
        <div class="stat-tile stat-in"><div class="num"><?= $incoming ?></div><div class="lbl">Входящие</div></div>
        <div class="stat-tile stat-out"><div class="num"><?= $outgoing ?></div><div class="lbl">Исходящие</div></div>
        <div class="stat-tile stat-missed"><div class="num"><?= $missed ?></div><div class="lbl">Пропущенные</div></div>
    </div>

    <!-- ИИ-сводка -->
    <?php if ($summaryRow): ?>
    <div class="summary-block">
        <div class="d-flex align-items-center justify-content-between">
            <strong>ИИ-сводка за день</strong>
            <button class="btn btn-sm btn-link" onclick="toggleSummary()">скрыть/показать</button>
        </div>
        <div id="summary-body">
            <p class="mb-1 mt-2"><?= nl2br(htmlspecialchars($summaryRow['summary_text'] ?? '')) ?></p>
            <?php if (!empty($summaryRow['key_themes'])): ?>
            <div class="themes">
                <?php $themes = json_decode($summaryRow['key_themes'], true) ?: []; ?>
                <?php foreach ($themes as $theme): ?>
                <span class="badge badge-light border"><?= htmlspecialchars($theme) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-light border mb-3" style="font-size:.9rem;">
        ИИ-сводка за <?= htmlspecialchars($date) ?> ещё не готова.
    </div>
    <?php endif; ?>

    <!-- Таблица звонков -->
    <div class="calls-table mb-4">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Время</th>
                    <th>Тип</th>
                    <th>Номер</th>
                    <th>Длит.</th>
                    <th>Краткое описание</th>
                    <th>Результат ИИ</th>
                    <th>Транскр.</th>
                    <th>Запись</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($calls)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Звонков за этот день нет</td></tr>
            <?php endif; ?>
            <?php foreach ($calls as $call): ?>
            <tr>
                <td><?= date('H:i', strtotime($call['call_date'])) ?></td>
                <td><?= callTypeIcon($call['call_type']) ?></td>
                <td>
                    <div><?= htmlspecialchars($call['call_type'] !== 'outgoing' ? $call['caller_number'] : $call['callee_number']) ?></div>
                    <?php if ($call['client_name']): ?>
                    <small class="text-muted"><?= htmlspecialchars($call['client_name']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= formatDuration((int) $call['call_duration']) ?></td>
                <td>
                    <?php if ($call['ai_summary'] && $call['ai_status'] === 'done'): ?>
                        <span title="<?= htmlspecialchars($call['ai_summary']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($call['ai_summary'], 0, 60, '…')) ?>
                        </span>
                    <?php elseif ($call['recording_uuid'] && $call['ai_status'] === 'processing'): ?>
                        <span class="text-muted small">обрабатывается…</span>
                    <?php elseif ($call['recording_uuid']): ?>
                        <span class="text-muted small">ожидает обработки</span>
                    <?php else: ?>
                        <span class="text-muted small">нет записи</span>
                    <?php endif; ?>
                </td>
                <td><?= aiResultBadge($call['ai_result'], $call['ai_status']) ?></td>
                <td>
                    <?php if ($call['transcript'] && $call['ai_status'] === 'done'): ?>
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick="showTranscript(<?= htmlspecialchars(json_encode($call['transcript'])) ?>)">T</button>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($call['recording_uuid']): ?>
                    <audio class="audio-player" controls preload="none"
                           src="/bb-internal/audio/<?= htmlspecialchars($call['recording_uuid']) ?>"></audio>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модалка транскрипции -->
<div class="modal fade" id="transcriptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Транскрипция разговора</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <pre id="transcriptContent" class="bg-light p-3 rounded"></pre>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script>
function showTranscript(text) {
    document.getElementById('transcriptContent').textContent = text;
    $('#transcriptModal').modal('show');
}
function toggleSummary() {
    var el = document.getElementById('summary-body');
    if (el) el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
</body>
</html>
