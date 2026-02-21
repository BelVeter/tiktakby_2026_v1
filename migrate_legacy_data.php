<?php
/**
 * TikTak.by — Legacy Data Migration Script
 *
 * PURPOSE:
 *   Phase 1: Migrate image paths in `dop_photos` and `tovar_list` DB tables
 *            from legacy root-relative paths to /public/rent/images/ paths.
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

// --------------- DB credentials from .env ------------------
// Reads DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
// from the project .env — works on both local and production without edits.
$_env_file = __DIR__ . '/.env';
if (!file_exists($_env_file)) {
    die("ERROR: .env file not found at {$_env_file}" . PHP_EOL);
}
$_env = [];
foreach (file($_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
    $_line = trim($_line);
    if ($_line === '' || $_line[0] === '#')
        continue;  // skip comments
    if (strpos($_line, '=') === false)
        continue;
    [$_k, $_v] = explode('=', $_line, 2);
    $_env[trim($_k)] = trim($_v, " \t\n\r\0\x0B\"'");
}

define('DB_HOST', $_env['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', (int) ($_env['DB_PORT'] ?? 3306));
define('DB_USER', $_env['DB_USERNAME'] ?? '');
define('DB_PASS', $_env['DB_PASSWORD'] ?? '');
define('DB_NAME', $_env['DB_DATABASE'] ?? '');
unset($_env, $_env_file, $_line, $_k, $_v);

if (DB_NAME === '') {
    die("ERROR: DB_DATABASE not found in .env" . PHP_EOL);
}
// -----------------------------------------------------------

// Project root (auto-detected based on this script's location)
define('PROJECT_ROOT', rtrim(str_replace('\\', '/', __DIR__), '/'));

// Destination for migrated images (web-accessible path)
define('IMAGE_DEST_WEB', '/public/rent/images');

// Physical path for migrated images
define('IMAGE_DEST_PATH', PROJECT_ROOT . '/public/rent/images');

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

// Ensure archive directory exists for both dry-run (log) and live modes
if (!is_dir(ARCHIVE_DIR)) {
    mkdir(ARCHIVE_DIR, 0755, true);
}

/**
 * Write a line to the log file and output it.
 * Logs are ALWAYS appended — never overwritten — for full idempotency.
 * Both dry-run and live runs are logged so you can track all executions.
 */
