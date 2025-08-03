<?php
/**
 * Jednoduchý test external connections
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$metadata_url = 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl';

// Test basic connection
echo json_encode([
    'test' => 'simple_connection',
    'timestamp' => time(),
    'url' => $metadata_url,
    'php_version' => PHP_VERSION,
    'allow_url_fopen' => ini_get('allow_url_fopen') ? true : false,
    'curl_available' => function_exists('curl_init')
], JSON_PRETTY_PRINT);

// Test 1: Basic file_get_contents
echo "\n\n--- TEST 1: file_get_contents ---\n";

$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'user_agent' => 'IghisHomeRadio/1.0'
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$start = microtime(true);
$response = @file_get_contents($metadata_url, false, $context);
$duration = microtime(true) - $start;

echo json_encode([
    'method' => 'file_get_contents',
    'success' => $response !== false,
    'duration_ms' => round($duration * 1000, 2),
    'response_length' => $response ? strlen($response) : 0,
    'json_valid' => $response ? (json_decode($response) !== null) : false
], JSON_PRETTY_PRINT);

// Test 2: cURL if available
if (function_exists('curl_init')) {
    echo "\n\n--- TEST 2: cURL ---\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $metadata_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'IghisHomeRadio/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $duration = microtime(true) - $start;
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo json_encode([
        'method' => 'curl',
        'success' => $response !== false,
        'http_code' => $http_code,
        'duration_ms' => round($duration * 1000, 2),
        'response_length' => $response ? strlen($response) : 0,
        'json_valid' => $response ? (json_decode($response) !== null) : false,
        'error' => $error ?: null
    ], JSON_PRETTY_PRINT);
}

// Test 3: Server restrictions
echo "\n\n--- TEST 3: Server Info ---\n";
echo json_encode([
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'https' => isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'no',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
], JSON_PRETTY_PRINT);

?>