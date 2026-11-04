<?php
/**
 * Reset Password Page
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../app/includes/session.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . appUrl('dashboard.php'));
    exit;
}

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';

$token = trim($_GET['token'] ?? '');
$isTokenValid = false;
$resetEmail = '';
$errorMessage = '';

if ($token === '') {
    setFlash('error', 'No reset token provided. Please request a new password reset.');
    header('Location: forgot_password.php');
    exit;
}

try {
    // Check token existence and expiration
    $stmt = $pdo->prepare('
        SELECT email, expires_at 
        FROM password_resets 
        WHERE token = :token 
        LIMIT 1
    ');
    $stmt->execute(['token' => $token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetData) {
        $errorMessage = 'This password reset link is invalid or has already been used.';
    } elseif (strtotime($resetData['expires_at']) < time()) {
        $errorMessage = 'This password reset link has expired. Reset links are only valid for 1 hour.';
    } else {
        $isTokenValid = true;
        $resetEmail = $resetData['email'];
    }
} catch (PDOException $e) {
    error_log('Reset token check error: ' . $e->getMessage());
    $errorMessage = 'Unable to verify reset token. Please check your database connection.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password | Help Desk System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(appUrl('assets/css/style.css')) ?>?v=12">
</head>
<body style="background: radial-gradient(circle at top, #eef2ff 0%, #f8fafc 100%);">

    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 460px;">
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
                <h1>Set New Password</h1>
                <?php if ($isTokenValid): ?>
                    <p>Enter your new password for <strong><?= e($resetEmail) ?></strong>.</p>
                <?php else: ?>
                    <p>Password reset verification.</p>
                <?php endif; ?>
            </div>

            <!-- Flash alerts -->
            <?= renderFlash() ?>

            <?php if (!$isTokenValid): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    <div class="alert-content"><?= e($errorMessage) ?></div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <a href="forgot_password.php" class="btn btn-primary" style="width: 100%;">
                        Request a New Reset Link &rarr;
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="actions/password_reset_submit.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="At least 8 characters"
                            required
                        >
                        <span class="form-hint">Must be at least 8 characters long.</span>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Repeat new password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                        Update Password &rarr;
                    </button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p>
                    <a href="login.php" style="font-weight: 600;">&larr; Back to Sign In</a>
                </p>
            </div>
        </div>
    </div>

    <script src="<?= e(appUrl('assets/js/app.js')) ?>?v=5"></script>
</body>
</html>
