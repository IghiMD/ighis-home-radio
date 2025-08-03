<?php
/**
 * AUTOMATIZÁCIA NOTIFIKÁCIÍ - Webhook Integration
 * Súbor: notification_trigger.php
 * 
 * Tento súbor sa volá automaticky keď sa zmení status udalosti v admin.php
 */

require_once 'notifications.php';

function triggerNotifications($oldStatus, $newStatus, $event) {
    $subscribers = loadSubscribers();
    $results = ['email' => 0, 'whatsapp' => 0, 'errors' => []];
    
    // Určenie typu notifikácie
    $notificationType = null;
    
    if ($oldStatus !== 'announced' && $newStatus === 'announced') {
        $notificationType = 'announced';
    } elseif ($oldStatus !== 'live' && $newStatus === 'live') {
        $notificationType = 'live';
    } elseif ($oldStatus !== 'completed' && $newStatus === 'completed') {
        $notificationType = 'completed';
    }
    
    if (!$notificationType) {
        return null; // Žiadna notifikácia potrebná
    }
    
    // Odoslanie email notifikácií
    foreach ($subscribers['email'] as $subscriber) {
        if (!$subscriber['active']) continue;
        
        $template = getEmailTemplate($notificationType, $event, $subscriber['language']);
        if ($template) {
            $htmlBody = str_replace('{{EMAIL}}', $subscriber['email'], $template['html']);
            if (sendEmail($subscriber['email'], $template['subject'], $htmlBody, '')) {
                $results['email']++;
            } else {
                $results['errors'][] = "Failed to send email to {$subscriber['email']}";
            }
        }
    }
    
    // Odoslanie WhatsApp notifikácií
    foreach ($subscribers['whatsapp'] as $subscriber) {
        if (!$subscriber['active']) continue;
        
        $message = getWhatsAppMessage($notificationType, $event, $subscriber['language']);
        if ($message) {
            if (sendWhatsApp($subscriber['phone'], $message)) {
                $results['whatsapp']++;
            } else {
                $results['errors'][] = "Failed to send WhatsApp to {$subscriber['phone']}";
            }
        }
    }
    
    // Log výsledkov
    logNotification('auto_trigger', 'system', "Status changed: {$oldStatus} → {$newStatus}", 
                   "📧{$results['email']} 📱{$results['whatsapp']}");
    
    return $results;
}

// API endpoint pre automatické triggering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_notifications'])) {
    header('Content-Type: application/json');
    
    $oldStatus = $_POST['old_status'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';
    $event = json_decode($_POST['event_data'] ?? '{}', true);
    
    $results = triggerNotifications($oldStatus, $newStatus, $event);
    
    echo json_encode([
        'success' => $results !== null,
        'results' => $results,
        'message' => $results ? 
            "Sent {$results['email']} emails and {$results['whatsapp']} WhatsApp messages" : 
            'No notifications triggered'
    ]);
    exit;
}
?>

