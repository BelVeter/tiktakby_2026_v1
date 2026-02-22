<?php
/**
 * Script to find broken image links in the database.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Finding broken image links...\n";

// 1. Gather all image paths from database
$dbPaths = [];

$queries = [
    ['table' => 'favorite_tovars', 'column' => 'pic_url', 'id' => 'id'],
    ['table' => 'multi_web', 'column' => 'l2_pic_add', 'id' => 'multi_id'],
    ['table' => 'offices', 'column' => 'pic_addr', 'id' => 'number'],
    ['table' => 'pages', 'column' => 'h1_pic_url', 'id' => 'page_id'],
    ['table' => 'razdel', 'column' => 'url_icon_razdel', 'id' => 'id_razdel'],
    ['table' => 'razdel', 'column' => 'url_icon2_razdel', 'id' => 'id_razdel'],
    ['table' => 'rent_model_web', 'column' => 'l2_pic', 'id' => 'model_id'],
    ['table' => 'rent_model_web', 'column' => 'm_pic_big', 'id' => 'model_id'],
    ['table' => 'sub_razdel', 'column' => 'url_sub_razdel_icon', 'id' => 'id_sub_razdel'],
    ['table' => 'tovar_cats', 'column' => 'cat_photo_url', 'id' => 'id'],
    ['table' => 'tovar_list', 'column' => 'w_icon', 'id' => 'tovar_id'],
    ['table' => 'dop_photos', 'column' => 'src', 'id' => 'dop_id'],
];

$publicDir = realpath(__DIR__ . '/public');
$brokenLinks = [];

// Exclude known placeholders or external URLs
$excludeList = ['no_image.png', 'no_image.jpg', 'default.png', ''];

foreach ($queries as $q) {
    $table = $q['table'];
    $column = $q['column'];
    $idCol = $q['id'];

    try {
        $results = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->get([$idCol, $column]);

        foreach ($results as $row) {
            $path = trim((string) $row->{$column});
            if (empty($path))
                continue;

            // Skip external URLs
            if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
                continue;
            }

            // Normalize path (some might have /public/ prefix, some might not)
            $normalized = str_replace('\\', '/', $path);
            if (strpos($normalized, '/public/') === 0) {
                $normalized = substr($normalized, 7); // removes '/public', leaving '/...'
            }
            if (strpos($normalized, '/') !== 0) {
                $normalized = '/' . $normalized; // ensure starts with /
            }

            // Skip if it's just a filename of a placeholder
            if (in_array(basename($normalized), $excludeList)) {
                continue;
            }

            $physicalPath = $publicDir . check_and_fix_path($normalized);

            if (!file_exists($physicalPath)) {
                $brokenLinks[] = [
                    'table' => $table,
                    'column' => $column,
                    'id' => $row->{$idCol},
                    'path' => $path,
                    'physical_path_checked' => $physicalPath
                ];
            }
        }
    } catch (\Exception $e) {
        // Silently ignore table/column not found errors
    }
}

function check_and_fix_path($path)
{
    // In windows, we don't care about case, but Linux does. 
    // Here we just use the path as-is for the exist check.
    return $path;
}

$brokenCount = count($brokenLinks);
echo "Found $brokenCount broken image links in the database.\n";

if ($brokenCount > 0) {
    $reportFile = __DIR__ . '/broken_images_report.txt';
    $handle = fopen($reportFile, 'w');
    fwrite($handle, "Broken Image Links Report\nGenerated: " . date('Y-m-d H:i:s') . "\nTotal broken links: $brokenCount\n\n");

    // Group by table
    $byTable = [];
    foreach ($brokenLinks as $broken) {
        $byTable[$broken['table']][] = $broken;
    }

    foreach ($byTable as $table => $items) {
        echo "- Table `$table`: " . count($items) . " broken links.\n";
        fwrite($handle, "--- Table: $table ---\n");
        foreach ($items as $item) {
            fwrite($handle, "ID: {$item['id']} | Column: {$item['column']} | Path in DB: {$item['path']}\n");
        }
        fwrite($handle, "\n");
    }
    fclose($handle);
    echo "Summary saved to broken_images_report.txt\n";
}

echo "Done.\n";
