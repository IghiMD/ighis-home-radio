<?php
/**
 * Cleanup utility for Ighi's Home Radio
 */

require_once 'config.php';
require_once 'auth.php';

// Require authentication
Auth::require();

$report = [];

// 1. Clean old cache files (older than 7 days)
$cacheAge = 7 * 24 * 60 * 60; // 7 days
$cleaned = 0;

if (is_dir(CACHE_DIR)) {
    $files = glob(CACHE_DIR . '*.json');
    foreach ($files as $file) {
        if (time() - filemtime($file) > $cacheAge) {
            if (unlink($file)) $cleaned++;
        }
    }
}
$report['cache_cleaned'] = $cleaned;

// 2. Trim log file if too large (keep last 1000 lines)
if (file_exists(LOG_FILE) && filesize(LOG_FILE) > 1048576) { // 1MB
    $lines = file(LOG_FILE);
    $lines = array_slice($lines, -1000);
    file_put_contents(LOG_FILE, implode('', $lines));
    $report['log_trimmed'] = true;
}

// 3. Clean old backups (keep last 7 days)
if (is_dir(BACKUP_DIR)) {
    $files = glob(BACKUP_DIR . '*.tar.gz');
    $oldBackups = 0;
    foreach ($files as $file) {
        if (time() - filemtime($file) > 7 * 24 * 60 * 60) {
            if (unlink($file)) $oldBackups++;
        }
    }
    $report['old_backups_removed'] = $oldBackups;
}

// Log cleanup
$logMessage = "[" . date('Y-m-d H:i:s') . "] [CLEANUP] System cleanup completed - " . json_encode($report) . "\n";
file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);

// Output
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'report' => $report,
    'timestamp' => time()
]);
?>