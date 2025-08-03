<?php
/**
 * SIMPLE AI INFO SYSTEM - Ighi's Home Radio
 * Generuje informatívny obsah o skladbách bez ohľadu na štýl
 * Podporuje automatický preklad z angličtiny do slovenčiny pomocou DeepL
 */

// Inicializácia
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Token');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load config with error handling
$configLoaded = false;
if (file_exists('config.php')) {
    try {
        // Zachyť potenciálne komentáre na konci súboru
        ob_start();
        require_once 'config.php';
        ob_end_clean();
        $configLoaded = true;
    } catch (Exception $e) {
        error_log('AI Info config error: ' . $e->getMessage());
    }
}

// Debug mode check
$debugMode = $configLoaded && defined('DEBUG_MODE') && DEBUG_MODE;

// Log function
function logDebug($message, $data = null) {
    global $debugMode;
    if ($debugMode && defined('LOG_FILE')) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [AI_INFO] $message";
        if ($data) {
            $logMessage .= " - " . (is_string($data) ? $data : json_encode($data));
        }
        $logMessage .= "\n";
        @file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// Input validation
$artist = isset($_GET['artist']) ? htmlspecialchars(trim($_GET['artist']), ENT_QUOTES, 'UTF-8') : '';
$title = isset($_GET['title']) ? htmlspecialchars(trim($_GET['title']), ENT_QUOTES, 'UTF-8') : '';
$lang = isset($_GET['lang']) ? htmlspecialchars($_GET['lang'], ENT_QUOTES, 'UTF-8') : 'sk';
$maxWords = min(max((int)($_GET['max_words'] ?? 80), 50), 200);
$year = isset($_GET['year']) ? htmlspecialchars($_GET['year'], ENT_QUOTES, 'UTF-8') : '';
$genre = isset($_GET['genre']) ? htmlspecialchars($_GET['genre'], ENT_QUOTES, 'UTF-8') : '';
$album = isset($_GET['album']) ? htmlspecialchars($_GET['album'], ENT_QUOTES, 'UTF-8') : '';

// Určenie typu promptu - striedanie medzi song trivia a artist fun fact
$promptType = isset($_GET['prompt_type']) ? $_GET['prompt_type'] : '';
if (empty($promptType)) {
    // Použijeme časový faktor na striedanie promptov
    // Každých 30 sekúnd sa zmení typ
    $currentMinute = floor(time() / 30);
    $promptType = ($currentMinute % 2 == 0) ? 'song_trivia' : 'artist_fact';
}

// Kontrola základných parametrov
if (empty($artist) || empty($title)) {
    http_response_code(400);
    echo json_encode(['error' => 'Artist and title required']);
    exit();
}

// Normalizácia jazyka
if (!in_array($lang, ['en', 'sk'])) {
    $lang = 'en';
}

logDebug("AI Info called", [
    'artist' => $artist,
    'title' => $title,
    'lang' => $lang,
    'prompt_type' => $promptType
]);

// Detekcia remixu pre lepšie generovanie obsahu
$trackInfo = prepareTrackInfo($artist, $title);
if ($trackInfo['is_remix']) {
    logDebug("Remix detected", [
        'original_title' => $trackInfo['original_title'],
        'remix_info' => $trackInfo['remix_info']
    ]);
}

// Cache key - preferuj originálny název pre lepšie cache hit rate
function getCacheKey($artist, $title, $lang, $promptType) {
    $trackInfo = prepareTrackInfo($artist, $title);
    // Pre remix použij originálny title v cache key pre lepšie zdieľanie
    $cacheTitle = $trackInfo['is_remix'] ? $trackInfo['original_title'] : $title;
    return md5(strtolower(trim($artist)) . '_' . strtolower(trim($cacheTitle)) . '_' . $lang . '_' . $promptType);
}

// Cache dir
$cacheDir = $configLoaded && defined('AI_CACHE_DIR') ? AI_CACHE_DIR : 'cache/ai/';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

// Check cache
$cacheKey = getCacheKey($artist, $title, $lang, $promptType);
$cacheFile = $cacheDir . $cacheKey . '.json';
$cacheValid = false;

if (file_exists($cacheFile)) {
    $cachedData = @json_decode(file_get_contents($cacheFile), true);
    if ($cachedData && isset($cachedData['timestamp']) && (time() - $cachedData['timestamp'] < 86400)) {
        // Cache valid for 24 hours
        echo json_encode($cachedData);
        logDebug("Returned cached response", ['cache_key' => $cacheKey]);
        exit();
    }
}

// Dostupné API kľúče z konfigurácie
$perplexityKey = $configLoaded && defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : '';
$openaiKey = $configLoaded && defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
$geminiKey = $configLoaded && defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
$deeplKey = $configLoaded && defined('DEEPL_API_KEY') ? DEEPL_API_KEY : '';

$hasApiKey = !empty($perplexityKey) || !empty($openaiKey) || !empty($geminiKey);

// Inicializácia odpovede
$content = '';
$alternatives = [];
$provider = 'Fallback';
$quality = 70;
$needsTranslation = ($lang === 'sk');

// Nastavenia časovača
$timerSeconds = $configLoaded && defined('AI_DEFAULT_TIMER') ? AI_DEFAULT_TIMER : 25;
$minTimerSeconds = $configLoaded && defined('AI_MIN_TIMER') ? AI_MIN_TIMER : 8;

// Generovanie obsahu
if ($hasApiKey) {
    // Prioritné poradie API
    if (!empty($perplexityKey)) {
        logDebug("Trying Perplexity API");
        $content = callPerplexityApi($artist, $title, $year, $genre, $album, $promptType);
        if (!empty($content)) {
            $provider = 'Perplexity';
            $quality = 90;
        }
    }
    
    if (empty($content) && !empty($geminiKey)) {
        logDebug("Trying Gemini API");
        $content = callGeminiApi($artist, $title, $year, $genre, $album, $promptType);
        if (!empty($content)) {
            $provider = 'Gemini';
            $quality = 85;
        }
    }
    
    if (empty($content) && !empty($openaiKey)) {
        logDebug("Trying OpenAI API");
        $content = callOpenAIApi($artist, $title, $year, $genre, $album, $promptType);
        if (!empty($content)) {
            $provider = 'OpenAI';
            $quality = 88;
        }
    }
}

// Fallback ak API zlyhá alebo nie sú kľúče
if (empty($content)) {
    logDebug("Using fallback content");
    $content = getFallbackContent($artist, $title, $promptType);
    $provider = 'Fallback';
    $quality = 70;
}

// Preklad do slovenčiny ak treba
if ($needsTranslation && !empty($content)) {
    $originalContent = $content;
    if (!empty($deeplKey)) {
        logDebug("Translating to Slovak with DeepL");
        $translatedContent = translateWithDeepL($content, 'SK', $deeplKey);
        if (!empty($translatedContent) && $translatedContent !== $content) {
            $content = $translatedContent;
            logDebug("Translation successful");
        } else {
            logDebug("Translation failed, using original content");
        }
    } else {
        // Jednoduchý fallback preklad - len pre základné fallback obsahy
        if ($provider === 'Fallback') {
            $content = getLocalizedFallback($artist, $title, 'sk', $promptType);
        }
    }
}

// Limit na počet slov
$content = limitWords($content, $maxWords);

// Vytvorenie odpovede
$response = [
    'content' => $content,
    'artist' => $artist,
    'title' => $title,
    'lang' => $lang,
    'provider' => $provider,
    'style' => 'informative',
    'prompt_type' => $promptType,
    'quality_score' => $quality,
    'word_count' => str_word_count($content),
    'alternatives' => $alternatives,
    'total_generated' => 1,
    'timestamp' => time(),
    'timer_seconds' => $timerSeconds,
    'min_timer_seconds' => $minTimerSeconds,
    'can_skip' => true,
    'enhanced' => ($provider !== 'Fallback'),
    'sarcastic' => false
];

// Cache response
@file_put_contents($cacheFile, json_encode($response, JSON_UNESCAPED_UNICODE));

// Return response
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// --- HELPER FUNCTIONS ---

// Detekcia remixu a príprava fallback údajov
function prepareTrackInfo($artist, $title) {
    $originalTitle = $title;
    $isRemix = false;
    $remixInfo = '';
    
    // Detekcia remix indikátorov
    $remixPatterns = [
        '/\s*\(.*?radio.*?edit.*?\)/i',
        '/\s*\(.*?extended.*?mix.*?\)/i',
        '/\s*\(.*?club.*?mix.*?\)/i',
        '/\s*\(.*?dance.*?mix.*?\)/i',
        '/\s*\(.*?vocal.*?mix.*?\)/i',
        '/\s*\(.*?instrumental.*?\)/i',
        '/\s*\(.*?remix.*?\)/i',
        '/\s*\(.*?edit.*?\)/i',
        '/\s*\(.*?version.*?\)/i',
        '/\s*\(.*?mix.*?\)/i',
        '/\s*-\s*.*?remix/i',
        '/\s*-\s*.*?edit/i',
        '/\s*-\s*.*?mix/i'
    ];
    
    foreach ($remixPatterns as $pattern) {
        if (preg_match($pattern, $title, $matches)) {
            $isRemix = true;
            $remixInfo = trim($matches[0]);
            $originalTitle = trim(preg_replace($pattern, '', $title));
            break;
        }
    }
    
    return [
        'original_title' => $originalTitle,
        'full_title' => $title,
        'is_remix' => $isRemix,
        'remix_info' => $remixInfo,
        'artist' => $artist
    ];
}

// Pomocná funkcia na získanie správneho promptu
function getPromptForType($trackInfo, $promptType, $context) {
    if ($promptType === 'song_trivia') {
        // Song/Artist trivia prompt
        return "Find a unique and intriguing trivia fact or anecdote about the song or artist entered below. Keep it light and entertaining, perfect for a fun pop music radio show. Highlight anything surprising, quirky, or little-known that would grab a listener's attention and give a fresh insight into the song or artist. You MUST fact check information using trusted online sources for music information and not opinion or web forum posts. Script length should be 10 seconds when spoken aloud. Only output the script, NO extra system messages. Song or Artist: '{$trackInfo['full_title']}' by {$trackInfo['artist']}.{$context}";
    } else {
        // Artist fun fact prompt
        return "Research a fun, interesting trivia fact about the artist entered below, perfect for surprising radio listeners on-air. Keep it short, just one to two sentences, beginning with 'Here's a fun fact about...' or a similar hook. Make it upbeat, playful, and engaging, focusing on something quirky, unexpected, or entertaining that will grab attention and add fun context to artists or songs listeners think they know. The writing style should be entertainment-focused. Light sarcasm is okay. At the end, include an interesting twist or unique fact using a transition like 'And here's a twist. AVOID mentioning specific song names in your output. #RULES: Avoid radio cliche language. You MUST fact check biographical information using trusted online sources for music information and not opinion or web forum posts. Only output the script, NO extra system messages. Artist: {$trackInfo['artist']}";
    }
}

// Aktualizované API volania s remix logikou
function callPerplexityApi($artist, $title, $year = '', $genre = '', $album = '', $promptType = 'song_trivia') {
    global $perplexityKey;
    if (empty($perplexityKey)) return null;
    
    // Priprav track info s remix detekciou
    $trackInfo = prepareTrackInfo($artist, $title);
    
    $context = '';
    if (!empty($year)) $context .= " Released in $year.";
    if (!empty($genre)) $context .= " Genre: $genre.";
    if (!empty($album)) $context .= " Album: $album.";
    
    $prompt = getPromptForType($trackInfo, $promptType, $context);
    
    $data = [
        'model' => 'llama-3.1-sonar-small-128k-online',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a professional radio content creator who researches and presents fascinating stories about music. Focus on verified facts from reliable music databases, interviews, and documentaries. Create engaging content suitable for radio show listeners.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.4,
        'max_tokens' => 350
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.perplexity.ai/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $perplexityKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
    }
    
    logDebug("Perplexity API failed", ['http_code' => $httpCode]);
    return null;
}

// Gemini API
function callGeminiApi($artist, $title, $year = '', $genre = '', $album = '', $promptType = 'song_trivia') {
    global $geminiKey;
    if (empty($geminiKey)) return null;
    
    // Priprav track info s remix detekciou
    $trackInfo = prepareTrackInfo($artist, $title);
    
    $context = '';
    if (!empty($year)) $context .= " Released in $year.";
    if (!empty($genre)) $context .= " Genre: $genre.";
    if (!empty($album)) $context .= " Album: $album.";
    
    $prompt = getPromptForType($trackInfo, $promptType, $context);
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $geminiKey;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 350
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
    }
    
    logDebug("Gemini API failed", ['http_code' => $httpCode]);
    return null;
}

