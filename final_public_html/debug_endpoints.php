<?php
/**
 * Debug a test súbor pre API endpoints
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>API Endpoints Debug</h1>";

$endpoints = [
    'metadata_proxy.php' => 'Metadata Proxy',
    'check_stream.php' => 'Stream Status Check'
];

foreach ($endpoints as $file => $name) {
    echo "<h2>Testing: $name ($file)</h2>";
    
    if (file_exists($file)) {
        echo "<p><strong>File exists:</strong> ✅ YES</p>";
        
        // Test HTTP request
        $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $file;
        
        echo "<p><strong>Testing URL:</strong> <a href=\"$url\" target=\"_blank\">$url</a></p>";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Debug/1.0'
            ]
        ]);
        
        $start = microtime(true);
        $response = @file_get_contents($url, false, $context);
        $duration = round((microtime(true) - $start) * 1000, 2);
        
        if ($response !== false) {
            echo "<p><strong>Response time:</strong> {$duration}ms</p>";
            echo "<p><strong>Response length:</strong> " . strlen($response) . " bytes</p>";
            
            $json = json_decode($response, true);
            if ($json) {
                echo "<p><strong>JSON valid:</strong> ✅ YES</p>";
                echo "<p><strong>Status:</strong> " . ($json['status'] ?? 'unknown') . "</p>";
                
                if (isset($json['stream_info'])) {
                    echo "<p><strong>Stream info:</strong></p>";
                    echo "<ul>";
                    foreach ($json['stream_info'] as $key => $value) {
                        echo "<li><strong>$key:</strong> " . ($value ?? 'null') . "</li>";
                    }
                    echo "</ul>";
                }
                
                echo "<details><summary>Full Response (click to expand)</summary>";
                echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
                echo "</details>";
            } else {
                echo "<p><strong>JSON valid:</strong> ❌ NO</p>";
                echo "<p><strong>JSON error:</strong> " . json_last_error_msg() . "</p>";
                echo "<details><summary>Raw Response</summary>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
                echo "</details>";
            }
        } else {
            echo "<p><strong>Request failed:</strong> ❌ Could not fetch response</p>";
            $error = error_get_last();
            if ($error) {
                echo "<p><strong>Error:</strong> " . htmlspecialchars($error['message']) . "</p>";
            }
        }
    } else {
        echo "<p><strong>File exists:</strong> ❌ NO</p>";
    }
    
    echo "<hr>";
}

// Test direct metadata endpoint
echo "<h2>Testing: Direct Metadata Endpoint</h2>";
$metadata_url = 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl';

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'IghisHomeRadio/1.0',
        'method' => 'GET'
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

echo "<p><strong>Testing URL:</strong> $metadata_url</p>";

$start = microtime(true);
$response = @file_get_contents($metadata_url, false, $context);
$duration = round((microtime(true) - $start) * 1000, 2);

if ($response !== false) {
    echo "<p><strong>Response time:</strong> {$duration}ms</p>";
    echo "<p><strong>Response length:</strong> " . strlen($response) . " bytes</p>";
    
    $json = json_decode($response, true);
    if ($json) {
        echo "<p><strong>JSON valid:</strong> ✅ YES</p>";
        echo "<details><summary>Raw Metadata (click to expand)</summary>";
        echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        echo "</details>";
    } else {
        echo "<p><strong>JSON valid:</strong> ❌ NO</p>";
        echo "<p><strong>JSON error:</strong> " . json_last_error_msg() . "</p>";
    }
} else {
    echo "<p><strong>Direct connection failed:</strong> ❌</p>";
}

// Cache info
echo "<h2>Cache Information</h2>";
$cache_dir = 'cache';
if (is_dir($cache_dir)) {
    echo "<p><strong>Cache directory:</strong> ✅ EXISTS</p>";
    echo "<p><strong>Permissions:</strong> " . substr(sprintf('%o', fileperms($cache_dir)), -4) . "</p>";
    
    $cache_files = glob($cache_dir . '/*.json');
    echo "<p><strong>Cache files:</strong></p>";
    echo "<ul>";
    foreach ($cache_files as $file) {
        $age = time() - filemtime($file);
        echo "<li>" . basename($file) . " (age: {$age}s)</li>";
    }
    echo "</ul>";
} else {
    echo "<p><strong>Cache directory:</strong> ❌ MISSING</p>";
}

echo "<h2>PHP Environment</h2>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>User Agent:</strong> " . ($_SERVER['HTTP_USER_AGENT'] ?? 'none') . "</p>";
echo "<p><strong>Server Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Allow URL fopen:</strong> " . (ini_get('allow_url_fopen') ? '✅ YES' : '❌ NO') . "</p>";
echo "<p><strong>OpenSSL:</strong> " . (extension_loaded('openssl') ? '✅ YES' : '❌ NO') . "</p>";

?>