<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$logos = DB::table('rent_model_web')->whereNotNull('logo')->where('logo', '!=', '')->pluck('logo', 'web_id');

$currentDir = __DIR__;
$oldDir = realpath(__DIR__ . '/../tiktakby_2026_v1_old');

echo "Scanning old directory for images...\n";
$allOldFiles = [];
function scanDirAndMap($dir, &$map)
{
    if (!is_dir($dir))
        return;
    $files = scandir($dir);
    foreach ($files as $f) {
        if ($f == '.' || $f == '..')
            continue;
        $path = $dir . DIRECTORY_SEPARATOR . $f;
        if (is_dir($path)) {
            // skip vendor/node_modules to speed up
            if ($f == 'vendor' || $f == 'node_modules')
                continue;
            scanDirAndMap($path, $map);
        } else {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $map[$f][] = $path; // might be multiple with same name
            }
        }
    }
}
scanDirAndMap($oldDir, $allOldFiles);

$missingDb = [];
$recovered = [];
$stillMissing = [];

foreach ($logos as $id => $path) {
    $path = trim((string) $path);
    if (empty($path))
        continue;

    // Skip external or malformed URLs
    if (strpos($path, 'http://') !== false || strpos($path, 'https://') !== false) {
        continue;
    }

    $normalizedPath = str_replace('\\', '/', $path);
    if (strpos($normalizedPath, '/') !== 0) {
        $normalizedPath = '/' . $normalizedPath;
    }

    $physicalPath = $currentDir . $normalizedPath;
    if (!file_exists($physicalPath)) {
        $missingDb[$id] = $normalizedPath;

        $basename = basename($normalizedPath);
        if (isset($allOldFiles[$basename]) && count($allOldFiles[$basename]) > 0) {
            // Process the clearest old path if possible, or just the first
            $sourcePath = $allOldFiles[$basename][0];
            foreach ($allOldFiles[$basename] as $p) {
                if (strpos(str_replace('\\', '/', $p), '/public/') !== false) {
                    $sourcePath = $p; // prefer public
                    break;
                }
            }

            $dir = dirname($physicalPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (copy($sourcePath, $physicalPath)) {
                $recovered[] = $normalizedPath;
            } else {
                $stillMissing[] = "Failed to copy: " . $normalizedPath;
            }
        } else {
            $stillMissing[] = "Not found anywhere in old dir: " . $normalizedPath;
        }
    }
}

echo "Total logos in DB: " . count($logos) . "\n";
echo "Missing logos detected: " . count($missingDb) . "\n";
echo "Successfully recovered by search: " . count($recovered) . "\n";
echo "Still missing: " . count($stillMissing) . "\n";

if (count($stillMissing) > 0) {
    echo "\n--- Still missing examples ---\n";
    foreach (array_slice($stillMissing, 0, 10) as $sm) {
        echo " - $sm\n";
    }
}
