
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

function redirectWithError(string $message): void
{
    $_SESSION['ticket_error'] = $message;
    header('Location: ../../public/tickets/create.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/tickets/create.php');
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit('Invalid request. Please return to the form and try again.');
}

$subject = trim($_POST['subject'] ?? '');
$description = trim($_POST['description'] ?? '');
$categoryId = filter_var(
    $_POST['category_id'] ?? null,
    FILTER_VALIDATE_INT
);
$priority = $_POST['priority'] ?? 'medium';

if ($subject === '' || strlen($subject) > 200) {
    redirectWithError('Enter a subject of up to 200 characters.');
}

if ($description === '' || strlen($description) > 5000) {
    redirectWithError('Enter a description of up to 5000 characters.');
}

if (!$categoryId || $categoryId < 1) {
    redirectWithError('Please select a valid category.');
}

$allowedPriorities = ['low', 'medium', 'high', 'critical'];

if (!in_array($priority, $allowedPriorities, true)) {
    redirectWithError('Please select a valid priority.');
}

try {
    // Confirm that the selected category exists.
    $check = $pdo->prepare(
        'SELECT id FROM categories WHERE id = :id'
    );
    $check->execute(['id' => $categoryId]);

    if (!$check->fetch()) {
        redirectWithError('The selected category does not exist.');
    }

    // Generate a unique ticket reference.
    $ticketNumber = 'HD-' . date('Ymd') . '-' .
        strtoupper(bin2hex(random_bytes(4)));

    $stmt = $pdo->prepare(
        'INSERT INTO tickets
            (ticket_number, created_by, category_id, subject,
             description, priority, status)
         VALUES
            (:ticket_number, :created_by, :category_id, :subject,
             :description, :priority, :status)'
    );

    $stmt->execute([
        'ticket_number' => $ticketNumber,
        'created_by' => (int) $_SESSION['user_id'],
        'category_id' => $categoryId,
        'subject' => $subject,
        'description' => $description,
        'priority' => $priority,
        'status' => 'open'
    ]);

    $ticketId = (int) $pdo->lastInsertId();

    // Record ticket creation in the activity history.
    $history = $pdo->prepare(
        'INSERT INTO ticket_history
            (ticket_id, changed_by, action_type, note)
         VALUES
            (:ticket_id, :changed_by, :action_type, :note)'
    );

    $history->execute([
        'ticket_id' => $ticketId,
        'changed_by' => (int) $_SESSION['user_id'],
        'action_type' => 'created',
        'note' => 'Ticket created'
    ]);

    $_SESSION['ticket_success'] =
        'Ticket ' . $ticketNumber . ' was created successfully.';

    header('Location: ../../public/tickets/index.php');
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    redirectWithError('Could not create the ticket. Please try again.');
}
