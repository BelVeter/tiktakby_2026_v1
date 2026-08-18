<?php
/**
 * ОДНОРАЗОВАЯ диагностика «последнего использованного тарифа» — только чтение,
 * удалить после прогона.
 *
 * Считает, у скольких действующих сделок денормализованная пара
 * tarif_value + tarif_step расходится с фактической ставкой (сумма ÷ период),
 * и показывает худшие случаи. Ключ доступа — тот же, что у Deploy.php.
 */

$secret_key = 'Deploy-Mb8941';
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die('Access Denied');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/classes/Deal.php');

header('Content-Type: text/plain; charset=utf-8');

$mysqli = \bb\Db::getInstance()->getConnection();
$inv_n  = isset($_GET['inv_n']) ? (int)$_GET['inv_n'] : 0;

if ($inv_n > 0) {
    echo "=== суб-сделки действующей сделки по инв.№$inv_n ===\n";
    $res = $mysqli->query(
        "SELECT s.sub_deal_id, s.type, s.`from`, s.`to`, s.tarif_step, s.tarif_value,
                s.rent_tenor, s.r_to_pay
         FROM tovar_rent_items i
         JOIN rent_sub_deals_act s ON s.deal_id = i.active_deal_id
         WHERE i.item_inv_n = $inv_n
         ORDER BY s.sub_deal_id"
    );
    if (!$res || $res->num_rows === 0) {
        echo "  действующей сделки нет\n";
    } else {
        while ($row = $res->fetch_assoc()) {
            $days = (int)round(($row['to'] - $row['from']) / 86400);
            printf(
                "  #%-7s %-12s %s → %s (%s дн.)  оплачено %-8s | в сделке: %s за %s, tenor %s  =>  факт %s/день, форма берёт %s/день\n",
                $row['sub_deal_id'], $row['type'],
                $row['from'] > 0 ? date('d.m.y', $row['from']) : '—',
                $row['to'] > 0 ? date('d.m.y', $row['to']) : '—',
                $days, $row['r_to_pay'], $row['tarif_value'], $row['tarif_step'], $row['rent_tenor'],
                $days > 0 && $row['r_to_pay'] > 0 ? number_format($row['r_to_pay'] / $days, 2) : '—',
                number_format($row['tarif_value'] / ($row['tarif_step'] == 'month' ? 30 : ($row['tarif_step'] == 'week' ? 7 : 1)), 2)
            );
        }
    }
    echo "\n";
}

echo "=== расхождение ставки по всем действующим сделкам ===\n";

$res = $mysqli->query(
    "SELECT d.deal_id, d.item_inv_n,
            s.`from`, s.`to`, s.r_to_pay, s.tarif_value, s.tarif_step
     FROM rent_deals_act d
     JOIN rent_sub_deals_act s ON s.sub_deal_id = (
         SELECT sub_deal_id FROM rent_sub_deals_act
         WHERE deal_id = d.deal_id AND type IN ('first_rent','extention','takeaway_plan')
         ORDER BY sub_deal_id DESC LIMIT 1
     )"
);

$buckets = ['не меняется' => 0, 'до 5% (округление)' => 0, '5-20%' => 0, 'больше 20%' => 0];
$worst = [];

while ($row = $res->fetch_assoc()) {
    $step_days = ($row['tarif_step'] == 'month' ? 30 : ($row['tarif_step'] == 'week' ? 7 : 1));
    $before = round(((float)$row['tarif_value']) / $step_days, 2);
    $after  = \bb\classes\Deal::lastTarifPerDay($row);

    if ($before <= 0) {
        if (abs($after - $before) >= 0.005) {
            $buckets['больше 20%']++;
            $worst[] = sprintf('  инв.%-8s сделка %-7s  %s → %s', $row['item_inv_n'], $row['deal_id'], $before, $after);
        } else {
            $buckets['не меняется']++;
        }
        continue;
    }

    $dev = abs($after - $before) / $before;
    if ($dev < 0.005) {
        $buckets['не меняется']++;
    } elseif ($dev < 0.05) {
        $buckets['до 5% (округление)']++;
    } elseif ($dev < 0.20) {
        $buckets['5-20%']++;
    } else {
        $buckets['больше 20%']++;
        $worst[] = sprintf(
            '  инв.%-8s сделка %-7s  было %s/день → стало %s/день  (в %.1f раза)',
            $row['item_inv_n'], $row['deal_id'], number_format($before, 2), number_format($after, 2), $after / $before
        );
    }
}

foreach ($buckets as $name => $count) {
    printf("  %-22s %s\n", $name, $count);
}

echo "\n=== сделки со сломанной ставкой (расхождение больше 20%) ===\n";
echo $worst ? implode("\n", $worst) . "\n" : "  таких нет\n";

echo "\nСкрипт только читает. Удалить после прогона.\n";
