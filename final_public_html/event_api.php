<?php
/**
 * DVOJJAZYČNÉ EVENT API - Poskytuje údaje o udalosti pre frontend
 * Súbor: event_api.php (AKTUALIZOVANÝ)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$EVENTS_FILE = 'data/events.json';

// Získaj požadovaný jazyk z URL parametra
$requestedLang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
if (!in_array($requestedLang, ['en', 'sk'])) {
    $requestedLang = 'en'; // fallback na angličtinu
}

function loadEvents() {
    global $EVENTS_FILE;
    if (!file_exists($EVENTS_FILE)) {
        return [
            'current_event' => [
                'title_en' => '"Hop or Drop" - Battle of Generations',
                'title_sk' => '"Hop alebo Trop" - Súboj generácií',
                'description_en' => 'Join us for our special 2-hour live show where Ighi and Kika battle it out with their music choices. Two generations, countless hits, one amazing radio experience!',
                'description_sk' => 'Pripojte sa k nášmu špeciálnemu 2-hodinovému live show, kde si Ighi a Kika súperia so svojimi hudobnými voľbami. Dve generácie, nespočetné hity, jeden úžasný rádiový zážitok!',
                'date' => '',
                'time' => '',
                'image' => 'https://i.imgur.com/6jMnbOD.jpeg',
                'status' => 'planning',
                'created' => date('Y-m-d H:i:s'),
                'updated' => date('Y-m-d H:i:s'),
                // Spätná kompatibilita
                'title' => '"Hop or Drop" - Battle of Generations',
                'description' => 'Join us for our special 2-hour live show where Ighi and Kika battle it out with their music choices. Two generations, countless hits, one amazing radio experience!'
            ]
        ];
    }
    return json_decode(file_get_contents($EVENTS_FILE), true);
}

function formatEventForFrontend($event, $lang) {
    // Formátovanie dátumu a času
    $formattedDate = '';
    $formattedTime = '';
    $timestamp = null;
    
    if (!empty($event['date'])) {
        $date = new DateTime($event['date']);
        $formattedDate = $date->format('d.m.Y');
        
        if (!empty($event['time'])) {
            $dateTime = new DateTime($event['date'] . ' ' . $event['time']);
            $formattedTime = $dateTime->format('H:i');
            $timestamp = $dateTime->getTimestamp();
        }
    }
    
    // Určenie či je udalosť v budúcnosti
    $isUpcoming = false;
    $timeRemaining = null;
    
    if ($timestamp) {
        $now = time();
        $isUpcoming = $timestamp > $now;
        if ($isUpcoming) {
            $timeRemaining = $timestamp - $now;
        }
    }
    
    // Zabezpeč že existujú dvojjazyčné polia
    $titleField = 'title_' . $lang;
    $descriptionField = 'description_' . $lang;
    
    // Fallback na anglické verzie ak slovenské neexistujú
    $title = isset($event[$titleField]) ? $event[$titleField] :
             (isset($event['title_en']) ? $event['title_en'] :
             ($event['title'] ?? ''));
             
    $description = isset($event[$descriptionField]) ? $event[$descriptionField] :
                  (isset($event['description_en']) ? $event['description_en'] :
                  ($event['description'] ?? ''));
    
    // Status texty v slovenčine
    $statusTexts = [
        'en' => [
            'planning' => 'Planning',
            'announced' => 'Announced',
            'live' => 'Live Now',
            'completed' => 'Completed'
        ],
        'sk' => [
            'planning' => 'Plánovanie',
            'announced' => 'Ohlásené',
            'live' => 'Naživo',
            'completed' => 'Ukončené'
        ]
    ];
    
    $statusText = $statusTexts[$lang][$event['status']] ?? $event['status'];
    
    return [
        'title' => $title,
        'description' => $description,
        'date' => $event['date'],
        'time' => $event['time'],
        'formatted_date' => $formattedDate,
        'formatted_time' => $formattedTime,
        'image' => $event['image'],
        'status' => $event['status'],
        'status_text' => $statusText,
        'is_upcoming' => $isUpcoming,
        'time_remaining_seconds' => $timeRemaining,
        'updated' => $event['updated'] ?? '',
        'timestamp' => $timestamp,
        'language' => $lang,
        // Pridaj obidve jazyky pre potrebu frontendu
        'all_languages' => [
            'en' => [
                'title' => $event['title_en'] ?? $event['title'] ?? '',
                'description' => $event['description_en'] ?? $event['description'] ?? ''
            ],
            'sk' => [
                'title' => $event['title_sk'] ?? $event['title'] ?? '',
                'description' => $event['description_sk'] ?? $event['description'] ?? ''
            ]
        ]
    ];
}

// Získaj debugging info ak je požadovaný
$debug = isset($_GET['debug']) ? true : false;

try {
    $events = loadEvents();
    $currentEvent = $events['current_event'];
    
    $response = [
        'success' => true,
        'data' => formatEventForFrontend($currentEvent, $requestedLang),
        'meta' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'api_version' => '2.0',
            'language' => $requestedLang,
            'supported_languages' => ['en', 'sk']
        ]
    ];
    
    // Pridaj debugging informácie ak sú požadované
    if ($debug) {
        $response['debug'] = [
            'raw_event' => $currentEvent,
            'request_lang' => $requestedLang,
            'has_bilingual_fields' => [
                'title_en' => isset($currentEvent['title_en']),
                'title_sk' => isset($currentEvent['title_sk']),
                'description_en' => isset($currentEvent['description_en']),
                'description_sk' => isset($currentEvent['description_sk'])
            ]
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Chyba pri načítaní údajov o udalosti',
        'message' => $e->getMessage(),
        'language' => $requestedLang
    ], JSON_UNESCAPED_UNICODE);
}
?>
