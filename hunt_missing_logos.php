<?php
require_once(__DIR__ . '/bb/Db.php');
$mysqli = \bb\Db::getInstance()->getConnection();

// 1. Get all unique logo values from DB
$res = $mysqli->query("SELECT DISTINCT logo FROM rent_model_web WHERE logo IS NOT NULL AND logo != ''");
$logoData = [];
$allLogos = [];

while ($row = $res->fetch_assoc()) {
    $path = $row['logo'];
    $basename = pathinfo($path, PATHINFO_FILENAME);
    $dir = dirname($path);
    $logoData[$path] = [
        'basename' => $basename,
        'dir' => $dir,
    ];
    $allLogos[$path] = false; // Mark as not found initially
}

$root = 'd:/sites/tiktakby_2026_v1';
$bundleDir = $root . '/logos_bundle';

// We DON'T clean the bundle directory here, we want to add to it
if (!is_dir($bundleDir))
    mkdir($bundleDir, 0755, true);

$extensions = ['png', 'jpg', 'jpeg', 'webp'];
$copiedFiles = [];

// First, mark what's already in the bundle
$existingItems = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bundleDir));
foreach ($existingItems as $file) {
    if ($file->isDir())
        continue;
    $rel = str_replace($bundleDir, '', $file->getRealPath());
    $rel = str_replace('\\', '/', $rel);
    // Try to match back to allLogos
    foreach ($allLogos as $dbPath => $found) {
        $dbBase = pathinfo($dbPath, PATHINFO_FILENAME);
        $dbDir = dirname($dbPath);
        if (dirname($rel) === $dbDir && pathinfo($rel, PATHINFO_FILENAME) === $dbBase) {
            $allLogos[$dbPath] = true;
        }
    }
}

$missingLogos = array_filter($allLogos, function ($v) {
    return !$v; });

echo "Missing from bundle currently: " . count($missingLogos) . "\n";

if (count($missingLogos) > 0) {
    echo "Searching for missing logos in broader locations...\n";
    $searchRoots = [
        'd:/sites/tiktakby_2026_v1/public',
        'd:/sites/'
    ];

    foreach ($missingLogos as $path => $dummy) {
        $name = pathinfo($path, PATHINFO_FILENAME);
        $intendedDir = dirname($path);
        $found = false;

        foreach ($searchRoots as $sRoot) {
            if (!is_dir($sRoot))
                continue;

            // Targeted search for this filename
            $cmd = "dir /s /b " . escapeshellarg($sRoot . "\\" . $name . ".*");
            $output = shell_exec($cmd);
            if ($output) {
                $lines = explode("\n", trim($output));
                foreach ($lines as $fileLine) {
                    $fileLine = trim($fileLine);
                    if (empty($fileLine))
                        continue;
                    $ext = strtolower(pathinfo($fileLine, PATHINFO_EXTENSION));
                    if (in_array($ext, $extensions)) {
                        $targetRel = $intendedDir . '/' . basename($fileLine);
                        $targetAbs = $bundleDir . $targetRel;
                        if (!is_dir(dirname($targetAbs)))
                            mkdir(dirname($targetAbs), 0755, true);
                        if (copy($fileLine, $targetAbs)) {
                            echo "RECOVERED: $name to $targetRel from $fileLine\n";
                            $found = true;
                        }
                    }
                }
            }
            if ($found)
                break;
        }
        if (!$found) {
            echo "STILL NOT FOUND: $path\n";
        }
    }
}

echo "\n--- FINAL CHECK ---\n";
// Re-scan bundle to count
$finalFound = 0;
$finalPaths = [];
$res = $mysqli->query("SELECT DISTINCT logo FROM rent_model_web WHERE logo IS NOT NULL AND logo != ''");
while ($row = $res->fetch_assoc()) {
    $path = $row['logo'];
    $base = pathinfo($path, PATHINFO_FILENAME);
    $dir = dirname($path);
    $foundThis = false;
    foreach ($extensions as $ext) {
        if (file_exists($bundleDir . $dir . '/' . $base . '.' . $ext)) {
            $foundThis = true;
            break;
        }
    }
    if ($foundThis)
        $finalFound++;
}

echo "Total logos in DB: " . $res->num_rows . "\n";
echo "Found and present in bundle (any ext): $finalFound\n";
echo "Missing completely: " . ($res->num_rows - $finalFound) . "\n";
