<?php
/**
 * Logout page for Ighi's Home Radio
 * Handles user logout and session destruction
 */

require_once '../auth.php';

// Destroy session and logout
$result = Auth::logout();

// Redirect to login page with success message
header('Location: login.php?logout=success');
exit();
?>