# Vytvor test_auth.php pre debugging
<?php
require_once 'config.php';
echo "Username: " . ADMIN_USERNAME . "\n";
echo "Password: " . (empty(ADMIN_PASSWORD) ? 'EMPTY!' : 'SET') . "\n";
echo "Token: " . (empty(ADMIN_TOKEN) ? 'EMPTY!' : 'SET') . "\n";
?>
