<?php
session_start();
ini_set('display_errors', (isset($_SESSION['svoi']) && $_SESSION['svoi'] == 8941) ? 1 : 0);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php';

\bb\Base::loginCheck();
if (!\bb\models\User::getCurrentUser()->isManagement()) {
    http_response_code(403);
    die('<p>Доступ запрещён.</p>');
}

$mysqli = \bb\Db::getInstance()->getConnection();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$razdelId = isset($_GET['razdel_id']) ? (int)$_GET['razdel_id'] : 0;
$tab  = $_GET['tab'] ?? 'stock'; // 'stock' or 'assortment'

$safeFrom = $mysqli->real_escape_string($from);
$safeTo   = $mysqli->real_escape_string($to);

// Get Razdels for filter
$razdels = [];
$resR = $mysqli->query("SELECT id_razdel, name_razdel_text FROM razdel ORDER BY name_razdel_text");
while ($r = $resR->fetch_assoc()) {
    $razdels[] = $r;
}

// Build Query
$reason = ($tab === 'stock') ? 'stock' : 'assortment';
$razdelWhere = "";
if ($razdelId > 0) {
    $safeRazdel = (int)$razdelId;
    $razdelWhere = " AND (sr.main_razdel_id = {$safeRazdel} OR cdi.cat_id IS NULL) ";
}

// Need to group by category and count
$sql = "
    SELECT 
        cdi.cat_id,
        COALESCE(trc.rent_cat_name, cdi.cat_name) AS cat_name,
        COUNT(*) AS mentions,
        SUM(cdi.kind = 'missed' AND cdi.missed_outcome = 'hard') AS missed_hard,
        SUM(cdi.kind = 'missed' AND cdi.missed_outcome = 'soft') AS missed_soft,
        GROUP_CONCAT(cdi.phrase SEPARATOR '|||') AS phrases_raw
    FROM call_demand_items cdi
    LEFT JOIN a1_call_analysis ca ON ca.recording_uuid = cdi.recording_uuid
    LEFT JOIN tovar_rent_cat trc ON trc.tovar_rent_cat_id = cdi.cat_id
    LEFT JOIN sub_razdel sr ON sr.id_sub_razdel = trc.main_sub_razdel_id
    WHERE cdi.call_date BETWEEN '{$safeFrom}' AND '{$safeTo}'
      AND cdi.missed_reason = '{$reason}'
      AND ca.is_internal = 0
      {$razdelWhere}
    GROUP BY COALESCE(cdi.cat_id, 0), COALESCE(trc.rent_cat_name, cdi.cat_name)
    ORDER BY mentions DESC
";

