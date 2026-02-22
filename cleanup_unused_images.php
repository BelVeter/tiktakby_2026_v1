<?php
/**
 * Script to find and optionally delete unused images in the public folder.
 * Run in CLI:
 * php cleanup_unused_images.php          -> Dry run (reports unused images)
 * php cleanup_unused_images.php --delete -> Deletes unused images
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$isDeleteMode = in_array('--delete', $argv);

echo "Starting Image Cleanup Script...\n";
if ($isDeleteMode) {
    echo "WARNING: DELETE MODE IS ON. Unused images will be physically removed.\n";
} else {
    echo "DRY RUN MODE. No files will be deleted. Use --delete to actually remove files.\n";
}

// 1. Gather all image paths from database
$dbPaths = [];

$queries = [
    ['table' => 'favorite_tovars', 'column' => 'pic_url'],
    ['table' => 'multi_web', 'column' => 'l2_pic_add'],
    ['table' => 'offices', 'column' => 'pic_addr'],
    ['table' => 'pages', 'column' => 'h1_pic_url'],
    ['table' => 'razdel', 'column' => 'url_icon_razdel'],
    ['table' => 'razdel', 'column' => 'url_icon2_razdel'],
    ['table' => 'rent_model_web', 'column' => 'l2_pic'],
    ['table' => 'rent_model_web', 'column' => 'm_pic_big'],
    ['table' => 'sub_razdel', 'column' => 'url_sub_razdel_icon'],
    ['table' => 'tovar_cats', 'column' => 'cat_photo_url'],
    ['table' => 'tovar_list', 'column' => 'w_icon'],
    ['table' => 'dop_photos', 'column' => 'src'], // Added from dop_photos
];

echo "Fetching paths from database...\n";
foreach ($queries as $q) {
    $table = $q['table'];
    $column = $q['column'];
    try {
        $results = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->pluck($column);
        foreach ($results as $path) {
            // Trim whitespace
            $path = trim((string) $path);
            if (empty($path))
                continue;

            // Normalize path (some might have /public/ prefix, some might not)
            // The physical paths will be checked against these. 
            // We want to store normalized relative paths like '/rent/images/...'
            $normalized = str_replace('\\', '/', $path);
            if (strpos($normalized, '/public/') === 0) {
                $normalized = substr($normalized, 7); // removes '/public', leaving '/...'
            }
            if (strpos($normalized, '/') !== 0) {
                $normalized = '/' . $normalized; // ensure starts with /
            }

            // Just to be safe, standardizing to lowercase for comparison (though case matters on Linux)
            $dbPaths[$normalized] = true;
        }
    } catch (\Exception $e) {
        echo "Error querying $table.$column: " . $e->getMessage() . "\n";
    }
}

echo "Found " . count($dbPaths) . " unique image references in the database.\n";

// 2. Scan physical directories
$publicDir = realpath(__DIR__ . '/public');
$directoriesToScan = [
    $publicDir . '/rent',
    $publicDir . '/images',
    $publicDir . '/img',
    $publicDir . '/pic',
    $publicDir . '/pics',
    $publicDir . '/slider',
    $publicDir . '/png',
    $publicDir . '/jpg',
];

$allFiles = [];

function scanDirRecursive($dir, &$results = [])
{
    if (!is_dir($dir))
        return $results;
    $files = scandir($dir);
    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            // Only care about image files
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $results[] = $path;
            }
        } else if ($value != "." && $value != "..") {
            scanDirRecursive($path, $results);
            $results[] = $path;
        }
    }
    return $results;
}

echo "Scanning physical directories...\n";
foreach ($directoriesToScan as $dir) {
    if (file_exists($dir)) {
        scanDirRecursive($dir, $allFiles);
    }
}

// Keep only actual files (filter out directories)
$imageFiles = array_filter($allFiles, function ($path) {
    return is_file($path);
});

echo "Found " . count($imageFiles) . " physical image files.\n";

// 3. Compare and find unused
$unusedFiles = [];
$totalSize = 0;

foreach ($imageFiles as $filePath) {
    // Convert absolute physical path to relative server-like path
    // e.g., D:\sites\tiktakby_2026_v1\public\rent\images\photo.jpg -> /rent/images/photo.jpg
    $relativePath = str_replace($publicDir, '', $filePath);
    $relativePath = str_replace('\\', '/', $relativePath);

    // Check if this path exists in DB
    $isUsed = false;

    // Direct match
    if (isset($dbPaths[$relativePath])) {
        $isUsed = true;
    } else {
        // Try case-insensitive comparison just in case
        foreach ($dbPaths as $dbPath => $val) {
            if (strcasecmp($dbPath, $relativePath) === 0) {
                $isUsed = true;
                break;
            }
        }
    }

    // Allow explicitly skipping some system files if they shouldn't be deleted even if not in DB
    $basename = basename($relativePath);
    if (in_array($basename, ['no_image.png', 'no_image.jpg', 'default.png', 'favicon.ico'])) {
        $isUsed = true;
    }

    if (!$isUsed) {
        $unusedFiles[] = $filePath;
        $totalSize += filesize($filePath);
    }
}

$countUnused = count($unusedFiles);
$formattedSize = round($totalSize / 1024 / 1024, 2);

echo "Found $countUnused unused images (Total size: {$formattedSize} MB).\n";

// 4. Output results and delete if requested
$reportFile = __DIR__ . '/unused_images_report.txt';
$reportHandle = fopen($reportFile, 'w');
fwrite($reportHandle, "Unused Images Report\nGenerated: " . date('Y-m-d H:i:s') . "\nTotal files: $countUnused\nTotal size: {$formattedSize} MB\n\n");

$deletedCount = 0;
foreach ($unusedFiles as $file) {
    fwrite($reportHandle, $file . "\n");
    if ($isDeleteMode) {
        if (unlink($file)) {
            $deletedCount++;
        } else {
            echo "Failed to delete: $file\n";
        }
    }
}

fclose($reportHandle);
echo "Report saved to unused_images_report.txt\n";

if ($isDeleteMode) {
    echo "Deleted $deletedCount files.\n";
} else {
    echo "Run with --delete flag to remove these files.\n";
}

echo "Done.\n";