<!-- SETUP GUIDE HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Notification Setup Guide - Ighi's Home Radio</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0a0a0a; color: #fff; padding: 20px; line-height: 1.6; }
        .container { max-width: 1000px; margin: 0 auto; }
        .setup-section { background: rgba(255,255,255,0.05); padding: 30px; margin: 20px 0; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); }
        .step { background: rgba(0,255,136,0.1); padding: 20px; margin: 15px 0; border-radius: 10px; border-left: 4px solid #00ff88; }
        .code { background: #1a1a1a; padding: 15px; border-radius: 8px; font-family: monospace; overflow-x: auto; margin: 10px 0; }
        .warning { background: rgba(255,140,0,0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #ff8c00; margin: 15px 0; }
        .success { background: rgba(0,255,136,0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #00ff88; margin: 15px 0; }
        h1, h2, h3 { color: #00ff88; }
        .btn { background: linear-gradient(135deg, #00ff88, #007aff); color: #000; padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-block; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Notification System Setup Guide</h1>
        <p>Complete setup guide for Email + WhatsApp notifications</p>

        <!-- STEP 1: FILE UPLOAD -->
        <div class="setup-section">
            <h2>📥 Step 1: Upload Files</h2>
            <div class="step">
                <h3>Upload these files to your server:</h3>
                <ul>
                    <li><strong>notifications.php</strong> - Main notification system</li>
                    <li><strong>notification_trigger.php</strong> - Automation (this file)</li>
                </ul>
                <div class="code">
# Upload to your web root directory:
/public_html/
├── notifications.php
├── notification_trigger.php
├── admin.php (existing)
└── index.html (existing)
                </div>
            </div>
        </div>

        <!-- STEP 2: EMAIL SETUP -->
        <div class="setup-section">
            <h2>📧 Step 2: Email Configuration</h2>
            
            <div class="step">
                <h3>🔐 Gmail Setup (Recommended)</h3>
                <ol>
                    <li><strong>Enable 2-Factor Authentication</strong> on your Gmail account</li>
                    <li><strong>Generate App Password:</strong>
                        <ul>
                            <li>Go to: <a href="https://myaccount.google.com/apppasswords" style="color: #00ff88;">Google App Passwords</a></li>
                            <li>Select "Mail" and "Other (Custom name)"</li>
                            <li>Enter "Ighi's Radio Notifications"</li>
                            <li>Copy the 16-character password</li>
                        </ul>
                    </li>
                    <li><strong>Update notifications.php:</strong></li>
                </ol>
                <div class="code">
$EMAIL_CONFIG = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',           // ← YOUR GMAIL
    'smtp_password' => 'abcd efgh ijkl mnop',            // ← APP PASSWORD  
    'from_email' => 'hello@ighishomeradio.com',         // ← YOUR DOMAIN EMAIL
    'from_name' => "Ighi's Home Radio"
];
                </div>
            </div>

            <div class="warning">
                <strong>⚠️ Important:</strong> Never use your regular Gmail password! Always use App Password for security.
            </div>

            <div class="step">
                <h3>✉️ Alternative: Domain Email</h3>
                <p>If you have domain email (hello@ighishomeradio.com), contact your hosting provider for SMTP settings:</p>
                <div class="code">
$EMAIL_CONFIG = [
    'smtp_host' => 'mail.ighishomeradio.com',    // From hosting provider
    'smtp_port' => 587,                          // Usually 587 or 465
    'smtp_username' => 'hello@ighishomeradio.com',
    'smtp_password' => 'your-email-password',
    'from_email' => 'hello@ighishomeradio.com',
    'from_name' => "Ighi's Home Radio"
];
                </div>
            </div>
        </div>

        <!-- STEP 3: WHATSAPP SETUP -->
        <div class="setup-section">
            <h2>📱 Step 3: WhatsApp Configuration</h2>
            
            <div class="step">
                <h3>🆓 Option A: CallMeBot (Free, Simple)</h3>
                <ol>
                    <li><strong>Add CallMeBot to WhatsApp:</strong> Send "I allow callmebot to send me messages" to <strong>+34 644 93 17 89</strong></li>
                    <li><strong>Get your API Key:</strong> You'll receive a message with your API key</li>
                    <li><strong>Update notifications.php:</strong></li>
                </ol>
                <div class="code">
$WHATSAPP_CONFIG = [
    'method' => 'callmebot',
    'api_key' => 'YOUR_API_KEY_FROM_CALLMEBOT',  // ← PASTE YOUR KEY HERE
];
                </div>
                <div class="warning">
                    <strong>Limitations:</strong> CallMeBot is free but has daily limits (200 messages/day) and may be slower.
                </div>
            </div>

            <div class="step">
                <h3>💳 Option B: Twilio (Professional, Paid)</h3>
                <ol>
                    <li><strong>Create Twilio Account:</strong> <a href="https://www.twilio.com/try-twilio" style="color: #00ff88;">twilio.com/try-twilio</a></li>
                    <li><strong>Get WhatsApp Sender:</strong> Follow Twilio's WhatsApp setup guide</li>
                    <li><strong>Get Credentials:</strong> Account SID and Auth Token</li>
                    <li><strong>Update notifications.php:</strong></li>
                </ol>
                <div class="code">
$WHATSAPP_CONFIG = [
    'method' => 'twilio',
    'twilio_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',    // ← YOUR SID
    'twilio_token' => 'your_auth_token',                     // ← YOUR TOKEN
    'twilio_whatsapp' => 'whatsapp:+14155238886'             // ← TWILIO NUMBER
];
                </div>
                <div class="success">
                    <strong>Benefits:</strong> Unlimited messages, faster delivery, professional reliability. Cost: ~$0.005 per message.
                </div>
            </div>
        </div>

        <!-- STEP 4: INTEGRATION -->
        <div class="setup-section">
            <h2>🔗 Step 4: Admin Integration</h2>
            
            <div class="step">
                <h3>Automatic Notifications</h3>
                <p>Add this code to your <strong>admin.php</strong> file after successful event update:</p>
                <div class="code">
// Add this after line: if (saveEvents($events)) {
if (saveEvents($events)) {
    $updateSuccess = 'Udalosť bola úspešne aktualizovaná!';
    
    // AUTOMATIC NOTIFICATIONS
    if (isset($events['current_event']['status']) && isset($_POST['status'])) {
        $oldStatus = $events['current_event']['status'] ?? 'planning';
        $newStatus = $_POST['status'];
        
        // Trigger notifications if status changed
        if ($oldStatus !== $newStatus) {
            $triggerData = [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'event_data' => json_encode($events['current_event'])
            ];
            
            // Call notification trigger
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'notification_trigger.php');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($triggerData + ['trigger_notifications' => 1]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            
            if ($result) {
                $notifyResult = json_decode($result, true);
                if ($notifyResult['success']) {
                    $updateSuccess .= ' Notifications sent: ' . $notifyResult['message'];
                }
            }
        }
    }
} else {
    $updateError = 'Chyba pri ukladaní údajov.';
}
                </div>
            </div>
        </div>

        <!-- STEP 5: TESTING -->
        <div class="setup-section">
            <h2>🧪 Step 5: Testing</h2>
            
            <div class="step">
                <h3>Test Email Configuration</h3>
                <div class="code">
// Add this temporary test to notifications.php and visit the page:
if (isset($_GET['test_email'])) {
    $result = sendEmail('your-email@gmail.com', 'Test Email', 
                       '&lt;h1&gt;Test successful!&lt;/h1&gt;&lt;p&gt;Email configuration works.&lt;/p&gt;', 
                       'Test successful! Email configuration works.');
    echo $result ? '✅ Email sent!' : '❌ Email failed!';
    exit;
}
                </div>
                <p>Visit: <code>notifications.php?test_email=1</code></p>
            </div>

            <div class="step">
                <h3>Test WhatsApp Configuration</h3>
                <div class="code">
// Add this temporary test to notifications.php:
if (isset($_GET['test_whatsapp'])) {
    $result = sendWhatsApp('+421900123456', 'Test message from Ighi\'s Radio!');
    echo $result ? '✅ WhatsApp sent!' : '❌ WhatsApp failed!';
    exit;
}
                </div>
                <p>Visit: <code>notifications.php?test_whatsapp=1</code> (replace with your number)</p>
            </div>
        </div>

        <!-- STEP 6: FINAL -->
        <div class="setup-section">
            <h2>🎯 Step 6: Go Live!</h2>
            
            <div class="success">
                <h3>✅ Your notification system is ready when:</h3>
                <ul>
                    <li>📧 Email test works</li>
                    <li>📱 WhatsApp test works</li>
                    <li>🔗 Admin integration added</li>
                    <li>📋 Subscription form on website</li>
                </ul>
            </div>

            <div class="step">
                <h3>🚀 How it works:</h3>
                <ol>
                    <li><strong>Visitors subscribe</strong> via the form on your website</li>
                    <li><strong>You update event status</strong> in admin panel (planning → announced → live → completed)</li>
                    <li><strong>Notifications sent automatically</strong> to all subscribers in their preferred language</li>
                    <li><strong>Track results</strong> in the admin dashboard</li>
                </ol>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="notifications.php" class="btn">🔔 Open Notification System</a>
                <a href="admin.php" class="btn">📝 Manage Events</a>
                <a href="index.html" class="btn">🏠 Back to Radio</a>
            </div>
        </div>

        <!-- TROUBLESHOOTING -->
        <div class="setup-section">
            <h2>🔧 Troubleshooting</h2>
            
            <div class="warning">
                <h3>Common Issues:</h3>
                <ul>
                    <li><strong>Emails not sending:</strong> Check SMTP credentials, use App Password for Gmail</li>
                    <li><strong>WhatsApp not working:</strong> Verify API key, check phone number format (+countrycode)</li>
                    <li><strong>Notifications not triggering:</strong> Check admin.php integration code</li>
                    <li><strong>Permission errors:</strong> Ensure data/ directory exists and is writable (chmod 755)</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>