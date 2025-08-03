<?php
/**
 * EMERGENCY LOGOUT - Jednoduché odhlásenie
 * Súbor: logout.php
 */

// Začni session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vymaž session údaje
session_unset();
session_destroy();

// Nastav nové session
session_start();
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odhlásenie - Ighi's Home Radio</title>
    <meta http-equiv="refresh" content="3;url=login.php">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .logout-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #00ff88, #007aff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        p { color: #b3b3b3; margin-bottom: 30px; }
        .btn {
            background: linear-gradient(135deg, #00ff88, #007aff);
            color: #000;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 136, 0.4);
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <h1>✅ Odhlásenie úspešné</h1>
        <p>Boli ste úspešne odhlásení zo systému.<br>
        Presmerúvanie na prihlasovaciu stránku za 3 sekundy...</p>
        <a href="login.php" class="btn">🔐 Prihlásiť sa znova</a>
    </div>
</body>
</html>