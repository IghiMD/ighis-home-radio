<?php
/**
 * EMERGENCY LOGIN - Jednoduchá prihlasoacia stránka
 * Súbor: login.php
 */

// Zabráň error outputu
error_reporting(0);
ini_set('display_errors', 0);

// Začni session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ak je už prihlásený, presmeruj
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: monitor.php');
    exit();
}

// Pokús sa načítať config
$config_loaded = false;
if (file_exists('config.php')) {
    try {
        require_once 'config.php';
        $config_loaded = true;
    } catch (Exception $e) {
        // Ignoruj chyby
    }
}

// Definuj konštanty ak neexistujú
if (!defined('ADMIN_USERNAME')) {
    define('ADMIN_USERNAME', 'ighi');
}
if (!defined('ADMIN_PASSWORD')) {
    define('ADMIN_PASSWORD', 'Ighi@Kika_2025!');
}

$error = '';
$success = '';

// Spracovanie prihlásenia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Overenie prihlasovacích údajov
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        
        $success = 'Prihlásenie úspešné! Presmerovanie...';
        
        // Presmeruj po 2 sekundách
        header('refresh:2;url=monitor.php');
    } else {
        $error = 'Nesprávne prihlasovacie údaje!';
    }
}

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prihlásenie - Ighi's Home Radio Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            text-align: center;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #00ff88;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #00ff88, #007aff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            color: #b3b3b3;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #00ff88;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #00ff88;
            box-shadow: 0 0 10px rgba(0, 255, 136, 0.3);
        }
        
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #00ff88, #007aff);
            color: #000;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 136, 0.4);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .alert-error {
            background: rgba(255, 45, 85, 0.2);
            border: 1px solid #ff2d55;
            color: #ff2d55;
        }
        
        .alert-success {
            background: rgba(0, 255, 136, 0.2);
            border: 1px solid #00ff88;
            color: #00ff88;
        }
        
        .config-status {
            margin-top: 20px;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .config-ok {
            background: rgba(0, 255, 136, 0.1);
            color: #00ff88;
        }
        
        .config-error {
            background: rgba(255, 45, 85, 0.1);
            color: #ff2d55;
        }
        
        .default-creds {
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 140, 0, 0.1);
            border: 1px solid #ff8c00;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #ff8c00;
        }
        
        .default-creds h4 {
            margin-bottom: 10px;
        }
        
        .default-creds code {
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        
        .links {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .links a {
            color: #007aff;
            text-decoration: none;
            margin: 0 10px;
            font-size: 0.9rem;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="https://i.imgur.com/Ym67jVJ.jpeg" alt="Ighi's Home Radio" />
        </div>
        
        <h1>Admin Login</h1>
        <p class="subtitle">Ighi's Home Radio System</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Používateľské meno:</label>
                <input type="text" id="username" name="username" required value="ighi" 
                       placeholder="Zadajte používateľské meno">
            </div>
            
            <div class="form-group">
                <label for="password">Heslo:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Zadajte heslo">
            </div>
            
            <button type="submit" name="login" class="btn">
                🔐 Prihlásiť sa
            </button>
        </form>
        
        <!-- Konfiguračný status -->
        <div class="config-status <?php echo $config_loaded ? 'config-ok' : 'config-error'; ?>">
            <?php if ($config_loaded): ?>
                ✅ Konfigurácia načítaná úspešne
            <?php else: ?>
                ⚠️ Konfigurácia nenačítaná - používajú sa default hodnoty
            <?php endif; ?>
        </div>
        
        <div class="links">
            <a href="index.html">← Späť na rádio</a>
            <a href="monitor.php">📊 Monitor</a>
            <a href="admin.php">⚙️ Admin</a>
        </div>
    </div>
</body>
</html>
