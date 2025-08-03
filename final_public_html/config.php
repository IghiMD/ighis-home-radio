<?php
/**
 * Enhanced Configuration System for Ighi's Home Radio
 * SECURE VERSION - Loads environment variables and defines system constants
 */

// Prevent direct access
if (!defined('CONFIG_LOADED')) {
    define('CONFIG_LOADED', true);
}

// Error reporting for development (disable in production)
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0); // Always off for security
ini_set('log_errors', 1);

// =============================================
// ENVIRONMENT LOADER
// =============================================

/**
 * Load environment variables from .env file
 */
function loadEnvironment($envFile = '.env') {
    if (!file_exists($envFile)) {
        // Try alternative locations
        $locations = ['.env', '../.env', '../../.env', dirname(__FILE__) . '/.env'];
        
        foreach ($locations as $location) {
            if (file_exists($location)) {
                $envFile = $location;
                break;
            }
        }
        
        if (!file_exists($envFile)) {
            error_log("Warning: .env file not found. Using default configuration.");
            return false;
        }
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (strpos(trim($line), '#') === 0 || trim($line) === '') {
            continue;
        }
        
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            
            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
    
    return true;
}

// Load environment variables
loadEnvironment();

// =============================================
// HELPER FUNCTIONS
// =============================================

/**
 * Get environment variable with default value
 */
function env($key, $default = null) {
    $value = getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // Convert string representations to appropriate types
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }
    
    return $value;
}

/**
 * Check if a service is enabled
 */
function isServiceEnabled($service) {
    switch (strtolower($service)) {
        case 'spotify':
            return !empty(SPOTIFY_CLIENT_ID) && !empty(SPOTIFY_CLIENT_SECRET);
        case 'youtube':
            return !empty(YOUTUBE_API_KEY);
        case 'lastfm':
            return !empty(LASTFM_API_KEY);
        case 'musicbrainz':
            return defined('MUSICBRAINZ_ENABLED') && MUSICBRAINZ_ENABLED === 'true';
        case 'deezer':
            return defined('DEEZER_ENABLED') && DEEZER_ENABLED === 'true';
        case 'perplexity':
            return !empty(PERPLEXITY_API_KEY);
        case 'openai':
            return !empty(OPENAI_API_KEY);
        case 'gemini':
            return !empty(GEMINI_API_KEY);
        case 'deepl':
            return !empty(DEEPL_API_KEY);
        default:
            return false;
    }
}

/**
 * Get active metadata services in priority order
 */
function getActiveMetadataServices() {
    $services = [];
    
    if (isServiceEnabled('spotify')) $services[] = 'Spotify';
    if (isServiceEnabled('musicbrainz')) $services[] = 'MusicBrainz';
    if (isServiceEnabled('lastfm')) $services[] = 'LastFm';
    if (isServiceEnabled('deezer')) $services[] = 'Deezer';
    if (isServiceEnabled('youtube')) $services[] = 'YouTube';
    
    return $services;
}

/**
 * Get active AI services
 */
function getActiveAIServices() {
    $services = [];
    
    if (isServiceEnabled('perplexity')) $services[] = 'Perplexity';
    if (isServiceEnabled('openai')) $services[] = 'OpenAI';
    if (isServiceEnabled('gemini')) $services[] = 'Gemini';
    
    return $services;
}

/**
 * Get active OpenAI key (with fallback support)
 */
function getActiveOpenAIKey() {
    // Check if primary key is marked as failed
    $failedFile = DATA_DIR . '.openai_primary_failed';
    $primaryFailed = file_exists($failedFile) && (time() - filemtime($failedFile)) < 3600; // 1 hour cooldown
    
    if (!$primaryFailed && !empty(OPENAI_API_KEY)) {
        return OPENAI_API_KEY;
    }
    
    // Use backup key if available
    if (!empty(OPENAI_API_KEY_BACKUP)) {
        return OPENAI_API_KEY_BACKUP;
    }
    
    return null;
}

/**
 * Mark primary OpenAI key as failed
 */
function markOpenAIKeyFailed() {
    $failedFile = DATA_DIR . '.openai_primary_failed';
    file_put_contents($failedFile, time());
}

