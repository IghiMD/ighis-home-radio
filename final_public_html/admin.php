<?php
/**
 * ROBUST ADMIN SYSTEM - Správa udalostí pre Ighi's Home Radio
 * Version 3.1 - SECURE UNIFIED AUTH VERSION
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// NAČÍTAJ JEDNOTNÚ KONFIGURÁCIU
require_once 'config.php';

// POUŽÍVAJ HESLÁ Z .ENV !!! 
$ADMIN_PASSWORD = env('ADMIN_PASSWORD', ''); // Žiadne default heslo!
$EVENTS_FILE = DATA_DIR . 'events.json';
$UPLOADS_DIR = 'uploads/events/';

// Kontrola či je heslo nastavené
if (empty($ADMIN_PASSWORD)) {
    die('ERROR: Admin password not configured. Please set ADMIN_PASSWORD in .env file.');
}

// Vytvor adresáre ak neexistujú
$directories = ['data', 'uploads', 'uploads/events'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Funkcie
function loadEvents() {
    global $EVENTS_FILE;
    
    if (!file_exists($EVENTS_FILE)) {
        // Predvolené údaje
        $defaultEvents = [
            'current_event' => [
                'title_en' => '"Hop or Drop" - Battle of Generations',
                'title_sk' => '"Hop alebo Trop" - Súboj generácií',
                'description_en' => 'Join us for our special 2-hour live show where Ighi and Kika battle it out with their music choices.',
                'description_sk' => 'Pripojte sa k nášmu špeciálnemu 2-hodinovému live show, kde si Ighi a Kika súperia so svojimi hudobnými voľbami.',
                'date' => '',
                'time' => '',
                'image' => 'https://i.imgur.com/6jMnbOD.jpeg',
                'status' => 'planning',
                'created' => date('Y-m-d H:i:s'),
                'updated' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Pokús sa vytvoriť súbor
        $dir = dirname($EVENTS_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        @file_put_contents($EVENTS_FILE, json_encode($defaultEvents, JSON_PRETTY_PRINT));
        return $defaultEvents;
    }
    
    $content = @file_get_contents($EVENTS_FILE);
    if ($content === false) {
        return ['current_event' => []];
    }
    
    $data = @json_decode($content, true);
    return $data ?: ['current_event' => []];
}

function saveEvents($events) {
    global $EVENTS_FILE;
    
    $events['current_event']['updated'] = date('Y-m-d H:i:s');
    
    // Zabezpeč spätnu kompatibilitu
    if (isset($events['current_event']['title_en'])) {
        $events['current_event']['title'] = $events['current_event']['title_en'];
    }
    if (isset($events['current_event']['description_en'])) {
        $events['current_event']['description'] = $events['current_event']['description_en'];
    }
    
    $json = json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return @file_put_contents($EVENTS_FILE, $json) !== false;
}

function uploadImage($file) {
    global $UPLOADS_DIR;
    
    // Kontroly
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Neplatný upload'];
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Získaj MIME typ
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Nepovolený typ súboru'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Súbor je príliš veľký (max 5MB)'];
    }
    
    // Vytvor adresár ak neexistuje
    if (!is_dir($UPLOADS_DIR)) {
        @mkdir($UPLOADS_DIR, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'event_' . date('Y-m-d_H-i-s') . '.' . $extension;
    $filepath = $UPLOADS_DIR . $filename;
    
    if (@move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'url' => $filepath];
    }
    
    return ['success' => false, 'error' => 'Chyba pri nahrávaní súboru'];
}

// Spracovanie prihlásenia - ZJEDNOTENÉ S OSTATNÝMI SÚBORMI
$loginError = '';
$isLoggedIn = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// Kontrola session timeout
if ($isLoggedIn && isset($_SESSION['login_time'])) {
    if ((time() - $_SESSION['login_time']) > 14400) { // 4 hodiny
        session_unset();
        session_destroy();
        $isLoggedIn = false;
        $loginError = 'Session expired. Please login again.';
    }
}

if (isset($_POST['login'])) {
    $password = $_POST['password'] ?? '';
    
    if ($password === $ADMIN_PASSWORD) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = 'admin';
        $_SESSION['login_time'] = time();
        $isLoggedIn = true;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Nesprávne heslo!';
        
        // Log failed attempt
        if (defined('AUTH_LOG_FILE')) {
            $logEntry = sprintf(
                "[%s] [LOGIN_FAILED] IP: %s, User-Agent: %s\n",
                date('Y-m-d H:i:s'),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            );
            @file_put_contents(AUTH_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }
}

// Spracovanie odhlásenia
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Spracovanie aktualizácie udalosti
$updateSuccess = '';
$updateError = '';

if ($isLoggedIn && isset($_POST['update_event'])) {
    $events = loadEvents();
    
    // Upload nového obrázka
    $imageUrl = $events['current_event']['image'] ?? '';
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadImage($_FILES['event_image']);
        if ($uploadResult['success']) {
            $imageUrl = $uploadResult['url'];
        } else {
            $updateError = $uploadResult['error'];
        }
    }
    
    if (empty($updateError)) {
        // Aktualizuj údaje
        $events['current_event'] = [
            'title_en' => $_POST['title_en'] ?? '',
            'title_sk' => $_POST['title_sk'] ?? '',
            'description_en' => $_POST['description_en'] ?? '',
            'description_sk' => $_POST['description_sk'] ?? '',
            'date' => $_POST['date'] ?? '',
            'time' => $_POST['time'] ?? '',
            'image' => $imageUrl,
            'status' => $_POST['status'] ?? 'planning',
            'created' => $events['current_event']['created'] ?? date('Y-m-d H:i:s'),
            'updated' => date('Y-m-d H:i:s')
        ];
        
        if (saveEvents($events)) {
            $updateSuccess = 'Udalosť bola úspešne aktualizovaná!';
        } else {
            $updateError = 'Chyba pri ukladaní údajov.';
        }
    }
}

// Načítaj aktuálne údaje
$events = loadEvents();
$currentEvent = $events['current_event'] ?? [];

// Zabezpeč že existujú všetky polia
$defaults = [
    'title_en' => '',
    'title_sk' => '',
    'description_en' => '',
    'description_sk' => '',
    'date' => '',
    'time' => '',
    'image' => 'https://i.imgur.com/6jMnbOD.jpeg',
    'status' => 'planning'
];

foreach ($defaults as $key => $value) {
    if (!isset($currentEvent[$key])) {
        $currentEvent[$key] = $value;
    }
}

?>
<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Ighi's Home Radio</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #0a0a0a, #1a1a1a);
        color: #fff;
        min-height: 100vh;
        padding: 20px;
    }

    .container {
        max-width: 900px;
        margin: 0 auto;
    }

    /* Header */
    .header {
        text-align: center;
        margin-bottom: 40px;
        padding: 30px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .header h1 {
        font-size: 2.5rem;
        background: linear-gradient(135deg, #00ff88, #007aff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 10px;
    }

    .header p {
        color: #b3b3b3;
        font-size: 1.1rem;
    }

    /* Login Form */
    .login-form {
        background: rgba(255, 255, 255, 0.05);
        padding: 40px;
        border-radius: 16px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        max-width: 400px;
        margin: 0 auto;
    }

    .login-form h2 {
        text-align: center;
        margin-bottom: 30px;
        color: #00ff88;
    }

    /* Admin Form */
    .admin-content {
        background: rgba(255, 255, 255, 0.05);
        padding: 30px;
        border-radius: 16px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #00ff88;
        font-size: 1rem;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        background: rgba(0, 0, 0, 0.3);
        color: #fff;
        font-size: 1rem;
        font-family: inherit;
        transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #00ff88;
        box-shadow: 0 0 10px rgba(0, 255, 136, 0.3);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    /* Language Tabs */
    .language-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }

    .language-tab {
        background: rgba(255, 255, 255, 0.1);
        color: #b3b3b3;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .language-tab.active {
        background: linear-gradient(135deg, #007aff, #00ff88);
        color: #000;
        font-weight: 600;
    }

    .bilingual-field {
        display: none;
    }

    .bilingual-field.active {
        display: block;
    }

    /* Buttons */
    .btn {
        background: linear-gradient(135deg, #00ff88, #00b4d8);
        color: #000;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
        text-decoration: none;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 255, 136, 0.4);
    }

    .btn-logout {
        background: linear-gradient(135deg, #ff2d55, #ff6b35);
        float: right;
        margin-bottom: 20px;
        color: white;
    }

    /* Alerts */
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 600;
    }

    .alert-success {
        background: rgba(0, 255, 136, 0.2);
        border: 1px solid #00ff88;
        color: #00ff88;
    }

    .alert-error {
        background: rgba(255, 45, 85, 0.2);
        border: 1px solid #ff2d55;
        color: #ff2d55;
    }

    /* Current Image */
    .current-image {
        max-width: 300px;
        max-height: 200px;
        border-radius: 8px;
        border: 2px solid rgba(255, 255, 255, 0.2);
        margin-top: 10px;
    }

    /* Grid layout */
    .datetime-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-planning {
        background: #ffc107;
        color: #000;
    }

    .status-announced {
        background: #17a2b8;
        color: #fff;
    }

    .status-live {
        background: #dc3545;
        color: #fff;
    }

    .status-completed {
        background: #28a745;
        color: #fff;
    }

    /* Footer */
    .footer {
        text-align: center;
        margin-top: 40px;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .footer a {
        color: #00ff88;
        text-decoration: none;
        font-weight: 600;
        margin: 0 10px;
    }

    .footer a:hover {
        text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .container {
            padding: 10px;
        }

        .datetime-grid {
            grid-template-columns: 1fr;
        }

        .btn-logout {
            float: none;
            display: block;
            margin-bottom: 20px;
            text-align: center;
        }
    }
    
    /* Security Notice */
    .security-notice {
        background: rgba(255, 140, 0, 0.1);
        border: 1px solid #ff8c00;
        border-radius: 8px;
        padding: 10px 15px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        color: #ff8c00;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🎵 Admin Panel</h1>
            <p>Správa udalostí pre Ighi's Home Radio</p>
            <?php if (defined('CONFIG_FULLY_LOADED') && CONFIG_FULLY_LOADED): ?>
                <p style="color: #00ff88; font-size: 0.8rem; margin-top: 10px;">✅ Secured via .env configuration</p>
            <?php endif; ?>
        </div>

        <?php if (!$isLoggedIn): ?>
        <!-- LOGIN FORM -->
        <div class="login-form">
            <h2>🔐 Prihlásenie</h2>

            <?php if ($loginError): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="password">Heslo:</label>
                    <input type="password" id="password" name="password" required placeholder="Zadajte admin heslo"
                        autofocus>
                </div>
                <button type="submit" name="login" class="btn" style="width: 100%;">
                    Prihlásiť sa
                </button>
            </form>
            
            <div class="security-notice" style="margin-top: 20px;">
                <strong>🔒 Bezpečnosť:</strong> Heslo je načítané z .env súboru. Nikdy nie je uložené v kóde.
            </div>
        </div>

        <?php else: ?>
        <!-- ADMIN INTERFACE -->
        <a href="?logout" class="btn btn-logout">🚪 Odhlásiť sa</a>
        <div style="clear: both;"></div>

        <?php if ($updateSuccess): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($updateSuccess); ?></div>
        <?php endif; ?>

        <?php if ($updateError): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($updateError); ?></div>
        <?php endif; ?>

        <div class="admin-content">
            <h2 style="margin-bottom: 30px; color: #fff;">📅 Správa udalostí</h2>

            <div style="margin-bottom: 20px;">
                <strong>Aktuálny status:</strong>
                <span class="status-badge status-<?php echo $currentEvent['status']; ?>">
                    <?php echo $currentEvent['status']; ?>
                </span>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <!-- Názov -->
                <div class="form-group">
                    <label>🏷️ Názov udalosti:</label>
                    <div class="language-tabs">
                        <button type="button" class="language-tab active" onclick="showLanguage('title', 'en')">🇬🇧
                            English</button>
                        <button type="button" class="language-tab" onclick="showLanguage('title', 'sk')">🇸🇰
                            Slovenčina</button>
                    </div>
                    <div id="title-en" class="bilingual-field active">
                        <input type="text" name="title_en"
                            value="<?php echo htmlspecialchars($currentEvent['title_en']); ?>" required
                            placeholder="Event title in English">
                    </div>
                    <div id="title-sk" class="bilingual-field">
                        <input type="text" name="title_sk"
                            value="<?php echo htmlspecialchars($currentEvent['title_sk']); ?>" required
                            placeholder="Názov udalosti v slovenčine">
                    </div>
                </div>

                <!-- Popis -->
                <div class="form-group">
                    <label>📝 Popis udalosti:</label>
                    <div class="language-tabs">
                        <button type="button" class="language-tab active"
                            onclick="showLanguage('description', 'en')">🇬🇧 English</button>
                        <button type="button" class="language-tab" onclick="showLanguage('description', 'sk')">🇸🇰
                            Slovenčina</button>
                    </div>
                    <div id="description-en" class="bilingual-field active">
                        <textarea name="description_en" required
                            placeholder="Event description in English"><?php echo htmlspecialchars($currentEvent['description_en']); ?></textarea>
                    </div>
                    <div id="description-sk" class="bilingual-field">
                        <textarea name="description_sk" required
                            placeholder="Popis udalosti v slovenčine"><?php echo htmlspecialchars($currentEvent['description_sk']); ?></textarea>
                    </div>
                </div>

                <!-- Dátum a čas -->
                <div class="datetime-grid">
                    <div class="form-group">
                        <label for="date">📅 Dátum:</label>
                        <input type="date" id="date" name="date"
                            value="<?php echo htmlspecialchars($currentEvent['date']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="time">🕒 Čas:</label>
                        <input type="time" id="time" name="time"
                            value="<?php echo htmlspecialchars($currentEvent['time']); ?>">
                    </div>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label for="status">📊 Status udalosti:</label>
                    <select id="status" name="status" required>
                        <option value="planning"
                            <?php echo $currentEvent['status'] === 'planning' ? 'selected' : ''; ?>>
                            🎯 Plánovanie
                        </option>
                        <option value="announced"
                            <?php echo $currentEvent['status'] === 'announced' ? 'selected' : ''; ?>>
                            📢 Ohlásená
                        </option>
                        <option value="live" <?php echo $currentEvent['status'] === 'live' ? 'selected' : ''; ?>>
                            🔴 Naživo
                        </option>
                        <option value="completed"
                            <?php echo $currentEvent['status'] === 'completed' ? 'selected' : ''; ?>>
                            ✅ Ukončená
                        </option>
                    </select>
                </div>

                <!-- Obrázok -->
                <div class="form-group">
                    <label for="event_image">🖼️ Obrázok udalosti:</label>
                    <input type="file" id="event_image" name="event_image" accept="image/*">
                    <small style="color: #b3b3b3; display: block; margin-top: 5px;">
                        Max. 5MB, formáty: JPG, PNG, GIF, WebP
                    </small>
                    <?php if ($currentEvent['image']): ?>
                    <img src="<?php echo htmlspecialchars($currentEvent['image']); ?>" alt="Current Event Image"
                        class="current-image">
                    <?php endif; ?>
                </div>

                <button type="submit" name="update_event" class="btn">
                    💾 Uložiť zmeny
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="footer">
            <a href="index.html">← Späť na rádio</a>
            <a href="monitor.php">📊 Monitor</a>
            <a href="event_api.php" target="_blank">📡 Event API</a>
        </div>
    </div>

    <script>
    function showLanguage(field, lang) {
        // Hide all fields for this field type
        document.querySelectorAll(`#${field}-en, #${field}-sk`).forEach(el => {
            el.classList.remove('active');
            // Odstráň required z skrytých polí
            const input = el.querySelector('input, textarea');
            if (input) {
                input.removeAttribute('required');
            }
        });

        // Show selected field
        const activeField = document.getElementById(`${field}-${lang}`);
        activeField.classList.add('active');

        // Pridaj required k aktívnemu poľu
        const activeInput = activeField.querySelector('input, textarea');
        if (activeInput) {
            activeInput.setAttribute('required', 'required');
        }

        // Update tabs
        const tabs = event.target.parentElement.querySelectorAll('.language-tab');
        tabs.forEach(tab => tab.classList.remove('active'));
        event.target.classList.add('active');
    }

    // Inicializácia pri načítaní stránky
    document.addEventListener('DOMContentLoaded', function() {
        // Nastav správne required atribúty na začiatku
        ['title', 'description'].forEach(field => {
            // English - active
            const enField = document.getElementById(`${field}-en`);
            const enInput = enField?.querySelector('input, textarea');
            if (enInput) {
                enInput.setAttribute('required', 'required');
            }

            // Slovak - inactive, remove required
            const skField = document.getElementById(`${field}-sk`);
            const skInput = skField?.querySelector('input, textarea');
            if (skInput) {
                skInput.removeAttribute('required');
            }
        });
    });

    // Validácia pred odoslaním formulára
    document.querySelector('form').addEventListener('submit', function(e) {
        // Skontroluj že všetky aktívne required polia sú vyplnené
        const activeRequiredFields = document.querySelectorAll('.bilingual-field.active input[required], .bilingual-field.active textarea[required]');
        let hasErrors = false;

        activeRequiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#ff2d55';
                hasErrors = true;
            } else {
                field.style.borderColor = 'rgba(255,255,255,0.2)';
            }
        });

        if (hasErrors) {
            e.preventDefault();
            alert('Prosím vyplňte všetky povinné polia v aktívnom jazyku.');
        }
    });
    </script>
</body>

</html>