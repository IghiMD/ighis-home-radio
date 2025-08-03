<?php
/**
 * Enhanced System Monitor for Ighi's Home Radio - SECURE VERSION
 * 
 * Features:
 * - Real-time metadata extraction monitoring  
 * - Gentleman AI content analytics
 * - DeepL translation tracking
 * - ICY + Enrichment pipeline visualization
 * - New sources: MusicBrainz, Discogs, Deezer
 */

require_once '../config.php';
require_once '../auth.php';

// Vyžaduj prihlásenie
Auth::require();

// File paths are already defined in config.php

// Auto-refresh interval
$refreshInterval = isset($_GET['refresh']) ? (int)$_GET['refresh'] : 10;
$autoRefresh = isset($_GET['auto']) ? $_GET['auto'] === '1' : true;

/**
 * Get system status with focus on enabled services
 */
function getSystemStatus() {
    $status = [
        'overall' => 'unknown',
        'files' => [
            'current_song' => file_exists(CURRENT_SONG_FILE),
            'history' => file_exists(HISTORY_FILE),
            'log' => file_exists(LOG_FILE),
            'metadata_cache' => file_exists(METADATA_CACHE_FILE),
            'trivia_cache' => file_exists(TRIVIA_CACHE_FILE),
            'ai_cache_dir' => is_dir(AI_CACHE_DIR),
            'backup_dir' => is_dir(BACKUP_DIR)
        ],
        'recognition' => [
            'active' => false,
            'last_update' => null,
            'quality' => 'unknown'
        ],
        'metadata' => [
            'sources_available' => 0,
            'completeness' => 0,
            'last_enrichment' => null
        ],
        'ai_content' => [
            'total_generated' => 0,
            'gentleman_quality' => 0,
            'translations_active' => false
        ],
        'services' => [
            'icy_enabled' => true,
            'spotify_enabled' => isServiceEnabled('spotify'),
            'lastfm_enabled' => isServiceEnabled('lastfm'),
            'youtube_enabled' => isServiceEnabled('youtube'),
            'musicbrainz_enabled' => isServiceEnabled('musicbrainz'),
            'discogs_enabled' => isServiceEnabled('discogs'),
            'deezer_enabled' => isServiceEnabled('deezer'),
            'deepl_enabled' => isServiceEnabled('deepl'),
            'ai_providers' => getActiveAIServices()
        ]
    ];
    
    // Check current song freshness
    if ($status['files']['current_song']) {
        $currentSong = json_decode(file_get_contents(CURRENT_SONG_FILE), true);
        if ($currentSong && isset($currentSong['timestamp'])) {
            $age = time() - $currentSong['timestamp'];
            $status['recognition']['active'] = $age < 600; // 10 minutes
            $status['recognition']['last_update'] = $currentSong['timestamp'];
            
            // Check metadata completeness
            if (isset($currentSong['data'])) {
                $status['metadata']['completeness'] = calculateMetadataCompleteness($currentSong['data']);
                $status['metadata']['sources_available'] = count($currentSong['data']['all_sources'] ?? []);
            }
        }
    }
    
    // Check AI content stats
    if (is_dir(AI_CACHE_DIR)) {
        $aiFiles = glob(AI_CACHE_DIR . '*.json');
        $status['ai_content']['total_generated'] = count($aiFiles);
        
        $totalQuality = 0;
        $validFiles = 0;
        foreach ($aiFiles as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['content']['score'])) {
                $totalQuality += $data['content']['score'];
                $validFiles++;
            }
        }
        
        if ($validFiles > 0) {
            $status['ai_content']['gentleman_quality'] = round($totalQuality / $validFiles, 1);
        }
    }
    
    // Check trivia cache
    if (file_exists(TRIVIA_CACHE_FILE)) {
        $triviaData = json_decode(file_get_contents(TRIVIA_CACHE_FILE), true);
        if ($triviaData && count($triviaData) > 0) {
            $status['ai_content']['translations_active'] = true;
        }
    }
    
    // Overall status determination
    $criticalFiles = ['current_song', 'history', 'log'];
    $allCriticalOk = true;
    foreach ($criticalFiles as $file) {
        if (!$status['files'][$file]) {
            $allCriticalOk = false;
            break;
        }
    }
    
    if (!$allCriticalOk) {
        $status['overall'] = 'error';
    } elseif (!$status['recognition']['active']) {
        $status['overall'] = 'warning';
    } elseif ($status['metadata']['completeness'] < 50) {
        $status['overall'] = 'metadata-poor';
    } elseif ($status['ai_content']['gentleman_quality'] > 70) {
        $status['overall'] = 'excellent';
    } else {
        $status['overall'] = 'good';
    }
    
    return $status;
}

