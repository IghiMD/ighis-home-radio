<?php
/**
 * EMERGENCY AUTH FIX - jednoduchá verzia bez chýb
 * Nahraď obsah auth.php týmto kódom
 */

class Auth {
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function isAuthenticated() {
        self::init();
        
        // Check session
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) < 14400) {
                return true;
            }
        }
        
        // Check token (fallback)
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? $_GET['token'] ?? $_POST['token'] ?? '';
        if (!empty($token) && defined('ADMIN_TOKEN') && $token === ADMIN_TOKEN) {
            return true;
        }
        
        return false;
    }
    
    public static function require() {
        if (!self::isAuthenticated()) {
            // For AJAX requests
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Authentication required']);
                exit();
            }
            
            // Redirect to login
            header('Location: login.php');
            exit();
        }
    }
    
    public static function login($username, $password) {
        self::init();
        
        // Simple check
        if (defined('ADMIN_USERNAME') && defined('ADMIN_PASSWORD')) {
            if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
                $_SESSION['authenticated'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['login_time'] = time();
                
                return ['success' => true, 'message' => 'Login successful'];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    public static function logout() {
        self::init();
        session_unset();
        session_destroy();
        return ['success' => true, 'message' => 'Logged out'];
    }
    
    public static function generateCSRFToken() {
        self::init();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function verifyCSRFToken($token) {
        self::init();
        return isset($_SESSION['csrf_token']) && $token === $_SESSION['csrf_token'];
    }
    
    public static function getCurrentUser() {
        self::init();
        if (self::isAuthenticated()) {
            return [
                'username' => $_SESSION['username'] ?? 'admin',
                'login_time' => $_SESSION['login_time'] ?? time()
            ];
        }
        return null;
    }
    
    // Dummy methods to prevent errors
    public static function getFailedAttempts() { return 0; }
    public static function canAttemptLogin() { return true; }
    public static function getTimeUntilNextAttempt() { return 0; }
    public static function checkRememberMe() { return false; }
}
?>