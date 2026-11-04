
<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . appUrl('tickets/index.php'));
    exit;
}

// Verify CSRF token.
$postedToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($postedToken)) {
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
    setFlash('error', 'Please select a valid ticket status.');
    header('Location: ' . appUrl('tickets/show.php') . '?id=' . $ticketId);
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
        SELECT id, ticket_number, subject, created_by, assigned_to, status
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
        setFlash('info', 'The ticket already has this status.');
        header('Location: ' . appUrl('tickets/show.php') . '?id=' . $ticketId);
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

    // In-app notification to ticket creator (if updated by someone else)
    if (!empty($ticket['created_by']) && (int) $ticket['created_by'] !== $userId) {
        createNotification(
            $pdo,
            (int) $ticket['created_by'],
            $ticketId,
            'Status Changed: ' . $ticket['ticket_number'],
            'Your ticket status was updated to "' . ucwords(str_replace('_', ' ', $newStatus)) . '" by ' . ($_SESSION['user_name'] ?? 'Staff'),
            appUrl('tickets/show.php') . '?id=' . $ticketId
        );
    }

    // In-app notification to assigned agent (if updated by someone else, e.g. Admin)
    if (!empty($ticket['assigned_to']) && (int) $ticket['assigned_to'] !== $userId) {
        createNotification(
            $pdo,
            (int) $ticket['assigned_to'],
            $ticketId,
            'Ticket Update: ' . $ticket['ticket_number'],
            'Assigned ticket status was updated to "' . ucwords(str_replace('_', ' ', $newStatus)) . '" by ' . ($_SESSION['user_name'] ?? 'Admin'),
            appUrl('tickets/show.php') . '?id=' . $ticketId
        );
    }

    setFlash('success', 'Ticket status updated to ' . ucwords(str_replace('_', ' ', $newStatus)) . ' successfully.');

    header('Location: ' . appUrl('tickets/show.php') . '?id=' . $ticketId);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Ticket status update failed: ' . $e->getMessage());

    setFlash('error', 'Could not update the ticket status. Please try again.');
    header('Location: ' . appUrl('tickets/show.php') . '?id=' . $ticketId);
    exit;
}
