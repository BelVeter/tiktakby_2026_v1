<?php
require_once(__DIR__ . '/bb/Db.php');
$mysqli = \bb\Db::getInstance()->getConnection();

// 1. Get all unique logo basenames and their intended directories
$res = $mysqli->query("SELECT DISTINCT logo FROM rent_model_web WHERE logo IS NOT NULL AND logo != ''");
$logoTargets = [];
while ($row = $res->fetch_assoc()) {
    $path = $row['logo'];
    $basename = pathinfo($path, PATHINFO_FILENAME);
    $dir = dirname($path);
    $logoTargets[$basename] = $dir;
}

echo "Unique logos to search for: " . count($logoTargets) . "\n";

$root = 'd:/sites/tiktakby_2026_v1';
$bundleDir = $root . '/logos_bundle';

// Clean start to ensure we don't have stale files
if (is_dir($bundleDir)) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        shell_exec("rd /s /q " . escapeshellarg($bundleDir));
    } else {
        shell_exec("rm -rf " . escapeshellarg($bundleDir));
    }
}
mkdir($bundleDir, 0755, true);

$extensions = ['png', 'jpg', 'jpeg', 'webp'];
$searchRoots = [
    'd:/sites/tiktakby_2026_v1/public',
    'd:/sites/tiktakby_2026_v1/bb',
    'd:/sites/' // Broad search
];

$stats = ['copied' => 0, 'unique_logos_found' => 0];
$foundBasenames = [];

foreach ($logoTargets as $name => $targetRelDir) {
    echo "Searching for '$name'...\n";
    $foundAny = false;

    foreach ($searchRoots as $sRoot) {
        if (!is_dir($sRoot))
            continue;

        // Use DOS 'dir' for speed on Windows
        $cmd = "dir /s /b " . escapeshellarg($sRoot . "\\" . $name . ".*");
        $output = shell_exec($cmd);

        if ($output) {
            $lines = explode("\n", trim($output));
            foreach ($lines as $absPath) {
                $absPath = trim($absPath);
                if (empty($absPath))
                    continue;

                $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
                if (in_array($ext, $extensions)) {
                    $fileName = basename($absPath);
                    $targetAbs = $bundleDir . $targetRelDir . '/' . $fileName;

                    if (!is_dir(dirname($targetAbs)))
                        mkdir(dirname($targetAbs), 0755, true);

                    if (!file_exists($targetAbs)) {
                        if (copy($absPath, $targetAbs)) {
                            $stats['copied']++;
                            $foundAny = true;
                            echo "  -> Copied $fileName\n";
                        }
                    } else {
                        $foundAny = true; // Already copied this version
                    }
                }
            }
        }
        // If we found something in our project, we might still want to look outside
        // for "original" non-webp versions if we only found webp.
        // But to keep it simple and safe, we stop after we've checked all roots.
    }

    if ($foundAny) {
        $stats['unique_logos_found']++;
        $foundBasenames[] = $name;
    } else {
        echo "  !! NOT FOUND ANYWHERE: $name\n";
    }
}

echo "\n--- FINAL SUMMARY ---\n";
echo "Total unique logos (basenames) in DB: " . count($logoTargets) . "\n";
echo "Unique logos found: " . $stats['unique_logos_found'] . "\n";
echo "Total files copied (including different formats): " . $stats['copied'] . "\n";
echo "Bundle ready at: $bundleDir\n";
