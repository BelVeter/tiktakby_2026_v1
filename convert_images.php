<?php

$sourceDir = __DIR__ . '/public/slider';
$quality = 80;

if (!is_dir($sourceDir)) {
    die("Directory not found: $sourceDir\n");
}

$files = scandir($sourceDir);

foreach ($files as $file) {
    if ($file === '.' || $file === '..')
        continue;

    $sourcePath = $sourceDir . '/' . $file;
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (!in_array($extension, ['jpg', 'jpeg', 'png']))
        continue;

    $destinationPath = $sourceDir . '/' . pathinfo($file, PATHINFO_FILENAME) . '.webp';

    echo "Converting $file -> " . basename($destinationPath) . "... ";

    try {
        if ($extension === 'png') {
            $image = imagecreatefrompng($sourcePath);
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        } else {
            $image = imagecreatefromjpeg($sourcePath);
        }

        if ($image) {
            imagewebp($image, $destinationPath, $quality);
            imagedestroy($image);
            echo "OK\n";
        } else {
            echo "FAILED (imagecreate error)\n";
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "Done!\n";
