<?php
// test_whatsapp.php
require_once 'config.php';
require_once 'notifications.php';

$testPhone = '+421900123456'; // VAŠE ČÍSLO
$testMessage = "🎵 Test z Ighi's Home Radio!\n\nKonfigurácia funguje správne! 🎉";

echo "Testing " . env('WHATSAPP_METHOD', 'none') . " WhatsApp...\n";
echo "Twilio SID: " . (env('TWILIO_SID') ? 'Configured' : 'Missing') . "\n";

$result = sendWhatsApp($testPhone, $testMessage);
echo $result ? "✅ Success!" : "❌ Failed!";
?>