<?php
/**
 * UNIFIED NOTIFICATION SYSTEM - Uses .env configuration
 * Súbor: notifications.php (UPDATED)
 */

// Load unified configuration
require_once 'config.php';

session_start();

// Configuration from .env
$SUBSCRIBERS_FILE = DATA_DIR . 'subscribers.json';
$NOTIFICATIONS_LOG = DATA_DIR . 'notifications_log.json';

// Email configuration from .env
$EMAIL_CONFIG = [
    'smtp_host' => env('SMTP_HOST', 'smtp.gmail.com'),
    'smtp_port' => (int)env('SMTP_PORT', 587),
    'smtp_username' => env('SMTP_USERNAME', ''),
    'smtp_password' => env('SMTP_PASSWORD', ''),
    'from_email' => env('SMTP_FROM_EMAIL', 'hello@ighishomeradio.com'),
    'from_name' => env('SMTP_FROM_NAME', "Ighi's Home Radio")
];

// WhatsApp configuration from .env
$WHATSAPP_CONFIG = [
    'method' => env('WHATSAPP_METHOD', 'callmebot'),
    'api_key' => env('WHATSAPP_API_KEY', ''),
    'twilio_sid' => env('TWILIO_SID', ''),
    'twilio_token' => env('TWILIO_TOKEN', ''),
    'twilio_whatsapp' => env('TWILIO_WHATSAPP', 'whatsapp:+14155238886')
];

// =========================================
// UNIFIED AUTH CHECK
// =========================================

function checkAuth() {
    // Use same auth as other systems
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 14400) {
        return false;
    }
    
    return true;
}

function requireAuth() {
    if (!checkAuth()) {
        header('Location: login.php');
        exit();
    }
}

// =========================================
// HELPER FUNCTIONS
// =========================================

function loadSubscribers() {
    global $SUBSCRIBERS_FILE;
    if (!file_exists($SUBSCRIBERS_FILE)) {
        return ['email' => [], 'whatsapp' => []];
    }
    return json_decode(file_get_contents($SUBSCRIBERS_FILE), true);
}

function saveSubscribers($subscribers) {
    global $SUBSCRIBERS_FILE;
    return file_put_contents($SUBSCRIBERS_FILE, json_encode($subscribers, JSON_PRETTY_PRINT));
}

function loadEvents() {
    if (file_exists(DATA_DIR . 'events.json')) {
        return json_decode(file_get_contents(DATA_DIR . 'events.json'), true);
    }
    return null;
}

function logNotification($type, $recipient, $subject, $status) {
    global $NOTIFICATIONS_LOG;
    $log = [];
    if (file_exists($NOTIFICATIONS_LOG)) {
        $log = json_decode(file_get_contents($NOTIFICATIONS_LOG), true);
    }
    
    $log[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'recipient' => $recipient,
        'subject' => $subject,
        'status' => $status
    ];
    
    // Keep only last 1000 records
    if (count($log) > 1000) {
        $log = array_slice($log, -1000);
    }
    
    file_put_contents($NOTIFICATIONS_LOG, json_encode($log, JSON_PRETTY_PRINT));
}

// =========================================
// EMAIL FUNCTIONS
// =========================================

function sendEmail($to, $subject, $htmlBody, $textBody) {
    global $EMAIL_CONFIG;
    
    // Check if email is configured
    if (empty($EMAIL_CONFIG['smtp_username']) || empty($EMAIL_CONFIG['smtp_password'])) {
        logNotification('email', $to, $subject, 'failed - not configured');
        return false;
    }
    
    // Simple mail() function (for production use PHPMailer)
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $EMAIL_CONFIG['from_name'] . ' <' . $EMAIL_CONFIG['from_email'] . '>',
        'Reply-To: ' . $EMAIL_CONFIG['from_email'],
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $success = mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    
    logNotification('email', $to, $subject, $success ? 'sent' : 'failed');
    return $success;
}

