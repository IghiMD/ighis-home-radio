<?php
/**
 * Enhanced System Monitor for Ighi's Home Radio
 * Version 3.0 - Robust Edition with Auth Fix
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load auth system FIRST
require_once 'auth.php';

// Use Auth class to check authentication
Auth::require();

// Load configuration
$configLoaded = false;
if (file_exists('config.php')) {
    try {
        require_once 'config.php';
        $configLoaded = true;
    } catch (Exception $e) {
        $configError = $e->getMessage();
    }
}

// Auto-refresh settings
$refreshInterval = isset($_GET['refresh']) ? (int)$_GET['refresh'] : 10;
$autoRefresh = !isset($_GET['auto']) || $_GET['auto'] !== '0';

// =============================================
// HELPER FUNCTIONS
// =============================================

/**
 * Safe file get contents with JSON decode
 */
function safeJsonDecode($file) {
    if (!file_exists($file)) {
        return null;
    }
    
    $content = @file_get_contents($file);
    if ($content === false) {
        return null;
    }
    
    $decoded = @json_decode($content, true);
    return $decoded ?: null;
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes <= 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log(1024));
    
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

/**
 * Format duration
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    } elseif ($seconds < 86400) {
        return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
    } else {
        return floor($seconds / 86400) . 'd ' . floor(($seconds % 86400) / 3600) . 'h';
    }
}

/**
 * Calculate metadata completeness
 */
function calculateMetadataCompleteness($metadata) {
    if (!is_array($metadata)) return 0;
    
    $fields = [
        'title' => 20,
        'artist' => 20,
        'album' => 15,
        'year' => 10,
        'artwork' => 15,
        'genre' => 10,
        'label' => 5,
        'isrc' => 5
    ];
    
    $score = 0;
    foreach ($fields as $field => $weight) {
        if (!empty($metadata[$field])) {
            $score += $weight;
        }
    }
    
    return $score;
}

/**
 * Check if service is enabled
 */
function checkServiceEnabled($service) {
    if (!$GLOBALS['configLoaded']) return false;
    
    if (function_exists('isServiceEnabled')) {
        return isServiceEnabled($service);
    }
    
    // Fallback checks
    switch ($service) {
        case 'spotify':
            return defined('SPOTIFY_CLIENT_ID') && !empty(SPOTIFY_CLIENT_ID);
        case 'youtube':
            return defined('YOUTUBE_API_KEY') && !empty(YOUTUBE_API_KEY);
        case 'lastfm':
            return defined('LASTFM_API_KEY') && !empty(LASTFM_API_KEY);
        case 'musicbrainz':
            return !defined('MUSICBRAINZ_ENABLED') || MUSICBRAINZ_ENABLED === 'true';
        case 'deezer':
            return !defined('DEEZER_ENABLED') || DEEZER_ENABLED === 'true';
        default:
            return false;
    }
}

/**
 * Get directory status
 */
function getDirectoryStatus($dir) {
    if (!is_dir($dir)) {
        return ['exists' => false, 'writable' => false, 'size' => 0];
    }
    
    $writable = is_writable($dir);
    $size = 0;
    
    // Calculate directory size
    $files = @scandir($dir);
    if ($files) {
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $filePath = $dir . '/' . $file;
                if (is_file($filePath)) {
                    $size += filesize($filePath);
                }
            }
        }
    }
    
    return ['exists' => true, 'writable' => $writable, 'size' => $size];
}

// =============================================
// DATA COLLECTION
// =============================================

// System directories
$directories = [
    'data' => $configLoaded && defined('DATA_DIR') ? DATA_DIR : 'data/',
    'cache' => $configLoaded && defined('CACHE_DIR') ? CACHE_DIR : 'cache/',
    'logs' => $configLoaded && defined('LOGS_DIR') ? LOGS_DIR : 'logs/',
    'backups' => $configLoaded && defined('BACKUP_DIR') ? BACKUP_DIR : 'backups/'
];

// System files
$systemFiles = [
    'current_song' => $configLoaded && defined('CURRENT_SONG_FILE') ? CURRENT_SONG_FILE : 'data/current_song.json',
    'history' => $configLoaded && defined('HISTORY_FILE') ? HISTORY_FILE : 'data/history.json',
    'system_log' => $configLoaded && defined('LOG_FILE') ? LOG_FILE : 'logs/system.log'
];

// Get current song data
$currentSong = safeJsonDecode($systemFiles['current_song']);
$songAge = null;
$metadataCompleteness = 0;

if ($currentSong && isset($currentSong['timestamp'])) {
    $songAge = time() - $currentSong['timestamp'];
    if (isset($currentSong['data'])) {
        $metadataCompleteness = calculateMetadataCompleteness($currentSong['data']);
    }
}

