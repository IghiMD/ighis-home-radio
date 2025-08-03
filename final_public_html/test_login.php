<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test Login System</h2>";

// Test 1: Auth.php
if (file_exists('auth.php')) {
    echo "✅ auth.php existuje<br>";
    try {
        require_once 'auth.php';
        echo "✅ auth.php načítaný<br>";
        
        // Test Auth class
        if (class_exists('Auth')) {
            echo "✅ Auth class existuje<br>";
        } else {
            echo "❌ Auth class neexistuje<br>";
        }
    } catch (Exception $e) {
        echo "❌ Chyba v auth.php: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ auth.php neexistuje<br>";
}

// Test 2: Login form
?>
<hr>
<h3>Prihlásenie</h3>
<form method="POST" action="login.php">
    <input type="text" name="username" value="ighi" placeholder="Username"><br>
    <input type="password" name="password" placeholder="Password"><br>
    <button type="submit" name="login">Login</button>
</form>
