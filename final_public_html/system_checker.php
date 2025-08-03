<?php
/**
 * KOMPLETNÁ DIAGNOSTIKA SYSTÉMU - Ighi's Home Radio
 * Súbor: check_systems.php
 */

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>🔧 Diagnostika Systému - Ighi's Home Radio</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #0a0a0a; color: #fff; padding: 20px; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #00ff88; text-align: center; margin-bottom: 30px; }
        h2 { color: #007aff; margin: 30px 0 15px; border-bottom: 2px solid #007aff; padding-bottom: 5px; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
        .ok { background: #00ff88; color: #000; }
        .warning { background: #ff8c00; color: #000; }
        .error { background: #ff2d55; color: #fff; }
        .info { background: #007aff; color: #fff; }
        .section { background: rgba(255,255,255,0.05); margin: 20px 0; padding: 20px; border-radius: 10px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .test-result { margin: 10px 0; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        pre { background: rgba(0,0,0,0.5); padding: 15px; border-radius: 5px; overflow-x: auto; margin: 10px 0; }
        .btn { background: #00ff88; color: #000; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .footer { text-align: center; margin-top: 50px; padding: 20px; border-top: 1px solid #333; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Ighi's Home Radio - Systémová Diagnostika</h1>";

// =============================================
// 1. KONFIGURÁCIA
// =============================================
echo "<div class='section'>";
echo "<h2>⚙️ Konfigurácia</h2>";

$configLoaded = false;
if (file_exists('config.php')) {
    try {
        require_once 'config.php';
        $configLoaded = true;
        echo "<p>✅ <span class='status ok'>config.php načítaný</span></p>";
    } catch (Exception $e) {
        echo "<p>❌ <span class='status error'>config.php error: " . htmlspecialchars($e->getMessage()) . "</span></p>";
    }
} else {
    echo "<p>❌ <span class='status error'>config.php neexistuje</span></p>";
}

// Check .env
if (file_exists('.env')) {
    echo "<p>✅ <span class='status ok'>.env súbor existuje</span></p>";
    $envSize = filesize('.env');
    echo "<p>📁 Veľkosť .env: " . number_format($envSize) . " bytes</p>";
    
    if ($envSize < 100) {
        echo "<p>⚠️ <span class='status warning'>.env súbor je príliš malý - možno chýbajú API kľúče</span></p>";
    }
} else {
    echo "<p>❌ <span class='status error'>.env súbor neexistuje</span></p>";
}

echo "</div>";

// =============================================
// 2. API KĽÚČE
// =============================================
echo "<div class='section'>";
echo "<h2>🔑 API Kľúče</h2>";

$apiKeys = [
    'GEMINI_API_KEY' => 'Google Gemini (AI)',
    'OPENAI_API_KEY' => 'OpenAI GPT (AI)',
    'PERPLEXITY_API_KEY' => 'Perplexity (AI)',
    'DEEPL_API_KEY' => 'DeepL (Preklad)',
    'SPOTIFY_CLIENT_ID' => 'Spotify (Metadata)',
    'YOUTUBE_API_KEY' => 'YouTube (Metadata)',
    'LASTFM_API_KEY' => 'Last.fm (Metadata)'
];

$apiCount = 0;
foreach ($apiKeys as $key => $name) {
    $exists = defined($key) && !empty(constant($key));
    if ($exists) {
        echo "<p>✅ <span class='status ok'>$name</span> - Nakonfigurovaný</p>";
        $apiCount++;
    } else {
        echo "<p>❌ <span class='status error'>$name</span> - Chýba</p>";
    }
}

echo "<p><strong>Nakonfigurované API kľúče: $apiCount/" . count($apiKeys) . "</strong></p>";

if ($apiCount === 0) {
    echo "<div class='test-result'>";
    echo "<p>🚨 <strong>KRITICKÝ PROBLÉM:</strong> Žiadne API kľúče nie sú nakonfigurované!</p>";
    echo "<p>To je dôvod, prečo AI Info systém používa len predefinované texty.</p>";
    echo "</div>";
}

echo "</div>";

// =============================================
// 3. ADRESÁRE A SÚBORY
// =============================================
echo "<div class='section'>";
echo "<h2>📁 Systémové Adresáre</h2>";

$directories = [
    'data' => 'Dáta',
    'cache' => 'Cache',
    'logs' => 'Logy',
    'cache/ai' => 'AI Cache'
];

foreach ($directories as $dir => $name) {
    if (is_dir($dir)) {
        $writable = is_writable($dir);
        $fileCount = count(glob($dir . '/*'));
        $size = 0;
        
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) $size += filesize($file);
        }
        
        echo "<p>📁 $name ($dir): ";
        if ($writable) {
            echo "<span class='status ok'>✅ OK</span>";
        } else {
            echo "<span class='status warning'>⚠️ Read-only</span>";
        }
        echo " - $fileCount súborov, " . number_format($size) . " bytes</p>";
    } else {
        echo "<p>📁 $name ($dir): <span class='status error'>❌ Neexistuje</span></p>";
        
        // Pokús sa vytvoriť
        if (@mkdir($dir, 0755, true)) {
            echo "<p>✅ Adresár $dir bol vytvorený</p>";
        } else {
            echo "<p>❌ Nepodarilo sa vytvoriť adresár $dir</p>";
        }
    }
}

echo "</div>";

// =============================================
// 4. TEST AI ENDPOINTS
// =============================================
echo "<div class='section'>";
echo "<h2>🤖 Test AI Systémov</h2>";

// Test ai_info.php
echo "<h3>Test ai_info.php (starý systém):</h3>";
$testUrl = "ai_info.php?artist=Test&title=Song&lang=sk&max_words=50";

$context = stream_context_create([
    'http' => [
        'timeout' => 15,
        'user_agent' => 'SystemCheck/1.0'
    ]
]);

$response = @file_get_contents($testUrl, false, $context);
if ($response) {
    $data = json_decode($response, true);
    if ($data) {
        echo "<div class='test-result'>";
        echo "<p><strong>Provider:</strong> " . ($data['provider'] ?? 'Unknown') . "</p>";
        echo "<p><strong>Content:</strong> " . htmlspecialchars(substr($data['content'] ?? '', 0, 100)) . "...</p>";
        
        $isFallback = ($data['provider'] ?? '') === 'Fallback' || 
                     strpos($data['content'] ?? '', 'vytvoril skladbu') !== false;
        
        if ($isFallback) {
            echo "<p>🚨 <span class='status warning'>Používa FALLBACK (predefinované texty)</span></p>";
        } else {
            echo "<p>✅ <span class='status ok'>Reálne AI content</span></p>";
        }
        echo "</div>";
    }
} else {
    echo "<p>❌ <span class='status error'>ai_info.php endpoint neodpovedá</span></p>";
}

// Test vylepšeného systému ak existuje
if (file_exists('ai_info_enhanced.php')) {
    echo "<h3>Test ai_info_enhanced.php (nový systém):</h3>";
    $enhancedUrl = "ai_info_enhanced.php?artist=Test&title=Song&lang=sk&max_words=50";
    
    $response = @file_get_contents($enhancedUrl, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if ($data) {
            echo "<div class='test-result'>";
            echo "<p><strong>Provider:</strong> " . ($data['provider'] ?? 'Unknown') . "</p>";
            echo "<p><strong>Quality Score:</strong> " . ($data['quality_score'] ?? 0) . "/100</p>";
            echo "<p><strong>Enhanced:</strong> " . (($data['enhanced'] ?? false) ? 'Áno' : 'Nie') . "</p>";
            echo "</div>";
        }
    }
}

echo "</div>";

// =============================================
// 5. STREAM A METADATA
// =============================================
echo "<div class='section'>";
echo "<h2>📡 Stream a Metadata</h2>";

// Test metadata proxy
if (file_exists('metadata_proxy.php')) {
    echo "<h3>Test metadata_proxy.php:</h3>";
    
    $metadataResponse = @file_get_contents('metadata_proxy.php', false, $context);
    if ($metadataResponse) {
        $metadataData = json_decode($metadataResponse, true);
        if ($metadataData && $metadataData['status'] === 'success') {
            echo "<p>✅ <span class='status ok'>Metadata proxy funguje</span></p>";
            if (isset($metadataData['parsed']['raw_title'])) {
                echo "<p>🎵 Aktuálna skladba: " . htmlspecialchars($metadataData['parsed']['raw_title']) . "</p>";
            }
        } else {
            echo "<p>⚠️ <span class='status warning'>Metadata proxy odpovedá, ale bez údajov</span></p>";
        }
    } else {
        echo "<p>❌ <span class='status error'>Metadata proxy neodpovedá</span></p>";
    }
} else {
    echo "<p>❌ <span class='status error'>metadata_proxy.php neexistuje</span></p>";
}

// Stream URL check
if (defined('STREAM_URL')) {
    echo "<p>📻 Stream URL: " . htmlspecialchars(STREAM_URL) . "</p>";
} else {
    echo "<p>❌ <span class='status error'>STREAM_URL nie je definovaný</span></p>";
}

echo "</div>";

// =============================================
// 6. BEZPEČNOSŤ
// =============================================
echo "<div class='section'>";
echo "<h2>🔒 Bezpečnosť</h2>";

// Admin credentials check
if (defined('ADMIN_PASSWORD')) {
    $defaultPasswords = ['changeme', 'TuDajSvojeSilneHeslo123!', 'admin123'];
    if (in_array(ADMIN_PASSWORD, $defaultPasswords)) {
        echo "<p>🚨 <span class='status error'>POUŽÍVATE DEFAULT ADMIN HESLO!</span></p>";
    } else {
        echo "<p>✅ <span class='status ok'>Admin heslo je zmenené</span></p>";
    }
}

// File permissions
$criticalFiles = ['config.php', '.env', 'data', 'logs'];
foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        $readable = is_readable($file);
        $writable = is_writable($file);
        
        echo "<p>🔐 $file: ";
        if ($readable && ($file === 'data' || $file === 'logs' ? $writable : true)) {
            echo "<span class='status ok'>OK</span>";
        } else {
            echo "<span class='status warning'>Problém s oprávneniami</span>";
        }
        echo "</p>";
    }
}

echo "</div>";

// =============================================
// 7. PERFORMANCE
// =============================================
echo "<div class='section'>";
echo "<h2>⚡ Performance</h2>";

$startTime = microtime(true);

// PHP info
echo "<p>🐘 PHP verzia: " . PHP_VERSION . "</p>";
echo "<p>💾 Memory limit: " . ini_get('memory_limit') . "</p>";
echo "<p>⏰ Max execution time: " . ini_get('max_execution_time') . "s</p>";

// Extensions
$requiredExtensions = ['curl', 'json', 'openssl'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p>✅ PHP $ext extension: <span class='status ok'>Loaded</span></p>";
    } else {
        echo "<p>❌ PHP $ext extension: <span class='status error'>Missing</span></p>";
    }
}

$endTime = microtime(true);
$pageLoadTime = round(($endTime - $startTime) * 1000, 2);
echo "<p>📊 Page load time: {$pageLoadTime}ms</p>";

echo "</div>";

// =============================================
// 8. RIEŠENIA A ODPORÚČANIA
// =============================================
echo "<div class='section'>";
echo "<h2>💡 Riešenia a Odporúčania</h2>";

echo "<div class='grid'>";

// AI Fix
echo "<div>";
echo "<h3>🤖 Oprava AI Systému</h3>";
if ($apiCount === 0) {
    echo "<p>1. Získajte aspoň jeden AI API kľúč:</p>";
    echo "<ul>";
    echo "<li><a href='https://makersuite.google.com/app/apikey' target='_blank'>Google Gemini</a> (Free)</li>";
    echo "<li><a href='https://platform.openai.com/api-keys' target='_blank'>OpenAI</a> (Paid)</li>";
    echo "</ul>";
    echo "<p>2. Pridajte do .env súboru</p>";
    echo "<p>3. Nahraďte ai_info.php s ai_info_enhanced.php</p>";
} else {
    echo "<p>✅ API kľúče sú nakonfigurované</p>";
}
echo "</div>";

// Security Fix
echo "<div>";
echo "<h3>🔒 Bezpečnosť</h3>";
echo "<p>1. Zmeňte default heslá v .env</p>";
echo "<p>2. Nastavte správne file permissions</p>";
echo "<p>3. Použite HTTPS</p>";
echo "<p>4. Pravidelne aktualizujte systém</p>";
echo "</div>";

echo "</div>";

echo "</div>";

// =============================================
// AKČNÉ TLAČIDLÁ
// =============================================
echo "<div class='section' style='text-align: center;'>";
echo "<h2>🛠️ Akcie</h2>";

echo "<a href='test_ai.php' class='btn'>🧪 Test AI Systém</a>";
echo "<a href='monitor.php' class='btn'>📊 System Monitor</a>";
echo "<a href='index.html' class='btn'>🏠 Späť na Rádio</a>";

if (file_exists('ai_info_enhanced.php')) {
    echo "<a href='ai_info_enhanced.php?artist=Test&title=Song&lang=sk' class='btn' target='_blank'>🤖 Test Enhanced AI</a>";
}

echo "</div>";

// Footer
echo "<div class='footer'>";
echo "<p>🎵 Ighi's Home Radio - System Check completed at " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Generated in {$pageLoadTime}ms</p>";
echo "</div>";

echo "</div></body></html>";
?>
