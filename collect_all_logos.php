<?php
require_once(__DIR__ . '/bb/Db.php');
$mysqli = \bb\Db::getInstance()->getConnection();

// 1. Get all unique logo values (including webp, png, etc.)
$res = $mysqli->query("SELECT DISTINCT logo FROM rent_model_web WHERE logo IS NOT NULL AND logo != ''");
$logoBasenames = [];
$originalPaths = [];

while ($row = $res->fetch_assoc()) {
    $path = $row['logo'];
    $originalPaths[] = $path;
    $basename = pathinfo($path, PATHINFO_FILENAME);
    $logoBasenames[$basename] = dirname($path); // Store the intended directory
}

$root = 'd:/sites/tiktakby_2026_v1';
$bundleDir = $root . '/logos_bundle';

// Clean start
if (is_dir($bundleDir)) {
    // Basic cleanup
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        shell_exec("rd /s /q " . escapeshellarg($bundleDir));
    } else {
        shell_exec("rm -rf " . escapeshellarg($bundleDir));
    }
}
mkdir($bundleDir, 0755, true);

$searchRoots = [
    'd:/sites/tiktakby_2026_v1/public',
    'd:/sites/tiktakby_2026_v1/bb',
    'd:/sites/' // Search other projects/backups as requested
];

$extensions = ['png', 'jpg', 'jpeg', 'webp'];
$foundCount = 0;
$copiedFiles = [];

echo "Processing " . count($logoBasenames) . " unique logo basenames...\n";

foreach ($logoBasenames as $name => $intendedDir) {
    if (empty($name))
        continue;

    $foundForThisLogo = false;

    foreach ($searchRoots as $sRoot) {
        if (!is_dir($sRoot))
            continue;

        $it = new RecursiveDirectoryIterator($sRoot, RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
        $itIterator = new RecursiveIteratorIterator($it);

        foreach ($itIterator as $file) {
            if ($file->isDir())
                continue;

            $currName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $currExt = strtolower($file->getExtension());

            if ($currName === $name && in_array($currExt, $extensions)) {
                // Determine target path in bundle
                // We preserve the structure from the DB reference as the primary target
                $targetRelPath = $intendedDir . '/' . $file->getFilename();
                $targetAbsPath = $bundleDir . $targetRelPath;

                if (!is_dir(dirname($targetAbsPath)))
                    mkdir(dirname($targetAbsPath), 0755, true);

                if (!isset($copiedFiles[$targetAbsPath])) {
                    if (copy($file->getRealPath(), $targetAbsPath)) {
                        $copiedFiles[$targetAbsPath] = true;
                        echo "Copied: " . $file->getFilename() . " to $targetRelPath\n";
                        $foundForThisLogo = true;
                    }
                }
            }
        }
    }

    if ($foundForThisLogo) {
        $foundCount++;
    } else {
        echo "WARNING: No files found for basename '$name'\n";
    }
}

echo "\n--- SUMMARY ---\n";
echo "Unique logos processed: " . count($logoBasenames) . "\n";
echo "Logos with at least one file found: $foundCount\n";
echo "Total files copied to bundle: " . count($copiedFiles) . "\n";
echo "Bundle ready at: $bundleDir\n";