// OpenAI API
function callOpenAIApi($artist, $title, $year = '', $genre = '', $album = '', $promptType = 'song_trivia') {
    global $openaiKey;
    if (empty($openaiKey)) return null;
    
    // Priprav track info s remix detekciou
    $trackInfo = prepareTrackInfo($artist, $title);
    
    $context = '';
    if (!empty($year)) $context .= " Released in $year.";
    if (!empty($genre)) $context .= " Genre: $genre.";
    if (!empty($album)) $context .= " Album: $album.";
    
    $prompt = getPromptForType($trackInfo, $promptType, $context);
    
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a professional radio content creator who researches and presents fascinating stories about music. Focus on verified facts from reliable music databases, interviews, and documentaries. Create engaging content suitable for radio show listeners.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 350,
        'temperature' => 0.5
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openaiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
    }
    
    logDebug("OpenAI API failed", ['http_code' => $httpCode]);
    return null;
}

// DeepL translation - OPRAVENÁ FUNKCIA
function translateWithDeepL($text, $targetLang, $apiKey) {
    if (empty($text) || empty($apiKey)) return $text;
    
    $url = 'https://api.deepl.com/v2/translate';
    $data = [
        'auth_key' => $apiKey,
        'text' => $text,
        'target_lang' => strtoupper($targetLang),
        'preserve_formatting' => 1
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['translations'][0]['text'])) {
            return $result['translations'][0]['text'];
        }
    }
    
    return $text;
}

