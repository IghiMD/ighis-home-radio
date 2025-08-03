<?php
/**
 * 🔍 DEBUG AI INFO
 * Nájde presný problém v ai_info.php
 */

echo "🔍 Debug AI Info - Nájdenie chyby\n";
echo "=================================\n\n";

// 1. SYNTAX CHECK
echo "🧪 1. Kontrolujem syntax...\n";

$syntaxCheck = shell_exec("php -l ai_info.php 2>&1");
echo "Syntax result: $syntaxCheck\n";

if (strpos($syntaxCheck, 'No syntax errors') === false) {
    echo "❌ SYNTAX ERROR NÁJDENÝ!\n";
    echo "Syntax output: $syntaxCheck\n";
    exit;
}

echo "✅ Syntax je OK\n\n";

// 2. REQUIRE CHECK  
echo "🔧 2. Kontrolujem require súbory...\n";

if (!file_exists('config.php')) {
    echo "❌ config.php neexistuje!\n";
    exit;
}

echo "✅ config.php existuje\n";

// Test načítania config
ob_start();
$configError = null;
try {
    require_once 'config.php';
    echo "✅ config.php sa načítal úspešne\n";
} catch (Exception $e) {
    $configError = $e->getMessage();
    echo "❌ Chyba v config.php: $configError\n";
} catch (Error $e) {
    $configError = $e->getMessage();
    echo "❌ Fatal error v config.php: $configError\n";
}
ob_end_clean();

// 3. KONŠTANTY CHECK
echo "\n🔍 3. Kontrolujem definované konštanty...\n";

$requiredConstants = [
    'AI_CACHE_DIR',
    'TRIVIA_CACHE_EXPIRY', 
    'AI_MAX_WORDS',
    'AI_DEFAULT_TIMER',
    'AI_MIN_TIMER'
];

foreach ($requiredConstants as $const) {
    if (defined($const)) {
        echo "✅ $const = " . constant($const) . "\n";
    } else {
        echo "❌ $const nie je definovaný!\n";
    }
}

// 4. ADRESÁRE CHECK
echo "\n📁 4. Kontrolujem adresáre...\n";

if (defined('AI_CACHE_DIR')) {
    $cacheDir = AI_CACHE_DIR;
    
    if (!file_exists($cacheDir)) {
        echo "⚠️  $cacheDir neexistuje, vytvárам...\n";
        if (mkdir($cacheDir, 0755, true)) {
            echo "✅ $cacheDir vytvorený\n";
        } else {
            echo "❌ Nemôžem vytvoriť $cacheDir\n";
        }
    } else {
        echo "✅ $cacheDir existuje\n";
    }
    
    if (is_writable($cacheDir)) {
        echo "✅ $cacheDir je zapisovateľný\n";
    } else {
        echo "❌ $cacheDir nie je zapisovateľný!\n";
    }
} else {
    echo "❌ AI_CACHE_DIR nie je definovaný!\n";
}

// 5. FUNKCIE CHECK
echo "\n🔧 5. Kontrolujem kľúčové funkcie...\n";

$requiredFunctions = ['isServiceEnabled', 'getActiveOpenAIKey', 'markOpenAIKeyFailed'];

foreach ($requiredFunctions as $func) {
    if (function_exists($func)) {
        echo "✅ $func() existuje\n";
    } else {
        echo "❌ $func() neexistuje!\n";
    }
}

// 6. SIMULÁCIA VOLANIA
echo "\n🧪 6. Simulujem volanie ai_info.php...\n";

// Nastavíme $_GET pre test
$_GET = [
    'artist' => 'Test Artist',
    'title' => 'Test Title',  
    'lang' => 'en',
    'max_words' => '50'
];

echo "Simulované GET parametre:\n";
print_r($_GET);

// Zachyť output a errors
ob_start();
$oldErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "PHP ERROR [$errno]: $errstr in $errfile:$errline\n";
});

echo "\n--- ZAČIATOK VOLANIA AI_INFO ---\n";

try {
    // Include súbor ale nie ho spustiť celý
    $aiInfoContent = file_get_contents('ai_info.php');
    
    // Odstraň exit; na konci aby sme mohli pokračovať
    $aiInfoContent = str_replace('exit;', '// exit;', $aiInfoContent);
    
    // Nahraď require_once aby sa neopakoval
    $aiInfoContent = str_replace("require_once 'config.php';", "// require_once 'config.php'; // Already loaded", $aiInfoContent);
    
    eval('?>' . $aiInfoContent);
    
} catch (ParseError $e) {
    echo "❌ PARSE ERROR: " . $e->getMessage() . "\n";
    echo "   Súbor: " . $e->getFile() . "\n";
    echo "   Riadok: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "   Súbor: " . $e->getFile() . "\n";
    echo "   Riadok: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   Súbor: " . $e->getFile() . "\n"; 
    echo "   Riadok: " . $e->getLine() . "\n";
}

echo "--- KONIEC VOLANIA AI_INFO ---\n";

$output = ob_get_clean();
set_error_handler($oldErrorHandler);

echo "📄 OUTPUT Z ai_info.php:\n";
echo "========================\n";
echo $output;
echo "\n========================\n";

// 7. LOG CHECK
echo "\n📜 7. Kontrolujem error logy...\n";

$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    echo "📄 Posledné error log záznamy:\n";
    $logs = file($errorLog);
    $recentLogs = array_slice($logs, -10);
    foreach ($recentLogs as $log) {
        if (strpos($log, 'ai_info') !== false) {
            echo "   " . trim($log) . "\n";
        }
    }
} else {
    echo "⚠️  Error log nedostupný\n";
}

echo "\n🎯 ZÁVER:\n";
echo "=========\n";
if ($configError) {
    echo "❌ Hlavný problém: Chyba v config.php\n";
    echo "   Oprav config.php pred pokračovaním\n";
} else {
    echo "ℹ️  Spusti tento script a pošli mi výsledok\n";
    echo "   Pomôže mi nájsť presný problém\n";
}
?>