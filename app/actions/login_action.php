<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $_SESSION['error'] = 'Please enter your email and password.';
    header('Location: ../../public/login.php');
    exit;
}

try {
    // Find the account using the submitted email.
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password, role, is_active
         FROM users
         WHERE email = :email
         LIMIT 1'
    );

    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Do not allow invalid passwords or inactive accounts to log in.
    if (!$user || !password_verify($password, $user['password'])
        || (int) $user['is_active'] !== 1) {

        $_SESSION['error'] = 'Invalid email or password, or account is inactive.';
        header('Location: ../../public/login.php');
        exit;
    }

    // Generate a new session ID after successful authentication.
    session_regenerate_id(true);

    // Store only the user information needed by the application.
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    header('Location: ../../public/dashboard.php');
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());

    $_SESSION['error'] = 'Something went wrong. Please try again.';
    header('Location: ../../public/login.php');
    exit;
}