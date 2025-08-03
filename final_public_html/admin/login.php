<?php
/**
 * Enhanced Login Page for Ighi's Home Radio
 * SECURE VERSION with CSRF protection and rate limiting
 */

require_once '../config.php';
require_once '../auth.php';

// If already authenticated, redirect
if (Auth::isAuthenticated()) {
    $redirectUrl = $_GET['redirect'] ?? 'monitor.php';
    header('Location: ' . $redirectUrl);
    exit();
}

$error = '';
$success = '';
$showForm = true;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF Protection
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::verifyCSRFToken($csrfToken)) {
            throw new Exception('Invalid CSRF token. Please refresh the page and try again.');
        }
        
        // Check rate limiting
        if (!Auth::canAttemptLogin()) {
            $timeRemaining = Auth::getTimeUntilNextAttempt();
            $minutes = ceil($timeRemaining / 60);
            throw new Exception("Too many failed login attempts. Please try again in {$minutes} minutes.");
        }
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        if (empty($username) || empty($password)) {
            throw new Exception('Please enter both username and password.');
        }
        
        // Attempt login
        $result = Auth::login($username, $password, $rememberMe);
        
        if ($result['success']) {
            $redirectUrl = $_GET['redirect'] ?? $_POST['redirect'] ?? 'monitor.php';
            
            // Prevent open redirect attacks
            if (!filter_var($redirectUrl, FILTER_VALIDATE_URL) && strpos($redirectUrl, '/') === 0) {
                // Local redirect is OK
            } else {
                $redirectUrl = 'monitor.php';
            }
            
            header('Location: ' . $redirectUrl);
            exit();
        } else {
            $error = $result['message'];
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get rate limiting info
$failedAttempts = Auth::getFailedAttempts();
$canAttempt = Auth::canAttemptLogin();
$timeUntilNext = $canAttempt ? 0 : Auth::getTimeUntilNextAttempt();

// Generate CSRF token
$csrfToken = Auth::generateCSRFToken();

// Handle logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been logged out successfully.';
}

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ighi's Home Radio Admin</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="0.5" fill="rgba(255,255,255,0.03)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #16a34a, #7c3aed);
            border: 3px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }
        
        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #16a34a, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        
        .logo-subtitle {
            color: #cbd5e1;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #e2e8f0;
            font-size: 0.95rem;
        }
        
        .form-input {
            width: 100%;
            padding: 16px 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            color: #f8fafc;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .form-input:focus {
            outline: none;
            border-color: #60a5fa;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.1);
        }
        
        .form-input::placeholder {
            color: #94a3b8;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .checkbox-input {
            width: 18px;
            height: 18px;
            accent-color: #60a5fa;
        }
        
        .checkbox-label {
            color: #cbd5e1;
            font-size: 0.9rem;
            user-select: none;
            cursor: pointer;
        }
        
        .login-button {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .login-button:hover:not(:disabled) {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(96, 165, 250, 0.3);
        }
        
        .login-button:disabled {
            background: #64748b;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid;
            font-weight: 500;
        }
        
        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .alert.success {
            background: rgba(34, 197, 94, 0.1);
            border-color: rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        
        .alert.warning {
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.3);
            color: #fbbf24;
        }
        
        .rate-limit-info {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: rgba(245, 158, 11, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .rate-limit-text {
            color: #fbbf24;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .countdown {
            font-weight: 700;
            font-size: 1.1rem;
            color: #f59e0b;
        }
        
        .back-link {
            text-align: center;
            margin-top: 30px;
        }
        
        .back-link a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .back-link a:hover {
            color: #3b82f6;
            text-decoration: underline;
        }
        
        .security-info {
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .security-info h4 {
            color: #e2e8f0;
            font-size: 0.95rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .security-features {
            list-style: none;
            color: #94a3b8;
            font-size: 0.85rem;
            line-height: 1.6;
        }
        
        .security-features li {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        
        .security-features li::before {
            content: '🔒';
            font-size: 0.8rem;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
            
            body {
                padding: 15px;
            }
        }
        
        /* Animation for rate limit countdown */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .countdown.pulse {
            animation: pulse 1s infinite;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-icon">
                <img src="https://i.imgur.com/Ym67jVJ.jpeg" alt="Ighi's Home Radio Logo" />
            </div>
            <div class="logo-text">Ighi's Home Radio</div>
            <div class="logo-subtitle">Admin Panel Access</div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert success">
                ✅ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($failedAttempts > 0 && $canAttempt): ?>
            <div class="alert warning">
                ⚠️ Failed login attempts: <?php echo $failedAttempts; ?>/5
            </div>
        <?php endif; ?>
        
        <?php if ($canAttempt): ?>
            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? 'monitor.php'); ?>">
                
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input" 
                        placeholder="Enter your username"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        required 
                        autocomplete="username"
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="Enter your password"
                        required 
                        autocomplete="current-password"
                    >
                </div>
                
                <div class="checkbox-group">
                    <input 
                        type="checkbox" 
                        id="remember_me" 
                        name="remember_me" 
                        class="checkbox-input"
                        <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>
                    >
                    <label for="remember_me" class="checkbox-label">
                        Remember me for 30 days
                    </label>
                </div>
                
                <button type="submit" class="login-button">
                    🔐 Sign In
                </button>
            </form>
        <?php else: ?>
            <div class="rate-limit-info">
                <div class="rate-limit-text">
                    Too many failed login attempts.<br>
                    Please wait before trying again.
                </div>
                <div class="countdown pulse" id="countdown">
                    <?php echo gmdate('i:s', $timeUntilNext); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="security-info">
            <h4>🛡️ Security Features</h4>
            <ul class="security-features">
                <li>CSRF protection enabled</li>
                <li>Rate limiting (5 attempts per 15 minutes)</li>
                <li>Secure session management</li>
                <li>Remember me with secure tokens</li>
                <li>Activity logging for security</li>
            </ul>
        </div>
        
        <div class="back-link">
            <a href="/">← Back to Radio</a>
        </div>
    </div>
    
    <script>
        // Countdown timer for rate limiting
        <?php if (!$canAttempt): ?>
        let timeRemaining = <?php echo $timeUntilNext; ?>;
        
        function updateCountdown() {
            if (timeRemaining <= 0) {
                location.reload();
                return;
            }
            
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            const display = minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
            
            const countdownElement = document.getElementById('countdown');
            if (countdownElement) {
                countdownElement.textContent = display;
            }
            
            timeRemaining--;
        }
        
        // Update countdown every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
        
        // Form enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const button = form.querySelector('.login-button');
                    if (button) {
                        button.disabled = true;
                        button.innerHTML = '🔄 Signing In...';
                    }
                });
            }
            
            // Auto-focus first empty field
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            
            if (username && !username.value) {
                username.focus();
            } else if (password) {
                password.focus();
            }
        });
        
        // Enhanced security: Clear form on page hide (prevent browser password managers from saving)
        window.addEventListener('beforeunload', function() {
            const passwordField = document.getElementById('password');
            if (passwordField) {
                passwordField.value = '';
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter to submit form
            if (e.ctrlKey && e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (form) {
                    form.submit();
                }
            }
        });
    </script>
</body>
</html>