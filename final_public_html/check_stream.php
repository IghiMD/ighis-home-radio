<?php
/**
 * Stream Status Checker
 * Kontroluje dostupnosť rádiového streamu
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Cache nastavenia (kratší cache pre status check)
$cacheFile = 'cache/stream_status.json';
$cacheTime = 3; // 3 sekundy cache

// Skontroluj cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo file_get_contents($cacheFile);
    exit();
}

// Stream URL a metadata URL
$stream_url = 'https://fra-pioneer01.dedicateware.com:2415/'; // Base URL bez /stream
$metadata_url = 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl'; // Metadata endpoint

/**
 * Kontrola dostupnosti URL
 */
function checkUrlStatus($url, $timeout = 5) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'IghisHomeRadio/1.0 StreamChecker',
            'method' => 'GET' // Zmenené z HEAD na GET kvôli HTTP 405
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $headers = @get_headers($url, 1, $context);
    
    if ($headers && is_array($headers) && count($headers) > 0) {
        // Získaj status line - môže byť na indexe 0 alebo ako prvý kľúč
        $status_line = '';
        if (isset($headers[0])) {
            $status_line = $headers[0];
        } else {
            // Nájdi prvý číslovaný kľúč
            foreach ($headers as $key => $value) {
                if (is_numeric($key)) {
                    $status_line = $value;
                    break;
                }
            }
        }
        
        if (!empty($status_line)) {
            // Extrahovaj status kód z response line (napr. "HTTP/1.1 200 OK")
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $status_line, $matches)) {
                $status_code = (int) $matches[1];
            } else {
                $status_code = 0;
            }
            
            return [
                'available' => ($status_code >= 200 && $status_code < 400),
                'status_code' => $status_code,
                'status_line' => $status_line
            ];
        }
    }
    
    return [
        'available' => false,
        'status_code' => 0,
        'status_line' => 'Connection failed'
    ];
}

/**
 * Získaj základné info z metadát
 */
function getBasicStreamInfo($metadata_url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 4,
            'user_agent' => 'IghisHomeRadio/1.0',
            'method' => 'GET'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $response = @file_get_contents($metadata_url, false, $context);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['icestats']['source'])) {
            $source = $data['icestats']['source'];
            return [
                'listeners' => $source['listeners'] ?? 0,
                'bitrate' => $source['bitrate'] ?? null,
                'server_name' => $source['server_name'] ?? null,
                'has_metadata' => true
            ];
        }
    }
    
    return [
        'listeners' => null,
        'bitrate' => null,
        'server_name' => null,
        'has_metadata' => false
    ];
}

// Kontrola streamu
$stream_status = checkUrlStatus($stream_url);
$metadata_status = checkUrlStatus($metadata_url);
$stream_info = getBasicStreamInfo($metadata_url);

// Celkový status
$overall_status = $stream_status['available'] && $metadata_status['available'];

$result = [
    'status' => $overall_status ? 'online' : 'offline',
    'timestamp' => time(),
    'checks' => [
        'stream' => [
            'url' => $stream_url,
            'available' => $stream_status['available'],
            'status_code' => $stream_status['status_code'],
            'status_line' => $stream_status['status_line']
        ],
        'metadata' => [
            'url' => $metadata_url,
            'available' => $metadata_status['available'],
            'status_code' => $metadata_status['status_code'],
            'status_line' => $metadata_status['status_line']
        ]
    ],
    'stream_info' => $stream_info,
    'summary' => [
        'online' => $overall_status,
        'listeners' => $stream_info['listeners'],
        'bitrate' => $stream_info['bitrate'],
        'server_name' => $stream_info['server_name']
    ]
];

// HTTP status code podľa výsledku
if (!$overall_status) {
    http_response_code(503); // Service Unavailable
}

$output = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Ulož do cache
if (!is_dir('cache')) {
    mkdir('cache', 0755, true);
}
file_put_contents($cacheFile, $output);

echo $output;
?>
