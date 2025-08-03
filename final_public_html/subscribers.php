<?php
/**
 * SUBSCRIBER MANAGEMENT SYSTEM - Ighi's Home Radio
 * Správa email a WhatsApp subscriberov
 */

session_start();

// Load configuration
require_once 'config.php';

// Check authentication
function checkAuth() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

// Require authentication
if (!checkAuth()) {
    header('Location: login.php');
    exit;
}

// Handle WhatsApp message sending
if (isset($_POST['action']) && $_POST['action'] === 'send_whatsapp') {
    if (!checkAuth()) {
        header('Location: login.php');
        exit;
    }
    
    $message = $_POST['message'] ?? '';
    $testMode = isset($_POST['test_mode']);
    $addUnsubscribe = isset($_POST['add_unsubscribe']);
    
    if (!empty($message) && !empty($WHATSAPP_CONFIG['twilio_sid'])) {
        $result = sendBulkWhatsApp($message, $testMode, $addUnsubscribe);
        $_SESSION['whatsapp_result'] = $result;
    }
    
    header('Location: subscribers.php#whatsappMessaging');
    exit;
}

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

// Load subscribers
function loadSubscribers() {
    global $SUBSCRIBERS_FILE;
    if (!file_exists($SUBSCRIBERS_FILE)) {
        return ['email' => [], 'whatsapp' => []];
    }
    return json_decode(file_get_contents($SUBSCRIBERS_FILE), true);
}

// Save subscribers
function saveSubscribers($subscribers) {
    global $SUBSCRIBERS_FILE;
    
    // Ensure directory exists
    $dir = dirname($SUBSCRIBERS_FILE);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Save with proper permissions
    $result = file_put_contents($SUBSCRIBERS_FILE, json_encode($subscribers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Set permissions
    if ($result !== false) {
        chmod($SUBSCRIBERS_FILE, 0644);
    }
    
    return $result !== false;
}

// Log notification
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

// Function to send bulk WhatsApp messages
function sendBulkWhatsApp($message, $testMode = false, $addUnsubscribe = true) {
    global $WHATSAPP_CONFIG;
    
    $subscribers = loadSubscribers();
    $whatsappNumbers = $subscribers['whatsapp'] ?? [];
    
    if (empty($whatsappNumbers)) {
        return ['type' => 'error', 'message' => 'Žiadni WhatsApp subscriberi'];
    }
    
    // Add unsubscribe info if enabled
    if ($addUnsubscribe) {
        $message .= "\n\n---\n📵 Pre odhlásenie odpovedzte STOP";
    }
    
    $sent = 0;
    $failed = 0;
    $errors = [];
    
    // If test mode, send only to first number
    if ($testMode) {
        $whatsappNumbers = array_slice($whatsappNumbers, 0, 1);
    }
    
    foreach ($whatsappNumbers as $subscriber) {
        if (!$subscriber['active']) continue;
        
        try {
            // Send via Twilio
            $twilioNumber = $WHATSAPP_CONFIG['twilio_whatsapp'];
            $toNumber = 'whatsapp:' . $subscriber['phone'];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 
                "https://api.twilio.com/2010-04-01/Accounts/{$WHATSAPP_CONFIG['twilio_sid']}/Messages.json"
            );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'From' => $twilioNumber,
                'To' => $toNumber,
                'Body' => $message
            ]));
            curl_setopt($ch, CURLOPT_USERPWD, 
                $WHATSAPP_CONFIG['twilio_sid'] . ':' . $WHATSAPP_CONFIG['twilio_token']
            );
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 201) {
                $sent++;
                logNotification('whatsapp_bulk', $subscriber['phone'], 'Bulk message', 'sent');
            } else {
                $failed++;
                $errors[] = $subscriber['phone'];
                logNotification('whatsapp_bulk', $subscriber['phone'], 'Bulk message', 'failed');
            }
            
            // Rate limiting - Twilio recommends max 1 message per second
            if (!$testMode) {
                sleep(1);
            }
            
        } catch (Exception $e) {
            $failed++;
            $errors[] = $subscriber['phone'];
        }
    }
    
    $message = $testMode ? 
        "Test správa odoslaná na $sent číslo" : 
        "Správa odoslaná: $sent úspešne, $failed neúspešne";
    
    if (!empty($errors)) {
        $message .= " (Chyby: " . implode(', ', array_slice($errors, 0, 3)) . "...)";
    }
    
    return [
        'type' => $sent > 0 ? 'success' : 'error',
        'message' => $message
    ];
}

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'delete') {
    $type = $_GET['type'] ?? '';
    $id = $_GET['id'] ?? '';
    
    $subscribers = loadSubscribers();
    
    if ($type === 'email' && isset($subscribers['email'][$id])) {
        unset($subscribers['email'][$id]);
        $subscribers['email'] = array_values($subscribers['email']); // Reindex
        saveSubscribers($subscribers);
        $message = 'Email subscriber odstránený';
    } elseif ($type === 'whatsapp' && isset($subscribers['whatsapp'][$id])) {
        unset($subscribers['whatsapp'][$id]);
        $subscribers['whatsapp'] = array_values($subscribers['whatsapp']); // Reindex
        saveSubscribers($subscribers);
        $message = 'WhatsApp subscriber odstránený';
    }
}

