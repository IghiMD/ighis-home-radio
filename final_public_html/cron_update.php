<?php
/**
 * CRON Script - Run every 2-3 minutes
 * Add to crontab: */3 * * * * php /path/to/cron_update.php
 */

// No output for cron
error_reporting(0);
ini_set('display_errors', 0);

// Load config
if (file_exists('config.php')) {
    @include_once 'config.php';
}

// Define constants if needed
if (!defined('CURRENT_SONG_FILE')) {
    define('CURRENT_SONG_FILE', 'data/current_song.json');
}

// Function to update song
function updateFromICY() {
    $metadataUrl = 'https://ighishomeradio.net/metadata_proxy.php';
    
    $ch = curl_init($metadataUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = @json_decode($response, true);
    
    if ($data && isset($data['parsed']['raw_title'])) {
        $title = $data['parsed']['raw_title'];
        
        // Check if different from current
        $currentData = @json_decode(file_get_contents(CURRENT_SONG_FILE), true);
        $currentTitle = ($currentData['data']['artist'] ?? '') . ' - ' . ($currentData['data']['title'] ?? '');
        
        if ($title !== $currentTitle) {
            // Parse and save new song
            $parts = explode(' - ', $title, 2);
            $artist = trim($parts[0] ?? 'Unknown Artist');
            $songTitle = trim($parts[1] ?? $title);
            
            $newSong = [
                'timestamp' => time(),
                'data' => [
                    'title' => $songTitle,
                    'artist' => $artist,
                    'album' => null,
                    'year' => null,
                    'source' => 'ICY'
                ]
            ];
            
            file_put_contents(CURRENT_SONG_FILE, json_encode($newSong, JSON_PRETTY_PRINT));
            
            // Log update
            $log = "[" . date('Y-m-d H:i:s') . "] Updated: $artist - $songTitle\n";
            @file_put_contents('logs/cron.log', $log, FILE_APPEND);
        }
    }
}

// Run update
updateFromICY();
?>
