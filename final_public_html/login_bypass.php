<?php
session_start();
$_SESSION['authenticated'] = true;
$_SESSION['username'] = 'ighi';
$_SESSION['login_time'] = time();
header('Location: monitor.php');
exit();
?>
