<?php
/**
 * 🚨 EMERGENCY FIX
 * Okamžite opraví ai_info.php minimálnou verziou
 */

echo "🚨 EMERGENCY FIX - Okamžité riešenie\n";
echo "===================================\n\n";

$originalFile = 'ai_info.php';
$emergencyFile = 'ai_info_minimal.php';
$backupDir = '_emergency_backup_' . date('H-i-s');

// 1. Skontroluj súbory
if (!file_exists($emergencyFile)) {
    echo "❌ $emergencyFile neexistuje!\n";
    echo "   Najprv ulož minimálnu verziu z artifacts\n";
    exit(1);
}

// 2. Vytvor emergency backup
if (!mkdir($backupDir, 0755, true)) {
    echo "❌ Nemôžem vytvoriť emergency backup\n";
    exit(1);
}

if (file_exists($originalFile)) {
    copy($originalFile, "$backupDir/ai_info.php.broken");
    echo "✅ Rozbitý súbor uložený do $backupDir/ai_info.php.broken\n";
}

// 3. Nahraď minimálnou verziou
if (copy($emergencyFile, $originalFile)) {
    echo "✅ ai_info.php nahradený minimálnou verziou\n";
    
    // Test syntax
    $syntaxCheck = shell_exec("php -l $originalFile 2>&1");
    if (strpos($syntaxCheck, 'No syntax errors') !== false) {
        echo "✅ Syntax je OK\n";
    } else {
        echo "❌ Stále syntax error: $syntaxCheck\n";
        exit(1);
    }
    
    // Quick test
    $testResult = shell_exec("php -f $originalFile artist=Test title=Test lang=en 2>&1");
    if (strpos($testResult, '"content"') !== false) {
        echo "✅ Minimálna verzia FUNGUJE!\n";
    } else {
        echo "⚠️  Test result: " . substr($testResult, 0, 100) . "\n";
    }
    
    echo "\n🎉 EMERGENCY FIX DOKONČENÝ!\n";
    echo "===========================\n";
    echo "✅ ai_info.php teraz používa minimálnu verziu\n";
    echo "✅ Bude fungovať s OpenAI (ak máte kľúč) alebo fallback textom\n";
    echo "✅ Rozbitý súbor je v $backupDir/\n\n";
    
    echo "🔄 Pre obnovenie komplexnej verzie neskôr:\n";
    echo "   1. Oprav chyby v komplexnej verzii\n";
    echo "   2. Nahraď ai_info.php opravenou verziou\n\n";
    
    echo "🧪 OTESTUJ TERAZ:\n";
    echo "   http://vaša-doména.com/ai_info.php?artist=Queen&title=Test&lang=sk\n";
    
} else {
    echo "❌ Nemôžem nahradiť súbor\n";
    exit(1);
}
?>