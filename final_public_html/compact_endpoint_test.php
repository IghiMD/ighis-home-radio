<?php
/**
 * COMPACT ENDPOINT TESTER - Fits on one screen
 */

header('Content-Type: text/html; charset=utf-8');

$ENDPOINTS = [
    'primary' => ['url' => 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl', 'type' => 'json'],
    'backup_http' => ['url' => 'http://fra-pioneer01.dedicateware.com:2410/status-json.xsl', 'type' => 'json'],
    'backup_status' => ['url' => 'http://fra-pioneer01.dedicateware.com:2410', 'type' => 'html'],
    'icy_https' => ['url' => 'https://fra-pioneer01.dedicateware.com:2415/stream', 'type' => 'icy'],
    'icy_http' => ['url' => 'http://fra-pioneer01.dedicateware.com:2410/stream', 'type' => 'icy']
];

function quickTest($url, $type) {
    $start = microtime(true);
    $result = ['status' => '❌', 'time' => 0, 'data' => ''];
    
    if ($type === 'json') {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true]
        ]));
        $result['time'] = round((microtime(true) - $start) * 1000);
        
        if ($response && ($data = @json_decode($response, true)) && isset($data['icestats']['source']['title'])) {
            $result['status'] = '✅';
            $result['data'] = $data['icestats']['source']['title'];
        }
    } 
    elseif ($type === 'icy') {
        $stream = @fopen($url, 'r', false, stream_context_create([
            'http' => ['method' => 'GET', 'header' => "Icy-MetaData: 1\r\n", 'timeout' => 5]
        ]));
        $result['time'] = round((microtime(true) - $start) * 1000);
        
        if ($stream) {
            $metaInt = 0;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/icy-metaint:\s*(\d+)/i', $header, $m)) {
                        $metaInt = (int)$m[1];
                        break;
                    }
                }
            }
            
            if ($metaInt > 0) {
                @fread($stream, min($metaInt, 4096));
                $metaLen = ord(@fread($stream, 1)) * 16;
                if ($metaLen > 0) {
                    $metadata = @fread($stream, $metaLen);
                    if (preg_match("/StreamTitle='([^']*)'/", $metadata, $m)) {
                        $result['status'] = '✅';
                        $result['data'] = $m[1];
                    }
                }
            }
            @fclose($stream);
        }
    }
    elseif ($type === 'html') {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 5]
        ]));
        $result['time'] = round((microtime(true) - $start) * 1000);
        
        if ($response && preg_match('/<title>([^<]+)/i', $response, $m)) {
            $result['status'] = '✅';
            $result['data'] = trim($m[1]);
        }
    }
    
    return $result;
}

echo '<!DOCTYPE html>
<html>
<head>
    <title>Backup Endpoint Test - Compact</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f0f2f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #4a5568, #2d3748); color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .results { background: white; border-radius: 10px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-ok { color: #22543d; font-weight: bold; }
        .status-fail { color: #c53030; font-weight: bold; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .stat { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: bold; color: #2d3748; }
        .copy-box { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 12px; margin-top: 15px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔍 Backup Endpoint Test</h1>
        <p>Testing FREE metadata sources for resilient operation</p>
    </div>
    
    <div class="results">
        <table>
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Status</th>
                    <th>Response Time</th>
                    <th>Sample Data</th>
                </tr>
            </thead>
            <tbody>';

$working = 0;
$total = count($ENDPOINTS);
$results = [];

foreach ($ENDPOINTS as $name => $config) {
    $test = quickTest($config['url'], $config['type']);
    $results[] = $test;
    
    if ($test['status'] === '✅') $working++;
    
    $statusClass = $test['status'] === '✅' ? 'status-ok' : 'status-fail';
    $sampleData = !empty($test['data']) ? htmlspecialchars(substr($test['data'], 0, 50)) : 'No data';
    
    echo "<tr>
        <td><strong>" . ucfirst(str_replace('_', ' ', $name)) . "</strong><br>
            <small style='color: #666;'>{$config['type']} - " . parse_url($config['url'], PHP_URL_HOST) . ":" . parse_url($config['url'], PHP_URL_PORT) . "</small>
        </td>
        <td class='{$statusClass}'>{$test['status']}</td>
        <td>{$test['time']}ms</td>
        <td style='font-size: 11px;'>{$sampleData}</td>
    </tr>";
}

$successRate = round(($working / $total) * 100);
$reliability = $working >= 3 ? 'EXCELLENT' : ($working >= 2 ? 'GOOD' : ($working >= 1 ? 'LIMITED' : 'CRITICAL'));

echo '</tbody></table>
    
        <div class="summary">
            <div class="stat">
                <div class="stat-value" style="color: #22543d;">' . $working . '/' . $total . '</div>
                <div>Working</div>
            </div>
            <div class="stat">
                <div class="stat-value" style="color: #3182ce;">' . $successRate . '%</div>
                <div>Success Rate</div>
            </div>
            <div class="stat">
                <div class="stat-value" style="color: #805ad5;">FREE</div>
                <div>Total Cost</div>
            </div>
            <div class="stat">
                <div class="stat-value" style="color: #d69e2e;">' . $reliability . '</div>
                <div>Reliability</div>
            </div>
        </div>
        
        <div class="copy-box">
<strong>QUICK RESULTS (copy this):</strong>
Working endpoints: ' . $working . '/' . $total . ' (' . $successRate . '%)
Reliability level: ' . $reliability . '
';

foreach ($ENDPOINTS as $name => $config) {
    $i = array_search($name, array_keys($ENDPOINTS));
    echo "\n" . ($results[$i]['status'] === '✅' ? 'WORKING' : 'FAILED') . " - " . ucfirst(str_replace('_', ' ', $name)) . " ({$results[$i]['time']}ms)";
}

echo '
        </div>
    </div>
</div>
</body>
</html>';
?>