/**
 * Get current song with enhanced AI analytics
 */
function getCurrentSong() {
    if (!file_exists(CURRENT_SONG_FILE)) {
        return null;
    }
    
    $currentSong = json_decode(file_get_contents(CURRENT_SONG_FILE), true);
    if (!$currentSong) {
        return null;
    }
    
    // Add age information
    if (isset($currentSong['timestamp'])) {
        $currentSong['age_seconds'] = time() - $currentSong['timestamp'];
        $currentSong['is_fresh'] = $currentSong['age_seconds'] < 300; // 5 minutes
    }
    
    // Add metadata completeness
    if (isset($currentSong['data'])) {
        $currentSong['metadata_completeness'] = calculateMetadataCompleteness($currentSong['data']);
    }
    
    // Add AI trivia info
    if (isset($currentSong['data']['artist']) && isset($currentSong['data']['title'])) {
        $currentSong['ai_trivia'] = [
            'en' => getAITriviaStatus($currentSong['data']['artist'], $currentSong['data']['title'], 'en'),
            'sk' => getAITriviaStatus($currentSong['data']['artist'], $currentSong['data']['title'], 'sk')
        ];
    }
    
    return $currentSong;
}

/**
 * Get AI trivia status
 */
function getAITriviaStatus($artist, $title, $lang) {
    if (!file_exists(TRIVIA_CACHE_FILE)) {
        return null;
    }
    
    $triviaCache = json_decode(file_get_contents(TRIVIA_CACHE_FILE), true);
    if (!$triviaCache) return null;
    
    $cacheKey = md5(strtolower($artist . '_' . $title . '_' . $lang));
    
    if (isset($triviaCache[$cacheKey])) {
        $trivia = $triviaCache[$cacheKey];
        
        if ($trivia['expires'] > time()) {
            return [
                'available' => true,
                'quality_score' => $trivia['data']['score'] ?? 0,
                'word_count' => $trivia['data']['word_count'] ?? 0,
                'provider' => $trivia['data']['provider'] ?? 'Unknown',
                'style' => $trivia['data']['style'] ?? 'standard',
                'cached_at' => $trivia['created']
            ];
        }
    }
    
    return ['available' => false];
}

/**
 * Calculate metadata completeness
 */
