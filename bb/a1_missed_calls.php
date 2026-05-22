<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

\bb\Base::loginCheck();

$mysqli      = \bb\Db::getInstance()->getConnection();
$currentUser = \bb\models\User::getCurrentUser();
$userFio     = $_SESSION['user_fio'] ?? 'unknown';
$isDima      = $currentUser && $currentUser->isDima();
$userId      = $currentUser ? (int)$currentUser->getId() : 0;

// ─── POST: обработка действий ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if ($id > 0 && in_array($action, ['called_back', 'false_call'], true)) {
        $stmt = $mysqli->prepare(
            "UPDATE a1_missed_calls
             SET action_type = ?, action_user_id = ?, action_user_name = ?, action_at = NOW()
             WHERE id = ? AND action_type = 'none'"
        );
        $stmt->bind_param('ssis', $action, $userId, $userFio, $id);
        $stmt->execute();
        $stmt->close();
    }

    // Редирект с сохранением фильтра даты
    $date = trim($_POST['redirect_date'] ?? '');
    $qs   = $date ? '?date=' . urlencode($date) : '';
    header('Location: /bb/a1_missed_calls.php' . $qs);
    exit;
}

// ─── GET параметры ───────────────────────────────────────────────────────────
$filterDate = trim($_GET['date'] ?? date('Y-m-d'));  // по умолчанию сегодня
$filterNew  = isset($_GET['filter']) && $_GET['filter'] === 'new';
$showAll    = isset($_GET['all']) && $_GET['all'] === '1';

if ($showAll) {
    $filterDate = '';
}

// ─── Чтение звонков ──────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
$types  = '';

if ($filterDate) {
    $where[]  = "DATE(FROM_UNIXTIME(call_timestamp)) = ?";
    $params[] = $filterDate;
    $types   .= 's';
}
if ($filterNew) {
    $where[] = "action_type = 'none'";
}

$whereStr = implode(' AND ', $where);
$sql = "SELECT * FROM a1_missed_calls WHERE $whereStr ORDER BY call_timestamp DESC LIMIT 300";

$calls = [];
if ($types) {
    // get_result() requires mysqlnd which is unavailable on this host — use real_escape_string instead
    $safeDate = $mysqli->real_escape_string($filterDate);
    $filledSql = str_replace('?', "'{$safeDate}'", $sql);
    $result = $mysqli->query($filledSql);
} else {
    $result = $mysqli->query($sql);
}

while ($row = $result->fetch_assoc()) {
    $row['crm_active_deals'] = json_decode($row['crm_active_deals'] ?? '[]', true) ?: [];
    $calls[] = $row;
}

// ─── Счётчик необработанных (всего) ─────────────────────────────────────────
$rowNew = $mysqli->query("SELECT COUNT(*) as n FROM a1_missed_calls WHERE action_type = 'none'");
$totalNew = (int)$rowNew->fetch_assoc()['n'];

// ─── Статистика для Димы ──────────────────────────────────────────────────────
$dimaStats = null;
if ($isDima) {
    $statsDate = trim($_GET['stats_date'] ?? date('Y-m-d'));

    $stRow = $mysqli->query(
        "SELECT
           COUNT(*)                                                      AS total,
           SUM(action_type = 'called_back')                             AS called_back,
           SUM(action_type = 'false_call')                              AS false_call,
           SUM(action_type = 'none')                                    AS pending,
           SUM(action_type = 'client_called_back')                      AS client_called_back
         FROM a1_missed_calls
         WHERE DATE(FROM_UNIXTIME(call_timestamp)) = '" . $mysqli->real_escape_string($statsDate) . "'"
    )->fetch_assoc();

    $apiRow = $mysqli->query(
        "SELECT
           COUNT(*)              AS total_fetches,
           SUM(status='success') AS ok,
           SUM(status='error')   AS errors
         FROM a1_api_fetch_log
         WHERE DATE(fetched_at) = '" . $mysqli->real_escape_string($statsDate) . "'"
    )->fetch_assoc();

    $lastError = null;
    $errRes = $mysqli->query(
        "SELECT error_message, fetched_at FROM a1_api_fetch_log
         WHERE DATE(fetched_at) = '" . $mysqli->real_escape_string($statsDate) . "'
           AND status = 'error'
         ORDER BY fetched_at DESC LIMIT 1"
    );
    if ($errRow = $errRes->fetch_assoc()) {
        $lastError = $errRow;
    }

    $dimaStats = [
        'date'          => $statsDate,
        'total'         => (int)$stRow['total'],
        'called_back'   => (int)$stRow['called_back'],
        'false_call'    => (int)$stRow['false_call'],
        'pending'            => (int)$stRow['pending'],
        'client_called_back' => (int)$stRow['client_called_back'],
        'api_total'     => (int)$apiRow['total_fetches'],
        'api_ok'        => (int)$apiRow['ok'],
        'api_errors'    => (int)$apiRow['errors'],
        'last_error'    => $lastError,
    ];
}