// Get history stats
$history = safeJsonDecode($systemFiles['history']);
$historyStats = [
    'total' => 0,
    'today' => 0,
    'quality' => ['excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0]
];

if (is_array($history)) {
    $historyStats['total'] = count($history);
    $todayStart = strtotime('today');
    
    foreach ($history as $entry) {
        if (isset($entry['timestamp']) && $entry['timestamp'] >= $todayStart) {
            $historyStats['today']++;
        }
        
        if (isset($entry['data'])) {
            $completeness = calculateMetadataCompleteness($entry['data']);
            if ($completeness >= 90) {
                $historyStats['quality']['excellent']++;
            } elseif ($completeness >= 70) {
                $historyStats['quality']['good']++;
            } elseif ($completeness >= 50) {
                $historyStats['quality']['fair']++;
            } else {
                $historyStats['quality']['poor']++;
            }
        }
    }
}

// Get recent logs
$recentLogs = [];
if (file_exists($systemFiles['system_log'])) {
    $logs = @file($systemFiles['system_log']);
    if ($logs) {
        $recentLogs = array_slice($logs, -10);
        $recentLogs = array_reverse($recentLogs);
    }
}

// Services status with actual functionality check
$services = [];

// Check each service
foreach (['spotify', 'youtube', 'lastfm', 'musicbrainz', 'deezer', 'deepl'] as $service) {
    if (!checkServiceEnabled($service)) {
        $services[$service] = false;
        continue;
    }
    
    // Check if service is actually working
    switch ($service) {
        case 'musicbrainz':
            $ch = curl_init('https://musicbrainz.org/ws/2/artist/5b11f4ce-a62d-471e-81fc-a69a8278c7da?fmt=json');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_USERAGENT, 'IghisHomeRadio/1.0');
            curl_exec($ch);
            $services[$service] = (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200);
            curl_close($ch);
            break;
            
        case 'deezer':
            $ch = curl_init('https://api.deezer.com/track/3135556');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_exec($ch);
            $services[$service] = (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200);
            curl_close($ch);
            break;
            
        default:
            $services[$service] = true; // Assume other services work if enabled
    }
}

// AI services
$aiServices = [];
if ($configLoaded && function_exists('getActiveAIServices')) {
    $aiServices = getActiveAIServices();
}

// Overall system status
$systemHealthy = $configLoaded &&
                 file_exists($systemFiles['current_song']) &&
                 is_dir($directories['data']) &&
                 is_writable($directories['data']);

