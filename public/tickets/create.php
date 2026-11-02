
<?php
require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/config/database.php';

requireLogin();

$formError = $_SESSION['ticket_error'] ?? '';
unset($_SESSION['ticket_error']);

if ($formError !== '') {
    $errors[] = $formError;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$subject = '';
$description = '';
$priority = 'medium';
$categoryId = '';

try {
    $stmt = $pdo->query(
        'SELECT id, name FROM categories ORDER BY name'
    );
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $categories = [];
    $errors[] = 'Could not load ticket categories.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $categoryId = $_POST['category_id'] ?? '';
}

function ticketEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Ticket | Help Desk</title>
    <link rel="stylesheet" href="/helpdesk-system/public/assets/css/style.css?v=3">
</head>
<body>

<nav class="navbar">
    <h2>Help Desk</h2>
    <a href="../dashboard.php">Dashboard</a>
</nav>

<main class="page-container">
    <section class="card">
        <h1>Create Support Ticket</h1>
        <p class="muted">
            Describe your IT issue so the support team can help you.
        </p>

        <?php foreach ($errors as $error): ?>
            <p class="error">
                <?= ticketEscape($error) ?>
            </p>
        <?php endforeach; ?>

        <form method="POST" action="../../app/actions/ticket_create.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= ticketEscape($_SESSION['csrf_token']) ?>"
            >

            <label for="subject">Ticket Subject</label>
            <input
                type="text"
                id="subject"
                name="subject"
                maxlength="200"
                value="<?= ticketEscape($subject) ?>"
                placeholder="Example: Cannot connect to Wi-Fi"
                required
            >

            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">Select a category</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category['id'] ?>"
                        <?= (string) $categoryId === (string) $category['id'] ? 'selected' : '' ?>
                    >
                        <?= ticketEscape($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="priority">Priority</label>
            <select id="priority" name="priority" required>
                <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                <option value="critical" <?= $priority === 'critical' ? 'selected' : '' ?>>Critical</option>
            </select>

            <label for="description">Describe the Issue</label>
            <textarea
                id="description"
                name="description"
                rows="6"
                maxlength="5000"
                placeholder="Explain what happened and what you tried..."
                required
            ><?= ticketEscape($description) ?></textarea>

            <button type="submit">Submit Ticket</button>
            <a href="../dashboard.php">Cancel</a>
        </form>
    </section>
</main>

</body>
</html>
