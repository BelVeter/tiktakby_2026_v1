<?php

/**
 * recover_missing_images.php
 *
 * Scans the `rent_model_web` table for missing image files (l2_pic, m_pic_big, logo).
 * For each missing file, searches for it in the `/to_delete/` folder.
 * If found, moves it to the correct `/public/rent/images/` location
 * and updates the database record accordingly.
 *
 * Usage:
 *   Dry-run (no changes):  php recover_missing_images.php
 *   Live run (with changes): php recover_missing_images.php --live
 */

require_once 'bb/Db.php';

define('PROJECT_ROOT', rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__FILE__), '/'));
define('TO_DELETE_DIR', PROJECT_ROOT . '/to_delete');
define('TARGET_BASE', PROJECT_ROOT . '/public/rent/images');

$IS_LIVE = in_array('--live', $argv ?? []);
$mode_label = $IS_LIVE ? '[LIVE]' : '[DRY-RUN]';

$mysqli = \bb\Db::getInstance()->getConnection();

// ============================================================
// LOGGING
// ============================================================
$log_dir = PROJECT_ROOT . '/to_delete';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_path = $log_dir . '/recover_log.txt';
$log_lines = [];

function log_it(string $msg): void
{
    global $log_lines;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line . "\n";
    $log_lines[] = $line;
}

// ============================================================
// HELPER: Given a DB path (possibly already prefixed with
// /public/rent/images), compute the source and destination.
// ============================================================
function resolve_paths(string $db_path): array
{
    // The destination on disk is always: PROJECT_ROOT . $db_path
    $dest_disk = PROJECT_ROOT . $db_path;

    // To find the file in to_delete, strip /public/rent/images prefix if present,
    // then prepend /to_delete/
    $stripped = $db_path;
    if (strpos($stripped, '/public/rent/images') === 0) {
        $stripped = substr($stripped, strlen('/public/rent/images'));
    }

    // Also the file might still be in the root (if it wasn't in the archival list)
    $original_disk = PROJECT_ROOT . $stripped;
    $search_in_to_delete = TO_DELETE_DIR . $stripped;

    return [
        'dest_db' => $db_path,
        'dest_disk' => $dest_disk,
        'to_delete_disk' => $search_in_to_delete,
        'original_disk' => $original_disk,
    ];
}

// ============================================================
// HELPER: Move a file (with dry-run support)
// ============================================================
function move_file(string $src, string $dest, bool $is_live): bool
{
    if (!file_exists($src)) {
        log_it("  NOT FOUND: $src");
        return false;
    }

    $dest_dir = dirname($dest);
    if ($is_live) {
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }
        if (rename($src, $dest)) {
            log_it("  MOVED: $src -> $dest");
            return true;
        } else {
            log_it("  ERROR: Failed to move $src -> $dest");
            return false;
        }
    } else {
        log_it("  WOULD MOVE: $src -> $dest");
        return true; // simulate success in dry-run
    }
}

// ============================================================
// MAIN
// ============================================================
log_it("==================================================================");
log_it("recover_missing_images.php - Mode: " . ($IS_LIVE ? 'LIVE' : 'DRY-RUN'));
log_it("==================================================================");

$target_fields = ['l2_pic', 'm_pic_big', 'logo'];

$res = $mysqli->query("SELECT web_id, l2_pic, m_pic_big, logo FROM rent_model_web");
if (!$res) {
    die("DB error: " . $mysqli->error);
}

$scanned = 0;
$already_ok = 0;
$recovered = 0;
$not_found = 0;
$skipped_empty = 0;
$db_updates = 0;

while ($row = $res->fetch_assoc()) {
    $scanned++;
    $web_id = $row['web_id'];

    foreach ($target_fields as $field) {
        $db_path = $row[$field];

        // Skip empty or null
        if (empty($db_path)) {
            $skipped_empty++;
            continue;
        }

        $paths = resolve_paths($db_path);
        $dest_disk = $paths['dest_disk'];

        // 1. File already exists at the correct location — all good
        if (file_exists($dest_disk)) {
            $already_ok++;
            continue;
        }

        // 2. File missing — time to search
        log_it("MISSING  web_id=$web_id [$field]: $db_path");

        // Strategy A: Check if it's in the project root (was never moved to to_delete)
        if (move_file($paths['original_disk'], $dest_disk, $IS_LIVE)) {
            $recovered++;
            continue;
        }

        // Strategy B: Check if it's in to_delete (was moved by migrate_legacy_data's Phase 2)
        if (move_file($paths['to_delete_disk'], $dest_disk, $IS_LIVE)) {
            $recovered++;
            continue;
        }

        // Strategy C: Recursive search in to_delete as a last resort
        $filename = basename($db_path);
        log_it("  Trying recursive search for: $filename");

        // Disable recursive search inside the main loop for performance 
        // if we are searching thousands of files, but we keep it here for fallback
        $found_path = find_file_recursive(TO_DELETE_DIR, $filename);

        if ($found_path) {
            log_it("  Found at: $found_path");
            if (move_file($found_path, $dest_disk, $IS_LIVE)) {
                $recovered++;
            } else {
                $not_found++;
            }
        } else {
            log_it("  NOT FOUND anywhere — skipping");
            $not_found++;
        }
    }
}

// ============================================================
// HELPER: Recursive file search
// ============================================================
function find_file_recursive(string $dir, string $filename): ?string
{
    if (!is_dir($dir))
        return null;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..')
            continue;
        $full_path = $dir . '/' . $item;
        if (is_dir($full_path)) {
            $found = find_file_recursive($full_path, $filename);
            if ($found !== null)
                return $found;
        } elseif ($item === $filename) {
            return $full_path;
        }
    }
    return null;
}

// ============================================================
// SUMMARY
// ============================================================
log_it("==================================================================");
log_it("DONE. Mode: " . ($IS_LIVE ? 'LIVE' : 'DRY-RUN'));
log_it("  Rows scanned:      $scanned");
log_it("  Fields already OK: $already_ok");
log_it("  Recovered:         $recovered (files moved to correct location)");
log_it("  Not found:         $not_found");
log_it("  Skipped (empty):   $skipped_empty");
log_it("==================================================================");

// Write log
file_put_contents($log_path, implode("\n", $log_lines) . "\n", FILE_APPEND | LOCK_EX);
echo "\nLog written to: $log_path\n";
