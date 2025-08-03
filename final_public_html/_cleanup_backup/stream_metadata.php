<?php
/**
 * Advanced Stream Metadata Handler for Ighi's Home Radio
 * 
 * Features:
 * - Multiple stream metadata extraction methods
 * - Icecast/Shoutcast support
 * - Intelligent metadata parsing
 * - Caching and fallback mechanisms
 * - Real-time stream monitoring
 * - Quality assessment
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration
define('STREAM_URL', 'https://fra-pioneer01.dedicateware.com:2415/stream');
define('STATS_URL_SHOUTCAST', 'https://fra-pioneer01.dedicateware.com:2415/stats?json=1');
define('STATS_URL_ICECAST', 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl');
define('ADMIN_URL', 'https://fra-pioneer01.dedicateware.com:2415/admin/');

define('CACHE_DIR', __DIR__ . '/cache/');
define('LOG_FILE', __DIR__ . '/logs/stream_metadata.log');
define('CACHE_DURATION', 30); // 30 seconds cache
define('TIMEOUT_SECONDS', 8);
define('MAX_RETRIES', 3);

// Create directories
foreach ([CACHE_DIR, dirname(LOG_FILE)] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Enhanced logging
 */
function logMetadata($message, $data = null, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[{$timestamp}] [{$level}] {$message}";
    if ($data) {
        $log .= " - " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents(LOG_FILE, $log . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Cache functions
 */
function getCacheKey($method, $url) {
    return md5($method . '_' . $url);
}

function getFromCache($key) {
    $cacheFile = CACHE_DIR . $key . '.json';
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && isset($data['expires']) && $data['expires'] > time()) {
            return $data['content'];
        }
        unlink($cacheFile); // Remove expired cache
    }
    
    return null;
}

function saveToCache($key, $content) {
    $cacheFile = CACHE_DIR . $key . '.json';
    $data = [
        'content' => $content,
        'expires' => time() + CACHE_DURATION,
        'created' => time()
    ];
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);
}

/**
 * Create HTTP context with timeout and user agent
 */
function createHttpContext($timeout = TIMEOUT_SECONDS) {
    return stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'IghisHomeRadio/1.0 (Metadata Parser)',
            'method' => 'GET',
            'header' => [
                'Accept: application/json, text/xml, text/plain',
                'Icy-MetaData: 1',
                'Connection: close'
            ]
        ]
    ]);
}

/**
 * Enhanced CURL request with retry logic
 */
function curlRequest($url, $headers = [], $timeout = TIMEOUT_SECONDS) {
    for ($attempt = 1; $attempt <= MAX_RETRIES; $attempt++) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'IghisHomeRadio/1.0',
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: application/json, text/xml, text/plain',
                'Icy-MetaData: 1'
            ], $headers),
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            logMetadata("CURL error on attempt {$attempt}", ['url' => $url, 'error' => $error], 'WARN');
            if ($attempt < MAX_RETRIES) {
                sleep($attempt); // Progressive delay
                continue;
            }
            return false;
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            
            return [
                'headers' => $headers,
                'body' => $body,
                'http_code' => $httpCode
            ];
        }
        
        logMetadata("HTTP error on attempt {$attempt}", ['url' => $url, 'code' => $httpCode], 'WARN');
        
        if ($attempt < MAX_RETRIES) {
            sleep($attempt);
        }
    }
    
    return false;
}

/**
 * Parse stream title into artist and title
 */
