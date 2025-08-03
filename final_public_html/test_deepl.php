<?php
// Test pre DeepL API
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Načítaj konfiguráciu
if (file_exists('config.php')) {
    ob_start();
    require_once 'config.php';
    ob_end_clean();
    
    if (defined('DEEPL_API_KEY')) {
        $apiKey = DEEPL_API_KEY;
    } else {
        die("DEEPL_API_KEY nie je definovaný v config.php");
    }
} else {
    die("Súbor config.php nenájdený");
}

// Testovací text
$textToTranslate = "This is a test message to verify that DeepL API is working correctly.";

// Volanie DeepL API
$url = 'https://api.deepl.com/v2/translate';
$data = [
    'auth_key' => $apiKey,
    'text' => $textToTranslate,
    'target_lang' => 'SK',
];

echo "<h1>DeepL API Test</h1>";
echo "<p><strong>Text na preklad:</strong> $textToTranslate</p>";
echo "<p><strong>API kľúč:</strong> " . substr($apiKey, 0, 5) . "..." . substr($apiKey, -5) . "</p>";

// Volanie API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($error) {
    echo "<p style='color: red'><strong>CURL Chyba:</strong> $error</p>";
} elseif ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['translations'][0]['text'])) {
        $translated = $result['translations'][0]['text'];
        echo "<p style='color: green'><strong>Test úspešný!</strong></p>";
        echo "<p><strong>Preložený text:</strong> $translated</p>";
    } else {
        echo "<p style='color: red'><strong>Chyba:</strong> Nepodarilo sa získať preklad z odpovede</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    }
} else {
    echo "<p style='color: red'><strong>HTTP Chyba:</strong> $httpCode</p>";
    echo "<pre>$response</pre>";
}
?>
