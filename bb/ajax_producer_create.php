<?php
/**
 * Создание производителя из модалки на bb/tovar_new_mod.php.
 * Калька bb/ajax_category_create.php.
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

$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    respond(['error' => 'Нет доступа']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Только POST']);
}

$name    = trim($_POST['name'] ?? '');
$comment = trim($_POST['comment'] ?? '');
$confirm = !empty($_POST['confirm']);

if (mb_strlen($name) < 2) {
    respond(['error' => 'Укажите название производителя.']);
}

$dup = \bb\classes\Producer::findDuplicates($name);

if ($dup['exact']) {
    respond([
        'error'    => 'Такой производитель уже есть: «' . $dup['exact']->getName() . '». Выберите его в списке.',
        'existing' => ['id' => $dup['exact']->getName(), 'name' => $dup['exact']->getName()],
    ]);
}

if (!$confirm && !empty($dup['similar'])) {
    respond([
        'needs_confirm' => true,
        'similar'       => array_map(function ($m) {
            return ['id' => $m['producer']->getName(), 'name' => $m['producer']->getName(), 'score' => $m['score']];
        }, $dup['similar']),
    ]);
}

$producer = new \bb\classes\Producer();
$producer->setName($name);
$producer->setComment($comment);

if (!$producer->save()) {
    respond(['error' => 'Сбой при сохранении в базу данных.']);
}

respond([
    'ok'       => true,
    'producer' => ['id' => $producer->getName(), 'name' => $producer->getName()],
]);