$result = $mysqli->query($sql);
$items = [];
$nullItems = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $phrases = explode('|||', $row['phrases_raw']);
        $uniquePhrases = array_values(array_unique(array_filter($phrases)));
        $row['top_phrases'] = array_slice($uniquePhrases, 0, 5);
        
        if ($row['cat_id'] === null) {
            $nullItems[] = $row;
        } else {
            $items[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Спрос по категориям</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/bb/bb_nav.css?v=5">
    <style>
        body { padding-top: 60px; background: #f8f9fa; }
        .controls-header { background: #fff; padding: 16px 20px; border-bottom: 1px solid #dee2e6; margin-bottom: 20px; }
        .table-wrap { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 20px; margin-bottom: 30px; }
        .nav-tabs { border-bottom: 2px solid #dee2e6; margin-bottom: 20px; }
        .nav-tabs .nav-item { margin-bottom: -2px; }
        .nav-tabs .nav-link { border: none; color: #495057; font-weight: 500; padding: 10px 20px; }
        .nav-tabs .nav-link:hover { background: transparent; color: #007bff; }
        .nav-tabs .nav-link.active { color: #007bff; border-bottom: 2px solid #007bff; background: transparent; }
        .phrase-badge { margin: 2px; font-size: 0.85rem; font-weight: normal; }
    </style>
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bb/bb_nav.php'; ?>

<div class="container-fluid" style="max-width:1200px;">
    
    <div class="controls-header rounded">
        <form method="GET" class="d-flex align-items-center flex-wrap gap-3" style="gap: 15px;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            
            <div class="d-flex align-items-center">
                <label class="mr-2 mb-0 font-weight-bold">Период:</label>
                <input type="date" name="from" value="<?= $from ?>" class="form-control form-control-sm mr-2" style="width:140px;">
                <span class="mr-2">—</span>
                <input type="date" name="to" value="<?= $to ?>" class="form-control form-control-sm" style="width:140px;">
            </div>
            
            <div class="d-flex align-items-center">
                <label class="mr-2 mb-0 font-weight-bold">Раздел:</label>
                <select name="razdel_id" class="form-control form-control-sm" style="width:200px;">
                    <option value="0">Все разделы</option>
                    <?php foreach ($razdels as $rz): ?>
                        <option value="<?= $rz['id_razdel'] ?>" <?= $razdelId == $rz['id_razdel'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rz['name_razdel_text']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary btn-sm">Показать</button>
        </form>
    </div>

    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'stock' ? 'active' : '' ?>" href="?from=<?= $from ?>&to=<?= $to ?>&razdel_id=<?= $razdelId ?>&tab=stock">
                Докупить в парк (нет в наличии)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'assortment' ? 'active' : '' ?>" href="?from=<?= $from ?>&to=<?= $to ?>&razdel_id=<?= $razdelId ?>&tab=assortment">
                Нет в ассортименте (упущенная выручка)
            </a>
        </li>
    </ul>

    <div class="table-wrap">
        <?php if ($tab === 'stock'): ?>
            <h5 class="mb-4">Товары из каталога, которых не хватило клиентам</h5>
        <?php else: ?>
            <h5 class="mb-4">Запросы на товары, которых у нас нет вообще</h5>
        <?php endif; ?>

        <?php if (empty($items) && empty($nullItems)): ?>
            <div class="alert alert-light border text-center py-4">Нет данных за выбранный период.</div>
        <?php else: ?>
            <table class="table table-hover table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Категория</th>
                        <th width="120" class="text-center">Упоминаний</th>
                        <th width="120" class="text-center" title="Отказ">Упущено 🔴</th>
                        <th width="120" class="text-center" title="Готов ждать">Упущено 🟡</th>
                        <th>Топ-5 фраз из звонков</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['cat_name']) ?></strong></td>
                            <td class="text-center font-weight-bold" style="font-size:1.1rem;"><?= $row['mentions'] ?></td>
                            <td class="text-center"><?= $row['missed_hard'] > 0 ? '<span class="text-danger font-weight-bold">'.$row['missed_hard'].'</span>' : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-center"><?= $row['missed_soft'] > 0 ? '<span class="text-warning font-weight-bold">'.$row['missed_soft'].'</span>' : '<span class="text-muted">—</span>' ?></td>
                            <td>
                                <?php foreach ($row['top_phrases'] as $phrase): ?>
                                    <span class="badge badge-light border phrase-badge"><?= htmlspecialchars($phrase) ?></span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($nullItems)): ?>
                        <tr class="table-secondary">
                            <td colspan="5" class="font-weight-bold text-center">Нет в каталоге (Категория не определена)</td>
                        </tr>
                        <?php foreach ($nullItems as $row): ?>
                            <tr>
                                <td><span class="text-muted"><i><?= htmlspecialchars($row['cat_name'] ?: 'Без названия') ?></i></span></td>
                                <td class="text-center font-weight-bold" style="font-size:1.1rem;"><?= $row['mentions'] ?></td>
                                <td class="text-center"><?= $row['missed_hard'] > 0 ? '<span class="text-danger font-weight-bold">'.$row['missed_hard'].'</span>' : '<span class="text-muted">—</span>' ?></td>
                                <td class="text-center"><?= $row['missed_soft'] > 0 ? '<span class="text-warning font-weight-bold">'.$row['missed_soft'].'</span>' : '<span class="text-muted">—</span>' ?></td>
                                <td>
                                    <?php foreach ($row['top_phrases'] as $phrase): ?>
                                        <span class="badge badge-light border phrase-badge"><?= htmlspecialchars($phrase) ?></span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
