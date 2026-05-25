<?php

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

\bb\Base::loginCheck();

if (!\bb\models\User::getCurrentUser()->isManagement()) {
    http_response_code(403);
    die('<p>Доступ запрещён.</p>');
}

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
    WHERE call_date >= '{$safeDate} 00:00:00' AND call_date <= '{$safeDate} 23:59:59'
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
        ca.transcript,
        ca.discussed_items,
        ca.missed_item,
        ca.client_sentiment,
        ca.consultant_sentiment,
        ca.ai_business_note,
        ca.recording_uuid AS analysis_recording_uuid,
        rec.uuid AS local_file_uuid
    FROM a1_cdr cdr
    LEFT JOIN a1_call_analysis ca ON ca.recording_uuid = cdr.recording_uuid
    LEFT JOIN a1_call_recordings rec ON rec.uuid = cdr.recording_uuid
    WHERE cdr.call_date >= '{$safeDate} 00:00:00' AND cdr.call_date <= '{$safeDate} 23:59:59'
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
function formatPhone(?string $phone): string {
    if (!$phone) return '';
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 12 && strpos($digits, '375') === 0) {
        return '+375 (' . substr($digits, 3, 2) . ') ' . substr($digits, 5, 3) . '-' . substr($digits, 8, 2) . '-' . substr($digits, 10, 2);
    }
    return $phone;
}

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

