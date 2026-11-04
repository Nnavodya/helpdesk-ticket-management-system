<?php
/**
 * Password Reset Request Action Handler
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../includes/session.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/forgot_password.php');
    exit;
}

$postedToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($postedToken)) {
    http_response_code(403);
    setFlash('error', 'Invalid security token. Please try again.');
    header('Location: ../../public/forgot_password.php');
    exit;
}

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash('error', 'Please enter a valid registered email address.');
    header('Location: ../../public/forgot_password.php');
    exit;
}

try {
    // 1. Auto-create password_resets table if not exists (Zero-config migration)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(150) NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reset_token (token),
            INDEX idx_reset_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Check if user exists and is active
    $stmt = $pdo->prepare('SELECT id, name, email, is_active FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        setFlash('error', 'No registered account found with that email address.');
        header('Location: ../../public/forgot_password.php');
        exit;
    }

    if ((int) $user['is_active'] !== 1) {
        setFlash('error', 'This account is currently inactive. Please contact your system administrator.');
        header('Location: ../../public/forgot_password.php');
        exit;
    }

    // 3. Generate secure cryptographic token (64 hex characters)
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // 4. Remove previous unused tokens for this email
    $delStmt = $pdo->prepare('DELETE FROM password_resets WHERE email = :email');
    $delStmt->execute(['email' => $email]);

    // 5. Store new reset token
    $insStmt = $pdo->prepare('
        INSERT INTO password_resets (email, token, expires_at)
        VALUES (:email, :token, :expires_at)
    ');
    $insStmt->execute([
        'email'      => $email,
        'token'      => $token,
        'expires_at' => $expiresAt,
    ]);

    // 6. Build the reset link
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $resetLink = $protocol . $host . '/helpdesk-system/public/reset_password.php?token=' . $token;

    // Store in session for local XAMPP testing (so user does not need SMTP configuration)
    $_SESSION['dev_reset_link'] = $resetLink;
    $_SESSION['dev_reset_email'] = $email;

    // Attempt native mail() if mail server is available
    @mail(
        $email,
        "Password Reset Request - HelpDesk Pro",
        "Hello " . $user['name'] . ",\n\nWe received a request to reset your password.\nPlease click the link below to set a new password:\n" . $resetLink . "\n\nThis link expires in 1 hour.\n\nIf you did not request this, please ignore this email."
    );

    setFlash('success', 'A password reset link has been generated successfully.');
    header('Location: ../../public/forgot_password.php');
    exit;

} catch (PDOException $e) {
    error_log('Password reset request error: ' . $e->getMessage());
    setFlash('error', 'Could not process password reset request. Please check your database.');
    header('Location: ../../public/forgot_password.php');
    exit;
}