/**
 * Ensure directory exists
 */
function ensureDirectory($path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) {
            error_log("Failed to create directory: $path");
            return false;
        }
    }
    return true;
}

// =============================================
// CORE CONFIGURATION
// =============================================

// Timezone
$timezone = env('TIMEZONE', 'Europe/Prague');
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set($timezone);
}

// Debug mode
define('DEBUG_MODE', env('DEBUG_MODE', 'false') === 'true');

// =============================================
// STREAM CONFIGURATION
// =============================================
define('STREAM_URL', env('STREAM_URL', 'https://fra-pioneer01.dedicateware.com:2415/stream'));
define('ICY_SERVER', env('ICY_SERVER', 'metadata_proxy.php'));

// =============================================
// API KEYS - METADATA SERVICES
// =============================================
define('SPOTIFY_CLIENT_ID', env('SPOTIFY_CLIENT_ID', ''));
define('SPOTIFY_CLIENT_SECRET', env('SPOTIFY_CLIENT_SECRET', ''));
define('YOUTUBE_API_KEY', env('YOUTUBE_API_KEY', ''));
define('LASTFM_API_KEY', env('LASTFM_API_KEY', ''));

// MusicBrainz (free service)
define('MUSICBRAINZ_ENABLED', env('MUSICBRAINZ_ENABLED', 'true'));
define('MUSICBRAINZ_USER_AGENT', env('MUSICBRAINZ_USER_AGENT', 'IghisHomeRadio/1.0 (hello@ighishomeradio.com)'));

// Deezer (free service)
define('DEEZER_ENABLED', env('DEEZER_ENABLED', 'true'));

// =============================================
// API KEYS - CHAT & SUPPORT
// =============================================
define('PERPLEXITY_API_KEY', env('PERPLEXITY_API_KEY', ''));
define('OPENAI_API_KEY', env('OPENAI_API_KEY', ''));
define('OPENAI_API_KEY_BACKUP', env('OPENAI_API_KEY_BACKUP', ''));
define('GEMINI_API_KEY', env('GEMINI_API_KEY', ''));
define('DEEPL_API_KEY', env('DEEPL_API_KEY', ''));
define('CRISP_WEBSITE_ID', env('CRISP_WEBSITE_ID', ''));

// =============================================
// ADMIN CONFIGURATION
// =============================================
define('ADMIN_USERNAME', env('ADMIN_USERNAME', 'admin'));
define('ADMIN_PASSWORD', env('ADMIN_PASSWORD', 'changeme'));
define('ADMIN_TOKEN', env('ADMIN_TOKEN', 'secure-random-token-change-this'));

// =============================================
// DIRECTORY STRUCTURE
// =============================================

// Base directories
define('BASE_DIR', dirname(__FILE__) . '/');
define('DATA_DIR', BASE_DIR . 'data/');
define('CACHE_DIR', BASE_DIR . 'cache/');
define('LOGS_DIR', BASE_DIR . 'logs/');
define('BACKUP_DIR', BASE_DIR . 'backups/');
define('AI_CACHE_DIR', CACHE_DIR . 'cache/ai/');

// Create directories if they don't exist
$directories = [DATA_DIR, CACHE_DIR, LOGS_DIR, BACKUP_DIR, AI_CACHE_DIR];
foreach ($directories as $dir) {
    ensureDirectory($dir);
    
    // Create .htaccess for security
    $htaccessFile = $dir . '.htaccess';
    if (!file_exists($htaccessFile)) {
        file_put_contents($htaccessFile, "Order deny,allow\nDeny from all\n");
    }
}

// =============================================
// FILE PATHS
// =============================================

// Core data files
define('CURRENT_SONG_FILE', DATA_DIR . 'current_song.json');
define('HISTORY_FILE', DATA_DIR . 'history.json');
define('ENRICHMENT_STATUS_FILE', DATA_DIR . 'enrichment_status.json');

// Cache files
define('METADATA_CACHE_FILE', CACHE_DIR . 'metadata.json');
define('TRIVIA_CACHE_FILE', CACHE_DIR . 'trivia.json');