// ─── CSV-экспорт ──────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="calls_' . $date . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['Время', 'Тип', 'Номер', 'Длительность', 'Краткое описание', 'Результат ИИ'], ';');
    foreach ($calls as $call) {
        fputcsv($out, [
            date('H:i', strtotime($call['call_date'])),
            $call['call_type'],
            $call['call_type'] !== 'outgoing' ? $call['caller_number'] : $call['callee_number'],
            formatDuration((int) $call['call_duration']),
            $call['ai_summary'] ?? '',
            $call['ai_result']  ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}
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
        .stat-tile { flex: 1; min-width: 120px; background: #fff; border-radius: 8px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; cursor: pointer; transition: all 0.2s ease-in-out; border: 2px solid transparent; }
        .stat-tile:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,.12); }
        .stat-tile.active-filter { border-color: #007bff; background-color: #f8fbff; }
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
        .ai-summary-text {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
            cursor: pointer;
            border-bottom: 1px dashed #adb5bd;
            display: inline-box; /* To make dashed border wrap tight */
        }
        @supports (-webkit-line-clamp: 1) {
            .ai-summary-text {
                display: -webkit-box;
            }
        }
        .ai-summary-text.expanded {
            -webkit-line-clamp: unset;
            display: block;
            border-bottom: none;
        }
        .ai-business-note {
            font-size: 12px;
            color: #6c757d;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            width: 220px;
            cursor: pointer;
            border-bottom: 1px dashed #adb5bd;
        }
        .ai-business-note.expanded {
            -webkit-line-clamp: unset;
            display: block;
            border-bottom: none;
            width: auto;
            min-width: 220px;
        }
        .calls-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        @media (max-width: 768px) {
            .phone-number {
                white-space: normal;
                word-break: break-all;
            }
        }
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
            <a href="?date=<?= $date ?>&type=<?= $typeFilter ?>&export=csv"
               class="btn btn-sm btn-outline-secondary">↓ CSV</a>
        </div>
    </div>

    <!-- Статистика -->
    <div class="stat-tiles">
        <div class="stat-tile stat-total <?= $typeFilter === 'all' ? 'active-filter' : '' ?>" onclick="window.location='?date=<?= $date ?>&type=all'">
            <div class="num"><?= $total ?></div><div class="lbl">Всего</div>
        </div>
        <div class="stat-tile stat-in <?= $typeFilter === 'incoming' ? 'active-filter' : '' ?>" onclick="window.location='?date=<?= $date ?>&type=<?= $typeFilter === 'incoming' ? 'all' : 'incoming' ?>'">
            <div class="num"><?= $incoming ?></div><div class="lbl">Входящие</div>
        </div>
        <div class="stat-tile stat-out <?= $typeFilter === 'outgoing' ? 'active-filter' : '' ?>" onclick="window.location='?date=<?= $date ?>&type=<?= $typeFilter === 'outgoing' ? 'all' : 'outgoing' ?>'">
            <div class="num"><?= $outgoing ?></div><div class="lbl">Исходящие</div>
        </div>
        <div class="stat-tile stat-missed <?= $typeFilter === 'missed' ? 'active-filter' : '' ?>" onclick="window.location='?date=<?= $date ?>&type=<?= $typeFilter === 'missed' ? 'all' : 'missed' ?>'">
            <div class="num"><?= $missed ?></div><div class="lbl">Пропущенные</div>
        </div>
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
      <div class="calls-table-wrapper">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Время</th>
                    <th>Тип</th>
                    <th>Номер</th>
                    <th>Длит.</th>
                    <th>Краткое описание</th>
                    <th>Результат ИИ</th>
                    <th>Детали</th>
                    <th>Транскр.</th>
                    <th>Запись</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($calls)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Звонков за этот день нет</td></tr>
            <?php endif; ?>
            <?php foreach ($calls as $call): ?>
            <tr>
                <td><?= date('H:i', strtotime($call['call_date'])) ?></td>
                <td><?= callTypeIcon($call['call_type']) ?></td>
                <td>
                    <div class="phone-number"><?= htmlspecialchars(formatPhone($call['call_type'] !== 'outgoing' ? $call['caller_number'] : $call['callee_number'])) ?></div>
                    <?php if ($call['client_name']): ?>
                    <small class="text-muted"><?= htmlspecialchars($call['client_name']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= formatDuration((int) $call['call_duration']) ?></td>
                <td>
                    <?php if ($call['ai_summary'] && $call['ai_status'] === 'done'): ?>
                        <div class="ai-summary-text" onclick="this.classList.toggle('expanded')" title="Нажмите, чтобы развернуть/свернуть">
                            <?= nl2br(htmlspecialchars($call['ai_summary'])) ?>
                        </div>
                    <?php elseif ($call['recording_uuid'] && $call['ai_status'] === 'processing'): ?>
                        <span class="text-muted small">обрабатывается…</span>
                    <?php elseif ($call['recording_uuid'] && $call['ai_status'] === 'transcribed'): ?>
                        <span class="text-muted small">транскрипт готов</span>
                    <?php elseif ($call['recording_uuid']): ?>
                        <span class="text-muted small">ожидает обработки</span>
                    <?php else: ?>
                        <span class="text-muted small">нет записи</span>
                    <?php endif; ?>
                </td>
                <td><?= aiResultBadge($call['ai_result'], $call['ai_status']) ?></td>
                <td>
                    <?php if (!empty($call['ai_business_note'])): ?>
                        <div class="ai-business-note" onclick="this.classList.toggle('expanded')" title="Нажмите, чтобы развернуть/свернуть">
                            <?= nl2br(htmlspecialchars($call['ai_business_note'])) ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($call['transcript'] && in_array($call['ai_status'], ['transcribed', 'done', 'error'], true)): ?>
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick="showAnalysis(<?= htmlspecialchars(json_encode([
                                'transcript'           => $call['transcript'],
                                'ai_summary'           => $call['ai_summary'],
                                'ai_result'            => $call['ai_result'],
                                'discussed_items'      => json_decode($call['discussed_items'] ?? '[]', true),
                                'missed_item'          => $call['missed_item'],
                                'client_sentiment'     => $call['client_sentiment'],
                                'consultant_sentiment' => $call['consultant_sentiment'],
                            ]), ENT_QUOTES) ?>)">T</button>
                    <?php elseif ($call['ai_status'] === 'error'): ?>
                    <span class="text-muted">—</span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                    <?php if ($call['recording_uuid'] && in_array($call['ai_status'], ['transcribed', 'done', 'error'], true)): ?>
                    <button class="btn btn-sm btn-outline-warning ml-1" title="Сбросить и перезапустить"
                            onclick="resetAnalysis('<?= htmlspecialchars($call['recording_uuid']) ?>')">↺</button>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($call['local_file_uuid']): ?>
                    <div class="d-flex align-items-center">
                        <audio class="audio-player" controls preload="none"
                               src="/bb-internal/audio/<?= htmlspecialchars($call['local_file_uuid']) ?>"></audio>
                        <a href="/bb-internal/audio/<?= htmlspecialchars($call['local_file_uuid']) ?>?download=1"
                           class="btn btn-sm btn-outline-secondary ml-2"
                           title="Скачать аудиофайл" download>
                           <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                               <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                               <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                           </svg>
                        </a>
                    </div>
                    <?php elseif ($call['recording_uuid']): ?>
                    <span class="text-muted small" title="Файл ещё не загружен с A1">⏳</span>
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
                <div id="analysisDetails" class="mb-3"></div>
                <hr id="analysisDivider" style="display:none;">
                <pre id="transcriptContent" class="bg-light p-3 rounded" style="white-space:pre-wrap;font-size:0.85rem;max-height:60vh;overflow-y:auto;"></pre>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script>