function parseStreamTitle($streamTitle) {
    $title = trim($streamTitle);
    
    // Skip empty or station identification
    if (empty($title) || 
        stripos($title, 'Ighi') !== false || 
        stripos($title, 'Home Radio') !== false ||
        stripos($title, 'Station') !== false ||
        strlen($title) < 3) {
        return null;
    }
    
    // Common separation patterns (in order of preference)
    $patterns = [
        '/^(.+?)\s*-\s*(.+)$/',           // Artist - Title
        '/^(.+?)\s*–\s*(.+)$/',           // Artist – Title (em dash)
        '/^(.+?)\s*:\s*(.+)$/',           // Artist : Title  
        '/^(.+?)\s*\|\s*(.+)$/',          // Artist | Title
        '/^(.+?)\s*\/\s*(.+)$/',          // Artist / Title
        '/^(.+?)\s*\\\\\s*(.+)$/',        // Artist \ Title
        '/^(.+?)\s*by\s+(.+)$/i',         // Title by Artist
        '/^(.+?)\s*performed\s+by\s+(.+)$/i', // Title performed by Artist
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $title, $matches)) {
            $part1 = trim($matches[1]);
            $part2 = trim($matches[2]);
            
            // For "by" patterns, swap order
            if (stripos($pattern, 'by') !== false) {
                return [
                    'artist' => $part2,
                    'title' => $part1,
                    'raw' => $title,
                    'confidence' => 0.8
                ];
            }
            
            return [
                'artist' => $part1,
                'title' => $part2,
                'raw' => $title,
                'confidence' => 0.9
            ];
        }
    }
    
    // If no pattern matches, assume it's just a title
    return [
        'artist' => 'Unknown Artist',
        'title' => $title,
        'raw' => $title,
        'confidence' => 0.3
    ];
}

/**
 * Get metadata from Icecast JSON stats
 */
function getIcecastMetadata($url) {
    $cacheKey = getCacheKey('icecast', $url);
    $cached = getFromCache($cacheKey);
    if ($cached) {
        return $cached;
    }
    
    logMetadata('Trying Icecast metadata', ['url' => $url]);
    
    $response = curlRequest($url);
    if (!$response) {
        return null;
    }
    
    $data = json_decode($response['body'], true);
    if (!$data) {
        return null;
    }
    
    $metadata = null;
    
    // Try different Icecast JSON structures
    if (isset($data['icestats']['source'])) {
        $source = $data['icestats']['source'];
        
        // Handle array of sources
        if (is_array($source) && isset($source[0])) {
            $source = $source[0];
        }
        
        if (isset($source['title']) && !empty($source['title'])) {
            $parsed = parseStreamTitle($source['title']);
            if ($parsed) {
                $metadata = [
                    'artist' => $parsed['artist'],
                    'title' => $parsed['title'],
                    'raw_title' => $parsed['raw'],
                    'confidence' => $parsed['confidence'],
                    'listeners' => $source['listeners'] ?? null,
                    'bitrate' => $source['bitrate'] ?? null,
                    'format' => $source['server_type'] ?? null,
                    'source' => 'Icecast Stats',
                    'timestamp' => time()
                ];
            }
        }
        
        // Additional metadata
        if ($metadata) {
            $metadata['stream_info'] = [
                'server_name' => $source['server_name'] ?? null,
                'server_description' => $source['server_description'] ?? null,
                'content_type' => $source['content_type'] ?? null,
                'public' => $source['public'] ?? null
            ];
        }
    }
    
    if ($metadata) {
        saveToCache($cacheKey, $metadata);
        logMetadata('Icecast metadata success', ['title' => $metadata['title']]);
    }
    
    return $metadata;
}

/**
 * Get metadata from Shoutcast JSON stats
 */
