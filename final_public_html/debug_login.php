<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Debug Login</h2>";
echo "ADMIN_USERNAME: " . ADMIN_USERNAME . "<br>";
echo "ADMIN_PASSWORD length: " . strlen(ADMIN_PASSWORD) . "<br>";
echo "ADMIN_PASSWORD (hidden): " . str_repeat('*', strlen(ADMIN_PASSWORD)) . "<br>";

// Test password
$testPassword = 'Ighi&Kika_2025!';
echo "<br>Test password: $testPassword<br>";
echo "Passwords match: " . (ADMIN_PASSWORD === $testPassword ? 'YES' : 'NO') . "<br>";

// Show actual comparison
echo "<br>Debug comparison:<br>";
echo "From config: '" . bin2hex(ADMIN_PASSWORD) . "'<br>";
echo "From form: '" . bin2hex($testPassword) . "'<br>";
?>