// Fallback content s remix podporou
function getFallbackContent($artist, $title, $promptType = 'song_trivia') {
    $trackInfo = prepareTrackInfo($artist, $title);
    $displayTitle = $trackInfo['is_remix'] ? $trackInfo['original_title'] : $title;
    $remixNote = $trackInfo['is_remix'] ? " This particular version is " . $trackInfo['remix_info'] . "." : "";
    
    if ($promptType === 'song_trivia') {
        $options = [
            "\"$displayTitle\" by $artist showcases their unique musical style and has resonated with listeners worldwide.$remixNote The track exemplifies the artist's ability to create memorable melodies while delivering meaningful lyrics.",
            "$artist created \"$displayTitle\" as part of their musical journey, combining creative expression with technical skill.$remixNote The song has become a notable part of their repertoire, demonstrating their artistic vision.",
            "With \"$displayTitle\", $artist demonstrates their musical expertise and artistic passion.$remixNote The song features distinctive elements that have made it stand out among listeners and critics alike."
        ];
    } else {
        $options = [
            "Here's a fun fact about $artist: They've created a unique sound that blends various musical influences into something truly distinctive. And here's a twist - their approach to music production has inspired countless other artists.",
            "Here's a fun fact about $artist: Their musical journey has taken them through various styles and genres over the years. And here's a twist - they continue to surprise fans with unexpected collaborations and creative ventures.",
            "Here's a fun fact about $artist: They're known for their innovative approach to creating music that connects with audiences. And here's a twist - their influence extends far beyond just their own recordings."
        ];
    }
    
    return $options[array_rand($options)];
}

