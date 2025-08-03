<?php
/**
 * ADVANCED HEALTH & MONITORING DASHBOARD
 * Real-time monitoring pre Ighi's Home Radio metadata systém
 * Version: 3.0 - Complete Observability
 */

session_start();

// Basic auth protection
$MONITORING_PASSWORD = 'ighiradio2025!'; // Zmeňte si heslo

if (!isset($_SESSION['monitoring_auth']) && !isset($_POST['auth_password'])) {
    showLoginForm();
    exit();
}

if (isset($_POST['auth_password'])) {
    if ($_POST['auth_password'] === $MONITORING_PASSWORD) {
        $_SESSION['monitoring_auth'] = true;
    } else {
        showLoginForm('Wrong password!');
        exit();
    }
}

if (!$_SESSION['monitoring_auth']) {
    showLoginForm();
    exit();
}

// Logout handling
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// =============================================
// DATA COLLECTION FUNCTIONS
// =============================================

function getMetadataProxyHealth() {
    $health = [
        'cache_status' => [],
        'circuit_breaker' => [],
        'performance' => [],
        'recent_logs' => []
    ];
    
    // Cache status
    $cacheFiles = [
        'metadata' => 'cache/metadata_proxy.json',
        'circuit_breaker' => 'cache/circuit_breaker.json', 
        'offline_store' => 'cache/offline_metadata.json'
    ];
    
    foreach ($cacheFiles as $name => $file) {
        if (file_exists($file)) {
            $age = time() - filemtime($file);
            $size = filesize($file);
            $health['cache_status'][$name] = [
                'exists' => true,
                'age_seconds' => $age,
                'size_bytes' => $size,
                'readable' => is_readable($file)
            ];
        } else {
            $health['cache_status'][$name] = ['exists' => false];
        }
    }
    
    // Circuit breaker status
    if (file_exists('cache/circuit_breaker.json')) {
        $cbData = @json_decode(file_get_contents('cache/circuit_breaker.json'), true);
        $health['circuit_breaker'] = $cbData ?: [];
    }
    
    // Performance data from logs
    if (file_exists('logs/metadata_proxy.log')) {
        $logs = @file('logs/metadata_proxy.log');
        if ($logs) {
            $health['recent_logs'] = array_slice($logs, -20);
            $health['performance'] = parsePerformanceFromLogs($logs);
        }
    }
    
    return $health;
}

function parsePerformanceFromLogs($logs) {
    $stats = [
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'average_response_time' => 0,
        'endpoints_used' => [],
        'last_24h_requests' => 0
    ];
    
    $responseTimes = [];
    $last24h = time() - 86400;
    
    foreach ($logs as $line) {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $timeMatch)) {
            $logTime = strtotime($timeMatch[1]);
            if ($logTime >= $last24h) {
                $stats['last_24h_requests']++;
            }
        }
        
        if (strpos($line, 'duration_ms') !== false) {
            $stats['total_requests']++;
            
            if (strpos($line, 'success') !== false) {
                $stats['successful_requests']++;
            } else {
                $stats['failed_requests']++;
            }
            
            if (preg_match('/"duration_ms":(\d+\.?\d*)/', $line, $matches)) {
                $responseTimes[] = (float)$matches[1];
            }
        }
        
        if (preg_match('/"endpoint":"([^"]+)"/', $line, $matches)) {
            $endpoint = $matches[1];
            if (!isset($stats['endpoints_used'][$endpoint])) {
                $stats['endpoints_used'][$endpoint] = 0;
            }
            $stats['endpoints_used'][$endpoint]++;
        }
    }
    
    if (!empty($responseTimes)) {
        $stats['average_response_time'] = array_sum($responseTimes) / count($responseTimes);
    }
    
    return $stats;
}