// Log files
define('LOG_FILE', LOGS_DIR . 'system.log');
define('AUTH_LOG_FILE', LOGS_DIR . 'auth.log');

// =============================================
// CACHE CONFIGURATION
// =============================================
define('CACHE_EXPIRY', (int)env('CACHE_EXPIRY', 86400)); // 24 hours
define('TRIVIA_CACHE_EXPIRY', (int)env('TRIVIA_CACHE_EXPIRY', 604800)); // 7 days
define('METADATA_CACHE_EXPIRY', (int)env('METADATA_CACHE_EXPIRY', 259200)); // 3 days

// =============================================
// BACKUP CONFIGURATION
// =============================================
define('MAX_BACKUPS', (int)env('MAX_BACKUPS', 168)); // 7 days * 24 hours
define('BACKUP_ENABLED', env('BACKUP_ENABLED', 'true') === 'true');
define('AUTO_BACKUP', env('AUTO_BACKUP', 'true') === 'true');

// =============================================
// AI CONFIGURATION
// =============================================
define('AI_MAX_WORDS', (int)env('AI_MAX_WORDS', 60));
define('AI_STYLE', env('AI_STYLE', 'gentleman'));
define('AI_MIN_TIMER', (int)env('AI_MIN_TIMER', 8));
define('AI_DEFAULT_TIMER', (int)env('AI_DEFAULT_TIMER', 25));

// =============================================
// SERVICE PRIORITY CONFIGURATION
// =============================================
define('SERVICE_PRIORITY', [
    'Spotify' => (int)env('PRIORITY_SPOTIFY', 1),
    'MusicBrainz' => (int)env('PRIORITY_MUSICBRAINZ', 2),
    'LastFm' => (int)env('PRIORITY_LASTFM', 3),
    'Deezer' => (int)env('PRIORITY_DEEZER', 4),
    'YouTube' => (int)env('PRIORITY_YOUTUBE', 5)
]);

// =============================================
// RATE LIMITING CONFIGURATION
// =============================================
define('SPOTIFY_RATE_LIMIT', (int)env('SPOTIFY_RATE_LIMIT', 100)); // per hour
define('YOUTUBE_RATE_LIMIT', (int)env('YOUTUBE_RATE_LIMIT', 10000)); // per day
define('DEEPL_RATE_LIMIT', (int)env('DEEPL_RATE_LIMIT', 500000)); // per month
define('LASTFM_RATE_LIMIT', (int)env('LASTFM_RATE_LIMIT', 300)); // per hour
define('MUSICBRAINZ_RATE_LIMIT', (int)env('MUSICBRAINZ_RATE_LIMIT', 50)); // per second (1/sec)

// =============================================
// SYSTEM SETTINGS
// =============================================
define('MIN_METADATA_QUALITY', (int)env('MIN_METADATA_QUALITY', 50));
define('AUTO_ENRICH', env('AUTO_ENRICH', 'true') === 'true');
define('ENRICH_DELAY', (int)env('ENRICH_DELAY', 1000)); // milliseconds
define('NOTIFICATION_DURATION', (int)env('NOTIFICATION_DURATION', 4000)); // milliseconds
define('ICY_CHECK_INTERVAL', (int)env('ICY_CHECK_INTERVAL', 3000)); // milliseconds

// =============================================
// RUNTIME CONFIGURATION
// =============================================

// Set memory limit for larger operations
ini_set('memory_limit', '256M');

// Set maximum execution time
set_time_limit(120);

// Set upload limits
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');

// Session configuration
ini_set('session.gc_maxlifetime', 14400); // 4 hours
ini_set('session.cookie_lifetime', 0); // Until browser closes
ini_set('session.name', 'IGHI_SESSION');

// =============================================
// VALIDATION AND WARNINGS
// =============================================

/**
 * Validate critical configuration
 */
