<?php
session_start();

// Jednoduché prihlásenie bez auth.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['username'] === 'ighi' && $_POST['password'] === 'Ighi@Kika_2025!') {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = 'ighi';
        $_SESSION['login_time'] = time();
        header('Location: monitor.php');
        exit();
    } else {
        $error = 'Nesprávne údaje';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Login</title>
</head>
<body>
    <h2>Simple Login</h2>
    <?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username"><br>
        <input type="password" name="password" placeholder="Password"><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
