<?php
/**
 * Clear Cache Utility for Ighi's Home Radio
 */

require_once 'config.php';
require_once 'auth.php';

// Require authentication
Auth::require();

header('Content-Type: application/json');

$cleared = 0;
$errors = [];

// Clear cache directory
if (is_dir(CACHE_DIR)) {
    $files = glob(CACHE_DIR . '*.json');
    foreach ($files as $file) {
        if (unlink($file)) {
            $cleared++;
        } else {
            $errors[] = basename($file);
        }
    }
}

// Clear AI cache
if (is_dir(AI_CACHE_DIR)) {
    $files = glob(AI_CACHE_DIR . '*.json');
    foreach ($files as $file) {
        if (unlink($file)) {
            $cleared++;
        } else {
            $errors[] = basename($file);
        }
    }
}

// Log the action
$logMessage = "[" . date('Y-m-d H:i:s') . "] [CACHE] Cleared by " . Auth::getCurrentUser()['username'] . " - Files: $cleared\n";
file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => "Cache cleared successfully",
    'files_cleared' => $cleared,
    'errors' => $errors,
    'timestamp' => time()
]);
?>