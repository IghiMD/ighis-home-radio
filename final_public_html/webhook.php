<?php
/**
 * Main Webhook for Ighi's Home Radio
 * Handles track updates and provides API endpoints
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Token');

// Handle OPTIONS for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'current';
    
    switch ($action) {
        case 'current':
            // Get current song
            if (file_exists(CURRENT_SONG_FILE)) {
                echo file_get_contents(CURRENT_SONG_FILE);
            } else {
                echo json_encode([
                    'status' => 'no_data',
                    'message' => 'No current song data',
                    'timestamp' => time()
                ]);
            }
            break;
            
        case 'history':
            // Get song history
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            $limit = min(max($limit, 1), 100);
            
            if (file_exists(HISTORY_FILE)) {
                $history = json_decode(file_get_contents(HISTORY_FILE), true);
                $history = array_slice($history, 0, $limit);
                
                echo json_encode([
                    'status' => 'success',
                    'count' => count($history),
                    'limit' => $limit,
                    'history' => $history
                ]);
            } else {
                echo json_encode([
                    'status' => 'no_data',
                    'message' => 'No history available',
                    'history' => []
                ]);
            }
            break;
            
        case 'health':
            // Health check
            echo json_encode([
                'status' => 'online',
                'timestamp' => time(),
                'server_time' => date('Y-m-d H:i:s'),
                'version' => '2.0',
                'stream_url' => STREAM_URL,
                'features' => [
                    'icy_metadata' => true,
                    'enrichment' => true,
                    'ai_info' => isServiceEnabled('perplexity') || isServiceEnabled('openai') || isServiceEnabled('gemini'),
                    'history' => true
                ]
            ]);
            break;
            
        case 'stats':
            // Basic statistics
            $history = file_exists(HISTORY_FILE) ? json_decode(file_get_contents(HISTORY_FILE), true) : [];
            $current = file_exists(CURRENT_SONG_FILE) ? json_decode(file_get_contents(CURRENT_SONG_FILE), true) : null;
            
            $stats = [
                'total_songs_played' => count($history),
                'unique_artists' => count(array_unique(array_column(array_column($history, 'data'), 'artist'))),
                'unique_tracks' => count(array_unique(array_map(function($h) {
                    return $h['data']['artist'] . ' - ' . $h['data']['title'];
                }, $history))),
                'last_update' => $current ? $current['timestamp'] : null,
                'uptime_hours' => file_exists(LOG_FILE) ? round((time() - filectime(LOG_FILE)) / 3600, 1) : 0
            ];
            
            echo json_encode([
                'status' => 'success',
                'stats' => $stats,
                'timestamp' => time()
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action', 'available_actions' => ['current', 'history', 'health', 'stats']]);
    }
    exit();
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['track'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data - track information required']);
        exit();
    }
    
    // Prepare data
    $trackData = $input['track'];
    $data = [
        'timestamp' => time(),
        'datetime' => date('Y-m-d H:i:s'),
        'source' => $input['source'] ?? 'unknown',
        'data' => $trackData
    ];
    
    // Ensure directories exist
    foreach ([DATA_DIR, LOGS_DIR, CACHE_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // Save current song
    file_put_contents(CURRENT_SONG_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Update history
    $history = [];
    if (file_exists(HISTORY_FILE)) {
        $history = json_decode(file_get_contents(HISTORY_FILE), true) ?: [];
    }
    
    // Check if it's a new song (not duplicate)
    $isNewSong = true;
    if (!empty($history) && isset($history[0]['data'])) {
        $lastSong = $history[0]['data'];
        if ($lastSong['artist'] === $trackData['artist'] && $lastSong['title'] === $trackData['title']) {
            $isNewSong = false;
        }
    }
    
    if ($isNewSong) {
        array_unshift($history, $data);
        $history = array_slice($history, 0, 500); // Keep last 500 songs
        file_put_contents(HISTORY_FILE, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    // Log the update
    $logMessage = sprintf(
        "[%s] [TRACK] %s - %s - %s (Source: %s)\n",
        date('Y-m-d H:i:s'),
        $trackData['artist'] ?? 'Unknown',
        $trackData['title'] ?? 'Unknown',
        $isNewSong ? 'NEW' : 'UPDATE',
        $input['source'] ?? 'unknown'
    );
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Return response
    echo json_encode([
        'success' => true,
        'message' => $isNewSong ? 'New track saved' : 'Track updated',
        'track' => [
            'artist' => $trackData['artist'],
            'title' => $trackData['title']
        ],
        'timestamp' => time()
    ]);
}
?>