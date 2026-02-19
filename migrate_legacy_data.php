<?php
/**
 * TikTak.by — Legacy Data Migration Script
 *
 * PURPOSE:
 *   Phase 1: Migrate image paths in `dop_photos` and `tovar_list` DB tables
 *            from legacy root-relative paths to standard /public/storage/images/ paths.
 *            Physical image files are moved accordingly.
 *   Phase 2: Archive all legacy root-level folders/files (not part of Laravel)
 *            by moving them to a `/to_delete/` directory, preserving their structure.
 *
 * All actions are logged to /to_delete/migration_log.txt
 *
 * USAGE:
 *   Dry-Run (default, safe, no changes made):
 *     php migrate_legacy_data.php
 *     -- or --
 *     http://your-domain.com/migrate_legacy_data.php
 *
 *   Live Run (ACTUALLY moves files and updates the DB):
 *     php migrate_legacy_data.php --live
 *     -- or --
 *     http://your-domain.com/migrate_legacy_data.php?mode=live
 *
 * SECURITY: Requires SECRET_KEY to run from browser.
 */

// ============================================================
// CONFIGURATION
// ============================================================

// Secret key to prevent unauthorized browser-based execution.
// Change this before deploying!
define('SECRET_KEY', 'tiktak_migrate_2026_SECRET');

// DB Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tiktakby_2026_v1');

// Project root (auto-detected based on this script's location)
define('PROJECT_ROOT', rtrim(str_replace('\\', '/', __DIR__), '/'));

// Destination for migrated images (web-accessible path)
define('IMAGE_DEST_WEB', '/public/storage/images');

// Physical path for migrated images
define('IMAGE_DEST_PATH', PROJECT_ROOT . '/public/storage/images');

// Archive directory for deprecated legacy files
define('ARCHIVE_DIR', PROJECT_ROOT . '/to_delete');

// Log file path
define('LOG_FILE', ARCHIVE_DIR . '/migration_log.txt');

// ============================================================
// SECURITY CHECK (browser execution)
// ============================================================
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    $key = $_GET['key'] ?? '';
    if ($key !== SECRET_KEY) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Missing or invalid secret key.</p>');
    }
}

// ============================================================
// DETERMINE RUN MODE
// ============================================================
$mode = 'dry-run';
if ($is_cli && in_array('--live', $argv ?? [])) {
    $mode = 'live';
} elseif (!$is_cli && ($_GET['mode'] ?? '') === 'live') {
    $mode = 'live';
}

$IS_LIVE = ($mode === 'live');

// ============================================================
// SETUP AND HELPERS
// ============================================================

// Ensure archive directory exists
if ($IS_LIVE && !is_dir(ARCHIVE_DIR)) {
    mkdir(ARCHIVE_DIR, 0755, true);
}

/**
 * Write a line to the log file and output it.
 */
