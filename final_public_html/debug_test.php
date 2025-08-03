<?php
/**
 * Debug skript pre testovanie systému rádia
 * Spusti tento súbor pre diagnostiku problémov
 */

echo "<h1>🔧 Diagnostika Ighi's Home Radio</h1>";
echo "<style>body{font-family:Arial;background:#0a0a0a;color:#fff;padding:20px;} .ok{color:#00ff88;} .error{color:#ff2d55;} .warning{color:#ff8c00;} pre{background:#1a1a1a;padding:15px;border-radius:8px;overflow:auto;}</style>";

// 1. Testovanie súborov
echo "<h2>📁 Kontrola súborov:</h2>";
$required_files = [
    'config.php' => 'Hlavná konfigurácia',
    'webhook.php' => 'API endpoint',
    'get_config.php' => 'Konfigurácia pre frontend',
    'stream_metadata.php' => 'Stream metadata handler',
    'ai_info.php' => 'AI informácie',
    'spotify_auth.php' => 'Spotify autentifikácia',
    'index.html' => 'Hlavná stránka'
];

foreach ($required_files as $file => $desc) {
    if (file_exists($file)) {
        echo "<span class='ok'>✅ $file - $desc</span><br>";
    } else {
        echo "<span class='error'>❌ $file - $desc (CHÝBA!)</span><br>";
    }
}

// 2. Testovanie konfigurácie
echo "<h2>⚙️ Konfigurácia:</h2>";
if (file_exists('config.php')) {
    require_once 'config.php';
    
    // Test definovaných konštánt
    $config_tests = [
        'STREAM_URL' => defined('STREAM_URL') ? STREAM_URL : 'NOT DEFINED',
        'ICY_SERVER' => defined('ICY_SERVER') ? ICY_SERVER : 'NOT DEFINED',
        'DATA_DIR' => defined('DATA_DIR') ? DATA_DIR : 'NOT DEFINED',
        'CURRENT_SONG_FILE' => defined('CURRENT_SONG_FILE') ? CURRENT_SONG_FILE : 'NOT DEFINED'
    ];
    
    foreach ($config_tests as $key => $value) {
        if ($value !== 'NOT DEFINED') {
            echo "<span class='ok'>✅ $key: $value</span><br>";
        } else {
            echo "<span class='error'>❌ $key: NOT DEFINED</span><br>";
        }
    }
} else {
    echo "<span class='error'>❌ config.php neexistuje!</span>";
}

// 3. Testovanie adresárov
echo "<h2>📂 Adresáre:</h2>";
if (defined('DATA_DIR')) {
    $dirs = [
        DATA_DIR => 'Data directory',
        CACHE_DIR ?? 'cache/' => 'Cache directory', 
        LOGS_DIR ?? 'logs/' => 'Logs directory'
    ];
    
    foreach ($dirs as $dir => $desc) {
        if (is_dir($dir)) {
            $writable = is_writable($dir) ? 'writable' : 'not writable';
            echo "<span class='ok'>✅ $dir - $desc ($writable)</span><br>";
        } else {
            echo "<span class='warning'>⚠️ $dir - $desc (vytváram...)</span><br>";
            mkdir($dir, 0755, true);
        }
    }
}

// 4. Test webhook endpoint
echo "<h2>🔗 Test Webhook:</h2>";
try {
    $webhook_url = 'webhook.php?action=current';
    if (file_exists('webhook.php')) {
        // Simuluj request
        ob_start();
        $_GET['action'] = 'current';
        include 'webhook.php';
        $output = ob_get_clean();
        
        if (!empty($output)) {
            echo "<span class='ok'>✅ Webhook odpovedá</span><br>";
            echo "<pre>Response: " . htmlspecialchars(substr($output, 0, 200)) . "...</pre>";
        } else {
            echo "<span class='warning'>⚠️ Webhook nevracia dáta</span><br>";
        }
    } else {
        echo "<span class='error'>❌ webhook.php neexistuje</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Webhook error: " . $e->getMessage() . "</span><br>";
}

// 5. Test stream metadata
echo "<h2>🎵 Test Stream Metadata:</h2>";
if (defined('ICY_SERVER')) {
    $stream_url = ICY_SERVER;
    echo "Testujem: $stream_url<br>";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'IghisHomeRadio/1.0'
        ]
    ]);
    
    $response = @file_get_contents($stream_url, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if ($data) {
            echo "<span class='ok'>✅ Stream metadata funguje</span><br>";
            echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<span class='warning'>⚠️ Stream vracia nevalidný JSON</span><br>";
            echo "<pre>" . htmlspecialchars(substr($response, 0, 300)) . "</pre>";
        }
    } else {
        echo "<span class='error'>❌ Nedá sa pripojiť k stream serveru</span><br>";
    }
} else {
    echo "<span class='error'>❌ ICY_SERVER nie je definovaný</span><br>";
}

// 6. Test .env súboru
echo "<h2>🔐 Environment:</h2>";
if (file_exists('.env')) {
    echo "<span class='ok'>✅ .env súbor existuje</span><br>";
    // Neukážeme obsah kvôli bezpečnosti
} else {
    echo "<span class='warning'>⚠️ .env súbor neexistuje - používajú sa defaultné hodnoty</span><br>";
}

// 7. Vytvorenie test súboru pre current song
echo "<h2>🎼 Vytvorenie test dát:</h2>";
if (defined('CURRENT_SONG_FILE')) {
    $test_song = [
        'timestamp' => time(),
        'datetime' => date('Y-m-d H:i:s'),
        'source' => 'Test',
        'data' => [
            'title' => 'Test Song',
            'artist' => 'Test Artist',
            'album' => 'Test Album',
            'year' => 2024,
            'genre' => 'Test Genre',
            'artwork' => 'https://i.imgur.com/Ym67jVJ.jpeg',
            'label' => 'Test Label',
            'all_sources' => ['Test']
        ]
    ];
    
    if (file_put_contents(CURRENT_SONG_FILE, json_encode($test_song, JSON_PRETTY_PRINT))) {
        echo "<span class='ok'>✅ Test song vytvorený: " . CURRENT_SONG_FILE . "</span><br>";
    } else {
        echo "<span class='error'>❌ Nedá sa zapísať test song</span><br>";
    }
}

echo "<h2>🎯 Odporúčané kroky:</h2>";
echo "<ol>";
echo "<li>Ak sú červené ❌ chyby, oprav ich najprv</li>";
echo "<li>Skontroluj .env súbor - použi env-example.txt ako šablónu</li>";
echo "<li>Spusti index.html a otvor Developer Tools (F12) pre console errors</li>";
echo "<li>Test webhook endpoint priamo: webhook.php?action=current</li>";
echo "</ol>";

?>