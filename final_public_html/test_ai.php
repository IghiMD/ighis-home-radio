<?php
/**
 * AI SYSTÉM TESTER - Testovanie AI služieb
 * Súbor: test_ai.php
 */

require_once 'config.php';

// Test API kľúčov
echo "<h1>🧪 AI Systém Diagnostika</h1>\n";
echo "<style>body{font-family:Arial;background:#0a0a0a;color:#fff;padding:20px;} .ok{color:#0f0;} .error{color:#f00;} .warning{color:#fa0;}</style>\n";

echo "<h2>🔑 API Kľúče</h2>\n";

// Perplexity
$perplexity_ok = defined('PERPLEXITY_API_KEY') && !empty(PERPLEXITY_API_KEY);
echo "<p>Perplexity: " . ($perplexity_ok ? "<span class='ok'>✅ Nakonfigurovaný</span>" : "<span class='error'>❌ Chýba</span>") . "</p>\n";

// OpenAI
$openai_ok = defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY);
echo "<p>OpenAI: " . ($openai_ok ? "<span class='ok'>✅ Nakonfigurovaný</span>" : "<span class='error'>❌ Chýba</span>") . "</p>\n";

// Gemini
$gemini_ok = defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY);
echo "<p>Gemini: " . ($gemini_ok ? "<span class='ok'>✅ Nakonfigurovaný</span>" : "<span class='error'>❌ Chýba</span>") . "</p>\n";

// DeepL
$deepl_ok = defined('DEEPL_API_KEY') && !empty(DEEPL_API_KEY);
echo "<p>DeepL: " . ($deepl_ok ? "<span class='ok'>✅ Nakonfigurovaný</span>" : "<span class='error'>❌ Chýba</span>") . "</p>\n";

echo "<h2>🧪 Test AI Info Endpoint</h2>\n";

if ($perplexity_ok || $openai_ok || $gemini_ok) {
    // Test AI info
    $test_url = "ai_info.php?artist=Mafia%20Corner%20%26%20Basta%20Fix&title=Sexy%20Čaja&lang=sk&max_words=50&style=gentleman";
    
    echo "<p>Testujem: <code>$test_url</code></p>\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0'
        ]
    ]);
    
    $response = @file_get_contents($test_url, false, $context);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['content'])) {
            echo "<div style='background:rgba(0,255,0,0.1);padding:15px;border-radius:8px;margin:10px 0;'>\n";
            echo "<h3>✅ AI Response:</h3>\n";
            echo "<p><strong>Provider:</strong> " . ($data['provider'] ?? 'Unknown') . "</p>\n";
            echo "<p><strong>Score:</strong> " . ($data['score'] ?? 0) . "/100</p>\n";
            echo "<p><strong>Content:</strong> " . htmlspecialchars($data['content']) . "</p>\n";
            echo "</div>\n";
            
            // Kontrola či je to fallback
            $isFallback = $data['provider'] === 'Fallback' || 
                         strpos($data['content'], 'Táto kompozícia od') !== false ||
                         strpos($data['content'], 'vytvoril skladbu') !== false;
            
            if ($isFallback) {
                echo "<p class='warning'>⚠️ Používa sa FALLBACK systém - nie reálne AI!</p>\n";
            } else {
                echo "<p class='ok'>✅ Reálne AI content!</p>\n";
            }
        } else {
            echo "<p class='error'>❌ Neplatná JSON odpoveď: " . htmlspecialchars($response) . "</p>\n";
        }
    } else {
        echo "<p class='error'>❌ AI endpoint neodpovedá</p>\n";
        $error = error_get_last();
        if ($error) {
            echo "<p class='error'>Error: " . $error['message'] . "</p>\n";
        }
    }
} else {
    echo "<p class='error'>❌ Žiadne AI API kľúče nie sú nakonfigurované!</p>\n";
}

echo "<h2>🔧 Riešenie</h2>\n";
echo "<ol>\n";
echo "<li>Vytvorte .env súbor s AI API kľúčmi</li>\n";
echo "<li>Získajte API kľúče:\n";
echo "   <ul>\n";
echo "   <li><a href='https://makersuite.google.com/app/apikey' target='_blank'>Google Gemini</a> (Free tier)</li>\n";
echo "   <li><a href='https://platform.openai.com/api-keys' target='_blank'>OpenAI</a> (Paid)</li>\n";
echo "   <li><a href='https://www.perplexity.ai/settings/api' target='_blank'>Perplexity</a> (Paid)</li>\n";
echo "   </ul>\n";
echo "</li>\n";
echo "<li>Pridajte do .env súboru:\n";
echo "<pre style='background:rgba(255,255,255,0.1);padding:10px;border-radius:5px;'>\n";
echo "GEMINI_API_KEY=\"your-gemini-key-here\"\n";
echo "OPENAI_API_KEY=\"your-openai-key-here\"\n";
echo "PERPLEXITY_API_KEY=\"your-perplexity-key-here\"\n";
echo "</pre></li>\n";
echo "</ol>\n";

echo "<h2>📊 Systém Status</h2>\n";

// Check directories
$dirs = ['data', 'cache', 'logs', 'cache/ai'];
foreach ($dirs as $dir) {
    $exists = is_dir($dir);
    $writable = $exists && is_writable($dir);
    echo "<p>$dir: ";
    if (!$exists) {
        echo "<span class='error'>❌ Neexistuje</span>";
    } elseif (!$writable) {
        echo "<span class='warning'>⚠️ Nie je writable</span>";
    } else {
        echo "<span class='ok'>✅ OK</span>";
    }
    echo "</p>\n";
}

echo "<hr><p><a href='index.html'>← Späť na rádio</a> | <a href='monitor.php'>📊 Monitor</a></p>\n";
?>