var sentimentMap = {positive: '😊 Позитивный', neutral: '😐 Нейтральный', negative: '😟 Негативный'};
var sentimentColor = {positive: 'success', neutral: 'secondary', negative: 'danger'};

function showAnalysis(data) {
    var html = '';

    if (data.discussed_items && data.discussed_items.length > 0) {
        html += '<div class="mb-2"><strong>Обсуждаемые товары:</strong> ';
        data.discussed_items.forEach(function(item) {
            html += '<span class="badge badge-light border mr-1">' + escHtml(item) + '</span>';
        });
        html += '</div>';
    }

    if (data.missed_item) {
        html += '<div class="mb-2"><strong>Отсутствующий товар:</strong> <span class="badge badge-warning">' + escHtml(data.missed_item) + '</span></div>';
    }

    if (data.client_sentiment) {
        html += '<div class="mb-1"><strong>Клиент:</strong> <span class="badge badge-' + (sentimentColor[data.client_sentiment] || 'secondary') + '">' + escHtml(sentimentMap[data.client_sentiment] || data.client_sentiment) + '</span></div>';
    }
    if (data.consultant_sentiment) {
        html += '<div class="mb-2"><strong>Консультант:</strong> <span class="badge badge-' + (sentimentColor[data.consultant_sentiment] || 'secondary') + '">' + escHtml(sentimentMap[data.consultant_sentiment] || data.consultant_sentiment) + '</span></div>';
    }

    document.getElementById('analysisDetails').innerHTML = html;
    document.getElementById('analysisDivider').style.display = html ? '' : 'none';
    document.getElementById('transcriptContent').textContent = data.transcript || '';
    $('#transcriptModal').modal('show');
}

function resetAnalysis(uuid) {
    if (!confirm('Сбросить анализ и поставить звонок в очередь на повторную обработку?')) return;
    fetch('/bb/a1_calls_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=reset_analysis&uuid=' + encodeURIComponent(uuid)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    })
    .catch(function() { alert('Ошибка соединения'); });
}

function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

function toggleSummary() {
    var el = document.getElementById('summary-body');
    if (el) el.style.display = el.style.display === 'none' ? '' : 'none';
}

document.querySelectorAll('audio.audio-player').forEach(function(player) {
    player.addEventListener('play', function() {
        document.querySelectorAll('audio.audio-player').forEach(function(other) {
            if (other !== player) other.pause();
        });
    });
});
</script>
</body>
</html>
