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
    die('Доступ запрещен. Страница доступна только для администратора (Dima).');
}

$rocketSms = new \bb\classes\RocketSMS();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$responseResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'send') {
        $phone = trim($_POST['phone'] ?? '');
        $text = trim($_POST['text'] ?? '');
        $sender = trim($_POST['sender'] ?? '');
        
        if ($phone && $text) {
            $responseResult = $rocketSms->send($phone, $text, $sender ?: null);
        } else {
            $responseResult = ['error' => 'Заполните номер телефона и текст'];
        }
    }
} elseif ($action === 'balance') {
    $responseResult = $rocketSms->balance();
} elseif ($action === 'senders') {
    $responseResult = $rocketSms->senders();
}

echo \bb\Base::pageStartB5('Тест RocketSMS');
?>
<div class="container mt-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <h2>Тестирование RocketSMS API</h2>
        <a href="/bb/index.php" class="btn btn-sm btn-outline-secondary">← На главную</a>
    </div>

    <div class="row">
        <!-- Форма отправки -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    Отправить одиночное SMS (SEND)
                </div>
                <div class="card-body">
                    <form method="post" action="/bb/rocketsms_test.php">
                        <input type="hidden" name="action" value="send">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Номер телефона</label>
                            <input type="text" name="phone" class="form-control" placeholder="37529xxxxxxx (без плюса)" required>
                            <div class="form-text">Международный формат без плюса.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Текст сообщения</label>
                            <textarea name="text" class="form-control" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Отправитель (Sender ID)</label>
                            <input type="text" name="sender" class="form-control" placeholder="Например: TIKTAK">
                            <div class="form-text">Оставьте пустым для использования имени по умолчанию.</div>
                        </div>

                        <button type="submit" class="btn btn-success">Отправить SMS</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Дополнительные действия -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    Дополнительные команды
                </div>
                <div class="card-body d-flex gap-2">
                    <a href="/bb/rocketsms_test.php?action=balance" class="btn btn-outline-info">Проверить баланс</a>
                    <a href="/bb/rocketsms_test.php?action=senders" class="btn btn-outline-info">Получить Sender ID</a>
                </div>
            </div>

            <!-- Блок результата -->
            <?php if ($responseResult !== null): ?>
            <div class="card shadow-sm border-<?= isset($responseResult['error']) ? 'danger' : 'success' ?>">
                <div class="card-header bg-<?= isset($responseResult['error']) ? 'danger' : 'success' ?> text-white">
                    Результат запроса
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded" style="font-size: 14px;"><?= htmlspecialchars(print_r($responseResult, true)) ?></pre>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"
        crossorigin="anonymous"></script>
</body>
</html>