function log_action(string $message, bool $is_live): void
{
    $prefix = $is_live ? '[LIVE]     ' : '[DRY-RUN]  ';
    $line = '[' . date('Y-m-d H:i:s') . "] {$prefix}{$message}";
    echo $line . PHP_EOL;

    if ($is_live) {
        // Ensure log directory exists before first write
        if (!is_dir(ARCHIVE_DIR)) {
            mkdir(ARCHIVE_DIR, 0755, true);
        }
        file_put_contents(LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Safely move a file, creating destination directories as needed.
 * Returns true on success (or in dry-run), false on failure.
 */
function safe_move_file(string $source, string $dest, bool $is_live): bool
{
    if (!file_exists($source)) {
        log_action("SKIP (not found): {$source}", $is_live);
        return false;
    }

    $dest_dir = dirname($dest);
    if ($is_live) {
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }
        if (rename($source, $dest)) {
            log_action("MOVED FILE: {$source} -> {$dest}", $is_live);
            return true;
        } else {
            log_action("ERROR moving: {$source} -> {$dest}", $is_live);
            return false;
        }
    } else {
        log_action("WOULD MOVE FILE: {$source} -> {$dest}", $is_live);
        return true;
    }
}

/**
 * Recursively move an entire directory to a new location.
 */
function safe_move_dir(string $source_dir, string $dest_dir, bool $is_live): void
{
    if (!is_dir($source_dir)) {
        log_action("SKIP DIR (not found): {$source_dir}", $is_live);
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    // First create directory structure
    if ($is_live && !is_dir($dest_dir)) {
        mkdir($dest_dir, 0755, true);
    }

    $files_to_move = [];
    $dirs_to_create = [];

    foreach ($items as $item) {
        $rel_path = str_replace(str_replace('\\', '/', $source_dir), '', str_replace('\\', '/', $item->getPathname()));
        $dest_path = $dest_dir . $rel_path;
        if ($item->isDir()) {
            $dirs_to_create[] = $dest_path;
        } else {
            $files_to_move[] = [$item->getPathname(), $dest_path];
        }
    }

    if ($is_live) {
        foreach ($dirs_to_create as $d) {
            if (!is_dir($d))
                mkdir($d, 0755, true);
        }
    }
    foreach ($files_to_move as [$src, $dst]) {
        safe_move_file(str_replace('\\', '/', $src), str_replace('\\', '/', $dst), $is_live);
    }

    // Remove source dir after moving all files (live only)
    if ($is_live && is_dir($source_dir)) {
        // Only remove if now empty
        $remaining = array_diff(scandir($source_dir), ['.', '..']);
        if (empty($remaining)) {
            rmdir($source_dir);
            log_action("REMOVED empty dir: {$source_dir}", $is_live);
        } else {
            log_action("NOTE: Source dir not empty after move (files may still remain): {$source_dir}", $is_live);
        }
    }
}

// ============================================================
// DB CONNECTION
// ============================================================
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("DB Connection Error: " . $mysqli->connect_error . PHP_EOL);
}
$mysqli->set_charset('utf8mb4');

// ============================================================
// OUTPUT HEADER
// ============================================================
if (!$is_cli) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo str_repeat('=', 70) . PHP_EOL;
echo "TikTak.by — Legacy Data Migration Script" . PHP_EOL;
echo "Mode: " . strtoupper($mode) . PHP_EOL;
echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "Project Root: " . PROJECT_ROOT . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL . PHP_EOL;

// ============================================================
// PHASE 1: MIGRATE dop_photos IMAGE PATHS
// ============================================================
echo PHP_EOL . str_repeat('-', 60) . PHP_EOL;
echo "PHASE 1A: Migrating dop_photos image paths" . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

$result = $mysqli->query("SELECT dop_id, model_id, src FROM dop_photos ORDER BY dop_id");
$dop_updated = 0;
$dop_skipped = 0;

while ($row = $result->fetch_assoc()) {
    $dop_id = $row['dop_id'];
    $model_id = $row['model_id'];
    $src_old = $row['src'];

    // Skip if already migrated (already points to /public/storage or is external URL)
    if (
        strpos($src_old, IMAGE_DEST_WEB) === 0 ||
        strpos($src_old, 'http://') === 0 ||
        strpos($src_old, 'https://') === 0
    ) {
        log_action("SKIP dop_photos ID={$dop_id}: already migrated or external — '{$src_old}'", $IS_LIVE);
        $dop_skipped++;
        continue;
    }

    // Derive category from path: /shezlongi/img/file.jpg -> shezlongi
    $path_parts = explode('/', trim($src_old, '/'));
    $category = $path_parts[0] ?? 'uncategorized';
    $filename = basename($src_old);

    // Physical source path
    $source_physical = PROJECT_ROOT . $src_old;

    // New destination
    $dest_web = IMAGE_DEST_WEB . '/' . $category . '/' . $filename;
    $dest_physical = IMAGE_DEST_PATH . '/' . $category . '/' . $filename;

    // Move the file
    safe_move_file($source_physical, $dest_physical, $IS_LIVE);

    // Update the DB
    $dest_web_esc = $mysqli->real_escape_string($dest_web);
    $src_old_esc = $mysqli->real_escape_string($src_old);
    log_action("DB_UPDATE: dop_photos, ID={$dop_id} | src: '{$src_old}' -> '{$dest_web}'", $IS_LIVE);

    if ($IS_LIVE) {
        $mysqli->query("UPDATE dop_photos SET src='{$dest_web_esc}' WHERE dop_id='{$dop_id}'");
    }
    $dop_updated++;
}

echo PHP_EOL . "dop_photos: {$dop_updated} records updated, {$dop_skipped} skipped." . PHP_EOL;

// ============================================================
// PHASE 1B: MIGRATE tovar_list IMAGE PATHS
// ============================================================
echo PHP_EOL . str_repeat('-', 60) . PHP_EOL;
echo "PHASE 1B: Migrating tovar_list image paths" . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

// tovar_list stores bare filenames (e.g., 'sling_ilya.jpg').
// We need to find them on the filesystem. They live inside category/img/ or category/ folders.
// We determine the category from tovar_page_url: e.g., /slingy/ -> slingi/ (may differ).
// Strategy: scan all root-level dirs that contain an img/ subfolder and build a filename map.

// Build a map: filename => [possible_physical_paths]
$filename_map = [];
$root_items = scandir(PROJECT_ROOT);
foreach ($root_items as $item) {
    if ($item === '.' || $item === '..')
        continue;
    $item_path = PROJECT_ROOT . '/' . $item;
    if (!is_dir($item_path))
        continue;
    // Skip Laravel/system/known-good dirs
    $skip_dirs = ['app', 'bootstrap', 'config', 'database', 'node_modules', 'public', 'resources', 'routes', 'storage', 'tests', 'vendor', 'bb', 'to_delete', '.git', '.agent', '.gemini'];
    if (in_array($item, $skip_dirs))
        continue;

    // Check subdirectories for images
    $sub_dirs = [$item_path, $item_path . '/img'];
    foreach ($sub_dirs as $scan_dir) {
        if (!is_dir($scan_dir))
            continue;
        $files = scandir($scan_dir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..')
                continue;
            $full = $scan_dir . '/' . $f;
            if (is_file($full) && preg_match('/\.(jpe?g|png|gif|webp|avif|svg)$/i', $f)) {
                $filename_map[$f][] = str_replace('\\', '/', $full);
            }
        }
    }
}

$tovar_result = $mysqli->query("SELECT tovar_id, main_file_name, middle_file_name, w_icon, producer_logo FROM tovar_list");
$tovar_updated = 0;
$tovar_skipped = 0;

// Image columns and their DB field names
$image_columns = ['main_file_name', 'middle_file_name', 'w_icon', 'producer_logo'];

while ($row = $tovar_result->fetch_assoc()) {
    $tovar_id = $row['tovar_id'];
    $updates = [];

    foreach ($image_columns as $col) {
        $filename = $row[$col] ?? '';
        if (empty($filename))
            continue;

        // Skip if it already looks like a full path
        if (strpos($filename, '/') !== false) {
            log_action("SKIP tovar_list ID={$tovar_id} col={$col}: value already has path — '{$filename}'", $IS_LIVE);
            $tovar_skipped++;
            continue;
        }

        // Find the file on the filesystem
        $found_paths = $filename_map[$filename] ?? [];

        if (empty($found_paths)) {
            log_action("NOT FOUND tovar_list ID={$tovar_id} col={$col}: file '{$filename}' not found on filesystem", $IS_LIVE);
            $tovar_skipped++;
            continue;
        }

        // Use the first found path; log if multiple
        if (count($found_paths) > 1) {
            log_action("WARN tovar_list ID={$tovar_id} col={$col}: multiple files found for '{$filename}', using first: " . implode(', ', $found_paths), $IS_LIVE);
        }

        $source_physical = $found_paths[0];

        // Derive category from the physical path relative to project root
        $rel = str_replace(PROJECT_ROOT . '/', '', $source_physical);
        $rel_parts = explode('/', $rel);
        $category = $rel_parts[0];

        $dest_web = IMAGE_DEST_WEB . '/' . $category . '/' . $filename;
        $dest_physical = IMAGE_DEST_PATH . '/' . $category . '/' . $filename;

        safe_move_file($source_physical, $dest_physical, $IS_LIVE);

        log_action("DB_UPDATE: tovar_list, ID={$tovar_id}, col={$col} | '{$filename}' -> '{$dest_web}'", $IS_LIVE);
        $updates[$col] = $dest_web;
        $tovar_updated++;
    }

    if (!empty($updates) && $IS_LIVE) {
        $set_parts = [];
        foreach ($updates as $col => $new_val) {
            $new_val_esc = $mysqli->real_escape_string($new_val);
            $set_parts[] = "`{$col}`='{$new_val_esc}'";
        }
        $mysqli->query("UPDATE tovar_list SET " . implode(', ', $set_parts) . " WHERE tovar_id='{$tovar_id}'");
    }
}

echo PHP_EOL . "tovar_list: {$tovar_updated} fields updated, {$tovar_skipped} skipped." . PHP_EOL;

// ============================================================
// PHASE 2: ARCHIVE LEGACY ROOT FILES AND FOLDERS
// ============================================================
echo PHP_EOL . str_repeat('-', 60) . PHP_EOL;
echo "PHASE 2: Archiving legacy root files and folders" . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

// Definitive list of legacy root-level folders to archive
// (these are old static site folders, not images which are handled in Phase 1)
$legacy_folders = [
    'about',
    'assets',
    'avtokresla',
    'batuty',
    'bigs',
    'compleksy',
    'fancybox',
    'fonts',
    'gorki',
    'hodunki',
    'igrushki',
    'images',
    'includes',
    'jstr',
    'js',
    'kacheli',
    'kacheli_napol',
    'karnaval',
    'kolyaski',
    'kolybeli',
    'kovriki',
    'lakatory',
    'manezhi',
    'media',
    'nochniki',
    'odezhda_dlia_photosessii',
    'player',
    'portfolio',
    'prokat',
    'prygunki',
    'shezlongi',
    'slingi',
    'stoliki',
    'stul',
    'svg',
    'tcpdf',
    'tmp',
    'to_copy',
    'toys',
    'transport',
    'uvlazhniteli',
    'velo',
    'vesy',
    'video',
    'webstat',
    'Templates',
];

// Definitive list of legacy root-level files to archive
$legacy_files = [
    // Old static HTM pages
    'balls.htm',
    'biggs.htm',
    'bigs.htm',
    'dostavka.htm',
    'o_nas.htm',
    'paneli.htm',
    'paneli.html',
    'search.htm',
    'stoliki.htm',
    'transport.htm',
    'index2.html',
    'test.html',
    'test copy.html',
    // Superseded PHP scripts (legacy order / deal views now in BB)
    'zakaz2.php',
    'old_bron.php',
    'dogovor_new2.php',
    'rent_deals_all.php',
    'rent_orders.php',
    'rent_orders_arch.php',
    'dohrash2.php',
    // Developer / debug scripts
    'dimanay.php',
    'dimanay2.php',
    'reproduce_issue.php',
    'test.php',
    'convert_images.php',
    // Various loose files
    'DD_belatedPNG.js',
    'swfobject.js',
    'stl.css',
    'dcfloater.css',
    'tt.css',
    'tt_karn.css',
    'tt_karn2.css',
    'tt_old.css',
    'app.blade.php',
    'nd_v.rtf',
    'nd_v copy.rtf',
    'analytics.txt',
    'test_time.txt',
    'production_update.sql',
    'update_prices.sql',
    'update_prices_byn.sql',
    'sitemap.xml',
    // Google/Yandex verification files
    'google524a38840591e81d.html',
    'google6f726f9664274105.html',
    'googleb24a612b5986870b.html',
    'y_key_e73e5476952ef0ec.html',
    'yandex_7348006d2698d8b8.html',
    'B6CFLN5ETuT1kgUuEn7UdeJIxYQ.txt',
    'MJ12_7e9e2d41-1b26-4497-ac61-5bc916ff0169.txt',
    'LiveSearchSiteAuth.xml',
    // Misc
    'DSC_3185.JPG',
    '__2013-11-10  12.24.12.png',
    'Gilroy-Light.otf',
    'tiktak.ico',
    'action_vesy_and_kacheli_cheap.jpg',
    '.DS_Store',
];

$archived_items = 0;

// Archive folders
foreach ($legacy_folders as $folder) {
    $source = PROJECT_ROOT . '/' . $folder;
    $dest = ARCHIVE_DIR . '/' . $folder;
    if (is_dir($source)) {
        log_action("ARCHIVING FOLDER: {$source} -> {$dest}", $IS_LIVE);
        safe_move_dir($source, $dest, $IS_LIVE);
        $archived_items++;
    } else {
        log_action("SKIP FOLDER (not present in this env): {$folder}", $IS_LIVE);
    }
}

// Archive individual files
foreach ($legacy_files as $file) {
    $source = PROJECT_ROOT . '/' . $file;
    $dest = ARCHIVE_DIR . '/' . $file;
    if (file_exists($source)) {
        safe_move_file($source, $dest, $IS_LIVE);
        $archived_items++;
    } else {
        log_action("SKIP FILE (not present in this env): {$file}", $IS_LIVE);
    }
}

echo PHP_EOL . "Phase 2: {$archived_items} items processed for archival." . PHP_EOL;

// ============================================================
// SUMMARY
// ============================================================
echo PHP_EOL . str_repeat('=', 70) . PHP_EOL;
echo "MIGRATION COMPLETE" . PHP_EOL;
echo "Mode: " . strtoupper($mode) . PHP_EOL;
if ($IS_LIVE) {
    echo "Log written to: " . LOG_FILE . PHP_EOL;
} else {
    echo "*** DRY-RUN ONLY — No files were moved, no DB records changed. ***" . PHP_EOL;
    echo "*** Re-run with --live (CLI) or ?mode=live&key=SECRET (browser) to apply. ***" . PHP_EOL;
}
echo str_repeat('=', 70) . PHP_EOL;

$mysqli->close();
