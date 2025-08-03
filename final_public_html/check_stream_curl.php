<?php
/**
 * Stream Status Checker - cURL verzia
 * Kontroluje dostupnosť rádiového streamu pomocou cURL
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Cache nastavenia
$cacheFile = 'cache/stream_status_curl.json';
$cacheTime = 3; // 3 sekundy cache

// Skontroluj cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo file_get_contents($cacheFile);
    exit();
}

// URLs
$base_url = 'https://fra-pioneer01.dedicateware.com:2415/';
$metadata_url = 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl';

/**
 * cURL check funkcia
 */
function curlCheck($url, $timeout = 5) {
    if (!function_exists('curl_init')) {
        return [
            'available' => false,
            'status_code' => 0,
            'status_line' => 'cURL not available',
            'error' => 'cURL extension not loaded'
        ];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'IghisHomeRadio/1.0 StreamChecker');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // Odstránené CURLOPT_NOBODY - použijeme GET request
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'available' => ($http_code >= 200 && $http_code < 400),
        'status_code' => $http_code,
        'status_line' => "HTTP/1.1 $http_code",
        'error' => $error ?: null,
        'response_length' => strlen($response)
    ];
}

/**
 * Získaj metadata cez cURL
 */
function getMetadataCurl($url) {
    if (!function_exists('curl_init')) {
        return ['has_metadata' => false, 'error' => 'cURL not available'];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_USERAGENT, 'IghisHomeRadio/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response && $http_code == 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['icestats']['source'])) {
            $source = $data['icestats']['source'];
            return [
                'listeners' => $source['listeners'] ?? 0,
                'bitrate' => $source['bitrate'] ?? null,
                'server_name' => $source['server_name'] ?? null,
                'title' => $source['title'] ?? null,
                'has_metadata' => true,
                'http_code' => $http_code
            ];
        }
    }
    
    return [
        'listeners' => null,
        'bitrate' => null,
        'server_name' => null,
        'title' => null,
        'has_metadata' => false,
        'http_code' => $http_code,
        'error' => $error
    ];
}

// Testovanie endpoints
$base_status = curlCheck($base_url);
$metadata_status = curlCheck($metadata_url);
$stream_info = getMetadataCurl($metadata_url);

// Celkový status - ak máme metadata, stream je online (nemusíme testovať base server)
$overall_status = $stream_info['has_metadata'] && $stream_info['http_code'] == 200;

$result = [
    'status' => $overall_status ? 'online' : 'offline',
    'timestamp' => time(),
    'method' => 'curl',
    'checks' => [
        'base_server' => [
            'url' => $base_url,
            'available' => $base_status['available'],
            'status_code' => $base_status['status_code'],
            'error' => $base_status['error']
        ],
        'metadata' => [
            'url' => $metadata_url,
            'available' => $metadata_status['available'],
            'status_code' => $metadata_status['status_code'],
            'error' => $metadata_status['error']
        ]
    ],
    'stream_info' => $stream_info,
    'summary' => [
        'online' => $overall_status,
        'listeners' => $stream_info['listeners'],
        'bitrate' => $stream_info['bitrate'],
        'server_name' => $stream_info['server_name'],
        'title' => $stream_info['title']
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
