<?php
/**
 * 🔄 SAFE REPLACE SCRIPT
 * Bezpečná výmena ai_info.php s kompletným backup systémom
 */

echo "🔄 Safe Replace Script - ai_info.php\n";
echo "====================================\n\n";

$sourceFile = 'ai_info_complete_fix.php'; // Nový súbor
$targetFile = 'ai_info.php'; // Pôvodný súbor
$backupDir = '_backup_' . date('Y-m-d_H-i-s');

// 1. KONTROLA EXISTENCIE SÚBOROV
echo "🔍 Kontrolujem súbory...\n";

if (!file_exists($sourceFile)) {
    echo "❌ CHYBA: $sourceFile neexistuje!\n";
    echo "   Najprv ulož obsah z artifacts do $sourceFile\n";
    exit(1);
}

echo "✅ $sourceFile existuje (" . number_format(filesize($sourceFile)) . " bytes)\n";

if (file_exists($targetFile)) {
    echo "✅ $targetFile existuje (" . number_format(filesize($targetFile)) . " bytes)\n";
} else {
    echo "⚠️  $targetFile neexistuje - bude vytvorený\n";
}

// 2. VYTVORENIE BACKUP ADRESÁRA
echo "\n📁 Vytváram backup adresár...\n";

if (!mkdir($backupDir, 0755, true)) {
    echo "❌ CHYBA: Nemôžem vytvoriť backup adresár $backupDir\n";
    exit(1);
}

echo "✅ Backup adresár vytvorený: $backupDir\n";

// 3. BACKUP PÔVODNÉHO SÚBORU
if (file_exists($targetFile)) {
    echo "\n💾 Vytváram backup pôvodného súboru...\n";
    
    $backupFile = $backupDir . '/' . $targetFile . '.backup';
    
    if (copy($targetFile, $backupFile)) {
        echo "✅ Backup vytvorený: $backupFile\n";
        
        // Pridaj informácie o backup
        $backupInfo = [
            'original_file' => $targetFile,
            'backup_created' => date('Y-m-d H:i:s'),
            'original_size' => filesize($targetFile),
            'original_md5' => md5_file($targetFile)
        ];
        
        file_put_contents($backupDir . '/backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));
        echo "✅ Backup info uložené\n";
    } else {
        echo "❌ CHYBA: Nemôžem vytvoriť backup!\n";
        exit(1);
    }
}

// 4. SYNTAX CHECK NOVÉHO SÚBORU
echo "\n🧪 Kontrolujem syntax nového súboru...\n";

$syntaxCheck = shell_exec("php -l $sourceFile 2>&1");

if (strpos($syntaxCheck, 'No syntax errors') !== false) {
    echo "✅ Syntax nového súboru je v poriadku\n";
} else {
    echo "❌ CHYBA: Syntax error v novom súbore!\n";
    echo "   $syntaxCheck\n";
    echo "   Oprav chyby pred pokračovaním!\n";
    exit(1);
}

// 5. VÝMENA SÚBORU
echo "\n🔄 Vykonávam výmenu súboru...\n";

if (copy($sourceFile, $targetFile)) {
    echo "✅ Súbor $targetFile úspešne nahradený\n";
    
    // Overenie
    if (filesize($targetFile) > 0) {
        echo "✅ Nový súbor má správnu veľkosť: " . number_format(filesize($targetFile)) . " bytes\n";
    } else {
        echo "❌ CHYBA: Nový súbor je prázdny!\n";
        
        // Obnov z backup
        if (file_exists($backupFile)) {
            copy($backupFile, $targetFile);
            echo "🔄 Obnovený pôvodný súbor z backup\n";
        }
        exit(1);
    }
} else {
    echo "❌ CHYBA: Nemôžem nahradiť súbor!\n";
    exit(1);
}

// 6. FINAL SYNTAX CHECK
echo "\n🔍 Finálna kontrola syntax...\n";

$finalCheck = shell_exec("php -l $targetFile 2>&1");

if (strpos($finalCheck, 'No syntax errors') !== false) {
    echo "✅ Finálny syntax check úspešný\n";
} else {
    echo "❌ CHYBA: Syntax error po výmene!\n";
    echo "   $finalCheck\n";
    
    // Obnov z backup
    if (file_exists($backupFile)) {
        copy($backupFile, $targetFile);
        echo "🔄 Obnovený pôvodný súbor z backup\n";
    }
    exit(1);
}

// 7. VYTVORENIE ROLLBACK SCRIPTU
echo "\n📝 Vytváram rollback script...\n";

$rollbackScript = "<?php
/**
 * ROLLBACK SCRIPT
 * Obnoví pôvodný ai_info.php z backup
 */

echo \"🔄 ROLLBACK ai_info.php\\n\";
echo \"=====================\\n\\n\";

\$backupFile = '$backupFile';
\$targetFile = '$targetFile';

if (file_exists(\$backupFile)) {
    if (copy(\$backupFile, \$targetFile)) {
        echo \"✅ Pôvodný súbor obnovený z backup\\n\";
        echo \"📁 Backup zostáva v: $backupDir\\n\";
    } else {
        echo \"❌ CHYBA: Nemôžem obnovi súbor\\n\";
    }
} else {
    echo \"❌ CHYBA: Backup súbor neexistuje\\n\";
}
?>";

file_put_contents($backupDir . '/rollback.php', $rollbackScript);
echo "✅ Rollback script vytvorený: $backupDir/rollback.php\n";

// 8. QUICK TEST
echo "\n🧪 Rýchly test funkčnosti...\n";

$testUrl = "ai_info.php?artist=Test&title=Test&lang=en";
$testCommand = "php -f ai_info.php artist=Test title=Test lang=en 2>&1";

$testResult = shell_exec($testCommand);

if (strpos($testResult, 'Fatal error') === false && strpos($testResult, 'Parse error') === false) {
    echo "✅ Základný test prešiel\n";
} else {
    echo "⚠️  Test odhalil možné problémy:\n";
    echo "   " . substr($testResult, 0, 200) . "...\n";
    echo "   Skontroluj ai_info.php manuálne\n";
}

// 9. CLEANUP
echo "\n🧹 Čistenie...\n";

// Odstráň source súbor ak je všetko OK
if (file_exists($sourceFile) && $sourceFile !== $targetFile) {
    rename($sourceFile, $backupDir . '/' . $sourceFile . '.used');
    echo "✅ Source súbor presunutý do backup\n";
}

// 10. SUMMARY
echo "\n📊 SÚHRN VÝMENY:\n";
echo "================\n";
echo "✅ Pôvodný súbor: $targetFile\n";
echo "✅ Backup vytvorený: $backupFile\n";
echo "✅ Nový súbor nahral: $sourceFile → $targetFile\n";
echo "✅ Syntax check: ÚSPEŠNÝ\n";
echo "✅ Backup adresár: $backupDir\n";
echo "✅ Rollback script: $backupDir/rollback.php\n\n";

echo "🔄 ĎALŠIE KROKY:\n";
echo "1. Otestuj ai_info.php v prehliadači\n";
echo "2. Ak nefunguje, spusti: php $backupDir/rollback.php\n";
echo "3. Ak funguje, môžeš zmazať $backupDir za týždeň\n\n";

echo "🎉 VÝMENA DOKONČENÁ ÚSPEŠNE!\n";
?>