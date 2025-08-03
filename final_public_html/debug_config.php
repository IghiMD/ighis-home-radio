<?php
// Debug verzia - zobrazí chyby
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n\n";

// Test 1: Existuje config.php?
echo "Test 1: config.php exists? ";
if (file_exists('config.php')) {
    echo "YES\n";
} else {
    echo "NO\n";
}

// Test 2: Je config.php čitateľný?
echo "Test 2: config.php readable? ";
if (is_readable('config.php')) {
    echo "YES\n";
} else {
    echo "NO\n";
}

// Test 3: Skúsime načítať config.php
echo "\nTest 3: Loading config.php...\n";
try {
    include_once 'config.php';
    echo "SUCCESS - config.php loaded\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Kontrola definovaných konštánt
echo "\nTest 4: Checking constants...\n";
$constants = [
    'STREAM_URL',
    'ICY_SERVER', 
    'SPOTIFY_CLIENT_ID',
    'YOUTUBE_API_KEY',
    'LASTFM_API_KEY'
];

foreach ($constants as $const) {
    echo "$const: ";
    if (defined($const)) {
        $value = constant($const);
        if (empty($value)) {
            echo "DEFINED but EMPTY\n";
        } else {
            echo "DEFINED (length: " . strlen($value) . ")\n";
        }
    } else {
        echo "NOT DEFINED\n";
    }
}

echo "</pre>";
?>