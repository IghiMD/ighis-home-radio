<?php
/**
 * OPRAVA: Zmena HTTP na HTTPS pre metadata endpoint
 * Pridaj túto opravu do config.php alebo .env súboru
 */

// V config.php zmeň:
// STARÁ HODNOTA:
// define('ICY_SERVER', 'http://fra-pioneer01.dedicateware.com:2415/status-json.xsl');

// NOVÁ HODNOTA (HTTPS):
define('ICY_SERVER', 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl');

// ALEBO v .env súbore zmeň:
// ICY_SERVER="https://fra-pioneer01.dedicateware.com:2415/status-json.xsl"

// Test či HTTPS endpoint funguje
$test_url = 'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl';

echo "<h1>🔧 Test HTTPS metadata endpoint</h1>";
echo "<style>body{font-family:Arial;background:#0a0a0a;color:#fff;padding:20px;} .ok{color:#00ff88;} .error{color:#ff2d55;} pre{background:#1a1a1a;padding:15px;border-radius:8px;}</style>";

echo "<h2>📡 Testujem HTTPS metadata: $test_url</h2>";

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'IghisHomeRadio/1.0'
    ]
]);

$response = @file_get_contents($test_url, false, $context);

if ($response) {
    echo "<span class='ok'>✅ HTTPS metadata endpoint funguje!</span><br>";
    
    $data = json_decode($response, true);
    if ($data) {
        echo "<span class='ok'>✅ JSON je validný</span><br>";
        
        // Parse current title
        if (isset($data['icestats']['source'])) {
            $source = is_array($data['icestats']['source']) ? 
                      $data['icestats']['source'][0] : $data['icestats']['source'];
            $title = $source['title'] ?? null;
            
            echo "<h3>🎵 Aktuálne hraje:</h3>";
            echo "<div style='background:#1a1a1a;padding:15px;border-radius:8px;'>";
            echo "<strong>Title:</strong> " . ($title ?? 'N/A') . "<br>";
            echo "<strong>Listeners:</strong> " . ($source['listeners'] ?? 'N/A') . "<br>";
            echo "<strong>Bitrate:</strong> " . ($source['bitrate'] ?? 'N/A') . "<br>";
            echo "</div>";
            
            // Parse artist - title if possible
            if ($title && strpos($title, ' - ') !== false) {
                $parts = explode(' - ', $title, 2);
                $artist = trim($parts[0]);
                $track = trim($parts[1]);
                
                echo "<h3>🎤 Parsed:</h3>";
                echo "<div style='background:#1a1a1a;padding:15px;border-radius:8px;'>";
                echo "<strong>Artist:</strong> $artist<br>";
                echo "<strong>Title:</strong> $track<br>";
                echo "</div>";
            }
        }
        
        echo "<h3>📋 Úlohy:</h3>";
        echo "<ol>";
        echo "<li>Zmeň ICY_SERVER v config.php na HTTPS URL</li>";
        echo "<li>Alebo aktualizuj .env súbor</li>";
        echo "<li>Refresh stránku rádia</li>";
        echo "<li>Live metadata by malo fungovať!</li>";
        echo "</ol>";
        
    } else {
        echo "<span class='error'>❌ Nevalidný JSON z HTTPS endpointu</span>";
    }
} else {
    echo "<span class='error'>❌ HTTPS metadata endpoint nefunguje</span><br>";
    echo "<p>Skús alternatívne riešenie...</p>";
    
    // Test alternatívne endpointy
    $alternatives = [
        'https://fra-pioneer01.dedicateware.com:2415/status.xsl',
        'https://fra-pioneer01.dedicateware.com:2415/admin/listclients',
        'https://fra-pioneer01.dedicateware.com:2415/stats'
    ];
    
    echo "<h3>🔄 Test alternatívnych endpointov:</h3>";
    foreach ($alternatives as $alt_url) {
        $alt_response = @file_get_contents($alt_url, false, $context);
        if ($alt_response) {
            echo "<span class='ok'>✅ $alt_url funguje</span><br>";
        } else {
            echo "<span class='error'>❌ $alt_url nefunguje</span><br>";
        }
    }
}

echo "<hr>";
echo "<h2>🔧 Alternatívne riešenie: Proxy endpoint</h2>";
echo "<p>Ak HTTPS metadata nefunguje, vytvor proxy endpoint...</p>";
?>