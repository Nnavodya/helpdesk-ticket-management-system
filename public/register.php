<?php
/**
 * User Registration Page
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../app/includes/session.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /helpdesk-system/public/dashboard.php');
    exit;
}

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Please enter a valid full name (max 100 characters).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
            $check->execute(['email' => $email]);

            if ($check->fetch()) {
                $errors[] = 'This email address is already registered. Please sign in instead.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare('
                    INSERT INTO users (name, email, password, role, is_active)
                    VALUES (:name, :email, :password, :role, 1)
                ');

                $stmt->execute([
                    'name'     => $name,
                    'email'    => $email,
                    'password' => $hashedPassword,
                    'role'     => 'employee',
                ]);

                $_SESSION['success'] = 'Account registered successfully! You can now log in.';
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Registration error: ' . $e->getMessage());
            $errors[] = 'Registration failed due to a database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Registration | Help Desk System</title>
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
                <h1>Create Account</h1>
                <p>Register as an employee to submit IT support tickets.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <div class="alert-content">
                        <?php foreach ($errors as $err): ?>
                            <div>&bull; <?= e($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="100"
                        value="<?= e($name) ?>"
                        placeholder="John Doe"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Work Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        value="<?= e($email) ?>"
                        placeholder="john.doe@company.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password (min 8 characters)</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 8px;">
                    Register Account &rarr;
                </button>
            </form>

            <div class="auth-footer">
                <p>
                    Already have an account?
                    <a href="login.php" style="font-weight: 600;">Sign in here</a>
                </p>
                <div style="margin-top: 14px;">
                    <a href="index.php" style="color: var(--text-muted); font-size: 13px;">&larr; Back to Public Portal</a>
                </div>
            </div>
        </div>
    </div>

    <script src="/helpdesk-system/public/assets/js/app.js?v=5"></script>
</body>
</html>
