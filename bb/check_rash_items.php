<?php
/**
 * Разовая проверка справочника статей расходов после миграции
 * 2026_08_18_120000_update_rash_items_office_articles. Только чтение.
 * Гейтится тем же ключом, что Deploy.php. Удаляется следующим PR.
 */

if (!isset($_GET['key']) || $_GET['key'] !== 'Deploy-Mb8941') {
    http_response_code(404);
    die('not found');
}

header('Content-Type: text/plain; charset=utf-8');

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
$mysqli = \bb\Db::getInstance()->getConnection();

$res = $mysqli->query("SELECT migration, batch FROM migrations
                       WHERE migration LIKE '%rash_items_office_articles%'");
echo "-- migrations:\n";
if (!$res || $res->num_rows == 0) {
    echo "  НЕ ПРИМЕНЕНА\n";
} else {
    while ($r = $res->fetch_assoc()) echo "  {$r['migration']} (batch {$r['batch']})\n";
}

echo "\n-- rash_items:\n";
$res = $mysqli->query("SELECT ri_id, ri_order, ri_code, ri_text, bank_yn, is_active
                       FROM rash_items ORDER BY ri_order, ri_id");
while ($r = $res->fetch_assoc()) {
    printf("  %4d  %5d  %-14s %-30s bank=%d active=%d\n",
        $r['ri_id'], $r['ri_order'], $r['ri_code'], $r['ri_text'], $r['bank_yn'], $r['is_active']);
}
