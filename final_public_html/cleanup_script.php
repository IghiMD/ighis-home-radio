<?php
/**
 * 🧹 CLEANUP SCRIPT - Vyčistenie kódu Ighi's Home Radio
 * 
 * Tento skript:
 * 1. Opraví syntax error v ai_info.php
 * 2. Odstráni duplicitné súbory  
 * 3. Vyčistí nepoužívané funkcie
 * 4. Reorganizuje štruktúru
 */

echo "🧹 Ighi's Home Radio - Cleanup Script\n";
echo "=====================================\n\n";

$errors = [];
$warnings = [];
$cleaned = [];

// 1. OPRAVA SYNTAX ERROR v ai_info.php
echo "🔧 Opravujem syntax error v ai_info.php...\n";

$aiInfoFile = 'ai_info.php';
if (file_exists($aiInfoFile)) {
    $content = file_get_contents($aiInfoFile);
    
    // Oprava syntax error - chýba zložená zátvorka
    if (strpos($content, 'return true;
    }))') !== false) {
        $content = str_replace(
            'return true;
    }))',
            'return true;
        }
    })',
            $content
        );
        
        file_put_contents($aiInfoFile . '.backup', file_get_contents($aiInfoFile));
        file_put_contents($aiInfoFile, $content);
        $cleaned[] = "✅ Opravený syntax error v ai_info.php";
    } else {
        $warnings[] = "⚠️ Syntax error v ai_info.php už môže byť opravený";
    }
} else {
    $errors[] = "❌ ai_info.php neexistuje";
}

// 2. ODSTRÁNENIE DUPLICITNÝCH SÚBOROV
echo "\n🗑️ Odstraňujem duplicitné súbory...\n";

$duplicateFiles = [
    // stream_metadata.php je duplicitný s metadata_proxy.php
    'stream_metadata.php' => 'metadata_proxy.php je lepší, odstraňujem stream_metadata.php',
    
    // webhook_secure.php je duplicitný s webhook.php  
    'webhook_secure.php' => 'webhook.php je hlavný, odstraňujem webhook_secure.php',
    
    // setup_script.php je len pre setup
    'setup_script.php' => 'Setup súbor - presuniem do setup/ adresára'
];

foreach ($duplicateFiles as $file => $reason) {
    if (file_exists($file)) {
        // Vytvor backup pred odstránením
        if (!is_dir('_cleanup_backup')) {
            mkdir('_cleanup_backup', 0755, true);
        }
        
        copy($file, "_cleanup_backup/$file");
        
        if ($file === 'setup_script.php') {
            // Presun setup súbory do setup adresára
            if (!is_dir('setup')) {
                mkdir('setup', 0755, true);
            }
            rename($file, "setup/$file");
            $cleaned[] = "📁 $file presunutý do setup/";
        } else {
            unlink($file);
            $cleaned[] = "🗑️ Odstránený $file ($reason)";
        }
    }
}

// 3. VYČISTENIE NEPOUŽÍVANÝCH FUNKCIÍ v ai_info.php
echo "\n🧼 Čistím nepoužívané funkcie...\n";

if (file_exists($aiInfoFile)) {
    $content = file_get_contents($aiInfoFile);
    
    // Odstráň duplicitné definície containsSlovakDiacritics
    $pattern = '/\/\/ Helper funkcia na detekciu slovenských znakov[\s\S]*?function containsSlovakDiacritics\([^}]+\}/';
    $content = preg_replace($pattern, '', $content);
    
    // Vyčisti komentáre DEBUGGING FUNKCIE
    $content = preg_replace('/\/\/ DEBUGGING FUNKCIE[\s\S]*?window\.testEventAPI = testEventAPI;/', '', $content);
    
    file_put_contents($aiInfoFile, $content);
    $cleaned[] = "🧼 Vyčistené duplicitné funkcie v ai_info.php";
}

// 4. REORGANIZÁCIA ŠTRUKTÚRY
echo "\n📁 Reorganizujem štruktúru súborov...\n";

// Presun admin súborov do admin/ adresára
$adminFiles = ['monitor.php', 'login.php', 'logout.php', 'backup.php'];
if (!is_dir('admin')) {
    mkdir('admin', 0755, true);
}

