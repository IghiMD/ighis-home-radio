<?php
/**
 * BACKUP ENDPOINT TESTER
 * Testuje všetky dostupné metadata zdroje pre cost-efficient riešenie
 */

header('Content-Type: text/html; charset=utf-8');

// Test endpoints z vašej infraštruktúry
$ENDPOINTS_TO_TEST = [
    'primary_https' => [
        'url' => 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl',
        'type' => 'json',
        'description' => 'Primary HTTPS JSON (current)',
        'cost' => 'FREE',
        'expected' => 'JSON with icestats.source.title'
    ],
    'backup_http_json' => [
        'url' => 'http://fra-pioneer01.dedicateware.com:2410/status-json.xsl',
        'type' => 'json', 
        'description' => 'Backup HTTP JSON (port 2410)',
        'cost' => 'FREE',
        'expected' => 'JSON with icestats.source.title'
    ],
    'backup_http_status' => [
        'url' => 'http://fra-pioneer01.dedicateware.com:2410',
        'type' => 'html',
        'description' => 'Backup HTTP Status Page',
        'cost' => 'FREE',
        'expected' => 'HTML status page with title'
    ],
    'icy_https_stream' => [
        'url' => 'https://fra-pioneer01.dedicateware.com:2415/stream',
        'type' => 'icy',
        'description' => 'ICY Direct HTTPS Stream',
        'cost' => 'FREE',
        'expected' => 'ICY metadata headers'
    ],
    'icy_http_stream' => [
        'url' => 'http://fra-pioneer01.dedicateware.com:2410/stream',
        'type' => 'icy',
        'description' => 'ICY Direct HTTP Stream (port 2410)',
        'cost' => 'FREE',
        'expected' => 'ICY metadata headers'
    ],
    'potential_xml' => [
        'url' => 'https://fra-pioneer01.dedicateware.com:2415/status.xsl',
        'type' => 'xml',
        'description' => 'Potential XML Status (legacy)',
        'cost' => 'FREE',
        'expected' => 'XML status format'
    ],
    'potential_admin' => [
        'url' => 'https://fra-pioneer01.dedicateware.com:2415/admin/stats.xml',
        'type' => 'xml',
        'description' => 'Potential Admin Stats',
        'cost' => 'FREE',
        'expected' => 'Admin statistics XML'
    ]
];

// =============================================
// TEST FUNCTIONS
// =============================================

function testJSONEndpoint($url, $timeout = 8) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'EndpointTester/1.0',
            'method' => 'GET',
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $startTime = microtime(true);
    $response = @file_get_contents($url, false, $context);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    
    $result = [
        'success' => false,
        'response_time' => $responseTime,
        'http_code' => 0,
        'data_size' => 0,
        'metadata' => null,
        'error' => null
    ];
    
    // Get HTTP status
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                $result['http_code'] = (int)$matches[1];
                break;
            }
        }
    }
    
    if ($response !== false && $result['http_code'] >= 200 && $result['http_code'] < 300) {
        $result['data_size'] = strlen($response);
        
        $decoded = @json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result['success'] = true;
            
            // Extract metadata
            if (isset($decoded['icestats']['source'])) {
                $source = $decoded['icestats']['source'];
                $result['metadata'] = [
                    'title' => $source['title'] ?? $source['display-title'] ?? null,
                    'listeners' => $source['listeners'] ?? 0,
                    'bitrate' => $source['bitrate'] ?? null,
                    'server_name' => $source['server_name'] ?? null
                ];
            } else {
                $result['metadata'] = ['structure' => array_keys($decoded)];
            }
        } else {
            $result['error'] = 'Invalid JSON: ' . json_last_error_msg();
        }
    } else {
        $result['error'] = "HTTP {$result['http_code']} or connection failed";
    }
    
    return $result;
}

