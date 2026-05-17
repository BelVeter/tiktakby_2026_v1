<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php';

\bb\Base::loginCheck();

// ─── Файл хранилища ──────────────────────────────────────────────────────────
$storageFile = $_SERVER['DOCUMENT_ROOT'] . '/storage/app/a1_missed_calls.json';

// ─── Обработка действия "перезвонили" ────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'mark_processed') {
    $uuid = trim($_POST['uuid'] ?? '');
    if ($uuid && file_exists($storageFile)) {
        $calls = json_decode(file_get_contents($storageFile), true);
        if (is_array($calls) && isset($calls[$uuid])) {
            $calls[$uuid]['processed_at'] = date('d.m.Y H:i');
            $calls[$uuid]['processed_by'] = $_SESSION['user_fio'] ?? 'unknown';
            file_put_contents($storageFile, json_encode($calls, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }
    header('Location: /bb/a1_missed_calls.php');
    exit;
}

// ─── Фильтр ──────────────────────────────────────────────────────────────────
$filterNew   = isset($_GET['filter']) && $_GET['filter'] === 'new';
$filterDate  = $_GET['date'] ?? '';   // формат YYYY-MM-DD

// ─── Чтение данных ───────────────────────────────────────────────────────────
$calls = [];
$lastUpdated = null;

if (file_exists($storageFile)) {
    $lastUpdated = filemtime($storageFile);
    $raw = json_decode(file_get_contents($storageFile), true);
    if (is_array($raw)) {
        // Сортировка: новые сначала
        uasort($raw, function ($a, $b) {
            return ($b['callTimestamp'] ?? 0) - ($a['callTimestamp'] ?? 0);
        });

        foreach ($raw as $uuid => $call) {
            // Фильтр: только необработанные
            if ($filterNew && !empty($call['processed_at'])) {
                continue;
            }
            // Фильтр по дате
            if ($filterDate) {
                $callDate = date('Y-m-d', $call['callTimestamp'] ?? 0);
                if ($callDate !== $filterDate) {
                    continue;
                }
            }
            $calls[$uuid] = $call;
        }
    }
}

$totalNew = 0;
if (file_exists($storageFile)) {
    $allRaw = json_decode(file_get_contents($storageFile), true);
    if (is_array($allRaw)) {
        $totalNew = count(array_filter($allRaw, function ($c) {
            return empty($c['processed_at']);
        }));
    }
}

// Лейблы статусов
$statusLabels = [
    'NOT_ANSWERED_COMMON'            => ['label' => 'Нет ответа',           'class' => 'bg-warning text-dark'],
    'CANCELLED_BY_CALLER'            => ['label' => 'Сброшен звонящим',     'class' => 'bg-secondary text-white'],
    'DENIED_DUE_TO_NOT_WORK_TIME'    => ['label' => 'Вне рабочего времени', 'class' => 'bg-info text-dark'],
    'DENIED_DUE_TO_MAX_SESSION'      => ['label' => 'Перегрузка (сессии)',  'class' => 'bg-danger text-white'],
    'DENIED_DUE_TO_MAX_CHANNEL_LIMIT'=> ['label' => 'Перегрузка (каналы)', 'class' => 'bg-danger text-white'],
];

echo \bb\Base::pageStartB5('A1 ВАТС — Пропущенные звонки');
\bb\Base::loginCheck();
?>
<style>
  .a1-card { border-radius: 8px; margin-bottom: 10px; padding: 12px 16px; border: 1px solid #dee2e6; background: #fff; }
  .a1-card.unread { border-left: 4px solid #dc3545; background: #fff8f8; }
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
</style>

<div class="container-fluid mt-3">

  <!-- Заголовок -->
  <div class="d-flex align-items-center mb-3 gap-3">
    <h4 class="mb-0">📞 Пропущенные звонки A1 ВАТС</h4>
    <?php if ($totalNew > 0): ?>
      <span class="badge bg-danger stat-badge"><?= $totalNew ?> необработанных</span>
    <?php else: ?>
      <span class="badge bg-success stat-badge">Все обработаны</span>
    <?php endif; ?>
    <?php if ($lastUpdated): ?>
      <small class="text-muted ms-auto">Обновлено: <?= date('d.m.Y H:i', $lastUpdated) ?></small>
    <?php endif; ?>
  </div>

  <!-- Навигация -->
  <div class="mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="/bb/index.php">← На главную</a>
    <a class="btn btn-sm btn-outline-primary ms-1" href="/bb/zv_ch.php">Заявки/звонки (старые)</a>
  </div>

  <!-- Фильтр -->
  <div class="filter-bar d-flex flex-wrap gap-3 align-items-center">
    <a href="/bb/a1_missed_calls.php" class="btn btn-sm <?= (!$filterNew && !$filterDate) ? 'btn-primary' : 'btn-outline-primary' ?>">
      Все
    </a>
    <a href="/bb/a1_missed_calls.php?filter=new" class="btn btn-sm <?= $filterNew ? 'btn-danger' : 'btn-outline-danger' ?>">
      Только необработанные
    </a>
    <form method="get" action="/bb/a1_missed_calls.php" class="d-flex gap-2 align-items-center mb-0">
      <label class="mb-0 small">Дата:</label>
      <input type="date" name="date" class="form-control form-control-sm" style="width:150px"
             value="<?= htmlspecialchars($filterDate) ?>"
             max="<?= date('Y-m-d') ?>">
      <button type="submit" class="btn btn-sm btn-outline-secondary">Показать</button>
      <?php if ($filterDate): ?>
        <a href="/bb/a1_missed_calls.php" class="btn btn-sm btn-link text-muted">✕ Сбросить</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Список звонков -->
  <?php if (empty($calls)): ?>
    <div class="alert alert-secondary">
      <?php if (!file_exists($storageFile)): ?>
        <strong>Данных пока нет.</strong>
        Файл хранилища отсутствует — запустите команду <code>php artisan a1:fetch-missed-calls</code>
        или дождитесь срабатывания cron.
      <?php else: ?>
        Пропущенных звонков по выбранному фильтру не найдено.
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($calls as $uuid => $call):
      $isProcessed   = !empty($call['processed_at']);
      $cardClass     = $isProcessed ? 'processed' : 'unread';
      $caller        = $call['callerNumber']    ?? '—';
      $callee        = $call['calleeNumber']    ?? '—';
      $callTs        = $call['callTimestamp']   ?? 0;
      $status        = $call['callStatus']      ?? '';
      $duration      = $call['callDuration']    ?? 0;
      $crmClient     = $call['crm_client']      ?? null;
      $activeDeals   = $call['crm_active_deals'] ?? [];
      $lastReturn    = $call['crm_last_return']  ?? null;
      $totalDeals    = $call['crm_total_deals']  ?? 0;
      $statusInfo    = $statusLabels[$status] ?? ['label' => $status, 'class' => 'bg-secondary text-white'];
    ?>
    <div class="a1-card <?= $cardClass ?>">
      <div class="d-flex flex-wrap align-items-start gap-3">

        <!-- Иконка + время -->
        <div class="text-center" style="min-width:70px">
          <div style="font-size:1.6rem"><?= $isProcessed ? '✅' : '📵' ?></div>
          <div class="a1-time"><?= $callTs ? date('d.m', $callTs) : '' ?></div>
          <div class="a1-time"><?= $callTs ? date('H:i', $callTs) : '' ?></div>
        </div>

        <!-- Основная информация -->
        <div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <span class="a1-phone">
              📲 <?= htmlspecialchars($caller) ?>
            </span>
            <span class="badge <?= $statusInfo['class'] ?> badge-status">
              <?= htmlspecialchars($statusInfo['label']) ?>
            </span>
            <?php if ($duration > 0): ?>
              <span class="text-muted small"><?= $duration ?> с</span>
            <?php endif; ?>
          </div>

          <div class="text-muted small">
            Назначение: <?= htmlspecialchars($callee) ?>
          </div>

          <!-- CRM-блок -->
          <div class="crm-block mt-2">
            <?php if ($crmClient): ?>
              <strong>👤 <?= htmlspecialchars($crmClient['fio']) ?></strong>
              <?php if ($totalDeals > 0): ?>
                <span class="badge bg-light text-dark ms-1"><?= $totalDeals ?> сделок</span>
              <?php endif; ?>

              <?php if (!empty($activeDeals)): ?>
                <div class="mt-1">
                  <span class="text-success fw-bold">Активная аренда:</span>
                  <?php foreach ($activeDeals as $deal): ?>
                    <div class="crm-deal">
                      📦 <strong><?= htmlspecialchars($deal['model']) ?></strong>
                      <span class="text-muted">(<?= htmlspecialchars($deal['category']) ?>)</span>
                      — с <?= htmlspecialchars($deal['rented_from']) ?>
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

        <!-- Кнопка / статус обработки -->
        <div class="text-end" style="min-width:150px">
          <?php if ($isProcessed): ?>
            <div class="text-success small fw-bold">✓ Обработан</div>
            <div class="processed-by"><?= htmlspecialchars($call['processed_at']) ?></div>
            <div class="processed-by"><?= htmlspecialchars($call['processed_by'] ?? '') ?></div>
          <?php else: ?>
            <form method="post" action="/bb/a1_missed_calls.php" onsubmit="return confirm('Отметить как обработанный?')">
              <input type="hidden" name="action" value="mark_processed">
              <input type="hidden" name="uuid"   value="<?= htmlspecialchars($uuid) ?>">
              <?php if ($filterNew): ?>
                <input type="hidden" name="redirect_new" value="1">
              <?php endif; ?>
              <button type="submit" class="btn btn-sm btn-success">✓ Перезвонили</button>
            </form>
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