function log_action(string $message, bool $is_live): void
{
    $prefix = $is_live ? '[LIVE]     ' : '[DRY-RUN]  ';
    $line = '[' . date('Y-m-d H:i:s') . "] {$prefix}{$message}";
    echo $line . PHP_EOL;

    // Always append to log (dry-run runs are also recorded for auditability)
    file_put_contents(LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
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
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($mysqli->connect_error) {
    die("DB Connection Error: " . $mysqli->connect_error . PHP_EOL);
}
$mysqli->set_charset('utf8mb4');

// ============================================================
// OUTPUT HEADER + SESSION LOG MARKER
// ============================================================
if (!$is_cli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$session_header = PHP_EOL
    . str_repeat('#', 70) . PHP_EOL
    . '# SESSION START: ' . date('Y-m-d H:i:s') . PHP_EOL
    . '# Mode: ' . strtoupper($mode) . PHP_EOL
    . '# Project Root: ' . PROJECT_ROOT . PHP_EOL
    . str_repeat('#', 70);

// Write session header to the log file (always appended)
file_put_contents(LOG_FILE, $session_header . PHP_EOL, FILE_APPEND | LOCK_EX);

echo str_repeat('=', 70) . PHP_EOL;
echo "TikTak.by — Legacy Data Migration Script" . PHP_EOL;
echo "Mode: " . strtoupper($mode) . PHP_EOL;
echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "Project Root: " . PROJECT_ROOT . PHP_EOL;
echo "Log file: " . LOG_FILE . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL . PHP_EOL;

// ============================================================
// PHASE 0: SCAN CODE FOR HARDCODED LEGACY IMAGE REFERENCES
// ============================================================
// This phase is READ-ONLY — it never moves files or changes the DB.
// It scans PHP, Blade, CSS, JS, and XML files in live code directories
// for any hardcoded paths pointing to legacy root-level image folders.
// Output: to_delete/hardcoded_refs_report.txt
// Purpose: produce a report of code lines that need manual fixing AFTER
//          the migration, so that the image addresses in code match the
//          new /public/rent/images/... convention used in the database.
// ============================================================
echo PHP_EOL . str_repeat('-', 60) . PHP_EOL;
echo "PHASE 0: Scanning code for hardcoded legacy image references" . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

// Regex matching any src="...", url(...), or bare string containing a
// legacy category folder immediately followed by / or /img/
$LEGACY_CATEGORIES = [
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
$cat_pattern = '(' . implode('|', $LEGACY_CATEGORIES) . ')';
// Match paths like /shezlongi/... or shezlongi/img/... or "../avtokresla/...
$HARDCODE_REGEX = '#["\'/](\.\./)?' . $cat_pattern . '/[^"\'>\s]+\.(jpe?g|png|gif|webp|svg|avif)#i';

// Directories and file extensions to scan
$scan_dirs = ['bb', 'resources', 'app', 'public/css', 'public/js'];
$scan_exts = ['php', 'blade.php', 'css', 'js']; // xml/html excluded: auto-generated feeds & legacy pages already being archived

// Subdirectories to skip inside scan targets (generated/vendor/cache)
$skip_scan_dirs = ['vendor', 'node_modules', 'storage', '.git', 'to_delete'];

$REPORT_FILE = ARCHIVE_DIR . '/hardcoded_refs_report.txt';
$report_lines = [];
$hardcode_count = 0;

function scan_dir_for_refs(string $dir, array $exts, array $skip_dirs, string $regex, array &$report, int &$count): void
{
    if (!is_dir($dir))
        return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $file) {
        // Skip excluded subdirectories
        $path = str_replace('\\', '/', $file->getPathname());
        foreach ($skip_dirs as $sd) {
            if (strpos($path, "/{$sd}/") !== false)
                continue 2;
        }
        // Check extension (handle double extension .blade.php)
        $fname = $file->getFilename();
        $matched_ext = false;
        foreach ($exts as $ext) {
            if (substr($fname, -strlen($ext) - 1) === '.' . $ext || $fname === $ext) {
                $matched_ext = true;
                break;
            }
        }
        if (!$matched_ext)
            continue;

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false)
            continue;

        foreach ($lines as $ln => $line) {
            if (preg_match($regex, $line)) {
                $entry = "{$path}:{$ln}: " . trim($line);
                $report[] = $entry;
                $count++;
                echo "  FOUND: {$path} [line " . ($ln + 1) . "]" . PHP_EOL;
            }
        }
    }
}

foreach ($scan_dirs as $sd) {
    scan_dir_for_refs(
        PROJECT_ROOT . '/' . $sd,
        $scan_exts,
        $skip_scan_dirs,
        $HARDCODE_REGEX,
        $report_lines,
        $hardcode_count
    );
}

// Write the report file (always, even in dry-run — it's read-only)
$report_header = str_repeat('#', 70) . PHP_EOL
    . "# HARDCODED LEGACY IMAGE REFERENCE REPORT" . PHP_EOL
    . "# Generated: " . date('Y-m-d H:i:s') . PHP_EOL
    . "# " . PHP_EOL
    . "# These lines in your CODE contain hardcoded paths to legacy" . PHP_EOL
    . "# image folders that will be archived or moved by this script." . PHP_EOL
    . "# They must be updated MANUALLY in the code after migration." . PHP_EOL
    . "# Target format: /public/rent/images/{category}/{filename}" . PHP_EOL
    . "#" . PHP_EOL
    . "# Format: filepath:line_number: matched_line_content" . PHP_EOL
    . str_repeat('#', 70) . PHP_EOL . PHP_EOL;

file_put_contents($REPORT_FILE, $report_header . implode(PHP_EOL, $report_lines) . PHP_EOL, LOCK_EX);

echo PHP_EOL . "Phase 0: {$hardcode_count} hardcoded legacy image reference(s) found." . PHP_EOL;
echo "Report saved to: {$REPORT_FILE}" . PHP_EOL;

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

        // Only update the DB path if the physical file was actually moved (or would be in dry-run)
        $moved = safe_move_file($source_physical, $dest_physical, $IS_LIVE);
        if ($moved) {
            log_action("DB_UPDATE: tovar_list, ID={$tovar_id}, col={$col} | '{$filename}' -> '{$dest_web}'", $IS_LIVE);
            $updates[$col] = $dest_web;
            $tovar_updated++;
        } else {
            log_action("SKIP DB_UPDATE: tovar_list, ID={$tovar_id}, col={$col} — file not moved, DB path unchanged", $IS_LIVE);
            $tovar_skipped++;
        }
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
    // 'assets' removed — used by bb/
    'avtokresla',
    'batuty',
    'bigs',
    'compleksy',
    'fancybox',
    // 'fonts' removed — used by cur_viezdy.php etc
    'gorki',
    'hodunki',
    'igrushki',
    'images',
    // 'includes' removed — contains zv_show.php used by CRM
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
    // 'svg' removed — used by bb/
    // 'tcpdf' removed — required by bb/pdf.php
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
    'old_bron.php',
    // --- The following were removed from archival because bb/*.php actively uses them:
    // 'zakaz2.php', 'dogovor_new2.php', 'rent_deals_all.php', 'rent_orders.php', 'rent_orders_arch.php', 'dohrash2.php'
    // Developer / debug scripts
    'reproduce_issue.php',
    'test.php',
    'convert_images.php',
    // --- 'dimanay.php', 'dimanay2.php' were removed because bb/database.php requires them
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
    // sitemap.xml intentionally excluded — served live by the site
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
// SUMMARY + SESSION LOG FOOTER
// ============================================================
$summary_line = $IS_LIVE
    ? "DONE: dop_photos={$dop_updated} updated/{$dop_skipped} skipped; tovar_list={$tovar_updated} updated/{$tovar_skipped} skipped; archived={$archived_items}"
    : "DRY-RUN ONLY — no changes made.";

$session_footer = str_repeat('#', 70) . PHP_EOL
    . '# SESSION END:   ' . date('Y-m-d H:i:s') . PHP_EOL
    . '# Result: ' . $summary_line . PHP_EOL
    . str_repeat('#', 70) . PHP_EOL;

file_put_contents(LOG_FILE, $session_footer, FILE_APPEND | LOCK_EX);

echo PHP_EOL . str_repeat('=', 70) . PHP_EOL;
echo "MIGRATION COMPLETE" . PHP_EOL;
echo "Mode: " . strtoupper($mode) . PHP_EOL;
echo $summary_line . PHP_EOL;
echo "Log file: " . LOG_FILE . PHP_EOL;
if (!$IS_LIVE) {
    echo PHP_EOL . "*** DRY-RUN ONLY — No files were moved, no DB records changed. ***" . PHP_EOL;
    echo "*** Re-run with --live (CLI) or ?mode=live&key=SECRET (browser) to apply. ***" . PHP_EOL;
}
echo str_repeat('=', 70) . PHP_EOL;

$mysqli->close();
