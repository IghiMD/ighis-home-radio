<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Monitor Debug Test</h2>";

// Test 1: Config
echo "1. Loading config.php...<br>";
try {
    require_once 'config.php';
    echo "✅ Config loaded<br>";
} catch (Exception $e) {
    echo "❌ Config error: " . $e->getMessage() . "<br>";
    die();
}

// Test 2: Auth
echo "<br>2. Loading auth.php...<br>";
try {
    require_once 'auth.php';
    echo "✅ Auth loaded<br>";
} catch (Exception $e) {
    echo "❌ Auth error: " . $e->getMessage() . "<br>";
    die();
}

// Test 3: Session
echo "<br>3. Setting session...<br>";
session_start();
$_SESSION['authenticated'] = true;
$_SESSION['username'] = 'ighi';
$_SESSION['login_time'] = time();
echo "✅ Session set<br>";

// Test 4: Required files
echo "<br>4. Checking required files...<br>";
$requiredFiles = [
    'CURRENT_SONG_FILE' => CURRENT_SONG_FILE,
    'HISTORY_FILE' => HISTORY_FILE,
    'LOG_FILE' => LOG_FILE,
    'METADATA_CACHE_FILE' => METADATA_CACHE_FILE,
    'TRIVIA_CACHE_FILE' => TRIVIA_CACHE_FILE
];

foreach ($requiredFiles as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name exists: $path<br>";
    } else {
        echo "❌ $name MISSING: $path<br>";
        // Try to create it
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, '{}');
        echo "   → Created empty file<br>";
    }
}

// Test 5: Functions
echo "<br>5. Testing monitor functions...<br>";
try {
    // Test if function exists
    if (function_exists('getSystemStatus')) {
        echo "✅ getSystemStatus function exists<br>";
    } else {
        echo "❌ getSystemStatus function NOT FOUND<br>";
    }
    
    if (function_exists('calculateMetadataCompleteness')) {
        echo "✅ calculateMetadataCompleteness function exists<br>";
    } else {
        echo "❌ calculateMetadataCompleteness function NOT FOUND<br>";
    }
} catch (Exception $e) {
    echo "❌ Function test error: " . $e->getMessage() . "<br>";
}

echo "<br><strong>Monitor should work if all above are ✅</strong><br>";
echo "<br><a href='monitor.php'>Try Monitor Now</a>";
?>
