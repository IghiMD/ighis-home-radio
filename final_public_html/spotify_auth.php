<?php
/**
 * Spotify autentifikácia - server side
 * Frontend volá tento endpoint pre získanie tokenu
 */

require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: private, max-age=3000');

// Check if Spotify is configured
if (!isServiceEnabled('spotify')) {
    http_response_code(503);
    echo json_encode(['error' => 'Spotify service not configured']);
    exit;
}

// Get Spotify access token
try {
    $authString = base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $authString,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('CURL error: ' . $error);
    }
    
    if ($httpCode === 200) {
        // Success - return the token
        echo $response;
    } else {
        // Error from Spotify
        http_response_code($httpCode);
        
        if ($response) {
            echo $response;
        } else {
            echo json_encode(['error' => 'Authentication failed', 'http_code' => $httpCode]);
        }
    }
    
} catch (Exception $e) {
    // Server error
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
    
    // Log the error
    error_log('Spotify auth error: ' . $e->getMessage());
}
?>