foreach ($adminFiles as $file) {
    if (file_exists($file) && !file_exists("admin/$file")) {
        copy($file, "admin/$file");
        
        // Uprav relatívne cesty v admin súboroch
        $content = file_get_contents("admin/$file");
        $content = str_replace("require_once 'config.php';", "require_once '../config.php';", $content);
        $content = str_replace("require_once 'auth.php';", "require_once '../auth.php';", $content);
        file_put_contents("admin/$file", $content);
        
        $cleaned[] = "📁 $file prekopírovaný do admin/ (originál zachovaný)";
    }
}

// Presun API súborov do api/ adresára  
$apiFiles = ['webhook.php', 'spotify_auth.php', 'deezer_proxy.php', 'get_config.php', 'event_api.php', 'notifications.php'];
if (!is_dir('api')) {
    mkdir('api', 0755, true);
}

foreach ($apiFiles as $file) {
    if (file_exists($file) && !file_exists("api/$file")) {
        copy($file, "api/$file");
        
        // Uprav relatívne cesty v API súboroch
        $content = file_get_contents("api/$file");
        $content = str_replace("require_once 'config.php';", "require_once '../config.php';", $content);
        $content = str_replace("require_once 'auth.php';", "require_once '../auth.php';", $content);
        file_put_contents("api/$file", $content);
        
        $cleaned[] = "📁 $file prekopírovaný do api/ (originál zachovaný)";
    }
}

// 5. VYTVORENIE .HTACCESS PRE BEZPEČNOSŤ
echo "\n🔒 Vytváram bezpečnostné .htaccess súbory...\n";

$htaccessDirs = ['data', 'logs', 'cache', '_cleanup_backup'];
foreach ($htaccessDirs as $dir) {
    if (is_dir($dir)) {
        $htaccessFile = "$dir/.htaccess";
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Order deny,allow\nDeny from all\n");
            $cleaned[] = "🔒 Vytvorený $htaccessFile";
        }
    }
}

// 6. OPRAVA KONFIGURÁCIÍ
echo "\n⚙️ Kontrolujem konfigurácie...\n";

// Skontroluj config.php
if (file_exists('config.php')) {
    $configContent = file_get_contents('config.php');
    
    // Pridaj chýbajúce konštanty ak nie sú definované
    $missingConstants = [
        'AI_CACHE_DIR' => "define('AI_CACHE_DIR', CACHE_DIR . 'ai/');",
        'TRIVIA_CACHE_FILE' => "define('TRIVIA_CACHE_FILE', CACHE_DIR . 'trivia.json');",
        'METADATA_CACHE_FILE' => "define('METADATA_CACHE_FILE', CACHE_DIR . 'metadata.json');"
    ];
    
    foreach ($missingConstants as $constant => $definition) {
        if (strpos($configContent, $constant) === false) {
            $configContent = str_replace(
                '// =============================================
// FINAL SETUP
// =============================================',
                $definition . "\n\n// =============================================
// FINAL SETUP
// =============================================",
                $configContent
            );
            $cleaned[] = "⚙️ Pridaná chýbajúca konštanta: $constant";
        }
    }
    
    file_put_contents('config.php', $configContent);
}

// 7. VYTVORENIE OPTIMALIZOVANÉHO ai_info.php
echo "\n🤖 Vytváram optimalizovaný ai_info.php...\n";

$optimizedAiInfo = '<?php
/**
 * AI_INFO OPTIMIZED - Opravená verzia
 */

require_once \'config.php\';