function calculateMetadataCompleteness($metadata) {
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
 * Get enhanced statistics including AI content
 */
function getEnhancedStats() {
    if (!file_exists(HISTORY_FILE)) {
        return [
            'total_songs' => 0,
            'metadata_quality' => ['excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0],
            'sources_usage' => [],
            'ai_content_stats' => [],
            'gentleman_style_stats' => []
        ];
    }
    
    $history = json_decode(file_get_contents(HISTORY_FILE), true);
    if (!$history) return [];
    
    $stats = [
        'total_songs' => count($history),
        'metadata_quality' => ['excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0],
        'sources_usage' => [],
        'missing_fields' => [
            'album' => 0,
            'year' => 0,
            'genre' => 0,
            'artwork' => 0,
            'label' => 0
        ],
        'avg_completeness' => 0,
        'ai_content_stats' => [
            'total_trivia_generated' => 0,
            'avg_gentleman_score' => 0,
            'languages' => ['en' => 0, 'sk' => 0],
            'avg_word_count' => 0
        ]
    ];
    
    $totalCompleteness = 0;
    
    foreach ($history as $entry) {
        if (!isset($entry['data'])) continue;
        
        $data = $entry['data'];
        $completeness = calculateMetadataCompleteness($data);
        $totalCompleteness += $completeness;
        
        // Quality distribution
        if ($completeness >= 90) {
            $stats['metadata_quality']['excellent']++;
        } elseif ($completeness >= 70) {
            $stats['metadata_quality']['good']++;
        } elseif ($completeness >= 50) {
            $stats['metadata_quality']['fair']++;
        } else {
            $stats['metadata_quality']['poor']++;
        }
        
        // Sources usage
        if (isset($data['all_sources'])) {
            foreach ($data['all_sources'] as $source) {
                $stats['sources_usage'][$source] = ($stats['sources_usage'][$source] ?? 0) + 1;
            }
        }
        
        // Missing fields
        foreach ($stats['missing_fields'] as $field => $count) {
            if (empty($data[$field])) {
                $stats['missing_fields'][$field]++;
            }
        }
    }
    
    $stats['avg_completeness'] = $stats['total_songs'] > 0 ? 
        round($totalCompleteness / $stats['total_songs'], 1) : 0;
    
    // AI Content Statistics
    if (file_exists(TRIVIA_CACHE_FILE)) {
        $triviaCache = json_decode(file_get_contents(TRIVIA_CACHE_FILE), true);
        if ($triviaCache) {
            $stats['ai_content_stats']['total_trivia_generated'] = count($triviaCache);
            
            $totalScore = 0;
            $totalWords = 0;
            $validEntries = 0;
            
            foreach ($triviaCache as $entry) {
                if (isset($entry['data'])) {
                    $data = $entry['data'];
                    
                    if (isset($data['score'])) {
                        $totalScore += $data['score'];
                        $validEntries++;
                    }
                    
                    if (isset($data['word_count'])) {
                        $totalWords += $data['word_count'];
                    }
                    
                    if (isset($data['lang'])) {
                        $lang = $data['lang'];
                        $stats['ai_content_stats']['languages'][$lang] = 
                            ($stats['ai_content_stats']['languages'][$lang] ?? 0) + 1;
                    }
                }
            }
            
            if ($validEntries > 0) {
                $stats['ai_content_stats']['avg_gentleman_score'] = round($totalScore / $validEntries, 1);
                $stats['ai_content_stats']['avg_word_count'] = round($totalWords / $validEntries, 1);
            }
        }
    }
    
    return $stats;
}

/**
 * Get recent logs with service highlighting
 */
function getRecentLogs($lines = 20) {
    if (!file_exists(LOG_FILE)) {
        return [];
    }
    
    $logs = file(LOG_FILE);
    if (!$logs) {
        return [];
    }
    
    $recent = array_slice($logs, -$lines);
    return array_reverse($recent);
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Format time duration
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    } elseif ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    } else {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        return $days . 'd ' . $hours . 'h';
    }
}

// Get data
$systemStatus = getSystemStatus();
$currentSong = getCurrentSong();
$enhancedStats = getEnhancedStats();
$recentLogs = getRecentLogs(20);

// Calculate uptime
$uptime = null;
if (!empty($recentLogs)) {
    $firstLog = end($recentLogs);
    if (preg_match('/\[([\d-\s:]+)\]/', $firstLog, $matches)) {
        $startTime = strtotime($matches[1]);
        if ($startTime) {
            $uptime = time() - $startTime;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Monitor - Ighi's Home Radio (Secure)</title>
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
            padding: 20px;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            backdrop-filter: blur(10px);
            position: relative;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #16a34a, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header .subtitle {
            color: #cbd5e1;
            font-size: 1.1rem;
        }
        
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #f87171, #dc2626);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(248, 113, 113, 0.3);
        }
        
        .status-indicator {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            margin: 10px;
        }
        
        .status-excellent {
            background: #16a34a;
            color: white;
        }
        
        .status-good {
            background: #3b82f6;
            color: white;
        }
        
        .status-metadata-fair {
            background: #8b3dff;
            color: white;
        }
        
        .status-metadata-poor {
            background: #f59e0b;
            color: white;
        }
        
        .status-warning {
            background: #d97706;
            color: white;
        }
        
        .status-error {
            background: #dc2626;
            color: white;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: transform 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .card h2 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #60a5fa;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h2::before {
            content: attr(data-icon);
            font-size: 1.5rem;
        }
        
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
            font-weight: 500;
            color: #cbd5e1;
        }
        
        .metric-value {
            font-weight: 700;
            color: #16a34a;
        }
        
        .metric-value.warning {
            color: #d97706;
        }
        
        .metric-value.error {
            color: #dc2626;
        }
        
        .metric-value.disabled {
            color: #64748b;
            font-style: italic;
        }
        
        .current-song {
            background: rgba(22, 163, 74, 0.1);
            border: 1px solid rgba(22, 163, 74, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .current-song h3 {
            color: #16a34a;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }
        
        .current-song p {
            color: #cbd5e1;
            margin-bottom: 5px;
        }
        
        .service-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .service-item {
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        
        .service-item.enabled {
            background: rgba(22, 163, 74, 0.2);
            color: #16a34a;
        }
        
        .service-item.disabled {
            background: rgba(100, 116, 139, 0.2);
            color: #64748b;
        }
        
        .ai-trivia-info {
            background: rgba(139, 61, 255, 0.1);
            border: 1px solid rgba(139, 61, 255, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .ai-trivia-info h4 {
            color: #8b3dff;
            margin-bottom: 8px;
            font-size: 1rem;
        }
        
        .log-container {
            max-height: 400px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 15px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.85rem;
        }
        
        .log-entry {
            color: #cbd5e1;
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            word-break: break-all;
            line-height: 1.4;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .log-entry.error {
            color: #fca5a5;
            background: rgba(220, 38, 38, 0.1);
            padding: 8px;
            margin: 2px 0;
            border-radius: 4px;
        }
        
        .log-entry.spotify {
            color: #86efac;
        }
        
        .log-entry.ai {
            color: #c084fc;
        }
        
        .log-entry.manual {
            color: #60a5fa;
        }
        
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn {
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(96, 165, 250, 0.3);
        }
        
        .btn.secondary {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .btn.danger {
            background: linear-gradient(135deg, #f87171, #dc2626);
        }
        
        .refresh-notice {
            text-align: center;
            color: #64748b;
            margin-top: 20px;
            font-size: 0.9rem;
            padding: 15px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .logout-btn {
                position: static;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎵 Enhanced Monitor - Secure</h1>
            <p class="subtitle">Ighi's Home Radio - Real-time System Analytics</p>
            <div class="status-indicator status-<?php echo $systemStatus['overall']; ?>">
                System: <?php echo ucfirst(str_replace('-', ' ', $systemStatus['overall'])); ?>
            </div>
            <div style="margin-top: 10px; font-size: 0.9rem; color: #94a3b8;">
                Last refresh: <?php echo date('H:i:s'); ?>
                <?php if ($uptime): ?>
                | Uptime: <?php echo formatDuration($uptime); ?>
                <?php endif; ?>
                | Avg Metadata: <?php echo $enhancedStats['avg_completeness']; ?>%
                | AI Gentleman Score: <?php echo $enhancedStats['ai_content_stats']['avg_gentleman_score']; ?>%
            </div>
            <a href="logout.php" class="logout-btn">🚪 Odhlásiť</a>
        </div>

        <div class="grid">
            <!-- Current Song with AI Trivia -->
            <div class="card" style="grid-column: span 2;">
                <h2 data-icon="🎵">Now Playing - Enhanced with AI</h2>
                
                <?php if ($currentSong && isset($currentSong['data'])): ?>
                    <div class="current-song">
                        <h3><?php echo htmlspecialchars($currentSong['data']['title']); ?></h3>
                        <p><strong>Artist:</strong> <?php echo htmlspecialchars($currentSong['data']['artist']); ?></p>
                        <p><strong>Album:</strong> <?php echo htmlspecialchars($currentSong['data']['album'] ?? '-'); ?></p>
                        <p><strong>Year:</strong> <?php echo htmlspecialchars($currentSong['data']['year'] ?? '-'); ?></p>
                        <p><strong>Label:</strong> <?php echo htmlspecialchars($currentSong['data']['label'] ?? '-'); ?></p>
                        <p><strong>Updated:</strong> <?php echo date('H:i:s', $currentSong['timestamp']); ?>
                           (<?php echo formatDuration($currentSong['age_seconds']); ?> ago)</p>
                        
                        <p style="margin-top: 10px;"><strong>Metadata Completeness:</strong> 
                           <?php echo $currentSong['metadata_completeness']; ?>%</p>
                        
                        <?php if (!empty($currentSong['data']['all_sources'])): ?>
                        <div class="service-status">
                            <?php foreach ($currentSong['data']['all_sources'] as $source): ?>
                                <div class="service-item enabled">
                                    ✅ <?php echo $source; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- AI Trivia Information -->
                        <?php if (isset($currentSong['ai_trivia'])): ?>
                        <div class="ai-trivia-info">
                            <h4>🎩 AI Gentleman Content Status</h4>
                            
                            <?php foreach (['en', 'sk'] as $lang): ?>
                                <?php $trivia = $currentSong['ai_trivia'][$lang]; ?>
                                <p><strong><?php echo strtoupper($lang); ?>:</strong> 
                                <?php if ($trivia && $trivia['available']): ?>
                                    ✅ Available (Quality: <?php echo $trivia['quality_score']; ?>%, 
                                    Words: <?php echo $trivia['word_count']; ?>, 
                                    Provider: <?php echo $trivia['provider']; ?>)
                                <?php else: ?>
                                    ❌ Not generated
                                <?php endif; ?>
                                </p>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #64748b; font-style: italic;">No song data available</p>
                <?php endif; ?>
            </div>
            
            <!-- Service Status -->
            <div class="card">
                <h2 data-icon="🔧">Service Status</h2>
                
                <div class="service-status">
                    <?php foreach ($systemStatus['services'] as $service => $enabled): ?>
                        <?php if (is_array($enabled)) continue; ?>
                        <div class="service-item <?php echo $enabled ? 'enabled' : 'disabled'; ?>">
                            <?php echo $enabled ? '✅' : '❌'; ?> 
                            <?php echo ucfirst(str_replace('_', ' ', $service)); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($systemStatus['services']['ai_providers'])): ?>
                <div style="margin-top: 15px; padding: 10px; background: rgba(139, 61, 255, 0.1); border-radius: 8px;">
                    <p style="color: #8b3dff; font-size: 0.9rem; text-align: center;">
                        🤖 AI Providers: <?php echo implode(', ', $systemStatus['services']['ai_providers']); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- AI Content Statistics -->
            <div class="card">
                <h2 data-icon="🤖">AI Gentleman Content</h2>
                
                <div class="metric">
                    <span class="metric-label">Total Generated</span>
                    <span class="metric-value"><?php echo $enhancedStats['ai_content_stats']['total_trivia_generated']; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Avg Gentleman Score</span>
                    <span class="metric-value"><?php echo $enhancedStats['ai_content_stats']['avg_gentleman_score']; ?>%</span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Avg Word Count</span>
                    <span class="metric-value"><?php echo $enhancedStats['ai_content_stats']['avg_word_count']; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">English Content</span>
                    <span class="metric-value"><?php echo $enhancedStats['ai_content_stats']['languages']['en'] ?? 0; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Slovak Content</span>
                    <span class="metric-value"><?php echo $enhancedStats['ai_content_stats']['languages']['sk'] ?? 0; ?></span>
                </div>
            </div>

            <!-- Metadata Quality -->
            <div class="card">
                <h2 data-icon="📊">Metadata Quality</h2>
                
                <div class="metric">
                    <span class="metric-label">Average Completeness</span>
                    <span class="metric-value"><?php echo $enhancedStats['avg_completeness']; ?>%</span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Excellent (90%+)</span>
                    <span class="metric-value"><?php echo $enhancedStats['metadata_quality']['excellent']; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Good (70-89%)</span>
                    <span class="metric-value"><?php echo $enhancedStats['metadata_quality']['good']; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Fair (50-69%)</span>
                    <span class="metric-value"><?php echo $enhancedStats['metadata_quality']['fair']; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Poor (<50%)</span>
                    <span class="metric-value <?php echo $enhancedStats['metadata_quality']['poor'] > 0 ? 'warning' : ''; ?>">
                        <?php echo $enhancedStats['metadata_quality']['poor']; ?>
                    </span>
                </div>
            </div>

            <!-- Active Sources Usage -->
            <div class="card">
                <h2 data-icon="🔌">Active Sources Usage</h2>
                
                <?php if (!empty($enhancedStats['sources_usage'])): ?>
                    <?php 
                    arsort($enhancedStats['sources_usage']);
                    foreach ($enhancedStats['sources_usage'] as $source => $count): 
                        $percentage = round(($count / $enhancedStats['total_songs']) * 100, 1);
                    ?>
                    <div class="metric">
                        <span class="metric-label"><?php echo $source; ?></span>
                        <span class="metric-value"><?php echo $count; ?> (<?php echo $percentage; ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #64748b;">No source data available</p>
                <?php endif; ?>
            </div>

            <!-- System Health -->
            <div class="card">
                <h2 data-icon="🏥">System Health</h2>
                
                <?php foreach ($systemStatus['files'] as $file => $exists): ?>
                <div class="metric">
                    <span class="metric-label"><?php echo ucwords(str_replace('_', ' ', $file)); ?></span>
                    <span class="metric-value <?php echo $exists ? '' : 'error'; ?>">
                        <?php echo $exists ? '✅ Online' : '❌ Missing'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
                
                <div class="metric">
                    <span class="metric-label">Songs in History</span>
                    <span class="metric-value"><?php echo $enhancedStats['total_songs']; ?></span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Recognition Active</span>
                    <span class="metric-value <?php echo $systemStatus['recognition']['active'] ? '' : 'warning'; ?>">
                        <?php echo $systemStatus['recognition']['active'] ? '✅ Active' : '⚠️ Inactive'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card" style="margin-bottom: 20px;">
            <h2 data-icon="📝">Recent System Activity</h2>
            
            <div class="log-container">
                <?php if (!empty($recentLogs)): ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <?php
                        $logClass = '';
                        if (strpos($log, '[ERROR]') !== false) $logClass = 'error';
                        elseif (strpos($log, '[Spotify]') !== false) $logClass = 'spotify';
                        elseif (strpos($log, '[AI]') !== false) $logClass = 'ai';
                        elseif (strpos($log, '[Manual]') !== false) $logClass = 'manual';
                        ?>
                        <div class="log-entry <?php echo $logClass; ?>"><?php echo htmlspecialchars(trim($log)); ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #64748b; font-style: italic;">No log entries available</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="actions">
            <a href="/" class="btn">
                🏠 Back to Radio
            </a>
            <a href="webhook_secure.php?action=current" class="btn secondary" target="_blank">
                📄 Current Song JSON
            </a>
            <a href="webhook_secure.php?action=stats" class="btn secondary" target="_blank">
                📊 Full Stats JSON
            </a>
            <a href="webhook_secure.php?action=health" class="btn secondary" target="_blank">
                🏥 Health Check
            </a>
            <a href="backup.php?action=list" class="btn secondary">
                💾 View Backups
            </a>
            <a href="backup.php?action=create" class="btn danger" onclick="return confirm('Create backup now?')">
                📦 Create Backup
            </a>
        </div>
        
        <div class="refresh-notice">
            <?php if ($autoRefresh): ?>
                <strong>Auto-refresh enabled:</strong> This page refreshes every <?php echo $refreshInterval; ?> seconds
            <?php else: ?>
                <strong>Auto-refresh disabled:</strong> Click refresh to update manually
            <?php endif; ?>
            <br>
            <small>System time: <?php echo date('Y-m-d H:i:s'); ?> | 
            Secure Version 2.0 | 
            AI Gentleman Score: <?php echo $enhancedStats['ai_content_stats']['avg_gentleman_score']; ?>%</small>
        </div>
    </div>
</body>
</html>