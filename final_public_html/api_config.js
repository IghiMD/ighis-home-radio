// api_config.js - Bezpečné načítanie API konfigurácie
// Tento súbor načíta API kľúče zo servera, nie sú v kóde!

async function getApiConfig() {
    try {
        const response = await fetch('get_config.php');
        if (!response.ok) throw new Error('Failed to load config');
        return await response.json();
    } catch (error) {
        console.error('Config load error:', error);
        return null;
    }
}

// Globálna konfigurácia (bez API kľúčov)
const CONFIG = {
    // Stream
    STREAM_URL: 'https://fra-pioneer01.dedicateware.com:2415/stream',
    RADIO_LOGO: 'https://i.imgur.com/Ym67jVJ.jpeg',
    
    // ICY Metadata
    ICY_SERVERS: [
        'http://fra-pioneer01.dedicateware.com:2415/status-json.xsl'
    ],
    
    // Endpoints - OPRAVENÉ NÁZVY
    AI_INFO_ENDPOINT: 'ai_info.php',
    WEBHOOK_ENDPOINT: 'webhook.php',
    
    // Intervals
    ICY_CHECK_INTERVAL: 3000,
    ENRICHMENT_DELAY: 1000,
    METADATA_TIMEOUT: 12000,
    INFO_ROTATION_INTERVAL: 30000,
    MIN_TIMER_SECONDS: 10,
    
    // AI konfigurácia
    AI_MAX_WORDS: 60,
    AI_STYLE: 'gentleman'
};

// API konfigurácia sa načíta dynamicky
let API_CONFIG = null;

// Inicializuj API konfiguráciu
async function initializeApiConfig() {
    console.log('Loading API configuration...');
    API_CONFIG = await getApiConfig();
    
    if (!API_CONFIG) {
        console.error('Failed to load API configuration');
        // Use empty config as fallback
        API_CONFIG = {
            YOUTUBE_API_KEY: '',
            SPOTIFY_CLIENT_ID: '',
            LASTFM_API_KEY: '',
            ICY_SERVER: CONFIG.ICY_SERVERS[0],
            STREAM_URL: CONFIG.STREAM_URL
        };
        return false;
    }
    
    // Merge server config with local config
    if (API_CONFIG.ICY_SERVER) {
        CONFIG.ICY_SERVERS = [API_CONFIG.ICY_SERVER];
    }
    if (API_CONFIG.STREAM_URL) {
        CONFIG.STREAM_URL = API_CONFIG.STREAM_URL;
    }
    
    console.log('API configuration loaded successfully');
    return true;
}