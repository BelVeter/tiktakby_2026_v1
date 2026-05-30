<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/classes/RocketSMS.php';

\bb\Base::loginCheck();

$currentUser = \bb\models\User::getCurrentUser();
if (!$currentUser || !$currentUser->isDima()) {
    http_response_code(403);
    die('Доступ запрещён. Страница доступна только для администратора (Dima).');
}

$rocketSms = new \bb\classes\RocketSMS();

// ─── Обработка POST (отправка SMS) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $phone  = trim($_POST['phone'] ?? '');
    $text   = trim($_POST['text']  ?? '');
    $sender = trim($_POST['sender'] ?? '') ?: null;

    if ($phone && $text) {
        $result = $rocketSms->send($phone, $text, $sender);
    } else {
        $result = ['error' => 'Заполните номер телефона и текст сообщения'];
    }

    // Redirect-after-post: сохраняем результат в сессию, редиректим на GET
    // Это предотвращает повторную отправку SMS при обновлении страницы (F5)
    $_SESSION['rocketsms_result'] = $result;
    header('Location: /bb/rocketsms_test.php');
    exit;
}

// ─── GET: дополнительные команды ─────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$responseResult = null;

if ($action === 'balance') {
    $responseResult = $rocketSms->balance();
} elseif ($action === 'senders') {
    $responseResult = $rocketSms->senders();
} elseif ($action === 'templates') {
    $responseResult = $rocketSms->templates();
}

// Подхватываем результат из сессии (после POST-редиректа)
if ($responseResult === null && isset($_SESSION['rocketsms_result'])) {
    $responseResult = $_SESSION['rocketsms_result'];
    unset($_SESSION['rocketsms_result']);
}

// ─── Отображение ─────────────────────────────────────────────────────────────
echo \bb\Base::pageStartB5('Тест RocketSMS');
?>
<div class="container mt-4" style="max-width: 900px;">

    <div class="d-flex align-items-center mb-4 gap-3 flex-wrap">
        <h2 class="mb-0">📱 Тестирование RocketSMS API</h2>
        <a href="/bb/index.php" class="btn btn-sm btn-outline-secondary ms-auto">← На главную</a>
    </div>

    <div class="row g-4">

        <!-- Левая колонка: форма + результат -->
        <div class="col-md-7">

            <!-- Форма отправки -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white fw-semibold">
                    Отправить SMS
                </div>
                <div class="card-body">
                    <form method="post" action="/bb/rocketsms_test.php">
                        <input type="hidden" name="action" value="send">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Номер телефона</label>
                            <input type="text" name="phone" class="form-control"
                                   placeholder="375296890043 (без плюса, международный формат)" required
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            <div class="form-text">Пример: 375291234567 (BY), 79101234567 (RU)</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Текст сообщения</label>
                            <textarea name="text" class="form-control" rows="3" required
                                      maxlength="160"><?= htmlspecialchars($_POST['text'] ?? '') ?></textarea>
                            <div class="form-text">Максимум 160 символов (1 SMS). Кириллица = ~70 символов на часть.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Sender ID <span class="text-muted fw-normal">(необязательно)</span></label>
                            <input type="text" name="sender" class="form-control"
                                   placeholder="Например: TIKTAK.BY">
                            <div class="form-text">Оставьте пустым — будет использовано имя по умолчанию.</div>
                        </div>

                        <button type="submit" class="btn btn-success">📤 Отправить SMS</button>
                    </form>
                </div>
            </div>

            <!-- Результат запроса -->
            <?php if ($responseResult !== null): ?>
            <div class="card shadow-sm border-<?= isset($responseResult['error']) ? 'danger' : 'success' ?>">
                <div class="card-header bg-<?= isset($responseResult['error']) ? 'danger' : 'success' ?> text-white fw-semibold">
                    <?= isset($responseResult['error']) ? '❌ Ошибка' : '✅ Результат' ?>
                </div>
                <div class="card-body p-0">
                    <pre class="m-0 p-3 bg-light rounded-bottom" style="font-size: 13px; white-space: pre-wrap;"><?= htmlspecialchars(json_encode($responseResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Правая колонка: дополнительные команды + справка -->
        <div class="col-md-5">

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-secondary text-white fw-semibold">
                    Информация об аккаунте
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="/bb/rocketsms_test.php?action=balance"   class="btn btn-outline-info w-100">💰 Проверить баланс</a>
                    <a href="/bb/rocketsms_test.php?action=senders"   class="btn btn-outline-info w-100">✉️ Список Sender ID</a>
                    <a href="/bb/rocketsms_test.php?action=templates" class="btn btn-outline-info w-100">📄 Список шаблонов</a>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-semibold">📖 Справка по статусам</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <tbody>
                            <tr><td><code>SENT</code></td><td>Отправлено в сеть</td></tr>
                            <tr><td><code>QUEUED</code></td><td>В очереди</td></tr>
                            <tr><td><code>DELIVERED</code></td><td>Доставлено</td></tr>
                            <tr><td><code>FAILED</code></td><td>Ошибка доставки</td></tr>
                            <tr><td><code>NO_MONEY</code></td><td>Недостаточно средств</td></tr>
                            <tr><td><code>INVALID_PHONE</code></td><td>Неверный номер</td></tr>
                            <tr><td><code>NO_MESSAGE</code></td><td>Текст не задан</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"
        crossorigin="anonymous"></script>
</body>
</html>