function testEndpointsHealth() {
    $endpoints = [
        'primary' => 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl',
        'backup_http' => 'http://fra-pioneer01.dedicateware.com:2410/status-json.xsl',
        'html_status' => 'http://fra-pioneer01.dedicateware.com:2410'
    ];
    
    $results = [];
    
    foreach ($endpoints as $name => $url) {
        $start = microtime(true);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        $responseTime = round((microtime(true) - $start) * 1000, 2);
        
        $results[$name] = [
            'url' => $url,
            'response_time_ms' => $responseTime,
            'success' => $response !== false,
            'response_size' => $response ? strlen($response) : 0,
            'timestamp' => time()
        ];
        
        if ($response && strpos($url, 'json') !== false) {
            $decoded = @json_decode($response, true);
            $results[$name]['valid_json'] = json_last_error() === JSON_ERROR_NONE;
            if ($decoded && isset($decoded['icestats']['source']['title'])) {
                $results[$name]['has_metadata'] = true;
                $results[$name]['current_track'] = $decoded['icestats']['source']['title'];
            }
        }
    }
    
    return $results;
}

function getSystemStatus() {
    $status = [
        'timestamp' => time(),
        'server_time' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'disk_free' => disk_free_space('.'),
        'load_average' => sys_getloadavg()
    ];
    
    // Directory status
    $dirs = ['cache', 'logs', 'data'];
    foreach ($dirs as $dir) {
        $status['directories'][$dir] = [
            'exists' => is_dir($dir),
            'writable' => is_writable($dir),
            'size' => is_dir($dir) ? getDirSize($dir) : 0
        ];
    }
    
    return $status;
}

function getDirSize($dir) {
    $size = 0;
    if (is_dir($dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            $size += $file->getSize();
        }
    }
    return $size;
}

