<?php
/**
 * Forgot Password Page
 * Help Desk & Ticket Management System
 */

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: /helpdesk-system/public/dashboard.php');
    exit;
}

require_once __DIR__ . '/../app/includes/functions.php';

$devResetLink = $_SESSION['dev_reset_link'] ?? '';
$devResetEmail = $_SESSION['dev_reset_email'] ?? '';
unset($_SESSION['dev_reset_link'], $_SESSION['dev_reset_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Help Desk System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/helpdesk-system/public/assets/css/style.css?v=12">
</head>
<body style="background: radial-gradient(circle at top, #eef2ff 0%, #f8fafc 100%);">

    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 480px;">
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
                <h1>Reset Your Password</h1>
                <p>Enter your registered account email to generate a secure reset link.</p>
            </div>

            <!-- Flash alerts -->
            <?= renderFlash() ?>

            <!-- Local Demo / Testing Mode Banner -->
            <?php if (!empty($devResetLink)): ?>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 8px; color: #15803d; font-weight: 600; font-size: 14px; margin-bottom: 6px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <span>Local / XAMPP Testing Helper</span>
                    </div>
                    <p style="font-size: 13px; color: #166534; margin-bottom: 12px; line-height: 1.4;">
                        Since local XAMPP environments usually do not have an active SMTP mail server, your reset link for <strong><?= e($devResetEmail) ?></strong> is available right here:
                    </p>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a href="<?= e($devResetLink) ?>" class="btn btn-primary btn-sm" style="background: #15803d;">
                            Open Password Reset &rarr;
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= e($devResetLink) ?>">
                            Copy Reset Link
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="actions/password_reset_request.php">
                <?= csrfField() ?>

                <div class="form-group">
                    <label for="email">Account Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        autocomplete="email"
                        placeholder="name@company.com"
                        required
                    >
                    <span class="form-hint">A single-use reset link valid for 1 hour will be generated.</span>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                    Send Password Reset Link &rarr;
                </button>
            </form>

            <div class="auth-footer">
                <p>
                    Remembered your password?
                    <a href="login.php" style="font-weight: 600;">Back to Sign In</a>
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
