
<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/tickets/index.php');
    exit;
}

// Verify CSRF token.
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

$ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
$newStatus = $_POST['status'] ?? '';

$allowedStatuses = [
    'open',
    'in_progress',
    'pending',
    'resolved',
    'closed'
];

if (!$ticketId || $ticketId < 1) {
    exit('Invalid ticket ID.');
}

if (!is_string($newStatus) || !in_array($newStatus, $allowedStatuses, true)) {
    $_SESSION['ticket_error'] = 'Please select a valid ticket status.';
    header('Location: ../../public/tickets/show.php?id=' . $ticketId);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

if (!in_array($userRole, ['agent', 'admin'], true)) {
    http_response_code(403);
    exit('Only agents and admins can update ticket status.');
}

try {
    // Start a transaction to keep the ticket and history consistent.
    $pdo->beginTransaction();

    // Lock the ticket while updating it.
    $sql = "
        SELECT id, status
        FROM tickets
        WHERE id = :ticket_id
    ";

    $params = [':ticket_id' => $ticketId];

    if ($userRole === 'agent') {
        $sql .= " AND assigned_to = :user_id";
        $params[':user_id'] = $userId;
    }

    $checkStmt = $pdo->prepare($sql . " FOR UPDATE");
    $checkStmt->execute($params);
    $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Ticket not found or access denied.');
    }

    $oldStatus = $ticket['status'];

    if ($oldStatus === $newStatus) {
        $pdo->commit();
        $_SESSION['ticket_success'] = 'The ticket already has this status.';
        header('Location: ../../public/tickets/show.php?id=' . $ticketId);
        exit;
    }

    // Update the ticket status.
    $updateStmt = $pdo->prepare("
        UPDATE tickets
        SET
            status = :new_status,
            updated_at = CURRENT_TIMESTAMP,
            resolved_at = CASE
                WHEN :resolved_status = 'resolved'
                    THEN COALESCE(resolved_at, CURRENT_TIMESTAMP)
                ELSE NULL
            END
        WHERE id = :ticket_id
    ");

    $updateStmt->execute([
        ':new_status' => $newStatus,
        ':resolved_status' => $newStatus,
        ':ticket_id' => $ticketId
    ]);

    // Record the change in ticket history.
    $historyStmt = $pdo->prepare("
        INSERT INTO ticket_history
            (ticket_id, changed_by, action_type, old_value, new_value, note)
        VALUES
            (:ticket_id, :changed_by, 'status_changed',
             :old_value, :new_value, 'Ticket status updated')
    ");

    $historyStmt->execute([
        ':ticket_id' => $ticketId,
        ':changed_by' => $userId,
        ':old_value' => $oldStatus,
        ':new_value' => $newStatus
    ]);

    $pdo->commit();

    $_SESSION['ticket_success'] = 'Ticket status updated successfully.';

    header('Location: ../../public/tickets/show.php?id=' . $ticketId);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Ticket status update failed: ' . $e->getMessage());

    $_SESSION['ticket_error'] = 'Could not update the ticket status. Please try again.';
    header('Location: ../../public/tickets/show.php?id=' . $ticketId);
    exit;
}
