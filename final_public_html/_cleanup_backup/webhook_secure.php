<?php
/**
 * Secure Webhook for Ighi's Home Radio
 * Requires authentication for sensitive operations
 */

require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Token');

// Check authentication for sensitive actions
$action = $_GET['action'] ?? 'current';
$requiresAuth = in_array($action, ['stats', 'full_stats', 'admin_info']);

if ($requiresAuth) {
    Auth::require();
}

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'current':
            // Current song (public)
            $currentFile = CURRENT_SONG_FILE;
            if (file_exists($currentFile)) {
                echo file_get_contents($currentFile);
            } else {
                echo json_encode([
                    'status' => 'no_data',
                    'message' => 'No current song data'
                ]);
            }
            break;
            
        case 'health':
            // Health check (public)
            echo json_encode([
                'status' => 'online',
                'timestamp' => time(),
                'version' => '2.0',
                'stream_url' => STREAM_URL
            ]);
            break;
            
        case 'stats':
            // Basic stats (requires auth)
            $history = file_exists(HISTORY_FILE) ? json_decode(file_get_contents(HISTORY_FILE), true) : [];
            echo json_encode([
                'total_songs' => count($history),
                'last_update' => file_exists(CURRENT_SONG_FILE) ? filemtime(CURRENT_SONG_FILE) : null,
                'cache_size' => is_dir(CACHE_DIR) ? count(glob(CACHE_DIR . '*.json')) : 0
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify token for write operations
    $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if ($token !== ADMIN_TOKEN) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input && isset($input['track'])) {
        // Save track data
        $data = [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'source' => $input['source'] ?? 'unknown',
            'data' => $input['track']
        ];
        
        // Ensure directory exists
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        
        // Save current song
        file_put_contents(CURRENT_SONG_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Update history
        $history = file_exists(HISTORY_FILE) ? json_decode(file_get_contents(HISTORY_FILE), true) : [];
        array_unshift($history, $data);
        $history = array_slice($history, 0, 100); // Keep last 100 songs
        file_put_contents(HISTORY_FILE, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Log
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [WEBHOOK] Track updated: {$input['track']['artist']} - {$input['track']['title']}\n";
        file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
        
        echo json_encode(['success' => true, 'message' => 'Track data saved']);
    } else {
        echo json_encode(['error' => 'Invalid data']);
    }
    exit();
}
?>