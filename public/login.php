<?php
/**
 * User Login Page
 * Help Desk & Ticket Management System
 */

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: /helpdesk-system/public/dashboard.php');
    exit;
}

require_once __DIR__ . '/../app/includes/functions.php';

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
    <title>Sign In | Help Desk System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/helpdesk-system/public/assets/css/style.css?v=11">
</head>
<body style="background: radial-gradient(circle at top, #eef2ff 0%, #f8fafc 100%);">

    <div class="auth-wrapper">
        <div class="auth-card">
            <!-- Brand Logo Header -->
            <div class="auth-header">
                <a href="index.php" style="display: inline-flex; align-items: center; gap: 10px; margin-bottom: 16px; text-decoration: none;">
                    <span class="brand-icon" style="width: 40px; height: 40px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                    </span>
                    <span style="font-size: 20px; font-weight: 700; color: var(--text-main);">HelpDesk Pro</span>
                </a>
                <h1>Welcome Back</h1>
                <p>Log in to manage your tickets and support requests.</p>
            </div>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success">
                    <div class="alert-content"><?= e($success) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error">
                    <div class="alert-content"><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="../app/actions/login_action.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        autocomplete="username"
                        placeholder="name@company.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                        <label for="password">Password</label>
                        <a href="forgot_password.php" style="font-size: 13px; color: var(--primary); font-weight: 500;">Forgot password?</a>
                    </div>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 8px;">
                    Sign In &rarr;
                </button>
            </form>

            <div class="auth-footer">
                <p>
                    Don't have an account?
                    <a href="register.php" style="font-weight: 600;">Register as Employee</a>
                </p>
                <div style="margin-top: 14px;">
                    <a href="index.php" style="color: var(--text-muted); font-size: 13px;">&larr; Back to Public Portal</a>
                </div>
            </div>
        </div>
    </div>

    <script src="/helpdesk-system/public/assets/js/app.js?v=4"></script>
</body>
</html>