function getEmailTemplate($type, $event, $lang) {
    $siteUrl = env('SITE_URL', 'https://www.ighishomeradio.com');
    
    $templates = [
        'announced' => [
            'en' => [
                'subject' => '📅 New Show Announced: ' . ($event['title_en'] ?? ''),
                'html' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #0a0a0a; color: #fff; padding: 20px; border-radius: 10px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <img src="https://i.imgur.com/Ym67jVJ.jpeg" alt="Ighi\'s Home Radio" style="width: 80px; border-radius: 50%;">
                        <h1 style="color: #00ff88;">📅 New Show Announced!</h1>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; margin: 20px 0;">
                        <h2 style="color: #00ff88;">' . ($event['title_en'] ?? '') . '</h2>
                        <p style="line-height: 1.6;">' . ($event['description_en'] ?? '') . '</p>
                        
                        ' . (($event['date'] && $event['time']) ? '
                        <div style="background: #ff2d55; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: center;">
                            <strong>📅 ' . date('d.m.Y', strtotime($event['date'])) . ' at ' . date('H:i', strtotime($event['time'])) . '</strong>
                        </div>
                        ' : '') . '
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $siteUrl . '" style="background: linear-gradient(135deg, #00ff88, #007aff); color: #000; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold;">
                            🎵 Listen Now
                        </a>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #666;">
                        <p>You\'re receiving this because you subscribed to Ighi\'s Home Radio notifications.</p>
                        <a href="' . $siteUrl . '/unsubscribe.php?email=' . urlencode('{{EMAIL}}') . '" style="color: #666;">Unsubscribe</a>
                    </div>
                </div>
                '
            ],
            'sk' => [
                'subject' => '📅 Nová show: ' . ($event['title_sk'] ?? ''),
                'html' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #0a0a0a; color: #fff; padding: 20px; border-radius: 10px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <img src="https://i.imgur.com/Ym67jVJ.jpeg" alt="Ighi\'s Home Radio" style="width: 80px; border-radius: 50%;">
                        <h1 style="color: #00ff88;">📅 Nová show!</h1>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; margin: 20px 0;">
                        <h2 style="color: #00ff88;">' . ($event['title_sk'] ?? '') . '</h2>
                        <p style="line-height: 1.6;">' . ($event['description_sk'] ?? '') . '</p>
                        
                        ' . (($event['date'] && $event['time']) ? '
                        <div style="background: #ff2d55; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: center;">
                            <strong>📅 ' . date('d.m.Y', strtotime($event['date'])) . ' o ' . date('H:i', strtotime($event['time'])) . '</strong>
                        </div>
                        ' : '') . '
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $siteUrl . '" style="background: linear-gradient(135deg, #00ff88, #007aff); color: #000; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold;">
                            🎵 Počúvať teraz
                        </a>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #666;">
                        <p>Tento email dostávate, pretože ste sa prihlásili k odberu notifikácií.</p>
                        <a href="' . $siteUrl . '/unsubscribe.php?email=' . urlencode('{{EMAIL}}') . '" style="color: #666;">Odhlásiť odber</a>
                    </div>
                </div>
                '
            ]
        ],
        'live' => [
            'en' => [
                'subject' => '🔴 LIVE NOW: ' . ($event['title_en'] ?? ''),
                'html' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #0a0a0a; color: #fff; padding: 20px; border-radius: 10px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <img src="https://i.imgur.com/Ym67jVJ.jpeg" alt="Ighi\'s Home Radio" style="width: 80px; border-radius: 50%;">
                        <h1 style="color: #ff2d55;">🔴 WE\'RE LIVE!</h1>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, rgba(255,45,85,0.2), rgba(255,140,0,0.2)); padding: 20px; border-radius: 10px; margin: 20px 0; border: 2px solid #ff2d55;">
                        <h2 style="color: #ff2d55; text-align: center;">' . ($event['title_en'] ?? '') . '</h2>
                        <p style="text-align: center; font-size: 18px; font-weight: bold;">IS LIVE RIGHT NOW!</p>
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $siteUrl . '" style="background: linear-gradient(135deg, #ff2d55, #ff6b35); color: #fff; padding: 20px 40px; text-decoration: none; border-radius: 25px; font-weight: bold; font-size: 18px;">
                            🎵 JOIN US LIVE!
                        </a>
                    </div>
                </div>
                '
            ],
            'sk' => [
                'subject' => '🔴 NAŽIVO: ' . ($event['title_sk'] ?? ''),
                'html' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #0a0a0a; color: #fff; padding: 20px; border-radius: 10px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <img src="https://i.imgur.com/Ym67jVJ.jpeg" alt="Ighi\'s Home Radio" style="width: 80px; border-radius: 50%;">
                        <h1 style="color: #ff2d55;">🔴 VYSIELAME NAŽIVO!</h1>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, rgba(255,45,85,0.2), rgba(255,140,0,0.2)); padding: 20px; border-radius: 10px; margin: 20px 0; border: 2px solid #ff2d55;">
                        <h2 style="color: #ff2d55; text-align: center;">' . ($event['title_sk'] ?? '') . '</h2>
                        <p style="text-align: center; font-size: 18px; font-weight: bold;">PRÁVE TERAZ NAŽIVO!</p>
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $siteUrl . '" style="background: linear-gradient(135deg, #ff2d55, #ff6b35); color: #fff; padding: 20px 40px; text-decoration: none; border-radius: 25px; font-weight: bold; font-size: 18px;">
                            🎵 PRIPOJTE SA!
                        </a>
                    </div>
                </div>
                '
            ]
        ]
    ];
    
    return $templates[$type][$lang] ?? null;
}