function getShoutcastMetadata($url) {
    $cacheKey = getCacheKey('shoutcast', $url);
    $cached = getFromCache($cacheKey);
    if ($cached) {
        return $cached;
    }
    
    logMetadata('Trying Shoutcast metadata', ['url' => $url]);
    
    $response = curlRequest($url);
    if (!$response) {
        return null;
    }
    
    $data = json_decode($response['body'], true);
    if (!$data) {
        return null;
    }
    
    $metadata = null;
    
    // Shoutcast JSON structure
    if (isset($data['songtitle']) && !empty($data['songtitle'])) {
        $parsed = parseStreamTitle($data['songtitle']);
        if ($parsed) {
            $metadata = [
                'artist' => $parsed['artist'],
                'title' => $parsed['title'],
                'raw_title' => $parsed['raw'],
                'confidence' => $parsed['confidence'],
                'listeners' => $data['currentlisteners'] ?? null,
                'peak_listeners' => $data['peaklisteners'] ?? null,
                'bitrate' => $data['bitrate'] ?? null,
                'sample_rate' => $data['samplerate'] ?? null,
                'source' => 'Shoutcast Stats',
                'timestamp' => time()
            ];
        }
    }
    
    // Additional stream info
    if ($metadata && isset($data['servertitle'])) {
        $metadata['stream_info'] = [
            'server_title' => $data['servertitle'],
            'server_url' => $data['serverurl'] ?? null,
            'stream_genre' => $data['streamgenre'] ?? null,
            'stream_homepage' => $data['streamhomepage'] ?? null
        ];
    }
    
    if ($metadata) {
        saveToCache($cacheKey, $metadata);
        logMetadata('Shoutcast metadata success', ['title' => $metadata['title']]);
    }
    
    return $metadata;
}

/**
 * Get metadata from ICY headers
 */
function getICYMetadata($url) {
    $cacheKey = getCacheKey('icy', $url);
    $cached = getFromCache($cacheKey);
    if ($cached) {
        return $cached;
    }
    
    logMetadata('Trying ICY metadata', ['url' => $url]);
    
    $response = curlRequest($url, ['Icy-MetaData: 1'], 5);
    if (!$response) {
        return null;
    }
    
    $metadata = null;
    $headers = $response['headers'];
    
    // Look for ICY headers
    $icyFields = [
        'icy-name' => 'station_name',
        'icy-description' => 'description', 
        'icy-url' => 'url',
        'icy-genre' => 'genre',
        'icy-br' => 'bitrate',
        'icy-title' => 'current_title'
    ];
    
    $icyData = [];
    foreach ($icyFields as $header => $field) {
        if (preg_match('/^' . preg_quote($header) . ':\s*(.+)$/im', $headers, $matches)) {
            $icyData[$field] = trim($matches[1]);
        }
    }
    
    // Parse current title if available
    if (!empty($icyData['current_title'])) {
        $parsed = parseStreamTitle($icyData['current_title']);
        if ($parsed) {
            $metadata = [
                'artist' => $parsed['artist'],
                'title' => $parsed['title'], 
                'raw_title' => $parsed['raw'],
                'confidence' => $parsed['confidence'],
                'source' => 'ICY Headers',
                'timestamp' => time()
            ];
            
            // Add stream info
            $metadata['stream_info'] = array_filter([
                'station_name' => $icyData['station_name'] ?? null,
                'description' => $icyData['description'] ?? null,
                'url' => $icyData['url'] ?? null,
                'genre' => $icyData['genre'] ?? null,
                'bitrate' => $icyData['bitrate'] ?? null
            ]);
        }
    }
    
    if ($metadata) {
        saveToCache($cacheKey, $metadata);
        logMetadata('ICY metadata success', ['title' => $metadata['title']]);
    }
    
    return $metadata;
}

/**
 * Get basic stream information
 */
function getStreamInfo($url) {
    $cacheKey = getCacheKey('stream_info', $url);
    $cached = getFromCache($cacheKey);
    if ($cached) {
        return $cached;
    }
    
    $response = curlRequest($url, [], 3);
    if (!$response) {
        return null;
    }
    
    $info = [
        'status' => 'online',
        'http_code' => $response['http_code'],
        'timestamp' => time(),
        'source' => 'Stream Check'
    ];
    
    // Parse headers for stream information
    $headers = $response['headers'];
    if (preg_match('/content-type:\s*([^\r\n]+)/i', $headers, $matches)) {
        $info['content_type'] = trim($matches[1]);
    }
    
    if (preg_match('/server:\s*([^\r\n]+)/i', $headers, $matches)) {
        $info['server'] = trim($matches[1]);
    }
    
    saveToCache($cacheKey, $info);
    return $info;
}

/**
 * Main metadata retrieval function
 */