function formatBytes($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size >= 1024 && $i < 3; $i++) {
        $size /= 1024;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function showLoginForm($error = '') {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Monitoring Dashboard - Login</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-form { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .login-form h2 { text-align: center; color: #333; margin-bottom: 30px; }
            .login-form input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
            .login-form button { width: 100%; background: #007aff; color: white; padding: 12px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
            .login-form button:hover { background: #0056b3; }
            .error { color: #ff2d55; text-align: center; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <form class="login-form" method="POST">
            <h2>🔍 Monitoring Dashboard</h2>
            ' . ($error ? '<div class="error">' . htmlspecialchars($error) . '</div>' : '') . '
            <input type="password" name="auth_password" placeholder="Enter monitoring password" required>
            <button type="submit">Access Dashboard</button>
        </form>
    </body>
    </html>';
}

// =============================================
// API ENDPOINT
// =============================================

if (isset($_GET['api']) && $_GET['api'] === 'health') {
    header('Content-Type: application/json');
    
    $healthData = [
        'status' => 'healthy',
        'timestamp' => time(),
        'metadata_proxy' => getMetadataProxyHealth(),
        'endpoints' => testEndpointsHealth(),
        'system' => getSystemStatus()
    ];
    
    // Determine overall health
    $endpointHealth = testEndpointsHealth();
    $workingEndpoints = count(array_filter($endpointHealth, function($ep) {
        return $ep['success'];
    }));
    
    if ($workingEndpoints === 0) {
        $healthData['status'] = 'critical';
    } elseif ($workingEndpoints === 1) {
        $healthData['status'] = 'warning';
    }
    
    echo json_encode($healthData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// =============================================
// DASHBOARD HTML
// =============================================

$healthData = getMetadataProxyHealth();
$endpointHealth = testEndpointsHealth();
$systemStatus = getSystemStatus();

$workingEndpoints = count(array_filter($endpointHealth, function($ep) {
    return $ep['success'];
}));

$overallStatus = 'healthy';
if ($workingEndpoints === 0) {
    $overallStatus = 'critical';
} elseif ($workingEndpoints === 1) {
    $overallStatus = 'warning';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Monitoring Dashboard - Ighi's Home Radio</title>
    <meta http-equiv="refresh" content="30">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'SF Pro Text', -apple-system, BlinkMacSystemFont, Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #333; min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 30px; margin-bottom: 30px; text-align: center; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .header h1 { font-size: 2.5rem; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 10px; }
        .header .status { font-size: 1.2rem; font-weight: 600; padding: 10px 20px; border-radius: 25px; display: inline-block; }
        .status.healthy { background: #d4edda; color: #155724; }
        .status.warning { background: #fff3cd; color: #856404; }
        .status.critical { background: #f8d7da; color: #721c24; }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); transition: transform 0.3s ease; }
        .card:hover { transform: translateY(-5px); }
        .card h2 { color: #4a5568; margin-bottom: 20px; font-size: 1.4rem; display: flex; align-items: center; gap: 10px; }
        
        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; }
        .metric { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .metric-value { font-size: 1.8rem; font-weight: bold; color: #2d3748; }
        .metric-label { font-size: 0.9rem; color: #718096; margin-top: 5px; }
        
        .endpoint { display: flex; justify-content: space-between; align-items: center; padding: 15px; margin: 10px 0; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #cbd5e0; }
        .endpoint.success { border-left-color: #48bb78; }
        .endpoint.failed { border-left-color: #f56565; }
        .endpoint-info { flex: 1; }
        .endpoint-status { padding: 5px 15px; border-radius: 15px; font-size: 0.85rem; font-weight: 600; }
        .endpoint-status.success { background: #c6f6d5; color: #22543d; }
        .endpoint-status.failed { background: #fed7d7; color: #c53030; }
        
        .log-container { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 10px; max-height: 300px; overflow-y: auto; font-family: 'Monaco', 'Menlo', monospace; font-size: 0.8rem; }
        .log-entry { padding: 2px 0; border-bottom: 1px solid #4a5568; }
        .log-entry:last-child { border-bottom: none; }
        
        .actions { text-align: center; margin-top: 30px; }
        .btn { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 12px 25px; border: none; border-radius: 25px; text-decoration: none; font-weight: 600; margin: 0 10px; transition: all 0.3s ease; display: inline-block; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        .btn.secondary { background: rgba(255, 255, 255, 0.2); }
        
        .full-width { grid-column: 1 / -1; }
        
        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
            .container { padding: 10px; }
            .header h1 { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Advanced Monitoring Dashboard</h1>
            <p>Real-time health monitoring for Ighi's Home Radio</p>
            <div class="status <?php echo $overallStatus; ?>">
                System Status: <?php echo strtoupper($overallStatus); ?>
            </div>
            <p style="margin-top: 10px; color: #666;">
                Last updated: <?php echo date('H:i:s'); ?> | Auto-refresh: 30s
            </p>
        </div>
        
        <div class="grid">
            <!-- System Overview -->
            <div class="card">
                <h2>📊 System Overview</h2>
                <div class="metric-grid">
                    <div class="metric">
                        <div class="metric-value"><?php echo $workingEndpoints; ?>/3</div>
                        <div class="metric-label">Endpoints Working</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value"><?php echo round(($workingEndpoints/3)*100); ?>%</div>
                        <div class="metric-label">Success Rate</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value"><?php echo formatBytes($systemStatus['memory_usage']); ?></div>
                        <div class="metric-label">Memory Usage</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value"><?php echo formatBytes($systemStatus['disk_free']); ?></div>
                        <div class="metric-label">Disk Free</div>
                    </div>
                </div>
            </div>
            
            <!-- Endpoint Health -->
            <div class="card">
                <h2>🌐 Endpoint Health</h2>
                <?php foreach ($endpointHealth as $name => $endpoint): ?>
                    <div class="endpoint <?php echo $endpoint['success'] ? 'success' : 'failed'; ?>">
                        <div class="endpoint-info">
                            <strong><?php echo ucfirst(str_replace('_', ' ', $name)); ?></strong><br>
                            <small><?php echo $endpoint['response_time_ms']; ?>ms</small>
                            <?php if (isset($endpoint['current_track'])): ?>
                                <br><small style="color: #666;">🎵 <?php echo htmlspecialchars(substr($endpoint['current_track'], 0, 30)); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="endpoint-status <?php echo $endpoint['success'] ? 'success' : 'failed'; ?>">
                            <?php echo $endpoint['success'] ? '✅ Online' : '❌ Failed'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Performance Stats -->
            <div class="card">
                <h2>⚡ Performance Stats</h2>
                <?php if (isset($healthData['performance']['total_requests'])): ?>
                    <div class="metric-grid">
                        <div class="metric">
                            <div class="metric-value"><?php echo $healthData['performance']['total_requests']; ?></div>
                            <div class="metric-label">Total Requests</div>
                        </div>
                        <div class="metric">
                            <div class="metric-value"><?php echo round($healthData['performance']['average_response_time']); ?>ms</div>
                            <div class="metric-label">Avg Response</div>
                        </div>
                        <div class="metric">
                            <div class="metric-value"><?php echo $healthData['performance']['last_24h_requests']; ?></div>
                            <div class="metric-label">Last 24h</div>
                        </div>
                        <div class="metric">
                            <div class="metric-value"><?php echo round(($healthData['performance']['successful_requests']/$healthData['performance']['total_requests'])*100); ?>%</div>
                            <div class="metric-label">Success Rate</div>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666;">No performance data available yet.</p>
                <?php endif; ?>
            </div>
            
            <!-- Cache Status -->
            <div class="card">
                <h2>💾 Cache Status</h2>
                <?php foreach ($healthData['cache_status'] as $name => $cache): ?>
                    <div class="endpoint <?php echo $cache['exists'] ? 'success' : 'failed'; ?>">
                        <div class="endpoint-info">
                            <strong><?php echo ucfirst($name); ?> Cache</strong><br>
                            <?php if ($cache['exists']): ?>
                                <small><?php echo formatBytes($cache['size_bytes']); ?> | <?php echo round($cache['age_seconds']); ?>s old</small>
                            <?php else: ?>
                                <small>File not found</small>
                            <?php endif; ?>
                        </div>
                        <div class="endpoint-status <?php echo $cache['exists'] ? 'success' : 'failed'; ?>">
                            <?php echo $cache['exists'] ? '✅ Active' : '❌ Missing'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Circuit Breaker Status -->
            <div class="card">
                <h2>🔌 Circuit Breaker</h2>
                <?php if (!empty($healthData['circuit_breaker'])): ?>
                    <?php foreach ($healthData['circuit_breaker'] as $endpoint => $status): ?>
                        <div class="endpoint <?php echo $status['state'] === 'closed' ? 'success' : 'failed'; ?>">
                            <div class="endpoint-info">
                                <strong><?php echo $endpoint; ?></strong><br>
                                <small>Failures: <?php echo $status['failures']; ?> | State: <?php echo $status['state']; ?></small>
                            </div>
                            <div class="endpoint-status <?php echo $status['state'] === 'closed' ? 'success' : 'failed'; ?>">
                                <?php echo $status['state'] === 'closed' ? '✅ Closed' : '⚠️ ' . ucfirst($status['state']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666;">No circuit breaker data available.</p>
                <?php endif; ?>
            </div>
            
            <!-- Recent Logs -->
            <div class="card full-width">
                <h2>📝 Recent Activity</h2>
                <div class="log-container">
                    <?php if (!empty($healthData['recent_logs'])): ?>
                        <?php foreach (array_reverse(array_slice($healthData['recent_logs'], -10)) as $log): ?>
                            <div class="log-entry"><?php echo htmlspecialchars(trim($log)); ?></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="log-entry">No recent logs available.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="actions">
            <a href="?api=health" class="btn">📊 API Health Check</a>
            <a href="index.html" class="btn secondary">🏠 Back to Radio</a>
            <a href="?logout" class="btn secondary">🚪 Logout</a>
        </div>
    </div>
</body>
</html>