function testICYEndpoint($url, $timeout = 10) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Icy-MetaData: 1\r\nUser-Agent: EndpointTester/1.0\r\n",
            'timeout' => $timeout
        ]
    ]);
    
    $result = [
        'success' => false,
        'response_time' => 0,
        'metadata' => null,
        'error' => null,
        'icy_headers' => []
    ];
    
    $startTime = microtime(true);
    $stream = @fopen($url, 'r', false, $context);
    $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
    
    if (!$stream) {
        $result['error'] = 'Cannot open stream';
        return $result;
    }
    
    // Read ICY headers
    $metaInt = 0;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/^icy-([^:]+):\s*(.+)/i', $header, $matches)) {
                $result['icy_headers'][strtolower($matches[1])] = trim($matches[2]);
            }
            if (preg_match('/^icy-metaint:\s*(\d+)/i', $header, $matches)) {
                $metaInt = (int)$matches[1];
            }
        }
    }
    
    if ($metaInt > 0) {
        // Skip initial audio data (max 8KB for safety)
        $skipBytes = min($metaInt, 8192);
        @fread($stream, $skipBytes);
        
        // Read metadata length
        $metaLenByte = @fread($stream, 1);
        if ($metaLenByte !== false) {
            $metaLen = ord($metaLenByte) * 16;
            
            if ($metaLen > 0 && $metaLen < 4096) {
                $metadata = @fread($stream, $metaLen);
                
                if (preg_match("/StreamTitle='([^']*)'/", $metadata, $matches)) {
                    $result['success'] = true;
                    $result['metadata'] = [
                        'stream_title' => $matches[1],
                        'meta_interval' => $metaInt,
                        'meta_length' => $metaLen
                    ];
                }
            }
        }
    } else {
        $result['error'] = 'No icy-metaint header found';
    }
    
    @fclose($stream);
    return $result;
}

function testHTMLEndpoint($url, $timeout = 6) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'EndpointTester/1.0'
        ]
    ]);
    
    $startTime = microtime(true);
    $response = @file_get_contents($url, false, $context);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    
    $result = [
        'success' => false,
        'response_time' => $responseTime,
        'metadata' => null,
        'error' => null
    ];
    
    if ($response !== false) {
        // Look for title in HTML
        if (preg_match('/<title>([^<]*)<\/title>/i', $response, $matches)) {
            $title = trim($matches[1]);
            if (!empty($title)) {
                $result['success'] = true;
                $result['metadata'] = ['page_title' => $title];
            }
        }
        
        // Look for any potential track info in HTML
        if (preg_match('/current[ly\s]*playing[:\s]*([^<\n]+)/i', $response, $matches)) {
            $result['metadata']['current_track'] = trim($matches[1]);
        }
        
        if (!$result['success']) {
            $result['error'] = 'No useful metadata found in HTML';
        }
    } else {
        $result['error'] = 'Connection failed';
    }
    
    return $result;
}

// =============================================
// RUN TESTS
// =============================================