// Localized fallback content s remix podporou
function getLocalizedFallback($artist, $title, $lang, $promptType = 'song_trivia') {
    if ($lang !== 'sk') return getFallbackContent($artist, $title, $promptType);
    
    $trackInfo = prepareTrackInfo($artist, $title);
    $displayTitle = $trackInfo['is_remix'] ? $trackInfo['original_title'] : $title;
    $remixNote = $trackInfo['is_remix'] ? " Táto konkrétna verzia je " . $trackInfo['remix_info'] . "." : "";
    
    if ($promptType === 'song_trivia') {
        $options = [
            "\"$displayTitle\" od interpreta $artist predstavuje ich jedinečný hudobný štýl a získal si poslucháčov po celom svete.$remixNote Skladba prezentuje schopnosť umelca vytvárať nezabudnuteľné melódie a zároveň prinášať zmysluplné texty.",
            "$artist vytvoril \"$displayTitle\" ako súčasť svojej hudobnej cesty, spájajúc kreatívne vyjadrenie s technickou zručnosťou.$remixNote Pieseň sa stala významnou súčasťou ich repertoáru, demonštrujúc ich umeleckú víziu.",
            "V skladbe \"$displayTitle\" $artist predvádza svoju hudobnú odbornosť a umeleckú vášeň.$remixNote Pieseň obsahuje charakteristické prvky, ktoré ju odlišujú medzi poslucháčmi aj kritikmi."
        ];
    } else {
        $options = [
            "Tu je zaujímavosť o umelcovi $artist: Vytvorili jedinečný zvuk, ktorý spája rôzne hudobné vplyvy do niečoho skutočne výnimočného. A tu je prekvapenie - ich prístup k hudobnej produkcii inšpiroval nespočetných ďalších umelcov.",
            "Tu je zaujímavosť o umelcovi $artist: Ich hudobná cesta ich viedla cez rôzne štýly a žánre v priebehu rokov. A tu je prekvapenie - naďalej prekvapujú fanúšikov nečakanými spoluprácami a kreatívnymi projektmi.",
            "Tu je zaujímavosť o umelcovi $artist: Sú známi svojím inovatívnym prístupom k tvorbe hudby, ktorá sa spája s publikom. A tu je prekvapenie - ich vplyv siaha ďaleko za ich vlastné nahrávky."
        ];
    }
    
    return $options[array_rand($options)];
}

// Word limiting
function limitWords($text, $maxWords = 60) {
    $words = explode(' ', $text);
    if (count($words) <= $maxWords) {
        return $text;
    }
    
    // Find last complete sentence within word limit
    $limitedText = implode(' ', array_slice($words, 0, $maxWords));
    $lastPeriod = strrpos($limitedText, '.');
    
    if ($lastPeriod !== false) {
        return substr($limitedText, 0, $lastPeriod + 1);
    }
    
    return $limitedText . '.';
}
?>