// =========================================
// WHATSAPP FUNCTIONS
// =========================================

function sendWhatsApp($phone, $message) {
    global $WHATSAPP_CONFIG;
    
    if ($WHATSAPP_CONFIG['method'] === 'callmebot') {
        if (empty($WHATSAPP_CONFIG['api_key'])) {
            logNotification('whatsapp', $phone, substr($message, 0, 50), 'failed - not configured');
            return false;
        }
        
        $url = "https://api.callmebot.com/whatsapp.php?" . http_build_query([
            'phone' => $phone,
            'text' => $message,
            'apikey' => $WHATSAPP_CONFIG['api_key']
        ]);
        
        $response = @file_get_contents($url);
        $success = $response !== false;
        
    } else {
        // Twilio implementation would go here
        $success = false;
    }
    
    logNotification('whatsapp', $phone, substr($message, 0, 50), $success ? 'sent' : 'failed');
    return $success;
}

function getWhatsAppMessage($type, $event, $lang) {
    $siteUrl = env('SITE_URL', 'https://www.ighishomeradio.com');
    
    $messages = [
        'announced' => [
            'en' => "🎵 *Ighi's Home Radio*\n\n📅 *NEW SHOW ANNOUNCED!*\n\n🎤 " . ($event['title_en'] ?? '') . "\n\n" . 
                    (($event['date'] && $event['time']) ? "📅 " . date('d.m.Y', strtotime($event['date'])) . " at " . date('H:i', strtotime($event['time'])) . "\n\n" : "") .
                    "🎧 Listen now: " . $siteUrl,
            'sk' => "🎵 *Ighi's Home Radio*\n\n📅 *NOVÁ SHOW!*\n\n🎤 " . ($event['title_sk'] ?? '') . "\n\n" . 
                    (($event['date'] && $event['time']) ? "📅 " . date('d.m.Y', strtotime($event['date'])) . " o " . date('H:i', strtotime($event['time'])) . "\n\n" : "") .
                    "🎧 Počúvať: " . $siteUrl
        ],
        'live' => [
            'en' => "🔴 *LIVE NOW!*\n\n🎵 *" . ($event['title_en'] ?? '') . "*\n\nWE'RE BROADCASTING RIGHT NOW!\n\n🎧 Join us: " . $siteUrl,
            'sk' => "🔴 *NAŽIVO!*\n\n🎵 *" . ($event['title_sk'] ?? '') . "*\n\nVYSIELAME PRÁVE TERAZ!\n\n🎧 Pripojte sa: " . $siteUrl
        ]
    ];
    
    return $messages[$type][$lang] ?? null;
}

// =========================================
// SUBSCRIPTION HANDLING
// =========================================

