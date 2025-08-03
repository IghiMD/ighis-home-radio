<?php
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Session status: " . session_status() . "<br>";
echo "Session data: <pre>";
print_r($_SESSION);
echo "</pre>";
?>