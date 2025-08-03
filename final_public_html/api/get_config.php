<?php
// Pridaj toto NA ÚPLNÝ ZAČIATOK súboru
error_reporting(0);
ini_set('display_errors', 0);

// Skontroluj či config.php existuje
if (!file_exists('config.php')) {
    // Ak neexistuje, použi základnú konfiguráciu
    header('Content-Type: application/json');
    echo json_encode([
        'config_loaded' => false,
        'STREAM_URL' => 'https://fra-pioneer01.dedicateware.com:2415/stream',
        'ICY_SERVER' => 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl',
        'services_enabled' => [
            'spotify' => false,
            'youtube' => false,
            'lastfm' => false,
            'musicbrainz' => true,
            'deezer' => true
        ]
    ]);
    exit();
}

// Pokračuj s existujúcim kódom...
// Nastavenie základných headerov
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

// CORS pre frontend
$allowedOrigins = [
    'http://localhost',
    'https://localhost', 
    'http://127.0.0.1',
    'https://127.0.0.1'
];

if (isset($_SERVER['HTTP_HOST'])) {
    $allowedOrigins[] = 'http://' . $_SERVER['HTTP_HOST'];
    $allowedOrigins[] = 'https://' . $_SERVER['HTTP_HOST'];
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}

// Pokus o načítanie konfigurácie
$config_loaded = false;
if (file_exists('config.php')) {
    try {
        require_once '../config.php';
        $config_loaded = true;
    } catch (Exception $e) {
        error_log('Config load error: ' . $e->getMessage());
    }
}

// Funkcia pre bezpečné získanie hodnoty
function getConfigValue($key, $default = '') {
    return defined($key) ? constant($key) : $default;
}

// Funkcia pre kontrolu služby
function isServiceEnabledSafe($service) {
    switch (strtolower($service)) {
        case 'spotify':
            return !empty(getConfigValue('SPOTIFY_CLIENT_ID'));
        case 'youtube':
            return !empty(getConfigValue('YOUTUBE_API_KEY'));
        case 'lastfm':
            return !empty(getConfigValue('LASTFM_API_KEY'));
        case 'musicbrainz':
            return getConfigValue('MUSICBRAINZ_ENABLED', 'true') === 'true';
        case 'discogs':
            return getConfigValue('DISCOGS_ENABLED', 'true') === 'true';
        case 'deezer':
            return getConfigValue('DEEZER_ENABLED', 'true') === 'true';
        default:
            return false;
    }
}

// Zostavenie konfigurácie
$config = [
    'config_loaded' => $config_loaded,
    'timestamp' => time()
];

// Základné nastavenia (vždy dostupné)
$config['STREAM_URL'] = getConfigValue('STREAM_URL', 'https://fra-pioneer01.dedicateware.com:2415/stream');
$config['ICY_SERVER'] = getConfigValue('ICY_SERVER', 'http://fra-pioneer01.dedicateware.com:2415/status-json.xsl');

// API kľúče (iba bezpečné pre frontend)
$safe_keys = [
    'YOUTUBE_API_KEY',
    'SPOTIFY_CLIENT_ID', 
    'LASTFM_API_KEY'
];

foreach ($safe_keys as $key) {
    $value = getConfigValue($key);
    if (!empty($value)) {
        $config[$key] = $value;
    }
}

// Informácie o dostupných službách
$config['services_enabled'] = [
    'spotify' => isServiceEnabledSafe('spotify'),
    'youtube' => isServiceEnabledSafe('youtube'),
    'lastfm' => isServiceEnabledSafe('lastfm'),
    'musicbrainz' => isServiceEnabledSafe('musicbrainz'),
    'discogs' => isServiceEnabledSafe('discogs'),
    'deezer' => isServiceEnabledSafe('deezer')
];

// Debug informácie (iba v debug móde)
if (getConfigValue('DEBUG_MODE', 'false') === 'true') {
    $config['debug'] = [
        'config_file_exists' => file_exists('config.php'),
        'env_file_exists' => file_exists('.env'),
        'data_dir_writable' => is_writable(getConfigValue('DATA_DIR', 'data/')),
        'php_version' => PHP_VERSION
    ];
}

// Ak konfigurácia nebola načítaná, pridaj error
if (!$config_loaded) {
    $config['error'] = 'Configuration could not be loaded';
    $config['message'] = 'Please check if config.php exists and is valid';
}

// Pošli konfiguráciu
echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