function validateConfiguration() {
    $warnings = [];
    $errors = [];
    
    // Check critical services
    if (!isServiceEnabled('spotify') && !isServiceEnabled('musicbrainz') && !isServiceEnabled('deezer')) {
        $warnings[] = 'No metadata services enabled. Music recognition will be limited.';
    }
    
    if (!isServiceEnabled('perplexity') && !isServiceEnabled('openai') && !isServiceEnabled('gemini')) {
        $warnings[] = 'No AI services enabled. AI content generation will not work.';
    }
    
    // Check admin credentials
    if (ADMIN_PASSWORD === 'changeme' || ADMIN_PASSWORD === 'TuDajSvojeSilneHeslo123!') {
        $errors[] = 'Default admin password detected! Please change ADMIN_PASSWORD in .env file.';
    }
    
    if (ADMIN_TOKEN === 'secure-random-token-change-this' || ADMIN_TOKEN === 'TuDajNahodnyToken-abc123xyz') {
        $errors[] = 'Default admin token detected! Please change ADMIN_TOKEN in .env file.';
    }
    
    // Check file permissions
    if (!is_writable(DATA_DIR)) {
        $errors[] = 'Data directory is not writable: ' . DATA_DIR;
    }
    
    if (!is_writable(LOGS_DIR)) {
        $errors[] = 'Logs directory is not writable: ' . LOGS_DIR;
    }
    
    // Log validation results
    if (!empty($errors)) {
        foreach ($errors as $error) {
            error_log("CONFIG ERROR: $error");
        }
    }
    
    if (!empty($warnings)) {
        foreach ($warnings as $warning) {
            error_log("CONFIG WARNING: $warning");
        }
    }
    
    return [
        'errors' => $errors,
        'warnings' => $warnings,
        'valid' => empty($errors)
    ];
}

// =============================================
// AI OPTIMALIZÁCIE PRE SARKASTICKÝ SYSTÉM
// =============================================

// Vylepšené AI nastavenia
if (!defined('AI_SARCASTIC_MODE')) {
    define('AI_SARCASTIC_MODE', true);
}

if (!defined('AI_STYLES')) {
    define('AI_STYLES', ['sarcastic', 'witty', 'sophisticated', 'insider', 'historian']);
}

if (!defined('AI_GENERATION_TIMEOUT')) {
    define('AI_GENERATION_TIMEOUT', 25); // sekundy
}

if (!defined('AI_CACHE_DURATION')) {
    define('AI_CACHE_DURATION', 86400); // 24 hodín
}

// =============================================
// VYLEPŠENÉ API RATE LIMITING
// =============================================

// Gemini rate limiting (60 req/min free tier)
if (!defined('GEMINI_RATE_LIMIT')) {
    define('GEMINI_RATE_LIMIT', 50); // Per minútu (buffer)
}

// OpenAI rate limiting
if (!defined('OPENAI_RATE_LIMIT')) {
    define('OPENAI_RATE_LIMIT', 200); // Per minútu
}

// =============================================
// HELPER FUNKCIE PRE AI SYSTÉM
// =============================================

/**
 * Kontrola dostupnosti sarkastického AI systému
 */
function isSarcasticAIAvailable() {
    return (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) ||
           (defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY)) ||
           (defined('PERPLEXITY_API_KEY') && !empty(PERPLEXITY_API_KEY));
}

/**
 * Získaj najlepší dostupný AI provider
 */
function getBestAIProvider() {
    // Priorita: Gemini (free + dobré), OpenAI (paid + výborné), Perplexity (paid + online)
    if (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
        return 'gemini';
    }
    
    if (defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY)) {
        return 'openai';
    }
    
    if (defined('PERPLEXITY_API_KEY') && !empty(PERPLEXITY_API_KEY)) {
        return 'perplexity';
    }
    
    return null;
}

/**
 * AI Rate limiting check
 */
function checkAIRateLimit($provider = 'gemini') {
    $rateFile = CACHE_DIR . "rate_limit_{$provider}.json";
    
    if (!file_exists($rateFile)) {
        return true; // First request
    }
    
    $data = json_decode(file_get_contents($rateFile), true);
    if (!$data) return true;
    
    $limits = [
        'gemini' => 50,    // per minute
        'openai' => 200,   // per minute  
        'perplexity' => 20 // per minute
    ];
    
    $limit = $limits[$provider] ?? 50;
    $window = 60; // 1 minute
    
    // Clean old requests
    $now = time();
    $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now, $window) {
        return ($now - $timestamp) < $window;
    });
    
    // Check if under limit
    if (count($data['requests']) >= $limit) {
        return false; // Rate limited
    }
    
    // Add current request
    $data['requests'][] = $now;
    file_put_contents($rateFile, json_encode($data));
    
    return true;
}