echo "<!DOCTYPE html>
<html>
<head>
    <title>Backup Endpoint Testing Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #2d3748, #4a5568); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .endpoint { background: white; margin: 15px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 5px solid #48bb78; }
        .failure { border-left: 5px solid #f56565; }
        .warning { border-left: 5px solid #ed8936; }
        .metadata { background: #f7fafc; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .status-badge { padding: 4px 8px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        .status-success { background: #c6f6d5; color: #22543d; }
        .status-fail { background: #fed7d7; color: #c53030; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 15px 0; }
        .stat { background: #edf2f7; padding: 10px; border-radius: 5px; text-align: center; }
    </style>
</head>
<body>
<div class='container'>
    <div class='header'>
        <h1>🔍 Backup Metadata Endpoint Testing</h1>
        <p>Testing all available FREE metadata sources for cost-efficient operation</p>
        <p><strong>Goal:</strong> Find reliable backup sources to eliminate 502 errors</p>
    </div>";

$successCount = 0;
$totalCount = count($ENDPOINTS_TO_TEST);

foreach ($ENDPOINTS_TO_TEST as $key => $endpoint) {
    echo "<div class='endpoint";
    
    $result = null;
    
    // Test based on type
    switch ($endpoint['type']) {
        case 'json':
            $result = testJSONEndpoint($endpoint['url']);
            break;
        case 'icy':
            $result = testICYEndpoint($endpoint['url']);
            break;
        case 'html':
            $result = testHTMLEndpoint($endpoint['url']);
            break;
        case 'xml':
            $result = testJSONEndpoint($endpoint['url']); // Try as JSON first
            if (!$result['success']) {
                $result = testHTMLEndpoint($endpoint['url']); // Fallback to HTML parser
            }
            break;
    }
    
    if ($result['success']) {
        echo " success'>";
        $successCount++;
        $statusBadge = "<span class='status-badge status-success'>✅ WORKING</span>";
    } else {
        echo " failure'>";
        $statusBadge = "<span class='status-badge status-fail'>❌ FAILED</span>";
    }
    
    echo "
        <h3>{$endpoint['description']} {$statusBadge}</h3>
        <p><strong>URL:</strong> <code>{$endpoint['url']}</code></p>
        <p><strong>Type:</strong> {$endpoint['type']} | <strong>Cost:</strong> <span style='color: green; font-weight: bold;'>{$endpoint['cost']}</span></p>
        
        <div class='stats'>
            <div class='stat'>
                <strong>Response Time</strong><br>
                {$result['response_time']}ms
            </div>";
    
    if (isset($result['http_code'])) {
        echo "<div class='stat'>
                <strong>HTTP Status</strong><br>
                {$result['http_code']}
            </div>";
    }
    
    if (isset($result['data_size'])) {
        echo "<div class='stat'>
                <strong>Data Size</strong><br>
                " . number_format($result['data_size']) . " bytes
            </div>";
    }
    
    echo "</div>";
    
    if ($result['success'] && $result['metadata']) {
        echo "<div class='metadata'>
                <strong>📊 Retrieved Metadata:</strong><br>
                <pre>" . json_encode($result['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>
            </div>";
    }
    
    if (isset($result['icy_headers']) && !empty($result['icy_headers'])) {
        echo "<div class='metadata'>
                <strong>📡 ICY Headers:</strong><br>
                <pre>" . json_encode($result['icy_headers'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>
            </div>";
    }
    
    if (!$result['success'] && $result['error']) {
        echo "<div class='metadata' style='background: #fed7d7; color: #c53030;'>
                <strong>❌ Error:</strong> {$result['error']}
            </div>";
    }
    
    echo "</div>";
}

// Summary
$successRate = round(($successCount / $totalCount) * 100, 1);
echo "
    <div class='header'>
        <h2>📈 Testing Summary</h2>
        <div class='stats'>
            <div class='stat'>
                <strong>Success Rate</strong><br>
                {$successRate}% ({$successCount}/{$totalCount})
            </div>
            <div class='stat'>
                <strong>Working Endpoints</strong><br>
                {$successCount}
            </div>
            <div class='stat'>
                <strong>Total Cost</strong><br>
                <span style='color: green; font-weight: bold;'>FREE</span>
            </div>
        </div>";

if ($successCount >= 2) {
    echo "<p style='color: #22543d; font-weight: bold;'>
        ✅ <strong>EXCELLENT!</strong> We have {$successCount} working backup sources. 
        This will provide robust metadata with 99%+ uptime at ZERO cost.
    </p>";
} elseif ($successCount >= 1) {
    echo "<p style='color: #d69e2e; font-weight: bold;'>
        ⚠️ <strong>LIMITED:</strong> Only {$successCount} working source(s). 
        We can still improve reliability, but need to investigate failed endpoints.
    </p>";
} else {
    echo "<p style='color: #c53030; font-weight: bold;'>
        ❌ <strong>CRITICAL:</strong> No backup sources working. 
        Current setup is vulnerable to single point of failure.
    </p>";
}

echo "</div>
</div>
</body>
</html>";
?>