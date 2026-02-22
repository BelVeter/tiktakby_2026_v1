<?php
/**
 * Script to recover missing images from the archive.
 * It scans rent_model_web and dop_photos, checks for missing files,
 * and searches in the provided archive path.
 */
require_once(__DIR__ . '/bb/Db.php');
require_once(__DIR__ . '/bb/Base.php');

$mysqli = \bb\Db::getInstance()->getConnection();
$current_root = __DIR__;
$archive_roots = [
    'D:/sites/archiv/backup-2.21.2026_22-13-31_tiktakby/homedir/public_html',
    'D:/sites/tiktakby_2026_v1_old/public',
    'D:/sites/archiv/slider'
];
$global_search_root = 'D:/sites/archiv';

echo "Starting recovery process...\n";

// 1. Gather all unique paths from DB
$paths = [];

// From rent_model_web
$res = $mysqli->query("SELECT l2_pic, m_pic_big, logo FROM rent_model_web");
while ($row = $res->fetch_assoc()) {
    if (!empty($row['l2_pic']))
        $paths[] = $row['l2_pic'];
    if (!empty($row['m_pic_big']))
        $paths[] = $row['m_pic_big'];
    if (!empty($row['logo']))
        $paths[] = $row['logo'];
}

// From dop_photos
$res = $mysqli->query("SELECT src FROM dop_photos");
while ($row = $res->fetch_assoc()) {
    if (!empty($row['src']))
        $paths[] = $row['src'];
}

$paths = array_unique($paths);
$total = count($paths);
echo "Total unique paths to check: $total\n";

$found = 0;
$recovered = 0;
$missing = 0;

foreach ($paths as $path) {
    // Skip external URLs
    if (preg_match('#^https?://#i', $path))
        continue;

    // Clean up path (replace // with /)
    $clean_path = str_replace('//', '/', $path);

    $local_path = $current_root . $clean_path;
    if (file_exists($local_path)) {
        $found++;
        continue;
    }

    // It's missing locally. Try to find in archive roots.
    $filename = pathinfo($clean_path, PATHINFO_BASENAME);
    $ext = strtolower(pathinfo($clean_path, PATHINFO_EXTENSION));
    $base_name_no_ext = pathinfo($clean_path, PATHINFO_FILENAME);

    // Also handle .webp.webp or similar if referenced in DB
    if (strpos($base_name_no_ext, '.webp') !== false) {
        $base_name_no_ext = str_replace('.webp', '', $base_name_no_ext);
    }

    $found_in_archive = false;

    foreach ($archive_roots as $archive_root) {
        $alternatives = [$clean_path];
        if ($ext === 'webp') {
            $alternatives[] = dirname($clean_path) . '/' . $base_name_no_ext . '.png';
            $alternatives[] = dirname($clean_path) . '/' . $base_name_no_ext . '.jpg';
            $alternatives[] = dirname($clean_path) . '/' . $base_name_no_ext . '.jpeg';
        }

        foreach ($alternatives as $alt_rel_path) {
            $alt_rel_path = str_replace('//', '/', $alt_rel_path);
            $alt_archive_full = $archive_root . $alt_rel_path;
            if (file_exists($alt_archive_full)) {
                // Found it! Create local directory if needed
                $local_file_to_save = $current_root . $alt_rel_path;
                $dir = dirname($local_file_to_save);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                if (copy($alt_archive_full, $local_file_to_save)) {
                    echo "Recovered (direct in " . basename($archive_root) . "): $alt_rel_path\n";
                    $recovered++;
                    $found_in_archive = true;
                    break 2; // Move to next path in main loop
                }
            }
        }
    }

    // If still missing, try global search in global_search_root by filename
    if (!$found_in_archive) {
        $search_names = [$filename];
        if ($ext === 'webp') {
            $search_names[] = $base_name_no_ext . '.png';
            $search_names[] = $base_name_no_ext . '.jpg';
            $search_names[] = $base_name_no_ext . '.jpeg';
        }

        foreach ($search_names as $sn) {
            $sn_escaped = escapeshellarg($sn);
            $archive_root_win = str_replace('/', '\\', $global_search_root);
            $cmd = "dir \"$archive_root_win\\$sn\" /s /b 2>nul";
            $output = [];
            exec($cmd, $output);

            if (!empty($output)) {
                $found_full_path = trim($output[0]); // Take the first match
                if (file_exists($found_full_path)) {
                    // Try to preserve the original intended path structure locally
                    $local_file_to_save = $current_root . $clean_path;
                    // If the found file has a different extension, adjust local path
                    $found_ext = pathinfo($found_full_path, PATHINFO_EXTENSION);
                    if ($found_ext !== $ext) {
                        $local_file_to_save = $current_root . dirname($clean_path) . '/' . pathinfo($found_full_path, PATHINFO_BASENAME);
                    }

                    $dir = dirname($local_file_to_save);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    if (copy($found_full_path, $local_file_to_save)) {
                        echo "Recovered (global search): " . $clean_path . " (found as " . pathinfo($found_full_path, PATHINFO_BASENAME) . " in " . $found_full_path . ")\n";
                        $recovered++;
                        $found_in_archive = true;
                        break;
                    }
                }
            }
        }
    }

    if (!$found_in_archive) {
        echo "Still missing: $path\n";
        $missing++;
    }
}

echo "\nDone!\n";
echo "Found locally: $found\n";
echo "Recovered from archive: $recovered\n";
echo "Still missing: $missing\n";
