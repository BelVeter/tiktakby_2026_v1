<?php
/**
 * ОДНОРАЗОВЫЙ перенос тарифов между моделями каталога — удалить после прогона.
 *
 * Задача: у «Ванночка Baby Patent Forever Warm» нет тарифов, нужно поставить
 * такие же, как у «Aqua scale». Пишем строго через `bb\classes\Tariff`, иначе
 * изменение не попадёт в `rent_tarif_history` (CLAUDE.md, правило 7).
 *
 * Идемпотентен: `hardSave()` находит существующий тариф по model_id+step+kol_vo
 * и обновляет его, а не плодит дубли.
 *
 * Без `&apply=1` — только показывает план (ничего не пишет).
 * Ключ доступа — тот же, что у Deploy.php.
 */

$secret_key = 'Deploy-Mb8941';
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die('Access Denied');
}

session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Tariff.php');

header('Content-Type: text/plain; charset=utf-8');

$SRC_SLUG = 'aquascale';
$DST_SLUG = 'vannochka-baby-patent-forever-warm';

$apply  = isset($_GET['apply']) && $_GET['apply'] === '1';
$mysqli = \bb\Db::getInstance()->getConnection();

/** page_addr не уникален — требуем ровно одну модель, иначе выходим. */
function resolve_model_id($mysqli, $slug)
{
    $query = "SELECT DISTINCT model_id FROM rent_model_web
              WHERE page_addr = '" . $mysqli->real_escape_string($slug) . "'";
    $result = $mysqli->query($query);
    if (!$result) {
        die("Сбой при доступе к базе данных: $query (" . $mysqli->errno . ') ' . $mysqli->error . "\n");
    }
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row['model_id'];
    }
    if (count($ids) !== 1) {
        die("Слаг '$slug' разрешается в " . count($ids) . " моделей (" . implode(', ', $ids) . ") — нужна ровно одна.\n");
    }
    return $ids[0];
}

function dump_tarifs($mysqli, $model_id, $label)
{
    $query = "SELECT * FROM rent_tarif_act WHERE model_id = " . (int) $model_id . " ORDER BY sort_num, kol_vo";
    $result = $mysqli->query($query);
    if (!$result) {
        die("Сбой при доступе к базе данных: $query (" . $mysqli->errno . ') ' . $mysqli->error . "\n");
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    echo "--- $label (model_id=$model_id): " . count($rows) . " тарифов\n";
    foreach ($rows as $r) {
        printf(
            "  tarif_id=%-6s step=%-6s kol_vo=%-3s kol_vo_min=%-3s rent_amount=%-8s rent_per_step=%-8s sort_num=%-4s start=%s\n",
            $r['tarif_id'], $r['step'], $r['kol_vo'], $r['kol_vo_min'],
            $r['rent_amount'], $r['rent_per_step'], $r['sort_num'],
            date('Y-m-d', (int) $r['start_date'])
        );
    }
    echo "\n";
    return $rows;
}

$src_id = resolve_model_id($mysqli, $SRC_SLUG);
$dst_id = resolve_model_id($mysqli, $DST_SLUG);

echo ($apply ? "РЕЖИМ: ЗАПИСЬ\n\n" : "РЕЖИМ: только просмотр (добавьте &apply=1 для записи)\n\n");

$src_rows = dump_tarifs($mysqli, $src_id, "ИСТОЧНИК $SRC_SLUG");
$dst_rows = dump_tarifs($mysqli, $dst_id, "ПРИЁМНИК $DST_SLUG");

if (!$src_rows) {
    die("У источника нет тарифов — копировать нечего.\n");
}

/** Тарифы приёмника, которых нет в источнике: их надо снять, чтобы наборы совпали. */
$src_keys = [];
foreach ($src_rows as $r) {
    $src_keys[$r['step'] . '/' . (int) $r['kol_vo']] = true;
}
$to_delete = [];
foreach ($dst_rows as $r) {
    if (!isset($src_keys[$r['step'] . '/' . (int) $r['kol_vo']])) {
        $to_delete[] = $r;
    }
}

echo "ПЛАН:\n";
foreach ($src_rows as $r) {
    echo "  создать/обновить: step={$r['step']} kol_vo={$r['kol_vo']} rent_amount={$r['rent_amount']} rent_per_step={$r['rent_per_step']}\n";
}
foreach ($to_delete as $r) {
    echo "  удалить лишний: tarif_id={$r['tarif_id']} step={$r['step']} kol_vo={$r['kol_vo']}\n";
}
echo "\n";

if (!$apply) {
    die("Ничего не записано.\n");
}

// start_date ставим сегодняшним днём, а не копируем из источника: задним числом
// эти тарифы не действовали, и /pricing/snapshot достраивал бы их в прошлое.
$today = new \DateTime('today');

\bb\Db::startTransaction();

foreach ($src_rows as $r) {
    $t = new \bb\classes\Tariff();
    $t->tarif_id      = 0;
    $t->model_id      = $dst_id;
    $t->start_date    = clone $today;
    $t->step          = $r['step'];
    $t->kol_vo        = (int) $r['kol_vo'];
    $t->kol_vo_min    = (int) $r['kol_vo_min'];
    $t->rent_amount   = $r['rent_amount'];
    $t->rent_per_step = $r['rent_per_step'];
    $t->sort_num      = (int) $r['sort_num'];
    $t->hardSave();

    echo "  записан tarif_id={$t->tarif_id} step={$t->step} kol_vo={$t->kol_vo} rent_amount={$t->rent_amount}\n";
}

foreach ($to_delete as $r) {
    $t = \bb\classes\Tariff::getById($r['tarif_id']);
    if ($t) {
        $t->delete();
        echo "  удалён tarif_id={$r['tarif_id']}\n";
    }
}

\bb\Db::commitTransaction();

echo "\nГОТОВО. Итог:\n\n";
dump_tarifs($mysqli, $dst_id, "ПРИЁМНИК $DST_SLUG после записи");
