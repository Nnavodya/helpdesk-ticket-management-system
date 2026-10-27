<?php
session_start();

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Help Desk</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <main class="auth-container">

        <h1>Welcome Back</h1>
        <p>Log in to your Help Desk account.</p>

        <?php if ($success !== ''): ?>
            <p class="success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <p class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form
            method="POST"
            action="../app/actions/login_action.php"
        >

            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Login</button>

        </form>

        <p>
            Don't have an account?
            <a href="register.php">Register here</a>
        </p>

    </main>

</body>
</html>