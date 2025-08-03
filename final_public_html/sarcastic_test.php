<?php
/**
 * TEST SARKASTICKÉHO AI SYSTÉMU
 * Súbor: test_sarcastic_ai.php
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>🎭 Test Sarkastického AI - Ighi's Home Radio</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #0a0a0a; color: #fff; padding: 20px; line-height: 1.6; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { color: #00ff88; text-align: center; margin-bottom: 30px; }
        h2 { color: #007aff; margin: 20px 0 10px; }
        .test-result { margin: 15px 0; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 8px; }
        .success { border-left: 4px solid #00ff88; }
        .warning { border-left: 4px solid #ff8c00; }
        .error { border-left: 4px solid #ff2d55; }
        .info { border-left: 4px solid #007aff; }
        .ai-response { background: rgba(0,255,136,0.1); padding: 15px; border-radius: 8px; margin: 10px 0; }
        .ai-meta { font-size: 0.9em; color: #b3b3b3; margin-top: 10px; }
        pre { background: rgba(0,0,0,0.5); padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 0.9em; }
        .btn { background: #00ff88; color: #000; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .loading { text-align: center; padding: 20px; }
        .loader { border: 3px solid rgba(255,255,255,0.3); border-top: 3px solid #00ff88; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🎭 Test Sarkastického AI Systému</h1>";

// =============================================
// 1. ZÁKLADNÉ TESTY KONFIGURÁCIE
// =============================================

echo "<h2>⚙️ Konfigurácia</h2>";

$configTests = [
    'config.php' => file_exists('config.php'),
    'ai_info_sarcastic.php' => file_exists('ai_info_sarcastic.php'),
    'AI_CACHE_DIR' => is_dir(AI_CACHE_DIR ?? 'cache/ai'),
    'cache writable' => is_writable(AI_CACHE_DIR ?? 'cache/ai')
];

foreach ($configTests as $test => $result) {
    $class = $result ? 'success' : 'error';
    $icon = $result ? '✅' : '❌';
    echo "<div class='test-result $class'>$icon $test</div>";
}

// =============================================
// 2. API KĽÚČE
// =============================================

echo "<h2>🔑 AI API Kľúče</h2>";

$apiTests = [
    'Gemini' => defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY),
    'OpenAI' => defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY),
    'Perplexity' => defined('PERPLEXITY_API_KEY') && !empty(PERPLEXITY_API_KEY),
    'DeepL' => defined('DEEPL_API_KEY') && !empty(DEEPL_API_KEY)
];

$availableAI = 0;
foreach ($apiTests as $api => $available) {
    $class = $available ? 'success' : 'warning';
    $icon = $available ? '✅' : '⚠️';
    echo "<div class='test-result $class'>$icon $api API</div>";
    if ($available && $api !== 'DeepL') $availableAI++;
}

echo "<div class='test-result info'>📊 Dostupné AI služby: $availableAI/3</div>";

// =============================================
// 3. TEST AI ENDPOINTS
// =============================================

echo "<h2>🤖 Test AI Generovanie</h2>";

if ($availableAI > 0) {
    // Test tracks
    $testTracks = [
        ['artist' => 'Ed Sheeran', 'title' => 'Shape of You', 'lang' => 'sk'],
        ['artist' => 'Back N Fourth and MOSE UK', 'title' => 'Movin Too Fast (Radio Edit)', 'lang' => 'sk'],
        ['artist' => 'Taylor Swift', 'title' => 'Love Story', 'lang' => 'en']
    ];
    
    foreach ($testTracks as $index => $track) {
        echo "<h3>🎵 Test " . ($index + 1) . ": {$track['artist']} - {$track['title']} ({$track['lang']})</h3>";
        
        $url = "ai_info_sarcastic.php?" . http_build_query([
            'artist' => $track['artist'],
            'title' => $track['title'],
            'lang' => $track['lang'],
            'max_words' => 60,
            'year' => $index == 0 ? '2017' : '',
            'genre' => $index == 0 ? 'pop' : ''
        ]);
        
        echo "<div class='loading'><div class='loader'></div><p>Generujem sarkastický komentár...</p></div>";
        echo "<script>console.log('Testing: $url');</script>";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'SarcasticAI-Test/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['content'])) {
                echo "<script>document.querySelector('.loading').style.display='none';</script>";
                echo "<div class='ai-response'>";
                echo "<strong>Sarkastický komentár:</strong><br>";
                echo htmlspecialchars($data['content']);
                echo "<div class='ai-meta'>";
                echo "Provider: {$data['provider']} | ";
                echo "Style: {$data['style']} | ";
                echo "Quality: {$data['quality_score']}/100 | ";
                echo "Alternatives: " . count($data['alternatives'] ?? []);
                echo "</div></div>";
                
                // Zobraz alternatívy
                if (!empty($data['alternatives'])) {
                    echo "<h4>🔄 Alternatívne verzie:</h4>";
                    foreach ($data['alternatives'] as $i => $alt) {
                        echo "<div class='test-result info'>";
                        echo "<strong>Alt " . ($i + 1) . " ({$alt['style']}):</strong><br>";
                        echo htmlspecialchars($alt['content']);
                        echo "</div>";
                    }
                }
                
                // Analýza kvality
                $quality = $data['quality_score'] ?? 0;
                if ($quality >= 80) {
                    echo "<div class='test-result success'>🎯 Výborná kvalita sarkasmu!</div>";
                } elseif ($quality >= 60) {
                    echo "<div class='test-result warning'>👍 Dobrá kvalita, môže byť lepšie</div>";
                } else {
                    echo "<div class='test-result error'>👎 Nízka kvalita, treba vyladiť</div>";
                }
                
            } else {
                echo "<script>document.querySelector('.loading').style.display='none';</script>";
                echo "<div class='test-result error'>❌ Neplatná JSON odpoveď</div>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            }
        } else {
            echo "<script>document.querySelector('.loading').style.display='none';</script>";
            echo "<div class='test-result error'>❌ Endpoint neodpovedá</div>";
        }
        
        echo "<hr style='margin: 20px 0; border: 1px solid #333;'>";
    }
    
} else {
    echo "<div class='test-result error'>❌ Žiadne AI API kľúče nie sú nakonfigurované!</div>";
    echo "<div class='test-result info'>Získajte aspoň jeden API kľúč:</div>";
    echo "<ul>";
    echo "<li><a href='https://makersuite.google.com/app/apikey' target='_blank'>Google Gemini</a> (Free tier)</li>";
    echo "<li><a href='https://platform.openai.com/api-keys' target='_blank'>OpenAI</a> (Paid)</li>";
    echo "<li><a href='https://www.perplexity.ai/settings/api' target='_blank'>Perplexity</a> (Paid)</li>";
    echo "</ul>";
}

// =============================================
// 4. PERFORMANCE TEST
// =============================================

echo "<h2>⚡ Performance</h2>";

$startTime = microtime(true);

// Simuluj AI request
$testUrl = "ai_info_sarcastic.php?artist=Test&title=Performance&lang=sk&max_words=30";
$perfResponse = @file_get_contents($testUrl);

$endTime = microtime(true);
$responseTime = round(($endTime - $startTime) * 1000, 2);

$performanceClass = 'success';
if ($responseTime > 10000) $performanceClass = 'error';
elseif ($responseTime > 5000) $performanceClass = 'warning';

echo "<div class='test-result $performanceClass'>";
echo "⏱️ Response time: {$responseTime}ms";
if ($responseTime > 5000) {
    echo " (pomalé - optimalizujte)";
} elseif ($responseTime < 3000) {
    echo " (výborné!)";
}
echo "</div>";

// Cache test
$cacheDir = AI_CACHE_DIR ?? 'cache/ai/';
$cacheFiles = glob($cacheDir . '*.json');
echo "<div class='test-result info'>💾 Cache súbory: " . count($cacheFiles) . "</div>";

// =============================================
// 5. ODPORÚČANIA
// =============================================

echo "<h2>💡 Odporúčania</h2>";

if ($availableAI === 0) {
    echo "<div class='test-result error'>";
    echo "<h3>🚨 Kritické problémy:</h3>";
    echo "<ol>";
    echo "<li>Žiadne AI API kľúče nie sú nakonfigurované</li>";
    echo "<li>Získajte aspoň Gemini API kľúč (zadarmo)</li>";
    echo "<li>Aktualizujte .env súbor s API kľúčmi</li>";
    echo "</ol>";
    echo "</div>";
} elseif ($availableAI === 1) {
    echo "<div class='test-result warning'>";
    echo "<h3>⚠️ Možné vylepšenia:</h3>";
    echo "<ol>";
    echo "<li>Pridajte záložný AI poskytovateľ pre redundanciu</li>";
    echo "<li>Otestujte rôzne AI poskytovateľov pre najlepšie výsledky</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='test-result success'>";
    echo "<h3>✅ Systém je správne nakonfigurovaný!</h3>";
    echo "<p>Sarkastický AI systém by mal fungovať správne.</p>";
    echo "</div>";
}

echo "<h3>🎯 Ďalšie kroky:</h3>";
echo "<ol>";
echo "<li>Nahraďte ai_info.php s ai_info_sarcastic.php</li>";
echo "<li>Aktualizujte JavaScript v index.html</li>";
echo "<li>Otestujte na live stránke</li>";
echo "<li>Monitorujte kvalitu generovaného obsahu</li>";
echo "</ol>";

// =============================================
// 6. QUICK ACTIONS
// =============================================

echo "<h2>🛠️ Rýchle akcie</h2>";

echo "<a href='ai_info_sarcastic.php?artist=Ed%20Sheeran&title=Shape%20of%20You&lang=sk&max_words=60' class='btn' target='_blank'>🧪 Test Live API</a>";
echo "<a href='index.html' class='btn'>🏠 Späť na rádio</a>";
echo "<a href='monitor.php' class='btn'>📊 System Monitor</a>";

if (file_exists('check_systems.php')) {
    echo "<a href='check_systems.php' class='btn'>🔧 Full System Check</a>";
}

// Footer
echo "<hr style='margin: 40px 0; border: 1px solid #333;'>";
echo "<div style='text-align: center; color: #666;'>";
echo "<p>🎭 Sarkastický AI Test completed at " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Response time: {$responseTime}ms | Available AI: $availableAI/3</p>";
echo "</div>";

echo "</div>";

// JavaScript pre real-time updates
echo "<script>
setTimeout(() => {
    console.log('🎭 Sarcastic AI Test completed');
    console.log('Available AI services: $availableAI');
    console.log('Response time: {$responseTime}ms');
}, 1000);
</script>";

echo "</body></html>";
?>