$systemStatus = $systemHealthy ?
    ($songAge && $songAge < 600 ? 'online' : 'idle') :
    'error';

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitor - Ighi's Home Radio</title>
    <?php if ($autoRefresh): ?>
    <meta http-equiv="refresh" content="<?php echo $refreshInterval; ?>">
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            color: #f8fafc;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            color: #94a3b8;
            font-size: 1.1rem;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 15px;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        .status-online {
            background: #16a34a;
            color: white;
        }
        
        .status-idle {
            background: #f59e0b;
            color: white;
        }
        
        .status-error {
            background: #dc2626;
            color: white;
        }
        
        /* Grid Layout */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 24px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .card h2 {
            font-size: 1.3rem;
            color: #60a5fa;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card.full-width {
            grid-column: 1 / -1;
        }
        
        .card.double-width {
            grid-column: span 2;
        }
        
        /* Metrics */
        .metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .metric:last-child {
            border-bottom: none;
        }
        
        .metric-label {
            color: #94a3b8;
            font-weight: 500;
        }
        
        .metric-value {
            font-weight: 700;
            color: #f8fafc;
        }
        
        .metric-value.success {
            color: #4ade80;
        }
        
        .metric-value.warning {
            color: #fbbf24;
        }
        
        .metric-value.error {
            color: #f87171;
        }
        
        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #60a5fa, #a78bfa);
            transition: width 0.3s ease;
        }
        
        /* Song Info */
        .song-info {
            background: rgba(96, 165, 250, 0.1);
            border: 1px solid rgba(96, 165, 250, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .song-info h3 {
            color: #60a5fa;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .song-info p {
            margin: 5px 0;
            color: #e2e8f0;
        }
        
        /* Service Grid */
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .service-item {
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .service-enabled {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }
        
        .service-disabled {
            background: rgba(148, 163, 184, 0.1);
            color: #64748b;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        
        /* Logs */
        .log-container {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.85rem;
        }
        
        .log-entry {
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
            word-break: break-all;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .log-entry.error {
            color: #fca5a5;
        }
        
        /* Actions */
        .actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(96, 165, 250, 0.3);
        }
        
        .btn.secondary {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .btn.danger {
            background: linear-gradient(135deg, #f87171, #dc2626);
        }
        
        /* Footer */
        .footer {
            text-align: center;
            color: #64748b;
            margin-top: 40px;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .card.double-width {
                grid-column: span 1;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Loading Animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .loading {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🎵 System Monitor</h1>
            <p class="subtitle">Ighi's Home Radio - Real-time Analytics</p>
            <div class="status-badge status-<?php echo $systemStatus; ?>">
                <span style="display: inline-block; width: 8px; height: 8px; background: currentColor; border-radius: 50%; animation: pulse 2s infinite;"></span>
                System <?php echo ucfirst($systemStatus); ?>
            </div>
        </div>
        
        <!-- Main Grid -->
        <div class="grid">
            <!-- Current Song -->
            <div class="card double-width">
                <h2>🎵 Now Playing</h2>
                
                <?php if ($currentSong && isset($currentSong['data'])): ?>
                    <div class="song-info">
                        <h3><?php echo htmlspecialchars($currentSong['data']['title'] ?? 'Unknown Title'); ?></h3>
                        <p><strong>Artist:</strong> <?php echo htmlspecialchars($currentSong['data']['artist'] ?? 'Unknown Artist'); ?></p>
                        <p><strong>Album:</strong> <?php echo htmlspecialchars($currentSong['data']['album'] ?? '-'); ?></p>
                        <p><strong>Year:</strong> <?php echo htmlspecialchars($currentSong['data']['year'] ?? '-'); ?></p>
                        
                        <?php if ($songAge !== null): ?>
                        <p><strong>Last Update:</strong> <?php echo formatDuration($songAge); ?> ago</p>
                        <?php endif; ?>
                        
                        <div style="margin-top: 15px;">
                            <strong>Metadata Quality: <?php echo $metadataCompleteness; ?>%</strong>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $metadataCompleteness; ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="color: #64748b; text-align: center; padding: 40px 0;">
                        No song data available
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- System Health -->
            <div class="card">
                <h2>🏥 System Health</h2>
                
                <?php foreach ($directories as $name => $path): ?>
                    <?php $status = getDirectoryStatus($path); ?>
                    <div class="metric">
                        <span class="metric-label"><?php echo ucfirst($name); ?> Directory</span>
                        <span class="metric-value <?php echo $status['exists'] ? ($status['writable'] ? 'success' : 'warning') : 'error'; ?>">
                            <?php
                            if (!$status['exists']) {
                                echo '❌ Missing';
                            } elseif (!$status['writable']) {
                                echo '⚠️ Read-only';
                            } else {
                                echo '✅ OK';
                            }
                            ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                
                <div class="metric">
                    <span class="metric-label">Configuration</span>
                    <span class="metric-value <?php echo $configLoaded ? 'success' : 'error'; ?>">
                        <?php echo $configLoaded ? '✅ Loaded' : '❌ Error'; ?>
                    </span>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="card">
                <h2>📊 Statistics</h2>
                
                <div class="metric">
                    <span class="metric-label">Total Songs</span>
                    <span class="metric-value"><?php echo $historyStats['total']; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Songs Today</span>
                    <span class="metric-value"><?php echo $historyStats['today']; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Excellent Quality</span>
                    <span class="metric-value success"><?php echo $historyStats['quality']['excellent']; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Good Quality</span>
                    <span class="metric-value"><?php echo $historyStats['quality']['good']; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Poor Quality</span>
                    <span class="metric-value <?php echo $historyStats['quality']['poor'] > 0 ? 'warning' : ''; ?>">
                        <?php echo $historyStats['quality']['poor']; ?>
                    </span>
                </div>
            </div>
            
            <!-- Services Status -->
            <div class="card full-width">
                <h2>🔧 Services Status</h2>
                
                <h3 style="font-size: 1rem; color: #94a3b8; margin-bottom: 10px;">Metadata Services</h3>
                <div class="service-grid">
                    <?php foreach ($services as $service => $enabled): ?>
                        <div class="service-item <?php echo $enabled ? 'service-enabled' : 'service-disabled'; ?>">
                            <?php echo $enabled ? '✅' : '❌'; ?>
                            <?php echo ucfirst($service); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($aiServices)): ?>
                <h3 style="font-size: 1rem; color: #94a3b8; margin: 20px 0 10px;">AI Services</h3>
                <div class="service-grid">
                    <?php foreach ($aiServices as $service): ?>
                        <div class="service-item service-enabled">
                            🤖 <?php echo $service; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Logs -->
            <div class="card full-width">
                <h2>📝 Recent Activity</h2>
                
                <div class="log-container">
                    <?php if (!empty($recentLogs)): ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="log-entry <?php echo strpos($log, 'ERROR') !== false ? 'error' : ''; ?>">
                                <?php echo htmlspecialchars(trim($log)); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #64748b; text-align: center;">No log entries available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="actions">
            <a href="index.html" class="btn">
                🏠 Back to Radio
            </a>
            <a href="admin.php" class="btn secondary">
                ⚙️ Admin Panel
            </a>
            <?php if ($configLoaded): ?>
                <a href="backup.php" class="btn secondary">
                    💾 Backups
                </a>
            <?php endif; ?>
            <a href="logout.php" class="btn danger">
                🚪 Logout
            </a>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>
                Last refresh: <?php echo date('H:i:s'); ?> |
                Auto-refresh: <?php echo $autoRefresh ? "Every {$refreshInterval}s" : "Disabled"; ?> |
                <a href="?auto=<?php echo $autoRefresh ? '0' : '1'; ?>" style="color: #60a5fa;">
                    <?php echo $autoRefresh ? 'Disable' : 'Enable'; ?> Auto-refresh
                </a>
            </p>
        </div>
    </div>
</body>
</html>