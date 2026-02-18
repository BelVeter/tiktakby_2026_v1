<?php
/**
 * AVIF → WebP Converter
 *
 * Finds all AVIF image paths in the database (rent_model_web + dop_photos),
 * converts each file to WebP (originals are NOT deleted),
 * and updates the database paths to use .webp extension.
 *
 * Run from browser: /bb/avif_to_webp.php
 * Or from CLI:      php bb/avif_to_webp.php
 */

define('BASE_DIR', dirname(__DIR__)); // project root = ~/public_html

require_once __DIR__ . '/Db.php';

$mysqli = bb\Db::getInstance()->getConnection();

$quality = 80; // WebP quality (0-100)
$dryRun = isset($_GET['dry']) || in_array('--dry', $argv ?? []); // add ?dry or --dry to preview only

header('Content-Type: text/plain; charset=utf-8');

echo "=== AVIF → WebP Converter ===\n";
echo "Base dir : " . BASE_DIR . "\n";
echo "Quality  : $quality\n";
echo "Dry run  : " . ($dryRun ? "YES (no changes will be made)" : "NO (will convert & update DB)") . "\n\n";

// ─── Collect all AVIF paths from DB ──────────────────────────────────────────

$paths = []; // [ 'db_path' => true ]

// rent_model_web: m_pic_big, l2_pic, logo
$res = $mysqli->query("SELECT DISTINCT m_pic_big AS p FROM rent_model_web WHERE m_pic_big LIKE '%.avif'");
while ($row = $res->fetch_assoc())
    $paths[$row['p']] = true;

$res = $mysqli->query("SELECT DISTINCT l2_pic AS p FROM rent_model_web WHERE l2_pic LIKE '%.avif'");
while ($row = $res->fetch_assoc())
    $paths[$row['p']] = true;

$res = $mysqli->query("SELECT DISTINCT logo AS p FROM rent_model_web WHERE logo LIKE '%.avif'");
while ($row = $res->fetch_assoc())
    $paths[$row['p']] = true;

// dop_photos: src
$res = $mysqli->query("SELECT DISTINCT src AS p FROM dop_photos WHERE src LIKE '%.avif'");
while ($row = $res->fetch_assoc())
    $paths[$row['p']] = true;

$total = count($paths);
echo "Found $total unique AVIF paths in DB.\n\n";

if ($total === 0) {
    echo "Nothing to do.\n";
    exit;
}

// ─── Convert each file ───────────────────────────────────────────────────────

$converted = 0;
$skipped = 0;
$errors = [];

foreach ($paths as $dbPath => $_) {
    // dbPath is like /public/rent/images/.../file.avif
    // Physical file is at BASE_DIR . $dbPath
    $srcFile = BASE_DIR . $dbPath;
    $dstPath = preg_replace('/\.avif$/i', '.webp', $dbPath);
    $dstFile = BASE_DIR . $dstPath;

    echo "[$dbPath]\n";

    // Check source exists
    if (!file_exists($srcFile)) {
        echo "  ⚠ Source file not found: $srcFile\n";
        $errors[] = $dbPath;
        continue;
    }

    // Check if WebP already exists
    if (file_exists($dstFile)) {
        echo "  ✓ WebP already exists, skipping conversion.\n";
    } else {
        if (!$dryRun) {
            // Convert AVIF → WebP using Imagick (preferred) or GD
            $ok = false;

            if (class_exists('Imagick')) {
                try {
                    $im = new Imagick($srcFile);
                    $im->setImageFormat('webp');
                    $im->setImageCompressionQuality($quality);
                    $im->writeImage($dstFile);
                    $im->destroy();
                    $ok = true;
                    echo "  ✓ Converted via Imagick.\n";
                } catch (Exception $e) {
                    echo "  ✗ Imagick error: " . $e->getMessage() . "\n";
                }
            }

            if (!$ok && function_exists('imagecreatefromstring')) {
                // GD fallback: read raw bytes, create image from string
                $raw = file_get_contents($srcFile);
                $img = @imagecreatefromstring($raw);
                if ($img) {
                    imagewebp($img, $dstFile, $quality);
                    imagedestroy($img);
                    $ok = true;
                    echo "  ✓ Converted via GD.\n";
                } else {
                    echo "  ✗ GD could not decode AVIF (GD usually cannot decode AVIF natively).\n";
                }
            }

            if (!$ok) {
                echo "  ✗ Conversion failed — no suitable library.\n";
                $errors[] = $dbPath;
                continue;
            }
        } else {
            echo "  [DRY] Would convert: $srcFile → $dstFile\n";
        }
    }

    // ─── Update DB ───────────────────────────────────────────────────────────
    if (!$dryRun) {
        $srcEsc = $mysqli->real_escape_string($dbPath);
        $dstEsc = $mysqli->real_escape_string($dstPath);

        $mysqli->query("UPDATE rent_model_web SET m_pic_big='$dstEsc' WHERE m_pic_big='$srcEsc'");
        $mysqli->query("UPDATE rent_model_web SET l2_pic='$dstEsc'   WHERE l2_pic='$srcEsc'");
        $mysqli->query("UPDATE rent_model_web SET logo='$dstEsc'     WHERE logo='$srcEsc'");
        $mysqli->query("UPDATE dop_photos     SET src='$dstEsc'      WHERE src='$srcEsc'");

        echo "  ✓ DB updated: $dbPath → $dstPath\n";
    } else {
        echo "  [DRY] Would update DB: $dbPath → $dstPath\n";
    }

    $converted++;
    echo "\n";
}

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "=== Done ===\n";
echo "Processed : $converted / $total\n";
echo "Errors    : " . count($errors) . "\n";
if ($errors) {
    echo "Failed paths:\n";
    foreach ($errors as $e)
        echo "  - $e\n";
}
echo "\nOriginal .avif files were NOT deleted.\n";
