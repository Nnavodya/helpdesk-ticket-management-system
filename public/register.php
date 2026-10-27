
<?php
session_start();

require_once __DIR__ . '/../app/config/database.php';

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || strlen($name) > 100) {
        $errors[] = 'Please enter a valid name.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150) {
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
            $check = $pdo->prepare(
                'SELECT id FROM users WHERE email = :email'
            );

            $check->execute(['email' => $email]);

            if ($check->fetch()) {
                $errors[] = 'This email is already registered.';
            } else {
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role)
                     VALUES (:name, :email, :password, :role)'
                );

                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'role' => 'employee'
                ]);

                $_SESSION['success'] =
                    'Registration successful. Please log in.';

                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] =
                    'This email may already be registered.';
            } else {
                error_log($e->getMessage());
                $errors[] =
                    'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Help Desk</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <main class="auth-container">
        <h1>Create Account</h1>
        <p>Register as an employee to submit support tickets.</p>

        <?php foreach ($errors as $error): ?>
            <p class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endforeach; ?>

        <form method="POST" action="">
            <label for="name">Full Name</label>
            <input
                type="text"
                id="name"
                name="name"
                maxlength="100"
                value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                required
            >

            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">Confirm Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <button type="submit">Register</button>
        </form>

        <p>Already have an account?
            <a href="login.php">Login</a>
        </p>
    </main>

</body>
</html>
