<?php
// Vypneme error reporting
error_reporting(0);
ini_set('display_errors', 0);

// Nastavíme JSON header
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Základná konfigurácia
$config = [
    'config_loaded' => true,
    'timestamp' => time(),
    'STREAM_URL' => 'https://fra-pioneer01.dedicateware.com:2415/stream',
    'ICY_SERVER' => 'metadata_proxy.php',
    'services_enabled' => [
        'spotify' => false,
        'youtube' => false,
        'lastfm' => false,
        'musicbrainz' => true,
        'deezer' => true
    ]
];

// Ak existuje config.php, skúsime načítať API kľúče
if (file_exists('config.php')) {
    // Zachytíme akýkoľvek výstup
    ob_start();
    @include_once 'config.php';
    ob_end_clean();
    
    // Pridáme API kľúče ak existujú
    if (defined('SPOTIFY_CLIENT_ID') && SPOTIFY_CLIENT_ID) {
        $config['SPOTIFY_CLIENT_ID'] = SPOTIFY_CLIENT_ID;
        $config['services_enabled']['spotify'] = true;
    }
    
    if (defined('YOUTUBE_API_KEY') && YOUTUBE_API_KEY) {
        $config['YOUTUBE_API_KEY'] = YOUTUBE_API_KEY;
        $config['services_enabled']['youtube'] = true;
    }
    
    if (defined('LASTFM_API_KEY') && LASTFM_API_KEY) {
        $config['LASTFM_API_KEY'] = LASTFM_API_KEY;
        $config['services_enabled']['lastfm'] = true;
    }
    
}

// Výstup JSON
echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