/**
 * Log AI usage pre debugging
 */
function logAIUsage($provider, $style, $success, $tokens = 0) {
    if (!defined('DEBUG_MODE') || !DEBUG_MODE) return;
    
    $logEntry = [
        'timestamp' => time(),
        'datetime' => date('Y-m-d H:i:s'),
        'provider' => $provider,
        'style' => $style,
        'success' => $success,
        'tokens' => $tokens
    ];
    
    $logFile = LOGS_DIR . 'ai_usage.log';
    $logLine = json_encode($logEntry) . "\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Vyčisti AI cache ak je potrebné
 */
function cleanAICache() {
    if (!is_dir(AI_CACHE_DIR)) return;
    
    $files = glob(AI_CACHE_DIR . '*.json');
    $cleaned = 0;
    
    foreach ($files as $file) {
        // Vymaž súbory staršie ako 7 dní
        if (time() - filemtime($file) > 604800) {
            if (unlink($file)) {
                $cleaned++;
            }
        }
    }
    
    if ($cleaned > 0) {
        error_log("AI Cache cleaned: $cleaned files removed");
    }
    
    return $cleaned;
}

/**
 * Optimalizované nastavenia pre rôzne prostredia
 */
function optimizeForEnvironment() {
    // Production optimalizácie
    if (!DEBUG_MODE) {
        // Vypni verbose logging
        ini_set('log_errors_max_len', 1024);
        
        // Optimalizuj cache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
    
    // Vyčisti starý cache pri inicializácii
    if (rand(1, 100) <= 5) { // 5% šanca
        cleanAICache();
    }
}

/**
 * Rozšírená validácia pre AI systém
 */
function validateAIConfiguration() {
    $validation = [
        'ai_available' => isSarcasticAIAvailable(),
        'best_provider' => getBestAIProvider(),
        'cache_writable' => is_writable(AI_CACHE_DIR),
        'rate_limiting' => true,
        'warnings' => [],
        'recommendations' => []
    ];
    
    // Kontroly a odporúčania
    if (!$validation['ai_available']) {
        $validation['warnings'][] = 'No AI services configured - sarcastic mode will use fallbacks';
        $validation['recommendations'][] = 'Get free Gemini API key at https://makersuite.google.com/app/apikey';
    }
    
    if (!$validation['cache_writable']) {
        $validation['warnings'][] = 'AI cache directory not writable - performance may be affected';
        $validation['recommendations'][] = 'Set write permissions for ' . AI_CACHE_DIR;
    }
    
    // Skontroluj API kľúče
    $apiKeys = ['GEMINI_API_KEY', 'OPENAI_API_KEY', 'PERPLEXITY_API_KEY'];
    $validKeys = 0;
    
    foreach ($apiKeys as $key) {
        if (defined($key) && !empty(constant($key))) {
            $validKeys++;
        }
    }
    
    if ($validKeys === 0) {
        $validation['warnings'][] = 'No AI API keys found in configuration';
    } elseif ($validKeys === 1) {
        $validation['recommendations'][] = 'Consider adding backup AI provider for redundancy';
    }
    
    return $validation;
}

// =============================================
// INITIALIZATION
// =============================================

// Validate configuration
$configValidation = validateConfiguration();

// Initialize log file
if (!file_exists(LOG_FILE)) {
    $initialLog = "[" . date('Y-m-d H:i:s') . "] [SYSTEM] Configuration loaded and validated\n";
    file_put_contents(LOG_FILE, $initialLog);
}

// Log configuration status
$logMessage = "[" . date('Y-m-d H:i:s') . "] [CONFIG] System initialized - ";
$logMessage .= "Metadata services: " . implode(', ', getActiveMetadataServices()) . " - ";
$logMessage .= "AI services: " . implode(', ', getActiveAIServices()) . "\n";
file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);

// Spusti optimalizácie
optimizeForEnvironment();

// Validuj AI konfiguráciu
$aiValidation = validateAIConfiguration();

// Log výsledky ak je debug mode
if (DEBUG_MODE) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] [CONFIG] AI System initialized - ";
    $logMessage .= "Available: " . ($aiValidation['ai_available'] ? 'YES' : 'NO') . ", ";
    $logMessage .= "Provider: " . ($aiValidation['best_provider'] ?? 'NONE') . ", ";
    $logMessage .= "Warnings: " . count($aiValidation['warnings']);
    
    file_put_contents(LOG_FILE, $logMessage . "\n", FILE_APPEND | LOCK_EX);
}

