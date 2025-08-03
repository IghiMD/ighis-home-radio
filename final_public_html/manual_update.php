<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config if available
if (file_exists('config.php')) {
    require_once 'config.php';
}

// Define paths if not defined
if (!defined('CURRENT_SONG_FILE')) {
    define('CURRENT_SONG_FILE', 'data/current_song.json');
}

function updateCurrentSong() {
    // Try metadata proxy first
    $metadataUrl = 'metadata_proxy.php';
    $ch = curl_init($metadataUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = @json_decode($response, true);
    
    if ($data && isset($data['parsed']['raw_title'])) {
        $title = $data['parsed']['raw_title'];
        
        // Parse artist - title
        $parts = explode(' - ', $title, 2);
        $artist = trim($parts[0] ?? 'Unknown Artist');
        $songTitle = trim($parts[1] ?? $title);
        
        // Update current song
        $currentSong = [
            'timestamp' => time(),
            'data' => [
                'title' => $songTitle,
                'artist' => $artist,
                'album' => null,
                'year' => null,
                'artwork' => null,
                'genre' => null,
                'source' => 'ICY'
            ]
        ];
        
        // Save to file
        $dir = dirname(CURRENT_SONG_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        file_put_contents(CURRENT_SONG_FILE, json_encode($currentSong, JSON_PRETTY_PRINT));
        
        echo "<h2>✅ Song Updated Successfully!</h2>";
        echo "<p><strong>Artist:</strong> $artist</p>";
        echo "<p><strong>Title:</strong> $songTitle</p>";
        echo "<p><strong>Time:</strong> " . date('H:i:s') . "</p>";
    } else {
        echo "<h2>❌ Update Failed</h2>";
        echo "<p>Could not fetch metadata from stream.</p>";
    }
    
    echo '<p><a href="monitor.php">← Back to Monitor</a></p>';
}

// Handle update request
if (isset($_POST['update'])) {
    updateCurrentSong();
} else {
    header('Location: monitor.php');
}
?>
