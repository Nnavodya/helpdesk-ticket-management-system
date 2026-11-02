
<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/tickets/index.php');
    exit;
}

// Check CSRF token.
$sessionToken = $_SESSION['csrf_token'] ?? '';
$postedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($postedToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $postedToken)
) {
    http_response_code(403);
    exit('Invalid security token. Please go back and try again.');
}

// Validate ticket ID.
$ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);

if (!$ticketId || $ticketId < 1) {
    exit('Invalid ticket ID.');
}

// Validate comment.
$body = trim($_POST['body'] ?? '');

if ($body === '' || strlen($body) > 3000) {
    $_SESSION['comment_error'] =
        'Comment is required and must not exceed 3000 bytes.';

    header('Location: ../../public/tickets/show.php?id=' . $ticketId);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

try {
    // Check that the user is allowed to access this ticket.
    $sql = "
        SELECT id
        FROM tickets
        WHERE id = :ticket_id
    ";

    $params = [':ticket_id' => $ticketId];

    if ($userRole === 'employee') {
        $sql .= " AND created_by = :user_id";
        $params[':user_id'] = $userId;
    } elseif ($userRole === 'agent') {
        $sql .= " AND assigned_to = :user_id";
        $params[':user_id'] = $userId;
    } elseif ($userRole !== 'admin') {
        http_response_code(403);
        exit('You do not have permission to comment on this ticket.');
    }

    $check = $pdo->prepare($sql);
    $check->execute($params);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        exit('Ticket not found or access denied.');
    }

    // Save the comment and its activity record together.
    $pdo->beginTransaction();

    $commentStmt = $pdo->prepare("
        INSERT INTO ticket_comments (ticket_id, user_id, body)
        VALUES (:ticket_id, :user_id, :body)
    ");

    $commentStmt->execute([
        ':ticket_id' => $ticketId,
        ':user_id' => $userId,
        ':body' => $body
    ]);

    $historyStmt = $pdo->prepare("
        INSERT INTO ticket_history
            (ticket_id, changed_by, action_type, note)
        VALUES
            (:ticket_id, :changed_by, 'comment_added', 'Comment added')
    ");

    $historyStmt->execute([
        ':ticket_id' => $ticketId,
        ':changed_by' => $userId
    ]);

    // Update the ticket's last-updated time.
    $updateStmt = $pdo->prepare("
        UPDATE tickets
        SET updated_at = CURRENT_TIMESTAMP
        WHERE id = :ticket_id
    ");

    $updateStmt->execute([':ticket_id' => $ticketId]);

    $pdo->commit();

    $_SESSION['comment_success'] = 'Your comment was added successfully.';

    header('Location: ../../public/tickets/show.php?id=' . $ticketId);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Comment creation failed: ' . $e->getMessage());

    $_SESSION['comment_error'] =
        'Could not save the comment. Please check the database setup and try again.';

    header('Location: ../../public/tickets/show.php?id=' . $ticketId);
    exit;
}