if (isset($_POST['subscribe'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
    $language = in_array($_POST['language'], ['en', 'sk']) ? $_POST['language'] : 'en';
    
    $subscribers = loadSubscribers();
    $message = '';
    
    if ($email) {
        if (!in_array($email, array_column($subscribers['email'], 'email'))) {
            $subscribers['email'][] = [
                'email' => $email,
                'language' => $language,
                'subscribed_at' => date('Y-m-d H:i:s'),
                'active' => true
            ];
            $message .= "✅ Email subscription added! ";
        } else {
            $message .= "⚠️ Email already subscribed. ";
        }
    }
    
    if ($phone && strlen($phone) >= 10) {
        if (!in_array($phone, array_column($subscribers['whatsapp'], 'phone'))) {
            $subscribers['whatsapp'][] = [
                'phone' => $phone,
                'language' => $language,
                'subscribed_at' => date('Y-m-d H:i:s'),
                'active' => true
            ];
            $message .= "✅ WhatsApp subscription added!";
        } else {
            $message .= "⚠️ WhatsApp already subscribed.";
        }
    }
    
    saveSubscribers($subscribers);
    $subscriptionMessage = $message;
}

// =========================================
// NOTIFICATION SENDING (ADMIN ONLY)
// =========================================

if (isset($_POST['send_notifications'])) {
    requireAuth(); // Use unified auth
    
    $type = $_POST['notification_type']; // announced, live, completed
    $events = loadEvents();
    $event = $events['current_event'] ?? null;
    
    if ($event) {
        $subscribers = loadSubscribers();
        $results = ['email' => 0, 'whatsapp' => 0];
        
        // Send emails
        foreach ($subscribers['email'] as $subscriber) {
            if (!$subscriber['active']) continue;
            
            $template = getEmailTemplate($type, $event, $subscriber['language']);
            if ($template) {
                $htmlBody = str_replace('{{EMAIL}}', $subscriber['email'], $template['html']);
                if (sendEmail($subscriber['email'], $template['subject'], $htmlBody, '')) {
                    $results['email']++;
                }
            }
        }
        
        // Send WhatsApp
        foreach ($subscribers['whatsapp'] as $subscriber) {
            if (!$subscriber['active']) continue;
            
            $message = getWhatsAppMessage($type, $event, $subscriber['language']);
            if ($message) {
                if (sendWhatsApp($subscriber['phone'], $message)) {
                    $results['whatsapp']++;
                }
            }
        }
        
        $notificationResults = "📧 {$results['email']} emails sent, 📱 {$results['whatsapp']} WhatsApp messages sent!";
    }
}

$subscribers = loadSubscribers();
$events = loadEvents();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔔 Notifications - Ighi's Home Radio</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: linear-gradient(135deg, #0a0a0a, #1a1a1a); 
            color: #fff; 
            min-height: 100vh; padding: 20px; 
        }
        .container { max-width: 800px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 40px; padding: 30px 0; border-bottom: 2px solid #00ff88; }
        .header h1 { font-size: 2.5rem; color: #00ff88; margin-bottom: 10px; }
        
        .subscription-form, .admin-panel { 
            background: rgba(255,255,255,0.05); 
            padding: 30px; 
            border-radius: 15px; 
            margin-bottom: 30px; 
            border: 1px solid rgba(255,255,255,0.1); 
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #00ff88; }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 12px; 
            border: 2px solid rgba(255,255,255,0.2); 
            border-radius: 8px; 
            background: rgba(0,0,0,0.3); 
            color: #fff; 
        }
        
        .btn { 
            background: linear-gradient(135deg, #00ff88, #007aff); 
            color: #000; 
            padding: 12px 30px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: transform 0.2s; 
        }
        .btn:hover { transform: translateY(-2px); }
        
        .alert { padding: 15px; border-radius: 8px; margin: 20px 0; }
        .alert-success { background: rgba(0,255,136,0.2); border: 1px solid #00ff88; color: #00ff88; }
        .alert-info { background: rgba(0,122,255,0.2); border: 1px solid #007aff; color: #007aff; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
        .stat-card { background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #00ff88; }
        
        .notification-buttons { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .notification-btn { 
            padding: 15px; 
            border: none; 
            border-radius: 10px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s; 
        }
        .btn-announced { background: #17a2b8; color: #fff; }
        .btn-live { background: #dc3545; color: #fff; }
        .btn-completed { background: #28a745; color: #fff; }
        
        .config-info { 
            background: rgba(255,140,0,0.1); 
            border: 1px solid #ff8c00; 
            border-radius: 8px; 
            padding: 15px; 
            margin: 20px 0; 
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .notification-buttons, .stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Notification System</h1>
            <p>Email & WhatsApp notifications for Ighi's Home Radio</p>
            
            <!-- Configuration status -->
            <div class="config-info">
                <strong>📧 Email:</strong> <?php echo !empty($EMAIL_CONFIG['smtp_username']) ? '✅ Configured' : '❌ Not configured'; ?>
                <br>
                <strong>📱 WhatsApp:</strong> <?php echo !empty($WHATSAPP_CONFIG['api_key']) ? '✅ Configured' : '❌ Not configured'; ?>
                <br>
                <strong>🔧 Auth:</strong> Unified with admin system
            </div>
        </div>

        <!-- SUBSCRIPTION FORM -->
        <div class="subscription-form">
            <h2>📧 Subscribe to Notifications</h2>
            <p style="margin-bottom: 20px; color: #b3b3b3;">Get notified about new shows, live broadcasts, and updates!</p>
            
            <?php if (isset($subscriptionMessage)): ?>
                <div class="alert alert-success"><?php echo $subscriptionMessage; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">📧 Email Address:</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com">
                </div>
                
                <div class="form-group">
                    <label for="phone">📱 WhatsApp Number (with country code):</label>
                    <input type="text" id="phone" name="phone" placeholder="+421900123456">
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Include country code (e.g., +421 for Slovakia, +420 for Czech Republic)
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="language">🌐 Preferred Language:</label>
                    <select id="language" name="language">
                        <option value="en">🇬🇧 English</option>
                        <option value="sk">🇸🇰 Slovenčina</option>
                    </select>
                </div>
                
                <button type="submit" name="subscribe" class="btn">🔔 Subscribe to Notifications</button>
            </form>
        </div>

        <!-- ADMIN PANEL -->
        <?php if (!checkAuth()): ?>
            <div class="subscription-form">
                <h2>🔐 Admin Access</h2>
                <p>Please <a href="login.php" style="color: #00ff88;">login</a> to access admin features.</p>
            </div>
        <?php else: ?>
            <div class="admin-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <h2>📊 Admin Dashboard</h2>
                    <a href="logout.php" class="btn" style="background: #ff2d55;">Logout</a>
                </div>
                
                <!-- STATISTICS -->
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($subscribers['email']); ?></div>
                        <div>📧 Email Subscribers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($subscribers['whatsapp']); ?></div>
                        <div>📱 WhatsApp Subscribers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($subscribers['email']) + count($subscribers['whatsapp']); ?></div>
                        <div>🎯 Total Subscribers</div>
                    </div>
                </div>
                
                <!-- SEND NOTIFICATIONS -->
                <h3>📨 Send Notifications</h3>
                <?php if (isset($notificationResults)): ?>
                    <div class="alert alert-success"><?php echo $notificationResults; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="notification-buttons">
                        <button type="submit" name="send_notifications" value="announced" 
                                onclick="this.form.notification_type.value='announced'" 
                                class="notification-btn btn-announced">
                            📅 Send "Show Announced"
                        </button>
                        <button type="submit" name="send_notifications" value="live" 
                                onclick="this.form.notification_type.value='live'" 
                                class="notification-btn btn-live">
                            🔴 Send "Live Now"
                        </button>
                        <button type="submit" name="send_notifications" value="completed" 
                                onclick="this.form.notification_type.value='completed'" 
                                class="notification-btn btn-completed">
                            ✅ Send "Show Completed"
                        </button>
                    </div>
                    <input type="hidden" name="notification_type" value="">
                </form>
                
                <div style="margin-top: 30px;">
                    <h3>🔗 Quick Links</h3>
                    <p>
                        <a href="admin.php" style="color: #00ff88;">📝 Manage Events</a> | 
                        <a href="index.html" style="color: #00ff88;">🏠 Back to Radio</a> |
                        <a href="monitor.php" style="color: #00ff88;">📊 Monitor</a>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>