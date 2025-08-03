<?php
/**
 * SIMPLIFIED METADATA PROXY - Fixed Version
 * Jednoduchá verzia ktorá funguje bez zložitých cache mechanizmov
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Vytvor cache adresár ak neexistuje
$cacheDir = 'cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

// Jednoduchý cache súbor
$cacheFile = $cacheDir . '/simple_metadata.json';
$cacheTime = 8; // 8 sekúnd cache

// Skontroluj cache
if (file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age < $cacheTime) {
        // Vráť cache
        $cached = @file_get_contents($cacheFile);
        if ($cached) {
            echo $cached;
            exit();
        }
    }
}

// Možné metadata endpointy
$endpoints = [
    'http://fra-pioneer01.dedicateware.com:2410/status-json.xsl',
    'https://fra-pioneer01.dedicateware.com:2415/status-json.xsl'
];

$result = null;

// Skús každý endpoint
foreach ($endpoints as $url) {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'IghisHomeRadio/Simple',
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            $data = @json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['icestats'])) {
                $result = processMetadata($data);
                break;
            }
        }
    } catch (Exception $e) {
        // Pokračuj na ďalší endpoint
        continue;
    }
}

// Ak sa nepodarilo získať metadata, vráť fallback
if (!$result) {
    $result = [
        'status' => 'fallback',
        'timestamp' => time(),
        'message' => 'Using fallback metadata',
        'stream_info' => [
            'title' => 'Ighi\'s Home Radio',
            'listeners' => 0,
            'bitrate' => null
        ],
        'parsed' => [
            'artist' => 'Ighi\'s Home Radio',
            'title' => 'Streaming Live',
            'raw_title' => 'Ighi\'s Home Radio - Streaming Live'
        ]
    ];
}

// Ulož do cache
@file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// Vráť výsledok
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Spracuj metadata z Icecast servera
 */
function processMetadata($data) {
    $enhanced = [
        'status' => 'success',
        'timestamp' => time(),
        'source' => 'Icecast Server'
    ];
    
    if (isset($data['icestats']['source'])) {
        $source = $data['icestats']['source'];
        
        // Môže byť array alebo object
        if (is_array($source) && isset($source[0])) {
            $source = $source[0];
        }
        
        $title = null;
        if (isset($source['title'])) {
            $title = $source['title'];
        } elseif (isset($source['display-title'])) {
            $title = $source['display-title'];
        } elseif (isset($source['yp_currently_playing'])) {
            $title = $source['yp_currently_playing'];
        }
        
        $enhanced['stream_info'] = [
            'title' => $title,
            'listeners' => (int)($source['listeners'] ?? 0),
            'bitrate' => $source['bitrate'] ?? null,
            'server_name' => $source['server_name'] ?? null
        ];
        
        // Parse artist - title
        if (!empty($title)) {
            if (strpos($title, ' - ') !== false) {
                $parts = explode(' - ', $title, 2);
                $enhanced['parsed'] = [
                    'artist' => trim($parts[0]),
                    'title' => trim($parts[1]),
                    'raw_title' => $title
                ];
            } else {
                $enhanced['parsed'] = [
                    'artist' => 'Ighi\'s Home Radio',
                    'title' => $title,
                    'raw_title' => $title
                ];
            }
        } else {
            $enhanced['parsed'] = [
                'artist' => 'Ighi\'s Home Radio',
                'title' => 'Live Stream',
                'raw_title' => 'Ighi\'s Home Radio - Live Stream'
            ];
        }
    } else {
        // Fallback ak nie sú source data
        $enhanced['stream_info'] = [
            'title' => 'Live Stream',
            'listeners' => 0,
            'bitrate' => null
        ];
        
        $enhanced['parsed'] = [
            'artist' => 'Ighi\'s Home Radio',
            'title' => 'Live Stream',
            'raw_title' => 'Ighi\'s Home Radio - Live Stream'
        ];
    }
    
    return $enhanced;
}
?>