// ─── Лейблы статусов ─────────────────────────────────────────────────────────
$statusLabels = [
    'NOT_ANSWERED_COMMON'             => ['label' => 'Нет ответа',           'class' => 'bg-warning text-dark'],
    'CANCELLED_BY_CALLER'             => ['label' => 'Сброшен звонящим',     'class' => 'bg-secondary text-white'],
    'DENIED_DUE_TO_NOT_WORK_TIME'     => ['label' => 'Вне рабочего времени', 'class' => 'bg-info text-dark'],
    'DENIED_DUE_TO_MAX_SESSION'       => ['label' => 'Перегрузка (сессии)',  'class' => 'bg-danger text-white'],
    'DENIED_DUE_TO_MAX_CHANNEL_LIMIT' => ['label' => 'Перегрузка (каналы)', 'class' => 'bg-danger text-white'],
];

echo \bb\Base::pageStartB5('A1 ВАТС — Пропущенные звонки');
?>
<style>
  .a1-card { border-radius: 8px; margin-bottom: 10px; padding: 12px 16px; border: 1px solid #dee2e6; background: #fff; }
  .a1-card.unread { border-left: 4px solid #dc3545; background: #fff8f8; }
  .a1-card.false-call { border-left: 4px solid #6c757d; background: #f8f9fa; opacity: .75; }
  .a1-card.auto-callback { border-left: 4px solid #0dcaf0; background: #f0fbff; opacity: .9; }
  .a1-card.processed { border-left: 4px solid #198754; opacity: .85; }
  .a1-phone { font-size: 1.15rem; font-weight: 700; letter-spacing: .03em; }
  .a1-time  { color: #6c757d; font-size: .85rem; }
  .crm-block { background: #f0f9ff; border-radius: 6px; padding: 8px 12px; margin-top: 8px; font-size: .88rem; }
  .crm-deal { padding: 3px 0; border-bottom: 1px dotted #cce5f5; }
  .crm-deal:last-child { border-bottom: none; }
  .no-client { color: #adb5bd; font-style: italic; }
  .badge-status { font-size: .75rem; }
  .filter-bar { background: #f8f9fa; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; }
  .stat-badge { font-size: 1rem; }
  .processed-by { font-size: .78rem; color: #6c757d; }
  .dima-stats { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 14px 18px; margin-bottom: 18px; }
  .dima-stats .stat-item { display: inline-block; margin-right: 18px; }
  .api-ok    { color: #198754; font-weight: 600; }
  .api-error { color: #dc3545; font-weight: 600; }
</style>

<div class="container-fluid mt-3">

  <!-- Заголовок -->
  <div class="d-flex align-items-center mb-3 gap-3 flex-wrap">
    <h4 class="mb-0">📞 Пропущенные звонки A1 ВАТС</h4>
    <?php if ($totalNew > 0): ?>
      <span class="badge bg-danger stat-badge"><?= $totalNew ?> необработанных</span>
    <?php else: ?>
      <span class="badge bg-success stat-badge">Все обработаны</span>
    <?php endif; ?>
  </div>

  <!-- Навигация -->
  <div class="mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="/bb/index.php">← На главную</a>
    <a class="btn btn-sm btn-outline-primary ms-1" href="/bb/zv_ch.php">Заявки/звонки (старые)</a>
  </div>

  <!-- Блок статистики для Димы (user_id=3) -->
  <?php if ($isDima && $dimaStats): ?>
  <div class="dima-stats">
    <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
      <strong>📊 Статистика за</strong>
      <form method="get" action="/bb/a1_missed_calls.php" class="d-flex gap-2 align-items-center mb-0">
        <input type="date" name="stats_date" class="form-control form-control-sm" style="width:150px"
               value="<?= htmlspecialchars($dimaStats['date']) ?>"
               max="<?= date('Y-m-d') ?>">
        <?php if ($filterDate && $filterDate !== date('Y-m-d')): ?>
          <input type="hidden" name="date" value="<?= htmlspecialchars($filterDate) ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Показать</button>
      </form>
    </div>
    <div class="d-flex flex-wrap gap-4">
      <div>
        <div class="text-muted small mb-1">Звонки</div>
        <span class="stat-item"><strong><?= $dimaStats['total'] ?></strong> всего</span>
        <span class="stat-item text-success"><strong><?= $dimaStats['called_back'] ?></strong> перезвонили</span>
        <span class="stat-item" style="color:#0dcaf0"><strong><?= $dimaStats['client_called_back'] ?></strong> сам перезвонил</span>
        <span class="stat-item text-secondary"><strong><?= $dimaStats['false_call'] ?></strong> ложных</span>
        <span class="stat-item text-danger"><strong><?= $dimaStats['pending'] ?></strong> не обработано</span>
      </div>
      <div>
        <div class="text-muted small mb-1">API A1</div>
        <?php if ($dimaStats['api_total'] === 0): ?>
          <span class="text-muted">Нет данных о запросах</span>
        <?php else: ?>
          <span class="stat-item"><?= $dimaStats['api_total'] ?> запросов</span>
          <span class="stat-item api-ok">✓ <?= $dimaStats['api_ok'] ?> успешных</span>
          <?php if ($dimaStats['api_errors'] > 0): ?>
            <span class="stat-item api-error">✗ <?= $dimaStats['api_errors'] ?> ошибок</span>
            <?php if ($dimaStats['last_error']): ?>
              <div class="mt-1 small text-danger">
                Последняя ошибка (<?= htmlspecialchars(substr($dimaStats['last_error']['fetched_at'], 11, 5)) ?>):
                <?= htmlspecialchars(mb_substr($dimaStats['last_error']['error_message'] ?? '', 0, 120)) ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <span class="api-ok">— всё OK</span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Фильтр -->
  <div class="filter-bar d-flex flex-wrap gap-3 align-items-center">
    <a href="/bb/a1_missed_calls.php" class="btn btn-sm <?= (!$showAll && $filterDate === date('Y-m-d') && !$filterNew) ? 'btn-primary' : 'btn-outline-primary' ?>">
      Сегодня
    </a>
    <a href="/bb/a1_missed_calls.php?filter=new" class="btn btn-sm <?= $filterNew ? 'btn-danger' : 'btn-outline-danger' ?>">
      Только необработанные
    </a>
    <a href="/bb/a1_missed_calls.php?all=1" class="btn btn-sm <?= $showAll ? 'btn-secondary' : 'btn-outline-secondary' ?>">
      Все записи
    </a>
    <form method="get" action="/bb/a1_missed_calls.php" class="d-flex gap-2 align-items-center mb-0">
      <label class="mb-0 small">Дата:</label>
      <input type="date" name="date" class="form-control form-control-sm" style="width:150px"
             value="<?= htmlspecialchars($filterDate) ?>"
             max="<?= date('Y-m-d') ?>">
      <button type="submit" class="btn btn-sm btn-outline-secondary">Показать</button>
    </form>
  </div>

  <!-- Список звонков -->
  <?php if (empty($calls)): ?>
    <div class="alert alert-secondary">
      Пропущенных звонков по выбранному фильтру не найдено.
    </div>
  <?php else: ?>
    <?php foreach ($calls as $call):
      $actionType       = $call['action_type'] ?? 'none';
      $isCalledBack     = ($actionType === 'called_back');
      $isFalse          = ($actionType === 'false_call');
      $isPending        = ($actionType === 'none');
      $isAutoCallback   = ($actionType === 'client_called_back');

      if ($isCalledBack)      $cardClass = 'processed';
      elseif ($isFalse)       $cardClass = 'false-call';
      elseif ($isAutoCallback) $cardClass = 'auto-callback';
      else                    $cardClass = 'unread';

      $caller      = $call['caller_number']  ?? '—';
      $callee      = $call['callee_number']  ?? '—';
      $callTs      = $call['call_timestamp'] ?? 0;
      $status      = $call['call_status']    ?? '';
      $duration    = $call['call_duration']  ?? 0;
      $crmFio      = $call['crm_client_fio'] ?? null;
      $activeDeals = $call['crm_active_deals'] ?? [];
      $lastReturn  = $call['crm_last_return']  ?? null;
      $totalDeals  = $call['crm_total_deals']  ?? 0;
      $statusInfo  = $statusLabels[$status] ?? ['label' => $status, 'class' => 'bg-secondary text-white'];
    ?>
    <div class="a1-card <?= $cardClass ?>">
      <div class="d-flex flex-wrap align-items-start gap-3">

        <!-- Иконка + время -->
        <div class="text-center" style="min-width:70px">
          <?php if ($isCalledBack): ?>
            <div style="font-size:1.6rem">✅</div>
          <?php elseif ($isFalse): ?>
            <div style="font-size:1.6rem">🚫</div>
          <?php elseif ($isAutoCallback): ?>
            <div style="font-size:1.6rem">↩️</div>
          <?php else: ?>
            <div style="font-size:1.6rem">📵</div>
          <?php endif; ?>
          <div class="a1-time"><?= $callTs ? date('d.m', $callTs) : '' ?></div>
          <div class="a1-time"><?= $callTs ? date('H:i', $callTs) : '' ?></div>
        </div>

        <!-- Основная информация -->
        <div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <span class="a1-phone">
              📲 <?= htmlspecialchars(\bb\Base::formatPhone($caller)) ?>
            </span>
            <span class="badge <?= $statusInfo['class'] ?> badge-status">
              <?= htmlspecialchars($statusInfo['label']) ?>
            </span>
            <?php if ($duration > 0): ?>
              <span class="text-muted small"><?= $duration ?> с</span>
            <?php endif; ?>
          </div>

          <div class="text-muted small">
            Назначение: <?= htmlspecialchars(\bb\Base::formatPhone($callee)) ?>
          </div>

          <!-- CRM-блок -->
          <div class="crm-block mt-2">
            <?php if ($crmFio): ?>
              <strong>👤 <?= htmlspecialchars($crmFio) ?></strong>
              <?php if ($totalDeals > 0): ?>
                <span class="badge bg-light text-dark ms-1"><?= $totalDeals ?> сделок</span>
              <?php endif; ?>

              <?php if (!empty($activeDeals)): ?>
                <div class="mt-1">
                  <span class="text-success fw-bold">Активная аренда:</span>
                  <?php foreach ($activeDeals as $deal): ?>
                    <div class="crm-deal">
                      📦 <strong><?= htmlspecialchars($deal['model'] ?? '') ?></strong>
                      <span class="text-muted">(<?= htmlspecialchars($deal['category'] ?? '') ?>)</span>
                      — с <?= htmlspecialchars($deal['rented_from'] ?? '') ?>
                      до <?= htmlspecialchars($deal['return_due'] ?? '?') ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php elseif ($lastReturn): ?>
                <div class="mt-1 text-muted">
                  🔄 Нет активных аренд. Последний возврат: <strong><?= htmlspecialchars($lastReturn) ?></strong>
                </div>
              <?php else: ?>
                <div class="mt-1 text-muted">Нет активных аренд и истории.</div>
              <?php endif; ?>

            <?php else: ?>
              <span class="no-client">🔍 Клиент в базе не найден</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Кнопки / статус -->
        <div class="text-end" style="min-width:160px">
          <?php if ($isPending): ?>
            <form method="post" action="/bb/a1_missed_calls.php">
              <input type="hidden" name="id" value="<?= (int)$call['id'] ?>">
              <input type="hidden" name="redirect_date" value="<?= htmlspecialchars($filterDate) ?>">
              <div class="d-flex flex-column gap-1">
                <button type="submit" name="action" value="called_back"
                        class="btn btn-sm btn-success"
                        onclick="return confirm('Отметить как перезвонили?')">
                  ✓ Перезвонили
                </button>
                <button type="submit" name="action" value="false_call"
                        class="btn btn-sm btn-outline-secondary"
                        onclick="return confirm('Отметить как ложный вызов?')">
                  🚫 Ложный вызов
                </button>
              </div>
            </form>
          <?php elseif ($isAutoCallback): ?>
            <?php
              $cbMin = (int)($call['callback_minutes'] ?? 0);
              $cbLabel = $cbMin >= 60
                ? floor($cbMin / 60) . ' ч ' . ($cbMin % 60) . ' мин'
                : ($cbMin > 0 ? $cbMin . ' мин' : '< 1 мин');
            ?>
            <div style="color:#0dcaf0" class="small fw-bold">↩ Сам перезвонил</div>
            <div class="processed-by">через <?= $cbLabel ?></div>
          <?php elseif ($isCalledBack): ?>
            <div class="text-success small fw-bold">✓ Перезвонили</div>
            <div class="processed-by"><?= htmlspecialchars($call['action_at'] ? substr($call['action_at'], 0, 16) : '') ?></div>
            <div class="processed-by"><?= htmlspecialchars($call['action_user_name'] ?? '') ?></div>
          <?php else: ?>
            <div class="text-secondary small fw-bold">🚫 Ложный вызов</div>
            <div class="processed-by"><?= htmlspecialchars($call['action_at'] ? substr($call['action_at'], 0, 16) : '') ?></div>
            <div class="processed-by"><?= htmlspecialchars($call['action_user_name'] ?? '') ?></div>
          <?php endif; ?>
        </div>

      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"
        crossorigin="anonymous"></script>
</body>
</html>
