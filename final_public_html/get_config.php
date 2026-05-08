<?php
/**
 * Public config endpoint - LEN bezpečné pre browser hodnoty.
 * API kľúče (YouTube, Last.fm, OpenAI, atď.) NIKDY nevracaj klientovi —
 * volaj ich server-side cez metadata_proxy.php alebo ai_info.php.
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');

$config_loaded = false;
if (file_exists(__DIR__ . '/config.php')) {
    ob_start();
    @include_once __DIR__ . '/config.php';
    ob_end_clean();
    $config_loaded = true;
}

function safe_const($name, $default = '') {
    return defined($name) ? constant($name) : $default;
}

$config = [
    'config_loaded' => $config_loaded,
    'timestamp' => time(),
    'STREAM_URL' => safe_const('STREAM_URL', 'https://fra-pioneer01.dedicateware.com:2415/stream'),
    'ICY_SERVER' => 'metadata_proxy.php',
    'services_enabled' => [
        'spotify'     => !empty(safe_const('SPOTIFY_CLIENT_ID')),
        'youtube'     => !empty(safe_const('YOUTUBE_API_KEY')),
        'lastfm'      => !empty(safe_const('LASTFM_API_KEY')),
        'musicbrainz' => safe_const('MUSICBRAINZ_ENABLED', 'true') === 'true',
        'deezer'      => safe_const('DEEZER_ENABLED', 'true') === 'true',
    ],
];

// SPOTIFY_CLIENT_ID je verejný (používa sa v Spotify Web Playback SDK auth flow).
// Client SECRET, YOUTUBE_API_KEY a LASTFM_API_KEY zostávajú server-only.
$spotifyId = safe_const('SPOTIFY_CLIENT_ID');
if (!empty($spotifyId)) {
    $config['SPOTIFY_CLIENT_ID'] = $spotifyId;
}

echo json_encode($config, JSON_UNESCAPED_UNICODE);
