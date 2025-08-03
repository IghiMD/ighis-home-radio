<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Získaj parametre
$artist = $_GET['artist'] ?? '';
$title = $_GET['title'] ?? '';

if (empty($artist) || empty($title)) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// Volaj Deezer API zo servera
$query = urlencode('artist:"' . $artist . '" track:"' . $title . '"');
$url = "https://api.deezer.com/search?q=$query&limit=1";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'IghisHomeRadio/1.0');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo $response;
} else {
    echo json_encode(['error' => 'Deezer API error', 'code' => $httpCode]);
}
?>
