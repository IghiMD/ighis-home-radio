<?php
/**
 * Setup Script pre Ighi's Home Radio
 * Automaticky nastaví a opraví systém
 */

echo "<h1>🎵 Ighi's Home Radio - Automatické nastavenie</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; background: #0a0a0a; color: #fff; padding: 20px; line-height: 1.6; }
    .ok { color: #00ff88; font-weight: bold; }
    .error { color: #ff2d55; font-weight: bold; }
    .warning { color: #ff8c00; font-weight: bold; }
    .info { color: #60a5fa; }
    pre { background: #1a1a1a; padding: 15px; border-radius: 8px; overflow: auto; border-left: 4px solid #60a5fa; }
    .section { margin: 20px 0; padding: 15px; background: #1a1a1a; border-radius: 8px; }
    .button { background: #60a5fa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; margin: 5px; }
    .success-box { background: #0f2419; border: 2px solid #00ff88; padding: 15px; border-radius: 8px; margin: 20px 0; }
</style>";

$setupSteps = [];
$errors = [];
$warnings = [];

// Krok 1: Kontrola PHP verzie
echo "<div class='section'>";
echo "<h2>🔧 Krok 1: Kontrola PHP</h2>";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "<span class='ok'>✅ PHP verzia: " . PHP_VERSION . " (OK)</span><br>";
    $setupSteps[] = "PHP verzia je kompatibilná";
} else {
    echo "<span class='error'>❌ PHP verzia: " . PHP_VERSION . " (vyžaduje sa 7.4+)</span><br>";
    $errors[] = "Aktualizuj PHP na verziu 7.4 alebo vyššiu";
}
echo "</div>";

// Krok 2: Vytvorenie adresárovej štruktúry
echo "<div class='section'>";
echo "<h2>📁 Krok 2: Vytvorenie adresárov</h2>";
$directories = [
    'data' => 'Dáta aplikácie',
    'cache' => 'Cache súbory',
    'logs' => 'Log súbory',
    'backups' => 'Zálohy'
];

foreach ($directories as $dir => $desc) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "<span class='ok'>✅ Vytvorený: $dir/ ($desc)</span><br>";
            $setupSteps[] = "Adresár $dir vytvorený";
        } else {
            echo "<span class='error'>❌ Nepodarilo sa vytvoriť: $dir/</span><br>";
            $errors[] = "Skontroluj oprávnenia pre vytvorenie adresára $dir";
        }
    } else {
        echo "<span class='info'>ℹ️ Existuje: $dir/ ($desc)</span><br>";
    }
    
    // Vytvor .htaccess pre ochranu
    $htaccessPath = $dir . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, "Order deny,allow\nDeny from all\n");
        echo "<span class='ok'>🔒 Ochrana vytvorená: $htaccessPath</span><br>";
    }
}
echo "</div>";

// Krok 3: Kontrola a vytvorenie .env súboru
echo "<div class='section'>";
echo "<h2>⚙️ Krok 3: Konfigurácia (.env súbor)</h2>";
if (!file_exists('.env')) {
    // Skopíruj example ak existuje
    if (file_exists('env-example.txt')) {
        copy('env-example.txt', '.env');
        echo "<span class='ok'>✅ .env súbor vytvorený z šablóny</span><br>";
        echo "<span class='warning'>⚠️ DÔLEŽITÉ: Uprav .env súbor s vlastnými API kľúčmi!</span><br>";
        $warnings[] = "Nastav svoje API kľúče v .env súbore";
    } else {
        // Vytvor základný .env
        $envContent = "# Ighi's Home Radio Configuration
STREAM_URL=\"https://fra-pioneer01.dedicateware.com:2415/stream\"
ICY_SERVER=\"http://fra-pioneer01.dedicateware.com:2415/status-json.xsl\"

# Admin prístup - ZMEŇ TIETO HODNOTY!
ADMIN_USERNAME=\"admin\"
ADMIN_PASSWORD=\"changeme123\"
ADMIN_TOKEN=\"change-this-token\"

# Služby (nechaj prázdne ak nemáš API kľúče)
SPOTIFY_CLIENT_ID=\"\"
SPOTIFY_CLIENT_SECRET=\"\"
YOUTUBE_API_KEY=\"\"
LASTFM_API_KEY=\"\"

# AI služby
PERPLEXITY_API_KEY=\"\"
OPENAI_API_KEY=\"\"
GEMINI_API_KEY=\"\"

# Systémové nastavenia
DEBUG_MODE=\"false\"
MUSICBRAINZ_ENABLED=\"true\"
DEEZER_ENABLED=\"true\"
";
        file_put_contents('.env', $envContent);
        echo "<span class='ok'>✅ Základný .env súbor vytvorený</span><br>";
        echo "<span class='warning'>⚠️ DÔLEŽITÉ: Uprav .env súbor s vlastnými hodnotami!</span><br>";
        $warnings[] = "Nastav svoje API kľúče a admin prístupy v .env súbore";
    }
    $setupSteps[] = ".env konfigurácia vytvorená";
} else {
    echo "<span class='info'>ℹ️ .env súbor už existuje</span><br>";
}
echo "</div>";

// Krok 4: Test konfigurácie
echo "<div class='section'>";
echo "<h2>🧪 Krok 4: Test konfigurácie</h2>";
if (file_exists('config.php')) {
    try {
        require_once 'config.php';
        echo "<span class='ok'>✅ config.php načítaný úspešne</span><br>";
        
        // Test základných konštánt
        $required_constants = ['STREAM_URL', 'DATA_DIR', 'LOGS_DIR'];
        foreach ($required_constants as $const) {
            if (defined($const)) {
                echo "<span class='ok'>✅ $const: " . constant($const) . "</span><br>";
            } else {
                echo "<span class='error'>❌ $const nie je definovaný</span><br>";
                $errors[] = "Konštanta $const chýba v konfigurácii";
            }
        }
        
        $setupSteps[] = "Konfigurácia načítaná a skontrolovaná";
    } catch (Exception $e) {
        echo "<span class='error'>❌ Chyba v config.php: " . $e->getMessage() . "</span><br>";
        $errors[] = "Oprav chyby v config.php súbore";
    }
} else {
    echo "<span class='error'>❌ config.php neexistuje</span><br>";
    $errors[] = "config.php súbor chýba";
}
echo "</div>";

// Krok 5: Vytvorenie test súborov
echo "<div class='section'>";
echo "<h2>🎵 Krok 5: Vytvorenie test dát</h2>";

// Current song test file
if (defined('CURRENT_SONG_FILE')) {
    $testSong = [
        'timestamp' => time(),
        'datetime' => date('Y-m-d H:i:s'),
        'source' => 'Setup',
        'data' => [
            'title' => 'Welcome to Ighi\'s Home Radio',
            'artist' => 'Ighi & Kika',
            'album' => 'Family Radio Collection',
            'year' => 2024,
            'genre' => 'Cross-Generational',
            'artwork' => 'https://i.imgur.com/Ym67jVJ.jpeg',
            'label' => 'Ighi\'s Home Radio',
            'all_sources' => ['Setup'],
            'message' => 'System is now configured! Click play to start streaming.'
        ]
    ];
    
    if (file_put_contents(CURRENT_SONG_FILE, json_encode($testSong, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo "<span class='ok'>✅ Test skladba vytvorená</span><br>";
        $setupSteps[] = "Test dáta vytvorené";
    } else {
        echo "<span class='error'>❌ Nepodarilo sa vytvoriť test skladbu</span><br>";
        $errors[] = "Skontroluj oprávnenia pre zápis do data/ adresára";
    }
} else {
    echo "<span class='warning'>⚠️ CURRENT_SONG_FILE nie je definovaný</span><br>";
}

// History test file
if (defined('HISTORY_FILE') && !file_exists(HISTORY_FILE)) {
    $testHistory = [$testSong];
    if (file_put_contents(HISTORY_FILE, json_encode($testHistory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo "<span class='ok'>✅ Test história vytvorená</span><br>";
    }
}
echo "</div>";

// Krok 6: Test endpointov
echo "<div class='section'>";
echo "<h2>🔗 Krok 6: Test API endpointov</h2>";

$endpoints = [
    'webhook.php?action=current' => 'Aktuálna skladba',
    'get_config.php' => 'Konfigurácia pre frontend',
    'webhook.php?action=health' => 'Health check'
];

foreach ($endpoints as $endpoint => $desc) {
    if (file_exists(explode('?', $endpoint)[0])) {
        echo "<span class='ok'>✅ $endpoint - $desc (súbor existuje)</span><br>";
    } else {
        echo "<span class='error'>❌ $endpoint - $desc (súbor chýba)</span><br>";
        $errors[] = "Súbor " . explode('?', $endpoint)[0] . " chýba";
    }
}
echo "</div>";

// Výsledok
echo "<div class='section'>";
echo "<h2>📊 Výsledok nastavenia</h2>";

if (empty($errors)) {
    echo "<div class='success-box'>";
    echo "<h3>🎉 Nastavenie úspešné!</h3>";
    echo "<p>Tvoje rádio je pripravené na spustenie.</p>";
    echo "<h4>Dokončené kroky:</h4><ul>";
    foreach ($setupSteps as $step) {
        echo "<li>✅ $step</li>";
    }
    echo "</ul>";
    
    if (!empty($warnings)) {
        echo "<h4>⚠️ Odporúčania:</h4><ul>";
        foreach ($warnings as $warning) {
            echo "<li class='warning'>$warning</li>";
        }
        echo "</ul>";
    }
    
    echo "<p><strong>Ďalšie kroky:</strong></p>";
    echo "<ol>";
    echo "<li>Uprav .env súbor so svojimi API kľúčmi</li>";
    echo "<li>Otvor index.html v prehliadači</li>";
    echo "<li>Klikni na play button pre spustenie rádia</li>";
    echo "<li>Pre admin prístup choď na monitor.php</li>";
    echo "</ol>";
    
    echo "<p style='text-align: center; margin-top: 20px;'>";
    echo "<a href='index.html' class='button'>🎵 Spustiť Rádio</a>";
    echo "<a href='monitor.php' class='button'>🔧 Admin Panel</a>";
    echo "<a href='test_system.php' class='button'>🧪 Test Systému</a>";
    echo "</p>";
    echo "</div>";
    
} else {
    echo "<div style='background: #2a0f0f; border: 2px solid #ff2d55; padding: 15px; border-radius: 8px;'>";
    echo "<h3>❌ Nastavenie neúplné</h3>";
    echo "<p>Našli sa problémy, ktoré je potrebné vyriešiť:</p>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li class='error'>$error</li>";
    }
    echo "</ul>";
    
    echo "<p><strong>Po oprave problémov spusti setup znova.</strong></p>";
    echo "<p style='text-align: center;'>";
    echo "<a href='setup.php' class='button'>🔄 Spustiť Setup znova</a>";
    echo "</p>";
    echo "</div>";
}
echo "</div>";

// Debug informácie
if (isset($_GET['debug'])) {
    echo "<div class='section'>";
    echo "<h2>🔧 Debug informácie</h2>";
    echo "<pre>";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
    echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    echo "Current Directory: " . __DIR__ . "\n";
    echo "File Permissions: " . decoct(fileperms(__DIR__) & 0777) . "\n";
    
    if (defined('CONFIG_FULLY_LOADED')) {
        echo "\nConfiguration Status: LOADED\n";
        echo "Stream URL: " . (defined('STREAM_URL') ? STREAM_URL : 'NOT DEFINED') . "\n";
        echo "Data Dir: " . (defined('DATA_DIR') ? DATA_DIR : 'NOT DEFINED') . "\n";
    } else {
        echo "\nConfiguration Status: NOT LOADED\n";
    }
    echo "</pre>";
    echo "</div>";
}

echo "<footer style='text-align: center; margin-top: 40px; color: #666;'>";
echo "<p>Ighi's Home Radio Setup Tool v2.0 | Pre pomoc navštív: <a href='mailto:hello@ighishomeradio.com' style='color: #60a5fa;'>hello@ighishomeradio.com</a></p>";
echo "</footer>";
?>