if ($action === 'export') {
    $subscribers = loadSubscribers();
    $emails = array_column($subscribers['email'], 'email');
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="email_subscribers_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Email', 'Language', 'Subscribed Date']);
    
    foreach ($subscribers['email'] as $sub) {
        fputcsv($output, [
            $sub['email'],
            $sub['language'],
            $sub['subscribed_at']
        ]);
    }
    
    fclose($output);
    exit;
}

if ($action === 'export_phones') {
    $subscribers = loadSubscribers();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="whatsapp_subscribers_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Phone', 'Language', 'Subscribed Date']);
    
    foreach ($subscribers['whatsapp'] as $sub) {
        fputcsv($output, [
            $sub['phone'],
            $sub['language'],
            $sub['subscribed_at']
        ]);
    }
    
    fclose($output);
    exit;
}

// Load current data
$subscribers = loadSubscribers();
$emailCount = count($subscribers['email'] ?? []);
$whatsappCount = count($subscribers['whatsapp'] ?? []);
$totalCount = $emailCount + $whatsappCount;

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa Subscriberov - Ighi's Home Radio</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0a0a, #1a1a1a);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .header h1 {
            font-size: 2.5rem;
            color: #00ff88;
            margin-bottom: 10px;
        }
        
        .config-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .config-ok { background: #16a34a; }
        .config-missing { background: #dc2626; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 30px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #00ff88;
        }
        
        .stat-label {
            color: #b3b3b3;
            margin-top: 10px;
        }
        
        .subscriber-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }
        
        .section-header {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .section-header:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        
        .section-header.active {
            background: rgba(0, 255, 136, 0.1);
            border-bottom: 2px solid #00ff88;
        }
        
        .section-header h2 {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-content {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        
        .section-content.active {
            padding: 20px;
            max-height: 2000px;
        }
        
        .subscriber-list {
            max-height: 400px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 15px;
        }
        
        .subscriber-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .subscriber-item:last-child {
            border-bottom: none;
        }
        
        .btn {
            background: linear-gradient(135deg, #00ff88, #00b4d8);
            color: #000;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 136, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff2d55, #ff6b35);
            color: white;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.9rem;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
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
        
        .alert-warning {
            background: rgba(255, 140, 0, 0.2);
            border: 1px solid #ff8c00;
            color: #ff8c00;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .actions {
            margin-top: 20px;
            text-align: center;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer a {
            color: #00ff88;
            text-decoration: none;
            margin: 0 10px;
        }
        
        /* WhatsApp Messaging Styles */
        .message-form {
            margin-bottom: 20px;
        }
        
        .message-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #00ff88;
        }
        
        .message-form textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
            font-size: 1rem;
            resize: vertical;
        }
        
        .char-counter {
            text-align: right;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        
        .message-options {
            margin: 20px 0;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            cursor: pointer;
        }
        
        .checkbox-label input {
            margin-right: 8px;
        }
        
        .message-preview {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .preview-box {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 6px;
            padding: 12px;
            margin-top: 10px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #00ff88, #00b4d8);
            color: #000;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 136, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Správa Subscriberov</h1>
            <p>Ighi's Home Radio - Admin Panel</p>
            
            <?php if (!empty($EMAIL_CONFIG['smtp_username'])): ?>
                <span class="config-status config-ok">✅ Konfigurácia načítaná z .env</span>
            <?php else: ?>
                <span class="config-status config-missing">❌ Email konfigurácia chýba</span>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalCount; ?></div>
                <div class="stat-label">Celkovo</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $emailCount; ?></div>
                <div class="stat-label">📧 Email Subscriberov</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $whatsappCount; ?></div>
                <div class="stat-label">📱 WhatsApp Subscriberov</div>
            </div>
        </div>
        
        <!-- Email Subscribers -->
        <div class="subscriber-section">
            <div class="section-header" onclick="toggleSection('emailSection')">
                <h2>📧 Email Subscriberi (<?php echo $emailCount; ?>)</h2>
            </div>
            <div id="emailSection" class="section-content">
                <?php if ($emailCount > 0): ?>
                    <div class="subscriber-list">
                        <?php foreach ($subscribers['email'] as $index => $sub): ?>
                            <div class="subscriber-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($sub['email']); ?></strong><br>
                                    <small>
                                        Jazyk: <?php echo $sub['language'] === 'sk' ? '🇸🇰' : '🇬🇧'; ?> |
                                        Od: <?php echo date('d.m.Y', strtotime($sub['subscribed_at'])); ?>
                                    </small>
                                </div>
                                <button onclick="deleteSubscriber('email', <?php echo $index; ?>)" 
                                        class="btn btn-danger btn-small">
                                    🗑️ Zmazať
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Žiadni email subscriberi</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- WhatsApp Subscribers -->
        <div class="subscriber-section">
            <div class="section-header" onclick="toggleSection('whatsappSection')">
                <h2>📱 WhatsApp Subscriberi (<?php echo $whatsappCount; ?>)</h2>
            </div>
            <div id="whatsappSection" class="section-content">
                <?php if ($whatsappCount > 0): ?>
                    <div class="subscriber-list">
                        <?php foreach ($subscribers['whatsapp'] as $index => $sub): ?>
                            <div class="subscriber-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($sub['phone']); ?></strong><br>
                                    <small>
                                        Jazyk: <?php echo $sub['language'] === 'sk' ? '🇸🇰' : '🇬🇧'; ?> |
                                        Od: <?php echo date('d.m.Y', strtotime($sub['subscribed_at'])); ?>
                                    </small>
                                </div>
                                <button onclick="deleteSubscriber('whatsapp', <?php echo $index; ?>)" 
                                        class="btn btn-danger btn-small">
                                    🗑️ Zmazať
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Žiadni WhatsApp subscriberi</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- WhatsApp Messaging Section -->
        <div class="subscriber-section">
            <div class="section-header" onclick="toggleSection('whatsappMessaging')">
                <h2>💬 Poslať WhatsApp správu</h2>
            </div>
            <div id="whatsappMessaging" class="section-content">
                <?php if (!empty($WHATSAPP_CONFIG['twilio_sid'])): ?>
                    <form id="whatsappForm" method="POST" action="">
                        <input type="hidden" name="action" value="send_whatsapp">
                        
                        <div class="message-form">
                            <label for="messageTextarea">Správa:</label>
                            <textarea 
                                id="messageTextarea" 
                                name="message" 
                                rows="6" 
                                maxlength="1000"
                                placeholder="Napíšte správu pre všetkých WhatsApp subscriberov..."></textarea>
                            <div class="char-counter">
                                <span id="charCount">0 / 1000</span>
                            </div>
                        </div>
                        
                        <div class="message-options">
                            <label class="checkbox-label">
                                <input type="checkbox" id="testMode" name="test_mode" value="1">
                                <span>Test mode (pošle len na prvé číslo)</span>
                            </label>
                            
                            <label class="checkbox-label">
                                <input type="checkbox" name="add_unsubscribe" value="1" checked>
                                <span>Pridať unsubscribe link</span>
                            </label>
                        </div>
                        
                        <div class="message-preview">
                            <h4>Náhľad správy:</h4>
                            <div class="preview-box" id="messagePreview">
                                <em>Tu sa zobrazí náhľad vašej správy...</em>
                            </div>
                        </div>
                        
                        <button type="button" id="sendWhatsAppBtn" onclick="sendWhatsAppMessage()" class="btn btn-primary">
                            📱 Poslať WhatsApp správu
                        </button>
                    </form>
                    
                    <?php if (isset($_SESSION['whatsapp_result'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['whatsapp_result']['type']; ?>">
                            <?php echo $_SESSION['whatsapp_result']['message']; ?>
                        </div>
                        <?php unset($_SESSION['whatsapp_result']); ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning">
                        ⚠️ Twilio WhatsApp nie je nakonfigurované. Pridajte Twilio credentials do konfigurácie.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Export Section -->
        <div class="subscriber-section">
            <div class="section-header" onclick="toggleSection('exportSection')">
                <h2>📥 Export emailov</h2>
            </div>
            <div id="exportSection" class="section-content">
                <p>Stiahnite zoznam všetkých email subscriberov vo formáte CSV.</p>
                <div class="actions">
                    <button onclick="exportEmails()" class="btn">
                        📧 Export Emailov (CSV)
                    </button>
                    <button onclick="exportPhones()" class="btn">
                        📱 Export WhatsApp čísel (CSV)
                    </button>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                    <h4>Ako použiť export:</h4>
                    <p style="margin-top: 10px;">
                        Kopírujte a vložte do emailového klienta pre hromadné emaily. 
                        WhatsApp čísla môžete použiť pre broadcast listy.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <a href="notifications.php">🔔 Notifikácie</a> |
            <a href="admin.php">📝 Admin Panel</a> |
            <a href="monitor.php">📊 Monitor</a> |
            <a href="index.html">🏠 Späť na rádio</a>
        </div>
    </div>
    
    <script>
        // Toggle sections with animation
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            const allSections = document.querySelectorAll('.section-content');
            const allHeaders = document.querySelectorAll('.section-header');
            
            // Close all other sections
            allSections.forEach(s => {
                if (s.id !== sectionId) {
                    s.classList.remove('active');
                }
            });
            
            // Remove active class from all headers
            allHeaders.forEach(h => {
                if (!h.onclick.toString().includes(sectionId)) {
                    h.classList.remove('active');
                }
            });
            
            // Toggle current section
            section.classList.toggle('active');
            event.currentTarget.classList.toggle('active');
        }
        
        // Character counter for message textarea
        function updateCharCount() {
            const textarea = document.getElementById('messageTextarea');
            const charCount = document.getElementById('charCount');
            
            if (textarea && charCount) {
                const length = textarea.value.length;
                charCount.textContent = `${length} / 1000`;
                
                if (length > 900) {
                    charCount.style.color = '#ff2d55';
                } else if (length > 700) {
                    charCount.style.color = '#ff8c00';
                } else {
                    charCount.style.color = '#00ff88';
                }
            }
        }
        
        // Live message preview
        function updatePreview() {
            const textarea = document.getElementById('messageTextarea');
            const preview = document.getElementById('messagePreview');
            const addUnsubscribe = document.querySelector('input[name="add_unsubscribe"]');
            
            if (textarea && preview) {
                let text = textarea.value || 'Tu sa zobrazí náhľad vašej správy...';
                
                if (addUnsubscribe && addUnsubscribe.checked && textarea.value) {
                    text += '\n\n---\n📵 Pre odhlásenie odpovedzte STOP';
                }
                
                preview.textContent = text;
            }
        }
        
        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('messageTextarea');
            const addUnsubscribe = document.querySelector('input[name="add_unsubscribe"]');
            
            if (textarea) {
                textarea.addEventListener('input', function() {
                    updateCharCount();
                    updatePreview();
                });
                updateCharCount();
            }
            
            if (addUnsubscribe) {
                addUnsubscribe.addEventListener('change', updatePreview);
            }
            
            updatePreview();
        });
        
        // Export functions
        function exportEmails() {
            window.location.href = '?action=export';
        }
        
        function exportPhones() {
            window.location.href = '?action=export_phones';
        }
        
        // Delete subscriber with confirmation
        function deleteSubscriber(type, id) {
            if (confirm('Naozaj chcete odstrániť tohto subscribera?')) {
                window.location.href = `?action=delete&type=${type}&id=${id}`;
            }
        }
        
        // Send WhatsApp message
        function sendWhatsAppMessage() {
            const message = document.getElementById('messageTextarea').value;
            const testMode = document.getElementById('testMode').checked;
            
            if (!message.trim()) {
                alert('Prosím napíšte správu!');
                return;
            }
            
            if (!testMode && !confirm('Naozaj chcete poslať túto správu všetkým WhatsApp subscriberom?')) {
                return;
            }
            
            // Show loading
            const btn = document.getElementById('sendWhatsAppBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Posielam...';
            btn.disabled = true;
            
            // Send via form
            const form = document.getElementById('whatsappForm');
            form.submit();
        }
    </script>
</body>
</html>