<?php
require_once(__DIR__ . '/bb/Db.php');
$mysqli = \bb\Db::getInstance()->getConnection();

// 1. Get all unique logo values from DB
$res = $mysqli->query("SELECT DISTINCT logo FROM rent_model_web WHERE logo IS NOT NULL AND logo != ''");
$logoData = [];

while ($row = $res->fetch_assoc()) {
    $path = $row['logo'];
    $basename = pathinfo($path, PATHINFO_FILENAME);
    $dir = dirname($path);
    $logoData[] = [
        'basename' => $basename,
        'dir' => $dir,
        'original' => $path
    ];
}

$root = 'd:/sites/tiktakby_2026_v1';
$bundleDir = $root . '/logos_bundle';

// Clean start
if (is_dir($bundleDir)) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        shell_exec("rd /s /q " . escapeshellarg($bundleDir));
    } else {
        shell_exec("rm -rf " . escapeshellarg($bundleDir));
    }
}
mkdir($bundleDir, 0755, true);

$extensions = ['png', 'jpg', 'jpeg', 'webp'];
$copiedFiles = [];

echo "Processing " . count($logoData) . " logo references...\n";

foreach ($logoData as $data) {
    $name = $data['basename'];
    $intendedDir = $data['dir'];

    // 1. Check same directory in our project but for any valid extension
    $absDir = $root . $intendedDir;
    if (is_dir($absDir)) {
        foreach ($extensions as $ext) {
            $testPath = $absDir . '/' . $name . '.' . $ext;
            if (file_exists($testPath)) {
                copyToBundle($testPath, $intendedDir . '/' . $name . '.' . $ext, $bundleDir, $copiedFiles);
            }
        }
    }

    // 2. Check a few other common locations specifically if not found or if we want "all" versions
    $commonDirs = [
        $root . '/public/rent/images/images',
        $root . '/public/images',
    ];

    foreach ($commonDirs as $cDir) {
        if (!is_dir($cDir))
            continue;
        foreach ($extensions as $ext) {
            $testPath = $cDir . '/' . $name . '.' . $ext;
            if (file_exists($testPath)) {
                copyToBundle($testPath, $intendedDir . '/' . $name . '.' . $ext, $bundleDir, $copiedFiles);
            }
        }
    }
}

function copyToBundle($sourceAbs, $targetRel, $bundleDir, &$copiedFiles)
{
    $targetAbs = $bundleDir . $targetRel;
    if (!is_dir(dirname($targetAbs)))
        mkdir(dirname($targetAbs), 0755, true);

    if (!isset($copiedFiles[$targetAbs])) {
        if (copy($sourceAbs, $targetAbs)) {
            $copiedFiles[$targetAbs] = true;
            echo "Copied: " . basename($sourceAbs) . " to $targetRel\n";
        }
    }
}

echo "\n--- SUMMARY ---\n";
echo "Total unique target files in bundle: " . count($copiedFiles) . "\n";
echo "Bundle ready at: $bundleDir\n";