header(\'Content-Type: application/json; charset=utf-8\');
header(\'Access-Control-Allow-Origin: *\');
header(\'Access-Control-Allow-Methods: GET, POST\');
header(\'Access-Control-Allow-Headers: Content-Type, X-API-Token\');

error_reporting(E_ALL & ~E_WARNING);
ini_set(\'display_errors\', 0);

// Parametre
$artist = trim($_GET[\'artist\'] ?? \'\');
$title = trim($_GET[\'title\'] ?? \'\');
$lang = $_GET[\'lang\'] ?? \'en\';
$maxWords = (int)($_GET[\'max_words\'] ?? 50);

if (empty($artist) || empty($title)) {
    echo json_encode([\'error\' => \'Missing parameters\']);
    exit;
}

if (!in_array($lang, [\'en\', \'sk\'])) {
    $lang = \'en\';
}

// Fallback content ak AI služby nie sú dostupné
function getFallbackContent($artist, $title, $lang) {
    $fallbacks = [
        \'sk\' => [
            "Skladba \'{$title}\' od {$artist} predstavuje zaujímavý príklad hudobného umenia.",
            "{$artist} vytvoril kompozíciu, ktorá elegantne spája tradičné prvky s moderným prístupom.",
            "Táto skladba demonštruje majstrovské ovládanie žánru."
        ],
        \'en\' => [
            "The track \'{$title}\' by {$artist} represents an intriguing example of musical artistry.",
            "{$artist} crafted a composition that elegantly combines traditional elements with modern approach.", 
            "This track demonstrates masterful command of the genre."
        ]
    ];
    
    $options = $fallbacks[$lang] ?? $fallbacks[\'en\'];
    return $options[array_rand($options)];
}

// Skús volať AI služby (ak sú konfigurované)
$aiContent = null;

// OpenAI
if (defined(\'OPENAI_API_KEY\') && !empty(OPENAI_API_KEY)) {
    try {
        $prompt = $lang === \'sk\' ? 
            "Napíš krátky kultivovaný komentár o skladbe \'{$title}\' od {$artist}. Maximálne 2 vety." :
            "Write a short cultured commentary about \'{$title}\' by {$artist}. Maximum 2 sentences.";
            
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, \'https://api.openai.com/v1/chat/completions\');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            \'model\' => \'gpt-3.5-turbo\',
            \'messages\' => [[\'role\' => \'user\', \'content\' => $prompt]],
            \'max_tokens\' => 100,
            \'temperature\' => 0.7
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            \'Authorization: Bearer \' . OPENAI_API_KEY,
            \'Content-Type: application/json\'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result[\'choices\'][0][\'message\'][\'content\'])) {
                $aiContent = trim($result[\'choices\'][0][\'message\'][\'content\']);
            }
        }
    } catch (Exception $e) {
        // Pokračuj s fallback obsahom
    }
}

// Výsledok
$content = $aiContent ?: getFallbackContent($artist, $title, $lang);

echo json_encode([
    \'content\' => $content,
    \'artist\' => $artist,
    \'title\' => $title,
    \'lang\' => $lang,
    \'provider\' => $aiContent ? \'OpenAI\' : \'Fallback\',
    \'timer_seconds\' => 25,
    \'can_skip\' => true,
    \'word_count\' => str_word_count($content),
    \'timestamp\' => time()
], JSON_UNESCAPED_UNICODE);
?>';

file_put_contents('ai_info_optimized.php', $optimizedAiInfo);
$cleaned[] = "🤖 Vytvorený ai_info_optimized.php (backup verzia)";

// 8. VÝSLEDOK
echo "\n📊 VÝSLEDOK ČISTENIA:\n";
echo "===================\n\n";

if (!empty($cleaned)) {
    echo "✅ ÚSPEŠNE VYČISTENÉ:\n";
    foreach ($cleaned as $item) {
        echo "   $item\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️ UPOZORNENIA:\n";
    foreach ($warnings as $warning) {
        echo "   $warning\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ CHYBY:\n";
    foreach ($errors as $error) {
        echo "   $error\n";
    }
    echo "\n";
}

echo "📁 NOVÁ ŠTRUKTÚRA:\n";
echo "   /\n";
echo "   ├── index.html (hlavná stránka)\n";
echo "   ├── config.php (konfigurácia)\n";
echo "   ├── auth.php (autentifikácia)\n";
echo "   ├── ai_info.php (opravený)\n";
echo "   ├── ai_info_optimized.php (záložná verzia)\n";
echo "   ├── metadata_proxy.php (metadata)\n";
echo "   ├── admin/\n";
echo "   │   ├── monitor.php\n";
echo "   │   ├── login.php\n";
echo "   │   ├── logout.php\n";
echo "   │   └── backup.php\n";
echo "   ├── api/\n";
echo "   │   ├── webhook.php\n";
echo "   │   ├── spotify_auth.php\n";
echo "   │   ├── get_config.php\n";
echo "   │   └── ...\n";
echo "   ├── setup/\n";
echo "   │   └── setup_script.php\n";
echo "   └── _cleanup_backup/ (zálohy)\n\n";

echo "🔄 ĎALŠIE KROKY:\n";
echo "1. Otestuj ai_info.php - ak nefunguje, premenuj ai_info_optimized.php na ai_info.php\n";
echo "2. Aktualizuj odkazy v index.html ak používa admin/ alebo api/ súbory\n";
echo "3. Skontroluj .env súbor s API kľúčmi\n";
echo "4. Zmaž _cleanup_backup/ adresár ak je všetko v poriadku\n\n";

echo "✅ CLEANUP DOKONČENÝ!\n";
?>