// Globálne dostupné AI info
$GLOBALS['AI_CONFIG'] = $aiValidation;

/**
 * Helper funkcia pre získanie AI config
 */
function getAIConfig() {
    return $GLOBALS['AI_CONFIG'] ?? ['ai_available' => false];
}

// =============================================
// CONSTANTS FOR COMPATIBILITY & AI
// =============================================

// Legacy constants for backward compatibility
if (!defined('WEBHOOK_URL')) {
    define('WEBHOOK_URL', 'webhook.php');
}

if (!defined('AI_INFO_URL')) {
    define('AI_INFO_URL', 'ai_info.php');
}

// AI Constants
if (!defined('AI_ENDPOINT')) {
    define('AI_ENDPOINT', 'ai_info.php');
}

if (!defined('AI_FALLBACK_ENABLED')) {
    define('AI_FALLBACK_ENABLED', true);
}

if (!defined('AI_DEBUG_MODE')) {
    define('AI_DEBUG_MODE', DEBUG_MODE);
}

// =============================================
// ERROR HANDLING IMPROVEMENTS
// =============================================

/**
 * Custom error handler pre AI operácie
 */
function handleAIError($errno, $errstr, $errfile, $errline) {
    if (strpos($errfile, 'ai_info') !== false || strpos($errstr, 'AI') !== false) {
        $errorLog = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $errno,
            'message' => $errstr,
            'file' => basename($errfile),
            'line' => $errline
        ];
        
        $logFile = LOGS_DIR . 'ai_errors.log';
        file_put_contents($logFile, json_encode($errorLog) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    return false; // Let PHP handle other errors normally
}

// Registruj error handler len ak je AI systém aktívny
if (isSarcasticAIAvailable()) {
    set_error_handler('handleAIError', E_WARNING | E_NOTICE);
}

// =============================================
// FINAL SETUP
// =============================================

// Register shutdown function for cleanup
register_shutdown_function(function() {
    // Log any fatal errors
    $error = error_get_last();
    if ($error && $error['type'] === E_ERROR) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [FATAL] " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'] . "\n";
        file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
    }
});

// Configuration loaded successfully
if (DEBUG_MODE) {
    error_log("Ighi's Home Radio configuration loaded successfully");
}

/**
 * Get system status for monitoring
 */
function getSystemStatus() {
    return [
        'config_valid' => $GLOBALS['configValidation']['valid'],
        'metadata_services' => count(getActiveMetadataServices()),
        'ai_services' => count(getActiveAIServices()),
        'directories_writable' => is_writable(DATA_DIR) && is_writable(LOGS_DIR) && is_writable(CACHE_DIR),
        'backup_enabled' => BACKUP_ENABLED,
        'debug_mode' => DEBUG_MODE
    ];
}

/**
 * Get configuration summary
 */
function getConfigSummary() {
    return [
        'version' => '2.0-sarcastic',
        'loaded_at' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'services' => [
            'metadata' => getActiveMetadataServices(),
            'ai' => getActiveAIServices()
        ],
        'directories' => [
            'data' => DATA_DIR,
            'cache' => CACHE_DIR,
            'logs' => LOGS_DIR,
            'backups' => BACKUP_DIR
        ],
        'validation' => $configValidation
    ];
}

// Mark configuration as fully loaded with AI optimization
define('CONFIG_FULLY_LOADED', true);
define('AI_OPTIMIZATION_LOADED', true);