function getStreamMetadata() {
    $startTime = microtime(true);
    
    logMetadata('Starting metadata retrieval');
    
    // Try multiple methods in order of reliability
    $methods = [
        ['function' => 'getShoutcastMetadata', 'url' => STATS_URL_SHOUTCAST, 'priority' => 1],
        ['function' => 'getIcecastMetadata', 'url' => STATS_URL_ICECAST, 'priority' => 2],
        ['function' => 'getICYMetadata', 'url' => STREAM_URL, 'priority' => 3]
    ];
    
    $bestMetadata = null;
    $methodResults = [];
    
    foreach ($methods as $method) {
        try {
            $result = call_user_func($method['function'], $method['url']);
            $methodResults[$method['function']] = $result ? 'success' : 'failed';
            
            if ($result && (!$bestMetadata || $result['confidence'] > $bestMetadata['confidence'])) {
                $bestMetadata = $result;
                $bestMetadata['method'] = $method['function'];
                $bestMetadata['priority'] = $method['priority'];
            }
            
        } catch (Exception $e) {
            $methodResults[$method['function']] = 'error: ' . $e->getMessage();
            logMetadata('Method error', ['method' => $method['function'], 'error' => $e->getMessage()], 'ERROR');
        }
    }
    
    // Add processing info
    if ($bestMetadata) {
        $bestMetadata['processing'] = [
            'methods_tried' => $methodResults,
            'processing_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
            'cache_used' => false // Will be updated if cache was used
        ];
        
        logMetadata('Metadata retrieval success', [
            'method' => $bestMetadata['method'],
            'title' => $bestMetadata['title'],
            'confidence' => $bestMetadata['confidence']
        ]);
    } else {
        logMetadata('All metadata methods failed', ['methods' => $methodResults], 'WARN');
    }
    
    // Fallback: get basic stream info
    if (!$bestMetadata) {
        $streamInfo = getStreamInfo(STREAM_URL);
        if ($streamInfo) {
            $bestMetadata = [
                'status' => 'stream_online_no_metadata',
                'message' => 'Stream is online but no track metadata available',
                'stream_info' => $streamInfo,
                'timestamp' => time(),
                'source' => 'Stream Status Only'
            ];
        }
    }
    
    return $bestMetadata;
}

// =========================================
// API ENDPOINTS
// =========================================

try {
    $action = $_GET['action'] ?? 'metadata';
    
    switch ($action) {
        case 'metadata':
            $metadata = getStreamMetadata();
            
            if ($metadata) {
                echo json_encode($metadata);
            } else {
                http_response_code(503);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Unable to retrieve stream metadata',
                    'timestamp' => time(),
                    'suggestion' => 'Stream may be offline or metadata unavailable'
                ]);
            }
            break;
            
        case 'status':
            $streamInfo = getStreamInfo(STREAM_URL);
            echo json_encode($streamInfo ?: [
                'status' => 'offline',
                'message' => 'Stream appears to be offline',
                'timestamp' => time()
            ]);
            break;
            
        case 'test':
            // Test all methods
            $results = [
                'shoutcast' => getShoutcastMetadata(STATS_URL_SHOUTCAST),
                'icecast' => getIcecastMetadata(STATS_URL_ICECAST),
                'icy' => getICYMetadata(STREAM_URL),
                'stream_info' => getStreamInfo(STREAM_URL)
            ];
            
            echo json_encode([
                'test_results' => $results,
                'timestamp' => time(),
                'cache_dir_writable' => is_writable(CACHE_DIR),
                'log_dir_writable' => is_writable(dirname(LOG_FILE))
            ]);
            break;
            
        case 'clear_cache':
            $cleared = 0;
            $files = glob(CACHE_DIR . '*.json');
            foreach ($files as $file) {
                if (unlink($file)) {
                    $cleared++;
                }
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => "Cleared {$cleared} cache files",
                'timestamp' => time()
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'Unknown action',
                'available_actions' => ['metadata', 'status', 'test', 'clear_cache']
            ]);
    }
    
} catch (Exception $e) {
    logMetadata('API error', ['error' => $e->getMessage()], 'ERROR');
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ]);
}
?>