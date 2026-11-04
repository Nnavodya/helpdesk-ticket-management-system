<?php
/**
 * Password Reset Submission Action Handler
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

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($token === '') {
    setFlash('error', 'Invalid reset token.');
    header('Location: ../../public/forgot_password.php');
    exit;
}

if (strlen($password) < 8) {
    setFlash('error', 'Password must be at least 8 characters long.');
    header('Location: ../../public/reset_password.php?token=' . urlencode($token));
    exit;
}

if ($password !== $confirmPassword) {
    setFlash('error', 'Passwords do not match. Please re-enter.');
    header('Location: ../../public/reset_password.php?token=' . urlencode($token));
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT email, expires_at 
        FROM password_resets 
        WHERE token = :token 
        LIMIT 1
    ');
    $stmt->execute(['token' => $token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetData || strtotime($resetData['expires_at']) < time()) {
        setFlash('error', 'This reset link has expired or is invalid. Please request a new one.');
        header('Location: ../../public/forgot_password.php');
        exit;
    }

    $email = $resetData['email'];

    // Transaction to update password and remove reset tokens
    $pdo->beginTransaction();

    $newHashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $updateStmt = $pdo->prepare('
        UPDATE users 
        SET password = :password 
        WHERE email = :email
    ');
    $updateStmt->execute([
        'password' => $newHashedPassword,
        'email'    => $email,
    ]);

    // Invalidate/delete tokens for this email
    $delStmt = $pdo->prepare('DELETE FROM password_resets WHERE email = :email');
    $delStmt->execute(['email' => $email]);

    $pdo->commit();

    setFlash('success', 'Your password has been reset successfully! You can now log in.');
    header('Location: ../../public/login.php');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Password reset submission error: ' . $e->getMessage());
    setFlash('error', 'Could not update password. Please try again.');
    header('Location: ../../public/reset_password.php?token=' . urlencode($token));
    exit;
}
