<?php
/**
 * Test načítania .env súboru a API kľúčov
 * Spustite: http://vasa-domena.sk/test_env.php
 */

// Načítaj config.php
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>ENV Test - Ighi's Home Radio</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: #fff; }
        .test { background: #2a2a2a; padding: 20px; margin: 10px 0; border-radius: 5px; border: 1px solid #444; }
        .success { color: #00ff88; }
        .error { color: #ff4444; }
        .warning { color: #ffaa00; }
        pre { background: #000; padding: 10px; overflow-x: auto; color: #0f0; }
        .key-value { margin: 5px 0; padding: 5px; background: #333; }
        .hidden { color: #888; }
    </style>
</head>
<body>
    <h1>🔧 ENV a API Kľúče Test</h1>
    
    <div class="test">
        <h2>1. Kontrola .env súboru</h2>
        <?php
        $envLocations = ['.env', '../.env', '../../.env', dirname(__FILE__) . '/.env'];
        $envFound = false;
        $envPath = '';
        
        foreach ($envLocations as $location) {
            if (file_exists($location)) {
                $envFound = true;
                $envPath = realpath($location);
                echo "<p class='success'>✅ .env súbor nájdený: <code>$envPath</code></p>";
                
                // Kontrola práv
                if (is_readable($location)) {
                    echo "<p class='success'>✅ .env súbor je čitateľný</p>";
                } else {
                    echo "<p class='error'>❌ .env súbor NIE JE čitateľný!</p>";
                }
                
                // Veľkosť súboru
                $size = filesize($location);
                echo "<p>Veľkosť: <strong>$size bytes</strong></p>";
                
                // Počet riadkov
                $lines = count(file($location));
                echo "<p>Počet riadkov: <strong>$lines</strong></p>";
                
                break;
            }
        }
        
        if (!$envFound) {
            echo "<p class='error'>❌ .env súbor NEBOL NÁJDENÝ!</p>";
            echo "<p>Hľadal som v: " . implode(', ', $envLocations) . "</p>";
        }
        ?>
    </div>
    
    <div class="test">
        <h2>2. Kontrola načítaných hodnôt</h2>
        <?php
        // Test env() funkcie
        echo "<h3>Test env() funkcie:</h3>";
        $testValue = env('TIMEZONE', 'not_found');
        echo "<p>env('TIMEZONE'): <code>$testValue</code></p>";
        
        // Kontrola getenv
        echo "<h3>Priame getenv() hodnoty:</h3>";
        $timezone = getenv('TIMEZONE');
        echo "<p>getenv('TIMEZONE'): <code>" . ($timezone ?: 'FALSE') . "</code></p>";
        
        // Kontrola $_ENV
        echo "<h3>\$_ENV array:</h3>";
        if (!empty($_ENV)) {
            echo "<p class='success'>✅ \$_ENV obsahuje " . count($_ENV) . " hodnôt</p>";
        } else {
            echo "<p class='warning'>⚠️ \$_ENV je prázdny!</p>";
        }
        ?>
    </div>
    
    <div class="test">
        <h2>3. AI API Kľúče</h2>
        <?php
        $aiKeys = [
            'PERPLEXITY_API_KEY' => 'Perplexity',
            'OPENAI_API_KEY' => 'OpenAI',
            'OPENAI_API_KEY_BACKUP' => 'OpenAI Backup',
            'GEMINI_API_KEY' => 'Google Gemini',
            'DEEPL_API_KEY' => 'DeepL'
        ];
        
        $hasAnyKey = false;
        foreach ($aiKeys as $key => $name) {
            echo "<div class='key-value'>";
            echo "<strong>$name ($key):</strong> ";
            
            if (defined($key)) {
                $value = constant($key);
                if (!empty($value)) {
                    // Zobraz len prvých/posledných 6 znakov pre bezpečnosť
                    $masked = substr($value, 0, 6) . '...' . substr($value, -6);
                    echo "<span class='success'>✅ Nastavený (<code class='hidden'>$masked</code>)</span>";
                    $hasAnyKey = true;
                } else {
                    echo "<span class='error'>❌ Prázdny</span>";
                }
            } else {
                echo "<span class='error'>❌ Nedefinovaný</span>";
            }
            echo "</div>";
        }
        
        if (!$hasAnyKey) {
            echo "<p class='error'>❌ ŽIADNE AI API kľúče nie sú nastavené!</p>";
            echo "<p>Skontrolujte váš .env súbor a uistite sa, že obsahuje aspoň jeden API kľúč.</p>";
        }
        ?>
    </div>
    
    <div class="test">
        <h2>4. Metadata API Kľúče</h2>
        <?php
        $metaKeys = [
            'SPOTIFY_CLIENT_ID' => 'Spotify Client ID',
            'SPOTIFY_CLIENT_SECRET' => 'Spotify Secret',
            'YOUTUBE_API_KEY' => 'YouTube',
            'LASTFM_API_KEY' => 'Last.fm'
        ];
        
        foreach ($metaKeys as $key => $name) {
            echo "<div class='key-value'>";
            echo "<strong>$name:</strong> ";
            
            if (defined($key) && !empty(constant($key))) {
                $value = constant($key);
                $masked = substr($value, 0, 6) . '...' . substr($value, -4);
                echo "<span class='success'>✅ Nastavený (<code class='hidden'>$masked</code>)</span>";
            } else {
                echo "<span class='warning'>⚠️ Nenastavený</span>";
            }
            echo "</div>";
        }
        ?>
    </div>
    
    <div class="test">
        <h2>5. Aktívne služby</h2>
        <?php
        echo "<h3>AI Služby:</h3>";
        $aiServices = getActiveAIServices();
        if (!empty($aiServices)) {
            echo "<p class='success'>✅ Aktívne: " . implode(', ', $aiServices) . "</p>";
        } else {
            echo "<p class='error'>❌ Žiadne AI služby nie sú aktívne</p>";
        }
        
        echo "<h3>Metadata Služby:</h3>";
        $metaServices = getActiveMetadataServices();
        if (!empty($metaServices)) {
            echo "<p class='success'>✅ Aktívne: " . implode(', ', $metaServices) . "</p>";
        } else {
            echo "<p class='warning'>⚠️ Žiadne metadata služby nie sú aktívne</p>";
        }
        ?>
    </div>
    
    <div class="test">
        <h2>6. Test priameho volania ai_info.php</h2>
        <button onclick="testAIInfo()">🧪 Testovať AI Info</button>
        <div id="aiResult"></div>
        
        <script>
        function testAIInfo() {
            const url = 'ai_info.php?artist=Test&title=Test&lang=sk';
            const resultDiv = document.getElementById('aiResult');
            resultDiv.innerHTML = '<p>⏳ Testujem...</p>';
            
            fetch(url)
                .then(response => {
                    resultDiv.innerHTML += `<p>HTTP Status: <strong>${response.status}</strong></p>`;
                    return response.text();
                })
                .then(text => {
                    try {
                        const json = JSON.parse(text);
                        resultDiv.innerHTML += '<pre>' + JSON.stringify(json, null, 2) + '</pre>';
                    } catch (e) {
                        resultDiv.innerHTML += '<p class="error">Response is not JSON:</p>';
                        resultDiv.innerHTML += '<pre>' + text.substring(0, 1000) + '</pre>';
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML += '<p class="error">Error: ' + error + '</p>';
                });
        }
        </script>
    </div>
    
    <div class="test">
        <h2>7. PHP Info relevantné</h2>
        <?php
        echo "<p>PHP Version: <strong>" . phpversion() . "</strong></p>";
        echo "<p>Memory Limit: <strong>" . ini_get('memory_limit') . "</strong></p>";
        echo "<p>Max Execution Time: <strong>" . ini_get('max_execution_time') . "s</strong></p>";
        echo "<p>Error Reporting: <strong>" . error_reporting() . "</strong></p>";
        echo "<p>Display Errors: <strong>" . ini_get('display_errors') . "</strong></p>";
        ?>
    </div>
</body>
</html>