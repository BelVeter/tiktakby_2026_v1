<?php
/**
 * Переименование производителя (название, комментарий, is_active) из
 * попапа редактирования на bb/tovar_new_mod.php.
 *
 * preview=1 — только масштаб изменений, без записи (для подтверждения
 * сотрудником перед кнопкой «сохранить»).
 * Без preview — переименовывает. Запрет слияния (переименование в уже
 * существующее имя) — здесь, а не в Producer::rename(): класс остаётся
 * «глупым» персистером, как Category::save() не проверяет cat_url_key сам.
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Producer.php');

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload)
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$in_level = array(5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    respond(['error' => 'Нет доступа. Переименование доступно только уровням 5 и 7.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Только POST']);
}

$id       = (int) ($_POST['id'] ?? 0);
$newName  = trim($_POST['name'] ?? '');
$comment  = trim($_POST['comment'] ?? '');
$isActive = !empty($_POST['is_active']);
$preview  = !empty($_POST['preview']);

$producer = \bb\classes\Producer::getById($id);
if (!$producer) {
    respond(['error' => 'Производитель не найден.']);
}

if (mb_strlen($newName) < 2) {
    respond(['error' => 'Укажите название производителя.']);
}

if ($preview) {
    respond(['ok' => true, 'impact' => $producer->impactOfRename()]);
}

if ($newName !== $producer->getName()) {
    $existing = \bb\classes\Producer::getByName($newName);
    if ($existing && $existing->getId() !== $producer->getId()) {
        respond([
            'error' => 'Производитель «' . $newName . '» уже существует. '
                . 'Это слияние, а не переименование — оно делается миграцией через PR, не отсюда.',
        ]);
    }
}

$producer->setComment($comment);
$producer->setActive($isActive);

if ($newName !== $producer->getName()) {
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    if (!$producer->rename($newName, $userId)) {
        respond(['error' => 'Сбой при переименовании в базе данных.']);
    }
} elseif (!$producer->save()) {
    respond(['error' => 'Сбой при сохранении в базу данных.']);
}

respond(['ok' => true, 'producer' => ['id' => $producer->getName(), 'name' => $producer->getName()]]);
